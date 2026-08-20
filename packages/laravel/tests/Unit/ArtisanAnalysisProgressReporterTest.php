<?php

declare(strict_types=1);

namespace PhpUpgradePreflight\Laravel\Tests\Unit;

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
use PhpUpgradePreflight\Laravel\Console\ArtisanAnalysisProgressReporter;
use PHPUnit\Framework\TestCase;
use Symfony\Component\Console\Input\ArrayInput;
use Symfony\Component\Console\Output\BufferedOutput;
use Symfony\Component\Console\Output\OutputInterface;
use Symfony\Component\Console\Output\StreamOutput;
use Symfony\Component\Console\Style\SymfonyStyle;

final class ArtisanAnalysisProgressReporterTest extends TestCase
{
    public function testItWritesDurableRawLinesOnlyWhileAttachedToATerminal(): void
    {
        $output = new BufferedOutput();
        $reporter = new ArtisanAnalysisProgressReporter(static fn (OutputInterface $output): bool => true);

        $reporter->report(AnalysisProgressEvent::analysisStarted());
        $reporter->attach(new SymfonyStyle(new ArrayInput([]), $output), $output);
        $reporter->report(AnalysisProgressEvent::analysisStarted());
        $reporter->report(AnalysisProgressEvent::phaseStarted(AnalysisPhase::SOURCE_SCAN));
        $reporter->detach();
        $reporter->report(AnalysisProgressEvent::analysisFailed());

        self::assertSame(
            "[working] Analysis started\n[working] Scanning application source\n",
            str_replace("\r\n", "\n", $output->fetch())
        );
    }

    public function testItStaysSilentForRedirectedDiagnostics(): void
    {
        $output = new BufferedOutput();
        $reporter = new ArtisanAnalysisProgressReporter(static fn (OutputInterface $output): bool => false);
        $reporter->attach(new SymfonyStyle(new ArrayInput([]), $output), $output);

        $reporter->report(AnalysisProgressEvent::analysisStarted());

        self::assertSame('', $output->fetch());
    }

    public function testItRendersAnalysisAndScenarioLifecycleLines(): void
    {
        $output = new BufferedOutput();
        $reporter = new ArtisanAnalysisProgressReporter(static fn (OutputInterface $output): bool => true);
        $reporter->attach(new SymfonyStyle(new ArrayInput([]), $output), $output);

        $reporter->report(AnalysisProgressEvent::analysisCompleted($this->report()));
        $reporter->report(AnalysisProgressEvent::analysisFailed());
        $reporter->report(AnalysisProgressEvent::scenarioStarted($this->scenario()));
        $reporter->report(AnalysisProgressEvent::scenarioCompleted(
            $this->scenarioResult(ScenarioResult::OUTCOME_SUCCESS)
        ));

        self::assertSame(
            "[done] Analysis complete: unknown\n"
            . "[failed] Analysis stopped\n"
            . "[working] Composer scenario: fixture-scenario\n"
            . "[done] Composer scenario: fixture-scenario\n",
            str_replace("\r\n", "\n", $output->fetch())
        );
    }

    public function testItRendersEveryPhaseAndBothCompletionStatuses(): void
    {
        $output = new BufferedOutput();
        $reporter = new ArtisanAnalysisProgressReporter(static fn (OutputInterface $output): bool => true);
        $reporter->attach(new SymfonyStyle(new ArrayInput([]), $output), $output);
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

        $contents = str_replace("\r\n", "\n", $output->fetch());
        foreach ($labels as $label) {
            self::assertStringContainsString("[working] {$label}\n", $contents);
            self::assertStringContainsString("[done] {$label}\n", $contents);
            self::assertStringContainsString("[failed] {$label}\n", $contents);
        }
    }

    public function testDefaultTerminalDetectionIsSafe(): void
    {
        $output = new BufferedOutput();
        $reporter = new ArtisanAnalysisProgressReporter();
        $reporter->attach(new SymfonyStyle(new ArrayInput([]), $output), $output);

        $reporter->report(AnalysisProgressEvent::analysisStarted());

        self::assertSame('', $output->fetch());
    }

    public function testDefaultTerminalDetectionInspectsTheAttachedOutput(): void
    {
        $stream = fopen('php://memory', 'w+');
        self::assertIsResource($stream);
        $output = new StreamOutput($stream);
        $output->setDecorated(true);
        $reporter = new ArtisanAnalysisProgressReporter();
        $style = new SymfonyStyle(new ArrayInput([]), $output);
        $reporter->attach($style, $style);

        $reporter->report(AnalysisProgressEvent::analysisStarted());

        rewind($stream);
        self::assertSame(
            "[working] Analysis started\n",
            str_replace("\r\n", "\n", (string) stream_get_contents($stream))
        );
        fclose($stream);
    }

    public function testAttachWithoutAnOutputObjectStaysConservativelySilent(): void
    {
        $output = new BufferedOutput();
        $reporter = new ArtisanAnalysisProgressReporter(
            static fn (OutputInterface $output): bool => true
        );
        $reporter->attach(new SymfonyStyle(new ArrayInput([]), $output));

        $reporter->report(AnalysisProgressEvent::analysisStarted());

        self::assertSame('', $output->fetch());
    }

    public function testTerminalDetectionAndOutputFailuresNeverEscape(): void
    {
        $output = new BufferedOutput();
        $detectorFailure = new ArtisanAnalysisProgressReporter(static function (OutputInterface $output): bool {
            throw new \RuntimeException('terminal detection failed');
        });
        $detectorFailure->attach(new SymfonyStyle(new ArrayInput([]), $output), $output);
        $detectorFailure->report(AnalysisProgressEvent::analysisStarted());

        $throwingOutput = new class () extends BufferedOutput {
            protected function doWrite(string $message, bool $newline): void
            {
                throw new \RuntimeException('diagnostic write failed');
            }
        };
        $writeFailure = new ArtisanAnalysisProgressReporter(static fn (OutputInterface $output): bool => true);
        $writeFailure->attach(new SymfonyStyle(new ArrayInput([]), $throwingOutput), $throwingOutput);
        $writeFailure->report(AnalysisProgressEvent::analysisStarted());

        self::assertSame('', $output->fetch());
    }

    public function testItIgnoresEventsWithoutARenderableMessage(): void
    {
        $output = new BufferedOutput();
        $reporter = new ArtisanAnalysisProgressReporter(static fn (OutputInterface $output): bool => true);
        $reporter->attach(new SymfonyStyle(new ArrayInput([]), $output), $output);

        $reporter->report($this->rawEvent('future-event'));
        $reporter->report($this->rawEvent('future-event', AnalysisPhase::SOURCE_SCAN));

        self::assertSame('', $output->fetch());
    }

    /** @dataProvider scenarioOutcomeProvider */
    public function testItDistinguishesScenarioOutcomeCategories(string $outcome, string $label): void
    {
        $output = new BufferedOutput();
        $reporter = new ArtisanAnalysisProgressReporter(static fn (OutputInterface $output): bool => true);
        $reporter->attach(new SymfonyStyle(new ArrayInput([]), $output), $output);
        $result = $this->scenarioResult($outcome);

        $reporter->report(AnalysisProgressEvent::scenarioCompleted($result));

        self::assertSame(
            sprintf('[%s] Composer scenario: fixture-scenario', $label),
            trim($output->fetch())
        );
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
