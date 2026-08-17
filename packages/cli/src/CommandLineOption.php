<?php

declare(strict_types=1);

namespace PhpUpgradePreflight\Cli;

/**
 * One documented `upgrade-intel analyze` option.
 *
 * A single entry carries the whole vocabulary for that option: how the parser
 * accepts it, whether it seeds a default into the parse result, and the two
 * strings the help text prints.
 */
final class CommandLineOption
{
    /** Single-valued option, rejected when repeated. */
    public const MODE_VALUE = 'value';
    /** Repeatable option collected into a list. */
    public const MODE_LIST = 'list';
    /** Valueless switch, rejected when repeated or given a value. */
    public const MODE_FLAG = 'flag';
    /** Repeatable assumption that a PHP extension is present. */
    public const MODE_EXTENSION_PRESENT = 'extension-present';
    /** Repeatable assumption that a PHP extension is absent. */
    public const MODE_EXTENSION_ABSENT = 'extension-absent';
    /** Documented in the help text but handled before parsing. */
    public const MODE_HELP = 'help';

    private string $name;
    private string $syntax;
    private string $usage;
    private string $mode;
    private bool $seedsDefault;
    /** @var string|bool|list<string>|null */
    private $default;

    /** @param string|bool|list<string>|null $default */
    private function __construct(
        string $name,
        string $syntax,
        string $usage,
        string $mode,
        bool $seedsDefault,
        $default
    ) {
        $this->name = $name;
        $this->syntax = $syntax;
        $this->usage = $usage;
        $this->mode = $mode;
        $this->seedsDefault = $seedsDefault;
        $this->default = $default;
    }

    /** Single-valued option that always appears in the parse result. */
    public static function value(string $name, string $syntax, string $usage, ?string $default): self
    {
        return new self($name, $syntax, $usage, self::MODE_VALUE, true, $default);
    }

    /** Single-valued option that appears in the parse result only when supplied. */
    public static function optionalValue(string $name, string $syntax, string $usage): self
    {
        return new self($name, $syntax, $usage, self::MODE_VALUE, false, null);
    }

    /** Repeatable option seeded with an empty list. */
    public static function repeatable(string $name, string $syntax, string $usage): self
    {
        return new self($name, $syntax, $usage, self::MODE_LIST, true, []);
    }

    /** Valueless switch seeded with false. */
    public static function flag(string $name, string $syntax, string $usage): self
    {
        return new self($name, $syntax, $usage, self::MODE_FLAG, true, false);
    }

    /** Repeatable extension-present assumption collected outside the parse result. */
    public static function presentExtension(string $name, string $syntax, string $usage): self
    {
        return new self($name, $syntax, $usage, self::MODE_EXTENSION_PRESENT, false, null);
    }

    /** Repeatable extension-absent assumption collected outside the parse result. */
    public static function absentExtension(string $name, string $syntax, string $usage): self
    {
        return new self($name, $syntax, $usage, self::MODE_EXTENSION_ABSENT, false, null);
    }

    /** Entry printed in the help text but handled before parsing. */
    public static function documented(string $name, string $syntax, string $usage): self
    {
        return new self($name, $syntax, $usage, self::MODE_HELP, false, null);
    }

    public function name(): string
    {
        return $this->name;
    }

    public function syntax(): string
    {
        return $this->syntax;
    }

    public function usage(): string
    {
        return $this->usage;
    }

    public function mode(): string
    {
        return $this->mode;
    }

    public function seedsDefault(): bool
    {
        return $this->seedsDefault;
    }

    /** @return string|bool|list<string>|null */
    public function defaultValue()
    {
        return $this->default;
    }
}
