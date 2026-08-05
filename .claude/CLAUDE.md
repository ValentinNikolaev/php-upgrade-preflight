# PHP Upgrade Preflight: Claude Instructions

## Mission

Build a deterministic, evidence-backed, read-only preflight analyzer for Composer-based PHP upgrades. Composer is the dependency solver; this project explains its results, lockfile changes, source impact, framework findings, risk, effort, and uncertainty.

Laravel is the first framework adapter, not the identity of the product. Keep the core framework-neutral so Symfony and CodeIgniter adapters can be added later.

## Start Every Session Here

1. Read `README.md`.
2. Read `.claude/memory/MEMORY.md` for durable architecture and constraints.
3. Read `.claude/DEVELOPMENT_PLAN.md` and continue the first unchecked milestone unless the user requests something else.
4. Inspect `git status --short` before editing. Preserve unrelated user changes.

The source architecture brief is outside this repository at:

`I:/Development/Git/ValentinNikolaev/laravel-package-intelligence/ARCHITECTURE_PROMPT.md`

The repository-local memory and plan summarize it. Re-read the source brief before changing product scope or package boundaries.

## Non-Negotiable Constraints

- Support PHP `^8.0` for the first in-project packages.
- Never mutate the analyzed project's `composer.json`, `composer.lock`, source tree, or vendor directory.
- Run Composer scenarios only in isolated temporary workspaces.
- Disable Composer scripts and plugins in analysis scenarios where practical.
- Do not reimplement Composer dependency resolution.
- Do not generate compatibility claims without evidence and confidence.
- JSON is the canonical report; Markdown renders the same report object.
- Arrays belong mainly at serialization boundaries; prefer typed value objects internally.
- The core package must not depend on Laravel or other framework packages.
- Keep adapters thin wrappers around `UpgradeAnalyzer::analyzeUpgrade()`.
- Do not add automatic edits, SaaS upload, dashboards, pull-request creation, or AI-generated upgrade advice to v0.1.

## Package Boundaries

- `packages/core`: state readers, scenario execution, lock diffing, blocker analysis, source scanning, framework contracts, risk/effort logic, report models and writers.
- `packages/cli`: generic `upgrade-intel analyze` entry point and argument handling.
- `packages/laravel`: framework detection, Laravel compatibility rules, service provider, and `upgrade:analyze` Artisan command.

Do not create Symfony or CodeIgniter packages until explicitly requested.

## Engineering Workflow

- Make the smallest coherent change that advances the active milestone.
- Add or update tests with every behavior change.
- Prefer deterministic parsers and structured data over regex or prose inference.
- Treat failure paths as reportable outcomes, not crashes, when analysis can continue.
- Keep evidence IDs stable within a report and ensure every meaningful finding references an existing evidence item.
- Preserve debug workspaces only when debug mode is explicitly enabled.
- Update `.claude/DEVELOPMENT_PLAN.md` when a milestone or acceptance gate is completed.
- Update `.claude/memory/MEMORY.md` only for durable decisions or architecture changes, not session logs.

## Verification Commands

Use the repository's installed tools once available:

```bash
composer validate --strict
composer install
composer test
composer analyse
```

Until scripts are added, run the underlying PHPUnit/Pest and static-analysis commands directly. Validate all three package manifests and test the CLI plus Laravel adapter on fixtures. The initial scaffold was created in an environment where `php` and `composer` were not on `PATH`, so its PHP syntax and runtime behavior still require first-pass verification.

## Definition of Done for v0.1

Given a Laravel 7 fixture and Laravel/PHP targets, the tool must reliably report whether resolution succeeds, blockers, root and transitive package changes, suspicious legacy packages, source files needing review, a staged plan, risks, effort ranges, uncertainties, and traceable evidence. It must prove that the original fixture remains byte-for-byte unchanged.
