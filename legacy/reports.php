<?php
/**
 * NIS Residence Card Issuance System (NIS-RCIS)
 * Reports & Immigration Analytics Portal
 */

require_once __DIR__ . '/includes/auth.php';
require_once __DIR__ . '/config/database.php';
require_once __DIR__ . '/config/constants.php';

$pageTitle = 'Reports & Analytics';
$db = Database::getConnection();

// Date Range Filter parameters (Default: start of current year to today)
$dateFrom = !empty($_GET['date_from']) ? trim($_GET['date_from']) : date('Y-01-01');
$dateTo = !empty($_GET['date_to']) ? trim($_GET['date_to']) : date('Y-m-d');
$user = currentUser();

// User & RBAC Context
$isSuperAdmin = ($user['role'] === ROLE_SUPER_ADMIN);
$userId = (int)$user['id'];
$serviceNo = trim((string)$user['service_number']);
$userRole = $user['role'];

if ($isSuperAdmin) {
    $cardScopeWhere = "";
    $scopeParams = [];
    $renScopeWhere = "";
    $renScopeParams = [];
} else {
    if ($userRole === ROLE_APPROVING_OFFICER) {
        $cardScopeWhere = " AND (created_by = :uid_c OR issuing_officer_service_no = :sn OR approved_by = :uid_a)";
        $scopeParams = [':uid_c' => $userId, ':sn' => $serviceNo, ':uid_a' => $userId];
    } elseif ($userRole === ROLE_ISSUING_OFFICER) {
        $cardScopeWhere = " AND (created_by = :uid_c OR issuing_officer_service_no = :sn)";
        $scopeParams = [':uid_c' => $userId, ':sn' => $serviceNo];
    } else {
        $cardScopeWhere = " AND (created_by = :uid_c OR issuing_officer_service_no = :sn OR watchlisted_by = :uid_w OR revoked_by = :uid_r)";
        $scopeParams = [':uid_c' => $userId, ':sn' => $serviceNo, ':uid_w' => $userId, ':uid_r' => $userId];
    }
    $renScopeWhere = " AND (officer_service_no = :sn_ren OR card_id IN (SELECT id FROM residence_cards WHERE 1=1 {$cardScopeWhere}))";
    $renScopeParams = array_merge([':sn_ren' => $serviceNo], $scopeParams);
}

// 1. Total Enrolled Cards in date range
$totalStmt = $db->prepare("SELECT COUNT(*) FROM residence_cards WHERE issued_on BETWEEN :df AND :dt {$cardScopeWhere}");
$totalStmt->execute(array_merge([':df' => $dateFrom, ':dt' => $dateTo], $scopeParams));
$countTotal = (int)$totalStmt->fetchColumn();

// 2. Active & Compliant Cards
$activeStmt = $db->prepare("SELECT COUNT(*) FROM residence_cards WHERE issued_on BETWEEN :df AND :dt AND status IN ('ISSUED', 'RENEWED') {$cardScopeWhere}");
$activeStmt->execute(array_merge([':df' => $dateFrom, ':dt' => $dateTo], $scopeParams));
$countActive = (int)$activeStmt->fetchColumn();
$activeRate = $countTotal > 0 ? round(($countActive / $countTotal) * 100, 1) : 0;

// 3. Renewals Granted in date range
$renewalStmt = $db->prepare("SELECT COUNT(*) FROM card_renewals WHERE SUBSTR(created_at, 1, 10) BETWEEN :df AND :dt {$renScopeWhere}");
$renewalStmt->execute(array_merge([':df' => $dateFrom, ':dt' => $dateTo], $renScopeParams));
$countRenewals = (int)$renewalStmt->fetchColumn();

// 4. Distinct Nationalities Enrolled
$distinctNatStmt = $db->prepare("SELECT COUNT(DISTINCT nationality) FROM residence_cards WHERE issued_on BETWEEN :df AND :dt {$cardScopeWhere}");
$distinctNatStmt->execute(array_merge([':df' => $dateFrom, ':dt' => $dateTo], $scopeParams));
$countNationalities = (int)$distinctNatStmt->fetchColumn();

// 5. Statistics by Nationality
$natStmt = $db->prepare("SELECT nationality, COUNT(*) as total FROM residence_cards 
                         WHERE issued_on BETWEEN :df AND :dt {$cardScopeWhere} GROUP BY nationality ORDER BY total DESC");
$natStmt->execute(array_merge([':df' => $dateFrom, ':dt' => $dateTo], $scopeParams));
$nationalityStats = $natStmt->fetchAll();

// 6. Monthly Registration Velocity
$velStmt = $db->prepare("SELECT SUBSTR(issued_on, 1, 7) as ym, COUNT(*) as total 
                         FROM residence_cards 
                         WHERE issued_on BETWEEN :df AND :dt {$cardScopeWhere} 
                         GROUP BY ym ORDER BY ym ASC");
$velStmt->execute(array_merge([':df' => $dateFrom, ':dt' => $dateTo], $scopeParams));
$velocityStats = $velStmt->fetchAll();

// 7. Demographic Gender Profile
$genderStmt = $db->prepare("SELECT 
                                CASE 
                                    WHEN UPPER(sex) IN ('M', 'MALE') THEN 'Male'
                                    WHEN UPPER(sex) IN ('F', 'FEMALE') THEN 'Female'
                                    ELSE 'Other'
                                END as gender, 
                                COUNT(*) as total 
                            FROM residence_cards 
                            WHERE issued_on BETWEEN :df AND :dt {$cardScopeWhere} 
                            GROUP BY gender ORDER BY total DESC");
$genderStmt->execute(array_merge([':df' => $dateFrom, ':dt' => $dateTo], $scopeParams));
$genderStats = $genderStmt->fetchAll();

// 8. Top Expatriate Professions
$profStmt = $db->prepare("SELECT profession, COUNT(*) as total 
                         FROM residence_cards 
                         WHERE issued_on BETWEEN :df AND :dt AND profession IS NOT NULL AND profession != '' {$cardScopeWhere} 
                         GROUP BY profession ORDER BY total DESC LIMIT 8");
$profStmt->execute(array_merge([':df' => $dateFrom, ':dt' => $dateTo], $scopeParams));
$professionStats = $profStmt->fetchAll();

// 9. Comprehensive Registry Dossier (Full list for the period)
$dossierStmt = $db->prepare("SELECT * FROM residence_cards 
                             WHERE issued_on BETWEEN :df AND :dt {$cardScopeWhere} 
                             ORDER BY id DESC");
$dossierStmt->execute(array_merge([':df' => $dateFrom, ':dt' => $dateTo], $scopeParams));
$dossierRecords = $dossierStmt->fetchAll();

// Palette color helper
if (!function_exists('getNisPaletteColor')) {
    function getNisPaletteColor($index) {
        $palette = ['#1a5c2e', '#d4af37', '#113f1f', '#27ae60', '#b38f20', '#082010', '#166534', '#047857', '#d97706', '#1e293b'];
        return $palette[$index % count($palette)];
    }
}

// Load Chart.js
$extraScripts = ['assets/js/chart.min.js'];

require_once __DIR__ . '/includes/header.php';
require_once __DIR__ . '/includes/sidebar.php';
?>


<!-- Print-Only Official NIS Header -->
<div class="print-only-header" style="display: none;">
    <div style="text-align: center; margin-bottom: 20px; border-bottom: 2px solid #000; padding-bottom: 15px;">
        <img src="assets/images/nis-crest-logo.png" alt="NIS Crest" style="height: 65px; margin-bottom: 8px;">
        <h2 style="margin: 0; font-size: 1.35rem; text-transform: uppercase; letter-spacing: 0.5px; color: #111827;">Federal Republic of Nigeria &bull; Nigeria Immigration Service</h2>
        <h3 style="margin: 4px 0; font-size: 1.12rem; color: #1a5c2e; font-weight: 700;">Residence Card Issuance Directorate (NIS-RCIS)</h3>
        <p style="margin: 4px 0; font-size: 0.9rem; color: #475569;">
            <?php echo $isSuperAdmin ? 'Official National Registry Intelligence &amp; Residency Analytics Report' : 'Official Officer Activity Intelligence &amp; Residency Report (Personal Dossier)'; ?>
        </p>
        <div style="font-size: 0.82rem; color: #64748b; margin-top: 6px;">
            Reporting Period: <strong><?php echo h($dateFrom); ?></strong> to <strong><?php echo h($dateTo); ?></strong> &bull; Generated on: <strong><?php echo date('d M Y, h:i A'); ?></strong> by <strong><?php echo h($user['fullname']); ?> (NIS No: <?php echo h($user['service_number']); ?>)</strong>
        </div>
    </div>
</div>

<div class="page-title-box no-print">
    <div>
        <h2><i class="fas fa-chart-line" style="color: var(--nis-green);"></i> Reports &amp; Analytics</h2>
        <div class="subtitle">Comprehensive national residency data, demographic profiles, and regulatory compliance intelligence</div>
    </div>
    <div style="display: flex; gap: 10px; align-items: center; flex-wrap: wrap;">
        <button type="button" onclick="window.print()" class="btn btn-outline">
            <i class="fas fa-print"></i> Print Report
        </button>
        <a href="export?type=reports&date_from=<?php echo urlencode($dateFrom); ?>&date_to=<?php echo urlencode($dateTo); ?>" class="btn btn-primary">
            <i class="fas fa-file-csv"></i> Download CSV Export
        </a>
    </div>
</div>

<!-- Key Performance Indicators (KPI) Ribbon -->
<div class="kpi-grid" style="display: grid; grid-template-columns: repeat(auto-fit, minmax(220px, 1fr)); gap: 1.25rem; margin-bottom: 1.75rem;">
    <!-- Stat 1: Total Enrolled -->
    <div class="stat-card" style="border-radius: 8px; padding: 1.25rem; background: var(--surface-color);">
        <div style="display: flex; justify-content: space-between; align-items: flex-start;">
            <div>
                <div style="font-size: 0.82rem; color: var(--text-muted); font-weight: 600; text-transform: uppercase; letter-spacing: 0.5px;">
                    <?php echo $isSuperAdmin ? 'Total Enrolled Cards' : 'My Enrolled / Processed Cards'; ?>
                </div>
                <div style="font-size: 1.85rem; font-weight: 700; color: var(--text-color); margin-top: 4px;"><?php echo number_format($countTotal); ?></div>
                <div style="font-size: 0.78rem; color: var(--text-muted); margin-top: 4px;">
                    <?php echo $isSuperAdmin ? 'Cards issued in selected window' : 'Processed by you in selected window'; ?>
                </div>
            </div>
            <div style="width: 44px; height: 44px; border-radius: 8px; background: rgba(26, 92, 46, 0.1); color: var(--nis-green); display: flex; align-items: center; justify-content: center; font-size: 1.25rem;">
                <i class="fas fa-id-card"></i>
            </div>
        </div>
    </div>

    <!-- Stat 2: Active & Compliant -->
    <div class="stat-card" style="border-radius: 8px; padding: 1.25rem; background: var(--surface-color);">
        <div style="display: flex; justify-content: space-between; align-items: flex-start;">
            <div>
                <div style="font-size: 0.82rem; color: var(--text-muted); font-weight: 600; text-transform: uppercase; letter-spacing: 0.5px;">Active &amp; Compliant</div>
                <div style="font-size: 1.85rem; font-weight: 700; color: var(--nis-green); margin-top: 4px;"><?php echo number_format($countActive); ?></div>
                <div style="font-size: 0.78rem; color: var(--nis-green); font-weight: 600; margin-top: 4px;"><?php echo $activeRate; ?>% active compliance rate</div>
            </div>
            <div style="width: 44px; height: 44px; border-radius: 8px; background: rgba(39, 174, 96, 0.1); color: #27ae60; display: flex; align-items: center; justify-content: center; font-size: 1.25rem;">
                <i class="fas fa-check-circle"></i>
            </div>
        </div>
    </div>

    <!-- Stat 3: Renewals Processed -->
    <div class="stat-card" style="border-radius: 8px; padding: 1.25rem; background: var(--surface-color);">
        <div style="display: flex; justify-content: space-between; align-items: flex-start;">
            <div>
                <div style="font-size: 0.82rem; color: var(--text-muted); font-weight: 600; text-transform: uppercase; letter-spacing: 0.5px;">
                    <?php echo $isSuperAdmin ? 'Renewals Processed' : 'My Renewals Processed'; ?>
                </div>
                <div style="font-size: 1.85rem; font-weight: 700; color: var(--nis-navy); margin-top: 4px;"><?php echo number_format($countRenewals); ?></div>
                <div style="font-size: 0.78rem; color: var(--text-muted); margin-top: 4px;">
                    <?php echo $isSuperAdmin ? 'Renewals granted' : 'Renewals endorsed by you'; ?>
                </div>
            </div>
            <div style="width: 44px; height: 44px; border-radius: 8px; background: rgba(26, 82, 118, 0.1); color: var(--nis-navy); display: flex; align-items: center; justify-content: center; font-size: 1.25rem;">
                <i class="fas fa-history"></i>
            </div>
        </div>
    </div>

    <!-- Stat 4: Distinct Foreign Nationalities -->
    <div class="stat-card" style="border-radius: 8px; padding: 1.25rem; background: var(--surface-color);">
        <div style="display: flex; justify-content: space-between; align-items: flex-start;">
            <div>
                <div style="font-size: 0.82rem; color: var(--text-muted); font-weight: 600; text-transform: uppercase; letter-spacing: 0.5px;">Foreign Nationalities</div>
                <div style="font-size: 1.85rem; font-weight: 700; color: var(--nis-gold-dark); margin-top: 4px;"><?php echo number_format($countNationalities); ?></div>
                <div style="font-size: 0.78rem; color: var(--text-muted); margin-top: 4px;">Distinct countries of origin</div>
            </div>
            <div style="width: 44px; height: 44px; border-radius: 8px; background: rgba(200, 148, 26, 0.1); color: var(--nis-gold-dark); display: flex; align-items: center; justify-content: center; font-size: 1.25rem;">
                <i class="fas fa-globe-americas"></i>
            </div>
        </div>
    </div>
</div>

<!-- Date Range & Operational Filter (Placed Below Stat Cards) -->
<div class="app-card filter-card no-print" style="margin-bottom: 2rem;">
    <div class="card-body" style="padding: 1.25rem;">
        <form method="GET" action="reports" style="display: flex; gap: 16px; align-items: flex-end; flex-wrap: wrap;">
            <div class="form-group" style="margin-bottom: 0;">
                <label for="date_from" style="font-weight: 600; font-size: 0.85rem; color: var(--text-color); margin-bottom: 4px; display: block;">
                    <i class="far fa-calendar-alt" style="color: var(--nis-green);"></i> Issuance Date From
                </label>
                <input type="date" id="date_from" name="date_from" class="form-control" value="<?php echo h($dateFrom); ?>" style="height: 38px;">
            </div>

            <div class="form-group" style="margin-bottom: 0;">
                <label for="date_to" style="font-weight: 600; font-size: 0.85rem; color: var(--text-color); margin-bottom: 4px; display: block;">
                    <i class="far fa-calendar-alt" style="color: var(--nis-green);"></i> Issuance Date To
                </label>
                <input type="date" id="date_to" name="date_to" class="form-control" value="<?php echo h($dateTo); ?>" style="height: 38px;">
            </div>

            <div style="display: flex; gap: 8px; align-items: center;">
                <button type="submit" class="btn btn-primary" style="height: 38px; display: inline-flex; align-items: center; gap: 6px;">
                    <i class="fas fa-filter"></i> Apply Period
                </button>
                <a href="reports?date_from=<?php echo date('Y-01-01'); ?>&date_to=<?php echo date('Y-m-d'); ?>" class="btn btn-outline" style="height: 38px; display: inline-flex; align-items: center;">Year to Date</a>
                <a href="reports?date_from=2020-01-01&date_to=<?php echo date('Y-m-d'); ?>" class="btn btn-outline" style="height: 38px; display: inline-flex; align-items: center;">All Records</a>
                <a href="reports" class="btn btn-outline" style="height: 38px; display: inline-flex; align-items: center;">Reset</a>
            </div>
        </form>
    </div>
</div>

<!-- SECTION 1: Residency by Nationality (Represented with Chart & Breakdown) -->
<div class="app-card" style="margin-bottom: 2rem;">
    <div class="card-header" style="display: flex; justify-content: space-between; align-items: center; padding: 1rem 1.25rem; border-bottom: 1px solid var(--nis-border);">
        <div>
            <h3 style="margin: 0; font-size: 1.08rem; font-weight: 700;">
                <i class="fas fa-globe" style="color: var(--nis-green); margin-right: 8px;"></i> Residency by Nationality
            </h3>
            <div style="font-size: 0.8rem; color: var(--text-muted); margin-top: 2px;">
                Distribution and ranking of registered foreign residents by country of origin
            </div>
        </div>
        <span class="badge badge-info" style="font-size: 0.8rem; font-weight: 600;">
            <?php echo number_format($countNationalities); ?> <?php echo $countNationalities === 1 ? 'Country' : 'Countries'; ?>
        </span>
    </div>
    <div class="card-body" style="padding: 1.5rem;">
        <?php if (empty($nationalityStats)): ?>
            <div style="text-align: center; padding: 2.5rem; color: #94a3b8;">
                <i class="fas fa-globe-americas" style="font-size: 2.5rem; margin-bottom: 10px; display: block; opacity: 0.4;"></i>
                No nationality records found for the selected reporting period.
            </div>
        <?php else: ?>
            <div class="nationality-layout">
                <!-- Nationality Donut Chart -->
                <div style="display: flex; flex-direction: column; align-items: center;">
                    <div style="position: relative; width: 100%; max-width: 320px; height: 260px;">
                        <canvas id="nationalityChart"></canvas>
                    </div>
                    <div style="font-size: 0.8rem; color: var(--text-muted); text-align: center; margin-top: 10px;">
                        Relative share of expatriates holding valid residence documentation
                    </div>
                </div>

                <!-- Nationality Ranked Distribution Table -->
                <div style="max-height: 280px; overflow-y: auto; border: 1px solid var(--nis-border); border-radius: 6px;">
                    <table class="nis-table" style="margin: 0; width: 100%;">
                        <thead>
                            <tr>
                                <th>Country of Origin</th>
                                <th style="text-align: right;">Cards</th>
                                <th style="width: 40%; text-align: right;">Distribution</th>
                            </tr>
                        </thead>
                        <tbody>
                            <?php foreach ($nationalityStats as $idx => $ns): ?>
                                <?php $pct = $countTotal > 0 ? round(($ns['total'] / $countTotal) * 100, 1) : 0; ?>
                                <tr>
                                    <td>
                                        <div style="display: flex; align-items: center; gap: 8px;">
                                            <span style="display: inline-block; width: 10px; height: 10px; border-radius: 50%; background-color: <?php echo getNisPaletteColor($idx); ?>;"></span>
                                            <strong><?php echo h($ns['nationality']); ?></strong>
                                        </div>
                                    </td>
                                    <td style="text-align: right; font-weight: 700;"><?php echo number_format($ns['total']); ?></td>
                                    <td style="text-align: right;">
                                        <div style="display: flex; align-items: center; justify-content: flex-end; gap: 8px;">
                                            <div style="flex: 1; height: 6px; background: #e2e8f0; border-radius: 3px; overflow: hidden;">
                                                <div style="height: 100%; width: <?php echo min(100, $pct); ?>%; background: <?php echo getNisPaletteColor($idx); ?>; border-radius: 3px;"></div>
                                            </div>
                                            <span style="font-size: 0.8rem; font-weight: 600; min-width: 42px;"><?php echo $pct; ?>%</span>
                                        </div>
                                    </td>
                                </tr>
                            <?php endforeach; ?>
                        </tbody>
                    </table>
                </div>
            </div>
        <?php endif; ?>
    </div>
</div>

<!-- SECTION 2: Insightful Analytics Grid (Registration Velocity & Demographic Gender Ratio) -->
<div class="charts-grid-2">
    <!-- Chart A: Monthly Registration Velocity -->
    <div class="app-card" style="margin-bottom: 0;">
        <div class="card-header" style="display: flex; justify-content: space-between; align-items: center; padding: 1rem 1.25rem; border-bottom: 1px solid var(--nis-border);">
            <div>
                <h3 style="margin: 0; font-size: 1.05rem; font-weight: 700;">
                    <i class="fas fa-chart-bar" style="color: var(--nis-navy); margin-right: 8px;"></i> Issuance &amp; Registration Velocity
                </h3>
                <div style="font-size: 0.8rem; color: var(--text-muted); margin-top: 2px;">Monthly volume of residence cards issued across registry commands</div>
            </div>
            <span class="badge badge-primary" style="font-size: 0.78rem;">Monthly Trajectory</span>
        </div>
        <div class="card-body" style="padding: 1.25rem;">
            <?php if (empty($velocityStats)): ?>
                <div style="text-align: center; padding: 2.5rem; color: #94a3b8;">
                    <i class="fas fa-chart-line" style="font-size: 2.2rem; margin-bottom: 10px; display: block; opacity: 0.4;"></i>
                    No monthly issuance records available for this window.
                </div>
            <?php else: ?>
                <div style="position: relative; height: 250px;">
                    <canvas id="velocityChart"></canvas>
                </div>
            <?php endif; ?>
        </div>
    </div>

    <!-- Chart B: Demographic Profile (Gender Distribution) -->
    <div class="app-card" style="margin-bottom: 0;">
        <div class="card-header" style="display: flex; justify-content: space-between; align-items: center; padding: 1rem 1.25rem; border-bottom: 1px solid var(--nis-border);">
            <div>
                <h3 style="margin: 0; font-size: 1.05rem; font-weight: 700;">
                    <i class="fas fa-venus-mars" style="color: var(--nis-gold-dark); margin-right: 8px;"></i> Demographic Gender Profile
                </h3>
                <div style="font-size: 0.8rem; color: var(--text-muted); margin-top: 2px;">Expatriate gender distribution of active and enrolled holders</div>
            </div>
            <span class="badge badge-warning" style="font-size: 0.78rem;">Demographics</span>
        </div>
        <div class="card-body" style="padding: 1.25rem;">
            <?php if (empty($genderStats)): ?>
                <div style="text-align: center; padding: 2.5rem; color: #94a3b8;">
                    <i class="fas fa-users" style="font-size: 2.2rem; margin-bottom: 10px; display: block; opacity: 0.4;"></i>
                    No gender profile data available.
                </div>
            <?php else: ?>
                <div style="display: flex; align-items: center; justify-content: center; height: 250px;">
                    <div style="position: relative; width: 100%; max-width: 320px; height: 230px;">
                        <canvas id="genderChart"></canvas>
                    </div>
                </div>
            <?php endif; ?>
        </div>
    </div>
</div>

<!-- SECTION 3: Expatriate Occupational Distribution -->
<div class="app-card" style="margin-bottom: 2rem;">
    <div class="card-header" style="display: flex; justify-content: space-between; align-items: center; padding: 1rem 1.25rem; border-bottom: 1px solid var(--nis-border);">
        <div>
            <h3 style="margin: 0; font-size: 1.05rem; font-weight: 700;">
                <i class="fas fa-briefcase" style="color: var(--nis-green); margin-right: 8px;"></i> Expatriate Occupational Distribution
            </h3>
            <div style="font-size: 0.8rem; color: var(--text-muted); margin-top: 2px;">Leading professional designations and employment sectors among resident cardholders</div>
        </div>
        <span class="badge badge-info" style="font-size: 0.78rem;">Occupational Sectors</span>
    </div>
    <div class="card-body" style="padding: 1.25rem;">
        <?php if (empty($professionStats)): ?>
            <div style="text-align: center; padding: 2.5rem; color: #94a3b8;">
                <i class="fas fa-briefcase" style="font-size: 2.2rem; margin-bottom: 10px; display: block; opacity: 0.4;"></i>
                No occupational records logged in this period.
            </div>
        <?php else: ?>
            <div style="position: relative; height: 220px;">
                <canvas id="professionChart"></canvas>
            </div>
        <?php endif; ?>
    </div>
</div>

<!-- SECTION 4: Comprehensive Registry Dossier (Detailed Table) -->
<div class="app-card" style="margin-bottom: 2rem;">
    <div class="card-header" style="display: flex; justify-content: space-between; align-items: center; padding: 1rem 1.25rem; border-bottom: 1px solid var(--nis-border);">
        <div>
            <h3 style="margin: 0; font-size: 1.08rem; font-weight: 700;">
                <i class="fas fa-archive" style="color: var(--nis-green); margin-right: 8px;"></i> Comprehensive Registry Dossier
            </h3>
            <div style="font-size: 0.8rem; color: var(--text-muted); margin-top: 2px;">
                Detailed record of all residence cards recorded within the specified period (<?php echo count($dossierRecords); ?> total records)
            </div>
        </div>
        <div class="no-print">
            <a href="view-cards?export=csv" class="btn btn-sm btn-outline">
                <i class="fas fa-download"></i> Export Data
            </a>
        </div>
    </div>
    <div class="card-body" style="padding: 0; overflow-x: auto;">
        <table class="nis-table" style="width: 100%; border-collapse: collapse;">
            <thead>
                <tr>
                    <th style="width: 48px; text-align: center;">S/N</th>
                    <th>Card No.</th>
                    <th>Full Name</th>
                    <th>Nationality</th>
                    <th>Passport No.</th>
                    <th>Profession</th>
                    <th>Issued On</th>
                    <th>Expires On</th>
                    <th>Status</th>
                    <th class="no-print" style="text-align: center;">Action</th>
                </tr>
            </thead>
            <tbody>
                <?php if (empty($dossierRecords)): ?>
                    <tr>
                        <td colspan="10" style="text-align: center; padding: 2.5rem; color: #94a3b8;">
                            No enrolled residence records found for the selected reporting period.
                        </td>
                    </tr>
                <?php else: ?>
                    <?php $sn = 1; foreach ($dossierRecords as $card): ?>
                        <tr>
                            <td style="text-align: center; color: var(--text-muted); font-size: 0.85rem;"><?php echo $sn++; ?></td>
                            <td>
                                <span style="font-family: monospace; font-weight: 700; color: var(--nis-green);">
                                    <?php echo h($card['card_number']); ?>
                                </span>
                            </td>
                            <td>
                                <strong><?php echo h(trim(($card['surname'] ?? '') . ' ' . ($card['forenames'] ?? ''))); ?></strong>
                            </td>
                            <td><?php echo h($card['nationality']); ?></td>
                            <td>
                                <span style="font-family: monospace; font-size: 0.85rem;"><?php echo h($card['passport_number']); ?></span>
                            </td>
                            <td><?php echo h($card['profession'] ?: '—'); ?></td>
                            <td style="white-space: nowrap; font-size: 0.85rem;"><?php echo h($card['issued_on']); ?></td>
                            <td style="white-space: nowrap; font-size: 0.85rem;"><?php echo h($card['expires_on']); ?></td>
                            <td>
                                <?php
                                $st = strtoupper($card['status'] ?? 'ISSUED');
                                $statusColor = '#166534';
                                if ($st === 'RENEWED') $statusColor = '#1d4ed8';
                                elseif ($st === 'PENDING_APPROVAL') $statusColor = '#b45309';
                                elseif ($st === 'QUERIED') $statusColor = '#c2410c';
                                elseif ($st === 'EXPIRED' || $st === 'REVOKED') $statusColor = '#b91c1c';
                                ?>
                                <span style="color: <?php echo $statusColor; ?>; font-weight: 600; font-size: 0.85rem;"><?php echo h(ucwords(str_replace('_', ' ', strtolower($st)))); ?></span>
                            </td>
                            <td class="no-print" style="text-align: center;">
                                <a href="card-details?id=<?php echo (int)$card['id']; ?>" class="btn btn-sm btn-outline" style="padding: 3px 10px; font-size: 0.78rem;">
                                    View File
                                </a>
                            </td>
                        </tr>
                    <?php endforeach; ?>
                <?php endif; ?>
            </tbody>
        </table>
    </div>
</div>

<!-- Print Styles -->
<style>
@media print {
    body {
        background: #ffffff !important;
        color: #111827 !important;
        font-size: 9.5pt !important;
    }
    .sidebar, .topbar, .app-header, .page-title-box, .no-print, .filter-card, .btn {
        display: none !important;
    }
    .main-content, .content-area {
        margin: 0 !important;
        padding: 0 !important;
        width: 100% !important;
        max-width: 100% !important;
    }
    .print-only-header {
        display: block !important;
    }
    .app-card {
        box-shadow: none !important;
        border: 1px solid #d1d5db !important;
        page-break-inside: avoid;
        margin-bottom: 1.5rem !important;
    }
    .kpi-grid {
        display: grid !important;
        grid-template-columns: repeat(4, 1fr) !important;
        gap: 10px !important;
    }
    .nationality-layout {
        display: grid !important;
        grid-template-columns: 1fr 1fr !important;
        gap: 15px !important;
    }
    .charts-grid-2 {
        display: grid !important;
        grid-template-columns: 1fr 1fr !important;
        gap: 15px !important;
    }
    .nis-table {
        font-size: 8pt !important;
    }
    .nis-table th, .nis-table td {
        padding: 4px 6px !important;
    }
}
</style>

<!-- Chart.js Data Initialization Script -->
<script>
document.addEventListener('DOMContentLoaded', function() {
    if (typeof Chart === 'undefined') {
        console.error('Chart.js library not loaded.');
        return;
    }

    const nisPalette = ['#1a5c2e', '#d4af37', '#113f1f', '#27ae60', '#b38f20', '#082010', '#166534', '#047857', '#d97706', '#1e293b'];

    // 1. Nationality Donut Chart
    const natCanvas = document.getElementById('nationalityChart');
    if (natCanvas) {
        const natLabels = <?php echo json_encode(array_column($nationalityStats, 'nationality')); ?>;
        const natData = <?php echo json_encode(array_map('intval', array_column($nationalityStats, 'total'))); ?>;
        
        if (natLabels.length > 0) {
            new Chart(natCanvas, {
                type: 'doughnut',
                data: {
                    labels: natLabels,
                    datasets: [{
                        data: natData,
                        backgroundColor: nisPalette.slice(0, natLabels.length),
                        borderColor: '#ffffff',
                        borderWidth: 2,
                        hoverOffset: 6
                    }]
                },
                options: {
                    responsive: true,
                    maintainAspectRatio: false,
                    plugins: {
                        legend: {
                            position: 'bottom',
                            labels: {
                                boxWidth: 12,
                                padding: 12,
                                font: { size: 11 }
                            }
                        },
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
                    cutout: '62%'
                }
            });
        }
    }

    // 2. Issuance Velocity Monthly Bar Chart
    const velCanvas = document.getElementById('velocityChart');
    if (velCanvas) {
        const velLabels = <?php echo json_encode(array_column($velocityStats, 'ym')); ?>;
        const velData = <?php echo json_encode(array_map('intval', array_column($velocityStats, 'total'))); ?>;

        if (velLabels.length > 0) {
            new Chart(velCanvas, {
                type: 'bar',
                data: {
                    labels: velLabels,
                    datasets: [{
                        label: 'Residence Cards Issued',
                        data: velData,
                        backgroundColor: '#1a5c2e',
                        borderRadius: 6,
                        maxBarThickness: 45
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
                                    return ' ' + context.parsed.y + ' Card(s) Issued';
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
    }

    // 3. Demographic Gender Distribution Chart
    const genderCanvas = document.getElementById('genderChart');
    if (genderCanvas) {
        const genderLabels = <?php echo json_encode(array_column($genderStats, 'gender')); ?>;
        const genderData = <?php echo json_encode(array_map('intval', array_column($genderStats, 'total'))); ?>;

        if (genderLabels.length > 0) {
            new Chart(genderCanvas, {
                type: 'doughnut',
                data: {
                    labels: genderLabels,
                    datasets: [{
                        data: genderData,
                        backgroundColor: ['#1a5c2e', '#d4af37', '#94a3b8'],
                        borderColor: '#ffffff',
                        borderWidth: 2,
                        hoverOffset: 6
                    }]
                },
                options: {
                    responsive: true,
                    maintainAspectRatio: false,
                    plugins: {
                        legend: {
                            position: 'bottom',
                            labels: {
                                boxWidth: 12,
                                padding: 12,
                                font: { size: 11 }
                            }
                        },
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
                    cutout: '60%'
                }
            });
        }
    }

    // 4. Top Expatriate Professions Horizontal Bar Chart
    const profCanvas = document.getElementById('professionChart');
    if (profCanvas) {
        const profLabels = <?php echo json_encode(array_column($professionStats, 'profession')); ?>;
        const profData = <?php echo json_encode(array_map('intval', array_column($professionStats, 'total'))); ?>;

        if (profLabels.length > 0) {
            new Chart(profCanvas, {
                type: 'bar',
                data: {
                    labels: profLabels,
                    datasets: [{
                        label: 'Expatriates',
                        data: profData,
                        backgroundColor: '#1a5c2e',
                        borderRadius: 4,
                        maxBarThickness: 24
                    }]
                },
                options: {
                    indexAxis: 'y',
                    responsive: true,
                    maintainAspectRatio: false,
                    plugins: {
                        legend: { display: false },
                        tooltip: {
                            callbacks: {
                                label: function(context) {
                                    return ' ' + context.parsed.x + ' Holder(s)';
                                }
                            }
                        }
                    },
                    scales: {
                        x: {
                            beginAtZero: true,
                            ticks: { precision: 0, stepSize: 1 },
                            grid: { color: '#f1f5f9' }
                        },
                        y: {
                            grid: { display: false }
                        }
                    }
                }
            });
        }
    }
});
</script>

<?php
require_once __DIR__ . '/includes/footer.php';
?>

