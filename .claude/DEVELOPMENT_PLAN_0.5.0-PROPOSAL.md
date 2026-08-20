# PHP Upgrade Preflight Development Plan — v0.5.0 Proposal

Status: **PROPOSAL**. This is not the active plan and it authorizes nothing.

Prepared: 2026-08-18, against released `0.3.0` and the active [v0.4 plan](DEVELOPMENT_PLAN.md).

This file collects the work deliberately cut from v0.4 so the second-adapter release stays narrow enough to finish. Nothing here may be pulled forward into v0.4 without reopening the v0.4 scope section, and nothing here is committed for v0.5 either: the theme of v0.5 must be re-decided against evidence from the v0.4 line, exactly as v0.4 gates its own theme.

On approval this file becomes the active plan: archive the completed roadmap to `DEVELOPMENT_PLAN_0.4.0.md` first, then replace `DEVELOPMENT_PLAN.md` with the approved v0.5 content and delete this proposal.

## Entry Conditions

- v0.4.0 published, with the `0.4.x` line supported and `0.3.x` moved to archival terms.
- The v0.4 acceptance gates met, in particular: two adapters coexisting with deterministic arbitration and attribution, and the honest published answer to how much of a Symfony upgrade a static analyzer can explain.
- Evidence collected from the v0.4 line the same way v0.4 Milestone 0 collects it: real analyses, recorded gaps, recorded requests.

## Deferred From v0.4

### 1. Symfony console command

Parity with the Laravel Artisan command: one command, default project path, the same analyzer operation, canonical-report equivalent output. Cut from v0.4 because the generic CLI already analyzes a Symfony project, so the command is convenience rather than proof of neutrality. It becomes worth doing once Symfony users exist.

### 2. A wider Symfony transition matrix

v0.4 encodes one approved hop pair. Widening means the same evidence standard applied to more upgrade guides and manifests, plus fixtures per path. This is the single largest deferred cost, and the v0.2 history is the warning: v0.1 shipped two Laravel paths, v0.2 shipped nine, and the breadth arrived before any production evidence.

### 3. Adapter migration guide with a worked diff

v0.4 documents the multi-adapter contracts; it does not walk an adapter author through migrating a v0.3-era adapter line by line. Useful when there is a third-party adapter author to serve. External code contributions are not accepted today, so the audience is the maintainer and documentation readers.

### 4. Published adapter conformance kit

A packaged test kit an external adapter can run against its own implementation. In-repo fixtures cover the maintainer's needs; publishing a kit is a new supported surface with its own compatibility promises.

### 5. Composer process-count reduction

Caching equivalent scenario and diagnostic executions inside one analysis, proving byte-identical canonical output with the cache disabled. v0.4 measures two-adapter budgets but does not optimize them. Do this when a measured budget is actually breached, not before: a cache that changes results is worse than a slow analysis.

## Candidate v0.5 Themes

None of these is chosen. They are recorded so the decision starts from a list rather than a blank page.

| Theme | Argument for | Argument against |
|---|---|---|
| Depth on the two shipped adapters — wider matrices, the console command, the migration guide | Uses proven machinery; lowest risk; directly serves whoever adopted v0.4 | Adds no new capability class, and repeats the v0.2 breadth pattern |
| A third adapter (CodeIgniter, or an ecosystem family such as Doctrine) | Confirms neutrality beyond two frameworks and grows addressable projects | Two adapters already prove the contract; a third mostly multiplies release cost |
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

- **D1 — Theme.** Decide against v0.4-line evidence, not against this list. Record the evidence beside the decision.
- **D2 — Whether `1.0` is in sight.** If the answer is yes, v0.5 should be a consolidation release and the deferred items above become `1.0` scope items or permanent non-goals.
- **D3 — Support policy.** The current policy archives a line the moment its successor publishes. Confirm it still fits once there are external users, or state the change explicitly.
