---
name: product-engineering-review-2026-10-10
type: audit
review_date: 2026-10-10
commit: 3410ffbbd4c058e41882444dcb77f38876adcca8
released_baseline: 0.3.5
report_schema: 0.8
---

# Product, Engineering and Roadmap Review

The product has a credible technical foundation and a useful planning hypothesis. The next investment should make reports safe, understandable and demonstrably useful before adding another framework. Architecture verdict: **CONCERNS**. Business verdict: **VALIDATE_FIRST**. Confidence is high in the reproduced isolation finding, moderate in the technical/product assessment, and low in demand or financial sustainability because direct user evidence is missing.

This review creates [Readiness Milestone R in the active plan](../DEVELOPMENT_PLAN.md#pre-v04-readiness-milestone-r-trusted-reports-and-demonstrated-user-value). It precedes existing v0.4 Milestones 0–6 without renumbering them. Creating the plan does not complete its fixes, user studies or release gates.

## Scope and evidence

Reviewed the repository instructions and original architecture brief; README and public status; active v0.4 plan and v0.5 proposal; adapter, platform/execution, schema and limitations contracts; the Laravel coverage/application evaluations; the demo report; and representative core/CLI/adapter implementation and tests. Three independent specialist passes covered engineering, product/business, and roadmap structure. This is a bounded review, not an exhaustive line-by-line audit or a new PR-diff review.

The starting working tree was clean. GitHub's latest release API reported v0.3.5, published 2026-10-06 at 23:03:05 UTC. GitHub had no milestones; the canonical milestones are in `.claude/DEVELOPMENT_PLAN.md`. A disposable Docker/Composer experiment reproduced the manifest-redirection mechanism. No target application boot, application tests, whole runtime suite, networked application replay or customer study was performed in this review. Existing release checks are historical evidence, not checks rerun today.

Generated dependencies, unrelated projects, binary assets and raw retained reports from previous tasks were not audited. Published schemas, signed artifacts and completed release history remain unchanged. Public sources below were accessed on 2026-10-10.

## What is worth preserving

| Decision | Benefit | Cost or limit |
|---|---|---|
| Composer is the dependency solver | Uses the ecosystem's actual solver instead of a competing approximation | Results depend on executable, metadata, credentials and platform assumptions |
| Local, read-only analysis | Makes investigation possible before an upgrade branch and preserves privacy | No automatic implementation or runtime proof; F1 shows input isolation still needs repair |
| Canonical JSON with derived Markdown | One semantic report for humans and tooling | Human summaries must expose limitations already present in canonical data |
| Explicit direct, guidance and staged dimensions | Keeps rule coverage, final-target feasibility and path evidence separate | Readers need to understand disagreements; one headline cannot replace all three |
| Candidate-state chaining and evidence validation | Stages carry real solver state and reject invalid provider plans | Original source remains unchanged between stages; no remediation is simulated |
| Adapter-owned framework knowledge | Dependency direction and optional capabilities permit future integrations | A second real adapter is useful validation, not a business reason by itself |
| Offline fixtures plus separate live compatibility gates | Distinguishes product regressions from ecosystem drift | Live evidence does not guarantee reproducible future network behavior |
| Narrow source rules and explicit manual actions | Avoids pretending to understand containers, databases and dynamic code | Guide accounting is broader than automated detection |

The [Laravel evaluation](../../docs/laravel-coverage/application-evaluation.md) is substantive: three independently maintained applications plus two supplemental BookStack snapshots exposed wrong PHPUnit/UI recommendations and improved inherited CSRF guidance. The [coverage ledger](../../docs/laravel-coverage/README.md) accounts for 299 pinned guide headings. Those are strengths in accuracy and disciplined scope. They do not establish adoption or prove runtime compatibility.

## Findings and required decisions

Priorities describe execution order for this project: P0 protects its core guarantee; P1 affects trust, useful decisions or the cost of expansion; P2 is contingent refinement. Product hypotheses are explicitly marked and should not be treated as confirmed software defects.

### F1 — P0: Ambient `COMPOSER` can escape the workspace boundary

**Reproduced mechanism; high confidence.** [ScenarioWorkspacePreparer.php:87](../../packages/core/src/Composer/ScenarioWorkspacePreparer.php#L87) returns the compatible-mode environment without clearing `COMPOSER`. [ComposerScenarioRunner.php:185](../../packages/core/src/Composer/ComposerScenarioRunner.php#L185) obtains that environment and launches updates in its temporary directory. Symfony Process inherits remaining host variables. Composer's [documented `COMPOSER` variable](https://getcomposer.org/doc/03-cli.md#composer) selects another manifest and its adjacent lockfile.

Trigger: a developer/CI shell sets `COMPOSER` to the analyzed project's manifest or another writable manifest. A temporary working directory alone does not confine the selected input. `--no-install`, disabled scripts and disabled plugins do not stop lockfile writes.

The engineering pass ran Composer 2.10.2 in `php-upgrade-preflight-dev:8.3`, with disposable `original/` and `workspace/` directories and no repository mount. From workspace cwd it executed an update with `COMPOSER` pointing at `original/composer.json`, using the analyzer's no-install/scripts/plugins/audit/interaction/progress flags. Observed output:

```text
original_lock_created=yes
workspace_lock_created=no
```

This directly proves Composer's redirection behavior; the production exposure is traced from the current environment construction. It was not an end-to-end analyzer mutation test, which R1 must add. The container's cleanup trap removed the disposable root and `docker run --rm` removed the container.

Existing mitigation: restricted mode clears this variable; temporary workspaces, process safety flags and fixture hashes cover normal scenarios. Compatible mode is the CLI default, so those mitigations do not close this path.

Smallest fix: clear `COMPOSER` in every scenario/diagnostic mode while retaining intentionally compatible authentication/global configuration. Review other input/write-redirection settings within the same boundary. Add a real offline Composer regression that fails on this version, checks the intended target outcome, and proves original bytes unchanged. **Owner/gate: R1; repair before affected execution studies or feature expansion.** This review does not implement the fix.

### F2 — P1: Unknown dependency analysis can display low headline risk

**Test/source-confirmed semantics; high confidence.** [DefaultUpgradeAnalyzerTest.php:538](../../packages/core/tests/Unit/Analysis/DefaultUpgradeAnalyzerTest.php#L538) explicitly expects `resolution.status = unknown` and risk `low` when operational failures prevent solving. [RiskAndEffortEstimator.php:237](../../packages/core/src/Analysis/RiskAndEffortEstimator.php#L237) grades observed blockers/changes/findings without a direct-resolution completeness input.

Trigger: unavailable Composer or repository access leaves little observable work. A manager reads “low risk” as an assessed upgrade conclusion. Existing `unknown` status and uncertainty messages mitigate this, but their presence elsewhere does not make the headline self-explanatory. Per-stage risk already treats unusable stage resolution differently; the aggregate needs a coherent documented meaning.

Smallest decision: separate observed risk from whether risk was assessable. Use prominent patch-compatible qualification if it is sufficient; decide explicit unassessed state/nullability in schema 0.9 if necessary. Keep JSON and Markdown aligned, including partial scans and adapter failures. **Owner/gate: R2, then Milestones 0–1 for any schema change.** Do not turn unknown into a fabricated high compatibility risk either.

### F3 — P1: Numeric effort ranges are uncalibrated and bounded independently of project size

**Source-confirmed limitation; moderate confidence in user impact.** [RiskAndEffortEstimator.php:59](../../packages/core/src/Analysis/RiskAndEffortEstimator.php#L59) uses fixed dependency/source/test buckets, capped at 8/16/8 hours. [Aggregate estimation](../../packages/core/src/Analysis/RiskAndEffortEstimator.php#L275) emits numeric totals with low confidence and deduplicates observed transitions. The demo displays `6–32` hours for its scenario.

Trigger: a decision maker treats a bounded heuristic as the whole upgrade quotation. More unobserved database, integration, deployment or business work cannot expand these buckets. Low confidence and stated heuristic assumptions are useful mitigations, but they are not calibration against actual upgrades.

Smallest improvement: make the included/excluded work clear, label unassessed inputs, and test comprehension. Do not replace this with an elaborate cost model before collecting actual effort evidence. No claim here that the demo's particular range is wrong. **Owner/gate: R2/R3; structured new semantics belong in the approved schema migration.**

### F4 — P1: Report completeness may conceal the next decision

**Product hypothesis supported by inspected presentation; needs observation.** The [demo Markdown](../../examples/five-minute-demo/reports/laravel-10-to-13.md) puts detailed request/provenance/scenario transcripts before much of the action guidance. It retains repeated original-snapshot findings across hops and a long evidence ledger. This is valuable for diagnosis but may impose high reading cost.

Trigger: a lead has ten minutes to identify the first blocker, what changes next, and what remains unproved. A technically complete report can still fail this task. The existing headline and staged plan are useful mitigations; this review did not establish that users find them sufficient or insufficient.

Smallest experiment: task-based reading of feasible, blocked, unknown, skipped and direct/staged-disagreement reports. If necessary, add a short decision summary before transcripts, using canonical facts and preserving the full evidence. A rich terminal explorer is not required to test or solve this problem. **Owner/gate: R2/R3; full-screen work stays a v0.5 proposal.**

### F5 — P1: The roadmap has technical proof but no demonstrated demand for Symfony

**Evidence gap; high confidence that inspected records do not answer it.** All five saved direct application requests were blocked; one supplemental staged request was feasible with changes. That is a legitimate preflight outcome, not an accuracy failure. The bounded retrospective feedback review records no regression report; it is not fresh user research or evidence that the tool is used.

The inspected records contain no task-based reader study, repeat use, acquisition/conversion record or willingness-to-pay evidence. Undocumented users may exist. Do not infer absence of demand from absent records, or demand from absence of complaints.

Smallest experiment: compare intended owners' current planning process with the report on equivalent tasks. Measure correct next actions and useful decisions, with actual voluntary reuse distinguished from stated interest. Gather concrete Symfony-owner cases before choosing a Symfony slice. **Owner/gate: R0/R3/R5.** A timeboxed incomplete study may yield `VALIDATE_FIRST` and deferred expansion; it cannot count as a passed adoption gate.

### F6 — P1: The proposed multi-adapter contract contains an ambiguous selection rule

**Specification gap; high confidence.** [docs/adapters.md:47](../../docs/adapters.md#L47) allows repeated explicit `--framework` options and bypasses detection for them. The previous Milestone 2 said an explicit request “wins” without specifying several explicit providers or competing root families. [StagePlanResolver.php:39](../../packages/core/src/Analysis/StagePlanResolver.php#L39) currently refuses multiple providers with evidence, which is a safe deliberate limitation.

Trigger: Laravel and Symfony are explicitly selected, or a rooted target set spans both families. A first-provider tie-break would make install order affect evidence; silently filtering targets would misrepresent the requested solve. Package-family collisions also matter because Laravel currently labels `symfony/*`.

Smallest decision: specify one unambiguous stage owner, conflict refusals, target preservation, attribution and visitor isolation. Generalize only as far as observed use cases require. Minor-precision identity is justified if the selected Symfony slice includes a same-major hop; [CompatibilityFinding.php:30](../../packages/core/src/Model/CompatibilityFinding.php#L30) currently rejects nonascending integer-major hops. **Owner/gate: R4, Milestones 0–2.** Do not weaken the current refusal in a patch as a shortcut.

### F7 — P1: Advisory targets and measured cost must constrain expansion earlier

**Documented limitation and roadmap concern.** [docs/v0.3-contract.md:74](../../docs/v0.3-contract.md#L74) distinguishes enforced process/time caps from advisory 256 MiB / 512 KiB JSON / 256 KiB Markdown targets. Schema 0.8's budget object does not encode that distinction. [Application evaluation](../../docs/laravel-coverage/application-evaluation.md) records a Lychee run exhausting 128 MiB and a 1024 MiB rerun; this does not establish a general 1024 MiB minimum.

Trigger: users assume all serialized budgets are enforced, or a fourth package makes analysis/CI/catalog maintenance costly enough to erase the planning benefit. The previous “CI is no slower” acceptance promise lacked a measured allowance for the additional package.

Smallest improvement: measure declared representative inputs, separate live/network variance, publish advisory versus hard bounds, and approve explicit runtime/runner budgets. Address demonstrated performance blockers before breadth; defer a general cache otherwise. **Owner/gate: R2/R4, Milestones 0/5.**

### F8 — P1: Migration and Wiki delivery must accompany the new public surface

**Planning omission; high confidence.** The roadmap deferred the worked adapter migration while scheduling new version identity/attribution, and its release milestone lacked an explicit expanded Wiki publication task. [Release Wiki strategy](../../wiki/Release-Wiki-Strategy.md) currently covers four destinations; [the materializer](../../tools/materialize-release-wikis.php#L5) accepts exactly the monorepo, core, CLI and Laravel sets. Adding Symfony creates a fifth Wiki destination alongside the fourth package distribution repository.

Smallest improvement: ship minimum examples for every public contract change; keep the extensive tutorial and published conformance kit deferred. Extend the Wiki strategy/materializer before the fourth-package release, preserve existing destinations, and block publication without real remote Wiki evidence. **Owner/gate: Milestone 6, conditional on R5.** No release/tag or remote Wiki publication is authorized by this planning review.

### F9 — P2: Structural cleanup should follow change difficulty

**Maintenance observation, not a release-blocking defect.** The old architecture audit is a historical report. Current staged orchestration delegates to resolver/executor/assessment/registry collaborators, so its old monolith finding cannot simply be carried forward. The Composer runner and report builders remain substantial, and static-analysis baselines contain iterable-type debt, but size alone does not justify a rewrite.

Smallest policy: refactor a boundary when a concrete failure or approved feature cannot be tested or changed safely. Keep behavior/evidence contracts stable. Avoid a broad framework switch, blanket DTO conversion, or removal of baselines as a readiness goal. **Owner/gate: relevant implementation slice and Milestone 5.**

## Product and business assessment

Provisional user: a senior PHP/Laravel lead or upgrade consultant. Provisional organizational buyer: an engineering manager or agency owner responsible for an upgrade budget. Job: identify blockers, scope manual work, choose sequence and decide whether to start an upgrade before committing implementation effort. These personas are hypotheses.

The strongest possible differentiation is a single local, shareable planning artifact joining solver evidence, staged states, source locations, manual work and explicit uncertainty. It is credible only if users make better decisions with less total effort than their existing process.

| Alternative | What it already provides | Where Preflight must demonstrate incremental value |
|---|---|---|
| [Composer `prohibits` / `why-not`](https://getcomposer.org/doc/03-cli.md#prohibits-why-not) | Dependency constraints preventing a requested package/version | Cross-stage explanation, source correlation and useful next actions beyond raw solver output |
| [Rector](https://getrector.com/documentation) and [Composer-based sets](https://getrector.com/documentation/composer-based-sets) | Preview/apply version-aware PHP/framework refactors | Earlier scope/dependency decisions and evidence before implementation |
| [PHPCompatibility](https://github.com/PHPCompatibility/PHPCompatibility) | PHP cross-version source checks | Complementary Composer/framework planning; do not imply full PHP-language coverage |
| [PHPStan](https://phpstan.org/user-guide/getting-started) | Static analysis of code errors | Upgrade-specific dependency/path evidence, alongside existing static checks |
| [Laravel Shift](https://laravelshift.com/faq) | Automated upgrade changes delivered for review | Read-only investigation without first commissioning implementation |
| Manual guides, a senior engineer, or postponing | Existing knowledge and zero new-tool setup | Enough saved planning effort and reduced uncertainty to justify installation and reading |

These tools can be complementary. No proprietary feature/price comparison, total addressable market estimate or claim that Preflight replaces them is made. The current public sources verify capabilities; they do not prove customers prefer this product.

Symfony's [major-upgrade guidance](https://symfony.com/doc/current/setup/upgrade_major.html) makes clearing deprecations before a major jump useful. A static read-only tool cannot observe all runtime deprecations, container behavior or Flex/recipe work. The framework's recommended process informs a possible hop pair, not a certification that the report can cover it.

The dominant financial assumption is maintainer opportunity cost versus demonstrable planning benefit. MIT licensing does not supply a revenue model. Keep the product free/open as specified; do not assume subscription retention for episodic upgrade work. Potential consulting/support/sponsorship interest is unvalidated and outside this milestone's implementation.

Capacity assumptions for the readiness work: optimistic 8 person-days if evidence is accessible and fixes stay narrow; working range 8–18 person-days; pessimistic 20–30+ if new defects or study recruitment require another cycle. Calendar time depends on voluntary participants. Cash-equivalent effort is `person-days × actual fully loaded day rate`, plus measured CI and support cost; no rate, revenue, break-even or willingness to pay has been established. Symfony implementation is additional work, not included in this range.

## New milestone and effect on later work

| Stage | Deliverable | Dependency / acceptance |
|---|---|---|
| R0 | Evidence ledger, intended user, ranked actions and contract classifications | Use current baseline; distinguish facts, defects and hypotheses |
| R1 | Ambient-manifest isolation repair | Real Composer regression fails before fix; target bytes remain unchanged |
| R2 | Clear assessment limits, estimate scope and next actions | Canonical semantics, unknown/partial cases and JSON/Markdown parity verified |
| R3 | Task-based user comparison and concrete Symfony use cases | Predeclared quality/time/reuse criteria; missing evidence remains missing |
| R4 | Analysis/CI/maintenance cost and minimum adapter slice | Measured hard/advisory limits, justified expansion and bounded optimization |
| R5 | Proceed, narrow, or defer Symfony decision | Consume actual R1–R4 outcomes; revise downstream scope before implementation |

R0 is first; R1 takes precedence before executing affected analysis in user studies. R4 measurement can overlap R2/R3 once the safety boundary is fixed. The roadmap contains the full tasks, owners, pass/fail criteria and verification commands.

Proposed small-sample decision criteria: five completed task studies across at least three independent teams; at least four of five identify correct next action/evidence; zero critical misleading compatibility conclusions; median planning time at least 20% lower without reduced quality; three teams voluntarily reuse for another decision/project. Two concrete Symfony-owner cases are required to select the Symfony direction. These are management thresholds chosen for this milestone, not achieved results or statistical market validation. Record setup/run/read time and counterbalance task order; verbal interest is not repeat use.

Milestone 0 consumes R's decision instead of duplicating a general theme review. Milestone 1 owns any approved schema changes for risk/completeness/budgets alongside identity. Milestone 2 resolves repeated explicit selections and ownership. Milestones 3–4 remain conditional Symfony work. Milestone 5 uses approved measured CI limits. Milestone 6 includes minimum migration examples and all required Wiki publication evidence. The v0.5 proposal remains unapproved; extended terminal UI, adapter breadth and conformance-kit work must still earn their place.

If criteria fail or evidence is unavailable, record `VALIDATE_FIRST`, narrow to the best demonstrated Composer/Laravel planning need, and defer Symfony. This is a valid completed decision stage, not permission to mark unimplemented fixes complete. If evidence succeeds, the bounded Symfony pair remains a reasonable architecture/product experiment.

## Pros, cons and decision

Benefits of the new milestone: closes a core safety gap before breadth; improves trust where unknown currently looks benign; turns existing application evidence into observable reader outcomes; avoids paying for schema/package/CI expansion without a justified user case; and preserves successful architecture rather than imposing a rewrite.

Costs: delays the second adapter, requires maintainer time and voluntary participants, and introduces a small-sample/bias risk. Hard thresholds can create false precision, so report counts, context and failures alongside the decision. A fourth package may still be worthwhile with limited demand if the maintainer consciously funds it as an experiment, but that rationale must be recorded rather than described as proven adoption.

Recommendation: execute R before v0.4 contract migration. Keep Symfony as a conditional narrow candidate. Prioritize the reproduced isolation defect, then assessment clarity and reader benefit. Implementation, financial viability and adoption are not established by this review.

## Validation and artifacts

The review/planning change is documentation-only. Local Markdown targets and milestone ordering passed for the four edited canonical documents; existing milestones remain numbered 0–6 and R0–R5 precede them. The four current Wiki source trees were regenerated, and `docker compose run --rm --no-deps --pull never -T php composer release:wiki:check` passed all four sets, including manifest/link/navigation/checksum validation. `git diff --check` passed. Runtime implementation checks belong to the later fixes and were not run as proof of this plan. These are local source updates; no remote Wiki publication, commit, push or tag was performed.

The focused Laravel completion memory now points to R and keeps the active plan as the sole checklist. The memory validator introduced no new findings; it still reports the pre-existing `MEMORY.md:21` index-grammar warning and advisory index-capacity/Windows-signing review-age notices. Those unrelated legacy-store issues were not rewritten as part of this review.

The disposable engineering reproduction left no retained artifacts. The Wiki materializer's staging/backup directories were absent after generation. This review creates the durable audit and roadmap edits; generated Wiki publication sources are retained intentionally. Existing `build/` diagnostics were left untouched; `build/integration-profile.xml` was absent at final inspection. No reports retained by previous tasks were deleted or represented as newly verified.
