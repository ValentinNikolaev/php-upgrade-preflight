# Optimization verification and review

Integration status: all four components reviewed, green and merged into the
optimization branch; combined local gates passed. Combined review and controlled
measurements are pending. No release or main merge is authorized here.

## Component acceptance

Each author worked separately. Independent reviewers inspected the complete
component diffs, reported concrete defects, then reassessed the corrections.
An APPROVE below describes code review, not unobserved application compatibility.
Workers used Full Access with approval disabled, `service_tier=default` and
`features.fast_mode=false`; no Fast/Ultrafast worker was launched. Component heads
and coordinator commits use verified Git signatures.

| Component | Reviewed final head | Independent result | Exact-head Quality |
| --- | --- | --- | --- |
| [PR49: concise output](https://github.com/ValentinNikolaev/php-upgrade-preflight/pull/49) | `fb882ce352ad03cf499e47de86b012ebbb4542f4` | APPROVE after UTF-8 correction | [19/19 successful](https://github.com/ValentinNikolaev/php-upgrade-preflight/actions/runs/39027624274) |
| [PR50: Composer execution](https://github.com/ValentinNikolaev/php-upgrade-preflight/pull/50) | `dbf1189fad254fa35e0e7156e99faf128437270c` | APPROVE | [19/19 successful](https://github.com/ValentinNikolaev/php-upgrade-preflight/actions/runs/39025640241) |
| [PR51: source facts and discovery](https://github.com/ValentinNikolaev/php-upgrade-preflight/pull/51) | `f57c37eed8eb0a5091082feb5173c472852dd92e` | APPROVE after both vendor-boundary corrections | [19/19 successful](https://github.com/ValentinNikolaev/php-upgrade-preflight/actions/runs/39028079853) |
| [PR52: measurement controls](https://github.com/ValentinNikolaev/php-upgrade-preflight/pull/52) | `1ab5fc1b0f36e5619c695b0aa8da322eb50c7859` | APPROVE | [19/19 successful](https://github.com/ValentinNikolaev/php-upgrade-preflight/actions/runs/39026343070) |

Review receipts and exact scope are recorded in the linked PR comments. Complete
local reports remain under the task-owned OS temporary directory, not a new
archive in Git. Authors' broad gates and reviewers' focused checks are separate
evidence; their test counts must not be summed as unique coverage.

## Defects found and corrected before measurement

| Problem | Consequence | Correction and verification |
| --- | --- | --- |
| Unknown cause version filled from installed-lock context | Candidate conflict could be attributed to an installed version | Keep cause version unverified and show installed context separately; candidate-only regression fixtures and independent attribution review. |
| Malformed UTF-8 in a displayed source filename | Default saved/terminal summary could contain invalid UTF-8 | Full-value privacy, native UTF-8 substitution, control removal, then byte budgeting. Independent old-writer negative control, 27 tests / 318 assertions and six sanitization scenarios passed, including mbstring-disabled execution. |
| Absolute or symlinked `vendor-dir` evaded automatic root exclusion | Dependency implementation could be labeled project source | Compare canonical absolute paths, retaining linked root-omission evidence and explicit-selection behavior. |
| Nested PHP file symlink reached vendor through a parent source root | Directory pruning alone still admitted dependency facts | Guard both resolved-file insertion paths before caps, reading, hashing and caching. Independent final review passed 19 tests / 394 assertions, an 18-case selection matrix and three cap controls. |
| Docker Desktop external cache mount owned by UID 0 | Private-cache checks safely bypassed reuse | Runtime process creates its private `facts` child; independent two-container persistence and identical-facts controls passed. No permission checks were relaxed. |
| Private comparison controller trusted incomplete resume identity | A misplaced completed receipt could enter paired statistics | Bind exact plan, request, argv, provenance, source snapshots and sealed output hashes. Independent original negative reproduction and 34 follow-up checks passed; no corpus had run when the defect was found. |

PR50 independently passed 27 unit cases / 501 assertions and four integration
cases / 155 assertions, including real offline exact-failure to `-W` success.
The PR49 initial review additionally exercised CLI/Artisan routing and transition
parity; the five-minute demo and signed historical contract fixtures remain
unchanged. PR52 independently exercised 20 host tests and four selected actual
Docker controls. None of these counts establishes real-user planning benefit.

## Combined gate

The only manual merge conflict was the new optional collector-key interface:
PR51's reviewed superset adds its per-scan stability documentation to PR50's
identical runtime declaration. The reviewed PR51 file was retained verbatim.

At integrated runtime head `b6f24fe3a98c1d0e3d87a3885a738bf4e3454c7b`, Docker
PHP 8.5 `composer check` passed: 1,538 unit tests / 11,687 assertions, 112 integration
tests / 6,305 assertions, two smoke tests / five assertions, all manifests, Wiki
checks, both PHPStan configurations and formatting across 392 files. A separate
PHP 8.3 PCOV run passed the unchanged ratchet at 12,912 / 13,370 executable lines,
with the same 1,538 unit tests / 11,687 assertions. Clover output lived only in its
disposable container; readable gate logs remain in task-owned temporary storage.

The integration suite includes `OptimizationCompositionTest`: real offline
Composer checks default modular discovery, cold/warm/full fact equivalence,
fewer standard scenarios, explicit path selection, current edits, bounded
presentation and target immutability. Its earlier read-only overlay preview was
only preliminary evidence; the integrated gate covers the final vendor fixes.
Final combined review and provider CI remain separate requirements.

The actual Docker harness also passed its two selected controls against this
combined runtime: private source facts persisted across two containers with
identical source inventory/impact, and the full-mode/disabled-cache invalid-input
control produced an honest unknown report with zero Composer starts, measured
phases, immutable input and complete cleanup. These are functional controls, not
public-project timings.

## Measurement boundary

The separate task-owned controller is approved at SHA-256
`0fd6cd4c1048ef86178b08245e16cc71c9f8edbd59e40bee90789284522938a4`.
Its 34 synthetic controls establish binding/rejection behavior, not performance.
The planned comparison has 40 paired first/repeat invocations and three full-mode
controls, using ten pinned sources, one fixed Docker image, no network, and
identical historical post-cold Composer metadata seeds. First-run source caches
are empty; this is not a cold-network experiment.

Analyzer time, summary readiness, full rendering, container wall time and
controller wall time have separate scopes. PHP heap peaks are cumulative and
exclude Composer-child memory; absent instrumentation remains unknown. Broader
autoload coverage and omitted standard alternatives must accompany speed claims.

## Artifact accounting

Raw runs, source/cache pins, the controller and readable independent reports are
retained locally under `%TEMP%/preflight-opt-20261017` for reproduction and review.
Earlier prepared cohorts and the excluded BookStack pilot are diagnostics, not
accepted paired measurements. Existing historical R4 artifacts are preserved.

Automatic approval review rejected deletion of known generated PHPStan caches,
some coverage/mutation/lint artifacts in component checkouts, and the empty
cache-mount probe directories. They remain local; rejected deletions were not
retried through another tool. Reusable vendor trees are retained. No `build/`
directory was removed wholesale, and `build/integration-profile.xml` is protected.
