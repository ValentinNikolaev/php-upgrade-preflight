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
use PhpUpgradePreflight\Laravel\Catalog\PackageConstraintDefinition;
use PhpUpgradePreflight\Laravel\Catalog\PackageRuleDefinition;

final class PackageVersionRule implements CompatibilityRule, HopAwareCompatibilityRule
{
    private PackageRuleDefinition $definition;

    public function __construct(PackageRuleDefinition $definition)
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
        $guidance = $target === null || $sourceMajor === null
            ? null
            : $this->guidanceFor($sourceMajor, $target->major());
        if ($target === null || $guidance === null) {
            return null;
        }

        return $this->evaluateGuidance($project, $evidence, $target, $guidance);
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
        $directGuidance = $requestedTarget === null || $requestedSource === null
            ? null
            : $this->guidanceFor($requestedSource, $requestedTarget->major());

        if ($directGuidance !== null) {
            if ($hop->toMajor() !== $requestedTarget->major()) {
                return null;
            }

            return $this->evaluateGuidance($project, $evidence, $requestedTarget, $directGuidance);
        }

        $guidance = $this->guidanceFor($hop->fromMajor(), $hop->toMajor())
            ?? $this->guidanceForTarget($hop->toMajor());
        if ($guidance === null) {
            return null;
        }

        return $this->evaluateGuidance($project, $evidence, LaravelTarget::forMajor($hop->toMajor()), $guidance);
    }

    private function evaluateGuidance(
        ProjectState $project,
        EvidenceLedger $evidence,
        LaravelTarget $target,
        PackageConstraintDefinition $guidance
    ): ?CompatibilityFinding {

        $package = $guidance->package();
        $locked = $project->composerLock()->package($package);
        $rootConstraint = $project->composerJson()->rootRequirements()[$package] ?? null;
        if ($locked === null && $rootConstraint === null) {
            return null;
        }

        $frameworkRequirements = $this->lockedFrameworkRequirements($project, $package);
        if ($guidance->preferLockedFrameworkRequirements() && $frameworkRequirements !== []) {
            $incompatibleRequirements = array_filter(
                $frameworkRequirements,
                static fn (string $constraint): bool => !$target->intersectsRequestedFrameworkRange($constraint)
            );

            if ($incompatibleRequirements === []) {
                return null;
            }

            $metadataId = $this->addMetadataEvidence(
                $project,
                $evidence,
                $rootConstraint,
                $frameworkRequirements,
                $target,
                $guidance
            );

            return new CompatibilityFinding(
                'laravel',
                $guidance->severity(),
                sprintf(
                    '%s %s declares framework constraints that exclude Laravel %d; upgrade or replace it before runtime validation.',
                    $package,
                    $locked === null ? $rootConstraint : $locked->version(),
                    $target->major()
                ),
                [$metadataId]
            );
        }

        $compatibleRange = $guidance->compatibleConstraint();
        $compatible = $locked !== null
            ? LaravelTarget::versionSatisfies($locked->version(), $compatibleRange)
            : LaravelTarget::constraintsIntersect((string) $rootConstraint, $compatibleRange);

        if ($compatible) {
            return null;
        }

        $metadataId = $this->addMetadataEvidence(
            $project,
            $evidence,
            $rootConstraint,
            $frameworkRequirements,
            $target,
            $guidance
        );
        $guidanceId = $evidence->add(
            'laravel-package-guidance',
            Evidence::E4_MAINTAINER_DOCUMENTATION,
            sprintf('The encoded Laravel %d guidance maps %s to `%s`.', $target->major(), $package, $compatibleRange),
            'medium',
            [
                'package' => $package,
                'target_laravel_major' => $target->major(),
                'compatible_package_constraint' => $compatibleRange,
                'sources' => $guidance->sources(),
            ]
        )->id();

        return new CompatibilityFinding(
            'laravel',
            $guidance->severity(),
            sprintf(
                '%s %s is outside the encoded Laravel %d review range `%s`; review its upgrade or replacement.',
                $package,
                $locked === null ? $rootConstraint : $locked->version(),
                $target->major(),
                $compatibleRange
            ),
            [$metadataId, $guidanceId]
        );
    }

    /** @return array<string, string> */
    private function lockedFrameworkRequirements(ProjectState $project, string $packageName): array
    {
        foreach (['packages', 'packages-dev'] as $section) {
            $packages = $project->composerLock()->data()[$section] ?? [];
            if (!is_array($packages)) {
                continue;
            }

            foreach ($packages as $package) {
                if (!is_array($package) || strtolower((string) ($package['name'] ?? '')) !== $packageName) {
                    continue;
                }

                $requirements = $package['require'] ?? [];
                if (!is_array($requirements)) {
                    return [];
                }

                $frameworkRequirements = [];
                foreach ($requirements as $name => $constraint) {
                    $name = strtolower((string) $name);
                    if (($name === 'laravel/framework' || str_starts_with($name, 'illuminate/'))
                        && is_string($constraint)) {
                        $frameworkRequirements[$name] = $constraint;
                    }
                }

                ksort($frameworkRequirements);

                return $frameworkRequirements;
            }
        }

        return [];
    }

    /** @param array<string, string> $frameworkRequirements */
    private function addMetadataEvidence(
        ProjectState $project,
        EvidenceLedger $evidence,
        ?string $rootConstraint,
        array $frameworkRequirements,
        LaravelTarget $target,
        PackageConstraintDefinition $guidance
    ): string {
        $package = $guidance->package();
        $locked = $project->composerLock()->package($package);
        $namespace = preg_replace('/[^a-z0-9_-]+/', '_', str_replace(['/', '-'], '_', $package));
        if ($namespace === null) {
            throw new \LogicException('Unable to create package evidence namespace.');
        }

        return $evidence->add(
            'laravel-package-' . $namespace,
            Evidence::E2_PACKAGE_METADATA,
            sprintf('%s is present in Composer metadata.', $package),
            'high',
            [
                'package' => $package,
                'locked_version' => $locked === null ? null : $locked->version(),
                'root_constraint' => $rootConstraint,
                'framework_requirements' => $frameworkRequirements,
                'target_laravel_major' => $target->major(),
            ]
        )->id();
    }

    private function guidanceFor(int $sourceMajor, int $targetMajor): ?PackageConstraintDefinition
    {
        foreach ($this->definition->guidance() as $guidance) {
            if ($guidance->applicability()->matches($sourceMajor, $targetMajor)) {
                return $guidance;
            }
        }

        return null;
    }

    private function guidanceForTarget(int $targetMajor): ?PackageConstraintDefinition
    {
        foreach ($this->definition->guidance() as $guidance) {
            if ($guidance->applicability()->targetMajor() === $targetMajor) {
                return $guidance;
            }
        }

        return null;
    }
}
