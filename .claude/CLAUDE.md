# PHP Upgrade Preflight: Claude Project Rules

## Mission

Build a deterministic, evidence-backed, read-only preflight analyzer for Composer-based PHP upgrades. Composer remains the dependency solver; this project explains resolution results, lockfile changes, blockers, source impact, framework findings, risk, effort, uncertainty, and supporting evidence.

Laravel is the first framework adapter, not the product identity. Keep the core framework-neutral so other adapters can be added later.

## Shared With Other Assistants

These rules, the roadmap, the memory file, and the audits are shared. `.codex/AGENTS.md` points at them rather than copying them, so an edit here serves every assistant working on the project. Do not create a parallel copy under another assistant's directory; if a genuinely assistant-specific rule is needed, keep only that difference in that assistant's own file.

## Session Start

Before editing:

1. Read `README.md`.
2. Read `.claude/DEVELOPMENT_PLAN.md` for current sequencing and acceptance gates.
3. Read `.claude/memory/MEMORY.md` for durable architecture and decisions.
4. Run `git status --short` and preserve unrelated user changes.

Unless the user requests different work, continue the first unchecked item in the earliest incomplete milestone of the active development plan. Treat versioned `.claude/DEVELOPMENT_PLAN_*.md` files as historical records; do not use their completed checklists to sequence current work.

The original architecture brief is at `I:/Development/Git/ValentinNikolaev/laravel-package-intelligence/ARCHITECTURE_PROMPT.md`. Re-read it before changing product scope or package boundaries.

## Non-Negotiable Product Rules

- Support PHP `^8.0` in all runtime packages. Do not use language features introduced after PHP 8.0 in shipped code.
- Treat the analyzed project as immutable input. Never modify its `composer.json`, `composer.lock`, source tree, or `vendor/` directory.
- Run Composer analysis only in test-owned or analyzer-owned temporary workspaces. Delete those workspaces unless debug mode explicitly preserves them.
- Disable Composer scripts and plugins in scenarios where practical.
- Do not reproduce Composer's dependency solver or infer solver outcomes without running Composer.
- Express missing or weak evidence as uncertainty. Do not turn heuristics into compatibility claims.
- JSON is the canonical report. Markdown must be a projection of the same `UpgradeReport`, with no independent analysis logic.
- Every meaningful finding must reference evidence that exists in the report. Evidence IDs must be unique and deterministic within that report; orphaned evidence is invalid.
- Effort estimates are ranges with assumptions and confidence, never precise promises.
- Keep the product local and read-only. Do not add automatic source edits, SaaS uploads, dashboards, pull-request creation, or AI-generated upgrade advice.

## Package Boundaries

- `packages/core`: project-state readers, target normalization, Composer scenarios, lock diffs, blocker analysis, source inspection, framework contracts, evidence, risk/effort logic, report models, and report writers.
- `packages/cli`: generic `upgrade-intel analyze` argument parsing, validation, output routing, and exit policy.
- `packages/laravel`: Laravel detection, compatibility rules, service-provider registration, and the `upgrade:analyze` Artisan command.

The core package must not depend on Laravel or another framework package. Keep framework adapters thin and route both CLI and Artisan execution through `UpgradeAnalyzer::analyzeUpgrade()`. Do not add Symfony or CodeIgniter packages unless explicitly requested.

## Implementation Rules

- Make the smallest coherent change that completes an active checklist item or a well-defined slice of it.
- Add or update tests with every behavior change. Test failure paths as structured outcomes when analysis can continue.
- Update every affected public document in the same change whenever behavior, commands, options, installation, compatibility, report schemas or semantics, limitations, troubleshooting, package metadata, or the release process changes. Review `README.md`, `docs/`, `CHANGELOG.md`, and contributor or security guidance as applicable before declaring the work complete.
- Prefer typed value objects and phase-specific interfaces internally. Use arrays primarily at serialization and integration boundaries.
- Make DTOs immutable where PHP 8.0 permits, and use explicit parameter and return types.
- Normalize paths, target ordering, package ordering, evidence ordering, and temporary-directory values before snapshots or report serialization.
- Prefer parsers and structured metadata over regular expressions or prose matching. Source inspection must use `nikic/php-parser`; syntax failures are non-fatal uncertainties with evidence.
- Keep framework-specific package families and migration guidance out of core.
- Preserve exact Composer command, version, duration, exit status, bounded output excerpts, and candidate lock evidence for each scenario.
- Deduplicate a root conflict reported by several scenarios while retaining links to all supporting scenario evidence.
- Use stdout for reports and stderr for diagnostics. Invalid invocation and internal failure are nonzero exits; keep the valid-but-blocked exit policy explicit and tested.

## Test Rules

Use the four layers defined in `.claude/DEVELOPMENT_PLAN.md`:

1. Fast offline unit tests for one class or phase.
2. Deterministic Composer integration tests backed by committed local `path` repositories.
3. Curated application-shaped Laravel fixtures containing only relevant Composer and source/config files.
4. Networked live smoke and package-compatibility tests, run separately from the deterministic gate.

For every analyzer scenario, copy the fixture to a fresh temporary directory, snapshot or hash the original tree, and prove byte-for-byte immutability afterward. Remove only test-owned temporary copies.

Do not use arbitrary public `composer.json` files as deterministic fixtures. Public sample applications are optional release smoke tests only; pin them to commit SHAs. Assert canonical JSON first, and test Markdown only for faithful projection and required sections.

## Verification

The preferred local gate runs in Docker:

```bash
docker compose run --rm php composer check
```

The root scripts are:

```bash
composer validate:all
composer test
composer test:core
composer test:cli
composer test:laravel
composer analyse
composer lint
composer check
```

Run the narrowest relevant checks during development and `composer check` before declaring a checklist item complete. The deterministic gate must remain offline. Run future smoke tests only through their explicit opt-in command and report ecosystem drift separately from product regressions.

CI runs `composer check` on PHP 8.0 through 8.5. Changes must pass on the PHP 8.0 runtime floor as well as the newer matrix versions.

## Plan and Memory Maintenance

- Mark a `.claude/DEVELOPMENT_PLAN.md` item `[x]` only after its behavior and acceptance evidence are implemented and verified.
- Use `[~]` only for genuinely active work; do not mark an entire milestone complete from partial coverage.
- Reconcile `.claude/DEVELOPMENT_PLAN.md` in the same change whenever work completes, partially completes, reopens, or reverts a roadmap item. Do not commit or hand off roadmap work with stale checklist or status text.
- Update `.claude/memory/MEMORY.md` only for durable architecture, constraints, or decisions. Do not record session logs, temporary failures, or facts cheaply derived from code.
- Keep the plan directional. Preserve milestone order unless concrete dependencies justify a change, and document that reason.

## Release Notes and Versions

- Do not create a release-notes file, move changelog entries into a dated release, or change tool/package version metadata for every ordinary change.
- Keep completed work under `CHANGELOG.md`'s `[Unreleased]` section until the user explicitly asks to prepare a new release or the active milestone specifically requires one.
- When a new release is intentionally prepared, update its release notes, changelog heading, `ReportMetadata::TOOL_VERSION`, branch aliases, internal package constraints, and matching tag plan together, then run the release verification workflow. Publish only from an annotated, GitHub-verified signed tag whose commit is on the approved release line: `0.1.x` for v0.1, `0.2.x` for v0.2, or `main` for the active v0.3 series.
- Release automation on `main` permits only the active `0.3.x` series. The signed v0.1.0 and v0.2.1 contracts remain immutable historical evidence; maintenance releases stay on their protected series branches.

## v0.1 Completion Standard

Given a Laravel 7 fixture and Laravel/PHP targets, the tool must deterministically report whether resolution succeeds, actionable blockers, root and transitive package changes, suspicious legacy packages, source files requiring review, staged actions, risks, effort ranges, uncertainties, and traceable evidence. JSON and Markdown must agree, and the original fixture must remain byte-for-byte unchanged.
