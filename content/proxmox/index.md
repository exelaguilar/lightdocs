---
title: Proxmox
description: Runbooks for native Proxmox VE Community Scripts LXC installations.
icon: server
order: 10
keywords: [proxmox, lxc, helper scripts, runbooks]
---

# Proxmox

These guides document native helper-script LXC installations. The current inventory contains 21 containers on node `exel`; each container has a private configuration baseline collected through the read-only Proxmox API.

Runbook verification task lists provide personal progress tracking. Their state stays in the current browser and never changes the operational source or represents shared approval.

:::banner type="warning"
Take a current Proxmox backup or snapshot before changing storage, identity mappings, networking, or helper-managed application files.
:::

:::cards
:::card title="LXC inventory overview" href="/proxmox/lxc-inventory"
Review current container IDs, addresses, resources, mounts, device mappings, and boot settings.
:::
:::card title="Immich storage and Intel GPU" href="/proxmox/immich"
Operate the native Immich helper installation in CT 107 with shared storage and hardware acceleration.
:::
:::card title="WireGuard egress gateways" href="/proxmox/vpn-gateways"
Route selected LXCs through dedicated WireGuard gateway containers with a tested kill switch.
:::
:::card title="Pangolin reverse proxy" href="/proxmox/pangolin"
Operate the native Pangolin components in CT 103 and recover their configuration.
:::
:::
