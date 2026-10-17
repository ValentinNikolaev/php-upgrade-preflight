# Parsed source facts and modular discovery

`SourceUsageScanner` caches successful parser-derived usage facts across checks.
Every invocation still discovers files, reads the configured bounded bytes and
hashes those exact bytes. It rebuilds evidence, ordering, deduplication and safety
limit omissions against the current ledger. Dependency ownership, changed-package
evaluation, framework rules and upgrade targets are evaluated again.

The cache key includes canonical project identity, relative filename, source
SHA-256, PHP runtime version, installed parser version/reference and implementation
digest, core collector implementation, and ordered adapter collector identities.
Unknown collectors and injected parsers bypass caching unless they implement the
optional `SourceUsageCacheKeyProvider`. Its key must identify implementation,
version and all configuration affecting facts; behavior must remain stable during
one scan. Keys are recomputed for each scan. The Laravel adapter opts in because
its source collector depends only on source bytes and relative filename, with
both adapter and collector implementation digests included. Rule catalogs and
targets do not affect those raw facts.

Parse failures and adapter failures are never cached. Corrupt, oversized,
unreadable or unavailable cache entries are misses. JSON checksums and fact-shape
validation reject malformed entries; no PHP deserialization or cached code runs.

The default directory is
`sys_get_temp_dir()/php-upgrade-preflight-source-cache-<user-hash>`. It must remain
outside the analyzed project, including through existing symlinked ancestors.
Unix directories require mode `0700` and current-user ownership; files require
private permissions. Windows uses the current user's temporary-directory ACLs.
Symlinks, special files and hardlinked cache files are rejected. Writes use a
nonblocking writer lock and an atomic same-directory rename. Cache contents
include private symbol names, usage types and line numbers, so the directory is
an analyzer-owned local artifact, not a shareable report. It stores no source
contents, ASTs, evidence IDs, absolute source paths or parse-error text.

There are 65,536 replacement slots and each entry is at most 8 KiB: facts occupy
at most 512 MiB, plus one bounded pending entry and writer lock. A second candidate
slot reduces collision misses without increasing that bound. Collisions and
large fact sets can reduce the hit rate; they never reduce scanning coverage.
The directory is disposable and may be removed while no scans are running.

Existing scanner callers benefit without changing their constructor. Embedders
can choose an analyzer-owned directory or disable caching explicitly:

```php
$scanner = new SourceUsageScanner(null, null, new SourceFactsCache('/private/source-cache'));
$uncached = new SourceUsageScanner(null, null, SourceFactsCache::disabled());
```

The cache accepts an optional existing-path resolver with `realpath` semantics
for deterministic filesystem-failure tests. The internal parser fingerprint
helper similarly accepts a file-digest reader and parser-file provenance. Default
callers use the real filesystem; no runtime CLI or environment settings are added.

Default source selection can additionally discover up to 256 static root
`autoload` and `autoload-dev` paths from PSR-4, PSR-0, classmap and files metadata.
This covers modular application sources outside conventional `app` and `src`
directories. Discovery does not execute Composer loaders, inspect path-repository
manifests, follow wildcard mappings or read outside the project. Dependency and
generated roots, including a custom Composer `vendor-dir`, remain excluded from
automatic discovery. Excluded metadata roots and the metadata-path limit produce
linked uncertainty/evidence using existing schema 0.8 fields.

The final optional `discoverAutoload` parameter on scanner selection/scanning and
`InputConsistencyTracker::captureSource()` / `checkSource()` must be enabled
together for default analysis paths. Explicit user paths leave it disabled and
retain their existing behavior. File, byte and usage caps are unchanged; repeated
selection and hashing for within-analysis mutation detection remain in place.

Focused tests cover cold/warm/disabled equivalence, parse counts, evidence ID
reconstruction, same-length edits, add/delete/rename context, project isolation,
collector/parser identity changes, corrupt/unavailable storage, parse and adapter
failures, cache filesystem attacks, modular coverage and input immutability.

A local PHP 8.3 Docker measurement used 1,000 generated Laravel-shaped controller
files and the real Laravel collector. It retained 5,000 usages with identical
canonical SHA-256 in disabled, cold and warm modes. The warmed disabled scan took
0.423 s and parsed 1,000 files; two warm scans took 0.295 s and 0.261 s with zero
parses. These are in-process source-phase measurements using a counting parser
wrapper, not whole-analyzer or cross-process default-cache benchmarks. They do
not establish a total-analysis performance target.
