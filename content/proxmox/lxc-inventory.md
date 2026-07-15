---
title: LXC Inventory Overview
description: Current Proxmox configuration baseline for every LXC on the homelab cluster.
icon: server
order: 10
visibility: private
nav: true
type: reference
reviewed: 2026-07-13
review_after: 30
keywords: [proxmox, lxc, inventory, homelab]
---

# LXC Inventory Overview

This inventory was collected read-only from Proxmox VE 9.1.9 on 2026-07-13. It contains one baseline page for every LXC visible to the documentation token.

Application runtime details were collected on 2026-07-13 using read-only commands inside the running LXCs. Unverified: the documentation API token receives HTTP 403 from the cluster backup-jobs endpoint.

:::banner type="warning"
Recovery notes identify application state and required data, but restore procedures remain incomplete until scheduled backup coverage and at least one representative restore are verified.
:::

| CT | Service | Status | Address | CPU | Memory | Root disk |
| ---: | --- | --- | --- | ---: | ---: | --- |
| 101 | [AdGuard Home](/proxmox/101-adguard) | running | `192.168.1.101` | 2 | 2000 MiB | `local-lvm:vm-101-disk-0,size=2G` |
| 102 | [Plex](/proxmox/102-plex) | running | `192.168.1.102` | 2 | 2048 MiB | `local-lvm:vm-102-disk-0,size=8G` |
| 103 | [Pangolin](/proxmox/103-pangolin) | running | `192.168.1.103` | 2 | 1024 MiB | `local-lvm:vm-103-disk-1,size=5G` |
| 104 | [qBittorrent](/proxmox/104-qbittorrent) | running | `192.168.1.104` | 2 | 2048 MiB | `local-lvm:vm-104-disk-0,size=8G` |
| 105 | [Anchor](/proxmox/105-anchor) | running | `192.168.1.105` | 2 | 2048 MiB | `local-lvm:vm-105-disk-0,size=10G` |
| 106 | [Local Web Server](/proxmox/106-local-web-server) | running | `192.168.1.106` | 4 | 4096 MiB | `local-lvm:vm-106-disk-0,size=20G` |
| 107 | [Immich](/proxmox/107-immich) | running | `192.168.1.107` | 4 | 8000 MiB | `local-lvm:vm-107-disk-0,size=20G` |
| 108 | [Radarr](/proxmox/108-radarr) | running | `192.168.1.108` | 2 | 1024 MiB | `local-lvm:vm-108-disk-0,size=4G` |
| 109 | [Prowlarr](/proxmox/109-prowlarr) | running | `192.168.1.109` | 2 | 1024 MiB | `local-lvm:vm-109-disk-0,size=4G` |
| 110 | [VPN Browser Gateway](/proxmox/110-vpn-browser) | running | `192.168.1.248` | 1 | 512 MiB | `local-lvm:vm-110-disk-0,size=4G` |
| 111 | [Debian Browser Client](/proxmox/111-debian) | running | `192.168.1.115` | 2 | 2048 MiB | `local-lvm:vm-111-disk-0,size=5G` |
| 112 | [VPN Torrent Gateway](/proxmox/112-vpn-torrent) | running | `192.168.1.249` | 1 | 512 MiB | `local-lvm:vm-112-disk-0,size=4G` |
| 113 | [n8n](/proxmox/113-n8n) | running | `192.168.1.113` | 2 | 2048 MiB | `local-lvm:vm-113-disk-0,size=10G` |
| 114 | [Open WebUI](/proxmox/114-openwebui) | running | `192.168.1.114` | 4 | 8192 MiB | `local-lvm:vm-114-disk-0,size=50G` |
| 115 | [Jellyfin](/proxmox/115-jellyfin) | running | `192.168.1.198` | 2 | 2048 MiB | `local-lvm:vm-115-disk-1,size=16G` |
| 116 | [SABnzbd](/proxmox/116-sabnzbd) | running | `192.168.1.116` | 2 | 2048 MiB | `local-lvm:vm-116-disk-0,size=5G` |
| 117 | [Bazarr](/proxmox/117-bazarr) | running | `192.168.1.117` | 2 | 1024 MiB | `local-lvm:vm-117-disk-0,size=4G` |
| 118 | [Seerr](/proxmox/118-seerr) | running | `192.168.1.118` | 4 | 4096 MiB | `local-lvm:vm-118-disk-0,size=12G` |
| 119 | [Lidarr](/proxmox/119-lidarr) | running | `192.168.1.119` | 2 | 1024 MiB | `local-lvm:vm-119-disk-0,size=4G` |
| 120 | [Ollama](/proxmox/120-ollama) | stopped | `192.168.1.120` | 8 | 16384 MiB | `local-lvm:vm-120-disk-0,size=40G` |
| 121 | [LeafWiki](/proxmox/121-leafwiki) | running | `192.168.1.22` | 1 | 512 MiB | `local-lvm:vm-121-disk-0,size=4G` |

## Key infrastructure relationships

- Shared media storage `/mnt/pve/media_storage`: [Plex (CT 102)](/proxmox/102-plex), [qBittorrent (CT 104)](/proxmox/104-qbittorrent), [Immich (CT 107)](/proxmox/107-immich), [Radarr (CT 108)](/proxmox/108-radarr), [Jellyfin (CT 115)](/proxmox/115-jellyfin), [SABnzbd (CT 116)](/proxmox/116-sabnzbd), [Bazarr (CT 117)](/proxmox/117-bazarr), [Lidarr (CT 119)](/proxmox/119-lidarr).
- Intel GPU consumers: [Plex (CT 102)](/proxmox/102-plex), [Immich (CT 107)](/proxmox/107-immich), [Open WebUI (CT 114)](/proxmox/114-openwebui), [Jellyfin (CT 115)](/proxmox/115-jellyfin), [Ollama (CT 120)](/proxmox/120-ollama).
- WireGuard browser path: [Debian Browser Client (CT 111)](/proxmox/111-debian) -> [VPN Browser Gateway (CT 110)](/proxmox/110-vpn-browser) -> LAN router.
- WireGuard torrent path: [qBittorrent (CT 104)](/proxmox/104-qbittorrent) -> [VPN Torrent Gateway (CT 112)](/proxmox/112-vpn-torrent) -> LAN router.

## Refreshing the inventory

Run from the documentation project on the trusted management workstation:

```powershell
.\bin\sync-proxmox-inventory.ps1
composer docs:validate
```

