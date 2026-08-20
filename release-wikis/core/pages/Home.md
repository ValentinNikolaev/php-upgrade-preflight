# Core Package Guide

`php-upgrade-preflight/core` is the framework-neutral engine.

This guide is for developers changing or embedding Core.

It focuses on contracts and safe change paths rather than repeating every class description.

See [[Core Service Reference|Core-Service-Reference]] for the detailed class index.

See [[Architecture Overview|Architecture-Overview]] for system-wide flow.

## What Core promises

Core accepts an `UpgradeRequest` through `UpgradeAnalyzer`.

Core returns an `UpgradeReport`.

Core performs analysis in temporary workspaces.

Core treats Composer, project source, platform data, and adapter guidance as distinct evidence sources.

Core does not know about a specific framework unless an adapter supplies that knowledge.

## Installation boundary

The package Composer name is:

```text
php-upgrade-preflight/core
```

Its namespace root is:

```text
PhpUpgradePreflight\Core\
```

Its production dependencies are:

- PHP `^8.0`;
- `composer/semver` `^3.4`;
- `nikic/php-parser` `^4.19|^5.0`;
- Symfony Filesystem `^5.4|^6.0|^7.0|^8.0`;
- Symfony Process `^5.4|^6.0|^7.0|^8.0`.

Core has no dependency on the CLI or Laravel package.

## Public entry contract

The primary interface is `Core\Contracts\UpgradeAnalyzer`.

The production implementation is `Analysis\DefaultUpgradeAnalyzer`.

A simplified embedding example is:

```php
use PhpUpgradePreflight\Core\Analysis\DefaultUpgradeAnalyzer;
use PhpUpgradePreflight\Core\Model\UpgradeRequest;
use PhpUpgradePreflight\Core\Model\UpgradeTarget;

$request = new UpgradeRequest(
    projectPath: '/srv/project',
    targets: [new UpgradeTarget('vendor/package', '^2.0')],
    fromPhp: '8.1',
    targetPhp: '8.2',
    sourcePaths: [],
    frameworks: [],
    format: 'json',
    outputPath: null,
    debug: false
);

$report = (new DefaultUpgradeAnalyzer())->analyzeUpgrade($request);
```

The CLI packages remain the easier entry point for most users.

Embedders may inject an `AnalysisProgressReporter` into `DefaultUpgradeAnalyzer`. Core emits validated lifecycle events for analysis, phases, and Composer scenarios. `NoOpAnalysisProgressReporter` is the default. Reporter failures are contained, so progress remains observational and cannot change the returned report.

## Constructing a valid request

`UpgradeRequest` validates its inputs immediately.

The project path must exist.

At least one package target, target PHP, or target-platform profile must exist after normalization.

Package targets use `UpgradeTarget`.

```php
$target = new UpgradeTarget('symfony/console', '^7.0');
```

The package must be a resolvable Composer package name.

The constraint must parse through Composer Semver.

Use exact values for `fromPhp` and `targetPhp`.

```php
targetPhp: '8.3'
```

Do not use a broad PHP constraint where the model requires a simulated platform value.

## Target normalization

`UpgradeTargetSet` sorts and validates targets.

It merges `php:VERSION` package-style input with the dedicated target PHP value.

Equivalent normalized PHP values can coexist.

Contradictory PHP values fail early.

Contradictory duplicate package targets also fail.

Stable normalized targets support stable scenario selection and report output.

## Composer execution configuration

`ComposerExecutionConfiguration` owns:

- executable command;
- expected Composer version range;
- scenario timeout;
- diagnostic timeout;
- compatible or restricted mode;
- derived environment and network policy.

Defaults are:

| Setting | Default |
| --- | --- |
| Executable | `composer` |
| Expected version | `>=2.0.0 <3.0.0` |
| Scenario timeout | 300 seconds |
| Diagnostic timeout | 60 seconds |
| Mode | `compatible` |

Scenario timeout must be from 1 through 3600 seconds.

Diagnostic timeout must be from 1 through 900 seconds.

## Package metadata discovery

`ComposerPackageMetadataLookup` is the bounded read-only discovery service used by interactive clients before analysis. Its public `lookup()` operation requires the project path, package, constraint, `ComposerExecutionConfiguration`, and an explicit `PackageMetadataLookupMode`.

The result is a `PackageMetadataLookupResult` with one of four statuses: `invalid`, `found`, `not_found`, or `unverified`. A found result includes bounded discovered and constraint-matching version lists and their full counts. Local-cache misses, timeout, offline, malformed output, and process failures are unverified rather than guessed nonexistence. Project-repository mode may use configured repositories, credentials, and network; only its explicit package-not-found response becomes `not_found`.

Restricted execution currently returns `restricted_execution_unavailable` without starting a process. This preserves the restricted contract until lookup can create isolated Composer home/cache state. Lookup diagnostics are bounded and redacted, and the service never writes analysis results into the target project.

## Loading project state

Use `ProjectStateBuilder` rather than manually decoding Composer files.

Its `load()` method returns `ProjectStateLoadResult`.

Call `succeeded()` before using the state as reliable input.

`build()` is the fail-fast convenience path.

`ComposerJson` exposes normalized manifest facts.

`ComposerLock` exposes locked packages and root-development knowledge.

Both retain raw data needed for temporary scenario copies and report context.

## JSON failure types

Core distinguishes:

- `MissingJsonFileException`;
- `UnreadableJsonFileException`;
- `InvalidJsonException`.

Do not collapse them into a generic solver failure.

The project may be impossible to load before Composer executes.

## Building the target platform

Use `TargetPlatform::fromRequest($request, $project)`.

This combines explicit target data with project metadata.

`TargetPlatformProfile` can supply a deterministic platform description.

Profiles validate supported classes, package values, and duplicate JSON object keys.

The request checks that profile values do not contradict direct targets or extension assumptions.

## Selecting scenarios

`ScenarioSelector::select()` returns an ordered list.

Never assume a fixed count.

The selector deduplicates execution-equivalent candidates.

A scenario contains:

- name;
- target set;
- with-all-dependencies flag;
- minimal-changes flag;
- baseline flag;
- target-feasibility flag.

The last flag is critical.

A diagnostic partial probe must not determine final resolution status.

## Running a scenario

`ComposerScenarioRunner::run()` accepts:

- current `ProjectState`;
- `UpgradeRequest`;
- one `Scenario`;
- `TargetPlatform`.

Before a full analysis, `DefaultUpgradeAnalyzer` resets runner caches.

The runner may probe Composer version and platform packages.

It creates an isolated workspace.

It seeds copied Composer files.

It applies temporary target and platform changes.

It executes Composer through Symfony Process.

It reads candidate lock evidence when available.

It cleans the workspace unless debug retention applies.

## Workspace preparation

`ScenarioWorkspacePreparer` is the only service that applies scenario changes to Composer data.

It keeps a package in `require-dev` when that package exists only there.

Otherwise it writes the target into `require`.

It adds simulated platform values to `config.platform` in the copy.

It preserves existing case variants for platform-package keys.

It makes relative `path` and `artifact` repository URLs absolute.

This retains their meaning from the temporary directory.

## Scenario result interpretation

Check `ScenarioResult` fields rather than exit code alone.

Important dimensions include:

- `succeeded()`;
- failure type;
- outcome;
- candidate lock;
- diagnostics;
- Composer version;
- duration;
- evidence references.

`ScenarioOutcomeClassifier` separates solver failure from operational outcomes.

A timeout or missing executable is not a dependency conflict.

## Lock changes

`LockDiffBuilder` compares two `ComposerLock` values.

It returns `LockDiff` containing `PackageChange` values.

Changes can include additions, removals, upgrades, and downgrades.

Adapter package-family classifiers can annotate changes.

No readable candidate lock means no honest candidate diff.

Do not infer an installed version from a requested constraint.

## Blockers

`BlockerGrouper` consumes scenario results and an evidence ledger.

It uses `ComposerBlockerParser` for transcript relations.

It also has access to lock data, requested constraints, and target platform.

A `Blocker` can include:

- type;
- subject;
- blocking package;
- requested and blocking constraints;
- dependency path;
- attribution;
- confidence;
- scenario references;
- evidence references.

Preserve this structure when adding new blocker knowledge.

Do not hide it in a longer prose message.

## Framework integrations

Every adapter implements `FrameworkIntegration`.

That base contract supplies:

- stable name;
- detection;
- compatibility rules;
- default source paths.

Optional interfaces add capabilities.

| Capability | Interface |
| --- | --- |
| Transition guidance | `FrameworkTransitionProvider` |
| Staged targets | `FrameworkStageTargetProvider` |
| Package families | `PackageFamilyClassifier` |
| Extra AST visitors | `SourceUsageVisitorProvider` |
| Per-hop rule evaluation | `HopAwareCompatibilityRule` |

Use runtime capability checks.

Do not require an old adapter to implement a new optional interface.

## Rule execution containment

`FrameworkRuleEngine` catches failures from third-party rules.

The failed rule is skipped.

The failure becomes evidence-backed uncertainty.

Remaining rules and integrations continue.

This protects report availability without pretending the failed rule succeeded.

Invalid findings are contained by the same boundary.

## Source scanning

`SourceUsageScanner` works from project-contained paths.

It parses PHP with PHP Parser.

Parse problems are recorded as uncertainties.

The scanner returns ordered `SourceUsage` inventory.

Inventory contains facts such as file, line, symbol, and usage type.

It does not itself decide that a usage blocks an upgrade.

## Ownership indexing

`AutoloadOwnershipIndexBuilder` reads root and locked-package autoload metadata.

It indexes relevant PSR mappings and exact declarations.

It has a bounded exact-file budget.

When limits or unreadable paths reduce confidence, it appends uncertainty.

`SymbolOwnershipIndex` answers ownership queries.

Ownership can be root, package, ambiguous, or otherwise modeled by the index.

## Source impact

`SourceImpactBuilder` correlates four inputs:

1. source inventory;
2. framework findings;
3. selected candidate package changes;
4. symbol ownership.

`SourceImpactAccumulator` merges equivalent conclusions while preserving occurrences and evidence.

`SourceImpactReasonWriter` centralizes stable explanation text.

This separation makes source impact testable without rerunning Composer.

## Staged analysis

`StagedUpgradeOrchestrator` works only with active stage providers.

A provider returns `FrameworkStagePlan`.

A plan contains ordered `FrameworkStageTarget` values or an explicit unavailable reason.

`StagePlanResolver` validates provider output and enforces selection rules.

`StageAttemptPlanner` creates attempts.

`StageExecutor` enforces stage and aggregate limits.

`StageBlockerRegistry` records blocker lifecycle.

Later stages use selected candidate project state from earlier successful stages.

## Analysis budgets

`AnalysisBudget` is serialized into the report.

`StagedAnalysisPolicy` aliases its constants for the analysis layer.

Budgets cover hops, attempts, scenarios, Composer processes, time, memory, and report sizes.

Some limits are enforced and some are advisory.

Read `AnalysisBudget` before claiming that every serialized budget is a hard runtime cap.

## Risk and effort

`RiskAndEffortEstimator` has aggregate and stage methods.

It consumes structured findings.

Risk output contains a level and reasons.

Effort output contains a range, confidence, components, and assumptions.

Never convert confidence to an undocumented percentage.

Never present effort as a contractual estimate.

## Evidence ledger

Pass the same `EvidenceLedger` through collaborators participating in one analysis.

`add()` creates a new sequential ID within a namespace.

`addOnce()` reuses content-identical evidence within that namespace.

`register()` accepts externally constructed evidence while rejecting duplicate IDs.

At report construction, all referenced IDs must exist.

All registered evidence must be referenced.

See [Determinism and Evidence](https://github.com/ValentinNikolaev/php-upgrade-preflight/wiki/Determinism-and-Evidence).

## Report construction

Use `ReportAssembler` for the normal complete report.

It delegates derived sections to `ReportSectionBuilder`.

It then creates `UpgradeReport` with direct, staged, framework, source, risk, effort, uncertainty, and evidence data.

Use `ReportAssembler::inputFailure()` only for terminal project-input failure.

Do not construct a weaker parallel report path for ordinary analysis.

## Rendering

`JsonReportWriter` is the canonical machine format.

`MarkdownReportWriter` is the human projection.

`ReportWriterResolver` maps normalized format to writer.

`ReportFileWriter` validates and writes destinations.

Rendering must be free of analysis decisions.

Given one report object, writers should describe the same conclusions.

## Safe contribution workflow

When changing Core:

1. Identify the owning service and model.
2. Read its unit tests before editing.
3. Preserve package boundaries.
4. Add the smallest model change that carries the fact.
5. Keep evidence creation near the observation.
6. Keep interpretation in analysis services.
7. Update report assembly and both renderers if output changes.
8. Update the current JSON schema.
9. Update snapshots intentionally.
10. Run focused tests, then the broader suite.
11. Update Wiki pages affected by behavior.

## Adding a report field

A report field normally touches:

- a model or section value;
- `UpgradeReport::toArray()` or nested serialization;
- `ReportAssembler` or `ReportSectionBuilder`;
- JSON schema;
- JSON snapshot tests;
- Markdown writer and snapshots when human-visible;
- documentation.

Do not patch only a snapshot.

Do not derive a second truth in Markdown.

## Adding a Composer scenario

Start in `ScenarioSelector`.

Decide whether it determines target feasibility.

Define its exact target set and dependency flags.

Ensure execution-key deduplication remains correct.

Teach workspace preparation only if the scenario requires new temporary input behavior.

Add classifier, blocker, and report tests for new outcomes.

Check analysis budgets.

## Adding a source usage type

Add or extend an AST visitor.

Represent the usage in `SourceUsage` vocabulary.

Keep file paths project-relative.

Add scanner tests with valid and invalid PHP examples.

If actionable, update ownership/impact correlation separately.

Do not make every new inventory item a finding.

## Adding an adapter capability

Prefer a new optional interface when old adapters can remain useful without it.

Contain third-party failures.

Add a current test-adapter implementation.

Verify `legacy-test-adapter` still loads.

Document manifest and construction requirements.

See [[Test Adapters|Test-Adapters]].

## Common mistakes

- Writing to the analyzed project instead of a workspace.
- Treating all non-zero Composer exits as blockers.
- Using a requested constraint as if it were a resolved version.
- Activating every installed adapter despite explicit framework selection.
- Creating evidence that no report claim references.
- Referencing an evidence ID that was never registered.
- Adding framework package names to Core.
- Sorting only in tests instead of at the ownership boundary.
- Allowing Markdown to disagree with JSON.
- Exposing absolute paths or credentials in diagnostics.

## Testing map

| Change area | Focused test directory |
| --- | --- |
| Analysis services | `packages/core/tests/Unit/Analysis` |
| Composer execution and loading | `packages/core/tests/Unit/Composer` |
| Models and invariants | `packages/core/tests/Unit/Model` |
| Report writers and schema | `packages/core/tests/Unit/Reporting` |
| AST and ownership | `packages/core/tests/Unit/Source` |
| Redaction and path policy | `packages/core/tests/Unit/Support` |
| Real Composer behavior | `packages/core/tests/Integration` |

Snapshot tests protect serialized contracts.

Integration tests cover behavior that mocks cannot prove.

## Release changes

If Core changes are part of a release tag, Wiki updates are mandatory.

Check tool version, schema version, package constraints, branch aliases, changelog, release notes, and examples together.

The release is not complete while tagged behavior and Wiki behavior claims disagree.

## Related pages

- [[Architecture Overview|Architecture-Overview]]
- [Package Map](https://github.com/ValentinNikolaev/php-upgrade-preflight/wiki/Package-Map)
- [[Core Analysis Pipeline|Core-Analysis-Pipeline]]
- [[Core Service Reference|Core-Service-Reference]]
- [Determinism and Evidence](https://github.com/ValentinNikolaev/php-upgrade-preflight/wiki/Determinism-and-Evidence)
