<?php

declare(strict_types=1);

namespace PhpUpgradePreflight\Laravel\Tests\Unit\Rules;

use PhpUpgradePreflight\Core\Analysis\FrameworkRuleEngine;
use PhpUpgradePreflight\Core\Model\ComposerJson;
use PhpUpgradePreflight\Core\Model\ComposerLock;
use PhpUpgradePreflight\Core\Model\EvidenceLedger;
use PhpUpgradePreflight\Core\Model\ProjectState;
use PhpUpgradePreflight\Core\Model\UpgradeRequest;
use PhpUpgradePreflight\Core\Model\UpgradeTarget;
use PhpUpgradePreflight\Laravel\LaravelFrameworkIntegration;
use PHPUnit\Framework\TestCase;

final class LaravelUiCompatibilityRulesTest extends TestCase
{
    /**
     * @dataProvider supportedUiProvider
     * @param array<string, string> $requirements
     */
    public function testLaravel9AcceptsThePublishedUi3LineAndUi4(int $source, int $target, ?string $version, array $requirements): void
    {
        [$findings] = $this->evaluate($source, $target, $version, $requirements);

        self::assertSame([], $findings);
    }

    /** @return iterable<string, array{int, int, string|null, array<string, string>}> */
    public function supportedUiProvider(): iterable
    {
        yield 'Crater UI3.4.6 official locked requirements permit Laravel9' => [8, 9, '3.4.6', ['illuminate/console' => '^8.42|^9.0', 'illuminate/filesystem' => '^8.42|^9.0', 'illuminate/support' => '^8.82|^9.0', 'illuminate/validation' => '^8.42|^9.0']];
        yield 'UI3.4.0 published target9 floor' => [8, 9, '3.4.0', ['illuminate/console' => '^8.42|^9.0', 'illuminate/filesystem' => '^8.42|^9.0', 'illuminate/support' => '^8.42|^9.0', 'illuminate/validation' => '^8.42|^9.0']];
        yield 'UI3.4.6 fallback when locked requirements are missing' => [8, 9, '3.4.6', []];
        yield 'UI4 target9 support remains accepted' => [8, 9, '4.0.0', ['illuminate/support' => '^9.0']];
        yield 'UI absence does not require installation' => [8, 9, null, []];
        yield 'Existing Laravel8 UI3 guidance is preserved' => [7, 8, '3.3.0', ['illuminate/support' => '^8.42']];
    }

    /**
     * @dataProvider unsupportedUiProvider
     * @param array<string, string> $requirements
     */
    public function testUnsupportedUiLinesKeepReviewFindings(int $source, int $target, string $version, array $requirements): void
    {
        [$findings, $evidence] = $this->evaluate($source, $target, $version, $requirements);

        self::assertCount(1, $findings);
        self::assertSame([['from_major' => $target - 1, 'to_major' => $target]], $findings[0]->appliesToHops());
        if ($target === 9) {
            self::assertStringContainsString('^3.4|^4.0', $findings[0]->summary());
            $guidance = array_values(array_filter($evidence->all(), static fn ($item): bool => $item->evidenceClass() === 'E4' && ($item->context()['package'] ?? null) === 'laravel/ui'));
            self::assertCount(1, $guidance);
            self::assertContains('https://github.com/laravel/ui/blob/b3e804559bf3973ecca160a4ae1068e6c7c167c6/composer.json', $guidance[0]->context()['sources']);
            self::assertContains($guidance[0]->id(), $findings[0]->evidence());
        }
    }

    /** @return iterable<string, array{int, int, string, array<string, string>}> */
    public function unsupportedUiProvider(): iterable
    {
        yield 'UI3.3.0 official requirements exclude Laravel9' => [8, 9, '3.3.0', ['illuminate/console' => '^8.42', 'illuminate/filesystem' => '^8.42', 'illuminate/support' => '^8.42', 'illuminate/validation' => '^8.42']];
        yield 'UI3.2.1 earlier line excludes Laravel9' => [8, 9, '3.2.1', ['illuminate/support' => '^8.0']];
        yield 'Immediately below reviewed UI3.4 floor' => [8, 9, '3.3.99', []];
        yield 'Unreviewed future UI major' => [8, 9, '5.0.0', []];
        yield 'UI3.4.6 still needs upgrade for Laravel10' => [9, 10, '3.4.6', ['illuminate/support' => '^8.82|^9.0']];
    }

    public function testRootOnlyConstraintsBelowTheUi3FloorAreDistinctFromRootRangesThatPermitAnUpdate(): void
    {
        [$excluded] = $this->evaluate(8, 9, null, [], '>=3.0 <3.4');
        [$permitted] = $this->evaluate(8, 9, null, [], '^3.0');

        self::assertCount(1, $excluded);
        self::assertSame([], $permitted);
    }

    /**
     * @param array<string, string> $requirements
     * @return array{list<\PhpUpgradePreflight\Core\Model\CompatibilityFinding>, EvidenceLedger}
     */
    private function evaluate(int $source, int $target, ?string $version, array $requirements, ?string $rootConstraint = null): array
    {
        $root = ['laravel/framework' => '^' . $source . '.0'];
        $packages = [['name' => 'laravel/framework', 'version' => $source . '.0.0']];
        if ($version !== null) {
            $root['laravel/ui'] = '^' . $version;
            $packages[] = ['name' => 'laravel/ui', 'version' => $version, 'require' => $requirements];
        } elseif ($rootConstraint !== null) {
            $root['laravel/ui'] = $rootConstraint;
        }
        $project = new ProjectState(__DIR__, new ComposerJson(['require' => $root]), new ComposerLock(['packages' => $packages]));
        $request = new UpgradeRequest(__DIR__, [new UpgradeTarget('laravel/framework', '^' . $target . '.0')], null, '8.3');
        $integration = new LaravelFrameworkIntegration();
        $engine = new FrameworkRuleEngine([$integration]);
        $evidence = new EvidenceLedger();
        $guidance = $engine->assessTransitions([$integration], $project, $request, $evidence);
        $findings = $engine->evaluate([$integration], $project, $request, $evidence, [], $guidance, '2.8.0');

        return [array_values(array_filter($findings, static fn ($finding): bool => str_starts_with($finding->summary(), 'laravel/ui '))), $evidence];
    }
}
