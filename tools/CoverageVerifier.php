<?php

declare(strict_types=1);

namespace PhpUpgradePreflight\Tools;

final class CoverageVerifier
{
    /** @var list<string> */
    private array $criticalModules;
    private string $root;

    /** @param list<string> $criticalModules */
    public function __construct(string $root, array $criticalModules)
    {
        $this->root = $this->normalizePath($root);
        $this->criticalModules = $criticalModules;
    }

    /**
     * @return array{
     *   overall: array{covered: int, executable: int},
     *   critical_modules: array<string, array{covered: int, executable: int}>,
     *   known_uncovered_fingerprints: array<string, array<string, int>>
     * }
     */
    public function measure(string $path): array
    {
        $document = new \DOMDocument();
        $previous = libxml_use_internal_errors(true);
        $loaded = $document->load($path, LIBXML_NONET);
        libxml_clear_errors();
        libxml_use_internal_errors($previous);
        if (!$loaded) {
            throw new \RuntimeException(sprintf('Could not read Clover report "%s".', $path));
        }

        $files = [];
        $knownUncovered = [];
        $covered = 0;
        $executable = 0;
        foreach ($document->getElementsByTagName('file') as $fileNode) {
            if (!$fileNode instanceof \DOMElement) {
                continue;
            }
            $pathName = $this->normalizePath($fileNode->getAttribute('name'));
            $prefix = rtrim($this->root, '/') . '/';
            $relative = str_starts_with($pathName, $prefix) ? substr($pathName, strlen($prefix)) : $pathName;
            $fileCovered = 0;
            $fileExecutable = 0;
            $uncovered = [];
            $sourceLines = is_file($pathName) ? file($pathName, FILE_IGNORE_NEW_LINES) : false;
            foreach ($fileNode->getElementsByTagName('line') as $lineNode) {
                if (!$lineNode instanceof \DOMElement || $lineNode->getAttribute('type') !== 'stmt') {
                    continue;
                }
                ++$fileExecutable;
                ++$executable;
                if ((int) $lineNode->getAttribute('count') > 0) {
                    ++$fileCovered;
                    ++$covered;
                } else {
                    $lineNumber = (int) $lineNode->getAttribute('num');
                    $source = is_array($sourceLines) ? trim($sourceLines[$lineNumber - 1] ?? '') : '';
                    $fingerprint = hash('sha256', $source);
                    $uncovered[$fingerprint] = ($uncovered[$fingerprint] ?? 0) + 1;
                }
            }
            $files[$relative] = ['covered' => $fileCovered, 'executable' => $fileExecutable];
            if ($uncovered !== []) {
                ksort($uncovered);
                $knownUncovered[$relative] = $uncovered;
            }
        }
        ksort($knownUncovered);

        $critical = [];
        foreach ($this->criticalModules as $module) {
            if (!isset($files[$module])) {
                throw new \RuntimeException(sprintf('Critical module "%s" is absent from the Clover report.', $module));
            }
            $critical[$module] = $files[$module];
        }

        return [
            'overall' => ['covered' => $covered, 'executable' => $executable],
            'critical_modules' => $critical,
            'known_uncovered_fingerprints' => $knownUncovered,
        ];
    }

    private function normalizePath(string $path): string
    {
        return str_replace('\\', '/', realpath($path) ?: $path);
    }

    /** @param array<string, mixed> $measurement */
    public function writeBaseline(string $path, array $measurement): void
    {
        $baseline = [
            'schema_version' => 1,
            'measurement' => 'Full unit test suite, Clover line coverage',
            'policy' => [
                'overall_ratio_must_not_decrease',
                'critical_module_ratio_must_not_decrease',
                'new_executable_lines_must_be_covered',
            ],
        ] + $measurement;
        $encoded = json_encode($baseline, JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES | JSON_THROW_ON_ERROR) . "\n";
        if (!is_dir(dirname($path)) && !mkdir(dirname($path), 0777, true) && !is_dir(dirname($path))) {
            throw new \RuntimeException('Could not create the coverage baseline directory.');
        }
        if (file_put_contents($path, $encoded) === false) {
            throw new \RuntimeException('Could not write the coverage baseline.');
        }
    }

    /** @return array<string, mixed> */
    public function readBaseline(string $path): array
    {
        $contents = @file_get_contents($path);
        if ($contents === false) {
            throw new \RuntimeException('Coverage baseline is missing; generate it only from a successful unit run.');
        }
        $baseline = json_decode($contents, true, 512, JSON_THROW_ON_ERROR);
        if (!is_array($baseline) || ($baseline['schema_version'] ?? null) !== 1) {
            throw new \RuntimeException('Coverage baseline has an unsupported schema.');
        }

        return $baseline;
    }

    /**
     * @param array<string, mixed> $baseline
     * @param array<string, mixed> $current
     */
    public function verify(array $baseline, array $current): void
    {
        $this->assertRatioDidNotDecrease(
            'overall',
            $this->readRatio($baseline['overall'] ?? null, 'baseline overall'),
            $this->readRatio($current['overall'] ?? null, 'current overall')
        );

        $baselineCritical = $this->readMap($baseline['critical_modules'] ?? null, 'baseline critical modules');
        $currentCritical = $this->readMap($current['critical_modules'] ?? null, 'current critical modules');
        foreach ($this->criticalModules as $module) {
            $this->assertRatioDidNotDecrease(
                $module,
                $this->readRatio($baselineCritical[$module] ?? null, 'baseline ' . $module),
                $this->readRatio($currentCritical[$module] ?? null, 'current ' . $module)
            );
        }

        $known = $this->readFingerprintMap(
            $baseline['known_uncovered_fingerprints'] ?? [],
            'baseline uncovered fingerprints'
        );
        $currentUncovered = $this->readFingerprintMap(
            $current['known_uncovered_fingerprints'] ?? null,
            'current uncovered fingerprints'
        );
        $newUncovered = [];
        foreach ($currentUncovered as $file => $fingerprints) {
            $allowed = $known[$file] ?? [];
            foreach ($fingerprints as $fingerprint => $count) {
                $increase = $count - ($allowed[$fingerprint] ?? 0);
                if ($increase > 0) {
                    $newUncovered[] = sprintf('%s:%s (+%d)', $file, substr($fingerprint, 0, 12), $increase);
                }
            }
        }
        if ($newUncovered !== []) {
            throw new \RuntimeException(sprintf(
                'New or changed executable lines lack coverage: %s.',
                implode(', ', array_slice($newUncovered, 0, 20))
            ));
        }
    }

    /** @return array<string, mixed> */
    private function readMap(mixed $value, string $scope): array
    {
        if (!is_array($value)) {
            throw new \RuntimeException(sprintf('Coverage data for %s is invalid.', $scope));
        }

        return $value;
    }

    /** @return array{covered: int, executable: int} */
    private function readRatio(mixed $value, string $scope): array
    {
        if (!is_array($value) || !is_int($value['covered'] ?? null) || !is_int($value['executable'] ?? null)) {
            throw new \RuntimeException(sprintf('Coverage ratio for %s is invalid.', $scope));
        }

        return ['covered' => $value['covered'], 'executable' => $value['executable']];
    }

    /** @return array<string, array<string, int>> */
    private function readFingerprintMap(mixed $value, string $scope): array
    {
        if (!is_array($value)) {
            throw new \RuntimeException(sprintf('Coverage data for %s is invalid.', $scope));
        }

        $map = [];
        foreach ($value as $file => $fingerprints) {
            if (!is_string($file) || !is_array($fingerprints)) {
                throw new \RuntimeException(sprintf('Coverage data for %s is invalid.', $scope));
            }
            foreach ($fingerprints as $fingerprint => $count) {
                if (!is_string($fingerprint) || !is_int($count)) {
                    throw new \RuntimeException(sprintf('Coverage data for %s is invalid.', $scope));
                }
                $map[$file][$fingerprint] = $count;
            }
        }

        return $map;
    }

    /**
     * @param array{covered: int, executable: int} $baseline
     * @param array{covered: int, executable: int} $current
     */
    private function assertRatioDidNotDecrease(string $scope, array $baseline, array $current): void
    {
        if ($current['executable'] === 0) {
            throw new \RuntimeException(sprintf('Coverage scope "%s" has no executable lines.', $scope));
        }
        if ($current['covered'] * $baseline['executable'] < $baseline['covered'] * $current['executable']) {
            throw new \RuntimeException(sprintf(
                'Coverage decreased for %s from %d/%d to %d/%d lines.',
                $scope,
                $baseline['covered'],
                $baseline['executable'],
                $current['covered'],
                $current['executable']
            ));
        }
    }
}
