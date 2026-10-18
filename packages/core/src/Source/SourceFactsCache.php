<?php

declare(strict_types=1);

namespace PhpUpgradePreflight\Core\Source;

use Symfony\Component\Filesystem\Path;

/** Disposable, private JSON facts; 65,536 replacement slots bound storage to 512 MiB. */
final class SourceFactsCache
{
    private const MAX_ENTRY_BYTES = 8192;

    private ?string $directory;
    private \Closure $resolvePath;

    /** @param null|callable(string): (string|false) $resolvePath existing-ancestor resolver with the same contract as realpath */
    public function __construct(?string $directory = null, ?callable $resolvePath = null)
    {
        $user = function_exists('posix_geteuid') ? (string) posix_geteuid() : (string) getenv('USERNAME');
        $this->directory = $directory ?? sys_get_temp_dir() . '/php-upgrade-preflight-source-cache-' . substr(hash('sha256', $user), 0, 16);
        $this->resolvePath = \Closure::fromCallable($resolvePath ?? 'realpath');
    }

    public static function disabled(): self
    {
        $cache = new self();
        $cache->directory = null;

        return $cache;
    }

    public function enabled(): bool
    {
        return $this->directory !== null;
    }

    /**
     * @param ?array<string, int> $statistics Pass [] to collect this call's counters; null avoids instrumentation.
     * @return ?list<array{symbol: string, usage_type: string, line: int}>
     */
    public function get(string $projectPath, string $key, ?array &$statistics = null): ?array
    {
        if ($statistics !== null) {
            $statistics = ['cache_rejected_entries' => 0, 'cache_read_oversized_entries' => 0, 'cache_unavailable_lookups' => 0];
        }
        $directory = $this->safeDirectory($projectPath, false);
        if ($directory === null) {
            if ($statistics !== null) {
                ++$statistics['cache_unavailable_lookups'];
            }
            return null;
        }
        foreach ($this->slots($key) as $slot) {
            $facts = $this->readFacts($directory . '/' . $slot . '.json', $key, $statistics);
            if ($facts !== null) {
                return $facts;
            }
        }

        return null;
    }

    /**
     * @param ?array<string, int> $statistics
     * @return ?list<array{symbol: string, usage_type: string, line: int}>
     */
    private function readFacts(string $path, string $key, ?array &$statistics): ?array
    {
        if (!$this->privatePath($path) || is_link($path)) {
            if ($statistics !== null && @lstat($path) !== false) {
                return $this->rejectedEntry($statistics);
            }
            return null;
        }
        $handle = @fopen($path, 'rb');
        if ($handle === false) {
            return $this->rejectedEntry($statistics);
        }
        $contents = @stream_get_contents($handle, self::MAX_ENTRY_BYTES + 1);
        fclose($handle);
        if ($contents === false || strlen($contents) > self::MAX_ENTRY_BYTES) {
            return $this->rejectedEntry($statistics, $contents !== false);
        }
        $entry = json_decode($contents, true, 32);
        if (!is_array($entry) || ($entry['key'] ?? null) !== $key || !is_string($entry['facts'] ?? null)
            || !is_string($entry['sha256'] ?? null) || !hash_equals($entry['sha256'], hash('sha256', $entry['facts']))) {
            return $this->rejectedEntry($statistics);
        }
        $facts = json_decode($entry['facts'], true, 8);
        if (!is_array($facts) || ($facts !== [] && array_keys($facts) !== range(0, count($facts) - 1))) {
            return $this->rejectedEntry($statistics);
        }
        $validated = [];
        foreach ($facts as $fact) {
            if (!is_array($fact) || count($fact) !== 3 || !is_string($fact['symbol'] ?? null)
                || !is_string($fact['usage_type'] ?? null) || !is_int($fact['line'] ?? null)
                || $fact['symbol'] === '' || $fact['usage_type'] === '' || $fact['line'] < 1) {
                return $this->rejectedEntry($statistics);
            }
            $validated[] = ['symbol' => $fact['symbol'], 'usage_type' => $fact['usage_type'], 'line' => $fact['line']];
        }

        return $validated;
    }

    /**
     * @param ?array<string, int> $statistics
     * @return null
     */
    private function rejectedEntry(?array &$statistics, bool $oversized = false): mixed
    {
        if ($statistics !== null) {
            ++$statistics['cache_rejected_entries'];
            if ($oversized) {
                ++$statistics['cache_read_oversized_entries'];
            }
        }

        return null;
    }

    /**
     * @param list<array{symbol: string, usage_type: string, line: int}> $facts
     * @param ?array<string, int> $statistics Pass [] to collect this call's counters.
     */
    public function put(string $projectPath, string $key, array $facts, ?array &$statistics = null): void
    {
        if ($statistics !== null) {
            $statistics = ['cache_stored_entries' => 0, 'cache_put_failures' => 1, 'cache_write_oversized_entries' => 0];
        }
        $encoded = json_encode($facts, JSON_UNESCAPED_SLASHES);
        if ($encoded === false) {
            return;
        }
        $entry = json_encode(['key' => $key, 'facts' => $encoded, 'sha256' => hash('sha256', $encoded)], JSON_UNESCAPED_SLASHES);
        if ($entry === false || strlen($entry) > self::MAX_ENTRY_BYTES) {
            if ($entry !== false && $statistics !== null) {
                ++$statistics['cache_write_oversized_entries'];
            }
            return;
        }
        $directory = $this->safeDirectory($projectPath, true);
        if ($directory === null) {
            return;
        }
        // A single writer bounds temporary storage as well as the replacement slots.
        $lockPath = $directory . '/writer.lock';
        if (is_link($lockPath) || (file_exists($lockPath) && !$this->privatePath($lockPath))) {
            return;
        }
        $lock = @fopen($lockPath, 'c');
        if ($lock === false) {
            return;
        }
        @chmod($lockPath, 0600);
        if (!@flock($lock, LOCK_EX | LOCK_NB)) {
            fclose($lock);
            return;
        }
        $slots = $this->slots($key);
        $destination = $directory . '/' . $slots[0] . '.json';
        foreach ($slots as $slot) {
            $candidate = $directory . '/' . $slot . '.json';
            if (!file_exists($candidate)) {
                $destination = $candidate;
                break;
            }
        }
        $temporary = $directory . '/pending.json';
        if (!is_link($temporary) && (!file_exists($temporary) || $this->privatePath($temporary))) {
            $handle = @fopen($temporary, 'wb');
            if ($handle !== false) {
                @chmod($temporary, 0600);
                $written = @fwrite($handle, $entry);
                fclose($handle);
                if ($written === strlen($entry)) {
                    if (@rename($temporary, $destination) && $statistics !== null) {
                        ++$statistics['cache_stored_entries'];
                        $statistics['cache_put_failures'] = 0;
                    }
                }
                if (is_file($temporary)) {
                    @unlink($temporary);
                }
            }
        }
        flock($lock, LOCK_UN);
        fclose($lock);
    }

    /** @return list<string> */
    private function slots(string $key): array
    {
        $hash = hash('sha256', $key);

        return array_values(array_unique([substr($hash, 0, 4), substr($hash, 4, 4)]));
    }

    private function safeDirectory(string $projectPath, bool $create): ?string
    {
        if ($this->directory === null || !Path::isAbsolute($this->directory)) {
            return null;
        }
        $project = rtrim(Path::canonicalize((string) (realpath($projectPath) ?: $projectPath)), '/');
        $directory = Path::canonicalize($this->directory);
        // Resolve existing ancestors before mkdir: even a symlinked parent must never
        // turn an analyzer cache into a write inside the immutable input project.
        $ancestor = $directory;
        while (!file_exists($ancestor) && dirname($ancestor) !== $ancestor) {
            $ancestor = dirname($ancestor);
        }
        $resolved = ($this->resolvePath)($ancestor);
        if ($resolved === false) {
            return null;
        }
        $directory = Path::canonicalize($resolved . substr($directory, strlen($ancestor)));
        $comparisonProject = DIRECTORY_SEPARATOR === '\\' ? strtolower($project) : $project;
        $comparisonDirectory = DIRECTORY_SEPARATOR === '\\' ? strtolower($directory) : $directory;
        if ($comparisonDirectory === $comparisonProject || str_starts_with($comparisonDirectory, $comparisonProject . '/')) {
            return null;
        }
        if (!is_dir($directory) && (!$create || !@mkdir($directory, 0700, true))) {
            return null;
        }

        return !is_link($this->directory) && $this->privatePath($directory) ? $directory : null;
    }

    private function privatePath(string $path): bool
    {
        clearstatcache(true, $path);
        $stat = @lstat($path);
        if ($stat === false) {
            return false;
        }
        $type = $stat['mode'] & 0170000;
        if ($type !== 0040000 && ($type !== 0100000 || $stat['nlink'] !== 1)) {
            return false;
        }

        return DIRECTORY_SEPARATOR === '\\' || (($stat['mode'] & 0077) === 0
            && (!function_exists('posix_geteuid') || $stat['uid'] === posix_geteuid()));
    }
}
