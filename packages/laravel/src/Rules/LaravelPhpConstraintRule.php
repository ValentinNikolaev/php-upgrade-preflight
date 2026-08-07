<?php

declare(strict_types=1);

namespace PhpUpgradePreflight\Laravel\Rules;

use PhpUpgradePreflight\Core\Framework\CompatibilityRule;
use PhpUpgradePreflight\Core\Model\CompatibilityFinding;
use PhpUpgradePreflight\Core\Model\Evidence;
use PhpUpgradePreflight\Core\Model\EvidenceLedger;
use PhpUpgradePreflight\Core\Model\ProjectState;
use PhpUpgradePreflight\Core\Model\UpgradeRequest;

final class LaravelPhpConstraintRule implements CompatibilityRule
{
    public function evaluate(
        ProjectState $project,
        UpgradeRequest $request,
        EvidenceLedger $evidence,
        array $sourceUsages = []
    ): ?CompatibilityFinding {
        $target = LaravelTarget::fromRequest($request);
        if ($target === null || !LaravelTarget::isLaravel7Project($project)) {
            return null;
        }

        $targetPhp = $request->targetPhp();
        $rootPhp = $project->composerJson()->rootRequirements()['php'] ?? null;
        $phpRange = $target->phpRange();

        if ($targetPhp !== null) {
            $laravelCompatible = LaravelTarget::versionSatisfies($targetPhp, $phpRange);
            $rootCompatible = $rootPhp === null || LaravelTarget::versionSatisfies($targetPhp, $rootPhp);
            $compatible = $laravelCompatible && $rootCompatible;
            $observed = $targetPhp;
            $observation = 'target_php';
        } elseif ($rootPhp !== null) {
            $laravelCompatible = LaravelTarget::constraintsIntersect($rootPhp, $phpRange);
            $rootCompatible = true;
            $compatible = $laravelCompatible;
            $observed = $rootPhp;
            $observation = 'root_constraint';
        } else {
            return null;
        }

        if ($compatible) {
            return null;
        }

        $metadataId = $evidence->add(
            'laravel-php-constraint',
            Evidence::E2_PACKAGE_METADATA,
            'The detected PHP target or root constraint does not satisfy the Laravel target PHP range.',
            'high',
            [
                'observation' => $observation,
                'observed_php' => $observed,
                'root_php_constraint' => $rootPhp,
                'required_php' => $phpRange,
                'target_laravel_major' => $target->major(),
                'laravel_range_satisfied' => $laravelCompatible,
                'root_constraint_satisfied' => $rootCompatible,
            ]
        )->id();

        $references = [$metadataId];
        if (!$laravelCompatible) {
            $references[] = $evidence->add(
                'laravel-php-guidance',
                Evidence::E4_MAINTAINER_DOCUMENTATION,
                sprintf('Laravel %d declares the encoded PHP compatibility range `%s`.', $target->major(), $phpRange),
                'medium',
                [
                    'target_laravel_major' => $target->major(),
                    'php_constraint' => $phpRange,
                    'source' => sprintf('https://laravel.com/docs/%d.x/upgrade', $target->major()),
                ]
            )->id();
        }

        if ($laravelCompatible && !$rootCompatible) {
            $summary = sprintf(
                'The root PHP constraint `%s` excludes target PHP `%s`; update it for the Laravel %d upgrade.',
                $rootPhp,
                $targetPhp,
                $target->major()
            );
        } else {
            $summary = sprintf(
                'PHP `%s` does not satisfy Laravel %d\'s required range `%s`; select a compatible target PHP version.',
                $observed,
                $target->major(),
                $phpRange
            );
        }

        return new CompatibilityFinding(
            'laravel',
            'high',
            $summary,
            $references
        );
    }
}
