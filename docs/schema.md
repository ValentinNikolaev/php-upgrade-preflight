# JSON schema and compatibility

JSON reports use a versioned consumer contract. Tool releases and schema releases move independently:

```json
{
  "metadata": {
    "schema_version": "0.7",
    "tool": {
      "name": "php-upgrade-preflight",
      "version": "0.2.0-dev"
    }
  }
}
```

Select a parser or validator through `metadata.schema_version`. Do not infer the report shape from `metadata.tool.version`.

## Current contract

The current strict Draft 2020-12 schema is [`upgrade-report-v0.7.schema.json`](../packages/core/resources/schema/upgrade-report-v0.7.schema.json). It rejects unknown properties and defines scenario outcomes, structured blockers, platform provenance, package changes, framework-transition guidance, source inventory, actionable source impact, risk, effort, and uncertainties.

Historical schemas remain in the same directory for consumers that store older reports:

- [v0.6](../packages/core/resources/schema/upgrade-report-v0.6.schema.json)
- [v0.5](../packages/core/resources/schema/upgrade-report-v0.5.schema.json)
- [v0.4](../packages/core/resources/schema/upgrade-report-v0.4.schema.json)
- [v0.3](../packages/core/resources/schema/upgrade-report-v0.3.schema.json)
- [v0.2](../packages/core/resources/schema/upgrade-report-v0.2.schema.json)

## Compatibility policy

Patch releases may correct findings, scenario selection, evidence, or wording while retaining their schema version. Consumers must tolerate different array contents that still validate against that schema.

A report-shape change requires a new schema version and a new schema file. Existing schema files remain immutable. The project keeps canonical schema snapshots and six full fixture report snapshots under test.

Markdown has no independent contract. It projects the canonical report for human review and may change its presentation in a patch release.

Composer `stdout_excerpt` and `stderr_excerpt` values are bounded and redacted before they enter the canonical model. Stable markers such as `[REDACTED]`, `[REDACTED_TOKEN]`, and `[REDACTED_URL]` replace sensitive values without changing the schema shape.

## Path exposure and report privacy

Default canonical JSON and Markdown replace absolute local roots with stable markers:

| Marker | Meaning |
| --- | --- |
| `[PROJECT_ROOT]` | Analyzed project root. |
| `[REPORT_OUTPUT]` | Report destination root. |
| `[LOCAL_REPOSITORY]` | Local Composer repository root. |
| `[ANALYZER_WORKSPACE]` | Analyzer-owned temporary workspace root. |

The analyzer still uses exact project and source paths internally, while source file locations in reports remain project-relative. Exact `temp_path` values are exposed only when `--debug` is explicit; those debug reports and retained workspaces are non-shareable artifacts. In default mode, cleanup failures expose only `[ANALYZER_WORKSPACE]`. Credential redaction remains active in every mode, including debug.

## Migrating from 0.6 to 0.7

Dispatch on `metadata.schema_version`; do not infer shape from the tool version. The migration is structural:

| 0.6 | 0.7 |
| --- | --- |
| `source_impact[]` was raw parser inventory | Raw observations move to `source_inventory[]` |
| No actionable source object | `source_impact[]` contains relevance, reason, severity, ownership, affected package, exact occurrences, and evidence |
| PHP values were spread across request/project fields | `platform` records analyzer, current, target, and extension provenance, including extension-model completeness and the source of unmodeled values |
| No framework coverage status | `transition.framework_guidance[]` records support and ordered hops separately from Composer resolution; findings add `applies_to_hops` |

Continue reading `request_summary` and `project_state` for the user's request and original Composer inputs. Use `platform` when explaining which PHP or extension state influenced analysis. `extensions.completeness: partial` with `provenance: mixed` means only the listed assumptions were explicit and unlisted extensions still came from `extensions.unmodeled_provenance`; it is not a reproducible complete target platform. An `affected_package` of `null` with `ownership: unknown` is deliberate uncertainty, not permission to infer an owner.

Extension assumptions are ordered by Composer extension name. `provenance: request` identifies CLI or Artisan `--with-extension` / `--without-extension` input; `provenance: composer_config` identifies the analyzed manifest's original `config.platform` entry. Request input overrides the same manifest entry, while duplicate request values are rejected. A present assumption with a `null` version models presence only and adds a version-compatibility uncertainty. Solver conflicts caused by its sentinel use the non-blocking `extension-version-unknown` blocker classification; exact modeled version conflicts use the blocking `extension-version-incompatible` classification. Reports never promote unlisted analyzer-host extension state to reproducible target evidence.

Do not map `transition.framework_guidance[].status` onto `resolution.status`. Composer feasibility and framework guidance coverage are independent; [the v0.2 contract](v0.2-contract.md) defines their composition.

## Validate a report

Use any Draft 2020-12 validator. The development suite validates reports with `opis/json-schema`:

```bash
composer test:core -- --filter UpgradeReportSchemaTest
```

Every finding references an ID in the top-level `evidence` collection. Consumers should preserve those references when transforming reports.
