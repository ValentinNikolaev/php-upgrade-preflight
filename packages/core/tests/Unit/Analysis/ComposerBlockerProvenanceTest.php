<?php

declare(strict_types=1);

namespace PhpUpgradePreflight\Core\Tests\Unit\Analysis;

use PhpUpgradePreflight\Core\Analysis\BlockerGrouper;
use PhpUpgradePreflight\Core\Analysis\ComposerBlockerParser;
use PhpUpgradePreflight\Core\Model\ComposerDiagnostic;
use PhpUpgradePreflight\Core\Model\ComposerLock;
use PhpUpgradePreflight\Core\Model\EvidenceLedger;
use PhpUpgradePreflight\Core\Model\Scenario;
use PhpUpgradePreflight\Core\Model\ScenarioResult;
use PhpUpgradePreflight\Core\Model\UpgradeTarget;
use PhpUpgradePreflight\Core\Model\UpgradeTargetSet;
use PHPUnit\Framework\TestCase;

final class ComposerBlockerProvenanceTest extends TestCase
{
    public function testCandidateDoesNotBecomeTheDifferentLockedVersion(): void
    {
        $lock = $this->lock('v2.0.8', '>=5.4.36');
        $original = $lock->data();
        $evidence = new EvidenceLedger();
        $result = $this->candidateRequirementResult();
        $blockers = (new BlockerGrouper())->group([$result], $evidence, $lock);

        self::assertCount(1, $blockers);
        self::assertSame('jc5/google2fa-laravel', $blockers[0]->blocker());
        self::assertNull($blockers[0]->lockedVersion());
        self::assertSame('^11.0|^12.0', $blockers[0]->conflict());
        self::assertSame(['jc5/google2fa-laravel', 'laravel/framework'], $blockers[0]->dependencyPath());
        self::assertSame(['solver-1'], $blockers[0]->evidence());
        self::assertSame($result->stderr(), $evidence->all()[0]->context()['output_excerpt']);
        self::assertStringContainsString('conflicts with your root composer.json require (^13.0)', $evidence->all()[0]->context()['output_excerpt']);
        self::assertSame($original, $lock->data());
    }

    public function testEachAttemptUsesItsOwnSuppliedLockInsteadOfAnOriginalOrPreviousLock(): void
    {
        $grouper = new BlockerGrouper();
        $result = $this->candidateRequirementResult();
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
        $blockers = (new ComposerBlockerParser())->parse($this->candidateRequirementResult(), 'candidate-evidence');

        self::assertCount(1, $blockers);
        self::assertNull($blockers[0]->lockedVersion());
        self::assertSame(['candidate-evidence'], $blockers[0]->evidence());
    }

    public function testAPackageAbsentFromTheSuppliedLockHasNoKnownLockedVersion(): void
    {
        $blockers = (new BlockerGrouper())->group([$this->candidateRequirementResult()], new EvidenceLedger(), new ComposerLock([]));

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

    /** @dataProvider lockedNoticePackageProvider */
    public function testExplicitNoticeProvesOnlyTheExactPackage(string $noticePackage, string $notice, ?string $expectedVersion): void
    {
        $diagnostic = new ComposerDiagnostic(
            'fixture/target',
            '^2.0',
            ['composer', 'prohibits'],
            1,
            'fixture/blocker 1.0.0 requires fixture/target ^1.0',
            ''
        );
        $result = new ScenarioResult(
            $this->result('')->scenario(),
            2,
            '',
            '- ' . $noticePackage . ' ' . $notice,
            null,
            null,
            ScenarioResult::FAILURE_SOLVER,
            null,
            [],
            0,
            null,
            [$diagnostic]
        );
        $blockers = (new ComposerBlockerParser())->parse($result, 'exact-package-evidence');

        self::assertCount(1, $blockers);
        self::assertSame('fixture/blocker', $blockers[0]->blocker());
        self::assertSame($expectedVersion, $blockers[0]->lockedVersion());
        self::assertSame('^1.0', $blockers[0]->conflict());
        self::assertSame(['fixture/blocker', 'fixture/target'], $blockers[0]->dependencyPath());
        self::assertSame(['exact-package-evidence'], $blockers[0]->evidence());
    }

    /** @return list<array{string, string, ?string}> */
    public function lockedNoticePackageProvider(): array
    {
        $cases = [];
        foreach ([
            'is locked to version 1.0.0 and an update of this package was not requested.',
            'is fixed to 1.0.0 (lock file version) by a partial update.',
        ] as $notice) {
            foreach ([
                ['fixture/blocker', '1.0.0'],
                ['other-fixture/blocker', null],
                ['other_fixture/blocker', null],
                ['other.fixture/blocker', null],
                ['otherfixture/blocker', null],
                ['fixture/blocker-extra', null],
                ['fixture/blocker_extra', null],
                ['fixture/blocker.extra', null],
            ] as [$package, $version]) {
                $cases[] = [$package, $notice, $version];
            }
        }

        return $cases;
    }

    public function testALockedPackageCannotBorrowADifferentCandidatesRequirement(): void
    {
        $result = $this->result(implode("\n", [
            'fixture/blocker is locked to version 1.0.0 and an update of this package was not requested.',
            'fixture/blocker 2.0.0 requires fixture/target ^3.0',
        ]));
        $blockers = (new BlockerGrouper())->group([$result], new EvidenceLedger());

        self::assertCount(1, $blockers);
        self::assertNull($blockers[0]->lockedVersion());
        self::assertSame('^3.0', $blockers[0]->conflict());
        self::assertSame('fixture/blocker', $blockers[0]->blocker());
        self::assertSame(['fixture/blocker', 'fixture/target'], $blockers[0]->dependencyPath());
    }

    private function lock(string $version, string $requirement): ComposerLock
    {
        return new ComposerLock(['packages' => [[
            'name' => 'jc5/google2fa-laravel',
            'version' => $version,
            'require' => ['laravel/framework' => $requirement],
        ]]]);
    }

    private function candidateRequirementResult(): ScenarioResult
    {
        // Candidate-version provenance with a demonstrated incompatible edge, independently of advisory policy.
        $output = implode("\n", [
            'Problem 4',
            '- Root composer.json requires jc5/google2fa-laravel ^2.0 -> satisfiable by jc5/google2fa-laravel[2.0.0, ..., v2.0.14].',
            '- jc5/google2fa-laravel 2.0.10 requires laravel/framework ^11.0|^12.0 -> found laravel/framework[v11.0.0, ..., v12.70.0] but it conflicts with your root composer.json require (^13.0).',
        ]);
        $scenario = new Scenario('candidate-edge', new UpgradeTargetSet([new UpgradeTarget('laravel/framework', '^13.0'), new UpgradeTarget('php', '8.4')]));

        return new ScenarioResult($scenario, 2, '', $output, null, null, ScenarioResult::FAILURE_SOLVER);
    }

    private function result(string $output): ScenarioResult
    {
        $scenario = new Scenario('locked-rule', new UpgradeTargetSet([new UpgradeTarget('fixture/target', '^2.0')]));

        return new ScenarioResult($scenario, 2, '', $output, null, null, ScenarioResult::FAILURE_SOLVER);
    }
}
