---
title: "CT 119 - Lidarr"
description: "Current Proxmox configuration and operating baseline for Lidarr."
order: 119
visibility: private
nav: true
type: reference
reviewed: 2026-07-13
review_after: 90
keywords: [proxmox, lxc, inventory, lidarr]
service:
  type: lxc
  id: 119
  application: "Lidarr"
  address: "192.168.1.119"
  installation: helper-script
  context: "Proxmox host + CT 119"
---

# CT 119 - Lidarr

Manages the music library and writes to shared media storage.

:::banner type="info"
Inventory captured from Proxmox VE 9.1.9 on 2026-07-13. Runtime status and DHCP addresses can change between syncs.
:::

## Application runtime

| Setting | Observed value |
| --- | --- |
| Runtime captured | `2026-07-13` |
| Application version | Lidarr 3.1.0.4875 |

### Services

| Unit | State | Role |
| --- | --- | --- |
| `lidarr.service` | active | Music automation |

### Listening ports

| Listener | Scope | Purpose |
| --- | --- | --- |
| `8686/tcp` | All interfaces | Lidarr web/API |

## Data and recovery

### Important paths

- `/var/lib/lidarr/config.xml`
- `/var/lib/lidarr/lidarr.db`
- `/var/lib/lidarr/Backups`
- `/media_storage (bind mount)`

### Backup procedure

- Use Lidarr's built-in backup or stop lidarr.service before copying /var/lib/lidarr.
- Back up /mnt/pve/media_storage separately; Proxmox vzdump does not include bind-mount contents.
- Reference: [Proxmox container bind-mount backup behavior](https://pve.proxmox.com/pve-docs-9-beta/pct.1.html).

### Observed state

- Application data uses approximately 71 MiB.

## Proxmox configuration

| Setting | Value |
| --- | --- |
| Node | `exel` |
| Status at capture | **running** |
| Hostname | `lidarr` |
| OS / architecture | `debian` / `amd64` |
| CPU | 2 cores |
| Memory / swap | 1024 MiB / 512 MiB |
| Unprivileged | Yes |
| Start at boot | Yes |
| Tags | `arr;community-script;torrent;usenet` |
| Features | `nesting=1,keyctl=1` |
| Time zone | `America/Chicago` |

## Network

| Setting | Value |
| --- | --- |
| Current IPv4 | `192.168.1.119` |
| Configured address | `192.168.1.119/24` |
| Gateway | `192.168.1.1` |
| Bridge | `vmbr0` |
| MAC address | `BC:24:11:CC:C4:69` |
| Proxmox firewall flag | Not enabled on `net0` |

:::command context="Proxmox host" risk="normal"
```text
name=eth0,bridge=vmbr0,gw=192.168.1.1,hwaddr=BC:24:11:CC:C4:69,ip=192.168.1.119/24,type=veth
```
:::

## Storage and devices

- Root filesystem: `local-lvm:vm-119-disk-0,size=4G`
- Mount `mp0`: `/mnt/pve/media_storage,mp=/media_storage`
- Device mappings: none configured

## Infrastructure relationships

- Shared storage: host path `/mnt/pve/media_storage` is mounted at `/media_storage`. Other current consumers are [Plex (CT 102)](/proxmox/102-plex), [qBittorrent (CT 104)](/proxmox/104-qbittorrent), [Immich (CT 107)](/proxmox/107-immich), [Radarr (CT 108)](/proxmox/108-radarr), [Jellyfin (CT 115)](/proxmox/115-jellyfin), [SABnzbd (CT 116)](/proxmox/116-sabnzbd), [Bazarr (CT 117)](/proxmox/117-bazarr).

## Routine checks

:::command context="Proxmox host" risk="normal"
```bash
pct status 119
pct config 119
pct exec 119 -- systemctl --failed
pct exec 119 -- systemctl status lidarr.service --no-pager
```
:::

- [ ] Confirm the expected application service is active inside the container.
- [ ] Verify the application from a client on the intended network.
- [ ] Confirm the latest scheduled backup completed successfully.
- [ ] Review recent application and system logs before making changes.

