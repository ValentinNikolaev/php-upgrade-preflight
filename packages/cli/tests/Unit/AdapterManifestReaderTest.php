<?php

declare(strict_types=1);

namespace PhpUpgradePreflight\Cli\Tests\Unit;

use PhpUpgradePreflight\Cli\AdapterManifestReader;
use PHPUnit\Framework\TestCase;
use Symfony\Component\Filesystem\Filesystem;

final class AdapterManifestReaderTest extends TestCase
{
    /** @var list<string> */
    private array $temporaryDirectories = [];

    protected function tearDown(): void
    {
        (new Filesystem())->remove($this->temporaryDirectories);
        $this->temporaryDirectories = [];

        parent::tearDown();
    }

    public function testItReadsAdvertisedClassesInManifestOrder(): void
    {
        $package = $this->package(['Vendor\\Second', 'Vendor\\First']);

        self::assertSame(
            ['Vendor\\Second', 'Vendor\\First'],
            (new AdapterManifestReader())->advertisedClasses('vendor/adapter', $package)
        );
    }

    /** @dataProvider silentPackageProvider */
    public function testAPackageWithoutAnAdapterRegistrationAdvertisesNothing(string $contents): void
    {
        $package = $this->temporaryDirectory();
        file_put_contents($package . DIRECTORY_SEPARATOR . 'composer.json', $contents);

        self::assertSame([], (new AdapterManifestReader())->advertisedClasses('vendor/quiet', $package));
    }

    /** @return iterable<string, array{string}> */
    public function silentPackageProvider(): iterable
    {
        yield 'no plugin metadata' => ['{"name":"vendor/quiet"}'];
        yield 'plugin metadata without adapters' => ['{"extra":{"php-upgrade-preflight":{}}}'];
        yield 'unrelated extra' => ['{"extra":{"other-plugin":{"framework-adapters":["Vendor\\\\X"]}}}'];
    }

    public function testAPackageWithoutAManifestAdvertisesNothing(): void
    {
        $package = $this->temporaryDirectory();
        unlink($package . DIRECTORY_SEPARATOR . 'composer.json');

        self::assertSame([], (new AdapterManifestReader())->advertisedClasses('vendor/absent', $package));
    }

    /**
     * The reader keeps rejecting every malformed manifest. Deciding whether a rejection
     * ends the run belongs to {@see \PhpUpgradePreflight\Cli\FrameworkIntegrationRegistry},
     * which skips that package and reports it instead.
     *
     * @dataProvider rejectedManifestProvider
     */
    public function testItFailsClosedOnAMalformedManifest(string $contents, string $message): void
    {
        $package = $this->temporaryDirectory();
        file_put_contents($package . DIRECTORY_SEPARATOR . 'composer.json', $contents);

        $this->expectException(\LogicException::class);
        $this->expectExceptionMessage($message);

        (new AdapterManifestReader())->advertisedClasses('vendor/broken', $package);
    }

    /** @return iterable<string, array{string, string}> */
    public function rejectedManifestProvider(): iterable
    {
        yield 'invalid JSON' => ['{', 'Invalid Composer metadata for installed package "vendor/broken"'];
        yield 'non-object manifest' => ['null', 'Composer metadata for installed package "vendor/broken" must be an object.'];
        yield 'list-shaped manifest' => ['[]', 'Composer metadata for installed package "vendor/broken" must be an object.'];
        yield 'list-shaped extra' => [
            '{"extra":[]}',
            'Composer metadata extra for package "vendor/broken" must be an object.',
        ];
        yield 'non-empty list-shaped extra' => [
            '{"extra":["unexpected"]}',
            'Composer metadata extra for package "vendor/broken" must be an object.',
        ];
        yield 'scalar plugin metadata' => [
            '{"extra":{"php-upgrade-preflight":"nope"}}',
            'Composer metadata extra.php-upgrade-preflight for package "vendor/broken" must be an object.',
        ];
        yield 'list-shaped plugin metadata' => [
            '{"extra":{"php-upgrade-preflight":[]}}',
            'Composer metadata extra.php-upgrade-preflight for package "vendor/broken" must be an object.',
        ];
        yield 'non-empty list-shaped plugin metadata' => [
            '{"extra":{"php-upgrade-preflight":["unexpected"]}}',
            'Composer metadata extra.php-upgrade-preflight for package "vendor/broken" must be an object.',
        ];
        yield 'object-shaped registration' => [
            '{"extra":{"php-upgrade-preflight":{"framework-adapters":{"first":"Vendor\\\\X"}}}}',
            'must be a non-empty list of class names',
        ];
        yield 'empty registration' => [
            '{"extra":{"php-upgrade-preflight":{"framework-adapters":[]}}}',
            'must be a non-empty list of class names',
        ];
        yield 'non-string class name' => [
            '{"extra":{"php-upgrade-preflight":{"framework-adapters":[42]}}}',
            'must contain only non-empty class names without surrounding whitespace',
        ];
        yield 'padded class name' => [
            '{"extra":{"php-upgrade-preflight":{"framework-adapters":[" Vendor\\\\X"]}}}',
            'must contain only non-empty class names without surrounding whitespace',
        ];
    }

    /** @param list<string> $advertised */
    private function package(array $advertised): string
    {
        $directory = $this->temporaryDirectory();
        file_put_contents(
            $directory . DIRECTORY_SEPARATOR . 'composer.json',
            json_encode([
                'name' => 'vendor/adapter',
                'extra' => ['php-upgrade-preflight' => ['framework-adapters' => $advertised]],
            ], JSON_THROW_ON_ERROR)
        );

        return $directory;
    }

    private function temporaryDirectory(): string
    {
        $directory = rtrim(sys_get_temp_dir(), DIRECTORY_SEPARATOR)
            . DIRECTORY_SEPARATOR . 'php-upgrade-preflight-manifest-' . bin2hex(random_bytes(8));
        mkdir($directory, 0700, true);
        file_put_contents($directory . DIRECTORY_SEPARATOR . 'composer.json', '{}');
        $this->temporaryDirectories[] = $directory;

        return $directory;
    }
}
