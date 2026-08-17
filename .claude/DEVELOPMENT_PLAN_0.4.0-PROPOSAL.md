# PHP Upgrade Preflight Development Plan — v0.4.0 Proposal

Status: **PROPOSAL**. This is not the active plan and it authorizes nothing.

Prepared: 2026-08-16
Prepared against: `main` plus the uncommitted `audit/fix-2026-08-16` working tree; last commit `91eac8f`

- Active tool/package target: `0.3.0` (release candidate, **unreleased**)
- Released baseline: `0.2.1`
- Released report schema: `0.7`
- Candidate v0.3 report schema: `0.8`
- Proposed v0.4 report schema: `0.9`

The active roadmap remains [DEVELOPMENT_PLAN.md](DEVELOPMENT_PLAN.md). On approval this file becomes the active plan: archive the current plan to `DEVELOPMENT_PLAN_0.3.0.md` first (the same copy-then-replace step recorded at [DEVELOPMENT_PLAN.md:10](DEVELOPMENT_PLAN.md)), then replace `DEVELOPMENT_PLAN.md` with the approved v0.4 content and delete this proposal file.

## How to Read This Proposal

- Nothing here permits changing tool version metadata, branch aliases, internal constraints, report identity, release branches, or verifier policy. Milestone 0 owns that coordinated switch, and only after v0.3.0 is published.
- Every gap below cites repository evidence that was verified while preparing this document. Line numbers are valid at `91eac8f` plus the current working tree; re-verify before acting.
- Seven decisions need a maintainer answer before this becomes the active plan. They are collected in [Open Decisions](#open-decisions), each with a recommendation. The milestone text assumes the recommended answers.
- The proposal deliberately reuses the v0.3 plan's shape — contract-first Milestone 0, an early vertical slice that de-risks the release thesis, then generalization, then quality, then release. That sequence worked: v0.3 closed 87 of 97 checklist items with the remaining ten all in release execution.

## Entry Conditions: v0.3.0 Must Ship First

v0.4 planning does not open v0.4 work. Ten items remain unchecked in the active plan, all of them release execution:

| Active-plan item                                                                          | Location                                       |
|-------------------------------------------------------------------------------------------|------------------------------------------------|
| Create and protect the `0.2.x` maintenance branch                                         | [DEVELOPMENT_PLAN.md:166](DEVELOPMENT_PLAN.md) |
| Verify the protected `0.2.x` branch after all v0.3 work on `main`                         | [DEVELOPMENT_PLAN.md:291](DEVELOPMENT_PLAN.md) |
| Finalize the dated changelog and rerun the release verifier                               | [DEVELOPMENT_PLAN.md:293](DEVELOPMENT_PLAN.md) |
| Deterministic gate on every supported PHP runtime plus Windows                            | [DEVELOPMENT_PLAN.md:294](DEVELOPMENT_PLAN.md) |
| Cross-host profile proofs, restricted-execution harness, staged budgets, privacy canaries | [DEVELOPMENT_PLAN.md:295](DEVELOPMENT_PLAN.md) |
| Normal and lowest-dependency consumers for every Laravel host line                        | [DEVELOPMENT_PLAN.md:296](DEVELOPMENT_PLAN.md) |
| Fresh-clone and release-artifact consumer audits on Windows and Linux                     | [DEVELOPMENT_PLAN.md:297](DEVELOPMENT_PLAN.md) |
| Checksum-bound archives with dependency inventory and provenance                          | [DEVELOPMENT_PLAN.md:298](DEVELOPMENT_PLAN.md) |
| Verified signed tags in four repositories plus Packagist synchronization                  | [DEVELOPMENT_PLAN.md:299](DEVELOPMENT_PLAN.md) |
| Reproduce the published complete-profile staged quick start                               | [DEVELOPMENT_PLAN.md:300](DEVELOPMENT_PLAN.md) |

Three additional entry conditions come from work completed on 2026-08-16 and are **not** v0.4 scope:

- The `audit/fix-2026-08-16` working tree is uncommitted across more than 140 files and was still growing while this proposal was written. Its suites were green when run individually — 937 unit, 76 integration, and 2 smoke tests, 1,015 in total, plus both PHPStan configurations, 296 lint targets, and the release-workflow contract tests — but nothing is staged or committed. The branch must be committed and released before any v0.4 baseline is frozen from it.
- The prescribed local gate cannot complete as a single command. `docker compose run --rm php composer check` dies because the integration suite runs about eight minutes against Composer's default 300-second `process-timeout`, which is documented nowhere in `composer.json`, `docs/`, or CI. The `E` and `F` marks it produces are killed subprocesses, not defects. The one-line fix (`composer config process-timeout 0`) was deliberately left unapplied because this project treats bounded Composer timeouts as a product concern. Decide it before v0.3.0, since the release checklist depends on that gate.
- Commits still need the configured SSH signing key, and the public `0.2.x` branch exists but is unprotected ([DEVELOPMENT_PLAN.md:304](DEVELOPMENT_PLAN.md)).

## Carry-Over From the 2026-08-16 Architecture Audit

[`.claude/audits/2026-08-16-architecture-audit.md`](audits/2026-08-16-architecture-audit.md) recorded 59 findings — 1 Critical, 6 High, 32 Medium, 20 Low — against commit `91eac8f`. The current working tree closes most of the prioritized list. Verified in the tree while preparing this proposal:

| Audit action                                                             | State         | Evidence                                                                                                                                                 |
|--------------------------------------------------------------------------|---------------|----------------------------------------------------------------------------------------------------------------------------------------------------------|
| F1 Critical — decompose `StagedUpgradeOrchestrator::analyze()`           | Closed        | Orchestrator is 157 lines; `StagePlanResolver` (216), `StageExecutor` (364), `StageBlockerRegistry` (116), `StageAttemptPlanner` (86) exist              |
| V1 High — Markdown writer fabricates provenance                          | Closed        | 18 explicit "not recorded" renderings in `MarkdownReportWriter`                                                                                          |
| ARCH-1 High — Laravel skeleton knowledge in core                         | Closed        | Moved to the Laravel adapter behind the new `SourceUsageVisitorProvider` / `SourceUsageCollector` contracts                                              |
| M-F2 High — blocker `type` magic string                                  | Closed        | `Model/BlockerType` with 24 call sites in core and the Laravel adapter                                                                                   |
| M-F3 High — `UpgradeTarget` normalizes nothing                           | Closed        | `Model/UpgradeTarget` modified; `Confidence`, `Severity`, `SolverRelation`, `BlockerAttribution` added                                                   |
| CB-3 Medium — diagnostics carry no failure class                         | Closed        | `ComposerDiagnostic::$outcome` with unit coverage                                                                                                        |
| **RPT-2 Medium — truncation and redaction failure are invisible**        | **Open**      | No `truncated`, `original_bytes`, or `[REDACTION_FAILED]` symbol anywhere in `packages/core/src`                                                         |
| F2 High — `ComposerScenarioRunner::run()` mixes eight abstraction levels | Partly closed | Split into `ScenarioWorkspacePreparer`, `ScenarioOutcomeClassifier`, `ScenarioOutcome`, `CandidateLockFileReader`; the runner still measures 1,046 lines |
| G3 High — 125-line regex-branch solver parser                            | Partly closed | `ComposerBlockerParser` modified and still measures 687 lines; the method-level split was not re-measured here                                           |

Two consequences for v0.4:

1. **RPT-2 is the one open audit item with product meaning.** A report that silently truncates an excerpt, or that silently fails a redaction pass, weakens exactly the evidence-integrity claim the product sells. It belongs in v0.4 Milestone 6 unless it lands in the v0.3.0 candidate first.
2. **Audit action 6 unblocked this release.** The audit told the project to sequence the framework-neutrality refactor "before adapter #2, not before the v0.3.0 release." That refactor has now landed. Adapter #2 is the natural next release.

## Version and Contract Vocabulary

| Contract              | State entering v0.4                                                                            | v0.4 direction                                                                         |
|-----------------------|------------------------------------------------------------------------------------------------|----------------------------------------------------------------------------------------|
| Tool and package line | `0.3.0` published; `0.3.x-dev` aliases; `^0.3` internal constraints                            | `0.4.0`; identity switched atomically in Milestone 0                                   |
| Canonical report      | Schema `0.8`                                                                                   | New schema `0.9` for framework-declared version identity and multi-adapter attribution |
| Published packages    | `core`, `cli`, `laravel`                                                                       | Adds `symfony` as a fourth published package and distribution repository               |
| Active release policy | `0.4.x` from `main`; `0.3.x` from its protected maintenance branch; `0.2.x` and `0.1.x` frozen | Milestone 0 establishes the `0.3.x` branch before `main` moves                         |

Schemas `0.2` through `0.8` and every signed compatibility artifact remain immutable. Packages continue to derive exact versions from matching signed Git tags rather than manifest `version` fields, and all published packages continue to release in lockstep.

## The v0.4 Thesis

v0.3 made the analyzer honest about *how* an upgrade would be reached: staged Composer evidence, candidate-state chaining, blocker lifecycles, closed-world platform profiles. It did that with one framework adapter.

v0.4 should prove the central architectural claim the product has asserted since v0.1 and never demonstrated publicly: **that the core is framework-neutral.** Today that claim rests on two test-only adapters inside the monorepo and on a negative check (no `use Illuminate\` in core). A second published adapter, built by the same maintainer through the same public contracts and shipping real evidence-backed guidance, is the only thing that converts the claim into a fact — and it is the fastest way to discover which parts of the "neutral" core are still Laravel-shaped.

Symfony is the recorded candidate ([docs/adapters.md:169](../docs/adapters.md), [DEVELOPMENT_PLAN.md:83](DEVELOPMENT_PLAN.md), and the durable decisions in [memory/MEMORY.md](memory/MEMORY.md)). Preparing this proposal surfaced three concrete places where the current contracts cannot express Symfony without change. They are the real content of v0.4, and none of them is Symfony-specific once fixed:

### Gap 1 — Hop identity is an integer major

`frameworkHop`, `frameworkHopReference`, and `stageAnalysis` in [`upgrade-report-v0.8.schema.json`](../packages/core/resources/schema/upgrade-report-v0.8.schema.json) all require integer `from_major` / `to_major`, and `stageAnalysis.to_major` carries `minimum: 1`. That models Laravel exactly, where one major is one upgrade guide and `laravel/framework` is one root constraint.

Symfony does not work that way. Its upgrade paths are minor-precision and anchored on the last minor of each major, which is also its LTS: a major hop departs only from that final minor, and the preceding same-major hop into it is the deprecation-clearing step that decides whether the major hop succeeds at all. Under schema `0.8` a same-major hop is not representable as a distinct stage, and "you may upgrade from Symfony N" is a claim the project would have no evidence for, because only `N.4` is a valid departure point for `N+1.0`.

(The exact version endpoints in this document are illustrative. Milestone 1 of the v0.2 plan required reviewing official upgrade guides and exact manifests before encoding any range; v0.4 must do the same for Symfony before its matrix is approved.)

This is the single strongest reason v0.4 needs schema `0.9`, and it is a correctness fix for any future adapter whose framework versions by minor.

### Gap 2 — A stage target assumes one root package

Laravel stage targets set one constraint: `laravel/framework`. A Symfony upgrade moves every rooted `symfony/*` component in lockstep, and a real application roots ten to forty of them. The stage-target contract must therefore express "set every rooted package in this declared family to this version", with the analyzer proving the resulting manifest against Composer rather than assuming the family list is complete.

### Gap 3 — Only one stage-target provider may be active, and the family classifier already collides

The v0.3 contract limits staged solving to a single active stage-target provider and skips with conflict evidence when several are present ([DEVELOPMENT_PLAN.md:163](DEVELOPMENT_PLAN.md)). That was the right v0.3 bound. With two published adapters it becomes the default failure mode for the most common real project, because a Laravel application roots `symfony/*` packages and a Symfony application may root none of Laravel's.

The collision is not hypothetical: the Laravel adapter currently owns the `symfony/*` package-family mapping (`LaravelPackageFamilyClassifier`, recorded in [memory/MEMORY.md](memory/MEMORY.md)). With a Symfony adapter active, two integrations claim the same package family. v0.4 must define deterministic activation, arbitration, and per-finding attribution rather than skipping.

## v0.4 Evidence and Gap Map

| Gap | Repository evidence | Roadmap response |
| --- | --- | --- |
| Framework neutrality has no published proof | Only `test-adapter` (278 LOC) and `legacy-test-adapter` exercise the contracts | Milestones 0, 3, 4 |
| Hop identity cannot express minor-versioned frameworks | `frameworkHop` / `stageAnalysis` integer `from_major`, `to_major` | Milestone 1, schema `0.9` |
| Stage targets assume a single root constraint | Laravel stage targets in `packages/laravel/src/Catalog` | Milestone 1 |
| One active stage provider; several skip staged solving | [DEVELOPMENT_PLAN.md:163](DEVELOPMENT_PLAN.md) | Milestone 2 |
| Two adapters claim the `symfony/*` package family | `LaravelPackageFamilyClassifier`; [memory/MEMORY.md](memory/MEMORY.md) | Milestone 2 |
| Findings do not name the adapter that produced them | `frameworkGuidance.framework` exists; `frameworkFinding` attribution not established | Milestone 2, schema `0.9` |
| Excerpt truncation and redaction failure are invisible | Audit RPT-2; no `truncated` / `original_bytes` symbol in core | Milestone 6 |
| Worst-case staged cost is 128 Composer processes and 1,800 s | [docs/v0.3-contract.md:63](../docs/v0.3-contract.md) | Milestone 6 |
| A fourth package multiplies release and compatibility jobs | v0.2.0 release ran 36 jobs for three packages | Milestones 6, 7 |

## Release Targets

### v0.3.x stabilization

Keep schema `0.8`, the public PHP operation, CLI and Artisan behavior, adapter metadata, exit policy, staged-analysis semantics, and supported Laravel transitions compatible. Establish and protect the `0.3.x` maintenance branch before `main` adopts v0.4 identity, so urgent patch work never requires backporting v0.4 behavior.

### v0.4.0

v0.4.0 should deliver a proven second framework:

- framework-declared, ordered version identity for hops and stages, replacing integer majors, under schema `0.9` with a documented `0.8` migration;
- multi-adapter activation, deterministic stage-provider arbitration, package-family collision rules, and per-finding adapter attribution;
- a published `php-upgrade-preflight/symfony` adapter with detection, a versioned rule catalog, staged targets, and a deliberately narrow initial transition matrix held to the same evidence standard as Laravel;
- a Symfony console command that is canonical-report equivalent to the generic CLI, matching the Laravel Artisan guarantee;
- family-wide stage targets that move every rooted component of a declared package family together;
- the remaining audit debt, re-derived two-adapter budgets, and a measured reduction in worst-case Composer process count;
- the existing PHP `^8.0` runtime floor. Symfony 7 requires PHP 8.2 for the *analyzed project*; that never raises the analyzer's floor, exactly as Laravel 13's PHP `^8.3` requirement did not.

CodeIgniter is not a v0.4 deliverable. A PHP language and API deprecation catalog is not a v0.4 deliverable.

## v0.4 Scope and Non-Goals

In scope:

- framework-declared version identity, ordering, and stage IDs that stay deterministic across adapters;
- multiple simultaneously active integrations, with deterministic detection order, arbitration, attribution, and collision evidence;
- family-scoped stage targets and their Composer proof;
- Symfony detection that never activates on transitively installed Symfony components;
- a versioned, test-validated Symfony rule catalog with commit-pinned upstream evidence;
- Symfony staged solving across the approved matrix, plus honest skips outside it;
- a fourth published package, distribution repository, and Packagist reference;
- schema `0.9`, its `0.8` migration, and preservation of every historical schema and snapshot;
- adapter-conformance coverage for two live adapters plus the existing third-party and legacy fixtures;
- the audit carry-over, two-adapter budgets, and release-automation changes those require.

Out of scope:

- modifying the analyzed application's source, `composer.json`, `composer.lock`, or `vendor/`;
- applying or simulating source or configuration remediations between stages;
- a CodeIgniter package or any fifth adapter;
- a static PHP language/API deprecation catalog beyond Composer platform evidence;
- Symfony recipe execution, Flex operations, or any behavior that runs the analyzed application;
- pull-request creation, hosted uploads, dashboards, telemetry, or SaaS storage;
- AI-generated compatibility claims or migration instructions;
- PHAR or versioned container delivery;
- raising the shared runtime floor above PHP `^8.0`;
- a published adapter conformance test-kit package (deferred; the in-repo fixtures remain the reference).

## Inherited Product and Test Rules

Unchanged from v0.3 and restated because a second adapter is the first real test of several of them:

- Composer remains the dependency solver. Never infer a successful stage without running it.
- The analyzed project is immutable input. Every mutation happens in an analyzer-owned temporary workspace.
- Core stays framework-neutral. Adapters own detection, version semantics, targets, rule catalogs, source defaults, package families, and source-usage visitors. A concept that only one adapter can interpret does not belong in core.
- JSON is canonical, Markdown is a projection with no independent analysis logic and no fabricated values.
- Evidence IDs are unique and deterministic; unsupported claims are uncertainty.
- Source inspection stays static and parser-based, always against the original project snapshot.
- Preserve `UpgradeAnalyzer::analyzeUpgrade(UpgradeRequest): UpgradeReport` as the single public operation.
- Redaction and path-exposure policy apply at model ingress and every publication boundary.
- PHP `^8.0` language floor in all shipped runtime code.

Four test layers are retained: offline unit tests; deterministic Composer integration tests over committed `path` repositories; curated application-shaped fixtures with immutability and JSON-first approvals; and networked installation and live-application smoke tests kept out of the deterministic gate.

## Milestone 0: Freeze v0.3.0 and Lock the v0.4 Contract

Priority: P0. Complete before changing report shape or development identity.

- [ ] Archive the completed v0.3 roadmap to `DEVELOPMENT_PLAN_0.3.0.md` and install the approved v0.4 plan as `DEVELOPMENT_PLAN.md`.
- [ ] Freeze the signed v0.3.0 public surface — PHP operation, CLI and Artisan behavior, adapter metadata, exit policy, schema `0.8`, staged-analysis semantics, and the Laravel transition matrix — as immutable compatibility evidence under `tests/fixtures/contracts/v0.3.0`.
- [ ] Split historical v0.3 compatibility assertions from live development-version and release-policy assertions, following the v0.2 precedent. Do not weaken existing contract tests by search-and-replace.
- [ ] Create and protect the `0.3.x` maintenance branch while the tree still carries its `0.3.x` verifier, aliases, and constraints.
- [ ] Add a machine-readable v0.4 contract and dedicated tests for every new identity, attribution, arbitration, status, ordering rule, and budget.
- [ ] Define the framework version-identity contract: what an adapter declares, how two versions are ordered, how a hop is named, and how stage IDs stay stable and collision-free across adapters.
- [ ] Define multi-adapter semantics before writing adapter code: activation, deterministic ordering, stage-provider arbitration, package-family collision resolution, source-usage visitor composition, and per-finding attribution.
- [ ] Define family-scoped stage targets: how an adapter declares a package family, how rooted members are enumerated from project state, and how the resulting manifest is proved by Composer rather than assumed.
- [ ] Re-derive hop, attempt, scenario, process, runtime, memory, and report-size budgets for two active adapters and record whether the v0.3 caps still hold.
- [ ] Approve schema `0.9` and its `0.8` migration, then add the immutable schema file and a minimal canonical serialization fixture before Milestone 1 emits new fields.
- [ ] Record the decision to keep CodeIgniter, PHP deprecation catalogs, PHAR, container delivery, a published conformance kit, and runtime-floor changes out of v0.4.
- [ ] Atomically switch `main` to v0.4 development identity, schema `0.9`, `0.4.x-dev` aliases, `^0.4` internal constraints, and a verifier permitting only `0.4.x` from `main`.

### Adapter-neutrality vertical slice

Part of Milestone 0, and the release's central risk control. It must pass before broad Symfony rule work starts, for the same reason the v0.3 staged slice preceded platform and execution hardening: if the core cannot carry a second adapter through one real path, no amount of rule content will fix it.

- [ ] Add the minimal Symfony integration needed for one covered path — detection from a rooted `symfony/framework-bundle`, one family-scoped stage target, and one rule with commit-pinned upstream evidence.
- [ ] Prove one offline Symfony fixture on the approved major hop end to end through the existing pipeline: Composer-backed staged evidence, blocker registry, source impact from the original snapshot, and byte-for-byte fixture immutability.
- [ ] Prove the same run with the Laravel adapter also installed: both integrations activate where their own evidence applies, the `symfony/*` family is attributed once under the arbitration rule, and staged solving does not skip on a provider conflict.
- [ ] Require that the slice needs no Symfony-specific branch in core. Any core change it forces must be expressed as a neutral contract and must leave the Laravel canonical reports unchanged except for documented schema `0.9` migration.
- [ ] Emit the slice through schema `0.9` with JSON-first assertions and a faithful Markdown projection.

Acceptance gate: immutable v0.2.1 and v0.3.0 evidence remains green; the `0.3.x` branch can still verify its own line; one offline Symfony hop produces real Composer evidence alongside an active Laravel adapter without a Symfony-specific branch in core; and `main` identifies every subsequent build as v0.4 under schema `0.9` after a machine-checked contract defines identity, arbitration, attribution, family targets, and budgets.

## Milestone 1: Framework Version Identity and Schema 0.9

Priority: P0.

- [ ] Replace integer `from_major` / `to_major` hop identity with the approved framework-declared version identity across models, guidance, stages, plan actions, and evidence references.
- [ ] Keep ordering, comparison, and gap detection inside a tested value object; adapters declare versions and their ordering rule, core never parses framework version semantics.
- [ ] Preserve stable, deterministic, human-readable stage IDs under the new identity, and prove no ID collides when two adapters are active.
- [ ] Support minor-precision hops, including a same-major deprecation-clearing hop, without weakening the gapless-path rule.
- [ ] Complete strict schema `0.9`, canonical snapshots, Markdown projection, and evidence-integrity checks.
- [ ] Add a consumer migration fixture from `0.8` and document exactly which fields moved, which are additive, and which are removed.
- [ ] Preserve schemas `0.2` through `0.8` and every historical snapshot byte-for-byte.
- [ ] Prove the Laravel transition matrix produces semantically identical findings under the new identity, with snapshot changes limited to documented migration effects.

Acceptance gate: a minor-versioned framework path is representable and evidence-backed; Laravel output is unchanged except for documented migration effects; and every schema `0.9` finding resolves to valid evidence.

## Milestone 2: Multi-Adapter Core

Priority: P0.

- [ ] Support several simultaneously active integrations with deterministic ordering, and cover activation, non-activation, and mutual-exclusion cases.
- [ ] Replace the single-stage-provider restriction with deterministic arbitration: an explicit `--framework` request wins, otherwise the provider whose declared family owns the requested root targets wins; an unresolvable case still skips with conflict evidence.
- [ ] Resolve package-family collisions deterministically and stop the Laravel adapter from being the implicit owner of `symfony/*` when a Symfony adapter is active.
- [ ] Attribute every framework finding, guidance entry, stage, rule pack, and family label to the adapter that produced it, and expose that attribution in schema `0.9`.
- [ ] Compose source-usage visitors from several adapters without duplicate usages, without cross-adapter evidence bleed, and without one adapter's parse failure suppressing another's findings.
- [ ] Keep an inactive adapter completely silent: no usage types, no families, no guidance, no uncertainty entries.
- [ ] Cover the realistic combinations: Laravel-only, Symfony-only, Laravel with rooted Symfony components, a Symfony application with a Laravel-family package installed transitively, both adapters installed with neither activating, and the third-party plus legacy test adapters alongside both.
- [ ] Prove deterministic, byte-identical canonical output regardless of adapter installation order.

Acceptance gate: two published adapters coexist with deterministic activation, arbitration, attribution, and family ownership; no adapter can influence a project it did not detect; and installation order cannot change canonical output.

## Milestone 3: Symfony Detection and Rule Catalog

Priority: P0.

- [ ] Detect Symfony conservatively from rooted `symfony/framework-bundle`, `symfony/runtime`, or rooted `symfony/*` components, preferring exact locked versions over root constraints.
- [ ] Never activate on transitively installed Symfony components. This is the Illuminate lesson restated: the analyzer's own dependencies and every Laravel application would otherwise trigger false detection.
- [ ] Report inconsistent rooted component versions as uncertainty rather than choosing one.
- [ ] Build a versioned, typed Symfony rule catalog mirroring the Laravel catalog's structure, with test-time validation of duplicate keys, missing sources, invalid SemVer, coverage gaps, and contradictory advice.
- [ ] Encode the approved initial matrix only, with exact PHP requirements, component constraints, first-party bundle migrations, and commit-pinned official upgrade-guide evidence.
- [ ] Distinguish exact metadata and source evidence from documentation-derived guidance, and label structural or recipe-related review work as low-confidence review locations, never confirmed incompatibilities.
- [ ] Cover the high-signal source patterns the parser can prove — bundle registration, service configuration references, removed and renamed classes, deprecated attributes and annotations — and nothing that requires container resolution.
- [ ] Own the `symfony/*` package-family classification, and keep Doctrine, Twig, and other ecosystem families separate from framework families.
- [ ] Add offline application-shaped fixtures for a feasible path, an advisory-heavy path, a blocked path, an ambiguous source version, and an unsupported range.

Acceptance gate: Symfony detection is evidence-backed and never fires transitively; the catalog validates at test time; and every emitted finding links to exact project, package, solver, or commit-pinned maintainer evidence.

## Milestone 4: Symfony Staged Solving and Entry-Point Parity

Priority: P0.

- [ ] Provide Symfony stage targets through the optional stage-target contract, at minor precision, with evidence-backed PHP requirements and stable stage IDs.
- [ ] Move every rooted member of the declared Symfony family together in one stage target, and record the enumerated member list as evidence.
- [ ] Run bounded isolated Composer strategies and remediation rounds per stage, carrying the selected candidate state forward exactly as the Laravel chain does.
- [ ] Skip honestly outside the approved matrix, on an ambiguous endpoint, on a missing hop, or when no safe exact stage PHP exists.
- [ ] Add the Symfony console command as the entry-point analogue of the Laravel Artisan command, defaulting to the application's project directory and delegating to the same analyzer operation.
- [ ] Keep generic CLI and Symfony console canonical JSON parity for feasible, blocked, skipped, single-hop, and multi-hop cases.
- [ ] Prove the original fixture remains byte-for-byte unchanged for success, failure, timeout, and debug cleanup paths.
- [ ] Prove direct final-target resolution stays independent of staged resolution for Symfony, as it is for Laravel.

Acceptance gate: every reported feasible Symfony stage has its own Composer evidence, family-wide targets are proved rather than assumed, both Symfony entry points are canonical-report equivalent, and no result appears after an unresolved blocker set or a matrix gap.

## Milestone 5: Adapter Ecosystem Hardening

Priority: P1.

- [ ] Extend conformance coverage to two live adapters plus the third-party and legacy fixtures: stable IDs, version identity and ordering, exact target constraints, PHP evidence, ordering, duplicate targets, conflicting providers, missing metadata, and invalid provider output.
- [ ] Prove an adapter written against the v0.3 contracts still loads under v0.4 with a widened Core constraint, contributes guidance, and makes no staged or attribution claims it cannot support.
- [ ] Document the migration an adapter author must perform for version identity, family declaration, and attribution, with a worked diff.
- [ ] Rewrite [docs/adapters.md](../docs/adapters.md) for the multi-adapter world: activation, arbitration, family ownership, attribution, visitors, collisions, privacy, and conformance fixtures.
- [ ] Keep core opaque to both frameworks' package families, version semantics, and rule-catalog details, and re-verify that no adapter concept leaked back into core during v0.4.
- [ ] Record CodeIgniter, or an ecosystem adapter such as Doctrine, as the first post-v0.4 candidate without adding a fifth package to this release.

Acceptance gate: an external adapter author can build, verify, and migrate an adapter from published documentation and in-repo fixtures alone, and core carries no framework-specific concept introduced by v0.4.

## Milestone 6: Quality, Performance, and Supply-Chain Hardening

Priority: P1.

- [ ] Close audit finding RPT-2: expose `truncated` and `original_bytes` on bounded excerpts and emit an explicit marker when a redaction pass fails, so no consumer mistakes a shortened or unfiltered excerpt for a complete, redacted one.
- [ ] Re-measure the audit's residual structural findings against the post-fix tree and either close them or record them with current line numbers.
- [ ] Enforce re-derived two-adapter budgets for process count, per-stage and aggregate runtime, memory, report size, redaction, and deterministic rerun on Linux and Windows.
- [ ] Reduce the worst-case Composer process count below the v0.3 ceiling by caching equivalent scenario and diagnostic executions within one analysis, and prove byte-identical canonical output with the cache disabled.
- [ ] Extend selective mutants to version identity and ordering, arbitration, family collision, attribution, Symfony detection, and family-scoped targets.
- [ ] Continue the coverage ratchet and make the new identity, arbitration, attribution, and Symfony catalog classes critical modules.
- [ ] Measure the compatibility and release matrices before the fourth package multiplies them, and keep total CI time from regressing against the v0.3 baseline established by the 2026-08-16 workflow tuning.
- [ ] Add Symfony transcript and catalog fixtures so upstream drift is separable from parser or solver drift.
- [ ] Retain dependency audits, commit-pinned actions, archive checksums, dependency inventory, provenance, signed distribution verification, secret canaries, and target-immutability gates.
- [ ] Preserve the PHP `^8.0` runtime floor and add Symfony host-installability coverage alongside the existing Laravel matrix.

Acceptance gate: the worst supported two-adapter request is bounded, deterministic, private, mutation-protected, and no slower in CI than the v0.3 baseline, without weakening any existing gate.

## Milestone 7: v0.4 Documentation, Migration, and Release

Priority: P0.

- [ ] Update README, installation, external-analysis, CLI, console, schema, limitations, troubleshooting, adapters, versioning, contribution, security, and release documentation for approved v0.4 behavior.
- [ ] Document version identity, multi-adapter activation and arbitration, family ownership, attribution, the Symfony matrix and its honest gaps, and the `0.8` to `0.9` migration.
- [ ] Extend release automation, the verifier, and the checklist to four packages and four distribution repositories.
- [ ] Verify the protected `0.3.x` maintenance branch still carries compatible aliases, constraints, schema, and release verification after all v0.4 work on `main`.
- [ ] Replace the development identity with exact `0.4.0`, prepare changelog and release notes, and re-verify schema `0.9`, aliases, constraints, and workflow contract together.
- [ ] Run the deterministic gate on every supported PHP runtime plus required Windows coverage.
- [ ] Run cross-host profile proofs, the restricted Composer harness, worst-case two-adapter budgets, and all privacy canaries.
- [ ] Run normal and lowest-dependency consumers for every advertised Laravel and Symfony host line.
- [ ] Run fresh-clone and release-artifact consumer audits on Windows and Linux using direct and staged analyses through both adapters.
- [ ] Produce checksum-bound archives for all four packages with dependency inventory and provenance.
- [ ] Create matching verified signed tags in the monorepo and all four distribution repositories, synchronize Packagist, and verify exact published references.
- [ ] Reproduce documented Laravel and Symfony staged quick starts from published packages and prove both target fixtures remain byte-for-byte unchanged.

Acceptance gate: published v0.4 packages validate schema `0.9`, reproduce every claimed stage for both frameworks under the declared platform and execution policy, preserve v0.3 migration evidence, and retain all read-only, privacy, compatibility, and supply-chain guarantees.

## Principal Risks and Controls

| Risk | Control |
| --- | --- |
| Symfony breadth consumes the release | A deliberately narrow approved matrix, held to the v0.1 precedent of shipping one proven path rather than a wide shallow one |
| Version-identity migration destabilizes Laravel output | Migration fixtures, unchanged-semantics proofs, and immutable `0.8` snapshots |
| Two adapters produce contradictory or duplicated findings | Deterministic arbitration, single-owner family rules, per-finding attribution, and installation-order-independence proofs |
| Symfony detection fires on transitive components | Rooted-only activation, mirroring the Illuminate rule, with explicit negative fixtures |
| Family-scoped targets silently miss a rooted component | Enumerated member list recorded as evidence and proved by Composer, never assumed |
| A fourth package doubles release and CI cost | Matrix measurement before expansion and a CI-time budget carried from the v0.3 baseline |
| Core re-acquires framework-specific knowledge | A neutrality re-verification item in Milestone 5 and the rule that adapter-only concepts never enter core |
| Symfony upstream drift breaks parsing or guidance | Commit-pinned catalog evidence and transcript fixtures that separate drift from regression |
| v0.3.0 slips while v0.4 work starts | Entry conditions above; no v0.4 identity switch before v0.3.0 publishes |

## Deferred Until After v0.4.0

- A CodeIgniter adapter, or an ecosystem adapter such as Doctrine.
- A published adapter conformance test-kit package.
- Static PHP language and API migration catalogs beyond Composer platform checks.
- Automatic edits to application source or Composer files, and simulation of user code changes between stages.
- Pull-request creation, hosted uploads, dashboards, telemetry, or SaaS storage.
- AI-generated compatibility claims or migration instructions.
- Executing or booting the analyzed application during deterministic analysis.
- PHAR or versioned container distribution.
- Raising the shared runtime floor above PHP 8.0.
- A `1.0` stability commitment. v0.4 should, however, record what remains between it and `1.0`.

## Open Decisions

Seven answers are needed before this becomes the active plan. Milestone text assumes the recommendation.

**D1 — Release theme.** Recommend the second published adapter. Alternatives: a PHP language/API deprecation catalog, which would address the product's name but competes with Rector and PHPCompatibility and adds a claim class the evidence rules make expensive; or a consolidation release toward `1.0`, which is defensible but leaves framework neutrality unproven for another cycle. Symfony is already the recorded candidate in three places and the audit sequenced the neutrality refactor specifically to unblock it.

**D2 — Symfony initial matrix.** Recommend a narrow start defined by rule rather than by version number: the current LTS to the current stable major, plus the same-major deprecation-clearing hop that enters that LTS. Everything outside it is an honest unsupported result. Establish the exact endpoints from commit-pinned upstream guides and manifests at approval time. The wide alternative — every supported Symfony line — repeats the v0.2 breadth expansion before the second adapter has any production evidence, and the Laravel adapter's own history argues against it: v0.1 shipped two paths, v0.2 shipped nine.

**D3 — Schema `0.9`.** Recommend yes. The integer-major hop identity cannot represent Symfony's real upgrade paths, and stretching `0.8` would either encode a false claim or bury minor precision in a string field consumers cannot rely on.

**D4 — Fourth published package and distribution repository.** Recommend yes. A monorepo-only Symfony adapter would not prove the public contract, which is the entire point of the release. The cost is a fourth split, tag, and Packagist reference plus a larger release matrix, and Milestone 6 measures that before Milestone 7 pays it.

**D5 — Symfony console command.** Recommend yes, scoped to parity with the Artisan command: one command, default project path, same analyzer operation, canonical-report equivalent. Without it the Symfony adapter is a second-class integration and the parity guarantee becomes Laravel-specific.

**D6 — Published adapter conformance kit.** Recommend deferring. External code contributions are not currently accepted, so the audience is the maintainer plus documentation readers, and in-repo fixtures serve both.

**D7 — Scope of the `0.3.x` maintenance line.** Recommend the same policy as `0.2.x`: security fixes, regressions, dependency maintenance, documentation corrections, and release-process repairs only, from its own protected branch and verifier.

Three smaller questions deserve an explicit answer at approval time, and the first two should be settled before v0.3.0 rather than deferred into v0.4:

- Whether the Composer `process-timeout` default is raised so the prescribed local gate can run as one command, and whether that lands in `composer.json` with a `CONTRIBUTING.md` note.
- Whether audit finding RPT-2 lands in the v0.3.0 candidate instead of waiting for v0.4 Milestone 6.
- Whether the dev-loop container change offered during the 2026-08-16 performance work — a named volume for `/app/vendor`, which diverges container and host vendor directories and would break IDE indexing until an in-container install runs — is wanted at all.

## Recommended First Work Session After Approval

Do not start Milestone 0 until v0.3.0 is published. The first v0.4 session should then archive the v0.3 plan, freeze the signed v0.3.0 surface, and protect the `0.3.x` branch — in that order, while the tree still carries `0.3.x` identity. The version-identity contract is the first thing to write down and the last thing that should be discovered during implementation.
