<?php

declare(strict_types=1);

require __DIR__ . '/DistributionPayloadVerifier.php';

use PhpUpgradePreflight\Tools\DistributionPayloadVerifier;

$expected = $argv[1] ?? '';
$actual = $argv[2] ?? '';

if ($expected === '' || $actual === '') {
    fwrite(STDERR, "Usage: php tools/verify-distribution-payload.php EXPECTED_DIRECTORY ACTUAL_DIRECTORY\n");
    exit(2);
}

try {
    $errors = (new DistributionPayloadVerifier())->verify($expected, $actual);
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

fwrite(STDOUT, "Distribution payloads match.\n");
