<?php

declare(strict_types=1);

namespace PhpUpgradePreflight\Laravel\Tests\Unit;

use PhpUpgradePreflight\Core\Model\ComposerJson;
use PhpUpgradePreflight\Core\Model\ComposerLock;
use PhpUpgradePreflight\Core\Model\EvidenceLedger;
use PhpUpgradePreflight\Core\Model\FrameworkGuidance;
use PhpUpgradePreflight\Core\Model\ProjectState;
use PhpUpgradePreflight\Core\Model\UpgradeRequest;
use PhpUpgradePreflight\Core\Model\UpgradeTarget;
use PhpUpgradePreflight\Laravel\Catalog\LaravelRuleCatalog;
use PhpUpgradePreflight\Laravel\Catalog\TransitionDefinition;
use PhpUpgradePreflight\Laravel\LaravelFrameworkIntegration;
use PHPUnit\Framework\TestCase;

final class LaravelFrameworkIntegrationTest extends TestCase
{
    public function testItDetectsTheLockedLaravelFrameworkVersion(): void
    {
        $project = new ProjectState(
            __DIR__,
            new ComposerJson(['require' => ['laravel/framework' => '^8.0']]),
            new ComposerLock(['packages' => [['name' => 'laravel/framework', 'version' => 'v8.83.27']]])
        );

        $integration = new LaravelFrameworkIntegration();
        $detection = $integration->detect($project);

        self::assertSame('laravel', $integration->name());
        self::assertTrue($detection->isDetected());
        self::assertSame('v8.83.27', $detection->version());
        self::assertCount(59, iterator_to_array($integration->rules()));
        self::assertSame(['src', 'app', 'bootstrap', 'config', 'database', 'routes', 'tests'], $integration->defaultSourcePaths($project));
        self::assertSame(['laravel'], $integration->packageFamilies('laravel/framework'));
    }

    public function testItClassifiesLaravelIlluminateAndSymfonyPackageFamilies(): void
    {
        $integration = new LaravelFrameworkIntegration();

        self::assertSame(['laravel'], $integration->packageFamilies('LARAVEL/Passport'));
        self::assertSame(['illuminate'], $integration->packageFamilies('illuminate/support'));
        self::assertSame(['symfony'], $integration->packageFamilies('symfony/http-foundation'));
        self::assertSame([], $integration->packageFamilies('vendor/package'));
    }

    public function testItReportsLaravelAsAbsentWithoutComposerMetadata(): void
    {
        $project = new ProjectState(__DIR__, new ComposerJson([]), new ComposerLock([]));

        $detection = (new LaravelFrameworkIntegration())->detect($project);

        self::assertFalse($detection->isDetected());
        self::assertNull($detection->version());
    }

    public function testItDetectsLaravelFromARootConstraintWithoutALockedFrameworkPackage(): void
    {
        $project = new ProjectState(
            __DIR__,
            new ComposerJson(['require' => ['Laravel/Framework' => '^7.0']]),
            new ComposerLock([])
        );

        $detection = (new LaravelFrameworkIntegration())->detect($project);

        self::assertTrue($detection->isDetected());
        self::assertSame('^7.0', $detection->version());
    }

    public function testLockedLaravelVersionTakesPrecedenceOverTheRootConstraint(): void
    {
        $project = new ProjectState(
            __DIR__,
            new ComposerJson(['require' => ['laravel/framework' => '^7.0']]),
            new ComposerLock(['packages' => [['name' => 'laravel/framework', 'version' => 'v7.30.7']]])
        );

        $detection = (new LaravelFrameworkIntegration())->detect($project);

        self::assertTrue($detection->isDetected());
        self::assertSame('v7.30.7', $detection->version());
    }

    public function testItDetectsAModularIlluminateProjectFromRootConstraintsAndLockData(): void
    {
        $project = new ProjectState(
            __DIR__,
            new ComposerJson([
                'require' => [
                    'illuminate/console' => '^7.0',
                    'Illuminate/Support' => '^7.0',
                ],
            ]),
            new ComposerLock([
                'packages' => [
                    ['name' => 'illuminate/support', 'version' => 'v7.30.7'],
                    ['name' => 'illuminate/console', 'version' => 'v7.30.7'],
                ],
            ])
        );

        $detection = (new LaravelFrameworkIntegration())->detect($project);

        self::assertTrue($detection->isDetected());
        self::assertSame('v7.30.7', $detection->version());
    }

    public function testItDoesNotTreatTransitiveIlluminatePackagesAsALaravelProject(): void
    {
        $project = new ProjectState(
            __DIR__,
            new ComposerJson(['require' => ['vendor/package' => '^1.0']]),
            new ComposerLock([
                'packages' => [['name' => 'illuminate/support', 'version' => 'v8.83.27']],
            ])
        );

        $detection = (new LaravelFrameworkIntegration())->detect($project);

        self::assertFalse($detection->isDetected());
        self::assertNull($detection->version());
    }

    public function testItDoesNotClaimASingleIlluminateVersionWhenRootedComponentsDisagree(): void
    {
        $project = new ProjectState(
            __DIR__,
            new ComposerJson([
                'require' => [
                    'illuminate/console' => '^7.0',
                    'illuminate/support' => '^8.0',
                ],
            ]),
            new ComposerLock([])
        );

        $detection = (new LaravelFrameworkIntegration())->detect($project);

        self::assertTrue($detection->isDetected());
        self::assertNull($detection->version());
    }

    public function testItUsesACommonIlluminateRootConstraintWithoutLockData(): void
    {
        $project = new ProjectState(
            __DIR__,
            new ComposerJson([
                'require' => [
                    'illuminate/console' => '^7.0',
                    'illuminate/support' => '^7.0',
                ],
            ]),
            new ComposerLock([])
        );

        $detection = (new LaravelFrameworkIntegration())->detect($project);

        self::assertTrue($detection->isDetected());
        self::assertSame('^7.0', $detection->version());
    }

    public function testItDoesNotMixAPartialIlluminateLockWithRootConstraintsIntoASingleVersion(): void
    {
        $project = new ProjectState(
            __DIR__,
            new ComposerJson([
                'require' => [
                    'illuminate/console' => '^7.0',
                    'illuminate/support' => '^7.0',
                ],
            ]),
            new ComposerLock([
                'packages' => [['name' => 'illuminate/support', 'version' => 'v7.30.7']],
            ])
        );

        $detection = (new LaravelFrameworkIntegration())->detect($project);

        self::assertTrue($detection->isDetected());
        self::assertNull($detection->version());
    }

    public function testItPrefersTheCompleteAdjacentPathOverTheRetainedDirectTransition(): void
    {
        $project = new ProjectState(
            __DIR__,
            new ComposerJson(['require' => ['laravel/framework' => '^7.0']]),
            new ComposerLock(['packages' => [['name' => 'laravel/framework', 'version' => 'v7.30.7']]])
        );
        $request = new UpgradeRequest(__DIR__, [new UpgradeTarget('laravel/framework', '^9.0')]);
        $evidence = new EvidenceLedger();

        $guidance = (new LaravelFrameworkIntegration())->assessTransition($project, $request, $evidence);

        self::assertNotNull($guidance);
        self::assertSame(FrameworkGuidance::SUPPORTED, $guidance->toArray()['status']);
        self::assertSame(7, $guidance->sourceMajor());
        self::assertSame(9, $guidance->targetMajor());
        self::assertSame([
            ['from_major' => 7, 'to_major' => 8],
            ['from_major' => 8, 'to_major' => 9],
        ], $guidance->supportedHopReferences());
        self::assertSame('laravel-7-to-8', $guidance->toArray()['hops'][0]['rule_pack']);
        self::assertSame('laravel-8-to-9', $guidance->toArray()['hops'][1]['rule_pack']);
        self::assertCount(2, $evidence->all());
    }

    public function testItRetainsTheDirectLaravel7To9PackAsAFallback(): void
    {
        $catalog = LaravelRuleCatalog::v0_2();
        $transitions = array_map(
            static function (TransitionDefinition $transition): TransitionDefinition {
                if ($transition->key() !== 'adjacent-8-9') {
                    return $transition;
                }

                return new TransitionDefinition(
                    $transition->key(),
                    $transition->sourceMajor(),
                    $transition->targetMajor(),
                    $transition->kind(),
                    null,
                    $transition->sources()
                );
            },
            $catalog->transitions()
        );
        $fallbackCatalog = new LaravelRuleCatalog(
            $catalog->version(),
            $catalog->minimumMajor(),
            $catalog->maximumMajor(),
            $catalog->targets(),
            $transitions,
            $catalog->rules(),
            $catalog->skeletonPatterns()
        );
        $project = new ProjectState(
            __DIR__,
            new ComposerJson(['require' => ['laravel/framework' => '^7.0']]),
            new ComposerLock(['packages' => [['name' => 'laravel/framework', 'version' => 'v7.30.7']]])
        );
        $request = new UpgradeRequest(__DIR__, [new UpgradeTarget('laravel/framework', '^9.0')]);

        $guidance = (new LaravelFrameworkIntegration(null, $fallbackCatalog))->assessTransition(
            $project,
            $request,
            new EvidenceLedger()
        );

        self::assertNotNull($guidance);
        self::assertSame(FrameworkGuidance::SUPPORTED, $guidance->status());
        self::assertSame([['from_major' => 7, 'to_major' => 9]], $guidance->supportedHopReferences());
        self::assertSame('laravel-7-to-9-direct', $guidance->hops()[0]->toArray()['rule_pack']);
    }

    public function testItDoesNotGuessAnAmbiguousSourceMajor(): void
    {
        $project = new ProjectState(
            __DIR__,
            new ComposerJson([
                'require' => [
                    'illuminate/console' => '^7.0',
                    'illuminate/support' => '^8.0',
                ],
            ]),
            new ComposerLock([])
        );
        $request = new UpgradeRequest(__DIR__, [new UpgradeTarget('illuminate/support', '^9.0')]);

        $guidance = (new LaravelFrameworkIntegration())->assessTransition($project, $request, new EvidenceLedger());

        self::assertNotNull($guidance);
        self::assertSame(FrameworkGuidance::UNSUPPORTED, $guidance->toArray()['status']);
        self::assertNull($guidance->sourceMajor());
        self::assertSame([], $guidance->hops());
        self::assertNotSame([], $guidance->toArray()['uncertainties']);
    }

    public function testItResolvesOneSourceMajorAndStopsAtTheUnimplementedLaravel13Hop(): void
    {
        $project = new ProjectState(
            __DIR__,
            new ComposerJson([
                'require' => [
                    'illuminate/console' => '^10.0',
                    'illuminate/support' => '^10.0',
                ],
            ]),
            new ComposerLock([
                'packages' => [
                    ['name' => 'illuminate/console', 'version' => 'v10.48.20'],
                    ['name' => 'illuminate/support', 'version' => 'v10.48.28'],
                ],
            ])
        );
        $request = new UpgradeRequest(__DIR__, [new UpgradeTarget('illuminate/support', '^13.0')]);

        $guidance = (new LaravelFrameworkIntegration())->assessTransition(
            $project,
            $request,
            new EvidenceLedger()
        );

        self::assertNotNull($guidance);
        self::assertSame(10, $guidance->sourceMajor());
        self::assertSame(13, $guidance->targetMajor());
        self::assertSame(FrameworkGuidance::PARTIALLY_SUPPORTED, $guidance->status());
        self::assertSame([
            [10, 11, 'supported'],
            [11, 12, 'supported'],
            [12, 13, 'unsupported'],
        ], array_map(
            static fn ($hop): array => [$hop->fromMajor(), $hop->toMajor(), $hop->status()],
            $guidance->hops()
        ));
        self::assertCount(1, $guidance->toArray()['uncertainties']);
    }

    public function testItReportsInconsistentRootedIlluminateLockedMajorsAsUncertainty(): void
    {
        $project = new ProjectState(
            __DIR__,
            new ComposerJson([
                'require' => [
                    'illuminate/console' => '^10.0',
                    'illuminate/support' => '^11.0',
                ],
            ]),
            new ComposerLock([
                'packages' => [
                    ['name' => 'illuminate/console', 'version' => 'v10.48.20'],
                    ['name' => 'illuminate/support', 'version' => 'v11.44.7'],
                ],
            ])
        );
        $evidence = new EvidenceLedger();
        $request = new UpgradeRequest(__DIR__, [new UpgradeTarget('illuminate/support', '^12.0')]);

        $guidance = (new LaravelFrameworkIntegration())->assessTransition($project, $request, $evidence);

        self::assertNotNull($guidance);
        self::assertNull($guidance->sourceMajor());
        self::assertSame([], $guidance->hops());
        self::assertStringContainsString(
            'inconsistent across majors: 10, 11',
            $guidance->toArray()['uncertainties'][0]
        );
        self::assertSame(
            10,
            $evidence->all()[0]->context()['source_observations']['illuminate/console']['major']
        );
        self::assertSame(
            11,
            $evidence->all()[0]->context()['source_observations']['illuminate/support']['major']
        );
    }

    public function testItResolvesAlignedSingleMajorIlluminateConstraintsWithoutLockData(): void
    {
        $project = new ProjectState(
            __DIR__,
            new ComposerJson([
                'require' => [
                    'illuminate/console' => '^10.0',
                    'illuminate/support' => '>=10.20 <11.0',
                ],
            ]),
            new ComposerLock([])
        );
        $request = new UpgradeRequest(__DIR__, [new UpgradeTarget('illuminate/support', '^11.0')]);

        $guidance = (new LaravelFrameworkIntegration())->assessTransition(
            $project,
            $request,
            new EvidenceLedger()
        );

        self::assertNotNull($guidance);
        self::assertSame(10, $guidance->sourceMajor());
        self::assertSame(11, $guidance->targetMajor());
    }

    /** @dataProvider singleMajorTargetProvider */
    public function testItParsesSingleMajorTargetsBeyondLaravel9(string $constraint, int $expectedMajor): void
    {
        $project = new ProjectState(
            __DIR__,
            new ComposerJson(['require' => ['laravel/framework' => '^9.0']]),
            new ComposerLock(['packages' => [['name' => 'laravel/framework', 'version' => 'v9.52.16']]])
        );
        $request = new UpgradeRequest(__DIR__, [new UpgradeTarget('laravel/framework', $constraint)]);

        $guidance = (new LaravelFrameworkIntegration())->assessTransition(
            $project,
            $request,
            new EvidenceLedger()
        );

        self::assertNotNull($guidance);
        self::assertSame($expectedMajor, $guidance->targetMajor());
    }

    /** @return iterable<string, array{string, int}> */
    public function singleMajorTargetProvider(): iterable
    {
        yield 'caret' => ['^10.0', 10];
        yield 'wildcard' => ['11.*', 11];
        yield 'bounded range' => ['>=12.0 <13.0', 12];
        yield 'exact' => ['13.17.0', 13];
    }

    /** @dataProvider crossMajorTargetProvider */
    public function testItRejectsCrossMajorLaravelTargetConstraints(string $constraint): void
    {
        $project = new ProjectState(
            __DIR__,
            new ComposerJson(['require' => ['laravel/framework' => '^9.0']]),
            new ComposerLock(['packages' => [['name' => 'laravel/framework', 'version' => 'v9.52.16']]])
        );
        $request = new UpgradeRequest(__DIR__, [new UpgradeTarget('laravel/framework', $constraint)]);

        $guidance = (new LaravelFrameworkIntegration())->assessTransition(
            $project,
            $request,
            new EvidenceLedger()
        );

        self::assertNotNull($guidance);
        self::assertNull($guidance->targetMajor());
        self::assertSame(FrameworkGuidance::UNSUPPORTED, $guidance->status());
        self::assertSame([], $guidance->hops());
    }

    /** @return iterable<string, array{string}> */
    public function crossMajorTargetProvider(): iterable
    {
        yield 'union' => ['^10.0|^11.0'];
        yield 'bounded across two majors' => ['>=10.0 <12.0'];
        yield 'unbounded upper range' => ['<11.0'];
        yield 'unbounded lower range' => ['>=10.0'];
    }

    public function testItRejectsConflictingMajorsAcrossLaravelPackageTargets(): void
    {
        $project = new ProjectState(
            __DIR__,
            new ComposerJson(['require' => ['laravel/framework' => '^9.0']]),
            new ComposerLock(['packages' => [['name' => 'laravel/framework', 'version' => 'v9.52.16']]])
        );
        $request = new UpgradeRequest(__DIR__, [
            new UpgradeTarget('laravel/framework', '^10.0'),
            new UpgradeTarget('illuminate/support', '^11.0'),
        ]);
        $evidence = new EvidenceLedger();

        $guidance = (new LaravelFrameworkIntegration())->assessTransition($project, $request, $evidence);

        self::assertNotNull($guidance);
        self::assertSame(9, $guidance->sourceMajor());
        self::assertNull($guidance->targetMajor());
        self::assertSame(FrameworkGuidance::UNSUPPORTED, $guidance->status());
        self::assertSame([], $guidance->hops());
        self::assertSame(
            [
                'illuminate/support' => '^11.0',
                'laravel/framework' => '^10.0',
            ],
            $evidence->all()[0]->context()['target_constraints']
        );
    }

    /** @dataProvider unsupportedResolvedTransitionProvider */
    public function testResolvedNonUpgradeAndCatalogBoundaryTransitionsHaveNoHops(
        string $sourceVersion,
        string $targetConstraint,
        int $expectedSource,
        int $expectedTarget,
        string $expectedUncertainty
    ): void {
        $project = new ProjectState(
            __DIR__,
            new ComposerJson(['require' => ['laravel/framework' => '^' . $expectedSource . '.0']]),
            new ComposerLock(['packages' => [[
                'name' => 'laravel/framework',
                'version' => $sourceVersion,
            ]]])
        );
        $request = new UpgradeRequest(__DIR__, [new UpgradeTarget('laravel/framework', $targetConstraint)]);

        $guidance = (new LaravelFrameworkIntegration())->assessTransition(
            $project,
            $request,
            new EvidenceLedger()
        );

        self::assertNotNull($guidance);
        self::assertSame($expectedSource, $guidance->sourceMajor());
        self::assertSame($expectedTarget, $guidance->targetMajor());
        self::assertSame(FrameworkGuidance::UNSUPPORTED, $guidance->status());
        self::assertSame([], $guidance->hops());
        self::assertStringContainsString($expectedUncertainty, $guidance->toArray()['uncertainties'][0]);
    }

    /** @return iterable<string, array{string, string, int, int, string}> */
    public function unsupportedResolvedTransitionProvider(): iterable
    {
        yield 'same major' => ['v10.48.28', '^10.0', 10, 10, 'not a major-version upgrade'];
        yield 'downgrade' => ['v10.48.28', '^9.0', 10, 9, 'not a major-version upgrade'];
        yield 'target above catalog' => ['v13.17.0', '^14.0', 13, 14, 'outside the modeled Laravel 7 through 13'];
        yield 'source below catalog' => ['v6.20.44', '^7.0', 6, 7, 'outside the modeled Laravel 7 through 13'];
    }

    public function testItModelsAContiguousSupportedAdjacentPathThroughLaravel10(): void
    {
        $project = new ProjectState(
            __DIR__,
            new ComposerJson(['require' => ['laravel/framework' => '^7.0']]),
            new ComposerLock(['packages' => [['name' => 'laravel/framework', 'version' => 'v7.30.7']]])
        );
        $request = new UpgradeRequest(__DIR__, [new UpgradeTarget('laravel/framework', '^10.0')]);

        $guidance = (new LaravelFrameworkIntegration())->assessTransition(
            $project,
            $request,
            new EvidenceLedger()
        );

        self::assertNotNull($guidance);
        self::assertSame(FrameworkGuidance::SUPPORTED, $guidance->status());
        self::assertSame([
            ['from_major' => 7, 'to_major' => 8],
            ['from_major' => 8, 'to_major' => 9],
            ['from_major' => 9, 'to_major' => 10],
        ], $guidance->supportedHopReferences());
        self::assertSame(
            [
                [7, 8, 'supported'],
                [8, 9, 'supported'],
                [9, 10, 'supported'],
            ],
            array_map(
                static fn ($hop): array => [$hop->fromMajor(), $hop->toMajor(), $hop->status()],
                $guidance->hops()
            )
        );
    }
}
