<?php

/**
 * Creates an atomic tar archive of frontend/.next ready to sync safely
 * without affecting running requests until unpacked.
 */

$tarCmd = 'tar -czf scratch/frontend_next_build.tar.gz -C frontend .next';
system($tarCmd, $ret);
if ($ret === 0) {
    echo "Successfully packaged frontend/.next (" . filesize('scratch/frontend_next_build.tar.gz') . " bytes)\n";
} else {
    echo "Tar failed with code $ret\n";
}
