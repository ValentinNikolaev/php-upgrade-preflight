# PHP Upgrade Preflight Report

Resolution: **blocked** | Staged: **blocked** | Schema: `0.8` | Tool: `php-upgrade-preflight 0.3.2`

## Analysis Request
- Project: `<PROJECT_PATH>`
- Current PHP: `7.4`
- Target PHP: `8.0.0`
- Source paths: `default project paths`
- Framework integrations: `automatic detection`
- Target platform profile: `not supplied`
- Composer execution mode: `compatible`
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
- Target platform profile: none; platform packages not explicitly modeled above remain analyzer-host dependent.

## Composer Execution Provenance
- Mode: `compatible`; Composer version: `unknown`; expected: `>=2.0.0 <3.0.0`; matches: `unknown`
- Executable selection: `path_search`; environment: `inherited`; network: `inherited`; repositories: `project_and_global`
- Timeouts: scenario `300 s`; diagnostic `60 s`; Composer home: `inherited`
- Inheritance: global configuration yes; credentials may be inherited yes; offline requested no; process/OS isolation no
- Side effects: scripts disabled; plugins disabled; installation disabled; audit disabled; interaction disabled; progress disabled.

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
  - candidate lock: SHA-256 `69022e490ecf9dbf9b0fb7a55f262af924a62fd239285431b744c8fbf85bfe69`, content hash `ignition-legacy-skeleton`, packages `2`
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
  - diagnostic for `laravel/framework ^8.0` (outcome `success`, exit `1`), command argv: `["composer","prohibits","laravel/framework","^8.0","--tree","--locked","--no-scripts","--no-plugins","--no-interaction"]`
    - stdout excerpt: *(empty)*
    - stderr excerpt:
      ```text
      No additional fixture diagnostic.
      ```
  - diagnostic for `php 8.0.0` (outcome `success`, exit `1`), command argv: `["composer","prohibits","php","8.0.0","--tree","--locked","--no-scripts","--no-plugins","--no-interaction"]`
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
  - diagnostic for `laravel/framework ^8.0` (outcome `success`, exit `1`), command argv: `["composer","prohibits","laravel/framework","^8.0","--tree","--locked","--no-scripts","--no-plugins","--no-interaction"]`
    - stdout excerpt: *(empty)*
    - stderr excerpt:
      ```text
      No additional fixture diagnostic.
      ```
  - diagnostic for `php 8.0.0` (outcome `success`, exit `1`), command argv: `["composer","prohibits","php","8.0.0","--tree","--locked","--no-scripts","--no-plugins","--no-interaction"]`
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
  - diagnostic for `laravel/framework ^8.0` (outcome `success`, exit `1`), command argv: `["composer","prohibits","laravel/framework","^8.0","--tree","--locked","--no-scripts","--no-plugins","--no-interaction"]`
    - stdout excerpt: *(empty)*
    - stderr excerpt:
      ```text
      No additional fixture diagnostic.
      ```
  - diagnostic for `php 8.0.0` (outcome `success`, exit `1`), command argv: `["composer","prohibits","php","8.0.0","--tree","--locked","--no-scripts","--no-plugins","--no-interaction"]`
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
  - diagnostic for `php 8.0.0` (outcome `success`, exit `1`), command argv: `["composer","prohibits","php","8.0.0","--tree","--locked","--no-scripts","--no-plugins","--no-interaction"]`
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
  - diagnostic for `laravel/framework ^8.0` (outcome `success`, exit `1`), command argv: `["composer","prohibits","laravel/framework","^8.0","--tree","--locked","--no-scripts","--no-plugins","--no-interaction"]`
    - stdout excerpt: *(empty)*
    - stderr excerpt:
      ```text
      No additional fixture diagnostic.
      ```

## Staged Composer Resolution
- Execution: `evaluated`; status: `blocked`; provider: `laravel`; stop reason: `blocking_registry_not_cleared`
- **laravel-7-to-8** (`7` -> `8`): execution `evaluated`; resolution `blocked`; selected attempt `none`
  - analysis PHP: `8.0.0`; source snapshot: `original_project`
  - This stage assessment inspects the original project source snapshot; it does not assume edits from an earlier stage were applied.
  - effective platform: `1227859358166ebdf070c802a94bc3d37e5f430d3f57f52f7c9db345bcc2356e`; completeness `partial`; profile `none`
  - Composer policy: `0c155d2582c9452186059dbe78d0da2d730f798c6c1d69f356c43ca832fcf1b9`; mode `compatible`; stage duration `1 ms`
  - stage evidence: `laravel-stage-target-1`, `laravel-stage-remediation-1`, `stage-attempt-1`, `stage-root-change-1`, `stage-attempt-2`, `stage-root-change-2`, `stage-root-change-3`, `stage-attempt-3`, `stage-root-change-4`, `stage-root-change-5`, `laravel-framework-constraint-1`, `laravel-php-constraint-1`, `laravel-package-facade_ignition-1`, `laravel-package-guidance-1`, `source-2`, `source-5`, `source-6`, `laravel-skeleton-guidance-1`, `solver-6`, `solver-7`, `solver-8`
  - state chain: predecessor `7497454c7ea95a13b92f6282ebef1b8929df048074d25c263ba071ff86bf4ef6`; input `7497454c7ea95a13b92f6282ebef1b8929df048074d25c263ba071ff86bf4ef6`; output `none`
  - attempt `1` `target_only`: outcome `solver_failure`; duration `1 ms`; selected no; blockers `stage-blocker-2b714eef51f2e8e8d649`
    - analyzer-only root change `laravel/framework`: `^7.0` -> `^8.0`
  - attempt `2` `root_constraint_remediation`: outcome `solver_failure`; duration `1 ms`; selected no; blockers `stage-blocker-4314dc8954f6e4383b54`
    - analyzer-only root change `facade/ignition`: `^1.0` -> `>=2.3.6 <3.0`
    - analyzer-only root change `laravel/framework`: `^7.0` -> `^8.0`
  - attempt `3` `root_and_locked_package_remediation`: outcome `solver_failure`; duration `1 ms`; selected no; blockers `stage-blocker-4314dc8954f6e4383b54`
    - analyzer-only root change `facade/ignition`: `^1.0` -> `>=2.3.6 <3.0`
    - analyzer-only root change `laravel/framework`: `^7.0` -> `^8.0`
  - original-source finding (`high`): Update the root laravel/framework constraint from `^7.0` to a constraint compatible with Laravel 8.
  - original-source finding (`high`): The root PHP constraint `^7.4` excludes target PHP `8.0.0`; update it for the Laravel 8 upgrade.
  - original-source finding (`medium`): facade/ignition 1.16.4 is outside the encoded Laravel 8 review range `>=2.3.6 <3.0`; review its upgrade or replacement.
  - original-source finding (`low`): Compare detected Laravel skeleton-managed integration locations (Kernel middleware, app config providers/aliases, or TrustProxies inheritance) with the Laravel 8 skeleton; these are review locations, not confirmed incompatibilities.
  - blocker references: `stage-blocker-2b714eef51f2e8e8d649`, `stage-blocker-4314dc8954f6e4383b54`
  - source-impact references: `source-impact-2c2e82dc82629e7036e5`, `source-impact-c5a0b2861da5dbee397c`, `source-impact-32c25b8a6283c163863e`
  - risk for `laravel-7-to-8`: `high`
  - effort: 6-30 hours (`low` confidence)
  - action: [laravel-7-to-8] Resolve every active blocker and rerun this complete stage; do not advance.
  - action: [laravel-7-to-8] Inspect the linked Composer evidence.
  - action: [laravel-7-to-8] Run `composer prohibits facade/ignition <constraint> --tree` in an isolated copy.
  - test for `laravel-7-to-8`: Resolve this stage stop condition, then rerun the complete Composer stage laravel-7-to-8. (`required`)
  - stop reason: `blocking_registry_not_cleared`
- Staged source-impact registry:
  - `source-impact-2c2e82dc82629e7036e5` stages `laravel-7-to-8`: `low` impact for `package unknown` (evidence: `source-2`, `source-5`, `source-6`, `laravel-skeleton-guidance-1`)
    - `middleware_reference` `Fruitcake\Cors\HandleCors` in `app/Http/Kernel.php:10` (evidence: `source-2`)
  - `source-impact-32c25b8a6283c163863e` stages `laravel-7-to-8`: `low` impact for `package unknown` (evidence: `source-6`, `source-2`, `source-5`, `laravel-skeleton-guidance-1`)
    - `facade_alias` `Facade\Ignition\Facades\Flare` in `config/app.php:8` (evidence: `source-6`)
  - `source-impact-c5a0b2861da5dbee397c` stages `laravel-7-to-8`: `low` impact for `package unknown` (evidence: `source-5`, `source-2`, `source-6`, `laravel-skeleton-guidance-1`)
    - `service_provider` `Facade\Ignition\IgnitionServiceProvider` in `config/app.php:5` (evidence: `source-5`)
- Blocker registry:
  - `stage-blocker-2b714eef51f2e8e8d649` stage `laravel-7-to-8`: `unknown-composer-failure` `laravel/framework`; lifecycle `resolved` (detected@1 -> resolved@2); blocking package `-`; constraint `-`; path `laravel/framework`
  - `stage-blocker-4314dc8954f6e4383b54` stage `laravel-7-to-8`: `unknown-composer-failure` `facade/ignition`; lifecycle `persists` (detected@2 -> persists@3); blocking package `-`; constraint `-`; path `facade/ignition`

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
- `source-impact-2c2e82dc82629e7036e5` `low` impact for `package unknown` (`unknown` ownership; `framework_rule`; stage references: `direct-final only`): Referenced by active laravel compatibility guidance; package ownership has not been established. (evidence: `source-2`, `source-5`, `source-6`, `laravel-skeleton-guidance-1`)
  - `middleware_reference` `Fruitcake\Cors\HandleCors` in `app/Http/Kernel.php:10` (evidence: `source-2`)
- `source-impact-c5a0b2861da5dbee397c` `low` impact for `package unknown` (`unknown` ownership; `framework_rule`; stage references: `direct-final only`): Referenced by active laravel compatibility guidance; package ownership has not been established. (evidence: `source-5`, `source-2`, `source-6`, `laravel-skeleton-guidance-1`)
  - `service_provider` `Facade\Ignition\IgnitionServiceProvider` in `config/app.php:5` (evidence: `source-5`)
- `source-impact-32c25b8a6283c163863e` `low` impact for `package unknown` (`unknown` ownership; `framework_rule`; stage references: `direct-final only`): Referenced by active laravel compatibility guidance; package ownership has not been established. (evidence: `source-6`, `source-2`, `source-5`, `laravel-skeleton-guidance-1`)
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
1. **laravel-7-to-8** — Stop at laravel-7-to-8; its transition is not proved and must be rerun.; executed stage `laravel-7-to-8` (evidence: `stage-plan-1`, `laravel-stage-target-1`, `laravel-stage-remediation-1`, `stage-attempt-1`, `stage-root-change-1`, `stage-attempt-2`, `stage-root-change-2`, `stage-root-change-3`, `stage-attempt-3`, `stage-root-change-4`, `stage-root-change-5`, `laravel-framework-constraint-1`, `laravel-php-constraint-1`, `laravel-package-facade_ignition-1`, `laravel-package-guidance-1`, `source-2`, `source-5`, `source-6`, `laravel-skeleton-guidance-1`, `solver-6`, `solver-7`, `solver-8`)
   - [laravel-7-to-8] Resolve every active blocker and rerun this complete stage; do not advance.
   - [laravel-7-to-8] Inspect the linked Composer evidence.
   - [laravel-7-to-8] Run `composer prohibits facade/ignition <constraint> --tree` in an isolated copy.

## Risk And Effort
- Risk: `high`
- Risk drivers:
  - Composer resolution is blocked.
  - Framework compatibility findings require review.
  - Weighted actionable source findings require review.
  - Executed stage laravel-7-to-8 retains an active Composer blocker.
- Effort: `6-30` hours (low confidence)
- Effort components:
  - `dependency_resolution`: `3-8` hours
  - `source_changes`: `1-14` hours
  - `tests_and_debugging`: `2-8` hours
- Effort assumptions:
  - Aggregate effort counts each exact package transition, framework finding, and source occurrence once; scenario and repeated-hop counts are excluded.

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
- Compatible Composer execution may inherit global configuration, credentials, proxies, caches, repository access, and other analyzer-host state.

## Evidence
- `solver-1` (`E1`, high confidence): Composer scenario "exact-target" failed. Context: `{"scenario":"exact-target","targets":[{"package":"laravel/framework","constraint":"^8.0"},{"package":"php","constraint":"8.0.0"}],"exit_code":2,"output_excerpt":"Your requirements could not be resolved to an installable set of packages.\n- Root composer.json retains legacy package constraints that exclude the requested Laravel target.","diagnostics":[{"package":"laravel/framework","constraint":"^8.0","command":["composer","prohibits","laravel/framework","^8.0","--tree","--locked","--no-scripts","--no-plugins","--no-interaction"],"exit_code":1,"outcome":"success","stdout_excerpt":"","stderr_excerpt":"No additional fixture diagnostic."},{"package":"php","constraint":"8.0.0","command":["composer","prohibits","php","8.0.0","--tree","--locked","--no-scripts","--no-plugins","--no-interaction"],"exit_code":1,"outcome":"success","stdout_excerpt":"","stderr_excerpt":"No additional fixture diagnostic."}]}`
- `solver-2` (`E1`, high confidence): Composer scenario "target-with-all-dependencies" failed. Context: `{"scenario":"target-with-all-dependencies","targets":[{"package":"laravel/framework","constraint":"^8.0"},{"package":"php","constraint":"8.0.0"}],"exit_code":2,"output_excerpt":"Your requirements could not be resolved to an installable set of packages.\n- Root composer.json retains legacy package constraints that exclude the requested Laravel target.","diagnostics":[{"package":"laravel/framework","constraint":"^8.0","command":["composer","prohibits","laravel/framework","^8.0","--tree","--locked","--no-scripts","--no-plugins","--no-interaction"],"exit_code":1,"outcome":"success","stdout_excerpt":"","stderr_excerpt":"No additional fixture diagnostic."},{"package":"php","constraint":"8.0.0","command":["composer","prohibits","php","8.0.0","--tree","--locked","--no-scripts","--no-plugins","--no-interaction"],"exit_code":1,"outcome":"success","stdout_excerpt":"","stderr_excerpt":"No additional fixture diagnostic."}]}`
- `solver-3` (`E1`, high confidence): Composer scenario "minimal-changes" failed. Context: `{"scenario":"minimal-changes","targets":[{"package":"laravel/framework","constraint":"^8.0"},{"package":"php","constraint":"8.0.0"}],"exit_code":2,"output_excerpt":"Your requirements could not be resolved to an installable set of packages.\n- Root composer.json retains legacy package constraints that exclude the requested Laravel target.","diagnostics":[{"package":"laravel/framework","constraint":"^8.0","command":["composer","prohibits","laravel/framework","^8.0","--tree","--locked","--no-scripts","--no-plugins","--no-interaction"],"exit_code":1,"outcome":"success","stdout_excerpt":"","stderr_excerpt":"No additional fixture diagnostic."},{"package":"php","constraint":"8.0.0","command":["composer","prohibits","php","8.0.0","--tree","--locked","--no-scripts","--no-plugins","--no-interaction"],"exit_code":1,"outcome":"success","stdout_excerpt":"","stderr_excerpt":"No additional fixture diagnostic."}]}`
- `solver-4` (`E1`, high confidence): Composer scenario "target-platform-only" failed. Context: `{"scenario":"target-platform-only","targets":[{"package":"php","constraint":"8.0.0"}],"exit_code":2,"output_excerpt":"Your requirements could not be resolved to an installable set of packages.\n- Root composer.json retains legacy package constraints that exclude the requested Laravel target.","diagnostics":[{"package":"php","constraint":"8.0.0","command":["composer","prohibits","php","8.0.0","--tree","--locked","--no-scripts","--no-plugins","--no-interaction"],"exit_code":1,"outcome":"success","stdout_excerpt":"","stderr_excerpt":"No additional fixture diagnostic."}]}`
- `solver-5` (`E1`, high confidence): Composer scenario "staged-targets" failed. Context: `{"scenario":"staged-targets","targets":[{"package":"laravel/framework","constraint":"^8.0"},{"package":"php","constraint":"7.4.0"}],"exit_code":2,"output_excerpt":"Your requirements could not be resolved to an installable set of packages.\n- Root composer.json retains legacy package constraints that exclude the requested Laravel target.","diagnostics":[{"package":"laravel/framework","constraint":"^8.0","command":["composer","prohibits","laravel/framework","^8.0","--tree","--locked","--no-scripts","--no-plugins","--no-interaction"],"exit_code":1,"outcome":"success","stdout_excerpt":"","stderr_excerpt":"No additional fixture diagnostic."}]}`
- `laravel-stage-target-1` (`E4`, high confidence): Laravel adapter metadata supplies the exact package target for stage 7 to 8. Context: `{"stage_id":"laravel-7-to-8","package":"laravel/framework","constraint":"^8.0","analysis_php":"8.0.0","minimum_php_constraint":"^7.3|^8.0","analysis_php_provenance":"final_target_php_exact_value_checked_against_adapter_constraint","sources":["https://laravel.com/docs/8.x/upgrade"]}`
- `laravel-stage-remediation-1` (`E4`, medium confidence): Laravel adapter metadata permits an analyzer-only root constraint candidate for facade/ignition in stage laravel-7-to-8. Context: `{"stage_id":"laravel-7-to-8","package":"facade/ignition","constraint":">=2.3.6 <3.0","sources":["https://laravel.com/docs/8.x/upgrade"]}`
- `stage-attempt-1` (`E1`, high confidence): Executed Composer attempt 1 for stage laravel-7-to-8. Context: `{"stage_id":"laravel-7-to-8","attempt":1,"strategy":"target_only","scenario":"laravel-7-to-8-attempt-1-target_only","outcome":"solver_failure"}`
- `solver-6` (`E1`, high confidence): Composer scenario "laravel-7-to-8-attempt-1-target_only" failed. Context: `{"scenario":"laravel-7-to-8-attempt-1-target_only","targets":[{"package":"laravel/framework","constraint":"^8.0"},{"package":"php","constraint":"8.0.0"}],"exit_code":2,"output_excerpt":"Your requirements could not be resolved to an installable set of packages.\n- Root composer.json retains legacy package constraints that exclude the requested Laravel target.","diagnostics":[{"package":"laravel/framework","constraint":"^8.0","command":["composer","prohibits","laravel/framework","^8.0","--tree","--locked","--no-scripts","--no-plugins","--no-interaction"],"exit_code":1,"outcome":"success","stdout_excerpt":"","stderr_excerpt":"No additional fixture diagnostic."},{"package":"php","constraint":"8.0.0","command":["composer","prohibits","php","8.0.0","--tree","--locked","--no-scripts","--no-plugins","--no-interaction"],"exit_code":1,"outcome":"success","stdout_excerpt":"","stderr_excerpt":"No additional fixture diagnostic."}]}`
- `stage-root-change-1` (`E2`, high confidence): Recorded an analyzer-only root constraint change for stage laravel-7-to-8. Context: `{"stage_id":"laravel-7-to-8","package":"laravel/framework","from_constraint":"^7.0","to_constraint":"^8.0","supporting_evidence":["laravel-stage-target-1"]}`
- `stage-attempt-2` (`E1`, high confidence): Executed Composer attempt 2 for stage laravel-7-to-8. Context: `{"stage_id":"laravel-7-to-8","attempt":2,"strategy":"root_constraint_remediation","scenario":"laravel-7-to-8-attempt-2-root_constraint_remediation","outcome":"solver_failure"}`
- `solver-7` (`E1`, high confidence): Composer scenario "laravel-7-to-8-attempt-2-root_constraint_remediation" failed. Context: `{"scenario":"laravel-7-to-8-attempt-2-root_constraint_remediation","targets":[{"package":"facade/ignition","constraint":">=2.3.6 <3.0"},{"package":"laravel/framework","constraint":"^8.0"},{"package":"php","constraint":"8.0.0"}],"exit_code":2,"output_excerpt":"Your requirements could not be resolved to an installable set of packages.\n- Root composer.json retains legacy package constraints that exclude the requested Laravel target.","diagnostics":[{"package":"facade/ignition","constraint":">=2.3.6 <3.0","command":["composer","prohibits","facade/ignition",">=2.3.6 <3.0","--tree","--locked","--no-scripts","--no-plugins","--no-interaction"],"exit_code":1,"outcome":"success","stdout_excerpt":"","stderr_excerpt":"No additional fixture diagnostic."},{"package":"laravel/framework","constraint":"^8.0","command":["composer","prohibits","laravel/framework","^8.0","--tree","--locked","--no-scripts","--no-plugins","--no-interaction"],"exit_code":1,"outcome":"success","stdout_excerpt":"","stderr_excerpt":"No additional fixture diagnostic."},{"package":"php","constraint":"8.0.0","command":["composer","prohibits","php","8.0.0","--tree","--locked","--no-scripts","--no-plugins","--no-interaction"],"exit_code":1,"outcome":"success","stdout_excerpt":"","stderr_excerpt":"No additional fixture diagnostic."}]}`
- `stage-root-change-2` (`E2`, high confidence): Recorded an analyzer-only root constraint change for stage laravel-7-to-8. Context: `{"stage_id":"laravel-7-to-8","package":"facade/ignition","from_constraint":"^1.0","to_constraint":">=2.3.6 <3.0","supporting_evidence":["laravel-stage-remediation-1"]}`
- `stage-root-change-3` (`E2`, high confidence): Recorded an analyzer-only root constraint change for stage laravel-7-to-8. Context: `{"stage_id":"laravel-7-to-8","package":"laravel/framework","from_constraint":"^7.0","to_constraint":"^8.0","supporting_evidence":["laravel-stage-target-1"]}`
- `stage-attempt-3` (`E1`, high confidence): Executed Composer attempt 3 for stage laravel-7-to-8. Context: `{"stage_id":"laravel-7-to-8","attempt":3,"strategy":"root_and_locked_package_remediation","scenario":"laravel-7-to-8-attempt-3-root_and_locked_package_remediation","outcome":"solver_failure"}`
- `solver-8` (`E1`, high confidence): Composer scenario "laravel-7-to-8-attempt-3-root_and_locked_package_remediation" failed. Context: `{"scenario":"laravel-7-to-8-attempt-3-root_and_locked_package_remediation","targets":[{"package":"facade/ignition","constraint":">=2.3.6 <3.0"},{"package":"laravel/framework","constraint":"^8.0"},{"package":"php","constraint":"8.0.0"}],"exit_code":2,"output_excerpt":"Your requirements could not be resolved to an installable set of packages.\n- Root composer.json retains legacy package constraints that exclude the requested Laravel target.","diagnostics":[{"package":"facade/ignition","constraint":">=2.3.6 <3.0","command":["composer","prohibits","facade/ignition",">=2.3.6 <3.0","--tree","--locked","--no-scripts","--no-plugins","--no-interaction"],"exit_code":1,"outcome":"success","stdout_excerpt":"","stderr_excerpt":"No additional fixture diagnostic."},{"package":"laravel/framework","constraint":"^8.0","command":["composer","prohibits","laravel/framework","^8.0","--tree","--locked","--no-scripts","--no-plugins","--no-interaction"],"exit_code":1,"outcome":"success","stdout_excerpt":"","stderr_excerpt":"No additional fixture diagnostic."},{"package":"php","constraint":"8.0.0","command":["composer","prohibits","php","8.0.0","--tree","--locked","--no-scripts","--no-plugins","--no-interaction"],"exit_code":1,"outcome":"success","stdout_excerpt":"","stderr_excerpt":"No additional fixture diagnostic."}]}`
- `stage-root-change-4` (`E2`, high confidence): Recorded an analyzer-only root constraint change for stage laravel-7-to-8. Context: `{"stage_id":"laravel-7-to-8","package":"facade/ignition","from_constraint":"^1.0","to_constraint":">=2.3.6 <3.0","supporting_evidence":["laravel-stage-remediation-1"]}`
- `stage-root-change-5` (`E2`, high confidence): Recorded an analyzer-only root constraint change for stage laravel-7-to-8. Context: `{"stage_id":"laravel-7-to-8","package":"laravel/framework","from_constraint":"^7.0","to_constraint":"^8.0","supporting_evidence":["laravel-stage-target-1"]}`
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
- `stage-plan-1` (`E5`, low confidence): Generated recommendations from the executed outcome of stage laravel-7-to-8. Context: `{"stage_id":"laravel-7-to-8","execution_state":"evaluated","resolution_status":"blocked","transition_recommended":false}`
