<?php

declare(strict_types=1);

namespace PhpUpgradePreflight\Core\Source;

/** Optional opt-in for deterministic source collectors or an injected parser. */
interface SourceUsageCacheKeyProvider
{
    /**
     * Identify every implementation, version and configuration input affecting facts.
     * Returning null disables caching. Implementations must be pure for a given key:
     * no dependence on mutable project files, targets, clock, or process state unless
     * that state is included in the key. The scanner separately keys exact source
     * bytes, project identity and the project-relative filename.
     * The key and contributed behavior must remain stable during a scan; core
     * snapshots the key once per scan and recomputes it on the next invocation.
     */
    public function sourceUsageCacheKey(): ?string;
}
