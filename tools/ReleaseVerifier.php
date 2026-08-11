<?php

declare(strict_types=1);

namespace PhpUpgradePreflight\Tools;

final class ReleaseVerifier
{
    private const ACTIVE_RELEASE_SERIES = '0.2';
    private const ACTIVE_SCHEMA_VERSION = '0.7';

    /** @var array<string, string> */
    private const PACKAGE_NAMES = [
        'core' => 'php-upgrade-preflight/core',
        'cli' => 'php-upgrade-preflight/cli',
        'laravel' => 'php-upgrade-preflight/laravel',
    ];

    /** @var array<string, list<string>> */
    private const EXPECTED_INTERNAL_REQUIREMENTS = [
        'core' => [],
        'cli' => ['php-upgrade-preflight/core'],
        'laravel' => ['php-upgrade-preflight/core'],
    ];

    private string $root;
    private \Closure $fileReader;

    public function __construct(string $root, ?callable $fileReader = null)
    {
        $this->root = rtrim($root, '/\\');
        $this->fileReader = \Closure::fromCallable(
            $fileReader ?? static fn (string $path) => @file_get_contents($path)
        );
    }

    /** @return list<string> */
    public function verify(string $version): array
    {
        if (!preg_match('/^(0|[1-9]\d*)\.(0|[1-9]\d*)\.(0|[1-9]\d*)$/', $version, $matches)) {
            throw new \InvalidArgumentException('Release version must use MAJOR.MINOR.PATCH format.');
        }

        $errors = [];
        $series = $matches[1] . '.' . $matches[2];
        if ($series !== self::ACTIVE_RELEASE_SERIES) {
            return [sprintf(
                'Release series %s.x is locked; only %s.x releases are currently allowed',
                $series,
                self::ACTIVE_RELEASE_SERIES
            )];
        }

        $developmentVersion = $series . '.x-dev';
        $internalConstraint = '^' . $series;
        $rootManifest = $this->readJson($this->root . '/composer.json', $errors);

        foreach (self::PACKAGE_NAMES as $directory => $packageName) {
            $this->expectSame(
                sprintf('root require.%s', $packageName),
                $developmentVersion,
                $rootManifest['require'][$packageName] ?? null,
                $errors
            );

            $repositoryVersion = null;
            foreach (($rootManifest['repositories'] ?? []) as $repository) {
                if (($repository['url'] ?? null) === 'packages/' . $directory) {
                    $repositoryVersion = $repository['options']['versions'][$packageName] ?? null;
                    break;
                }
            }

            $this->expectSame(
                sprintf('root path repository version for %s', $packageName),
                $developmentVersion,
                $repositoryVersion,
                $errors
            );

            $manifest = $this->readJson($this->root . '/packages/' . $directory . '/composer.json', $errors);
            $this->expectSame(
                sprintf('%s branch alias', $packageName),
                $developmentVersion,
                $manifest['extra']['branch-alias']['dev-main'] ?? null,
                $errors
            );

            if (array_key_exists('version', $manifest)) {
                $errors[] = sprintf(
                    '%s must not declare composer.json version; Composer derives release versions from Git tags',
                    $packageName
                );
            }

            $requirements = isset($manifest['require']) && is_array($manifest['require'])
                ? $manifest['require']
                : [];

            foreach (self::EXPECTED_INTERNAL_REQUIREMENTS[$directory] as $internalName) {
                $this->expectSame(
                    sprintf('%s require.%s', $packageName, $internalName),
                    $internalConstraint,
                    $requirements[$internalName] ?? null,
                    $errors
                );
            }

            foreach (self::PACKAGE_NAMES as $internalName) {
                if (
                    array_key_exists($internalName, $requirements)
                    && !in_array($internalName, self::EXPECTED_INTERNAL_REQUIREMENTS[$directory], true)
                ) {
                    $errors[] = sprintf(
                        '%s must not require unexpected internal package %s',
                        $packageName,
                        $internalName
                    );
                }
            }
        }

        $this->verifyReportMetadata($version, $errors);
        $this->verifyChangelog($version, $errors);
        $this->verifyReleaseNotes($version, $errors);

        return $errors;
    }

    /** @param list<string> $errors
     * @return array<string, mixed>
     */
    private function readJson(string $path, array &$errors): array
    {
        try {
            $contents = $this->readFile($path);
            if ($contents === false) {
                throw new \RuntimeException('could not read file');
            }

            $decoded = json_decode($contents, true, 512, JSON_THROW_ON_ERROR);
            if (!is_array($decoded)) {
                throw new \RuntimeException('root value is not an object');
            }

            return $decoded;
        } catch (\Throwable $exception) {
            $errors[] = sprintf('%s: %s', $path, $exception->getMessage());

            return [];
        }
    }

    /** @param list<string> $errors */
    private function expectSame(string $label, string $expected, mixed $actual, array &$errors): void
    {
        if ($actual !== $expected) {
            $errors[] = sprintf(
                '%s must be %s; found %s',
                $label,
                var_export($expected, true),
                var_export($actual, true)
            );
        }
    }

    /** @param list<string> $errors */
    private function verifyReportMetadata(string $version, array &$errors): void
    {
        $metadataPath = $this->root . '/packages/core/src/Model/ReportMetadata.php';
        $metadata = $this->readFile($metadataPath);
        if ($metadata === false || !preg_match("/TOOL_VERSION\s*=\s*'([^']+)'/", $metadata, $toolVersion)) {
            $errors[] = $metadataPath . ': could not find TOOL_VERSION';

            return;
        }

        $this->expectSame('ReportMetadata::TOOL_VERSION', $version, $toolVersion[1], $errors);

        if (!preg_match("/SCHEMA_VERSION\s*=\s*'([^']+)'/", $metadata, $schemaVersion)) {
            $errors[] = $metadataPath . ': could not find SCHEMA_VERSION';

            return;
        }

        $this->expectSame(
            'ReportMetadata::SCHEMA_VERSION',
            self::ACTIVE_SCHEMA_VERSION,
            $schemaVersion[1],
            $errors
        );
    }

    /** @param list<string> $errors */
    private function verifyChangelog(string $version, array &$errors): void
    {
        $changelog = $this->readFile($this->root . '/CHANGELOG.md');
        if ($changelog === false || !preg_match(
            '/^## \[' . preg_quote($version, '/') . '\] - \d{4}-\d{2}-\d{2}$/m',
            $changelog
        )) {
            $errors[] = sprintf('CHANGELOG.md must contain a dated [%s] release heading', $version);
        }
    }

    /** @param list<string> $errors */
    private function verifyReleaseNotes(string $version, array &$errors): void
    {
        $path = $this->root . '/docs/releases/v' . $version . '.md';
        if (!is_file($path)) {
            $errors[] = sprintf('missing release notes: %s', $path);

            return;
        }

        $notes = $this->readFile($path);
        if ($notes === false) {
            $errors[] = sprintf('could not read release notes: %s', $path);

            return;
        }

        $expectedHeading = '# PHP Upgrade Preflight v' . $version;
        $lines = preg_split('/\R/', $notes);
        $heading = is_array($lines) && $lines !== [] ? trim($lines[0]) : '';
        $this->expectSame('release notes heading', $expectedHeading, $heading, $errors);

        $body = is_array($lines) ? trim(implode("\n", array_slice($lines, 1))) : '';
        if ($body === '') {
            $errors[] = sprintf('release notes must contain content after the heading: %s', $path);
        }
    }

    private function readFile(string $path): string|false
    {
        return ($this->fileReader)($path);
    }
}
