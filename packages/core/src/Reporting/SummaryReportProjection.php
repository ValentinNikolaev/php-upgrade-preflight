<?php

declare(strict_types=1);

namespace PhpUpgradePreflight\Core\Reporting;

use PhpUpgradePreflight\Core\Model\Blocker;
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
            sprintf('Staged path: %s / %s; stop: %s', $staged->executionState(), $staged->status(), $staged->stopReason() ?? 'none'),
            'Dependency resolution does not prove application compatibility.',
            sprintf(
                'PHP: current %s (%s); target %s (%s)',
                $report->platform()->currentPhp() ?? 'unknown',
                $report->platform()->currentPhpSource(),
                $report->platform()->targetPhp() ?? 'unchanged',
                $report->platform()->targetPhpSource()
            ),
        ];
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
        $this->lines[] = 'Dependency causes (suggestions require a new Composer check):';
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
        if ($direct === [] && $active === [] && $advisory === []) {
            $this->lines[] = $report->resolutionStatus() === 'unknown'
                ? 'No dependency causes recorded; an unknown result is not a successful check.'
                : 'No dependency causes recorded.';
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
        $this->lines[] = sprintf('Selected successful stage candidates contain %d checked root changes; application/source repairs remain unverified.', $checked);
        $this->manualWork($report);
        $this->limits($report);
        $this->lines[] = '';
        $this->lines[] = 'Full causes, source locations, evidence and transcripts: --format=json or --format=markdown.';
        $this->lines[] = 'Next: review the causes and limits, change only your own upgrade workspace, rerun analysis and application tests.';
    }

    /** @return list<string> */
    public function lines(): array
    {
        return $this->lines;
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
            $this->lines[] = sprintf(
                '- %s [%s]: %s %s; subject %s; requested %s; %d observation(s).',
                $label,
                $cause->scope(),
                $package,
                $blocker->lockedVersion() ?? 'version not recorded',
                $blocker->subject(),
                $blocker->requestedConstraint() ?? 'not recorded',
                $cause->count()
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
        $this->lines[] = sprintf('%s groups: %d shown of %d; %d additional in full details.', $label, count($selected), count($groups), count($groups) - count($selected));
    }

    private function manualWork(UpgradeReport $report): void
    {
        $this->lines[] = '';
        $this->lines[] = sprintf(
            'Manual work: %d direct source groups, %d staged source groups, %d framework findings; original source snapshot only.',
            count($report->actionableSourceImpact()),
            count($report->stagedResolution()->sourceImpact()),
            count($report->frameworkFindings())
        );
        $shown = 0;
        $sourceFindings = $report->actionableSourceImpact() !== [] ? $report->actionableSourceImpact() : $report->stagedResolution()->sourceImpact();
        foreach ($sourceFindings as $finding) {
            if ($shown++ >= 2) {
                break;
            }
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
        foreach ($report->frameworkFindings() as $finding) {
            if ($shown++ >= 2) {
                break;
            }
            $this->lines[] = sprintf('- Framework review [%s, %s; evidence: %s]: %s', $finding->framework(), $finding->severity(), $this->references($finding->evidence()), $finding->summary());
        }
        $this->lines[] = 'Review source locations and maintainer guidance in full details; dependency candidates do not apply source/configuration edits.';
    }

    private function limits(UpgradeReport $report): void
    {
        $this->lines[] = '';
        $this->lines[] = sprintf('Coverage and unknowns: %d recorded uncertainties; source inventory is omitted from this summary.', count($report->uncertainties()));
        $this->lines[] = 'Source scanning is static and may be incomplete; zero findings does not establish compatibility.';
        $coverage = [];
        foreach ($report->frameworkGuidance() as $guidance) {
            $coverage[$guidance->status()] = ($coverage[$guidance->status()] ?? 0) + 1;
        }
        ksort($coverage, SORT_STRING);
        $coverageLabels = [];
        foreach ($coverage as $status => $count) {
            $coverageLabels[] = $status . ': ' . $count;
        }
        $this->lines[] = 'Framework coverage: ' . ($coverageLabels === [] ? 'none recorded' : implode('; ', $coverageLabels)) . '.';
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
