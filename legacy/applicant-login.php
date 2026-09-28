<?php
/**
 * NIS Residence Card Issuance System (NIS-RCIS)
 * Applicant Portal Authentication (Sign In & Profile Registration)
 */

require_once __DIR__ . '/includes/security.php';
require_once __DIR__ . '/config/database.php';
require_once __DIR__ . '/config/constants.php';

if (session_status() === PHP_SESSION_NONE) {
    session_start();
}

// If already signed in as applicant, redirect to requested target or dashboard
if (!empty($_SESSION['applicant_id'])) {
    $redirect = $_GET['redirect'] ?? $_POST['redirect'] ?? 'applicant-dashboard';
    if (empty($redirect) || strpos($redirect, '://') !== false || strpos($redirect, '//') === 0) {
        $redirect = 'applicant-dashboard';
    }
    $redirect = preg_replace('/\.php(\?|$)/', '$1', $redirect);
    header("Location: " . $redirect);
    exit();
}

$db = Database::getConnection();
$error = '';
$success = '';
$activeTab = $_GET['tab'] ?? 'login';
$accountCreated = false;
$registeredEmail = '';
$registeredToken = '';
$registeredRedirect = '';
$pendingVerifyEmail = '';
$pendingVerifyToken = '';

// 0. Handle Email Verification Link Click (e.g., ?verify_token=XYZ)
if (!empty($_GET['verify_token'])) {
    $token = trim($_GET['verify_token']);
    $stmt = $db->prepare("SELECT * FROM applicants WHERE verification_token = :tk LIMIT 1");
    $stmt->execute([':tk' => $token]);
    $verifiedApplicant = $stmt->fetch();
    if ($verifiedApplicant) {
        $upd = $db->prepare("UPDATE applicants SET email_verified = 1, verification_token = NULL WHERE id = :id");
        $upd->execute([':id' => $verifiedApplicant['id']]);

        // Auto login on successful verification
        session_regenerate_id(true);
        $_SESSION['applicant_id'] = (int)$verifiedApplicant['id'];
        $_SESSION['applicant_name'] = trim($verifiedApplicant['forenames'] . ' ' . $verifiedApplicant['surname']);
        $_SESSION['applicant_email'] = $verifiedApplicant['email'];
        $_SESSION['applicant_nationality'] = $verifiedApplicant['nationality'];
        $_SESSION['applicant_passport'] = $verifiedApplicant['passport_number'];

        $redirect = $_GET['redirect'] ?? 'applicant-dashboard';
        if (empty($redirect) || strpos($redirect, '://') !== false || strpos($redirect, '//') === 0) {
            $redirect = 'applicant-dashboard';
        }
        $redirect = preg_replace('/\.php(\?|$)/', '$1', $redirect);
        header("Location: " . $redirect);
        exit();
    } else {
        $error = 'The verification link is invalid, expired, or your email has already been verified.';
        $activeTab = 'login';
    }
}

// 1. Handle Applicant Login
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['action_applicant_login'])) {
    if (!validate_csrf()) {
        $error = 'Security validation token mismatch. Please refresh and try again.';
    } else {
        $email = strtolower(trim($_POST['login_email'] ?? ''));
        $password = trim($_POST['login_password'] ?? '');

        if (empty($email) || empty($password)) {
            $error = 'Please enter both your registered email address and password.';
        } elseif (!filter_var($email, FILTER_VALIDATE_EMAIL)) {
            $error = 'Please enter a valid email address.';
        } else {
            $stmt = $db->prepare("SELECT * FROM applicants WHERE email = :em LIMIT 1");
            $stmt->execute([':em' => $email]);
            $applicant = $stmt->fetch();

            if ($applicant && password_verify($password, $applicant['password_hash'])) {
                if (isset($applicant['email_verified']) && (int)$applicant['email_verified'] === 0 && !empty($applicant['verification_token'])) {
                    $error = 'Your email address has not been verified yet. An email was sent to ' . htmlspecialchars($applicant['email']) . ' to verify your account. Please click the link in the email to verify your email.';
                    $pendingVerifyEmail = $applicant['email'];
                    $pendingVerifyToken = $applicant['verification_token'];
                    $activeTab = 'login';
                } else {
                    session_regenerate_id(true);
                    $_SESSION['applicant_id'] = (int)$applicant['id'];
                    $_SESSION['applicant_name'] = trim($applicant['forenames'] . ' ' . $applicant['surname']);
                    $_SESSION['applicant_email'] = $applicant['email'];
                    $_SESSION['applicant_nationality'] = $applicant['nationality'];
                    $_SESSION['applicant_passport'] = $applicant['passport_number'];

                    // Automatically link any past orphaned applications with this email
                    $linkStmt = $db->prepare("UPDATE applications SET applicant_id = :aid WHERE email = :em AND applicant_id IS NULL");
                    $linkStmt->execute([':aid' => $applicant['id'], ':em' => $email]);

                    $redirect = $_POST['redirect'] ?? $_GET['redirect'] ?? 'applicant-dashboard';
                    if (empty($redirect) || strpos($redirect, '://') !== false || strpos($redirect, '//') === 0) {
                        $redirect = 'applicant-dashboard';
                    }
                    $redirect = preg_replace('/\.php(\?|$)/', '$1', $redirect);
                    header("Location: " . $redirect);
                    exit();
                }
            } else {
                $error = 'Invalid email address or password. Please verify your credentials.';
            }
        }
    }
}

// 2. Handle Applicant Registration (Profile Creation)
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['action_applicant_register'])) {
    $activeTab = 'register';
    if (!validate_csrf()) {
        $error = 'Security validation token mismatch. Please refresh and try again.';
    } else {
        $surname = strtoupper(trim($_POST['reg_surname'] ?? ''));
        $forenames = strtoupper(trim($_POST['reg_forenames'] ?? ''));
        $nationality = '';
        $passportNumber = '';
        $email = strtolower(trim($_POST['reg_email'] ?? ''));
        $phone = trim($_POST['reg_phone'] ?? '');
        $password = trim($_POST['reg_password'] ?? '');
        $passwordConfirm = trim($_POST['reg_password_confirm'] ?? '');

        if (empty($surname) || empty($forenames) || empty($email) || empty($phone) || empty($password)) {
            $error = 'All fields are mandatory to create your official applicant profile.';
        } elseif (!filter_var($email, FILTER_VALIDATE_EMAIL)) {
            $error = 'Please enter a valid email address.';
        } elseif (strlen($password) < 6) {
            $error = 'Password must be at least 6 characters long.';
        } elseif ($password !== $passwordConfirm) {
            $error = 'Password and confirmation password do not match.';
        } else {
            // Check if email already registered
            $checkStmt = $db->prepare("SELECT id FROM applicants WHERE email = :em LIMIT 1");
            $checkStmt->execute([':em' => $email]);
            if ($checkStmt->fetch()) {
                $error = 'An applicant profile with this email address already exists. Please sign in instead.';
                $activeTab = 'login';
            } else {
                $pwdHash = password_hash($password, PASSWORD_BCRYPT);
                $verificationToken = bin2hex(random_bytes(24));
                $ins = $db->prepare("INSERT INTO applicants (email, password_hash, surname, forenames, nationality, passport_number, phone, email_verified, verification_token) 
                                     VALUES (:em, :pw, :sn, :fn, :nat, :pn, :ph, 0, :vtoken)");
                $ins->execute([
                    ':em' => $email,
                    ':pw' => $pwdHash,
                    ':sn' => $surname,
                    ':fn' => $forenames,
                    ':nat' => $nationality,
                    ':pn' => $passportNumber,
                    ':ph' => $phone,
                    ':vtoken' => $verificationToken
                ]);

                $newId = (int)$db->lastInsertId();

                // Link existing applications matching email
                $linkStmt = $db->prepare("UPDATE applications SET applicant_id = :aid WHERE email = :em AND applicant_id IS NULL");
                $linkStmt->execute([':aid' => $newId, ':em' => $email]);

                // Show email verification message box
                $accountCreated = true;
                $registeredEmail = $email;
                $registeredToken = $verificationToken;
                $registeredRedirect = $_POST['redirect'] ?? $_GET['redirect'] ?? '';
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
    <title>Applicant Portal — Nigeria Immigration Service</title>

    <link rel="icon" type="image/png" href="assets/images/nis-crest-logo-transparent.png?v=3">
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css">
    <link rel="stylesheet" href="assets/css/style.css?v=<?php echo APP_VERSION; ?>">

    <style>
        body {
            background: linear-gradient(135deg, rgba(248, 250, 252, 0.45) 0%, rgba(241, 245, 249, 0.55) 100%),
                        url('assets/images/login-bg.jpg') no-repeat center center fixed;
            background-size: cover;
            min-height: 100vh;
            display: flex;
            flex-direction: column;
            color: #1e293b;
            font-family: -apple-system, BlinkMacSystemFont, "Segoe UI", Roboto, "Helvetica Neue", Arial, sans-serif;
        }

        .auth-container {
            max-width: 520px;
            width: 100%;
            margin: 0 auto;
            padding: 2.5rem 1.25rem;
            flex: 1;
            display: flex;
            align-items: center;
            justify-content: center;
        }

        .auth-card {
            width: 100%;
            background: #ffffff;
            border-radius: 12px;
            box-shadow: 0 10px 25px -5px rgba(0, 0, 0, 0.08);
            border: 1px solid rgba(226, 232, 240, 0.85);
            overflow: hidden;
        }

        .email-verification-box {
            padding: 2.25rem 2rem 2.5rem;
            text-align: center;
        }

        .verification-badge {
            width: 58px;
            height: 58px;
            background: #ecfdf5;
            color: #15803d;
            border-radius: 50%;
            display: inline-flex;
            align-items: center;
            justify-content: center;
            font-size: 1.6rem;
            margin-bottom: 1rem;
        }

        .email-verification-box h2 {
            font-size: 1.35rem;
            font-weight: 700;
            color: #0f172a;
            margin: 0 0 0.5rem;
        }

        .verification-intro {
            font-size: 0.92rem;
            color: #64748b;
            margin: 0 0 1rem;
        }

        .verification-email-card {
            background: #f8fafc;
            border-radius: 8px;
            padding: 0.85rem 1.25rem;
            font-size: 0.95rem;
            color: #0f172a;
            margin: 0 auto 1.25rem;
            max-width: 420px;
            line-height: 1.5;
        }

        .verification-text {
            font-size: 0.9rem;
            color: #475569;
            line-height: 1.65;
            margin-bottom: 1.5rem;
            max-width: 440px;
            margin-left: auto;
            margin-right: auto;
        }

        .btn-verify-instant {
            display: inline-flex;
            align-items: center;
            gap: 8px;
            background: #113f1f;
            color: #ffffff;
            padding: 11px 22px;
            font-size: 0.9rem;
            font-weight: 600;
            border-radius: 6px;
            text-decoration: none;
            transition: background 0.15s ease;
        }

        .btn-verify-instant:hover {
            background: #165b2d;
            color: #ffffff;
        }

        .auth-header {
            background: #ffffff;
            padding: 2.5rem 2rem 1.25rem;
            text-align: center;
        }

        .auth-header img {
            width: 64px;
            height: auto;
            margin-bottom: 1rem;
        }

        .auth-header h1 {
            font-size: 1.35rem;
            font-weight: 700;
            color: #0f172a;
            margin: 0;
            letter-spacing: -0.2px;
        }

        .auth-header p {
            font-size: 0.88rem;
            color: #64748b;
            margin: 6px 0 0;
            font-weight: 400;
        }

        .auth-tabs-wrapper {
            padding: 0 2rem;
            margin-bottom: 0.5rem;
        }

        .auth-tabs {
            display: flex;
            background: #f1f5f9;
            border-radius: 8px;
            padding: 4px;
            border: none;
        }

        .auth-tab-btn {
            flex: 1;
            padding: 9px 16px;
            background: transparent;
            border: none;
            border-radius: 6px;
            font-size: 0.88rem;
            font-weight: 600;
            color: #64748b;
            cursor: pointer;
            transition: all 0.15s ease;
            text-align: center;
        }

        .auth-tab-btn:hover {
            color: #0f172a;
        }

        .auth-tab-btn.active {
            color: #0f172a;
            background: #ffffff;
            box-shadow: 0 1px 3px rgba(0, 0, 0, 0.08);
        }

        .auth-body {
            padding: 1.5rem 2rem 2.25rem;
        }

        .form-group {
            margin-bottom: 1.15rem;
        }

        .form-group label {
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
            outline: none;
            transition: border-color 0.15s, box-shadow 0.15s;
            box-sizing: border-box;
            background: #ffffff;
        }

        .form-control:focus {
            border-color: #0f172a;
            box-shadow: 0 0 0 1px #0f172a;
        }

        .btn-auth-submit {
            width: 100%;
            background: #113f1f;
            color: #ffffff;
            border: none;
            padding: 11px 20px;
            font-size: 0.92rem;
            font-weight: 600;
            border-radius: 6px;
            cursor: pointer;
            transition: background 0.15s ease;
            display: flex;
            align-items: center;
            justify-content: center;
            gap: 8px;
            margin-top: 1rem;
        }

        .btn-auth-submit:hover {
            background: #165b2d;
        }

        .auth-alert-danger {
            background: #fef2f2;
            color: #991b1b;
            border: none;
            padding: 11px 14px;
            border-radius: 6px;
            font-size: 0.86rem;
            margin-bottom: 1.25rem;
            display: flex;
            align-items: center;
            gap: 8px;
        }

        .auth-alert-success {
            background: #f0fdf4;
            color: #166534;
            border: none;
            padding: 11px 14px;
            border-radius: 6px;
            font-size: 0.86rem;
            margin-bottom: 1.25rem;
            display: flex;
            align-items: center;
            gap: 8px;
        }

        .password-wrapper {
            position: relative;
        }

        .password-wrapper .form-control {
            padding-right: 42px;
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
            padding: 6px;
            font-size: 0.9rem;
            line-height: 1;
            display: flex;
            align-items: center;
            justify-content: center;
            transition: color 0.15s ease;
        }

        .toggle-password:hover {
            color: #334155;
        }

        .form-row-2 {
            display: grid;
            grid-template-columns: 1fr 1fr;
            gap: 12px;
        }

        @media (max-width: 600px) {
            .auth-container {
                padding: 1.5rem 1rem;
            }
            .form-row-2 {
                grid-template-columns: 1fr;
            }
            .auth-tabs-wrapper {
                padding: 0 1.25rem;
            }
            .auth-body {
                padding: 1.25rem;
            }
        }
    </style>
</head>
<body>

    <?php require __DIR__ . '/includes/portal-header.php'; ?>

    <main class="auth-container">
        <div class="auth-card">
            <div class="auth-header">
                <img src="assets/images/nis-crest-logo-transparent.png?v=3" alt="NIS Crest">
                <h1>Applicant Self-Service Portal</h1>
                <p>Manage Applications &amp; Permit Renewals</p>
            </div>

            <?php if (!empty($accountCreated)): ?>
                <!-- Email Verification Message Box -->
                <div class="email-verification-box">
                    <div class="verification-badge">
                        <i class="fas fa-envelope-open-text"></i>
                    </div>
                    <h2>Verify Your Email Address</h2>
                    <p class="verification-intro">
                        Your account has been created successfully.
                    </p>
                    <div class="verification-email-card">
                        An email has been sent to <strong><?php echo h($registeredEmail); ?></strong> to verify your email.
                    </div>
                    <p class="verification-text">
                        Please check your inbox (and spam/junk folder) and <strong>click the link in the mail to verify your email</strong> and activate your profile before proceeding.
                    </p>

                    <?php 
                    $verifyLink = 'applicant-login?verify_token=' . urlencode($registeredToken) . (!empty($registeredRedirect) ? '&redirect=' . urlencode($registeredRedirect) : '');
                    ?>
                    <div style="margin-bottom: 1.5rem;">
                        <a href="<?php echo h($verifyLink); ?>" class="btn-verify-instant">
                            <i class="fas fa-check-circle"></i> Click here to verify email
                        </a>
                    </div>

                    <div>
                        <a href="applicant-login?tab=login<?php echo !empty($registeredRedirect) ? '&redirect=' . urlencode($registeredRedirect) : ''; ?>" style="color: #64748b; font-size: 0.86rem; text-decoration: none;">
                            &larr; Return to Sign In
                        </a>
                    </div>
                </div>
            <?php else: ?>
                <!-- Tab Switcher -->
                <div class="auth-tabs-wrapper">
                    <div class="auth-tabs">
                        <button type="button" class="auth-tab-btn <?php echo ($activeTab === 'login') ? 'active' : ''; ?>" onclick="switchAuthTab('login')">
                            Sign In
                        </button>
                        <button type="button" class="auth-tab-btn <?php echo ($activeTab === 'register') ? 'active' : ''; ?>" onclick="switchAuthTab('register')">
                            Create Profile
                        </button>
                    </div>
                </div>

                <div class="auth-body">
                    <?php if (!empty($error)): ?>
                        <div class="auth-alert-danger">
                            <i class="fas fa-exclamation-circle"></i>
                            <span><?php echo h($error); ?></span>
                        </div>
                        <?php if (!empty($pendingVerifyToken)): ?>
                            <div style="margin-top: -0.75rem; margin-bottom: 1.25rem; text-align: center;">
                                <a href="applicant-login?verify_token=<?php echo urlencode($pendingVerifyToken); ?>" style="color: #113f1f; font-weight: 600; font-size: 0.86rem; text-decoration: underline;">
                                    Click here to verify email now
                                </a>
                            </div>
                        <?php endif; ?>
                    <?php endif; ?>

                    <?php if (!empty($success)): ?>
                        <div class="auth-alert-success">
                            <i class="fas fa-check-circle"></i>
                            <span><?php echo h($success); ?></span>
                        </div>
                    <?php endif; ?>

                    <?php 
                    $reqRedirect = $_GET['redirect'] ?? $_POST['redirect'] ?? '';
                    if (!empty($reqRedirect) && (strpos($reqRedirect, 'apply') !== false)): 
                    ?>
                        <div style="background: #f8fafc; color: #334155; border-radius: 8px; padding: 12px 14px; margin-bottom: 1.25rem; font-size: 0.86rem;">
                            <strong style="color: #0f172a;">Applicant Authentication Required</strong><br>
                            Please sign in to your account or create a new profile below before proceeding to complete your Residence Card Application.
                        </div>
                    <?php endif; ?>

                    <!-- Sign In Form -->
                    <div id="tabContentLogin" style="<?php echo ($activeTab === 'login') ? 'display: block;' : 'display: none;'; ?>">
                        <form method="POST" action="applicant-login?tab=login<?php echo !empty($reqRedirect) ? '&redirect=' . urlencode($reqRedirect) : ''; ?>">
                            <?php echo csrf_field(); ?>
                            <input type="hidden" name="redirect" value="<?php echo h($reqRedirect); ?>">
                            <div class="form-group">
                                <label for="login_email">Registered Email Address</label>
                                <input type="email" id="login_email" name="login_email" class="form-control" placeholder="name@domain.com" required value="<?php echo h($_POST['login_email'] ?? ''); ?>">
                            </div>
                            <div class="form-group">
                                <label for="login_password">Account Password</label>
                                <div class="password-wrapper">
                                    <input type="password" id="login_password" name="login_password" class="form-control" placeholder="••••••••" required>
                                    <button type="button" class="toggle-password" onclick="togglePasswordVisibility('login_password', this)" aria-label="Toggle password visibility">
                                        <i class="fas fa-eye"></i>
                                    </button>
                                </div>
                            </div>
                            <button type="submit" name="action_applicant_login" class="btn-auth-submit">
                                Sign In
                            </button>
                        </form>
                        <div style="margin-top: 1.5rem; text-align: center; font-size: 0.86rem; color: #64748b;">
                            New applicant? <a href="javascript:void(0)" onclick="switchAuthTab('register')" style="color: #113f1f; font-weight: 600; text-decoration: none;">Create profile</a>
                        </div>
                    </div>

                    <!-- Create Profile Form -->
                    <div id="tabContentRegister" style="<?php echo ($activeTab === 'register') ? 'display: block;' : 'display: none;'; ?>">
                        <form method="POST" action="applicant-login?tab=register<?php echo !empty($reqRedirect) ? '&redirect=' . urlencode($reqRedirect) : ''; ?>">
                            <?php echo csrf_field(); ?>
                            <input type="hidden" name="redirect" value="<?php echo h($reqRedirect); ?>">
                            <div class="form-row-2">
                                <div class="form-group">
                                    <label for="reg_surname">Surname</label>
                                    <input type="text" id="reg_surname" name="reg_surname" class="form-control" placeholder="SURNAME" required value="<?php echo h($_POST['reg_surname'] ?? ''); ?>">
                                </div>
                                <div class="form-group">
                                    <label for="reg_forenames">Given Names</label>
                                    <input type="text" id="reg_forenames" name="reg_forenames" class="form-control" placeholder="GIVEN NAMES" required value="<?php echo h($_POST['reg_forenames'] ?? ''); ?>">
                                </div>
                            </div>

                            <div class="form-row-2">
                                <div class="form-group">
                                    <label for="reg_email">Email Address</label>
                                    <input type="email" id="reg_email" name="reg_email" class="form-control" placeholder="name@domain.com" required value="<?php echo h($_POST['reg_email'] ?? ''); ?>">
                                </div>
                                <div class="form-group">
                                    <label for="reg_phone">Telephone Number</label>
                                    <input type="tel" id="reg_phone" name="reg_phone" class="form-control" placeholder="+234..." required value="<?php echo h($_POST['reg_phone'] ?? ''); ?>">
                                </div>
                            </div>

                            <div class="form-row-2">
                                <div class="form-group">
                                    <label for="reg_password">Create Password</label>
                                    <div class="password-wrapper">
                                        <input type="password" id="reg_password" name="reg_password" class="form-control" placeholder="At least 6 characters" required>
                                        <button type="button" class="toggle-password" onclick="togglePasswordVisibility('reg_password', this)" aria-label="Toggle password visibility">
                                            <i class="fas fa-eye"></i>
                                        </button>
                                    </div>
                                </div>
                                <div class="form-group">
                                    <label for="reg_password_confirm">Confirm Password</label>
                                    <div class="password-wrapper">
                                        <input type="password" id="reg_password_confirm" name="reg_password_confirm" class="form-control" placeholder="Repeat password" required>
                                        <button type="button" class="toggle-password" onclick="togglePasswordVisibility('reg_password_confirm', this)" aria-label="Toggle password visibility">
                                            <i class="fas fa-eye"></i>
                                        </button>
                                    </div>
                                </div>
                            </div>

                            <button type="submit" name="action_applicant_register" class="btn-auth-submit">
                                Create Profile &amp; Continue
                            </button>
                        </form>
                        <div style="margin-top: 1.5rem; text-align: center; font-size: 0.86rem; color: #64748b;">
                            Already registered? <a href="javascript:void(0)" onclick="switchAuthTab('login')" style="color: #113f1f; font-weight: 600; text-decoration: none;">Sign in to your account</a>
                        </div>
                    </div>
                </div>
            <?php endif; ?>
        </div>
    </main>

    <?php require __DIR__ . '/includes/portal-footer.php'; ?>

    <script>
    function switchAuthTab(tab) {
        document.querySelectorAll('.auth-tab-btn').forEach(btn => btn.classList.remove('active'));
        if (tab === 'login') {
            document.querySelectorAll('.auth-tab-btn')[0].classList.add('active');
            document.getElementById('tabContentLogin').style.display = 'block';
            document.getElementById('tabContentRegister').style.display = 'none';
        } else {
            document.querySelectorAll('.auth-tab-btn')[1].classList.add('active');
            document.getElementById('tabContentLogin').style.display = 'none';
            document.getElementById('tabContentRegister').style.display = 'block';
        }
    }

    function togglePasswordVisibility(fieldId, btn) {
        const field = document.getElementById(fieldId);
        const icon = btn ? btn.querySelector('i') : null;
        if (!field || !icon) return;
        if (field.type === 'password') {
            field.type = 'text';
            icon.classList.remove('fa-eye');
            icon.classList.add('fa-eye-slash');
        } else {
            field.type = 'password';
            icon.classList.remove('fa-eye-slash');
            icon.classList.add('fa-eye');
        }
    }
    </script>
</body>
</html>
