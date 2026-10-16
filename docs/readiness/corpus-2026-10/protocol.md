# R4 public Laravel application campaign

Protocol fixed on 2026-10-17 before the campaign's analyzer runs. This is an opt-in, networked engineering evaluation, separate from deterministic tests. The baseline is the development tree descended from `0329c5a32adeb10cb48a1e93e81c0249864270cf`, with schema `0.8`. The exact executed tool tree, dependency lock and image identifiers belong in each run's provenance.

## Questions and selection

Can the analyzer produce reviewable upgrade-planning evidence on ten independently maintained applications with bounded, attributable operating cost and unchanged inputs? Which report recommendations are supported, incorrect or unverified?

The preselected applications are Crater, BookStack, Lychee, Snipe-IT, Invoice Ninja, Akaunting, Monica, Koel, Bagisto and Firefly III. Their original committed manifests and locks must be retained. Selection intentionally spans application purposes, locked Laravel 8–12, dependency counts and application layouts. It is a convenience sample, not a representative market sample. Record all intake failures and uncovered shapes. Do not replace an application merely because its solve fails.

`projects.json` records immutable application commits, source links, manifest/lock hashes and requests. Acquire source without running application code, Composer installation, scripts, plugins, tests or migrations. Mount application snapshots read-only during analysis. The tool's dependencies are installed separately from its own lock.

## Predeclared run matrix

Thirty primary top-level analyzer runs:

- For each application: nearest supported major, dedicated initially empty cache (cold).
- For each application: the identical nearest-major request using the preceding run's cache (warm).
- For each application: the longest remaining supported path to Laravel 13, with a separate initially empty cache. Where the nearest target is already 13, record the duplicate request explicitly as a cold repeat, not additional transition coverage.

Six focused probes: matched Lychee analyzer limits of 128 MiB and 1024 MiB with the same request/cache policy; a short Composer timeout; network denied with an empty cache; a controlled child-process failure; and a disposable cache/workspace failure where safely injectable. Record an unsupported probe as unavailable rather than inventing execution. A supplemental real Laravel 7 snapshot may supply the otherwise uncovered six-hop chain; retain its separate identity and count.

"Run" means one analyzer invocation, not one internal scenario. Count internal direct/staged scenarios, diagnostic calls and instrumented Composer invocations separately. Cold means an empty task-owned Composer cache, not cold DNS, network, OS cache or provider infrastructure. Warm live runs may see metadata drift; preserve outcomes and do not claim causal speedup from a pair.

## Measurement and budgets

Use Docker, a fixed PHP/Composer toolchain, a separately declared analyzer PHP memory limit and Composer-child limit, and declared partial extension assumptions. Record actual image ID/digest and tool/harness/dependency fingerprints. Source acquisition, tool installation, Docker startup, hashing and report reading must not be silently included in or excluded from reported times.

Record analyzer wall time, host/container wall time, PHP heap peak, analyzer RSS, child RSS and container peak as distinct metrics with units and collection methods. Missing measurements are null/unavailable, never zero. Record Composer starts, completions and exit statuses, report bytes, direct/staged conclusions, timeouts/unknowns, scratch leftovers and before/after input digests on success and failure. Derive JSON and Markdown from the same report object without a second solve. Preserve fatal stderr and watchdog outcomes even when no valid report exists.

Pilot Lychee, a small application and the largest/longest-path case before broad execution. Calibrate instrumentation with controlled real offline Composer success/failure and termination. Initially allow at most 600 seconds per top-level run, a 2 GiB container limit and nine analysis hours for the campaign; scenario/diagnostic caps are recorded per run. These are evaluation limits, not product guarantees. Execute measured runs serially to reduce contention. Stop and report partial evidence if measurement or safety checks fail; repair the harness before continuing.

## Review and deliverables

Retain raw canonical reports, Markdown projections, resource records and hashes in reviewable artifacts. Publish a concise per-project table plus an issue ledger separating product defects, expected dependency blockers, source-rule uncertainty, harness limitations and external network/security-policy drift. Verify every selected high-impact conclusion against pinned manifests/locks, source or an authoritative guide. Unknown recall and runtime compatibility remain unknown without a suitable oracle and application validation.

Use independent code review for each completed component PR, fix accepted findings, push and verify CI. Integrate reviewed components into one branch and create a final PR against `main`, with another independent review and green required checks. Only the user merges the final PR. No release tag, schema migration or new adapter is part of this campaign.

## R3 boundary

The campaign can produce report-reading exercises, evidence-backed expected answers, installation/execution timings and agent engineering reviews. Label these as engineering observations. No participant comprehension score, normal-workflow comparison, planning-time benefit, demand or voluntary reuse is established by agents running public projects. The real-user R3 gates and maintainer-capacity observations remain open unless actual observations are supplied. No unsolicited outreach is authorized.

## Artifact lifecycle

Task-owned scratch root: the operating-system temporary directory `preflight-r4-20261017`. Track its contents. Delete downloaded archives after verified extraction; retain source/cache/raw results only while needed for review and reproduction, and disclose any retained evidence. Never delete unfamiliar existing diagnostics or `build/` wholesale.
