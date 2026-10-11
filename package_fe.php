<?php
$dist = "c:/nis-rcis-new/dist/rcis";
$stg = $dist . "/fe_staging";
if (is_dir($stg)) {
    exec("rmdir /s /q \"$stg\"");
}
mkdir($stg, 0777, true);
exec("robocopy c:\\nis-rcis-new\\frontend\\.next\\standalone \"$stg\" /E");
exec("robocopy c:\\nis-rcis-new\\frontend\\public \"$stg\\public\" /E");
exec("robocopy c:\\nis-rcis-new\\frontend\\.next\\static \"$stg\\.next\\static\" /E");

// Create .env.production and .env inside staging
$envContent = <<<ENV
NODE_ENV=production
HOSTNAME=0.0.0.0
APP_URL=https://rcis.nimcoselection.com
API_URL=https://rcis.nimcoselection.com/api
API_PUBLIC_URL=https://rcis.nimcoselection.com/api
SESSION_SECRET=y5bwqkiAno3ZfYXyxWkzvnMBthGSJiCbdePLomuX7q4QieM7dh+7SGWoMRx0PkSZ
OAUTH_STAFF_CLIENT_ID=01a0fff6-0f3c-70b5-a54f-2d6c2caa6393
OAUTH_STAFF_CLIENT_SECRET=O7dWCXnM0pj7RQhCkhrJBGHzlKamhjXHH1c47IGaDXwKipys
OAUTH_APPLICANT_CLIENT_ID=01a0fff6-107a-71d4-a724-c209130a6b6c
OAUTH_APPLICANT_CLIENT_SECRET=mOhcY9Rvbg9vKm0XhAdoOOiDFf6ik6vAmLPMagAbnDE67VUT
UV_THREADPOOL_SIZE=2
ENV;
// server.js from standalone handles Passenger environment directly

// Tar it without .env files (server keeps its own .env.production securely)
chdir($stg);
exec("tar -czf \"$dist/next_update.tar.gz\" * .next");
$tarSize = filesize("$dist/next_update.tar.gz");
echo "DONE PACKAGING: $tarSize bytes\n";

// Automatically publish to frontend-dist branch for direct curl deployment
$tar = "$dist/next_update.tar.gz";
$blob = trim(shell_exec("git hash-object -w \"$tar\""));
$descriptorspec = [0 => ["pipe", "r"], 1 => ["pipe", "w"], 2 => ["pipe", "w"]];
$process = proc_open("git mktree", $descriptorspec, $pipes);
if (is_resource($process)) {
    fwrite($pipes[0], "100644 blob $blob\tnext_update.tar.gz\n");
    fclose($pipes[0]);
    $tree = trim(stream_get_contents($pipes[1]));
    fclose($pipes[1]);
    fclose($pipes[2]);
    proc_close($process);
}
$process = proc_open("git commit-tree $tree", $descriptorspec, $pipes);
if (is_resource($process)) {
    fwrite($pipes[0], "Frontend Release Build\n");
    fclose($pipes[0]);
    $commit = trim(stream_get_contents($pipes[1]));
    fclose($pipes[1]);
    fclose($pipes[2]);
    proc_close($process);
}
exec("git update-ref refs/heads/frontend-dist $commit");
shell_exec("git push origin frontend-dist -f 2>&1");
echo "DONE PUBLISHING TO frontend-dist\n";
