---
title: "CT 108 - Radarr"
description: "Current Proxmox configuration and operating baseline for Radarr."
order: 108
visibility: private
nav: true
type: reference
reviewed: 2026-07-13
review_after: 90
keywords: [proxmox, lxc, inventory, radarr]
service:
  type: lxc
  id: 108
  application: "Radarr"
  address: "192.168.1.108"
  installation: helper-script
  context: "Proxmox host + CT 108"
---

# CT 108 - Radarr

Manages the movie library and writes to shared media storage.

:::banner type="info"
Inventory captured from Proxmox VE 9.1.9 on 2026-07-13. Runtime status and DHCP addresses can change between syncs.
:::

## Application runtime

| Setting | Observed value |
| --- | --- |
| Runtime captured | `2026-07-13` |
| Application version | Installed Radarr 6.2.1.10461; running process started as 6.0.4.10291 |

### Services

| Unit | State | Role |
| --- | --- | --- |
| `radarr.service` | active | Movie automation |

### Listening ports

| Listener | Scope | Purpose |
| --- | --- | --- |
| `7878/tcp` | All interfaces | Radarr web/API |

## Data and recovery

### Important paths

- `/var/lib/radarr/config.xml`
- `/var/lib/radarr/radarr.db`
- `/var/lib/radarr/Backups`
- `/media_storage (bind mount)`

### Backup procedure

- Use Radarr's built-in backup or stop radarr.service before copying /var/lib/radarr.
- Back up /mnt/pve/media_storage separately; Proxmox vzdump does not include bind-mount contents.
- Reference: [Proxmox container bind-mount backup behavior](https://pve.proxmox.com/pve-docs-9-beta/pct.1.html).

### Observed state

- The installed binary is newer than the running process; verify an intentional maintenance window and restart after reviewing release changes.
- Application data uses approximately 262 MiB.

## Proxmox configuration

| Setting | Value |
| --- | --- |
| Node | `exel` |
| Status at capture | **running** |
| Hostname | `radarr` |
| OS / architecture | `debian` / `amd64` |
| CPU | 2 cores |
| Memory / swap | 1024 MiB / 512 MiB |
| Unprivileged | Yes |
| Start at boot | Yes |
| Tags | `arr;community-script` |
| Features | `keyctl=1,nesting=1` |

## Network

| Setting | Value |
| --- | --- |
| Current IPv4 | `192.168.1.108` |
| Configured address | `192.168.1.108/24` |
| Gateway | `192.168.1.1` |
| Bridge | `vmbr0` |
| MAC address | `BC:24:11:F5:CD:71` |
| Proxmox firewall flag | Not enabled on `net0` |

:::command context="Proxmox host" risk="normal"
```text
name=eth0,bridge=vmbr0,gw=192.168.1.1,hwaddr=BC:24:11:F5:CD:71,ip=192.168.1.108/24,type=veth
```
:::

## Storage and devices

- Root filesystem: `local-lvm:vm-108-disk-0,size=4G`
- Mount `mp0`: `/mnt/pve/media_storage,mp=/media_storage`
- Device mappings: none configured

## Infrastructure relationships

- Shared storage: host path `/mnt/pve/media_storage` is mounted at `/media_storage`. Other current consumers are [Plex (CT 102)](/proxmox/102-plex), [qBittorrent (CT 104)](/proxmox/104-qbittorrent), [Immich (CT 107)](/proxmox/107-immich), [Jellyfin (CT 115)](/proxmox/115-jellyfin), [SABnzbd (CT 116)](/proxmox/116-sabnzbd), [Bazarr (CT 117)](/proxmox/117-bazarr), [Lidarr (CT 119)](/proxmox/119-lidarr).

## Routine checks

:::command context="Proxmox host" risk="normal"
```bash
pct status 108
pct config 108
pct exec 108 -- systemctl --failed
pct exec 108 -- systemctl status radarr.service --no-pager
```
:::

- [ ] Confirm the expected application service is active inside the container.
- [ ] Verify the application from a client on the intended network.
- [ ] Confirm the latest scheduled backup completed successfully.
- [ ] Review recent application and system logs before making changes.

