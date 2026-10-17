<?php

declare(strict_types=1);

namespace PhpUpgradePreflight\Core\Tests\Integration;

use PhpUpgradePreflight\Core\Analysis\InputConsistencyTracker;
use PhpUpgradePreflight\Core\Composer\ProjectStateBuilder;
use PhpUpgradePreflight\Core\Model\EvidenceLedger;
use PhpUpgradePreflight\Core\Model\ProjectState;
use PhpUpgradePreflight\Core\Source\SourceFactsCache;
use PhpUpgradePreflight\Core\Source\SourceUsageScanner;
use PHPUnit\Framework\TestCase;
use Symfony\Component\Filesystem\Filesystem;

final class ModularSourceDiscoveryTest extends TestCase
{
    private string $temporary;
    private string $projectPath;

    protected function setUp(): void
    {
        $this->temporary = sys_get_temp_dir() . '/modular-source-discovery-' . bin2hex(random_bytes(8));
        $this->projectPath = $this->temporary . '/project';
        mkdir($this->projectPath, 0700, true);
        file_put_contents($this->projectPath . '/composer.lock', '{"packages":[],"packages-dev":[]}');
        foreach ([
            'app/Kernel.php', 'packages/Webkul/Admin/src/Provider.php', 'packages/Webkul/Shop/src/Controller.php',
            'packages/Webkul/Admin/tests/ProviderTest.php', 'packages/Webkul/Core/helpers.php',
            'legacy/Legacy.php', 'node_modules/hidden.php', 'dependencies/acme/package/Dependency.php',
        ] as $file) {
            $this->write($file, '<?php Acme\\Client::run();');
        }
    }

    protected function tearDown(): void
    {
        (new Filesystem())->remove($this->temporary);
    }

    public function testBagistoShapedRootAutoloadIncludesModularSourcesAndRetainsInputBytes(): void
    {
        $project = $this->project([
            'autoload' => [
                'psr-4' => ['App\\' => 'app/', 'Webkul\\Admin\\' => 'packages/Webkul/Admin/src/', 'Webkul\\Shop\\' => ['packages/Webkul/Shop/src/']],
                'psr-0' => ['Legacy_' => 'legacy/'],
                'files' => ['packages/Webkul/Core/helpers.php'],
            ],
            'autoload-dev' => ['classmap' => ['packages/Webkul/Admin/tests/']],
        ]);
        $before = $this->treeDigests();
        $scanner = new SourceUsageScanner(null, null, new SourceFactsCache($this->temporary . '/cache'));
        $cold = $this->scan($scanner, $project, true);
        self::assertSame([
            'app/Kernel.php', 'legacy/Legacy.php', 'packages/Webkul/Admin/src/Provider.php',
            'packages/Webkul/Admin/tests/ProviderTest.php', 'packages/Webkul/Core/helpers.php', 'packages/Webkul/Shop/src/Controller.php',
        ], array_column($cold['usages'], 'file'));
        self::assertSame([], $cold['uncertainties']);
        self::assertSame($cold, $this->scan($scanner, $project, true));
        self::assertSame(['app/Kernel.php'], array_column($this->scan($scanner, $project, false)['usages'], 'file'));
        self::assertSame($before, $this->treeDigests());
    }

    public function testExcludedModularRootsHaveLinkedMetadataEvidence(): void
    {
        $project = $this->project(['autoload' => ['psr-4' => [
            'Missing\\' => 'packages/Webkul/Missing/src/', 'Dependency\\' => 'dependencies/acme/package/',
            'Outside\\' => '../outside/', 'Wildcard\\' => 'packages/*/src/',
        ]]]);
        mkdir($this->temporary . '/outside', 0700);
        $result = $this->scan(new SourceUsageScanner(null, null, SourceFactsCache::disabled()), $project, true);
        self::assertSame(['app/Kernel.php'], array_column($result['usages'], 'file'));
        self::assertCount(4, $result['uncertainties']);
        $discovery = array_values(array_filter($result['evidence'], static fn (array $entry): bool => str_starts_with($entry['id'], 'source-discovery-')));
        self::assertCount(4, $discovery);
        foreach ($discovery as $entry) {
            self::assertStringContainsString($entry['id'], implode(' ', $result['uncertainties']));
        }
    }

    public function testBroadMetadataRootSkipsCustomVendorAndGeneratedDependencies(): void
    {
        $project = $this->project(['autoload' => ['classmap' => ['.']]]);
        $result = $this->scan(new SourceUsageScanner(null, null, SourceFactsCache::disabled()), $project, true);
        self::assertCount(6, $result['usages']);
        self::assertNotContains('dependencies/acme/package/Dependency.php', array_column($result['usages'], 'file'));
        self::assertNotContains('node_modules/hidden.php', array_column($result['usages'], 'file'));
        $uncertainties = [];
        $explicit = (new SourceUsageScanner(null, null, SourceFactsCache::disabled()))->scan($project, ['dependencies/acme/package'], new EvidenceLedger(), $uncertainties);
        self::assertCount(1, $explicit, 'A directly requested dependency path retains existing explicit behavior.');
    }

    public function testMetadataPathBudgetIsVisibleAndDoesNotExpandAllModularRoots(): void
    {
        $mappings = [];
        for ($index = 0; $index < 260; ++$index) {
            $mappings['Module' . $index . '\\'] = 'app/';
        }
        $project = $this->project(['autoload' => ['psr-4' => $mappings]]);
        $result = $this->scan(new SourceUsageScanner(null, null, SourceFactsCache::disabled()), $project, true);
        self::assertCount(1, $result['usages']);
        self::assertCount(1, $result['uncertainties']);
        self::assertStringContainsString('256-path', $result['uncertainties'][0]);
    }

    public function testInputConsistencyIncludesDiscoveredModulesAndComposerMetadata(): void
    {
        $project = $this->project(['autoload' => ['psr-4' => ['Webkul\\Shop\\' => 'packages/Webkul/Shop/src/']]]);
        $scanner = new SourceUsageScanner(null, null, new SourceFactsCache($this->temporary . '/cache'));
        $this->scan($scanner, $project, true);
        $tracker = new InputConsistencyTracker($this->projectPath);
        $tracker->captureSource($scanner, $project, ['app'], true);
        unlink($this->projectPath . '/packages/Webkul/Shop/src/Controller.php');
        $this->write('packages/Webkul/Shop/src/Added.php', '<?php Acme\\NewClient::run();');
        file_put_contents($this->projectPath . '/composer.json', '{"require":{}}');
        $tracker->checkSource($scanner, $project, ['app'], [], true);
        $tracker->checkComposer();
        self::assertCount(1, $tracker->uncertainties());
        self::assertStringContainsString('packages/Webkul/Shop/src/Controller.php', $tracker->uncertainties()[0]);
        self::assertStringContainsString('packages/Webkul/Shop/src/Added.php', $tracker->uncertainties()[0]);
        self::assertStringContainsString('composer.json', $tracker->uncertainties()[0]);
    }

    /** @param array<string, mixed> $metadata */
    private function project(array $metadata): ProjectState
    {
        file_put_contents($this->projectPath . '/composer.json', (string) json_encode(array_merge([
            'require' => new \stdClass(), 'config' => ['vendor-dir' => 'dependencies'],
        ], $metadata)));

        return (new ProjectStateBuilder())->build($this->projectPath);
    }

    private function write(string $file, string $contents): void
    {
        (new Filesystem())->mkdir(dirname($this->projectPath . '/' . $file), 0700);
        file_put_contents($this->projectPath . '/' . $file, $contents);
    }

    /** @return array<string, mixed> */
    private function scan(SourceUsageScanner $scanner, ProjectState $project, bool $discover): array
    {
        $ledger = new EvidenceLedger();
        $uncertainties = [];
        $digests = [];
        $usages = $scanner->scan($project, ['app'], $ledger, $uncertainties, false, [], $digests, $discover);

        return [
            'usages' => array_map(static fn ($usage): array => $usage->toArray(), $usages),
            'evidence' => array_map(static fn ($evidence): array => $evidence->toArray(), $ledger->all()),
            'uncertainties' => $uncertainties,
        ];
    }

    /** @return array<string, string> */
    private function treeDigests(): array
    {
        $digests = [];
        foreach (new \RecursiveIteratorIterator(new \RecursiveDirectoryIterator($this->projectPath, \FilesystemIterator::SKIP_DOTS)) as $file) {
            $digests[$file->getPathname()] = (string) hash_file('sha256', $file->getPathname());
        }

        return $digests;
    }
}
