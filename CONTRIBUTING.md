# Contributing

Contributions should keep target projects immutable, report uncertainty explicitly, and preserve the separation between generic core logic and framework adapters.

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
composer test:core
composer test:cli
composer test:laravel
composer test:fixtures
composer analyse
composer lint
```

Use the Docker prefix when the host lacks PHP or Composer:

```bash
docker compose run --rm php composer test:fixtures
```

## Fixture snapshots

The six Laravel fixture reports are approval tests. Review both formats after an intentional behavior change, then regenerate them.

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
