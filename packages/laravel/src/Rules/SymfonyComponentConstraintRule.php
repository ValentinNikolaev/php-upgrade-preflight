<?php

declare(strict_types=1);

namespace PhpUpgradePreflight\Laravel\Rules;

use PhpUpgradePreflight\Core\Framework\CompatibilityRule;
use PhpUpgradePreflight\Core\Model\CompatibilityFinding;
use PhpUpgradePreflight\Core\Model\Evidence;
use PhpUpgradePreflight\Core\Model\EvidenceLedger;
use PhpUpgradePreflight\Core\Model\ProjectState;
use PhpUpgradePreflight\Core\Model\UpgradeRequest;
use PhpUpgradePreflight\Laravel\Catalog\BuiltinRuleDefinition;
use PhpUpgradePreflight\Laravel\Catalog\LaravelRuleCatalog;

final class SymfonyComponentConstraintRule implements CompatibilityRule
{
    private const COMPONENTS = [
        'symfony/cache',
        'symfony/console',
        'symfony/error-handler',
        'symfony/filesystem',
        'symfony/finder',
        'symfony/http-client',
        'symfony/http-foundation',
        'symfony/http-kernel',
        'symfony/mailer',
        'symfony/mime',
        'symfony/process',
        'symfony/routing',
        'symfony/uid',
        'symfony/var-dumper',
    ];

    private BuiltinRuleDefinition $definition;
    private LaravelRuleCatalog $catalog;

    public function __construct(BuiltinRuleDefinition $definition, LaravelRuleCatalog $catalog)
    {
        $this->definition = $definition;
        $this->catalog = $catalog;
    }

    public function evaluate(
        ProjectState $project,
        UpgradeRequest $request,
        EvidenceLedger $evidence,
        array $sourceUsages = []
    ): ?CompatibilityFinding {
        $target = LaravelTarget::fromRequest($request);
        $sourceMajor = LaravelSource::fromProject($project)->major();
        $targetDefinition = $target === null ? null : $this->catalog->target($target->major());
        if ($target === null
            || $sourceMajor === null
            || $targetDefinition === null
            || !$this->definition->appliesTo($sourceMajor, $target->major())) {
            return null;
        }

        $compatibleRange = $targetDefinition->symfonyConstraint();
        if ($compatibleRange === null) {
            return null;
        }
        $incompatible = [];
        foreach ($project->composerJson()->rootRequirements() as $package => $constraint) {
            if (!in_array($package, self::COMPONENTS, true)
                || LaravelTarget::constraintsIntersect($constraint, $compatibleRange)) {
                continue;
            }

            $incompatible[$package] = $constraint;
        }

        if ($incompatible === []) {
            return null;
        }

        ksort($incompatible);
        $metadataId = $evidence->add(
            'laravel-symfony-constraints',
            Evidence::E2_PACKAGE_METADATA,
            'Root Symfony component constraints exclude the component major used by the Laravel target.',
            'high',
            [
                'root_constraints' => $incompatible,
                'target_laravel_major' => $target->major(),
                'compatible_symfony_constraint' => $compatibleRange,
            ]
        )->id();
        $documentationId = $evidence->add(
            'laravel-symfony-guidance',
            Evidence::E4_MAINTAINER_DOCUMENTATION,
            sprintf('Laravel %d maps its core Symfony components to `%s`.', $target->major(), $compatibleRange),
            'medium',
            [
                'target_laravel_major' => $target->major(),
                'compatible_symfony_constraint' => $compatibleRange,
                'source' => $targetDefinition->symfonySources()[0],
            ]
        )->id();

        return new CompatibilityFinding(
            'laravel',
            'high',
            sprintf(
                'Review direct Symfony component constraints for Laravel %d (`%s` expected): %s.',
                $target->major(),
                $compatibleRange,
                implode(', ', array_keys($incompatible))
            ),
            [$metadataId, $documentationId]
        );
    }
}
