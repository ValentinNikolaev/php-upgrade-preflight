<?php

declare(strict_types=1);

namespace PhpUpgradePreflight\Core\Tests\Unit\Analysis;

use PhpUpgradePreflight\Core\Analysis\BlockerGrouper;
use PhpUpgradePreflight\Core\Analysis\ComposerBlockerParser;
use PhpUpgradePreflight\Core\Analysis\StageBlockerRegistry;
use PhpUpgradePreflight\Core\Model\ComposerDiagnostic;
use PhpUpgradePreflight\Core\Model\EvidenceLedger;
use PhpUpgradePreflight\Core\Model\Scenario;
use PhpUpgradePreflight\Core\Model\ScenarioResult;
use PhpUpgradePreflight\Core\Model\UpgradeTarget;
use PhpUpgradePreflight\Core\Model\UpgradeTargetSet;
use PHPUnit\Framework\TestCase;

final class ComposerBlockerAttributionTest extends TestCase
{
    /** @dataProvider diagnosticModeProvider */
    public function testLycheeConflictKeepsItsOwnAncestryInDirectAndStagedBlockers(bool $diagnostic): void
    {
        // Minimized from Lychee's real root and locked metadata: Spatie does not require Collision.
        $output = implode("\n", [
            'spatie/laravel-image-optimizer 1.8.2 requires laravel/framework (^8.0|^9.0|^10.0|^11.0|^12.0)',
            '`--lychee-org/lychee (requires spatie/laravel-image-optimizer ^1.8)',
            'nunomaduro/collision v8.8.2 conflicts with laravel/framework (<11.44.2 || >=13.0.0)',
            '`--lychee-org/lychee (requires nunomaduro/collision ^8.3)',
        ]);
        $result = $this->result('laravel/framework', '^13.0', $output, $diagnostic);
        $blockers = (new BlockerGrouper())->group([$result], new EvidenceLedger());
        self::assertCount($diagnostic ? 2 : 1, $blockers);
        $collision = $blockers[count($blockers) - 1];
        self::assertSame('nunomaduro/collision', $collision->blocker());
        self::assertSame('v8.8.2', $collision->lockedVersion());
        self::assertSame('<11.44.2 || >=13.0.0', $collision->conflict());
        $expectedPath = ['lychee-org/lychee', 'nunomaduro/collision', 'laravel/framework'];
        self::assertSame($expectedPath, $collision->dependencyPath());

        $registry = new StageBlockerRegistry();
        $registry->observe('laravel-12-to-13', 1, 'target_only', $blockers, 'stage-attempt-1', true);
        $entries = $registry->ordered();
        self::assertSame($expectedPath, $entries[count($entries) - 1]->toArray()['dependency_path']);
        if ($diagnostic) {
            self::assertSame(
                ['lychee-org/lychee', 'spatie/laravel-image-optimizer', 'laravel/framework'],
                $blockers[0]->dependencyPath()
            );
        }
    }

    /** @return list<array{bool}> */
    public function diagnosticModeProvider(): array
    {
        return [[true], [false]];
    }

    /** @dataProvider collisionCandidateProvider */
    public function testInvoiceNinjaCandidateRulesDoNotBecomeActiveLaravelBlockers(string $candidate): void
    {
        // Bounded lines from the completed Invoice Ninja attempts 2 and 3 diagnostic.
        $output = implode("\n", [
            'nunomaduro/collision v8.1.1 Cli error handling for console/command-line PHP applications.',
            '|--nunomaduro/collision ' . $candidate . ' (conflicts laravel/framework <11.48.0 || >=14.0.0) (circular dependency aborted here)',
            '|--nunomaduro/collision ' . $candidate . ' (conflicts phpunit/phpunit <11.5.50 || >=14.0.0) (circular dependency aborted here)',
            '`--nunomaduro/collision ' . $candidate . ' (requires filp/whoops ^2.18.4) (circular dependency aborted here)',
        ]);
        $scenario = new Scenario('invoice-ninja', new UpgradeTargetSet([
            new UpgradeTarget('laravel/framework', '^12.0'),
            new UpgradeTarget('nunomaduro/collision', '^8.6'),
        ]));
        $diagnostics = [
            new ComposerDiagnostic('nunomaduro/collision', '^8.6', ['composer', 'prohibits'], 1, $output, ''),
        ];
        $result = new ScenarioResult(
            $scenario,
            2,
            '',
            'Resolution failed.',
            null,
            null,
            ScenarioResult::FAILURE_SOLVER,
            null,
            [],
            0,
            null,
            $diagnostics
        );
        $blockers = (new ComposerBlockerParser())->parse($result, 'solver-1');

        self::assertCount(1, $blockers);
        self::assertSame('unknown-composer-failure', $blockers[0]->type());
        self::assertNull($blockers[0]->blocker());
        self::assertNull($blockers[0]->lockedVersion());
        self::assertSame('^12.0', $blockers[0]->requestedConstraint());
        $registry = new StageBlockerRegistry();
        $registry->observe('laravel-11-to-12', 2, 'root_constraint_remediation', $blockers, 'stage-attempt-2', true);
        self::assertCount(1, $registry->ordered());
        self::assertNull($registry->ordered()[0]->toArray()['blocking_package']);
    }

    /** @return list<array{string}> */
    public function collisionCandidateProvider(): array
    {
        return [['v8.x-dev'], ['v8.9.5']];
    }

    public function testKoelCandidateRequirementsDoNotBorrowTheSanctumRequestedConstraint(): void
    {
        $output = implode("\n", [
            'laravel/sanctum v3.3.3 Laravel Sanctum provides a featherweight authentication system for SPAs and simple APIs.',
            '|--laravel/sanctum v4.3.3 (requires illuminate/console ^11.0|^12.0|^13.0) (circular dependency aborted here)',
            '`--koel/koel (does not require illuminate/console but v10.48.8 is installed) (circular dependency aborted here)',
        ]);
        $scenario = new Scenario('koel', new UpgradeTargetSet([
            new UpgradeTarget('laravel/framework', '^11.0'),
            new UpgradeTarget('laravel/sanctum', '^4.0'),
        ]));
        $diagnostic = new ComposerDiagnostic('laravel/sanctum', '^4.0', ['composer', 'prohibits'], 1, $output, '');
        $result = new ScenarioResult(
            $scenario,
            2,
            '',
            'Resolution failed.',
            null,
            null,
            ScenarioResult::FAILURE_SOLVER,
            null,
            [],
            0,
            null,
            [$diagnostic]
        );
        $blockers = (new ComposerBlockerParser())->parse($result, 'solver-1');

        self::assertCount(1, $blockers);
        self::assertSame('unknown-composer-failure', $blockers[0]->type());
        self::assertSame('laravel/framework', $blockers[0]->subject());
        self::assertSame('^11.0', $blockers[0]->requestedConstraint());
        self::assertNull($blockers[0]->blocker());
        self::assertNull($blockers[0]->lockedVersion());
    }

    /** @dataProvider conflictConstraintProvider */
    public function testDiagnosticConflictRulesMustOverlapTheRequestedDependency(string $constraint, string $type): void
    {
        $result = $this->result(
            'laravel/framework',
            '^12.0',
            'nunomaduro/collision v8.1.1 conflicts with laravel/framework (' . $constraint . ')',
            true
        );
        $blockers = (new ComposerBlockerParser())->parse($result, 'solver-1');
        self::assertCount(1, $blockers);
        self::assertSame($type, $blockers[0]->type());
        self::assertSame('^12.0', $blockers[0]->requestedConstraint());
    }

    /** @return list<array{string, string}> */
    public function conflictConstraintProvider(): array
    {
        return [
            ['<11 || >=12', 'replace-provide-conflict'],
            ['<11.48.0 || >=14.0.0', 'unknown-composer-failure'],
        ];
    }

    public function testUnparseableIncomingConflictRetainsFailedEvidenceWithoutInventingPackageBlame(): void
    {
        $output = 'fixture/blocker 1.0.0 conflicts with fixture/target (not-a-version)';
        $result = $this->result('fixture/target', '^2.0', $output, true);
        $evidence = new EvidenceLedger();
        $blockers = (new BlockerGrouper())->group([$result], $evidence);

        self::assertCount(1, $blockers);
        self::assertSame('unknown-composer-failure', $blockers[0]->type());
        self::assertSame('fixture/target', $blockers[0]->subject());
        self::assertSame('^2.0', $blockers[0]->requestedConstraint());
        self::assertSame('low', $blockers[0]->confidence());
        self::assertNull($blockers[0]->blocker());
        self::assertNull($blockers[0]->lockedVersion());
        self::assertNull($blockers[0]->conflict());
        self::assertSame(['fixture/target'], $blockers[0]->dependencyPath());
        self::assertSame(['solver-1'], $blockers[0]->evidence());
        self::assertCount(1, $evidence->all());
        self::assertSame('solver-1', $evidence->all()[0]->id());
        $context = $evidence->all()[0]->context();
        self::assertSame(2, $context['exit_code']);
        self::assertSame('Resolution failed.', $context['output_excerpt']);
        self::assertSame('fixture/target', $context['diagnostics'][0]['package']);
        self::assertSame('^2.0', $context['diagnostics'][0]['constraint']);
        self::assertSame($output, $context['diagnostics'][0]['stdout_excerpt']);
    }

    public function testAmbiguousOrConflictingAncestorsDoNotInventARequirementPath(): void
    {
        $output = implode("\n", [
            'fixture/blocker 1.0.0 requires fixture/target (^1.0)',
            'fixture/unrelated 1.0.0 conflicts with fixture/blocker (*)',
            'fixture/first 1.0.0 requires fixture/blocker (^1.0)',
            'fixture/second 1.0.0 requires fixture/blocker (^1.0)',
        ]);
        $blockers = (new ComposerBlockerParser())->parse($this->result('fixture/target', '^2.0', $output, true), 'solver-1');
        self::assertCount(1, $blockers);
        self::assertSame(['fixture/blocker', 'fixture/target'], $blockers[0]->dependencyPath());
    }

    public function testLockedPackagesOwnRequirementIsNotReversedIntoDependencyAncestry(): void
    {
        $output = implode("\n", [
            'spatie/laravel-image-optimizer is locked to version 1.8.2 and an update of this package was not requested.',
            'spatie/laravel-image-optimizer 1.8.2 requires laravel/framework ^8.0|^9.0|^10.0|^11.0|^12.0 -> found laravel/framework but it conflicts with your root composer.json require (^13.0).',
        ]);
        $blockers = (new ComposerBlockerParser())->parse($this->result('laravel/framework', '^13.0', $output, false), 'solver-1');
        self::assertCount(1, $blockers);
        self::assertSame('spatie/laravel-image-optimizer', $blockers[0]->blocker());
        self::assertSame('^8.0|^9.0|^10.0|^11.0|^12.0', $blockers[0]->conflict());
        self::assertSame(['spatie/laravel-image-optimizer', 'laravel/framework'], $blockers[0]->dependencyPath());
    }

    private function result(string $subject, string $constraint, string $output, bool $diagnostic): ScenarioResult
    {
        $scenario = new Scenario('attribution', new UpgradeTargetSet([new UpgradeTarget($subject, $constraint)]));
        $diagnostics = $diagnostic
            ? [new ComposerDiagnostic($subject, $constraint, ['composer', 'prohibits'], 1, $output, '')]
            : [];

        return new ScenarioResult(
            $scenario,
            2,
            '',
            $diagnostic ? 'Resolution failed.' : $output,
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
