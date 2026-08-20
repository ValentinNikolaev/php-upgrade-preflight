<?php

declare(strict_types=1);

namespace PhpUpgradePreflight\Laravel\Tests\Unit;

use PhpUpgradePreflight\Core\Model\Scenario;
use PhpUpgradePreflight\Core\Model\ScenarioResult;
use PhpUpgradePreflight\Core\Model\UpgradeTarget;
use PhpUpgradePreflight\Core\Model\UpgradeTargetSet;
use PhpUpgradePreflight\Core\Progress\AnalysisPhase;
use PhpUpgradePreflight\Core\Progress\AnalysisProgressEvent;
use PhpUpgradePreflight\Laravel\Console\ArtisanAnalysisProgressReporter;
use PHPUnit\Framework\TestCase;
use Symfony\Component\Console\Input\ArrayInput;
use Symfony\Component\Console\Output\BufferedOutput;
use Symfony\Component\Console\Style\SymfonyStyle;

final class ArtisanAnalysisProgressReporterTest extends TestCase
{
    public function testItWritesDurableRawLinesOnlyWhileAttachedToATerminal(): void
    {
        $output = new BufferedOutput();
        $reporter = new ArtisanAnalysisProgressReporter(static fn (): bool => true);

        $reporter->report(AnalysisProgressEvent::analysisStarted());
        $reporter->attach(new SymfonyStyle(new ArrayInput([]), $output));
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
        $reporter = new ArtisanAnalysisProgressReporter(static fn (): bool => false);
        $reporter->attach(new SymfonyStyle(new ArrayInput([]), $output));

        $reporter->report(AnalysisProgressEvent::analysisStarted());

        self::assertSame('', $output->fetch());
    }

    /** @dataProvider scenarioOutcomeProvider */
    public function testItDistinguishesScenarioOutcomeCategories(string $outcome, string $label): void
    {
        $output = new BufferedOutput();
        $reporter = new ArtisanAnalysisProgressReporter(static fn (): bool => true);
        $reporter->attach(new SymfonyStyle(new ArrayInput([]), $output));
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

        self::assertSame(
            sprintf('[%s] Composer scenario: fixture-scenario', $label),
            trim($output->fetch())
        );
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
