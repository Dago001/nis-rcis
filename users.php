<?php
/**
 * NIS Residence Card Issuance System (NIS-RCIS)
 * User & Officer Role Management Portal (SuperAdmin Only)
 */

require_once __DIR__ . '/includes/auth.php';
require_once __DIR__ . '/config/database.php';
require_once __DIR__ . '/config/constants.php';

requireRole(ROLE_SUPER_ADMIN);

$pageTitle = 'Account Management';
$db = Database::getConnection();
$errors = [];

// Handle New User Creation
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['create_user'])) {
    if (!validate_csrf()) {
        $errors[] = 'Security token mismatch. Please try again.';
    } else {
        $serviceNo = trim($_POST['service_number'] ?? '');
        $username = $serviceNo;
        $surname = strtoupper(trim($_POST['surname'] ?? ''));
        $otherName = strtoupper(trim($_POST['other_name'] ?? ''));
        $rawFullname = trim($surname . ($otherName !== '' ? ' ' . $otherName : '')) ?: strtoupper(trim($_POST['fullname'] ?? ''));
        $fullname = stripRank($rawFullname);
        $email = trim($_POST['email'] ?? '');
        $password = trim($_POST['password'] ?? '');
        $role = trim($_POST['role'] ?? ROLE_ISSUING_OFFICER);
        $command = strtoupper(trim($_POST['command'] ?? 'Abuja Central Enrollment Center'));

        if (empty($surname) || empty($serviceNo) || empty($password)) {
            $errors[] = 'Surname, Service Number, and Password are all required.';
        } elseif (!preg_match('/^\d{1,5}$/', $serviceNo)) {
            $errors[] = 'Service Number must be numeric and maximum 5 digits (e.g. 39031).';
        } else {
            // Check uniqueness
            $chk = $db->prepare("SELECT id FROM users WHERE username = :u OR service_number = :s LIMIT 1");
            $chk->execute([':u' => $username, ':s' => $serviceNo]);
            if ($chk->fetch()) {
                $errors[] = "User account with this Service Number already exists.";
            } else {
                $pwdHash = password_hash($password, PASSWORD_BCRYPT);
                $ins = $db->prepare("INSERT INTO users (username, fullname, service_number, email, password_hash, role, command) 
                                     VALUES (:u, :fn, :sn, :em, :ph, :ro, :cmd)");
                $ins->execute([
                    ':u' => $username,
                    ':fn' => $fullname,
                    ':sn' => $serviceNo,
                    ':em' => $email,
                    ':ph' => $pwdHash,
                    ':ro' => $role,
                    ':cmd' => $command
                ]);

                logAudit('USER_CREATED', null, "Created new user account: {$username} ({$fullname}) with role {$role}");
                setFlash('success', "User account <strong>{$fullname}</strong> created successfully!");
                header("Location: users");
                exit();
            }
        }
    }
}

// Handle Status Toggle
if (isset($_GET['toggle_status']) && (int)$_GET['toggle_status'] > 0) {
    $targetId = (int)$_GET['toggle_status'];
    // Prevent deactivating own account
    if ($targetId !== (int)$_SESSION['user_id']) {
        $db->prepare("UPDATE users SET is_active = CASE WHEN is_active = 1 THEN 0 ELSE 1 END WHERE id = :id")->execute([':id' => $targetId]);
        logAudit('USER_STATUS_TOGGLED', null, "Toggled active status for user ID {$targetId}");
        setFlash('success', 'User activation status updated successfully.');
    }
    header("Location: users");
    exit();
}

// Search & Pagination Setup (20 per page)
$search = trim($_GET['search'] ?? '');
$page = max(1, (int)($_GET['page'] ?? 1));
$perPage = 20;

$whereSql = "";
$params = [];

if (!empty($search)) {
    $whereSql = " WHERE (fullname LIKE :s1 OR service_number LIKE :s2 OR username LIKE :s3 OR email LIKE :s4 OR role LIKE :s5 OR command LIKE :s6)";
    $term = "%{$search}%";
    $params[':s1'] = $term;
    $params[':s2'] = $term;
    $params[':s3'] = $term;
    $params[':s4'] = $term;
    $params[':s5'] = $term;
    $params[':s6'] = $term;
}

$countStmt = $db->prepare("SELECT COUNT(*) FROM users" . $whereSql);
$countStmt->execute($params);
$totalUsers = (int)$countStmt->fetchColumn();

$totalPages = max(1, (int)ceil($totalUsers / $perPage));
if ($page > $totalPages) {
    $page = $totalPages;
}
$offset = ($page - 1) * $perPage;

$dataStmt = $db->prepare("SELECT * FROM users" . $whereSql . " ORDER BY id ASC LIMIT {$perPage} OFFSET {$offset}");
$dataStmt->execute($params);
$allUsers = $dataStmt->fetchAll();

require_once __DIR__ . '/includes/header.php';
require_once __DIR__ . '/includes/sidebar.php';
?>

<div class="page-title-box">
    <div>
        <h2><i class="fas fa-users-cog"></i> Account Management</h2>
        <div class="subtitle">Assign roles and credentials</div>
    </div>
</div>

<?php if (!empty($errors)): ?>
    <div class="alert alert-danger">
        <i class="fas fa-exclamation-circle"></i>
        <div>
            <strong>Error creating user account:</strong>
            <ul style="margin-left: 20px; margin-top: 5px;">
                <?php foreach ($errors as $err): ?>
                    <li><?php echo h($err); ?></li>
                <?php endforeach; ?>
            </ul>
        </div>
    </div>
<?php endif; ?>

<style>
.user-form-grid {
    display: grid;
    grid-template-columns: 1fr 1fr;
    gap: 16px;
    margin-bottom: 20px;
}
@media (max-width: 768px) {
    .user-form-grid {
        grid-template-columns: 1fr;
    }
}
</style>

<!-- Row 1: Create New User Form -->
<div class="app-card" style="margin-bottom: 2rem;">
    <div class="card-header">
        <h3><i class="fas fa-user-plus"></i> Create New User</h3>
    </div>
    <div class="card-body">
        <form method="POST" action="users">
            <?php echo csrf_field(); ?>
            <input type="hidden" name="create_user" value="1">

            <div class="user-form-grid">
                <!-- 1. Service No (Username) - comes first before name -->
                <div class="form-group" style="margin-bottom: 0;">
                    <label for="service_number" class="required">Service No. (Username)</label>
                    <input type="text" id="service_number" name="service_number" class="form-control" required 
                           placeholder="e.g. 39031" maxlength="5" pattern="\d{1,5}" inputmode="numeric"
                           value="<?php echo h($_POST['service_number'] ?? ''); ?>">
                    <small style="color: #64748b; font-size: 0.72rem;">Service No. serves as the login username (maximum 5 digits).</small>
                </div>

                <!-- 2. Surname -->
                <div class="form-group" style="margin-bottom: 0;">
                    <label for="surname" class="required">Surname</label>
                    <input type="text" id="surname" name="surname" class="form-control" required 
                           placeholder="e.g. ADELEKE" value="<?php echo h($_POST['surname'] ?? ''); ?>">
                </div>

                <!-- 3. Other Name -->
                <div class="form-group" style="margin-bottom: 0;">
                    <label for="other_name">Other Name</label>
                    <input type="text" id="other_name" name="other_name" class="form-control" 
                           placeholder="e.g. NGOZI MARY" value="<?php echo h($_POST['other_name'] ?? ''); ?>">
                </div>

                <!-- 4. Email -->
                <div class="form-group" style="margin-bottom: 0;">
                    <label for="email">Email</label>
                    <input type="email" id="email" name="email" class="form-control" 
                           placeholder="officer@immigration.gov.ng" style="text-transform: none !important;"
                           value="<?php echo h($_POST['email'] ?? ''); ?>">
                </div>

                <!-- 5. Temporary Password with Eye Icon & Default password123 -->
                <div class="form-group" style="margin-bottom: 0;">
                    <label for="password" class="required">Temporary Password</label>
                    <div style="position: relative; display: flex; align-items: center;">
                        <input type="password" id="password" name="password" class="form-control" required 
                               value="password123" placeholder="Min 8 characters" style="padding-right: 42px;">
                        <button type="button" id="togglePasswordBtn" 
                                style="position: absolute; right: 8px; background: none; border: none; color: #64748b; cursor: pointer; padding: 6px 10px; font-size: 1rem;" 
                                title="Toggle password visibility" aria-label="Toggle password visibility">
                            <i class="fas fa-eye" id="togglePasswordIcon"></i>
                        </button>
                    </div>
                </div>

                <!-- 6. User Role -->
                <div class="form-group" style="margin-bottom: 0;">
                    <label for="role" class="required">User Role</label>
                    <select id="role" name="role" class="form-select" required>
                        <option value="IssuingOfficer">Issuing Officer (Enrollment)</option>
                        <option value="ApprovingOfficer">Approving Officer (Approval & Endorsement)</option>
                        <option value="Inspector">Inspector (Border Posts)</option>
                        <option value="Auditor">Auditor (View Only)</option>
                        <option value="SuperAdmin">Super Administrator</option>
                    </select>
                </div>
            </div>

            <div style="display: flex; justify-content: flex-end;">
                <button type="submit" class="btn btn-primary" style="padding: 10px 24px;">
                    <i class="fas fa-user-plus"></i> Create User
                </button>
            </div>
        </form>
    </div>
</div>

<!-- Row 2: Registered Account Directory -->
<div class="app-card">
    <div class="card-header" style="display: flex; justify-content: space-between; align-items: center; flex-wrap: wrap; gap: 15px;">
        <h3 style="margin-bottom: 0;"><i class="fas fa-user-shield"></i> Registered Account</h3>
        <form method="GET" action="users" style="display: flex; gap: 8px; align-items: center; max-width: 460px; flex: 1; justify-content: flex-end;">
            <input type="text" name="search" class="form-control form-control-sm" 
                   placeholder="Search by Name, Service No, Role, Command..." 
                   value="<?php echo h($search); ?>" style="max-width: 280px; text-transform: none !important;">
            <button type="submit" class="btn btn-primary btn-sm" style="white-space: nowrap;">
                <i class="fas fa-search"></i> Search
            </button>
            <?php if (!empty($search)): ?>
                <a href="users" class="btn btn-outline btn-sm">Reset</a>
            <?php endif; ?>
        </form>
    </div>
    <div class="card-body" style="padding: 0;">
        <div class="table-responsive">
            <table class="nis-table">
                <thead>
                    <tr>
                        <th style="width: 45px; text-align: center;">S/N</th>
                        <th>Officer Name &amp; Service No.</th>
                        <th>Username</th>
                        <th>Role</th>
                        <th>Assigned Center</th>
                        <th>Status</th>
                        <th>Action</th>
                    </tr>
                </thead>
                <tbody>
                    <?php if (empty($allUsers)): ?>
                        <tr>
                            <td colspan="7" style="text-align: center; padding: 2.5rem; color: #94a3b8;">
                                No registered accounts found matching your search.
                            </td>
                        </tr>
                    <?php else: ?>
                        <?php foreach ($allUsers as $idx => $u): ?>
                            <tr>
                                <td style="text-align: center; font-weight: 600; color: #64748b; font-size: 0.85rem;">
                                    <?php echo ($offset + $idx + 1); ?>
                                </td>
                                <td>
                                    <strong><?php echo h(stripRank($u['fullname'])); ?></strong>
                                    <div style="font-size: 0.75rem; color: #64748b;">Service No: <strong><?php echo h($u['service_number']); ?></strong></div>
                                </td>
                                <td><code><?php echo h($u['username']); ?></code></td>
                                <td><span style="font-weight: 600; font-size: 0.85rem; color: #334155;"><?php echo h($u['role']); ?></span></td>
                                <td style="font-size: 0.85rem; color: #475569; font-weight: 500;">NIS HQ</td>
                                <td>
                                    <?php if ((int)$u['is_active'] === 1): ?>
                                        <span style="color: #166534; font-weight: 600; font-size: 0.85rem;">Active</span>
                                    <?php else: ?>
                                        <span style="color: #b91c1c; font-weight: 600; font-size: 0.85rem;">Inactive</span>
                                    <?php endif; ?>
                                </td>
                                <td>
                                    <?php if ((int)$u['id'] !== (int)$_SESSION['user_id']): ?>
                                        <a href="users?toggle_status=<?php echo (int)$u['id']; ?>" 
                                           class="btn btn-outline btn-sm" data-confirm="Change active status for this user?">
                                            <?php echo (int)$u['is_active'] === 1 ? 'Deactivate' : 'Activate'; ?>
                                        </a>
                                    <?php else: ?>
                                        <span style="font-size: 0.75rem; color: #94a3b8;">(Current User)</span>
                                    <?php endif; ?>
                                </td>
                            </tr>
                        <?php endforeach; ?>
                    <?php endif; ?>
                </tbody>
            </table>
        </div>

        <?php if ($totalPages > 1 || $totalUsers > 0): ?>
            <div style="display: flex; justify-content: space-between; align-items: center; padding: 14px 20px; border-top: 1px solid var(--nis-border); background: #f8fafc; font-size: 0.85rem; flex-wrap: wrap; gap: 10px;">
                <div style="color: #64748b;">
                    Showing <strong><?php echo $totalUsers > 0 ? ($offset + 1) : 0; ?></strong> to <strong><?php echo min($totalUsers, $offset + count($allUsers)); ?></strong> of <strong><?php echo number_format($totalUsers); ?></strong> accounts
                </div>
                <div style="display: flex; gap: 8px; align-items: center;">
                    <?php 
                    $pageParams = $_GET;
                    ?>
                    <?php if ($page > 1): ?>
                        <a href="users?<?php echo http_build_query(array_merge($pageParams, ['page' => $page - 1])); ?>" class="btn btn-outline btn-sm">
                            <i class="fas fa-chevron-left"></i> Previous
                        </a>
                    <?php else: ?>
                        <span class="btn btn-outline btn-sm" style="opacity: 0.5; cursor: not-allowed;">
                            <i class="fas fa-chevron-left"></i> Previous
                        </span>
                    <?php endif; ?>

                    <span style="font-weight: 600; padding: 0 8px; color: var(--nis-slate);">Page <?php echo $page; ?> of <?php echo $totalPages; ?></span>

                    <?php if ($page < $totalPages): ?>
                        <a href="users?<?php echo http_build_query(array_merge($pageParams, ['page' => $page + 1])); ?>" class="btn btn-primary btn-sm">
                            Next <i class="fas fa-chevron-right"></i>
                        </a>
                    <?php else: ?>
                        <span class="btn btn-outline btn-sm" style="opacity: 0.5; cursor: not-allowed;">
                            Next <i class="fas fa-chevron-right"></i>
                        </span>
                    <?php endif; ?>
                </div>
            </div>
        <?php endif; ?>
    </div>
</div>

<script>
document.addEventListener('DOMContentLoaded', function() {
    var toggleBtn = document.getElementById('togglePasswordBtn');
    var passwordInput = document.getElementById('password');
    var toggleIcon = document.getElementById('togglePasswordIcon');
    if (toggleBtn && passwordInput && toggleIcon) {
        toggleBtn.addEventListener('click', function(e) {
            e.preventDefault();
            if (passwordInput.type === 'password') {
                passwordInput.type = 'text';
                toggleIcon.classList.remove('fa-eye');
                toggleIcon.classList.add('fa-eye-slash');
            } else {
                passwordInput.type = 'password';
                toggleIcon.classList.remove('fa-eye-slash');
                toggleIcon.classList.add('fa-eye');
            }
        });
    }
});
</script>

<?php
require_once __DIR__ . '/includes/footer.php';
?>
