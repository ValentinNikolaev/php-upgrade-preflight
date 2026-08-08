<?php

declare(strict_types=1);

namespace PhpUpgradePreflight\Laravel;

use PhpUpgradePreflight\Core\Framework\FrameworkDetection;
use PhpUpgradePreflight\Core\Framework\FrameworkIntegration;
use PhpUpgradePreflight\Core\Framework\FrameworkTransitionProvider;
use PhpUpgradePreflight\Core\Framework\PackageFamilyClassifier;
use PhpUpgradePreflight\Core\Model\Evidence;
use PhpUpgradePreflight\Core\Model\EvidenceLedger;
use PhpUpgradePreflight\Core\Model\FrameworkGuidance;
use PhpUpgradePreflight\Core\Model\FrameworkHop;
use PhpUpgradePreflight\Core\Model\ProjectState;
use PhpUpgradePreflight\Core\Model\UpgradeRequest;
use PhpUpgradePreflight\Laravel\Rules\LaravelFrameworkConstraintRule;
use PhpUpgradePreflight\Laravel\Rules\LaravelPhpConstraintRule;
use PhpUpgradePreflight\Laravel\Rules\LaravelSkeletonRule;
use PhpUpgradePreflight\Laravel\Rules\LaravelTarget;
use PhpUpgradePreflight\Laravel\Rules\OldIlluminateSupportRule;
use PhpUpgradePreflight\Laravel\Rules\PackageVersionRule;
use PhpUpgradePreflight\Laravel\Rules\SymfonyComponentConstraintRule;
use PhpUpgradePreflight\Laravel\Rules\TargetedPackageAdvisoryRule;

final class LaravelFrameworkIntegration implements FrameworkIntegration, FrameworkTransitionProvider, PackageFamilyClassifier
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
        $rootRequirements = $project->composerJson()->rootRequirements();
        $lockedFramework = $project->composerLock()->package('laravel/framework');
        $frameworkConstraint = $rootRequirements['laravel/framework'] ?? null;

        if ($lockedFramework !== null || $frameworkConstraint !== null) {
            return new FrameworkDetection(
                'laravel',
                true,
                $lockedFramework === null ? $frameworkConstraint : $lockedFramework->version()
            );
        }

        $illuminateConstraints = [];
        foreach ($rootRequirements as $package => $constraint) {
            if (str_starts_with($package, 'illuminate/')) {
                $illuminateConstraints[$package] = $constraint;
            }
        }

        if ($illuminateConstraints === []) {
            return new FrameworkDetection('laravel', false);
        }

        ksort($illuminateConstraints);
        $versions = [];
        foreach ($illuminateConstraints as $package => $constraint) {
            $locked = $project->composerLock()->package($package);
            $versions[] = $locked === null ? $constraint : $locked->version();
        }

        $versions = array_values(array_unique($versions));

        return new FrameworkDetection('laravel', true, count($versions) === 1 ? $versions[0] : null);
    }

    public function rules(): iterable
    {
        $laravel8Upgrade = 'https://laravel.com/docs/8.x/upgrade';
        $laravel9Upgrade = 'https://laravel.com/docs/9.x/upgrade';
        $laravel8Skeleton = 'https://github.com/laravel/laravel/blob/8.x/composer.json';
        $laravel9Skeleton = 'https://github.com/laravel/laravel/blob/9.x/composer.json';

        yield new LaravelFrameworkConstraintRule();
        yield new LaravelPhpConstraintRule();
        yield new PackageVersionRule('laravel/passport', [8 => '^10.0', 9 => '^10.0|^11.0'], 'high', [
            8 => [$laravel8Upgrade],
            9 => [
                'https://github.com/laravel/passport/blob/10.x/composer.json',
                'https://github.com/laravel/passport/blob/11.x/composer.json',
            ],
        ], true);
        yield new PackageVersionRule('laravel/sanctum', [8 => '^2.0', 9 => '^2.0|^3.0'], 'medium', [
            8 => ['https://github.com/laravel/sanctum/blob/2.x/composer.json'],
            9 => [
                'https://github.com/laravel/sanctum/blob/2.x/composer.json',
                'https://github.com/laravel/sanctum/blob/3.x/composer.json',
            ],
        ], true);
        yield new PackageVersionRule('laravel/horizon', [8 => '^5.0', 9 => '^5.0'], 'high', [
            8 => [$laravel8Upgrade],
            9 => ['https://github.com/laravel/horizon/blob/5.x/composer.json'],
        ], true);
        yield new PackageVersionRule('laravel/telescope', [8 => '^4.0', 9 => '^4.0'], 'medium', [
            8 => ['https://github.com/laravel/telescope/blob/4.x/composer.json'],
            9 => ['https://github.com/laravel/telescope/blob/4.x/composer.json'],
        ], true);
        yield new PackageVersionRule('phpunit/phpunit', [8 => '^9.0', 9 => '^9.5.10'], 'medium', [
            8 => [$laravel8Upgrade, $laravel8Skeleton],
            9 => [$laravel9Skeleton],
        ]);
        yield new PackageVersionRule('mockery/mockery', [8 => '^1.4', 9 => '^1.4'], 'low', [
            8 => [$laravel8Skeleton],
            9 => [$laravel9Skeleton],
        ]);
        yield new SymfonyComponentConstraintRule();
        yield new OldIlluminateSupportRule();
        yield new PackageVersionRule('facade/ignition', [8 => '>=2.3.6 <3.0'], 'medium', [8 => [$laravel8Upgrade]]);
        yield new TargetedPackageAdvisoryRule(
            'facade/ignition',
            [9],
            'Replace facade/ignition with spatie/laravel-ignition for the Laravel 9 target.',
            'high',
            $laravel9Upgrade
        );
        yield new TargetedPackageAdvisoryRule(
            'fideloper/proxy',
            [9],
            'Remove fideloper/proxy and review the trusted proxy middleware for the Laravel 9 target.',
            'medium',
            $laravel9Upgrade
        );
        yield new PackageVersionRule('fruitcake/laravel-cors', [8 => '^2.0'], 'medium', [8 => [$laravel8Skeleton]]);
        yield new TargetedPackageAdvisoryRule(
            'fruitcake/laravel-cors',
            [9],
            'Review removal of fruitcake/laravel-cors because Laravel 9 integrates CORS middleware through the framework.',
            'medium',
            $laravel9Upgrade
        );
        yield new PackageVersionRule('nunomaduro/collision', [8 => '^5.0', 9 => '^6.1'], 'medium', [
            8 => [$laravel8Upgrade],
            9 => [$laravel9Upgrade],
        ]);
        yield new PackageVersionRule('laravel/ui', [8 => '^3.0', 9 => '^4.0'], 'low', [
            8 => ['https://github.com/laravel/ui/blob/3.x/composer.json'],
            9 => ['https://github.com/laravel/ui/blob/4.x/composer.json'],
        ]);
        yield new PackageVersionRule('orchestra/testbench', [8 => '^6.0', 9 => '^7.0'], 'medium', [
            8 => ['https://github.com/orchestral/testbench/blob/6.x/composer.json'],
            9 => ['https://github.com/orchestral/testbench/blob/7.x/composer.json'],
        ]);
        yield new LaravelSkeletonRule();
    }

    public function assessTransition(
        ProjectState $project,
        UpgradeRequest $request,
        EvidenceLedger $evidence
    ): ?FrameworkGuidance {
        if (!$this->hasLaravelTarget($request)) {
            return null;
        }

        $sourceMajor = LaravelTarget::isLaravel7Project($project) ? 7 : null;
        $target = LaravelTarget::fromRequest($request);
        $targetMajor = $target === null ? null : $target->major();

        if ($sourceMajor === null || $targetMajor === null) {
            $evidenceId = $evidence->add(
                'laravel-transition',
                Evidence::E2_PACKAGE_METADATA,
                'Laravel transition coverage could not be selected because a source or target major was ambiguous or unsupported.',
                'high',
                [
                    'source_major' => $sourceMajor,
                    'target_major' => $targetMajor,
                    'root_requirements' => $project->composerJson()->rootRequirements(),
                ]
            )->id();

            return new FrameworkGuidance(
                'laravel',
                $sourceMajor,
                $targetMajor,
                FrameworkGuidance::UNSUPPORTED,
                [],
                [sprintf(
                    'Laravel framework guidance is unsupported because the source and target do not each resolve to one implemented major (%s).',
                    $evidenceId
                )],
                [$evidenceId]
            );
        }

        $rulePack = sprintf('laravel-%d-to-%d%s', $sourceMajor, $targetMajor, $targetMajor === 9 ? '-direct' : '');
        $source = sprintf('https://laravel.com/docs/%d.x/upgrade', $targetMajor);
        $evidenceId = $evidence->add(
            'laravel-transition',
            Evidence::E4_MAINTAINER_DOCUMENTATION,
            sprintf('The retained Laravel %d to %d rule pack covers this requested transition.', $sourceMajor, $targetMajor),
            'medium',
            [
                'source_major' => $sourceMajor,
                'target_major' => $targetMajor,
                'rule_pack' => $rulePack,
                'source' => $source,
            ]
        )->id();
        $hop = new FrameworkHop(
            $sourceMajor,
            $targetMajor,
            FrameworkHop::SUPPORTED,
            $rulePack,
            [$evidenceId]
        );

        return new FrameworkGuidance(
            'laravel',
            $sourceMajor,
            $targetMajor,
            FrameworkGuidance::SUPPORTED,
            [$hop],
            [],
            [$evidenceId]
        );
    }

    public function defaultSourcePaths(ProjectState $project): array
    {
        return ['src', 'app', 'bootstrap', 'config', 'database', 'routes', 'tests'];
    }

    public function packageFamilies(string $packageName): array
    {
        return $this->packageFamilyClassifier->packageFamilies($packageName);
    }

    private function hasLaravelTarget(UpgradeRequest $request): bool
    {
        foreach ($request->targets()->packageTargets() as $target) {
            $package = strtolower($target->package());
            if ($package === 'laravel/framework' || str_starts_with($package, 'illuminate/')) {
                return true;
            }
        }

        return false;
    }
}
