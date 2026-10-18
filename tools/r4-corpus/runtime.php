<?php

declare(strict_types=1);

function r4Autoload(): void
{
    $autoload = realpath('/app/vendor/autoload.php');
    if ($autoload === false || !is_file($autoload)) {
        throw new RuntimeException('R4 runtime autoload unavailable');
    }
    require $autoload;
    // A reused vendor tree can contain copied path packages from another head.
    // Prefer this read-only checkout even over an optimized Composer classmap.
    spl_autoload_register(static function (string $class): void {
        foreach (['Core' => 'core', 'Cli' => 'cli', 'Laravel' => 'laravel'] as $namespace => $package) {
            $prefix = 'PhpUpgradePreflight\\' . $namespace . '\\';
            if (str_starts_with($class, $prefix)) {
                $file = '/app/packages/' . $package . '/src/' . str_replace('\\', '/', substr($class, strlen($prefix))) . '.php';
                if (is_file($file)) {
                    require $file;
                }

                return;
            }
        }
    }, true, true);
}

/** @return array<string, string> Relative runtime filenames with their actually loaded source hashes. */
function r4RuntimeAttestation(): array
{
    $files = [];
    foreach ([
        'PhpUpgradePreflight\\Core\\Analysis\\DefaultUpgradeAnalyzer' => 'packages/core/src/Analysis/DefaultUpgradeAnalyzer.php',
        'PhpUpgradePreflight\\Core\\Source\\SourceUsageScanner' => 'packages/core/src/Source/SourceUsageScanner.php',
        'PhpUpgradePreflight\\Core\\Model\\UpgradeReport' => 'packages/core/src/Model/UpgradeReport.php',
    ] as $class => $relative) {
        $loaded = (new ReflectionClass($class))->getFileName();
        $expected = realpath('/app/' . $relative);
        if ($loaded === false || $expected === false || realpath($loaded) !== $expected) {
            throw new RuntimeException('Loaded runtime class differs from the selected checkout.');
        }
        $hash = hash_file('sha256', $loaded);
        if ($hash === false) {
            throw new RuntimeException('Loaded runtime class cannot be hashed.');
        }
        $files[$relative] = $hash;
    }

    return $files;
}

function r4Write(string $path, string $contents): void
{
    $written = file_put_contents($path, $contents);
    if ($written === false || $written !== strlen($contents)) {
        throw new RuntimeException('R4 output write incomplete');
    }
}

function r4Elapsed(?float $started): ?float
{
    return $started === null ? null : ((float) hrtime(true) - $started) / 1e9;
}
