---
title: "CT 112 - VPN Torrent Gateway"
description: "Current Proxmox configuration and operating baseline for VPN Torrent Gateway."
order: 112
visibility: private
nav: true
type: reference
reviewed: 2026-07-13
review_after: 90
keywords: [proxmox, lxc, inventory, vpn-torrent]
service:
  type: lxc
  id: 112
  application: "VPN Torrent Gateway"
  address: "192.168.1.249"
  installation: helper-script
  context: "Proxmox host + CT 112"
---

# CT 112 - VPN Torrent Gateway

Provides WireGuard egress for qBittorrent at CT 104.

:::banner type="info"
Inventory captured from Proxmox VE 9.1.9 on 2026-07-13. Runtime status and DHCP addresses can change between syncs.
:::

## Application runtime

| Setting | Observed value |
| --- | --- |
| Runtime captured | `2026-07-13` |
| Application version | wireguard-tools 1.0.20210914 |

## Data and recovery

### Important paths

- `/etc/wireguard/wg0.conf`
- `Active interface wg0`

### Backup procedure

- Back up /etc/wireguard/wg0.conf as a secret and restrict access to the backup.
- Preserve the forwarding/NAT rules and verify them after restore before routing CT 104 through this gateway.

### Observed state

- IPv4 forwarding is enabled.
- The FORWARD chain defaults to drop, permits eth0-to-wg0 traffic, and permits established/related return traffic.
- Masquerading is applied on wg0, providing the documented forwarding kill switch for qBittorrent CT 104.

## Proxmox configuration

| Setting | Value |
| --- | --- |
| Node | `exel` |
| Status at capture | **running** |
| Hostname | `vpn-torrent` |
| OS / architecture | `debian` / `amd64` |
| CPU | 1 core |
| Memory / swap | 512 MiB / 512 MiB |
| Unprivileged | Yes |
| Start at boot | Yes |
| Tags | `community-script;network;vpn` |
| Features | `nesting=1,keyctl=1` |
| Time zone | `America/Chicago` |

## Network

| Setting | Value |
| --- | --- |
| Current IPv4 | `192.168.1.249` |
| Configured address | `192.168.1.249/24` |
| Gateway | `192.168.1.1` |
| Bridge | `vmbr0` |
| MAC address | `BC:24:11:C7:3C:44` |
| Proxmox firewall flag | Not enabled on `net0` |

:::command context="Proxmox host" risk="normal"
```text
name=eth0,bridge=vmbr0,gw=192.168.1.1,hwaddr=BC:24:11:C7:3C:44,ip=192.168.1.249/24,type=veth
```
:::

## Storage and devices

- Root filesystem: `local-lvm:vm-112-disk-0,size=4G`
- Additional mount points: none configured
- Device mappings: none configured

## Infrastructure relationships

- Gateway responsibility: this LXC provides the default route for [qBittorrent (CT 104)](/proxmox/104-qbittorrent). Its availability and kill-switch behavior directly affect those clients.

## Routine checks

:::command context="Proxmox host" risk="normal"
```bash
pct status 112
pct config 112
pct exec 112 -- systemctl --failed
pct exec 112 -- wg show interfaces
pct exec 112 -- iptables -S FORWARD
```
:::

- [ ] Confirm the expected application service is active inside the container.
- [ ] Verify the application from a client on the intended network.
- [ ] Confirm the latest scheduled backup completed successfully.
- [ ] Review recent application and system logs before making changes.

## Related runbook

See [VPN Torrent Gateway operational guidance](/proxmox/vpn-gateways).

