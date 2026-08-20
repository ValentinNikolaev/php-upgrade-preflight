<?php

declare(strict_types=1);

namespace PhpUpgradePreflight\Cli\Tests\Unit;

use PhpUpgradePreflight\Cli\LocalPackageTargetValidator;
use PhpUpgradePreflight\Cli\PackageTargetValidation;
use PHPUnit\Framework\TestCase;
use Symfony\Component\Filesystem\Filesystem;

final class LocalPackageTargetValidatorTest extends TestCase
{
    private Filesystem $filesystem;
    private string $projectPath;

    protected function setUp(): void
    {
        parent::setUp();

        $this->filesystem = new Filesystem();
        $this->projectPath = sys_get_temp_dir() . DIRECTORY_SEPARATOR
            . 'php-upgrade-preflight-local-validator-' . bin2hex(random_bytes(8));
        $this->filesystem->mkdir($this->projectPath);
    }

    protected function tearDown(): void
    {
        $this->filesystem->remove($this->projectPath);

        parent::tearDown();
    }

    public function testItReturnsUnverifiedWhenComposerJsonCannotBeRead(): void
    {
        set_error_handler(static fn (): bool => true);
        try {
            $result = (new LocalPackageTargetValidator())->validate(
                $this->projectPath,
                'vendor/package',
                '^2.0'
            );
        } finally {
            restore_error_handler();
        }

        self::assertSame(PackageTargetValidation::UNVERIFIED, $result->status());
        self::assertStringContainsString('could not be read', $result->message());
    }

    /** @dataProvider invalidComposerProvider */
    public function testItReturnsUnverifiedForInvalidComposerJson(string $contents): void
    {
        $this->filesystem->dumpFile($this->projectPath . DIRECTORY_SEPARATOR . 'composer.json', $contents);

        $result = (new LocalPackageTargetValidator())->validate(
            $this->projectPath,
            'vendor/package',
            '^2.0'
        );

        self::assertSame(PackageTargetValidation::UNVERIFIED, $result->status());
        self::assertStringContainsString('could not be decoded', $result->message());
    }

    /** @return list<array{string}> */
    public function invalidComposerProvider(): array
    {
        return [['{'], ['true']];
    }

    public function testItRecognizesARequireDevPackage(): void
    {
        $this->filesystem->dumpFile($this->projectPath . DIRECTORY_SEPARATOR . 'composer.json', json_encode([
            'require' => 'not-an-object',
            'require-dev' => ['vendor/package' => '^1.0'],
        ], JSON_THROW_ON_ERROR));

        $result = (new LocalPackageTargetValidator())->validate(
            $this->projectPath,
            'vendor/package',
            '^2.0'
        );

        self::assertSame(PackageTargetValidation::FOUND, $result->status());
        self::assertStringContainsString('(require-dev)', $result->message());
    }

    public function testItDoesNotClaimThatANonRootPackageIsMissing(): void
    {
        $this->filesystem->dumpFile($this->projectPath . DIRECTORY_SEPARATOR . 'composer.json', '{}');

        $result = (new LocalPackageTargetValidator())->validate(
            $this->projectPath,
            'vendor/package',
            '^2.0'
        );

        self::assertSame(PackageTargetValidation::UNVERIFIED, $result->status());
        self::assertStringContainsString('repository lookup was not performed', $result->message());
    }
}
