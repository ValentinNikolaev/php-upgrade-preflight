# Contributing

Contributions should keep target projects immutable, report uncertainty explicitly, and preserve the separation between generic core logic and framework adapters.

PHP Upgrade Preflight is an Open Source public beta under the [MIT License](LICENSE). See [Project status and licensing](docs/project-status.md) before contributing.

## Accepted contributions

Code, test, fixture, and documentation contributions are welcome. By contributing, you agree that your contribution is licensed under the repository's [MIT License](LICENSE), the same license that covers the project (inbound=outbound). No contributor license agreement is required, and you must have the right to submit the work under that license.

Before starting a large change, open an issue first to confirm the direction. Contributions must keep the non-negotiable product rules intact: analyzed projects stay immutable, findings stay evidence-backed, JSON stays canonical, and the core stays framework-neutral.

Bug reports and product feedback are welcome through [GitHub issues](https://github.com/ValentinNikolaev/php-upgrade-preflight/issues). Report security vulnerabilities privately through [the security policy](SECURITY.md), not through an issue or pull request.

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

If `docker compose run --rm php composer check` reports killed subprocesses partway through the integration suite, the cause is Composer's default 300-second `process-timeout`: that suite runs real Composer scenarios for roughly eight minutes inside one script. Run the gate as `docker compose run --rm -e COMPOSER_PROCESS_TIMEOUT=0 php composer check`. CI is unaffected — no workflow sets that variable and none has hit the limit — and the analyzer's own Composer timeouts are separate, bounded product settings that this variable does not change.

The repository's own command-line helpers live in `tools/` and are documented in [the tools guide](tools/README.md).

`test:coverage` measures the full unit suite and compares it with the committed exact baseline. Overall and critical-module ratios may not decline, and newly uncovered source-line fingerprints fail the ratchet; there is no hand-picked percentage threshold. Update the baseline with `php tools/verify-coverage.php build/coverage/clover.xml --write-baseline` only after reviewing a successful full-unit Clover report.

`test:mutation` runs after coverage and must kill every configured selective mutant, including profile completeness and precedence, restricted execution, stage chaining, fingerprint validation, stop-on-gap behavior, blocker aggregation, old-adapter compatibility, schema validation, and release policy. The integration suite also enforces the v0.3 per-stage and worst-supported-chain limits for Composer processes, runtime, peak memory, report size, redaction, and deterministic reruns.

Use the Docker prefix when the host lacks PHP or Composer:

```bash
docker compose run --rm php composer test:fixtures
```

## Fixture snapshots

The six Laravel fixture reports are approval tests. Review both formats after an intentional behavior change, then regenerate them.

The v0.1 and v0.2.1 compatibility manifests lock their public operations, command surfaces, exit policies, schemas, and byte-for-byte signed report artifacts under `tests/fixtures/contracts/v0.1` and `tests/fixtures/contracts/v0.2.1`. Current schema 0.8 snapshots remain separate under the package test trees. Do not update either archive during ordinary snapshot regeneration; a released-baseline correction requires explicit compatibility review and documented provenance.

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

- Keep each pull request focused on one coherent change, with tests for every behavior change.
- Update every affected public document — `README.md`, `docs/`, `CHANGELOG.md` — in the same pull request when behavior, commands, report semantics, or supported versions change.
- Run the narrowest relevant checks during development and `composer check` before opening the pull request.
- Check links, commands, version claims, and examples in any changed documentation.
- Do not commit preserved debug workspaces, generated reports, credentials, or unrelated formatting changes.

Report security issues through [SECURITY.md](SECURITY.md), not a public issue.

## Releases and versions

Run the release workflow manually to exercise the release gates and build archives without publishing. A matching annotated, GitHub-verified `vMAJOR.MINOR.PATCH` tag on the approved release line reruns the gates and publishes the GitHub release only after the quality, compatibility, fresh-clone, and artifact-consumer jobs succeed. The active `0.3.x` release line publishes from `main`; v0.2 maintenance publishes from `0.2.x`, and the signed v0.1 and v0.2 compatibility contracts remain frozen.

Before tagging, run:

```bash
composer release:verify -- 0.3.1
```

See [Versioning](docs/versioning.md) for the `0.x` policy and active `0.3.x` release line, and [the release checklist](docs/release-checklist.md) for dependency-inventory, provenance, distribution-repository, and Packagist steps.
