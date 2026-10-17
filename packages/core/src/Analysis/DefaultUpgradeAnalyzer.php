<?php

declare(strict_types=1);

namespace PhpUpgradePreflight\Core\Analysis;

use PhpUpgradePreflight\Core\Composer\ComposerScenarioRunner;
use PhpUpgradePreflight\Core\Composer\InvalidJsonException;
use PhpUpgradePreflight\Core\Composer\MissingJsonFileException;
use PhpUpgradePreflight\Core\Composer\ProjectStateBuilder;
use PhpUpgradePreflight\Core\Composer\ProjectStateLoadResult;
use PhpUpgradePreflight\Core\Contracts\UpgradeAnalyzer;
use PhpUpgradePreflight\Core\Framework\FrameworkIntegration;
use PhpUpgradePreflight\Core\Model\ComposerLock;
use PhpUpgradePreflight\Core\Model\EvidenceLedger;
use PhpUpgradePreflight\Core\Model\LockDiff;
use PhpUpgradePreflight\Core\Model\Scenario;
use PhpUpgradePreflight\Core\Model\ScenarioResult;
use PhpUpgradePreflight\Core\Model\TargetPlatform;
use PhpUpgradePreflight\Core\Model\UpgradeReport;
use PhpUpgradePreflight\Core\Model\UpgradeRequest;
use PhpUpgradePreflight\Core\Progress\AnalysisPhase;
use PhpUpgradePreflight\Core\Progress\AnalysisProgressEvent;
use PhpUpgradePreflight\Core\Progress\AnalysisProgressReporter;
use PhpUpgradePreflight\Core\Progress\NoOpAnalysisProgressReporter;
use PhpUpgradePreflight\Core\Source\AutoloadOwnershipIndexBuilder;
use PhpUpgradePreflight\Core\Source\SourceUsageScanner;
use PhpUpgradePreflight\Core\Support\PathExposurePolicy;

final class DefaultUpgradeAnalyzer implements UpgradeAnalyzer
{
    private ProjectStateBuilder $projectStateBuilder;
    private ComposerScenarioRunner $scenarioRunner;
    private LockDiffBuilder $lockDiffBuilder;
    private BlockerGrouper $blockerGrouper;
    private SourceUsageScanner $sourceUsageScanner;
    private TargetNormalizer $targetNormalizer;
    private ScenarioSelector $scenarioSelector;
    private FrameworkRuleEngine $frameworkRuleEngine;
    private RiskAndEffortEstimator $riskAndEffortEstimator;
    private ReportAssembler $reportAssembler;
    private AutoloadOwnershipIndexBuilder $ownershipIndexBuilder;
    private SourceImpactBuilder $sourceImpactBuilder;
    private StagedUpgradeOrchestrator $stagedUpgradeOrchestrator;
    private StageAssessmentBuilder $stageAssessmentBuilder;
    private AnalysisProgressReporter $progressReporter;
    private ?AnalysisTimings $timings;
    /** @var array<string, float|int> */
    private array $phaseStartedAt = [];

    /** @param list<FrameworkIntegration> $frameworks */
    public function __construct(
        array $frameworks = [],
        ?ProjectStateBuilder $projectStateBuilder = null,
        ?ComposerScenarioRunner $scenarioRunner = null,
        ?LockDiffBuilder $lockDiffBuilder = null,
        ?BlockerGrouper $blockerGrouper = null,
        ?SourceUsageScanner $sourceUsageScanner = null,
        ?TargetNormalizer $targetNormalizer = null,
        ?FrameworkRuleEngine $frameworkRuleEngine = null,
        ?RiskAndEffortEstimator $riskAndEffortEstimator = null,
        ?ReportAssembler $reportAssembler = null,
        ?ScenarioSelector $scenarioSelector = null,
        ?AutoloadOwnershipIndexBuilder $ownershipIndexBuilder = null,
        ?SourceImpactBuilder $sourceImpactBuilder = null,
        ?StagedUpgradeOrchestrator $stagedUpgradeOrchestrator = null,
        ?StageAssessmentBuilder $stageAssessmentBuilder = null,
        ?AnalysisProgressReporter $progressReporter = null,
        ?AnalysisTimings $timings = null
    ) {
        $this->projectStateBuilder = $projectStateBuilder ?? new ProjectStateBuilder();
        $this->scenarioRunner = $scenarioRunner ?? new ComposerScenarioRunner();
        $this->lockDiffBuilder = $lockDiffBuilder ?? new LockDiffBuilder();
        $this->blockerGrouper = $blockerGrouper ?? new BlockerGrouper();
        $this->sourceUsageScanner = $sourceUsageScanner ?? new SourceUsageScanner();
        $this->targetNormalizer = $targetNormalizer ?? new TargetNormalizer();
        $this->scenarioSelector = $scenarioSelector ?? new ScenarioSelector();
        $this->frameworkRuleEngine = $frameworkRuleEngine ?? new FrameworkRuleEngine($frameworks);
        $this->riskAndEffortEstimator = $riskAndEffortEstimator ?? new RiskAndEffortEstimator();
        $this->reportAssembler = $reportAssembler ?? new ReportAssembler();
        $this->ownershipIndexBuilder = $ownershipIndexBuilder ?? new AutoloadOwnershipIndexBuilder();
        $this->sourceImpactBuilder = $sourceImpactBuilder ?? new SourceImpactBuilder();
        $this->stagedUpgradeOrchestrator = $stagedUpgradeOrchestrator
            ?? new StagedUpgradeOrchestrator($this->scenarioRunner, $this->blockerGrouper, $this->lockDiffBuilder);
        $this->stageAssessmentBuilder = $stageAssessmentBuilder
            ?? new StageAssessmentBuilder($this->sourceImpactBuilder, $this->riskAndEffortEstimator);
        $this->progressReporter = $progressReporter ?? new NoOpAnalysisProgressReporter();
        $this->timings = $timings;
    }

    public function analyzeUpgrade(UpgradeRequest $request): UpgradeReport
    {
        $startedAt = hrtime(true);
        $this->timings?->reset();
        $this->phaseStartedAt = [];
        $this->scenarioRunner->resetAnalysisCaches();
        $this->reportProgress(AnalysisProgressEvent::analysisStarted());

        try {
            $report = $this->performAnalysis($request);
            $this->timings?->complete(true);
            $this->reportProgress(AnalysisProgressEvent::analysisCompleted($report, (hrtime(true) - $startedAt) / 1000000));

            return $report;
        } catch (\Throwable $failure) {
            $this->timings?->complete(false);
            $this->reportProgress(AnalysisProgressEvent::analysisFailed((hrtime(true) - $startedAt) / 1000000));

            throw $failure;
        }
    }

    private function performAnalysis(UpgradeRequest $request): UpgradeReport
    {
        $evidence = new EvidenceLedger();
        $inputConsistency = new InputConsistencyTracker($request->projectPath());
        $this->phaseStarted(AnalysisPhase::PROJECT_LOADING);
        $projectLoad = $this->projectStateBuilder->load($request->projectPath());
        $inputConsistency->checkComposer();
        if (!$projectLoad->succeeded()) {
            $this->phaseCompleted(AnalysisPhase::PROJECT_LOADING, AnalysisProgressEvent::STATUS_FAILED);
            $this->phaseStarted(AnalysisPhase::REPORT_ASSEMBLY);
            $report = $this->inputFailureReport($request, $projectLoad);
            $this->phaseCompleted(AnalysisPhase::REPORT_ASSEMBLY);

            return $report;
        }
        $this->phaseCompleted(AnalysisPhase::PROJECT_LOADING);

        $project = $projectLoad->project();
        $platform = TargetPlatform::fromRequest($request, $project);
        $targets = $this->targetNormalizer->normalize($request->targets()->packageTargets(), $request->targetPhp());
        $analysisUncertainties = [];
        $activeFrameworks = $this->frameworkRuleEngine->activeIntegrations(
            $project,
            $request,
            $evidence,
            $analysisUncertainties
        );
        $packageFamilyClassifiers = $this->frameworkRuleEngine->packageFamilyClassifiers($activeFrameworks);
        $sourcePaths = $this->frameworkRuleEngine->sourcePaths(
            $project,
            $request,
            $activeFrameworks,
            $evidence,
            $analysisUncertainties
        );
        $this->timings?->begin('source_capture');
        $inputConsistency->captureSource($this->sourceUsageScanner, $project, array_values($sourcePaths), $request->sourcePaths() === []);
        $this->timings?->end('source_capture');
        $scenarios = $this->scenarioSelector->select(
            $targets,
            $request->fromPhp(),
            $project->composerJson()->platformPhp(),
            $analysisUncertainties
        );

        $this->phaseStarted(AnalysisPhase::COMPOSER_FEASIBILITY);
        $scenarioResults = [];
        foreach ($scenarios as $scenario) {
            if (!$this->scenarioSelector->shouldExecute($scenario, $request->analysisMode(), $scenarioResults)) {
                $analysisUncertainties[] = sprintf(
                    'Standard analysis skipped Composer alternative "%s"; alternative candidate locks, smallest-change solutions and partial-target ordering diagnostics were not fully compared. No outcome is inferred for skipped alternatives. Use full analysis to execute all alternatives.',
                    $scenario->name()
                );
                continue;
            }
            $this->reportProgress(AnalysisProgressEvent::scenarioStarted($scenario));
            $scenarioResult = $this->scenarioRunner->run($project, $request, $scenario, $platform);
            $scenarioResults[] = $scenarioResult;
            $this->reportProgress(AnalysisProgressEvent::scenarioCompleted($scenarioResult));
        }

        $bestResult = $this->bestSuccessfulResult($project->composerLock(), $scenarioResults);
        $bestLock = $bestResult === null ? null : $bestResult->lock();

        $lockDiff = $bestLock === null
            ? new LockDiff([])
            : $this->lockDiffBuilder->build(
                $project->composerLock(),
                $bestLock,
                $packageFamilyClassifiers,
                $evidence,
                $analysisUncertainties
            );
        $requestedConstraints = $project->composerJson()->rootRequirements();
        foreach ($targets->packageTargets() as $target) {
            $requestedConstraints[$target->package()] = $target->constraint();
        }
        $blockers = $this->blockerGrouper->group(
            $scenarioResults,
            $evidence,
            $bestLock ?? $project->composerLock(),
            $requestedConstraints,
            $platform
        );
        $this->phaseCompleted(AnalysisPhase::COMPOSER_FEASIBILITY);
        $this->phaseStarted(AnalysisPhase::STAGED_RESOLUTION);
        $stagedResolution = $this->stagedUpgradeOrchestrator->analyze(
            $activeFrameworks,
            $project,
            $request,
            $platform,
            $evidence
        );
        $this->phaseCompleted(AnalysisPhase::STAGED_RESOLUTION);
        // A metadata-probe workspace the analyzer could not remove leaves state on disk and makes
        // every Composer version or platform answer derived from that probe suspect, so it has to
        // reach the report rather than staying an in-process accessor nobody reads. Candidate locks
        // are collected after the staged chain for the same reason: a scenario or stage lock entry
        // the analyzer could not index is gone once the workspace is removed, and the candidate
        // package count and package changes silently exclude it.
        $this->phaseStarted(AnalysisPhase::SOURCE_SCAN);
        $sourceUncertainties = array_merge(
            $analysisUncertainties,
            $this->scenarioRunner->probeCleanupUncertainties(),
            $project->composerLock()->unusablePackageUncertainties(),
            $this->scenarioRunner->candidateLockUncertainties()
        );
        $readDigests = [];
        $sourceInventory = $this->sourceUsageScanner->scan(
            $project,
            array_values($sourcePaths),
            $evidence,
            $sourceUncertainties,
            $request->sourcePaths() !== [],
            $activeFrameworks,
            $readDigests,
            $request->sourcePaths() === []
        );
        $this->timings?->begin('source_recheck_after_scan');
        $inputConsistency->checkSource($this->sourceUsageScanner, $project, array_values($sourcePaths), $readDigests, $request->sourcePaths() === []);
        $this->timings?->end('source_recheck_after_scan');
        $this->phaseCompleted(AnalysisPhase::SOURCE_SCAN);
        $this->phaseStarted(AnalysisPhase::FRAMEWORK_EVALUATION);
        $frameworkGuidance = $this->frameworkRuleEngine->assessTransitions(
            $activeFrameworks,
            $project,
            $request,
            $evidence,
            $sourceUncertainties
        );
        $frameworkFindings = $this->frameworkRuleEngine->evaluate(
            $activeFrameworks,
            $project,
            $request,
            $evidence,
            $sourceInventory,
            $frameworkGuidance,
            $this->composerVersion($scenarioResults),
            // A rule that throws is skipped rather than ending the run, but the evidence
            // recorded for that skip is only reachable through the uncertainty that cites
            // it. Dropping the appended entries here would orphan that evidence and turn
            // a contained adapter defect back into a failed report.
            $sourceUncertainties
        );
        $this->phaseCompleted(AnalysisPhase::FRAMEWORK_EVALUATION);
        $this->phaseStarted(AnalysisPhase::REPORT_ASSEMBLY);
        $this->timings?->begin('ownership');
        $ownershipIndex = $this->ownershipIndexBuilder->build(
            $project,
            $sourceUncertainties,
            array_map(static fn ($usage): string => $usage->symbol(), $sourceInventory)
        );
        $this->timings?->end('ownership');
        $this->timings?->begin('source_impact');
        $actionableSourceImpact = $this->sourceImpactBuilder->build(
            $sourceInventory,
            $frameworkFindings,
            $lockDiff->packageChanges(),
            $ownershipIndex,
            $evidence
        );
        $this->timings?->end('source_impact');
        $this->timings?->begin('stage_assessment');
        $stagedResolution = $this->stageAssessmentBuilder->build(
            $stagedResolution,
            $sourceInventory,
            $frameworkFindings,
            $ownershipIndex,
            $evidence,
            $project,
            $request
        );
        $this->timings?->end('stage_assessment');
        $this->timings?->begin('risk_effort');
        $risk = $this->riskAndEffortEstimator->estimateAggregateRisk(
            $blockers,
            $lockDiff->packageChanges(),
            $frameworkFindings,
            $actionableSourceImpact,
            $stagedResolution
        );
        $effort = $this->riskAndEffortEstimator->estimateAggregateEffort(
            $blockers,
            $lockDiff->packageChanges(),
            $actionableSourceImpact,
            $frameworkFindings,
            $stagedResolution
        );

        $this->timings?->end('risk_effort');
        $this->timings?->begin('input_recheck_final');
        $inputConsistency->checkComposer();
        $inputConsistency->checkSource($this->sourceUsageScanner, $project, array_values($sourcePaths), $readDigests, $request->sourcePaths() === []);
        $sourceUncertainties = array_merge($sourceUncertainties, $inputConsistency->uncertainties());
        $this->timings?->end('input_recheck_final');

        $this->timings?->begin('assembly');
        $report = $this->reportAssembler->assemble(
            $request,
            $project,
            $scenarioResults,
            $lockDiff,
            $blockers,
            $sourceInventory,
            $actionableSourceImpact,
            $frameworkFindings,
            $risk,
            $effort,
            $sourceUncertainties,
            $evidence,
            $frameworkGuidance,
            $platform,
            $stagedResolution
        );
        $this->timings?->end('assembly');
        $this->phaseCompleted(AnalysisPhase::REPORT_ASSEMBLY);

        return $report;
    }

    private function phaseStarted(string $phase): void
    {
        $this->phaseStartedAt[$phase] = hrtime(true);
        $this->reportProgress(AnalysisProgressEvent::phaseStarted($phase));
    }

    private function phaseCompleted(
        string $phase,
        string $status = AnalysisProgressEvent::STATUS_SUCCEEDED
    ): void {
        $startedAt = $this->phaseStartedAt[$phase] ?? hrtime(true);
        $this->reportProgress(AnalysisProgressEvent::phaseCompleted($phase, $status, (hrtime(true) - $startedAt) / 1000000));
    }

    private function reportProgress(AnalysisProgressEvent $event): void
    {
        try {
            $this->progressReporter->report($event);
        } catch (\Throwable) {
            // Progress is observational and cannot alter or mask analysis behavior.
        }
    }

    private function inputFailureReport(UpgradeRequest $request, ProjectStateLoadResult $projectLoad): UpgradeReport
    {
        $failure = $projectLoad->failure();
        if ($failure === null) {
            throw new \LogicException('A failed project-state load must contain its failure.');
        }

        if ($failure instanceof InvalidJsonException) {
            $outcome = ScenarioResult::OUTCOME_INVALID_JSON;
        } elseif ($failure instanceof MissingJsonFileException && basename($failure->path()) === 'composer.lock') {
            $outcome = ScenarioResult::OUTCOME_LOCKFILE_MISSING;
        } else {
            $outcome = ScenarioResult::OUTCOME_WORKSPACE_FAILURE;
        }

        $safeFailureMessage = PathExposurePolicy::redactComposerText(
            $failure->getMessage(),
            $request->projectPath()
        );

        $scenario = new Scenario('project-input', $request->targets(), false);
        $scenarioResult = new ScenarioResult(
            $scenario,
            1,
            '',
            $safeFailureMessage,
            null,
            null,
            ScenarioResult::FAILURE_OPERATIONAL,
            null,
            [],
            0,
            null,
            [],
            $outcome
        );

        return ReportAssembler::inputFailure(
            $request,
            $projectLoad->project(),
            $scenarioResult,
            $safeFailureMessage
        );
    }

    /** @param list<ScenarioResult> $scenarioResults */
    private function bestSuccessfulResult(ComposerLock $baseline, array $scenarioResults): ?ScenarioResult
    {
        /** @var list<array{int, int, int, ScenarioResult}> $candidates */
        $candidates = [];

        foreach ($scenarioResults as $index => $result) {
            if (!$result->scenario()->determinesTargetFeasibility() || !$result->succeeded() || $result->lock() === null) {
                continue;
            }

            $changeCount = count($this->lockDiffBuilder->build($baseline, $result->lock())->packageChanges());
            $strategyRank = $result->scenario()->minimalChanges() ? 1 : ($result->scenario()->withAllDependencies() ? 2 : 0);
            $candidates[] = [$changeCount, $strategyRank, $index, $result];
        }

        if ($candidates === []) {
            return null;
        }

        usort($candidates, static function (array $left, array $right): int {
            return [$left[0], $left[1], $left[2]] <=> [$right[0], $right[1], $right[2]];
        });

        return $candidates[0][3];
    }

    /** @param list<ScenarioResult> $scenarioResults */
    private function composerVersion(array $scenarioResults): ?string
    {
        foreach ($scenarioResults as $result) {
            if ($result->composerVersion() !== null) {
                return $result->composerVersion();
            }
        }

        return null;
    }
}
