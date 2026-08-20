<?php

declare(strict_types=1);

namespace PhpUpgradePreflight\Cli;

use PhpUpgradePreflight\Core\Analysis\DefaultUpgradeAnalyzer;
use PhpUpgradePreflight\Core\Contracts\UpgradeAnalyzer;
use PhpUpgradePreflight\Core\Progress\AnalysisProgressReporter;
use PhpUpgradePreflight\Core\Progress\NoOpAnalysisProgressReporter;

final class DefaultAnalyzerFactory implements AnalyzerFactory
{
    private AnalysisProgressReporter $progressReporter;

    public function __construct(?AnalysisProgressReporter $progressReporter = null)
    {
        $this->progressReporter = $progressReporter ?? new NoOpAnalysisProgressReporter();
    }

    public function create(array $integrations): UpgradeAnalyzer
    {
        return new DefaultUpgradeAnalyzer($integrations, progressReporter: $this->progressReporter);
    }
}
