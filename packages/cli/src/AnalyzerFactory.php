<?php

declare(strict_types=1);

namespace PhpUpgradePreflight\Cli;

use PhpUpgradePreflight\Core\Contracts\UpgradeAnalyzer;
use PhpUpgradePreflight\Core\Framework\FrameworkIntegration;

interface AnalyzerFactory
{
    /** @param list<FrameworkIntegration> $integrations */
    public function create(array $integrations): UpgradeAnalyzer;
}
