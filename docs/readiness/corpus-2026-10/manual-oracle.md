# Independent source/lock oracle — R4 corpus

Status: **SOURCE-ONLY BASELINE; ALL TOOL COMPARISONS PENDING** (2026-10-17).
This status records the blinded pre-report checkpoint. Later comparisons are in [case reviews A](case-review-a.md), [B](case-review-b.md) and [C](case-review-c.md); the predeclared checks below remain unchanged.
Prepared without opening analyzer pilot/final reports or executing application code, scripts, tests or installs.
This engineering review is not a participant normal-workflow comparison, time benefit, demand or reuse outcome; it does not complete R3.
Authority: [frozen inputs/requests](projects.json), [campaign protocol](protocol.md), [R3 gates](../decision-study.md).
Source location during evaluation: `<TASK_TEMP>/apps/<id>`; the reviewer created no temporary files.
All 11 manifest/lock byte hashes were independently checked against projects.json during this review; full-tree revalidation is intake evidence, not repeated here.

## Interpretation and bounded scope

- Each section links the exact project commit and pinned files; line anchors locate package objects. `require` entries below are selected exact metadata, not complete dependency graphs.
- Root values are `composer.json#/require/{php,laravel/framework}`; platform is `config.platform.php`. Ecosystem rows give production/dev root ranges and lock `require` keys.
- Lock entries describe recorded versions, not installed vendor code: `vendor/` is absent in every snapshot. Framework replacement of Illuminate components must be considered.
- Every root framework range below excludes its requested next major: an unchanged root range needs adjustment to request that major. This does not identify the first solver blocker.
- A locked dependency excluding the target proves that version cannot accompany the target under its recorded constraints, not that all available versions or the root range are incompatible.
- No registry/network solve, upgrade availability, target application tests, migration or deployment validation was performed. PHP allowance alone is not full compatibility.
- Source checks are narrow follow-up questions, not deprecated-API verdicts. Guide-based statements use the [pinned guide ledger](../../laravel-coverage/modern-guide-audit.json); no new API-removal claims are invented.
- Tests are metadata only. PHPUnit PHP ranges do not themselves constrain Laravel; distinguish framework testing support, guide recommendations and exact patch floors.

## Per-project facts and predeclared checks

### crater

Pin `05d5ce26fdd8d9466009163444e944259bc0cc2a`: [manifest](https://github.com/crater-invoice-inc/crater/blob/05d5ce26fdd8d9466009163444e944259bc0cc2a/composer.json), [lock](https://github.com/crater-invoice-inc/crater/blob/05d5ce26fdd8d9466009163444e944259bc0cc2a/composer.lock#L2290); nearest: Laravel 9, PHP 7.4.0→8.1.0; longest: Laravel 13, PHP 7.4.0→8.3.0.
Root framework `^8.0`, PHP `^7.4 || ^8.0`; locked framework `v8.83.16` requires PHP `^7.3|^8.0`; platform PHP `absent`.

| Rooted package (root range) | Locked version; selected exact `require` metadata |
| --- | --- |
| [barryvdh/laravel-dompdf](https://github.com/crater-invoice-inc/crater/blob/05d5ce26fdd8d9466009163444e944259bc0cc2a/composer.lock#L207) (`^0.9.0`) | `v0.9.0`; `php=^7.1 \|\| ^8.0`; `illuminate/support=^5.5\|^6\|^7\|^8` |
| [facade/ignition](https://github.com/crater-invoice-inc/crater/blob/05d5ce26fdd8d9466009163444e944259bc0cc2a/composer.lock#L10737) (`^2.3.6` dev) | `2.17.5`; `php=^7.2.5\|^8.0`; `illuminate/support=^7.0\|^8.0`; `symfony/console=^5.0` |
| [phpunit/phpunit](https://github.com/crater-invoice-inc/crater/blob/05d5ce26fdd8d9466009163444e944259bc0cc2a/composer.lock#L4898) (`^9.3` dev) | `9.5.20`; `php=>=7.3` |

Repositories: no root repositories key (no declared custom VCS/path repository). Layout: `Crater` → `app/`; database factories/seeders; `Modules` → `Modules/` is declared but directory absent; `tests/` exists.
Checks (comparison **PENDING**): **1.** The locked Dompdf wrapper and Ignition exclude Laravel 9; neither proves no newer allowed version exists. Distinguish a locked-version change from a necessary root-range change.
**2.** [app/Http/Middleware/VerifyCsrfToken.php:5,7](https://github.com/crater-invoice-inc/crater/blob/05d5ce26fdd8d9466009163444e944259bc0cc2a/app/Http/Middleware/VerifyCsrfToken.php#L5) extends framework middleware; inspect inherited behavior before any missing-method claim; inspect Sanctum configuration and tests separately.

### bookstack

Pin `07e45a20e55fc955e9ef252d0653f23193a71a77`: [manifest](https://github.com/BookStackApp/BookStack/blob/07e45a20e55fc955e9ef252d0653f23193a71a77/composer.json), [lock](https://github.com/BookStackApp/BookStack/blob/07e45a20e55fc955e9ef252d0653f23193a71a77/composer.lock#L2087); nearest: Laravel 11, PHP 8.1.0→8.2.0; longest: Laravel 13, PHP 8.1.0→8.3.0.
Root framework `^10.48.23`, PHP `^8.1.0`; locked framework `v10.48.25` requires PHP `^8.1`; platform PHP `8.1.0`.

| Rooted package (root range) | Locked version; selected exact `require` metadata |
| --- | --- |
| [ssddanbrown/symfony-mailer](https://github.com/BookStackApp/BookStack/blob/07e45a20e55fc955e9ef252d0653f23193a71a77/composer.lock#L5571) (`6.4.x-dev`) | `6.4.x-dev`; `php=>=8.1`; `symfony/mime=^6.2\|^7.0`; `replace={"symfony/mailer":"^6.0"}` |
| [nunomaduro/collision](https://github.com/BookStackApp/BookStack/blob/07e45a20e55fc955e9ef252d0653f23193a71a77/composer.lock#L8491) (`^7.0` dev) | `v7.11.0`; `php=^8.1.0`; `symfony/console=^6.4.12` |
| [phpunit/phpunit](https://github.com/BookStackApp/BookStack/blob/07e45a20e55fc955e9ef252d0653f23193a71a77/composer.lock#L9171) (`^10.0` dev) | `10.5.38`; `php=>=8.1` |

Repositories: no root repositories key (no declared custom VCS/path repository). Layout: `BookStack` → `app/`; configuration is `app/Config/`, not root `config/`; tests under `tests/`.
Checks (comparison **PENDING**): **1.** Mailer fork replaces `symfony/mailer` with `^6.0`; check replacement semantics for each target, not only its own require list. Collision is tied to Symfony 6.
**2.** Pinned guide ledger permits PHPUnit 10.5.38 for 10→11; mandatory PHPUnit 11 advice would need contrary evidence. [app/Http/Middleware/VerifyCsrfToken.php:5,7](https://github.com/BookStackApp/BookStack/blob/07e45a20e55fc955e9ef252d0653f23193a71a77/app/Http/Middleware/VerifyCsrfToken.php#L5) inherits framework behavior.

### lychee

Pin `bf5495c706a9971ebfcd03a88c36e336fc0261d5`: [manifest](https://github.com/LycheeOrg/Lychee/blob/bf5495c706a9971ebfcd03a88c36e336fc0261d5/composer.json), [lock](https://github.com/LycheeOrg/Lychee/blob/bf5495c706a9971ebfcd03a88c36e336fc0261d5/composer.lock#L2413); nearest: Laravel 13, PHP 8.3.0→8.3.0; longest: Laravel 13, PHP 8.3.0→8.3.0.
Root framework `^12.0`, PHP `^8.3`; locked framework `v12.36.1` requires PHP `^8.2`; platform PHP `8.3`.

| Rooted package (root range) | Locked version; selected exact `require` metadata |
| --- | --- |
| [opcodesio/log-viewer](https://github.com/LycheeOrg/Lychee/blob/bf5495c706a9971ebfcd03a88c36e336fc0261d5/composer.lock#L5308) (`dev-lycheeOrg`) | `dev-lycheeOrg`; `php=^8.0`; `illuminate/contracts=^8.0\|^9.0\|^10.0\|^11.0\|^12.0` |
| [lychee-org/nestedset](https://github.com/LycheeOrg/Lychee/blob/bf5495c706a9971ebfcd03a88c36e336fc0261d5/composer.lock#L3858) (`^10.0.3`) | `v10.0.3`; `php=^8.3`; `illuminate/support=^11.0\|^12.0`; `illuminate/database=^11.0\|^12.0` |
| [phpunit/phpunit](https://github.com/LycheeOrg/Lychee/blob/bf5495c706a9971ebfcd03a88c36e336fc0261d5/composer.lock#L14764) (`^11.0` dev) | `11.5.42`; `php=>=8.2` |

Repositories: `vcs: https://github.com/LycheeOrg/phpstan-lychee`; `vcs: https://github.com/LycheeOrg/php-exif`; `vcs: https://github.com/LycheeOrg/log-viewer`; `vcs: https://github.com/LycheeOrg/verify`. Layout: `App` → `app/`, `Scripts` → `scripts/`; dev mapping declares `tests/` and `phpstan/`; `tests/` absent; `phpunit.xml` exists.
Checks (comparison **PENDING**): **1.** Both selected ecosystem locks exclude Laravel 13; branch-name availability or future package support is unknown. Nearest/longest are the same target, not distinct transition coverage. Locked PHPUnit 11.5.42 is below the pinned ledger's separately reviewed 11.5.50 floor; review the patch floor, not a blanket PHPUnit 12 requirement.
**2.** [app/Http/Middleware/VerifyCsrfToken.php:45,52,62,68](https://github.com/LycheeOrg/Lychee/blob/bf5495c706a9971ebfcd03a88c36e336fc0261d5/app/Http/Middleware/VerifyCsrfToken.php#L45) overrides handle/isReading and calls parent; review behavior/signatures before CSRF advice. Omitted `bootstrap/cache` and `public/storage` symlinks are declared non-source limits.

### snipeit

Pin `91a423e60eca0e5f96a1564e0c1fae8d360c4f15`: [manifest](https://github.com/grokability/snipe-it/blob/91a423e60eca0e5f96a1564e0c1fae8d360c4f15/composer.json), [lock](https://github.com/grokability/snipe-it/blob/91a423e60eca0e5f96a1564e0c1fae8d360c4f15/composer.lock#L2672); nearest: Laravel 11, PHP 8.1.0→8.2.0; longest: Laravel 13, PHP 8.1.0→8.3.0.
Root framework `^10.0`, PHP `^8.1`; locked framework `v10.48.4` requires PHP `^8.1`; platform PHP `absent`.

| Rooted package (root range) | Locked version; selected exact `require` metadata |
| --- | --- |
| [laravel/passport](https://github.com/grokability/snipe-it/blob/91a423e60eca0e5f96a1564e0c1fae8d360c4f15/composer.lock#L2936) (`^11.0`) | `v11.10.6`; `php=^8.0`; `illuminate/support=^9.0\|^10.0`; `illuminate/contracts=^9.0\|^10.0`; `illuminate/database=^9.0\|^10.0` |
| [livewire/livewire](https://github.com/grokability/snipe-it/blob/91a423e60eca0e5f96a1564e0c1fae8d360c4f15/composer.lock#L4477) (`^2.4`) | `v2.12.6`; `php=^7.2.5\|^8.0`; `illuminate/support=^7.0\|^8.0\|^9.0\|^10.0`; `illuminate/database=^7.0\|^8.0\|^9.0\|^10.0` |
| [phpunit/phpunit](https://github.com/grokability/snipe-it/blob/91a423e60eca0e5f96a1564e0c1fae8d360c4f15/composer.lock#L14167) (`^9.0` dev) | `9.6.18`; `php=>=7.3` |

Repositories: `vcs: https://github.com/grokability/laravel-scim-server`. Layout: `App` → `app/`; database classmap/factories/seeders; `tests/` and `config/` exist.
Checks (comparison **PENDING**): **1.** Locked Passport and Livewire exclude Laravel 11, therefore also 13; identify candidate changes without declaring their root ranges unsatisfiable from this lock alone.
**2.** [config/auth.php:45](https://github.com/grokability/snipe-it/blob/91a423e60eca0e5f96a1564e0c1fae8d360c4f15/config/auth.php#L45) uses passport; review authentication/migrations and [app/Http/Kernel.php](https://github.com/grokability/snipe-it/blob/91a423e60eca0e5f96a1564e0c1fae8d360c4f15/app/Http/Kernel.php) wiring. Old Kernel layout alone does not require a Laravel 11 skeleton rewrite.

### invoiceninja

Pin `c064f1c3e9163ca0758eb36df5d7947b5c8e19c6`: [manifest](https://github.com/invoiceninja/invoiceninja/blob/c064f1c3e9163ca0758eb36df5d7947b5c8e19c6/composer.json), [lock](https://github.com/invoiceninja/invoiceninja/blob/c064f1c3e9163ca0758eb36df5d7947b5c8e19c6/composer.lock#L4611); nearest: Laravel 12, PHP 8.2.0→8.2.0; longest: Laravel 13, PHP 8.2.0→8.3.0.
Root framework `^11.0`, PHP `^8.2`; locked framework `v11.11.1` requires PHP `^8.2`; platform PHP `absent`.

| Rooted package (root range) | Locked version; selected exact `require` metadata |
| --- | --- |
| [awobaz/compoships](https://github.com/invoiceninja/invoiceninja/blob/c064f1c3e9163ca0758eb36df5d7947b5c8e19c6/composer.lock#L421) (`^2.1`) | `2.3.0`; `illuminate/database=>=5.6 <12.0` |
| [invoiceninja/einvoice](https://github.com/invoiceninja/invoiceninja/blob/c064f1c3e9163ca0758eb36df5d7947b5c8e19c6/composer.lock#L4055) (`dev-main`) | `dev-main`; `php=^8.2`; `illuminate/collections=^10\|^11` |
| [phpunit/phpunit](https://github.com/invoiceninja/invoiceninja/blob/c064f1c3e9163ca0758eb36df5d7947b5c8e19c6/composer.lock#L17398) (`^10` dev) | `10.5.24`; `php=>=8.1` |

Repositories: `vcs: https://github.com/turbo124/apple`; `vcs: https://github.com/invoiceninja/einvoice`; `vcs: https://github.com/turbo124/orderx`; `vcs: https://github.com/beganovich/php-ansible`. Layout: `App` → `app/`; database factories/seeders; three autoload helper files under `app/Helpers/`; tests include Integration suite.
Checks (comparison **PENDING**): **1.** Locked Compoships excludes 12+ and the einvoice branch excludes 12/13. Review VCS branch provenance and candidate metadata before prescribing a root-range edit.
**2.** [app/Http/Middleware/VerifyCsrfToken.php:14,16](https://github.com/invoiceninja/invoiceninja/blob/c064f1c3e9163ca0758eb36df5d7947b5c8e19c6/app/Http/Middleware/VerifyCsrfToken.php#L14) inherits framework middleware; `phpunit.xml:9` declares Integration tests. Neither static lock review nor package resolution validates billing or migration behavior.

### akaunting

Pin `465656fa69ab39ccd22a6aab93014f7e1aa81db8`: [manifest](https://github.com/akaunting/akaunting/blob/465656fa69ab39ccd22a6aab93014f7e1aa81db8/composer.json), [lock](https://github.com/akaunting/akaunting/blob/465656fa69ab39ccd22a6aab93014f7e1aa81db8/composer.lock#L4970); nearest: Laravel 11, PHP 8.1.0→8.2.0; longest: Laravel 13, PHP 8.1.0→8.3.0.
Root framework `^10.0`, PHP `^8.1`; locked framework `v10.25.2` requires PHP `^8.1`; platform PHP `absent`.

| Rooted package (root range) | Locked version; selected exact `require` metadata |
| --- | --- |
| [akaunting/laravel-module](https://github.com/akaunting/akaunting/blob/465656fa69ab39ccd22a6aab93014f7e1aa81db8/composer.lock#L345) (`^3.0`) | `3.0.1`; `php=^8.0` |
| [livewire/livewire](https://github.com/akaunting/akaunting/blob/465656fa69ab39ccd22a6aab93014f7e1aa81db8/composer.lock#L6211) (`^2.10`) | `v2.12.6`; `php=^7.2.5\|^8.0`; `illuminate/support=^7.0\|^8.0\|^9.0\|^10.0`; `illuminate/database=^7.0\|^8.0\|^9.0\|^10.0` |
| [phpunit/phpunit](https://github.com/akaunting/akaunting/blob/465656fa69ab39ccd22a6aab93014f7e1aa81db8/composer.lock#L14409) (`^10.0` dev) | `10.3.5`; `php=>=8.1` |

Repositories: no root repositories key (no declared custom VCS/path repository). Layout: `App` → array `[app/]`; `Modules` → `modules/` (only `.gitkeep` observed); overrides for Illuminate, Livewire, Excel, Symfony and module commands.
Checks (comparison **PENDING**): **1.** Module package has only PHP in require; absence of an Illuminate restriction does not prove compatibility. Check locked Livewire restrictions separately from first-party overrides.
**2.** `composer.json#/autoload/exclude-from-classmap` substitutes vendor classes with `overrides/`; `phpunit.xml:9` includes module tests. Ask whether analysis covers these roots before declaring missing source advice.

### monica

Pin `32028ce3ce79cef38df5d27a297e5b20680f0065`: [manifest](https://github.com/monicahq/monica/blob/32028ce3ce79cef38df5d27a297e5b20680f0065/composer.json), [lock](https://github.com/monicahq/monica/blob/32028ce3ce79cef38df5d27a297e5b20680f0065/composer.lock#L2880); nearest: Laravel 10, PHP 8.1.0→8.1.0; longest: Laravel 13, PHP 8.1.0→8.3.0.
Root framework `^9.0`, PHP `^8.1`; locked framework `v9.52.16` requires PHP `^8.0.2`; platform PHP `absent`.

| Rooted package (root range) | Locked version; selected exact `require` metadata |
| --- | --- |
| [laravel/passport](https://github.com/monicahq/monica/blob/32028ce3ce79cef38df5d27a297e5b20680f0065/composer.lock#L3078) (`^11.0`) | `v11.10.6`; `php=^8.0`; `illuminate/support=^9.0\|^10.0`; `illuminate/contracts=^9.0\|^10.0`; `illuminate/database=^9.0\|^10.0` |
| [laravel/cashier](https://github.com/monicahq/monica/blob/32028ce3ce79cef38df5d27a297e5b20680f0065/composer.lock#L2796) (`^13.0`) | `v13.17.0`; `php=^7.3\|^8.0`; `illuminate/support=^8.37\|^9.0\|^10.0`; `illuminate/contracts=^8.37\|^9.0\|^10.0`; `illuminate/database=^8.37\|^9.0\|^10.0` |
| [phpunit/phpunit](https://github.com/monicahq/monica/blob/32028ce3ce79cef38df5d27a297e5b20680f0065/composer.lock#L16299) (`^9.0` dev) | `9.6.19`; `php=>=7.3` |

Repositories: no root repositories key (no declared custom VCS/path repository). Layout: `App` → `app/`; database seeds/factories classmap and [app/Helpers/helpers.php](https://github.com/monicahq/monica/blob/32028ce3ce79cef38df5d27a297e5b20680f0065/app/Helpers/helpers.php); `tests/` and phpunit.xml[.dist] absent.
Checks (comparison **PENDING**): **1.** Locked Passport/Cashier admit the nearest Laravel 10 but exclude 11–13; do not call them nearest-hop incompatibilities based on framework requirements alone.
**2.** [config/auth.php:45](https://github.com/monicahq/monica/blob/32028ce3ce79cef38df5d27a297e5b20680f0065/config/auth.php#L45) uses passport; test/billing validation remains unavailable in this snapshot. Review original-snapshot source separately at each planned hop.

### koel

Pin `ad9d1b57e15f1e14d915ac5c7ada7206bf9a3b71`: [manifest](https://github.com/koel/koel/blob/ad9d1b57e15f1e14d915ac5c7ada7206bf9a3b71/composer.json), [lock](https://github.com/koel/koel/blob/ad9d1b57e15f1e14d915ac5c7ada7206bf9a3b71/composer.lock#L2373); nearest: Laravel 11, PHP 8.1.0→8.2.0; longest: Laravel 13, PHP 8.1.0→8.3.0.
Root framework `^10.0`, PHP `>=8.1`; locked framework `v10.48.8` requires PHP `^8.1`; platform PHP `absent`.

| Rooted package (root range) | Locked version; selected exact `require` metadata |
| --- | --- |
| [laravel/sanctum](https://github.com/koel/koel/blob/ad9d1b57e15f1e14d915ac5c7ada7206bf9a3b71/composer.lock#L2694) (`^3.3`) | `v3.3.3`; `php=^8.0.2`; `illuminate/support=^9.21\|^10.0`; `illuminate/contracts=^9.21\|^10.0`; `illuminate/database=^9.21\|^10.0` |
| [laravel/tinker](https://github.com/koel/koel/blob/ad9d1b57e15f1e14d915ac5c7ada7206bf9a3b71/composer.lock#L10484) (`^2.9` dev) | `v2.9.0`; `php=^7.2.5\|^8.0`; `illuminate/support=^6.0\|^7.0\|^8.0\|^9.0\|^10.0\|^11.0`; `illuminate/contracts=^6.0\|^7.0\|^8.0\|^9.0\|^10.0\|^11.0` |
| [phpunit/phpunit](https://github.com/koel/koel/blob/ad9d1b57e15f1e14d915ac5c7ada7206bf9a3b71/composer.lock#L11528) (`^10.1` dev) | `10.5.19`; `php=>=8.1` |

Repositories: no root repositories key (no declared custom VCS/path repository). Layout: `App` → `app/`; database classmap and [app/Helpers.php](https://github.com/koel/koel/blob/ad9d1b57e15f1e14d915ac5c7ada7206bf9a3b71/app/Helpers.php); `tests/`, `phpunit.xml.dist` exist; case-sensitive song fixtures preserved.
Checks (comparison **PENDING**): **1.** Locked Sanctum excludes 11; Tinker permits 11 but excludes 13. Do not conflate these nearest and longest expectations.
**2.** [config/auth.php:40](https://github.com/koel/koel/blob/ad9d1b57e15f1e14d915ac5c7ada7206bf9a3b71/config/auth.php#L40) uses sanctum; review [config/sanctum.php:41](https://github.com/koel/koel/blob/ad9d1b57e15f1e14d915ac5c7ada7206bf9a3b71/config/sanctum.php#L41) middleware and stateful-auth validation. `tests/songs/subdir` has case-distinct MP3 fixtures, not a source incompatibility.

### bagisto

Pin `8c33fbbd89b64043d98697f5590d6de658a9241b`: [manifest](https://github.com/bagisto/bagisto/blob/8c33fbbd89b64043d98697f5590d6de658a9241b/composer.json), [lock](https://github.com/bagisto/bagisto/blob/8c33fbbd89b64043d98697f5590d6de658a9241b/composer.lock#L2796); nearest: Laravel 11, PHP 8.1.0→8.2.0; longest: Laravel 13, PHP 8.1.0→8.3.0.
Root framework `^10.0`, PHP `^8.1`; locked framework `v10.41.0` requires PHP `^8.1`; platform PHP `absent`.

| Rooted package (root range) | Locked version; selected exact `require` metadata |
| --- | --- |
| [laravel/sanctum](https://github.com/bagisto/bagisto/blob/8c33fbbd89b64043d98697f5590d6de658a9241b/composer.lock#L3146) (`^3.2`) | `v3.3.3`; `php=^8.0.2`; `illuminate/support=^9.21\|^10.0`; `illuminate/contracts=^9.21\|^10.0`; `illuminate/database=^9.21\|^10.0` |
| [pestphp/pest-plugin-laravel](https://github.com/bagisto/bagisto/blob/8c33fbbd89b64043d98697f5590d6de658a9241b/composer.lock#L11826) (`^2.1` dev) | `v2.2.0`; `php=^8.1.0`; `laravel/framework=^10.18.0\|^11.0` |
| [phpunit/phpunit](https://github.com/bagisto/bagisto/blob/8c33fbbd89b64043d98697f5590d6de658a9241b/composer.lock#L12542) (`^10.0` dev) | `10.5.5`; `php=>=8.1` |

Repositories: no root repositories key (no declared custom VCS/path repository). Layout: `App` → `app/` plus 31 `Webkul` namespace roots in `packages/Webkul/*/src`; dev roots include package tests; no path repository.
Checks (comparison **PENDING**): **1.** Locked Sanctum excludes 11 while the Pest Laravel plugin explicitly allows 11; framework support and testing-tool requirements need separate treatment.
**2.** [packages/Webkul/Core/src/Providers/CoreServiceProvider.php](https://github.com/bagisto/bagisto/blob/8c33fbbd89b64043d98697f5590d6de658a9241b/packages/Webkul/Core/src/Providers/CoreServiceProvider.php) and `packages/Webkul/Shop/src/Http/Middleware/` are first-party source; `phpunit.xml:11` points into package tests. Coverage is a question until report scope is known.

### firefly

Pin `cd0201074c6e2ea4ea6a0353a05c893f7d9655a3`: [manifest](https://github.com/firefly-iii/firefly-iii/blob/cd0201074c6e2ea4ea6a0353a05c893f7d9655a3/composer.json), [lock](https://github.com/firefly-iii/firefly-iii/blob/cd0201074c6e2ea4ea6a0353a05c893f7d9655a3/composer.lock#L1876); nearest: Laravel 12, PHP 8.4.0→8.4.0; longest: Laravel 13, PHP 8.4.0→8.4.0.
Root framework `^11`, PHP `>=8.4`; locked framework `v11.41.3` requires PHP `^8.2`; platform PHP `absent`.

| Rooted package (root range) | Locked version; selected exact `require` metadata |
| --- | --- |
| [laravel/sanctum](https://github.com/firefly-iii/firefly-iii/blob/cd0201074c6e2ea4ea6a0353a05c893f7d9655a3/composer.lock#L2226) (`^4`) | `v4.0.8`; `php=^8.2`; `illuminate/support=^11.0\|^12.0`; `illuminate/contracts=^11.0\|^12.0`; `illuminate/database=^11.0\|^12.0`; `symfony/console=^7.0` |
| [barryvdh/laravel-ide-helper](https://github.com/firefly-iii/firefly-iii/blob/cd0201074c6e2ea4ea6a0353a05c893f7d9655a3/composer.lock#L10196) (`^3` dev) | `v3.5.4`; `php=^8.2`; `illuminate/support=^11.15`; `illuminate/database=^11.15` |
| [phpunit/phpunit](https://github.com/firefly-iii/firefly-iii/blob/cd0201074c6e2ea4ea6a0353a05c893f7d9655a3/composer.lock#L11822) (`^11` dev) | `11.5.6`; `php=>=8.2` |

Repositories: no root repositories key (no declared custom VCS/path repository). Layout: `FireflyIII` → `app/`; `Domain` → `domain/` declared but absent; factories/seeders and `tests/` present; `patches/` contains frontend patch.
Checks (comparison **PENDING**): **1.** Locked Sanctum admits 12 but excludes 13; locked IDE Helper admits only ^11.15. Separate development-package candidate work from production auth work.
**2.** [config/auth.php:77](https://github.com/firefly-iii/firefly-iii/blob/cd0201074c6e2ea4ea6a0353a05c893f7d9655a3/config/auth.php#L77) uses passport; compare Sanctum/Passport and migration guidance separately. Root >=8.4 must not be weakened merely because target framework permits an earlier PHP.

### monica7 (supplemental)

Pin `6d809fda1d59048521c6d5c7e8c3306039b18d4c`: [manifest](https://github.com/monicahq/monica/blob/6d809fda1d59048521c6d5c7e8c3306039b18d4c/composer.json), [lock](https://github.com/monicahq/monica/blob/6d809fda1d59048521c6d5c7e8c3306039b18d4c/composer.lock#L2756); longest: Laravel 13, PHP 7.4.0→8.3.0.
Root framework `^7.0`, PHP `^7.2.5`; locked framework `v7.28.1` requires PHP `^7.2.5`; platform PHP `absent`.

| Rooted package (root range) | Locked version; selected exact `require` metadata |
| --- | --- |
| [laravel/cashier](https://github.com/monicahq/monica/blob/6d809fda1d59048521c6d5c7e8c3306039b18d4c/composer.lock#L2682) (`~10.0`) | `v10.7.1`; `php=^7.1.3`; `illuminate/support=~5.8.0\|^6.0\|^7.0`; `illuminate/contracts=~5.8.0\|^6.0\|^7.0`; `illuminate/database=~5.8.0\|^6.0\|^7.0` |
| [laravel/ui](https://github.com/monicahq/monica/blob/6d809fda1d59048521c6d5c7e8c3306039b18d4c/composer.lock#L3116) (`^2.0`) | `v2.4.0`; `php=^7.2.5`; `illuminate/support=^7.0` |
| [phpunit/phpunit](https://github.com/monicahq/monica/blob/6d809fda1d59048521c6d5c7e8c3306039b18d4c/composer.lock#L14847) (`^9.0` dev) | `9.2.6`; `php=^7.3` |

Repositories: no root repositories key (no declared custom VCS/path repository). Layout: `App` → `app/`, `Tests` → `tests/` in production autoload; old seeds/factories classmap; case-distinct test fixture directories preserved.
Checks (comparison **PENDING**): **1.** Root PHP ^7.2.5 excludes 8.3, as do the selected locked PHP requirements. Cashier/UI also exclude later framework majors; a six-hop request is not six solved hops.
**2.** [config/auth.php:45](https://github.com/monicahq/monica/blob/6d809fda1d59048521c6d5c7e8c3306039b18d4c/config/auth.php#L45) uses passport; billing/authentication, old migrations and preserved `tests/Fixtures` versus `tests/fixtures` require separate review. No later-hop source state has been constructed.

## Later report-comparison disposition template

Freeze this source baseline before comparison; append report ID/hash, tool revision, request/mode and exact claim citation when authorized.

| Project/check ID | Source/lock/guide evidence | Tool claim or bounded omission | Disposition + rationale |
| --- | --- | --- | --- |
| UNAVAILABLE | Reference a fact/check above and exact key/path | PENDING — no reports inspected | PENDING |

Use **supported claim**, **false positive**, **missed within checked scope**, or **unverified**; distinguish expected blockers from product defects and harness/network failures.
A missed finding requires proof that the reported analysis covered that path/hop/rule and should have exposed the checked fact; package-layout questions alone do not prove a miss.
Keep unsupported upgrade-availability claims unverified; do not infer false-negative rates, runtime readiness or successful chaining from this bounded sample.
Retain independent source facts if reports disagree; adjudicate exact versions/paths and record evidence, rather than rewriting this baseline to match output.
