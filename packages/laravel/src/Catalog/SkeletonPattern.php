<?php

declare(strict_types=1);

namespace PhpUpgradePreflight\Laravel\Catalog;

final class SkeletonPattern
{
    private string $key;
    private string $file;
    /** @var list<string> */
    private array $usageTypes;
    private ?string $symbol;

    /** @param list<string> $usageTypes */
    public function __construct(string $key, string $file, array $usageTypes, ?string $symbol = null)
    {
        $this->key = $key;
        $this->file = strtolower(str_replace('\\', '/', $file));
        $this->usageTypes = $usageTypes;
        $this->symbol = $symbol === null ? null : strtolower($symbol);
    }

    public function key(): string
    {
        return $this->key;
    }

    public function file(): string
    {
        return $this->file;
    }

    /** @return list<string> */
    public function usageTypes(): array
    {
        return $this->usageTypes;
    }

    public function symbol(): ?string
    {
        return $this->symbol;
    }
}
