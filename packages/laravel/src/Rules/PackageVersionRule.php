<?php

declare(strict_types=1);

namespace PhpUpgradePreflight\Laravel\Rules;

use PhpUpgradePreflight\Core\Framework\CompatibilityRule;
use PhpUpgradePreflight\Core\Model\CompatibilityFinding;
use PhpUpgradePreflight\Core\Model\Evidence;
use PhpUpgradePreflight\Core\Model\EvidenceLedger;
use PhpUpgradePreflight\Core\Model\ProjectState;
use PhpUpgradePreflight\Core\Model\UpgradeRequest;

final class PackageVersionRule implements CompatibilityRule
{
    private string $package;
    /** @var array<int, string> */
    private array $compatibleRanges;
    private string $severity;
    /** @var array<int, list<string>> */
    private array $documentation;
    private bool $preferLockedFrameworkRequirements;

    /**
     * @param array<int, string> $compatibleRanges
     * @param array<int, list<string>> $documentation
     */
    public function __construct(
        string $package,
        array $compatibleRanges,
        string $severity = 'medium',
        array $documentation = [],
        bool $preferLockedFrameworkRequirements = false
    ) {
        $this->package = strtolower($package);
        $this->compatibleRanges = $compatibleRanges;
        $this->severity = $severity;
        $this->documentation = $documentation;
        $this->preferLockedFrameworkRequirements = $preferLockedFrameworkRequirements;
    }

    public function evaluate(
        ProjectState $project,
        UpgradeRequest $request,
        EvidenceLedger $evidence,
        array $sourceUsages = []
    ): ?CompatibilityFinding {
        $target = LaravelTarget::fromRequest($request);
        $locked = $project->composerLock()->package($this->package);
        $rootConstraint = $project->composerJson()->rootRequirements()[$this->package] ?? null;

        if ($target === null
            || LaravelSource::fromProject($project)->major() !== 7
            || ($locked === null && $rootConstraint === null)
            || !isset($this->compatibleRanges[$target->major()])) {
            return null;
        }

        $frameworkRequirements = $this->lockedFrameworkRequirements($project);
        if ($this->preferLockedFrameworkRequirements && $frameworkRequirements !== []) {
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
                $target
            );

            return new CompatibilityFinding(
                'laravel',
                $this->severity,
                sprintf(
                    '%s %s declares framework constraints that exclude Laravel %d; upgrade or replace it before runtime validation.',
                    $this->package,
                    $locked === null ? $rootConstraint : $locked->version(),
                    $target->major()
                ),
                [$metadataId]
            );
        }

        $compatibleRange = $this->compatibleRanges[$target->major()];
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
            $target
        );
        $guidanceId = $evidence->add(
            'laravel-package-guidance',
            Evidence::E4_MAINTAINER_DOCUMENTATION,
            sprintf('The encoded Laravel %d guidance maps %s to `%s`.', $target->major(), $this->package, $compatibleRange),
            'medium',
            [
                'package' => $this->package,
                'target_laravel_major' => $target->major(),
                'compatible_package_constraint' => $compatibleRange,
                'sources' => $this->documentation[$target->major()] ?? [],
            ]
        )->id();

        return new CompatibilityFinding(
            'laravel',
            $this->severity,
            sprintf(
                '%s %s is outside the encoded Laravel %d review range `%s`; review its upgrade or replacement.',
                $this->package,
                $locked === null ? $rootConstraint : $locked->version(),
                $target->major(),
                $compatibleRange
            ),
            [$metadataId, $guidanceId]
        );
    }

    /** @return array<string, string> */
    private function lockedFrameworkRequirements(ProjectState $project): array
    {
        foreach (['packages', 'packages-dev'] as $section) {
            $packages = $project->composerLock()->data()[$section] ?? [];
            if (!is_array($packages)) {
                continue;
            }

            foreach ($packages as $package) {
                if (!is_array($package) || strtolower((string) ($package['name'] ?? '')) !== $this->package) {
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
        LaravelTarget $target
    ): string {
        $locked = $project->composerLock()->package($this->package);
        $namespace = preg_replace('/[^a-z0-9_-]+/', '_', str_replace(['/', '-'], '_', $this->package));
        if ($namespace === null) {
            throw new \LogicException('Unable to create package evidence namespace.');
        }

        return $evidence->add(
            'laravel-package-' . $namespace,
            Evidence::E2_PACKAGE_METADATA,
            sprintf('%s is present in Composer metadata.', $this->package),
            'high',
            [
                'package' => $this->package,
                'locked_version' => $locked === null ? null : $locked->version(),
                'root_constraint' => $rootConstraint,
                'framework_requirements' => $frameworkRequirements,
                'target_laravel_major' => $target->major(),
            ]
        )->id();
    }
}
