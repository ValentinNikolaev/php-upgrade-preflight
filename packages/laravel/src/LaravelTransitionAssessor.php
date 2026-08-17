<?php

declare(strict_types=1);

namespace PhpUpgradePreflight\Laravel;

use PhpUpgradePreflight\Core\Model\Evidence;
use PhpUpgradePreflight\Core\Model\EvidenceLedger;
use PhpUpgradePreflight\Core\Model\FrameworkGuidance;
use PhpUpgradePreflight\Core\Model\FrameworkHop;
use PhpUpgradePreflight\Core\Model\ProjectState;
use PhpUpgradePreflight\Core\Model\UpgradeRequest;
use PhpUpgradePreflight\Laravel\Catalog\LaravelRuleCatalog;
use PhpUpgradePreflight\Laravel\Catalog\TransitionDefinition;
use PhpUpgradePreflight\Laravel\Rules\LaravelSource;
use PhpUpgradePreflight\Laravel\Rules\LaravelTarget;

/**
 * Answers which modeled Laravel rule packs cover a requested major transition.
 *
 * The answer is always evidence-backed: a covered hop cites the rule pack that
 * covers it, and an uncovered one records why coverage stops there instead of
 * silently narrowing the reported guidance.
 */
final class LaravelTransitionAssessor
{
    private LaravelRuleCatalog $catalog;

    public function __construct(LaravelRuleCatalog $catalog)
    {
        $this->catalog = $catalog;
    }

    public function assessTransition(
        ProjectState $project,
        UpgradeRequest $request,
        EvidenceLedger $evidence
    ): ?FrameworkGuidance {
        if (!LaravelRequestTargets::present($request)) {
            return null;
        }

        $source = LaravelSource::fromProject($project);
        $sourceMajor = $source->major();
        $target = LaravelTarget::fromRequest($request);
        $targetMajor = $target === null ? null : $target->major();

        if ($sourceMajor === null || $targetMajor === null) {
            $evidenceId = $evidence->add(
                'laravel-transition',
                Evidence::E2_PACKAGE_METADATA,
                'Laravel transition coverage could not be selected because a source or target major was ambiguous or unsupported.',
                'high',
                [
                    'source_major' => $sourceMajor,
                    'target_major' => $targetMajor,
                    'source_observations' => $source->observations(),
                    'target_constraints' => $target === null
                        ? LaravelRequestTargets::constraints($request)
                        : $target->requestedConstraints(),
                    'root_requirements' => $project->composerJson()->rootRequirements(),
                ]
            )->id();

            $uncertainties = $sourceMajor === null ? $source->uncertainties() : [];
            if ($targetMajor === null) {
                $uncertainties[] = 'The requested Laravel package constraints do not identify exactly one target major.';
            }
            $uncertainties = array_map(
                static fn (string $uncertainty): string => sprintf('%s (%s)', $uncertainty, $evidenceId),
                $uncertainties
            );

            return new FrameworkGuidance(
                'laravel',
                $sourceMajor,
                $targetMajor,
                FrameworkGuidance::UNSUPPORTED,
                [],
                $uncertainties,
                [$evidenceId]
            );
        }

        if ($sourceMajor >= $targetMajor) {
            return $this->unsupportedTransition(
                $sourceMajor,
                $targetMajor,
                'Laravel framework guidance is unsupported because the requested target is not a major-version upgrade.',
                $evidence
            );
        }

        $direct = $this->catalog->transition($sourceMajor, $targetMajor, TransitionDefinition::DIRECT);
        if ($direct !== null && $direct->isSupported() && !$this->hasCompleteAdjacentPath($sourceMajor, $targetMajor)) {
            return $this->supportedDirectTransition($direct, $evidence);
        }

        if ($sourceMajor < $this->catalog->minimumMajor() || $targetMajor > $this->catalog->maximumMajor()) {
            return $this->unsupportedTransition(
                $sourceMajor,
                $targetMajor,
                sprintf(
                    'Laravel framework guidance is unsupported outside the modeled Laravel %d through %d transition catalog.',
                    $this->catalog->minimumMajor(),
                    $this->catalog->maximumMajor()
                ),
                $evidence
            );
        }

        return $this->adjacentTransition($sourceMajor, $targetMajor, $evidence);
    }

    private function supportedDirectTransition(
        TransitionDefinition $transition,
        EvidenceLedger $evidence
    ): FrameworkGuidance {
        $sourceMajor = $transition->sourceMajor();
        $targetMajor = $transition->targetMajor();
        $rulePack = $transition->rulePack();
        if ($rulePack === null) {
            throw new \LogicException('A supported direct transition must declare a rule pack.');
        }
        $source = $transition->sources()[0];
        $evidenceId = $evidence->add(
            'laravel-transition',
            Evidence::E4_MAINTAINER_DOCUMENTATION,
            sprintf('The retained Laravel %d to %d rule pack covers this requested transition.', $sourceMajor, $targetMajor),
            'medium',
            [
                'source_major' => $sourceMajor,
                'target_major' => $targetMajor,
                'rule_pack' => $rulePack,
                'source' => $source,
            ]
        )->id();
        $hop = new FrameworkHop(
            $sourceMajor,
            $targetMajor,
            FrameworkHop::SUPPORTED,
            $rulePack,
            [$evidenceId]
        );

        return new FrameworkGuidance(
            'laravel',
            $sourceMajor,
            $targetMajor,
            FrameworkGuidance::SUPPORTED,
            [$hop],
            [],
            [$evidenceId]
        );
    }

    private function adjacentTransition(
        int $sourceMajor,
        int $targetMajor,
        EvidenceLedger $evidence
    ): FrameworkGuidance {
        $hops = [];
        $evidenceIds = [];
        $uncertainties = [];
        $coveredPrefix = true;
        $supportedCount = 0;

        for ($fromMajor = $sourceMajor; $fromMajor < $targetMajor; ++$fromMajor) {
            $toMajor = $fromMajor + 1;
            $definition = $this->catalog->transition($fromMajor, $toMajor, TransitionDefinition::ADJACENT);
            $implementedRulePack = $definition === null ? null : $definition->rulePack();
            $rulePack = $coveredPrefix ? $implementedRulePack : null;

            if ($rulePack !== null) {
                $evidenceId = $evidence->add(
                    'laravel-transition',
                    Evidence::E4_MAINTAINER_DOCUMENTATION,
                    sprintf(
                        'The %s Laravel %d to %d rule pack covers this requested transition.',
                        $fromMajor === 7 ? 'retained' : 'implemented',
                        $fromMajor,
                        $toMajor
                    ),
                    'medium',
                    [
                        'source_major' => $fromMajor,
                        'target_major' => $toMajor,
                        'rule_pack' => $rulePack,
                        'source' => $definition->sources()[0],
                    ]
                )->id();
                $hops[] = new FrameworkHop(
                    $fromMajor,
                    $toMajor,
                    FrameworkHop::SUPPORTED,
                    $rulePack,
                    [$evidenceId]
                );
                $evidenceIds[] = $evidenceId;
                ++$supportedCount;

                continue;
            }

            $ignoredAfterGap = $implementedRulePack !== null;
            $coveredPrefix = false;
            $evidenceId = $evidence->add(
                'laravel-transition',
                Evidence::E4_MAINTAINER_DOCUMENTATION,
                $ignoredAfterGap
                    ? sprintf('The Laravel %d to %d adjacent rule pack is ignored after an earlier coverage gap.', $fromMajor, $toMajor)
                    : sprintf('No implemented Laravel %d to %d adjacent rule pack is available.', $fromMajor, $toMajor),
                'medium',
                [
                    'source_major' => $fromMajor,
                    'target_major' => $toMajor,
                    'rule_pack' => $implementedRulePack,
                    'implemented' => $ignoredAfterGap,
                    'ignored_after_gap' => $ignoredAfterGap,
                    'source' => $definition === null ? null : $definition->sources()[0],
                ]
            )->id();
            $hops[] = new FrameworkHop(
                $fromMajor,
                $toMajor,
                FrameworkHop::UNSUPPORTED,
                null,
                [$evidenceId]
            );
            $evidenceIds[] = $evidenceId;
            $uncertainties[] = $ignoredAfterGap
                ? sprintf(
                    'Laravel %d to %d guidance is ignored because coverage cannot continue after an earlier missing hop (%s).',
                    $fromMajor,
                    $toMajor,
                    $evidenceId
                )
                : sprintf(
                    'Laravel %d to %d guidance is unavailable because its adjacent rule pack is not implemented (%s).',
                    $fromMajor,
                    $toMajor,
                    $evidenceId
                );
        }

        if ($supportedCount === count($hops)) {
            $status = FrameworkGuidance::SUPPORTED;
        } elseif ($supportedCount > 0) {
            $status = FrameworkGuidance::PARTIALLY_SUPPORTED;
        } else {
            $status = FrameworkGuidance::UNSUPPORTED;
            $hops = [];
        }

        return new FrameworkGuidance(
            'laravel',
            $sourceMajor,
            $targetMajor,
            $status,
            $hops,
            $uncertainties,
            $evidenceIds
        );
    }

    private function unsupportedTransition(
        int $sourceMajor,
        int $targetMajor,
        string $uncertainty,
        EvidenceLedger $evidence
    ): FrameworkGuidance {
        $evidenceId = $evidence->add(
            'laravel-transition',
            Evidence::E2_PACKAGE_METADATA,
            $uncertainty,
            'high',
            [
                'source_major' => $sourceMajor,
                'target_major' => $targetMajor,
                'catalog_minimum_major' => $this->catalog->minimumMajor(),
                'catalog_maximum_major' => $this->catalog->maximumMajor(),
            ]
        )->id();

        return new FrameworkGuidance(
            'laravel',
            $sourceMajor,
            $targetMajor,
            FrameworkGuidance::UNSUPPORTED,
            [],
            [sprintf('%s (%s)', $uncertainty, $evidenceId)],
            [$evidenceId]
        );
    }

    private function hasCompleteAdjacentPath(int $sourceMajor, int $targetMajor): bool
    {
        for ($fromMajor = $sourceMajor; $fromMajor < $targetMajor; ++$fromMajor) {
            $transition = $this->catalog->transition(
                $fromMajor,
                $fromMajor + 1,
                TransitionDefinition::ADJACENT
            );
            if ($transition === null || !$transition->isSupported()) {
                return false;
            }
        }

        return true;
    }
}
