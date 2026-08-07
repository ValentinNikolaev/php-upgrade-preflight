<?php

declare(strict_types=1);

namespace PhpUpgradePreflight\Laravel\Tests\Unit;

use PhpUpgradePreflight\Core\Model\ComposerJson;
use PhpUpgradePreflight\Core\Model\ComposerLock;
use PhpUpgradePreflight\Core\Model\ProjectState;
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
        self::assertCount(6, iterator_to_array($integration->rules()));
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
}
