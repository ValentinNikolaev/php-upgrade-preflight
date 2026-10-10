# PHP Upgrade Preflight Development Plan

Last updated: 2026-10-10

- Released baseline: `0.3.5` (published 2026-10-07 Europe/Rome; 2026-10-06 at 23:03:05 UTC)
- Released report schema: `0.8`
- Immediate work: Pre-v0.4 Readiness Milestone R; the bounded Laravel completion milestone remains complete
- Active development target: `0.4.0`
- Planned v0.4 report schema: `0.9`

This roadmap supersedes the archived [v0.3.0 implementation plan](DEVELOPMENT_PLAN_0.3.0.md), which records the completed v0.3 milestones and the v0.3.0 release evidence. The archive was copied from the completed plan before this file was replaced.

v0.3 made the analyzer honest about *how* an upgrade would be reached: staged Composer evidence, candidate-state chaining, blocker lifecycles, closed-world platform profiles. It did all of that with one framework adapter.

The proposed v0.4 theme is a second published adapter that tests the core's framework neutrality through a real integration. That is a useful architecture experiment, but it is not evidence that users need Symfony next. The [2026-10-10 product and engineering review](audits/2026-10-10-product-engineering-review.md) adds Readiness Milestone R before contract migration: repair the confirmed input-isolation defect, prove that readers can make better upgrade decisions, and choose the smallest justified next release. Symfony remains the conditional direction of Milestones 0–6 until R records a decision.

## How to Use This Plan

- Continue the first unchecked item in the earliest incomplete milestone unless repository evidence requires a safer order.
- Execute Readiness Milestone R before Milestone 0 or any Symfony implementation. Preserve existing milestone numbers and completed historical evidence; R adds a dependency, not a new version or a release authorization.
- Mark work `[~]` only while someone is implementing it. Mark it `[x]` only after the acceptance evidence passes.
- Reconcile this plan in the same change whenever roadmap work is completed, partially completed, reopened, or reverted.
- Complete the intermediate Laravel completion milestone before v0.4 Milestone 0. Keep v0.3.x work limited to security fixes, regressions, dependency maintenance, evidence-backed corrections to the existing Laravel rule packs, documentation corrections, and release-process repairs. Put new supported transition modes, version identity, multi-adapter behavior, and the Symfony adapter in v0.4.
- Do not switch development aliases, internal constraints, report identity, release branches, or release-verifier policy piecemeal. Milestone 0 owns that coordinated migration.
- Recheck external release and package state before acting. Local Git state cannot prove that GitHub, distribution repositories, or Packagist did not change later.
- Update public documentation in the same change as behavior, commands, report semantics, supported versions, trust boundaries, or release policy.

## Version and Contract Vocabulary

| Contract | State entering v0.4 | v0.4 direction |
| --- | --- | --- |
| Tool and package line | `0.3.5` published; bounded Laravel completion accepted; `0.3.x-dev` aliases; `^0.3` internal constraints | `0.4.0`; identity switched atomically in Milestone 0 |
| Canonical report | Schema `0.8` | New schema `0.9` for framework-declared version identity and adapter attribution |
| Published packages | `core`, `cli`, `laravel` | Adds `symfony` as a fourth published package and distribution repository |
| Active release policy | `0.3.x` from `main`; `0.2.x` and `0.1.x` archival | `0.4.x` from `main` once Milestone 0 establishes the protected `0.3.x` branch |

Schemas `0.2` through `0.8` and every signed compatibility artifact remain immutable. Packages continue to derive exact versions from matching signed Git tags rather than manifest `version` fields, and all published packages release in lockstep.

## Support Policy Across Lines

`0.3.x` is supported until v0.4.0 is published: security fixes, regressions, dependency maintenance, documentation corrections, and release-process repairs, prepared from its own protected branch. At the moment v0.4.0 publishes, `0.3.x` becomes archival on the same terms as `0.2.x` and `0.1.x` — signed artifacts and schemas stay available and immutable, and the line receives nothing further, security fixes included. That is exactly what happened to `0.2.x` when v0.3.0 shipped, and the public pages state it.

## Released v0.3.x Baseline

The current published baseline is documented in the [v0.3.5 release notes](../docs/releases/v0.3.5.md). The [v0.3.0 release notes](../docs/releases/v0.3.0.md), [v0.3.1 release notes](../docs/releases/v0.3.1.md), [v0.3.2 release notes](../docs/releases/v0.3.2.md), and [v0.3 contract](../docs/v0.3-contract.md) retain the earlier release and contract evidence.

- Schema `0.8` carries required `staged_resolution`, Composer execution provenance, target-platform-profile projections, adjacent stage attempts, candidate-state fingerprints, and blocker lifecycle history.
- Laravel guidance covers 7 to 8, the retained direct 7 to 9 path, and every adjacent hop from 8 to 9 through 12 to 13, with real Composer evidence per contiguous stage.
- Framework-shaped source inspection is adapter-owned behind `SourceUsageVisitorProvider`; core no longer interprets another framework's application skeleton.
- Vocabularies that reach the report — severity, confidence, blocker type, solver relation — have single owners and validate at construction.
- Excerpt truncation and redaction failure are visible in canonical output, closing the last open finding of the 2026-08-16 architecture audit.
- v0.3.0 was published from `main` at `3959b0fe` through release run 32136742538, with verified signed tags in four repositories, byte-compared distribution payloads, checksum-bound archives, and a published-package quick start that left the analyzed fixture unchanged.
- v0.3.1 followed on the same day from `83a9ba2f` through release run 32178181503. It reports tool `0.3.1` on unchanged schema `0.8`, makes excerpt truncation and redaction failure visible, and replaces the pre-publication documentation the v0.3.0 packages had shipped with.
- v0.3.2 was published from `e6744c09` through release run 32272063360. It keeps schema `0.8` and the analyzer behavior unchanged while publishing the MIT relicensing, the GitHub Pages and four-destination Wiki surfaces, and the repaired offline demo.
- v0.3.3 was published from `3725603a` through the verified release workflow. It keeps schema `0.8` and the PHP `^8.0` runtime floor while adding the interactive wizard, terminal progress, optional report copies, package metadata lookup modes, and the wizard-first Pages workflow.
- v0.3.5 was published from `60f0c49f5a357406e37ee75ffcb03c2b90abf43f` through [release run 37543254451](https://github.com/ValentinNikolaev/php-upgrade-preflight/actions/runs/37543254451), with all 45 jobs successful and none skipped. It publishes the bounded Laravel corrections, earlier merged hardening and maintenance, and the distribution file-mode repair while retaining schema `0.8`, the PHP `^8.0` floor, and existing patch contracts. The v0.3.4 distribution-only candidate remains an immutable recovery record, not the final baseline.

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

The intermediate Laravel completion milestone is complete in published v0.3.5. Keep subsequent `0.3.x` maintenance compatible with schema `0.8`, the public PHP operation, CLI and Artisan behavior, adapter metadata, exit policy, staged-analysis semantics, and supported Laravel transitions. Establish and protect the `0.3.x` maintenance branch in Milestone 0 before `main` adopts v0.4 identity, so urgent patch work never requires backporting v0.4 behavior.

### v0.4.0

If R approves the Symfony direction, v0.4.0 delivers a proven second framework, deliberately narrow:

- framework-declared, ordered version identity for hops and stages, replacing integer majors, under schema `0.9` with a documented `0.8` migration;
- multi-adapter activation, deterministic stage-provider arbitration, package-family collision rules, and per-finding adapter attribution;
- family-scoped stage targets that move every rooted component of a declared package family together;
- a published `php-upgrade-preflight/symfony` adapter with detection, a versioned rule catalog, and staged solving across one approved hop pair — the same-major deprecation-clearing hop and the major hop that departs from it — held to the same evidence standard as Laravel;
- the existing PHP `^8.0` runtime floor. The Symfony requirement applies to the analyzed project and never raises the analyzer floor, exactly as the Laravel 13 requirement did not.

Deferred to [the v0.5 proposal](DEVELOPMENT_PLAN_0.5.0-PROPOSAL.md) rather than dropped: the Symfony console command, a broader Symfony matrix, extended adapter migration tutorials, published conformance tooling, and speculative Composer caching. Minimum migration examples for changed contracts ship with v0.4. R may pull forward a narrowly justified performance repair when measured cost prevents useful analysis; it does not authorize a general cache or removal of safety checks.

### Separate maintenance patch: GitHub Actions JavaScript runtime

The bounded cache-action refresh already merged in [PR #23](https://github.com/ValentinNikolaev/php-upgrade-preflight/pull/23): Quality and Compatibility smoke use `actions/cache@55cc8345863c7cc4c66a329aec7e433d2d1c52a9` (v6.1.0, Node.js 24). Commit `660b2b1892bfba43fefebe0ed096230e390b7a3f` is included in signed v0.3.5, but is not an ancestor of signed v0.3.3; the latter retains the cache v4 pin. This is not evidence that every JavaScript action or deprecation annotation has been audited.

- [x] Publish the reviewed, immutable Node.js 24 cache-action pin in the v0.3.5 baseline.

Separate audit TODO: complete the broader JavaScript-action inventory and verify any remaining runtime maintenance before the v0.4.0 release gate. This work does not change the v0.4 product, schema, adapter, command, or compatibility scope.

- [ ] Inventory every pinned JavaScript action and record which revisions still target a deprecated Node.js runtime; do not infer completion from the cache-action refresh.
- [ ] Replace any remaining affected actions with reviewed immutable commit SHAs from upstream releases that target GitHub's supported JavaScript runtime; do not replace SHA pins with floating tags.
- [ ] Preserve workflow permissions, cache keys, restore-key behavior, cross-platform paths, concurrency, artifact retention, and existing job topology unless a separately reviewed compatibility change is required.
- [ ] Run workflow static validation and the complete Quality and CodeQL matrices on Linux and Windows, including all supported PHP versions.
- [ ] Confirm the completed runs contain no Node.js 20 deprecation annotation and that cache restore/save behavior remains visible and successful where expected.
- [ ] Publish the change independently of feature work, with rollback instructions and links to the upstream action release notes and the validating workflow runs.

Acceptance gate for the remaining audit: all JavaScript actions remain commit-pinned, no workflow run reports a deprecated Node.js 20 action runtime, existing security permissions and cache semantics are unchanged, and the full required CI matrix passes. The cache refresh alone does not close this audit or authorize any v0.4 feature or contract change.

## v0.4 Scope and Non-Goals

The feature scope below is conditional on R5. Readiness fixes and evidence collection precede it; if R chooses another direction, revise this section and its contracts before feature implementation.

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

## Intermediate Milestone: Laravel Completion Through v0.3.x Patches

Priority: P0. Finish before v0.4 Milestone 0 and before Symfony implementation. This is new follow-up work; the archived v0.3 implementation milestones remain complete.

Goal: close every patch-compatible Laravel gap identified by real use and a bounded coverage review, publish the corrections, and account explicitly for the known contract exclusions. Completion means reviewed coverage and tested behavior within the declared scope, not exhaustive support for every Laravel application or ecosystem package.

Sources: [published patch policy](../docs/versioning.md), [v0.3 staged contract](../docs/v0.3-contract.md), [documented limitations](../docs/limitations.md), [approved Laravel matrix](../docs/laravel-v0.2-transition-scope.md), `packages/laravel/src/Catalog`, `packages/laravel/src/LaravelStagePlanner.php`, and the Laravel unit, integration, parity, and snapshot suites.

Current evidence: [verified compatibility baseline](../docs/laravel-coverage/compatibility-baseline.json), [complete guide accounting](../docs/laravel-coverage/README.md), and [pinned application evaluation](../docs/laravel-coverage/application-evaluation.md). Current patch expectations live separately from historical contracts in `tests/fixtures/laravel-completion/transition-expectations.json`.

Patch boundary: retain schema `0.8`, PHP `^8.0`, existing public interfaces, command and exit behavior, the three-package release set, the supported Laravel 7-13 major-transition matrix, and the independent direct/guidance/staged dimensions. Finding, evidence, diagnostic, and documentation corrections fit this milestone. Changes to serialized shape or supported transition modes require a minor-line contract decision and are tracked for v0.4. No version number is reserved by this plan; verify remote releases and package references before choosing each patch.

### Gap-to-deliverable map

| Observed gap or limit | Required outcome | Step |
| --- | --- | --- |
| No recorded evaluation on 2-3 real applications | Before/after reports, expected upgrade work, false positives, missed findings, and reader follow-up | L1 |
| Upgrade-guide changes are only partially encoded, explicitly including Laravel 13 | Every reviewed guide item classified; all patch-compatible detectable omissions corrected; manual review items explained | L1, L2 |
| Package coverage is a curated subset | Inventory all first-party and guide-mentioned packages rooted in the evaluation corpus; correct ranges/advice or record why no rule applies | L1, L2 |
| Static source and skeleton findings cannot prove runtime behavior | Exact source checks where possible, confidence tests, and concrete manual verification for dynamic/container/configuration behavior | L2, L3 |
| Illuminate-only projects lack staged solving | Prove detection, supported guidance, direct solving, and explicit staged refusal; record the family-target extension needed for v0.4 | L0, L3 |
| Mixed Laravel-family targets lack staged solving | Prove direct-target preservation and explicit staged refusal; specify the rooted-member and constraint cases for a v0.4 family-target decision | L0, L3 |
| Same-major upgrades lack framework guidance and staging | Prove direct resolution remains usable and the guidance/staging limits are explicit; record the version-identity decision needed for v0.4 | L0, L3 |
| Laravel currently classifies `symfony/*`; multiple stage providers conflict | Protect existing v0.3 behavior with tests; hand the collision cases to v0.4 Milestone 2 | L0, L3, L5 |

### L0: Establish the patch compatibility baseline

Dependencies: none. Output: a reviewed compatibility checklist and an initial gap ledger linked from this milestone.

- [x] Verify the latest published `0.3.x` release in the monorepo, distribution repositories, and Packagist; record exact versions, source references, and the reviewed implementation commit.
- [x] Record the existing schema, command, adapter, transition, and staged-skip contracts before editing behavior. Preserve all signed historical schemas, reports, and fixtures; keep new patch expectations separate from frozen artifacts.
- [x] Classify every map entry as a confirmed defect, missing evidence, or deliberate limitation. For each proposed fix, record its affected contract and whether it fits the published patch policy.
- [x] Record that enabling Illuminate-only/mixed-target staging or same-major framework transitions changes the declared v0.3 scope. Preserve those refusals in this patch milestone; carry explicit acceptance cases into the v0.4 contract decision rather than treating documentation as implemented support.

Acceptance: each known gap has an owner area, patch disposition, and failure-revealing check. Historical compatibility tests cannot be weakened to make a new behavior pass.

### L1: Evaluate real applications and audit guide/package coverage

Dependencies: L0. Output: a bounded evaluation corpus, feedback record, and coverage ledger.

- [x] Select three real Laravel applications covering a legacy 7/8/9 project, a 10/11 project retaining its existing skeleton, and a 12/13 project with rooted ecosystem dependencies. Pin authorized snapshots to exact commits; record substitutions and any unavailable case. Keep sensitive project details out of committed evidence.
- [x] Analyze those snapshots with the exact published baseline from a separate tools directory. Record requests, platform/execution policy, canonical reports, normalized fingerprints, immutability checks, timings, and what the maintainer actually needs to change next. Keep live/networked runs separate from the deterministic gate.
- [x] Compare report findings with official upgrade guides, package manifests, and independently reviewed expected work. Record missed findings, false positives, wrong ranges, weak confidence, and unclear next actions; do not infer runtime correctness from Composer success.
- [x] Review every guide section for 7->8 and each adjacent hop through 12->13, retaining the direct 7->9 path. Pin upstream guide/manifest sources to real reviewed commits; classify every item as implemented, patch omission, manual review, not applicable, or contract-dependent. Review changes since the existing pins without overwriting historical evidence.
- [x] Inventory the corpus's rooted first-party packages and all packages mentioned by the reviewed guides. Cover package/test-tool/extension/PHP requirements, removed or renamed symbols, configuration and skeleton changes, and migration or runtime tasks that require manual verification. Bound the ecosystem claim to this recorded inventory.
- [x] Review a bounded public-feedback window with actual start/end dates and record received feedback or its absence; identify this as retrospective review, not fresh solicitation. Convert confirmed findings into minimal sanitized application-shaped fixtures; do not import arbitrary public applications into offline tests.

Acceptance: every reviewed guide item and inventoried package has a recorded disposition, and every confirmed omission or false positive maps to a reproducible fixture or an explicit manual-review limitation. Record corpus access blockers; fabricated substitute evidence cannot close the gate.

### L2: Correct the existing Laravel rule packs

Dependencies: L1. Output: small independently reviewable patches in `packages/laravel`, with evidence and regression coverage.

- [x] Correct every patch-compatible omission found in L1, prioritizing incorrect blockers, missing exact package/platform requirements, and source findings that can be proved statically. Review Laravel 13's bounded pack as thoroughly as the earlier hops.
- [x] Review first-party and guide-mentioned package guidance against exact manifests and maintainer sources. Correct direct/transitive applicability, compatible constraints, replacement/removal advice, and test-tool transitions. Unknown or unreviewed versions must remain uncertainty.
- [x] Add or correct parser-based checks for proved removed/renamed APIs and supported configuration references. Keep framework knowledge inside the Laravel adapter and source inspection against the original snapshot.
- [x] Protect the optional Laravel 11 skeleton choice: retaining the Laravel 10 skeleton must not itself create a mandatory migration. Keep structural review locations at appropriate confidence and avoid presenting dynamic/container behavior as confirmed incompatibility.
- [x] For each corrected rule, add a case that would fail before the fix and a negative/non-applicable case. Verify hop attribution, evidence references, severity, confidence, duplicate handling, and catalog validation before reviewing snapshot changes.

Acceptance: all patch-compatible L1 defects are fixed with E1-E4 evidence where applicable; remaining manual work is named with its reason and verification action. Unrelated baseline findings do not change silently.

### L3: Verify supported paths and every documented refusal

Dependencies: L2, using L0's contract decisions. Output: expanded full-analyzer and command-parity coverage.

- [x] Exercise feasible and blocked/advisory outcomes for every approved adjacent hop, the direct 7->9 guidance path, and representative multi-hop chains. Verify real offline Composer evidence, carried candidate state, multiple blockers and lifecycles, and independent direct/guidance/staged outcomes.
- [x] Cover rooted Illuminate-only projects, mixed framework/component targets, inconsistent rooted component versions, same-major targets, downgrades, ambiguous ranges, missing hops, and endpoints outside Laravel 7-13. Assert the declared guidance status and staged refusal with evidence-backed reasons while verifying direct solving still honors all valid supplied targets.
- [x] Cover explicit absent extensions, unavailable exact stage PHP, provider conflicts, transitive-only framework dependencies, and active Laravel package-family labels. Include the Laravel/Symfony collision cases in the v0.4 handoff without changing ownership semantics in patches.
- [x] Verify CLI/Artisan canonical parity, JSON/Markdown projection, deterministic normalization, and target immutability for the new fixtures. Cover timeout/failure cleanup and the documented debug retention path without deleting user-owned diagnostics.
- [x] Rerun the pinned real-application analyses against the corrected candidate with the same declared inputs. Explain changed findings and measured outcomes; report live ecosystem drift separately from product regressions.

Acceptance: every supported transition and known exclusion is exercised, all corrected findings resolve to evidence, and before/after application reports demonstrate the corrections without inventing new staged support.

### L4: Verify patch quality and publish accurate coverage documentation

Dependencies: L3. Output: passing candidate gates and documentation of the reviewed Laravel coverage.

- [x] Run focused Laravel/catalog/contract/parity checks, then the complete deterministic `composer check` gate. Use the documented Docker invocation with `COMPOSER_PROCESS_TIMEOUT=0` when needed; complete the PHP 8.0-8.5 and required Windows CI coverage.
- [x] Run coverage, selective mutation, staged-budget, privacy, and immutability gates. Extend the selective checks for corrected critical rules; preserve existing process/runtime/memory/report-size limits.
- [x] Verify normal and lowest-dependency consumer installs for every advertised Laravel 8-13 host line through `Compatibility smoke`, including the separate-tools workflow for older analyzed projects. Record current upstream/installability failures separately.
- [x] Update affected README, CLI/Artisan, limitations, transition coverage, troubleshooting, Wiki, site claims, and `[Unreleased]` notes. Explain how users handle Illuminate-only, mixed-target, same-major, dynamic-source, and manual migration cases today.

Acceptance: the patch candidate passes required checks and public claims match the coverage ledger. No checklist item becomes complete solely because its implementation or documentation exists.

### L5: Release the corrections and hand off the final v0.3 baseline

Dependencies: L4. Output: published and verified compatible patches, a closed gap ledger, and the v0.4 handoff.

- [x] Group corrections into coherent `0.3.x` patches; recheck remote state before allocating each version. Reconcile any already merged unreleased Laravel-relevant fixes and evidence so the release notes describe the actual payload.
- [x] For every patch, follow `docs/release-checklist.md` and `wiki/Release-Wiki-Strategy.md`: verify source claims, update canonical and destination Wiki pages, run `composer release:wiki:check`, attach versioned four-destination evidence with real reviewed/published remote SHAs, validate links/sidebar coverage, and publish the matching Wiki commits before release completion.
- [x] Verify coordinated release identity, signed tags in the monorepo and three distribution repositories, distribution payloads, archive checksums/provenance, Packagist references, and published-package CLI/Artisan quick starts. Keep schema `0.8`, `0.3.x-dev` aliases, and `^0.3` internal constraints. An unavailable required Wiki publication blocks release completion.
- [x] Close each gap with its fix and acceptance evidence, or with its tested contract limitation, concrete user workaround, and named v0.4 decision. Re-evaluate the Symfony theme using the real-use results; if a material patch-compatible Laravel defect remains open, this milestone remains incomplete.
- [x] Record the final published `0.3.x` patch as the v0.4 migration/regression baseline, retaining the signed v0.3.0 evidence separately. Carry same-major identity to Milestone 1 and rooted-family/multi-adapter decisions to Milestones 0 and 2 before Symfony Milestone 3.

Acceptance gate: every listed gap has evidence and an explicit disposition; all patch-compatible defects are corrected and published; the required compatibility, quality, privacy, immutability, and Wiki gates pass; and the final patch baseline plus remaining contract decisions are ready for v0.4.

Recovery: implement each correction in a focused change so an unsuccessful candidate can be revised or reverted without unrelated changes. Never move or replace a published signed tag or schema; correct a released regression through the next tested patch. Track temporary paths, consume and delete only task-owned disposable artifacts, and report retained diagnostics and why they remain.

Status: L0–L5 complete within the reviewed scope, published as [v0.3.5](../docs/releases/v0.3.5.md) from `60f0c49f5a357406e37ee75ffcb03c2b90abf43f`. All 299 pinned guide headings have explicit dispositions; three primary applications plus two supplemental snapshots were evaluated without input mutation. The retained v0.3.3 evaluation baseline and signed historical contracts remain unchanged. Manual database/runtime/configuration checks and existing Illuminate-only, mixed-target, same-major, ambiguous and unsupported transition exclusions remain limitations, not new supported modes.

Acceptance evidence: the final Docker `composer check` passed 1,368 unit tests, 94 integration tests, two smoke tests, both static-analysis configurations and lint; coverage, all 18 selective mutations and both staged budgets passed without weakened floors. [Actual tag run 37543254451](https://github.com/ValentinNikolaev/php-upgrade-preflight/actions/runs/37543254451) passed all 45 jobs with none skipped, including required runtime/Windows, consumer, signature, distribution paths/blobs/modes, archive/provenance, Packagist and publication gates. The [four-destination Wiki evidence](../docs/releases/v0.3.5-wiki-evidence.json) records publication before tagging; independently verified published-package CLI/Artisan reports retained schema `0.8` and target immutability. The failed v0.3.4 distribution-only candidate and its three signed tags remain immutable; the repaired v0.3.5 release supersedes it without replacing any tag. Next is Readiness Milestone R, then v0.4 Milestone 0 for the direction R approves.

## Pre-v0.4 Readiness Milestone R: Trusted Reports and Demonstrated User Value

Priority: P0. Depends on the published Laravel completion baseline. Complete before Milestone 0 changes contracts or development identity and before Symfony work in Milestone 3. Owner: maintainer; implementation and independent review may be delegated. R0–R2 are complete; R3–R5 remain open.

Outcome: a reader can distinguish an evidenced blocker from unavailable analysis, identify the next safe action, and explain why this tool adds value to their current workflow. The decision to fund a second adapter follows that evidence. Keep the local, read-only, MIT product, PHP `^8.0` floor, existing valid-report exit policy, and canonical JSON boundary.

### R0: Record the decision baseline

- [x] Build a bounded [evidence ledger](../docs/readiness/decision-baseline.md) from the five pinned application comparisons, current v0.3.5 contracts, and the new review. Record missing/inaccessible raw reports honestly; report hashes do not substitute for readable evidence. Reuse valid evaluations rather than rerunning the entire guide audit.
- [x] Classify the review findings as reproduced defect, source/test-confirmed semantics, measured limitation, or product hypothesis. For each accepted action record priority, owner, affected contract, prerequisite, smallest fix, and observable acceptance evidence.
- [x] Define the first intended user and job: provisionally a PHP/Laravel lead or upgrade consultant making a scope, sequence, or budget decision before implementation. Compare with Composer plus upgrade guides and existing migration/static-analysis tools. Record a reason users would choose this report and a practical discovery/install path; do not infer demand from technical coverage or a quiet issue tracker.

Acceptance: every selected action has evidence and a contract classification; the segment and differentiation are explicit hypotheses; no previously completed Laravel item is reopened merely to repeat its checks.

### R1: Repair the confirmed read-only boundary defect

Depends on R0. This safety repair takes precedence over user studies that execute affected compatible-mode analysis.

- [x] Clear the manifest-selecting `COMPOSER` environment variable for every scenario and diagnostic child process, including compatible mode in `ScenarioWorkspacePreparer::processEnvironment()`. Preserve deliberately compatible authentication/global configuration behavior; audit other ambient settings that can redirect inputs or writes without claiming an OS sandbox.
- [x] Add a regression with ambient `COMPOSER` pointing at a disposable original manifest. Use real offline Composer in both modes; verify intended target solving and byte-for-byte original manifest, lock and source immutability for successful and failed requests, with cleanup/debug retention checks. The test must fail against the reviewed implementation.
- [x] Run focused environment/isolation tests and the complete `composer check` gate; update affected safety documentation and `[Unreleased]` notes. Classify any advisory/disclosure or separate v0.3 patch through the existing security/release policy; this milestone does not itself allocate a tag. If a release is authorized, all existing Wiki and publication gates apply.

Acceptance: no ambient manifest override can redirect a supported scenario outside its analyzer-owned workspace; intended target constraints are actually solved and original files remain unchanged. A green mock-only environment test is insufficient.

### R2: Make assessment limits and next actions understandable

Depends on R0; real affected-project execution also depends on R1.

- [x] Decide how unknown/degraded resolution and source-scan omissions qualify headline risk and effort. Cover missing Composer, timeout, unavailable metadata, invalid input, failed adapter, scan limits and partially executed stages. Absence of observed findings must not be presented as a completed low-risk assessment.
- [x] Separate observed risk drivers from assessment completeness and heuristic effort from a project quote. The existing numeric ranges are uncalibrated planning heuristics; explicitly exclude unobserved migration, deployment, runtime and business validation work. Choose patch-compatible clarification where sufficient; carry any new state/nullability/shape to Milestone 0's schema decision rather than editing published schema `0.8`.
- [x] Review task-based reading of feasible, blocked, unknown, direct/staged disagreement and skipped-stage reports. Identify the first blocking subject, evidence, limitation, next action and required manual validation. Put a concise summary before command transcripts using canonical fields; change canonical semantics first if the needed fact is absent. Keep all evidence available and Markdown a faithful projection.
- [x] Distinguish enforced budgets from advisory memory/report-size targets in user-facing explanations now, and define any structured schema `0.9` representation in Milestone 0. Test missing measurements as missing evidence, not zero or a successful limit check.
- [x] Add behavior tests for accepted semantic/summary changes, CLI/Artisan parity, JSON/Markdown projection and evidence integrity. Run the complete deterministic gate before marking implementation complete; do not freeze incidental copy or pretend usability was proved by snapshots.

Acceptance: the reviewed unknown-resolution case cannot be read as verified low upgrade risk; five report states have independently reviewed next-action checklists; the estimate's scope and unmeasured work are clear without searching the uncertainty appendix.

### R3: Test whether upgrade owners make better decisions

Depends on R0, R1 and the accepted R2 clarification. Owner: maintainer arranges voluntary participants; an agent must not contact people without explicit authorization.

- [ ] Timebox an initial study to ten working days of active evaluation, excluding participant scheduling. Aim for five completed studies across at least three independent intended-user teams. Record missing recruitment or unavailable projects rather than substituting agent opinions for users.
- [ ] Compare the same bounded planning task with the participant's normal Composer/guide/tool workflow and with Preflight. Alternate order or use equivalent tasks to reduce learning bias; capture the starting tool knowledge and exact project/tool inputs. Ask for the first blocker, next safe action, direct versus staged meaning, unseen runtime work, and a scope/sequence decision. Do not execute the target application through the analyzer.
- [ ] Record installation time, analysis time, report-reading time, high-impact false claims/missed work, useful new decisions and voluntary reuse. Proposed management thresholds: at least four of five participants identify the next action and evidence correctly; zero critical misleading compatibility conclusions; median planning time at least 20% lower without reduced answer quality; at least three independent teams voluntarily reuse a report on a second decision/project. Record commitments separately from observed reuse. These are small-sample decision criteria, not market statistics or existing results.
- [ ] Gather at least two concrete Symfony upgrade use cases from intended Symfony owners before selecting Symfony. State the hop, rooted component problem, manual/recipe work, and how a read-only report would improve their present process. If owners/evidence are unavailable, record `VALIDATE_FIRST`; lack of evidence does not pass the Symfony gate.

Acceptance: a sanitized study record supports or rejects the value hypothesis against predeclared criteria. Failed or incomplete studies are a valid outcome with a conservative scope decision; they do not authorize unchecked downstream work. No telemetry, hosted uploads, payment system or licensing change is part of this study.

### R4: Bound maintenance and operational cost

Depends on R0; baseline measurement can proceed alongside R2/R3 after R1.

- [ ] Measure representative small/large and worst-stage runs with declared inputs and toolchain: direct versus staged process count, wall time, peak memory, JSON/Markdown bytes, timeout/unknown outcomes and cleanup. Keep live network/cache drift separate from offline regressions. Investigate the recorded Lychee 128 MiB exhaustion; do not label the 256 MiB advisory target as a runtime guarantee.
- [ ] Compare measured planning benefit with setup/run/read time. Record CI duration and total runner cost for the existing three packages; estimate the incremental fourth package, host matrix, catalog upkeep and Wiki publication burden. Use ranges and assumptions rather than a fabricated revenue forecast.
- [ ] Fix a demonstrated budget/usability blocker with the smallest safe change before breadth expansion; otherwise retain the existing caps and defer optimization. Require equivalent resolution, evidence and immutability with any optimization enabled/disabled.
- [ ] Decide the minimum supported Symfony application/component shape and whether family staging is necessary for the collected cases. Compare full generic multi-adapter machinery with deterministic selection of one stage provider and honest conflict refusal. Preserve attribution/ownership safety whichever slice is chosen.

Acceptance: observed operational cost and maintenance capacity are recorded; required caps remain enforced; advisory targets are identified; every proposed performance or contract expansion has a measured/user-case justification.

### R5: Select the next release and hand off

Depends on R1–R4 outcomes. Owner: maintainer records the decision, using independent engineering/product review where useful.

- [ ] Record one direction: proceed with the bounded Symfony pair, revise/narrow that pair, or defer Symfony for the demonstrated Laravel/PHP/Composer planning need. State passed/failed/unavailable criteria, unresolved risks and the decision's confidence. An incomplete demand study may close as `VALIDATE_FIRST` with Symfony deferred; it cannot close as approval to expand.
- [ ] If Symfony proceeds, feed R2 semantics and R4 budgets/scope into Milestone 0; explicitly resolve multiple `--framework` selections, competing rooted target families and the minimum migration guide. If the direction changes, revise Milestones 0–6 and the v0.5 proposal before implementation; do not quietly delete contractual acceptance gates.
- [ ] Reconcile the canonical roadmap and current public status/Wiki sources with the recorded direction. Preserve historical release notes/schemas and the completed Laravel milestone. Publish any separately authorized maintenance release using the existing release checklist, not by marking planning tasks as implementation.

Acceptance gate: R1 safety proof is green, accepted R2 changes are verified, R3/R4 results are real and bounded, and an explicit decision determines which downstream milestones are authorized. No new package, schema, branch, version or tag is implied by completing this review.

Planning estimate: 8–18 person-days for a lean implementation/evaluation cycle, typically spread over 2–4 calendar weeks plus recruitment delays. This is a low-confidence capacity assumption for one experienced maintainer, not an estimate of a full application upgrade; substantive newly discovered defects may require a revised range.

Status: R0–R2 complete; R1's real Composer regression proved the inherited-manifest redirect and its repair, and R2's report semantics passed independent review with the composed verification recorded in the [decision baseline](../docs/readiness/decision-baseline.md). R3–R5 remain open. Next executable task: R3's participant study; R4's measured cost work can proceed independently. Neither has evidence from R2's engineering tests.

## Milestone 0: Confirm the Theme, Freeze v0.3.x, Lock the v0.4 Contract

Priority: P0. Depends on Readiness Milestone R and its recorded release direction. Complete before changing report shape or development identity; re-scope this milestone first if R defers Symfony.

- [ ] Consume R's application/report evaluations, user studies and cost record; fill only evidence gaps that affect the approved release contract.
- [ ] Confirm the R5 decision still holds against any subsequent published-line feedback; reopen scope before Milestone 1 when new evidence changes it.
- [ ] Specify R2's risk/effort assessment state and enforced/advisory budget semantics in the new contract and schema, with unknown, degraded and unmeasured fixtures. Do not make a new compatibility claim from a renderer-only change.
- [ ] Resolve the intermediate milestone's Laravel handoff in the v0.4 contract: decide explicitly whether same-major Laravel transitions and Illuminate-only/mixed-family staged targets are supported, record required evidence and acceptance cases for any expansion, and update scope before implementation. Carry the Laravel/Symfony ownership and provider-conflict cases into Milestone 2.
- [ ] Freeze the signed v0.3.0 public surface — PHP operation, CLI and Artisan behavior, adapter metadata, exit policy, schema `0.8`, staged-analysis semantics, and the Laravel transition matrix — as immutable compatibility evidence under `tests/fixtures/contracts/v0.3.0`. Separately archive the intermediate milestone's final signed patch reports and corrections as the live v0.4 migration baseline; never overwrite v0.3.0 evidence.
- [ ] Split historical v0.3 compatibility assertions from live development-version and release-policy assertions, following the v0.2 precedent. Do not weaken existing contract tests by search-and-replace.
- [ ] Create and protect the `0.3.x` maintenance branch while the tree still carries its `0.3.x` verifier, aliases, and constraints.
- [ ] Add a machine-readable v0.4 contract and dedicated tests for every new identity, attribution, arbitration, ordering rule, and budget.
- [ ] Define the framework version-identity contract: what an adapter declares, how two versions are ordered, how a hop is named, and how stage IDs stay stable and collision-free across adapters.
- [ ] Define multi-adapter semantics before writing adapter code: activation, deterministic ordering, stage-provider arbitration, package-family collision resolution, source-usage visitor composition, and per-finding attribution.
- [ ] Resolve repeated explicit `--framework` selections and targets spanning competing root families: exactly one unambiguous stage owner may be selected, otherwise retain an evidence-backed conflict refusal. No installation-order or arbitrary first-provider tie-break is allowed.
- [ ] Define family-scoped stage targets: how an adapter declares a package family, how rooted members are enumerated from project state, and how the resulting manifest is proved by Composer rather than assumed.
- [ ] Re-derive hop, attempt, scenario, process, runtime, memory, and report-size budgets for two active adapters and record whether the v0.3 caps still hold.
- [ ] Approve schema `0.9` and its `0.8` migration, then add the immutable schema file and a minimal canonical serialization fixture before Milestone 1 emits new fields.
- [ ] Record the decision to keep the Symfony console command, a wider Symfony matrix, CodeIgniter, PHP deprecation catalogs, PHAR, container delivery, and runtime-floor changes out of v0.4.
- [ ] Atomically switch `main` to v0.4 development identity, schema `0.9`, `0.4.x-dev` aliases, `^0.4` internal constraints, and a verifier permitting only `0.4.x` from `main`.

Acceptance gate: the release theme is confirmed against evidence from real use rather than architecture alone; immutable v0.2.1 and v0.3.0 evidence remains green alongside the final Laravel completion patch baseline; the `0.3.x` branch can still verify its own line; and `main` identifies every subsequent build as v0.4 under schema `0.9` after a machine-checked contract defines identity, arbitration, attribution, family targets, and budgets.

Status: not started.

## Milestone 1: Framework Version Identity and Schema 0.9

Priority: P0. Depends on Milestone 0 and R5's approved direction.

- [ ] Replace integer `from_major` and `to_major` hop identity with the approved framework-declared version identity across models, guidance, stages, plan actions, and evidence references.
- [ ] Keep ordering, comparison, and gap detection inside a tested value object; adapters declare versions and their ordering rule, core never parses framework version semantics.
- [ ] Preserve stable, deterministic, human-readable stage IDs under the new identity, and prove no ID collides when two adapters are active.
- [ ] Support minor-precision hops, including a same-major deprecation-clearing hop, without weakening the gapless-path rule.
- [ ] Complete strict schema `0.9`, canonical snapshots, Markdown projection, and evidence-integrity checks.
- [ ] Implement the R2 assessment-completeness and enforced/advisory-budget contract approved in Milestone 0; verify unknown and partial evidence cannot serialize as completed assessment.
- [ ] Add a consumer migration fixture from `0.8` and document exactly which fields moved, which are additive, and which are removed.
- [ ] Preserve schemas `0.2` through `0.8` and every historical snapshot byte-for-byte.
- [ ] Prove the Laravel transition matrix produces semantically identical findings to the final Laravel completion patch baseline under the new identity, with snapshot changes limited to documented migration effects and explicitly approved scope changes.

Acceptance gate: a minor-versioned framework path is representable and evidence-backed; Laravel output is unchanged except for documented migration effects; and every schema `0.9` finding resolves to valid evidence.

Status: not started.

## Milestone 2: Multi-Adapter Core

Priority: P0. Depends on Milestones 0–1; limit generalization to R4's approved cases.

- [ ] Support several simultaneously active integrations with deterministic ordering, and cover activation, non-activation, and mutual-exclusion cases.
- [ ] Replace the single-stage-provider restriction with the approved deterministic arbitration: one explicitly selected eligible provider wins; multiple explicit providers and automatic selection require one unambiguous owner of the requested root targets. Competing or disjoint ownership that the approved slice cannot handle skips with conflict evidence. Never select by installation order.
- [ ] Resolve package-family collisions deterministically and stop the Laravel adapter from being the implicit owner of `symfony/*` when a Symfony adapter is active.
- [ ] Attribute every framework finding, guidance entry, stage, rule pack, and family label to the adapter that produced it, and expose that attribution in schema `0.9`.
- [ ] Compose source-usage visitors from several adapters without duplicate usages, cross-adapter evidence bleed, or one adapter's failure suppressing another's findings.
- [ ] Keep an inactive adapter completely silent: no usage types, no families, no guidance, no uncertainty entries.
- [ ] Cover the realistic combinations: Laravel-only, Symfony-only, Laravel with rooted Symfony components, a Symfony application with a Laravel-family package installed transitively, both adapters installed with neither activating, and the test adapters alongside both.
- [ ] Prove deterministic, byte-identical canonical output regardless of adapter installation order.

Acceptance gate: two adapters coexist with deterministic activation, arbitration, attribution, and family ownership; no adapter can influence a project it did not detect; and installation order cannot change canonical output.

Status: not started.

## Milestone 3: Symfony Detection and the Approved Hop Pair

Priority: P0. Depends on R5 explicitly selecting Symfony and Milestones 0–2 passing; defer/rewrite when R chooses another direction.

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

Priority: P0. Depends on Milestone 3 and the R4 family-target decision.

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
- [ ] Review remaining structural hotspots only where a demonstrated change/failure is hard to isolate. Preserve the repaired staged collaborators; no blanket rewrite or PHPStan-baseline deletion is a release requirement. Carry the 2026-10-10 review dispositions forward with current evidence.
- [ ] Enforce re-derived two-adapter budgets for process count, per-stage and aggregate runtime, memory, report size, redaction, and deterministic rerun on Linux and Windows.
- [ ] Extend selective mutants to version identity and ordering, arbitration, family collision, attribution, Symfony detection, and family-scoped targets.
- [ ] Continue the coverage ratchet and make the new identity, arbitration, attribution, and Symfony catalog classes critical modules.
- [ ] Measure the compatibility and release matrices before the fourth package multiplies them, and keep total CI time from regressing against the v0.3 baseline.
- [ ] Add Symfony transcript and catalog fixtures so upstream drift stays separable from parser or solver drift.
- [ ] Retain dependency audits, commit-pinned actions, archive checksums, dependency inventory, provenance, signed distribution verification, secret canaries, and target-immutability gates.
- [ ] Preserve the PHP `^8.0` runtime floor and add Symfony host-installability coverage alongside the existing Laravel matrix.

Acceptance gate: the worst supported two-adapter request is bounded, deterministic, private, and mutation-protected. CI fits the duration and total-runner budget approved from R4's measured baseline; record justified fourth-package increases rather than promising unchanged cost or weakening existing gates.

Status: not started.

## Milestone 6: v0.4 Documentation, Migration, and Release

Priority: P0.

- [ ] Update README, installation, external-analysis, CLI, schema, limitations, troubleshooting, adapters, versioning, contribution, security, and release documentation for approved v0.4 behavior.
- [ ] Document version identity, multi-adapter activation and arbitration, family ownership, attribution, the approved Symfony hop pair and its honest gaps, and the `0.8` to `0.9` migration.
- [ ] Include a minimal worked adapter migration example for every changed public contract in v0.4. A separately published conformance kit and extended tutorial remain deferred; essential migration instructions are part of the breaking release.
- [ ] Extend release automation, the verifier, `tools/prepare-distribution.sh`, `tools/release-distribution.sh`, and the release checklist to four packages and four distribution repositories.
- [ ] Verify the protected `0.3.x` maintenance branch still carries compatible aliases, constraints, schema, and release verification after all v0.4 work on `main`.
- [ ] Replace the development identity with exact `0.4.0`, prepare the dated changelog and release notes, and re-verify schema `0.9`, aliases, constraints, and the workflow contract together.
- [ ] Run the deterministic gate on every supported PHP runtime plus required Windows coverage.
- [ ] Run cross-host profile proofs, the restricted Composer harness, worst-case two-adapter budgets, and all privacy canaries.
- [ ] Run normal and lowest-dependency consumers for every advertised Laravel and Symfony host line.
- [ ] Run fresh-clone and release-artifact consumer audits on Windows and Linux using direct and staged analyses through both adapters.
- [ ] Produce checksum-bound archives for all four packages with dependency inventory and source/build provenance.
- [ ] Extend the Wiki strategy/materializer to the monorepo plus four package destinations, retaining the current four mandatory destinations and adding Symfony. Verify source/version/schema claims, examples, links/sidebar coverage and `composer release:wiki:check`; publish matching Wiki commits and versioned evidence with real reviewed/published remote SHAs, linked from release notes, before tagging. Missing required Wiki publication blocks release completion.
- [ ] Create matching verified signed tags in the monorepo and all four distribution repositories, synchronize Packagist, and verify exact published source and distribution references.
- [ ] Reproduce documented Laravel and Symfony quick starts from published packages and prove both target fixtures remain byte-for-byte unchanged.
- [ ] Move `0.3.x` to archival terms at publication, on the public pages and in this plan's support policy.

Acceptance gate: published v0.4 packages validate schema `0.9`, reproduce every claimed stage for both frameworks under the declared platform and execution policy, preserve v0.3 migration evidence, and retain all read-only, privacy, compatibility, and supply-chain guarantees.

Status: not started.

## Principal Risks and Controls

| Risk | Control |
|---|---|
| The release theme is chosen from architecture rather than demand | R tests user decisions against alternatives and records unavailable evidence; R5 chooses scope before Milestone 0 pays for migration |
| A host manifest override defeats temporary-workspace isolation | R1 clears ambient `COMPOSER` in both modes and proves target immutability with real Composer |
| Unknown analysis looks low-risk or numeric effort looks like a quote | R2 qualifies completeness/estimate scope; Milestones 0–1 implement any new schema semantics |
| Symfony upgrades are recipe-driven, so a static analyzer explains less of them than it does for Laravel | Milestone 4 measures the explained fraction on a real fixture and publishes the honest limit; if the report cannot explain a useful share of the work without executing recipes, stop after the hop pair and reconsider the theme rather than widening the matrix |
| Version identity touches every hop, stage, guidance, and evidence path | Contract and schema first in Milestone 0, one tested value object in Milestone 1, Laravel snapshots as the regression proof |
| Two adapters collide on package families and stage providers | Deterministic arbitration and attribution defined before adapter code, with collision evidence instead of silent skips |
| A fourth package multiplies release and CI cost | Milestone 5 measures the matrices before Milestone 6 pays for them |
| Scope creep repeats the v0.3 breadth expansion | The Symfony matrix is one approved hop pair; the console command, wider matrix, migration guide, conformance tooling, and process-count work are already parked in the v0.5 proposal |
| An unsupported line is left exposed | `0.3.x` stays supported until v0.4.0 publishes, and the public pages change in the same release |

## Deferred Until After v0.4.0

- The Symfony console command and any second framework entry point.
- A wider Symfony transition matrix beyond the approved hop pair.
- Extended adapter migration tutorials and any published conformance test kit; minimum instructions for breaking contracts ship in Milestone 6.
- Speculative caching of equivalent Composer scenarios; a measured readiness blocker may justify a narrowly tested repair in R4.
- CodeIgniter, Doctrine, or any fifth adapter.
- A static PHP language and API deprecation catalog.
- Pull-request creation, hosted uploads, dashboards, telemetry, or SaaS storage.
- AI-generated compatibility claims or migration instructions.
- PHAR or versioned container distribution.
- Raising the shared runtime floor above PHP 8.0.

These are collected with rationale in [the v0.5 proposal](DEVELOPMENT_PLAN_0.5.0-PROPOSAL.md), which authorizes nothing.

## Recommended Next Work Session

Readiness Milestone R now has [R0's decision baseline and R2 acceptance record](../docs/readiness/decision-baseline.md), grounded in the published v0.3.5 contracts and [the product/engineering review](audits/2026-10-10-product-engineering-review.md). R1 repaired the inherited-manifest boundary with a real offline Composer regression; R2 qualified report completeness, estimate scope, next actions, and budget language without changing schema `0.8`. Proceed through R3 reader value and R4 measured cost to R5's scope decision. Milestone 0 then owns any approved contract/branch/schema migration. Do not begin Symfony implementation or check off later readiness work from this baseline alone. The separate JavaScript-action audit remains open.

Operational notes carried forward:

- The local gate needs `COMPOSER_PROCESS_TIMEOUT=0`; the symptom and the command are recorded in [CONTRIBUTING.md](../CONTRIBUTING.md).
- Branch protection covers `main` and `0.2.x`, but not tags. The signed `v0.1.0`, `v0.2.1`, and `v0.3.0` tags are the evidence base for every frozen compatibility contract and can still be deleted or moved; a tag ruleset on `v*` would close that.
