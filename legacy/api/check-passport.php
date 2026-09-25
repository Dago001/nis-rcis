<?php
/**
 * NIS Residence Card Issuance System (NIS-RCIS)
 * API: Check Passport Number Duplicate & Format
 */

require_once __DIR__ . '/../includes/auth.php';
require_once __DIR__ . '/../config/database.php';

header('Content-Type: application/json; charset=utf-8');

if (!isLoggedIn()) {
    http_response_code(401);
    echo json_encode(['valid' => false, 'message' => 'Unauthorized']);
    exit;
}

$passport = strtoupper(trim($_GET['passport'] ?? ''));
$excludeId = isset($_GET['exclude_id']) ? (int)$_GET['exclude_id'] : 0;

if (empty($passport)) {
    echo json_encode(['valid' => false, 'message' => 'Passport number is required.']);
    exit;
}

if (!preg_match('/^[A-Z0-9]{6,15}$/', $passport)) {
    echo json_encode([
        'valid' => false, 
        'is_duplicate' => false,
        'message' => 'Passport number must be 6 to 15 alphanumeric characters (no spaces or special symbols).'
    ]);
    exit;
}

$db = Database::getConnection();
if ($excludeId > 0) {
    $stmt = $db->prepare("SELECT id, card_number, surname, forenames FROM residence_cards WHERE UPPER(passport_number) = :p AND id != :ex LIMIT 1");
    $stmt->execute([':p' => $passport, ':ex' => $excludeId]);
} else {
    $stmt = $db->prepare("SELECT id, card_number, surname, forenames FROM residence_cards WHERE UPPER(passport_number) = :p LIMIT 1");
    $stmt->execute([':p' => $passport]);
}

$existing = $stmt->fetch(PDO::FETCH_ASSOC);

if ($existing) {
    echo json_encode([
        'valid' => false,
        'is_duplicate' => true,
        'card_number' => $existing['card_number'],
        'holder' => $existing['surname'] . ', ' . $existing['forenames'],
        'message' => "Duplicate detected! Passport {$passport} is already registered under Card No. {$existing['card_number']} ({$existing['surname']}, {$existing['forenames']})."
    ]);
} else {
    echo json_encode([
        'valid' => true,
        'is_duplicate' => false,
        'message' => 'Passport number is available.'
    ]);
}
