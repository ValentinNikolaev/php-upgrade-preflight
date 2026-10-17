<?php

declare(strict_types=1);

namespace PhpUpgradePreflight\Core\Tests\Unit\Source;

use PhpUpgradePreflight\Core\Model\ComposerJson;
use PhpUpgradePreflight\Core\Model\ComposerLock;
use PhpUpgradePreflight\Core\Model\EvidenceLedger;
use PhpUpgradePreflight\Core\Model\ProjectState;
use PhpUpgradePreflight\Core\Source\AutoloadSourceDiscovery;
use PHPUnit\Framework\TestCase;
use Symfony\Component\Filesystem\Filesystem;

final class AutoloadSourceDiscoveryTest extends TestCase
{
    private string $temporary;
    private string $projectPath;

    protected function setUp(): void
    {
        $this->temporary = sys_get_temp_dir() . '/autoload-source-discovery-test-' . bin2hex(random_bytes(8));
        $this->projectPath = $this->temporary . '/project';
        foreach (['app', 'modules/shop', 'modules/admin', 'tests', 'legacy', 'dependencies/acme', 'generated', 'bootstrap/cache'] as $path) {
            mkdir($this->projectPath . '/' . $path, 0700, true);
        }
        mkdir($this->temporary . '/outside', 0700);
        file_put_contents($this->projectPath . '/helpers.php', '<?php function helper() {}');
    }

    protected function tearDown(): void
    {
        (new Filesystem())->remove($this->temporary);
    }

    public function testStaticRootAutoloadAndDevPathsAreUniqueAndOrdered(): void
    {
        $project = $this->project([
            'autoload' => [
                'psr-4' => ['App\\' => 'app/', 'Modules\\' => ['modules/shop', 'modules/admin']],
                'psr-0' => ['Legacy_' => 'legacy'],
                'classmap' => ['tests'],
                'files' => ['helpers.php'],
            ],
            'autoload-dev' => ['psr-4' => ['Tests\\' => 'tests']],
        ]);
        $uncertainties = [];
        $ledger = new EvidenceLedger();
        self::assertSame(['app/', 'helpers.php', 'legacy', 'modules/admin', 'modules/shop', 'tests'], (new AutoloadSourceDiscovery())->paths($project, $uncertainties, $ledger));
        self::assertSame([], $uncertainties);
        self::assertSame([], $ledger->all());
    }

    public function testExcludedModularMetadataPathsProduceSpecificLinkedEvidence(): void
    {
        $paths = ['missing', '../outside', $this->projectPath . '/app', 'modules/*', 'dependencies/acme', 'generated', 'bootstrap/cache'];
        $project = $this->project(['autoload' => ['classmap' => $paths]]);
        $uncertainties = [];
        $ledger = new EvidenceLedger();
        self::assertSame([], (new AutoloadSourceDiscovery())->paths($project, $uncertainties, $ledger));
        self::assertCount(7, $uncertainties);
        self::assertCount(7, $ledger->all());
        foreach ($ledger->all() as $evidence) {
            self::assertStringContainsString($evidence->id(), implode(' ', $uncertainties));
            self::assertSame('E2', $evidence->evidenceClass());
        }
        self::assertSame('the path resolves outside the analyzed project', $ledger->all()[1]->context()['reason']);
        self::assertSame('the path is in the dependency directory', $ledger->all()[4]->context()['reason']);
    }

    public function testUnsupportedMetadataIsVisibleWithoutRequiringAnEvidenceLedger(): void
    {
        $project = $this->project(['autoload' => ['psr-4' => 'dynamic', 'files' => [false, ['unexpected' => ['nested']]]]]);
        $uncertainties = [];
        self::assertSame([], (new AutoloadSourceDiscovery())->paths($project, $uncertainties, null));
        self::assertCount(3, $uncertainties);
        self::assertStringContainsString('unsupported metadata', $uncertainties[0]);
        self::assertStringContainsString('unsupported path metadata', $uncertainties[1]);
        self::assertStringContainsString('unsupported path metadata', $uncertainties[2]);
    }

    public function testRootMappingAndMetadataBudgetRemainBoundedAndExplicit(): void
    {
        $project = $this->project(['autoload' => ['psr-4' => array_merge(['Root\\' => ''], array_fill(0, 256, 'app'))]]);
        $uncertainties = [];
        self::assertSame(['.', 'app'], (new AutoloadSourceDiscovery())->paths($project, $uncertainties, null));
        self::assertCount(1, $uncertainties);
        self::assertStringContainsString('256-path metadata safety limit', $uncertainties[0]);
    }

    /** @dataProvider configuredVendorPaths */
    public function testCanonicalVendorRootDirectoryAndFileMappingsAreExcludedWithEvidence(string $configuration): void
    {
        $vendor = 'dependencies';
        if ($configuration === 'absolute') {
            $vendor = $this->projectPath . '/dependencies';
        } elseif ($configuration === 'symlink') {
            if (DIRECTORY_SEPARATOR === '\\') {
                self::markTestSkipped('Requires unprivileged Unix directory symlinks.');
            }
            symlink($this->projectPath . '/dependencies', $this->projectPath . '/deps-link');
            $vendor = 'deps-link';
        }
        file_put_contents($this->projectPath . '/dependencies/acme/Dependency.php', '<?php Acme\\Dependency::run();');
        $project = $this->project([
            'config' => ['vendor-dir' => $vendor],
            'autoload' => [
                'psr-4' => ['App\\' => 'modules/admin', 'Dependency\\' => 'dependencies/acme'],
                'classmap' => ['dependencies'],
                'files' => ['dependencies/acme/Dependency.php'],
            ],
        ]);
        $uncertainties = [];
        $ledger = new EvidenceLedger();
        self::assertSame(['modules/admin'], (new AutoloadSourceDiscovery())->paths($project, $uncertainties, $ledger));
        self::assertCount(3, $uncertainties);
        self::assertCount(3, $ledger->all());
        foreach ($ledger->all() as $evidence) {
            self::assertSame('the path is in the dependency directory', $evidence->context()['reason']);
            self::assertSame('E2', $evidence->evidenceClass());
            self::assertStringContainsString($evidence->id(), implode(' ', $uncertainties));
        }
    }

    public function testVendorComparisonFollowsPlatformCaseRules(): void
    {
        $vendor = strtoupper($this->projectPath . '/dependencies');
        if (DIRECTORY_SEPARATOR !== '\\') {
            mkdir($this->projectPath . '/Dependencies', 0700);
            $vendor = 'Dependencies';
        }
        $project = $this->project(['config' => ['vendor-dir' => $vendor], 'autoload' => ['classmap' => ['dependencies/acme']]]);
        $uncertainties = [];
        $paths = (new AutoloadSourceDiscovery())->paths($project, $uncertainties, null);
        self::assertSame(DIRECTORY_SEPARATOR === '\\' ? [] : ['dependencies/acme'], $paths);
        self::assertCount(DIRECTORY_SEPARATOR === '\\' ? 1 : 0, $uncertainties);
    }

    /** @return iterable<string, array{string}> */
    public function configuredVendorPaths(): iterable
    {
        yield 'relative working control' => ['relative'];
        yield 'absolute vendor directory' => ['absolute'];
        yield 'symlink-resolved vendor directory' => ['symlink'];
    }

    /** @param array<string, mixed> $metadata */
    private function project(array $metadata): ProjectState
    {
        return new ProjectState($this->projectPath, new ComposerJson(array_merge(['config' => ['vendor-dir' => 'dependencies']], $metadata)), new ComposerLock([]));
    }
}
