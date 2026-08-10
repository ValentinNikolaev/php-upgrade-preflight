<?php

declare(strict_types=1);

use PhpUpgradePreflight\Tools\CoverageVerifier;

require_once __DIR__ . '/CoverageVerifier.php';

const COVERAGE_BASELINE = __DIR__ . '/../tests/fixtures/quality/coverage-baseline.json';
const CRITICAL_MODULES = [
    'packages/core/src/Analysis/ScenarioSelector.php',
    'packages/core/src/Analysis/ComposerBlockerParser.php',
    'packages/core/src/Model/UpgradeReport.php',
    'packages/core/src/Analysis/RiskAndEffortEstimator.php',
    'packages/laravel/src/LaravelFrameworkIntegration.php',
    'tools/ReleaseVerifier.php',
    'tools/CoverageVerifier.php',
    'tools/SecretLeakVerifier.php',
];

$cloverPath = $argv[1] ?? (__DIR__ . '/../build/coverage/clover.xml');
$writeBaseline = in_array('--write-baseline', $argv, true);
$verifier = new CoverageVerifier(dirname(__DIR__), CRITICAL_MODULES);

try {
    $measurement = $verifier->measure($cloverPath);
    if ($writeBaseline) {
        $verifier->writeBaseline(COVERAGE_BASELINE, $measurement);
        fwrite(STDOUT, sprintf(
            "Coverage baseline recorded: %d/%d executable lines covered.\n",
            $measurement['overall']['covered'],
            $measurement['overall']['executable']
        ));
        exit(0);
    }

    $baseline = $verifier->readBaseline(COVERAGE_BASELINE);
    $verifier->verify($baseline, $measurement);
    fwrite(STDOUT, sprintf(
        "Coverage ratchet passed: %d/%d executable lines covered.\n",
        $measurement['overall']['covered'],
        $measurement['overall']['executable']
    ));
} catch (Throwable $exception) {
    fwrite(STDERR, 'Coverage verification failed: ' . $exception->getMessage() . "\n");
    exit(1);
}
