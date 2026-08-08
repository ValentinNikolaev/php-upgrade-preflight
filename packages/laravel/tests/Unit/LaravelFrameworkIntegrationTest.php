<?php

declare(strict_types=1);

namespace PhpUpgradePreflight\Laravel\Tests\Unit;

use PhpUpgradePreflight\Core\Model\ComposerJson;
use PhpUpgradePreflight\Core\Model\ComposerLock;
use PhpUpgradePreflight\Core\Model\EvidenceLedger;
use PhpUpgradePreflight\Core\Model\FrameworkGuidance;
use PhpUpgradePreflight\Core\Model\ProjectState;
use PhpUpgradePreflight\Core\Model\UpgradeRequest;
use PhpUpgradePreflight\Core\Model\UpgradeTarget;
use PhpUpgradePreflight\Laravel\LaravelFrameworkIntegration;
use PHPUnit\Framework\TestCase;

final class LaravelFrameworkIntegrationTest extends TestCase
{
    public function testItDetectsTheLockedLaravelFrameworkVersion(): void
    {
        $project = new ProjectState(
            __DIR__,
            new ComposerJson(['require' => ['laravel/framework' => '^8.0']]),
            new ComposerLock(['packages' => [['name' => 'laravel/framework', 'version' => 'v8.83.27']]])
        );

        $integration = new LaravelFrameworkIntegration();
        $detection = $integration->detect($project);

        self::assertSame('laravel', $integration->name());
        self::assertTrue($detection->isDetected());
        self::assertSame('v8.83.27', $detection->version());
        self::assertCount(19, iterator_to_array($integration->rules()));
        self::assertSame(['src', 'app', 'bootstrap', 'config', 'database', 'routes', 'tests'], $integration->defaultSourcePaths($project));
        self::assertSame(['laravel'], $integration->packageFamilies('laravel/framework'));
    }

    public function testItClassifiesLaravelIlluminateAndSymfonyPackageFamilies(): void
    {
        $integration = new LaravelFrameworkIntegration();

        self::assertSame(['laravel'], $integration->packageFamilies('LARAVEL/Passport'));
        self::assertSame(['illuminate'], $integration->packageFamilies('illuminate/support'));
        self::assertSame(['symfony'], $integration->packageFamilies('symfony/http-foundation'));
        self::assertSame([], $integration->packageFamilies('vendor/package'));
    }

    public function testItReportsLaravelAsAbsentWithoutComposerMetadata(): void
    {
        $project = new ProjectState(__DIR__, new ComposerJson([]), new ComposerLock([]));

        $detection = (new LaravelFrameworkIntegration())->detect($project);

        self::assertFalse($detection->isDetected());
        self::assertNull($detection->version());
    }

    public function testItDetectsLaravelFromARootConstraintWithoutALockedFrameworkPackage(): void
    {
        $project = new ProjectState(
            __DIR__,
            new ComposerJson(['require' => ['Laravel/Framework' => '^7.0']]),
            new ComposerLock([])
        );

        $detection = (new LaravelFrameworkIntegration())->detect($project);

        self::assertTrue($detection->isDetected());
        self::assertSame('^7.0', $detection->version());
    }

    public function testLockedLaravelVersionTakesPrecedenceOverTheRootConstraint(): void
    {
        $project = new ProjectState(
            __DIR__,
            new ComposerJson(['require' => ['laravel/framework' => '^7.0']]),
            new ComposerLock(['packages' => [['name' => 'laravel/framework', 'version' => 'v7.30.7']]])
        );

        $detection = (new LaravelFrameworkIntegration())->detect($project);

        self::assertTrue($detection->isDetected());
        self::assertSame('v7.30.7', $detection->version());
    }

    public function testItDetectsAModularIlluminateProjectFromRootConstraintsAndLockData(): void
    {
        $project = new ProjectState(
            __DIR__,
            new ComposerJson([
                'require' => [
                    'illuminate/console' => '^7.0',
                    'Illuminate/Support' => '^7.0',
                ],
            ]),
            new ComposerLock([
                'packages' => [
                    ['name' => 'illuminate/support', 'version' => 'v7.30.7'],
                    ['name' => 'illuminate/console', 'version' => 'v7.30.7'],
                ],
            ])
        );

        $detection = (new LaravelFrameworkIntegration())->detect($project);

        self::assertTrue($detection->isDetected());
        self::assertSame('v7.30.7', $detection->version());
    }

    public function testItDoesNotTreatTransitiveIlluminatePackagesAsALaravelProject(): void
    {
        $project = new ProjectState(
            __DIR__,
            new ComposerJson(['require' => ['vendor/package' => '^1.0']]),
            new ComposerLock([
                'packages' => [['name' => 'illuminate/support', 'version' => 'v8.83.27']],
            ])
        );

        $detection = (new LaravelFrameworkIntegration())->detect($project);

        self::assertFalse($detection->isDetected());
        self::assertNull($detection->version());
    }

    public function testItDoesNotClaimASingleIlluminateVersionWhenRootedComponentsDisagree(): void
    {
        $project = new ProjectState(
            __DIR__,
            new ComposerJson([
                'require' => [
                    'illuminate/console' => '^7.0',
                    'illuminate/support' => '^8.0',
                ],
            ]),
            new ComposerLock([])
        );

        $detection = (new LaravelFrameworkIntegration())->detect($project);

        self::assertTrue($detection->isDetected());
        self::assertNull($detection->version());
    }

    public function testItUsesACommonIlluminateRootConstraintWithoutLockData(): void
    {
        $project = new ProjectState(
            __DIR__,
            new ComposerJson([
                'require' => [
                    'illuminate/console' => '^7.0',
                    'illuminate/support' => '^7.0',
                ],
            ]),
            new ComposerLock([])
        );

        $detection = (new LaravelFrameworkIntegration())->detect($project);

        self::assertTrue($detection->isDetected());
        self::assertSame('^7.0', $detection->version());
    }

    public function testItDoesNotMixAPartialIlluminateLockWithRootConstraintsIntoASingleVersion(): void
    {
        $project = new ProjectState(
            __DIR__,
            new ComposerJson([
                'require' => [
                    'illuminate/console' => '^7.0',
                    'illuminate/support' => '^7.0',
                ],
            ]),
            new ComposerLock([
                'packages' => [['name' => 'illuminate/support', 'version' => 'v7.30.7']],
            ])
        );

        $detection = (new LaravelFrameworkIntegration())->detect($project);

        self::assertTrue($detection->isDetected());
        self::assertNull($detection->version());
    }

    public function testItAssessesTheRetainedDirectTransitionAndScopesItsHop(): void
    {
        $project = new ProjectState(
            __DIR__,
            new ComposerJson(['require' => ['laravel/framework' => '^7.0']]),
            new ComposerLock(['packages' => [['name' => 'laravel/framework', 'version' => 'v7.30.7']]])
        );
        $request = new UpgradeRequest(__DIR__, [new UpgradeTarget('laravel/framework', '^9.0')]);
        $evidence = new EvidenceLedger();

        $guidance = (new LaravelFrameworkIntegration())->assessTransition($project, $request, $evidence);

        self::assertNotNull($guidance);
        self::assertSame(FrameworkGuidance::SUPPORTED, $guidance->toArray()['status']);
        self::assertSame(7, $guidance->sourceMajor());
        self::assertSame(9, $guidance->targetMajor());
        self::assertSame([['from_major' => 7, 'to_major' => 9]], $guidance->supportedHopReferences());
        self::assertSame('laravel-7-to-9-direct', $guidance->toArray()['hops'][0]['rule_pack']);
        self::assertCount(1, $evidence->all());
    }

    public function testItDoesNotGuessAnAmbiguousSourceMajor(): void
    {
        $project = new ProjectState(
            __DIR__,
            new ComposerJson([
                'require' => [
                    'illuminate/console' => '^7.0',
                    'illuminate/support' => '^8.0',
                ],
            ]),
            new ComposerLock([])
        );
        $request = new UpgradeRequest(__DIR__, [new UpgradeTarget('illuminate/support', '^9.0')]);

        $guidance = (new LaravelFrameworkIntegration())->assessTransition($project, $request, new EvidenceLedger());

        self::assertNotNull($guidance);
        self::assertSame(FrameworkGuidance::UNSUPPORTED, $guidance->toArray()['status']);
        self::assertNull($guidance->sourceMajor());
        self::assertSame([], $guidance->hops());
        self::assertNotSame([], $guidance->toArray()['uncertainties']);
    }
}
