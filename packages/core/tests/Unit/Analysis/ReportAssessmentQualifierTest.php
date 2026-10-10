<?php

declare(strict_types=1);

namespace PhpUpgradePreflight\Core\Tests\Unit\Analysis;

use PhpUpgradePreflight\Core\Analysis\ReportAssessmentQualifier;
use PhpUpgradePreflight\Core\Model\ComposerLock;
use PhpUpgradePreflight\Core\Model\EffortEstimate;
use PhpUpgradePreflight\Core\Model\FrameworkStageTarget;
use PhpUpgradePreflight\Core\Model\RiskSummary;
use PhpUpgradePreflight\Core\Model\Scenario;
use PhpUpgradePreflight\Core\Model\ScenarioResult;
use PhpUpgradePreflight\Core\Model\StageAnalysis;
use PhpUpgradePreflight\Core\Model\StagedResolution;
use PhpUpgradePreflight\Core\Model\UpgradeTarget;
use PhpUpgradePreflight\Core\Model\UpgradeTargetSet;
use PHPUnit\Framework\TestCase;

final class ReportAssessmentQualifierTest extends TestCase
{
    /** @dataProvider unavailableOutcomeProvider */
    public function testOperationallyUnavailableTargetCannotReadAsVerifiedLowRisk(string $outcome): void
    {
        $scenario = new Scenario('exact-target', new UpgradeTargetSet([new UpgradeTarget('vendor/package', '^2.0')]));
        $result = new ScenarioResult($scenario, 1, '', 'Analysis unavailable.', null, null, ScenarioResult::FAILURE_OPERATIONAL, null, [], 0, null, [], $outcome);
        [$risk, $effort] = (new ReportAssessmentQualifier())->qualify(
            new RiskSummary('low', []),
            new EffortEstimate([2, 8], 'low', [], []),
            [$result],
            [],
            StagedResolution::skipped('stage_target_provider_unavailable')
        );

        self::assertSame('low', $risk->level());
        self::assertStringContainsString('Assessment incomplete', $risk->drivers()[0]);
        self::assertStringContainsString('not a verified low-risk conclusion', $risk->drivers()[0]);
        self::assertSame([2, 8], $effort->rangeHours());
        self::assertStringContainsString('not a project quote', $effort->assumptions()[0]);
        self::assertStringContainsString('deployment', $effort->assumptions()[1]);
        self::assertStringContainsString('unselected dependency transition', implode(' ', $effort->assumptions()));
    }

    /** @return list<array{string}> */
    public function unavailableOutcomeProvider(): array
    {
        return [
            [ScenarioResult::OUTCOME_COMPOSER_MISSING],
            [ScenarioResult::OUTCOME_TIMEOUT],
            [ScenarioResult::OUTCOME_REPOSITORY_METADATA_UNAVAILABLE],
            [ScenarioResult::OUTCOME_INVALID_JSON],
            [ScenarioResult::OUTCOME_PROCESS_FAILURE],
        ];
    }

    public function testSuccessfulSolverWithSourceOmissionAndSkippedStagesRemainsQualified(): void
    {
        $scenario = new Scenario('exact-target', new UpgradeTargetSet([new UpgradeTarget('vendor/package', '^2.0')]));
        $result = new ScenarioResult($scenario, 0, 'Resolved.', '', new ComposerLock([]));
        [$risk, $effort] = (new ReportAssessmentQualifier())->qualify(
            new RiskSummary('low', []),
            new EffortEstimate([2, 8], 'low', [], []),
            [$result],
            ['Source file count limit omitted 12 files.', 'Adapter rule failed.'],
            StagedResolution::skipped('hop_budget_exceeded')
        );

        self::assertStringContainsString('metadata, source, adapter, or input checks were unavailable', $risk->drivers()[0]);
        self::assertStringContainsString('Staged assessment is incomplete', $risk->drivers()[1]);
        self::assertStringContainsString('unobserved inputs', implode(' ', $effort->assumptions()));
        self::assertStringContainsString('unexecuted staged transitions', implode(' ', $effort->assumptions()));
    }

    public function testFailedBaselineValidationQualifiesAFeasibleTarget(): void
    {
        $targets = new UpgradeTargetSet([new UpgradeTarget('vendor/package', '^2.0')]);
        $baseline = new ScenarioResult(
            new Scenario('baseline-validation', $targets, true, false, true),
            1,
            '',
            'Invalid baseline.',
            null,
            null,
            ScenarioResult::FAILURE_VALIDATION
        );
        $target = new ScenarioResult(new Scenario('exact-target', $targets), 0, 'Resolved.', '', new ComposerLock([]));

        [$risk, $effort] = (new ReportAssessmentQualifier())->qualify(
            new RiskSummary('low', []),
            new EffortEstimate([2, 8], 'low', [], []),
            [$baseline, $target],
            [],
            null
        );

        self::assertStringContainsString('baseline validation failed', $risk->drivers()[0]);
        self::assertStringContainsString('pre-existing Composer input errors', implode(' ', $effort->assumptions()));
    }

    public function testSuccessfulTargetWithOperationalFailureAndBlockedPartialStageRemainsDegraded(): void
    {
        $targets = new UpgradeTargetSet([new UpgradeTarget('vendor/package', '^2.0')]);
        $successful = new ScenarioResult(new Scenario('exact-target', $targets), 0, 'Resolved.', '', new ComposerLock([]));
        $failed = new ScenarioResult(
            new Scenario('preferred-target', $targets),
            1,
            '',
            'Composer timed out.',
            null,
            null,
            ScenarioResult::FAILURE_OPERATIONAL,
            null,
            [],
            0,
            null,
            [],
            ScenarioResult::OUTCOME_TIMEOUT
        );
        $stageTargets = new UpgradeTargetSet([new UpgradeTarget('laravel/framework', '^11.0')], '8.3.0');
        $stageTarget = new FrameworkStageTarget('laravel-10-to-11', 'laravel', 10, 11, $stageTargets, '8.3.0', [], [], ['e1']);
        $skipped = new StageAnalysis($stageTarget, StageAnalysis::SKIPPED, null, [], null, null, null, null, [], 'previous_hop_blocked');
        $staged = new StagedResolution(StagedResolution::EVALUATED, StagedResolution::BLOCKED, 'laravel', [$skipped], []);

        [$risk, $effort] = (new ReportAssessmentQualifier())->qualify(
            new RiskSummary('low', []),
            new EffortEstimate([2, 8], 'low', [], []),
            [$successful, $failed],
            [],
            $staged
        );

        self::assertStringContainsString('at least one Composer scenario failed operationally', $risk->drivers()[0]);
        self::assertStringContainsString('direct final target resolved but the staged path is blocked', $risk->drivers()[1]);
        self::assertStringContainsString('skipped or unknown transitions', $risk->drivers()[2]);
        self::assertStringContainsString('unexecuted staged transitions', implode(' ', $effort->assumptions()));
    }

    public function testFeasibleStagedPathDoesNotImplyDirectTargetFeasibility(): void
    {
        $staged = new StagedResolution(StagedResolution::EVALUATED, StagedResolution::FEASIBLE, 'laravel', [], []);

        [$risk] = (new ReportAssessmentQualifier())->qualify(
            new RiskSummary('low', []),
            new EffortEstimate([0, 0], 'low', [], []),
            [],
            [],
            $staged
        );

        self::assertStringContainsString('No successful direct target resolution', $risk->drivers()[0]);
        self::assertStringContainsString('staged path has a selectable result but the direct final target does not', $risk->drivers()[1]);
    }
}
