<?php

declare(strict_types=1);

namespace PhpUpgradePreflight\Tests\Release;

use PhpUpgradePreflight\Core\Framework\FrameworkIntegration;
use PhpUpgradePreflight\Core\Framework\FrameworkStageTargetProvider;
use PhpUpgradePreflight\Core\Framework\FrameworkTransitionProvider;
use PhpUpgradePreflight\Core\Model\ComposerJson;
use PhpUpgradePreflight\Core\Model\ComposerLock;
use PhpUpgradePreflight\Core\Model\EvidenceLedger;
use PhpUpgradePreflight\Core\Model\ProjectState;
use PhpUpgradePreflight\Core\Model\UpgradeRequest;
use PhpUpgradePreflight\Core\Model\UpgradeTarget;
use PhpUpgradePreflight\LegacyTestAdapter\LegacyTestFrameworkIntegration;
use PhpUpgradePreflight\TestAdapter\TestFrameworkIntegration;
use PHPUnit\Framework\TestCase;

final class AdapterConformanceTest extends TestCase
{
    private string $root;

    protected function setUp(): void
    {
        parent::setUp();

        $this->root = dirname(__DIR__, 2);
    }

    public function testRequiredV02InterfacesRemainSourceCompatible(): void
    {
        self::assertSame(
            ['defaultSourcePaths', 'detect', 'name', 'rules'],
            $this->publicMethods(FrameworkIntegration::class)
        );
        self::assertSame(
            ['assessTransition'],
            $this->publicMethods(FrameworkTransitionProvider::class)
        );
        self::assertSame(
            ['planStages'],
            $this->publicMethods(FrameworkStageTargetProvider::class)
        );
    }

    public function testThirdPartyProviderReturnsStableOrderedEvidenceBackedTargets(): void
    {
        $project = new ProjectState(
            $this->root . '/tests/fixtures/projects/third-party-adapter',
            new ComposerJson(['require' => ['test-vendor/framework' => '1.0.0']]),
            new ComposerLock(['packages' => [[
                'name' => 'test-vendor/framework',
                'version' => '1.0.0',
            ]]])
        );
        $request = new UpgradeRequest(
            $project->path(),
            [new UpgradeTarget('test-vendor/framework', '^2.0')],
            '8.0.30',
            '8.1.0'
        );
        $firstLedger = new EvidenceLedger();
        $secondLedger = new EvidenceLedger();
        $provider = new TestFrameworkIntegration();

        $first = $provider->planStages($project, $request, $firstLedger);
        $second = $provider->planStages($project, $request, $secondLedger);

        self::assertSame($first->provider(), $second->provider());
        self::assertSame(
            array_map(static fn ($stage): array => $stage->toArray(), $first->stages()),
            array_map(static fn ($stage): array => $stage->toArray(), $second->stages())
        );
        self::assertSame(['test-framework-1-to-2'], array_map(
            static fn ($stage): string => $stage->id(),
            $first->stages()
        ));
        self::assertSame([
            ['package' => 'php', 'constraint' => '8.1.0'],
            ['package' => 'test-vendor/framework', 'constraint' => '^2.0'],
        ], $first->stages()[0]->targets()->toArray());
        self::assertSame(
            'final_target_php_exact_value_checked_against_adapter_constraint',
            $firstLedger->all()[0]->context()['analysis_php_provenance']
        );
        $firstLedger->validateReferences($first->evidence());
        $secondLedger->validateReferences($second->evidence());
    }

    public function testOldStyleAdapterExplicitlySupportsCoreV03WithoutClaimingStageTargets(): void
    {
        self::assertInstanceOf(FrameworkIntegration::class, new LegacyTestFrameworkIntegration());
        self::assertInstanceOf(FrameworkTransitionProvider::class, new LegacyTestFrameworkIntegration());
        self::assertNotInstanceOf(FrameworkStageTargetProvider::class, new LegacyTestFrameworkIntegration());

        $manifest = $this->readJson('packages/legacy-test-adapter/composer.json');
        self::assertSame('^0.3', $manifest['require']['php-upgrade-preflight/core']);
        self::assertNotSame('^0.2', $manifest['require']['php-upgrade-preflight/core']);
    }

    public function testCoreStagedContractsRemainOpaqueToLaravelImplementationDetails(): void
    {
        $forbidden = [
            'PhpUpgradePreflight\\Laravel',
            'LaravelRuleCatalog',
            'LaravelTarget',
            'LaravelPackageFamilyClassifier',
            'laravel/framework',
            'illuminate/',
        ];
        $files = new \RecursiveIteratorIterator(new \RecursiveDirectoryIterator(
            $this->root . '/packages/core/src',
            \FilesystemIterator::SKIP_DOTS
        ));

        foreach ($files as $file) {
            if (!$file->isFile() || $file->getExtension() !== 'php') {
                continue;
            }
            $contents = file_get_contents($file->getPathname());
            self::assertIsString($contents);
            foreach ($forbidden as $needle) {
                self::assertStringNotContainsString(
                    $needle,
                    $contents,
                    sprintf('Core file %s contains adapter-specific staged knowledge.', $file->getFilename())
                );
            }
        }
    }

    /** @return list<string> */
    private function publicMethods(string $interface): array
    {
        $methods = array_map(
            static fn (\ReflectionMethod $method): string => $method->getName(),
            (new \ReflectionClass($interface))->getMethods(\ReflectionMethod::IS_PUBLIC)
        );
        sort($methods, SORT_STRING);

        return $methods;
    }

    /** @return array<string, mixed> */
    private function readJson(string $relativePath): array
    {
        $contents = file_get_contents($this->root . '/' . $relativePath);
        self::assertIsString($contents);
        $decoded = json_decode($contents, true, 512, JSON_THROW_ON_ERROR);
        self::assertIsArray($decoded);

        return $decoded;
    }
}
