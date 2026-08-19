<?php

declare(strict_types=1);

namespace PhpUpgradePreflight\Tests\Release;

use PHPUnit\Framework\TestCase;
use Symfony\Component\Filesystem\Filesystem;
use Symfony\Component\Process\Process;

final class ReleaseWikiMaterializerTest extends TestCase
{
    private const REQUIRED_SETS = ['common', 'core', 'cli', 'laravel'];

    private const SET_CONTRACTS = [
        'common' => ['ValentinNikolaev/php-upgrade-preflight', 'Product-wide and monorepo documentation'],
        'core' => ['ValentinNikolaev/php-upgrade-preflight-core', 'Framework-neutral Core package documentation'],
        'cli' => ['ValentinNikolaev/php-upgrade-preflight-cli', 'Standalone CLI package documentation'],
        'laravel' => ['ValentinNikolaev/php-upgrade-preflight-laravel', 'Laravel adapter and Artisan documentation'],
    ];

    private Filesystem $filesystem;
    private string $temporaryRoot;
    private string $fixtureRoot;

    protected function setUp(): void
    {
        $this->filesystem = new Filesystem();
        $this->temporaryRoot = rtrim(sys_get_temp_dir(), DIRECTORY_SEPARATOR)
            . DIRECTORY_SEPARATOR
            . 'php-upgrade-preflight-release-wikis-' . bin2hex(random_bytes(8));
        $this->fixtureRoot = $this->temporaryRoot . '/repository';
        $this->filesystem->mkdir([$this->fixtureRoot . '/tools', $this->fixtureRoot . '/wiki']);
        $this->filesystem->copy(
            dirname(__DIR__, 2) . '/tools/materialize-release-wikis.php',
            $this->fixtureRoot . '/tools/materialize-release-wikis.php'
        );

        foreach (self::REQUIRED_SETS as $set) {
            $this->writeSet($set);
        }
    }

    protected function tearDown(): void
    {
        $this->filesystem->remove($this->temporaryRoot);
    }

    /** @dataProvider incompleteSetProvider */
    public function testCheckRejectsARequiredSetOrManifestThatIsMissing(string $case): void
    {
        $generated = $this->runMaterializer();
        self::assertTrue($generated->isSuccessful(), $generated->getErrorOutput());

        if ($case === 'directory') {
            $this->filesystem->remove($this->fixtureRoot . '/release-wikis/laravel');
        } else {
            $this->filesystem->remove($this->fixtureRoot . '/release-wikis/laravel/wiki-manifest.json');
        }

        $process = $this->runMaterializer('--check');

        self::assertFalse($process->isSuccessful(), 'An incomplete Wiki set inventory must fail closed.');
        self::assertStringContainsString('laravel', $process->getErrorOutput());
    }

    /** @return list<array{string}> */
    public function incompleteSetProvider(): array
    {
        return [['directory'], ['manifest']];
    }

    public function testManifestSourceCannotEscapeTheCanonicalWikiDirectory(): void
    {
        $secretPath = $this->temporaryRoot . '/outside.md';
        file_put_contents($secretPath, 'must not be materialized');
        $this->writeManifest('core', [
            ['source' => '../outside.md', 'destination' => 'Home.md'],
        ], ['Home']);

        $process = $this->runMaterializer();

        self::assertFalse($process->isSuccessful(), 'A traversing source path must be rejected.');
        self::assertStringContainsString('core', $process->getErrorOutput());
        self::assertFileDoesNotExist($this->fixtureRoot . '/release-wikis/core/pages/Home.md');
    }

    public function testUnknownSetDirectoryIsRejected(): void
    {
        $this->filesystem->mkdir($this->fixtureRoot . '/release-wikis/experimental');

        $process = $this->runMaterializer();

        self::assertFalse($process->isSuccessful());
        self::assertStringContainsString('unknown release Wiki set directory: experimental', $process->getErrorOutput());
        self::assertDirectoryDoesNotExist($this->fixtureRoot . '/release-wikis/common/pages');
    }

    /** @dataProvider invalidSetContractProvider */
    public function testSetRepositoryAndPurposeMustMatchTheAllowlist(string $field, string $value): void
    {
        $manifestPath = $this->fixtureRoot . '/release-wikis/core/wiki-manifest.json';
        $manifest = json_decode((string) file_get_contents($manifestPath), true, 512, JSON_THROW_ON_ERROR);
        $manifest[$field] = $value;
        file_put_contents(
            $manifestPath,
            json_encode($manifest, JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES | JSON_THROW_ON_ERROR) . "\n"
        );

        $process = $this->runMaterializer();

        self::assertFalse($process->isSuccessful());
        self::assertStringContainsString($field . ' does not match the allowlist', $process->getErrorOutput());
    }

    /** @return list<array{string, string}> */
    public function invalidSetContractProvider(): array
    {
        return [
            ['destination_repository', 'example/incorrect'],
            ['purpose', 'Incorrect purpose'],
        ];
    }

    public function testSymlinkedPagesDirectoryIsRejectedWithoutWritingThroughIt(): void
    {
        if (!function_exists('symlink')) {
            self::markTestSkipped('symlink() is unavailable.');
        }
        $outside = $this->temporaryRoot . '/outside-pages';
        $this->filesystem->mkdir($outside);
        file_put_contents($outside . '/sentinel.txt', 'unchanged');
        $link = $this->fixtureRoot . '/release-wikis/core/pages';
        if (!@symlink($outside, $link)) {
            self::markTestSkipped('The current platform does not permit directory symlinks.');
        }

        $process = $this->runMaterializer();

        self::assertFalse($process->isSuccessful());
        self::assertStringContainsString('pages path must be a real directory', $process->getErrorOutput());
        self::assertSame('unchanged', file_get_contents($outside . '/sentinel.txt'));
        self::assertFileDoesNotExist($outside . '/Home.md');
    }

    /**
     * @param list<array{source?: string, destination: string}> $pages
     * @dataProvider invalidDestinationProvider
     */
    public function testReservedAndCaseInsensitiveDestinationCollisionsAreRejected(array $pages): void
    {
        foreach ($pages as $index => $page) {
            file_put_contents($this->fixtureRoot . '/wiki/Collision-' . $index . '.md', '# Collision');
            $pages[$index]['source'] = 'wiki/Collision-' . $index . '.md';
        }
        $this->writeManifest('core', $pages, []);

        $process = $this->runMaterializer();

        self::assertFalse($process->isSuccessful(), 'Ambiguous or generated destinations must be rejected.');
        self::assertStringContainsString('core', $process->getErrorOutput());
        self::assertDirectoryDoesNotExist($this->fixtureRoot . '/release-wikis/core/pages');
    }

    /** @return list<array{list<array{destination: string}>}> */
    public function invalidDestinationProvider(): array
    {
        return [
            [[['destination' => '_Sidebar.md']]],
            [[['destination' => '_Footer.md']]],
            [[['destination' => 'Guide.md'], ['destination' => 'guide.md']]],
        ];
    }

    public function testBrokenLinkFailureDoesNotModifyAnyMaterializedSet(): void
    {
        $initial = $this->runMaterializer();
        self::assertTrue($initial->isSuccessful(), $initial->getErrorOutput());
        $before = $this->materializedTreeHashes();

        file_put_contents(
            $this->fixtureRoot . '/wiki/core.md',
            "# core\n\n[Missing page](Does-Not-Exist.md)\n"
        );
        $failed = $this->runMaterializer();

        self::assertFalse($failed->isSuccessful(), 'Broken local links must fail materialization.');
        self::assertStringContainsString('Does-Not-Exist.md', $failed->getErrorOutput());
        self::assertSame($before, $this->materializedTreeHashes(), 'A failed run must not partially update pages.');
    }

    public function testHomeAliasIsRewrittenAndChecksumDriftIsDetected(): void
    {
        file_put_contents(
            $this->fixtureRoot . '/wiki/Core-Package-Guide.md',
            "# Core\n\n[[Core guide|Core-Package-Guide]]\n"
        );
        $this->writeManifest('core', [
            ['source' => 'wiki/Core-Package-Guide.md', 'destination' => 'Home.md'],
        ], ['Home']);

        $generated = $this->runMaterializer();
        self::assertTrue($generated->isSuccessful(), $generated->getErrorOutput());

        $homePath = $this->fixtureRoot . '/release-wikis/core/pages/Home.md';
        $home = file_get_contents($homePath);
        self::assertIsString($home);
        self::assertStringContainsString('[[Core guide|Home]]', $home);

        $checksumPath = $this->fixtureRoot . '/release-wikis/core/pages/.source-checksums.json';
        $checksum = json_decode((string) file_get_contents($checksumPath), true, 512, JSON_THROW_ON_ERROR);
        self::assertSame(hash('sha256', $home), $checksum['pages'][0]['materialized_sha256'] ?? null);
        self::assertSame(
            hash_file('sha256', $this->fixtureRoot . '/release-wikis/core/wiki-manifest.json'),
            $checksum['manifest_sha256'] ?? null
        );

        file_put_contents($homePath, $home . "\nmanual drift\n");
        $checked = $this->runMaterializer('--check');

        self::assertFalse($checked->isSuccessful(), 'Manually edited materialized pages must fail checksum checking.');
        self::assertStringContainsString('core', $checked->getErrorOutput());
        self::assertStringContainsString('Home.md', $checked->getErrorOutput());
    }

    public function testPublishedInventoryReportsSurplusRemotePages(): void
    {
        $generated = $this->runMaterializer();
        self::assertTrue($generated->isSuccessful(), $generated->getErrorOutput());
        $checkout = $this->temporaryRoot . '/common.wiki';
        $this->filesystem->mkdir($checkout);
        foreach (glob($this->fixtureRoot . '/release-wikis/common/pages/*.md') ?: [] as $page) {
            $this->filesystem->copy($page, $checkout . '/' . basename($page));
        }
        file_put_contents($checkout . '/Retired.md', "# Retired\n");

        $checked = $this->runMaterializer('--check-published', ['common', $checkout]);

        self::assertFalse($checked->isSuccessful());
        self::assertStringContainsString('surplus remote page Retired.md', $checked->getErrorOutput());
        self::assertStringContainsString('git rm explicitly', $checked->getErrorOutput());
    }

    private function writeSet(string $set): void
    {
        file_put_contents($this->fixtureRoot . '/wiki/' . $set . '.md', '# ' . $set . "\n");
        $this->writeManifest($set, [
            ['source' => 'wiki/' . $set . '.md', 'destination' => 'Home.md'],
        ], ['Home']);
    }

    /**
     * @param list<array{source: string, destination: string}> $pages
     * @param list<string>                                    $sidebar
     */
    private function writeManifest(string $set, array $pages, array $sidebar): void
    {
        $directory = $this->fixtureRoot . '/release-wikis/' . $set;
        $this->filesystem->mkdir($directory);
        file_put_contents(
            $directory . '/wiki-manifest.json',
            json_encode([
                'schema_version' => 1,
                'destination_repository' => self::SET_CONTRACTS[$set][0],
                'purpose' => self::SET_CONTRACTS[$set][1],
                'pages' => $pages,
                'sidebar' => $sidebar,
                'footer' => ucfirst($set) . ' documentation',
            ], JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES | JSON_THROW_ON_ERROR) . "\n"
        );
    }

    /** @param list<string> $additionalArguments */
    private function runMaterializer(?string $argument = null, array $additionalArguments = []): Process
    {
        $command = [PHP_BINARY, $this->fixtureRoot . '/tools/materialize-release-wikis.php'];
        if ($argument !== null) {
            $command[] = $argument;
        }
        array_push($command, ...$additionalArguments);
        $process = new Process($command, $this->fixtureRoot);
        $process->run();

        return $process;
    }

    /** @return array<string, string> */
    private function materializedTreeHashes(): array
    {
        $hashes = [];
        foreach (self::REQUIRED_SETS as $set) {
            $directory = $this->fixtureRoot . '/release-wikis/' . $set . '/pages';
            foreach (array_values(array_diff(scandir($directory) ?: [], ['.', '..'])) as $name) {
                $hash = hash_file('sha256', $directory . '/' . $name);
                self::assertIsString($hash);
                $hashes[$set . '/' . $name] = $hash;
            }
        }
        ksort($hashes, SORT_STRING);

        return $hashes;
    }
}
