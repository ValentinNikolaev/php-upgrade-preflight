<?php

declare(strict_types=1);

namespace PhpUpgradePreflight\Core\Progress;

interface AnalysisProgressReporter
{
    /** Implementations must return normally so observation cannot change analysis behavior. */
    public function report(AnalysisProgressEvent $event): void;
}
