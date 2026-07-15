---
title: "CT 107 - Immich"
description: "Current Proxmox configuration and operating baseline for Immich."
order: 107
visibility: private
nav: true
type: reference
reviewed: 2026-07-13
review_after: 90
keywords: [proxmox, lxc, inventory, immich]
---

# CT 107 - Immich

Provides photo management with shared media storage and Intel GPU devices.

:::banner type="info"
Inventory captured from Proxmox VE 9.1.9 on 2026-07-13. Runtime status and DHCP addresses can change between syncs.
:::

## Application runtime

| Setting | Observed value |
| --- | --- |
| Runtime captured | `2026-07-13` |
| Application version | Immich 3.0.1; PostgreSQL 16.11; Redis 8.0.2 |

### Services

| Unit | State | Role |
| --- | --- | --- |
| `immich-web.service` | active | Immich API/web |
| `immich-ml.service` | active | Machine learning |
| `postgresql@16-main.service` | active | Application database |
| `redis-server.service` | active | Cache/queue |

### Listening ports

| Listener | Scope | Purpose |
| --- | --- | --- |
| `2283/tcp` | All interfaces | Immich web/API |
| `3003/tcp` | All interfaces | Immich machine learning |
| `5432/tcp` | Loopback | PostgreSQL |
| `6379/tcp` | Loopback | Redis |

## Data and recovery

### Important paths

- `/opt/immich/.env`
- `/opt/immich/app`
- `/var/lib/postgresql/16`
- `/var/lib/redis`
- `/media_storage (bind mount)`

### Backup procedure

- Create a PostgreSQL dump and preserve /opt/immich/.env; the database and environment must be restored together.
- Back up /mnt/pve/media_storage separately; Proxmox vzdump does not include bind-mount contents.
- Treat Redis as rebuildable runtime state unless a tested application-specific procedure requires it; the authoritative data is PostgreSQL plus media.
- Reference: [Proxmox container bind-mount backup behavior](https://pve.proxmox.com/pve-docs-9-beta/pct.1.html).

### Observed state

- The Immich tree uses approximately 8.0 GiB and PostgreSQL approximately 268 MiB.
- Intel render and card devices are mapped; preserve and revalidate the documented GIDs after restore.

## Proxmox configuration

| Setting | Value |
| --- | --- |
| Node | `exel` |
| Status at capture | **running** |
| Hostname | `immich` |
| OS / architecture | `debian` / `amd64` |
| CPU | 4 cores |
| Memory / swap | 8000 MiB / 512 MiB |
| Unprivileged | Yes |
| Start at boot | Yes |
| Tags | `community-script;photos` |
| Features | `keyctl=1,nesting=1,fuse=1` |

## Network

| Setting | Value |
| --- | --- |
| Current IPv4 | `192.168.1.107` |
| Configured address | `192.168.1.107/24` |
| Gateway | `192.168.1.1` |
| Bridge | `vmbr0` |
| MAC address | `bc:24:11:59:db:50` |
| Proxmox firewall flag | Not enabled on `net0` |

:::command context="Proxmox host" risk="normal"
```text
name=eth0,bridge=vmbr0,gw=192.168.1.1,hwaddr=bc:24:11:59:db:50,ip=192.168.1.107/24,type=veth
```
:::

## Storage and devices

- Root filesystem: `local-lvm:vm-107-disk-0,size=20G`
- Mount `mp0`: `/mnt/pve/media_storage,mp=/media_storage`
- Device `dev0`: `/dev/dri/card0,gid=44`
- Device `dev1`: `/dev/dri/renderD128,gid=992`

## Infrastructure relationships

- Shared storage: host path `/mnt/pve/media_storage` is mounted at `/media_storage`. Other current consumers are [Plex (CT 102)](/proxmox/102-plex), [qBittorrent (CT 104)](/proxmox/104-qbittorrent), [Radarr (CT 108)](/proxmox/108-radarr), [Jellyfin (CT 115)](/proxmox/115-jellyfin), [SABnzbd (CT 116)](/proxmox/116-sabnzbd), [Bazarr (CT 117)](/proxmox/117-bazarr), [Lidarr (CT 119)](/proxmox/119-lidarr).
- Intel GPU: `/dev/dri/card0` and `/dev/dri/renderD128` are mapped into this LXC. Numeric GIDs are container-specific and must be revalidated after restore. Other current GPU consumers are [Plex (CT 102)](/proxmox/102-plex), [Open WebUI (CT 114)](/proxmox/114-openwebui), [Jellyfin (CT 115)](/proxmox/115-jellyfin), [Ollama (CT 120)](/proxmox/120-ollama).

## Routine checks

:::command context="Proxmox host" risk="normal"
```bash
pct status 107
pct config 107
pct exec 107 -- systemctl --failed
pct exec 107 -- systemctl status immich-web.service immich-ml.service postgresql@16-main.service redis-server.service --no-pager
```
:::

- [ ] Confirm the expected application service is active inside the container.
- [ ] Verify the application from a client on the intended network.
- [ ] Confirm the latest scheduled backup completed successfully.
- [ ] Review recent application and system logs before making changes.

## Related runbook

See [Immich operational guidance](/proxmox/immich).

