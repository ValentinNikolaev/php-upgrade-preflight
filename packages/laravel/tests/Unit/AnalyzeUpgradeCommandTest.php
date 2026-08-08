<?php

declare(strict_types=1);

namespace PhpUpgradePreflight\Laravel\Tests\Unit;

use Illuminate\Console\OutputStyle;
use Illuminate\Contracts\Foundation\Application;
use PhpUpgradePreflight\Core\Contracts\UpgradeAnalyzer;
use PhpUpgradePreflight\Core\Model\ComposerJson;
use PhpUpgradePreflight\Core\Model\ComposerLock;
use PhpUpgradePreflight\Core\Model\EffortEstimate;
use PhpUpgradePreflight\Core\Model\LockDiff;
use PhpUpgradePreflight\Core\Model\ProjectState;
use PhpUpgradePreflight\Core\Model\ReportFormat;
use PhpUpgradePreflight\Core\Model\RiskSummary;
use PhpUpgradePreflight\Core\Model\UpgradeReport;
use PhpUpgradePreflight\Core\Model\UpgradeRequest;
use PhpUpgradePreflight\Laravel\Commands\AnalyzeUpgradeCommand;
use PHPUnit\Framework\TestCase;
use Symfony\Component\Console\Command\Command;
use Symfony\Component\Console\Tester\CommandTester;

final class AnalyzeUpgradeCommandTest extends TestCase
{
    public function testItDefaultsToTheCurrentProjectAndDelegatesToTheAnalyzer(): void
    {
        $projectPath = dirname(__DIR__, 4) . '/tests/fixtures/laravel-app';
        $analyzer = new RecordingUpgradeAnalyzer();
        $tester = $this->commandTester($analyzer, $projectPath);

        $exitCode = $tester->execute([
            '--target-php' => '8.2',
            '--from-php' => '7.4',
            '--source' => ['app'],
            '--format' => 'markdown',
        ], ['capture_stderr_separately' => true]);

        self::assertSame(Command::SUCCESS, $exitCode);
        self::assertNotNull($analyzer->request);
        self::assertNotSame(realpath(getcwd() ?: '.'), $analyzer->request->projectPath());
        self::assertSame(realpath($projectPath), $analyzer->request->projectPath());
        self::assertSame('8.2.0', $analyzer->request->targetPhp());
        self::assertSame('7.4', $analyzer->request->fromPhp());
        self::assertSame(['app'], $analyzer->request->sourcePaths());
        self::assertSame(['laravel'], $analyzer->request->frameworks());
        self::assertSame(ReportFormat::MARKDOWN, $analyzer->request->format());
        self::assertStringStartsWith('# PHP Upgrade Preflight', $tester->getDisplay());
        self::assertStringContainsString('Literal <info>canonical text</info> remains unchanged.', $tester->getDisplay());
        self::assertSame('', $tester->getErrorOutput());
    }

    /**
     * @dataProvider invalidInvocationProvider
     * @param array<string, mixed> $input
     */
    public function testItReturnsInvalidForBadInvocationWithoutRunningAnalysis(array $input, string $message): void
    {
        $analyzer = new RecordingUpgradeAnalyzer();
        $tester = $this->commandTester($analyzer);

        $exitCode = $tester->execute($input, ['capture_stderr_separately' => true]);

        self::assertSame(Command::INVALID, $exitCode);
        self::assertNull($analyzer->request);
        self::assertSame('', $tester->getDisplay());
        self::assertStringContainsString('Invalid invocation:', $tester->getErrorOutput());
        self::assertStringContainsString($message, $tester->getErrorOutput());
    }

    /** @return list<array{array<string, mixed>, string}> */
    public function invalidInvocationProvider(): array
    {
        $projectPath = dirname(__DIR__, 4);

        return [
            [[], 'At least one --target'],
            [['--path' => $projectPath . DIRECTORY_SEPARATOR . 'missing', '--target-php' => '8.2'], 'Project path'],
            [['--path' => $projectPath, '--target' => ['invalid:^2.0']], 'Invalid Composer target package'],
            [['--path' => $projectPath, '--target-php' => '8.2', '--from-php' => '^7.4'], 'Current PHP version'],
            [['--path' => $projectPath, '--target' => ['php:8.1'], '--target-php' => '8.2'], 'Conflicting PHP targets'],
            [['--path' => $projectPath, '--target-php' => '8.2', '--source' => ['missing']], 'Source path'],
            [['--path' => $projectPath, '--target-php' => '8.2', '--format' => 'yaml'], 'Unsupported report format'],
            [[
                '--path' => $projectPath,
                '--target-php' => '8.2',
                '--output' => sys_get_temp_dir() . DIRECTORY_SEPARATOR . 'missing-' . bin2hex(random_bytes(8)) . DIRECTORY_SEPARATOR . 'report.json',
            ], 'output directory'],
        ];
    }

    public function testItReturnsFailureForAnInternalAnalyzerError(): void
    {
        $tester = $this->commandTester(new FailingUpgradeAnalyzer());

        $exitCode = $tester->execute([
            '--path' => dirname(__DIR__, 4),
            '--target-php' => '8.2',
        ], ['capture_stderr_separately' => true]);

        self::assertSame(Command::FAILURE, $exitCode);
        self::assertSame('', $tester->getDisplay());
        self::assertStringContainsString('Analysis failed: unexpected failure', $tester->getErrorOutput());
    }

    public function testItTreatsAnInternalInvalidArgumentExceptionAsAnAnalysisFailure(): void
    {
        $tester = $this->commandTester(new InvalidArgumentFailingUpgradeAnalyzer());

        $exitCode = $tester->execute([
            '--path' => dirname(__DIR__, 4),
            '--target-php' => '8.2',
        ], ['capture_stderr_separately' => true]);

        self::assertSame(Command::FAILURE, $exitCode);
        self::assertSame('', $tester->getDisplay());
        self::assertStringContainsString('Analysis failed: internal invariant failed', $tester->getErrorOutput());
        self::assertStringNotContainsString('Invalid invocation:', $tester->getErrorOutput());
    }

    public function testItRedactsSensitiveValuesFromDiagnosticOutput(): void
    {
        $fixturePath = dirname(__DIR__, 4) . '/tests/fixtures/security/composer-output-with-secrets.json';
        $contents = file_get_contents($fixturePath);
        self::assertNotFalse($contents);
        $fixture = json_decode($contents, true, 512, JSON_THROW_ON_ERROR);
        self::assertIsArray($fixture);
        self::assertIsArray($fixture['canaries'] ?? null);
        self::assertIsString($fixture['stderr'] ?? null);

        $tester = $this->commandTester(new MessageFailingUpgradeAnalyzer($fixture['stderr']));
        $exitCode = $tester->execute([
            '--path' => dirname(__DIR__, 4),
            '--target-php' => '8.2',
        ], ['capture_stderr_separately' => true]);
        $diagnostic = $tester->getErrorOutput();

        self::assertSame(Command::FAILURE, $exitCode);
        foreach ($fixture['canaries'] as $label => $canary) {
            if (str_contains($diagnostic, $canary)) {
                self::fail(sprintf('Sensitive canary %s reached Artisan diagnostics.', $label));
            }
        }
    }

    private function commandTester(UpgradeAnalyzer $analyzer, ?string $basePath = null): CommandTester
    {
        $basePath = $basePath ?? dirname(__DIR__, 4);
        $application = $this->createMock(Application::class);
        $application->method('basePath')->willReturn($basePath);
        $application->method('make')->willReturnCallback(
            static fn (string $abstract, array $parameters): OutputStyle => new OutputStyle(
                $parameters['input'],
                $parameters['output']
            )
        );
        $application->method('call')->willReturnCallback(
            static fn (callable $callback): int => (int) $callback()
        );

        $command = new AnalyzeUpgradeCommand($analyzer);
        $command->setLaravel($application);

        return new CommandTester($command);
    }
}

final class RecordingUpgradeAnalyzer implements UpgradeAnalyzer
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
            ['Literal <info>canonical text</info> remains unchanged.'],
            []
        );
    }
}

final class FailingUpgradeAnalyzer implements UpgradeAnalyzer
{
    public function analyzeUpgrade(UpgradeRequest $request): UpgradeReport
    {
        throw new \RuntimeException('unexpected failure');
    }
}

final class InvalidArgumentFailingUpgradeAnalyzer implements UpgradeAnalyzer
{
    public function analyzeUpgrade(UpgradeRequest $request): UpgradeReport
    {
        throw new \InvalidArgumentException('internal invariant failed');
    }
}

final class MessageFailingUpgradeAnalyzer implements UpgradeAnalyzer
{
    private string $message;

    public function __construct(string $message)
    {
        $this->message = $message;
    }

    public function analyzeUpgrade(UpgradeRequest $request): UpgradeReport
    {
        throw new \RuntimeException($this->message);
    }
}
