# Package Map

PHP Upgrade Preflight is a monorepo containing five Composer packages. They are not five editions of the same tool: each package has a different responsibility and dependency boundary.

## At a glance

| Directory | Composer package | Purpose | Intended consumer | Production package? |
| --- | --- | --- | --- | --- |
| `packages/core` | `php-upgrade-preflight/core` | Framework-neutral analysis, Composer scenarios, source scanning, evidence, risk, effort, and report writing | CLI packages, framework adapters, and PHP applications embedding the analyzer | Yes |
| `packages/cli` | `php-upgrade-preflight/cli` | The `upgrade-intel` executable, argument parsing, adapter discovery, and report delivery | Developers and CI systems | Yes |
| `packages/laravel` | `php-upgrade-preflight/laravel` | Laravel detection, transition catalog, compatibility rules, staged targets, source visitors, and Artisan integration | Laravel projects and the generic CLI | Yes |
| `packages/test-adapter` | `php-upgrade-preflight/test-adapter` | A complete third-party adapter fixture used to exercise current extension interfaces | Repository tests and adapter authors reading a compact example | No; its Composer description says test-only |
| `packages/legacy-test-adapter` | `php-upgrade-preflight/legacy-test-adapter` | An old-style adapter fixture proving that pre-v0.3 adapter capabilities still load | Repository compatibility tests | No; its Composer description says test-only |

All five require PHP `^8.0`. Core uses Composer Semver, PHP Parser, Symfony Filesystem, and Symfony Process. CLI depends on Core and the Composer runtime API. Laravel depends on Core plus Illuminate Console/Support, PHP Parser, Composer Semver, and Symfony Console.

## Dependency direction

```text
upgrade-intel executable
        |
        v
php-upgrade-preflight/cli ---- discovers ----> installed adapter packages
        |                                      |       |       |
        v                                      v       v       v
php-upgrade-preflight/core <--------------- laravel  test   legacy-test
```

Core does not depend on Laravel. Instead, it defines small capability interfaces under `Core\Framework`. An adapter implements only the capabilities it can provide. This keeps generic Composer/PHP analysis usable for a non-Laravel project.

## What each package owns

### Core

Core owns the analysis pipeline, not a user-facing executable. Its main contract is `UpgradeAnalyzer`; the default implementation is `DefaultUpgradeAnalyzer`.

Important service groups:

| Namespace | Examples | Responsibility |
| --- | --- | --- |
| `Analysis` | `ScenarioSelector`, `BlockerGrouper`, `LockDiffBuilder`, `RiskAndEffortEstimator` | Turn evidence into upgrade conclusions |
| `Composer` | `ProjectStateBuilder`, `ComposerScenarioRunner`, `ScenarioWorkspacePreparer` | Read project metadata and run Composer in temporary workspaces |
| `Filesystem` | `TemporaryWorkspaceManager`, `NativeWorkspaceFilesystem` | Create and clean analyzer-owned workspaces |
| `Framework` | `FrameworkIntegration`, `FrameworkTransitionProvider`, `FrameworkStageTargetProvider` | Extension seams for adapters |
| `Source` | `SourceUsageScanner`, `AutoloadOwnershipIndexBuilder`, visitors | AST-based source inventory and package ownership |
| `Reporting` | `JsonReportWriter`, `MarkdownReportWriter`, `ReportFileWriter` | Render or safely write a completed report |
| `Support` | `SensitiveOutputRedactor`, `PathExposurePolicy`, `OutputExcerpt` | Prevent path and secret leakage and bound output |
| `Model` | `UpgradeRequest`, `ScenarioResult`, `Blocker`, `UpgradeReport` | Immutable values passed between services |

See [[Core Analysis Pipeline|Core-Analysis-Pipeline]] for the execution order and [[Core Service Reference|Core-Service-Reference]] for a class-by-class navigation guide.

### CLI

CLI is deliberately thin. It converts shell strings to model objects, discovers installed adapters, delegates to Core, and renders the returned report. It does not contain solver rules.

```bash
vendor/bin/upgrade-intel analyze \
  --path=/work/my-app \
  --target=laravel/framework:^12.0 \
  --target-php=8.3 \
  --framework=laravel \
  --format=json
```

See [[CLI Package Internals|CLI-Package-Internals]].

### Laravel

Laravel is both an adapter for the generic CLI and a Laravel service-provider package. Composer advertises `LaravelFrameworkIntegration` through `extra.php-upgrade-preflight.framework-adapters`; Laravel package discovery advertises `UpgradePreflightServiceProvider` separately.

The adapter currently models Laravel majors 7 through 13. Its catalog contains target PHP and Symfony constraints, adjacent transitions, package rules, package advisories, source rules, and skeleton patterns. Catalog coverage is guidance coverage, not proof that an application works on the target version.

See [[Laravel Package Internals|Laravel-Package-Internals]].

### Test adapters

The two fixture packages make capability evolution visible:

| Capability | `test-adapter` | `legacy-test-adapter` |
| --- | --- | --- |
| Framework detection | Yes | Yes |
| Compatibility rules | One source-aware rule | No rules |
| Default source paths | `modules` | `legacy-modules` |
| Transition guidance | Yes | Yes |
| Staged target provider | Yes | No |
| Package family classifier | Yes | No |

See [[Test Adapters|Test-Adapters]].

## Which entry point should I use?

| Need | Entry point |
| --- | --- |
| Run from any PHP project or CI | `vendor/bin/upgrade-intel analyze ...` from the CLI package |
| Run inside a Laravel application | `php artisan upgrade:analyze ...` from the Laravel package |
| Embed analysis in custom PHP code | Implement against `Core\Contracts\UpgradeAnalyzer` and construct an `UpgradeRequest` |
| Add support for another framework | Implement `FrameworkIntegration`, advertise it in Composer metadata, then add optional capability interfaces |
| Understand report fields | Start with [[Key Concepts|Key-Concepts]] and the report documentation |

## Boundary rules for contributors

- Generic behavior belongs in Core. Do not teach Core about a Laravel class or Laravel package name.
- Command-line syntax and adapter discovery belong in CLI. Do not put analysis decisions in `AnalyzeCommand`.
- Maintainer-sourced Laravel knowledge belongs in the Laravel catalog and rule implementations.
- Fixture behavior belongs only in the test adapter packages; users should not install those packages as real framework support.
- JSON is the canonical report contract. Markdown is a human-readable projection of the same `UpgradeReport`.

## Example: following one request across packages

For this command:

```bash
vendor/bin/upgrade-intel analyze \
  --path=./shop \
  --target=laravel/framework:^13.0 \
  --target-php=8.3 \
  --framework=laravel \
  --output=build/upgrade-report.json
```

1. CLI parses and validates the options.
2. CLI confirms that an installed adapter named `laravel` is available.
3. Core loads `composer.json` and `composer.lock` from `./shop`.
4. Laravel detects the project, supplies rules, source visitors, package-family labels, transition guidance, and adjacent stage targets.
5. Core runs isolated Composer scenarios and scans the original source tree.
6. Core assembles one `UpgradeReport`.
7. CLI selects the JSON writer and writes the destination after validating it.

The target project is analyzed, not upgraded. Composer manifest edits and candidate lock files exist only in analyzer-owned temporary workspaces.
