<?php

declare(strict_types=1);

namespace PhpUpgradePreflight\TestAdapter;

use PhpUpgradePreflight\Core\Framework\CompatibilityRule;
use PhpUpgradePreflight\Core\Model\CompatibilityFinding;
use PhpUpgradePreflight\Core\Model\Evidence;
use PhpUpgradePreflight\Core\Model\EvidenceLedger;
use PhpUpgradePreflight\Core\Model\ProjectState;
use PhpUpgradePreflight\Core\Model\SourceUsage;
use PhpUpgradePreflight\Core\Model\UpgradeRequest;

final class TestFrameworkSourceRule implements CompatibilityRule
{
    /** @param list<SourceUsage> $sourceUsages */
    public function evaluate(
        ProjectState $project,
        UpgradeRequest $request,
        EvidenceLedger $evidence,
        array $sourceUsages = []
    ): ?CompatibilityFinding {
        $hasFrameworkTarget = false;
        foreach ($request->targets()->packageTargets() as $target) {
            if (strtolower($target->package()) === 'test-vendor/framework') {
                $hasFrameworkTarget = true;
                break;
            }
        }
        if (!$hasFrameworkTarget) {
            return null;
        }

        foreach ($sourceUsages as $usage) {
            if ($usage->file() !== 'modules/Plugin.php') {
                continue;
            }

            $evidenceId = $evidence->add(
                'test-framework',
                Evidence::E3_PROJECT_SOURCE,
                'The test framework adapter inspected its default modules source path.',
                'high',
                ['file' => $usage->file(), 'symbol' => $usage->symbol()]
            )->id();

            return new CompatibilityFinding(
                $this->frameworkName(),
                'medium',
                'Test framework source usage requires review.',
                [$evidenceId]
            );
        }

        return null;
    }

    private function frameworkName(): string
    {
        return 'test-framework';
    }
}
