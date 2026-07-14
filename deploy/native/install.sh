#!/usr/bin/env bash
set -euo pipefail

set_command_path() {
    export PATH="/usr/local/sbin:/usr/local/bin:/usr/sbin:/usr/bin:/sbin:/bin"
}

dotenv_quote() {
    local value="${1-}"
    value="${value//\\/\\\\}"
    value="${value//\"/\\\"}"
    value="${value//$'\r'/\\r}"
    value="${value//$'\n'/\\n}"
    value="${value//\$/\\\$}"
    printf '"%s"' "$value"
}

repair_legacy_env() {
    local env_file="$1"
    if grep -Fxq 'DOCS_TAGLINE=Documentation without the framework tax.' "$env_file"; then
        sed -i 's/^DOCS_TAGLINE=Documentation without the framework tax\.$/DOCS_TAGLINE="Documentation without the framework tax."/' "$env_file"
        echo "Repaired the legacy unquoted DOCS_TAGLINE setting."
    fi
}

main() {
    [[ $EUID -eq 0 ]] || { echo "Run this installer as root." >&2; exit 1; }
    set_command_path

    local repository="${LIGHTDOCS_REPOSITORY:-exelaguilar/lightdocs}"
    local ref="${LIGHTDOCS_REF:-main}"
    local raw_base="${LIGHTDOCS_RAW_BASE_URL:-https://raw.githubusercontent.com/$repository/$ref}"
    local requested_version="${LIGHTDOCS_VERSION:-latest}"
    local generated_password=""

    source /etc/os-release
    if [[ "${ID:-}" != "debian" || "${VERSION_ID:-}" != "13" ]]; then
        echo "The native installer currently requires Debian 13." >&2
        exit 1
    fi

    export DEBIAN_FRONTEND=noninteractive
    apt-get update
    apt-get install -y --no-install-recommends \
        ca-certificates curl git nginx openssl rsync tar \
        php8.4-cli php8.4-curl php8.4-fpm php8.4-mbstring php8.4-sqlite3 php8.4-xml php8.4-zip

    mkdir -p /etc/lightdocs /opt/lightdocs/releases /var/lib/lightdocs/content /var/lib/lightdocs/public/uploads /var/lib/lightdocs/var /var/backups/lightdocs

    if [[ ! -f /etc/lightdocs/lightdocs.env ]]; then
        generated_password="${LIGHTDOCS_ADMIN_PASSWORD:-$(openssl rand -hex 24)}"
        umask 077
        {
            printf 'APP_ENV=%s\n' "$(dotenv_quote production)"
            printf 'DOCS_NAME=%s\n' "$(dotenv_quote "${LIGHTDOCS_NAME:-Lightdocs}")"
            printf 'DOCS_TAGLINE=%s\n' "$(dotenv_quote 'Documentation without the framework tax.')"
            printf 'DOCS_BASE_URL=%s\n' "$(dotenv_quote "${LIGHTDOCS_BASE_URL:-}")"
            printf 'DOCS_ADMIN_PASSWORD=%s\n' "$(dotenv_quote "$generated_password")"
        } > /etc/lightdocs/lightdocs.env
        umask 022
    else
        repair_legacy_env /etc/lightdocs/lightdocs.env
    fi
    chown root:www-data /etc/lightdocs/lightdocs.env
    chown root:www-data /etc/lightdocs
    chmod 0770 /etc/lightdocs
    chmod 0660 /etc/lightdocs/lightdocs.env

    {
        printf 'REPOSITORY=%q\n' "$repository"
        printf 'LIGHTDOCS_RELEASE_BASE_URL=%q\n' "${LIGHTDOCS_RELEASE_BASE_URL:-https://github.com/$repository/releases}"
    } > /etc/lightdocs/installer.conf
    chmod 0644 /etc/lightdocs/installer.conf

    if [[ -n "${LIGHTDOCS_MANAGER_PATH:-}" ]]; then
        install -m 0755 "$LIGHTDOCS_MANAGER_PATH" /usr/local/sbin/lightdocs
    else
        curl --fail --silent --show-error --location "$raw_base/deploy/native/lightdocs" --output /usr/local/sbin/lightdocs
        chmod 0755 /usr/local/sbin/lightdocs
    fi

    rm -f /etc/nginx/sites-enabled/default
    /usr/local/sbin/lightdocs update "$requested_version"

    systemctl enable php8.4-fpm nginx
    /usr/sbin/nginx -t
    systemctl restart php8.4-fpm nginx
    /usr/local/sbin/lightdocs doctor

    local address
    address="$(hostname -I | awk '{print $1}')"
    echo
    echo "Lightdocs installation completed."
    echo "Open: http://${address:-127.0.0.1}/"
    if [[ -n "$generated_password" ]]; then
        echo "Administrator password: $generated_password"
        echo "The credential is stored in /etc/lightdocs/lightdocs.env."
    fi
}

if [[ "${LIGHTDOCS_INSTALLER_TEST_MODE:-0}" == "1" ]]; then
    exit 0
fi

if [[ "${BASH_SOURCE[0]:-$0}" == "$0" ]]; then
    main "$@"
fi
