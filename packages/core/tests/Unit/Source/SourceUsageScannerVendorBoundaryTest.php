<?php

declare(strict_types=1);

namespace PhpUpgradePreflight\Core\Tests\Unit\Source;

use PhpUpgradePreflight\Core\Composer\ProjectStateBuilder;
use PhpUpgradePreflight\Core\Model\EvidenceLedger;
use PhpUpgradePreflight\Core\Source\SourceFactsCache;
use PhpUpgradePreflight\Core\Source\SourceUsageScanner;
use PHPUnit\Framework\TestCase;
use Symfony\Component\Filesystem\Filesystem;

final class SourceUsageScannerVendorBoundaryTest extends TestCase
{
    private string $temporary;

    protected function setUp(): void
    {
        $this->temporary = sys_get_temp_dir() . '/source-vendor-boundary-' . bin2hex(random_bytes(8));
        mkdir($this->temporary . '/app/nested', 0700, true);
        mkdir($this->temporary . '/dependencies/acme', 0700, true);
        file_put_contents($this->temporary . '/app/App.php', '<?php Acme\\Application::run();');
        file_put_contents($this->temporary . '/dependencies/acme/Dependency.php', '<?php Acme\\Dependency::run();');
        file_put_contents($this->temporary . '/composer.lock', '{"packages":[],"packages-dev":[]}');
    }

    protected function tearDown(): void
    {
        (new Filesystem())->remove($this->temporary);
    }

    /** @dataProvider vendorConfigurations */
    public function testEveryAutomaticFileSelectionPathExcludesResolvedVendorTargets(string $configuration): void
    {
        if (DIRECTORY_SEPARATOR === '\\') {
            self::markTestSkipped('Requires unprivileged Unix file/directory symlinks.');
        }
        $vendor = $configuration === 'absolute' ? $this->temporary . '/dependencies' : 'dependencies';
        if ($configuration === 'symlink') {
            symlink($this->temporary . '/dependencies', $this->temporary . '/deps-link');
            $vendor = 'deps-link';
        }
        symlink($this->temporary . '/dependencies/acme/Dependency.php', $this->temporary . '/app/nested/Linked.php');
        $app = 'app/App.php';
        $link = 'app/nested/Linked.php';
        $cases = [
            'mapped linked file' => [[$app], ['files' => [$link]], [$app, $link], 1],
            'mapped application directory' => [[$app], ['classmap' => ['app']], ['app'], 0],
            'mapped broad root' => [[$app], ['classmap' => ['.']], ['.'], 0],
            'fallback linked file' => [[$app, $link], [], [$app, $link], 0],
            'fallback application directory' => [['app'], [], ['app'], 0],
            'fallback broad root' => [['.'], [], ['.'], 0],
            'fallback vendor file' => [[$app, 'dependencies/acme/Dependency.php'], [], [$app, 'dependencies/acme/Dependency.php'], 0],
            'fallback vendor directory' => [[$app, 'dependencies/acme'], [], [$app, 'dependencies/acme'], 0],
        ];
        $scanner = new SourceUsageScanner(null, null, SourceFactsCache::disabled());
        foreach ($cases as $label => [$paths, $autoload, $explicitPaths, $omittedRoots]) {
            file_put_contents($this->temporary . '/composer.json', (string) json_encode([
                'require' => new \stdClass(), 'config' => ['vendor-dir' => $vendor], 'autoload' => $autoload,
            ]));
            $project = (new ProjectStateBuilder())->build($this->temporary);
            $before = $this->treeDigests();
            $ledger = new EvidenceLedger();
            $uncertainties = [];
            $digests = [];
            $usages = $scanner->scan($project, $paths, $ledger, $uncertainties, true, [], $digests, true);
            self::assertSame(['app/App.php'], array_map(static fn ($usage): string => $usage->file(), $usages), $label);
            self::assertSame(['Acme\\Application'], array_map(static fn ($usage): string => $usage->symbol(), $usages), $label);
            self::assertSame([$this->temporary . '/app/App.php'], array_keys($digests), $label . ': vendor bytes must never be read.');
            self::assertCount($omittedRoots, $uncertainties, $label);
            $omissions = array_values(array_filter($ledger->all(), static fn ($entry): bool => str_starts_with($entry->id(), 'source-discovery-')));
            self::assertCount($omittedRoots, $omissions, $label);
            foreach ($omissions as $entry) {
                self::assertSame('E2', $entry->evidenceClass());
                self::assertSame('the path is in the dependency directory', $entry->context()['reason']);
                self::assertStringContainsString($entry->id(), implode(' ', $uncertainties));
            }

            $explicitUncertainties = [];
            $explicit = $scanner->scan($project, $explicitPaths, new EvidenceLedger(), $explicitUncertainties);
            self::assertSame(['Acme\\Application', 'Acme\\Dependency'], array_map(static fn ($usage): string => $usage->symbol(), $explicit), $label);
            self::assertSame([], $explicitUncertainties, $label);
            self::assertSame($before, $this->treeDigests(), $label . ': preserve input bytes and symlink destinations.');
        }
    }

    /** @return iterable<string, array{string}> */
    public function vendorConfigurations(): iterable
    {
        yield 'relative vendor' => ['relative'];
        yield 'absolute vendor' => ['absolute'];
        yield 'symlink-configured vendor' => ['symlink'];
    }

    /** @return array<string, string> */
    private function treeDigests(): array
    {
        $digests = [];
        foreach (new \RecursiveIteratorIterator(new \RecursiveDirectoryIterator($this->temporary, \FilesystemIterator::SKIP_DOTS)) as $file) {
            $digests[$file->getPathname()] = $file->isLink()
                ? 'link:' . (string) readlink($file->getPathname())
                : (string) hash_file('sha256', $file->getPathname());
        }

        return $digests;
    }
}
