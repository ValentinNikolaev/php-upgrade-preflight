<?php

declare(strict_types=1);

namespace PhpUpgradePreflight\Laravel\Catalog;

final class LaravelRuleCatalog
{
    private string $version;
    private int $minimumMajor;
    private int $maximumMajor;
    /** @var list<TargetDefinition> */
    private array $targets;
    /** @var list<TransitionDefinition> */
    private array $transitions;
    /** @var list<RuleDefinition> */
    private array $rules;
    /** @var list<SkeletonPattern> */
    private array $skeletonPatterns;

    /**
     * @param list<TargetDefinition> $targets
     * @param list<TransitionDefinition> $transitions
     * @param list<RuleDefinition> $rules
     * @param list<SkeletonPattern> $skeletonPatterns
     */
    public function __construct(
        string $version,
        int $minimumMajor,
        int $maximumMajor,
        array $targets,
        array $transitions,
        array $rules,
        array $skeletonPatterns
    ) {
        $this->version = $version;
        $this->minimumMajor = $minimumMajor;
        $this->maximumMajor = $maximumMajor;
        $this->targets = $targets;
        $this->transitions = $transitions;
        $this->rules = $rules;
        $this->skeletonPatterns = $skeletonPatterns;
    }

    public static function v0_2(): self
    {
        $appliesTo8 = new RuleApplicability(7, 8);
        $appliesTo9 = new RuleApplicability(7, 9);
        $retainedV01 = [$appliesTo8, $appliesTo9];
        $applies8To9 = new RuleApplicability(8, 9);
        $applies9To10 = new RuleApplicability(9, 10);
        $applies10To11 = new RuleApplicability(10, 11);
        $applies11To12 = new RuleApplicability(11, 12);
        $applies12To13 = new RuleApplicability(12, 13);
        $implemented = array_merge($retainedV01, [
            $applies8To9,
            $applies9To10,
            $applies10To11,
            $applies11To12,
            $applies12To13,
        ]);
        $laravel8Upgrade = 'https://laravel.com/docs/8.x/upgrade';
        $laravel9Upgrade = 'https://laravel.com/docs/9.x/upgrade';
        $laravel10Upgrade = 'https://laravel.com/docs/10.x/upgrade';
        $laravel11Upgrade = 'https://laravel.com/docs/11.x/upgrade';
        $laravel12Upgrade = 'https://laravel.com/docs/12.x/upgrade';
        $laravel13Upgrade = 'https://github.com/laravel/docs/blob/9c5a062c14069bab9054b558829e282f9593a065/upgrade.md';
        $laravel10Framework = 'https://github.com/laravel/framework/blob/61cad00bdaf0f710da070e1596c9ca6d567f4d12/composer.json';
        $laravel11Framework = 'https://github.com/laravel/framework/blob/e353708c960ec5066d76b0da4b81c8a68d183b93/composer.json';
        $laravel12Framework = 'https://github.com/laravel/framework/blob/5260836df1b953a558d9b810880f20db15568c01/composer.json';
        $laravel13Framework = 'https://github.com/laravel/framework/blob/8df67f9d176d1d0375a866d8c6780be95ce0336e/composer.json';
        $laravel13Skeleton = 'https://github.com/laravel/laravel/blob/c926b8ca7fa01e71852e19141f2bdd7fabfb6ade/composer.json';
        $laravel8Skeleton = 'https://github.com/laravel/laravel/blob/8.x/composer.json';
        $laravel9Skeleton = 'https://github.com/laravel/laravel/blob/9.x/composer.json';

        $targets = [
            new TargetDefinition('target-8', 8, '^7.3|^8.0', [$laravel8Upgrade], '^5.0', [
                'https://github.com/laravel/framework/blob/8.x/composer.json',
            ]),
            new TargetDefinition('target-9', 9, '^8.0.2', [$laravel9Upgrade], '^6.0', [
                'https://github.com/laravel/framework/blob/9.x/composer.json',
            ]),
            new TargetDefinition('target-10', 10, '^8.1', [$laravel10Upgrade, $laravel10Framework], '^6.2', [$laravel10Framework], [
                'symfony/http-client' => '^6.2.4',
                'symfony/http-foundation' => '^6.4',
            ]),
            new TargetDefinition('target-11', 11, '^8.2', [$laravel11Upgrade, $laravel11Framework], '^7.0.3', [$laravel11Framework], [
                'symfony/http-foundation' => '^7.2.0',
            ]),
            new TargetDefinition('target-12', 12, '^8.2', [$laravel12Upgrade, $laravel12Framework], '^7.2.0', [$laravel12Framework]),
            new TargetDefinition('target-13', 13, '^8.3', [$laravel13Upgrade, $laravel13Framework], '^7.4.0|^8.0.0', [$laravel13Framework], [
                'symfony/cache' => '^7.4.12|^8.0.12',
                'symfony/http-foundation' => '^7.4.13|^8.0.13',
                'symfony/http-kernel' => '^7.4.12|^8.0.12',
                'symfony/mailer' => '^7.4.12|^8.0.12',
                'symfony/mime' => '^7.4.12|^8.0.12',
                'symfony/process' => '^7.4.5|^8.0.5',
                'symfony/routing' => '^7.4.13|^8.0.13',
            ]),
        ];

        $transitions = [
            new TransitionDefinition('adjacent-7-8', 7, 8, TransitionDefinition::ADJACENT, 'laravel-7-to-8', [$laravel8Upgrade]),
            new TransitionDefinition('adjacent-8-9', 8, 9, TransitionDefinition::ADJACENT, 'laravel-8-to-9', [$laravel9Upgrade]),
            new TransitionDefinition('adjacent-9-10', 9, 10, TransitionDefinition::ADJACENT, 'laravel-9-to-10', [$laravel10Upgrade]),
            new TransitionDefinition('adjacent-10-11', 10, 11, TransitionDefinition::ADJACENT, 'laravel-10-to-11', [$laravel11Upgrade]),
            new TransitionDefinition('adjacent-11-12', 11, 12, TransitionDefinition::ADJACENT, 'laravel-11-to-12', [$laravel12Upgrade]),
            new TransitionDefinition('adjacent-12-13', 12, 13, TransitionDefinition::ADJACENT, 'laravel-12-to-13', [$laravel13Upgrade]),
            new TransitionDefinition('direct-7-9', 7, 9, TransitionDefinition::DIRECT, 'laravel-7-to-9-direct', [$laravel9Upgrade]),
        ];

        $rules = [
            new BuiltinRuleDefinition('rule-framework-constraint', BuiltinRuleDefinition::FRAMEWORK_CONSTRAINT, $implemented),
            new BuiltinRuleDefinition('rule-php-constraint', BuiltinRuleDefinition::PHP_CONSTRAINT, $implemented),
            self::packageRule('rule-package-passport', 'laravel/passport', $appliesTo8, '^10.0', 'high', [$laravel8Upgrade], true, $appliesTo9, '^10.0|^11.0', [
                'https://github.com/laravel/passport/blob/10.x/composer.json',
                'https://github.com/laravel/passport/blob/11.x/composer.json',
            ]),
            self::packageRule('rule-package-sanctum', 'laravel/sanctum', $appliesTo8, '^2.0', 'medium', [
                'https://github.com/laravel/sanctum/blob/2.x/composer.json',
            ], true, $appliesTo9, '^2.0|^3.0', [
                'https://github.com/laravel/sanctum/blob/2.x/composer.json',
                'https://github.com/laravel/sanctum/blob/3.x/composer.json',
            ]),
            self::packageRule('rule-package-horizon', 'laravel/horizon', $appliesTo8, '^5.0', 'high', [$laravel8Upgrade], true, $appliesTo9, '^5.0', [
                'https://github.com/laravel/horizon/blob/5.x/composer.json',
            ]),
            self::packageRule('rule-package-telescope', 'laravel/telescope', $appliesTo8, '^4.0', 'medium', [
                'https://github.com/laravel/telescope/blob/4.x/composer.json',
            ], true, $appliesTo9, '^4.0', [
                'https://github.com/laravel/telescope/blob/4.x/composer.json',
            ]),
            self::packageRule('rule-package-phpunit', 'phpunit/phpunit', $appliesTo8, '^9.0', 'medium', [$laravel8Upgrade, $laravel8Skeleton], false, $appliesTo9, '^9.5.10', [$laravel9Skeleton]),
            self::packageRule('rule-package-mockery', 'mockery/mockery', $appliesTo8, '^1.4', 'low', [$laravel8Skeleton], false, $appliesTo9, '^1.4', [$laravel9Skeleton]),
            new BuiltinRuleDefinition('rule-symfony-constraint', BuiltinRuleDefinition::SYMFONY_CONSTRAINT, $implemented),
            new BuiltinRuleDefinition('rule-illuminate-support', BuiltinRuleDefinition::ILLUMINATE_SUPPORT, $implemented),
            new PackageRuleDefinition('rule-package-facade-ignition', [
                new PackageConstraintDefinition('package-facade-ignition-7-8', 'facade/ignition', $appliesTo8, '>=2.3.6 <3.0', 'medium', [$laravel8Upgrade]),
            ]),
            new PackageAdvisoryDefinition('advisory-facade-ignition-7-9', 'facade/ignition', $appliesTo9, PackageAdvisoryDefinition::REPLACE_IGNITION, 'high', [$laravel9Upgrade]),
            new PackageAdvisoryDefinition('advisory-fideloper-proxy-7-9', 'fideloper/proxy', $appliesTo9, PackageAdvisoryDefinition::REMOVE_TRUSTED_PROXY, 'medium', [$laravel9Upgrade]),
            new PackageRuleDefinition('rule-package-fruitcake-cors', [
                new PackageConstraintDefinition('package-fruitcake-cors-7-8', 'fruitcake/laravel-cors', $appliesTo8, '^2.0', 'medium', [$laravel8Skeleton]),
            ]),
            new PackageAdvisoryDefinition('advisory-fruitcake-cors-7-9', 'fruitcake/laravel-cors', $appliesTo9, PackageAdvisoryDefinition::REVIEW_CORS_REMOVAL, 'medium', [$laravel9Upgrade]),
            self::packageRule('rule-package-collision', 'nunomaduro/collision', $appliesTo8, '^5.0', 'medium', [$laravel8Upgrade], false, $appliesTo9, '^6.1', [$laravel9Upgrade]),
            self::packageRule('rule-package-laravel-ui', 'laravel/ui', $appliesTo8, '^3.0', 'low', [
                'https://github.com/laravel/ui/blob/3.x/composer.json',
            ], false, $appliesTo9, '^4.0', [
                'https://github.com/laravel/ui/blob/4.x/composer.json',
            ]),
            self::packageRule('rule-package-testbench', 'orchestra/testbench', $appliesTo8, '^6.0', 'medium', [
                'https://github.com/orchestral/testbench/blob/6.x/composer.json',
            ], false, $appliesTo9, '^7.0', [
                'https://github.com/orchestral/testbench/blob/7.x/composer.json',
            ]),
            new BuiltinRuleDefinition('rule-skeleton', BuiltinRuleDefinition::SKELETON, $retainedV01),

            self::singlePackageRule('rule-package-pusher-8-9', 'pusher/pusher-php-server', $applies8To9, '^5.0', 'medium', [$laravel9Upgrade]),
            self::singlePackageRule('rule-package-spatie-ignition-8-9', 'spatie/laravel-ignition', $applies8To9, '^1.0', 'high', [$laravel9Upgrade]),
            self::singlePackageRule('rule-package-flysystem-s3-8-9', 'league/flysystem-aws-s3-v3', $applies8To9, '^3.0', 'high', [$laravel9Upgrade]),
            self::singlePackageRule('rule-package-flysystem-ftp-8-9', 'league/flysystem-ftp', $applies8To9, '^3.0', 'high', [$laravel9Upgrade]),
            self::singlePackageRule('rule-package-flysystem-sftp-v3-8-9', 'league/flysystem-sftp-v3', $applies8To9, '^3.0', 'high', [$laravel9Upgrade]),
            new PackageAdvisoryDefinition('advisory-flysystem-sftp-8-9', 'league/flysystem-sftp', $applies8To9, PackageAdvisoryDefinition::REPLACE_FLYSYSTEM_SFTP, 'high', [$laravel9Upgrade]),

            new BuiltinRuleDefinition('rule-composer-version-9-10', BuiltinRuleDefinition::COMPOSER_VERSION, [$applies9To10]),
            new BuiltinRuleDefinition('rule-high-signal-source-9-10', BuiltinRuleDefinition::HIGH_SIGNAL_SOURCE, [$applies9To10]),
            self::singlePackageRule('rule-package-dbal-9-10', 'doctrine/dbal', $applies9To10, '^3.0', 'high', [$laravel10Upgrade]),
            self::singlePackageRule('rule-package-passport-9-10', 'laravel/passport', $applies9To10, '^11.0', 'high', [$laravel10Upgrade]),
            self::singlePackageRule('rule-package-sanctum-9-10', 'laravel/sanctum', $applies9To10, '^3.2', 'high', [$laravel10Upgrade]),
            self::singlePackageRule('rule-package-ui-9-10', 'laravel/ui', $applies9To10, '^4.0', 'medium', [$laravel10Upgrade]),
            self::singlePackageRule('rule-package-ignition-9-10', 'spatie/laravel-ignition', $applies9To10, '^2.0', 'high', [$laravel10Upgrade]),
            self::singlePackageRule('rule-package-collision-9-10', 'nunomaduro/collision', $applies9To10, '^7.0', 'medium', [$laravel10Upgrade]),
            self::singlePackageRule('rule-package-phpunit-9-10', 'phpunit/phpunit', $applies9To10, '^10.0', 'medium', [$laravel10Upgrade]),

            new BuiltinRuleDefinition('rule-curl-extension-10-11', BuiltinRuleDefinition::CURL_EXTENSION, [$applies10To11]),
            self::singlePackageRule('rule-package-collision-10-11', 'nunomaduro/collision', $applies10To11, '^8.1', 'medium', [$laravel11Upgrade]),
            self::singlePackageRule('rule-package-breeze-10-11', 'laravel/breeze', $applies10To11, '^2.0', 'medium', [$laravel11Upgrade]),
            self::singlePackageRule('rule-package-cashier-10-11', 'laravel/cashier', $applies10To11, '^15.0', 'high', [$laravel11Upgrade]),
            self::singlePackageRule('rule-package-dusk-10-11', 'laravel/dusk', $applies10To11, '^8.0', 'medium', [$laravel11Upgrade]),
            self::singlePackageRule('rule-package-jetstream-10-11', 'laravel/jetstream', $applies10To11, '^5.0', 'medium', [$laravel11Upgrade]),
            self::singlePackageRule('rule-package-octane-10-11', 'laravel/octane', $applies10To11, '^2.3', 'medium', [$laravel11Upgrade]),
            self::singlePackageRule('rule-package-passport-10-11', 'laravel/passport', $applies10To11, '^12.0', 'high', [$laravel11Upgrade]),
            self::singlePackageRule('rule-package-sanctum-10-11', 'laravel/sanctum', $applies10To11, '^4.0', 'high', [$laravel11Upgrade]),
            self::singlePackageRule('rule-package-scout-10-11', 'laravel/scout', $applies10To11, '^10.0', 'medium', [$laravel11Upgrade]),
            self::singlePackageRule('rule-package-spark-10-11', 'laravel/spark-stripe', $applies10To11, '^5.0', 'high', [$laravel11Upgrade]),
            self::singlePackageRule('rule-package-telescope-10-11', 'laravel/telescope', $applies10To11, '^5.0', 'high', [$laravel11Upgrade]),
            self::singlePackageRule('rule-package-livewire-10-11', 'livewire/livewire', $applies10To11, '^3.4', 'medium', [$laravel11Upgrade]),
            self::singlePackageRule('rule-package-inertia-10-11', 'inertiajs/inertia-laravel', $applies10To11, '^1.0', 'medium', [$laravel11Upgrade]),
            self::singlePackageRule('rule-package-phpunit-10-11', 'phpunit/phpunit', $applies10To11, '^11.0.1', 'medium', ['https://github.com/laravel/laravel/blob/11.x/composer.json']),
            new PackageAdvisoryDefinition('advisory-dbal-removal-10-11', 'doctrine/dbal', $applies10To11, PackageAdvisoryDefinition::REVIEW_DBAL_REMOVAL, 'low', [$laravel11Upgrade]),
            new PackageAdvisoryDefinition('advisory-cashier-migrations-10-11', 'laravel/cashier', $applies10To11, PackageAdvisoryDefinition::PUBLISH_MIGRATIONS, 'high', [$laravel11Upgrade]),
            new PackageAdvisoryDefinition('advisory-passport-migrations-10-11', 'laravel/passport', $applies10To11, PackageAdvisoryDefinition::PUBLISH_MIGRATIONS, 'high', [$laravel11Upgrade]),
            new PackageAdvisoryDefinition('advisory-sanctum-migrations-10-11', 'laravel/sanctum', $applies10To11, PackageAdvisoryDefinition::PUBLISH_MIGRATIONS, 'high', [$laravel11Upgrade]),
            new PackageAdvisoryDefinition('advisory-spark-migrations-10-11', 'laravel/spark-stripe', $applies10To11, PackageAdvisoryDefinition::PUBLISH_MIGRATIONS, 'high', [$laravel11Upgrade]),
            new PackageAdvisoryDefinition('advisory-telescope-migrations-10-11', 'laravel/telescope', $applies10To11, PackageAdvisoryDefinition::PUBLISH_MIGRATIONS, 'high', [$laravel11Upgrade]),

            self::singlePackageRule('rule-package-phpunit-11-12', 'phpunit/phpunit', $applies11To12, '^11.0', 'high', [$laravel12Upgrade]),
            self::singlePackageRule('rule-package-pest-11-12', 'pestphp/pest', $applies11To12, '^3.0', 'high', [$laravel12Upgrade]),
            self::singlePackageRule('rule-package-carbon-11-12', 'nesbot/carbon', $applies11To12, '^3.0', 'medium', [$laravel12Upgrade]),
            self::singlePackageRule('rule-package-collision-11-12', 'nunomaduro/collision', $applies11To12, '^8.6', 'medium', ['https://github.com/laravel/laravel/blob/12.x/composer.json']),

            self::singlePackageRule('rule-package-boost-12-13', 'laravel/boost', $applies12To13, '^2.0', 'high', [$laravel13Upgrade]),
            self::singlePackageRule('rule-package-tinker-12-13', 'laravel/tinker', $applies12To13, '^3.0', 'high', [$laravel13Upgrade]),
            self::singlePackageRule('rule-package-phpunit-12-13', 'phpunit/phpunit', $applies12To13, '^12.0', 'high', [$laravel13Upgrade, $laravel13Skeleton]),
            self::singlePackageRule('rule-package-pest-12-13', 'pestphp/pest', $applies12To13, '^4.0', 'high', [$laravel13Upgrade]),
            self::singlePackageRule('rule-package-collision-12-13', 'nunomaduro/collision', $applies12To13, '^8.6', 'medium', [$laravel13Skeleton]),
            new PackageAdvisoryDefinition(
                'advisory-laravel-helpers-12-13',
                'laravel/helpers',
                $applies12To13,
                PackageAdvisoryDefinition::REVIEW_LEGACY_HELPERS,
                'medium',
                [$laravel13Upgrade]
            ),
            new BuiltinRuleDefinition('rule-high-signal-source-12-13', BuiltinRuleDefinition::HIGH_SIGNAL_SOURCE, [$applies12To13]),
        ];

        return new self('0.2', 7, 13, $targets, $transitions, $rules, [
            new SkeletonPattern('kernel-middleware', 'app/http/kernel.php', ['middleware_reference']),
            new SkeletonPattern('application-provider-alias', 'config/app.php', ['service_provider', 'facade_alias']),
            new SkeletonPattern('legacy-trust-proxies', 'app/http/middleware/trustproxies.php', ['inheritance'], 'fideloper\\proxy\\trustproxies'),
        ]);
    }

    public function version(): string
    {
        return $this->version;
    }

    public function minimumMajor(): int
    {
        return $this->minimumMajor;
    }

    public function maximumMajor(): int
    {
        return $this->maximumMajor;
    }

    /** @return list<TargetDefinition> */
    public function targets(): array
    {
        return $this->targets;
    }

    public function target(int $major): ?TargetDefinition
    {
        foreach ($this->targets as $target) {
            if ($target->major() === $major) {
                return $target;
            }
        }

        return null;
    }

    /** @return list<TransitionDefinition> */
    public function transitions(): array
    {
        return $this->transitions;
    }

    public function transition(int $sourceMajor, int $targetMajor, string $kind): ?TransitionDefinition
    {
        foreach ($this->transitions as $transition) {
            if ($transition->sourceMajor() === $sourceMajor
                && $transition->targetMajor() === $targetMajor
                && $transition->kind() === $kind) {
                return $transition;
            }
        }

        return null;
    }

    /** @return list<RuleDefinition> */
    public function rules(): array
    {
        return $this->rules;
    }

    /** @return list<SkeletonPattern> */
    public function skeletonPatterns(): array
    {
        return $this->skeletonPatterns;
    }

    /**
     * @param list<string> $sources8
     * @param list<string> $sources9
     */
    private static function packageRule(
        string $key,
        string $package,
        RuleApplicability $appliesTo8,
        string $constraint8,
        string $severity,
        array $sources8,
        bool $preferLockedFrameworkRequirements,
        RuleApplicability $appliesTo9,
        string $constraint9,
        array $sources9
    ): PackageRuleDefinition {
        return new PackageRuleDefinition($key, [
            new PackageConstraintDefinition($key . '-7-8', $package, $appliesTo8, $constraint8, $severity, $sources8, $preferLockedFrameworkRequirements),
            new PackageConstraintDefinition($key . '-7-9', $package, $appliesTo9, $constraint9, $severity, $sources9, $preferLockedFrameworkRequirements),
        ]);
    }

    /** @param list<string> $sources */
    private static function singlePackageRule(
        string $key,
        string $package,
        RuleApplicability $applicability,
        string $constraint,
        string $severity,
        array $sources
    ): PackageRuleDefinition {
        return new PackageRuleDefinition($key, [
            new PackageConstraintDefinition(
                $key . '-' . str_replace(':', '-', $applicability->key()),
                $package,
                $applicability,
                $constraint,
                $severity,
                $sources
            ),
        ]);
    }
}
