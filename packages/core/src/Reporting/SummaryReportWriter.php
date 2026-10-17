<?php

declare(strict_types=1);

namespace PhpUpgradePreflight\Core\Reporting;

use PhpUpgradePreflight\Core\Model\UpgradeReport;
use PhpUpgradePreflight\Core\Support\PathExposurePolicy;
use PhpUpgradePreflight\Core\Support\OutputExcerpt;

final class SummaryReportWriter implements ReportWriter
{
    public function render(UpgradeReport $report): string
    {
        $projection = new SummaryReportProjection($report);
        // Redact complete values before shortening, so truncation cannot expose a partial secret.
        $safe = PathExposurePolicy::sanitizeCanonicalReport(
            ['lines' => $projection->lines()],
            $report->request()->projectPath(),
            $report->request()->outputPath(),
            PathExposurePolicy::localRepositoryPaths($report->projectState()->composerJson()->data(), $report->projectState()->path())
        );
        /** @var list<string> $lines */
        $lines = $safe['lines'];

        return implode(PHP_EOL, array_map(static function (string $line): string {
            // Native JSON substitution preserves valid Unicode without requiring mbstring.
            /** @var string $line */
            $line = json_decode(json_encode($line, JSON_INVALID_UTF8_SUBSTITUTE | JSON_THROW_ON_ERROR), true, 512, JSON_THROW_ON_ERROR);
            $line = preg_replace('/\p{Cc}/u', ' ', $line) ?? '';

            // Keep the excerpt's generated truncation marker on the same bounded line.
            return str_replace(PHP_EOL, ' ', OutputExcerpt::bounded($line, 320));
        }, $lines)) . PHP_EOL;
    }
}
