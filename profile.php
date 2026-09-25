<?php
/**
 * NIS Residence Card Issuance System (NIS-RCIS)
 * User Profile & Security Credentials
 */

require_once __DIR__ . '/includes/auth.php';
require_once __DIR__ . '/config/database.php';
require_once __DIR__ . '/config/constants.php';

$pageTitle = 'User Profile';
$db = Database::getConnection();
$user = currentUser();

// Refresh officer record from database
$stmt = $db->prepare("SELECT * FROM users WHERE id = :id LIMIT 1");
$stmt->execute([':id' => $user['id']]);
$officer = $stmt->fetch();

$errors = [];

// Handle Profile Photo Upload
if (($_SERVER['REQUEST_METHOD'] ?? '') === 'POST' && isset($_POST['upload_photo'])) {
    if (!validate_csrf()) {
        $errors[] = 'Security token validation failed. Please refresh and try again.';
    } else {
        if (!isset($_FILES['profile_photo']) || $_FILES['profile_photo']['error'] !== UPLOAD_ERR_OK) {
            $errors[] = 'Please select a valid image file to upload.';
        } else {
            $file = $_FILES['profile_photo'];
            $allowedExts = ['jpg', 'jpeg', 'png', 'webp'];
            $fileExt = strtolower(pathinfo($file['name'], PATHINFO_EXTENSION));

            if (!in_array($fileExt, $allowedExts)) {
                $errors[] = 'Invalid image format. Allowed formats: JPG, PNG, WEBP.';
            } elseif ($file['size'] > 2 * 1024 * 1024) {
                $errors[] = 'Image size exceeds maximum limit of 2MB.';
            } else {
                $finfo = finfo_open(FILEINFO_MIME_TYPE);
                $mimeType = finfo_file($finfo, $file['tmp_name']);
                finfo_close($finfo);

                $allowedMimes = ['image/jpeg', 'image/png', 'image/webp'];
                if (!in_array($mimeType, $allowedMimes)) {
                    $errors[] = 'Invalid image MIME type detected.';
                } else {
                    $uploadDir = __DIR__ . '/uploads/avatars';
                    if (!is_dir($uploadDir)) {
                        @mkdir($uploadDir, 0755, true);
                    }

                    // Remove old custom photo if exists
                    if (!empty($officer['photo_path']) && file_exists(__DIR__ . '/' . $officer['photo_path'])) {
                        @unlink(__DIR__ . '/' . $officer['photo_path']);
                    }

                    $fileName = 'user_' . $officer['id'] . '_' . time() . '.' . $fileExt;
                    $targetPath = $uploadDir . '/' . $fileName;
                    $relativePath = 'uploads/avatars/' . $fileName;

                    if (move_uploaded_file($file['tmp_name'], $targetPath)) {
                        $updPhoto = $db->prepare("UPDATE users SET photo_path = :pp WHERE id = :id");
                        $updPhoto->execute([':pp' => $relativePath, ':id' => $officer['id']]);

                        $_SESSION['photo_path'] = $relativePath;
                        logAudit('PROFILE_PHOTO_UPDATED', null, "User {$officer['fullname']} ({$officer['service_number']}) uploaded a new profile photo.");
                        setFlash('success', 'Profile photo updated successfully!');
                        header("Location: profile");
                        exit();
                    } else {
                        $errors[] = 'Failed to save uploaded photo. Please check folder write permissions.';
                    }
                }
            }
        }
    }
}

// Handle Profile Photo Removal
if (($_SERVER['REQUEST_METHOD'] ?? '') === 'POST' && isset($_POST['remove_photo'])) {
    if (!validate_csrf()) {
        $errors[] = 'Security token validation failed. Please refresh and try again.';
    } else {
        if (!empty($officer['photo_path']) && file_exists(__DIR__ . '/' . $officer['photo_path'])) {
            @unlink(__DIR__ . '/' . $officer['photo_path']);
        }
        $upd = $db->prepare("UPDATE users SET photo_path = NULL WHERE id = :id");
        $upd->execute([':id' => $officer['id']]);

        $_SESSION['photo_path'] = null;
        logAudit('PROFILE_PHOTO_REMOVED', null, "User {$officer['fullname']} removed profile photo.");
        setFlash('info', 'Profile photo has been removed.');
        header("Location: profile");
        exit();
    }
}

// Handle Password Change Request
if (($_SERVER['REQUEST_METHOD'] ?? '') === 'POST' && isset($_POST['change_password'])) {
    if (!validate_csrf()) {
        $errors[] = 'Security token validation failed. Please refresh and try again.';
    } else {
        $currentPassword = $_POST['current_password'] ?? '';
        $newPassword = $_POST['new_password'] ?? '';
        $confirmPassword = $_POST['confirm_password'] ?? '';

        if (empty($currentPassword) || empty($newPassword) || empty($confirmPassword)) {
            $errors[] = 'All password fields are required.';
        } elseif ($newPassword !== $confirmPassword) {
            $errors[] = 'New password and confirmation password do not match.';
        } elseif (strlen($newPassword) < 6) {
            $errors[] = 'New password must be at least 6 characters long.';
        } elseif (!password_verify($currentPassword, $officer['password_hash'])) {
            $errors[] = 'Current password entered is incorrect.';
        } else {
            $newHash = password_hash($newPassword, PASSWORD_BCRYPT);
            $upd = $db->prepare("UPDATE users SET password_hash = :ph WHERE id = :id");
            $upd->execute([':ph' => $newHash, ':id' => $officer['id']]);

            logAudit('PASSWORD_CHANGED', null, "User {$officer['fullname']} ({$officer['service_number']}) changed their password.");
            setFlash('success', 'Your password has been changed successfully!');
            header("Location: profile");
            exit();
        }
    }
}

// Search & Pagination for Recent Activity (Break after 10 records)
$logSearch = trim($_GET['log_search'] ?? '');
$logPage = max(1, (int)($_GET['log_page'] ?? 1));
$logsPerPage = 10;

$whereSql = "WHERE user_id = :uid";
$params = [':uid' => $officer['id']];
if (!empty($logSearch)) {
    $whereSql .= " AND (action LIKE :s1 OR details LIKE :s2 OR ip_address LIKE :s3)";
    $term = "%{$logSearch}%";
    $params[':s1'] = $term;
    $params[':s2'] = $term;
    $params[':s3'] = $term;
}

$countLogsStmt = $db->prepare("SELECT COUNT(*) FROM audit_logs {$whereSql}");
$countLogsStmt->execute($params);
$totalLogs = (int)$countLogsStmt->fetchColumn();

$totalLogPages = max(1, ceil($totalLogs / $logsPerPage));
if ($logPage > $totalLogPages) $logPage = $totalLogPages;
$logOffset = ($logPage - 1) * $logsPerPage;

$stmtLogs = $db->prepare("SELECT * FROM audit_logs {$whereSql} ORDER BY id DESC LIMIT {$logsPerPage} OFFSET {$logOffset}");
$stmtLogs->execute($params);
$recentLogs = $stmtLogs->fetchAll();

require_once __DIR__ . '/includes/header.php';
require_once __DIR__ . '/includes/sidebar.php';
?>

<div class="page-title-box">
    <div>
        <h2><i class="fas fa-user-circle" style="color: var(--nis-green);"></i> User Profile</h2>
        <div class="subtitle">User account particulars, security credentials, and profile photo</div>
    </div>
    <div>
        <a href="dashboard" class="btn btn-outline">
            <i class="fas fa-arrow-left"></i> Return to Dashboard
        </a>
    </div>
</div>

<?php if (!empty($errors)): ?>
    <div class="alert alert-danger">
        <i class="fas fa-exclamation-circle"></i>
        <div>
            <strong>Security Notice:</strong>
            <ul style="margin-left: 20px; margin-top: 5px;">
                <?php foreach ($errors as $err): ?>
                    <li><?php echo h($err); ?></li>
                <?php endforeach; ?>
            </ul>
        </div>
    </div>
<?php endif; ?>

<div class="profile-layout">
    <!-- Left Column: User Particulars & Photo Upload -->
    <div style="display: flex; flex-direction: column; gap: 1.5rem;">
        <!-- Card 1: User Particulars -->
        <div class="app-card" style="margin-bottom: 0;">
            <div class="card-header" style="display: flex; justify-content: space-between; align-items: center;">
                <h3 style="margin: 0; font-size: 1.05rem; font-weight: 700;">
                    <i class="fas fa-id-badge" style="color: var(--nis-green); margin-right: 8px;"></i> User Particulars
                </h3>
                <span class="badge badge-issued">ACTIVE</span>
            </div>
            <div class="card-body">
                <div style="display: flex; align-items: center; gap: 1.25rem; margin-bottom: 1.5rem; padding-bottom: 1.25rem; border-bottom: 1px solid var(--nis-border);">
                    <div style="width: 70px; height: 70px; border-radius: 50%; overflow: hidden; background: linear-gradient(135deg, var(--nis-green-dark), var(--nis-green)); color: var(--nis-gold); display: flex; align-items: center; justify-content: center; font-size: 1.85rem; font-weight: 700; box-shadow: 0 4px 10px rgba(0,0,0,0.12); flex-shrink: 0; border: 2px solid var(--nis-border);">
                        <?php if (!empty($officer['photo_path']) && file_exists(__DIR__ . '/' . $officer['photo_path'])): ?>
                            <img src="<?php echo h($officer['photo_path']); ?>" alt="User Photo" style="width: 100%; height: 100%; object-fit: cover;">
                        <?php else: ?>
                            <?php echo strtoupper(substr($officer['fullname'], 0, 1)); ?>
                        <?php endif; ?>
                    </div>
                    <div>
                        <h3 style="margin: 0 0 4px 0; color: var(--nis-slate); font-size: 1.25rem;"><?php echo h($officer['fullname']); ?></h3>
                        <div style="font-size: 0.85rem; color: #64748b;">
                            <span>Service No: <strong style="color: var(--nis-green-dark);"><?php echo h($officer['service_number']); ?></strong></span>
                            &bull;
                            <span>Role: <strong><?php echo h($officer['role']); ?></strong></span>
                        </div>
                    </div>
                </div>

                <div class="profile-details-grid">
                    <div>
                        <label style="font-size: 0.72rem; text-transform: uppercase; color: #64748b; font-weight: 700; display: block; margin-bottom: 4px;">Service Number (Username)</label>
                        <div style="font-weight: 600; font-family: monospace; font-size: 0.95rem;"><?php echo h($officer['service_number']); ?></div>
                    </div>
                    <div>
                        <label style="font-size: 0.72rem; text-transform: uppercase; color: #64748b; font-weight: 700; display: block; margin-bottom: 4px;">Assigned Role</label>
                        <div style="font-weight: 600;"><?php echo h($officer['role']); ?></div>
                    </div>
                    <div>
                        <label style="font-size: 0.72rem; text-transform: uppercase; color: #64748b; font-weight: 700; display: block; margin-bottom: 4px;">Email Address</label>
                        <div style="font-weight: 500;"><?php echo h($officer['email'] ?: 'N/A'); ?></div>
                    </div>
                    <div>
                        <label style="font-size: 0.72rem; text-transform: uppercase; color: #64748b; font-weight: 700; display: block; margin-bottom: 4px;">Last Login Timestamp</label>
                        <div style="color: #64748b; font-size: 0.82rem;"><?php echo h($officer['last_login'] ?: 'Active Session'); ?></div>
                    </div>
                    <div style="grid-column: span 2;">
                        <label style="font-size: 0.72rem; text-transform: uppercase; color: #64748b; font-weight: 700; display: block; margin-bottom: 4px;">Command / Station</label>
                        <div style="font-weight: 600; color: var(--nis-slate); display: flex; align-items: center; gap: 8px;">
                            <i class="fas fa-map-marker-alt" style="color: var(--nis-green);"></i>
                            <?php echo h($officer['command']); ?>
                        </div>
                    </div>
                </div>
            </div>
        </div>

        <!-- Card 2: Profile Photo Upload Section -->
        <div class="app-card" style="margin-bottom: 0;">
            <div class="card-header">
                <h3 style="margin: 0; font-size: 1.05rem; font-weight: 700;">
                    <i class="fas fa-camera" style="color: var(--nis-green); margin-right: 8px;"></i> Profile Photo Upload
                </h3>
            </div>
            <div class="card-body">
                <form method="POST" action="profile" enctype="multipart/form-data">
                    <?php echo csrf_field(); ?>
                    <input type="hidden" name="upload_photo" value="1">
                    
                    <div style="display: flex; gap: 18px; align-items: center; flex-wrap: wrap;">
                        <div id="photoPreviewBox" style="width: 76px; height: 76px; border-radius: 50%; overflow: hidden; background: #f8fafc; border: 2px dashed var(--nis-border); display: flex; align-items: center; justify-content: center; flex-shrink: 0;">
                            <?php if (!empty($officer['photo_path']) && file_exists(__DIR__ . '/' . $officer['photo_path'])): ?>
                                <img id="avatarPreviewImg" src="<?php echo h($officer['photo_path']); ?>" alt="Current Avatar" style="width: 100%; height: 100%; object-fit: cover;">
                            <?php else: ?>
                                <i id="avatarPlaceholderIcon" class="fas fa-user" style="font-size: 2rem; color: #94a3b8;"></i>
                                <img id="avatarPreviewImg" src="" alt="Preview" style="display: none; width: 100%; height: 100%; object-fit: cover;">
                            <?php endif; ?>
                        </div>

                        <div style="flex: 1; min-width: 220px;">
                            <label for="profile_photo" style="font-weight: 600; font-size: 0.85rem; display: block; margin-bottom: 5px;">
                                Select Profile Image
                            </label>
                            <input type="file" id="profile_photo" name="profile_photo" accept="image/jpeg,image/png,image/webp" class="form-control" style="font-size: 0.82rem; height: 38px;" required onchange="previewAvatar(event)">
                            <div style="font-size: 0.74rem; color: #64748b; margin-top: 4px;">Supported: JPG, PNG, WEBP (Max 2MB)</div>
                        </div>
                    </div>

                    <div style="display: flex; gap: 10px; margin-top: 18px;">
                        <button type="submit" class="btn btn-primary btn-sm">
                            <i class="fas fa-upload"></i> Upload Photo
                        </button>
                        <?php if (!empty($officer['photo_path'])): ?>
                            <button type="submit" form="removePhotoForm" class="btn btn-outline btn-sm text-danger" onclick="return confirm('Are you sure you want to remove your profile photo?');">
                                <i class="fas fa-trash-alt"></i> Remove Photo
                            </button>
                        <?php endif; ?>
                    </div>
                </form>

                <?php if (!empty($officer['photo_path'])): ?>
                    <form id="removePhotoForm" method="POST" action="profile" style="display: none;">
                        <?php echo csrf_field(); ?>
                        <input type="hidden" name="remove_photo" value="1">
                    </form>
                <?php endif; ?>
            </div>
        </div>
    </div>

    <!-- Right Column: Change Password / Security Box -->
    <div class="app-card" style="margin-bottom: 0; align-self: flex-start;">
        <div class="card-header">
            <h3 style="margin: 0; font-size: 1.05rem; font-weight: 700;">
                <i class="fas fa-lock" style="color: var(--nis-navy); margin-right: 8px;"></i> Update Access Password
            </h3>
        </div>
        <div class="card-body">
            <form method="POST" action="profile">
                <?php echo csrf_field(); ?>
                <input type="hidden" name="change_password" value="1">

                <div class="form-group" style="margin-bottom: 1rem;">
                    <label for="current_password" class="required">Current Password</label>
                    <input type="password" id="current_password" name="current_password" class="form-control" 
                           placeholder="Enter current password" required>
                </div>

                <div class="form-group" style="margin-bottom: 1rem;">
                    <label for="new_password" class="required">New Password</label>
                    <input type="password" id="new_password" name="new_password" class="form-control" 
                           placeholder="Enter new password (minimum 6 characters)" required minlength="6">
                </div>

                <div class="form-group" style="margin-bottom: 1.5rem;">
                    <label for="confirm_password" class="required">Confirm New Password</label>
                    <input type="password" id="confirm_password" name="confirm_password" class="form-control" 
                           placeholder="Confirm new password" required minlength="6">
                </div>

                <button type="submit" class="btn btn-primary" style="width: 100%;">
                    <i class="fas fa-key"></i> Update Password
                </button>
            </form>
        </div>
    </div>
</div>

<!-- Bottom Section: Officer Recent Audit Trail (With break after 10 records & search) -->
<div class="app-card">
    <div class="card-header" style="display: flex; justify-content: space-between; align-items: center; flex-wrap: wrap; gap: 12px;">
        <div style="display: flex; align-items: center; gap: 10px;">
            <h3 style="margin: 0; font-size: 1.05rem; font-weight: 700;">
                <i class="fas fa-history" style="color: var(--nis-gold-dark); margin-right: 8px;"></i> Recent Activity Recorded by Officer
            </h3>
            <span class="badge badge-info" style="font-size: 0.78rem;">
                <?php echo $totalLogs; ?> Total <?php echo $totalLogs === 1 ? 'Record' : 'Records'; ?>
            </span>
        </div>
        <form method="GET" action="profile" style="display: flex; gap: 6px; align-items: center; margin: 0;">
            <input type="text" name="log_search" class="form-control" placeholder="Search activity..." value="<?php echo h($logSearch); ?>" style="height: 32px; font-size: 0.8rem; width: 200px;">
            <button type="submit" class="btn btn-primary btn-sm" style="height: 32px; padding: 0 10px;">
                <i class="fas fa-search"></i>
            </button>
            <?php if (!empty($logSearch)): ?>
                <a href="profile" class="btn btn-outline btn-sm" style="height: 32px; padding: 0 10px; display: inline-flex; align-items: center;">Reset</a>
            <?php endif; ?>
        </form>
    </div>
    <div class="card-body" style="padding: 0;">
        <div class="table-responsive">
            <table class="nis-table" style="width: 100%; margin: 0;">
                <thead>
                    <tr>
                        <th style="width: 50px; text-align: center;">S/N</th>
                        <th style="width: 180px;">Timestamp</th>
                        <th style="width: 160px;">Action</th>
                        <th>Details</th>
                        <th style="width: 140px;">IP Address</th>
                    </tr>
                </thead>
                <tbody>
                    <?php if (empty($recentLogs)): ?>
                        <tr>
                            <td colspan="5" style="text-align: center; padding: 2rem; color: #94a3b8;">
                                No activity records found<?php echo !empty($logSearch) ? ' matching "' . h($logSearch) . '"' : ''; ?>.
                            </td>
                        </tr>
                    <?php else: ?>
                        <?php $sn = $logOffset + 1; foreach ($recentLogs as $log): ?>
                            <tr>
                                <td style="text-align: center; color: var(--text-muted); font-size: 0.82rem;"><?php echo $sn++; ?></td>
                                <td style="font-size: 0.8rem; color: #64748b; white-space: nowrap;"><?php echo h($log['created_at']); ?></td>
                                <td>
                                    <span class="badge" style="background: #e2e8f0; color: #334155; font-size: 0.75rem;">
                                        <?php echo h($log['action']); ?>
                                    </span>
                                </td>
                                <td style="font-size: 0.85rem;"><?php echo h($log['details']); ?></td>
                                <td style="font-size: 0.78rem; font-family: monospace; color: #64748b;"><?php echo h($log['ip_address']); ?></td>
                            </tr>
                        <?php endforeach; ?>
                    <?php endif; ?>
                </tbody>
            </table>
        </div>

        <?php if ($totalLogs > 0): ?>
            <div style="display: flex; justify-content: space-between; align-items: center; padding: 0.85rem 1.25rem; border-top: 1px solid var(--nis-border); background: var(--surface-alt); font-size: 0.82rem; flex-wrap: wrap; gap: 10px;">
                <div style="color: var(--text-muted);">
                    Showing <?php echo min($totalLogs, $logOffset + 1); ?> to <?php echo min($totalLogs, $logOffset + count($recentLogs)); ?> of <?php echo $totalLogs; ?> activities
                </div>
                <div style="display: flex; gap: 6px; align-items: center;">
                    <?php if ($logPage > 1): ?>
                        <a href="profile?log_page=<?php echo $logPage - 1; ?><?php echo !empty($logSearch) ? '&log_search=' . urlencode($logSearch) : ''; ?>" class="btn btn-sm btn-outline">
                            <i class="fas fa-chevron-left"></i> Previous
                        </a>
                    <?php else: ?>
                        <button type="button" class="btn btn-sm btn-outline" disabled style="opacity: 0.5; cursor: not-allowed;">
                            <i class="fas fa-chevron-left"></i> Previous
                        </button>
                    <?php endif; ?>

                    <span style="font-weight: 600; padding: 0 8px; color: var(--text-color);">
                        Page <?php echo $logPage; ?> of <?php echo $totalLogPages; ?>
                    </span>

                    <?php if ($logPage < $totalLogPages): ?>
                        <a href="profile?log_page=<?php echo $logPage + 1; ?><?php echo !empty($logSearch) ? '&log_search=' . urlencode($logSearch) : ''; ?>" class="btn btn-sm btn-outline">
                            Next <i class="fas fa-chevron-right"></i>
                        </a>
                    <?php else: ?>
                        <button type="button" class="btn btn-sm btn-outline" disabled style="opacity: 0.5; cursor: not-allowed;">
                            Next <i class="fas fa-chevron-right"></i>
                        </button>
                    <?php endif; ?>
                </div>
            </div>
        <?php endif; ?>
    </div>
</div>

<script>
function previewAvatar(event) {
    const file = event.target.files[0];
    if (file) {
        const reader = new FileReader();
        reader.onload = function(e) {
            const previewImg = document.getElementById('avatarPreviewImg');
            const placeholderIcon = document.getElementById('avatarPlaceholderIcon');
            if (placeholderIcon) placeholderIcon.style.display = 'none';
            if (previewImg) {
                previewImg.src = e.target.result;
                previewImg.style.display = 'block';
            }
        };
        reader.readAsDataURL(file);
    }
}
</script>

<?php
require_once __DIR__ . '/includes/footer.php';
?>
