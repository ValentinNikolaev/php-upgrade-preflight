<?php

declare(strict_types=1);

use PhpUpgradePreflight\Core\Progress\AnalysisProgressEvent;
use PhpUpgradePreflight\Core\Progress\AnalysisProgressReporter;

/** Measures existing phase boundaries without changing report semantics. */
final class OptimizationProbe implements AnalysisProgressReporter
{
    /** @return ReflectionClass<object>|null Optional API available only in the candidate revision. */
    public static function optionalClass(string $class): ?ReflectionClass
    {
        return class_exists($class) ? new ReflectionClass($class) : null;
    }

    /** @return array<string, mixed> Private numeric-only instrumentation; never canonical report data. */
    public static function sourceProfileSnapshot(object $profile): array
    {
        $data = (new ReflectionObject($profile))->getMethod('snapshot')->invoke($profile);
        if (!is_array($data) || count($data) !== 3 || array_diff(array_keys($data), ['total_ms', 'timings_ms', 'counters']) !== []
            || !is_array($data['timings_ms']) || !is_array($data['counters'])) {
            throw new RuntimeException('Invalid source profile shape.');
        }
        foreach ([$data['total_ms'], ...array_values($data['timings_ms'])] as $value) {
            if ((!is_float($value) && !is_int($value)) || !is_finite((float) $value) || $value < 0) {
                throw new RuntimeException('Invalid source profile duration.');
            }
        }
        foreach ($data['counters'] as $key => $value) {
            if (!is_string($key) || preg_match('/^[a-z_]{1,64}$/D', $key) !== 1 || !is_int($value) || $value < 0) {
                throw new RuntimeException('Invalid source profile counter.');
            }
        }
        if (count($data['timings_ms']) !== 7 || array_diff(array_keys($data['timings_ms']), ['discovery', 'read', 'hash', 'cache_get', 'parse', 'cache_put', 'evidence']) !== []
            || count($data['counters']) < 1 || count($data['counters']) > 64) {
            throw new RuntimeException('Unsupported source profile fields.');
        }

        return $data;
    }

    /** @return array<string, mixed> Optional completed operation scopes, without report or source text. */
    public static function analysisProfileSnapshot(object $profile): array
    {
        $data = (new ReflectionObject($profile))->getMethod('snapshot')->invoke($profile);
        $operations = ['source_capture', 'source_recheck_after_scan', 'input_recheck_final',
            'ownership', 'source_impact', 'stage_assessment', 'risk_effort', 'assembly'];
        if (!is_array($data) || count($data) !== 3 || array_diff(array_keys($data), ['status', 'total_ms', 'timings_ms']) !== []
            || $data['status'] !== 'completed' || !is_array($data['timings_ms'])
            || count($data['timings_ms']) !== count($operations) || array_diff(array_keys($data['timings_ms']), $operations) !== []) {
            throw new RuntimeException('Invalid analysis profile shape.');
        }
        foreach ([$data['total_ms'], ...array_values($data['timings_ms'])] as $value) {
            if ((!is_float($value) && !is_int($value)) || !is_finite((float) $value) || $value < 0) {
                throw new RuntimeException('Invalid analysis profile duration.');
            }
        }

        return $data;
    }

    /** @var array<string, float> */
    private array $started = [];
    /** @var array<string, float> */
    private array $seconds = [];
    private ?float $analysisStarted = null;
    private ?float $firstDirectFinding = null;

    public function startAnalysis(float $started): void
    {
        $this->analysisStarted = $started;
        $this->firstDirectFinding = null;
    }

    public function report(AnalysisProgressEvent $event): void
    {
        if ($this->analysisStarted !== null && $this->firstDirectFinding === null
            && defined(AnalysisProgressEvent::class . '::DIRECT_FINDING')
            && $event->type() === constant(AnalysisProgressEvent::class . '::DIRECT_FINDING')) {
            $this->firstDirectFinding = ((float) hrtime(true) - $this->analysisStarted) / 1e9;
        }
        $phase = $event->phase();
        if ($phase === null) {
            return;
        }
        if ($event->type() === AnalysisProgressEvent::PHASE_STARTED) {
            $this->started[$phase] = (float) hrtime(true);
        } elseif ($event->type() === AnalysisProgressEvent::PHASE_COMPLETED && isset($this->started[$phase])) {
            $this->seconds[$phase] = ($this->seconds[$phase] ?? 0.0)
                + ((float) hrtime(true) - $this->started[$phase]) / 1e9;
            unset($this->started[$phase]);
        }
    }

    /** @return array<string, float> Completed phase scopes; uninstrumented work is not attributed. */
    public function seconds(): array
    {
        return $this->seconds;
    }

    /** Time to an actually emitted finding; null for unsupported or absent events. */
    public function firstDirectFindingSeconds(): ?float
    {
        return $this->firstDirectFinding;
    }
}
