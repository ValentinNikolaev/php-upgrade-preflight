<?php

declare(strict_types=1);

function r4Autoload(): void
{
    $autoload = realpath('/app/vendor/autoload.php');
    if ($autoload === false || !is_file($autoload)) {
        throw new RuntimeException('R4 runtime autoload unavailable');
    }
    require $autoload;
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
