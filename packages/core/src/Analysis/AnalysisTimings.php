<?php

declare(strict_types=1);

namespace PhpUpgradePreflight\Core\Analysis;

/** Optional operation timings; no project paths, source contents or report data. */
final class AnalysisTimings
{
    private const OPERATIONS = [
        'source_capture', 'source_recheck_after_scan', 'input_recheck_final',
        'ownership', 'source_impact', 'stage_assessment', 'risk_effort', 'assembly',
    ];

    /** @var array<string, float> */
    private array $durations = [];
    /** @var array<string, float> */
    private array $started = [];
    private ?float $analysisStarted = null;
    private float $totalMs = 0.0;
    private string $status = 'not-started';

    public function __construct()
    {
        $this->durations = array_fill_keys(self::OPERATIONS, 0.0);
    }

    public function reset(): void
    {
        $this->durations = array_fill_keys(self::OPERATIONS, 0.0);
        $this->started = [];
        $this->totalMs = 0.0;
        $this->status = 'running';
        $this->analysisStarted = (float) hrtime(true);
    }

    public function begin(string $operation): void
    {
        if (!array_key_exists($operation, $this->durations)) {
            throw new \InvalidArgumentException('Unknown analysis timing operation.');
        }
        $this->started[$operation] = (float) hrtime(true);
    }

    public function end(string $operation): void
    {
        if (!isset($this->started[$operation])) {
            return;
        }
        $this->durations[$operation] += ((float) hrtime(true) - $this->started[$operation]) / 1000000;
        unset($this->started[$operation]);
    }

    public function complete(bool $succeeded): void
    {
        foreach (array_keys($this->started) as $operation) {
            $this->end($operation);
        }
        $this->totalMs = $this->analysisStarted === null ? 0.0 : ((float) hrtime(true) - $this->analysisStarted) / 1000000;
        $this->status = $succeeded ? 'completed' : 'failed';
    }

    /** @return array{status: string, total_ms: float, timings_ms: array<string, float>} */
    public function snapshot(): array
    {
        return ['status' => $this->status, 'total_ms' => $this->totalMs, 'timings_ms' => $this->durations];
    }
}
