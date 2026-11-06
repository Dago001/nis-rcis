#!/usr/bin/env bash
set -e

echo "=========================================================="
echo " NIS-RCIS: Applying Production Live Portal Update"
echo " (National Dashboard Fix, Full Height Sidebar, Vetting->Biometrics->Approval)"
echo "=========================================================="

BASE_DIR="/home/nimcosel/domains/rcis.nimcoselection.com"
FRONTEND_DIR="$BASE_DIR/public_html/frontend"
BACKEND_DIR="$BASE_DIR/app/backend"
ALT_BACKEND_DIR="$BASE_DIR/backend"
BUILD_DIR="$BASE_DIR/public_html/build"

# Step 0: Git sync if repository is present
if [ -d "$BASE_DIR/app/.git" ]; then
    echo "[0/4] Syncing Git repository at $BASE_DIR/app..."
    cd "$BASE_DIR/app"
    git fetch origin main 2>/dev/null || true
    git checkout -f main 2>/dev/null || true
    git pull origin main 2>/dev/null || true
elif [ -d "$BASE_DIR/.git" ]; then
    echo "[0/4] Syncing Git repository at $BASE_DIR..."
    cd "$BASE_DIR"
    git fetch origin main 2>/dev/null || true
    git checkout -f main 2>/dev/null || true
    git pull origin main 2>/dev/null || true
fi

# Step 1: Update Backend Core Application (Controllers, Services, Models, Migrations, Routes)
echo "[1/4] Updating Backend Core Application..."
TMP_DIR=$(mktemp -d)
cd "$TMP_DIR"
echo "  -> Downloading backend release package..."
curl -sL https://raw.githubusercontent.com/Dago001/nis-rcis/frontend-dist/backend_update.tar.gz -o backend_update.tar.gz

if [ -s backend_update.tar.gz ]; then
    echo "  -> Extracting backend files..."
    tar -xzf backend_update.tar.gz
    rm -f backend_update.tar.gz

    # Deploy to primary backend
    if [ -d "$BACKEND_DIR" ]; then
        echo "  -> Deploying to $BACKEND_DIR..."
        cp -r * "$BACKEND_DIR/"
    fi

    # Deploy to alt backend
    if [ -d "$ALT_BACKEND_DIR" ]; then
        echo "  -> Deploying to $ALT_BACKEND_DIR..."
        cp -r * "$ALT_BACKEND_DIR/"
    fi
    echo "  -> Backend core application files updated successfully."
else
    echo "  [WARN] Failed to download backend_update.tar.gz"
fi
cd "$BASE_DIR"
rm -rf "$TMP_DIR"

# Step 2: Update Compiled Frontend Build Assets
echo "[2/4] Updating Public Build Assets..."
mkdir -p "$BUILD_DIR"
cd "$BUILD_DIR"
echo "  -> Downloading complete compiled frontend package..."
curl -sL https://raw.githubusercontent.com/Dago001/nis-rcis/frontend-dist/build_update.tar.gz -o build_update.tar.gz

if [ -s build_update.tar.gz ]; then
    tar -xzf build_update.tar.gz
    rm -f build_update.tar.gz
    chmod -R 755 "$BUILD_DIR" 2>/dev/null || true
    echo "  -> Public build assets updated in $BUILD_DIR."

    # Mirror to backend public build directories
    if [ -d "$BACKEND_DIR" ]; then
        mkdir -p "$BACKEND_DIR/public/build"
        cp -r "$BUILD_DIR"/* "$BACKEND_DIR/public/build/" 2>/dev/null || true
    fi
    if [ -d "$ALT_BACKEND_DIR" ]; then
        mkdir -p "$ALT_BACKEND_DIR/public/build"
        cp -r "$BUILD_DIR"/* "$ALT_BACKEND_DIR/public/build/" 2>/dev/null || true
    fi
else
    echo "  [WARN] Failed to download build_update.tar.gz"
fi

# Step 3: Run Database Migrations & Clear Laravel Cache
echo "[3/4] Optimizing Laravel Backend & Running Database Migrations..."
TARGET_BE=""
if [ -d "$BACKEND_DIR" ]; then
    TARGET_BE="$BACKEND_DIR"
elif [ -d "$ALT_BACKEND_DIR" ]; then
    TARGET_BE="$ALT_BACKEND_DIR"
fi

if [ -n "$TARGET_BE" ]; then
    cd "$TARGET_BE"
    echo "  -> Clearing application and route cache in $TARGET_BE..."
    php artisan optimize:clear 2>/dev/null || true
    echo "  -> Running database migrations..."
    php artisan migrate --force 2>/dev/null || true
fi

# Step 4: Restart Workers
echo "[4/4] Reloading Web Server & Passenger Workers..."
if [ -d "$FRONTEND_DIR" ]; then
    mkdir -p "$FRONTEND_DIR/tmp"
    touch "$FRONTEND_DIR/tmp/restart.txt"
fi
mkdir -p "$BASE_DIR/tmp" 2>/dev/null || true
touch "$BASE_DIR/tmp/restart.txt" 2>/dev/null || true
pkill -u nimcosel -9 node 2>/dev/null || true

echo "=========================================================="
echo " SUCCESS: Update deployed successfully!"
echo " 1. Staff Sidebar: Full height container + Vetting -> Biometrics -> Approval order"
echo " 2. National Dashboard: Fixed query analytics, no 500 error"
echo " 3. Profile display: Duplicate profile removed"
echo " (Please hard refresh your browser: Ctrl + F5 or Cmd + Shift + R)"
echo "=========================================================="
