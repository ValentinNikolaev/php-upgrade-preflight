<?php

declare(strict_types=1);

namespace PhpUpgradePreflight\Laravel;

use PhpUpgradePreflight\Core\Model\UpgradeRequest;

/**
 * Reads the Laravel-family package targets out of an upgrade request.
 *
 * Transition assessment and stage planning both need the same answer to "which
 * requested targets belong to Laravel", and both put that answer into evidence
 * context, so the definition of the family lives in one place rather than being
 * restated by each collaborator.
 */
final class LaravelRequestTargets
{
    public static function present(UpgradeRequest $request): bool
    {
        foreach ($request->targets()->packageTargets() as $target) {
            if (self::isLaravelFamily(strtolower($target->package()))) {
                return true;
            }
        }

        return false;
    }

    /**
     * The requested Laravel-family constraints, keyed and ordered by package name.
     *
     * @return array<string, string>
     */
    public static function constraints(UpgradeRequest $request): array
    {
        $constraints = [];
        foreach ($request->targets()->packageTargets() as $target) {
            $package = strtolower($target->package());
            if (self::isLaravelFamily($package)) {
                $constraints[$package] = $target->constraint();
            }
        }
        ksort($constraints);

        return $constraints;
    }

    private static function isLaravelFamily(string $package): bool
    {
        return $package === 'laravel/framework' || str_starts_with($package, 'illuminate/');
    }
}
