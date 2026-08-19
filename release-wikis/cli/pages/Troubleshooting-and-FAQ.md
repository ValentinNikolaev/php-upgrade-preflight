# Troubleshooting and FAQ

Start by separating command failure from analysis outcome:

- Exit code `1` or `2`: no valid report was produced; fix the invocation or environment.
- Exit code `0`: a valid report exists; inspect `resolution.status` and the other report dimensions.
- `resolution.status: blocked`: Composer evidence found a target conflict; the command still correctly returns 0.
- `resolution.status: unknown`: evidence was insufficient or an operational problem prevented a reliable solver conclusion.

## `Unable to find Composer autoload.php`

Run the executable installed by Composer:

```bash
vendor/bin/upgrade-intel --help
```

```powershell
vendor\bin\upgrade-intel.bat --help
```

For a source checkout, run `composer install` at the repository root first. Do not copy the PHP executable by itself; it depends on Composer autoloading.

## The shell says `upgrade-intel` is not recognized

Use the project-relative launcher instead of expecting a global command.

```bash
./vendor/bin/upgrade-intel --help
```

```powershell
.\vendor\bin\upgrade-intel.bat --help
```

Confirm you are in the Composer project where the CLI package is installed.

## Composer is missing

The analyzer launches `composer` from `PATH` unless `--composer-executable` selects another command.

```bash
composer --version
which composer
```

```powershell
composer --version
Get-Command composer
```

External analysis needs Composer in the tools environment, not inside the target application.

## The target runs PHP 7.4

Do not install the analyzer into that application. Install CLI and adapter in a separate directory running PHP 8.0+:

```bash
mkdir php-upgrade-tools && cd php-upgrade-tools
composer require php-upgrade-preflight/cli:^0.3 php-upgrade-preflight/laravel:^0.3
vendor/bin/upgrade-intel analyze \
  --path=/work/php74-app \
  --from-php=7.4 \
  --target-php=8.1 \
  --target=laravel/framework:^9.0 \
  --framework=laravel
```

```powershell
New-Item -ItemType Directory php-upgrade-tools | Out-Null
Set-Location php-upgrade-tools
composer require php-upgrade-preflight/cli:^0.3 php-upgrade-preflight/laravel:^0.3
vendor\bin\upgrade-intel.bat analyze `
  --path=C:\work\php74-app `
  --from-php=7.4 `
  --target-php=8.1 `
  --target=laravel/framework:^9.0 `
  --framework=laravel
```

The PHP 8 analyzer can model PHP 7.4 as the source state. It does not boot the target.

## Why can the CLI analyze Laravel 7 but the adapter cannot be installed there?

Host installability and analyzed target scope are separate. Project-local adapter installation requires Laravel 8–13 and the applicable host PHP floor. External CLI analysis installs the adapter in its own PHP 8 tools project, then reads Laravel 7 metadata and source without booting Laravel 7.

## `Unknown command`; expected `analyze`

The standalone CLI has one required subcommand:

```bash
vendor/bin/upgrade-intel analyze --target-php=8.2
```

The Artisan command is different:

```bash
php artisan upgrade:analyze --target-php=8.2
```

## `Unsupported argument at position ...`

Standalone values must use `=`:

```bash
# Wrong
vendor/bin/upgrade-intel analyze --path /work/app --target-php 8.2

# Right
vendor/bin/upgrade-intel analyze --path=/work/app --target-php=8.2
```

PowerShell:

```powershell
vendor\bin\upgrade-intel.bat analyze --path=C:\work\app --target-php=8.2
```

## At least one target is required

Supply at least one of:

```text
--target=vendor/package:constraint
--target-php=exact-version
--target-platform-profile=path
```

A profile containing exact PHP may provide the PHP target without a separate switch.

## A PHP target is rejected

PHP simulation uses an exact value, not a Composer range.

```bash
# Accepted
--target-php=8.3
--target-php=8.3.4

# Rejected
--target-php=^8.3
--target-php='>=8.2'
```

If `--target=php:8.3` and `--target-php=8.2` appear together, they conflict and invocation returns code 2.

## A package target is rejected

Use `vendor/package:constraint` with a valid Composer package name and constraint:

```bash
--target=laravel/framework:^11.0
```

Avoid spaces that the shell can split. Quote the entire token if your shell requires it.

## Laravel rules are unavailable

Install the adapter beside the CLI:

```bash
composer require php-upgrade-preflight/laravel:^0.3
composer show php-upgrade-preflight/laravel
```

An explicit unavailable `--framework=laravel` is invalid input and returns code 2. An unrelated malformed adapter manifest may also emit a discovery diagnostic on stderr.

## Artisan does not list `upgrade:analyze`

Check that the package is installed and package discovery is working:

```bash
composer show php-upgrade-preflight/laravel
php artisan package:discover
php artisan list
```

```powershell
composer show php-upgrade-preflight/laravel
php artisan package:discover
php artisan list | Select-String upgrade
```

The service provider registers the command only when the application runs in console mode. If Laravel cannot boot, use the external CLI.

## Artisan fails before the analyzer starts

Run:

```bash
php artisan --version
```

If that fails, an application/provider/configuration problem prevents every Artisan command, including preflight. External CLI analysis does not boot the application and is the safer fallback.

## Laravel guidance is partial or unsupported

Read `transition.framework_guidance[].uncertainties`.

- `supported`: every required hop has a rule pack.
- `partially_supported`: a covered prefix exists, then guidance stops at a gap.
- `unsupported`: no safe first hop/path can be selected.

Common causes are an ambiguous source/target major, same-major request, downgrade, target outside Laravel 7–13, or missing required hop. Do not use `resolution.status` to override guidance coverage; Composer feasibility and rule coverage answer different questions.

## Staged analysis was skipped

Inspect:

```text
staged_resolution.execution_state
staged_resolution.status
staged_resolution.stop_reason
staged_resolution.evidence
```

Staging may skip because no adapter supplies targets, providers conflict, endpoints are ambiguous, an adjacent hop is missing, the request exceeds a budget, or no exact request-backed PHP value satisfies adapter metadata.

A skipped stage is not a Composer blocker. Direct resolution and framework guidance remain independently useful.

## The command returns 0 for a blocked upgrade

This is expected.

```text
process exit code 0 = report production succeeded
resolution.status blocked = Composer final-target evidence found blockers
```

CI should first require exit code 0, then parse JSON:

```bash
vendor/bin/upgrade-intel analyze --path=/work/app --target-php=8.2 --output=/work/reports/app.json
test "$?" -eq 0 || exit $?
status="$(jq -r '.resolution.status' /work/reports/app.json)"
test "$status" = feasible -o "$status" = feasible_with_changes
```

```powershell
vendor\bin\upgrade-intel.bat analyze --path=C:\work\app --target-php=8.2 --output=C:\work\reports\app.json
if ($LASTEXITCODE -ne 0) { exit $LASTEXITCODE }
$status = (Get-Content -Raw C:\work\reports\app.json | ConvertFrom-Json).resolution.status
if ($status -notin @('feasible', 'feasible_with_changes')) { exit 10 }
```

Exit 10 in the wrapper is your policy, not an analyzer exit code.

## `resolution.status` is `unknown`

Unknown is an honest evidence limit. Inspect scenario `outcome`, diagnostics, Composer execution provenance, and `uncertainties`.

Typical causes:

- Composer executable missing or outside the expected range;
- timeout;
- restricted cache lacks repository metadata;
- complete profile used with Composer 2.0/2.1;
- baseline validation failure;
- operational workspace problem;
- redaction or parsing prevented confident classification.

Fix the cause and rerun. Do not translate unknown into feasible or blocked.

## A complete platform profile gives unknown

Check `composer --version`. Complete closed-world simulation needs Composer 2.2+ because older versions cannot hide all unlisted supported platform packages.

Also check for:

- contradictory target PHP between request and profile;
- contradictory extension values;
- presence-only extension assumptions combined with completeness;
- unsupported names or non-exact versions;
- malformed JSON or unsupported schema/completeness.

The analyzer does not silently downgrade complete to partial.

## Windows and Linux results differ

Compare these inputs first:

- Composer version and expected range;
- compatible versus restricted mode;
- repository metadata and credentials;
- target profile digest and completeness;
- explicit extension assumptions;
- unlisted host platform packages;
- original `config.platform`;
- path repository content.

Stable markers normalize common absolute roots, but Composer-produced lock metadata, durations, and raw lock hashes can vary. A partial platform request is deliberately host-dependent.

## The output path is rejected

`--output` must be outside the analyzed project. Its parent must already exist and be writable, and the destination must not be a directory.

```bash
mkdir -p /work/reports
vendor/bin/upgrade-intel analyze --path=/work/app --target-php=8.2 --output=/work/reports/app.json
```

```powershell
New-Item -ItemType Directory -Force C:\work\reports | Out-Null
vendor\bin\upgrade-intel.bat analyze --path=C:\work\app --target-php=8.2 --output=C:\work\reports\app.json
```

Avoid stdout redirection into the project because the shell creates the file before analyzer validation.

## A source path is rejected

Every `--source` must exist and resolve inside `--path`. Relative values are project-relative.

```bash
--path=/work/app --source=app --source=tests/Feature
```

Do not pass a sibling shared library as `--source`; analyze it as its own Composer project or include it through the project's supported layout.

## Relative Composer path repositories fail

Ordinary relative path repository URLs are resolved against the target before Composer runs. URLs containing environment variables or `~` remain untouched. Use an absolute URL or define the variable in the analyzer process.

Shareable reports replace the resolved root with `[LOCAL_REPOSITORY]`.

## Private packages cannot be downloaded

Compatible mode can use the analyzer host's normal authentication and network. Configure scoped credentials in the tools environment.

Restricted mode intentionally starts with empty Composer state and requests offline behavior. A metadata miss becomes `repository_metadata_unavailable`; it is not a package incompatibility.

If a credential appears unredacted, stop sharing, rotate it, and report only a synthetic reproduction through the private security channel.

## A Composer scenario times out

Default scenario timeout is 300 seconds; diagnostic timeout is 60 seconds.

```bash
--composer-timeout=600 --composer-diagnostic-timeout=90
```

Check repository availability, credentials, proxy behavior, and noninteractive authentication. Increase timeouts only after confirming the process is making legitimate progress.

## Temporary workspace cleanup failed

Default reports expose only `[ANALYZER_WORKSPACE]` and record `cleanup_failure`. If authorized, rerun with `--debug` to reveal and retain the exact path for diagnosis.

Do not share the debug report or retained workspace. Remove it manually after its value has been consumed, according to local retention policy.

## Why does a presence-only extension still produce uncertainty?

`--with-extension=ext-json` proves only modeled presence through a sentinel. It cannot prove a real version satisfies a versioned constraint. Supply an exact value such as `--with-extension=ext-json:8.3.0` when verified, or use a platform profile.

## Why does a successful Composer candidate not mean “ready”?

Composer success proves one dependency solution was found under the recorded inputs. It does not prove:

- the application boots;
- framework migrations were applied;
- tests pass;
- extensions behave as expected;
- database or external service compatibility;
- deployment configuration matches the model.

Use `tests`, `framework_findings`, `source_impact`, and `uncertainties` to plan the real validation.

## Why are there source inventory entries but no source impact?

`source_inventory` records observed declarations/usages. `source_impact` includes only observations correlated with a selected package change or applicable framework rule. Dynamic or unowned usages can remain inventory-only.

No impact is not a clean-runtime guarantee. Static scanning does not evaluate dynamic class names, container bindings, generated code, or application execution.

## An older report consumer rejects schema 0.8

Dispatch on `metadata.schema_version`. v0.3.x writes 0.8, which adds required top-level `composer_execution` and `staged_resolution`, required request Composer policy, and nullable platform-profile fields.

Do not treat an absent old-schema field as equivalent to a present schema 0.8 `null`. Update the consumer using the ordered schema migration documentation.

## FAQ for technical managers

### Does `feasible_with_changes` approve the project?

No. It says Composer found a candidate dependency state with package changes. Engineering must review the diff, apply changes in a branch, run tests on the target runtime, and validate deployment.

### Can we compare reports from two machines?

Yes, but compare recorded Composer execution and platform provenance first. A complete verified profile narrows platform differences; it does not pin repositories, credentials, network, or Composer behavior.

### Can the tool estimate effort?

It reports a bounded estimate with confidence and assumptions. Use it for planning discussion, not as a fixed quote or schedule commitment.

### Does restricted mode make an untrusted repository safe?

No. It hardens Composer-layer state and requests offline behavior, but it is not process or network isolation. Use an external disposable sandbox.

### Should we share Markdown or JSON?

Share Markdown for human review and retain JSON as canonical evidence. Review either artifact for sensitive data before distribution.

## Information to include in a bug report

- package version and `metadata.tool_version` if a report exists;
- `metadata.schema_version`;
- operating system and PHP version;
- Composer version and selected execution mode;
- sanitized command with secrets and private paths removed;
- process exit code;
- relevant scenario `outcome`, not only its numeric Composer exit code;
- expected versus actual behavior;
- a synthetic reproducer when possible.

Never attach a retained debug workspace from a private project.

## Related pages

- [[Getting Started|Getting-Started]]
- [[CLI Reference|Home]]
- [Artisan Command](https://github.com/ValentinNikolaev/php-upgrade-preflight/wiki/Artisan-Command)
- [[Safety and Trust Boundaries|Safety-and-Trust-Boundaries]]
- [Reading the Report](https://github.com/ValentinNikolaev/php-upgrade-preflight/wiki/Reading-the-Report)
