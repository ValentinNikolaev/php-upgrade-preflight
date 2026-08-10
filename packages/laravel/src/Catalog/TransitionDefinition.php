<?php

declare(strict_types=1);

namespace PhpUpgradePreflight\Laravel\Catalog;

final class TransitionDefinition
{
    public const ADJACENT = 'adjacent';
    public const DIRECT = 'direct';

    private string $key;
    private int $sourceMajor;
    private int $targetMajor;
    private string $kind;
    private ?string $rulePack;
    /** @var list<string> */
    private array $sources;

    /** @param list<string> $sources */
    public function __construct(
        string $key,
        int $sourceMajor,
        int $targetMajor,
        string $kind,
        ?string $rulePack,
        array $sources
    ) {
        $this->key = $key;
        $this->sourceMajor = $sourceMajor;
        $this->targetMajor = $targetMajor;
        $this->kind = $kind;
        $this->rulePack = $rulePack;
        $this->sources = $sources;
    }

    public function key(): string
    {
        return $this->key;
    }

    public function sourceMajor(): int
    {
        return $this->sourceMajor;
    }

    public function targetMajor(): int
    {
        return $this->targetMajor;
    }

    public function kind(): string
    {
        return $this->kind;
    }

    public function rulePack(): ?string
    {
        return $this->rulePack;
    }

    public function isSupported(): bool
    {
        return $this->rulePack !== null;
    }

    /** @return list<string> */
    public function sources(): array
    {
        return $this->sources;
    }
}
