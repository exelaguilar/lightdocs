---
title: "CT 106 - Local Web Server"
description: "Current Proxmox configuration and operating baseline for Local Web Server."
order: 106
visibility: private
nav: true
type: reference
reviewed: 2026-07-13
review_after: 90
keywords: [proxmox, lxc, inventory, local-web-server]
service:
  type: lxc
  id: 106
  application: "Local Web Server"
  address: "192.168.1.106"
  installation: manual-or-unknown
  context: "Proxmox host + CT 106"
---

# CT 106 - Local Web Server

Hosts internal web workloads on the local network.

:::banner type="info"
Inventory captured from Proxmox VE 9.1.9 on 2026-07-13. Runtime status and DHCP addresses can change between syncs.
:::

## Application runtime

| Setting | Observed value |
| --- | --- |
| Runtime captured | `2026-07-13` |
| Application version | Apache 2.4.66; PHP 8.4.16; MariaDB 11.8.6; BIND 9.20.21; Samba 4.22.8; Webmin 2.630; Usermin 2.530 |

### Services

| Unit | State | Role |
| --- | --- | --- |
| `apache2.service` | active | HTTP/HTTPS |
| `php8.4-fpm.service` | active | PHP runtime |
| `mariadb.service` | active | Database |
| `named.service` | active | DNS |
| `smbd.service` | active | SMB file service |
| `webmin.service` | active | Webmin |
| `usermin.service` | active | Usermin |

### Listening ports

| Listener | Scope | Purpose |
| --- | --- | --- |
| `80/tcp, 443/tcp` | All interfaces | Apache |
| `10000/tcp` | All interfaces | Webmin |
| `20000/tcp` | All interfaces | Usermin |
| `3306/tcp` | All interfaces | MariaDB |
| `53/tcp+udp` | LAN and loopback | BIND DNS |
| `139/tcp, 445/tcp` | All interfaces | Samba |
| `25/tcp, 465/tcp, 587/tcp` | All interfaces | Mail transport/submission |

## Data and recovery

### Important paths

- `/var/www`
- `/etc/apache2`
- `/var/lib/mysql`
- `/etc/webmin`
- `/etc/usermin`
- `/etc/bind`
- `/etc/samba`

### Backup procedure

- Create a MariaDB logical dump and preserve /var/lib/mysql only as a secondary physical recovery source.
- Back up /var/www and the Apache, PHP, BIND, Samba, Webmin, Usermin, mail, and firewall configuration trees.
- Test restores for the current virtual hosts: tiny.exel.dev, exel.dev, nevernote.exel.dev, and belen.exel.dev.

### Observed state

- MariaDB data uses approximately 5.1 GiB.
- MariaDB currently listens on all interfaces at port 3306; confirm host and network firewall restrictions.
- This LXC is a multi-role server, so a single web-root backup is not a complete recovery plan.

## Proxmox configuration

| Setting | Value |
| --- | --- |
| Node | `exel` |
| Status at capture | **running** |
| Hostname | `local-web-server` |
| OS / architecture | `debian` / `amd64` |
| CPU | 4 cores |
| Memory / swap | 4096 MiB / 512 MiB |
| Unprivileged | Yes |
| Start at boot | No |
| Tags | `-` |
| Features | `nesting=1` |

## Network

| Setting | Value |
| --- | --- |
| Current IPv4 | `192.168.1.106` |
| Configured address | `192.168.1.106/24` |
| Gateway | `192.168.1.1` |
| Bridge | `vmbr0` |
| MAC address | `BC:24:11:B9:47:EF` |
| Proxmox firewall flag | Enabled |

:::command context="Proxmox host" risk="normal"
```text
name=eth0,bridge=vmbr0,firewall=1,gw=192.168.1.1,hwaddr=BC:24:11:B9:47:EF,ip=192.168.1.106/24,type=veth
```
:::

## Storage and devices

- Root filesystem: `local-lvm:vm-106-disk-0,size=20G`
- Additional mount points: none configured
- Device mappings: none configured

## Routine checks

:::command context="Proxmox host" risk="normal"
```bash
pct status 106
pct config 106
pct exec 106 -- systemctl --failed
pct exec 106 -- systemctl status apache2.service php8.4-fpm.service mariadb.service named.service smbd.service webmin.service usermin.service --no-pager
```
:::

- [ ] Confirm the expected application service is active inside the container.
- [ ] Verify the application from a client on the intended network.
- [ ] Confirm the latest scheduled backup completed successfully.
- [ ] Review recent application and system logs before making changes.

