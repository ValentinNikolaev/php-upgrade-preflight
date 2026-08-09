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
        $contents = @file_get_contents($path);
        if ($contents === false) {
            throw new \RuntimeException('Could not read the secret-canary fixture.');
        }

        $fixture = json_decode($contents, true, 512, JSON_THROW_ON_ERROR);
        if (!is_array($fixture) || !isset($fixture['canaries']) || !is_array($fixture['canaries'])) {
            throw new \RuntimeException('The secret-canary fixture has no canary map.');
        }

        /** @var array<string, string> $canaries */
        $canaries = $fixture['canaries'];

        return new self($canaries);
    }

    /** @param list<string> $paths @return list<string> */
    public function verify(array $paths): array
    {
        $errors = [];

        foreach ($paths as $inputIndex => $path) {
            $input = sprintf('input #%d', $inputIndex + 1);
            $this->scanValue($path, $input . ' path', $errors);

            if (is_dir($path)) {
                $iterator = new \RecursiveIteratorIterator(
                    new \RecursiveDirectoryIterator($path, \FilesystemIterator::SKIP_DOTS)
                );
                $fileIndex = 0;
                foreach ($iterator as $file) {
                    if ($file instanceof \SplFileInfo && $file->isFile()) {
                        ++$fileIndex;
                        $this->scanValue(
                            $file->getPathname(),
                            sprintf('%s file #%d path', $input, $fileIndex),
                            $errors
                        );
                        $this->scanFile(
                            $file->getPathname(),
                            sprintf('%s file #%d', $input, $fileIndex),
                            $errors
                        );
                    }
                }

                continue;
            }

            if (!is_file($path)) {
                $errors[] = sprintf('Leak-scan %s does not exist.', $input);

                continue;
            }

            $this->scanFile($path, $input, $errors);
        }

        return $errors;
    }

    /** @param list<string> $errors */
    private function scanFile(string $path, string $surface, array &$errors): void
    {
        if (strtolower(pathinfo($path, PATHINFO_EXTENSION)) === 'zip') {
            $this->scanZip($path, $surface, $errors);

            return;
        }

        $contents = @file_get_contents($path);
        if ($contents === false) {
            $errors[] = sprintf('Could not read leak-scan %s.', $surface);

            return;
        }

        $this->scanValue($contents, $surface, $errors);
    }

    /** @param list<string> $errors */
    private function scanZip(string $path, string $surface, array &$errors): void
    {
        if (!class_exists(\ZipArchive::class)) {
            $errors[] = 'The ZIP extension is required to inspect release archives.';

            return;
        }

        $archive = new \ZipArchive();
        $opened = $archive->open($path);
        if ($opened !== true) {
            $errors[] = sprintf('Could not open release archive %s for leak scanning.', $surface);

            return;
        }

        try {
            for ($index = 0; $index < $archive->numFiles; ++$index) {
                $name = $archive->getNameIndex($index);
                if ($name === false) {
                    $errors[] = sprintf('Could not read ZIP entry name #%d in %s.', $index, $surface);

                    continue;
                }

                $this->scanValue($name, sprintf('ZIP entry name #%d in %s', $index, $surface), $errors);
                if (str_ends_with($name, '/')) {
                    continue;
                }

                $contents = $archive->getFromIndex($index);
                if ($contents === false) {
                    $errors[] = sprintf('Could not read ZIP entry #%d in %s.', $index, $surface);

                    continue;
                }

                $this->scanValue($contents, sprintf('ZIP entry #%d in %s', $index, $surface), $errors);
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
