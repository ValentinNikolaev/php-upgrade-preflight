<?php

declare(strict_types=1);

namespace PhpUpgradePreflight\LegacyTestAdapter;

use PhpUpgradePreflight\Core\Framework\FrameworkDetection;
use PhpUpgradePreflight\Core\Framework\FrameworkIntegration;
use PhpUpgradePreflight\Core\Framework\FrameworkTransitionProvider;
use PhpUpgradePreflight\Core\Model\Evidence;
use PhpUpgradePreflight\Core\Model\EvidenceLedger;
use PhpUpgradePreflight\Core\Model\FrameworkGuidance;
use PhpUpgradePreflight\Core\Model\FrameworkHop;
use PhpUpgradePreflight\Core\Model\ProjectState;
use PhpUpgradePreflight\Core\Model\UpgradeRequest;

/**
 * Deliberately uses only the adapter interfaces available before Core v0.3.
 */
final class LegacyTestFrameworkIntegration implements FrameworkIntegration, FrameworkTransitionProvider
{
    public function name(): string
    {
        return 'legacy-test-framework';
    }

    public function detect(ProjectState $project): FrameworkDetection
    {
        $constraint = $project->composerJson()->rootRequirements()['legacy-vendor/framework'] ?? null;
        $locked = $project->composerLock()->package('legacy-vendor/framework');

        return new FrameworkDetection(
            $this->name(),
            $constraint !== null || $locked !== null,
            $locked === null ? $constraint : $locked->version()
        );
    }

    public function rules(): iterable
    {
        return [];
    }

    public function defaultSourcePaths(ProjectState $project): array
    {
        return ['legacy-modules'];
    }

    public function assessTransition(
        ProjectState $project,
        UpgradeRequest $request,
        EvidenceLedger $evidence
    ): ?FrameworkGuidance {
        if (!$this->hasFrameworkTarget($request)) {
            return null;
        }

        $evidenceId = $evidence->add(
            'legacy-test-framework-transition',
            Evidence::E2_PACKAGE_METADATA,
            'The old-style test adapter selected its version 1 to 2 transition guidance.'
        )->id();

        return new FrameworkGuidance(
            $this->name(),
            1,
            2,
            FrameworkGuidance::SUPPORTED,
            [new FrameworkHop(1, 2, FrameworkHop::SUPPORTED, 'legacy-test-framework-1-to-2', [$evidenceId])],
            [],
            [$evidenceId]
        );
    }

    private function hasFrameworkTarget(UpgradeRequest $request): bool
    {
        foreach ($request->targets()->packageTargets() as $target) {
            if (strtolower($target->package()) === 'legacy-vendor/framework') {
                return true;
            }
        }

        return false;
    }
}
