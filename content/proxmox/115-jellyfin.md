---
title: "CT 115 - Jellyfin"
description: "Current Proxmox configuration and operating baseline for Jellyfin."
order: 115
visibility: private
nav: true
type: reference
reviewed: 2026-07-13
review_after: 90
keywords: [proxmox, lxc, inventory, jellyfin]
service:
  type: lxc
  id: 115
  application: "Jellyfin"
  address: "192.168.1.198"
  installation: helper-script
  context: "Proxmox host + CT 115"
---

# CT 115 - Jellyfin

Provides the Jellyfin media server with shared media storage and Intel GPU devices.

:::banner type="info"
Inventory captured from Proxmox VE 9.1.9 on 2026-07-13. Runtime status and DHCP addresses can change between syncs.
:::

## Application runtime

| Setting | Observed value |
| --- | --- |
| Runtime captured | `2026-07-13` |
| Application version | Jellyfin.Server 12.0.0.0; package build 2026062906+ubu2404 |

### Services

| Unit | State | Role |
| --- | --- | --- |
| `jellyfin.service` | active | Media server |

### Listening ports

| Listener | Scope | Purpose |
| --- | --- | --- |
| `8096/tcp` | All interfaces | Jellyfin HTTP |

## Data and recovery

### Important paths

- `/var/lib/jellyfin/data/jellyfin.db`
- `/var/lib/jellyfin/metadata`
- `/etc/jellyfin`
- `/media_storage (bind mount)`

### Backup procedure

- Stop jellyfin.service or use application-consistent SQLite backups before copying /var/lib/jellyfin.
- Back up /etc/jellyfin with the application database and metadata.
- Back up /mnt/pve/media_storage separately; Proxmox vzdump does not include bind-mount contents.
- Reference: [Proxmox container bind-mount backup behavior](https://pve.proxmox.com/pve-docs-9-beta/pct.1.html).

### Observed state

- Jellyfin data uses approximately 760 MiB.
- Intel render and card devices are mapped for hardware transcoding; preserve and revalidate the documented GIDs after restore.

## Proxmox configuration

| Setting | Value |
| --- | --- |
| Node | `exel` |
| Status at capture | **running** |
| Hostname | `jellyfin` |
| OS / architecture | `ubuntu` / `amd64` |
| CPU | 2 cores |
| Memory / swap | 2048 MiB / 512 MiB |
| Unprivileged | Yes |
| Start at boot | Yes |
| Tags | `community-script;media` |
| Features | `nesting=1,keyctl=1` |
| Time zone | `America/Chicago` |

## Network

| Setting | Value |
| --- | --- |
| Current IPv4 | `192.168.1.198` |
| Configured address | `192.168.1.198/24` |
| Gateway | `192.168.1.1` |
| Bridge | `vmbr0` |
| MAC address | `BC:24:11:41:D4:27` |
| Proxmox firewall flag | Not enabled on `net0` |

:::command context="Proxmox host" risk="normal"
```text
name=eth0,bridge=vmbr0,gw=192.168.1.1,hwaddr=BC:24:11:41:D4:27,ip=192.168.1.198/24,type=veth
```
:::

## Storage and devices

- Root filesystem: `local-lvm:vm-115-disk-1,size=16G`
- Mount `mp0`: `/mnt/pve/media_storage,mp=/media_storage`
- Device `dev0`: `/dev/dri/renderD128,gid=993`
- Device `dev1`: `/dev/dri/card0,gid=44`

## Infrastructure relationships

- Shared storage: host path `/mnt/pve/media_storage` is mounted at `/media_storage`. Other current consumers are [Plex (CT 102)](/proxmox/102-plex), [qBittorrent (CT 104)](/proxmox/104-qbittorrent), [Immich (CT 107)](/proxmox/107-immich), [Radarr (CT 108)](/proxmox/108-radarr), [SABnzbd (CT 116)](/proxmox/116-sabnzbd), [Bazarr (CT 117)](/proxmox/117-bazarr), [Lidarr (CT 119)](/proxmox/119-lidarr).
- Intel GPU: `/dev/dri/renderD128` and `/dev/dri/card0` are mapped into this LXC. Numeric GIDs are container-specific and must be revalidated after restore. Other current GPU consumers are [Plex (CT 102)](/proxmox/102-plex), [Immich (CT 107)](/proxmox/107-immich), [Open WebUI (CT 114)](/proxmox/114-openwebui), [Ollama (CT 120)](/proxmox/120-ollama).

## Routine checks

:::command context="Proxmox host" risk="normal"
```bash
pct status 115
pct config 115
pct exec 115 -- systemctl --failed
pct exec 115 -- systemctl status jellyfin.service --no-pager
```
:::

- [ ] Confirm the expected application service is active inside the container.
- [ ] Verify the application from a client on the intended network.
- [ ] Confirm the latest scheduled backup completed successfully.
- [ ] Review recent application and system logs before making changes.

