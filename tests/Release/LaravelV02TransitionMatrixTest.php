<?php

declare(strict_types=1);

namespace PhpUpgradePreflight\Tests\Release;

use PhpUpgradePreflight\Laravel\Catalog\LaravelRuleCatalog;
use PHPUnit\Framework\TestCase;

final class LaravelV02TransitionMatrixTest extends TestCase
{
    /** @var array<string, mixed> */
    private array $matrix;

    protected function setUp(): void
    {
        parent::setUp();

        $path = dirname(__DIR__, 2) . '/tests/fixtures/contracts/laravel-v0.2-transition-matrix.json';
        $contents = file_get_contents($path);
        self::assertIsString($contents);
        $matrix = json_decode($contents, true, 512, JSON_THROW_ON_ERROR);
        self::assertIsArray($matrix);
        $this->matrix = $matrix;
    }

    public function testItApprovesOneGaplessAdjacentMatrixThroughLaravel13(): void
    {
        $transitions = $this->matrix['in_scope_adjacent_transitions'];
        self::assertIsArray($transitions);
        $actual = [];

        foreach ($transitions as $transition) {
            self::assertIsArray($transition);
            self::assertIsInt($transition['from']);
            self::assertIsInt($transition['to']);
            self::assertSame($transition['from'] + 1, $transition['to']);
            self::assertSame($transition['to'], $transition['evidence_target']);
            $actual[] = [
                'from' => $transition['from'],
                'to' => $transition['to'],
                'target_constraint' => $transition['target_constraint'],
                'minimum_target_php' => $transition['minimum_target_php'],
            ];
        }

        self::assertSame([
            ['from' => 8, 'to' => 9, 'target_constraint' => '^9.0', 'minimum_target_php' => '8.0.2'],
            ['from' => 9, 'to' => 10, 'target_constraint' => '^10.0', 'minimum_target_php' => '8.1.0'],
            ['from' => 10, 'to' => 11, 'target_constraint' => '^11.0', 'minimum_target_php' => '8.2.0'],
            ['from' => 11, 'to' => 12, 'target_constraint' => '^12.0', 'minimum_target_php' => '8.2.0'],
            ['from' => 12, 'to' => 13, 'target_constraint' => '^13.0', 'minimum_target_php' => '8.3.0'],
        ], $actual);

        self::assertIsArray($this->matrix['decision']);
        self::assertSame('included', $this->matrix['decision']['laravel_13']);
        self::assertSame(
            '^8.0|^9.0|^10.0|^11.0|^12.0|^13.0',
            $this->matrix['decision']['planned_adapter_illuminate_range']
        );
    }

    public function testItKeepsTheV01DirectTransitionsSeparateFromNewAdjacentRulePacks(): void
    {
        self::assertSame([
            ['from' => 7, 'to' => 8, 'kind' => 'adjacent'],
            ['from' => 7, 'to' => 9, 'kind' => 'legacy_direct'],
        ], $this->matrix['retained_v0_1_transitions']);
    }

    public function testEveryTargetUsesCommitPinnedOfficialGuidesAndExactManifests(): void
    {
        $targets = $this->matrix['reviewed_targets'];
        self::assertIsArray($targets);
        $expected = [
            9 => ['php' => '^8.0.2', 'framework' => '^9.19', 'guide' => '^9.0'],
            10 => ['php' => '^8.1', 'framework' => '^10.10', 'guide' => '^10.0'],
            11 => ['php' => '^8.2', 'framework' => '^11.31', 'guide' => '^11.0'],
            12 => ['php' => '^8.2', 'framework' => '^12.0', 'guide' => '^12.0'],
            13 => ['php' => '^8.3', 'framework' => '^13.17', 'guide' => '^13.0'],
        ];
        $actualMajors = [];

        foreach ($targets as $target) {
            self::assertIsArray($target);
            self::assertIsInt($target['major']);
            $major = $target['major'];
            $actualMajors[] = $major;
            self::assertArrayHasKey($major, $expected);
            self::assertIsArray($target['guide']);
            self::assertIsArray($target['framework_manifest']);
            self::assertIsArray($target['application_manifest']);

            $this->assertPinnedSource($target['guide']);
            $this->assertPinnedSource($target['framework_manifest']);
            $this->assertPinnedSource($target['application_manifest']);

            self::assertIsArray($target['guide']['dependencies']);
            self::assertIsArray($target['framework_manifest']['require']);
            self::assertIsArray($target['application_manifest']['require']);
            self::assertSame($expected[$major]['guide'], $target['guide']['dependencies']['laravel/framework']);
            self::assertSame($expected[$major]['php'], $target['framework_manifest']['require']['php']);
            self::assertSame($expected[$major]['php'], $target['application_manifest']['require']['php']);
            self::assertSame(
                $expected[$major]['framework'],
                $target['application_manifest']['require']['laravel/framework']
            );
        }

        self::assertSame(array_keys($expected), $actualMajors);
    }

    public function testLaravel13DecisionLocksItsRuntimeAndTestToolEvidence(): void
    {
        $targets = $this->matrix['reviewed_targets'];
        self::assertIsArray($targets);
        $laravel13 = null;
        foreach ($targets as $target) {
            if (is_array($target) && ($target['major'] ?? null) === 13) {
                $laravel13 = $target;
            }
        }

        self::assertIsArray($laravel13);
        self::assertSame('^13.0', $laravel13['guide']['dependencies']['laravel/framework']);
        self::assertSame('^12.0', $laravel13['guide']['dependencies']['phpunit/phpunit']);
        self::assertSame('^4.0', $laravel13['guide']['dependencies']['pestphp/pest']);
        self::assertSame('^8.3', $laravel13['framework_manifest']['require']['php']);
        self::assertSame('^8.3', $laravel13['application_manifest']['require']['php']);
        self::assertSame('^12.5.12', $laravel13['application_manifest']['require-dev']['phpunit/phpunit']);
    }

    public function testCatalogUsesThePinnedComponentSpecificSymfonyConstraints(): void
    {
        $catalog = LaravelRuleCatalog::v0_2();
        foreach ($this->matrix['reviewed_targets'] as $target) {
            self::assertIsArray($target);
            $major = $target['major'];
            if ($major < 10) {
                continue;
            }

            $definition = $catalog->target($major);
            self::assertNotNull($definition);
            $requirements = array_merge(
                $target['framework_manifest']['require'],
                $target['framework_manifest']['require-dev']
            );
            foreach ($requirements as $package => $constraint) {
                if (!str_starts_with($package, 'symfony/')) {
                    continue;
                }

                self::assertSame(
                    str_replace(' || ', '|', $constraint),
                    $definition->symfonyConstraintFor($package),
                    sprintf('Laravel %d %s', $major, $package)
                );
            }
        }
    }

    public function testLaravel13HostInstallabilityIsDeclaredAndGatedAtNormalAndLowestResolution(): void
    {
        $root = dirname(__DIR__, 2);
        $contents = file_get_contents($root . '/packages/laravel/composer.json');
        self::assertIsString($contents);
        $manifest = json_decode($contents, true, 512, JSON_THROW_ON_ERROR);
        self::assertIsArray($manifest);
        $range = '^8.0|^9.0|^10.0|^11.0|^12.0|^13.0';
        self::assertSame($range, $manifest['require']['illuminate/console']);
        self::assertSame($range, $manifest['require']['illuminate/support']);

        $workflow = file_get_contents($root . '/.github/workflows/compatibility.yml');
        self::assertIsString($workflow);
        self::assertStringContainsString('- normal', $workflow);
        self::assertStringContainsString('- lowest', $workflow);
        self::assertStringContainsString('name: Laravel 13 / PHP 8.3', $workflow);
        self::assertStringContainsString('framework: laravel/framework:^13.0', $workflow);
    }

    public function testEveryApprovedAdjacentPathHasFullAnalyzerFeasibleAndAdvisoryOrBlockedCases(): void
    {
        $contents = file_get_contents(
            dirname(__DIR__, 2) . '/tests/fixtures/contracts/laravel-v0.2-transition-cases.json'
        );
        self::assertIsString($contents);
        $contract = json_decode($contents, true, 512, JSON_THROW_ON_ERROR);
        self::assertIsArray($contract);
        self::assertIsArray($contract['cases']);

        $rolesByTransition = [];
        foreach ($contract['cases'] as $case) {
            self::assertIsArray($case);
            if (!is_int($case['source_major'] ?? null)
                || !is_int($case['target_major'] ?? null)
                || $case['target_major'] !== $case['source_major'] + 1
                || !is_string($case['fixture_role'] ?? null)) {
                continue;
            }

            $key = $case['source_major'] . '-' . $case['target_major'];
            $rolesByTransition[$key][] = $case['fixture_role'];
        }

        foreach (['8-9', '9-10', '10-11', '11-12', '12-13'] as $transition) {
            self::assertContains('feasible', $rolesByTransition[$transition] ?? [], $transition);
            self::assertNotSame(
                [],
                array_intersect(['advisory_heavy', 'blocked'], $rolesByTransition[$transition] ?? []),
                $transition
            );
        }
    }

    /** @param array<string, mixed> $source */
    private function assertPinnedSource(array $source): void
    {
        self::assertIsString($source['source']);
        self::assertIsString($source['commit']);
        self::assertMatchesRegularExpression('/^[0-9a-f]{40}$/', $source['commit']);
        self::assertStringContainsString($source['commit'], $source['source']);
        self::assertStringStartsWith('https://github.com/laravel/', $source['source']);
    }
}
