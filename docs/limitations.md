# Limitations and trust boundaries

PHP Upgrade Preflight predicts dependency and source impact. It does not perform an upgrade or prove that the upgraded application works.

## Dependency resolution

- Composer resolves scenarios against the repositories, credentials, and network available to the analyzer process. Explicit extension versions and absences override the named extensions in temporary workspaces; absence simulation requires Composer 2.2 or newer. Presence-only extension assumptions cannot prove versioned constraints, so sentinel-related failures remain non-blocking advisories. Every unlisted extension remains analyzer-host state and is reported as uncertainty.
- Scripts and plugins stay disabled. A project that depends on plugin behavior may resolve differently in its normal environment.
- A successful candidate lock proves that Composer found one dependency solution. It does not prove runtime compatibility.
- Partial platform and staged probes provide ordering evidence. Only full-target scenarios determine combined feasibility.
- Composer output changes can reduce blocker parsing confidence. The report retains bounded, redacted excerpts and uncertainty. Redaction deliberately removes whole URLs and known credential forms, so diagnostic text may be less specific.

## Source inspection

- The scanner parses PHP syntax and reports supported static symbol and configuration references.
- It does not execute the application, resolve container bindings, evaluate dynamic class names, or infer string-built symbols.
- Composer PSR-4 and PSR-0 ownership uses deterministic longest-prefix matching for class-like symbols. Function and constant ownership requires an exact declaration from an available classmap or files entry. Root `autoload-dev` metadata is indexed, while dependency `autoload-dev` metadata remains root-only and is ignored.
- Missing classmap/files paths, unsupported metadata shapes, `eval`, `class_alias`, and registered dynamic autoloaders are reported as ownership uncertainty. Exact classmap/files indexing is skipped when inventory is empty and stops at a deterministic 2,000-file safety limit; exceeding it also adds uncertainty. Custom installer paths and runtime-generated symbols may therefore remain unresolved.
- Parse errors skip the malformed file and add evidence-linked uncertainty.
- Default scans exclude dependencies and common generated, cache, and build directories. Explicit paths inside the project can opt into an excluded directory.

## Framework guidance

- v0.2 retains Laravel 7 to 8/9 rules and adds adjacent rule packs through Laravel 12. Laravel 13 remains approved catalog scope but is not implemented by Milestone 5.
- Encoded package ranges and maintainer links identify review work; they do not replace each package's upgrade guide.
- Skeleton findings identify locations to compare and carry low confidence. They are not confirmed incompatibilities.
- Laravel 11's streamlined application skeleton is optional for upgraded Laravel 10 applications; the adapter does not report the retained Laravel 10 structure as required migration work.
- The Laravel 11 curl rule can prove an explicitly absent `ext-curl` assumption. A present PHP extension version does not prove the linked libcurl runtime version, so deployment verification remains necessary.
- Ambiguous framework target ranges produce less guidance instead of guessing a target major.

## Read-only boundary

The analysis pipeline copies the target manifest and lockfile, then scans source files without writing them. A project-local `composer require` still changes the project during installation. Shell redirection can also write inside the project before the analyzer can validate the destination.

Exact project and source paths remain available only for internal filesystem access. Default canonical JSON and Markdown replace absolute roots with `[PROJECT_ROOT]`, `[REPORT_OUTPUT]`, `[LOCAL_REPOSITORY]`, and `[ANALYZER_WORKSPACE]`; source file locations remain project-relative.

Debug mode preserves temporary copies and exposes exact `temp_path` values, making debug reports and retained workspaces non-shareable. Default cleanup failures expose only `[ANALYZER_WORKSPACE]`. Credentials remain redacted in every mode.

## Untrusted projects

The tool reads Composer metadata and PHP source from the target. Composer scripts and plugins stay disabled, but Composer may contact declared repositories and use host credentials. Analyze untrusted repositories inside a disposable container or restricted account with scoped credentials and network access.

Secret redaction is deterministic and covered by synthetic canaries, but no pattern-based filter can recognize every future credential format. Use least-privilege credentials, review reports before sharing them, and report an unredacted form through the private security channel.
