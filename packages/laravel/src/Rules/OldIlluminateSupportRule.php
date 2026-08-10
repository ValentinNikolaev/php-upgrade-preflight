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

final class OldIlluminateSupportRule implements CompatibilityRule, HopAwareCompatibilityRule
{
    private const SPECIALIZED_PACKAGES = [
        'laravel/horizon',
        'laravel/passport',
        'laravel/sanctum',
        'laravel/telescope',
    ];

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
        if ($target === null || $sourceMajor === null) {
            return null;
        }

        return $this->evaluateTransition($project, $evidence, $sourceMajor, $target);
    }

    public function evaluateForHop(
        ProjectState $project,
        UpgradeRequest $request,
        EvidenceLedger $evidence,
        FrameworkHop $hop,
        ?string $composerVersion = null,
        array $sourceUsages = []
    ): ?CompatibilityFinding {
        if (LaravelTarget::fromRequest($request) === null) {
            return null;
        }

        $earlierTargets = [];
        $sourceMajor = LaravelSource::fromProject($project)->major();
        if ($sourceMajor !== null && $hop->toMajor() === $hop->fromMajor() + 1) {
            for ($major = $sourceMajor + 1; $major < $hop->toMajor(); ++$major) {
                $earlierTargets[] = LaravelTarget::forMajor($major);
            }
        }

        return $this->evaluateTransition(
            $project,
            $evidence,
            $hop->fromMajor(),
            LaravelTarget::forMajor($hop->toMajor()),
            $earlierTargets
        );
    }

    /** @param list<LaravelTarget> $earlierTargets */
    private function evaluateTransition(
        ProjectState $project,
        EvidenceLedger $evidence,
        int $sourceMajor,
        LaravelTarget $target,
        array $earlierTargets = []
    ): ?CompatibilityFinding {
        if (!$this->definition->appliesTo($sourceMajor, $target->major())) {
            return null;
        }

        $references = [];
        $blockingPackages = [];
        $rootConstraint = $project->composerJson()->rootRequirements()['illuminate/support'] ?? null;

        if ($rootConstraint !== null
            && !$target->intersectsRequestedFrameworkRange($rootConstraint)
            && !$this->excludedByAnyTarget($rootConstraint, $earlierTargets)) {
            $blockingPackages['illuminate/support'] = $rootConstraint;
            $references[] = $evidence->add(
                'old-illuminate-support',
                Evidence::E2_PACKAGE_METADATA,
                'The root illuminate/support constraint excludes the requested Laravel target range.',
                'high',
                [
                    'package' => 'illuminate/support',
                    'constraint' => $rootConstraint,
                    'target_laravel_major' => $target->major(),
                ]
            )->id();
        }

        foreach ($this->lockedPackages($project) as $package) {
            $name = strtolower((string) ($package['name'] ?? ''));
            if ($name === ''
                || $name === 'laravel/framework'
                || str_starts_with($name, 'illuminate/')
                || in_array($name, self::SPECIALIZED_PACKAGES, true)) {
                continue;
            }

            $requirements = $package['require'] ?? [];
            $constraint = is_array($requirements) ? ($requirements['illuminate/support'] ?? null) : null;
            if (!is_string($constraint)
                || $target->intersectsRequestedFrameworkRange($constraint)
                || $this->excludedByAnyTarget($constraint, $earlierTargets)) {
                continue;
            }

            $blockingPackages[$name] = $constraint;
            $references[] = $evidence->add(
                'old-illuminate-consumer',
                Evidence::E2_PACKAGE_METADATA,
                sprintf('%s declares an illuminate/support constraint that excludes the requested Laravel %d range.', $name, $target->major()),
                'high',
                [
                    'package' => $name,
                    'locked_version' => isset($package['version']) ? (string) $package['version'] : null,
                    'illuminate_support_constraint' => $constraint,
                    'target_laravel_major' => $target->major(),
                ]
            )->id();
        }

        if ($references === []) {
            return null;
        }

        ksort($blockingPackages);

        return new CompatibilityFinding(
            'laravel',
            'high',
            sprintf(
                'Update or replace incompatible illuminate/support constraints before targeting Laravel %d: %s.',
                $target->major(),
                implode(', ', array_keys($blockingPackages))
            ),
            $references
        );
    }

    /** @param list<LaravelTarget> $targets */
    private function excludedByAnyTarget(string $constraint, array $targets): bool
    {
        foreach ($targets as $target) {
            if (!$target->intersectsRequestedFrameworkRange($constraint)) {
                return true;
            }
        }

        return false;
    }

    /** @return list<array<string, mixed>> */
    private function lockedPackages(ProjectState $project): array
    {
        $packages = [];
        foreach (['packages', 'packages-dev'] as $section) {
            $sectionPackages = $project->composerLock()->data()[$section] ?? [];
            if (!is_array($sectionPackages)) {
                continue;
            }

            foreach ($sectionPackages as $package) {
                if (is_array($package)) {
                    $packages[] = $package;
                }
            }
        }

        return $packages;
    }
}
