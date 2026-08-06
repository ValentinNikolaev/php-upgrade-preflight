<?php

declare(strict_types=1);

namespace PhpUpgradePreflight\Laravel;

use PhpUpgradePreflight\Core\Framework\FrameworkDetection;
use PhpUpgradePreflight\Core\Framework\FrameworkIntegration;
use PhpUpgradePreflight\Core\Framework\PackageFamilyClassifier;
use PhpUpgradePreflight\Core\Model\ProjectState;
use PhpUpgradePreflight\Laravel\Rules\LegacyLaravelPackageRule;

final class LaravelFrameworkIntegration implements FrameworkIntegration, PackageFamilyClassifier
{
    private LaravelPackageFamilyClassifier $packageFamilyClassifier;

    public function __construct(?LaravelPackageFamilyClassifier $packageFamilyClassifier = null)
    {
        $this->packageFamilyClassifier = $packageFamilyClassifier ?? new LaravelPackageFamilyClassifier();
    }

    public function name(): string
    {
        return 'laravel';
    }

    public function detect(ProjectState $project): FrameworkDetection
    {
        $package = $project->composerLock()->package('laravel/framework');
        $rootConstraint = $project->composerJson()->rootRequirements()['laravel/framework'] ?? null;

        return new FrameworkDetection('laravel', $package !== null || $rootConstraint !== null, $package ? $package->version() : $rootConstraint);
    }

    public function rules(): iterable
    {
        yield new LegacyLaravelPackageRule('facade/ignition', 'facade/ignition is tied to older Laravel error handling and should be reviewed for Laravel 8/9 targets.', 'medium');
        yield new LegacyLaravelPackageRule('fideloper/proxy', 'fideloper/proxy is a legacy Laravel proxy package and may be removable in newer Laravel skeletons.', 'medium');
        yield new LegacyLaravelPackageRule('fruitcake/laravel-cors', 'fruitcake/laravel-cors needs review because CORS handling moved into newer Laravel skeletons.', 'medium');
        yield new LegacyLaravelPackageRule('nunomaduro/collision', 'nunomaduro/collision often needs a compatible major version for the target Laravel/PHP pair.', 'medium');
        yield new LegacyLaravelPackageRule('laravel/ui', 'laravel/ui compatibility depends on the target Laravel major and installed frontend scaffolding.', 'low');
        yield new LegacyLaravelPackageRule('orchestra/testbench', 'orchestra/testbench must usually match the target Laravel major.', 'medium');
    }

    public function defaultSourcePaths(ProjectState $project): array
    {
        return ['app', 'bootstrap', 'config', 'database', 'routes', 'tests'];
    }

    public function packageFamilies(string $packageName): array
    {
        return $this->packageFamilyClassifier->packageFamilies($packageName);
    }
}
