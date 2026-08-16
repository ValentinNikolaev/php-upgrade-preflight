<?php

declare(strict_types=1);

namespace PhpUpgradePreflight\Tests\Release;

use PHPUnit\Framework\TestCase;

final class SelectiveMutationPolicyTest extends TestCase
{
    public function testConfigurationDefinesEveryCriticalMutantExactlyOnce(): void
    {
        $contents = file_get_contents(dirname(__DIR__, 2) . '/mutation.json');
        self::assertNotFalse($contents);
        $configuration = json_decode($contents, true, 512, JSON_THROW_ON_ERROR);
        self::assertIsArray($configuration);
        self::assertSame(1, $configuration['schema_version'] ?? null);
        self::assertIsArray($configuration['mutations'] ?? null);

        $configured = [];
        foreach ($configuration['mutations'] as $mutation) {
            self::assertIsArray($mutation);
            self::assertIsString($mutation['name'] ?? null);
            self::assertIsString($mutation['file'] ?? null);
            self::assertIsString($mutation['test_filter'] ?? null);
            self::assertArrayNotHasKey($mutation['name'], $configured, 'Mutation names must be unique.');
            $configured[$mutation['name']] = [
                'file' => $mutation['file'],
                'test_filter' => $mutation['test_filter'],
            ];
        }

        $expected = [
            'scenario-selection-package-target-guard' => ['file' => 'packages/core/src/Analysis/ScenarioSelector.php', 'test_filter' => 'ScenarioSelectorTest'],
            'composer-blocker-platform-pattern' => ['file' => 'packages/core/src/Analysis/ComposerBlockerParser.php', 'test_filter' => 'ComposerBlockerParserTranscriptTest'],
            'schema-version-constant' => ['file' => 'packages/core/resources/schema/upgrade-report-v0.8.schema.json', 'test_filter' => 'UpgradeReportSchemaTest'],
            'risk-resolution-blocker-level' => ['file' => 'packages/core/src/Analysis/RiskAndEffortEstimator.php', 'test_filter' => 'RiskAndEffortEstimatorTest'],
            'laravel-transition-equal-major-guard' => ['file' => 'packages/laravel/src/LaravelFrameworkIntegration.php', 'test_filter' => 'LaravelFrameworkIntegrationTest'],
            'release-series-lock-branch' => ['file' => 'tools/ReleaseVerifier.php', 'test_filter' => 'ReleaseVerifierTest'],
            'platform-completeness-requires-php' => ['file' => 'packages/core/src/Model/TargetPlatformProfile.php', 'test_filter' => 'TargetPlatformProfileTest'],
            'profile-request-precedence' => ['file' => 'packages/core/src/Model/TargetPlatform.php', 'test_filter' => 'TargetPlatformResolutionTest'],
            'restricted-execution-environment' => ['file' => 'packages/core/src/Model/ComposerExecutionConfiguration.php', 'test_filter' => 'ComposerExecutionConfigurationTest'],
            'stage-selected-state-chaining' => ['file' => 'packages/core/src/Analysis/StagedUpgradeOrchestrator.php', 'test_filter' => 'StagedUpgradeOrchestratorTest'],
            'state-fingerprint-execution-policy' => ['file' => 'packages/core/src/Model/ProjectStateFingerprint.php', 'test_filter' => 'ProjectStateFingerprintTest'],
            'stage-plan-stop-on-gap' => ['file' => 'packages/laravel/src/LaravelFrameworkIntegration.php', 'test_filter' => 'LaravelFrameworkIntegrationTest'],
            'aggregate-uncertainty-deduplication' => ['file' => 'packages/core/src/Model/UpgradeReport.php', 'test_filter' => 'ReportAssemblerTest'],
            'old-style-adapter-stage-provider-guard' => ['file' => 'packages/core/src/Analysis/StagedUpgradeOrchestrator.php', 'test_filter' => 'testOldStyleAdapterWithoutStageProviderRemainsCompatible'],
        ];
        ksort($expected);
        ksort($configured);

        self::assertSame($expected, $configured);
    }
}
