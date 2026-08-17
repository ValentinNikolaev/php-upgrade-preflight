<?php

declare(strict_types=1);

namespace PhpUpgradePreflight\Laravel\Commands;

use Illuminate\Console\Command;
use PhpUpgradePreflight\Core\Contracts\UpgradeAnalyzer;
use PhpUpgradePreflight\Core\Model\ComposerExecutionConfiguration;
use PhpUpgradePreflight\Core\Model\ExtensionAssumptionSet;
use PhpUpgradePreflight\Core\Model\ReportFormat;
use PhpUpgradePreflight\Core\Model\TargetPlatformProfile;
use PhpUpgradePreflight\Core\Model\UpgradeRequest;
use PhpUpgradePreflight\Core\Model\UpgradeTarget;
use PhpUpgradePreflight\Core\Reporting\ReportFileWriter;
use PhpUpgradePreflight\Core\Reporting\ReportWriterResolver;
use PhpUpgradePreflight\Core\Support\PathExposurePolicy;
use PhpUpgradePreflight\Core\Support\SensitiveOutputRedactor;
use Symfony\Component\Console\Output\OutputInterface;

final class AnalyzeUpgradeCommand extends Command
{
    private const INVALID_EXIT_CODE = 2;

    protected $signature = 'upgrade:analyze
        {--path= : Project path to analyze}
        {--target=* : Target constraint using package:constraint syntax}
        {--target-php= : Explicit target PHP platform version}
        {--target-platform-profile=* : JSON target-platform profile file; may be specified once}
        {--from-php= : Current project PHP version}
        {--with-extension=* : Assume an extension is present, optionally as ext-name:version}
        {--without-extension=* : Assume an extension is absent}
        {--source=* : Additional source path to scan}
        {--format=json : json or markdown}
        {--output= : Report output path}
        {--composer-mode=compatible : compatible or restricted}
        {--composer-executable=composer : Composer command or executable path}
        {--composer-version= : Expected Composer version constraint}
        {--composer-timeout=300 : Composer scenario timeout in seconds}
        {--composer-diagnostic-timeout=60 : Composer diagnostic timeout in seconds}
        {--debug : Preserve temporary Composer workspaces}';

    protected $description = 'Analyze Composer and PHP upgrade readiness without mutating the project.';

    private UpgradeAnalyzer $analyzer;
    private ReportFileWriter $reportFileWriter;
    private ReportWriterResolver $reportWriterResolver;

    public function __construct(
        UpgradeAnalyzer $analyzer,
        ?ReportFileWriter $reportFileWriter = null,
        ?ReportWriterResolver $reportWriterResolver = null
    ) {
        parent::__construct();
        $this->analyzer = $analyzer;
        $this->reportFileWriter = $reportFileWriter ?? new ReportFileWriter();
        $this->reportWriterResolver = $reportWriterResolver ?? new ReportWriterResolver();
    }

    public function handle(): int
    {
        try {
            $targets = array_map(
                static fn (string $target): UpgradeTarget => UpgradeTarget::fromString($target),
                array_values((array) $this->option('target'))
            );
            $targetPhp = $this->optionalString('target-php');
            $targetPlatformProfile = $this->loadTargetPlatformProfile($this->targetPlatformProfilePath());

            if ($targets === [] && $targetPhp === null && $targetPlatformProfile === null) {
                throw new \InvalidArgumentException(
                    'At least one --target=package:constraint, --target-php=VERSION, or --target-platform-profile=PATH option is required.'
                );
            }

            $format = ReportFormat::normalize($this->requiredStringOption('format'));
            $extensionAssumptions = ExtensionAssumptionSet::fromInputs(
                array_values((array) $this->option('with-extension')),
                array_values((array) $this->option('without-extension'))
            )->all();
            $composerExecution = new ComposerExecutionConfiguration(
                $this->requiredStringOption('composer-executable'),
                $this->optionalString('composer-version') ?? ComposerExecutionConfiguration::DEFAULT_EXPECTED_VERSION,
                $this->positiveIntegerOption('composer-timeout'),
                $this->positiveIntegerOption('composer-diagnostic-timeout'),
                $this->requiredStringOption('composer-mode')
            );
            $request = new UpgradeRequest(
                $this->projectPath(),
                $targets,
                $this->optionalString('from-php'),
                $targetPhp,
                array_values((array) $this->option('source')),
                ['laravel'],
                $format,
                $this->optionalString('output'),
                (bool) $this->option('debug'),
                $extensionAssumptions,
                $targetPlatformProfile,
                $composerExecution
            );

            if ($request->outputPath() !== null) {
                $this->reportFileWriter->validateDestination($request->projectPath(), $request->outputPath());
            }
        } catch (\InvalidArgumentException $exception) {
            $this->diagnostic('Invalid invocation: ' . $exception->getMessage());

            return self::INVALID_EXIT_CODE;
        } catch (\Throwable $exception) {
            $this->diagnostic('Analysis failed: ' . $exception->getMessage());

            return self::FAILURE;
        }

        try {
            $report = $this->analyzer->analyzeUpgrade($request);
            $rendered = $this->reportWriterResolver->resolve($format)->render($report);

            if ($request->outputPath() !== null) {
                $writtenPath = $this->reportFileWriter->write($request->projectPath(), $request->outputPath(), $rendered);
                $this->info(sprintf(
                    'Wrote report to %s',
                    PathExposurePolicy::operationalPath($writtenPath)
                ));
            } else {
                $this->output->writeln($rendered, OutputInterface::OUTPUT_RAW);
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

    private function requiredStringOption(string $name): string
    {
        $value = $this->optionalString($name);
        if ($value === null) {
            throw new \InvalidArgumentException(sprintf('Option "--%s" must be a string.', $name));
        }

        return $value;
    }

    private function loadTargetPlatformProfile(?string $path): ?TargetPlatformProfile
    {
        if ($path === null) {
            return null;
        }

        return TargetPlatformProfile::fromFile($path);
    }

    private function targetPlatformProfilePath(): ?string
    {
        $values = array_values((array) $this->option('target-platform-profile'));
        if ($values === []) {
            return null;
        }

        if (count($values) !== 1) {
            throw new \InvalidArgumentException('Option "--target-platform-profile" may only be specified once.');
        }

        if (!is_string($values[0])) {
            throw new \InvalidArgumentException('Option "--target-platform-profile" must be a string.');
        }

        return $values[0];
    }

    private function diagnostic(string $message): void
    {
        $this->output->getErrorStyle()->writeln(
            SensitiveOutputRedactor::redact($message),
            OutputInterface::OUTPUT_RAW
        );
    }

    private function positiveIntegerOption(string $name): int
    {
        $value = $this->option($name);
        if (!is_string($value) || $value === '' || !ctype_digit($value)) {
            throw new \InvalidArgumentException(sprintf('Option "--%s" must be a positive integer.', $name));
        }

        return (int) $value;
    }
}
