<?php
/**
 * NIS Residence Card Issuance System (NIS-RCIS)
 * Officer Operations Dashboard
 */

require_once __DIR__ . '/includes/auth.php';
require_once __DIR__ . '/config/database.php';
require_once __DIR__ . '/config/constants.php';

$pageTitle = 'Dashboard Overview';
$db = Database::getConnection();
$user = currentUser();

// User & RBAC Context
$isSuperAdmin = ($user['role'] === ROLE_SUPER_ADMIN);
$userId = (int)$user['id'];
$serviceNo = trim((string)$user['service_number']);
$userRole = $user['role'];

$recentPage = max(1, (int)($_GET['recent_page'] ?? 1));
$recentPerPage = 10;

if ($isSuperAdmin) {
    // 1. SuperAdmin: Full System-Wide Consolidated Intelligence
    $totalCards = (int)$db->query("SELECT COUNT(*) FROM residence_cards")->fetchColumn();
    $activeCards = (int)$db->query("SELECT COUNT(*) FROM residence_cards WHERE status IN ('ISSUED', 'RENEWED')")->fetchColumn();
    $pendingCards = (int)$db->query("SELECT COUNT(*) FROM residence_cards WHERE status = 'PENDING_APPROVAL'")->fetchColumn();
    $totalRenewals = (int)$db->query("SELECT COUNT(*) FROM card_renewals")->fetchColumn();

    $totalRecentCards = (int)$db->query("SELECT COUNT(*) FROM residence_cards")->fetchColumn();
    $totalRecentPages = max(1, ceil($totalRecentCards / $recentPerPage));
    if ($recentPage > $totalRecentPages) $recentPage = $totalRecentPages;
    $recentOffset = ($recentPage - 1) * $recentPerPage;

    $stmtRecent = $db->prepare("SELECT * FROM residence_cards ORDER BY id DESC LIMIT {$recentPerPage} OFFSET {$recentOffset}");
    $stmtRecent->execute();
    $recentCards = $stmtRecent->fetchAll();

    $stmtNat = $db->query("SELECT nationality, COUNT(*) as count FROM residence_cards GROUP BY nationality ORDER BY count DESC LIMIT 6");
    $topNationalities = $stmtNat->fetchAll();

    $statusCounts = [
        'ISSUED' => (int)$db->query("SELECT COUNT(*) FROM residence_cards WHERE status = 'ISSUED'")->fetchColumn(),
        'RENEWED' => (int)$db->query("SELECT COUNT(*) FROM residence_cards WHERE status = 'RENEWED'")->fetchColumn(),
        'PENDING_APPROVAL' => (int)$db->query("SELECT COUNT(*) FROM residence_cards WHERE status = 'PENDING_APPROVAL'")->fetchColumn(),
        'EXPIRED_REVOKED' => (int)$db->query("SELECT COUNT(*) FROM residence_cards WHERE status IN ('EXPIRED', 'REVOKED')")->fetchColumn()
    ];
} else {
    // 2. Non-SuperAdmin: Scoped to work they have done and reports pertaining to them
    if ($userRole === ROLE_APPROVING_OFFICER) {
        $cardScopeSql = "(approved_by = :uid_a OR created_by = :uid_c OR issuing_officer_service_no = :sn)";
        $scopeParams = [':uid_a' => $userId, ':uid_c' => $userId, ':sn' => $serviceNo];

        $stmtTot = $db->prepare("SELECT COUNT(*) FROM residence_cards WHERE {$cardScopeSql}");
        $stmtTot->execute($scopeParams);
        $totalCards = (int)$stmtTot->fetchColumn();

        $stmtAct = $db->prepare("SELECT COUNT(*) FROM residence_cards WHERE {$cardScopeSql} AND status IN ('ISSUED', 'RENEWED')");
        $stmtAct->execute($scopeParams);
        $activeCards = (int)$stmtAct->fetchColumn();

        // Pending approval items in queue awaiting this approving officer's action
        $pendingCards = (int)$db->query("SELECT COUNT(*) FROM residence_cards WHERE status = 'PENDING_APPROVAL'")->fetchColumn();

        $stmtRen = $db->prepare("SELECT COUNT(*) FROM card_renewals WHERE officer_service_no = :sn_ren OR card_id IN (SELECT id FROM residence_cards WHERE {$cardScopeSql})");
        $stmtRen->execute(array_merge([':sn_ren' => $serviceNo], $scopeParams));
        $totalRenewals = (int)$stmtRen->fetchColumn();

    } elseif ($userRole === ROLE_ISSUING_OFFICER) {
        $cardScopeSql = "(created_by = :uid_c OR issuing_officer_service_no = :sn)";
        $scopeParams = [':uid_c' => $userId, ':sn' => $serviceNo];

        $stmtTot = $db->prepare("SELECT COUNT(*) FROM residence_cards WHERE {$cardScopeSql}");
        $stmtTot->execute($scopeParams);
        $totalCards = (int)$stmtTot->fetchColumn();

        $stmtAct = $db->prepare("SELECT COUNT(*) FROM residence_cards WHERE {$cardScopeSql} AND status IN ('ISSUED', 'RENEWED')");
        $stmtAct->execute($scopeParams);
        $activeCards = (int)$stmtAct->fetchColumn();

        // Pending submissions created by this issuing officer
        $stmtPend = $db->prepare("SELECT COUNT(*) FROM residence_cards WHERE {$cardScopeSql} AND status = 'PENDING_APPROVAL'");
        $stmtPend->execute($scopeParams);
        $pendingCards = (int)$stmtPend->fetchColumn();

        $stmtRen = $db->prepare("SELECT COUNT(*) FROM card_renewals WHERE officer_service_no = :sn_ren OR card_id IN (SELECT id FROM residence_cards WHERE {$cardScopeSql})");
        $stmtRen->execute(array_merge([':sn_ren' => $serviceNo], $scopeParams));
        $totalRenewals = (int)$stmtRen->fetchColumn();

    } else {
        $cardScopeSql = "(created_by = :uid_c OR issuing_officer_service_no = :sn OR watchlisted_by = :uid_w OR revoked_by = :uid_r)";
        $scopeParams = [':uid_c' => $userId, ':sn' => $serviceNo, ':uid_w' => $userId, ':uid_r' => $userId];

        $stmtTot = $db->prepare("SELECT COUNT(*) FROM residence_cards WHERE {$cardScopeSql}");
        $stmtTot->execute($scopeParams);
        $totalCards = (int)$stmtTot->fetchColumn();

        $stmtAct = $db->prepare("SELECT COUNT(*) FROM residence_cards WHERE {$cardScopeSql} AND status IN ('ISSUED', 'RENEWED')");
        $stmtAct->execute($scopeParams);
        $activeCards = (int)$stmtAct->fetchColumn();

        $stmtPend = $db->prepare("SELECT COUNT(*) FROM residence_cards WHERE {$cardScopeSql} AND status = 'PENDING_APPROVAL'");
        $stmtPend->execute($scopeParams);
        $pendingCards = (int)$stmtPend->fetchColumn();

        $stmtRen = $db->prepare("SELECT COUNT(*) FROM card_renewals WHERE officer_service_no = :sn_ren OR card_id IN (SELECT id FROM residence_cards WHERE {$cardScopeSql})");
        $stmtRen->execute(array_merge([':sn_ren' => $serviceNo], $scopeParams));
        $totalRenewals = (int)$stmtRen->fetchColumn();
    }

    $countRecentStmt = $db->prepare("SELECT COUNT(*) FROM residence_cards WHERE {$cardScopeSql}");
    $countRecentStmt->execute($scopeParams);
    $totalRecentCards = (int)$countRecentStmt->fetchColumn();
    $totalRecentPages = max(1, ceil($totalRecentCards / $recentPerPage));
    if ($recentPage > $totalRecentPages) $recentPage = $totalRecentPages;
    $recentOffset = ($recentPage - 1) * $recentPerPage;

    $stmtRecent = $db->prepare("SELECT * FROM residence_cards WHERE {$cardScopeSql} ORDER BY id DESC LIMIT {$recentPerPage} OFFSET {$recentOffset}");
    $stmtRecent->execute($scopeParams);
    $recentCards = $stmtRecent->fetchAll();

    $stmtNat = $db->prepare("SELECT nationality, COUNT(*) as count FROM residence_cards WHERE {$cardScopeSql} GROUP BY nationality ORDER BY count DESC LIMIT 6");
    $stmtNat->execute($scopeParams);
    $topNationalities = $stmtNat->fetchAll();

    $statusCounts = [];
    foreach (['ISSUED', 'RENEWED', 'PENDING_APPROVAL'] as $st) {
        $sStmt = $db->prepare("SELECT COUNT(*) FROM residence_cards WHERE {$cardScopeSql} AND status = :st");
        $sStmt->execute(array_merge($scopeParams, [':st' => $st]));
        $statusCounts[$st] = (int)$sStmt->fetchColumn();
    }
    $sExpStmt = $db->prepare("SELECT COUNT(*) FROM residence_cards WHERE {$cardScopeSql} AND status IN ('EXPIRED', 'REVOKED')");
    $sExpStmt->execute($scopeParams);
    $statusCounts['EXPIRED_REVOKED'] = (int)$sExpStmt->fetchColumn();
}

$totalTracked = array_sum($statusCounts);
$complianceRate = $totalTracked > 0 ? round((($statusCounts['ISSUED'] + $statusCounts['RENEWED']) / $totalTracked) * 100, 1) : 0;

$extraScripts = ['assets/js/chart.min.js'];
require_once __DIR__ . '/includes/header.php';
require_once __DIR__ . '/includes/sidebar.php';
?>

<div class="page-title-box">
    <div>
        <h2><i class="fas fa-user-shield" style="color: var(--nis-green);"></i> Welcome, <?php echo h($user['fullname']); ?></h2>
        <div class="subtitle">NIS Residence Card Operations Dashboard</div>
    </div>
    <div class="dashboard-datetime-box">
        <div class="datetime-item">
            <i class="far fa-calendar-alt" style="color: var(--nis-gold-dark);"></i>
            <span id="currentDateDisplay"><?php echo date('l, d F Y'); ?></span>
        </div>
        <div class="datetime-divider">|</div>
        <div class="datetime-item">
            <i class="far fa-clock" style="color: var(--nis-green);"></i>
            <span id="currentTimeDisplay" style="font-family: monospace; font-size: 0.92rem; font-weight: 700;"><?php echo date('h:i:s A'); ?></span>
        </div>
    </div>
</div>

<!-- Stat Cards Grid -->
<div class="stat-grid">
    <div class="stat-card">
        <div class="stat-info">
            <div class="stat-label"><?php echo $isSuperAdmin ? 'Total Registered' : 'My Registered Cards'; ?></div>
            <div class="stat-value"><?php echo number_format($totalCards); ?></div>
            <div class="stat-meta"><i class="fas fa-database"></i> <?php echo $isSuperAdmin ? 'All Recorded Files' : 'Files Processed by You'; ?></div>
        </div>
        <div class="stat-icon-wrapper">
            <i class="fas fa-id-card"></i>
        </div>
    </div>

    <div class="stat-card gold">
        <div class="stat-info">
            <div class="stat-label"><?php echo $isSuperAdmin ? 'Active &amp; Issued' : 'My Active / Issued'; ?></div>
            <div class="stat-value"><?php echo number_format($activeCards); ?></div>
            <div class="stat-meta"><i class="fas fa-check-circle"></i> <?php echo $isSuperAdmin ? 'Valid Resident Aliens' : 'Your Valid Issuances'; ?></div>
        </div>
        <div class="stat-icon-wrapper">
            <i class="fas fa-user-check"></i>
        </div>
    </div>

    <div class="stat-card warning">
        <div class="stat-info">
            <div class="stat-label">Pending Approval</div>
            <div class="stat-value"><?php echo number_format($pendingCards); ?></div>
            <div class="stat-meta"><i class="fas fa-clock"></i> <?php 
                if ($isSuperAdmin) {
                    echo 'Awaiting Sign-off (All)';
                } elseif ($userRole === ROLE_APPROVING_OFFICER) {
                    echo 'Awaiting Your Sign-off';
                } else {
                    echo 'Your Pending Submissions';
                }
            ?></div>
        </div>
        <div class="stat-icon-wrapper">
            <i class="fas fa-hourglass-half"></i>
        </div>
    </div>

    <div class="stat-card navy">
        <div class="stat-info">
            <div class="stat-label"><?php echo $isSuperAdmin ? 'Renewals Granted' : 'My Renewals Granted'; ?></div>
            <div class="stat-value"><?php echo number_format($totalRenewals); ?></div>
            <div class="stat-meta"><i class="fas fa-history"></i> <?php echo $isSuperAdmin ? 'Card Extensions' : 'Endorsed by You'; ?></div>
        </div>
        <div class="stat-icon-wrapper">
            <i class="fas fa-sync-alt"></i>
        </div>
    </div>
</div>

<!-- Charts Row: Top Nationalities & Lifecycle Status -->
<div class="charts-grid">
    <!-- Chart 1: Top Nationalities Distribution -->
    <div class="app-card" style="margin-bottom: 0;">
        <div class="card-header">
            <h3><i class="fas fa-chart-pie"></i> <?php echo $isSuperAdmin ? 'Top Nationalities Distribution' : 'Top Nationalities (Your Files)'; ?></h3>
            <span class="badge" style="background: rgba(26, 92, 46, 0.1); color: var(--nis-green); font-size: 0.75rem;">
                <?php echo $isSuperAdmin ? 'Resident Aliens (System-Wide)' : 'Your Processed Files'; ?>
            </span>
        </div>
        <div class="card-body">
            <?php if (empty($topNationalities)): ?>
                <p style="color: #94a3b8; font-size: 0.85rem; text-align: center; padding: 2rem 0;">
                    <i class="fas fa-chart-pie" style="font-size: 2rem; display: block; margin-bottom: 8px; color: #cbd5e1;"></i>
                    No nationality records found.
                </p>
            <?php else: ?>
                <div style="position: relative; height: 200px; width: 100%; margin-bottom: 1rem;">
                    <canvas id="topNationalitiesChart"></canvas>
                </div>

                <!-- Nationalities Breakdown Legend -->
                <div class="nat-chart-legend">
                    <?php 
                    $totalNatCount = array_sum(array_column($topNationalities, 'count'));
                    $palette = ['#1a5c2e', '#d4af37', '#113f1f', '#27ae60', '#b38f20', '#082010', '#166534'];
                    foreach ($topNationalities as $idx => $nat): 
                        $color = $palette[$idx % count($palette)];
                        $pct = $totalNatCount > 0 ? round(($nat['count'] / $totalNatCount) * 100, 1) : 0;
                    ?>
                        <div class="nat-legend-item">
                            <div class="nat-legend-left">
                                <span class="nat-color-dot" style="background-color: <?php echo $color; ?>;"></span>
                                <span class="nat-name"><?php echo h($nat['nationality']); ?></span>
                            </div>
                            <div class="nat-legend-right">
                                <strong class="nat-count"><?php echo (int)$nat['count']; ?></strong>
                                <span class="nat-pct">(<?php echo $pct; ?>%)</span>
                            </div>
                        </div>
                    <?php endforeach; ?>
                </div>
            <?php endif; ?>
        </div>
    </div>

    <!-- Chart 2: Card Issuance -->
    <div class="app-card" style="margin-bottom: 0;">
        <div class="card-header">
            <h3><i class="fas fa-chart-bar"></i> <?php echo $isSuperAdmin ? 'System Card Issuance Breakdown' : 'Your Card Issuance Breakdown'; ?></h3>
        </div>
        <div class="card-body">
            <div style="position: relative; height: 200px; width: 100%; margin-bottom: 1rem;">
                <canvas id="lifecycleStatusChart"></canvas>
            </div>

            <!-- Status Breakdown Metric Pills -->
            <div class="nat-chart-legend">
                <div class="nat-legend-item">
                    <div class="nat-legend-left">
                        <span class="nat-color-dot" style="background-color: var(--nis-green);"></span>
                        <span class="nat-name">Active &amp; Issued</span>
                    </div>
                    <div class="nat-legend-right">
                        <strong class="nat-count"><?php echo $statusCounts['ISSUED']; ?></strong>
                        <span class="nat-pct">(<?php echo $totalTracked > 0 ? round(($statusCounts['ISSUED'] / $totalTracked) * 100, 1) : 0; ?>%)</span>
                    </div>
                </div>
                <div class="nat-legend-item">
                    <div class="nat-legend-left">
                        <span class="nat-color-dot" style="background-color: var(--nis-navy);"></span>
                        <span class="nat-name">Renewed</span>
                    </div>
                    <div class="nat-legend-right">
                        <strong class="nat-count"><?php echo $statusCounts['RENEWED']; ?></strong>
                        <span class="nat-pct">(<?php echo $totalTracked > 0 ? round(($statusCounts['RENEWED'] / $totalTracked) * 100, 1) : 0; ?>%)</span>
                    </div>
                </div>
                <div class="nat-legend-item">
                    <div class="nat-legend-left">
                        <span class="nat-color-dot" style="background-color: var(--nis-warning);"></span>
                        <span class="nat-name">Pending</span>
                    </div>
                    <div class="nat-legend-right">
                        <strong class="nat-count"><?php echo $statusCounts['PENDING_APPROVAL']; ?></strong>
                        <span class="nat-pct">(<?php echo $totalTracked > 0 ? round(($statusCounts['PENDING_APPROVAL'] / $totalTracked) * 100, 1) : 0; ?>%)</span>
                    </div>
                </div>
                <div class="nat-legend-item">
                    <div class="nat-legend-left">
                        <span class="nat-color-dot" style="background-color: var(--nis-danger);"></span>
                        <span class="nat-name">Expired / Revoked</span>
                    </div>
                    <div class="nat-legend-right">
                        <strong class="nat-count"><?php echo $statusCounts['EXPIRED_REVOKED']; ?></strong>
                        <span class="nat-pct">(<?php echo $totalTracked > 0 ? round(($statusCounts['EXPIRED_REVOKED'] / $totalTracked) * 100, 1) : 0; ?>%)</span>
                    </div>
                </div>
            </div>
        </div>
    </div>
</div>

<!-- Full-Width Recent Issuances Section -->
<div class="app-card">
    <div class="card-header">
        <h3><i class="fas fa-list-alt"></i> <?php echo $isSuperAdmin ? 'Recent Residence Card Issuances' : 'Recent Residence Cards Processed by You'; ?></h3>
        <a href="view-cards<?php echo $isSuperAdmin ? '' : '?scope=my'; ?>" class="btn btn-outline btn-sm">
            <?php echo $isSuperAdmin ? 'View All Directory' : 'View Your Directory'; ?>
        </a>
    </div>
    <div class="card-body" style="padding: 0;">
        <div class="table-responsive">
            <table class="nis-table">
                <thead>
                    <tr>
                        <th style="width: 50px; text-align: center;">S/N</th>
                        <th>Card No.</th>
                        <th>Applicant Full Name</th>
                        <th>Nationality</th>
                        <th>Issue Date</th>
                        <th>Status</th>
                        <th>Actions</th>
                    </tr>
                </thead>
                <tbody>
                    <?php if (empty($recentCards)): ?>
                        <tr>
                            <td colspan="7" style="text-align: center; padding: 2rem; color: #94a3b8;">
                                No residence card records found.
                            </td>
                        </tr>
                    <?php else: ?>
                        <?php $idx = 0; foreach ($recentCards as $card): ?>
                            <tr>
                                <td style="text-align: center; color: var(--text-muted); font-size: 0.82rem;">
                                    <?php echo ($recentOffset + $idx + 1); $idx++; ?>
                                </td>
                                <td>
                                    <strong style="color: var(--nis-green-dark); font-family: monospace; font-size: 0.95rem;">
                                        No. <?php echo h($card['card_number']); ?>
                                    </strong>
                                    <div style="font-size: 0.72rem; color: #64748b;"><?php echo h($card['booklet_number']); ?></div>
                                </td>
                                <td>
                                    <strong><?php echo h($card['surname'] . ', ' . $card['forenames']); ?></strong>
                                    <div style="font-size: 0.75rem; color: #64748b;">DOB: <?php echo h($card['date_of_birth']); ?></div>
                                </td>
                                <td>
                                    <span class="badge" style="background: #edf2f7; color: #2d3748;">
                                        <?php echo h($card['nationality']); ?>
                                    </span>
                                </td>
                                <td><?php echo h($card['issued_on']); ?></td>
                                <td>
                                    <?php 
                                    $st = strtoupper($card['status']);
                                    $statusColor = '#166534';
                                    if ($st === 'PENDING_APPROVAL') $statusColor = '#b45309';
                                    elseif ($st === 'RENEWED') $statusColor = '#1d4ed8';
                                    elseif ($st === 'EXPIRED' || $st === 'REVOKED') $statusColor = '#b91c1c';
                                    elseif ($st === 'QUERIED') $statusColor = '#c2410c';
                                    ?>
                                    <span style="color: <?php echo $statusColor; ?>; font-weight: 600; font-size: 0.85rem;">
                                        <?php echo h(ucwords(str_replace('_', ' ', strtolower($card['status'])))); ?>
                                    </span>
                                </td>
                                <td>
                                    <div style="display: flex; gap: 6px;">
                                        <a href="card-details?id=<?php echo (int)$card['id']; ?>" class="btn btn-outline btn-sm" title="Inspect Record">
                                            View
                                        </a>
                                        <a href="print-idcard?id=<?php echo (int)$card['id']; ?>" class="btn btn-primary btn-sm" title="Print PVC Card">
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

        <?php if ($totalRecentCards > 0): ?>
            <div style="display: flex; justify-content: space-between; align-items: center; padding: 0.85rem 1.25rem; border-top: 1px solid var(--nis-border); background: var(--surface-alt); font-size: 0.82rem; flex-wrap: wrap; gap: 10px;">
                <div style="color: var(--text-muted);">
                    Showing <?php echo min($totalRecentCards, $recentOffset + 1); ?> to <?php echo min($totalRecentCards, $recentOffset + count($recentCards)); ?> of <?php echo $totalRecentCards; ?> issuances
                </div>
                <div style="display: flex; gap: 6px; align-items: center;">
                    <?php if ($recentPage > 1): ?>
                        <a href="dashboard?recent_page=<?php echo $recentPage - 1; ?>" class="btn btn-sm btn-outline">
                            <i class="fas fa-chevron-left"></i> Previous
                        </a>
                    <?php else: ?>
                        <button type="button" class="btn btn-sm btn-outline" disabled style="opacity: 0.5; cursor: not-allowed;">
                            <i class="fas fa-chevron-left"></i> Previous
                        </button>
                    <?php endif; ?>

                    <span style="font-weight: 600; padding: 0 8px; color: var(--text-color);">
                        Page <?php echo $recentPage; ?> of <?php echo $totalRecentPages; ?>
                    </span>

                    <?php if ($recentPage < $totalRecentPages): ?>
                        <a href="dashboard?recent_page=<?php echo $recentPage + 1; ?>" class="btn btn-sm btn-outline">
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
    // Real-Time Live Clock Updater
    function updateDashboardClock() {
        const now = new Date();
        const dateOptions = { weekday: 'long', day: 'numeric', month: 'long', year: 'numeric' };
        const dateEl = document.getElementById('currentDateDisplay');
        const timeEl = document.getElementById('currentTimeDisplay');
        if (dateEl) dateEl.textContent = now.toLocaleDateString('en-GB', dateOptions);
        if (timeEl) timeEl.textContent = now.toLocaleTimeString('en-US', { hour: '2-digit', minute: '2-digit', second: '2-digit', hour12: true });
    }
    setInterval(updateDashboardClock, 1000);

    // Initialize Charts
    document.addEventListener('DOMContentLoaded', function() {
        // 1. Top Nationalities Donut Chart
        const natCanvas = document.getElementById('topNationalitiesChart');
        if (natCanvas && typeof Chart !== 'undefined') {
            const labels = <?php echo json_encode(array_column($topNationalities, 'nationality')); ?>;
            const data = <?php echo json_encode(array_map('intval', array_column($topNationalities, 'count'))); ?>;
            const palette = ['#1a5c2e', '#d4af37', '#113f1f', '#27ae60', '#b38f20', '#082010', '#166534'];

            new Chart(natCanvas, {
                type: 'doughnut',
                data: {
                    labels: labels,
                    datasets: [{
                        data: data,
                        backgroundColor: palette.slice(0, labels.length),
                        borderWidth: 2,
                        borderColor: '#ffffff',
                        hoverOffset: 6
                    }]
                },
                options: {
                    responsive: true,
                    maintainAspectRatio: false,
                    plugins: {
                        legend: { display: false },
                        tooltip: {
                            callbacks: {
                                label: function(context) {
                                    const total = context.dataset.data.reduce((a, b) => a + b, 0);
                                    const val = context.parsed;
                                    const pct = total > 0 ? ((val / total) * 100).toFixed(1) : 0;
                                    return ' ' + context.label + ': ' + val + ' (' + pct + '%)';
                                }
                            }
                        }
                    },
                    cutout: '68%'
                }
            });
        }

        // 2. Lifecycle Status Bar Chart
        const statusCanvas = document.getElementById('lifecycleStatusChart');
        if (statusCanvas && typeof Chart !== 'undefined') {
            new Chart(statusCanvas, {
                type: 'bar',
                data: {
                    labels: ['Active Issued', 'Renewed', 'Pending', 'Expired / Revoked'],
                    datasets: [{
                        label: 'Residence Cards',
                        data: [
                            <?php echo (int)$statusCounts['ISSUED']; ?>,
                            <?php echo (int)$statusCounts['RENEWED']; ?>,
                            <?php echo (int)$statusCounts['PENDING_APPROVAL']; ?>,
                            <?php echo (int)$statusCounts['EXPIRED_REVOKED']; ?>
                        ],
                        backgroundColor: ['#1a5c2e', '#27ae60', '#d4af37', '#dc2626'],
                        borderRadius: 6,
                        maxBarThickness: 42
                    }]
                },
                options: {
                    responsive: true,
                    maintainAspectRatio: false,
                    plugins: {
                        legend: { display: false },
                        tooltip: {
                            callbacks: {
                                label: function(context) {
                                    return ' ' + context.parsed.y + ' Card(s)';
                                }
                            }
                        }
                    },
                    scales: {
                        y: {
                            beginAtZero: true,
                            ticks: { precision: 0, stepSize: 1 },
                            grid: { color: '#f1f5f9' }
                        },
                        x: {
                            grid: { display: false }
                        }
                    }
                }
            });
        }
    });
</script>

<?php
require_once __DIR__ . '/includes/footer.php';
?>
