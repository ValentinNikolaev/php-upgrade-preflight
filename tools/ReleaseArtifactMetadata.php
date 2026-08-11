<?php

declare(strict_types=1);

namespace PhpUpgradePreflight\Tools;

final class ReleaseArtifactMetadata
{
    /** @var array<string, string> */
    private const PACKAGES = [
        'core' => 'php-upgrade-preflight/core',
        'cli' => 'php-upgrade-preflight/cli',
        'laravel' => 'php-upgrade-preflight/laravel',
    ];

    private string $root;

    public function __construct(string $root)
    {
        $this->root = rtrim($root, '/\\');
    }

    /**
     * @param array{repository:string, commit:string, ref:string, workflow:string, run_uri:string} $source
     */
    public function generate(string $version, string $distDirectory, array $source): void
    {
        $this->assertVersion($version);
        $this->assertSource($version, $source);

        $distDirectory = rtrim($distDirectory, '/\\');
        if (!is_dir($distDirectory)) {
            throw new \RuntimeException(sprintf('Distribution directory does not exist: %s', $distDirectory));
        }

        $artifacts = [];
        foreach (self::PACKAGES as $slug => $packageName) {
            $filename = sprintf('php-upgrade-preflight-%s-v%s.zip', $slug, $version);
            $path = $distDirectory . DIRECTORY_SEPARATOR . $filename;
            if (!is_file($path)) {
                throw new \RuntimeException(sprintf('Missing release archive: %s', $path));
            }

            $artifacts[] = $this->artifactRecord($filename, $path, $packageName);
        }

        $inventoryPath = $distDirectory . DIRECTORY_SEPARATOR . 'DEPENDENCY-INVENTORY.json';
        $this->writeJson($inventoryPath, $this->dependencyInventory($version));

        $provenance = [
            'schema_version' => 1,
            'release_version' => $version,
            'release_tag' => 'v' . $version,
            'source' => [
                'repository' => $source['repository'],
                'commit' => $source['commit'],
                'ref' => $source['ref'],
            ],
            'build' => [
                'provider' => 'github-actions',
                'workflow' => $source['workflow'],
                'run_uri' => $source['run_uri'],
            ],
            'artifacts' => $artifacts,
        ];
        $provenancePath = $distDirectory . DIRECTORY_SEPARATOR . 'ARTIFACT-PROVENANCE.json';
        $this->writeJson($provenancePath, $provenance);

        $checksumFiles = array_merge(
            array_column($artifacts, 'name'),
            ['ARTIFACT-PROVENANCE.json', 'DEPENDENCY-INVENTORY.json']
        );
        sort($checksumFiles, SORT_STRING);

        $lines = [];
        foreach ($checksumFiles as $filename) {
            $hash = hash_file('sha256', $distDirectory . DIRECTORY_SEPARATOR . $filename);
            if ($hash === false) {
                throw new \RuntimeException(sprintf('Unable to hash release asset: %s', $filename));
            }
            $lines[] = $hash . '  ' . $filename;
        }

        $this->writeFile($distDirectory . DIRECTORY_SEPARATOR . 'SHA256SUMS', implode("\n", $lines) . "\n");
    }

    /**
     * @param array{repository:string, commit:string, ref:string, workflow:string, run_uri:string}|null $expectedSource
     * @return list<string>
     */
    public function verify(string $version, string $distDirectory, ?array $expectedSource = null): array
    {
        $this->assertVersion($version);
        if ($expectedSource !== null) {
            $this->assertSource($version, $expectedSource);
        }
        $distDirectory = rtrim($distDirectory, '/\\');
        $errors = [];

        $inventory = $this->readJson($distDirectory . DIRECTORY_SEPARATOR . 'DEPENDENCY-INVENTORY.json', $errors);
        $this->verifyInventory($version, $inventory, $errors);

        $provenance = $this->readJson($distDirectory . DIRECTORY_SEPARATOR . 'ARTIFACT-PROVENANCE.json', $errors);
        $this->verifyProvenance($version, $distDirectory, $provenance, $expectedSource, $errors);

        $checksumFiles = array_merge(
            array_keys($this->expectedArtifacts($version)),
            ['ARTIFACT-PROVENANCE.json', 'DEPENDENCY-INVENTORY.json']
        );
        sort($checksumFiles, SORT_STRING);
        $this->verifyChecksums($distDirectory, $checksumFiles, $errors);

        return $errors;
    }

    /** @param array<string, mixed> $inventory
     * @param list<string> $errors
     */
    private function verifyInventory(string $version, array $inventory, array &$errors): void
    {
        if (($inventory['schema_version'] ?? null) !== 1) {
            $errors[] = 'Dependency inventory schema_version must be 1.';
        }
        if (($inventory['release_version'] ?? null) !== $version) {
            $errors[] = 'Dependency inventory release_version does not match the requested release.';
        }
        if (!is_string($inventory['composer_lock_content_hash'] ?? null)) {
            $errors[] = 'Dependency inventory must record the Composer lock content hash.';
        }

        $releasedPackages = $inventory['released_packages'] ?? null;
        if (!is_array($releasedPackages)) {
            $errors[] = 'Dependency inventory released_packages must be an array.';
        } else {
            $actual = [];
            foreach ($releasedPackages as $package) {
                if (!is_array($package) || !is_string($package['name'] ?? null)) {
                    $errors[] = 'Dependency inventory contains an invalid released package record.';
                    continue;
                }
                $name = $package['name'];
                if (isset($actual[$name])) {
                    $errors[] = sprintf('Dependency inventory repeats released package %s.', $name);
                    continue;
                }
                $actual[$name] = true;
                if (($package['version'] ?? null) !== $version) {
                    $errors[] = sprintf('Dependency inventory version mismatch for %s.', $name);
                }
                if (!is_array($package['runtime_requirements'] ?? null)) {
                    $errors[] = sprintf('Dependency inventory runtime requirements are invalid for %s.', $name);
                }
            }

            $expected = array_values(self::PACKAGES);
            sort($expected, SORT_STRING);
            $actualNames = array_keys($actual);
            sort($actualNames, SORT_STRING);
            if ($actualNames !== $expected) {
                $errors[] = 'Dependency inventory must describe exactly the three released packages.';
            }
        }

        $lockedDependencies = $inventory['locked_build_dependencies'] ?? null;
        if (!is_array($lockedDependencies)) {
            $errors[] = 'Dependency inventory locked_build_dependencies must be an array.';
            return;
        }
        foreach ($lockedDependencies as $dependency) {
            if (
                !is_array($dependency)
                || !is_string($dependency['name'] ?? null)
                || !is_string($dependency['version'] ?? null)
                || !is_bool($dependency['development'] ?? null)
            ) {
                $errors[] = 'Dependency inventory contains an invalid locked dependency record.';
            }
        }

        try {
            if ($inventory != $this->dependencyInventory($version)) {
                $errors[] = 'Dependency inventory does not match the checked-out manifests and Composer lock.';
            }
        } catch (\Throwable $exception) {
            $errors[] = 'Could not derive the expected dependency inventory: ' . $exception->getMessage();
        }
    }

    /**
     * @param array<string, mixed> $provenance
     * @param array{repository:string, commit:string, ref:string, workflow:string, run_uri:string}|null $expectedSource
     * @param list<string> $errors
     */
    private function verifyProvenance(
        string $version,
        string $distDirectory,
        array $provenance,
        ?array $expectedSource,
        array &$errors
    ): void {
        if (($provenance['schema_version'] ?? null) !== 1) {
            $errors[] = 'Artifact provenance schema_version must be 1.';
        }
        if (($provenance['release_version'] ?? null) !== $version) {
            $errors[] = 'Artifact provenance release_version does not match the requested release.';
        }
        if (($provenance['release_tag'] ?? null) !== 'v' . $version) {
            $errors[] = 'Artifact provenance release_tag does not match the requested release.';
        }

        $source = $provenance['source'] ?? null;
        if (!is_array($source)) {
            $errors[] = 'Artifact provenance source must be an object.';
        } else {
            if (!is_string($source['repository'] ?? null) || $source['repository'] === '') {
                $errors[] = 'Artifact provenance source repository is missing.';
            }
            if (!is_string($source['commit'] ?? null) || preg_match('/^[0-9a-f]{40}$/', $source['commit']) !== 1) {
                $errors[] = 'Artifact provenance source commit must be a full lowercase Git SHA.';
            }
            if (!is_string($source['ref'] ?? null) || $source['ref'] === '') {
                $errors[] = 'Artifact provenance source ref is missing.';
            } elseif (str_starts_with($source['ref'], 'refs/tags/') && $source['ref'] !== 'refs/tags/v' . $version) {
                $errors[] = 'Artifact provenance source tag does not match the release version.';
            }
        }

        $build = $provenance['build'] ?? null;
        if (
            !is_array($build)
            || ($build['provider'] ?? null) !== 'github-actions'
            || !is_string($build['workflow'] ?? null)
            || $build['workflow'] === ''
            || !is_string($build['run_uri'] ?? null)
            || $build['run_uri'] === ''
        ) {
            $errors[] = 'Artifact provenance build metadata is invalid.';
        }

        if ($expectedSource !== null) {
            $expectedProvenanceSource = [
                'repository' => $expectedSource['repository'],
                'commit' => $expectedSource['commit'],
                'ref' => $expectedSource['ref'],
            ];
            if ($source !== $expectedProvenanceSource) {
                $errors[] = 'Artifact provenance source does not match the current release workflow inputs.';
            }
            $expectedBuild = [
                'provider' => 'github-actions',
                'workflow' => $expectedSource['workflow'],
                'run_uri' => $expectedSource['run_uri'],
            ];
            if ($build !== $expectedBuild) {
                $errors[] = 'Artifact provenance build does not match the current release workflow inputs.';
            }
        }

        $expectedArtifacts = $this->expectedArtifacts($version);
        $actualArtifacts = [];
        $artifacts = $provenance['artifacts'] ?? null;
        if (!is_array($artifacts)) {
            $errors[] = 'Artifact provenance artifacts must be an array.';

            return;
        }
        foreach ($artifacts as $artifact) {
            if (!is_array($artifact) || !is_string($artifact['name'] ?? null)) {
                $errors[] = 'Artifact provenance contains an invalid artifact record.';
                continue;
            }

            $name = $artifact['name'];
            if (isset($actualArtifacts[$name])) {
                $errors[] = sprintf('Artifact provenance repeats %s.', $name);
                continue;
            }
            $actualArtifacts[$name] = true;
            if (!isset($expectedArtifacts[$name])) {
                $errors[] = sprintf('Artifact provenance references unexpected file: %s', $name);
                continue;
            }
            if (($artifact['package'] ?? null) !== $expectedArtifacts[$name]) {
                $errors[] = sprintf('Artifact package mapping mismatch: %s', $name);
            }
            if (($artifact['media_type'] ?? null) !== 'application/zip') {
                $errors[] = sprintf('Artifact media type mismatch: %s', $name);
            }
            if (!is_string($artifact['sha256'] ?? null) || preg_match('/^[0-9a-f]{64}$/', $artifact['sha256']) !== 1) {
                $errors[] = sprintf('Artifact digest is invalid: %s', $name);
            }
            if (!is_int($artifact['size'] ?? null) || $artifact['size'] < 0) {
                $errors[] = sprintf('Artifact size is invalid: %s', $name);
            }

            $path = $distDirectory . DIRECTORY_SEPARATOR . $name;
            if (!is_file($path)) {
                $errors[] = sprintf('Artifact provenance references a missing file: %s', $name);
                continue;
            }

            $hash = hash_file('sha256', $path);
            if ($hash !== ($artifact['sha256'] ?? null)) {
                $errors[] = sprintf('Artifact digest mismatch: %s', $name);
            }
            if (filesize($path) !== ($artifact['size'] ?? null)) {
                $errors[] = sprintf('Artifact size mismatch: %s', $name);
            }
        }

        $expectedNames = array_keys($expectedArtifacts);
        $actualNames = array_keys($actualArtifacts);
        sort($expectedNames, SORT_STRING);
        sort($actualNames, SORT_STRING);
        if ($actualNames !== $expectedNames) {
            $errors[] = 'Artifact provenance must describe exactly the three release archives.';
        }
    }

    /** @param list<string> $expectedFiles
     * @param list<string> $errors
     */
    private function verifyChecksums(string $distDirectory, array $expectedFiles, array &$errors): void
    {
        $checksumPath = $distDirectory . DIRECTORY_SEPARATOR . 'SHA256SUMS';
        $checksumContents = is_file($checksumPath) ? file($checksumPath, FILE_IGNORE_NEW_LINES) : false;
        if ($checksumContents === false) {
            $errors[] = 'SHA256SUMS is missing or unreadable.';

            return;
        }

        $records = [];
        foreach ($checksumContents as $line) {
            if (preg_match('/^([0-9a-f]{64})  ([^\\/]+)$/', $line, $matches) !== 1) {
                $errors[] = sprintf('SHA256SUMS contains an invalid record: %s', $line);
                continue;
            }
            $filename = $matches[2];
            if (isset($records[$filename])) {
                $errors[] = sprintf('SHA256SUMS repeats %s.', $filename);
                continue;
            }
            $records[$filename] = $matches[1];
        }

        $actualFiles = array_keys($records);
        sort($actualFiles, SORT_STRING);
        sort($expectedFiles, SORT_STRING);
        if ($actualFiles !== $expectedFiles) {
            $errors[] = 'SHA256SUMS must describe exactly the five release assets.';
        }

        foreach ($expectedFiles as $filename) {
            if (!isset($records[$filename])) {
                continue;
            }
            $path = $distDirectory . DIRECTORY_SEPARATOR . $filename;
            $hash = is_file($path) ? hash_file('sha256', $path) : false;
            if ($hash === false) {
                $errors[] = sprintf('SHA256SUMS references a missing or unreadable file: %s', $filename);
            } elseif ($hash !== $records[$filename]) {
                $errors[] = sprintf('SHA256SUMS digest mismatch: %s', $filename);
            }
        }
    }

    /** @return array<string, string> */
    private function expectedArtifacts(string $version): array
    {
        $artifacts = [];
        foreach (self::PACKAGES as $slug => $packageName) {
            $artifacts[sprintf('php-upgrade-preflight-%s-v%s.zip', $slug, $version)] = $packageName;
        }

        return $artifacts;
    }

    /** @return array<string, mixed> */
    private function dependencyInventory(string $version): array
    {
        $releasedPackages = [];
        foreach (self::PACKAGES as $slug => $expectedName) {
            $manifest = $this->decodeJsonFile($this->root . '/packages/' . $slug . '/composer.json');
            if (($manifest['name'] ?? null) !== $expectedName) {
                throw new \RuntimeException(sprintf('Unexpected package name in packages/%s/composer.json.', $slug));
            }

            $requirements = is_array($manifest['require'] ?? null) ? $manifest['require'] : [];
            ksort($requirements, SORT_STRING);
            $releasedPackages[] = [
                'name' => $expectedName,
                'version' => $version,
                'runtime_requirements' => $requirements,
            ];
        }

        $lock = $this->decodeJsonFile($this->root . '/composer.lock');
        $lockedDependencies = [];
        foreach (['packages' => false, 'packages-dev' => true] as $key => $development) {
            foreach (($lock[$key] ?? []) as $package) {
                if (!is_array($package) || !is_string($package['name'] ?? null) || !is_string($package['version'] ?? null)) {
                    throw new \RuntimeException(sprintf('Invalid dependency record in composer.lock %s.', $key));
                }
                $lockedDependencies[] = [
                    'name' => $package['name'],
                    'version' => $package['version'],
                    'development' => $development,
                ];
            }
        }
        usort($lockedDependencies, static function (array $left, array $right): int {
            return [$left['name'], $left['development']] <=> [$right['name'], $right['development']];
        });

        return [
            'schema_version' => 1,
            'release_version' => $version,
            'composer_lock_content_hash' => $lock['content-hash'] ?? null,
            'released_packages' => $releasedPackages,
            'locked_build_dependencies' => $lockedDependencies,
        ];
    }

    /**
     * @param array{repository:string, commit:string, ref:string, workflow:string, run_uri:string} $source
     */
    private function assertSource(string $version, array $source): void
    {
        foreach (['repository', 'commit', 'ref', 'workflow', 'run_uri'] as $key) {
            if (($source[$key] ?? '') === '') {
                throw new \InvalidArgumentException(sprintf('Missing provenance value: %s', $key));
            }
        }
        if (preg_match('/^[0-9a-f]{40}$/', $source['commit']) !== 1) {
            throw new \InvalidArgumentException('Source commit must be a full lowercase Git SHA.');
        }
        if (str_starts_with($source['ref'], 'refs/tags/') && $source['ref'] !== 'refs/tags/v' . $version) {
            throw new \InvalidArgumentException('Source tag does not match the release version.');
        }
    }

    private function assertVersion(string $version): void
    {
        if (preg_match('/^(0|[1-9]\d*)\.(0|[1-9]\d*)\.(0|[1-9]\d*)$/', $version) !== 1) {
            throw new \InvalidArgumentException('Release version must use MAJOR.MINOR.PATCH format.');
        }
    }

    /** @return array{name:string, package:string, media_type:string, sha256:string, size:int} */
    private function artifactRecord(string $filename, string $path, string $packageName): array
    {
        $hash = hash_file('sha256', $path);
        $size = filesize($path);
        if ($hash === false || $size === false) {
            throw new \RuntimeException(sprintf('Unable to inspect release archive: %s', $path));
        }

        return [
            'name' => $filename,
            'package' => $packageName,
            'media_type' => 'application/zip',
            'sha256' => $hash,
            'size' => $size,
        ];
    }

    /** @param list<string> $errors
     * @return array<string, mixed>
     */
    private function readJson(string $path, array &$errors): array
    {
        try {
            return $this->decodeJsonFile($path);
        } catch (\Throwable $exception) {
            $errors[] = $exception->getMessage();

            return [];
        }
    }

    /** @return array<string, mixed> */
    private function decodeJsonFile(string $path): array
    {
        $contents = @file_get_contents($path);
        if ($contents === false) {
            throw new \RuntimeException(sprintf('Unable to read JSON file: %s', $path));
        }
        $decoded = json_decode($contents, true, 512, JSON_THROW_ON_ERROR);
        if (!is_array($decoded)) {
            throw new \RuntimeException(sprintf('JSON root must be an object: %s', $path));
        }

        return $decoded;
    }

    /** @param array<string, mixed> $value */
    private function writeJson(string $path, array $value): void
    {
        $this->writeFile(
            $path,
            json_encode($value, JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES | JSON_THROW_ON_ERROR) . "\n"
        );
    }

    private function writeFile(string $path, string $contents): void
    {
        if (file_put_contents($path, $contents) === false) {
            throw new \RuntimeException(sprintf('Unable to write release metadata: %s', $path));
        }
    }
}
