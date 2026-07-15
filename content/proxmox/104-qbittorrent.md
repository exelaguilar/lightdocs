---
title: "CT 104 - qBittorrent"
description: "Current Proxmox configuration and operating baseline for qBittorrent."
order: 104
visibility: private
nav: true
type: reference
reviewed: 2026-07-13
review_after: 90
keywords: [proxmox, lxc, inventory, qbittorrent]
service:
  type: lxc
  id: 104
  application: "qBittorrent"
  address: "192.168.1.104"
  installation: helper-script
  context: "Proxmox host + CT 104"
---

# CT 104 - qBittorrent

Provides torrent downloads through the dedicated VPN gateway at 192.168.1.249.

:::banner type="info"
Inventory captured from Proxmox VE 9.1.9 on 2026-07-13. Runtime status and DHCP addresses can change between syncs.
:::

## Application runtime

| Setting | Observed value |
| --- | --- |
| Runtime captured | `2026-07-13` |
| Application version | qBittorrent v5.2.2 |

### Services

| Unit | State | Role |
| --- | --- | --- |
| `qbittorrent-nox.service` | active | Torrent client |

### Listening ports

| Listener | Scope | Purpose |
| --- | --- | --- |
| `8090/tcp` | All interfaces | qBittorrent Web UI |
| `16304/tcp+udp` | 192.168.1.104 | Torrent peer traffic |

## Data and recovery

### Important paths

- `/root/.config/qBittorrent/qBittorrent.conf`
- `/root/.config/qBittorrent/qBittorrent-data.conf`
- `/media_storage (bind mount)`

### Backup procedure

- Back up the LXC root filesystem to preserve qBittorrent configuration.
- Back up /mnt/pve/media_storage separately; Proxmox vzdump does not include bind-mount contents.
- Stop qbittorrent-nox.service before copying its configuration so queue and resume data are flushed.
- Reference: [Proxmox container bind-mount backup behavior](https://pve.proxmox.com/pve-docs-9-beta/pct.1.html).

### Observed state

- The default route is CT 112 at 192.168.1.249; loss of that gateway should stop forwarded traffic because CT 112 has a default-drop forwarding policy.
- The current Proxmox search-domain value is 1.1.1.1 and appears to belong in the nameserver field instead.

## Proxmox configuration

| Setting | Value |
| --- | --- |
| Node | `exel` |
| Status at capture | **running** |
| Hostname | `qbittorrent` |
| OS / architecture | `debian` / `amd64` |
| CPU | 2 cores |
| Memory / swap | 2048 MiB / 512 MiB |
| Unprivileged | Yes |
| Start at boot | Yes |
| Tags | `community-script;torrent` |
| Features | `keyctl=1,nesting=1` |

## Network

| Setting | Value |
| --- | --- |
| Current IPv4 | `192.168.1.104` |
| Configured address | `192.168.1.104/24` |
| Gateway | `192.168.1.249` |
| Bridge | `vmbr0` |
| MAC address | `BC:24:11:A6:B7:AD` |
| Proxmox firewall flag | Not enabled on `net0` |
| Search domain | `1.1.1.1` |

:::command context="Proxmox host" risk="normal"
```text
name=eth0,bridge=vmbr0,gw=192.168.1.249,hwaddr=BC:24:11:A6:B7:AD,ip=192.168.1.104/24,type=veth
```
:::

:::banner type="warning"
The configured search domain looks like an IPv4 DNS resolver. Verify whether this value belongs in the Proxmox `nameserver` field instead.
:::

## Storage and devices

- Root filesystem: `local-lvm:vm-104-disk-0,size=8G`
- Mount `mp0`: `/mnt/pve/media_storage,mp=/media_storage`
- Device mappings: none configured

## Infrastructure relationships

- Shared storage: host path `/mnt/pve/media_storage` is mounted at `/media_storage`. Other current consumers are [Plex (CT 102)](/proxmox/102-plex), [Immich (CT 107)](/proxmox/107-immich), [Radarr (CT 108)](/proxmox/108-radarr), [Jellyfin (CT 115)](/proxmox/115-jellyfin), [SABnzbd (CT 116)](/proxmox/116-sabnzbd), [Bazarr (CT 117)](/proxmox/117-bazarr), [Lidarr (CT 119)](/proxmox/119-lidarr).
- Default-route dependency: this LXC uses [VPN Torrent Gateway (CT 112)](/proxmox/112-vpn-torrent) at `192.168.1.249` instead of the LAN router.

## Routine checks

:::command context="Proxmox host" risk="normal"
```bash
pct status 104
pct config 104
pct exec 104 -- systemctl --failed
pct exec 104 -- systemctl status qbittorrent-nox.service --no-pager
```
:::

- [ ] Confirm the expected application service is active inside the container.
- [ ] Verify the application from a client on the intended network.
- [ ] Confirm the latest scheduled backup completed successfully.
- [ ] Review recent application and system logs before making changes.

## Related runbook

See [qBittorrent operational guidance](/proxmox/vpn-gateways).

