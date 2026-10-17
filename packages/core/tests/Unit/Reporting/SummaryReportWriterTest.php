<?php

declare(strict_types=1);

namespace PhpUpgradePreflight\Core\Tests\Unit\Reporting;

use PhpUpgradePreflight\Core\Model\Blocker;
use PhpUpgradePreflight\Core\Model\ComposerJson;
use PhpUpgradePreflight\Core\Model\ComposerLock;
use PhpUpgradePreflight\Core\Model\EffortEstimate;
use PhpUpgradePreflight\Core\Model\Evidence;
use PhpUpgradePreflight\Core\Model\FrameworkStageTarget;
use PhpUpgradePreflight\Core\Model\LockDiff;
use PhpUpgradePreflight\Core\Model\ProjectState;
use PhpUpgradePreflight\Core\Model\ProjectStateFingerprint;
use PhpUpgradePreflight\Core\Model\RiskSummary;
use PhpUpgradePreflight\Core\Model\RootConstraintChange;
use PhpUpgradePreflight\Core\Model\Scenario;
use PhpUpgradePreflight\Core\Model\ScenarioResult;
use PhpUpgradePreflight\Core\Model\SourceUsage;
use PhpUpgradePreflight\Core\Model\SourceImpactFinding;
use PhpUpgradePreflight\Core\Model\StageAnalysis;
use PhpUpgradePreflight\Core\Model\StageAttempt;
use PhpUpgradePreflight\Core\Model\StageBlockerEntry;
use PhpUpgradePreflight\Core\Model\StagedResolution;
use PhpUpgradePreflight\Core\Model\TargetPlatform;
use PhpUpgradePreflight\Core\Model\UpgradeReport;
use PhpUpgradePreflight\Core\Model\UpgradeRequest;
use PhpUpgradePreflight\Core\Model\UpgradeTarget;
use PhpUpgradePreflight\Core\Reporting\JsonReportWriter;
use PhpUpgradePreflight\Core\Reporting\SummaryReportWriter;
use PhpUpgradePreflight\Core\Reporting\SummaryUncertainties;
use PHPUnit\Framework\TestCase;

final class SummaryReportWriterTest extends TestCase
{
    public function testBlockedOutputGroupsRealCausesWithVersionsProvenanceAndUnverifiedActions(): void
    {
        $cause = $this->blocker();
        $report = $this->report([$cause, $cause]);
        $summary = (new SummaryReportWriter())->render($report);

        self::assertStringContainsString('Direct target: blocked', $summary);
        self::assertStringContainsString('vendor/legacy 1.4.0', $summary);
        self::assertStringContainsString('project lock, root dependency', $summary);
        self::assertStringContainsString('2 observation(s)', $summary);
        self::assertStringContainsString('Evidence: cause-1', $summary);
        self::assertStringContainsString('Suggested next action (not dependency-checked): Review vendor/legacy requirements.', $summary);
        self::assertStringContainsString('direct blocking groups: 1 shown of 1', $summary);
        self::assertStringNotContainsString('hours', $summary);
    }

    public function testAdvisoryOnlySuccessfulResolutionDoesNotBecomeBlocked(): void
    {
        $advisory = $this->blocker('vendor/legacy', 'abandoned-package');
        $report = $this->report([$advisory], [$this->scenario(true)]);
        $summary = (new SummaryReportWriter())->render($report);

        self::assertStringContainsString('Direct target: feasible', $summary);
        self::assertStringContainsString('advisory (does not block resolution)', $summary);
        self::assertStringContainsString('direct blocking groups: 0 shown of 0', $summary);
        self::assertStringContainsString('Dependency resolution does not prove application compatibility.', $summary);
    }

    public function testUnknownAndBaselineFailureRemainVisibleWithoutTranscripts(): void
    {
        $targets = $this->request()->targets();
        $baseline = new ScenarioResult(new Scenario('baseline', $targets, false, false, true), 1, 'PRIVATE TRANSCRIPT', '', null, null, ScenarioResult::FAILURE_VALIDATION);
        $missing = new ScenarioResult(new Scenario('exact-target', $targets), 127, '', 'PRIVATE TRANSCRIPT', null, null, ScenarioResult::FAILURE_OPERATIONAL, null, [], 0, null, [], ScenarioResult::OUTCOME_COMPOSER_MISSING);
        $summary = (new SummaryReportWriter())->render($this->report([], [$baseline, $missing]));

        self::assertStringContainsString('Direct target: unknown', $summary);
        self::assertStringContainsString('Baseline validation failed:', $summary);
        self::assertStringContainsString('composer_missing', $summary);
        self::assertStringNotContainsString('PRIVATE TRANSCRIPT', $summary);
    }

    public function testLaterActiveStageSurvivesResolvedEntriesAndEarlyStageFlood(): void
    {
        $entries = [];
        $entries[] = StageBlockerEntry::detected('early', 1, 'exact', $this->blocker('vendor/resolved'))
            ->withLifecycle(StageBlockerEntry::RESOLVED, 2, 'repair', ['cause-1']);
        for ($index = 0; $index < 20; ++$index) {
            $entries[] = StageBlockerEntry::detected('early', 1, 'exact', $this->blocker('vendor/early-' . $index));
        }
        $entries[] = StageBlockerEntry::detected('late', 1, 'exact', $this->blocker('vendor/later-active'));
        $staged = new StagedResolution('evaluated', 'blocked', 'fixture', [], $entries, 'stage_blocked');
        $summary = (new SummaryReportWriter())->render($this->report([], [$this->scenario(true)], $staged));

        self::assertStringContainsString('Direct target: feasible', $summary);
        self::assertStringContainsString('Staged path: evaluated / blocked', $summary);
        self::assertStringContainsString('vendor/later-active', $summary);
        self::assertStringNotContainsString('vendor/resolved', $summary);
        self::assertStringContainsString('active staged blocking groups: 6 shown of 21; 15 additional', $summary);
        self::assertLessThanOrEqual(60, substr_count($summary, PHP_EOL));
    }

    public function testDirectBlockedAndStagedFeasibleAreSeparateOutcomes(): void
    {
        $staged = new StagedResolution('evaluated', 'feasible_with_changes', 'fixture', [], []);
        $summary = (new SummaryReportWriter())->render($this->report([$this->blocker()], [], $staged));

        self::assertStringContainsString('Direct target: blocked', $summary);
        self::assertStringContainsString('Staged path: evaluated / feasible_with_changes', $summary);
    }

    public function testOnlySelectedSuccessfulStageChangesAreCalledDependencyChecked(): void
    {
        $state = $this->state();
        $request = $this->request();
        $fingerprint = ProjectStateFingerprint::fromState($state, TargetPlatform::fromRequest($request, $state), '8.3.0', []);
        $change = new RootConstraintChange('vendor/legacy', 'update', '^1.0', '^2.0', 'Recorded repair.', ['cause-1']);
        $attempts = [
            new StageAttempt(1, 'suggested', [$change], $this->scenario(false), $fingerprint, null, [], ['cause-1'], false),
            new StageAttempt(2, 'repair', [$change], $this->scenario(true), $fingerprint, $fingerprint, [], ['cause-1'], true),
        ];
        $targets = $request->targets();
        $target = new FrameworkStageTarget('hop-1-2', 'fixture', 1, 2, $targets, '8.3.0', [], [], ['cause-1']);
        $stage = new StageAnalysis($target, 'evaluated', 'feasible_with_changes', $attempts, 2, null, $fingerprint, $fingerprint, []);
        $staged = new StagedResolution('evaluated', 'feasible_with_changes', 'fixture', [$stage], []);
        $summary = (new SummaryReportWriter())->render($this->report([], [$this->scenario(true)], $staged));

        self::assertStringContainsString('Dependency-checked stage candidate [hop-1-2]: vendor/legacy ^1.0 -> ^2.0', $summary);
        self::assertStringContainsString('contain 1 checked root changes', $summary);
        self::assertStringContainsString('application/source repairs remain unverified', $summary);
    }

    public function testHugeInventoryAndEvidenceContextsAreNotMaterializedBySummary(): void
    {
        $writer = new SummaryReportWriter();
        $small = $this->report([$this->blocker()]);
        $usage = new SourceUsage('app/Hidden.php', "UNSERIALIZED\xB1", 'import', ['cause-1']);
        $large = $this->report([$this->blocker()], [], null, array_fill(0, 50000, $usage));
        $before = memory_get_usage();
        $summary = $writer->render($large);

        self::assertSame($writer->render($small), $summary);
        self::assertLessThan(2 * 1024 * 1024, memory_get_usage() - $before);
        self::assertLessThanOrEqual(60, substr_count($summary, PHP_EOL));
        self::assertStringNotContainsString('Hidden.php', $summary);
        self::assertStringNotContainsString('UNSERIALIZED', $summary);
        $this->expectException(\JsonException::class);
        (new JsonReportWriter())->render($large);
    }

    public function testCriticalSourceOmissionsPrecedeAmbientUncertaintyAndAllOthersAreDiscoverable(): void
    {
        $uncertainties = array_map(static fn (int $index): string => 'Ambient uncertainty ' . $index, range(1, 10));
        $uncertainties[] = 'Source scan omitted oversized files (cause-1).';
        $summary = (new SummaryReportWriter())->render($this->report([$this->blocker()], [], null, [], $uncertainties));

        self::assertStringContainsString('Source scan omitted oversized files (cause-1)', $summary);
        self::assertStringContainsString('Additional uncertainties in full details:', $summary);
        self::assertStringContainsString('zero findings does not establish compatibility', $summary);
    }

    public function testSkippedAlternativeFloodCannotHideCriticalUncertaintyCategories(): void
    {
        $uncertainties = [];
        for ($index = 0; $index < 30; ++$index) {
            $uncertainties[] = 'Alternative stage probe skipped: ' . $index;
        }
        $uncertainties[] = 'Source scanning reached the per-file limit; 5 files were not scanned.';
        $uncertainties[] = 'Composer process timed out.';
        $uncertainties[] = 'Project input changed during analysis; dependency and source findings may describe different project states.';
        $uncertainties[] = 'Framework hop unsupported.';
        $lines = implode("\n", (new SummaryUncertainties($uncertainties))->lines());

        self::assertStringContainsString('5 files were not scanned', $lines);
        self::assertStringContainsString('Composer process timed out', $lines);
        self::assertStringContainsString('Limit [input; 1 recorded]: Project input changed during analysis', $lines);
        self::assertStringContainsString('Framework hop unsupported', $lines);
        self::assertStringContainsString('stage/coverage; 31 recorded', $lines);
        self::assertStringContainsString('Additional uncertainties in full details: 30', $lines);
    }

    public function testPlatformAndOperationalLimitsSurviveAmbientWarnings(): void
    {
        $lines = implode("\n", (new SummaryUncertainties([
            'Dependency resolution does not prove application runtime compatibility.',
            'Restricted execution uses analyzer-owned workspaces.',
            'Composer extension checks used the analyzer runtime.',
            'Composer process timed out.',
        ]))->lines());
        self::assertStringContainsString('Limit [platform; 1 recorded]: Composer extension checks used the analyzer runtime.', $lines);
        self::assertStringContainsString('Limit [operational; 2 recorded]: Composer process timed out.', $lines);
    }

    public function testSourceReviewShowsAConcreteLocationWithoutDumpingOccurrences(): void
    {
        $usage = new SourceUsage('app/Legacy.php', 'Vendor\\Legacy', 'import', ['cause-1'], 42);
        $finding = new SourceImpactFinding('vendor/legacy', 'exact', 'package_change', 'Review this changed API.', 'high', [$usage], ['cause-1']);
        $report = new UpgradeReport(
            $this->request(),
            $this->state(),
            [],
            new LockDiff([]),
            [],
            [$usage],
            [],
            new RiskSummary('low', []),
            new EffortEstimate([0, 0], 'low', [], []),
            [],
            [new Evidence('cause-1', Evidence::E3_PROJECT_SOURCE, 'Recorded source location.')],
            actionableSourceImpact: [$finding]
        );
        $summary = (new SummaryReportWriter())->render($report);

        self::assertStringContainsString('Source review app/Legacy.php:42 [vendor/legacy, high; evidence: cause-1]', $summary);
        self::assertStringContainsString('Review this changed API', $summary);
        self::assertStringContainsString('Manual work: 1 direct source groups', $summary);
    }

    public function testPrivacyAndTerminalControlsArePreservedBeforeLongValuesAreShortened(): void
    {
        $root = $this->request()->projectPath();
        $cause = new Blocker('root-constraint-conflict', 'vendor/legacy', $root . '/private https://user:password@example.test/repo ' . str_repeat('long', 500) . "\n\x1b[31m", 'high', ['cause-1']);
        $summary = (new SummaryReportWriter())->render($this->report([$cause]));

        self::assertStringNotContainsString($root, $summary);
        self::assertStringNotContainsString('password', $summary);
        self::assertStringNotContainsString("\x1b", $summary);
        self::assertStringContainsString('[PROJECT_ROOT]', $summary);
        self::assertStringContainsString('Evidence: cause-1', $summary);
        foreach (explode(PHP_EOL, $summary) as $line) {
            self::assertLessThanOrEqual(320, mb_strlen($line));
        }
    }

    private function blocker(string $subject = 'vendor/legacy', string $type = 'root-constraint-conflict'): Blocker
    {
        return new Blocker($type, $subject, 'The recorded requirement conflicts with the target.', 'high', ['cause-1'], '^2.0', $subject, '1.4.0', '^1.0', [], ['Review ' . $subject . ' requirements.']);
    }

    private function request(): UpgradeRequest
    {
        return new UpgradeRequest(dirname(__DIR__, 5), [new UpgradeTarget('vendor/legacy', '^2.0')], targetPhp: '8.3.0');
    }

    private function state(): ProjectState
    {
        return new ProjectState(
            $this->request()->projectPath(),
            new ComposerJson(['require' => ['vendor/legacy' => '^1.0']]),
            new ComposerLock(['packages' => [['name' => 'vendor/legacy', 'version' => '1.4.0']]], ['vendor/legacy'])
        );
    }

    private function scenario(bool $success): ScenarioResult
    {
        return new ScenarioResult(
            new Scenario('exact-target', $this->request()->targets()),
            $success ? 0 : 2,
            '',
            '',
            $success ? new ComposerLock([]) : null,
            null,
            $success ? null : ScenarioResult::FAILURE_SOLVER
        );
    }

    /**
     * @param list<Blocker> $blockers
     * @param list<ScenarioResult> $scenarios
     * @param list<SourceUsage> $inventory
     * @param list<string> $uncertainties
     */
    private function report(array $blockers = [], array $scenarios = [], ?StagedResolution $staged = null, array $inventory = [], array $uncertainties = []): UpgradeReport
    {
        return new UpgradeReport(
            $this->request(),
            $this->state(),
            $scenarios,
            new LockDiff([]),
            $blockers,
            $inventory,
            [],
            new RiskSummary('low', []),
            new EffortEstimate([0, 0], 'low', [], []),
            array_merge($uncertainties, ['Recorded evidence cause-1.']),
            [new Evidence('cause-1', Evidence::E1_SOLVER, 'Recorded fixture cause.')],
            stagedResolution: $staged
        );
    }
}
