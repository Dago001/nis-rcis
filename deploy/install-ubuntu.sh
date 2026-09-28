#!/usr/bin/env bash
# One-time provisioning of an Ubuntu 24.04 server for NIS-RCIS (no Docker).
# Installs: Nginx, PHP 8.5-FPM, PostgreSQL 16, Redis, Node.js 22.
# Run as root. Review before running in production.
set -euo pipefail

DB_PASSWORD=${DB_PASSWORD:?Set DB_PASSWORD (e.g. DB_PASSWORD=$(openssl rand -base64 32))}

apt-get update
apt-get install -y software-properties-common curl gnupg unzip git ca-certificates

# PHP 8.5 (ondrej/php PPA)
add-apt-repository -y ppa:ondrej/php
apt-get update
apt-get install -y php8.5-fpm php8.5-cli php8.5-pgsql php8.5-redis php8.5-mbstring php8.5-xml \
  php8.5-curl php8.5-intl php8.5-gd php8.5-zip php8.5-bcmath

# Composer
curl -fsSL https://getcomposer.org/installer | php -- --install-dir=/usr/local/bin --filename=composer

# Node.js 22 LTS
curl -fsSL https://deb.nodesource.com/setup_22.x | bash -
apt-get install -y nodejs

# Nginx, PostgreSQL, Redis
apt-get install -y nginx postgresql redis-server
systemctl enable --now nginx postgresql redis-server php8.5-fpm

# Service account and directories
id nis-rcis >/dev/null 2>&1 || useradd --system --create-home --home-dir /var/www/nis-rcis --shell /usr/sbin/nologin nis-rcis
install -d -o nis-rcis -g nis-rcis -m 750 /var/www/nis-rcis /var/www/nis-rcis/releases /var/www/nis-rcis/shared
install -d -o nis-rcis -g nis-rcis -m 750 /var/www/nis-rcis/shared/storage
install -d -o root -g nis-rcis -m 750 /etc/nis-rcis

# Database (local connections only)
sudo -u postgres psql -v ON_ERROR_STOP=1 <<SQL
DO \$\$ BEGIN
  IF NOT EXISTS (SELECT FROM pg_roles WHERE rolname = 'nis_rcis') THEN
    CREATE ROLE nis_rcis LOGIN PASSWORD '${DB_PASSWORD}';
  END IF;
END \$\$;
SQL
sudo -u postgres createdb -O nis_rcis nis_rcis 2>/dev/null || true

# Redis: local only, password recommended (set requirepass in /etc/redis/redis.conf)

# ---- Sizing for this server
here=$(cd "$(dirname "$0")" && pwd)
cores=$(nproc)
mem_mb=$(awk '/MemTotal/ {print int($2 / 1024)}' /proc/meminfo)
# PHP workers: ~64 MB each within 40% of memory, 8 per core at most, and at most 80
# so they plus the queue worker and maintenance stay under PostgreSQL's 100 connections.
php_children=$(( mem_mb * 40 / 100 / 64 ))
(( php_children > cores * 8 )) && php_children=$(( cores * 8 ))
(( php_children > 80 )) && php_children=80
(( php_children < 8 )) && php_children=8
# Next.js: one process per core (Node.js uses one core per process), 2 to 8.
web_instances=$cores
(( web_instances < 2 )) && web_instances=2
(( web_instances > 8 )) && web_instances=8
echo "Sizing: ${cores} cores, ${mem_mb} MB -> ${php_children} PHP workers, ${web_instances} Next.js processes"

# PHP-FPM pool and OPcache
sed "s/@CHILDREN@/${php_children}/" "$here/php-fpm-pool.conf" > /etc/php/8.5/fpm/pool.d/nis-rcis.conf
chmod 644 /etc/php/8.5/fpm/pool.d/nis-rcis.conf
install -m 644 "$here/php-opcache.ini" /etc/php/8.5/fpm/conf.d/99-nis-rcis.ini
rm -f /etc/php/8.5/fpm/pool.d/www.conf
systemctl restart php8.5-fpm

# Nginx: more connections per worker, compression, and the list of Next.js processes
sed -i -E 's/^(\s*)worker_connections\s+[0-9]+;/\1worker_connections 8192;/' /etc/nginx/nginx.conf
grep -q '^worker_rlimit_nofile' /etc/nginx/nginx.conf || sed -i '1i worker_rlimit_nofile 65535;' /etc/nginx/nginx.conf
install -m 644 "$here/nginx/performance.conf" /etc/nginx/conf.d/nis-rcis-performance.conf
{
  echo "# Written by install-ubuntu.sh: one line per nis-rcis-web@PORT service."
  echo "upstream nis_rcis_next {"
  echo "    least_conn;"
  for ((i = 0; i < web_instances; i++)); do echo "    server 127.0.0.1:$((3000 + i)) max_fails=3 fail_timeout=10s;"; done
  echo "    keepalive 64;"
  echo "}"
} > /etc/nginx/snippets/nis-rcis-next-upstream.conf

"$here/nginx/update-cloudflare-real-ip.sh" || true
# Install a site; on a re-run keep the real domain already set in its server_name.
install_site() {
  local name=$1 example=$2 target=/etc/nginx/sites-available/$1.conf current=""
  [ -f "$target" ] && current=$(grep -m1 -oE 'server_name[[:space:]]+[^;]+' "$target" | sed -E 's/server_name[[:space:]]+//')
  install -m 644 "$here/nginx/$name.conf" "$target"
  if [ -n "$current" ] && [ "$current" != "$example" ]; then
    sed -i "s/server_name $example;/server_name $current;/" "$target"
  fi
  ln -sf "$target" /etc/nginx/sites-enabled/
}
install_site nis-rcis-api api.rcis.example.gov.ng
install_site nis-rcis-web rcis.example.gov.ng
rm -f /etc/nginx/sites-enabled/default

# Servers set up before per-core Next.js processes ran a single nis-rcis-web.service.
if [ -f /etc/systemd/system/nis-rcis-web.service ]; then
  systemctl disable --now nis-rcis-web.service || true
  rm -f /etc/systemd/system/nis-rcis-web.service
fi
install -m 644 "$here"/systemd/*.service "$here"/systemd/*.timer /etc/systemd/system/
systemctl daemon-reload
systemctl enable nis-rcis-queue.service nis-rcis-scheduler.timer
for ((i = 0; i < web_instances; i++)); do systemctl enable "nis-rcis-web@$((3000 + i)).service"; done
# Fewer cores than last time: retire the extra processes.
for ((i = web_instances; i < 8; i++)); do systemctl disable --now "nis-rcis-web@$((3000 + i)).service" 2>/dev/null || true; done

# Re-run on a live server: bring the website back up on the new layout straight away.
if [ -e /var/www/nis-rcis/current ]; then
  for ((i = 0; i < web_instances; i++)); do systemctl restart "nis-rcis-web@$((3000 + i)).service"; done
  nginx -t && systemctl reload nginx
fi

# Weekly refresh of Cloudflare IP ranges
echo "0 4 * * 1 root $here/nginx/update-cloudflare-real-ip.sh >/dev/null 2>&1" > /etc/cron.d/nis-rcis-cloudflare

echo
echo "Provisioned. Next:"
echo "  1. Put TLS certificate at /etc/ssl/nis-rcis/origin.{pem,key} and set server_name in the Nginx configs"
echo "  2. Create /etc/nis-rcis/backend.env and /etc/nis-rcis/frontend.env (see README)"
echo "  3. Run deploy/deploy.sh"
