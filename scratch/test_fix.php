<?php
require 'backend/vendor/autoload.php';
$app = require 'backend/bootstrap/app.php';
$kernel = $app->make(Illuminate\Contracts\Console\Kernel::class);
$kernel->bootstrap();

echo "1. Scanning database for broken encrypted payloads..." . PHP_EOL;
$fixed = 0;
foreach (Illuminate\Support\Facades\DB::table('applications')->whereNotNull('fingerprints')->get(['id', 'application_number', 'fingerprints']) as $row) {
    try {
        Illuminate\Support\Facades\Crypt::decrypt($row->fingerprints);
    } catch (\Throwable $e) {
        echo "  Repairing application ID " . $row->id . " (" . $row->application_number . ")..." . PHP_EOL;
        Illuminate\Support\Facades\DB::table('applications')->where('id', $row->id)->update(['fingerprints' => null, 'fingerprint_template' => null]);
        $fixed++;
    }
}
echo "  Database repair complete. Repaired {$fixed} corrupted records." . PHP_EOL;
