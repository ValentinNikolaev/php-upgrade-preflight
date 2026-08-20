<?php

declare(strict_types=1);

namespace PhpUpgradePreflight\Cli\Tests\Unit;

use PhpUpgradePreflight\Cli\TerminalAnalysisProgressReporter;
use PhpUpgradePreflight\Core\Model\ComposerJson;
use PhpUpgradePreflight\Core\Model\ComposerLock;
use PhpUpgradePreflight\Core\Model\EffortEstimate;
use PhpUpgradePreflight\Core\Model\LockDiff;
use PhpUpgradePreflight\Core\Model\ProjectState;
use PhpUpgradePreflight\Core\Model\RiskSummary;
use PhpUpgradePreflight\Core\Model\Scenario;
use PhpUpgradePreflight\Core\Model\ScenarioResult;
use PhpUpgradePreflight\Core\Model\UpgradeReport;
use PhpUpgradePreflight\Core\Model\UpgradeRequest;
use PhpUpgradePreflight\Core\Model\UpgradeTarget;
use PhpUpgradePreflight\Core\Model\UpgradeTargetSet;
use PhpUpgradePreflight\Core\Progress\AnalysisPhase;
use PhpUpgradePreflight\Core\Progress\AnalysisProgressEvent;
use PHPUnit\Framework\TestCase;

final class TerminalAnalysisProgressReporterTest extends TestCase
{
    public function testItRendersDurableProgressLinesForATerminal(): void
    {
        $stderr = fopen('php://memory', 'w+');
        self::assertIsResource($stderr);
        $reporter = new TerminalAnalysisProgressReporter($stderr, static fn ($stream): bool => true);

        $reporter->report(AnalysisProgressEvent::analysisStarted());
        $reporter->report(AnalysisProgressEvent::phaseStarted(AnalysisPhase::SOURCE_SCAN));
        $reporter->report(AnalysisProgressEvent::phaseCompleted(AnalysisPhase::SOURCE_SCAN));

        rewind($stderr);
        $contents = stream_get_contents($stderr);
        fclose($stderr);
        self::assertIsString($contents);
        self::assertSame(
            "[working] Analysis started\n"
            . "[working] Scanning application source\n"
            . "[done] Scanning application source\n",
            str_replace("\r\n", "\n", $contents)
        );
    }

    public function testItStaysSilentWhenDiagnosticsAreRedirected(): void
    {
        $stderr = fopen('php://memory', 'w+');
        self::assertIsResource($stderr);
        $reporter = new TerminalAnalysisProgressReporter($stderr, static fn ($stream): bool => false);

        $reporter->report(AnalysisProgressEvent::analysisStarted());

        rewind($stderr);
        $contents = stream_get_contents($stderr);
        fclose($stderr);
        self::assertSame('', $contents);
    }

    public function testItRendersAnalysisAndScenarioLifecycleLines(): void
    {
        $stderr = fopen('php://memory', 'w+');
        self::assertIsResource($stderr);
        $reporter = new TerminalAnalysisProgressReporter($stderr, static fn ($stream): bool => true);
        $scenario = $this->scenario();

        $reporter->report(AnalysisProgressEvent::analysisCompleted($this->report()));
        $reporter->report(AnalysisProgressEvent::analysisFailed());
        $reporter->report(AnalysisProgressEvent::scenarioStarted($scenario));
        $reporter->report(AnalysisProgressEvent::scenarioCompleted(
            $this->scenarioResult(ScenarioResult::OUTCOME_SUCCESS)
        ));

        rewind($stderr);
        $contents = stream_get_contents($stderr);
        fclose($stderr);
        self::assertSame(
            "[done] Analysis complete: unknown\n"
            . "[failed] Analysis stopped\n"
            . "[working] Composer scenario: fixture-scenario\n"
            . "[done] Composer scenario: fixture-scenario\n",
            str_replace("\r\n", "\n", (string) $contents)
        );
    }

    public function testItRendersEveryPhaseAndBothCompletionStatuses(): void
    {
        $stderr = fopen('php://memory', 'w+');
        self::assertIsResource($stderr);
        $reporter = new TerminalAnalysisProgressReporter($stderr, static fn ($stream): bool => true);
        $labels = [
            AnalysisPhase::PROJECT_LOADING => 'Loading project metadata',
            AnalysisPhase::COMPOSER_FEASIBILITY => 'Checking Composer feasibility',
            AnalysisPhase::STAGED_RESOLUTION => 'Checking staged upgrade paths',
            AnalysisPhase::SOURCE_SCAN => 'Scanning application source',
            AnalysisPhase::FRAMEWORK_EVALUATION => 'Evaluating framework rules',
            AnalysisPhase::REPORT_ASSEMBLY => 'Building report',
        ];

        foreach ($labels as $phase => $label) {
            $reporter->report(AnalysisProgressEvent::phaseStarted($phase));
            $reporter->report(AnalysisProgressEvent::phaseCompleted($phase));
            $reporter->report(AnalysisProgressEvent::phaseCompleted(
                $phase,
                AnalysisProgressEvent::STATUS_FAILED
            ));
        }

        rewind($stderr);
        $contents = str_replace("\r\n", "\n", (string) stream_get_contents($stderr));
        fclose($stderr);
        foreach ($labels as $label) {
            self::assertStringContainsString("[working] {$label}\n", $contents);
            self::assertStringContainsString("[done] {$label}\n", $contents);
            self::assertStringContainsString("[failed] {$label}\n", $contents);
        }
    }

    public function testDefaultTerminalDetectionIsSafeForANonTerminalStream(): void
    {
        $stderr = fopen('php://memory', 'w+');
        self::assertIsResource($stderr);
        $reporter = new TerminalAnalysisProgressReporter($stderr);

        $reporter->report(AnalysisProgressEvent::analysisStarted());

        rewind($stderr);
        self::assertSame('', stream_get_contents($stderr));
        fclose($stderr);
    }

    public function testTerminalDetectionAndClosedStreamFailuresNeverEscape(): void
    {
        $stderr = fopen('php://memory', 'w+');
        self::assertIsResource($stderr);
        $detectorFailure = new TerminalAnalysisProgressReporter(
            $stderr,
            static function ($stream): bool {
                throw new \RuntimeException('terminal detection failed');
            }
        );

        $detectorFailure->report(AnalysisProgressEvent::analysisStarted());
        fclose($stderr);

        $writeFailure = new TerminalAnalysisProgressReporter($stderr, static fn ($stream): bool => true);
        $writeFailure->report(AnalysisProgressEvent::analysisStarted());

        self::addToAssertionCount(1);
    }

    public function testItIgnoresEventsWithoutARenderableMessage(): void
    {
        $stderr = fopen('php://memory', 'w+');
        self::assertIsResource($stderr);
        $reporter = new TerminalAnalysisProgressReporter($stderr, static fn ($stream): bool => true);

        $reporter->report($this->rawEvent('future-event'));
        $reporter->report($this->rawEvent('future-event', AnalysisPhase::SOURCE_SCAN));

        rewind($stderr);
        self::assertSame('', stream_get_contents($stderr));
        fclose($stderr);
    }

    /** @dataProvider scenarioOutcomeProvider */
    public function testItDistinguishesScenarioOutcomeCategories(string $outcome, string $label): void
    {
        $stderr = fopen('php://memory', 'w+');
        self::assertIsResource($stderr);
        $reporter = new TerminalAnalysisProgressReporter($stderr, static fn ($stream): bool => true);
        $result = $this->scenarioResult($outcome);

        $reporter->report(AnalysisProgressEvent::scenarioCompleted($result));

        rewind($stderr);
        $contents = stream_get_contents($stderr);
        fclose($stderr);
        self::assertSame(sprintf('[%s] Composer scenario: fixture-scenario', $label), trim((string) $contents));
    }

    /** @return list<array{string, string}> */
    public function scenarioOutcomeProvider(): array
    {
        return [
            [ScenarioResult::OUTCOME_SUCCESS, 'done'],
            [ScenarioResult::OUTCOME_SOLVER_FAILURE, 'blocked'],
            [ScenarioResult::OUTCOME_VALIDATION_FAILURE, 'invalid'],
            [ScenarioResult::OUTCOME_COMPOSER_MISSING, 'failed'],
            [ScenarioResult::OUTCOME_INVALID_JSON, 'invalid'],
            [ScenarioResult::OUTCOME_LOCKFILE_MISSING, 'invalid'],
            [ScenarioResult::OUTCOME_TIMEOUT, 'timed-out'],
            [ScenarioResult::OUTCOME_REPOSITORY_METADATA_UNAVAILABLE, 'unverified'],
            [ScenarioResult::OUTCOME_PROCESS_FAILURE, 'failed'],
            [ScenarioResult::OUTCOME_CLEANUP_FAILURE, 'failed'],
            [ScenarioResult::OUTCOME_WORKSPACE_FAILURE, 'failed'],
        ];
    }

    private function scenario(): Scenario
    {
        return new Scenario(
            'fixture-scenario',
            new UpgradeTargetSet([new UpgradeTarget('vendor/package', '^2.0')])
        );
    }

    private function scenarioResult(string $outcome): ScenarioResult
    {
        $successful = $outcome === ScenarioResult::OUTCOME_SUCCESS;
        $failureType = null;
        if (!$successful) {
            $failureType = $outcome === ScenarioResult::OUTCOME_SOLVER_FAILURE
                ? ScenarioResult::FAILURE_SOLVER
                : ($outcome === ScenarioResult::OUTCOME_VALIDATION_FAILURE
                    ? ScenarioResult::FAILURE_VALIDATION
                    : ScenarioResult::FAILURE_OPERATIONAL);
        }

        return new ScenarioResult(
            $this->scenario(),
            $successful ? 0 : 1,
            '',
            '',
            $successful ? new ComposerLock([]) : null,
            null,
            $failureType,
            null,
            [],
            0,
            null,
            [],
            $outcome
        );
    }

    private function report(): UpgradeReport
    {
        $request = new UpgradeRequest(
            dirname(__DIR__, 5),
            [new UpgradeTarget('vendor/package', '^2.0')]
        );

        return new UpgradeReport(
            $request,
            new ProjectState($request->projectPath(), new ComposerJson([]), new ComposerLock([])),
            [],
            new LockDiff([]),
            [],
            [],
            [],
            new RiskSummary('low', []),
            new EffortEstimate([0, 0], 'high', [], []),
            [],
            []
        );
    }

    private function rawEvent(string $type, ?string $phase = null): AnalysisProgressEvent
    {
        $reflection = new \ReflectionClass(AnalysisProgressEvent::class);
        $constructor = $reflection->getConstructor();
        self::assertNotNull($constructor);
        $event = $reflection->newInstanceWithoutConstructor();
        $constructor->setAccessible(true);
        $constructor->invoke($event, $type, $phase);

        return $event;
    }
}
