# Laravel Adapter Internals

This page follows a Laravel request through `packages/laravel`. It shows where detection, guidance, source inspection, and staged solving live, so you can check or change one behavior without guessing which class owns it.

## Architecture

`LaravelFrameworkIntegration` is a thin facade. It implements all current adapter ports and delegates work:

| Responsibility | Implementation |
|---|---|
| Framework identity | `LaravelFrameworkIntegration::name()` returns `laravel` |
| Detection | `LaravelFrameworkDetector` |
| Rules | `LaravelRuleFactory` backed by `LaravelRuleCatalog` |
| Transition guidance | `LaravelTransitionAssessor` |
| Staged targets | `LaravelStagePlanner` |
| Package families | `LaravelPackageFamilyClassifier` |
| Framework-shaped PHP inspection | `LaravelSourceUsageVisitor` |
| Artisan registration | `UpgradePreflightServiceProvider` and `AnalyzeUpgradeCommand` |

Each collaborator answers a different question. For example, changing detection alone does not change the catalog's rule definitions or the stage planner's requirements.

## Two registration paths

The standalone CLI discovers the adapter through `packages/laravel/composer.json`:

```json
{
  "extra": {
    "php-upgrade-preflight": {
      "framework-adapters": [
        "PhpUpgradePreflight\\Laravel\\LaravelFrameworkIntegration"
      ]
    }
  }
}
```

Laravel package discovery separately registers `UpgradePreflightServiceProvider`. Its `register()` binds one singleton `UpgradeAnalyzer` as `DefaultUpgradeAnalyzer([new LaravelFrameworkIntegration()])`. Its `boot()` registers `AnalyzeUpgradeCommand` only when the application is running in console mode.

Both commands reach the same Core analyzer and Laravel adapter through different registration paths:

```bash
vendor/bin/upgrade-intel analyze --path=/work/app --framework=laravel ...
php artisan upgrade:analyze ...
```

Integration tests verify canonical entry-point parity.

## Detection

`LaravelFrameworkDetector` reads Composer metadata only:

1. A root or locked `laravel/framework` means detected. The locked version wins over the root constraint when both exist.
2. Otherwise, any root `illuminate/*` requirement means detected.
3. For an Illuminate-only project, a version is returned only when all relevant locked versions or root constraints agree.
4. No Laravel-family root requirements means not detected.

Example outcomes:

| Project metadata | Detection result |
|---|---|
| root `laravel/framework:^10.0`, lock `v10.48.0` | detected, version `v10.48.0` |
| root `illuminate/support:^11.0` only | detected, version `^11.0` |
| `illuminate/support:^11.0` and `illuminate/console:^12.0` | detected, version unknown |
| only `symfony/console` | not detected |

Detection reads metadata. It never boots the application or tests its runtime behavior.

## Default source scope

When the user supplies no source paths, the adapter contributes:

```text
src, app, bootstrap, config, database, routes, tests
```

The analyzer scans a source snapshot. It does not execute the application or apply source changes between staged hops.

## Catalog and rule factory

`LaravelRuleCatalog::v0_2()` remains the catalog constructor in the v0.3 release line. Its catalog version is `0.2`, and it contains targets, transitions, rules, and skeleton patterns.

The modeled major range is Laravel 7 through 13. Target metadata exists for majors 8–13, including documented PHP and Composer constraints. Transition definitions are:

- adjacent 7→8, 8→9, 9→10, 10→11, 11→12, and 12→13.
- retained direct 7→9 guidance.

`LaravelRuleFactory` yields executable rules in catalog order. It maps three definition families:

- package constraint rules.
- package advisory rules.
- built-in rules such as framework/PHP/Symfony constraints, Illuminate support, skeleton checks, Composer version, cURL extension, and high-signal source checks.

An unknown definition or built-in kind throws during construction. If an individual rule fails during analysis, Core records the uncertainty and continues with other rules.

## Three independent conclusions

A Laravel report contains separate answers:

1. **Direct resolution** — can Composer solve the final requested target?
2. **Framework guidance** — does the catalog document the requested transition path?
3. **Staged resolution** — can the analyzer execute a sequence of adjacent Composer states?

Read each answer on its own terms. Composer may fail to solve the final target even when the catalog covers the transition. Staging may be skipped for lack of an exact PHP value. A successful solve still leaves source changes and runtime tests open.

## Transition assessment

`LaravelTransitionAssessor` derives source and target majors, then uses catalog transitions.

- Same-major requests, downgrades, ambiguous/unknown endpoints, and majors outside 7–13 are unsupported.
- The direct 7→9 definition is retained.
- Other multi-major paths compose adjacent definitions.
- A complete adjacent chain is supported.
- A covered prefix followed by a missing definition is partially supported and stops at the gap.
- A missing first hop is unsupported.

Rules that implement `HopAwareCompatibilityRule` receive each supported hop, so findings can reference exact applicability rather than leaking across a gap.

## Stage planning

`LaravelStagePlanner` needs more than catalog coverage. It requires a project rooted on `laravel/framework` and exactly one requested Laravel framework target. Illuminate-only projects and mixed Laravel-family target sets get an unavailable plan with a reason.

For a valid ascending path it creates one stage per adjacent hop. A Laravel 10→13 request becomes:

```text
laravel-10-to-11  target laravel/framework:^11.0
laravel-11-to-12  target laravel/framework:^12.0
laravel-12-to-13  target laravel/framework:^13.0
```

For each hop, the planner tries the exact target PHP first, then the exact current PHP. It uses a value only if it satisfies that hop's catalog constraint. A minimum constraint does not tell the planner which exact PHP version to simulate.

If the project directly requires a package outside a hop's compatible range, package rules may suggest candidate root constraints. The analyzer sorts those targets and evidence references by package and tries them only in temporary workspaces.

Plans are unavailable for missing target, ambiguous transition, unsupported direction/range, guidance gap, or unavailable exact PHP. Core then records staged execution as skipped/unknown rather than pretending Composer evaluated it.

## Package-family classification

Classification is case-insensitive and prefix-based:

| Package prefix | Family |
|---|---|
| `laravel/` | `laravel` |
| `illuminate/` | `illuminate` |
| `symfony/` | `symfony` |
| anything else | no Laravel-owned family |

These families organize package changes. They do not change Composer results.

## Laravel-shaped source usage

For each scanned file, `sourceUsageVisitors()` yields a fresh `LaravelSourceUsageVisitor`. It contributes these adapter-owned usage types:

- `service_provider`
- `facade_alias`
- `middleware_reference`
- `console_command`
- `config_reference`
- `test_double`
- `deprecated_queue_dispatch`
- `deprecated_asset_helper`

Examples include provider and alias arrays in `config/app.php`, middleware and command registration, configuration keys, Laravel facade test doubles, legacy `dispatchNow` calls, and calls resolved to the global `elixir` function. The visitor records the symbol, usage type, and exact line. An unresolved namespaced function fallback still needs manual review. Laravel skeleton and high-signal rules use these observations. Core does not assign the Laravel-specific usage types a generic meaning.

Laravel's source collector also recognizes selected PHPUnit, Mockery, and Prophecy `test_double` calls. Core does not assign those calls a generic meaning.

## Adding or changing a Laravel rule

1. Decide whether the behavior is data (`Catalog/*`) or executable logic (`Rules/*`).
2. Add/update the catalog definition with exact applicability and source references.
3. If introducing a definition subtype or built-in kind, add its explicit factory mapping.
4. Add focused unit coverage for positive and negative cases.
5. Add fixture coverage when canonical findings, evidence, transitions, or staged output change.
6. Review JSON and Markdown snapshot pairs and target immutability.
7. Run at least `composer test:laravel`, `composer test:fixtures`, `composer analyse`, and `composer lint`. Finish with `composer check`.
8. Update affected Wiki pages, docs, changelog, and schema/migration documentation when applicable.

Do not broaden a rule to unsupported hops, convert a minimum into an exact PHP value, or make a catalog correction by rewriting archived schema/contract fixtures.

## Tests to know

- `LaravelFrameworkIntegrationTest`: detection, guidance, and integration behavior.
- `LaravelRuleFactoryTest` and `LaravelCompatibilityRulesTest`: catalog-to-rule construction and findings.
- `LaravelStagePlannerTest`: stage targets, exact PHP evidence, gaps, and remediation.
- `LaravelSourceUsageVisitorTest`: Laravel vocabulary extraction.
- `LaravelFixtureAnalysisTest`: canonical JSON/Markdown fixtures.
- `LaravelTransitionCommandParityTest` and `CommandEntryPointParityTest`: CLI/Artisan parity.
- `WorstCaseStagedBudgetTest` and `RepresentativeCorpusBudgetTest`: deterministic cost bounds.

## Release documentation policy

Before creating a release tag, update affected Laravel Wiki pages and examples. `verify-release.php` checks the materialized Wiki trees and release evidence, while maintainers still have to compare behavioral claims with source. See [Release Wiki Strategy](https://github.com/ValentinNikolaev/php-upgrade-preflight/wiki/Release-Wiki-Strategy).

## Target metadata reference

The current catalog describes these target-major requirements:

| Laravel target | Modeled PHP constraint | Modeled Symfony constraint |
| --- | --- | --- |
| 8 | `^7.3|^8.0` | `^5.0` |
| 9 | `^8.0.2` | `^6.0` |
| 10 | `^8.1` | `^6.2` |
| 11 | `^8.2` | `^7.0.3` |
| 12 | `^8.2` | `^7.2.0` |
| 13 | `^8.3` | `^7.4.0|^8.0.0` |

These are the constraints the adapter checks. Composer decides whether the complete dependency graph resolves. The application's tests decide whether its behavior still works.

## Package-rule examples

The catalog is not limited to `laravel/framework`.

| Hop | Representative package guidance |
| --- | --- |
| 7→8 and 7→9 | Passport, Sanctum, Horizon, Telescope, PHPUnit, Mockery, Collision, Laravel UI, Testbench |
| 8→9 | Pusher, Spatie Ignition, Flysystem adapters |
| 9→10 | Doctrine DBAL, Passport, Sanctum, UI, Ignition, Collision, PHPUnit |
| 10→11 | Breeze, Cashier, Dusk, Jetstream, Octane, Passport, Sanctum, Scout, Spark, Telescope, Livewire, Inertia, PHPUnit |
| 11→12 | PHPUnit, Pest, Carbon, Collision |
| 12→13 | Boost, Tinker, PHPUnit, Pest, Collision, legacy helpers advisory |

Constraint rules compare package ranges. Advisories suggest actions such as replacing or removing a package, publishing migrations, or reviewing a change. Each definition has applicability and source references, so advice stays with the hops it describes.

The [completion coverage ledgers](https://github.com/ValentinNikolaev/php-upgrade-preflight/blob/main/docs/laravel-coverage/README.md) account for every heading in the pinned Laravel 8–13 guides and explain concrete manual checks. They supplement, rather than rewrite, the historical transition contract. Guide recommendations, framework-supported test ranges, and fresh skeleton defaults are distinct evidence.

Removed-symbol source rules match exact parser-derived identities and applicable usage types. Laravel 13 retains the deprecated CSRF aliases, so its request-forgery finding is medium-severity review advice. Neither that advice nor a low-confidence skeleton finding proves runtime incompatibility.

## Worked stage-planning example

Assume the project locks Laravel 10 and directly requires `laravel/framework`.

The request is:

```bash
vendor/bin/upgrade-intel analyze \
  --path=. \
  --target=laravel/framework:^13.0 \
  --from-php=8.2 \
  --target-php=8.3 \
  --framework=laravel
```

`LaravelSource` finds major 10 and `LaravelTarget` finds major 13. The planner checks all three adjacent transitions. It selects PHP `8.3` for each stage because the exact target PHP takes priority and satisfies each stage's constraint.

It constructs these exact temporary targets:

```text
laravel-10-to-11: laravel/framework:^11.0, php 8.3
laravel-11-to-12: laravel/framework:^12.0, php 8.3
laravel-12-to-13: laravel/framework:^13.0, php 8.3
```

The planner supplies targets. Core runs Composer against them.

If stage 11→12 cannot produce a selected candidate state, 12→13 is reported as skipped.

The original Laravel source remains the source snapshot used for stage assessments.

## Example of an unavailable plan

This request lacks exact PHP evidence:

```bash
vendor/bin/upgrade-intel analyze \
  --target=laravel/framework:^13.0 \
  --framework=laravel
```

The catalog gives a PHP range for Laravel 13, but the analyzer needs one exact version to simulate.

If neither target PHP nor current PHP supplies a safe exact value, the plan is unavailable with `analysis_php_unavailable`.

Direct scenarios and framework guidance can still return their own results.

## Rule execution path

For one active Laravel analysis:

```text
LaravelRuleCatalog
  -> LaravelRuleFactory
  -> CompatibilityRule instances in catalog order
  -> FrameworkRuleEngine
  -> zero or one finding per applicable rule evaluation
  -> EvidenceLedger references
  -> UpgradeReport
```

`PackageRuleDefinition` becomes `PackageVersionRule`.

`PackageAdvisoryDefinition` becomes `TargetedPackageAdvisoryRule`.

`BuiltinRuleDefinition` dispatches to one of the explicit built-in implementations.

An unmapped definition is a programming error.

A throwing third-party-style rule is contained by Core as uncertainty so other rules can continue.

## Source visitor examples

The visitor recognizes Laravel-shaped syntax after PHP Parser name resolution.

Examples include:

- a service provider entry in `config/app.php`.
- a facade alias entry.
- middleware registration in an HTTP kernel.
- console command registration.
- selected configuration references.
- Laravel facade testing helpers.
- legacy queue dispatch calls.

A usage contains project-relative file, exact line, symbol, and adapter-owned usage type.

The visitor records usage. Skeleton and high-signal rules decide whether it matters for a modeled transition. Core then correlates package ownership to decide whether a package change makes the usage actionable.

## Debugging guide

If Laravel is not active, inspect root requirements and lock metadata first.

If it is detected with unknown version, check whether Illuminate components disagree.

If guidance is unsupported, compare resolved source/target majors with catalog transitions.

If staging is skipped, inspect the explicit stage-plan reason and PHP provenance.

If a package finding is missing, confirm the rule applicability, root/locked package data, and compatible constraint.

If a source finding is missing, confirm the path was selected, PHP parsed, visitor emitted the expected usage type, and the hop is supported.

If CLI and Artisan differ, run the entry-point parity tests and compare normalized requests before changing adapter rules.

## Manager interpretation

Catalog coverage names the versions and hops the adapter knows how to assess. Stage results show intermediate dependency attempts and possible changes. They do not perform the upgrade-guide work. Source findings point to code worth reviewing, without clearing code the scanner did not flag.

For the larger package boundary, see [[Laravel Package Internals|Home]].
