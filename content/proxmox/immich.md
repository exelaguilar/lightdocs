---
title: Immich Helper-Script LXC Storage and Intel GPU
description: Move a native Community Scripts Immich installation to Proxmox storage and expose Intel hardware acceleration.
order: 3
type: runbook
reviewed: 2026-07-12
review_after: 180
keywords: [proxmox, lxc, immich, storage, intel gpu, systemd]
related:
  - /proxmox/transmission
service:
  type: lxc
  id: 107
  application: Immich
  installation: helper-script
  context: Proxmox host + CT 107
---

# Immich Helper-Script LXC Storage and Intel GPU

This guide covers **CT 107**, installed with the Proxmox VE Community Scripts Immich helper. It is a native LXC installation—not Docker Compose.

| Component | Helper-script location |
|---|---|
| Configuration | `/opt/immich/.env` |
| Application | `/opt/immich/app` |
| Default media | `/opt/immich/upload` |
| Web service | `immich-web.service` |
| Machine learning | `immich-ml.service` |
| Logs | `/var/log/immich` |

:::callout type="warning" title="Do not use Docker instructions in this LXC"
Commands such as `docker compose down`, Compose volume mappings, `UPLOAD_LOCATION`, and `hwaccel.transcoding.yml` apply to upstream Docker installations. They do not manage the Community Scripts native LXC layout.
:::

## 1. Confirm the installed layout

Run inside **CT 107** before changing anything:

```bash
systemctl status immich-web immich-ml --no-pager
systemctl cat immich-web immich-ml
id immich
grep '^IMMICH_MEDIA_LOCATION=' /opt/immich/.env
readlink -f /opt/immich/app/upload
readlink -f /opt/immich/app/machine-learning/upload
```

If these units or paths do not exist, stop and document the actual helper-script version and layout. Do not force this procedure onto a different installation model.

## 2. Back up before changing storage

An Immich recovery needs both the database and media. Make a current Proxmox backup or snapshot of CT 107 and preserve a separate copy of the media.

On the **Proxmox host**:

```bash
cp /etc/pve/lxc/107.conf /root/107.conf.before-immich-storage
```

Also record the current service and path state inside **CT 107**:

```bash
systemctl is-active immich-web immich-ml
grep '^IMMICH_MEDIA_LOCATION=' /opt/immich/.env
du -sh /opt/immich/upload
```

Do not delete `/opt/immich/upload` until the migrated library has been tested and backed up.

## 3. Add the Proxmox bind mount

Stop CT 107 and edit its configuration on the **Proxmox host**:

```bash
pct stop 107
nano /etc/pve/lxc/107.conf
```

Add the next unused mount-point number:

```ini
mp0: /mnt/pve/media_storage,mp=/media_storage
```

Create the application-specific destination and restart the LXC:

```bash
mkdir -p /mnt/pve/media_storage/Immich
pct start 107
```

## 4. Establish the correct ownership

The helper runs Immich as the native `immich` user. Confirm its numeric IDs inside **CT 107**:

```bash
id immich
```

Test how that account maps to the host:

```bash
runuser -u immich -- touch /media_storage/Immich/.immich-write-test
stat -c '%u:%g %a %n' /media_storage/Immich/.immich-write-test
```

On the **Proxmox host**:

```bash
stat -c '%u:%g %a %n' /mnt/pve/media_storage/Immich/.immich-write-test
rm /mnt/pve/media_storage/Immich/.immich-write-test
```

If the test fails, correct the destination ownership using the observed mapped host UID/GID. With a default unprivileged map, LXC ID `N` normally appears as host ID `100000 + N`; custom maps invalidate that shortcut.

Do not recursively change ownership of the entire media share. Restrict changes to the Immich directory, and keep files non-executable:

```bash
chown -R MAPPED_UID:MAPPED_GID /mnt/pve/media_storage/Immich
find /mnt/pve/media_storage/Immich -type d -exec chmod 2775 {} \;
find /mnt/pve/media_storage/Immich -type f -exec chmod 0664 {} \;
```

Replace `MAPPED_UID:MAPPED_GID` with the values proven by the test.

## 5. Stop the native services and copy media

Inside **CT 107**:

```bash
systemctl stop immich-web immich-ml
systemctl is-active immich-web immich-ml
apt-get install -y rsync
rsync -aH --no-owner --no-group --info=progress2 /opt/immich/upload/ /media_storage/Immich/
```

The second command should report both services as `inactive`. The trailing slashes copy all contents, including dotfiles. Ownership is deliberately not carried across the LXC bind-mount boundary.

## 6. Update the helper-script media path

Back up and edit `/opt/immich/.env` inside **CT 107**:

```bash
cp /opt/immich/.env /opt/immich/.env.before-media-move
nano /opt/immich/.env
```

Set the helper-specific native path:

```ini
IMMICH_MEDIA_LOCATION=/media_storage/Immich
```

Confirm the two helper-managed paths are symlinks before replacing them:

```bash
test -L /opt/immich/app/upload
test -L /opt/immich/app/machine-learning/upload
```

If either test fails, stop and inspect that path; do not overwrite a real directory. Once confirmed, retarget the symlinks:

```bash
ln -sfn /media_storage/Immich /opt/immich/app/upload
ln -sfn /media_storage/Immich /opt/immich/app/machine-learning/upload
chown -h immich:immich /opt/immich/app/upload /opt/immich/app/machine-learning/upload
```

Confirm the resolved targets before starting Immich:

```bash
readlink -f /opt/immich/app/upload
readlink -f /opt/immich/app/machine-learning/upload
runuser -u immich -- test -w /media_storage/Immich
```

## 7. Start and verify Immich

```bash
systemctl start immich-ml immich-web
systemctl status immich-web immich-ml --no-pager
tail -n 100 /var/log/immich/web.log
tail -n 100 /var/log/immich/ml.log
```

Verify all of the following in the web interface. These tasks drive the page's local runbook progress:

- [ ] Existing thumbnails and full-size assets open.
- [ ] Videos play.
- [ ] A new upload succeeds and appears below `/media_storage/Immich`.
- [ ] Background jobs complete without path or permission errors.
- [ ] Both native systemd services remain active after verification.

Compare the old and new trees:

```bash
du -sh /opt/immich/upload /media_storage/Immich
find /opt/immich/upload -type f | wc -l
find /media_storage/Immich -type f | wc -l
```

Because `/opt/immich/app/upload` is now a symlink, use the saved original media path when comparing. Keep the original copy through a successful verification period.

## 8. Expose the Intel render device

On the **Proxmox host**, confirm the device numbers and group:

```bash
stat -c '%t:%T %g %n' /dev/dri/renderD128
```

Stop CT 107 and add the device to `/etc/pve/lxc/107.conf`:

```ini
lxc.cgroup2.devices.allow: c 226:128 rwm
lxc.mount.entry: /dev/dri/renderD128 dev/dri/renderD128 none bind,optional,create=file
```

Start the LXC and inspect the device from inside:

```bash
pct start 107
pct exec 107 -- ls -ln /dev/dri/renderD128
pct exec 107 -- id immich
```

The native `immich` user must have read/write access to the render node. Match the device's numeric group inside the LXC or use an explicit Proxmox ID map; do not make the device world-writable.

The Community Scripts installation already supports hardware-accelerated transcoding. Enable the Intel Quick Sync or VAAPI option in the Immich administration settings, then run a transcode and inspect:

```bash
tail -f /var/log/immich/web.log
```

Intel OpenVINO for machine learning is a separate helper-script installation choice and uses additional memory. Do not add Docker ML profiles to this LXC.

## Helper-script updates

Use the update function supplied by the Community Scripts installation rather than upstream Docker upgrade commands. Run it from a neutral directory such as `/root`, take a backup first, and allow for the helper's pinned Immich version:

```bash
cd /root
update
```

After updating, re-check `/opt/immich/.env`, both upload symlinks, the two systemd units, and the logs. Never run an updater while your shell is inside a directory that the updater may replace.

## Rollback

Stop `immich-web` and `immich-ml`, restore `/opt/immich/.env.before-media-move`, retarget both upload symlinks to the previous media directory, and restart the services. If necessary, stop CT 107 and restore `/root/107.conf.before-immich-storage` on the Proxmox host.

Never delete the only known-good media tree during rollback.

## References

- [Community Scripts Immich installation notes](https://community-scripts.org/scripts/immich)
- [Community Scripts Immich media-location discussion](https://github.com/community-scripts/ProxmoxVE/discussions/5075)
- [Proxmox VE container toolkit documentation](https://pve.proxmox.com/pve-docs/pct.1.html)
