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

final class LaravelModernPhpunitRulesTest extends TestCase
{
    /** @dataProvider supportedVersionProvider */
    public function testFrameworkSupportedTestToolsAndExistingRecommendationsDoNotGenerateWarnings(int $source, int $target, ?string $version): void
    {
        [$findings] = $this->evaluate($source, $target, $version);

        self::assertSame([], $findings);
    }

    /** @return iterable<string, array{int, int, string|null}> */
    public function supportedVersionProvider(): iterable
    {
        yield 'Laravel11 retains PHPUnit10 support' => [10, 11, '10.5.35'];
        yield 'BookStack v24.10.3 retains PHPUnit10.5.38 at the Laravel11 hop' => [10, 11, '10.5.38'];
        yield 'Laravel11 supports PHPUnit12' => [10, 11, '12.0.1'];
        yield 'Laravel11 retains existing skeleton recommendation' => [10, 11, '11.0.1'];
        yield 'Laravel12 retains PHPUnit10 support' => [11, 12, '10.5.35'];
        yield 'Laravel12 supports PHPUnit12' => [11, 12, '12.0.1'];
        yield 'Laravel12 retains guide recommendation' => [11, 12, '11.0.0'];
        yield 'Laravel13 retains PHPUnit11 support' => [12, 13, '11.5.50'];
        yield 'Pinned BookStack Laravel12 corpus retains PHPUnit11.5.56 at the Laravel13 hop' => [12, 13, '11.5.56'];
        yield 'Laravel13 supports PHPUnit13' => [12, 13, '13.0.3'];
        yield 'Laravel13 retains guide recommendation' => [12, 13, '12.0.0'];
        yield 'Laravel11 does not require installing PHPUnit' => [10, 11, null];
        yield 'Laravel12 does not require installing PHPUnit' => [11, 12, null];
        yield 'Laravel13 does not require installing PHPUnit' => [12, 13, null];
    }

    /** @dataProvider unsupportedVersionProvider */
    public function testVersionsOutsideReviewedRangesKeepPinnedEvidence(int $source, int $target, string $version, string $manifestCommit): void
    {
        [$findings, $evidence] = $this->evaluate($source, $target, $version);

        self::assertCount(1, $findings);
        self::assertSame([['from_major' => $target - 1, 'to_major' => $target]], $findings[0]->appliesToHops());
        $guidance = array_values(array_filter($evidence->all(), static fn ($item): bool => $item->evidenceClass() === 'E4' && ($item->context()['package'] ?? null) === 'phpunit/phpunit'));
        self::assertCount(1, $guidance);
        self::assertContains('https://github.com/laravel/framework/blob/' . $manifestCommit . '/composer.json', $guidance[0]->context()['sources']);
        self::assertContains($guidance[0]->id(), $findings[0]->evidence());
    }

    /** @return iterable<string, array{int, int, string, string}> */
    public function unsupportedVersionProvider(): iterable
    {
        yield 'Laravel11 old major' => [10, 11, '9.6.0', 'e353708c960ec5066d76b0da4b81c8a68d183b93'];
        yield 'Laravel11 PHPUnit10 patch floor' => [10, 11, '10.5.34', 'e353708c960ec5066d76b0da4b81c8a68d183b93'];
        yield 'Laravel12 old major' => [11, 12, '9.6.0', '5260836df1b953a558d9b810880f20db15568c01'];
        yield 'Laravel12 PHPUnit10 patch floor' => [11, 12, '10.5.34', '5260836df1b953a558d9b810880f20db15568c01'];
        yield 'Laravel13 old major' => [12, 13, '10.5.35', '8df67f9d176d1d0375a866d8c6780be95ce0336e'];
        yield 'Laravel13 PHPUnit11 patch floor' => [12, 13, '11.5.49', '8df67f9d176d1d0375a866d8c6780be95ce0336e'];
        yield 'Lychee v6.10.0 PHPUnit11.5.42 still needs review at the Laravel13 hop' => [12, 13, '11.5.42', '8df67f9d176d1d0375a866d8c6780be95ce0336e'];
    }

    public function testMultiMajorWarningsStartOnlyAtTheFirstUnsupportedTestingRange(): void
    {
        [$findings] = $this->evaluate(10, 13, '10.5.35');

        self::assertCount(1, $findings);
        self::assertSame([['from_major' => 12, 'to_major' => 13]], $findings[0]->appliesToHops());
    }

    /** @return array{list<\PhpUpgradePreflight\Core\Model\CompatibilityFinding>, EvidenceLedger} */
    private function evaluate(int $source, int $target, ?string $version): array
    {
        $packages = [['name' => 'laravel/framework', 'version' => $source . '.0.0']];
        $requireDev = [];
        if ($version !== null) {
            $packages[] = ['name' => 'phpunit/phpunit', 'version' => $version];
            $requireDev['phpunit/phpunit'] = '^' . $version;
        }
        $project = new ProjectState(__DIR__, new ComposerJson(['require' => ['laravel/framework' => '^' . $source . '.0'], 'require-dev' => $requireDev]), new ComposerLock(['packages' => $packages]));
        $request = new UpgradeRequest(__DIR__, [new UpgradeTarget('laravel/framework', '^' . $target . '.0')], null, '8.3');
        $integration = new LaravelFrameworkIntegration();
        $engine = new FrameworkRuleEngine([$integration]);
        $evidence = new EvidenceLedger();
        $guidance = $engine->assessTransitions([$integration], $project, $request, $evidence);
        $findings = $engine->evaluate([$integration], $project, $request, $evidence, [], $guidance, '2.8.0');

        return [array_values(array_filter($findings, static fn ($finding): bool => strpos($finding->summary(), 'phpunit/phpunit') !== false)), $evidence];
    }
}
