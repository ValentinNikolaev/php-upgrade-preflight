# PHP Upgrade Preflight Report

Resolution: **blocked** | Staged: **blocked** | Schema: `0.8` | Tool: `php-upgrade-preflight 0.3.1`

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
- Locked packages: `2`
- Root requirements:
  - `php`: `^7.4 || ^8.0`
  - `laravel/framework`: `^7.0`
  - `fixture/illuminate-consumer`: `1.0.0`

## Composer Scenarios
- `baseline-validation`: succeeded (outcome `success`, Composer `unknown`, duration `1 ms`, exit `0`, failure type `none`)
  - command argv: `["composer","validate","--check-lock","--no-check-publish","--no-scripts","--no-plugins","--no-interaction"]`
  - temporary workspace: `not preserved`
  - stdout excerpt:
    ```text
    Fixture baseline is valid.
    ```
  - stderr excerpt: *(empty)*
  - candidate lock: SHA-256 `929e695e51443279a4e6ade5ffa688d20770ccd6b2a3753debfcaacd2598d7e9`, content hash `blocked-illuminate-constraint`, packages `2`
  - diagnostics: none
- `exact-target`: failed (outcome `solver_failure`, Composer `unknown`, duration `1 ms`, exit `2`, failure type `solver`)
  - command argv: `["composer","update","laravel/framework","--no-scripts","--no-plugins","--no-install","--no-audit","--no-progress","--no-interaction"]`
  - temporary workspace: `not preserved`
  - stdout excerpt: *(empty)*
  - stderr excerpt:
    ```text
    Your requirements could not be resolved to an installable set of packages.
    - fixture/illuminate-consumer 1.0.0 requires illuminate/support ^7.0 -> found illuminate/support[v7.30.7] but it conflicts with the requested Laravel target.
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
    - fixture/illuminate-consumer 1.0.0 requires illuminate/support ^7.0 -> found illuminate/support[v7.30.7] but it conflicts with the requested Laravel target.
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
    - fixture/illuminate-consumer 1.0.0 requires illuminate/support ^7.0 -> found illuminate/support[v7.30.7] but it conflicts with the requested Laravel target.
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
    - fixture/illuminate-consumer 1.0.0 requires illuminate/support ^7.0 -> found illuminate/support[v7.30.7] but it conflicts with the requested Laravel target.
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
    - fixture/illuminate-consumer 1.0.0 requires illuminate/support ^7.0 -> found illuminate/support[v7.30.7] but it conflicts with the requested Laravel target.
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
  - stage evidence: `laravel-stage-target-1`, `stage-attempt-1`, `stage-root-change-1`, `stage-attempt-2`, `stage-root-change-2`, `old-illuminate-consumer-1`, `solver-6`, `solver-7`
  - state chain: predecessor `9077f3f300759abb9eaf54aa09d5aa6acd5d3c9c3a58257baf11c019b3f32425`; input `9077f3f300759abb9eaf54aa09d5aa6acd5d3c9c3a58257baf11c019b3f32425`; output `none`
  - attempt `1` `target_only`: outcome `solver_failure`; duration `1 ms`; selected no; blockers `stage-blocker-a3bd37c51e9a796aef74`
    - analyzer-only root change `laravel/framework`: `^7.0` -> `^8.0`
  - attempt `2` `locked_package_remediation`: outcome `solver_failure`; duration `1 ms`; selected no; blockers `stage-blocker-a3bd37c51e9a796aef74`
    - analyzer-only root change `laravel/framework`: `^7.0` -> `^8.0`
  - original-source finding (`high`): Update or replace incompatible illuminate/support constraints before targeting Laravel 8: fixture/illuminate-consumer.
  - blocker references: `stage-blocker-a3bd37c51e9a796aef74`
  - source-impact references: `none`
  - risk for `laravel-7-to-8`: `high`
  - effort: 6-19 hours (`low` confidence)
  - action: [laravel-7-to-8] Resolve every active blocker and rerun this complete stage; do not advance.
  - action: [laravel-7-to-8] Upgrade or replace `fixture/illuminate-consumer`.
  - action: [laravel-7-to-8] Choose a `illuminate/support` version compatible with the transitive constraint.
  - test for `laravel-7-to-8`: Resolve this stage stop condition, then rerun the complete Composer stage laravel-7-to-8. (`required`)
  - stop reason: `blocking_registry_not_cleared`
- **laravel-8-to-9** (`8` -> `9`): execution `skipped`; resolution `not evaluated`; selected attempt `none`
  - analysis PHP: `8.1.0`; source snapshot: `original_project`
  - This stage assessment inspects the original project source snapshot; it does not assume edits from an earlier stage were applied.
  - effective platform: `c2b227c251d4ec9ccf885e95404feaceedd627ff0c7104bc213f254935e62537`; completeness `partial`; profile `none`
  - Composer policy: `0c155d2582c9452186059dbe78d0da2d730f798c6c1d69f356c43ca832fcf1b9`; mode `compatible`; stage duration `1 ms`
  - stage evidence: `laravel-stage-target-2`, `stage-skipped-1`, `laravel-framework-constraint-1`
  - state chain: predecessor `none`; input `none`; output `none`
  - original-source finding (`high`): Update the root laravel/framework constraint from `^7.0` to a constraint compatible with Laravel 9.
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
  - `stage-blocker-a3bd37c51e9a796aef74` stage `laravel-7-to-8`: `transitive-package-conflict` `illuminate/support`; lifecycle `persists` (detected@1 -> persists@2); blocking package `fixture/illuminate-consumer`; constraint `^7.0`; path `fixture/illuminate-consumer -> illuminate/support`

## Package Changes
- No lockfile changes detected.

## Framework Transition Guidance
- `laravel`: `supported` (`7` -> `9`; evidence: `laravel-transition-1`, `laravel-transition-2`)
  - hop `7` -> `8`: `supported`; rule pack `laravel-7-to-8` (evidence: `laravel-transition-1`)
  - hop `8` -> `9`: `supported`; rule pack `laravel-8-to-9` (evidence: `laravel-transition-2`)

## Root Constraint Changes
- `laravel/framework`: updated `^7.0` -> `^9.0`. The declared root constraint differs from the requested target. (evidence: `root-constraint-1`)

## Blockers
- `transitive-package-conflict` `illuminate/support`: A transitive package constraint blocks the requested target. (high confidence; evidence: `solver-1`, `solver-2`, `solver-3`, `solver-4`, `solver-5`)
  - requested `-`; blocker `fixture/illuminate-consumer`; locked `1.0.0`; conflict `^7.0`
  - dependency path: `fixture/illuminate-consumer -> illuminate/support`
  - option: Upgrade or replace `fixture/illuminate-consumer`.
  - option: Choose a `illuminate/support` version compatible with the transitive constraint.

## Source Inventory
- None detected.

## Actionable Source Impact
- None detected.

## Framework Findings
- `laravel` `high`: Update the root laravel/framework constraint from `^7.0` to a constraint compatible with Laravel 9. (evidence: `laravel-framework-constraint-1`)
  - applies to hops: `8 -> 9`
- `laravel` `high`: Update or replace incompatible illuminate/support constraints before targeting Laravel 8: fixture/illuminate-consumer. (evidence: `old-illuminate-consumer-1`)
  - applies to hops: `7 -> 8`

## Staged Plan
1. **laravel-7-to-8** — Stop at laravel-7-to-8; its transition is not proved and must be rerun.; executed stage `laravel-7-to-8` (evidence: `stage-plan-1`, `laravel-stage-target-1`, `stage-attempt-1`, `stage-root-change-1`, `stage-attempt-2`, `stage-root-change-2`, `old-illuminate-consumer-1`, `solver-6`, `solver-7`)
   - [laravel-7-to-8] Resolve every active blocker and rerun this complete stage; do not advance.
   - [laravel-7-to-8] Upgrade or replace `fixture/illuminate-consumer`.
   - [laravel-7-to-8] Choose a `illuminate/support` version compatible with the transitive constraint.

## Risk And Effort
- Risk: `high`
- Risk drivers:
  - Composer resolution is blocked.
  - Framework compatibility findings require review.
  - Executed stage laravel-7-to-8 retains an active Composer blocker.
- Effort: `6-20` hours (low confidence)
- Effort components:
  - `dependency_resolution`: `3-8` hours
  - `source_changes`: `1-4` hours
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
- No Composer "test" script was found, so the project's canonical test command is unknown.
- Composer extension checks used the analyzer runtime because no complete explicit extension platform was supplied.
- Compatible Composer execution may inherit global configuration, credentials, proxies, caches, repository access, and other analyzer-host state.

## Evidence
- `solver-1` (`E1`, high confidence): Composer scenario "exact-target" failed. Context: `{"scenario":"exact-target","targets":[{"package":"laravel/framework","constraint":"^9.0"},{"package":"php","constraint":"8.1.0"}],"exit_code":2,"output_excerpt":"Your requirements could not be resolved to an installable set of packages.\n- fixture/illuminate-consumer 1.0.0 requires illuminate/support ^7.0 -> found illuminate/support[v7.30.7] but it conflicts with the requested Laravel target.","diagnostics":[{"package":"laravel/framework","constraint":"^9.0","command":["composer","prohibits","laravel/framework","^9.0","--tree","--locked","--no-scripts","--no-plugins","--no-interaction"],"exit_code":1,"outcome":"success","stdout_excerpt":"","stderr_excerpt":"No additional fixture diagnostic."},{"package":"php","constraint":"8.1.0","command":["composer","prohibits","php","8.1.0","--tree","--locked","--no-scripts","--no-plugins","--no-interaction"],"exit_code":1,"outcome":"success","stdout_excerpt":"","stderr_excerpt":"No additional fixture diagnostic."}]}`
- `solver-2` (`E1`, high confidence): Composer scenario "target-with-all-dependencies" failed. Context: `{"scenario":"target-with-all-dependencies","targets":[{"package":"laravel/framework","constraint":"^9.0"},{"package":"php","constraint":"8.1.0"}],"exit_code":2,"output_excerpt":"Your requirements could not be resolved to an installable set of packages.\n- fixture/illuminate-consumer 1.0.0 requires illuminate/support ^7.0 -> found illuminate/support[v7.30.7] but it conflicts with the requested Laravel target.","diagnostics":[{"package":"laravel/framework","constraint":"^9.0","command":["composer","prohibits","laravel/framework","^9.0","--tree","--locked","--no-scripts","--no-plugins","--no-interaction"],"exit_code":1,"outcome":"success","stdout_excerpt":"","stderr_excerpt":"No additional fixture diagnostic."},{"package":"php","constraint":"8.1.0","command":["composer","prohibits","php","8.1.0","--tree","--locked","--no-scripts","--no-plugins","--no-interaction"],"exit_code":1,"outcome":"success","stdout_excerpt":"","stderr_excerpt":"No additional fixture diagnostic."}]}`
- `solver-3` (`E1`, high confidence): Composer scenario "minimal-changes" failed. Context: `{"scenario":"minimal-changes","targets":[{"package":"laravel/framework","constraint":"^9.0"},{"package":"php","constraint":"8.1.0"}],"exit_code":2,"output_excerpt":"Your requirements could not be resolved to an installable set of packages.\n- fixture/illuminate-consumer 1.0.0 requires illuminate/support ^7.0 -> found illuminate/support[v7.30.7] but it conflicts with the requested Laravel target.","diagnostics":[{"package":"laravel/framework","constraint":"^9.0","command":["composer","prohibits","laravel/framework","^9.0","--tree","--locked","--no-scripts","--no-plugins","--no-interaction"],"exit_code":1,"outcome":"success","stdout_excerpt":"","stderr_excerpt":"No additional fixture diagnostic."},{"package":"php","constraint":"8.1.0","command":["composer","prohibits","php","8.1.0","--tree","--locked","--no-scripts","--no-plugins","--no-interaction"],"exit_code":1,"outcome":"success","stdout_excerpt":"","stderr_excerpt":"No additional fixture diagnostic."}]}`
- `solver-4` (`E1`, high confidence): Composer scenario "target-platform-only" failed. Context: `{"scenario":"target-platform-only","targets":[{"package":"php","constraint":"8.1.0"}],"exit_code":2,"output_excerpt":"Your requirements could not be resolved to an installable set of packages.\n- fixture/illuminate-consumer 1.0.0 requires illuminate/support ^7.0 -> found illuminate/support[v7.30.7] but it conflicts with the requested Laravel target.","diagnostics":[{"package":"php","constraint":"8.1.0","command":["composer","prohibits","php","8.1.0","--tree","--locked","--no-scripts","--no-plugins","--no-interaction"],"exit_code":1,"outcome":"success","stdout_excerpt":"","stderr_excerpt":"No additional fixture diagnostic."}]}`
- `solver-5` (`E1`, high confidence): Composer scenario "staged-targets" failed. Context: `{"scenario":"staged-targets","targets":[{"package":"laravel/framework","constraint":"^9.0"},{"package":"php","constraint":"7.4.0"}],"exit_code":2,"output_excerpt":"Your requirements could not be resolved to an installable set of packages.\n- fixture/illuminate-consumer 1.0.0 requires illuminate/support ^7.0 -> found illuminate/support[v7.30.7] but it conflicts with the requested Laravel target.","diagnostics":[{"package":"laravel/framework","constraint":"^9.0","command":["composer","prohibits","laravel/framework","^9.0","--tree","--locked","--no-scripts","--no-plugins","--no-interaction"],"exit_code":1,"outcome":"success","stdout_excerpt":"","stderr_excerpt":"No additional fixture diagnostic."}]}`
- `laravel-stage-target-1` (`E4`, high confidence): Laravel adapter metadata supplies the exact package target for stage 7 to 8. Context: `{"stage_id":"laravel-7-to-8","package":"laravel/framework","constraint":"^8.0","analysis_php":"8.1.0","minimum_php_constraint":"^7.3|^8.0","analysis_php_provenance":"final_target_php_exact_value_checked_against_adapter_constraint","sources":["https://laravel.com/docs/8.x/upgrade"]}`
- `laravel-stage-target-2` (`E4`, high confidence): Laravel adapter metadata supplies the exact package target for stage 8 to 9. Context: `{"stage_id":"laravel-8-to-9","package":"laravel/framework","constraint":"^9.0","analysis_php":"8.1.0","minimum_php_constraint":"^8.0.2","analysis_php_provenance":"final_target_php_exact_value_checked_against_adapter_constraint","sources":["https://laravel.com/docs/9.x/upgrade"]}`
- `stage-attempt-1` (`E1`, high confidence): Executed Composer attempt 1 for stage laravel-7-to-8. Context: `{"stage_id":"laravel-7-to-8","attempt":1,"strategy":"target_only","scenario":"laravel-7-to-8-attempt-1-target_only","outcome":"solver_failure"}`
- `solver-6` (`E1`, high confidence): Composer scenario "laravel-7-to-8-attempt-1-target_only" failed. Context: `{"scenario":"laravel-7-to-8-attempt-1-target_only","targets":[{"package":"laravel/framework","constraint":"^8.0"},{"package":"php","constraint":"8.1.0"}],"exit_code":2,"output_excerpt":"Your requirements could not be resolved to an installable set of packages.\n- fixture/illuminate-consumer 1.0.0 requires illuminate/support ^7.0 -> found illuminate/support[v7.30.7] but it conflicts with the requested Laravel target.","diagnostics":[{"package":"laravel/framework","constraint":"^8.0","command":["composer","prohibits","laravel/framework","^8.0","--tree","--locked","--no-scripts","--no-plugins","--no-interaction"],"exit_code":1,"outcome":"success","stdout_excerpt":"","stderr_excerpt":"No additional fixture diagnostic."},{"package":"php","constraint":"8.1.0","command":["composer","prohibits","php","8.1.0","--tree","--locked","--no-scripts","--no-plugins","--no-interaction"],"exit_code":1,"outcome":"success","stdout_excerpt":"","stderr_excerpt":"No additional fixture diagnostic."}]}`
- `stage-root-change-1` (`E2`, high confidence): Recorded an analyzer-only root constraint change for stage laravel-7-to-8. Context: `{"stage_id":"laravel-7-to-8","package":"laravel/framework","from_constraint":"^7.0","to_constraint":"^8.0","supporting_evidence":["laravel-stage-target-1"]}`
- `stage-attempt-2` (`E1`, high confidence): Executed Composer attempt 2 for stage laravel-7-to-8. Context: `{"stage_id":"laravel-7-to-8","attempt":2,"strategy":"locked_package_remediation","scenario":"laravel-7-to-8-attempt-2-locked_package_remediation","outcome":"solver_failure"}`
- `solver-7` (`E1`, high confidence): Composer scenario "laravel-7-to-8-attempt-2-locked_package_remediation" failed. Context: `{"scenario":"laravel-7-to-8-attempt-2-locked_package_remediation","targets":[{"package":"laravel/framework","constraint":"^8.0"},{"package":"php","constraint":"8.1.0"}],"exit_code":2,"output_excerpt":"Your requirements could not be resolved to an installable set of packages.\n- fixture/illuminate-consumer 1.0.0 requires illuminate/support ^7.0 -> found illuminate/support[v7.30.7] but it conflicts with the requested Laravel target.","diagnostics":[{"package":"laravel/framework","constraint":"^8.0","command":["composer","prohibits","laravel/framework","^8.0","--tree","--locked","--no-scripts","--no-plugins","--no-interaction"],"exit_code":1,"outcome":"success","stdout_excerpt":"","stderr_excerpt":"No additional fixture diagnostic."},{"package":"php","constraint":"8.1.0","command":["composer","prohibits","php","8.1.0","--tree","--locked","--no-scripts","--no-plugins","--no-interaction"],"exit_code":1,"outcome":"success","stdout_excerpt":"","stderr_excerpt":"No additional fixture diagnostic."}]}`
- `stage-root-change-2` (`E2`, high confidence): Recorded an analyzer-only root constraint change for stage laravel-7-to-8. Context: `{"stage_id":"laravel-7-to-8","package":"laravel/framework","from_constraint":"^7.0","to_constraint":"^8.0","supporting_evidence":["laravel-stage-target-1"]}`
- `stage-skipped-1` (`E1`, high confidence): Stage laravel-8-to-9 was skipped after the preceding stage stopped the candidate-state chain. Context: `{"stage_id":"laravel-8-to-9","preceding_status":"blocked","reason":"previous_stage_blocked"}`
- `laravel-transition-1` (`E4`, medium confidence): The retained Laravel 7 to 8 rule pack covers this requested transition. Context: `{"source_major":7,"target_major":8,"rule_pack":"laravel-7-to-8","source":"https://laravel.com/docs/8.x/upgrade"}`
- `laravel-transition-2` (`E4`, medium confidence): The implemented Laravel 8 to 9 rule pack covers this requested transition. Context: `{"source_major":8,"target_major":9,"rule_pack":"laravel-8-to-9","source":"https://laravel.com/docs/9.x/upgrade"}`
- `laravel-framework-constraint-1` (`E2`, high confidence): The root Laravel framework constraint does not include the requested target major. Context: `{"package":"laravel/framework","root_constraint":"^7.0","target_constraint":"^9.0","target_laravel_major":9}`
- `old-illuminate-consumer-1` (`E2`, high confidence): fixture/illuminate-consumer declares an illuminate/support constraint that excludes the requested Laravel 8 range. Context: `{"package":"fixture/illuminate-consumer","locked_version":"1.0.0","illuminate_support_constraint":"^7.0","target_laravel_major":8}`
- `root-constraint-1` (`E2`, high confidence): Compared the root requirement for laravel/framework with the requested target. Context: `{"package":"laravel/framework","from_constraint":"^7.0","to_constraint":"^9.0"}`
- `stage-plan-1` (`E5`, low confidence): Generated recommendations from the executed outcome of stage laravel-7-to-8. Context: `{"stage_id":"laravel-7-to-8","execution_state":"evaluated","resolution_status":"blocked","transition_recommended":false}`
