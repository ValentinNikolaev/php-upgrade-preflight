# Repository tools

Small command-line helpers this repository uses on itself. They check the release
state, guard quality ratchets, prove that reports leak no secrets or local paths,
and assemble the three distribution repositories.

Nothing here ships to users. The published packages are `packages/core`,
`packages/cli`, and `packages/laravel`; installing the analyzer never installs this
directory. Almost every tool runs from CI, and the few that are useful locally are
marked below.

## Conventions

- Run everything from the repository root, for example `php tools/verify-release.php 0.3.0`.
- PHP tools target the same PHP 8.0 floor as the shipped code. The ones that touch
  analyzer classes need `composer install` first; the rest need only PHP.
- Exit codes are uniform: `0` success, `2` wrong invocation, `1` a real failure.
- A file named after a class — `ReleaseVerifier.php`, `CoverageVerifier.php`,
  `SecretLeakVerifier.php`, `DistributionPayloadVerifier.php`,
  `InstalledPackageReferenceVerifier.php`, `ReleaseArtifactMetadata.php` — is the
  library behind the executable script beside it. Their behavior is covered by the
  tests in [`tests/Release`](../tests/Release).

## Release identity

### `verify-release.php`

```bash
php tools/verify-release.php 0.3.0        # or: composer release:verify -- 0.3.0
```

Answers one question: does this working tree consistently describe the release you
named? It checks the active release series, `ReportMetadata::TOOL_VERSION` and
`SCHEMA_VERSION`, the `MAJOR.MINOR.x-dev` branch aliases and root path-repository
versions, the `^MAJOR.MINOR` constraints between the three packages, the absence of
`version` fields in package manifests, a dated `[VERSION]` changelog heading, and a
release-notes file whose first line names the version. It first runs the offline
release-Wiki materialization check, then requires the release notes to link a
machine-readable file that names all four Wiki destinations. Every destination
must contain a real published Wiki commit SHA, or a reviewed unchanged remote SHA
with a passed inventory check. Historical baselines and readiness placeholders do
not authorize releases. The Release workflow runs this combined gate after tag
trust verification; run it locally before proposing a release commit.

Use `composer release:wiki:check` for only the read-only Wiki-tree validation. Both
commands are offline; publication to the four `.wiki.git` repositories is a
separate maintainer step.

## Distribution repositories

Packagist reads each package from its own repository, so every release copies the
package subtree plus the shared `LICENSE`, `README.md`, `CHANGELOG.md`,
`SECURITY.md`, and `docs/` into `php-upgrade-preflight-core`, `-cli`, and
`-laravel`.

### `prepare-distribution.sh`

```bash
bash tools/prepare-distribution.sh [WORK_DIR]
```

Clones the three distribution repositories into `build/dist/<package>` (or
`WORK_DIR`), replaces their content with exactly that payload, stages it, and fails
if the result differs from what the release workflow will compare against. It
refuses to run on a dirty working tree, so the payload always matches a real commit.
It never commits, tags, or pushes.

### `release-distribution.sh`

```bash
bash tools/release-distribution.sh [--tag v0.3.0] [--work DIR] [--dry-run] [--yes]
```

Walks the three prepared repositories and, for each, commits the staged payload as
`release: prepare <tag> distribution`, creates the signed annotated tag
`php-upgrade-preflight/<package> <tag>`, verifies that signature locally, and asks
before pushing. The tag is the only value it prompts for; it defaults to the newest
dated changelog release. A repository whose tag already exists locally or on the
remote is skipped, so rerunning after a partial failure is safe. `--dry-run` prints
the commands instead of running them.

### `verify-distribution-payload.php`

```bash
php tools/verify-distribution-payload.php EXPECTED_DIRECTORY ACTUAL_DIRECTORY
```

Compares two directories file by file — SHA-256 and the executable bit — and reports
missing, unexpected, and differing paths. The Release workflow uses it on a tagged
release to prove that each published distribution tag contains exactly the package
it claims to.

### `release-artifact-metadata.php`

```bash
php tools/release-artifact-metadata.php generate --version=0.3.0 --dist=dist \
  --repository=URL --commit=SHA --ref=REF --workflow=PATH --run-uri=URL
php tools/release-artifact-metadata.php verify   ... same options ...
```

Writes and then re-checks the provenance record beside the built archives: their
checksums, the dependency inventory, and the exact source and build coordinates the
archives came from. The Release workflow generates it while packaging and verifies
it again before publication.

### `verify-installed-package-references.php`

```bash
php tools/verify-installed-package-references.php VERSION LOCK CORE_REF CLI_REF LARAVEL_REF
```

Reads a consumer's `composer.lock` after installing the published packages and
requires each of the three to resolve to the exact source and distribution commits
behind their signed tags. A matching version string alone is not accepted, so a
stale or re-pointed Packagist reference fails the release.

## Quality ratchets

### `verify-coverage.php`

```bash
composer test:coverage                                            # measure and verify
php tools/verify-coverage.php build/coverage/clover.xml --write-baseline
```

Compares a Clover report with the committed baseline in
`tests/fixtures/quality/coverage-baseline.json`. Overall and critical-module ratios
may not fall, and a source line that loses coverage fails the run; there is no
hand-picked percentage. Rewrite the baseline only after reviewing a full, successful
coverage run.

### `run-selective-mutations.php`

```bash
composer test:mutation
```

Runs the mutation checks declared in `mutation.json`: each entry patches one source
line and requires a named test to fail. It is a targeted guard for logic the test
suite could otherwise pass by accident, not a full mutation-testing run.

## Privacy and secret gates

The repository seeds synthetic credentials into a fixture so the redaction paths can
be tested with something that looks real. These tools make sure those canaries never
escape into reports, logs, or archives.

### `mask-secret-canaries.php`

```bash
php tools/mask-secret-canaries.php
```

Prints `::add-mask::` lines for every synthetic canary so GitHub Actions redacts them
from job logs. CI runs it before anything that could echo one.

### `verify-secret-leaks.php`

```bash
php tools/verify-secret-leaks.php PATH [PATH ...]
```

Scans files or directories — release archives, package sources, generated reports —
for those canaries and fails if one appears.

### `verify-report-privacy.php`

```bash
php tools/verify-report-privacy.php
```

Runs the analyzer against a fixture that contains seeded credentials and local paths,
then checks the canonical JSON, the Markdown projection, evidence, diagnostics, and
error rendering: credentials must be redacted, absolute roots must be replaced by
`[PROJECT_ROOT]`-style markers, and no canary may survive. The quality workflow runs
it on both Linux and Windows.

## Reporting helper

### `render-markdown-report.php`

```bash
php tools/render-markdown-report.php INPUT.json OUTPUT.md [PROJECT_ROOT]
```

Renders an existing canonical JSON report as Markdown using the product's own writer,
without rerunning any analysis. The release audits use it to prove that both output
formats come from one report.
