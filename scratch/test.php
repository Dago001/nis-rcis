<?php
$file = 'C:/nis-rcis-new/scratch/test_php_patch.js';
$c = file_get_contents($file);
$target1 = '(()=>{try{let e=sessionStorage.getItem("nis_staff_dashboard");if(!e)return null;let t=JSON.parse(e);if(t&&t.analytics?.totals&&t.queue)return t;return sessionStorage.removeItem("nis_staff_dashboard"),null}catch{return null}})';
$target2 = 'try{sessionStorage.setItem("nis_staff_dashboard",JSON.stringify(e))}catch{}';

if (str_contains($c, 'nis_staff_dashboard')) {
    $c = str_replace($target1, '(null)', $c);
    $c = str_replace($target2, '/*no-cache*/', $c);
    file_put_contents($file, $c);
    echo "Dashboard chunk patched successfully!\n";
} else {
    echo "Target not found.\n";
}
