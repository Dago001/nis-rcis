<?php
$stg = "c:/nis-rcis-new/dist/rcis/fe_staging";
$dist = "c:/nis-rcis-new/dist/rcis";

echo "Updating fe_staging...\n";
exec("robocopy c:\\nis-rcis-new\\frontend\\.next\\standalone \"$stg\" /E");
exec("robocopy c:\\nis-rcis-new\\frontend\\.next\\static \"$stg\\.next\\static\" /E");

chdir($stg);
echo "Creating tar.gz...\n";
exec("tar -czf \"$dist/next_update.tar.gz\" * .next");
echo "TAR DONE: " . filesize("$dist/next_update.tar.gz") . " bytes\n";
