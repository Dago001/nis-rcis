<?php
/**
 * NIS Residence Card Issuance System (NIS-RCIS)
 * Approval Queue & Review Station (Unified Applications Queue)
 */

require_once __DIR__ . '/includes/auth.php';
require_once __DIR__ . '/config/database.php';
require_once __DIR__ . '/config/constants.php';

$pageTitle = 'Approval Queue';
$db = Database::getConnection();
$user = currentUser();
$canApprove = hasRole([ROLE_SUPER_ADMIN, ROLE_APPROVING_OFFICER]);

// =========================================================================
// POST HANDLER: Application Approval / Query / Rejection
// =========================================================================
if (($_SERVER['REQUEST_METHOD'] ?? '') === 'POST' && (isset($_POST['action_online_approval']) || isset($_POST['action_approval']))) {
    if (!validate_csrf()) {
        setFlash('danger', 'Security token validation failed. Please try again.');
        header("Location: pending-approvals");
        exit();
    }

    if (!$canApprove) {
        setFlash('danger', 'Access denied. You do not possess approval authority.');
        header("Location: pending-approvals");
        exit();
    }

    $appId = (int)($_POST['application_id'] ?? 0);
    $decision = trim($_POST['decision'] ?? 'APPROVE');
    $notes = trim($_POST['approval_notes'] ?? '');

    $appStmt = $db->prepare("SELECT * FROM applications WHERE id = :id LIMIT 1");
    $appStmt->execute([':id' => $appId]);
    $targetApp = $appStmt->fetch();

    if (!$targetApp) {
        setFlash('danger', 'Application record not found.');
        header("Location: pending-approvals");
        exit();
    }

    require_once __DIR__ . '/includes/notifications.php';

    if ($decision === 'APPROVE') {
        $upd = $db->prepare("UPDATE applications SET 
            status = 'APPROVED_FOR_BIOMETRICS', 
            approved_by = :uid, 
            approved_at = CURRENT_TIMESTAMP, 
            approval_notes = :notes 
            WHERE id = :id");
        $upd->execute([
            ':uid' => $user['id'],
            ':notes' => $notes !== '' ? $notes : 'Application approved. Authorized for physical biometrics capturing at enrollment center.',
            ':id' => $appId
        ]);

        logAudit("APPLICATION_APPROVED", $appId, "Approving Officer {$user['fullname']} ({$user['service_number']}) approved Application No. {$targetApp['application_number']} ({$targetApp['surname']}, {$targetApp['forenames']}) for physical biometrics capturing at {$targetApp['enrollment_center']}.");

        createNotification(
            (int)$user['id'],
            NOTIF_APPROVAL_SENT,
            'Application Approved',
            "You approved Application No. {$targetApp['application_number']} ({$targetApp['surname']}, {$targetApp['forenames']}) for biometrics appointment on {$targetApp['appointment_date']}.",
            "print-appointment-slip?app={$targetApp['application_number']}",
            $db
        );

        setFlash('success', "Application No. <strong>{$targetApp['application_number']}</strong> for <strong>{$targetApp['surname']} {$targetApp['forenames']}</strong> has been <strong>APPROVED FOR BIOMETRICS</strong>.");
        header("Location: pending-approvals?status=PENDING_APPROVAL");
        exit();

    } elseif ($decision === 'QUERY') {
        $upd = $db->prepare("UPDATE applications SET 
            status = 'QUERIED', 
            approved_by = :uid, 
            approved_at = CURRENT_TIMESTAMP, 
            approval_notes = :notes 
            WHERE id = :id");
        $upd->execute([
            ':uid' => $user['id'],
            ':notes' => $notes !== '' ? $notes : 'Application queried. Please rectify uploaded documentation or contact enrollment desk.',
            ':id' => $appId
        ]);

        logAudit("APPLICATION_QUERIED", $appId, "Application No. {$targetApp['application_number']} queried by {$user['fullname']}: {$notes}");

        createNotification(
            (int)$user['id'],
            NOTIF_APPROVAL_SENT,
            'Application Query Issued',
            "Query issued on Application No. {$targetApp['application_number']}: {$notes}",
            "pending-approvals?status=QUERIED",
            $db
        );

        setFlash('warning', "Application No. <strong>{$targetApp['application_number']}</strong> has been <strong>QUERIED</strong>.");
        header("Location: pending-approvals?status=QUERIED");
        exit();

    } elseif ($decision === 'REJECT') {
        $upd = $db->prepare("UPDATE applications SET 
            status = 'REJECTED', 
            approved_by = :uid, 
            approved_at = CURRENT_TIMESTAMP, 
            approval_notes = :notes 
            WHERE id = :id");
        $upd->execute([
            ':uid' => $user['id'],
            ':notes' => $notes !== '' ? $notes : 'Application rejected under immigration regulations.',
            ':id' => $appId
        ]);

        logAudit("APPLICATION_REJECTED", $appId, "Application No. {$targetApp['application_number']} rejected by {$user['fullname']}: {$notes}");

        setFlash('danger', "Application No. <strong>{$targetApp['application_number']}</strong> has been <strong>REJECTED</strong>.");
        header("Location: pending-approvals");
        exit();
    }
}

// =========================================================================
// KPI Statistics
// =========================================================================
$countAwaitingApproval = (int)$db->query("SELECT COUNT(*) FROM applications WHERE status = 'PENDING_APPROVAL'")->fetchColumn();
$countAwaitingIssuance = (int)$db->query("SELECT COUNT(*) FROM applications WHERE status IN ('APPROVED_FOR_BIOMETRICS', 'BIOMETRICS_CAPTURED')")->fetchColumn();
$countQueried          = (int)$db->query("SELECT COUNT(*) FROM applications WHERE status = 'QUERIED'")->fetchColumn();

$countApprovedMonth    = (int)$db->query("SELECT COUNT(*) FROM applications WHERE status IN ('APPROVED_FOR_BIOMETRICS', 'BIOMETRICS_CAPTURED', 'READY_FOR_COLLECTION') AND approved_by IS NOT NULL AND SUBSTR(approved_at, 1, 7) = '" . date('Y-m') . "'")->fetchColumn();

$countTotal            = (int)$db->query("SELECT COUNT(*) FROM applications")->fetchColumn();

// Filter & Search Parameters
$statusFilter = trim($_GET['status'] ?? 'PENDING_APPROVAL');
$search = trim($_GET['search'] ?? '');
$page = max(1, (int)($_GET['page'] ?? 1));
$perPage = 20;

$whereSql = "";
$params = [];

if ($statusFilter === 'PENDING_APPROVAL') {
    $whereSql .= " AND a.status = 'PENDING_APPROVAL'";
} elseif ($statusFilter === 'AWAITING_ISSUANCE') {
    $whereSql .= " AND a.status IN ('APPROVED_FOR_BIOMETRICS', 'BIOMETRICS_CAPTURED')";
} elseif ($statusFilter === 'QUERIED') {
    $whereSql .= " AND a.status = 'QUERIED'";
} elseif ($statusFilter === 'REJECTED') {
    $whereSql .= " AND a.status = 'REJECTED'";
} elseif ($statusFilter !== 'ALL' && !empty($statusFilter)) {
    $whereSql .= " AND a.status = :st";
    $params[':st'] = $statusFilter;
}

if (!empty($search)) {
    $whereSql .= " AND (a.application_number LIKE :s1 OR a.reference_number LIKE :s2 OR a.surname LIKE :s3 OR a.forenames LIKE :s4 OR a.passport_number LIKE :s5 OR a.email LIKE :s6 OR a.phone LIKE :s7 OR a.payment_reference LIKE :s8)";
    $term = "%{$search}%";
    $params[':s1'] = $term;
    $params[':s2'] = $term;
    $params[':s3'] = $term;
    $params[':s4'] = $term;
    $params[':s5'] = $term;
    $params[':s6'] = $term;
    $params[':s7'] = $term;
    $params[':s8'] = $term;
}

$countStmt = $db->prepare("SELECT COUNT(*) FROM applications a WHERE 1=1" . $whereSql);
$countStmt->execute($params);
$totalRecords = (int)$countStmt->fetchColumn();
$totalPages = max(1, (int)ceil($totalRecords / $perPage));
if ($page > $totalPages) $page = $totalPages;
$offset = ($page - 1) * $perPage;

$dataSql = "SELECT a.*, u.fullname as approver_name, u.service_number as approver_svc 
            FROM applications a 
            LEFT JOIN users u ON a.approved_by = u.id 
            WHERE 1=1" . $whereSql . " 
            ORDER BY a.id DESC 
            LIMIT {$perPage} OFFSET {$offset}";
$stmt = $db->prepare($dataSql);
$stmt->execute($params);
$records = $stmt->fetchAll();

require_once __DIR__ . '/includes/header.php';
require_once __DIR__ . '/includes/sidebar.php';
?>

<style>
/* Status Filter Tabs - Fixed without hover jump / shake */
.status-tabs-container {
    display: flex;
    gap: 8px;
    margin-bottom: 1.25rem;
    border-bottom: 2px solid #e2e8f0;
    padding-bottom: 4px;
    overflow-x: auto;
}
.status-tab-btn {
    display: inline-flex;
    align-items: center;
    justify-content: center;
    font-size: 0.88rem;
    padding: 8px 16px;
    font-weight: 600;
    border-radius: 6px 6px 0 0;
    white-space: nowrap;
    text-decoration: none;
    border: 1px solid transparent;
    cursor: pointer;
    box-sizing: border-box;
    transform: none !important;
    transition: background-color 0.15s ease, color 0.15s ease, border-color 0.15s ease;
}
.status-tab-btn:hover,
.status-tab-btn:active,
.status-tab-btn:focus {
    transform: none !important;
}
.status-tab-btn.active {
    background: linear-gradient(135deg, var(--nis-green) 0%, var(--nis-green-dark) 100%);
    color: #ffffff !important;
    border-color: transparent;
    box-shadow: 0 2px 6px rgba(26, 92, 46, 0.25);
}
.status-tab-btn.active:hover {
    background: linear-gradient(135deg, #1f6e37 0%, #154c25 100%);
    transform: none !important;
}
.status-tab-btn.inactive {
    background: #ffffff;
    border: 1px solid #cbd5e1;
    color: var(--nis-slate);
}
.status-tab-btn.inactive:hover {
    background: #f8fafc;
    border-color: #94a3b8;
    color: #1e293b;
    transform: none !important;
}

/* Table Action Buttons - 2 Columns Layout */
.table-action-group {
    display: grid;
    grid-template-columns: repeat(2, 70px);
    gap: 4px;
    align-items: center;
    width: max-content;
}
.table-action-group .act-btn {
    display: inline-flex;
    align-items: center;
    justify-content: center;
    height: 28px;
    padding: 0 4px;
    font-size: 0.78rem;
    font-weight: 600;
    border-radius: 5px;
    text-decoration: none;
    border: 1px solid transparent;
    cursor: pointer;
    line-height: 1;
    white-space: nowrap;
    box-sizing: border-box;
    box-shadow: none !important;
    width: 100%;
    min-width: 0;
    text-align: center;
    transform: none !important;
    transition: all 0.15s ease-in-out;
}
.table-action-group .act-btn-review {
    background: #ffffff;
    border-color: #cbd5e1;
    color: #1e293b;
}
.table-action-group .act-btn-review:hover {
    background: #f1f5f9;
    border-color: #94a3b8;
    color: #0f172a;
}
.table-action-group .act-btn-approve,
.table-action-group .act-btn-biometrics {
    background: #115e2e;
    border-color: #115e2e;
    color: #ffffff;
}
.table-action-group .act-btn-approve:hover,
.table-action-group .act-btn-biometrics:hover {
    background: #0b4520;
    border-color: #0b4520;
    color: #ffffff;
}
.table-action-group .act-btn-query {
    background: #ffffff;
    border-color: #fca5a5;
    color: #c0392b;
}
.table-action-group .act-btn-query:hover {
    background: #fef2f2;
    border-color: #f87171;
    color: #991b1b;
}
.table-action-group .act-btn-slip {
    background: #ffffff;
    border-color: #cbd5e1;
    color: #1e293b;
}
.table-action-group .act-btn-slip:hover {
    background: #f1f5f9;
    border-color: #94a3b8;
    color: #0f172a;
}
</style>

<div class="page-title-box">
    <div>
        <h2>Approval Queue</h2>
        <div class="subtitle">Review, vet, and authorize residence card applications</div>
    </div>
</div>

<!-- Stat Cards Ribbon -->
<div class="stat-grid">
    <!-- Stat 1: Awaiting approval -->
    <div class="stat-card warning">
        <div class="stat-info">
            <div class="stat-label">Awaiting approval</div>
            <div class="stat-value" style="color: #d97706;"><?php echo number_format($countAwaitingApproval); ?></div>
            <div class="stat-meta" style="color: #d97706;">Pending review</div>
        </div>
        <div class="stat-icon-wrapper">
            <i class="fas fa-clock"></i>
        </div>
    </div>

    <!-- Stat 2: Awaiting Issuance -->
    <div class="stat-card">
        <div class="stat-info">
            <div class="stat-label">Awaiting Issuance</div>
            <div class="stat-value" style="color: var(--nis-navy);"><?php echo number_format($countAwaitingIssuance); ?></div>
            <div class="stat-meta" style="color: var(--nis-navy);">Biometrics &amp; card production</div>
        </div>
        <div class="stat-icon-wrapper">
            <i class="fas fa-id-card"></i>
        </div>
    </div>

    <!-- Stat 3: Queried -->
    <div class="stat-card danger">
        <div class="stat-info">
            <div class="stat-label">Queried</div>
            <div class="stat-value" style="color: #c0392b;"><?php echo number_format($countQueried); ?></div>
            <div class="stat-meta" style="color: #c0392b;">Returned for correction</div>
        </div>
        <div class="stat-icon-wrapper">
            <i class="fas fa-exclamation-triangle"></i>
        </div>
    </div>

    <!-- Stat 4: Approved This Month -->
    <div class="stat-card">
        <div class="stat-info">
            <div class="stat-label">Approved This Month</div>
            <div class="stat-value" style="color: var(--nis-green);"><?php echo number_format($countApprovedMonth); ?></div>
            <div class="stat-meta">Total approved in <?php echo date('M Y'); ?></div>
        </div>
        <div class="stat-icon-wrapper">
            <i class="fas fa-check-double"></i>
        </div>
    </div>
</div>

<!-- Status Filter Tabs -->
<div class="status-tabs-container">
    <a href="pending-approvals?status=PENDING_APPROVAL" 
       class="status-tab-btn <?php echo ($statusFilter === 'PENDING_APPROVAL') ? 'active' : 'inactive'; ?>">
        Awaiting approval
        <span class="badge" data-bg-inactive="#d97706" style="background: <?php echo ($statusFilter === 'PENDING_APPROVAL') ? '#ffffff' : '#d97706'; ?>; color: <?php echo ($statusFilter === 'PENDING_APPROVAL') ? 'var(--nis-green-dark)' : '#ffffff'; ?>; margin-left: 6px; font-size: 0.74rem;">
            <?php echo number_format($countAwaitingApproval); ?>
        </span>
    </a>

    <a href="pending-approvals?status=AWAITING_ISSUANCE" 
       class="status-tab-btn <?php echo ($statusFilter === 'AWAITING_ISSUANCE') ? 'active' : 'inactive'; ?>">
        Awaiting Issuance
        <span class="badge" data-bg-inactive="var(--nis-green)" style="background: <?php echo ($statusFilter === 'AWAITING_ISSUANCE') ? '#ffffff' : 'var(--nis-green)'; ?>; color: <?php echo ($statusFilter === 'AWAITING_ISSUANCE') ? 'var(--nis-green-dark)' : '#ffffff'; ?>; margin-left: 6px; font-size: 0.74rem;">
            <?php echo number_format($countAwaitingIssuance); ?>
        </span>
    </a>

    <a href="pending-approvals?status=QUERIED" 
       class="status-tab-btn <?php echo ($statusFilter === 'QUERIED') ? 'active' : 'inactive'; ?>">
        Queried
        <span class="badge" data-bg-inactive="#c0392b" style="background: <?php echo ($statusFilter === 'QUERIED') ? '#ffffff' : '#c0392b'; ?>; color: <?php echo ($statusFilter === 'QUERIED') ? 'var(--nis-green-dark)' : '#ffffff'; ?>; margin-left: 6px; font-size: 0.74rem;">
            <?php echo number_format($countQueried); ?>
        </span>
    </a>

    <a href="pending-approvals?status=ALL" 
       class="status-tab-btn <?php echo ($statusFilter === 'ALL') ? 'active' : 'inactive'; ?>">
        All Applications
        <span class="badge" data-bg-inactive="#64748b" style="background: <?php echo ($statusFilter === 'ALL') ? '#ffffff' : '#64748b'; ?>; color: <?php echo ($statusFilter === 'ALL') ? 'var(--nis-green-dark)' : '#ffffff'; ?>; margin-left: 6px; font-size: 0.74rem;">
            <?php echo number_format($countTotal); ?>
        </span>
    </a>
</div>

<!-- Filter & Search Panel -->
<div class="app-card" style="margin-bottom: 1.5rem;">
    <div class="card-body" style="padding: 1.25rem;">
        <form method="GET" action="pending-approvals" style="display: flex; gap: 12px; flex-wrap: wrap; align-items: center; justify-content: space-between;">
            <div style="display: flex; gap: 10px; flex-wrap: wrap; align-items: center; flex: 1;">
                <div style="min-width: 220px;">
                    <select name="status" class="form-select" onchange="this.form.submit()">
                        <option value="PENDING_APPROVAL" <?php echo ($statusFilter === 'PENDING_APPROVAL') ? 'selected' : ''; ?>>Awaiting approval (<?php echo $countAwaitingApproval; ?>)</option>
                        <option value="AWAITING_ISSUANCE" <?php echo ($statusFilter === 'AWAITING_ISSUANCE') ? 'selected' : ''; ?>>Awaiting Issuance (<?php echo $countAwaitingIssuance; ?>)</option>
                        <option value="QUERIED" <?php echo ($statusFilter === 'QUERIED') ? 'selected' : ''; ?>>Queried (<?php echo $countQueried; ?>)</option>
                        <option value="REJECTED" <?php echo ($statusFilter === 'REJECTED') ? 'selected' : ''; ?>>Rejected</option>
                        <option value="ALL" <?php echo ($statusFilter === 'ALL') ? 'selected' : ''; ?>>All Applications (<?php echo $countTotal; ?>)</option>
                    </select>
                </div>
                <div style="flex: 1; min-width: 280px;">
                    <input type="text" name="search" class="form-control" 
                           placeholder="Search by application number, reference, surname, passport, or phone..." 
                           value="<?php echo h($search); ?>" style="text-transform: none !important;">
                </div>
                <button type="submit" class="btn btn-primary">Search</button>
                <a href="pending-approvals?status=<?php echo urlencode($statusFilter); ?>" class="btn btn-outline">Reset</a>
            </div>
            <div>
                <a href="export?type=applications&status=<?php echo urlencode($statusFilter); ?>" class="btn btn-outline">Export CSV</a>
            </div>
        </form>
    </div>
</div>

<!-- Unified Applications Table -->
<div id="queueTableCard" class="app-card">
    <div class="card-header" style="display: flex; justify-content: space-between; align-items: center;">
        <h3>
            Applications Queue (<?php echo number_format($totalRecords); ?>)
        </h3>
        <span style="font-size: 0.8rem; color: #64748b;">
            Displaying 20 entries per page
        </span>
    </div>
    <div class="card-body" style="padding: 0;">
        <?php if (empty($records)): ?>
            <div style="text-align: center; padding: 3.5rem 1rem; color: #64748b;">
                <h3 style="font-size: 1.15rem; color: #1e293b; margin-bottom: 6px;">No Applications in Current View</h3>
                <p style="font-size: 0.88rem; max-width: 450px; margin: 0 auto;">No records match the selected status filter or search keywords.</p>
            </div>
        <?php else: ?>
            <div class="table-responsive">
                <table class="nis-table" style="font-size: 0.86rem;">
                    <thead>
                        <tr>
                            <th style="width: 35px; text-align: center;">#</th>
                            <th style="min-width: 155px;">Application &amp; Ref</th>
                            <th style="min-width: 175px;">Applicant Name &amp; Contact</th>
                            <th style="min-width: 160px;">Nationality &amp; Passport</th>
                            <th style="min-width: 190px;">Enrollment &amp; Schedule</th>
                            <th style="min-width: 120px;">Payment</th>
                            <th style="min-width: 110px;">Submitted</th>
                            <th style="min-width: 130px;">Status</th>
                            <th style="text-align: left; width: 155px; min-width: 155px;">Actions</th>
                        </tr>
                    </thead>
                    <tbody>
                        <?php 
                        $serial = $offset;
                        foreach ($records as $app): 
                            $serial++;
                            $st = $app['status'];
                            $statusColor = '#b45309';
                            $statusLabel = 'Awaiting approval';

                            if ($st === 'APPROVED_FOR_BIOMETRICS') {
                                $statusColor = '#166534';
                                $statusLabel = 'Approved for Biometrics';
                            } elseif ($st === 'BIOMETRICS_CAPTURED') {
                                $statusColor = '#1e40af';
                                $statusLabel = 'Biometrics Captured';
                            } elseif ($st === 'READY_FOR_COLLECTION') {
                                $statusColor = '#065f46';
                                $statusLabel = 'Ready for Collection';
                            } elseif ($st === 'QUERIED') {
                                $statusColor = '#c2410c';
                                $statusLabel = 'Queried';
                            } elseif ($st === 'REJECTED') {
                                $statusColor = '#475569';
                                $statusLabel = 'Rejected';
                            }
                        ?>
                            <tr>
                                <td style="text-align: center; color: #64748b; font-weight: 600;"><?php echo $serial; ?></td>
                                <td>
                                    <div style="font-family: monospace; font-weight: 700; color: var(--nis-green-dark); font-size: 0.92rem;">
                                        <?php echo h($app['application_number']); ?>
                                    </div>
                                    <div style="font-size: 0.74rem; color: #64748b; font-family: monospace;">
                                        Ref: <?php echo h($app['reference_number']); ?>
                                    </div>
                                </td>
                                <td>
                                    <div style="font-weight: 700; color: #1e293b;">
                                        <?php echo h($app['surname'] . ', ' . $app['forenames']); ?>
                                    </div>
                                    <div style="font-size: 0.75rem; color: #64748b; margin-top: 1px;">
                                        <?php echo h($app['email']); ?> &bull; <?php echo h($app['phone']); ?>
                                    </div>
                                </td>
                                <td>
                                    <div style="font-weight: 700; color: var(--nis-green-dark);">
                                        <?php echo h($app['nationality']); ?>
                                    </div>
                                    <div style="font-size: 0.75rem; color: #475569; font-family: monospace;">
                                        <?php echo h($app['passport_number']); ?> (Exp: <?php echo date('d/m/Y', strtotime($app['passport_expiry'])); ?>)
                                    </div>
                                </td>
                                <td>
                                    <div style="font-weight: 600; color: #1e293b; font-size: 0.83rem;">
                                        <?php echo h($app['enrollment_center']); ?>
                                    </div>
                                    <div style="font-size: 0.75rem; color: #2563eb; font-weight: 600; margin-top: 1px;">
                                        <?php echo date('d M Y', strtotime($app['appointment_date'])); ?> &bull; <?php echo h($app['appointment_time']); ?>
                                    </div>
                                </td>
                                <td>
                                    <div style="font-weight: 700; color: var(--nis-green); font-size: 0.86rem;">
                                        ₦<?php echo number_format($app['fee_amount'] ?? $app['payment_amount'] ?? 35000, 2); ?>
                                    </div>
                                    <div style="font-size: 0.72rem; color: #64748b; font-family: monospace;">
                                        <?php echo h($app['payment_reference'] ?: 'PAID'); ?>
                                    </div>
                                </td>
                                <td>
                                    <div style="font-size: 0.80rem; color: #334155;"><?php echo date('d/m/Y', strtotime($app['created_at'])); ?></div>
                                    <div style="font-size: 0.72rem; color: #64748b;"><?php echo date('h:i A', strtotime($app['created_at'])); ?></div>
                                </td>
                                <td>
                                    <span style="color: <?php echo $statusColor; ?>; font-weight: 600; font-size: 0.85rem;">
                                        <?php echo h($statusLabel); ?>
                                    </span>
                                    <?php if (!empty($app['approval_notes'])): ?>
                                        <div style="font-size: 0.72rem; color: #64748b; margin-top: 3px; max-width: 170px; line-height: 1.2;">
                                            <?php echo h(mb_strimwidth($app['approval_notes'], 0, 50, '...')); ?>
                                        </div>
                                    <?php endif; ?>
                                </td>
                                <td style="text-align: left; width: 155px;">
                                    <div class="table-action-group">
                                        <button type="button" class="act-btn act-btn-review" 
                                                onclick="openAppReviewModal(<?php echo htmlspecialchars(json_encode($app), ENT_QUOTES, 'UTF-8'); ?>)" 
                                                title="Review Application Particulars">
                                            Review
                                        </button>

                                        <?php if ($canApprove && $st === 'PENDING_APPROVAL'): ?>
                                            <button type="button" class="act-btn act-btn-approve" 
                                                    onclick="openApproveAppModal(<?php echo (int)$app['id']; ?>, '<?php echo h(addslashes($app['application_number'])); ?>', '<?php echo h(addslashes($app['surname'] . ' ' . $app['forenames'])); ?>', '<?php echo h(addslashes($app['enrollment_center'])); ?>', '<?php echo h(addslashes($app['appointment_date'])); ?>')" 
                                                    title="Approve for Biometrics Capturing">
                                                Approve
                                            </button>
                                            <button type="button" class="act-btn act-btn-query" 
                                                    onclick="openQueryAppModal(<?php echo (int)$app['id']; ?>, '<?php echo h(addslashes($app['application_number'])); ?>', '<?php echo h(addslashes($app['surname'] . ' ' . $app['forenames'])); ?>')" 
                                                    title="Query Application">
                                                Query
                                            </button>
                                        <?php endif; ?>

                                        <?php if ($st === 'APPROVED_FOR_BIOMETRICS' || $st === 'BIOMETRICS_CAPTURED'): ?>
                                            <a href="biometrics-capture?app_search=<?php echo urlencode($app['application_number']); ?>" class="act-btn act-btn-biometrics" title="Biometrics Enrollment">
                                                Biometrics
                                            </a>
                                            <a href="print-appointment-slip?app=<?php echo urlencode($app['application_number']); ?>" target="_blank" class="act-btn act-btn-slip" title="Print Biometrics Slip">
                                                Slip
                                            </a>
                                        <?php endif; ?>
                                    </div>
                                </td>
                            </tr>
                        <?php endforeach; ?>
                    </tbody>
                </table>
            </div>

            <!-- Pagination Bar -->
            <?php if ($totalPages > 1): ?>
                <div class="pagination" style="display: flex; justify-content: space-between; align-items: center; padding: 1rem 1.5rem; border-top: 1px solid var(--nis-border);">
                    <div style="font-size: 0.85rem; color: var(--nis-text-muted);">
                        Showing page <strong><?php echo $page; ?></strong> of <strong><?php echo $totalPages; ?></strong> (Total: <?php echo number_format($totalRecords); ?> records)
                    </div>
                    <div style="display: flex; gap: 6px;">
                        <?php if ($page > 1): ?>
                            <a href="pending-approvals?<?php echo http_build_query(array_merge($_GET, ['page' => 1])); ?>" class="btn btn-outline btn-sm">First</a>
                            <a href="pending-approvals?<?php echo http_build_query(array_merge($_GET, ['page' => $page - 1])); ?>" class="btn btn-outline btn-sm">Prev</a>
                        <?php endif; ?>

                        <span class="btn btn-primary btn-sm" style="cursor: default;"><?php echo $page; ?></span>

                        <?php if ($page < $totalPages): ?>
                            <a href="pending-approvals?<?php echo http_build_query(array_merge($_GET, ['page' => $page + 1])); ?>" class="btn btn-outline btn-sm">Next</a>
                            <a href="pending-approvals?<?php echo http_build_query(array_merge($_GET, ['page' => $totalPages])); ?>" class="btn btn-outline btn-sm">Last</a>
                        <?php endif; ?>
                    </div>
                </div>
            <?php endif; ?>
        <?php endif; ?>
    </div>
</div>

<!-- Modal: Approve Application for Biometrics -->
<div class="nis-modal-backdrop" id="approveAppModal">
    <div class="nis-modal-dialog" style="max-width: 560px;">
        <form method="POST" action="pending-approvals">
            <?php echo csrf_field(); ?>
            <input type="hidden" name="action_online_approval" value="1">
            <input type="hidden" name="decision" value="APPROVE">
            <input type="hidden" name="application_id" id="approveAppId" value="">

            <div class="nis-modal-header" style="background: #f0fdf4; border-bottom: 1px solid #bbf7d0; color: #14532d; padding: 1rem 1.5rem;">
                <h3 style="margin: 0; font-size: 1.08rem; font-weight: 700; display: flex; align-items: center; gap: 8px;">
                    <i class="fas fa-check-circle" style="color: #16a34a;"></i> Approve Application For Biometrics
                </h3>
                <button type="button" class="btn btn-outline btn-sm" onclick="closeModal('approveAppModal')" style="padding: 2px 8px; border: none; font-size: 1.25rem; color: #14532d; background: transparent; cursor: pointer;">&times;</button>
            </div>
            <div class="nis-modal-body" style="padding: 1.5rem;">
                <p style="font-size: 0.88rem; color: #475569; margin-bottom: 14px;">
                    Review application particulars before authorizing physical biometrics capturing:
                </p>
                <div style="background: #f8fafc; border: 1px solid #e2e8f0; border-radius: 8px; padding: 16px; margin-bottom: 16px;">
                    <div style="display: grid; grid-template-columns: 1fr 1fr; gap: 12px 18px;">
                        <div>
                            <div style="font-size: 0.72rem; color: #64748b; text-transform: uppercase; font-weight: 700; letter-spacing: 0.5px;">Application No.</div>
                            <div id="approveAppNum" style="font-size: 1.1rem; font-weight: 800; color: var(--nis-green-dark); font-family: monospace; margin-top: 2px;"></div>
                        </div>
                        <div>
                            <div style="font-size: 0.72rem; color: #64748b; text-transform: uppercase; font-weight: 700; letter-spacing: 0.5px;">Appointment Schedule</div>
                            <div id="approveAppDate" style="font-size: 0.92rem; font-weight: 700; color: #2563eb; margin-top: 2px;"></div>
                        </div>
                        <div>
                            <div style="font-size: 0.72rem; color: #64748b; text-transform: uppercase; font-weight: 700; letter-spacing: 0.5px;">Applicant Name</div>
                            <div id="approveAppName" style="font-size: 0.95rem; font-weight: 700; color: #0f172a; margin-top: 2px;"></div>
                        </div>
                        <div>
                            <div style="font-size: 0.72rem; color: #64748b; text-transform: uppercase; font-weight: 700; letter-spacing: 0.5px;">Enrollment Center</div>
                            <div id="approveAppCenter" style="font-size: 0.88rem; font-weight: 600; color: #334155; margin-top: 2px;"></div>
                        </div>
                    </div>
                </div>

                <div class="form-group" style="margin-bottom: 14px;">
                    <label style="font-size: 0.82rem; font-weight: 700; color: #334155; margin-bottom: 6px; display: block;">
                        <i class="fas fa-file-signature" style="color: var(--nis-green); margin-right: 4px;"></i> Approval Notes / Directive
                    </label>
                    <input type="text" name="approval_notes" class="form-control" 
                           value="Approved for physical biometrics capturing on appointment date." 
                           placeholder="Optional endorsement directive..." style="font-size: 0.86rem; padding: 9px 12px;">
                </div>

                <div style="font-size: 0.82rem; color: #166534; line-height: 1.45; background: #f0fdf4; padding: 12px 14px; border-radius: 8px; border: 1px solid #bbf7d0; display: flex; gap: 10px; align-items: flex-start;">
                    <i class="fas fa-info-circle" style="color: #16a34a; font-size: 1rem; margin-top: 2px; flex-shrink: 0;"></i>
                    <div>
                        Upon approval, the application status transitions to <strong>Approved for Biometrics</strong>. The applicant is authorized to print their official <strong>Biometrics Appointment Slip</strong> and attend the enrollment desk.
                    </div>
                </div>
            </div>
            <div class="nis-modal-footer" style="padding: 1rem 1.5rem; background: #f8fafc; border-top: 1px solid #e2e8f0; display: flex; justify-content: flex-end; gap: 10px;">
                <button type="button" class="btn btn-outline" onclick="closeModal('approveAppModal')" style="padding: 8px 18px; font-size: 0.88rem;">Cancel</button>
                <button type="submit" class="btn btn-primary" style="padding: 8px 22px; font-size: 0.88rem; background: #115e2e; border-color: #115e2e;">
                    <i class="fas fa-check"></i> Authorize Biometrics
                </button>
            </div>
        </form>
    </div>
</div>

<!-- Modal: Query Application -->
<div class="nis-modal-backdrop" id="queryAppModal">
    <div class="nis-modal-dialog">
        <form method="POST" action="pending-approvals">
            <?php echo csrf_field(); ?>
            <input type="hidden" name="action_online_approval" value="1">
            <input type="hidden" name="decision" value="QUERY">
            <input type="hidden" name="application_id" id="queryAppId" value="">

            <div class="nis-modal-header" style="background: #fef3c7; color: #92400e;">
                <h3>Query Application</h3>
                <button type="button" class="btn btn-outline btn-sm" onclick="closeModal('queryAppModal')" style="padding: 2px 8px; border: none; font-size: 1.1rem;">&times;</button>
            </div>
            <div class="nis-modal-body">
                <p style="font-size: 0.9rem; color: #1e293b; margin-bottom: 12px;">
                    Specify reason for querying application <strong id="queryAppNum"></strong> (<span id="queryAppName"></span>):
                </p>

                <div class="form-group" style="margin-bottom: 12px;">
                    <label style="font-size: 0.8rem; font-weight: 700; color: #334155; margin-bottom: 4px; display: block;">
                        Query Directive / Notes *
                    </label>
                    <textarea name="approval_notes" class="form-control" style="min-height: 90px;" 
                              placeholder="e.g. Passport validity under 6 months; Residential address documentation unclear..." required></textarea>
                </div>
                <div style="font-size: 0.8rem; color: #64748b;">
                    The applicant's status tracking will display this query directive.
                </div>
            </div>
            <div class="nis-modal-footer">
                <button type="button" class="btn btn-outline" onclick="closeModal('queryAppModal')">Cancel</button>
                <button type="submit" class="btn btn-danger">Issue Query</button>
            </div>
        </form>
    </div>
</div>

<!-- Modal: Review Full Application Particulars -->
<div class="nis-modal-backdrop" id="reviewAppModal">
    <div class="nis-modal-dialog" style="max-width: 680px;">
        <div class="nis-modal-header">
            <h3>Application Particulars &amp; Review</h3>
            <button type="button" class="btn btn-outline btn-sm" onclick="closeModal('reviewAppModal')" style="padding: 2px 8px; border: none; font-size: 1.1rem;">&times;</button>
        </div>
        <div class="nis-modal-body" id="reviewAppBody" style="max-height: 70vh; overflow-y: auto;">
            <!-- Rendered dynamically by JavaScript -->
        </div>
        <div class="nis-modal-footer">
            <button type="button" class="btn btn-outline" onclick="closeModal('reviewAppModal')">Close</button>
        </div>
    </div>
</div>

<script>
function openApproveAppModal(id, appNum, name, center, date) {
    document.getElementById('approveAppId').value = id;
    document.getElementById('approveAppNum').innerText = appNum;
    document.getElementById('approveAppName').innerText = name;
    document.getElementById('approveAppCenter').innerText = center;
    document.getElementById('approveAppDate').innerText = date;
    document.getElementById('approveAppModal').classList.add('active');
}

function openQueryAppModal(id, appNum, name) {
    document.getElementById('queryAppId').value = id;
    document.getElementById('queryAppNum').innerText = appNum;
    document.getElementById('queryAppName').innerText = name;
    document.getElementById('queryAppModal').classList.add('active');
}

function openAppReviewModal(app) {
    var photoHtml = '';
    if (app.photo_path) {
        photoHtml = `
            <div style="width: 120px; text-align: center; flex-shrink: 0;">
                <img src="${app.photo_path}" alt="Passport Photo" style="width: 110px; height: 140px; object-fit: contain; object-position: center; border: 2px solid #cbd5e1; border-radius: 6px; box-shadow: 0 2px 8px rgba(0,0,0,0.1); background: #f8fafc;">
                <div style="font-size: 0.7rem; font-weight: 700; color: var(--nis-green-dark); margin-top: 4px;">PASSPORT PHOTO</div>
            </div>
        `;
    }

    var payMethodLabel = (app.payment_method === 'PAYSTACK') ? 'Paystack Gateway' : ((app.payment_method === 'BANK_TRANSFER') ? 'Bank Transfer' : 'Debit / Credit Card');

    function renderDocBadge(docPath, label) {
        if (docPath && docPath.trim() !== '') {
            return `
                <div style="background: #f8fafc; border: 1px solid #e2e8f0; border-radius: 6px; padding: 10px; display: flex; align-items: center; justify-content: space-between; gap: 8px;">
                    <div>
                        <div style="font-size: 0.72rem; color: #64748b; font-weight: 700; text-transform: uppercase;">${label}</div>
                        <div style="font-size: 0.8rem; font-weight: 600; color: #1e293b; margin-top: 2px;">
                            Attached Document
                        </div>
                    </div>
                    <a href="${docPath}" target="_blank" class="btn btn-outline btn-sm" style="font-size: 0.76rem; padding: 4px 10px;" title="Open and Inspect Document">
                        View
                    </a>
                </div>
            `;
        }
        return `
            <div style="background: #f8fafc; border: 1px dashed #cbd5e1; border-radius: 6px; padding: 10px;">
                <div style="font-size: 0.72rem; color: #94a3b8; font-weight: 700; text-transform: uppercase;">${label}</div>
                <div style="font-size: 0.78rem; color: #94a3b8; font-style: italic; margin-top: 2px;">Not Attached</div>
            </div>
        `;
    }

    var html = `
        <div style="display: flex; gap: 14px; margin-bottom: 16px;">
            ${photoHtml}
            <div style="flex: 1; display: grid; grid-template-columns: 1fr 1fr; gap: 12px;">
                <div style="background: #f8fafc; padding: 12px; border-radius: 6px; border: 1px solid #e2e8f0;">
                    <div style="font-size: 0.72rem; color: #64748b; font-weight: 700; text-transform: uppercase;">Application Details</div>
                    <div style="font-size: 1.05rem; font-weight: 800; color: var(--nis-green-dark); font-family: monospace;">${app.application_number}</div>
                    <div style="font-size: 0.8rem; color: #475569;">Ref: <strong>${app.reference_number}</strong></div>
                    <div style="font-size: 0.8rem; color: #475569;">Status: <strong>${app.status.replace(/_/g, ' ')}</strong></div>
                </div>
                <div style="background: #f8fafc; padding: 12px; border-radius: 6px; border: 1px solid #e2e8f0;">
                    <div style="font-size: 0.72rem; color: #64748b; font-weight: 700; text-transform: uppercase;">Payment & Schedule</div>
                    <div style="font-size: 1.05rem; font-weight: 800; color: #166534;">₦${parseFloat(app.fee_amount || app.payment_amount || 35000).toLocaleString('en-US', {minimumFractionDigits: 2})}</div>
                    <div style="font-size: 0.78rem; color: #475569;">Method: <strong>${payMethodLabel}</strong></div>
                    <div style="font-size: 0.75rem; color: #475569; font-family: monospace;">Ref: <strong>${app.payment_reference || 'PAID'}</strong></div>
                    <div style="font-size: 0.8rem; color: #2563eb;">Date: <strong>${app.appointment_date} (${app.appointment_time})</strong></div>
                </div>
            </div>
        </div>

        <div style="background: #ffffff; border: 1px solid #e2e8f0; border-radius: 6px; padding: 14px; margin-bottom: 14px;">
            <h4 style="font-size: 0.85rem; text-transform: uppercase; color: #334155; margin-bottom: 10px; border-bottom: 1px solid #f1f5f9; padding-bottom: 6px;">
                Personal &amp; Passport Particulars
            </h4>
            <div style="display: grid; grid-template-columns: 1fr 1fr; gap: 10px; font-size: 0.84rem;">
                <div><span style="color: #64748b;">Full Name:</span> <strong>${app.surname}, ${app.forenames}</strong></div>
                <div><span style="color: #64748b;">Gender:</span> <strong>${app.sex || app.gender || '—'}</strong></div>
                <div><span style="color: #64748b;">Date of Birth:</span> <strong>${app.date_of_birth}</strong></div>
                <div><span style="color: #64748b;">Place of Birth:</span> <strong>${app.place_of_birth || '—'}</strong></div>
                <div><span style="color: #64748b;">Nationality:</span> <strong style="color: var(--nis-green-dark);">${app.nationality}</strong></div>
                <div><span style="color: #64748b;">Passport No:</span> <strong style="font-family: monospace;">${app.passport_number}</strong></div>
                <div><span style="color: #64748b;">Passport Issue Date:</span> <strong>${app.passport_issue_date || '—'}</strong></div>
                <div><span style="color: #64748b;">Passport Expiry:</span> <strong>${app.passport_expiry || '—'}</strong></div>
                <div><span style="color: #64748b;">Profession:</span> <strong>${app.profession || app.occupation || '—'}</strong></div>
                <div><span style="color: #64748b;">Physical:</span> <strong>${app.complexion || '—'} &bull; ${app.eye_color || '—'} eyes &bull; ${app.hair_color || '—'} hair${app.blood_group ? ' &bull; Blood: ' + app.blood_group : ''}</strong></div>
            </div>
        </div>

        <div style="background: #ffffff; border: 1px solid #e2e8f0; border-radius: 6px; padding: 14px; margin-bottom: 14px;">
            <h4 style="font-size: 0.85rem; text-transform: uppercase; color: #334155; margin-bottom: 10px; border-bottom: 1px solid #f1f5f9; padding-bottom: 6px;">
                Nigerian Address &amp; Contact
            </h4>
            <div style="font-size: 0.84rem; line-height: 1.5;">
                <div><span style="color: #64748b;">Residential Address:</span> <strong>${app.domicile || app.residential_address || '—'}</strong></div>
                ${app.change_of_address ? `<div style="margin-top: 6px; padding: 6px 10px; background: #fefce8; border: 1px solid #fef08a; border-radius: 4px;"><span style="color: #854d0e; font-weight: 600;">Change of Address:</span> <strong style="color: #713f12;">${app.change_of_address}</strong></div>` : ''}
                <div style="display: grid; grid-template-columns: 1fr 1fr; gap: 10px; margin-top: 6px;">
                    <div><span style="color: #64748b;">Phone:</span> <strong>${app.phone}</strong></div>
                    <div><span style="color: #64748b;">Email:</span> <strong>${app.email}</strong></div>
                </div>
            </div>
        </div>

        <div style="background: #ffffff; border: 1px solid #e2e8f0; border-radius: 6px; padding: 14px; margin-bottom: 14px;">
            <h4 style="font-size: 0.85rem; text-transform: uppercase; color: #334155; margin-bottom: 10px; border-bottom: 1px solid #f1f5f9; padding-bottom: 6px;">
                Next of Kin &amp; Enrollment Station
            </h4>
            <div style="display: grid; grid-template-columns: 1fr 1fr; gap: 10px; font-size: 0.84rem;">
                <div><span style="color: #64748b;">Next of Kin:</span> <strong>${app.emergency_contact_name || '—'}</strong> (${app.emergency_contact_relation || '—'})</div>
                <div><span style="color: #64748b;">Contact Phone:</span> <strong>${app.emergency_contact_phone || '—'}</strong></div>
                <div style="grid-column: span 2;"><span style="color: #64748b;">Address:</span> <strong>${app.emergency_contact_address || '—'}</strong></div>
                <div style="grid-column: span 2;"><span style="color: #64748b;">Scheduled Center:</span> <strong>${app.enrollment_center || '—'}</strong></div>
            </div>
        </div>

        <div style="background: #ffffff; border: 1px solid #e2e8f0; border-radius: 6px; padding: 14px;">
            <h4 style="font-size: 0.85rem; text-transform: uppercase; color: #334155; margin-bottom: 10px; border-bottom: 1px solid #f1f5f9; padding-bottom: 6px;">
                Uploaded Supporting Documents
            </h4>
            <div style="display: grid; grid-template-columns: 1fr 1fr; gap: 10px;">
                ${renderDocBadge(app.doc_passport_copy, 'Passport Bio-data Page')}
                ${renderDocBadge(app.doc_residence_visa, 'STR / Valid Entry Visa')}
                ${renderDocBadge(app.doc_quota_approval, 'Quota Approval / Employment')}
                ${renderDocBadge(app.doc_domicile_proof, 'Proof of Nigerian Residence')}
                ${renderDocBadge(app.doc_additional, 'Tax ID / Additional Document')}
            </div>
        </div>
    `;

    document.getElementById('reviewAppBody').innerHTML = html;
    document.getElementById('reviewAppModal').classList.add('active');
}

function closeModal(modalId) {
    var modal = document.getElementById(modalId);
    if (modal) {
        modal.classList.remove('active');
    }
}

// Close modals when clicking backdrop
document.addEventListener('click', function(e) {
    if (e.target.classList.contains('nis-modal-backdrop')) {
        e.target.classList.remove('active');
    }
});

// Smooth AJAX Tab & Queue Switching (No page reload, no shaking)
document.addEventListener('DOMContentLoaded', function() {
    function initAjaxQueueTransitions() {
        const tabs = document.querySelectorAll('.status-tab-btn');
        const queueCard = document.getElementById('queueTableCard');

        tabs.forEach(tab => {
            tab.addEventListener('click', function(e) {
                if (e.ctrlKey || e.metaKey || e.shiftKey) return;
                e.preventDefault();
                const url = this.getAttribute('href');
                if (!url) return;

                // Update active tab visual state immediately
                tabs.forEach(t => {
                    t.classList.remove('active');
                    t.classList.add('inactive');
                    const b = t.querySelector('.badge');
                    if (b) {
                        b.style.background = b.getAttribute('data-bg-inactive') || '#64748b';
                        b.style.color = '#ffffff';
                    }
                });
                this.classList.remove('inactive');
                this.classList.add('active');
                const activeBadge = this.querySelector('.badge');
                if (activeBadge) {
                    activeBadge.style.background = '#ffffff';
                    activeBadge.style.color = 'var(--nis-green-dark)';
                }

                if (queueCard) {
                    queueCard.style.transition = 'opacity 0.12s ease';
                    queueCard.style.opacity = '0.35';
                }

                fetch(url, { headers: { 'X-Requested-With': 'XMLHttpRequest' } })
                    .then(res => {
                        if (!res.ok) throw new Error('Network response not ok');
                        return res.text();
                    })
                    .then(html => {
                        const parser = new DOMParser();
                        const doc = parser.parseFromString(html, 'text/html');
                        const newCard = doc.getElementById('queueTableCard');
                        if (newCard && queueCard) {
                            queueCard.innerHTML = newCard.innerHTML;
                            queueCard.style.opacity = '1';
                        } else {
                            window.location.href = url;
                        }

                        // Also sync status select dropdown if present
                        const selectEl = document.querySelector('select[name="status"]');
                        const newSelect = doc.querySelector('select[name="status"]');
                        if (selectEl && newSelect) {
                            selectEl.value = newSelect.value;
                        }

                        window.history.pushState({ path: url }, '', url);
                    })
                    .catch(() => {
                        window.location.href = url;
                    });
            });
        });
    }

    initAjaxQueueTransitions();

    window.addEventListener('popstate', function() {
        window.location.reload();
    });
});
</script>

<?php
require_once __DIR__ . '/includes/footer.php';
?>
