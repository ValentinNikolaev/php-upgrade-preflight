# Frozen corpus results

37 of 37 declared slots produced complete reports: 30 primary, six probes and one supplemental snapshot. Three earlier diagnostic attempts are separate: 40 public-application attempts in total.

Summed external invocation time for the 37 slots: 4103.693 seconds (68.4 minutes), against the declared 30,600-second budget after reserving three diagnostic slots. This excludes controller preparation/cache copying and other work listed in [measurement methods](README.md#what-the-measurements-mean).

A completed report is a collection outcome. It does not mean a feasible upgrade. State pairs below are direct/staged.

| Application | Locked Laravel -> nearest | Nearest cold state | Longest state | First recorded direct blocking package |
| --- | --- | --- | --- | --- |
| crater | v8.83.16 -> 9 | blocked/blocked | blocked/blocked | barryvdh/laravel-dompdf |
| bookstack | v10.48.25 -> 11 | blocked/blocked | blocked/blocked | nunomaduro/collision |
| lychee | v12.36.1 -> 13 | blocked/blocked | blocked/blocked | spatie/laravel-image-optimizer |
| snipeit | v10.48.4 -> 11 | blocked/blocked | blocked/blocked | arietimmerman/laravel-scim-server |
| invoiceninja | v11.11.1 -> 12 | blocked/blocked | blocked/blocked | nunomaduro/collision |
| akaunting | v10.25.2 -> 11 | blocked/blocked | blocked/blocked | akaunting/laravel-firewall |
| monica | v9.52.16 -> 10 | blocked/blocked | blocked/blocked | orchestra/testbench |
| koel | v10.48.8 -> 11 | blocked/blocked | blocked/blocked | nunomaduro/collision |
| bagisto | v10.41.0 -> 11 | blocked/blocked | blocked/blocked | diglactic/laravel-breadcrumbs |
| firefly | v11.41.3 -> 12 | blocked/blocked | blocked/blocked | diglactic/laravel-breadcrumbs |

The three warm unknowns are Git dubious-ownership refusals after host cache copying, not dependency outcomes or successful warm speedups. Their original data remains unchanged. Live network drift also prevents causal cache claims for the other pairs.

## External invocation times and instrumented command starts

Times are seconds. Starts count wrapper invocations, not logical diagnostic entries or successful solves. Unknown warm runs are labeled rather than compared as normal warm measurements.

| Application | Cold seconds | Warm seconds | Longest seconds | Starts cold/warm/longest |
| --- | ---: | ---: | ---: | --- |
| crater | 91.4 | 84.0 | 98.4 | 17/17/19 |
| bookstack | 66.2 | 65.8 | 69.0 | 17/17/19 |
| lychee | 180.3 | 46.5 (unknown) | 177.5 | 14/7/14 |
| snipeit | 155.7 | 45.7 (unknown) | 157.5 | 23/8/25 |
| invoiceninja | 231.1 | 72.8 (unknown) | 258.1 | 18/7/22 |
| akaunting | 115.1 | 112.2 | 117.0 | 23/23/25 |
| monica | 113.3 | 111.7 | 104.6 | 15/15/19 |
| koel | 79.6 | 75.8 | 78.2 | 22/22/24 |
| bagisto | 71.6 | 69.5 | 72.7 | 22/22/24 |
| firefly | 85.5 | 82.6 | 86.0 | 11/11/13 |

## Observed memory and report maxima over each primary triplet

MiB units. Each column is its own maximum across the three runs; these peaks are neither simultaneous nor additive. Composer RSS covers the PHP process only. Missing measurements remain null. Full per-run analysis time, own RSS and process scope are in [metrics.csv](evidence/metrics.csv).

| Application | Analyzer heap MiB | Composer own RSS MiB | Container peak MiB | JSON / Markdown MiB |
| --- | ---: | ---: | ---: | --- |
| crater | 72.6 | 209.1 | 209.4 | 5.6 / 2.9 |
| bookstack | 83.9 | 141.5 | 141.9 | 6.6 / 3.3 |
| lychee | 92.8 | 189.3 | 199.8 | 7.0 / 3.7 |
| snipeit | 88.2 | 228.4 | 231.2 | 6.8 / 3.4 |
| invoiceninja | 201.4 | 256.9 | 261.8 | 6.8 / 3.5 |
| akaunting | 88.2 | 209.0 | 211.3 | 6.8 / 3.4 |
| monica | 90.7 | 265.6 | 270.0 | 7.1 / 3.7 |
| koel | 68.1 | 172.5 | 174.9 | 5.3 / 2.7 |
| bagisto | 20.0 | 193.0 | 195.0 | 0.7 / 0.3 |
| firefly | 91.0 | 158.8 | 161.1 | 7.1 / 3.8 |

Bagisto default selection retained only 598 usages and excluded its 31 Webkul source roots. Its smaller figures do not profile the full application. Other projects often hit the 10,000-usage cap. Source recall remains unknown; see the independent [A](case-review-a.md), [B](case-review-b.md) and [C](case-review-c.md) claim reviews.

All 30 primary JSON reports exceeded the advisory 512 KiB size target, and all 30 Markdown reports exceeded 256 KiB. The smallest were 670,720 and 342,445 bytes respectively, verified from the [artifact index](evidence/reports.json). These are advisory-target exceedances, not failed enforced caps; participant reading cost remains unmeasured.

## Controlled probes

| Probe | Observed | Direct/staged | External seconds | Heap MiB |
| --- | --- | --- | ---: | ---: |
| probe-memory-128 | True | blocked/blocked | 173.4 | 92.8 |
| probe-memory-1024 | True | blocked/blocked | 176.0 | 92.8 |
| probe-short-timeout | True | unknown/unknown | 314.3 | 88.5 |
| probe-network-denied | True | unknown/unknown | 45.4 | 90.6 |
| probe-child-failure | True | unknown/unknown | 43.7 | 88.5 |
| probe-cache-failure | True | unknown/unknown | 44.9 | 90.6 |

Both Lychee memory-limit runs completed. The earlier 128 MiB exhaustion was not reproduced under this pin/PHP 8.3 setup; this is not a general 128 MiB guarantee or a root-cause diagnosis. Timeout, network-denied, child-failure and cache-failure produced operational unknowns and preserved cleanup. Fault shims can stop before ordinary Composer logic executes.

## Supplemental six-hop request

Monica 7 requested Laravel 7 -> 13. Only the first stage was evaluated and blocked; five later stages were skipped. No successful six-hop candidate chain or application transition was observed. Supplemental metrics remain separate in the machine-readable result.

Baseline reports contain four independently recorded attribution/provenance defect families. Repairs are separate code changes and do not rewrite these reports or establish post-repair live performance. R3 participant benefit, voluntary reuse, actual maintenance consumption and Symfony-owner cases remain unavailable.
