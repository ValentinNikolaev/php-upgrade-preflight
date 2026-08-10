<?php

declare(strict_types=1);

namespace PhpUpgradePreflight\Laravel\Rules;

use PhpUpgradePreflight\Core\Framework\CompatibilityRule;
use PhpUpgradePreflight\Core\Framework\HopAwareCompatibilityRule;
use PhpUpgradePreflight\Core\Model\CompatibilityFinding;
use PhpUpgradePreflight\Core\Model\Evidence;
use PhpUpgradePreflight\Core\Model\EvidenceLedger;
use PhpUpgradePreflight\Core\Model\FrameworkHop;
use PhpUpgradePreflight\Core\Model\ProjectState;
use PhpUpgradePreflight\Core\Model\SourceUsage;
use PhpUpgradePreflight\Core\Model\UpgradeRequest;
use PhpUpgradePreflight\Laravel\Catalog\BuiltinRuleDefinition;
use PhpUpgradePreflight\Laravel\Catalog\SkeletonPattern;

final class LaravelSkeletonRule implements CompatibilityRule, HopAwareCompatibilityRule
{
    private BuiltinRuleDefinition $definition;
    /** @var list<SkeletonPattern> */
    private array $patterns;

    /** @param list<SkeletonPattern> $patterns */
    public function __construct(BuiltinRuleDefinition $definition, array $patterns)
    {
        $this->definition = $definition;
        $this->patterns = $patterns;
    }

    public function evaluate(
        ProjectState $project,
        UpgradeRequest $request,
        EvidenceLedger $evidence,
        array $sourceUsages = []
    ): ?CompatibilityFinding {
        $target = LaravelTarget::fromRequest($request);
        $sourceMajor = LaravelSource::fromProject($project)->major();
        if ($target === null || $sourceMajor === null) {
            return null;
        }

        return $this->evaluateTransition($evidence, $sourceUsages, $sourceMajor, $target->major());
    }

    public function evaluateForHop(
        ProjectState $project,
        UpgradeRequest $request,
        EvidenceLedger $evidence,
        FrameworkHop $hop,
        ?string $composerVersion = null,
        array $sourceUsages = []
    ): ?CompatibilityFinding {
        $requestedTarget = LaravelTarget::fromRequest($request);
        $requestedSource = LaravelSource::fromProject($project)->major();
        if ($requestedTarget !== null
            && $requestedSource !== null
            && $this->definition->appliesTo($requestedSource, $requestedTarget->major())) {
            if ($hop->toMajor() !== $requestedTarget->major()) {
                return null;
            }

            return $this->evaluateTransition(
                $evidence,
                $sourceUsages,
                $requestedSource,
                $requestedTarget->major()
            );
        }

        return $this->evaluateTransition($evidence, $sourceUsages, $hop->fromMajor(), $hop->toMajor());
    }

    /** @param list<SourceUsage> $sourceUsages */
    private function evaluateTransition(
        EvidenceLedger $evidence,
        array $sourceUsages,
        int $sourceMajor,
        int $targetMajor
    ): ?CompatibilityFinding {
        if (!$this->definition->appliesTo($sourceMajor, $targetMajor)) {
            return null;
        }

        $matched = array_values(array_filter(
            $sourceUsages,
            function (SourceUsage $usage): bool {
                foreach ($this->patterns as $pattern) {
                    if ($this->matches($usage, $pattern)) {
                        return true;
                    }
                }

                return false;
            }
        ));

        if ($matched === []) {
            return null;
        }

        $sourceEvidence = [];
        $indicators = [];
        foreach ($matched as $usage) {
            $sourceEvidence = array_merge($sourceEvidence, $usage->evidence());
            $indicators[] = [
                'file' => $usage->file(),
                'line' => $usage->line(),
                'symbol' => $usage->symbol(),
                'usage_type' => $usage->usageType(),
            ];
        }

        $heuristicId = $evidence->add(
            'laravel-skeleton-guidance',
            Evidence::E5_HEURISTIC,
            'Detected Kernel middleware, application provider/alias entries, or TrustProxies inheritance identify skeleton-managed integration points for manual comparison.',
            'low',
            [
                'target_laravel_major' => $targetMajor,
                'indicator_count' => count($indicators),
                'indicators' => $indicators,
                'claim' => 'review_location_only',
            ]
        )->id();

        return new CompatibilityFinding(
            'laravel',
            'low',
            sprintf(
                'Compare detected Laravel skeleton-managed integration locations (Kernel middleware, app config providers/aliases, or TrustProxies inheritance) with the Laravel %d skeleton; these are review locations, not confirmed incompatibilities.',
                $targetMajor
            ),
            array_values(array_unique(array_merge($sourceEvidence, [$heuristicId])))
        );
    }

    private function matches(SourceUsage $usage, SkeletonPattern $pattern): bool
    {
        if (strtolower(str_replace('\\', '/', $usage->file())) !== $pattern->file()
            || !in_array($usage->usageType(), $pattern->usageTypes(), true)) {
            return false;
        }

        return $pattern->symbol() === null || strtolower($usage->symbol()) === $pattern->symbol();
    }
}
