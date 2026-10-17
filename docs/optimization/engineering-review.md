# Optimization verification and review

Integration status: all four initial components reviewed, green and merged into
the optimization branch; combined local gates and independent code review passed.
A subsequent semantic review of actual public-case reports found two remaining
correctness defects. Both corrections have independent approval, 19/19 final-head
CI, and signed integration merges; the corrected combined local gate passed.
Fresh measurements and the final aggregation review/CI remain. No release or
main merge is authorized here.

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
| [PR53: visible source caps](https://github.com/ValentinNikolaev/php-upgrade-preflight/pull/53) | `45e825178f089634a47b1830a6995c0204db7884` | APPROVE; real omitted-usage warnings and five cap kinds verified | [19/19 successful](https://github.com/ValentinNikolaev/php-upgrade-preflight/actions/runs/39032483143) |
| [PR54: proved causes and retained policy reasons](https://github.com/ValentinNikolaev/php-upgrade-preflight/pull/54) | `45555c245741defed4134b4582978dc048576204` | APPROVE after mixed-cause, grouping and legacy-ID corrections | [19/19 successful](https://github.com/ValentinNikolaev/php-upgrade-preflight/actions/runs/39034323229) |

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
| Compatible requirements or locked notices treated as incompatible package edges | Readers could be directed to change Pest, Socialite or Google2FA without supporting cause evidence | Require disjoint requirements or conflicts covering the requested range; keep unresolved chains unclassified and preserve advisory-policy rejection. Recorded-excerpt controls and incompatible-edge/provenance regressions pass. |
| Advisory precedence and shared unknown identities discarded distinct explanations | A genuine raw edge or the security-policy reason could disappear from grouping, stages or summary | Retain independent raw causes alongside policy; use one internal explanation identity through all grouping and lifecycle boundaries. Independent raw/grouping/reappearance/supersession/summary probes pass; no schema fields added. |
| First identity correction changed established generic-unknown IDs | Two frozen Laravel integration snapshots failed in CI | Preserve the standard fallback's legacy identity while distinguishing richer explanations. The actual failing tests pass at the final head; old-head negative controls still fail and snapshots remain unchanged. |

PR53's reviewer independently passed 32 tests / 436 assertions, reproduced five
old-behavior failures and checked the exact Lychee/Snipe-IT cap strings. PR54's
final reviewer passed 144 tests / 1,617 assertions on PHP 8.3, 13 tests / 139
assertions on PHP 8.0 and 16 independent probe checks. Its author passed the
final-head 1,577-unit-test PCOV run / 12,005 assertions, with the unchanged ratchet
at 12,993 / 13,451 executable lines. These are scoped component checks; the
integrated gate below is separate. Review dispositions do not imply GitHub thread
resolution or application compatibility.

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

The independent initial combined code review approved the complete 75-path
optimization diff against trusted baseline `2a0b01ad`, at `b6f24fe`. The later
public-case semantic review still exposed the corrections above. That initial
approval is not substituted for review and verification of the final composition.

PR53 and PR54 were integrated only after both exact heads had independent
approval and all 19 respective Quality jobs succeeded. Their signed merge
commits are `a8f814fcec8b0d7610db7baefe59f5b55367767f` and
`e275f1498b0ea6b4cc7bb97dc253318fb3755bdf`; no merge conflict occurred.
At the latter corrected runtime, Docker PHP 8.5 `composer check` passed:
**1,585 unit tests / 12,133 assertions, 112 integration tests / 6,305 assertions,
two smoke tests / five assertions**, manifests, Wiki checks, both PHPStan
configurations (394 development / 217 production paths) and formatting across
395 files. Both previously failing frozen Laravel fixtures pass unchanged.
The tracked tree stayed clean, and the disposable gate container was removed.
The gate receipt is retained as OS-temp `final-integration-check.log`; subsequent
documentation-only acceptance updates do not change this tested runtime.

The actual Docker harness also passed its two selected controls against this
combined runtime: private source facts persisted across two containers with
identical source inventory/impact, and the full-mode/disabled-cache invalid-input
control produced an honest unknown report with zero Composer starts, measured
phases, immutable input and complete cleanup. These are functional controls, not
public-project timings.

## Measurement boundary

The partial `cohort-v3` series at `b685b5d9486b3d55ada17cec98437f14d5eb045d`
was deliberately stopped between invocations after 25 completed runs. All 25
recorded successful input/cleanup checks, but they are pre-fix diagnostics and
are excluded from final comparison statistics. No receipt was invented for the
unexecuted stopping slot. Raw records and the explicit abort receipt remain in
task-owned temporary storage.

The semantic review found that a package's participation in a failed Composer
problem was sometimes presented as proof that its constraint excluded the target:
Crater's Pest plugin, BookStack's Socialite and Snipe-IT's Google2FA. The last
case instead records a security-advisory policy rejection. It also found that
Lychee's 8,672 and Snipe-IT's 8,284 omitted unique source usages were hidden behind
other source-warning representatives. Passing structural/code checks had not
established the truth or completeness of these user-facing explanations. Both
defects require runtime corrections and independent regression review before
new timings can describe the final implementation.

The separate task-owned controller is approved at SHA-256
`0fd6cd4c1048ef86178b08245e16cc71c9f8edbd59e40bee90789284522938a4`.
Its 34 synthetic controls establish binding/rejection behavior, not performance.
The derived-statistics helper was separately corrected after review exposed
acceptance of self-sealed foreign receipts and omission of source byte-limit
warnings. The approved helper SHA-256 is
`7044c0dd39ea01e8049308d2c9ce553ab9109182c2923a163c9cfd5149b2aca8`.
Its independent 46 synthetic checks exercised the actual controller and report
validators, exact 43-slot membership, source/runtime/request/seed bindings,
positive cleanup, strict numeric admission, both distinct median formulas and
retained source qualifications. No real timing entered those controls.
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
