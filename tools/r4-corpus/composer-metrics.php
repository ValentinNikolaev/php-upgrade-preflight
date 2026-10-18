<?php

declare(strict_types=1);

// Runs in Composer's PHP PID: no timing parent can outlive Symfony cancellation.
$r4Started = (float) hrtime(true);
register_shutdown_function(static function () use ($r4Started): void {
    $status = @file_get_contents('/proc/self/status');
    $rss = $status !== false && preg_match('/^VmHWM:\s+(\d+) kB/m', $status, $match) === 1 ? (int) $match[1] : null;
    $metrics = [
        'scope' => 'composer_php_process',
        'elapsed_seconds' => ((float) hrtime(true) - $r4Started) / 1e9,
        'max_rss_kib' => $rss,
        // Shutdown functions cannot reliably observe PHP exit status or signals.
        'exit_code' => null,
        'termination_kind' => 'unknown',
        'termination_signal' => null,
    ];
    $path = '/output/composer/' . getenv('R4_INVOCATION_ID') . '.metrics.json';
    $json = json_encode($metrics, JSON_THROW_ON_ERROR) . "\n";
    if (file_put_contents($path, $json) !== strlen($json)) {
        // Preserve partial output; the host validates it before using measurements.
        error_log('R4 Composer metrics write incomplete');
    }
});
if (getenv('R4_APPLIED_PROBE') === 'timeout') {
    sleep(3600);
    exit(0);
}
if (getenv('R4_APPLIED_PROBE') === 'child-failure') {
    exit(42);
}
