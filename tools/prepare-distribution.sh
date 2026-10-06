#!/usr/bin/env bash
# Rebuild the three distribution working trees from the current monorepo checkout.
# Run it from the monorepo root on the exact commit that will be tagged.
# It clones, replaces content, and stages; it never commits, tags, or pushes.
set -euo pipefail

root="$(git rev-parse --show-toplevel)"
work="${1:-${root}/build/dist}"
owner='ValentinNikolaev'

cd "${root}"
if [[ -n "$(git status --porcelain)" ]]; then
  echo 'Monorepo working tree is dirty; commit the release state first.' >&2
  exit 1
fi
release_commit="$(git rev-parse HEAD)"

mkdir -p "${work}"
for package in core cli laravel; do
  target="${work}/${package}"
  expected="${work}/expected-${package}"

  rm -rf "${target}" "${expected}"
  git clone --quiet "https://github.com/${owner}/php-upgrade-preflight-${package}.git" "${target}"
  git -C "${target}" config core.filemode false

  # Build the payload the release workflow expects: the package subtree plus the
  # shared licence, readme, changelog, security policy, and documentation tree.
  mkdir -p "${expected}"
  cp -R "${root}/packages/${package}/." "${expected}/"
  cp "${root}/LICENSE" "${root}/README.md" "${root}/CHANGELOG.md" "${root}/SECURITY.md" "${expected}/"
  cp -R "${root}/docs" "${expected}/docs"

  git -C "${target}" rm -rq --ignore-unmatch .
  cp -R "${expected}/." "${target}/"
  git -C "${target}" add -A

  # Bind mounts can report every file executable (or none). Use the committed
  # source modes, not stat bits, while retaining the blobs staged from the copy.
  git ls-tree -r -z "${release_commit}" -- "packages/${package}" docs LICENSE README.md CHANGELOG.md SECURITY.md |
    while IFS= read -r -d '' entry; do
      mode="${entry%% *}"
      source_path="${entry#*$'\t'}"
      path="${source_path#packages/${package}/}"
      case "${mode}" in
        100644|100755|120000) ;;
        *) echo "Unsupported distribution source mode ${mode}: ${source_path}" >&2; exit 1 ;;
      esac
      blob="$(git -C "${target}" rev-parse ":${path}")"
      git -C "${target}" update-index --cacheinfo "${mode}" "${blob}" "${path}"
    done

  if ! diff -r --exclude=.git "${expected}" "${target}" > /dev/null; then
    echo "Distribution payload for ${package} does not match the expected tree." >&2
    diff -r --exclude=.git "${expected}" "${target}" >&2 || true
    exit 1
  fi

  changed="$(git -C "${target}" diff --cached --name-only | wc -l)"
  echo "${package}: payload staged from ${release_commit:0:8}, ${changed} changed path(s) in ${target}"
done

# Recorded so the release step can refuse a payload built from an older commit.
printf '%s
' "${release_commit}" > "${work}/.source-commit"
