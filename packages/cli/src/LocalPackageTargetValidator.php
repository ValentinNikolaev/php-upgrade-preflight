<?php

declare(strict_types=1);

namespace PhpUpgradePreflight\Cli;

final class LocalPackageTargetValidator implements PackageTargetValidator
{
    public function validate(string $projectPath, string $package, string $constraint): PackageTargetValidation
    {
        $contents = file_get_contents($projectPath . DIRECTORY_SEPARATOR . 'composer.json');
        if ($contents === false) {
            return new PackageTargetValidation(
                PackageTargetValidation::UNVERIFIED,
                'Package metadata could not be read locally.'
            );
        }

        try {
            $composer = json_decode($contents, true, 512, JSON_THROW_ON_ERROR);
        } catch (\JsonException) {
            return new PackageTargetValidation(
                PackageTargetValidation::UNVERIFIED,
                'Package metadata could not be decoded locally.'
            );
        }

        if (!is_array($composer)) {
            return new PackageTargetValidation(
                PackageTargetValidation::UNVERIFIED,
                'Package metadata could not be decoded locally.'
            );
        }

        foreach (['require', 'require-dev'] as $section) {
            $requirements = $composer[$section] ?? null;
            if (is_array($requirements) && array_key_exists($package, $requirements)) {
                return new PackageTargetValidation(
                    PackageTargetValidation::FOUND,
                    sprintf('Package is a root requirement in composer.json (%s).', $section)
                );
            }
        }

        return new PackageTargetValidation(
            PackageTargetValidation::UNVERIFIED,
            'Package is not a root requirement; configured repository lookup was not performed.'
        );
    }
}
