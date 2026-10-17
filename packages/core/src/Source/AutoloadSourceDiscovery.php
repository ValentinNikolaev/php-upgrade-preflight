<?php

declare(strict_types=1);

namespace PhpUpgradePreflight\Core\Source;

use PhpUpgradePreflight\Core\Model\Evidence;
use PhpUpgradePreflight\Core\Model\EvidenceLedger;
use PhpUpgradePreflight\Core\Model\ProjectState;
use Symfony\Component\Filesystem\Path;

/** Root Composer metadata only: never executes loaders or follows package repositories. */
final class AutoloadSourceDiscovery
{
    private const MAX_PATHS = 256;

    /**
     * @param list<string> $uncertainties
     * @return list<string>
     */
    public function paths(ProjectState $project, array &$uncertainties, ?EvidenceLedger $evidence): array
    {
        $paths = [];
        $seen = 0;
        foreach (['autoload' => $project->composerJson()->autoload(), 'autoload-dev' => $project->composerJson()->autoloadDev()] as $section => $autoload) {
            foreach (['psr-4', 'psr-0', 'classmap', 'files'] as $type) {
                $mappings = $autoload[$type] ?? [];
                if (!is_array($mappings)) {
                    $this->omission($section . '.' . $type, 'unsupported metadata', $uncertainties, $evidence);
                    continue;
                }
                foreach ($mappings as $mapping) {
                    foreach (is_array($mapping) ? $mapping : [$mapping] as $path) {
                        if (++$seen > self::MAX_PATHS) {
                            $this->omission('autoload', 'the 256-path metadata safety limit was reached', $uncertainties, $evidence);
                            break 4;
                        }
                        if (!is_string($path)) {
                            $this->omission($section . '.' . $type, 'unsupported path metadata', $uncertainties, $evidence);
                            continue;
                        }
                        $path = trim(str_replace('\\', '/', $path));
                        $path = $path === '' ? '.' : $path;
                        $reason = $this->excludedReason($project, $path);
                        if ($reason !== null) {
                            $this->omission($path, $reason, $uncertainties, $evidence);
                            continue;
                        }
                        $paths[] = $path;
                    }
                }
            }
        }
        $paths = array_values(array_unique($paths));
        sort($paths, SORT_STRING);

        return $paths;
    }

    private function excludedReason(ProjectState $project, string $path): ?string
    {
        if (Path::isAbsolute($path) || strpbrk($path, '*?[') !== false || strpos($path, "\0") !== false) {
            return 'an absolute, wildcard or unsupported path';
        }
        $root = rtrim(Path::canonicalize((string) (realpath($project->path()) ?: $project->path())), '/');
        $full = Path::join($root, $path);
        $resolved = realpath($full);
        if ($resolved === false) {
            return 'the path does not exist';
        }
        $resolved = Path::canonicalize($resolved);
        $comparisonRoot = DIRECTORY_SEPARATOR === '\\' ? strtolower($root) : $root;
        $comparisonResolved = DIRECTORY_SEPARATOR === '\\' ? strtolower($resolved) : $resolved;
        if ($comparisonResolved !== $comparisonRoot && !str_starts_with($comparisonResolved, $comparisonRoot . '/')) {
            return 'the path resolves outside the analyzed project';
        }
        $relative = strtolower(Path::makeRelative($resolved, $root));
        $vendor = strtolower(trim(Path::canonicalize($project->composerJson()->vendorDirectory()), '/'));
        if ($relative === $vendor || str_starts_with($relative, $vendor . '/')) {
            return 'the path is in the dependency directory';
        }
        $segments = explode('/', $relative);
        if (array_intersect($segments, ['.git', '.cache', 'generated', 'node_modules', 'vendor']) !== []) {
            return 'the path is in an excluded dependency or generated directory';
        }
        foreach (['bootstrap/cache', 'storage/framework', 'var/cache'] as $excluded) {
            if (strpos('/' . $relative . '/', '/' . $excluded . '/') !== false) {
                return 'the path is in an excluded cache directory';
            }
        }

        return null;
    }

    /** @param list<string> $uncertainties */
    private function omission(string $path, string $reason, array &$uncertainties, ?EvidenceLedger $evidence): void
    {
        $message = sprintf('Composer source root "%s" was not discovered: %s; modular source coverage may be incomplete.', $path, $reason);
        if ($evidence !== null) {
            $id = $evidence->add('source-discovery', Evidence::E2_PACKAGE_METADATA, 'A Composer autoload source root was excluded from default discovery.', 'high', [
                'path' => $path,
                'reason' => $reason,
            ])->id();
            $message .= ' (' . $id . ')';
        }
        $uncertainties[] = $message;
    }
}
