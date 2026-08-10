<?php

declare(strict_types=1);

namespace PhpUpgradePreflight\TestAdapter;

use PhpUpgradePreflight\Core\Framework\FrameworkDetection;
use PhpUpgradePreflight\Core\Framework\FrameworkIntegration;
use PhpUpgradePreflight\Core\Framework\FrameworkTransitionProvider;
use PhpUpgradePreflight\Core\Framework\PackageFamilyClassifier;
use PhpUpgradePreflight\Core\Model\Evidence;
use PhpUpgradePreflight\Core\Model\EvidenceLedger;
use PhpUpgradePreflight\Core\Model\FrameworkGuidance;
use PhpUpgradePreflight\Core\Model\FrameworkHop;
use PhpUpgradePreflight\Core\Model\ProjectState;
use PhpUpgradePreflight\Core\Model\UpgradeRequest;

final class TestFrameworkIntegration implements FrameworkIntegration, FrameworkTransitionProvider, PackageFamilyClassifier
{
    public function name(): string
    {
        return 'test-framework';
    }

    public function detect(ProjectState $project): FrameworkDetection
    {
        $constraint = $project->composerJson()->rootRequirements()['test-vendor/framework'] ?? null;
        $locked = $project->composerLock()->package('test-vendor/framework');

        return new FrameworkDetection(
            $this->name(),
            $constraint !== null || $locked !== null,
            $locked === null ? $constraint : $locked->version()
        );
    }

    public function rules(): iterable
    {
        yield new TestFrameworkSourceRule();
    }

    public function defaultSourcePaths(ProjectState $project): array
    {
        return ['modules'];
    }

    public function packageFamilies(string $packageName): array
    {
        return str_starts_with(strtolower($packageName), 'test-vendor/') ? ['test-framework'] : [];
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
            'test-framework-transition',
            Evidence::E2_PACKAGE_METADATA,
            'The test framework adapter selected its version 1 to 2 transition guidance.'
        )->id();
        $hop = new FrameworkHop(1, 2, FrameworkHop::SUPPORTED, 'test-framework-1-to-2', [$evidenceId]);

        return new FrameworkGuidance(
            $this->name(),
            1,
            2,
            FrameworkGuidance::SUPPORTED,
            [$hop],
            [],
            [$evidenceId]
        );
    }

    private function hasFrameworkTarget(UpgradeRequest $request): bool
    {
        foreach ($request->targets()->packageTargets() as $target) {
            if (strtolower($target->package()) === 'test-vendor/framework') {
                return true;
            }
        }

        return false;
    }
}
