<?php

declare(strict_types=1);

namespace PhpUpgradePreflight\Cli;

use PhpUpgradePreflight\Core\Contracts\UpgradeAnalyzer;
use PhpUpgradePreflight\Core\Model\ComposerExecutionConfiguration;
use PhpUpgradePreflight\Core\Model\TargetPlatformProfile;
use PhpUpgradePreflight\Core\Model\UpgradeRequest;
use PhpUpgradePreflight\Core\Model\UpgradeTarget;
use PhpUpgradePreflight\Core\Reporting\ReportFileWriter;
use PhpUpgradePreflight\Core\Reporting\ReportWriterResolver;
use PhpUpgradePreflight\Core\Support\PathExposurePolicy;
use PhpUpgradePreflight\Core\Support\SensitiveOutputRedactor;

final class AnalyzeCommand
{
    public const SUCCESS = 0;
    public const FAILURE = 1;
    public const INVALID = 2;

    private ?UpgradeAnalyzer $analyzer;
    /** @var resource */
    private $stdout;
    /** @var resource */
    private $stderr;
    private ReportFileWriter $reportFileWriter;
    private CommandLineParser $parser;
    private FrameworkIntegrationRegistry $frameworkIntegrations;
    private AnalyzerFactory $analyzerFactory;
    private ReportWriterResolver $reportWriters;

    /**
     * @param resource|null $stdout
     * @param resource|null $stderr
     */
    public function __construct(
        ?UpgradeAnalyzer $analyzer = null,
        mixed $stdout = null,
        mixed $stderr = null,
        ?ReportFileWriter $reportFileWriter = null,
        ?CommandLineParser $parser = null,
        ?FrameworkIntegrationRegistry $frameworkIntegrations = null,
        ?AnalyzerFactory $analyzerFactory = null,
        ?ReportWriterResolver $reportWriters = null
    ) {
        $this->analyzer = $analyzer;
        $this->stdout = $stdout ?? STDOUT;
        $this->stderr = $stderr ?? STDERR;
        $this->reportFileWriter = $reportFileWriter ?? new ReportFileWriter();
        $this->parser = $parser ?? new CommandLineParser();
        $this->frameworkIntegrations = $frameworkIntegrations ?? new FrameworkIntegrationRegistry();
        $this->analyzerFactory = $analyzerFactory ?? new DefaultAnalyzerFactory();
        $this->reportWriters = $reportWriters ?? new ReportWriterResolver();
    }

    /** @param list<string> $argv */
    public function run(array $argv): int
    {
        if (in_array('--help', $argv, true) || in_array('-h', $argv, true)) {
            fwrite($this->stdout, $this->usage());

            return self::SUCCESS;
        }

        try {
            $options = $this->parser->parse($argv);
            $targets = array_map(static fn (string $target): UpgradeTarget => UpgradeTarget::fromString($target), $options['target']);
            $targetPlatformProfile = $this->loadTargetPlatformProfile($options['target-platform-profile'] ?? null);
            $composerExecution = new ComposerExecutionConfiguration(
                $options['composer-executable'] ?? 'composer',
                $options['composer-version'] ?? ComposerExecutionConfiguration::DEFAULT_EXPECTED_VERSION,
                $this->positiveIntegerOption(
                    $options['composer-timeout'] ?? (string) ComposerExecutionConfiguration::DEFAULT_SCENARIO_TIMEOUT_SECONDS,
                    'composer-timeout'
                ),
                $this->positiveIntegerOption(
                    $options['composer-diagnostic-timeout'] ?? (string) ComposerExecutionConfiguration::DEFAULT_DIAGNOSTIC_TIMEOUT_SECONDS,
                    'composer-diagnostic-timeout'
                ),
                $options['composer-mode'] ?? ComposerExecutionConfiguration::MODE_COMPATIBLE
            );
            $request = new UpgradeRequest(
                $options['path'],
                $targets,
                $options['from-php'],
                $options['target-php'],
                $options['source'],
                $options['framework'],
                $options['format'],
                $options['output'],
                $options['debug'],
                $options['extension-assumptions'] ?? [],
                $targetPlatformProfile,
                $composerExecution
            );
            $this->frameworkIntegrations->assertAvailable($request->frameworks());

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
            $analyzer = $this->analyzer ?? $this->analyzerFactory->create($this->frameworkIntegrations->installed());
            // Discovery skips an installed package whose adapter manifest cannot be read
            // instead of ending the run. The skip still has to be visible, or an adapter
            // the user believes is active is silently absent from the report.
            foreach ($this->frameworkIntegrations->discoveryDiagnostics() as $discoveryDiagnostic) {
                $this->diagnostic($discoveryDiagnostic);
            }
            $report = $analyzer->analyzeUpgrade($request);
            $rendered = $this->reportWriters->resolve($request->format())->render($report);

            if ($request->outputPath() !== null) {
                $writtenPath = $this->reportFileWriter->write($request->projectPath(), $request->outputPath(), $rendered);
                fwrite($this->stdout, sprintf(
                    "Wrote report to %s\n",
                    PathExposurePolicy::operationalPath($writtenPath)
                ));
            } else {
                fwrite($this->stdout, $rendered);
            }

            return self::SUCCESS;
        } catch (\Throwable $exception) {
            $this->diagnostic('Analysis failed: ' . $exception->getMessage());

            return self::FAILURE;
        }
    }

    /**
     * The synopsis is specific to this binary; the option block is rendered
     * from the one option vocabulary the parser also validates against.
     */
    private function usage(): string
    {
        return "Usage:\n"
            . "  upgrade-intel analyze --target=package:constraint [options]\n"
            . "  upgrade-intel analyze --target-platform-profile=PATH [options]\n"
            . "\n"
            . "Options:\n"
            . CommandLineOptions::usageLines();
    }

    private function loadTargetPlatformProfile(?string $path): ?TargetPlatformProfile
    {
        if ($path === null) {
            return null;
        }

        return TargetPlatformProfile::fromFile($path);
    }

    private function diagnostic(string $message): void
    {
        fwrite($this->stderr, SensitiveOutputRedactor::redact($message) . PHP_EOL);
    }

    private function positiveIntegerOption(string $value, string $name): int
    {
        if ($value === '' || !ctype_digit($value)) {
            throw new \InvalidArgumentException(sprintf('Option "--%s" must be a positive integer.', $name));
        }

        return (int) $value;
    }
}
