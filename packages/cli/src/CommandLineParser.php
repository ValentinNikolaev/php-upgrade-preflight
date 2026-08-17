<?php

declare(strict_types=1);

namespace PhpUpgradePreflight\Cli;

use PhpUpgradePreflight\Core\Model\ExtensionAssumption;
use PhpUpgradePreflight\Core\Model\ExtensionAssumptionSet;
use PhpUpgradePreflight\Core\Model\ReportFormat;

/**
 * Parses `upgrade-intel analyze` arguments against the one option vocabulary.
 *
 * Defaults, repetition rules, the accepted-name allow-list, and value
 * assignment are all driven by {@see CommandLineOptions}, so an option cannot
 * be accepted without being documented.
 *
 * @phpstan-type ParsedOptions array{
 *     path: string,
 *     target: list<string>,
 *     target-php: ?string,
 *     target-platform-profile?: string,
 *     from-php: ?string,
 *     source: list<string>,
 *     framework: list<string>,
 *     extension-assumptions?: list<ExtensionAssumption>,
 *     format: string,
 *     output: ?string,
 *     debug: bool,
 *     composer-mode?: string,
 *     composer-executable?: string,
 *     composer-version?: string,
 *     composer-timeout?: string,
 *     composer-diagnostic-timeout?: string
 * }
 */
final class CommandLineParser
{
    /**
     * @param list<string> $argv
     * @return ParsedOptions
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

        $modes = CommandLineOptions::parseModes();
        $defaults = CommandLineOptions::defaults();
        $defaults['path'] = $workingDirectory;

        $seen = [];
        $repeated = [];
        $values = [];
        $flags = [];
        $presentExtensions = [];
        $absentExtensions = [];

        foreach ($arguments as $index => $argument) {
            $flag = $this->flagName($argument, $modes);
            if ($flag !== null) {
                $this->assertNotSeen($flag, $seen);
                $seen[$flag] = true;
                $flags[$flag] = true;
                continue;
            }

            if (!str_starts_with($argument, '--') || !str_contains($argument, '=')) {
                throw new \InvalidArgumentException(sprintf('Unsupported argument at position %d.', $index));
            }

            [$name, $value] = explode('=', substr($argument, 2), 2);
            $mode = $modes[$name] ?? null;

            if ($mode === CommandLineOption::MODE_FLAG) {
                throw new \InvalidArgumentException(sprintf('Option "--%s" does not accept a value.', $name));
            }

            if ($mode === CommandLineOption::MODE_EXTENSION_PRESENT) {
                $presentExtensions[] = $value;
                continue;
            }

            if ($mode === CommandLineOption::MODE_EXTENSION_ABSENT) {
                $absentExtensions[] = $value;
                continue;
            }

            if ($mode === CommandLineOption::MODE_LIST) {
                $list = $repeated[$name] ?? [];
                $list[] = $value;
                $repeated[$name] = $list;
                continue;
            }

            if ($mode === null) {
                throw new \InvalidArgumentException('Unknown option.');
            }

            $this->assertNotSeen($name, $seen);
            $seen[$name] = true;
            $values[$name] = $value;
        }

        /** @var ParsedOptions $options */
        $options = array_merge($defaults, $repeated, $values, $flags);

        if ($options['target'] === [] && $options['target-php'] === null && !isset($options['target-platform-profile'])) {
            throw new \InvalidArgumentException(
                'At least one --target=package:constraint, --target-php=VERSION, or --target-platform-profile=PATH option is required.'
            );
        }

        $options['format'] = ReportFormat::normalize((string) $options['format']);
        $extensionAssumptions = ExtensionAssumptionSet::fromInputs($presentExtensions, $absentExtensions)->all();
        if ($extensionAssumptions !== []) {
            $options['extension-assumptions'] = $extensionAssumptions;
        }

        return $options;
    }

    /**
     * The option name of a valueless switch, or null when the argument is not one.
     *
     * @param array<string, string> $modes
     */
    private function flagName(string $argument, array $modes): ?string
    {
        if (!str_starts_with($argument, '--') || str_contains($argument, '=')) {
            return null;
        }

        $name = substr($argument, 2);

        return ($modes[$name] ?? null) === CommandLineOption::MODE_FLAG ? $name : null;
    }

    /** @param array<string, bool> $seen */
    private function assertNotSeen(string $name, array $seen): void
    {
        if (isset($seen[$name])) {
            throw new \InvalidArgumentException(sprintf('Option "--%s" may only be specified once.', $name));
        }
    }
}
