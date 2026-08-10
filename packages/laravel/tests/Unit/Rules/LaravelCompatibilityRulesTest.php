<?php

declare(strict_types=1);

namespace PhpUpgradePreflight\Laravel\Tests\Unit\Rules;

use PhpUpgradePreflight\Core\Analysis\FrameworkRuleEngine;
use PhpUpgradePreflight\Core\Model\ComposerJson;
use PhpUpgradePreflight\Core\Model\ComposerLock;
use PhpUpgradePreflight\Core\Model\Evidence;
use PhpUpgradePreflight\Core\Model\EvidenceLedger;
use PhpUpgradePreflight\Core\Model\ExtensionAssumption;
use PhpUpgradePreflight\Core\Model\ProjectState;
use PhpUpgradePreflight\Core\Model\SourceUsage;
use PhpUpgradePreflight\Core\Model\UpgradeRequest;
use PhpUpgradePreflight\Core\Model\UpgradeTarget;
use PhpUpgradePreflight\Laravel\LaravelFrameworkIntegration;
use PHPUnit\Framework\TestCase;

final class LaravelCompatibilityRulesTest extends TestCase
{
    public function testLaravel9RulesMapFrameworkPhpAndPackageRangesWithSemver(): void
    {
        $project = $this->project(
            [
                'php' => '^7.4',
                'laravel/framework' => '^7.0',
                'laravel/passport' => '^9.0',
                'laravel/sanctum' => '^2.0',
                'laravel/horizon' => '^4.0',
                'laravel/telescope' => '^3.0',
                'symfony/http-foundation' => '^5.0',
            ],
            [
                $this->package('laravel/framework', 'v7.30.7'),
                $this->package('laravel/passport', 'v9.3.2', ['illuminate/support' => '^6.0|^7.0']),
                $this->package('laravel/sanctum', 'v2.15.1', ['illuminate/support' => '^6.9|^7.0|^8.0|^9.0']),
                $this->package('laravel/horizon', 'v4.3.5', ['illuminate/support' => '^7.0|^8.0']),
                $this->package('laravel/telescope', 'v3.7.0', ['laravel/framework' => '^6.0|^7.0']),
            ]
        );
        $evidence = new EvidenceLedger();

        $findings = $this->evaluate($project, $this->request('^9.0', '8.0.1'), $evidence);
        $summaries = array_map(static fn ($finding): string => $finding->summary(), $findings);

        self::assertTrue($this->contains($summaries, 'root laravel/framework constraint'));
        self::assertTrue($this->contains($summaries, 'does not satisfy Laravel 9'));
        self::assertTrue($this->contains($summaries, 'laravel/passport'));
        self::assertFalse($this->contains($summaries, 'laravel/sanctum'));
        self::assertTrue($this->contains($summaries, 'laravel/horizon'));
        self::assertTrue($this->contains($summaries, 'laravel/telescope'));
        self::assertTrue($this->contains($summaries, 'Symfony component constraints'));

        $classes = array_map(static fn (Evidence $item): string => $item->evidenceClass(), $evidence->all());
        self::assertContains(Evidence::E2_PACKAGE_METADATA, $classes);
        self::assertContains(Evidence::E4_MAINTAINER_DOCUMENTATION, $classes);
    }

    public function testPackageMatrixFlagsOutdatedTestAndLegacyPackagesForLaravel8(): void
    {
        $project = $this->project(
            [
                'laravel/framework' => '^7.0',
                'facade/ignition' => '^1.0',
                'fruitcake/laravel-cors' => '^1.0',
                'laravel/ui' => '^2.0',
                'mockery/mockery' => '^1.3',
                'nunomaduro/collision' => '^4.0',
                'orchestra/testbench' => '^5.0',
                'phpunit/phpunit' => '^8.0',
            ],
            [
                $this->package('laravel/framework', 'v7.30.7'),
                $this->package('facade/ignition', 'v1.16.4'),
                $this->package('fruitcake/laravel-cors', 'v1.0.6'),
                $this->package('laravel/ui', 'v2.5.0'),
                $this->package('mockery/mockery', '1.3.6'),
                $this->package('nunomaduro/collision', 'v4.3.0'),
                $this->package('orchestra/testbench', 'v5.4.0'),
                $this->package('phpunit/phpunit', '8.5.21'),
            ]
        );

        $summaries = array_map(
            static fn ($finding): string => $finding->summary(),
            $this->evaluate($project, $this->request('^8.0', '8.0'), new EvidenceLedger())
        );

        foreach (['facade/ignition', 'fruitcake/laravel-cors', 'laravel/ui', 'mockery/mockery', 'nunomaduro/collision', 'orchestra/testbench', 'phpunit/phpunit'] as $package) {
            self::assertTrue($this->contains($summaries, $package), $package . ' should produce a finding.');
        }
    }

    public function testItFindsTransitiveConsumersPinnedToOldIlluminateSupport(): void
    {
        $project = $this->project(
            ['laravel/framework' => '^7.0'],
            [
                $this->package('laravel/framework', 'v7.30.7'),
                $this->package('legacy/package', '1.0.0', ['illuminate/support' => '^7.0']),
            ]
        );
        $evidence = new EvidenceLedger();

        $findings = $this->evaluate($project, $this->request('^9.0', '8.1'), $evidence);
        $summaries = array_map(static fn ($finding): string => $finding->summary(), $findings);

        self::assertTrue($this->contains($summaries, 'legacy/package'));
        $consumerEvidence = array_values(array_filter(
            $evidence->all(),
            static fn (Evidence $item): bool => ($item->context()['package'] ?? null) === 'legacy/package'
        ));
        self::assertCount(1, $consumerEvidence);
        self::assertSame('^7.0', $consumerEvidence[0]->context()['illuminate_support_constraint']);
    }

    public function testMultiMajorIlluminateConsumerIsAttributedToTheFirstIncompatibleHop(): void
    {
        $project = $this->project(
            ['laravel/framework' => '^10.0'],
            [
                $this->package('laravel/framework', 'v10.48.0'),
                $this->package('legacy/package', '1.0.0', ['illuminate/support' => '^10.0']),
            ]
        );

        $findings = $this->evaluate(
            $project,
            $this->request('^13.0', '8.3'),
            new EvidenceLedger(),
            [],
            true
        );
        $legacy = array_values(array_filter(
            $findings,
            static fn ($finding): bool => stripos($finding->summary(), 'legacy/package') !== false
        ));

        self::assertNotSame([], $legacy);
        self::assertSame(
            [['from_major' => 10, 'to_major' => 11]],
            $legacy[0]->appliesToHops()
        );
        self::assertStringContainsString('Laravel 11', $legacy[0]->summary());
    }

    public function testMultiMajorIlluminateConsumerRemainsOnTheFirstHopItsRangeExcludes(): void
    {
        $project = $this->project(
            ['laravel/framework' => '^10.0'],
            [
                $this->package('laravel/framework', 'v10.48.0'),
                $this->package('legacy/package', '1.0.0', ['illuminate/support' => '^10.0|^11.0']),
            ]
        );

        $legacy = array_values(array_filter(
            $this->evaluate(
                $project,
                $this->request('^13.0', '8.3'),
                new EvidenceLedger(),
                [],
                true
            ),
            static fn ($finding): bool => stripos($finding->summary(), 'legacy/package') !== false
        ));

        self::assertNotSame([], $legacy);
        self::assertSame(
            [['from_major' => 11, 'to_major' => 12]],
            $legacy[0]->appliesToHops()
        );
        self::assertStringContainsString('Laravel 12', $legacy[0]->summary());
    }

    public function testSkeletonReviewUsesExactSourceEvidenceAndSeparateHeuristicGuidance(): void
    {
        $project = $this->project(
            ['laravel/framework' => '^7.0'],
            [$this->package('laravel/framework', 'v7.30.7')]
        );
        $evidence = new EvidenceLedger([
            new Evidence('source-kernel-1', Evidence::E3_PROJECT_SOURCE, 'Kernel middleware.', 'high', [
                'file' => 'app/Http/Kernel.php',
                'line' => 12,
                'usage_type' => 'middleware_reference',
            ]),
            new Evidence('source-alias-1', Evidence::E3_PROJECT_SOURCE, 'Facade alias.', 'high', [
                'file' => 'config/app.php',
                'line' => 20,
                'usage_type' => 'facade_alias',
            ]),
            new Evidence('source-trust-proxies-1', Evidence::E3_PROJECT_SOURCE, 'TrustProxies inheritance.', 'high', [
                'file' => 'app/Http/Middleware/TrustProxies.php',
                'line' => 8,
                'usage_type' => 'inheritance',
            ]),
        ]);
        $usages = [
            new SourceUsage('app/Http/Kernel.php', 'Fruitcake\Cors\HandleCors', 'middleware_reference', ['source-kernel-1'], 12),
            new SourceUsage('config/app.php', 'Vendor\Package\Facade', 'facade_alias', ['source-alias-1'], 20),
            new SourceUsage('app/Http/Middleware/TrustProxies.php', 'Fideloper\Proxy\TrustProxies', 'inheritance', ['source-trust-proxies-1'], 8),
        ];

        $findings = $this->evaluate($project, $this->request('^9.0', '8.1'), $evidence, $usages);
        $skeleton = array_values(array_filter(
            $findings,
            static fn ($finding): bool => strpos($finding->summary(), 'review locations') !== false
        ));

        self::assertCount(1, $skeleton);
        self::assertContains('source-kernel-1', $skeleton[0]->evidence());
        self::assertContains('source-alias-1', $skeleton[0]->evidence());
        self::assertContains('source-trust-proxies-1', $skeleton[0]->evidence());

        $heuristics = array_values(array_filter(
            $evidence->all(),
            static fn (Evidence $item): bool => $item->evidenceClass() === Evidence::E5_HEURISTIC
        ));
        self::assertCount(1, $heuristics);
        self::assertSame('review_location_only', $heuristics[0]->context()['claim']);
    }

    public function testSkeletonReviewIgnoresLegacySymbolsOutsideSkeletonManagedLocations(): void
    {
        $project = $this->project(
            ['laravel/framework' => '^7.0'],
            [$this->package('laravel/framework', 'v7.30.7')]
        );
        $usage = new SourceUsage(
            'app/Services/CorsFactory.php',
            'Fruitcake\\Cors\\HandleCors',
            'instantiation',
            ['source-outside-skeleton-1'],
            12
        );

        $findings = $this->evaluate(
            $project,
            $this->request('^9.0', '8.1'),
            new EvidenceLedger(),
            [$usage]
        );

        self::assertSame([], array_values(array_filter(
            $findings,
            static fn ($finding): bool => strpos($finding->summary(), 'review locations') !== false
        )));
    }

    /** @dataProvider ambiguousLaravelTargetProvider */
    public function testAmbiguousLaravelTargetDoesNotProduceRangeClaims(string $constraint): void
    {
        $project = $this->project(
            ['laravel/framework' => '^7.0', 'facade/ignition' => '^1.0'],
            [$this->package('laravel/framework', 'v7.30.7'), $this->package('facade/ignition', '1.0.0')]
        );

        self::assertSame([], $this->evaluate($project, $this->request($constraint), new EvidenceLedger()));
    }

    /** @return iterable<string, array{string}> */
    public function ambiguousLaravelTargetProvider(): iterable
    {
        yield 'Laravel 8 or 9' => ['^8.0|^9.0'];
        yield 'current or Laravel 9' => ['^7.0|^9.0'];
        yield 'Laravel 9 or unsupported Laravel 10' => ['^9.0|^10.0'];
        yield 'unbounded from Laravel 9' => ['>=9.0'];
        yield 'Laravel 8 or unsupported Laravel 10' => ['^8.0|^10.0'];
    }

    public function testRulesRunForAConsoleOnlyIlluminate7Project(): void
    {
        $project = $this->project(
            ['illuminate/console' => '^7.0', 'phpunit/phpunit' => '^8.0'],
            [
                $this->package('illuminate/console', 'v7.30.7'),
                $this->package('phpunit/phpunit', '8.5.21'),
            ]
        );

        $summaries = array_map(
            static fn ($finding): string => $finding->summary(),
            $this->evaluate(
                $project,
                $this->requestFor('illuminate/console', '^8.0', '8.0'),
                new EvidenceLedger()
            )
        );

        self::assertTrue($this->contains($summaries, 'phpunit/phpunit'));
    }

    public function testMultiMajorUpgradeRunsAndScopesEachCoveredHop(): void
    {
        $project = $this->project(
            ['laravel/framework' => '^7.0', 'facade/ignition' => '^1.0', 'phpunit/phpunit' => '^8.0'],
            [
                $this->package('laravel/framework', 'v7.30.7'),
                $this->package('facade/ignition', '1.16.4'),
                $this->package('phpunit/phpunit', '8.5.21'),
            ]
        );

        $findings = $this->evaluate(
            $project,
            $this->request('^10.0', '8.1'),
            new EvidenceLedger(),
            [],
            true,
            '2.8.12'
        );

        $framework = array_values(array_filter(
            $findings,
            static fn ($finding): bool => strpos($finding->summary(), 'root laravel/framework constraint') !== false
        ));
        self::assertCount(1, $framework);
        self::assertSame([['from_major' => 9, 'to_major' => 10]], $framework[0]->appliesToHops());

        $phpunitHops = [];
        foreach ($findings as $finding) {
            if (strpos($finding->summary(), 'phpunit/phpunit') !== false) {
                $phpunitHops[] = $finding->appliesToHops()[0];
            }
        }
        self::assertSame([
            ['from_major' => 7, 'to_major' => 8],
            ['from_major' => 8, 'to_major' => 9],
            ['from_major' => 9, 'to_major' => 10],
        ], $phpunitHops);

        $ignitionHops = [];
        foreach ($findings as $finding) {
            if (strpos($finding->summary(), 'facade/ignition') !== false) {
                $ignitionHops[] = $finding->appliesToHops()[0];
            }
        }
        self::assertSame([
            ['from_major' => 7, 'to_major' => 8],
            ['from_major' => 8, 'to_major' => 9],
        ], $ignitionHops);
    }

    public function testComposerRuleUsesRuntimeVersionInsteadOfLockGenerationMetadata(): void
    {
        $oldLockNewRuntime = new ProjectState(
            __DIR__,
            new ComposerJson(['require' => ['laravel/framework' => '^9.0']]),
            new ComposerLock([
                'plugin-api-version' => '2.1.0',
                'packages' => [$this->package('laravel/framework', 'v9.52.16')],
            ])
        );
        $newLockOldRuntime = new ProjectState(
            __DIR__,
            new ComposerJson(['require' => ['laravel/framework' => '^9.0']]),
            new ComposerLock([
                'plugin-api-version' => '2.8.0',
                'packages' => [$this->package('laravel/framework', 'v9.52.16')],
            ])
        );
        $request = $this->request('^10.0', '8.1');
        $oldRuntimeEvidence = new EvidenceLedger();

        $newRuntimeSummaries = array_map(
            static fn ($finding): string => $finding->summary(),
            $this->evaluate($oldLockNewRuntime, $request, new EvidenceLedger(), [], true, '2.8.12')
        );
        $oldRuntimeSummaries = array_map(
            static fn ($finding): string => $finding->summary(),
            $this->evaluate($newLockOldRuntime, $request, $oldRuntimeEvidence, [], true, '2.1.14')
        );
        $unknownRuntimeSummaries = array_map(
            static fn ($finding): string => $finding->summary(),
            $this->evaluate($newLockOldRuntime, $request, new EvidenceLedger(), [], true)
        );

        self::assertFalse($this->contains($newRuntimeSummaries, 'Composer `'));
        self::assertTrue($this->contains($oldRuntimeSummaries, 'Composer `2.1.14`'));
        self::assertFalse($this->contains($unknownRuntimeSummaries, 'Composer `'));
        $runtimeEvidence = array_values(array_filter(
            $oldRuntimeEvidence->all(),
            static fn (Evidence $item): bool => isset($item->context()['composer_version'])
        ));
        self::assertCount(1, $runtimeEvidence);
        self::assertSame(Evidence::E1_SOLVER, $runtimeEvidence[0]->evidenceClass());
        self::assertSame('2.1.14', $runtimeEvidence[0]->context()['composer_version']);
    }

    public function testCurlRuleDistinguishesAbsentPresentUnknownAndRequestOverride(): void
    {
        $requirements = ['laravel/framework' => '^10.0'];
        $packages = [$this->package('laravel/framework', 'v10.48.0')];
        $absent = new ProjectState(__DIR__, new ComposerJson([
            'require' => $requirements,
            'config' => ['platform' => ['ext-curl' => false]],
        ]), new ComposerLock(['packages' => $packages]));
        $present = new ProjectState(__DIR__, new ComposerJson([
            'require' => $requirements,
            'config' => ['platform' => ['ext-curl' => '8.2.0']],
        ]), new ComposerLock(['packages' => $packages]));
        $unknown = $this->project($requirements, $packages);
        $request = $this->request('^11.0', '8.2');
        $overrideRequest = new UpgradeRequest(
            __DIR__,
            [new UpgradeTarget('laravel/framework', '^11.0')],
            null,
            '8.2',
            [],
            [],
            'json',
            null,
            false,
            [ExtensionAssumption::fromPresenceInput('ext-curl')]
        );

        self::assertTrue($this->contains($this->summaries($this->evaluate($absent, $request, new EvidenceLedger(), [], true)), 'curl is `absent`'));
        self::assertFalse($this->contains($this->summaries($this->evaluate($present, $request, new EvidenceLedger(), [], true)), 'curl is `absent`'));
        self::assertFalse($this->contains($this->summaries($this->evaluate($unknown, $request, new EvidenceLedger(), [], true)), 'curl is `absent`'));
        self::assertFalse($this->contains($this->summaries($this->evaluate($absent, $overrideRequest, new EvidenceLedger(), [], true)), 'curl is `absent`'));
    }

    public function testLaravel9PackageGuidanceUsesLaravel9Sources(): void
    {
        $project = $this->project(
            [
                'laravel/framework' => '^7.0',
                'nunomaduro/collision' => '^5.0',
                'phpunit/phpunit' => '^8.0',
            ],
            [
                $this->package('laravel/framework', 'v7.30.7'),
                $this->package('nunomaduro/collision', 'v5.10.0'),
                $this->package('phpunit/phpunit', '8.5.21'),
            ]
        );
        $evidence = new EvidenceLedger();

        $this->evaluate($project, $this->request('^9.0', '8.1'), $evidence);

        $sourcesByPackage = [];
        foreach ($evidence->all() as $item) {
            $context = $item->context();
            if ($item->evidenceClass() !== Evidence::E4_MAINTAINER_DOCUMENTATION
                || !isset($context['package'], $context['sources'])
                || !is_string($context['package'])
                || !is_array($context['sources'])) {
                continue;
            }

            $sourcesByPackage[$context['package']] = $context['sources'];
        }

        self::assertSame(
            ['https://github.com/laravel/laravel/blob/9.x/composer.json'],
            $sourcesByPackage['phpunit/phpunit']
        );
        self::assertSame(
            ['https://laravel.com/docs/9.x/upgrade'],
            $sourcesByPackage['nunomaduro/collision']
        );
    }

    public function testLaravel9TargetedLegacyAdvisoriesAreEmitted(): void
    {
        $project = $this->project(
            [
                'laravel/framework' => '^7.0',
                'facade/ignition' => '^2.0',
                'fideloper/proxy' => '^4.0',
                'fruitcake/laravel-cors' => '^2.0',
            ],
            [
                $this->package('laravel/framework', 'v7.30.7'),
                $this->package('facade/ignition', '2.17.7'),
                $this->package('fideloper/proxy', '4.4.1'),
                $this->package('fruitcake/laravel-cors', '2.2.0'),
            ]
        );

        $summaries = array_map(
            static fn ($finding): string => $finding->summary(),
            $this->evaluate($project, $this->request('^9.0', '8.1'), new EvidenceLedger())
        );

        self::assertTrue($this->contains($summaries, 'Replace facade/ignition'));
        self::assertTrue($this->contains($summaries, 'Remove fideloper/proxy'));
        self::assertTrue($this->contains($summaries, 'removal of fruitcake/laravel-cors'));
    }

    public function testDirectRootIlluminateSupportMismatchIsReported(): void
    {
        $project = $this->project(
            ['illuminate/support' => '^7.0'],
            [$this->package('illuminate/support', 'v7.30.7')]
        );

        $summaries = array_map(
            static fn ($finding): string => $finding->summary(),
            $this->evaluate(
                $project,
                $this->requestFor('illuminate/support', '^8.0'),
                new EvidenceLedger()
            )
        );

        self::assertTrue($this->contains($summaries, 'incompatible illuminate/support constraints'));
    }

    public function testCompatibleLaravel8PackageAndSymfonyConstraintsProduceNoPackageFindings(): void
    {
        $project = $this->project(
            [
                'laravel/framework' => '^7.0',
                'mockery/mockery' => '^1.4',
                'nunomaduro/collision' => '^5.0',
                'phpunit/phpunit' => '^9.0',
                'symfony/http-foundation' => '^5.4',
            ],
            [
                $this->package('laravel/framework', 'v7.30.7'),
                $this->package('mockery/mockery', '1.4.4'),
                $this->package('nunomaduro/collision', 'v5.10.0'),
                $this->package('phpunit/phpunit', '9.5.10'),
                $this->package('symfony/http-foundation', 'v5.4.45'),
            ]
        );

        $summaries = array_map(
            static fn ($finding): string => $finding->summary(),
            $this->evaluate($project, $this->request('^8.0', '8.0'), new EvidenceLedger())
        );

        foreach (['mockery/mockery', 'nunomaduro/collision', 'phpunit/phpunit', 'Symfony component constraints'] as $unexpected) {
            self::assertFalse($this->contains($summaries, $unexpected), $unexpected . ' should not produce a finding.');
        }
    }

    public function testExactLaravelTargetDoesNotWidenLockedPackageRequirementsToTheWholeMajor(): void
    {
        $project = $this->project(
            ['laravel/framework' => '^7.0', 'laravel/passport' => '^10.0'],
            [
                $this->package('laravel/framework', 'v7.30.7'),
                $this->package('laravel/passport', 'v10.0.0', ['illuminate/support' => '^8.37']),
            ]
        );

        $summaries = array_map(
            static fn ($finding): string => $finding->summary(),
            $this->evaluate($project, $this->request('8.0.0', '8.0'), new EvidenceLedger())
        );

        self::assertTrue($this->contains($summaries, 'laravel/passport'));
        self::assertTrue($this->contains($summaries, 'framework constraints that exclude Laravel 8'));
    }

    public function testLaravel13SymfonyGuidanceUsesExactComponentSpecificMinimums(): void
    {
        $project = $this->project(
            [
                'laravel/framework' => '^12.0',
                'symfony/console' => '7.4.0',
                'symfony/http-foundation' => '7.4.0',
                'symfony/process' => '7.4.4',
            ],
            [
                $this->package('laravel/framework', 'v12.20.0'),
                $this->package('symfony/console', 'v7.4.0'),
                $this->package('symfony/http-foundation', 'v7.4.0'),
                $this->package('symfony/process', 'v7.4.4'),
            ]
        );

        $summaries = $this->summaries($this->evaluate(
            $project,
            $this->request('^13.0', '8.3'),
            new EvidenceLedger(),
            [],
            true
        ));
        $symfony = array_values(array_filter(
            $summaries,
            static fn (string $summary): bool => strpos($summary, 'direct Symfony component constraints') !== false
        ));

        self::assertCount(1, $symfony);
        self::assertStringContainsString('symfony/http-foundation (`^7.4.13|^8.0.13`)', $symfony[0]);
        self::assertStringContainsString('symfony/process (`^7.4.5|^8.0.5`)', $symfony[0]);
        self::assertStringNotContainsString('symfony/console', $symfony[0]);
    }

    /**
     * @param array<string, string> $requirements
     * @param list<array<string, mixed>> $packages
     */
    private function project(array $requirements, array $packages): ProjectState
    {
        return new ProjectState(
            __DIR__,
            new ComposerJson(['require' => $requirements]),
            new ComposerLock(['packages' => $packages])
        );
    }

    /** @param array<string, string> $requirements @return array<string, mixed> */
    private function package(string $name, string $version, array $requirements = []): array
    {
        $package = ['name' => $name, 'version' => $version];
        if ($requirements !== []) {
            $package['require'] = $requirements;
        }

        return $package;
    }

    private function request(string $laravelConstraint, ?string $php = null): UpgradeRequest
    {
        return $this->requestFor('laravel/framework', $laravelConstraint, $php);
    }

    private function requestFor(string $package, string $constraint, ?string $php = null): UpgradeRequest
    {
        return new UpgradeRequest(
            __DIR__,
            [new UpgradeTarget($package, $constraint)],
            null,
            $php
        );
    }

    /**
     * @param list<SourceUsage> $sourceUsages
     * @return list<\PhpUpgradePreflight\Core\Model\CompatibilityFinding>
     */
    private function evaluate(
        ProjectState $project,
        UpgradeRequest $request,
        EvidenceLedger $evidence,
        array $sourceUsages = [],
        bool $withGuidance = false,
        ?string $composerVersion = null
    ): array {
        $integration = new LaravelFrameworkIntegration();
        $engine = new FrameworkRuleEngine([$integration]);
        $guidance = $withGuidance
            ? $engine->assessTransitions([$integration], $project, $request, $evidence)
            : [];

        return $engine->evaluate(
            [$integration],
            $project,
            $request,
            $evidence,
            $sourceUsages,
            $guidance,
            $composerVersion
        );
    }

    /** @return list<string> */
    private function summaries(array $findings): array
    {
        return array_map(static fn ($finding): string => $finding->summary(), $findings);
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
}
