<?php

declare(strict_types=1);

namespace PhpUpgradePreflight\Tests\Support;

use FilesystemIterator;
use InvalidArgumentException;
use PHPUnit\Framework\TestCase;
use RecursiveDirectoryIterator;
use RecursiveIteratorIterator;

final class FixtureSnapshot
{
    private string $rootPath;
    /** @var array<string, string> */
    private array $files;

    /** @param array<string, string> $files */
    private function __construct(string $rootPath, array $files)
    {
        $this->rootPath = $rootPath;
        $this->files = $files;
    }

    public static function capture(string $fixturePath): self
    {
        $rootPath = realpath($fixturePath);
        if ($rootPath === false || !is_dir($rootPath)) {
            throw new InvalidArgumentException(sprintf('Fixture path "%s" does not exist or is not a directory.', $fixturePath));
        }

        return new self($rootPath, self::filesIn($rootPath));
    }

    public function assertUnchanged(TestCase $test): void
    {
        $actualFiles = self::filesIn($this->rootPath);

        $test->assertSame(
            array_keys($this->files),
            array_keys($actualFiles),
            'Analysis changed the set of files in the fixture.'
        );

        foreach ($this->files as $relativePath => $contents) {
            $test->assertArrayHasKey($relativePath, $actualFiles);
            $test->assertSame(
                $contents,
                $actualFiles[$relativePath],
                sprintf('Analysis changed fixture file "%s".', $relativePath)
            );
        }
    }

    /** @return array<string, string> */
    private static function filesIn(string $rootPath): array
    {
        $files = [];
        $iterator = new RecursiveIteratorIterator(
            new RecursiveDirectoryIterator($rootPath, FilesystemIterator::SKIP_DOTS)
        );

        foreach ($iterator as $file) {
            if (! $file->isFile()) {
                continue;
            }

            $contents = file_get_contents($file->getPathname());
            if ($contents === false) {
                throw new \RuntimeException(sprintf('Unable to read fixture file "%s".', $file->getPathname()));
            }

            $files[str_replace('\\', '/', $iterator->getSubPathname())] = $contents;
        }

        ksort($files);

        return $files;
    }
}
