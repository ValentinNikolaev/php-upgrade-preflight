<?php

declare(strict_types=1);

namespace PhpUpgradePreflight\Tests\Release;

use Opis\JsonSchema\Errors\ErrorFormatter;
use Opis\JsonSchema\Validator;
use PhpUpgradePreflight\Core\Analysis\StagedAnalysisPolicy;
use PhpUpgradePreflight\Core\Framework\FrameworkStageTargetProvider;
use PhpUpgradePreflight\Core\Model\ComposerExecutionConfiguration;
use PhpUpgradePreflight\Core\Model\FrameworkStagePlan;
use PhpUpgradePreflight\Core\Model\ReportMetadata;
use PhpUpgradePreflight\Core\Model\StageAnalysis;
use PhpUpgradePreflight\Core\Model\StageBlockerEntry;
use PhpUpgradePreflight\Core\Model\StagedResolution;
use PHPUnit\Framework\TestCase;

final class V03ContractTest extends TestCase
{
    private string $root;

    /** @var array<string, mixed> */
    private array $contract;

    protected function setUp(): void
    {
        parent::setUp();

        $this->root = dirname(__DIR__, 2);
        $this->contract = $this->readJson($this->root . '/tests/fixtures/contracts/v0.3.json');
    }

    public function testReportDimensionsStatusesAndStopConditionsAreExhaustive(): void
    {
        $dimensions = $this->contract['report_dimensions'];
        self::assertSame([
            'resolution.status',
            'transition.framework_guidance[].status',
            'staged_resolution.status',
        ], $dimensions['independent']);
        self::assertSame(StagedResolution::statuses(), $dimensions['direct_resolution_statuses']);
        self::assertSame(StagedResolution::statuses(), $dimensions['staged_resolution_statuses']);
        self::assertTrue($dimensions['silent_cross_dimension_upgrade_forbidden']);

        $stage = $this->contract['stage_contract'];
        self::assertSame([StageAnalysis::EXECUTED, StageAnalysis::SKIPPED], $stage['execution_states']);
        self::assertSame(StagedResolution::statuses(), $stage['resolution_statuses']);
        self::assertSame([
            'missing_target',
            'ambiguous_transition',
            'guidance_gap',
            FrameworkStagePlan::REASON_ANALYSIS_PHP_UNAVAILABLE,
            'solver_blocker',
            'timeout',
            'aggregate_timeout',
            'operational_failure',
            'provider_conflict',
            'hop_budget',
        ], array_keys($stage['stop_conditions']));
        self::assertSame('skipped', $stage['later_stages_after_stop']);
        self::assertSame('original_project', $stage['source_snapshot']);
        self::assertTrue($stage['source_change_application_forbidden']);
    }

    public function testBlockerIdentityLifecycleAndDeduplicationAreLocked(): void
    {
        $registry = $this->contract['blocker_registry'];
        self::assertSame('ordered_collection', $registry['shape']);
        self::assertFalse($registry['nullable']);
        self::assertTrue($registry['singleton_shortcut_forbidden']);
        self::assertSame([
            'detected',
            'persists',
            'resolved',
            'superseded',
        ], $registry['lifecycles']);
        self::assertSame([
            StageBlockerEntry::DETECTED,
            StageBlockerEntry::PERSISTS,
            StageBlockerEntry::RESOLVED,
            StageBlockerEntry::SUPERSEDED,
        ], $registry['lifecycles']);
        self::assertContains('constraint', $registry['required_fields']);
        self::assertContains('dependency_path', $registry['required_fields']);
        self::assertContains('first_seen', $registry['required_fields']);
        self::assertContains('last_seen', $registry['required_fields']);
        self::assertContains('lifecycle_history', $registry['required_fields']);
        self::assertFalse($registry['deduplication']['similar_prose_is_identity']);
        self::assertFalse($registry['deduplication']['similar_package_name_is_identity']);
        self::assertStringContainsString('never merge', $registry['deduplication']['across_stages']);
    }

    public function testRemediationOrderingStateCarryAndWholeRegistryGateAreLocked(): void
    {
        $remediation = $this->contract['remediation'];
        self::assertSame([
            'target_only',
            'locked_package_remediation',
        ], $remediation['attempt_orders']['without_adapter_root_remediations']);
        self::assertSame([
            'target_only',
            'root_constraint_remediation',
            'root_and_locked_package_remediation',
        ], $remediation['attempt_orders']['with_adapter_root_remediations']);
        $contractedStrategies = array_values(array_unique(array_merge(
            $remediation['attempt_orders']['without_adapter_root_remediations'],
            $remediation['attempt_orders']['with_adapter_root_remediations']
        )));
        $schema = $this->readJson(
            $this->root . '/packages/core/resources/schema/upgrade-report-v0.8.schema.json'
        );
        $schemaStrategies = $schema['$defs']['stageAttempt']['properties']['strategy']['enum'];
        sort($contractedStrategies, SORT_STRING);
        sort($schemaStrategies, SORT_STRING);
        self::assertSame($contractedStrategies, $schemaStrategies);
        self::assertStringContainsString('direct and transitive', $remediation['allowed_locked_changes']);
        self::assertTrue($remediation['every_change_recorded']);
        self::assertTrue($remediation['original_project_immutable']);
        self::assertStringContainsString('selected successful candidate', $remediation['next_stage_input']);
        self::assertStringContainsString('no active blocking entry', $remediation['selection_gate']);
    }

    public function testTargetProviderProfileCompatibilityAndNonGoalsAreExplicit(): void
    {
        $targets = $this->contract['stage_targets'];
        self::assertSame(FrameworkStageTargetProvider::class, $targets['optional_provider_interface']);
        self::assertTrue($targets['required_v0_2_interfaces_unchanged']);
        self::assertTrue($targets['minimum_constraint_is_not_exact_deployment_claim']);
        self::assertSame(1, $targets['active_provider_limit']);
        self::assertSame(
            'rooted laravel/framework 10 with only laravel/framework targeted at 13',
            $targets['milestone_0_slice']
        );
        self::assertStringContainsString('7 through 13', $targets['production_laravel_scope']);
        self::assertStringContainsString('never synthesize', $targets['analysis_php_selection']);

        $profile = $this->contract['target_platform_profile'];
        self::assertTrue($profile['versioned']);
        self::assertSame('1.0', $profile['schema_version']);
        self::assertSame('platform.profile', $profile['report_location']);
        self::assertSame('request_summary.target_platform_profile', $profile['request_summary_location']);
        self::assertSame('2.2.0', $profile['composer_complete_minimum']);
        self::assertSame('unknown', $profile['older_composer_complete_result']);
        self::assertTrue($profile['conflicts_rejected_before_composer']);
        self::assertSame(['php', 'extension', 'library', 'php_subtype', 'composer_platform'], $profile['supported_classes']);
        self::assertSame(
            ['php', 'ext-*', 'lib-*', 'php-subtype', 'composer-platform'],
            $profile['supported_package_patterns']
        );
        self::assertSame(['php_api', 'file'], $profile['profile_provenance']);
        self::assertSame(['request', 'composer_config', 'profile', 'closed_world'], $profile['effective_provenance']);
        self::assertSame(['composer_config', 'toolchain_bound'], $profile['simulation']);
        self::assertTrue($profile['toolchain_bound_are_package_names']);
        self::assertTrue($profile['effective_values_sorted_by_name']);
        self::assertTrue($profile['profile_paths_forbidden_in_canonical_output']);
        self::assertTrue($profile['complete_closed_world']);
        self::assertTrue($profile['complete_closed_world_excludes_toolchain_bound']);
        self::assertTrue($profile['partial_host_dependent']);

        foreach (['Symfony adapter', 'CodeIgniter adapter', 'PHAR delivery', 'versioned container delivery', 'runtime floor change'] as $nonGoal) {
            self::assertContains($nonGoal, $this->contract['non_goals']);
        }
    }

    public function testBudgetsMatchTheProductionPolicyAndOrderingIsComplete(): void
    {
        self::assertSame(
            StagedAnalysisPolicy::MAX_HOPS * StagedAnalysisPolicy::MAX_ATTEMPTS_PER_STAGE,
            StagedAnalysisPolicy::MAX_SCENARIOS,
            'The scenario cap is derived from the independently enforced hop and per-stage attempt caps.'
        );
        self::assertSame([
            'max_hops' => StagedAnalysisPolicy::MAX_HOPS,
            'max_attempts_per_stage' => StagedAnalysisPolicy::MAX_ATTEMPTS_PER_STAGE,
            'max_scenarios' => StagedAnalysisPolicy::MAX_SCENARIOS,
            'scenario_timeout_seconds' => StagedAnalysisPolicy::SCENARIO_TIMEOUT_SECONDS,
            'aggregate_timeout_seconds' => StagedAnalysisPolicy::AGGREGATE_TIMEOUT_SECONDS,
            'scenario_timeout_application' => 'effective staged timeout is the lesser of the request timeout and scenario_timeout_seconds',
            'aggregate_start_gate' => 'an attempt starts only when its full effective scenario timeout fits within the remaining aggregate budget',
            'memory_bytes' => StagedAnalysisPolicy::MEMORY_BUDGET_BYTES,
            'json_report_bytes' => StagedAnalysisPolicy::JSON_REPORT_BUDGET_BYTES,
            'markdown_report_bytes' => StagedAnalysisPolicy::MARKDOWN_REPORT_BUDGET_BYTES,
        ], $this->contract['budgets']);
        self::assertCount(7, $this->contract['ordering']);
    }

    public function testComposerExecutionModesThreatModelAndDefaultsAreLocked(): void
    {
        $execution = $this->contract['composer_execution'];
        self::assertSame(['compatible', 'restricted'], $execution['modes']);
        self::assertSame(ComposerExecutionConfiguration::DEFAULT_EXPECTED_VERSION, $execution['expected_version_default']);
        self::assertSame(ComposerExecutionConfiguration::DEFAULT_SCENARIO_TIMEOUT_SECONDS, $execution['scenario_timeout_default_seconds']);
        self::assertSame(ComposerExecutionConfiguration::DEFAULT_DIAGNOSTIC_TIMEOUT_SECONDS, $execution['diagnostic_timeout_default_seconds']);
        self::assertSame('inherited', $execution['compatible']['environment_mode']);
        self::assertTrue($execution['compatible']['credentials_may_be_inherited']);
        self::assertSame('sanitized', $execution['restricted']['environment_mode']);
        self::assertSame('best_effort_offline', $execution['restricted']['network_policy']);
        self::assertFalse($execution['restricted']['credentials_may_be_inherited']);
        self::assertFalse($execution['restricted']['os_network_sandbox_claimed']);
        self::assertContains('COMPOSER_AUTH', $execution['restricted_controlled_sources']);
        self::assertContains('OS-level networking and process isolation', $execution['residual_boundaries']);
        self::assertSame('operational_unknown', $execution['restricted_metadata_miss']);
    }

    public function testSchemaMigrationAndMinimalCanonicalFixtureAreValid(): void
    {
        $schemaContract = $this->contract['schema_0_8'];
        self::assertSame('0.8', ReportMetadata::SCHEMA_VERSION);
        self::assertSame('0.3.0-dev', ReportMetadata::TOOL_VERSION);
        self::assertSame(['composer_execution', 'staged_resolution'], $schemaContract['new_required_top_level_fields']);
        self::assertSame([
            'request_summary.target_platform_profile',
            'request_summary.composer_execution',
            'platform.profile',
            'staged_resolution.stages[].platform',
            'staged_resolution.stages[].composer_execution',
            'staged_resolution.stages[].duration_ms',
            'staged_resolution.stages[].evidence',
            'staged_resolution.source_impact',
            'staged_resolution.stages[].blockers',
            'staged_resolution.stages[].source_snapshot_note',
            'staged_resolution.stages[].risk.stage_id',
            'staged_resolution.stages[].effort.stage_id',
            'staged_resolution.stages[].tests',
            'source_impact[].id',
            'source_impact[].stage_ids',
            'plan.stages[].stage_id',
        ], $schemaContract['new_required_nested_fields']);
        self::assertTrue($schemaContract['migration_from_0_7']['historical_reports_immutable']);
        self::assertTrue($schemaContract['migration_from_0_7']['missing_staged_resolution_means_v0_7_not_empty_v0_8']);
        self::assertTrue($schemaContract['migration_from_0_7']['direct_resolution_meaning_unchanged']);
        self::assertTrue($schemaContract['migration_from_0_7']['framework_guidance_meaning_unchanged']);

        $schemaContents = file_get_contents($this->root . '/' . $schemaContract['path']);
        $fixtureContents = file_get_contents($this->root . '/' . $schemaContract['minimal_fixture']);
        self::assertIsString($schemaContents);
        self::assertIsString($fixtureContents);
        $schema = json_decode($schemaContents, false, 512, JSON_THROW_ON_ERROR);
        $fixture = json_decode($fixtureContents, false, 512, JSON_THROW_ON_ERROR);
        $result = (new Validator(null, 20, false))->validate($fixture, $schema);
        if ($result->hasError()) {
            $error = $result->error();
            self::assertNotNull($error);
            self::fail(json_encode((new ErrorFormatter())->format($error), JSON_PRETTY_PRINT | JSON_THROW_ON_ERROR));
        }
        self::assertTrue($result->isValid());

        $migrationContents = file_get_contents($this->root . '/' . $schemaContract['migration_fixture']);
        self::assertIsString($migrationContents);
        $migration = json_decode($migrationContents, true, 512, JSON_THROW_ON_ERROR);
        self::assertSame('0.7', $migration['source_schema_version']);
        self::assertSame('0.8', $migration['target_schema_version']);
        self::assertSame('metadata.schema_version', $migration['dispatch_path']);
        $source = $this->readJson($this->root . '/' . $migration['source_fixture']);
        self::assertSame('0.7', $source['metadata']['schema_version']);
        self::assertArrayNotHasKey('staged_resolution', $source);
        self::assertContains('staged_resolution.source_impact', $migration['v0_8_only_paths']);
    }

    public function testTargetPlatformProfileMachineContractMatchesThePublishedSchema(): void
    {
        $profile = $this->contract['target_platform_profile'];
        $schema = $this->readJson(
            $this->root . '/packages/core/resources/schema/upgrade-report-v0.8.schema.json'
        );

        self::assertContains('target_platform_profile', $schema['$defs']['requestSummary']['required']);
        self::assertContains('profile', $schema['$defs']['platformProvenance']['required']);
        self::assertSame(
            $profile['request_summary_fields'],
            $schema['$defs']['targetPlatformProfileSummary']['oneOf'][0]['required']
        );
        self::assertSame(
            $profile['report_fields'],
            $schema['$defs']['targetPlatformProfileReport']['oneOf'][0]['required']
        );
        self::assertSame($profile['supported_classes'], $schema['$defs']['platformPackageClass']['enum']);
        self::assertSame(
            $profile['supported_classes'],
            $schema['$defs']['targetPlatformProfileReport']['oneOf'][0]['properties']['supported_classes']['const']
        );
        self::assertSame(
            $profile['effective_fields'],
            $schema['$defs']['effectivePlatformDecision']['required']
        );
        self::assertSame(
            $profile['effective_provenance'],
            $schema['$defs']['effectivePlatformDecision']['properties']['provenance']['enum']
        );
        self::assertSame(
            $profile['simulation'],
            $schema['$defs']['effectivePlatformDecision']['properties']['simulation']['enum']
        );
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
