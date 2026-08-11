# PHP Upgrade Preflight Report

Resolution: **blocked** | Schema: `0.7` | Tool: `php-upgrade-preflight 0.2.0`

## Analysis Request
- Project: `<PROJECT_PATH>`
- Current PHP: `7.4`
- Target PHP: `8.0.0`
- Source paths: `default project paths`
- Framework integrations: `automatic detection`
- Requested format: `markdown`
- Output destination: `stdout`
- Targets:
  - `laravel/framework`: `^8.0`
  - `php`: `8.0.0`

## Platform Provenance
- Analyzer PHP: `<ANALYZER_PHP_VERSION>` (provenance: `runtime`)
- Current project PHP: `7.4` (provenance: `request`)
- Target PHP: `8.0.0` (provenance: `request`)
- Extensions: provenance `analyzer_runtime`; explicitly modeled: no; completeness: `none`; unmodeled values: `analyzer_runtime`

## Project State
- Analyzed path: `<PROJECT_PATH>`
- Composer platform PHP: `not configured`
- Locked packages: `2`
- Root requirements:
  - `php`: `^7.4`
  - `laravel/framework`: `^7.0`
  - `facade/ignition`: `^1.0`

## Composer Scenarios
- `baseline-validation`: succeeded (outcome `success`, Composer `unknown`, duration `1 ms`, exit `0`, failure type `none`)
  - command argv: `["composer","validate","--check-lock","--no-check-publish","--no-scripts","--no-plugins","--no-interaction"]`
  - temporary workspace: `not preserved`
  - stdout excerpt:
    ```text
    Fixture baseline is valid.
    ```
  - stderr excerpt: *(empty)*
  - candidate lock: SHA-256 `ff4acbafded8f33cd64c19ffb5d943b24a4041a2ba81fa4b8aa0f5aef137703d`, content hash `ignition-legacy-skeleton`, packages `2`
  - diagnostics: none
- `exact-target`: failed (outcome `solver_failure`, Composer `unknown`, duration `1 ms`, exit `2`, failure type `solver`)
  - command argv: `["composer","update","laravel/framework","--no-scripts","--no-plugins","--no-install","--no-audit","--no-progress","--no-interaction"]`
  - temporary workspace: `not preserved`
  - stdout excerpt: *(empty)*
  - stderr excerpt:
    ```text
    Your requirements could not be resolved to an installable set of packages.
    - Root composer.json retains legacy package constraints that exclude the requested Laravel target.
    ```
  - candidate lock: not available
  - diagnostic for `laravel/framework ^8.0` (exit `1`), command argv: `["composer","prohibits","laravel/framework","^8.0","--tree","--locked","--no-scripts","--no-plugins","--no-interaction"]`
    - stdout excerpt: *(empty)*
    - stderr excerpt:
      ```text
      No additional fixture diagnostic.
      ```
  - diagnostic for `php 8.0.0` (exit `1`), command argv: `["composer","prohibits","php","8.0.0","--tree","--locked","--no-scripts","--no-plugins","--no-interaction"]`
    - stdout excerpt: *(empty)*
    - stderr excerpt:
      ```text
      No additional fixture diagnostic.
      ```
- `target-with-all-dependencies`: failed (outcome `solver_failure`, Composer `unknown`, duration `1 ms`, exit `2`, failure type `solver`)
  - command argv: `["composer","update","laravel/framework","--with-all-dependencies","--no-scripts","--no-plugins","--no-install","--no-audit","--no-progress","--no-interaction"]`
  - temporary workspace: `not preserved`
  - stdout excerpt: *(empty)*
  - stderr excerpt:
    ```text
    Your requirements could not be resolved to an installable set of packages.
    - Root composer.json retains legacy package constraints that exclude the requested Laravel target.
    ```
  - candidate lock: not available
  - diagnostic for `laravel/framework ^8.0` (exit `1`), command argv: `["composer","prohibits","laravel/framework","^8.0","--tree","--locked","--no-scripts","--no-plugins","--no-interaction"]`
    - stdout excerpt: *(empty)*
    - stderr excerpt:
      ```text
      No additional fixture diagnostic.
      ```
  - diagnostic for `php 8.0.0` (exit `1`), command argv: `["composer","prohibits","php","8.0.0","--tree","--locked","--no-scripts","--no-plugins","--no-interaction"]`
    - stdout excerpt: *(empty)*
    - stderr excerpt:
      ```text
      No additional fixture diagnostic.
      ```
- `minimal-changes`: failed (outcome `solver_failure`, Composer `unknown`, duration `1 ms`, exit `2`, failure type `solver`)
  - command argv: `["composer","update","laravel/framework","--with-all-dependencies","--minimal-changes","--no-scripts","--no-plugins","--no-install","--no-audit","--no-progress","--no-interaction"]`
  - temporary workspace: `not preserved`
  - stdout excerpt: *(empty)*
  - stderr excerpt:
    ```text
    Your requirements could not be resolved to an installable set of packages.
    - Root composer.json retains legacy package constraints that exclude the requested Laravel target.
    ```
  - candidate lock: not available
  - diagnostic for `laravel/framework ^8.0` (exit `1`), command argv: `["composer","prohibits","laravel/framework","^8.0","--tree","--locked","--no-scripts","--no-plugins","--no-interaction"]`
    - stdout excerpt: *(empty)*
    - stderr excerpt:
      ```text
      No additional fixture diagnostic.
      ```
  - diagnostic for `php 8.0.0` (exit `1`), command argv: `["composer","prohibits","php","8.0.0","--tree","--locked","--no-scripts","--no-plugins","--no-interaction"]`
    - stdout excerpt: *(empty)*
    - stderr excerpt:
      ```text
      No additional fixture diagnostic.
      ```
- `target-platform-only`: failed (outcome `solver_failure`, Composer `unknown`, duration `1 ms`, exit `2`, failure type `solver`)
  - command argv: `["composer","update","--no-scripts","--no-plugins","--no-install","--no-audit","--no-progress","--no-interaction"]`
  - temporary workspace: `not preserved`
  - stdout excerpt: *(empty)*
  - stderr excerpt:
    ```text
    Your requirements could not be resolved to an installable set of packages.
    - Root composer.json retains legacy package constraints that exclude the requested Laravel target.
    ```
  - candidate lock: not available
  - diagnostic for `php 8.0.0` (exit `1`), command argv: `["composer","prohibits","php","8.0.0","--tree","--locked","--no-scripts","--no-plugins","--no-interaction"]`
    - stdout excerpt: *(empty)*
    - stderr excerpt:
      ```text
      No additional fixture diagnostic.
      ```
- `staged-targets`: failed (outcome `solver_failure`, Composer `unknown`, duration `1 ms`, exit `2`, failure type `solver`)
  - command argv: `["composer","update","laravel/framework","--with-all-dependencies","--no-scripts","--no-plugins","--no-install","--no-audit","--no-progress","--no-interaction"]`
  - temporary workspace: `not preserved`
  - stdout excerpt: *(empty)*
  - stderr excerpt:
    ```text
    Your requirements could not be resolved to an installable set of packages.
    - Root composer.json retains legacy package constraints that exclude the requested Laravel target.
    ```
  - candidate lock: not available
  - diagnostic for `laravel/framework ^8.0` (exit `1`), command argv: `["composer","prohibits","laravel/framework","^8.0","--tree","--locked","--no-scripts","--no-plugins","--no-interaction"]`
    - stdout excerpt: *(empty)*
    - stderr excerpt:
      ```text
      No additional fixture diagnostic.
      ```

## Package Changes
- No lockfile changes detected.

## Framework Transition Guidance
- `laravel`: `supported` (`7` -> `8`; evidence: `laravel-transition-1`)
  - hop `7` -> `8`: `supported`; rule pack `laravel-7-to-8` (evidence: `laravel-transition-1`)

## Root Constraint Changes
- `laravel/framework`: updated `^7.0` -> `^8.0`. The declared root constraint differs from the requested target. (evidence: `root-constraint-1`)

## Blockers
- `unknown-composer-failure` `laravel/framework`: Composer failed, but the blocker type could not be classified. (low confidence; evidence: `solver-1`, `solver-2`, `solver-3`, `solver-5`)
  - requested `^8.0`; blocker `-`; locked `-`; conflict `-`
  - dependency path: `laravel/framework`
  - option: Inspect the linked Composer evidence.
  - option: Run `composer prohibits laravel/framework <constraint> --tree` in an isolated copy.
- `unknown-composer-failure` `php`: Composer failed, but the blocker type could not be classified. (low confidence; evidence: `solver-4`)
  - requested `8.0.0`; blocker `-`; locked `-`; conflict `-`
  - dependency path: `php`
  - option: Inspect the linked Composer evidence.
  - option: Run `composer prohibits php <constraint> --tree` in an isolated copy.

## Source Inventory
- `class_constant_access` `Fruitcake\Cors\HandleCors` in `app/Http/Kernel.php:10` (evidence: `source-1`)
- `middleware_reference` `Fruitcake\Cors\HandleCors` in `app/Http/Kernel.php:10` (evidence: `source-2`)
- `class_constant_access` `Facade\Ignition\IgnitionServiceProvider` in `config/app.php:5` (evidence: `source-3`)
- `class_constant_access` `Facade\Ignition\Facades\Flare` in `config/app.php:8` (evidence: `source-4`)
- `service_provider` `Facade\Ignition\IgnitionServiceProvider` in `config/app.php:5` (evidence: `source-5`)
- `facade_alias` `Facade\Ignition\Facades\Flare` in `config/app.php:8` (evidence: `source-6`)

## Actionable Source Impact
- `low` impact for `package unknown` (`unknown` ownership; `framework_rule`): Referenced by active laravel compatibility guidance; package ownership has not been established. (evidence: `source-2`, `source-5`, `source-6`, `laravel-skeleton-guidance-1`)
  - `middleware_reference` `Fruitcake\Cors\HandleCors` in `app/Http/Kernel.php:10` (evidence: `source-2`)
- `low` impact for `package unknown` (`unknown` ownership; `framework_rule`): Referenced by active laravel compatibility guidance; package ownership has not been established. (evidence: `source-5`, `source-2`, `source-6`, `laravel-skeleton-guidance-1`)
  - `service_provider` `Facade\Ignition\IgnitionServiceProvider` in `config/app.php:5` (evidence: `source-5`)
- `low` impact for `package unknown` (`unknown` ownership; `framework_rule`): Referenced by active laravel compatibility guidance; package ownership has not been established. (evidence: `source-6`, `source-2`, `source-5`, `laravel-skeleton-guidance-1`)
  - `facade_alias` `Facade\Ignition\Facades\Flare` in `config/app.php:8` (evidence: `source-6`)

## Framework Findings
- `laravel` `high`: Update the root laravel/framework constraint from `^7.0` to a constraint compatible with Laravel 8. (evidence: `laravel-framework-constraint-1`)
  - applies to hops: `7 -> 8`
- `laravel` `high`: The root PHP constraint `^7.4` excludes target PHP `8.0.0`; update it for the Laravel 8 upgrade. (evidence: `laravel-php-constraint-1`)
  - applies to hops: `7 -> 8`
- `laravel` `medium`: facade/ignition 1.16.4 is outside the encoded Laravel 8 review range `>=2.3.6 <3.0`; review its upgrade or replacement. (evidence: `laravel-package-facade_ignition-1`, `laravel-package-guidance-1`)
  - applies to hops: `7 -> 8`
- `laravel` `low`: Compare detected Laravel skeleton-managed integration locations (Kernel middleware, app config providers/aliases, or TrustProxies inheritance) with the Laravel 8 skeleton; these are review locations, not confirmed incompatibilities. (evidence: `source-2`, `source-5`, `source-6`, `laravel-skeleton-guidance-1`)
  - applies to hops: `7 -> 8`

## Staged Plan
1. **constraints** — Prepare the requested root constraint changes before dependency resolution. (evidence: `plan-1`, `root-constraint-1`)
   - Update the `laravel/framework` root constraint to `^8.0`.
   - Select a root PHP constraint that includes target platform PHP 8.0.0 without pinning an exact patch version.
2. **dependencies** — Resolve dependency blockers and review the resulting lockfile transition. (evidence: `plan-1`, `solver-1`, `solver-2`, `solver-3`, `solver-5`, `solver-4`)
   - Resolve the `unknown-composer-failure` blocker affecting `laravel/framework`.
   - Resolve the `unknown-composer-failure` blocker affecting `php`.
   - Rerun the isolated Composer scenarios after resolving the reported blockers.
3. **application** — Apply source and framework migration work after dependency resolution is stable. (evidence: `plan-1`, `source-2`, `source-5`, `source-6`, `laravel-skeleton-guidance-1`, `laravel-framework-constraint-1`, `laravel-php-constraint-1`, `laravel-package-facade_ignition-1`, `laravel-package-guidance-1`)
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
- Effort: `6-30` hours (low confidence)
- Effort components:
  - `dependency_resolution`: `3-8` hours
  - `source_changes`: `1-14` hours
  - `tests_and_debugging`: `2-8` hours
- Effort assumptions:
  - Estimate is heuristic until project-specific tests and Composer solver output are reviewed.

## Test Guidance
- **composer-validation** (`required`): Validate the edited Composer manifest before dependency installation. Command: `composer validate --strict`.
- **project-test-suite** (`required`): Identify and run the project test suite; no Composer test script was detected. Command: project-specific command required.
- **platform-requirements** (`required`): Confirm the installed dependencies satisfy PHP 8.0.0 and the deployment extensions. Command: `composer check-platform-reqs`.
- **focused-regressions** (`recommended`): Add or run focused regression coverage for the reported source and framework findings. Command: project-specific command required.

## Uncertainties
- Dependency resolution does not prove application runtime compatibility; the project test suite must run on the target runtime.
- Root PHP constraint "^7.4" does not include target platform PHP 8.0.0; select an appropriate Composer constraint instead of using the exact simulated platform version.
- No Composer "test" script was found, so the project's canonical test command is unknown.
- Composer extension checks used the analyzer runtime because no complete explicit extension platform was supplied.

## Evidence
- `solver-1` (`E1`, high confidence): Composer scenario "exact-target" failed. Context: `{"scenario":"exact-target","targets":[{"package":"laravel/framework","constraint":"^8.0"},{"package":"php","constraint":"8.0.0"}],"exit_code":2,"output_excerpt":"Your requirements could not be resolved to an installable set of packages.\n- Root composer.json retains legacy package constraints that exclude the requested Laravel target.","diagnostics":[{"package":"laravel/framework","constraint":"^8.0","command":["composer","prohibits","laravel/framework","^8.0","--tree","--locked","--no-scripts","--no-plugins","--no-interaction"],"exit_code":1,"stdout_excerpt":"","stderr_excerpt":"No additional fixture diagnostic."},{"package":"php","constraint":"8.0.0","command":["composer","prohibits","php","8.0.0","--tree","--locked","--no-scripts","--no-plugins","--no-interaction"],"exit_code":1,"stdout_excerpt":"","stderr_excerpt":"No additional fixture diagnostic."}]}`
- `solver-2` (`E1`, high confidence): Composer scenario "target-with-all-dependencies" failed. Context: `{"scenario":"target-with-all-dependencies","targets":[{"package":"laravel/framework","constraint":"^8.0"},{"package":"php","constraint":"8.0.0"}],"exit_code":2,"output_excerpt":"Your requirements could not be resolved to an installable set of packages.\n- Root composer.json retains legacy package constraints that exclude the requested Laravel target.","diagnostics":[{"package":"laravel/framework","constraint":"^8.0","command":["composer","prohibits","laravel/framework","^8.0","--tree","--locked","--no-scripts","--no-plugins","--no-interaction"],"exit_code":1,"stdout_excerpt":"","stderr_excerpt":"No additional fixture diagnostic."},{"package":"php","constraint":"8.0.0","command":["composer","prohibits","php","8.0.0","--tree","--locked","--no-scripts","--no-plugins","--no-interaction"],"exit_code":1,"stdout_excerpt":"","stderr_excerpt":"No additional fixture diagnostic."}]}`
- `solver-3` (`E1`, high confidence): Composer scenario "minimal-changes" failed. Context: `{"scenario":"minimal-changes","targets":[{"package":"laravel/framework","constraint":"^8.0"},{"package":"php","constraint":"8.0.0"}],"exit_code":2,"output_excerpt":"Your requirements could not be resolved to an installable set of packages.\n- Root composer.json retains legacy package constraints that exclude the requested Laravel target.","diagnostics":[{"package":"laravel/framework","constraint":"^8.0","command":["composer","prohibits","laravel/framework","^8.0","--tree","--locked","--no-scripts","--no-plugins","--no-interaction"],"exit_code":1,"stdout_excerpt":"","stderr_excerpt":"No additional fixture diagnostic."},{"package":"php","constraint":"8.0.0","command":["composer","prohibits","php","8.0.0","--tree","--locked","--no-scripts","--no-plugins","--no-interaction"],"exit_code":1,"stdout_excerpt":"","stderr_excerpt":"No additional fixture diagnostic."}]}`
- `solver-4` (`E1`, high confidence): Composer scenario "target-platform-only" failed. Context: `{"scenario":"target-platform-only","targets":[{"package":"php","constraint":"8.0.0"}],"exit_code":2,"output_excerpt":"Your requirements could not be resolved to an installable set of packages.\n- Root composer.json retains legacy package constraints that exclude the requested Laravel target.","diagnostics":[{"package":"php","constraint":"8.0.0","command":["composer","prohibits","php","8.0.0","--tree","--locked","--no-scripts","--no-plugins","--no-interaction"],"exit_code":1,"stdout_excerpt":"","stderr_excerpt":"No additional fixture diagnostic."}]}`
- `solver-5` (`E1`, high confidence): Composer scenario "staged-targets" failed. Context: `{"scenario":"staged-targets","targets":[{"package":"laravel/framework","constraint":"^8.0"},{"package":"php","constraint":"7.4.0"}],"exit_code":2,"output_excerpt":"Your requirements could not be resolved to an installable set of packages.\n- Root composer.json retains legacy package constraints that exclude the requested Laravel target.","diagnostics":[{"package":"laravel/framework","constraint":"^8.0","command":["composer","prohibits","laravel/framework","^8.0","--tree","--locked","--no-scripts","--no-plugins","--no-interaction"],"exit_code":1,"stdout_excerpt":"","stderr_excerpt":"No additional fixture diagnostic."}]}`
- `source-1` (`E3`, high confidence): Detected Fruitcake\Cors\HandleCors in app/Http/Kernel.php. Context: `{"file":"app/Http/Kernel.php","line":10,"usage_type":"class_constant_access"}`
- `source-2` (`E3`, high confidence): Detected Fruitcake\Cors\HandleCors in app/Http/Kernel.php. Context: `{"file":"app/Http/Kernel.php","line":10,"usage_type":"middleware_reference"}`
- `source-3` (`E3`, high confidence): Detected Facade\Ignition\IgnitionServiceProvider in config/app.php. Context: `{"file":"config/app.php","line":5,"usage_type":"class_constant_access"}`
- `source-4` (`E3`, high confidence): Detected Facade\Ignition\Facades\Flare in config/app.php. Context: `{"file":"config/app.php","line":8,"usage_type":"class_constant_access"}`
- `source-5` (`E3`, high confidence): Detected Facade\Ignition\IgnitionServiceProvider in config/app.php. Context: `{"file":"config/app.php","line":5,"usage_type":"service_provider"}`
- `source-6` (`E3`, high confidence): Detected Facade\Ignition\Facades\Flare in config/app.php. Context: `{"file":"config/app.php","line":8,"usage_type":"facade_alias"}`
- `laravel-transition-1` (`E4`, medium confidence): The retained Laravel 7 to 8 rule pack covers this requested transition. Context: `{"source_major":7,"target_major":8,"rule_pack":"laravel-7-to-8","source":"https://laravel.com/docs/8.x/upgrade"}`
- `laravel-framework-constraint-1` (`E2`, high confidence): The root Laravel framework constraint does not include the requested target major. Context: `{"package":"laravel/framework","root_constraint":"^7.0","target_constraint":"^8.0","target_laravel_major":8}`
- `laravel-php-constraint-1` (`E2`, high confidence): The detected PHP target or root constraint does not satisfy the Laravel target PHP range. Context: `{"observation":"target_php","observed_php":"8.0.0","root_php_constraint":"^7.4","required_php":"^7.3|^8.0","target_laravel_major":8,"laravel_range_satisfied":true,"root_constraint_satisfied":false}`
- `laravel-package-facade_ignition-1` (`E2`, high confidence): facade/ignition is present in Composer metadata. Context: `{"package":"facade/ignition","locked_version":"1.16.4","root_constraint":"^1.0","framework_requirements":[],"target_laravel_major":8}`
- `laravel-package-guidance-1` (`E4`, medium confidence): The encoded Laravel 8 guidance maps facade/ignition to `>=2.3.6 <3.0`. Context: `{"package":"facade/ignition","target_laravel_major":8,"compatible_package_constraint":">=2.3.6 <3.0","sources":["https://laravel.com/docs/8.x/upgrade"]}`
- `laravel-skeleton-guidance-1` (`E5`, low confidence): Detected Kernel middleware, application provider/alias entries, or TrustProxies inheritance identify skeleton-managed integration points for manual comparison. Context: `{"target_laravel_major":8,"indicator_count":3,"indicators":[{"file":"app/Http/Kernel.php","line":10,"symbol":"Fruitcake\\Cors\\HandleCors","usage_type":"middleware_reference"},{"file":"config/app.php","line":5,"symbol":"Facade\\Ignition\\IgnitionServiceProvider","usage_type":"service_provider"},{"file":"config/app.php","line":8,"symbol":"Facade\\Ignition\\Facades\\Flare","usage_type":"facade_alias"}],"claim":"review_location_only"}`
- `root-constraint-1` (`E2`, high confidence): Compared the root requirement for laravel/framework with the requested target. Context: `{"package":"laravel/framework","from_constraint":"^7.0","to_constraint":"^8.0"}`
- `plan-1` (`E5`, low confidence): Generated conservative staged actions from the requested targets and detected findings. Context: `{"target_count":2,"root_constraint_change_count":1,"blocker_count":2,"source_finding_count":3,"framework_finding_count":4}`
