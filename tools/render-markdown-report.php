<?php

declare(strict_types=1);

use PhpUpgradePreflight\Core\Model\ReportFormat;
use PhpUpgradePreflight\Core\Reporting\MarkdownReportWriter;
use PhpUpgradePreflight\Core\Reporting\ReportFileWriter;

if (count($argv) !== 3) {
    fwrite(STDERR, "Usage: php tools/render-markdown-report.php INPUT.json OUTPUT.md\n");
    exit(2);
}

$autoloadPath = dirname(__DIR__) . '/vendor/autoload.php';
if (!is_file($autoloadPath)) {
    fwrite(STDERR, "Unable to find Composer autoload.php.\n");
    exit(1);
}
require $autoloadPath;

try {
    $contents = file_get_contents($argv[1]);
    if ($contents === false) {
        throw new RuntimeException('Unable to read the canonical JSON report.');
    }

    $canonical = json_decode($contents, true, 512, JSON_THROW_ON_ERROR);
    if (!is_array($canonical) || !isset($canonical['request_summary']) || !is_array($canonical['request_summary'])) {
        throw new RuntimeException('The canonical JSON report is invalid.');
    }

    $projectPath = $canonical['request_summary']['project_path'] ?? null;
    if (!is_string($projectPath) || $projectPath === '') {
        throw new RuntimeException('The canonical JSON report has no project path.');
    }

    $fileWriter = new ReportFileWriter();
    $outputPath = $fileWriter->validateDestination($projectPath, $argv[2]);
    $canonical['request_summary']['format'] = ReportFormat::MARKDOWN;
    $canonical['request_summary']['output_path'] = $outputPath;

    $rendered = (new MarkdownReportWriter())->renderCanonical($canonical);
    $writtenPath = $fileWriter->write($projectPath, $outputPath, $rendered);
} catch (Throwable) {
    fwrite(STDERR, 'Unable to render the Markdown report safely.' . PHP_EOL);
    exit(1);
}

fwrite(STDOUT, sprintf("Wrote report to %s\n", $writtenPath));
