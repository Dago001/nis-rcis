<?php
/**
 * NIS Residence Card Issuance System (NIS-RCIS)
 * Secure Session Logout Handler
 */

require_once __DIR__ . '/includes/security.php';

if (isset($_SESSION['user_id'])) {
    logAudit('LOGOUT', null, "User " . ($_SESSION['username'] ?? '') . " logged out");
}

// Unset all session variables
$_SESSION = [];

// Destroy session cookie if present
if (ini_get("session.use_cookies")) {
    $params = session_get_cookie_params();
    setcookie(session_name(), '', time() - 42000,
        $params["path"], $params["domain"],
        $params["secure"], $params["httponly"]
    );
}

// Destroy the session
session_destroy();

header("Location: index?msg=logged_out");
exit();
