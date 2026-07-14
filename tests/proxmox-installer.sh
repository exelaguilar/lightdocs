#!/usr/bin/env bash
set -euo pipefail

root="$(cd "$(dirname "${BASH_SOURCE[0]}")/.." && pwd)"
installer="$root/deploy/proxmox/install-lxc.sh"

bootstrap_line="$(grep -nF 'pct_exec DEBIAN_FRONTEND=noninteractive apt-get install -y --no-install-recommends ca-certificates curl' "$installer" | cut -d: -f1 || true)"
download_line="$(grep -nF 'pct_exec curl --fail --silent --show-error --location' "$installer" | cut -d: -f1 || true)"

[[ -n "$bootstrap_line" && -n "$download_line" && "$bootstrap_line" -lt "$download_line" ]] || {
    printf 'The Proxmox helper must install curl before downloading the native installer.\n' >&2
    exit 1
}

# shellcheck disable=SC2016
grep -Fq 'pct exec "$ctid" -- env LC_ALL=C LANG=C "$@"' "$installer"
printf 'Proxmox installer bootstrap-order test passed.\n'
