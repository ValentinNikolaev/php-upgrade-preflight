# PHP performance follow-up

Measurements are complete. This is unreleased work; aggregate checks and review
are recorded on [PR 44](https://github.com/ValentinNikolaev/php-upgrade-preflight/pull/44).

In this Windows/Docker Desktop control, disabling implicit source-fact caching
reduced analyzer time. Across ten projects, median first-condition time fell from **76.1 to
49.3 seconds (35.3%)**; repeat time fell from **55.0 to 48.7 seconds (11.5%)**.
Every matched project/condition improved. Composer process counts, retained source
facts, source limitations and direct/staged statuses stayed the same.

With caching disabled on both sides, the remaining changes reduced the pooled
median by **1.83%**; median paired savings were **1.13%**. Qualified Composer
causes became available earlier on three projects. User planning benefit remains
unmeasured.

The profiles explain why a parser rewrite is not the next priority in this
environment: parsing accounts for about 5–9% of the three complete analyses.
Source discovery, reads, repeated input verification and Composer take much more
time. A high cache hit rate did not make the existing cache faster than parsing
without it.

## What changed for a user

- Persistent source caching is now **off by default**. CLI and Artisan callers can
  opt in with `--source-cache=PATH`; API callers retain explicit cache injection.
  Measure an opt-in on the storage actually used by that installation.
- A supported, high-confidence direct Composer cause can appear on an interactive
  diagnostic stream before the remaining checks finish. It is explicitly
  preliminary and its suggested repair is unverified. Unknown failures and a
  failed attempt superseded by a successful target attempt do not produce this
  message.
- Report evidence validation now avoids repeated copying of a growing reference
  list and repeated scans of the uncertainty text. Eager evidence checks, ordering
  and complete canonical JSON/Markdown remain intact.
- Detailed source and analyzer profiles are opt-in diagnostic APIs. Ordinary
  output remains the bounded human summary; full reports remain explicit options.

PHP `^8.0`, schema `0.8`, source limits, privacy and input-consistency checks are
preserved. The existing cache format and safety checks were not redesigned.

## Measured cache-off effect

This stage compares previous `6fe3249` with cache-off-only P1 `4f979aa`.
Each project/runtime has one first and one repeat observation; role order
alternates by project. All calls use fresh independent Composer caches seeded
with the same frozen historical metadata, network disabled, the same PHP 8.3
image and Windows-backed Docker Desktop storage. First/repeat describes the
previous runtime's explicit external fact-cache lineage, not network or OS cache
temperature. P1 uses its reviewed default without a forced-disable flag.

| Project | Previous first s | P1 first s | Previous repeat s | P1 repeat s |
| --- | ---: | ---: | ---: | ---: |
| Crater | 64.680 | 46.822 | 51.845 | 46.486 |
| BookStack | 51.228 | 36.223 | 39.182 | 35.634 |
| Lychee | 114.594 | 77.325 | 85.681 | 71.854 |
| Snipe-IT | 85.712 | 65.563 | 71.908 | 65.514 |
| Invoice Ninja | 177.748 | 118.060 | 121.656 | 110.279 |
| Akaunting | 69.256 | 44.142 | 53.627 | 44.665 |
| Monica | 60.454 | 39.866 | 44.801 | 39.320 |
| Koel | 43.434 | 30.583 | 32.166 | 28.295 |
| Bagisto | 128.424 | 70.043 | 79.615 | 73.086 |
| Firefly | 82.916 | 51.690 | 56.413 | 50.929 |

The ratio-of-medians savings are **35.262% first / 11.472% repeat**. The median
of the ten individually paired savings is a different quantity: **33.051% /
10.028%**. Composer starts remain **95 per runtime per condition**. There is no
observed matched regression in these 20 comparisons; one pair does not establish
statistical significance or a general speed guarantee.

## Where the time goes

Three separate full analyses at reviewed `701c47c` injected source and analyzer
profiles. These instrumented single observations do not enter the speed medians.

| Project | Full analysis s | Parse/traversal s | Input passes s | Report assembly ms |
| --- | ---: | ---: | ---: | ---: |
| Crater | 44.982 | 2.169 | 11.044 | 80.155 |
| Invoice Ninja | 117.945 | 10.818 | 46.390 | 80.435 |
| Bagisto | 71.760 | 6.061 | 38.872 | 76.828 |

Input passes sum source capture, the recheck after scanning and the final
Composer/source input recheck. Those operations do not overlap each other.
Parsing includes AST traversal and collectors. This table is not a complete
decomposition: discovery, reads, Composer, rule evaluation and other work remain.
The wall-clock buckets do not establish syscall or CPU attribution.

Invoice Ninja's existing broad `report-assembly` phase took 15.325 seconds, of
which the final input recheck took 14.983 seconds. Actual report assembly was
80 milliseconds. Treating that entire phase as report construction would target
the wrong work. The isolated replay of its retained inventory reduced reference
collection from 0.803 to 0.004 seconds with identical ordered references; see
[report internals](report-internals.md). This is a component result, not a claim
of that proportional improvement in complete analysis.

Preliminary Composer finding times from the separate, unprofiled final series
are reported below; diagnostic profiles do not enter that comparison.

## Why the existing cache stays optional

Nine scanner-only calls used independent PHP processes/containers: disabled,
empty and then genuinely warm cache for each project. They use the same
Windows-backed cache placement as the control and preserve the actual parser,
Laravel integration, source selection, caps and retained tuples.

| Project | Disabled s | Empty cache s | Warm cache s | Warm hits / lookups |
| --- | ---: | ---: | ---: | ---: |
| Crater | 5.905 | 21.606 | 9.413 | 647 / 656 |
| Invoice Ninja | 23.216 | 72.940 | 32.953 | 2,398 / 2,506 |
| Bagisto | 17.530 | 71.848 | 32.476 | 1,931 / 1,973 |

The warm cache was slower in all three controls despite those hits. For Invoice
Ninja, cold cache lookup/write operations alone took 37.486 seconds; warm lookups
took 13.336 seconds. Large fact entries still require parsing when they exceed
the existing entry-size bound. Cache misses/rejected slots can include ordinary
key collisions; they are not automatically corruption.

P3 therefore retains the measured cache-off default and explicit opt-in. A new
storage format is not selected in this cycle: the existing format has no measured
advantage over disabled caching here, and a replacement needs a separate bounded
experiment demonstrating a complete-analysis win while retaining its safety
properties. These fixed-order, instrumented single controls do not establish
native-Linux storage performance or a universal caching result.

## Final comparison of the remaining changes

P1 `4f979aa` versus optimized library `701c47c` used both default caches off,
alternating paired replicates and the same phase observer, without detailed
profiles. The predeclared third-pair rule triggered because the absolute
relative cohort-median difference after two pairs was below 10%. A third pair
therefore ran on **all ten projects**, yielding 60 admitted observations.
These descriptive triggers are not significance tests. This series is separate
from the cache-off control above.

Each cell shows median seconds and the observed minimum–maximum across three
calls per runtime/project.

| Project | P1 median (range), s | Optimized median (range), s |
| --- | ---: | ---: |
| Crater | 40.102 (39.977–40.311) | 39.793 (39.664–40.280) |
| BookStack | 31.562 (30.960–32.492) | 30.730 (30.613–31.062) |
| Lychee | 66.157 (65.988–66.206) | 65.101 (65.019–65.404) |
| Snipe-IT | 59.958 (59.913–60.189) | 59.278 (59.136–59.626) |
| Invoice Ninja | 104.104 (103.869–106.375) | 104.107 (103.636–105.382) |
| Akaunting | 42.786 (42.561–42.985) | 41.817 (41.612–41.886) |
| Monica | 35.543 (35.506–36.402) | 35.270 (34.869–35.865) |
| Koel | 27.062 (26.970–27.171) | 26.717 (26.500–26.994) |
| Bagisto | 66.919 (66.508–67.398) | 66.352 (66.267–67.650) |
| Firefly | 48.264 (48.023–48.450) | 47.505 (47.457–47.907) |

Across 30 intervals per runtime, the pooled median fell from **45.504 to 44.671
seconds**, a **1.830%** ratio-of-medians saving. Median paired savings were
**1.129%**. Of the 30 matched pairs, 27 were faster and three slower; all remain
in the result. Invoice Ninja's project median was effectively flat, about
3 milliseconds slower. Composer starts remained **285 per runtime**. Three
replicates do not establish statistical significance or a general speed promise;
the separate stages' percentage savings must not be multiplied.

The optimized runtime emitted an eligible preliminary finding in all three calls
for each project below. The other seven projects emitted none.

| Project | Median first finding, s | Median complete analysis, s |
| --- | ---: | ---: |
| BookStack | 9.616 | 30.730 |
| Lychee | 20.645 | 65.101 |
| Invoice Ninja | 28.585 | 104.107 |

These are events relative to analyzer start, not terminal-paint measurements,
final verdicts or verified repairs. Interactive diagnostic rendering remains
conditional; all remaining checks continue. Final human summaries were
24–39 lines and 2,460–4,958 bytes. Earlier availability is measured; its effect
on a person's upgrade planning is not.

## Limits and next priority

Completed full-analysis controls preserve retained facts, scoped source
qualifications, direct/staged states and input immutability. Scanner-only controls
check retained facts, scanner qualifications and input immutability, without a
dependency verdict. This is narrower than every-field semantic equivalence,
complete source coverage or defect recall. Eight projects
still reach the 10,000-usage inventory cap with explicit omissions. Five projects
remain blocked and five remain operationally unknown because offline metadata
was unavailable; none is evidence of a successful application upgrade.

The next performance investigation should reduce the cost of source path
selection, reads and input verification while still detecting changed bytes,
added/deleted files and changed path boundaries. The current work removes none
of those checks. Parser-only work has a much smaller measured share. The older
30%/50% targets against `2a0b01ad` are not established by a comparison against
the slower `6fe3249`; [the previous result](results.md) remains separate evidence.
R3 participant benefit and product demand remain unmeasured.

## Verification and reproduction

Component PRs 55–60 were independently reviewed and green before integration.
The reviewed integrated library head `701c47c` passed all 22 remote contexts and
local Docker checks: 1,643 unit tests / 12,423 assertions, 112 integration tests /
6,305 assertions, two smoke tests, both PHPStan configurations, formatting,
18 selective mutants and report privacy. PHP 8.3 PCOV passed the unchanged
coverage ratchet at 13,196/13,650 executable lines. The tooling-only exporter
correction at `ee5523c` separately passed all 19 CI jobs before integration; it
protects sealed inputs while allowing fresh sibling exports. The final
comparison is complete; aggregate review and final-head checks are recorded on
[PR 44](https://github.com/ValentinNikolaev/php-upgrade-preflight/pull/44).

The concise [CSV](performance-followup.csv), about 33 kB, contains 100 admitted
observations without detailed profiles: 40 cache-off controls and 60 final
comparisons, with exact heads, conditions and run seals. The three full profiles
and nine scanner-only calls remain separate diagnostics outside Git. Analysis and
summary-ready times are distinct from controller/container envelopes, which also
include explicit full-format rendering and verification. Heap-through-analysis,
analyzer-process VmHWM and individual Composer memory are different scopes.
VmHWM is sampled after explicit full-format rendering; individual Composer
records stay in the raw data. Neither the CSV nor this report claims a concurrent
process-tree peak.

Raw reports, profiles, caches, plans, approvals, ownership and review evidence
stay outside Git under `%TEMP%/preflight-performance-20261017`. Exact reruns need
those frozen metadata seeds and inputs; the CSV alone cannot reconstruct them.
The image is `sha256:c3087cd6c2f9848a65d7442df36dbde51852513f5130848091d13ba87c3cb63b`.
The [staged protocol](../../tools/r4-corpus/PERFORMANCE.md) describes the requests,
runtime/vendor attestation, plan approval, admission, cleanup and export rules.
The user retains the final main-merge decision on PR 44.
