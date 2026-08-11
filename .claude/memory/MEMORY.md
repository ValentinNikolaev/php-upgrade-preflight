---
name: php-upgrade-preflight-project
description: Durable architecture, constraints, and current-state context for PHP Upgrade Preflight development.
type: project
related:
  - ../DEVELOPMENT_PLAN.md
last_updated: 2026-08-10
---

# Project Memory

## Focused Memories

- [windows-git-signing.md](windows-git-signing.md) — project — Windows OpenSSH override required for agent-backed signed commits

## Product

PHP Upgrade Preflight is a local, read-only analyzer for Composer-based PHP projects. Its semantic operation is:

```php
UpgradeAnalyzer::analyzeUpgrade(UpgradeRequest $request): UpgradeReport
```

Inputs are Composer files, requested package/PHP constraints, and optional source paths. Outputs are canonical JSON and derived Markdown. Composer remains authoritative for dependency resolution.

The initial use case is Laravel 7 to Laravel 8/9 and PHP 7.4 to PHP 8.0/8.1. The runtime packages target PHP `^8.0`; older project PHP versions are modeled with Composer `config.platform.php`. Laravel 7 projects still running PHP 7.x use the CLI and desired adapters from a separate Composer tools-directory installation. v0.2 does not ship or support a PHAR or versioned container image.

## Architecture

The development repository is a private Composer monorepo with path repositories:

- `php-upgrade-preflight/core`
- `php-upgrade-preflight/cli`
- `php-upgrade-preflight/laravel`

Core owns deterministic analysis. CLI owns generic command parsing, Composer-metadata adapter discovery, output, and exit policy. Laravel owns detection, rules, service-provider registration, and Artisan integration. Third-party framework packages register zero-argument `FrameworkIntegration` implementations through `extra.php-upgrade-preflight.framework-adapters`; adapters implement core contracts and must not duplicate the analysis pipeline. The monorepo's `packages/test-adapter` is test-only and is not a published product package.

The intended pipeline is:

```text
UpgradeRequest
  -> ProjectStateBuilder
  -> target normalization
  -> ComposerScenarioRunner
  -> LockDiffBuilder
  -> BlockerGrouper
  -> SourceUsageScanner
  -> framework rules
  -> risk and effort estimation
  -> UpgradeReport
```

Evidence classes are `E1` Composer solver, `E2` package metadata, `E3` project source, `E4` maintainer documentation, and `E5` heuristic inference. Confidence is high for exact solver/lock/source/metadata evidence, medium for official documentation mapped to detected use, and low for heuristics.

## Current State

The initial scaffold exists across all three packages. It includes typed DTO-style models, Composer JSON/lock readers, temporary workspace handling, baseline validation plus three full-target update scenarios, lock diffing, structured blocker parsing, parser-based source scanning, report writers, the generic CLI, Laravel detection/rules, and the Artisan command. `ScenarioSelector` owns deterministic scenario ordering and retains the first candidate for each normalized target and effective-option execution key. PHP-only requests skip the ineffective all-dependencies variant while retaining the distinct minimal-changes strategy. Requests combining package and PHP targets also run a `target-platform-only` probe. They run `staged-targets` only when the current PHP is known from the request or the project's Composer platform and differs from the target PHP; an equivalent staged probe is silently deduplicated, while missing current-PHP evidence records an uncertainty. The platform-only probe tests the requested PHP against current package constraints, while the staged probe tests requested packages on the known current PHP. These partial probes provide ordering diagnostics but do not determine combined-target feasibility, suppress full-target blockers, or supply the report's candidate lock. Baseline validation runs first against unchanged isolated Composer files and is excluded from target feasibility, lock selection, and blocker suppression. Scenario results retain the broad `solver`, `validation`, and `operational` failure classes while exposing exact outcomes for missing Composer, timeouts, invalid JSON, missing lockfiles, process failures, workspace failures, and cleanup failures. Project-input JSON and lockfile failures produce canonical reports without invoking Composer. Every Composer scenario, diagnostic, and version probe explicitly disables scripts and plugins. `ComposerBlockerParser` prefers `prohibits --tree` relations and falls back to solver output, producing blocker subject, requested constraint, blocking package, locked version, conflict, dependency path, resolution options, confidence, and evidence references for the specified blocker types. Equivalent root constraint conflicts from multiple scenarios are collapsed deterministically into the first blocker while retaining each scenario evidence reference. Successful and failed scenario workspaces are removed unless debug mode intentionally preserves them; cleanup failures remain structured operational outcomes. Raw recovery paths stay available only through in-process accessors, default serialization uses `[ANALYZER_WORKSPACE]`, and exact sanitized `temp_path` output requires explicit non-shareable debug mode. Lock package references are classified as direct from the corresponding manifest's `require` and `require-dev` entries, including each scenario's temporary manifest, and package changes expose direct/transitive classification plus evidence-safe numeric major-version jumps. Active framework integrations may optionally implement the generic `PackageFamilyClassifier` contract; core treats its labels as opaque, while the Laravel adapter owns the `laravel/*`, `illuminate/*`, and `symfony/*` mappings. Family labels are normalized, deduplicated, sorted, and included with each changed package. Core DTOs use private typed state and explicit typed accessors so they remain immutable on the PHP 8.0 runtime floor without relying on PHP 8.1 `readonly` properties; collection accessors return arrays by value. `UpgradeTargetSet` validates Composer targets, rejects conflicting duplicates, normalizes exact PHP platform versions, and supplies deterministic target ordering to requests, scenarios, Composer commands, and report serialization. `EvidenceLedger` owns deterministic, namespace-scoped evidence ID allocation. Report construction rejects duplicate evidence IDs, missing or empty finding references, and evidence that is not referenced by a blocker, source usage, framework finding, root constraint change, plan stage, or evidenced uncertainty. The core pipeline delegates target normalization, framework activation and rule execution, risk/effort estimation, report-section guidance, and final report construction to independently tested phases. `ReportSectionBuilder` compares requested targets with root requirements, produces evidence-backed constraint changes and staged actions, derives project-aware test guidance, and records runtime and test-command uncertainty. Development reports use strict Draft 2020-12 schema `0.7`: platform provenance distinguishes explicit extension assumptions from unmodeled analyzer-runtime state, raw AST observations live under `source_inventory`, actionable correlations live under `source_impact`, and framework guidance remains independent from Composer resolution. Framework findings must reference supported assessed hops. Historical schemas and snapshots from `0.2` through `0.6` remain immutable for earlier consumers. Markdown projects the same canonical data.

Abandoned packages are detected from the selected candidate lock, or the baseline lock when no candidate exists, using Composer's boolean or package-or-URL `abandoned` metadata. These maintenance advisories carry E2 evidence, locked versions, direct/transitive context, and preserved alternative guidance; duplicate warning prose contributes solver evidence without replacing the structured metadata. They remain serialized in the blocker collection for the v0.6 contract but do not make a successful Composer resolution blocked or trigger solver-failure risk and rerun guidance.

Target-platform inputs model explicit extension presence, absence, and exact versions separately from analyzer-runtime state. Request assumptions override Composer `config.platform`, every effective assumption records provenance, and unmodeled host extensions remain explicit uncertainty. Determinism claims are scoped to each explicitly modeled extension; partial assumption lists never claim complete host independence, and a future complete target-platform input is required before unlisted extension state can stop depending on the analyzer runtime. Offline path-repository fixtures cover required, missing, disabled, compatible, and incompatible extension outcomes without network access.

Report privacy is enforced before publication, not only in writers. `SensitiveOutputRedactor` recursively sanitizes URL user information, authorization and Composer auth values, escaped diagnostics, and common provider tokens before they reach evidence or canonical output. `PathExposurePolicy` maps project, report-output, local-repository, and analyzer-workspace roots to stable markers in default JSON and Markdown. The seeded privacy harness scans reports, evidence, rendered exception chains, debug output, command diagnostics, and CI logs; credentials remain redacted even in debug mode.

The repository has a verified Docker toolchain using PHP 8.3 by default and Composer 2, with working PHP-version overrides from 8.0 through 8.5. The root `composer check` command validates every package manifest, runs PHPUnit, PHPStan, and PHP CS Fixer, and is the same quality gate used by the GitHub Actions runtime matrix. The deterministic analyzer isolation test runs all scenario paths through temporary workspaces and proves the original fixture remains byte-for-byte unchanged.

Source inspection uses streaming `nikic/php-parser` visitors after a complete namespace-resolution pass. It classifies imports, explicit fully qualified names, inheritance, interfaces, traits, attributes, static access, named function calls, and instantiations with file/line evidence. A contextual visitor also emits exact config keys, service-provider subclasses and registrations, middleware class references, console-command subclasses and registrations, and explicit test-double/mock targets. Repeated occurrences with the same file, symbol, and usage type are one source-impact finding; its first occurrence is the representative location and its evidence references retain every exact file/line occurrence. Dynamic names are not guessed. Malformed files produce linked uncertainties without stopping the scan. Recursive default scans prune dependency and common generated/cache directories, while a directly requested excluded directory remains scannable.

Laravel detection prefers the exact locked `laravel/framework` version and falls back to its root constraint. Modular Illuminate projects activate the Laravel adapter only when at least one `illuminate/*` package is a root requirement; matching lock entries refine a common detected version. Transitive Illuminate packages alone do not activate the adapter, which avoids false detection from the analyzer's own Illuminate dependencies. When rooted Illuminate components do not share one version or constraint, the adapter detects the project without claiming a single framework version. Laravel adapter source defaults retain `src/` alongside application directories so Illuminate packages do not lose the generic source scan when the adapter activates.

Laravel 7 to 8/9 rules derive one unambiguous target major from requested `laravel/framework` or `illuminate/*` constraints with `composer/semver`; ambiguous cross-major targets produce no range claim. Laravel 7 status is established conservatively across all rooted Illuminate components, so modular projects do not require `illuminate/support` specifically. Rules cover framework and PHP constraints, Passport, Sanctum, Horizon, Telescope, PHPUnit, Mockery, direct Symfony component constraints, packages pinned to old `illuminate/support`, Ignition, Collision, CORS, trusted proxies, UI, and Testbench. First-party package checks prefer exact locked `require` metadata over fallback documented version ranges and retain exact requested constraints rather than widening them to the whole target major. Fallback E4 sources are selected per Laravel target major. Framework rules receive parser-derived source usages, allowing `app/Http/Kernel.php` middleware and `config/app.php` provider/facade-alias registrations to reuse exact E3 evidence. Skeleton comparison guidance is separately labeled E5 with low confidence and explicitly identifies review locations rather than confirmed incompatibilities. Six offline application-shaped fixtures cover Laravel 7 to 8, Laravel 7 to 9, an old Illuminate consumer, Ignition with legacy skeleton entries, combined PHP/extension solver conflicts, and the first-party/test package matrix. Their integration tests assert an explicit finding allowlist, evidence-reference integrity, structured blockers, source evidence classes, and byte-for-byte fixture immutability.

The v0.1 compatibility contract is independently frozen in `tests/fixtures/contracts/v0.1.json`: reflection locks `UpgradeAnalyzer::analyzeUpgrade`, an end-to-end probe locks the generic CLI help, parse surface, and current-directory default, command executions lock exits 0/1/2 including valid blocked and unknown reports, and SHA-256 values lock schema `0.6` plus twelve Laravel JSON/Markdown artifacts copied byte-for-byte from signed tag `v0.1.0` at commit `a8d154826f35fcb25a22868556534cc8c0331c0c`. Those release artifacts live under `tests/fixtures/contracts/v0.1/laravel-reports` and remain independent from current development snapshots. The v0.2 Laravel scope retains the v0.1 7 to 8/9 paths and adds adjacent rule packs for 8 to 9, 9 to 10, 10 to 11, 11 to 12, and 12 to 13. Laravel 13 is intentionally included based on commit-pinned official guide, framework-manifest, and application-manifest evidence; its PHP `^8.3` target requirement does not raise the analyzer's PHP `^8.0` runtime floor. Tool version `0.2.1` emits schema `0.7`; the signed v0.1 schema `0.6` contract remains immutable.

The generic CLI retains a dedicated custom parser rather than depending directly on `symfony/console`. The command has one subcommand and a small fixed option surface, so a console dependency would add string, service-contract, and polyfill packages without improving current testability; parsing is isolated in `CommandLineParser` with direct unit coverage. CLI and Artisan validate project/source paths, exact current and target PHP versions, target conflicts, formats, requested framework adapters, and report destinations before analysis. Invalid invocation exits 2, unexpected failures after validation exit 1 regardless of exception type, and every completed canonical report exits 0 even when `resolution.status` is `blocked` or `unknown`. Reports use stdout and diagnostics use stderr when the console exposes separate streams. Explicit source paths must exist inside the analyzed project, while report output must remain outside it. Generic CLI construction discovers installed adapters from Composer metadata so core detection can activate them; packages are ordered deterministically, duplicate classes or case-insensitive names and invalid advertised registrations fail closed, absent packages contribute nothing, and explicit `--framework` requests are rejected early when their adapter package is unavailable. Artisan defaults to the Laravel application's base path rather than the process working directory.

The separate compatibility workflow performs clean normal and lowest-dependency consumer installs for core and CLI on PHP 8.0, Laravel 8/9 on PHP 8.0, Laravel 10 on PHP 8.1, Laravel 11/12 on PHP 8.2, and Laravel 13 on PHP 8.3. The release workflow runs version/schema checks, the PHP 8.0-8.5 deterministic matrix, compatibility and dependency audits, and fresh-clone plus release-artifact consumers on Windows and Linux. Release archives include a verified dependency inventory, source/build provenance, and checksums. Tag-triggered publication requires GitHub-verified signed monorepo and distribution tags, byte-identical complete distribution payloads, Packagist availability, and a published quick start that proves target immutability. Distribution-repository splits, signed tags, and Packagist synchronization remain explicit maintainer operations.

## Durable Decisions

- PolyForm Noncommercial 1.0.0 for free noncommercial use; commercial use requires a separate paid license from Valentin Nikolaev.
- Product name: PHP Upgrade Preflight; repository: `php-upgrade-preflight`.
- Command names: generic `upgrade-intel analyze`; Laravel `upgrade:analyze`.
- CLI parsing remains dependency-light and custom until the command surface grows enough to justify a console component.
- JSON is canonical and Markdown is a projection of the same report.
- Default JSON and Markdown are shareable artifacts: absolute project, output, local-repository, and analyzer-workspace roots use stable markers; debug artifacts with exact sanitized workspaces are explicitly non-shareable.
- Credential redaction is a model-ingress and publication invariant across evidence, reports, exceptions, debug output, and logs, not a renderer-only concern.
- Reports may be incomplete but must expose uncertainty rather than unsupported certainty.
- Analyzed projects are immutable inputs. Temporary Composer files are disposable analysis artifacts.
- Effort is a range with assumptions and confidence, never a precise promise.
- Testing uses four layers: offline unit tests, deterministic Composer integration tests backed by local path repositories, curated Laravel end-to-end fixtures, and opt-in networked smoke tests. Public sample projects must be pinned to commit SHAs and are release checks, not canonical test fixtures.
- The default development interpreter is the Compose `php` service on PHP 8.3. The root Composer manifest pins dependency resolution to PHP 8.0.30, while CI will execute the supported runtime matrix including PHP 8.4 and newer supported releases. PHP 8.3 avoids upstream deprecation output from PHP-8.0-compatible Laravel dependencies during ordinary CLI development. The container is a CLI toolchain only; target project PHP versions remain simulated by analysis scenarios.
- All three packages release in lockstep. During initial development, fixes and maintenance use patch releases, while features and intentional breaking changes use a new `0.MINOR` line with prominent migration notes. Major remains `0` until the project deliberately commits to a stable public API; a future PHP 9 floor is a possible input to that decision, not an automatic `1.0` trigger.
- The active release gate permits only `0.2.x` from `main`. Reopening the frozen `0.1.x` maintenance line or advancing to another release series requires an explicit coordinated policy and metadata change.
- The v0.2 Laravel scope includes adjacent rule packs from 8 to 9 through 12 to 13 while retaining the v0.1 Laravel 7 paths. Upstream evidence is commit-pinned in the transition contract; public support is claimed only after each planned pack and host line has its required fixtures and compatibility gates.
- v0.2 supports one external execution path: install the CLI and desired adapters with Composer in a separate tools directory. PHAR and versioned container artifacts are intentionally outside the v0.2 support and release surface; repository Docker files remain development tooling.

## Repository Notes

At bootstrap, `.gitignore` was already staged as an empty file and `.idea/` was untracked. These were not part of the implementation and must not be removed or overwritten without user direction.

The original architecture source is:

`I:/Development/Git/ValentinNikolaev/laravel-package-intelligence/ARCHITECTURE_PROMPT.md`

Use `.claude/DEVELOPMENT_PLAN.md` for active sequencing. This file is durable context, not a progress diary.
