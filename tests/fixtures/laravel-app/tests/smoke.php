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

$root = getenv('GITHUB_WORKSPACE') ?: dirname(__DIR__, 4);
$project = $root . '/tests/fixtures/path-repository/project';
$inputs = [$project . '/composer.json', $project . '/composer.lock'];
$before = array_map(static fn (string $path): string|false => hash_file('sha256', $path), $inputs);
assertTrue(!in_array(false, $before, true), 'offline project inputs can be fingerprinted');

$status = $kernel->call('upgrade:analyze', [
    '--path' => $project,
    '--target' => ['fixture/dependency:^2.0'],
    '--from-php' => '8.0.0',
    '--target-php' => '8.5.12',
    '--composer-mode' => 'restricted',
    '--format' => 'json',
]);
assertSame(0, $status, 'Artisan completes an offline upgrade analysis');

$report = json_decode($kernel->output(), true, 512, JSON_THROW_ON_ERROR);
assertTrue(($report['request_summary']['target_php'] ?? null) === '8.5.12', 'JSON preserves the current PHP target');
assertTrue(($report['resolution']['status'] ?? null) === 'feasible_with_changes', 'the solver proves the offline upgrade');
assertTrue(($report['transition']['package_changes'][0]['to_version'] ?? null) === '2.0.0', 'the report includes the candidate package');
assertTrue(($report['resolution']['scenarios'] ?? []) !== [], 'the analysis executes Composer scenarios');
foreach ($report['resolution']['scenarios'] as $scenario) {
    assertTrue(($scenario['succeeded'] ?? false) === true, 'each offline Composer scenario succeeds');
}
assertTrue(
    $before === array_map(static fn (string $path): string|false => hash_file('sha256', $path), $inputs),
    'Artisan preserves the analyzed Composer inputs'
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
