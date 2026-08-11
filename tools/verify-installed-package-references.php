<?php

declare(strict_types=1);

require __DIR__ . '/InstalledPackageReferenceVerifier.php';

use PhpUpgradePreflight\Tools\InstalledPackageReferenceVerifier;

if (count($argv) !== 6) {
    fwrite(
        STDERR,
        "Usage: php tools/verify-installed-package-references.php VERSION LOCK CORE_REF CLI_REF LARAVEL_REF\n"
    );
    exit(2);
}

$references = [
    'php-upgrade-preflight/core' => $argv[3],
    'php-upgrade-preflight/cli' => $argv[4],
    'php-upgrade-preflight/laravel' => $argv[5],
];

try {
    $errors = (new InstalledPackageReferenceVerifier())->verify($argv[2], $argv[1], $references);
} catch (Throwable $exception) {
    fwrite(STDERR, 'ERROR: ' . $exception->getMessage() . "\n");
    exit(1);
}

if ($errors !== []) {
    foreach ($errors as $error) {
        fwrite(STDERR, 'ERROR: ' . $error . "\n");
    }
    exit(1);
}

fwrite(STDOUT, "Published package references match the verified signed tags.\n");
