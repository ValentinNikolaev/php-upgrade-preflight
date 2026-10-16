# R4 public Laravel application campaign

Protocol fixed on 2026-10-17 before the campaign's analyzer runs. This is an opt-in, networked engineering evaluation, separate from deterministic tests. The baseline is the development tree descended from `0329c5a32adeb10cb48a1e93e81c0249864270cf`, with schema `0.8`. The exact executed tool tree, dependency lock and image identifiers belong in each run's provenance.

## Questions and selection

Can the analyzer produce reviewable upgrade-planning evidence on ten independently maintained applications with bounded, attributable operating cost and unchanged inputs? Which report recommendations are supported, incorrect or unverified?

The preselected applications are Crater, BookStack, Lychee, Snipe-IT, Invoice Ninja, Akaunting, Monica, Koel, Bagisto and Firefly III. Their original committed manifests and locks must be retained. Selection intentionally spans application purposes, locked Laravel 8–12, dependency counts and application layouts. It is a convenience sample, not a representative market sample. Record all intake failures and uncovered shapes. Do not replace an application merely because its solve fails.

`projects.json` records immutable application commits, source links, manifest/lock hashes and requests. Acquire source without running application code, Composer installation, scripts, plugins, tests or migrations. Mount application snapshots read-only during analysis. The tool's dependencies are installed separately from its own lock.

Final intake also binds the complete extracted tree with `source_snapshot_sha256` under `r4-tree-v1`, matching the harness's portable path/type/file-content digest. Every source file and directory was compared with a fresh copy of the originally hashed pinned archive before final execution. Lychee's two listed non-source symlinks are omitted explicitly. Windows extraction initially collapsed Koel's `no-name.MP3`/`no-name.mp3` fixtures and Monica 7's `tests/Fixtures`/`tests/fixtures`; those task-owned input directories were reconstructed with case sensitivity before any analysis of those applications. Reproduction must preserve the declared case-sensitive directories or stop with an actionable acquisition error.

## Predeclared run matrix

Thirty primary top-level analyzer runs:

- For each application: nearest supported major, dedicated initially empty cache (cold).
- For each application: the identical nearest-major request using the preceding run's cache (warm).
- For each application: the longest remaining supported path to Laravel 13, with a separate initially empty cache. Where the nearest target is already 13, record the duplicate request explicitly as a cold repeat, not additional transition coverage.

Six focused probes: matched Lychee analyzer limits of 128 MiB and 1024 MiB with the same request/cache policy; a short Composer timeout; network denied with an empty cache; a controlled child-process failure; and a disposable cache/workspace failure where safely injectable. Record an unsupported probe as unavailable rather than inventing execution. A supplemental real Laravel 7 snapshot may supply the otherwise uncovered six-hop chain; retain its separate identity and count.

The supplemental snapshot is Monica `v2.19.1`, Laravel `7.28.1`, pinned in `projects.json`: one independent-cache request from PHP 7.4 to 8.3 and Laravel 13. This makes **37 planned slots**: 30 primary, six probes and one supplemental. A pilot consumes its matching slot only when its final harness, request and cache policy match the frozen campaign; otherwise retain it as an additional pilot attempt. Retries never overwrite artifacts and are counted separately from planned slots. The nine-hour analysis budget includes pilots, fault probes and retries; acquisition, installation, calibration and review are separate activities and must be reported separately when measured.

"Run" means one analyzer invocation, not one internal scenario. Count internal direct/staged scenarios, diagnostic calls and instrumented Composer invocations separately. Cold means an empty task-owned Composer cache, not cold DNS, network, OS cache or provider infrastructure. Warm live runs may see metadata drift; preserve outcomes and do not claim causal speedup from a pair.

## Measurement and budgets

Use Docker, a fixed PHP/Composer toolchain, a separately declared analyzer PHP memory limit and Composer-child limit, and declared partial extension assumptions. Record actual image ID/digest and tool/harness/dependency fingerprints. Source acquisition, tool installation, Docker startup, hashing and report reading must not be silently included in or excluded from reported times.

The first default-policy Lychee pilot returned operational unknowns after GitHub API authentication failure and an SSH fallback in an image without SSH. Preserve it as an extra pilot. Before primary execution, declare `public-https` transport for every primary/probe/supplemental slot: an isolated Composer home with `github-protocols: [https]` and `use-github-api: false`, plus a command-scoped Git rewrite of `git@github.com:` to public HTTPS. No host credentials are supplied; no application or security-advisory policy is changed. These [Composer configuration keys](https://getcomposer.org/doc/06-config.md#use-github-api) change repository access and may change network cost; comparisons with the default-policy pilot are not controlled performance comparisons.

Record analyzer wall time, host/container wall time, PHP heap peak, analyzer RSS, child RSS and container peak as distinct metrics with units and collection methods. Missing measurements are null/unavailable, never zero. Record Composer starts, completions and exit statuses, report bytes, direct/staged conclusions, timeouts/unknowns, scratch leftovers and before/after input digests on success and failure. Derive JSON and Markdown from the same report object without a second solve. Preserve fatal stderr and watchdog outcomes even when no valid report exists.

Pilot Lychee, a small application and the largest/longest-path case before broad execution. Calibrate instrumentation with controlled real offline Composer success/failure and termination. Initially allow at most 600 seconds per top-level run, a 2 GiB container limit and nine analysis hours for the campaign; scenario/diagnostic caps are recorded per run. These are evaluation limits, not product guarantees. Execute measured runs serially to reduce contention. Stop and report partial evidence if measurement or safety checks fail; repair the harness before continuing.

Independent review found that the initial GNU-time Composer wrapper could leave its child alive on a Symfony per-PID timeout, and that the initial controller did not durably own Docker cleanup at its outer deadline. The two early Lychee attempts remain diagnostic pilots, with timeout/resource limitations disclosed; the first also overlapped 6.65 seconds of offline Docker tests. The old controller was stopped after its first receipt at a verified checkpoint with no matching analyzer processes or labeled containers remaining. Do not reuse its plan or cache for the corrected primary series. Recalibrate the repaired cancellation and ownership paths before freezing the final campaign.

## Review and deliverables

Retain raw canonical reports, Markdown projections, resource records and hashes in reviewable artifacts. Publish a concise per-project table plus an issue ledger separating product defects, expected dependency blockers, source-rule uncertainty, harness limitations and external network/security-policy drift. Verify every selected high-impact conclusion against pinned manifests/locks, source or an authoritative guide. Unknown recall and runtime compatibility remain unknown without a suitable oracle and application validation.

Use independent code review for each completed component PR, fix accepted findings, push and verify CI. Integrate reviewed components into one branch and create a final PR against `main`, with another independent review and green required checks. Only the user merges the final PR. No release tag, schema migration or new adapter is part of this campaign.

Bounded acceptance requires a disposition for all 37 slots, valid input/cleanup checks for every executed attempt, independently calibrated resource instrumentation, and an evidence disposition for each selected high-impact recommendation. An unavailable probe or uncompleted six-hop chain keeps the corresponding claim open. Blocked live solves are useful operational evidence but do not demonstrate successful candidate chaining; report successful live transitions and offline calibration separately. An incomplete campaign is a valid published result and must not be relabeled full R4 completion.

## R3 boundary

The campaign can produce report-reading exercises, evidence-backed expected answers, installation/execution timings and agent engineering reviews. Label these as engineering observations. No participant comprehension score, normal-workflow comparison, planning-time benefit, demand or voluntary reuse is established by agents running public projects. The real-user R3 gates and maintainer-capacity observations remain open unless actual observations are supplied. No unsolicited outreach is authorized.

## Artifact lifecycle

Task-owned scratch root: the operating-system temporary directory `preflight-r4-20261017`. Track its contents. Delete downloaded archives after verified extraction; retain source/cache/raw results only while needed for review and reproduction, and disclose any retained evidence. Never delete unfamiliar existing diagnostics or `build/` wholesale.
