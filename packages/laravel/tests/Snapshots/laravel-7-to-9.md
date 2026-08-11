# PHP Upgrade Preflight Report

Resolution: **feasible_with_changes** | Schema: `0.7` | Tool: `php-upgrade-preflight 0.2.0`

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
  - candidate lock: SHA-256 `c69e486f887c9f57ee9199802d06393946ed7728c13c2e5c2476308e4703ef2e`, content hash `laravel-7-to-9`, packages `1`
  - diagnostics: none
- `exact-target`: succeeded (outcome `success`, Composer `unknown`, duration `1 ms`, exit `0`, failure type `none`)
  - command argv: `["composer","update","laravel/framework","--no-scripts","--no-plugins","--no-install","--no-audit","--no-progress","--no-interaction"]`
  - temporary workspace: `not preserved`
  - stdout excerpt:
    ```text
    Fixture target resolved.
    ```
  - stderr excerpt: *(empty)*
  - candidate lock: SHA-256 `4cdd1b9c664657c4cc43ab2762565e387270b3dde4d1f4de7ba19c93a29c3495`, content hash `candidate-v9.52.16`, packages `1`
  - diagnostics: none
- `target-with-all-dependencies`: succeeded (outcome `success`, Composer `unknown`, duration `1 ms`, exit `0`, failure type `none`)
  - command argv: `["composer","update","laravel/framework","--with-all-dependencies","--no-scripts","--no-plugins","--no-install","--no-audit","--no-progress","--no-interaction"]`
  - temporary workspace: `not preserved`
  - stdout excerpt:
    ```text
    Fixture target resolved.
    ```
  - stderr excerpt: *(empty)*
  - candidate lock: SHA-256 `4cdd1b9c664657c4cc43ab2762565e387270b3dde4d1f4de7ba19c93a29c3495`, content hash `candidate-v9.52.16`, packages `1`
  - diagnostics: none
- `minimal-changes`: succeeded (outcome `success`, Composer `unknown`, duration `1 ms`, exit `0`, failure type `none`)
  - command argv: `["composer","update","laravel/framework","--with-all-dependencies","--minimal-changes","--no-scripts","--no-plugins","--no-install","--no-audit","--no-progress","--no-interaction"]`
  - temporary workspace: `not preserved`
  - stdout excerpt:
    ```text
    Fixture target resolved.
    ```
  - stderr excerpt: *(empty)*
  - candidate lock: SHA-256 `4cdd1b9c664657c4cc43ab2762565e387270b3dde4d1f4de7ba19c93a29c3495`, content hash `candidate-v9.52.16`, packages `1`
  - diagnostics: none
- `target-platform-only`: succeeded (outcome `success`, Composer `unknown`, duration `1 ms`, exit `0`, failure type `none`)
  - command argv: `["composer","update","--no-scripts","--no-plugins","--no-install","--no-audit","--no-progress","--no-interaction"]`
  - temporary workspace: `not preserved`
  - stdout excerpt:
    ```text
    Fixture target resolved.
    ```
  - stderr excerpt: *(empty)*
  - candidate lock: SHA-256 `c69e486f887c9f57ee9199802d06393946ed7728c13c2e5c2476308e4703ef2e`, content hash `laravel-7-to-9`, packages `1`
  - diagnostics: none
- `staged-targets`: succeeded (outcome `success`, Composer `unknown`, duration `1 ms`, exit `0`, failure type `none`)
  - command argv: `["composer","update","laravel/framework","--with-all-dependencies","--no-scripts","--no-plugins","--no-install","--no-audit","--no-progress","--no-interaction"]`
  - temporary workspace: `not preserved`
  - stdout excerpt:
    ```text
    Fixture target resolved.
    ```
  - stderr excerpt: *(empty)*
  - candidate lock: SHA-256 `4cdd1b9c664657c4cc43ab2762565e387270b3dde4d1f4de7ba19c93a29c3495`, content hash `candidate-v9.52.16`, packages `1`
  - diagnostics: none

## Package Changes
- `laravel/framework`: upgraded `v7.30.7` -> `v9.52.16` (direct dependency; major-version jump; families: laravel)
  - source reference: `-` -> `-`
  - dist reference: `-` -> `-`

## Framework Transition Guidance
- `laravel`: `supported` (`7` -> `9`; evidence: `laravel-transition-1`, `laravel-transition-2`)
  - hop `7` -> `8`: `supported`; rule pack `laravel-7-to-8` (evidence: `laravel-transition-1`)
  - hop `8` -> `9`: `supported`; rule pack `laravel-8-to-9` (evidence: `laravel-transition-2`)

## Root Constraint Changes
- `laravel/framework`: updated `^7.0` -> `^9.0`. The declared root constraint differs from the requested target. (evidence: `root-constraint-1`)

## Blockers
- None detected.

## Source Inventory
- None detected.

## Actionable Source Impact
- None detected.

## Framework Findings
- `laravel` `high`: Update the root laravel/framework constraint from `^7.0` to a constraint compatible with Laravel 9. (evidence: `laravel-framework-constraint-1`)
  - applies to hops: `8 -> 9`

## Staged Plan
1. **constraints** — Prepare the requested root constraint changes before dependency resolution. (evidence: `plan-1`, `root-constraint-1`)
   - Update the `laravel/framework` root constraint to `^9.0`.
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
- **platform-requirements** (`required`): Confirm the installed dependencies satisfy PHP 8.1.0 and the deployment extensions. Command: `composer check-platform-reqs`.
- **focused-regressions** (`recommended`): Add or run focused regression coverage for the reported source and framework findings. Command: project-specific command required.

## Uncertainties
- No PHP source files were scanned.
- Dependency resolution does not prove application runtime compatibility; the project test suite must run on the target runtime.
- No Composer "test" script was found, so the project's canonical test command is unknown.
- Composer extension checks used the analyzer runtime because no complete explicit extension platform was supplied.

## Evidence
- `laravel-transition-1` (`E4`, medium confidence): The retained Laravel 7 to 8 rule pack covers this requested transition. Context: `{"source_major":7,"target_major":8,"rule_pack":"laravel-7-to-8","source":"https://laravel.com/docs/8.x/upgrade"}`
- `laravel-transition-2` (`E4`, medium confidence): The implemented Laravel 8 to 9 rule pack covers this requested transition. Context: `{"source_major":8,"target_major":9,"rule_pack":"laravel-8-to-9","source":"https://laravel.com/docs/9.x/upgrade"}`
- `laravel-framework-constraint-1` (`E2`, high confidence): The root Laravel framework constraint does not include the requested target major. Context: `{"package":"laravel/framework","root_constraint":"^7.0","target_constraint":"^9.0","target_laravel_major":9}`
- `root-constraint-1` (`E2`, high confidence): Compared the root requirement for laravel/framework with the requested target. Context: `{"package":"laravel/framework","from_constraint":"^7.0","to_constraint":"^9.0"}`
- `plan-1` (`E5`, low confidence): Generated conservative staged actions from the requested targets and detected findings. Context: `{"target_count":2,"root_constraint_change_count":1,"blocker_count":0,"source_finding_count":0,"framework_finding_count":1}`
