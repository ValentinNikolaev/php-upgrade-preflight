# CLI Reference

Install `php-upgrade-preflight/cli` to get the standalone `upgrade-intel` command. It reads the project you point it at and returns a report. It does not upgrade that project.

```text
upgrade-intel wizard
upgrade-intel analyze --target=package:constraint [options]
upgrade-intel analyze --target-platform-profile=PATH [options]
```

Use the Composer-generated launcher for your operating system:

```bash
vendor/bin/upgrade-intel --help
```

```powershell
vendor\bin\upgrade-intel.bat --help
```

## How the command reads options

- Use `wizard` at a terminal when you want guided choices. Use `analyze` when you already know the options or are writing a script.
- `wizard` accepts no options. It requires terminal-attached stdin and stderr.
- Value options use one token: `--name=value`.
- `--path /work/app` is invalid. Use `--path=/work/app`.
- `--debug` is a flag and must not have a value.
- Scalar options may be supplied once.
- Repeatable options are `--target`, `--with-extension`, `--without-extension`, `--source`, and `--framework`.
- At least one `--target`, `--target-php`, or `--target-platform-profile` is required.
- Diagnostics and terminal progress go to stderr. The report goes to stdout unless `--output` is used.

## Complete option table

| Option | Repeatable | Default | Meaning |
| --- | ---: | --- | --- |
| `--path=PATH` | No | Current directory | Project directory to analyze |
| `--target=PACKAGE:VALUE` | Yes | None | Composer package target and constraint |
| `--target-php=VERSION` | No | None | Exact target PHP platform version |
| `--target-platform-profile=PATH` | No | None | Schema 1.0 JSON platform profile |
| `--from-php=VALUE` | No | None | Known exact current PHP version |
| `--with-extension=EXT[:VERSION]` | Yes | None | Model an extension as present, optionally at an exact version |
| `--without-extension=EXT` | Yes | None | Model an extension as absent |
| `--source=PATH` | Yes | Adapter/default paths | Additional file or directory inside the project |
| `--framework=NAME` | Yes | Auto-detection | Explicit installed framework adapter |
| `--format=json\|markdown` | No | `json` | Report writer |
| `--output=PATH` | No | stdout | Report file outside the project |
| `--save-report=PATH` | No | None | Keep the report on stdout and save the identical rendered bytes outside the project |
| `--composer-mode=compatible\|restricted` | No | `compatible` | Composer state and network policy |
| `--composer-executable=PATH` | No | `composer` | Composer command or executable selection |
| `--composer-version=RANGE` | No | `>=2.0.0 <3.0.0` | Accepted Composer version constraint |
| `--composer-timeout=SEC` | No | `300` | Scenario timeout, 1–3600 seconds |
| `--composer-diagnostic-timeout=SEC` | No | `60` | Diagnostic timeout, 1–900 seconds |
| `--debug` | No | Off | Preserve workspaces and expose exact temporary paths |
| `-h`, `--help` | No | — | Print help and return 0 |

Choose one file option. `--output` writes the report to a file and prints a short confirmation. `--save-report` keeps the report on stdout and writes the same bytes to a file.

## Interactive wizard

Run the guided flow in a real terminal:

```bash
vendor/bin/upgrade-intel wizard
```

The wizard reads `composer.json` and shows what it knows about the project's PHP version alongside the PHP version running the analyzer. It asks you to choose the Composer mode, targets, report format, and optional saved copy. The running PHP version is never silently used as your target. Before analysis, the wizard prints the equivalent quoted `upgrade-intel analyze` command for review or reuse.

Package-target selection has three explicit metadata sources:

| Source | Network and trust behavior | Result handling |
| --- | --- | --- |
| `composer.json` only | Default, with no Composer metadata process | Root requirements are offered, but external existence is not claimed |
| Local Composer cache | Requests no network | Cache misses and operational failures are `unverified`, never “package does not exist” |
| Configured project repositories | May use the lookup's configured Composer executable, repository configuration, network, and credentials | Explicit not-found, found, matching-version, and operationally unverified results are kept distinct |

When a lookup finds package versions, the wizard offers a short list of release-line and exact-version choices, plus a custom constraint. It asks you to correct invalid syntax, a confirmed missing package, or a constraint with no matching discovered version. A timeout, offline failure, or unusable metadata leaves the lookup `unverified`. The wizard warns you and lets analysis make its own attempt.

Enter `cancel`, `quit`, or `q` at a prompt to stop before analysis with exit code `130`. End-of-input is invalid input (`2`). The wizard rejects redirected or non-TTY input and diagnostics instead of guessing defaults. Use `analyze` in scripts.

## Project and source paths

`--path` must resolve to an existing directory. Relative paths are resolved from the command's current directory.

```bash
cd /work/tools
vendor/bin/upgrade-intel analyze --path=../legacy-app --target-php=8.2
```

```powershell
Set-Location C:\work\tools
vendor\bin\upgrade-intel.bat analyze --path=..\legacy-app --target-php=8.2
```

Each `--source` must resolve to an existing file or directory inside the analyzed project. Relative source paths are project-relative. Duplicate normalized paths collapse.

```bash
vendor/bin/upgrade-intel analyze \
  --path=/work/app \
  --target-php=8.2 \
  --source=app \
  --source=tests/Feature
```

```powershell
vendor\bin\upgrade-intel.bat analyze `
  --path=C:\work\app `
  --target-php=8.2 `
  --source=app `
  --source=tests\Feature
```

An existing path outside the project still fails validation.

## Package and PHP targets

`--target` splits at the first colon and validates the package name and Composer constraint.

```bash
--target=laravel/framework:^13.0
--target=laravel/passport:^12.0
```

You may repeat an identical package target. The duplicates collapse. Conflicting constraints for the same package are invalid.

`--target-php` accepts an exact major, major.minor, or major.minor.patch value. Values normalize to three components in the target set.

```bash
--target-php=8.3
--target=php:8.3
```

Both PHP forms mean the same thing. If you use both, their normalized exact versions must agree. A range such as `--target-php=^8.3` cannot describe one platform for Composer to test.

`--from-php` also accepts an exact major, major.minor, or major.minor.patch. It describes the current project for staging. It does not change the PHP interpreter running the analyzer.

## Extension assumptions

Composer extension names use `ext-name` form.

```bash
vendor/bin/upgrade-intel analyze \
  --path=/work/app \
  --target-php=8.3 \
  --with-extension=ext-curl:8.3.0 \
  --with-extension=ext-json \
  --without-extension=ext-xdebug
```

```powershell
vendor\bin\upgrade-intel.bat analyze `
  --path=C:\work\app `
  --target-php=8.3 `
  --with-extension=ext-curl:8.3.0 `
  --with-extension=ext-json `
  --without-extension=ext-xdebug
```

Rules:

- exact versions and absences are written only to analyzer-owned temporary manifests.
- matching repeats collapse.
- different versions for one extension are contradictory.
- present and absent for one extension are contradictory.
- absence simulation requires Composer 2.2+.
- presence without a version uses a conservative sentinel and cannot prove a versioned constraint.
- a sentinel-related constraint failure becomes a non-blocking `extension-version-unknown` advisory.
- unlisted extensions may still come from the analyzer host and are reported as host-dependent.

## Target-platform profiles

A profile inventories the deployment platform more broadly than named extension switches.

```json
{
  "schema_version": "1.0",
  "completeness": "complete",
  "packages": {
    "php": "8.3.0",
    "ext-curl": "8.3.0",
    "ext-xdebug": false,
    "lib-curl": "8.6.0",
    "php-64bit": "8.3.0",
    "composer-plugin-api": "2.6.0"
  }
}
```

```bash
vendor/bin/upgrade-intel analyze \
  --path=/work/app \
  --target=laravel/framework:^12.0 \
  --target-platform-profile=/work/profiles/php-83-production.json
```

```powershell
vendor\bin\upgrade-intel.bat analyze `
  --path=C:\work\app `
  --target=laravel/framework:^12.0 `
  --target-platform-profile=C:\work\profiles\php-83-production.json
```

Supported names include `php`, `ext-*`, `lib-*`, PHP subtypes such as `php-64bit`, and Composer platform packages. Values are exact versions or `false` for verified absence.

Use `partial` if the inventory is incomplete. Composer may still take unlisted platform packages from the host. A `complete` profile treats every unlisted safely simulated platform package as absent. Use it only after you have verified that the inventory covers the deployment platform completely.

Complete profiles require Composer 2.2+. Composer 2.0 or 2.1 produces an operationally unknown result before workspace creation. The request is not silently weakened to partial.

Request values take precedence over the profile, which takes precedence over original `config.platform`. Equal request/profile values are accepted. Contradictions are rejected. A complete profile cannot be combined with a presence-only extension assumption.

Executable-bound values such as `composer`, `composer-plugin-api`, and `composer-runtime-api` are recorded as `toolchain_bound`. The analyzer does not claim that `config.platform` safely simulates them.

## Framework adapters

The CLI discovers installed adapters through Composer package metadata. With no `--framework`, every discovered adapter may run automatic detection.

```bash
vendor/bin/upgrade-intel analyze \
  --path=/work/app \
  --target=laravel/framework:^11.0 \
  --framework=laravel
```

Explicit names are case-insensitive and deduplicated. An unavailable explicit adapter is invalid input and returns exit code 2. A malformed unrelated installed adapter manifest is skipped with a stderr diagnostic. Adapter class or name collisions fail analysis rather than selecting an arbitrary winner.

The Artisan entry point does not accept `--framework`. It always enables Laravel.

## Composer execution modes

### Compatible mode

`compatible` is the default. Composer may use the host's global config, credentials, proxy, cache, Git/SSH setup, and network. Choose it when private repositories require that environment.

```bash
--composer-mode=compatible
```

The result can depend on that host state, so the same request may behave differently on another machine.

### Restricted mode

`restricted` uses fresh analyzer-owned Composer home, cache, and XDG roots. It writes empty Composer config and auth files, scrubs controlled credential, proxy, and askpass variables, and requests best-effort offline behavior.

```bash
--composer-mode=restricted
```

This mode does not block network access at the operating-system level. The selected executable, its helpers, system trust, and credentials in project input are still in scope. If a fresh offline cache lacks repository metadata, the report records `repository_metadata_unavailable`. It has too little evidence to call that a dependency blocker.

Scripts, plugins, installation, audit, interaction, and progress are disabled in both modes.

## Composer executable, version, and timeouts

Choose a Composer executable without publishing its exact path in the report:

```bash
--composer-executable=/opt/composer/composer
```

```powershell
--composer-executable=C:\tools\composer\composer.bat
```

The default expected range is Composer 2. A detected executable outside `--composer-version` stops scenario execution. Scenario and diagnostic timeouts are separate:

```bash
--composer-timeout=600 --composer-diagnostic-timeout=90
```

Both values must contain only digits. The allowed ranges are 1–3600 and 1–900 seconds, so `0` is invalid.

## Output and streams

Without `--output`, the report is written to stdout. Diagnostics and human progress are written only to stderr:

```bash
vendor/bin/upgrade-intel analyze --path=/work/app --target-php=8.2 > /tmp/report.json
```

Shell redirection happens before the analyzer validates a destination. Do not redirect stdout into the analyzed project.

For an atomic, validated copy while preserving the stdout report, use `--save-report`:

```bash
vendor/bin/upgrade-intel analyze --path=/work/app --target-php=8.2 --save-report=/work/reports/app.json
```

The saved file contains the same rendered bytes emitted to stdout. The destination is validated before analysis and written with the same project-boundary checks as `--output`. `--output` and `--save-report` cannot be combined.

If the additional copy fails after stdout was written, the report remains available on stdout, a redacted diagnostic explains the copy failure on stderr, and the process returns `1`.

If you only need a file, `--output` checks that it sits outside the project, is not a directory, and has an existing writable parent:

```bash
mkdir -p /work/reports
vendor/bin/upgrade-intel analyze --path=/work/app --target-php=8.2 --output=/work/reports/app.json
```

```powershell
New-Item -ItemType Directory -Force C:\work\reports | Out-Null
vendor\bin\upgrade-intel.bat analyze --path=C:\work\app --target-php=8.2 --output=C:\work\reports\app.json
```

On success with `--output`, stdout contains a short “Wrote report” message rather than report JSON.

### Terminal progress contract

When stderr is attached to a terminal, both the standalone CLI and Laravel Artisan entry point print durable phase and Composer-scenario lines such as `[working]`, `[done]`, `[blocked]`, `[timed-out]`, and `[unverified]`. The phases cover project metadata loading, Composer feasibility, staged paths, source scanning, framework rules, and report assembly.

Progress is observational: reporter failures are ignored and cannot alter analysis or report status. No spinner or cursor-control sequence is used. When stderr is redirected or is not a TTY, progress is suppressed. Stdout remains a clean report stream suitable for a pipe. Errors remain redacted stderr diagnostics.

## Exit code versus report status

A successful process means you have a report. Read the report to learn whether the target resolved. `$?`, `$LASTEXITCODE`, and a green CI step cannot tell you that.

| Process code | Contract |
| --- | --- |
| `0` | Help or a valid canonical report was produced |
| `1` | Report production failed internally or operationally |
| `2` | Invocation validation failed |
| `130` | The interactive wizard was cancelled before analysis |

| `resolution.status` | Direct final-target meaning |
| --- | --- |
| `feasible` | Final target resolved with no package changes |
| `feasible_with_changes` | Final target resolved with candidate package changes |
| `blocked` | Composer blockers prevent final-target resolution |
| `unknown` | No reliable feasibility conclusion was reached |

For example, the five-minute demo exits `0` even though its report says `resolution.status` is `blocked`.

For framework work, read three independent dimensions:

1. `resolution.status` — direct final-target Composer scenarios.
2. `transition.framework_guidance[].status` — adapter rule-pack coverage.
3. `staged_resolution.execution_state` and `staged_resolution.status` — adjacent-stage Composer chain.

## Debug mode

`--debug` deliberately preserves temporary Composer workspaces and exposes exact `temp_path` values. Those workspaces contain copied manifests and possibly sensitive project input.

```bash
vendor/bin/upgrade-intel analyze --path=/work/app --target-php=8.2 --debug
```

Debug reports and retained workspaces are non-shareable. Redaction remains active in rendered output, but it does not sanitize retained files on disk.

## Copy-ready examples

Multiple package targets:

```bash
vendor/bin/upgrade-intel analyze \
  --path=/work/app \
  --target=laravel/framework:^11.0 \
  --target=laravel/passport:^11.0 \
  --target-php=8.2 \
  --framework=laravel \
  --format=markdown \
  --output=/work/reports/laravel-11.md
```

Windows equivalent:

```powershell
vendor\bin\upgrade-intel.bat analyze `
  --path=C:\work\app `
  --target=laravel/framework:^11.0 `
  --target=laravel/passport:^11.0 `
  --target-php=8.2 `
  --framework=laravel `
  --format=markdown `
  --output=C:\work\reports\laravel-11.md
```

Restricted PHP-only check:

```bash
vendor/bin/upgrade-intel analyze \
  --path=/work/app \
  --from-php=7.4 \
  --target-php=8.1 \
  --composer-mode=restricted \
  --format=json
```

## Related pages

- [[Getting Started|Getting-Started]]
- [[Artisan Command|Artisan-Command]]
- [[Safety and Trust Boundaries|Safety-and-Trust-Boundaries]]
- [[Troubleshooting and FAQ|Troubleshooting-and-FAQ]]
- [[Report Schema|Report-Schema]]
