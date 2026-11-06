<?php
// scripts/package_release.php
// Packages backend_update.tar.gz and build_update.tar.gz and pushes them to origin/frontend-dist branch.

$rootDir = realpath(__DIR__ . '/..');
$distDir = $rootDir . '/dist_package';
if (!is_dir($distDir)) {
    mkdir($distDir, 0777, true);
}

echo "=== 1. Packaging Frontend Build Assets ===\n";
$buildDir = $rootDir . '/backend/public/build';
if (!file_exists($buildDir . '/manifest.json')) {
    echo "ERROR: manifest.json not found in $buildDir\n";
    exit(1);
}
$buildTar = $distDir . '/build_update.tar.gz';
if (file_exists($buildTar)) unlink($buildTar);

exec("tar -czf " . escapeshellarg($buildTar) . " -C " . escapeshellarg($buildDir) . " .", $out, $ret);
if ($ret !== 0 || !file_exists($buildTar)) {
    echo "ERROR: Failed to create build_update.tar.gz\n";
    exit(1);
}
echo "build_update.tar.gz created: " . filesize($buildTar) . " bytes\n";

echo "=== 2. Packaging Backend Core Application ===\n";
$backendTar = $distDir . '/backend_update.tar.gz';
if (file_exists($backendTar)) unlink($backendTar);

$backendDir = $rootDir . '/backend';
// We package app, routes, config, database/migrations, resources/views
exec("tar -czf " . escapeshellarg($backendTar) . " -C " . escapeshellarg($backendDir) . " app routes config database/migrations resources/views", $out2, $ret2);
if ($ret2 !== 0 || !file_exists($backendTar)) {
    echo "ERROR: Failed to create backend_update.tar.gz\n";
    exit(1);
}
echo "backend_update.tar.gz created: " . filesize($backendTar) . " bytes\n";

echo "=== 3. Uploading to origin/frontend-dist via Git Object Database ===\n";
chdir($rootDir);

$buildBlob = trim(shell_exec("git hash-object -w " . escapeshellarg($buildTar)));
$backendBlob = trim(shell_exec("git hash-object -w " . escapeshellarg($backendTar)));
echo "buildBlob: $buildBlob\n";
echo "backendBlob: $backendBlob\n";

$descriptorspec = [0 => ["pipe", "r"], 1 => ["pipe", "w"], 2 => ["pipe", "w"]];
$process = proc_open("git mktree", $descriptorspec, $pipes);
if (!is_resource($process)) {
    echo "ERROR: proc_open git mktree failed\n";
    exit(1);
}
fwrite($pipes[0], "100644 blob $buildBlob\tbuild_update.tar.gz\n");
fwrite($pipes[0], "100644 blob $backendBlob\tbackend_update.tar.gz\n");
fclose($pipes[0]);
$tree = trim(stream_get_contents($pipes[1]));
fclose($pipes[1]);
fclose($pipes[2]);
proc_close($process);
echo "Tree hash: $tree\n";

$process = proc_open("git commit-tree $tree", $descriptorspec, $pipes);
if (!is_resource($process)) {
    echo "ERROR: proc_open git commit-tree failed\n";
    exit(1);
}
fwrite($pipes[0], "Deploy release: National Dashboard, Full Height Sidebar, Vetting->Biometrics->Approval\n");
fclose($pipes[0]);
$commit = trim(stream_get_contents($pipes[1]));
fclose($pipes[1]);
fclose($pipes[2]);
proc_close($process);
echo "Commit hash: $commit\n";

exec("git update-ref refs/heads/frontend-dist $commit");
$pushOut = shell_exec("git push origin frontend-dist -f 2>&1");
echo "Push result: $pushOut\n";

echo "=== Packaging & Distribution Complete! ===\n";
