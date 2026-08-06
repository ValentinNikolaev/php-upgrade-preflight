---
name: php-upgrade-preflight-project
description: Durable architecture, constraints, and current-state context for PHP Upgrade Preflight development.
type: project
related:
  - ../DEVELOPMENT_PLAN.md
last_updated: 2026-08-06
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

The initial scaffold exists across all three packages. It includes typed DTO-style models, Composer JSON/lock readers, temporary workspace handling, baseline validation plus three update scenarios, lock diffing, basic blocker grouping, parser-based source scanning, report writers, the generic CLI, Laravel detection/rules, and the Artisan command. Baseline validation runs first against unchanged isolated Composer files and is excluded from target feasibility, lock selection, and blocker suppression. A completed baseline rejection has the structured `validation` failure type, while workspace, process, and Composer-executable failures remain `operational`. Core DTOs use private typed state and explicit typed accessors so they remain immutable on the PHP 8.0 runtime floor without relying on PHP 8.1 `readonly` properties; collection accessors return arrays by value. `UpgradeTargetSet` validates Composer targets, rejects conflicting duplicates, normalizes exact PHP platform versions, and supplies deterministic target ordering to requests, scenarios, Composer commands, and report serialization. `EvidenceLedger` owns deterministic, namespace-scoped evidence ID allocation. Report construction rejects duplicate evidence IDs, missing or empty finding references, and evidence that is not referenced by a blocker, source usage, framework finding, root constraint change, plan stage, or evidenced uncertainty. The core pipeline delegates target normalization, framework activation and rule execution, risk/effort estimation, report-section guidance, and final report construction to independently tested phases. `ReportSectionBuilder` compares requested targets with root requirements, produces evidence-backed constraint changes and staged actions, derives project-aware test guidance, and records runtime and test-command uncertainty. Canonical JSON reports carry immutable schema/tool metadata, follow the strict Draft 2020-12 v0.1 schema shipped under `packages/core/resources/schema/`, and are validated against that schema in addition to being protected by a normalized full-report snapshot. Markdown projects the same populated sections. Schema version `0.1` identifies the consumer contract independently of the producing tool's semantic version.

The repository has a verified Docker toolchain using PHP 8.3 by default and Composer 2, with working PHP-version overrides from 8.0 through 8.5. The root `composer check` command validates every package manifest, runs PHPUnit, PHPStan, and PHP CS Fixer, and is the same quality gate used by the GitHub Actions runtime matrix. The deterministic analyzer isolation test runs all scenario paths through temporary workspaces and proves the original fixture remains byte-for-byte unchanged.

Known implementation gaps:

- Blocker parsing covers only a few broad message patterns and may duplicate the same root cause across scenarios.
- Scenario coverage lacks platform-only, staged targets, and explicit `why-not`/`prohibits` diagnostics.
- Laravel rules do not yet cover Passport, Sanctum, Horizon, Telescope, PHPUnit, Mockery, Symfony coupling, old `illuminate/support`, Kernel middleware, or providers/aliases.
- CLI uses a custom parser and has not been runtime-tested. Laravel integration must be checked against every declared Illuminate major.

## Durable Decisions

- MIT license.
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
