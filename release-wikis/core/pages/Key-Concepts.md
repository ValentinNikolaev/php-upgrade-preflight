# Key Concepts

This glossary gives the shortest accurate model of the terms used by PHP Upgrade Preflight. Examples are illustrative but use current report vocabulary.

## Upgrade target

An upgrade target is one requested Composer package constraint, represented by `UpgradeTarget` and written as `package:constraint` on the command line.

```bash
--target=laravel/framework:^13.0
```

PHP may be supplied as `--target=php:8.3` or `--target-php=8.3`. If both forms are present, normalization requires them to describe the same exact PHP value.

## Target set

The target set is the normalized collection of package targets plus an optional exact target PHP. `UpgradeTargetSet` sorts and validates the request so equivalent inputs have stable meaning.

```bash
--target=laravel/framework:^11.0 \
--target=laravel/passport:^11.0 \
--target-php=8.2
```

At least one package target, target PHP, or target-platform profile is required.

## Composer scenario

A Composer scenario is one isolated command run against copied manifests in an analyzer-owned temporary workspace. Examples include baseline validation, an exact target solve, and a target solve with all dependencies.

Each scenario records its name, safe command array, duration, exit code, outcome, bounded and redacted output excerpts, diagnostics, optional candidate-lock evidence, and optional debug path.

A scenario is evidence, not a change to the project.

## Direct resolution

`resolution` summarizes the Composer scenarios that ask about the requested final target directly. Its status is:

- `feasible`: a determining direct Composer scenario succeeded without package changes;
- `feasible_with_changes`: a determining direct Composer scenario succeeded with candidate package changes;
- `blocked`: Composer produced reproducible solver blockers;
- `unknown`: the analyzer could not reach a reliable solver conclusion.

This direct result is independent of framework rule coverage and staged resolution.

## Staged analysis

Staged analysis evaluates an upgrade as bounded adjacent stages, sometimes called hops. For a rooted Laravel 10→13 request, the adapter can plan 10→11, 11→12, and 12→13.

Each evaluated stage starts from the selected candidate state of the previous successful stage. The analyzed project's original files are never replaced. Only the original source snapshot is inspected, even when later stages are assessed.

## Hop

A hop is a framework-major transition such as Laravel 10→11. Adapter guidance describes whether a rule pack exists for that hop. Composer stage execution separately describes whether candidate dependencies could be solved for the hop.

“Supported guidance” never means “Composer resolution succeeded.”

## Stage

A stage is the executable unit of staged analysis. It has an ID such as `laravel-10-to-11`, exact package targets, an exact analysis PHP value supported by request evidence, attempts, input/output fingerprints, changes, blockers, source impact, tests, risk, effort, and actions.

A stage is `evaluated` or `skipped`. Evaluated stage statuses use `feasible`, `feasible_with_changes`, `blocked`, or `unknown`; the aggregate staged result is reported separately.

## Blocker

A blocker is a structured explanation of why Composer could not resolve a requested state. It can identify the category, subject, blocking package, requested and blocking constraints, dependency path, confidence, scenario links, and evidence links.

Example question a blocker answers: “Which locked package conflicts with Laravel 11, and in which scenarios was the conflict observed?”

## Blocker attribution

Blocker attribution explains the relationship between a parsed conflict and the request. It distinguishes a root requirement, a locked dependency, a platform requirement, and other modeled causes instead of treating every line of Composer output as the same kind of failure.

Attribution allows remediation planning to focus on causes rather than raw text.

## Blocker lifecycle

The staged blocker registry tracks a blocker across attempts within a stage. Its lifecycle vocabulary is:

- `detected`: first observed;
- `persists`: still observed after another attempt;
- `resolved`: absent after a successful remediation;
- `superseded`: replaced by a different effective blocker.

Similar messages do not merge if their stage, constraint, dependency path, platform, or identity differs.

## Lock diff

A lock diff compares the original lock state with a selected candidate lock. It reports package additions, removals, upgrades, downgrades, and other modeled changes through `LockDiff` and `PackageChange` values.

No candidate lock means no honest candidate diff. The analyzer does not fabricate expected versions from constraints.

## Root constraint change

A root constraint change describes a requested edit to a direct requirement in the temporary manifest. It is distinct from a package version change in the candidate lock.

For example, changing the requested root constraint to `laravel/framework:^11.0` and resolving `laravel/framework` to `11.0.0` are related but different facts.

## Source inventory

`source_inventory` is the framework-neutral record of discovered PHP declarations and usages. It is broader than actionable upgrade impact and preserves useful raw observations.

The scanner uses `nikic/php-parser`, not regular expressions, so syntax structure and line numbers come from an AST.

## Source impact finding

A source impact finding correlates a source usage with a relevant package change or framework rule. It names the file, line, symbol/type, reason, severity or confidence where modeled, and evidence references.

Inventory does not automatically become impact. Ownership and transition relevance are required to avoid blaming unrelated symbols.

## Symbol ownership index

`SymbolOwnershipIndex` maps Composer-autoloaded symbols to owning packages. It helps distinguish application use of a changing dependency from a same-named symbol owned elsewhere.

This seam makes source impact more precise than a plain text search.

## Framework detection

Framework detection is adapter-provided recognition of a project and optional version. The Laravel detector reads root requirements and the lock file; it does not boot Laravel.

Without explicit `--framework`, installed adapters may activate through detection. With `--framework=laravel`, the named integration is explicitly requested and must be installed.

## Framework guidance

Framework guidance is adapter-authored transition coverage. It can be `supported`, `partially_supported`, or `unsupported` and contains hop-specific findings and uncertainties.

It is based on maintained rule catalogs and project evidence. It is not a Composer feasibility result and not an automated migration.

## Evidence

Evidence is a structured item supporting a claim. It has a stable ID, kind, evidence level, summary, confidence, and context. Findings link to evidence by ID rather than copying untraceable prose.

Examples include project metadata, Composer outcomes, source observations, and maintainer documentation encoded by an adapter.

## Evidence ledger

`EvidenceLedger` is the ordered collection that creates and stores evidence items. IDs are unique and deterministic for the same analysis input and execution results.

The ledger enables a reader to start at a blocker or finding, follow its evidence IDs, and inspect the supporting context.

## Orphan evidence

Orphan evidence would be an evidence record that supports no report claim. The report assembly contract avoids such disconnected records: useful evidence is referenced by scenarios, blockers, findings, guidance, plans, uncertainty, or other report sections.

## Uncertainty

An uncertainty is an explicit statement that the analyzer lacks sufficient evidence for a confident claim. Examples include host-dependent extensions, a PHP parse failure, a contained adapter exception, an unavailable Composer executable, or a staging guidance gap.

Unknown is a valid, intentional result. The tool records uncertainty rather than guessing.

## Risk summary

The risk summary combines deterministic drivers into a level and a list of reasons. Drivers can include blockers, package changes, source impact, framework findings, and uncertainty.

Risk is planning support, not a probability of failure and not a deployment verdict.

## Effort estimate

The effort estimate is a range of hours, a confidence value, components, and assumptions. A range acknowledges that evidence supports bounds better than false precision.

Example:

```json
{
  "range_hours": {"min": 8, "max": 20},
  "confidence": "medium",
  "components": [],
  "assumptions": []
}
```

Always read the assumptions and confidence. The number is not a quote or commitment.

## Confidence

Confidence records how directly evidence supports a finding. It does not erase uncertainty and should not be translated into a numeric probability unless a separate system explicitly defines that conversion.

## Resolution status

At the direct-report level, consumers must handle exactly these values:

| Status | Meaning | Automation action |
| --- | --- | --- |
| `feasible` | A determining direct scenario succeeded without package changes | Continue to findings and tests |
| `feasible_with_changes` | A determining direct scenario succeeded with candidate package changes | Review candidate changes, findings, and tests |
| `blocked` | Reproducible solver conflicts were found | Read blockers and remediation evidence |
| `unknown` | Operational or evidence limits prevented a reliable conclusion | Fix the cause and rerun |

The process exit code remains `0` for all four when a valid report was produced.

## Canonical JSON

Canonical JSON is the machine-readable report contract. Schema 0.8 defines required fields, types, enums, and compatibility expectations for v0.3.x consumers.

Automation must consume JSON, validate `metadata.schema_version`, and preserve unknown fields for forward-tolerant processing where appropriate.

## Markdown projection

Markdown is a human-readable rendering of an `UpgradeReport`. `MarkdownReportWriter` does not re-run analysis and has no independent decision logic.

If JSON and Markdown appear to disagree, preserve the JSON, check the schema and writer, and treat the mismatch as a bug.

## Path marker

Shareable reports replace absolute roots with stable markers:

- `[PROJECT_ROOT]` for the analyzed project;
- `[REPORT_OUTPUT]` for the chosen destination;
- `[LOCAL_REPOSITORY]` for local Composer repositories;
- `[ANALYZER_WORKSPACE]` for temporary analyzer roots.

Source files remain project-relative. Debug mode can expose exact temporary paths.

## Read-only

Read-only means the analyzer does not mutate the target tree. It does not mean Composer has no network or credential access, and it does not mean retained debug workspaces contain no sensitive copied data.

See [[Safety and Trust Boundaries|Safety-and-Trust-Boundaries]] for the full boundary.

## Deterministic

Deterministic means the implementation normalizes ordering, paths, excerpts, IDs, and report assembly so equivalent evidence produces stable output. It does not mean uncontrolled repository metadata, network state, or different Composer executables are magically identical.

See [Determinism and Evidence](https://github.com/ValentinNikolaev/php-upgrade-preflight/wiki/Determinism-and-Evidence).

## Next steps

- Run a first analysis: [Getting Started](https://github.com/ValentinNikolaev/php-upgrade-preflight/wiki/Getting-Started).
- Interpret every section: [Reading the Report](https://github.com/ValentinNikolaev/php-upgrade-preflight/wiki/Reading-the-Report).
- Build an adapter: [[Writing a Framework Adapter|Writing-a-Framework-Adapter]].

## How the concepts connect

The concepts form an evidence chain rather than an unordered glossary.

```text
UpgradeRequest
  -> normalized target set and target platform
  -> direct Composer scenarios
  -> candidate lock changes or blockers/unknown
  -> framework guidance and staged analysis
  -> source inventory and ownership correlation
  -> risk, effort, tests, actions, and evidence
  -> UpgradeReport
```

An error near the start changes what can be claimed later.

For example, an unreadable lock file prevents an honest candidate lock comparison.

It should not be replaced with guessed package changes.

## Direct versus staged example

Consider a Laravel 10 project targeting Laravel 13.

Direct analysis asks Composer about the final requested state.

Staged analysis asks about 10→11, then 11→12, then 12→13 when the adapter supplies a valid plan.

These conclusions can differ:

| Direct result | Staged result | Meaning |
| --- | --- | --- |
| `feasible_with_changes` | `feasible_with_changes` | Final and adjacent candidate states were found; application verification remains |
| `blocked` | `feasible_with_changes` | Direct solve is blocked under its scenario evidence, while adjacent candidates provide planning evidence |
| `feasible` | `unknown` or skipped | Direct Composer evidence succeeded, but staged evidence was unavailable or unnecessary |
| `unknown` | `blocked` | Direct execution lacked a reliable conclusion, while a stage produced a reproducible conflict |

Never overwrite one status with another.

The report keeps them separate so a reader can ask the right follow-up question.

## Constraint versus resolved version

A Composer constraint is a request such as `^12.0`.

A resolved version is a concrete candidate such as `12.4.2`.

They are not interchangeable.

```text
root constraint change: laravel/framework -> ^12.0
candidate package change: laravel/framework 11.46.0 -> 12.4.2
```

The first describes temporary manifest intent.

The second describes solver-produced lock evidence.

When no candidate lock exists, the analyzer must not invent the second line.

## Severity, confidence, and risk

These three values answer different questions.

| Value | Question |
| --- | --- |
| Severity | How serious is this individual finding if applicable? |
| Confidence | How directly does evidence support the finding? |
| Risk | What aggregate planning level follows from the report's modeled drivers? |

A high-severity, low-confidence finding deserves investigation.

It does not automatically make every conclusion high confidence.

A low-severity finding can still be backed by high-confidence evidence.

Risk reasons should show which drivers affected the aggregate level.

## Evidence classes in practice

| Class | Practical reading |
| --- | --- |
| E1 solver | Composer directly observed an execution or dependency relation |
| E2 package metadata | A manifest or lock file states the fact |
| E3 project source | The PHP parser observed a declaration or usage |
| E4 maintainer documentation | Adapter-maintained source material supports transition guidance |
| E5 heuristic | A deterministic rule inferred planning advice from other facts |

Evidence class is not severity.

Follow the evidence ID to its summary, confidence, and structured context.

## Uncertainty checklist

Before acting on a report, ask:

1. Was Composer executable and within the expected version range?
2. Did scenarios complete within configured timeouts?
3. Was target platform evidence explicit and non-contradictory?
4. Did every requested source path parse successfully?
5. Were adapter rules or stage providers skipped after exceptions?
6. Was a candidate lock available for the claimed package diff?
7. Did workspace cleanup leave sensitive retained state?

An uncertainty is actionable information about missing evidence.

It is not documentation noise to delete from a management summary.

## Manager-readable summary vocabulary

Prefer precise phrases:

- “Composer found a candidate state” instead of “the upgrade works.”
- “The adapter has guidance for these hops” instead of “Laravel supports the migration automatically.”
- “Source locations require review” instead of “the code is incompatible.”
- “The estimate assumes the recorded evidence” instead of “delivery will take this many hours.”
- “The result is unknown because Composer timed out” instead of “Composer conflict.”

This vocabulary keeps technical evidence and business decisions connected without overstating either.
