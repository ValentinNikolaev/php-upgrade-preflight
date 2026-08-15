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
            self::assertArrayNotHasKey($mutation['name'], $configured, 'Mutation names must be unique.');
            $configured[$mutation['name']] = $mutation['file'];
        }

        $expected = [
            'scenario-selection-package-target-guard' => 'packages/core/src/Analysis/ScenarioSelector.php',
            'composer-blocker-platform-pattern' => 'packages/core/src/Analysis/ComposerBlockerParser.php',
            'schema-version-constant' => 'packages/core/resources/schema/upgrade-report-v0.8.schema.json',
            'risk-resolution-blocker-level' => 'packages/core/src/Analysis/RiskAndEffortEstimator.php',
            'laravel-transition-equal-major-guard' => 'packages/laravel/src/LaravelFrameworkIntegration.php',
            'release-series-lock-branch' => 'tools/ReleaseVerifier.php',
        ];
        ksort($expected);
        ksort($configured);

        self::assertSame($expected, $configured);
    }
}
