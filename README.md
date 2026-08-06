# PHP Upgrade Preflight

> [!IMPORTANT]
> **Work in progress:** PHP Upgrade Preflight is under active development and has not been released yet. The first release is coming soon, and behavior or report formats may change before then.

PHP Upgrade Preflight is a local, read-only analyzer for Composer-based PHP projects. It runs isolated Composer scenarios, compares lockfile states, groups dependency blockers, scans source usage, and produces evidence-backed JSON and Markdown reports before you modify your project.

Framework-specific rule packs can add deeper checks for Laravel, Symfony, CodeIgniter, and other ecosystems. Laravel is the first adapter, not the project identity.

## Packages

- `php-upgrade-preflight/core`: deterministic analysis logic, report models, Composer scenario execution, lock diffing, source scanning, and framework integration contracts.
- `php-upgrade-preflight/cli`: generic `upgrade-intel analyze` command.
- `php-upgrade-preflight/laravel`: thin Laravel service provider, Artisan command, detection, and initial Laravel rules.

The first package line targets PHP `^8.0` so it can be installed in many Laravel 8/9/10 projects while still analyzing older PHP platform constraints through Composer platform simulation.

## CLI

```bash
upgrade-intel analyze \
  --path=/projects/legacy-app \
  --from-php=7.4 \
  --target=php:8.1 \
  --target=laravel/framework:^9 \
  --format=json
```

## Laravel

```bash
php artisan upgrade:analyze \
  --target=laravel/framework:^8 \
  --target=php:8.0 \
  --from-php=7.4 \
  --format=markdown
```

The analyzer copies only `composer.json` and `composer.lock` into temporary workspaces for Composer scenarios. It never writes to the analyzed project. Every Composer scenario and diagnostic disables scripts and plugins. Temporary workspaces are removed after both successful and failed scenarios unless `--debug` explicitly preserves them. If cleanup itself fails, the scenario is reported as a cleanup failure and includes the leaked path so it can be inspected and removed manually.

When package and PHP targets are requested together, the report includes a `target-platform-only` probe that checks the requested PHP against current package constraints. It also runs `staged-targets` against the current PHP when that version is supplied with `--from-php` or configured through Composer's `config.platform.php`; otherwise, the staged probe is skipped and the report records the uncertainty. These probes help identify a safer ordering, but only full-target scenarios determine whether the combined upgrade is feasible.

Scenario selection has a stable priority: baseline validation, exact target, all-dependencies, minimal changes, platform-only, then staged targets. A later candidate is omitted when its normalized targets and effective Composer update options match an earlier candidate. PHP-only requests therefore skip the ineffective all-dependencies variant, and mixed requests skip `staged-targets` when the known current PHP already equals the requested target PHP. Intentional redundancy skips do not create uncertainty; missing current-PHP evidence still does.

## JSON report contract

JSON reports follow the versioned [v0.6 report schema](packages/core/resources/schema/upgrade-report-v0.6.schema.json). The previous [v0.5](packages/core/resources/schema/upgrade-report-v0.5.schema.json), [v0.4](packages/core/resources/schema/upgrade-report-v0.4.schema.json), [v0.3](packages/core/resources/schema/upgrade-report-v0.3.schema.json), and [v0.2](packages/core/resources/schema/upgrade-report-v0.2.schema.json) schemas remain available for consumers of earlier reports. Every report starts with metadata that identifies both the contract and the producing tool:

```json
{
  "metadata": {
    "schema_version": "0.6",
    "tool": {
      "name": "php-upgrade-preflight",
      "version": "0.1.0"
    }
  }
}
```

Consumers should select a parser by `schema_version`. Patch releases of the tool may change findings or fix analysis behavior while retaining the `0.6` report shape. The committed canonical report snapshot is at `packages/core/tests/Snapshots/upgrade-report-v0.6.json`.

Blockers expose both a stable type and actionable structure: the subject and requested constraint, blocking package and locked version when known, the conflicting constraint, dependency path, possible resolution options, confidence, and evidence references. Composer `prohibits --tree` diagnostics are preferred for dependency paths; bounded solver output is used as a fallback.

Package changes may include opaque `package_families` labels supplied by active integrations. The Laravel adapter identifies changed `laravel/*`, `illuminate/*`, and `symfony/*` packages as the `laravel`, `illuminate`, and `symfony` families. Core only coordinates generic classifiers and does not encode those framework package names.

Each Composer scenario records the resolved Composer version, exact command argv, elapsed milliseconds, exit code, bounded stdout/stderr excerpts, and a fingerprint of any readable candidate lock. Candidate-lock fingerprints include the file SHA-256, Composer `content-hash` when present, and the total locked package count; they retain traceable evidence after disposable workspaces are removed.

Scenario outcomes are machine-readable. In addition to successful resolution and solver or validation failures, reports distinguish a missing Composer executable, timeout, invalid JSON, missing candidate lockfile, process failure, workspace failure, and cleanup failure without requiring consumers to parse diagnostic text.

After a target-resolution solver failure, the analyzer runs bounded `composer prohibits --tree --locked` diagnostics in the same isolated workspace for requested targets that are not already satisfied by the baseline lock. Identical probes are reused within one analysis. The locked diagnostic requires Composer 2.4 or newer; older versions receive a structured unsupported diagnostic without replacing the primary solver outcome.

The transition section compares requested targets with root requirements, while the plan, tests, and uncertainties sections provide evidence-linked staged actions, project-aware validation guidance, and explicit limits on what dependency resolution proves. Markdown reports project these same canonical sections.

## Development with Docker

The default development interpreter is PHP 8.3 in Docker. Composer dependency resolution is pinned to PHP 8.0.30 in the root manifest so development dependencies remain compatible with the package runtime floor. PHP 8.4 and newer supported runtimes remain part of the CI compatibility matrix.

```bash
docker compose build php
docker compose run --rm php composer install
docker compose run --rm php composer validate --strict
```

Common shortcuts are also available through `make build`, `make install`, `make validate`, and `make shell`. Override the interpreter when checking another runtime on a POSIX shell:

```bash
PHP_VERSION=8.0 docker compose build php
PHP_VERSION=8.0 docker compose run --rm php php -v
```

In PowerShell, set `$env:PHP_VERSION = '8.0'` before running the same Docker Compose commands.

## Quality checks

PHPUnit 9.6 is used because it supports the PHP 8.0 runtime floor. Run the full local gate with:

```bash
docker compose run --rm php composer check
```

The gate validates the root and all package manifests before running tests, static analysis, and coding-style checks. Individual checks are available through `composer test`, `composer analyse`, and `composer lint`. Package unit suites can be run independently with `composer test:core`, `composer test:cli`, or `composer test:laravel`. GitHub Actions runs the same gate for pull requests and pushes to `main` across PHP 8.0 through PHP 8.5, the current stable release.

## License

Copyright 2026 Valentin Nikolaev. Free noncommercial use is available under the [PolyForm Noncommercial License 1.0.0](LICENSE). Commercial use requires a separate commercial license from the copyright holder.
