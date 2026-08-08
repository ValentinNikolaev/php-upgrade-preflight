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
use PhpUpgradePreflight\Core\Reporting\ReportFileWriter;
use Symfony\Component\Console\Output\ConsoleOutputInterface;
use Symfony\Component\Console\Output\OutputInterface;

final class AnalyzeUpgradeCommand extends Command
{
    protected $signature = 'upgrade:analyze
        {--path= : Project path to analyze}
        {--target=* : Target constraint using package:constraint syntax}
        {--target-php= : Explicit target PHP platform version}
        {--from-php= : Current project PHP version}
        {--source=* : Additional source path to scan}
        {--format=json : json or markdown}
        {--output= : Report output path}
        {--debug : Preserve temporary Composer workspaces}';

    protected $description = 'Analyze Composer and PHP upgrade readiness without mutating the project.';

    private UpgradeAnalyzer $analyzer;
    private ReportFileWriter $reportFileWriter;

    public function __construct(UpgradeAnalyzer $analyzer, ?ReportFileWriter $reportFileWriter = null)
    {
        parent::__construct();
        $this->analyzer = $analyzer;
        $this->reportFileWriter = $reportFileWriter ?? new ReportFileWriter();
    }

    public function handle(): int
    {
        try {
            $targets = array_map(
                static fn (string $target): UpgradeTarget => UpgradeTarget::fromString($target),
                array_values((array) $this->option('target'))
            );
            $targetPhp = $this->optionalString('target-php');

            if ($targets === [] && $targetPhp === null) {
                throw new \InvalidArgumentException('At least one --target=package:constraint or --target-php=VERSION option is required.');
            }

            $format = ReportFormat::normalize((string) $this->option('format'));
            $request = new UpgradeRequest(
                $this->projectPath(),
                $targets,
                $this->optionalString('from-php'),
                $targetPhp,
                array_values((array) $this->option('source')),
                ['laravel'],
                $format,
                $this->optionalString('output'),
                (bool) $this->option('debug')
            );

            if ($request->outputPath() !== null) {
                $this->reportFileWriter->validateDestination($request->projectPath(), $request->outputPath());
            }
        } catch (\InvalidArgumentException $exception) {
            $this->diagnostic('Invalid invocation: ' . $exception->getMessage());

            return self::INVALID;
        } catch (\Throwable $exception) {
            $this->diagnostic('Analysis failed: ' . $exception->getMessage());

            return self::FAILURE;
        }

        try {
            $report = $this->analyzer->analyzeUpgrade($request);
            $rendered = $format === ReportFormat::MARKDOWN
                ? (new MarkdownReportWriter())->render($report)
                : (new JsonReportWriter())->render($report);

            if ($request->outputPath() !== null) {
                $writtenPath = $this->reportFileWriter->write($request->projectPath(), $request->outputPath(), $rendered);
                $this->info(sprintf('Wrote report to %s', $writtenPath));
            } else {
                $this->output->getOutput()->writeln($rendered, OutputInterface::OUTPUT_RAW);
            }

            return self::SUCCESS;
        } catch (\Throwable $exception) {
            $this->diagnostic('Analysis failed: ' . $exception->getMessage());

            return self::FAILURE;
        }
    }

    private function projectPath(): string
    {
        $path = $this->option('path');

        if ($path !== null) {
            return is_string($path) ? $path : '';
        }

        $basePath = $this->laravel->basePath();
        if (!is_string($basePath) || trim($basePath) === '') {
            throw new \RuntimeException('Unable to determine the Laravel application base path.');
        }

        return $basePath;
    }

    private function optionalString(string $name): ?string
    {
        $value = $this->option($name);

        if ($value === null) {
            return null;
        }

        if (!is_string($value)) {
            throw new \InvalidArgumentException(sprintf('Option "--%s" must be a string.', $name));
        }

        return $value;
    }

    private function diagnostic(string $message): void
    {
        $output = $this->output->getOutput();
        $diagnosticOutput = $output instanceof ConsoleOutputInterface
            ? $output->getErrorOutput()
            : $output;

        $diagnosticOutput->writeln($message, OutputInterface::OUTPUT_RAW);
    }
}
