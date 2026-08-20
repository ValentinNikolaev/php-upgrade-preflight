# Core Service Reference

This is a navigation reference for the main non-model classes in `php-upgrade-preflight/core`. Start with [[Core Analysis Pipeline|Core-Analysis-Pipeline]] if you want the end-to-end story; use this page when you know the problem area and need the owning service.

## Analysis services

| Service | What it owns | Typical reason to inspect it |
| --- | --- | --- |
| `DefaultUpgradeAnalyzer` | Top-level orchestration from request to report | A report section is missing or services run in the wrong order |
| `TargetNormalizer` | Stable target normalization | Equivalent target inputs produce different internal target sets |
| `ScenarioSelector` | Direct Composer scenario matrix and deduplication | A needed probe is absent or duplicated |
| `ComposerBlockerParser` | Parse Composer conflict text into modeled relations | Solver text exists but blocker fields are incomplete |
| `BlockerGrouper` | Combine scenario evidence into ordered blockers | Duplicate conflicts appear or cross-scenario evidence is lost |
| `AbandonedPackageDetector` | Detect abandoned packages from lock metadata | Abandonment metadata is missing from blocker output |
| `LockDiffBuilder` | Compare baseline and selected candidate locks | Added, removed, upgraded, or downgraded packages are wrong |
| `FrameworkRuleEngine` | Activate adapters and run/contain their rules | An adapter is not active or a failing rule affects other rules |
| `SourceImpactBuilder` | Correlate usages, ownership, rules, and package changes | Inventory is incorrectly promoted to actionable impact |
| `SourceImpactAccumulator` | Merge compatible impact observations | Repeated impact rows should form one finding |
| `SourceImpactReasonWriter` | Stable human-readable impact reason text | Impact evidence is correct but explanation vocabulary is wrong |
| `RiskAndEffortEstimator` | Deterministic aggregate and stage assessments | Risk level or effort components do not match evidence |
| `ReportSectionBuilder` | Build normalized report-section values | A section needs ordering or cross-field normalization |
| `ReportAssembler` | Create the final `UpgradeReport` and input-failure reports | Schema-required fields or evidence references are missing |

## Staged-analysis services

Staged analysis is split into small services because planning, execution, assessment, and blocker lifecycle are different concerns.

| Service | Responsibility |
| --- | --- |
| `StagedUpgradeOrchestrator` | Carry candidate project state from one stage to the next and stop the chain safely |
| `StagePlanResolver` | Select and validate a framework-provided plan; contain provider failures |
| `StageAttemptPlanner` | Define ordered attempts for a stage, including remediation candidates |
| `StageExecutor` | Run attempts within stage and aggregate budgets, then select the stage outcome |
| `StageOutcome` | Return the stage analysis plus optional selected next state |
| `StageBlockerRegistry` | Track blocker identity and lifecycle across attempts |
| `StageAssessmentBuilder` | Add source impact, risk, effort, tests, and actions to executed stages |
| `StagedAnalysisPolicy` | Shared bounds for stage count, attempts, and runtime |

Example debugging path:

```text
Adapter returned an expected stage
  -> StagePlanResolver accepted or rejected it
  -> StageAttemptPlanner generated attempts
  -> StageExecutor ran Composer
  -> StageBlockerRegistry tracked conflicts
  -> StagedUpgradeOrchestrator selected next state or stopped
  -> StageAssessmentBuilder attached reporting assessments
```

## Composer services

| Service | Responsibility | Important boundary |
| --- | --- | --- |
| `JsonFileReader` | Strict JSON-object reading with typed failures | Invalid JSON is project input evidence, not a solver blocker |
| `ProjectStateBuilder` | Load manifest and lock into `ProjectState` | Can return a failure-bearing load result |
| `TargetPlatformProfileFileReader` | Read a platform profile file | Profile values are validated by model construction |
| `ComposerScenarioRunner` | Run Composer, metadata probes, diagnostics, cleanup, and candidate-lock reads | All execution happens in analyzer-owned workspaces |
| `ScenarioWorkspacePreparer` | Seed and modify copied Composer files and construct restricted environment | Never writes target changes to the analyzed tree |
| `ScenarioOutcomeClassifier` | Separate success, solver failure, and operational outcomes | Process failure does not always mean dependency blockage |
| `CandidateLockFileReader` | Fingerprint LF-normalized candidate lockfile bytes and package them as `CandidateLockEvidence` | Unreadable lock evidence becomes uncertainty rather than guessed data |
| `ComposerPackageMetadataLookup` | Read-only, bounded package/version discovery for interactive target selection | The caller must explicitly choose cache-only or project-repository lookup; operational failures remain unverified |
| `PackageMetadataLookupMode` | Closed vocabulary for `local_cache_only` and `project_repositories` | Network permission is explicit rather than hidden in a default API |
| `PackageMetadataLookupResult` | Invalid/found/not-found/unverified result plus bounded version and diagnostic data | Offline, timeout, malformed output, and cache misses are not package nonexistence |

`ComposerScenarioRunner` is intentionally broad because it owns one external-process boundary. Analysis interpretation remains in `Analysis` services.

`ComposerPackageMetadataLookup` is a separate pre-analysis discovery boundary. It invokes the selected Composer executable with `show --all --format=json`, plugins, scripts, interaction, and ANSI disabled, and uses the configured diagnostic timeout. Project-repository mode may use repository configuration, credentials, and network. Local-cache mode requests network disablement and never returns `not_found` for a cache miss. Restricted `ComposerExecutionConfiguration` currently returns an explicit unverified result without starting a process because isolated lookup state is not yet implemented.

## Progress services

| Type | Responsibility |
| --- | --- |
| `AnalysisProgressReporter` | Optional observational event sink injected into the analyzer |
| `AnalysisProgressEvent` | Validated analysis, phase, and Composer-scenario lifecycle event |
| `AnalysisPhase` | Stable phase identifiers for project loading, Composer feasibility, staged resolution, source scan, framework evaluation, and report assembly |
| `NoOpAnalysisProgressReporter` | Default sink for embeddings that do not expose progress |

`DefaultUpgradeAnalyzer` emits lifecycle events but contains reporter exceptions. Reporters must not change ordering, evidence, report status, or failures. CLI and Laravel own terminal-specific rendering; Core contains no TTY or console styling code.

## Filesystem services

| Type | Role |
| --- | --- |
| `WorkspaceFilesystem` | Abstraction for analyzer workspace operations |
| `NativeWorkspaceFilesystem` | Native implementation of those operations |
| `WorkspaceManager` | Workspace lifecycle contract |
| `TemporaryWorkspaceManager` | Create, retain in debug mode, and remove scenario workspaces |
| `WorkspaceCleanupException` | Preserve cleanup failure details without hiding the original analysis evidence |

Cleanup failures matter because a retained workspace can contain copied Composer metadata. Core therefore reports cleanup uncertainty rather than treating cleanup as invisible housekeeping.

## Source services

| Service | Responsibility |
| --- | --- |
| `SourceUsageScanner` | Find PHP files under selected paths, parse ASTs, and collect inventory |
| `SourceUsageVisitor` | Collect common names and usages from PHP AST nodes |
| `ExplicitFullyQualifiedNameVisitor` | Record explicitly fully qualified names |
| `SymbolDeclarationVisitor` | Record declarations used to build ownership information |
| `AutoloadOwnershipIndexBuilder` | Resolve Composer PSR-0/PSR-4/classmap/files ownership evidence |
| `SymbolOwnershipIndex` | Query which package owns a discovered symbol |

Framework adapters can add collectors with `SourceUsageVisitorProvider`. They augment the framework-neutral scan; they do not replace Core's parser.

## Reporting services

| Service | Responsibility |
| --- | --- |
| `JsonReportWriter` | Serialize the canonical report structure as JSON |
| `MarkdownReportWriter` | Render the same report for human reading |
| `ReportWriter` | Writer interface |
| `ReportWriterResolver` | Normalize format selection into a writer |
| `ReportDestinationFilesystem` | Destination filesystem contract |
| `SymfonyReportDestinationFilesystem` | Production destination implementation |
| `ReportFileWriter` | Validate output location and perform the final write |

When adding a report field, changing only a writer is not enough. Update the model, assembler, canonical JSON schema, snapshots, both projections where relevant, and Wiki documentation.

## Safety and privacy services

| Service | What it protects against |
| --- | --- |
| `SensitiveOutputRedactor` | Credentials, tokens, and sensitive fragments in diagnostics or external output |
| `PathExposurePolicy` | Absolute host paths leaking into shareable reports |
| `OutputExcerpt` | Unbounded Composer output and invalid UTF-8 truncation |

The services complement one another. A bounded excerpt can still contain a token; redaction is still required. A redacted error can still expose a host directory; path normalization is still required.

## Important model families

Core model classes are immutable data boundaries rather than services. The easiest way to navigate them is by family:

| Family | Representative classes |
| --- | --- |
| Request and targets | `UpgradeRequest`, `UpgradeTarget`, `UpgradeTargetSet`, `TargetPlatform`, `TargetPlatformProfile` |
| Composer input | `ComposerJson`, `ComposerLock`, `PackageRef`, `ProjectState`, `ProjectStateFingerprint` |
| Direct scenarios | `Scenario`, `ScenarioResult`, `ComposerDiagnostic`, `CandidateLockEvidence` |
| Changes and blockers | `LockDiff`, `PackageChange`, `RootConstraintChange`, `Blocker`, `BlockerAttribution`, `SolverRelation` |
| Framework guidance | `FrameworkGuidance`, `FrameworkHop`, `CompatibilityFinding` |
| Staging | `FrameworkStagePlan`, `FrameworkStageTarget`, `StageAttempt`, `StageAnalysis`, `StageBlockerEntry`, `StagedResolution` |
| Source | `SourceUsage`, `SourceImpactFinding` |
| Evidence | `Evidence`, `EvidenceLedger`, `EvidenceRecorder` |
| Assessment | `RiskSummary`, `EffortEstimate`, `TestGuidance`, `StageTestGuidance` |
| Report | `ReportMetadata`, `ReportSections`, `UpgradeReport`, `ReportFormat` |

## Practical change examples

### Add a new blocker attribute

Follow the data from `ComposerBlockerParser` or attribution logic into `Blocker`, through grouping, then into the report model and schema. Verify JSON and Markdown snapshots. Avoid deriving the attribute independently in a report writer.

### Add a framework-specific check

Implement it in the adapter's rule catalog/rule classes. Core should see only `CompatibilityRule` or `HopAwareCompatibilityRule`. If the check needs special AST data, expose a visitor from the adapter.

### Add a different output format

Implement `ReportWriter`, register resolution in `ReportWriterResolver`, extend `ReportFormat`, and add tests. The new writer must consume `UpgradeReport`; it must not rerun Composer or reinterpret the project.

### Diagnose a surprising source-impact row

Check, in order:

1. `SourceUsageScanner` inventory;
2. adapter compatibility findings;
3. selected candidate `LockDiff`;
4. `SymbolOwnershipIndex` result;
5. `SourceImpactBuilder` correlation;
6. `SourceImpactAccumulator` merge behavior.

This order helps distinguish a parsing error from an ownership error or an over-broad impact rule.

## Related pages

- [Package Map](https://github.com/ValentinNikolaev/php-upgrade-preflight/wiki/Package-Map)
- [[Core Analysis Pipeline|Core-Analysis-Pipeline]]
- [CLI Package Internals](https://github.com/ValentinNikolaev/php-upgrade-preflight/wiki/CLI-Package-Internals)
- [Laravel Package Internals](https://github.com/ValentinNikolaev/php-upgrade-preflight/wiki/Laravel-Package-Internals)
