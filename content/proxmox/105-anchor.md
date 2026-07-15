---
title: "CT 105 - Anchor"
description: "Current Proxmox configuration and operating baseline for Anchor."
order: 105
visibility: private
nav: true
type: reference
reviewed: 2026-07-13
review_after: 90
keywords: [proxmox, lxc, inventory, anchor]
service:
  type: lxc
  id: 105
  application: "Anchor"
  address: "192.168.1.105"
  installation: helper-script
  context: "Proxmox host + CT 105"
---

# CT 105 - Anchor

Provides the Anchor notes, productivity, and synchronization service.

:::banner type="info"
Inventory captured from Proxmox VE 9.1.9 on 2026-07-13. Runtime status and DHCP addresses can change between syncs.
:::

## Application runtime

| Setting | Observed value |
| --- | --- |
| Runtime captured | `2026-07-13` |
| Application version | Anchor server/web 0.13.0; PostgreSQL 17.9 |

### Services

| Unit | State | Role |
| --- | --- | --- |
| `anchor-server.service` | active | Anchor API |
| `anchor-web.service` | active | Anchor web interface |
| `postgresql@17-main.service` | active | Application database |

### Listening ports

| Listener | Scope | Purpose |
| --- | --- | --- |
| `3000/tcp` | All interfaces | Anchor web interface |
| `3001/tcp` | All interfaces | Anchor API |
| `5432/tcp` | Loopback | PostgreSQL |

## Data and recovery

### Important paths

- `/opt/anchor`
- `/opt/anchor/.env`
- `/var/lib/postgresql/17`

### Backup procedure

- Create a PostgreSQL dump before application-level recovery or take a consistent snapshot with Anchor stopped.
- Back up /opt/anchor, including the private .env file, together with the PostgreSQL data or dump.
- Keep the .env file private because it contains application credentials.

### Observed state

- The Anchor tree uses approximately 1.1 GiB and PostgreSQL approximately 42 MiB.

## Proxmox configuration

| Setting | Value |
| --- | --- |
| Node | `exel` |
| Status at capture | **running** |
| Hostname | `anchor` |
| OS / architecture | `debian` / `amd64` |
| CPU | 2 cores |
| Memory / swap | 2048 MiB / 512 MiB |
| Unprivileged | Yes |
| Start at boot | Yes |
| Tags | `community-script;notes;productivity;sync` |
| Features | `nesting=1,keyctl=1` |
| Time zone | `America/Chicago` |

## Network

| Setting | Value |
| --- | --- |
| Current IPv4 | `192.168.1.105` |
| Configured address | `192.168.1.105/24` |
| Gateway | `192.168.1.1` |
| Bridge | `vmbr0` |
| MAC address | `BC:24:11:22:09:59` |
| Proxmox firewall flag | Not enabled on `net0` |

:::command context="Proxmox host" risk="normal"
```text
name=eth0,bridge=vmbr0,gw=192.168.1.1,hwaddr=BC:24:11:22:09:59,ip=192.168.1.105/24,type=veth
```
:::

## Storage and devices

- Root filesystem: `local-lvm:vm-105-disk-0,size=10G`
- Additional mount points: none configured
- Device mappings: none configured

## Routine checks

:::command context="Proxmox host" risk="normal"
```bash
pct status 105
pct config 105
pct exec 105 -- systemctl --failed
pct exec 105 -- systemctl status anchor-server.service anchor-web.service postgresql@17-main.service --no-pager
```
:::

- [ ] Confirm the expected application service is active inside the container.
- [ ] Verify the application from a client on the intended network.
- [ ] Confirm the latest scheduled backup completed successfully.
- [ ] Review recent application and system logs before making changes.

