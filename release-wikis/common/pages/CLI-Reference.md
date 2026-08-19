# CLI Reference

The standalone executable is installed by `php-upgrade-preflight/cli`.

```text
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

## Parsing rules that prevent surprises

- The `analyze` subcommand is required unless `-h` or `--help` is present.
- Value options use one token: `--name=value`.
- `--path /work/app` is invalid; use `--path=/work/app`.
- `--debug` is a flag and must not have a value.
- Scalar options may be supplied once.
- Repeatable options are `--target`, `--with-extension`, `--without-extension`, `--source`, and `--framework`.
- At least one `--target`, `--target-php`, or `--target-platform-profile` is required.
- Diagnostics go to stderr. The report goes to stdout unless `--output` is used.

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
| `--composer-mode=compatible\|restricted` | No | `compatible` | Composer state and network policy |
| `--composer-executable=PATH` | No | `composer` | Composer command or executable selection |
| `--composer-version=RANGE` | No | `>=2.0.0 <3.0.0` | Accepted Composer version constraint |
| `--composer-timeout=SEC` | No | `300` | Scenario timeout, 1–3600 seconds |
| `--composer-diagnostic-timeout=SEC` | No | `60` | Diagnostic timeout, 1–900 seconds |
| `--debug` | No | Off | Preserve workspaces and expose exact temporary paths |
| `-h`, `--help` | No | — | Print help and return 0 |

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

A source path outside the project is rejected even if it exists.

## Package and PHP targets

`--target` splits at the first colon and validates the package name and Composer constraint.

```bash
--target=laravel/framework:^13.0
--target=laravel/passport:^12.0
```

Repeat an identical package target if necessary; it collapses. Conflicting constraints for the same package are invalid.

`--target-php` accepts an exact major, major.minor, or major.minor.patch value. Values normalize to three components in the target set.

```bash
--target-php=8.3
--target=php:8.3
```

These two PHP forms are equivalent. If both are supplied, they must normalize to the same exact value. A range such as `--target-php=^8.3` is invalid because simulation needs a concrete platform value.

`--from-php` also accepts an exact major, major.minor, or major.minor.patch. It describes the current project for staging; it does not change the PHP interpreter running the analyzer.

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

- exact versions and absences are written only to analyzer-owned temporary manifests;
- matching repeats collapse;
- different versions for one extension are contradictory;
- present and absent for one extension are contradictory;
- absence simulation requires Composer 2.2+;
- presence without a version uses a conservative sentinel and cannot prove a versioned constraint;
- a sentinel-related constraint failure becomes a non-blocking `extension-version-unknown` advisory;
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

Use `partial` when inventory is incomplete. Unlisted platform packages then remain host-dependent. Use `complete` only when every supported safely simulated class was considered and every unlisted value should be absent.

Complete profiles require Composer 2.2+. Composer 2.0 or 2.1 produces an operationally unknown result before workspace creation; the request is not silently weakened to partial.

Request values take precedence over the profile, which takes precedence over original `config.platform`. Equal request/profile values are accepted. Contradictions are rejected. A complete profile cannot be combined with a presence-only extension assumption.

Executable-bound values such as `composer`, `composer-plugin-api`, and `composer-runtime-api` are recorded as `toolchain_bound`; the analyzer does not claim that `config.platform` safely simulates them.

## Framework adapters

The CLI discovers installed adapters through Composer package metadata. With no `--framework`, every discovered adapter may run automatic detection.

```bash
vendor/bin/upgrade-intel analyze \
  --path=/work/app \
  --target=laravel/framework:^11.0 \
  --framework=laravel
```

Explicit names are case-insensitive and deduplicated. An unavailable explicit adapter is invalid input and returns exit code 2. A malformed unrelated installed adapter manifest is skipped with a stderr diagnostic; adapter class or name collisions fail analysis rather than selecting an arbitrary winner.

The Artisan entry point does not accept `--framework`; it always enables Laravel.

## Composer execution modes

### Compatible mode

`compatible` is the default. Composer may inherit global config, authentication, proxy settings, cache, Git/SSH setup, and network access. Use it when private repositories need the normal host environment.

```bash
--composer-mode=compatible
```

Evidence from compatible mode depends on host state and is not cross-host reproducibility proof.

### Restricted mode

`restricted` uses fresh analyzer-owned Composer home, cache, and XDG roots; writes empty Composer config/auth files; scrubs controlled credential, proxy, and askpass variables; and requests best-effort offline behavior.

```bash
--composer-mode=restricted
```

It is not an operating-system network sandbox. The selected executable, helper processes, system trust, and credentials embedded in project input remain boundaries. A fresh offline cache miss is `repository_metadata_unavailable`, an operational uncertainty, not a dependency blocker.

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

The parser first requires digits; the configuration then enforces 1–3600 and 1–900. Note that the literal `0` contains digits but fails the allowed range.

## Output and streams

Without `--output`, the report is written to stdout and diagnostics to stderr:

```bash
vendor/bin/upgrade-intel analyze --path=/work/app --target-php=8.2 > /tmp/report.json
```

Shell redirection happens before the analyzer validates a destination. Do not redirect stdout into the analyzed project.

Prefer `--output`, which validates that the destination is outside the project, is not a directory, and has an existing writable parent:

```bash
mkdir -p /work/reports
vendor/bin/upgrade-intel analyze --path=/work/app --target-php=8.2 --output=/work/reports/app.json
```

```powershell
New-Item -ItemType Directory -Force C:\work\reports | Out-Null
vendor\bin\upgrade-intel.bat analyze --path=C:\work\app --target-php=8.2 --output=C:\work\reports\app.json
```

On success with `--output`, stdout contains a short “Wrote report” message rather than report JSON.

## Exit code versus report status

Never use `$?`, `$LASTEXITCODE`, or a CI step's green/red state as the upgrade verdict.

| Process code | Contract |
| --- | --- |
| `0` | Help or a valid canonical report was produced |
| `1` | Report production failed internally or operationally |
| `2` | Invocation validation failed |

| `resolution.status` | Direct final-target meaning |
| --- | --- |
| `feasible` | Final target resolved with no package changes |
| `feasible_with_changes` | Final target resolved with candidate package changes |
| `blocked` | Composer blockers prevent final-target resolution |
| `unknown` | No reliable feasibility conclusion was reached |

The five-minute demo returns process code 0 while `resolution.status` is `blocked`.

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

