# PHP optimization results

This is the historical `2a0b01ad` → `6fe3249` series. The subsequent
[performance follow-up](performance-followup.md) records the cache-off control,
detailed profiles and current comparison status. The measurements below are
preserved as that earlier checkpoint's evidence.

The ordinary result is now a short explanation of the blockers, next actions and
remaining uncertainty. **The performance targets were not met** in the controlled
Windows Docker Desktop comparison. First analyses often became slower; repeated
analyses improved unevenly. This change should not be promoted as a general
speed improvement.

The accepted series contains 43 executions on ten pinned public Laravel projects:
40 paired first/repeat measurements and three full-mode controls. Five projects
remained dependency-blocked and five remained unknown because offline Composer
metadata retrieval failed. No application upgrade or runtime compatibility was
demonstrated. The [exact measurements](measurements.csv) occupy about 21 kB;
large raw reports remain local.

## What changed for the reader

- Default summaries are **24–39 lines and 2,460–4,958 bytes**. The same candidate's
  full JSON reports are 5.4–7.6 MB. Full output remains available with
  `--format=json` or `--format=markdown`.
- Direct and staged results, supported dependency causes, evidence references,
  manual work and material omissions remain separate. Suggested package changes
  explicitly remain unverified until an actual candidate solve succeeds.
- Compatible Pest, Socialite and Google2FA constraints are no longer presented
  as proved causes merely because their packages occur in a failed problem.
  Security-advisory rejection and unresolved dependency chains retain their own
  explanations. Usage caps remain visible beside other source warnings.
- Standard analysis starts fewer Composer processes: **128 to 95 per ten runs**,
  for both first and repeat series. It intentionally skips optional alternative
  probes, with visible uncertainty. `--analysis-mode=full` restores those probes;
  requesting a full report format alone does not.

These are unreleased development changes. Automation must explicitly request
JSON. A future publication needs the documented default-migration decision;
package identity, PHP `^8.0` and canonical schema `0.8` are unchanged.

## Measured time and outcomes

Times below are analyzer seconds, before presentation. Each pair is baseline →
candidate. Composer counts apply to each first/repeat run. Direct and staged
states agree within each row and stayed the same between versions.

| Project and Laravel target | First analysis | Repeat analysis | Composer starts | Direct and staged result |
| --- | ---: | ---: | ---: | --- |
| Crater → 9 | 60.40 → 55.99 | 59.57 → 46.01 | 17 → 12 | blocked |
| BookStack → 11 | 40.78 → 46.57 | 41.22 → 35.89 | 17 → 12 | blocked |
| Lychee → 13 | 80.50 → 98.00 | 80.26 → 74.71 | 14 → 12 | blocked |
| Snipe-IT → 11 | 84.19 → 81.19 | 84.10 → 66.86 | 23 → 18 | blocked |
| Invoice Ninja → 12 | 114.36 → 157.48 | 114.57 → 122.59 | 18 → 16 | blocked |
| Akaunting → 11 | 56.24 → 69.77 | 54.91 → 49.84 | 8 → 5 | unknown |
| Monica → 10 | 49.91 → 55.97 | 49.70 → 42.06 | 7 → 5 | unknown |
| Koel → 11 | 40.10 → 43.57 | 39.32 → 32.52 | 9 → 5 | unknown |
| Bagisto → 11 | 31.17 → 124.55 | 31.27 → 77.98 | 8 → 5 | unknown |
| Firefly → 12 | 52.95 → 80.05 | 52.07 → 55.72 | 7 → 5 | unknown |

Across all ten projects, the median first analysis rose **54.60 → 74.91 seconds
(37.2% slower)**. The median repeat analysis fell **53.49 → 52.78 seconds
(1.3% faster)**. These use `1 − median(candidate) / median(baseline)`. Taking the
median of the ten individual paired percentage changes instead gives **18.0%
slower first analyses and 11.1% faster repeats**. The formulas describe different
quantities; neither meets the 30% first / 50% repeat targets.

Unknown runs remain in these declared aggregates. Their times measure incomplete
analysis, not quicker answers about compatibility. In the separate five-project
blocked subset, medians are 80.50 → 81.19 seconds first and 80.26 → 66.86 repeat
(0.9% slower / 16.7% faster). This subset also misses both targets. The offline
unknown fraction is not an estimate of ordinary online failure frequency.

## Actual problems and next actions

| Project | Supported conclusion and next safe action |
| --- | --- |
| Crater | Some Symfony dependency chains remain unresolved without an established incompatible package edge. Review their recorded Composer problems before choosing package changes; Pest is not a proved cause. |
| BookStack | Collision 7.11 conflicts with Laravel 11 and later. Review a compatible development-tool constraint and the separately recorded advisory-policy rejection, then solve an isolated candidate; replacing Socialite is not an evidenced repair. |
| Lychee | Collision 8.8.2 and `spatie/laravel-image-optimizer` 1.8.2 have constraints excluding Laravel 13. Review compatible candidates; middleware guidance remains manual static review. The scan omitted 8,672 unique usages. |
| Snipe-IT | The recorded target solve was rejected by security-advisory policy; Google2FA's range admits Laravel 11. Investigate the actual advisory and candidate versions. Paratest's PHPUnit restriction belongs to its particular staged attempt. The scan omitted 8,284 usages and recorded a parse failure. |
| Invoice Ninja | Collision 8.1.1 excludes Laravel 12. Review that direct constraint and the unresolved final staged chain separately; no proposed repair succeeded. The scan omitted 43,607 usages. |
| Akaunting | Metadata retrieval failed offline. Restore adequate metadata before drawing dependency conclusions; review the recorded source parse failure and 5,352 omitted usages separately. |
| Monica | Metadata retrieval failed offline. Review the pinned old-Illuminate consumer constraints as manual work, then obtain a real solve. Larastan abandonment is advisory. The scan omitted 1,856 usages. |
| Koel | Metadata retrieval failed offline. Review Dotenv Editor and Poddle's old-Illuminate constraints separately. No usage cap was recorded; that does not establish complete source coverage. |
| Bagisto | Metadata retrieval failed offline. The new analysis also discovers module sources; account for that different scope before interpreting timing. The scan omitted 13,932 usages. |
| Firefly | Metadata retrieval failed offline. Review manual package constraints separately; the mapped `domain/` root is absent and 25,860 usages were omitted. |

BookStack also omitted 692 usages. These counts are source observations, not
defects or omitted files. All five unknown cases record exit-100 canceled network
requests; the redacted excerpts do not establish a particular missing endpoint.
An abandoned-package notice never demonstrates a blocking incompatibility.

Independent bounded review checked each candidate-first summary against its
sealed canonical output, actual Composer excerpts and targeted pinned metadata
or source. No material defect was confirmed in those displayed summaries. This
does not certify every full-report finding, source recall or a repaired application.

## Source scope and cache cost

Bagisto's default selection changed substantially: **598 observations in 55
files → 10,000 in 363 files**, including 9,472 observations in module sources.
The cap displaced 70 observations retained by the baseline. More discovered
roots therefore do not guarantee a strict superset of retained facts or complete
module coverage. This is a comparison of ordinary modes with different work,
not a pure parser benchmark.

The other nine baseline/candidate inventories have matching retained-fact hashes.
Candidate first/repeat hashes match on all ten projects. The three full-mode,
source-cache-disabled controls also match their candidate's retained facts and
recorded direct/staged states: Crater 64.62 seconds / 17 Composer starts, Lychee
87.74 / 14 and Bagisto 87.35 / 8. Matching facts and states do not establish
complete semantic equivalence or whole-project coverage.

Three additional Crater controls used standard mode and independent Composer
caches seeded identically. They are exploratory and excluded from primary medians.

| Source-fact storage | Analyzer seconds | Source phase seconds |
| --- | ---: | ---: |
| Disabled | 41.78 | 9.21 |
| Empty Docker local volume | 46.87 | 14.34 |
| Same volume in the next container | 42.68 | 9.51 |

All three started 12 Composer processes and retained the same 7,997 observations
and blocked states. The owned volume's contents, permissions and cold-to-warm
lineage were checked, and the volume was removed. For context, the primary
Windows-backed Crater cache measured 55.99 seconds cold and 46.01 warm. These
single post-hoc controls show no total-time win over disabled caching. They do
not establish a cache hit rate or a general storage-speed guarantee. Source and
temporary-workspace binds remained on Windows; only the fact-cache storage moved.

## Measurement boundaries and remaining work

Baseline: `2a0b01ad6be1e59458a0d2fa6a3ff0aa43738138`. Measured candidate:
`7e482766f07b0c8a5321cae9b06ee84319c489b3`, with the corrected integrated runtime
at `e275f1498b0ea6b4cc7bb97dc253318fb3755bdf`. Later report-only changes do not
alter that runtime. Sources use the [pinned repositories](../readiness/corpus-2026-10/projects.json)
with verified restored snapshots; requests and source/receipt hashes are in the CSV.

The protocol used PHP 8.3, one pinned Docker image, network disabled, identical
historical post-cold Composer metadata seeds, alternating pair order and empty
first-run source caches persisted for repeats. This is not network-cold or
OS-cache-cold measurement. First/repeat runs are different cache conditions,
not independent timing replicates. Original pre-correction and pilot runs are
excluded. Input immutability and positive cleanup passed for all 46 executions.

Summary readiness is analyzer completion plus rendering, **not early delivery of
the first blocker**. Source-phase time includes discovery, reads, hashes, cache
operations and parsing. For example, Invoice Ninja's first source phase took
88.86 of 157.48 analyzer seconds. Cumulative PHP heap through analysis/summary was
34–190.5 MiB in candidate-first runs. Individual Composer PHP process peak RSS
was recorded separately from `/proc/self/status` `VmHWM`; those non-additive
measurements do not establish a simultaneous process-tree memory peak. The
collector subsequently forced both full formats, so container/controller wall
times and shutdown heap are not normal summary-command costs. Blank CSV cells
mean unavailable instrumentation. Full gate/review details and local receipt
identities are in the [engineering record](engineering-review.md).

This checkpoint motivated separating source discovery/read/hash/parser/cache
costs and report assembly, comparing matched source scope, and requiring a
measured win over disabled caching before promoting the cache as an acceleration.
Earlier delivery of evidenced blockers was also left for the follow-up. Existing
correctness, coverage, privacy and mutation checks remain requirements.

The [R3 user comparison](../readiness/decision-study.md) still needs real
participants: smaller output and passing engineering checks do not demonstrate
better decisions, planning-time savings, voluntary reuse or demand. Retain
`VALIDATE_FIRST`, defer Symfony and release expansion, and leave the final
`main` merge to the maintainer. This series missed its performance targets;
subsequent comparisons use another baseline and are documented separately.
