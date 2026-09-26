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

# PHP-FPM pool, Nginx sites, systemd units
here=$(cd "$(dirname "$0")" && pwd)
install -m 644 "$here/php-fpm-pool.conf" /etc/php/8.5/fpm/pool.d/nis-rcis.conf
rm -f /etc/php/8.5/fpm/pool.d/www.conf
systemctl restart php8.5-fpm

"$here/nginx/update-cloudflare-real-ip.sh" || true
install -m 644 "$here/nginx/nis-rcis-api.conf" /etc/nginx/sites-available/nis-rcis-api.conf
install -m 644 "$here/nginx/nis-rcis-web.conf" /etc/nginx/sites-available/nis-rcis-web.conf
ln -sf /etc/nginx/sites-available/nis-rcis-api.conf /etc/nginx/sites-enabled/
ln -sf /etc/nginx/sites-available/nis-rcis-web.conf /etc/nginx/sites-enabled/
rm -f /etc/nginx/sites-enabled/default

install -m 644 "$here"/systemd/*.service "$here"/systemd/*.timer /etc/systemd/system/
systemctl daemon-reload
systemctl enable nis-rcis-queue.service nis-rcis-web.service nis-rcis-scheduler.timer

# Weekly refresh of Cloudflare IP ranges
echo "0 4 * * 1 root $here/nginx/update-cloudflare-real-ip.sh >/dev/null 2>&1" > /etc/cron.d/nis-rcis-cloudflare

echo
echo "Provisioned. Next:"
echo "  1. Put TLS certificate at /etc/ssl/nis-rcis/origin.{pem,key} and set server_name in the Nginx configs"
echo "  2. Create /etc/nis-rcis/backend.env and /etc/nis-rcis/frontend.env (see README)"
echo "  3. Run deploy/deploy.sh"
