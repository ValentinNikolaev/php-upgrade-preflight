# v0.1 release checklist

Run this checklist from a clean release candidate commit. Record command output or CI links in the release notes.

## Version and contract

- [x] Confirm `ReportMetadata::TOOL_VERSION` is `0.1.0` and the current schema stays `0.6`.
- [x] Confirm every package dependency on another project package uses `^0.1`.
- [x] Confirm all package manifests use `0.1.x-dev` as the `dev-main` branch alias.
- [x] Confirm the release verifier rejects every series except patch releases on `0.1.x`.
- [x] Move any remaining changelog entries under `0.1.0` and confirm the release date.
- [x] Validate links in README, package support metadata, schema docs, security policy, and changelog.

Run `composer release:verify -- 0.1.0` to enforce the version, branch-alias, internal-constraint, changelog, and release-notes checks above. Composer package versions come from tags; do not add `version` fields to package manifests.

## Deterministic quality gate

- [x] Run `composer check` on PHP 8.0 through PHP 8.5.
- [x] Confirm the Windows PHP 8.3 and Ubuntu jobs pass.
- [x] Run `composer test:fixtures` and review all six JSON and Markdown snapshot pairs.
- [x] Confirm fixture immutability assertions pass and `git status --short` shows no fixture changes.
- [x] Run normal and `--prefer-lowest` clean dependency installs for each package subtree on its declared PHP floor.
- [x] Install the Laravel adapter against supported Illuminate 8, 9, and 10 applications; also smoke-test the declared 11 and 12 constraints.
- [x] Confirm every Laravel host-line smoke boots the application and verifies provider discovery, analyzer binding, command registration, and the harmless no-target invocation.
- [x] Confirm the synthetic Composer credential fixture is absent from JSON, Markdown, captured diagnostics, and all generated ZIP entries.

The historical Laravel host matrix uses Composer's `--no-security-blocking` option so published advisories do not prevent installability and application-boot checks. This flag is limited to ephemeral compatibility consumers; a passing compatibility job is not a security endorsement of an old Laravel release, and advisory review remains a separate maintainer decision.

## Fresh-clone audit

- [x] Clone the release candidate into a new directory with no existing `vendor` tree.
- [x] Run `composer install` and `composer check` in the documented Docker environment.
- [x] Install the CLI and Laravel adapter in a separate tools directory.
- [x] Analyze a copied documented fixture in JSON and Markdown modes.
- [x] Hash or snapshot the fixture before and after, then confirm byte-for-byte equality.
- [x] Test an output path containing spaces on Windows and Unix.

The `Release` workflow performs the clean install, deterministic gate, JSON and Markdown analysis, fixture digest comparison, and spaced-output-path audit from a second clone on both Windows and Linux.

## Package distribution

This repository is a monorepo. Packagist reads a package manifest from the root of each distribution repository, so publish the `core`, `cli`, and `laravel` subtrees to their corresponding package repositories before submission.

- [x] Split `packages/core`, `packages/cli`, and `packages/laravel` with history preserved.
- [x] Confirm each split root contains its `composer.json`, source, schema resources where applicable, license, readme, changelog, and security information.
- [x] Run `composer validate --strict` at the root of each split.
- [x] Create matching signed `v0.1.0` tags on all three package repositories from the approved monorepo commit.
- [x] Submit or update all three Packagist packages and enable GitHub synchronization.
- [x] Install `php-upgrade-preflight/cli:^0.1` and `php-upgrade-preflight/laravel:^0.1` from Packagist in an empty tools directory.
- [x] Confirm `vendor/bin/upgrade-intel --help` and Laravel package discovery work from distribution archives.

Do not submit the monorepo root package to Packagist. Its path repositories support development and cannot resolve as dependency repositories for consumers.

The release workflow stages each package with its license and shared README, changelog, security, and documentation files, then produces validated Composer archives and SHA-256 checksums. The distribution-repository split, signed tags, and Packagist synchronization remain explicit maintainer actions because they require access to separate repositories.

Before upload or publication, the workflow stamps the release version only into each temporary archive manifest, verifies `SHA256SUMS`, scans archive contents for synthetic secret canaries, and installs `core`, `cli`, and `laravel` from the ZIPs in three clean consumers. The consumer gate runs `upgrade-intel --help`, one canonical JSON analysis with a before/after fixture digest, and the Laravel package-discovery boot harness.

## Publish

- [x] Create the GitHub release from the signed `v0.1.0` tag and attach release notes derived from the changelog.
- [x] Confirm Packagist shows the license, authors, keywords, homepage, support links, and `0.1.0` for each package.
- [x] Run the README quick start using only published packages.
- [x] Announce any known limitations and link to the schema compatibility policy.

A manual `Release` workflow run verifies and packages a version without publishing. Pushing a matching annotated tag publishes the GitHub release only after GitHub verifies its signature, its commit is confirmed on `main`, and the deterministic suite, dependency matrix, and fresh-clone audits pass.

## Release evidence

- [x] Manual `Release` workflow: [run 31269898286](https://github.com/ValentinNikolaev/php-upgrade-preflight/actions/runs/31269898286).
- [x] Approved release commit: [`a8d1548`](https://github.com/ValentinNikolaev/php-upgrade-preflight/commit/a8d154826f35fcb25a22868556534cc8c0331c0c).
- [x] [`release-archives` artifact](https://github.com/ValentinNikolaev/php-upgrade-preflight/actions/runs/31270210035/artifacts/9025412650), artifact SHA-256 `b94895c36cae77362ee43fab25a8c3de4ddca06a6296a692325092ddeb2721ed`, and independently verified `SHA256SUMS`.
- [x] Archive- and Packagist-installed fixture SHA-256 before and after: `e7fc002d5fec60dec556b90c51b806dd8433036a5f124e8089db9c6022d1cb37`.
- [x] Published [GitHub release](https://github.com/ValentinNikolaev/php-upgrade-preflight/releases/tag/v0.1.0) from successful [tag run 31270210035](https://github.com/ValentinNikolaev/php-upgrade-preflight/actions/runs/31270210035).
- [x] Packagist install evidence: [core](https://packagist.org/packages/php-upgrade-preflight/core), [cli](https://packagist.org/packages/php-upgrade-preflight/cli), and [laravel](https://packagist.org/packages/php-upgrade-preflight/laravel), all auto-updated at `v0.1.0`.
