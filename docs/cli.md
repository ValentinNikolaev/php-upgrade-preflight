# CLI reference

The standalone command accepts one subcommand and `--name=value` options:

```text
upgrade-intel analyze --target=package:constraint [options]
```

## Options

| Option | Meaning |
| --- | --- |
| `--path=PATH` | Project directory. Defaults to the current directory. |
| `--target=PACKAGE:CONSTRAINT` | Requested package constraint. Repeat for multiple packages. |
| `--target-php=VERSION` | Exact target PHP platform version. |
| `--from-php=VERSION` | Known current PHP version used for staging analysis. |
| `--with-extension=EXT[:VERSION]` | Assume `ext-name` is present, optionally at an exact version. Repeat as needed. |
| `--without-extension=EXT` | Assume `ext-name` is absent. Repeat as needed. |
| `--source=PATH` | File or directory to scan inside the project. Repeat as needed. |
| `--framework=NAME` | Installed framework adapter to enable. Repeat as needed. |
| `--format=json\|markdown` | Report format. Defaults to `json`. |
| `--output=PATH` | Report file outside the analyzed project. Defaults to stdout. |
| `--debug` | Preserve Composer workspaces and expose exact `temp_path` values; output is non-shareable. |
| `-h`, `--help` | Print command help. |

Supply at least one package target or `--target-php`. `--target=php:8.1` and `--target-php=8.1` are equivalent; if you use both, they must normalize to the same exact PHP version.

The parser accepts only the documented forms. Write `--path=value`, not `--path value`. The `--debug` flag takes no value.

By default, canonical JSON and Markdown replace absolute local roots with `[PROJECT_ROOT]`, `[REPORT_OUTPUT]`, `[LOCAL_REPOSITORY]`, and `[ANALYZER_WORKSPACE]`. The analyzer still uses exact project and source paths internally, while reported source files remain project-relative. A cleanup failure reports only `[ANALYZER_WORKSPACE]` unless `--debug` was supplied. Explicit debug mode preserves workspaces and exposes exact `temp_path` values, so debug reports and retained workspaces are non-shareable. Credential redaction remains active in every mode.

Extension names use Composer's `ext-name` form. An extension may appear only once across `--with-extension` and `--without-extension`; repeated or contradictory assumptions are rejected. Exact versions and absences are written only to analyzer-owned temporary Composer manifests. Absence simulation requires Composer 2.2 or newer; older detected versions stop the affected target scenarios before a workspace is created and leave resolution unknown. Presence without a version uses a conservative temporary presence sentinel. A constraint failure involving that sentinel is reported as the non-blocking `extension-version-unknown` advisory, not as reproducible evidence that the extension is missing. Unlisted extensions still come from the analyzer runtime and are labeled as host-dependent in `platform.extensions` and `uncertainties`.

## Framework selection

The CLI discovers installed adapters from their `extra.php-upgrade-preflight.framework-adapters` Composer metadata. Without `--framework`, each discovered adapter performs automatic target detection. Laravel continues to detect `laravel/framework` or `illuminate/*` requirements and lock entries. Use `--framework=laravel` to request Laravel analysis explicitly and bypass detection.

Explicit names are case-insensitive. An explicit request fails with exit code `2` when no installed adapter has that name. Invalid metadata and adapter name or class collisions fail the analysis rather than selecting a winner. The complete registration contract and deterministic ordering rules are documented in [Framework adapters](adapters.md).

## Streams and exit codes

Reports go to stdout unless `--output` is set. Diagnostics go to stderr.

| Code | Meaning |
| --- | --- |
| `0` | Help or a completed canonical report. Inspect `resolution.status`. |
| `1` | An internal or operational failure prevented report production. |
| `2` | Invalid command syntax, paths, targets, format, framework, or output destination. |

A solver-blocked upgrade is valid analysis output and returns `0`.

## Examples

PHP-only analysis:

```bash
upgrade-intel analyze --path=/work/app --from-php=7.4 --target-php=8.1
```

Target extension assumptions:

```bash
upgrade-intel analyze \
  --path=/work/app \
  --target-php=8.2 \
  --with-extension=ext-intl:72.1 \
  --with-extension=ext-json \
  --without-extension=ext-xdebug
```

Multiple package targets and Markdown output:

```bash
upgrade-intel analyze \
  --path=/work/app \
  --target=laravel/framework:^9.0 \
  --target=laravel/passport:^10.0 \
  --target-php=8.1 \
  --format=markdown \
  --output=/work/reports/app.md
```
