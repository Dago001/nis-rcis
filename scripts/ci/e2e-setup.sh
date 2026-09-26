#!/usr/bin/env bash
# Prepares a throw-away copy of NIS-RCIS for the end-to-end tests (CI):
# local settings, demo data, OAuth2 clients, and both servers started.
# Expects PostgreSQL on 127.0.0.1 with user/password nis_rcis/secret.
set -euo pipefail
cd "$(dirname "$0")/../.."
root=$(pwd)

cd "$root/backend"
cp .env.example .env
set_env() { # key value
  if grep -qE "^#?\s*$1=" .env; then sed -i -E "s|^#?\s*$1=.*|$1=$2|" .env; else echo "$1=$2" >> .env; fi
}
set_env APP_ENV local
set_env APP_DEBUG false
set_env APP_URL http://127.0.0.1:8000
set_env FRONTEND_URL http://localhost:3000
set_env DB_CONNECTION pgsql
set_env DB_HOST 127.0.0.1
set_env DB_DATABASE "${E2E_DB:-nis_rcis}"
set_env DB_USERNAME nis_rcis
set_env DB_PASSWORD secret
set_env SESSION_DRIVER file
set_env CACHE_STORE file
set_env QUEUE_CONNECTION sync
set_env MAIL_MAILER log
set_env DOCUMENTS_DISK local
set_env PAYMENTS_FAKE true
set_env SKIP_EMAIL_VERIFICATION true
set_env PHOTO_QUALITY_CHECK false
set_env ALERT_DISK_MIN_FREE_GB 1

php artisan key:generate --force
php artisan passport:keys --force
php artisan migrate --seed --force
php artisan nis:demo

clients=$(php artisan nis:oauth-clients --frontend=http://localhost:3000 | grep -E '^OAUTH_[A-Z_]+=')
{
  echo "APP_URL=http://localhost:3000"
  echo "API_URL=http://127.0.0.1:8000"
  echo "API_PUBLIC_URL=http://127.0.0.1:8000"
  echo "SESSION_SECRET=$(openssl rand -base64 48 | tr -d '\n')"
  echo "$clients"
} > "$root/frontend/.env.local"

nohup php artisan serve --host=127.0.0.1 --port=8000 > "$root/backend/storage/logs/serve.log" 2>&1 &

cd "$root/frontend"
npm run build
nohup npm start -- -p 3000 > "$root/frontend/next.log" 2>&1 &

for _ in $(seq 1 60); do
  if curl -fs http://127.0.0.1:8000/up > /dev/null && curl -fs http://localhost:3000/ > /dev/null; then
    echo "Both servers are up."
    exit 0
  fi
  sleep 2
done
echo "The servers did not start." >&2
tail -50 "$root/backend/storage/logs/serve.log" "$root/frontend/next.log" >&2
exit 1
