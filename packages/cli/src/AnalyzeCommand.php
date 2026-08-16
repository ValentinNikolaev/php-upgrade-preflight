<?php

declare(strict_types=1);

namespace PhpUpgradePreflight\Cli;

use PhpUpgradePreflight\Core\Contracts\UpgradeAnalyzer;
use PhpUpgradePreflight\Core\Model\ComposerExecutionConfiguration;
use PhpUpgradePreflight\Core\Model\ReportFormat;
use PhpUpgradePreflight\Core\Model\TargetPlatformProfile;
use PhpUpgradePreflight\Core\Model\UpgradeRequest;
use PhpUpgradePreflight\Core\Model\UpgradeTarget;
use PhpUpgradePreflight\Core\Reporting\JsonReportWriter;
use PhpUpgradePreflight\Core\Reporting\MarkdownReportWriter;
use PhpUpgradePreflight\Core\Reporting\ReportFileWriter;
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
        ?AnalyzerFactory $analyzerFactory = null
    ) {
        $this->analyzer = $analyzer;
        $this->stdout = $stdout ?? STDOUT;
        $this->stderr = $stderr ?? STDERR;
        $this->reportFileWriter = $reportFileWriter ?? new ReportFileWriter();
        $this->parser = $parser ?? new CommandLineParser();
        $this->frameworkIntegrations = $frameworkIntegrations ?? new FrameworkIntegrationRegistry();
        $this->analyzerFactory = $analyzerFactory ?? new DefaultAnalyzerFactory();
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
            $report = $analyzer->analyzeUpgrade($request);
            $rendered = $request->format() === ReportFormat::MARKDOWN
                ? (new MarkdownReportWriter())->render($report)
                : (new JsonReportWriter())->render($report);

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

    private function usage(): string
    {
        return <<<'USAGE'
Usage:
  upgrade-intel analyze --target=package:constraint [options]
  upgrade-intel analyze --target-platform-profile=PATH [options]

Options:
  --path=PATH             Project path to analyze (default: current directory)
  --target=PACKAGE:VALUE  Target package constraint; repeatable
  --target-php=VERSION    Explicit target PHP platform version
  --target-platform-profile=PATH
                          JSON target-platform profile file
  --from-php=VALUE        Current project PHP version
  --with-extension=EXT[:VERSION]
                          Assume an extension is present; repeatable
  --without-extension=EXT Assume an extension is absent; repeatable
  --source=PATH           Additional source path to scan; repeatable
  --framework=NAME        Framework integration to enable; repeatable
  --format=json|markdown  Report format (default: json)
  --output=PATH           Write the report to a file
  --composer-mode=MODE    compatible or restricted (default: compatible)
  --composer-executable=PATH
                          Composer command or executable path (default: composer)
  --composer-version=RANGE
                          Expected Composer constraint (default: >=2.0.0 <3.0.0)
  --composer-timeout=SEC  Scenario timeout from 1 to 3600 seconds (default: 300)
  --composer-diagnostic-timeout=SEC
                          Diagnostic timeout from 1 to 900 seconds (default: 60)
  --debug                 Preserve temporary Composer workspaces
  -h, --help              Show this help

USAGE;
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
