<?php

declare(strict_types=1);

namespace PhpUpgradePreflight\Laravel\Tests\Unit;

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
use PhpUpgradePreflight\Core\Reporting\JsonReportWriter;
use PhpUpgradePreflight\Core\Reporting\MarkdownReportWriter;
use PhpUpgradePreflight\Core\Reporting\ReportWriter;
use PhpUpgradePreflight\Core\Reporting\ReportWriterResolver;
use PhpUpgradePreflight\Laravel\Commands\AnalyzeUpgradeCommand;
use PHPUnit\Framework\TestCase;
use Symfony\Component\Console\Command\Command;
use Symfony\Component\Console\Style\SymfonyStyle;
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
            '--with-extension' => ['ext-intl:72.1'],
            '--without-extension' => ['ext-xdebug'],
            '--composer-mode' => 'restricted',
            '--composer-executable' => '/tools/composer.phar',
            '--composer-version' => '^2.8',
            '--composer-timeout' => '120',
            '--composer-diagnostic-timeout' => '15',
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
        self::assertSame(['ext-intl', 'ext-xdebug'], array_map(
            static fn ($assumption): string => $assumption->name(),
            $analyzer->request->extensionAssumptions()
        ));
        self::assertSame(ReportFormat::MARKDOWN, $analyzer->request->format());
        self::assertSame('restricted', $analyzer->request->composerExecution()->mode());
        self::assertSame('/tools/composer.phar', $analyzer->request->composerExecution()->executable());
        self::assertSame('^2.8', $analyzer->request->composerExecution()->expectedVersion());
        self::assertSame(120, $analyzer->request->composerExecution()->scenarioTimeoutSeconds());
        self::assertSame(15, $analyzer->request->composerExecution()->diagnosticTimeoutSeconds());
        self::assertStringStartsWith('# PHP Upgrade Preflight', $tester->getDisplay());
        self::assertStringContainsString('Literal <info>canonical text</info> remains unchanged.', $tester->getDisplay());
        self::assertSame('', $tester->getErrorOutput());
    }

    /**
     * The Artisan entry point renders through the shared core resolver, so a given `--format`
     * value must produce exactly what the corresponding writer produces. Nothing between the
     * analyzer and stdout is allowed to reshape the canonical projection.
     */
    public function testItRendersTheCanonicalJsonProjectionByteForByte(): void
    {
        $analyzer = new RecordingUpgradeAnalyzer();

        $display = $this->renderedReport($analyzer, ReportFormat::JSON);

        self::assertNotNull($analyzer->report);
        self::assertSame($this->canonicalLines((new JsonReportWriter())->render($analyzer->report)), $display);
    }

    public function testItRendersTheMarkdownProjectionByteForByte(): void
    {
        $analyzer = new RecordingUpgradeAnalyzer();

        $display = $this->renderedReport($analyzer, ReportFormat::MARKDOWN);

        self::assertNotNull($analyzer->report);
        self::assertSame($this->canonicalLines((new MarkdownReportWriter())->render($analyzer->report)), $display);
    }

    /** An unknown format never reaches rendering, so the resolver's JSON fallback stays reachable only from core. */
    public function testTheSharedResolverBacksBothArtisanProjections(): void
    {
        $resolver = new ReportWriterResolver();

        self::assertInstanceOf(ReportWriter::class, $resolver->resolve(ReportFormat::JSON));
        self::assertInstanceOf(JsonReportWriter::class, $resolver->resolve(ReportFormat::JSON));
        self::assertInstanceOf(MarkdownReportWriter::class, $resolver->resolve(ReportFormat::MARKDOWN));
    }

    private function renderedReport(RecordingUpgradeAnalyzer $analyzer, string $format): string
    {
        $tester = $this->commandTester($analyzer);

        $exitCode = $tester->execute([
            '--path' => dirname(__DIR__, 4),
            '--target-php' => '8.2',
            '--format' => $format,
        ], ['capture_stderr_separately' => true]);

        self::assertSame(Command::SUCCESS, $exitCode);
        self::assertSame('', $tester->getErrorOutput());

        return $this->canonicalLines($tester->getDisplay());
    }

    /**
     * The writers join their lines with `PHP_EOL`, so a Windows host renders both the
     * console display and the writer output with CRLF. Comparing the projections is a
     * comparison of their content, so both sides are read with the same line endings.
     */
    private function canonicalLines(string $value): string
    {
        return trim(str_replace("\r\n", "\n", $value));
    }

    public function testItLoadsAProfileAndPassesItToTheAnalyzer(): void
    {
        $analyzer = new RecordingUpgradeAnalyzer();
        $tester = $this->commandTester($analyzer);
        $profilePath = dirname(__DIR__, 4) . '/tests/fixtures/platform-profiles/complete-php-83.json';

        $exitCode = $tester->execute([
            '--target-platform-profile' => [$profilePath],
        ], ['capture_stderr_separately' => true]);

        self::assertSame(Command::SUCCESS, $exitCode);
        self::assertNotNull($analyzer->request);
        self::assertNotNull($analyzer->request->targetPlatformProfile());
        self::assertSame('file', $analyzer->request->targetPlatformProfile()->provenance());
        self::assertSame('1.0', $analyzer->request->targetPlatformProfile()->schemaVersion());
        self::assertSame('8.3.0', $analyzer->request->targetPhp());
        self::assertSame('', $tester->getErrorOutput());
    }

    /** @dataProvider invalidProfileProvider */
    public function testItRejectsInvalidProfilesWithoutDisclosingTheirPath(string $profilePath, string $message): void
    {
        $analyzer = new RecordingUpgradeAnalyzer();
        $tester = $this->commandTester($analyzer);

        $exitCode = $tester->execute([
            '--target-platform-profile' => [$profilePath],
        ], ['capture_stderr_separately' => true]);
        $diagnostic = $tester->getErrorOutput();

        self::assertSame(Command::INVALID, $exitCode);
        self::assertNull($analyzer->request);
        self::assertStringStartsWith('Invalid invocation:', $diagnostic);
        self::assertStringContainsString($message, $diagnostic);
        self::assertStringNotContainsString($profilePath, $diagnostic);
    }

    /** @return list<array{string, string}> */
    public function invalidProfileProvider(): array
    {
        return [
            [
                dirname(__DIR__, 4) . '/tests/fixtures/platform-profiles/missing-secret-profile.json',
                'Target platform profile file could not be read.',
            ],
            [
                dirname(__DIR__, 4) . '/tests/fixtures/platform-profiles',
                'Target platform profile file could not be read.',
            ],
            [
                dirname(__DIR__, 4) . '/tests/fixtures/platform-profiles/malformed.json',
                'Target platform profile contains invalid JSON.',
            ],
            [
                dirname(__DIR__, 4) . '/tests/fixtures/platform-profiles/invalid-package-name.json',
                'Target platform package name is unsupported.',
            ],
        ];
    }

    /**
     * @dataProvider conflictingProfileInputProvider
     * @param array<string, mixed> $input
     */
    public function testItRejectsProfileConflictsBeforeRunningAnalysis(array $input, string $message): void
    {
        $analyzer = new RecordingUpgradeAnalyzer();
        $tester = $this->commandTester($analyzer);

        $exitCode = $tester->execute($input, ['capture_stderr_separately' => true]);

        self::assertSame(Command::INVALID, $exitCode);
        self::assertNull($analyzer->request);
        self::assertStringStartsWith('Invalid invocation:', $tester->getErrorOutput());
        self::assertStringContainsString($message, $tester->getErrorOutput());
    }

    /** @return list<array{array<string, mixed>, string}> */
    public function conflictingProfileInputProvider(): array
    {
        return [
            [[
                '--target-platform-profile' => [
                    dirname(__DIR__, 4) . '/tests/fixtures/platform-profiles/partial-php-83-ext-json.json',
                ],
                '--without-extension' => ['ext-json'],
            ], 'contradicts the target platform profile'],
            [[
                '--target-platform-profile' => [
                    dirname(__DIR__, 4) . '/tests/fixtures/platform-profiles/partial-php-83-ext-json.json',
                ],
                '--with-extension' => ['ext-json:8.2.0'],
            ], 'contradicts the target platform profile'],
            [[
                '--target-platform-profile' => [
                    dirname(__DIR__, 4) . '/tests/fixtures/platform-profiles/complete-php-83.json',
                ],
                '--with-extension' => ['ext-json'],
            ], 'presence-only'],
        ];
    }

    public function testItRejectsRepeatedProfileOptionsWithoutRunningAnalysis(): void
    {
        $analyzer = new RecordingUpgradeAnalyzer();
        $tester = $this->commandTester($analyzer);

        $exitCode = $tester->execute([
            '--target-platform-profile' => ['first.json', 'second.json'],
        ], ['capture_stderr_separately' => true]);

        self::assertSame(Command::INVALID, $exitCode);
        self::assertNull($analyzer->request);
        self::assertStringContainsString('may only be specified once', $tester->getErrorOutput());
        self::assertStringNotContainsString('first.json', $tester->getErrorOutput());
        self::assertStringNotContainsString('second.json', $tester->getErrorOutput());
    }

    public function testItRejectsANonStringProfileOptionWithoutRunningAnalysis(): void
    {
        $analyzer = new RecordingUpgradeAnalyzer();
        $tester = $this->commandTester($analyzer);

        $exitCode = $tester->execute([
            '--target-platform-profile' => [123],
        ], ['capture_stderr_separately' => true]);

        self::assertSame(Command::INVALID, $exitCode);
        self::assertNull($analyzer->request);
        self::assertStringContainsString('must be a string', $tester->getErrorOutput());
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
                '--with-extension' => ['ext-json'],
                '--without-extension' => ['EXT-JSON'],
            ], 'may only be specified once'],
            [[
                '--path' => $projectPath,
                '--target-php' => '8.2',
                '--with-extension' => ['ext-a..b'],
            ], 'must use Composer ext-name syntax'],
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
            static fn (string $abstract, array $parameters): SymfonyStyle => new SymfonyStyle(
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
    public ?UpgradeReport $report = null;

    public function analyzeUpgrade(UpgradeRequest $request): UpgradeReport
    {
        $this->request = $request;

        return $this->report = new UpgradeReport(
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
