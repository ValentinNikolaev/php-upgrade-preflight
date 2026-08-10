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
use PhpUpgradePreflight\Core\Model\UpgradeRequest;
use PhpUpgradePreflight\Laravel\Catalog\BuiltinRuleDefinition;

final class LaravelComposerVersionRule implements CompatibilityRule, HopAwareCompatibilityRule
{
    private const MINIMUM_COMPOSER = '2.2.0';

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
        return null;
    }

    public function evaluateForHop(
        ProjectState $project,
        UpgradeRequest $request,
        EvidenceLedger $evidence,
        FrameworkHop $hop,
        ?string $composerVersion = null,
        array $sourceUsages = []
    ): ?CompatibilityFinding {
        if (!$this->definition->appliesTo($hop->fromMajor(), $hop->toMajor())
            || $composerVersion === null
            || LaravelTarget::versionSatisfies($composerVersion, '>=' . self::MINIMUM_COMPOSER)) {
            return null;
        }

        $metadataId = $evidence->add(
            'laravel-composer-version',
            Evidence::E1_SOLVER,
            'The Composer runtime used for dependency scenarios is older than the Laravel target minimum.',
            'high',
            [
                'composer_version' => $composerVersion,
                'minimum_composer_version' => self::MINIMUM_COMPOSER,
                'target_laravel_major' => $hop->toMajor(),
            ]
        )->id();
        $documentationId = $evidence->add(
            'laravel-composer-guidance',
            Evidence::E4_MAINTAINER_DOCUMENTATION,
            'Laravel 10 requires Composer 2.2.0 or newer.',
            'medium',
            [
                'minimum_composer_version' => self::MINIMUM_COMPOSER,
                'source' => 'https://laravel.com/docs/10.x/upgrade',
            ]
        )->id();

        return new CompatibilityFinding(
            'laravel',
            'high',
            sprintf(
                'Composer `%s` predates Composer %s; update Composer before targeting Laravel %d.',
                $composerVersion,
                self::MINIMUM_COMPOSER,
                $hop->toMajor()
            ),
            [$metadataId, $documentationId]
        );
    }
}
