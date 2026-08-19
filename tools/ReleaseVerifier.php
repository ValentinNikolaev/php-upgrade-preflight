<?php

declare(strict_types=1);

namespace PhpUpgradePreflight\Tools;

final class ReleaseVerifier
{
    private const ACTIVE_RELEASE_SERIES = '0.3';
    private const ACTIVE_SCHEMA_VERSION = '0.8';

    private const WIKI_EVIDENCE_SCHEMA_VERSION = 1;

    /** @var array<string, string> */
    private const WIKI_DESTINATIONS = [
        'common' => 'ValentinNikolaev/php-upgrade-preflight',
        'core' => 'ValentinNikolaev/php-upgrade-preflight-core',
        'cli' => 'ValentinNikolaev/php-upgrade-preflight-cli',
        'laravel' => 'ValentinNikolaev/php-upgrade-preflight-laravel',
    ];

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
        $this->verifyWikiEvidence($version, $errors);

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

        $evidenceFile = 'v' . $version . '-wiki-evidence.json';
        if (!str_contains($notes, '](' . $evidenceFile . ')')) {
            $errors[] = sprintf(
                'release notes must link machine-readable Wiki evidence %s',
                $evidenceFile
            );
        }
    }

    /** @param list<string> $errors */
    private function verifyWikiEvidence(string $version, array &$errors): void
    {
        $path = $this->root . '/docs/releases/v' . $version . '-wiki-evidence.json';
        $evidence = $this->readJson($path, $errors);
        if ($evidence === []) {
            return;
        }

        $this->verifyExactKeys(
            'Wiki evidence root',
            ['$schema', 'schema_version', 'evidence_mode', 'release', 'materialization_gate', 'destinations'],
            $evidence,
            $errors
        );

        $this->expectSame(
            'Wiki evidence schema_version',
            (string) self::WIKI_EVIDENCE_SCHEMA_VERSION,
            isset($evidence['schema_version']) ? (string) $evidence['schema_version'] : null,
            $errors
        );
        $this->expectSame(
            'Wiki evidence $schema',
            'wiki-evidence.schema.json',
            $evidence['$schema'] ?? null,
            $errors
        );
        $this->expectSame(
            'Wiki evidence evidence_mode',
            'release-candidate',
            $evidence['evidence_mode'] ?? null,
            $errors
        );
        $this->expectSame('Wiki evidence release', $version, $evidence['release'] ?? null, $errors);
        $this->expectSame(
            'Wiki evidence materialization_gate',
            'php tools/materialize-release-wikis.php --check',
            $evidence['materialization_gate'] ?? null,
            $errors
        );

        $destinations = $evidence['destinations'] ?? null;
        if (!is_array($destinations) || $destinations !== array_values($destinations)) {
            $errors[] = 'Wiki evidence destinations must be a JSON array containing all four Wiki sets';

            return;
        }

        $seen = [];
        foreach ($destinations as $index => $destination) {
            if (!is_array($destination)) {
                $errors[] = sprintf('Wiki evidence destinations[%d] must be an object', $index);

                continue;
            }

            $set = $destination['set'] ?? null;
            if (!is_string($set) || !array_key_exists($set, self::WIKI_DESTINATIONS)) {
                $errors[] = sprintf('Wiki evidence destinations[%d] has unknown set %s', $index, var_export($set, true));

                continue;
            }
            if (isset($seen[$set])) {
                $errors[] = sprintf('Wiki evidence contains duplicate %s destination', $set);

                continue;
            }
            $seen[$set] = true;

            $this->verifyExactKeys(
                sprintf('Wiki evidence %s destination', $set),
                ['set', 'destination_repository', 'wiki_repository', 'manifest', 'result'],
                $destination,
                $errors
            );

            $repository = self::WIKI_DESTINATIONS[$set];
            $this->expectSame(
                sprintf('Wiki evidence %s destination_repository', $set),
                $repository,
                $destination['destination_repository'] ?? null,
                $errors
            );
            $this->expectSame(
                sprintf('Wiki evidence %s wiki_repository', $set),
                'https://github.com/' . $repository . '.wiki.git',
                $destination['wiki_repository'] ?? null,
                $errors
            );
            $this->expectSame(
                sprintf('Wiki evidence %s manifest', $set),
                'release-wikis/' . $set . '/wiki-manifest.json',
                $destination['manifest'] ?? null,
                $errors
            );
            $this->verifyWikiPublicationResult($set, $destination['result'] ?? null, $errors);
        }

        foreach (array_keys(self::WIKI_DESTINATIONS) as $set) {
            if (!isset($seen[$set])) {
                $errors[] = sprintf('Wiki evidence is missing required %s destination', $set);
            }
        }
    }

    /** @param list<string> $errors */
    private function verifyWikiPublicationResult(string $set, mixed $result, array &$errors): void
    {
        if (!is_array($result)) {
            $errors[] = sprintf('Wiki evidence %s result must be an object', $set);

            return;
        }

        $status = $result['status'] ?? null;
        if ($status === 'published') {
            $this->verifyExactKeys(
                sprintf('Wiki evidence %s published result', $set),
                ['status', 'wiki_commit'],
                $result,
                $errors
            );
            $this->verifyCommitSha(
                sprintf('Wiki evidence %s wiki_commit', $set),
                $result['wiki_commit'] ?? null,
                $errors
            );

            return;
        }

        if ($status === 'unchanged-after-review') {
            $this->verifyExactKeys(
                sprintf('Wiki evidence %s unchanged result', $set),
                ['status', 'reviewed_remote_commit', 'inventory_check'],
                $result,
                $errors
            );
            $this->verifyCommitSha(
                sprintf('Wiki evidence %s reviewed_remote_commit', $set),
                $result['reviewed_remote_commit'] ?? null,
                $errors
            );
            $this->expectSame(
                sprintf('Wiki evidence %s inventory_check', $set),
                'passed',
                $result['inventory_check'] ?? null,
                $errors
            );

            return;
        }

        $errors[] = sprintf(
            'Wiki evidence %s result status must be published or unchanged-after-review; found %s',
            $set,
            var_export($status, true)
        );
    }

    /** @param list<string> $expected
     * @param array<string, mixed> $actual
     * @param list<string> $errors
     */
    private function verifyExactKeys(string $label, array $expected, array $actual, array &$errors): void
    {
        $keys = array_keys($actual);
        sort($expected);
        sort($keys);
        if ($keys !== $expected) {
            $errors[] = sprintf(
                '%s must contain exactly [%s]; found [%s]',
                $label,
                implode(', ', $expected),
                implode(', ', $keys)
            );
        }
    }

    /** @param list<string> $errors */
    private function verifyCommitSha(string $label, mixed $value, array &$errors): void
    {
        if (!is_string($value) || preg_match('/^[0-9a-f]{40}$/', $value) !== 1) {
            $errors[] = sprintf('%s must be a full lowercase 40-character Git SHA', $label);
        }
    }

    private function readFile(string $path): string|false
    {
        return ($this->fileReader)($path);
    }
}
