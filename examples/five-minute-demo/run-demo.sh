#!/usr/bin/env bash

set -euo pipefail

repository_root="${1:-/app}"
demo_root="${repository_root}/examples/five-minute-demo"
target="${demo_root}/target"
report="$(mktemp "${TMPDIR:-/tmp}/php-upgrade-preflight-demo.XXXXXX.json")"

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

printf '\033[1;36mPHP Upgrade Preflight\033[0m  Laravel 10 -> 13\n'
printf 'Offline sequential Composer stages + framework guidance + source scan\n\n'

before="$(tree_digest "${target}")"
printf '\033[2mTarget SHA-256 before: %s\033[0m\n\n' "${before}"

printf '\033[1;33m$ upgrade-intel analyze --from-php=8.1 --target=laravel/framework:^13.0 --target-php=8.3 --without-extension=ext-preflight-stage --composer-mode=restricted\033[0m\n'
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

php "${demo_root}/summarize-report.php" "${report}" "${demo_root}/reports/laravel-10-to-13.json"

after="$(tree_digest "${target}")"
printf '\n\033[2mTarget SHA-256 after:  %s\033[0m\n' "${after}"

if [[ "${before}" != "${after}" ]]; then
    printf '\033[1;31mTarget unchanged: NO\033[0m\n' >&2
    exit 1
fi

printf '\033[1;32mTarget unchanged: YES\033[0m  (%ss)\n' "${elapsed}"
