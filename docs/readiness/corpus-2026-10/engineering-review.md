# Engineering review of the evaluation tooling

The two component PRs target the integration branch `codex/r4-corpus-20261017`. They do not merge into `main`. Separate read-only reviewers inspected each completed diff, authors repaired accepted findings, and reviewers checked the resulting exact commits. These are engineering reviews, not participant outcomes or empirical proof that an upgrade succeeds.

| Component | First reviewed commit and verdict | Final reviewed commit and verdict |
| --- | --- | --- |
| [PR 42: Docker harness](https://github.com/ValentinNikolaev/php-upgrade-preflight/pull/42) | `d2e1d3cbe7a16e2d47cf6465f3d381773e746fac`, REQUEST_CHANGES | `9eae84916548363de28d4592ee05ec7105bd6c71`, APPROVE |
| [PR 43: acquisition, controller and evidence](https://github.com/ValentinNikolaev/php-upgrade-preflight/pull/43) | `a59e78247028e82d58d0c2c016b976ba5807bc5e`, REQUEST_CHANGES | `089fbebe2b54b1bcbf616fb8564187d781aa261c`, APPROVE |

## Confirmed findings and repairs

PR 42:

- **CR-001, P1:** GNU time changed the PID owned by Symfony and could leave Composer alive after a timeout. The replacement executes Composer's PHP PID directly, observes its own shutdown time/RSS, and leaves unavailable termination/descendant measurements unknown. A real installed-Symfony negative control reproduced the old orphan; timeout, `stop(0)` and ordinary-stop checks passed for the replacement. This does not establish a new general supervisor for Composer's own Git descendants.
- **CR-002, P1:** Docker-only autoload paths and shutdown timestamp inference broke the existing PHPStan gate. Guarded autoload resolution and explicit timing types repaired both PHP 8.0 static configurations and formatting without exclusions or weaker checks.
- **CR-003, P2:** A partial invocation-start file could abort collection. Each start is now retained independently with malformed/unknown fields, preserving other invocations.
- **CR-004, P2:** A successful collector flag could conceal missing or short-written reports. Checked writes, rendering hashes, canonical-state checks and required finalization now reject false completion while retaining partial files as failed evidence.

PR 43:

- **CR-001, P1:** Missing input snapshots and empty canonical JSON could be sealed as completed. Completion now requires populated pinned input evidence, a completed collector, structured canonical sections and consistent states; unavailable resource measurements remain null.
- **CR-002, P1:** Manifest/lock hashes alone did not bind PHP source to intake. The full extracted-tree digest is frozen and checked before attempts, on resume and against both harness snapshots. All eleven real source pins were independently rechecked by the reviewer.
- **CR-003, P2:** A failed warm run could mutate its predecessor's cache and poison retries. Every warm attempt now receives a verified independent copy of the unchanged cold cache, with preflight checks preceding attempt allocation.
- **CR-004, P1:** Killing the host controller child could leave daemon-managed Docker work alive. Atomic ownership is persisted before setup, and bounded recovery proves host exit, exact token/label/container identity, safe temporary-state containment and completed cleanup before inspecting caches. Real offline killed-host recovery was exercised at setup, accepted create, cleanup and calibration boundaries. Uncertain ownership is refused.
- **CR-005, P2:** The summary mislabeled Composer's own VmHWM as GNU-time data. The final wording preserves declared per-invocation scopes, treats absent/legacy scopes as unknown and leaves numeric/null values unchanged. The new projection regression failed against the previous wording and passed after repair.

## Observed verification and limits

The harness author observed 26 passing targeted host/offline-Docker checks and both PHP 8.0 PHPStan configurations plus lint. Its independent reviewer additionally observed sixteen host checks, four targeted Docker checks, and exact-head CI static-analysis/lint success. The evidence author observed 34 host tests: 33 passed and one Windows symlink-creation test was skipped because the host lacked that privilege. The final reviewer repeated the two affected projection tests and four negative-control scope variants; the preceding full re-review covered the remaining fixes.

No review booted a target application, ran its tests, executed its Composer scripts/plugins, contacted users, or altered public report schema `0.8`. Docker calibration uses controlled synthetic inputs; it is not evidence of successful application upgrades. The [campaign protocol](protocol.md) and [independent source oracle](manual-oracle.md) define the separate empirical work.

The final aggregate PR requires another independent review and CI verification at its final head. Component approvals alone do not establish that final integration has passed.
