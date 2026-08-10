<?php

declare(strict_types=1);

namespace PhpUpgradePreflight\Laravel\Catalog;

final class TargetDefinition
{
    private string $key;
    private int $major;
    private string $phpConstraint;
    /** @var list<string> */
    private array $phpSources;
    private ?string $symfonyConstraint;
    /** @var list<string> */
    private array $symfonySources;
    /** @var array<string, string> */
    private array $symfonyComponentConstraints;

    /**
     * @param list<string> $phpSources
     * @param list<string> $symfonySources
     * @param array<string, string> $symfonyComponentConstraints
     */
    public function __construct(
        string $key,
        int $major,
        string $phpConstraint,
        array $phpSources,
        ?string $symfonyConstraint = null,
        array $symfonySources = [],
        array $symfonyComponentConstraints = []
    ) {
        $this->key = $key;
        $this->major = $major;
        $this->phpConstraint = $phpConstraint;
        $this->phpSources = $phpSources;
        $this->symfonyConstraint = $symfonyConstraint;
        $this->symfonySources = $symfonySources;
        $this->symfonyComponentConstraints = array_change_key_case($symfonyComponentConstraints, CASE_LOWER);
    }

    public function key(): string
    {
        return $this->key;
    }

    public function major(): int
    {
        return $this->major;
    }

    public function phpConstraint(): string
    {
        return $this->phpConstraint;
    }

    /** @return list<string> */
    public function phpSources(): array
    {
        return $this->phpSources;
    }

    public function symfonyConstraint(): ?string
    {
        return $this->symfonyConstraint;
    }

    public function symfonyConstraintFor(string $package): ?string
    {
        return $this->symfonyComponentConstraints[strtolower($package)] ?? $this->symfonyConstraint;
    }

    /** @return array<string, string> */
    public function symfonyComponentConstraints(): array
    {
        return $this->symfonyComponentConstraints;
    }

    /** @return list<string> */
    public function symfonySources(): array
    {
        return $this->symfonySources;
    }
}
