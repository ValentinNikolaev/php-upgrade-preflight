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
    'packages/core/src/Model/TargetPlatformProfile.php',
    'packages/core/src/Model/TargetPlatform.php',
    'packages/core/src/Model/ComposerExecutionConfiguration.php',
    'packages/core/src/Model/ProjectStateFingerprint.php',
    'packages/core/src/Analysis/StagedUpgradeOrchestrator.php',
    'packages/core/src/Analysis/ReportAssembler.php',
    'packages/core/src/Analysis/ReportSectionBuilder.php',
    'packages/cli/src/FrameworkIntegrationRegistry.php',
    // Extracted from StagedUpgradeOrchestrator and LaravelFrameworkIntegration. The floors here are
    // ratios, so leaving these off the list would let a thin delegator satisfy the original module's
    // floor while the logic it used to hold went unwatched.
    'packages/core/src/Analysis/StagePlanResolver.php',
    'packages/core/src/Analysis/StageExecutor.php',
    'packages/core/src/Analysis/StageBlockerRegistry.php',
    'packages/core/src/Analysis/StageAttemptPlanner.php',
    'packages/core/src/Composer/ScenarioOutcomeClassifier.php',
    'packages/core/src/Composer/ScenarioWorkspacePreparer.php',
    'packages/core/src/Model/BlockerType.php',
    'packages/laravel/src/LaravelTransitionAssessor.php',
    'packages/laravel/src/LaravelStagePlanner.php',
    'packages/laravel/src/LaravelRuleFactory.php',
    'packages/laravel/src/LaravelFrameworkDetector.php',
    'packages/laravel/src/Source/LaravelSourceUsageVisitor.php',
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
