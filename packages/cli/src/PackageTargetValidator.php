<?php

declare(strict_types=1);

namespace PhpUpgradePreflight\Cli;

/**
 * Keeps interactive target discovery independent from the analyzer. A richer
 * implementation may consult configured Composer repositories; the default
 * implementation deliberately proves only facts available in composer.json.
 */
interface PackageTargetValidator
{
    public function validate(string $projectPath, string $package, string $constraint): PackageTargetValidation;
}
