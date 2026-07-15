---
title: "CT 118 - Seerr"
description: "Current Proxmox configuration and operating baseline for Seerr."
order: 118
visibility: private
nav: true
type: reference
reviewed: 2026-07-13
review_after: 90
keywords: [proxmox, lxc, inventory, seerr]
service:
  type: lxc
  id: 118
  application: "Seerr"
  address: "192.168.1.118"
  installation: helper-script
  context: "Proxmox host + CT 118"
---

# CT 118 - Seerr

Provides media discovery and request management.

:::banner type="info"
Inventory captured from Proxmox VE 9.1.9 on 2026-07-13. Runtime status and DHCP addresses can change between syncs.
:::

## Application runtime

| Setting | Observed value |
| --- | --- |
| Runtime captured | `2026-07-13` |
| Application version | Seerr 3.3.0 |

### Services

| Unit | State | Role |
| --- | --- | --- |
| `seerr.service` | active | Media requests |

### Listening ports

| Listener | Scope | Purpose |
| --- | --- | --- |
| `5055/tcp` | All interfaces | Seerr web/API |

## Data and recovery

### Important paths

- `/opt/seerr/config/db/db.sqlite3`
- `/opt/seerr/config`
- `/opt/seerr/.env (if present)`

### Backup procedure

- Stop seerr.service or use SQLite's online backup mechanism before copying db.sqlite3 and related configuration.
- Back up the complete /opt/seerr/config directory and any private environment file.

### Observed state

- The Seerr tree uses approximately 1.3 GiB.

## Proxmox configuration

| Setting | Value |
| --- | --- |
| Node | `exel` |
| Status at capture | **running** |
| Hostname | `seerr` |
| OS / architecture | `debian` / `amd64` |
| CPU | 4 cores |
| Memory / swap | 4096 MiB / 512 MiB |
| Unprivileged | Yes |
| Start at boot | Yes |
| Tags | `community-script;media` |
| Features | `nesting=1,keyctl=1` |
| Time zone | `America/Chicago` |

## Network

| Setting | Value |
| --- | --- |
| Current IPv4 | `192.168.1.118` |
| Configured address | `192.168.1.118/24` |
| Gateway | `192.168.1.1` |
| Bridge | `vmbr0` |
| MAC address | `BC:24:11:58:B0:46` |
| Proxmox firewall flag | Not enabled on `net0` |

:::command context="Proxmox host" risk="normal"
```text
name=eth0,bridge=vmbr0,gw=192.168.1.1,hwaddr=BC:24:11:58:B0:46,ip=192.168.1.118/24,type=veth
```
:::

## Storage and devices

- Root filesystem: `local-lvm:vm-118-disk-0,size=12G`
- Additional mount points: none configured
- Device mappings: none configured

## Routine checks

:::command context="Proxmox host" risk="normal"
```bash
pct status 118
pct config 118
pct exec 118 -- systemctl --failed
pct exec 118 -- systemctl status seerr.service --no-pager
```
:::

- [ ] Confirm the expected application service is active inside the container.
- [ ] Verify the application from a client on the intended network.
- [ ] Confirm the latest scheduled backup completed successfully.
- [ ] Review recent application and system logs before making changes.

