<?php

declare(strict_types=1);

namespace PhpUpgradePreflight\Core\Tests\Unit\Reporting;

use PhpUpgradePreflight\Core\Model\Blocker;
use PhpUpgradePreflight\Core\Model\ComposerJson;
use PhpUpgradePreflight\Core\Model\ComposerLock;
use PhpUpgradePreflight\Core\Model\CompatibilityFinding;
use PhpUpgradePreflight\Core\Model\EffortEstimate;
use PhpUpgradePreflight\Core\Model\Evidence;
use PhpUpgradePreflight\Core\Model\FrameworkStageTarget;
use PhpUpgradePreflight\Core\Model\FrameworkGuidance;
use PhpUpgradePreflight\Core\Model\FrameworkHop;
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
    public function testOnePackageCauseUsesAShortFirstScreenWithoutEmptySectionsOrRoutinePolicies(): void
    {
        $request = new UpgradeRequest(dirname(__DIR__, 5), [new UpgradeTarget('vendor/legacy', '^3.0')]);
        $cause = new Blocker('package-not-found', 'vendor/legacy', 'Composer could not find the requested version.', 'high', ['cause-1'], '^3.0', options: ['Verify the constraint and configured repositories for vendor/legacy.']);
        $report = new UpgradeReport(
            $request,
            $this->state(),
            [],
            new LockDiff([]),
            [$cause],
            [],
            [],
            new RiskSummary('low', []),
            new EffortEstimate([0, 0], 'low', [], []),
            [
                'No PHP source files were scanned.',
                'Composer extension checks used the analyzer runtime because no complete explicit extension platform was supplied.',
                'Dependency resolution does not prove application runtime compatibility; the project test suite must run on the target runtime.',
                'Restricted Composer execution uses sanitized analyzer-owned Composer state and best-effort offline behavior; it is not a process or OS network sandbox, and user-selected executables plus Git/SSH helpers remain residual boundaries.',
            ],
            [new Evidence('cause-1', Evidence::E1_SOLVER, 'Recorded package lookup failure.')]
        );
        $summary = (new SummaryReportWriter())->render($report);

        self::assertLessThanOrEqual(15, substr_count($summary, PHP_EOL));
        self::assertStringContainsString('vendor/legacy (cause version unverified)', $summary);
        self::assertStringContainsString('installed context: 1.4.0 (project lock)', $summary);
        self::assertStringContainsString('Suggested next action (not dependency-checked): Verify the constraint', $summary);
        self::assertStringContainsString('No PHP source files were scanned.', $summary);
        self::assertStringContainsString('Composer extension checks used the analyzer runtime', $summary);
        self::assertSame(1, substr_count($summary, 'application runtime compatibility'));
        foreach (['Manual work:', '0 checked', '0 shown', 'Staged path:', 'PHP: current', 'Restricted Composer execution', 'Compatible Composer execution'] as $emptyOrRoutine) {
            self::assertStringNotContainsString($emptyOrRoutine, $summary);
        }
        self::assertStringContainsString('Compatible Composer execution may inherit', (new JsonReportWriter())->render($report));
    }

    public function testBlockedOutputGroupsRealCausesWithVersionsProvenanceAndUnverifiedActions(): void
    {
        $cause = $this->blocker();
        $report = $this->report([$cause, $cause]);
        $summary = (new SummaryReportWriter())->render($report);

        self::assertStringContainsString('Direct target: blocked', $summary);
        self::assertStringContainsString('vendor/legacy 1.4.0', $summary);
        self::assertStringContainsString('project lock, root dependency', $summary);
        self::assertStringContainsString('Evidence: cause-1', $summary);
        self::assertStringContainsString('Suggested next action (not dependency-checked): Review vendor/legacy requirements.', $summary);
        self::assertSame(1, substr_count($summary, '- direct blocking [direct]:'));
        self::assertStringNotContainsString('Additional direct blocking groups', $summary);
        self::assertStringNotContainsString('hours', $summary);
    }

    public function testAdvisoryOnlySuccessfulResolutionDoesNotBecomeBlocked(): void
    {
        $advisory = $this->blocker('vendor/legacy', 'abandoned-package');
        $report = $this->report([$advisory], [$this->scenario(true)]);
        $summary = (new SummaryReportWriter())->render($report);

        self::assertStringContainsString('Direct target: feasible', $summary);
        self::assertStringContainsString('advisory (does not block resolution)', $summary);
        self::assertStringNotContainsString('- direct blocking', $summary);
        self::assertStringContainsString('Not checked: application runtime compatibility', $summary);
    }

    /** @dataProvider unknownExplanationVariants */
    public function testGenericFirstUnknownsDoNotHideSecurityGuidanceEvenWithoutCanonicalGrouping(bool $sameSummary): void
    {
        $generic = new Blocker('unknown-composer-failure', 'vendor/legacy', 'Composer resolution failed without an established edge.', 'low', ['cause-1'], '^2.0', dependencyPath: ['vendor/legacy']);
        $policy = new Blocker('unknown-composer-failure', 'vendor/legacy', $sameSummary ? $generic->summary() : 'Composer rejected candidates because of security advisories.', 'high', ['cause-1'], '^2.0', dependencyPath: ['vendor/legacy'], options: ['Review the recorded security advisories and project security policy.']);
        $report = $this->report([$generic, $policy, $generic, $policy]);
        $summary = (new SummaryReportWriter())->render($report);

        self::assertSame(2, substr_count($summary, '- direct blocking [direct]:'));
        self::assertStringContainsString($generic->summary(), $summary);
        self::assertStringContainsString($policy->summary(), $summary);
        self::assertStringContainsString($policy->options()[0], $summary);
        self::assertSame(2, substr_count($summary, 'Evidence: cause-1'));
        self::assertSame('blocked', $report->resolutionStatus());
    }

    /** @return list<array{bool}> */
    public function unknownExplanationVariants(): array
    {
        return [[false], [true]];
    }

    public function testActiveStageAdvisoriesRemainSeparateFromBlockingStageCauses(): void
    {
        $entry = StageBlockerEntry::detected('hop-1-2', 1, 'exact', $this->blocker('vendor/legacy', 'abandoned-package'));
        $staged = new StagedResolution('evaluated', 'feasible', 'fixture', [], [$entry]);
        $summary = (new SummaryReportWriter())->render($this->report([], [$this->scenario(true)], $staged));

        self::assertStringContainsString('Direct target: feasible', $summary);
        self::assertStringContainsString('[hop-1-2 advisory]: vendor/legacy 1.4.0', $summary);
        self::assertStringNotContainsString('- active staged blocking', $summary);
        self::assertSame(1, substr_count($summary, '- advisory (does not block resolution)'));
    }

    public function testFrameworkReviewsAreBoundedAndLateUnsupportedCoverageRemainsVisible(): void
    {
        $findings = [];
        foreach (['Review bootstrap changes.', 'Review service registration.', 'Review additional framework work.'] as $message) {
            $findings[] = new CompatibilityFinding('fixture-0', 'high', $message, ['cause-1'], [['from_major' => 1, 'to_major' => 2]]);
        }
        $guidance = [];
        for ($index = 0; $index < 4; ++$index) {
            $guidance[] = $this->guidance('fixture-' . $index);
        }
        $guidance[] = new FrameworkGuidance(
            'unsupported-fixture',
            1,
            2,
            'unsupported',
            [new FrameworkHop(1, 2, 'unsupported', null, ['cause-1'])],
            ['This hop is unsupported.'],
            ['cause-1']
        );
        $summary = (new SummaryReportWriter())->render($this->report(frameworkFindings: $findings, frameworkGuidance: $guidance));

        self::assertStringContainsString('Review bootstrap changes.', $summary);
        self::assertStringContainsString('Review service registration.', $summary);
        self::assertStringNotContainsString('Review additional framework work.', $summary);
        self::assertStringContainsString('3 framework findings', $summary);
        self::assertStringContainsString('Incomplete framework guidance: unsupported: 1', $summary);
    }

    public function testManySourceGroupsStayBoundedWhileFrameworkWorkRemainsDiscoverable(): void
    {
        $findings = [];
        foreach (range(1, 3) as $index) {
            $usage = new SourceUsage('app/Review' . $index . '.php', 'Vendor\\Legacy', 'import', ['cause-1'], $index);
            $findings[] = new SourceImpactFinding(
                'vendor/legacy',
                'exact',
                'package_change',
                'Review changed API ' . $index,
                'high',
                [$usage],
                ['cause-1']
            );
        }
        $framework = new CompatibilityFinding('fixture-0', 'high', 'Additional framework work.', ['cause-1'], [['from_major' => 1, 'to_major' => 2]]);
        $summary = (new SummaryReportWriter())->render($this->report(frameworkFindings: [$framework], frameworkGuidance: [$this->guidance('fixture-0')], sourceFindings: $findings));

        self::assertStringContainsString('Manual work: 3 direct source groups, 0 staged source groups, 1 framework findings', $summary);
        self::assertStringContainsString('app/Review1.php:1', $summary);
        self::assertStringNotContainsString('app/Review2.php', $summary);
        self::assertStringNotContainsString('app/Review3.php', $summary);
        self::assertStringContainsString('Additional framework work.', $summary);
        self::assertStringContainsString('Additional manual-review entries in full details: 2.', $summary);
    }

    public function testHumanStopExplanationPreservesTheCanonicalReasonAndKnownLockedVersion(): void
    {
        $staged = new StagedResolution('evaluated', 'blocked', 'fixture', [], [], 'blocking_registry_not_cleared');
        $cause = new Blocker('package-not-found', 'vendor/legacy', 'Requested package version was not found.', 'high', ['cause-1'], '^2.0');
        $report = $this->report([$cause], [], $staged);
        $summary = (new SummaryReportWriter())->render($report);

        self::assertStringContainsString('stop: active dependency blockers remain', $summary);
        self::assertStringNotContainsString('blocking_registry_not_cleared', $summary);
        self::assertSame('blocking_registry_not_cleared', $report->toArray()['staged_resolution']['stop_reason']);
        self::assertStringContainsString('vendor/legacy (cause version unverified)', $summary);
        self::assertStringContainsString('installed context: 1.4.0 (project lock)', $summary);
        self::assertStringNotContainsString('observation(s)', $summary);
    }

    /** @dataProvider candidateRelationVersions */
    public function testCandidateRelationsCannotBeAttributedToTheInstalledVersion(?string $reportedVersion, string $expectedVersion): void
    {
        $state = new ProjectState(
            $this->request()->projectPath(),
            new ComposerJson(['require' => ['vendor/legacy' => '^1.0']]),
            new ComposerLock(['packages' => [['name' => 'vendor/legacy', 'version' => '1.4.0', 'require' => ['fixture/framework' => '^7.0']]]], ['vendor/legacy'])
        );
        $cause = new Blocker(
            'transitive-package-conflict',
            'fixture/framework',
            'A candidate requires a conflicting framework range.',
            'high',
            ['cause-1'],
            '^9.0',
            'vendor/legacy',
            $reportedVersion,
            '^8.0',
            [],
            ['Check a solver-backed vendor/legacy candidate before changing constraints.']
        );
        $report = new UpgradeReport(
            $this->request(),
            $state,
            [],
            new LockDiff([]),
            [$cause],
            [],
            [],
            new RiskSummary('high', []),
            new EffortEstimate([0, 0], 'low', [], []),
            [],
            [new Evidence('cause-1', Evidence::E1_SOLVER, 'Recorded candidate relation.')]
        );
        $summary = (new SummaryReportWriter())->render($report);

        self::assertStringContainsString($expectedVersion, $summary);
        self::assertStringContainsString('installed context: 1.4.0 (project lock)', $summary);
        self::assertStringContainsString('Constraint: ^8.0', $summary);
        self::assertStringNotContainsString('vendor/legacy 1.4.0', $summary);
        $installed = $state->composerLock()->package('vendor/legacy');
        self::assertNotNull($installed);
        self::assertSame('^7.0', $installed->requirements()['fixture/framework']);
        self::assertSame('^8.0', $report->blockers()[0]->conflict());
    }

    /** @return list<array{?string, string}> */
    public static function candidateRelationVersions(): array
    {
        return [[null, '(cause version unverified)'], ['2.0.0', '2.0.0 (reported cause version)']];
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
        self::assertStringContainsString('Additional active staged blocking groups in full details: 15 (6 of 21 shown)', $summary);
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
            new StageAttempt(1, 'suggested', [new RootConstraintChange('vendor/unverified', 'update', '^1.0', '^9.0', 'Unverified suggestion.', ['cause-1'])], $this->scenario(false), $fingerprint, null, [], ['cause-1'], false),
            new StageAttempt(2, 'repair', [$change,
                new RootConstraintChange('vendor/test-tools', 'update', '^1.0', '^3.0', 'Recorded repair.', ['cause-1']),
                new RootConstraintChange('vendor/optional', 'update', '^1.0', '^4.0', 'Recorded repair.', ['cause-1']),
            ], $this->scenario(true), $fingerprint, $fingerprint, [], ['cause-1'], true),
        ];
        $targets = $request->targets();
        $target = new FrameworkStageTarget('hop-1-2', 'fixture', 1, 2, $targets, '8.3.0', [], [], ['cause-1']);
        $stage = new StageAnalysis($target, 'evaluated', 'feasible_with_changes', $attempts, 2, null, $fingerprint, $fingerprint, []);
        $staged = new StagedResolution('evaluated', 'feasible_with_changes', 'fixture', [$stage], []);
        $summary = (new SummaryReportWriter())->render($this->report([], [$this->scenario(true)], $staged));

        self::assertStringContainsString('Dependency-checked stage candidate [hop-1-2]: vendor/legacy ^1.0 -> ^2.0', $summary);
        self::assertSame(2, substr_count($summary, 'Dependency-checked stage candidate'));
        self::assertStringNotContainsString('vendor/unverified', $summary);
        self::assertStringNotContainsString('vendor/optional', $summary);
        self::assertStringContainsString('Additional dependency-checked root changes in full details: 1.', $summary);
        self::assertStringContainsString('Not checked: application runtime compatibility', $summary);
    }

    public function testHugeInventoryAndEvidenceContextsAreNotMaterializedBySummary(): void
    {
        $writer = new SummaryReportWriter();
        $small = $this->report([$this->blocker()]);
        $usage = new SourceUsage('app/Hidden.php', "UNSERIALIZED\xB1", 'import', ['cause-1']);
        $large = $this->report([$this->blocker()], [], null, array_fill(0, 50000, $usage));
        $before = memory_get_usage();
        $peakBefore = memory_get_peak_usage(true);
        $summary = $writer->render($large);

        self::assertSame($writer->render($small), $summary);
        self::assertLessThan(2 * 1024 * 1024, memory_get_usage() - $before);
        self::assertLessThan(4 * 1024 * 1024, memory_get_peak_usage(true) - $peakBefore);
        self::assertLessThanOrEqual(60, substr_count($summary, PHP_EOL));
        self::assertStringNotContainsString('Hidden.php', $summary);
        self::assertStringNotContainsString('UNSERIALIZED', $summary);
        $this->expectException(\JsonException::class);
        (new JsonReportWriter())->render($this->report([$this->blocker()], [], null, [$usage]));
    }

    public function testCriticalSourceOmissionsPrecedeAmbientUncertaintyAndAllOthersAreDiscoverable(): void
    {
        $uncertainties = array_map(static fn (int $index): string => 'Ambient uncertainty ' . $index, range(1, 10));
        $uncertainties[] = 'Source scan omitted oversized files (cause-1).';
        $summary = (new SummaryReportWriter())->render($this->report([$this->blocker()], [], null, [], $uncertainties));

        self::assertStringContainsString('Source scan omitted oversized files (cause-1)', $summary);
        self::assertStringContainsString('Additional uncertainties in full details:', $summary);
        self::assertStringContainsString('Not checked: application runtime compatibility', $summary);
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
        self::assertStringContainsString('Limit [input]: Project input changed during analysis', $lines);
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
        self::assertStringContainsString('Limit [platform]: Composer extension checks used the analyzer runtime.', $lines);
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
            self::assertLessThanOrEqual(320, strlen($line));
        }
    }

    /** @dataProvider malformedDisplayedText */
    public function testMalformedDisplayedLocationsAndProseRemainUsefulValidUtf8WithinTheByteBudget(string $path, string $prose, string $expectedPath, string $expectedProse, bool $truncated): void
    {
        $usage = new SourceUsage($path, 'Vendor\\Legacy', 'import', ['cause-1'], 42);
        $finding = new SourceImpactFinding('vendor/legacy', 'exact', 'package_change', $prose, 'high', [$usage], ['cause-1']);
        $summary = (new SummaryReportWriter())->render($this->report(sourceFindings: [$finding]));

        self::assertSame(1, preg_match('//u', $summary));
        self::assertStringContainsString('- Source review ' . $expectedPath, $summary);
        if ($expectedProse !== '') {
            self::assertStringContainsString($expectedProse, $summary);
        }
        self::assertSame($truncated, str_contains($summary, '[TRUNCATED:'));
        foreach (explode(PHP_EOL, $summary) as $line) {
            self::assertLessThanOrEqual(320, strlen($line));
        }
    }

    /** @return array<string, array{string, string, string, string, bool}> */
    public static function malformedDisplayedText(): array
    {
        return [
            'short path and prose' => ["app/Legacy\xB1.php", "Review malformed \xC3 prose.", "app/Legacy\u{FFFD}.php:42", "Review malformed \u{FFFD} prose.", false],
            'long path' => ["app/Legacy\xB1" . str_repeat('é', 200) . '.php', 'Review this API.', "app/Legacy\u{FFFD}é", '', true],
            'long prose' => ['app/Legacy.php', "Review malformed \xB1 " . str_repeat('é', 200), 'app/Legacy.php:42', "Review malformed \u{FFFD} é", true],
        ];
    }

    public function testValidUnicodeIsUnchangedAndTerminalControlsAreRemovedBeforeTheByteBudget(): void
    {
        $path = 'app/Été日本.php';
        $prose = 'Review café 日本 😀.';
        $usage = new SourceUsage($path, 'Vendor\\Legacy', 'import', ['cause-1'], 42);
        $finding = new SourceImpactFinding('vendor/legacy', 'exact', 'package_change', $prose, 'high', [$usage], ['cause-1']);
        $writer = new SummaryReportWriter();
        $summary = $writer->render($this->report(sourceFindings: [$finding]));

        self::assertStringContainsString($path . ':42', $summary);
        self::assertStringContainsString($prose, $summary);
        self::assertStringNotContainsString("\u{FFFD}", $summary);
        $controlledProse = "Review\u{0085}café\u{009B}日本\x1b[31m\x00\r\n 😀." . str_repeat("\u{0085}", 150) . ' End.';
        $controlled = new SourceImpactFinding('vendor/legacy', 'exact', 'package_change', $controlledProse, 'high', [$usage], ['cause-1']);
        $controlledSummary = $writer->render($this->report(sourceFindings: [$controlled]));

        self::assertStringContainsString('Review café 日本 [31m    😀.', $controlledSummary);
        self::assertStringContainsString('😀.' . str_repeat(' ', 150) . ' End.', $controlledSummary);
        self::assertStringNotContainsString('[TRUNCATED:', $controlledSummary);
        foreach (explode(PHP_EOL, $controlledSummary) as $line) {
            self::assertSame(0, preg_match('/\p{Cc}/u', $line));
            self::assertLessThanOrEqual(320, strlen($line));
        }
    }

    public function testMalformedDisplayedProseRetainsCompleteValuePrivacyBeforeNormalizationAndTruncation(): void
    {
        $root = $this->request()->projectPath();
        $prose = "Review malformed \xB1 " . $root . '/private https://user:password@example.test/repo ' . str_repeat('é', 200);
        $cause = new Blocker('root-constraint-conflict', 'vendor/legacy', $prose, 'high', ['cause-1']);
        $summary = (new SummaryReportWriter())->render($this->report([$cause]));

        self::assertSame(1, preg_match('//u', $summary));
        self::assertStringContainsString("Review malformed \u{FFFD} [PROJECT_ROOT]/private", $summary);
        self::assertStringNotContainsString($root, $summary);
        self::assertStringNotContainsString('user:password', $summary);
        self::assertStringContainsString('[TRUNCATED:', $summary);
        foreach (explode(PHP_EOL, $summary) as $line) {
            self::assertLessThanOrEqual(320, strlen($line));
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

    private function guidance(string $framework): FrameworkGuidance
    {
        return new FrameworkGuidance(
            $framework,
            1,
            2,
            'supported',
            [new FrameworkHop(1, 2, 'supported', 'fixture-rules', ['cause-1'])],
            [],
            ['cause-1']
        );
    }

    /**
     * @param list<Blocker> $blockers
     * @param list<ScenarioResult> $scenarios
     * @param list<SourceUsage> $inventory
     * @param list<string> $uncertainties
     * @param list<CompatibilityFinding> $frameworkFindings
     * @param list<FrameworkGuidance> $frameworkGuidance
     * @param list<SourceImpactFinding> $sourceFindings
     */
    private function report(array $blockers = [], array $scenarios = [], ?StagedResolution $staged = null, array $inventory = [], array $uncertainties = [], array $frameworkFindings = [], array $frameworkGuidance = [], array $sourceFindings = []): UpgradeReport
    {
        return new UpgradeReport(
            $this->request(),
            $this->state(),
            $scenarios,
            new LockDiff([]),
            $blockers,
            $inventory,
            $frameworkFindings,
            new RiskSummary('low', []),
            new EffortEstimate([0, 0], 'low', [], []),
            array_merge($uncertainties, ['Recorded evidence cause-1.']),
            [new Evidence('cause-1', Evidence::E1_SOLVER, 'Recorded fixture cause.')],
            actionableSourceImpact: $sourceFindings,
            frameworkGuidance: $frameworkGuidance,
            stagedResolution: $staged
        );
    }
}
