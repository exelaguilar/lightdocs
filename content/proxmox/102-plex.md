---
title: "CT 102 - Plex"
description: "Current Proxmox configuration and operating baseline for Plex."
order: 102
visibility: private
nav: true
type: reference
reviewed: 2026-07-13
review_after: 90
keywords: [proxmox, lxc, inventory, plex]
service:
  type: lxc
  id: 102
  application: "Plex"
  address: "192.168.1.102"
  installation: helper-script
  context: "Proxmox host + CT 102"
---

# CT 102 - Plex

Provides the Plex media server and uses shared media storage plus Intel GPU devices.

:::banner type="info"
Inventory captured from Proxmox VE 9.1.9 on 2026-07-13. Runtime status and DHCP addresses can change between syncs.
:::

## Application runtime

| Setting | Observed value |
| --- | --- |
| Runtime captured | `2026-07-13` |
| Application version | Plex Media Server 1.43.2.10687-563d026ea |

### Services

| Unit | State | Role |
| --- | --- | --- |
| `plexmediaserver.service` | active | Media server |

### Listening ports

| Listener | Scope | Purpose |
| --- | --- | --- |
| `32400/tcp` | All interfaces | Plex web and client API |

## Data and recovery

### Important paths

- `/var/lib/plexmediaserver/Library`
- `/media_storage (bind mount)`

### Backup procedure

- Back up the LXC root filesystem to preserve Plex metadata and configuration.
- Back up /mnt/pve/media_storage separately; Proxmox vzdump does not include bind-mount contents.
- For the cleanest metadata backup, stop plexmediaserver.service while copying /var/lib/plexmediaserver.
- Reference: [Proxmox container bind-mount backup behavior](https://pve.proxmox.com/pve-docs-9-beta/pct.1.html).

### Observed state

- Plex metadata uses approximately 993 MiB.
- Intel render and card devices are mapped for hardware transcoding; preserve and revalidate the documented GIDs after restore.

## Proxmox configuration

| Setting | Value |
| --- | --- |
| Node | `exel` |
| Status at capture | **running** |
| Hostname | `plex` |
| OS / architecture | `ubuntu` / `amd64` |
| CPU | 2 cores |
| Memory / swap | 2048 MiB / 512 MiB |
| Unprivileged | Yes |
| Start at boot | Yes |
| Tags | `community-script;media` |
| Features | `keyctl=1,nesting=1` |

## Network

| Setting | Value |
| --- | --- |
| Current IPv4 | `192.168.1.102` |
| Configured address | `192.168.1.102/24` |
| Gateway | `192.168.1.1` |
| Bridge | `vmbr0` |
| MAC address | `BC:24:11:54:FB:F3` |
| Proxmox firewall flag | Not enabled on `net0` |

:::command context="Proxmox host" risk="normal"
```text
name=eth0,bridge=vmbr0,gw=192.168.1.1,hwaddr=BC:24:11:54:FB:F3,ip=192.168.1.102/24,type=veth
```
:::

## Storage and devices

- Root filesystem: `local-lvm:vm-102-disk-0,size=8G`
- Mount `mp0`: `/mnt/pve/media_storage,mp=/media_storage`
- Device `dev0`: `/dev/dri/card0,gid=44`
- Device `dev1`: `/dev/dri/renderD128,gid=104`

## Infrastructure relationships

- Shared storage: host path `/mnt/pve/media_storage` is mounted at `/media_storage`. Other current consumers are [qBittorrent (CT 104)](/proxmox/104-qbittorrent), [Immich (CT 107)](/proxmox/107-immich), [Radarr (CT 108)](/proxmox/108-radarr), [Jellyfin (CT 115)](/proxmox/115-jellyfin), [SABnzbd (CT 116)](/proxmox/116-sabnzbd), [Bazarr (CT 117)](/proxmox/117-bazarr), [Lidarr (CT 119)](/proxmox/119-lidarr).
- Intel GPU: `/dev/dri/card0` and `/dev/dri/renderD128` are mapped into this LXC. Numeric GIDs are container-specific and must be revalidated after restore. Other current GPU consumers are [Immich (CT 107)](/proxmox/107-immich), [Open WebUI (CT 114)](/proxmox/114-openwebui), [Jellyfin (CT 115)](/proxmox/115-jellyfin), [Ollama (CT 120)](/proxmox/120-ollama).

## Routine checks

:::command context="Proxmox host" risk="normal"
```bash
pct status 102
pct config 102
pct exec 102 -- systemctl --failed
pct exec 102 -- systemctl status plexmediaserver.service --no-pager
```
:::

- [ ] Confirm the expected application service is active inside the container.
- [ ] Verify the application from a client on the intended network.
- [ ] Confirm the latest scheduled backup completed successfully.
- [ ] Review recent application and system logs before making changes.

