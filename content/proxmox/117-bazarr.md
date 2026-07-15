---
title: "CT 117 - Bazarr"
description: "Current Proxmox configuration and operating baseline for Bazarr."
order: 117
visibility: private
nav: true
type: reference
reviewed: 2026-07-13
review_after: 90
keywords: [proxmox, lxc, inventory, bazarr]
service:
  type: lxc
  id: 117
  application: "Bazarr"
  address: "192.168.1.117"
  installation: helper-script
  context: "Proxmox host + CT 117"
---

# CT 117 - Bazarr

Manages subtitles for the media library and reads shared media storage.

:::banner type="info"
Inventory captured from Proxmox VE 9.1.9 on 2026-07-13. Runtime status and DHCP addresses can change between syncs.
:::

## Application runtime

| Setting | Observed value |
| --- | --- |
| Runtime captured | `2026-07-13` |
| Application version | Bazarr release not embedded in the installed source tree |

### Services

| Unit | State | Role |
| --- | --- | --- |
| `bazarr.service` | active | Subtitle management |

### Listening ports

| Listener | Scope | Purpose |
| --- | --- | --- |
| `6767/tcp` | IPv4 and IPv6 all interfaces | Bazarr web/API |

## Data and recovery

### Important paths

- `/opt/bazarr/data/config/config.yaml`
- `/opt/bazarr/data/db/bazarr.db`
- `/media_storage (bind mount)`

### Backup procedure

- Use Bazarr's application backup or stop bazarr.service before copying /opt/bazarr/data.
- Back up /mnt/pve/media_storage separately; Proxmox vzdump does not include bind-mount contents.
- Reference: [Proxmox container bind-mount backup behavior](https://pve.proxmox.com/pve-docs-9-beta/pct.1.html).

### Observed state

- The Bazarr tree uses approximately 396 MiB.
- The installed build does not expose a reliable release string through its source tree or service environment; record the UI-reported version during the next manual review.

## Proxmox configuration

| Setting | Value |
| --- | --- |
| Node | `exel` |
| Status at capture | **running** |
| Hostname | `bazarr` |
| OS / architecture | `debian` / `amd64` |
| CPU | 2 cores |
| Memory / swap | 1024 MiB / 512 MiB |
| Unprivileged | Yes |
| Start at boot | Yes |
| Tags | `arr;community-script` |
| Features | `nesting=1,keyctl=1` |
| Time zone | `America/Chicago` |

## Network

| Setting | Value |
| --- | --- |
| Current IPv4 | `192.168.1.117` |
| Configured address | `192.168.1.117/24` |
| Gateway | `192.168.1.1` |
| Bridge | `vmbr0` |
| MAC address | `BC:24:11:7B:AE:1F` |
| Proxmox firewall flag | Not enabled on `net0` |

:::command context="Proxmox host" risk="normal"
```text
name=eth0,bridge=vmbr0,gw=192.168.1.1,hwaddr=BC:24:11:7B:AE:1F,ip=192.168.1.117/24,type=veth
```
:::

## Storage and devices

- Root filesystem: `local-lvm:vm-117-disk-0,size=4G`
- Mount `mp0`: `/mnt/pve/media_storage,mp=/media_storage`
- Device mappings: none configured

## Infrastructure relationships

- Shared storage: host path `/mnt/pve/media_storage` is mounted at `/media_storage`. Other current consumers are [Plex (CT 102)](/proxmox/102-plex), [qBittorrent (CT 104)](/proxmox/104-qbittorrent), [Immich (CT 107)](/proxmox/107-immich), [Radarr (CT 108)](/proxmox/108-radarr), [Jellyfin (CT 115)](/proxmox/115-jellyfin), [SABnzbd (CT 116)](/proxmox/116-sabnzbd), [Lidarr (CT 119)](/proxmox/119-lidarr).

## Routine checks

:::command context="Proxmox host" risk="normal"
```bash
pct status 117
pct config 117
pct exec 117 -- systemctl --failed
pct exec 117 -- systemctl status bazarr.service --no-pager
```
:::

- [ ] Confirm the expected application service is active inside the container.
- [ ] Verify the application from a client on the intended network.
- [ ] Confirm the latest scheduled backup completed successfully.
- [ ] Review recent application and system logs before making changes.

