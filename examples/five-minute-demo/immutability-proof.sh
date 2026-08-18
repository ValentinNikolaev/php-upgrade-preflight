#!/usr/bin/env bash

set -euo pipefail

repository_root="${1:-/app}"
demo_root="${repository_root}/examples/five-minute-demo"
target="${demo_root}/target"
report="$(mktemp "${TMPDIR:-/tmp}/php-upgrade-preflight-immutability.XXXXXX.json")"

cleanup() {
    rm -f "${report}"
}

tree_digest() {
    (
        cd "$1"
        find . -type f -print0 | LC_ALL=C sort -z | xargs -0 sha256sum
    ) | sha256sum | cut -d ' ' -f 1
}

trap cleanup EXIT

printf '\033[1;36mPHP Upgrade Preflight\033[0m  Immutability proof\n'
printf 'The analyzed project is immutable input: Composer runs only in disposable workspaces.\n\n'

file_count="$(find "${target}" -type f | wc -l | tr -d '[:space:]')"
printf 'Analyzed project: %s (%s files)\n' 'examples/five-minute-demo/target' "${file_count}"

before="$(tree_digest "${target}")"
printf '\033[1mRecursive SHA-256 before: %s\033[0m\n\n' "${before}"

printf '\033[1;33m$ upgrade-intel analyze --target=laravel/framework:^13.0 --target-php=8.3 --composer-mode=restricted ...\033[0m\n'
printf '\033[2mRunning real Composer scenarios in analyzer-owned temporary workspaces ...\033[0m\n'
started_at="${SECONDS}"
COMPOSER_DISABLE_NETWORK=1 COMPOSER_ROOT_VERSION=1.0.0 \
    php "${repository_root}/packages/cli/bin/upgrade-intel" analyze \
    --path="${target}" \
    --from-php=8.1 \
    --target=laravel/framework:^13.0 \
    --target-php=8.3 \
    --without-extension=ext-preflight-stage \
    --composer-mode=restricted \
    --framework=laravel \
    --format=json \
    --output="${report}" >/dev/null
elapsed="$((SECONDS - started_at))"
report_size="$(du -k "${report}" | cut -f 1)"
printf 'Report written outside the target: %sK of JSON in %ss\n\n' "${report_size}" "${elapsed}"

after="$(tree_digest "${target}")"
printf '\033[1mRecursive SHA-256 after:  %s\033[0m\n\n' "${after}"

if [[ "${before}" != "${after}" ]]; then
    printf '\033[1;31mTarget unchanged: NO\033[0m\n' >&2
    exit 1
fi

printf '\033[1;32mTarget unchanged: YES\033[0m  every one of the %s files is byte-for-byte identical\n' "${file_count}"
