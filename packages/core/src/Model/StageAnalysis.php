<?php

declare(strict_types=1);

namespace PhpUpgradePreflight\Core\Model;

final class StageAnalysis
{
    public const EXECUTED = 'evaluated';
    public const SKIPPED = 'skipped';

    private FrameworkStageTarget $target;
    private string $executionState;
    private ?string $resolutionStatus;
    /** @var list<StageAttempt> */
    private array $attempts;
    private ?int $selectedAttempt;
    private ?ProjectStateFingerprint $predecessorState;
    private ?ProjectStateFingerprint $inputState;
    private ?ProjectStateFingerprint $outputState;
    /** @var list<PackageChange> */
    private array $packageChanges;
    /** @var list<CompatibilityFinding> */
    private array $sourceFindings;
    /** @var list<SourceImpactFinding> */
    private array $sourceImpact;
    private ?string $stopReason;

    /**
     * @param list<StageAttempt> $attempts
     * @param list<PackageChange> $packageChanges
     * @param list<CompatibilityFinding> $sourceFindings
     * @param list<SourceImpactFinding> $sourceImpact
     */
    public function __construct(
        FrameworkStageTarget $target,
        string $executionState,
        ?string $resolutionStatus,
        array $attempts,
        ?int $selectedAttempt,
        ?ProjectStateFingerprint $predecessorState,
        ?ProjectStateFingerprint $inputState,
        ?ProjectStateFingerprint $outputState,
        array $packageChanges,
        ?string $stopReason = null,
        array $sourceFindings = [],
        array $sourceImpact = []
    ) {
        if (!in_array($executionState, [self::EXECUTED, self::SKIPPED], true)) {
            throw new \InvalidArgumentException(sprintf('Unsupported stage execution state "%s".', $executionState));
        }
        if ($resolutionStatus !== null && !in_array($resolutionStatus, StagedResolution::statuses(), true)) {
            throw new \InvalidArgumentException(sprintf('Unsupported stage resolution status "%s".', $resolutionStatus));
        }
        if ($executionState === self::SKIPPED && ($resolutionStatus !== null || $attempts !== [] || $selectedAttempt !== null)) {
            throw new \InvalidArgumentException('Skipped stages cannot contain a resolution or attempts.');
        }
        foreach ($attempts as $attempt) {
            if (!$attempt instanceof StageAttempt) {
                throw new \InvalidArgumentException('Stage analyses may contain only StageAttempt instances.');
            }
        }
        foreach ($packageChanges as $change) {
            if (!$change instanceof PackageChange) {
                throw new \InvalidArgumentException('Stage package changes must be PackageChange instances.');
            }
        }

        $this->target = $target;
        $this->executionState = $executionState;
        $this->resolutionStatus = $resolutionStatus;
        $this->attempts = array_values($attempts);
        $this->selectedAttempt = $selectedAttempt;
        $this->predecessorState = $predecessorState;
        $this->inputState = $inputState;
        $this->outputState = $outputState;
        $this->packageChanges = array_values($packageChanges);
        $this->sourceFindings = array_values($sourceFindings);
        $this->sourceImpact = array_values($sourceImpact);
        $this->stopReason = $stopReason;
    }

    public function target(): FrameworkStageTarget
    {
        return $this->target;
    }

    public function executionState(): string
    {
        return $this->executionState;
    }

    public function resolutionStatus(): ?string
    {
        return $this->resolutionStatus;
    }

    /** @return list<StageAttempt> */
    public function attempts(): array
    {
        return $this->attempts;
    }

    public function outputState(): ?ProjectStateFingerprint
    {
        return $this->outputState;
    }

    /**
     * @param list<CompatibilityFinding> $sourceFindings
     * @param list<SourceImpactFinding> $sourceImpact
     */
    public function withSourceAssessment(array $sourceFindings, array $sourceImpact): self
    {
        return new self(
            $this->target,
            $this->executionState,
            $this->resolutionStatus,
            $this->attempts,
            $this->selectedAttempt,
            $this->predecessorState,
            $this->inputState,
            $this->outputState,
            $this->packageChanges,
            $this->stopReason,
            $sourceFindings,
            $sourceImpact
        );
    }

    /** @return list<string> */
    public function evidenceReferences(): array
    {
        $references = $this->target->evidence();
        foreach ($this->target->remediationTargets() as $target) {
            $references = array_merge($references, $this->target->remediationEvidence($target->package()));
        }
        foreach ($this->attempts as $attempt) {
            $references = array_merge($references, $attempt->evidenceReferences());
        }

        return array_values(array_unique($references));
    }

    /** @return array<string, mixed> */
    public function toArray(): array
    {
        $risk = $this->stageRisk();
        $findingCount = count($this->sourceFindings) + count($this->sourceImpact);
        $changeCount = count($this->packageChanges);
        $effortBase = $findingCount + $changeCount;

        return [
            'id' => $this->target->id(),
            'framework' => $this->target->framework(),
            'from_major' => $this->target->fromMajor(),
            'to_major' => $this->target->toMajor(),
            'execution_state' => $this->executionState,
            'resolution_status' => $this->resolutionStatus,
            'targets' => $this->target->targets()->toArray(),
            'analysis_php' => $this->target->analysisPhp(),
            'target_evidence' => $this->target->evidence(),
            'predecessor_state' => $this->predecessorState === null ? null : $this->predecessorState->toArray(),
            'input_state' => $this->inputState === null ? null : $this->inputState->toArray(),
            'output_state' => $this->outputState === null ? null : $this->outputState->toArray(),
            'attempts' => array_map(static fn (StageAttempt $attempt): array => $attempt->toArray(), $this->attempts),
            'selected_attempt' => $this->selectedAttempt,
            'package_changes' => array_map(static fn (PackageChange $change): array => $change->toArray(), $this->packageChanges),
            'source_snapshot' => 'original_project',
            'source_findings' => array_map(
                static fn (CompatibilityFinding $finding): array => $finding->toArray(),
                $this->sourceFindings
            ),
            'source_impact' => array_map(
                static fn (SourceImpactFinding $finding): array => $finding->toArray(),
                $this->sourceImpact
            ),
            'risk' => $risk,
            'effort' => [
                'range_hours' => [$effortBase, $effortBase * 2],
                'confidence' => $this->executionState === self::EXECUTED ? 'medium' : 'low',
            ],
            'recommended_actions' => $this->recommendedActions(),
            'stop_reason' => $this->stopReason,
        ];
    }

    /** @return array{level: string, drivers: list<string>} */
    private function stageRisk(): array
    {
        $drivers = [];
        if (in_array($this->resolutionStatus, [StagedResolution::BLOCKED, StagedResolution::UNKNOWN], true)) {
            $drivers[] = 'The stage did not produce a selectable Composer state.';
        }
        foreach ($this->sourceFindings as $finding) {
            if ($finding->severity() === 'high') {
                $drivers[] = $finding->summary();
            }
        }
        if ($drivers !== []) {
            return ['level' => 'high', 'drivers' => array_values(array_unique($drivers))];
        }
        if ($this->packageChanges !== [] || $this->sourceFindings !== [] || $this->sourceImpact !== []) {
            return ['level' => 'medium', 'drivers' => ['The stage changes dependencies or has source review findings.']];
        }

        return ['level' => 'low', 'drivers' => []];
    }

    /** @return list<string> */
    private function recommendedActions(): array
    {
        if ($this->executionState === self::SKIPPED) {
            return ['Do not advance to this stage until the preceding stop condition is resolved and the analysis is rerun.'];
        }

        $actions = [];
        if ($this->resolutionStatus === StagedResolution::BLOCKED) {
            $actions[] = 'Resolve every active blocking registry entry, then rerun the complete stage.';
        } elseif ($this->resolutionStatus === StagedResolution::UNKNOWN) {
            $actions[] = 'Resolve the operational uncertainty, then rerun the stage without inferring feasibility.';
        } elseif ($this->outputState !== null) {
            $actions[] = 'Use only the selected candidate state as evidence for the next stage.';
        }
        foreach ($this->sourceFindings as $finding) {
            $actions[] = $finding->summary();
        }

        return array_values(array_unique($actions));
    }
}
