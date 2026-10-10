<?php

declare(strict_types=1);

namespace PhpUpgradePreflight\Core\Analysis;

use PhpUpgradePreflight\Core\Model\Blocker;
use PhpUpgradePreflight\Core\Model\CompatibilityFinding;
use PhpUpgradePreflight\Core\Model\EffortEstimate;
use PhpUpgradePreflight\Core\Model\Evidence;
use PhpUpgradePreflight\Core\Model\EvidenceLedger;
use PhpUpgradePreflight\Core\Model\FrameworkGuidance;
use PhpUpgradePreflight\Core\Model\LockDiff;
use PhpUpgradePreflight\Core\Model\PlanStage;
use PhpUpgradePreflight\Core\Model\ProjectState;
use PhpUpgradePreflight\Core\Model\RiskSummary;
use PhpUpgradePreflight\Core\Model\ScenarioResult;
use PhpUpgradePreflight\Core\Model\SourceImpactFinding;
use PhpUpgradePreflight\Core\Model\SourceUsage;
use PhpUpgradePreflight\Core\Model\StagedResolution;
use PhpUpgradePreflight\Core\Model\TargetPlatform;
use PhpUpgradePreflight\Core\Model\UpgradeReport;
use PhpUpgradePreflight\Core\Model\UpgradeRequest;

/**
 * Single construction point for every {@see UpgradeReport}.
 *
 * Correlated source impact is supplied by the caller rather than rebuilt here: the
 * analyzer owns the ownership index and the evidence ledger, and a second, weaker
 * construction path would emit findings without package ownership or E2 evidence.
 */
final class ReportAssembler
{
    private ReportSectionBuilder $sectionBuilder;
    private ReportAssessmentQualifier $assessmentQualifier;

    public function __construct(?ReportSectionBuilder $sectionBuilder = null)
    {
        $this->sectionBuilder = $sectionBuilder ?? new ReportSectionBuilder();
        $this->assessmentQualifier = new ReportAssessmentQualifier();
    }

    /**
     * @param list<ScenarioResult> $scenarioResults
     * @param list<Blocker> $blockers
     * @param list<SourceUsage> $sourceImpact
     * @param list<SourceImpactFinding> $actionableSourceImpact
     * @param list<CompatibilityFinding> $frameworkFindings
     * @param list<string> $sourceUncertainties
     * @param list<FrameworkGuidance> $frameworkGuidance
     */
    public function assemble(
        UpgradeRequest $request,
        ProjectState $project,
        array $scenarioResults,
        LockDiff $lockDiff,
        array $blockers,
        array $sourceImpact,
        array $actionableSourceImpact,
        array $frameworkFindings,
        RiskSummary $risk,
        EffortEstimate $effort,
        array $sourceUncertainties,
        EvidenceLedger $evidence,
        array $frameworkGuidance = [],
        ?TargetPlatform $platform = null,
        ?StagedResolution $stagedResolution = null
    ): UpgradeReport {
        $actionableSourceImpact = array_values($actionableSourceImpact);
        $blocking = [];
        $advisory = [];
        foreach ($blockers as $blocker) {
            if ($blocker->blocksResolution()) {
                $blocking[] = $blocker;
            } else {
                $advisory[] = $blocker;
            }
        }
        $blockers = array_merge($blocking, $advisory);
        $sections = $this->sectionBuilder->build(
            $request,
            $project,
            $scenarioResults,
            $lockDiff,
            $blockers,
            $actionableSourceImpact,
            $frameworkFindings,
            $sourceUncertainties,
            $evidence,
            $stagedResolution
        );
        [$risk, $effort] = $this->assessmentQualifier->qualify(
            $risk,
            $effort,
            $scenarioResults,
            $sourceUncertainties,
            $stagedResolution
        );

        return new UpgradeReport(
            request: $request,
            projectState: $project,
            scenarios: $scenarioResults,
            lockDiff: $lockDiff,
            blockers: $blockers,
            sourceImpact: $sourceImpact,
            frameworkFindings: $frameworkFindings,
            risk: $risk,
            effort: $effort,
            uncertainties: $sections->uncertainties(),
            evidence: $evidence->all(),
            rootConstraintChanges: $sections->rootConstraintChanges(),
            planStages: $sections->planStages(),
            tests: $sections->tests(),
            actionableSourceImpact: $actionableSourceImpact,
            frameworkGuidance: $frameworkGuidance,
            targetPlatform: $platform,
            stagedResolution: $stagedResolution
        );
    }

    /**
     * Builds the terminal report for a project whose Composer input could not be read.
     *
     * No scenario, section, or evidence analysis is possible in that state, so the report
     * carries only the failing scenario and the uncertainty that explains it.
     */
    public static function inputFailure(
        UpgradeRequest $request,
        ProjectState $project,
        ScenarioResult $result,
        string $message
    ): UpgradeReport {
        $evidence = new EvidenceLedger();
        $evidenceId = $evidence->add(
            'project-input',
            Evidence::E3_PROJECT_SOURCE,
            'Composer project input could not be loaded.',
            'high',
            ['outcome' => $result->outcome()]
        )->id();

        return new UpgradeReport(
            request: $request,
            projectState: $project,
            scenarios: [$result],
            lockDiff: new LockDiff([]),
            blockers: [],
            sourceImpact: [],
            frameworkFindings: [],
            risk: new RiskSummary('high', [
                'Assessment unavailable: Composer project input is incomplete; the high grade describes analysis uncertainty, not measured upgrade difficulty.',
            ]),
            effort: new EffortEstimate(
                [0, 0],
                'low',
                [],
                [
                    'Upgrade effort was not estimated because Composer project input could not be loaded; 0-0 hours is a not-estimated sentinel, not a zero-work quote.',
                    'Unobserved migration, deployment, runtime failures, and business validation work are excluded.',
                ]
            ),
            uncertainties: [sprintf('Composer project input could not be loaded: %s', $message)],
            evidence: $evidence->all(),
            planStages: [new PlanStage(
                'project-input',
                'Restore readable Composer project input before assessing the upgrade.',
                ['Repair the reported Composer project input and rerun analysis before planning dependency changes.'],
                [$evidenceId]
            )],
            stagedResolution: StagedResolution::skipped('project_input_failure')
        );
    }
}
