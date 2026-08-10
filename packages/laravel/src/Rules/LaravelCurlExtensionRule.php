<?php

declare(strict_types=1);

namespace PhpUpgradePreflight\Laravel\Rules;

use PhpUpgradePreflight\Core\Framework\CompatibilityRule;
use PhpUpgradePreflight\Core\Framework\HopAwareCompatibilityRule;
use PhpUpgradePreflight\Core\Model\CompatibilityFinding;
use PhpUpgradePreflight\Core\Model\Evidence;
use PhpUpgradePreflight\Core\Model\EvidenceLedger;
use PhpUpgradePreflight\Core\Model\ExtensionAssumption;
use PhpUpgradePreflight\Core\Model\FrameworkHop;
use PhpUpgradePreflight\Core\Model\ProjectState;
use PhpUpgradePreflight\Core\Model\UpgradeRequest;
use PhpUpgradePreflight\Laravel\Catalog\BuiltinRuleDefinition;

final class LaravelCurlExtensionRule implements CompatibilityRule, HopAwareCompatibilityRule
{
    private const MINIMUM_CURL = '7.34.0';

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
        return $target === null || $sourceMajor === null
            ? null
            : $this->evaluateTransition($project, $request, $evidence, $sourceMajor, $target);
    }

    public function evaluateForHop(
        ProjectState $project,
        UpgradeRequest $request,
        EvidenceLedger $evidence,
        FrameworkHop $hop,
        ?string $composerVersion = null,
        array $sourceUsages = []
    ): ?CompatibilityFinding {
        return $this->evaluateTransition(
            $project,
            $request,
            $evidence,
            $hop->fromMajor(),
            LaravelTarget::forMajor($hop->toMajor())
        );
    }

    private function evaluateTransition(
        ProjectState $project,
        UpgradeRequest $request,
        EvidenceLedger $evidence,
        int $sourceMajor,
        LaravelTarget $target
    ): ?CompatibilityFinding {
        $curl = $this->curlAssumption($project, $request);
        if (!$this->definition->appliesTo($sourceMajor, $target->major())
            || $curl === null
            || $curl->state() === ExtensionAssumption::PRESENT) {
            return null;
        }

        $metadataId = $evidence->add(
            'laravel-curl-extension',
            Evidence::E2_PACKAGE_METADATA,
            'The declared curl platform assumption does not satisfy Laravel 11 HTTP client requirements.',
            'high',
            [
                'extension' => $curl->name(),
                'state' => $curl->state(),
                'version' => $curl->version(),
                'provenance' => $curl->provenance(),
                'minimum_curl_version' => self::MINIMUM_CURL,
                'target_laravel_major' => $target->major(),
            ]
        )->id();
        $documentationId = $evidence->add(
            'laravel-curl-guidance',
            Evidence::E4_MAINTAINER_DOCUMENTATION,
            'Laravel 11 requires curl 7.34.0 or newer for its HTTP client.',
            'medium',
            [
                'minimum_curl_version' => self::MINIMUM_CURL,
                'source' => 'https://laravel.com/docs/11.x/upgrade',
            ]
        )->id();

        return new CompatibilityFinding(
            'laravel',
            'high',
            sprintf(
                'curl is `%s`; Laravel %d HTTP client usage requires curl %s or newer.',
                'absent',
                $target->major(),
                self::MINIMUM_CURL
            ),
            [$metadataId, $documentationId]
        );
    }

    private function curlAssumption(ProjectState $project, UpgradeRequest $request): ?ExtensionAssumption
    {
        $configured = null;
        foreach ($project->composerJson()->configuredExtensions() as $extension) {
            if ($extension['name'] !== 'ext-curl') {
                continue;
            }
            $configured = ExtensionAssumption::fromComposerConfig(
                'ext-curl',
                $extension['state'] === ExtensionAssumption::ABSENT ? false : (string) $extension['version']
            );
        }

        foreach ($request->extensionAssumptions() as $assumption) {
            if ($assumption->name() === 'ext-curl') {
                return $assumption;
            }
        }

        return $configured;
    }
}
