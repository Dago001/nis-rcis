<?php
$src = 'c:/nis-rcis-new/dist/cpanel/live_migration.sql';
$dstDir = 'c:/nis-rcis-new/dist/rcis';
if (!is_dir($dstDir)) {
    mkdir($dstDir, 0777, true);
}
$dst = $dstDir . '/mysql_migration.sql';

$in = fopen($src, 'r');
$out = fopen($dst, 'w');

fwrite($out, "/*!40101 SET @OLD_CHARACTER_SET_CLIENT=@@CHARACTER_SET_CLIENT */;\n");
fwrite($out, "/*!40101 SET NAMES utf8mb4 */;\n");
fwrite($out, "/*!40014 SET @OLD_FOREIGN_KEY_CHECKS=@@FOREIGN_KEY_CHECKS, FOREIGN_KEY_CHECKS=0 */;\n");
fwrite($out, "/*!40101 SET @OLD_SQL_MODE=@@SQL_MODE, SQL_MODE='NO_AUTO_VALUE_ON_ZERO' */;\n\n");

while (($line = fgets($in)) !== false) {
    $trimmed = trim($line);
    
    // Skip Postgres-only session headers
    if (preg_match('/^SET\s+(statement_timeout|lock_timeout|client_encoding|standard_conforming_strings)/i', $trimmed)) {
        continue;
    }
    // Skip BEGIN/COMMIT transaction headers from Postgres
    if (preg_match('/^(BEGIN|COMMIT)\s*;/i', $trimmed)) {
        continue;
    }
    // Skip Postgres triggers & sequence setvals
    if (preg_match('/^(ALTER\s+TABLE\s+.*(DISABLE|ENABLE)\s+TRIGGER|SELECT\s+setval)/i', $trimmed)) {
        continue;
    }

    if (stripos($line, 'INSERT INTO ') !== false) {
        // Replace only the column and table names before 'VALUES' with backticks
        if (preg_match('/^(INSERT INTO\s+)"([^"]+)"\s*\((.*)\)\s*(VALUES\s*\(.*)$/is', $line, $m)) {
            $table = $m[2];
            // Replace double quotes in column list only
            $columns = str_replace('"', '`', $m[3]);
            $valuesPart = $m[4];
            
            // Remove 'ON CONFLICT DO NOTHING' at end
            $valuesPart = preg_replace('/\s+ON\s+CONFLICT\s+DO\s+NOTHING\s*;?\s*$/i', ';', $valuesPart);
            
            // Replace boolean literals TRUE/FALSE that are not inside quotes
            // e.g. , TRUE, -> , 1,
            $valuesPart = preg_replace('/,\s*TRUE\s*,/i', ', 1,', $valuesPart);
            $valuesPart = preg_replace('/,\s*FALSE\s*,/i', ', 0,', $valuesPart);
            $valuesPart = preg_replace('/,\s*TRUE\s*\)/i', ', 1)', $valuesPart);
            $valuesPart = preg_replace('/,\s*FALSE\s*\)/i', ', 0)', $valuesPart);
            $valuesPart = preg_replace('/\(\s*TRUE\s*,/i', '(1,', $valuesPart);
            $valuesPart = preg_replace('/\(\s*FALSE\s*,/i', '(0,', $valuesPart);
            
            $line = "INSERT IGNORE INTO `{$table}` ({$columns}) " . $valuesPart . "\n";
        }
    }

    fwrite($out, $line);
}

fwrite($out, "\n/*!40101 SET SQL_MODE=IFNULL(@OLD_SQL_MODE, '') */;\n");
fwrite($out, "/*!40014 SET FOREIGN_KEY_CHECKS=IFNULL(@OLD_FOREIGN_KEY_CHECKS, 1) */;\n");
fwrite($out, "/*!40101 SET CHARACTER_SET_CLIENT=@OLD_CHARACTER_SET_CLIENT */;\n");

fclose($in);
fclose($out);

echo "SUCCESS: Converted clean MySQL dump (" . round(filesize($dst)/1024, 2) . " KB)\n";
