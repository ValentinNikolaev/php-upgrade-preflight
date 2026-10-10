<?php

declare(strict_types=1);

namespace PhpUpgradePreflight\Core\Analysis;

use PhpUpgradePreflight\Core\Model\EffortEstimate;
use PhpUpgradePreflight\Core\Model\RiskSummary;
use PhpUpgradePreflight\Core\Model\ScenarioResult;
use PhpUpgradePreflight\Core\Model\StageAnalysis;
use PhpUpgradePreflight\Core\Model\StagedResolution;

/** Qualifies the observed assessment without changing schema 0.8's grades or hour ranges. */
final class ReportAssessmentQualifier
{
    /**
     * @param list<ScenarioResult> $scenarios
     * @param list<string> $analysisUncertainties
     * @return array{0:RiskSummary, 1:EffortEstimate}
     */
    public function qualify(
        RiskSummary $risk,
        EffortEstimate $effort,
        array $scenarios,
        array $analysisUncertainties,
        ?StagedResolution $staged
    ): array {
        $drivers = [];
        $assumptions = [
            'Hours are an uncalibrated planning heuristic for reported dependency, source-change, and test/debugging work, not a project quote.',
            'Unobserved migration, deployment, runtime failures, and business validation work are excluded.',
        ];

        $hasSuccessfulTarget = false;
        $hasOperationalFailure = false;
        $baselineFailed = false;
        foreach ($scenarios as $scenario) {
            if ($scenario->scenario()->determinesTargetFeasibility() && $scenario->succeeded()) {
                $hasSuccessfulTarget = true;
            }
            if ($scenario->isOperationalFailure()) {
                $hasOperationalFailure = true;
            }
            if ($scenario->scenario()->isBaselineValidation() && $scenario->isValidationFailure()) {
                $baselineFailed = true;
            }
        }
        if (!$hasSuccessfulTarget && $hasOperationalFailure) {
            $drivers[] = 'Assessment incomplete: Composer analysis failed or timed out; the reported risk grade is not a verified low-risk conclusion.';
            $assumptions[] = 'No successful direct target resolution was observed; the range cannot price an unselected dependency transition.';
        } elseif (!$hasSuccessfulTarget) {
            $drivers[] = 'No successful direct target resolution was observed; the risk grade is not a verified low-risk conclusion.';
            $assumptions[] = 'The range cannot price an unselected dependency transition.';
        } elseif ($hasOperationalFailure) {
            $drivers[] = 'Assessment degraded: at least one Composer scenario failed operationally; review its outcome before relying on the observed risk grade.';
        }
        if ($baselineFailed) {
            $drivers[] = 'Assessment degraded: Composer baseline validation failed; target results may include pre-existing manifest or lockfile errors.';
            $assumptions[] = 'The range does not price work to repair pre-existing Composer input errors.';
        }

        if ($analysisUncertainties !== []) {
            $drivers[] = 'Assessment incomplete: some metadata, source, adapter, or input checks were unavailable; review uncertainties before interpreting the risk grade.';
            $assumptions[] = 'Work in unobserved inputs is excluded from the hour range.';
        }

        if ($staged !== null) {
            if (in_array($staged->status(), [StagedResolution::FEASIBLE, StagedResolution::FEASIBLE_WITH_CHANGES], true)
                && !$hasSuccessfulTarget) {
                $drivers[] = 'The staged path has a selectable result but the direct final target does not; validate each hop and do not infer direct feasibility.';
            }
            if ($hasSuccessfulTarget && $staged->status() === StagedResolution::BLOCKED) {
                $drivers[] = 'The direct final target resolved but the staged path is blocked; no blocked hop is cleared by the direct result.';
            }
            if ($staged->status() === StagedResolution::UNKNOWN || $this->hasSkippedStage($staged)) {
                $drivers[] = 'Staged assessment is incomplete; skipped or unknown transitions have no verified compatibility conclusion.';
                $assumptions[] = 'The range does not price unexecuted staged transitions.';
            }
        }

        $drivers[] = 'Risk level grades observed dependency, framework, and source findings only; it does not verify application runtime safety.';
        $drivers = array_merge($drivers, $risk->drivers());
        $assumptions = array_merge($assumptions, $effort->assumptions());

        return [
            new RiskSummary($risk->level(), array_values(array_unique($drivers))),
            new EffortEstimate($effort->rangeHours(), $effort->confidence(), $effort->components(), array_values(array_unique($assumptions))),
        ];
    }

    private function hasSkippedStage(StagedResolution $staged): bool
    {
        foreach ($staged->stages() as $stage) {
            if ($stage->executionState() === StageAnalysis::SKIPPED) {
                return true;
            }
        }

        return false;
    }
}
