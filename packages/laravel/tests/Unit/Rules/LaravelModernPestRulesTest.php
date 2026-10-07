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

final class LaravelModernPestRulesTest extends TestCase
{
    /** @dataProvider versionProvider */
    public function testReviewedPestBranchesKeepSupportedAlternatives(int $source, int $target, string $version, bool $warn): void
    {
        $project = new ProjectState(__DIR__, new ComposerJson([
            'require' => ['laravel/framework' => '^' . $source . '.0'],
            'require-dev' => ['pestphp/pest' => '^' . $version],
        ]), new ComposerLock(['packages' => [
            ['name' => 'laravel/framework', 'version' => $source . '.0.0'],
        ], 'packages-dev' => [
            ['name' => 'pestphp/pest', 'version' => $version],
        ]]));
        $request = new UpgradeRequest(__DIR__, [new UpgradeTarget('laravel/framework', '^' . $target . '.0')], null, '8.5.11');
        $integration = new LaravelFrameworkIntegration();
        $engine = new FrameworkRuleEngine([$integration]);
        $evidence = new EvidenceLedger();
        $guidance = $engine->assessTransitions([$integration], $project, $request, $evidence);
        $findings = array_values(array_filter(
            $engine->evaluate([$integration], $project, $request, $evidence, [], $guidance, '2.10.3'),
            static fn ($finding): bool => str_contains($finding->summary(), 'pestphp/pest')
        ));

        self::assertCount($warn ? 1 : 0, $findings);
        if ($warn) {
            self::assertSame([['from_major' => $source, 'to_major' => $target]], $findings[0]->appliesToHops());
            self::assertNotEmpty($findings[0]->evidence());
        }
    }

    /** @return iterable<string, array{int, int, string, bool}> */
    public function versionProvider(): iterable
    {
        yield 'Laravel12 retains Pest3' => [11, 12, '3.8.2', false];
        yield 'Laravel12 supports Pest4' => [11, 12, '4.4.1', false];
        yield 'Laravel13 retains Pest4' => [12, 13, '4.4.1', false];
        yield 'Laravel13 supports Pest5' => [12, 13, '5.0.1', false];
        yield 'Laravel13 supports current Pest5' => [12, 13, '5.3.1', false];
        yield 'Laravel12 older Pest needs review' => [11, 12, '2.36.0', true];
        yield 'Laravel13 older Pest needs review' => [12, 13, '3.8.2', true];
        yield 'Laravel13 future Pest needs review' => [12, 13, '6.0.0', true];
    }
}
