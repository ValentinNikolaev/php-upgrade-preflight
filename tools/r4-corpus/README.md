# Opt-in R4 Docker harness

This is experimental evaluation tooling, outside `composer check` and all deterministic gates. It never boots the target application. The coordinator owns corpus acquisition, campaigns, interpretation, and published results.

Build the measurement image from the existing development image:

```powershell
docker build -t php-upgrade-preflight-r4:8.3 tools/r4-corpus
python -B tools/r4-corpus/run.py --app C:/corpus/app --output C:/results/new-attempt --target-major 13 --from-php 8.2.0 --target-php 8.3.0 --memory-limit 1024M --composer-memory-limit 1024M --cache C:/corpus-cache/dedicated --network bridge --scenario-timeout 45 --diagnostic-timeout 15 --overall-timeout 540 --repo I:/tool-checkout --image php-upgrade-preflight-r4:8.3
```

The host needs Python 3.9+ and Docker. The selected tool checkout needs its own installed `vendor/autoload.php`. Target, tool checkout, output and cache paths must be separate and non-nested; output must not exist. Do not share the dedicated cache with unrelated work. Cache may persist between attempts; a new empty cache represents a cold-cache attempt. The image runs as `app`, so bind mounts must permit that user to write output, cache, Composer home and scratch. Docker Desktop Windows bind mounts satisfy this in the tested environment; Linux users must provide compatible ownership/ACLs.

`/target`, `/app` and `/harness` are read-only. The runner creates separate writable output, cache, initially empty Composer home, and task-owned scratch mounts. Network is explicitly `bridge` or `none`; `none` also sets Composer's offline environment flag. Composer compatible mode uses the existing product API with scripts/plugins disabled. It declares the same partial extension-presence assumptions as the earlier evaluation helper: bcmath, curl, exif, fileinfo, gd, imagick, mbstring, openssl, pdo, xml, zip. Unlisted extensions still depend on the analyzer runtime. The PHP collector limit (`--memory-limit`) and Composer child limit (`--composer-memory-limit`, default `1024M`) are independent, explicitly set, and recorded. Each container has a 2 GiB cgroup limit. Scenario limits are 1–3600 seconds, diagnostic limits 1–900, and the host container watchdog 1–86400. Host digest/provenance work and bounded Docker cleanup occur outside the container watchdog; an outer campaign deadline needs headroom.

`--github-transport default|public-https` defaults to `default`, which keeps the Composer home empty and retains Composer's normal GitHub transport behavior. The opt-in `public-https` policy writes only `config.github-protocols=["https"]` and `config.use-github-api=false` in the isolated home's `config.json`. It sets `GIT_CONFIG_COUNT=1`, `GIT_CONFIG_KEY_0=url.https://github.com/.insteadOf` and `GIT_CONFIG_VALUE_0=git@github.com:` so SSH-style GitHub URLs use public HTTPS. It supplies no credentials and does not change advisory policy. Configuration is written directly, without an extra Composer configuration invocation. `request.github_transport` and `github_transport` record the policy, exact settings and home digests before/after policy setup; `composer_home_final` records state before cleanup. The collector separately confirms which transport policy and Git override reached its environment. The option changes the transport experiment and must be declared when comparing results. Composer documents these settings under [use-github-api](https://getcomposer.org/doc/06-config.md#use-github-api) and [github-protocols](https://getcomposer.org/doc/06-config.md#github-protocols).

`run.json` is the attempt receipt, with status `completed`, `failed`, or `watchdog_timeout`, input digests and `immutable`, cleanup evidence, provenance, and measurements. Exit 0 means a completed canonical report and verified input immutability; a blocked/unknown canonical report can still be a completed harness attempt. Exit 1 means an incomplete/failed attempt; exit 2 means invalid invocation. Source hashes cover the complete host tree, including vendor and .git if present, empty directories, and symlink text without following links; shape counts include PHP file count/bytes. These hashes run outside analysis timing. Manifest and lock hashes are separate. Git HEAD/dirty state, package/vendor runtime digest, tool lock hash, harness file hashes and the resolved image ID identify executed inputs. Tool source hashes do not prove dependency trust or target runtime compatibility.

`report.json` and `report.md` come from the same `UpgradeReport` returned by one `DefaultUpgradeAnalyzer` with Laravel integration. Raw Composer arguments, output and host environment values are excluded from instrumentation. Bounded analyzer stderr is sanitized before publication; fatal records retain class/reason and allocation file/line, never a raw exception message. Product reports retain their normal sanitization.

The memory measurements have different scopes:

| Field | Meaning |
| --- | --- |
| `collector.php_heap_peak_bytes` | PHP allocator peak, with real allocation size (`memory_get_peak_usage(true)`) |
| `collector.php_process_peak_rss_kib` | Analyzer PHP process high-water RSS from Linux `/proc/self/status` VmHWM |
| `analyzer_resources.max_rss_kib` | GNU time command-envelope maximum individual process/descendant RSS; not an aggregate and not necessarily analyzer-only |
| `composer.invocations[].resources.max_rss_kib` | Per-Composer GNU time maximum individual process/descendant RSS |
| `container.container_memory_v1_peak_bytes` | Aggregate container v1 cgroup peak only when the controller's sibling limit matches 2 GiB; otherwise null/unavailable |

`collector.analysis_seconds` measures just the analyzer call. `analyzer_resources.elapsed_seconds` includes collector bootstrap and rendering. `container_wall_seconds` includes container startup/finalization. Every Composer invocation has a separate start record, safe command category, applied probe and GNU time record. `started_count` and `timed_count` differ when termination prevents GNU time from writing results. Unknown measurements remain null, including own-process RSS when PHP never reaches shutdown. GNU time formats round elapsed seconds to hundredths; an observed `0.00` is valid, whereas a missing value stays null.

Resource records distinguish `termination_kind=exited|signalled|unknown` and nullable `termination_signal`. GNU time can print `%x=0` when its child was killed by a signal; a `Command terminated by signal N` record therefore clears `exit_code` and records signal N instead. Incomplete signal diagnostics leave termination unknown, and unterminated metric fields are ignored rather than interpreted as complete measurements. The wrapper and analyzer command use the C locale so signal diagnostics have the same format across hosts.

The wrapper uses `exec /usr/bin/time` and preserves child exits/signals. The host watchdog sends TERM, then KILL when necessary, and removes only its unique task container. It hashes target input after failures too and inspects persistent scratch before cleanup. `container.scratch_leftovers_before_container_exit` describes analyzer scratch, while `cleanup.scratch_before_host_cleanup` includes the harness scaffolding. Scratch/home are removed after inspection; a cleanup failure retains the task temp path in the receipt for investigation. Output and cache are deliberately retained. Keep failed attempt records as evidence.

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

`--calibrate` runs a separate offline container before analysis. It creates synthetic manifests with Packagist disabled, executes real successful and failed Composer updates through the wrapper, then exercises TERM timeout behavior. It verifies three starts, real exit/resource records, signal exit 124 and immutable read-only input, prints its JSON result, and removes its dedicated container and scratch. It does not execute target application code. Calibration output is separate from measured analyzer invocations; save stdout if a durable calibration artifact is required.
