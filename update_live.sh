#!/usr/bin/env bash
set -e

echo "=========================================================="
echo " NIS-RCIS: Applying Temporary Residence Slip Update"
echo "=========================================================="

FRONTEND_DIR="/home/nimcosel/domains/rcis.nimcoselection.com/public_html/frontend"
BACKEND_DIR="/home/nimcosel/domains/rcis.nimcoselection.com/app/backend"
BUILD_DIR="/home/nimcosel/domains/rcis.nimcoselection.com/public_html/build"

# Step 1: Update Frontend Next.js Build
echo "[1/4] Updating Next.js Frontend Application..."
if [ -d "$FRONTEND_DIR" ]; then
    cd "$FRONTEND_DIR"
    echo "  -> Downloading latest frontend release package..."
    curl -sL https://raw.githubusercontent.com/Dago001/nis-rcis/frontend-dist/next_update.tar.gz -o next_update.tar.gz
    echo "  -> Extracting files..."
    tar -xzf next_update.tar.gz
    rm -f next_update.tar.gz
    mkdir -p tmp
    touch tmp/restart.txt
    echo "  -> Frontend updated successfully."
else
    echo "  [SKIP] $FRONTEND_DIR not found."
fi

# Step 2: Update Backend Controllers & Configuration
echo "[2/4] Updating Backend Controllers & Services..."
if [ -d "$BACKEND_DIR" ]; then
    cd "$BACKEND_DIR"
    mkdir -p app/Rules app/Http/Controllers/Api/Staff app/Http/Controllers/Api/Applicant app/Http/Controllers/Api/Public app/Http/Controllers/Auth app/Http/Middleware resources/views/auth config routes
    curl -sL https://raw.githubusercontent.com/Dago001/nis-rcis/main/backend/routes/api.php -o routes/api.php
    curl -sL https://raw.githubusercontent.com/Dago001/nis-rcis/main/backend/app/Rules/Turnstile.php -o app/Rules/Turnstile.php
    curl -sL https://raw.githubusercontent.com/Dago001/nis-rcis/main/backend/config/services.php -o config/services.php
    curl -sL https://raw.githubusercontent.com/Dago001/nis-rcis/main/backend/app/Http/Middleware/SecurityHeaders.php -o app/Http/Middleware/SecurityHeaders.php
    curl -sL https://raw.githubusercontent.com/Dago001/nis-rcis/main/backend/app/Http/Controllers/Auth/ApplicantLoginController.php -o app/Http/Controllers/Auth/ApplicantLoginController.php
    curl -sL https://raw.githubusercontent.com/Dago001/nis-rcis/main/backend/app/Http/Controllers/Auth/StaffLoginController.php -o app/Http/Controllers/Auth/StaffLoginController.php
    curl -sL https://raw.githubusercontent.com/Dago001/nis-rcis/main/backend/app/Http/Controllers/Api/Applicant/AccountController.php -o app/Http/Controllers/Api/Applicant/AccountController.php
    curl -sL https://raw.githubusercontent.com/Dago001/nis-rcis/main/backend/app/Http/Controllers/Api/Public/PublicController.php -o app/Http/Controllers/Api/Public/PublicController.php
    curl -sL https://raw.githubusercontent.com/Dago001/nis-rcis/main/backend/resources/views/layout.blade.php -o resources/views/layout.blade.php
    curl -sL https://raw.githubusercontent.com/Dago001/nis-rcis/main/backend/resources/views/auth/applicant-login.blade.php -o resources/views/auth/applicant-login.blade.php
    curl -sL https://raw.githubusercontent.com/Dago001/nis-rcis/main/backend/resources/views/auth/staff-login.blade.php -o resources/views/auth/staff-login.blade.php
    curl -sL https://raw.githubusercontent.com/Dago001/nis-rcis/main/backend/app/Http/Controllers/Api/Staff/ApplicationController.php -o app/Http/Controllers/Api/Staff/ApplicationController.php
    curl -sL https://raw.githubusercontent.com/Dago001/nis-rcis/main/backend/app/Http/Controllers/Api/Applicant/ApplicationController.php -o app/Http/Controllers/Api/Applicant/ApplicationController.php

    # Auto-detect Turnstile keys if entered into any surrounding .env file
    sync_env_keys() {
        local src="$1"
        local dst="$2"
        if [ -f "$src" ] && [ -f "$dst" ]; then
            for key in SITE_KEY SECRET_KEY TURNSTILE_SITE_KEY TURNSTILE_SECRET_KEY TURNSTILE_ENABLED; do
                local val
                val=$(grep -E "^${key}=" "$src" 2>/dev/null | tail -n 1)
                if [ -n "$val" ]; then
                    if grep -q "^${key}=" "$dst" 2>/dev/null; then
                        sed -i "s|^${key}=.*|$val|" "$dst"
                    else
                        echo "$val" >> "$dst"
                    fi
                fi
            done
        fi
    }
    sync_env_keys "/home/nimcosel/domains/rcis.nimcoselection.com/backend/.env" "$BACKEND_DIR/.env"
    sync_env_keys "/home/nimcosel/domains/rcis.nimcoselection.com/.env" "$BACKEND_DIR/.env"
    sync_env_keys "/home/nimcosel/domains/rcis.nimcoselection.com/public_html/.env" "$BACKEND_DIR/.env"
    sync_env_keys "/home/nimcosel/domains/rcis.nimcoselection.com/public_html/frontend/.env" "$BACKEND_DIR/.env"
    sync_env_keys "/home/nimcosel/domains/rcis.nimcoselection.com/public_html/frontend/.env.production" "$BACKEND_DIR/.env"

    if grep -E -q "^(SITE_KEY|TURNSTILE_SITE_KEY)=" "$BACKEND_DIR/.env" 2>/dev/null; then
        echo "  -> Cloudflare Turnstile keys detected in backend/.env: ACTIVE"
    else
        echo "  -> Notice: SITE_KEY has not been added to $BACKEND_DIR/.env yet."
    fi

    echo "  -> Clearing Laravel cache..."
    php artisan optimize:clear 2>/dev/null || true
    echo "  -> Backend updated successfully."
else
    echo "  [SKIP] $BACKEND_DIR not found."
fi

# Step 3: Update Inertia Public Build Assets
echo "[3/4] Updating Public Build Assets..."
if [ -d "$BUILD_DIR" ]; then
    cd "$BUILD_DIR"
    echo "  -> Downloading complete build package..."
    curl -sL https://raw.githubusercontent.com/Dago001/nis-rcis/frontend-dist/build_update.tar.gz -o build_update.tar.gz
    tar -xzf build_update.tar.gz
    rm -f build_update.tar.gz
    echo "  -> Public build assets updated."
fi
if [ -d "$BACKEND_DIR/public/build" ]; then
    cd "$BACKEND_DIR/public/build"
    curl -sL https://raw.githubusercontent.com/Dago001/nis-rcis/frontend-dist/build_update.tar.gz -o build_update.tar.gz
    tar -xzf build_update.tar.gz
    rm -f build_update.tar.gz
fi

# Step 4: Restart Workers
echo "[4/4] Reloading Web Server Workers..."
if [ -d "$FRONTEND_DIR" ]; then
    touch "$FRONTEND_DIR/tmp/restart.txt"
fi
pkill -u nimcosel -9 node 2>/dev/null || true

echo "=========================================================="
echo " SUCCESS: Update deployed! Please refresh your browser"
echo " (Press Ctrl + F5 or Cmd + Shift + R to clear cache)"
echo "=========================================================="
