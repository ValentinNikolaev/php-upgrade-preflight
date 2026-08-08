<?php

declare(strict_types=1);

namespace PhpUpgradePreflight\Tests\Release;

use PhpUpgradePreflight\Cli\AnalyzeCommand;
use PhpUpgradePreflight\Cli\CommandLineParser;
use PhpUpgradePreflight\Core\Contracts\UpgradeAnalyzer;
use PhpUpgradePreflight\Core\Model\Blocker;
use PhpUpgradePreflight\Core\Model\ComposerJson;
use PhpUpgradePreflight\Core\Model\ComposerLock;
use PhpUpgradePreflight\Core\Model\EffortEstimate;
use PhpUpgradePreflight\Core\Model\Evidence;
use PhpUpgradePreflight\Core\Model\LockDiff;
use PhpUpgradePreflight\Core\Model\ProjectState;
use PhpUpgradePreflight\Core\Model\RiskSummary;
use PhpUpgradePreflight\Core\Model\UpgradeReport;
use PhpUpgradePreflight\Core\Model\UpgradeRequest;
use PHPUnit\Framework\TestCase;

final class V01CompatibilityContractTest extends TestCase
{
    private string $root;

    /** @var array<string, mixed> */
    private array $contract;

    protected function setUp(): void
    {
        parent::setUp();

        $this->root = dirname(__DIR__, 2);
        $contents = file_get_contents($this->root . '/tests/fixtures/contracts/v0.1.json');
        self::assertIsString($contents);
        $contract = json_decode($contents, true, 512, JSON_THROW_ON_ERROR);
        self::assertIsArray($contract);
        $this->contract = $contract;
    }

    public function testItLocksTheOnePublicPhpOperation(): void
    {
        $expected = $this->contract['public_php_operation'];
        self::assertIsArray($expected);
        self::assertIsString($expected['interface']);
        self::assertIsString($expected['method']);

        $interface = new \ReflectionClass($expected['interface']);
        $method = $interface->getMethod($expected['method']);
        $parameters = array_map(static function (\ReflectionParameter $parameter): array {
            $type = $parameter->getType();
            self::assertInstanceOf(\ReflectionNamedType::class, $type);

            return [
                'name' => $parameter->getName(),
                'type' => $type->getName(),
                'optional' => $parameter->isOptional(),
                'by_reference' => $parameter->isPassedByReference(),
                'variadic' => $parameter->isVariadic(),
            ];
        }, $method->getParameters());
        $returnType = $method->getReturnType();
        self::assertInstanceOf(\ReflectionNamedType::class, $returnType);

        self::assertTrue($interface->isInterface());
        self::assertSame($expected, [
            'interface' => $interface->getName(),
            'method' => $method->getName(),
            'public' => $method->isPublic(),
            'static' => $method->isStatic(),
            'parameters' => $parameters,
            'return_type' => $returnType->getName(),
        ]);
    }

    public function testItLocksTheDocumentedCliArgumentsAndParseShape(): void
    {
        $cli = $this->contract['cli'];
        self::assertIsArray($cli);
        self::assertIsString($cli['help']);
        self::assertIsArray($cli['arguments']);
        self::assertIsArray($cli['probe_argv']);
        self::assertIsArray($cli['probe_result']);
        self::assertIsArray($cli['default_probe_result']);

        [$exitCode, $stdout, $stderr] = $this->runCommand(
            new V01BlockedAnalyzer(),
            [(string) $cli['binary'], '--help']
        );

        self::assertSame(AnalyzeCommand::SUCCESS, $exitCode);
        self::assertSame($cli['help'], $stdout);
        self::assertSame('', $stderr);
        [$shortHelpExit, $shortHelpOutput, $shortHelpError] = $this->runCommand(
            new V01BlockedAnalyzer(),
            [(string) $cli['binary'], '-h']
        );
        self::assertSame(AnalyzeCommand::SUCCESS, $shortHelpExit);
        self::assertSame($cli['help'], $shortHelpOutput);
        self::assertSame('', $shortHelpError);
        foreach ($cli['arguments'] as $argument) {
            self::assertIsArray($argument);
            self::assertIsString($argument['syntax']);
            self::assertStringContainsString($argument['syntax'], $stdout);
        }

        $argv = array_map(
            fn (string $argument): string => str_replace('<PROJECT_PATH>', $this->root, $argument),
            $cli['probe_argv']
        );
        $actual = (new CommandLineParser())->parse($argv);
        $actual['path'] = $this->normalizeContractPath($actual['path']);
        self::assertIsString($actual['output']);
        $actual['output'] = $this->normalizeContractPath($actual['output']);

        self::assertSame($cli['probe_result'], $actual);

        $defaults = (new CommandLineParser())->parse(['upgrade-intel', 'analyze', '--target-php=8.2']);
        $currentDirectory = getcwd();
        self::assertIsString($currentDirectory);
        self::assertSame($currentDirectory, $defaults['path']);
        $defaults['path'] = '<CURRENT_DIRECTORY>';
        self::assertSame($cli['default_probe_result'], $defaults);
    }

    public function testItLocksTheExitPolicyIncludingValidBlockedAndUnknownReports(): void
    {
        $policy = $this->contract['exit_policy'];
        self::assertIsArray($policy);
        self::assertIsArray($policy['success']);
        self::assertIsArray($policy['failure']);
        self::assertIsArray($policy['invalid']);

        self::assertSame($policy['success']['code'], AnalyzeCommand::SUCCESS);
        self::assertSame($policy['failure']['code'], AnalyzeCommand::FAILURE);
        self::assertSame($policy['invalid']['code'], AnalyzeCommand::INVALID);

        [$blockedExit, $blockedOutput, $blockedError] = $this->runCommand(new V01BlockedAnalyzer(), [
            'upgrade-intel',
            'analyze',
            '--path=' . $this->root,
            '--target-php=8.2',
        ]);
        $blockedReport = json_decode($blockedOutput, true, 512, JSON_THROW_ON_ERROR);
        self::assertIsArray($blockedReport);
        self::assertIsArray($blockedReport['resolution']);
        self::assertSame($policy['success']['code'], $blockedExit);
        self::assertSame('blocked', $blockedReport['resolution']['status']);
        self::assertSame('', $blockedError);

        [$unknownExit, $unknownOutput, $unknownError] = $this->runCommand(new V01UnknownAnalyzer(), [
            'upgrade-intel',
            'analyze',
            '--path=' . $this->root,
            '--target-php=8.2',
        ]);
        $unknownReport = json_decode($unknownOutput, true, 512, JSON_THROW_ON_ERROR);
        self::assertIsArray($unknownReport);
        self::assertIsArray($unknownReport['resolution']);
        self::assertSame($policy['success']['code'], $unknownExit);
        self::assertSame('unknown', $unknownReport['resolution']['status']);
        self::assertSame('', $unknownError);

        [$failureExit, , $failureError] = $this->runCommand(new V01FailingAnalyzer(), [
            'upgrade-intel',
            'analyze',
            '--path=' . $this->root,
            '--target-php=8.2',
        ]);
        self::assertSame($policy['failure']['code'], $failureExit);
        self::assertStringStartsWith('Analysis failed:', $failureError);

        [$invalidExit, , $invalidError] = $this->runCommand(
            new V01BlockedAnalyzer(),
            ['upgrade-intel', 'analyze']
        );
        self::assertSame($policy['invalid']['code'], $invalidExit);
        self::assertStringStartsWith('Invalid invocation:', $invalidError);
    }

    public function testItLocksSchemaV06AndAllSixApprovedLaravelReports(): void
    {
        self::assertSame('0.1.0', $this->contract['released_version']);
        self::assertSame('a8d154826f35fcb25a22868556534cc8c0331c0c', $this->contract['released_commit']);

        $schema = $this->contract['canonical_schema'];
        self::assertIsArray($schema);
        self::assertSame('0.6', $schema['version']);
        $this->assertLockedFile($schema);
        $schemaContents = file_get_contents($this->root . '/' . $schema['path']);
        self::assertIsString($schemaContents);
        $publishedSchema = json_decode($schemaContents, true, 512, JSON_THROW_ON_ERROR);
        self::assertIsArray($publishedSchema);
        self::assertSame(
            $schema['version'],
            $publishedSchema['$defs']['metadata']['properties']['schema_version']['const']
        );

        $reports = $this->contract['approved_laravel_reports'];
        self::assertIsArray($reports);
        self::assertCount(6, $reports);
        $fixtureNames = [];

        foreach ($reports as $report) {
            self::assertIsArray($report);
            self::assertIsString($report['fixture']);
            self::assertIsArray($report['json']);
            self::assertIsArray($report['markdown']);
            self::assertIsString($report['json']['path']);
            self::assertIsString($report['markdown']['path']);
            $fixtureNames[] = $report['fixture'];
            self::assertStringStartsWith(
                'tests/fixtures/contracts/v0.1/laravel-reports/',
                $report['json']['path']
            );
            self::assertStringStartsWith(
                'tests/fixtures/contracts/v0.1/laravel-reports/',
                $report['markdown']['path']
            );
            $this->assertLockedFile($report['json']);
            $this->assertLockedFile($report['markdown']);

            $json = file_get_contents($this->root . '/' . $report['json']['path']);
            self::assertIsString($json);
            $canonical = json_decode($json, true, 512, JSON_THROW_ON_ERROR);
            self::assertIsArray($canonical);
            self::assertIsArray($canonical['metadata']);
            self::assertSame('0.6', $canonical['metadata']['schema_version']);

            $markdown = file_get_contents($this->root . '/' . $report['markdown']['path']);
            self::assertIsString($markdown);
            self::assertStringContainsString('Schema: `0.6`', $markdown);
        }

        self::assertSame($fixtureNames, array_values(array_unique($fixtureNames)));
    }

    /** @param array{path: mixed, sha256: mixed} $lockedFile */
    private function assertLockedFile(array $lockedFile): void
    {
        self::assertIsString($lockedFile['path']);
        self::assertIsString($lockedFile['sha256']);
        $path = $this->root . '/' . $lockedFile['path'];
        self::assertFileExists($path);
        self::assertSame($lockedFile['sha256'], hash_file('sha256', $path));
    }

    private function normalizeContractPath(string $path): string
    {
        return str_replace('\\', '/', str_replace($this->root, '<PROJECT_PATH>', $path));
    }

    /**
     * @param list<string> $argv
     * @return array{int, string, string}
     */
    private function runCommand(UpgradeAnalyzer $analyzer, array $argv): array
    {
        $stdout = fopen('php://memory', 'w+');
        $stderr = fopen('php://memory', 'w+');
        self::assertIsResource($stdout);
        self::assertIsResource($stderr);

        try {
            $exitCode = (new AnalyzeCommand($analyzer, $stdout, $stderr))->run($argv);
            rewind($stdout);
            rewind($stderr);
            $output = stream_get_contents($stdout);
            $error = stream_get_contents($stderr);
            self::assertIsString($output);
            self::assertIsString($error);

            return [$exitCode, $output, $error];
        } finally {
            fclose($stdout);
            fclose($stderr);
        }
    }
}

final class V01BlockedAnalyzer implements UpgradeAnalyzer
{
    public function analyzeUpgrade(UpgradeRequest $request): UpgradeReport
    {
        return new UpgradeReport(
            $request,
            new ProjectState($request->projectPath(), new ComposerJson([]), new ComposerLock([])),
            [],
            new LockDiff([]),
            [new Blocker('conflict', 'php', 'Target PHP is blocked.', 'high', ['solver-1'])],
            [],
            [],
            new RiskSummary('high', ['Composer resolution is blocked.']),
            new EffortEstimate([1, 2], 'medium', [], []),
            [],
            [new Evidence('solver-1', Evidence::E1_SOLVER, 'Composer rejected the target.')]
        );
    }
}

final class V01UnknownAnalyzer implements UpgradeAnalyzer
{
    public function analyzeUpgrade(UpgradeRequest $request): UpgradeReport
    {
        return new UpgradeReport(
            $request,
            new ProjectState($request->projectPath(), new ComposerJson([]), new ComposerLock([])),
            [],
            new LockDiff([]),
            [],
            [],
            [],
            new RiskSummary('low', []),
            new EffortEstimate([0, 0], 'high', [], []),
            [],
            []
        );
    }
}

final class V01FailingAnalyzer implements UpgradeAnalyzer
{
    public function analyzeUpgrade(UpgradeRequest $request): UpgradeReport
    {
        throw new \RuntimeException('compatibility fixture failure');
    }
}
