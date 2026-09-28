<?php
/**
 * NIS Residence Card Issuance System (NIS-RCIS)
 * All Residence Cards Directory & Management Table
 */

require_once __DIR__ . '/includes/auth.php';
require_once __DIR__ . '/config/database.php';
require_once __DIR__ . '/config/constants.php';

$pageTitle = 'Residence Cards Directory';
$db = Database::getConnection();

// Handle Search & Filtering
$search = trim($_GET['search'] ?? '');
$filterNat = trim($_GET['nationality'] ?? '');
$filterStatus = trim($_GET['status'] ?? '');

// Pagination setup
$perPage = 20;
$page = max(1, (int)($_GET['page'] ?? 1));

// Current user context
$user = currentUser();
$currentUserId = (int)($user['id'] ?? 1);

// Build dynamic WHERE clause - show all cards in registry (or filter by user if scope=my)
$whereSql = "";
$params = [];
if (isset($_GET['scope']) && $_GET['scope'] === 'my') {
    $whereSql .= " AND created_by = :current_user_id";
    $params[':current_user_id'] = $currentUserId;
}

if (!empty($search)) {
    $whereSql .= " AND (card_number LIKE :s1 OR booklet_number LIKE :s2 OR surname LIKE :s3 OR forenames LIKE :s4 OR passport_number LIKE :s5)";
    $term = "%{$search}%";
    $params[':s1'] = $term;
    $params[':s2'] = $term;
    $params[':s3'] = $term;
    $params[':s4'] = $term;
    $params[':s5'] = $term;
}

if (!empty($filterNat)) {
    $whereSql .= " AND nationality = :nat";
    $params[':nat'] = $filterNat;
}

if (!empty($filterStatus)) {
    if ($filterStatus === 'WATCHLISTED') {
        $whereSql .= " AND is_watchlisted = 1";
    } else {
        $whereSql .= " AND status = :st";
        $params[':st'] = $filterStatus;
    }
}

// Get total count of matching records
$countStmt = $db->prepare("SELECT COUNT(*) FROM residence_cards WHERE 1=1" . $whereSql);
$countStmt->execute($params);
$totalRecords = (int)$countStmt->fetchColumn();
$totalPages = max(1, ceil($totalRecords / $perPage));
if ($page > $totalPages) $page = $totalPages;
$offset = ($page - 1) * $perPage;

// CSV Export Handler
if (isset($_GET['export']) && $_GET['export'] === 'csv') {
    $exportStmt = $db->prepare("SELECT * FROM residence_cards WHERE 1=1" . $whereSql . " ORDER BY id DESC");
    $exportStmt->execute($params);
    $exportCards = $exportStmt->fetchAll();
    logAudit('EXPORT_CSV', null, "Exported residence cards CSV report");
    header('Content-Type: text/csv; charset=utf-8');
    header('Content-Disposition: attachment; filename=NIS_Residence_Cards_' . date('Ymd_His') . '.csv');
    $output = fopen('php://output', 'w');
    fputcsv($output, ['Card Number', 'Surname', 'Given Name', 'Nationality', 'Passport No', 'Issue Date', 'Expiry Date', 'Status', 'Enrollment Center']);
    foreach ($exportCards as $c) {
        fputcsv($output, [
            $c['card_number'], $c['surname'], $c['forenames'],
            $c['nationality'], $c['passport_number'], $c['issued_on'], $c['expires_on'],
            $c['status'], ($c['issued_at'] ?: 'NIS HQ')
        ]);
    }
    fclose($output);
    exit();
}

// Fetch 20 records per page
$dataSql = "SELECT * FROM residence_cards WHERE 1=1" . $whereSql . " ORDER BY id DESC LIMIT {$perPage} OFFSET {$offset}";
$stmt = $db->prepare($dataSql);
$stmt->execute($params);
$cards = $stmt->fetchAll();

require_once __DIR__ . '/includes/header.php';
require_once __DIR__ . '/includes/sidebar.php';
?>

<div class="page-title-box">
    <div>
        <h2>Residence Cards Directory</h2>
        <div class="subtitle">Search, inspect, print, and track all issued residence cards</div>
    </div>
    <div style="display: flex; gap: 10px;">
        <a href="export?type=cards&<?php echo http_build_query($_GET); ?>" class="btn btn-outline">
            Export CSV
        </a>
        <?php if (hasRole(ROLE_SUPER_ADMIN)): ?>
        <a href="new-card" class="btn btn-primary">
            Issue New Card
        </a>
        <?php endif; ?>
    </div>
</div>

<!-- Directory Toolbar -->
<style>
.directory-filter-card {
    background: #ffffff;
    border: 1px solid var(--nis-border);
    border-radius: 8px;
    box-shadow: 0 1px 3px rgba(0,0,0,0.04);
    padding: 16px 20px;
    margin-bottom: 1.5rem;
}
.dir-filter-row {
    display: flex;
    gap: 14px;
    align-items: flex-end;
    flex-wrap: wrap;
}
.dir-filter-col label {
    display: block;
    font-size: 0.74rem;
    font-weight: 700;
    color: #475569;
    text-transform: uppercase;
    letter-spacing: 0.5px;
    margin-bottom: 5px;
}
@media (max-width: 992px) {
    .dir-filter-col {
        flex: 1 1 calc(50% - 10px) !important;
        min-width: 160px !important;
    }
    .dir-filter-actions {
        flex: 1 1 100% !important;
        justify-content: flex-end !important;
    }
}
@media (max-width: 600px) {
    .dir-filter-col {
        flex: 1 1 100% !important;
    }
    .dir-filter-actions {
        flex: 1 1 100% !important;
    }
    .dir-filter-actions button,
    .dir-filter-actions a {
        flex: 1 !important;
        justify-content: center !important;
    }
}
</style>

<div class="directory-filter-card">
    <form method="GET" action="view-cards" style="margin: 0;">
        <div class="dir-filter-row">
            <!-- Search Query -->
            <div class="dir-filter-col" style="flex: 2; min-width: 250px;">
                <label for="search">Search Registry</label>
                <input type="text" id="search" name="search" class="form-control" 
                       placeholder="Card No, Name, Passport No..." value="<?php echo h($search); ?>"
                       style="width: 100%; height: 38px; font-size: 0.88rem;">
            </div>

            <!-- Nationality Filter -->
            <div class="dir-filter-col" style="flex: 1.3; min-width: 180px;">
                <label for="nationality">Nationality</label>
                <select id="nationality" name="nationality" class="form-select" style="width: 100%; height: 38px; font-size: 0.85rem;">
                    <option value="">All Nationalities</option>
                    <?php foreach ($GLOBALS['NATIONALITIES'] as $code => $name): ?>
                        <option value="<?php echo h($code); ?>" <?php echo ($filterNat === $code) ? 'selected' : ''; ?>>
                            <?php echo h($name); ?>
                        </option>
                    <?php endforeach; ?>
                </select>
            </div>

            <!-- Status Filter -->
            <div class="dir-filter-col" style="flex: 1.1; min-width: 160px;">
                <label for="status">Status</label>
                <select id="status" name="status" class="form-select" style="width: 100%; height: 38px; font-size: 0.85rem;">
                    <option value="">All Statuses</option>
                    <option value="ISSUED" <?php echo ($filterStatus === 'ISSUED') ? 'selected' : ''; ?>>Issued</option>
                    <option value="PENDING_APPROVAL" <?php echo ($filterStatus === 'PENDING_APPROVAL') ? 'selected' : ''; ?>>Pending Approval</option>
                    <option value="QUERIED" <?php echo ($filterStatus === 'QUERIED') ? 'selected' : ''; ?>>Queried</option>
                    <option value="RENEWED" <?php echo ($filterStatus === 'RENEWED') ? 'selected' : ''; ?>>Renewed</option>
                    <option value="EXPIRED" <?php echo ($filterStatus === 'EXPIRED') ? 'selected' : ''; ?>>Expired</option>
                    <option value="REVOKED" <?php echo ($filterStatus === 'REVOKED') ? 'selected' : ''; ?>>Revoked</option>
                    <option value="WATCHLISTED" <?php echo ($filterStatus === 'WATCHLISTED') ? 'selected' : ''; ?>>Watchlisted Only</option>
                </select>
            </div>

            <!-- Action Buttons -->
            <div class="dir-filter-actions" style="display: flex; gap: 8px; align-items: flex-end;">
                <button type="submit" class="btn btn-primary" style="height: 38px; padding: 0 18px; font-weight: 600;">
                    Filter
                </button>
                <a href="view-cards" class="btn btn-outline" style="height: 38px; padding: 0 14px;">
                    Reset
                </a>
            </div>
        </div>

        <!-- Filter Status & Summary Row -->
        <div class="dir-filter-meta" style="display: flex; justify-content: space-between; align-items: center; margin-top: 14px; padding-top: 10px; border-top: 1px solid #f1f5f9; font-size: 0.82rem; color: #64748b; flex-wrap: wrap; gap: 8px;">
            <div>
                <?php if (!empty($search) || !empty($filterNat) || !empty($filterStatus)): ?>
                    <span style="color: var(--nis-green-dark); font-weight: 600;">
                        Filter Applied &bull; Found <strong><?php echo number_format($totalRecords); ?></strong> matching card(s)
                    </span>
                <?php else: ?>
                    <span>
                        Total Registry Records: <strong><?php echo number_format($totalRecords); ?></strong> cards enrolled
                    </span>
                <?php endif; ?>
            </div>
            <div style="display: flex; gap: 8px; align-items: center;">
                <?php if (!empty($search)): ?>
                    <span style="background: #f1f5f9; color: #334155; font-size: 0.72rem; padding: 3px 8px; border-radius: 4px; font-weight: 600;">
                        Search: "<?php echo h($search); ?>"
                    </span>
                <?php endif; ?>
                <?php if (!empty($filterNat)): ?>
                    <span style="background: #f1f5f9; color: #334155; font-size: 0.72rem; padding: 3px 8px; border-radius: 4px; font-weight: 600;">
                        Nat: <?php echo h($filterNat); ?>
                    </span>
                <?php endif; ?>
                <?php if (!empty($filterStatus)): ?>
                    <span style="background: #f1f5f9; color: #334155; font-size: 0.72rem; padding: 3px 8px; border-radius: 4px; font-weight: 600;">
                        Status: <?php echo h($filterStatus); ?>
                    </span>
                <?php endif; ?>
            </div>
        </div>
    </form>
</div>

<!-- Directory Table -->
<div class="app-card">
    <div class="card-header" style="display: flex; justify-content: space-between; align-items: center;">
        <h3>Records Found (<?php echo $totalRecords; ?>)</h3>
        <span style="font-size: 0.8rem; color: #64748b;">Page <?php echo $page; ?> of <?php echo $totalPages; ?></span>
    </div>
    <div class="card-body" style="padding: 0;">
        <div class="table-responsive">
            <table class="nis-table">
                <thead>
                    <tr>
                        <th style="width: 45px; text-align: center;">S/N</th>
                        <th style="width: 50px;">Photo</th>
                        <th>Card No.</th>
                        <th>Full Name</th>
                        <th>Nationality</th>
                        <th>Passport No.</th>
                        <th>Validity Period</th>
                        <th>Enrollment Center</th>
                        <th>Status</th>
                        <th style="width: 190px; text-align: left;">Actions</th>
                    </tr>
                </thead>
                <tbody>
                    <?php if (empty($cards)): ?>
                        <tr>
                            <td colspan="10" style="text-align: center; padding: 3rem; color: #94a3b8;">
                                No residence cards match the specified search or filter criteria.
                            </td>
                        </tr>
                    <?php else: ?>
                        <?php foreach ($cards as $idx => $c): ?>
                            <tr>
                                <td style="text-align: center; font-weight: 600; color: #64748b; font-size: 0.85rem;">
                                    <?php echo ($offset + $idx + 1); ?>
                                </td>
                                <td>
                                    <div style="width: 42px; height: 50px; border-radius: 4px; overflow: hidden; background: #e2e8f0; border: 1px solid #cbd5e1; display: flex; align-items: center; justify-content: center;">
                                        <?php if (!empty($c['photo_path']) && file_exists(__DIR__ . '/' . $c['photo_path'])): ?>
                                            <img src="<?php echo h($c['photo_path']); ?>" alt="Photo" style="width: 100%; height: 100%; object-fit: cover;">
                                        <?php else: ?>
                                            <span style="font-size: 0.65rem; color: #94a3b8; font-weight: 600;">PHOTO</span>
                                        <?php endif; ?>
                                    </div>
                                </td>
                                <td>
                                    <strong style="color: var(--nis-green-dark); font-family: monospace; font-size: 0.95rem;">
                                        No. <?php echo h($c['card_number']); ?>
                                    </strong>
                                    <?php if (!empty($c['is_watchlisted'])): ?>
                                        <div style="font-size: 0.72rem; color: #dc2626; font-weight: 700; margin-top: 2px;">
                                            WATCHLIST
                                        </div>
                                    <?php endif; ?>
                                </td>
                                <td>
                                    <strong><?php echo h($c['surname'] . ', ' . $c['forenames']); ?></strong>
                                    <div style="font-size: 0.75rem; color: #64748b;">
                                        <?php echo h($c['sex']); ?> &bull; DOB: <?php echo h($c['date_of_birth']); ?>
                                    </div>
                                </td>
                                <td>
                                    <span style="font-weight: 500; color: #334155;">
                                        <?php echo h($c['nationality']); ?>
                                    </span>
                                </td>
                                <td>
                                    <code style="font-weight: bold; color: var(--nis-navy);"><?php echo h($c['passport_number']); ?></code>
                                </td>
                                <td>
                                    <div style="font-size: 0.85rem; font-weight: 600; color: #1e293b;">
                                        <?php echo h($c['issued_on']); ?>
                                    </div>
                                    <div style="font-size: 0.75rem; color: #64748b;">
                                        Exp: <span style="color: #b91c1c; font-weight: bold;"><?php echo h($c['expires_on']); ?></span>
                                    </div>
                                </td>
                                <td>
                                    <span style="font-size: 0.85rem; color: #475569; font-weight: 500;">
                                         <?php echo h($c['issued_at'] ?: 'NIS HQ'); ?>
                                     </span>
                                </td>
                                <td>
                                    <?php 
                                    $st = strtoupper($c['status']);
                                    $statusColor = '#166534';
                                    if ($st === 'PENDING_APPROVAL') $statusColor = '#b45309';
                                    elseif ($st === 'QUERIED') $statusColor = '#c2410c';
                                    elseif ($st === 'RENEWED') $statusColor = '#1d4ed8';
                                    elseif ($st === 'EXPIRED' || $st === 'REVOKED') $statusColor = '#b91c1c';
                                    ?>
                                    <span style="color: <?php echo $statusColor; ?>; font-weight: 600; font-size: 0.85rem;">
                                        <?php echo h(ucwords(str_replace('_', ' ', strtolower($c['status'])))); ?>
                                    </span>
                                </td>
                                <td style="text-align: left; width: 190px;">
                                    <div style="display: inline-flex; gap: 6px; align-items: center;">
                                        <a href="card-details?id=<?php echo (int)$c['id']; ?>" class="btn btn-outline btn-sm" title="View File">
                                            View
                                        </a>
                                        <a href="edit-card?id=<?php echo (int)$c['id']; ?>" class="btn btn-outline btn-sm" style="color: #b45309; border-color: #fcd34d;" title="Edit Card">
                                            Edit
                                        </a>
                                        <a href="print-idcard?id=<?php echo (int)$c['id']; ?>" class="btn btn-primary btn-sm" title="Print Card">
                                            Card
                                        </a>
                                    </div>
                                </td>
                            </tr>
                        <?php endforeach; ?>
                    <?php endif; ?>
                </tbody>
            </table>
        </div>
        <?php if ($totalPages > 1 || $totalRecords > 0): ?>
            <div style="display: flex; justify-content: space-between; align-items: center; padding: 14px 20px; border-top: 1px solid var(--nis-border); background: #f8fafc; font-size: 0.85rem; flex-wrap: wrap; gap: 10px;">
                <div style="color: #64748b;">
                    Showing <strong><?php echo $totalRecords > 0 ? ($offset + 1) : 0; ?></strong> to <strong><?php echo min($totalRecords, $offset + count($cards)); ?></strong> of <strong><?php echo $totalRecords; ?></strong> records
                </div>
                <div style="display: flex; gap: 8px; align-items: center;">
                    <?php 
                    $pageParams = $_GET;
                    ?>
                    <?php if ($page > 1): ?>
                        <a href="view-cards?<?php echo http_build_query(array_merge($pageParams, ['page' => $page - 1])); ?>" class="btn btn-outline btn-sm">
                            Previous
                        </a>
                    <?php else: ?>
                        <span class="btn btn-outline btn-sm" style="opacity: 0.5; cursor: not-allowed;">
                            Previous
                        </span>
                    <?php endif; ?>

                    <span style="font-weight: 600; padding: 0 8px; color: var(--nis-slate);">Page <?php echo $page; ?> of <?php echo $totalPages; ?></span>

                    <?php if ($page < $totalPages): ?>
                        <a href="view-cards?<?php echo http_build_query(array_merge($pageParams, ['page' => $page + 1])); ?>" class="btn btn-primary btn-sm">
                            Next
                        </a>
                    <?php else: ?>
                        <span class="btn btn-outline btn-sm" style="opacity: 0.5; cursor: not-allowed;">
                            Next
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
