#!/usr/bin/env bash
set -euo pipefail

[[ $EUID -eq 0 ]] || { echo "Run this helper from the Proxmox VE shell as root." >&2; exit 1; }
for command in pct pveam pvesh pvesm curl; do
    command -v "$command" >/dev/null 2>&1 || { echo "Missing Proxmox command: $command" >&2; exit 1; }
done

repository="${LIGHTDOCS_REPOSITORY:-exelaguilar/lightdocs}"
ref="${LIGHTDOCS_REF:-main}"
raw_base="${LIGHTDOCS_RAW_BASE_URL:-https://raw.githubusercontent.com/$repository/$ref}"
version="${LIGHTDOCS_VERSION:-latest}"
release_base="${LIGHTDOCS_RELEASE_BASE_URL:-https://github.com/$repository/releases}"

pct_exec() {
    pct exec "$ctid" -- env \
        LC_ALL=C \
        LANG=C \
        PATH=/usr/local/sbin:/usr/local/bin:/usr/sbin:/usr/bin:/sbin:/bin \
        "$@"
}

prompt() {
    local label="$1" default="$2" result
    if command -v whiptail >/dev/null 2>&1 && [[ -t 1 ]]; then
        result="$(whiptail --title "Lightdocs LXC" --inputbox "$label" 10 70 "$default" 3>&1 1>&2 2>&3)" || exit 1
    else
        read -r -p "$label [$default]: " result
        result="${result:-$default}"
    fi
    printf '%s\n' "$result"
}

choose_storage() {
    local content="$1" default choices result
    mapfile -t choices < <(pvesm status -content "$content" --enabled 1 | awk 'NR > 1 {print $1}')
    [[ ${#choices[@]} -gt 0 ]] || { echo "No enabled Proxmox storage supports $content." >&2; exit 1; }
    default="${choices[0]}"
    if command -v whiptail >/dev/null 2>&1 && [[ -t 1 ]]; then
        local menu=() item
        for item in "${choices[@]}"; do menu+=("$item" "$content storage"); done
        result="$(whiptail --title "Lightdocs LXC" --menu "Choose $content storage" 18 70 10 "${menu[@]}" 3>&1 1>&2 2>&3)" || exit 1
    else
        result="$(prompt "Storage for $content (${choices[*]})" "$default")"
    fi
    printf '%s\n' "$result"
}

ctid="${LIGHTDOCS_CTID:-$(pvesh get /cluster/nextid)}"
hostname="${LIGHTDOCS_HOSTNAME:-lightdocs}"
cores="${LIGHTDOCS_CORES:-1}"
memory="${LIGHTDOCS_MEMORY:-1024}"
swap="${LIGHTDOCS_SWAP:-512}"
disk="${LIGHTDOCS_DISK_GB:-8}"
bridge="${LIGHTDOCS_BRIDGE:-vmbr0}"
network="${LIGHTDOCS_NETWORK:-dhcp}"

if [[ -t 0 ]]; then
    ctid="$(prompt "Container ID" "$ctid")"
    hostname="$(prompt "Hostname" "$hostname")"
    cores="$(prompt "CPU cores" "$cores")"
    memory="$(prompt "Memory in MiB" "$memory")"
    disk="$(prompt "Root disk in GiB" "$disk")"
    network="$(prompt "IPv4 configuration (dhcp or CIDR)" "$network")"
fi

[[ "$ctid" =~ ^[1-9][0-9]{2,8}$ ]] || { echo "Invalid CT ID: $ctid" >&2; exit 1; }
[[ "$hostname" =~ ^[A-Za-z0-9][A-Za-z0-9.-]{0,62}$ ]] || { echo "Invalid hostname." >&2; exit 1; }
for value in "$cores" "$memory" "$swap" "$disk"; do [[ "$value" =~ ^[0-9]+$ ]] || { echo "Numeric resource value required." >&2; exit 1; }; done
if pct status "$ctid" >/dev/null 2>&1; then echo "CT $ctid already exists." >&2; exit 1; fi

root_storage="${LIGHTDOCS_ROOT_STORAGE:-$(choose_storage rootdir)}"
template_storage="${LIGHTDOCS_TEMPLATE_STORAGE:-$(choose_storage vztmpl)}"

template="$(pveam list "$template_storage" | awk '/debian-13-standard/ {print $1}' | tail -n 1)"
if [[ -z "$template" ]]; then
    template_name="$(pveam available --section system | awk '/debian-13-standard/ {print $2}' | tail -n 1)"
    [[ -n "$template_name" ]] || { echo "No Debian 13 standard template is available." >&2; exit 1; }
    echo "Downloading $template_name to $template_storage..."
    pveam download "$template_storage" "$template_name"
    template="$template_storage:vztmpl/$template_name"
fi

gateway=""
if [[ "$network" != "dhcp" ]]; then
    [[ "$network" == */* ]] || { echo "Static networking must use CIDR notation." >&2; exit 1; }
    detected_gateway="$(ip route show default | awk 'NR == 1 {print $3}')"
    gateway="${LIGHTDOCS_GATEWAY:-$(prompt "IPv4 gateway" "$detected_gateway")}"
    [[ -n "$gateway" ]] || { echo "A gateway is required for static networking." >&2; exit 1; }
fi
net0="name=eth0,bridge=$bridge,ip=$network,type=veth"
[[ -z "$gateway" ]] || net0+=",gw=$gateway"

echo "Creating unprivileged Debian 13 CT $ctid..."
pct create "$ctid" "$template" \
    --hostname "$hostname" \
    --ostype debian \
    --arch amd64 \
    --cores "$cores" \
    --memory "$memory" \
    --swap "$swap" \
    --rootfs "$root_storage:$disk" \
    --net0 "$net0" \
    --unprivileged 1 \
    --onboot 1 \
    --start 1

echo "Waiting for networking inside CT $ctid..."
ready=0
for _ in {1..60}; do
    if pct_exec bash -c 'ip route | grep -q default && getent hosts raw.githubusercontent.com >/dev/null'; then ready=1; break; fi
    sleep 2
done
[[ $ready -eq 1 ]] || { echo "CT $ctid was created, but networking did not become ready. It was left running for diagnosis." >&2; exit 1; }

echo "Installing bootstrap prerequisites inside CT $ctid..."
pct_exec DEBIAN_FRONTEND=noninteractive apt-get update
pct_exec DEBIAN_FRONTEND=noninteractive apt-get install -y --no-install-recommends ca-certificates curl

pct_exec curl --fail --silent --show-error --location "$raw_base/deploy/native/install.sh" --output /root/lightdocs-install.sh
if ! pct_exec \
    LIGHTDOCS_REPOSITORY="$repository" \
    LIGHTDOCS_REF="$ref" \
    LIGHTDOCS_VERSION="$version" \
    LIGHTDOCS_RAW_BASE_URL="$raw_base" \
    LIGHTDOCS_RELEASE_BASE_URL="$release_base" \
    bash /root/lightdocs-install.sh; then
    echo "Installation failed inside CT $ctid. The container was preserved for diagnosis." >&2
    exit 1
fi
pct_exec rm -f /root/lightdocs-install.sh

address="$(pct_exec hostname -I | awk '{print $1}')"
echo
echo "Lightdocs CT $ctid is ready at http://${address:-unknown}/"
echo "Manage it with: pct exec $ctid -- lightdocs help"
