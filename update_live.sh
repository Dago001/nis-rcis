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

# Step 2: Update Backend Controllers
echo "[2/4] Updating Backend Controllers & API..."
if [ -d "$BACKEND_DIR" ]; then
    cd "$BACKEND_DIR"
    mkdir -p app/Http/Controllers/Api/Staff app/Http/Controllers/Api/Applicant
    curl -sL https://raw.githubusercontent.com/Dago001/nis-rcis/main/backend/app/Http/Controllers/Api/Staff/ApplicationController.php -o app/Http/Controllers/Api/Staff/ApplicationController.php
    curl -sL https://raw.githubusercontent.com/Dago001/nis-rcis/main/backend/app/Http/Controllers/Api/Applicant/ApplicationController.php -o app/Http/Controllers/Api/Applicant/ApplicationController.php
    echo "  -> Clearing Laravel cache..."
    php artisan optimize:clear 2>/dev/null || true
    echo "  -> Backend updated successfully."
else
    echo "  [SKIP] $BACKEND_DIR not found."
fi

# Step 3: Update Inertia Public Build Assets
echo "[3/4] Updating Public Build Assets..."
if [ -d "$BUILD_DIR" ]; then
    mkdir -p "$BUILD_DIR/assets"
    curl -sL https://raw.githubusercontent.com/Dago001/nis-rcis/main/backend/public/build/manifest.json -o "$BUILD_DIR/manifest.json"
    curl -sL https://raw.githubusercontent.com/Dago001/nis-rcis/main/backend/public/build/assets/Slip-2pgJmLOP.js -o "$BUILD_DIR/assets/Slip-2pgJmLOP.js"
    curl -sL https://raw.githubusercontent.com/Dago001/nis-rcis/main/backend/public/build/assets/QrCode-BuufxRkh.js -o "$BUILD_DIR/assets/QrCode-BuufxRkh.js"
    echo "  -> Public build assets updated."
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
