# Upgrade decision study: protocol and record

Status: **UNAVAILABLE — VALIDATE_FIRST**. No participant study, actual second-decision reuse, or Symfony-owner use case has been supplied. This document prepares the R3 study; it does not complete any R3 acceptance criterion. The provisional reader and decision job come from the [decision baseline](decision-baseline.md); the current report states are described in the [reading checklists](report-reading-checklists.md).

## Protocol fixed before observation

**Question.** Does a PHP/Laravel lead or upgrade consultant make a faster, at least equally sound scope and sequence decision for a real Composer/PHP/Laravel upgrade using Preflight than with their usual Composer, upgrade-guide and other tool workflow? Recruit voluntarily through the maintainer. Aim for five completed comparisons from at least three independent intended-user teams. Record the role and team under anonymous IDs; an agent must not contact candidates without authorization. Do not treat an agent's report reading as a participant result.

**Timebox.** Start the clock with the first participant study session or analysis of a participant-supplied task after this protocol is fixed. Stop after ten *working days of active evaluation*; record dates and active days used. Protocol preparation, participant scheduling and waiting are excluded. Recruitment failure, unavailable projects and an expired timebox are study outcomes to record, not reasons to invent substitutes. A completed comparison requires both workflows, the participant's decision answers, timing and a reviewable sanitized evidence trail.

**Task and order.** Give each participant a bounded, pre-implementation choice: identify the first evidenced dependency blocker (or state that no blocker was established), the next safe action with its evidence, the distinct direct and staged meanings, unobserved runtime/deployment/migration work, and a scope/sequence decision. Compare their normal workflow with Preflight on two equivalent upgrade tasks from a project they can inspect. Record why the tasks have comparable starting state and difficulty. Alternate workflow order across participants (normal→Preflight, then Preflight→normal) and swap task assignment where possible. If only one project/task is available, repeat on that task with order alternated across participants and flag possible carryover; do not attribute a time gain to the tool without that caveat. Capture the participant's starting familiarity with Composer, upgrade guides, other tools and Preflight before either task.

**Execution and provenance.** Record a sanitized project/task ID, framework and upgrade hop, manifest/lock or revision fingerprint, applicable PHP/Composer/tool versions, Preflight revision and report schema, its exact command/options including Composer mode and network/cache conditions, and report artifact ID/hash. Record the participant's normal tools, exact commands/options and guide versions or access dates too. Retain sensitive project files and full reports only under participant control; record sanitized observations here with their permission. Do not add telemetry, hosted uploads, payment or licensing changes for this study. Run Preflight only for its documented local, read-only analysis; no application boot, tests, migrations, deployment or target edits are part of its run. The participant can use their usual workflow, including tests, but record those steps separately so the comparison is interpretable.

**Timing.** For each workflow, record active discovery/setup, installation, analysis/tool execution, reading/investigation, and decision-writing time in minutes, plus the total and any excluded waiting or unrelated work. Use a timer or timestamped observation; record the method. Pre-existing installation takes zero installation time only when directly observed or confirmed; an unmeasured duration is blank and marked unavailable. Planning time is the sum of active components for the bounded decision task, not an application-upgrade duration. Record whether Preflight installation was fresh, already present, or assisted. Keep runner/resource cost in R4, not in participant planning time.

**Answer quality.** Score each workflow answer against the same five checks, using the actual project and cited evidence: (1) first blocker or honest unknown, (2) evidence and next safe action, (3) direct versus staged distinction when applicable, (4) unobserved runtime, migration and deployment work, and (5) defensible scope/sequence decision. Each check is correct, partial, incorrect or unavailable, with a brief reason and source reference. For the planning-benefit gate, order comparable scores as incorrect < partial < correct; no Preflight check may score below the corresponding normal-workflow check for any participant. Mark a check not applicable only when it does not apply to either equivalent task, with a reason. If an applicable check is unavailable in either workflow, the paired quality comparison and planning-benefit gate are unavailable, not passed. An independent maintainer review should adjudicate disagreements without seeing which workflow produced an answer where practical. Log a high-impact false claim or missed work separately. A **critical misleading compatibility conclusion** asserts an upgrade is safe/complete when the available evidence is blocked, unknown, partial or only a dependency solution; stop the study run, record the exact claim and evidence, correct the participant's decision before they act, and route the product issue for remediation. Do not count a corrected answer as an uncorrected success. Count every observed critical conclusion in attempted as well as completed runs, including corrected ones, in the safety incident ledger and criterion.

**Reuse and Symfony.** After the first comparison, record separately (a) interest or a commitment to use a report again and (b) an *observed voluntary* second decision/project using a report, with date, distinct decision ID and team. Intent does not satisfy reuse. Seek at least two concrete Symfony cases from intended Symfony owners: record the upgrade hop, rooted component or dependency problem, manual/recipe work, present decision process and the specific improvement a read-only report could offer. A generic feature request or an agent-invented example is not a case.

**Version comparability.** Fix this protocol's wording and scoring rules before collecting answers. Each record names the protocol revision (repository commit), Preflight revision, schema and report revision/hash. If report wording or behavior changes during the study, keep the original records, label the new cohort, describe the change, and compare only like versions or qualify the result. Do not silently pool unlike reports.

## Predeclared decision criteria

These are small-sample management thresholds, not market statistics. Evaluate coverage, comprehension and planning benefit from completed, reviewable pairs; evaluate the critical-safety criterion from every attempted run. Keep incomplete attempts in the record. Unavailable is distinct from failed. A supportive result requires all of the following:

| Criterion | Threshold | Current evidence |
| --- | --- | --- |
| Coverage | Five completed paired studies across at least three independent intended-user teams within ten active working days | UNAVAILABLE |
| Action comprehension | At least four of five participants identify the next safe action and its evidence correctly with Preflight | UNAVAILABLE |
| Critical safety | Zero observed critical misleading compatibility conclusions across all attempted runs, including stopped or corrected runs | UNAVAILABLE |
| Planning benefit | Median paired active planning time at least 20% lower with Preflight, with no lower score on any applicable quality check for any participant | UNAVAILABLE |
| Actual repeat use | At least three independent teams voluntarily use a report on a distinct second decision/project | UNAVAILABLE |
| Symfony direction | At least two concrete, rooted Symfony-owner use cases before selecting Symfony | UNAVAILABLE |

Calculate each participant's paired change as `(normal total − Preflight total) / normal total`; take the median of those changes, retaining raw times and task/order caveats. Do not compute a ratio from absent or zero normal time. Compare answer-quality scores and critical errors alongside speed, never speed alone. Record the number of attempted runs and every observed critical incident even when the pair is incomplete; one observed incident fails the zero-critical criterion. If observations fail a threshold, record **FAILED** with the denominator and source records. If records are missing or incomparable, record **UNAVAILABLE** and keep the direction **VALIDATE_FIRST**. Failure or incompleteness calls for conservative scope reconsideration; absence of demand evidence is not proof of negative demand. Symfony remains deferred unless its owner-case gate, R3/R4 evidence and a later explicit release-scope decision support it.

## Sanitized study record template

Copy the following fields for each *actual* completed or attempted study. Leave unmeasured values blank and mark their status **UNAVAILABLE**; never fill them with zero, a guessed baseline or an agent result. Keep a separate row for an attempted study that did not complete both workflows, with the reason.

| Field | Observation |
| --- | --- |
| Record ID; status (COMPLETE / INCOMPLETE); reason if incomplete |  |
| Anonymous participant/team IDs; intended role; permission to retain sanitized record |  |
| Active evaluation date/day number; protocol revision |  |
| Starting tool knowledge; fresh/pre-existing/assisted installation |  |
| Task A and B IDs, hop, equivalence rationale; workflow order and carryover caveat |  |
| Project revision/fingerprint; PHP, Composer, normal-tool commands/options and guide versions; network/cache conditions |  |
| Preflight revision/schema, command/options/Composer mode; report ID/hash and report revision |  |
| Normal workflow discovery/setup, installation, execution, reading, decision minutes; total; timing method |  |
| Preflight discovery/setup, installation, execution, reading, decision minutes; total; timing method |  |
| Excluded waiting or unrelated work, minutes and reason |  |
| Normal and Preflight answers: first blocker/evidence, next action, direct/staged meaning, unseen work, scope/sequence |  |
| Five quality scores per workflow, source references, adjudicator and rationale |  |
| High-impact false claims/missed work; critical incident ledger, stop, correction and remediation reference, including incomplete attempts |  |
| Useful *new* decision attributable to report, or none, with supporting answer difference |  |
| Interest/commitment to reuse; observed distinct second decision ID/date/team/report, if any |  |
| Sanitization and comparability notes; missing fields/status |  |

Use the two blank forms below only for actual Symfony-owner cases. Blank fields and unavailable status do not count as use cases.

| Symfony case field | Case A | Case B |
| --- | --- | --- |
| Status | UNAVAILABLE | UNAVAILABLE |
| Anonymous owner/team ID; intended role; permission to retain sanitized record |  |  |
| Project revision or sanitized fingerprint; current→target Symfony hop |  |  |
| Rooted component/package and observed constraint, with evidence reference |  |  |
| Manual/recipe work and current tools/decision process |  |  |
| Specific decision a read-only report could improve, and why |  |  |
| Sanitization and missing-evidence notes |  |  |

**Current record.** Participant records: **UNAVAILABLE**. Active evaluation start/end and days used: **UNAVAILABLE**. Timing, answer quality, critical-claim observations, second-decision reuse and Symfony-owner cases: **UNAVAILABLE**. The R3 value hypothesis is untested. Carry this status to R4's benefit comparison and the [R5 `VALIDATE_FIRST` decision](release-direction.md) until real evidence is recorded.
