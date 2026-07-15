---
title: "CT 103 - Pangolin"
description: "Current Proxmox configuration and operating baseline for Pangolin."
order: 103
visibility: private
nav: true
type: reference
reviewed: 2026-07-13
review_after: 90
keywords: [proxmox, lxc, inventory, pangolin]
service:
  type: lxc
  id: 103
  application: "Pangolin"
  address: "192.168.1.103"
  installation: helper-script
  context: "Proxmox host + CT 103"
---

# CT 103 - Pangolin

Provides the Pangolin reverse-proxy and tunneling service.

:::banner type="info"
Inventory captured from Proxmox VE 9.1.9 on 2026-07-13. Runtime status and DHCP addresses can change between syncs.
:::

## Application runtime

| Setting | Observed value |
| --- | --- |
| Runtime captured | `2026-07-13` |
| Application version | Pangolin manifest 0.0.0; Newt 1.8.1; Traefik 3.6.5 |

### Services

| Unit | State | Role |
| --- | --- | --- |
| `pangolin.service` | active | Pangolin application |
| `gerbil.service` | active | Tunnel data plane |
| `newt.service` | active | Private connector |
| `traefik.service` | active | HTTP/HTTPS edge router |

### Listening ports

| Listener | Scope | Purpose |
| --- | --- | --- |
| `80/tcp, 443/tcp` | All interfaces | Traefik HTTP/HTTPS |
| `8080/tcp` | All interfaces | Traefik service |
| `3000-3002/tcp` | All interfaces | Pangolin Node services |
| `3004/tcp, 8443/tcp` | All interfaces | Gerbil |
| `2112/tcp` | Loopback | Newt metrics |

## Data and recovery

### Important paths

- `/opt/pangolin/config/config.yml`
- `/opt/pangolin/config/db/db.sqlite`
- `/opt/pangolin/config/traefik/traefik_config.yml`
- `/opt/pangolin/config/traefik/dynamic_config.yml`
- `/etc/systemd/system/newt.service`

### Backup procedure

- Back up the LXC root filesystem with Proxmox.
- Before a file-level backup, stop Pangolin briefly or use a consistent snapshot so db.sqlite and configuration agree.
- Protect the Newt unit and Pangolin configuration as secrets; recovery requires their credentials as well as the SQLite database.

### Observed state

- The Pangolin tree uses approximately 2.6 GiB.
- The Pangolin package manifest reports 0.0.0, so an exact Pangolin release cannot be proven from the installed artifact.
- The Newt credential is embedded in its systemd launch arguments and must never be copied into public documentation.

## Proxmox configuration

| Setting | Value |
| --- | --- |
| Node | `exel` |
| Status at capture | **running** |
| Hostname | `pangolin` |
| OS / architecture | `debian` / `amd64` |
| CPU | 2 cores |
| Memory / swap | 1024 MiB / 512 MiB |
| Unprivileged | Yes |
| Start at boot | Yes |
| Tags | `community-script;proxy` |
| Features | `nesting=1,keyctl=1` |
| Time zone | `America/Chicago` |

## Network

| Setting | Value |
| --- | --- |
| Current IPv4 | `192.168.1.103` |
| Configured address | `192.168.1.103/24` |
| Gateway | `192.168.1.1` |
| Bridge | `vmbr0` |
| MAC address | `bc:24:11:c0:51:24` |
| Proxmox firewall flag | Not enabled on `net0` |

:::command context="Proxmox host" risk="normal"
```text
name=eth0,bridge=vmbr0,gw=192.168.1.1,hwaddr=bc:24:11:c0:51:24,ip=192.168.1.103/24,type=veth
```
:::

## Storage and devices

- Root filesystem: `local-lvm:vm-103-disk-1,size=5G`
- Additional mount points: none configured
- Device mappings: none configured
- Low-level LXC settings:
  - `lxc.cgroup2.devices.allow c 10:200 rwm`
  - `lxc.mount.entry /dev/net/tun dev/net/tun none bind,create=file`

## Routine checks

:::command context="Proxmox host" risk="normal"
```bash
pct status 103
pct config 103
pct exec 103 -- systemctl --failed
pct exec 103 -- systemctl status pangolin.service gerbil.service newt.service traefik.service --no-pager
```
:::

- [ ] Confirm the expected application service is active inside the container.
- [ ] Verify the application from a client on the intended network.
- [ ] Confirm the latest scheduled backup completed successfully.
- [ ] Review recent application and system logs before making changes.

## Related runbook

See [Pangolin operational guidance](/proxmox/pangolin).

