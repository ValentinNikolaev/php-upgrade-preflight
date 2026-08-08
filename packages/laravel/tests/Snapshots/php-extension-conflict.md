# PHP Upgrade Preflight Report

Resolution: **blocked** | Schema: `0.7` | Tool: `php-upgrade-preflight 0.2.0-dev`

## Analysis Request
- Project: `<PROJECT_PATH>`
- Current PHP: `7.4`
- Target PHP: `8.1.0`
- Source paths: `default project paths`
- Framework integrations: `automatic detection`
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

## Project State
- Analyzed path: `<PROJECT_PATH>`
- Composer platform PHP: `not configured`
- Locked packages: `2`
- Root requirements:
  - `php`: `^7.4`
  - `laravel/framework`: `^7.0`
  - `fixture/runtime-guard`: `1.0.0`

## Composer Scenarios
- `baseline-validation`: succeeded (outcome `success`, Composer `unknown`, duration `1 ms`, exit `0`, failure type `none`)
  - command argv: `["composer","validate","--check-lock","--no-check-publish","--no-scripts","--no-plugins","--no-interaction"]`
  - temporary workspace: `not preserved`
  - stdout excerpt:
    ```text
    Fixture baseline is valid.
    ```
  - stderr excerpt: *(empty)*
  - candidate lock: SHA-256 `3401d4adcfc3dd15766b4963616eedd2b3c05d764f233bfe16986229e3eb2775`, content hash `php-extension-conflict`, packages `2`
  - diagnostics: none
- `exact-target`: failed (outcome `solver_failure`, Composer `unknown`, duration `1 ms`, exit `2`, failure type `solver`)
  - command argv: `["composer","update","laravel/framework","--no-scripts","--no-plugins","--no-install","--no-audit","--no-progress","--no-interaction"]`
  - temporary workspace: `not preserved`
  - stdout excerpt: *(empty)*
  - stderr excerpt:
    ```text
    Your requirements could not be resolved to an installable set of packages.
    Problem 1
    - fixture/runtime-guard 1.0.0 requires php ^7.4 -> your php version (8.1.0) does not satisfy that requirement.
    Problem 2
    - fixture/runtime-guard 1.0.0 requires ext-preflight_fixture * -> it is missing from your system.
    ```
  - candidate lock: not available
  - diagnostic for `laravel/framework ^9.0` (exit `1`), command argv: `["composer","prohibits","laravel/framework","^9.0","--tree","--locked","--no-scripts","--no-plugins","--no-interaction"]`
    - stdout excerpt: *(empty)*
    - stderr excerpt:
      ```text
      No additional fixture diagnostic.
      ```
  - diagnostic for `php 8.1.0` (exit `1`), command argv: `["composer","prohibits","php","8.1.0","--tree","--locked","--no-scripts","--no-plugins","--no-interaction"]`
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
    Problem 1
    - fixture/runtime-guard 1.0.0 requires php ^7.4 -> your php version (8.1.0) does not satisfy that requirement.
    Problem 2
    - fixture/runtime-guard 1.0.0 requires ext-preflight_fixture * -> it is missing from your system.
    ```
  - candidate lock: not available
  - diagnostic for `laravel/framework ^9.0` (exit `1`), command argv: `["composer","prohibits","laravel/framework","^9.0","--tree","--locked","--no-scripts","--no-plugins","--no-interaction"]`
    - stdout excerpt: *(empty)*
    - stderr excerpt:
      ```text
      No additional fixture diagnostic.
      ```
  - diagnostic for `php 8.1.0` (exit `1`), command argv: `["composer","prohibits","php","8.1.0","--tree","--locked","--no-scripts","--no-plugins","--no-interaction"]`
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
    Problem 1
    - fixture/runtime-guard 1.0.0 requires php ^7.4 -> your php version (8.1.0) does not satisfy that requirement.
    Problem 2
    - fixture/runtime-guard 1.0.0 requires ext-preflight_fixture * -> it is missing from your system.
    ```
  - candidate lock: not available
  - diagnostic for `laravel/framework ^9.0` (exit `1`), command argv: `["composer","prohibits","laravel/framework","^9.0","--tree","--locked","--no-scripts","--no-plugins","--no-interaction"]`
    - stdout excerpt: *(empty)*
    - stderr excerpt:
      ```text
      No additional fixture diagnostic.
      ```
  - diagnostic for `php 8.1.0` (exit `1`), command argv: `["composer","prohibits","php","8.1.0","--tree","--locked","--no-scripts","--no-plugins","--no-interaction"]`
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
    Problem 1
    - fixture/runtime-guard 1.0.0 requires php ^7.4 -> your php version (8.1.0) does not satisfy that requirement.
    Problem 2
    - fixture/runtime-guard 1.0.0 requires ext-preflight_fixture * -> it is missing from your system.
    ```
  - candidate lock: not available
  - diagnostic for `php 8.1.0` (exit `1`), command argv: `["composer","prohibits","php","8.1.0","--tree","--locked","--no-scripts","--no-plugins","--no-interaction"]`
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
    Problem 1
    - fixture/runtime-guard 1.0.0 requires php ^7.4 -> your php version (8.1.0) does not satisfy that requirement.
    Problem 2
    - fixture/runtime-guard 1.0.0 requires ext-preflight_fixture * -> it is missing from your system.
    ```
  - candidate lock: not available
  - diagnostic for `laravel/framework ^9.0` (exit `1`), command argv: `["composer","prohibits","laravel/framework","^9.0","--tree","--locked","--no-scripts","--no-plugins","--no-interaction"]`
    - stdout excerpt: *(empty)*
    - stderr excerpt:
      ```text
      No additional fixture diagnostic.
      ```

## Package Changes
- No lockfile changes detected.

## Framework Transition Guidance
- `laravel`: `supported` (`7` -> `9`; evidence: `laravel-transition-1`)
  - hop `7` -> `9`: `supported`; rule pack `laravel-7-to-9-direct` (evidence: `laravel-transition-1`)

## Root Constraint Changes
- `laravel/framework`: updated `^7.0` -> `^9.0`. The declared root constraint differs from the requested target. (evidence: `root-constraint-1`)

## Blockers
- `php-platform-too-high` `php`: The requested PHP platform is higher than a package supports. (high confidence; evidence: `solver-1`, `solver-2`, `solver-3`, `solver-4`)
  - requested `8.1.0`; blocker `fixture/runtime-guard`; locked `1.0.0`; conflict `^7.4`
  - dependency path: `fixture/runtime-guard -> php`
  - option: Upgrade or replace `fixture/runtime-guard` with a version that supports the target PHP.
  - option: Select a supported PHP target.
- `extension-missing` `ext-preflight_fixture`: A required PHP extension is unavailable. (high confidence; evidence: `solver-1`, `solver-2`, `solver-3`, `solver-4`, `solver-5`)
  - requested `-`; blocker `fixture/runtime-guard`; locked `1.0.0`; conflict `*`
  - dependency path: `fixture/runtime-guard -> ext-preflight_fixture`
  - option: Install and enable `ext-preflight_fixture` for the target runtime.
  - option: Choose package versions that do not require `ext-preflight_fixture`.
- `php-platform-too-low` `php`: The requested PHP platform is lower than a package requirement. (high confidence; evidence: `solver-5`)
  - requested `7.4.0`; blocker `fixture/runtime-guard`; locked `1.0.0`; conflict `^7.4`
  - dependency path: `fixture/runtime-guard -> php`
  - option: Raise the target PHP version.
  - option: Select a version of `fixture/runtime-guard` compatible with the target PHP.

## Source Inventory
- None detected.

## Actionable Source Impact
- None detected.

## Framework Findings
- `laravel` `high`: Update the root laravel/framework constraint from `^7.0` to a constraint compatible with Laravel 9. (evidence: `laravel-framework-constraint-1`)
  - applies to hops: `7 -> 9`
- `laravel` `high`: The root PHP constraint `^7.4` excludes target PHP `8.1.0`; update it for the Laravel 9 upgrade. (evidence: `laravel-php-constraint-1`)
  - applies to hops: `7 -> 9`

## Staged Plan
1. **constraints** — Prepare the requested root constraint changes before dependency resolution. (evidence: `plan-1`, `root-constraint-1`)
   - Update the `laravel/framework` root constraint to `^9.0`.
   - Select a root PHP constraint that includes target platform PHP 8.1.0 without pinning an exact patch version.
2. **dependencies** — Resolve dependency blockers and review the resulting lockfile transition. (evidence: `plan-1`, `solver-1`, `solver-2`, `solver-3`, `solver-4`, `solver-5`)
   - Resolve the `php-platform-too-high` blocker affecting `php`.
   - Resolve the `extension-missing` blocker affecting `ext-preflight_fixture`.
   - Resolve the `php-platform-too-low` blocker affecting `php`.
   - Rerun the isolated Composer scenarios after resolving the reported blockers.
3. **application** — Apply source and framework migration work after dependency resolution is stable. (evidence: `plan-1`, `laravel-framework-constraint-1`, `laravel-php-constraint-1`)
   - Address framework compatibility findings before runtime validation.
4. **validation** — Validate the upgraded project on the target runtime before release. (evidence: `plan-1`)
   - Validate the Composer manifest and installed platform requirements.
   - Run the project test suite and focused regression tests.

## Risk And Effort
- Risk: `high`
- Risk drivers:
  - Composer resolution is blocked.
  - Framework compatibility findings require review.
- Effort: `6-20` hours (low confidence)
- Effort components:
  - `dependency_resolution`: `3-8` hours
  - `source_changes`: `1-4` hours
  - `tests_and_debugging`: `2-8` hours
- Effort assumptions:
  - Estimate is heuristic until project-specific tests and Composer solver output are reviewed.

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

## Evidence
- `solver-1` (`E1`, high confidence): Composer scenario "exact-target" failed. Context: `{"scenario":"exact-target","targets":[{"package":"laravel/framework","constraint":"^9.0"},{"package":"php","constraint":"8.1.0"}],"exit_code":2,"output_excerpt":"Your requirements could not be resolved to an installable set of packages.\nProblem 1\n- fixture/runtime-guard 1.0.0 requires php ^7.4 -> your php version (8.1.0) does not satisfy that requirement.\nProblem 2\n- fixture/runtime-guard 1.0.0 requires ext-preflight_fixture * -> it is missing from your system.","diagnostics":[{"package":"laravel/framework","constraint":"^9.0","command":["composer","prohibits","laravel/framework","^9.0","--tree","--locked","--no-scripts","--no-plugins","--no-interaction"],"exit_code":1,"stdout_excerpt":"","stderr_excerpt":"No additional fixture diagnostic."},{"package":"php","constraint":"8.1.0","command":["composer","prohibits","php","8.1.0","--tree","--locked","--no-scripts","--no-plugins","--no-interaction"],"exit_code":1,"stdout_excerpt":"","stderr_excerpt":"No additional fixture diagnostic."}]}`
- `solver-2` (`E1`, high confidence): Composer scenario "target-with-all-dependencies" failed. Context: `{"scenario":"target-with-all-dependencies","targets":[{"package":"laravel/framework","constraint":"^9.0"},{"package":"php","constraint":"8.1.0"}],"exit_code":2,"output_excerpt":"Your requirements could not be resolved to an installable set of packages.\nProblem 1\n- fixture/runtime-guard 1.0.0 requires php ^7.4 -> your php version (8.1.0) does not satisfy that requirement.\nProblem 2\n- fixture/runtime-guard 1.0.0 requires ext-preflight_fixture * -> it is missing from your system.","diagnostics":[{"package":"laravel/framework","constraint":"^9.0","command":["composer","prohibits","laravel/framework","^9.0","--tree","--locked","--no-scripts","--no-plugins","--no-interaction"],"exit_code":1,"stdout_excerpt":"","stderr_excerpt":"No additional fixture diagnostic."},{"package":"php","constraint":"8.1.0","command":["composer","prohibits","php","8.1.0","--tree","--locked","--no-scripts","--no-plugins","--no-interaction"],"exit_code":1,"stdout_excerpt":"","stderr_excerpt":"No additional fixture diagnostic."}]}`
- `solver-3` (`E1`, high confidence): Composer scenario "minimal-changes" failed. Context: `{"scenario":"minimal-changes","targets":[{"package":"laravel/framework","constraint":"^9.0"},{"package":"php","constraint":"8.1.0"}],"exit_code":2,"output_excerpt":"Your requirements could not be resolved to an installable set of packages.\nProblem 1\n- fixture/runtime-guard 1.0.0 requires php ^7.4 -> your php version (8.1.0) does not satisfy that requirement.\nProblem 2\n- fixture/runtime-guard 1.0.0 requires ext-preflight_fixture * -> it is missing from your system.","diagnostics":[{"package":"laravel/framework","constraint":"^9.0","command":["composer","prohibits","laravel/framework","^9.0","--tree","--locked","--no-scripts","--no-plugins","--no-interaction"],"exit_code":1,"stdout_excerpt":"","stderr_excerpt":"No additional fixture diagnostic."},{"package":"php","constraint":"8.1.0","command":["composer","prohibits","php","8.1.0","--tree","--locked","--no-scripts","--no-plugins","--no-interaction"],"exit_code":1,"stdout_excerpt":"","stderr_excerpt":"No additional fixture diagnostic."}]}`
- `solver-4` (`E1`, high confidence): Composer scenario "target-platform-only" failed. Context: `{"scenario":"target-platform-only","targets":[{"package":"php","constraint":"8.1.0"}],"exit_code":2,"output_excerpt":"Your requirements could not be resolved to an installable set of packages.\nProblem 1\n- fixture/runtime-guard 1.0.0 requires php ^7.4 -> your php version (8.1.0) does not satisfy that requirement.\nProblem 2\n- fixture/runtime-guard 1.0.0 requires ext-preflight_fixture * -> it is missing from your system.","diagnostics":[{"package":"php","constraint":"8.1.0","command":["composer","prohibits","php","8.1.0","--tree","--locked","--no-scripts","--no-plugins","--no-interaction"],"exit_code":1,"stdout_excerpt":"","stderr_excerpt":"No additional fixture diagnostic."}]}`
- `solver-5` (`E1`, high confidence): Composer scenario "staged-targets" failed. Context: `{"scenario":"staged-targets","targets":[{"package":"laravel/framework","constraint":"^9.0"},{"package":"php","constraint":"7.4.0"}],"exit_code":2,"output_excerpt":"Your requirements could not be resolved to an installable set of packages.\nProblem 1\n- fixture/runtime-guard 1.0.0 requires php ^7.4 -> your php version (8.1.0) does not satisfy that requirement.\nProblem 2\n- fixture/runtime-guard 1.0.0 requires ext-preflight_fixture * -> it is missing from your system.","diagnostics":[{"package":"laravel/framework","constraint":"^9.0","command":["composer","prohibits","laravel/framework","^9.0","--tree","--locked","--no-scripts","--no-plugins","--no-interaction"],"exit_code":1,"stdout_excerpt":"","stderr_excerpt":"No additional fixture diagnostic."}]}`
- `laravel-transition-1` (`E4`, medium confidence): The retained Laravel 7 to 9 rule pack covers this requested transition. Context: `{"source_major":7,"target_major":9,"rule_pack":"laravel-7-to-9-direct","source":"https://laravel.com/docs/9.x/upgrade"}`
- `laravel-framework-constraint-1` (`E2`, high confidence): The root Laravel framework constraint does not include the requested target major. Context: `{"package":"laravel/framework","root_constraint":"^7.0","target_constraint":"^9.0","target_laravel_major":9}`
- `laravel-php-constraint-1` (`E2`, high confidence): The detected PHP target or root constraint does not satisfy the Laravel target PHP range. Context: `{"observation":"target_php","observed_php":"8.1.0","root_php_constraint":"^7.4","required_php":"^8.0.2","target_laravel_major":9,"laravel_range_satisfied":true,"root_constraint_satisfied":false}`
- `root-constraint-1` (`E2`, high confidence): Compared the root requirement for laravel/framework with the requested target. Context: `{"package":"laravel/framework","from_constraint":"^7.0","to_constraint":"^9.0"}`
- `plan-1` (`E5`, low confidence): Generated conservative staged actions from the requested targets and detected findings. Context: `{"target_count":2,"root_constraint_change_count":1,"blocker_count":3,"source_finding_count":0,"framework_finding_count":2}`
