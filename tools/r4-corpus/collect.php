<?php

declare(strict_types=1);

use PhpUpgradePreflight\Core\Analysis\DefaultUpgradeAnalyzer;
use PhpUpgradePreflight\Core\Model\ComposerExecutionConfiguration;
use PhpUpgradePreflight\Core\Model\ExtensionAssumption;
use PhpUpgradePreflight\Core\Model\UpgradeRequest;
use PhpUpgradePreflight\Core\Model\UpgradeTarget;
use PhpUpgradePreflight\Core\Reporting\JsonReportWriter;
use PhpUpgradePreflight\Core\Reporting\MarkdownReportWriter;
use PhpUpgradePreflight\Laravel\LaravelFrameworkIntegration;

// The reserved buffer allows a small fatal record after PHP exhausts its heap.
$reserve = str_repeat('x', 262144);
$record = ['status' => 'started', 'php_heap_peak_bytes' => null, 'analysis_seconds' => null];
$started = null;
register_shutdown_function(static function () use (&$reserve, &$record, &$started): void {
    $reserve = null;
    $record['php_heap_peak_bytes'] = memory_get_peak_usage(true);
    $status = @file_get_contents('/proc/self/status');
    $record['php_process_peak_rss_kib'] = $status !== false && preg_match('/^VmHWM:\s+(\d+) kB/m', $status, $match) === 1 ? (int) $match[1] : null;
    if ($started !== null) {
        $record['analysis_seconds'] = (hrtime(true) - $started) / 1e9;
    }
    $last = error_get_last();
    if ($last !== null && in_array($last['type'], [E_ERROR, E_PARSE, E_CORE_ERROR, E_COMPILE_ERROR], true)) {
        $record['status'] = 'fatal';
        $record['fatal_type'] = $last['type'];
        $record['fatal_reason'] = str_contains($last['message'], 'Allowed memory size') ? 'php_memory_exhausted' : 'php_fatal';
        $record['fatal_file'] = str_replace(['/app/', '/harness/', '/target/'], ['[TOOL]/', '[HARNESS]/', '[PROJECT_ROOT]/'], $last['file']);
        $record['fatal_line'] = $last['line'];
    }
    file_put_contents('/output/collector.json', json_encode($record, JSON_PRETTY_PRINT | JSON_THROW_ON_ERROR) . "\n");
});

try {
    require '/app/vendor/autoload.php';
    $record['runtime_php'] = PHP_VERSION;
    $record['collector_memory_limit'] = ini_get('memory_limit');
    $record['composer_child_memory_limit'] = getenv('COMPOSER_MEMORY_LIMIT');
    $extensions = [];
    foreach (['bcmath', 'curl', 'exif', 'fileinfo', 'gd', 'imagick', 'mbstring', 'openssl', 'pdo', 'xml', 'zip'] as $name) {
        $extensions[] = ExtensionAssumption::fromPresenceInput('ext-' . $name);
    }
    $execution = new ComposerExecutionConfiguration(
        '/scratch/composer-r4',
        ComposerExecutionConfiguration::DEFAULT_EXPECTED_VERSION,
        (int) getenv('R4_SCENARIO_TIMEOUT'),
        (int) getenv('R4_DIAGNOSTIC_TIMEOUT'),
        'compatible'
    );
    $request = new UpgradeRequest(
        '/target',
        [new UpgradeTarget('laravel/framework', '^' . getenv('R4_TARGET_MAJOR') . '.0')],
        getenv('R4_FROM_PHP'),
        getenv('R4_TARGET_PHP'),
        [],
        ['laravel'],
        'json',
        null,
        false,
        $extensions,
        null,
        $execution
    );
    $analyzer = new DefaultUpgradeAnalyzer([new LaravelFrameworkIntegration()]);
    $started = hrtime(true);
    $report = $analyzer->analyzeUpgrade($request);
    $record['analysis_seconds'] = (hrtime(true) - $started) / 1e9;
    $started = null;
    file_put_contents('/output/report.json', (new JsonReportWriter())->render($report));
    file_put_contents('/output/report.md', (new MarkdownReportWriter())->render($report));
    $canonical = $report->toArray();
    $record['status'] = 'completed';
    $record['report_states'] = [
        'resolution' => $canonical['resolution']['status'] ?? null,
        'staged_resolution' => $canonical['staged_resolution']['status'] ?? null,
        'staged_execution_state' => $canonical['staged_resolution']['execution_state'] ?? null,
        'staged_stop_reason' => $canonical['staged_resolution']['stop_reason'] ?? null,
        'framework_guidance' => $canonical['transition']['framework_guidance'] ?? null,
    ];
    $record['unknowns'] = $canonical['uncertainties'] ?? [];
    $record['extension_assumptions'] = [
        'presence' => ['bcmath', 'curl', 'exif', 'fileinfo', 'gd', 'imagick', 'mbstring', 'openssl', 'pdo', 'xml', 'zip'],
        'scope' => 'partial; unlisted extensions depend on analyzer runtime',
    ];
} catch (Throwable $failure) {
    // Exception messages may contain credentials or host paths; retain only class.
    $record['status'] = 'failed';
    $record['exception_class'] = get_class($failure);
    exit(1);
}
