# Release Wiki source layout

This directory defines four independent GitHub Wiki publication sets:

| Source set | Destination repository | Purpose |
|---|---|---|
| `common` | `ValentinNikolaev/php-upgrade-preflight` | Monorepo and product-wide documentation |
| `core` | `ValentinNikolaev/php-upgrade-preflight-core` | Core package documentation |
| `cli` | `ValentinNikolaev/php-upgrade-preflight-cli` | CLI package documentation |
| `laravel` | `ValentinNikolaev/php-upgrade-preflight-laravel` | Laravel package documentation |

Each set has its own `wiki-manifest.json` and a materialized `pages/` tree. A
manifest maps reviewed Markdown in `wiki/` to names in one destination Wiki and
defines that Wiki's own navigation.
The same source page may intentionally appear in more than one manifest so a
package Wiki remains understandable without sending a Junior developer through
several repositories.

These are Wiki publication sources, not package payloads. The existing release
scripts do not read this directory and no Wiki is pushed automatically. See
[`wiki/Release-Wiki-Strategy.md`](../wiki/Release-Wiki-Strategy.md) before adding a
publisher.

## Rules

- Publish one manifest at a time to its named destination. Never merge manifests.
- Publish Markdown from the materialized `pages/` tree; do not publish these README
  files or `.source-checksums.json`.
- Regenerate with `php tools/materialize-release-wikis.php` after changing canonical
  pages or manifests. Verify with `php tools/materialize-release-wikis.php --check`.
- The only valid set directories are exactly `common`, `core`, `cli`, and `laravel`.
  A missing/unknown set or manifest is an error, as is a destination repository or
  purpose that differs from the built-in allowlist.
- Fail on a missing or escaping source, case-insensitive duplicate or reserved
  destination, broken local Wiki link, dirty generated tree, symlinked output, or
  content outside the manifest. Validation of all four sets completes before the
  generator replaces any `pages/` tree.
- Test adapters are documented as compatibility fixtures but never receive a Wiki
  destination or release publication set.
- Before any release tag, all four manifests must be reviewed and every affected
  destination set must be updated. This applies to Codex, Claude, and all agents.

The materializer keeps cross-Wiki links explicit, converts repository-relative links
to stable GitHub repository URLs, generates per-set navigation, rejects extra files,
and writes `.source-checksums.json` so copied-page drift is reviewable.

After copying one set into a cloned Wiki, compare the complete Markdown inventory:

```text
php tools/materialize-release-wikis.php --check-published SET WIKI_CHECKOUT
```

The comparison fails on missing, changed, or surplus remote pages. Review every
reported surplus and remove it with a path-specific `git rm`; never bulk-delete a
Wiki checkout.

`wiki-manifest.json` uses repository-local forward-slash paths and schema version
`1`. It deliberately contains no credentials or Wiki remote URL.
