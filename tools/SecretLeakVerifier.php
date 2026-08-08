<?php

declare(strict_types=1);

namespace PhpUpgradePreflight\Tools;

final class SecretLeakVerifier
{
    /** @var array<string, string> */
    private array $canaries;

    /** @param array<string, string> $canaries */
    public function __construct(array $canaries)
    {
        foreach ($canaries as $label => $canary) {
            if (!is_string($label) || trim($label) === '' || !is_string($canary) || $canary === '') {
                throw new \InvalidArgumentException('Secret canary labels and values must be non-empty strings.');
            }
        }

        $this->canaries = $canaries;
    }

    public static function fromFixture(string $path): self
    {
        $contents = file_get_contents($path);
        if ($contents === false) {
            throw new \RuntimeException(sprintf('Could not read secret-canary fixture: %s', $path));
        }

        $fixture = json_decode($contents, true, 512, JSON_THROW_ON_ERROR);
        if (!is_array($fixture) || !isset($fixture['canaries']) || !is_array($fixture['canaries'])) {
            throw new \RuntimeException(sprintf('Secret-canary fixture has no canary map: %s', $path));
        }

        /** @var array<string, string> $canaries */
        $canaries = $fixture['canaries'];

        return new self($canaries);
    }

    /** @param list<string> $paths @return list<string> */
    public function verify(array $paths): array
    {
        $errors = [];

        foreach ($paths as $path) {
            if (is_dir($path)) {
                $iterator = new \RecursiveIteratorIterator(
                    new \RecursiveDirectoryIterator($path, \FilesystemIterator::SKIP_DOTS)
                );
                foreach ($iterator as $file) {
                    if ($file instanceof \SplFileInfo && $file->isFile()) {
                        $this->scanFile($file->getPathname(), $errors);
                    }
                }

                continue;
            }

            if (!is_file($path)) {
                $errors[] = sprintf('Leak-scan input does not exist: %s', $path);

                continue;
            }

            $this->scanFile($path, $errors);
        }

        return $errors;
    }

    /** @param list<string> $errors */
    private function scanFile(string $path, array &$errors): void
    {
        if (strtolower(pathinfo($path, PATHINFO_EXTENSION)) === 'zip') {
            $this->scanZip($path, $errors);

            return;
        }

        $contents = file_get_contents($path);
        if ($contents === false) {
            $errors[] = sprintf('Could not read leak-scan input: %s', $path);

            return;
        }

        $this->scanValue($contents, sprintf('file %s', $path), $errors);
    }

    /** @param list<string> $errors */
    private function scanZip(string $path, array &$errors): void
    {
        if (!class_exists(\ZipArchive::class)) {
            $errors[] = 'The ZIP extension is required to inspect release archives.';

            return;
        }

        $archive = new \ZipArchive();
        $opened = $archive->open($path);
        if ($opened !== true) {
            $errors[] = sprintf('Could not open release archive for leak scanning: %s', $path);

            return;
        }

        try {
            for ($index = 0; $index < $archive->numFiles; ++$index) {
                $name = $archive->getNameIndex($index);
                if ($name === false) {
                    $errors[] = sprintf('Could not read ZIP entry name #%d in %s.', $index, $path);

                    continue;
                }

                $this->scanValue($name, sprintf('ZIP entry name #%d in %s', $index, $path), $errors);
                if (str_ends_with($name, '/')) {
                    continue;
                }

                $contents = $archive->getFromIndex($index);
                if ($contents === false) {
                    $errors[] = sprintf('Could not read ZIP entry #%d in %s.', $index, $path);

                    continue;
                }

                $this->scanValue($contents, sprintf('ZIP entry #%d in %s', $index, $path), $errors);
            }
        } finally {
            $archive->close();
        }
    }

    /** @param list<string> $errors */
    private function scanValue(string $value, string $surface, array &$errors): void
    {
        foreach ($this->canaries as $label => $canary) {
            if (str_contains($value, $canary)) {
                $errors[] = sprintf('Sensitive canary %s reached %s.', $label, $surface);
            }
        }
    }
}
