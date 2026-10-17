<?php

declare(strict_types=1);

namespace PhpUpgradePreflight\Core\Tests\Unit\Source;

use PhpParser\Parser;
use PhpUpgradePreflight\Core\Source\SourceFactsIdentity;
use PHPUnit\Framework\TestCase;

final class SourceFactsIdentityTest extends TestCase
{
    public function testUnreadableCoreImplementationDisablesReuse(): void
    {
        self::assertNull(SourceFactsIdentity::parser($this->createMock(Parser::class), true, static fn (string $path) => false));
    }

    public function testUnavailableParserImplementationMetadataDisablesReuse(): void
    {
        self::assertNull(SourceFactsIdentity::parser($this->createMock(Parser::class), true, null, false));
    }

    public function testUnreadableParserImplementationDisablesReuseEvenWhenCoreIsReadable(): void
    {
        $digest = static function (string $path): string|false {
            return str_contains(str_replace('\\', '/', $path), '/packages/core/src/Source/') ? hash_file('sha256', $path) : false;
        };
        self::assertNull(SourceFactsIdentity::parser($this->createMock(Parser::class), true, $digest));
    }

    public function testFilesystemFailureDisablesReuseRatherThanFailingTheScan(): void
    {
        self::assertNull(SourceFactsIdentity::parser($this->createMock(Parser::class), true, static function (string $path): string|false {
            throw new \RuntimeException('The implementation filesystem became unavailable.');
        }));
    }
}
