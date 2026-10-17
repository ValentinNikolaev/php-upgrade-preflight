<?php

declare(strict_types=1);

namespace PhpUpgradePreflight\Core\Tests\Unit\Analysis;

use PhpUpgradePreflight\Core\Analysis\BlockerGrouper;
use PhpUpgradePreflight\Core\Analysis\ComposerBlockerParser;
use PhpUpgradePreflight\Core\Analysis\StageBlockerRegistry;
use PhpUpgradePreflight\Core\Model\Blocker;
use PhpUpgradePreflight\Core\Model\BlockerType;
use PhpUpgradePreflight\Core\Model\ComposerDiagnostic;
use PhpUpgradePreflight\Core\Model\EvidenceLedger;
use PhpUpgradePreflight\Core\Model\Scenario;
use PhpUpgradePreflight\Core\Model\ScenarioResult;
use PhpUpgradePreflight\Core\Model\UpgradeTarget;
use PhpUpgradePreflight\Core\Model\UpgradeTargetSet;
use PHPUnit\Framework\TestCase;

final class ComposerBlockerExplanationTest extends TestCase
{
    /** @dataProvider unusableDiagnostics */
    public function testOneRawProblemRetainsBothAProvedEdgeAndPolicyWithoutBlamingCompatibleParticipants(bool $withDiagnostic): void
    {
        $output = implode("\n", [
            'Problem 1',
            '- fixture/old-consumer is locked to version 1.0.0 and an update of this package was not requested.',
            '- fixture/old-consumer 1.0.0 requires laravel/framework ^10.0 -> it conflicts with your root composer.json require (^11.0).',
            '- fixture/participant 1.0.0 requires laravel/framework ^10.0|^11.0 -> satisfiable by laravel/framework[v11.0.0].',
            $this->policyOutput(),
        ]);
        $diagnostics = $withDiagnostic ? [new ComposerDiagnostic(
            'laravel/framework',
            '^11.0',
            ['composer', 'prohibits', '--locked'],
            1,
            'fixture/participant 1.0.0 requires laravel/framework (^10.0|^11.0)',
            ''
        )] : [];
        $result = $this->result($output, $diagnostics);
        $evidence = new EvidenceLedger();
        $blockers = (new BlockerGrouper())->group([$result], $evidence);

        self::assertCount(2, $blockers);
        self::assertSame(BlockerType::TRANSITIVE_PACKAGE_CONFLICT, $blockers[0]->type());
        self::assertSame('fixture/old-consumer', $blockers[0]->blocker());
        self::assertSame('1.0.0', $blockers[0]->lockedVersion());
        self::assertSame('^10.0', $blockers[0]->conflict());
        $this->assertPolicy($blockers[1]);
        foreach ($blockers as $blocker) {
            self::assertTrue($blocker->blocksResolution());
            self::assertNotSame('fixture/participant', $blocker->blocker());
            $evidence->validateReferences($blocker->evidence());
        }
        self::assertTrue($result->isSolverFailure());
        self::assertFalse($result->succeeded());
    }

    /** @return list<array{bool}> */
    public function unusableDiagnostics(): array
    {
        return [[false], [true]];
    }

    /** @dataProvider groupingOrders */
    public function testGroupingKeepsDifferentUnknownExplanationsAndDeduplicatesEquivalentOnes(bool $crossScenario, bool $genericFirst): void
    {
        $outputs = $genericFirst
            ? [$this->genericOutput(), $this->policyOutput(), $this->genericOutput(), $this->policyOutput()]
            : [$this->policyOutput(), $this->genericOutput(), $this->policyOutput(), $this->genericOutput()];
        $results = $crossScenario
            ? array_map(fn (string $output, int $index): ScenarioResult => $this->result($output, [], 'scenario-' . $index), $outputs, array_keys($outputs))
            : [$this->result(implode("\n", array_map(static fn (string $output, int $index): string => 'Problem ' . ($index + 1) . "\n" . $output, $outputs, array_keys($outputs))))];
        $evidence = new EvidenceLedger();
        $blockers = (new BlockerGrouper())->group($results, $evidence);

        self::assertCount(2, $blockers);
        $this->assertPolicy($blockers[$genericFirst ? 1 : 0]);
        self::assertStringNotContainsString('security', $blockers[$genericFirst ? 0 : 1]->summary());
        foreach ($blockers as $blocker) {
            self::assertSame(BlockerType::UNKNOWN_COMPOSER_FAILURE, $blocker->type());
            self::assertTrue($blocker->blocksResolution());
            self::assertCount($crossScenario ? 2 : 1, $blocker->evidence());
        }
        $evidence->validateReferences(array_merge(...array_map(static fn (Blocker $blocker): array => $blocker->evidence(), $blockers)));
    }

    /** @return list<array{bool, bool}> */
    public function groupingOrders(): array
    {
        return [[false, true], [true, true], [false, false], [true, false]];
    }

    public function testStageObservationsRetainSeparateExplanationsWithoutFalseSupersession(): void
    {
        $registry = new StageBlockerRegistry();
        $generic = $this->parse($this->genericOutput(), 'generic-1');
        $policy = $this->parse($this->policyOutput(), 'policy-1');
        $genericIds = $registry->observe('stage', 1, 'exact', [$generic], 'attempt-1', false);
        $policyIds = $registry->observe('stage', 2, 'wide', [$policy], 'attempt-2', false);
        $observedIds = $registry->observe('stage', 3, 'repeat', [
            $this->parse($this->genericOutput(), 'generic-3'),
            $this->parse($this->policyOutput(), 'policy-3'),
        ], 'attempt-3', false);
        $entries = $registry->ordered();

        self::assertCount(2, $entries);
        self::assertSame(array_merge($genericIds, $policyIds), $observedIds);
        self::assertNotSame($genericIds, $policyIds);
        self::assertNotSame($entries[0]->supersessionKey(), $entries[1]->supersessionKey());
        self::assertSame(['persists', 'persists'], array_map(static fn ($entry): string => $entry->lifecycle(), $entries));
        self::assertSame(['generic-1', 'attempt-1', 'generic-3'], $entries[0]->evidence());
        self::assertSame(['policy-1', 'attempt-2', 'policy-3'], $entries[1]->evidence());
        $this->assertPolicy($entries[1]->blocker());
        self::assertTrue($registry->hasActiveBlocking('stage'));
    }

    public function testResolvedGenericDoesNotAbsorbNewPolicyAndBothCanReappearWithTheirOwnHistory(): void
    {
        $registry = new StageBlockerRegistry();
        $genericIds = $registry->observe('stage', 1, 'exact', [$this->parse($this->genericOutput(), 'generic-1')], 'attempt-1', true);
        $registry->observe('stage', 2, 'resolved', [], 'attempt-2', true);
        $policyIds = $registry->observe('stage', 3, 'policy', [$this->parse($this->policyOutput(), 'policy-3')], 'attempt-3', true);
        $registry->observe('stage', 4, 'resolved', [], 'attempt-4', true);
        $reappearedIds = $registry->observe('stage', 5, 'repeat', [
            $this->parse($this->genericOutput(), 'generic-5'),
            $this->parse($this->policyOutput(), 'policy-5'),
        ], 'attempt-5', true);
        $entries = $registry->ordered();

        self::assertCount(2, $entries);
        self::assertSame(array_merge($genericIds, $policyIds), $reappearedIds);
        foreach ($entries as $entry) {
            self::assertSame(['detected', 'resolved', 'detected'], array_column($entry->toArray()['lifecycle_history'], 'status'));
            self::assertTrue($entry->isActive());
            self::assertTrue($entry->isBlocking());
        }
        self::assertSame([1, 5], array_column($entries[0]->toArray()['observations'], 'attempt'));
        self::assertSame([3, 5], array_column($entries[1]->toArray()['observations'], 'attempt'));
        self::assertNotContains('policy-3', $entries[0]->evidence());
        self::assertNotContains('generic-1', $entries[1]->evidence());
        $this->assertPolicy($entries[1]->blocker());
    }

    public function testAChangedPolicyConstraintSupersedesOnlyTheSameExplanation(): void
    {
        $registry = new StageBlockerRegistry();
        $generic = $this->parse($this->genericOutput(), 'generic-1');
        $policy = $this->parse($this->policyOutput(), 'policy-1');
        $registry->observe('stage', 1, 'exact', [$generic, $policy], 'attempt-1', false);
        $changedPolicy = new Blocker(
            $policy->type(),
            $policy->subject(),
            $policy->summary(),
            $policy->confidence(),
            ['policy-2'],
            '^12.0',
            null,
            null,
            null,
            $policy->dependencyPath(),
            $policy->options()
        );
        $registry->observe('stage', 2, 'new-target', [$changedPolicy], 'attempt-2', false);
        $entries = $registry->ordered();

        self::assertCount(3, $entries);
        self::assertSame(['detected', 'superseded', 'detected'], array_map(static fn ($entry): string => $entry->lifecycle(), $entries));
        self::assertSame($entries[1]->supersessionKey(), $entries[2]->supersessionKey());
        self::assertNotSame($entries[1]->identityKey(), $entries[2]->identityKey());
        self::assertTrue($entries[0]->isActive());
        self::assertTrue($entries[2]->isActive());
        $this->assertPolicy($entries[2]->blocker());
    }

    private function assertPolicy(Blocker $blocker): void
    {
        self::assertSame(BlockerType::UNKNOWN_COMPOSER_FAILURE, $blocker->type());
        self::assertNull($blocker->blocker());
        self::assertNull($blocker->conflict());
        self::assertStringContainsString('security advisories', $blocker->summary());
        self::assertStringContainsString('security policy', implode(' ', $blocker->options()));
    }

    private function parse(string $output, string $evidence): Blocker
    {
        return (new ComposerBlockerParser())->parse($this->result($output), $evidence)[0];
    }

    private function genericOutput(): string
    {
        return '- Root composer.json requires laravel/framework ^11.0 -> satisfiable by laravel/framework[v11.0.0].'
            . "\n- Conclusion: don't install laravel/framework v11.0.0 (conflict analysis result)";
    }

    private function policyOutput(): string
    {
        return '- Root composer.json requires laravel/framework ^11.0 -> found laravel/framework[v11.0.0] but these were not loaded, because they are affected by security advisories ("PKSA-fixture").';
    }

    /** @param list<ComposerDiagnostic> $diagnostics */
    private function result(string $output, array $diagnostics = [], string $scenarioName = 'explanation'): ScenarioResult
    {
        return new ScenarioResult(
            new Scenario($scenarioName, new UpgradeTargetSet([new UpgradeTarget('laravel/framework', '^11.0')])),
            2,
            '',
            $output,
            null,
            null,
            ScenarioResult::FAILURE_SOLVER,
            null,
            [],
            0,
            null,
            $diagnostics
        );
    }
}
