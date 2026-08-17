<?php

declare(strict_types=1);

namespace PhpUpgradePreflight\Laravel;

use PhpUpgradePreflight\Core\Framework\FrameworkDetection;
use PhpUpgradePreflight\Core\Framework\FrameworkIntegration;
use PhpUpgradePreflight\Core\Framework\FrameworkStageTargetProvider;
use PhpUpgradePreflight\Core\Framework\FrameworkTransitionProvider;
use PhpUpgradePreflight\Core\Framework\PackageFamilyClassifier;
use PhpUpgradePreflight\Core\Framework\SourceUsageVisitorProvider;
use PhpUpgradePreflight\Core\Model\EvidenceLedger;
use PhpUpgradePreflight\Core\Model\FrameworkGuidance;
use PhpUpgradePreflight\Core\Model\FrameworkStagePlan;
use PhpUpgradePreflight\Core\Model\ProjectState;
use PhpUpgradePreflight\Core\Model\UpgradeRequest;
use PhpUpgradePreflight\Core\Source\SourceUsageCollector;
use PhpUpgradePreflight\Laravel\Catalog\LaravelRuleCatalog;
use PhpUpgradePreflight\Laravel\Source\LaravelSourceUsageVisitor;

/**
 * The Laravel adapter's entry point into the framework-neutral core.
 *
 * This class is a facade: it owns no analysis of its own and holds the adapter's
 * capabilities together behind the interfaces core detects. Detection, rule
 * construction, transition assessment, and stage planning each live in their own
 * collaborator so a change to one cannot disturb the others.
 */
final class LaravelFrameworkIntegration implements FrameworkIntegration, FrameworkTransitionProvider, FrameworkStageTargetProvider, PackageFamilyClassifier, SourceUsageVisitorProvider
{
    private LaravelPackageFamilyClassifier $packageFamilyClassifier;
    private LaravelFrameworkDetector $detector;
    private LaravelRuleFactory $ruleFactory;
    private LaravelTransitionAssessor $transitionAssessor;
    private LaravelStagePlanner $stagePlanner;

    public function __construct(
        ?LaravelPackageFamilyClassifier $packageFamilyClassifier = null,
        ?LaravelRuleCatalog $catalog = null
    ) {
        $catalog = $catalog ?? LaravelRuleCatalog::v0_2();
        $this->packageFamilyClassifier = $packageFamilyClassifier ?? new LaravelPackageFamilyClassifier();
        $this->detector = new LaravelFrameworkDetector();
        $this->ruleFactory = new LaravelRuleFactory($catalog);
        $this->transitionAssessor = new LaravelTransitionAssessor($catalog);
        $this->stagePlanner = new LaravelStagePlanner($catalog);
    }

    public function name(): string
    {
        return 'laravel';
    }

    public function detect(ProjectState $project): FrameworkDetection
    {
        return $this->detector->detect($project);
    }

    public function rules(): iterable
    {
        return $this->ruleFactory->rules();
    }

    public function assessTransition(
        ProjectState $project,
        UpgradeRequest $request,
        EvidenceLedger $evidence
    ): ?FrameworkGuidance {
        return $this->transitionAssessor->assessTransition($project, $request, $evidence);
    }

    public function planStages(
        ProjectState $project,
        UpgradeRequest $request,
        EvidenceLedger $evidence
    ): FrameworkStagePlan {
        return $this->stagePlanner->planStages($project, $request, $evidence);
    }

    public function defaultSourcePaths(ProjectState $project): array
    {
        return ['src', 'app', 'bootstrap', 'config', 'database', 'routes', 'tests'];
    }

    /** @return iterable<SourceUsageCollector> */
    public function sourceUsageVisitors(string $relativeFile): iterable
    {
        yield new LaravelSourceUsageVisitor($relativeFile);
    }

    public function packageFamilies(string $packageName): array
    {
        return $this->packageFamilyClassifier->packageFamilies($packageName);
    }
}
