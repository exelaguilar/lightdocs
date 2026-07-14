#!/usr/bin/env bash
set -euo pipefail
# Shell/PHP fixtures intentionally use single-quoted dollar signs.

root="$(cd "$(dirname "${BASH_SOURCE[0]}")/.." && pwd)"
php_bin="${PHP_BIN:-php}"

installer_text="$(< "$root/deploy/native/install.sh")"
LIGHTDOCS_INSTALLER_TEST_MODE=1 bash -u -c "$installer_text"

# shellcheck source=deploy/native/install.sh
source "$root/deploy/native/install.sh"

original_path="$PATH"
PATH="/usr/bin:/bin"
set_command_path
[[ ":$PATH:" == *:/usr/local/sbin:* && ":$PATH:" == *:/usr/sbin:* ]] || {
    printf 'Native installer did not restore the required system command paths.\n' >&2
    exit 1
}
PATH="$original_path"

work="$(mktemp -d)"
trap 'rm -rf "$work"' EXIT

# shellcheck disable=SC2016
name='My $Docs "Home" \\ Rack'
tagline='Documentation without the framework tax.'
password='dollar$ quote" slash\\ value'

{
    printf 'APP_ENV=%s\n' "$(dotenv_quote production)"
    printf 'DOCS_NAME=%s\n' "$(dotenv_quote "$name")"
    printf 'DOCS_TAGLINE=%s\n' "$(dotenv_quote "$tagline")"
    printf 'DOCS_BASE_URL=%s\n' "$(dotenv_quote 'https://docs.example.com')"
    printf 'DOCS_ADMIN_PASSWORD=%s\n' "$(dotenv_quote "$password")"
} > "$work/.env"

# shellcheck disable=SC2016
"$php_bin" -r '
require $argv[1] . "/vendor/autoload.php";
Dotenv\Dotenv::createImmutable($argv[2])->load();
$expected = [
    "DOCS_NAME" => $argv[3],
    "DOCS_TAGLINE" => $argv[4],
    "DOCS_ADMIN_PASSWORD" => $argv[5],
];
foreach ($expected as $key => $value) {
    if (($_ENV[$key] ?? null) !== $value) {
        fwrite(STDERR, "$key did not survive dotenv encoding.\n");
        exit(1);
    }
}
' "$root" "$work" "$name" "$tagline" "$password"

printf '%s\n' \
    'APP_ENV=production' \
    'DOCS_NAME=Lightdocs' \
    'DOCS_TAGLINE=Documentation without the framework tax.' \
    'DOCS_BASE_URL=' \
    'DOCS_ADMIN_PASSWORD=test-password' \
    > "$work/legacy.env"

repair_legacy_env "$work/legacy.env"
grep -Fxq 'DOCS_TAGLINE="Documentation without the framework tax."' "$work/legacy.env"
# shellcheck disable=SC2016
"$php_bin" -r '
require $argv[1] . "/vendor/autoload.php";
Dotenv\Dotenv::createImmutable($argv[2], "legacy.env")->load();
if (($_ENV["DOCS_TAGLINE"] ?? null) !== "Documentation without the framework tax.") {
    fwrite(STDERR, "Legacy DOCS_TAGLINE repair did not parse correctly.\n");
    exit(1);
}
' "$root" "$work"

printf 'Native installer dotenv test passed.\n'
