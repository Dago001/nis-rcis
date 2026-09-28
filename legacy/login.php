<?php
/**
 * NIS Residence Card Issuance System (NIS-RCIS)
 * Secure Authentication Portal / Login Gateway
 */

require_once __DIR__ . '/includes/security.php';
require_once __DIR__ . '/config/database.php';
require_once __DIR__ . '/config/constants.php';

// If already logged in, redirect to dashboard
if (isset($_SESSION['user_id']) && isset($_SESSION['loggedin']) && $_SESSION['loggedin'] === true) {
    header("Location: dashboard");
    exit();
}

$error = '';
$db = Database::getConnection();

// Handle Login Submission
if (($_SERVER['REQUEST_METHOD'] ?? '') === 'POST') {
    if (!validate_csrf()) {
        $error = 'Security validation failed (CSRF mismatch). Please refresh and try again.';
    } else {
        $serviceNo = trim($_POST['service_number'] ?? $_POST['username'] ?? '');
        $password = trim($_POST['password'] ?? '');

        if (empty($serviceNo) || empty($password)) {
            $error = 'Please enter both Service Number and Password.';
        } elseif (!preg_match('/^\d{1,5}$/', $serviceNo)) {
            $error = 'Service Number must consist of numbers only (maximum 5 digits).';
        } else {
            // Lookup user by service_number or username
            $stmt = $db->prepare("SELECT * FROM users WHERE service_number = :sn OR username = :un LIMIT 1");
            $stmt->execute([':sn' => $serviceNo, ':un' => $serviceNo]);
            $user = $stmt->fetch();

            if ($user && password_verify($password, $user['password_hash'])) {
                if ((int)$user['is_active'] !== 1) {
                    $error = 'This account has been deactivated. Please consult the Service Administrator.';
                    logAudit('LOGIN_FAILED_DEACTIVATED', null, "Attempted login for inactive service number: {$serviceNo}");
                } else {
                    // Regenerate session ID upon successful authentication
                    session_regenerate_id(true);

                    $_SESSION['loggedin'] = true;
                    $_SESSION['user_id'] = (int)$user['id'];
                    $_SESSION['username'] = $user['username'];
                    $_SESSION['fullname'] = stripRank($user['fullname']);
                    $_SESSION['service_number'] = $user['service_number'];
                    $_SESSION['role'] = $user['role'];
                    $_SESSION['command'] = $user['command'];
                    $_SESSION['email'] = $user['email'];
                    $_SESSION['photo_path'] = $user['photo_path'] ?? null;
                    $_SESSION['ip_address'] = $_SERVER['REMOTE_ADDR'] ?? 'UNKNOWN';
                    $_SESSION['user_agent'] = $_SERVER['HTTP_USER_AGENT'] ?? 'UNKNOWN';

                    // Update last login
                    $updateStmt = $db->prepare("UPDATE users SET last_login = CURRENT_TIMESTAMP WHERE id = :id");
                    $updateStmt->execute([':id' => $user['id']]);

                    $cleanOfficerName = stripRank($user['fullname']);
                    logAudit('LOGIN_SUCCESS', null, "Officer {$cleanOfficerName} ({$serviceNo}) logged in successfully");

                    $redirect = $_GET['redirect'] ?? 'dashboard';
                    $redirect = preg_replace('/\.php(\?|$)/', '$1', $redirect);
                    header("Location: " . filter_var($redirect, FILTER_SANITIZE_URL));
                    exit();
                }
            } else {
                $error = 'Invalid Service Number or Password. Access denied.';
                logAudit('LOGIN_FAILED', null, "Failed login attempt for Service No: {$serviceNo}");
            }
        }
    }
}
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0, maximum-scale=5.0">
    <meta http-equiv="X-UA-Compatible" content="IE=edge">
    <title>NIS Residence Card Portal - Administrative Sign In</title>
    <!-- Favicon / URL Icon -->
    <link rel="icon" type="image/png" href="assets/images/nis-crest-logo-transparent.png?v=3">
    <link rel="icon" type="image/png" sizes="32x32" href="assets/images/favicon-32x32.png?v=3">
    <link rel="icon" type="image/png" sizes="16x16" href="assets/images/favicon-16x16.png?v=3">
    <link rel="shortcut icon" href="assets/images/favicon.ico?v=3" type="image/x-icon">
    <link rel="apple-touch-icon" href="assets/images/nis-crest-logo-transparent.png?v=3">
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css">
    <style>
        * {
            margin: 0;
            padding: 0;
            box-sizing: border-box;
        }

        body {
            font-family: -apple-system, BlinkMacSystemFont, "Segoe UI", Roboto, "Helvetica Neue", Arial, sans-serif;
            background: linear-gradient(135deg, rgba(248, 250, 252, 0.45), rgba(241, 245, 249, 0.55)),
                        url('assets/images/login-bg.jpg') center center / cover fixed no-repeat;
            color: #1e293b;
            min-height: 100vh;
            display: flex;
            align-items: center;
            justify-content: center;
            padding: 20px;
        }

        .login-wrapper {
            width: 100%;
            max-width: 400px;
            margin: 0 auto;
        }

        .login-container {
            background: #ffffff;
            border-radius: 12px;
            box-shadow: 0 10px 25px -5px rgba(0, 0, 0, 0.08);
            border: 1px solid rgba(226, 232, 240, 0.85);
            padding: 2.5rem 2rem 2.25rem;
        }

        .login-header {
            background: transparent;
            text-align: center;
            padding: 0 0 1.75rem;
            border: none;
        }

        .login-header img {
            width: 64px;
            height: auto;
            margin-bottom: 1rem;
        }

        .login-header h1 {
            font-size: 1.32rem;
            font-weight: 700;
            color: #0f172a;
            margin: 0 0 4px;
            letter-spacing: -0.2px;
        }

        .login-header p {
            font-size: 0.86rem;
            color: #64748b;
            margin: 0;
            font-weight: 400;
        }

        .alert-danger {
            background: #fef2f2;
            color: #991b1b;
            border: none;
            border-radius: 6px;
            padding: 11px 14px;
            margin-bottom: 1.25rem;
            font-size: 0.86rem;
            display: flex;
            align-items: center;
            gap: 8px;
            line-height: 1.4;
        }

        .form-group {
            margin-bottom: 1.15rem;
        }

        .form-label {
            display: block;
            font-size: 0.82rem;
            font-weight: 600;
            color: #334155;
            margin-bottom: 6px;
        }

        .form-control {
            width: 100%;
            padding: 10px 12px;
            border: 1px solid #d1d5db;
            border-radius: 6px;
            font-size: 0.92rem;
            color: #0f172a;
            background: #ffffff;
            outline: none;
            transition: border-color 0.15s, box-shadow 0.15s;
            box-sizing: border-box;
        }

        .form-control:focus {
            border-color: #0f172a;
            box-shadow: 0 0 0 1px #0f172a;
        }

        .password-wrapper {
            position: relative;
        }

        .toggle-password {
            position: absolute;
            right: 10px;
            top: 50%;
            transform: translateY(-50%);
            background: none;
            border: none;
            color: #94a3b8;
            cursor: pointer;
            padding: 4px;
            font-size: 0.85rem;
        }

        .toggle-password:hover {
            color: #475569;
        }

        .btn-login {
            width: 100%;
            padding: 11px 20px;
            background: #113f1f;
            color: #ffffff;
            border: none;
            border-radius: 6px;
            font-size: 0.92rem;
            font-weight: 600;
            cursor: pointer;
            transition: background 0.15s ease;
            display: flex;
            align-items: center;
            justify-content: center;
            gap: 6px;
            margin-top: 1.25rem;
        }

        .btn-login:hover {
            background: #165b2d;
        }

        .security-note {
            text-align: center;
            margin-top: 1.5rem;
            color: #94a3b8;
            font-size: 0.76rem;
            display: flex;
            align-items: center;
            justify-content: center;
            gap: 6px;
            border: none;
        }

        .login-footer-copy {
            text-align: center;
            margin-top: 1.5rem;
            font-size: 0.85rem;
            color: #ffffff !important;
            font-weight: normal;
            text-shadow: 0 1px 4px rgba(0, 0, 0, 0.85);
            background: transparent !important;
        }

        @media (max-width: 576px) {
            body {
                padding: 12px;
            }
            .login-container {
                padding: 2rem 1.25rem 1.75rem;
            }
        }
    </style>
</head>
<body>
    <div class="login-wrapper">
        <div class="login-container">
            <div class="login-header">
                <img src="assets/images/nis-crest-logo-transparent.png" alt="NIS Crest">
                <h1>NIS Residence Card Portal</h1>
                <p>Administrative Portal Sign In</p>
            </div>
            
            <div class="login-body">
                <?php if (!empty($error)): ?>
                    <div class="alert-danger">
                        <i class="fa fa-exclamation-circle"></i> 
                        <span><?php echo htmlspecialchars($error); ?></span>
                    </div>
                <?php endif; ?>
                
                <form method="POST" id="loginForm" autocomplete="off" action="login">
                    <?php echo csrf_field(); ?>
                    <div class="form-group">
                        <label class="form-label" for="service_number">Service Number</label>
                        <input type="text" id="service_number" name="service_number" class="form-control" 
                               placeholder="e.g. 10001" required 
                               maxlength="5" pattern="\d{1,5}" inputmode="numeric"
                               autocomplete="username" autofocus>
                    </div>
                    
                    <div class="form-group">
                        <label class="form-label" for="passwordField">Password</label>
                        <div class="password-wrapper">
                            <input type="password" id="passwordField" name="password" class="form-control" 
                                   placeholder="••••••••" required 
                                   autocomplete="current-password">
                            <button type="button" class="toggle-password" id="togglePassword" 
                                    aria-label="Toggle password visibility">
                                <i class="fa fa-eye" id="toggleIcon"></i>
                            </button>
                        </div>
                    </div>
                    
                    <button type="submit" name="login" class="btn-login">
                        Sign In
                    </button>

                    <div style="text-align: center; margin-top: 1.25rem;">
                        <a href="index" style="color: #113f1f; text-decoration: none; font-size: 0.82rem; font-weight: 500; display: inline-flex; align-items: center; gap: 6px;">
                            <i class="fas fa-arrow-left"></i> Return to Public Portal
                        </a>
                    </div>
                    
                    <div class="security-note">
                        <i class="fa fa-shield-alt"></i>
                        <span>Authorized Administrative Personnel Only</span>
                    </div>
                </form>
            </div>
        </div>
        <div class="login-footer-copy">
            &copy; <?php echo date('Y'); ?> Nigeria Immigration Service (NIS). All rights reserved.
        </div>
    </div>

    <script>
        document.addEventListener('DOMContentLoaded', function() {
            const toggleBtn = document.getElementById('togglePassword');
            const passwordField = document.getElementById('passwordField');
            const toggleIcon = document.getElementById('toggleIcon');
            
            if (toggleBtn && passwordField && toggleIcon) {
                toggleBtn.addEventListener('click', function(e) {
                    e.preventDefault();
                    if (passwordField.type === 'password') {
                        passwordField.type = 'text';
                        toggleIcon.classList.remove('fa-eye');
                        toggleIcon.classList.add('fa-eye-slash');
                    } else {
                        passwordField.type = 'password';
                        toggleIcon.classList.remove('fa-eye-slash');
                        toggleIcon.classList.add('fa-eye');
                    }
                });
            }
        });
    </script>
</body>
</html>