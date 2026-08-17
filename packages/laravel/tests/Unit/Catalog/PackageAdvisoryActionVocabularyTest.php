<?php

declare(strict_types=1);

namespace PhpUpgradePreflight\Laravel\Tests\Unit\Catalog;

use PhpUpgradePreflight\Core\Model\ComposerJson;
use PhpUpgradePreflight\Core\Model\ComposerLock;
use PhpUpgradePreflight\Core\Model\EvidenceLedger;
use PhpUpgradePreflight\Core\Model\ProjectState;
use PhpUpgradePreflight\Core\Model\UpgradeRequest;
use PhpUpgradePreflight\Core\Model\UpgradeTarget;
use PhpUpgradePreflight\Laravel\Catalog\LaravelRuleCatalog;
use PhpUpgradePreflight\Laravel\Catalog\LaravelRuleCatalogValidator;
use PhpUpgradePreflight\Laravel\Catalog\PackageAdvisoryDefinition;
use PhpUpgradePreflight\Laravel\Catalog\RuleApplicability;
use PhpUpgradePreflight\Laravel\Rules\TargetedPackageAdvisoryRule;
use PHPUnit\Framework\TestCase;

/**
 * The advisory action vocabulary is declared once and read by the catalog validator,
 * the expected-package binding and the rendered finding summary. These tests fail if a
 * future action is added to only some of those consumers.
 */
final class PackageAdvisoryActionVocabularyTest extends TestCase
{
    private const TARGET_MAJOR = 9;

    public function testEveryDeclaredActionConstantIsRegisteredInTheVocabulary(): void
    {
        $declared = [];
        foreach ((new \ReflectionClass(PackageAdvisoryDefinition::class))->getConstants() as $value) {
            if (is_string($value)) {
                $declared[] = $value;
            }
        }
        sort($declared, SORT_STRING);

        $registered = PackageAdvisoryDefinition::actions();
        sort($registered, SORT_STRING);

        self::assertNotSame([], $registered);
        self::assertSame($declared, $registered);
    }

    public function testEveryActionResolvesInTheCatalogValidatorAndRendersASummary(): void
    {
        $catalog = LaravelRuleCatalog::v0_2();
        $validator = new LaravelRuleCatalogValidator();

        foreach (PackageAdvisoryDefinition::actions() as $action) {
            $definition = $this->advisoryFor($action);
            $summary = $definition->summary(self::TARGET_MAJOR);
            $errors = implode("\n", $validator->validate($this->withExtraRule($catalog, $definition)));

            self::assertTrue(PackageAdvisoryDefinition::isSupportedAction($action), $action);
            self::assertStringNotContainsString('Unsupported package advisory action', $errors, $action);
            self::assertStringNotContainsString(
                sprintf('Package advisory %s uses action', $definition->key()),
                $errors,
                $action
            );
            self::assertStringNotContainsString('%', $summary, $action);
            self::assertStringContainsString((string) self::TARGET_MAJOR, $summary, $action);
            self::assertStringContainsString($definition->package(), $summary, $action);
        }
    }

    public function testEveryActionRendersTheSameSummaryThroughTheWiredAdvisoryRule(): void
    {
        foreach (PackageAdvisoryDefinition::actions() as $action) {
            $definition = $this->advisoryFor($action);
            $evidence = new EvidenceLedger();

            $finding = (new TargetedPackageAdvisoryRule($definition))->evaluate(
                $this->projectRequiring($definition->package()),
                $this->request(),
                $evidence
            );

            self::assertNotNull($finding, $action);
            self::assertSame($definition->summary(self::TARGET_MAJOR), $finding->summary(), $action);
            self::assertSame($definition->severity(), $finding->severity(), $action);
            self::assertCount(2, $finding->evidence(), $action);
            $evidence->validateReferences($finding->evidence());
        }
    }

    public function testAnUnregisteredActionIsRejectedInsteadOfResolvingSilently(): void
    {
        $definition = $this->advisory('not_a_registered_action', 'vendor/package');

        self::assertFalse(PackageAdvisoryDefinition::isSupportedAction('not_a_registered_action'));
        self::assertNull($definition->expectedPackage());
        self::assertTrue($definition->isExclusivePackageAdvice());
        self::assertStringContainsString(
            'Unsupported package advisory action for vocabulary-not_a_registered_action: not_a_registered_action.',
            implode("\n", (new LaravelRuleCatalogValidator())->validate(
                $this->withExtraRule(LaravelRuleCatalog::v0_2(), $definition)
            ))
        );

        $this->expectException(\LogicException::class);
        $this->expectExceptionMessage('Unsupported Laravel package advisory action: not_a_registered_action.');

        $definition->summary(self::TARGET_MAJOR);
    }

    private function advisoryFor(string $action): PackageAdvisoryDefinition
    {
        $probe = $this->advisory($action, 'vendor/package');

        return $this->advisory($action, $probe->expectedPackage() ?? 'vendor/package');
    }

    private function advisory(string $action, string $package): PackageAdvisoryDefinition
    {
        return new PackageAdvisoryDefinition(
            'vocabulary-' . $action,
            $package,
            new RuleApplicability(7, self::TARGET_MAJOR),
            $action,
            'medium',
            ['https://laravel.com/docs/9.x/upgrade']
        );
    }

    private function withExtraRule(LaravelRuleCatalog $catalog, PackageAdvisoryDefinition $rule): LaravelRuleCatalog
    {
        $rules = $catalog->rules();
        $rules[] = $rule;

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

    private function projectRequiring(string $package): ProjectState
    {
        return new ProjectState(
            __DIR__,
            new ComposerJson(['require' => ['laravel/framework' => '^7.0', $package => '^1.0']]),
            new ComposerLock(['packages' => [
                ['name' => 'laravel/framework', 'version' => 'v7.30.4'],
                ['name' => $package, 'version' => '1.0.0'],
            ]])
        );
    }

    private function request(): UpgradeRequest
    {
        return new UpgradeRequest(
            __DIR__,
            [new UpgradeTarget('laravel/framework', '^' . self::TARGET_MAJOR . '.0')]
        );
    }
}
