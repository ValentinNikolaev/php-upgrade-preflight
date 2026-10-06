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

final class LaravelCompletionPackageRulesTest extends TestCase
{
    /** @dataProvider packageGuidanceProvider */
    public function testReviewedPackageGuidanceIsActionableAndScoped(
        int $source,
        int $target,
        string $package,
        string $version,
        string $expected
    ): void {
        [$findings, $ledger] = $this->evaluate($source, $target, $package, $version);
        $matching = array_values(array_filter($findings, static fn ($finding): bool => strpos($finding->summary(), $expected) !== false));

        self::assertCount(1, $matching);
        self::assertSame([['from_major' => $target - 1, 'to_major' => $target]], $matching[0]->appliesToHops());
        $referenced = array_filter($ledger->all(), static fn ($item): bool => in_array($item->id(), $matching[0]->evidence(), true));
        self::assertCount(2, $referenced);
        self::assertNotEmpty(array_filter($referenced, static fn ($item): bool => $item->evidenceClass() === 'E4'));
    }

    /** @return iterable<string, array{int, int, string, string, string}> */
    public function packageGuidanceProvider(): iterable
    {
        yield 'Guzzle below both supported lines' => [7, 8, 'guzzlehttp/guzzle', '6.5.4', '^6.5.5|^7.0.1'];
        yield 'Socialite first-party guide' => [7, 8, 'laravel/socialite', '4.4.1', '^5.0'];
        yield 'Guzzle Symfony mailer transition' => [8, 9, 'guzzlehttp/guzzle', '6.5.5', '^7.2'];
        yield 'Old Pusher driver' => [8, 9, 'pusher/pusher-php-server', '4.1.0', '^5.0|^6.0|^7.0'];
        yield 'Nexmo channel replacement' => [8, 9, 'laravel/nexmo-notification-channel', '2.5.1', 'laravel/vonage-notification-channel:^3.0'];
        yield 'Retained direct Nexmo replacement' => [7, 9, 'laravel/nexmo-notification-channel', '2.5.1', 'laravel/vonage-notification-channel:^3.0'];
        yield 'Postmark transport replacement' => [8, 9, 'wildbit/swiftmailer-postmark', '3.0.0', 'symfony/postmark-mailer'];
        yield 'Spatie once conflicts with framework helper' => [10, 11, 'spatie/once', '3.1.0', 'Remove spatie/once'];
    }

    /** @dataProvider compatiblePackageProvider */
    public function testSupportedAndAbsentPackagesDoNotAcquireUpgradeWarnings(int $source, int $target, string $package, ?string $version): void
    {
        [$findings] = $this->evaluate($source, $target, $package, $version);
        foreach ($findings as $finding) {
            self::assertStringNotContainsString($package, $finding->summary());
        }
    }

    /** @return iterable<string, array{int, int, string, string|null}> */
    public function compatiblePackageProvider(): iterable
    {
        yield 'Guzzle6 remains permitted on Laravel8' => [7, 8, 'guzzlehttp/guzzle', '6.5.5'];
        yield 'Socialite5 permitted' => [7, 8, 'laravel/socialite', '5.0.0'];
        yield 'Guzzle7 permitted on Laravel9' => [8, 9, 'guzzlehttp/guzzle', '7.2.0'];
        yield 'Guide Pusher5 recommendation retained' => [8, 9, 'pusher/pusher-php-server', '5.0.0'];
        yield 'Framework Pusher6 permitted' => [8, 9, 'pusher/pusher-php-server', '6.0.0'];
        yield 'Framework Pusher7 permitted' => [8, 9, 'pusher/pusher-php-server', '7.0.0'];
        yield 'PHPUnit9 remains permitted on Laravel10' => [9, 10, 'phpunit/phpunit', '9.5.8'];
        yield 'PHPUnit10 supported patch' => [9, 10, 'phpunit/phpunit', '10.0.7'];
        yield 'Collision6 remains permitted on Laravel10' => [9, 10, 'nunomaduro/collision', '6.4.0'];
        yield 'Collision7 permitted' => [9, 10, 'nunomaduro/collision', '7.0.0'];
        yield 'Nexmo absent' => [8, 9, 'laravel/nexmo-notification-channel', null];
        yield 'Postmark absent' => [8, 9, 'wildbit/swiftmailer-postmark', null];
        yield 'Once absent' => [10, 11, 'spatie/once', null];
        yield 'Once before framework helper' => [9, 10, 'spatie/once', '3.1.0'];
    }

    /** @return array{list<\PhpUpgradePreflight\Core\Model\CompatibilityFinding>, EvidenceLedger} */
    private function evaluate(int $source, int $target, string $package, ?string $version): array
    {
        $requirements = ['laravel/framework' => '^' . $source . '.0'];
        $packages = [['name' => 'laravel/framework', 'version' => $source . '.0.0']];
        if ($version !== null) {
            $requirements[$package] = '^' . $version;
            $packages[] = ['name' => $package, 'version' => $version];
        }
        $project = new ProjectState(__DIR__, new ComposerJson(['require' => $requirements]), new ComposerLock(['packages' => $packages]));
        $request = new UpgradeRequest(__DIR__, [new UpgradeTarget('laravel/framework', '^' . $target . '.0')], null, '8.3');
        $integration = new LaravelFrameworkIntegration();
        $engine = new FrameworkRuleEngine([$integration]);
        $ledger = new EvidenceLedger();
        $guidance = $engine->assessTransitions([$integration], $project, $request, $ledger);

        return [$engine->evaluate([$integration], $project, $request, $ledger, [], $guidance, '2.8.0'), $ledger];
    }
}
