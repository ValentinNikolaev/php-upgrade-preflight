<?php

declare(strict_types=1);

namespace PhpUpgradePreflight\Cli;

use PhpUpgradePreflight\Core\Model\ReportFormat;

final class CommandLineParser
{
    /**
     * @param list<string> $argv
     * @return array{
     *     path: string,
     *     target: list<string>,
     *     target-php: ?string,
     *     from-php: ?string,
     *     source: list<string>,
     *     framework: list<string>,
     *     format: string,
     *     output: ?string,
     *     debug: bool
     * }
     */
    public function parse(array $argv): array
    {
        $arguments = array_slice($argv, 1);
        $command = array_shift($arguments);

        if ($command !== 'analyze') {
            throw new \InvalidArgumentException($command === null
                ? 'The "analyze" command is required.'
                : sprintf('Unknown command "%s"; expected "analyze".', $command));
        }

        $workingDirectory = getcwd();
        if ($workingDirectory === false) {
            throw new \RuntimeException('Unable to determine the current working directory.');
        }

        $options = [
            'path' => $workingDirectory,
            'target' => [],
            'target-php' => null,
            'from-php' => null,
            'source' => [],
            'framework' => [],
            'format' => ReportFormat::JSON,
            'output' => null,
            'debug' => false,
        ];
        $seen = [];

        foreach ($arguments as $argument) {
            if ($argument === '--debug') {
                if (isset($seen['debug'])) {
                    throw new \InvalidArgumentException('Option "--debug" may only be specified once.');
                }

                $seen['debug'] = true;
                $options['debug'] = true;
                continue;
            }

            if (!str_starts_with($argument, '--') || !str_contains($argument, '=')) {
                throw new \InvalidArgumentException(sprintf('Unsupported argument "%s".', $argument));
            }

            [$name, $value] = explode('=', substr($argument, 2), 2);

            if ($name === 'debug') {
                throw new \InvalidArgumentException('Option "--debug" does not accept a value.');
            }

            if (in_array($name, ['target', 'source', 'framework'], true)) {
                $options[$name][] = $value;
                continue;
            }

            if (!array_key_exists($name, $options)) {
                throw new \InvalidArgumentException(sprintf('Unknown option "--%s".', $name));
            }

            if (isset($seen[$name])) {
                throw new \InvalidArgumentException(sprintf('Option "--%s" may only be specified once.', $name));
            }

            $seen[$name] = true;
            $options[$name] = $value;
        }

        if ($options['target'] === [] && $options['target-php'] === null) {
            throw new \InvalidArgumentException('At least one --target=package:constraint or --target-php=VERSION option is required.');
        }

        $options['format'] = ReportFormat::normalize((string) $options['format']);

        return $options;
    }
}
