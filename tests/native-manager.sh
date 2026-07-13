#!/usr/bin/env bash
set -euo pipefail

root="$(cd "$(dirname "${BASH_SOURCE[0]}")/.." && pwd)"

# shellcheck source=../deploy/native/lightdocs
source "$root/deploy/native/lightdocs"

work_root="$(mktemp -d)"
trap 'rm -rf "$work_root"' EXIT
mkdir -p "$work_root/payload" "$work_root/download"

APP_ROOT="$work_root/app"
mkdir -p "$APP_ROOT/releases/test"
atomic_link "$APP_ROOT/releases/test" "$APP_ROOT/current"
if [[ "${OSTYPE:-}" == msys* || "${OSTYPE:-}" == cygwin* ]]; then
    [[ -e "$APP_ROOT/current" ]]
else
    [[ -L "$APP_ROOT/current" && "$(readlink -f "$APP_ROOT/current")" == "$(readlink -f "$APP_ROOT/releases/test")" ]] || {
        printf 'atomic_link did not create the expected symlink.\n' >&2
        exit 1
    }
fi

printf 'test payload\n' > "$work_root/payload/VERSION"
tar -C "$work_root/payload" -czf "$work_root/source.tar.gz" .

export LIGHTDOCS_ARCHIVE="$work_root/source.tar.gz"
export LIGHTDOCS_ARCHIVE_SHA256
LIGHTDOCS_ARCHIVE_SHA256="$(sha256sum "$LIGHTDOCS_ARCHIVE" | awk '{print $1}')"

archive="$(download_release latest "$work_root/download")"
expected="$work_root/download/lightdocs-release.tar.gz"

[[ "$archive" == "$expected" ]] || {
    printf 'download_release returned unexpected output: %q\n' "$archive" >&2
    exit 1
}
tar -tzf "$archive" >/dev/null
printf 'Native manager release-download test passed.\n'
