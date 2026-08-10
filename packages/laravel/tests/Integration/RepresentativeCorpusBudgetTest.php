<?php

declare(strict_types=1);

namespace PhpUpgradePreflight\Laravel\Tests\Integration;

use PhpUpgradePreflight\Core\Analysis\DefaultUpgradeAnalyzer;
use PhpUpgradePreflight\Core\Composer\ComposerScenarioRunner;
use PhpUpgradePreflight\Core\Model\ReportFormat;
use PhpUpgradePreflight\Core\Model\UpgradeRequest;
use PhpUpgradePreflight\Core\Model\UpgradeTarget;
use PhpUpgradePreflight\Core\Reporting\JsonReportWriter;
use PhpUpgradePreflight\Core\Reporting\MarkdownReportWriter;
use PhpUpgradePreflight\Laravel\LaravelFrameworkIntegration;
use PHPUnit\Framework\TestCase;

final class RepresentativeCorpusBudgetTest extends TestCase
{
    /**
     * @var array<string, array{laravel: string, php: string}>
     */
    private const TARGETS = [
        'blocked-illuminate-constraint' => ['laravel' => '^9.0', 'php' => '8.1'],
        'ignition-legacy-skeleton' => ['laravel' => '^8.0', 'php' => '8.0'],
        'laravel-7-to-8' => ['laravel' => '^8.0', 'php' => '8.0'],
        'laravel-7-to-9' => ['laravel' => '^9.0', 'php' => '8.1'],
        'laravel-package-matrix' => ['laravel' => '^9.0', 'php' => '8.1'],
        'php-extension-conflict' => ['laravel' => '^9.0', 'php' => '8.1'],
    ];

    /**
     * @runInSeparateProcess
     * @preserveGlobalState disabled
     */
    public function testRepresentativeCorpusStaysWithinContractBudgets(): void
    {
        $contract = $this->contract();
        $budgets = $contract['budgets'];
        self::assertIsArray($budgets);
        self::assertSame(array_keys(self::TARGETS), $budgets['corpus']);

        $analyzer = new DefaultUpgradeAnalyzer(
            [new LaravelFrameworkIntegration()],
            null,
            $this->fixtureRunner()
        );
        $startedAt = hrtime(true);
        $combinedBytes = 0;

        foreach (self::TARGETS as $fixture => $target) {
            $projectPath = $this->root() . '/tests/fixtures/projects/' . $fixture;
            foreach ([ReportFormat::JSON, ReportFormat::MARKDOWN] as $format) {
                $report = $analyzer->analyzeUpgrade(new UpgradeRequest(
                    $projectPath,
                    [new UpgradeTarget('laravel/framework', $target['laravel'])],
                    '7.4',
                    $target['php'],
                    [],
                    [],
                    $format
                ));
                $rendered = $format === ReportFormat::JSON
                    ? (new JsonReportWriter())->render($report)
                    : (new MarkdownReportWriter())->render($report);
                $bytes = strlen($rendered);
                $combinedBytes += $bytes;
                $limit = $format === ReportFormat::JSON
                    ? $budgets['report_size']['json_per_fixture_bytes']
                    : $budgets['report_size']['markdown_per_fixture_bytes'];

                self::assertLessThanOrEqual(
                    $limit,
                    $bytes,
                    sprintf('%s.%s exceeds its report-size budget.', $fixture, $format)
                );
            }
        }

        $elapsedSeconds = (hrtime(true) - $startedAt) / 1_000_000_000;
        self::assertLessThanOrEqual($budgets['report_size']['combined_corpus_bytes'], $combinedBytes);
        self::assertLessThanOrEqual($budgets['runtime']['corpus_seconds'], $elapsedSeconds);
        self::assertLessThanOrEqual($budgets['memory']['peak_bytes'], memory_get_peak_usage(true));
    }

    /** @return array<string, mixed> */
    private function contract(): array
    {
        $contents = file_get_contents($this->root() . '/tests/fixtures/contracts/v0.2.json');
        self::assertIsString($contents);
        $contract = json_decode($contents, true, 512, JSON_THROW_ON_ERROR);
        self::assertIsArray($contract);

        return $contract;
    }

    private function fixtureRunner(): ComposerScenarioRunner
    {
        return new ComposerScenarioRunner(null, null, static function (array $command, string $workingDirectory): array {
            $contents = file_get_contents($workingDirectory . DIRECTORY_SEPARATOR . 'composer.json');
            if ($contents === false) {
                throw new \RuntimeException('Unable to read representative fixture manifest.');
            }
            $manifest = json_decode($contents, true, 512, JSON_THROW_ON_ERROR);
            $fixture = $manifest['extra']['php-upgrade-preflight-fixture'] ?? null;

            if (in_array('validate', $command, true)) {
                return ['exit_code' => 0, 'stdout' => 'Fixture baseline is valid.', 'stderr' => ''];
            }
            if (in_array('prohibits', $command, true)) {
                return ['exit_code' => 1, 'stdout' => '', 'stderr' => 'No additional fixture diagnostic.'];
            }
            if ($fixture === 'blocked-illuminate-constraint') {
                return [
                    'exit_code' => 2,
                    'stdout' => '',
                    'stderr' => '- fixture/illuminate-consumer 1.0.0 requires illuminate/support ^7.0 -> found illuminate/support[v7.30.7] but it conflicts with the requested Laravel target.',
                ];
            }
            if ($fixture === 'php-extension-conflict') {
                return [
                    'exit_code' => 2,
                    'stdout' => '',
                    'stderr' => implode("\n", [
                        '- fixture/runtime-guard 1.0.0 requires php ^7.4 -> your php version (8.1.0) does not satisfy that requirement.',
                        '- fixture/runtime-guard 1.0.0 requires ext-preflight_fixture * -> it is missing from your system.',
                    ]),
                ];
            }
            if (in_array($fixture, ['ignition-legacy-skeleton', 'laravel-package-matrix'], true)) {
                return [
                    'exit_code' => 2,
                    'stdout' => '',
                    'stderr' => '- Root composer.json retains legacy package constraints that exclude the requested Laravel target.',
                ];
            }
            if (in_array($fixture, ['laravel-7-to-8', 'laravel-7-to-9'], true)) {
                $constraint = $manifest['require']['laravel/framework'] ?? null;
                if (is_string($constraint)) {
                    self::writeCandidateLock(
                        $workingDirectory,
                        str_starts_with($constraint, '^8.') ? 'v8.83.27' : 'v9.52.16'
                    );
                }

                return ['exit_code' => 0, 'stdout' => 'Fixture target resolved.', 'stderr' => ''];
            }

            throw new \RuntimeException('Unexpected representative fixture process invocation.');
        }, null, static function (): float {
            static $milliseconds = 0;

            return $milliseconds++ / 1000;
        });
    }

    private static function writeCandidateLock(string $workingDirectory, string $targetVersion): void
    {
        $lockPath = $workingDirectory . DIRECTORY_SEPARATOR . 'composer.lock';
        $contents = file_get_contents($lockPath);
        if ($contents === false) {
            throw new \RuntimeException('Unable to read representative fixture lockfile.');
        }
        $lock = json_decode($contents, true, 512, JSON_THROW_ON_ERROR);
        foreach ($lock['packages'] as &$package) {
            if (is_array($package) && ($package['name'] ?? null) === 'laravel/framework') {
                $package['version'] = $targetVersion;
            }
        }
        unset($package);
        $lock['content-hash'] = 'candidate-' . $targetVersion;

        if (file_put_contents(
            $lockPath,
            json_encode($lock, JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES | JSON_THROW_ON_ERROR) . "\n"
        ) === false) {
            throw new \RuntimeException('Unable to write representative fixture candidate lockfile.');
        }
    }

    private function root(): string
    {
        return dirname(__DIR__, 4);
    }
}
