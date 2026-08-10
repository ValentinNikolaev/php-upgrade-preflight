<?php

declare(strict_types=1);

namespace PhpUpgradePreflight\Laravel\Rules;

use PhpUpgradePreflight\Core\Framework\CompatibilityRule;
use PhpUpgradePreflight\Core\Model\CompatibilityFinding;
use PhpUpgradePreflight\Core\Model\Evidence;
use PhpUpgradePreflight\Core\Model\EvidenceLedger;
use PhpUpgradePreflight\Core\Model\ProjectState;
use PhpUpgradePreflight\Core\Model\SourceUsage;
use PhpUpgradePreflight\Core\Model\UpgradeRequest;
use PhpUpgradePreflight\Laravel\Catalog\BuiltinRuleDefinition;
use PhpUpgradePreflight\Laravel\Catalog\SkeletonPattern;

final class LaravelSkeletonRule implements CompatibilityRule
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
        if ($target === null
            || $sourceMajor === null
            || !$this->definition->appliesTo($sourceMajor, $target->major())) {
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
                'target_laravel_major' => $target->major(),
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
                $target->major()
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
