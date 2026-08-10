<?php

declare(strict_types=1);

namespace PhpUpgradePreflight\Laravel\Catalog;

final class PackageRuleDefinition implements RuleDefinition
{
    private string $key;
    /** @var list<PackageConstraintDefinition> */
    private array $guidance;

    /** @param list<PackageConstraintDefinition> $guidance */
    public function __construct(string $key, array $guidance)
    {
        $this->key = $key;
        $this->guidance = $guidance;
    }

    public function key(): string
    {
        return $this->key;
    }

    /** @return list<PackageConstraintDefinition> */
    public function guidance(): array
    {
        return $this->guidance;
    }
}
