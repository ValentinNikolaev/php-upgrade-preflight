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

    /** @var array<string, float> */
    private array $started = [];
    /** @var array<string, float> */
    private array $seconds = [];

    public function report(AnalysisProgressEvent $event): void
    {
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
}
