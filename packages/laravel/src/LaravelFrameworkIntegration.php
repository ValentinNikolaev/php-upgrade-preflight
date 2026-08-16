<?php

declare(strict_types=1);

namespace PhpUpgradePreflight\Laravel;

use PhpUpgradePreflight\Core\Framework\FrameworkDetection;
use PhpUpgradePreflight\Core\Framework\FrameworkIntegration;
use PhpUpgradePreflight\Core\Framework\FrameworkStageTargetProvider;
use PhpUpgradePreflight\Core\Framework\FrameworkTransitionProvider;
use PhpUpgradePreflight\Core\Framework\PackageFamilyClassifier;
use PhpUpgradePreflight\Core\Model\Evidence;
use PhpUpgradePreflight\Core\Model\EvidenceLedger;
use PhpUpgradePreflight\Core\Model\FrameworkGuidance;
use PhpUpgradePreflight\Core\Model\FrameworkHop;
use PhpUpgradePreflight\Core\Model\FrameworkStagePlan;
use PhpUpgradePreflight\Core\Model\FrameworkStageTarget;
use PhpUpgradePreflight\Core\Model\ProjectState;
use PhpUpgradePreflight\Core\Model\UpgradeRequest;
use PhpUpgradePreflight\Core\Model\UpgradeTarget;
use PhpUpgradePreflight\Core\Model\UpgradeTargetSet;
use PhpUpgradePreflight\Laravel\Catalog\BuiltinRuleDefinition;
use PhpUpgradePreflight\Laravel\Catalog\LaravelRuleCatalog;
use PhpUpgradePreflight\Laravel\Catalog\PackageAdvisoryDefinition;
use PhpUpgradePreflight\Laravel\Catalog\PackageConstraintDefinition;
use PhpUpgradePreflight\Laravel\Catalog\PackageRuleDefinition;
use PhpUpgradePreflight\Laravel\Catalog\TransitionDefinition;
use PhpUpgradePreflight\Laravel\Rules\LaravelComposerVersionRule;
use PhpUpgradePreflight\Laravel\Rules\LaravelCurlExtensionRule;
use PhpUpgradePreflight\Laravel\Rules\LaravelFrameworkConstraintRule;
use PhpUpgradePreflight\Laravel\Rules\LaravelHighSignalSourceRule;
use PhpUpgradePreflight\Laravel\Rules\LaravelPhpConstraintRule;
use PhpUpgradePreflight\Laravel\Rules\LaravelSkeletonRule;
use PhpUpgradePreflight\Laravel\Rules\LaravelSource;
use PhpUpgradePreflight\Laravel\Rules\LaravelTarget;
use PhpUpgradePreflight\Laravel\Rules\OldIlluminateSupportRule;
use PhpUpgradePreflight\Laravel\Rules\PackageVersionRule;
use PhpUpgradePreflight\Laravel\Rules\SymfonyComponentConstraintRule;
use PhpUpgradePreflight\Laravel\Rules\TargetedPackageAdvisoryRule;

final class LaravelFrameworkIntegration implements FrameworkIntegration, FrameworkTransitionProvider, FrameworkStageTargetProvider, PackageFamilyClassifier
{
    private LaravelPackageFamilyClassifier $packageFamilyClassifier;
    private LaravelRuleCatalog $catalog;

    public function __construct(
        ?LaravelPackageFamilyClassifier $packageFamilyClassifier = null,
        ?LaravelRuleCatalog $catalog = null
    ) {
        $this->packageFamilyClassifier = $packageFamilyClassifier ?? new LaravelPackageFamilyClassifier();
        $this->catalog = $catalog ?? LaravelRuleCatalog::v0_2();
    }

    public function name(): string
    {
        return 'laravel';
    }

    public function detect(ProjectState $project): FrameworkDetection
    {
        $rootRequirements = $project->composerJson()->rootRequirements();
        $lockedFramework = $project->composerLock()->package('laravel/framework');
        $frameworkConstraint = $rootRequirements['laravel/framework'] ?? null;

        if ($lockedFramework !== null || $frameworkConstraint !== null) {
            return new FrameworkDetection(
                'laravel',
                true,
                $lockedFramework === null ? $frameworkConstraint : $lockedFramework->version()
            );
        }

        $illuminateConstraints = [];
        foreach ($rootRequirements as $package => $constraint) {
            if (str_starts_with($package, 'illuminate/')) {
                $illuminateConstraints[$package] = $constraint;
            }
        }

        if ($illuminateConstraints === []) {
            return new FrameworkDetection('laravel', false);
        }

        ksort($illuminateConstraints);
        $versions = [];
        foreach ($illuminateConstraints as $package => $constraint) {
            $locked = $project->composerLock()->package($package);
            $versions[] = $locked === null ? $constraint : $locked->version();
        }

        $versions = array_values(array_unique($versions));

        return new FrameworkDetection('laravel', true, count($versions) === 1 ? $versions[0] : null);
    }

    public function rules(): iterable
    {
        foreach ($this->catalog->rules() as $definition) {
            if ($definition instanceof PackageRuleDefinition) {
                yield new PackageVersionRule($definition);

                continue;
            }
            if ($definition instanceof PackageAdvisoryDefinition) {
                yield new TargetedPackageAdvisoryRule($definition);

                continue;
            }
            if (!$definition instanceof BuiltinRuleDefinition) {
                throw new \LogicException('Unsupported Laravel catalog rule definition.');
            }

            switch ($definition->rule()) {
                case BuiltinRuleDefinition::FRAMEWORK_CONSTRAINT:
                    yield new LaravelFrameworkConstraintRule($definition);
                    break;
                case BuiltinRuleDefinition::PHP_CONSTRAINT:
                    yield new LaravelPhpConstraintRule($definition, $this->catalog);
                    break;
                case BuiltinRuleDefinition::SYMFONY_CONSTRAINT:
                    yield new SymfonyComponentConstraintRule($definition, $this->catalog);
                    break;
                case BuiltinRuleDefinition::ILLUMINATE_SUPPORT:
                    yield new OldIlluminateSupportRule($definition);
                    break;
                case BuiltinRuleDefinition::SKELETON:
                    yield new LaravelSkeletonRule($definition, $this->catalog->skeletonPatterns());
                    break;
                case BuiltinRuleDefinition::COMPOSER_VERSION:
                    yield new LaravelComposerVersionRule($definition);
                    break;
                case BuiltinRuleDefinition::CURL_EXTENSION:
                    yield new LaravelCurlExtensionRule($definition);
                    break;
                case BuiltinRuleDefinition::HIGH_SIGNAL_SOURCE:
                    yield new LaravelHighSignalSourceRule($definition);
                    break;
                default:
                    throw new \LogicException(sprintf('Unsupported Laravel built-in rule: %s.', $definition->rule()));
            }
        }
    }

    public function assessTransition(
        ProjectState $project,
        UpgradeRequest $request,
        EvidenceLedger $evidence
    ): ?FrameworkGuidance {
        if (!$this->hasLaravelTarget($request)) {
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
                        ? $this->laravelTargetConstraints($request)
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

    public function planStages(
        ProjectState $project,
        UpgradeRequest $request,
        EvidenceLedger $evidence
    ): FrameworkStagePlan {
        if (!$this->hasLaravelTarget($request)) {
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
                'target_constraints' => $this->laravelTargetConstraints($request),
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
                $references[$guidance->package()] = [$evidenceId];
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

    public function defaultSourcePaths(ProjectState $project): array
    {
        return ['src', 'app', 'bootstrap', 'config', 'database', 'routes', 'tests'];
    }

    public function packageFamilies(string $packageName): array
    {
        return $this->packageFamilyClassifier->packageFamilies($packageName);
    }

    private function hasLaravelTarget(UpgradeRequest $request): bool
    {
        foreach ($request->targets()->packageTargets() as $target) {
            $package = strtolower($target->package());
            if ($package === 'laravel/framework' || str_starts_with($package, 'illuminate/')) {
                return true;
            }
        }

        return false;
    }

    private function supportsFrameworkStageProject(ProjectState $project, LaravelTarget $target): bool
    {
        $requestedConstraints = $target->requestedConstraints();
        $rootRequirements = $project->composerJson()->rootRequirements();

        return count($requestedConstraints) === 1
            && isset($requestedConstraints['laravel/framework'])
            && isset($rootRequirements['laravel/framework']);
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

    /** @return array<string, string> */
    private function laravelTargetConstraints(UpgradeRequest $request): array
    {
        $constraints = [];
        foreach ($request->targets()->packageTargets() as $target) {
            $package = strtolower($target->package());
            if ($package === 'laravel/framework' || str_starts_with($package, 'illuminate/')) {
                $constraints[$package] = $target->constraint();
            }
        }
        ksort($constraints);

        return $constraints;
    }
}
