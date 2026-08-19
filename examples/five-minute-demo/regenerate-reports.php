<?php

declare(strict_types=1);

/*
 * Regenerates both committed demo reports from one analyzer run.
 *
 * JSON is the canonical report and Markdown must be a projection of the same
 * UpgradeReport, so both files are rendered from a single analysis instead of
 * two CLI invocations whose run-local values (durations, raw candidate-lock
 * hashes) would disagree.
 *
 * The analyzed request comes from FiveMinuteDemoAnalysis, the same definition
 * the deterministic test gate uses, so this script cannot drift into analyzing
 * something the gate does not verify. The demo target is snapshotted before the
 * run and proven byte-for-byte unchanged afterwards.
 *
 * Run from the repository root with development dependencies installed:
 *
 *   php examples/five-minute-demo/regenerate-reports.php
 */

use PhpUpgradePreflight\Core\Analysis\DefaultUpgradeAnalyzer;
use PhpUpgradePreflight\Core\Reporting\JsonReportWriter;
use PhpUpgradePreflight\Core\Reporting\MarkdownReportWriter;
use PhpUpgradePreflight\Core\Reporting\ReportFileWriter;
use PhpUpgradePreflight\Laravel\LaravelFrameworkIntegration;
use PhpUpgradePreflight\Tests\Support\FiveMinuteDemoAnalysis;
use PhpUpgradePreflight\Tests\Support\FixtureSnapshot;

require __DIR__ . '/../../vendor/autoload.php';

putenv('COMPOSER_DISABLE_NETWORK=1');
putenv('COMPOSER_ROOT_VERSION=1.0.0');

$target = FiveMinuteDemoAnalysis::targetPath();
$jsonPath = FiveMinuteDemoAnalysis::canonicalJsonPath();
$markdownPath = FiveMinuteDemoAnalysis::canonicalMarkdownPath();

$snapshot = FixtureSnapshot::capture($target);

$report = (new DefaultUpgradeAnalyzer([new LaravelFrameworkIntegration()]))
    ->analyzeUpgrade(FiveMinuteDemoAnalysis::request($jsonPath));

$changed = $snapshot->differencesFromDisk();
if ($changed !== []) {
    throw new RuntimeException(sprintf(
        'The analysis modified the demo target, which must stay immutable input: %s',
        implode(', ', $changed)
    ));
}

$files = new ReportFileWriter();
$files->write($target, $jsonPath, (new JsonReportWriter())->render($report));
$files->write($target, $markdownPath, (new MarkdownReportWriter())->render($report));

fwrite(STDOUT, sprintf(
    "Regenerated %s and %s from one analyzer run; the demo target is unchanged.\n",
    basename($jsonPath),
    basename($markdownPath)
));
