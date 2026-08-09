<?php

declare(strict_types=1);

$fixturePath = dirname(__DIR__) . '/tests/fixtures/security/composer-output-with-secrets.json';

try {
    $contents = @file_get_contents($fixturePath);
    if ($contents === false) {
        throw new RuntimeException('Unable to read the synthetic secret fixture.');
    }

    $fixture = json_decode($contents, true, 512, JSON_THROW_ON_ERROR);
    $canaries = $fixture['canaries'] ?? null;
    if (!is_array($canaries) || $canaries === []) {
        throw new RuntimeException('The synthetic secret fixture has no canaries.');
    }

    foreach ($canaries as $name => $value) {
        if (!is_string($name) || !is_string($value) || $value === '' || preg_match('/[\r\n]/', $value) === 1) {
            throw new RuntimeException('The synthetic secret fixture contains an invalid canary.');
        }

        fwrite(STDOUT, '::add-mask::' . $value . PHP_EOL);
    }
} catch (Throwable) {
    fwrite(STDERR, 'Unable to mask synthetic secret canaries safely.' . PHP_EOL);
    exit(1);
}
