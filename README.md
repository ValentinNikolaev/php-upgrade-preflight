# PHP Upgrade Preflight

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

The analyzer copies only `composer.json` and `composer.lock` into temporary workspaces for Composer scenarios. It never writes to the analyzed project.

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

Individual checks are available through `composer test`, `composer analyse`, and `composer lint`. Package unit suites can be run independently with `composer test:core`, `composer test:cli`, or `composer test:laravel`. GitHub Actions runs the same gate for pull requests and pushes to `main` on PHP 8.0.
