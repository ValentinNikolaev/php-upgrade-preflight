<?php

declare(strict_types=1);

namespace PhpUpgradePreflight\Laravel\Catalog;

final class PackageConstraintDefinition
{
    private string $key;
    private string $package;
    private RuleApplicability $applicability;
    private string $compatibleConstraint;
    private string $severity;
    /** @var list<string> */
    private array $sources;
    private bool $preferLockedFrameworkRequirements;

    /** @param list<string> $sources */
    public function __construct(
        string $key,
        string $package,
        RuleApplicability $applicability,
        string $compatibleConstraint,
        string $severity,
        array $sources,
        bool $preferLockedFrameworkRequirements = false
    ) {
        $this->key = $key;
        $this->package = strtolower($package);
        $this->applicability = $applicability;
        $this->compatibleConstraint = $compatibleConstraint;
        $this->severity = $severity;
        $this->sources = $sources;
        $this->preferLockedFrameworkRequirements = $preferLockedFrameworkRequirements;
    }

    public function key(): string
    {
        return $this->key;
    }

    public function package(): string
    {
        return $this->package;
    }

    public function applicability(): RuleApplicability
    {
        return $this->applicability;
    }

    public function compatibleConstraint(): string
    {
        return $this->compatibleConstraint;
    }

    public function severity(): string
    {
        return $this->severity;
    }

    /** @return list<string> */
    public function sources(): array
    {
        return $this->sources;
    }

    public function preferLockedFrameworkRequirements(): bool
    {
        return $this->preferLockedFrameworkRequirements;
    }
}
