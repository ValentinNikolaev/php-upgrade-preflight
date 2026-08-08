<?php

declare(strict_types=1);

namespace PhpUpgradePreflight\Tests\Support;

final class JsonSnapshotNormalizer
{
    public const PROJECT_PATH = '<PROJECT_PATH>';
    public const TEMP_DIRECTORY = '<TEMP_DIR>';
    public const ANALYZER_PHP_VERSION = '<ANALYZER_PHP_VERSION>';

    /**
     * @param list<string> $temporaryDirectories
     */
    public static function normalize(string $json, string $projectPath, array $temporaryDirectories = []): string
    {
        if ($projectPath === '') {
            throw new \InvalidArgumentException('The project path cannot be empty.');
        }

        /** @var mixed $decoded */
        $decoded = json_decode($json, false, 512, JSON_THROW_ON_ERROR);
        if (is_object($decoded)
            && isset($decoded->platform)
            && is_object($decoded->platform)
            && isset($decoded->platform->analyzer)
            && is_object($decoded->platform->analyzer)
        ) {
            $decoded->platform->analyzer->php_version = self::ANALYZER_PHP_VERSION;
        }
        $temporaryDirectories = array_merge(
            $temporaryDirectories,
            self::temporaryDirectoriesIn($decoded)
        );

        $replacements = self::pathReplacements($projectPath, $temporaryDirectories);
        $normalized = self::normalizeValue($decoded, $replacements);

        return json_encode(
            $normalized,
            JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES | JSON_PRESERVE_ZERO_FRACTION | JSON_THROW_ON_ERROR
        ) . "\n";
    }

    /**
     * @param mixed $value
     * @return list<string>
     */
    private static function temporaryDirectoriesIn($value): array
    {
        if (is_object($value)) {
            $value = get_object_vars($value);
        }

        if (! is_array($value)) {
            return [];
        }

        $directories = [];

        foreach ($value as $key => $item) {
            if ($key === 'temp_path' && is_string($item) && $item !== '') {
                $directories[] = $item;
            }

            $directories = array_merge($directories, self::temporaryDirectoriesIn($item));
        }

        return array_values(array_unique($directories));
    }

    /**
     * @param list<string> $temporaryDirectories
     * @return list<array{path: string, placeholder: string}>
     */
    private static function pathReplacements(string $projectPath, array $temporaryDirectories): array
    {
        $paths = [
            ['path' => $projectPath, 'placeholder' => self::PROJECT_PATH],
        ];

        foreach ($temporaryDirectories as $directory) {
            if ($directory === '') {
                throw new \InvalidArgumentException('Temporary directory paths cannot be empty.');
            }

            $paths[] = ['path' => $directory, 'placeholder' => self::TEMP_DIRECTORY];
        }

        usort(
            $paths,
            static fn (array $left, array $right): int => strlen($right['path']) <=> strlen($left['path'])
        );

        $replacements = [];

        foreach ($paths as $path) {
            foreach (self::pathVariants($path['path']) as $variant) {
                $replacements[] = ['path' => $variant, 'placeholder' => $path['placeholder']];
            }
        }

        usort(
            $replacements,
            static fn (array $left, array $right): int => strlen($right['path']) <=> strlen($left['path'])
        );

        return $replacements;
    }

    /** @return list<string> */
    private static function pathVariants(string $path): array
    {
        $path = rtrim($path, '/\\');
        if ($path === '') {
            $path = DIRECTORY_SEPARATOR;
        }

        return array_values(array_unique([
            $path,
            str_replace('\\', '/', $path),
            str_replace('/', '\\', $path),
        ]));
    }

    /**
     * @param mixed $value
     * @param list<array{path: string, placeholder: string}> $replacements
     * @return mixed
     */
    private static function normalizeValue($value, array $replacements, ?string $key = null)
    {
        if (is_string($value)) {
            $value = self::normalizeString($value, $replacements);

            return self::isPathKey($key) ? str_replace('\\', '/', $value) : $value;
        }

        if (is_object($value)) {
            $normalized = new \stdClass();

            foreach (get_object_vars($value) as $itemKey => $item) {
                $normalized->{$itemKey} = self::normalizeValue($item, $replacements, $itemKey);
            }

            return $normalized;
        }

        if (! is_array($value)) {
            return $value;
        }

        $normalized = [];

        foreach ($value as $itemKey => $item) {
            $normalized[$itemKey] = self::normalizeValue($item, $replacements, is_string($itemKey) ? $itemKey : $key);
        }

        return $normalized;
    }

    private static function isPathKey(?string $key): bool
    {
        return $key !== null && in_array($key, [
            'file',
            'output_path',
            'path',
            'project_path',
            'source_paths',
            'temp_path',
        ], true);
    }

    /** @param list<array{path: string, placeholder: string}> $replacements */
    private static function normalizeString(string $value, array $replacements): string
    {
        foreach ($replacements as $replacement) {
            $pattern = '~' . preg_quote($replacement['path'], '~') . '(?=$|[\\\\/\s"\'.,:;)\]}])~';
            $replaced = preg_replace($pattern, $replacement['placeholder'], $value);

            if ($replaced === null) {
                throw new \RuntimeException('Unable to normalize a snapshot path.');
            }

            $value = $replaced;
        }

        $normalized = preg_replace_callback(
            '~<(?:PROJECT_PATH|TEMP_DIR)>(?:[\\\\/][^\s"\'(),:;]*)*~',
            static fn (array $matches): string => str_replace('\\', '/', $matches[0]),
            $value
        );

        if ($normalized === null) {
            throw new \RuntimeException('Unable to normalize snapshot directory separators.');
        }

        return $normalized;
    }
}
