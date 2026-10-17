# R4 CI operating-cost observations

Retrieved through GitHub's authenticated REST API on 2026-10-17 (Europe/Rome), using the latest `main` run returned for each workflow. These are two successful workflow observations at different commits, not repeated benchmarks or a price quote. The public corpus campaign is separate from these deterministic and compatibility checks.

| Observation | Quality | Compatibility smoke |
| --- | --- | --- |
| Run | [38756846359](https://github.com/ValentinNikolaev/php-upgrade-preflight/actions/runs/38756846359) | [38205268082](https://github.com/ValentinNikolaev/php-upgrade-preflight/actions/runs/38205268082) |
| Exact source commit | `0329c5a32adeb10cb48a1e93e81c0249864270cf` | `abf0ffae836a63415e355ce834bc5e2aaf347ab6` |
| Run started, UTC | 2026-10-15 15:02:51 | 2026-10-12 04:48:14 |
| Run updated, UTC | 2026-10-15 15:11:04 | 2026-10-12 04:48:42 |
| Run start to final update, seconds | 493 | 28 |
| Jobs returned / API total | 19 / 19 | 30 / 30 |
| Successful / failed / skipped jobs | 19 / 0 / 0 | 30 / 0 / 0 |
| Sum of job durations, seconds | 3110 | 549 |
| Individual job duration range, seconds | 22–488 | 12–25 |
| Earliest job start to latest job completion, seconds | 489 | 26 |
| Skipped steps | 42 | 26 |

Every job duration is `completed_at - started_at` from the jobs endpoint, including job setup and post steps. The sum counts concurrent jobs separately; it is not elapsed workflow time, billed runner minutes, CPU time, or a sum of test-only steps. Workflow start to final update also includes scheduling and service bookkeeping. Timestamps have one-second precision: a reported zero-second step is below that precision, not proof of zero work. Skipped steps were excluded from measured step totals. No job was skipped; conditional steps account for the skipped-step counts.

| Selected executed steps | Count | Sum, seconds | Individual range, seconds |
| --- | ---: | ---: | ---: |
| Quality: Run test suite | 14 | 2430 | 20–444 |
| Quality: Measure and verify coverage | 1 | 15 | 15–15 |
| Quality: Run selective mutation gate | 1 | 6 | 6–6 |
| Compatibility: Resolve a clean consumer install | 30 | 160 | 1–13 |
| Compatibility: Smoke-test installed package | 30 | 3 | 0–1 |
| Compatibility: Exercise current test tools with the installed analyzer | 4 | 10 | 2–3 |

These rows are selected step categories, not exhaustive decompositions of the job totals. Quality includes Linux PHP 8.0–8.5, Windows PHP 8.3 shards, static analysis, coverage/mutation, an experimental PHP 8.6 job and two staged-budget jobs. Compatibility has 15 install cases at normal and lowest resolution. The exact matrices belong to the executed revisions: [Quality workflow](https://github.com/ValentinNikolaev/php-upgrade-preflight/blob/0329c5a32adeb10cb48a1e93e81c0249864270cf/.github/workflows/quality.yml) and [Compatibility workflow](https://github.com/ValentinNikolaev/php-upgrade-preflight/blob/abf0ffae836a63415e355ce834bc5e2aaf347ab6/.github/workflows/compatibility.yml).

Reproduce acquisition of the primary evidence with `gh api`:

```powershell
gh api 'repos/ValentinNikolaev/php-upgrade-preflight/actions/workflows/quality.yml/runs?branch=main&per_page=1'
gh api 'repos/ValentinNikolaev/php-upgrade-preflight/actions/workflows/compatibility.yml/runs?branch=main&per_page=1'
gh api 'repos/ValentinNikolaev/php-upgrade-preflight/actions/runs/38756846359/jobs?per_page=100'
gh api 'repos/ValentinNikolaev/php-upgrade-preflight/actions/runs/38205268082/jobs?per_page=100'
```

The latest-run queries can change; use the recorded run IDs to reproduce this observation. Compare the returned job count with `total_count`, and paginate if it exceeds 100. Count job and step conclusions independently. Sum durations only at the stated level.

The maintainer stated that **more than five hours per week** are available for library maintenance during this study. That is a stated capacity lower bound, not measured maintenance consumption, a promised allocation, or an upper bound. Actual maintenance time, support incidents, review throughput and a sustainable fourth-package commitment remain unmeasured.

No fourth-package implementation, approved matrix or measured adapter workload exists in this study. Its incremental job count, elapsed time, runner time, review burden and maintenance cost are unknown; multiplying the existing totals by four-thirds would not provide evidence. A defensible range needs a proposed matrix and measured comparable jobs, including release/distribution and adapter-specific work. Actual monetary spend, account allowances, billing multipliers, runner rates and the maintainer's monetary time value are unknown. These observations do not close the R3 or R4 readiness gates on their own.


## Conditional fourth-package accounting - 2026-10-17

The [machine counterfactual](ci-counterfactual.json) binds Compatibility run 38205268082: **30 jobs**, distributed **core 4 / CLI 4 / Laravel 22**, with observed **12-25 seconds per job**. Package shares are unequal; a four-thirds multiplier is not evidence.

| Conditional added install topology | Added jobs | Summed runner-second range using observed extrema |
| --- | ---: | ---: |
| Mirror core/CLI's two cases, each normal/lowest | 4 | 48-100 |
| Illustrative five cases, each normal/lowest | 10 | 120-250 |
| Mirror Laravel's eleven cases, each normal/lowest | 22 | 264-550 |

These are explicit accounting scenarios, not an approved Symfony matrix, confidence intervals, package shares or measured future workloads. The five-case/ten-job example is illustrative and does not equal an existing package's share. OS/PHP/framework cases remain unselected. Shared Quality topology assumes **zero added jobs**, which does **not** make added tests free: the duration increase is unknown. Parallel scheduling/queueing prevents deriving added pipeline wall time from these sums.

Catalog/guide review, fixtures, release/distribution work and a fifth Wiki destination need human upkeep that has not been measured. Monetary spend, rates/allowances and net benefit remain unavailable. The user's >5 hours/week is a stated capacity lower bound, not observed consumption or allocation. No demand/use-case approval exists for a fourth package or fifth Wiki. These conditional ranges preserve VALIDATE_FIRST and do not close R4 or authorize Symfony/schema/release expansion.
