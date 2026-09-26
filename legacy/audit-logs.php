<?php
/**
 * NIS Residence Card Issuance System (NIS-RCIS)
 * Security Audit Trail & Event Logger (SuperAdmin Only)
 */

require_once __DIR__ . '/includes/auth.php';
require_once __DIR__ . '/config/database.php';
require_once __DIR__ . '/config/constants.php';

requireRole(ROLE_SUPER_ADMIN);

$pageTitle = 'Security Audit Logs';
$db = Database::getConnection();

$search = trim($_GET['search'] ?? '');
$page = max(1, (int)($_GET['page'] ?? 1));
$perPage = 20;

$whereSql = "";
$params = [];

if (!empty($search)) {
    $whereSql .= " AND (a.action LIKE :s1 OR a.username LIKE :s2 OR a.details LIKE :s3 OR a.ip_address LIKE :s4 OR u.fullname LIKE :s5 OR u.service_number LIKE :s6)";
    $term = "%{$search}%";
    $params[':s1'] = $term;
    $params[':s2'] = $term;
    $params[':s3'] = $term;
    $params[':s4'] = $term;
    $params[':s5'] = $term;
    $params[':s6'] = $term;
}

// Get total count of matching audit records
$countSql = "SELECT COUNT(*) FROM audit_logs a 
             LEFT JOIN users u ON (a.user_id = u.id OR (a.user_id IS NULL AND (a.username = u.service_number OR a.username = u.username)))
             WHERE 1=1" . $whereSql;
$countStmt = $db->prepare($countSql);
$countStmt->execute($params);
$totalRecords = (int)$countStmt->fetchColumn();

$totalPages = max(1, (int)ceil($totalRecords / $perPage));
if ($page > $totalPages) {
    $page = $totalPages;
}
$offset = ($page - 1) * $perPage;

$dataSql = "SELECT a.*, u.fullname, u.service_number, u.role as officer_role 
            FROM audit_logs a 
            LEFT JOIN users u ON (a.user_id = u.id OR (a.user_id IS NULL AND (a.username = u.service_number OR a.username = u.username)))
            WHERE 1=1" . $whereSql . " 
            ORDER BY a.id DESC 
            LIMIT {$perPage} OFFSET {$offset}";
$stmt = $db->prepare($dataSql);
$stmt->execute($params);
$logs = $stmt->fetchAll();

require_once __DIR__ . '/includes/header.php';
require_once __DIR__ . '/includes/sidebar.php';
?>

<div class="page-title-box">
    <div>
        <h2><i class="fas fa-shield-alt"></i> Security Audit Trail</h2>
        <div class="subtitle">Complete chronological record of all system authentications, issuances, approvals, and inspections</div>
    </div>
    <div>
        <a href="export?type=audit_logs&<?php echo http_build_query($_GET); ?>" class="btn btn-outline">
            <i class="fas fa-file-csv"></i> Export CSV
        </a>
    </div>
</div>

<div class="app-card" style="margin-bottom: 1.5rem;">
    <div class="card-body" style="padding: 1.25rem;">
        <form method="GET" action="audit-logs" style="display: flex; gap: 12px; max-width: 500px;">
            <input type="text" name="search" class="form-control" placeholder="Search by Action, Officer, IP, or Event..." value="<?php echo h($search); ?>" style="text-transform: none !important;">
            <button type="submit" class="btn btn-primary"><i class="fas fa-search"></i> Search</button>
            <a href="audit-logs" class="btn btn-outline">Reset</a>
        </form>
    </div>
</div>

<div class="app-card">
    <div class="card-header">
        <h3><i class="fas fa-history"></i> Logged Events (<?php echo number_format($totalRecords); ?>)</h3>
    </div>
    <div class="card-body" style="padding: 0;">
        <table class="nis-table">
            <thead>
                <tr>
                    <th>Timestamp</th>
                    <th>Officer / User</th>
                    <th>Action</th>
                    <th>Event Details</th>
                    <th>IP Address</th>
                </tr>
            </thead>
            <tbody>
                <?php if (empty($logs)): ?>
                    <tr><td colspan="5" style="text-align: center; padding: 2.5rem; color: #94a3b8;">No audit events found.</td></tr>
                <?php else: ?>
                    <?php foreach ($logs as $l): ?>
                        <tr>
                            <td style="font-size: 0.8rem; color: #64748b; white-space: nowrap;">
                                <?php echo h($l['created_at']); ?>
                            </td>
                            <td>
                                <?php if (!empty($l['fullname'])): ?>
                                    <strong style="color: var(--nis-green-dark);"><?php echo h($l['fullname']); ?></strong>
                                    <div style="font-size: 0.78rem; color: #64748b;">
                                        Service No: <strong><?php echo h($l['service_number'] ?: $l['username']); ?></strong>
                                    </div>
                                <?php elseif (!empty($l['username']) && $l['username'] !== 'GUEST_OR_SYSTEM'): ?>
                                    <strong style="color: var(--nis-green-dark);"><?php echo h($l['username']); ?></strong>
                                    <div style="font-size: 0.78rem; color: #64748b;">
                                        Service No: <strong><?php echo h($l['username']); ?></strong>
                                    </div>
                                <?php else: ?>
                                    <strong style="color: #475569;">System / Public</strong>
                                    <div style="font-size: 0.75rem; color: #94a3b8;">Automated / Inspection</div>
                                <?php endif; ?>
                            </td>
                            <td>
                                <span class="badge" style="background: #e2e8f0; color: #334155; font-size: 0.72rem;">
                                    <?php echo h($l['action']); ?>
                                </span>
                            </td>
                            <td style="font-size: 0.85rem; color: #334155;">
                                <?php echo h($l['details']); ?>
                                <?php if ($l['card_id']): ?>
                                     <a href="card-details?id=<?php echo (int)$l['card_id']; ?>" style="color: var(--nis-green); margin-left: 6px;">[View Card]</a>
                                <?php endif; ?>
                            </td>
                            <td><code><?php echo h($l['ip_address']); ?></code></td>
                        </tr>
                    <?php endforeach; ?>
                <?php endif; ?>
            </tbody>
        </table>

        <?php if ($totalPages > 1 || $totalRecords > 0): ?>
            <div style="display: flex; justify-content: space-between; align-items: center; padding: 14px 20px; border-top: 1px solid var(--nis-border); background: #f8fafc; font-size: 0.85rem; flex-wrap: wrap; gap: 10px;">
                <div style="color: #64748b;">
                    Showing <strong><?php echo $totalRecords > 0 ? ($offset + 1) : 0; ?></strong> to <strong><?php echo min($totalRecords, $offset + count($logs)); ?></strong> of <strong><?php echo number_format($totalRecords); ?></strong> entries
                </div>
                <div style="display: flex; gap: 8px; align-items: center;">
                    <?php 
                    $pageParams = $_GET;
                    ?>
                    <?php if ($page > 1): ?>
                        <a href="audit-logs?<?php echo http_build_query(array_merge($pageParams, ['page' => $page - 1])); ?>" class="btn btn-outline btn-sm">
                            <i class="fas fa-chevron-left"></i> Previous
                        </a>
                    <?php else: ?>
                        <span class="btn btn-outline btn-sm" style="opacity: 0.5; cursor: not-allowed;">
                            <i class="fas fa-chevron-left"></i> Previous
                        </span>
                    <?php endif; ?>

                    <span style="font-weight: 600; padding: 0 8px; color: var(--nis-slate);">Page <?php echo $page; ?> of <?php echo $totalPages; ?></span>

                    <?php if ($page < $totalPages): ?>
                        <a href="audit-logs?<?php echo http_build_query(array_merge($pageParams, ['page' => $page + 1])); ?>" class="btn btn-primary btn-sm">
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

<?php
require_once __DIR__ . '/includes/footer.php';
?>
