<?php
$dist = "c:/nis-rcis-new/dist/rcis";
$stg = $dist . "/be_staging";
if (is_dir($stg)) {
    exec("rmdir /s /q \"$stg\"");
}
mkdir($stg, 0777, true);

$files = [
    'config/nis.php',
    'app/Models/Application.php',
    'app/Support/ApplicationRules.php',
    'app/Enums/DocumentType.php',
    'app/Services/NumberGenerator.php',
    'app/Services/ApplicationWorkflow.php',
    'database/migrations/2026_10_04_200000_add_home_country_address_to_particulars.php',
];

foreach ($files as $file) {
    $src = "c:/nis-rcis-new/backend/" . $file;
    $dst = $stg . "/" . $file;
    $dir = dirname($dst);
    if (!is_dir($dir)) {
        mkdir($dir, 0777, true);
    }
    copy($src, $dst);
}

chdir($stg);
exec("tar -czf \"$dist/backend_update.tar.gz\" *");
echo "DONE BE: " . filesize("$dist/backend_update.tar.gz") . " bytes\n";
