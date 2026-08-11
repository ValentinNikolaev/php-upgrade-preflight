<?php

declare(strict_types=1);

namespace PhpUpgradePreflight\Tools;

final class InstalledPackageReferenceVerifier
{
    /** @var list<string> */
    private const PACKAGE_NAMES = [
        'php-upgrade-preflight/core',
        'php-upgrade-preflight/cli',
        'php-upgrade-preflight/laravel',
    ];

    /**
     * @param array<string, string> $expectedReferences
     * @return list<string>
     */
    public function verify(string $lockPath, string $version, array $expectedReferences): array
    {
        $contents = @file_get_contents($lockPath);
        if ($contents === false) {
            throw new \RuntimeException('Unable to read Composer lock file: ' . $lockPath);
        }

        $lock = json_decode($contents, true, 512, JSON_THROW_ON_ERROR);
        if (!is_array($lock)) {
            throw new \RuntimeException('Composer lock root must be an object.');
        }

        $installed = [];
        foreach (($lock['packages'] ?? []) as $package) {
            if (is_array($package) && is_string($package['name'] ?? null)) {
                $installed[$package['name']] = $package;
            }
        }

        $errors = [];
        foreach (self::PACKAGE_NAMES as $packageName) {
            $package = $installed[$packageName] ?? null;
            if (!is_array($package)) {
                $errors[] = sprintf('Published quick start did not install %s.', $packageName);

                continue;
            }

            $actualVersion = ltrim((string) ($package['version'] ?? ''), 'v');
            if ($actualVersion !== $version) {
                $errors[] = sprintf(
                    'Published quick start installed %s at %s; expected %s.',
                    $packageName,
                    $actualVersion === '' ? 'an unknown version' : $actualVersion,
                    $version
                );
            }

            $expectedReference = $expectedReferences[$packageName] ?? null;
            if (!is_string($expectedReference) || $expectedReference === '') {
                $errors[] = sprintf('Missing expected signed-tag reference for %s.', $packageName);

                continue;
            }

            foreach (['source', 'dist'] as $transport) {
                $actualReference = $package[$transport]['reference'] ?? null;
                if ($actualReference !== $expectedReference) {
                    $errors[] = sprintf(
                        'Published %s %s reference is %s; expected signed-tag commit %s.',
                        $packageName,
                        $transport,
                        is_string($actualReference) && $actualReference !== '' ? $actualReference : 'missing',
                        $expectedReference
                    );
                }
            }
        }

        return $errors;
    }
}
