# PHP Upgrade Preflight Report

Resolution: **blocked** | Staged: **blocked** | Schema: `0.8` | Tool: `php-upgrade-preflight 0.3.0-dev`

## Analysis Request
- Project: `[PROJECT_ROOT]`
- Current PHP: `8.1`
- Target PHP: `8.3.0`
- Source paths: `default project paths`
- Framework integrations: `laravel`
- Target platform profile: `not supplied`
- Requested format: `markdown`
- Output destination: `[REPORT_OUTPUT]`
- Targets:
  - `laravel/framework`: `^13.0`
  - `php`: `8.3.0`

## Platform Provenance
- Analyzer PHP: `8.3.33` (provenance: `runtime`)
- Current project PHP: `8.1` (provenance: `request`)
- Target PHP: `8.3.0` (provenance: `request`)
- Extensions: provenance `mixed`; explicitly modeled: yes; completeness: `partial`; unmodeled values: `analyzer_runtime`
  - `ext-preflight-stage`: `absent` (provenance: `request`)
- Target platform profile: none; platform packages not explicitly modeled above remain analyzer-host dependent.

## Project State
- Analyzed path: `[PROJECT_ROOT]`
- Composer platform PHP: `not configured`
- Locked packages: `5`
- Root requirements:
  - `php`: `^8.1`
  - `laravel/framework`: `^10.0`
  - `laravel/tinker`: `^2.9`
  - `nesbot/carbon`: `^2.72`
  - `nunomaduro/collision`: `^7.11`
  - `phpunit/phpunit`: `^10.0`

## Composer Scenarios
- `baseline-validation`: succeeded (outcome `success`, Composer `2.10.2`, duration `123 ms`, exit `0`, failure type `none`)
  - command argv: `["composer","validate","--check-lock","--no-check-publish","--no-scripts","--no-plugins","--no-interaction"]`
  - temporary workspace: `not preserved`
  - stdout excerpt:
    ```text
    ./composer.json is valid

    ```
  - stderr excerpt: *(empty)*
  - candidate lock: SHA-256 `889eb4af8a022251d13a50fc551257ee75d2580c792e05cd3c11fa1995188f8b`, content hash `0e3e25bea4860bcbbe5529ec8924aab5`, packages `5`
  - diagnostics: none
- `exact-target`: failed (outcome `solver_failure`, Composer `2.10.2`, duration `312 ms`, exit `2`, failure type `solver`)
  - command argv: `["composer","update","laravel/framework","--no-scripts","--no-plugins","--no-install","--no-audit","--no-progress","--no-interaction"]`
  - temporary workspace: `not preserved`
  - stdout excerpt: *(empty)*
  - stderr excerpt:
    ```text
    Loading composer repositories with package information
    Updating dependencies
    Your requirements could not be resolved to an installable set of packages.

      Problem 1
        - Root composer.json requires laravel/framework ^13.0 -> satisfiable by laravel/framework[13.0.0].
        - laravel/framework 13.0.0 requires ext-preflight-stage ^2.0 -> it is missing from your system. Install or enable PHP's preflight-stage extension.

    To enable extensions, verify that they are enabled in your .ini files:
        - /usr/local/etc/php/conf.d/docker-php-ext-opcache.ini
        - /usr/local/etc/php/conf.d/docker-php-ext-sodium.ini
        - /usr/local/etc/php/conf.d/docker-php-ext-zip.ini
    You can also run `php --ini` in a terminal to see which files are used by PHP in CLI mode.
    Alternatively, you can run Composer with `--ignore-platform-req=ext-preflight-stage` to temporarily ignore these required extensions.

    Use the option --with-all-dependencies (-W) to allow upgrades, downgrades and removals for packages currently locked to specific versions.

    ```
  - candidate lock: not available
  - diagnostic for `laravel/framework ^13.0` (exit `1`), command argv: `["composer","prohibits","laravel/framework","^13.0","--tree","--locked","--no-scripts","--no-plugins","--no-interaction"]`
    - stdout excerpt:
      ```text
      laravel/framework 10.0.0 Metadata-only Laravel 10 package for the offline demo.
      |--nunomaduro/collision 7.11.0 (conflicts laravel/framework >=11.0.0) (circular dependency aborted here)
      |--phpunit/phpunit 10.0.0 (conflicts laravel/framework >=11.0.0) (circular dependency aborted here)
      `--laravel/framework 13.0.0 (requires ext-preflight-stage ^2.0 but it is missing) (circular dependency aborted here)

      ```
    - stderr excerpt:
      ```text
      Not finding what you were looking for? Try calling `composer require "laravel/framework:^13.0" --dry-run` to get another view on the problem.

      ```
  - diagnostic for `php 8.3.0` (exit `0`), command argv: `["composer","prohibits","php","8.3.0","--tree","--locked","--no-scripts","--no-plugins","--no-interaction"]`
    - stdout excerpt: *(empty)*
    - stderr excerpt:
      ```text
      There is no installed package depending on "php" in versions not matching 8.3.0

      ```
- `target-with-all-dependencies`: failed (outcome `solver_failure`, Composer `2.10.2`, duration `317 ms`, exit `2`, failure type `solver`)
  - command argv: `["composer","update","laravel/framework","--with-all-dependencies","--no-scripts","--no-plugins","--no-install","--no-audit","--no-progress","--no-interaction"]`
  - temporary workspace: `not preserved`
  - stdout excerpt: *(empty)*
  - stderr excerpt:
    ```text
    Loading composer repositories with package information
    Updating dependencies
    Your requirements could not be resolved to an installable set of packages.

      Problem 1
        - Root composer.json requires laravel/framework ^13.0 -> satisfiable by laravel/framework[13.0.0].
        - laravel/framework 13.0.0 requires ext-preflight-stage ^2.0 -> it is missing from your system. Install or enable PHP's preflight-stage extension.

    To enable extensions, verify that they are enabled in your .ini files:
        - /usr/local/etc/php/conf.d/docker-php-ext-opcache.ini
        - /usr/local/etc/php/conf.d/docker-php-ext-sodium.ini
        - /usr/local/etc/php/conf.d/docker-php-ext-zip.ini
    You can also run `php --ini` in a terminal to see which files are used by PHP in CLI mode.
    Alternatively, you can run Composer with `--ignore-platform-req=ext-preflight-stage` to temporarily ignore these required extensions.

    ```
  - candidate lock: not available
  - diagnostic for `laravel/framework ^13.0` (exit `1`), command argv: `["composer","prohibits","laravel/framework","^13.0","--tree","--locked","--no-scripts","--no-plugins","--no-interaction"]`
    - stdout excerpt:
      ```text
      laravel/framework 10.0.0 Metadata-only Laravel 10 package for the offline demo.
      |--nunomaduro/collision 7.11.0 (conflicts laravel/framework >=11.0.0) (circular dependency aborted here)
      |--phpunit/phpunit 10.0.0 (conflicts laravel/framework >=11.0.0) (circular dependency aborted here)
      `--laravel/framework 13.0.0 (requires ext-preflight-stage ^2.0 but it is missing) (circular dependency aborted here)

      ```
    - stderr excerpt:
      ```text
      Not finding what you were looking for? Try calling `composer require "laravel/framework:^13.0" --dry-run` to get another view on the problem.

      ```
  - diagnostic for `php 8.3.0` (exit `0`), command argv: `["composer","prohibits","php","8.3.0","--tree","--locked","--no-scripts","--no-plugins","--no-interaction"]`
    - stdout excerpt: *(empty)*
    - stderr excerpt:
      ```text
      There is no installed package depending on "php" in versions not matching 8.3.0

      ```
- `minimal-changes`: failed (outcome `solver_failure`, Composer `2.10.2`, duration `308 ms`, exit `2`, failure type `solver`)
  - command argv: `["composer","update","laravel/framework","--with-all-dependencies","--minimal-changes","--no-scripts","--no-plugins","--no-install","--no-audit","--no-progress","--no-interaction"]`
  - temporary workspace: `not preserved`
  - stdout excerpt: *(empty)*
  - stderr excerpt:
    ```text
    Loading composer repositories with package information
    Updating dependencies
    Your requirements could not be resolved to an installable set of packages.

      Problem 1
        - Root composer.json requires laravel/framework ^13.0 -> satisfiable by laravel/framework[13.0.0].
        - laravel/framework 13.0.0 requires ext-preflight-stage ^2.0 -> it is missing from your system. Install or enable PHP's preflight-stage extension.

    To enable extensions, verify that they are enabled in your .ini files:
        - /usr/local/etc/php/conf.d/docker-php-ext-opcache.ini
        - /usr/local/etc/php/conf.d/docker-php-ext-sodium.ini
        - /usr/local/etc/php/conf.d/docker-php-ext-zip.ini
    You can also run `php --ini` in a terminal to see which files are used by PHP in CLI mode.
    Alternatively, you can run Composer with `--ignore-platform-req=ext-preflight-stage` to temporarily ignore these required extensions.

    ```
  - candidate lock: not available
  - diagnostic for `laravel/framework ^13.0` (exit `1`), command argv: `["composer","prohibits","laravel/framework","^13.0","--tree","--locked","--no-scripts","--no-plugins","--no-interaction"]`
    - stdout excerpt:
      ```text
      laravel/framework 10.0.0 Metadata-only Laravel 10 package for the offline demo.
      |--nunomaduro/collision 7.11.0 (conflicts laravel/framework >=11.0.0) (circular dependency aborted here)
      |--phpunit/phpunit 10.0.0 (conflicts laravel/framework >=11.0.0) (circular dependency aborted here)
      `--laravel/framework 13.0.0 (requires ext-preflight-stage ^2.0 but it is missing) (circular dependency aborted here)

      ```
    - stderr excerpt:
      ```text
      Not finding what you were looking for? Try calling `composer require "laravel/framework:^13.0" --dry-run` to get another view on the problem.

      ```
  - diagnostic for `php 8.3.0` (exit `0`), command argv: `["composer","prohibits","php","8.3.0","--tree","--locked","--no-scripts","--no-plugins","--no-interaction"]`
    - stdout excerpt: *(empty)*
    - stderr excerpt:
      ```text
      There is no installed package depending on "php" in versions not matching 8.3.0

      ```
- `target-platform-only`: succeeded (outcome `success`, Composer `2.10.2`, duration `294 ms`, exit `0`, failure type `none`)
  - command argv: `["composer","update","--no-scripts","--no-plugins","--no-install","--no-audit","--no-progress","--no-interaction"]`
  - temporary workspace: `not preserved`
  - stdout excerpt: *(empty)*
  - stderr excerpt:
    ```text
    Loading composer repositories with package information
    Updating dependencies
    Nothing to modify in lock file
    Writing lock file

    ```
  - candidate lock: SHA-256 `ef43c1cef17de8f82cbbfb8858fa737167a1d6b20789a847ef4fd6e9b9986121`, content hash `2550fd71deb90c9bcff4ce41546ee066`, packages `5`
  - diagnostics: none
- `staged-targets`: failed (outcome `solver_failure`, Composer `2.10.2`, duration `292 ms`, exit `2`, failure type `solver`)
  - command argv: `["composer","update","laravel/framework","--with-all-dependencies","--no-scripts","--no-plugins","--no-install","--no-audit","--no-progress","--no-interaction"]`
  - temporary workspace: `not preserved`
  - stdout excerpt: *(empty)*
  - stderr excerpt:
    ```text
    Loading composer repositories with package information
    Updating dependencies
    Your requirements could not be resolved to an installable set of packages.

      Problem 1
        - Root composer.json requires laravel/framework ^13.0 -> satisfiable by laravel/framework[13.0.0].
        - laravel/framework 13.0.0 requires php ^8.3 -> your php version (8.1.0; overridden via config.platform, actual: 8.3.33) does not satisfy that requirement.


    ```
  - candidate lock: not available
  - diagnostic for `laravel/framework ^13.0` (exit `1`), command argv: `["composer","prohibits","laravel/framework","^13.0","--tree","--locked","--no-scripts","--no-plugins","--no-interaction"]`
    - stdout excerpt:
      ```text
      laravel/framework 10.0.0 Metadata-only Laravel 10 package for the offline demo.
      |--nunomaduro/collision 7.11.0 (conflicts laravel/framework >=11.0.0) (circular dependency aborted here)
      |--phpunit/phpunit 10.0.0 (conflicts laravel/framework >=11.0.0) (circular dependency aborted here)
      `--laravel/framework 13.0.0 (requires ext-preflight-stage ^2.0 but it is missing) (circular dependency aborted here)

      ```
    - stderr excerpt:
      ```text
      Not finding what you were looking for? Try calling `composer require "laravel/framework:^13.0" --dry-run` to get another view on the problem.

      ```

## Staged Composer Resolution
- Execution: `evaluated`; status: `blocked`; provider: `laravel`; stop reason: `blocking_registry_not_cleared`
- **laravel-10-to-11** (`10` -> `11`): execution `evaluated`; resolution `feasible_with_changes`; selected attempt `3`
  - analysis PHP: `8.3.0`; source snapshot: `original_project`
  - state chain: predecessor `a506769b5d65be3b3e27cec250aca451037e007272e589fd438c08dae270f984`; input `a506769b5d65be3b3e27cec250aca451037e007272e589fd438c08dae270f984`; output `f895e6529133aa3146077ad05f71ab54868ece7b95b486e5909d13e02abce0e9`
  - attempt `1` `target_only`: outcome `solver_failure`; selected no; blockers `stage-blocker-c2a3069ee41bf51e8d62`, `stage-blocker-86a42c6d92a5c34c9664`
    - analyzer-only root change `laravel/framework`: `^10.0` -> `^11.0`
  - attempt `2` `root_constraint_remediation`: outcome `solver_failure`; selected no; blockers `stage-blocker-86a42c6d92a5c34c9664`
    - analyzer-only root change `laravel/framework`: `^10.0` -> `^11.0`
    - analyzer-only root change `nunomaduro/collision`: `^7.11` -> `^8.1`
  - attempt `3` `root_and_locked_package_remediation`: outcome `success`; selected yes; blockers `none`
    - analyzer-only root change `laravel/framework`: `^10.0` -> `^11.0`
    - analyzer-only root change `nunomaduro/collision`: `^7.11` -> `^8.1`
    - analyzer-only root change `phpunit/phpunit`: `^10.0` -> `^11.0.1`
  - selected package change `laravel/framework`: `10.0.0` -> `11.0.0`
  - selected package change `nunomaduro/collision`: `7.11.0` -> `8.6.0`
  - selected package change `phpunit/phpunit`: `10.0.0` -> `11.0.1`
  - original-source finding (`medium`): nunomaduro/collision 7.11.0 is outside the encoded Laravel 11 review range `^8.1`; review its upgrade or replacement.
  - original-source finding (`medium`): phpunit/phpunit 10.0.0 is outside the encoded Laravel 11 review range `^11.0.1`; review its upgrade or replacement.
- **laravel-11-to-12** (`11` -> `12`): execution `evaluated`; resolution `feasible_with_changes`; selected attempt `1`
  - analysis PHP: `8.3.0`; source snapshot: `original_project`
  - state chain: predecessor `f895e6529133aa3146077ad05f71ab54868ece7b95b486e5909d13e02abce0e9`; input `f895e6529133aa3146077ad05f71ab54868ece7b95b486e5909d13e02abce0e9`; output `a5a48b6aad3e8513d6a9516d696822af8c1b97f2c8cc1f0b4d0f10eecd01c0fb`
  - attempt `1` `target_only`: outcome `success`; selected yes; blockers `none`
    - analyzer-only root change `laravel/framework`: `^11.0` -> `^12.0`
  - selected package change `laravel/framework`: `11.0.0` -> `12.0.0`
  - original-source finding (`high`): phpunit/phpunit 10.0.0 is outside the encoded Laravel 12 review range `^11.0`; review its upgrade or replacement.
  - original-source finding (`medium`): nesbot/carbon 2.72.0 is outside the encoded Laravel 12 review range `^3.0`; review its upgrade or replacement.
  - original-source finding (`medium`): nunomaduro/collision 7.11.0 is outside the encoded Laravel 12 review range `^8.6`; review its upgrade or replacement.
- **laravel-12-to-13** (`12` -> `13`): execution `evaluated`; resolution `blocked`; selected attempt `none`
  - analysis PHP: `8.3.0`; source snapshot: `original_project`
  - state chain: predecessor `a5a48b6aad3e8513d6a9516d696822af8c1b97f2c8cc1f0b4d0f10eecd01c0fb`; input `a5a48b6aad3e8513d6a9516d696822af8c1b97f2c8cc1f0b4d0f10eecd01c0fb`; output `none`
  - attempt `1` `target_only`: outcome `solver_failure`; selected no; blockers `stage-blocker-04dd2115b0a9fe4ea391`
    - analyzer-only root change `laravel/framework`: `^12.0` -> `^13.0`
  - attempt `2` `root_constraint_remediation`: outcome `solver_failure`; selected no; blockers `stage-blocker-04dd2115b0a9fe4ea391`
    - analyzer-only root change `laravel/framework`: `^12.0` -> `^13.0`
    - analyzer-only root change `laravel/tinker`: `^2.9` -> `^3.0`
  - attempt `3` `root_and_locked_package_remediation`: outcome `solver_failure`; selected no; blockers `stage-blocker-04dd2115b0a9fe4ea391`
    - analyzer-only root change `laravel/framework`: `^12.0` -> `^13.0`
    - analyzer-only root change `laravel/tinker`: `^2.9` -> `^3.0`
    - analyzer-only root change `nunomaduro/collision`: `^8.1` -> `^8.6`
    - analyzer-only root change `phpunit/phpunit`: `^11.0.1` -> `^12.0`
  - original-source finding (`high`): Update the root laravel/framework constraint from `^10.0` to a constraint compatible with Laravel 13.
  - original-source finding (`high`): laravel/tinker 2.9.0 is outside the encoded Laravel 13 review range `^3.0`; review its upgrade or replacement.
  - original-source finding (`high`): phpunit/phpunit 10.0.0 is outside the encoded Laravel 13 review range `^12.0`; review its upgrade or replacement.
  - original-source finding (`medium`): nunomaduro/collision 7.11.0 is outside the encoded Laravel 13 review range `^8.6`; review its upgrade or replacement.
  - original-source finding (`high`): Replace 1 detected direct reference to VerifyCsrfToken or ValidateCsrfToken with PreventRequestForgery before targeting Laravel 13.
  - stop reason: `blocking_registry_not_cleared`
- Blocker registry:
  - `stage-blocker-c2a3069ee41bf51e8d62` stage `laravel-10-to-11`: `replace-provide-conflict` `laravel/framework`; lifecycle `resolved` (detected@1 -> resolved@2); blocking package `nunomaduro/collision`; constraint `>=11.0.0`; path `nunomaduro/collision -> laravel/framework`
  - `stage-blocker-86a42c6d92a5c34c9664` stage `laravel-10-to-11`: `replace-provide-conflict` `laravel/framework`; lifecycle `resolved` (detected@1 -> persists@2 -> resolved@3); blocking package `phpunit/phpunit`; constraint `>=11.0.0`; path `nunomaduro/collision -> laravel/framework`
  - `stage-blocker-04dd2115b0a9fe4ea391` stage `laravel-12-to-13`: `extension-missing` `ext-preflight-stage`; lifecycle `persists` (detected@1 -> persists@2 -> persists@3); blocking package `laravel/framework`; constraint `^2.0`; path `laravel/framework -> ext-preflight-stage`

## Package Changes
- No lockfile changes detected.

## Framework Transition Guidance
- `laravel`: `supported` (`10` -> `13`; evidence: `laravel-transition-1`, `laravel-transition-2`, `laravel-transition-3`)
  - hop `10` -> `11`: `supported`; rule pack `laravel-10-to-11` (evidence: `laravel-transition-1`)
  - hop `11` -> `12`: `supported`; rule pack `laravel-11-to-12` (evidence: `laravel-transition-2`)
  - hop `12` -> `13`: `supported`; rule pack `laravel-12-to-13` (evidence: `laravel-transition-3`)

## Root Constraint Changes
- `laravel/framework`: updated `^10.0` -> `^13.0`. The declared root constraint differs from the requested target. (evidence: `root-constraint-1`)

## Blockers
- `replace-provide-conflict` `laravel/framework`: Composer found conflicting replace, provide, or conflict rules. (high confidence; evidence: `solver-1`, `solver-2`, `solver-3`, `solver-4`)
  - requested `^13.0`; blocker `nunomaduro/collision`; locked `7.11.0`; conflict `>=11.0.0`
  - dependency path: `nunomaduro/collision -> laravel/framework`
  - option: Remove or replace `nunomaduro/collision`.
  - option: Choose versions whose replace/provide rules can coexist.
- `replace-provide-conflict` `laravel/framework`: Composer found conflicting replace, provide, or conflict rules. (high confidence; evidence: `solver-1`, `solver-2`, `solver-3`, `solver-4`)
  - requested `^13.0`; blocker `phpunit/phpunit`; locked `10.0.0`; conflict `>=11.0.0`
  - dependency path: `nunomaduro/collision -> laravel/framework`
  - option: Remove or replace `phpunit/phpunit`.
  - option: Choose versions whose replace/provide rules can coexist.

## Source Inventory
- `namespace_import` `Illuminate\Foundation\Http\Middleware\VerifyCsrfToken` in `tests/Feature/LegacyCsrfTest.php:7` (evidence: `source-1`)
- `class_constant_access` `Illuminate\Foundation\Http\Middleware\VerifyCsrfToken` in `tests/Feature/LegacyCsrfTest.php:13` (evidence: `source-2`)
- `middleware_reference` `Illuminate\Foundation\Http\Middleware\VerifyCsrfToken` in `tests/Feature/LegacyCsrfTest.php:13` (evidence: `source-3`)

## Actionable Source Impact
- `high` impact for `package unknown` (`unknown` ownership; `framework_rule`): Referenced by active laravel compatibility guidance; package ownership has not been established. (evidence: `source-3`, `laravel-request-forgery-guidance-1`)
  - `middleware_reference` `Illuminate\Foundation\Http\Middleware\VerifyCsrfToken` in `tests/Feature/LegacyCsrfTest.php:13` (evidence: `source-3`)

## Framework Findings
- `laravel` `high`: Update the root laravel/framework constraint from `^10.0` to a constraint compatible with Laravel 13. (evidence: `laravel-framework-constraint-1`)
  - applies to hops: `12 -> 13`
- `laravel` `medium`: nunomaduro/collision 7.11.0 is outside the encoded Laravel 11 review range `^8.1`; review its upgrade or replacement. (evidence: `laravel-package-nunomaduro_collision-1`, `laravel-package-guidance-1`)
  - applies to hops: `10 -> 11`
- `laravel` `medium`: phpunit/phpunit 10.0.0 is outside the encoded Laravel 11 review range `^11.0.1`; review its upgrade or replacement. (evidence: `laravel-package-phpunit_phpunit-1`, `laravel-package-guidance-2`)
  - applies to hops: `10 -> 11`
- `laravel` `high`: phpunit/phpunit 10.0.0 is outside the encoded Laravel 12 review range `^11.0`; review its upgrade or replacement. (evidence: `laravel-package-phpunit_phpunit-2`, `laravel-package-guidance-3`)
  - applies to hops: `11 -> 12`
- `laravel` `medium`: nesbot/carbon 2.72.0 is outside the encoded Laravel 12 review range `^3.0`; review its upgrade or replacement. (evidence: `laravel-package-nesbot_carbon-1`, `laravel-package-guidance-4`)
  - applies to hops: `11 -> 12`
- `laravel` `medium`: nunomaduro/collision 7.11.0 is outside the encoded Laravel 12 review range `^8.6`; review its upgrade or replacement. (evidence: `laravel-package-nunomaduro_collision-2`, `laravel-package-guidance-5`)
  - applies to hops: `11 -> 12`
- `laravel` `high`: laravel/tinker 2.9.0 is outside the encoded Laravel 13 review range `^3.0`; review its upgrade or replacement. (evidence: `laravel-package-laravel_tinker-1`, `laravel-package-guidance-6`)
  - applies to hops: `12 -> 13`
- `laravel` `high`: phpunit/phpunit 10.0.0 is outside the encoded Laravel 13 review range `^12.0`; review its upgrade or replacement. (evidence: `laravel-package-phpunit_phpunit-3`, `laravel-package-guidance-7`)
  - applies to hops: `12 -> 13`
- `laravel` `medium`: nunomaduro/collision 7.11.0 is outside the encoded Laravel 13 review range `^8.6`; review its upgrade or replacement. (evidence: `laravel-package-nunomaduro_collision-3`, `laravel-package-guidance-8`)
  - applies to hops: `12 -> 13`
- `laravel` `high`: Replace 1 detected direct reference to VerifyCsrfToken or ValidateCsrfToken with PreventRequestForgery before targeting Laravel 13. (evidence: `laravel-request-forgery-guidance-1`, `source-3`)
  - applies to hops: `12 -> 13`

## Staged Plan
1. **constraints** — Prepare the requested root constraint changes before dependency resolution. (evidence: `plan-1`, `root-constraint-1`)
   - Update the `laravel/framework` root constraint to `^13.0`.
2. **dependencies** — Resolve dependency blockers and review the resulting lockfile transition. (evidence: `plan-1`, `solver-1`, `solver-2`, `solver-3`, `solver-4`)
   - Resolve the `replace-provide-conflict` blocker affecting `laravel/framework`.
   - Resolve the `replace-provide-conflict` blocker affecting `laravel/framework`.
   - Rerun the isolated Composer scenarios after resolving the reported blockers.
3. **application** — Apply source and framework migration work after dependency resolution is stable. (evidence: `plan-1`, `source-3`, `laravel-request-forgery-guidance-1`, `laravel-framework-constraint-1`, `laravel-package-nunomaduro_collision-1`, `laravel-package-guidance-1`, `laravel-package-phpunit_phpunit-1`, `laravel-package-guidance-2`, `laravel-package-phpunit_phpunit-2`, `laravel-package-guidance-3`, `laravel-package-nesbot_carbon-1`, `laravel-package-guidance-4`, `laravel-package-nunomaduro_collision-2`, `laravel-package-guidance-5`, `laravel-package-laravel_tinker-1`, `laravel-package-guidance-6`, `laravel-package-phpunit_phpunit-3`, `laravel-package-guidance-7`, `laravel-package-nunomaduro_collision-3`, `laravel-package-guidance-8`)
   - Review the reported source locations and adapt affected application code.
   - Address framework compatibility findings before runtime validation.
4. **validation** — Validate the upgraded project on the target runtime before release. (evidence: `plan-1`)
   - Validate the Composer manifest and installed platform requirements.
   - Run the project test suite and focused regression tests.

## Risk And Effort
- Risk: `high`
- Risk drivers:
  - Composer resolution is blocked.
  - Framework compatibility findings require review.
  - Weighted actionable source findings require review.
- Effort: `6-32` hours (low confidence)
- Effort components:
  - `dependency_resolution`: `3-8` hours
  - `source_changes`: `1-16` hours
  - `tests_and_debugging`: `2-8` hours
- Effort assumptions:
  - Estimate is heuristic until project-specific tests and Composer solver output are reviewed.

## Test Guidance
- **composer-validation** (`required`): Validate the edited Composer manifest before dependency installation. Command: `composer validate --strict`.
- **project-test-suite** (`required`): Identify and run the project test suite; no Composer test script was detected. Command: project-specific command required.
- **platform-requirements** (`required`): Confirm the installed dependencies satisfy PHP 8.3.0 and the deployment extensions. Command: `composer check-platform-reqs`.
- **focused-regressions** (`recommended`): Add or run focused regression coverage for the reported source and framework findings. Command: project-specific command required.

## Uncertainties
- Dependency resolution does not prove application runtime compatibility; the project test suite must run on the target runtime.
- No Composer "test" script was found, so the project's canonical test command is unknown.
- Composer modeled only the listed extension assumptions; every unlisted extension still came from the analyzer runtime.

## Evidence
- `solver-1` (`E1`, high confidence): Composer scenario "exact-target" failed. Context: ``{"scenario":"exact-target","targets":[{"package":"laravel/framework","constraint":"^13.0"},{"package":"php","constraint":"8.3.0"}],"exit_code":2,"output_excerpt":"Loading composer repositories with package information\nUpdating dependencies\nYour requirements could not be resolved to an installable set of packages.\n\n  Problem 1\n    - Root composer.json requires laravel/framework ^13.0 -> satisfiable by laravel/framework[13.0.0].\n    - laravel/framework 13.0.0 requires ext-preflight-stage ^2.0 -> it is missing from your system. Install or enable PHP's preflight-stage extension.\n\nTo enable extensions, verify that they are enabled in your .ini files:\n    - /usr/local/etc/php/conf.d/docker-php-ext-opcache.ini\n    - /usr/local/etc/php/conf.d/docker-php-ext-sodium.ini\n    - /usr/local/etc/php/conf.d/docker-php-ext-zip.ini\nYou can also run `php --ini` in a terminal to see which files are used by PHP in CLI mode.\nAlternatively, you can run Composer with `--ignore-platform-req=ext-preflight-stage` to temporarily ignore these required extensions.\n\nUse the option --with-all-dependencies (-W) to allow upgrades, downgrades and removals for packages currently locked to specific versions.","diagnostics":[{"package":"laravel/framework","constraint":"^13.0","command":["composer","prohibits","laravel/framework","^13.0","--tree","--locked","--no-scripts","--no-plugins","--no-interaction"],"exit_code":1,"stdout_excerpt":"laravel/framework 10.0.0 Metadata-only Laravel 10 package for the offline demo.\n|--nunomaduro/collision 7.11.0 (conflicts laravel/framework >=11.0.0) (circular dependency aborted here)\n|--phpunit/phpunit 10.0.0 (conflicts laravel/framework >=11.0.0) (circular dependency aborted here)\n`--laravel/framework 13.0.0 (requires ext-preflight-stage ^2.0 but it is missing) (circular dependency aborted here)\n","stderr_excerpt":"Not finding what you were looking for? Try calling `composer require \"laravel/framework:^13.0\" --dry-run` to get another view on the problem.\n"},{"package":"php","constraint":"8.3.0","command":["composer","prohibits","php","8.3.0","--tree","--locked","--no-scripts","--no-plugins","--no-interaction"],"exit_code":0,"stdout_excerpt":"","stderr_excerpt":"There is no installed package depending on \"php\" in versions not matching 8.3.0\n"}]}``
- `solver-2` (`E1`, high confidence): Composer scenario "target-with-all-dependencies" failed. Context: ``{"scenario":"target-with-all-dependencies","targets":[{"package":"laravel/framework","constraint":"^13.0"},{"package":"php","constraint":"8.3.0"}],"exit_code":2,"output_excerpt":"Loading composer repositories with package information\nUpdating dependencies\nYour requirements could not be resolved to an installable set of packages.\n\n  Problem 1\n    - Root composer.json requires laravel/framework ^13.0 -> satisfiable by laravel/framework[13.0.0].\n    - laravel/framework 13.0.0 requires ext-preflight-stage ^2.0 -> it is missing from your system. Install or enable PHP's preflight-stage extension.\n\nTo enable extensions, verify that they are enabled in your .ini files:\n    - /usr/local/etc/php/conf.d/docker-php-ext-opcache.ini\n    - /usr/local/etc/php/conf.d/docker-php-ext-sodium.ini\n    - /usr/local/etc/php/conf.d/docker-php-ext-zip.ini\nYou can also run `php --ini` in a terminal to see which files are used by PHP in CLI mode.\nAlternatively, you can run Composer with `--ignore-platform-req=ext-preflight-stage` to temporarily ignore these required extensions.","diagnostics":[{"package":"laravel/framework","constraint":"^13.0","command":["composer","prohibits","laravel/framework","^13.0","--tree","--locked","--no-scripts","--no-plugins","--no-interaction"],"exit_code":1,"stdout_excerpt":"laravel/framework 10.0.0 Metadata-only Laravel 10 package for the offline demo.\n|--nunomaduro/collision 7.11.0 (conflicts laravel/framework >=11.0.0) (circular dependency aborted here)\n|--phpunit/phpunit 10.0.0 (conflicts laravel/framework >=11.0.0) (circular dependency aborted here)\n`--laravel/framework 13.0.0 (requires ext-preflight-stage ^2.0 but it is missing) (circular dependency aborted here)\n","stderr_excerpt":"Not finding what you were looking for? Try calling `composer require \"laravel/framework:^13.0\" --dry-run` to get another view on the problem.\n"},{"package":"php","constraint":"8.3.0","command":["composer","prohibits","php","8.3.0","--tree","--locked","--no-scripts","--no-plugins","--no-interaction"],"exit_code":0,"stdout_excerpt":"","stderr_excerpt":"There is no installed package depending on \"php\" in versions not matching 8.3.0\n"}]}``
- `solver-3` (`E1`, high confidence): Composer scenario "minimal-changes" failed. Context: ``{"scenario":"minimal-changes","targets":[{"package":"laravel/framework","constraint":"^13.0"},{"package":"php","constraint":"8.3.0"}],"exit_code":2,"output_excerpt":"Loading composer repositories with package information\nUpdating dependencies\nYour requirements could not be resolved to an installable set of packages.\n\n  Problem 1\n    - Root composer.json requires laravel/framework ^13.0 -> satisfiable by laravel/framework[13.0.0].\n    - laravel/framework 13.0.0 requires ext-preflight-stage ^2.0 -> it is missing from your system. Install or enable PHP's preflight-stage extension.\n\nTo enable extensions, verify that they are enabled in your .ini files:\n    - /usr/local/etc/php/conf.d/docker-php-ext-opcache.ini\n    - /usr/local/etc/php/conf.d/docker-php-ext-sodium.ini\n    - /usr/local/etc/php/conf.d/docker-php-ext-zip.ini\nYou can also run `php --ini` in a terminal to see which files are used by PHP in CLI mode.\nAlternatively, you can run Composer with `--ignore-platform-req=ext-preflight-stage` to temporarily ignore these required extensions.","diagnostics":[{"package":"laravel/framework","constraint":"^13.0","command":["composer","prohibits","laravel/framework","^13.0","--tree","--locked","--no-scripts","--no-plugins","--no-interaction"],"exit_code":1,"stdout_excerpt":"laravel/framework 10.0.0 Metadata-only Laravel 10 package for the offline demo.\n|--nunomaduro/collision 7.11.0 (conflicts laravel/framework >=11.0.0) (circular dependency aborted here)\n|--phpunit/phpunit 10.0.0 (conflicts laravel/framework >=11.0.0) (circular dependency aborted here)\n`--laravel/framework 13.0.0 (requires ext-preflight-stage ^2.0 but it is missing) (circular dependency aborted here)\n","stderr_excerpt":"Not finding what you were looking for? Try calling `composer require \"laravel/framework:^13.0\" --dry-run` to get another view on the problem.\n"},{"package":"php","constraint":"8.3.0","command":["composer","prohibits","php","8.3.0","--tree","--locked","--no-scripts","--no-plugins","--no-interaction"],"exit_code":0,"stdout_excerpt":"","stderr_excerpt":"There is no installed package depending on \"php\" in versions not matching 8.3.0\n"}]}``
- `solver-4` (`E1`, high confidence): Composer scenario "staged-targets" failed. Context: ``{"scenario":"staged-targets","targets":[{"package":"laravel/framework","constraint":"^13.0"},{"package":"php","constraint":"8.1.0"}],"exit_code":2,"output_excerpt":"Loading composer repositories with package information\nUpdating dependencies\nYour requirements could not be resolved to an installable set of packages.\n\n  Problem 1\n    - Root composer.json requires laravel/framework ^13.0 -> satisfiable by laravel/framework[13.0.0].\n    - laravel/framework 13.0.0 requires php ^8.3 -> your php version (8.1.0; overridden via config.platform, actual: 8.3.33) does not satisfy that requirement.","diagnostics":[{"package":"laravel/framework","constraint":"^13.0","command":["composer","prohibits","laravel/framework","^13.0","--tree","--locked","--no-scripts","--no-plugins","--no-interaction"],"exit_code":1,"stdout_excerpt":"laravel/framework 10.0.0 Metadata-only Laravel 10 package for the offline demo.\n|--nunomaduro/collision 7.11.0 (conflicts laravel/framework >=11.0.0) (circular dependency aborted here)\n|--phpunit/phpunit 10.0.0 (conflicts laravel/framework >=11.0.0) (circular dependency aborted here)\n`--laravel/framework 13.0.0 (requires ext-preflight-stage ^2.0 but it is missing) (circular dependency aborted here)\n","stderr_excerpt":"Not finding what you were looking for? Try calling `composer require \"laravel/framework:^13.0\" --dry-run` to get another view on the problem.\n"}]}``
- `laravel-stage-target-1` (`E4`, high confidence): Laravel adapter metadata supplies the exact package target for stage 10 to 11. Context: `{"stage_id":"laravel-10-to-11","package":"laravel/framework","constraint":"^11.0","analysis_php":"8.3.0","minimum_php_constraint":"^8.2","analysis_php_provenance":"request_exact_value_checked_against_adapter_constraint","sources":["https://laravel.com/docs/11.x/upgrade","https://github.com/laravel/framework/blob/e353708c960ec5066d76b0da4b81c8a68d183b93/composer.json"]}`
- `laravel-stage-remediation-1` (`E4`, medium confidence): Laravel adapter metadata permits an analyzer-only root constraint candidate for nunomaduro/collision in stage laravel-10-to-11. Context: `{"stage_id":"laravel-10-to-11","package":"nunomaduro/collision","constraint":"^8.1","sources":["https://laravel.com/docs/11.x/upgrade"]}`
- `laravel-stage-remediation-2` (`E4`, medium confidence): Laravel adapter metadata permits an analyzer-only root constraint candidate for phpunit/phpunit in stage laravel-10-to-11. Context: `{"stage_id":"laravel-10-to-11","package":"phpunit/phpunit","constraint":"^11.0.1","sources":["https://github.com/laravel/laravel/blob/11.x/composer.json"]}`
- `laravel-stage-target-2` (`E4`, high confidence): Laravel adapter metadata supplies the exact package target for stage 11 to 12. Context: `{"stage_id":"laravel-11-to-12","package":"laravel/framework","constraint":"^12.0","analysis_php":"8.3.0","minimum_php_constraint":"^8.2","analysis_php_provenance":"request_exact_value_checked_against_adapter_constraint","sources":["https://laravel.com/docs/12.x/upgrade","https://github.com/laravel/framework/blob/5260836df1b953a558d9b810880f20db15568c01/composer.json"]}`
- `laravel-stage-remediation-3` (`E4`, medium confidence): Laravel adapter metadata permits an analyzer-only root constraint candidate for phpunit/phpunit in stage laravel-11-to-12. Context: `{"stage_id":"laravel-11-to-12","package":"phpunit/phpunit","constraint":"^11.0","sources":["https://laravel.com/docs/12.x/upgrade"]}`
- `laravel-stage-remediation-4` (`E4`, medium confidence): Laravel adapter metadata permits an analyzer-only root constraint candidate for nesbot/carbon in stage laravel-11-to-12. Context: `{"stage_id":"laravel-11-to-12","package":"nesbot/carbon","constraint":"^3.0","sources":["https://laravel.com/docs/12.x/upgrade"]}`
- `laravel-stage-remediation-5` (`E4`, medium confidence): Laravel adapter metadata permits an analyzer-only root constraint candidate for nunomaduro/collision in stage laravel-11-to-12. Context: `{"stage_id":"laravel-11-to-12","package":"nunomaduro/collision","constraint":"^8.6","sources":["https://github.com/laravel/laravel/blob/12.x/composer.json"]}`
- `laravel-stage-target-3` (`E4`, high confidence): Laravel adapter metadata supplies the exact package target for stage 12 to 13. Context: `{"stage_id":"laravel-12-to-13","package":"laravel/framework","constraint":"^13.0","analysis_php":"8.3.0","minimum_php_constraint":"^8.3","analysis_php_provenance":"request_exact_value_checked_against_adapter_constraint","sources":["https://github.com/laravel/docs/blob/9c5a062c14069bab9054b558829e282f9593a065/upgrade.md","https://github.com/laravel/framework/blob/8df67f9d176d1d0375a866d8c6780be95ce0336e/composer.json"]}`
- `laravel-stage-remediation-6` (`E4`, medium confidence): Laravel adapter metadata permits an analyzer-only root constraint candidate for laravel/tinker in stage laravel-12-to-13. Context: `{"stage_id":"laravel-12-to-13","package":"laravel/tinker","constraint":"^3.0","sources":["https://github.com/laravel/docs/blob/9c5a062c14069bab9054b558829e282f9593a065/upgrade.md"]}`
- `laravel-stage-remediation-7` (`E4`, medium confidence): Laravel adapter metadata permits an analyzer-only root constraint candidate for phpunit/phpunit in stage laravel-12-to-13. Context: `{"stage_id":"laravel-12-to-13","package":"phpunit/phpunit","constraint":"^12.0","sources":["https://github.com/laravel/docs/blob/9c5a062c14069bab9054b558829e282f9593a065/upgrade.md","https://github.com/laravel/laravel/blob/c926b8ca7fa01e71852e19141f2bdd7fabfb6ade/composer.json"]}`
- `laravel-stage-remediation-8` (`E4`, medium confidence): Laravel adapter metadata permits an analyzer-only root constraint candidate for nunomaduro/collision in stage laravel-12-to-13. Context: `{"stage_id":"laravel-12-to-13","package":"nunomaduro/collision","constraint":"^8.6","sources":["https://github.com/laravel/laravel/blob/c926b8ca7fa01e71852e19141f2bdd7fabfb6ade/composer.json"]}`
- `stage-attempt-1` (`E1`, high confidence): Executed Composer attempt 1 for stage laravel-10-to-11. Context: `{"stage_id":"laravel-10-to-11","attempt":1,"strategy":"target_only","scenario":"laravel-10-to-11-attempt-1-target_only","outcome":"solver_failure"}`
- `solver-5` (`E1`, high confidence): Composer scenario "laravel-10-to-11-attempt-1-target_only" failed. Context: ``{"scenario":"laravel-10-to-11-attempt-1-target_only","targets":[{"package":"laravel/framework","constraint":"^11.0"},{"package":"php","constraint":"8.3.0"}],"exit_code":2,"output_excerpt":"Loading composer repositories with package information\nUpdating dependencies\nYour requirements could not be resolved to an installable set of packages.\n\n  Problem 1\n    - Root composer.json requires laravel/framework ^11.0 -> satisfiable by laravel/framework[11.0.0].\n    - phpunit/phpunit is locked to version 10.0.0 and an update of this package was not requested.\n    - phpunit/phpunit 10.0.0 conflicts with laravel/framework 11.0.0.\n\nUse the option --with-all-dependencies (-W) to allow upgrades, downgrades and removals for packages currently locked to specific versions.","diagnostics":[{"package":"laravel/framework","constraint":"^11.0","command":["composer","prohibits","laravel/framework","^11.0","--tree","--locked","--no-scripts","--no-plugins","--no-interaction"],"exit_code":1,"stdout_excerpt":"laravel/framework 10.0.0 Metadata-only Laravel 10 package for the offline demo.\n|--nunomaduro/collision 7.11.0 (conflicts laravel/framework >=11.0.0) (circular dependency aborted here)\n`--phpunit/phpunit 10.0.0 (conflicts laravel/framework >=11.0.0) (circular dependency aborted here)\n","stderr_excerpt":"Not finding what you were looking for? Try calling `composer require \"laravel/framework:^11.0\" --dry-run` to get another view on the problem.\n"},{"package":"php","constraint":"8.3.0","command":["composer","prohibits","php","8.3.0","--tree","--locked","--no-scripts","--no-plugins","--no-interaction"],"exit_code":0,"stdout_excerpt":"","stderr_excerpt":"There is no installed package depending on \"php\" in versions not matching 8.3.0\n"}]}``
- `stage-root-change-1` (`E2`, high confidence): Recorded an analyzer-only root constraint change for stage laravel-10-to-11. Context: `{"stage_id":"laravel-10-to-11","package":"laravel/framework","from_constraint":"^10.0","to_constraint":"^11.0","supporting_evidence":["laravel-stage-target-1"]}`
- `stage-attempt-2` (`E1`, high confidence): Executed Composer attempt 2 for stage laravel-10-to-11. Context: `{"stage_id":"laravel-10-to-11","attempt":2,"strategy":"root_constraint_remediation","scenario":"laravel-10-to-11-attempt-2-root_constraint_remediation","outcome":"solver_failure"}`
- `solver-6` (`E1`, high confidence): Composer scenario "laravel-10-to-11-attempt-2-root_constraint_remediation" failed. Context: ``{"scenario":"laravel-10-to-11-attempt-2-root_constraint_remediation","targets":[{"package":"laravel/framework","constraint":"^11.0"},{"package":"nunomaduro/collision","constraint":"^8.1"},{"package":"php","constraint":"8.3.0"}],"exit_code":2,"output_excerpt":"Loading composer repositories with package information\nUpdating dependencies\nYour requirements could not be resolved to an installable set of packages.\n\n  Problem 1\n    - Root composer.json requires laravel/framework ^11.0 -> satisfiable by laravel/framework[11.0.0].\n    - phpunit/phpunit is locked to version 10.0.0 and an update of this package was not requested.\n    - phpunit/phpunit 10.0.0 conflicts with laravel/framework 11.0.0.\n\nUse the option --with-all-dependencies (-W) to allow upgrades, downgrades and removals for packages currently locked to specific versions.","diagnostics":[{"package":"laravel/framework","constraint":"^11.0","command":["composer","prohibits","laravel/framework","^11.0","--tree","--locked","--no-scripts","--no-plugins","--no-interaction"],"exit_code":1,"stdout_excerpt":"laravel/framework 10.0.0 Metadata-only Laravel 10 package for the offline demo.\n|--nunomaduro/collision 7.11.0 (conflicts laravel/framework >=11.0.0) (circular dependency aborted here)\n`--phpunit/phpunit 10.0.0 (conflicts laravel/framework >=11.0.0) (circular dependency aborted here)\n","stderr_excerpt":"Not finding what you were looking for? Try calling `composer require \"laravel/framework:^11.0\" --dry-run` to get another view on the problem.\n"},{"package":"nunomaduro/collision","constraint":"^8.1","command":["composer","prohibits","nunomaduro/collision","^8.1","--tree","--locked","--no-scripts","--no-plugins","--no-interaction"],"exit_code":0,"stdout_excerpt":"","stderr_excerpt":"There is no installed package depending on \"nunomaduro/collision\" in versions not matching ^8.1\nNot finding what you were looking for? Try calling `composer require --dev \"nunomaduro/collision:^8.1\" --dry-run` to get another view on the problem.\n"},{"package":"php","constraint":"8.3.0","command":["composer","prohibits","php","8.3.0","--tree","--locked","--no-scripts","--no-plugins","--no-interaction"],"exit_code":0,"stdout_excerpt":"","stderr_excerpt":"There is no installed package depending on \"php\" in versions not matching 8.3.0\n"}]}``
- `stage-root-change-2` (`E2`, high confidence): Recorded an analyzer-only root constraint change for stage laravel-10-to-11. Context: `{"stage_id":"laravel-10-to-11","package":"laravel/framework","from_constraint":"^10.0","to_constraint":"^11.0","supporting_evidence":["laravel-stage-target-1"]}`
- `stage-root-change-3` (`E2`, high confidence): Recorded an analyzer-only root constraint change for stage laravel-10-to-11. Context: `{"stage_id":"laravel-10-to-11","package":"nunomaduro/collision","from_constraint":"^7.11","to_constraint":"^8.1","supporting_evidence":["laravel-stage-remediation-1"]}`
- `stage-attempt-3` (`E1`, high confidence): Executed Composer attempt 3 for stage laravel-10-to-11. Context: `{"stage_id":"laravel-10-to-11","attempt":3,"strategy":"root_and_locked_package_remediation","scenario":"laravel-10-to-11-attempt-3-root_and_locked_package_remediation","outcome":"success"}`
- `stage-root-change-4` (`E2`, high confidence): Recorded an analyzer-only root constraint change for stage laravel-10-to-11. Context: `{"stage_id":"laravel-10-to-11","package":"laravel/framework","from_constraint":"^10.0","to_constraint":"^11.0","supporting_evidence":["laravel-stage-target-1"]}`
- `stage-root-change-5` (`E2`, high confidence): Recorded an analyzer-only root constraint change for stage laravel-10-to-11. Context: `{"stage_id":"laravel-10-to-11","package":"nunomaduro/collision","from_constraint":"^7.11","to_constraint":"^8.1","supporting_evidence":["laravel-stage-remediation-1"]}`
- `stage-root-change-6` (`E2`, high confidence): Recorded an analyzer-only root constraint change for stage laravel-10-to-11. Context: `{"stage_id":"laravel-10-to-11","package":"phpunit/phpunit","from_constraint":"^10.0","to_constraint":"^11.0.1","supporting_evidence":["laravel-stage-remediation-2"]}`
- `stage-attempt-4` (`E1`, high confidence): Executed Composer attempt 1 for stage laravel-11-to-12. Context: `{"stage_id":"laravel-11-to-12","attempt":1,"strategy":"target_only","scenario":"laravel-11-to-12-attempt-1-target_only","outcome":"success"}`
- `stage-root-change-7` (`E2`, high confidence): Recorded an analyzer-only root constraint change for stage laravel-11-to-12. Context: `{"stage_id":"laravel-11-to-12","package":"laravel/framework","from_constraint":"^11.0","to_constraint":"^12.0","supporting_evidence":["laravel-stage-target-2"]}`
- `stage-attempt-5` (`E1`, high confidence): Executed Composer attempt 1 for stage laravel-12-to-13. Context: `{"stage_id":"laravel-12-to-13","attempt":1,"strategy":"target_only","scenario":"laravel-12-to-13-attempt-1-target_only","outcome":"solver_failure"}`
- `solver-7` (`E1`, high confidence): Composer scenario "laravel-12-to-13-attempt-1-target_only" failed. Context: ``{"scenario":"laravel-12-to-13-attempt-1-target_only","targets":[{"package":"laravel/framework","constraint":"^13.0"},{"package":"php","constraint":"8.3.0"}],"exit_code":2,"output_excerpt":"Loading composer repositories with package information\nUpdating dependencies\nYour requirements could not be resolved to an installable set of packages.\n\n  Problem 1\n    - Root composer.json requires laravel/framework ^13.0 -> satisfiable by laravel/framework[13.0.0].\n    - laravel/framework 13.0.0 requires ext-preflight-stage ^2.0 -> it is missing from your system. Install or enable PHP's preflight-stage extension.\n\nTo enable extensions, verify that they are enabled in your .ini files:\n    - /usr/local/etc/php/conf.d/docker-php-ext-opcache.ini\n    - /usr/local/etc/php/conf.d/docker-php-ext-sodium.ini\n    - /usr/local/etc/php/conf.d/docker-php-ext-zip.ini\nYou can also run `php --ini` in a terminal to see which files are used by PHP in CLI mode.\nAlternatively, you can run Composer with `--ignore-platform-req=ext-preflight-stage` to temporarily ignore these required extensions.\n\nUse the option --with-all-dependencies (-W) to allow upgrades, downgrades and removals for packages currently locked to specific versions.","diagnostics":[{"package":"laravel/framework","constraint":"^13.0","command":["composer","prohibits","laravel/framework","^13.0","--tree","--locked","--no-scripts","--no-plugins","--no-interaction"],"exit_code":1,"stdout_excerpt":"laravel/framework 12.0.0 Metadata-only Laravel 12 package for the offline demo.\n`--laravel/framework 13.0.0 (requires ext-preflight-stage ^2.0 but it is missing) (circular dependency aborted here)\n","stderr_excerpt":"Not finding what you were looking for? Try calling `composer require \"laravel/framework:^13.0\" --dry-run` to get another view on the problem.\n"},{"package":"php","constraint":"8.3.0","command":["composer","prohibits","php","8.3.0","--tree","--locked","--no-scripts","--no-plugins","--no-interaction"],"exit_code":0,"stdout_excerpt":"","stderr_excerpt":"Package \"php 8.3.0\" found in version \"8.3.0\" (version provided by config.platform).\nThere is no installed package depending on \"php\" in versions not matching 8.3.0\n"}]}``
- `stage-root-change-8` (`E2`, high confidence): Recorded an analyzer-only root constraint change for stage laravel-12-to-13. Context: `{"stage_id":"laravel-12-to-13","package":"laravel/framework","from_constraint":"^12.0","to_constraint":"^13.0","supporting_evidence":["laravel-stage-target-3"]}`
- `stage-attempt-6` (`E1`, high confidence): Executed Composer attempt 2 for stage laravel-12-to-13. Context: `{"stage_id":"laravel-12-to-13","attempt":2,"strategy":"root_constraint_remediation","scenario":"laravel-12-to-13-attempt-2-root_constraint_remediation","outcome":"solver_failure"}`
- `solver-8` (`E1`, high confidence): Composer scenario "laravel-12-to-13-attempt-2-root_constraint_remediation" failed. Context: ``{"scenario":"laravel-12-to-13-attempt-2-root_constraint_remediation","targets":[{"package":"laravel/framework","constraint":"^13.0"},{"package":"laravel/tinker","constraint":"^3.0"},{"package":"php","constraint":"8.3.0"}],"exit_code":2,"output_excerpt":"Loading composer repositories with package information\nUpdating dependencies\nYour requirements could not be resolved to an installable set of packages.\n\n  Problem 1\n    - Root composer.json requires laravel/framework ^13.0 -> satisfiable by laravel/framework[13.0.0].\n    - laravel/framework 13.0.0 requires ext-preflight-stage ^2.0 -> it is missing from your system. Install or enable PHP's preflight-stage extension.\n\nTo enable extensions, verify that they are enabled in your .ini files:\n    - /usr/local/etc/php/conf.d/docker-php-ext-opcache.ini\n    - /usr/local/etc/php/conf.d/docker-php-ext-sodium.ini\n    - /usr/local/etc/php/conf.d/docker-php-ext-zip.ini\nYou can also run `php --ini` in a terminal to see which files are used by PHP in CLI mode.\nAlternatively, you can run Composer with `--ignore-platform-req=ext-preflight-stage` to temporarily ignore these required extensions.\n\nUse the option --with-all-dependencies (-W) to allow upgrades, downgrades and removals for packages currently locked to specific versions.","diagnostics":[{"package":"laravel/framework","constraint":"^13.0","command":["composer","prohibits","laravel/framework","^13.0","--tree","--locked","--no-scripts","--no-plugins","--no-interaction"],"exit_code":1,"stdout_excerpt":"laravel/framework 12.0.0 Metadata-only Laravel 12 package for the offline demo.\n`--laravel/framework 13.0.0 (requires ext-preflight-stage ^2.0 but it is missing) (circular dependency aborted here)\n","stderr_excerpt":"Not finding what you were looking for? Try calling `composer require \"laravel/framework:^13.0\" --dry-run` to get another view on the problem.\n"},{"package":"laravel/tinker","constraint":"^3.0","command":["composer","prohibits","laravel/tinker","^3.0","--tree","--locked","--no-scripts","--no-plugins","--no-interaction"],"exit_code":0,"stdout_excerpt":"","stderr_excerpt":"There is no installed package depending on \"laravel/tinker\" in versions not matching ^3.0\nNot finding what you were looking for? Try calling `composer require \"laravel/tinker:^3.0\" --dry-run` to get another view on the problem.\n"},{"package":"php","constraint":"8.3.0","command":["composer","prohibits","php","8.3.0","--tree","--locked","--no-scripts","--no-plugins","--no-interaction"],"exit_code":0,"stdout_excerpt":"","stderr_excerpt":"Package \"php 8.3.0\" found in version \"8.3.0\" (version provided by config.platform).\nThere is no installed package depending on \"php\" in versions not matching 8.3.0\n"}]}``
- `stage-root-change-9` (`E2`, high confidence): Recorded an analyzer-only root constraint change for stage laravel-12-to-13. Context: `{"stage_id":"laravel-12-to-13","package":"laravel/framework","from_constraint":"^12.0","to_constraint":"^13.0","supporting_evidence":["laravel-stage-target-3"]}`
- `stage-root-change-10` (`E2`, high confidence): Recorded an analyzer-only root constraint change for stage laravel-12-to-13. Context: `{"stage_id":"laravel-12-to-13","package":"laravel/tinker","from_constraint":"^2.9","to_constraint":"^3.0","supporting_evidence":["laravel-stage-remediation-6"]}`
- `stage-attempt-7` (`E1`, high confidence): Executed Composer attempt 3 for stage laravel-12-to-13. Context: `{"stage_id":"laravel-12-to-13","attempt":3,"strategy":"root_and_locked_package_remediation","scenario":"laravel-12-to-13-attempt-3-root_and_locked_package_remediation","outcome":"solver_failure"}`
- `solver-9` (`E1`, high confidence): Composer scenario "laravel-12-to-13-attempt-3-root_and_locked_package_remediation" failed. Context: ``{"scenario":"laravel-12-to-13-attempt-3-root_and_locked_package_remediation","targets":[{"package":"laravel/framework","constraint":"^13.0"},{"package":"laravel/tinker","constraint":"^3.0"},{"package":"nunomaduro/collision","constraint":"^8.6"},{"package":"php","constraint":"8.3.0"},{"package":"phpunit/phpunit","constraint":"^12.0"}],"exit_code":2,"output_excerpt":"Loading composer repositories with package information\nUpdating dependencies\nYour requirements could not be resolved to an installable set of packages.\n\n  Problem 1\n    - Root composer.json requires laravel/framework ^13.0 -> satisfiable by laravel/framework[13.0.0].\n    - laravel/framework 13.0.0 requires ext-preflight-stage ^2.0 -> it is missing from your system. Install or enable PHP's preflight-stage extension.\n\nTo enable extensions, verify that they are enabled in your .ini files:\n    - /usr/local/etc/php/conf.d/docker-php-ext-opcache.ini\n    - /usr/local/etc/php/conf.d/docker-php-ext-sodium.ini\n    - /usr/local/etc/php/conf.d/docker-php-ext-zip.ini\nYou can also run `php --ini` in a terminal to see which files are used by PHP in CLI mode.\nAlternatively, you can run Composer with `--ignore-platform-req=ext-preflight-stage` to temporarily ignore these required extensions.","diagnostics":[{"package":"laravel/framework","constraint":"^13.0","command":["composer","prohibits","laravel/framework","^13.0","--tree","--locked","--no-scripts","--no-plugins","--no-interaction"],"exit_code":1,"stdout_excerpt":"laravel/framework 12.0.0 Metadata-only Laravel 12 package for the offline demo.\n`--laravel/framework 13.0.0 (requires ext-preflight-stage ^2.0 but it is missing) (circular dependency aborted here)\n","stderr_excerpt":"Not finding what you were looking for? Try calling `composer require \"laravel/framework:^13.0\" --dry-run` to get another view on the problem.\n"},{"package":"laravel/tinker","constraint":"^3.0","command":["composer","prohibits","laravel/tinker","^3.0","--tree","--locked","--no-scripts","--no-plugins","--no-interaction"],"exit_code":0,"stdout_excerpt":"","stderr_excerpt":"There is no installed package depending on \"laravel/tinker\" in versions not matching ^3.0\nNot finding what you were looking for? Try calling `composer require \"laravel/tinker:^3.0\" --dry-run` to get another view on the problem.\n"},{"package":"php","constraint":"8.3.0","command":["composer","prohibits","php","8.3.0","--tree","--locked","--no-scripts","--no-plugins","--no-interaction"],"exit_code":0,"stdout_excerpt":"","stderr_excerpt":"Package \"php 8.3.0\" found in version \"8.3.0\" (version provided by config.platform).\nThere is no installed package depending on \"php\" in versions not matching 8.3.0\n"},{"package":"phpunit/phpunit","constraint":"^12.0","command":["composer","prohibits","phpunit/phpunit","^12.0","--tree","--locked","--no-scripts","--no-plugins","--no-interaction"],"exit_code":0,"stdout_excerpt":"","stderr_excerpt":"There is no installed package depending on \"phpunit/phpunit\" in versions not matching ^12.0\nNot finding what you were looking for? Try calling `composer require --dev \"phpunit/phpunit:^12.0\" --dry-run` to get another view on the problem.\n"}]}``
- `stage-root-change-11` (`E2`, high confidence): Recorded an analyzer-only root constraint change for stage laravel-12-to-13. Context: `{"stage_id":"laravel-12-to-13","package":"laravel/framework","from_constraint":"^12.0","to_constraint":"^13.0","supporting_evidence":["laravel-stage-target-3"]}`
- `stage-root-change-12` (`E2`, high confidence): Recorded an analyzer-only root constraint change for stage laravel-12-to-13. Context: `{"stage_id":"laravel-12-to-13","package":"laravel/tinker","from_constraint":"^2.9","to_constraint":"^3.0","supporting_evidence":["laravel-stage-remediation-6"]}`
- `stage-root-change-13` (`E2`, high confidence): Recorded an analyzer-only root constraint change for stage laravel-12-to-13. Context: `{"stage_id":"laravel-12-to-13","package":"nunomaduro/collision","from_constraint":"^8.1","to_constraint":"^8.6","supporting_evidence":["laravel-stage-remediation-8"]}`
- `stage-root-change-14` (`E2`, high confidence): Recorded an analyzer-only root constraint change for stage laravel-12-to-13. Context: `{"stage_id":"laravel-12-to-13","package":"phpunit/phpunit","from_constraint":"^11.0.1","to_constraint":"^12.0","supporting_evidence":["laravel-stage-remediation-7"]}`
- `source-1` (`E3`, high confidence): Detected Illuminate\Foundation\Http\Middleware\VerifyCsrfToken in tests/Feature/LegacyCsrfTest.php. Context: `{"file":"tests/Feature/LegacyCsrfTest.php","line":7,"usage_type":"namespace_import"}`
- `source-2` (`E3`, high confidence): Detected Illuminate\Foundation\Http\Middleware\VerifyCsrfToken in tests/Feature/LegacyCsrfTest.php. Context: `{"file":"tests/Feature/LegacyCsrfTest.php","line":13,"usage_type":"class_constant_access"}`
- `source-3` (`E3`, high confidence): Detected Illuminate\Foundation\Http\Middleware\VerifyCsrfToken in tests/Feature/LegacyCsrfTest.php. Context: `{"file":"tests/Feature/LegacyCsrfTest.php","line":13,"usage_type":"middleware_reference"}`
- `laravel-transition-1` (`E4`, medium confidence): The implemented Laravel 10 to 11 rule pack covers this requested transition. Context: `{"source_major":10,"target_major":11,"rule_pack":"laravel-10-to-11","source":"https://laravel.com/docs/11.x/upgrade"}`
- `laravel-transition-2` (`E4`, medium confidence): The implemented Laravel 11 to 12 rule pack covers this requested transition. Context: `{"source_major":11,"target_major":12,"rule_pack":"laravel-11-to-12","source":"https://laravel.com/docs/12.x/upgrade"}`
- `laravel-transition-3` (`E4`, medium confidence): The implemented Laravel 12 to 13 rule pack covers this requested transition. Context: `{"source_major":12,"target_major":13,"rule_pack":"laravel-12-to-13","source":"https://github.com/laravel/docs/blob/9c5a062c14069bab9054b558829e282f9593a065/upgrade.md"}`
- `laravel-framework-constraint-1` (`E2`, high confidence): The root Laravel framework constraint does not include the requested target major. Context: `{"package":"laravel/framework","root_constraint":"^10.0","target_constraint":"^13.0","target_laravel_major":13}`
- `laravel-package-nunomaduro_collision-1` (`E2`, high confidence): nunomaduro/collision is present in Composer metadata. Context: `{"package":"nunomaduro/collision","locked_version":"7.11.0","root_constraint":"^7.11","framework_requirements":[],"target_laravel_major":11}`
- `laravel-package-guidance-1` (`E4`, medium confidence): The encoded Laravel 11 guidance maps nunomaduro/collision to `^8.1`. Context: `{"package":"nunomaduro/collision","target_laravel_major":11,"compatible_package_constraint":"^8.1","sources":["https://laravel.com/docs/11.x/upgrade"]}`
- `laravel-package-phpunit_phpunit-1` (`E2`, high confidence): phpunit/phpunit is present in Composer metadata. Context: `{"package":"phpunit/phpunit","locked_version":"10.0.0","root_constraint":"^10.0","framework_requirements":[],"target_laravel_major":11}`
- `laravel-package-guidance-2` (`E4`, medium confidence): The encoded Laravel 11 guidance maps phpunit/phpunit to `^11.0.1`. Context: `{"package":"phpunit/phpunit","target_laravel_major":11,"compatible_package_constraint":"^11.0.1","sources":["https://github.com/laravel/laravel/blob/11.x/composer.json"]}`
- `laravel-package-phpunit_phpunit-2` (`E2`, high confidence): phpunit/phpunit is present in Composer metadata. Context: `{"package":"phpunit/phpunit","locked_version":"10.0.0","root_constraint":"^10.0","framework_requirements":[],"target_laravel_major":12}`
- `laravel-package-guidance-3` (`E4`, medium confidence): The encoded Laravel 12 guidance maps phpunit/phpunit to `^11.0`. Context: `{"package":"phpunit/phpunit","target_laravel_major":12,"compatible_package_constraint":"^11.0","sources":["https://laravel.com/docs/12.x/upgrade"]}`
- `laravel-package-nesbot_carbon-1` (`E2`, high confidence): nesbot/carbon is present in Composer metadata. Context: `{"package":"nesbot/carbon","locked_version":"2.72.0","root_constraint":"^2.72","framework_requirements":[],"target_laravel_major":12}`
- `laravel-package-guidance-4` (`E4`, medium confidence): The encoded Laravel 12 guidance maps nesbot/carbon to `^3.0`. Context: `{"package":"nesbot/carbon","target_laravel_major":12,"compatible_package_constraint":"^3.0","sources":["https://laravel.com/docs/12.x/upgrade"]}`
- `laravel-package-nunomaduro_collision-2` (`E2`, high confidence): nunomaduro/collision is present in Composer metadata. Context: `{"package":"nunomaduro/collision","locked_version":"7.11.0","root_constraint":"^7.11","framework_requirements":[],"target_laravel_major":12}`
- `laravel-package-guidance-5` (`E4`, medium confidence): The encoded Laravel 12 guidance maps nunomaduro/collision to `^8.6`. Context: `{"package":"nunomaduro/collision","target_laravel_major":12,"compatible_package_constraint":"^8.6","sources":["https://github.com/laravel/laravel/blob/12.x/composer.json"]}`
- `laravel-package-laravel_tinker-1` (`E2`, high confidence): laravel/tinker is present in Composer metadata. Context: `{"package":"laravel/tinker","locked_version":"2.9.0","root_constraint":"^2.9","framework_requirements":[],"target_laravel_major":13}`
- `laravel-package-guidance-6` (`E4`, medium confidence): The encoded Laravel 13 guidance maps laravel/tinker to `^3.0`. Context: `{"package":"laravel/tinker","target_laravel_major":13,"compatible_package_constraint":"^3.0","sources":["https://github.com/laravel/docs/blob/9c5a062c14069bab9054b558829e282f9593a065/upgrade.md"]}`
- `laravel-package-phpunit_phpunit-3` (`E2`, high confidence): phpunit/phpunit is present in Composer metadata. Context: `{"package":"phpunit/phpunit","locked_version":"10.0.0","root_constraint":"^10.0","framework_requirements":[],"target_laravel_major":13}`
- `laravel-package-guidance-7` (`E4`, medium confidence): The encoded Laravel 13 guidance maps phpunit/phpunit to `^12.0`. Context: `{"package":"phpunit/phpunit","target_laravel_major":13,"compatible_package_constraint":"^12.0","sources":["https://github.com/laravel/docs/blob/9c5a062c14069bab9054b558829e282f9593a065/upgrade.md","https://github.com/laravel/laravel/blob/c926b8ca7fa01e71852e19141f2bdd7fabfb6ade/composer.json"]}`
- `laravel-package-nunomaduro_collision-3` (`E2`, high confidence): nunomaduro/collision is present in Composer metadata. Context: `{"package":"nunomaduro/collision","locked_version":"7.11.0","root_constraint":"^7.11","framework_requirements":[],"target_laravel_major":13}`
- `laravel-package-guidance-8` (`E4`, medium confidence): The encoded Laravel 13 guidance maps nunomaduro/collision to `^8.6`. Context: `{"package":"nunomaduro/collision","target_laravel_major":13,"compatible_package_constraint":"^8.6","sources":["https://github.com/laravel/laravel/blob/c926b8ca7fa01e71852e19141f2bdd7fabfb6ade/composer.json"]}`
- `laravel-request-forgery-guidance-1` (`E4`, high confidence): Laravel 13 renames the CSRF middleware to PreventRequestForgery and deprecates the previous aliases. Context: `{"legacy_symbols":["Illuminate\\Foundation\\Http\\Middleware\\VerifyCsrfToken","Illuminate\\Foundation\\Http\\Middleware\\ValidateCsrfToken"],"replacement_symbol":"Illuminate\\Foundation\\Http\\Middleware\\PreventRequestForgery","source":"https://github.com/laravel/docs/blob/9c5a062c14069bab9054b558829e282f9593a065/upgrade.md"}`
- `root-constraint-1` (`E2`, high confidence): Compared the root requirement for laravel/framework with the requested target. Context: `{"package":"laravel/framework","from_constraint":"^10.0","to_constraint":"^13.0"}`
- `plan-1` (`E5`, low confidence): Generated conservative staged actions from the requested targets and detected findings. Context: `{"target_count":2,"root_constraint_change_count":1,"blocker_count":2,"source_finding_count":1,"framework_finding_count":10}`
