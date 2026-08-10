<?php

declare(strict_types=1);

namespace PhpUpgradePreflight\Laravel\Rules;

use PhpUpgradePreflight\Core\Framework\CompatibilityRule;
use PhpUpgradePreflight\Core\Framework\HopAwareCompatibilityRule;
use PhpUpgradePreflight\Core\Model\CompatibilityFinding;
use PhpUpgradePreflight\Core\Model\Evidence;
use PhpUpgradePreflight\Core\Model\EvidenceLedger;
use PhpUpgradePreflight\Core\Model\FrameworkHop;
use PhpUpgradePreflight\Core\Model\ProjectState;
use PhpUpgradePreflight\Core\Model\UpgradeRequest;
use PhpUpgradePreflight\Laravel\Catalog\BuiltinRuleDefinition;

final class LaravelFrameworkConstraintRule implements CompatibilityRule, HopAwareCompatibilityRule
{
    private BuiltinRuleDefinition $definition;

    public function __construct(BuiltinRuleDefinition $definition)
    {
        $this->definition = $definition;
    }

    public function evaluate(
        ProjectState $project,
        UpgradeRequest $request,
        EvidenceLedger $evidence,
        array $sourceUsages = []
    ): ?CompatibilityFinding {
        $target = LaravelTarget::fromRequest($request);
        $sourceMajor = LaravelSource::fromProject($project)->major();

        return $target === null || $sourceMajor === null
            ? null
            : $this->evaluateTransition($project, $evidence, $sourceMajor, $target);
    }

    public function evaluateForHop(
        ProjectState $project,
        UpgradeRequest $request,
        EvidenceLedger $evidence,
        FrameworkHop $hop,
        ?string $composerVersion = null,
        array $sourceUsages = []
    ): ?CompatibilityFinding {
        $target = LaravelTarget::fromRequest($request);
        if ($target === null || $target->major() !== $hop->toMajor()) {
            return null;
        }

        return $this->evaluateTransition($project, $evidence, $hop->fromMajor(), $target);
    }

    private function evaluateTransition(
        ProjectState $project,
        EvidenceLedger $evidence,
        int $sourceMajor,
        LaravelTarget $target
    ): ?CompatibilityFinding {
        $constraint = $project->composerJson()->rootRequirements()['laravel/framework'] ?? null;
        if (!$this->definition->appliesTo($sourceMajor, $target->major())
            || $constraint === null
            || $target->intersectsRequestedFrameworkRange($constraint)) {
            return null;
        }

        $id = $evidence->add(
            'laravel-framework-constraint',
            Evidence::E2_PACKAGE_METADATA,
            'The root Laravel framework constraint does not include the requested target major.',
            'high',
            [
                'package' => 'laravel/framework',
                'root_constraint' => $constraint,
                'target_constraint' => $target->requestedConstraint(),
                'target_laravel_major' => $target->major(),
            ]
        )->id();

        return new CompatibilityFinding(
            'laravel',
            'high',
            sprintf(
                'Update the root laravel/framework constraint from `%s` to a constraint compatible with Laravel %d.',
                $constraint,
                $target->major()
            ),
            [$id]
        );
    }
}
