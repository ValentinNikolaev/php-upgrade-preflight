# Quality and Release Tooling

This page explains how a change moves from a developer's machine to a verified release. For command-level details, see [Repository Tools Reference](Tools-Reference).

## The short version

For an ordinary change:

```bash
composer install
composer check
```

Before a release candidate:

```bash
composer check
composer test:coverage
composer test:mutation
php tools/verify-report-privacy.php
composer audit --locked --no-interaction --no-ansi
composer release:verify -- 0.3.1
```

This local sequence is useful, but GitHub Actions remains authoritative because it also tests multiple PHP versions, Windows, fresh consumer installations, dependency-resolution variants, distribution tags, and release archives.

## Composer command reference

Run commands from the repository root.

### Validation and the main quality bundle

| Command | What it does | Typical use |
|---|---|---|
| `composer validate:all` | Strictly validates the root manifest and all five package manifests | After changing any `composer.json` |
| `composer check` | Runs `validate:all`, the offline release-Wiki tree check, all tests, both PHPStan configurations, and style checking | Main pre-push check |
| `composer analyse` | Runs PHPStan with development and production configurations, each with a 512 MB limit | After changing PHP types or package boundaries |
| `composer lint` | Runs PHP-CS-Fixer in dry-run/diff mode; it does not rewrite files | Check formatting |

`validate:all` covers `core`, `cli`, `laravel`, `test-adapter`, and `legacy-test-adapter`. The last two are development packages even though all five manifests must remain valid.

Example after changing an internal dependency constraint:

```bash
composer validate:all
composer analyse
```

### Test suites

| Command | Scope |
|---|---|
| `composer test` | Alias of `test:all` |
| `composer test:all` | Unit, integration, then smoke suites |
| `composer test:unit` | PHPUnit `unit` suite |
| `composer test:integration` | PHPUnit `integration` suite |
| `composer test:smoke` | PHPUnit `smoke` suite |
| `composer test:unit-smoke` | Unit then smoke; used by the main Windows matrix entry |
| `composer test:core` | PHPUnit `core` suite |
| `composer test:cli` | PHPUnit `cli` suite |
| `composer test:laravel` | PHPUnit `laravel` suite |
| `composer test:fixtures` | Tests matching `LaravelFixtureAnalysisTest` |
| `composer test:integration:staged-budget` | Tests matching `WorstCaseStagedBudgetTest` |

Choose the narrowest command while developing, then run `composer check` before handoff. Examples:

```bash
# A CLI-only change during development
composer test:cli

# A Laravel fixture or transition change
composer test:laravel
composer test:fixtures

# Final local verification
composer check
```

The `test:integration:windows-*` commands partition integration tests into Windows CI shards and write JUnit files under `build/`. They are CI-oriented; use `composer test:integration` for the normal local integration run.

The exact shard commands are:

- `composer test:integration:windows-parity-1`
- `composer test:integration:windows-parity-2`
- `composer test:integration:windows-parity-3`
- `composer test:integration:windows-staged`
- `composer test:integration:windows-fixtures-1`
- `composer test:integration:windows-fixtures-2`
- `composer test:integration:windows-rest`

### Coverage, mutations, and release metadata

| Command | Scope |
|---|---|
| `composer test:coverage` | Removes the previous Clover file, measures unit coverage, and enforces the committed ratchet |
| `composer test:mutation` | Runs the targeted mutations defined by `mutation.json` |
| `composer release:verify -- VERSION` | Checks repository release identity for an exact version |

Coverage requires a PHP coverage driver. CI uses PCOV on PHP 8.3. If local PHPUnit reports that no driver is available, enable PCOV or Xdebug rather than updating the baseline from an incomplete run.

## Makefile and Docker equivalents

The `Makefile` runs commands in the Compose `php` service. It is useful when the host does not have the required PHP environment.

```bash
make build
make install
make check
```

Available targets include `build`, `shell`, `install`, `update`, `validate`, `test`, `test-laravel-fixture`, `analyse`, `lint`, and `check`. The `analyze` target forwards custom CLI arguments:

```bash
make analyze ARGS="--path=/app/example --target-php=8.3 --format=json"
```

`make update` changes `composer.lock`; use it only when a dependency update is intended. `make check` is similar to `composer check`, but the Makefile's `validate` target currently validates the root plus `core`, `cli`, and `laravel`, while `composer validate:all` also validates both adapter packages. For the complete manifest gate, prefer `composer check` or run `composer validate:all` in the container.

## What GitHub Actions checks

### Quality workflow

`.github/workflows/quality.yml` runs on pull requests, pushes to `main`, and calls from the release workflow.

- **Static analysis / PHP 8.0:** actionlint, all six Composer manifest validations, development and production PHPStan, and PHP-CS-Fixer dry-run.
- **Coverage / PHP 8.3:** the coverage ratchet followed by selective mutations.
- **Runtime matrix:** all suites on Linux PHP 8.0 through 8.5; unit/smoke plus partitioned integration suites on Windows PHP 8.3.
- **Report privacy:** Linux runtime jobs and the main Windows unit/smoke job run the privacy verifier.
- **Staged budgets:** Linux and Windows enforce worst-supported-chain process, runtime, memory, privacy, report-size, and determinism budgets.

The workflow masks synthetic canaries before relevant test commands. Windows JUnit timing artifacts are uploaded even when a shard fails so maintainers can rebalance the partitions.

### Compatibility workflow

`.github/workflows/compatibility.yml` installs packages in fresh consumer projects. It exercises dependency resolution variants and supported combinations of the standalone packages and Laravel versions, then runs `composer check-platform-reqs` and a package-specific smoke check.

This answers a different question from the monorepo tests:

- monorepo tests ask, “does our source behave correctly with the locked development environment?”
- compatibility tests ask, “can a real consumer resolve and run the package under supported constraints?”

### Dependency security workflow

`.github/workflows/security.yml` runs weekly, on manual dispatch, and when called by Release. It installs locked dependencies on PHP 8.0 and runs:

```bash
composer audit --locked --no-interaction --no-ansi
```

It blocks a release when the committed dependency lock contains a known Composer advisory.

## Release workflow, step by step

`.github/workflows/release.yml` supports two modes:

- pushing a tag matching `vX.Y.Z` verifies and publishes a release;
- manual dispatch verifies and packages an exact version without publishing.

### 1. Prepare the release documentation and version

Before creating a release tag:

1. Update `CHANGELOG.md` with a dated `[X.Y.Z]` heading.
2. Create or update `docs/releases/vX.Y.Z.md`; its first line must identify the version.
3. Update the GitHub Wiki so all changed commands, services, configuration, schemas, examples, compatibility information, and release notes are accurate for `vX.Y.Z`. Regenerate and check all four allowlisted release-Wiki sets, then use `--check-published SET WIKI_CHECKOUT` for each cloned destination so retired remote pages cannot remain silently.
4. Create `docs/releases/vX.Y.Z-wiki-evidence.json` from the repository schema. List the common, Core, CLI, and Laravel destinations and link that file from the release notes. For each destination, record either the published Wiki commit SHA or an unchanged-after-review remote SHA plus the passed exact-inventory check.
5. If an agent such as Codex or Claude prepares the release, the agent must treat the Wiki update as part of the release work, not as an optional follow-up.
6. Run `composer release:verify -- X.Y.Z`.

The release verifier now runs the offline Wiki materialization check before checking package/report metadata, changelog, release notes, and the exact four-destination evidence. It needs no network credentials and never publishes a Wiki. Readiness or placeholder values do not pass: every destination needs a real 40-character commit SHA. A historical baseline can document absent Wiki repositories without making the release gate green.

### 2. Prepare the three distribution repositories

From the exact committed monorepo state:

```bash
bash tools/prepare-distribution.sh
bash tools/release-distribution.sh --tag v0.3.1 --dry-run
bash tools/release-distribution.sh --tag v0.3.1
```

Inspect staged changes and the dry run before accepting pushes. Only `core`, `cli`, and `laravel` have distribution repositories; the two adapter packages are development fixtures.

### 3. Create the monorepo tag

After all three distribution tags exist, create the signed annotated monorepo tag using the commands printed by `release-distribution.sh`:

```bash
git tag -s v0.3.1 -m "php-upgrade-preflight v0.3.1"
git push origin v0.3.1
```

Pushing this tag starts the publishing workflow. A tag is an external, consequential action: confirm the version, commit, signatures, and Wiki state first.

### 4. Release gates run

The workflow then verifies:

1. the monorepo tag is annotated, signed, and on the approved release line;
2. repository release metadata is consistent;
3. the full Quality, Compatibility, and dependency-security workflows pass;
4. all three distribution tags are signed and contain byte-for-byte expected payloads;
5. fresh Linux and Windows clones install cleanly, analyze without modifying the target, and render Markdown from canonical JSON.

### 5. Archives are built and tested

The package job creates exactly three ZIP archives from staged package payloads. It generates and verifies dependency inventory, provenance, and checksums, then scans the result for synthetic-secret leakage.

Artifact-consumer jobs on Linux and Windows:

- verify the archive set and checksums;
- verify provenance again;
- install `core`, `cli`, and `laravel` into clean consumers;
- run CLI and Laravel smoke checks;
- run a JSON analysis, scan its log/report, verify metadata, and prove target immutability.

### 6. Published packages and GitHub Release are verified

For a real tag push, the workflow waits for Packagist to expose the packages. It verifies that Composer resolved both source and dist references to the exact signed distribution-tag commits, runs the published quick start, and checks target immutability again.

Only after those gates pass does the workflow create or update the GitHub Release using `docs/releases/vX.Y.Z.md` and attach the verified artifacts.

## Diagnosing common failures

### “Release metadata is inconsistent”

Run the exact version locally:

```bash
php tools/verify-release.php 0.3.1
```

Read every `ERROR:` line. Do not fix only the first one: a release version is repeated deliberately across metadata, constraints, changelog, and release notes.

### “Coverage decreased”

Open the test gap first. The baseline is a ratchet, not a target percentage that should be lowered. Add or improve tests, regenerate Clover, and rerun `composer test:coverage`. Rewrite the baseline only when the new measurement is intentional and reviewed.

### “Mutation survived”

The named focused test still passes after a critical condition was changed. Strengthen that test so it detects the behavior change. Do not remove the mutation merely to pass the gate.

### “Distribution payload differs”

The distribution tag does not exactly represent its monorepo package plus shared files. Rerun preparation from the intended clean commit and inspect staged changes. Existing tags are skipped rather than overwritten, so investigate a wrong existing tag instead of trying to repoint it.

### “Packagist reference mismatch”

The published version may not yet have synchronized, or a distribution reference may not match the signed tag. The workflow retries its clean installation during the synchronization window. If it still fails, compare the signed tag commit with both `source.reference` and `dist.reference` in the consumer `composer.lock`.

### “Report privacy verification failed safely”

The command intentionally withholds sensitive details. Reproduce with the focused Release tests and inspect redaction/path-exposure code without printing fixture canaries into shared logs.

## Maintainer handoff checklist

- [ ] `composer check` passes.
- [ ] Coverage and selective mutation gates pass when affected logic changes.
- [ ] Privacy verification passes when reports, diagnostics, paths, or Composer output change.
- [ ] Compatibility expectations are reflected in package constraints and documentation.
- [ ] Changelog and `docs/releases/vX.Y.Z.md` identify the exact release.
- [ ] Wiki pages and examples describe the released behavior; all four materialized sets pass `--check`, each destination passes `--check-published`, and agents are explicitly required to include this update.
- [ ] `verify-release.php` passes for the exact version.
- [ ] Three distribution payloads were prepared from the intended clean commit and reviewed.
- [ ] Signed distribution tags exist before the signed monorepo tag is pushed.
- [ ] GitHub Actions completes all release gates and publishes verified artifacts.
