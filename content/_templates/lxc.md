---
title: New LXC Service
description: Document this Proxmox LXC service.
order: 100
visibility: private
draft: true
nav: true
type: runbook
reviewed: 2026-07-12
review_after: 180
keywords: [proxmox, lxc, runbook, helper scripts]
service:
  type: lxc
  id: 000
  application: Application
  address: 192.0.2.10
  installation: helper-script
  context: Proxmox host + LXC
---

# New LXC Service

## Scope

Describe what the container provides and how it was installed.

## Backup

- [ ] Take a current Proxmox backup or snapshot.

## Configuration

:::command context="Proxmox host" risk="normal"
```bash
pct config 000
```
:::

## Verification

- [ ] Confirm the expected service state.
- [ ] Review recent service logs.
- [ ] Verify network and storage access from the correct execution context.

## Rollback

Document the tested rollback procedure.
