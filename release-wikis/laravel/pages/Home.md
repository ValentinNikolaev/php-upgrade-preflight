# Laravel Package Internals

`php-upgrade-preflight/laravel` adds Laravel-specific evidence and two entry paths: automatic adapter discovery for `upgrade-intel`, and the `php artisan upgrade:analyze` command.

## Integration facade

`LaravelFrameworkIntegration` implements five Core capability interfaces:

| Interface | Collaborator | Result |
| --- | --- | --- |
| `FrameworkIntegration` | `LaravelFrameworkDetector`, `LaravelRuleFactory` | Detection, rules, and default source paths |
| `FrameworkTransitionProvider` | `LaravelTransitionAssessor` | Supported/partial/unsupported hop guidance |
| `FrameworkStageTargetProvider` | `LaravelStagePlanner` | Exact adjacent Composer stage targets |
| `PackageFamilyClassifier` | `LaravelPackageFamilyClassifier` | `laravel`, `illuminate`, or `symfony` family labels |
| `SourceUsageVisitorProvider` | `LaravelSourceUsageVisitor` | Laravel-aware AST observations |

The facade owns no compatibility logic. This separation lets detection, catalog rules, source collection, and stage planning change independently.

## How Laravel is detected

`LaravelFrameworkDetector` reads Composer metadata; it never boots the Laravel application.

| Project evidence | Detected? | Version evidence |
| --- | --- | --- |
| Locked `laravel/framework` | Yes | Locked version |
| Root `laravel/framework` but no locked package | Yes | Root constraint |
| Root `illuminate/*` components with agreeing locked versions or constraints | Yes | The one shared value |
| Root `illuminate/*` components with different values | Yes | Unknown version |
| No Laravel framework or Illuminate root requirements | No | None |

For example, a component project with `illuminate/console` and `illuminate/support` is still Laravel-family, but conflicting component versions prevent a confident framework version.

## Default source paths

When Laravel is active and the request does not replace source selection, the adapter proposes:

```text
src
app
bootstrap
config
database
routes
tests
```

Only existing project-contained paths are scanned. Extra `--source` values are handled by the request and Core scanner.

## Catalog coverage

`LaravelRuleCatalog::v0_2()` is the maintained knowledge source. Its target range is Laravel 7 through 13.

| Target major | Minimum modeled PHP constraint | Modeled Symfony constraint |
| --- | --- | --- |
| 8 | `^7.3\|^8.0` | `^5.0` |
| 9 | `^8.0.2` | `^6.0` |
| 10 | `^8.1` | `^6.2` |
| 11 | `^8.2` | `^7.0.3` |
| 12 | `^8.2` | `^7.2.0` |
| 13 | `^8.3` | `^7.4.0\|^8.0.0` |

The catalog includes adjacent rule packs for 7→8, 8→9, 9→10, 10→11, 11→12, and 12→13, plus a direct 7→9 guidance definition. Staged solving still requires a contiguous adjacent chain.

These values describe what the adapter checks. They do not supersede Composer's solver and do not promise runtime compatibility.

## Rule definition types

`LaravelRuleFactory` maps catalog definitions to executable rules in catalog order.

| Definition | Executable rule | Example question |
| --- | --- | --- |
| `PackageRuleDefinition` | `PackageVersionRule` | Does the locked or required Passport version intersect the compatible range for this hop? |
| `PackageAdvisoryDefinition` | `TargetedPackageAdvisoryRule` | Should an obsolete package be removed or replaced? |
| Framework constraint built-in | `LaravelFrameworkConstraintRule` | Does the project framework state match the requested transition? |
| PHP constraint built-in | `LaravelPhpConstraintRule` | Does the selected target PHP satisfy the target Laravel major? |
| Symfony constraint built-in | `SymfonyComponentConstraintRule` | Do relevant Symfony components fit the target family? |
| Illuminate support built-in | `OldIlluminateSupportRule` | Are old Illuminate constraints incompatible? |
| Skeleton built-in | `LaravelSkeletonRule` | Does project structure contain a modeled legacy skeleton pattern? |
| Composer version built-in | `LaravelComposerVersionRule` | Does the Composer version meet a transition requirement? |
| cURL extension built-in | `LaravelCurlExtensionRule` | Is cURL availability known for the modeled hop? |
| High-signal source built-in | `LaravelHighSignalSourceRule` | Is a transition-relevant Laravel symbol present in source? |

An unknown definition subtype or built-in kind fails loudly during construction; it is not silently ignored.

## Examples of modeled package guidance

The catalog includes far more than `laravel/framework`. Examples include:

| Transition | Selected examples |
| --- | --- |
| 7→8 / 7→9 | Passport, Sanctum, Horizon, Telescope, PHPUnit, Mockery, Collision, Laravel UI, Testbench, Ignition, CORS and proxy advisories |
| 8→9 | Pusher, Spatie Ignition, Flysystem S3/FTP/SFTP packages |
| 9→10 | Doctrine DBAL, Passport, Sanctum, UI, Ignition, Collision, PHPUnit |
| 10→11 | Breeze, Cashier, Dusk, Jetstream, Octane, Passport, Sanctum, Scout, Spark, Telescope, Livewire, Inertia, PHPUnit, migration advisories |
| 11→12 | PHPUnit, Pest, Carbon, Collision |
| 12→13 | Boost, Tinker, PHPUnit, Pest, Collision, legacy helpers advisory |

Each catalog entry carries applicability and source URLs. A package rule applies only when its transition and project evidence match.

## Transition guidance versus staged solving

These are separate outputs:

- `LaravelTransitionAssessor` explains which rule packs cover the requested transition.
- `LaravelStagePlanner` supplies exact adjacent package/PHP targets that Core can ask Composer to solve.

Guidance can be supported while a Composer stage is blocked. Conversely, Composer may solve a target whose adapter guidance is incomplete.

## Staged Laravel requirements

`LaravelStagePlanner` creates a plan only when all of these are true:

1. The request contains a Laravel-family target.
2. Source and target majors are unambiguous.
3. The project and request each root exactly one `laravel/framework` target for staging.
4. The transition is ascending and within majors 7 through 13.
5. Every adjacent hop has a supported target and transition definition.
6. Each stage can select an exact PHP value from `--target-php` first, then `--from-php`, that satisfies the target-major PHP constraint.

Example:

```bash
vendor/bin/upgrade-intel analyze \
  --path=. \
  --target=laravel/framework:^13.0 \
  --from-php=8.2 \
  --target-php=8.3 \
  --framework=laravel
```

For a detected Laravel 10 project, the planner can propose 10→11, 11→12, and 12→13. The final target PHP `8.3` is tested first for every stage; if it satisfies the stage's minimum, it becomes that stage's analysis PHP. Exact request evidence is required—the planner does not guess a PHP version from a broad constraint.

If planning cannot proceed, it returns an empty plan with a reason such as missing target, ambiguous transition, guidance gap, unsupported transition, or analysis PHP unavailable. That explicit skipped result is preferable to pretending staged analysis ran.

## Analyzer-only remediation targets

For a stage, the planner may derive root-package remediation candidates from catalog package rules. It adds them only when the package is a root requirement and the current locked version or constraint does not already match the compatible range.

Example concept:

```text
Stage target: laravel/framework:^11.0
Detected root package: laravel/passport at an incompatible range
Remediation attempt: also try laravel/passport:^12.0 in the temporary manifest
```

These are candidate constraints in analyzer-owned workspaces. They are not edits applied to the application.

## Package-family classification

The classifier is intentionally simple and case-insensitive:

| Prefix | Family |
| --- | --- |
| `laravel/` | `laravel` |
| `illuminate/` | `illuminate` |
| `symfony/` | `symfony` |
| Anything else | No Laravel-provided family |

Core can attach these families to package changes, making a large lock diff easier to group.

## Generic CLI discovery

The package Composer metadata advertises:

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

After installation beside the CLI package, `FrameworkIntegrationRegistry` can discover it. Explicit use is:

```bash
vendor/bin/upgrade-intel analyze \
  --target=laravel/framework:^12.0 \
  --target-php=8.3 \
  --framework=laravel
```

Without `--framework=laravel`, detection can activate the installed integration when Composer metadata identifies a Laravel-family project.

## Laravel service provider and Artisan command

Laravel package metadata also advertises `UpgradePreflightServiceProvider`. Its `register()` method binds `ArtisanAnalysisProgressReporter` and `UpgradeAnalyzer` as singletons; the analyzer contains one `LaravelFrameworkIntegration` and receives the reporter. Its `boot()` method registers `AnalyzeUpgradeCommand` only while the application is running in console mode.

```bash
php artisan upgrade:analyze \
  --target=laravel/framework:^12.0 \
  --target-php=8.3 \
  --format=json
```

The Artisan command defaults `--path` to the Laravel application base path and always requests the `laravel` integration. Its main options mirror the generic CLI, except it does not expose a repeatable `--framework` selector because the command is already Laravel-specific.

`AnalyzeUpgradeCommand` attaches `ArtisanAnalysisProgressReporter` to Symfony Console's error style only for the command run and detaches it afterward. The reporter renders Core phase/scenario events only when stderr is a TTY, catches its own failures, and never modifies canonical report stdout or analysis semantics.

## Class reference

| Class/group | Responsibility |
| --- | --- |
| `LaravelFrameworkIntegration` | Capability facade registered as the adapter |
| `LaravelFrameworkDetector` | Composer-metadata-only detection |
| `LaravelRequestTargets` | Shared definition of Laravel-family request targets |
| `LaravelTransitionAssessor` | Rule-pack coverage and hop evidence |
| `LaravelStagePlanner` | Adjacent exact targets and remediation candidates |
| `LaravelRuleFactory` | Catalog-definition dispatch to executable rules |
| `LaravelPackageFamilyClassifier` | Lock-diff family labels |
| `LaravelSourceUsageVisitor` | Framework-specific AST observations |
| `LaravelRuleCatalogValidator` | Catalog consistency checks |
| `UpgradePreflightServiceProvider` | Container binding and console registration |
| `Commands\AnalyzeUpgradeCommand` | Artisan request construction and report delivery |
| `Console\ArtisanAnalysisProgressReporter` | TTY-only stderr rendering of Core progress events |

## Related pages

- [Package Map](https://github.com/ValentinNikolaev/php-upgrade-preflight/wiki/Package-Map)
- [Core Analysis Pipeline](https://github.com/ValentinNikolaev/php-upgrade-preflight/wiki/Core-Analysis-Pipeline)
- [CLI Package Internals](https://github.com/ValentinNikolaev/php-upgrade-preflight/wiki/CLI-Package-Internals)
- [Test Adapters](https://github.com/ValentinNikolaev/php-upgrade-preflight/wiki/Test-Adapters)
