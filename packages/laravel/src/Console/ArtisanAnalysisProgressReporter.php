<?php

declare(strict_types=1);

namespace PhpUpgradePreflight\Laravel\Console;

use PhpUpgradePreflight\Core\Model\ScenarioResult;
use PhpUpgradePreflight\Core\Progress\AnalysisPhase;
use PhpUpgradePreflight\Core\Progress\AnalysisProgressEvent;
use PhpUpgradePreflight\Core\Progress\AnalysisProgressReporter;
use Symfony\Component\Console\Output\OutputInterface;
use Symfony\Component\Console\Style\SymfonyStyle;

final class ArtisanAnalysisProgressReporter implements AnalysisProgressReporter
{
    private ?SymfonyStyle $stderr = null;
    private ?OutputInterface $errorOutput = null;
    /** @var \Closure(OutputInterface): bool */
    private \Closure $isTerminal;

    /** @param callable(OutputInterface): bool|null $isTerminal */
    public function __construct(?callable $isTerminal = null)
    {
        $this->isTerminal = $isTerminal === null
            ? static function (OutputInterface $output): bool {
                return $output->isDecorated();
            }
        : \Closure::fromCallable($isTerminal);
    }

    public function attach(SymfonyStyle $stderr, ?OutputInterface $errorOutput = null): void
    {
        $this->stderr = $stderr;
        $this->errorOutput = $errorOutput;
    }

    public function detach(): void
    {
        $this->stderr = null;
        $this->errorOutput = null;
    }

    public function report(AnalysisProgressEvent $event): void
    {
        try {
            if (
                $this->stderr === null
                || $this->errorOutput === null
                || !(($this->isTerminal)($this->errorOutput))
            ) {
                return;
            }

            $message = $this->message($event);
            if ($message !== null) {
                $this->stderr->writeln($message, OutputInterface::OUTPUT_RAW);
            }
        } catch (\Throwable) {
            // Progress is observational and must never change analysis behavior.
        }
    }

    private function message(AnalysisProgressEvent $event): ?string
    {
        if ($event->type() === AnalysisProgressEvent::ANALYSIS_STARTED) {
            return '[working] Analysis started';
        }
        if ($event->type() === AnalysisProgressEvent::ANALYSIS_COMPLETED) {
            return sprintf('[done] Analysis complete: %s', $event->outcome() ?? 'report ready');
        }
        if ($event->type() === AnalysisProgressEvent::ANALYSIS_FAILED) {
            return '[failed] Analysis stopped';
        }
        if ($event->type() === AnalysisProgressEvent::SCENARIO_STARTED) {
            return sprintf('[working] Composer scenario: %s', $event->scenario() ?? 'unknown');
        }
        if ($event->type() === AnalysisProgressEvent::SCENARIO_COMPLETED) {
            return sprintf(
                '[%s] Composer scenario: %s',
                $this->scenarioStatus($event),
                $event->scenario() ?? 'unknown'
            );
        }
        if ($event->phase() === null) {
            return null;
        }

        $label = $this->phaseLabel($event->phase());
        if ($event->type() === AnalysisProgressEvent::PHASE_STARTED) {
            return '[working] ' . $label;
        }
        if ($event->type() === AnalysisProgressEvent::PHASE_COMPLETED) {
            return sprintf(
                '[%s] %s',
                $event->status() === AnalysisProgressEvent::STATUS_SUCCEEDED ? 'done' : 'failed',
                $label
            );
        }

        return null;
    }

    private function scenarioStatus(AnalysisProgressEvent $event): string
    {
        if ($event->status() === AnalysisProgressEvent::STATUS_SUCCEEDED) {
            return 'done';
        }
        if ($event->outcome() === ScenarioResult::OUTCOME_SOLVER_FAILURE) {
            return 'blocked';
        }
        if (in_array($event->outcome(), [
            ScenarioResult::OUTCOME_VALIDATION_FAILURE,
            ScenarioResult::OUTCOME_INVALID_JSON,
            ScenarioResult::OUTCOME_LOCKFILE_MISSING,
        ], true)) {
            return 'invalid';
        }
        if ($event->outcome() === ScenarioResult::OUTCOME_TIMEOUT) {
            return 'timed-out';
        }
        if ($event->outcome() === ScenarioResult::OUTCOME_REPOSITORY_METADATA_UNAVAILABLE) {
            return 'unverified';
        }

        return 'failed';
    }

    private function phaseLabel(string $phase): string
    {
        $labels = [
            AnalysisPhase::PROJECT_LOADING => 'Loading project metadata',
            AnalysisPhase::COMPOSER_FEASIBILITY => 'Checking Composer feasibility',
            AnalysisPhase::STAGED_RESOLUTION => 'Checking staged upgrade paths',
            AnalysisPhase::SOURCE_SCAN => 'Scanning application source',
            AnalysisPhase::FRAMEWORK_EVALUATION => 'Evaluating framework rules',
            AnalysisPhase::REPORT_ASSEMBLY => 'Building report',
        ];

        return $labels[$phase] ?? $phase;
    }
}
