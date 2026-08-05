<?php

declare(strict_types=1);

namespace PhpUpgradePreflight\Laravel\Rules;

use PhpUpgradePreflight\Core\Framework\CompatibilityRule;
use PhpUpgradePreflight\Core\Model\CompatibilityFinding;
use PhpUpgradePreflight\Core\Model\Evidence;
use PhpUpgradePreflight\Core\Model\ProjectState;
use PhpUpgradePreflight\Core\Model\UpgradeRequest;

final class LegacyLaravelPackageRule implements CompatibilityRule
{
    private string $package;
    private string $summary;
    private string $severity;

    public function __construct(string $package, string $summary, string $severity)
    {
        $this->package = strtolower($package);
        $this->summary = $summary;
        $this->severity = $severity;
    }

    public function evaluate(ProjectState $project, UpgradeRequest $request, array &$evidence): ?CompatibilityFinding
    {
        $locked = $project->composerLock->package($this->package);
        $constraint = $project->composerJson->rootRequirements()[$this->package] ?? null;

        if ($locked === null && $constraint === null) {
            return null;
        }

        $id = 'package-' . str_replace(['/', '-'], '_', $this->package);
        $evidence[] = new Evidence($id, Evidence::E2_PACKAGE_METADATA, sprintf('%s is present in Composer metadata.', $this->package), 'high', [
            'package' => $this->package,
            'locked_version' => $locked ? $locked->version : null,
            'root_constraint' => $constraint,
        ]);

        return new CompatibilityFinding('laravel', $this->severity, $this->summary, [$id]);
    }
}
