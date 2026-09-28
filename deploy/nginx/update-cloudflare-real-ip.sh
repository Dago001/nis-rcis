#!/usr/bin/env bash
# Regenerates /etc/nginx/snippets/cloudflare-real-ip.conf from Cloudflare's
# published IP ranges so $remote_addr is the real visitor IP (needed for
# rate limiting and the audit trail). Run from cron weekly.
set -euo pipefail

out=/etc/nginx/snippets/cloudflare-real-ip.conf
tmp=$(mktemp)
{
  echo "# Generated $(date -u +%FT%TZ) from https://www.cloudflare.com/ips/"
  for ip in $(curl -fsS https://www.cloudflare.com/ips-v4) $(curl -fsS https://www.cloudflare.com/ips-v6); do
    echo "set_real_ip_from ${ip};"
  done
  echo "real_ip_header CF-Connecting-IP;"
} > "$tmp"

install -m 644 "$tmp" "$out"
rm -f "$tmp"
nginx -t && systemctl reload nginx
