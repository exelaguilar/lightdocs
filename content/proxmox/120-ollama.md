---
title: "CT 120 - Ollama"
description: "Current Proxmox configuration and operating baseline for Ollama."
order: 120
visibility: private
nav: true
type: reference
reviewed: 2026-07-13
review_after: 90
keywords: [proxmox, lxc, inventory, ollama]
service:
  type: lxc
  id: 120
  application: "Ollama"
  address: "192.168.1.120"
  installation: helper-script
  context: "Proxmox host + CT 120"
---

# CT 120 - Ollama

Provides the local model runtime and has Intel GPU devices mapped in.

:::banner type="info"
Inventory captured from Proxmox VE 9.1.9 on 2026-07-13. Runtime status and DHCP addresses can change between syncs.
:::

## Application runtime

| Setting | Observed value |
| --- | --- |
| Runtime captured | `2026-07-13` |
| Application version | Not collected: CT 120 was stopped |

## Data and recovery

### Backup procedure

- Verify Ollama model and configuration locations after the container is intentionally started.
- Back up the LXC root filesystem and any model directory identified during that review.

### Observed state

- Runtime inspection was intentionally skipped because the container was stopped.
- Intel render and card devices are configured in Proxmox but could not be verified inside the stopped LXC.

## Proxmox configuration

| Setting | Value |
| --- | --- |
| Node | `exel` |
| Status at capture | **stopped** |
| Hostname | `ollama` |
| OS / architecture | `ubuntu` / `amd64` |
| CPU | 8 cores |
| Memory / swap | 16384 MiB / 512 MiB |
| Unprivileged | Yes |
| Start at boot | Yes |
| Tags | `ai;community-script` |
| Features | `nesting=1,keyctl=1` |
| Time zone | `America/Chicago` |

## Network

| Setting | Value |
| --- | --- |
| Current IPv4 | `192.168.1.120` |
| Configured address | `192.168.1.120/24` |
| Gateway | `192.168.1.1` |
| Bridge | `vmbr0` |
| MAC address | `BC:24:11:48:8A:9A` |
| Proxmox firewall flag | Not enabled on `net0` |

:::command context="Proxmox host" risk="normal"
```text
name=eth0,bridge=vmbr0,gw=192.168.1.1,hwaddr=BC:24:11:48:8A:9A,ip=192.168.1.120/24,type=veth
```
:::

## Storage and devices

- Root filesystem: `local-lvm:vm-120-disk-0,size=40G`
- Additional mount points: none configured
- Device `dev0`: `/dev/dri/renderD128,gid=993`
- Device `dev1`: `/dev/dri/card0,gid=44`

## Infrastructure relationships

- Intel GPU: `/dev/dri/renderD128` and `/dev/dri/card0` are mapped into this LXC. Numeric GIDs are container-specific and must be revalidated after restore. Other current GPU consumers are [Plex (CT 102)](/proxmox/102-plex), [Immich (CT 107)](/proxmox/107-immich), [Open WebUI (CT 114)](/proxmox/114-openwebui), [Jellyfin (CT 115)](/proxmox/115-jellyfin).

## Routine checks

:::command context="Proxmox host" risk="normal"
```bash
pct status 120
pct config 120
```
:::

- [ ] Confirm the expected application service is active inside the container.
- [ ] Verify the application from a client on the intended network.
- [ ] Confirm the latest scheduled backup completed successfully.
- [ ] Review recent application and system logs before making changes.

