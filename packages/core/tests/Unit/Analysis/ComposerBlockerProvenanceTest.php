<?php

declare(strict_types=1);

namespace PhpUpgradePreflight\Core\Tests\Unit\Analysis;

use PhpUpgradePreflight\Core\Analysis\BlockerGrouper;
use PhpUpgradePreflight\Core\Analysis\ComposerBlockerParser;
use PhpUpgradePreflight\Core\Model\ComposerLock;
use PhpUpgradePreflight\Core\Model\EvidenceLedger;
use PhpUpgradePreflight\Core\Model\Scenario;
use PhpUpgradePreflight\Core\Model\ScenarioResult;
use PhpUpgradePreflight\Core\Model\UpgradeTarget;
use PhpUpgradePreflight\Core\Model\UpgradeTargetSet;
use PHPUnit\Framework\TestCase;

final class ComposerBlockerProvenanceTest extends TestCase
{
    public function testFireflyCandidateDoesNotBecomeTheDifferentLockedVersion(): void
    {
        $lock = $this->lock('v2.0.8', '>=5.4.36');
        $original = $lock->data();
        $evidence = new EvidenceLedger();
        $result = $this->fireflyResult();
        $blockers = (new BlockerGrouper())->group([$result], $evidence, $lock);

        self::assertCount(1, $blockers);
        self::assertSame('jc5/google2fa-laravel', $blockers[0]->blocker());
        self::assertNull($blockers[0]->lockedVersion());
        self::assertSame('^11.0|^12.0', $blockers[0]->conflict());
        self::assertSame(['jc5/google2fa-laravel', 'laravel/framework'], $blockers[0]->dependencyPath());
        self::assertSame(['solver-1'], $blockers[0]->evidence());
        self::assertSame($result->stderr(), $evidence->all()[0]->context()['output_excerpt']);
        self::assertStringContainsString('affected by security advisories', $evidence->all()[0]->context()['output_excerpt']);
        self::assertSame($original, $lock->data());
    }

    public function testEachAttemptUsesItsOwnSuppliedLockInsteadOfAnOriginalOrPreviousLock(): void
    {
        $grouper = new BlockerGrouper();
        $result = $this->fireflyResult();
        $originalLock = $this->lock('v2.0.8', '>=5.4.36');
        $candidateStateLock = $this->lock('v2.0.10', '^11.0|^12.0');

        $original = $grouper->group([$result], new EvidenceLedger(), $originalLock);
        $laterStage = $grouper->group([$result], new EvidenceLedger(), $candidateStateLock);
        $originalAgain = $grouper->group([$result], new EvidenceLedger(), $originalLock);

        self::assertNull($original[0]->lockedVersion());
        self::assertSame('2.0.10', $laterStage[0]->lockedVersion());
        self::assertSame('^11.0|^12.0', $laterStage[0]->conflict());
        self::assertNull($originalAgain[0]->lockedVersion());
    }

    public function testAnUnscopedPublicParserDoesNotAssertThatACandidateWasLocked(): void
    {
        $blockers = (new ComposerBlockerParser())->parse($this->fireflyResult(), 'candidate-evidence');

        self::assertCount(1, $blockers);
        self::assertNull($blockers[0]->lockedVersion());
        self::assertSame(['candidate-evidence'], $blockers[0]->evidence());
    }

    public function testAPackageAbsentFromTheSuppliedLockHasNoKnownLockedVersion(): void
    {
        $blockers = (new BlockerGrouper())->group([$this->fireflyResult()], new EvidenceLedger(), new ComposerLock([]));

        self::assertCount(1, $blockers);
        self::assertNull($blockers[0]->lockedVersion());
    }

    /** @dataProvider lockedMessageProvider */
    public function testExplicitLockedSolverEvidenceStillRetainsItsVersion(string $message): void
    {
        $result = $this->result($message . "\nfixture/blocker 1.0.0 requires fixture/target ^1.0");
        $blockers = (new BlockerGrouper())->group([$result], new EvidenceLedger());

        self::assertCount(1, $blockers);
        self::assertSame('fixture/blocker', $blockers[0]->blocker());
        self::assertSame('1.0.0', $blockers[0]->lockedVersion());
        self::assertSame('^1.0', $blockers[0]->conflict());
    }

    /** @return list<array{string}> */
    public function lockedMessageProvider(): array
    {
        return [
            ['fixture/blocker is locked to version 1.0.0 and an update of this package was not requested.'],
            ['fixture/blocker is fixed to 1.0.0 (lock file version) by a partial update.'],
        ];
    }

    public function testALockedPackageCannotBorrowADifferentCandidatesRequirement(): void
    {
        $result = $this->result(implode("\n", [
            'fixture/blocker is locked to version 1.0.0 and an update of this package was not requested.',
            'fixture/blocker 2.0.0 requires fixture/target ^3.0',
        ]));
        $blockers = (new BlockerGrouper())->group([$result], new EvidenceLedger());

        self::assertCount(1, $blockers);
        self::assertSame('1.0.0', $blockers[0]->lockedVersion());
        self::assertNull($blockers[0]->conflict());
        self::assertSame(['fixture/blocker'], $blockers[0]->dependencyPath());
    }

    private function lock(string $version, string $requirement): ComposerLock
    {
        return new ComposerLock(['packages' => [[
            'name' => 'jc5/google2fa-laravel',
            'version' => $version,
            'require' => ['laravel/framework' => $requirement],
        ]]]);
    }

    private function fireflyResult(): ScenarioResult
    {
        // Bounded Problem 4 from the sealed Firefly run: candidate metadata, not a locked-version assertion.
        $output = implode("\n", [
            'Problem 4',
            '- Root composer.json requires jc5/google2fa-laravel ^2.0 -> satisfiable by jc5/google2fa-laravel[2.0.0, ..., v2.0.14].',
            '- jc5/google2fa-laravel 2.0.10 requires laravel/framework ^11.0|^12.0 -> found laravel/framework[v11.0.0, ..., v11.57.0, v12.0.0, ..., v12.70.0] but these were not loaded, because they are affected by security advisories.',
        ]);
        $scenario = new Scenario('target-platform-only', new UpgradeTargetSet([new UpgradeTarget('php', '8.4')]), true, false, false, false);

        return new ScenarioResult($scenario, 2, '', $output, null, null, ScenarioResult::FAILURE_SOLVER);
    }

    private function result(string $output): ScenarioResult
    {
        $scenario = new Scenario('locked-rule', new UpgradeTargetSet([new UpgradeTarget('fixture/target', '^2.0')]));

        return new ScenarioResult($scenario, 2, '', $output, null, null, ScenarioResult::FAILURE_SOLVER);
    }
}
