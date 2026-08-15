<?php

declare(strict_types=1);

namespace PhpUpgradePreflight\Core\Model;

use PhpUpgradePreflight\Core\Analysis\StagedAnalysisPolicy;

final class StagedResolution
{
    public const FEASIBLE = 'feasible';
    public const FEASIBLE_WITH_CHANGES = 'feasible_with_changes';
    public const BLOCKED = 'blocked';
    public const UNKNOWN = 'unknown';

    public const EVALUATED = 'evaluated';
    public const SKIPPED = 'skipped';

    private string $executionState;
    private string $status;
    private ?string $provider;
    /** @var list<StageAnalysis> */
    private array $stages;
    /** @var list<StageBlockerEntry> */
    private array $blockerRegistry;
    private ?string $stopReason;
    /** @var list<string> */
    private array $evidence;

    /**
     * @param list<StageAnalysis> $stages
     * @param list<StageBlockerEntry> $blockerRegistry
     * @param list<string> $evidence
     */
    public function __construct(
        string $executionState,
        string $status,
        ?string $provider,
        array $stages,
        array $blockerRegistry,
        ?string $stopReason = null,
        array $evidence = []
    ) {
        if (!in_array($executionState, [self::EVALUATED, self::SKIPPED], true)) {
            throw new \InvalidArgumentException(sprintf('Unsupported staged execution state "%s".', $executionState));
        }
        if (!in_array($status, self::statuses(), true)) {
            throw new \InvalidArgumentException(sprintf('Unsupported staged resolution status "%s".', $status));
        }
        foreach ($stages as $stage) {
            if (!$stage instanceof StageAnalysis) {
                throw new \InvalidArgumentException('Staged resolution may contain only StageAnalysis instances.');
            }
        }
        foreach ($blockerRegistry as $entry) {
            if (!$entry instanceof StageBlockerEntry) {
                throw new \InvalidArgumentException('The staged blocker registry may contain only StageBlockerEntry instances.');
            }
        }

        $this->executionState = $executionState;
        $this->status = $status;
        $this->provider = $provider;
        $this->stages = array_values($stages);
        $this->blockerRegistry = array_values($blockerRegistry);
        $this->stopReason = $stopReason;
        $this->evidence = array_values(array_unique($evidence));
    }

    /** @param list<string> $evidence */
    public static function skipped(string $reason, ?string $provider = null, array $evidence = []): self
    {
        return new self(self::SKIPPED, self::UNKNOWN, $provider, [], [], $reason, $evidence);
    }

    public function executionState(): string
    {
        return $this->executionState;
    }

    public function status(): string
    {
        return $this->status;
    }

    /** @return list<StageAnalysis> */
    public function stages(): array
    {
        return $this->stages;
    }

    /** @return list<StageBlockerEntry> */
    public function blockerRegistry(): array
    {
        return $this->blockerRegistry;
    }

    /**
     * @param list<CompatibilityFinding> $findings
     * @param list<SourceImpactFinding> $sourceImpact
     */
    public function withSourceAssessments(array $findings, array $sourceImpact): self
    {
        $stages = [];
        foreach ($this->stages as $stage) {
            $hop = [
                'from_major' => $stage->target()->fromMajor(),
                'to_major' => $stage->target()->toMajor(),
            ];
            $stageFindings = array_values(array_filter(
                $findings,
                static fn (CompatibilityFinding $finding): bool => in_array($hop, $finding->appliesToHops(), true)
            ));
            $findingEvidence = [];
            foreach ($stageFindings as $finding) {
                $findingEvidence = array_merge($findingEvidence, $finding->evidence());
            }
            $stageImpact = array_values(array_filter(
                $sourceImpact,
                static function (SourceImpactFinding $finding) use ($findingEvidence): bool {
                    return array_intersect($finding->evidence(), $findingEvidence) !== [];
                }
            ));
            $stages[] = $stage->withSourceAssessment($stageFindings, $stageImpact);
        }

        return new self(
            $this->executionState,
            $this->status,
            $this->provider,
            $stages,
            $this->blockerRegistry,
            $this->stopReason,
            $this->evidence
        );
    }

    /** @return list<string> */
    public function evidenceReferences(): array
    {
        $references = $this->evidence;
        foreach ($this->stages as $stage) {
            $references = array_merge($references, $stage->evidenceReferences());
        }
        foreach ($this->blockerRegistry as $entry) {
            $references = array_merge($references, $entry->evidence());
        }

        return array_values(array_unique($references));
    }

    /** @return array<string, mixed> */
    public function toArray(): array
    {
        return [
            'execution_state' => $this->executionState,
            'status' => $this->status,
            'provider' => $this->provider,
            'stages' => array_map(static fn (StageAnalysis $stage): array => $stage->toArray(), $this->stages),
            'blocker_registry' => array_map(
                static fn (StageBlockerEntry $entry): array => $entry->toArray(),
                $this->blockerRegistry
            ),
            'stop_reason' => $this->stopReason,
            'budgets' => [
                'max_hops' => StagedAnalysisPolicy::MAX_HOPS,
                'max_attempts_per_stage' => StagedAnalysisPolicy::MAX_ATTEMPTS_PER_STAGE,
                'max_scenarios' => StagedAnalysisPolicy::MAX_SCENARIOS,
                'scenario_timeout_seconds' => StagedAnalysisPolicy::SCENARIO_TIMEOUT_SECONDS,
                'aggregate_timeout_seconds' => StagedAnalysisPolicy::AGGREGATE_TIMEOUT_SECONDS,
                'memory_bytes' => StagedAnalysisPolicy::MEMORY_BUDGET_BYTES,
                'json_report_bytes' => StagedAnalysisPolicy::JSON_REPORT_BUDGET_BYTES,
                'markdown_report_bytes' => StagedAnalysisPolicy::MARKDOWN_REPORT_BUDGET_BYTES,
            ],
            'evidence' => $this->evidence,
        ];
    }

    /** @return list<string> */
    public static function statuses(): array
    {
        return [self::FEASIBLE, self::FEASIBLE_WITH_CHANGES, self::BLOCKED, self::UNKNOWN];
    }
}
