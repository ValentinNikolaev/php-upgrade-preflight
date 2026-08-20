# Report Schema

PHP Upgrade Preflight v0.3.x writes canonical JSON schema 0.8. The normative file is [`upgrade-report-v0.8.schema.json`](../packages/core/resources/schema/upgrade-report-v0.8.schema.json), a strict JSON Schema Draft 2020-12 document.

This page is a consumer guide. The schema file is authoritative for exact types, required properties, patterns, enums, and conditional rules.

## Select by schema version

```json
{
  "metadata": {
    "schema_version": "0.8",
    "tool": {
      "name": "php-upgrade-preflight",
      "version": "0.3.3"
    }
  }
}
```

Select the parser by `metadata.schema_version`, not `metadata.tool.version`. Tool and schema versions move independently. Patch releases can change findings or wording while keeping valid schema 0.8 shape.

## Strict top-level shape

The root rejects additional properties and requires all of these:

| Property | Type | Purpose |
| --- | --- | --- |
| `metadata` | object | Schema and tool identity |
| `request_summary` | object | Normalized safe request |
| `project_state` | object | Original Composer facts |
| `platform` | object | Analyzer/current/target platform provenance |
| `composer_execution` | object | Effective Composer policy and version |
| `resolution` | object | Direct final-target status and scenarios |
| `staged_resolution` | object | Adjacent-stage execution and evidence |
| `transition` | object | Direct changes and framework guidance |
| `blockers` | array | Direct structured blockers |
| `source_inventory` | array | Raw source observations |
| `source_impact` | array | Direct actionable source findings |
| `framework_findings` | array | Adapter guidance scoped to hops |
| `plan` | object | Ordered evidence-backed actions |
| `risk` | object | Level and drivers |
| `effort` | object | Hour range, confidence, components, assumptions |
| `tests` | array | Required/recommended validation |
| `uncertainties` | array | Unique evidence limitations |
| `evidence` | array | Referenced evidence ledger |

The real minimal 0.8 fixture contains:

```json
{
  "resolution": {"status": "unknown", "scenarios": []},
  "staged_resolution": {
    "execution_state": "skipped",
    "status": "unknown",
    "provider": null,
    "stages": [],
    "blocker_registry": [],
    "source_impact": [],
    "stop_reason": "stage_target_provider_unavailable",
    "evidence": []
  },
  "blockers": [],
  "source_inventory": [],
  "source_impact": [],
  "framework_findings": [],
  "tests": [],
  "uncertainties": [],
  "evidence": []
}
```

This excerpt omits other required properties for readability. It demonstrates that explicit empty arrays and null/unknown values are meaningful data, not missing fields.

## Metadata

`metadata.schema_version` is exactly `0.8`. Tool name is exactly `php-upgrade-preflight`. Tool version matches the v0.3.x pattern with optional prerelease/build identifiers.

Consumer rule:

```text
if schema_version == "0.8": use the 0.8 parser
else: use a separate historical parser or reject explicitly
```

Never reinterpret a 0.7 report by inserting guessed 0.8 values.

## Request summary

Required fields:

```text
project_path
targets
from_php
target_php
source_paths
frameworks
format
output_path
target_platform_profile
composer_execution
```

`targets` has at least one normalized target. Nullable values remain explicit. `target_platform_profile` contains only safe schema/completeness/digest/provenance metadata and never the local input path.

Request Composer execution omits an exact executable path. It records requested mode, selection style, expected version, timeouts, environment mode, and network policy.

## Project state

`project_state` summarizes sanitized path, nullable Composer platform PHP, normalized root requirements, and indexed locked-package count.

Malformed or unreadable lock entries are skipped with explicit uncertainty. Counts and changes exclude skipped entries rather than pretending they were valid.

## Platform provenance

`platform` contains:

```text
analyzer
current_php
target_php
extensions
profile
```

This separates analyzer-host PHP, source-project PHP, and exact target PHP. Host installability is not target runtime compatibility.

### Extensions

`extensions.completeness` is `none`, `partial`, or `complete`.

- `none`: no explicit assumptions; values come from analyzer runtime.
- `partial`: decisions exist; unlisted values remain analyzer-runtime dependent.
- `complete`: closed world for supported safely simulated classes; unmodeled provenance is null.

Assumptions record name, state, nullable version, and provenance. Presence-only requests report null version; the internal sentinel is never published as an exact target version.

### Profile

`platform.profile` is null or adds:

- profile schema and completeness;
- canonical SHA-256 and safe `php_api`/`file` provenance;
- supported classes and `closed_world`;
- sorted toolchain-bound names;
- sorted effective decisions.

Decision fields are `name`, `class`, `state`, nullable `version`, `provenance`, and `simulation`. Executable-bound Composer packages remain `toolchain_bound`.

## Composer execution

Top-level `composer_execution` adds observed facts:

```text
mode
composer_version
expected_version
version_matches_expectation
executable_selection
scenario_timeout_seconds
diagnostic_timeout_seconds
environment_mode
network_policy
repository_source_mode
composer_home
global_configuration_inherited
credentials_may_be_inherited
offline_requested
scripts_enabled
plugins_enabled
installation_enabled
audit_enabled
interaction_enabled
progress_enabled
process_os_isolation
```

Exact executable paths and environment values are not serialized. `process_os_isolation: false` makes clear that restricted mode is not an OS sandbox.

## Direct resolution

`resolution.status` enum is exactly:

```text
feasible
feasible_with_changes
blocked
unknown
```

It is not `ok`. It describes direct final-target Composer scenarios only.

### Scenario result

Each scenario records:

- name and nullable Composer version;
- command array and duration;
- nullable process exit code;
- `succeeded` and structured `outcome`;
- nullable failure type and bounded excerpts;
- nullable candidate lock;
- diagnostics and nullable debug path.

Outcome enum:

```text
success
solver_failure
validation_failure
composer_missing
repository_metadata_unavailable
timeout
invalid_json
lockfile_missing
process_failure
cleanup_failure
workspace_failure
```

Do not derive outcome from an exit code. A diagnostic probe can execute successfully and return non-zero because it found the relation it was asked to detect.

Candidate-lock hashes/counts describe the lock Composer produced. Raw hashes may vary with Composer/workspace details.

## Staged resolution

Required fields:

```text
execution_state
status
provider
stages
blocker_registry
source_impact
stop_reason
budgets
evidence
```

Execution state is `evaluated` or `skipped`. Status uses the same four-value feasibility enum. Read execution state first: skipped/unknown is not a Composer blocker.

### Budgets

Current canonical values reported by schema 0.8 include:

| Field | Value |
| --- | ---: |
| `max_hops` | 6 |
| `max_attempts_per_stage` | 3 |
| `max_scenarios` | 18 |
| `max_composer_processes` | 128 |
| `stage_timeout_seconds` | 900 |
| `aggregate_timeout_seconds` | 1800 |
| `memory_bytes` | 268435456 |
| `json_report_bytes` | 524288 |
| `markdown_report_bytes` | 262144 |

`scenario_timeout_seconds` reflects the request. Consumers should read the object, not hard-code this table.

### Stage

A stage records identity, exact targets/PHP, platform/execution hashes, timing, attempts, selected attempt, package changes, blocker IDs, source snapshot/findings/impact IDs, risk, effort, actions, tests, and evidence.

Real demo fields:

```json
{
  "id": "laravel-10-to-11",
  "execution_state": "evaluated",
  "resolution_status": "feasible_with_changes",
  "analysis_php": "8.3.0",
  "selected_attempt": 3,
  "source_snapshot": "original_project"
}
```

`resolution_status` can be null for allowed unevaluated contexts. A selectable transition needs selected attempt and output state.

### Attempts and state continuity

Attempts record number, strategy, analyzer-only root changes, scenario, input state, nullable output state, blocker IDs, evidence, and selected flag.

State fingerprints contain:

```text
manifest_sha256
lock_sha256
platform_sha256
execution_policy_sha256
state_sha256
```

One selected stage output must match the next stage input. Fingerprints sanitize path-bearing content so identity is content-based, not directory-based.

### Blocker registry

The registry is always an ordered array. Entries carry stable identity, stage, first attempt/scenario, category, subject, constraints, dependency path, confidence, evidence, current lifecycle, history, and observations.

Lifecycle enum:

```text
detected
persists
resolved
superseded
```

Do not merge blockers across stages only because summaries look similar.

## Transition

`transition` contains:

- direct selected `package_changes`;
- requested `root_constraint_changes`;
- adapter `framework_guidance`.

Direct changes must not be overwritten with staged changes. Framework status is `supported`, `partially_supported`, or `unsupported`; it is independent of direct feasibility.

## Direct blockers

Top-level blockers describe direct final-target conflicts. They contain blocker vocabulary, subject, optional package/version/constraint/path details, options, summary, confidence, and evidence.

Real demo excerpt:

```json
{
  "type": "replace-provide-conflict",
  "subject": "laravel/framework",
  "requested_constraint": "^13.0",
  "blocker": "phpunit/phpunit",
  "locked_version": "10.0.0",
  "conflict": ">=11.0.0",
  "summary": "Composer found conflicting replace, provide, or conflict rules.",
  "confidence": "high",
  "evidence": ["solver-1", "solver-2", "solver-3", "solver-4"]
}
```

Consumers must not invent missing attribution.

## Source inventory

Each raw usage has file, symbol, usage type, line, and evidence. Source paths are project-relative. Inventory is observation, not an action list.

## Source impact

Each actionable direct finding requires stable ID, stage IDs, nullable affected package, ownership, relevance, reason, severity, occurrences, and evidence.

Ownership can be `exact`, `ambiguous`, or `unknown`. Null affected package is deliberate uncertainty.

Staged impact is separately de-duplicated under `staged_resolution.source_impact`; stages refer to IDs from that registry. Never overwrite direct impact with staged impact.

## Framework findings

Findings require framework, severity, summary, applicable hops, and evidence. Hop scope prevents guidance from crossing a gap.

Severity and confidence both use `low`, `medium`, `high`, but severity means impact while confidence means evidence strength.

## Plan

`plan.stages[]` contains nullable stage ID, name, summary, nonempty actions, and evidence. Consumers stop at the first stage that is blocked, unknown, skipped, missing, or lacks selectable output.

## Risk and effort

Risk has `level` and `drivers`. Level is low, medium, or high.

Effort requires:

```text
range_hours: [minimum, maximum]
confidence
components: component -> [minimum, maximum]
assumptions
```

Hour bounds are non-negative integers. This is planning support, not a probability or quote.

## Tests

Every test has name, purpose, nullable command, and priority (`required` or `recommended`). Null command means known work whose executable command was not discovered. Stage tests add stable `stage_id`.

## Uncertainties

`uncertainties` is a unique array of nonempty strings. Preserve it. An empty finding array plus a nonempty uncertainty array is not a clean result.

## Evidence ledger

Every evidence record requires:

```text
id
class: E1 | E2 | E3 | E4 | E5
summary
confidence: low | medium | high
context: object
```

Evidence-reference arrays contain unique stable IDs. Transformations must preserve referential integrity.

## Path/privacy contract

Default reports use:

| Marker | Hidden root |
| --- | --- |
| `[PROJECT_ROOT]` | Project |
| `[REPORT_OUTPUT]` | Destination |
| `[LOCAL_REPOSITORY]` | Local repository |
| `[ANALYZER_WORKSPACE]` | Temporary workspace |

Debug may expose exact temporary paths and is non-shareable. Redaction does not sanitize retained debug files or prevent credentials from being used during Composer execution.

## Migrating from 0.7

Schema 0.8 adds required:

- top-level `composer_execution`;
- top-level `staged_resolution`;
- request Composer policy;
- nullable request/platform profile fields;
- diagnostic `outcome`.

It preserves meanings of direct resolution, direct package changes, framework guidance, source inventory, and direct source impact.

Migration order:

1. Dispatch by schema version.
2. Keep 0.7 direct reads intact.
3. Read 0.8 staged execution state before status.
4. Keep direct/staged changes and impact separate.
5. Validate state continuity.
6. Read profile digest, completeness, closed-world flag, and decisions.
7. Stop at the first non-selectable plan stage.

Field absence in 0.7 is not equivalent to 0.8 null.

## Migrating from 0.6

Schema 0.7 moved raw observations from `source_impact` to `source_inventory` and redefined `source_impact` as grouped actionable findings. It added platform provenance and framework guidance.

A multi-version consumer needs distinct 0.6, 0.7, and 0.8 paths. Historical reports are not rewritten.

## Validation

Use a Draft 2020-12 validator against the packaged schema. Repository tests use `opis/json-schema`:

```bash
composer test:core -- --filter UpgradeReportSchemaTest
```

Lightweight guard before full validation:

```bash
jq -e '.metadata.schema_version == "0.8" and (.resolution.status | IN("feasible", "feasible_with_changes", "blocked", "unknown"))' report.json
```

PowerShell guard (not full schema validation):

```powershell
$report = Get-Content -Raw .\report.json | ConvertFrom-Json
$allowed = @('feasible', 'feasible_with_changes', 'blocked', 'unknown')
if ($report.metadata.schema_version -ne '0.8' -or $report.resolution.status -notin $allowed) {
    throw 'Unsupported report contract.'
}
```

## Consumer invariants

- Dispatch on schema version, never tool version.
- Preserve valid array content across patch releases.
- Do not infer report status from process exit code.
- Do not map framework coverage to Composer feasibility.
- Do not merge direct and staged changes or blockers.
- Do not treat inventory as actionable impact.
- Do not infer unknown ownership.
- Do not advance without selected state continuity.
- Do not ignore uncertainties.
- Preserve evidence references and targets.
- Treat JSON as canonical and Markdown as projection.

## Related pages

- [[Reading the Report|Reading-the-Report]]
- [[Key Concepts|Key-Concepts]]
- [[Determinism and Evidence|Determinism-and-Evidence]]
- [[Safety and Trust Boundaries|Safety-and-Trust-Boundaries]]
- [[CLI Reference|CLI-Reference]]
