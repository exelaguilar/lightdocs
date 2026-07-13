#!/usr/bin/env bash
set -euo pipefail

root="$(cd "$(dirname "${BASH_SOURCE[0]}")/../.." && pwd)"
version="${LIGHTDOCS_VERSION:-$(tr -d '[:space:]' < "$root/VERSION")}"
dist="${LIGHTDOCS_DIST_DIR:-$root/dist}"
stage="$(mktemp -d)"
static_stage="$(mktemp -d)"
trap 'rm -rf "$stage" "$static_stage"' EXIT

if [[ ! "$version" =~ ^[0-9]+\.[0-9]+\.[0-9]+([.-][A-Za-z0-9.-]+)?$ ]]; then
    echo "VERSION must be a semantic version; received: $version" >&2
    exit 1
fi
if ! command -v composer >/dev/null 2>&1 || ! command -v rsync >/dev/null 2>&1 || ! command -v tar >/dev/null 2>&1 || ! command -v zip >/dev/null 2>&1; then
    echo "composer, rsync, tar, and zip are required to build a release." >&2
    exit 1
fi

if [[ -e "$dist" ]]; then
    if [[ ! -f "$dist/.lightdocs-dist" ]]; then
        echo "Refusing to replace unmarked distribution directory: $dist" >&2
        exit 1
    fi
    rm -rf "$dist"
fi
mkdir -p "$dist"
printf 'Managed Lightdocs release output.\n' > "$dist/.lightdocs-dist"

rsync -a "$root/" "$stage/" \
    --exclude '/.git/' \
    --exclude '/.github/' \
    --exclude '/.claude/' \
    --exclude '/.codex/' \
    --exclude '/.env' \
    --exclude '/build*/' \
    --exclude '/content/' \
    --exclude '/dist/' \
    --exclude '/public/uploads/' \
    --exclude '/site/' \
    --exclude '/tests/' \
    --exclude '/var/' \
    --exclude '/vendor/'

mkdir -p "$stage/content" "$stage/public/uploads" "$stage/var/cache" "$stage/var/revisions" "$stage/var/exports"
cp -a "$root/resources/starter-site/content/." "$stage/content/"
cp -a "$root/resources/starter-site/public/uploads/." "$stage/public/uploads/"
printf '%s\n' "$version" > "$stage/VERSION"
chmod 0755 "$stage/bin/docs" "$stage/deploy/docker/entrypoint.sh" "$stage/deploy/docker/install.sh" "$stage/deploy/native/install.sh" "$stage/deploy/native/lightdocs" "$stage/deploy/proxmox/install-lxc.sh" "$stage/deploy/release/build.sh"

composer install --working-dir="$stage" --no-dev --no-interaction --no-progress --prefer-dist --classmap-authoritative
php "$stage/bin/docs" doctor
php "$stage/bin/docs" validate
rm -f "$stage/var/lightdocs.sqlite" "$stage/var/lightdocs.sqlite-shm" "$stage/var/lightdocs.sqlite-wal"
rm -rf "$stage/var/cache/"*

archive="$dist/lightdocs-release.tar.gz"
versioned="$dist/lightdocs-v$version.tar.gz"
tar --sort=name --owner=0 --group=0 --numeric-owner --mtime="@${SOURCE_DATE_EPOCH:-0}" -C "$stage" -czf "$archive" .
cp "$archive" "$versioned"

php "$stage/bin/docs" build "$static_stage/site" --profile=public
static_archive="$dist/lightdocs-static-public.zip"
static_versioned="$dist/lightdocs-static-public-v$version.zip"
(cd "$static_stage/site" && zip -q -r "$static_archive" .)
cp "$static_archive" "$static_versioned"
(
    cd "$dist"
    sha256sum "$(basename "$archive")" > "$(basename "$archive").sha256"
    sha256sum "$(basename "$versioned")" > "$(basename "$versioned").sha256"
    sha256sum "$(basename "$static_archive")" > "$(basename "$static_archive").sha256"
    sha256sum "$(basename "$static_versioned")" > "$(basename "$static_versioned").sha256"
)

echo "Built Lightdocs $version release assets in $dist"
