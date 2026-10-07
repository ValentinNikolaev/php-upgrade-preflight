# Core Analysis Pipeline

Follow a request through `php-upgrade-preflight/core` here. The sequence helps you find the service behind a surprising result and see where an adapter or other change belongs.

## The central contract

`UpgradeAnalyzer` takes an `UpgradeRequest` and returns an `UpgradeReport` through `analyzeUpgrade()`. Production uses `DefaultUpgradeAnalyzer`.

Conceptually:

```php
$request = new UpgradeRequest(
    projectPath: '/work/shop',
    targets: [new UpgradeTarget('laravel/framework', '^12.0')],
    fromPhp: '8.2',
    targetPhp: '8.3',
    sourcePaths: [],
    frameworks: ['laravel'],
    format: 'json',
    outputPath: null,
    debug: false
);

$report = $analyzer->analyzeUpgrade($request);
```

The example shows the object boundary. For a complete entry point, use the generic CLI or Laravel command.

## Pipeline overview

| Order | Service | Input | Output or decision |
| --- | --- | --- | --- |
| 1 | `ProjectStateBuilder` | Project directory | Parsed Composer manifest and lock state, or a contained input failure |
| 2 | `TargetPlatform::fromRequest` and `TargetNormalizer` | Request plus project metadata | Normalized target packages, PHP, extensions, and platform provenance |
| 3 | `FrameworkRuleEngine` | Installed adapters, project, request | Active integrations, source paths, and package-family classifiers |
| 4 | `ScenarioSelector` | Normalized targets and current PHP evidence | Ordered, deduplicated Composer scenarios |
| 5 | `ComposerScenarioRunner` | One scenario | Exit status, bounded output, diagnostics, Composer version, and optional candidate lock |
| 6 | `LockDiffBuilder` and `BlockerGrouper` | Scenario results | Candidate package changes or structured blockers |
| 7 | `StagedUpgradeOrchestrator` | Active stage providers | Adjacent stage attempts, selected candidate states, and blocker lifecycle |
| 8 | `SourceUsageScanner` | Original project source | AST-derived source inventory and uncertainties |
| 9 | Framework rules | Inventory plus transition guidance | Compatibility findings |
| 10 | Ownership and impact builders | Composer autoload metadata, inventory, lock changes | Actionable source impact |
| 11 | `RiskAndEffortEstimator` | Blockers, changes, findings, impact, stages | Aggregate risk and effort ranges |
| 12 | `ReportAssembler` | All accumulated evidence | Canonical `UpgradeReport` |

## Observational progress events

`DefaultUpgradeAnalyzer` can receive an `AnalysisProgressReporter`. It emits analysis start/completion/failure, phase start/completion, and Composer scenario start/completion events around the same pipeline. The stable phases are project loading, Composer feasibility, staged resolution, source scan, framework evaluation, and report assembly.

Progress stays outside the report. The analyzer catches reporter exceptions, so a broken progress display cannot cancel or change analysis. `NoOpAnalysisProgressReporter` is the default. CLI and Laravel render progress to terminal-attached stderr. Redirected and embedded runs stay quiet.

## Project input handling

`ProjectStateBuilder` reads `composer.json` and `composer.lock` through `JsonFileReader`. When input is missing or invalid, `DefaultUpgradeAnalyzer` can still produce a report with a `project-input` scenario. That failure is about the project files, not Composer's solver.

Examples of modeled outcomes include:

- `invalid_json` for an invalid Composer JSON document
- `lockfile_missing` when `composer.lock` is missing
- `workspace_failure` for other project-state loading failures.

Paths in failure messages pass through `PathExposurePolicy` before entering a shareable report.

## Scenario selection

For a package target, `ScenarioSelector` starts with these scenarios:

| Scenario | Purpose | Target feasibility? |
| --- | --- | --- |
| `baseline-validation` | Check whether the copied current manifest and lock are internally usable | No |
| `exact-target` | Try the requested target without `--with-all-dependencies` | Yes |
| `target-with-all-dependencies` | Allow dependency movement around target packages | Yes |
| `minimal-changes` | Combine dependency movement with Composer minimal-change behavior | Yes |

When both package targets and a target PHP are present, Core can also add:

- `target-platform-only`, a diagnostic partial probe that does not determine the final target
- `staged-targets`, which tries package targets against the current PHP. This requires `--from-php` or `config.platform.php`. Without either value, the report records an uncertainty.

Core removes scenarios that would run the same Composer operation. A PHP-only request therefore does not repeat an identical probe under several names.

## Temporary Composer workspaces

`ScenarioWorkspacePreparer` writes copied `composer.json` and `composer.lock` data into an analyzer-owned workspace. Target constraints and simulated platform values are applied to that copy.

Example transformation inside the temporary copy:

```json
{
  "require": {
    "laravel/framework": "^12.0"
  },
  "config": {
    "platform": {
      "php": "8.3"
    }
  }
}
```

If a targeted package originally lives only in `require-dev`, its target constraint is updated there. Relative Composer `path` and `artifact` repository URLs are made absolute relative to the analyzed project so the copied manifest retains their meaning.

The transformed manifest stays in the temporary workspace. The project copy does not change.

## Compatible and restricted Composer modes

The default compatible mode inherits the normal Composer environment, apart from non-interactive/no-audit settings. Restricted mode creates isolated Composer home/cache/XDG directories inside the temporary workspace, clears proxy and prompt variables, sets empty auth, and requests Composer network disablement.

Restricted mode gives Composer less access to host configuration and network state. It is still a process on your machine, so use the controls in [[Safety and Trust Boundaries|Safety-and-Trust-Boundaries]] for untrusted projects.

## Selecting candidate evidence

Several scenarios may succeed with candidate locks. `DefaultUpgradeAnalyzer` selects the candidate with the fewest package changes. Ties prefer the exact-target strategy, then minimal-changes, then with-all-dependencies, with original scenario order as the final tie-breaker.

That candidate supplies the direct `LockDiff`. If no successful target-feasibility scenario has a readable lock, the diff is empty. Core cannot infer candidate package versions from solver text.

## Blockers and diagnostics

`BlockerGrouper` turns solver evidence into structured blockers. It uses scenario output, Composer diagnostics, the relevant lock, requested constraints, and target platform.

The important distinction is:

```text
Composer process failed
        |
        +-- solver evidence is reliable --> structured blocker(s)
        |
        +-- execution/evidence failed ----> unknown + uncertainty
```

A timeout, missing Composer executable, or unreadable candidate lock leaves an evidence gap. None establishes a dependency conflict by itself.

## Staged analysis

Adapters may implement `FrameworkStageTargetProvider`. `StagedUpgradeOrchestrator` asks eligible active integrations for a plan and executes a contiguous chain.

For Laravel 10 to 13, an adapter may produce:

```text
laravel-10-to-11 -> laravel-11-to-12 -> laravel-12-to-13
```

Each successful stage supplies the candidate `ProjectState` used by the next stage. A failed or unknown stage stops later execution. The report marks later planned stages as skipped.

Staged execution uses bounded timeouts from `StagedAnalysisPolicy`. The blocker registry tracks whether blockers are detected, persist, resolve, or are superseded across attempts.

## Source scanning and ownership

`SourceUsageScanner` parses PHP through `nikic/php-parser`. Framework adapters can provide extra AST visitors through `SourceUsageVisitorProvider`.

`SourceScanLimits` bounds the deterministic file prefix, bytes per file, aggregate bytes, and retained unique usages. A reached limit is visible as `E3` evidence-backed uncertainty rather than silent truncation. The default limits are 10,000 files, 2 MiB per file, 64 MiB aggregate, and 10,000 usages.

The scan produces an inventory first. `AutoloadOwnershipIndexBuilder` then maps relevant declarations and symbols to Composer packages using autoload metadata. `SourceImpactBuilder` correlates usages with framework findings or actual candidate package changes.

Example:

```text
Inventory: app/Service.php uses Vendor\Package\Client
Ownership: Vendor\Package\Client belongs to vendor/package
Lock diff: vendor/package changes in the selected candidate
Result: actionable source-impact finding
```

If ownership or transition relevance is missing, the usage stays in inventory. It does not become a claim that code must change.

The original source snapshot is scanned even when staged Composer candidates exist. A staged candidate is dependency evidence, not a rewritten application tree.

The analyzer compares Composer and selected-source fingerprints around long-running phases. Input drift adds uncertainty because dependency and source sections may otherwise describe different project states. The detector does not lock the project or create a full source snapshot.

## Framework capability interfaces

| Interface | What an adapter supplies |
| --- | --- |
| `FrameworkIntegration` | Name, project detection, compatibility rules, default source paths |
| `FrameworkTransitionProvider` | Evidence-backed hop guidance |
| `FrameworkStageTargetProvider` | Exact adjacent stage targets and optional remediation candidates |
| `PackageFamilyClassifier` | Family labels for package changes, such as Laravel or Symfony |
| `SourceUsageVisitorProvider` | Framework-specific AST collectors |
| `HopAwareCompatibilityRule` | A rule that can evaluate one specific transition hop |

Core checks optional interfaces at runtime. An older adapter implementing only the original interfaces can still provide detection and guidance. It cannot provide newer capabilities such as staged targets.

## Reporting services

`ReportAssembler` creates a complete `UpgradeReport`. Rendering happens afterward:

| Service | Role |
| --- | --- |
| `JsonReportWriter` | Canonical machine-readable JSON |
| `MarkdownReportWriter` | Human-readable projection |
| `ReportWriterResolver` | Chooses a writer from the requested format |
| `ReportFileWriter` | Validates and writes an output destination |

Writers render the assembled report. They do not run analysis or decide what a result means.

## Where to make a change

| Desired change | Likely home |
| --- | --- |
| Add a new direct Composer scenario | `Analysis/ScenarioSelector` plus runner/report tests |
| Improve solver conflict parsing | `Analysis/ComposerBlockerParser` or `BlockerGrouper` |
| Change temporary manifest behavior | `Composer/ScenarioWorkspacePreparer` |
| Add a report field | Model, assembler, both writers, schema, snapshots, and Wiki |
| Add framework-specific knowledge | An adapter package, not Core |
| Improve redaction | `Support/SensitiveOutputRedactor` and path policy tests |
| Change staged attempt policy | Staged planner/executor services and budget tests |
| Add or rename a progress phase | `Progress/AnalysisPhase`, analyzer event tests, and both console renderers |

## Related pages

- [[Package Map|Package-Map]]
- [[Core Service Reference|Core-Service-Reference]]
- [[CLI Package Internals|CLI-Package-Internals]]
- [[Laravel Package Internals|Laravel-Package-Internals]]
- [[Key Concepts|Key-Concepts]]
