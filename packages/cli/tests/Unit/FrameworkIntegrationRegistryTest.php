<?php

declare(strict_types=1);

namespace PhpUpgradePreflight\Cli\Tests\Unit;

use PhpUpgradePreflight\Cli\FrameworkIntegrationRegistry;
use PhpUpgradePreflight\Core\Framework\FrameworkDetection;
use PhpUpgradePreflight\Core\Framework\FrameworkIntegration;
use PhpUpgradePreflight\Core\Model\ProjectState;
use PHPUnit\Framework\TestCase;

final class FrameworkIntegrationRegistryTest extends TestCase
{
    /** @var list<string> */
    private array $temporaryDirectories = [];

    protected function tearDown(): void
    {
        foreach ($this->temporaryDirectories as $directory) {
            $files = new \RecursiveIteratorIterator(
                new \RecursiveDirectoryIterator($directory, \FilesystemIterator::SKIP_DOTS),
                \RecursiveIteratorIterator::CHILD_FIRST
            );
            foreach ($files as $file) {
                if ($file->isDir()) {
                    rmdir($file->getPathname());
                } else {
                    unlink($file->getPathname());
                }
            }
            rmdir($directory);
        }

        $this->temporaryDirectories = [];
    }

    public function testItDiscoversInstalledFrameworkIntegrations(): void
    {
        $integrations = (new FrameworkIntegrationRegistry())->installed();

        self::assertSame(
            ['laravel', 'test-framework'],
            array_map(static fn ($integration): string => $integration->name(), $integrations)
        );
    }

    public function testItDiscoversComposerMetadataInDeterministicAdapterNameOrder(): void
    {
        $zuluPackage = $this->package('vendor/zulu', [RegistryZuluIntegration::class]);
        $alphaPackage = $this->package('vendor/alpha', [RegistryAlphaIntegration::class]);

        $integrations = (new FrameworkIntegrationRegistry(null, [
            'vendor/zulu' => $zuluPackage,
            'vendor/alpha' => $alphaPackage,
        ]))->installed();

        self::assertSame(['alpha', 'Zulu'], array_map(static fn ($integration): string => $integration->name(), $integrations));
    }

    public function testItUsesComposerTwoPointZeroRawDataFallback(): void
    {
        $package = $this->package('vendor/legacy-runtime', [RegistryAlphaIntegration::class]);
        RegistryLegacyInstalledVersions::$rawData = [
            'versions' => [
                'vendor/legacy-runtime' => ['install_path' => $package],
            ],
        ];

        $integrations = (new FrameworkIntegrationRegistry(
            null,
            null,
            RegistryLegacyInstalledVersions::class
        ))->installed();

        self::assertSame(['alpha'], array_map(static fn ($integration): string => $integration->name(), $integrations));
    }

    public function testItReturnsNoIntegrationsWhenComposerRuntimeMetadataIsUnavailable(): void
    {
        self::assertSame([], (new FrameworkIntegrationRegistry(
            null,
            null,
            'Vendor\\Missing\\InstalledVersions'
        ))->installed());

        self::assertSame([], (new FrameworkIntegrationRegistry(
            null,
            null,
            RegistryInstalledVersionsWithoutMetadataMethods::class
        ))->installed());

        self::assertSame([], (new FrameworkIntegrationRegistry(
            null,
            null,
            RegistryInvalidInstalledVersionsData::class
        ))->installed());
    }

    public function testItSkipsMalformedComposerRuntimeEntriesAndDiscoversTheValidRootPackage(): void
    {
        $package = $this->package('vendor/root-adapter', [RegistryAlphaIntegration::class]);
        RegistryMixedInstalledVersions::$rawData = [
            null,
            ['versions' => 'invalid'],
            [
                'versions' => [],
                'root' => [
                    'name' => 'vendor/root-adapter',
                    'install_path' => $package,
                ],
            ],
        ];

        $integrations = (new FrameworkIntegrationRegistry(
            null,
            null,
            RegistryMixedInstalledVersions::class
        ))->installed();

        self::assertSame(['alpha'], array_map(static fn ($integration): string => $integration->name(), $integrations));
    }

    public function testItRejectsConflictingComposerInstallPaths(): void
    {
        RegistryMixedInstalledVersions::$rawData = [
            ['versions' => ['vendor/collision' => ['install_path' => '/first/path']]],
            ['versions' => ['vendor/collision' => ['install_path' => '/second/path']]],
        ];

        $this->expectException(\LogicException::class);
        $this->expectExceptionMessage('Composer package "vendor/collision" is registered with conflicting install paths');

        (new FrameworkIntegrationRegistry(
            null,
            null,
            RegistryMixedInstalledVersions::class
        ))->installed();
    }

    public function testItIgnoresUnavailableOptionalIntegrationsSuppliedDirectly(): void
    {
        self::assertSame([], (new FrameworkIntegrationRegistry(['Missing\\Framework\\Integration']))->installed());
    }

    public function testItIgnoresInstalledPackagesWithoutAdapterMetadataOrComposerJson(): void
    {
        $package = $this->package('vendor/unrelated', null);
        $missingPackage = $this->temporaryDirectory();
        unlink($missingPackage . DIRECTORY_SEPARATOR . 'composer.json');

        self::assertSame([], (new FrameworkIntegrationRegistry(null, [
            'vendor/unrelated' => $package,
            'vendor/missing' => $missingPackage,
        ]))->installed());
    }

    public function testItRejectsComposerMetadataThatCannotBeRead(): void
    {
        $scheme = 'registry-unreadable';
        self::assertTrue(stream_wrapper_register($scheme, RegistryUnreadableStreamWrapper::class));

        $this->expectException(\LogicException::class);
        $this->expectExceptionMessage('Could not read Composer metadata for installed package "vendor/unreadable".');

        try {
            (new FrameworkIntegrationRegistry(null, [
                'vendor/unreadable' => $scheme . '://package',
            ]))->installed();
        } finally {
            stream_wrapper_unregister($scheme);
        }
    }

    public function testItRejectsADuplicateAdvertisedClassCaseInsensitively(): void
    {
        $firstPackage = $this->package('vendor/first', [RegistryAlphaIntegration::class]);
        $secondPackage = $this->package('vendor/second', [strtolower(RegistryAlphaIntegration::class)]);

        $this->expectException(\LogicException::class);
        $this->expectExceptionMessage('is registered more than once');

        (new FrameworkIntegrationRegistry(null, [
            'vendor/second' => $secondPackage,
            'vendor/first' => $firstPackage,
        ]))->installed();
    }

    public function testItRejectsDuplicateAdapterNamesCaseInsensitively(): void
    {
        $package = $this->package('vendor/collision', [
            RegistryAlphaIntegration::class,
            RegistryDuplicateNameIntegration::class,
        ]);

        $this->expectException(\LogicException::class);
        $this->expectExceptionMessage('Framework integration name "ALPHA" is provided by both');

        (new FrameworkIntegrationRegistry(null, ['vendor/collision' => $package]))->installed();
    }

    public function testItRejectsAnAdvertisedMissingClass(): void
    {
        $package = $this->package('vendor/missing-class', ['Vendor\\Missing\\Integration']);

        $this->expectException(\LogicException::class);
        $this->expectExceptionMessage('Registered framework integration class "Vendor\\Missing\\Integration" could not be loaded.');

        (new FrameworkIntegrationRegistry(null, ['vendor/missing-class' => $package]))->installed();
    }

    public function testItRejectsAnAdvertisedClassThatDoesNotImplementTheContract(): void
    {
        $package = $this->package('vendor/invalid', [RegistryNotAnIntegration::class]);

        $this->expectException(\LogicException::class);
        $this->expectExceptionMessage('must implement ' . FrameworkIntegration::class);

        (new FrameworkIntegrationRegistry(null, ['vendor/invalid' => $package]))->installed();
    }

    public function testItRejectsAnAdvertisedClassWithRequiredConstructorArguments(): void
    {
        $package = $this->package('vendor/constructor', [RegistryConstructorIntegration::class]);

        $this->expectException(\LogicException::class);
        $this->expectExceptionMessage('must have a constructor with no required parameters');

        (new FrameworkIntegrationRegistry(null, ['vendor/constructor' => $package]))->installed();
    }

    public function testItRejectsAnAdvertisedClassThatIsNotInstantiable(): void
    {
        $package = $this->package('vendor/abstract', [RegistryFixtureIntegration::class]);

        $this->expectException(\LogicException::class);
        $this->expectExceptionMessage('is not instantiable');

        (new FrameworkIntegrationRegistry(null, ['vendor/abstract' => $package]))->installed();
    }

    public function testItWrapsAnUnexpectedConstructorFailure(): void
    {
        $package = $this->package('vendor/throwing-constructor', [RegistryThrowingConstructorIntegration::class]);

        $this->expectException(\LogicException::class);
        $this->expectExceptionMessage(
            'Registered framework integration class "' . RegistryThrowingConstructorIntegration::class
            . '" could not be instantiated: constructor failed'
        );

        (new FrameworkIntegrationRegistry(null, ['vendor/throwing-constructor' => $package]))->installed();
    }

    public function testItRejectsAnAdapterWithABlankName(): void
    {
        $package = $this->package('vendor/blank-name', [RegistryBlankNameIntegration::class]);

        $this->expectException(\LogicException::class);
        $this->expectExceptionMessage('must provide a non-empty name without surrounding whitespace');

        (new FrameworkIntegrationRegistry(null, ['vendor/blank-name' => $package]))->installed();
    }

    /**
     * @dataProvider malformedMetadataProvider
     * @param mixed $registered
     */
    public function testItRejectsMalformedAdapterMetadata($registered, string $expectedMessage): void
    {
        $package = $this->package('vendor/malformed', $registered);

        $this->expectException(\LogicException::class);
        $this->expectExceptionMessage($expectedMessage);

        (new FrameworkIntegrationRegistry(null, ['vendor/malformed' => $package]))->installed();
    }

    /**
     * @dataProvider malformedPackageMetadataProvider
     * @param mixed $metadata
     */
    public function testItRejectsMalformedPackageMetadata($metadata, string $expectedMessage, bool $encode = true): void
    {
        $package = $this->temporaryDirectory();
        $contents = $encode ? json_encode($metadata, JSON_THROW_ON_ERROR) : $metadata;
        file_put_contents($package . DIRECTORY_SEPARATOR . 'composer.json', $contents);

        $this->expectException(\LogicException::class);
        $this->expectExceptionMessage($expectedMessage);

        (new FrameworkIntegrationRegistry(null, ['vendor/malformed-package' => $package]))->installed();
    }

    /** @return iterable<string, array{mixed, string, bool?: bool}> */
    public function malformedPackageMetadataProvider(): iterable
    {
        yield 'invalid JSON' => ['{', 'Invalid Composer metadata for installed package "vendor/malformed-package"', false];
        yield 'non-object JSON' => [null, 'Composer metadata for installed package "vendor/malformed-package" must be an object.'];
        yield 'non-object plugin metadata' => [
            ['extra' => ['php-upgrade-preflight' => 'invalid']],
            'Composer metadata extra.php-upgrade-preflight for package "vendor/malformed-package" must be an object.',
        ];
    }

    public function testItIgnoresPluginMetadataWithoutAnAdapterRegistration(): void
    {
        $package = $this->temporaryDirectory();
        file_put_contents(
            $package . DIRECTORY_SEPARATOR . 'composer.json',
            json_encode(['extra' => ['php-upgrade-preflight' => []]], JSON_THROW_ON_ERROR)
        );

        self::assertSame([], (new FrameworkIntegrationRegistry(
            null,
            ['vendor/no-adapter-registration' => $package]
        ))->installed());
    }

    /** @return iterable<string, array{mixed, string}> */
    public function malformedMetadataProvider(): iterable
    {
        yield 'scalar' => [RegistryAlphaIntegration::class, 'must be a non-empty list of class names'];
        yield 'empty list' => [[], 'must be a non-empty list of class names'];
        yield 'object-shaped array' => [['class' => RegistryAlphaIntegration::class], 'must be a non-empty list of class names'];
        yield 'non-string entry' => [[42], 'must contain only non-empty class names without surrounding whitespace'];
        yield 'blank entry' => [['  '], 'must contain only non-empty class names without surrounding whitespace'];
        yield 'padded entry' => [[' ' . RegistryAlphaIntegration::class], 'must contain only non-empty class names without surrounding whitespace'];
    }

    public function testItAcceptsARequestedInstalledIntegrationCaseInsensitively(): void
    {
        (new FrameworkIntegrationRegistry([RegistryAlphaIntegration::class]))->assertAvailable(['ALPHA']);

        self::addToAssertionCount(1);
    }

    public function testItRejectsARequestedIntegrationWhoseAdapterIsNotInstalled(): void
    {
        $this->expectException(\InvalidArgumentException::class);
        $this->expectExceptionMessage('Requested framework integration is unavailable: laravel.');

        (new FrameworkIntegrationRegistry([]))->assertAvailable(['laravel']);
    }

    public function testAMissingAdvertisedPackageOnlyFailsWhenItsFrameworkIsExplicitlyRequested(): void
    {
        $missingPackage = $this->temporaryDirectory();
        unlink($missingPackage . DIRECTORY_SEPARATOR . 'composer.json');
        $registry = new FrameworkIntegrationRegistry(null, ['vendor/missing' => $missingPackage]);

        self::assertSame([], $registry->installed());

        $this->expectException(\InvalidArgumentException::class);
        $this->expectExceptionMessage('Requested framework integration is unavailable: missing.');

        $registry->assertAvailable(['missing']);
    }

    /** @param mixed $registered */
    private function package(string $name, $registered): string
    {
        $directory = $this->temporaryDirectory();
        $metadata = ['name' => $name];
        if ($registered !== null) {
            $metadata['extra'] = [
                'php-upgrade-preflight' => [
                    'framework-adapters' => $registered,
                ],
            ];
        }
        file_put_contents(
            $directory . DIRECTORY_SEPARATOR . 'composer.json',
            json_encode($metadata, JSON_THROW_ON_ERROR | JSON_PRETTY_PRINT)
        );

        return $directory;
    }

    private function temporaryDirectory(): string
    {
        $directory = rtrim(sys_get_temp_dir(), DIRECTORY_SEPARATOR)
            . DIRECTORY_SEPARATOR . 'php-upgrade-preflight-registry-' . bin2hex(random_bytes(8));
        mkdir($directory, 0700, true);
        file_put_contents($directory . DIRECTORY_SEPARATOR . 'composer.json', '{}');
        $this->temporaryDirectories[] = $directory;

        return $directory;
    }
}

abstract class RegistryFixtureIntegration implements FrameworkIntegration
{
    public function detect(ProjectState $project): FrameworkDetection
    {
        return new FrameworkDetection($this->name(), false);
    }

    public function rules(): iterable
    {
        return [];
    }

    public function defaultSourcePaths(ProjectState $project): array
    {
        return [];
    }
}

final class RegistryAlphaIntegration extends RegistryFixtureIntegration
{
    public function name(): string
    {
        return 'alpha';
    }
}

final class RegistryDuplicateNameIntegration extends RegistryFixtureIntegration
{
    public function name(): string
    {
        return 'ALPHA';
    }
}

final class RegistryZuluIntegration extends RegistryFixtureIntegration
{
    public function name(): string
    {
        return 'Zulu';
    }
}

final class RegistryBlankNameIntegration extends RegistryFixtureIntegration
{
    public function name(): string
    {
        return '  ';
    }
}

final class RegistryConstructorIntegration extends RegistryFixtureIntegration
{
    private string $required;

    public function __construct(string $required)
    {
        $this->required = $required;
    }

    public function name(): string
    {
        return $this->required;
    }
}

final class RegistryThrowingConstructorIntegration extends RegistryFixtureIntegration
{
    public function __construct()
    {
        throw new \RuntimeException('constructor failed');
    }

    public function name(): string
    {
        return 'throwing-constructor';
    }
}

final class RegistryNotAnIntegration
{
}

final class RegistryLegacyInstalledVersions
{
    /** @var array<string, mixed> */
    public static array $rawData = [];

    /** @return array<string, mixed> */
    public static function getRawData(): array
    {
        return self::$rawData;
    }
}

final class RegistryInstalledVersionsWithoutMetadataMethods
{
}

final class RegistryInvalidInstalledVersionsData
{
    /** @return mixed */
    public static function getAllRawData()
    {
        return 'invalid';
    }
}

final class RegistryMixedInstalledVersions
{
    /** @var mixed */
    public static $rawData = [];

    /** @return mixed */
    public static function getAllRawData()
    {
        return self::$rawData;
    }
}

final class RegistryUnreadableStreamWrapper
{
    /** @var resource|null */
    public $context;

    /** @return array{mode: int} */
    public function url_stat(string $path, int $flags): array
    {
        return ['mode' => 0100000];
    }

    public function stream_open(string $path, string $mode, int $options, ?string &$openedPath): bool
    {
        return false;
    }
}
