<?php

declare(strict_types=1);

namespace PhpUpgradePreflight\Laravel\Tests\Integration;

use PhpUpgradePreflight\Core\Support\PathExposurePolicy;
use PhpUpgradePreflight\Tests\Support\FixtureSnapshot;
use PHPUnit\Framework\TestCase;
use Symfony\Component\Console\Command\Command;
use Symfony\Component\Filesystem\Filesystem;
use Symfony\Component\Process\Process;

final class CommandEntryPointParityTest extends TestCase
{
    private string $temporaryDirectory;
    private string $fixturePath;
    private string $projectPath;

    protected function setUp(): void
    {
        parent::setUp();

        $this->temporaryDirectory = rtrim(sys_get_temp_dir(), DIRECTORY_SEPARATOR)
            . DIRECTORY_SEPARATOR
            . 'php upgrade preflight command parity '
            . bin2hex(random_bytes(8));
        $this->fixturePath = $this->temporaryDirectory . DIRECTORY_SEPARATOR . 'path-repository';
        $this->projectPath = $this->fixturePath . DIRECTORY_SEPARATOR . 'project';

        (new Filesystem())->mirror(
            dirname(__DIR__, 4) . DIRECTORY_SEPARATOR . 'tests' . DIRECTORY_SEPARATOR . 'fixtures' . DIRECTORY_SEPARATOR . 'path-repository',
            $this->fixturePath
        );
    }

    protected function tearDown(): void
    {
        (new Filesystem())->remove($this->temporaryDirectory);

        parent::tearDown();
    }

    /** @dataProvider completedAnalysisProvider */
    public function testCliAndArtisanProduceEquivalentCanonicalFilesAndExitPolicy(
        string $target,
        string $expectedStatus
    ): void {
        if (!$this->composerIsAvailable()) {
            self::markTestSkipped('Composer is required for the command entry-point parity test.');
        }

        $snapshot = FixtureSnapshot::capture($this->fixturePath);
        $cliOutputPath = $this->temporaryDirectory . DIRECTORY_SEPARATOR . 'cli-report.json';
        $artisanOutputPath = $this->temporaryDirectory . DIRECTORY_SEPARATOR . 'artisan-report.json';

        $cli = $this->runCli([
            '--path=' . $this->projectPath,
            '--target=fixture/dependency:' . $target,
            '--framework=laravel',
            '--format=json',
            '--output=' . $cliOutputPath,
        ]);
        $artisan = $this->runArtisan([
            '--target=fixture/dependency:' . $target,
            '--format=json',
            '--output=' . $artisanOutputPath,
        ]);

        self::assertSame(Command::SUCCESS, $cli->getExitCode(), $cli->getErrorOutput());
        self::assertSame(Command::SUCCESS, $artisan->getExitCode(), $artisan->getErrorOutput());
        self::assertSame('', $cli->getErrorOutput());
        self::assertSame('', $artisan->getErrorOutput());
        self::assertStringContainsString('Wrote report to', $cli->getOutput());
        self::assertStringContainsString('Wrote report to', $artisan->getOutput());
        self::assertFileExists($cliOutputPath);
        self::assertFileExists($artisanOutputPath);

        $cliReport = $this->decodeReport($cliOutputPath);
        $artisanReport = $this->decodeReport($artisanOutputPath);
        self::assertSame(PathExposurePolicy::REPORT_OUTPUT, $cliReport['request_summary']['output_path']);
        self::assertSame(PathExposurePolicy::REPORT_OUTPUT, $artisanReport['request_summary']['output_path']);
        self::assertSame(PathExposurePolicy::PROJECT_ROOT, $cliReport['request_summary']['project_path']);
        self::assertSame(PathExposurePolicy::PROJECT_ROOT, $artisanReport['request_summary']['project_path']);

        $cliReport = $this->normalizeReport($cliReport);
        $artisanReport = $this->normalizeReport($artisanReport);

        self::assertSame($cliReport, $artisanReport);
        self::assertSame('0.7', $cliReport['metadata']['schema_version']);
        self::assertSame($expectedStatus, $cliReport['resolution']['status']);
        self::assertNotSame([], $cliReport['resolution']['scenarios']);
        self::assertNotSame('project-input', $cliReport['resolution']['scenarios'][0]['name']);
        self::assertNotNull($cliReport['resolution']['scenarios'][0]['composer_version']);
        self::assertSame('composer', $cliReport['resolution']['scenarios'][0]['command'][0]);
        self::assertSame(['laravel'], $cliReport['request_summary']['frameworks']);

        if ($expectedStatus === 'feasible_with_changes') {
            self::assertSame('fixture/dependency', $cliReport['transition']['package_changes'][0]['name']);
            self::assertSame('1.0.0', $cliReport['transition']['package_changes'][0]['from_version']);
            self::assertSame('2.0.0', $cliReport['transition']['package_changes'][0]['to_version']);
            self::assertSame([], $cliReport['blockers']);
        } else {
            self::assertSame('package-not-found', $cliReport['blockers'][0]['type']);
            self::assertSame('fixture/dependency', $cliReport['blockers'][0]['subject']);
            self::assertSame([], $cliReport['transition']['package_changes']);
        }

        $snapshot->assertUnchanged($this);
    }

    /** @return list<array{string, string}> */
    public function completedAnalysisProvider(): array
    {
        return [
            ['^2.0', 'feasible_with_changes'],
            ['^3.0', 'blocked'],
        ];
    }

    public function testCliAndArtisanReturnTheSameInvalidInvocationExitCodeWithoutWritingReports(): void
    {
        $cli = $this->runCli(['--path=' . $this->projectPath]);
        $artisan = $this->runArtisan([]);

        self::assertSame(Command::INVALID, $cli->getExitCode());
        self::assertSame(Command::INVALID, $artisan->getExitCode());
        self::assertSame('', $cli->getOutput());
        self::assertSame('', $artisan->getOutput());
        self::assertStringContainsString('Invalid invocation:', $cli->getErrorOutput());
        self::assertStringContainsString('Invalid invocation:', $artisan->getErrorOutput());
        self::assertSame([], glob($this->temporaryDirectory . DIRECTORY_SEPARATOR . '*-report.json') ?: []);
    }

    /** @param list<string> $arguments */
    private function runCli(array $arguments): Process
    {
        return $this->runProcess(array_merge([
            PHP_BINARY,
            $this->repositoryRoot() . DIRECTORY_SEPARATOR . 'packages' . DIRECTORY_SEPARATOR . 'cli' . DIRECTORY_SEPARATOR . 'bin' . DIRECTORY_SEPARATOR . 'upgrade-intel',
            'analyze',
        ], $arguments));
    }

    /** @param list<string> $arguments */
    private function runArtisan(array $arguments): Process
    {
        return $this->runProcess(array_merge([
            PHP_BINARY,
            $this->repositoryRoot() . DIRECTORY_SEPARATOR . 'tests' . DIRECTORY_SEPARATOR . 'fixtures' . DIRECTORY_SEPARATOR . 'artisan-harness' . DIRECTORY_SEPARATOR . 'artisan',
            'upgrade:analyze',
        ], $arguments));
    }

    /** @param list<string> $command */
    private function runProcess(array $command): Process
    {
        $process = new Process($command, $this->repositoryRoot(), [
            'COMPOSER_DISABLE_NETWORK' => '1',
            'PHP_UPGRADE_PREFLIGHT_TEST_PROJECT_PATH' => $this->projectPath,
        ]);
        $process->setTimeout(180);
        $process->run();

        return $process;
    }

    /** @return array<string, mixed> */
    private function decodeReport(string $path): array
    {
        $contents = file_get_contents($path);
        self::assertIsString($contents);

        /** @var array<string, mixed> $report */
        $report = json_decode($contents, true, 512, JSON_THROW_ON_ERROR);

        return $report;
    }

    /** @param array<string, mixed> $report @return array<string, mixed> */
    private function normalizeReport(array $report): array
    {
        $report['request_summary']['output_path'] = '<REPORT_PATH>';
        foreach ($report['resolution']['scenarios'] as &$scenario) {
            $scenario['duration_ms'] = 0;
        }
        unset($scenario);

        return $report;
    }

    private function composerIsAvailable(): bool
    {
        $process = new Process(['composer', '--version']);
        $process->run();

        return $process->isSuccessful();
    }

    private function repositoryRoot(): string
    {
        return dirname(__DIR__, 4);
    }

}
