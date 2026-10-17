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
            $line = OutputExcerpt::bounded($line, 320);

            return preg_replace('/\p{Cc}/u', ' ', $line)
                ?? preg_replace('/[\x00-\x1f\x7f-\x9f]/', ' ', $line)
                ?? '';
        }, $lines)) . PHP_EOL;
    }
}
