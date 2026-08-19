# CLI Package Internals

`php-upgrade-preflight/cli` provides the framework-neutral `upgrade-intel` executable. It owns command syntax, adapter discovery, request construction, and output delivery. Core owns analysis.

## Entry point and install layouts

Composer exposes `bin/upgrade-intel`. The script looks for an autoloader in this order:

1. Composer's `_composer_autoload_path` global, when supplied;
2. the monorepo root `vendor/autoload.php` layout;
3. a nearby `autoload.php` layout;
4. the package-local `vendor/autoload.php` layout.

If none exists, it prints `Unable to find Composer autoload.php.` to standard error and exits `1`.

## Command shape

```bash
vendor/bin/upgrade-intel analyze --target=vendor/package:^2.0 [options]
vendor/bin/upgrade-intel analyze --target-platform-profile=platform.json [options]
```

The literal `analyze` command is required. `-h` and `--help` are recognized before ordinary parsing.

## Complete option reference

`CommandLineOptions::all()` is the single source for accepted option names, parse modes, defaults, and help text.

| Option | Repeatable | Default | Meaning |
| --- | --- | --- | --- |
| `--path=PATH` | No | Current directory | Project root to analyze |
| `--target=PACKAGE:VALUE` | Yes | Empty | Composer package target; `php:VERSION` is normalized specially |
| `--target-php=VERSION` | No | None | Exact simulated PHP value |
| `--target-platform-profile=PATH` | No | None | JSON target platform profile |
| `--from-php=VALUE` | No | None | Exact current PHP evidence for staged reasoning |
| `--with-extension=EXT[:VERSION]` | Yes | Empty | Assume an extension is present, optionally at a version |
| `--without-extension=EXT` | Yes | Empty | Assume an extension is absent |
| `--source=PATH` | Yes | Empty | Add a project-contained source path |
| `--framework=NAME` | Yes | Empty | Explicitly activate an installed adapter by name |
| `--format=json\|markdown` | No | `json` | Report writer |
| `--output=PATH` | No | Standard output | Write the rendered report to a validated destination |
| `--composer-mode=MODE` | No | `compatible` | `compatible` or `restricted` |
| `--composer-executable=PATH` | No | `composer` | Composer command or executable path |
| `--composer-version=RANGE` | No | `>=2.0.0 <3.0.0` | Expected Composer version constraint |
| `--composer-timeout=SEC` | No | `300` | Scenario timeout, validated by the model from 1 through 3600 |
| `--composer-diagnostic-timeout=SEC` | No | `60` | Diagnostic timeout, validated by the model from 1 through 900 |
| `--debug` | No | Off | Preserve temporary Composer workspaces and expose debug paths |
| `-h`, `--help` | No | — | Print help |

At least one package target, target PHP, or target-platform profile is required.

## Parser behavior

The parser accepts long values only as `--name=value`; it does not consume a following token as the value.

Correct:

```bash
vendor/bin/upgrade-intel analyze --target=phpunit/phpunit:^12.0
```

Rejected:

```bash
vendor/bin/upgrade-intel analyze --target phpunit/phpunit:^12.0
```

Single-valued options and flags are rejected when repeated. List options and extension assumptions are intentionally repeatable.

```bash
vendor/bin/upgrade-intel analyze \
  --target=laravel/framework:^13.0 \
  --target=laravel/passport:^13.0 \
  --source=app \
  --source=packages/Billing/src
```

Unknown options produce the generic `Unknown option.` diagnostic. A flag with a value, such as `--debug=true`, is rejected because `--debug` is valueless.

## Request construction

`AnalyzeCommand` converts parsed strings to:

- `UpgradeTarget` values;
- an optional `TargetPlatformProfile` loaded from JSON;
- `ExtensionAssumption` values;
- `ComposerExecutionConfiguration`;
- one validated `UpgradeRequest`.

The command validates an output destination before analysis starts. This avoids spending time on Composer scenarios only to discover that the requested report path is invalid.

## Exit codes and report status

| Exit code | CLI meaning |
| --- | --- |
| `0` | A valid report was rendered or written, or help was printed |
| `1` | Analysis or delivery failed unexpectedly |
| `2` | Invocation was invalid |

A valid report whose resolution is `blocked` or `unknown` still exits `0`. Consumers must inspect the JSON report status instead of translating the process exit code into upgrade readiness.

Example CI pattern:

```bash
vendor/bin/upgrade-intel analyze \
  --path=. \
  --target=laravel/framework:^12.0 \
  --target-php=8.3 \
  --format=json \
  --output=build/upgrade-report.json

# Next, validate and inspect metadata.schema_version and resolution.status.
```

## Adapter discovery

`FrameworkIntegrationRegistry` enumerates installed Composer packages and asks `AdapterManifestReader` to inspect each package's `composer.json`.

An adapter package advertises classes like this:

```json
{
  "extra": {
    "php-upgrade-preflight": {
      "framework-adapters": [
        "Vendor\\UpgradeAdapter\\FrameworkIntegration"
      ]
    }
  }
}
```

The advertised value must be a non-empty JSON list. Every item must be a non-empty, trimmed class name. The registry also requires each loaded class to:

- exist and be instantiable;
- have no required constructor parameters;
- implement `FrameworkIntegration`;
- return a non-empty, trimmed integration name;
- avoid duplicate class and case-insensitive integration-name registrations.

Healthy integrations are sorted case-insensitively by name, then by class name, giving stable discovery order.

## Broken optional adapter behavior

Discovery is isolated per installed package. If one package has unreadable or invalid adapter metadata, the registry skips that package, keeps other integrations, and exposes a diagnostic on standard error.

This behavior differs for an explicit request:

```bash
vendor/bin/upgrade-intel analyze \
  --target=laravel/framework:^12.0 \
  --framework=laravel
```

If `laravel` is unavailable, the command fails validation. When adapter packages were skipped, the error names them and includes their manifest-read reasons so the missing adapter is diagnosable.

With no `--framework`, Core may activate installed adapters through project detection. With `--framework=name`, the requested named integration must be installed and available.

## Diagnostics and sensitive output

All command exceptions are written to standard error after `SensitiveOutputRedactor::redact()`. Reports go to standard output unless `--output` is supplied.

Operational messages use path-exposure policy. For example, a successful file write prints a safe path marker when the real path should not be exposed.

`--debug` is deliberately different: it preserves temporary workspaces for investigation. Those workspaces can contain copied Composer metadata and should be handled as potentially sensitive.

## Common examples

### PHP-only target

```bash
vendor/bin/upgrade-intel analyze --path=. --target-php=8.3
```

### Generic package upgrade with current and target PHP

```bash
vendor/bin/upgrade-intel analyze \
  --path=. \
  --target=symfony/console:^7.0 \
  --from-php=8.1 \
  --target-php=8.2 \
  --format=markdown
```

### Explicit extension assumptions

```bash
vendor/bin/upgrade-intel analyze \
  --target=laravel/framework:^11.0 \
  --target-php=8.2 \
  --with-extension=curl:8.2.0 \
  --without-extension=imagick
```

### Restricted Composer environment

```bash
vendor/bin/upgrade-intel analyze \
  --target=vendor/package:^2.0 \
  --composer-mode=restricted \
  --composer-timeout=120 \
  --composer-diagnostic-timeout=30
```

Restricted mode can prevent network-backed resolution when artifacts are not already available. Treat that as an environment/evidence limitation, not automatically as a package blocker.

## Class reference

| Class | Responsibility |
| --- | --- |
| `CommandLineOption` | Immutable definition of one option |
| `CommandLineOptions` | Complete option vocabulary, defaults, modes, and help rendering |
| `CommandLineParser` | Shell-string validation and normalized option array |
| `AnalyzeCommand` | Request construction, delegation, rendering, exit codes |
| `AdapterManifestReader` | Strictly read one package's adapter metadata |
| `FrameworkIntegrationRegistry` | Enumerate, instantiate, sort, select, and diagnose adapters |
| `DefaultAnalyzerFactory` | Construct `DefaultUpgradeAnalyzer` with discovered integrations |
| `AnalyzerFactory` | Injection seam for alternate analyzer construction |

## Related pages

- [[Package Map|Package-Map]]
- [[Core Analysis Pipeline|Core-Analysis-Pipeline]]
- [[Laravel Package Internals|Laravel-Package-Internals]]
- [[Test Adapters|Test-Adapters]]
