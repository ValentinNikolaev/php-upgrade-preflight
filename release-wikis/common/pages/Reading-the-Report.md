# Reading the Report

This page gives a practical reading order for schema 0.8 reports. It serves the developer who must act on details and the technical manager who must understand scope, confidence, and stop conditions.

JSON is canonical. Markdown is a human-readable projection of the same `UpgradeReport`; it does not run a second analysis.

## The five-minute reading order

1. Confirm `metadata.schema_version` and tool version.
2. Verify that request and platform modeling match team intent.
3. Read direct `resolution.status`.
4. Read framework guidance and staged resolution independently.
5. Review blockers, actionable source impact, risk, effort, tests, and uncertainties.
6. Trace decision-critical claims through evidence IDs.

Do not begin with the process exit code. Exit code 0 says a report was produced; it does not say the upgrade is feasible.

## Running example

The repository's checked-in five-minute demo analyzes Laravel 10→13. This real excerpt is from `examples/five-minute-demo/reports/laravel-10-to-13.json`:

```json
{
  "metadata": {
    "schema_version": "0.8",
    "tool": {
      "name": "php-upgrade-preflight",
      "version": "0.3.1"
    }
  }
}
```

Its command exits 0 because it writes a valid report. The report itself says:

```json
{
  "resolution": {"status": "blocked"},
  "staged_resolution": {
    "execution_state": "evaluated",
    "status": "blocked",
    "provider": "laravel"
  }
}
```

That is successful analysis of an upgrade that is not currently feasible.

## Step 1: identify the contract

Dispatch a parser by `metadata.schema_version`, never by tool version.

```powershell
$report = Get-Content -Raw C:\work\reports\app.json | ConvertFrom-Json
if ($report.metadata.schema_version -ne '0.8') {
    throw "Unsupported report schema: $($report.metadata.schema_version)"
}
```

```bash
schema="$(jq -r '.metadata.schema_version' /work/reports/app.json)"
test "$schema" = 0.8 || { echo "Unsupported schema: $schema" >&2; exit 1; }
```

Patch releases can correct findings, evidence, scenario selection, or wording while preserving schema 0.8. Consumers must tolerate different valid array contents.

## Step 2: verify the request

`request_summary` records normalized input, not facts discovered later. Check:

- `project_path` uses `[PROJECT_ROOT]` in a shareable report;
- `targets` contains every intended package and PHP target;
- `from_php` represents current PHP evidence;
- `target_php` is the desired exact simulation value;
- `source_paths` and `frameworks` are expected;
- `format` and `output_path` are expected;
- `target_platform_profile` has the intended digest/completeness or is null;
- request-level `composer_execution` reflects the chosen policy.

Real demo excerpt:

```json
{
  "targets": [
    {"package": "laravel/framework", "constraint": "^13.0"},
    {"package": "php", "constraint": "8.3.0"}
  ],
  "from_php": "8.1",
  "target_php": "8.3.0",
  "frameworks": ["laravel"],
  "format": "json",
  "output_path": "[REPORT_OUTPUT]",
  "target_platform_profile": null
}
```

If the request is wrong, stop. A precise report for the wrong target is not useful evidence.

## Step 3: separate host, current, and target PHP

`platform` prevents three values from being confused:

| Field | Question |
| --- | --- |
| `platform.analyzer` | Which PHP executed the analyzer? |
| `platform.current_php` | Which PHP describes the project before upgrade? |
| `platform.target_php` | Which exact PHP did Composer model? |

The analyzer host may run PHP 8.3 while current evidence says 8.1 and target evidence says 8.3. Host installability does not prove target runtime compatibility.

Review `platform.extensions`:

- `completeness: none` means no explicit extension modeling;
- `partial` means named decisions exist and unlisted values remain host-dependent;
- `complete` means supported safely simulated unlisted classes are modeled absent;
- `unmodeled_provenance` says where remaining values came from;
- `assumptions[]` identifies each effective decision and provenance.

A non-null `platform.profile` adds canonical digest, `closed_world`, supported classes, toolchain-bound names, and normalized `effective[]` decisions. A complete profile narrows platform dependence; it does not pin repositories, credentials, network, or Composer executable behavior.

## Step 4: inspect Composer execution provenance

Top-level `composer_execution` reports what governed scenarios:

- `mode`: compatible or restricted;
- detected Composer version and expectation match;
- scenario and diagnostic timeouts;
- environment and network policy;
- repository source and Composer home policy;
- global configuration and possible credential inheritance;
- requested offline behavior;
- disabled scripts, plugins, installation, audit, interaction, and progress;
- `process_os_isolation`, false because the tool supplies no OS sandbox.

Real demo excerpt:

```json
{
  "mode": "restricted",
  "composer_version": "2.10.2",
  "expected_version": ">=2.0.0 <3.0.0",
  "version_matches_expectation": true,
  "offline_requested": true,
  "scripts_enabled": false,
  "plugins_enabled": false,
  "installation_enabled": false,
  "process_os_isolation": false
}
```

Restricted mode is not a firewall. `repository_metadata_unavailable` is operational uncertainty, not a dependency conflict.

## Step 5: read direct resolution

`resolution` answers only: “Could the requested final target resolve in determining Composer scenarios?”

| Status | Interpretation | Next action |
| --- | --- | --- |
| `feasible` | A final-target scenario succeeded without package changes | Review platform, source, guidance, and tests |
| `feasible_with_changes` | A final-target scenario succeeded with candidate package changes | Review the diff and reproduce it in a branch |
| `blocked` | Reproducible blockers prevent the requested target | Read blockers and scenario evidence |
| `unknown` | No reliable conclusion was possible | Fix evidence/operational gaps and rerun |

The direct status is never `ok` in schema 0.8.

### Read scenarios, not only the summary

Each scenario includes name, Composer version, safe command array, duration, process exit code, `succeeded`, structured `outcome`, optional failure type, bounded redacted excerpts, optional candidate lock, diagnostics, and optional debug path.

Scenario/diagnostic outcome vocabulary:

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

A diagnostic probe can have a non-zero Composer exit code and `outcome: success`: it ran successfully and its non-zero result can be the relationship evidence it was meant to capture. Prefer `outcome` over raw numeric interpretation.

## Step 6: read framework guidance separately

`transition.framework_guidance[]` describes adapter rule coverage, not Composer feasibility.

Real demo excerpt, with evidence arrays omitted only for readability:

```json
{
  "framework": "laravel",
  "source_major": 10,
  "target_major": 13,
  "status": "supported",
  "hops": [
    {"from_major": 10, "to_major": 11, "status": "supported", "rule_pack": "laravel-10-to-11"},
    {"from_major": 11, "to_major": 12, "status": "supported", "rule_pack": "laravel-11-to-12"},
    {"from_major": 12, "to_major": 13, "status": "supported", "rule_pack": "laravel-12-to-13"}
  ],
  "uncertainties": []
}
```

`supported`, `partially_supported`, and `unsupported` describe coverage. They cannot upgrade a blocked Composer result or downgrade a feasible one.

## Step 7: read staged resolution

First read `staged_resolution.execution_state`:

- `evaluated`: staged execution occurred;
- `skipped`: no staged Composer conclusion was executed; read `stop_reason`.

Then read aggregate `status`: `feasible`, `feasible_with_changes`, `blocked`, or `unknown`.

Each stage includes:

- stable ID and framework majors;
- execution state and nullable resolution status;
- exact targets and analysis PHP;
- platform and Composer execution digests;
- duration and evidence;
- input state, attempts, and optional selected output;
- package changes and blocker references;
- original-snapshot source findings and staged impact IDs;
- stage risk, effort, actions, and tests.

Faithful selected fields from the demo chain:

```json
[
  {"id": "laravel-10-to-11", "resolution_status": "feasible_with_changes", "selected_attempt": 3},
  {"id": "laravel-11-to-12", "resolution_status": "feasible_with_changes", "selected_attempt": 1},
  {"id": "laravel-12-to-13", "resolution_status": "blocked", "selected_attempt": null}
]
```

Only a stage with selected attempt and output state can feed the next stage. Stop at the first blocked, unknown, skipped, or missing stage.

### Candidate-state continuity

Verify that the selected output fingerprint of one stage equals the next stage's input fingerprint. A state fingerprint covers sanitized manifest, lock, platform, and execution policy identity. It identifies content, not analysis directory.

Raw `candidate_lock.sha256` and Composer `content_hash` are workspace-local Composer output; do not confuse them with path-normalized stage fingerprints.

## Step 8: inspect blockers and lifecycle

Top-level `blockers[]` belongs to direct final-target analysis. A blocker records type, subject, requested constraint, blocking package/version or conflict, dependency path, options, summary, confidence, and evidence.

Real shortened demo blocker; every shown value is exact:

```json
{
  "type": "replace-provide-conflict",
  "subject": "laravel/framework",
  "requested_constraint": "^13.0",
  "blocker": "nunomaduro/collision",
  "locked_version": "7.11.0",
  "conflict": ">=11.0.0",
  "dependency_path": ["nunomaduro/collision", "laravel/framework"],
  "confidence": "high",
  "evidence": ["solver-1", "solver-2", "solver-3", "solver-4"]
}
```

`staged_resolution.blocker_registry[]` tracks identity and lifecycle across attempts. Lifecycle values are `detected`, `persists`, `resolved`, and `superseded`. One blocker can resolve while another persists.

## Step 9: distinguish inventory and impact

`source_inventory[]` is raw static observation. `source_impact[]` is narrower actionable correlation.

Real inventory item:

```json
{
  "file": "tests/Feature/LegacyCsrfTest.php",
  "symbol": "Illuminate\\Foundation\\Http\\Middleware\\VerifyCsrfToken",
  "usage_type": "middleware_reference",
  "line": 13,
  "evidence": ["source-3"]
}
```

Real actionable impact:

```json
{
  "id": "source-impact-967745ebc2016f78d1c2",
  "stage_ids": [],
  "affected_package": null,
  "ownership": "unknown",
  "relevance": "framework_rule",
  "reason": "Referenced by active laravel compatibility guidance; package ownership has not been established.",
  "severity": "high",
  "occurrences": [
    {
      "file": "tests/Feature/LegacyCsrfTest.php",
      "symbol": "Illuminate\\Foundation\\Http\\Middleware\\VerifyCsrfToken",
      "usage_type": "middleware_reference",
      "line": 13,
      "evidence": ["source-3"]
    }
  ],
  "evidence": ["source-3", "laravel-request-forgery-guidance-1"]
}
```

Unknown ownership is not permission to guess. Staged impact uses its own registry under `staged_resolution.source_impact`; stages reference those IDs. Every stage reads the original source snapshot, not simulated earlier edits.

## Step 10: read framework findings in hop scope

`framework_findings[]` includes framework, severity, summary, applicable hops, and evidence. Advice applying only to 12→13 must not be presented as a 10→11 task.

```json
{
  "framework": "laravel",
  "severity": "high",
  "summary": "Replace 1 detected direct reference to VerifyCsrfToken or ValidateCsrfToken with PreventRequestForgery before targeting Laravel 13.",
  "applies_to_hops": [{"from_major": 12, "to_major": 13}],
  "evidence": ["laravel-request-forgery-guidance-1", "source-3"]
}
```

This is review guidance. The analyzer does not perform the replacement.

## Step 11: turn summaries into work

`plan.stages[]` supplies ordered actions and evidence. The demo's first two summaries say to apply only selected candidates and validate before advancing. The final stage says to stop because its transition is not proved.

`risk` contains a level and drivers; it is not a probability. Real demo excerpt:

```json
{
  "risk": {
    "level": "high",
    "drivers": [
      "Composer resolution is blocked.",
      "Framework compatibility findings require review.",
      "Weighted actionable source findings require review.",
      "Executed stage laravel-12-to-13 retains an active Composer blocker."
    ]
  },
  "effort": {
    "range_hours": [6, 32],
    "confidence": "low"
  }
}
```

The full effort object has component ranges and assumptions. It is planning support, not a quote.

`tests[]` names purpose, nullable command, and required/recommended priority. Null command means validation is needed but the project command was not identified.

## Step 12: read uncertainties before approval

Uncertainty is a first-class result. The demo says:

- dependency resolution does not prove runtime compatibility;
- no Composer test script was found;
- unlisted extensions came from analyzer runtime;
- restricted Composer mode is not process or OS isolation.

An empty findings array alongside a contained adapter or parse uncertainty is not a clean bill of health.

## Step 13: trace evidence

Decision-bearing objects carry IDs into top-level `evidence[]`. Records contain stable ID, class E1–E5, summary, confidence, and structured context.

Real example:

```json
{
  "id": "stage-plan-3",
  "class": "E5",
  "summary": "Generated recommendations from the executed outcome of stage laravel-12-to-13.",
  "confidence": "low",
  "context": {
    "stage_id": "laravel-12-to-13",
    "execution_state": "evaluated",
    "resolution_status": "blocked",
    "transition_recommended": false
  }
}
```

For a critical decision, start at the action or blocker, collect its evidence IDs, and inspect those records plus referenced scenarios.

## Developer checklist

- [ ] Schema 0.8 was recognized.
- [ ] Request and platform match intent.
- [ ] Direct, guidance, and staged outcomes were read separately.
- [ ] Selected candidates will be reproduced in a real branch.
- [ ] Blocker lifecycles and stop reason were reviewed.
- [ ] Source impact was not confused with inventory.
- [ ] Uncertainties and evidence confidence were reviewed.
- [ ] Tests will run on the real target runtime.

## Technical-manager checklist

- [ ] A process code was not presented as upgrade feasibility.
- [ ] Platform fidelity and host dependence are explicit.
- [ ] Blocked/unknown stages are plan stop conditions.
- [ ] Risk drivers and effort assumptions are visible.
- [ ] Missing tests have an owner and action.
- [ ] Runtime acceptance remains a separate gate.

## Related pages

- [[Report Schema|Report-Schema]]
- [[Key Concepts|Key-Concepts]]
- [[Safety and Trust Boundaries|Safety-and-Trust-Boundaries]]
- [[Troubleshooting and FAQ|Troubleshooting-and-FAQ]]
