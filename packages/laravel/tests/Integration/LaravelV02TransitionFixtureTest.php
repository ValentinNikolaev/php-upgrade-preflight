<?php

declare(strict_types=1);

namespace PhpUpgradePreflight\Laravel\Tests\Integration;

use PhpUpgradePreflight\Core\Model\Evidence;
use PhpUpgradePreflight\Core\Model\FrameworkGuidance;
use PhpUpgradePreflight\Core\Model\UpgradeRequest;
use PhpUpgradePreflight\Core\Model\UpgradeTarget;
use PhpUpgradePreflight\Tests\Support\FixtureSnapshot;
use PhpUpgradePreflight\Tests\Support\LaravelTransitionFixtureFactory;
use PhpUpgradePreflight\Tests\Support\LaravelTransitionFixtureRunner;
use PHPUnit\Framework\TestCase;

final class LaravelV02TransitionFixtureTest extends TestCase
{
    /** @dataProvider transitionCaseProvider */
    public function testTransitionCaseCoversResolutionAndGuidanceIndependently(array $case): void
    {
        $path = $this->fixturePath($case['fixture']);
        $snapshot = FixtureSnapshot::capture($path);
        $report = LaravelTransitionFixtureFactory::analyzer($case['catalog'])->analyzeUpgrade(
            new UpgradeRequest(
                $path,
                [new UpgradeTarget($case['target_package'], $case['target_constraint'])],
                null,
                $case['target_php'],
                [],
                ['laravel']
            )
        );

        $snapshot->assertUnchanged($this);
        self::assertSame($case['resolution'], $report->resolutionStatus());
        self::assertCount(1, $report->frameworkGuidance());
        $guidance = $report->frameworkGuidance()[0];
        self::assertSame($case['guidance'], $guidance->status());
        self::assertSame($case['source_major'], $guidance->sourceMajor());
        self::assertSame($case['target_major'], $guidance->targetMajor());
        self::assertGreaterThanOrEqual(
            $case['minimum_framework_findings'] ?? 0,
            count($report->frameworkFindings()),
            $case['name']
        );
        $evidenceById = [];
        foreach ($report->evidence() as $item) {
            $evidenceById[$item->id()] = $item;
        }
        foreach ($report->frameworkFindings() as $finding) {
            self::assertNotSame([], $finding->evidence(), $finding->summary());
            foreach ($finding->evidence() as $reference) {
                self::assertArrayHasKey($reference, $evidenceById, $finding->summary());
                self::assertContains($evidenceById[$reference]->evidenceClass(), [
                    Evidence::E1_SOLVER,
                    Evidence::E2_PACKAGE_METADATA,
                    Evidence::E3_PROJECT_SOURCE,
                    Evidence::E4_MAINTAINER_DOCUMENTATION,
                ], $finding->summary());
            }
        }

        if (isset($case['fixture_role'])
            && $case['guidance'] === FrameworkGuidance::SUPPORTED
            && is_int($case['source_major'])
            && is_int($case['target_major'])
            && $case['target_major'] === $case['source_major'] + 1) {
            $expectedHop = [[
                'from_major' => $case['source_major'],
                'to_major' => $case['target_major'],
            ]];
            self::assertSame([
                [$case['source_major'], $case['target_major'], 'supported'],
            ], $this->hopTriples($guidance));
            foreach ($report->frameworkFindings() as $finding) {
                self::assertSame($expectedHop, $finding->appliesToHops(), $finding->summary());
            }
        }

        if ($case['guidance'] === FrameworkGuidance::UNSUPPORTED) {
            self::assertSame([], $guidance->hops());
            self::assertNotSame([], $guidance->toArray()['uncertainties']);
        }

        if ($case['fixture'] === 'laravel-missing-hop') {
            self::assertSame([
                [10, 11, 'supported'],
                [11, 12, 'unsupported'],
                [12, 13, 'unsupported'],
            ], $this->hopTriples($guidance));
            self::assertCount(2, $guidance->toArray()['uncertainties']);
        }

        if ($case['fixture'] === 'laravel-10-to-13') {
            self::assertSame([
                [10, 11, 'supported'],
                [11, 12, 'supported'],
                [12, 13, 'supported'],
            ], $this->hopTriples($guidance));
        }
    }

    public function testLaravel12To13FixtureExercisesOnlyEvidenceBackedPackageAndSourceRules(): void
    {
        $case = $this->caseNamed('advisory-heavy Laravel 12 to 13');
        $path = $this->fixturePath($case['fixture']);
        $report = LaravelTransitionFixtureFactory::analyzer()->analyzeUpgrade(new UpgradeRequest(
            $path,
            [new UpgradeTarget('laravel/framework', '^13.0')],
            null,
            '8.3',
            [],
            ['laravel']
        ));
        $summaries = array_map(static fn ($finding): string => $finding->summary(), $report->frameworkFindings());

        foreach ([
            'root laravel/framework constraint',
            'root PHP constraint',
            'laravel/boost',
            'laravel/tinker',
            'phpunit/phpunit',
            'pestphp/pest',
            'direct Symfony component constraints',
            'laravel/helpers',
            'PreventRequestForgery',
        ] as $expected) {
            self::assertTrue($this->contains($summaries, $expected), $expected . "\n" . implode("\n", $summaries));
        }
        self::assertFalse($this->contains($summaries, 'nunomaduro/collision'));
        foreach ($report->frameworkFindings() as $finding) {
            self::assertSame([['from_major' => 12, 'to_major' => 13]], $finding->appliesToHops());
        }
    }

    public function testFixtureCandidateLockValidationRejectsRootConstraintViolations(): void
    {
        $lock = [
            'packages' => [
                ['name' => 'illuminate/console', 'version' => 'v13.17.0'],
                ['name' => 'illuminate/support', 'version' => 'v13.17.0'],
            ],
            'packages-dev' => [],
        ];

        self::assertSame([
            'Candidate lock selects illuminate/console v13.17.0, which does not satisfy root constraint `^11.0`.',
        ], LaravelTransitionFixtureRunner::candidateLockViolations([
            'require' => [
                'illuminate/console' => '^11.0',
                'illuminate/support' => '^13.0',
            ],
        ], $lock));
        self::assertSame([], LaravelTransitionFixtureRunner::candidateLockViolations([
            'require' => [
                'illuminate/console' => '^11.0|^13.0',
                'illuminate/support' => '^12.0|^13.0',
            ],
        ], $lock));
    }

    public function testFixtureCandidateLockValidationRejectsTargetPhpOutsideTheRootConstraint(): void
    {
        self::assertSame([
            'Target platform PHP 8.0.2 does not satisfy root constraint `^7.4`.',
        ], LaravelTransitionFixtureRunner::candidateLockViolations([
            'require' => [
                'php' => '^7.4',
                'laravel/framework' => '^9.0',
            ],
            'config' => [
                'platform' => ['php' => '8.0.2'],
            ],
        ], [
            'packages' => [
                ['name' => 'laravel/framework', 'version' => 'v9.0.0'],
            ],
            'packages-dev' => [],
        ]));
    }

    public function testEveryAdjacentAcceptanceFixtureUsesTheRealOfflineComposerSolver(): void
    {
        foreach ($this->cases() as $case) {
            if (!isset($case['fixture_role'])) {
                continue;
            }

            $manifest = file_get_contents($this->fixturePath($case['fixture']) . '/composer.json');
            self::assertIsString($manifest);
            $decoded = json_decode($manifest, true, 512, JSON_THROW_ON_ERROR);
            self::assertIsArray($decoded);
            self::assertSame(
                'real',
                $decoded['extra']['php-upgrade-preflight-composer-mode'] ?? null,
                $case['name']
            );
        }
    }

    /** @return iterable<string, array{array<string, mixed>}> */
    public function transitionCaseProvider(): iterable
    {
        foreach ($this->cases() as $case) {
            yield $case['name'] => [$case];
        }
    }

    /** @return list<array<string, mixed>> */
    private function cases(): array
    {
        $contents = file_get_contents(dirname(__DIR__, 4) . '/tests/fixtures/contracts/laravel-v0.2-transition-cases.json');
        self::assertIsString($contents);
        $contract = json_decode($contents, true, 512, JSON_THROW_ON_ERROR);
        self::assertIsArray($contract);
        self::assertIsArray($contract['cases']);

        return $contract['cases'];
    }

    /** @return array<string, mixed> */
    private function caseNamed(string $name): array
    {
        foreach ($this->cases() as $case) {
            if ($case['name'] === $name) {
                return $case;
            }
        }

        throw new \LogicException(sprintf('Missing transition fixture case: %s.', $name));
    }

    /** @return list<array{int, int, string}> */
    private function hopTriples(FrameworkGuidance $guidance): array
    {
        return array_map(
            static fn ($hop): array => [$hop->fromMajor(), $hop->toMajor(), $hop->status()],
            $guidance->hops()
        );
    }

    /** @param list<string> $haystack */
    private function contains(array $haystack, string $needle): bool
    {
        foreach ($haystack as $item) {
            if (stripos($item, $needle) !== false) {
                return true;
            }
        }

        return false;
    }

    private function fixturePath(string $fixture): string
    {
        return dirname(__DIR__, 4) . '/tests/fixtures/projects/' . $fixture;
    }
}
