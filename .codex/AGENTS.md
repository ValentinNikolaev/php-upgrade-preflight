# Codex instructions

This project is worked on by more than one assistant. To keep them from drifting apart,
the project rules, roadmap, memory, and audits exist in exactly one place. This file
points at that place; it deliberately contains no copy of the content.

Do not fork these files into `.codex/`. An edit to any of them serves every assistant.

| What | Canonical file |
|---|---|
| Project rules, boundaries, verification, plan and memory maintenance | [`../.claude/CLAUDE.md`](../.claude/CLAUDE.md) |
| Active roadmap, milestones, acceptance gates | [`../.claude/DEVELOPMENT_PLAN.md`](../.claude/DEVELOPMENT_PLAN.md) |
| Completed roadmaps, kept as history | `../.claude/DEVELOPMENT_PLAN_0.1.0.md`, `_0.2.0.md`, `_0.3.0.md` |
| Proposals, which authorize nothing | `../.claude/DEVELOPMENT_PLAN_0.5.0-PROPOSAL.md` |
| Durable architecture and decisions | [`../.claude/memory/MEMORY.md`](../.claude/memory/MEMORY.md) |
| Architecture audits | [`../.claude/audits/`](../.claude/audits) |

## Session start

1. Read `README.md`.
2. Read `../.claude/CLAUDE.md` in full and follow it. Its rules are not Claude-specific: they
   are the project's rules, including the PHP 8.0 floor, the read-only guarantee for the
   analyzed project, evidence requirements, package boundaries, and the verification gate.
3. Read `../.claude/DEVELOPMENT_PLAN.md` for current sequencing.
4. Read `../.claude/memory/MEMORY.md` for durable decisions.
5. Run `git status --short` and preserve unrelated user changes.

## Keeping this arrangement honest

If the canonical location ever moves, update this file in the same change. If two
assistants need genuinely different instructions — different tool names, different
sandbox rules — put only that difference here and keep the shared content where it is.
