<?php

declare(strict_types=1);

namespace PhpUpgradePreflight\Cli;

use PhpUpgradePreflight\Core\Analysis\DefaultUpgradeAnalyzer;
use PhpUpgradePreflight\Core\Model\ReportFormat;
use PhpUpgradePreflight\Core\Model\UpgradeRequest;
use PhpUpgradePreflight\Core\Model\UpgradeTarget;
use PhpUpgradePreflight\Core\Reporting\JsonReportWriter;
use PhpUpgradePreflight\Core\Reporting\MarkdownReportWriter;

final class AnalyzeCommand
{
    /** @param list<string> $argv */
    public function run(array $argv): int
    {
        try {
            $options = $this->parse($argv);
            $targets = array_map(static fn (string $target): UpgradeTarget => UpgradeTarget::fromString($target), $options['target']);
            $request = new UpgradeRequest(
                $options['path'],
                $targets,
                $options['from-php'],
                $this->targetPhp($targets, $options['target-php']),
                $options['source'],
                $options['framework'],
                $options['format'],
                $options['output'],
                $options['debug']
            );

            $report = (new DefaultUpgradeAnalyzer())->analyzeUpgrade($request);
            $rendered = $request->format === ReportFormat::MARKDOWN
                ? (new MarkdownReportWriter())->render($report)
                : (new JsonReportWriter())->render($report);

            if ($request->outputPath !== null) {
                file_put_contents($request->outputPath, $rendered);
                fwrite(STDOUT, sprintf("Wrote report to %s\n", $request->outputPath));
            } else {
                fwrite(STDOUT, $rendered);
            }

            return 0;
        } catch (\Throwable $exception) {
            fwrite(STDERR, $exception->getMessage() . PHP_EOL);

            return 1;
        }
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

    /** @param list<UpgradeTarget> $targets */
    private function targetPhp(array $targets, ?string $explicit): ?string
    {
        if ($explicit !== null) {
            return $explicit;
        }

        foreach ($targets as $target) {
            if ($target->package === 'php') {
                return $target->constraint;
            }
        }

        return null;
    }
}
