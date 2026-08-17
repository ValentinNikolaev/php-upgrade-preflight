<?php

declare(strict_types=1);

namespace PhpUpgradePreflight\Laravel\Tests\Unit;

use PhpUpgradePreflight\Laravel\Catalog\BuiltinRuleDefinition;
use PhpUpgradePreflight\Laravel\Catalog\LaravelRuleCatalog;
use PhpUpgradePreflight\Laravel\Catalog\RuleDefinition;
use PhpUpgradePreflight\Laravel\LaravelFrameworkIntegration;
use PhpUpgradePreflight\Laravel\LaravelRuleFactory;
use PhpUpgradePreflight\Laravel\Rules\LaravelComposerVersionRule;
use PhpUpgradePreflight\Laravel\Rules\LaravelCurlExtensionRule;
use PhpUpgradePreflight\Laravel\Rules\LaravelFrameworkConstraintRule;
use PhpUpgradePreflight\Laravel\Rules\LaravelHighSignalSourceRule;
use PhpUpgradePreflight\Laravel\Rules\LaravelPhpConstraintRule;
use PhpUpgradePreflight\Laravel\Rules\LaravelSkeletonRule;
use PhpUpgradePreflight\Laravel\Rules\OldIlluminateSupportRule;
use PhpUpgradePreflight\Laravel\Rules\SymfonyComponentConstraintRule;
use PHPUnit\Framework\TestCase;

final class LaravelRuleFactoryTest extends TestCase
{
    /**
     * Finding order in the report is a property of the catalog, not of the factory, so the
     * facade must expose exactly the sequence the factory builds.
     */
    public function testTheFacadeExposesExactlyTheRuleSequenceTheFactoryBuilds(): void
    {
        $catalog = LaravelRuleCatalog::v0_2();

        self::assertSame(
            $this->ruleClasses((new LaravelRuleFactory($catalog))->rules()),
            $this->ruleClasses((new LaravelFrameworkIntegration(null, $catalog))->rules())
        );
    }

    public function testItBuildsOneRulePerCatalogDefinition(): void
    {
        $catalog = LaravelRuleCatalog::v0_2();

        self::assertCount(count($catalog->rules()), $this->ruleClasses((new LaravelRuleFactory($catalog))->rules()));
    }

    /** @dataProvider builtinRuleProvider */
    public function testEveryBuiltinRuleKindMapsToItsRuleClass(string $rule, string $expectedClass): void
    {
        $factory = new LaravelRuleFactory($this->catalogWithRules([
            new BuiltinRuleDefinition('only', $rule, []),
        ]));

        self::assertSame([$expectedClass], $this->ruleClasses($factory->rules()));
    }

    /** @return array<string, array{string, class-string}> */
    public function builtinRuleProvider(): array
    {
        return [
            'framework constraint' => [BuiltinRuleDefinition::FRAMEWORK_CONSTRAINT, LaravelFrameworkConstraintRule::class],
            'php constraint' => [BuiltinRuleDefinition::PHP_CONSTRAINT, LaravelPhpConstraintRule::class],
            'symfony constraint' => [BuiltinRuleDefinition::SYMFONY_CONSTRAINT, SymfonyComponentConstraintRule::class],
            'illuminate support' => [BuiltinRuleDefinition::ILLUMINATE_SUPPORT, OldIlluminateSupportRule::class],
            'skeleton' => [BuiltinRuleDefinition::SKELETON, LaravelSkeletonRule::class],
            'composer version' => [BuiltinRuleDefinition::COMPOSER_VERSION, LaravelComposerVersionRule::class],
            'curl extension' => [BuiltinRuleDefinition::CURL_EXTENSION, LaravelCurlExtensionRule::class],
            'high signal source' => [BuiltinRuleDefinition::HIGH_SIGNAL_SOURCE, LaravelHighSignalSourceRule::class],
        ];
    }

    /**
     * A rule-definition subtype nobody mapped is a catalog authoring mistake. It must surface
     * as a failure rather than silently dropping the rules that subtype was meant to produce.
     */
    public function testAnUnmappedRuleDefinitionSubtypeIsReportedRatherThanSkipped(): void
    {
        $definition = new class () implements RuleDefinition {
            public function key(): string
            {
                return 'unmapped';
            }
        };
        $factory = new LaravelRuleFactory($this->catalogWithRules([$definition]));

        $this->expectException(\LogicException::class);
        $this->expectExceptionMessage('Unsupported Laravel catalog rule definition: ');

        $built = $this->ruleClasses($factory->rules());
        self::fail(sprintf('Expected an unmapped definition to be rejected, built %d rule(s).', count($built)));
    }

    public function testAnUnmappedBuiltinRuleKindIsReportedRatherThanSkipped(): void
    {
        $factory = new LaravelRuleFactory($this->catalogWithRules([
            new BuiltinRuleDefinition('unknown-kind', 'not_a_builtin_rule', []),
        ]));

        $this->expectException(\LogicException::class);
        $this->expectExceptionMessage('Unsupported Laravel built-in rule: not_a_builtin_rule.');

        $built = $this->ruleClasses($factory->rules());
        self::fail(sprintf('Expected an unmapped built-in kind to be rejected, built %d rule(s).', count($built)));
    }

    /** @param list<RuleDefinition> $rules */
    private function catalogWithRules(array $rules): LaravelRuleCatalog
    {
        return new LaravelRuleCatalog('test', 7, 8, [], [], $rules, []);
    }

    /**
     * @param iterable<object> $rules
     * @return list<string>
     */
    private function ruleClasses(iterable $rules): array
    {
        $classes = [];
        foreach ($rules as $rule) {
            $classes[] = get_class($rule);
        }

        return $classes;
    }
}
