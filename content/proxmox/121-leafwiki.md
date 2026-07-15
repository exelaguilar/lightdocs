---
title: "CT 121 - LeafWiki"
description: "Current Proxmox configuration and operating baseline for LeafWiki."
order: 121
visibility: private
nav: true
type: reference
reviewed: 2026-07-13
review_after: 90
keywords: [proxmox, lxc, inventory, leafwiki]
service:
  type: lxc
  id: 121
  application: "LeafWiki"
  address: "192.168.1.22"
  installation: helper-script
  context: "Proxmox host + CT 121"
---

# CT 121 - LeafWiki

Provides the LeafWiki Markdown knowledge base.

:::banner type="info"
Inventory captured from Proxmox VE 9.1.9 on 2026-07-13. Runtime status and DHCP addresses can change between syncs.
:::

## Application runtime

| Setting | Observed value |
| --- | --- |
| Runtime captured | `2026-07-13` |
| Application version | LeafWiki release not embedded in the stripped Go binary |

### Services

| Unit | State | Role |
| --- | --- | --- |
| `leafwiki.service` | active | Markdown wiki |

### Listening ports

| Listener | Scope | Purpose |
| --- | --- | --- |
| `8080/tcp` | All interfaces | LeafWiki web service |

## Data and recovery

### Important paths

- `/opt/leafwiki/data`
- `/opt/leafwiki/data/users.db`
- `/opt/leafwiki/data/search.db`
- `/opt/leafwiki/data/sessions.db`
- `/etc/leafwiki/.env`

### Backup procedure

- Stop leafwiki.service or use SQLite-aware backups before copying /opt/leafwiki/data.
- Preserve /etc/leafwiki/.env as a secret because it supplies authentication and signing configuration.
- If LeafWiki's optional Git backup is enabled later, document the remote, key path, and restore test without exposing the private key.

### Observed state

- LeafWiki data uses approximately 292 KiB.
- The stripped binary exposes a build ID but no reliable semantic version; record the release during the next upgrade.

## Proxmox configuration

| Setting | Value |
| --- | --- |
| Node | `exel` |
| Status at capture | **running** |
| Hostname | `leafwiki` |
| OS / architecture | `debian` / `amd64` |
| CPU | 1 core |
| Memory / swap | 512 MiB / 512 MiB |
| Unprivileged | Yes |
| Start at boot | Yes |
| Tags | `community-script;markdown;notes;wiki` |
| Features | `nesting=1,keyctl=1` |
| Time zone | `America/Chicago` |

## Network

| Setting | Value |
| --- | --- |
| Current IPv4 | `192.168.1.22` |
| Configured address | `dhcp` |
| Gateway | `-` |
| Bridge | `vmbr0` |
| MAC address | `BC:24:11:C3:36:EB` |
| Proxmox firewall flag | Not enabled on `net0` |

:::command context="Proxmox host" risk="normal"
```text
name=eth0,bridge=vmbr0,hwaddr=BC:24:11:C3:36:EB,ip=dhcp,type=veth
```
:::

## Storage and devices

- Root filesystem: `local-lvm:vm-121-disk-0,size=4G`
- Additional mount points: none configured
- Device mappings: none configured

## Routine checks

:::command context="Proxmox host" risk="normal"
```bash
pct status 121
pct config 121
pct exec 121 -- systemctl --failed
pct exec 121 -- systemctl status leafwiki.service --no-pager
```
:::

- [ ] Confirm the expected application service is active inside the container.
- [ ] Verify the application from a client on the intended network.
- [ ] Confirm the latest scheduled backup completed successfully.
- [ ] Review recent application and system logs before making changes.

