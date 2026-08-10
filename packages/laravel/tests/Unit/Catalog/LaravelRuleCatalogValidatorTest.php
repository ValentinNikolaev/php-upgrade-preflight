<?php

declare(strict_types=1);

namespace PhpUpgradePreflight\Laravel\Tests\Unit\Catalog;

use PhpUpgradePreflight\Laravel\Catalog\LaravelRuleCatalog;
use PhpUpgradePreflight\Laravel\Catalog\LaravelRuleCatalogValidator;
use PhpUpgradePreflight\Laravel\Catalog\PackageAdvisoryDefinition;
use PhpUpgradePreflight\Laravel\Catalog\PackageConstraintDefinition;
use PhpUpgradePreflight\Laravel\Catalog\PackageRuleDefinition;
use PhpUpgradePreflight\Laravel\Catalog\RuleApplicability;
use PhpUpgradePreflight\Laravel\Catalog\RuleDefinition;
use PhpUpgradePreflight\Laravel\Catalog\TargetDefinition;
use PhpUpgradePreflight\Laravel\Catalog\TransitionDefinition;
use PHPUnit\Framework\TestCase;

final class LaravelRuleCatalogValidatorTest extends TestCase
{
    public function testVersionedCatalogIsValid(): void
    {
        $catalog = LaravelRuleCatalog::v0_2();

        self::assertSame('0.2', $catalog->version());
        self::assertSame([], (new LaravelRuleCatalogValidator())->validate($catalog));
        self::assertSame(
            '^7.4.13|^8.0.13',
            $catalog->target(13)->symfonyConstraintFor('symfony/http-foundation')
        );
        self::assertSame('^7.4.0|^8.0.0', $catalog->target(13)->symfonyConstraintFor('symfony/console'));
    }

    public function testItReportsDuplicateKeysMissingSourcesInvalidConstraintsGapsAndContradictoryAdvice(): void
    {
        $valid = LaravelRuleCatalog::v0_2();
        $targets = $valid->targets();
        $targets[] = new TargetDefinition('target-8', 14, 'definitely not semver(', [
            'https://laravel.com/docs/14.x/upgrade',
        ]);

        $transitions = array_values(array_filter(
            $valid->transitions(),
            static fn (TransitionDefinition $transition): bool => $transition->key() !== 'adjacent-9-10'
        ));
        $transitions[0] = new TransitionDefinition(
            'adjacent-7-8',
            7,
            8,
            TransitionDefinition::ADJACENT,
            'laravel-7-to-8',
            []
        );

        $rules = $valid->rules();
        $rules[] = new PackageAdvisoryDefinition(
            'contradict-facade-ignition-7-9',
            'facade/ignition',
            new RuleApplicability(7, 9),
            PackageAdvisoryDefinition::REPLACE_IGNITION,
            'high',
            ['https://laravel.com/docs/9.x/upgrade']
        );

        $invalid = new LaravelRuleCatalog(
            $valid->version(),
            $valid->minimumMajor(),
            $valid->maximumMajor(),
            $targets,
            $transitions,
            $rules,
            $valid->skeletonPatterns()
        );

        $message = implode("\n", (new LaravelRuleCatalogValidator())->validate($invalid));

        self::assertStringContainsString('Duplicate catalog key: target-8', $message);
        self::assertStringContainsString('Invalid SemVer constraint', $message);
        self::assertStringContainsString('Missing evidence source for adjacent-7-8', $message);
        self::assertStringContainsString('unsupported gap: adjacent transition 9:10', $message);
        self::assertStringContainsString('Contradictory package advice for facade/ignition on transition 7:9', $message);
    }

    public function testItRejectsAnAdvisoryActionAssignedToTheWrongPackage(): void
    {
        $valid = LaravelRuleCatalog::v0_2();
        $rules = $valid->rules();
        $rules[] = new PackageAdvisoryDefinition(
            'mismatched-ignition-advisory',
            'vendor/package',
            new RuleApplicability(7, 9),
            PackageAdvisoryDefinition::REPLACE_IGNITION,
            'high',
            ['https://laravel.com/docs/9.x/upgrade']
        );

        $message = implode("\n", (new LaravelRuleCatalogValidator())->validate(
            $this->withRules($valid, $rules)
        ));

        self::assertStringContainsString(
            'mismatched-ignition-advisory uses action replace_ignition for vendor/package; expected facade/ignition',
            $message
        );
    }

    public function testItRejectsInvalidConstraintAndAdvisorySeverities(): void
    {
        $valid = LaravelRuleCatalog::v0_2();
        $rules = $valid->rules();
        $rules[] = new PackageRuleDefinition('invalid-constraint-severity-rule', [
            new PackageConstraintDefinition(
                'invalid-constraint-severity',
                'vendor/package',
                new RuleApplicability(7, 8),
                '^1.0',
                'critical',
                ['https://laravel.com/docs/8.x/upgrade']
            ),
        ]);
        $constraintMessage = implode("\n", (new LaravelRuleCatalogValidator())->validate(
            $this->withRules($valid, $rules)
        ));

        $advisoryRules = array_map(
            static function (RuleDefinition $rule): RuleDefinition {
                if (!$rule instanceof PackageAdvisoryDefinition
                    || $rule->key() !== 'advisory-facade-ignition-7-9') {
                    return $rule;
                }

                return new PackageAdvisoryDefinition(
                    $rule->key(),
                    $rule->package(),
                    $rule->applicability(),
                    $rule->action(),
                    'critical',
                    $rule->sources()
                );
            },
            $valid->rules()
        );
        $advisoryMessage = implode("\n", (new LaravelRuleCatalogValidator())->validate(
            $this->withRules($valid, $advisoryRules)
        ));

        self::assertStringContainsString('Invalid severity for invalid-constraint-severity: critical', $constraintMessage);
        self::assertStringContainsString('Invalid severity for advisory-facade-ignition-7-9: critical', $advisoryMessage);
    }

    /** @param list<\PhpUpgradePreflight\Laravel\Catalog\RuleDefinition> $rules */
    private function withRules(LaravelRuleCatalog $catalog, array $rules): LaravelRuleCatalog
    {
        return new LaravelRuleCatalog(
            $catalog->version(),
            $catalog->minimumMajor(),
            $catalog->maximumMajor(),
            $catalog->targets(),
            $catalog->transitions(),
            $rules,
            $catalog->skeletonPatterns()
        );
    }
}
