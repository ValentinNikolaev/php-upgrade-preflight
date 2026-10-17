<?php

declare(strict_types=1);

namespace PhpUpgradePreflight\Core\Tests\Unit\Analysis;

use PhpUpgradePreflight\Core\Analysis\BlockerGrouper;
use PhpUpgradePreflight\Core\Analysis\ComposerBlockerParser;
use PhpUpgradePreflight\Core\Analysis\StageBlockerRegistry;
use PhpUpgradePreflight\Core\Model\Blocker;
use PhpUpgradePreflight\Core\Model\BlockerType;
use PhpUpgradePreflight\Core\Model\ComposerDiagnostic;
use PhpUpgradePreflight\Core\Model\ComposerLock;
use PhpUpgradePreflight\Core\Model\EvidenceLedger;
use PhpUpgradePreflight\Core\Model\Scenario;
use PhpUpgradePreflight\Core\Model\ScenarioResult;
use PhpUpgradePreflight\Core\Model\UpgradeTarget;
use PhpUpgradePreflight\Core\Model\UpgradeTargetSet;
use PHPUnit\Framework\TestCase;

final class ComposerBlockerCausalityTest extends TestCase
{
    /** @dataProvider craterEdges */
    public function testCraterPestParticipationDoesNotProveAnIncompatibleFrameworkRequirement(string $dependency, string $rejection): void
    {
        // Bounded Problem 3 from cohort-v3 Crater solver-4/solver-5; no application files are needed.
        $output = implode("\n", [
            'Problem 3',
            '- pestphp/pest-plugin-laravel is locked to version v1.2.0 and an update of this package was not requested.',
            '- laravel/framework 9.x-dev requires ' . $dependency . ' ^6.0 -> ' . $rejection,
            '- pestphp/pest-plugin-laravel v1.2.0 requires laravel/framework ^7.0 || ^8.0 || ^9.0 -> satisfiable by laravel/framework[9.x-dev].',
        ]);
        $result = $this->result('^9.0', $output);
        $lock = $this->lock('pestphp/pest-plugin-laravel', 'v1.2.0', '^7.0 || ^8.0 || ^9.0');
        $originalLock = $lock->data();
        $evidence = new EvidenceLedger();
        $blockers = (new BlockerGrouper())->group([$result], $evidence, $lock);

        self::assertCount(1, $blockers);
        self::assertSame(BlockerType::UNKNOWN_COMPOSER_FAILURE, $blockers[0]->type());
        self::assertNull($blockers[0]->blocker());
        self::assertNull($blockers[0]->lockedVersion());
        self::assertNull($blockers[0]->conflict());
        self::assertStringContainsString('chain', $blockers[0]->summary());
        self::assertContains($dependency, $blockers[0]->dependencyPath());
        self::assertSame($output, $evidence->all()[0]->context()['output_excerpt']);
        self::assertSame($originalLock, $lock->data());
        $this->assertStillFailedAndBlocking($result, $blockers);
    }

    /** @return list<array{string, string}> */
    public function craterEdges(): array
    {
        return [
            ['symfony/error-handler', 'found symfony/error-handler[v6.0.0-BETA1, ..., 6.4.x-dev] but the package is fixed to v5.4.9 (lock file version) by a partial update and that version does not match.'],
            ['symfony/finder', 'found symfony/finder[v6.0.0-BETA1, ..., 6.4.x-dev] but these were not loaded, likely because it conflicts with another require.'],
        ];
    }

    public function testBookStackLockedSocialiteNoticeDoesNotEstablishItsCausality(): void
    {
        // Bounded solver-5 Problem 2: Socialite has no requiring/conflicting edge in this problem.
        $output = implode("\n", [
            'Problem 2',
            '- laravel/socialite is locked to version v5.16.0 and an update of this package was not requested.',
            '- laravel/tinker is locked to version v2.10.0 and an update of this package was not requested.',
            '- Root composer.json requires nunomaduro/collision ^8.1 -> satisfiable by nunomaduro/collision[v8.1.0, ..., v8.9.5].',
            '- laravel/tinker v2.10.0 requires illuminate/console ^6.0|^7.0|^8.0|^9.0|^10.0|^11.0 -> satisfiable by illuminate/console[v6.0.0, ..., v11.51.0].',
            '- nunomaduro/termwind v1.17.0 requires symfony/console ^6.4.15 -> satisfiable by symfony/console[v6.4.15, ..., v6.4.48].',
            "- Conclusion: don't install symfony/console v6.4.48 (conflict analysis result)",
        ]);
        $result = $this->result('^11.0', $output);
        $blockers = (new BlockerGrouper())->group([$result], new EvidenceLedger(), $this->lock('laravel/socialite', 'v5.16.0', '^11.0'));

        self::assertCount(1, $blockers);
        self::assertSame(BlockerType::UNKNOWN_COMPOSER_FAILURE, $blockers[0]->type());
        self::assertNull($blockers[0]->blocker());
        self::assertNull($blockers[0]->lockedVersion());
        self::assertStringContainsString('chain', $blockers[0]->summary());
        self::assertNotContains('laravel/socialite', $blockers[0]->dependencyPath());
        $this->assertStillFailedAndBlocking($result, $blockers);
    }

    public function testSnipeItCompatibleGoogle2faRequirementRetainsTheActualAdvisoryRejection(): void
    {
        $output = implode("\n", [
            'Problem 2',
            '- pragmarx/google2fa-laravel is locked to version v1.4.1 and an update of this package was not requested.',
            '- pragmarx/google2fa-laravel v1.4.1 requires laravel/framework >=5.4.36|^8.0 -> found laravel/framework[v5.4.36, ..., v11.57.0] but these were not loaded, because they are affected by security advisories ("PKSA-d5tc-s1qs-h781").',
        ]);
        $result = $this->result('^11.0', $output);
        $evidence = new EvidenceLedger();
        $blockers = (new BlockerGrouper())->group([$result], $evidence, $this->lock('pragmarx/google2fa-laravel', 'v1.4.1', '>=5.4.36|^8.0'));

        self::assertCount(1, $blockers);
        self::assertSame(BlockerType::UNKNOWN_COMPOSER_FAILURE, $blockers[0]->type());
        self::assertNull($blockers[0]->blocker());
        self::assertNull($blockers[0]->lockedVersion());
        self::assertNull($blockers[0]->conflict());
        self::assertStringContainsString('security advisories', $blockers[0]->summary());
        self::assertStringNotContainsString('Upgrade or replace', implode(' ', $blockers[0]->options()));
        self::assertStringNotContainsString('ignore', implode(' ', $blockers[0]->options()));
        self::assertSame($output, $evidence->all()[0]->context()['output_excerpt']);
        $this->assertStillFailedAndBlocking($result, $blockers);
    }

    /** @dataProvider overlappingRequirements */
    public function testCompatibleOrUnparseableDiagnosticRequirementIsNotAnEstablishedCause(string $requirement): void
    {
        $result = $this->result('^11.0', 'Resolution failed.', [new ComposerDiagnostic(
            'laravel/framework',
            '^11.0',
            ['composer', 'prohibits', '--locked'],
            1,
            'fixture/participant 1.0.0 requires laravel/framework (' . $requirement . ')',
            ''
        )]);
        $blockers = (new ComposerBlockerParser())->parse($result, 'solver-1');
        self::assertCount(1, $blockers);
        self::assertSame(BlockerType::UNKNOWN_COMPOSER_FAILURE, $blockers[0]->type());
        self::assertNull($blockers[0]->blocker());
        self::assertNull($blockers[0]->conflict());
    }

    /** @return list<array{string}> */
    public function overlappingRequirements(): array
    {
        return [['>=5.4.36|^8.0'], ['^10.0|^11.0'], ['*'], ['not-a-constraint']];
    }

    public function testAdvisoryReasonSurvivesAlongsideADiagnosticThatProvesAnotherIncompatibleEdge(): void
    {
        $output = 'Problem 1' . "\n"
            . '- Root composer.json requires laravel/framework ^11.0, found laravel/framework[v11.0.0] but these were not loaded, because they are affected by security advisories.';
        $result = $this->result('^11.0', $output, [new ComposerDiagnostic(
            'laravel/framework',
            '^11.0',
            ['composer', 'prohibits', '--locked'],
            1,
            'fixture/old-consumer 1.0.0 requires laravel/framework (^10.0)',
            ''
        )]);
        $evidence = new EvidenceLedger();
        $blockers = (new BlockerGrouper())->group([$result], $evidence);
        self::assertCount(2, $blockers);
        self::assertSame('fixture/old-consumer', $blockers[0]->blocker());
        self::assertSame('^10.0', $blockers[0]->conflict());
        self::assertSame(BlockerType::TRANSITIVE_PACKAGE_CONFLICT, $blockers[0]->type());
        self::assertStringContainsString('security advisories', $blockers[1]->summary());
        self::assertNull($blockers[1]->blocker());
        $evidence->validateReferences(array_merge($blockers[0]->evidence(), $blockers[1]->evidence()));
    }

    public function testAnAdvisoryRejectedCandidateWithoutARequestedFrameworkConstraintIsNotACompatibilityCause(): void
    {
        $output = 'Problem 4' . "\n"
            . '- jc5/google2fa-laravel 2.0.10 requires laravel/framework ^11.0|^12.0 -> found laravel/framework[v11.0.0, ..., v12.70.0] but these were not loaded, because they are affected by security advisories.';
        $scenario = new Scenario('target-platform-only', new UpgradeTargetSet([new UpgradeTarget('php', '8.4')]), true, false, false, false);
        $result = new ScenarioResult($scenario, 2, '', $output, null, null, ScenarioResult::FAILURE_SOLVER);
        $blockers = (new BlockerGrouper())->group([$result], new EvidenceLedger(), $this->lock('jc5/google2fa-laravel', 'v2.0.8', '>=5.4.36'));
        self::assertCount(1, $blockers);
        self::assertSame(BlockerType::UNKNOWN_COMPOSER_FAILURE, $blockers[0]->type());
        self::assertNull($blockers[0]->blocker());
        self::assertNull($blockers[0]->lockedVersion());
        self::assertNull($blockers[0]->conflict());
        self::assertStringContainsString('security advisories', $blockers[0]->summary());
        self::assertTrue($result->isSolverFailure());
        self::assertTrue($result->scenario()->isPartialTargetProbe());
    }

    public function testALockedParticipantWithASatisfiedIncomingRequirementIsNotACause(): void
    {
        $result = $this->result('^11.0', implode("\n", [
            'laravel/socialite is locked to version v5.16.0 and an update of this package was not requested.',
            'fixture/project 1.0.0 requires laravel/socialite ^5.0',
            'fixture/participant 1.0.0 requires laravel/framework ^10.0|^11.0 -> satisfiable by laravel/framework[v11.0.0].',
        ]));
        $blockers = (new ComposerBlockerParser())->parse($result, 'solver-1');
        self::assertCount(1, $blockers);
        self::assertSame(BlockerType::UNKNOWN_COMPOSER_FAILURE, $blockers[0]->type());
        self::assertNull($blockers[0]->blocker());
        self::assertNull($blockers[0]->lockedVersion());
        self::assertNull($blockers[0]->conflict());
    }

    public function testAProvideRelationInADiagnosticDoesNotProveAnIncompatibleRequirement(): void
    {
        $result = $this->result('^11.0', 'Resolution failed.', [new ComposerDiagnostic(
            'laravel/framework',
            '^11.0',
            ['composer', 'prohibits', '--locked'],
            1,
            'fixture/provider 1.0.0 provides laravel/framework (^11.0)',
            ''
        )]);
        $blockers = (new ComposerBlockerParser())->parse($result, 'solver-1');
        self::assertCount(1, $blockers);
        self::assertSame(BlockerType::UNKNOWN_COMPOSER_FAILURE, $blockers[0]->type());
        self::assertNull($blockers[0]->blocker());
        self::assertNull($blockers[0]->conflict());
    }

    /** @dataProvider causalRangeCases */
    public function testPackageCausalityRequiresProofAcrossTheRequestedRangeInBothInputPaths(
        string $target,
        string $operation,
        string $constraint,
        string $expectedType,
        bool $diagnostic
    ): void {
        $relation = 'fixture/consumer 1.0.0 ' . $operation . ' laravel/framework (' . $constraint . ')';
        $result = $this->result($target, $diagnostic ? 'Resolution failed.' : $relation, $diagnostic ? [
            new ComposerDiagnostic('laravel/framework', $target, ['composer', 'prohibits', '--locked'], 1, $relation, ''),
        ] : []);
        $blockers = (new ComposerBlockerParser())->parse($result, 'range-evidence');
        self::assertCount(1, $blockers);
        self::assertSame($expectedType, $blockers[0]->type());
        if ($expectedType === BlockerType::UNKNOWN_COMPOSER_FAILURE) {
            self::assertNull($blockers[0]->blocker());
            self::assertNull($blockers[0]->conflict());
        } else {
            self::assertSame('fixture/consumer', $blockers[0]->blocker());
            self::assertSame($constraint, $blockers[0]->conflict());
        }
        self::assertTrue($result->isSolverFailure());
        self::assertTrue($blockers[0]->blocksResolution());
    }

    /** @return list<array{string, string, string, string, bool}> */
    public function causalRangeCases(): array
    {
        $cases = [];
        foreach ([false, true] as $diagnostic) {
            foreach ([
                ['^11.0', 'requires', '^10.0', BlockerType::TRANSITIVE_PACKAGE_CONFLICT],
                ['^11.0', 'requires', '^10.0|^11.0', BlockerType::UNKNOWN_COMPOSER_FAILURE],
                ['^10.0|^11.0', 'requires', '^11.0', BlockerType::UNKNOWN_COMPOSER_FAILURE],
                ['^11.0', 'conflicts with', '<11.48.0', BlockerType::UNKNOWN_COMPOSER_FAILURE],
                ['^11.0|^12.0', 'conflicts with', '^11.0', BlockerType::UNKNOWN_COMPOSER_FAILURE],
                ['^11.0', 'conflicts with', '>=11.0.0', BlockerType::REPLACE_PROVIDE_CONFLICT],
                ['11.0.0', 'conflicts with', '<11.48.0', BlockerType::REPLACE_PROVIDE_CONFLICT],
            ] as $case) {
                $cases[] = array_merge($case, [$diagnostic]);
            }
        }
        return $cases;
    }

    /** @param list<Blocker> $blockers */
    private function assertStillFailedAndBlocking(ScenarioResult $result, array $blockers): void
    {
        self::assertTrue($result->isSolverFailure());
        self::assertFalse($result->succeeded());
        self::assertTrue($blockers[0]->blocksResolution());
        $registry = new StageBlockerRegistry();
        $registry->observe('stage', 1, $result->scenario()->name(), $blockers, 'attempt-evidence', true);
        self::assertTrue($registry->hasActiveBlocking('stage'));
        self::assertNull($registry->ordered()[0]->toArray()['blocking_package']);
    }

    private function lock(string $package, string $version, string $requirement): ComposerLock
    {
        return new ComposerLock(['packages' => [[
            'name' => $package, 'version' => $version, 'require' => ['laravel/framework' => $requirement],
        ]]]);
    }

    /** @param list<ComposerDiagnostic> $diagnostics */
    private function result(string $constraint, string $output, array $diagnostics = []): ScenarioResult
    {
        return new ScenarioResult(
            new Scenario('causal-evidence', new UpgradeTargetSet([new UpgradeTarget('laravel/framework', $constraint)])),
            2,
            '',
            $output,
            null,
            null,
            ScenarioResult::FAILURE_SOLVER,
            '2.10.2',
            [],
            0,
            null,
            $diagnostics
        );
    }
}
