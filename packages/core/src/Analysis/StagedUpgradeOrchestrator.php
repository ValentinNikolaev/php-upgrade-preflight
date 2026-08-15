<?php

declare(strict_types=1);

namespace PhpUpgradePreflight\Core\Analysis;

use PhpUpgradePreflight\Core\Composer\ComposerScenarioRunner;
use PhpUpgradePreflight\Core\Framework\FrameworkIntegration;
use PhpUpgradePreflight\Core\Framework\FrameworkStageTargetProvider;
use PhpUpgradePreflight\Core\Model\Blocker;
use PhpUpgradePreflight\Core\Model\Evidence;
use PhpUpgradePreflight\Core\Model\EvidenceLedger;
use PhpUpgradePreflight\Core\Model\FrameworkStageTarget;
use PhpUpgradePreflight\Core\Model\PackageChange;
use PhpUpgradePreflight\Core\Model\ProjectState;
use PhpUpgradePreflight\Core\Model\ProjectStateFingerprint;
use PhpUpgradePreflight\Core\Model\RootConstraintChange;
use PhpUpgradePreflight\Core\Model\Scenario;
use PhpUpgradePreflight\Core\Model\StageAnalysis;
use PhpUpgradePreflight\Core\Model\StageAttempt;
use PhpUpgradePreflight\Core\Model\StageBlockerEntry;
use PhpUpgradePreflight\Core\Model\StagedResolution;
use PhpUpgradePreflight\Core\Model\TargetPlatform;
use PhpUpgradePreflight\Core\Model\UpgradeRequest;
use PhpUpgradePreflight\Core\Model\UpgradeTarget;
use PhpUpgradePreflight\Core\Model\UpgradeTargetSet;

final class StagedUpgradeOrchestrator
{
    /** @var array<string, mixed> */
    private const EXECUTION_POLICY = [
        'mode' => 'compatible',
        'composer_executable' => 'composer',
        'network_policy' => 'inherited',
        'scripts' => false,
        'plugins' => false,
        'installation' => false,
        'timeout_seconds' => StagedAnalysisPolicy::SCENARIO_TIMEOUT_SECONDS,
    ];

    private ComposerScenarioRunner $runner;
    private BlockerGrouper $blockerGrouper;
    private LockDiffBuilder $lockDiffBuilder;

    public function __construct(
        ComposerScenarioRunner $runner,
        ?BlockerGrouper $blockerGrouper = null,
        ?LockDiffBuilder $lockDiffBuilder = null
    ) {
        $this->runner = $runner;
        $this->blockerGrouper = $blockerGrouper ?? new BlockerGrouper();
        $this->lockDiffBuilder = $lockDiffBuilder ?? new LockDiffBuilder();
    }

    /** @param list<FrameworkIntegration> $activeFrameworks */
    public function analyze(
        array $activeFrameworks,
        ProjectState $project,
        UpgradeRequest $request,
        TargetPlatform $platform,
        EvidenceLedger $evidence
    ): StagedResolution {
        $providers = array_values(array_filter(
            $activeFrameworks,
            static fn (FrameworkIntegration $framework): bool => $framework instanceof FrameworkStageTargetProvider
        ));

        if ($providers === []) {
            return StagedResolution::skipped('stage_target_provider_unavailable');
        }
        if (count($providers) > 1) {
            $names = array_map(static fn (FrameworkIntegration $provider): string => $provider->name(), $providers);
            sort($names, SORT_STRING);
            $evidenceId = $evidence->add(
                'stage-provider-conflict',
                Evidence::E2_PACKAGE_METADATA,
                'Staged Composer analysis was skipped because several active adapters supplied stage targets.',
                'high',
                ['providers' => $names]
            )->id();

            return StagedResolution::skipped('multiple_stage_target_providers', null, [$evidenceId]);
        }

        /** @var FrameworkIntegration&FrameworkStageTargetProvider $provider */
        $provider = $providers[0];
        $plan = $provider->planStages($project, $request, $evidence);
        if (!$plan->isAvailable()) {
            return StagedResolution::skipped(
                (string) $plan->unavailableReason(),
                $plan->provider(),
                $plan->evidence()
            );
        }

        $stages = $plan->stages();
        $planEvidence = $plan->evidence();
        foreach ($stages as $stage) {
            $planEvidence = array_merge($planEvidence, $stage->evidence());
            foreach ($stage->remediationTargets() as $target) {
                $planEvidence = array_merge($planEvidence, $stage->remediationEvidence($target->package()));
            }
        }
        $planEvidence = array_values(array_unique($planEvidence));
        $validationFailure = $this->validatePlan($stages);
        if ($validationFailure !== null) {
            $evidenceId = $evidence->add(
                'stage-plan-invalid',
                Evidence::E2_PACKAGE_METADATA,
                'The active adapter returned an invalid staged target chain.',
                'high',
                ['provider' => $plan->provider(), 'reason' => $validationFailure]
            )->id();

            return StagedResolution::skipped(
                'invalid_stage_plan',
                $plan->provider(),
                array_values(array_unique(array_merge($planEvidence, [$evidenceId])))
            );
        }
        if (count($stages) > StagedAnalysisPolicy::MAX_HOPS) {
            $evidenceId = $evidence->add(
                'stage-hop-budget',
                Evidence::E5_HEURISTIC,
                'Staged Composer analysis exceeded the approved hop budget and was not executed.',
                'high',
                ['requested_hops' => count($stages), 'max_hops' => StagedAnalysisPolicy::MAX_HOPS]
            )->id();

            return StagedResolution::skipped(
                'hop_budget_exceeded',
                $plan->provider(),
                array_values(array_unique(array_merge($planEvidence, [$evidenceId])))
            );
        }

        $currentState = $project;
        $stageAnalyses = [];
        /** @var array<string, StageBlockerEntry> $registry */
        $registry = [];
        /** @var list<string> $registryOrder */
        $registryOrder = [];
        $aggregateDurationMs = 0;
        $stopReason = null;
        $overallStatus = StagedResolution::FEASIBLE;
        $hasChanges = false;

        foreach ($stages as $index => $stage) {
            if ($stopReason !== null) {
                $stageAnalyses[] = new StageAnalysis(
                    $stage,
                    StageAnalysis::SKIPPED,
                    null,
                    [],
                    null,
                    null,
                    null,
                    null,
                    [],
                    'previous_stage_' . $overallStatus
                );
                continue;
            }

            $predecessor = ProjectStateFingerprint::fromState(
                $currentState,
                $platform,
                $stage->analysisPhp(),
                self::EXECUTION_POLICY
            );
            $attempts = [];
            $selectedAttempt = null;
            $selectedState = null;
            $selectedFingerprint = null;
            $stageStatus = StagedResolution::UNKNOWN;
            $stageStopReason = null;
            $definitions = $this->attemptDefinitions($stage);

            foreach ($definitions as $attemptIndex => $definition) {
                if ($aggregateDurationMs >= StagedAnalysisPolicy::AGGREGATE_TIMEOUT_SECONDS * 1000) {
                    $stageStopReason = 'aggregate_timeout';
                    $stageStatus = StagedResolution::UNKNOWN;
                    break;
                }

                $attemptNumber = $attemptIndex + 1;
                $scenario = new Scenario(
                    sprintf('%s-attempt-%d-%s', $stage->id(), $attemptNumber, $definition['strategy']),
                    $definition['targets'],
                    $definition['with_all_dependencies']
                );
                $result = $this->runner->run($currentState, $request, $scenario, $platform);
                $aggregateDurationMs += $result->durationMs();

                $attemptEvidence = $evidence->add(
                    'stage-attempt',
                    Evidence::E1_SOLVER,
                    sprintf('Executed Composer attempt %d for stage %s.', $attemptNumber, $stage->id()),
                    'high',
                    [
                        'stage_id' => $stage->id(),
                        'attempt' => $attemptNumber,
                        'strategy' => $definition['strategy'],
                        'scenario' => $scenario->name(),
                        'outcome' => $result->outcome(),
                    ]
                )->id();

                $requestedConstraints = $currentState->composerJson()->rootRequirements();
                foreach ($definition['targets']->packageTargets() as $target) {
                    $requestedConstraints[$target->package()] = $target->constraint();
                }
                $attemptBlockers = $this->blockerGrouper->group(
                    [$result],
                    $evidence,
                    $result->lock() ?? $currentState->composerLock(),
                    $requestedConstraints,
                    $platform
                );
                $blockerIds = $this->observeBlockers(
                    $registry,
                    $registryOrder,
                    $stage,
                    $attemptNumber,
                    $scenario->name(),
                    $attemptBlockers,
                    $attemptEvidence,
                    $result->succeeded() || $result->isSolverFailure()
                );

                $candidate = $result->candidateProjectState();
                $outputFingerprint = $candidate === null
                    ? null
                    : ProjectStateFingerprint::fromState(
                        $candidate,
                        $platform,
                        $stage->analysisPhp(),
                        self::EXECUTION_POLICY
                    );
                $rootChanges = $this->rootConstraintChanges(
                    $currentState,
                    $stage,
                    $definition['targets'],
                    $evidence
                );
                $attempt = new StageAttempt(
                    $attemptNumber,
                    $definition['strategy'],
                    $rootChanges,
                    $result,
                    $predecessor,
                    $outputFingerprint,
                    $blockerIds,
                    [$attemptEvidence]
                );

                if ($result->succeeded()
                    && $candidate !== null
                    && !$this->hasActiveBlockingEntries($registry, $stage->id())) {
                    $attempt = $attempt->withSelected();
                    $selectedAttempt = $attemptNumber;
                    $selectedState = $candidate;
                    $selectedFingerprint = $outputFingerprint;
                    $stageStatus = StagedResolution::FEASIBLE;
                } elseif ($result->isSolverFailure()) {
                    $stageStatus = StagedResolution::BLOCKED;
                } else {
                    $stageStatus = StagedResolution::UNKNOWN;
                    $stageStopReason = $result->outcome() === 'timeout' ? 'timeout' : 'operational_failure';
                }

                $attempts[] = $attempt;
                if ($selectedState !== null || $stageStatus === StagedResolution::UNKNOWN) {
                    break;
                }
            }

            $packageChanges = [];
            if ($selectedState !== null) {
                $packageChanges = $this->lockDiffBuilder
                    ->build($currentState->composerLock(), $selectedState->composerLock())
                    ->packageChanges();
                $stageStatus = $packageChanges === []
                    ? StagedResolution::FEASIBLE
                    : StagedResolution::FEASIBLE_WITH_CHANGES;
                $hasChanges = $hasChanges || $packageChanges !== [];
            } elseif ($stageStopReason === null) {
                $stageStopReason = $stageStatus === StagedResolution::BLOCKED
                    ? 'blocking_registry_not_cleared'
                    : 'no_selectable_candidate';
            }

            $stageAnalyses[] = new StageAnalysis(
                $stage,
                StageAnalysis::EXECUTED,
                $stageStatus,
                $attempts,
                $selectedAttempt,
                $predecessor,
                $predecessor,
                $selectedFingerprint,
                $packageChanges,
                $stageStopReason
            );

            if ($selectedState === null) {
                $overallStatus = $stageStatus;
                $stopReason = $stageStopReason;
                continue;
            }

            $currentState = $selectedState;
            $overallStatus = $hasChanges
                ? StagedResolution::FEASIBLE_WITH_CHANGES
                : StagedResolution::FEASIBLE;
        }

        $orderedRegistry = array_map(
            static fn (string $id): StageBlockerEntry => $registry[$id],
            $registryOrder
        );

        return new StagedResolution(
            StagedResolution::EVALUATED,
            $overallStatus,
            $plan->provider(),
            $stageAnalyses,
            $orderedRegistry,
            $stopReason,
            $plan->evidence()
        );
    }

    /**
     * @return list<array{strategy: string, targets: UpgradeTargetSet, with_all_dependencies: bool}>
     */
    private function attemptDefinitions(FrameworkStageTarget $stage): array
    {
        $baseTargets = $stage->targets()->packageTargets();
        $definitions = [[
            'strategy' => 'target_only',
            'targets' => new UpgradeTargetSet($baseTargets, $stage->analysisPhp()),
            'with_all_dependencies' => false,
        ]];
        $remediations = $stage->remediationTargets();
        if ($remediations === []) {
            $definitions[] = [
                'strategy' => 'locked_package_remediation',
                'targets' => new UpgradeTargetSet($baseTargets, $stage->analysisPhp()),
                'with_all_dependencies' => true,
            ];

            return $definitions;
        }

        $definitions[] = [
            'strategy' => 'root_constraint_remediation',
            'targets' => new UpgradeTargetSet(array_merge($baseTargets, [$remediations[0]]), $stage->analysisPhp()),
            'with_all_dependencies' => false,
        ];
        $definitions[] = [
            'strategy' => 'root_and_locked_package_remediation',
            'targets' => new UpgradeTargetSet(array_merge($baseTargets, $remediations), $stage->analysisPhp()),
            'with_all_dependencies' => true,
        ];

        return array_slice($definitions, 0, StagedAnalysisPolicy::MAX_ATTEMPTS_PER_STAGE);
    }

    /**
     * @param array<string, StageBlockerEntry> $registry
     * @param list<string> $registryOrder
     * @param list<Blocker> $blockers
     * @return list<string>
     */
    private function observeBlockers(
        array &$registry,
        array &$registryOrder,
        FrameworkStageTarget $stage,
        int $attempt,
        string $scenario,
        array $blockers,
        string $attemptEvidence,
        bool $attemptProducedFeasibilityEvidence
    ): array {
        $observed = [];
        $observedSupersessionKeys = [];

        foreach ($blockers as $blocker) {
            $candidate = StageBlockerEntry::detected($stage->id(), $attempt, $scenario, $blocker, [$attemptEvidence]);
            $identity = $candidate->identityKey();
            $observed[$identity] = true;
            $observedSupersessionKeys[$candidate->supersessionKey()] = true;

            if (isset($registry[$identity])) {
                $registry[$identity] = $registry[$identity]->isActive()
                    ? $registry[$identity]->withObservation($attempt, $scenario, $blocker)
                    : $registry[$identity]->withReappearance($attempt, $scenario, $blocker);
                continue;
            }

            foreach ($registry as $existingIdentity => $existing) {
                if ($existingIdentity === $identity
                    || !$existing->isActive()
                    || $existing->supersessionKey() !== $candidate->supersessionKey()) {
                    continue;
                }
                $registry[$existingIdentity] = $existing->withLifecycle(
                    StageBlockerEntry::SUPERSEDED,
                    $attempt,
                    $scenario,
                    [$attemptEvidence]
                );
            }

            $registry[$identity] = $candidate;
            $registryOrder[] = $identity;
        }

        if ($attemptProducedFeasibilityEvidence) {
            foreach ($registry as $identity => $entry) {
                if (!$entry->isActive() || $entry->toArray()['stage_id'] !== $stage->id()) {
                    continue;
                }
                if (isset($observed[$identity])) {
                    continue;
                }
                $lifecycle = isset($observedSupersessionKeys[$entry->supersessionKey()])
                    ? StageBlockerEntry::SUPERSEDED
                    : StageBlockerEntry::RESOLVED;
                $registry[$identity] = $entry->withLifecycle(
                    $lifecycle,
                    $attempt,
                    $scenario,
                    [$attemptEvidence]
                );
            }
        }

        $ids = [];
        foreach ($observed as $identity => $_) {
            $ids[] = $registry[$identity]->id();
        }

        return $ids;
    }

    /** @param array<string, StageBlockerEntry> $registry */
    private function hasActiveBlockingEntries(array $registry, string $stageId): bool
    {
        foreach ($registry as $entry) {
            $canonical = $entry->toArray();
            if ($canonical['stage_id'] === $stageId && $entry->isActive() && $entry->isBlocking()) {
                return true;
            }
        }

        return false;
    }

    /** @return list<RootConstraintChange> */
    private function rootConstraintChanges(
        ProjectState $state,
        FrameworkStageTarget $stage,
        UpgradeTargetSet $targets,
        EvidenceLedger $evidence
    ): array {
        $requirements = $state->composerJson()->rootRequirements();
        $changes = [];
        foreach ($targets->packageTargets() as $target) {
            $from = $requirements[$target->package()] ?? null;
            if ($from === $target->constraint()) {
                continue;
            }
            $references = $stage->remediationEvidence($target->package());
            if ($references === []) {
                $references = $stage->evidence();
            }
            $evidenceId = $evidence->add(
                'stage-root-change',
                Evidence::E2_PACKAGE_METADATA,
                sprintf('Recorded an analyzer-only root constraint change for stage %s.', $stage->id()),
                'high',
                [
                    'stage_id' => $stage->id(),
                    'package' => $target->package(),
                    'from_constraint' => $from,
                    'to_constraint' => $target->constraint(),
                    'supporting_evidence' => $references,
                ]
            )->id();
            $changes[] = new RootConstraintChange(
                $target->package(),
                $from === null ? 'added' : 'updated',
                $from,
                $target->constraint(),
                'Analyzer-only staged Composer simulation; the analyzed project was not changed.',
                array_values(array_unique(array_merge($references, [$evidenceId])))
            );
        }

        return $changes;
    }

    /** @param list<FrameworkStageTarget> $stages */
    private function validatePlan(array $stages): ?string
    {
        $ids = [];
        $previous = null;
        foreach ($stages as $stage) {
            if (isset($ids[$stage->id()])) {
                return 'duplicate_stage_id';
            }
            $ids[$stage->id()] = true;
            if ($previous !== null
                && ($previous->framework() !== $stage->framework()
                    || $previous->toMajor() !== $stage->fromMajor())) {
                return 'non_contiguous_stage_chain';
            }
            $previous = $stage;
        }

        return null;
    }
}
