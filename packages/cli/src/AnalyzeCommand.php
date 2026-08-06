<?php

declare(strict_types=1);

namespace PhpUpgradePreflight\Cli;

use PhpUpgradePreflight\Core\Analysis\DefaultUpgradeAnalyzer;
use PhpUpgradePreflight\Core\Contracts\UpgradeAnalyzer;
use PhpUpgradePreflight\Core\Model\ReportFormat;
use PhpUpgradePreflight\Core\Model\UpgradeRequest;
use PhpUpgradePreflight\Core\Model\UpgradeTarget;
use PhpUpgradePreflight\Core\Reporting\JsonReportWriter;
use PhpUpgradePreflight\Core\Reporting\MarkdownReportWriter;
use PhpUpgradePreflight\Core\Reporting\ReportFileWriter;

final class AnalyzeCommand
{
    private UpgradeAnalyzer $analyzer;
    /** @var resource */
    private $stdout;
    /** @var resource */
    private $stderr;
    private ReportFileWriter $reportFileWriter;

    /**
     * @param resource|null $stdout
     * @param resource|null $stderr
     */
    public function __construct(?UpgradeAnalyzer $analyzer = null, mixed $stdout = null, mixed $stderr = null, ?ReportFileWriter $reportFileWriter = null)
    {
        $this->analyzer = $analyzer ?? new DefaultUpgradeAnalyzer((new FrameworkIntegrationRegistry())->installed());
        $this->stdout = $stdout ?? STDOUT;
        $this->stderr = $stderr ?? STDERR;
        $this->reportFileWriter = $reportFileWriter ?? new ReportFileWriter();
    }

    /** @param list<string> $argv */
    public function run(array $argv): int
    {
        if (in_array('--help', $argv, true) || in_array('-h', $argv, true)) {
            fwrite($this->stdout, $this->usage());

            return 0;
        }

        try {
            $options = $this->parse($argv);
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

            $report = $this->analyzer->analyzeUpgrade($request);
            $rendered = $request->format() === ReportFormat::MARKDOWN
                ? (new MarkdownReportWriter())->render($report)
                : (new JsonReportWriter())->render($report);

            if ($request->outputPath() !== null) {
                $writtenPath = $this->reportFileWriter->write($request->projectPath(), $request->outputPath(), $rendered);
                fwrite($this->stdout, sprintf("Wrote report to %s\n", $writtenPath));
            } else {
                fwrite($this->stdout, $rendered);
            }

            return 0;
        } catch (\Throwable $exception) {
            fwrite($this->stderr, $exception->getMessage() . PHP_EOL);

            return 1;
        }
    }

    private function usage(): string
    {
        return <<<'USAGE'
Usage:
  upgrade-intel analyze --target=package:constraint [options]

Options:
  --path=PATH             Project path to analyze (default: current directory)
  --target=PACKAGE:VALUE  Target package constraint; repeatable and required
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

    /** @param list<string> $argv @return array<string, mixed> */
    private function parse(array $argv): array
    {
        $options = [
            'path' => getcwd() ?: '.',
            'target' => [],
            'target-php' => null,
            'from-php' => null,
            'source' => [],
            'framework' => [],
            'format' => ReportFormat::JSON,
            'output' => null,
            'debug' => false,
        ];

        foreach (array_slice($argv, 1) as $argument) {
            if ($argument === 'analyze') {
                continue;
            }

            if ($argument === '--debug') {
                $options['debug'] = true;
                continue;
            }

            if (!str_starts_with($argument, '--') || !str_contains($argument, '=')) {
                throw new \InvalidArgumentException(sprintf('Unsupported argument "%s".', $argument));
            }

            [$name, $value] = explode('=', substr($argument, 2), 2);
            if (in_array($name, ['target', 'source', 'framework'], true)) {
                $options[$name][] = $value;
            } elseif (array_key_exists($name, $options)) {
                $options[$name] = $value;
            } else {
                throw new \InvalidArgumentException(sprintf('Unknown option "--%s".', $name));
            }
        }

        if ($options['target'] === []) {
            throw new \InvalidArgumentException('At least one --target=package:constraint option is required.');
        }

        $options['format'] = ReportFormat::normalize((string) $options['format']);

        return $options;
    }

}
