# Five-minute offline demo

This demo shows a real Laravel 10 to 13 preflight without contacting Packagist or changing the target project. The target uses small local Composer metadata models from path repositories:

- `laravel/framework` 10.0.0 through 13.0.0 model the covered framework majors;
- `nunomaduro/collision` 7.11.0 and PHPUnit 10 simultaneously block Laravel 11, so the report can retain one blocker that resolves after the intermediate root remediation while another persists until the bounded all-dependencies attempt;
- Laravel 11 and 12 metadata provide real successful middle-hop locks, while Laravel 13 requires a deliberately absent `ext-preflight-stage` platform package and stops the chain;
- locked metadata for Carbon 2 and Tinker 2 exercises stage-specific package guidance across the three adjacent hops;
- `target/tests/Feature/LegacyCsrfTest.php` contains a direct `VerifyCsrfToken` reference covered by the Laravel 12 to 13 source rule.

The packages are deliberately metadata-only. This is a deterministic Composer-solver demonstration, not a Packagist smoke test and not proof that an upgraded Laravel application will run. The schema 0.8 development report keeps the direct Laravel 13 solve separate from a sequential 10→11→12→13 analysis. Each reported feasible stage has its own Composer evidence, and only the selected candidate manifest and lock feed the next stage.

## Run it

Install the CLI and Laravel adapter as described in the root README, then run this command from the tools directory. Replace `/path/to/php-upgrade-preflight` with this repository checkout and keep the output outside `target`:

```bash
COMPOSER_ROOT_VERSION=1.0.0 vendor/bin/upgrade-intel analyze --path=/path/to/php-upgrade-preflight/examples/five-minute-demo/target --from-php=8.1 --target=laravel/framework:^13.0 --target-php=8.3 --without-extension=ext-preflight-stage --composer-mode=restricted --framework=laravel --format=json --output=/tmp/php-upgrade-preflight-demo.json
```

The process exits successfully after writing a valid report even though `resolution.status` is `blocked`. Read the status from the JSON rather than from the process exit code.

## Expected result

The checked-in [JSON report](reports/laravel-10-to-13.json) is canonical. The [Markdown report](reports/laravel-10-to-13.md) is its human-readable projection: both committed files are rendered from one analyzer run by [`regenerate-reports.php`](regenerate-reports.php), so they agree on every value, including measured durations. Because that single run analyzes the canonical JSON request, the committed Markdown records `Requested format: json`; running the command below with `--format=markdown` reports `markdown` there instead, and nothing else in the request block changes. Both were generated in restricted mode with analyzer-owned Composer configuration and best-effort offline behavior; neither contains debug output. To regenerate them after changing the demo fixtures, run `php examples/five-minute-demo/regenerate-reports.php` from a repository checkout with development dependencies installed. Restricted mode is not an OS network sandbox.

Rerunning the command reproduces the same findings and the same candidate-state fingerprints from any directory and on any supported host, because a state fingerprint identifies manifest and lock content rather than the path it was analyzed in. Composer writes its own version into the locks it produces, so a different Composer version is a different candidate state. Measured durations and the raw `candidate_lock.sha256` and `candidate_lock.content_hash` evidence are local to the workspace Composer wrote in and differ between runs.

The useful result is:

- request: model Laravel 10 on PHP 8.1 moving to Laravel 13 on PHP 8.3;
- direct final-target resolution: `blocked`;
- staged resolution: `blocked`, independently of direct resolution and framework guidance;
- Laravel 10→11: two simultaneous blockers are retained; the Collision blocker resolves after attempt 2, PHPUnit persists, and attempt 3 selects the successful Laravel 11 candidate;
- Laravel 11→12: Composer-feasible from the selected Laravel 11 manifest and lock;
- Laravel 12→13: blocked after all three attempts by the absent `ext-preflight-stage`, with no output state selected;
- framework guidance: `supported` for 10→11, 11→12, and 12→13;
- package findings: review PHPUnit 10 for every hop, Carbon 2 for 11→12, and Tinker 2 for 12→13;
- source finding: replace the direct `VerifyCsrfToken` reference with `PreventRequestForgery` for 12→13;
- next manual step: provide a real target-platform decision for the missing extension, apply the reported package and source changes in a branch, rerun the full stage, and then run the application's own tests on PHP 8.3.

The report does not perform the upgrade, execute an application, prove runtime compatibility, or establish production readiness. Extension values not explicitly modeled still come from the analyzer host.

## Verify target immutability

For an auditable check, compute a recursive digest before and after the analysis. This is the same path-and-content construction used by the release gate:

```bash
tree_digest() {
  (
    cd "$1"
    find . -type f -print0 | LC_ALL=C sort -z | xargs -0 sha256sum
  ) | sha256sum | cut -d ' ' -f 1
}

target=/path/to/php-upgrade-preflight/examples/five-minute-demo/target
before="$(tree_digest "$target")"
COMPOSER_ROOT_VERSION=1.0.0 vendor/bin/upgrade-intel analyze --path="$target" --from-php=8.1 --target=laravel/framework:^13.0 --target-php=8.3 --without-extension=ext-preflight-stage --composer-mode=restricted --framework=laravel --format=json --output=/tmp/php-upgrade-preflight-demo.json
after="$(tree_digest "$target")"
test "$before" = "$after"
printf 'Target SHA-256 before: %s\nTarget SHA-256 after:  %s\n' "$before" "$after"
```

Do not use `--debug` for a report that will be shared. Debug mode retains workspaces and exact temporary paths.

## Record the terminal GIFs

Four checked-in VHS tapes run the real analyzer in an offline PHP container. Each records a different aspect of one analysis, and each writes its GIF next to the tape:

| Tape | GIF | What it records |
|---|---|---|
| `laravel-10-to-13.tape` | `laravel-10-to-13.gif` | The full staged run through `run-demo.sh`, whose summary lives in [`summarize-report.php`](summarize-report.php) and refuses to present the result unless its stable stage, state-chain, source-impact-reference, blocker-lifecycle, and resolution projection matches the checked-in canonical JSON. It records the adjacent-stage outcomes, blocker lifecycles, original-source finding, and matching target digests. |
| `blocker-deep-dive.tape` | `blocker-deep-dive.gif` | A `jq` walk over the generated JSON: resolution statuses, the retained Collision blocker with its dependency path and resolution options, and the evidence entry that blocker links to, with the exact Composer command and the solver's output excerpt. |
| `immutability-proof.tape` | `immutability-proof.gif` | `immutability-proof.sh`: a recursive SHA-256 digest of the target before the analysis, the analysis itself, and the identical digest afterwards. |
| `markdown-report.tape` | `markdown-report.gif` | The Markdown projection: its section list, risk and effort with assumptions, and the staged actions for the blocked 12→13 stage. |

Every tape removes its temporary report automatically. The `blocker-deep-dive` and `markdown-report` tapes select report data by stable keys — package name, evidence link, stage id, section anchor — rather than by array position or line offset, so a fixture or schema change surfaces as visibly different content instead of a silently empty frame.

From the repository root in PowerShell:

```powershell
docker build --file examples/five-minute-demo/Dockerfile.vhs --tag php-upgrade-preflight-vhs:0.11.0 .
docker run --rm --volume "${PWD}:/app" --volume "${PWD}/examples/five-minute-demo:/vhs" php-upgrade-preflight-vhs:0.11.0 laravel-10-to-13.tape
```

Pass a different tape name as the last argument to record the others. Rebuild the image after changing `Dockerfile.vhs`: the recording environment also provides `jq` and `bat`, which the deep-dive and Markdown tapes require.

Each GIF is reproducible from its tape, and these four files are the only tracked copies. The [landing page](../../site/index.html) serves them from `site/assets/`, where its deploy workflow copies them; local copies under `site/assets/` are git-ignored, so run `cp examples/five-minute-demo/*.gif site/assets/` to preview the page after re-recording. The first build downloads the pinned VHS and Composer images plus the PHP packages used by the recording environment.
