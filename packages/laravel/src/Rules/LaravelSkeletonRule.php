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

final class LaravelSkeletonRule implements CompatibilityRule
{
    public function evaluate(
        ProjectState $project,
        UpgradeRequest $request,
        EvidenceLedger $evidence,
        array $sourceUsages = []
    ): ?CompatibilityFinding {
        $target = LaravelTarget::fromRequest($request);
        if ($target === null || !LaravelTarget::isLaravel7Project($project)) {
            return null;
        }

        $matched = array_values(array_filter(
            $sourceUsages,
            static function (SourceUsage $usage): bool {
                $file = strtolower(str_replace('\\', '/', $usage->file()));

                if ($file === 'app/http/kernel.php' && $usage->usageType() === 'middleware_reference') {
                    return true;
                }

                if ($file === 'config/app.php'
                    && in_array($usage->usageType(), ['service_provider', 'facade_alias'], true)) {
                    return true;
                }

                return $file === 'app/http/middleware/trustproxies.php'
                    && $usage->usageType() === 'inheritance'
                    && strtolower($usage->symbol()) === 'fideloper\\proxy\\trustproxies';
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
}
