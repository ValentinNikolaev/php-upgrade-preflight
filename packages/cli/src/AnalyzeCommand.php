<?php

declare(strict_types=1);

namespace PhpUpgradePreflight\Cli;

use PhpUpgradePreflight\Core\Contracts\UpgradeAnalyzer;
use PhpUpgradePreflight\Core\Model\ReportFormat;
use PhpUpgradePreflight\Core\Model\UpgradeRequest;
use PhpUpgradePreflight\Core\Model\UpgradeTarget;
use PhpUpgradePreflight\Core\Reporting\JsonReportWriter;
use PhpUpgradePreflight\Core\Reporting\MarkdownReportWriter;
use PhpUpgradePreflight\Core\Reporting\ReportFileWriter;

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
            $request = new UpgradeRequest(
                $options['path'],
                $targets,
                $options['from-php'],
                $options['target-php'],
                $options['source'],
                $options['framework'],
                $options['format'],
                $options['output'],
                $options['debug']
            );
            $this->frameworkIntegrations->assertAvailable($request->frameworks());

            if ($request->outputPath() !== null) {
                $this->reportFileWriter->validateDestination($request->projectPath(), $request->outputPath());
            }
        } catch (\InvalidArgumentException $exception) {
            fwrite($this->stderr, 'Invalid invocation: ' . $exception->getMessage() . PHP_EOL);

            return self::INVALID;
        } catch (\Throwable $exception) {
            fwrite($this->stderr, 'Analysis failed: ' . $exception->getMessage() . PHP_EOL);

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
                fwrite($this->stdout, sprintf("Wrote report to %s\n", $writtenPath));
            } else {
                fwrite($this->stdout, $rendered);
            }

            return self::SUCCESS;
        } catch (\Throwable $exception) {
            fwrite($this->stderr, 'Analysis failed: ' . $exception->getMessage() . PHP_EOL);

            return self::FAILURE;
        }
    }

    private function usage(): string
    {
        return <<<'USAGE'
Usage:
  upgrade-intel analyze --target=package:constraint [options]

Options:
  --path=PATH             Project path to analyze (default: current directory)
  --target=PACKAGE:VALUE  Target package constraint; repeatable
  --target-php=VERSION    Explicit target PHP platform version
  --from-php=VALUE        Current project PHP version
  --source=PATH           Additional source path to scan; repeatable
  --framework=NAME        Framework integration to enable; repeatable
  --format=json|markdown  Report format (default: json)
  --output=PATH           Write the report to a file
  --debug                 Preserve temporary Composer workspaces
  -h, --help              Show this help

USAGE;
    }
}
