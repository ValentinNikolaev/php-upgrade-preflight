# Current compatibility coverage

Reviewed on 2026-10-08. This inventory records the versions available from PHP's release API, Composer's download page, and published package manifests at review time. CI uses branch constraints so it can detect subsequent ecosystem drift.

| Component | Reviewed release | Coverage or disposition |
|---|---|---|
| PHP | 8.5.11, 8.4.26, 8.3.35, 8.2.34 | Stable quality matrix covers PHP 8.0–8.5; development defaults to 8.5. |
| PHP preview | 8.6.0RC2 source; 8.6.0RC3 Windows build | Separate experimental PHP 8.6 unit/smoke job. No stable runtime or complete language-support claim. |
| Composer | 2.10.3 | Development image imports `composer:2`; current consumer jobs request Composer 2. |
| Laravel / Illuminate | 13.35.0 | Original PHP 8.3 host cases remain; current PHP 8.5 consumers boot the full framework and construct the Illuminate command. |
| Symfony | 8.1; Process 8.1.7, Filesystem 8.1.6, Console/Yaml 8.1.8 | Existing production `^8.0` ranges include 8.1. Current consumers require 8.1 explicitly. Development Yaml also permits 8.x; PHP 8.0 resolution retains 5.4. |
| PHPUnit | 13.4.1 | Current Laravel consumer executes a real offline upgrade through a PHPUnit 13 smoke test. Repository tests use the updated 9.6.38 release to retain PHP 8.0 compatibility. |
| Pest / Laravel plugin | 5.3.1 / 5.0.1 | Current Laravel consumer executes a real offline upgrade through a Pest 5 smoke test. Laravel 13 guidance accepts Pest 4 and 5; Laravel 12 accepts Pest 3 and 4. |
| PHP-Parser | 5.9.0 | Modern dependency branch now requires `^5.9`; source tests cover property hooks, pipes, and partial application. Legacy `^4.19` consumers report parse uncertainty for syntax they cannot read. |
| Composer Semver | 3.5.1 | Updated development lock; production `^3.4` already permits it. |
| PHP-CS-Fixer | 3.95.27 | Updated development lock; existing `^3.64` range permits it. |
| PHPStan | 2.3.0 | Updated development lock; existing `^2.1` range permits it. |
| Opis JSON Schema | 2.6.0 | Already current; existing `^2.6` range remains. |

Sources: [PHP release API](https://www.php.net/releases/?json&version=8&max=4), [PHP prerelease builds](https://www.php.net/pre-release-builds.php), [PHP support calendar](https://www.php.net/supported-versions.php), [Composer downloads](https://getcomposer.org/download/), [Symfony 8.1](https://symfony.com/releases/8.1), [PHPUnit support policy](https://phpunit.de/supported-versions.html), [Pest upgrade guide](https://pestphp.com/docs/upgrade-guide), and [PHP-Parser 5.9.0](https://github.com/nikic/PHP-Parser/releases/tag/v5.9.0).

Published metadata: [Laravel](https://repo.packagist.org/p2/laravel/framework.json), [Illuminate Console](https://repo.packagist.org/p2/illuminate/console.json), [PHPUnit](https://repo.packagist.org/p2/phpunit/phpunit.json), [Pest](https://repo.packagist.org/p2/pestphp/pest.json), [Pest Laravel plugin](https://repo.packagist.org/p2/pestphp/pest-plugin-laravel.json), [Symfony Process](https://repo.packagist.org/p2/symfony/process.json), [Filesystem](https://repo.packagist.org/p2/symfony/filesystem.json), [Console](https://repo.packagist.org/p2/symfony/console.json), [Yaml](https://repo.packagist.org/p2/symfony/yaml.json), [Semver](https://repo.packagist.org/p2/composer/semver.json), [PHP-CS-Fixer](https://repo.packagist.org/p2/friendsofphp/php-cs-fixer.json), [PHPStan](https://repo.packagist.org/p2/phpstan/phpstan.json), and [Opis](https://repo.packagist.org/p2/opis/json-schema.json).

The published PHPUnit 13.4.1 and Symfony 8.1 component manifests require PHP 8.4.1 or newer. Pest 5 requires PHP 8.4; its Laravel plugin 5.0.1 requires Laravel `^13.23.0`. These requirements belong to the consumer toolchain and do not raise the analyzer's PHP `^8.0` floor. Composer determines whether the exact PHP, framework, test-tool, and plugin combination can resolve. Package guidance does not certify application tests or override the solver.

The Pest alternatives are grounded in reviewed [plugin 4.1.0](https://github.com/pestphp/pest-plugin-laravel/blob/3057a36669ff11416cc0dc2b521b3aec58c488d0/composer.json) and [plugin 5.0.1](https://github.com/pestphp/pest-plugin-laravel/blob/d1564646e3198f1b607e64a36501d6edff7d104e/composer.json) manifests. Compatible older branches remain valid; the current-version consumer cases exercise newer choices independently.

An analyzer running on older PHP can model an exact newer target with `--target-php=8.5.11`. Known PHP 8.6 syntax can be scanned with PHP-Parser 5.9 without executing target source, but that does not establish exhaustive PHP 8.6 feature coverage. Existing historical fixtures, signed release contracts, report schema `0.8`, and release tags remain unchanged.

Local consumer verification used PHP 8.5.11 and Composer 2.10.3. All five current-runtime cases passed with normal and lowest dependency resolution: Core with current dependencies, CLI, Laravel with PHPUnit 13, Laravel with Pest 5 and its plugin, and Illuminate with Symfony 8.1. Each test-tool case performs a restricted, offline `fixture/dependency` 1.0.0→2.0.0 analysis targeting PHP 8.5.11 and verifies the report and successful solver scenarios. Temporary consumers are removed after each run.

The default development image directs PHP diagnostics to stderr and excludes deprecation notices from ordinary CLI error reporting. It also explicitly enables `register_argc_argv` so PHPStan recognizes arguments in repository CLI scripts on current PHP. Explicit `E_ALL` compatibility checks retain deprecation detection: legacy dependency branches selected to exercise PHP 8.0 may emit notices on newer runtimes, while the analyzer's privacy regression checks its own traversal without runtime diagnostics. Cyclic and repeated objects remain sanitized on PHP 8.0 through the PHP 8.6 preview. The preview workflow also runs the syntax and privacy regressions explicitly with `E_ALL`.

PHP 8.0.30 and PHP 8.6.0RC2 each passed all 1,382 unit tests and both smoke tests after the compatibility fixes. The preview still remains experimental: dependency deprecations and future prerelease changes are not stable-runtime guarantees. The Laravel source visitor preserves placeholder positions, avoiding invented config or test-double references when a partial application leaves its first argument unknown.
