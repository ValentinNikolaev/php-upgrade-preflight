# Core Package Guide

`php-upgrade-preflight/core` contains the framework-neutral analysis engine. Use this guide when embedding it or changing one of its contracts. [[Core Service Reference|Core-Service-Reference]] lists the classes. [[Architecture Overview|Architecture-Overview]] shows how the packages fit together.

## What Core promises

`UpgradeAnalyzer` accepts an `UpgradeRequest` and returns an `UpgradeReport`. Core runs Composer probes in temporary workspaces and keeps Composer, source, platform, and adapter evidence separate. Framework knowledge arrives through adapters.

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

- PHP `^8.0`
- `composer/semver` `^3.4`
- `nikic/php-parser` `^4.19|^5.0`
- Symfony Filesystem `^5.4|^6.0|^7.0|^8.0`
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

For command-line use, the CLI handles request construction and report delivery.

Embedders may inject an `AnalysisProgressReporter` into `DefaultUpgradeAnalyzer`. Core emits validated lifecycle events for analysis, phases, and Composer scenarios. `NoOpAnalysisProgressReporter` is the default. Reporter failures are contained, so progress remains observational and cannot change the returned report.

## Constructing a valid request

`UpgradeRequest` validates inputs when you construct it. The project path must exist, and normalization must leave at least one package target, target PHP value, or target-platform profile. Represent package targets with `UpgradeTarget`.

```php
$target = new UpgradeTarget('symfony/console', '^7.0');
```

Use a valid Composer package name and a constraint that Composer Semver can parse. `fromPhp` and `targetPhp` need exact PHP versions because Core models a concrete platform value.

```php
targetPhp: '8.3'
```

A range such as `^8.3` cannot stand in for that exact platform value.

## Target normalization

`UpgradeTargetSet` validates and sorts targets. It merges package-style `php:VERSION` input with the dedicated PHP target. Equivalent values collapse to one target. Conflicting PHP values or duplicate package constraints fail before scenario selection. This stable input order carries through to the report.

## Composer execution configuration

`ComposerExecutionConfiguration` owns:

- executable command
- expected Composer version range
- scenario timeout
- diagnostic timeout
- compatible or restricted mode
- derived environment and network policy.

Defaults are:

| Setting | Default |
| --- | --- |
| Executable | `composer` |
| Expected version | `>=2.0.0 <3.0.0` |
| Scenario timeout | 300 seconds |
| Diagnostic timeout | 60 seconds |
| Mode | `compatible` |

Scenario timeouts accept 1–3600 seconds, and diagnostic timeouts accept 1–900 seconds.

## Package metadata discovery

`ComposerPackageMetadataLookup` is the bounded read-only discovery service used by interactive clients before analysis. Its public `lookup()` operation requires the project path, package, constraint, `ComposerExecutionConfiguration`, and an explicit `PackageMetadataLookupMode`.

The result is a `PackageMetadataLookupResult` with one of four statuses: `invalid`, `found`, `not_found`, or `unverified`. A found result includes bounded discovered and constraint-matching version lists and their full counts. Local-cache misses, timeout, offline, malformed output, and process failures are unverified rather than guessed nonexistence. Project-repository mode may use configured repositories, credentials, and network. Only its explicit package-not-found response becomes `not_found`.

Restricted execution currently returns `restricted_execution_unavailable` without starting a process. This preserves the restricted contract until lookup can create isolated Composer home/cache state. Lookup diagnostics are bounded and redacted, and the service never writes analysis results into the target project.

## Loading project state

`ProjectStateBuilder::load()` returns `ProjectStateLoadResult`. Check `succeeded()` before using the state. Use `build()` when the caller should fail immediately. `ComposerJson` and `ComposerLock` expose normalized facts while retaining the raw data needed for temporary copies and report context.

## JSON failure types

Core distinguishes:

- `MissingJsonFileException`
- `UnreadableJsonFileException`
- `InvalidJsonException`.

Keep these failures separate from solver results. Composer may never have started.

## Building the target platform

Use `TargetPlatform::fromRequest($request, $project)`.

This combines explicit target data with project metadata. A `TargetPlatformProfile` supplies platform values under a declared completeness mode. Its reader and models validate supported package classes, values, and duplicate JSON keys. The request rejects contradictions with direct targets or extension assumptions.

## Selecting scenarios

`ScenarioSelector::select()` returns scenarios in a deliberate order and removes executions that would be equivalent. The count depends on the target set and available current PHP evidence.

A scenario contains:

- name
- target set
- with-all-dependencies flag
- minimal-changes flag
- baseline flag
- target-feasibility flag.

Check `target-feasibility` before using a result to decide final resolution. A partial diagnostic probe does not settle that question.

## Running a scenario

`ComposerScenarioRunner::run()` accepts:

- current `ProjectState`
- `UpgradeRequest`
- one `Scenario`
- `TargetPlatform`.

Before analysis, `DefaultUpgradeAnalyzer` resets the runner's caches. The runner can probe Composer's version and platform packages, prepares copied Composer files in a temporary workspace, applies the scenario, runs Composer through Symfony Process, and reads a candidate lock when one exists. It cleans the workspace unless debug mode retains it.

## Workspace preparation

`ScenarioWorkspacePreparer` changes only copied Composer data. It updates a package in `require-dev` if that is where the project declared it. Otherwise, it writes the target to `require`. It adds simulated values to copied `config.platform`, preserves existing platform-key casing, and resolves relative `path` and `artifact` repository URLs against the original project so they still point to the same place.

## Scenario result interpretation

Check `ScenarioResult` fields rather than exit code alone.

Important dimensions include:

- `succeeded()`
- failure type
- outcome
- candidate lock
- diagnostics
- Composer version
- duration
- evidence references.

`ScenarioOutcomeClassifier` separates a solver conflict from an execution problem. A timeout or missing executable provides no proof of a dependency conflict.

## Lock changes

`LockDiffBuilder` compares baseline and candidate `ComposerLock` values. The resulting `LockDiff` contains added, removed, upgraded, and downgraded `PackageChange` entries, which adapter classifiers may label by family. Without a readable candidate lock, Core leaves the candidate diff empty. A requested constraint does not identify an installed version.

## Blockers

`BlockerGrouper` consumes scenario results and an evidence ledger.

It uses `ComposerBlockerParser` for transcript relations.

It also has access to lock data, requested constraints, and target platform.

A `Blocker` can include:

- type
- subject
- blocking package
- requested and blocking constraints
- dependency path
- attribution
- confidence
- scenario references
- evidence references.

When adding blocker knowledge, carry it in these fields. A longer message cannot give report consumers the same structured data.

## Framework integrations

Every adapter implements `FrameworkIntegration`.

That base contract supplies:

- stable name
- detection
- compatibility rules
- default source paths.

Optional interfaces add capabilities.

| Capability | Interface |
| --- | --- |
| Transition guidance | `FrameworkTransitionProvider` |
| Staged targets | `FrameworkStageTargetProvider` |
| Package families | `PackageFamilyClassifier` |
| Extra AST visitors | `SourceUsageVisitorProvider` |
| Per-hop rule evaluation | `HopAwareCompatibilityRule` |

Check optional interfaces at runtime. An older adapter can still provide its existing capabilities.

## Rule execution containment

`FrameworkRuleEngine` catches third-party rule failures and invalid findings. It records evidence-backed uncertainty for the affected rule and continues with the others. The report can finish while still showing that one check did not.

## Source scanning

`SourceUsageScanner` parses PHP under paths contained in the project and returns ordered `SourceUsage` records: file, line, symbol, and usage type. Parse failures become uncertainties. A usage is an observation, not yet an upgrade finding.

## Ownership indexing

`AutoloadOwnershipIndexBuilder` reads root and locked-package autoload metadata, including relevant PSR mappings and exact declarations. Its exact-file scan has a limit. When that limit or an unreadable path weakens the result, it records uncertainty. `SymbolOwnershipIndex` then answers whether a symbol belongs to the root project, a package, or an ambiguous set of owners.

## Source impact

`SourceImpactBuilder` correlates four inputs:

1. source inventory
2. framework findings
3. selected candidate package changes
4. symbol ownership.

`SourceImpactAccumulator` merges equivalent conclusions without losing occurrences or evidence. `SourceImpactReasonWriter` supplies stable explanation text. You can test this correlation with fixed inputs instead of rerunning Composer.

## Staged analysis

`StagedUpgradeOrchestrator` runs when an active adapter supplies `FrameworkStageTargetProvider`. Its `FrameworkStagePlan` contains ordered targets or a reason staging is unavailable. `StagePlanResolver` validates the plan, `StageAttemptPlanner` prepares attempts, `StageExecutor` applies stage and aggregate limits, and `StageBlockerRegistry` follows blockers across attempts. Each successful stage passes its selected candidate project state to the next.

## Analysis budgets

The report serializes the values from `AnalysisBudget`. `StagedAnalysisPolicy` exposes those same constants to the analysis layer. Core enforces hop and Composer-process limits and checks scenario, stage, and aggregate time while running staged Composer work. Before an attempt, it reserves time for the scenario and its possible `composer prohibits` diagnostics. `MAX_ATTEMPTS_PER_STAGE` caps the attempt list, but the time left may prevent every planned attempt from running. `MAX_SCENARIOS` is the product of the hop and attempt limits.

Memory and JSON/Markdown report-size values are advisory targets. The analyzer does not measure arbitrary projects against them, though committed fixtures check report size. Schema 0.8 puts enforced and advisory values together in `budgets` without enforcement labels or observed measurements. Adding those fields would require a new schema version and an intentional minor-line migration.

The Markdown staged section labels enforced hop, attempt, process and timeout limits separately from advisory memory and size targets. No per-run peak-memory or advisory pass measurement is serialized; absence does not mean zero usage or a passed target.

## Risk and effort

`RiskAndEffortEstimator` uses structured findings for aggregate and stage assessments. Risk has a level and reasons. Effort has a range, confidence, components, and assumptions. The confidence label is not a probability, and the effort range is planning input rather than a delivery commitment.

`ReportAssessmentQualifier` adds canonical drivers and assumptions for unknown or degraded Composer work, baseline validation failure, unavailable input/source/adapter contributions, and incomplete staged work without changing schema 0.8 grades or ranges. The hour range covers observed dependency, source and test work only; unobserved migration, deployment, runtime and business validation are outside it. `ReportAssembler` orders resolution blockers ahead of advisories for the first decision while preserving each group's existing order.

## Evidence ledger

Pass one `EvidenceLedger` through an analysis. `add()` gives a new namespace-local sequence ID, `addOnce()` reuses identical content in that namespace, and `register()` accepts an externally created item only if its ID is free. At report construction, every referenced ID must exist and every registered item must support a claim.

See [[Determinism and Evidence|Determinism-and-Evidence]].

## Report construction

`ReportAssembler` builds the normal `UpgradeReport`, using `ReportSectionBuilder` for derived sections. It joins direct, staged, framework, source, risk, effort, uncertainty, and evidence data. `inputFailure()` handles terminal project-input failure. Ordinary analysis should use the complete path.

## Rendering

`JsonReportWriter` writes the canonical machine format. `MarkdownReportWriter` presents the same conclusions for readers. `ReportWriterResolver` chooses the writer, and `ReportFileWriter` checks and writes a destination. Rendering should make no new analysis decision.

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

- a model or section value
- `UpgradeReport::toArray()` or nested serialization
- `ReportAssembler` or `ReportSectionBuilder`
- JSON schema
- JSON snapshot tests
- Markdown writer and snapshots when human-visible
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

Snapshots catch changes to serialized contracts. Integration tests exercise real Composer and filesystem behavior that a stubbed unit test cannot establish.

## Release changes

For release-tag work, update the Wiki with the code. Compare tool and schema versions, package constraints, branch aliases, changelog, release notes, and examples before calling the release complete.

## Related pages

- [[Architecture Overview|Architecture-Overview]]
- [[Package Map|Package-Map]]
- [[Core Analysis Pipeline|Core-Analysis-Pipeline]]
- [[Core Service Reference|Core-Service-Reference]]
- [[Determinism and Evidence|Determinism-and-Evidence]]
