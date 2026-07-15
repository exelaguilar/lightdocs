---
title: "CT 116 - SABnzbd"
description: "Current Proxmox configuration and operating baseline for SABnzbd."
order: 116
visibility: private
nav: true
type: reference
reviewed: 2026-07-13
review_after: 90
keywords: [proxmox, lxc, inventory, sabnzbd]
service:
  type: lxc
  id: 116
  application: "SABnzbd"
  address: "192.168.1.116"
  installation: helper-script
  context: "Proxmox host + CT 116"
---

# CT 116 - SABnzbd

Provides Usenet downloads and writes to shared media storage.

:::banner type="info"
Inventory captured from Proxmox VE 9.1.9 on 2026-07-13. Runtime status and DHCP addresses can change between syncs.
:::

## Application runtime

| Setting | Observed value |
| --- | --- |
| Runtime captured | `2026-07-13` |
| Application version | SABnzbd 5.0.4 |

### Services

| Unit | State | Role |
| --- | --- | --- |
| `sabnzbd.service` | active | Usenet downloader |

### Listening ports

| Listener | Scope | Purpose |
| --- | --- | --- |
| `7777/tcp` | All interfaces | SABnzbd web UI |

## Data and recovery

### Important paths

- `/root/.sabnzbd`
- `/root/.sabnzbd/admin/history1.db`
- `/opt/sabnzbd`
- `/media_storage (bind mount)`

### Backup procedure

- Pause downloads and stop sabnzbd.service before copying /root/.sabnzbd.
- Back up /mnt/pve/media_storage separately; Proxmox vzdump does not include bind-mount contents.
- Reference: [Proxmox container bind-mount backup behavior](https://pve.proxmox.com/pve-docs-9-beta/pct.1.html).

### Observed state

- SABnzbd configuration/history uses approximately 1.6 MiB; the application tree uses approximately 67 MiB.

## Proxmox configuration

| Setting | Value |
| --- | --- |
| Node | `exel` |
| Status at capture | **running** |
| Hostname | `sabnzbd` |
| OS / architecture | `debian` / `amd64` |
| CPU | 2 cores |
| Memory / swap | 2048 MiB / 512 MiB |
| Unprivileged | Yes |
| Start at boot | Yes |
| Tags | `community-script;downloader` |
| Features | `nesting=1,keyctl=1` |
| Time zone | `America/Chicago` |

## Network

| Setting | Value |
| --- | --- |
| Current IPv4 | `192.168.1.116` |
| Configured address | `192.168.1.116/24` |
| Gateway | `192.168.1.1` |
| Bridge | `vmbr0` |
| MAC address | `BC:24:11:52:10:C1` |
| Proxmox firewall flag | Not enabled on `net0` |

:::command context="Proxmox host" risk="normal"
```text
name=eth0,bridge=vmbr0,gw=192.168.1.1,hwaddr=BC:24:11:52:10:C1,ip=192.168.1.116/24,type=veth
```
:::

## Storage and devices

- Root filesystem: `local-lvm:vm-116-disk-0,size=5G`
- Mount `mp0`: `/mnt/pve/media_storage,mp=/media_storage`
- Device mappings: none configured

## Infrastructure relationships

- Shared storage: host path `/mnt/pve/media_storage` is mounted at `/media_storage`. Other current consumers are [Plex (CT 102)](/proxmox/102-plex), [qBittorrent (CT 104)](/proxmox/104-qbittorrent), [Immich (CT 107)](/proxmox/107-immich), [Radarr (CT 108)](/proxmox/108-radarr), [Jellyfin (CT 115)](/proxmox/115-jellyfin), [Bazarr (CT 117)](/proxmox/117-bazarr), [Lidarr (CT 119)](/proxmox/119-lidarr).

## Routine checks

:::command context="Proxmox host" risk="normal"
```bash
pct status 116
pct config 116
pct exec 116 -- systemctl --failed
pct exec 116 -- systemctl status sabnzbd.service --no-pager
```
:::

- [ ] Confirm the expected application service is active inside the container.
- [ ] Verify the application from a client on the intended network.
- [ ] Confirm the latest scheduled backup completed successfully.
- [ ] Review recent application and system logs before making changes.

