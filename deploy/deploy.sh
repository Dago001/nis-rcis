#!/usr/bin/env bash
# Build and activate a release (zero-downtime symlink switch). Run as root.
#   deploy/deploy.sh [git-ref]
set -euo pipefail

REF=${1:-main}
REPO=${REPO:-$(git -C "$(dirname "$0")/.." remote get-url origin)}
BASE=/var/www/nis-rcis
RELEASE=$BASE/releases/$(date +%Y%m%d%H%M%S)
as() { sudo -u nis-rcis -H "$@"; }

as git clone --depth 1 --branch "$REF" "$REPO" "$RELEASE"

# ---- Backend
ln -sfn /etc/nis-rcis/backend.env "$RELEASE/backend/.env"
rm -rf "$RELEASE/backend/storage"
ln -sfn "$BASE/shared/storage" "$RELEASE/backend/storage"
as mkdir -p "$BASE/shared/storage"/{app/private,framework/{cache,sessions,views},logs}
(cd "$RELEASE/backend" && as composer install --no-dev --optimize-autoloader --no-interaction)
[ -f "$BASE/shared/storage/oauth-private.key" ] || (cd "$RELEASE/backend" && as php artisan passport:keys)
chmod 600 "$BASE/shared/storage/oauth-private.key"
(cd "$RELEASE/backend" && as php artisan migrate --force && as php artisan db:seed --force \
  && as php artisan config:cache && as php artisan route:cache && as php artisan view:cache)

# ---- Frontend (Next.js standalone build)
ln -sfn /etc/nis-rcis/frontend.env "$RELEASE/frontend/.env.production.local"
(cd "$RELEASE/frontend" && as npm ci && as npm run build)
as cp -r "$RELEASE/frontend/public" "$RELEASE/frontend/.next/standalone/public"
as cp -r "$RELEASE/frontend/.next/static" "$RELEASE/frontend/.next/standalone/.next/static"

# ---- Activate
ln -sfn "$RELEASE" "$BASE/current"
systemctl reload php8.5-fpm
systemctl restart nis-rcis-queue nis-rcis-web
nginx -t && systemctl reload nginx

# Keep the five most recent releases
ls -1dt "$BASE"/releases/* | tail -n +6 | xargs -r rm -rf
echo "Deployed $REF -> $RELEASE"
