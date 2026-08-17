<?php

declare(strict_types=1);

namespace PhpUpgradePreflight\Cli\Tests\Unit;

use PhpUpgradePreflight\Cli\CommandLineOption;
use PhpUpgradePreflight\Cli\CommandLineOptions;
use PhpUpgradePreflight\Cli\CommandLineParser;
use PHPUnit\Framework\TestCase;

final class CommandLineOptionsTest extends TestCase
{
    public function testSeededDefaultsKeepTheCanonicalParseResultOrder(): void
    {
        self::assertSame(
            ['path', 'target', 'target-php', 'from-php', 'source', 'framework', 'format', 'output', 'debug'],
            array_keys(CommandLineOptions::defaults())
        );
        self::assertSame(
            [null, [], null, null, [], [], 'json', null, false],
            array_values(CommandLineOptions::defaults())
        );
    }

    public function testOptionsHandledBeforeParsingAreDocumentedButNotParseable(): void
    {
        self::assertArrayNotHasKey('help', CommandLineOptions::parseModes());
        self::assertStringContainsString('-h, --help', CommandLineOptions::usageLines());

        $this->expectException(\InvalidArgumentException::class);
        $this->expectExceptionMessage('Unknown option.');

        (new CommandLineParser())->parse(['upgrade-intel', 'analyze', '--target-php=8.2', '--help=1']);
    }

    public function testEveryDocumentedOptionIsAcceptedByTheParser(): void
    {
        $argv = ['upgrade-intel', 'analyze'];
        $documented = [];
        foreach (CommandLineOptions::all() as $option) {
            if ($option->mode() === CommandLineOption::MODE_HELP) {
                continue;
            }

            $documented[] = $option->name();
            $argv[] = $option->mode() === CommandLineOption::MODE_FLAG
                ? '--' . $option->name()
                : '--' . $option->name() . '=' . $this->sampleValue($option->name());
        }

        $options = (new CommandLineParser())->parse($argv);

        self::assertSame(['ext-intl', 'ext-xdebug'], array_map(
            static fn ($assumption): string => $assumption->name(),
            $options['extension-assumptions']
        ));
        foreach ($documented as $name) {
            if (in_array($name, ['with-extension', 'without-extension'], true)) {
                continue;
            }

            self::assertArrayHasKey($name, $options, $name);
        }
    }

    public function testTheParseResultExposesNoUndocumentedKeys(): void
    {
        $documented = array_map(
            static fn (CommandLineOption $option): string => $option->name(),
            CommandLineOptions::all()
        );
        $documented[] = 'extension-assumptions';

        $options = (new CommandLineParser())->parse([
            'upgrade-intel',
            'analyze',
            '--target-php=8.2',
            '--composer-mode=restricted',
            '--with-extension=ext-intl',
        ]);

        foreach (array_keys($options) as $key) {
            self::assertContains($key, $documented, $key);
        }
    }

    public function testEveryDocumentedOptionIsRenderedAtTheSameDescriptionColumn(): void
    {
        $block = CommandLineOptions::usageLines();
        self::assertStringEndsWith("\n", $block);
        $lines = explode("\n", rtrim($block, "\n"));

        foreach (CommandLineOptions::all() as $option) {
            self::assertStringContainsString('  ' . $option->syntax(), $block, $option->syntax());

            $rendered = $this->renderedLine($lines, $option->usage());
            self::assertSame(26, strlen($rendered) - strlen($option->usage()), $option->syntax());
        }
    }

    /** @param list<string> $lines */
    private function renderedLine(array $lines, string $usage): string
    {
        $matches = array_values(array_filter(
            $lines,
            static fn (string $line): bool => str_ends_with($line, $usage)
        ));
        self::assertCount(1, $matches, $usage);

        return $matches[0];
    }

    private function sampleValue(string $name): string
    {
        if ($name === 'format') {
            return 'markdown';
        }
        if ($name === 'with-extension') {
            return 'ext-intl';
        }
        if ($name === 'without-extension') {
            return 'ext-xdebug';
        }

        return 'sample-' . $name;
    }
}
