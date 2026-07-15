---
title: "CT 109 - Prowlarr"
description: "Current Proxmox configuration and operating baseline for Prowlarr."
order: 109
visibility: private
nav: true
type: reference
reviewed: 2026-07-13
review_after: 90
keywords: [proxmox, lxc, inventory, prowlarr]
service:
  type: lxc
  id: 109
  application: "Prowlarr"
  address: "192.168.1.109"
  installation: helper-script
  context: "Proxmox host + CT 109"
---

# CT 109 - Prowlarr

Manages indexers for the media automation stack.

:::banner type="info"
Inventory captured from Proxmox VE 9.1.9 on 2026-07-13. Runtime status and DHCP addresses can change between syncs.
:::

## Application runtime

| Setting | Observed value |
| --- | --- |
| Runtime captured | `2026-07-13` |
| Application version | Installed Prowlarr 2.4.0.5397; running process started as 2.3.0.5236 |

### Services

| Unit | State | Role |
| --- | --- | --- |
| `prowlarr.service` | active | Indexer management |

### Listening ports

| Listener | Scope | Purpose |
| --- | --- | --- |
| `9696/tcp` | All interfaces | Prowlarr web/API |

## Data and recovery

### Important paths

- `/var/lib/prowlarr/config.xml`
- `/var/lib/prowlarr/prowlarr.db`
- `/var/lib/prowlarr/Backups`

### Backup procedure

- Use Prowlarr's built-in backup or stop prowlarr.service before copying /var/lib/prowlarr.
- Preserve config.xml and prowlarr.db together because they contain indexer and application integration state.

### Observed state

- The installed binary is newer than the running process; verify an intentional maintenance window and restart after reviewing release changes.
- Application data uses approximately 29 MiB.

## Proxmox configuration

| Setting | Value |
| --- | --- |
| Node | `exel` |
| Status at capture | **running** |
| Hostname | `prowlarr` |
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
| Current IPv4 | `192.168.1.109` |
| Configured address | `192.168.1.109/24` |
| Gateway | `192.168.1.1` |
| Bridge | `vmbr0` |
| MAC address | `BC:24:11:B4:48:64` |
| Proxmox firewall flag | Not enabled on `net0` |

:::command context="Proxmox host" risk="normal"
```text
name=eth0,bridge=vmbr0,gw=192.168.1.1,hwaddr=BC:24:11:B4:48:64,ip=192.168.1.109/24,type=veth
```
:::

## Storage and devices

- Root filesystem: `local-lvm:vm-109-disk-0,size=4G`
- Additional mount points: none configured
- Device mappings: none configured

## Routine checks

:::command context="Proxmox host" risk="normal"
```bash
pct status 109
pct config 109
pct exec 109 -- systemctl --failed
pct exec 109 -- systemctl status prowlarr.service --no-pager
```
:::

- [ ] Confirm the expected application service is active inside the container.
- [ ] Verify the application from a client on the intended network.
- [ ] Confirm the latest scheduled backup completed successfully.
- [ ] Review recent application and system logs before making changes.

