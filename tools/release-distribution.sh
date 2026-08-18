#!/usr/bin/env bash
# Commit-and-sign step for the three distribution repositories.
# Run it after build/prepare-distribution.sh has staged the payloads.
#
#   bash build/release-distribution.sh [--tag v0.3.0] [--work DIR] [--dry-run] [--yes]
#
# The tag is the only thing asked for: it defaults to the newest dated changelog
# release and can be edited at the prompt. Commit and tag messages follow the
# convention previous releases used and are generated from it. Nothing is pushed
# without a separate confirmation per repository. --dry-run prints the commands
# instead of running them, and --yes accepts every default including the pushes.
set -euo pipefail

root="$(git rev-parse --show-toplevel)"
work="${root}/build/dist"
tag=''
dry_run=false
assume_yes=false
packages=(core cli laravel)

while [[ $# -gt 0 ]]; do
  case "$1" in
    --tag|--version) tag="${2:?$1 needs a value}"; shift 2 ;;
    --work) work="${2:?--work needs a value}"; shift 2 ;;
    --dry-run) dry_run=true; shift ;;
    --yes) assume_yes=true; shift ;;
    -h|--help) sed -n '2,12p' "$0"; exit 0 ;;
    *) echo "Unknown option: $1" >&2; exit 2 ;;
  esac
done

ask() { # label, default
  local answer
  if [[ "${assume_yes}" == true ]]; then printf '%s' "$2"; return 0; fi
  read -r -p "$1 [$2]: " answer < /dev/tty || true
  printf '%s' "${answer:-$2}"
}

confirm() { # question
  if [[ "${assume_yes}" == true ]]; then return 0; fi
  local answer
  read -r -p "$1 [y/N]: " answer < /dev/tty || true
  [[ "${answer}" == [Yy] || "${answer}" == [Yy][Ee][Ss] ]]
}

verify_signature() { # tag
  if [[ "${dry_run}" == true ]]; then
    printf '  would run: git tag -v %q
' "$1"
    return 0
  fi

  local output status
  set +e
  output="$(git tag -v "$1" 2>&1)"
  status=$?
  set -e

  if [[ "${output}" != *'Good "git" signature'* && "${output}" != *'Good signature'* ]]; then
    echo "  ${1} does not carry a good signature:" >&2
    printf '%s
' "${output}" >&2
    exit 1
  fi

  echo "  signature ok: $(printf '%s' "${output}" | grep -m1 -E 'Good .*signature')"
  if [[ ${status} -ne 0 ]]; then
    echo '  (git could not name the signer locally; that only means gpg.ssh.allowedSignersFile is'
    echo '   unset on this machine. GitHub verifies the tag against the key on your account.)'
  fi
}

run() {
  if [[ "${dry_run}" == true ]]; then
    printf '  would run:'; printf ' %q' "$@"; printf '\n'
    return 0
  fi
  "$@"
}

if [[ -z "${tag}" ]]; then
  changelog_version="$(grep -m1 -oE '^## \[[0-9]+\.[0-9]+\.[0-9]+\]' "${root}/CHANGELOG.md" | tr -d '#[] ')"
  tag="$(ask 'release tag' "v${changelog_version}")"
fi
[[ "${tag}" == v* ]] || tag="v${tag}"
[[ "${tag}" =~ ^v[0-9]+\.[0-9]+\.[0-9]+$ ]] || { echo "Release tag must look like v1.2.3; got '${tag}'." >&2; exit 2; }

if [[ -z "$(git config --get user.signingkey)" ]]; then
  echo 'No user.signingkey is configured; the release tags must be signed.' >&2
  exit 1
fi

source_commit_file="${work}/.source-commit"
if [[ ! -f "${source_commit_file}" ]]; then
  echo "No ${source_commit_file}; run tools/prepare-distribution.sh first." >&2
  exit 1
fi
source_commit="$(<"${source_commit_file}")"
head_commit="$(git -C "${root}" rev-parse HEAD)"
if [[ "${source_commit}" != "${head_commit}" ]]; then
  echo "The staged payload was built from ${source_commit:0:8}, but HEAD is ${head_commit:0:8}." >&2
  echo 'Rerun tools/prepare-distribution.sh so the release matches the commit you are tagging.' >&2
  exit 1
fi

echo "Releasing ${tag} from ${work}"
echo "  commit message: release: prepare ${tag} distribution"
echo "  tag message:    php-upgrade-preflight/<package> ${tag}"
echo

for package in "${packages[@]}"; do
  directory="${work}/${package}"
  echo "== php-upgrade-preflight/${package} =="

  if [[ ! -d "${directory}/.git" ]]; then
    echo "  ${directory} is not a Git clone; run build/prepare-distribution.sh first." >&2
    exit 1
  fi

  cd "${directory}"
  branch="$(git symbolic-ref --short HEAD)"

  if git rev-parse -q --verify "refs/tags/${tag}" > /dev/null; then
    echo "  ${tag} already exists locally at $(git rev-parse --short "${tag}^{commit}"); skipping this repository."
    echo '  Rerun tools/prepare-distribution.sh for a clean clone if that tag is stale.'
    echo
    continue
  fi
  if [[ -n "$(git ls-remote --tags origin "refs/tags/${tag}")" ]]; then
    echo "  ${tag} already exists on origin; skipping this repository." >&2
    echo
    continue
  fi

  if [[ -n "$(git status --porcelain)" ]]; then
    git add -A
    git diff --cached --shortstat | sed 's/^/  staged: /'
    run git commit -m "release: prepare ${tag} distribution"
  else
    echo "  nothing to commit; tagging ${branch} at $(git rev-parse --short HEAD)"
  fi

  run git tag -s "${tag}" -m "php-upgrade-preflight/${package} ${tag}"
  verify_signature "${tag}"

  if confirm "  push ${branch} and ${tag} to origin?"; then
    run git push origin "${branch}"
    run git push origin "${tag}"
  else
    echo "  left unpushed: ${directory}"
  fi
  echo
done

cd "${root}"
echo 'Distribution repositories done. The monorepo tag starts the publishing run:'
echo
echo "  git tag -s ${tag} -m \"php-upgrade-preflight ${tag}\""
echo "  git push origin ${tag}"
