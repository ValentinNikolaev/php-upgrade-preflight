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
        self::assertTrue($detection->detected);
        self::assertSame('v8.83.27', $detection->version);
        self::assertCount(6, iterator_to_array($integration->rules()));
        self::assertSame(['app', 'bootstrap', 'config', 'database', 'routes', 'tests'], $integration->defaultSourcePaths($project));
    }

    public function testItReportsLaravelAsAbsentWithoutComposerMetadata(): void
    {
        $project = new ProjectState(__DIR__, new ComposerJson([]), new ComposerLock([]));

        $detection = (new LaravelFrameworkIntegration())->detect($project);

        self::assertFalse($detection->detected);
        self::assertNull($detection->version);
    }
}
