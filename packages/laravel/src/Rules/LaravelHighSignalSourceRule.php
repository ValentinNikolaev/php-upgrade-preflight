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

final class LaravelHighSignalSourceRule implements CompatibilityRule, HopAwareCompatibilityRule
{
    private BuiltinRuleDefinition $definition;

    public function __construct(BuiltinRuleDefinition $definition)
    {
        $this->definition = $definition;
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
            static fn (SourceUsage $usage): bool => (
                $usage->usageType() === 'deprecated_queue_dispatch'
                || ($usage->usageType() === 'function_call' && strtolower($usage->symbol()) === 'dispatch_now')
            )
        ));
        if ($matched === []) {
            return null;
        }

        $documentationId = $evidence->add(
            'laravel-queue-dispatch-guidance',
            Evidence::E4_MAINTAINER_DOCUMENTATION,
            'Laravel 10 removes Bus::dispatchNow and dispatch_now in favor of their synchronous replacements.',
            'medium',
            [
                'replacement_methods' => ['Bus::dispatchSync', 'dispatch_sync'],
                'source' => 'https://laravel.com/docs/10.x/upgrade',
            ]
        )->id();
        $references = [$documentationId];
        foreach ($matched as $usage) {
            $references = array_merge($references, $usage->evidence());
        }

        return new CompatibilityFinding(
            'laravel',
            'high',
            sprintf(
                'Replace %d detected Bus::dispatchNow or dispatch_now call%s with Bus::dispatchSync or dispatch_sync before targeting Laravel 10.',
                count($matched),
                count($matched) === 1 ? '' : 's'
            ),
            array_values(array_unique($references))
        );
    }
}
