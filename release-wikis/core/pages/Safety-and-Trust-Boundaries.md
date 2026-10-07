# Safety and Trust Boundaries

PHP Upgrade Preflight reads a project and runs Composer probes in temporary workspaces. It helps plan an upgrade without carrying one out or isolating Composer as a security sandbox. This page explains the boundaries you need to account for when running it and sharing a report.

## Trust statement for a technical manager

The report can show Composer resolution, candidate dependency changes, selected source references, Laravel guidance, uncertainties, risk, and effort. Use that evidence to plan work. A release decision still needs a real upgrade branch, installation, application tests, security review, and validation in the target environment.

## What read-only means

The analysis pipeline:

- resolves the project path
- reads `composer.json`, `composer.lock`, and selected source
- copies manifests into analyzer-owned temporary workspaces
- runs Composer only in those workspaces
- disables Composer scripts and plugins
- writes a report only to stdout or a validated path outside the project
- cleans temporary workspaces unless debug mode preserves them.

The analysis leaves the target manifest, lock file, source, and installed dependencies unchanged.

### Actions outside that promise

Some surrounding commands can still change the project:

- `composer require --dev ...` installs the analyzer locally and changes manifest/lock state
- shell redirection opens its destination before the analyzer can validate it
- your own wrapper scripts may create logs or temporary files
- `--debug` intentionally preserves analyzer workspaces.

If you need to compare project bytes before and after, install the analyzer in a separate tools directory and put `--output` outside the target.

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

The shell can create `/work/app/report.json` before the analyzer can reject that location. Use an external output path:

```bash
mkdir -p /work/reports
vendor/bin/upgrade-intel analyze --path=/work/app --target-php=8.2 --output=/work/reports/app.json
```

Use `--save-report=/work/reports/app.json` when the canonical report must also remain on stdout. It performs the same pre-analysis destination validation and writes the identical rendered bytes. It cannot be combined with `--output`.

Stdout carries the report, apart from the file-only `--output` acknowledgement. Diagnostics and terminal progress use stderr. Progress appears only when stderr is attached to a terminal. Redirecting it keeps the report pipe clean. A failed progress reporter cannot change analysis.

## Temporary workspaces and debug mode

Default mode cleans analyzer-owned workspaces. Canonical reports replace exact temporary roots with `[ANALYZER_WORKSPACE]`.

If cleanup fails, the report records `cleanup_failure` while hiding the exact path. `--debug` retains and exposes the workspace path for an authorized investigation.

Debug mode:

- preserves workspaces by design
- exposes exact `temp_path` values
- leaves copied Composer manifests on disk
- makes the report and workspace non-shareable.

The report still redacts known credentials in debug mode. Files kept in the workspace are copied inputs and are not rewritten by report redaction.

## Path privacy

Default JSON and Markdown replace absolute roots with stable markers:

| Marker | Meaning |
| --- | --- |
| `[PROJECT_ROOT]` | Analyzed project root |
| `[REPORT_OUTPUT]` | Chosen report destination |
| `[LOCAL_REPOSITORY]` | Resolved local Composer repository root |
| `[ANALYZER_WORKSPACE]` | Temporary analyzer root |

Reported source paths remain project-relative. Exact paths are still used internally for filesystem access.

Path markers make reports easier to compare across machines. Durations, Composer lock metadata, and candidate lock hashes can still differ.

## Compatible Composer mode

`--composer-mode=compatible` is the default. It preserves the environment needed by many real projects:

- global Composer config and auth
- Composer cache
- proxy variables
- Git and SSH configuration
- network access
- repository credentials available to the analyzer process.

This mode can use the same repository access as a normal Composer run, but its result depends on the host. Another machine with different credentials, cache, repositories, or Composer version may resolve a different candidate.

Use short-lived, read-only credentials where possible.

## Restricted Composer mode

`--composer-mode=restricted` creates fresh analyzer-owned Composer home, cache, and XDG directories, writes empty config/auth files, sets empty `COMPOSER_AUTH`, scrubs controlled proxy and askpass variables, disables prompts, and requests Composer's best-effort offline behavior.

It does **not** provide:

- an OS firewall
- process isolation
- a guarantee that helper executables cannot access the network
- removal of repository URLs or credentials embedded in project `composer.json`
- isolation from system trust stores
- a guarantee that a user-selected executable is benign.

If the fresh restricted cache lacks repository metadata, Core reports `repository_metadata_unavailable`. It cannot infer a package conflict from missing repository data.

For untrusted projects, run the analyzer inside a disposable container or restricted account with independently enforced network and filesystem controls.

## Composer side effects

Both modes disable scripts, plugins, package installation, audit, interaction, and Composer progress output. This limits side effects. A project that depends on a Composer plugin may also resolve differently, so check that limitation when reading its report.

## Wizard package-metadata lookup boundary

The wizard's optional package lookup is a pre-analysis convenience, not Composer feasibility evidence and not a replacement for `--composer-mode`. The user chooses the lookup source explicitly:

- `composer.json` only starts no Composer process
- local-cache-only lookup requests no network and treats missing metadata as unverified
- configured project repositories may use network, global Composer state, repository credentials, proxy, Git, and SSH configuration.

Composer metadata lookup disables plugins, scripts, interaction, and ANSI and has a bounded timeout and redacted, bounded diagnostics. Only an explicit package-not-found response from the configured repository universe becomes `not_found`. DNS, offline, authentication, timeout, malformed-output, and other operational failures remain `unverified`. Restricted Composer execution is also unverified without starting a lookup process until an isolated lookup home/cache is available.

Candidate versions help the wizard offer choices. Analysis later runs its own bounded scenarios in temporary workspaces under the chosen Composer analysis mode.

## Credentials and redaction

Report fields, bounded Composer stdout/stderr excerpts, diagnostics, and command failure messages pass through deterministic redaction. Known credential-bearing URLs, authorization values, common tokens, and named credential fields are replaced with markers.

Redaction runs on output after Composer has done its work:

- Composer may already have read credentials before output is redacted
- network requests may already have occurred
- a retained debug workspace may contain sensitive input
- pattern matching cannot recognize every future secret format
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

Host installability asks whether the analyzer and adapter can execute in the current Composer project. The packages require PHP `^8.0`. A project-local Laravel adapter also has to satisfy the installed Laravel/Illuminate constraints.

### Target platform

Target modeling asks Composer to reason about a desired exact PHP and platform package set in temporary manifests. It is controlled by:

- `--target-php`
- `--with-extension` and `--without-extension`
- `--target-platform-profile`
- lower-priority original `config.platform`
- host values for anything still unmodeled.

These inputs do not alter the analyzer interpreter and do not prove the real deployment environment matches the model.

### Runtime compatibility

Runtime compatibility asks whether the changed application actually works. The analyzer does not boot the target, execute its tests, call external services, validate data migrations, or exercise production traffic.

Keep host installability, modeled target resolution, and runtime behavior separate when describing a result.

## Partial and complete platform profiles

In a partial profile, listed decisions are explicit. Unlisted supported values may still come from the analyzer host, with that provenance recorded.

A complete profile is closed-world only for supported safely simulated platform-package classes. Unlisted values in those classes are modeled absent. It still does not pin:

- repository metadata
- downloads or network behavior
- credentials
- Composer executable behavior
- toolchain-bound platform packages
- application runtime behavior.

Complete profiles and explicit absences require Composer 2.2+. On Composer 2.0 or 2.1, affected analysis stops as unknown before workspace creation rather than weakening the request.

Call a profile complete only after someone has inventoried the deployment platform. `composer show --platform` can help collect that information, though its output is not the profile schema.

## Source-analysis limits

The scanner parses PHP syntax statically. It does not execute code, resolve service-container bindings, evaluate dynamic class names, or infer string-built symbols.

The default scan retains at most 10,000 deterministically ordered PHP files, reads at most 2 MiB from one file and 64 MiB in aggregate, and retains at most 10,000 unique usages. Reaching a limit skips the remaining work in that dimension and adds `E3` evidence plus uncertainty with the threshold and omission count. Embedded Core users can inject `SourceScanLimits` to configure positive limits.

It can miss or downgrade confidence for:

- parse errors
- `eval` and runtime-generated declarations
- `class_alias` and dynamic autoloaders
- missing or unsupported autoload metadata
- custom installer paths
- classmap/files inventories beyond the deterministic safety limit
- dependencies' `autoload-dev` data
- symbols whose ownership is ambiguous.

`source_inventory` says what the scanner observed. `source_impact` contains only items Core could correlate with relevant change evidence. An empty impact list cannot prove the application needs no source edits.

Staged findings are always projected from the original source snapshot. The analyzer does not simulate source edits between stages.

Composer input and the selected source set are fingerprinted around long-running analysis phases. Concurrent additions, removals, or edits produce input-drift uncertainty. This detects a non-atomic run. It does not lock the checkout or reconstruct one historical snapshot. Analyze an immutable checkout when every report section must describe exactly the same state.

## Framework-guidance limits

Laravel guidance coverage is independent of Composer feasibility. A rule pack can be `supported` while direct or staged resolution is blocked. Conversely, Composer may resolve a target for which safe migration guidance is partial or unsupported.

Encoded package ranges, maintainer links, and skeleton patterns identify review work. They do not replace official upgrade guides. Skeleton findings are low-confidence comparison points, not confirmed incompatibilities.

Installed adapters run as PHP code inside the analyzer. They have the analyzer's filesystem, network, environment, and credential privileges. If detection, default paths, transition guidance, package-family classification, source collection, or a compatibility rule throws at runtime, Core records evidence-backed uncertainty and continues where it can. Catching that failure cannot undo adapter side effects or isolate an untrusted adapter. Read an empty finding list alongside `uncertainties` before treating it as a clean result.

## Exit status boundary

Exit code 0 means the command produced a valid report. That report can still say `blocked` or `unknown` for direct resolution.

Process codes 1 and 2 mean no valid analysis report was produced. Wizard cancellation before analysis uses conventional code 130. They are operational/interface results, not Composer solver results.

Within a valid report, read independently:

- `resolution.status` for direct final-target Composer feasibility
- `transition.framework_guidance[].status` for adapter coverage
- `staged_resolution.execution_state` and `.status` for adjacent-stage evidence
- `uncertainties` for evidence gaps
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

- that an upgrade has been performed
- that a candidate lock should be committed unchanged
- that application tests pass
- that production data migrations are safe
- that private integrations still work
- that the deployment image matches the modeled platform
- that a `feasible` result is ready to release
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

A usable report establishes command integrity. A successful dependency probe establishes a Composer candidate under recorded inputs. Runtime acceptance still depends on installation, tests, and deployment checks.

### Retention guidance

Keep canonical non-debug JSON when you need an audit trail: it preserves evidence IDs and machine-readable provenance. Store it under the project's confidentiality rules. Markdown is easier to read, but automated consumers should use JSON.

Do not retain debug workspaces by default. When one is needed for an incident, record who authorized retention, where it is stored, and when it must be removed. A copied manifest can reveal private repository definitions even when the rendered report redacts output.

## Related pages

- [Getting Started](https://github.com/ValentinNikolaev/php-upgrade-preflight/wiki/Getting-Started)
- [CLI Reference](https://github.com/ValentinNikolaev/php-upgrade-preflight/wiki/CLI-Reference)
- [[Troubleshooting and FAQ|Troubleshooting-and-FAQ]]
- [Determinism and Evidence](https://github.com/ValentinNikolaev/php-upgrade-preflight/wiki/Determinism-and-Evidence)
- [Report Schema](https://github.com/ValentinNikolaev/php-upgrade-preflight/wiki/Report-Schema)
