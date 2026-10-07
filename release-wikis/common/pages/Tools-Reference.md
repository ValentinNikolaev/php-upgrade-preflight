# Repository Tools Reference

The `tools/` directory supports this repository's checks and releases. Distribution packages do not install these scripts. If you are analyzing an application, use `vendor/bin/upgrade-intel`. Use this page when maintaining the repository.

> Run every example from the monorepo root. Install development dependencies first with `composer install` when a tool loads product classes or PHPUnit.

## At a glance

| Command | Purpose | Used automatically |
|---|---|---|
| `php tools/verify-release.php VERSION` | Check release identity, materialized Wikis, and four-destination Wiki evidence | Release workflow |
| `bash tools/prepare-distribution.sh [WORK_DIR]` | Prepare three distribution repositories | Maintainer-operated release preparation |
| `bash tools/release-distribution.sh [...]` | Commit, sign, and optionally push distribution releases | Maintainer-operated release preparation |
| `php tools/verify-distribution-payload.php EXPECTED ACTUAL` | Compare distribution trees exactly | Release workflow |
| `php tools/release-artifact-metadata.php generate\|verify ...` | Create or verify archive provenance, inventory, and checksums | Release workflow |
| `php tools/verify-installed-package-references.php ...` | Prove Packagist installed the signed-tag commits | Release workflow |
| `php tools/verify-coverage.php [CLOVER] [--write-baseline]` | Enforce the coverage ratchet | Quality workflow through Composer |
| `php tools/run-selective-mutations.php` | Check that focused tests kill critical mutations | Quality workflow through Composer |
| `php tools/mask-secret-canaries.php` | Tell GitHub Actions to mask synthetic credentials | Quality and release workflows |
| `php tools/verify-secret-leaks.php PATH [...]` | Scan files, directories, and ZIPs for synthetic credentials | Release workflow |
| `php tools/verify-report-privacy.php` | Exercise report redaction and path sanitization | Quality workflow |
| `php tools/render-markdown-report.php INPUT OUTPUT [ROOT]` | Render Markdown from canonical JSON | Release fresh-clone audit |
| `php tools/materialize-release-wikis.php [--check]` | Generate or verify four isolated Wiki source trees | Mandatory pre-tag documentation process |
| `php tools/materialize-release-wikis.php --check-published SET WIKI_CHECKOUT` | Compare one cloned Wiki's exact Markdown inventory and content | Manual Wiki publication review |

Unless a section says otherwise, a successful command exits with `0`, a detected failure with `1`, and an invalid invocation with `2`.

## Release identity

### `verify-release.php`

`verify-release.php` checks that a requested `0.3.PATCH` version agrees across the repository. It first runs `materialize-release-wikis.php --check`, then checks release and schema versions, path-repository versions, branch aliases, internal constraints, forbidden manifest `version` fields, the dated changelog heading, release notes, and their Wiki-evidence link. The evidence file must cover Common, Core, CLI, and Laravel. Each destination needs either a published Wiki commit SHA or a reviewed remote SHA with a passed inventory check.

The check reads local files and works offline. It does not clone or push a Wiki repository, so record real remote evidence before using it as a pre-tag gate:

```bash
php tools/verify-release.php 0.3.5
# Equivalent Composer entry point:
composer release:verify -- 0.3.5
```

**Input and output.** Pass a version without the `v` prefix. Success prints a confirmation, and each inconsistency appears as an `ERROR:` line. The current release-series policy rejects `v0.3.5`, `0.2.9`, and incomplete versions.

**CI/release role.** It is the first metadata gate in `.github/workflows/release.yml`. The PHP implementation is split between the entry point and `tools/ReleaseVerifier.php`.

Use `composer release:wiki:check` when you only need the standalone four-tree materialization check while writing documentation.

## Distribution repository tools

The monorepo has five packages, but only three are published as end-user distributions:

- `packages/core` → `php-upgrade-preflight-core`
- `packages/cli` → `php-upgrade-preflight-cli`
- `packages/laravel` → `php-upgrade-preflight-laravel`

`test-adapter` and `legacy-test-adapter` are development/test packages and are not processed by these distribution scripts.

### `prepare-distribution.sh`

`prepare-distribution.sh` clones the three distribution repositories and copies each package subtree with shared `LICENSE`, `README.md`, `CHANGELOG.md`, `SECURITY.md`, and `docs/`. It stages and checks the result. Staged file modes come from the committed source tree rather than host filesystem permissions, while copied blob bytes are retained. Before signing, compare exact Git paths, blobs, and modes with the source commit, including after any final `git add`. The filesystem payload verifier checks content and executable bits, but cannot check the Git index.

Run it with Bash from a clean monorepo, with network access and clone credentials. It rebuilds the three package directories and three `expected-*` directories inside the selected work directory. Give it a dedicated directory whose contents you can replace.

```bash
# Default: build/dist
bash tools/prepare-distribution.sh

# Safer for an inspection run: use a new disposable directory
bash tools/prepare-distribution.sh /tmp/php-upgrade-preflight-dist
```

**Input and output.** The optional positional argument is the work directory. Each prepared clone contains staged changes. A `.source-commit` file records the monorepo `HEAD`, and the console reports the source commit and number of changed paths. The script never commits, tags, or pushes.

**CI/release role.** This is a maintainer preparation tool, not a GitHub Actions step. The Release workflow independently reconstructs expected trees and verifies the already published distribution tags.

### `release-distribution.sh`

`release-distribution.sh` processes the prepared `core`, `cli`, and `laravel` clones. It commits staged payload changes, creates signed annotated tags, checks their signatures, and offers to push each repository's branch and tag.

**Prerequisites and safety.** Run `prepare-distribution.sh` first. The recorded source commit must still equal monorepo `HEAD`, and `git config user.signingkey` must exist. Start with `--dry-run`. Without `--yes`, the script asks for a separate push confirmation for each repository. `--yes` also approves pushes, so use it only for deliberate non-interactive execution.

```bash
# Inspect commands without changing the clones or remotes
bash tools/release-distribution.sh --tag v0.3.5 --dry-run

# Use a non-default prepared directory, still with confirmations
bash tools/release-distribution.sh --tag v0.3.5 --work /tmp/php-upgrade-preflight-dist
```

Options:

| Option | Meaning |
|---|---|
| `--tag vX.Y.Z` or `--version vX.Y.Z` | Release tag. The `v` is added when omitted |
| `--work DIR` | Directory previously populated by `prepare-distribution.sh` |
| `--dry-run` | Print commit, tag, verification, and push commands instead of executing them |
| `--yes` | Accept the detected tag and all push prompts |
| `--help` | Show the embedded help text |

If no tag is supplied, the tool proposes the newest dated version in `CHANGELOG.md`. Repositories where the tag already exists locally or remotely are skipped, which supports resuming a partial release. At the end it prints the separate commands for signing and pushing the monorepo tag. It does not run them.

> Before creating a release tag, update and publish the matching Wiki content. `verify-release.php` checks local materialization, four-destination evidence, the changelog, and release notes. It does not contact or publish remote Wiki repositories, so review source-to-prose accuracy and remote publication separately. See [Release Wiki Strategy](Release-Wiki-Strategy).

### `verify-distribution-payload.php`

`verify-distribution-payload.php` compares two trees by relative path, SHA-256 content hash, and executable bit.

**Safe use.** It is read-only:

```bash
php tools/verify-distribution-payload.php \
  /tmp/expected-core \
  /tmp/downloaded-core
```

Both inputs must be existing directories. Success prints `Distribution payloads match.` Failures identify missing, unexpected, or different paths.

**CI/release role.** For a tag-triggered release, the distribution-preflight job downloads each distribution repository tag and compares it with a tree rebuilt from the monorepo. `tools/DistributionPayloadVerifier.php` contains the reusable implementation.

### `release-artifact-metadata.php`

**Purpose.** `generate` writes release archive metadata, and `verify` checks it. The files are:

- `DEPENDENCY-INVENTORY.json`: package runtime requirements and locked dependencies.
- `ARTIFACT-PROVENANCE.json`: version, archives, hashes, and source/build coordinates.
- `SHA256SUMS`: checksums for the three ZIP archives plus the two JSON metadata files.

All options use `--name=value` syntax.

```bash
php tools/release-artifact-metadata.php generate \
  --version=0.3.5 \
  --dist=dist \
  --repository=https://github.com/ValentinNikolaev/php-upgrade-preflight \
  --commit=0123456789abcdef0123456789abcdef01234567 \
  --ref=refs/tags/v0.3.5 \
  --workflow=.github/workflows/release.yml \
  --run-uri=https://github.com/OWNER/REPO/actions/runs/RUN_ID

php tools/release-artifact-metadata.php verify \
  --version=0.3.5 \
  --dist=dist
```

`generate` requires all five provenance values shown above and the expected archives in `dist`. `verify` may check only the recorded metadata, as in the short example, or receive all five provenance options to compare against expected build coordinates. Supplying only some provenance options is an error.

**Safe use.** Generation writes or replaces the three metadata files in `--dist`. Use only the intended archive directory. Verification is read-only.

**CI/release role.** The package job generates and immediately verifies metadata. Linux and Windows artifact-consumer jobs verify it again before installing archives. The implementation lives in `tools/ReleaseArtifactMetadata.php`.

### `verify-installed-package-references.php`

`verify-installed-package-references.php` checks a clean consumer's `composer.lock`. Core, CLI, and Laravel must have the requested version, and each package's `source.reference` and `dist.reference` must match its distribution repository's signed-tag commit.

```bash
php tools/verify-installed-package-references.php \
  0.3.5 \
  /tmp/consumer/composer.lock \
  CORE_TAG_COMMIT \
  CLI_TAG_COMMIT \
  LARAVEL_TAG_COMMIT
```

Inputs are positional and must appear in that exact order. This tool is read-only and prints a specific error for a missing package, wrong version, missing expected reference, or mismatched transport reference.

**CI/release role.** The published-quick-start job runs it after Packagist installation. This catches a stale or repointed package even when its displayed version is correct. `tools/InstalledPackageReferenceVerifier.php` contains the reusable implementation.

## Quality ratchets

### `verify-coverage.php`

`verify-coverage.php` compares a Clover report with `tests/fixtures/quality/coverage-baseline.json`. Overall and critical-module coverage must not fall, and new or changed executable lines must not add uncovered-line fingerprints.

```bash
# Recommended: PHPUnit creates Clover, then the verifier checks it
composer test:coverage

# Check an existing report explicitly
php tools/verify-coverage.php build/coverage/clover.xml

# Rewrite the committed baseline after reviewing a complete successful unit run
php tools/verify-coverage.php build/coverage/clover.xml --write-baseline
```

The Clover path defaults to `build/coverage/clover.xml`. `--write-baseline` modifies a committed fixture, so do not use it merely to make CI green. Review why coverage changed and include the baseline diff in code review.

**CI/release role.** The PHP 8.3 coverage job runs `composer test:coverage`. `tools/CoverageVerifier.php` measures, stores, and compares the data.

### `run-selective-mutations.php`

`run-selective-mutations.php` changes one configured source occurrence at a time and runs its focused test. The test must fail for each mutation. A passing test has not shown it can catch the change.

```bash
composer test:mutation
# Direct equivalent:
php tools/run-selective-mutations.php
```

**Inputs and outputs.** There are no command-line inputs. The tool validates that `mutation.json` contains exactly the required critical mutation identities. It temporarily edits source files, restores each one in `finally` and at shutdown, and uses `build/selective-mutation.lock` to reject concurrent runs. Success lists killed mutations and prints a final count.

**Safe use.** Do not edit the same source files during a mutation run. If a process is forcibly terminated, inspect `git diff` before continuing, even though shutdown restoration normally protects the tree.

**CI/release role.** The coverage job runs it immediately after the coverage ratchet.

## Privacy and synthetic-secret gates

These tools use synthetic values from `tests/fixtures/security/composer-output-with-secrets.json`. They do not search for every possible real credential format. They check that the repository's known redaction paths contain the seeded canaries.

### `mask-secret-canaries.php`

`mask-secret-canaries.php` prints one GitHub Actions `::add-mask::VALUE` command per synthetic canary, so the runner can hide it in later logs.

```bash
php tools/mask-secret-canaries.php
```

There are no inputs. Run it only in a GitHub Actions log-command context. Local output intentionally contains the synthetic fixture values. It does not scan anything.

**CI/release role.** Quality, privacy, staged-budget, and fresh-clone jobs invoke it before tests or analysis that could echo a canary.

### `verify-secret-leaks.php`

`verify-secret-leaks.php` searches files and directories for synthetic canaries. It also scans ZIP entries. The PHP ZIP extension is required for ZIP inputs, and the command reports an error if that extension is missing.

```bash
php tools/verify-secret-leaks.php dist
php tools/verify-secret-leaks.php /tmp/run.log /tmp/report.json
```

Inputs must be readable paths. The tool is read-only and reports the affected surface without printing the secret value. `tools/SecretLeakVerifier.php` contains the scanner.

**CI/release role.** It scans packaged release archives, downloaded artifact directories, and the log/report produced by an installed release analysis.

### `verify-report-privacy.php`

`verify-report-privacy.php` runs an analyzer scenario with synthetic credentials and absolute paths. It checks JSON, Markdown, evidence, diagnostics, and errors for redaction and `[PROJECT_ROOT]` path replacement.

```bash
php tools/verify-report-privacy.php
```

There are no arguments. It needs `vendor/autoload.php`. The tool creates a uniquely named directory under the operating system's temporary directory, removes it in `finally`, and prints only a safe stage name on failure.

**CI/release role.** The quality matrix runs it across supported Linux PHP versions and on the Windows unit/smoke job.

## Wiki publication helper

### `materialize-release-wikis.php`

`materialize-release-wikis.php` reads the four `release-wikis/*/wiki-manifest.json`
files and builds separate Wiki trees under their `pages/` directories. It copies
canonical pages, rewrites renamed-home and cross-Wiki links, builds each sidebar
and footer, and records source and output SHA-256 values.

```bash
# Regenerate physical copies after changing wiki/ or a manifest
php tools/materialize-release-wikis.php

# Read-only drift, inventory, manifest, and local-link verification
php tools/materialize-release-wikis.php --check

# After copying Common pages into its cloned Wiki, detect missing, changed, or retired pages
php tools/materialize-release-wikis.php --check-published common php-upgrade-preflight.wiki
```

The tool accepts exactly `common`, `core`, `cli`, and `laravel`, with fixed repository
and purpose values. Sources must resolve to regular Markdown files below `wiki/`.
destinations are case-insensitively unique and cannot claim generated filenames.
Generation validates all four sets before writing, stages a complete known tree, and
atomically replaces each validated `pages/` directory. It refuses unlisted files and
symlinked output instead of deleting or following them.

`--check` writes nothing and fails when a canonical page, manifest, generated copy,
navigation file, footer, or checksum record has drifted. `--check-published` also
writes nothing. Run it against one cloned Wiki after copying the selected set. For a
surplus remote page, review the page, remove only that path with `git rm`, and rerun
the comparison. Never bulk-delete the checkout.

**CI/release role.** The release workflow runs `composer release:verify`, whose
verifier invokes this tool in `--check` mode. Distribution scripts do not publish
Wiki repositories. Regenerating the four sets, passing the drift check, publishing
or reviewing all four Wiki destinations, and recording their remote commit SHAs
remain mandatory pre-tag work described in [Release Wiki Strategy](Release-Wiki-Strategy).

## Reporting helper

### `render-markdown-report.php`

`render-markdown-report.php` turns an existing canonical JSON report into Markdown with the product's renderer. It does not rerun analysis.

```bash
php tools/render-markdown-report.php \
  "/tmp/reports/upgrade.json" \
  "/tmp/reports/upgrade.md" \
  "/work/my-project"
```

The required inputs are the JSON report and Markdown destination. `PROJECT_ROOT` is optional when the JSON contains an unsanitized real project path, but it is required when `request_summary.project_path` is `[PROJECT_ROOT]`. The writer validates that the output destination is safe for that project root. The command requires Composer autoloading.

**Output.** It writes Markdown and prints a path filtered through the operational path-exposure policy. Invalid input or an unsafe output location fails without exposing detailed path data.

**CI/release role.** The fresh-clone release audit generates canonical JSON, renders Markdown from it, and proves that analysis did not modify the target fixture.

## Why some `tools/*.php` files are not commands

The following files define reusable classes and have no command-line interface of their own:

- `CoverageVerifier.php`
- `DistributionPayloadVerifier.php`
- `InstalledPackageReferenceVerifier.php`
- `ReleaseArtifactMetadata.php`
- `ReleaseVerifier.php`
- `SecretLeakVerifier.php`

Run the corresponding lowercase executable described above. Their behavior is covered primarily by `tests/Release/`.
