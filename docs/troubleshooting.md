# Troubleshooting

## `Unable to find Composer autoload.php`

Run the executable installed by Composer, normally `vendor/bin/upgrade-intel` on Unix or `vendor\\bin\\upgrade-intel.bat` on Windows. If you are using a source checkout, run `composer install` at the repository root first.

## Composer is missing

The analyzer launches `composer` from `PATH`. Confirm that the same shell can run:

```bash
composer --version
```

External analysis needs Composer in the tools environment, not in the target project.

## The target project runs PHP 7.4

Install the CLI in a separate directory running PHP 8.0 or later. Pass `--from-php=7.4` and the target runtime through `--target-php`. Do not install v0.1 into the PHP 7.4 application.

## Laravel rules are unavailable

Install `php-upgrade-preflight/laravel` beside the CLI. An explicit `--framework=laravel` request returns exit code `2` when the adapter is absent.

## The output path is rejected

`--output` must name a file outside the analyzed project, and its parent directory must already exist and be writable. This rule preserves the input tree. Create a sibling report directory and retry.

## Relative path repositories fail

The analyzer rewrites ordinary relative `path` repository URLs against the target project before running Composer. URLs containing environment variables or `~` remain untouched. Use an absolute path or define the required variable in the analyzer process when Composer cannot resolve one of those URLs. Default JSON and Markdown show the resolved local repository root as `[LOCAL_REPOSITORY]`.

## Private packages cannot be downloaded

Composer uses the analyzer process's normal global authentication and environment. Configure scoped credentials in that environment. Captured output redacts repository URLs, authorization values, common tokens, and named credential fields, but you should still use short-lived, read-only credentials and inspect a report before sharing it.

If a credential form is not redacted, stop sharing the report, revoke or rotate the credential, and use the private vulnerability-reporting channel. Include only a synthetic reproduction.

## A scenario times out

Composer scenarios have a five-minute timeout. Check repository availability, authentication prompts, and network access. Composer runs non-interactively. Re-run with `--debug` only when you can safely retain copied manifests for inspection.

## Temporary workspace cleanup fails

In default mode, the report records `cleanup_failure` and only the `[ANALYZER_WORKSPACE]` marker. Re-run with explicit `--debug` only when you need the exact `temp_path` for inspection or manual removal. Debug reports and retained workspaces are non-shareable artifacts, and debug runs preserve workspaces by design. Credentials remain redacted in every mode.

## The command returns `0` for a blocked upgrade

Exit code `0` means the analyzer produced a report. Read `resolution.status` for `feasible`, `feasible_with_changes`, `blocked`, or `unknown` behavior. Exit codes `1` and `2` mean no valid analysis report was produced.

## Windows and Unix reports differ

Default canonical JSON and Markdown use `[PROJECT_ROOT]`, `[REPORT_OUTPUT]`, `[LOCAL_REPOSITORY]`, and `[ANALYZER_WORKSPACE]` instead of host-specific absolute roots. Source file locations remain project-relative. The analyzer still uses native exact project and source paths internally, but report consumers should compare the stable markers and relative locations.
