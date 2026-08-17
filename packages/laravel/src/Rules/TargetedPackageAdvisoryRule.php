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
use PhpUpgradePreflight\Laravel\Catalog\PackageAdvisoryDefinition;

final class TargetedPackageAdvisoryRule implements CompatibilityRule, HopAwareCompatibilityRule
{
    private PackageAdvisoryDefinition $definition;

    public function __construct(PackageAdvisoryDefinition $definition)
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
        $requestedTarget = LaravelTarget::fromRequest($request);
        $requestedSource = LaravelSource::fromProject($project)->major();
        if ($requestedTarget !== null
            && $requestedSource !== null
            && $this->definition->applicability()->matches($requestedSource, $requestedTarget->major())) {
            if ($hop->toMajor() !== $requestedTarget->major()) {
                return null;
            }

            return $this->evaluateTransition($project, $evidence, $requestedSource, $requestedTarget);
        }

        $applicability = $this->definition->applicability();
        if ($applicability->targetMajor() !== $hop->toMajor()) {
            return null;
        }

        return $this->evaluateTransition(
            $project,
            $evidence,
            $applicability->sourceMajor(),
            LaravelTarget::forMajor($hop->toMajor())
        );
    }

    private function evaluateTransition(
        ProjectState $project,
        EvidenceLedger $evidence,
        int $sourceMajor,
        LaravelTarget $target
    ): ?CompatibilityFinding {
        $package = $this->definition->package();
        $locked = $project->composerLock()->package($package);
        $rootConstraint = $project->composerJson()->rootRequirements()[$package] ?? null;
        if (!$this->definition->applicability()->matches($sourceMajor, $target->major())
            || ($locked === null && $rootConstraint === null)) {
            return null;
        }

        $summary = $this->definition->summary($target->major());
        $namespace = preg_replace('/[^a-z0-9_-]+/', '_', str_replace(['/', '-'], '_', $package));
        if ($namespace === null) {
            throw new \LogicException('Unable to create package evidence namespace.');
        }

        $metadataId = $evidence->add(
            'laravel-advisory-' . $namespace,
            Evidence::E2_PACKAGE_METADATA,
            sprintf('%s is present in Composer metadata.', $package),
            'high',
            [
                'package' => $package,
                'locked_version' => $locked === null ? null : $locked->version(),
                'root_constraint' => $rootConstraint,
                'target_laravel_major' => $target->major(),
            ]
        )->id();
        $guidanceId = $evidence->add(
            'laravel-package-advisory',
            Evidence::E4_MAINTAINER_DOCUMENTATION,
            $summary,
            'medium',
            [
                'package' => $package,
                'target_laravel_major' => $target->major(),
                'source' => $this->definition->sources()[0],
            ]
        )->id();

        return new CompatibilityFinding(
            'laravel',
            $this->definition->severity(),
            $summary,
            [$metadataId, $guidanceId]
        );
    }
}
