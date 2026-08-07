---
name: php-upgrade-preflight-project
description: Durable architecture, constraints, and current-state context for PHP Upgrade Preflight development.
type: project
related:
  - ../DEVELOPMENT_PLAN.md
last_updated: 2026-08-07
---

# Project Memory

## Product

PHP Upgrade Preflight is a local, read-only analyzer for Composer-based PHP projects. Its semantic operation is:

```php
UpgradeAnalyzer::analyzeUpgrade(UpgradeRequest $request): UpgradeReport
```

Inputs are Composer files, requested package/PHP constraints, and optional source paths. Outputs are canonical JSON and derived Markdown. Composer remains authoritative for dependency resolution.

The initial use case is Laravel 7 to Laravel 8/9 and PHP 7.4 to PHP 8.0/8.1. The runtime packages target PHP `^8.0`; older project PHP versions are modeled with Composer `config.platform.php`. Laravel 7 projects still running PHP 7.x should eventually use an external CLI, PHAR, or container rather than forcing the main package to support PHP 7.

## Architecture

The development repository is a private Composer monorepo with path repositories:

- `php-upgrade-preflight/core`
- `php-upgrade-preflight/cli`
- `php-upgrade-preflight/laravel`

Core owns deterministic analysis. CLI owns generic command parsing and output. Laravel owns detection, rules, service-provider registration, and Artisan integration. Future framework adapters implement core contracts and must not duplicate the analysis pipeline.

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

The initial scaffold exists across all three packages. It includes typed DTO-style models, Composer JSON/lock readers, temporary workspace handling, baseline validation plus three full-target update scenarios, lock diffing, structured blocker parsing, parser-based source scanning, report writers, the generic CLI, Laravel detection/rules, and the Artisan command. `ScenarioSelector` owns deterministic scenario ordering and retains the first candidate for each normalized target and effective-option execution key. PHP-only requests skip the ineffective all-dependencies variant while retaining the distinct minimal-changes strategy. Requests combining package and PHP targets also run a `target-platform-only` probe. They run `staged-targets` only when the current PHP is known from the request or the project's Composer platform and differs from the target PHP; an equivalent staged probe is silently deduplicated, while missing current-PHP evidence records an uncertainty. The platform-only probe tests the requested PHP against current package constraints, while the staged probe tests requested packages on the known current PHP. These partial probes provide ordering diagnostics but do not determine combined-target feasibility, suppress full-target blockers, or supply the report's candidate lock. Baseline validation runs first against unchanged isolated Composer files and is excluded from target feasibility, lock selection, and blocker suppression. Scenario results retain the broad `solver`, `validation`, and `operational` failure classes while exposing exact outcomes for missing Composer, timeouts, invalid JSON, missing lockfiles, process failures, workspace failures, and cleanup failures. Project-input JSON and lockfile failures produce canonical reports without invoking Composer. Every Composer scenario, diagnostic, and version probe explicitly disables scripts and plugins. `ComposerBlockerParser` prefers `prohibits --tree` relations and falls back to solver output, producing blocker subject, requested constraint, blocking package, locked version, conflict, dependency path, resolution options, confidence, and evidence references for the specified blocker types. Equivalent root constraint conflicts from multiple scenarios are collapsed deterministically into the first blocker while retaining each scenario evidence reference. Successful and failed scenario workspaces are removed unless debug mode intentionally preserves them; cleanup failures remain structured operational outcomes and carry leaked paths for manual recovery. Lock package references are classified as direct from the corresponding manifest's `require` and `require-dev` entries, including each scenario's temporary manifest, and package changes expose direct/transitive classification plus evidence-safe numeric major-version jumps. Active framework integrations may optionally implement the generic `PackageFamilyClassifier` contract; core treats its labels as opaque, while the Laravel adapter owns the `laravel/*`, `illuminate/*`, and `symfony/*` mappings. Family labels are normalized, deduplicated, sorted, and included with each changed package. Core DTOs use private typed state and explicit typed accessors so they remain immutable on the PHP 8.0 runtime floor without relying on PHP 8.1 `readonly` properties; collection accessors return arrays by value. `UpgradeTargetSet` validates Composer targets, rejects conflicting duplicates, normalizes exact PHP platform versions, and supplies deterministic target ordering to requests, scenarios, Composer commands, and report serialization. `EvidenceLedger` owns deterministic, namespace-scoped evidence ID allocation. Report construction rejects duplicate evidence IDs, missing or empty finding references, and evidence that is not referenced by a blocker, source usage, framework finding, root constraint change, plan stage, or evidenced uncertainty. The core pipeline delegates target normalization, framework activation and rule execution, risk/effort estimation, report-section guidance, and final report construction to independently tested phases. `ReportSectionBuilder` compares requested targets with root requirements, produces evidence-backed constraint changes and staged actions, derives project-aware test guidance, and records runtime and test-command uncertainty. Canonical JSON reports carry immutable schema/tool metadata, follow the strict Draft 2020-12 v0.6 schema shipped under `packages/core/resources/schema/`, and are validated against that schema and a normalized v0.6 full-report snapshot. The published v0.5, v0.4, v0.3, and v0.2 schemas and snapshots remain preserved for earlier consumers. Markdown projects the same populated sections. Schema version `0.6` identifies the current consumer contract independently of the producing tool's semantic version.

Abandoned packages are detected from the selected candidate lock, or the baseline lock when no candidate exists, using Composer's boolean or package-or-URL `abandoned` metadata. These maintenance advisories carry E2 evidence, locked versions, direct/transitive context, and preserved alternative guidance; duplicate warning prose contributes solver evidence without replacing the structured metadata. They remain serialized in the blocker collection for the v0.6 contract but do not make a successful Composer resolution blocked or trigger solver-failure risk and rerun guidance.

The repository has a verified Docker toolchain using PHP 8.3 by default and Composer 2, with working PHP-version overrides from 8.0 through 8.5. The root `composer check` command validates every package manifest, runs PHPUnit, PHPStan, and PHP CS Fixer, and is the same quality gate used by the GitHub Actions runtime matrix. The deterministic analyzer isolation test runs all scenario paths through temporary workspaces and proves the original fixture remains byte-for-byte unchanged.

Source inspection uses streaming `nikic/php-parser` visitors after a complete namespace-resolution pass. It classifies imports, explicit fully qualified names, inheritance, interfaces, traits, attributes, static access, named function calls, and instantiations with file/line evidence. A contextual visitor also emits exact config keys, service-provider subclasses and registrations, middleware class references, console-command subclasses and registrations, and explicit test-double/mock targets. Repeated occurrences with the same file, symbol, and usage type are one source-impact finding; its first occurrence is the representative location and its evidence references retain every exact file/line occurrence. Dynamic names are not guessed. Malformed files produce linked uncertainties without stopping the scan. Recursive default scans prune dependency and common generated/cache directories, while a directly requested excluded directory remains scannable.

Laravel detection prefers the exact locked `laravel/framework` version and falls back to its root constraint. Modular Illuminate projects activate the Laravel adapter only when at least one `illuminate/*` package is a root requirement; matching lock entries refine a common detected version. Transitive Illuminate packages alone do not activate the adapter, which avoids false detection from the analyzer's own Illuminate dependencies. When rooted Illuminate components do not share one version or constraint, the adapter detects the project without claiming a single framework version. Laravel adapter source defaults retain `src/` alongside application directories so Illuminate packages do not lose the generic source scan when the adapter activates.

Known implementation gaps:

- Laravel rules do not yet cover Passport, Sanctum, Horizon, Telescope, PHPUnit, Mockery, Symfony coupling, old `illuminate/support`, Kernel middleware, or providers/aliases.
- CLI uses a custom parser and has not been runtime-tested. Laravel integration must be checked against every declared Illuminate major.

## Durable Decisions

- PolyForm Noncommercial 1.0.0 for free noncommercial use; commercial use requires a separate paid license from Valentin Nikolaev.
- Product name: PHP Upgrade Preflight; repository: `php-upgrade-preflight`.
- Command names: generic `upgrade-intel analyze`; Laravel `upgrade:analyze`.
- JSON is canonical and Markdown is a projection of the same report.
- Reports may be incomplete but must expose uncertainty rather than unsupported certainty.
- Analyzed projects are immutable inputs. Temporary Composer files are disposable analysis artifacts.
- Effort is a range with assumptions and confidence, never a precise promise.
- Testing uses four layers: offline unit tests, deterministic Composer integration tests backed by local path repositories, curated Laravel end-to-end fixtures, and opt-in networked smoke tests. Public sample projects must be pinned to commit SHAs and are release checks, not canonical test fixtures.
- The default development interpreter is the Compose `php` service on PHP 8.3. The root Composer manifest pins dependency resolution to PHP 8.0.30, while CI will execute the supported runtime matrix including PHP 8.4 and newer supported releases. PHP 8.3 avoids upstream deprecation output from PHP-8.0-compatible Laravel dependencies during ordinary CLI development. The container is a CLI toolchain only; target project PHP versions remain simulated by analysis scenarios.

## Repository Notes

At bootstrap, `.gitignore` was already staged as an empty file and `.idea/` was untracked. These were not part of the implementation and must not be removed or overwritten without user direction.

The original architecture source is:

`I:/Development/Git/ValentinNikolaev/laravel-package-intelligence/ARCHITECTURE_PROMPT.md`

Use `.claude/DEVELOPMENT_PLAN.md` for active sequencing. This file is durable context, not a progress diary.
