---
title: Transmission LXC Storage and ID Mapping
description: Safely give an unprivileged Transmission LXC access to shared Proxmox storage.
order: 2
visibility: private
draft: true
nav: false
type: runbook
reviewed: 2026-07-12
review_after: 180
keywords: [proxmox, lxc, transmission, storage, id mapping, systemd]
related:
  - /proxmox/vpn-gateways
  - /proxmox/immich
---

# Transmission LXC Storage and ID Mapping

:::banner type="danger"
Legacy configuration only. The live Proxmox inventory shows that CT 104 is now qBittorrent, not Transmission. Do not run this page's CT 104 commands unless a matching Transmission container is restored and every ID, path, and mount is revalidated.
:::

This guide documents the custom user/group mapping used by **CT 104** so the `debian-transmission` account can write to `/mnt/pve/media_storage/Downloads` without making the container privileged.

CT 104 is a helper-script-created native Transmission LXC. The relevant process is `transmission-daemon.service`; Docker volume and container-user instructions do not apply.

:::callout type="warning" title="Confirm the IDs before continuing"
The mapping below is valid only when `debian-transmission` is UID `102` and GID `105` inside CT 104. Package versions can use different IDs. Never paste an ID map until you have verified them.
:::

## 1. Record the current state

Run on the **Proxmox host**:

```bash
pct status 104
cp /etc/pve/lxc/104.conf /root/104.conf.before-transmission-idmap
pct exec 104 -- id debian-transmission
```

Expected output must include:

```text
uid=102(debian-transmission) gid=105(debian-transmission)
```

If the numbers differ, stop here and recalculate every range. An invalid or overlapping map can prevent the container from starting.

## 2. Understand the target mapping

The normal unprivileged map shifts container IDs `0–65535` to host IDs `100000–165535`. This setup maps one container UID and one container GID to host ID `100999`, while keeping every other ID shifted and non-overlapping.

Because `100999` is already inside the standard subordinate range `root:100000:65536`, do **not** add a redundant `root:100999:1` entry to `/etc/subuid` or `/etc/subgid`. Confirm the standard allocation exists:

```bash
grep '^root:100000:65536$' /etc/subuid
grep '^root:100000:65536$' /etc/subgid
```

## 3. Configure CT 104

Stop the container before editing its namespace map:

```bash
pct stop 104
nano /etc/pve/lxc/104.conf
```

Add the mount point if it is not already present, then add the exact map:

```ini
mp0: /mnt/pve/media_storage,mp=/media_storage

# Container UID 102 -> host UID 100999
lxc.idmap: u 0 100000 102
lxc.idmap: u 102 100999 1
lxc.idmap: u 103 100102 896
lxc.idmap: u 999 100998 1
lxc.idmap: u 1000 101000 64536

# Container GID 105 -> host GID 100999
lxc.idmap: g 0 100000 105
lxc.idmap: g 105 100999 1
lxc.idmap: g 106 100105 894
lxc.idmap: g 1000 101000 64536
```

The UID and GID ranges cover all 65,536 container IDs exactly once. The one-ID `u 999` segment fills the available host ID `100998` without colliding with the direct map at `100999`.

## 4. Set ownership only on the download directory

Run on the **Proxmox host**. Do not recursively change the ownership of the entire media library unless Transmission genuinely needs all of it.

```bash
install -d -o 100999 -g 100999 -m 2775 /mnt/pve/media_storage/Downloads
find /mnt/pve/media_storage/Downloads -type d -exec chmod 2775 {} \;
find /mnt/pve/media_storage/Downloads -type f -exec chmod 0664 {} \;
chown -R 100999:100999 /mnt/pve/media_storage/Downloads
```

Directory mode `2775` preserves group inheritance. Files remain non-executable at `0664`.

## 5. Start and verify the mapping

Run on the **Proxmox host**:

```bash
pct start 104
pct exec 104 -- id debian-transmission
pct exec 104 -- stat -c '%u:%g %a %n' /media_storage/Downloads
pct exec 104 -- runuser -u debian-transmission -- touch /media_storage/Downloads/.permission-test
stat -c '%u:%g %a %n' /mnt/pve/media_storage/Downloads/.permission-test
rm /mnt/pve/media_storage/Downloads/.permission-test
```

The host-side test file should be owned by `100999:100999`. If the container does not start, inspect `pct start 104 --debug`, restore `/root/104.conf.before-transmission-idmap`, and start it again.

## 6. Configure Transmission

First locate the configuration directory actually used by your package:

```bash
systemctl cat transmission-daemon
systemctl show transmission-daemon -p User -p Group -p Environment
```

Stop Transmission before directly editing `settings.json`; otherwise it may overwrite your changes on shutdown.

```bash
systemctl stop transmission-daemon
```

In the active `settings.json`, set the download directory using the key style supported by your installed Transmission version:

```json
"download-dir": "/media_storage/Downloads"
```

Then restart and test:

```bash
systemctl start transmission-daemon
systemctl status transmission-daemon --no-pager
journalctl -u transmission-daemon -n 50 --no-pager
```

:::callout type="info" title="Avoid a recursive systemd chown"
A service-wide `ExecStartPre=chown -R ...` makes every restart slower and can conceal a broken ID map. Correct the namespace and storage ownership once. Use a systemd ownership workaround only for a specific package-managed path that is demonstrably reset during upgrades.
:::

### Completion checklist

- [ ] CT 104 starts with the documented ID map.
- [ ] The `debian-transmission` account can create a file in the mounted download directory.
- [ ] The host observes the expected mapped ownership.
- [ ] `transmission-daemon` is active and its recent logs contain no permission errors.
- [ ] A test download is written to `/media_storage/Downloads`.

## Helper-script updates

Use the updater supplied inside the helper-created LXC rather than replacing the native installation:

```bash
cd /root
command -v update
update
```

Take a Proxmox backup first. After updating, repeat the `id debian-transmission`, mount write, service-status, and log checks because a changed service UID/GID would invalidate the custom map.

## Rollback

Run on the **Proxmox host**:

```bash
pct stop 104
cp /root/104.conf.before-transmission-idmap /etc/pve/lxc/104.conf
pct start 104
```

Restoring the configuration does not restore filesystem ownership. Change the download directory back only after identifying which host UID/GID should own it.

## References

- [Proxmox VE container toolkit documentation](https://pve.proxmox.com/pve-docs/pct.1.html)
- [Transmission configuration-file guidance](https://github.com/transmission/transmission/blob/main/docs/Editing-Configuration-Files.md?plain=1)
