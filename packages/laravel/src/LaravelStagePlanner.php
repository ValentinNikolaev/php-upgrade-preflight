<?php

declare(strict_types=1);

namespace PhpUpgradePreflight\Laravel;

use PhpUpgradePreflight\Core\Model\Evidence;
use PhpUpgradePreflight\Core\Model\EvidenceLedger;
use PhpUpgradePreflight\Core\Model\FrameworkStagePlan;
use PhpUpgradePreflight\Core\Model\FrameworkStageTarget;
use PhpUpgradePreflight\Core\Model\ProjectState;
use PhpUpgradePreflight\Core\Model\UpgradeRequest;
use PhpUpgradePreflight\Core\Model\UpgradeTarget;
use PhpUpgradePreflight\Core\Model\UpgradeTargetSet;
use PhpUpgradePreflight\Laravel\Catalog\LaravelRuleCatalog;
use PhpUpgradePreflight\Laravel\Catalog\PackageConstraintDefinition;
use PhpUpgradePreflight\Laravel\Catalog\PackageRuleDefinition;
use PhpUpgradePreflight\Laravel\Catalog\TransitionDefinition;
use PhpUpgradePreflight\Laravel\Rules\LaravelSource;
use PhpUpgradePreflight\Laravel\Rules\LaravelTarget;

/**
 * Turns a requested Laravel endpoint into the contiguous single-major stages
 * Composer is asked to solve.
 *
 * Every refusal is explicit: a plan is either a complete adjacent chain of
 * evidence-backed stage targets, or an empty plan carrying the reason and the
 * evidence for why staging could not be offered.
 */
final class LaravelStagePlanner
{
    private LaravelRuleCatalog $catalog;

    public function __construct(LaravelRuleCatalog $catalog)
    {
        $this->catalog = $catalog;
    }

    public function planStages(
        ProjectState $project,
        UpgradeRequest $request,
        EvidenceLedger $evidence
    ): FrameworkStagePlan {
        if (!LaravelRequestTargets::present($request)) {
            return $this->unavailableStagePlan(
                FrameworkStagePlan::REASON_MISSING_TARGET,
                'No Laravel-family upgrade target was supplied.',
                $project,
                $request,
                $evidence
            );
        }

        $source = LaravelSource::fromProject($project);
        $sourceMajor = $source->major();
        $target = LaravelTarget::fromRequest($request);
        if ($sourceMajor === null || $target === null) {
            return $this->unavailableStagePlan(
                FrameworkStagePlan::REASON_AMBIGUOUS_TRANSITION,
                'The source or target Laravel major could not be resolved unambiguously.',
                $project,
                $request,
                $evidence,
                ['source_uncertainties' => $source->uncertainties()]
            );
        }
        $targetMajor = $target->major();
        if (!$this->supportsFrameworkStageProject($project, $target)) {
            return $this->unavailableStagePlan(
                FrameworkStagePlan::REASON_GUIDANCE_GAP,
                'Staged Laravel solving requires one rooted laravel/framework project and target.',
                $project,
                $request,
                $evidence
            );
        }
        if ($sourceMajor >= $targetMajor) {
            return $this->unavailableStagePlan(
                FrameworkStagePlan::REASON_UNSUPPORTED_TRANSITION,
                'The requested Laravel endpoint is not an ascending framework transition.',
                $project,
                $request,
                $evidence
            );
        }
        if ($sourceMajor < $this->catalog->minimumMajor() || $targetMajor > $this->catalog->maximumMajor()) {
            return $this->unavailableStagePlan(
                FrameworkStagePlan::REASON_UNSUPPORTED_TRANSITION,
                'The requested Laravel endpoints fall outside the staged target catalog.',
                $project,
                $request,
                $evidence
            );
        }

        $stageMetadata = [];
        for ($from = $sourceMajor; $from < $targetMajor; ++$from) {
            $to = $from + 1;
            $definition = $this->catalog->target($to);
            $transition = $this->catalog->transition($from, $to, TransitionDefinition::ADJACENT);
            if ($definition === null || $transition === null || !$transition->isSupported()) {
                return $this->unavailableStagePlan(
                    FrameworkStagePlan::REASON_GUIDANCE_GAP,
                    sprintf('No contiguous supported adjacent rule pack exists for Laravel %d to %d.', $from, $to),
                    $project,
                    $request,
                    $evidence,
                    ['gap_from_major' => $from, 'gap_to_major' => $to]
                );
            }
            $analysisPhp = $this->selectAnalysisPhp($request, $definition->phpConstraint());
            if ($analysisPhp === null) {
                return $this->unavailableStagePlan(
                    FrameworkStagePlan::REASON_ANALYSIS_PHP_UNAVAILABLE,
                    sprintf('No exact request PHP value safely satisfies the Laravel %d stage requirement.', $to),
                    $project,
                    $request,
                    $evidence,
                    [
                        'stage_to_major' => $to,
                        'minimum_php_constraint' => $definition->phpConstraint(),
                    ]
                );
            }
            $stageMetadata[] = [$from, $to, $definition, $transition, $analysisPhp];
        }

        $stages = [];
        $planEvidence = [];
        foreach ($stageMetadata as [$from, $to, $definition, $transition, $analysisPhp]) {
            $stageId = sprintf('laravel-%d-to-%d', $from, $to);
            $stageEvidence = $evidence->add(
                'laravel-stage-target',
                Evidence::E4_MAINTAINER_DOCUMENTATION,
                sprintf('Laravel adapter metadata supplies the exact package target for stage %d to %d.', $from, $to),
                'high',
                [
                    'stage_id' => $stageId,
                    'package' => 'laravel/framework',
                    'constraint' => '^' . $to . '.0',
                    'analysis_php' => $analysisPhp['version'],
                    'minimum_php_constraint' => $definition->phpConstraint(),
                    'analysis_php_provenance' => $analysisPhp['provenance'],
                    'sources' => array_values(array_unique(array_merge(
                        $transition->sources(),
                        $definition->phpSources()
                    ))),
                ]
            )->id();
            $planEvidence[] = $stageEvidence;

            [$remediationTargets, $remediationEvidence] = $this->stageRemediations(
                $project,
                $from,
                $to,
                $stageId,
                $evidence
            );
            $stages[] = new FrameworkStageTarget(
                $stageId,
                'laravel',
                $from,
                $to,
                new UpgradeTargetSet(
                    [new UpgradeTarget('laravel/framework', '^' . $to . '.0')],
                    $analysisPhp['version']
                ),
                $analysisPhp['version'],
                $remediationTargets,
                $remediationEvidence,
                [$stageEvidence]
            );
        }

        return new FrameworkStagePlan('laravel', $stages, null, $planEvidence);
    }

    /** @return ?array{version: string, provenance: string} */
    private function selectAnalysisPhp(UpgradeRequest $request, string $minimumConstraint): ?array
    {
        $candidates = [
            'final_target_php_exact_value_checked_against_adapter_constraint' => $request->targetPhp(),
            'current_php_exact_value_checked_against_adapter_constraint' => $request->fromPhp(),
        ];
        $seen = [];
        foreach ($candidates as $provenance => $candidate) {
            if ($candidate === null) {
                continue;
            }
            $normalized = (new UpgradeTargetSet([], $candidate))->targetPhp();
            if ($normalized === null || isset($seen[$normalized])) {
                continue;
            }
            $seen[$normalized] = true;
            if (LaravelTarget::versionSatisfies($normalized, $minimumConstraint)) {
                return ['version' => $normalized, 'provenance' => $provenance];
            }
        }

        return null;
    }

    /** @param array<string, mixed> $context */
    private function unavailableStagePlan(
        string $reason,
        string $summary,
        ProjectState $project,
        UpgradeRequest $request,
        EvidenceLedger $evidence,
        array $context = []
    ): FrameworkStagePlan {
        $evidenceId = $evidence->add(
            'laravel-stage-plan-unavailable',
            Evidence::E2_PACKAGE_METADATA,
            $summary,
            'high',
            $context + [
                'reason' => $reason,
                'source_requirements' => $project->composerJson()->rootRequirements(),
                'target_constraints' => LaravelRequestTargets::constraints($request),
                'current_php' => $request->fromPhp(),
                'final_target_php' => $request->targetPhp(),
            ]
        )->id();

        return new FrameworkStagePlan('laravel', [], $reason, [$evidenceId]);
    }

    /**
     * @return array{list<UpgradeTarget>, array<string, list<string>>}
     */
    private function stageRemediations(
        ProjectState $project,
        int $fromMajor,
        int $toMajor,
        string $stageId,
        EvidenceLedger $evidence
    ): array {
        $requirements = $project->composerJson()->rootRequirements();
        /** @var array<string, UpgradeTarget> $targets */
        $targets = [];
        /** @var array<string, list<string>> $references */
        $references = [];

        foreach ($this->catalog->rules() as $rule) {
            if (!$rule instanceof PackageRuleDefinition) {
                continue;
            }
            foreach ($rule->guidance() as $guidance) {
                if (!$guidance->applicability()->matches($fromMajor, $toMajor)
                    || !isset($requirements[$guidance->package()])
                    || $this->packageAlreadyMatches($project, $guidance)) {
                    continue;
                }

                $evidenceId = $evidence->add(
                    'laravel-stage-remediation',
                    Evidence::E4_MAINTAINER_DOCUMENTATION,
                    sprintf(
                        'Laravel adapter metadata permits an analyzer-only root constraint candidate for %s in stage %s.',
                        $guidance->package(),
                        $stageId
                    ),
                    'medium',
                    [
                        'stage_id' => $stageId,
                        'package' => $guidance->package(),
                        'constraint' => $guidance->compatibleConstraint(),
                        'sources' => $guidance->sources(),
                    ]
                )->id();
                $targets[$guidance->package()] = new UpgradeTarget(
                    $guidance->package(),
                    $guidance->compatibleConstraint()
                );
                // Every minted evidence ID has to stay referenced. A custom catalog
                // may carry several guidance entries for one package on one stage,
                // and the ledger never deduplicates, so overwriting this entry would
                // orphan the earlier IDs and invalidate the whole staged chain.
                $references[$guidance->package()][] = $evidenceId;
            }
        }

        ksort($targets, SORT_STRING);
        ksort($references, SORT_STRING);

        return [array_values($targets), $references];
    }

    private function packageAlreadyMatches(ProjectState $project, PackageConstraintDefinition $guidance): bool
    {
        $locked = $project->composerLock()->package($guidance->package());
        if ($locked !== null) {
            return LaravelTarget::versionSatisfies($locked->version(), $guidance->compatibleConstraint());
        }

        $constraint = $project->composerJson()->rootRequirements()[$guidance->package()] ?? null;

        return $constraint !== null
            && LaravelTarget::constraintsIntersect($constraint, $guidance->compatibleConstraint());
    }

    private function supportsFrameworkStageProject(ProjectState $project, LaravelTarget $target): bool
    {
        $requestedConstraints = $target->requestedConstraints();
        $rootRequirements = $project->composerJson()->rootRequirements();

        return count($requestedConstraints) === 1
            && isset($requestedConstraints['laravel/framework'])
            && isset($rootRequirements['laravel/framework']);
    }
}
