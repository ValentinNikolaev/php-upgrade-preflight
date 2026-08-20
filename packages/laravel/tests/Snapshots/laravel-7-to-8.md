# PHP Upgrade Preflight Report

Resolution: **feasible_with_changes** | Staged: **feasible_with_changes** | Schema: `0.8` | Tool: `php-upgrade-preflight 0.3.3`

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
  - candidate lock: SHA-256 `2680ac963ce716e4ae164553599a4ba190c5161873966399eff009d609750554`, content hash `laravel-7-to-8`, packages `1`
  - diagnostics: none
- `exact-target`: succeeded (outcome `success`, Composer `unknown`, duration `1 ms`, exit `0`, failure type `none`)
  - command argv: `["composer","update","laravel/framework","--no-scripts","--no-plugins","--no-install","--no-audit","--no-progress","--no-interaction"]`
  - temporary workspace: `not preserved`
  - stdout excerpt:
    ```text
    Fixture target resolved.
    ```
  - stderr excerpt: *(empty)*
  - candidate lock: SHA-256 `8ae5491363cf02faf31066502efc71434bfea78b873276b1cabb7962f9f8371a`, content hash `candidate-v8.83.27`, packages `1`
  - diagnostics: none
- `target-with-all-dependencies`: succeeded (outcome `success`, Composer `unknown`, duration `1 ms`, exit `0`, failure type `none`)
  - command argv: `["composer","update","laravel/framework","--with-all-dependencies","--no-scripts","--no-plugins","--no-install","--no-audit","--no-progress","--no-interaction"]`
  - temporary workspace: `not preserved`
  - stdout excerpt:
    ```text
    Fixture target resolved.
    ```
  - stderr excerpt: *(empty)*
  - candidate lock: SHA-256 `8ae5491363cf02faf31066502efc71434bfea78b873276b1cabb7962f9f8371a`, content hash `candidate-v8.83.27`, packages `1`
  - diagnostics: none
- `minimal-changes`: succeeded (outcome `success`, Composer `unknown`, duration `1 ms`, exit `0`, failure type `none`)
  - command argv: `["composer","update","laravel/framework","--with-all-dependencies","--minimal-changes","--no-scripts","--no-plugins","--no-install","--no-audit","--no-progress","--no-interaction"]`
  - temporary workspace: `not preserved`
  - stdout excerpt:
    ```text
    Fixture target resolved.
    ```
  - stderr excerpt: *(empty)*
  - candidate lock: SHA-256 `8ae5491363cf02faf31066502efc71434bfea78b873276b1cabb7962f9f8371a`, content hash `candidate-v8.83.27`, packages `1`
  - diagnostics: none
- `target-platform-only`: succeeded (outcome `success`, Composer `unknown`, duration `1 ms`, exit `0`, failure type `none`)
  - command argv: `["composer","update","--no-scripts","--no-plugins","--no-install","--no-audit","--no-progress","--no-interaction"]`
  - temporary workspace: `not preserved`
  - stdout excerpt:
    ```text
    Fixture target resolved.
    ```
  - stderr excerpt: *(empty)*
  - candidate lock: SHA-256 `2680ac963ce716e4ae164553599a4ba190c5161873966399eff009d609750554`, content hash `laravel-7-to-8`, packages `1`
  - diagnostics: none
- `staged-targets`: succeeded (outcome `success`, Composer `unknown`, duration `1 ms`, exit `0`, failure type `none`)
  - command argv: `["composer","update","laravel/framework","--with-all-dependencies","--no-scripts","--no-plugins","--no-install","--no-audit","--no-progress","--no-interaction"]`
  - temporary workspace: `not preserved`
  - stdout excerpt:
    ```text
    Fixture target resolved.
    ```
  - stderr excerpt: *(empty)*
  - candidate lock: SHA-256 `8ae5491363cf02faf31066502efc71434bfea78b873276b1cabb7962f9f8371a`, content hash `candidate-v8.83.27`, packages `1`
  - diagnostics: none

## Staged Composer Resolution
- Execution: `evaluated`; status: `feasible_with_changes`; provider: `laravel`; stop reason: `none`
- **laravel-7-to-8** (`7` -> `8`): execution `evaluated`; resolution `feasible_with_changes`; selected attempt `1`
  - analysis PHP: `8.0.0`; source snapshot: `original_project`
  - This stage assessment inspects the original project source snapshot; it does not assume edits from an earlier stage were applied.
  - effective platform: `1227859358166ebdf070c802a94bc3d37e5f430d3f57f52f7c9db345bcc2356e`; completeness `partial`; profile `none`
  - Composer policy: `0c155d2582c9452186059dbe78d0da2d730f798c6c1d69f356c43ca832fcf1b9`; mode `compatible`; stage duration `1 ms`
  - stage evidence: `laravel-stage-target-1`, `stage-attempt-1`, `stage-root-change-1`, `laravel-framework-constraint-1`
  - state chain: predecessor `346f31120fbaf2d82c1e7d61829cc75a5c31dd1472f214f3f550a3875d7f50ad`; input `346f31120fbaf2d82c1e7d61829cc75a5c31dd1472f214f3f550a3875d7f50ad`; output `aa4d3f2e881fd081c216f38d69d55bd824fac92eae7023c645b16fdcb05cc434`
  - attempt `1` `target_only`: outcome `success`; duration `1 ms`; selected yes; blockers `none`
    - analyzer-only root change `laravel/framework`: `^7.0` -> `^8.0`
  - selected package change `laravel/framework`: `v7.30.7` -> `v8.83.27`
  - original-source finding (`high`): Update the root laravel/framework constraint from `^7.0` to a constraint compatible with Laravel 8.
  - blocker references: `none`
  - source-impact references: `none`
  - risk for `laravel-7-to-8`: `high`
  - effort: 4-13 hours (`low` confidence)
  - action: [laravel-7-to-8] Reproduce and review only the selected Composer candidate state before advancing.
  - action: [laravel-7-to-8] Review the original-source finding: Update the root laravel/framework constraint from `^7.0` to a constraint compatible with Laravel 8.
  - test for `laravel-7-to-8`: Validate the stage laravel-7-to-8 manifest. (`required`)
  - test for `laravel-7-to-8`: Run the project test suite for stage laravel-7-to-8 after applying its evidenced changes. (`required`)
  - test for `laravel-7-to-8`: Validate stage laravel-7-to-8 against analysis PHP 8.0.0 and its recorded platform. (`required`)
  - test for `laravel-7-to-8`: Exercise the original-snapshot findings correlated with stage laravel-7-to-8. (`recommended`)
- Staged source-impact registry:
  - None recorded.
- Blocker registry:
  - None recorded.

## Package Changes
- `laravel/framework`: upgraded `v7.30.7` -> `v8.83.27` (direct dependency; major-version jump; families: laravel)
  - source reference: `-` -> `-`
  - dist reference: `-` -> `-`

## Framework Transition Guidance
- `laravel`: `supported` (`7` -> `8`; evidence: `laravel-transition-1`)
  - hop `7` -> `8`: `supported`; rule pack `laravel-7-to-8` (evidence: `laravel-transition-1`)

## Root Constraint Changes
- `laravel/framework`: updated `^7.0` -> `^8.0`. The declared root constraint differs from the requested target. (evidence: `root-constraint-1`)

## Blockers
- None detected.

## Source Inventory
- None detected.

## Actionable Source Impact
- None detected.

## Framework Findings
- `laravel` `high`: Update the root laravel/framework constraint from `^7.0` to a constraint compatible with Laravel 8. (evidence: `laravel-framework-constraint-1`)
  - applies to hops: `7 -> 8`

## Staged Plan
1. **laravel-7-to-8** — Apply only the selected laravel-7-to-8 candidate, then validate before advancing.; executed stage `laravel-7-to-8` (evidence: `stage-plan-1`, `laravel-stage-target-1`, `stage-attempt-1`, `stage-root-change-1`, `laravel-framework-constraint-1`)
   - [laravel-7-to-8] Reproduce and review only the selected Composer candidate state before advancing.
   - [laravel-7-to-8] Review the original-source finding: Update the root laravel/framework constraint from `^7.0` to a constraint compatible with Laravel 8.
   - [laravel-7-to-8] composer-validation: Validate the stage laravel-7-to-8 manifest.
   - [laravel-7-to-8] project-test-suite: Run the project test suite for stage laravel-7-to-8 after applying its evidenced changes.
   - [laravel-7-to-8] platform-requirements: Validate stage laravel-7-to-8 against analysis PHP 8.0.0 and its recorded platform.
   - [laravel-7-to-8] focused-regressions: Exercise the original-snapshot findings correlated with stage laravel-7-to-8.

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
  - Aggregate effort counts each exact package transition, framework finding, and source occurrence once; scenario and repeated-hop counts are excluded.

## Test Guidance
- **composer-validation** (`required`): Validate the edited Composer manifest before dependency installation. Command: `composer validate --strict`.
- **project-test-suite** (`required`): Identify and run the project test suite; no Composer test script was detected. Command: project-specific command required.
- **platform-requirements** (`required`): Confirm the installed dependencies satisfy PHP 8.0.0 and the deployment extensions. Command: `composer check-platform-reqs`.
- **focused-regressions** (`recommended`): Add or run focused regression coverage for the reported source and framework findings. Command: project-specific command required.

## Uncertainties
- No PHP source files were scanned.
- Dependency resolution does not prove application runtime compatibility; the project test suite must run on the target runtime.
- No Composer "test" script was found, so the project's canonical test command is unknown.
- Composer extension checks used the analyzer runtime because no complete explicit extension platform was supplied.
- Compatible Composer execution may inherit global configuration, credentials, proxies, caches, repository access, and other analyzer-host state.

## Evidence
- `laravel-stage-target-1` (`E4`, high confidence): Laravel adapter metadata supplies the exact package target for stage 7 to 8. Context: `{"stage_id":"laravel-7-to-8","package":"laravel/framework","constraint":"^8.0","analysis_php":"8.0.0","minimum_php_constraint":"^7.3|^8.0","analysis_php_provenance":"final_target_php_exact_value_checked_against_adapter_constraint","sources":["https://laravel.com/docs/8.x/upgrade"]}`
- `stage-attempt-1` (`E1`, high confidence): Executed Composer attempt 1 for stage laravel-7-to-8. Context: `{"stage_id":"laravel-7-to-8","attempt":1,"strategy":"target_only","scenario":"laravel-7-to-8-attempt-1-target_only","outcome":"success"}`
- `stage-root-change-1` (`E2`, high confidence): Recorded an analyzer-only root constraint change for stage laravel-7-to-8. Context: `{"stage_id":"laravel-7-to-8","package":"laravel/framework","from_constraint":"^7.0","to_constraint":"^8.0","supporting_evidence":["laravel-stage-target-1"]}`
- `laravel-transition-1` (`E4`, medium confidence): The retained Laravel 7 to 8 rule pack covers this requested transition. Context: `{"source_major":7,"target_major":8,"rule_pack":"laravel-7-to-8","source":"https://laravel.com/docs/8.x/upgrade"}`
- `laravel-framework-constraint-1` (`E2`, high confidence): The root Laravel framework constraint does not include the requested target major. Context: `{"package":"laravel/framework","root_constraint":"^7.0","target_constraint":"^8.0","target_laravel_major":8}`
- `root-constraint-1` (`E2`, high confidence): Compared the root requirement for laravel/framework with the requested target. Context: `{"package":"laravel/framework","from_constraint":"^7.0","to_constraint":"^8.0"}`
- `stage-plan-1` (`E5`, medium confidence): Generated recommendations from the executed outcome of stage laravel-7-to-8. Context: `{"stage_id":"laravel-7-to-8","execution_state":"evaluated","resolution_status":"feasible_with_changes","transition_recommended":true}`
