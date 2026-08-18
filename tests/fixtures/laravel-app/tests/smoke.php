<?php

declare(strict_types=1);

use Illuminate\Contracts\Console\Kernel;
use Illuminate\Foundation\PackageManifest;
use PhpUpgradePreflight\Core\Contracts\UpgradeAnalyzer;
use PhpUpgradePreflight\Core\Analysis\DefaultUpgradeAnalyzer;
use PhpUpgradePreflight\Laravel\Commands\AnalyzeUpgradeCommand;
use PhpUpgradePreflight\Laravel\UpgradePreflightServiceProvider;

require __DIR__.'/../vendor/autoload.php';

$app = require __DIR__.'/../bootstrap/app.php';
$kernel = $app->make(Kernel::class);
$kernel->bootstrap();

assertTrue(
    in_array(UpgradePreflightServiceProvider::class, app(PackageManifest::class)->providers(), true),
    'package discovery exposes the Laravel service provider'
);

assertTrue(
    $app->bound(UpgradeAnalyzer::class),
    'service provider registers the upgrade analyzer binding'
);

assertTrue(
    $app->make(UpgradeAnalyzer::class) instanceof DefaultUpgradeAnalyzer,
    'upgrade analyzer binding resolves'
);

assertTrue(
    $app->make(AnalyzeUpgradeCommand::class) instanceof AnalyzeUpgradeCommand,
    'Artisan command can be constructed through the Laravel container'
);

$status = $kernel->call('upgrade:analyze', []);

assertSame(
    2,
    $status,
    'Artisan command starts and returns validation failure when no target is supplied'
);

assertContains(
    'At least one --target=package:constraint, --target-php=VERSION, or --target-platform-profile=PATH option is required.',
    $kernel->output(),
    'Artisan command emits its startup validation message'
);

echo "Laravel fixture smoke test passed.\n";

function assertTrue(bool $condition, string $message): void
{
    if (! $condition) {
        fail($message);
    }
}

function assertSame(int $expected, int $actual, string $message): void
{
    if ($expected !== $actual) {
        fail(sprintf('%s Expected %d, got %d.', $message, $expected, $actual));
    }
}

function assertContains(string $needle, string $haystack, string $message): void
{
    if (! str_contains($haystack, $needle)) {
        fail($message);
    }
}

function fail(string $message): void
{
    fwrite(STDERR, "Smoke test failed: {$message}\n");
    exit(1);
}
