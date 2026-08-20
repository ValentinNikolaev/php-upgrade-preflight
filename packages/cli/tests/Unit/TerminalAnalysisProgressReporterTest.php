<?php

declare(strict_types=1);

namespace PhpUpgradePreflight\Cli\Tests\Unit;

use PhpUpgradePreflight\Cli\TerminalAnalysisProgressReporter;
use PhpUpgradePreflight\Core\Model\Scenario;
use PhpUpgradePreflight\Core\Model\ScenarioResult;
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

    /** @dataProvider scenarioOutcomeProvider */
    public function testItDistinguishesScenarioOutcomeCategories(string $outcome, string $label): void
    {
        $stderr = fopen('php://memory', 'w+');
        self::assertIsResource($stderr);
        $reporter = new TerminalAnalysisProgressReporter($stderr, static fn ($stream): bool => true);
        $scenario = new Scenario(
            'fixture-scenario',
            new UpgradeTargetSet([new UpgradeTarget('vendor/package', '^2.0')])
        );
        $failureType = $outcome === ScenarioResult::OUTCOME_SOLVER_FAILURE
            ? ScenarioResult::FAILURE_SOLVER
            : ($outcome === ScenarioResult::OUTCOME_VALIDATION_FAILURE
                ? ScenarioResult::FAILURE_VALIDATION
                : ScenarioResult::FAILURE_OPERATIONAL);
        $result = new ScenarioResult(
            $scenario,
            1,
            '',
            '',
            null,
            null,
            $failureType,
            null,
            [],
            0,
            null,
            [],
            $outcome
        );

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
            [ScenarioResult::OUTCOME_SOLVER_FAILURE, 'blocked'],
            [ScenarioResult::OUTCOME_VALIDATION_FAILURE, 'invalid'],
            [ScenarioResult::OUTCOME_INVALID_JSON, 'invalid'],
            [ScenarioResult::OUTCOME_TIMEOUT, 'timed-out'],
            [ScenarioResult::OUTCOME_REPOSITORY_METADATA_UNAVAILABLE, 'unverified'],
            [ScenarioResult::OUTCOME_PROCESS_FAILURE, 'failed'],
        ];
    }
}
