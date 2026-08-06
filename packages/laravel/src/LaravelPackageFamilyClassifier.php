<?php

declare(strict_types=1);

namespace PhpUpgradePreflight\Laravel;

use PhpUpgradePreflight\Core\Framework\PackageFamilyClassifier;

final class LaravelPackageFamilyClassifier implements PackageFamilyClassifier
{
    public const LARAVEL = 'laravel';
    public const ILLUMINATE = 'illuminate';
    public const SYMFONY = 'symfony';

    public function packageFamilies(string $packageName): array
    {
        $packageName = strtolower($packageName);

        if (strpos($packageName, 'laravel/') === 0) {
            return [self::LARAVEL];
        }

        if (strpos($packageName, 'illuminate/') === 0) {
            return [self::ILLUMINATE];
        }

        if (strpos($packageName, 'symfony/') === 0) {
            return [self::SYMFONY];
        }

        return [];
    }
}
