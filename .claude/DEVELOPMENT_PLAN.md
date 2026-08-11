# PHP Upgrade Preflight Development Plan

Last updated: 2026-08-11

- Active tool/package target: `0.3.0`
- Released baseline: `0.2.1`
- Released report schema: `0.7`
- Planned v0.3 report schema: `0.8`

This roadmap supersedes the archived [v0.2.0 implementation plan](DEVELOPMENT_PLAN_0.2.0.md), which also records the v0.2.1 release-hardening closeout. The archive was copied from the completed plan before this file was replaced.

v0.3 turns the v0.2 final-target preflight into a reproducible staged analysis. It should accept an explicit target platform, run Composer for each evidence-backed framework hop, and tie package changes, blockers, source impact, risk, effort, and recommended actions to the stage that produced them. It remains an analyzer, not an upgrade executor.

## How to Use This Plan

- Continue the first unchecked item in the earliest incomplete milestone unless repository evidence requires a safer order.
- Mark work `[~]` only while someone is implementing it. Mark it `[x]` only after the acceptance evidence passes.
- Reconcile this plan in the same change whenever roadmap work is completed, partially completed, reopened, or reverted.
- Keep v0.2.x work limited to security fixes, regressions, dependency maintenance, documentation corrections, and release-process repairs. Put new inputs, schema fields, staged solving, and adapter contracts in v0.3.
- Do not switch development aliases, internal constraints, report identity, release branches, or release-verifier policy piecemeal. Milestone 0 owns that coordinated migration.
- Recheck external release and package state before acting. Local Git state cannot prove that GitHub, distribution repositories, or Packagist did not change later.
- Update public documentation in the same change as behavior, commands, report semantics, supported versions, trust boundaries, or release policy.

## Version and Contract Vocabulary

Tool/package versions and report-schema versions are independent:

| Contract | Current released state | v0.3 direction |
| --- | --- | --- |
| Tool and package line | `0.2.1` release; `0.2.x-dev` aliases; `^0.2` internal constraints | `0.3.0` release; v0.3 development identity locked and activated in Milestone 0 |
| Canonical report | Schema `0.7` | New schema `0.8` for staged results |
| Active release policy | `0.2.x` from `main` | Preserve `0.2.x` on its maintenance branch before enabling `0.3.x` from `main` |

The existing [`upgrade-report-v0.3.schema.json`](../packages/core/resources/schema/upgrade-report-v0.3.schema.json) is a historical report schema and is checksum-locked. It is unrelated to tool/package v0.3.0 and must never be reused or rewritten. Every historical schema and signed compatibility artifact remains immutable.

Package releases continue to derive exact versions from matching Git tags rather than manifest `version` fields. Core, CLI, and Laravel continue to release in lockstep.

## Released v0.2.1 Baseline

The published baseline is documented in the [v0.2.0 release notes](../docs/releases/v0.2.0.md), [v0.2.1 release notes](../docs/releases/v0.2.1.md), and [v0.2 contract](../docs/v0.2-contract.md).

- Schema `0.7` separates platform provenance, raw source inventory, actionable source impact, and framework guidance from final-target Composer feasibility.
- Laravel guidance covers 7→8, the retained direct 7→9 path, and every adjacent hop from 8→9 through 12→13.
- Composer metadata can discover third-party adapters without CLI source changes.
- Shareable reports redact supported credential forms and local roots; target projects remain byte-for-byte immutable.
- The supported external execution path is a separate Composer tools-directory installation. v0.2 does not publish a PHAR or versioned runtime container.
- Deterministic, compatibility, privacy, coverage, mutation, supply-chain, archive, signed-tag, and published-package gates protect the released line.

The v0.2.0 release workflow completed all 36 jobs; v0.2.1 then closed the published-reference integrity gap without changing schema `0.7`.

The current version values and release policy remain authoritative until Milestone 0 performs and verifies the coordinated development-line migration. Creating this roadmap does not itself authorize that switch.

## v0.3 Evidence and Gap Map

| Gap | Repository evidence | Roadmap response |
| --- | --- | --- |
| Unlisted extensions still come from the analyzer host | [Schema platform provenance](../docs/schema.md) and [limitations](../docs/limitations.md) state that v0.2 inputs are only partial | Milestone 1 |
| Composer solves the requested final target, not each framework hop | The [v0.2 contract](../docs/v0.2-contract.md) explicitly labels hops as guidance without feasibility | Milestone 3 |
| Package changes and source impact describe only the selected final-target lock | [Limitations](../docs/limitations.md) exclude intermediate-hop package predictions | Milestone 4 |
| Framework adapters cannot contribute concrete staged Composer targets | [`FrameworkTransitionProvider`](../packages/core/src/Framework/FrameworkTransitionProvider.php) assesses guidance only | Milestones 0, 3, and 5 |
| The v0.2 contract test also asserts live development and release identity | [`V02ContractTest`](../tests/Release/V02ContractTest.php) mixes historical compatibility with active-series policy | Milestone 0 |
| More Composer processes increase host, network, credential, time, and report-size variance | [`ComposerScenarioRunner`](../packages/core/src/Composer/ComposerScenarioRunner.php) currently owns fixed executable, environment, and timeout behavior | Milestones 2 and 6 |

## Release Targets

### v0.2.x stabilization

Keep schema `0.7`, the public PHP operation, CLI and Artisan behavior, adapter metadata, exit policy, and supported transition claims compatible. Before `main` adopts v0.3 development identity, establish the approved v0.2.x maintenance branch and release policy so urgent patch work remains possible without backporting v0.3 behavior.

### v0.3.0

v0.3.0 should deliver reproducible staged upgrade analysis:

- a versioned target-platform profile with honest partial and complete semantics;
- explicit Composer execution provenance plus a restricted Composer mode with sanitized configuration and environment, best-effort offline behavior, and clearly stated residual process/OS boundaries;
- actual isolated Composer evidence for every reported feasible framework stage;
- stage-scoped package changes, blockers, source impact, risk, effort, and plan actions;
- an optional stage-target adapter contract that preserves the existing required interfaces and documents source-level migration across the `0.MINOR` boundary;
- schema `0.8` with a documented `0.7` migration;
- the existing PHP `^8.0` runtime floor and the three-package release set.

Symfony and CodeIgniter are not v0.3 release deliverables. v0.3 must first prove the staged contract with Laravel and the test-only third-party adapter. Symfony is the first adapter candidate after that contract has production evidence.

## v0.3 Scope and Non-Goals

In scope:

- complete and partial target-platform profiles;
- framework-neutral stage planning and bounded sequential Composer scenarios;
- Laravel stage targets for the already supported transition matrix;
- global source inventory with stage-scoped actionable correlations;
- source/interface compatibility for old-style adapter implementations re-released with a v0.3-compatible Composer constraint, plus new-adapter conformance tests;
- schema, CLI, Artisan, documentation, quality, release, and migration work required by those changes.

Out of scope:

- modifying application source, `composer.json`, `composer.lock`, or `vendor/`;
- applying one stage's proposed changes before scanning later stages;
- pull-request creation, hosted uploads, dashboards, telemetry, or SaaS storage;
- AI-generated compatibility claims or migration instructions;
- booting or executing the analyzed application during deterministic analysis;
- claiming runtime compatibility from Composer success;
- a Symfony or CodeIgniter package;
- a PHP language/API deprecation catalog beyond Composer platform evidence;
- PHAR or versioned container delivery;
- raising the shared runtime floor above PHP `^8.0`;
- perfect dynamic symbol, container, or runtime autoload resolution.

## Inherited Product and Test Rules

- Composer remains the dependency solver. Never infer a successful stage without running it.
- Treat the analyzed project as immutable input. Run every mutation in an analyzer-owned temporary workspace.
- Keep core framework-neutral. Framework packages own detection, targets, rule catalogs, source defaults, and package families.
- Keep JSON canonical, Markdown derived, evidence IDs deterministic, and unsupported claims explicit as uncertainty.
- Keep source inspection static and parser-based. Later-stage findings inspect the original source snapshot and must say so.
- Preserve the public semantic operation `UpgradeAnalyzer::analyzeUpgrade(UpgradeRequest): UpgradeReport`; do not add separate public scan, solve, or estimate commands.
- Keep report privacy and credential redaction at model ingress and every publication boundary.

Maintain four test layers:

1. Offline unit tests for one model, phase, rule, or stop condition.
2. Deterministic Composer integration tests backed by committed local `path` repositories.
3. Curated application-shaped fixtures with immutability and JSON-first approval assertions.
4. Networked installation and live-application smoke tests that run separately from the deterministic gate.

## Milestone 0: Freeze v0.2.1 and Lock the v0.3 Contract

Priority: P0. Complete before changing production report shape or active development identity.

- [ ] Archive signed v0.2.1 canonical reports and the public PHP, CLI, Artisan, adapter-metadata, exit-policy, schema `0.7`, and Laravel-transition behavior needed for immutable compatibility checks.
- [ ] Split historical v0.2 compatibility assertions from live development-version and release-policy assertions. Do not weaken or search-and-replace [`V02ContractTest`](../tests/Release/V02ContractTest.php).
- [ ] Add a machine-readable v0.3 contract and dedicated tests for every new status, field, ordering rule, stop condition, compatibility promise, and budget.
- [ ] Define direct final-target resolution, framework guidance coverage, and staged Composer resolution as separate report dimensions. None may silently upgrade another.
- [ ] Define stage execution states such as `evaluated` and `skipped` separately from resolution statuses (`feasible`, `feasible_with_changes`, `blocked`, and `unknown`), including behavior after a missing target, ambiguous transition, guidance gap, solver blocker, timeout, or operational failure.
- [ ] Lock how a stage receives exact package targets and an exact analysis PHP value from request evidence and adapter metadata. Never turn a minimum PHP constraint into an unexplained deployment claim.
- [ ] Approve schema `0.8` and its `0.7` migration, then add the immutable schema file and minimal canonical serialization fixture before Milestone 1 emits new fields. Preserve every historical schema and report checksum.
- [ ] Define a versioned target-platform-profile contract, its partial/complete semantics, supported Composer platform-package classes, conflict rules with existing PHP and extension inputs, and the Composer-version capability policy.
- [ ] Require Composer 2.2 or newer for a complete closed-world profile. An older Composer must yield a canonical operationally unknown result and must not silently downgrade the request to partial coverage.
- [ ] Define an optional stage-target provider contract without adding methods to the required v0.2 adapter interfaces.
- [ ] Limit v0.3 staged solving to one active stage-target provider. If several active adapters provide stages, continue their ordinary rules but skip staged solving with deterministic conflict evidence.
- [ ] Set maximum hop and scenario counts plus per-stage and aggregate runtime, memory, report-size, redaction, and deterministic-ordering budgets.
- [ ] Record the decision to keep Symfony, CodeIgniter, PHAR, container, and runtime-floor changes out of v0.3.
- [ ] Create and protect the v0.2.x maintenance branch while the tree still carries its `0.2.x` verifier, aliases, and constraints.
- [ ] Atomically switch `main` to the approved v0.3 development report identity, schema `0.8`, `0.3.x-dev` aliases, `^0.3` internal constraints, and a verifier/workflow that permits only `0.3.x` from `main`.

Acceptance gate: immutable v0.2.1 evidence remains green, the maintenance branch can still verify `0.2.x`, and `main` identifies every subsequent feature build as v0.3 under schema `0.8` after a machine-checked contract defines the new input, execution, stage, compatibility, budget, and release policy.

Status: not started.

## Milestone 1: Complete Target-Platform Profiles

Priority: P0.

- [ ] Add immutable profile and platform-package models shared by the PHP API, CLI, Artisan command, scenario selection, report model, and writers.
- [ ] Accept exact target PHP and supported `ext-*`, `lib-*`, PHP-subtype, and Composer-platform values or absences. Explicitly classify toolchain-bound values that cannot be simulated safely.
- [ ] Retain the existing named partial assumptions and define deterministic precedence, matching-duplicate, contradiction, and mutual-exclusion behavior across profile input, request options, and project `config.platform`.
- [ ] Make `complete` a closed-world claim for the approved package classes: an unlisted value must be absent in temporary Composer state, never inherited silently from the analyzer host.
- [ ] Reject malformed, contradictory, or falsely complete profiles before running Composer.
- [ ] Apply profiles only to analyzer-owned temporary manifests and record profile schema, completeness, digest, provenance, and every effective value in canonical output.
- [ ] Keep exact profile paths behind the existing path-exposure policy and redact credentials or local roots in validation failures.
- [ ] Add offline fixtures for complete and partial profiles, host-only extensions, explicit libraries, PHP subtypes, absent values, version conflicts, project/request/profile precedence, and the pre-Composer-2.2 capability failure.
- [ ] Prove on Linux and Windows that equivalent complete profiles produce byte-identical normalized platform decisions despite different analyzer-host extensions.
- [ ] Keep network, repository metadata, and Composer executable differences outside the platform-completeness claim and report them separately.

Acceptance gate: on Composer 2.2 or newer, a complete profile removes analyzer-host inheritance for every platform-package class it claims; a partial profile remains visibly host-dependent; older Composer produces operational uncertainty; and no report claims broader reproducibility than the cross-host tests prove.

Status: not started.

## Milestone 2: Reproducible and Restricted Composer Execution

Priority: P1. Complete before multiplying Composer work per stage.

- [ ] Extract typed Composer execution configuration for executable selection, expected version range, scenario timeout, diagnostic timeout, environment mode, and network policy.
- [ ] Define the restricted-mode threat model: enumerate the Composer configuration, authentication, proxy, and environment sources the analyzer controls, and name user-selected executables, Git/SSH helpers, caches, and OS-level networking as residual boundaries unless separately isolated.
- [ ] Record redacted execution provenance including Composer version, policy mode, timeout policy, repository source mode, and whether global configuration or credentials may have been inherited.
- [ ] Preserve the current compatible execution mode for projects that require configured private repositories, while labeling its host and credential dependencies.
- [ ] Add an explicit restricted mode backed by analyzer-owned Composer home/configuration, scrub every credential and proxy source covered by the threat model, and request Composer's offline behavior. Do not describe it as an OS network sandbox.
- [ ] Treat unavailable repository metadata in restricted mode as operational uncertainty, not proof of dependency incompatibility.
- [ ] Keep scripts, plugins, installation, audit side effects, interaction, and progress disabled for analysis scenarios.
- [ ] Keep executable paths, environment values, authentication material, and private repository URLs behind the existing privacy boundary.
- [ ] Add deterministic tests for executable mismatch, missing Composer, timeout, offline cache hit/miss, empty global configuration, seeded credentials in every controlled source, and attempted network access through the instrumented Composer test path.

Acceptance gate: every report states enough non-secret execution context to interpret its solver evidence; restricted mode passes the documented Composer-layer credential and offline harness without claiming process/OS isolation; and compatible mode remains explicit about inherited state.

Status: not started.

## Milestone 3: Sequential Per-Stage Composer Solving

Priority: P0.

- [ ] Add the optional framework-neutral stage-target provider approved in Milestone 0 without changing the existing required interfaces. Old-style implementations remain source-compatible when re-released with a Core v0.3-compatible Composer constraint.
- [ ] Adapt the Laravel catalog to expose concrete adjacent package targets, evidence-backed PHP requirements, and stable stage IDs for every already supported hop, including the retained 7→8 foundation.
- [ ] Build a deterministic stage plan from the assessed contiguous framework path of the one active stage provider. Do not cross a provider conflict, ambiguous endpoint, missing hop, unsupported range, or post-gap rule pack.
- [ ] Apply the approved exact-stage-PHP selection rule using current PHP, final target PHP, and adapter evidence; emit uncertainty instead of guessing when no safe exact value exists.
- [ ] Run bounded isolated Composer strategies for the first stage from the original manifest, lock, effective platform, and execution policy, then build each later stage only from the preceding selected candidate project state.
- [ ] Preserve the existing direct final-target resolution independently so consumers can compare direct feasibility with staged feasibility.
- [ ] Stop at the first blocked, unknown, operationally failed, or unselectable stage and mark later stages skipped with evidence-backed reasons.
- [ ] Record each stage's targets, scenarios, selected result, root changes relative to the preceding state, package changes, blockers, platform, execution policy, duration, and evidence. Fingerprint the canonical input and output manifest, lock, effective platform, and execution policy as one candidate-project-state chain.
- [ ] Deduplicate diagnostics without merging evidence from different stages or allowing a later success to erase an earlier blocker.
- [ ] Cover feasible single- and multi-hop chains, a blocked middle hop, timeout, cleanup failure, missing hop, ambiguous source and target, modular Illuminate, direct 7→9, and deterministic scenario-cap behavior.
- [ ] Prove the original target fixture remains byte-for-byte unchanged for success, failure, timeout, and debug cleanup paths.

Acceptance gate: every reported feasible stage has its own Composer evidence, every later manifest/lock/platform/execution input is digest-linked to the selected preceding output, direct and staged results remain independent, and no result appears after a provider conflict or unresolved gap.

Status: not started.

## Milestone 4: Stage-Scoped Impact, Risk, Effort, and Schema 0.8

Priority: P0.

- [ ] Keep one deterministic raw `source_inventory` from the original project snapshot.
- [ ] Correlate source usages separately with each stage's selected package changes and applicable framework rules.
- [ ] Add stable stage references to actionable findings without duplicating exact occurrences or evidence records across stages.
- [ ] State on every later-stage source assessment that it inspects the original source snapshot, not hypothetical edits from earlier stages.
- [ ] Produce per-stage blockers, actions, tests, risk, and effort plus a conservative aggregate that does not double-count repeated findings.
- [ ] Build recommended plan stages from executed outcomes and stop recommendations at the first blocked, unknown, skipped, or missing stage.
- [ ] Keep direct-final package changes and impacts distinguishable from staged changes; neither representation may overwrite the other.
- [ ] Complete production population and validation of the strict schema `0.8` scaffold from Milestone 0, canonical snapshots, Markdown projection, evidence-integrity checks, and a consumer migration fixture from schema `0.7`.
- [ ] Preserve schema `0.7` and every historical snapshot byte-for-byte.
- [ ] Update the risk/effort estimator so scenario count alone does not inflate application-change estimates and repeated hop findings are bounded.

Acceptance gate: every action and estimate names the executed stage that supports it, aggregate values are deterministic and non-duplicative, the plan never recommends an unproved transition, and every schema `0.8` finding resolves to valid evidence.

Status: not started.

## Milestone 5: Adapter Conformance and Laravel Parity

Priority: P1.

- [ ] Extend the test-only third-party adapter with the optional stage-target contract and prove discovery still requires no CLI source registration.
- [ ] Add an old-style adapter fixture whose unchanged implementation uses only the v0.2 required interfaces but whose Composer constraint explicitly permits Core v0.3; do not claim that an adapter pinned to Core `^0.2` is install-compatible.
- [ ] Add conformance tests for stable stage IDs, exact target constraints, PHP requirement evidence, ordering, duplicate targets, conflicting providers, missing metadata, and invalid provider output.
- [ ] Prove old-style third-party adapter implementations with a v0.3-compatible constraint still load and contribute guidance without making staged-feasibility claims.
- [ ] Keep core opaque to Laravel package families, version semantics, and rule-catalog details.
- [ ] Preserve Laravel's supported guidance matrix and direct final-target behavior while adding staged evidence for every adjacent path.
- [ ] Keep generic CLI and Laravel Artisan canonical JSON parity for complete profiles, restricted execution, single-hop, multi-hop, blocked, and skipped cases.
- [ ] Publish adapter-author guidance for detection, guidance, optional stage targets, platform evidence, source scope, ordering, collisions, privacy, and conformance fixtures.
- [ ] Record Symfony as the first post-v0.3 candidate rather than adding a fourth package or distribution repository to this release.

Acceptance gate: an external adapter can contribute a deterministic staged plan without CLI changes, unchanged old-style source can migrate by widening or updating its Core constraint and remains honest about absent staged evidence, and Laravel's two entry points remain canonical-report equivalent.

Status: not started.

## Milestone 6: Quality, Performance, and Supply-Chain Hardening

Priority: P1.

- [ ] Add selective mutants for platform completeness, profile precedence, restricted execution, stage chaining, fingerprint validation, stop-on-gap behavior, aggregate de-duplication, old-adapter compatibility, and release-series policy.
- [ ] Continue the coverage ratchet and make new profile, execution-policy, stage-orchestration, and report-assembly classes critical modules.
- [ ] Raise production and full-repository PHPStan levels in measured steps without hiding new defects in the baseline.
- [ ] Keep Composer transcript coverage for every supported diagnostic version and separate parser drift from solver or repository drift.
- [ ] Enforce per-stage and worst-case supported-chain process, runtime, memory, report-size, redaction, and deterministic-rerun budgets on Linux and Windows.
- [ ] Bound scenario expansion by contract and fail with explicit uncertainty when a request exceeds the supported stage or process budget.
- [ ] Refactor phase boundaries if staged work would otherwise turn `ComposerScenarioRunner`, `DefaultUpgradeAnalyzer`, source-impact construction, or report assembly into orchestration monoliths.
- [ ] Retain dependency audits, commit-pinned actions, archive checksums, dependency inventory, provenance, signed distribution verification, secret canaries, and target-immutability gates.
- [ ] Preserve the PHP `^8.0` runtime floor and normal/lowest Laravel 8–13 host-installability matrix.

Acceptance gate: the worst supported staged request is bounded, deterministic, private, mutation-protected, and reliable across supported hosts without weakening any existing quality or supply-chain gate.

Status: not started.

## Milestone 7: v0.3 Documentation, Migration, and Release

Priority: P0.

- [ ] Update README, installation, external-analysis, CLI, Artisan, schema, limitations, troubleshooting, adapters, versioning, contribution, security, and release documentation for the approved v0.3 behavior.
- [ ] Document target-platform profile generation and validation, partial versus complete guarantees, execution modes, staged versus direct resolution, skipped stages, original-snapshot source limits, and schema `0.7` to `0.8` migration.
- [ ] Verify the protected v0.2.x maintenance branch still carries compatible `0.2.x` aliases, constraints, schema, and release verification after all v0.3 work on `main`.
- [ ] Replace the v0.3 development report identity with exact `0.3.0`, finalize changelog and release notes, and re-verify the schema `0.8`, `0.3.x-dev` alias, `^0.3` constraint, release-verifier, and workflow contract together.
- [ ] Run the deterministic gate on every supported PHP runtime plus required Windows coverage.
- [ ] Run complete-profile cross-host proofs, the restricted Composer-layer offline and credential harness, worst-case staged-corpus budgets, and all privacy canaries.
- [ ] Run normal and lowest-dependency consumers for every advertised Laravel host line.
- [ ] Run fresh-clone and release-artifact consumer audits on Windows and Linux using direct and staged analyses.
- [ ] Produce checksum-bound Core, CLI, and Laravel archives with dependency inventory and source/build provenance.
- [ ] Create matching verified signed tags in the monorepo and all three distribution repositories, synchronize Packagist, and verify exact published source and distribution references.
- [ ] Reproduce the documented complete-profile staged quick start from published packages and prove the target fixture remains byte-for-byte unchanged.

Acceptance gate: published v0.3 packages validate schema `0.8`, reproduce every claimed stage under the declared platform and execution policy, preserve v0.2 migration evidence, and retain all read-only, privacy, compatibility, and supply-chain guarantees.

Status: not started.

## Principal Risks and Controls

| Risk | Control |
| --- | --- |
| Scenario explosion across long transitions | Contract caps, early stop, diagnostic caching, and per-stage plus aggregate budgets |
| False confidence from a `complete` profile | Closed-world semantics, explicit modeled classes, cross-host proofs, and narrower claims when proof fails |
| Corrupt sequential state | Canonical manifest, lock, platform, and execution-policy digests plus a strict selected-predecessor chain |
| Schema consumer breakage | Immutable schema `0.7`, new schema `0.8`, dual-version fixtures, and migration documentation |
| Private repositories or subprocesses exceed the restricted threat model | Explicit compatible/restricted modes, documented residual boundaries, and operational uncertainty instead of false blockers |
| Later-stage source findings assume unperformed edits | One labeled original source snapshot and no claims about hypothetical rewritten code |
| Adapter ecosystem churn | Optional provider contract, unchanged required interfaces, and source-migration fixtures with explicit v0.3 constraints |
| Framework breadth consumes the release | No new published adapter in v0.3 |

## Deferred Until After v0.3.0

- A Symfony adapter, after the optional stage contract has production evidence.
- A CodeIgniter adapter.
- Static PHP language and API migration catalogs beyond Composer platform checks.
- Automatic edits to application source or Composer files.
- Applying or simulating user code changes between reported stages.
- Pull-request creation, hosted uploads, dashboards, telemetry, or SaaS storage.
- AI-generated compatibility claims or migration instructions.
- Executing or booting the analyzed application during deterministic analysis.
- PHAR or versioned container distribution.
- Raising the shared runtime floor above PHP 8.0.

## Recommended Next Work Session

Start Milestone 0 by freezing signed v0.2.1 report and interface artifacts, then separate immutable v0.2 compatibility assertions from live release-series assertions before changing any version, schema, or production behavior.
