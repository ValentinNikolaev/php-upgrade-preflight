# Opt-in R4 Docker harness

This is explicitly invoked experimental evaluation tooling. Its PHP files participate in normal static analysis and lint; Docker experiments do not run under `composer check`. It never boots the target application. The coordinator owns corpus acquisition, campaigns, interpretation, and published results.

Build the measurement image from the existing development image:

```powershell
docker build -t php-upgrade-preflight-r4:8.3 tools/r4-corpus
python -B tools/r4-corpus/run.py --app C:/corpus/app --output C:/results/new-attempt --target-major 13 --from-php 8.2.0 --target-php 8.3.0 --memory-limit 1024M --composer-memory-limit 1024M --cache C:/corpus-cache/dedicated --network bridge --scenario-timeout 45 --diagnostic-timeout 15 --overall-timeout 540 --repo I:/tool-checkout --image php-upgrade-preflight-r4:8.3
```

The host needs Python 3.9+ and Docker. The selected tool checkout needs its own installed `vendor/autoload.php`. Target, tool checkout, output and cache paths must be separate and non-nested; output must not exist. Do not share the dedicated cache with unrelated work. Cache may persist between attempts; a new empty cache represents a cold-cache attempt. The image runs as `app`, so bind mounts must permit that user to write output, cache, Composer home and scratch. Docker Desktop Windows bind mounts satisfy this in the tested environment; Linux users must provide compatible ownership/ACLs.

`/target`, `/app` and `/harness` are read-only. The runner creates separate writable output, cache, initially empty Composer home, and task-owned scratch mounts. Network is explicitly `bridge` or `none`; `none` also sets Composer's offline environment flag. Composer compatible mode uses the existing product API with scripts/plugins disabled. It declares the same partial extension-presence assumptions as the earlier evaluation helper: bcmath, curl, exif, fileinfo, gd, imagick, mbstring, openssl, pdo, xml, zip. Unlisted extensions still depend on the analyzer runtime. The PHP collector limit (`--memory-limit`) and Composer child limit (`--composer-memory-limit`, default `1024M`) are independent, explicitly set, and recorded. Each container has a 2 GiB cgroup limit. Scenario limits are 1–3600 seconds, diagnostic limits 1–900, and the host container watchdog 1–86400. Host digest/provenance work and bounded Docker cleanup occur outside the container watchdog; an outer campaign deadline needs headroom.

`--github-transport default|public-https` defaults to `default`, which keeps the Composer home empty and retains Composer's normal GitHub transport behavior. The opt-in `public-https` policy writes only `config.github-protocols=["https"]` and `config.use-github-api=false` in the isolated home's `config.json`. It sets `GIT_CONFIG_COUNT=1`, `GIT_CONFIG_KEY_0=url.https://github.com/.insteadOf` and `GIT_CONFIG_VALUE_0=git@github.com:` so SSH-style GitHub URLs use public HTTPS. It supplies no credentials and does not change advisory policy. Configuration is written directly, without an extra Composer configuration invocation. `request.github_transport` and `github_transport` record the policy, exact settings and home digests before/after policy setup; `composer_home_final` records state before cleanup. The collector separately confirms which transport policy and Git override reached its environment. The option changes the transport experiment and must be declared when comparing results. Composer documents these settings under [use-github-api](https://getcomposer.org/doc/06-config.md#use-github-api) and [github-protocols](https://getcomposer.org/doc/06-config.md#github-protocols).

`run.json` is the attempt receipt, with status `completed`, `failed`, or `watchdog_timeout`, input digests and `immutable`, cleanup evidence, provenance, and measurements. Exit 0 means a completed canonical report and verified input immutability; a blocked/unknown canonical report can still be a completed harness attempt. Exit 1 means an incomplete/failed attempt; exit 2 means invalid invocation. Source hashes cover the complete host tree, including vendor and .git if present, empty directories, and symlink text without following links; shape counts include PHP file count/bytes. These hashes run outside analysis timing. Manifest and lock hashes are separate. Git HEAD/dirty state, package/vendor runtime digest, tool lock hash, harness file hashes and the resolved image ID identify executed inputs. Tool source hashes do not prove dependency trust or target runtime compatibility.

`report.json` and `report.md` come from the same `UpgradeReport` returned by one `DefaultUpgradeAnalyzer` with Laravel integration. Each write must complete; collector evidence includes exact byte lengths and SHA-256 hashes. The host requires both nonempty matching artifacts, parseable canonical JSON with required resolution/staged/transition states matching the collector, successful finalization, container exit 0, immutable input, and verified container and scratch cleanup before declaring completion. Partial files remain failed-attempt evidence. Raw Composer arguments, output and host environment values are excluded from instrumentation. Bounded analyzer stderr is sanitized before publication; fatal records retain class/reason and allocation file/line, never a raw exception message. Product reports retain their normal sanitization.

The memory measurements have different scopes:

| Field | Meaning |
| --- | --- |
| `collector.php_heap_peak_bytes` | PHP allocator peak, with real allocation size (`memory_get_peak_usage(true)`) |
| `collector.php_process_peak_rss_kib` | Analyzer PHP process high-water RSS from Linux `/proc/self/status` VmHWM |
| `analyzer_resources.max_rss_kib` | GNU time command-envelope maximum individual process/descendant RSS; not an aggregate and not necessarily analyzer-only |
| `composer.invocations[].resources.max_rss_kib` | Composer PHP PID's VmHWM at shutdown, scope `composer_php_process`; descendant RSS unavailable |
| `container.container_memory_v1_peak_bytes` | Aggregate container v1 cgroup peak only when the controller's sibling limit matches 2 GiB; otherwise null/unavailable |

`collector.analysis_seconds` measures just the analyzer call. `analyzer_resources.elapsed_seconds` includes collector bootstrap and rendering. `container_wall_seconds` includes container startup/finalization. Every Composer invocation has a separate start record, safe command category and applied probe. The direct PHP process writes monotonic elapsed time and its own peak RSS at shutdown. `started_count` and `timed_count` differ when termination prevents shutdown. Composer exit code and termination signal are unavailable in this measurement path and remain null; native process exit status still reaches Symfony unchanged. Interrupted timings remain null. Empty, truncated, invalid and nonfinite start receipts retain an invocation with `start_status=malformed` and unavailable fields, preserving other invocations. GNU time still measures the analyzer envelope, rounding elapsed seconds to hundredths; `0.00` is valid, whereas a missing value stays null.

Resource records distinguish `termination_kind=exited|signalled|unknown` and nullable `termination_signal`. GNU time can print `%x=0` when its child was killed by a signal; a `Command terminated by signal N` record therefore clears `exit_code` and records signal N instead. Incomplete signal diagnostics leave termination unknown, and unterminated metric fields are ignored rather than interpreted as complete measurements. The wrapper and analyzer command use the C locale so signal diagnostics have the same format across hosts.

The wrapper uses `exec php` with a measurement prepend file, so installed Symfony Process owns Composer's PHP PID directly. This avoids an instrumentation parent leaving Composer alive after per-PID timeout/stop signals. It adds no subprocess tree and preserves native Composer execution and exit behavior; it does not add a separate descendant supervisor. Tests reproduce the old GNU-time orphan with installed Symfony timeout, `stop(0)` and ordinary `stop`, then verify direct-PID cancellation, unavailable interrupted metrics and safe next-scenario execution. The host watchdog sends TERM, then KILL when necessary, and removes only its label-verified unique task container. It hashes target input after failures too and inspects persistent scratch only after verified container removal. `container.scratch_leftovers_before_container_exit` describes analyzer scratch, while `cleanup.scratch_before_host_cleanup` includes harness scaffolding. Scratch/home are removed after inspection; a cleanup failure retains the task temp path in the receipt. Output and cache are deliberately retained. Keep failed attempt records as evidence.

Before creating scratch or performing snapshots, provenance or Docker creation, the host atomically persists `ownership.json` in the output directory (the campaign's `raw/ownership.json`). Version 1 records `container_name=preflight-r4-<task_token>`, a 32-hex `task_token` matching Docker label `preflight.r4.task`, the exact absolute OS-temp state path with that same basename, `host_pid`, lifecycle, creation-started/settled flags, and cleanup evidence. Atomic updates cover `prepared`, `creating`, `created`/`create_failed`, `exited`, `cleaning`, `cleaned`, and `unknown` boundaries. An outer controller must terminate and wait for the Python host before recovery, then run this command before inspecting a shared cache:

```powershell
python -B tools/r4-corpus/run.py recover --ownership C:/results/attempt/raw/ownership.json
```

Recovery returns 0 only after proving the owner host exited, inspecting the exact container name and matching label, removing the verified full container ID, positively verifying absence, and removing only the exact generated OS-temp state path without symlink/junction traversal. It persists `cleanup.container_removed=true` and `cleanup.host_scratch_removed=true`. It never kills a host PID or deletes resources by name prefix. A live/reused host PID, unavailable Docker state, ownership mismatch, unsafe path, or absent container with an unsettled create leaves nonzero/unknown cleanup and a retained path. An in-flight create can finish after its Python owner dies; absence alone at that boundary does not authorize cache inspection. Recovery is repeatable once positive evidence is available. Individual Docker operations have 30-second bounds; allow at least 120 seconds for recovery and retain the receipt/log on nonzero exit.

Fault options are `--probe normal|timeout|child-failure|cache-failure`. Timeout and child-failure faults apply to scenario/diagnostic commands, leaving real Composer metadata probes usable. Inspect `composer.invocations[].probe_applied` and outcomes rather than assuming every requested fault was reached. Cache-failure selects a read-only target cache path; Composer may continue with a warning, so this probe does not promise a failed report. `container.cache_fault_requested` and `cache_path_writable` record that boundary.

Run the host tests without Docker:

```powershell
python -B -m unittest discover -s tools/r4-corpus -p test_harness.py -v
```

Opt into offline Docker calibration and end-to-end projection checks:

```powershell
$env:R4_TEST_REPO='I:/tool-checkout'
$env:R4_TEST_IMAGE='php-upgrade-preflight-r4:8.3'
python -B -m unittest discover -s tools/r4-corpus -p test_harness.py -v
```

`--calibrate` runs a separate offline container before analysis. It creates synthetic manifests with Packagist disabled, executes real successful and failed Composer updates through the wrapper, then exercises GNU timeout TERM behavior as a basic control. It verifies three starts, two shutdown resource records, real command success/failure, signal exit 124 and immutable read-only input, prints its JSON result, and removes its dedicated container and scratch. Installed Symfony cancellation is tested separately; GNU timeout is not proof of Symfony's per-PID behavior. Opt-in tests also kill a real runner during setup, after create acceptance and during cleanup, then recover through the public CLI. Calibration does not execute target application code; its output is separate from measured analyzer invocations.
