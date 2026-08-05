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
