# Laravel Adapter Internals

This is a code-oriented map of `packages/laravel`, verified against the repository on **2026-08-19**. It helps a Junior developer find the right class and helps a technical manager understand which claims are independently tested.

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

This split is intentional: changing detection should not silently change rule construction or stage planning.

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

Therefore the standalone command and Artisan use the same Core analyzer and adapter behavior, even though registration differs:

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

Detection does not boot Laravel and does not prove runtime compatibility.

## Default source scope

When the user supplies no source paths, the adapter contributes:

```text
src, app, bootstrap, config, database, routes, tests
```

The analyzer scans a source snapshot. It does not execute the application or apply source changes between staged hops.

## Catalog and rule factory

`LaravelRuleCatalog::v0_2()` is the current catalog constructor despite the product's v0.3 release line. The catalog reports its own version as `0.2` and contains target, transition, rule, and skeleton-pattern definitions.

The modeled major range is Laravel 7 through 13. Target metadata exists for majors 8–13, including documented PHP and Composer constraints. Transition definitions are:

- adjacent 7→8, 8→9, 9→10, 10→11, 11→12, and 12→13;
- retained direct 7→9 guidance.

`LaravelRuleFactory` yields executable rules in catalog order. It maps three definition families:

- package constraint rules;
- package advisory rules;
- built-in rules such as framework/PHP/Symfony constraints, Illuminate support, skeleton checks, Composer version, cURL extension, and high-signal source checks.

An unknown definition or built-in kind throws instead of being silently skipped. Core contains individual rule failures as evidence-backed uncertainty and continues other rules.

## Three independent conclusions

A Laravel report contains separate answers:

1. **Direct resolution** — can Composer solve the final requested target?
2. **Framework guidance** — does the catalog document the requested transition path?
3. **Staged resolution** — can the analyzer execute a sequence of adjacent Composer states?

Do not infer one from another. A direct solve may fail while guidance exists; a stage plan may be skipped because exact PHP evidence is absent; Composer success never proves source or runtime compatibility.

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

`LaravelStagePlanner` is stricter than guidance. It supports only a project rooted on `laravel/framework` with exactly one requested Laravel framework constraint. Illuminate-only projects and mixed Laravel-family target sets receive an unavailable plan.

For a valid ascending path it creates one stage per adjacent hop. A Laravel 10→13 request becomes:

```text
laravel-10-to-11  target laravel/framework:^11.0
laravel-11-to-12  target laravel/framework:^12.0
laravel-12-to-13  target laravel/framework:^13.0
```

The analysis PHP is selected from exact request evidence: exact final target PHP first, then exact current PHP, and only when the value satisfies the catalog minimum for that hop. A minimum constraint alone is not converted into an invented exact version.

Package-rule metadata may add analyzer-only root-remediation candidates when the project directly requires a package that does not already match the hop's compatible constraint. Targets and evidence references are package-sorted. These candidates affect temporary analysis workspaces only.

Plans are unavailable for missing target, ambiguous transition, unsupported direction/range, guidance gap, or unavailable exact PHP. Core then records staged execution as skipped/unknown rather than pretending Composer evaluated it.

## Package-family classification

Classification is case-insensitive and prefix-based:

| Package prefix | Family |
|---|---|
| `laravel/` | `laravel` |
| `illuminate/` | `illuminate` |
| `symfony/` | `symfony` |
| anything else | no Laravel-owned family |

These families organize package changes; they do not change Composer results.

## Laravel-shaped source usage

For each scanned file, `sourceUsageVisitors()` yields a fresh `LaravelSourceUsageVisitor`. It contributes these adapter-owned usage types:

- `service_provider`
- `facade_alias`
- `middleware_reference`
- `console_command`
- `config_reference`
- `test_double`
- `deprecated_queue_dispatch`

Examples include provider and alias arrays in `config/app.php`, middleware and command registration, configuration keys, Laravel facade test doubles, and legacy `dispatchNow` calls. The visitor emits symbol, usage type, and exact line. Laravel skeleton and high-signal rules consume this vocabulary; Core does not give it generic meaning.

There is a documented seam: generic PHPUnit, Mockery, and Prophecy `test_double` detection is intentionally limited to Laravel's active source collector rather than Core.

## Adding or changing a Laravel rule

1. Decide whether the behavior is data (`Catalog/*`) or executable logic (`Rules/*`).
2. Add/update the catalog definition with exact applicability and source references.
3. If introducing a definition subtype or built-in kind, add its explicit factory mapping.
4. Add focused unit coverage for positive and negative cases.
5. Add fixture coverage when canonical findings, evidence, transitions, or staged output change.
6. Review JSON and Markdown snapshot pairs and target immutability.
7. Run at least `composer test:laravel`, `composer test:fixtures`, `composer analyse`, and `composer lint`; finish with `composer check`.
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

Before any release tag is created, all affected Laravel Wiki documentation and examples must be updated. This applies equally to human maintainers, Codex, Claude, and other agents. On 2026-08-19 the policy is mandatory but review-enforced; the release verifier does not automatically compare Wiki content with code.

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

These are adapter checks backed by catalog sources.

Composer still decides whether the complete dependency graph can resolve.

The application test suite still decides whether project behavior remains correct.

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

Package constraint rules check compatible ranges.

Package advisory rules can recommend replace, remove, publish migrations, or review actions.

Applicability prevents guidance from leaking into unrelated hops.

Every catalog definition includes maintained source references.

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

`LaravelSource` resolves source major 10.

`LaravelTarget` resolves target major 13.

`LaravelStagePlanner` verifies all three adjacent transition definitions.

It selects exact PHP `8.3` because final target PHP has priority and satisfies each target-stage requirement.

It constructs these exact temporary targets:

```text
laravel-10-to-11: laravel/framework:^11.0, php 8.3
laravel-11-to-12: laravel/framework:^12.0, php 8.3
laravel-12-to-13: laravel/framework:^13.0, php 8.3
```

Core executes the plan.

The planner itself does not run Composer.

If stage 11→12 cannot produce a selected candidate state, 12→13 is reported as skipped.

The original Laravel source remains the source snapshot used for stage assessments.

## Example of an unavailable plan

This request lacks exact PHP evidence:

```bash
vendor/bin/upgrade-intel analyze \
  --target=laravel/framework:^13.0 \
  --framework=laravel
```

The catalog knows Laravel 13 requires a PHP range.

That range does not authorize the adapter to invent one exact simulated version.

If neither target PHP nor current PHP supplies a safe exact value, the plan is unavailable with `analysis_php_unavailable`.

Direct scenarios and framework guidance can still produce useful independent results.

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

- a service provider entry in `config/app.php`;
- a facade alias entry;
- middleware registration in an HTTP kernel;
- console command registration;
- selected configuration references;
- Laravel facade testing helpers;
- legacy queue dispatch calls.

A usage contains project-relative file, exact line, symbol, and adapter-owned usage type.

The visitor records inventory.

Skeleton and high-signal rules decide whether inventory is relevant to a modeled transition.

Core ownership correlation decides whether a package change makes a usage actionable.

## Debugging guide

If Laravel is not active, inspect root requirements and lock metadata first.

If it is detected with unknown version, check whether Illuminate components disagree.

If guidance is unsupported, compare resolved source/target majors with catalog transitions.

If staging is skipped, inspect the explicit stage-plan reason and PHP provenance.

If a package finding is missing, confirm the rule applicability, root/locked package data, and compatible constraint.

If a source finding is missing, confirm the path was selected, PHP parsed, visitor emitted the expected usage type, and the hop is supported.

If CLI and Artisan differ, run the entry-point parity tests and compare normalized requests before changing adapter rules.

## Manager interpretation

Laravel catalog coverage is a maintained product capability.

It should be reported as a version-and-hop matrix, not as universal Laravel compatibility.

Stage results provide intermediate dependency evidence and possible remediation scope.

They do not apply Laravel upgrade-guide steps.

Source findings identify review locations.

They do not prove that unreported code is safe.

For the larger package boundary, see [[Laravel Package Internals|Home]].
