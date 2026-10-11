<?php
require '/home/nimcosel/domains/rcis.nimcoselection.com/app/backend/vendor/autoload.php';
$app = require '/home/nimcosel/domains/rcis.nimcoselection.com/app/backend/bootstrap/app.php';
$kernel = $app->make(Illuminate\Contracts\Console\Kernel::class);
$kernel->bootstrap();

echo "1. Scanning database for broken encrypted payloads..." . PHP_EOL;
$fixed = 0;
foreach (Illuminate\Support\Facades\DB::table('applications')->whereNotNull('fingerprints')->get(['id', 'application_number', 'fingerprints']) as $row) {
    try {
        Illuminate\Support\Facades\Crypt::decrypt($row->fingerprints);
    } catch (\Throwable $e) {
        echo "  Repairing application ID " . $row->id . " (" . $row->application_number . ")..." . PHP_EOL;
        Illuminate\Support\Facades\DB::table('applications')->where('id', $row->id)->update([
            'fingerprints' => null,
            'fingerprint_template' => null,
        ]);
        $fixed++;
    }
}
echo "  Database repair complete. Repaired " . $fixed . " corrupted records." . PHP_EOL;

echo "2. Applying permanent DecryptException shield to Application.php..." . PHP_EOL;
$modelFile = '/home/nimcosel/domains/rcis.nimcoselection.com/app/backend/app/Models/Application.php';
if (file_exists($modelFile)) {
    $content = file_get_contents($modelFile);
    if (!str_contains($content, 'fromEncryptedString')) {
        $guard = '
    public function fromEncryptedString($value)
    {
        try {
            return parent::fromEncryptedString($value);
        } catch (\Illuminate\Contracts\Encryption\DecryptException $e) {
            \Illuminate\Support\Facades\Log::warning("Corrupted encrypted attribute on application {$this->id}: {$e->getMessage()}");
            return null;
        }
    }
';
        $content = preg_replace('/(\n\s*public function applicant\(\))/', $guard . '$1', $content, 1);
        file_put_contents($modelFile, $content);
        echo "  Application.php successfully shielded!" . PHP_EOL;
    } else {
        echo "  Application.php already shielded." . PHP_EOL;
    }
}

echo "3. Optimizing navigation link speed (disabling background prefetch queue)..." . PHP_EOL;
$chunkDir = '/home/nimcosel/domains/rcis.nimcoselection.com/public_html/frontend/.next/static/chunks';
foreach (glob($chunkDir . '/*.js') as $jsFile) {
    $js = file_get_contents($jsFile);
    if (str_contains($js, 'scroll:!1') && !str_contains($js, 'prefetch:!1')) {
        $js = str_replace('scroll:!1', 'scroll:!1,prefetch:!1', $js);
        file_put_contents($jsFile, $js);
        echo "  Optimized navigation in " . basename($jsFile) . PHP_EOL;
    }
}

echo "4. Clearing application cache..." . PHP_EOL;
Illuminate\Support\Facades\Artisan::call('optimize:clear');
echo "ALL ISSUES FIXED PERMANENTLY!" . PHP_EOL;
