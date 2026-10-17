<?php

declare(strict_types=1);

namespace PhpUpgradePreflight\Core\Tests\Integration;

use PhpUpgradePreflight\Core\Analysis\DefaultUpgradeAnalyzer;
use PhpUpgradePreflight\Core\Composer\ComposerScenarioRunner;
use PhpUpgradePreflight\Core\Model\ComposerExecutionConfiguration;
use PhpUpgradePreflight\Core\Model\ScenarioResult;
use PhpUpgradePreflight\Core\Model\UpgradeRequest;
use PhpUpgradePreflight\Core\Model\UpgradeTarget;
use PhpUpgradePreflight\Tests\Support\FixtureSnapshot;
use PHPUnit\Framework\TestCase;
use Symfony\Component\Filesystem\Filesystem;
use Symfony\Component\Process\Process;

final class AdaptiveComposerExecutionTest extends TestCase
{
    /** @dataProvider targetCases */
    public function testRealOfflineComposerUsesFewerProcessesWithoutInventingOutcomes(
        string $constraint,
        string $status,
        int $standardScenarios,
        int $fullScenarios
    ): void {
        $filesystem = new Filesystem();
        $root = sys_get_temp_dir() . '/adaptive-offline-' . bin2hex(random_bytes(8));
        $filesystem->mirror(dirname(__DIR__, 4) . '/tests/fixtures/path-repository', $root);
        $snapshot = FixtureSnapshot::capture($root);
        $counts = [];
        $reports = [];
        try {
            foreach (['standard', 'full'] as $mode) {
                $runner = new ComposerScenarioRunner();
                $analyzer = new DefaultUpgradeAnalyzer(scenarioRunner: $runner);
                $request = new UpgradeRequest(
                    $root . '/project',
                    [new UpgradeTarget('fixture/dependency', $constraint)],
                    '8.0',
                    '8.3',
                    composerExecution: ComposerExecutionConfiguration::restricted(),
                    analysisMode: $mode
                );
                $report = $analyzer->analyzeUpgrade($request);
                self::assertSame($status, $report->resolutionStatus(), json_encode($report->toArray(), JSON_THROW_ON_ERROR));
                self::assertCount($mode === 'standard' ? $standardScenarios : $fullScenarios, $report->scenarios());
                $counts[$mode] = $runner->measurements()['process_counts'];
                self::assertSame(1, $counts[$mode]['metadata']);
                $reports[$mode] = $report;
                $firstCounts = $counts[$mode];
                $analyzer->analyzeUpgrade($request);
                self::assertSame($firstCounts, $runner->measurements()['process_counts'], 'Repeat runs cannot reuse solver outcomes across analyses.');
                foreach ($report->scenarios() as $result) {
                    self::assertNull($result->tempPath());
                }
            }
            self::assertLessThan(array_sum($counts['full']), array_sum($counts['standard']));
            self::assertSame($reports['full']->lockDiff()->toArray(), $reports['standard']->lockDiff()->toArray());
            if ($status === 'blocked') {
                self::assertCount(1, $reports['standard']->blockers());
                self::assertSame($reports['full']->blockers()[0]->type(), $reports['standard']->blockers()[0]->type());
                self::assertSame($reports['full']->blockers()[0]->subject(), $reports['standard']->blockers()[0]->subject());
                self::assertTrue($reports['standard']->scenarios()[2]->isSolverFailure());
            }
            self::assertStringContainsString('No outcome is inferred', implode(' ', $reports['standard']->uncertainties()));
            self::assertStringNotContainsString('Standard analysis skipped', implode(' ', $reports['full']->uncertainties()));
            $snapshot->assertUnchanged($this);
        } finally {
            $filesystem->remove($root);
        }
    }

    /** @return list<array{string, string, int, int}> */
    public function targetCases(): array
    {
        return [
            ['^2.0', 'feasible_with_changes', 2, 6],
            ['^3.0', 'blocked', 3, 6],
        ];
    }

    public function testExactTargetLockConflictDoesNotStopTheRealWRepair(): void
    {
        $path = sys_get_temp_dir() . '/adaptive-locked-root-' . bin2hex(random_bytes(8));
        mkdir($path, 0700, true);
        $packages = [];
        foreach (['1.0.0', '2.0.0'] as $version) {
            $packages[] = ['name' => 'fixture/dependency', 'version' => $version, 'require' => ['fixture/transitive' => $version]];
            $packages[] = ['name' => 'fixture/transitive', 'version' => $version];
        }
        file_put_contents($path . '/composer.json', json_encode([
            'name' => 'fixture/locked-root',
            'description' => 'Deterministic offline partial-update escape fixture.',
            'license' => 'MIT',
            'require' => ['fixture/dependency' => '^1.0', 'fixture/transitive' => '*'],
            'repositories' => [['type' => 'package', 'package' => $packages], ['packagist.org' => false]],
        ], JSON_PRETTY_PRINT | JSON_THROW_ON_ERROR));
        try {
            (new Process([
                'composer', 'update', '--no-install', '--no-audit', '--no-scripts', '--no-plugins', '--no-interaction',
            ], $path, ['COMPOSER' => false, 'COMPOSER_DISABLE_NETWORK' => '1'], null, 60))->mustRun();
            $snapshot = FixtureSnapshot::capture($path);
            $runner = new ComposerScenarioRunner();
            $report = (new DefaultUpgradeAnalyzer(scenarioRunner: $runner))->analyzeUpgrade(new UpgradeRequest(
                $path,
                [new UpgradeTarget('fixture/dependency', '^2.0')],
                composerExecution: ComposerExecutionConfiguration::restricted()
            ));
            self::assertSame('feasible_with_changes', $report->resolutionStatus());
            self::assertSame([], $report->blockers());
            self::assertTrue($report->scenarios()[1]->isSolverFailure());
            self::assertTrue($report->scenarios()[2]->succeeded(), $report->scenarios()[2]->stderr());
            self::assertContains('--with-all-dependencies', $report->scenarios()[2]->command());
            self::assertCount(2, $report->lockDiff()->packageChanges());
            self::assertSame(3, $runner->measurements()['process_counts']['scenario']);
            self::assertSame(['baseline-validation', 'exact-target', 'target-with-all-dependencies'], array_map(
                static fn (ScenarioResult $result): string => $result->scenario()->name(),
                $report->scenarios()
            ));
            $snapshot->assertUnchanged($this);
        } finally {
            (new Filesystem())->remove($path);
        }
    }
}
