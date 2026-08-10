<?php

declare(strict_types=1);

namespace PhpUpgradePreflight\Laravel\Tests\Integration;

use PhpUpgradePreflight\Core\Analysis\FrameworkRuleEngine;
use PhpUpgradePreflight\Core\Composer\ProjectStateBuilder;
use PhpUpgradePreflight\Core\Model\EvidenceLedger;
use PhpUpgradePreflight\Core\Model\FrameworkGuidance;
use PhpUpgradePreflight\Core\Model\UpgradeRequest;
use PhpUpgradePreflight\Core\Model\UpgradeTarget;
use PhpUpgradePreflight\Core\Source\SourceUsageScanner;
use PhpUpgradePreflight\Laravel\LaravelFrameworkIntegration;
use PHPUnit\Framework\TestCase;

final class LaterLaravelUpgradeFixtureTest extends TestCase
{
    /** @dataProvider transitionFixtureProvider */
    public function testLaterTransitionFixturesExerciseTheirApprovedRulePacks(
        string $fixture,
        string $target,
        string $targetPhp,
        array $expectedSummaries,
        array $unexpectedSummaries
    ): void {
        $path = $this->fixturePath($fixture);
        $load = (new ProjectStateBuilder())->load($path);
        self::assertTrue($load->succeeded());
        $project = $load->project();
        $request = new UpgradeRequest($path, [new UpgradeTarget('laravel/framework', $target)], null, $targetPhp);
        $integration = new LaravelFrameworkIntegration();
        $engine = new FrameworkRuleEngine([$integration]);
        $evidence = new EvidenceLedger();
        $uncertainties = [];
        $usages = (new SourceUsageScanner())->scan(
            $project,
            $integration->defaultSourcePaths($project),
            $evidence,
            $uncertainties,
            true
        );

        $guidance = $engine->assessTransitions([$integration], $project, $request, $evidence);
        self::assertCount(1, $guidance);
        self::assertSame(FrameworkGuidance::SUPPORTED, $guidance[0]->status());
        self::assertCount(1, $guidance[0]->hops());

        $composerVersion = $fixture === 'laravel-9-to-10' ? '2.1.14' : '2.8.12';
        $findings = $engine->evaluate([$integration], $project, $request, $evidence, $usages, $guidance, $composerVersion);
        $summaries = array_map(static fn ($finding): string => $finding->summary(), $findings);
        foreach ($expectedSummaries as $expected) {
            self::assertTrue($this->contains($summaries, $expected), $expected . "\n" . implode("\n", $summaries));
        }
        foreach ($unexpectedSummaries as $unexpected) {
            self::assertFalse($this->contains($summaries, $unexpected), $unexpected . "\n" . implode("\n", $summaries));
        }
    }

    /** @return iterable<string, array{string, string, string, list<string>, list<string>}> */
    public function transitionFixtureProvider(): iterable
    {
        yield 'Laravel 8 to 9' => [
            'laravel-8-to-9',
            '^9.0',
            '8.0.2',
            ['root laravel/framework constraint', 'facade/ignition', 'league/flysystem-ftp', 'league/flysystem-sftp-v3', 'nunomaduro/collision', 'pusher/pusher-php-server', 'phpunit/phpunit'],
            [],
        ];
        yield 'Laravel 9 to 10' => [
            'laravel-9-to-10',
            '^10.0',
            '8.1.0',
            ['root laravel/framework constraint', 'Composer `2.1.14`', 'doctrine/dbal', 'laravel/sanctum', 'spatie/laravel-ignition', 'nunomaduro/collision', 'phpunit/phpunit', 'Replace 3 detected'],
            [],
        ];
        yield 'Laravel 10 to 11' => [
            'laravel-10-to-11',
            '^11.0',
            '8.2.0',
            ['root laravel/framework constraint', 'curl is `absent`', 'laravel/cashier', 'laravel/passport', 'laravel/sanctum', 'laravel/spark-stripe', 'laravel/telescope', 'nunomaduro/collision', 'phpunit/phpunit', 'Publish the laravel/cashier migrations', 'Publish the laravel/passport migrations', 'Publish the laravel/sanctum migrations', 'Publish the laravel/spark-stripe migrations', 'Publish the laravel/telescope migrations', 'remove doctrine/dbal'],
            ['skeleton-managed integration locations'],
        ];
        yield 'Laravel 11 to 12' => [
            'laravel-11-to-12',
            '^12.0',
            '8.2.0',
            ['root laravel/framework constraint', 'nesbot/carbon 2.72.6 is outside the encoded Laravel 12 review range `^3.0`', 'nunomaduro/collision', 'pestphp/pest', 'phpunit/phpunit'],
            [],
        ];
    }

    /** @param list<string> $haystack */
    private function contains(array $haystack, string $needle): bool
    {
        foreach ($haystack as $item) {
            if (stripos($item, $needle) !== false) {
                return true;
            }
        }

        return false;
    }

    private function fixturePath(string $fixture): string
    {
        return dirname(__DIR__, 4)
            . DIRECTORY_SEPARATOR . 'tests'
            . DIRECTORY_SEPARATOR . 'fixtures'
            . DIRECTORY_SEPARATOR . 'projects'
            . DIRECTORY_SEPARATOR . $fixture;
    }
}
