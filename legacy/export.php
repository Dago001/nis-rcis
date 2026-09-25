<?php
/**
 * NIS Residence Card Issuance System (NIS-RCIS)
 * Centralized Excel / CSV Export Engine
 * Generates Microsoft Excel compatible UTF-8 CSV downloads
 */

require_once __DIR__ . '/includes/auth.php';
require_once __DIR__ . '/config/database.php';
require_once __DIR__ . '/config/constants.php';

$db = Database::getConnection();
$user = currentUser();
$exportType = trim($_GET['type'] ?? 'cards');

if ($exportType === 'cards') {
    // -------------------------------------------------------------
    // EXPORT TYPE 1: RESIDENCE CARDS DIRECTORY
    // -------------------------------------------------------------
    $search = trim($_GET['search'] ?? '');
    $filterNat = trim($_GET['nationality'] ?? '');
    $filterStatus = trim($_GET['status'] ?? '');
    $filterCommand = trim($_GET['command'] ?? '');

    $whereSql = "";
    $params = [];

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

    if (!empty($filterCommand)) {
        $whereSql .= " AND issued_at = :cmd";
        $params[':cmd'] = $filterCommand;
    }

    $stmt = $db->prepare("SELECT * FROM residence_cards WHERE 1=1" . $whereSql . " ORDER BY id DESC");
    $stmt->execute($params);
    $rows = $stmt->fetchAll();

    logAudit('EXPORT_CARDS_CSV', null, "Exported " . count($rows) . " residence cards to Excel CSV");

    $filename = "NIS_Residence_Cards_Directory_" . date('Ymd_His') . ".csv";

    header('Content-Type: text/csv; charset=UTF-8');
    header('Content-Disposition: attachment; filename="' . $filename . '"');
    header('Pragma: no-cache');
    header('Expires: 0');

    $out = fopen('php://output', 'w');
    // Prepend UTF-8 BOM for Microsoft Excel compatibility
    fputs($out, "\xEF\xBB\xBF");

    // CSV Header row
    fputcsv($out, [
        'Card Number',
        'Booklet Number',
        'Surname',
        'Given Names',
        'Nationality',
        'Sex',
        'Date of Birth',
        'Place of Birth',
        'Passport Number',
        'National ID Number',
        'Tax ID Number',
        'Profession',
        'Residential Address',
        'Blood Group',
        'Emergency Contact Name',
        'Emergency Relationship',
        'Emergency Contact Phone',
        'Issued Date',
        'Expiry Date',
        'Enrollment Center',
        'Issuing Officer',
        'Officer Service No',
        'Card Status',
        'Watchlisted',
        'Watchlist Directive',
        'Revocation Reason'
    ]);

    foreach ($rows as $r) {
        fputcsv($out, [
            $r['card_number'],
            $r['booklet_number'],
            $r['surname'],
            $r['forenames'],
            $r['nationality'],
            $r['sex'],
            $r['date_of_birth'],
            $r['place_of_birth'],
            $r['passport_number'],
            $r['national_id_number'] ?: '—',
            $r['tax_id_number'] ?: '—',
            $r['profession'],
            $r['domicile'],
            $r['blood_group'] ?: 'UNKNOWN',
            $r['emergency_contact_name'],
            $r['emergency_contact_relation'],
            $r['emergency_contact_phone'],
            $r['issued_on'],
            $r['expires_on'],
            $r['issued_at'],
            $r['issuing_officer_name'],
            $r['issuing_officer_service_no'],
            $r['status'],
            !empty($r['is_watchlisted']) ? 'YES' : 'NO',
            $r['watchlist_reason'] ?: '—',
            $r['revocation_reason'] ?: '—'
        ]);
    }

    fclose($out);
    exit();

} elseif ($exportType === 'reports') {
    // -------------------------------------------------------------
    // EXPORT TYPE 2: STATISTICAL & DEMOGRAPHIC REPORT
    // -------------------------------------------------------------
    $dateFrom = !empty($_GET['date_from']) ? trim($_GET['date_from']) : date('Y-01-01');
    $dateTo = !empty($_GET['date_to']) ? trim($_GET['date_to']) : date('Y-m-d');

    // User & RBAC Scoping
    $isSuperAdmin = ($user['role'] === ROLE_SUPER_ADMIN);
    $userId = (int)$user['id'];
    $serviceNo = trim((string)$user['service_number']);
    $userRole = $user['role'];

    if ($isSuperAdmin) {
        $cardScopeWhere = "";
        $scopeParams = [];
        $renScopeWhere = "";
        $renScopeParams = [];
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

    // Total Enrolled
    $totStmt = $db->prepare("SELECT COUNT(*) FROM residence_cards WHERE issued_on BETWEEN :df AND :dt {$cardScopeWhere}");
    $totStmt->execute(array_merge([':df' => $dateFrom, ':dt' => $dateTo], $scopeParams));
    $totalEnrolled = (int)$totStmt->fetchColumn();

    // Active & Compliant
    $actStmt = $db->prepare("SELECT COUNT(*) FROM residence_cards WHERE issued_on BETWEEN :df AND :dt AND status IN ('ISSUED', 'RENEWED') {$cardScopeWhere}");
    $actStmt->execute(array_merge([':df' => $dateFrom, ':dt' => $dateTo], $scopeParams));
    $activeCount = (int)$actStmt->fetchColumn();
    $complianceRate = $totalEnrolled > 0 ? round(($activeCount / $totalEnrolled) * 100, 1) : 0;

    // Renewals Recorded
    $renStmt = $db->prepare("SELECT COUNT(*) FROM card_renewals WHERE SUBSTR(created_at, 1, 10) BETWEEN :df AND :dt {$renScopeWhere}");
    $renStmt->execute(array_merge([':df' => $dateFrom, ':dt' => $dateTo], $renScopeParams));
    $renewalsCount = (int)$renStmt->fetchColumn();

    // Distinct Nationalities
    $natCountStmt = $db->prepare("SELECT COUNT(DISTINCT nationality) FROM residence_cards WHERE issued_on BETWEEN :df AND :dt {$cardScopeWhere}");
    $natCountStmt->execute(array_merge([':df' => $dateFrom, ':dt' => $dateTo], $scopeParams));
    $distinctNationalities = (int)$natCountStmt->fetchColumn();

    // Breakdown by Nationality
    $natStmt = $db->prepare("SELECT nationality, COUNT(*) as total FROM residence_cards WHERE issued_on BETWEEN :df AND :dt {$cardScopeWhere} GROUP BY nationality ORDER BY total DESC");
    $natStmt->execute(array_merge([':df' => $dateFrom, ':dt' => $dateTo], $scopeParams));
    $natRows = $natStmt->fetchAll();

    // Full List of Cards in Period
    $listStmt = $db->prepare("SELECT * FROM residence_cards WHERE issued_on BETWEEN :df AND :dt {$cardScopeWhere} ORDER BY id DESC");
    $listStmt->execute(array_merge([':df' => $dateFrom, ':dt' => $dateTo], $scopeParams));
    $cards = $listStmt->fetchAll();

    logAudit('EXPORT_REPORT_CSV', null, "Exported comprehensive residency report CSV for {$dateFrom} to {$dateTo}");

    $filename = "NIS_Residency_Report_" . date('Ymd_His') . ".csv";

    header('Content-Type: text/csv; charset=UTF-8');
    header('Content-Disposition: attachment; filename="' . $filename . '"');
    header('Pragma: no-cache');
    header('Expires: 0');

    $out = fopen('php://output', 'w');
    fputs($out, "\xEF\xBB\xBF");

    // Metadata Section
    fputcsv($out, ['NIGERIA IMMIGRATION SERVICE (NIS) — RESIDENCE CARD REPORT']);
    fputcsv($out, ['Report Generation Date', date('Y-m-d H:i:s') . ' (WAT)']);
    fputcsv($out, ['Reporting Period From', $dateFrom]);
    fputcsv($out, ['Reporting Period To', $dateTo]);
    fputcsv($out, ['Generated By Officer', $user['fullname'] . ' (' . $user['service_number'] . ')']);
    fputcsv($out, []); // Blank line

    // KPI Summary
    fputcsv($out, ['KEY PERFORMANCE INDICATORS (KPIs)']);
    fputcsv($out, ['Metric', 'Value', 'Note']);
    fputcsv($out, ['Total Cards Enrolled', $totalEnrolled, 'Cards issued within date window']);
    fputcsv($out, ['Active & Compliant Cards', $activeCount, 'Valid issued / renewed status']);
    fputcsv($out, ['Active Compliance Rate', $complianceRate . '%', 'Proportion of active vs expired/revoked']);
    fputcsv($out, ['Renewals Granted', $renewalsCount, 'Total endorsements']);
    fputcsv($out, ['Distinct Foreign Nationalities', $distinctNationalities, 'Unique expatriate source states']);
    fputcsv($out, []); // Blank line

    // Nationality Breakdown Section
    fputcsv($out, ['EXPATRIATE RESIDENCY BY NATIONALITY']);
    fputcsv($out, ['Rank', 'Nationality', 'Enrolled Count', 'Share of Total']);
    $rank = 1;
    foreach ($natRows as $nr) {
        $pct = $totalEnrolled > 0 ? round(($nr['total'] / $totalEnrolled) * 100, 1) : 0;
        fputcsv($out, [$rank++, $nr['nationality'], $nr['total'], $pct . '%']);
    }
    fputcsv($out, []); // Blank line

    // Detailed Registry Section
    fputcsv($out, ['DETAILED RESIDENCE CARDS REGISTRY IN PERIOD']);
    fputcsv($out, [
        'Card Number',
        'Booklet Number',
        'Surname',
        'Given Names',
        'Nationality',
        'Sex',
        'Date of Birth',
        'Passport Number',
        'Profession',
        'Residential Address',
        'Issued Date',
        'Expiry Date',
        'Enrollment Center',
        'Issuing Officer',
        'Card Status',
        'Watchlisted'
    ]);

    foreach ($cards as $c) {
        fputcsv($out, [
            $c['card_number'],
            $c['booklet_number'],
            $c['surname'],
            $c['forenames'],
            $c['nationality'],
            $c['sex'],
            $c['date_of_birth'],
            $c['passport_number'],
            $c['profession'],
            $c['domicile'],
            $c['issued_on'],
            $c['expires_on'],
            $c['issued_at'],
            $c['issuing_officer_name'],
            $c['status'],
            !empty($c['is_watchlisted']) ? 'YES' : 'NO'
        ]);
    }

    fclose($out);
    exit();

} elseif ($exportType === 'audit_logs') {
    // -------------------------------------------------------------
    // EXPORT TYPE 3: SECURITY AUDIT TRAIL (SuperAdmin Only)
    // -------------------------------------------------------------
    requireRole(ROLE_SUPER_ADMIN);

    $search = trim($_GET['search'] ?? '');
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

    $sql = "SELECT a.*, u.fullname, u.service_number, u.role as officer_role 
            FROM audit_logs a 
            LEFT JOIN users u ON (a.user_id = u.id OR (a.user_id IS NULL AND (a.username = u.service_number OR a.username = u.username)))
            WHERE 1=1" . $whereSql . " 
            ORDER BY a.id DESC";
    $stmt = $db->prepare($sql);
    $stmt->execute($params);
    $logs = $stmt->fetchAll();

    logAudit('EXPORT_AUDIT_CSV', null, "Exported " . count($logs) . " security audit log entries to CSV");

    $filename = "NIS_Security_Audit_Logs_" . date('Ymd_His') . ".csv";

    header('Content-Type: text/csv; charset=UTF-8');
    header('Content-Disposition: attachment; filename="' . $filename . '"');
    header('Pragma: no-cache');
    header('Expires: 0');

    $out = fopen('php://output', 'w');
    fputs($out, "\xEF\xBB\xBF");

    fputcsv($out, [
        'Log ID',
        'Timestamp (UTC/WAT)',
        'Officer / User Name',
        'Service Number / Username',
        'Assigned Role',
        'Action Code',
        'Target Card ID',
        'Event Details',
        'IP Address',
        'User Agent'
    ]);

    foreach ($logs as $l) {
        $officerDisplay = !empty($l['fullname']) ? $l['fullname'] : $l['username'];
        $svcDisplay = !empty($l['service_number']) ? $l['service_number'] : $l['username'];

        fputcsv($out, [
            $l['id'],
            $l['created_at'],
            $officerDisplay,
            $svcDisplay,
            $l['officer_role'] ?: 'System / Automated',
            $l['action'],
            $l['card_id'] ?: '—',
            $l['details'],
            $l['ip_address'],
            $l['user_agent']
        ]);
    }

    fclose($out);
    exit();

} elseif ($exportType === 'applications') {
    // -------------------------------------------------------------
    // EXPORT TYPE 4: APPLICATIONS APPROVAL QUEUE
    // -------------------------------------------------------------
    $statusFilter = trim($_GET['status'] ?? '');
    $search = trim($_GET['search'] ?? '');

    $whereSql = "";
    $params = [];

    if ($statusFilter === 'PENDING_APPROVAL') {
        $whereSql .= " AND status = 'PENDING_APPROVAL'";
    } elseif ($statusFilter === 'AWAITING_ISSUANCE') {
        $whereSql .= " AND status IN ('APPROVED_FOR_BIOMETRICS', 'BIOMETRICS_CAPTURED')";
    } elseif ($statusFilter === 'QUERIED') {
        $whereSql .= " AND status = 'QUERIED'";
    } elseif ($statusFilter === 'REJECTED') {
        $whereSql .= " AND status = 'REJECTED'";
    } elseif ($statusFilter !== 'ALL' && !empty($statusFilter)) {
        $whereSql .= " AND status = :st";
        $params[':st'] = $statusFilter;
    }

    if (!empty($search)) {
        $whereSql .= " AND (application_number LIKE :s1 OR reference_number LIKE :s2 OR surname LIKE :s3 OR forenames LIKE :s4 OR passport_number LIKE :s5 OR email LIKE :s6 OR phone LIKE :s7 OR payment_reference LIKE :s8)";
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

    $stmt = $db->prepare("SELECT * FROM applications WHERE 1=1" . $whereSql . " ORDER BY id DESC");
    $stmt->execute($params);
    $apps = $stmt->fetchAll();

    logAudit('EXPORT_APPLICATIONS_CSV', null, "Exported " . count($apps) . " applications to CSV");

    $filename = "NIS_Applications_Queue_" . date('Ymd_His') . ".csv";

    header('Content-Type: text/csv; charset=UTF-8');
    header('Content-Disposition: attachment; filename="' . $filename . '"');
    header('Pragma: no-cache');
    header('Expires: 0');

    $out = fopen('php://output', 'w');
    fputs($out, "\xEF\xBB\xBF");

    fputcsv($out, [
        'Application Number',
        'Payment Reference',
        'Surname',
        'Given Names',
        'Nationality',
        'Gender',
        'Date of Birth',
        'Passport Number',
        'Passport Expiry',
        'Email Address',
        'Phone Number',
        'Residential Address in Nigeria',
        'Change of Address',
        'Next of Kin Name',
        'Next of Kin Relation',
        'Next of Kin Phone',
        'Fee Amount',
        'Enrollment Center',
        'Appointment Date',
        'Appointment Time',
        'Status',
        'Date Submitted'
    ]);

    foreach ($apps as $a) {
        fputcsv($out, [
            $a['application_number'],
            $a['payment_reference'],
            $a['surname'],
            $a['forenames'],
            $a['nationality'],
            $a['sex'],
            $a['date_of_birth'],
            $a['passport_number'],
            $a['passport_expiry'],
            $a['email'],
            $a['phone'],
            $a['domicile'],
            $a['change_of_address'] ?: '',
            $a['emergency_contact_name'],
            $a['emergency_contact_relation'],
            $a['emergency_contact_phone'],
            $a['fee_amount'],
            $a['enrollment_center'],
            $a['appointment_date'],
            $a['appointment_time'],
            $a['status'],
            $a['created_at']
        ]);
    }

    fclose($out);
    exit();

} else {
    header("Location: dashboard");
    exit();
}
