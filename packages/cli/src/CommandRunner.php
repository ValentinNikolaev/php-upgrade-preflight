<?php

declare(strict_types=1);

namespace PhpUpgradePreflight\Cli;

interface CommandRunner
{
    /** @param list<string> $argv */
    public function run(array $argv): int;
}
