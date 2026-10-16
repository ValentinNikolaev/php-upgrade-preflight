# R3 engineering exercise materials — draft

Status: **ENGINEERING PREPARATION; REPORT-SPECIFIC KEYS PENDING** (2026-10-17).
These materials support a PHP/Laravel upgrade lead's bounded scope/sequence decision.
They are not actual participant comprehension, planning benefit, demand, user or reuse outcomes.
Public-project report bodies are not yet supplied to this draft; no case outcome is asserted below.
The [R3 protocol and thresholds](../decision-study.md#predeclared-decision-criteria) remain canonical;
this document adds no acceptance gate, numerical score or substitute participant result.
Use the [reading checklists](../report-reading-checklists.md) and [R4 protocol](protocol.md#r3-boundary).
The user's stated **>5 hours/week maintenance capacity** is a capacity statement, not observed maintenance cost.

## Facilitator preparation — withhold the reviewer sections

- Freeze the protocol revision before observation; record tool/report revisions and schema per session.
- Supply the participant only the common brief, assigned card, source snapshot and workflow-appropriate packet.
- Before release, attach actual JSON/Markdown reports, command/options, hashes and run provenance to each card.
- For a normal-workflow task, supply the equivalent source and task brief without exposing its Preflight report/key.
- Review the key against pinned inputs and authoritative upgrade guidance; retain disputed claims as unverified.
- Do not run application code, migrations or deployments as part of Preflight analysis; record normal-workflow checks separately.
- Keep private source/full reports under participant control and retain sanitized records only with permission.
- No outreach is authorized by this preparation. An agent rehearsal is engineering evidence only.

## Participant handout: common brief

You lead an upgrade and must recommend its next bounded step before implementation.
Use your assigned workflow and cite the evidence you actually inspected; uncertainty is an acceptable answer.
Produce a short decision memo answering all five prompts:

1. What is the first evidenced dependency blocker? Cite its subject and evidence, or say none was established.
2. What is the next safe action, and which evidence justifies doing that before other work?
3. What do the direct and staged results each establish? State any stop, skipped stage or unknown separately.
4. What runtime, migration, deployment and business behavior remains unobserved, and how would you validate it?
5. What scope and sequence do you recommend now, including prerequisites and the condition for proceeding?

Do not infer application readiness from a dependency solution, low risk grade, effort range or report exit code.
Record commands, guide versions/access dates and evidence locations so another lead can review the decision.
Tell the facilitator when you start/finish each active phase and when waiting begins/ends.

## Participant exercise cards

All revisions and requests below are anchored to [the frozen project manifest](projects.json).
Report availability and answers remain **PENDING**; the facilitator must bind a reviewed run before using a card.
Cold/warm repeats are the same task and may expose changed live metadata; they are not independent difficulty levels.

| Card | Frozen case and request | Decision emphasis; no outcome implied |
| --- | --- | --- |
| A | Crater `05d5ce26fdd8d9466009163444e944259bc0cc2a`; Laravel 8.83.16 → 9; PHP 7.4 → 8.1; nearest | Identify the first evidenced dependency issue or honest unknown; propose the smallest justified next step. |
| B | BookStack `07e45a20e55fc955e9ef252d0653f23193a71a77`; Laravel 10.48.25 → 11; PHP 8.1 → 8.2; nearest | Separate root/dependency constraints from source/manual review; justify a bounded sequence. |
| C | Lychee `bf5495c706a9971ebfcd03a88c36e336fc0261d5`; Laravel 12.36.1 → 13; PHP 8.3 → 8.3; nearest | Distinguish a solve conclusion from unobserved application behavior and resource limits. |
| D | Bagisto `8c33fbbd89b64043d98697f5590d6de658a9241b`; Laravel 10.41.0 → 11; PHP 8.1 → 8.2; nearest | Trace high-impact advice to its evidence and identify prerequisites before implementing it. |
| E | Supplemental Monica7 `6d809fda1d59048521c6d5c7e8c3306039b18d4c`; Laravel 7.28.1 → 13; PHP 7.4 → 8.3; longest | Explain direct versus staged scope, the first actionable executed stage and any limit on later hops. |

For every card, answer the common five prompts; the emphasis does not reduce the task.
Monica7 is supplemental and a six-hop request, not proof of six successful live transitions.
Lychee's longest request duplicates its nearest target; it adds no transition coverage.

**Card U — incomplete evidence variant.** Use an actual frozen campaign timeout/unknown probe only after its
run record is supplied. Identify what was observed, what failed or timed out, and the next evidence-gathering step.
If no valid report exists, use retained stderr/watchdog/provenance and say the report is unavailable.
Do not invent a blocker, compatibility result or later-stage result to complete the memo.
This is a controlled evidence-limit exercise, not an observed incident or participant response.

## Task equivalence and order — facilitator only

Follow [the canonical task/order procedure](../decision-study.md#protocol-fixed-before-observation).
Prefer two inspectable tasks from the participant's project; these public cards are engineering preparations.
Compare starting framework/PHP, hop count, dependency/source complexity, prior familiarity and evidence availability
before declaring two tasks equivalent; shared Laravel majors alone do not establish equivalent difficulty.
BookStack/Bagisto are candidates for review, not a prevalidated pair. Do not pair Monica7 with a nearest-hop task
or a timeout probe with a completed solve as if their difficulty were equal.
Alternate normal→Preflight and Preflight→normal across participants; swap task assignment where feasible.
Record assignment and rationale before answers. If only one task is available, repeat with alternated order and
explicit carryover caveat; learned answers can explain faster performance. Do not attribute that gain solely to Preflight.
Do not pool unlike report/tool versions or silently replace an inconvenient failed attempt.

## Expected-answer rubric — reviewer only, separate from handout

Use the same five checks for both workflows. Record **correct / partial / incorrect / unavailable** with reasons
and source references as defined in [the R3 protocol](../decision-study.md#protocol-fixed-before-observation).
An honest unknown can be correct when it accurately describes the available evidence; a missing answer is unavailable.
Use not applicable only when the check applies to neither equivalent task, with a reason.
Do not convert these labels to points or compute a quality gate when an applicable paired check is unavailable.

| Check | Expected reasoning once report/source evidence is bound | Common error to flag |
| --- | --- | --- |
| First blocker / unknown | Trace a resolution-blocking subject to the actual attempted state; distinguish advisory findings and absent evidence. If no blocker is established, say so without claiming feasibility. | Treating the first advisory package finding, timeout or skipped stage as a proven incompatibility. |
| Evidence + next action | Cite an exact JSON field/scenario/stage, diagnostic or source location; repair the evidenced constraint/input/environment, or review a successful candidate transition before implementation. | Guessing a package upgrade or changing the lock without reviewing the relevant attempt. |
| Direct / staged | Read statuses independently; direct scenarios have no selected marker in schema 0.8. For staged work inspect execution, selection and stop evidence; do not extrapolate beyond a blocked/unknown/skipped stage. | A direct solution clears every hop, or a staged solution proves the direct final solve. |
| Unobserved work | Identify application tests, target platform/runtime checks, migrations, deployment and business behavior as unobserved unless separately evidenced; stage source checks use the original snapshot. | Dependency resolution or source scanning proves the deployed application is safe. |
| Scope / sequence | Recommend a bounded step tied to the first justified action, prerequisites and validation before advancing; preserve unknowns and distinguish investigation from implementation. | A complete upgrade estimate or deployment authorization inferred from an uncalibrated effort range. |

For a feasible dependency result, expected reasoning reviews candidate-lock evidence and reported changes,
then schedules application validation; it does not promise successful runtime behavior.
For blocked, unknown or skipped results, follow the corresponding [state checklist](../report-reading-checklists.md).
On a critical misleading compatibility conclusion, apply the canonical stop/correct/remediate procedure and
record the incident even in an incomplete or corrected attempt; correction does not erase the observation.
Use independent maintainer adjudication, blinded to workflow where practical, for disputed answers.

## Evidence labels and false-positive review — reviewer only

Label each high-impact claim **SUPPORTED**, **CONTRADICTED**, **UNVERIFIED**, or **UNAVAILABLE** with a citation.
These labels describe claim review, not participant quality scores or measured false-positive rates.
- Bind the exact snapshot, request, scenario/stage and report hash; do not use a different retry's evidence.
- Verify a dependency claim against root constraints, lock/platform and actual Composer diagnostics/candidate evidence.
- Verify a source finding at its path/line and relevant guide; inspect inheritance, conditional code and applicability.
- Separate a source-rule false positive from an expected dependency blocker, harness failure or metadata/network drift.
- Absence of a finding does not establish absence of work; unknown recall remains unknown without an oracle.
- Treat advisory memory/report-size budgets and missing measurements as limits, not measured passes or zero usage.

## Sanitized record form — blank; copy only for actual attempts

Use [the full canonical record](../decision-study.md#sanitized-study-record-template) for study storage.
This compact worksheet is an entry aid; no participant responses or measurements have been populated.

| Field | Value / availability |
| --- | --- |
| Record, anonymous participant/team/role; retention permission; complete/incomplete + reason | UNAVAILABLE |
| Date/active study day; protocol revision; starting familiarity; installation fresh/present/assisted | UNAVAILABLE |
| Task IDs, revisions/hashes, hops, equivalence rationale; assignment/order/carryover | UNAVAILABLE |
| PHP/Composer/tool versions; normal commands/guides; Preflight revision/schema/options/mode; network/cache | UNAVAILABLE |
| Report IDs/hashes/revisions and source/diagnostic references | UNAVAILABLE |
| Each workflow: discovery/setup, installation, execution, reading, decision minutes; total; timer method | UNAVAILABLE |
| Excluded waiting/unrelated minutes + reason; separate R4 resource-record reference | UNAVAILABLE |
| Verbatim sanitized answers to five prompts; evidence; per-check labels/reasons; adjudicator | UNAVAILABLE |
| High-impact claims/missed work; incident/stop/correction/remediation, including incomplete attempts | UNAVAILABLE |
| New decision attributable to report; reuse interest separately from observed distinct second decision/date/team | UNAVAILABLE |
| Missing fields, sanitization and comparability caveats | UNAVAILABLE |

Leave unmeasured numeric cells blank with status UNAVAILABLE; zero requires direct observation/confirmation.
Do not calculate time benefit from absent/zero normal time or mix runner cost with participant planning time.

## Pending report references — required before exact answer-key completion

- A–D: nearest Crater, BookStack, Lychee and Bagisto canonical JSON + Markdown, run IDs/hashes and provenance.
- E: supplemental Monica7 longest report pair, executed stages/selection/stop evidence and run provenance.
- U: actual short-timeout/unknown probe request, diagnostics, watchdog/exit record and report pair if produced.
- Every assigned key: exact blocker/evidence/action citations, direct/staged outcomes, selected claim/source review,
  reviewer disposition and source/guide references; unresolved claims stay explicitly pending.
- Any chosen paired task: reviewed equivalence rationale and normal-workflow source packet before observation.

No actual R3 participant, timing, safety-observation or voluntary-reuse record is supplied by this draft.
