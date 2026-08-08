# PHP Upgrade Preflight Report

Resolution: **feasible_with_changes** | Schema: `0.6` | Tool: `php-upgrade-preflight 0.1.0`

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

## Project State
- Analyzed path: `<PROJECT_PATH>`
- Composer platform PHP: `7.4.33`
- Locked packages: `1`
- Root requirements:
  - `php`: `^7.4 || ^8.0`
  - `laravel/framework`: `^7.0`

## Composer Scenarios
- `baseline-validation`: succeeded (outcome `success`, Composer `unknown`, duration `1 ms`, exit `0`, failure type `none`)
  - command argv: `["composer","validate","--check-lock","--no-check-publish","--no-scripts","--no-plugins","--no-interaction"]`
  - temporary workspace: `not preserved`
  - stdout excerpt:
    ```text
    Fixture baseline is valid.
    ```
  - stderr excerpt: *(empty)*
  - candidate lock: SHA-256 `f5c68267ae8dd17ac99cb55577fe45208367c358e8be650d2b908d6c2207ca18`, content hash `laravel-7-to-8`, packages `1`
  - diagnostics: none
- `exact-target`: succeeded (outcome `success`, Composer `unknown`, duration `1 ms`, exit `0`, failure type `none`)
  - command argv: `["composer","update","laravel/framework","--no-scripts","--no-plugins","--no-install","--no-interaction"]`
  - temporary workspace: `not preserved`
  - stdout excerpt:
    ```text
    Fixture target resolved.
    ```
  - stderr excerpt: *(empty)*
  - candidate lock: SHA-256 `8ae5491363cf02faf31066502efc71434bfea78b873276b1cabb7962f9f8371a`, content hash `candidate-v8.83.27`, packages `1`
  - diagnostics: none
- `target-with-all-dependencies`: succeeded (outcome `success`, Composer `unknown`, duration `1 ms`, exit `0`, failure type `none`)
  - command argv: `["composer","update","laravel/framework","--with-all-dependencies","--no-scripts","--no-plugins","--no-install","--no-interaction"]`
  - temporary workspace: `not preserved`
  - stdout excerpt:
    ```text
    Fixture target resolved.
    ```
  - stderr excerpt: *(empty)*
  - candidate lock: SHA-256 `8ae5491363cf02faf31066502efc71434bfea78b873276b1cabb7962f9f8371a`, content hash `candidate-v8.83.27`, packages `1`
  - diagnostics: none
- `minimal-changes`: succeeded (outcome `success`, Composer `unknown`, duration `1 ms`, exit `0`, failure type `none`)
  - command argv: `["composer","update","laravel/framework","--with-all-dependencies","--minimal-changes","--no-scripts","--no-plugins","--no-install","--no-interaction"]`
  - temporary workspace: `not preserved`
  - stdout excerpt:
    ```text
    Fixture target resolved.
    ```
  - stderr excerpt: *(empty)*
  - candidate lock: SHA-256 `8ae5491363cf02faf31066502efc71434bfea78b873276b1cabb7962f9f8371a`, content hash `candidate-v8.83.27`, packages `1`
  - diagnostics: none
- `target-platform-only`: succeeded (outcome `success`, Composer `unknown`, duration `1 ms`, exit `0`, failure type `none`)
  - command argv: `["composer","update","--no-scripts","--no-plugins","--no-install","--no-interaction"]`
  - temporary workspace: `not preserved`
  - stdout excerpt:
    ```text
    Fixture target resolved.
    ```
  - stderr excerpt: *(empty)*
  - candidate lock: SHA-256 `f5c68267ae8dd17ac99cb55577fe45208367c358e8be650d2b908d6c2207ca18`, content hash `laravel-7-to-8`, packages `1`
  - diagnostics: none
- `staged-targets`: succeeded (outcome `success`, Composer `unknown`, duration `1 ms`, exit `0`, failure type `none`)
  - command argv: `["composer","update","laravel/framework","--with-all-dependencies","--no-scripts","--no-plugins","--no-install","--no-interaction"]`
  - temporary workspace: `not preserved`
  - stdout excerpt:
    ```text
    Fixture target resolved.
    ```
  - stderr excerpt: *(empty)*
  - candidate lock: SHA-256 `8ae5491363cf02faf31066502efc71434bfea78b873276b1cabb7962f9f8371a`, content hash `candidate-v8.83.27`, packages `1`
  - diagnostics: none

## Package Changes
- `laravel/framework`: upgraded `v7.30.7` -> `v8.83.27` (direct dependency; major-version jump; families: laravel)
  - source reference: `-` -> `-`
  - dist reference: `-` -> `-`

## Root Constraint Changes
- `laravel/framework`: updated `^7.0` -> `^8.0`. The declared root constraint differs from the requested target. (evidence: `root-constraint-1`)

## Blockers
- None detected.

## Source Impact
- None detected.

## Framework Findings
- `laravel` `high`: Update the root laravel/framework constraint from `^7.0` to a constraint compatible with Laravel 8. (evidence: `laravel-framework-constraint-1`)

## Staged Plan
1. **constraints** — Prepare the requested root constraint changes before dependency resolution. (evidence: `plan-1`, `root-constraint-1`)
   - Update the `laravel/framework` root constraint to `^8.0`.
2. **dependencies** — Apply and review the successful dependency transition. (evidence: `plan-1`)
   - Regenerate `composer.lock` with the smallest successful dependency transition.
3. **application** — Apply source and framework migration work after dependency resolution is stable. (evidence: `plan-1`, `laravel-framework-constraint-1`)
   - Address framework compatibility findings before runtime validation.
4. **validation** — Validate the upgraded project on the target runtime before release. (evidence: `plan-1`)
   - Validate the Composer manifest and installed platform requirements.
   - Run the project test suite and focused regression tests.

## Risk And Effort
- Risk: `low`
- Risk drivers:
  - Framework compatibility findings require review.
- Effort: `4-13` hours (low confidence)
- Effort components:
  - `dependency_resolution`: `1-2` hours
  - `source_changes`: `1-3` hours
  - `tests_and_debugging`: `2-8` hours
- Effort assumptions:
  - Estimate is heuristic until project-specific tests and Composer solver output are reviewed.

## Test Guidance
- **composer-validation** (`required`): Validate the edited Composer manifest before dependency installation. Command: `composer validate --strict`.
- **project-test-suite** (`required`): Identify and run the project test suite; no Composer test script was detected. Command: project-specific command required.
- **platform-requirements** (`required`): Confirm the installed dependencies satisfy PHP 8.0.0 and the deployment extensions. Command: `composer check-platform-reqs`.
- **focused-regressions** (`recommended`): Add or run focused regression coverage for the reported source and framework findings. Command: project-specific command required.

## Uncertainties
- No PHP source files were scanned.
- Dependency resolution does not prove application runtime compatibility; the project test suite must run on the target runtime.
- No Composer "test" script was found, so the project's canonical test command is unknown.

## Evidence
- `laravel-framework-constraint-1` (`E2`, high confidence): The root Laravel framework constraint does not include the requested target major. Context: `{"package":"laravel/framework","root_constraint":"^7.0","target_constraint":"^8.0","target_laravel_major":8}`
- `root-constraint-1` (`E2`, high confidence): Compared the root requirement for laravel/framework with the requested target. Context: `{"package":"laravel/framework","from_constraint":"^7.0","to_constraint":"^8.0"}`
- `plan-1` (`E5`, low confidence): Generated conservative staged actions from the requested targets and detected findings. Context: `{"target_count":2,"root_constraint_change_count":1,"blocker_count":0,"source_finding_count":0,"framework_finding_count":1}`
