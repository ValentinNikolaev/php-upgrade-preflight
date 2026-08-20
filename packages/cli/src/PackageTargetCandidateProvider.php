<?php

declare(strict_types=1);

namespace PhpUpgradePreflight\Cli;

/** Optional capability for validators backed by enumerable Composer metadata. */
interface PackageTargetCandidateProvider
{
    public function discover(string $projectPath, string $package): PackageTargetValidation;
}
