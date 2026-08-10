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
use PhpUpgradePreflight\Laravel\Catalog\LaravelRuleCatalog;

final class SymfonyComponentConstraintRule implements CompatibilityRule, HopAwareCompatibilityRule
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
        return $target === null || $sourceMajor === null
            ? null
            : $this->evaluateTransition($project, $evidence, $sourceMajor, $target);
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

            return $this->evaluateTransition($project, $evidence, $requestedSource, $requestedTarget);
        }

        return $this->evaluateTransition(
            $project,
            $evidence,
            $hop->fromMajor(),
            LaravelTarget::forMajor($hop->toMajor())
        );
    }

    private function evaluateTransition(
        ProjectState $project,
        EvidenceLedger $evidence,
        int $sourceMajor,
        LaravelTarget $target
    ): ?CompatibilityFinding {
        $targetDefinition = $this->catalog->target($target->major());
        if ($targetDefinition === null || !$this->definition->appliesTo($sourceMajor, $target->major())) {
            return null;
        }

        $incompatible = [];
        $compatibleRanges = [];
        foreach ($project->composerJson()->rootRequirements() as $package => $constraint) {
            $compatibleRange = $targetDefinition->symfonyConstraintFor($package);
            if (!in_array($package, self::COMPONENTS, true)
                || $compatibleRange === null
                || LaravelTarget::constraintsIntersect($constraint, $compatibleRange)) {
                continue;
            }

            $incompatible[$package] = $constraint;
            $compatibleRanges[$package] = $compatibleRange;
        }

        if ($incompatible === []) {
            return null;
        }

        ksort($incompatible);
        ksort($compatibleRanges);
        $uniqueRanges = array_values(array_unique(array_values($compatibleRanges)));
        $compatibleContext = count($uniqueRanges) === 1
            ? ['compatible_symfony_constraint' => $uniqueRanges[0]]
            : ['compatible_symfony_constraints' => $compatibleRanges];
        $metadataId = $evidence->add(
            'laravel-symfony-constraints',
            Evidence::E2_PACKAGE_METADATA,
            'Root Symfony component constraints exclude the component major used by the Laravel target.',
            'high',
            array_merge([
                'root_constraints' => $incompatible,
                'target_laravel_major' => $target->major(),
            ], $compatibleContext)
        )->id();
        $documentationId = $evidence->add(
            'laravel-symfony-guidance',
            Evidence::E4_MAINTAINER_DOCUMENTATION,
            count($uniqueRanges) === 1
                ? sprintf('Laravel %d maps its core Symfony components to `%s`.', $target->major(), $uniqueRanges[0])
                : sprintf('Laravel %d maps its core Symfony components to package-specific constraints.', $target->major()),
            'medium',
            array_merge(
                ['target_laravel_major' => $target->major()],
                $compatibleContext,
                ['source' => $targetDefinition->symfonySources()[0]]
            )
        )->id();

        $expected = count($uniqueRanges) === 1
            ? sprintf('`%s` expected', $uniqueRanges[0])
            : 'package-specific constraints expected';
        $packages = count($uniqueRanges) === 1
            ? array_keys($incompatible)
            : array_map(
                static fn (string $package): string => sprintf('%s (`%s`)', $package, $compatibleRanges[$package]),
                array_keys($incompatible)
            );

        return new CompatibilityFinding(
            'laravel',
            'high',
            sprintf(
                'Review direct Symfony component constraints for Laravel %d (%s): %s.',
                $target->major(),
                $expected,
                implode(', ', $packages)
            ),
            [$metadataId, $documentationId]
        );
    }
}
