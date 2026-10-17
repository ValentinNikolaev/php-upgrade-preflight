<?php

declare(strict_types=1);

namespace PhpUpgradePreflight\Core\Reporting;

use PhpUpgradePreflight\Core\Model\Blocker;
use PhpUpgradePreflight\Core\Model\FrameworkGuidance;
use PhpUpgradePreflight\Core\Model\UpgradeReport;

/** Bounded facts from the report DTOs; never serializes source inventories or scenarios. */
final class SummaryReportProjection
{
    /** @var list<string> */
    private array $lines;

    public function __construct(UpgradeReport $report)
    {
        $staged = $report->stagedResolution();
        $this->lines = [
            'PHP Upgrade Preflight — Summary',
            'Direct target: ' . $report->resolutionStatus(),
        ];
        if ($staged->executionState() === 'evaluated'
            || $staged->stopReason() !== 'stage_target_provider_unavailable'
            || $report->frameworkGuidance() !== []) {
            $this->lines[] = sprintf('Staged path: %s / %s; stop: %s', $staged->executionState(), $staged->status(), $this->stopExplanation($staged->stopReason()));
        }
        if ($report->request()->targetPhp() !== null) {
            $this->lines[] = sprintf(
                'PHP: current %s (%s); target %s (%s)',
                $report->platform()->currentPhp() ?? 'unknown',
                $report->platform()->currentPhpSource(),
                $report->platform()->targetPhp() ?? 'unchanged',
                $report->platform()->targetPhpSource()
            );
        }
        $targets = [];
        foreach (array_slice($report->request()->targets()->all(), 0, 3) as $target) {
            $targets[] = $target->package() . ':' . $target->constraint();
        }
        $this->lines[] = 'Requested targets: ' . implode(', ', $targets)
            . (count($report->request()->targets()) > 3 ? sprintf(' (+%d in full details)', count($report->request()->targets()) - 3) : '');
        $stageStates = [];
        foreach ($staged->stages() as $stage) {
            $stageStates[] = $stage->target()->id() . ': ' . ($stage->resolutionStatus() ?? $stage->executionState());
        }
        if ($stageStates !== []) {
            $this->lines[] = 'Stages: ' . implode('; ', array_slice($stageStates, 0, 6));
        }
        foreach ($report->scenarios() as $scenario) {
            if ($scenario->scenario()->isBaselineValidation() && !$scenario->succeeded()) {
                $this->lines[] = 'Baseline validation failed: ' . $scenario->outcome() . '; repair the existing Composer input before relying on target checks.';
                break;
            }
        }
        foreach ($report->scenarios() as $scenario) {
            if ($scenario->isOperationalFailure()) {
                $this->lines[] = sprintf(
                    'Incomplete Composer check [%s]: %s; inspect full evidence and restore the toolchain/repository access before rerunning.',
                    $scenario->scenario()->name(),
                    $scenario->outcome()
                );
                break;
            }
        }

        $direct = [];
        $advisory = [];
        foreach ($report->blockers() as $blocker) {
            if ($blocker->blocksResolution()) {
                $this->add($direct, $blocker, 'direct', $blocker->evidence());
            } else {
                $this->add($advisory, $blocker, 'direct advisory', $blocker->evidence());
            }
        }
        $active = [];
        foreach ($staged->blockerRegistry() as $entry) {
            if (!$entry->isActive()) {
                continue;
            }
            if ($entry->isBlocking()) {
                $this->add($active, $entry->blocker(), $entry->stageId(), $entry->evidence());
            } else {
                $this->add($advisory, $entry->blocker(), $entry->stageId() . ' advisory', $entry->evidence());
            }
        }
        $this->lines[] = '';
        $this->causes($report, $direct, 2, 'direct blocking');
        // Give each stage a first representative before showing another cause from the same stage.
        $byStage = [];
        $remainder = [];
        foreach ($active as $key => $cause) {
            if (!isset($byStage[$cause->scope()])) {
                $byStage[$cause->scope()] = $cause;
            } else {
                $remainder[$key] = $cause;
            }
        }
        $this->causes($report, array_merge(array_values($byStage), array_values($remainder)), 6, 'active staged blocking');
        $this->causes($report, $advisory, 1, 'advisory (does not block resolution)');
        if ($direct === [] && $active === [] && $advisory === [] && $report->resolutionStatus() === 'unknown') {
            $this->lines[] = 'No dependency cause was established; review the recorded limits before selecting a repair.';
        }

        $checked = 0;
        $checkedShown = 0;
        foreach ($staged->stages() as $stage) {
            foreach ($stage->attempts() as $attempt) {
                if (!$attempt->selected() || !$attempt->scenario()->succeeded()) {
                    continue;
                }
                foreach ($attempt->rootConstraintChanges() as $change) {
                    ++$checked;
                    if ($checkedShown++ < 2) {
                        $this->lines[] = sprintf(
                            'Dependency-checked stage candidate [%s]: %s %s -> %s (evidence: %s).',
                            $stage->target()->id(),
                            $change->package(),
                            $change->fromConstraint() ?? '(absent)',
                            $change->toConstraint() ?? '(removed)',
                            $this->references($change->evidence())
                        );
                    }
                }
            }
        }
        if ($checked > 2) {
            $this->lines[] = sprintf('Additional dependency-checked root changes in full details: %d.', $checked - 2);
        }
        $this->manualWork($report);
        $this->limits($report);
        $this->lines[] = 'Details: --format=json (canonical) or --format=markdown (full report).';
    }

    /** @return list<string> */
    public function lines(): array
    {
        return $this->lines;
    }

    private function stopExplanation(?string $reason): string
    {
        $explanations = [
            'blocking_registry_not_cleared' => 'active dependency blockers remain',
            'stage_target_provider_unavailable' => 'no staged planner is available for this request',
            'project_input_failure' => 'project Composer input could not be assessed',
            'invalid_stage_plan' => 'the adapter could not supply a valid staged plan',
            'hop_budget_exceeded' => 'the requested path exceeds the allowed number of stages',
            'process_budget_exceeded' => 'the requested path exceeds the Composer process limit',
        ];

        return $reason === null ? 'none' : ($explanations[$reason] ?? str_replace('_', ' ', $reason));
    }

    /**
     * @param array<array-key, SummaryCause> $groups
     * @param list<string> $evidence
     */
    private function add(array &$groups, Blocker $blocker, string $scope, array $evidence): void
    {
        $key = SummaryCause::key($blocker, $scope);
        $groups[$key] = $groups[$key] ?? new SummaryCause($blocker, $scope);
        $groups[$key]->observe($evidence);
    }

    /** @param array<array-key, SummaryCause> $groups */
    private function causes(UpgradeReport $report, array $groups, int $limit, string $label): void
    {
        $selected = array_slice(array_values($groups), 0, $limit);
        foreach ($selected as $cause) {
            $blocker = $cause->blocker();
            $package = $blocker->blocker() ?? $blocker->subject();
            $locked = $report->projectState()->composerLock()->package($package);
            $version = $blocker->lockedVersion() ?? ($locked === null ? null : $locked->version() . ' (project lock)');
            $this->lines[] = sprintf(
                '- %s [%s]: %s %s%s; requested %s.',
                $label,
                $cause->scope(),
                $package,
                $version ?? 'version not recorded',
                $blocker->subject() === $package ? '' : '; target ' . $blocker->subject(),
                $blocker->requestedConstraint() ?? 'not recorded'
            );
            $this->lines[] = sprintf(
                '  Evidence: %s; why (%s; %s): %s%s',
                $cause->references(),
                $blocker->type(),
                $locked === null ? 'package provenance not recorded' : 'project lock, ' . ($locked->isDirect() ? 'root dependency' : 'transitive dependency'),
                $blocker->summary(),
                $blocker->conflict() === null ? '' : ' Constraint: ' . $blocker->conflict()
            );
            $this->lines[] = '  Suggested next action (not dependency-checked): '
                . ($blocker->options()[0] ?? 'Inspect the referenced Composer evidence and verify the relevant constraints before selecting a repair.');
        }
        if (count($groups) > count($selected)) {
            $this->lines[] = sprintf('Additional %s groups in full details: %d (%d of %d shown).', $label, count($groups) - count($selected), count($selected), count($groups));
        }
    }

    private function manualWork(UpgradeReport $report): void
    {
        $total = count($report->actionableSourceImpact()) + count($report->stagedResolution()->sourceImpact()) + count($report->frameworkFindings());
        if ($total === 0) {
            return;
        }
        $this->lines[] = '';
        $this->lines[] = sprintf(
            'Manual work: %d direct source groups, %d staged source groups, %d framework findings; original source snapshot only.',
            count($report->actionableSourceImpact()),
            count($report->stagedResolution()->sourceImpact()),
            count($report->frameworkFindings())
        );
        $sourceFindings = $report->actionableSourceImpact() !== [] ? $report->actionableSourceImpact() : $report->stagedResolution()->sourceImpact();
        $sourceLimit = $report->frameworkFindings() === [] ? 2 : 1;
        $sourceSelection = array_slice($sourceFindings, 0, $sourceLimit);
        foreach ($sourceSelection as $finding) {
            $location = $finding->occurrences()[0];
            $this->lines[] = sprintf(
                '- Source review %s:%s [%s, %s; evidence: %s]: %s',
                $location->file(),
                $location->line() ?? '?',
                $finding->affectedPackage() ?? 'unknown ownership',
                $finding->severity(),
                $this->references($finding->evidence()),
                $finding->reason()
            );
        }
        $frameworkSelection = array_slice($report->frameworkFindings(), 0, 2 - count($sourceSelection));
        foreach ($frameworkSelection as $finding) {
            $this->lines[] = sprintf('- Framework review [%s, %s; evidence: %s]: %s', $finding->framework(), $finding->severity(), $this->references($finding->evidence()), $finding->summary());
        }
        $hidden = $total - count($sourceSelection) - count($frameworkSelection);
        if ($hidden > 0) {
            $this->lines[] = sprintf('Additional manual-review entries in full details: %d.', $hidden);
        }
    }

    private function limits(UpgradeReport $report): void
    {
        $this->lines[] = '';
        $this->lines[] = 'Not checked: application runtime compatibility; review source/configuration changes and run application tests.';
        $coverage = [];
        foreach ($report->frameworkGuidance() as $guidance) {
            if ($guidance->status() !== FrameworkGuidance::SUPPORTED) {
                $coverage[$guidance->status()] = ($coverage[$guidance->status()] ?? 0) + 1;
            }
        }
        ksort($coverage, SORT_STRING);
        $coverageLabels = [];
        foreach ($coverage as $status => $count) {
            $coverageLabels[] = $status . ': ' . $count;
        }
        if ($coverageLabels !== []) {
            $this->lines[] = 'Incomplete framework guidance: ' . implode('; ', $coverageLabels) . '; inspect full guidance before proceeding.';
        }
        $limits = new SummaryUncertainties($report->uncertainties());
        foreach ($limits->lines() as $line) {
            $this->lines[] = $line;
        }
    }

    /** @param list<string> $references */
    private function references(array $references): string
    {
        return implode(', ', array_slice($references, 0, 3)) . (count($references) > 3 ? sprintf(' (+%d)', count($references) - 3) : '');
    }
}
