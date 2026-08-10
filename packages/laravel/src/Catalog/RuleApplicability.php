<?php

declare(strict_types=1);

namespace PhpUpgradePreflight\Laravel\Catalog;

final class RuleApplicability
{
    private int $sourceMajor;
    private int $targetMajor;

    public function __construct(int $sourceMajor, int $targetMajor)
    {
        $this->sourceMajor = $sourceMajor;
        $this->targetMajor = $targetMajor;
    }

    public function key(): string
    {
        return $this->sourceMajor . ':' . $this->targetMajor;
    }

    public function sourceMajor(): int
    {
        return $this->sourceMajor;
    }

    public function targetMajor(): int
    {
        return $this->targetMajor;
    }

    public function matches(int $sourceMajor, int $targetMajor): bool
    {
        return $this->sourceMajor === $sourceMajor && $this->targetMajor === $targetMajor;
    }
}
