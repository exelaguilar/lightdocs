---
title: Proxmox WireGuard Egress Gateways
description: Route selected LXCs through dedicated WireGuard gateways with a tested IPv4 kill switch.
order: 1
type: runbook
reviewed: 2026-07-12
review_after: 180
keywords: [proxmox, lxc, wireguard, vpn, gateway, kill switch]
related:
  - /proxmox/transmission
service:
  type: network stack
  id: 110 / 112
  application: WireGuard Egress Gateways
  address: 192.168.1.248 / 192.168.1.249
  installation: helper-script
  context: Proxmox host + gateway LXCs
---

# Proxmox WireGuard Egress Gateways

This design routes selected client LXCs through dedicated unprivileged WireGuard gateway LXCs:

| Workload | Client | VPN gateway |
|---|---|---|
| qBittorrent | `192.168.1.104` | CT 112 at `192.168.1.249` |
| Browser | `192.168.1.115` | CT 110 at `192.168.1.248` |

These LXCs were created with Proxmox VE Community Scripts. WireGuard and qBittorrent are native LXC services; this guide extends those installations and does not replace them with Docker workloads.

The gateway performs source NAT to `wg0`. Its forwarding policy drops client Internet traffic whenever the tunnel is unavailable.

:::callout type="warning" title="This is VPN egress, not full network isolation"
The clients and gateways are all on `192.168.1.0/24`. Each client therefore has a directly connected route to every other address on that LAN, bypassing its default gateway. The design protects off-subnet Internet egress, but it does not isolate the client from the LAN or force same-subnet DNS through WireGuard.
:::

For strict isolation, place each client/gateway pair on a dedicated Proxmox bridge or VLAN with no direct path to the normal LAN. Keep the gateway’s management interface separate from the client-facing interface. That is the recommended future topology; the steps below document the current single-LAN deployment accurately.

## 1. Before you begin

For each stack, record:

- the client IP and gateway IP;
- the actual LXC interface name (`ip -br link`);
- the VPN provider’s DNS address;
- the expected VPN public IP;
- a console path that does not depend on the VPN route.

Back up both LXC configurations on the **Proxmox host**:

```bash
cp /etc/pve/lxc/110.conf /root/110.conf.before-vpn
cp /etc/pve/lxc/112.conf /root/112.conf.before-vpn
```

Use Proxmox firewall rules to restrict management services. A forwarding kill switch does not protect SSH, noVNC, or other ports listening on the gateway or client.

## 2. Disable IPv6 only if the VPN is IPv4-only

If the provider configuration does not route IPv6, disable it on every client and gateway to avoid an IPv6 default route outside the tunnel. Run inside each affected **LXC**:

```bash
nano /etc/sysctl.d/99-vpn-ipv6.conf
```

```ini
net.ipv6.conf.all.disable_ipv6 = 1
net.ipv6.conf.default.disable_ipv6 = 1
```

```bash
sysctl --system
```

Do not suppress errors with `|| true`; a failed setting must be visible. If your VPN supports IPv6, route and filter it instead of disabling it.

## 3. Configure each gateway LXC

Run inside **CT 110** and **CT 112**.

### Verify the helper installation and add missing tools

```bash
command -v wg
command -v wg-quick
systemctl list-unit-files | grep -E 'wg-quick|wgdashboard'
cp -a /etc/wireguard /root/wireguard.before-egress-routing
apt update
apt install -y openresolv iptables iptables-persistent curl dnsutils
```

The helper installation uses `/etc/wireguard/wg0.conf`. Back up an existing configuration before replacing it with the provider configuration, then protect it:

```bash
chown root:root /etc/wireguard/wg0.conf
chmod 0600 /etc/wireguard/wg0.conf
wg-quick up wg0
wg show
```

Do not paste private keys into documentation or tickets.

### Enable IPv4 forwarding

```bash
nano /etc/sysctl.d/99-vpn-gateway.conf
```

```ini
net.ipv4.ip_forward = 1
```

```bash
sysctl --system
```

### Install a client-scoped forwarding policy

First preserve the existing rules:

```bash
iptables-save > /root/iptables.before-vpn.rules
```

The gateway LXC should be dedicated to this role. Replace `CLIENT_IP` below with `192.168.1.104` on CT 112 or `192.168.1.115` on CT 110, and verify that the LAN interface is actually `eth0`.

```bash
CLIENT_IP=192.168.1.104

iptables -t nat -A POSTROUTING -s "${CLIENT_IP}/32" -o wg0 -j MASQUERADE
iptables -A FORWARD -i eth0 -s "${CLIENT_IP}/32" -o wg0 -m conntrack --ctstate NEW,ESTABLISHED,RELATED -j ACCEPT
iptables -A FORWARD -i wg0 -o eth0 -d "${CLIENT_IP}/32" -m conntrack --ctstate ESTABLISHED,RELATED -j ACCEPT
iptables -P FORWARD DROP
netfilter-persistent save
```

These rules are deliberately scoped to one client. Do not use an unrestricted `eth0 -> wg0` rule on a shared gateway.

Enable the tunnel at boot and verify both services:

```bash
systemctl enable wg-quick@wg0
systemctl status wg-quick@wg0 --no-pager
iptables -S FORWARD
iptables -t nat -S POSTROUTING
```

:::callout type="info" title="Re-running the rule commands"
The commands append rules. Do not run them repeatedly. If the rules need changing, edit the saved policy deliberately or restore `/root/iptables.before-vpn.rules`, then install the corrected rules once.
:::

## 4. Point each client at its gateway

Configure the client’s default route through its matching VPN gateway. For the qBittorrent client, `/etc/network/interfaces` is:

```text
auto eth0
iface eth0 inet static
    address 192.168.1.104/24
    gateway 192.168.1.249
```

For the browser client:

```text
auto eth0
iface eth0 inet static
    address 192.168.1.115/24
    gateway 192.168.1.248
```

Restart networking from a Proxmox console because an incorrect route can disconnect SSH. Confirm inside each **client LXC**:

```bash
ip route
ip route get 1.1.1.1
```

The default route must name the matching VPN gateway.

### DNS behavior

A DNS server on `192.168.1.0/24` is reached directly, not through the gateway. For DNS privacy, use a resolver address supplied through the tunnel that is outside the local subnet, or run a resolver on the VPN gateway and explicitly permit only the client-to-gateway DNS traffic. Do not claim a DNS kill switch until this path has been tested.

## 5. qBittorrent storage

The qBittorrent storage bind is configured on the **Proxmox host** in `/etc/pve/lxc/104.conf`:

```ini
mp0: /mnt/pve/media_storage,mp=/media_storage
```

Use the separate Transmission/qBittorrent storage guide to validate unprivileged ID mapping and directory ownership. Network routing does not change storage permissions.

## 6. Browser LXC with noVNC

The browser desktop is optional. Install it inside CT 115:

```bash
apt update
apt install -y xfce4 tigervnc-standalone-server tigervnc-common novnc websockify chromium dbus-x11
adduser --disabled-password --gecos '' vpnuser
install -d -o vpnuser -g vpnuser -m 0700 /home/vpnuser/.vnc /home/vpnuser/.local/run
runuser -u vpnuser -- vncpasswd
```

Create `/home/vpnuser/.vnc/xstartup`:

```bash
#!/bin/sh
unset SESSION_MANAGER
unset DBUS_SESSION_BUS_ADDRESS
exec dbus-run-session startxfce4
```

```bash
chown vpnuser:vpnuser /home/vpnuser/.vnc/xstartup
chmod 0700 /home/vpnuser/.vnc/xstartup
```

Create `/usr/local/bin/start-vpn-browser`:

```bash
#!/bin/sh
set -eu
export HOME=/home/vpnuser
export USER=vpnuser
export XDG_RUNTIME_DIR=/home/vpnuser/.local/run

vncserver :1 -geometry 1920x1080 -depth 24 -localhost yes
websockify --web=/usr/share/novnc 6080 localhost:5901 &
WEBSOCKIFY_PID=$!

trap 'vncserver -kill :1 >/dev/null 2>&1 || true; kill "$WEBSOCKIFY_PID" >/dev/null 2>&1 || true' INT TERM EXIT
while ! DISPLAY=:1 chromium --start-maximized --no-first-run --password-store=basic about:blank; do
    sleep 2
done
```

```bash
chown root:root /usr/local/bin/start-vpn-browser
chmod 0755 /usr/local/bin/start-vpn-browser
```

Create `/etc/systemd/system/vpn-browser.service`:

```ini
[Unit]
Description=VPN browser desktop
Wants=network-online.target
After=network-online.target

[Service]
Type=simple
User=vpnuser
Group=vpnuser
WorkingDirectory=/home/vpnuser
ExecStart=/usr/local/bin/start-vpn-browser
Restart=on-failure
RestartSec=5
KillMode=control-group

[Install]
WantedBy=multi-user.target
```

```bash
systemctl daemon-reload
systemctl enable --now vpn-browser.service
systemctl status vpn-browser.service --no-pager
```

This keeps TigerVNC on loopback, retains VNC authentication, and runs Chromium with its sandbox enabled. Port `6080` is still plain HTTP by default: restrict it to a trusted management network with the Proxmox firewall, or place it behind an authenticated HTTPS reverse proxy. Never expose it directly to the Internet.

## 7. Test normal operation and failure behavior

Run the first four checks inside each **client LXC**:

| Check | Command | Expected result |
|---|---|---|
| Route | `ip route get 1.1.1.1` | Uses the matching gateway LXC |
| Public IPv4 | `curl -4 https://icanhazip.com` | VPN provider address |
| IPv6 | `curl -6 --max-time 10 https://icanhazip.com` | Fails when IPv6 is intentionally disabled |
| DNS egress | `dig +short whoami.akamai.net @ns1-1.akamaitech.net` | VPN public address, not the normal WAN address |

Then test the kill switch from a Proxmox console:

1. On the **gateway LXC**, run `wg-quick down wg0`.
2. On its **client LXC**, run `curl -4 --max-time 10 https://icanhazip.com`.
3. Confirm the request fails rather than showing the normal WAN address.
4. On the gateway, run `wg-quick up wg0` and repeat the public-IP test.

Also test access to a same-subnet address. It will still work in this topology; that is expected and demonstrates why the design is not full LAN isolation.

### Completion checklist

- [ ] Each client resolves DNS and reports the intended VPN public IPv4 address.
- [ ] IPv6 behaves according to the documented provider design.
- [ ] Bringing `wg0` down blocks public Internet access instead of leaking through the normal WAN.
- [ ] Bringing `wg0` back up restores the expected route.
- [ ] Same-subnet access matches the documented, intentionally limited isolation boundary.

## Rollback

On a gateway, restore the saved firewall policy and disable automatic WireGuard startup:

```bash
iptables-restore < /root/iptables.before-vpn.rules
netfilter-persistent save
systemctl disable --now wg-quick@wg0
```

Restore each client’s original default gateway from the Proxmox console. If necessary, restore the backed-up CT configurations on the **Proxmox host**.

## Helper-script updates

Run helper-provided application updates from `/root` inside the relevant LXC, after a Proxmox backup:

```bash
cd /root
command -v update
update
```

After a WireGuard update, confirm `/etc/wireguard/wg0.conf`, the saved netfilter rules, forwarding, and the kill-switch test. After a qBittorrent update, confirm its native service user, storage access, listening address, and VPN public IP. Do not introduce Docker update instructions into either LXC.

## References

- [Proxmox VE container toolkit documentation](https://pve.proxmox.com/pve-docs/pct.1.html)
- [WireGuard quick-start documentation](https://www.wireguard.com/quickstart/)
- [Community Scripts WireGuard LXC](https://community-scripts.org/scripts/wireguard)
- [Community Scripts qBittorrent LXC](https://community-scripts.org/scripts/qbittorrent)
