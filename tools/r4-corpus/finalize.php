<?php

declare(strict_types=1);

require __DIR__ . '/runtime.php';

$scratch = ['entries' => 0, 'files' => 0, 'bytes' => 0];
$iterator = new RecursiveIteratorIterator(new RecursiveDirectoryIterator('/scratch/work', FilesystemIterator::SKIP_DOTS));
foreach ($iterator as $entry) {
    ++$scratch['entries'];
    if ($entry->isFile() && !$entry->isLink()) {
        ++$scratch['files'];
        $scratch['bytes'] += $entry->getSize();
    }
}
$memoryDirectory = '/sys/fs/cgroup/memory';
$cgroup = @file('/proc/self/cgroup', FILE_IGNORE_NEW_LINES) ?: [];
$candidates = [$memoryDirectory];
foreach ($cgroup as $line) {
    $parts = explode(':', $line, 3);
    if (count($parts) === 3 && in_array('memory', explode(',', $parts[1]), true) && !str_contains($parts[2], '..')) {
        $candidates[] = $memoryDirectory . $parts[2];
    }
}
$peak = null;
foreach ($candidates as $directory) {
    // Accept only a controller whose limit matches this task's declared 2 GiB.
    $limit = @file_get_contents($directory . '/memory.limit_in_bytes');
    if ($limit !== false && trim($limit) === '2147483648') {
        $candidate = @file_get_contents($directory . '/memory.max_usage_in_bytes');
        if ($candidate !== false && ctype_digit(trim($candidate))) {
            $peak = trim($candidate);
            break;
        }
    }
}
$record = [
    'status' => 'completed',
    'collector_exit_code' => (int) $argv[1],
    'scratch_leftovers_before_container_exit' => $scratch,
    'container_memory_v1_peak_bytes' => $peak !== null && ctype_digit($peak) ? (int) $peak : null,
    'container_memory_v1_status' => $peak !== null && ctype_digit($peak) ? 'available' : 'unavailable',
    'cache_fault_requested' => getenv('R4_PROBE') === 'cache-failure',
    'cache_path_writable' => is_writable((string) getenv('COMPOSER_CACHE_DIR')),
];
// Bounded diagnostics are sanitized before leaving task-owned scratch.
try {
    r4Autoload();
    $stderr = (string) file_get_contents('/scratch/analyzer.stderr', false, null, 0, 32768);
    $stderr = PhpUpgradePreflight\Core\Support\SensitiveOutputRedactor::redact($stderr);
    $stderr = str_replace(['/app/', '/target/', '/scratch/', '/harness/'], ['[TOOL]/', '[PROJECT_ROOT]/', '[SCRATCH]/', '[HARNESS]/'], $stderr);
    r4Write('/output/analyzer.stderr.txt', $stderr);
    $record['sanitized_stderr_bytes'] = strlen($stderr);
} catch (Throwable $failure) {
    $record['status'] = 'failed';
    $record['sanitized_stderr_bytes'] = null;
}
r4Write('/output/container.json', json_encode($record, JSON_PRETTY_PRINT | JSON_THROW_ON_ERROR) . "\n");
exit($record['status'] === 'completed' ? 0 : 1);
