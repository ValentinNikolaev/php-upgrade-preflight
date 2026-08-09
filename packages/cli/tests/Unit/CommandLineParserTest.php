<?php

declare(strict_types=1);

namespace PhpUpgradePreflight\Cli\Tests\Unit;

use PhpUpgradePreflight\Cli\CommandLineParser;
use PHPUnit\Framework\TestCase;

final class CommandLineParserTest extends TestCase
{
    public function testItParsesTheSupportedCommandWithoutAConsoleDependency(): void
    {
        $options = (new CommandLineParser())->parse([
            'upgrade-intel',
            'analyze',
            '--target=vendor/package:^2.0',
            '--target-php=8.2',
            '--source=src',
            '--source=tests',
            '--framework=laravel',
            '--with-extension=ext-intl:72.1',
            '--with-extension=ext-json',
            '--without-extension=ext-xdebug',
            '--debug',
        ]);

        self::assertSame(['vendor/package:^2.0'], $options['target']);
        self::assertSame('8.2', $options['target-php']);
        self::assertSame(['src', 'tests'], $options['source']);
        self::assertSame(['laravel'], $options['framework']);
        self::assertSame(['ext-intl', 'ext-json', 'ext-xdebug'], array_map(
            static fn ($assumption): string => $assumption->name(),
            $options['extension-assumptions']
        ));
        self::assertTrue($options['debug']);
    }

    public function testTargetPhpAloneIsAValidTargetSelection(): void
    {
        $options = (new CommandLineParser())->parse(['upgrade-intel', 'analyze', '--target-php=8.2']);

        self::assertSame([], $options['target']);
        self::assertSame('8.2', $options['target-php']);
    }

    /**
     * @dataProvider invalidArgumentsProvider
     * @param list<string> $argv
     */
    public function testItRejectsInvalidOrAmbiguousArguments(array $argv, string $message): void
    {
        $this->expectException(\InvalidArgumentException::class);
        $this->expectExceptionMessage($message);

        (new CommandLineParser())->parse($argv);
    }

    /** @return list<array{list<string>, string}> */
    public function invalidArgumentsProvider(): array
    {
        return [
            [['upgrade-intel'], '"analyze" command is required'],
            [['upgrade-intel', 'scan', '--target-php=8.2'], 'Unknown command "scan"'],
            [['upgrade-intel', 'analyze'], 'At least one --target'],
            [['upgrade-intel', 'analyze', '--target-php=8.2', '--format=yaml'], 'Unsupported report format'],
            [['upgrade-intel', 'analyze', '--target-php=8.2', '--target-php=8.3'], 'may only be specified once'],
            [['upgrade-intel', 'analyze', '--target-php=8.2', '--debug=false'], 'does not accept a value'],
            [[
                'upgrade-intel',
                'analyze',
                '--target-php=8.2',
                '--with-extension=ext-json:8.2.0',
                '--without-extension=EXT-JSON',
            ], 'may only be specified once'],
        ];
    }
}
