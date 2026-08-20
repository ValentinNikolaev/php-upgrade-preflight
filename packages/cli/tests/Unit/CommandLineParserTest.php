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
            '--target-platform-profile=target-platform.json',
            '--source=src',
            '--source=tests',
            '--framework=laravel',
            '--with-extension=ext-intl:72.1',
            '--with-extension=ext-json',
            '--without-extension=ext-xdebug',
            '--composer-mode=restricted',
            '--composer-executable=/tools/composer.phar',
            '--composer-version=^2.8',
            '--composer-timeout=120',
            '--composer-diagnostic-timeout=15',
            '--save-report=/tmp/report.json',
            '--debug',
        ]);

        self::assertSame(['vendor/package:^2.0'], $options['target']);
        self::assertSame('8.2', $options['target-php']);
        self::assertSame('target-platform.json', $options['target-platform-profile']);
        self::assertSame(['src', 'tests'], $options['source']);
        self::assertSame(['laravel'], $options['framework']);
        self::assertSame(['ext-intl', 'ext-json', 'ext-xdebug'], array_map(
            static fn ($assumption): string => $assumption->name(),
            $options['extension-assumptions']
        ));
        self::assertTrue($options['debug']);
        self::assertSame('restricted', $options['composer-mode']);
        self::assertSame('/tools/composer.phar', $options['composer-executable']);
        self::assertSame('^2.8', $options['composer-version']);
        self::assertSame('120', $options['composer-timeout']);
        self::assertSame('15', $options['composer-diagnostic-timeout']);
        self::assertSame('/tmp/report.json', $options['save-report']);
    }

    public function testTargetPhpAloneIsAValidTargetSelection(): void
    {
        $options = (new CommandLineParser())->parse(['upgrade-intel', 'analyze', '--target-php=8.2']);

        self::assertSame([], $options['target']);
        self::assertSame('8.2', $options['target-php']);
    }

    public function testTargetPlatformProfileAloneIsAValidTargetSelection(): void
    {
        $options = (new CommandLineParser())->parse([
            'upgrade-intel',
            'analyze',
            '--target-platform-profile=target-platform.json',
        ]);

        self::assertSame([], $options['target']);
        self::assertNull($options['target-php']);
        self::assertSame('target-platform.json', $options['target-platform-profile']);
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
            [[
                'upgrade-intel',
                'analyze',
                '--target-platform-profile=first.json',
                '--target-platform-profile=second.json',
            ], 'may only be specified once'],
            [['upgrade-intel', 'analyze', '--target-php=8.2', '--debug=false'], 'does not accept a value'],
            [['upgrade-intel', 'analyze', '--target-php=8.2', '--debug', '--debug'], 'may only be specified once'],
            [[
                'upgrade-intel',
                'analyze',
                '--target-php=8.2',
                '--output=report.json',
                '--save-report=copy.json',
            ], 'cannot be combined'],
            [['upgrade-intel', 'analyze', '--target-php=8.2', '--unsupported=1'], 'Unknown option.'],
            [['upgrade-intel', 'analyze', '--target-php=8.2', '--unsupported'], 'Unsupported argument at position 1.'],
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
