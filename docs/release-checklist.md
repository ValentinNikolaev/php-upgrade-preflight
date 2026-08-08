# Release checklist

Run this checklist from a clean release-candidate commit. Set `VERSION` to an exact `MAJOR.MINOR.PATCH` value and derive `TAG=v$VERSION`, `SERIES=MAJOR.MINOR`, and `DEV_VERSION=$SERIES.x-dev`. Record command output and CI links in `docs/releases/v$VERSION.md` instead of writing release-specific values into this checklist.

## Version and contract

- [ ] Confirm the requested release series is enabled by `ReleaseVerifier::ACTIVE_RELEASE_SERIES`.
- [ ] Confirm the approved release branch exists on `origin`, is protected, and contains the release-candidate commit (`0.1.x` for `0.1.x`; `main` for later approved series).
- [ ] Confirm `ReportMetadata::TOOL_VERSION` is the exact `VERSION` and the release notes describe `ReportMetadata::SCHEMA_VERSION`.
- [ ] Confirm every package dependency on another project package uses `^$SERIES`.
- [ ] Confirm root path versions, root requirements, and every `dev-main` branch alias use `DEV_VERSION`.
- [ ] Move releasable changelog entries under a dated `[VERSION]` heading.
- [ ] Create `docs/releases/v$VERSION.md` with `# PHP Upgrade Preflight v$VERSION` as its first line.
- [ ] Validate links in README, package support metadata, schema docs, security policy, changelog, and release notes.

Run `composer release:verify -- VERSION` to enforce the release-series, tool-version, branch-alias, internal-constraint, changelog, and release-notes checks. Composer package versions come from tags; do not add `version` fields to package manifests.

## Deterministic quality gate

- [ ] Run `composer check` on every supported analyzer PHP version.
- [ ] Confirm the required Windows and Ubuntu jobs pass.
- [ ] Run `composer test:fixtures` and review every JSON and Markdown snapshot pair.
- [ ] Confirm fixture immutability assertions pass and `git status --short` shows no fixture changes.
- [ ] Enforce the representative-corpus report-size, runtime, memory, redaction, and ordering budgets in [the v0.2 contract](v0.2-contract.md), when applicable to the release line.
- [ ] Run normal and `--prefer-lowest` clean dependency installs for each package subtree on its declared PHP floor.
- [ ] Install the Laravel adapter against every advertised Illuminate host line and run the application-boot smoke.
- [ ] Confirm every host-line smoke verifies provider discovery, analyzer binding, command registration, and a harmless invocation.
- [ ] Confirm synthetic credentials are absent from JSON, Markdown, captured diagnostics, workflow logs, and generated ZIP entries.

Historical host matrices may need Composer's `--no-security-blocking` option when a released old framework has advisories. Limit that flag to ephemeral compatibility consumers; installability is not a security endorsement.

## Fresh-clone audit

- [ ] Clone the release candidate into a directory with no existing `vendor` tree.
- [ ] Run `composer install` and `composer check` in the documented environment.
- [ ] Install the CLI and framework adapters in a separate tools directory.
- [ ] Analyze a copied documented fixture in JSON and Markdown modes.
- [ ] Hash the fixture before and after and confirm byte-for-byte equality.
- [ ] Test an output path containing spaces on Windows and Unix.

The release workflow performs the clean install, deterministic gate, JSON and Markdown analysis, fixture digest comparison, and spaced-output-path audit from a second clone on both platforms.

## Package distribution

This repository is a monorepo. Packagist reads a package manifest from the root of each distribution repository, so publish `core`, `cli`, and `laravel` subtrees to their corresponding repositories before synchronization.

- [ ] Split every package subtree with history preserved.
- [ ] Confirm each split contains its manifest, source, schema resources where applicable, license, shared readme, changelog, security policy, and documentation.
- [ ] Run `composer validate --strict` at every split root.
- [ ] Create matching signed `TAG` tags on the monorepo and all distribution repositories from the approved commit.
- [ ] Update all Packagist packages and confirm GitHub synchronization.
- [ ] Install the released package constraints from Packagist in an empty directory.
- [ ] Confirm the generic CLI help, one analysis, framework package discovery, and target-project immutability from distribution artifacts.

Do not submit the monorepo root package to Packagist. Its path repositories exist only for development.

The workflow stamps the exact release version only into temporary archive manifests, verifies `SHA256SUMS`, scans archives for seeded secrets, and installs every package ZIP in clean consumers. Distribution-repository updates and Packagist synchronization remain explicit maintainer actions.

## Publish

- [ ] Create the GitHub release from the signed `TAG` and attach release notes derived from the changelog.
- [ ] Confirm Packagist shows the expected metadata and exact version for every package.
- [ ] Run the README quick start using only published packages.
- [ ] Announce supported transitions, schema migration requirements, and known limitations.
- [ ] Record the workflow run, approved commit, archive checksum, immutable-fixture checksum, release URL, and Packagist evidence in `docs/releases/v$VERSION.md`.

A manual `Release` run verifies and packages without publishing. A matching annotated tag publishes only after GitHub verifies its signature, confirms its commit is on `main` or (for `0.1.x`) the protected `0.1.x` maintenance line, and all release gates pass. Historical v0.1.0 evidence is retained in [`docs/releases/v0.1.0.md`](releases/v0.1.0.md).
