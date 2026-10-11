<?php

/**
 * Migration & DB patch for NIS RCIS Roles, State & LGA Command structure.
 */

$pdo = \Illuminate\Support\Facades\DB::connection()->getPdo();

echo "1. Checking and updating users table schema for state and lga columns...\n";
$columns = collect(\Illuminate\Support\Facades\DB::select("SHOW COLUMNS FROM users"))->pluck('Field')->all();

if (!in_array('state', $columns)) {
    echo "  Adding 'state' column to users...\n";
    $pdo->exec("ALTER TABLE `users` ADD COLUMN `state` VARCHAR(100) NULL AFTER `command`");
} else {
    echo "  'state' column already exists in users.\n";
}

if (!in_array('lga', $columns)) {
    echo "  Adding 'lga' column to users...\n";
    $pdo->exec("ALTER TABLE `users` ADD COLUMN `lga` VARCHAR(100) NULL AFTER `state`");
} else {
    echo "  'lga' column already exists in users.\n";
}

echo "Schema updated successfully!\n";
