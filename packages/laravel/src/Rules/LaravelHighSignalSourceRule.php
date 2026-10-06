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

        if ($this->definition->rule() === BuiltinRuleDefinition::REMOVED_SOURCE_SYMBOLS) {
            return $this->removedLegacySymbolFinding($evidence, $sourceUsages, $sourceMajor, $targetMajor);
        }

        if ($sourceMajor === 12 && $targetMajor === 13) {
            return $this->requestForgeryFinding($evidence, $sourceUsages);
        }

        if ($sourceMajor === 11 && $targetMajor === 12) {
            return $this->removedUuidTraitFinding($evidence, $sourceUsages);
        }

        return $this->queueDispatchFinding($evidence, $sourceUsages);
    }

    /** @param list<SourceUsage> $sourceUsages */
    private function removedLegacySymbolFinding(
        EvidenceLedger $evidence,
        array $sourceUsages,
        int $sourceMajor,
        int $targetMajor
    ): ?CompatibilityFinding {
        if ($sourceMajor === 7 && $targetMajor === 8) {
            $symbols = ['elixir'];
            $usageTypes = ['deprecated_asset_helper'];
            $summary = 'Review %d detected use%s of the removed elixir helper; migrate to a Laravel Mix / mix asset workflow before targeting Laravel 8.';
            $source = 'https://github.com/laravel/docs/blob/13bfbca86689ae71739debd56e83ed6efc840ab2/upgrade.md#the-elixir-helper';
        } elseif ($sourceMajor === 8 && $targetMajor === 9) {
            $symbols = ['Illuminate\\Queue\\SerializableClosureFactory', 'Illuminate\\Queue\\SerializableClosure'];
            $usageTypes = ['instantiated_class', 'inheritance', 'class_constant_access', 'static_call', 'fully_qualified_name'];
            $summary = 'Review %d detected use%s of the removed Illuminate\\Queue\\SerializableClosureFactory or SerializableClosure classes; use laravel/serializable-closure replacements before targeting Laravel 9.';
            $source = 'https://github.com/laravel/docs/blob/177c095cc802ea0a1fa5f765e870c2cccaae9aa2/upgrade.md#the-opis-closure-library';
        } elseif ($sourceMajor === 9 && $targetMajor === 10) {
            $symbols = ['Illuminate\\Foundation\\Testing\\Concerns\\MocksApplicationServices'];
            $usageTypes = ['trait_reference'];
            $summary = 'Review %d detected use%s of the removed MocksApplicationServices trait; use Event::fake, Bus::fake, and Notification::fake before targeting Laravel 10.';
            $source = 'https://github.com/laravel/docs/blob/37e19ec52ec0894e9380fb57f3c5d0dd1f85872a/upgrade.md#service-mocking';
        } else {
            return null;
        }

        $normalizedSymbols = array_map('strtolower', $symbols);
        $matched = array_values(array_filter(
            $sourceUsages,
            static fn (SourceUsage $usage): bool => in_array($usage->usageType(), $usageTypes, true)
                && in_array(strtolower($usage->symbol()), $normalizedSymbols, true)
        ));
        if ($matched === []) {
            return null;
        }

        $findingSummary = sprintf($summary, count($matched), count($matched) === 1 ? '' : 's');
        $documentationId = $evidence->add(
            'laravel-removed-source-symbol-guidance',
            Evidence::E4_MAINTAINER_DOCUMENTATION,
            $findingSummary,
            'high',
            ['removed_symbols' => $symbols, 'target_laravel_major' => $targetMajor, 'source' => $source]
        )->id();
        $references = [$documentationId];
        foreach ($matched as $usage) {
            $references = array_merge($references, $usage->evidence());
        }

        return new CompatibilityFinding('laravel', 'high', $findingSummary, array_values(array_unique($references)));
    }

    /** @param list<SourceUsage> $sourceUsages */
    private function removedUuidTraitFinding(EvidenceLedger $evidence, array $sourceUsages): ?CompatibilityFinding
    {
        $matched = array_values(array_filter(
            $sourceUsages,
            static fn (SourceUsage $usage): bool => $usage->usageType() === 'trait_reference'
                && strtolower($usage->symbol()) === 'illuminate\\database\\eloquent\\concerns\\hasversion7uuids'
        ));
        if ($matched === []) {
            return null;
        }

        $documentationId = $evidence->add(
            'laravel-uuid-trait-guidance',
            Evidence::E4_MAINTAINER_DOCUMENTATION,
            'Laravel 12 removes HasVersion7Uuids; HasUuids now provides UUIDv7 behavior.',
            'high',
            [
                'removed_symbol' => 'Illuminate\\Database\\Eloquent\\Concerns\\HasVersion7Uuids',
                'replacement_symbol' => 'Illuminate\\Database\\Eloquent\\Concerns\\HasUuids',
                'source' => 'https://github.com/laravel/docs/blob/5b8c610735c8af96a3bda4e37a820b27dc40aee9/upgrade.md#models-and-uuidv7',
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
                'Replace %d detected use%s of the removed HasVersion7Uuids trait with HasUuids before targeting Laravel 12.',
                count($matched),
                count($matched) === 1 ? '' : 's'
            ),
            array_values(array_unique($references))
        );
    }

    /** @param list<SourceUsage> $sourceUsages */
    private function queueDispatchFinding(EvidenceLedger $evidence, array $sourceUsages): ?CompatibilityFinding
    {

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

    /** @param list<SourceUsage> $sourceUsages */
    private function requestForgeryFinding(EvidenceLedger $evidence, array $sourceUsages): ?CompatibilityFinding
    {
        $legacyMiddleware = [
            'illuminate\\foundation\\http\\middleware\\verifycsrftoken',
            'illuminate\\foundation\\http\\middleware\\validatecsrftoken',
        ];
        $matched = array_values(array_filter(
            $sourceUsages,
            static fn (SourceUsage $usage): bool => in_array($usage->usageType(), ['middleware_reference', 'inheritance'], true)
                && in_array(strtolower($usage->symbol()), $legacyMiddleware, true)
        ));
        if ($matched === []) {
            return null;
        }

        $documentationId = $evidence->add(
            'laravel-request-forgery-guidance',
            Evidence::E4_MAINTAINER_DOCUMENTATION,
            'Laravel 13 renames the CSRF middleware to PreventRequestForgery and deprecates the previous aliases.',
            'high',
            [
                'legacy_symbols' => [
                    'Illuminate\\Foundation\\Http\\Middleware\\VerifyCsrfToken',
                    'Illuminate\\Foundation\\Http\\Middleware\\ValidateCsrfToken',
                ],
                'replacement_symbol' => 'Illuminate\\Foundation\\Http\\Middleware\\PreventRequestForgery',
                'source' => 'https://github.com/laravel/docs/blob/9c5a062c14069bab9054b558829e282f9593a065/upgrade.md',
            ]
        )->id();
        $references = [$documentationId];
        foreach ($matched as $usage) {
            $references = array_merge($references, $usage->evidence());
        }

        return new CompatibilityFinding(
            'laravel',
            'medium',
            sprintf(
                'Review %d detected direct reference%s to VerifyCsrfToken or ValidateCsrfToken for PreventRequestForgery when targeting Laravel 13; deprecated aliases remain available.',
                count($matched),
                count($matched) === 1 ? '' : 's'
            ),
            array_values(array_unique($references))
        );
    }
}
