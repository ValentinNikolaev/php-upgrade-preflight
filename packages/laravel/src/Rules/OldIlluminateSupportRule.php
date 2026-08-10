<?php

declare(strict_types=1);

namespace PhpUpgradePreflight\Laravel\Rules;

use PhpUpgradePreflight\Core\Framework\CompatibilityRule;
use PhpUpgradePreflight\Core\Model\CompatibilityFinding;
use PhpUpgradePreflight\Core\Model\Evidence;
use PhpUpgradePreflight\Core\Model\EvidenceLedger;
use PhpUpgradePreflight\Core\Model\ProjectState;
use PhpUpgradePreflight\Core\Model\UpgradeRequest;

final class OldIlluminateSupportRule implements CompatibilityRule
{
    private const SPECIALIZED_PACKAGES = [
        'laravel/horizon',
        'laravel/passport',
        'laravel/sanctum',
        'laravel/telescope',
    ];

    public function evaluate(
        ProjectState $project,
        UpgradeRequest $request,
        EvidenceLedger $evidence,
        array $sourceUsages = []
    ): ?CompatibilityFinding {
        $target = LaravelTarget::fromRequest($request);
        if ($target === null
            || LaravelSource::fromProject($project)->major() !== 7
            || !in_array($target->major(), [8, 9], true)) {
            return null;
        }

        $references = [];
        $blockingPackages = [];
        $rootConstraint = $project->composerJson()->rootRequirements()['illuminate/support'] ?? null;

        if ($rootConstraint !== null
            && !$target->intersectsRequestedFrameworkRange($rootConstraint)) {
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
                || $target->intersectsRequestedFrameworkRange($constraint)) {
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
