---
title: "CT 111 - Debian Browser Client"
description: "Current Proxmox configuration and operating baseline for Debian Browser Client."
order: 111
visibility: private
nav: true
type: reference
reviewed: 2026-07-13
review_after: 90
keywords: [proxmox, lxc, inventory, debian]
service:
  type: lxc
  id: 111
  application: "Debian Browser Client"
  address: "192.168.1.115"
  installation: helper-script
  context: "Proxmox host + CT 111"
---

# CT 111 - Debian Browser Client

Provides the browser workload routed through the VPN gateway at 192.168.1.248.

:::banner type="info"
Inventory captured from Proxmox VE 9.1.9 on 2026-07-13. Runtime status and DHCP addresses can change between syncs.
:::

## Application runtime

| Setting | Observed value |
| --- | --- |
| Runtime captured | `2026-07-13` |
| Application version | Chromium 143.0.7499.169; TigerVNC 1.15.0; noVNC 1.6.0; websockify 0.12.0 |

### Services

| Unit | State | Role |
| --- | --- | --- |
| `vpn-browser.service` | active | Primary browser/VNC stack |
| `lightdm.service` | active | Display manager |
| `vnc.service` | activating/restart loop | Duplicate VNC unit |
| `novnc.service` | activating/restart loop | Duplicate noVNC unit |

### Listening ports

| Listener | Scope | Purpose |
| --- | --- | --- |
| `6080/tcp` | All interfaces | noVNC/WebSocket access |
| `5901/tcp` | Loopback | TigerVNC |

## Data and recovery

### Important paths

- `/home/vpnuser/start-vpn-browser.sh`
- `/home/vpnuser/.vnc/xstartup`
- `/etc/systemd/system/vpn-browser.service`
- `/etc/systemd/system/vnc.service`
- `/etc/systemd/system/novnc.service`

### Backup procedure

- Back up /home/vpnuser and the three custom systemd units.
- Restore and verify the default route through CT 110 before opening the browser workload.

### Observed state

- The default route is CT 110 at 192.168.1.248.
- vpn-browser.service already owns ports 5901 and 6080; separately enabled vnc.service and novnc.service repeatedly fail with address-in-use errors.
- The browser user's home directory uses approximately 601 MiB.

## Proxmox configuration

| Setting | Value |
| --- | --- |
| Node | `exel` |
| Status at capture | **running** |
| Hostname | `debian` |
| OS / architecture | `debian` / `amd64` |
| CPU | 2 cores |
| Memory / swap | 2048 MiB / 512 MiB |
| Unprivileged | Yes |
| Start at boot | Yes |
| Tags | `community-script;os` |
| Features | `nesting=1,keyctl=1` |
| Time zone | `America/Chicago` |

## Network

| Setting | Value |
| --- | --- |
| Current IPv4 | `192.168.1.115` |
| Configured address | `192.168.1.115/24` |
| Gateway | `192.168.1.248` |
| Bridge | `vmbr0` |
| MAC address | `BC:24:11:B0:59:74` |
| Proxmox firewall flag | Not enabled on `net0` |

:::command context="Proxmox host" risk="normal"
```text
name=eth0,bridge=vmbr0,gw=192.168.1.248,hwaddr=BC:24:11:B0:59:74,ip=192.168.1.115/24,type=veth
```
:::

## Storage and devices

- Root filesystem: `local-lvm:vm-111-disk-0,size=5G`
- Additional mount points: none configured
- Device mappings: none configured

## Infrastructure relationships

- Default-route dependency: this LXC uses [VPN Browser Gateway (CT 110)](/proxmox/110-vpn-browser) at `192.168.1.248` instead of the LAN router.

## Routine checks

:::command context="Proxmox host" risk="normal"
```bash
pct status 111
pct config 111
pct exec 111 -- systemctl --failed
pct exec 111 -- systemctl status vpn-browser.service lightdm.service vnc.service novnc.service --no-pager
```
:::

- [ ] Confirm the expected application service is active inside the container.
- [ ] Verify the application from a client on the intended network.
- [ ] Confirm the latest scheduled backup completed successfully.
- [ ] Review recent application and system logs before making changes.

## Related runbook

See [Debian Browser Client operational guidance](/proxmox/vpn-gateways).

