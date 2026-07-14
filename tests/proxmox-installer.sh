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

grep -Fq 'PATH=/usr/local/sbin:/usr/local/bin:/usr/sbin:/usr/bin:/sbin:/bin' "$installer"
grep -Fq 'agetty --autologin root' "$installer"
grep -Fq 'create_args+=(--password' "$installer"
grep -Fq 'Canonical external URL (optional)' "$installer"
grep -Fq 'LIGHTDOCS_ADMIN_ENABLED=' "$installer"
grep -Fq 'LIGHTDOCS_ADMIN_PASSWORD=' "$installer"
printf 'Proxmox installer bootstrap-order test passed.\n'
