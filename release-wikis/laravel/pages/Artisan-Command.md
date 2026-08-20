# Artisan Command

Installing `php-upgrade-preflight/laravel` in a Laravel application registers a service provider through package discovery. When the application is running in console mode, the provider adds:

```text
php artisan upgrade:analyze [options]
```

The command uses the same core analyzer and Laravel integration as the standalone CLI. It defaults `--path` to the application's base path and always enables Laravel.

## When Artisan is a good fit

Use Artisan when:

- the application can boot on the current PHP interpreter;
- the Laravel adapter can be installed without harming the dependency graph;
- project-local installation is acceptable;
- developers already use Artisan as the operational entry point.

Use an external CLI tools directory when:

- the target is Laravel 7 or PHP 7.x;
- Laravel cannot boot because of a broken provider or configuration;
- installing a dev dependency would disturb the project;
- an immutable audit must include the state before package installation.

### Host installability versus target platform

The Artisan command must first boot the current Laravel application. Therefore its host constraints are real installation constraints. The adapter is host-installable on Laravel 8–13, with Laravel's own PHP floor applying when higher than the package's PHP 8.0 floor.

`--target-php=8.3`, however, is Composer simulation input for the desired target. It does not switch the interpreter that runs `php artisan`. A Laravel 10 application that boots on PHP 8.1 may analyze a PHP 8.3 target; the report still does not prove that the upgraded application runs on PHP 8.3.

## Install

```bash
composer require --dev php-upgrade-preflight/laravel:^0.3
php artisan list | grep upgrade
```

PowerShell:

```powershell
composer require --dev php-upgrade-preflight/laravel:^0.3
php artisan list | Select-String upgrade
```

The Laravel package includes the Artisan command. Install `php-upgrade-preflight/cli` too only if you also need `vendor/bin/upgrade-intel`.

## First report

Linux or macOS:

```bash
mkdir -p ../upgrade-reports
php artisan upgrade:analyze \
  --from-php=8.1 \
  --target=laravel/framework:^11.0 \
  --target-php=8.2 \
  --format=markdown \
  --output=../upgrade-reports/laravel-11.md
```

Windows PowerShell:

```powershell
New-Item -ItemType Directory -Force ..\upgrade-reports | Out-Null
php artisan upgrade:analyze `
  --from-php=8.1 `
  --target=laravel/framework:^11.0 `
  --target-php=8.2 `
  --format=markdown `
  --output=..\upgrade-reports\laravel-11.md
```

Keep output outside the application. The parent directory must already exist and be writable.

## Options

| Option | Repeatable | Default | Meaning |
| --- | ---: | --- | --- |
| `--path=PATH` | No | Laravel base path | Project directory to analyze |
| `--target=PACKAGE:CONSTRAINT` | Yes | None | Requested package target |
| `--target-php=VERSION` | No | None | Exact target PHP platform |
| `--target-platform-profile=PATH` | Technically list-valued; exactly one allowed | None | Schema 1.0 JSON platform profile |
| `--from-php=VERSION` | No | None | Known current PHP for staging |
| `--with-extension=EXT[:VERSION]` | Yes | None | Model an extension as present |
| `--without-extension=EXT` | Yes | None | Model an extension as absent |
| `--source=PATH` | Yes | Adapter/default paths | Additional source inside the project |
| `--format=json\|markdown` | No | `json` | Report format |
| `--output=PATH` | No | stdout | Report file outside the project |
| `--composer-mode=compatible\|restricted` | No | `compatible` | Composer execution policy |
| `--composer-executable=PATH` | No | `composer` | Composer selection |
| `--composer-version=RANGE` | No | `>=2.0.0 <3.0.0` | Expected Composer version |
| `--composer-timeout=SECONDS` | No | `300` | Scenario timeout, 1–3600 |
| `--composer-diagnostic-timeout=SECONDS` | No | `60` | Diagnostic timeout, 1–900 |
| `--debug` | No | Off | Preserve workspaces and expose exact paths |

Unlike the standalone parser, Symfony Console also accepts the normal separated-value form for Artisan options, but the `--name=value` form keeps examples portable and unambiguous.

At least one package target, target PHP, or target-platform profile is required.

## Differences from the standalone CLI

| Behavior | Artisan | Standalone CLI |
| --- | --- | --- |
| Default project path | Laravel application base path | Current working directory |
| Laravel activation | Always enabled | Auto-detected or `--framework=laravel` |
| Needs application boot | Yes | No |
| Adapter discovery | Laravel package discovery registers command | Composer metadata discovers installed adapters |
| Windows launcher | `php artisan` | `vendor\bin\upgrade-intel.bat` |

Targets, platform profiles, extension assumptions, source validation, report writers, output safety, Composer execution modes, debug behavior, and exit policy otherwise use the same underlying model.

The standalone CLI also provides `upgrade-intel wizard`; Artisan remains an explicit option-based command and does not add a second interactive prompt surface.

## Choosing between Artisan and an external CLI

| Situation | Recommended entry point | Reason |
| --- | --- | --- |
| Healthy Laravel 10 application on PHP 8.1 | Artisan or CLI | Both entry points can use the same Laravel analyzer pipeline |
| Laravel 7 application on PHP 7.4 | External CLI | The analyzer requires PHP 8 and does not need to boot the target |
| Broken service provider prevents Artisan boot | External CLI | Composer metadata and source can still be inspected statically |
| Dependency graph cannot accept the adapter | External CLI | Adapter packages live in an isolated tools project |
| Team wants a familiar local developer command | Artisan | Laravel package discovery exposes `upgrade:analyze` |
| Audit requires pre-install target bytes to remain unchanged | External CLI | Project-local `composer require` would change the target first |

An external run still needs `php-upgrade-preflight/laravel` installed beside `php-upgrade-preflight/cli`. The target project itself needs neither package.

## Multiple targets and custom source paths

Artisan list-valued options may be repeated:

```bash
php artisan upgrade:analyze \
  --target=laravel/framework:^11.0 \
  --target=laravel/passport:^11.0 \
  --target-php=8.2 \
  --source=app \
  --source=tests/Feature \
  --format=json \
  --output=../upgrade-reports/laravel-11.json
```

```powershell
php artisan upgrade:analyze `
  --target=laravel/framework:^11.0 `
  --target=laravel/passport:^11.0 `
  --target-php=8.2 `
  --source=app `
  --source=tests\Feature `
  --format=json `
  --output=..\upgrade-reports\laravel-11.json
```

Every source path must exist inside the analyzed project. The Laravel adapter also contributes its normal default paths, so `--source` is for additional or focused input, not permission to read outside the project.

Duplicate identical targets collapse after normalization. Conflicting constraints for the same package are invalid. PHP targets must be exact values such as `8.2` or `8.2.12`, never ranges such as `^8.2`.

## Platform examples

Explicit extensions:

```bash
php artisan upgrade:analyze \
  --target=laravel/framework:^12.0 \
  --target-php=8.3 \
  --with-extension=ext-curl:8.3.0 \
  --without-extension=ext-xdebug \
  --output=../upgrade-reports/laravel-12.json
```

Target profile:

```bash
php artisan upgrade:analyze \
  --target=laravel/framework:^12.0 \
  --target-platform-profile=../platform/php-83-production.json \
  --composer-mode=compatible \
  --output=../upgrade-reports/laravel-12.json
```

PowerShell:

```powershell
php artisan upgrade:analyze `
  --target=laravel/framework:^12.0 `
  --target-platform-profile=..\platform\php-83-production.json `
  --composer-mode=compatible `
  --output=..\upgrade-reports\laravel-12.json
```

A partial profile leaves unlisted values host-dependent. A complete profile treats unlisted supported safely simulated classes as absent and requires Composer 2.2+. Executable-bound Composer platform packages remain `toolchain_bound`.

## Laravel guidance scope

The v0.3 catalog covers Laravel 7→8, retained direct 7→9 guidance, and adjacent 8→9 through 12→13 rule packs. Gapless adjacent packs can compose multi-major guidance.

Same-major requests, downgrades, ambiguous or unknown majors, targets outside Laravel 7–13, and a missing first hop are unsupported. A covered prefix followed by a later gap is `partially_supported` and guidance stops before that gap.

Staged Composer solving has narrower prerequisites: it supports a single rooted `laravel/framework` target across a contiguous adjacent path. Illuminate-component-only projects and mixed Laravel-family target sets can receive honest skipped staged results.

### Example: explain three independent outcomes

Suppose a report contains:

```json
{
  "resolution": {"status": "blocked"},
  "staged_resolution": {
    "execution_state": "evaluated",
    "status": "feasible_with_changes"
  },
  "transition": {
    "framework_guidance": [
      {"framework": "laravel", "status": "supported"}
    ]
  }
}
```

This is not contradictory:

- the requested final target did not resolve in direct scenarios;
- an adjacent-stage chain did find selectable candidate states;
- the adapter has rule coverage for the requested framework route.

The next action is to inspect the selected stage attempts and blockers, not to declare the application compatible. Later source and runtime work still has to be performed.

## Exit codes are not upgrade statuses

The Artisan command returns:

| Code | Meaning |
| --- | --- |
| `0` | A valid report was produced, even if blocked or unknown |
| `1` | Report production failed |
| `2` | Invocation validation failed |

After code 0, read the JSON:

```powershell
php artisan upgrade:analyze --target=laravel/framework:^11.0 --target-php=8.2 --output=..\upgrade-reports\report.json
if ($LASTEXITCODE -ne 0) { throw 'Analyzer did not produce a report.' }
$report = Get-Content -Raw ..\upgrade-reports\report.json | ConvertFrom-Json
$report.resolution.status
$report.transition.framework_guidance | Select-Object framework,status
$report.staged_resolution | Select-Object execution_state,status,stop_reason
```

`resolution.status` uses `feasible`, `feasible_with_changes`, `blocked`, or `unknown`. Framework guidance uses `supported`, `partially_supported`, or `unsupported`. Staged execution may be `evaluated` or `skipped`, and its status is independent. None of these fields is a deployment approval.

## Composer modes and private repositories

Compatible mode may inherit host Composer config, credentials, proxy variables, cache, Git/SSH setup, and network. Restricted mode creates fresh Composer state, scrubs controlled credential/proxy sources, and requests offline behavior.

Restricted mode is not an OS sandbox. If its fresh cache cannot provide repository metadata, the report records `repository_metadata_unavailable` and resolution becomes unknown rather than blocked.

### Slow or private-repository example

```bash
php artisan upgrade:analyze \
  --target=laravel/framework:^11.0 \
  --target-php=8.2 \
  --composer-mode=compatible \
  --composer-timeout=600 \
  --composer-diagnostic-timeout=90 \
  --output=../upgrade-reports/private-app.json
```

Use compatible mode only with scoped credentials appropriate for the repositories Composer may contact. The exact Composer executable path and environment values are not serialized, but captured output still needs review before sharing.

## Reading output from stdout

When `--output` is absent, Artisan writes the rendered report to stdout and diagnostics through Symfony Console's error style. When stderr is attached to a terminal, it also prints durable `[working]`, `[done]`, `[blocked]`, `[timed-out]`, and `[unverified]` progress lines for analysis phases and Composer scenarios. This is convenient for a developer:

```bash
php artisan upgrade:analyze --target-php=8.2 --format=markdown
```

For automation, prefer a validated output file. If you redirect stdout yourself, the shell can create a destination inside the application before the analyzer sees it, defeating the destination guard.

Progress is observational and never changes the report. Terminal detection follows the command's attached error output rather than the host process: redirected, buffered, or captured invocations remain silent, so report stdout remains suitable for pipes. The renderer uses ordinary lines rather than a spinner or cursor-control sequences.

## Debugging a boot failure

If `php artisan` itself cannot start, the analyzer command cannot run. Confirm this first:

```bash
php artisan --version
php artisan list
```

```powershell
php artisan --version
php artisan list
```

When boot fails, use the external standalone CLI. It reads Composer metadata and PHP source without booting the target application.

## Debug mode warning

`--debug` preserves temporary workspaces and exposes exact temporary paths. Retained workspaces contain copied manifests; redaction of the report does not sanitize those files. Do not share debug reports or workspaces.

## Related pages

- [[Getting Started|Getting-Started]]
- [CLI Reference](https://github.com/ValentinNikolaev/php-upgrade-preflight/wiki/CLI-Reference)
- [[Safety and Trust Boundaries|Safety-and-Trust-Boundaries]]
- [[Troubleshooting and FAQ|Troubleshooting-and-FAQ]]
