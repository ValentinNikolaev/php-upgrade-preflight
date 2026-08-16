<?php

declare(strict_types=1);

namespace PhpUpgradePreflight\TestAdapter;

use PhpUpgradePreflight\Core\Framework\FrameworkDetection;
use PhpUpgradePreflight\Core\Framework\FrameworkIntegration;
use PhpUpgradePreflight\Core\Framework\FrameworkStageTargetProvider;
use PhpUpgradePreflight\Core\Framework\FrameworkTransitionProvider;
use PhpUpgradePreflight\Core\Framework\PackageFamilyClassifier;
use PhpUpgradePreflight\Core\Model\Evidence;
use PhpUpgradePreflight\Core\Model\EvidenceLedger;
use PhpUpgradePreflight\Core\Model\FrameworkGuidance;
use PhpUpgradePreflight\Core\Model\FrameworkHop;
use PhpUpgradePreflight\Core\Model\FrameworkStagePlan;
use PhpUpgradePreflight\Core\Model\FrameworkStageTarget;
use PhpUpgradePreflight\Core\Model\ProjectState;
use PhpUpgradePreflight\Core\Model\UpgradeRequest;
use PhpUpgradePreflight\Core\Model\UpgradeTarget;
use PhpUpgradePreflight\Core\Model\UpgradeTargetSet;

final class TestFrameworkIntegration implements FrameworkIntegration, FrameworkTransitionProvider, FrameworkStageTargetProvider, PackageFamilyClassifier
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

    public function planStages(
        ProjectState $project,
        UpgradeRequest $request,
        EvidenceLedger $evidence
    ): FrameworkStagePlan {
        if (!$this->hasFrameworkTarget($request)) {
            return $this->unavailablePlan(
                FrameworkStagePlan::REASON_MISSING_TARGET,
                'The test framework request does not contain its framework package target.',
                $evidence
            );
        }
        if ($this->sourceMajor($project) !== 1 || $this->frameworkTargetConstraint($request) !== '^2.0') {
            return $this->unavailablePlan(
                FrameworkStagePlan::REASON_UNSUPPORTED_TRANSITION,
                'The test adapter stages only its version 1 to 2 transition.',
                $evidence
            );
        }

        $analysisPhp = $this->selectAnalysisPhp($request);
        if ($analysisPhp === null) {
            return $this->unavailablePlan(
                FrameworkStagePlan::REASON_ANALYSIS_PHP_UNAVAILABLE,
                'The test framework stage requires a compatible exact PHP value from the request.',
                $evidence
            );
        }

        $stageId = 'test-framework-1-to-2';
        $evidenceId = $evidence->add(
            'test-framework-stage-target',
            Evidence::E2_PACKAGE_METADATA,
            'The test framework adapter supplies its exact version 1 to 2 stage target.',
            'high',
            [
                'stage_id' => $stageId,
                'package' => 'test-vendor/framework',
                'constraint' => '^2.0',
                'analysis_php' => $analysisPhp['version'],
                'minimum_php_constraint' => '^8.0',
                'analysis_php_provenance' => $analysisPhp['provenance'],
            ]
        )->id();
        $stage = new FrameworkStageTarget(
            $stageId,
            $this->name(),
            1,
            2,
            new UpgradeTargetSet(
                [new UpgradeTarget('test-vendor/framework', '^2.0')],
                $analysisPhp['version']
            ),
            $analysisPhp['version'],
            [],
            [],
            [$evidenceId]
        );

        return new FrameworkStagePlan($this->name(), [$stage], null, [$evidenceId]);
    }

    /** @return ?array{version: string, provenance: string} */
    private function selectAnalysisPhp(UpgradeRequest $request): ?array
    {
        $candidates = [
            'final_target_php_exact_value_checked_against_adapter_constraint' => $request->targetPhp(),
            'current_php_exact_value_checked_against_adapter_constraint' => $request->fromPhp(),
        ];
        $seen = [];
        foreach ($candidates as $provenance => $candidate) {
            if ($candidate === null) {
                continue;
            }
            $version = (new UpgradeTargetSet([], $candidate))->targetPhp();
            if ($version === null || isset($seen[$version])) {
                continue;
            }
            $seen[$version] = true;
            if (explode('.', $version)[0] === '8') {
                return ['version' => $version, 'provenance' => $provenance];
            }
        }

        return null;
    }

    private function hasFrameworkTarget(UpgradeRequest $request): bool
    {
        return $this->frameworkTargetConstraint($request) !== null;
    }

    private function frameworkTargetConstraint(UpgradeRequest $request): ?string
    {
        foreach ($request->targets()->packageTargets() as $target) {
            if (strtolower($target->package()) === 'test-vendor/framework') {
                return $target->constraint();
            }
        }

        return null;
    }

    private function sourceMajor(ProjectState $project): ?int
    {
        $locked = $project->composerLock()->package('test-vendor/framework');
        $version = $locked === null
            ? ($project->composerJson()->rootRequirements()['test-vendor/framework'] ?? null)
            : $locked->version();
        if (!is_string($version) || preg_match('/(?:^|\D)(\d+)(?:\D|$)/', $version, $matches) !== 1) {
            return null;
        }

        return (int) $matches[1];
    }

    private function unavailablePlan(
        string $reason,
        string $summary,
        EvidenceLedger $evidence
    ): FrameworkStagePlan {
        $evidenceId = $evidence->add(
            'test-framework-stage-plan-unavailable',
            Evidence::E2_PACKAGE_METADATA,
            $summary,
            'high',
            ['reason' => $reason]
        )->id();

        return new FrameworkStagePlan($this->name(), [], $reason, [$evidenceId]);
    }
}
