---
title: "CT 101 - AdGuard Home"
description: "Current Proxmox configuration and operating baseline for AdGuard Home."
order: 101
visibility: private
nav: true
type: reference
reviewed: 2026-07-13
review_after: 90
keywords: [proxmox, lxc, inventory, adguard]
service:
  type: lxc
  id: 101
  application: "AdGuard Home"
  address: "192.168.1.101"
  installation: helper-script
  context: "Proxmox host + CT 101"
---

# CT 101 - AdGuard Home

Provides network-wide DNS filtering and ad blocking.

:::banner type="info"
Inventory captured from Proxmox VE 9.1.9 on 2026-07-13. Runtime status and DHCP addresses can change between syncs.
:::

## Application runtime

| Setting | Observed value |
| --- | --- |
| Runtime captured | `2026-07-13` |
| Application version | AdGuard Home v0.107.77 |

### Services

| Unit | State | Role |
| --- | --- | --- |
| `AdGuardHome.service` | active | DNS filtering and web administration |

### Listening ports

| Listener | Scope | Purpose |
| --- | --- | --- |
| `53/tcp+udp` | All interfaces | DNS |
| `80/tcp` | All interfaces | Web administration |

## Data and recovery

### Important paths

- `/opt/AdGuardHome/AdGuardHome.yaml`
- `/opt/AdGuardHome/data`
- `/opt/AdGuardHome/agh-backup`

### Backup procedure

- Back up the LXC root filesystem with Proxmox.
- Preserve AdGuardHome.yaml and the data directory together; stop AdGuard Home or use an application-consistent snapshot before copying the live databases.

### Observed state

- The AdGuard Home application tree uses approximately 316 MiB.

## Proxmox configuration

| Setting | Value |
| --- | --- |
| Node | `exel` |
| Status at capture | **running** |
| Hostname | `adguard` |
| OS / architecture | `debian` / `amd64` |
| CPU | 2 cores |
| Memory / swap | 2000 MiB / 512 MiB |
| Unprivileged | Yes |
| Start at boot | Yes |
| Tags | `adblock;community-script` |
| Features | `keyctl=1,nesting=1` |

## Network

| Setting | Value |
| --- | --- |
| Current IPv4 | `192.168.1.101` |
| Configured address | `192.168.1.101/24` |
| Gateway | `192.168.1.1` |
| Bridge | `vmbr0` |
| MAC address | `BC:24:11:FC:6E:58` |
| Proxmox firewall flag | Not enabled on `net0` |

:::command context="Proxmox host" risk="normal"
```text
name=eth0,bridge=vmbr0,gw=192.168.1.1,hwaddr=BC:24:11:FC:6E:58,ip=192.168.1.101/24,type=veth
```
:::

## Storage and devices

- Root filesystem: `local-lvm:vm-101-disk-0,size=2G`
- Additional mount points: none configured
- Device mappings: none configured

## Routine checks

:::command context="Proxmox host" risk="normal"
```bash
pct status 101
pct config 101
pct exec 101 -- systemctl --failed
pct exec 101 -- systemctl status AdGuardHome.service --no-pager
```
:::

- [ ] Confirm the expected application service is active inside the container.
- [ ] Verify the application from a client on the intended network.
- [ ] Confirm the latest scheduled backup completed successfully.
- [ ] Review recent application and system logs before making changes.

