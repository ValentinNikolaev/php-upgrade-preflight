<?php

declare(strict_types=1);

use PhpUpgradePreflight\Core\Analysis\DefaultUpgradeAnalyzer;
use PhpUpgradePreflight\Core\Model\ComposerExecutionConfiguration;
use PhpUpgradePreflight\Core\Model\UpgradeReport;
use PhpUpgradePreflight\Core\Model\UpgradeRequest;
use PhpUpgradePreflight\Core\Model\UpgradeTarget;

function analyzeCurrentConsumer(): UpgradeReport
{
    $root = getenv('GITHUB_WORKSPACE');
    if (!is_string($root) || $root === '') {
        throw new RuntimeException('GITHUB_WORKSPACE must identify the repository fixtures.');
    }

    return (new DefaultUpgradeAnalyzer())->analyzeUpgrade(new UpgradeRequest(
        $root . '/tests/fixtures/path-repository/project',
        [new UpgradeTarget('fixture/dependency', '^2.0')],
        '8.0.0',
        '8.5.11',
        composerExecution: new ComposerExecutionConfiguration(mode: ComposerExecutionConfiguration::MODE_RESTRICTED)
    ));
}
