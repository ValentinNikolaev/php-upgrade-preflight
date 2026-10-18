<?php

declare(strict_types=1);

namespace PhpUpgradePreflight\Core\Tests\Unit\Progress;

use PhpUpgradePreflight\Core\Model\Blocker;
use PhpUpgradePreflight\Core\Model\BlockerType;
use PhpUpgradePreflight\Core\Model\ComposerJson;
use PhpUpgradePreflight\Core\Model\ComposerLock;
use PhpUpgradePreflight\Core\Model\Evidence;
use PhpUpgradePreflight\Core\Model\EvidenceLedger;
use PhpUpgradePreflight\Core\Model\ProjectState;
use PhpUpgradePreflight\Core\Model\UpgradeRequest;
use PhpUpgradePreflight\Core\Model\UpgradeTarget;
use PhpUpgradePreflight\Core\Progress\AnalysisProgressEvent;
use PhpUpgradePreflight\Core\Progress\DirectFinding;
use PHPUnit\Framework\TestCase;

final class DirectFindingTest extends TestCase
{
    public function testItShowsOneEvidencedCauseAndAnUnverifiedNextStep(): void
    {
        $evidence = new EvidenceLedger();
        $reference = $evidence->add('solver', Evidence::E1_SOLVER, 'Observed constraint conflict.')->id();
        $finding = DirectFinding::first([$this->blocker([$reference]), $this->blocker([$reference])], $evidence, $this->project(), $this->request());
        self::assertNotNull($finding);
        self::assertSame([
            '[finding] A direct Composer attempt reported: vendor/extension 1.0.0. A transitive constraint conflicts with the target.',
            '[next] Review a compatible vendor/extension version. Repair not verified.',
            '[evidence] solver-1',
            '[pending] Preliminary finding; remaining checks and the final result are still pending.',
        ], $finding->lines());
        $event = AnalysisProgressEvent::directFinding($finding);
        self::assertSame(AnalysisProgressEvent::DIRECT_FINDING, $event->type());
        self::assertSame($finding, $event->finding());
        self::assertNull($event->outcome());
        self::assertNull(AnalysisProgressEvent::analysisStarted()->finding());
    }

    /**
     * @dataProvider unsupportedFindings
     * @param list<string> $references
     * @param list<string> $options
     */
    public function testItDoesNotPromoteUnsupportedOrUnverifiedCauses(string $type, string $confidence, array $references, array $options): void
    {
        $blocker = new Blocker($type, 'vendor/package', 'Not a confirmed explanation.', $confidence, $references, options: $options);
        self::assertNull(DirectFinding::first([$blocker], new EvidenceLedger(), $this->project(), $this->request()));
        self::assertNull(DirectFinding::first([], new EvidenceLedger(), $this->project(), $this->request()));
    }

    /** @return array<string, array{string, string, list<string>, list<string>}> */
    public function unsupportedFindings(): array
    {
        return [
            'unknown type' => ['custom-type', 'high', ['missing'], ['Review.']],
            'advisory' => [BlockerType::ABANDONED_PACKAGE, 'high', ['missing'], ['Review.']],
            'unclassified failure' => [BlockerType::UNKNOWN_COMPOSER_FAILURE, 'high', ['missing'], ['Review.']],
            'qualified cause' => [BlockerType::TRANSITIVE_PACKAGE_CONFLICT, 'medium', ['missing'], ['Review.']],
            'no evidence' => [BlockerType::TRANSITIVE_PACKAGE_CONFLICT, 'high', [], ['Review.']],
            'no action' => [BlockerType::TRANSITIVE_PACKAGE_CONFLICT, 'high', ['missing'], []],
            'dangling evidence' => [BlockerType::TRANSITIVE_PACKAGE_CONFLICT, 'high', ['missing'], ['Review.']],
        ];
    }

    public function testItRedactsWholeValuesBeforeBoundingAndRemovesTerminalControls(): void
    {
        $evidence = new EvidenceLedger();
        $reference = $evidence->add('solver', Evidence::E1_SOLVER, 'Constraint conflict.')->id();
        $blocker = new Blocker(
            BlockerType::ROOT_CONSTRAINT_CONFLICT,
            $this->request()->projectPath() . '/private-package',
            "Conflict\n\x1b[31m " . str_repeat('x', 500) . ' https://alice:sample-password@example.test/repo',
            'high',
            [$reference],
            options: ['Inspect ' . $this->request()->projectPath() . '/composer.json then https://alice:sample-password@example.test/repo']
        );
        $finding = DirectFinding::first([$blocker], $evidence, $this->project(), $this->request());
        self::assertNotNull($finding);
        $text = implode("\n", $finding->lines());
        self::assertStringNotContainsString($this->request()->projectPath(), $text);
        self::assertStringNotContainsString('sample-password', $text);
        self::assertStringNotContainsString("\x1b", $text);
        self::assertStringContainsString('[PROJECT_ROOT]', $text);
        foreach ($finding->lines() as $line) {
            self::assertLessThanOrEqual(320, strlen($line));
            self::assertStringNotContainsString("\n", $line);
        }
    }

    /** @param list<string> $references */
    private function blocker(array $references): Blocker
    {
        return new Blocker(
            BlockerType::TRANSITIVE_PACKAGE_CONFLICT,
            'vendor/package',
            'A transitive constraint conflicts with the target.',
            'high',
            $references,
            blocker: 'vendor/extension',
            lockedVersion: '1.0.0',
            options: ['Review a compatible vendor/extension version.']
        );
    }

    private function request(): UpgradeRequest
    {
        return new UpgradeRequest(dirname(__DIR__, 5), [new UpgradeTarget('vendor/package', '^2.0')]);
    }

    private function project(): ProjectState
    {
        return new ProjectState($this->request()->projectPath(), new ComposerJson([]), new ComposerLock([]));
    }
}
