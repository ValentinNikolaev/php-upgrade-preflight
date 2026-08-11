# Contributing

Contributions should keep target projects immutable, report uncertainty explicitly, and preserve the separation between generic core logic and framework adapters.

PHP Upgrade Preflight is a source-available public beta, not an Open Source project. Noncommercial use is permitted under the repository's [PolyForm Noncommercial License 1.0.0](LICENSE); commercial use requires a separate license. Contributions do not change that licensing model. See [Project status and licensing](docs/project-status.md) before contributing.

## Set up the repository

```bash
git clone https://github.com/ValentinNikolaev/php-upgrade-preflight.git
cd php-upgrade-preflight
docker compose build php
docker compose run --rm php composer install
docker compose run --rm php composer check
```

The development container uses PHP 8.3. Composer resolves dependencies against PHP 8.0.30 so new dependencies cannot silently raise the package floor.

## Run focused checks

```bash
composer test:unit
composer test:integration
composer test:smoke
composer test:all
composer test:core
composer test:cli
composer test:laravel
composer test:fixtures
composer analyse
composer lint
```

`test:unit`, `test:integration`, and `test:smoke` are disjoint. `test:all` runs those deterministic suites in order, and `composer check` uses `test:all` together with manifest validation, static analysis, and formatting. These commands do not perform dependency installation, vulnerability queries, or live package-resolution checks.

The GitHub `Compatibility smoke` workflow is the networked ecosystem gate. It creates clean temporary consumers, resolves both normal and lowest dependencies, and boots the package inside every supported Laravel host line. Its failures stay separate from offline test regressions. Dependency vulnerability data is likewise refreshed only in the scheduled and release audit workflows.

## Coverage, mutation, and budgets

The dedicated PHP 8.3 coverage job enables PCOV and runs:

```bash
composer test:coverage
composer test:mutation
```

`test:coverage` measures the full unit suite and compares it with the committed exact baseline. Overall and critical-module ratios may not decline, and newly uncovered source-line fingerprints fail the ratchet; there is no hand-picked percentage threshold. Update the baseline with `php tools/verify-coverage.php build/coverage/clover.xml --write-baseline` only after reviewing a successful full-unit Clover report.

`test:mutation` runs after coverage and must kill the six configured mutants for scenario selection, blocker parsing, schema validation, risk and effort, Laravel transition selection, and release verification. The integration suite also enforces the v0.2 representative-corpus limits for runtime, peak memory, per-report size, and combined report size.

Use the Docker prefix when the host lacks PHP or Composer:

```bash
docker compose run --rm php composer test:fixtures
```

## Fixture snapshots

The six Laravel fixture reports are approval tests. Review both formats after an intentional behavior change, then regenerate them.

The v0.1 compatibility manifest at `tests/fixtures/contracts/v0.1.json` locks the public PHP operation, generic CLI surface, exit policy, schema `0.6`, and byte-for-byte report artifacts archived from the signed `v0.1.0` tag under `tests/fixtures/contracts/v0.1/laravel-reports`. Current development snapshots remain separate under `packages/laravel/tests/Snapshots`. Do not update the archived reports or manifest during ordinary snapshot regeneration. A correction to the released v0.1 baseline requires explicit compatibility review and documented provenance.

POSIX shell:

```bash
PHP_UPGRADE_PREFLIGHT_UPDATE_SNAPSHOTS=1 vendor/bin/phpunit --filter LaravelFixtureAnalysisTest
```

PowerShell:

```powershell
$env:PHP_UPGRADE_PREFLIGHT_UPDATE_SNAPSHOTS = '1'
vendor\bin\phpunit --filter LaravelFixtureAnalysisTest
Remove-Item Env:PHP_UPGRADE_PREFLIGHT_UPDATE_SNAPSHOTS
```

Docker:

```bash
docker compose run --rm -e PHP_UPGRADE_PREFLIGHT_UPDATE_SNAPSHOTS=1 php composer test:fixtures
```

Inspect the diff instead of accepting snapshots from a failing or partially understood run. Snapshot normalization removes absolute roots, host separators, and timing noise. It retains commands, outcomes, findings, evidence, and lock fingerprints.

## Report schema changes

Keep existing schema files immutable. A breaking or additive report-shape change needs a new schema version, schema file, canonical core snapshot, and consumer documentation. Finding and guidance corrections can retain the schema version when the serialized shape stays compatible.

## Pull requests

- Add focused tests for behavior changes and regression cases.
- Run `composer check` before opening the pull request.
- Update public documentation when command behavior or report semantics change.
- Do not commit preserved debug workspaces, generated reports, credentials, or unrelated formatting changes.

Report security issues through [SECURITY.md](SECURITY.md), not a public issue.

By contributing, you agree that your contribution is licensed under the repository's [PolyForm Noncommercial License 1.0.0](LICENSE).

## Releases and versions

Run the release workflow manually to exercise the release gates and build archives without publishing. A matching annotated, GitHub-verified `vMAJOR.MINOR.PATCH` tag on the approved release line reruns the gates and publishes the GitHub release only after the quality, compatibility, fresh-clone, and artifact-consumer jobs succeed. The active `0.2.x` release line publishes from `main`; the signed v0.1 compatibility contract remains frozen and is not regenerated during v0.2 work.

Before tagging, run:

```bash
composer release:verify -- 0.2.1
```

See [Versioning](docs/versioning.md) for the `0.x` policy and active `0.2.x` release line, and [the release checklist](docs/release-checklist.md) for dependency-inventory, provenance, distribution-repository, and Packagist steps.
