<?php
$dist = 'c:/nis-rcis-new/dist/rcis';
if (!is_dir($dist)) {
    mkdir($dist, 0777, true);
}

// 1. Package backend into backend.zip
$backendZip = $dist . '/backend.zip';
if (file_exists($backendZip)) {
    unlink($backendZip);
}

$staging = $dist . '/backend_staging';
if (is_dir($staging)) {
    exec("rmdir /s /q \"$staging\"");
}
mkdir($staging, 0777, true);

// Copy backend files
exec("robocopy c:\\nis-rcis-new\\backend \"$staging\" /E /XD .git tests node_modules .phpunit.cache");

// Put production .env into backend
copy($dist . '/backend.env', $staging . '/.env');

// Zip using PowerShell or tar
exec("powershell -Command \"Compress-Archive -Path '$staging\\*' -DestinationPath '$backendZip' -Force\"");
exec("rmdir /s /q \"$staging\"");

echo "Backend Zip: " . (file_exists($backendZip) ? filesize($backendZip) . " bytes\n" : "FAILED\n");

// 2. Package frontend into frontend.zip
$frontendZip = $dist . '/frontend.zip';
if (file_exists($frontendZip)) {
    unlink($frontendZip);
}

$feStaging = $dist . '/fe_staging';
if (is_dir($feStaging)) {
    exec("rmdir /s /q \"$feStaging\"");
}
mkdir($feStaging, 0777, true);

exec("robocopy c:\\nis-rcis-new\\frontend\\.next\\standalone \"$feStaging\" /E");
exec("robocopy c:\\nis-rcis-new\\frontend\\public \"$feStaging\\public\" /E");
exec("robocopy c:\\nis-rcis-new\\frontend\\.next\\static \"$feStaging\\.next\\static\" /E");

exec("powershell -Command \"Compress-Archive -Path '$feStaging\\*' -DestinationPath '$frontendZip' -Force\"");
exec("rmdir /s /q \"$feStaging\"");

echo "Frontend Zip: " . (file_exists($frontendZip) ? filesize($frontendZip) . " bytes\n" : "FAILED\n");
