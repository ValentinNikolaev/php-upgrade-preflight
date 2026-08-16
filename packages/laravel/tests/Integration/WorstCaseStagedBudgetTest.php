<?php

declare(strict_types=1);

namespace PhpUpgradePreflight\Laravel\Tests\Integration;

use PhpUpgradePreflight\Core\Analysis\DefaultUpgradeAnalyzer;
use PhpUpgradePreflight\Core\Analysis\StagedAnalysisPolicy;
use PhpUpgradePreflight\Core\Composer\ComposerScenarioRunner;
use PhpUpgradePreflight\Core\Model\ReportFormat;
use PhpUpgradePreflight\Core\Model\UpgradeRequest;
use PhpUpgradePreflight\Core\Model\UpgradeTarget;
use PhpUpgradePreflight\Core\Reporting\JsonReportWriter;
use PhpUpgradePreflight\Core\Reporting\MarkdownReportWriter;
use PhpUpgradePreflight\Laravel\LaravelFrameworkIntegration;
use PHPUnit\Framework\TestCase;

/**
 * @group staged-budget
 */
final class WorstCaseStagedBudgetTest extends TestCase
{
    /**
     * @runInSeparateProcess
     * @preserveGlobalState disabled
     */
    public function testWorstSupportedLaravelChainStaysWithinCrossHostBudgets(): void
    {
        $contract = $this->contract();
        self::assertSame(['linux', 'windows'], $contract['platforms']);
        self::assertTrue($contract['target_immutability_required']);
        $fixture = $this->fixturePath();
        $before = $this->fixtureDigests($fixture);
        $startedAt = hrtime(true);
        $normalizedJson = null;
        $normalizedMarkdown = null;
        $processCounts = [];

        for ($rerun = 0; $rerun < $contract['deterministic_reruns']; ++$rerun) {
            $processCount = 0;
            $report = (new DefaultUpgradeAnalyzer(
                [new LaravelFrameworkIntegration()],
                null,
                $this->runner($fixture, $processCount)
            ))->analyzeUpgrade(new UpgradeRequest(
                $fixture,
                [new UpgradeTarget('laravel/framework', '^13.0')],
                '7.4.33',
                '8.3.0',
                [],
                [],
                ReportFormat::JSON
            ));
            $canonical = $report->toArray();
            $staged = $canonical['staged_resolution'];
            self::assertIsArray($staged);
            self::assertSame('evaluated', $staged['execution_state']);
            self::assertSame('feasible_with_changes', $staged['status']);
            $stages = $staged['stages'] ?? null;
            self::assertIsArray($stages);
            self::assertCount($contract['expected_stages'], $stages);
            self::assertSame(
                $contract['expected_staged_scenarios'],
                array_sum(array_map(
                    static fn (array $stage): int => count($stage['attempts']),
                    $stages
                ))
            );
            $aggregateDurationMs = 0;
            foreach ($stages as $stage) {
                self::assertIsArray($stage);
                $aggregateDurationMs += $stage['duration_ms'];
                self::assertLessThanOrEqual(
                    StagedAnalysisPolicy::STAGE_TIMEOUT_SECONDS * 1000,
                    $stage['duration_ms']
                );
                foreach ($stage['attempts'] as $attempt) {
                    self::assertIsArray($attempt);
                    self::assertLessThanOrEqual(
                        StagedAnalysisPolicy::SCENARIO_TIMEOUT_SECONDS * 1000,
                        $attempt['scenario']['duration_ms']
                    );
                }
            }
            self::assertLessThanOrEqual(
                StagedAnalysisPolicy::AGGREGATE_TIMEOUT_SECONDS * 1000,
                $aggregateDurationMs
            );

            $json = (new JsonReportWriter())->render($report);
            $markdown = (new MarkdownReportWriter())->render($report);
            self::assertLessThanOrEqual($contract['json_report_bytes'], strlen($json));
            self::assertLessThanOrEqual($contract['markdown_report_bytes'], strlen($markdown));
            $this->assertRedacted($json . "\n" . $markdown, $contract['allowed_seeded_canary_occurrences']);

            $currentJson = $this->normalizeJson($json);
            $currentMarkdown = $this->normalizeMarkdown($markdown);
            $normalizedJson = $normalizedJson ?? $currentJson;
            $normalizedMarkdown = $normalizedMarkdown ?? $currentMarkdown;
            self::assertSame($normalizedJson, $currentJson);
            self::assertSame($normalizedMarkdown, $currentMarkdown);
            self::assertGreaterThan($contract['expected_staged_scenarios'], $processCount);
            self::assertLessThanOrEqual($contract['max_observed_composer_processes'], $processCount);
            $processCounts[] = $processCount;
        }

        self::assertCount(1, array_unique($processCounts));
        self::assertSame($before, $this->fixtureDigests($fixture));
        self::assertLessThanOrEqual(
            $contract['runtime_seconds'],
            (hrtime(true) - $startedAt) / 1_000_000_000
        );
        self::assertLessThanOrEqual($contract['peak_memory_bytes'], memory_get_peak_usage(true));
    }

    private function runner(string $fixture, int &$processCount): ComposerScenarioRunner
    {
        $milliseconds = 0;
        $secret = $this->canaries()['github_token'];
        self::assertIsString($secret);

        return new ComposerScenarioRunner(
            null,
            null,
            static function (array $command, string $workingDirectory) use (
                $fixture,
                &$processCount,
                $secret
            ): array {
                ++$processCount;
                if (in_array('prohibits', $command, true)) {
                    return ['exit_code' => 1, 'stdout' => '', 'stderr' => 'No additional budget diagnostic.'];
                }

                $manifest = self::readJson($workingDirectory . DIRECTORY_SEPARATOR . 'composer.json');
                $lock = self::readJson($workingDirectory . DIRECTORY_SEPARATOR . 'composer.lock');
                $requirements = $manifest['require'] ?? null;
                if (!is_array($requirements)) {
                    throw new \RuntimeException('Budget fixture has no root requirements.');
                }
                $targetMajor = self::frameworkMajor($requirements['laravel/framework'] ?? null);
                $lockedMajor = self::frameworkMajor(self::lockedFrameworkVersion($lock));
                $adjacentStage = $targetMajor === $lockedMajor + 1;
                if (!$adjacentStage || !in_array('--with-all-dependencies', $command, true)) {
                    return [
                        'exit_code' => 2,
                        'stdout' => '',
                        'stderr' => implode("\n", [
                            'Your requirements could not be resolved to an installable set of packages.',
                            sprintf('- fixture/budget-blocker 1.0.0 requires laravel/framework ^%d.0.', $lockedMajor),
                            'Authorization: Bearer ' . $secret,
                            'Project path: ' . $fixture,
                            'Workspace path: ' . $workingDirectory,
                        ]),
                    ];
                }

                $packages = $lock['packages'] ?? null;
                if (!is_array($packages)) {
                    throw new \RuntimeException('Budget fixture lock has no package list.');
                }
                $versions = [
                    'doctrine/dbal' => ['constraint' => '^3.0', 'version' => '3.0.0'],
                    'laravel/boost' => ['constraint' => '^2.0', 'version' => '2.0.0'],
                    'laravel/breeze' => ['constraint' => '^2.0', 'version' => '2.0.0'],
                    'laravel/passport' => ['constraint' => '^10.0', 'version' => '10.0.0'],
                    'nesbot/carbon' => ['constraint' => '^3.0', 'version' => '3.0.0'],
                    'pusher/pusher-php-server' => ['constraint' => '^5.0', 'version' => '5.0.0'],
                ];
                foreach ($packages as &$package) {
                    if (!is_array($package) || !is_string($package['name'] ?? null)) {
                        continue;
                    }
                    if ($package['name'] === 'laravel/framework') {
                        $package['version'] = sprintf('v%d.0.0', $targetMajor);
                    } elseif (isset($versions[$package['name']])
                        && ($requirements[$package['name']] ?? null) === $versions[$package['name']]['constraint']) {
                        $package['version'] = $versions[$package['name']]['version'];
                    }
                }
                unset($package);
                $lock['packages'] = $packages;
                $lock['content-hash'] = sprintf('budget-stage-%d', $targetMajor);
                $encoded = json_encode(
                    $lock,
                    JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES | JSON_THROW_ON_ERROR
                ) . "\n";
                if (file_put_contents($workingDirectory . DIRECTORY_SEPARATOR . 'composer.lock', $encoded) === false) {
                    throw new \RuntimeException('Unable to write the budget candidate lock.');
                }

                return ['exit_code' => 0, 'stdout' => 'Budget fixture target resolved.', 'stderr' => ''];
            },
            static fn (): string => '2.8.12',
            static function () use (&$milliseconds): float {
                return $milliseconds++ / 1000;
            }
        );
    }

    /**
     * @return array{
     *     fixture: string,
     *     platforms: list<string>,
     *     deterministic_reruns: int,
     *     expected_stages: int,
     *     expected_staged_scenarios: int,
     *     max_observed_composer_processes: int,
     *     runtime_seconds: int,
     *     peak_memory_bytes: int,
     *     json_report_bytes: int,
     *     markdown_report_bytes: int,
     *     allowed_seeded_canary_occurrences: int,
     *     target_immutability_required: bool
     * }
     */
    private function contract(): array
    {
        $contract = self::readJson($this->root() . '/tests/fixtures/contracts/v0.3.json');
        $budget = $contract['ci_worst_chain'] ?? null;
        self::assertIsArray($budget);

        /**
         * @var array{
         *     fixture: string,
         *     platforms: list<string>,
         *     deterministic_reruns: int,
         *     expected_stages: int,
         *     expected_staged_scenarios: int,
         *     max_observed_composer_processes: int,
         *     runtime_seconds: int,
         *     peak_memory_bytes: int,
         *     json_report_bytes: int,
         *     markdown_report_bytes: int,
         *     allowed_seeded_canary_occurrences: int,
         *     target_immutability_required: bool
         * } $budget
         */
        return $budget;
    }

    /** @return array<string, string> */
    private function canaries(): array
    {
        $fixture = self::readJson($this->root() . '/tests/fixtures/security/composer-output-with-secrets.json');
        $canaries = $fixture['canaries'] ?? null;
        self::assertIsArray($canaries);
        foreach ($canaries as $label => $canary) {
            self::assertIsString($label);
            self::assertIsString($canary);
        }

        /** @var array<string, string> $canaries */
        return $canaries;
    }

    private function assertRedacted(string $surface, int $allowedOccurrences): void
    {
        foreach ($this->canaries() as $label => $canary) {
            self::assertLessThanOrEqual(
                $allowedOccurrences,
                substr_count($surface, $canary),
                sprintf('Sensitive canary %s reached a worst-chain report.', $label)
            );
        }
        foreach ([$this->fixturePath(), str_replace('\\', '/', $this->fixturePath())] as $privateRoot) {
            self::assertStringNotContainsString($privateRoot, $surface);
        }
    }

    private function normalizeJson(string $json): string
    {
        $canonical = json_decode($json, true, 512, JSON_THROW_ON_ERROR);
        self::assertIsArray($canonical);
        /** @var array<mixed> $canonical */
        $this->normalizeDurations($canonical);

        return json_encode($canonical, JSON_UNESCAPED_SLASHES | JSON_THROW_ON_ERROR);
    }

    private function normalizeMarkdown(string $markdown): string
    {
        $normalized = preg_replace('/duration `\d+ ms`/', 'duration `0 ms`', $markdown);
        self::assertIsString($normalized);

        return $normalized;
    }

    /** @param array<mixed> $value */
    private function normalizeDurations(array &$value): void
    {
        foreach ($value as $key => &$child) {
            if ($key === 'duration_ms') {
                $child = 0;
            } elseif (is_array($child)) {
                $this->normalizeDurations($child);
            }
        }
        unset($child);
    }

    /** @return array<string, string> */
    private function fixtureDigests(string $directory): array
    {
        $digests = [];
        $iterator = new \RecursiveIteratorIterator(
            new \RecursiveDirectoryIterator($directory, \FilesystemIterator::SKIP_DOTS)
        );
        foreach ($iterator as $file) {
            if (!$file instanceof \SplFileInfo || !$file->isFile()) {
                continue;
            }
            $relative = substr($file->getPathname(), strlen($directory) + 1);
            $digest = hash_file('sha256', $file->getPathname());
            if ($digest === false) {
                throw new \RuntimeException('Unable to hash a budget fixture file.');
            }
            $digests[str_replace('\\', '/', $relative)] = $digest;
        }
        ksort($digests, SORT_STRING);

        return $digests;
    }

    /** @return array<string, mixed> */
    private static function readJson(string $path): array
    {
        $contents = file_get_contents($path);
        if ($contents === false) {
            throw new \RuntimeException(sprintf('Unable to read budget fixture %s.', basename($path)));
        }
        $decoded = json_decode($contents, true, 512, JSON_THROW_ON_ERROR);
        if (!is_array($decoded)) {
            throw new \RuntimeException('Budget fixture JSON must decode to an object.');
        }

        return $decoded;
    }

    /** @param mixed $constraint */
    private static function frameworkMajor($constraint): int
    {
        if (!is_string($constraint) || preg_match('/(\d+)/', $constraint, $matches) !== 1) {
            throw new \RuntimeException('Budget fixture has no parseable Laravel major.');
        }

        return (int) $matches[1];
    }

    /** @param array<string, mixed> $lock */
    private static function lockedFrameworkVersion(array $lock): string
    {
        $packages = $lock['packages'] ?? null;
        if (!is_array($packages)) {
            throw new \RuntimeException('Budget fixture lock has no package list.');
        }
        foreach ($packages as $package) {
            if (is_array($package)
                && ($package['name'] ?? null) === 'laravel/framework'
                && is_string($package['version'] ?? null)) {
                return $package['version'];
            }
        }

        throw new \RuntimeException('Budget fixture lock has no Laravel package.');
    }

    private function fixturePath(): string
    {
        return $this->root() . '/tests/fixtures/projects/' . $this->contract()['fixture'];
    }

    private function root(): string
    {
        return dirname(__DIR__, 4);
    }
}
