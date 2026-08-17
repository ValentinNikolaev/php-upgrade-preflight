<?php

declare(strict_types=1);

namespace PhpUpgradePreflight\Cli;

use PhpUpgradePreflight\Core\Model\ReportFormat;

/**
 * The single `upgrade-intel analyze` option vocabulary.
 *
 * Parse defaults, parse modes, and the help text are all projections of the one
 * table returned by {@see self::all()}, so an option cannot be documented
 * without being accepted, or accepted without being documented.
 */
final class CommandLineOptions
{
    private const INDENT = '  ';
    private const DESCRIPTION_COLUMN = 26;

    /**
     * The complete vocabulary, in help order.
     *
     * Seeded entries also appear in the parse result in this order. `--path` is
     * seeded with null because its documented default, the current working
     * directory, can only be resolved while parsing.
     *
     * @return list<CommandLineOption>
     */
    public static function all(): array
    {
        return [
            CommandLineOption::value(
                'path',
                '--path=PATH',
                'Project path to analyze (default: current directory)',
                null
            ),
            CommandLineOption::repeatable(
                'target',
                '--target=PACKAGE:VALUE',
                'Target package constraint; repeatable'
            ),
            CommandLineOption::value(
                'target-php',
                '--target-php=VERSION',
                'Explicit target PHP platform version',
                null
            ),
            CommandLineOption::optionalValue(
                'target-platform-profile',
                '--target-platform-profile=PATH',
                'JSON target-platform profile file'
            ),
            CommandLineOption::value(
                'from-php',
                '--from-php=VALUE',
                'Current project PHP version',
                null
            ),
            CommandLineOption::presentExtension(
                'with-extension',
                '--with-extension=EXT[:VERSION]',
                'Assume an extension is present; repeatable'
            ),
            CommandLineOption::absentExtension(
                'without-extension',
                '--without-extension=EXT',
                'Assume an extension is absent; repeatable'
            ),
            CommandLineOption::repeatable(
                'source',
                '--source=PATH',
                'Additional source path to scan; repeatable'
            ),
            CommandLineOption::repeatable(
                'framework',
                '--framework=NAME',
                'Framework integration to enable; repeatable'
            ),
            CommandLineOption::value(
                'format',
                '--format=json|markdown',
                'Report format (default: json)',
                ReportFormat::JSON
            ),
            CommandLineOption::value(
                'output',
                '--output=PATH',
                'Write the report to a file',
                null
            ),
            CommandLineOption::optionalValue(
                'composer-mode',
                '--composer-mode=MODE',
                'compatible or restricted (default: compatible)'
            ),
            CommandLineOption::optionalValue(
                'composer-executable',
                '--composer-executable=PATH',
                'Composer command or executable path (default: composer)'
            ),
            CommandLineOption::optionalValue(
                'composer-version',
                '--composer-version=RANGE',
                'Expected Composer constraint (default: >=2.0.0 <3.0.0)'
            ),
            CommandLineOption::optionalValue(
                'composer-timeout',
                '--composer-timeout=SEC',
                'Scenario timeout from 1 to 3600 seconds (default: 300)'
            ),
            CommandLineOption::optionalValue(
                'composer-diagnostic-timeout',
                '--composer-diagnostic-timeout=SEC',
                'Diagnostic timeout from 1 to 900 seconds (default: 60)'
            ),
            CommandLineOption::flag(
                'debug',
                '--debug',
                'Preserve temporary Composer workspaces'
            ),
            CommandLineOption::documented(
                'help',
                '-h, --help',
                'Show this help'
            ),
        ];
    }

    /**
     * Seeded parse-result defaults, in canonical key order.
     *
     * Values are heterogeneous by design: a string, a list, a bool, or null,
     * depending on the entry that seeded them.
     *
     * @return array<string, mixed>
     */
    public static function defaults(): array
    {
        $defaults = [];
        foreach (self::all() as $option) {
            if ($option->seedsDefault()) {
                $defaults[$option->name()] = $option->defaultValue();
            }
        }

        return $defaults;
    }

    /**
     * Parse mode by option name; entries handled before parsing are excluded.
     *
     * @return array<string, string>
     */
    public static function parseModes(): array
    {
        $modes = [];
        foreach (self::all() as $option) {
            if ($option->mode() !== CommandLineOption::MODE_HELP) {
                $modes[$option->name()] = $option->mode();
            }
        }

        return $modes;
    }

    /**
     * The option block of the help text, one entry per documented option.
     *
     * Descriptions align at a fixed column; a syntax too wide to leave a gap
     * takes its own line and the description is indented to the same column.
     */
    public static function usageLines(): string
    {
        $lines = '';
        foreach (self::all() as $option) {
            $syntax = self::INDENT . $option->syntax();
            $lines .= strlen($syntax) < self::DESCRIPTION_COLUMN
                ? $syntax . str_repeat(' ', self::DESCRIPTION_COLUMN - strlen($syntax))
                : $syntax . "\n" . str_repeat(' ', self::DESCRIPTION_COLUMN);
            $lines .= $option->usage() . "\n";
        }

        return $lines;
    }
}
