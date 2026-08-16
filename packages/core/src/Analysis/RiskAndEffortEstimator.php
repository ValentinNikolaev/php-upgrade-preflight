<?php

declare(strict_types=1);

namespace PhpUpgradePreflight\Core\Analysis;

use PhpUpgradePreflight\Core\Model\Blocker;
use PhpUpgradePreflight\Core\Model\CompatibilityFinding;
use PhpUpgradePreflight\Core\Model\EffortEstimate;
use PhpUpgradePreflight\Core\Model\PackageChange;
use PhpUpgradePreflight\Core\Model\RiskSummary;
use PhpUpgradePreflight\Core\Model\StageAnalysis;
use PhpUpgradePreflight\Core\Model\StageBlockerEntry;
use PhpUpgradePreflight\Core\Model\StagedResolution;
use PhpUpgradePreflight\Core\Model\SourceImpactFinding;

final class RiskAndEffortEstimator
{
    /**
     * @param list<Blocker> $blockers
     * @param list<PackageChange> $changes
     * @param list<CompatibilityFinding> $findings
     * @param list<SourceImpactFinding> $sourceImpact
     */
    public function estimateRisk(array $blockers, array $changes, array $findings, array $sourceImpact = []): RiskSummary
    {
        $drivers = [];
        $hasResolutionBlocker = $this->hasResolutionBlocker($blockers);
        $hasAdvisory = $this->hasAdvisory($blockers);
        $sourceWeight = $this->actionableWeight($sourceImpact);

        if ($hasResolutionBlocker) {
            $drivers[] = 'Composer resolution is blocked.';
        }
        if ($hasAdvisory) {
            $drivers[] = 'Abandoned packages require replacement or removal.';
        }
        if (count($changes) > 20) {
            $drivers[] = 'Large lockfile transition.';
        }
        if ($findings !== []) {
            $drivers[] = 'Framework compatibility findings require review.';
        }
        if ($sourceWeight >= 4) {
            $drivers[] = 'Weighted actionable source findings require review.';
        }

        $level = $hasResolutionBlocker
            ? 'high'
            : ($sourceWeight >= 10
                ? 'high'
                : ($hasAdvisory || count($changes) > 10 || count($findings) > 2 || $sourceWeight >= 4 ? 'medium' : 'low'));

        return new RiskSummary($level, $drivers);
    }

    /**
     * @param list<Blocker> $blockers
     * @param list<PackageChange> $changes
     * @param list<SourceImpactFinding> $sourceImpact
     * @param list<CompatibilityFinding> $findings
     */
    public function estimateEffort(
        array $blockers,
        array $changes,
        array $sourceImpact,
        array $findings
    ): EffortEstimate {
        $dependency = $blockers !== [] ? [3, 8] : [1, max(2, min(8, count($changes)))];
        $source = [1, max(3, min(16, $this->actionableWeight($sourceImpact) + count($findings) * 2))];
        $tests = [2, 8];

        return new EffortEstimate(
            [$dependency[0] + $source[0] + $tests[0], $dependency[1] + $source[1] + $tests[1]],
            'low',
            [
                'dependency_resolution' => $dependency,
                'source_changes' => $source,
                'tests_and_debugging' => $tests,
            ],
            ['Estimate is heuristic until project-specific tests and Composer solver output are reviewed.']
        );
    }

    /**
     * @param list<StageBlockerEntry> $blockers
     * @param list<CompatibilityFinding> $findings
     * @param list<SourceImpactFinding> $sourceImpact
     */
    public function estimateStageRisk(
        StageAnalysis $stage,
        array $blockers,
        array $findings,
        array $sourceImpact
    ): RiskSummary {
        $stageId = $stage->target()->id();
        $drivers = [];
        if (in_array($stage->resolutionStatus(), [StagedResolution::BLOCKED, StagedResolution::UNKNOWN], true)) {
            $drivers[] = sprintf('Stage %s did not produce a selectable Composer state.', $stageId);
        }
        foreach ($blockers as $blocker) {
            if ($blocker->isBlocking() && $blocker->isActive()) {
                $drivers[] = sprintf('Stage %s retains an active Composer blocker.', $stageId);
                break;
            }
        }
        foreach ($findings as $finding) {
            if ($finding->severity() === 'high') {
                $drivers[] = sprintf('Stage %s: %s', $stageId, $finding->summary());
            }
        }

        $weight = $this->actionableWeight($sourceImpact);
        $level = $drivers !== [] || $weight >= 10
            ? 'high'
            : ($stage->packageChanges() !== [] || $findings !== [] || $weight > 0 ? 'medium' : 'low');

        return new RiskSummary($level, array_values(array_unique($drivers)));
    }

    /**
     * @param list<StageBlockerEntry> $blockers
     * @param list<CompatibilityFinding> $findings
     * @param list<SourceImpactFinding> $sourceImpact
     */
    public function estimateStageEffort(
        StageAnalysis $stage,
        array $blockers,
        array $findings,
        array $sourceImpact
    ): EffortEstimate {
        $activeBlockers = array_filter(
            $blockers,
            static fn (StageBlockerEntry $blocker): bool => $blocker->isBlocking() && $blocker->isActive()
        );
        if ($stage->executionState() === StageAnalysis::SKIPPED) {
            return new EffortEstimate([0, 0], 'low', ['not_estimated' => [0, 0]], [
                sprintf('Stage %s was skipped, so no application-change effort is inferred.', $stage->target()->id()),
            ]);
        }

        $dependency = $activeBlockers !== []
            ? [3, 8]
            : [1, max(2, min(8, count($stage->packageChanges())))];
        $sourceWeight = $this->actionableWeight($sourceImpact) + count($this->uniqueFindings($findings)) * 2;
        $source = $sourceWeight === 0 ? [0, 0] : [1, max(3, min(16, $sourceWeight))];
        $tests = [2, 8];

        return new EffortEstimate(
            [$dependency[0] + $source[0] + $tests[0], $dependency[1] + $source[1] + $tests[1]],
            'low',
            [
                'dependency_resolution' => $dependency,
                'source_changes' => $source,
                'tests_and_debugging' => $tests,
            ],
            [sprintf(
                'Stage %s is estimated from unique package and original-snapshot findings; Composer attempt count is excluded.',
                $stage->target()->id()
            )]
        );
    }

    /**
     * @param list<Blocker> $blockers
     * @param list<PackageChange> $changes
     * @param list<CompatibilityFinding> $findings
     * @param list<SourceImpactFinding> $sourceImpact
     */
    public function estimateAggregateRisk(
        array $blockers,
        array $changes,
        array $findings,
        array $sourceImpact,
        StagedResolution $staged
    ): RiskSummary {
        $risk = $this->estimateRisk(
            $blockers,
            $this->uniqueChanges(array_merge($changes, ...array_map(
                static fn (StageAnalysis $stage): array => $stage->packageChanges(),
                $staged->stages()
            ))),
            $this->uniqueFindings($findings),
            $this->uniqueSourceImpact(array_merge($sourceImpact, $staged->sourceImpact()))
        );
        $drivers = $risk->drivers();
        foreach ($staged->blockerRegistry() as $blocker) {
            if ($blocker->isBlocking() && $blocker->isActive()) {
                $drivers[] = sprintf('Executed stage %s retains an active Composer blocker.', $blocker->stageId());

                return new RiskSummary('high', array_values(array_unique($drivers)));
            }
        }

        return new RiskSummary($risk->level(), array_values(array_unique($drivers)));
    }

    /**
     * @param list<Blocker> $blockers
     * @param list<PackageChange> $changes
     * @param list<SourceImpactFinding> $sourceImpact
     * @param list<CompatibilityFinding> $findings
     */
    public function estimateAggregateEffort(
        array $blockers,
        array $changes,
        array $sourceImpact,
        array $findings,
        StagedResolution $staged
    ): EffortEstimate {
        $allChanges = $this->uniqueChanges(array_merge($changes, ...array_map(
            static fn (StageAnalysis $stage): array => $stage->packageChanges(),
            $staged->stages()
        )));
        $allImpact = $this->uniqueSourceImpact(array_merge($sourceImpact, $staged->sourceImpact()));
        $allFindings = $this->uniqueFindings($findings);
        $hasStageBlocker = false;
        foreach ($staged->blockerRegistry() as $blocker) {
            if ($blocker->isBlocking() && $blocker->isActive()) {
                $hasStageBlocker = true;
                break;
            }
        }

        $dependency = $blockers !== [] || $hasStageBlocker
            ? [3, 8]
            : [1, max(2, min(8, count($allChanges)))];
        $sourceWeight = $this->actionableWeight($allImpact) + count($allFindings) * 2;
        $source = $sourceWeight === 0 ? [0, 0] : [1, max(3, min(16, $sourceWeight))];
        $tests = [2, 8];

        return new EffortEstimate(
            [$dependency[0] + $source[0] + $tests[0], $dependency[1] + $source[1] + $tests[1]],
            'low',
            [
                'dependency_resolution' => $dependency,
                'source_changes' => $source,
                'tests_and_debugging' => $tests,
            ],
            ['Aggregate effort counts each exact package transition, framework finding, and source occurrence once; scenario and repeated-hop counts are excluded.']
        );
    }

    /** @param list<Blocker> $blockers */
    private function hasResolutionBlocker(array $blockers): bool
    {
        foreach ($blockers as $blocker) {
            if ($blocker->blocksResolution()) {
                return true;
            }
        }

        return false;
    }

    /** @param list<Blocker> $blockers */
    private function hasAdvisory(array $blockers): bool
    {
        foreach ($blockers as $blocker) {
            if (!$blocker->blocksResolution()) {
                return true;
            }
        }

        return false;
    }

    /** @param list<SourceImpactFinding> $findings */
    private function actionableWeight(array $findings): int
    {
        $weight = 0;
        foreach ($findings as $finding) {
            $findingWeight = ['low' => 1, 'medium' => 2, 'high' => 4][$finding->severity()] ?? 1;
            if ($finding->relevance() === 'package_change_and_framework_rule') {
                ++$findingWeight;
            }
            if ($finding->ownership() !== 'exact') {
                ++$findingWeight;
            }

            $findingWeight += min(3, max(0, count($finding->occurrences()) - 1));
            $weight += $findingWeight;
        }

        return $weight;
    }

    /**
     * @param list<PackageChange> $changes
     * @return list<PackageChange>
     */
    private function uniqueChanges(array $changes): array
    {
        $unique = [];
        foreach ($changes as $change) {
            $unique[serialize($change->toArray())] = $change;
        }

        return array_values($unique);
    }

    /**
     * @param list<CompatibilityFinding> $findings
     * @return list<CompatibilityFinding>
     */
    private function uniqueFindings(array $findings): array
    {
        $unique = [];
        foreach ($findings as $finding) {
            $unique[serialize([
                $finding->framework(),
                $finding->severity(),
                $finding->summary(),
            ])] = $finding;
        }

        return array_values($unique);
    }

    /**
     * @param list<SourceImpactFinding> $findings
     * @return list<SourceImpactFinding>
     */
    private function uniqueSourceImpact(array $findings): array
    {
        $unique = [];
        foreach ($findings as $finding) {
            $unique[$finding->id()] = isset($unique[$finding->id()])
                ? $unique[$finding->id()]->merge($finding)
                : $finding;
        }

        return array_values($unique);
    }
}
