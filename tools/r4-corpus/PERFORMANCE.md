# Staged PHP performance follow-up

This is a prepared protocol and opt-in controller, **not executed results**.
Preserve the accepted 43-row cohort and three controls under
`%TEMP%/preflight-opt-20261017` unchanged. New raw evidence belongs under
`%TEMP%/preflight-performance-20261017`, outside Git. Do not run a corpus while
another workload or profiling campaign uses the measurement CPU.

## Ordered decisions

1. **Cache-off control before redesign:** all ten projects, previous `6fe3249`
   versus reviewed P1 default-cache-off, one first/repeat pair each: 40 invocations.
   Alternate which runtime runs first by project. Source discovery, caps,
   requests, image, Composer settings and retained facts must match. The previous
   runtime uses an explicit external private fact directory, enabling controlled
   first-to-repeat persistence just as in the accepted comparison; this measures
   that configuration, not the ephemeral default TMPDIR cache lifetime.
   P1 uses its reviewed default with no forced disable flag. Do not redesign the
   cache before reviewing this measured control.
2. **Separate profiles:** Crater, Invoice Ninja and Bagisto, at an exact reviewed
   profiling-capable head. Three full analyzer calls are the maximum default;
   a smaller scanner-only investigation can be separately predeclared. Profiles
   are instrumented and excluded from uninstrumented timing comparisons. Use the
   evidence to select a redesign, not a presumed parser/cache bottleneck.
3. **Final frozen comparison:** P1 versus optimized runtime, two independent
   alternating paired replicates per project: 40 invocations. Both defaults have
   persistent caching off, so these are independent samples, not artificial
   first/warm fact-cache labels. A third pair on **all ten** is permitted only
   when absolute cohort median difference is below 10%, or any project/role's
   replicate range exceeds 10% of its median. These are descriptive triggers,
   not significance tests. The third pair is a separate 20-invocation plan,
   bound to the completed first two pairs and reviewed before execution. Never
   pick only favorable projects. Keep all samples; report ranges and both median
   formulas. The previous/P1 stage and final P1/optimized stage remain distinct;
   do not pool across dates/heads or manufacture a three-way same-session result.

Every invocation has a **fresh independent Composer cache**, seeded from the
same frozen historical post-cold metadata. First/repeat in stage 1 refers only to
the explicit source-fact directory; Composer metadata is matched even on repeat.
These are offline metadata-warm conditions, not network/OS-cache-cold runs.
Baseline `6fe3249686866e0b9764ceb7eb2b51c89aad88e6` and P1 must use immutable
checkouts; preserve the earlier `2a0b01ad` checkout. Reviewed final/profile heads
must be supplied before their stage is prepared. No placeholder head executes.

## Plan, review, execute, export

`performance.py plan --config CONFIG --output NEW_COHORT` verifies inputs and
runtime identity, then freezes the harness, exact heads/provenance and schedule.
Planning hashes complete source/seed/runtime trees and queries the pinned local
image: do it before timed work, only when preparation is authorized. It performs
no downloads or application code execution. Do not prepare against a mutable
source-worker checkout. Installed vendor trees may be reused through owned
junctions only after lock identity checks; Docker sees the runtime read-only.

Configuration fields: `stage`, `repetitions`, exact `image` ID,
`reference_plan`, `reference_plan_sha256`, `reference_summary_sha256`, and `variants` mapping stage roles to
`{repo, head}`. Stages/roles are `cache-off` (previous/cache-off, repetitions 1),
`profiles` (profile, 1), `final` (cache-off/optimized, 2), and `final-third`
(cache-off/optimized, 1 with repetition ID 3). A final-third config also supplies
`prior_final_plan`, `prior_final_plan_sha256`, `prior_final_summary_sha256`;
its validated completed data must meet the trigger. The accepted v4 reference
plan hash is `26c39cb19475f53e74361eb7aa4e1ede1dc4372389e831d1c6d5ed88e79c361b`.
Its accepted summary hash is
`78bb6c593215cb000f50ae929fdf4e75604605c03fb965c49e622b6ecd052506`;
the reference retained-fact expectation binds through its sealed run/report hashes.

A variant can additionally declare `vendor`: a separately verified shared
dependency directory mounted read-only at `/app/vendor`. Windows junctions alone
are not assumed to cross Docker bind mounts. Provenance hashes the actual overlay
bytes. The harness prepends `/app/packages` for its own Core/CLI/Laravel namespaces
so copied vendor path packages cannot shadow the selected runtime, and attests
actual reflection filenames/hashes of DefaultUpgradeAnalyzer, SourceUsageScanner
and UpgradeReport against the plan before analysis. This preflight class loading
is outside the analysis timer for every variant. No vendor tree is rewritten.

The coordinator reviews the frozen plan/controller and explicitly authorizes
its SHA-256 before invoking:

```text
python -B COHORT/harness/performance.py run --plan COHORT/plan.json --approved-plan-sha256 EXACT_REVIEWED_HASH
python -B COHORT/harness/performance.py export --plan COHORT/plan.json --output FRESH_DERIVED_DIRECTORY
```

Only sealed completed entries can resume. An unsealed existing output, failed
invocation or unknown cleanup stops the stage; ABORTED cohorts cannot resume or
export statistics. Stale controller leases require explicit ownership review,
not automatic deletion. Outer timeout waits for the host subprocess to exit and
uses the existing exact-owner recovery command; retain all diagnostics even if
recovery fails. Never delete containers by prefix or remove an unrelated cache.
Human interruption may leave an active host child: inspect ownership/PID and
prove host exit before recovery or a new attempt. No automatic retry is allowed.

## Metrics and acceptance

Every completion binds reconstructed argv/request, plan/controller/harness,
runtime/dependency/image/source identity, same-object report artifacts, seed
proof/gate and positive container/host-scratch cleanup. Boundary hashes are
rechecked; heads/clean runtime are checked per invocation. Each fresh Composer
cache/output is retained independently. The previous source cache starts empty;
repeat-before must match first-after. Retained fact tuples and source-limit
qualifications must match the accepted candidate-first reference for each case;
changed verdict/source scope stops admission rather than silently changing work.
This is not full semantic equivalence, scanned-file coverage or recall.

The CSV and readable export preserve all rows, instrumented status, exact heads,
states, starts, retained-fact identity, represented files and seals. Missing
instrumentation stays blank/null. Analysis, summary-ready, preliminary direct
finding, profile scopes and controller envelopes are separate. Summary-ready is
not first-blocker delivery; the direct event is a preliminary supported cause,
not a final verdict or verified repair. Full-format rendering still happens
afterward; controller/container wall time and shutdown heap are not the normal
summary-command cost. Individual Composer PHP VmHWM is separate from analyzer
heap; neither is a concurrent process-tree peak.
Controller wall covers invocation, cleanup and postflight source verification;
initial source/seed/provenance preflight is excluded. The export labels heap
through analysis, shutdown heap and analyzer-process VmHWM separately; individual
Composer process records remain in raw JSON and must not be summed into a peak.

`--source-profile` is optional; unsupported APIs fail before analysis. Injected
`SourceScanProfile` survives request-local scanner clones. Its numeric snapshot
contains inclusive `total_ms`, exclusive `timings_ms` discovery/read/hash/
cache_get/parse/cache_put/evidence and file/byte/cache counters. Parse includes
AST traversal/collectors; hash includes scanner key identity; cache operations
include validation/serialization. **Input capture/rechecks are outside this scan
profile**, including work otherwise hidden in composite report-assembly time.
Do not sum inclusive total and exclusive components or call all unexplained time
parser overhead. Null profile performs no source-profile clock work. Timing of
`DIRECT_FINDING` is captured independently if that runtime emits the event.

When available under the same opt-in, a retained `AnalysisTimings` object records
completed `analysis_profile` scopes: source_capture, source_recheck_after_scan,
input_recheck_final (Composer and source consistency), ownership, source_impact,
stage_assessment, risk_effort and assembly. Missing APIs remain null. These scopes
are separate from scanner timings and inclusive analyzer total; do not add
overlapping scopes together. Uninstrumented comparison stages inject neither object.

Five prior projects were offline unknowns; preserve operational unknowns and
their timings. No gain may come from skipped checks, changed source caps or
unknown shortcuts. Existing 30%/50% goals remain unmet until new controlled
evidence says otherwise. No successful upgrade, source recall, R3 participant
benefit, demand or release claim follows from this tooling.
