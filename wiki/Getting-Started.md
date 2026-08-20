# Getting Started

This guide takes you from an empty tools directory to a report you can explain to a developer or a technical manager. PHP Upgrade Preflight analyzes an upgrade; it does not perform one.

> **Current release line:** v0.3.x. The latest published release recorded by the repository is v0.3.3. It produces canonical JSON schema 0.8 and requires PHP `^8.0` on the machine that runs the analyzer.

## The result in one sentence

You give the analyzer an existing Composer project, a desired package or PHP target, and optional target-platform facts. It copies the project metadata to temporary workspaces, asks Composer what can resolve, scans selected PHP source, and writes an evidence-backed report without changing the target tree.

The report is planning input. It is not proof that the application boots, passes tests, is secure, or is ready to deploy.

## Before you install

Check the analyzer host:

```bash
php --version
composer --version
```

```powershell
php --version
composer --version
```

The analyzer host needs PHP 8.0 or newer and Composer 2. A target project may use an older PHP version because the analyzer can be installed in a separate tools directory.

### Host installability is not target compatibility

These are different questions:

| Question | Example | Answered by |
| --- | --- | --- |
| Can the analyzer package run here? | Can the Laravel adapter be installed into this Laravel 10 application? | Host PHP and host Laravel package constraints |
| What deployment platform should Composer model? | Can this project resolve for PHP 8.3 with `ext-curl`? | `--target-php`, extension assumptions, or a platform profile |
| Will the upgraded application work? | Does checkout still complete after the upgrade? | Your own tests and runtime validation, not this analyzer |

The Laravel adapter is project-locally installable with Laravel 8–13, subject to Laravel's own PHP floor. Laravel 7 should be analyzed from an external PHP 8 tools directory. An external analyzer can model a newer PHP target even if its own host uses another supported PHP 8 version.

## Choose an installation layout

### Recommended: a separate tools directory

Use this layout when the target runs PHP 7, has tight dependency constraints, or must remain byte-for-byte unchanged.

Linux or macOS:

```bash
mkdir -p "$HOME/php-upgrade-tools"
cd "$HOME/php-upgrade-tools"
composer require php-upgrade-preflight/cli:^0.3 php-upgrade-preflight/laravel:^0.3
vendor/bin/upgrade-intel --help
```

Windows PowerShell:

```powershell
New-Item -ItemType Directory -Force "$env:USERPROFILE\php-upgrade-tools" | Out-Null
Set-Location "$env:USERPROFILE\php-upgrade-tools"
composer require php-upgrade-preflight/cli:^0.3 php-upgrade-preflight/laravel:^0.3
vendor\bin\upgrade-intel.bat --help
```

Install every framework adapter beside the CLI. Nothing has to be installed into the target project.

### Project-local development dependency

Use this only when the application's dependency graph accepts the analyzer packages and changing `composer.json` and `composer.lock` during installation is acceptable.

```bash
composer require --dev php-upgrade-preflight/cli:^0.3 php-upgrade-preflight/laravel:^0.3
vendor/bin/upgrade-intel --help
```

```powershell
composer require --dev php-upgrade-preflight/cli:^0.3 php-upgrade-preflight/laravel:^0.3
vendor\bin\upgrade-intel.bat --help
```

Project-local installation changes the project before analysis. The analyzer's read-only promise begins after installation; it cannot make the `composer require` step immutable.

The published packages are ordinary Composer packages. There is no supported PHAR or versioned runtime container image. The repository Docker files are development and verification tooling.

## Prepare safe paths

The analyzed path must contain a valid `composer.json`. A `composer.lock` provides much stronger current-state evidence.

Create the report directory outside the project. The parent directory must already exist.

```bash
mkdir -p /work/upgrade-reports
```

```powershell
New-Item -ItemType Directory -Force C:\work\upgrade-reports | Out-Null
```

Do not write the report under the analyzed project. The command rejects that destination to preserve the read-only input contract.

## Guided first run

In a terminal, start the interactive wizard:

```bash
vendor/bin/upgrade-intel wizard
```

```powershell
vendor\bin\upgrade-intel.bat wizard
```

It shows available project evidence, asks whether to model PHP, packages, or both, and requires an explicit Composer analysis mode. For package targets, the default reads only `composer.json`. You may instead request a local-cache-only metadata check or configured project repositories; the latter can use network access and credentials. A package that is explicitly absent or has no matching discovered version must be corrected. Offline, timeout, and metadata failures are shown as unverified rather than falsely labeled nonexistent.

The default report format is readable Markdown. The wizard always keeps the report on stdout and can also save the same bytes to a validated path outside the project. It shows the equivalent flag-based `analyze` command before confirmation. Use that explicit command in CI or any redirected/non-TTY session. Enter `cancel`, `quit`, or `q` to stop before analysis.

## Run a first PHP-only analysis

Linux or macOS:

```bash
vendor/bin/upgrade-intel analyze \
  --path=/work/legacy-app \
  --from-php=7.4 \
  --target-php=8.2 \
  --format=json \
  --output=/work/upgrade-reports/php-82.json
```

Windows PowerShell:

```powershell
vendor\bin\upgrade-intel.bat analyze `
  --path=C:\work\legacy-app `
  --from-php=7.4 `
  --target-php=8.2 `
  --format=json `
  --output=C:\work\upgrade-reports\php-82.json
```

All standalone CLI values use `--name=value`. `--path C:\work\legacy-app` is not accepted. Only flags such as `--debug` omit `=value`.

## Run a first Laravel analysis

The Laravel adapter must be installed beside the CLI.

```bash
vendor/bin/upgrade-intel analyze \
  --path=/work/legacy-app \
  --from-php=8.1 \
  --target=laravel/framework:^11.0 \
  --target-php=8.2 \
  --framework=laravel \
  --format=json \
  --output=/work/upgrade-reports/laravel-11.json
```

```powershell
vendor\bin\upgrade-intel.bat analyze `
  --path=C:\work\legacy-app `
  --from-php=8.1 `
  --target=laravel/framework:^11.0 `
  --target-php=8.2 `
  --framework=laravel `
  --format=json `
  --output=C:\work\upgrade-reports\laravel-11.json
```

Without `--framework`, installed adapters may activate by detecting the project. Supplying `--framework=laravel` is clearer in automation and fails early with exit code 2 if the adapter is missing.

## Understand the two kinds of status

The shell exit code answers, “Did the command produce a valid report?” It does not answer, “Can the upgrade resolve?”

| Exit code | Meaning |
| --- | --- |
| `0` | Help was shown or a complete canonical report was produced |
| `1` | An internal or operational failure prevented report production |
| `2` | The invocation, target, path, format, framework, profile, or destination was invalid |
| `130` | The interactive wizard was cancelled before analysis |

A solver-blocked upgrade is successful analysis output, so it returns `0`.

The report field `resolution.status` answers the direct final-target Composer question:

| `resolution.status` | Meaning |
| --- | --- |
| `feasible` | A determining final-target scenario succeeded without package changes |
| `feasible_with_changes` | A determining final-target scenario succeeded with candidate lock changes |
| `blocked` | Reproducible Composer blockers prevent the requested final target |
| `unknown` | Operational or evidence limits prevented a reliable conclusion |

Example shell logic for JSON output:

```bash
vendor/bin/upgrade-intel analyze --path=/work/app --target-php=8.2 --format=json --output=/work/reports/app.json
command_code=$?
if [ "$command_code" -ne 0 ]; then
  echo "No valid report was produced; command exit code: $command_code" >&2
  exit "$command_code"
fi
jq -r '.resolution.status' /work/reports/app.json
```

```powershell
vendor\bin\upgrade-intel.bat analyze --path=C:\work\app --target-php=8.2 --format=json --output=C:\work\reports\app.json
$commandCode = $LASTEXITCODE
if ($commandCode -ne 0) {
    throw "No valid report was produced; command exit code: $commandCode"
}
$report = Get-Content -Raw C:\work\reports\app.json | ConvertFrom-Json
$report.resolution.status
```

For Laravel, also read `transition.framework_guidance[].status` and `staged_resolution.execution_state` plus `staged_resolution.status`. Guidance coverage, direct feasibility, and adjacent-stage feasibility are independent and may legitimately disagree.

## Try the checked-in five-minute demo

The repository demo models Laravel 10→13 using local path repositories. It does not contact Packagist and does not modify its target.

From a tools directory on Linux or macOS:

```bash
COMPOSER_ROOT_VERSION=1.0.0 vendor/bin/upgrade-intel analyze \
  --path=/path/to/php-upgrade-preflight/examples/five-minute-demo/target \
  --from-php=8.1 \
  --target=laravel/framework:^13.0 \
  --target-php=8.3 \
  --without-extension=ext-preflight-stage \
  --composer-mode=restricted \
  --framework=laravel \
  --format=json \
  --output=/tmp/php-upgrade-preflight-demo.json
```

PowerShell:

```powershell
$env:COMPOSER_ROOT_VERSION = '1.0.0'
vendor\bin\upgrade-intel.bat analyze `
  --path=C:\src\php-upgrade-preflight\examples\five-minute-demo\target `
  --from-php=8.1 `
  --target=laravel/framework:^13.0 `
  --target-php=8.3 `
  --without-extension=ext-preflight-stage `
  --composer-mode=restricted `
  --framework=laravel `
  --format=json `
  --output="$env:TEMP\php-upgrade-preflight-demo.json"
Remove-Item Env:COMPOSER_ROOT_VERSION
```

Expected interpretation:

- process exit code: `0`, because a valid report was written;
- direct `resolution.status`: `blocked`;
- aggregate `staged_resolution.status`: `blocked`;
- 10→11 and 11→12 stages: `feasible_with_changes`;
- 12→13 stage: `blocked` by the deliberately absent `ext-preflight-stage`;
- Laravel guidance: supported for all three hops.

This is the clearest example of why an exit code is not an upgrade verdict.

## Choose JSON or Markdown

Use JSON for automation, storage, comparisons, and evidence traversal:

```bash
--format=json --output=/work/reports/app.json
```

Use Markdown for a review meeting or ticket attachment:

```bash
--format=markdown --output=/work/reports/app.md
```

JSON is canonical. Markdown is a human-readable projection of the same report, not a second analysis.

### Quick JSON inspection without extra tools

PHP itself can print the direct status when `jq` is unavailable:

```bash
php -r '$r=json_decode(file_get_contents($argv[1]), true, 512, JSON_THROW_ON_ERROR); echo $r["resolution"]["status"], PHP_EOL;' /work/reports/app.json
```

PowerShell has a built-in JSON reader:

```powershell
$report = Get-Content -Raw C:\work\reports\app.json | ConvertFrom-Json
$report.metadata.schema_version
$report.resolution.status
$report.uncertainties
```

Always confirm `metadata.schema_version` before an automated consumer assumes field names or enums. v0.3.x writes schema 0.8.

## A practical team workflow

1. A developer records the exact current PHP with `--from-php` and desired deployment PHP with `--target-php`.
2. The platform owner supplies verified extension assumptions or a target-platform profile.
3. CI runs JSON output and checks the process exit code first.
4. A developer reads direct resolution, staged resolution, blockers, source impact, and uncertainties.
5. A technical manager uses risk, effort, confidence, and unresolved uncertainty for planning.
6. The team performs the real upgrade in a separate branch.
7. The application is installed and tested on the target runtime.
8. The analyzer is rerun after each meaningful remediation or stage.

## First-run checklist

- [ ] The analyzer host runs PHP 8.0+ and Composer 2.
- [ ] The target path is correct and contains `composer.json`.
- [ ] The report directory exists outside the target.
- [ ] At least one package target, target PHP, or target-platform profile is supplied.
- [ ] Every standalone option uses `--name=value`.
- [ ] The Laravel adapter is installed if Laravel guidance is requested.
- [ ] Exit code and report statuses are interpreted separately.
- [ ] `uncertainties` is reviewed before decisions are made.
- [ ] `--debug` is off for any report that will be shared.

## Next steps

- [[CLI Reference|CLI-Reference]] documents every standalone option.
- [[Artisan Command|Artisan-Command]] covers project-hosted Laravel use.
- [[Safety and Trust Boundaries|Safety-and-Trust-Boundaries]] explains credentials, restricted mode, paths, and untrusted projects.
- [[Troubleshooting and FAQ|Troubleshooting-and-FAQ]] maps common symptoms to fixes.
- [[Reading the Report|Reading-the-Report]] explains schema 0.8 for developers and managers.
