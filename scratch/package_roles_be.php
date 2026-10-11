<?php

$files = [
    'backend/app/Enums/StaffRole.php',
    'backend/app/Models/User.php',
    'backend/app/Http/Controllers/Api/Staff/UserController.php',
    'backend/app/Http/Controllers/Api/Staff/DashboardController.php',
    'backend/app/Http/Controllers/Api/Staff/ApplicationController.php',
    'backend/app/Services/CentreQueue.php',
    'backend/routes/api.php',
];

$bundle = [];
foreach ($files as $file) {
    if (!file_exists($file)) {
        die("Missing file: $file\n");
    }
    $bundle[$file] = base64_encode(file_get_contents($file));
}

$payload = json_encode($bundle);
file_put_contents('scratch/be_roles_payload.json', $payload);
echo "Bundled " . count($bundle) . " backend files (" . strlen($payload) . " bytes)\n";
