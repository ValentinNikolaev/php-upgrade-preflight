# Safety and Trust Boundaries

PHP Upgrade Preflight is a read-only planning analyzer, not an upgrade executor or a security sandbox. This page defines what it protects, what it deliberately does not promise, and how to run it safely.

## Trust statement for a technical manager

The tool can provide reviewable evidence about Composer resolution, candidate dependency changes, selected static source references, Laravel migration guidance, uncertainty, risk, and effort. It cannot certify runtime behavior or production readiness.

Treat its report as input to an upgrade plan. Approval still requires a real branch, dependency installation, application tests, security review, and deployment validation on the target environment.

## What read-only means

The analysis pipeline:

- resolves the project path;
- reads `composer.json`, `composer.lock`, and selected source;
- copies manifests into analyzer-owned temporary workspaces;
- runs Composer only in those workspaces;
- disables Composer scripts and plugins;
- writes a report only to stdout or a validated path outside the project;
- cleans temporary workspaces unless debug mode preserves them.

It does not edit the target manifest, lock file, source, or installed dependencies.

### Actions outside that promise

These actions can change the project before or outside analysis:

- `composer require --dev ...` installs the analyzer locally and changes manifest/lock state;
- shell redirection opens its destination before the analyzer can validate it;
- your own wrapper scripts may create logs or temporary files;
- `--debug` intentionally preserves analyzer workspaces.

For byte-for-byte audits, install the analyzer in a separate tools directory and use `--output` outside the target.

## Safe output handling

Good Linux layout:

```bash
/work/apps/legacy-app
/work/upgrade-reports/legacy-app.json
/work/php-upgrade-tools/vendor/bin/upgrade-intel
```

Good Windows layout:

```text
C:\work\apps\legacy-app
C:\work\upgrade-reports\legacy-app.json
C:\work\php-upgrade-tools\vendor\bin\upgrade-intel.bat
```

The output parent must exist and be writable. The destination must not be the project itself, a directory, or any file below the project after path resolution. Symlink/junction resolution is considered by destination validation.

Avoid this:

```bash
vendor/bin/upgrade-intel analyze --path=/work/app --target-php=8.2 > /work/app/report.json
```

The shell can create `/work/app/report.json` before PHP starts. Prefer:

```bash
mkdir -p /work/reports
vendor/bin/upgrade-intel analyze --path=/work/app --target-php=8.2 --output=/work/reports/app.json
```

## Temporary workspaces and debug mode

Default mode cleans analyzer-owned workspaces. Canonical reports replace exact temporary roots with `[ANALYZER_WORKSPACE]`.

If cleanup fails, the report records `cleanup_failure` without revealing the real path. Use `--debug` only when an authorized developer needs that exact path for diagnosis.

Debug mode:

- preserves workspaces by design;
- exposes exact `temp_path` values;
- leaves copied Composer manifests on disk;
- makes the report and workspace non-shareable.

Credential redaction still applies to output in debug mode, but it does not rewrite files retained in the workspace.

## Path privacy

Default JSON and Markdown replace absolute roots with stable markers:

| Marker | Meaning |
| --- | --- |
| `[PROJECT_ROOT]` | Analyzed project root |
| `[REPORT_OUTPUT]` | Chosen report destination |
| `[LOCAL_REPOSITORY]` | Resolved local Composer repository root |
| `[ANALYZER_WORKSPACE]` | Temporary analyzer root |

Reported source paths remain project-relative. Exact paths are still used internally for filesystem access.

This makes Windows and Unix reports more comparable, but measured durations, Composer-produced lock metadata, and candidate lock hashes can still differ between runs or Composer versions.

## Compatible Composer mode

`--composer-mode=compatible` is the default. It preserves the environment needed by many real projects:

- global Composer config and auth;
- Composer cache;
- proxy variables;
- Git and SSH configuration;
- network access;
- repository credentials available to the analyzer process.

This mode is operationally convenient but host-dependent. A successful result is not proof that another machine with different credentials, cache, repositories, or Composer version will obtain the same dependency solution.

Use short-lived, read-only credentials where possible.

## Restricted Composer mode

`--composer-mode=restricted` creates fresh analyzer-owned Composer home, cache, and XDG directories, writes empty config/auth files, sets empty `COMPOSER_AUTH`, scrubs controlled proxy and askpass variables, disables prompts, and requests Composer's best-effort offline behavior.

It does **not** provide:

- an OS firewall;
- process isolation;
- a guarantee that helper executables cannot access the network;
- removal of repository URLs or credentials embedded in project `composer.json`;
- isolation from system trust stores;
- a guarantee that a user-selected executable is benign.

If a restricted fresh cache lacks repository metadata, the correct outcome is operational uncertainty: `repository_metadata_unavailable`. That is not evidence that packages conflict.

For untrusted projects, run the analyzer inside a disposable container or restricted account with independently enforced network and filesystem controls.

## Composer side effects

Both modes disable scripts, plugins, package installation, audit, interaction, and progress output. This reduces side effects but can also change resolution compared with a project's normal Composer workflow.

A project that relies on plugin behavior may therefore receive incomplete or different evidence. The report must be read with that limitation.

## Credentials and redaction

Report fields, bounded Composer stdout/stderr excerpts, diagnostics, and command failure messages pass through deterministic redaction. Known credential-bearing URLs, authorization values, common tokens, and named credential fields are replaced with markers.

Redaction is a publication boundary, not prevention:

- Composer may already have read credentials before output is redacted;
- network requests may already have occurred;
- a retained debug workspace may contain sensitive input;
- pattern matching cannot recognize every future secret format;
- deliberate bounding may remove context needed for diagnosis.

Before sharing a report:

1. Confirm `--debug` was not used.
2. Search for organization-specific token formats and private hostnames.
3. Confirm exact local paths are represented by stable markers.
4. Verify the report does not include proprietary source snippets beyond approved metadata.
5. Share with the minimum necessary audience.

If an unredacted credential appears, stop distribution, revoke or rotate it, and use the private security-reporting channel with a synthetic reproduction.

## Bounded Composer output

Composer excerpts are intentionally bounded and redacted. A shortened excerpt ends with a marker such as:

```text
[TRUNCATED: N bytes of output omitted]
```

If redaction itself fails, the value is withheld under `[REDACTION_FAILED]`. Excerpts are supporting evidence, not always the entire diagnostic transcript.

## Platform modeling boundary

### Host installability

Host installability asks whether the analyzer and adapter can execute in the current Composer project. The packages require PHP `^8.0`; a project-local Laravel adapter also has to satisfy the installed Laravel/Illuminate constraints.

### Target platform

Target modeling asks Composer to reason about a desired exact PHP and platform package set in temporary manifests. It is controlled by:

- `--target-php`;
- `--with-extension` and `--without-extension`;
- `--target-platform-profile`;
- lower-priority original `config.platform`;
- host values for anything still unmodeled.

These inputs do not alter the analyzer interpreter and do not prove the real deployment environment matches the model.

### Runtime compatibility

Runtime compatibility asks whether the changed application actually works. The analyzer does not boot the target, execute its tests, call external services, validate data migrations, or exercise production traffic.

The three layers must not be collapsed into one “compatible” label.

## Partial and complete platform profiles

A partial profile makes only listed decisions deterministic. Unlisted supported values may come from the analyzer host and are labeled accordingly.

A complete profile is closed-world only for supported safely simulated platform-package classes. Unlisted values in those classes are modeled absent. It still does not pin:

- repository metadata;
- downloads or network behavior;
- credentials;
- Composer executable behavior;
- toolchain-bound platform packages;
- application runtime behavior.

Complete profiles and explicit absences require Composer 2.2+. On Composer 2.0 or 2.1, affected analysis stops as unknown before workspace creation rather than weakening the request.

Never call a profile complete unless the deployment owner has inventoried the real platform. `composer show --platform` is useful inventory input, but its output is not directly the profile schema.

## Source-analysis limits

The scanner parses PHP syntax statically. It does not execute code, resolve service-container bindings, evaluate dynamic class names, or infer string-built symbols.

It can miss or downgrade confidence for:

- parse errors;
- `eval` and runtime-generated declarations;
- `class_alias` and dynamic autoloaders;
- missing or unsupported autoload metadata;
- custom installer paths;
- classmap/files inventories beyond the deterministic safety limit;
- dependencies' `autoload-dev` data;
- symbols whose ownership is ambiguous.

`source_inventory` is observation, not a change list. Only correlated items enter actionable `source_impact`. Absence of a finding is not proof that no source change is needed.

Staged findings are always projected from the original source snapshot. The analyzer does not simulate source edits between stages.

## Framework-guidance limits

Laravel guidance coverage is independent of Composer feasibility. A rule pack can be `supported` while direct or staged resolution is blocked. Conversely, Composer may resolve a target for which safe migration guidance is partial or unsupported.

Encoded package ranges, maintainer links, and skeleton patterns identify review work. They do not replace official upgrade guides. Skeleton findings are low-confidence comparison points, not confirmed incompatibilities.

An adapter rule failure is contained and recorded as uncertainty so the report can still be produced. Therefore “no findings” must always be read alongside `uncertainties`.

## Exit status boundary

Process exit code 0 means the command produced a valid report. It includes reports whose direct resolution is `blocked` or `unknown`.

Process codes 1 and 2 mean no valid analysis report was produced. They are operational/interface results, not Composer solver results.

Within a valid report, read independently:

- `resolution.status` for direct final-target Composer feasibility;
- `transition.framework_guidance[].status` for adapter coverage;
- `staged_resolution.execution_state` and `.status` for adjacent-stage evidence;
- `uncertainties` for evidence gaps;
- `tests` for required validation outside the analyzer.

## Untrusted-project checklist

- [ ] Use a disposable container or restricted account.
- [ ] Enforce network policy outside Composer if required.
- [ ] Use scoped, short-lived credentials or none.
- [ ] Inspect `composer.json` for embedded repository credentials.
- [ ] Prefer restricted mode when offline metadata is sufficient.
- [ ] Keep `--debug` off unless workspace retention is authorized.
- [ ] Place output outside the project.
- [ ] Review the report for secrets and proprietary data before sharing.
- [ ] Destroy disposable environments according to your organization's policy.

## What the analyzer never proves

- that an upgrade has been performed;
- that a candidate lock should be committed unchanged;
- that application tests pass;
- that production data migrations are safe;
- that private integrations still work;
- that the deployment image matches the modeled platform;
- that a `feasible` result is ready to release;
- that an empty finding list means no work exists.

## Suggested review gates

Use separate gates instead of one overloaded “pass/fail” check:

| Gate | Evidence to review | Owner |
| --- | --- | --- |
| Command integrity | Process exit code, schema version, complete report file | CI or tooling owner |
| Dependency feasibility | Direct and staged statuses, scenarios, blockers, candidate changes | PHP developer |
| Guidance coverage | Framework status, hop list, findings, maintainer links | Framework specialist |
| Platform fidelity | Profile digest, completeness, extension decisions, host dependence | Platform or DevOps owner |
| Source work | Source impact, confidence, original-snapshot limitation | Application developer |
| Operational uncertainty | `uncertainties`, timeouts, repository metadata, contained failures | Technical lead |
| Runtime acceptance | Real install, application tests, smoke tests, deployment checks | Delivery team |

A green command-integrity gate says only that a usable report exists. A green dependency gate says only that Composer produced a candidate under recorded inputs. Production approval belongs to the runtime acceptance gate.

### Retention guidance

Canonical non-debug JSON is the best audit artifact because it preserves evidence IDs and machine-readable provenance. Store it according to the project's confidentiality policy. Markdown is convenient for review, but it is a projection and should not replace canonical JSON in an automated evidence trail.

Do not retain debug workspaces by default. When one is needed for an incident, record who authorized retention, where it is stored, and when it must be removed. A copied manifest can reveal private repository definitions even when the rendered report redacts output.

## Related pages

- [[Getting Started|Getting-Started]]
- [[CLI Reference|CLI-Reference]]
- [[Troubleshooting and FAQ|Troubleshooting-and-FAQ]]
- [[Determinism and Evidence|Determinism-and-Evidence]]
- [[Report Schema|Report-Schema]]
