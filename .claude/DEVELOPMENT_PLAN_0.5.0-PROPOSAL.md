# PHP Upgrade Preflight Development Plan — v0.5.0 Proposal

Status: **PROPOSAL**. This is not the active plan and it authorizes nothing.

Prepared: 2026-08-18, against released `0.3.0`; revised 2026-10-10 against the published `0.3.5` baseline and the [R5 decision](../docs/readiness/release-direction.md).

This file preserves possible later work. The R5 decision defers Symfony and leaves the v0.4 theme unapproved, so the assumed two-adapter baseline and the original "cut from v0.4" framing are hypothetical. Nothing here is committed for v0.5 or eligible for implementation without an evidence-backed release decision and a reconciled active plan.

Planning amendment, 2026-10-10: R5 selected `VALIDATE_FIRST`. R3 has no participant, repeat-use or Symfony-owner evidence; R4 has bounded small offline and CI observations but lacks large-project/child-memory profiles, Lychee cause and maintainer capacity. Re-evaluate every candidate after those gates and any approved v0.4 contract. This amendment approves no v0.5 implementation.

On approval this file becomes the active plan: archive the completed roadmap to `DEVELOPMENT_PLAN_0.4.0.md` first, then replace `DEVELOPMENT_PLAN.md` with the approved v0.5 content and delete this proposal.

## Entry Conditions

- Readiness R3/R4 empirical gates evaluated and a v0.4 theme explicitly approved, implemented and published; until then `0.3.x` is the active line.
- Every acceptance gate for the *actual approved* v0.4 scope met. Two-adapter arbitration, attribution and Symfony explained-work checks apply only if that scope includes a second adapter.
- Real analyses, recorded gaps and requests from the published line support a distinct v0.5 need and maintainer capacity.

## Conditional candidates originally deferred from the Symfony v0.4 outline

### 1. Symfony console command

If a Symfony adapter eventually ships, parity with the Laravel Artisan command could offer one command, default project path, the same analyzer operation and canonical-report equivalent output. The generic CLI already serves as the entry point for Composer projects; a Symfony command requires evidence of actual users and convenience value.

### 2. A wider Symfony transition matrix

No Symfony hop pair is approved by R5. If a later release establishes one, widening would require the same evidence standard for more guides, manifests and fixtures. The v0.2 history is the warning: v0.1 shipped two Laravel paths, v0.2 shipped nine, and the breadth arrived before production evidence.

### 3. Adapter migration guide with a worked diff

If a future release changes public adapter contracts, that release must ship minimum worked migration examples. An extended line-by-line tutorial remains a later candidate, justified when there are adapter authors to serve. External contributions are accepted under the current MIT contribution policy; that policy does not establish that external adapter authors exist.

### 4. Published adapter conformance kit

A packaged test kit an external adapter can run against its own implementation. In-repo fixtures cover the maintainer's needs; publishing a kit is a new supported surface with its own compatibility promises.

### 5. Composer process-count reduction

Caching equivalent scenario and diagnostic executions inside one analysis, proving equivalent canonical resolution and evidence with the cache disabled after declared timing/provenance normalization. Readiness Milestone R may justify a narrow performance repair when measured cost prevents useful analysis; a general cache remains deferred. Do this for a demonstrated bottleneck, not a presumed one.

## Candidate v0.5 Themes

None of these is chosen. They are recorded so the decision starts from a list rather than a blank page.

| Theme | Argument for | Argument against |
|---|---|---|
| Depth on any actually shipped adapters — wider matrices, a console command, a migration guide | Could serve observed users of a later line | Adds no new capability class, and risks repeating the v0.2 breadth pattern |
| Another adapter (CodeIgniter, or an ecosystem family such as Doctrine) | Could test neutrality and serve new projects if owners demonstrate a need | Multiplies release and catalog cost before demand and capacity are established |
| PHP language and API deprecation catalog | Matches the product's name, which promises PHP upgrade preflight rather than framework preflight | Overlaps Rector and PHPCompatibility, and every claim must meet the project's evidence rules, which is expensive |
| Consolidation toward `1.0` | Freezes contracts, sharpens documentation, reduces the maintenance surface | Premature while adoption is unproven; `1.0` is a promise, not a milestone |

## Candidate Milestone 6 — Rich Interactive Terminal and Report Explorer

This milestone is deliberately later than the line-oriented wizard and phase progress work. It is not required to make ordinary analysis usable, and it must not hold the automation-safe CLI contract hostage to a full-screen interface. It becomes eligible only after the simpler interactive workflow has shipped, has real usage evidence, and has proved which selections and report sections users repeatedly need to revisit.

### Outcome

An opt-in adaptive terminal interface lets a person configure an analysis, understand repository and version provenance, follow long-running work, and explore the resulting report without assembling long flag lists or writing `jq`, `grep`, and `sed` pipelines. The same operation remains expressible through `upgrade-intel analyze` flags, and the same canonical JSON or Markdown report remains the source of truth.

### Candidate Scope

- a full-screen mode entered explicitly, for example through `upgrade-intel wizard --ui=full` or a separately approved `upgrade-intel explore` command; never inferred only from stdout being attached to a terminal;
- searchable package and version selection with local Composer metadata first, explicit repository/network lookup, visible provenance, bounded waits, and an `unverified` state distinct from `not found`;
- compatibility views that keep package existence, matching published versions, PHP/framework requirements, and project installability as separate claims;
- persistent but compact progress for project discovery, Composer feasibility scenarios, staged transitions, source scanning, report assembly, cancellation, and cleanup;
- report exploration for the executive summary, direct and staged resolution, active blockers, package changes, framework findings, source impact, evidence, uncertainties, risk, effort, and test guidance;
- filtering, search, drill-down, back navigation, copyable identifiers and commands, and explicit save/export actions;
- an always-available view of the equivalent non-interactive command so an accepted interactive configuration can be repeated in CI or documentation;
- graceful terminal resize, narrow-width layouts, color-disabled and ASCII fallbacks, and a line-oriented mode with equivalent outcomes for unsupported terminals and assistive workflows.

### Contract Boundaries

- The rich interface is a presentation and request-building layer. It does not create a second analyzer, report schema, package-resolution policy, or exit-code taxonomy.
- `upgrade-intel analyze` remains non-interactive. Machine-readable stdout stays free of prompts, progress, ANSI sequences, and commentary.
- Network access, inherited Composer configuration, credentials, and private repositories are never hidden behind discovery. The user selects the lookup mode before a remote probe starts.
- EOF, an unavailable TTY, invalid input, timeout, and cancellation never imply consent. Cancellation restores the terminal and reports cleanup or retained temporary state honestly.
- The interface does not modify the analyzed application, install candidate dependencies into it, execute it, upload reports, add telemetry, or weaken the standing non-goals.
- The line-oriented wizard remains supported even if the full-screen interface ships; it is the compatibility and accessibility fallback, not a temporary scaffold.

### Entry and Acceptance Gates

- Evidence from the line-oriented wizard identifies repeated navigation or report-reading work that a richer interface materially reduces.
- The command contract, prompt precedence, repository lookup modes, progress events, cancellation semantics, and report summary vocabulary are stable before full-screen rendering begins.
- Pseudo-terminal coverage exercises Linux and Windows behavior, normal and narrow widths, resize, no-color/plain output, Unicode and ASCII symbols, redirected streams, EOF, and `Ctrl+C` during lookup, analysis, and cleanup.
- Snapshot or transcript tests protect navigation states and stream boundaries without freezing incidental animation frames or styling.
- The full-screen and line-oriented paths produce equivalent `UpgradeRequest` values and canonical reports for the same approved choices.
- A terminal capability failure falls back before side effects and never corrupts the terminal, stdout report, saved report, or exit result.
- Package and report searches remain bounded for representative large dependency graphs and reports; measured latency and memory budgets are recorded with the acceptance evidence.

### Explicitly Deferred Beyond This Milestone

- mouse-first interaction, terminal graphics protocols, embedded editors, and terminal-emulator-specific extensions;
- hosted dashboards, synchronized sessions, telemetry, collaborative review, or remote report storage;
- applying remediations, editing `composer.json`, running the analyzed application, or turning report exploration into an upgrade executor.

## Standing Non-Goals

Unchanged from v0.3 and v0.4, restated so no proposal quietly reopens them:

- modifying the analyzed application, or applying and simulating remediations between stages;
- executing the analyzed application, its recipes, or its Flex operations;
- pull-request creation, hosted uploads, dashboards, telemetry, or SaaS storage;
- AI-generated compatibility claims or migration instructions;
- PHAR or versioned container distribution;
- raising the shared runtime floor above PHP `^8.0`.

## Open Decisions

- **D1 — Theme.** Decide against evidence from the actual published predecessor and its users, not against this list. Record the evidence beside the decision.
- **D2 — Whether `1.0` is in sight.** If the answer is yes, v0.5 should be a consolidation release and the deferred items above become `1.0` scope items or permanent non-goals.
- **D3 — Support policy.** The current policy archives a line the moment its successor publishes. Confirm it still fits once there are external users, or state the change explicitly.
