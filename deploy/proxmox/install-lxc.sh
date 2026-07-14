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

prompt_password() {
    local label="$1" minimum="${2:-1}" first second
    while true; do
        if command -v whiptail >/dev/null 2>&1 && [[ -t 1 ]]; then
            first="$(whiptail --title "Lightdocs LXC" --passwordbox "$label" 10 70 3>&1 1>&2 2>&3)" || exit 1
            second="$(whiptail --title "Lightdocs LXC" --passwordbox "Confirm $label" 10 70 3>&1 1>&2 2>&3)" || exit 1
        else
            read -r -s -p "$label: " first
            printf '\n' >&2
            read -r -s -p "Confirm $label: " second
            printf '\n' >&2
        fi
        if [[ ${#first} -ge minimum && "$first" == "$second" ]]; then
            printf '%s\n' "$first"
            return 0
        fi
        printf 'Passwords must match and contain at least %s characters. Try again.\n' "$minimum" >&2
    done
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
root_password="${LIGHTDOCS_ROOT_PASSWORD:-}"
console_mode="${LIGHTDOCS_CONSOLE_MODE:-}"
docs_name="${LIGHTDOCS_NAME:-Lightdocs}"
docs_tagline="${LIGHTDOCS_TAGLINE:-Documentation without the framework tax.}"
docs_base_url="${LIGHTDOCS_BASE_URL:-}"
admin_mode="${LIGHTDOCS_ADMIN_ENABLED:-enabled}"
admin_password="${LIGHTDOCS_ADMIN_PASSWORD:-}"
admin_password_mode="${LIGHTDOCS_ADMIN_PASSWORD_MODE:-}"
if [[ -z "$console_mode" ]]; then
    if [[ -n "$root_password" ]]; then console_mode="password"; else console_mode="autologin"; fi
fi
if [[ -z "$admin_password_mode" ]]; then
    if [[ -n "$admin_password" ]]; then admin_password_mode="password"; else admin_password_mode="generate"; fi
fi

if [[ -t 0 ]]; then
    ctid="$(prompt "Container ID" "$ctid")"
    hostname="$(prompt "Hostname" "$hostname")"
    cores="$(prompt "CPU cores" "$cores")"
    memory="$(prompt "Memory in MiB" "$memory")"
    disk="$(prompt "Root disk in GiB" "$disk")"
    network="$(prompt "IPv4 configuration (dhcp or CIDR)" "$network")"
    console_mode="$(prompt "Console access (autologin or password)" "$console_mode")"
    if [[ "$console_mode" == "password" && -z "$root_password" ]]; then
        root_password="$(prompt_password "LXC root password")"
    fi
    docs_name="$(prompt "Documentation site name" "$docs_name")"
    docs_tagline="$(prompt "Documentation site tagline" "$docs_tagline")"
    docs_base_url="$(prompt "Canonical external URL (optional)" "$docs_base_url")"
    admin_mode="$(prompt "Browser Content Studio (enabled or disabled)" "$admin_mode")"
    if [[ "$admin_mode" == "enabled" ]]; then
        admin_password_mode="$(prompt "Administrator password (generate or password)" "$admin_password_mode")"
        if [[ "$admin_password_mode" == "password" && -z "$admin_password" ]]; then
            admin_password="$(prompt_password "Lightdocs administrator password" 12)"
        fi
    fi
fi

[[ "$ctid" =~ ^[1-9][0-9]{2,8}$ ]] || { echo "Invalid CT ID: $ctid" >&2; exit 1; }
[[ "$hostname" =~ ^[A-Za-z0-9][A-Za-z0-9.-]{0,62}$ ]] || { echo "Invalid hostname." >&2; exit 1; }
for value in "$cores" "$memory" "$swap" "$disk"; do [[ "$value" =~ ^[0-9]+$ ]] || { echo "Numeric resource value required." >&2; exit 1; }; done
case "$console_mode" in
    autologin) root_password="" ;;
    password) [[ -n "$root_password" ]] || { echo "LIGHTDOCS_ROOT_PASSWORD is required when console mode is password." >&2; exit 1; } ;;
    *) echo "Console access must be autologin or password." >&2; exit 1 ;;
esac
[[ -n "$docs_name" && ${#docs_name} -le 80 ]] || { echo "Documentation site name must be 1-80 characters." >&2; exit 1; }
[[ -n "$docs_tagline" && ${#docs_tagline} -le 180 ]] || { echo "Documentation tagline must be 1-180 characters." >&2; exit 1; }
docs_base_url="${docs_base_url%/}"
if [[ -n "$docs_base_url" && ! "$docs_base_url" =~ ^https?://[^[:space:]]+$ ]]; then
    echo "Canonical external URL must be an absolute http:// or https:// URL." >&2
    exit 1
fi
case "$admin_mode" in
    enabled)
        case "$admin_password_mode" in
            generate) admin_password="" ;;
            password) [[ ${#admin_password} -ge 12 ]] || { echo "The administrator password must be at least 12 characters." >&2; exit 1; } ;;
            *) echo "Administrator password mode must be generate or password." >&2; exit 1 ;;
        esac
        ;;
    disabled) admin_password="" ;;
    *) echo "Browser Content Studio must be enabled or disabled." >&2; exit 1 ;;
esac
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
create_args=(
    --hostname "$hostname"
    --ostype debian
    --arch amd64
    --cores "$cores"
    --memory "$memory"
    --swap "$swap"
    --rootfs "$root_storage:$disk"
    --net0 "$net0"
    --unprivileged 1
    --onboot 1
    --start 1
)
[[ -z "$root_password" ]] || create_args+=(--password "$root_password")
pct create "$ctid" "$template" "${create_args[@]}"

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

if [[ "$console_mode" == "autologin" ]]; then
    # The inner shell must write a literal $TERM into the systemd override.
    # shellcheck disable=SC2016
    pct_exec bash -c 'install -d /etc/systemd/system/container-getty@1.service.d &&
        printf "%s\n" \
            "[Service]" \
            "ExecStart=" \
            "ExecStart=-/sbin/agetty --autologin root --noclear --keep-baud tty%I 115200,38400,9600 \$TERM" \
            > /etc/systemd/system/container-getty@1.service.d/override.conf &&
        systemctl daemon-reload &&
        systemctl restart container-getty@1.service'
fi

pct_exec curl --fail --silent --show-error --location "$raw_base/deploy/native/install.sh" --output /root/lightdocs-install.sh
if ! pct_exec \
    LIGHTDOCS_REPOSITORY="$repository" \
    LIGHTDOCS_REF="$ref" \
    LIGHTDOCS_VERSION="$version" \
    LIGHTDOCS_RAW_BASE_URL="$raw_base" \
    LIGHTDOCS_RELEASE_BASE_URL="$release_base" \
    LIGHTDOCS_NAME="$docs_name" \
    LIGHTDOCS_TAGLINE="$docs_tagline" \
    LIGHTDOCS_BASE_URL="$docs_base_url" \
    LIGHTDOCS_ADMIN_ENABLED="$admin_mode" \
    LIGHTDOCS_ADMIN_PASSWORD="$admin_password" \
    bash /root/lightdocs-install.sh; then
    echo "Installation failed inside CT $ctid. The container was preserved for diagnosis." >&2
    exit 1
fi
pct_exec rm -f /root/lightdocs-install.sh

address="$(pct_exec hostname -I | awk '{print $1}')"
echo
echo "Lightdocs CT $ctid is ready at http://${address:-unknown}/"
echo "Manage it with: pct exec $ctid -- lightdocs help"
echo "Site name: $docs_name"
echo "Canonical URL: ${docs_base_url:-not configured}"
echo "Content Studio: $admin_mode"
if [[ "$console_mode" == "autologin" ]]; then
    echo "Console access: root auto-login"
else
    echo "Console username: root (password configured during setup)"
fi
