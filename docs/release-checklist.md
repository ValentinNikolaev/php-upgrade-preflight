# v0.1 release checklist

Run this checklist from a clean release candidate commit. Record command output or CI links in the release notes.

## Version and contract

- [ ] Confirm `ReportMetadata::TOOL_VERSION` is `0.1.0` and the current schema stays `0.6`.
- [ ] Confirm every package dependency on another project package uses `^0.1`.
- [ ] Confirm all package manifests use `0.1.x-dev` as the `dev-main` branch alias.
- [ ] Move any remaining changelog entries under `0.1.0` and confirm the release date.
- [ ] Validate links in README, package support metadata, schema docs, security policy, and changelog.

## Deterministic quality gate

- [ ] Run `composer check` on PHP 8.0 through PHP 8.5.
- [ ] Confirm the Windows PHP 8.3 and Ubuntu jobs pass.
- [ ] Run `composer test:fixtures` and review all six JSON and Markdown snapshot pairs.
- [ ] Confirm fixture immutability assertions pass and `git status --short` shows no fixture changes.
- [ ] Run normal and `--prefer-lowest` clean dependency installs for each package subtree on its declared PHP floor.
- [ ] Install the Laravel adapter against supported Illuminate 8, 9, and 10 applications; also smoke-test the declared 11 and 12 constraints.

## Fresh-clone audit

- [ ] Clone the release candidate into a new directory with no existing `vendor` tree.
- [ ] Run `composer install` and `composer check` in the documented Docker environment.
- [ ] Install the CLI and Laravel adapter in a separate tools directory.
- [ ] Analyze a copied documented fixture in JSON and Markdown modes.
- [ ] Hash or snapshot the fixture before and after, then confirm byte-for-byte equality.
- [ ] Test an output path containing spaces on Windows and Unix.

## Package distribution

This repository is a monorepo. Packagist reads a package manifest from the root of each distribution repository, so publish the `core`, `cli`, and `laravel` subtrees to their corresponding package repositories before submission.

- [ ] Split `packages/core`, `packages/cli`, and `packages/laravel` with history preserved.
- [ ] Confirm each split root contains its `composer.json`, source, schema resources where applicable, license, readme, changelog, and security information.
- [ ] Run `composer validate --strict` at the root of each split.
- [ ] Create matching signed `v0.1.0` tags on all three package repositories from the approved monorepo commit.
- [ ] Submit or update all three Packagist packages and enable GitHub synchronization.
- [ ] Install `php-upgrade-preflight/cli:^0.1` and `php-upgrade-preflight/laravel:^0.1` from Packagist in an empty tools directory.
- [ ] Confirm `vendor/bin/upgrade-intel --help` and Laravel package discovery work from distribution archives.

Do not submit the monorepo root package to Packagist. Its path repositories support development and cannot resolve as dependency repositories for consumers.

## Publish

- [ ] Create the GitHub release from the signed `v0.1.0` tag and attach release notes derived from the changelog.
- [ ] Confirm Packagist shows the license, authors, keywords, homepage, support links, and `0.1.0` for each package.
- [ ] Run the README quick start using only published packages.
- [ ] Announce any known limitations and link to the schema compatibility policy.
