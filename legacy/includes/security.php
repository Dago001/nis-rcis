<?php
/**
 * NIS Residence Card Issuance System (NIS-RCIS)
 * Security Guard, Session Protection, CSRF, XSS & Audit Engine
 */

// 1. Hardened Session Settings
if (session_status() === PHP_SESSION_NONE) {
    @ini_set('session.cookie_httponly', '1');
    @ini_set('session.use_only_cookies', '1');
    @ini_set('session.cookie_samesite', 'Strict');
    session_start();
}

// 2. Global Security Headers
if (!headers_sent()) {
    header("X-Frame-Options: SAMEORIGIN");
    header("X-Content-Type-Options: nosniff");
    header("X-XSS-Protection: 1; mode=block");
    header("Referrer-Policy: strict-origin-when-cross-origin");
    header("Content-Security-Policy: default-src 'self' 'unsafe-inline' data: blob: https://cdn.jsdelivr.net https://cdnjs.cloudflare.com https://js.paystack.co; script-src 'self' 'unsafe-inline' 'unsafe-eval' https://cdn.jsdelivr.net https://cdnjs.cloudflare.com https://js.paystack.co; frame-src 'self' https://checkout.paystack.com https://*.paystack.co https://*.paystack.com; connect-src 'self' https://api.paystack.co https://*.paystack.co; img-src 'self' data: blob: https:; font-src 'self' data: https://cdnjs.cloudflare.com https://fonts.gstatic.com;");
}

// 3. Session Hijacking Protection (IP & User-Agent fingerprinting)
if (isset($_SESSION['user_id'])) {
    $remoteIp = $_SERVER['REMOTE_ADDR'] ?? 'UNKNOWN';
    $userAgent = $_SERVER['HTTP_USER_AGENT'] ?? 'UNKNOWN';

    if (!isset($_SESSION['ip_address']) || !isset($_SESSION['user_agent'])) {
        $_SESSION['ip_address'] = $remoteIp;
        $_SESSION['user_agent'] = $userAgent;
    } elseif ($_SESSION['ip_address'] !== $remoteIp || $_SESSION['user_agent'] !== $userAgent) {
        // Potential session hijacking detected - wipe session
        session_unset();
        session_destroy();
        header("Location: index?error=session_hijack_detected");
        exit();
    }
}

// 4. CSRF Token Protection
if (!isset($_SESSION['csrf_token'])) {
    try {
        $_SESSION['csrf_token'] = bin2hex(random_bytes(32));
    } catch (Throwable $e) {
        $_SESSION['csrf_token'] = md5(uniqid((string)rand(), true));
    }
}

function csrf_token(): string {
    return $_SESSION['csrf_token'] ?? '';
}

function csrf_field(): string {
    $token = htmlspecialchars(csrf_token(), ENT_QUOTES, 'UTF-8');
    return '<input type="hidden" name="csrf_token" value="' . $token . '">';
}

function validate_csrf(): bool {
    if ($_SERVER['REQUEST_METHOD'] === 'POST') {
        $submittedToken = $_POST['csrf_token'] ?? '';
        if (empty($submittedToken) || !hash_equals($_SESSION['csrf_token'] ?? '', $submittedToken)) {
            return false;
        }
    }
    return true;
}

// 5. XSS Sanitization & Escaping Helper
function h(?string $str): string {
    return htmlspecialchars((string)($str ?? ''), ENT_QUOTES, 'UTF-8');
}

// 5b. Strip Military/Paramilitary Ranks & Parenthetical Badges from Names
function stripRank(?string $name): string {
    if (empty($name)) {
        return '';
    }

    // Retain institutional authority titles untouched
    $trimmedUpper = strtoupper(trim($name));
    if (in_array($trimmedUpper, ['COMPTROLLER GENERAL', 'COMPTROLLER GENERAL OF IMMIGRATION', 'COMPTROLLER-GENERAL', 'MINISTER OF INTERIOR'], true)) {
        return trim($name);
    }

    // Strip parentheticals e.g. (Head, Residence Section), (Issuance Desk), etc.
    $cleaned = preg_replace('/\s*\([^)]*\)/', '', $name);

    // Strip NIS / Paramilitary ranks and titles
    $rankPattern = '/^\s*(?:CGI|DCG|ACG|CIS|DCI|ACI|CSI|DSI|DSP|ASI|ASP|CII|DII|AII|SIA|AIC|II|SI|SP|IC|COMPTROLLER[- ]GENERAL|COMPTROLLER|SUPERINTENDENT|INSPECTOR|ASSISTANT COMPTROLLER|DEPUTY COMPTROLLER|CHIEF SUPERINTENDENT|DEPUTY SUPERINTENDENT|ASSISTANT SUPERINTENDENT|CHIEF INSPECTOR|DEPUTY INSPECTOR|ASSISTANT INSPECTOR)\b[\.\s-]*/i';
    $stripped = trim(preg_replace($rankPattern, '', $cleaned));

    return $stripped !== '' ? $stripped : trim($cleaned);
}

// 6. NIS Standard Date Formatting Helper (e.g. "20TH JANUARY 2026")
function formatNISDate(?string $dateStr): string {
    if (empty($dateStr) || $dateStr === '0000-00-00') {
        return 'N/A';
    }
    $timestamp = strtotime($dateStr);
    if (!$timestamp) {
        return strtoupper($dateStr);
    }
    $day = date('j', $timestamp);
    $suffix = strtoupper(date('S', $timestamp));
    $month = strtoupper(date('F', $timestamp));
    $year = date('Y', $timestamp);
    return $day . $suffix . ' ' . $month . ' ' . $year;
}

// 7. Flash Messages Helpers
function setFlash(string $type, string $message): void {
    $_SESSION['flash'] = [
        'type' => $type, // 'success', 'danger', 'warning', 'info'
        'message' => $message
    ];
}

function getFlash(): ?array {
    if (isset($_SESSION['flash'])) {
        $flash = $_SESSION['flash'];
        unset($_SESSION['flash']);
        return $flash;
    }
    return null;
}

// 8. Audit Trail Logger
function logAudit(string $action, ?int $cardId = null, ?string $details = null): void {
    try {
        require_once dirname(__DIR__) . '/config/database.php';
        $db = Database::getConnection();

        $userId = $_SESSION['user_id'] ?? null;
        $username = $_SESSION['username'] ?? 'GUEST_OR_SYSTEM';
        $remoteIp = $_SERVER['REMOTE_ADDR'] ?? '127.0.0.1';
        $userAgent = substr($_SERVER['HTTP_USER_AGENT'] ?? 'CLI', 0, 255);

        $stmt = $db->prepare("INSERT INTO audit_logs (user_id, username, action, card_id, details, ip_address, user_agent) 
                              VALUES (:user_id, :username, :action, :card_id, :details, :ip, :ua)");
        $stmt->execute([
            ':user_id' => $userId,
            ':username' => $username,
            ':action' => $action,
            ':card_id' => $cardId,
            ':details' => $details,
            ':ip' => $remoteIp,
            ':ua' => $userAgent
        ]);
    } catch (Throwable $e) {
        // Silently prevent logging failure from halting application flow
        error_log("Audit log failed: " . $e->getMessage());
    }
}

// 9. Pure PHP SVG Barcode Generator (Code 39)
function generateBarcodeSvg(?string $code, int $height = 45, int $width = 240): string {
    $code = strtoupper(trim((string)$code));
    $patterns = [
        '0' => '000110100', '1' => '100100001', '2' => '001100001', '3' => '101100000',
        '4' => '000110001', '5' => '100110000', '6' => '001110000', '7' => '000100101',
        '8' => '100100100', '9' => '001100100', 'A' => '100001001', 'B' => '001001001',
        'C' => '101001000', 'D' => '000011001', 'E' => '100011000', 'F' => '001011000',
        'G' => '000001101', 'H' => '100001100', 'I' => '001001100', 'J' => '000011100',
        'K' => '100000011', 'L' => '001000011', 'M' => '101000010', 'N' => '000010011',
        'O' => '100010010', 'P' => '001010010', 'Q' => '000000111', 'R' => '100000110',
        'S' => '001000110', 'T' => '000010110', 'U' => '110000001', 'V' => '011000001',
        'W' => '111000000', 'X' => '010010001', 'Y' => '110010000', 'Z' => '011010000',
        '-' => '010000101', '.' => '110000100', ' ' => '011000100', '*' => '010010100',
        '$' => '010101000', '/' => '010100010', '+' => '010001010', '%' => '000101010'
    ];

    $cleanCode = preg_replace('/[^A-Z0-9\-\.\ \$\/\+\%]/', '', $code);
    if (empty($cleanCode)) {
        $cleanCode = 'NIS-RCIS';
    }
    $fullCode = '*' . $cleanCode . '*';

    $modules = [];
    $len = strlen($fullCode);
    for ($i = 0; $i < $len; $i++) {
        $char = $fullCode[$i];
        if (!isset($patterns[$char])) continue;
        $pattern = $patterns[$char];
        for ($j = 0; $j < 9; $j++) {
            $isBar = ($j % 2 == 0);
            $isWide = ($pattern[$j] === '1');
            $modules[] = ['isBar' => $isBar, 'width' => $isWide ? 3 : 1];
        }
        $modules[] = ['isBar' => false, 'width' => 1];
    }

    $totalUnits = 0;
    foreach ($modules as $m) {
        $totalUnits += $m['width'];
    }

    $unitWidth = $width / max($totalUnits, 1);
    $svg = '<svg xmlns="http://www.w3.org/2000/svg" viewBox="0 0 ' . $width . ' ' . $height . '" width="' . $width . '" height="' . $height . '" preserveAspectRatio="none">';
    $currentX = 0;
    foreach ($modules as $m) {
        $w = $m['width'] * $unitWidth;
        if ($m['isBar']) {
            $svg .= '<rect x="' . round($currentX, 2) . '" y="0" width="' . round($w, 2) . '" height="' . $height . '" fill="#000000" />';
        }
        $currentX += $w;
    }
    $svg .= '</svg>';
    return $svg;
}
