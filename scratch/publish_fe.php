<?php
$tar = "c:/nis-rcis-new/dist/rcis/next_update.tar.gz";
$blob = trim(shell_exec("git hash-object -w \"$tar\""));
echo "BLOB: $blob\n";

$descriptorspec = [0 => ["pipe", "r"], 1 => ["pipe", "w"], 2 => ["pipe", "w"]];
$process = proc_open("git mktree", $descriptorspec, $pipes);
fwrite($pipes[0], "100644 blob $blob\tnext_update.tar.gz\n");
fclose($pipes[0]);
$tree = trim(stream_get_contents($pipes[1]));
fclose($pipes[1]);
fclose($pipes[2]);
proc_close($process);
echo "TREE: $tree\n";

$process = proc_open("git commit-tree $tree -m \"Frontend 8 Roles and Command Update\"", $descriptorspec, $pipes);
$commit = trim(stream_get_contents($pipes[1]));
fclose($pipes[1]);
fclose($pipes[2]);
proc_close($process);
echo "COMMIT: $commit\n";

exec("git update-ref refs/heads/frontend-dist $commit");
$out = shell_exec("git push origin frontend-dist -f 2>&1");
echo "PUSH RESULT: $out\n";
