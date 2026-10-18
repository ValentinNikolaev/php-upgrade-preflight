<?php

declare(strict_types=1);

namespace PhpUpgradePreflight\Core\Progress;

use PhpUpgradePreflight\Core\Model\Scenario;
use PhpUpgradePreflight\Core\Model\ScenarioResult;
use PhpUpgradePreflight\Core\Model\UpgradeReport;

final class AnalysisProgressEvent
{
    public const ANALYSIS_STARTED = 'analysis-started';
    public const PHASE_STARTED = 'phase-started';
    public const PHASE_COMPLETED = 'phase-completed';
    public const SCENARIO_STARTED = 'scenario-started';
    public const SCENARIO_COMPLETED = 'scenario-completed';
    public const DIRECT_FINDING = 'direct-finding';
    public const ANALYSIS_COMPLETED = 'analysis-completed';
    public const ANALYSIS_FAILED = 'analysis-failed';

    public const STATUS_SUCCEEDED = 'succeeded';
    public const STATUS_FAILED = 'failed';

    private string $type;
    private ?string $phase;
    private ?string $scenario;
    private ?string $status;
    private ?string $outcome;
    private ?float $durationMs;

    private function __construct(
        string $type,
        ?string $phase = null,
        ?string $scenario = null,
        ?string $status = null,
        ?string $outcome = null,
        ?float $durationMs = null,
        private ?DirectFinding $finding = null
    ) {
        $this->type = $type;
        $this->phase = $phase;
        $this->scenario = $scenario;
        $this->status = $status;
        $this->outcome = $outcome;
        $this->durationMs = $durationMs;
    }

    public static function analysisStarted(): self
    {
        return new self(self::ANALYSIS_STARTED);
    }

    public static function directFinding(DirectFinding $finding): self
    {
        return new self(self::DIRECT_FINDING, AnalysisPhase::COMPOSER_FEASIBILITY, finding: $finding);
    }

    public function finding(): ?DirectFinding
    {
        return $this->finding;
    }

    public static function phaseStarted(string $phase): self
    {
        AnalysisPhase::assertKnown($phase);

        return new self(self::PHASE_STARTED, $phase);
    }

    public static function phaseCompleted(string $phase, string $status = self::STATUS_SUCCEEDED, ?float $durationMs = null): self
    {
        AnalysisPhase::assertKnown($phase);
        self::assertStatus($status);

        return new self(self::PHASE_COMPLETED, $phase, null, $status, null, $durationMs);
    }

    public static function scenarioStarted(Scenario $scenario): self
    {
        return new self(self::SCENARIO_STARTED, AnalysisPhase::COMPOSER_FEASIBILITY, $scenario->name());
    }

    public static function scenarioCompleted(ScenarioResult $result): self
    {
        return new self(
            self::SCENARIO_COMPLETED,
            AnalysisPhase::COMPOSER_FEASIBILITY,
            $result->scenario()->name(),
            $result->succeeded() ? self::STATUS_SUCCEEDED : self::STATUS_FAILED,
            $result->outcome(),
            (float) $result->durationMs()
        );
    }

    public static function analysisCompleted(UpgradeReport $report, ?float $durationMs = null): self
    {
        return new self(
            self::ANALYSIS_COMPLETED,
            null,
            null,
            self::STATUS_SUCCEEDED,
            $report->resolutionStatus(),
            $durationMs
        );
    }

    public static function analysisFailed(?float $durationMs = null): self
    {
        return new self(self::ANALYSIS_FAILED, null, null, self::STATUS_FAILED, null, $durationMs);
    }

    public function type(): string
    {
        return $this->type;
    }

    public function phase(): ?string
    {
        return $this->phase;
    }

    public function scenario(): ?string
    {
        return $this->scenario;
    }

    public function status(): ?string
    {
        return $this->status;
    }

    public function outcome(): ?string
    {
        return $this->outcome;
    }

    /** Scenario duration includes workspace, diagnostics and cleanup, not just Composer. */
    public function durationMs(): ?float
    {
        return $this->durationMs;
    }

    private static function assertStatus(string $status): void
    {
        if (!in_array($status, [self::STATUS_SUCCEEDED, self::STATUS_FAILED], true)) {
            throw new \InvalidArgumentException(sprintf('Unknown analysis progress status "%s".', $status));
        }
    }
}
