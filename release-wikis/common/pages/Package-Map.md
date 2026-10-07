# Package Map

This repository contains five Composer packages. Three ship the analyzer and its entry points. Two exercise adapter compatibility in tests. The split tells you where to make a change.

## At a glance

| Directory | Composer package | Purpose | Intended consumer | Production package? |
| --- | --- | --- | --- | --- |
| `packages/core` | `php-upgrade-preflight/core` | Framework-neutral analysis, Composer scenarios, source scanning, evidence, risk, effort, and report writing | CLI packages, framework adapters, and PHP applications embedding the analyzer | Yes |
| `packages/cli` | `php-upgrade-preflight/cli` | The `upgrade-intel` executable, argument parsing, adapter discovery, and report delivery | Developers and CI systems | Yes |
| `packages/laravel` | `php-upgrade-preflight/laravel` | Laravel detection, transition catalog, compatibility rules, staged targets, source visitors, and Artisan integration | Laravel projects and the generic CLI | Yes |
| `packages/test-adapter` | `php-upgrade-preflight/test-adapter` | A complete third-party adapter fixture used to exercise current extension interfaces | Repository tests and adapter authors reading a compact example | No. Its Composer description says test-only |
| `packages/legacy-test-adapter` | `php-upgrade-preflight/legacy-test-adapter` | An old-style adapter fixture proving that pre-v0.3 adapter capabilities still load | Repository compatibility tests | No. Its Composer description says test-only |

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

Core defines adapter interfaces under `Core\Framework` and has no Laravel dependency. An adapter implements the capabilities it supports, so the same Composer and PHP analysis works for other projects.

## What each package owns

### Core

Core runs analysis and builds the report. Its main contract is `UpgradeAnalyzer`, implemented by `DefaultUpgradeAnalyzer`. Core has no user-facing executable.

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

CLI turns command-line values into a request, finds installed adapters, calls Core, and renders the report. Composer solving and framework rules live elsewhere.

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

Laravel is both an adapter for the generic CLI and a Laravel service-provider package. Composer advertises `LaravelFrameworkIntegration` through `extra.php-upgrade-preflight.framework-adapters`. Laravel package discovery advertises `UpgradePreflightServiceProvider` separately.

The adapter has guidance for Laravel majors 7 through 13: target PHP and Symfony constraints, transitions, package rules and advisories, source rules, and skeleton patterns. That coverage tells you what the adapter checks. Only the project's own tests can establish whether the upgraded application works.

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

- Put generic behavior in Core so it can serve any framework.
- Put command syntax and adapter discovery in CLI. Keep analysis decisions out of `AnalyzeCommand`.
- Keep maintained Laravel knowledge in its catalog and rule implementations.
- Use the test adapters as fixtures, not as framework packages for users.
- Treat JSON as the report contract. Markdown renders the same `UpgradeReport` for readers.

## Example: following one request across packages

For this command:

```bash
vendor/bin/upgrade-intel analyze \
  --path=./shop \
  --target=laravel/framework:^13.0 \
  --target-php=8.3 \
  --framework=laravel \
  --format=json
```

1. CLI parses and validates the options.
2. CLI confirms that an installed adapter named `laravel` is available.
3. Core loads `composer.json` and `composer.lock` from `./shop`.
4. Laravel detects the project, supplies rules, source visitors, package-family labels, transition guidance, and adjacent stage targets.
5. Core runs isolated Composer scenarios and scans the original source tree.
6. Core assembles one `UpgradeReport`.
7. CLI selects the JSON writer and prints the report on stdout.

The analyzer leaves `./shop` alone. It makes candidate manifest edits and lock files in its own temporary workspaces, then reports what those attempts showed.
