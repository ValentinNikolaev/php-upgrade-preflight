<?php

declare(strict_types=1);

namespace PhpUpgradePreflight\Laravel\Catalog;

interface RuleDefinition
{
    public function key(): string;
}
