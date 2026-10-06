<?php

declare(strict_types=1);

namespace PhpUpgradePreflight\Laravel\Tests\Integration;

use PhpUpgradePreflight\Core\Model\ComposerExecutionConfiguration;
use PhpUpgradePreflight\Core\Model\UpgradeRequest;
use PhpUpgradePreflight\Core\Model\UpgradeTarget;
use PhpUpgradePreflight\Core\Reporting\JsonReportWriter;
use PhpUpgradePreflight\Core\Reporting\MarkdownReportWriter;
use PhpUpgradePreflight\Tests\Support\FixtureSnapshot;
use PhpUpgradePreflight\Tests\Support\LaravelTransitionFixtureFactory;
use PHPUnit\Framework\TestCase;
use Symfony\Component\Filesystem\Filesystem;

final class LaravelRemovedSourceStagedReportTest extends TestCase
{
    public function testParsedAliasesRetainSourceEvidenceOnlyOnTheirApplicableStages(): void
    {
        $fixtures = dirname(__DIR__, 4) . '/tests/fixtures';
        $originalProject = $fixtures . '/projects/laravel-8-to-9-feasible';
        $originalPackages = $fixtures . '/path-repository/laravel-transition';
        $projectSnapshot = FixtureSnapshot::capture($originalProject);
        $packageSnapshot = FixtureSnapshot::capture($originalPackages);
        $temporary = sys_get_temp_dir() . '/laravel-removed-symbols-' . bin2hex(random_bytes(8));
        $project = $temporary . '/projects/laravel-8-to-9-feasible';
        $filesystem = new Filesystem();

        try {
            $filesystem->mirror($originalProject, $project);
            $filesystem->mirror($originalPackages, $temporary . '/path-repository/laravel-transition');
            $filesystem->mkdir($project . '/app');
            $source = <<<'PHP'
<?php
namespace App;
use Illuminate\Queue\SerializableClosure as LegacyClosure;
use Illuminate\Queue\SerializableClosureFactory as LegacyFactory;
use Illuminate\Foundation\Testing\Concerns\MocksApplicationServices as LegacyMocks;
use Illuminate\Support\Facades\Bus as LegacyBus;
new LegacyClosure(static function (): void {});
LegacyFactory::make();
class UpgradeCheck { use LegacyMocks; }
LegacyBus::dispatchNow($job);
PHP;
            self::assertSame(strlen($source), file_put_contents($project . '/app/RemovedSymbols.php', $source));
            $inputSnapshot = FixtureSnapshot::capture($temporary);
            $report = LaravelTransitionFixtureFactory::analyzer()->analyzeUpgrade(new UpgradeRequest(
                $project,
                [new UpgradeTarget('laravel/framework', '^10.0')],
                '8.0.2',
                '8.1.0',
                ['app'],
                ['laravel'],
                'json',
                null,
                false,
                [],
                null,
                ComposerExecutionConfiguration::restricted()
            ));
            $canonical = json_decode((new JsonReportWriter())->render($report), true, 512, JSON_THROW_ON_ERROR);

            self::assertSame('0.8', $canonical['metadata']['schema_version']);
            self::assertSame('feasible_with_changes', $canonical['resolution']['status']);
            self::assertSame('supported', $canonical['transition']['framework_guidance'][0]['status']);
            self::assertSame('evaluated', $canonical['staged_resolution']['execution_state']);
            self::assertSame('feasible_with_changes', $canonical['staged_resolution']['status']);
            self::assertNull($canonical['staged_resolution']['stop_reason']);
            $stages = array_column($canonical['staged_resolution']['stages'], null, 'id');
            self::assertSame(['laravel-8-to-9', 'laravel-9-to-10'], array_keys($stages));
            self::assertSame($stages['laravel-8-to-9']['output_state'], $stages['laravel-9-to-10']['input_state']);
            foreach ($stages as $stage) {
                self::assertSame('original_project', $stage['source_snapshot']);
                self::assertSame('feasible_with_changes', $stage['resolution_status']);
                self::assertNotNull($stage['output_state']);
                $selected = array_values(array_filter($stage['attempts'], static fn (array $attempt): bool => $attempt['selected']));
                self::assertCount(1, $selected);
                self::assertSame('success', $selected[0]['scenario']['outcome']);
                self::assertSame('composer', $selected[0]['scenario']['command'][0]);
                self::assertNotNull($selected[0]['scenario']['composer_version']);
                self::assertNotNull($selected[0]['scenario']['candidate_lock']);
            }

            $expectations = [
                ['SerializableClosureFactory or SerializableClosure classes', 'laravel-8-to-9', [
                    ['Illuminate\\Queue\\SerializableClosure', 'instantiated_class', 7],
                    ['Illuminate\\Queue\\SerializableClosureFactory', 'static_call', 8],
                ]],
                ['MocksApplicationServices trait', 'laravel-9-to-10', [
                    ['Illuminate\\Foundation\\Testing\\Concerns\\MocksApplicationServices', 'trait_reference', 9],
                ]],
                ['Bus::dispatchNow or dispatch_now', 'laravel-9-to-10', [
                    ['Illuminate\\Support\\Facades\\Bus::dispatchNow', 'deprecated_queue_dispatch', 10],
                ]],
            ];
            $evidence = array_column($canonical['evidence'], null, 'id');
            $expectedImpact = [];
            foreach ($expectations as [$summary, $stageId, $expectedUsages]) {
                $findings = $this->matchingFindings($canonical['framework_findings'], $summary);
                self::assertCount(1, $findings, $summary);
                $finding = $findings[0];
                self::assertSame('high', $finding['severity']);
                self::assertSame([[
                    'from_major' => $stages[$stageId]['from_major'],
                    'to_major' => $stages[$stageId]['to_major'],
                ]], $finding['applies_to_hops']);
                $sourceReferences = [];
                foreach ($expectedUsages as [$symbol, $usageType, $line]) {
                    $expectedImpact[] = [$symbol, $usageType, $line, [$stageId]];
                    $usages = array_values(array_filter(
                        $canonical['source_inventory'],
                        static fn (array $usage): bool => $usage['symbol'] === $symbol && $usage['usage_type'] === $usageType
                    ));
                    self::assertCount(1, $usages);
                    self::assertSame('app/RemovedSymbols.php', $usages[0]['file']);
                    self::assertSame($line, $usages[0]['line']);
                    foreach ($usages[0]['evidence'] as $reference) {
                        self::assertContains($reference, $finding['evidence']);
                        self::assertSame('E3', $evidence[$reference]['class']);
                        self::assertSame('app/RemovedSymbols.php', $evidence[$reference]['context']['file']);
                        self::assertSame($line, $evidence[$reference]['context']['line']);
                        $sourceReferences[] = $reference;
                    }
                }
                $documentation = array_values(array_filter(
                    $finding['evidence'],
                    static fn (string $reference): bool => $evidence[$reference]['class'] === 'E4'
                ));
                self::assertCount(1, $documentation);
                self::assertNotEmpty($evidence[$documentation[0]]['context']['source']);
                self::assertSame(count($sourceReferences) + 1, count($finding['evidence']));
                foreach ($stages as $id => $stage) {
                    self::assertSame(
                        $id === $stageId ? [$finding] : [],
                        $this->matchingFindings($stage['source_findings'], $summary)
                    );
                    if ($id === $stageId) {
                        foreach ($finding['evidence'] as $reference) {
                            self::assertContains($reference, $stage['evidence']);
                        }
                    }
                }
            }
            $actualImpact = [];
            foreach ($canonical['staged_resolution']['source_impact'] as $impact) {
                foreach ($impact['stage_ids'] as $stageId) {
                    self::assertContains($impact['id'], $stages[$stageId]['source_impact']);
                }
                self::assertNotEmpty(array_filter(
                    $impact['evidence'],
                    static fn (string $reference): bool => $evidence[$reference]['class'] === 'E4'
                ));
                foreach ($impact['occurrences'] as $occurrence) {
                    self::assertSame('app/RemovedSymbols.php', $occurrence['file']);
                    $actualImpact[] = [$occurrence['symbol'], $occurrence['usage_type'], $occurrence['line'], $impact['stage_ids']];
                }
            }
            sort($expectedImpact);
            sort($actualImpact);
            self::assertSame($expectedImpact, $actualImpact);
            $writer = new MarkdownReportWriter();
            self::assertSame($writer->renderCanonical($canonical), $writer->render($report));
            $inputSnapshot->assertUnchanged($this);
        } finally {
            try {
                $projectSnapshot->assertUnchanged($this);
                $packageSnapshot->assertUnchanged($this);
            } finally {
                $filesystem->remove($temporary);
                self::assertDirectoryDoesNotExist($temporary);
            }
        }
    }

    /**
     * @param list<array<string, mixed>> $findings
     * @return list<array<string, mixed>>
     */
    private function matchingFindings(array $findings, string $summary): array
    {
        return array_values(array_filter($findings, static fn (array $finding): bool => strpos($finding['summary'], $summary) !== false));
    }
}
