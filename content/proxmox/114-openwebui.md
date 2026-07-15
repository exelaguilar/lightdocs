---
title: "CT 114 - Open WebUI"
description: "Current Proxmox configuration and operating baseline for Open WebUI."
order: 114
visibility: private
nav: true
type: reference
reviewed: 2026-07-13
review_after: 90
keywords: [proxmox, lxc, inventory, openwebui]
service:
  type: lxc
  id: 114
  application: "Open WebUI"
  address: "192.168.1.114"
  installation: helper-script
  context: "Proxmox host + CT 114"
---

# CT 114 - Open WebUI

Provides the web interface for local AI services and has Intel GPU devices mapped in.

:::banner type="info"
Inventory captured from Proxmox VE 9.1.9 on 2026-07-13. Runtime status and DHCP addresses can change between syncs.
:::

## Application runtime

| Setting | Observed value |
| --- | --- |
| Runtime captured | `2026-07-13` |
| Application version | Open WebUI 0.10.2 |

### Services

| Unit | State | Role |
| --- | --- | --- |
| `open-webui.service` | active | AI web interface |

### Listening ports

| Listener | Scope | Purpose |
| --- | --- | --- |
| `8080/tcp` | All interfaces | Open WebUI |

## Data and recovery

### Important paths

- `/root/.open-webui/webui.db`
- `/root/.open-webui/vector_db/chroma.sqlite3`
- `/root/.open-webui/uploads`
- `/root/.env`

### Backup procedure

- Stop open-webui.service or use SQLite-aware backups for webui.db and chroma.sqlite3.
- Back up /root/.open-webui and the private /root/.env file together.

### Observed state

- Open WebUI data uses approximately 8.9 MiB.
- Intel render and card devices are mapped; preserve and revalidate their documented GIDs after restore.

## Proxmox configuration

| Setting | Value |
| --- | --- |
| Node | `exel` |
| Status at capture | **running** |
| Hostname | `openwebui` |
| OS / architecture | `debian` / `amd64` |
| CPU | 4 cores |
| Memory / swap | 8192 MiB / 512 MiB |
| Unprivileged | Yes |
| Start at boot | Yes |
| Tags | `ai;community-script;interface` |
| Features | `nesting=1,keyctl=1` |
| Time zone | `America/Chicago` |

## Network

| Setting | Value |
| --- | --- |
| Current IPv4 | `192.168.1.114` |
| Configured address | `192.168.1.114/24` |
| Gateway | `192.168.1.1` |
| Bridge | `vmbr0` |
| MAC address | `BC:24:11:CB:FC:5A` |
| Proxmox firewall flag | Not enabled on `net0` |

:::command context="Proxmox host" risk="normal"
```text
name=eth0,bridge=vmbr0,gw=192.168.1.1,hwaddr=BC:24:11:CB:FC:5A,ip=192.168.1.114/24,type=veth
```
:::

## Storage and devices

- Root filesystem: `local-lvm:vm-114-disk-0,size=50G`
- Additional mount points: none configured
- Device `dev0`: `/dev/dri/renderD128,gid=992`
- Device `dev1`: `/dev/dri/card0,gid=44`

## Infrastructure relationships

- Intel GPU: `/dev/dri/renderD128` and `/dev/dri/card0` are mapped into this LXC. Numeric GIDs are container-specific and must be revalidated after restore. Other current GPU consumers are [Plex (CT 102)](/proxmox/102-plex), [Immich (CT 107)](/proxmox/107-immich), [Jellyfin (CT 115)](/proxmox/115-jellyfin), [Ollama (CT 120)](/proxmox/120-ollama).

## Routine checks

:::command context="Proxmox host" risk="normal"
```bash
pct status 114
pct config 114
pct exec 114 -- systemctl --failed
pct exec 114 -- systemctl status open-webui.service --no-pager
```
:::

- [ ] Confirm the expected application service is active inside the container.
- [ ] Verify the application from a client on the intended network.
- [ ] Confirm the latest scheduled backup completed successfully.
- [ ] Review recent application and system logs before making changes.

