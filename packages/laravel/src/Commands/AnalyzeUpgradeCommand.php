<?php

declare(strict_types=1);

namespace PhpUpgradePreflight\Laravel\Commands;

use Illuminate\Console\Command;
use PhpUpgradePreflight\Core\Contracts\UpgradeAnalyzer;
use PhpUpgradePreflight\Core\Model\ReportFormat;
use PhpUpgradePreflight\Core\Model\UpgradeRequest;
use PhpUpgradePreflight\Core\Model\UpgradeTarget;
use PhpUpgradePreflight\Core\Reporting\JsonReportWriter;
use PhpUpgradePreflight\Core\Reporting\MarkdownReportWriter;

final class AnalyzeUpgradeCommand extends Command
{
    protected $signature = 'upgrade:analyze
        {--path= : Project path to analyze}
        {--target=* : Target constraint using package:constraint syntax}
        {--from-php= : Current project PHP version}
        {--format=json : json or markdown}
        {--output= : Report output path}
        {--debug : Preserve temporary Composer workspaces}';

    protected $description = 'Analyze Composer and PHP upgrade readiness without mutating the project.';

    private UpgradeAnalyzer $analyzer;

    public function __construct(UpgradeAnalyzer $analyzer)
    {
        parent::__construct();
        $this->analyzer = $analyzer;
    }

    public function handle(): int
    {
        $targets = array_map(
            static fn (string $target): UpgradeTarget => UpgradeTarget::fromString($target),
            array_values((array) $this->option('target'))
        );

        if ($targets === []) {
            $this->error('At least one --target=package:constraint option is required.');

            return self::FAILURE;
        }

        $format = ReportFormat::normalize((string) $this->option('format'));
        $request = new UpgradeRequest(
            (string) ($this->option('path') ?: base_path()),
            $targets,
            $this->nullableString($this->option('from-php')),
            $this->targetPhp($targets),
            [],
            ['laravel'],
            $format,
            $this->nullableString($this->option('output')),
            (bool) $this->option('debug')
        );

        $report = $this->analyzer->analyzeUpgrade($request);
        $rendered = $format === ReportFormat::MARKDOWN
            ? (new MarkdownReportWriter())->render($report)
            : (new JsonReportWriter())->render($report);

        if ($request->outputPath !== null) {
            file_put_contents($request->outputPath, $rendered);
            $this->info(sprintf('Wrote report to %s', $request->outputPath));
        } else {
            $this->line($rendered);
        }

        return self::SUCCESS;
    }

    /** @param list<UpgradeTarget> $targets */
    private function targetPhp(array $targets): ?string
    {
        foreach ($targets as $target) {
            if ($target->package === 'php') {
                return $target->constraint;
            }
        }

        return null;
    }

    /** @param mixed $value */
    private function nullableString($value): ?string
    {
        return is_string($value) && $value !== '' ? $value : null;
    }
}
