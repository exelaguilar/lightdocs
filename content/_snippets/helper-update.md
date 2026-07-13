:::callout type="warning" title="Back up before updating"
Take a current Proxmox backup or snapshot first. Run the helper-provided updater from `/root`, then verify service status, logs, mounts, and application access.
:::

```bash
cd /root
command -v update
update
```

Record the helper version or update result in the page revision or Git commit message when repository history is enabled.
