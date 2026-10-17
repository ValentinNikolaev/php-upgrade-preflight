<?php

declare(strict_types=1);

require_once __DIR__ . '/runtime.php';

/** @return array{files_sha256: array<string, string>, directories: list<string>} */
function r4CacheInventory(string $root, ?int $uid = null): array
{
    if (!is_dir($root) || is_link($root)) {
        throw new RuntimeException('cache_root_invalid');
    }
    $files = [];
    $directories = [];
    $walk = static function (string $relative) use (&$walk, &$files, &$directories, $root, $uid): void {
        $entries = scandir($root . '/' . $relative);
        if ($entries === false) {
            throw new RuntimeException('cache_inventory_failed');
        }
        foreach ($entries as $name) {
            if ($name === '.' || $name === '..') {
                continue;
            }
            $key = $relative === '' ? $name : $relative . '/' . $name;
            $path = $root . '/' . $key;
            if (is_link($path) || ($uid !== null && fileowner($path) !== $uid)) {
                throw new RuntimeException('cache_link_or_owner_mismatch');
            }
            if (is_dir($path)) {
                $directories[] = $key;
                $walk($key);
            } elseif (is_file($path)) {
                $hash = hash_file('sha256', $path);
                if ($hash === false) {
                    throw new RuntimeException('cache_hash_failed');
                }
                $files[$key] = $hash;
            } else {
                throw new RuntimeException('cache_entry_unsupported');
            }
        }
    };
    $walk('');
    ksort($files, SORT_STRING);
    sort($directories, SORT_STRING);

    return ['files_sha256' => $files, 'directories' => $directories];
}

/** @return array<string, mixed> */
function r4SeedExpected(): array
{
    $expected = json_decode((string) file_get_contents('/cache-seed-proof.json'), true, 512, JSON_THROW_ON_ERROR);
    if (!is_array($expected) || ($expected['policy'] ?? null) !== 'runtime-user-copy-v1'
        || !is_string($expected['cold_receipt_sha256'] ?? null)
        || preg_match('/^[0-9a-f]{64}$/D', $expected['cold_receipt_sha256']) !== 1
        || !is_array($expected['files_sha256'] ?? null) || !is_array($expected['directories'] ?? null)) {
        throw new RuntimeException('cache_seed_expected_invalid');
    }
    ksort($expected['files_sha256'], SORT_STRING);
    sort($expected['directories'], SORT_STRING);

    return $expected;
}

function r4SeedCache(): int
{
    $started = (float) hrtime(true);
    $record = ['policy' => 'runtime-user-copy-v1', 'status' => 'started', 'elapsed_seconds' => null];
    try {
        if (!function_exists('posix_geteuid') || !function_exists('posix_getegid')) {
            throw new RuntimeException('cache_seed_identity_unavailable');
        }
        $record['owner_uid'] = posix_geteuid();
        $record['owner_gid'] = posix_getegid();
        $expected = r4SeedExpected();
        $record['cold_receipt_sha256'] = $expected['cold_receipt_sha256'];
        $record['expected_proof_sha256'] = hash_file('sha256', '/cache-seed-proof.json');
        $before = r4CacheInventory('/cache-seed');
        if ($before['files_sha256'] !== $expected['files_sha256'] || $before['directories'] !== $expected['directories']) {
            throw new RuntimeException('cache_seed_source_mismatch');
        }
        $empty = r4CacheInventory('/composer-cache');
        if ($empty['files_sha256'] !== [] || $empty['directories'] !== []) {
            throw new RuntimeException('cache_seed_destination_not_empty');
        }
        $record['destination_empty'] = true;
        // Fresh entries are created by this runtime UID; no chown or Git trust override.
        $copy = static function (string $relative) use (&$copy): void {
            $entries = scandir('/cache-seed/' . $relative);
            if ($entries === false) {
                throw new RuntimeException('cache_seed_source_unreadable');
            }
            foreach ($entries as $name) {
                if ($name === '.' || $name === '..') {
                    continue;
                }
                $key = $relative === '' ? $name : $relative . '/' . $name;
                $source = '/cache-seed/' . $key;
                $target = '/composer-cache/' . $key;
                if (is_link($source)) {
                    throw new RuntimeException('cache_seed_link_rejected');
                }
                if (is_dir($source)) {
                    if (!mkdir($target, 0700)) {
                        throw new RuntimeException('cache_seed_mkdir_failed');
                    }
                    $copy($key);
                } elseif (!is_file($source) || !copy($source, $target)) {
                    throw new RuntimeException('cache_seed_copy_failed');
                }
            }
        };
        $copy('');
        $after = r4CacheInventory('/cache-seed');
        $destination = r4CacheInventory('/composer-cache', $record['owner_uid']);
        if ($before !== $after || $before !== $destination) {
            throw new RuntimeException('cache_seed_integrity_failed');
        }
        $record['source_before_sha256'] = (object) $before['files_sha256'];
        $record['source_after_sha256'] = (object) $after['files_sha256'];
        $record['destination_before_analysis_sha256'] = (object) $destination['files_sha256'];
        $record['directories'] = $destination['directories'];
        $record['entries_owned'] = true;
        $record['status'] = 'completed';
    } catch (Throwable $failure) {
        $record['status'] = 'failed';
        $record['failure_class'] = get_class($failure);
        $record['failure_reason'] = $failure instanceof RuntimeException ? $failure->getMessage() : 'cache_seed_failed';
    }
    $record['elapsed_seconds'] = r4Elapsed($started);
    r4Write('/output/cache-seed.json', json_encode($record, JSON_PRETTY_PRINT | JSON_THROW_ON_ERROR) . "\n");

    return $record['status'] === 'completed' ? 0 : 78;
}

/** @return array<string, string> */
function r4VerifySeedBeforeAnalysis(): array
{
    $proof = json_decode((string) file_get_contents('/output/cache-seed.json'), true, 512, JSON_THROW_ON_ERROR);
    $expected = r4SeedExpected();
    if (!is_array($proof) || ($proof['status'] ?? null) !== 'completed'
        || ($proof['policy'] ?? null) !== $expected['policy']
        || ($proof['cold_receipt_sha256'] ?? null) !== $expected['cold_receipt_sha256']
        || ($proof['expected_proof_sha256'] ?? null) !== hash_file('sha256', '/cache-seed-proof.json')
        || ($proof['owner_uid'] ?? null) !== posix_geteuid()) {
        throw new RuntimeException('cache_seed_proof_invalid');
    }
    $actual = r4CacheInventory('/composer-cache', posix_geteuid());
    if ($actual['files_sha256'] !== $expected['files_sha256'] || $actual['directories'] !== $expected['directories']) {
        throw new RuntimeException('cache_before_analysis_mismatch');
    }

    return $actual['files_sha256'];
}

if (realpath((string) ($_SERVER['SCRIPT_FILENAME'] ?? '')) === __FILE__) {
    exit(r4SeedCache());
}
