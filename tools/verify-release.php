<?php

declare(strict_types=1);

require __DIR__ . '/ReleaseVerifier.php';

$version = $argv[1] ?? '';

try {
    $errors = (new PhpUpgradePreflight\Tools\ReleaseVerifier(dirname(__DIR__)))->verify($version);
} catch (InvalidArgumentException) {
    fwrite(STDERR, "Usage: php tools/verify-release.php 0.1.PATCH\n");
    exit(2);
}

if ($errors !== []) {
    foreach ($errors as $error) {
        fwrite(STDERR, 'ERROR: ' . $error . "\n");
    }

    exit(1);
}

fwrite(STDOUT, sprintf(
    "Release metadata is consistent for v%s (%s package line).\n",
    $version,
    implode('.', array_slice(explode('.', $version), 0, 2))
));
