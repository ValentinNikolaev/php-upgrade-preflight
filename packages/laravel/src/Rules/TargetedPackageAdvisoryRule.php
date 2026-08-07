<?php

declare(strict_types=1);

namespace PhpUpgradePreflight\Laravel\Rules;

use PhpUpgradePreflight\Core\Framework\CompatibilityRule;
use PhpUpgradePreflight\Core\Model\CompatibilityFinding;
use PhpUpgradePreflight\Core\Model\Evidence;
use PhpUpgradePreflight\Core\Model\EvidenceLedger;
use PhpUpgradePreflight\Core\Model\ProjectState;
use PhpUpgradePreflight\Core\Model\UpgradeRequest;

final class TargetedPackageAdvisoryRule implements CompatibilityRule
{
    private string $package;
    /** @var list<int> */
    private array $targetMajors;
    private string $summary;
    private string $severity;
    private string $documentation;

    /** @param list<int> $targetMajors */
    public function __construct(
        string $package,
        array $targetMajors,
        string $summary,
        string $severity,
        string $documentation
    ) {
        $this->package = strtolower($package);
        $this->targetMajors = $targetMajors;
        $this->summary = $summary;
        $this->severity = $severity;
        $this->documentation = $documentation;
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
            || !LaravelTarget::isLaravel7Project($project)
            || !in_array($target->major(), $this->targetMajors, true)
            || ($locked === null && $rootConstraint === null)) {
            return null;
        }

        $namespace = preg_replace('/[^a-z0-9_-]+/', '_', str_replace(['/', '-'], '_', $this->package));
        if ($namespace === null) {
            throw new \LogicException('Unable to create package evidence namespace.');
        }

        $metadataId = $evidence->add(
            'laravel-advisory-' . $namespace,
            Evidence::E2_PACKAGE_METADATA,
            sprintf('%s is present in Composer metadata.', $this->package),
            'high',
            [
                'package' => $this->package,
                'locked_version' => $locked === null ? null : $locked->version(),
                'root_constraint' => $rootConstraint,
                'target_laravel_major' => $target->major(),
            ]
        )->id();
        $guidanceId = $evidence->add(
            'laravel-package-advisory',
            Evidence::E4_MAINTAINER_DOCUMENTATION,
            $this->summary,
            'medium',
            [
                'package' => $this->package,
                'target_laravel_major' => $target->major(),
                'source' => $this->documentation,
            ]
        )->id();

        return new CompatibilityFinding(
            'laravel',
            $this->severity,
            $this->summary,
            [$metadataId, $guidanceId]
        );
    }
}
