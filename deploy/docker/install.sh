#!/usr/bin/env sh
set -eu

repository="${LIGHTDOCS_REPOSITORY:-exelaguilar/lightdocs}"
ref="${LIGHTDOCS_REF:-main}"
install_dir="${LIGHTDOCS_INSTALL_DIR:-$PWD/lightdocs}"
raw_base="${LIGHTDOCS_RAW_BASE_URL:-https://raw.githubusercontent.com/$repository/$ref}"

if ! command -v docker >/dev/null 2>&1; then
    echo "Docker is required before running this installer." >&2
    exit 1
fi
if ! docker compose version >/dev/null 2>&1; then
    echo "The Docker Compose plugin is required." >&2
    exit 1
fi

mkdir -p "$install_dir"
cd "$install_dir"

if [ ! -f compose.yaml ]; then
    curl --fail --silent --show-error --location "$raw_base/compose.yaml" --output compose.yaml
fi

generated_password=""
if [ ! -f .env ]; then
    if command -v openssl >/dev/null 2>&1; then
        generated_password="$(openssl rand -hex 24)"
    else
        generated_password="$(od -An -N24 -tx1 /dev/urandom | tr -d ' \n')"
    fi
    umask 077
    {
        printf 'LIGHTDOCS_IMAGE=%s\n' "${LIGHTDOCS_IMAGE:-ghcr.io/$repository:latest}"
        printf 'LIGHTDOCS_PORT=%s\n' "${LIGHTDOCS_PORT:-8080}"
        printf 'LIGHTDOCS_NAME=Lightdocs\n'
        printf 'LIGHTDOCS_BASE_URL=%s\n' "${LIGHTDOCS_BASE_URL:-}"
        printf 'LIGHTDOCS_ADMIN_PASSWORD=%s\n' "$generated_password"
    } > .env
fi

docker compose pull
docker compose up -d

port="$(sed -n 's/^LIGHTDOCS_PORT=//p' .env | tail -n 1)"
port="${port:-8080}"
echo
echo "Lightdocs is starting at http://127.0.0.1:$port"
if [ -n "$generated_password" ]; then
    echo "Generated administrator password: $generated_password"
    echo "It is stored in $install_dir/.env; protect that file."
fi
echo "Run 'docker compose ps' to watch the health check."
