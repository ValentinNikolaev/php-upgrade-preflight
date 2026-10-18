<?php

declare(strict_types=1);

use PhpUpgradePreflight\Core\Analysis\DefaultUpgradeAnalyzer;
use PhpUpgradePreflight\Core\Composer\ComposerScenarioRunner;
use PhpUpgradePreflight\Core\Model\ComposerExecutionConfiguration;
use PhpUpgradePreflight\Core\Model\ExtensionAssumption;
use PhpUpgradePreflight\Core\Model\UpgradeRequest;
use PhpUpgradePreflight\Core\Model\UpgradeTarget;
use PhpUpgradePreflight\Core\Reporting\JsonReportWriter;
use PhpUpgradePreflight\Core\Reporting\MarkdownReportWriter;
use PhpUpgradePreflight\Core\Reporting\ReportWriterResolver;
use PhpUpgradePreflight\Core\Model\ReportFormat;
use PhpUpgradePreflight\Core\Source\SourceUsageScanner;
use PhpUpgradePreflight\Laravel\LaravelFrameworkIntegration;

require __DIR__ . '/runtime.php';

// The reserved buffer allows a small fatal record after PHP exhausts its heap.
$reserve = str_repeat('x', 262144);
$record = ['status' => 'started', 'php_heap_peak_bytes' => null, 'analysis_seconds' => null];
$started = null;
register_shutdown_function(static function () use (&$reserve, &$record, &$started): void {
    $reserve = null;
    $record['php_heap_peak_bytes'] = memory_get_peak_usage(true);
    $status = @file_get_contents('/proc/self/status');
    $record['php_process_peak_rss_kib'] = $status !== false && preg_match('/^VmHWM:\s+(\d+) kB/m', $status, $match) === 1 ? (int) $match[1] : null;
    $elapsed = r4Elapsed($started);
    if ($elapsed !== null) {
        $record['analysis_seconds'] = $elapsed;
    }
    $last = error_get_last();
    if ($last !== null && in_array($last['type'], [E_ERROR, E_PARSE, E_CORE_ERROR, E_COMPILE_ERROR], true)) {
        $record['status'] = 'fatal';
        $record['fatal_type'] = $last['type'];
        $record['fatal_reason'] = str_contains($last['message'], 'Allowed memory size') ? 'php_memory_exhausted' : 'php_fatal';
        $record['fatal_file'] = str_replace(['/app/', '/harness/', '/target/'], ['[TOOL]/', '[HARNESS]/', '[PROJECT_ROOT]/'], $last['file']);
        $record['fatal_line'] = $last['line'];
    }
    r4Write('/output/collector.json', json_encode($record, JSON_PRETTY_PRINT | JSON_THROW_ON_ERROR) . "\n");
});

try {
    if (getenv('R4_CACHE_SEED_POLICY') === 'runtime-user-copy-v1') {
        require __DIR__ . '/cache-seed.php';
        $seedCheck = (float) hrtime(true);
        $record['cache_before_analysis_sha256'] = (object) r4VerifySeedBeforeAnalysis();
        $record['cache_seed_verification_seconds'] = r4Elapsed($seedCheck);
        $record['cache_seed_verified'] = true;
    }
    r4Autoload();
    $record['runtime_class_files_sha256'] = r4RuntimeAttestation();
    require_once __DIR__ . '/OptimizationProbe.php';
    $record['runtime_php'] = PHP_VERSION;
    $record['collector_memory_limit'] = ini_get('memory_limit');
    $record['composer_child_memory_limit'] = getenv('COMPOSER_MEMORY_LIMIT');
    $record['github_transport_policy'] = getenv('R4_GITHUB_TRANSPORT') === 'public-https' ? 'public-https' : 'default';
    $record['github_https_git_environment_active'] = getenv('GIT_CONFIG_COUNT') === '1'
        && getenv('GIT_CONFIG_KEY_0') === 'url.https://github.com/.insteadOf'
        && getenv('GIT_CONFIG_VALUE_0') === 'git@github.com:';
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
    $requestArguments = [
        '/target',
        [new UpgradeTarget('laravel/framework', '^' . getenv('R4_TARGET_MAJOR') . '.0')],
        (string) getenv('R4_FROM_PHP'),
        (string) getenv('R4_TARGET_PHP'),
        [],
        ['laravel'],
        'json',
        null,
        false,
        $extensions,
        null,
        $execution,
    ];
    $requestApi = new ReflectionClass(UpgradeRequest::class);
    $analysisMode = getenv('R4_ANALYSIS_MODE');
    if ($analysisMode !== false && $analysisMode !== '') {
        if (!in_array($analysisMode, ['standard', 'full'], true)) {
            throw new RuntimeException('Unsupported requested analysis mode.');
        }
        if ($requestApi->hasMethod('analysisMode')) {
            $requestArguments[] = $analysisMode;
        } elseif ($analysisMode !== 'full') {
            throw new RuntimeException('Selected baseline does not support standard analysis mode.');
        }
    }
    // Compare both API revisions without requiring the newer constructor on the baseline.
    $request = $requestApi->newInstanceArgs($requestArguments);
    $record['analysis_mode_effective'] = $requestApi->hasMethod('analysisMode')
        ? $requestApi->getMethod('analysisMode')->invoke($request) : 'full';
    $sourceCachePath = getenv('R4_SOURCE_CACHE_DIR');
    $disableSourceCache = getenv('R4_DISABLE_SOURCE_CACHE') === '1';
    $scanner = null;
    $cacheApi = OptimizationProbe::optionalClass('PhpUpgradePreflight\\Core\\Source\\SourceFactsCache');
    $record['source_cache_supported'] = $cacheApi !== null;
    if ($sourceCachePath !== false && $sourceCachePath !== '') {
        if ($sourceCachePath !== '/source-cache' || $disableSourceCache || $cacheApi === null) {
            throw new RuntimeException('Selected tool or configuration cannot use the dedicated source cache.');
        }
        // The mount root can be host-owned on Docker Desktop. Create private facts as the runtime user.
        $cache = $cacheApi->newInstanceArgs([$sourceCachePath . '/facts']);
        $scanner = (new ReflectionClass(SourceUsageScanner::class))->newInstanceArgs([null, null, $cache]);
        $record['source_cache_policy'] = 'dedicated_external_directory';
    } elseif ($disableSourceCache && $cacheApi !== null) {
        $cache = $cacheApi->getMethod('disabled')->invoke(null);
        $scanner = (new ReflectionClass(SourceUsageScanner::class))->newInstanceArgs([null, null, $cache]);
        $record['source_cache_policy'] = 'disabled';
    } else {
        $record['source_cache_policy'] = $disableSourceCache ? 'unavailable_in_baseline' : 'tool_default';
    }
    $sourceProfile = null;
    $record['source_profile_instrumented'] = getenv('R4_SOURCE_PROFILE') === '1';
    if ($record['source_profile_instrumented']) {
        $profileApi = OptimizationProbe::optionalClass('PhpUpgradePreflight\\Core\\Source\\SourceScanProfile');
        if ($profileApi === null || !$profileApi->hasMethod('snapshot')) {
            throw new RuntimeException('Selected runtime does not support source profiling.');
        }
        $sourceProfile = $profileApi->newInstance();
        // Keep the profile independently: the analyzer may clone the scanner per request.
        $scanner = (new ReflectionClass(SourceUsageScanner::class))->newInstanceArgs([null, null, $cache ?? null, $sourceProfile]);
    }
    $analysisTimings = null;
    if ($record['source_profile_instrumented']) {
        $timingApi = OptimizationProbe::optionalClass('PhpUpgradePreflight\\Core\\Analysis\\AnalysisTimings');
        if ($timingApi !== null && $timingApi->hasMethod('snapshot')) {
            $analysisTimings = $timingApi->newInstance();
        }
    }
    $probe = new OptimizationProbe();
    $scenarioRunner = new ComposerScenarioRunner();
    $analyzerArguments = [[new LaravelFrameworkIntegration()],
        'scenarioRunner' => $scenarioRunner, 'sourceUsageScanner' => $scanner, 'progressReporter' => $probe];
    if ($analysisTimings !== null) {
        $analyzerArguments['timings'] = $analysisTimings;
    }
    // Reflection supports named arguments on PHP 8.0, including optional APIs.
    $analyzer = (new ReflectionClass(DefaultUpgradeAnalyzer::class))->newInstanceArgs($analyzerArguments);
    $started = (float) hrtime(true);
    $probe->startAnalysis($started);
    $report = $analyzer->analyzeUpgrade($request);
    $record['analysis_seconds'] = r4Elapsed($started);
    $record['heap_peak_through_analysis_bytes'] = memory_get_peak_usage(true);
    $started = null;
    $record['phase_seconds'] = $probe->seconds();
    $record['source_profile'] = $sourceProfile === null ? null : OptimizationProbe::sourceProfileSnapshot($sourceProfile);
    $record['analysis_profile'] = $analysisTimings === null ? null : OptimizationProbe::analysisProfileSnapshot($analysisTimings);
    $record['first_direct_finding_seconds'] = $probe->firstDirectFindingSeconds();
    $runnerApi = new ReflectionObject($scenarioRunner);
    if ($runnerApi->hasMethod('measurements')) {
        $record['runner_measurements'] = $runnerApi->getMethod('measurements')->invoke($scenarioRunner);
    }
    $record['human_summary_ready_seconds'] = null;
    if (defined(ReportFormat::class . '::SUMMARY')) {
        $renderStarted = (float) hrtime(true);
        $summary = (new ReportWriterResolver())->resolve('summary')->render($report);
        $record['summary_render_seconds'] = r4Elapsed($renderStarted);
        if ($record['analysis_seconds'] !== null && $record['summary_render_seconds'] !== null) {
            $record['human_summary_ready_seconds'] = $record['analysis_seconds'] + $record['summary_render_seconds'];
        }
        $record['summary_bytes'] = strlen($summary);
        $record['summary_lines'] = substr_count(rtrim($summary, "\r\n"), "\n") + 1;
        $record['heap_peak_through_summary_bytes'] = memory_get_peak_usage(true);
        r4Write('/output/summary.txt', $summary);
    }
    $renderStarted = (float) hrtime(true);
    $json = (new JsonReportWriter())->render($report);
    $markdown = (new MarkdownReportWriter())->render($report);
    $record['full_report_render_seconds'] = r4Elapsed($renderStarted);
    r4Write('/output/report.json', $json);
    r4Write('/output/report.md', $markdown);
    $record['report_artifacts'] = [
        'report.json' => ['bytes' => strlen($json), 'sha256' => hash('sha256', $json)],
        'report.md' => ['bytes' => strlen($markdown), 'sha256' => hash('sha256', $markdown)],
    ];
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
