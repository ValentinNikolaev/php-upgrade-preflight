<?php

declare(strict_types=1);

namespace PhpUpgradePreflight\Laravel\Tests\Integration;

use PhpUpgradePreflight\Core\Analysis\DefaultUpgradeAnalyzer;
use PhpUpgradePreflight\Core\Composer\ComposerScenarioRunner;
use PhpUpgradePreflight\Core\Model\Evidence;
use PhpUpgradePreflight\Core\Model\UpgradeReport;
use PhpUpgradePreflight\Core\Model\UpgradeRequest;
use PhpUpgradePreflight\Core\Model\UpgradeTarget;
use PhpUpgradePreflight\Laravel\LaravelFrameworkIntegration;
use PhpUpgradePreflight\Tests\Support\FixtureSnapshot;
use PHPUnit\Framework\TestCase;

final class LaravelFixtureAnalysisTest extends TestCase
{
    public function testLaravel7To8FixtureProducesOnlyEncodedConstraintFindings(): void
    {
        $report = $this->analyze('laravel-7-to-8', '^8.0', '8.0');

        self::assertSame('feasible_with_changes', $report->resolutionStatus());
        $this->assertFrameworkFindings($report, [
            'Update the root laravel/framework constraint from `^7.0` to a constraint compatible with Laravel 8.',
        ]);
        $this->assertFrameworkTransition($report, 'v8.83.27');
    }

    public function testLaravel7To9FixtureProducesOnlyTheFrameworkConstraintFinding(): void
    {
        $report = $this->analyze('laravel-7-to-9', '^9.0', '8.1');

        self::assertSame('feasible_with_changes', $report->resolutionStatus());
        $this->assertFrameworkFindings($report, [
            'Update the root laravel/framework constraint from `^7.0` to a constraint compatible with Laravel 9.',
        ]);
        $this->assertFrameworkTransition($report, 'v9.52.16');
    }

    public function testBlockedIlluminateConstraintFixtureIdentifiesTheLockedConsumer(): void
    {
        $report = $this->analyze('blocked-illuminate-constraint', '^9.0', '8.1');

        self::assertSame('blocked', $report->resolutionStatus());
        $this->assertFrameworkFindings($report, [
            'Update the root laravel/framework constraint from `^7.0` to a constraint compatible with Laravel 9.',
            'Update or replace incompatible illuminate/support constraints before targeting Laravel 9: fixture/illuminate-consumer.',
        ]);
        self::assertSame(
            ['transitive-package-conflict'],
            array_values(array_unique(array_map(static fn ($blocker): string => $blocker->type(), $report->blockers())))
        );
        self::assertContains('fixture/illuminate-consumer', array_map(
            static fn (Evidence $evidence): ?string => is_string($evidence->context()['package'] ?? null)
                ? $evidence->context()['package']
                : null,
            $report->evidence()
        ));
    }

    public function testIgnitionFixtureLinksPackageAndSkeletonSourceEvidence(): void
    {
        $report = $this->analyze('ignition-legacy-skeleton', '^8.0', '8.0');

        self::assertSame('blocked', $report->resolutionStatus());
        $this->assertFrameworkFindings($report, [
            'Update the root laravel/framework constraint from `^7.0` to a constraint compatible with Laravel 8.',
            'The root PHP constraint `^7.4` excludes target PHP `8.0.0`; update it for the Laravel 8 upgrade.',
            'facade/ignition 1.16.4 is outside the encoded Laravel 8 review range `>=2.3.6 <3.0`; review its upgrade or replacement.',
            'Compare detected Laravel skeleton-managed integration locations (Kernel middleware, app config providers/aliases, or TrustProxies inheritance) with the Laravel 8 skeleton; these are review locations, not confirmed incompatibilities.',
        ]);
        self::assertContains('middleware_reference', $this->sourceUsageTypes($report));
        self::assertContains('service_provider', $this->sourceUsageTypes($report));
        self::assertContains('facade_alias', $this->sourceUsageTypes($report));
        self::assertContains(Evidence::E3_PROJECT_SOURCE, $this->evidenceClasses($report));
        self::assertContains(Evidence::E5_HEURISTIC, $this->evidenceClasses($report));
    }

    public function testPhpAndExtensionConflictFixtureProducesStructuredSolverBlockers(): void
    {
        $report = $this->analyze('php-extension-conflict', '^9.0', '8.1');

        self::assertSame('blocked', $report->resolutionStatus());
        $this->assertFrameworkFindings($report, [
            'Update the root laravel/framework constraint from `^7.0` to a constraint compatible with Laravel 9.',
            'The root PHP constraint `^7.4` excludes target PHP `8.1.0`; update it for the Laravel 9 upgrade.',
        ]);

        $blockerTypes = array_map(static fn ($blocker): string => $blocker->type(), $report->blockers());
        self::assertContains('php-platform-too-high', $blockerTypes);
        self::assertContains('extension-missing', $blockerTypes, implode(', ', $blockerTypes));
        $this->assertAllReferencesExist($report);
    }

    public function testLaravelPackageMatrixFixtureCoversEveryEncodedPackageFamily(): void
    {
        $report = $this->analyze('laravel-package-matrix', '^9.0', '8.1');

        self::assertSame('blocked', $report->resolutionStatus());
        $this->assertFrameworkFindings($report, [
            'Update the root laravel/framework constraint from `^7.0` to a constraint compatible with Laravel 9.',
            'The root PHP constraint `^7.4` excludes target PHP `8.1.0`; update it for the Laravel 9 upgrade.',
            'laravel/passport v8.5.0 is outside the encoded Laravel 9 review range `^10.0|^11.0`; review its upgrade or replacement.',
            'laravel/sanctum v1.3.3 is outside the encoded Laravel 9 review range `^2.0|^3.0`; review its upgrade or replacement.',
            'laravel/horizon v4.3.5 is outside the encoded Laravel 9 review range `^5.0`; review its upgrade or replacement.',
            'laravel/telescope v3.7.0 is outside the encoded Laravel 9 review range `^4.0`; review its upgrade or replacement.',
            'phpunit/phpunit 8.5.21 is outside the encoded Laravel 9 review range `^9.5.10`; review its upgrade or replacement.',
            'mockery/mockery 1.3.6 is outside the encoded Laravel 9 review range `^1.4`; review its upgrade or replacement.',
            'Review direct Symfony component constraints for Laravel 9 (`^6.0` expected): symfony/http-foundation.',
            'Replace facade/ignition with spatie/laravel-ignition for the Laravel 9 target.',
            'Remove fideloper/proxy and review the trusted proxy middleware for the Laravel 9 target.',
            'Review removal of fruitcake/laravel-cors because Laravel 9 integrates CORS middleware through the framework.',
            'nunomaduro/collision v4.3.0 is outside the encoded Laravel 9 review range `^6.1`; review its upgrade or replacement.',
            'laravel/ui v2.5.0 is outside the encoded Laravel 9 review range `^4.0`; review its upgrade or replacement.',
            'orchestra/testbench v5.4.0 is outside the encoded Laravel 9 review range `^7.0`; review its upgrade or replacement.',
        ]);
        self::assertContains(Evidence::E2_PACKAGE_METADATA, $this->evidenceClasses($report));
        self::assertContains(Evidence::E4_MAINTAINER_DOCUMENTATION, $this->evidenceClasses($report));
    }

    private function analyze(string $fixture, string $laravelTarget, string $phpTarget): UpgradeReport
    {
        $projectPath = $this->fixturePath($fixture);
        $snapshot = FixtureSnapshot::capture($projectPath);
        $runner = new ComposerScenarioRunner(null, null, static function (array $command, string $workingDirectory): array {
            $manifestContents = file_get_contents($workingDirectory . DIRECTORY_SEPARATOR . 'composer.json');
            if ($manifestContents === false) {
                throw new \RuntimeException('Unable to read fixture manifest.');
            }

            $manifest = json_decode($manifestContents, true, 512, JSON_THROW_ON_ERROR);
            $fixtureName = $manifest['extra']['php-upgrade-preflight-fixture'] ?? null;
            if (in_array('validate', $command, true)) {
                return ['exit_code' => 0, 'stdout' => 'Fixture baseline is valid.', 'stderr' => ''];
            }

            if (in_array('prohibits', $command, true)) {
                return ['exit_code' => 1, 'stdout' => '', 'stderr' => 'No additional fixture diagnostic.'];
            }

            if ($fixtureName === 'blocked-illuminate-constraint') {
                return [
                    'exit_code' => 2,
                    'stdout' => '',
                    'stderr' => implode("\n", [
                        'Your requirements could not be resolved to an installable set of packages.',
                        '- fixture/illuminate-consumer 1.0.0 requires illuminate/support ^7.0 -> found illuminate/support[v7.30.7] but it conflicts with the requested Laravel target.',
                    ]),
                ];
            }

            if ($fixtureName === 'php-extension-conflict') {
                return [
                    'exit_code' => 2,
                    'stdout' => '',
                    'stderr' => implode("\n", [
                        'Your requirements could not be resolved to an installable set of packages.',
                        'Problem 1',
                        '- fixture/runtime-guard 1.0.0 requires php ^7.4 -> your php version (8.1.0) does not satisfy that requirement.',
                        'Problem 2',
                        '- fixture/runtime-guard 1.0.0 requires ext-preflight_fixture * -> it is missing from your system.',
                    ]),
                ];
            }

            if (in_array($fixtureName, ['ignition-legacy-skeleton', 'laravel-package-matrix'], true)) {
                return [
                    'exit_code' => 2,
                    'stdout' => '',
                    'stderr' => implode("\n", [
                        'Your requirements could not be resolved to an installable set of packages.',
                        '- Root composer.json retains legacy package constraints that exclude the requested Laravel target.',
                    ]),
                ];
            }

            if (in_array($fixtureName, ['laravel-7-to-8', 'laravel-7-to-9'], true)) {
                $frameworkConstraint = $manifest['require']['laravel/framework'] ?? null;
                if (is_string($frameworkConstraint) && str_starts_with($frameworkConstraint, '^8.')) {
                    self::writeFrameworkCandidateLock($workingDirectory, 'v8.83.27');
                } elseif (is_string($frameworkConstraint) && str_starts_with($frameworkConstraint, '^9.')) {
                    self::writeFrameworkCandidateLock($workingDirectory, 'v9.52.16');
                }

                return ['exit_code' => 0, 'stdout' => 'Fixture target resolved.', 'stderr' => ''];
            }

            throw new \RuntimeException('Unexpected Laravel fixture process invocation.');
        });

        $report = (new DefaultUpgradeAnalyzer(
            [new LaravelFrameworkIntegration()],
            null,
            $runner
        ))->analyzeUpgrade(new UpgradeRequest(
            $projectPath,
            [new UpgradeTarget('laravel/framework', $laravelTarget)],
            '7.4',
            $phpTarget
        ));

        $snapshot->assertUnchanged($this);
        $this->assertAllReferencesExist($report);

        return $report;
    }

    /** @param list<string> $expectedFindings */
    private function assertFrameworkFindings(UpgradeReport $report, array $expectedFindings): void
    {
        $summaries = array_map(static fn ($finding): string => $finding->summary(), $report->frameworkFindings());
        sort($expectedFindings);
        sort($summaries);

        self::assertSame($expectedFindings, $summaries);
    }

    private function assertFrameworkTransition(UpgradeReport $report, string $targetVersion): void
    {
        $changes = $report->lockDiff()->packageChanges();

        self::assertCount(1, $changes);
        self::assertSame('laravel/framework', $changes[0]->name());
        self::assertSame('v7.30.7', $changes[0]->fromVersion());
        self::assertSame($targetVersion, $changes[0]->toVersion());
        self::assertTrue($changes[0]->isMajorChange());
        self::assertTrue($changes[0]->isDirect());
    }

    private function assertAllReferencesExist(UpgradeReport $report): void
    {
        $evidenceIds = array_map(static fn (Evidence $evidence): string => $evidence->id(), $report->evidence());

        foreach ($report->evidence() as $evidence) {
            if ($evidence->evidenceClass() !== Evidence::E4_MAINTAINER_DOCUMENTATION) {
                continue;
            }

            $context = $evidence->context();
            $hasSource = isset($context['source']) && is_string($context['source']) && $context['source'] !== '';
            $hasSources = isset($context['sources']) && is_array($context['sources']) && $context['sources'] !== [];
            self::assertTrue($hasSource || $hasSources, sprintf('%s must link its documentation source.', $evidence->id()));
        }

        foreach (array_merge($report->frameworkFindings(), $report->blockers(), $report->sourceImpact()) as $finding) {
            self::assertNotSame([], $finding->evidence());
            foreach ($finding->evidence() as $reference) {
                self::assertContains($reference, $evidenceIds);
            }
        }
    }

    /** @return list<string> */
    private function evidenceClasses(UpgradeReport $report): array
    {
        return array_map(static fn (Evidence $evidence): string => $evidence->evidenceClass(), $report->evidence());
    }

    /** @return list<string> */
    private function sourceUsageTypes(UpgradeReport $report): array
    {
        return array_map(static fn ($usage): string => $usage->usageType(), $report->sourceImpact());
    }

    private static function writeFrameworkCandidateLock(string $workingDirectory, string $targetVersion): void
    {
        $lockPath = $workingDirectory . DIRECTORY_SEPARATOR . 'composer.lock';
        $lockContents = file_get_contents($lockPath);
        if ($lockContents === false) {
            throw new \RuntimeException('Unable to read fixture lockfile.');
        }

        $lock = json_decode($lockContents, true, 512, JSON_THROW_ON_ERROR);
        foreach ($lock['packages'] as &$package) {
            if (is_array($package) && ($package['name'] ?? null) === 'laravel/framework') {
                $package['version'] = $targetVersion;
            }
        }
        unset($package);
        $lock['content-hash'] = 'candidate-' . $targetVersion;

        $encoded = json_encode($lock, JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES | JSON_THROW_ON_ERROR) . PHP_EOL;
        if (file_put_contents($lockPath, $encoded) === false) {
            throw new \RuntimeException('Unable to write fixture candidate lockfile.');
        }
    }

    private function fixturePath(string $fixture): string
    {
        return dirname(__DIR__, 4)
            . DIRECTORY_SEPARATOR . 'tests'
            . DIRECTORY_SEPARATOR . 'fixtures'
            . DIRECTORY_SEPARATOR . 'projects'
            . DIRECTORY_SEPARATOR . $fixture;
    }
}
