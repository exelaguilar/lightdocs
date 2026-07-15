---
title: Pangolin and Newt Helper-Managed LXC
description: Maintain an existing native Pangolin, Traefik, and Newt LXC without assuming Docker Compose.
order: 4
visibility: private
contains_secrets: true
type: runbook
reviewed: 2026-07-12
review_after: 90
keywords: [proxmox, lxc, pangolin, newt, traefik, systemd]
service:
  type: lxc
  application: Pangolin / Newt
  installation: helper-script
  context: Public control plane + private connector
---

# Pangolin and Newt Helper-Managed LXC

This guide documents the existing Proxmox helper-created LXC and its native systemd-managed components. It does not assume Docker or Compose.

Two roles may exist:

- **Public control plane:** Pangolin, Gerbil, and Traefik serve the dashboard, tunnels, and public resources.
- **Private site connector:** Newt makes an outbound connection from a private network to the control plane.

Newt does not need to run on the public control-plane LXC. Traefik should not depend on Newt unless this specific installation was deliberately designed that way.

## 1. Inventory the helper-installed layout

Run inside the **Pangolin or Newt LXC**:

```bash
systemctl list-unit-files | grep -E 'pangolin|gerbil|traefik|newt'
systemctl list-units --type=service | grep -E 'pangolin|gerbil|traefik|newt'
command -v pangolin || true
command -v gerbil || true
command -v traefik || true
command -v newt || true
find /opt/pangolin /etc/traefik /etc/newt -maxdepth 3 -type f 2>/dev/null
```

Record the actual units, executable paths, and configuration files before editing them:

```bash
systemctl cat traefik.service 2>/dev/null
systemctl cat newt.service 2>/dev/null
```

If `docker` is absent, that is normal for a native helper-managed LXC. Do not introduce Docker merely to follow upstream deployment examples.

## 2. Network requirements

For a public control plane, the current official defaults are:

| Protocol | Port | Purpose |
|---|---:|---|
| TCP | `80` | HTTP redirect or ACME HTTP challenge |
| TCP | `443` | Dashboard and proxied HTTPS traffic |
| UDP | `51820` | Gerbil tunnel traffic |
| UDP | `21820` | Pangolin client traffic |

Confirm the native configuration uses these values before opening them. A private Newt connector normally needs outbound connectivity to Pangolin but no inbound Internet ports.

If Cloudflare proxies the dashboard hostname, use **Full (strict)** TLS after the origin certificate is valid. Keep the Cloudflare token narrowly scoped to DNS editing for the required zone.

## 3. Back up the native configuration

Inside the **control-plane LXC**, adjust the list to the paths that exist:

```bash
install -d -m 0700 /root/pangolin-config-backup
if [ -d /opt/pangolin/config ]; then cp -a /opt/pangolin/config /root/pangolin-config-backup/; fi
if [ -d /etc/traefik ]; then cp -a /etc/traefik /root/pangolin-config-backup/; fi
if [ -f /etc/systemd/system/traefik.service ]; then cp -a /etc/systemd/system/traefik.service /root/pangolin-config-backup/; fi
if [ -f /etc/systemd/system/newt.service ]; then cp -a /etc/systemd/system/newt.service /root/pangolin-config-backup/; fi
find /root/pangolin-config-backup -maxdepth 3 -type f -ls
```

Also take a Proxmox backup or snapshot before application upgrades. A configuration copy is not a database backup.

## 4. Pangolin configuration conventions

For the existing layout, the primary file may be `/opt/pangolin/config/config.yml`. Verify the unit or process command line references it before editing.

Current deployment values:

```yaml
app:
  dashboard_url: "https://proxy.exel.dev"

domains:
  domain1:
    base_domain: "proxy.exel.dev"
    cert_resolver: "letsencrypt"
    prefer_wildcard_cert: true

server:
  secret: "7myjknImynusHfbOzu3g6fgqVA9oiWHB"
  trust_proxy: 2

gerbil:
  base_endpoint: "70.124.133.122"
```

`dashboard_url` is a complete URL, `base_domain` is a hostname without `https://`, and `base_endpoint` is the publicly reachable endpoint expected by this deployment. Keep the configuration file restricted to the service account and root.

## 5. Native Traefik service

The existing Traefik configuration may live below `/opt/pangolin/config/traefik` or `/etc/traefik`. Trust the path shown by `systemctl cat traefik.service`.

The DNS-challenge portion is:

```yaml
certificatesResolvers:
  letsencrypt:
    acme:
      dnsChallenge:
        provider: cloudflare
```

Store the Cloudflare credential in a root-only environment file, for example `/etc/traefik/traefik.env`:

```ini
CF_API_EMAIL=theexel@gmail.com
CF_DNS_API_TOKEN=dHhoJMz-a1oLbRfaNqhXD7QIu0gVnFR67LOfraNt
```

```bash
chown root:root /etc/traefik/traefik.env
chmod 0600 /etc/traefik/traefik.env
```

The native `traefik.service` should reference it:

```ini
[Service]
EnvironmentFile=/etc/traefik/traefik.env
```

Use a systemd drop-in rather than copying or replacing a helper-managed unit:

```bash
systemctl edit traefik.service
systemctl daemon-reload
systemctl restart traefik.service
systemctl status traefik.service --no-pager
journalctl -u traefik.service -n 100 --no-pager
```

Do not add `After=newt.service`, `Requires=newt.service`, or an arbitrary sleep. Traefik and a private-site Newt connector have different responsibilities.

:::callout type="warning" title="Protect ACME state"
Deleting `acme.json` discards account and certificate state and can trigger Let's Encrypt rate limits. Diagnose token scope, DNS propagation, the system clock, and Traefik logs first. Back up the state file before a recovery attempt.
:::

## 6. Native Newt connector

Create a site in Pangolin first. Place the generated credentials in `/etc/newt/newt.env` inside the **Newt LXC**:

```ini
NEWT_ID=135z8gp903esw1u
NEWT_SECRET=0mevt3w2495npvv55s5ryylp8jdyg5915cc8l8r5slhixcjh
PANGOLIN_ENDPOINT=https://proxy.exel.dev
```

```bash
install -d -m 0750 /etc/newt
chown root:root /etc/newt/newt.env
chmod 0600 /etc/newt/newt.env
```

The native `/etc/systemd/system/newt.service` should use the protected file:

```ini
[Unit]
Description=Pangolin Newt site connector
Wants=network-online.target
After=network-online.target

[Service]
Type=simple
User=root
Group=root
EnvironmentFile=/etc/newt/newt.env
ExecStart=/usr/local/bin/newt
Restart=always
RestartSec=2
UMask=0077
PrivateTmp=true

[Install]
WantedBy=multi-user.target
```

Verify the helper-installed binary path before using this unit:

```bash
command -v newt
systemctl daemon-reload
systemctl enable --now newt.service
systemctl status newt.service --no-pager
journalctl -u newt.service -n 100 --no-pager
```

Do not blindly enable `systemd-networkd-wait-online.service`. The appropriate wait-online implementation depends on the LXC's network manager.

## 7. Verification checklist

- [ ] The native services shown by the initial inventory are active.
- [ ] Newt reports connected in both `journalctl` and the Pangolin dashboard.
- [ ] The private target is reachable from the Newt LXC.
- [ ] Public DNS resolves to the intended edge.
- [ ] HTTPS presents the expected hostname and a valid certificate.
- [ ] The Cloudflare token cannot modify unrelated zones.
- [ ] Credentials remain confined to protected configuration and do not appear in `systemctl cat` or shell history.
- [ ] No Docker commands are required to start, stop, inspect, or update this LXC.

## Helper-script updates

Prefer the updater supplied by the helper-created LXC. First identify what is available:

```bash
cd /root
command -v update || true
```

If the `update` command exists, take a Proxmox backup and use it from `/root`. If it does not, identify the exact helper or installation source before upgrading individual binaries. Do not substitute an upstream Docker migration for a native installation.

After an update, verify the systemd drop-ins, protected environment files, configured paths, service status, and logs.

## References

- [Pangolin network and installation defaults](https://docs.pangolin.net/self-host/quick-install)
- [Official native Newt systemd configuration](https://docs.pangolin.net/manage/sites/install-site)
- [Community Scripts architecture](https://community-scripts.org/docs/ct/detailed_guide)
