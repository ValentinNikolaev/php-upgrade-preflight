<?php

declare(strict_types=1);

namespace PhpUpgradePreflight\Cli;

/** @internal Test-only seam for deterministic native filesystem failures. */
function getcwd(): string|false
{
    if (($GLOBALS['php_upgrade_preflight_cli_getcwd_failure'] ?? false) === true) {
        return false;
    }

    return \getcwd();
}

/** @internal Test-only seam for deterministic native filesystem failures. */
function file_get_contents(string $filename): string|false
{
    if (($GLOBALS['php_upgrade_preflight_cli_unreadable_path'] ?? null) === $filename) {
        return false;
    }

    return \file_get_contents($filename);
}
