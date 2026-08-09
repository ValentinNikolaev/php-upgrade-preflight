<?php

declare(strict_types=1);

namespace PhpUpgradePreflight\Tests\Release;

use PhpUpgradePreflight\Core\Model\ReportMetadata;
use PHPUnit\Framework\TestCase;

final class V02ContractTest extends TestCase
{
    /** @var array<string, mixed> */
    private array $contract;
    private string $root;

    protected function setUp(): void
    {
        parent::setUp();

        $this->root = dirname(__DIR__, 2);
        $contents = file_get_contents($this->root . '/tests/fixtures/contracts/v0.2.json');
        self::assertIsString($contents);
        $contract = json_decode($contents, true, 512, JSON_THROW_ON_ERROR);
        self::assertIsArray($contract);
        $this->contract = $contract;
    }

    public function testTransitionStatusesForbidBestGuessesAndExposeMissingHops(): void
    {
        $semantics = $this->contract['transition_semantics'];
        self::assertIsArray($semantics);
        self::assertSame(
            ['supported', 'partially_supported', 'unsupported'],
            array_keys($semantics['statuses'])
        );

        foreach (['ambiguous_source', 'ambiguous_target'] as $case) {
            self::assertSame('unsupported', $semantics['rules'][$case]['status']);
            self::assertSame([], $semantics['rules'][$case]['hops']);
            self::assertTrue($semantics['rules'][$case]['uncertainty_required']);
            self::assertTrue($semantics['rules'][$case]['best_guess_forbidden']);
        }

        self::assertSame('partially_supported', $semantics['rules']['missing_some_hops']['status']);
        self::assertTrue($semantics['rules']['missing_some_hops']['covered_prefix_from_source_required']);
        self::assertTrue($semantics['rules']['missing_some_hops']['missing_hops_must_be_listed']);
        self::assertTrue($semantics['rules']['missing_some_hops']['guidance_must_not_cross_gap']);
        self::assertTrue($semantics['rules']['missing_some_hops']['post_gap_coverage_ignored']);
        self::assertSame('unsupported', $semantics['rules']['first_hop_missing']['status']);
        self::assertSame([], $semantics['rules']['first_hop_missing']['hops']);
        self::assertTrue($semantics['rules']['first_hop_missing']['post_gap_coverage_ignored']);
        self::assertSame('unsupported', $semantics['rules']['missing_all_hops']['status']);
    }

    public function testComposerAndFrameworkStatusesFormIndependentDimensions(): void
    {
        $composition = $this->contract['composition'];
        self::assertIsArray($composition);
        self::assertSame(
            ['resolution.status', 'transition.framework_guidance[].status'],
            $composition['independent_dimensions']
        );

        $combinations = [];
        foreach ($composition['resolution_statuses'] as $resolution) {
            foreach ($composition['transition_statuses'] as $transition) {
                $combinations[] = $resolution . ':' . $transition;
            }
        }

        self::assertCount(12, array_unique($combinations));
        self::assertContains('feasible_with_changes:partially_supported', $combinations);
        self::assertContains('blocked:supported', $combinations);
        self::assertContains('feasible:unsupported', $combinations);
        self::assertCount(7, $composition['rules']);
    }

    public function testSchemaPlanLocksMigrationFieldsAndHistoricalArtifacts(): void
    {
        $schema = $this->contract['schema_0_7'];
        self::assertIsArray($schema);
        self::assertSame(['platform', 'source_inventory'], $schema['new_required_top_level_fields']);
        self::assertSame(['framework_guidance'], $schema['transition_additions']);
        self::assertContains('extensions.completeness', $schema['platform_fields']);
        self::assertContains('extensions.unmodeled_provenance', $schema['platform_fields']);
        self::assertSame('source_inventory', $schema['migration']['v0_6_source_impact_becomes']);
        self::assertTrue($schema['migration']['historical_reports_are_not_rewritten']);

        foreach ($schema['historical_artifacts'] as $filename => $sha256) {
            $directory = str_ends_with($filename, '.schema.json')
                ? '/packages/core/resources/schema/'
                : '/packages/core/tests/Snapshots/';
            self::assertSame(
                $sha256,
                hash_file('sha256', $this->root . $directory . $filename),
                $filename . ' changed after its contract was published.'
            );
        }

        self::assertFileExists($this->root . '/packages/core/resources/schema/upgrade-report-v0.7.schema.json');
    }

    public function testMainUsesTheV02DevelopmentIdentityAcrossPackagesAndReports(): void
    {
        $policy = $this->contract['development_version'];
        self::assertSame($policy['report_tool_version'], ReportMetadata::TOOL_VERSION);
        self::assertSame('0.7', ReportMetadata::SCHEMA_VERSION);

        $root = $this->readJson($this->root . '/composer.json');
        foreach (['core', 'cli', 'laravel'] as $directory) {
            $packageName = 'php-upgrade-preflight/' . $directory;
            self::assertSame($policy['composer_development_version'], $root['require'][$packageName]);

            $repositoryVersions = [];
            foreach ($root['repositories'] as $repository) {
                if (($repository['url'] ?? null) === 'packages/' . $directory) {
                    $repositoryVersions = $repository['options']['versions'];
                }
            }
            self::assertSame($policy['composer_development_version'], $repositoryVersions[$packageName]);

            $package = $this->readJson($this->root . '/packages/' . $directory . '/composer.json');
            self::assertSame(
                $policy['composer_development_version'],
                $package['extra']['branch-alias']['dev-main']
            );
            if ($directory !== 'core') {
                self::assertSame($policy['internal_constraint'], $package['require']['php-upgrade-preflight/core']);
            }
        }
    }

    public function testReleaseChecklistUsesDerivedVersionPlaceholders(): void
    {
        $checklist = file_get_contents($this->root . '/docs/release-checklist.md');
        self::assertIsString($checklist);
        self::assertStringStartsWith('# Release checklist', $checklist);
        self::assertStringContainsString('VERSION', $checklist);
        self::assertStringContainsString('SERIES', $checklist);
        self::assertStringContainsString('DEV_VERSION', $checklist);
        self::assertStringNotContainsString('# v0.1 release checklist', $checklist);
        self::assertStringNotContainsString('composer release:verify -- 0.1.0', $checklist);
        self::assertStringNotContainsString('- [x]', $checklist);
    }

    public function testReleaseLinePolicyIsConsistentAcrossRepositoryInstructions(): void
    {
        $contributing = file_get_contents($this->root . '/CONTRIBUTING.md');
        $agentRules = file_get_contents($this->root . '/.claude/CLAUDE.md');
        $memory = file_get_contents($this->root . '/.claude/memory/MEMORY.md');
        $checklist = file_get_contents($this->root . '/docs/release-checklist.md');

        foreach ([$contributing, $agentRules, $memory, $checklist] as $contents) {
            self::assertIsString($contents);
            self::assertStringContainsString('0.1.x', $contents);
            self::assertStringContainsString('main', $contents);
        }
        self::assertStringContainsString('protected `0.1.x` maintenance branch', $contributing);
        self::assertStringContainsString('approved release line', $agentRules);
        self::assertStringContainsString('approved release line', $memory);
        self::assertStringContainsString('approved release branch exists on `origin`', $checklist);
    }

    public function testRepresentativeReportSnapshotsStayInsideSizeAndRedactionBudgets(): void
    {
        $budgets = $this->contract['budgets'];
        self::assertIsArray($budgets);
        $combined = 0;
        $canaryContents = file_get_contents($this->root . '/tests/fixtures/security/composer-output-with-secrets.json');
        self::assertIsString($canaryContents);
        $canaryFixture = json_decode($canaryContents, true, 512, JSON_THROW_ON_ERROR);
        self::assertIsArray($canaryFixture);

        foreach ($budgets['corpus'] as $fixture) {
            foreach (['json', 'md'] as $format) {
                $path = sprintf('%s/packages/laravel/tests/Snapshots/%s.%s', $this->root, $fixture, $format);
                $size = filesize($path);
                self::assertIsInt($size);
                $combined += $size;
                $limit = $format === 'json'
                    ? $budgets['report_size']['json_per_fixture_bytes']
                    : $budgets['report_size']['markdown_per_fixture_bytes'];
                self::assertLessThanOrEqual($limit, $size, basename($path) . ' exceeds its report-size budget.');

                $report = file_get_contents($path);
                self::assertIsString($report);
                if ($format === 'json') {
                    $canonical = json_decode($report, true, 512, JSON_THROW_ON_ERROR);
                    self::assertIsArray($canonical);
                    self::assertNotSame([], $canonical['transition']['framework_guidance'], basename($path));
                    foreach ($canonical['framework_findings'] as $finding) {
                        self::assertNotSame([], $finding['applies_to_hops'], basename($path));
                    }
                    self::assertContains(
                        $canonical['platform']['extensions']['completeness'],
                        ['none', 'partial', 'complete']
                    );
                    if ($canonical['platform']['extensions']['completeness'] !== 'complete') {
                        self::assertSame(
                            'analyzer_runtime',
                            $canonical['platform']['extensions']['unmodeled_provenance']
                        );
                    }
                }
                foreach ($canaryFixture['canaries'] as $canary) {
                    self::assertStringNotContainsString($canary, $report, basename($path));
                }
            }
        }

        self::assertLessThanOrEqual($budgets['report_size']['combined_corpus_bytes'], $combined);
        self::assertSame(0, $budgets['redaction']['allowed_seeded_canary_occurrences']);
        self::assertSame([
            'canonical_json',
            'markdown',
            'evidence',
            'exception_messages',
            'debug_output',
            'console_diagnostics',
            'ci_logs',
            'release_archives',
        ], $budgets['redaction']['surfaces']);
        self::assertSame([
            'project' => '[PROJECT_ROOT]',
            'output' => '[REPORT_OUTPUT]',
            'local_repository' => '[LOCAL_REPOSITORY]',
            'analyzer_workspace' => '[ANALYZER_WORKSPACE]',
        ], $budgets['path_exposure']['default_shareable_markers']);
        self::assertTrue($budgets['path_exposure']['source_locations_are_project_relative']);
        self::assertTrue($budgets['path_exposure']['exact_workspace_requires_debug']);
        self::assertFalse($budgets['path_exposure']['debug_artifacts_are_shareable']);
        self::assertTrue($budgets['path_exposure']['credentials_are_redacted_in_debug']);
        self::assertSame(3, $budgets['determinism']['reruns']);
        self::assertTrue($budgets['determinism']['require_byte_identical_normalized_reports']);
        self::assertSame([
            'modeled_extensions_are_host_independent' => true,
            'partial_unlisted_extensions_are_host_dependent' => true,
            'complete_input_required_for_full_host_independence' => true,
        ], $budgets['determinism']['platform_scope']);
        self::assertSame(268435456, $budgets['memory']['peak_bytes']);
        self::assertSame(30, $budgets['runtime']['corpus_seconds']);
    }

    /** @return array<string, mixed> */
    private function readJson(string $path): array
    {
        $contents = file_get_contents($path);
        self::assertIsString($contents);
        $decoded = json_decode($contents, true, 512, JSON_THROW_ON_ERROR);
        self::assertIsArray($decoded);

        return $decoded;
    }
}
