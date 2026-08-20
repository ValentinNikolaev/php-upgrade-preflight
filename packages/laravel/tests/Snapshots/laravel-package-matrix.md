# PHP Upgrade Preflight Report

Resolution: **blocked** | Staged: **blocked** | Schema: `0.8` | Tool: `php-upgrade-preflight 0.3.3`

## Analysis Request
- Project: `<PROJECT_PATH>`
- Current PHP: `7.4`
- Target PHP: `8.1.0`
- Source paths: `default project paths`
- Framework integrations: `automatic detection`
- Target platform profile: `not supplied`
- Composer execution mode: `compatible`
- Requested format: `markdown`
- Output destination: `stdout`
- Targets:
  - `laravel/framework`: `^9.0`
  - `php`: `8.1.0`

## Platform Provenance
- Analyzer PHP: `<ANALYZER_PHP_VERSION>` (provenance: `runtime`)
- Current project PHP: `7.4` (provenance: `request`)
- Target PHP: `8.1.0` (provenance: `request`)
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
- Locked packages: `14`
- Root requirements:
  - `php`: `^7.4`
  - `laravel/framework`: `^7.0`
  - `laravel/passport`: `^8.0`
  - `laravel/sanctum`: `^1.0`
  - `laravel/horizon`: `^4.0`
  - `laravel/telescope`: `^3.0`
  - `facade/ignition`: `^2.0`
  - `fideloper/proxy`: `^4.0`
  - `fruitcake/laravel-cors`: `^2.0`
  - `symfony/http-foundation`: `^4.4`
  - `phpunit/phpunit`: `^8.0`
  - `mockery/mockery`: `^1.3`
  - `nunomaduro/collision`: `^4.0`
  - `laravel/ui`: `^2.0`
  - `orchestra/testbench`: `^5.0`

## Composer Scenarios
- `baseline-validation`: succeeded (outcome `success`, Composer `unknown`, duration `1 ms`, exit `0`, failure type `none`)
  - command argv: `["composer","validate","--check-lock","--no-check-publish","--no-scripts","--no-plugins","--no-interaction"]`
  - temporary workspace: `not preserved`
  - stdout excerpt:
    ```text
    Fixture baseline is valid.
    ```
  - stderr excerpt: *(empty)*
  - candidate lock: SHA-256 `1ad00c20a738947e3309a0e6917a76cea816fbbd9b2e790222fbdc8a343403d9`, content hash `laravel-package-matrix`, packages `14`
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
  - diagnostic for `laravel/framework ^9.0` (outcome `success`, exit `1`), command argv: `["composer","prohibits","laravel/framework","^9.0","--tree","--locked","--no-scripts","--no-plugins","--no-interaction"]`
    - stdout excerpt: *(empty)*
    - stderr excerpt:
      ```text
      No additional fixture diagnostic.
      ```
  - diagnostic for `php 8.1.0` (outcome `success`, exit `1`), command argv: `["composer","prohibits","php","8.1.0","--tree","--locked","--no-scripts","--no-plugins","--no-interaction"]`
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
  - diagnostic for `laravel/framework ^9.0` (outcome `success`, exit `1`), command argv: `["composer","prohibits","laravel/framework","^9.0","--tree","--locked","--no-scripts","--no-plugins","--no-interaction"]`
    - stdout excerpt: *(empty)*
    - stderr excerpt:
      ```text
      No additional fixture diagnostic.
      ```
  - diagnostic for `php 8.1.0` (outcome `success`, exit `1`), command argv: `["composer","prohibits","php","8.1.0","--tree","--locked","--no-scripts","--no-plugins","--no-interaction"]`
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
  - diagnostic for `laravel/framework ^9.0` (outcome `success`, exit `1`), command argv: `["composer","prohibits","laravel/framework","^9.0","--tree","--locked","--no-scripts","--no-plugins","--no-interaction"]`
    - stdout excerpt: *(empty)*
    - stderr excerpt:
      ```text
      No additional fixture diagnostic.
      ```
  - diagnostic for `php 8.1.0` (outcome `success`, exit `1`), command argv: `["composer","prohibits","php","8.1.0","--tree","--locked","--no-scripts","--no-plugins","--no-interaction"]`
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
  - diagnostic for `php 8.1.0` (outcome `success`, exit `1`), command argv: `["composer","prohibits","php","8.1.0","--tree","--locked","--no-scripts","--no-plugins","--no-interaction"]`
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
  - diagnostic for `laravel/framework ^9.0` (outcome `success`, exit `1`), command argv: `["composer","prohibits","laravel/framework","^9.0","--tree","--locked","--no-scripts","--no-plugins","--no-interaction"]`
    - stdout excerpt: *(empty)*
    - stderr excerpt:
      ```text
      No additional fixture diagnostic.
      ```

## Staged Composer Resolution
- Execution: `evaluated`; status: `blocked`; provider: `laravel`; stop reason: `blocking_registry_not_cleared`
- **laravel-7-to-8** (`7` -> `8`): execution `evaluated`; resolution `blocked`; selected attempt `none`
  - analysis PHP: `8.1.0`; source snapshot: `original_project`
  - This stage assessment inspects the original project source snapshot; it does not assume edits from an earlier stage were applied.
  - effective platform: `c2b227c251d4ec9ccf885e95404feaceedd627ff0c7104bc213f254935e62537`; completeness `partial`; profile `none`
  - Composer policy: `0c155d2582c9452186059dbe78d0da2d730f798c6c1d69f356c43ca832fcf1b9`; mode `compatible`; stage duration `1 ms`
  - stage evidence: `laravel-stage-target-1`, `laravel-stage-remediation-3`, `laravel-stage-remediation-1`, `laravel-stage-remediation-2`, `laravel-stage-remediation-4`, `laravel-stage-remediation-8`, `laravel-stage-remediation-6`, `laravel-stage-remediation-7`, `laravel-stage-remediation-9`, `laravel-stage-remediation-5`, `stage-attempt-1`, `stage-root-change-1`, `stage-attempt-2`, `stage-root-change-2`, `stage-root-change-3`, `stage-attempt-3`, `stage-root-change-4`, `stage-root-change-5`, `stage-root-change-6`, `stage-root-change-7`, `stage-root-change-8`, `stage-root-change-9`, `stage-root-change-10`, `stage-root-change-11`, `stage-root-change-12`, `stage-root-change-13`, `solver-6`, `solver-7`, `solver-8`
  - state chain: predecessor `cca72240f2c309d91ed9f8b1904b4a77a661a0be60805c72374f74151ff061af`; input `cca72240f2c309d91ed9f8b1904b4a77a661a0be60805c72374f74151ff061af`; output `none`
  - attempt `1` `target_only`: outcome `solver_failure`; duration `1 ms`; selected no; blockers `stage-blocker-2b714eef51f2e8e8d649`
    - analyzer-only root change `laravel/framework`: `^7.0` -> `^8.0`
  - attempt `2` `root_constraint_remediation`: outcome `solver_failure`; duration `1 ms`; selected no; blockers `stage-blocker-2b714eef51f2e8e8d649`
    - analyzer-only root change `laravel/framework`: `^7.0` -> `^8.0`
    - analyzer-only root change `laravel/horizon`: `^4.0` -> `^5.0`
  - attempt `3` `root_and_locked_package_remediation`: outcome `solver_failure`; duration `1 ms`; selected no; blockers `stage-blocker-2b714eef51f2e8e8d649`
    - analyzer-only root change `laravel/framework`: `^7.0` -> `^8.0`
    - analyzer-only root change `laravel/horizon`: `^4.0` -> `^5.0`
    - analyzer-only root change `laravel/passport`: `^8.0` -> `^10.0`
    - analyzer-only root change `laravel/sanctum`: `^1.0` -> `^2.0`
    - analyzer-only root change `laravel/telescope`: `^3.0` -> `^4.0`
    - analyzer-only root change `laravel/ui`: `^2.0` -> `^3.0`
    - analyzer-only root change `mockery/mockery`: `^1.3` -> `^1.4`
    - analyzer-only root change `nunomaduro/collision`: `^4.0` -> `^5.0`
    - analyzer-only root change `orchestra/testbench`: `^5.0` -> `^6.0`
    - analyzer-only root change `phpunit/phpunit`: `^8.0` -> `^9.0`
  - blocker references: `stage-blocker-2b714eef51f2e8e8d649`
  - source-impact references: `none`
  - risk for `laravel-7-to-8`: `high`
  - effort: 5-16 hours (`low` confidence)
  - action: [laravel-7-to-8] Resolve every active blocker and rerun this complete stage; do not advance.
  - action: [laravel-7-to-8] Inspect the linked Composer evidence.
  - action: [laravel-7-to-8] Run `composer prohibits laravel/framework <constraint> --tree` in an isolated copy.
  - test for `laravel-7-to-8`: Resolve this stage stop condition, then rerun the complete Composer stage laravel-7-to-8. (`required`)
  - stop reason: `blocking_registry_not_cleared`
- **laravel-8-to-9** (`8` -> `9`): execution `skipped`; resolution `not evaluated`; selected attempt `none`
  - analysis PHP: `8.1.0`; source snapshot: `original_project`
  - This stage assessment inspects the original project source snapshot; it does not assume edits from an earlier stage were applied.
  - effective platform: `c2b227c251d4ec9ccf885e95404feaceedd627ff0c7104bc213f254935e62537`; completeness `partial`; profile `none`
  - Composer policy: `0c155d2582c9452186059dbe78d0da2d730f798c6c1d69f356c43ca832fcf1b9`; mode `compatible`; stage duration `1 ms`
  - stage evidence: `laravel-stage-target-2`, `stage-skipped-1`, `laravel-framework-constraint-1`, `laravel-php-constraint-1`, `laravel-package-laravel_passport-1`, `laravel-package-guidance-1`, `laravel-package-laravel_sanctum-1`, `laravel-package-guidance-2`, `laravel-package-laravel_horizon-1`, `laravel-package-guidance-3`, `laravel-package-laravel_telescope-1`, `laravel-package-guidance-4`, `laravel-package-phpunit_phpunit-1`, `laravel-package-guidance-5`, `laravel-package-mockery_mockery-1`, `laravel-package-guidance-6`, `laravel-symfony-constraints-1`, `laravel-symfony-guidance-1`, `laravel-advisory-facade_ignition-1`, `laravel-package-advisory-1`, `laravel-advisory-fideloper_proxy-1`, `laravel-package-advisory-2`, `laravel-advisory-fruitcake_laravel_cors-1`, `laravel-package-advisory-3`, `laravel-package-nunomaduro_collision-1`, `laravel-package-guidance-7`, `laravel-package-laravel_ui-1`, `laravel-package-guidance-8`, `laravel-package-orchestra_testbench-1`, `laravel-package-guidance-9`
  - state chain: predecessor `none`; input `none`; output `none`
  - original-source finding (`high`): Update the root laravel/framework constraint from `^7.0` to a constraint compatible with Laravel 9.
  - original-source finding (`high`): The root PHP constraint `^7.4` excludes target PHP `8.1.0`; update it for the Laravel 9 upgrade.
  - original-source finding (`high`): laravel/passport v8.5.0 is outside the encoded Laravel 9 review range `^10.0|^11.0`; review its upgrade or replacement.
  - original-source finding (`medium`): laravel/sanctum v1.3.3 is outside the encoded Laravel 9 review range `^2.0|^3.0`; review its upgrade or replacement.
  - original-source finding (`high`): laravel/horizon v4.3.5 is outside the encoded Laravel 9 review range `^5.0`; review its upgrade or replacement.
  - original-source finding (`medium`): laravel/telescope v3.7.0 is outside the encoded Laravel 9 review range `^4.0`; review its upgrade or replacement.
  - original-source finding (`medium`): phpunit/phpunit 8.5.21 is outside the encoded Laravel 9 review range `^9.5.10`; review its upgrade or replacement.
  - original-source finding (`low`): mockery/mockery 1.3.6 is outside the encoded Laravel 9 review range `^1.4`; review its upgrade or replacement.
  - original-source finding (`high`): Review direct Symfony component constraints for Laravel 9 (`^6.0` expected): symfony/http-foundation.
  - original-source finding (`high`): Replace facade/ignition with spatie/laravel-ignition for the Laravel 9 target.
  - original-source finding (`medium`): Remove fideloper/proxy and review the trusted proxy middleware for the Laravel 9 target.
  - original-source finding (`medium`): Review removal of fruitcake/laravel-cors because Laravel 9 integrates CORS middleware through the framework.
  - original-source finding (`medium`): nunomaduro/collision v4.3.0 is outside the encoded Laravel 9 review range `^6.1`; review its upgrade or replacement.
  - original-source finding (`low`): laravel/ui v2.5.0 is outside the encoded Laravel 9 review range `^4.0`; review its upgrade or replacement.
  - original-source finding (`medium`): orchestra/testbench v5.4.0 is outside the encoded Laravel 9 review range `^7.0`; review its upgrade or replacement.
  - blocker references: `none`
  - source-impact references: `none`
  - risk for `laravel-8-to-9`: `high`
  - effort: 0-0 hours (`low` confidence)
  - action: [laravel-8-to-9] Do not advance: this stage was skipped and has no Composer feasibility evidence.
  - test for `laravel-8-to-9`: Resolve the preceding stop condition, then execute stage laravel-8-to-9 before selecting migration tests. (`required`)
  - stop reason: `previous_stage_blocked`
- Staged source-impact registry:
  - None recorded.
- Blocker registry:
  - `stage-blocker-2b714eef51f2e8e8d649` stage `laravel-7-to-8`: `unknown-composer-failure` `laravel/framework`; lifecycle `persists` (detected@1 -> persists@2 -> persists@3); blocking package `-`; constraint `-`; path `laravel/framework`

## Package Changes
- No lockfile changes detected.

## Framework Transition Guidance
- `laravel`: `supported` (`7` -> `9`; evidence: `laravel-transition-1`, `laravel-transition-2`)
  - hop `7` -> `8`: `supported`; rule pack `laravel-7-to-8` (evidence: `laravel-transition-1`)
  - hop `8` -> `9`: `supported`; rule pack `laravel-8-to-9` (evidence: `laravel-transition-2`)

## Root Constraint Changes
- `laravel/framework`: updated `^7.0` -> `^9.0`. The declared root constraint differs from the requested target. (evidence: `root-constraint-1`)

## Blockers
- `unknown-composer-failure` `laravel/framework`: Composer failed, but the blocker type could not be classified. (low confidence; evidence: `solver-1`, `solver-2`, `solver-3`, `solver-5`)
  - requested `^9.0`; blocker `-`; locked `-`; conflict `-`
  - dependency path: `laravel/framework`
  - option: Inspect the linked Composer evidence.
  - option: Run `composer prohibits laravel/framework <constraint> --tree` in an isolated copy.
- `unknown-composer-failure` `php`: Composer failed, but the blocker type could not be classified. (low confidence; evidence: `solver-4`)
  - requested `8.1.0`; blocker `-`; locked `-`; conflict `-`
  - dependency path: `php`
  - option: Inspect the linked Composer evidence.
  - option: Run `composer prohibits php <constraint> --tree` in an isolated copy.

## Source Inventory
- None detected.

## Actionable Source Impact
- None detected.

## Framework Findings
- `laravel` `high`: Update the root laravel/framework constraint from `^7.0` to a constraint compatible with Laravel 9. (evidence: `laravel-framework-constraint-1`)
  - applies to hops: `8 -> 9`
- `laravel` `high`: The root PHP constraint `^7.4` excludes target PHP `8.1.0`; update it for the Laravel 9 upgrade. (evidence: `laravel-php-constraint-1`)
  - applies to hops: `8 -> 9`
- `laravel` `high`: laravel/passport v8.5.0 is outside the encoded Laravel 9 review range `^10.0|^11.0`; review its upgrade or replacement. (evidence: `laravel-package-laravel_passport-1`, `laravel-package-guidance-1`)
  - applies to hops: `8 -> 9`
- `laravel` `medium`: laravel/sanctum v1.3.3 is outside the encoded Laravel 9 review range `^2.0|^3.0`; review its upgrade or replacement. (evidence: `laravel-package-laravel_sanctum-1`, `laravel-package-guidance-2`)
  - applies to hops: `8 -> 9`
- `laravel` `high`: laravel/horizon v4.3.5 is outside the encoded Laravel 9 review range `^5.0`; review its upgrade or replacement. (evidence: `laravel-package-laravel_horizon-1`, `laravel-package-guidance-3`)
  - applies to hops: `8 -> 9`
- `laravel` `medium`: laravel/telescope v3.7.0 is outside the encoded Laravel 9 review range `^4.0`; review its upgrade or replacement. (evidence: `laravel-package-laravel_telescope-1`, `laravel-package-guidance-4`)
  - applies to hops: `8 -> 9`
- `laravel` `medium`: phpunit/phpunit 8.5.21 is outside the encoded Laravel 9 review range `^9.5.10`; review its upgrade or replacement. (evidence: `laravel-package-phpunit_phpunit-1`, `laravel-package-guidance-5`)
  - applies to hops: `8 -> 9`
- `laravel` `low`: mockery/mockery 1.3.6 is outside the encoded Laravel 9 review range `^1.4`; review its upgrade or replacement. (evidence: `laravel-package-mockery_mockery-1`, `laravel-package-guidance-6`)
  - applies to hops: `8 -> 9`
- `laravel` `high`: Review direct Symfony component constraints for Laravel 9 (`^6.0` expected): symfony/http-foundation. (evidence: `laravel-symfony-constraints-1`, `laravel-symfony-guidance-1`)
  - applies to hops: `8 -> 9`
- `laravel` `high`: Replace facade/ignition with spatie/laravel-ignition for the Laravel 9 target. (evidence: `laravel-advisory-facade_ignition-1`, `laravel-package-advisory-1`)
  - applies to hops: `8 -> 9`
- `laravel` `medium`: Remove fideloper/proxy and review the trusted proxy middleware for the Laravel 9 target. (evidence: `laravel-advisory-fideloper_proxy-1`, `laravel-package-advisory-2`)
  - applies to hops: `8 -> 9`
- `laravel` `medium`: Review removal of fruitcake/laravel-cors because Laravel 9 integrates CORS middleware through the framework. (evidence: `laravel-advisory-fruitcake_laravel_cors-1`, `laravel-package-advisory-3`)
  - applies to hops: `8 -> 9`
- `laravel` `medium`: nunomaduro/collision v4.3.0 is outside the encoded Laravel 9 review range `^6.1`; review its upgrade or replacement. (evidence: `laravel-package-nunomaduro_collision-1`, `laravel-package-guidance-7`)
  - applies to hops: `8 -> 9`
- `laravel` `low`: laravel/ui v2.5.0 is outside the encoded Laravel 9 review range `^4.0`; review its upgrade or replacement. (evidence: `laravel-package-laravel_ui-1`, `laravel-package-guidance-8`)
  - applies to hops: `8 -> 9`
- `laravel` `medium`: orchestra/testbench v5.4.0 is outside the encoded Laravel 9 review range `^7.0`; review its upgrade or replacement. (evidence: `laravel-package-orchestra_testbench-1`, `laravel-package-guidance-9`)
  - applies to hops: `8 -> 9`

## Staged Plan
1. **laravel-7-to-8** — Stop at laravel-7-to-8; its transition is not proved and must be rerun.; executed stage `laravel-7-to-8` (evidence: `stage-plan-1`, `laravel-stage-target-1`, `laravel-stage-remediation-3`, `laravel-stage-remediation-1`, `laravel-stage-remediation-2`, `laravel-stage-remediation-4`, `laravel-stage-remediation-8`, `laravel-stage-remediation-6`, `laravel-stage-remediation-7`, `laravel-stage-remediation-9`, `laravel-stage-remediation-5`, `stage-attempt-1`, `stage-root-change-1`, `stage-attempt-2`, `stage-root-change-2`, `stage-root-change-3`, `stage-attempt-3`, `stage-root-change-4`, `stage-root-change-5`, `stage-root-change-6`, `stage-root-change-7`, `stage-root-change-8`, `stage-root-change-9`, `stage-root-change-10`, `stage-root-change-11`, `stage-root-change-12`, `stage-root-change-13`, `solver-6`, `solver-7`, `solver-8`)
   - [laravel-7-to-8] Resolve every active blocker and rerun this complete stage; do not advance.
   - [laravel-7-to-8] Inspect the linked Composer evidence.
   - [laravel-7-to-8] Run `composer prohibits laravel/framework <constraint> --tree` in an isolated copy.

## Risk And Effort
- Risk: `high`
- Risk drivers:
  - Composer resolution is blocked.
  - Framework compatibility findings require review.
  - Executed stage laravel-7-to-8 retains an active Composer blocker.
- Effort: `6-32` hours (low confidence)
- Effort components:
  - `dependency_resolution`: `3-8` hours
  - `source_changes`: `1-16` hours
  - `tests_and_debugging`: `2-8` hours
- Effort assumptions:
  - Aggregate effort counts each exact package transition, framework finding, and source occurrence once; scenario and repeated-hop counts are excluded.

## Test Guidance
- **composer-validation** (`required`): Validate the edited Composer manifest before dependency installation. Command: `composer validate --strict`.
- **project-test-suite** (`required`): Identify and run the project test suite; no Composer test script was detected. Command: project-specific command required.
- **platform-requirements** (`required`): Confirm the installed dependencies satisfy PHP 8.1.0 and the deployment extensions. Command: `composer check-platform-reqs`.
- **focused-regressions** (`recommended`): Add or run focused regression coverage for the reported source and framework findings. Command: project-specific command required.

## Uncertainties
- No PHP source files were scanned.
- Dependency resolution does not prove application runtime compatibility; the project test suite must run on the target runtime.
- Root PHP constraint "^7.4" does not include target platform PHP 8.1.0; select an appropriate Composer constraint instead of using the exact simulated platform version.
- No Composer "test" script was found, so the project's canonical test command is unknown.
- Composer extension checks used the analyzer runtime because no complete explicit extension platform was supplied.
- Compatible Composer execution may inherit global configuration, credentials, proxies, caches, repository access, and other analyzer-host state.

## Evidence
- `solver-1` (`E1`, high confidence): Composer scenario "exact-target" failed. Context: `{"scenario":"exact-target","targets":[{"package":"laravel/framework","constraint":"^9.0"},{"package":"php","constraint":"8.1.0"}],"exit_code":2,"output_excerpt":"Your requirements could not be resolved to an installable set of packages.\n- Root composer.json retains legacy package constraints that exclude the requested Laravel target.","diagnostics":[{"package":"laravel/framework","constraint":"^9.0","command":["composer","prohibits","laravel/framework","^9.0","--tree","--locked","--no-scripts","--no-plugins","--no-interaction"],"exit_code":1,"outcome":"success","stdout_excerpt":"","stderr_excerpt":"No additional fixture diagnostic."},{"package":"php","constraint":"8.1.0","command":["composer","prohibits","php","8.1.0","--tree","--locked","--no-scripts","--no-plugins","--no-interaction"],"exit_code":1,"outcome":"success","stdout_excerpt":"","stderr_excerpt":"No additional fixture diagnostic."}]}`
- `solver-2` (`E1`, high confidence): Composer scenario "target-with-all-dependencies" failed. Context: `{"scenario":"target-with-all-dependencies","targets":[{"package":"laravel/framework","constraint":"^9.0"},{"package":"php","constraint":"8.1.0"}],"exit_code":2,"output_excerpt":"Your requirements could not be resolved to an installable set of packages.\n- Root composer.json retains legacy package constraints that exclude the requested Laravel target.","diagnostics":[{"package":"laravel/framework","constraint":"^9.0","command":["composer","prohibits","laravel/framework","^9.0","--tree","--locked","--no-scripts","--no-plugins","--no-interaction"],"exit_code":1,"outcome":"success","stdout_excerpt":"","stderr_excerpt":"No additional fixture diagnostic."},{"package":"php","constraint":"8.1.0","command":["composer","prohibits","php","8.1.0","--tree","--locked","--no-scripts","--no-plugins","--no-interaction"],"exit_code":1,"outcome":"success","stdout_excerpt":"","stderr_excerpt":"No additional fixture diagnostic."}]}`
- `solver-3` (`E1`, high confidence): Composer scenario "minimal-changes" failed. Context: `{"scenario":"minimal-changes","targets":[{"package":"laravel/framework","constraint":"^9.0"},{"package":"php","constraint":"8.1.0"}],"exit_code":2,"output_excerpt":"Your requirements could not be resolved to an installable set of packages.\n- Root composer.json retains legacy package constraints that exclude the requested Laravel target.","diagnostics":[{"package":"laravel/framework","constraint":"^9.0","command":["composer","prohibits","laravel/framework","^9.0","--tree","--locked","--no-scripts","--no-plugins","--no-interaction"],"exit_code":1,"outcome":"success","stdout_excerpt":"","stderr_excerpt":"No additional fixture diagnostic."},{"package":"php","constraint":"8.1.0","command":["composer","prohibits","php","8.1.0","--tree","--locked","--no-scripts","--no-plugins","--no-interaction"],"exit_code":1,"outcome":"success","stdout_excerpt":"","stderr_excerpt":"No additional fixture diagnostic."}]}`
- `solver-4` (`E1`, high confidence): Composer scenario "target-platform-only" failed. Context: `{"scenario":"target-platform-only","targets":[{"package":"php","constraint":"8.1.0"}],"exit_code":2,"output_excerpt":"Your requirements could not be resolved to an installable set of packages.\n- Root composer.json retains legacy package constraints that exclude the requested Laravel target.","diagnostics":[{"package":"php","constraint":"8.1.0","command":["composer","prohibits","php","8.1.0","--tree","--locked","--no-scripts","--no-plugins","--no-interaction"],"exit_code":1,"outcome":"success","stdout_excerpt":"","stderr_excerpt":"No additional fixture diagnostic."}]}`
- `solver-5` (`E1`, high confidence): Composer scenario "staged-targets" failed. Context: `{"scenario":"staged-targets","targets":[{"package":"laravel/framework","constraint":"^9.0"},{"package":"php","constraint":"7.4.0"}],"exit_code":2,"output_excerpt":"Your requirements could not be resolved to an installable set of packages.\n- Root composer.json retains legacy package constraints that exclude the requested Laravel target.","diagnostics":[{"package":"laravel/framework","constraint":"^9.0","command":["composer","prohibits","laravel/framework","^9.0","--tree","--locked","--no-scripts","--no-plugins","--no-interaction"],"exit_code":1,"outcome":"success","stdout_excerpt":"","stderr_excerpt":"No additional fixture diagnostic."}]}`
- `laravel-stage-target-1` (`E4`, high confidence): Laravel adapter metadata supplies the exact package target for stage 7 to 8. Context: `{"stage_id":"laravel-7-to-8","package":"laravel/framework","constraint":"^8.0","analysis_php":"8.1.0","minimum_php_constraint":"^7.3|^8.0","analysis_php_provenance":"final_target_php_exact_value_checked_against_adapter_constraint","sources":["https://laravel.com/docs/8.x/upgrade"]}`
- `laravel-stage-remediation-1` (`E4`, medium confidence): Laravel adapter metadata permits an analyzer-only root constraint candidate for laravel/passport in stage laravel-7-to-8. Context: `{"stage_id":"laravel-7-to-8","package":"laravel/passport","constraint":"^10.0","sources":["https://laravel.com/docs/8.x/upgrade"]}`
- `laravel-stage-remediation-2` (`E4`, medium confidence): Laravel adapter metadata permits an analyzer-only root constraint candidate for laravel/sanctum in stage laravel-7-to-8. Context: `{"stage_id":"laravel-7-to-8","package":"laravel/sanctum","constraint":"^2.0","sources":["https://github.com/laravel/sanctum/blob/2.x/composer.json"]}`
- `laravel-stage-remediation-3` (`E4`, medium confidence): Laravel adapter metadata permits an analyzer-only root constraint candidate for laravel/horizon in stage laravel-7-to-8. Context: `{"stage_id":"laravel-7-to-8","package":"laravel/horizon","constraint":"^5.0","sources":["https://laravel.com/docs/8.x/upgrade"]}`
- `laravel-stage-remediation-4` (`E4`, medium confidence): Laravel adapter metadata permits an analyzer-only root constraint candidate for laravel/telescope in stage laravel-7-to-8. Context: `{"stage_id":"laravel-7-to-8","package":"laravel/telescope","constraint":"^4.0","sources":["https://github.com/laravel/telescope/blob/4.x/composer.json"]}`
- `laravel-stage-remediation-5` (`E4`, medium confidence): Laravel adapter metadata permits an analyzer-only root constraint candidate for phpunit/phpunit in stage laravel-7-to-8. Context: `{"stage_id":"laravel-7-to-8","package":"phpunit/phpunit","constraint":"^9.0","sources":["https://laravel.com/docs/8.x/upgrade","https://github.com/laravel/laravel/blob/8.x/composer.json"]}`
- `laravel-stage-remediation-6` (`E4`, medium confidence): Laravel adapter metadata permits an analyzer-only root constraint candidate for mockery/mockery in stage laravel-7-to-8. Context: `{"stage_id":"laravel-7-to-8","package":"mockery/mockery","constraint":"^1.4","sources":["https://github.com/laravel/laravel/blob/8.x/composer.json"]}`
- `laravel-stage-remediation-7` (`E4`, medium confidence): Laravel adapter metadata permits an analyzer-only root constraint candidate for nunomaduro/collision in stage laravel-7-to-8. Context: `{"stage_id":"laravel-7-to-8","package":"nunomaduro/collision","constraint":"^5.0","sources":["https://laravel.com/docs/8.x/upgrade"]}`
- `laravel-stage-remediation-8` (`E4`, medium confidence): Laravel adapter metadata permits an analyzer-only root constraint candidate for laravel/ui in stage laravel-7-to-8. Context: `{"stage_id":"laravel-7-to-8","package":"laravel/ui","constraint":"^3.0","sources":["https://github.com/laravel/ui/blob/3.x/composer.json"]}`
- `laravel-stage-remediation-9` (`E4`, medium confidence): Laravel adapter metadata permits an analyzer-only root constraint candidate for orchestra/testbench in stage laravel-7-to-8. Context: `{"stage_id":"laravel-7-to-8","package":"orchestra/testbench","constraint":"^6.0","sources":["https://github.com/orchestral/testbench/blob/6.x/composer.json"]}`
- `laravel-stage-target-2` (`E4`, high confidence): Laravel adapter metadata supplies the exact package target for stage 8 to 9. Context: `{"stage_id":"laravel-8-to-9","package":"laravel/framework","constraint":"^9.0","analysis_php":"8.1.0","minimum_php_constraint":"^8.0.2","analysis_php_provenance":"final_target_php_exact_value_checked_against_adapter_constraint","sources":["https://laravel.com/docs/9.x/upgrade"]}`
- `stage-attempt-1` (`E1`, high confidence): Executed Composer attempt 1 for stage laravel-7-to-8. Context: `{"stage_id":"laravel-7-to-8","attempt":1,"strategy":"target_only","scenario":"laravel-7-to-8-attempt-1-target_only","outcome":"solver_failure"}`
- `solver-6` (`E1`, high confidence): Composer scenario "laravel-7-to-8-attempt-1-target_only" failed. Context: `{"scenario":"laravel-7-to-8-attempt-1-target_only","targets":[{"package":"laravel/framework","constraint":"^8.0"},{"package":"php","constraint":"8.1.0"}],"exit_code":2,"output_excerpt":"Your requirements could not be resolved to an installable set of packages.\n- Root composer.json retains legacy package constraints that exclude the requested Laravel target.","diagnostics":[{"package":"laravel/framework","constraint":"^8.0","command":["composer","prohibits","laravel/framework","^8.0","--tree","--locked","--no-scripts","--no-plugins","--no-interaction"],"exit_code":1,"outcome":"success","stdout_excerpt":"","stderr_excerpt":"No additional fixture diagnostic."},{"package":"php","constraint":"8.1.0","command":["composer","prohibits","php","8.1.0","--tree","--locked","--no-scripts","--no-plugins","--no-interaction"],"exit_code":1,"outcome":"success","stdout_excerpt":"","stderr_excerpt":"No additional fixture diagnostic."}]}`
- `stage-root-change-1` (`E2`, high confidence): Recorded an analyzer-only root constraint change for stage laravel-7-to-8. Context: `{"stage_id":"laravel-7-to-8","package":"laravel/framework","from_constraint":"^7.0","to_constraint":"^8.0","supporting_evidence":["laravel-stage-target-1"]}`
- `stage-attempt-2` (`E1`, high confidence): Executed Composer attempt 2 for stage laravel-7-to-8. Context: `{"stage_id":"laravel-7-to-8","attempt":2,"strategy":"root_constraint_remediation","scenario":"laravel-7-to-8-attempt-2-root_constraint_remediation","outcome":"solver_failure"}`
- `solver-7` (`E1`, high confidence): Composer scenario "laravel-7-to-8-attempt-2-root_constraint_remediation" failed. Context: `{"scenario":"laravel-7-to-8-attempt-2-root_constraint_remediation","targets":[{"package":"laravel/framework","constraint":"^8.0"},{"package":"laravel/horizon","constraint":"^5.0"},{"package":"php","constraint":"8.1.0"}],"exit_code":2,"output_excerpt":"Your requirements could not be resolved to an installable set of packages.\n- Root composer.json retains legacy package constraints that exclude the requested Laravel target.","diagnostics":[{"package":"laravel/framework","constraint":"^8.0","command":["composer","prohibits","laravel/framework","^8.0","--tree","--locked","--no-scripts","--no-plugins","--no-interaction"],"exit_code":1,"outcome":"success","stdout_excerpt":"","stderr_excerpt":"No additional fixture diagnostic."},{"package":"laravel/horizon","constraint":"^5.0","command":["composer","prohibits","laravel/horizon","^5.0","--tree","--locked","--no-scripts","--no-plugins","--no-interaction"],"exit_code":1,"outcome":"success","stdout_excerpt":"","stderr_excerpt":"No additional fixture diagnostic."},{"package":"php","constraint":"8.1.0","command":["composer","prohibits","php","8.1.0","--tree","--locked","--no-scripts","--no-plugins","--no-interaction"],"exit_code":1,"outcome":"success","stdout_excerpt":"","stderr_excerpt":"No additional fixture diagnostic."}]}`
- `stage-root-change-2` (`E2`, high confidence): Recorded an analyzer-only root constraint change for stage laravel-7-to-8. Context: `{"stage_id":"laravel-7-to-8","package":"laravel/framework","from_constraint":"^7.0","to_constraint":"^8.0","supporting_evidence":["laravel-stage-target-1"]}`
- `stage-root-change-3` (`E2`, high confidence): Recorded an analyzer-only root constraint change for stage laravel-7-to-8. Context: `{"stage_id":"laravel-7-to-8","package":"laravel/horizon","from_constraint":"^4.0","to_constraint":"^5.0","supporting_evidence":["laravel-stage-remediation-3"]}`
- `stage-attempt-3` (`E1`, high confidence): Executed Composer attempt 3 for stage laravel-7-to-8. Context: `{"stage_id":"laravel-7-to-8","attempt":3,"strategy":"root_and_locked_package_remediation","scenario":"laravel-7-to-8-attempt-3-root_and_locked_package_remediation","outcome":"solver_failure"}`
- `solver-8` (`E1`, high confidence): Composer scenario "laravel-7-to-8-attempt-3-root_and_locked_package_remediation" failed. Context: `{"scenario":"laravel-7-to-8-attempt-3-root_and_locked_package_remediation","targets":[{"package":"laravel/framework","constraint":"^8.0"},{"package":"laravel/horizon","constraint":"^5.0"},{"package":"laravel/passport","constraint":"^10.0"},{"package":"laravel/sanctum","constraint":"^2.0"},{"package":"laravel/telescope","constraint":"^4.0"},{"package":"laravel/ui","constraint":"^3.0"},{"package":"mockery/mockery","constraint":"^1.4"},{"package":"nunomaduro/collision","constraint":"^5.0"},{"package":"orchestra/testbench","constraint":"^6.0"},{"package":"php","constraint":"8.1.0"},{"package":"phpunit/phpunit","constraint":"^9.0"}],"exit_code":2,"output_excerpt":"Your requirements could not be resolved to an installable set of packages.\n- Root composer.json retains legacy package constraints that exclude the requested Laravel target.","diagnostics":[{"package":"laravel/framework","constraint":"^8.0","command":["composer","prohibits","laravel/framework","^8.0","--tree","--locked","--no-scripts","--no-plugins","--no-interaction"],"exit_code":1,"outcome":"success","stdout_excerpt":"","stderr_excerpt":"No additional fixture diagnostic."},{"package":"laravel/horizon","constraint":"^5.0","command":["composer","prohibits","laravel/horizon","^5.0","--tree","--locked","--no-scripts","--no-plugins","--no-interaction"],"exit_code":1,"outcome":"success","stdout_excerpt":"","stderr_excerpt":"No additional fixture diagnostic."},{"package":"laravel/passport","constraint":"^10.0","command":["composer","prohibits","laravel/passport","^10.0","--tree","--locked","--no-scripts","--no-plugins","--no-interaction"],"exit_code":1,"outcome":"success","stdout_excerpt":"","stderr_excerpt":"No additional fixture diagnostic."},{"package":"laravel/sanctum","constraint":"^2.0","command":["composer","prohibits","laravel/sanctum","^2.0","--tree","--locked","--no-scripts","--no-plugins","--no-interaction"],"exit_code":1,"outcome":"success","stdout_excerpt":"","stderr_excerpt":"No additional fixture diagnostic."},{"package":"laravel/telescope","constraint":"^4.0","command":["composer","prohibits","laravel/telescope","^4.0","--tree","--locked","--no-scripts","--no-plugins","--no-interaction"],"exit_code":1,"outcome":"success","stdout_excerpt":"","stderr_excerpt":"No additional fixture diagnostic."},{"package":"laravel/ui","constraint":"^3.0","command":["composer","prohibits","laravel/ui","^3.0","--tree","--locked","--no-scripts","--no-plugins","--no-interaction"],"exit_code":1,"outcome":"success","stdout_excerpt":"","stderr_excerpt":"No additional fixture diagnostic."},{"package":"mockery/mockery","constraint":"^1.4","command":["composer","prohibits","mockery/mockery","^1.4","--tree","--locked","--no-scripts","--no-plugins","--no-interaction"],"exit_code":1,"outcome":"success","stdout_excerpt":"","stderr_excerpt":"No additional fixture diagnostic."},{"package":"nunomaduro/collision","constraint":"^5.0","command":["composer","prohibits","nunomaduro/collision","^5.0","--tree","--locked","--no-scripts","--no-plugins","--no-interaction"],"exit_code":1,"outcome":"success","stdout_excerpt":"","stderr_excerpt":"No additional fixture diagnostic."},{"package":"orchestra/testbench","constraint":"^6.0","command":["composer","prohibits","orchestra/testbench","^6.0","--tree","--locked","--no-scripts","--no-plugins","--no-interaction"],"exit_code":1,"outcome":"success","stdout_excerpt":"","stderr_excerpt":"No additional fixture diagnostic."},{"package":"php","constraint":"8.1.0","command":["composer","prohibits","php","8.1.0","--tree","--locked","--no-scripts","--no-plugins","--no-interaction"],"exit_code":1,"outcome":"success","stdout_excerpt":"","stderr_excerpt":"No additional fixture diagnostic."},{"package":"phpunit/phpunit","constraint":"^9.0","command":["composer","prohibits","phpunit/phpunit","^9.0","--tree","--locked","--no-scripts","--no-plugins","--no-interaction"],"exit_code":1,"outcome":"success","stdout_excerpt":"","stderr_excerpt":"No additional fixture diagnostic."}]}`
- `stage-root-change-4` (`E2`, high confidence): Recorded an analyzer-only root constraint change for stage laravel-7-to-8. Context: `{"stage_id":"laravel-7-to-8","package":"laravel/framework","from_constraint":"^7.0","to_constraint":"^8.0","supporting_evidence":["laravel-stage-target-1"]}`
- `stage-root-change-5` (`E2`, high confidence): Recorded an analyzer-only root constraint change for stage laravel-7-to-8. Context: `{"stage_id":"laravel-7-to-8","package":"laravel/horizon","from_constraint":"^4.0","to_constraint":"^5.0","supporting_evidence":["laravel-stage-remediation-3"]}`
- `stage-root-change-6` (`E2`, high confidence): Recorded an analyzer-only root constraint change for stage laravel-7-to-8. Context: `{"stage_id":"laravel-7-to-8","package":"laravel/passport","from_constraint":"^8.0","to_constraint":"^10.0","supporting_evidence":["laravel-stage-remediation-1"]}`
- `stage-root-change-7` (`E2`, high confidence): Recorded an analyzer-only root constraint change for stage laravel-7-to-8. Context: `{"stage_id":"laravel-7-to-8","package":"laravel/sanctum","from_constraint":"^1.0","to_constraint":"^2.0","supporting_evidence":["laravel-stage-remediation-2"]}`
- `stage-root-change-8` (`E2`, high confidence): Recorded an analyzer-only root constraint change for stage laravel-7-to-8. Context: `{"stage_id":"laravel-7-to-8","package":"laravel/telescope","from_constraint":"^3.0","to_constraint":"^4.0","supporting_evidence":["laravel-stage-remediation-4"]}`
- `stage-root-change-9` (`E2`, high confidence): Recorded an analyzer-only root constraint change for stage laravel-7-to-8. Context: `{"stage_id":"laravel-7-to-8","package":"laravel/ui","from_constraint":"^2.0","to_constraint":"^3.0","supporting_evidence":["laravel-stage-remediation-8"]}`
- `stage-root-change-10` (`E2`, high confidence): Recorded an analyzer-only root constraint change for stage laravel-7-to-8. Context: `{"stage_id":"laravel-7-to-8","package":"mockery/mockery","from_constraint":"^1.3","to_constraint":"^1.4","supporting_evidence":["laravel-stage-remediation-6"]}`
- `stage-root-change-11` (`E2`, high confidence): Recorded an analyzer-only root constraint change for stage laravel-7-to-8. Context: `{"stage_id":"laravel-7-to-8","package":"nunomaduro/collision","from_constraint":"^4.0","to_constraint":"^5.0","supporting_evidence":["laravel-stage-remediation-7"]}`
- `stage-root-change-12` (`E2`, high confidence): Recorded an analyzer-only root constraint change for stage laravel-7-to-8. Context: `{"stage_id":"laravel-7-to-8","package":"orchestra/testbench","from_constraint":"^5.0","to_constraint":"^6.0","supporting_evidence":["laravel-stage-remediation-9"]}`
- `stage-root-change-13` (`E2`, high confidence): Recorded an analyzer-only root constraint change for stage laravel-7-to-8. Context: `{"stage_id":"laravel-7-to-8","package":"phpunit/phpunit","from_constraint":"^8.0","to_constraint":"^9.0","supporting_evidence":["laravel-stage-remediation-5"]}`
- `stage-skipped-1` (`E1`, high confidence): Stage laravel-8-to-9 was skipped after the preceding stage stopped the candidate-state chain. Context: `{"stage_id":"laravel-8-to-9","preceding_status":"blocked","reason":"previous_stage_blocked"}`
- `laravel-transition-1` (`E4`, medium confidence): The retained Laravel 7 to 8 rule pack covers this requested transition. Context: `{"source_major":7,"target_major":8,"rule_pack":"laravel-7-to-8","source":"https://laravel.com/docs/8.x/upgrade"}`
- `laravel-transition-2` (`E4`, medium confidence): The implemented Laravel 8 to 9 rule pack covers this requested transition. Context: `{"source_major":8,"target_major":9,"rule_pack":"laravel-8-to-9","source":"https://laravel.com/docs/9.x/upgrade"}`
- `laravel-framework-constraint-1` (`E2`, high confidence): The root Laravel framework constraint does not include the requested target major. Context: `{"package":"laravel/framework","root_constraint":"^7.0","target_constraint":"^9.0","target_laravel_major":9}`
- `laravel-php-constraint-1` (`E2`, high confidence): The detected PHP target or root constraint does not satisfy the Laravel target PHP range. Context: `{"observation":"target_php","observed_php":"8.1.0","root_php_constraint":"^7.4","required_php":"^8.0.2","target_laravel_major":9,"laravel_range_satisfied":true,"root_constraint_satisfied":false}`
- `laravel-package-laravel_passport-1` (`E2`, high confidence): laravel/passport is present in Composer metadata. Context: `{"package":"laravel/passport","locked_version":"v8.5.0","root_constraint":"^8.0","framework_requirements":[],"target_laravel_major":9}`
- `laravel-package-guidance-1` (`E4`, medium confidence): The encoded Laravel 9 guidance maps laravel/passport to `^10.0|^11.0`. Context: `{"package":"laravel/passport","target_laravel_major":9,"compatible_package_constraint":"^10.0|^11.0","sources":["https://github.com/laravel/passport/blob/10.x/composer.json","https://github.com/laravel/passport/blob/11.x/composer.json"]}`
- `laravel-package-laravel_sanctum-1` (`E2`, high confidence): laravel/sanctum is present in Composer metadata. Context: `{"package":"laravel/sanctum","locked_version":"v1.3.3","root_constraint":"^1.0","framework_requirements":[],"target_laravel_major":9}`
- `laravel-package-guidance-2` (`E4`, medium confidence): The encoded Laravel 9 guidance maps laravel/sanctum to `^2.0|^3.0`. Context: `{"package":"laravel/sanctum","target_laravel_major":9,"compatible_package_constraint":"^2.0|^3.0","sources":["https://github.com/laravel/sanctum/blob/2.x/composer.json","https://github.com/laravel/sanctum/blob/3.x/composer.json"]}`
- `laravel-package-laravel_horizon-1` (`E2`, high confidence): laravel/horizon is present in Composer metadata. Context: `{"package":"laravel/horizon","locked_version":"v4.3.5","root_constraint":"^4.0","framework_requirements":[],"target_laravel_major":9}`
- `laravel-package-guidance-3` (`E4`, medium confidence): The encoded Laravel 9 guidance maps laravel/horizon to `^5.0`. Context: `{"package":"laravel/horizon","target_laravel_major":9,"compatible_package_constraint":"^5.0","sources":["https://github.com/laravel/horizon/blob/5.x/composer.json"]}`
- `laravel-package-laravel_telescope-1` (`E2`, high confidence): laravel/telescope is present in Composer metadata. Context: `{"package":"laravel/telescope","locked_version":"v3.7.0","root_constraint":"^3.0","framework_requirements":[],"target_laravel_major":9}`
- `laravel-package-guidance-4` (`E4`, medium confidence): The encoded Laravel 9 guidance maps laravel/telescope to `^4.0`. Context: `{"package":"laravel/telescope","target_laravel_major":9,"compatible_package_constraint":"^4.0","sources":["https://github.com/laravel/telescope/blob/4.x/composer.json"]}`
- `laravel-package-phpunit_phpunit-1` (`E2`, high confidence): phpunit/phpunit is present in Composer metadata. Context: `{"package":"phpunit/phpunit","locked_version":"8.5.21","root_constraint":"^8.0","framework_requirements":[],"target_laravel_major":9}`
- `laravel-package-guidance-5` (`E4`, medium confidence): The encoded Laravel 9 guidance maps phpunit/phpunit to `^9.5.10`. Context: `{"package":"phpunit/phpunit","target_laravel_major":9,"compatible_package_constraint":"^9.5.10","sources":["https://github.com/laravel/laravel/blob/9.x/composer.json"]}`
- `laravel-package-mockery_mockery-1` (`E2`, high confidence): mockery/mockery is present in Composer metadata. Context: `{"package":"mockery/mockery","locked_version":"1.3.6","root_constraint":"^1.3","framework_requirements":[],"target_laravel_major":9}`
- `laravel-package-guidance-6` (`E4`, medium confidence): The encoded Laravel 9 guidance maps mockery/mockery to `^1.4`. Context: `{"package":"mockery/mockery","target_laravel_major":9,"compatible_package_constraint":"^1.4","sources":["https://github.com/laravel/laravel/blob/9.x/composer.json"]}`
- `laravel-symfony-constraints-1` (`E2`, high confidence): Root Symfony component constraints exclude the component major used by the Laravel target. Context: `{"root_constraints":{"symfony/http-foundation":"^4.4"},"target_laravel_major":9,"compatible_symfony_constraint":"^6.0"}`
- `laravel-symfony-guidance-1` (`E4`, medium confidence): Laravel 9 maps its core Symfony components to `^6.0`. Context: `{"target_laravel_major":9,"compatible_symfony_constraint":"^6.0","source":"https://github.com/laravel/framework/blob/9.x/composer.json"}`
- `laravel-advisory-facade_ignition-1` (`E2`, high confidence): facade/ignition is present in Composer metadata. Context: `{"package":"facade/ignition","locked_version":"2.17.7","root_constraint":"^2.0","target_laravel_major":9}`
- `laravel-package-advisory-1` (`E4`, medium confidence): Replace facade/ignition with spatie/laravel-ignition for the Laravel 9 target. Context: `{"package":"facade/ignition","target_laravel_major":9,"source":"https://laravel.com/docs/9.x/upgrade"}`
- `laravel-advisory-fideloper_proxy-1` (`E2`, high confidence): fideloper/proxy is present in Composer metadata. Context: `{"package":"fideloper/proxy","locked_version":"4.4.1","root_constraint":"^4.0","target_laravel_major":9}`
- `laravel-package-advisory-2` (`E4`, medium confidence): Remove fideloper/proxy and review the trusted proxy middleware for the Laravel 9 target. Context: `{"package":"fideloper/proxy","target_laravel_major":9,"source":"https://laravel.com/docs/9.x/upgrade"}`
- `laravel-advisory-fruitcake_laravel_cors-1` (`E2`, high confidence): fruitcake/laravel-cors is present in Composer metadata. Context: `{"package":"fruitcake/laravel-cors","locked_version":"2.2.0","root_constraint":"^2.0","target_laravel_major":9}`
- `laravel-package-advisory-3` (`E4`, medium confidence): Review removal of fruitcake/laravel-cors because Laravel 9 integrates CORS middleware through the framework. Context: `{"package":"fruitcake/laravel-cors","target_laravel_major":9,"source":"https://laravel.com/docs/9.x/upgrade"}`
- `laravel-package-nunomaduro_collision-1` (`E2`, high confidence): nunomaduro/collision is present in Composer metadata. Context: `{"package":"nunomaduro/collision","locked_version":"v4.3.0","root_constraint":"^4.0","framework_requirements":[],"target_laravel_major":9}`
- `laravel-package-guidance-7` (`E4`, medium confidence): The encoded Laravel 9 guidance maps nunomaduro/collision to `^6.1`. Context: `{"package":"nunomaduro/collision","target_laravel_major":9,"compatible_package_constraint":"^6.1","sources":["https://laravel.com/docs/9.x/upgrade"]}`
- `laravel-package-laravel_ui-1` (`E2`, high confidence): laravel/ui is present in Composer metadata. Context: `{"package":"laravel/ui","locked_version":"v2.5.0","root_constraint":"^2.0","framework_requirements":[],"target_laravel_major":9}`
- `laravel-package-guidance-8` (`E4`, medium confidence): The encoded Laravel 9 guidance maps laravel/ui to `^4.0`. Context: `{"package":"laravel/ui","target_laravel_major":9,"compatible_package_constraint":"^4.0","sources":["https://github.com/laravel/ui/blob/4.x/composer.json"]}`
- `laravel-package-orchestra_testbench-1` (`E2`, high confidence): orchestra/testbench is present in Composer metadata. Context: `{"package":"orchestra/testbench","locked_version":"v5.4.0","root_constraint":"^5.0","framework_requirements":[],"target_laravel_major":9}`
- `laravel-package-guidance-9` (`E4`, medium confidence): The encoded Laravel 9 guidance maps orchestra/testbench to `^7.0`. Context: `{"package":"orchestra/testbench","target_laravel_major":9,"compatible_package_constraint":"^7.0","sources":["https://github.com/orchestral/testbench/blob/7.x/composer.json"]}`
- `root-constraint-1` (`E2`, high confidence): Compared the root requirement for laravel/framework with the requested target. Context: `{"package":"laravel/framework","from_constraint":"^7.0","to_constraint":"^9.0"}`
- `stage-plan-1` (`E5`, low confidence): Generated recommendations from the executed outcome of stage laravel-7-to-8. Context: `{"stage_id":"laravel-7-to-8","execution_state":"evaluated","resolution_status":"blocked","transition_recommended":false}`
