<?php
$data = file_get_contents('scratch/be_roles_payload.json');
echo "SIZE: " . strlen($data) . "\n";
$gz = gzencode($data, 9);
file_put_contents('scratch/be_roles_payload.gz.b64', base64_encode($gz));
echo "B64 GZ SIZE: " . filesize('scratch/be_roles_payload.gz.b64') . "\n";
