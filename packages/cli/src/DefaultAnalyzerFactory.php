<?php

declare(strict_types=1);

namespace PhpUpgradePreflight\Cli;

use PhpUpgradePreflight\Core\Analysis\DefaultUpgradeAnalyzer;
use PhpUpgradePreflight\Core\Contracts\UpgradeAnalyzer;

final class DefaultAnalyzerFactory implements AnalyzerFactory
{
    public function create(array $integrations): UpgradeAnalyzer
    {
        return new DefaultUpgradeAnalyzer($integrations);
    }
}
