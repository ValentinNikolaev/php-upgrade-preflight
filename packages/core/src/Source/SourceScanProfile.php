<?php

declare(strict_types=1);

namespace PhpUpgradePreflight\Core\Source;

/**
 * Opt-in, per-scan operational measurements, never part of the canonical report.
 * total_ms includes the whole scan. The seven phase times are exclusive of one
 * another: hash covers scanner digests and cache identity/key preparation;
 * cache validation/checksums/serialization stay in cache_get/cache_put; parse
 * includes AST traversal and collectors; evidence converts facts and warnings
 * after discovery (discovery includes its own omission/limit evidence).
 * Their sum can be below total_ms because loop/control overhead is unassigned.
 * Counters describe actual operations, not source coverage or defect recall.
 * selected_files is after the file cap; read_files/read_bytes count successful
 * source reads including the one-byte oversize probe. parsed_files counts
 * successful extraction, including empty facts; parse_attempts includes throws.
 * Cache hits count accepted facts, including empty lists. Rejected entries are
 * existing slots refused for safety, size, decoding, key or checksum reasons;
 * oversized reads are a subset. Missing/unsafe directories are unavailable
 * lookups, not rejected entries. Put failures include oversized writes, unsafe
 * destinations, contention and failed writes/renames; stored means rename won.
 * Counter values reset before every scan, including empty or throwing scans.
 * Only scan() is measured; independent selection/input-consistency passes are
 * outside this profile, as are Composer and the remainder of analyzer execution.
 */
final class SourceScanProfile
{
    /** @var array<string, float> */
    private array $timings = [];
    /** @var array<string, int> */
    private array $counters = [];
    private float $total = 0.0;

    public function __construct()
    {
        $this->reset();
    }

    public function reset(): void
    {
        $this->total = 0.0;
        $this->timings = array_fill_keys(['discovery', 'read', 'hash', 'cache_get', 'parse', 'cache_put', 'evidence'], 0.0);
        $this->counters = array_fill_keys([
            'selected_files', 'read_attempts', 'read_files', 'read_failures', 'read_bytes',
            'parse_attempts', 'parsed_files', 'parse_failures', 'cache_lookups', 'cache_hits',
            'cache_misses', 'cache_bypassed_files', 'cache_put_attempts', 'cache_stored_entries',
            'cache_put_failures', 'cache_rejected_entries', 'cache_read_oversized_entries',
            'cache_write_oversized_entries', 'cache_unavailable_lookups',
        ], 0);
    }

    public function record(string $phase, int|float $startedAt): void
    {
        if (!array_key_exists($phase, $this->timings)) {
            throw new \InvalidArgumentException('Unknown source profile phase.');
        }
        $this->timings[$phase] += (hrtime(true) - $startedAt) / 1000000;
    }

    public function count(string $counter, int $amount = 1): void
    {
        if (!array_key_exists($counter, $this->counters) || $amount < 0) {
            throw new \InvalidArgumentException('Invalid source profile counter.');
        }
        $this->counters[$counter] += $amount;
    }

    public function finish(int|float $startedAt): void
    {
        $this->total = (hrtime(true) - $startedAt) / 1000000;
    }

    /** @return array{total_ms: float, timings_ms: array<string, float>, counters: array<string, int>} */
    public function snapshot(): array
    {
        return ['total_ms' => $this->total, 'timings_ms' => $this->timings, 'counters' => $this->counters];
    }
}
