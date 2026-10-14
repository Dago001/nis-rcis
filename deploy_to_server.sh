#!/bin/bash
set -e

BASE_DIR="/home/nimcosel/domains/rcis.nimcoselection.com"

echo "=== Deploying ECOWAS RCIS Form & Photograph Fixes ==="

# 1. Update live backend
if [ -d "$BASE_DIR/backend" ]; then
    echo "Updating $BASE_DIR/backend..."
    cp -r backend/app "$BASE_DIR/backend/"
    cp -r backend/routes "$BASE_DIR/backend/"
    cp -r backend/resources "$BASE_DIR/backend/"
    cp -r backend/public/build "$BASE_DIR/backend/public/"
fi

# 2. Update app clone if present
if [ -d "$BASE_DIR/app/backend" ]; then
    echo "Updating $BASE_DIR/app/backend..."
    cp -r backend/app "$BASE_DIR/app/backend/"
    cp -r backend/routes "$BASE_DIR/app/backend/"
    cp -r backend/resources "$BASE_DIR/app/backend/"
    cp -r backend/public/build "$BASE_DIR/app/backend/public/"
fi

# 3. Update public_html build assets
if [ -d "$BASE_DIR/public_html" ]; then
    echo "Updating $BASE_DIR/public_html/build..."
    mkdir -p "$BASE_DIR/public_html/build"
    cp -r backend/public/build/* "$BASE_DIR/public_html/build/"
fi

# 4. Clear Laravel optimization cache
echo "Clearing Laravel application cache..."
if [ -d "$BASE_DIR/backend" ]; then
    cd "$BASE_DIR/backend"
    php artisan optimize:clear || true
fi

# 5. Reload CloudLinux Passenger
echo "Reloading Passenger workers..."
if [ -d "$BASE_DIR/public_html/frontend" ]; then
    mkdir -p "$BASE_DIR/public_html/frontend/tmp"
    touch "$BASE_DIR/public_html/frontend/tmp/restart.txt"
fi

echo "=== Deployment Completed Successfully! ==="
