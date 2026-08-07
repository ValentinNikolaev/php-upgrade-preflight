<?php

declare(strict_types=1);

namespace PhpUpgradePreflight\Cli\Tests\Unit;

use PhpUpgradePreflight\Cli\AnalyzeCommand;
use PhpUpgradePreflight\Cli\AnalyzerFactory;
use PhpUpgradePreflight\Cli\FrameworkIntegrationRegistry;
use PhpUpgradePreflight\Core\Analysis\FrameworkRuleEngine;
use PhpUpgradePreflight\Core\Contracts\UpgradeAnalyzer;
use PhpUpgradePreflight\Core\Framework\FrameworkIntegration;
use PhpUpgradePreflight\Core\Model\Blocker;
use PhpUpgradePreflight\Core\Model\ComposerJson;
use PhpUpgradePreflight\Core\Model\ComposerLock;
use PhpUpgradePreflight\Core\Model\EffortEstimate;
use PhpUpgradePreflight\Core\Model\Evidence;
use PhpUpgradePreflight\Core\Model\LockDiff;
use PhpUpgradePreflight\Core\Model\ProjectState;
use PhpUpgradePreflight\Core\Model\ReportFormat;
use PhpUpgradePreflight\Core\Model\RiskSummary;
use PhpUpgradePreflight\Core\Model\UpgradeReport;
use PhpUpgradePreflight\Core\Model\UpgradeRequest;
use PHPUnit\Framework\TestCase;
use Symfony\Component\Filesystem\Filesystem;

final class AnalyzeCommandTest extends TestCase
{
    /** @var resource */
    private $stdout;
    /** @var resource */
    private $stderr;
    private FakeUpgradeAnalyzer $analyzer;
    private AnalyzeCommand $command;

    protected function setUp(): void
    {
        parent::setUp();

        $stdout = fopen('php://memory', 'w+');
        $stderr = fopen('php://memory', 'w+');
        if ($stdout === false || $stderr === false) {
            throw new \RuntimeException('Unable to open in-memory test streams.');
        }

        $this->stdout = $stdout;
        $this->stderr = $stderr;
        $this->analyzer = new FakeUpgradeAnalyzer();
        $this->command = new AnalyzeCommand($this->analyzer, $this->stdout, $this->stderr);
    }

    protected function tearDown(): void
    {
        fclose($this->stdout);
        fclose($this->stderr);

        parent::tearDown();
    }

    public function testItParsesOptionsAndPassesARequestToTheAnalyzer(): void
    {
        $exitCode = $this->command->run([
            'upgrade-intel',
            'analyze',
            '--path=' . dirname(__DIR__, 4),
            '--target=laravel/framework:^9.0',
            '--target=php:8.1',
            '--from-php=7.4',
            '--source=packages/cli/src',
            '--framework=laravel',
            '--format=markdown',
            '--debug',
        ]);

        self::assertSame(0, $exitCode);
        self::assertNotNull($this->analyzer->request);
        self::assertSame(['laravel/framework', 'php'], array_map(
            static fn ($target): string => $target->package(),
            $this->analyzer->request->targets()->all()
        ));
        self::assertSame('8.1.0', $this->analyzer->request->targetPhp());
        self::assertSame('7.4', $this->analyzer->request->fromPhp());
        self::assertSame(['packages/cli/src'], $this->analyzer->request->sourcePaths());
        self::assertSame(['laravel'], $this->analyzer->request->frameworks());
        self::assertSame(ReportFormat::MARKDOWN, $this->analyzer->request->format());
        self::assertTrue($this->analyzer->request->debug());
        self::assertStringStartsWith('# PHP Upgrade Preflight', $this->streamContents($this->stdout));
        self::assertSame('', $this->streamContents($this->stderr));
    }

    public function testItReturnsFailureForInvalidInputWithoutCallingTheAnalyzer(): void
    {
        $exitCode = $this->command->run(['upgrade-intel', 'analyze']);

        self::assertSame(AnalyzeCommand::INVALID, $exitCode);
        self::assertNull($this->analyzer->request);
        self::assertStringContainsString('At least one --target', $this->streamContents($this->stderr));
    }

    public function testItCanonicalizesRepeatedFrameworkOptionsInTheReportRequest(): void
    {
        $exitCode = $this->command->run([
            'upgrade-intel',
            'analyze',
            '--path=' . dirname(__DIR__, 4),
            '--target=fixture/dependency:^2.0',
            '--framework=Laravel',
            '--framework=laravel',
        ]);

        self::assertSame(0, $exitCode);
        self::assertNotNull($this->analyzer->request);
        self::assertSame(['laravel'], $this->analyzer->request->frameworks());
    }

    public function testItPrintsHelpWithoutCallingTheAnalyzer(): void
    {
        $exitCode = $this->command->run(['upgrade-intel', '--help']);

        self::assertSame(0, $exitCode);
        self::assertNull($this->analyzer->request);
        self::assertStringStartsWith('Usage:', $this->streamContents($this->stdout));
    }

    public function testItRejectsAnOutputPathInsideTheAnalyzedProject(): void
    {
        $projectPath = dirname(__DIR__, 4);
        $composerPath = $projectPath . DIRECTORY_SEPARATOR . 'composer.json';
        $before = file_get_contents($composerPath);

        $exitCode = $this->command->run([
            'upgrade-intel',
            'analyze',
            '--path=' . $projectPath,
            '--target=fixture/dependency:^2.0',
            '--output=' . $composerPath,
        ]);

        self::assertSame(AnalyzeCommand::INVALID, $exitCode);
        self::assertSame($before, file_get_contents($composerPath));
        self::assertStringContainsString('outside the analyzed project', $this->streamContents($this->stderr));
    }

    public function testItValidatesTheOutputDestinationBeforeRunningAnalysis(): void
    {
        $exitCode = $this->command->run([
            'upgrade-intel',
            'analyze',
            '--path=' . dirname(__DIR__, 4),
            '--target-php=8.2',
            '--output=' . sys_get_temp_dir() . DIRECTORY_SEPARATOR . 'missing-' . bin2hex(random_bytes(8)) . DIRECTORY_SEPARATOR . 'report.json',
        ]);

        self::assertSame(AnalyzeCommand::INVALID, $exitCode);
        self::assertNull($this->analyzer->request);
        self::assertStringContainsString('output directory', strtolower($this->streamContents($this->stderr)));
    }

    /**
     * @dataProvider invalidInvocationProvider
     * @param list<string> $arguments
     */
    public function testItReturnsTheInvalidInvocationExitCodeForBadInputs(array $arguments, string $message): void
    {
        $exitCode = $this->command->run(array_merge(['upgrade-intel', 'analyze'], $arguments));

        self::assertSame(AnalyzeCommand::INVALID, $exitCode);
        self::assertNull($this->analyzer->request);
        self::assertStringStartsWith('Invalid invocation:', $this->streamContents($this->stderr));
        self::assertStringContainsString($message, $this->streamContents($this->stderr));
    }

    /** @return list<array{list<string>, string}> */
    public function invalidInvocationProvider(): array
    {
        $projectPath = dirname(__DIR__, 4);

        return [
            [['--path=' . $projectPath . DIRECTORY_SEPARATOR . 'missing', '--target-php=8.2'], 'Project path'],
            [['--path=' . $projectPath, '--target=invalid:^2.0'], 'Invalid Composer target package'],
            [['--path=' . $projectPath, '--target-php=not-a-version'], 'PHP target'],
            [['--path=' . $projectPath, '--target-php=8.2', '--from-php=^7.4'], 'Current PHP version'],
            [['--path=' . $projectPath, '--target=php:8.1', '--target-php=8.2'], 'Conflicting PHP targets'],
            [['--path=' . $projectPath, '--target-php=8.2', '--source=missing'], 'Source path'],
        ];
    }

    public function testItRejectsAnUnavailableRequestedFrameworkBeforeAnalysis(): void
    {
        $command = new AnalyzeCommand(
            $this->analyzer,
            $this->stdout,
            $this->stderr,
            null,
            null,
            new FrameworkIntegrationRegistry([])
        );

        $exitCode = $command->run([
            'upgrade-intel',
            'analyze',
            '--path=' . dirname(__DIR__, 4),
            '--target-php=8.2',
            '--framework=laravel',
        ]);

        self::assertSame(AnalyzeCommand::INVALID, $exitCode);
        self::assertNull($this->analyzer->request);
        self::assertStringContainsString('Install the matching adapter package', $this->streamContents($this->stderr));
    }

    public function testItReturnsFailureForAnInternalAnalyzerError(): void
    {
        $command = new AnalyzeCommand(new ThrowingUpgradeAnalyzer(), $this->stdout, $this->stderr);

        $exitCode = $command->run([
            'upgrade-intel',
            'analyze',
            '--path=' . dirname(__DIR__, 4),
            '--target-php=8.2',
        ]);

        self::assertSame(AnalyzeCommand::FAILURE, $exitCode);
        self::assertStringContainsString('Analysis failed: unexpected failure', $this->streamContents($this->stderr));
    }

    public function testItTreatsAnInternalInvalidArgumentExceptionAsAnAnalysisFailure(): void
    {
        $command = new AnalyzeCommand(new InvalidArgumentThrowingUpgradeAnalyzer(), $this->stdout, $this->stderr);

        $exitCode = $command->run([
            'upgrade-intel',
            'analyze',
            '--path=' . dirname(__DIR__, 4),
            '--target-php=8.2',
        ]);

        self::assertSame(AnalyzeCommand::FAILURE, $exitCode);
        self::assertStringContainsString('Analysis failed: internal invariant failed', $this->streamContents($this->stderr));
        self::assertStringNotContainsString('Invalid invocation:', $this->streamContents($this->stderr));
    }

    public function testGenericCliPassesInstalledIntegrationsThroughAutomaticDetection(): void
    {
        $factory = new DetectingAnalyzerFactory();
        $command = new AnalyzeCommand(
            null,
            $this->stdout,
            $this->stderr,
            null,
            null,
            new FrameworkIntegrationRegistry(),
            $factory
        );

        $exitCode = $command->run([
            'upgrade-intel',
            'analyze',
            '--path=' . dirname(__DIR__, 4),
            '--target=laravel/framework:^9.0',
        ]);

        self::assertSame(AnalyzeCommand::SUCCESS, $exitCode);
        self::assertSame(['laravel'], $factory->registeredNames);
        self::assertSame(['laravel'], $factory->detectedNames);
    }

    public function testACompletedBlockedAnalysisReturnsSuccessAndCanonicalStatus(): void
    {
        $command = new AnalyzeCommand(new BlockedUpgradeAnalyzer(), $this->stdout, $this->stderr);

        $exitCode = $command->run([
            'upgrade-intel',
            'analyze',
            '--path=' . dirname(__DIR__, 4),
            '--target-php=8.2',
        ]);
        /** @var array<string, mixed> $report */
        $report = json_decode($this->streamContents($this->stdout), true, 512, JSON_THROW_ON_ERROR);

        self::assertSame(AnalyzeCommand::SUCCESS, $exitCode);
        self::assertSame('blocked', $report['resolution']['status']);
        self::assertSame('', $this->streamContents($this->stderr));
    }

    public function testDefaultAnalyzerRendersMissingLockfileAsStructuredJson(): void
    {
        $projectPath = rtrim(sys_get_temp_dir(), DIRECTORY_SEPARATOR) . DIRECTORY_SEPARATOR . 'php-upgrade-preflight-cli-input-' . bin2hex(random_bytes(8));
        mkdir($projectPath, 0700, true);
        file_put_contents($projectPath . DIRECTORY_SEPARATOR . 'composer.json', json_encode([
            'require' => ['fixture/dependency' => '^1.0'],
        ], JSON_THROW_ON_ERROR));
        $command = new AnalyzeCommand(null, $this->stdout, $this->stderr);

        try {
            $exitCode = $command->run([
                'upgrade-intel',
                'analyze',
                '--path=' . $projectPath,
                '--target=fixture/dependency:^2.0',
            ]);
            /** @var array<string, mixed> $report */
            $report = json_decode($this->streamContents($this->stdout), true, 512, JSON_THROW_ON_ERROR);

            self::assertSame(0, $exitCode);
            self::assertSame('', $this->streamContents($this->stderr));
            self::assertSame('0.6', $report['metadata']['schema_version']);
            self::assertSame('unknown', $report['resolution']['status']);
            self::assertSame('lockfile_missing', $report['resolution']['scenarios'][0]['outcome']);
        } finally {
            (new Filesystem())->remove($projectPath);
        }
    }

    /** @param resource $stream */
    private function streamContents($stream): string
    {
        rewind($stream);
        $contents = stream_get_contents($stream);

        self::assertIsString($contents);

        return $contents;
    }
}

final class FakeUpgradeAnalyzer implements UpgradeAnalyzer
{
    public ?UpgradeRequest $request = null;

    public function analyzeUpgrade(UpgradeRequest $request): UpgradeReport
    {
        $this->request = $request;

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

final class ThrowingUpgradeAnalyzer implements UpgradeAnalyzer
{
    public function analyzeUpgrade(UpgradeRequest $request): UpgradeReport
    {
        throw new \RuntimeException('unexpected failure');
    }
}

final class InvalidArgumentThrowingUpgradeAnalyzer implements UpgradeAnalyzer
{
    public function analyzeUpgrade(UpgradeRequest $request): UpgradeReport
    {
        throw new \InvalidArgumentException('internal invariant failed');
    }
}

final class DetectingAnalyzerFactory implements AnalyzerFactory
{
    /** @var list<string> */
    public array $registeredNames = [];
    /** @var list<string> */
    public array $detectedNames = [];

    public function create(array $integrations): UpgradeAnalyzer
    {
        $this->registeredNames = array_map(
            static fn (FrameworkIntegration $integration): string => $integration->name(),
            $integrations
        );

        return new FrameworkDetectingUpgradeAnalyzer($this, $integrations);
    }
}

final class FrameworkDetectingUpgradeAnalyzer implements UpgradeAnalyzer
{
    private DetectingAnalyzerFactory $factory;
    /** @var list<FrameworkIntegration> */
    private array $integrations;

    /** @param list<FrameworkIntegration> $integrations */
    public function __construct(DetectingAnalyzerFactory $factory, array $integrations)
    {
        $this->factory = $factory;
        $this->integrations = $integrations;
    }

    public function analyzeUpgrade(UpgradeRequest $request): UpgradeReport
    {
        $project = new ProjectState(
            $request->projectPath(),
            new ComposerJson(['require' => ['laravel/framework' => '^7.0']]),
            new ComposerLock(['packages' => [['name' => 'laravel/framework', 'version' => 'v7.30.7']]])
        );
        $active = (new FrameworkRuleEngine($this->integrations))->activeIntegrations($project, $request);
        $this->factory->detectedNames = array_map(
            static fn (FrameworkIntegration $integration): string => $integration->name(),
            $active
        );

        return new UpgradeReport(
            $request,
            $project,
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

final class BlockedUpgradeAnalyzer implements UpgradeAnalyzer
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
