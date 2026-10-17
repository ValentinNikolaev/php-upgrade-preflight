# PHP optimization: execution and acceptance

Status: initial implementation and combined local gates passed; semantic corpus
review reopened causal attribution and summary limitations. Corrections and a
fresh controlled measurement are in progress. Authorized 2026-10-17. No release
is prepared.

The ordinary result must answer three questions: what prevents the requested
upgrade, what the owner can do next, and which parts of that answer remain
unverified. Full machine reports and diagnostic inventories are explicit options.
Composer remains the dependency solver; interpreting its output is only one part
of the tool's job.

## Starting evidence and boundaries

The starting implementation is `2a0b01ad6be1e59458a0d2fa6a3ff0aa43738138`,
including the reviewed attribution and cache-ownership repairs from the R4 work.
That composition has not yet been measured on the public corpus. The older
reports are historical observations, not corrected performance baselines.

Across the ten historical nearest-cold runs, reported analysis time totals
1,029.234 seconds. Direct scenario durations plus staged attempt durations total
782.721 seconds, about 76%. These durations include workspace preparation,
Composer, diagnostics and cleanup. The remaining 24% is not a parser profile.
See the [original measurements](../readiness/corpus-2026-10/evidence/metrics.csv)
and [measurement boundaries](../readiness/corpus-2026-10/README.md).

PHP `^8.0`, target immutability, evidence integrity, privacy, deterministic offline
tests, and distinct dependency/framework/staged conclusions remain requirements.
Existing signed artifacts and schema `0.8` are immutable. The user authorized an
intentional change to the command's default presentation and bounded optimization;
this does not authorize Symfony, a new version identity, a release tag, or a main
merge. Any future publication must explicitly resolve the minor-release migration
of the changed default. R3 participant benefit and demand remain unmeasured.

## Ordered deliverables

| Step | Deliverable | Acceptance evidence | Status |
| --- | --- | --- | --- |
| O1 | Bounded human summary by default in CLI, Artisan and the normal wizard flow; full JSON/Markdown requested explicitly | Correct actions and qualifications for blocked, feasible, unknown, advisory-only, partial-source and direct/staged disagreement; no entire inventory serialization for summary | Implemented; component review/CI and combined local gate passed |
| O2 | Grouped explanations with specific safe actions and accurate verification status | Every material recommendation has supporting evidence; hypothetical candidates are not represented as checked repairs; maintenance advisories do not become blockers | Reopened: public-case evidence exposed unsupported package-cause attribution; correction and fresh review in progress |
| O3 | Adaptive standard execution and an explicit exhaustive alternative | Exact-target failure cannot suppress a successful dependency-expansion path; requested stage chains and honest unknowns remain; executed work is inspectable | Implemented; component review/CI and combined local gate passed |
| O4 | Measured phases and equivalent Composer-result reuse | Fewer actual subprocesses in deterministic cases; relevant state/configuration changes prevent reuse; cached and uncached semantic conclusions agree | Implemented with O3; same gates passed |
| O5 | Persistent source-fact reuse, safe source discovery and visible omissions | Unchanged files avoid reparsing; edits/additions/deletions and changed collectors invalidate appropriately; corrupt/unavailable cache falls back; source coverage and mutation checks remain | Cache/discovery gates passed; summary visibility of material caps reopened and being corrected |
| O6 | Controlled before/after measurement and a readable outcome | Corrected baseline and candidate identified separately; results explain improvements, regressions, remaining limitations and failed targets | Partial pre-fix series stopped after 25 completed invocations; retained as diagnostics, excluded from final statistics; fresh 43-invocation series required |

O1/O2, O3/O4 and O5 may proceed independently in separate checkouts. Integration
must reconcile their public interfaces before running the complete gate. Each
component PR requires independent code review, applied corrections, and green
checks before integration. The final combined PR requires another independent
review and green checks; the user performs the final merge.

## Measurement acceptance

These are targets, not observations or product guarantees:

- Ordinary output is approximately 30–60 lines and does not enumerate all scanned
  usages. Essential limitations remain visible when other groups are summarized.
- At least 30% lower median first-run time and 50% lower median repeat-run time
  against the corrected baseline, with comparable scope and evidence quality.
- No new false blocker, unsupported repair claim, or promotion of unknown or
  partial analysis to verified application compatibility.

Measure time to the first useful evidenced result, analyzer and end-to-end time,
actual Composer process count, memory scope, source coverage and output size.
Keep cache preparation outside analysis timing and report it separately. Control
metadata/network state for causal comparisons; otherwise label live results as
observations. Do not compare injected-failure runs as ordinary performance.
Never meet a timing target by silently omitting work or suppressing uncertainty.

Full traces belong in task-owned temporary storage or explicit diagnostic
attachments, not a second large archive in Git. Keep one concise outcome document
with reproduction instructions and enough measurements to assess the claim.

## Verification and outcome reporting

Develop with focused behavior tests. Before completion run the Docker
`composer check` gate, the existing coverage/mutation and privacy/immutability
checks, supported runtime/Windows CI, and independent reviews on the final heads.
Schema/format parity and cache invalidation need behavioral assertions; snapshots
alone do not establish correctness.

The outcome must state what changed for a user, what became faster under which
conditions, which targets were missed, and what remains unverified. Repository
corpus measurements do not complete the [R3 user study](../readiness/decision-study.md).
