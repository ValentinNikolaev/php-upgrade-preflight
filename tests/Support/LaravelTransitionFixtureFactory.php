<?php

declare(strict_types=1);

namespace PhpUpgradePreflight\Tests\Support;

use PhpUpgradePreflight\Core\Analysis\DefaultUpgradeAnalyzer;
use PhpUpgradePreflight\Laravel\Catalog\LaravelRuleCatalog;
use PhpUpgradePreflight\Laravel\Catalog\TransitionDefinition;
use PhpUpgradePreflight\Laravel\LaravelFrameworkIntegration;

final class LaravelTransitionFixtureFactory
{
    public static function analyzer(string $catalogVariant = 'default'): DefaultUpgradeAnalyzer
    {
        $integration = self::integration($catalogVariant);

        return new DefaultUpgradeAnalyzer(
            [$integration],
            null,
            LaravelTransitionFixtureRunner::create()
        );
    }

    public static function integration(string $catalogVariant = 'default'): LaravelFrameworkIntegration
    {
        if ($catalogVariant === 'default') {
            return new LaravelFrameworkIntegration();
        }
        if ($catalogVariant !== 'missing-11-to-12') {
            throw new \InvalidArgumentException(sprintf('Unknown fixture catalog variant: %s.', $catalogVariant));
        }

        $catalog = LaravelRuleCatalog::v0_2();
        $transitions = array_map(
            static function (TransitionDefinition $transition): TransitionDefinition {
                if ($transition->key() !== 'adjacent-11-12') {
                    return $transition;
                }

                return new TransitionDefinition(
                    $transition->key(),
                    $transition->sourceMajor(),
                    $transition->targetMajor(),
                    $transition->kind(),
                    null,
                    $transition->sources()
                );
            },
            $catalog->transitions()
        );
        $fixtureCatalog = new LaravelRuleCatalog(
            $catalog->version(),
            $catalog->minimumMajor(),
            $catalog->maximumMajor(),
            $catalog->targets(),
            $transitions,
            $catalog->rules(),
            $catalog->skeletonPatterns()
        );

        return new LaravelFrameworkIntegration(null, $fixtureCatalog);
    }
}
