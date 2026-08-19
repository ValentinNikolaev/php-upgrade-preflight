# PHP Upgrade Preflight Development Plan

Last updated: 2026-08-18

- Released baseline: `0.3.1` (published 2026-08-18)
- Released report schema: `0.8`
- Active development target: `0.4.0`
- Planned v0.4 report schema: `0.9`

This roadmap supersedes the archived [v0.3.0 implementation plan](DEVELOPMENT_PLAN_0.3.0.md), which records the completed v0.3 milestones and the v0.3.0 release evidence. The archive was copied from the completed plan before this file was replaced.

v0.3 made the analyzer honest about *how* an upgrade would be reached: staged Composer evidence, candidate-state chaining, blocker lifecycles, closed-world platform profiles. It did all of that with one framework adapter.

v0.4 should prove the architectural claim the product has asserted since v0.1 and never demonstrated publicly: **that the core is framework-neutral**. Today that claim rests on two test-only adapters inside the monorepo and on a negative check. A second published adapter, built through the same public contracts, is the only thing that turns the claim into a fact, and the fastest way to find which parts of the neutral core are still Laravel-shaped.

## How to Use This Plan

- Continue the first unchecked item in the earliest incomplete milestone unless repository evidence requires a safer order.
- Mark work `[~]` only while someone is implementing it. Mark it `[x]` only after the acceptance evidence passes.
- Reconcile this plan in the same change whenever roadmap work is completed, partially completed, reopened, or reverted.
- Keep v0.3.x work limited to security fixes, regressions, dependency maintenance, documentation corrections, and release-process repairs. Put version identity, multi-adapter behavior, and the Symfony adapter in v0.4.
- Do not switch development aliases, internal constraints, report identity, release branches, or release-verifier policy piecemeal. Milestone 0 owns that coordinated migration.
- Recheck external release and package state before acting. Local Git state cannot prove that GitHub, distribution repositories, or Packagist did not change later.
- Update public documentation in the same change as behavior, commands, report semantics, supported versions, trust boundaries, or release policy.

## Version and Contract Vocabulary

| Contract | State entering v0.4 | v0.4 direction |
| --- | --- | --- |
| Tool and package line | `0.3.0` published; `0.3.x-dev` aliases; `^0.3` internal constraints | `0.4.0`; identity switched atomically in Milestone 0 |
| Canonical report | Schema `0.8` | New schema `0.9` for framework-declared version identity and adapter attribution |
| Published packages | `core`, `cli`, `laravel` | Adds `symfony` as a fourth published package and distribution repository |
| Active release policy | `0.3.x` from `main`; `0.2.x` and `0.1.x` archival | `0.4.x` from `main` once Milestone 0 establishes the protected `0.3.x` branch |

Schemas `0.2` through `0.8` and every signed compatibility artifact remain immutable. Packages continue to derive exact versions from matching signed Git tags rather than manifest `version` fields, and all published packages release in lockstep.

## Support Policy Across Lines

`0.3.x` is supported until v0.4.0 is published: security fixes, regressions, dependency maintenance, documentation corrections, and release-process repairs, prepared from its own protected branch. At the moment v0.4.0 publishes, `0.3.x` becomes archival on the same terms as `0.2.x` and `0.1.x` — signed artifacts and schemas stay available and immutable, and the line receives nothing further, security fixes included. That is exactly what happened to `0.2.x` when v0.3.0 shipped, and the public pages state it.

## Released v0.3.x Baseline

The published baseline is documented in the [v0.3.0 release notes](../docs/releases/v0.3.0.md), the [v0.3.1 release notes](../docs/releases/v0.3.1.md), and the [v0.3 contract](../docs/v0.3-contract.md).

- Schema `0.8` carries required `staged_resolution`, Composer execution provenance, target-platform-profile projections, adjacent stage attempts, candidate-state fingerprints, and blocker lifecycle history.
- Laravel guidance covers 7 to 8, the retained direct 7 to 9 path, and every adjacent hop from 8 to 9 through 12 to 13, with real Composer evidence per contiguous stage.
- Framework-shaped source inspection is adapter-owned behind `SourceUsageVisitorProvider`; core no longer interprets another framework's application skeleton.
- Vocabularies that reach the report — severity, confidence, blocker type, solver relation — have single owners and validate at construction.
- Excerpt truncation and redaction failure are visible in canonical output, closing the last open finding of the 2026-08-16 architecture audit.
- v0.3.0 was published from `main` at `3959b0fe` through release run 32136742538, with verified signed tags in four repositories, byte-compared distribution payloads, checksum-bound archives, and a published-package quick start that left the analyzed fixture unchanged.
- v0.3.1 followed on the same day from `83a9ba2f` through release run 32178181503. It reports tool `0.3.1` on unchanged schema `0.8`, makes excerpt truncation and redaction failure visible, and replaces the pre-publication documentation the v0.3.0 packages had shipped with.

## v0.4 Evidence and Gap Map

Every gap below was verified against the released tree.

| Gap | Repository evidence | Roadmap response |
| --- | --- | --- |
| Framework neutrality has no published proof | Only `test-adapter` and `legacy-test-adapter` exercise the contracts | Milestones 0, 3, 4 |
| Hop identity is an integer major and cannot express a minor-versioned framework | `frameworkHop` and `stageAnalysis` require integer `from_major` and `to_major` in [`upgrade-report-v0.8.schema.json`](../packages/core/resources/schema/upgrade-report-v0.8.schema.json) | Milestone 1, schema `0.9` |
| A stage target sets one root constraint | Laravel stage targets in `packages/laravel/src/Catalog` | Milestone 1 |
| Only one stage-target provider may be active; several skip staged solving | v0.3 contract bound, retained deliberately | Milestone 2 |
| Two adapters would claim the `symfony/*` package family | The Laravel classifier owns `symfony/` prefixes today | Milestone 2 |
| Findings do not name the adapter that produced them | `frameworkGuidance.framework` exists; per-finding attribution does not | Milestone 2, schema `0.9` |
| A fourth package multiplies release and compatibility jobs | The v0.3.0 release run executed 44 jobs for three packages | Milestones 5, 6 |
| Worst-case staged cost stands at the v0.3 ceiling | [docs/v0.3-contract.md](../docs/v0.3-contract.md) budgets | Milestone 5 |

### Why Symfony forces version identity

Symfony upgrade paths are minor-precision and anchored on the last minor of each major, which is also its LTS: a major hop departs only from that final minor, and the preceding same-major hop is the deprecation-clearing step that decides whether the major hop can succeed at all. Under schema `0.8` a same-major hop is not representable as a distinct stage, so a claim that a project may upgrade from Symfony N would have no evidence behind it. The fix is not Symfony-specific: it is a correctness fix for any adapter whose framework versions by minor.

Exact version endpoints stay illustrative until Milestone 3 reviews official upgrade guides and exact manifests, exactly as the Laravel matrix was established.

## Release Targets

### v0.3.x stabilization

Keep schema `0.8`, the public PHP operation, CLI and Artisan behavior, adapter metadata, exit policy, staged-analysis semantics, and supported Laravel transitions compatible. Establish and protect the `0.3.x` maintenance branch before `main` adopts v0.4 identity, so urgent patch work never requires backporting v0.4 behavior.

### v0.4.0

v0.4.0 delivers a proven second framework, deliberately narrow:

- framework-declared, ordered version identity for hops and stages, replacing integer majors, under schema `0.9` with a documented `0.8` migration;
- multi-adapter activation, deterministic stage-provider arbitration, package-family collision rules, and per-finding adapter attribution;
- family-scoped stage targets that move every rooted component of a declared package family together;
- a published `php-upgrade-preflight/symfony` adapter with detection, a versioned rule catalog, and staged solving across one approved hop pair — the same-major deprecation-clearing hop and the major hop that departs from it — held to the same evidence standard as Laravel;
- the existing PHP `^8.0` runtime floor. The Symfony requirement applies to the analyzed project and never raises the analyzer floor, exactly as the Laravel 13 requirement did not.

Deferred to [the v0.5 proposal](DEVELOPMENT_PLAN_0.5.0-PROPOSAL.md) rather than dropped: the Symfony console command, a broader Symfony matrix, the adapter migration guide with a worked diff, published conformance tooling, and the Composer process-count reduction. Each is a lever this plan can pull if the cycle runs long, and none of them is required to prove neutrality.

## v0.4 Scope and Non-Goals

In scope:

- framework-declared version identity, ordering, and stage IDs that stay deterministic across adapters;
- several simultaneously active integrations, with deterministic detection order, arbitration, attribution, and collision evidence;
- family-scoped stage targets and their Composer proof;
- Symfony detection that never activates on transitively installed Symfony components;
- a versioned, test-validated Symfony rule catalog with commit-pinned upstream evidence for the approved hop pair;
- a fourth published package, distribution repository, and Packagist reference;
- schema `0.9`, its `0.8` migration, and preservation of every historical schema and snapshot;
- adapter-conformance coverage for two live adapters plus the existing third-party and legacy fixtures;
- two-adapter budgets and the release-automation changes a fourth package requires.

Out of scope:

- modifying the analyzed application's source, `composer.json`, `composer.lock`, or `vendor/`;
- applying or simulating source or configuration remediations between stages;
- Symfony recipe execution, Flex operations, or anything that runs the analyzed application;
- a Symfony console command, or any second entry point beyond the generic CLI, in this release;
- a CodeIgniter package or any fifth adapter;
- a static PHP language and API deprecation catalog beyond Composer platform evidence;
- pull-request creation, hosted uploads, dashboards, telemetry, or SaaS storage;
- AI-generated compatibility claims or migration instructions;
- PHAR or versioned container delivery;
- raising the shared runtime floor above PHP `^8.0`.

## Inherited Product and Test Rules

Restated because a second adapter is the first real test of several of them:

- Composer remains the dependency solver. Never infer a successful stage without running it.
- The analyzed project is immutable input. Every mutation happens in an analyzer-owned temporary workspace.
- Core stays framework-neutral. Adapters own detection, version semantics, targets, rule catalogs, source defaults, package families, and source-usage visitors. A concept only one adapter can interpret does not belong in core.
- JSON is canonical, Markdown is a projection with no independent analysis logic and no fabricated values.
- Evidence IDs are unique and deterministic; unsupported claims are uncertainty.
- Source inspection stays static and parser-based, always against the original project snapshot.
- Preserve `UpgradeAnalyzer::analyzeUpgrade(UpgradeRequest): UpgradeReport` as the single public operation.
- Redaction and path-exposure policy apply at model ingress and every publication boundary.
- PHP `^8.0` language floor in all shipped runtime code.

Four test layers are retained: offline unit tests; deterministic Composer integration tests over committed `path` repositories; curated application-shaped fixtures with immutability and JSON-first approvals; and networked installation and live-application smoke tests kept out of the deterministic gate.

## Milestone 0: Confirm the Theme, Freeze v0.3.0, Lock the v0.4 Contract

Priority: P0. Complete before changing report shape or development identity.

- [ ] Analyze two or three real applications with the published v0.3.0 packages and record what the reports got right, what they missed, and what a reader would have had to do next.
- [ ] Collect whatever feedback the published line produces within a bounded window and record it beside those notes.
- [ ] Confirm or replace the release theme against that evidence. A second published adapter is the recommended answer and the one this plan assumes; a different signal from real use outranks the recommendation and must reopen the decision before Milestone 1 starts.
- [ ] Freeze the signed v0.3.0 public surface — PHP operation, CLI and Artisan behavior, adapter metadata, exit policy, schema `0.8`, staged-analysis semantics, and the Laravel transition matrix — as immutable compatibility evidence under `tests/fixtures/contracts/v0.3.0`.
- [ ] Split historical v0.3 compatibility assertions from live development-version and release-policy assertions, following the v0.2 precedent. Do not weaken existing contract tests by search-and-replace.
- [ ] Create and protect the `0.3.x` maintenance branch while the tree still carries its `0.3.x` verifier, aliases, and constraints.
- [ ] Add a machine-readable v0.4 contract and dedicated tests for every new identity, attribution, arbitration, ordering rule, and budget.
- [ ] Define the framework version-identity contract: what an adapter declares, how two versions are ordered, how a hop is named, and how stage IDs stay stable and collision-free across adapters.
- [ ] Define multi-adapter semantics before writing adapter code: activation, deterministic ordering, stage-provider arbitration, package-family collision resolution, source-usage visitor composition, and per-finding attribution.
- [ ] Define family-scoped stage targets: how an adapter declares a package family, how rooted members are enumerated from project state, and how the resulting manifest is proved by Composer rather than assumed.
- [ ] Re-derive hop, attempt, scenario, process, runtime, memory, and report-size budgets for two active adapters and record whether the v0.3 caps still hold.
- [ ] Approve schema `0.9` and its `0.8` migration, then add the immutable schema file and a minimal canonical serialization fixture before Milestone 1 emits new fields.
- [ ] Record the decision to keep the Symfony console command, a wider Symfony matrix, CodeIgniter, PHP deprecation catalogs, PHAR, container delivery, and runtime-floor changes out of v0.4.
- [ ] Atomically switch `main` to v0.4 development identity, schema `0.9`, `0.4.x-dev` aliases, `^0.4` internal constraints, and a verifier permitting only `0.4.x` from `main`.

Acceptance gate: the release theme is confirmed against evidence from real use rather than architecture alone; immutable v0.2.1 and v0.3.0 evidence remains green; the `0.3.x` branch can still verify its own line; and `main` identifies every subsequent build as v0.4 under schema `0.9` after a machine-checked contract defines identity, arbitration, attribution, family targets, and budgets.

Status: not started.

## Milestone 1: Framework Version Identity and Schema 0.9

Priority: P0.

- [ ] Replace integer `from_major` and `to_major` hop identity with the approved framework-declared version identity across models, guidance, stages, plan actions, and evidence references.
- [ ] Keep ordering, comparison, and gap detection inside a tested value object; adapters declare versions and their ordering rule, core never parses framework version semantics.
- [ ] Preserve stable, deterministic, human-readable stage IDs under the new identity, and prove no ID collides when two adapters are active.
- [ ] Support minor-precision hops, including a same-major deprecation-clearing hop, without weakening the gapless-path rule.
- [ ] Complete strict schema `0.9`, canonical snapshots, Markdown projection, and evidence-integrity checks.
- [ ] Add a consumer migration fixture from `0.8` and document exactly which fields moved, which are additive, and which are removed.
- [ ] Preserve schemas `0.2` through `0.8` and every historical snapshot byte-for-byte.
- [ ] Prove the Laravel transition matrix produces semantically identical findings under the new identity, with snapshot changes limited to documented migration effects.

Acceptance gate: a minor-versioned framework path is representable and evidence-backed; Laravel output is unchanged except for documented migration effects; and every schema `0.9` finding resolves to valid evidence.

Status: not started.

## Milestone 2: Multi-Adapter Core

Priority: P0.

- [ ] Support several simultaneously active integrations with deterministic ordering, and cover activation, non-activation, and mutual-exclusion cases.
- [ ] Replace the single-stage-provider restriction with deterministic arbitration: an explicit `--framework` request wins, otherwise the provider whose declared family owns the requested root targets wins; an unresolvable case still skips with conflict evidence.
- [ ] Resolve package-family collisions deterministically and stop the Laravel adapter from being the implicit owner of `symfony/*` when a Symfony adapter is active.
- [ ] Attribute every framework finding, guidance entry, stage, rule pack, and family label to the adapter that produced it, and expose that attribution in schema `0.9`.
- [ ] Compose source-usage visitors from several adapters without duplicate usages, cross-adapter evidence bleed, or one adapter's failure suppressing another's findings.
- [ ] Keep an inactive adapter completely silent: no usage types, no families, no guidance, no uncertainty entries.
- [ ] Cover the realistic combinations: Laravel-only, Symfony-only, Laravel with rooted Symfony components, a Symfony application with a Laravel-family package installed transitively, both adapters installed with neither activating, and the test adapters alongside both.
- [ ] Prove deterministic, byte-identical canonical output regardless of adapter installation order.

Acceptance gate: two adapters coexist with deterministic activation, arbitration, attribution, and family ownership; no adapter can influence a project it did not detect; and installation order cannot change canonical output.

Status: not started.

## Milestone 3: Symfony Detection and the Approved Hop Pair

Priority: P0.

- [ ] Detect Symfony conservatively from rooted `symfony/framework-bundle`, `symfony/runtime`, or rooted `symfony/*` components, preferring exact locked versions over root constraints.
- [ ] Never activate on transitively installed Symfony components. This is the Illuminate lesson restated: the analyzer's own dependencies and every Laravel application would otherwise trigger false detection.
- [ ] Report inconsistent rooted component versions as uncertainty rather than choosing one.
- [ ] Establish the approved hop pair from commit-pinned official upgrade guides and exact manifests, and record why those endpoints were chosen.
- [ ] Build a versioned, typed Symfony rule catalog mirroring the Laravel catalog's structure, with test-time validation of duplicate keys, missing sources, invalid SemVer, coverage gaps, and contradictory advice.
- [ ] Encode the approved hop pair only, with exact PHP requirements, component constraints, first-party bundle migrations, and commit-pinned evidence. Everything outside it is an honest unsupported result.
- [ ] Distinguish exact metadata and source evidence from documentation-derived guidance, and label structural or recipe-related review work as low-confidence review locations, never confirmed incompatibilities.
- [ ] Cover the high-signal source patterns the parser can prove — bundle registration, service configuration references, removed and renamed classes, deprecated attributes and annotations — and nothing that requires container resolution.
- [ ] Own the `symfony/*` package-family classification, and keep Doctrine, Twig, and other ecosystem families separate from framework families.
- [ ] Add offline application-shaped fixtures for a feasible path, an advisory-heavy path, a blocked path, an ambiguous source version, and an unsupported range.

Acceptance gate: Symfony detection is evidence-backed and never fires transitively; the catalog validates at test time; and every emitted finding links to exact project, package, solver, or commit-pinned maintainer evidence.

Status: not started.

## Milestone 4: Symfony Staged Solving

Priority: P0.

- [ ] Provide Symfony stage targets through the optional stage-target contract, at minor precision, with evidence-backed PHP requirements and stable stage IDs.
- [ ] Move every rooted member of the declared Symfony family together in one stage target, and record the enumerated member list as evidence.
- [ ] Run bounded isolated Composer strategies and remediation rounds per stage, carrying the selected candidate state forward exactly as the Laravel chain does.
- [ ] Skip honestly outside the approved hop pair, on an ambiguous endpoint, on a missing hop, or when no safe exact stage PHP exists.
- [ ] Prove one offline Symfony fixture end to end with the Laravel adapter also installed: both integrations activate only where their own evidence applies, the `symfony/*` family is attributed once under the arbitration rule, and staged solving does not skip on a provider conflict.
- [ ] Require that the slice needs no Symfony-specific branch in core. Any core change it forces must be expressed as a neutral contract and must leave Laravel canonical reports unchanged except for documented schema `0.9` migration.
- [ ] Prove the original fixture remains byte-for-byte unchanged for success, failure, timeout, and debug cleanup paths.
- [ ] Prove direct final-target resolution stays independent of staged resolution for Symfony, as it is for Laravel.
- [ ] Measure how much of a real Symfony upgrade the report explains without recipe or Flex knowledge, and record the honest answer in the limitations page.

Acceptance gate: one approved Symfony hop pair produces real Composer evidence alongside an active Laravel adapter, without a Symfony-specific branch in core, and the report states plainly which parts of a Symfony upgrade it cannot see.

Status: not started.

## Milestone 5: Quality, Budgets, and Supply Chain for Four Packages

Priority: P1.

- [ ] Extend adapter conformance coverage to two live adapters plus the third-party and legacy fixtures: stable IDs, version identity and ordering, exact target constraints, PHP evidence, duplicate targets, conflicting providers, missing metadata, and invalid provider output.
- [ ] Prove an adapter written against the v0.3 contracts still loads under v0.4 with a widened Core constraint, contributes guidance, and makes no staged or attribution claims it cannot support.
- [ ] Re-measure the 2026-08-16 audit's residual structural findings against the current tree and either close them or record them with current line numbers.
- [ ] Enforce re-derived two-adapter budgets for process count, per-stage and aggregate runtime, memory, report size, redaction, and deterministic rerun on Linux and Windows.
- [ ] Extend selective mutants to version identity and ordering, arbitration, family collision, attribution, Symfony detection, and family-scoped targets.
- [ ] Continue the coverage ratchet and make the new identity, arbitration, attribution, and Symfony catalog classes critical modules.
- [ ] Measure the compatibility and release matrices before the fourth package multiplies them, and keep total CI time from regressing against the v0.3 baseline.
- [ ] Add Symfony transcript and catalog fixtures so upstream drift stays separable from parser or solver drift.
- [ ] Retain dependency audits, commit-pinned actions, archive checksums, dependency inventory, provenance, signed distribution verification, secret canaries, and target-immutability gates.
- [ ] Preserve the PHP `^8.0` runtime floor and add Symfony host-installability coverage alongside the existing Laravel matrix.

Acceptance gate: the worst supported two-adapter request is bounded, deterministic, private, and mutation-protected, and CI is no slower than the v0.3 baseline without weakening any existing gate.

Status: not started.

## Milestone 6: v0.4 Documentation, Migration, and Release

Priority: P0.

- [ ] Update README, installation, external-analysis, CLI, schema, limitations, troubleshooting, adapters, versioning, contribution, security, and release documentation for approved v0.4 behavior.
- [ ] Document version identity, multi-adapter activation and arbitration, family ownership, attribution, the approved Symfony hop pair and its honest gaps, and the `0.8` to `0.9` migration.
- [ ] Extend release automation, the verifier, `tools/prepare-distribution.sh`, `tools/release-distribution.sh`, and the release checklist to four packages and four distribution repositories.
- [ ] Verify the protected `0.3.x` maintenance branch still carries compatible aliases, constraints, schema, and release verification after all v0.4 work on `main`.
- [ ] Replace the development identity with exact `0.4.0`, prepare the dated changelog and release notes, and re-verify schema `0.9`, aliases, constraints, and the workflow contract together.
- [ ] Run the deterministic gate on every supported PHP runtime plus required Windows coverage.
- [ ] Run cross-host profile proofs, the restricted Composer harness, worst-case two-adapter budgets, and all privacy canaries.
- [ ] Run normal and lowest-dependency consumers for every advertised Laravel and Symfony host line.
- [ ] Run fresh-clone and release-artifact consumer audits on Windows and Linux using direct and staged analyses through both adapters.
- [ ] Produce checksum-bound archives for all four packages with dependency inventory and source/build provenance.
- [ ] Create matching verified signed tags in the monorepo and all four distribution repositories, synchronize Packagist, and verify exact published source and distribution references.
- [ ] Reproduce documented Laravel and Symfony quick starts from published packages and prove both target fixtures remain byte-for-byte unchanged.
- [ ] Move `0.3.x` to archival terms at publication, on the public pages and in this plan's support policy.

Acceptance gate: published v0.4 packages validate schema `0.9`, reproduce every claimed stage for both frameworks under the declared platform and execution policy, preserve v0.3 migration evidence, and retain all read-only, privacy, compatibility, and supply-chain guarantees.

Status: not started.

## Principal Risks and Controls

| Risk | Control |
|---|---|
| The release theme is chosen from architecture rather than demand | Milestone 0 gates the theme on dogfooding and published-line feedback, and allows the answer to change |
| Symfony upgrades are recipe-driven, so a static analyzer explains less of them than it does for Laravel | Milestone 4 measures the explained fraction on a real fixture and publishes the honest limit; if the report cannot explain a useful share of the work without executing recipes, stop after the hop pair and reconsider the theme rather than widening the matrix |
| Version identity touches every hop, stage, guidance, and evidence path | Contract and schema first in Milestone 0, one tested value object in Milestone 1, Laravel snapshots as the regression proof |
| Two adapters collide on package families and stage providers | Deterministic arbitration and attribution defined before adapter code, with collision evidence instead of silent skips |
| A fourth package multiplies release and CI cost | Milestone 5 measures the matrices before Milestone 6 pays for them |
| Scope creep repeats the v0.3 breadth expansion | The Symfony matrix is one approved hop pair; the console command, wider matrix, migration guide, conformance tooling, and process-count work are already parked in the v0.5 proposal |
| An unsupported line is left exposed | `0.3.x` stays supported until v0.4.0 publishes, and the public pages change in the same release |

## Deferred Until After v0.4.0

- The Symfony console command and any second framework entry point.
- A wider Symfony transition matrix beyond the approved hop pair.
- The adapter migration guide with a worked diff, and any published conformance test kit.
- Reducing the worst-case Composer process count by caching equivalent scenario executions.
- CodeIgniter, Doctrine, or any fifth adapter.
- A static PHP language and API deprecation catalog.
- Pull-request creation, hosted uploads, dashboards, telemetry, or SaaS storage.
- AI-generated compatibility claims or migration instructions.
- PHAR or versioned container distribution.
- Raising the shared runtime floor above PHP 8.0.

These are collected with rationale in [the v0.5 proposal](DEVELOPMENT_PLAN_0.5.0-PROPOSAL.md), which authorizes nothing.

## Recommended Next Work Session

Start Milestone 0 from its first item, not from its contract work: analyze two or three real applications with the published v0.3.0 packages and write down what the reports actually delivered. Everything after that in this plan assumes the second-adapter theme survives that evidence.

Operational notes carried forward:

- The local gate needs `COMPOSER_PROCESS_TIMEOUT=0`; the symptom and the command are recorded in [CONTRIBUTING.md](../CONTRIBUTING.md).
- Branch protection covers `main` and `0.2.x`, but not tags. The signed `v0.1.0`, `v0.2.1`, and `v0.3.0` tags are the evidence base for every frozen compatibility contract and can still be deleted or moved; a tag ruleset on `v*` would close that.
