# PHP Upgrade Preflight Development Plan

Last updated: 2026-08-07

This is a directional plan, not a rigid contract. Keep milestone order unless repository evidence shows a safer dependency order. Mark completed work with `[x]`, active work with `[~]`, and remaining work with `[ ]`.

## Release Target

v0.1 should analyze a Laravel 7 Composer fixture against Laravel 8/9 and PHP 8.0/8.1 targets, leave the fixture unchanged, and produce deterministic JSON plus equivalent Markdown with resolution status, package changes, blockers, source impact, Laravel findings, staged actions, risk, effort, uncertainty, and evidence.

## Milestone 0: Architecture Scaffold

- [x] Create the private monorepo and three Composer packages.
- [x] Add core request/report models and `UpgradeAnalyzer` contract.
- [x] Add initial state readers, isolated scenario runner, lock diff, blockers, source scan, reports, CLI, and Laravel adapter.
- [x] Verify manifests, dependency installation, PHP syntax, autoloading, and generic CLI startup in Docker.
- [x] Verify Laravel provider registration, boot, and Artisan command startup in a Laravel application fixture.
- [x] Add a Docker-based PHP 8.3 development interpreter with Composer and a PHP 8.0 dependency-resolution platform.

Acceptance gate: all manifests validate, dependencies install, every PHP file parses, and both command entry points can show or return valid usage without fatal errors.

## Milestone 1: Test and Quality Foundation

Status: complete and verified on 2026-08-06.

- [x] Choose PHPUnit or Pest with versions compatible with PHP 8.0.
- [x] Add root scripts for tests, static analysis, and coding-style checks.
- [x] Add fixture helpers that snapshot every original file before analysis and assert byte-for-byte immutability afterward.
- [x] Add unit-test structure for core, CLI, and Laravel packages.
- [x] Add CI covering the supported runtime matrix, initially PHP 8.0 through the current stable PHP release.
- [x] Establish deterministic JSON snapshot normalization for paths and temporary directories.

Acceptance gate: one command runs the full local quality suite, CI runs it across supported PHP versions, and an isolation test proves the analyzed project is unchanged.

## Test Architecture

Use four layers. Do not collect arbitrary public `composer.json` files: they are incomplete without matching lockfiles and source context, and live dependency metadata makes failures hard to reproduce.

### 1. Unit tests: fast and offline

Run on every change. Test one class or phase with hand-built DTOs and small JSON/PHP fixtures.

- `packages/core/tests/Unit`: target parsing, state readers, lock diff classification, blocker parsing, evidence ledger, risk/effort ranges, report serialization, and PHP AST extraction.
- `packages/cli/tests/Unit`: argument parsing, validation, output selection, and exit-code policy using a fake analyzer.
- `packages/laravel/tests/Unit`: detection and each compatibility rule using minimal project states and source-usage records.
- Introduce process-runner and filesystem/workspace interfaces where needed so unit tests do not launch Composer or touch production paths.

### 2. Integration tests: deterministic Composer behavior

Run in CI without Packagist. Build tiny fake packages under `tests/fixtures/repository/` and expose them through Composer `path` repositories. Commit fixture `composer.json` and `composer.lock` files.

Required fixture projects:

- `package-upgrade-success`: direct and transitive packages both move.
- `package-upgrade-blocked`: a transitive constraint prevents the target.
- `php-platform-too-low`: target package requires a newer PHP platform.
- `php-platform-too-high`: legacy package rejects the target PHP platform.
- `extension-missing`: package requires a deliberately unavailable fixture extension or use a captured solver-output parser fixture when platform variance would make execution unstable.
- `abandoned-package`: lock metadata marks a package abandoned.
- `invalid-composer-json` and `missing-lock`: structured input failures.
- `source-usage`: PHP files covering every supported AST usage type plus one syntax error.

Each scenario copies a fixture to a fresh test temp directory, hashes the original tree before and after, runs the analyzer, and removes only the test-owned temp copy.

### 3. Framework end-to-end fixtures

Store minimal application-shaped fixtures under `tests/fixtures/projects/`; these are curated inputs, not full framework installations. Include only Composer files and source/config files needed by the rule under test.

- Laravel 7/PHP 7.4 to Laravel 8 success.
- Laravel 7/PHP 7.4 to Laravel 9 success with dependency changes.
- Laravel 7 blocked by an old `illuminate/support` consumer.
- Laravel 7 with `facade/ignition` and legacy skeleton files.
- Laravel 7 with incompatible PHP or extension requirements.
- Laravel package matrix covering Passport, Sanctum, Horizon, Telescope, Collision, PHPUnit, Mockery, and Testbench.

Assert the canonical JSON first. Assert Markdown only for projection and key sections; avoid duplicating every JSON assertion in prose snapshots.

### 4. Live smoke and package compatibility tests

Run separately from the deterministic suite, nightly or before release, because these tests use networked Composer metadata.

- Install each package independently from its own `composer.json`.
- Test normal and `--prefer-lowest` dependency resolution on PHP 8.0, 8.1, 8.2, 8.3, 8.4, and newer supported releases.
- Smoke-test the root monorepo path repositories and executable wiring.
- Create temporary Laravel skeletons for a small declared matrix and install the Laravel adapter to verify service-provider discovery and Artisan registration.
- Analyze one or two pinned, intentionally selected public sample applications only as a release confidence check. Record repository commit SHAs; never depend on moving default branches.
- Cache Composer downloads in CI, but never cache generated fixture lockfiles as test truth.

Live smoke failures should be reported separately from deterministic test failures so ecosystem drift is visible without disguising product regressions.

### Test command contract

The eventual root commands should make scope obvious:

```bash
composer test              # unit + deterministic integration
composer test:unit
composer test:integration
composer test:smoke        # networked; opt-in locally
composer test:all          # deterministic suite + smoke
composer analyse
composer check             # validate + style + analyse + deterministic tests
```

The first implementation step is the core unit suite plus the local-repository integration harness. Real Laravel skeleton smoke tests come after the deterministic Laravel rules are implemented.

## Milestone 2: Core Domain and Report Contract

- [x] Add `UpgradeTargetSet` with validation, duplicate handling, PHP-target normalization, and deterministic ordering.
- [x] Add `EvidenceLedger` to allocate unique IDs, validate references, and prevent orphaned findings.
- [x] Extract target normalization, framework rule execution, risk/effort estimation, and report assembly into independently tested phases.
- [x] Make DTOs immutable where PHP 8.0 permits and tighten parameter/return types.
- [x] Define and snapshot the v0.2 JSON schema, including schema/tool version metadata.
- [x] Populate root constraint changes, staged plan, test guidance, and uncertainty sections.

Acceptance gate: report schema is stable, every finding references existing evidence, and each pipeline phase has focused tests.

## Milestone 3: Composer Scenario Engine

Status: complete and verified on 2026-08-06.

- [x] Add baseline validation before target scenarios.
- [x] Add target-platform-only and staged-target scenarios where applicable.
- [x] Define scenario selection rules so redundant scenarios are skipped deterministically.
- [x] Capture Composer version, exact command, duration, exit status, stdout/stderr excerpts, and candidate lock evidence.
- [x] Run `composer prohibits` or `why-not` in the temp workspace after failed target resolution when it adds diagnostic value.
- [x] Handle missing Composer, timeout, invalid JSON, missing lockfile, process failure, and cleanup failure as structured outcomes.
- [x] Confirm scripts/plugins are disabled and debug workspaces are the only preserved workspaces.

Acceptance gate: successful and blocked package-only fixtures produce stable scenario results, diagnostics, and no project mutations.

## Milestone 4: Lock Diff and Blocker Intelligence

Status: complete and verified on 2026-08-07 with Composer-backed successful and blocked package fixture reports.

- [x] Test added, removed, upgraded, downgraded, source-ref, and dist-ref changes.
- [x] Identify direct versus transitive packages and major-version jumps.
- [x] Track Laravel/Illuminate and Symfony component families without putting framework concepts into core.
- [x] Parse all specified blocker types into structured fields: subject, requested constraint, blocker, locked version, conflict, dependency path, options, confidence, and evidence.
- [x] Deduplicate one root conflict reported by multiple scenarios while retaining scenario evidence.
- [x] Detect abandoned packages from lock metadata.

Acceptance gate: fixture outputs identify the actionable root cause and package transition without relying on raw Composer prose alone.

## Milestone 5: Parser-Based Source Impact

- [x] Replace regex extraction with `nikic/php-parser` while keeping parse failures non-fatal and evidenced.
- [x] Detect imports, fully qualified names, inheritance, interfaces, traits, attributes, static calls, function calls, and instantiated classes.
- [ ] Add deterministic inspection for config references, service providers, middleware, console commands, and test doubles/mocks.
- [x] Exclude `vendor/` and generated/cache paths by default; support explicit source paths safely.
- [ ] Aggregate duplicate usages without losing file/line evidence.

Acceptance gate: source fixtures cover every supported usage type, syntax errors become uncertainties, and findings include precise file and line evidence.

## Milestone 6: Laravel 7 to 8/9 Rules

- [ ] Strengthen Laravel and Illuminate detection using root constraints plus lock data.
- [ ] Add conservative rules for framework/PHP constraints, Passport, Sanctum, Horizon, Telescope, PHPUnit, Mockery, Symfony components, old `illuminate/support`, and the existing legacy packages.
- [ ] Inspect `app/Http/Kernel.php`, `config/app.php` providers/aliases, and Laravel skeleton indicators through detected source usage.
- [ ] Map rules to target Laravel/PHP ranges using `composer/semver`.
- [ ] Distinguish exact metadata/source evidence from heuristic migration guidance.
- [ ] Test Laravel 7 to 8, Laravel 7 to 9, blocked Illuminate constraints, Ignition, and PHP/extension conflict fixtures.

Acceptance gate: the six required Laravel fixture classes produce conservative, evidence-linked findings with no claims outside encoded rules.

## Milestone 7: CLI, Artisan, and Reporting UX

- [ ] Validate paths, targets, formats, output destinations, and conflicting PHP options with clear exit codes.
- [ ] Decide whether to retain the small custom CLI parser or adopt a PHP 8.0-compatible console component based on dependency cost and testability.
- [ ] Ensure framework integrations are registered in generic CLI mode when requested or detected.
- [ ] Ensure the Laravel command defaults to the current project and delegates to the same analyzer operation.
- [ ] Render Markdown entirely from the canonical report without dropping evidence or uncertainty.
- [ ] Use stdout for reports, stderr for diagnostics, and nonzero exits for invalid invocation or internal failure; document the policy for a valid but blocked analysis.

Acceptance gate: CLI and Artisan end-to-end tests produce equivalent canonical data and predictable files/exit codes.

## Milestone 8: v0.1 Hardening and Release

- [ ] Run all six required fixture scenarios and approve JSON/Markdown snapshots.
- [ ] Test Windows and Unix path/process behavior.
- [ ] Add installation, external-analysis, CLI, Artisan, schema, limitations, and troubleshooting documentation.
- [ ] Add changelog, contribution guidance, security policy, release checklist, and package metadata needed for Packagist.
- [ ] Review dependency floors against PHP 8.0 and Laravel 8/9/10 installability.
- [ ] Perform a clean-install smoke test and read-only audit from a fresh clone.
- [ ] Tag package versions consistently and prepare the v0.1 release.

Acceptance gate: a fresh user can install and run the analyzer against the documented fixtures, reproduce reports, and verify that target projects remain untouched.

## Recommended Next Work Session

Milestones 0 through 4 are complete. Continue with Milestone 5:

1. Replace regex source extraction with complete `nikic/php-parser` coverage while keeping parse failures non-fatal and evidenced.
