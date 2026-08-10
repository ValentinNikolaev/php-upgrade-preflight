# PHP Upgrade Preflight Development Plan

Last updated: 2026-08-10

This roadmap supersedes the [v0.1.0 implementation plan](DEVELOPMENT_PLAN_0.1.0.md). It records the completed v0.1.0 release, a short v0.1.x stabilization line, and the v0.2.0 feature line.

## How to Use This Plan

- Continue the first unchecked item in the earliest incomplete milestone unless repository evidence requires a safer order.
- Mark work `[~]` only while someone is implementing it. Mark it `[x]` only after the acceptance evidence passes.
- Reconcile this plan in the same change whenever roadmap work is completed, partially completed, reopened, or reverted; commits and handoffs must not leave checklist or status text stale.
- Keep fixes and release hardening compatible with `0.1.x`. Put new report fields, public behavior, and broader framework intelligence in `0.2.0`.
- Keep release automation locked to `0.1.x` patch increments until the v0.2.0 release candidate is explicitly approved. Do not use an interim minor version while executing this roadmap.
- Recheck external release state before acting. Local Git state cannot prove whether a distribution repository or Packagist changed later.
- Update public documentation in the same change as behavior, commands, report semantics, supported versions, or release policy.

## Current Baseline

Repository state recorded on 2026-08-08:

- v0.1.0 was published from `a8d1548` with matching GitHub-verified signed tags in the monorepo and all three history-preserving distribution repositories. The final Release run passed all 28 jobs and published checksummed archives.
- Packagist auto-updates `php-upgrade-preflight/core`, `php-upgrade-preflight/cli`, and `php-upgrade-preflight/laravel` at `v0.1.0`; the monorepo root package was not submitted. A clean Packagist-only quick start reproduced the JSON analysis and fixture immutability proof.
- The release commit passes `docker compose run --rm php composer check` with 328 tests and 2,077 assertions, PHPStan, and PHP CS Fixer.
- Tool version `0.1.0` emits canonical JSON schema `0.6`; Markdown projects the same report.
- Core handles generic Composer and PHP targets. The Laravel rule pack handles Laravel 7 projects targeting Laravel 8 or 9.
- The Laravel adapter can coexist with Laravel 8 through 12, but installability does not mean that later upgrade paths have rules.
- Upstream now publishes upgrade guides through [Laravel 13](https://laravel.com/docs/13.x/upgrade). The v0.2 scope must make an explicit decision about Laravel 13 instead of silently treating Laravel 12 as current.

The remaining gaps determine the milestone order:

| Gap                                                            | Repository evidence                                                                        | Roadmap response   |
|----------------------------------------------------------------|--------------------------------------------------------------------------------------------|--------------------|
| Extension results depend on the analyzer host                  | `ComposerScenarioRunner` simulates PHP but not an explicit extension set                   | Milestone 2        |
| Source impact is an unranked AST inventory                     | `SourceUsageScanner` emits every usage and `RiskAndEffortEstimator` counts them directly   | Milestone 3        |
| Laravel source and target versions are hard-coded to 7 and 8/9 | `LaravelTarget::isLaravel7Project()` and `singleSupportedMajor()` encode the current range | Milestones 4 and 5 |
| Generic CLI adapter discovery names Laravel directly           | `FrameworkIntegrationRegistry` cannot discover a third-party adapter                       | Milestone 7        |

## Release Targets

### v0.1.0 completion

Publish the already implemented read-only Laravel 7 to 8/9 analyzer only after the release archives, installed packages, command entry points, and target-project immutability pass from release artifacts.

### v0.1.x stabilization

Use patch releases for security fixes, blocker-parser corrections, fixture regressions, documentation fixes, dependency maintenance, and release-process repairs. Do not add report fields or claim new Laravel transition support in this line.

### v0.2.0

v0.2.0 should improve trust before adding breadth. It should:

- model target-platform assumptions explicitly and produce reports safe enough to share for review;
- correlate source usage with packages and framework changes so risk and effort use actionable impact rather than raw symbol counts;
- generalize Laravel source/target modeling and add evidence-backed adjacent upgrade paths beyond Laravel 7;
- compose supported adjacent rule packs into staged guidance for multi-major requests without confusing that guidance with Composer's direct-target feasibility result;
- preserve the PHP `^8.0` runtime floor for core, CLI, and the broad Laravel adapter unless a separately approved package strategy changes it.

The recommended adjacent Laravel matrix is 8 to 9, 9 to 10, 10 to 11, and 11 to 12 while retaining the v0.1 paths. Milestone 1 must decide whether 12 to 13 ships in v0.2.0 after verifying its package and PHP requirements. A multi-major request is fully supported only when every adjacent hop has an approved rule pack; otherwise the report must label the missing hop as uncertainty.

## Inherited Product and Test Rules

- Composer remains the dependency solver. Do not infer a successful resolution without running it.
- Treat the analyzed project as immutable input. Run every mutation in an analyzer-owned temporary workspace.
- Keep core framework-neutral. Add no Symfony or CodeIgniter package in this roadmap.
- Keep JSON canonical, Markdown derived, evidence references deterministic, and unsupported claims explicit as uncertainty.
- Keep source inspection static and parser-based. Do not execute the target application as part of deterministic analysis.

Maintain four test layers:

1. Offline unit tests for one phase or rule.
2. Deterministic Composer integration tests backed by committed local `path` repositories.
3. Curated application-shaped fixtures with immutability and JSON-first approval assertions.
4. Networked installation and live-application smoke tests that run separately from the deterministic gate.

## Milestone 0: Finish and Prove v0.1.0 Distribution

Priority: P0. Do this before changing package development versions to `0.2.x-dev`.

- [x] Add a release-artifact consumer job that verifies `SHA256SUMS`, installs all three generated ZIP packages in clean projects, runs `upgrade-intel --help`, performs one JSON analysis, and boots Laravel package discovery.
- [x] Replace the Laravel compatibility `class_exists` smoke with an application boot that verifies provider discovery, analyzer binding, command registration, and one harmless command invocation for each supported host line.
- [x] Add a synthetic Composer-output fixture containing credentials, tokens, and private repository URLs. Block publication if any secret reaches JSON, Markdown, CI logs, or release artifacts.
- [x] Re-run the manual `Release` workflow for `0.1.0` after the post-review workflow security hardening, then review the deterministic matrix, compatibility matrix, fresh-clone audits, generated archives, and checksums.
- [x] Push the approved release commit to `main`.
- [x] Create matching annotated, verified signed `v0.1.0` tags in the monorepo and all three distribution repositories.
- [x] Publish or synchronize `core`, `cli`, and `laravel` on Packagist. Do not publish the monorepo root package.
- [x] Install `php-upgrade-preflight/cli:^0.1` and `php-upgrade-preflight/laravel:^0.1` from Packagist in an empty tools directory and reproduce the README quick start.
- [x] Confirm the published analysis leaves the target fixture byte-for-byte unchanged, then record the release URL and CI evidence in the release notes or checklist.

Acceptance gate: users can install the three published package artifacts, execute both entry points, produce valid schema `0.6` reports, and verify target-project immutability. Every repository carries the same signed `v0.1.0` tag.

Status: passed on 2026-08-08. Evidence is recorded in [`docs/releases/v0.1.0.md`](../docs/releases/v0.1.0.md) and [`docs/release-checklist.md`](../docs/release-checklist.md).

## Milestone 1: Lock the v0.2 Contract

- [x] Preserve a v0.1 compatibility fixture that snapshots the public PHP operation, CLI arguments, exit policy, schema `0.6`, and all six approved Laravel reports.
- [x] Approve the exact adjacent Laravel transition matrix. Review the official [Laravel 10](https://laravel.com/docs/10.x/upgrade), [Laravel 11](https://laravel.com/docs/11.x/upgrade), [Laravel 12](https://laravel.com/docs/12.x/upgrade), and [Laravel 13](https://laravel.com/docs/13.x/upgrade) guides plus exact package manifests before encoding a range.
- [x] Define `supported`, `partially_supported`, and `unsupported` transition semantics. Ambiguous source majors and missing adjacent rule packs must produce uncertainty, not a best guess.
- [x] Define how direct Composer feasibility, adjacent upgrade hops, package changes, and framework guidance appear together without contradicting one another.
- [x] Plan schema `0.7` for platform provenance and actionable source-impact fields. Preserve every historical schema file and document the consumer migration from `0.6`.
- [x] Define the development-version policy after the v0.1.0 tag so reports from `main` do not continue identifying unreleased v0.2 behavior as tool `0.1.0`.
- [x] Make `docs/release-checklist.md` version-neutral or generate its version-specific values through the existing release verifier.
- [x] Set report-size, runtime, memory, redaction, and deterministic-ordering budgets for the representative fixture corpus.

Acceptance gate: the repository contains one approved v0.2 scope and transition matrix, a schema migration decision, measurable budgets, and compatibility tests that fail on accidental v0.1 contract drift.

Status: passed on 2026-08-08. Evidence is recorded in [`docs/v0.2-contract.md`](../docs/v0.2-contract.md), [`docs/laravel-v0.2-transition-scope.md`](../docs/laravel-v0.2-transition-scope.md), [`tests/fixtures/contracts/v0.2.json`](../tests/fixtures/contracts/v0.2.json), and the v0.1/v0.2 release contract tests. The complete `composer check` gate passed with 356 tests and 3,093 assertions.

## Milestone 2: Platform Determinism and Report Privacy

- [x] Introduce a typed target-platform model that distinguishes analyzer runtime, current project PHP, target PHP, host extensions, and explicitly simulated extension assumptions.
- [x] Define CLI and Artisan input for extension presence, absence, and version assumptions without allowing contradictory duplicate values.
- [x] Apply platform assumptions only in temporary Composer workspaces and record their provenance in the canonical report.
- [x] Record uncertainty when an extension result depends on unmodeled host state. Do not present a host-dependent result as reproducible target evidence.
- [x] Redact URL user information, authorization values, common token formats, and credential-bearing Composer diagnostics before storing evidence or rendering reports.
- [x] Define a path-exposure policy for project, output, repository, and debug-workspace paths. Default shareable reports must not expose analyzer-owned temporary roots or credentials.
- [x] Add deterministic fixtures for required, missing, disabled, and version-constrained extensions plus credential-bearing repository failures.
- [x] Verify redaction in JSON, Markdown, exception messages, debug output, and CI logs.

Acceptance gate: equivalent explicit assumptions produce equivalent normalized results for each modeled extension across supported hosts, every assumption has provenance, unlisted extensions remain explicitly partial and host-dependent, and a seeded secret never appears in any persisted or logged output. Full host independence requires a future complete target-platform input rather than inference from a partial assumption list.

Status: passed on 2026-08-09. Evidence is recorded in the offline platform-determinism integration suite, the normalized host-inventory independence test for explicitly modeled assumptions, the credential-bearing repository failure fixture, the canonical path-exposure contract, and `tools/verify-report-privacy.php`, which runs after PHPUnit in the shared Linux/Windows quality matrix. The complete `composer check` gate passed with 401 tests and 3,832 assertions.

## Milestone 3: Actionable Source Impact

- [x] Retain root and locked-package `autoload` and `autoload-dev` metadata needed for symbol ownership. Handle unsupported classmaps or dynamic loaders as uncertainty.
- [x] Build a deterministic ownership index for supported PSR-4, PSR-0, classmap, and files mappings without loading target code.
- [x] Separate raw source usages from actionable impact findings. Keep raw scanner output internal or expose it under an explicitly named inventory section.
- [x] Correlate usages with removed, upgraded, downgraded, or major-jump packages and with active framework rules.
- [x] Add affected package, relevance, reason, severity, and evidence references to actionable source-impact findings in schema `0.7`.
- [x] Deduplicate repeated usages while preserving every exact file and line evidence record.
- [x] Change risk and effort estimation to use weighted actionable findings. Unrelated imports must not raise the estimate.
- [x] Add fixtures for owned symbols, ambiguous namespace ownership, classmap-only packages, removed packages, unchanged packages, and large unrelated application namespaces.

Acceptance gate: each reported actionable source finding explains why the target upgrade affects it, unrelated source inventory does not inflate risk or effort, and ownership uncertainty remains visible.

Status: passed on 2026-08-10. Evidence is recorded in the typed autoload-ownership unit suite, actionable-impact unit and integration fixtures, weighted estimator regressions, schema/snapshot validation, and the complete `composer check` gate with 417 tests and 3,931 assertions.

## Milestone 4: Generalize Laravel Transition Modeling

- [x] Replace the Laravel-7-only predicate with a typed source detection that conservatively resolves one current major from locked framework or rooted Illuminate packages.
- [x] Generalize target parsing beyond majors 8 and 9 while rejecting cross-major target constraints that do not identify one target major.
- [x] Model a Laravel transition as source major, target major, adjacent hops, and support status.
- [x] Preserve modular Illuminate detection and report inconsistent rooted component versions as uncertainty.
- [x] Move PHP requirements, package guidance, official sources, skeleton patterns, and rule applicability into a versioned Laravel rule catalog.
- [x] Validate the catalog at test time for duplicate keys, missing evidence sources, invalid SemVer constraints, unsupported gaps, and contradictory package advice.
- [x] Keep rule execution and source matching in typed rule classes. Do not turn the catalog into unvalidated prose or move Laravel concepts into core.
- [x] Prove that v0.1 Laravel 7 to 8/9 fixtures retain their approved findings unless a documented correction requires a snapshot change.

Acceptance gate: the adapter identifies supported source and target majors without a Laravel-7 special case, constructs deterministic adjacent hops, and refuses ambiguous transitions with evidence-backed uncertainty.

Status: passed on 2026-08-10. Evidence is recorded in the typed source/target and transition unit suites, catalog validator regressions, catalog-backed rule tests, and unchanged v0.1 Laravel fixture approvals. The complete `composer check` gate passes with 439 tests and 4,015 assertions.

## Milestone 5: Later Laravel Upgrade Intelligence

Implement the matrix approved in Milestone 1. The recommended baseline is:

- [x] Retain and regression-test Laravel 7 to 8/9 behavior.
- [x] Add Laravel 8 to 9 rules and fixtures.
- [x] Add Laravel 9 to 10 rules and fixtures, including PHP, Composer dependency, test-tool, and high-signal source checks from the official guide.
- [x] Add Laravel 10 to 11 rules and fixtures, including PHP and extension requirements, first-party package migrations, and the distinction between optional new skeleton structure and required upgrade work.
- [x] Add Laravel 11 to 12 rules and fixtures, including testing dependencies and Carbon compatibility.
- [x] Decide and record whether Laravel 12 to 13 belongs in v0.2.0. If approved, add Laravel 13 host installability and a 12 to 13 rule/fixture slice; otherwise name it as the first v0.3 candidate.
- [x] Cover first-party packages, common test tools, direct Symfony constraints, replaced or removed packages, and source patterns only where exact metadata, source, or maintainer guidance supports the claim.
- [x] Compose adjacent rule packs for supported multi-major requests and deduplicate repeated findings without hiding hop-specific evidence.
- [x] Add blocked, feasible, modular Illuminate, ambiguous source, ambiguous target, missing-hop, and multi-major fixture cases.
- [x] Keep CLI and Artisan canonical JSON parity for every new transition fixture.

Acceptance gate: each approved adjacent path has one feasible and one blocked or advisory-heavy deterministic fixture, multi-major plans preserve hop order, and every finding links to exact project, package, solver, or maintainer evidence.

Status: passed on 2026-08-10. Evidence is recorded in the commit-pinned transition matrix, component-specific Symfony catalog regressions, separate feasible and advisory-heavy or blocked full-analyzer fixtures for every approved adjacent path, real offline Composer resolution for every adjacent acceptance case, multi-major and missing-hop integration tests, evidence-class assertions, and CLI/Artisan parity for every transition case. The complete `composer check` gate passes with 490 tests and 5,135 assertions. Disposable PHP 8.3 consumers also install the adapter with Laravel 13 at normal (`v13.24.0`) and lowest (`v13.0.0`) dependency resolution.

## Milestone 6: Test, Quality, and Supply-Chain Hardening

- [x] Add the documented `test:unit`, `test:integration`, `test:smoke`, and `test:all` Composer commands. Keep `composer check` offline and deterministic.
- [x] Add one coverage job, record the current baseline, and ratchet critical-module and changed-code coverage without selecting an arbitrary initial percentage.
- [x] Raise PHPStan in measured steps, starting with production code. Expand analysis and CS Fixer scope to all first-party support and release-tool code.
- [x] Add transcript fixtures for supported Composer versions so parser changes fail against known solver and `prohibits` output.
- [x] Run real temporary Laravel application boot tests for every supported host line and keep networked failures separate from deterministic regressions.
- [x] Add `composer audit` to scheduled and release-blocking workflows, configure dependency update automation, and pin third-party GitHub Actions by commit SHA.
- [x] Add table-driven tests for every release-verifier branch and parse workflow YAML in policy tests instead of relying only on substring assertions.
- [x] Add selective mutation tests for scenario selection, blocker parsing, schema validation, risk/effort logic, Laravel transition selection, and release verification after coverage measurement.
- [x] Enforce the runtime, memory, and report-size budgets set in Milestone 1 on representative fixtures.

Acceptance gate: developers can choose deterministic or networked scope explicitly, quality metrics ratchet instead of regress, release policy tests cover every enforced rule, and dependency or workflow integrity failures block publication.

Status: passed on 2026-08-10. The offline `composer check` gate passes with 550 tests and 5,422 assertions across the disjoint unit, integration, and smoke suites, both PHPStan configurations, and all 198 CS Fixer targets. The PCOV coverage ratchet passes at 6,342 of 6,886 executable lines with critical-module and changed-code safeguards, all six selective mutants are killed, and the representative corpus stays within its runtime, memory, and report-size budgets. Composer 2.0, 2.2, 2.4, and 2.8 transcript fixtures protect solver and `prohibits` parsing. Separate normal and lowest-dependency Laravel 8-13 application boot jobs, scheduled and release-blocking dependency audits, Dependabot coverage, commit-pinned actions, parsed workflow policy tests, and table-driven release-verifier branches provide the supply-chain and publication evidence. The locked dependency audit reports no known vulnerability advisories, and the completed Milestone 5 transition suite remains green.

## Milestone 7: Adapter Extensibility and External Execution

- [ ] Replace the Laravel-specific CLI registry probe with a documented adapter registration or Composer-metadata discovery mechanism.
- [ ] Add a test-only adapter package that proves third-party detection, default source paths, rules, and package-family classification work without editing CLI source.
- [ ] Define collisions, ordering, duplicate adapter names, missing packages, and explicit `--framework` failure behavior.
- [ ] Keep Laravel auto-detection behavior and CLI/Artisan parity unchanged while generalizing registration.
- [ ] Decide whether v0.2 ships a signed PHAR, a versioned container image, or only the existing external Composer installation. Prefer one supported external path over several partially maintained paths.
- [ ] If a new delivery format is approved, build it reproducibly, attach checksums and provenance, and analyze a PHP 7.4 fixture without installing anything into that target project.

Acceptance gate: a third-party adapter can register through the public mechanism, the deterministic core remains framework-neutral, and every advertised external delivery path has an automated installation and read-only analysis test.

## Milestone 8: v0.2.0 Documentation, Hardening, and Release

- [ ] Update README, installation, external-analysis, CLI, Artisan, schema, limitations, troubleshooting, versioning, contribution, security, and release documentation for the approved v0.2 behavior.
- [ ] Document schema `0.6` to `0.7` migration, source-impact semantics, platform provenance, redaction boundaries, supported Laravel transitions, and unsupported hops.
- [ ] Update tool version, branch aliases, root path versions, internal package constraints, changelog, release notes, and release-verifier expectations together.
- [ ] Run the deterministic gate across every supported PHP runtime and Windows coverage required by CI.
- [ ] Run normal and lowest-dependency consumer installs for every advertised Laravel host line, including Laravel 13 only if Milestone 5 approved it.
- [ ] Run fresh-clone and release-artifact consumer audits on Windows and Linux.
- [ ] Produce an SBOM or equivalent dependency inventory and artifact provenance for release archives.
- [ ] Create matching verified signed tags, publish all three distribution repositories, synchronize Packagist, and verify the published quick start.

Acceptance gate: v0.2.0 installs from published packages, validates against the documented schema, produces deterministic and redacted reports, supports the approved transition matrix, and preserves every read-only guarantee.

## Deferred Until After v0.2.0

- Symfony and CodeIgniter adapters.
- Automatic edits to application source or Composer files.
- Pull-request creation, hosted uploads, dashboards, or SaaS storage.
- AI-generated compatibility claims or migration instructions.
- Executing or booting the analyzed application during deterministic analysis.
- Raising the shared runtime floor above PHP 8.0 without a separate package or major-version decision.

## Recommended Next Work Session

Start Milestone 7 by replacing the Laravel-specific CLI registry probe with a documented adapter registration or Composer-metadata discovery mechanism, then prove the mechanism with a test-only third-party adapter. Keep Laravel auto-detection, CLI/Artisan parity, the completed transition contracts, and v0.1 fixture approvals intact, and keep release automation on patch-only `0.1.x` increments until the v0.2.0 release candidate and its contract changes are explicitly approved.
