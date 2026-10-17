<?php

declare(strict_types=1);

namespace PhpUpgradePreflight\Core\Tests\Unit\Analysis;

use PhpUpgradePreflight\Core\Analysis\DefaultUpgradeAnalyzer;
use PhpUpgradePreflight\Core\Composer\ComposerScenarioRunner;
use PhpUpgradePreflight\Core\Model\ScenarioResult;
use PhpUpgradePreflight\Core\Model\UpgradeRequest;
use PhpUpgradePreflight\Core\Model\UpgradeTarget;
use PhpUpgradePreflight\Core\Progress\AnalysisProgressEvent;
use PhpUpgradePreflight\Core\Progress\AnalysisProgressReporter;
use PhpUpgradePreflight\Tests\Support\FixtureSnapshot;
use PHPUnit\Framework\TestCase;
use Symfony\Component\Filesystem\Filesystem;

final class AdaptiveComposerAnalysisTest extends TestCase
{
    /** @dataProvider executionCases */
    public function testStandardExecutesOnlyObservedAuthoritativeAttempts(
        int $exactExit,
        int $relaxedExit,
        string $expectedStatus,
        int $expectedUpdates
    ): void {
        $path = sys_get_temp_dir() . '/adaptive-composer-' . bin2hex(random_bytes(8));
        $filesystem = new Filesystem();
        $filesystem->mirror(dirname(__DIR__, 5) . '/tests/fixtures/project-isolation', $path);
        $snapshot = FixtureSnapshot::capture($path);
        $commands = [];
        $runner = new ComposerScenarioRunner(processRunner: static function (array $command, string $directory) use (&$commands, $exactExit, $relaxedExit): array {
            $commands[] = $command;
            if ($command[1] === 'validate') {
                return ['exit_code' => 1, 'stdout' => '', 'stderr' => 'The lock file is not up to date.'];
            }
            if ($command[1] === 'prohibits') {
                return ['exit_code' => 1, 'stdout' => 'fixture/dependency 1.0.0 requires php (^8.0)', 'stderr' => ''];
            }
            $exit = in_array('--with-all-dependencies', $command, true) ? $relaxedExit : $exactExit;
            if ($exit === 0) {
                file_put_contents($directory . '/composer.lock', '{"packages":[{"name":"fixture/dependency","version":"2.0.0"}],"packages-dev":[]}');
            }
            return [
                'exit_code' => $exit,
                'stdout' => '',
                'stderr' => $exit === 2
                    ? 'Your requirements could not be resolved to an installable set of packages. Root composer.json requires fixture/dependency ^2.0.'
                    : ($exit === 0 ? '' : 'Connection failed.'),
            ];
        });
        $events = [];
        $reporter = new class ($events) implements AnalysisProgressReporter {
            /** @var list<AnalysisProgressEvent> */
            public array $events;

            /** @param list<AnalysisProgressEvent> $events */
            public function __construct(array &$events)
            {
                $this->events = &$events;
            }

            public function report(AnalysisProgressEvent $event): void
            {
                $this->events[] = $event;
            }
        };

        try {
            $report = (new DefaultUpgradeAnalyzer(scenarioRunner: $runner, progressReporter: $reporter))->analyzeUpgrade(
                new UpgradeRequest($path, [new UpgradeTarget('fixture/dependency', '^2.0')], '8.0', '8.1')
            );
            self::assertSame($expectedStatus, $report->resolutionStatus());
            self::assertTrue($report->scenarios()[0]->isValidationFailure());
            self::assertCount($expectedUpdates, array_filter($commands, static fn (array $command): bool => $command[1] === 'update'));
            self::assertSame('exact-target', $report->scenarios()[1]->scenario()->name());
            if ($expectedUpdates === 2) {
                self::assertSame('target-with-all-dependencies', $report->scenarios()[2]->scenario()->name());
            }
            if ($expectedStatus === 'feasible_with_changes') {
                self::assertSame([], $report->blockers());
                self::assertSame('2.0.0', $report->lockDiff()->packageChanges()[0]->toVersion());
            }
            foreach ($commands as $command) {
                self::assertNotContains('--minimal-changes', $command);
                self::assertContains('--no-scripts', $command);
                self::assertContains('--no-plugins', $command);
            }
            self::assertStringContainsString('No outcome is inferred for skipped alternatives', implode(' ', $report->uncertainties()));
            self::assertStringContainsString('target-platform-only', implode(' ', $report->uncertainties()));
            self::assertStringContainsString('staged-targets', implode(' ', $report->uncertainties()));
            foreach ($events as $event) {
                if (in_array($event->type(), ['phase-completed', 'scenario-completed', 'analysis-completed'], true)) {
                    self::assertNotNull($event->durationMs());
                    self::assertGreaterThanOrEqual(0, $event->durationMs());
                }
            }
            $previews = array_values(array_filter($events, static fn (AnalysisProgressEvent $event): bool => $event->type() === AnalysisProgressEvent::DIRECT_FINDING));
            if ($expectedStatus === 'feasible_with_changes' || ($exactExit === 1 && $relaxedExit === 1)) {
                self::assertSame([], $previews, 'A successful alternative or operational failure must not produce an early cause.');
            }
            if ($previews !== []) {
                self::assertCount(1, $previews);
                $types = array_map(static fn (AnalysisProgressEvent $event): string => $event->type(), $events);
                $previewIndex = array_search(AnalysisProgressEvent::DIRECT_FINDING, $types, true);
                self::assertIsInt($previewIndex);
                self::assertSame(AnalysisProgressEvent::PHASE_STARTED, $events[$previewIndex + 1]->type());
                self::assertSame('staged-resolution', $events[$previewIndex + 1]->phase());
                self::assertSame(AnalysisProgressEvent::ANALYSIS_COMPLETED, $events[count($events) - 1]->type());
            }
            self::assertArrayNotHasKey('analysis_mode', $report->request()->toArray());
            self::assertSame('0.8', $report->metadata()->schemaVersion());
            $snapshot->assertUnchanged($this);
        } finally {
            $filesystem->remove($path);
        }
    }

    /** @return array<string, array{int, int, string, int}> */
    public function executionCases(): array
    {
        return [
            'success skips redundant alternatives' => [0, 0, 'feasible_with_changes', 1],
            'exact failure still tries W' => [2, 0, 'feasible_with_changes', 2],
            'both solver failures remain blocked' => [2, 2, 'blocked', 2],
            'relaxed operational failure remains unknown' => [2, 1, 'unknown', 2],
            'operational failures remain unknown' => [1, 1, 'unknown', 2],
        ];
    }

    public function testStandardPhpOnlySkipsTheIneffectiveWAndMinimalComparison(): void
    {
        $path = sys_get_temp_dir() . '/adaptive-php-only-' . bin2hex(random_bytes(8));
        $filesystem = new Filesystem();
        $filesystem->mirror(dirname(__DIR__, 5) . '/tests/fixtures/project-isolation', $path);
        $snapshot = FixtureSnapshot::capture($path);
        $runner = new ComposerScenarioRunner(processRunner: static fn (): array => ['exit_code' => 1, 'stdout' => '', 'stderr' => 'Network unavailable.']);
        try {
            $analyzer = new DefaultUpgradeAnalyzer(scenarioRunner: $runner);
            $report = $analyzer->analyzeUpgrade(new UpgradeRequest($path, [], targetPhp: '8.3'));
            self::assertSame('unknown', $report->resolutionStatus());
            self::assertSame(['baseline-validation', 'exact-target'], array_map(
                static fn (ScenarioResult $result): string => $result->scenario()->name(),
                $report->scenarios()
            ));
            self::assertSame(2, $runner->measurements()['process_counts']['scenario']);
            $analyzer->analyzeUpgrade(new UpgradeRequest($path, [], targetPhp: '8.3'));
            self::assertSame(2, $runner->measurements()['process_counts']['scenario'], 'Counts reset for each analysis; no cross-run solver outcome is reused.');
            $snapshot->assertUnchanged($this);
        } finally {
            $filesystem->remove($path);
        }
    }

    public function testUnknownCurrentPhpIsNotInventedForAnOrderingProbe(): void
    {
        $path = sys_get_temp_dir() . '/adaptive-unknown-current-' . bin2hex(random_bytes(8));
        $filesystem = new Filesystem();
        $filesystem->mirror(dirname(__DIR__, 5) . '/tests/fixtures/project-isolation', $path);
        $snapshot = FixtureSnapshot::capture($path);
        $runner = new ComposerScenarioRunner(processRunner: static fn (): array => ['exit_code' => 1, 'stdout' => '', 'stderr' => 'Network unavailable.']);
        try {
            $report = (new DefaultUpgradeAnalyzer(scenarioRunner: $runner))->analyzeUpgrade(new UpgradeRequest(
                $path,
                [new UpgradeTarget('fixture/dependency', '^2.0')],
                targetPhp: '8.3'
            ));
            self::assertSame('unknown', $report->resolutionStatus());
            self::assertCount(3, $report->scenarios());
            self::assertStringContainsString('current project PHP version is unknown', implode(' ', $report->uncertainties()));
            foreach ($report->scenarios() as $result) {
                self::assertNotSame('staged-targets', $result->scenario()->name());
            }
            $snapshot->assertUnchanged($this);
        } finally {
            $filesystem->remove($path);
        }
    }
}
