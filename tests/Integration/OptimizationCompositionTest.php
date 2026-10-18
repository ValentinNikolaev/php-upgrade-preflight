<?php

declare(strict_types=1);

namespace PhpUpgradePreflight\Tests\Integration;

use PhpUpgradePreflight\Core\Analysis\DefaultUpgradeAnalyzer;
use PhpUpgradePreflight\Core\Model\ComposerExecutionConfiguration;
use PhpUpgradePreflight\Core\Model\SourceUsage;
use PhpUpgradePreflight\Core\Model\UpgradeReport;
use PhpUpgradePreflight\Core\Model\UpgradeRequest;
use PhpUpgradePreflight\Core\Model\UpgradeTarget;
use PhpUpgradePreflight\Core\Reporting\SummaryReportWriter;
use PhpUpgradePreflight\Core\Source\SourceFactsCache;
use PhpUpgradePreflight\Core\Source\SourceUsageScanner;
use PhpUpgradePreflight\Tests\Support\FixtureSnapshot;
use PHPUnit\Framework\TestCase;
use Symfony\Component\Filesystem\Filesystem;

final class OptimizationCompositionTest extends TestCase
{
    public function testAdaptiveAnalysisDiscoversModulesAcrossFreshCachedScannersAndHonorsExplicitSelection(): void
    {
        $filesystem = new Filesystem();
        $temporary = sys_get_temp_dir() . '/optimization-composition-' . bin2hex(random_bytes(8));
        $fixture = $temporary . '/fixture';
        $project = $fixture . '/project';
        $filesystem->mirror(dirname(__DIR__) . '/fixtures/path-repository', $fixture);
        try {
            $filesystem->mkdir([$project . '/app', $project . '/modules/Shop']);
            /** @var array<string, mixed> $manifest */
            $manifest = json_decode((string) file_get_contents($project . '/composer.json'), true, 512, JSON_THROW_ON_ERROR);
            $manifest['autoload'] = ['psr-4' => ['App\\' => 'app/', 'Shop\\' => 'modules/Shop/']];
            file_put_contents($project . '/composer.json', json_encode($manifest, JSON_THROW_ON_ERROR));
            file_put_contents($project . '/app/Main.php', '<?php AppClient::run();');
            file_put_contents($project . '/modules/Shop/Client.php', '<?php PrivateModuleClient::run();');
            $snapshot = FixtureSnapshot::capture($fixture);
            $reports = [];
            foreach (['cold', 'warm', 'full'] as $variant) {
                $cache = $variant === 'full' ? SourceFactsCache::disabled() : new SourceFactsCache($temporary . '/facts');
                $reports[$variant] = $this->analyze($project, $cache, $variant === 'full' ? 'full' : 'standard');
                self::assertSame('feasible_with_changes', $reports[$variant]->resolutionStatus());
                self::assertSame(['app/Main.php', 'modules/Shop/Client.php'], array_column($this->facts($reports[$variant]), 'file'));
                $snapshot->assertUnchanged($this);
            }
            self::assertSame($this->facts($reports['cold']), $this->facts($reports['warm']));
            self::assertSame($this->facts($reports['cold']), $this->facts($reports['full']));
            self::assertSame($reports['cold']->lockDiff()->toArray(), $reports['full']->lockDiff()->toArray());
            self::assertLessThan(count($reports['full']->scenarios()), count($reports['cold']->scenarios()));
            $summary = (new SummaryReportWriter())->render($reports['warm']);
            self::assertStringContainsString('Direct target: feasible_with_changes', $summary);
            self::assertStringNotContainsString('PrivateModuleClient', $summary, 'Raw observation inventory is opt-in.');

            $explicit = $this->analyze($project, new SourceFactsCache($temporary . '/facts'), 'standard', ['app']);
            self::assertSame(['app/Main.php'], array_column($this->facts($explicit), 'file'));
            $snapshot->assertUnchanged($this);

            file_put_contents($project . '/modules/Shop/Client.php', '<?php ChangedModuleClient::run();');
            $editedSnapshot = FixtureSnapshot::capture($fixture);
            $edited = $this->analyze($project, new SourceFactsCache($temporary . '/facts'), 'standard');
            self::assertContains('ChangedModuleClient', array_column($this->facts($edited), 'symbol'));
            self::assertNotContains('PrivateModuleClient', array_column($this->facts($edited), 'symbol'));
            $editedSnapshot->assertUnchanged($this);
        } finally {
            $filesystem->remove($temporary);
        }
    }

    /** @param list<string> $sources */
    private function analyze(string $project, SourceFactsCache $cache, string $mode, array $sources = []): UpgradeReport
    {
        return (new DefaultUpgradeAnalyzer(sourceUsageScanner: new SourceUsageScanner(null, null, $cache)))->analyzeUpgrade(
            new UpgradeRequest(
                $project,
                [new UpgradeTarget('fixture/dependency', '^2.0')],
                '8.0',
                '8.3',
                sourcePaths: $sources,
                composerExecution: ComposerExecutionConfiguration::restricted(),
                analysisMode: $mode
            )
        );
    }

    /** @return list<array{file: string, symbol: string, usage_type: string, line: ?int}> */
    private function facts(UpgradeReport $report): array
    {
        return array_map(static fn (SourceUsage $usage): array => [
            'file' => $usage->file(), 'symbol' => $usage->symbol(), 'usage_type' => $usage->usageType(), 'line' => $usage->line(),
        ], $report->sourceInventory());
    }
}
