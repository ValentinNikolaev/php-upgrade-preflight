<?php

declare(strict_types=1);

namespace PhpUpgradePreflight\Tests\Release;

use PhpUpgradePreflight\Core\Model\ReportFormat;
use PhpUpgradePreflight\Core\Reporting\MarkdownReportWriter;
use PhpUpgradePreflight\Core\Reporting\ReportFileWriter;
use PHPUnit\Framework\TestCase;
use Symfony\Component\Filesystem\Filesystem;
use Symfony\Component\Process\Process;

final class WorkflowOptimizationToolTest extends TestCase
{
    public function testMaskSecretCanariesEmitsEveryMaskWithoutPublishingValuesInAssertions(): void
    {
        $root = dirname(__DIR__, 2);
        $fixtureContents = file_get_contents($root . '/tests/fixtures/security/composer-output-with-secrets.json');
        self::assertIsString($fixtureContents);
        $fixture = json_decode($fixtureContents, true, 512, JSON_THROW_ON_ERROR);
        self::assertIsArray($fixture['canaries'] ?? null);

        $process = new Process([PHP_BINARY, $root . '/tools/mask-secret-canaries.php'], $root);
        $process->run();

        self::assertTrue($process->isSuccessful(), $process->getErrorOutput());
        $expected = implode('', array_map(
            static fn (string $value): string => '::add-mask::' . $value . PHP_EOL,
            array_values($fixture['canaries'])
        ));
        self::assertSame(hash('sha256', $expected), hash('sha256', $process->getOutput()));
        self::assertSame(count($fixture['canaries']), substr_count($process->getOutput(), '::add-mask::'));
    }

    public function testMarkdownReportIsRenderedFromCanonicalJsonWithoutRunningAnalysisAgain(): void
    {
        $root = dirname(__DIR__, 2);
        $temporaryDirectory = rtrim(sys_get_temp_dir(), DIRECTORY_SEPARATOR)
            . DIRECTORY_SEPARATOR
            . 'php-upgrade-preflight-report-render-' . bin2hex(random_bytes(8));
        (new Filesystem())->mkdir($temporaryDirectory);

        try {
            $snapshot = file_get_contents($root . '/packages/core/tests/Snapshots/upgrade-report-v0.7.json');
            self::assertIsString($snapshot);
            $canonical = json_decode($snapshot, true, 512, JSON_THROW_ON_ERROR);
            self::assertIsArray($canonical);

            $projectPath = $root . '/tests/fixtures/laravel-app';
            $inputPath = $temporaryDirectory . '/input.json';
            $outputPath = $temporaryDirectory . '/report with spaces.md';
            $canonical['request_summary']['project_path'] = $projectPath;
            file_put_contents($inputPath, json_encode(
                $canonical,
                JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES | JSON_THROW_ON_ERROR
            ));

            $process = new Process([
                PHP_BINARY,
                $root . '/tools/render-markdown-report.php',
                $inputPath,
                $outputPath,
                $projectPath,
            ], $root);
            $process->run();

            self::assertTrue($process->isSuccessful(), $process->getErrorOutput());
            self::assertFileExists($outputPath);

            $resolvedOutputPath = (new ReportFileWriter())->validateDestination($projectPath, $outputPath);
            $canonical['request_summary']['format'] = ReportFormat::MARKDOWN;
            $canonical['request_summary']['output_path'] = $resolvedOutputPath;
            $expected = (new MarkdownReportWriter())->renderCanonical($canonical);
            $actual = file_get_contents($outputPath);
            self::assertIsString($actual);
            self::assertSame(hash('sha256', $expected), hash('sha256', $actual));
        } finally {
            (new Filesystem())->remove($temporaryDirectory);
        }
    }

    public function testPrivacyVerifierScansGeneratedReportsExceptionsDebugOutputAndCiLogs(): void
    {
        $root = dirname(__DIR__, 2);
        $process = new Process([PHP_BINARY, $root . '/tools/verify-report-privacy.php'], $root);
        $process->setTimeout(60);
        $process->run();

        self::assertTrue($process->isSuccessful(), $process->getErrorOutput());
        self::assertSame('Report privacy verification passed.' . PHP_EOL, $process->getOutput());

        $fixtureContents = file_get_contents($root . '/tests/fixtures/security/composer-output-with-secrets.json');
        self::assertIsString($fixtureContents);
        $fixture = json_decode($fixtureContents, true, 512, JSON_THROW_ON_ERROR);
        self::assertIsArray($fixture['canaries'] ?? null);
        $log = $process->getOutput() . $process->getErrorOutput();
        foreach ($fixture['canaries'] as $label => $canary) {
            if (str_contains($log, $canary)) {
                self::fail(sprintf('Sensitive canary %s reached the privacy verifier log.', $label));
            }
        }
    }
}
