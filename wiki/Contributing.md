# Contributing

PHP Upgrade Preflight welcomes focused fixes, tests, documentation, framework rules, fixtures, and reproducibility improvements. This Wiki guide reflects repository policy on **2026-08-19**; `CONTRIBUTING.md` remains the canonical in-repository source.

## Before you start

For a large change, open an issue first to confirm direction. Report security vulnerabilities privately through `SECURITY.md`, never in a public issue or pull request.

Every contribution must preserve four product rules:

- the analyzed project stays unchanged;
- conclusions remain evidence-backed;
- JSON is the canonical report and Markdown is a projection;
- Core stays framework-neutral.

## Development setup

The documented Docker path is:

```bash
git clone https://github.com/ValentinNikolaev/php-upgrade-preflight.git
cd php-upgrade-preflight
docker compose build php
docker compose run --rm php composer install
docker compose run --rm php composer check
```

With a compatible local PHP/Composer toolchain:

```bash
composer install
composer check
```

The project supports PHP 8.0 as its package floor. CI additionally exercises newer PHP versions and Windows.

## Work from narrow to broad

During development, run the smallest relevant check:

```bash
composer test:core
composer test:cli
composer test:laravel
composer test:unit
composer test:integration
composer test:smoke
composer test:fixtures
composer analyse
composer lint
```

Before opening a pull request, run:

```bash
composer check
```

`composer check` validates all manifests, runs unit/integration/smoke tests, both PHPStan configurations, and formatting in dry-run mode. It does not update dependencies or query live vulnerability data.

If Docker stops the long integration suite near Composer's default 300-second process timeout, run:

```bash
docker compose run --rm -e COMPOSER_PROCESS_TIMEOUT=0 php composer check
```

That environment variable affects the outer Composer script timeout, not the analyzer's bounded scenario timeouts.

## Coverage, mutations, and budgets

```bash
composer test:coverage
composer test:mutation
```

Coverage is an exact ratchet: overall and critical-module ratios cannot decline, and new uncovered fingerprints fail. Do not lower the baseline to hide missing tests. Rewrite it only after reviewing a complete successful Clover run:

```bash
php tools/verify-coverage.php build/coverage/clover.xml --write-baseline
```

Selective mutations must all be killed by their focused tests. The integration suite also enforces process, runtime, memory, privacy, report-size, and determinism budgets for representative and worst staged chains.

## Fixture snapshots

Ordinary fixture tests compare current output with committed snapshots. Regeneration is explicit.

POSIX:

```bash
PHP_UPGRADE_PREFLIGHT_UPDATE_SNAPSHOTS=1 vendor/bin/phpunit --filter LaravelFixtureAnalysisTest
```

PowerShell:

```powershell
$env:PHP_UPGRADE_PREFLIGHT_UPDATE_SNAPSHOTS = '1'
vendor\bin\phpunit --filter LaravelFixtureAnalysisTest
Remove-Item Env:PHP_UPGRADE_PREFLIGHT_UPDATE_SNAPSHOTS
```

Docker:

```bash
docker compose run --rm -e PHP_UPGRADE_PREFLIGHT_UPDATE_SNAPSHOTS=1 php composer test:fixtures
```

Review every JSON/Markdown pair. Snapshot normalization removes host paths, separators, and timing noise but preserves meaningful commands, outcomes, findings, evidence, and lock fingerprints.

Never regenerate archived `tests/fixtures/contracts/v0.1` or `v0.2.1` during ordinary work. They are released compatibility evidence; correction requires explicit compatibility review and provenance.

## Schema changes

Published schema files are immutable. Any additive or breaking serialized shape change requires:

1. a new schema version and file;
2. an updated canonical Core snapshot;
3. consumer migration documentation;
4. tests for JSON and Markdown projection;
5. changelog and Wiki updates.

A finding or guidance correction may retain the schema only when the serialized shape remains compatible.

## Pull request checklist

- Keep one coherent change per pull request.
- Add tests for every behavior change and failure boundary.
- Preserve target-project immutability; compare before/after digests for integration fixtures.
- Update affected `README.md`, `docs/`, `CHANGELOG.md`, and Wiki pages in the same change.
- Check every changed command, link, supported-version claim, and example.
- Run focused checks during development and `composer check` before review.
- Do not commit credentials, debug workspaces, generated reports, or unrelated formatting.

Example verification for a Laravel rule change:

```bash
composer test:laravel
composer test:fixtures
composer analyse
composer lint
composer check
git status --short
```

## Five packages, three distributions

All five package manifests participate in monorepo validation:

- `core`
- `cli`
- `laravel`
- `test-adapter`
- `legacy-test-adapter`

Only `core`, `cli`, and `laravel` are supported external distributions. The two adapter packages are test fixtures. Do not add them to release archives or Packagist release steps.

## Mandatory documentation policy for releases and agents

Every behavior change must update affected public documentation in its pull request. The requirement becomes a hard release condition before any `vMAJOR.MINOR.PATCH` tag:

1. update `CHANGELOG.md` and `docs/releases/vVERSION.md`;
2. update all affected Wiki pages, commands, examples, compatibility tables, service descriptions, schemas, and limitations;
3. verify the text is understandable to a Junior developer and a technical manager;
4. run `composer release:verify -- VERSION` and complete the release checklist.

Codex, Claude, and all other coding agents are explicitly required to perform the Wiki update when their work creates or prepares a release tag. They must not defer it as optional cleanup. As of 2026-08-19, `verify-release.php` checks repository metadata, changelog, and release notes, but Wiki freshness remains a mandatory human/agent review item.

## Release changes

Use a clean release-candidate commit and follow [Quality and Release Tooling](Quality-and-Release-Tooling). A manual Release workflow run packages without publishing. A matching signed annotated tag publishes only after metadata, quality, compatibility, security, distribution, fresh-clone, archive-consumer, and Packagist gates pass.

Do not add `version` fields to Composer manifests. Exact versions come from matching Git tags.

## Find the owning package first

Use the package boundary to avoid coupling:

| Change | Owning package |
| --- | --- |
| Generic Composer scenarios, blockers, source inventory, evidence, report model | `packages/core` |
| Standalone options, parsing, adapter discovery, generic command delivery | `packages/cli` |
| Laravel catalog, rules, stages, source visitor, Artisan integration | `packages/laravel` |
| Current third-party adapter capability fixture | `packages/test-adapter` |
| Backward-compatibility adapter fixture | `packages/legacy-test-adapter` |

Do not solve a Laravel requirement by importing Laravel code into Core.

Do not add analysis decisions to a command controller.

Do not put fixture-only package names into production behavior.

See [[Package Map|Package-Map]] and [[Class and Service Index|Class-and-Service-Index]] before introducing a new service.

## Change-to-test matrix

| Change type | Minimum focused evidence |
| --- | --- |
| Request/model validation | Unit tests for accepted, rejected, and normalization cases |
| Composer command or environment | Runner tests plus an integration fixture proving external behavior |
| Blocker parsing | Transcript tests with solver and operational counterexamples |
| Source visitor | AST fixture tests with line, symbol, usage type, and parse failure |
| Adapter rule | Positive, negative, non-applicable, and throwing paths |
| Staged planning | Adjacency, exact PHP provenance, gap, collision, and budget tests |
| Report field | Model, schema, JSON/Markdown, snapshots, and migration documentation |
| Redaction/path behavior | Synthetic canary tests on strings and structured values |
| CLI option | Vocabulary, parser, help, command, and CLI documentation tests |

A happy-path unit test is not sufficient for a trust boundary.

Test the classification of failure, not only that an exception occurred.

## Example: changing a Core report field

Suppose a new field is added to a blocker.

The complete path normally includes:

1. Add the validated property to `Blocker` or a related value.
2. Populate it in the parser, attribution service, or grouper that owns the fact.
3. Serialize it in `toArray()`.
4. Update schema with the correct required/optional compatibility decision.
5. Update canonical Core and relevant Laravel fixtures.
6. Update Markdown rendering from canonical report data.
7. Add consumer migration text if shape or meaning changed.
8. Update Wiki report and concept pages.

Do not calculate the field only in `MarkdownReportWriter`.

That would create a second, non-canonical analysis path.

## Example: changing a Laravel rule

Start with applicability and sources.

Decide whether the change belongs in a catalog definition or executable rule logic.

Verify the target package appears in the correct project state.

Test the relevant hop and at least one adjacent non-applicable hop.

Check evidence class, confidence, context, and references.

Run fixture tests and review both JSON and Markdown changes.

If the rule can produce a stage remediation, test temporary target constraints separately from ordinary guidance.

Never rewrite archived released fixtures to make a new rule appear backward compatible.

## Evidence review

For every new evidence item, confirm:

- the namespace is stable and valid;
- creation order is deterministic;
- the summary states an observation, not an unsupported conclusion;
- context contains no secret or private absolute path;
- evidence class matches the source;
- confidence matches support strength;
- a report claim references the ID;
- no orphan evidence remains.

`UpgradeReport` rejects missing and orphan evidence references.

Use `addOnce()` only when strict content identity really means one reusable observation.

See [[Determinism and Evidence|Determinism-and-Evidence]].

## Review target immutability

Tests that run Composer must compare the target tree before and after analysis.

Expected writes belong only to analyzer-owned temporary workspaces and an explicitly requested report destination outside the target tree.

Debug mode can retain workspaces.

Retained workspaces may contain copied Composer metadata and should not be committed.

If cleanup fails, preserve the uncertainty in the report rather than hiding it.

## Pull request description template

A useful description answers:

```text
Problem:
Owning package/service:
Behavioral change:
Evidence and trust boundaries:
Compatibility/schema impact:
Focused tests run:
Full checks run:
Documentation updated:
Known limitations:
```

For a manager, state user-visible scope and compatibility impact plainly.

For a reviewer, link the exact tests and canonical snapshots.

Do not describe an analyzer finding correction as a runtime guarantee.

## Documentation review

Documentation examples must be runnable or explicitly marked abbreviated.

Use current option names from `CommandLineOptions` and the Artisan signature.

Use current report vocabulary from models and schema.

Cross-link the detailed page instead of copying a long canonical table into several pages.

Check every local Markdown link and every Wiki link.

Keep required Wiki pages between 300 and 900 source lines when that is the repository documentation contract.

Verify code fences are balanced.

Ensure every page in `_Sidebar.md` exists and important main pages are reachable from it.

## Final local review

Before handoff:

```bash
composer check
git diff --check
git status --short
```

Read the diff as a reviewer after tests pass.

Check that no unrelated user changes were reformatted or removed.

Inspect temporary paths created during the task and delete only known reproducible artifacts.

Report retained diagnostics and why they remain.

For release-tag work, complete [[Release Wiki Strategy|Release-Wiki-Strategy]] in addition to normal code checks.
