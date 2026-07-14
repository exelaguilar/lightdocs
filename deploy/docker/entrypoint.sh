#!/usr/bin/env sh
set -eu

site_dir="${LIGHTDOCS_SITE_DIR:-/var/lib/lightdocs}"
state_dir="${LIGHTDOCS_STATE_DIR:-$site_dir/storage}"
env_file="${LIGHTDOCS_ENV_FILE:-$site_dir/lightdocs.env}"

mkdir -p "$site_dir/content" "$state_dir/uploads" "$state_dir/cache" "$state_dir/revisions" "$state_dir/exports"

if [ ! -f "$site_dir/content/index.md" ]; then
    cp -R /usr/share/lightdocs/starter-site/content/. "$site_dir/content/"
fi
if [ ! -f "$site_dir/.gitignore" ]; then
    cp /usr/share/lightdocs/starter-site/.gitignore "$site_dir/.gitignore"
fi
if [ ! -f "$state_dir/uploads/.gitkeep" ]; then
    cp /usr/share/lightdocs/starter-site/public/uploads/.gitkeep "$state_dir/uploads/.gitkeep"
fi

if [ ! -f "$env_file" ]; then
    umask 077
    {
        printf 'APP_ENV=production\n'
        printf 'DOCS_NAME=%s\n' "${LIGHTDOCS_BOOTSTRAP_NAME:-Lightdocs}"
        printf 'DOCS_TAGLINE=%s\n' "${LIGHTDOCS_BOOTSTRAP_TAGLINE:-Documentation without the framework tax.}"
        printf 'DOCS_BASE_URL=%s\n' "${LIGHTDOCS_BOOTSTRAP_BASE_URL:-}"
        printf 'DOCS_ADMIN_PASSWORD=%s\n' "${LIGHTDOCS_BOOTSTRAP_ADMIN_PASSWORD:-}"
    } > "$env_file"
fi

chown -R www-data:www-data "$site_dir"
chmod 0600 "$env_file"

unset LIGHTDOCS_BOOTSTRAP_NAME LIGHTDOCS_BOOTSTRAP_TAGLINE LIGHTDOCS_BOOTSTRAP_BASE_URL LIGHTDOCS_BOOTSTRAP_ADMIN_PASSWORD

exec "$@"
