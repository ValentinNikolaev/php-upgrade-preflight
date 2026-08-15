# Limitations and trust boundaries

PHP Upgrade Preflight is a public-beta planning tool that predicts dependency and source impact. It does not perform an upgrade, certify application compatibility, establish production readiness, or prove that the upgraded application works.

## Dependency resolution

- Composer resolves scenarios against the repositories, credentials, network, and executable available to the analyzer process. A complete target-platform profile removes analyzer-host inheritance only for its supported safely simulated platform-package classes; unlisted values in those classes are modeled absent. It does not pin repository metadata, downloads, credentials, network behavior, or Composer executable behavior. Composer platform packages tied to the executable are labeled `toolchain_bound`, not claimed as safely simulated. Complete profiles and explicit absences require Composer 2.2 or newer.
- A partial profile, original `config.platform`, and named `--with-extension` / `--without-extension` assumptions remain host-dependent for unlisted packages. Presence-only assumptions cannot prove versioned constraints, so sentinel-related failures remain non-blocking advisories. The canonical report states the profile completeness, closed-world status, toolchain-bound names, and every effective decision; consumers must not widen that guarantee.
- Scripts and plugins stay disabled. A project that depends on plugin behavior may resolve differently in its normal environment.
- A successful candidate lock proves that Composer found one dependency solution. It does not prove runtime or production compatibility.
- Partial platform and staged probes provide ordering evidence. Only full-target scenarios determine combined feasibility, and only a complete profile can support the narrower cross-host platform-decision reproducibility claim.
- Composer output changes can reduce blocker parsing confidence. The report retains bounded, redacted excerpts and uncertainty. Redaction deliberately removes whole URLs and known credential forms, so diagnostic text may be less specific.

## Source inspection

- The scanner parses PHP syntax and reports supported static symbol and configuration references.
- It does not execute the application, resolve container bindings, evaluate dynamic class names, or infer string-built symbols.
- Composer PSR-4 and PSR-0 ownership uses deterministic longest-prefix matching for class-like symbols. Function and constant ownership requires an exact declaration from an available classmap or files entry. Root `autoload-dev` metadata is indexed, while dependency `autoload-dev` metadata remains root-only and is ignored.
- Missing classmap/files paths, unsupported metadata shapes, `eval`, `class_alias`, and registered dynamic autoloaders are reported as ownership uncertainty. Exact classmap/files indexing is skipped when inventory is empty and stops at a deterministic 2,000-file safety limit; exceeding it also adds uncertainty. Custom installer paths and runtime-generated symbols may therefore remain unresolved.
- Parse errors skip the malformed file and add evidence-linked uncertainty.
- Default scans exclude dependencies and common generated, cache, and build directories. Explicit paths inside the project can opt into an excluded directory.
- `source_inventory` is an observation list, not a change list. An item reaches `source_impact` only when it correlates with a selected final-target package change, an applicable framework rule, or both. Dynamic or unowned usages can therefore remain inventory-only, while a framework-correlated item may be actionable even when package ownership is unknown.
- Direct `source_impact` remains tied to the selected successful final-target scenario. Schema 0.8 stages separately project applicable framework findings and source impact for executed hops, but always from the original project snapshot. The analyzer does not simulate or claim that source edits were applied between stages.

## Framework guidance

- v0.2 retains Laravel 7→8 and direct 7→9 rules and adds every adjacent rule pack from 8→9 through 12→13. Gapless adjacent packs can compose multi-major guidance within Laravel 7–13. Same-major requests, downgrades, ambiguous or unknown majors, a source or target outside the catalog, and requests whose first hop is missing are unsupported. A covered prefix before a later gap is only `partially_supported`, and guidance never crosses the gap.
- Framework support describes rule-pack coverage, not dependency feasibility. In schema 0.8, `transition.framework_guidance[].status`, direct `resolution.status`, and `staged_resolution.status` are three independent dimensions and may disagree without error.
- Milestone 0 staged Composer solving is deliberately limited to the committed rooted `laravel/framework` 10→13 vertical slice, a request whose only Laravel-family target is `laravel/framework`, and one active stage-target provider. Illuminate-component-only projects, mixed Laravel-family target sets, and other supported Laravel guidance paths remain honest `staged_resolution.execution_state: skipped` results until the production generalization milestone.
- The Laravel 13 pack is limited to exact package metadata and explicit maintainer guidance; unencoded guide changes remain manual review work.
- Encoded package ranges and maintainer links identify review work; they do not replace each package's upgrade guide.
- Skeleton findings identify locations to compare and carry low confidence. They are not confirmed incompatibilities.
- Laravel 11's streamlined application skeleton is optional for upgraded Laravel 10 applications; the adapter does not report the retained Laravel 10 structure as required migration work.
- The Laravel 11 curl rule can prove an explicitly absent `ext-curl` assumption. A present PHP extension version does not prove the linked libcurl runtime version, so deployment verification remains necessary.
- Ambiguous framework target ranges produce less guidance instead of guessing a target major.

## Read-only boundary

The analysis pipeline copies the target manifest and lockfile, then scans source files without writing them. A project-local `composer require` still changes the project during installation. Shell redirection can also write inside the project before the analyzer can validate the destination.

Exact project and source paths remain available only for internal filesystem access. Default canonical JSON and Markdown replace absolute roots with `[PROJECT_ROOT]`, `[REPORT_OUTPUT]`, `[LOCAL_REPOSITORY]`, and `[ANALYZER_WORKSPACE]`; source file locations remain project-relative.

Debug mode preserves temporary copies and exposes exact `temp_path` values, making debug reports and retained workspaces non-shareable. Those workspaces contain the copied Composer manifest and lock data; output redaction does not sanitize files retained on disk. Default cleanup failures expose only `[ANALYZER_WORKSPACE]`. Credentials remain redacted in rendered output and diagnostics in every mode.

## Untrusted projects

The tool reads Composer metadata and PHP source from the target. Composer scripts and plugins stay disabled, but Composer may contact declared repositories and use host credentials. Analyze untrusted repositories inside a disposable container or restricted account with scoped credentials and network access.

Secret redaction is deterministic and covered by synthetic canaries, but no pattern-based filter can recognize every future credential format. Use least-privilege credentials, review reports before sharing them, and report an unredacted form through the private security channel.
