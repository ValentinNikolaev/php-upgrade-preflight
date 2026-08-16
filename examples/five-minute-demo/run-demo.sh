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

php -r '
$report = json_decode(file_get_contents($argv[1]), true, 512, JSON_THROW_ON_ERROR);
$canonical = json_decode(file_get_contents($argv[2]), true, 512, JSON_THROW_ON_ERROR);
$projection = static function (array $value): array {
    return [
        "schema" => $value["metadata"]["schema_version"],
        "direct" => $value["resolution"]["status"],
        "staged" => $value["staged_resolution"]["status"],
        "stages" => array_map(static fn (array $stage): array => [
            $stage["id"],
            $stage["execution_state"],
            $stage["resolution_status"],
            $stage["selected_attempt"],
            $stage["input_state"]["state_sha256"] ?? null,
            $stage["output_state"]["state_sha256"] ?? null,
        ], $value["staged_resolution"]["stages"]),
        "blockers" => array_map(static fn (array $blocker): array => [
            $blocker["stage_id"],
            $blocker["category"],
            $blocker["subject"],
            $blocker["blocking_package"],
            $blocker["constraint"],
            $blocker["dependency_path"],
            $blocker["lifecycle"],
            array_column($blocker["lifecycle_history"], "status"),
        ], $value["staged_resolution"]["blocker_registry"]),
    ];
};
if ($projection($report) !== $projection($canonical)) {
    throw new RuntimeException("Generated staged evidence differs from the checked-in canonical report.");
}

printf("\n\033[1;31mDirect final target: %s\033[0m\n", strtoupper($report["resolution"]["status"]));
printf("\033[1;31mStaged resolution:   %s\033[0m\n", strtoupper($report["staged_resolution"]["status"]));
printf("Composer execution:  %s (offline best effort; no OS sandbox)\n", strtoupper($report["composer_execution"]["mode"]));
foreach ($report["staged_resolution"]["stages"] as $stage) {
    printf(
        "Stage %d->%d: %-21s attempts=%d selected=%s\n",
        $stage["from_major"],
        $stage["to_major"],
        strtoupper((string) $stage["resolution_status"]),
        count($stage["attempts"]),
        $stage["selected_attempt"] === null ? "none" : (string) $stage["selected_attempt"]
    );
}
foreach ($report["staged_resolution"]["blocker_registry"] as $blocker) {
    printf(
        "Blocker %-9s %-22s %s\n",
        strtoupper($blocker["lifecycle"]),
        $blocker["blocking_package"] ?? $blocker["subject"],
        $blocker["constraint"] ?? ""
    );
}
$occurrence = $report["staged_resolution"]["stages"][2]["source_impact"][0]["occurrences"][0];
printf(
    "Original-source finding: %s:%d  %s\n",
    $occurrence["file"],
    $occurrence["line"],
    basename(str_replace("\\", "/", $occurrence["symbol"]))
);
' "${report}" "${demo_root}/reports/laravel-10-to-13.json"

after="$(tree_digest "${target}")"
printf '\n\033[2mTarget SHA-256 after:  %s\033[0m\n' "${after}"

if [[ "${before}" != "${after}" ]]; then
    printf '\033[1;31mTarget unchanged: NO\033[0m\n' >&2
    exit 1
fi

printf '\033[1;32mTarget unchanged: YES\033[0m  (%ss)\n' "${elapsed}"
