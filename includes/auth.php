<?php
/**
 * NIS Residence Card Issuance System (NIS-RCIS)
 * Authentication Middleware & Role-Based Access Control (RBAC)
 */

require_once __DIR__ . '/security.php';
require_once dirname(__DIR__) . '/config/constants.php';

// Authentication Guard
if (!isset($_SESSION['user_id']) || !isset($_SESSION['loggedin']) || $_SESSION['loggedin'] !== true) {
    $redirectUrl = urlencode($_SERVER['REQUEST_URI'] ?? 'dashboard');
    header("Location: login?redirect={$redirectUrl}");
    exit();
}

// Get logged-in user details
function currentUser(): array {
    $userId = (int)($_SESSION['user_id'] ?? 0);
    $photo = $_SESSION['photo_path'] ?? null;

    if ($userId > 0) {
        try {
            require_once dirname(__DIR__) . '/config/database.php';
            $db = Database::getConnection();
            $stmt = $db->prepare("SELECT fullname, photo_path, role, command, email FROM users WHERE id = :id LIMIT 1");
            $stmt->execute([':id' => $userId]);
            $userDb = $stmt->fetch(PDO::FETCH_ASSOC);
            if ($userDb) {
                $_SESSION['fullname'] = stripRank($userDb['fullname']);
                $_SESSION['role'] = $userDb['role'];
                $_SESSION['command'] = $userDb['command'];
                $_SESSION['email'] = $userDb['email'];
                $photo = $userDb['photo_path'] ?: null;
                $_SESSION['photo_path'] = $photo;
            }
        } catch (Throwable $e) {
            $photo = $_SESSION['photo_path'] ?? null;
        }
    }

    $fullname = stripRank($_SESSION['fullname'] ?? 'Immigration Officer');
    $_SESSION['fullname'] = $fullname;

    return [
        'id' => $userId,
        'username' => $_SESSION['username'] ?? '',
        'fullname' => $fullname,
        'service_number' => $_SESSION['service_number'] ?? '',
        'role' => $_SESSION['role'] ?? ROLE_ISSUING_OFFICER,
        'command' => $_SESSION['command'] ?? 'National Processing Center',
        'email' => $_SESSION['email'] ?? '',
        'photo_path' => $photo
    ];
}

// RBAC Role Verification Guard
function requireRole($allowedRoles): void {
    if (!is_array($allowedRoles)) {
        $allowedRoles = [$allowedRoles];
    }

    $userRole = $_SESSION['role'] ?? '';

    // SuperAdmin has access to everything
    if ($userRole === ROLE_SUPER_ADMIN) {
        return;
    }

    if (!in_array($userRole, $allowedRoles, true)) {
        setFlash('danger', 'Access Denied: You do not possess the official clearance required for this operation.');
        header("Location: dashboard");
        exit();
    }
}

// Role Check Helper (accepts single role or array of allowed roles)
function hasRole(string|array $role): bool {
    $userRole = $_SESSION['role'] ?? '';
    if ($userRole === ROLE_SUPER_ADMIN) {
        return true;
    }
    if (is_array($role)) {
        return in_array($userRole, $role, true);
    }
    return $userRole === $role;
}
