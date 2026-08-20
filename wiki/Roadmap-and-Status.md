# Roadmap and Project Status

> Point-in-time status: **2026-08-19**. This page reports repository-backed commitments and boundaries; it does not invent delivery dates or unapproved features.

## Current status

PHP Upgrade Preflight is an Open Source **public beta**. The latest published release recorded by the repository is **v0.3.2**, producing tool version `0.3.2` reports with schema `0.8`. Development on `main` uses `0.3.x-dev` aliases and `^0.3` internal constraints.

Public beta means the public PHP API, CLI and Artisan surfaces, adapter extension points, package boundaries, and report semantics are still being proven before `1.0`. It does **not** mean the analyzer guarantees a successful production upgrade.

The analyzer provides decision-support evidence. It does not:

- modify or perform the target upgrade;
- boot or execute the analyzed application;
- prove runtime compatibility;
- guarantee tests, deployment, or production behavior;
- replace security and operational review.

## Release lines

| Line | Status on 2026-08-19 | Report schema | Policy |
|---|---|---|---|
| `0.3.x` | Active published line from `main` | `0.8` | Patch compatibility commitment |
| `0.2.x` | Archival | `0.7` | Signed artifacts retained; no features, routine fixes, or security fixes |
| `0.1.x` | Archival | `0.6` | Immutable historical contract evidence |

Users on archival lines should plan an upgrade to v0.3 rather than pin indefinitely.

Within v0.3.x, patch releases preserve documented public PHP operation, CLI/Artisan behavior, required adapter interfaces and discovery metadata, exit policy, schema `0.8` compatibility, and supported transition/staged-analysis claims. Individual findings, evidence, diagnostics, security behavior, and documentation may be corrected without changing those contracts.

## What v0.3 delivers

- Three supported external Composer packages: `core`, `cli`, and `laravel`.
- Two internal test packages proving modern and legacy third-party adapter contracts.
- Automation-safe generic CLI, an interactive standalone wizard, and Laravel Artisan entry points over the same canonical analyzer.
- TTY-only durable progress events for standalone CLI and Artisan without contaminating report stdout.
- JSON schema `0.8` plus Markdown projection.
- Read-only Composer scenario analysis in analyzer-owned workspaces.
- Compatible and restricted Composer execution policies.
- Schema `1.0` partial/complete target-platform profiles.
- Framework adapters discovered from Composer metadata.
- Optional stage-target and source-usage extension points.
- Independent direct resolution, framework guidance, and staged-resolution conclusions.
- Signed, checksum/provenance-bound release artifacts and clean-consumer verification.

## Laravel support boundary

The current catalog supports:

- Laravel 7→8;
- retained direct 7→9 guidance;
- every adjacent hop from 8→9 through 12→13;
- gapless multi-major guidance within Laravel 7–13;
- staged Composer solving for a single rooted `laravel/framework` target across contiguous adjacent paths.

Same-major requests, downgrades, ambiguous or unknown majors, endpoints outside 7–13, and a missing first hop are unsupported. A later gap after a covered prefix is only partially supported. Illuminate-component-only projects and mixed Laravel-family target sets do not receive a fabricated staged solve.

## Explicitly outside the v0.3 scope

Repository contracts explicitly state that v0.3 does not add:

- Symfony or CodeIgniter production adapters;
- a PHAR distribution;
- a supported runtime container image;
- a higher PHP package floor;
- source or project Composer-file modification;
- target-application execution;
- a runtime-compatibility guarantee.

The repository's Docker files are development tooling, not a supported product runtime.

## Path toward 1.0

There is no repository-backed calendar date for `1.0.0`. It is a stability decision, appropriate when the public PHP API, CLI behavior, package split, adapter surface, and schema policy are mature enough that future breaking changes can wait for a new major release.

The project remains in major version `0` while those contracts are being proven:

- patch releases contain backward-compatible fixes, security work, documentation corrections, test maintenance, and release/build changes;
- minor releases may include features and intentional breaking changes, which still require prominent changelog and migration documentation.

A future PHP 9-only runtime could influence the decision because dropping PHP 8 is breaking, but PHP 9 does not automatically imply project version 1.0.

## Evidence required to change status

A credible move toward broader stability should preserve and extend the evidence already required by the repository:

- cross-version Linux and Windows tests;
- normal and lowest dependency-resolution consumers;
- framework-host smoke tests;
- exact coverage and selective-mutation ratchets;
- immutable fixture and archived compatibility contracts;
- privacy/redaction checks with synthetic canaries;
- deterministic resource budgets;
- signed distribution and monorepo tags;
- archive checksums, dependency inventory, and provenance;
- Packagist installation at exact signed-tag references;
- accurate developer- and manager-readable documentation.

These are release gates and maturity signals, not a promised delivery schedule.

## Licensing status

The repository is licensed under MIT, permitting commercial and noncommercial use, modification, and redistribution under its terms. Releases up to and including v0.3.1 were published under PolyForm Noncommercial 1.0.0 and remain governed by the license they shipped with. MIT applies to the repository and to v0.3.2 and later. The license text controls over summaries.

## How to read roadmap claims

Use these labels when discussing future work:

- **Committed:** encoded in an active compatibility contract or release gate.
- **Current:** implemented and tested on the point-in-time date.
- **Out of scope:** explicitly excluded by the v0.3 contract.
- **Possible:** technically discussed but not scheduled or promised.

Do not turn “possible” into a date, supported platform, adapter, or package claim without an approved code/documentation change.

## Mandatory release documentation rule

Before any new release tag, update this point-in-time page and every affected Wiki page. Record the new release, schema, support boundary, limitations, and verified examples. Codex, Claude, and other agents preparing the tag must perform this update as part of release work. Repository automation checks materialized Wiki drift, while behavioral accuracy and published four-destination commit evidence still require human or agent review.

## Where to verify details

- `README.md` — public overview and latest release identity.
- `docs/project-status.md` — canonical status and licensing position.
- `docs/versioning.md` — SemVer and release-line policy.
- `docs/v0.3-contract.md` — staged-analysis guarantees and exclusions.
- `docs/limitations.md` — trust boundaries and unsupported cases.
- `docs/release-checklist.md` — evidence required to publish.
- `CHANGELOG.md` and `docs/releases/` — released changes and provenance.

## Current capability matrix

This table distinguishes implemented capability from interpretation.

| Capability | Current repository-backed status | What it does not mean |
| --- | --- | --- |
| Generic PHP/Composer analysis | Available in Core and CLI | Every PHP framework has dedicated guidance |
| Laravel adapter | Available for the documented 7–13 boundary | Every Laravel package or source pattern is modeled |
| Direct Composer scenarios | Available | The application runs successfully |
| Adjacent staged solving | Available when one active provider returns a valid plan | Project files are upgraded between stages |
| Source inventory | AST-based and framework-extensible | Every inventory item is actionable impact |
| Source impact | Correlated with ownership, rules, or package changes | Unreported source is proven compatible |
| Target-platform profiles | Schema 1.0 partial and complete profiles | A deployment environment was inspected remotely |
| JSON report | Canonical schema 0.8 contract | Future schemas cannot add or change fields under versioning policy |
| Markdown report | Human-readable projection | Markdown is an independent analysis engine |
| Restricted Composer mode | Isolates analyzer state and requests no network | Operating-system sandboxing is guaranteed |
| Interactive wizard | Builds and reviews an explicit analysis request in a TTY | Prompts are supported in CI or replace the `analyze` contract |
| Package metadata choices | Distinguish found, no-match, not-found, and operationally unverified | A pre-analysis lookup proves final Composer feasibility |
| Terminal progress | Shows durable phases and scenarios on TTY stderr | Progress changes report semantics or appears in redirected streams |
| `--save-report` | Preserves stdout and writes an identical validated copy | Destinations inside the analyzed project are allowed |

## Supported entry points

The supported user-facing entry points are:

```text
vendor/bin/upgrade-intel analyze
vendor/bin/upgrade-intel wizard
php artisan upgrade:analyze
```

`analyze` and Artisan create `UpgradeRequest` directly and delegate to `UpgradeAnalyzer`. The wizard collects and reviews choices, prints an equivalent explicit command, then delegates to `analyze`.

They should agree on canonical analysis when normalized requests and integrations agree.

The generic CLI discovers installed adapters from Composer metadata.

The Artisan command is registered by the Laravel service provider and selects Laravel explicitly.

## Package status

| Composer package | Status | Notes |
| --- | --- | --- |
| `php-upgrade-preflight/core` | Supported external distribution | Framework-neutral analyzer and report contract |
| `php-upgrade-preflight/cli` | Supported external distribution | Generic executable and adapter discovery |
| `php-upgrade-preflight/laravel` | Supported external distribution | Laravel adapter and Artisan command |
| `php-upgrade-preflight/test-adapter` | Internal fixture | Exercises current optional adapter capabilities |
| `php-upgrade-preflight/legacy-test-adapter` | Internal fixture | Proves older adapter interfaces remain usable |

All five manifests participate in monorepo validation.

Only the first three belong in public distribution workflows.

## Stability dimensions

Project maturity is not one number.

The following dimensions can progress at different rates:

- PHP API stability;
- CLI and Artisan syntax stability;
- report schema stability;
- adapter interface stability;
- framework guidance coverage;
- source-impact precision;
- Composer environment reproducibility;
- privacy and redaction assurance;
- release provenance and consumer verification;
- documentation quality.

A new Laravel rule can improve guidance coverage without changing the schema.

A new report field can change schema work without changing Composer behavior.

A redaction correction can improve safety while preserving public command syntax.

Roadmap decisions should name the dimension being changed.

## Criteria for a broader stability claim

A future stability milestone should have evidence for:

1. Public interfaces used by real consumers without frequent breaking changes.
2. Schema evolution and migration policy exercised across releases.
3. Third-party adapters loading across supported Core versions.
4. Linux and Windows determinism for canonical outputs.
5. Lowest and normal dependency-set consumer verification.
6. Bounded staged analysis under representative and worst supported chains.
7. Privacy canaries covering commands, evidence, paths, URLs, and structured data.
8. Signed artifacts and exact-tag installation verification.
9. Junior-readable operating documentation and manager-readable limitations.
10. A release process that updates Wiki and repository documentation together.

These are evidence categories, not a hidden release date.

## How roadmap proposals should be written

A useful proposal contains:

```text
User problem:
Current evidence:
Proposed capability:
Package and public contract affected:
Schema/compatibility impact:
Trust and privacy impact:
Test and fixture plan:
Documentation and migration plan:
Explicit non-goals:
```

For example, “add Symfony adapter” is not enough.

The proposal should identify detection packages, version boundary, maintained sources, rule vocabulary, staged support decision, and ownership of future updates.

Until implemented and tested, it remains possible rather than current.

## Examples of non-commitments

The following statements are not roadmap commitments unless a future approved change says otherwise:

- support for every PHP framework;
- a hosted analysis service;
- automatic source rewriting;
- automated deployment approval;
- a PHAR release;
- a production container image;
- PHP 9-only package requirements;
- a specific 1.0 date;
- unlimited staged hops or Composer processes;
- automatic security vulnerability scanning.

Avoid turning repository experiments, development Docker files, or fixture adapters into product promises.

## Reading release status from source

For a release candidate, compare all of these values:

| Source | Expected relationship |
| --- | --- |
| Git tag | Exact `vMAJOR.MINOR.PATCH` release identity |
| `ReportMetadata::TOOL_VERSION` | Same version without `v` |
| `ReportMetadata::SCHEMA_VERSION` | Existing or intentionally new schema |
| Package branch aliases | Next development line, not an invented package `version` field |
| Internal package constraints | Compatible with packages released together |
| `CHANGELOG.md` heading | Same release version |
| `docs/releases/vVERSION.md` | Matching release notes and provenance |
| Wiki status and examples | Describe tagged behavior |

A mismatch blocks the release until explained and corrected.

Do not fix only one version string.

Behavior claims, compatibility tables, commands, and examples must be reviewed together.

## Decision examples

### Example: new package rule

A new evidence-backed Laravel package rule can fit a v0.3 patch when it preserves documented interfaces and schema shape.

It still requires tests, fixture review, changelog entry, and affected Wiki updates.

### Example: new required adapter method

Adding a required method to `FrameworkIntegration` can break third-party adapters.

Prefer an optional capability interface where older adapters remain useful.

If a required break is necessary, treat it as a compatibility decision with migration documentation rather than a routine patch.

### Example: schema field

Adding a serialized field requires a schema decision.

Published schema files remain immutable.

Create a new schema version when required by the compatibility policy, add migrations, update snapshots, and revise consumer documentation.

### Example: PHP floor

Raising package PHP requirements is a runtime compatibility break.

Do not infer it from the analyzer's ability to target a newer PHP platform.

Analyzer runtime PHP and analyzed target PHP are different concepts.

## Manager-facing status summary

The project can reduce uncertainty before an upgrade branch is created.

It can identify dependency blockers, candidate changes, source review locations, framework guidance, and staged evidence.

It cannot replace application tests, data migration review, deployment rehearsals, security review, or operational ownership.

Public beta is therefore appropriate language for current capabilities.

Use report evidence to define work packages and validation steps.

Do not use the tool as an automated go-live gate without separate organizational controls.

## Wiki publication status

The main repository stores Wiki source in `wiki/`.

Release-specific publication repositories are managed separately as documented in [[Release Wiki Strategy|Release-Wiki-Strategy]].

This page must be refreshed for every release tag because version, schema, licensing, supported transitions, and active-line claims are point-in-time facts.

Agents preparing a release must follow the root `AGENTS.md` or `CLAUDE.md` instruction and block completion when Wiki publication cannot be completed.
