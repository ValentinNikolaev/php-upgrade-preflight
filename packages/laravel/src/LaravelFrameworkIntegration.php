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
use PhpUpgradePreflight\Laravel\Rules\LaravelSource;
use PhpUpgradePreflight\Laravel\Rules\LaravelTarget;
use PhpUpgradePreflight\Laravel\Rules\OldIlluminateSupportRule;
use PhpUpgradePreflight\Laravel\Rules\PackageVersionRule;
use PhpUpgradePreflight\Laravel\Rules\SymfonyComponentConstraintRule;
use PhpUpgradePreflight\Laravel\Rules\TargetedPackageAdvisoryRule;

final class LaravelFrameworkIntegration implements FrameworkIntegration, FrameworkTransitionProvider, PackageFamilyClassifier
{
    private const MINIMUM_CATALOG_MAJOR = 7;
    private const MAXIMUM_CATALOG_MAJOR = 13;

    /** @var array<string, string> */
    private const ADJACENT_RULE_PACKS = [
        '7:8' => 'laravel-7-to-8',
    ];

    /** @var array<string, string> */
    private const DIRECT_RULE_PACKS = [
        '7:9' => 'laravel-7-to-9-direct',
    ];

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

        $source = LaravelSource::fromProject($project);
        $sourceMajor = $source->major();
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
                    'source_observations' => $source->observations(),
                    'target_constraints' => $target === null
                        ? $this->laravelTargetConstraints($request)
                        : $target->requestedConstraints(),
                    'root_requirements' => $project->composerJson()->rootRequirements(),
                ]
            )->id();

            $uncertainties = $sourceMajor === null ? $source->uncertainties() : [];
            if ($targetMajor === null) {
                $uncertainties[] = 'The requested Laravel package constraints do not identify exactly one target major.';
            }
            $uncertainties = array_map(
                static fn (string $uncertainty): string => sprintf('%s (%s)', $uncertainty, $evidenceId),
                $uncertainties
            );

            return new FrameworkGuidance(
                'laravel',
                $sourceMajor,
                $targetMajor,
                FrameworkGuidance::UNSUPPORTED,
                [],
                $uncertainties,
                [$evidenceId]
            );
        }

        if ($sourceMajor >= $targetMajor) {
            return $this->unsupportedTransition(
                $sourceMajor,
                $targetMajor,
                'Laravel framework guidance is unsupported because the requested target is not a major-version upgrade.',
                $evidence
            );
        }

        $directRulePack = self::DIRECT_RULE_PACKS[$this->transitionKey($sourceMajor, $targetMajor)] ?? null;
        if ($directRulePack !== null && !$this->hasCompleteAdjacentPath($sourceMajor, $targetMajor)) {
            return $this->supportedDirectTransition($sourceMajor, $targetMajor, $directRulePack, $evidence);
        }

        if ($sourceMajor < self::MINIMUM_CATALOG_MAJOR || $targetMajor > self::MAXIMUM_CATALOG_MAJOR) {
            return $this->unsupportedTransition(
                $sourceMajor,
                $targetMajor,
                sprintf(
                    'Laravel framework guidance is unsupported outside the modeled Laravel %d through %d transition catalog.',
                    self::MINIMUM_CATALOG_MAJOR,
                    self::MAXIMUM_CATALOG_MAJOR
                ),
                $evidence
            );
        }

        return $this->adjacentTransition($sourceMajor, $targetMajor, $evidence);
    }

    private function supportedDirectTransition(
        int $sourceMajor,
        int $targetMajor,
        string $rulePack,
        EvidenceLedger $evidence
    ): FrameworkGuidance {
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

    private function adjacentTransition(
        int $sourceMajor,
        int $targetMajor,
        EvidenceLedger $evidence
    ): FrameworkGuidance {
        $hops = [];
        $evidenceIds = [];
        $uncertainties = [];
        $coveredPrefix = true;
        $supportedCount = 0;

        for ($fromMajor = $sourceMajor; $fromMajor < $targetMajor; ++$fromMajor) {
            $toMajor = $fromMajor + 1;
            $implementedRulePack = self::ADJACENT_RULE_PACKS[$this->transitionKey($fromMajor, $toMajor)] ?? null;
            $rulePack = $coveredPrefix ? $implementedRulePack : null;

            if ($rulePack !== null) {
                $evidenceId = $evidence->add(
                    'laravel-transition',
                    Evidence::E4_MAINTAINER_DOCUMENTATION,
                    sprintf('The retained Laravel %d to %d rule pack covers this requested transition.', $fromMajor, $toMajor),
                    'medium',
                    [
                        'source_major' => $fromMajor,
                        'target_major' => $toMajor,
                        'rule_pack' => $rulePack,
                        'source' => sprintf('https://laravel.com/docs/%d.x/upgrade', $toMajor),
                    ]
                )->id();
                $hops[] = new FrameworkHop(
                    $fromMajor,
                    $toMajor,
                    FrameworkHop::SUPPORTED,
                    $rulePack,
                    [$evidenceId]
                );
                $evidenceIds[] = $evidenceId;
                ++$supportedCount;

                continue;
            }

            $ignoredAfterGap = $implementedRulePack !== null;
            $coveredPrefix = false;
            $evidenceId = $evidence->add(
                'laravel-transition',
                Evidence::E4_MAINTAINER_DOCUMENTATION,
                $ignoredAfterGap
                    ? sprintf('The Laravel %d to %d adjacent rule pack is ignored after an earlier coverage gap.', $fromMajor, $toMajor)
                    : sprintf('No implemented Laravel %d to %d adjacent rule pack is available.', $fromMajor, $toMajor),
                'medium',
                [
                    'source_major' => $fromMajor,
                    'target_major' => $toMajor,
                    'rule_pack' => $implementedRulePack,
                    'implemented' => $ignoredAfterGap,
                    'ignored_after_gap' => $ignoredAfterGap,
                    'source' => sprintf('https://laravel.com/docs/%d.x/upgrade', $toMajor),
                ]
            )->id();
            $hops[] = new FrameworkHop(
                $fromMajor,
                $toMajor,
                FrameworkHop::UNSUPPORTED,
                null,
                [$evidenceId]
            );
            $evidenceIds[] = $evidenceId;
            $uncertainties[] = $ignoredAfterGap
                ? sprintf(
                    'Laravel %d to %d guidance is ignored because coverage cannot continue after an earlier missing hop (%s).',
                    $fromMajor,
                    $toMajor,
                    $evidenceId
                )
                : sprintf(
                    'Laravel %d to %d guidance is unavailable because its adjacent rule pack is not implemented (%s).',
                    $fromMajor,
                    $toMajor,
                    $evidenceId
                );
        }

        if ($supportedCount === count($hops)) {
            $status = FrameworkGuidance::SUPPORTED;
        } elseif ($supportedCount > 0) {
            $status = FrameworkGuidance::PARTIALLY_SUPPORTED;
        } else {
            $status = FrameworkGuidance::UNSUPPORTED;
            $hops = [];
        }

        return new FrameworkGuidance(
            'laravel',
            $sourceMajor,
            $targetMajor,
            $status,
            $hops,
            $uncertainties,
            $evidenceIds
        );
    }

    private function unsupportedTransition(
        int $sourceMajor,
        int $targetMajor,
        string $uncertainty,
        EvidenceLedger $evidence
    ): FrameworkGuidance {
        $evidenceId = $evidence->add(
            'laravel-transition',
            Evidence::E2_PACKAGE_METADATA,
            $uncertainty,
            'high',
            [
                'source_major' => $sourceMajor,
                'target_major' => $targetMajor,
                'catalog_minimum_major' => self::MINIMUM_CATALOG_MAJOR,
                'catalog_maximum_major' => self::MAXIMUM_CATALOG_MAJOR,
            ]
        )->id();

        return new FrameworkGuidance(
            'laravel',
            $sourceMajor,
            $targetMajor,
            FrameworkGuidance::UNSUPPORTED,
            [],
            [sprintf('%s (%s)', $uncertainty, $evidenceId)],
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

    private function transitionKey(int $sourceMajor, int $targetMajor): string
    {
        return $sourceMajor . ':' . $targetMajor;
    }

    private function hasCompleteAdjacentPath(int $sourceMajor, int $targetMajor): bool
    {
        for ($fromMajor = $sourceMajor; $fromMajor < $targetMajor; ++$fromMajor) {
            if (!isset(self::ADJACENT_RULE_PACKS[$this->transitionKey($fromMajor, $fromMajor + 1)])) {
                return false;
            }
        }

        return true;
    }

    /** @return array<string, string> */
    private function laravelTargetConstraints(UpgradeRequest $request): array
    {
        $constraints = [];
        foreach ($request->targets()->packageTargets() as $target) {
            $package = strtolower($target->package());
            if ($package === 'laravel/framework' || str_starts_with($package, 'illuminate/')) {
                $constraints[$package] = $target->constraint();
            }
        }
        ksort($constraints);

        return $constraints;
    }
}
