# Release Wiki Strategy

PHP Upgrade Preflight has four documentation destinations, not one combined Wiki:

1. the common product/monorepo Wiki;
2. the Core package Wiki;
3. the CLI package Wiki;
4. the Laravel package Wiki.

Canonical authoring pages live in `wiki/`. Physical destination copies live under
`release-wikis/{common,core,cli,laravel}/pages`, beside the manifest that defines
each set. This strategy was verified against release code on **2026-08-19**.

## Why four sets exist

The monorepo contains five packages, but only three are supported external Composer
distributions: `core`, `cli`, and `laravel`. Each distribution has its own GitHub
repository and needs package-focused documentation. The common Wiki explains the
whole product and monorepo.

`test-adapter` and `legacy-test-adapter` are development fixtures. They remain
documented in the common and Core sets because they prove adapter compatibility,
but they do not receive distribution repositories, release archives, Packagist
publication, or their own Wiki publication set.

## Source manifests

Each `wiki-manifest.json` contains:

- the exact destination repository;
- an ordered `source` → `destination` page map;
- destination-specific sidebar order;
- destination-specific footer text.

A page may appear in several manifests intentionally. For example, safety and
troubleshooting belong in each package Wiki because a reader should not need to
discover the monorepo first. The manifests are the separation boundary: never
combine their page lists into one Wiki checkout.

The copies are generated, reviewable repository files rather than runtime-only
artifacts. `pages/.source-checksums.json` records the canonical source, destination,
source SHA-256, and materialized SHA-256. The materializer rewrites a link to a local
renamed home page when possible and makes an absent cross-set page an explicit link
to the common Wiki. It also converts `../docs/...`-style repository links to full
monorepo GitHub URLs.

```bash
php tools/materialize-release-wikis.php
php tools/materialize-release-wikis.php --check
```

The first command regenerates declared pages, navigation, footer, and checksums. It
refuses to overwrite a tree containing an unlisted file. `--check` performs no
writes and fails on missing/extra files, content or checksum drift, invalid local
links, and manifest errors.

The package home pages are mapped deliberately:

| Destination | Source used as `Home.md` |
|---|---|
| Common | `wiki/Home.md` |
| Core | `wiki/Core-Package-Guide.md` |
| CLI | `wiki/CLI-Reference.md` |
| Laravel | `wiki/Laravel-Package-Internals.md` |

## What current release automation actually does

The existing distribution scripts are package-release tools, not Wiki tools:

- `prepare-distribution.sh` loops over exactly `core cli laravel`, clones their
  normal Git repositories, replaces package payloads, and stages changes;
- `release-distribution.sh` loops over the same three clones, commits, creates
  signed annotated tags, verifies signatures, and optionally pushes;
- `ReleaseArtifactMetadata` requires exactly three release ZIPs and five checksum
  assets (three ZIPs plus two JSON metadata files);
- `verify-installed-package-references.php` expects Core, CLI, and Laravel signed-tag
  commits in a consumer lock;
- the Release workflow verifies the same three distribution tags and publishes a
  monorepo GitHub Release only after package, consumer, and Packagist gates.

Package distribution still does not clone or push a GitHub `.wiki.git` repository.
The release metadata gate now runs the offline `--check` mode and verifies the
release-specific four-destination evidence before package jobs can authorize a
tag. The workflow's default permission remains `contents: read`, so deterministic
validation never depends on Wiki credentials and cannot be mistaken for publication.

Therefore Wiki publication is **not** inserted into the package loop. Doing so would
mix two different Git repositories, rollback models, and retry rules. It could also
let `--yes` push Wiki changes without a destination-specific review.

## Required pre-tag process today

Before creating any distribution or monorepo release tag:

1. Update canonical pages under `wiki/` for the release behavior.
2. Review all four manifests. Add, remove, or remap pages when package scope changed.
3. Run `php tools/materialize-release-wikis.php` to regenerate all four physical
   source trees.
4. Generate that destination's `_Sidebar.md` and `_Footer.md`; do not reuse the
   common Wiki navigation in a package Wiki.
5. Run `php tools/materialize-release-wikis.php --check` to validate sources,
   destinations, checksums, local links, and exact inventories.
6. Review each destination diff separately at Junior-developer and technical-manager
   reading levels.
7. Commit and push each affected Wiki repository deliberately.
8. Create `docs/releases/vVERSION-wiki-evidence.json` using
   `docs/releases/wiki-evidence.schema.json`. It must list exactly `common`, `core`,
   `cli`, and `laravel`, including each destination repository, `.wiki.git` URL,
   and manifest. Each `result` must be either `published` with the full 40-character
   Wiki commit SHA, or `unchanged-after-review` with the reviewed remote commit SHA
   and `inventory_check: passed`. Link the JSON file from
   `docs/releases/vVERSION.md`. A placeholder or readiness status is invalid.
9. Record the four Wiki commit IDs, or an explicit “unchanged after review” result,
   in `docs/releases/vVERSION.md`.
10. Only then run `composer release:verify -- VERSION` and create release tags.

`composer release:wiki:check` is the standalone offline page-tree check.
`composer release:verify -- VERSION` always runs the same materializer check first,
then verifies release series, package metadata, report metadata, changelog, release
notes, their evidence link, and all four evidence records. The release workflow uses
this Composer gate before packaging. The check intentionally does not contact GitHub:
actual Wiki publication and the final commit/result record remain separate maintainer
steps.

The v0.3.1 Wiki repositories were not found during the 2026-08-19 review, so
`docs/releases/v0.3.1-wiki-baseline.json` records a historical baseline under a
separate schema. It deliberately does not satisfy `release:verify`; it prevents a
missing publication from being rewritten as a successful result. That baseline stays
as written: it is evidence about v0.3.1 and is not amended by any later publication.

All four Wiki repositories were created and populated for v0.3.2, whose
per-destination commits are recorded in `docs/releases/v0.3.2-wiki-evidence.json`.
A release remains blocked until real per-destination evidence exists for that
release; evidence from an earlier release never satisfies a later one.

## Manual publication commands

Run these commands from the monorepo root only after materialization and `--check`
succeed. A GitHub Wiki is a separate Git repository. Copy only the generated
Markdown files: `.source-checksums.json` is local verification evidence and is not
a Wiki page.

The common destination is stated by
`release-wikis/common/wiki-manifest.json` as
`ValentinNikolaev/php-upgrade-preflight`. On Bash (Linux, macOS, Git Bash, or WSL):

```bash
git clone https://github.com/ValentinNikolaev/php-upgrade-preflight.wiki.git
cp release-wikis/common/pages/*.md php-upgrade-preflight.wiki/
php tools/materialize-release-wikis.php --check-published common php-upgrade-preflight.wiki
git -C php-upgrade-preflight.wiki status --short
git -C php-upgrade-preflight.wiki diff --check
git -C php-upgrade-preflight.wiki diff -- '*.md'
git -C php-upgrade-preflight.wiki add -- '*.md'
git -C php-upgrade-preflight.wiki commit -m "Update Wiki for vVERSION"
git -C php-upgrade-preflight.wiki push origin HEAD
```

`git clone` creates the temporary `php-upgrade-preflight.wiki` checkout inside the
monorepo working directory. Do not add that nested checkout to the monorepo commit.
Remove it after recording the pushed Wiki commit ID, using a path-specific command
appropriate to your operating system. Replace `vVERSION` with the real release, for
example `v0.4.0`. The inventory check reports every remote Markdown page that is not
in the selected manifest. Review each reported page, run
`git -C php-upgrade-preflight.wiki rm -- Page-Name.md` explicitly when it is truly
retired, and rerun `--check-published` until it succeeds. Do not bulk-delete the Wiki
checkout or rely on `git status` to discover untouched retired pages.

The equivalent PowerShell commands on Windows are:

```powershell
git clone https://github.com/ValentinNikolaev/php-upgrade-preflight.wiki.git
Copy-Item -Path 'release-wikis\common\pages\*.md' -Destination 'php-upgrade-preflight.wiki' -Force
php tools/materialize-release-wikis.php --check-published common 'php-upgrade-preflight.wiki'
git -C 'php-upgrade-preflight.wiki' status --short
git -C 'php-upgrade-preflight.wiki' diff --check
git -C 'php-upgrade-preflight.wiki' diff -- '*.md'
git -C 'php-upgrade-preflight.wiki' add -- '*.md'
git -C 'php-upgrade-preflight.wiki' commit -m 'Update Wiki for vVERSION'
git -C 'php-upgrade-preflight.wiki' push origin HEAD
```

Publish package documentation to three different Wiki repositories. Their names
come directly from the package manifests, not from an inferred naming convention:

| Set | Manifest destination | Wiki clone URL |
|---|---|---|
| Core | `ValentinNikolaev/php-upgrade-preflight-core` | `https://github.com/ValentinNikolaev/php-upgrade-preflight-core.wiki.git` |
| CLI | `ValentinNikolaev/php-upgrade-preflight-cli` | `https://github.com/ValentinNikolaev/php-upgrade-preflight-cli.wiki.git` |
| Laravel | `ValentinNikolaev/php-upgrade-preflight-laravel` | `https://github.com/ValentinNikolaev/php-upgrade-preflight-laravel.wiki.git` |

For Bash, select one row at a time and copy this template. The example selects
Core; change both values to `cli` / `php-upgrade-preflight-cli` or `laravel` /
`php-upgrade-preflight-laravel` for the other destinations:

```bash
wiki_set=core
wiki_repository=php-upgrade-preflight-core
git clone "https://github.com/ValentinNikolaev/${wiki_repository}.wiki.git" "../${wiki_repository}.wiki"
cp "release-wikis/${wiki_set}/pages/"*.md "../${wiki_repository}.wiki/"
php tools/materialize-release-wikis.php --check-published "${wiki_set}" "../${wiki_repository}.wiki"
git -C "../${wiki_repository}.wiki" status --short
git -C "../${wiki_repository}.wiki" diff --check
git -C "../${wiki_repository}.wiki" diff -- '*.md'
git -C "../${wiki_repository}.wiki" add -- '*.md'
git -C "../${wiki_repository}.wiki" commit -m "Update Wiki for vVERSION"
git -C "../${wiki_repository}.wiki" push origin HEAD
```

PowerShell uses the same manifest values without Bash variable syntax:

```powershell
$wikiSet = 'core'
$wikiRepository = 'php-upgrade-preflight-core'
git clone "https://github.com/ValentinNikolaev/$wikiRepository.wiki.git" "..\$wikiRepository.wiki"
Copy-Item -Path "release-wikis\$wikiSet\pages\*.md" -Destination "..\$wikiRepository.wiki" -Force
php tools/materialize-release-wikis.php --check-published $wikiSet "..\$wikiRepository.wiki"
git -C "..\$wikiRepository.wiki" status --short
git -C "..\$wikiRepository.wiki" diff --check
git -C "..\$wikiRepository.wiki" diff -- '*.md'
git -C "..\$wikiRepository.wiki" add -- '*.md'
git -C "..\$wikiRepository.wiki" commit -m 'Update Wiki for vVERSION'
git -C "..\$wikiRepository.wiki" push origin HEAD
```

For every package checkout, handle each surplus reported by `--check-published` with
a reviewed, path-specific `git rm -- Page-Name.md`, then rerun the comparison. Never
run all four pushes without reviewing each checkout. If `git commit` reports
that there is nothing to commit, record that destination as “unchanged after
review” instead of treating it as an error. Authentication may use an existing Git
credential helper or an SSH remote, but changing transport does not change the
four allowlisted destination repositories.

## Safe automation hook to add later

The safest future integration is a separate **pre-tag documentation gate**, not a
modification to `release-distribution.sh`:

```text
validate canonical Wiki pages
  -> materialize four isolated trees from manifests
  -> validate links and exact inventories
  -> compare each tree with its destination Wiki checkout
  -> require clean match or reviewed Wiki commits
  -> record destination commit IDs in release notes/evidence
  -> allow package/distribution tagging
```

The publisher should have a dry-run mode by default, explicit destination allowlist,
one confirmation per Wiki, commit pinning, and no `--yes` path that silently crosses
all four destinations. CI should validate and compare; remote writes should remain a
separate, explicitly authorized maintainer action unless a later security design
adds scoped credentials and protected-environment approval.

## Agent policy

Wiki-before-tag is mandatory. Codex, Claude, and every other coding agent that
creates, prepares, or recommends a new release tag must update affected canonical
pages, regenerate physical copies, pass `--check`, review all four destination
manifests, and provide publication evidence.
Agents must not say “release ready” while Wiki updates are deferred. When a Wiki set
is unchanged, the agent must report that it was reviewed and why no page changed.

This policy applies even though current automation does not enforce it.
