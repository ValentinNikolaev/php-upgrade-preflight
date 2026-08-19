<?php

declare(strict_types=1);

require __DIR__ . '/ReleaseVerifier.php';

$version = $argv[1] ?? '';

if (!preg_match('/^(0|[1-9]\d*)\.(0|[1-9]\d*)\.(0|[1-9]\d*)$/', $version)) {
    fwrite(STDERR, "Usage: php tools/verify-release.php 0.3.PATCH\n");
    exit(2);
}

$materializer = __DIR__ . '/materialize-release-wikis.php';
$command = sprintf(
    '%s %s --check',
    escapeshellarg(PHP_BINARY),
    escapeshellarg($materializer)
);
passthru($command, $wikiExitCode);
if ($wikiExitCode !== 0) {
    fwrite(STDERR, "ERROR: release Wiki materialization check failed; release authorization is blocked.\n");
    exit(1);
}

try {
    $errors = (new PhpUpgradePreflight\Tools\ReleaseVerifier(dirname(__DIR__)))->verify($version);
} catch (InvalidArgumentException) {
    fwrite(STDERR, "Usage: php tools/verify-release.php 0.3.PATCH\n");
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
