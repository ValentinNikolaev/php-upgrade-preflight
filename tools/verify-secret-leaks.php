<?php

declare(strict_types=1);

require __DIR__ . '/SecretLeakVerifier.php';

$paths = array_values(array_slice($argv, 1));
if ($paths === []) {
    fwrite(STDERR, "Usage: php tools/verify-secret-leaks.php PATH [PATH ...]\n");
    exit(2);
}

try {
    $verifier = PhpUpgradePreflight\Tools\SecretLeakVerifier::fromFixture(
        dirname(__DIR__) . '/tests/fixtures/security/composer-output-with-secrets.json'
    );
    $errors = $verifier->verify($paths);
} catch (Throwable $exception) {
    fwrite(STDERR, 'Secret leak verification failed safely; inspect the verifier configuration.' . PHP_EOL);
    exit(1);
}

if ($errors !== []) {
    foreach ($errors as $error) {
        fwrite(STDERR, 'ERROR: ' . $error . PHP_EOL);
    }

    exit(1);
}

fwrite(STDOUT, sprintf("Secret leak verification passed for %d input path(s).\n", count($paths)));
