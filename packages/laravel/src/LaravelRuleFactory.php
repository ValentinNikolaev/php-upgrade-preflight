<?php

declare(strict_types=1);

namespace PhpUpgradePreflight\Laravel;

use PhpUpgradePreflight\Core\Framework\CompatibilityRule;
use PhpUpgradePreflight\Laravel\Catalog\BuiltinRuleDefinition;
use PhpUpgradePreflight\Laravel\Catalog\LaravelRuleCatalog;
use PhpUpgradePreflight\Laravel\Catalog\PackageAdvisoryDefinition;
use PhpUpgradePreflight\Laravel\Catalog\PackageRuleDefinition;
use PhpUpgradePreflight\Laravel\Catalog\RuleDefinition;
use PhpUpgradePreflight\Laravel\Rules\LaravelComposerVersionRule;
use PhpUpgradePreflight\Laravel\Rules\LaravelCurlExtensionRule;
use PhpUpgradePreflight\Laravel\Rules\LaravelFrameworkConstraintRule;
use PhpUpgradePreflight\Laravel\Rules\LaravelHighSignalSourceRule;
use PhpUpgradePreflight\Laravel\Rules\LaravelPhpConstraintRule;
use PhpUpgradePreflight\Laravel\Rules\LaravelSkeletonRule;
use PhpUpgradePreflight\Laravel\Rules\OldIlluminateSupportRule;
use PhpUpgradePreflight\Laravel\Rules\PackageVersionRule;
use PhpUpgradePreflight\Laravel\Rules\SymfonyComponentConstraintRule;
use PhpUpgradePreflight\Laravel\Rules\TargetedPackageAdvisoryRule;

/**
 * Builds the executable compatibility rules described by a Laravel rule catalog.
 *
 * Rules are yielded in catalog order, one rule per definition, so the report's
 * finding order stays a property of the catalog rather than of this factory.
 */
final class LaravelRuleFactory
{
    private LaravelRuleCatalog $catalog;

    public function __construct(LaravelRuleCatalog $catalog)
    {
        $this->catalog = $catalog;
    }

    /** @return iterable<CompatibilityRule> */
    public function rules(): iterable
    {
        $builders = $this->ruleBuilders();

        foreach ($this->catalog->rules() as $definition) {
            $builder = $builders[get_class($definition)] ?? null;
            $rule = $builder === null ? null : $builder($definition);
            if ($rule === null) {
                throw new \LogicException(sprintf(
                    'Unsupported Laravel catalog rule definition: %s.',
                    get_class($definition)
                ));
            }

            yield $rule;
        }
    }

    /**
     * The one dispatch table over rule-definition subtypes. A new subtype needs a
     * single entry here, and an unmapped subtype is reported instead of skipped.
     *
     * @return array<class-string<RuleDefinition>, \Closure(RuleDefinition): ?CompatibilityRule>
     */
    private function ruleBuilders(): array
    {
        return [
            PackageRuleDefinition::class => static function (RuleDefinition $definition): ?CompatibilityRule {
                return $definition instanceof PackageRuleDefinition
                    ? new PackageVersionRule($definition)
                    : null;
            },
            PackageAdvisoryDefinition::class => static function (RuleDefinition $definition): ?CompatibilityRule {
                return $definition instanceof PackageAdvisoryDefinition
                    ? new TargetedPackageAdvisoryRule($definition)
                    : null;
            },
            BuiltinRuleDefinition::class => function (RuleDefinition $definition): ?CompatibilityRule {
                return $definition instanceof BuiltinRuleDefinition
                    ? $this->builtinRule($definition)
                    : null;
            },
        ];
    }

    private function builtinRule(BuiltinRuleDefinition $definition): CompatibilityRule
    {
        $builder = $this->builtinRuleBuilders()[$definition->rule()] ?? null;
        if ($builder === null) {
            throw new \LogicException(sprintf('Unsupported Laravel built-in rule: %s.', $definition->rule()));
        }

        return $builder($definition);
    }

    /**
     * The one dispatch table over built-in rule kinds, keyed by the catalog's own
     * rule constants so a new kind is a single entry beside its rule class.
     *
     * @return array<string, \Closure(BuiltinRuleDefinition): CompatibilityRule>
     */
    private function builtinRuleBuilders(): array
    {
        $catalog = $this->catalog;

        return [
            BuiltinRuleDefinition::FRAMEWORK_CONSTRAINT => static function (
                BuiltinRuleDefinition $definition
            ): CompatibilityRule {
                return new LaravelFrameworkConstraintRule($definition);
            },
            BuiltinRuleDefinition::PHP_CONSTRAINT => static function (
                BuiltinRuleDefinition $definition
            ) use ($catalog): CompatibilityRule {
                return new LaravelPhpConstraintRule($definition, $catalog);
            },
            BuiltinRuleDefinition::SYMFONY_CONSTRAINT => static function (
                BuiltinRuleDefinition $definition
            ) use ($catalog): CompatibilityRule {
                return new SymfonyComponentConstraintRule($definition, $catalog);
            },
            BuiltinRuleDefinition::ILLUMINATE_SUPPORT => static function (
                BuiltinRuleDefinition $definition
            ): CompatibilityRule {
                return new OldIlluminateSupportRule($definition);
            },
            BuiltinRuleDefinition::SKELETON => static function (
                BuiltinRuleDefinition $definition
            ) use ($catalog): CompatibilityRule {
                return new LaravelSkeletonRule($definition, $catalog->skeletonPatterns());
            },
            BuiltinRuleDefinition::COMPOSER_VERSION => static function (
                BuiltinRuleDefinition $definition
            ): CompatibilityRule {
                return new LaravelComposerVersionRule($definition);
            },
            BuiltinRuleDefinition::CURL_EXTENSION => static function (
                BuiltinRuleDefinition $definition
            ): CompatibilityRule {
                return new LaravelCurlExtensionRule($definition);
            },
            BuiltinRuleDefinition::HIGH_SIGNAL_SOURCE => static function (
                BuiltinRuleDefinition $definition
            ): CompatibilityRule {
                return new LaravelHighSignalSourceRule($definition);
            },
        ];
    }
}
