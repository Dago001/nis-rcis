<?php
/**
 * NIS Residence Card Issuance System (NIS-RCIS)
 * Applicant Dashboard & Self-Service Management Portal
 * Displays all saved applications, live approval status, notifications, and biometrics slip printing
 */

require_once __DIR__ . '/includes/security.php';
require_once __DIR__ . '/config/database.php';
require_once __DIR__ . '/config/constants.php';

if (session_status() === PHP_SESSION_NONE) {
    session_start();
}

// Authentication guard for applicant
if (empty($_SESSION['applicant_id'])) {
    header("Location: applicant-login?redirect=" . urlencode('applicant-dashboard'));
    exit();
}

$db = Database::getConnection();
$applicantId = (int)$_SESSION['applicant_id'];

// Fetch applicant profile
$stmt = $db->prepare("SELECT * FROM applicants WHERE id = :id LIMIT 1");
$stmt->execute([':id' => $applicantId]);
$applicant = $stmt->fetch();

if (!$applicant) {
    session_destroy();
    header("Location: applicant-login");
    exit();
}

// Handle Query Document Re-upload
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['action_reupload_doc'])) {
    if (!validate_csrf()) {
        setFlash('danger', 'Security validation token mismatch. Please try again.');
        header("Location: applicant-dashboard");
        exit();
    }
    
    $targetAppId = (int)($_POST['app_id'] ?? 0);
    $docType = trim($_POST['doc_type'] ?? '');
    
    // Verify application belongs to this applicant and is currently QUERIED
    $checkStmt = $db->prepare("SELECT * FROM applications WHERE id = :id AND (applicant_id = :aid OR email = :em) AND status = 'QUERIED' LIMIT 1");
    $checkStmt->execute([':id' => $targetAppId, ':aid' => $applicantId, ':em' => $applicant['email']]);
    $targetApp = $checkStmt->fetch();
    
    if (!$targetApp) {
        setFlash('danger', 'Invalid application or application is not currently pending query resolution.');
        header("Location: applicant-dashboard");
        exit();
    }
    
    $validDocCols = [
        'doc_passport_copy' => 'International Passport Bio-data Page',
        'doc_residence_visa' => 'Residence Visa / Entry Permit',
        'doc_quota_approval' => 'Expatriate Quota Approval / Letter',
        'doc_domicile_proof' => 'Proof of Domicile / Tenancy Agreement',
        'doc_additional' => 'Additional Supporting Document'
    ];
    
    if (!array_key_exists($docType, $validDocCols)) {
        setFlash('danger', 'Please select a valid document category to re-upload.');
        header("Location: applicant-dashboard");
        exit();
    }
    
    if (!isset($_FILES['replacement_file']) || $_FILES['replacement_file']['error'] !== UPLOAD_ERR_OK) {
        setFlash('danger', 'Please select a valid file to upload.');
        header("Location: applicant-dashboard");
        exit();
    }
    
    $file = $_FILES['replacement_file'];
    $ext = strtolower(pathinfo($file['name'], PATHINFO_EXTENSION));
    $allowedExts = ['pdf', 'jpg', 'jpeg', 'png'];
    if (!in_array($ext, $allowedExts)) {
        setFlash('danger', 'Invalid file format. Allowed formats: PDF, JPG, PNG.');
        header("Location: applicant-dashboard");
        exit();
    }
    if ($file['size'] > 5 * 1024 * 1024) {
        setFlash('danger', 'File size exceeds maximum limit of 5MB.');
        header("Location: applicant-dashboard");
        exit();
    }
    
    $uploadDir = __DIR__ . '/uploads/documents/';
    if (!is_dir($uploadDir)) @mkdir($uploadDir, 0755, true);
    $newFileName = 'doc_' . $targetApp['id'] . '_' . substr($docType, 4) . '_' . time() . '.' . $ext;
    $destPath = $uploadDir . $newFileName;
    
    if (move_uploaded_file($file['tmp_name'], $destPath)) {
        $dbPath = 'uploads/documents/' . $newFileName;
        $updStmt = $db->prepare("UPDATE applications SET 
            {$docType} = :path, 
            status = 'PENDING_APPROVAL', 
            updated_at = CURRENT_TIMESTAMP 
            WHERE id = :id");
        $updStmt->execute([':path' => $dbPath, ':id' => $targetApp['id']]);
        
        logAudit('DOCUMENT_REUPLOADED', $targetApp['id'], "Applicant re-uploaded {$validDocCols[$docType]} for queried application {$targetApp['application_number']}. Status reset to PENDING_APPROVAL.");
        
        setFlash('success', "Your replacement {$validDocCols[$docType]} has been uploaded successfully. Application {$targetApp['application_number']} has been resubmitted for immigration officer review.");
        header("Location: applicant-dashboard");
        exit();
    } else {
        setFlash('danger', 'Failed to save uploaded file. Please try again.');
        header("Location: applicant-dashboard");
        exit();
    }
}

// Fetch all applications linked to this applicant (by applicant_id or matching email)
$appStmt = $db->prepare("SELECT * FROM applications WHERE applicant_id = :aid OR (email = :em AND applicant_id IS NULL) ORDER BY id DESC");
$appStmt->execute([':aid' => $applicantId, ':em' => $applicant['email']]);
$applications = $appStmt->fetchAll();

// Look up any issued residence card matching this applicant's passport number
$cardStmt = $db->prepare("SELECT * FROM residence_cards WHERE passport_number = :pn ORDER BY id DESC LIMIT 1");
$cardStmt->execute([':pn' => $applicant['passport_number']]);
$activeCard = $cardStmt->fetch();

$hasDraft = !empty($applicant['draft_data']);
$draftInfo = $hasDraft ? json_decode($applicant['draft_data'], true) : null;
$draftStep = $hasDraft ? (int)($applicant['draft_step'] ?? ($draftInfo['current_step_saved'] ?? 1)) : 1;
$stepLabels = [
    1 => '1. Passport Details & Photo',
    2 => '2. Personal Particulars & Contact',
    3 => '3. Next of Kin Particulars',
    4 => '4. Supporting Documents',
    5 => '5. Review Application',
    6 => '6. Fee Payment',
    7 => '7. Appointment Booking'
];

// Look up notifications for approved applications & ready cards
$applicantNotifications = [];
$pendingAppsCount = 0;

foreach ($applications as $app) {
    $stUpper = strtoupper(trim($app['status'] ?? ''));
    
    // 1. Residence Card Ready for Collection
    if ($stUpper === 'CARD_READY' || $stUpper === 'READY_FOR_COLLECTION' || !empty($app['card_ready_notified'])) {
        $applicantNotifications[] = [
            'type' => 'card_ready',
            'icon' => 'fas fa-id-card',
            'title' => 'Residence Card Ready for Collection',
            'tag' => 'Ready for Collection',
            'app_num' => $app['application_number'],
            'message' => 'Good news! Your official Smart Residence Card for Application <strong>' . h($app['application_number']) . '</strong> has been produced and delivered to <strong>' . h($app['enrollment_center']) . '</strong>. You may now visit the center to pick up your physical card.',
            'subtext' => 'Please bring along your original International Passport (' . h($app['passport_number']) . ') and your printed Biometrics Slip for identity verification.',
            'action_label' => 'View Collection Details',
            'action_url' => 'track?app_no=' . urlencode($app['application_number']) . '&passport_no=' . urlencode($app['passport_number']),
            'action_icon' => 'fas fa-map-marker-alt',
            'time' => !empty($app['card_ready_notified_at']) ? date('d M Y, h:i A', strtotime($app['card_ready_notified_at'])) : date('d M Y', strtotime($app['updated_at']))
        ];
    }
    
    // 2. Application Approved (Authorized for Biometrics)
    if ($stUpper === 'APPROVED_FOR_BIOMETRICS' || $stUpper === 'APPROVED') {
        $applicantNotifications[] = [
            'type' => 'approved',
            'icon' => 'fas fa-check-circle',
            'title' => 'Application Approved & Biometrics Authorized',
            'tag' => 'Official Approval Granted',
            'app_num' => $app['application_number'],
            'message' => 'Official approval has been granted for Application <strong>' . h($app['application_number']) . '</strong>! Your physical biometrics capturing appointment is confirmed for <strong>' . h(date('D, d M Y', strtotime($app['appointment_date']))) . ' (' . h($app['appointment_time']) . ')</strong> at <strong>' . h($app['enrollment_center']) . '</strong>.',
            'subtext' => 'Please print your official Biometrics Appointment Slip and bring it along to the enrollment center.',
            'action_label' => 'Print Biometrics Slip',
            'action_url' => 'print-appointment-slip?id=' . (int)$app['id'],
            'action_icon' => 'fas fa-print',
            'time' => !empty($app['approved_at']) ? date('d M Y, h:i A', strtotime($app['approved_at'])) : date('d M Y', strtotime($app['updated_at']))
        ];
    } elseif ($stUpper === 'QUERIED') {
        $queryPayload = [
            'app_id' => (int)$app['id'],
            'app_num' => $app['application_number'],
            'notes' => !empty($app['approval_notes']) ? $app['approval_notes'] : 'One or more uploaded supporting documents are unclear, blurred, or require verification. Please review and re-upload a clear copy to proceed.',
            'center' => !empty($app['enrollment_center']) ? $app['enrollment_center'] : 'NIS HQ, Airport Sauka Abuja',
            'date' => !empty($app['appointment_date']) ? $app['appointment_date'] : '',
            'passport' => $app['passport_number']
        ];
        $queryPayloadJson = json_encode($queryPayload);

        $applicantNotifications[] = [
            'type' => 'queried',
            'icon' => 'fas fa-exclamation-triangle',
            'title' => 'Application Query Issued',
            'tag' => 'Action Required',
            'app_num' => $app['application_number'],
            'message' => 'An immigration query was issued for Application <strong>' . h($app['application_number']) . '</strong>: ' . h($app['approval_notes'] ?? 'Please check uploaded documents or visit your enrollment center.'),
            'subtext' => 'Please review and resolve the query to proceed with processing.',
            'action_label' => 'View Query & Resolution',
            'action_onclick' => 'openQueryModal(' . htmlspecialchars($queryPayloadJson, ENT_QUOTES, 'UTF-8') . '); closeNotificationDropdown();',
            'action_url' => '#',
            'action_icon' => 'fas fa-eye',
            'time' => !empty($app['approved_at']) ? date('d M Y, h:i A', strtotime($app['approved_at'])) : date('d M Y', strtotime($app['updated_at']))
        ];
    } elseif ($stUpper === 'PENDING' || $stUpper === 'PENDING_APPROVAL' || $stUpper === 'BIOMETRICS_CAPTURED') {
        $pendingAppsCount++;
    }
}

// Compute KPI statistics
$statTotal = count($applications) + ($hasDraft ? 1 : 0);
$statApproved = 0;
$statPending = 0;
$statIssued = $activeCard ? 1 : 0;

foreach ($applications as $app) {
    $st = strtoupper($app['status'] ?? '');
    if ($st === 'APPROVED_FOR_BIOMETRICS' || $st === 'APPROVED' || $st === 'READY_FOR_COLLECTION') {
        $statApproved++;
    } elseif ($st === 'ISSUED') {
        if (!$activeCard) $statIssued++;
    } else {
        $statPending++;
    }
}
if ($hasDraft) {
    $statPending++;
}

// Prepare applicant initials & personalized greeting
$applicantFullName = trim(($applicant['forenames'] ?? '') . ' ' . ($applicant['surname'] ?? ''));
$initials = strtoupper(substr($applicant['forenames'] ?? '', 0, 1) . substr($applicant['surname'] ?? '', 0, 1));
if (empty(trim($initials))) {
    $initials = 'NIS';
}

$welcomeFullName = !empty($applicantFullName) ? ucwords(strtolower($applicantFullName)) : 'Applicant';

$pageTitle = 'My Profile & Applications';
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0, maximum-scale=5.0">
    <title>Applicant Dashboard — Nigeria Immigration Service</title>

    <link rel="icon" type="image/png" href="assets/images/nis-crest-logo-transparent.png?v=3">
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css">
    <link rel="stylesheet" href="assets/css/style.css?v=<?php echo APP_VERSION; ?>">

    <style>
        body {
            background: linear-gradient(135deg, rgba(248, 250, 252, 0.45), rgba(241, 245, 249, 0.55)),
                        url('assets/images/login-bg.jpg') center center / cover fixed no-repeat;
            color: #1e293b;
            min-height: 100vh;
            display: flex;
            flex-direction: column;
            font-family: -apple-system, BlinkMacSystemFont, "Segoe UI", Roboto, sans-serif;
        }

        .dashboard-container {
            max-width: 960px;
            margin: 2rem auto 3.5rem;
            padding: 0 1.25rem;
            flex: 1 0 auto;
            width: 100%;
        }

        /* Unified Master Dashboard Surface */
        .dashboard-unified-card {
            background: #ffffff;
            border: 1px solid #e2e8f0;
            border-radius: 14px;
            box-shadow: 0 4px 20px rgba(0, 0, 0, 0.05);
            overflow: hidden;
        }

        /* Profile Header Section */
        .dash-header-section {
            background: #ffffff;
            padding: 1.75rem 2rem;
            display: flex;
            justify-content: space-between;
            align-items: center;
            flex-wrap: wrap;
            gap: 1.5rem;
            border-bottom: 1px solid #f1f5f9;
        }

        .profile-left {
            display: flex;
            align-items: center;
            gap: 1.25rem;
        }

        .profile-avatar {
            width: 64px;
            height: 64px;
            border-radius: 50%;
            background: linear-gradient(135deg, #113f1f, #1a5c2e);
            border: 2px solid #e2e8f0;
            display: flex;
            align-items: center;
            justify-content: center;
            font-size: 1.35rem;
            font-weight: 800;
            color: #ffffff;
            overflow: hidden;
            flex-shrink: 0;
            box-shadow: 0 3px 10px rgba(17, 63, 31, 0.12);
        }

        .profile-clock-row {
            margin-top: 3px;
            margin-bottom: 6px;
        }

        .dash-live-clock {
            display: inline-flex;
            align-items: center;
            gap: 6px;
            font-size: 0.84rem;
            font-weight: 500;
            color: #64748b;
            background: transparent !important;
            border: none !important;
            padding: 0 !important;
            box-shadow: none !important;
        }

        .profile-title-row {
            display: flex;
            align-items: center;
            gap: 10px;
            flex-wrap: wrap;
            margin-bottom: 6px;
        }

        .profile-name {
            font-size: 1.35rem;
            font-weight: 800;
            color: #0f172a;
            margin: 0;
            letter-spacing: -0.2px;
        }

        .profile-meta {
            font-size: 0.85rem;
            color: #64748b;
            display: flex;
            gap: 12px;
            flex-wrap: wrap;
            align-items: center;
        }

        .profile-meta span {
            display: inline-flex;
            align-items: center;
            gap: 6px;
        }


        /* Stats KPI Strip */
        .dash-stats-strip {
            display: grid;
            grid-template-columns: repeat(4, 1fr);
            background: #ffffff;
            border-bottom: 1px solid #f1f5f9;
        }

        .stat-item {
            padding: 1.4rem 1.75rem;
            display: flex;
            align-items: center;
            gap: 1.15rem;
            border-right: 1px solid #f1f5f9;
            transition: background-color 0.15s ease;
        }

        .stat-item:last-child {
            border-right: none;
        }

        .stat-item:hover {
            background: #fafbfc;
        }

        .stat-icon-wrapper {
            width: 46px;
            height: 46px;
            border-radius: 10px;
            display: flex;
            align-items: center;
            justify-content: center;
            font-size: 1.25rem;
            flex-shrink: 0;
        }

        .stat-icon-green {
            background: #ecfdf5;
            color: #059669;
        }

        .stat-icon-blue {
            background: #eff6ff;
            color: #2563eb;
        }

        .stat-icon-amber {
            background: #fffbeb;
            color: #d97706;
        }

        .stat-icon-gold {
            background: #fefce8;
            color: #ca8a04;
        }

        .stat-info {
            flex: 1;
            min-width: 0;
        }

        .stat-number {
            font-size: 1.55rem;
            font-weight: 400;
            color: #0f172a;
            line-height: 1.1;
        }

        .stat-label {
            font-size: 0.82rem;
            font-weight: 700;
            color: #334155;
            margin-top: 2px;
        }

        @keyframes queryModalFadeIn {
            from {
                opacity: 0;
                transform: scale(0.96) translateY(-8px);
            }
            to {
                opacity: 1;
                transform: scale(1) translateY(0);
            }
        }

        /* Query Reason & Resolution Modal Styles */
        .query-modal-backdrop {
            display: none;
            position: fixed;
            top: 0;
            left: 0;
            right: 0;
            bottom: 0;
            background: rgba(15, 23, 42, 0.65);
            backdrop-filter: blur(4px);
            z-index: 9999;
            align-items: center;
            justify-content: center;
            padding: 16px;
        }

        .query-modal-dialog {
            background: #ffffff;
            border-radius: 12px;
            max-width: 580px;
            width: 100%;
            box-shadow: 0 20px 25px -5px rgba(0, 0, 0, 0.15), 0 10px 10px -5px rgba(0, 0, 0, 0.05);
            border: 1px solid #e2e8f0;
            overflow: hidden;
            animation: queryModalFadeIn 0.22s ease-out forwards;
        }

        .query-modal-header {
            background: #fff1f2;
            border-bottom: 1px solid #fecdd3;
            padding: 16px 20px;
            display: flex;
            align-items: center;
            justify-content: space-between;
        }

        .query-modal-title {
            display: flex;
            align-items: center;
            gap: 10px;
            font-size: 1.05rem;
            font-weight: 700;
            color: #9f1239;
            margin: 0;
        }

        .btn-query-modal-close {
            background: transparent;
            border: none;
            font-size: 1.35rem;
            color: #9f1239;
            cursor: pointer;
            line-height: 1;
            padding: 4px 8px;
            border-radius: 4px;
        }

        .btn-query-modal-close:hover {
            background: rgba(159, 18, 57, 0.1);
        }

        .query-modal-body {
            padding: 22px 24px;
            color: #334155;
            font-size: 0.92rem;
            max-height: 75vh;
            overflow-y: auto;
        }

        .query-app-banner {
            display: flex;
            align-items: center;
            justify-content: space-between;
            background: #f8fafc;
            border: 1px solid #e2e8f0;
            padding: 10px 14px;
            border-radius: 8px;
            margin-bottom: 18px;
            font-size: 0.85rem;
        }

        .query-reason-card {
            background: #fff7ed;
            border: 1px solid #fed7aa;
            border-left: 4px solid #ea580c;
            border-radius: 6px;
            padding: 14px 16px;
            margin-bottom: 20px;
        }

        .query-reason-label {
            font-size: 0.78rem;
            font-weight: 700;
            text-transform: uppercase;
            letter-spacing: 0.5px;
            color: #c2410c;
            margin-bottom: 6px;
            display: flex;
            align-items: center;
            gap: 6px;
        }

        .query-reason-text {
            color: #7c2d12;
            font-size: 0.92rem;
            line-height: 1.5;
            word-break: break-word;
        }

        .query-steps-title {
            font-size: 0.88rem;
            font-weight: 700;
            color: #0f172a;
            margin-bottom: 12px;
            display: flex;
            align-items: center;
            gap: 8px;
        }

        .query-steps-list {
            list-style: none;
            padding: 0;
            margin: 0 0 20px 0;
            display: flex;
            flex-direction: column;
            gap: 10px;
        }

        .query-step-item {
            display: flex;
            gap: 12px;
            font-size: 0.86rem;
            line-height: 1.45;
            color: #334155;
            background: #f8fafc;
            padding: 10px 12px;
            border-radius: 6px;
            border: 1px solid #edf2f7;
        }

        .query-step-num {
            width: 22px;
            height: 22px;
            border-radius: 50%;
            background: #113f1f;
            color: #ffffff;
            font-size: 0.75rem;
            font-weight: 700;
            display: flex;
            align-items: center;
            justify-content: center;
            flex-shrink: 0;
            margin-top: 1px;
        }

        .query-modal-footer {
            background: #f8fafc;
            border-top: 1px solid #e2e8f0;
            padding: 14px 20px;
            display: flex;
            justify-content: flex-end;
            gap: 10px;
        }

        .btn-dash-query {
            background: #fef2f2 !important;
            color: #991b1b !important;
            border: 1px solid #fca5a5 !important;
            font-weight: 600;
        }

        .btn-dash-query:hover {
            background: #fee2e2 !important;
            color: #7f1d1d !important;
            border-color: #f87171 !important;
        }

        .profile-actions {
            display: flex;
            gap: 10px;
            flex-wrap: wrap;
            align-items: center;
        }

        .btn-dash-action {
            background: #ffffff;
            color: #113f1f;
            border: 1px solid #cbd5e1;
            padding: 9px 16px;
            font-size: 0.86rem;
            font-weight: 600;
            border-radius: 6px;
            text-decoration: none;
            display: inline-flex;
            align-items: center;
            gap: 8px;
            transition: all 0.15s ease;
        }

        .btn-dash-action:hover {
            background: #f8fafc;
            border-color: #94a3b8;
            color: #113f1f;
        }

        .btn-dash-primary {
            background: #113f1f;
            color: #ffffff;
            border-color: #113f1f;
        }

        .btn-dash-primary:hover {
            background: #165b2d;
            border-color: #165b2d;
            color: #ffffff;
        }

        .btn-dash-logout {
            color: #dc2626;
            border-color: #fecaca;
        }

        .btn-dash-logout:hover {
            background: #fef2f2;
            border-color: #fca5a5;
            color: #b91c1c;
        }

        .btn-dash-gold {
            background: #fef9c3;
            color: #854d0e;
            border: 1px solid #fef08a;
        }

        .btn-dash-gold:hover {
            background: #fef08a;
            color: #713f12;
        }

        /* Card Section / Unified Sections */
        .dash-table-section {
            padding: 1.75rem 2rem 2.25rem;
            background: #ffffff;
        }

        .dash-active-card-section {
            padding: 1.5rem 2rem;
            border-bottom: 1px solid #f1f5f9;
            background: #fcfdfd;
        }

        .dash-section-card {
            background: #ffffff;
            border: 1px solid #e2e8f0;
            border-radius: 12px;
            padding: 1.75rem 2rem;
            margin-bottom: 2rem;
            box-shadow: 0 2px 10px rgba(0, 0, 0, 0.04);
        }

        .section-header-row {
            display: flex;
            justify-content: space-between;
            align-items: center;
            margin-bottom: 1.25rem;
            padding-bottom: 0.75rem;
            border-bottom: 1px solid #f1f5f9;
            flex-wrap: wrap;
            gap: 10px;
        }

        .section-header-row h3 {
            font-size: 1.15rem;
            font-weight: 800;
            color: #113f1f;
            margin: 0;
            display: flex;
            align-items: center;
            gap: 8px;
        }

        /* Table */
        .app-table-wrapper {
            overflow-x: auto;
        }

        .dash-table {
            width: 100%;
            border-collapse: collapse;
            text-align: left;
            font-size: 0.88rem;
        }

        .dash-table th {
            background: #f8fafc;
            color: #475569;
            font-weight: 700;
            text-transform: uppercase;
            letter-spacing: 0.5px;
            font-size: 0.76rem;
            padding: 12px 16px;
            border-bottom: 1.5px solid #e2e8f0;
        }

        .dash-table td {
            padding: 14px 16px;
            border-bottom: 1px solid #f1f5f9;
            vertical-align: middle;
        }

        .dash-table tr:hover td {
            background: #fbfcfe;
        }

        .badge-status {
            display: inline-block;
            font-size: 0.82rem;
            font-weight: 700;
            text-transform: uppercase;
            letter-spacing: 0.4px;
            background: transparent !important;
            border: none !important;
            padding: 0 !important;
            box-shadow: none !important;
        }

        .badge-status.pending {
            background: transparent !important;
            color: #b45309;
        }

        .badge-status.approved {
            background: transparent !important;
            color: #15803d;
        }

        .badge-status.ready {
            background: transparent !important;
            color: #2563eb;
        }

        .badge-status.draft {
            background: transparent !important;
            color: #b45309;
            border: none !important;
        }

        .empty-history-box {
            text-align: center;
            padding: 3rem 1.5rem;
            color: #64748b;
        }

        .empty-history-box i {
            font-size: 3rem;
            color: #cbd5e1;
            margin-bottom: 1rem;
        }

        .permit-card-widget {
            background: #0f2d18;
            color: #ffffff;
            border-radius: 10px;
            padding: 1.5rem;
            border: 1px solid #1c5e31;
            display: flex;
            justify-content: space-between;
            align-items: center;
            flex-wrap: wrap;
            gap: 1rem;
        }

        /* Official Notifications Dropdown & Bell */
        .notif-dropdown-wrapper {
            position: relative;
            display: inline-block;
        }

        .btn-notif-bell {
            position: relative;
            width: 42px;
            height: 42px;
            border-radius: 8px;
            background: #ffffff;
            border: 1.5px solid #cbd5e1;
            color: #334155;
            display: inline-flex;
            align-items: center;
            justify-content: center;
            font-size: 1.15rem;
            cursor: pointer;
            transition: all 0.18s ease;
        }

        .btn-notif-bell:hover,
        .btn-notif-bell:focus {
            background: #f8fafc;
            border-color: #113f1f;
            color: #113f1f;
            outline: none;
        }

        .notif-bell-badge {
            position: absolute;
            top: -5px;
            right: -5px;
            background: #dc2626;
            color: #ffffff;
            font-size: 0.68rem;
            font-weight: 800;
            min-width: 18px;
            height: 18px;
            line-height: 18px;
            border-radius: 9999px;
            text-align: center;
            padding: 0 4px;
            border: 2px solid #ffffff;
            box-shadow: 0 2px 5px rgba(0, 0, 0, 0.15);
        }

        .notif-dropdown-panel {
            display: none;
            position: absolute;
            top: calc(100% + 10px);
            right: 0;
            width: 440px;
            max-width: calc(100vw - 32px);
            background: #ffffff;
            border-radius: 12px;
            box-shadow: 0 16px 40px rgba(0, 0, 0, 0.15), 0 0 0 1px rgba(0, 0, 0, 0.06);
            z-index: 1050;
            overflow: hidden;
            animation: notifSlideDown 0.18s ease-out forwards;
        }

        .notif-dropdown-panel.show {
            display: block;
        }

        @keyframes notifSlideDown {
            from {
                opacity: 0;
                transform: translateY(-6px);
            }
            to {
                opacity: 1;
                transform: translateY(0);
            }
        }

        .notif-dropdown-header {
            padding: 0.85rem 1.15rem;
            background: #f8fafc;
            border-bottom: 1px solid #e2e8f0;
            display: flex;
            align-items: center;
            justify-content: space-between;
            font-size: 0.92rem;
            color: #0f172a;
        }

        .btn-close-notif {
            background: transparent;
            border: none;
            font-size: 1.35rem;
            line-height: 1;
            color: #64748b;
            cursor: pointer;
            padding: 0 4px;
        }

        .btn-close-notif:hover {
            color: #0f172a;
        }

        .notif-dropdown-body {
            padding: 0.9rem;
            max-height: 420px;
            overflow-y: auto;
        }

        .notif-banner {
            background: #ffffff;
            border-radius: 8px;
            padding: 0.85rem 1rem;
            margin-bottom: 0.75rem;
            display: flex;
            flex-direction: column;
            align-items: stretch;
            gap: 0.75rem;
            border: 1px solid #e2e8f0;
            transition: transform 0.15s ease, box-shadow 0.15s ease;
        }

        .notif-banner:last-child {
            margin-bottom: 0;
        }

        .notif-banner:hover {
            transform: translateY(-1px);
            box-shadow: 0 4px 14px rgba(0, 0, 0, 0.06);
        }

        .notif-banner.notif-approved {
            background: #ecfdf5;
            border: 1px solid #a7f3d0;
            color: #065f46;
        }

        .notif-banner.notif-ready {
            background: #eff6ff;
            border: 1px solid #bfdbfe;
            color: #1e40af;
        }

        .notif-left {
            display: flex;
            align-items: flex-start;
            gap: 12px;
        }

        .notif-icon-box {
            width: 40px;
            height: 40px;
            border-radius: 8px;
            display: flex;
            align-items: center;
            justify-content: center;
            font-size: 1.15rem;
            flex-shrink: 0;
            margin-top: 2px;
        }

        .notif-approved .notif-icon-box {
            background: #d1fae5;
            color: #059669;
        }

        .notif-ready .notif-icon-box {
            background: #dbeafe;
            color: #2563eb;
        }

        .notif-content {
            flex: 1;
            min-width: 0;
        }

        .notif-content h4 {
            font-size: 0.95rem;
            font-weight: 700;
            margin: 0 0 3px;
            display: flex;
            align-items: center;
            gap: 8px;
            flex-wrap: wrap;
        }

        .notif-tag {
            font-size: 0.68rem;
            text-transform: uppercase;
            letter-spacing: 0.5px;
            padding: 2px 7px;
            border-radius: 4px;
            font-weight: 700;
        }

        .notif-approved .notif-tag {
            background: #10b981;
            color: #ffffff;
        }

        .notif-ready .notif-tag {
            background: #2563eb;
            color: #ffffff;
        }

        .notif-content p {
            font-size: 0.84rem;
            margin: 0 0 5px;
            line-height: 1.5;
        }

        .notif-meta {
            font-size: 0.75rem;
            opacity: 0.85;
            display: flex;
            gap: 10px;
            align-items: center;
            flex-wrap: wrap;
        }

        .notif-actions {
            display: flex;
            align-items: center;
            justify-content: flex-end;
            gap: 8px;
        }

        .btn-notif-action {
            display: inline-flex;
            align-items: center;
            gap: 6px;
            padding: 7px 14px;
            font-size: 0.82rem;
            font-weight: 600;
            border-radius: 6px;
            text-decoration: none;
            transition: all 0.15s ease;
            white-space: nowrap;
        }

        .notif-approved .btn-notif-action {
            background: #047857;
            color: #ffffff;
            box-shadow: 0 2px 6px rgba(4, 120, 87, 0.25);
        }

        .notif-approved .btn-notif-action:hover {
            background: #065f46;
            color: #ffffff;
        }

        .notif-ready .btn-notif-action {
            background: #1d4ed8;
            color: #ffffff;
            box-shadow: 0 2px 6px rgba(29, 78, 216, 0.25);
        }

        .notif-ready .btn-notif-action:hover {
            background: #1e40af;
            color: #ffffff;
        }

        .notif-dropdown-empty {
            display: flex;
            align-items: flex-start;
            gap: 12px;
            padding: 0.75rem 0.5rem;
        }

        .notif-empty-icon {
            width: 42px;
            height: 42px;
            border-radius: 50%;
            background: #f0fdf4;
            border: 1px solid #bbf7d0;
            color: #166534;
            display: flex;
            align-items: center;
            justify-content: center;
            font-size: 1.15rem;
            flex-shrink: 0;
        }

        .notif-empty-content h4 {
            font-size: 0.94rem;
            font-weight: 700;
            color: #0f172a;
            margin: 0 0 4px;
        }

        .notif-empty-content p {
            font-size: 0.84rem;
            color: #475569;
            margin: 0;
            line-height: 1.5;
        }

        .notif-count-pill {
            background: #fef3c7;
            color: #92400e;
            font-size: 0.72rem;
            font-weight: 700;
            padding: 2px 8px;
            border-radius: 12px;
            letter-spacing: 0.3px;
        }

        .dash-table th.sn-col,
        .dash-table td.sn-col {
            width: 55px;
            text-align: center;
            font-weight: 700;
            color: #64748b;
        }

        @media (max-width: 992px) {
            .dash-stats-strip {
                grid-template-columns: repeat(2, 1fr);
            }
            .stat-item:nth-child(2) {
                border-right: none;
            }
            .stat-item:nth-child(1),
            .stat-item:nth-child(2) {
                border-bottom: 1px solid #f1f5f9;
            }
        }

        @media (max-width: 640px) {
            .dash-header-section {
                padding: 1.25rem 1rem;
            }
            .dash-notifications-section {
                padding: 1rem;
            }
            .dash-stats-strip {
                grid-template-columns: 1fr;
            }
            .stat-item {
                border-right: none;
                border-bottom: 1px solid #f1f5f9;
                padding: 1.15rem 1rem;
            }
            .stat-item:last-child {
                border-bottom: none;
            }
            .dash-active-card-section {
                padding: 1.25rem 1rem;
            }
            .dash-table-section {
                padding: 1.25rem 1rem;
            }
            .notif-empty-card {
                flex-direction: column;
                align-items: flex-start;
            }
        }
    </style>
</head>
<body>

    <?php require __DIR__ . '/includes/portal-header.php'; ?>

    <main class="dashboard-container">
        <?php $flash = getFlash(); if ($flash): ?>
            <div style="background: <?php echo $flash['type'] === 'success' ? '#ecfdf5' : '#fef2f2'; ?>; border: 1px solid <?php echo $flash['type'] === 'success' ? '#a7f3d0' : '#fecaca'; ?>; color: <?php echo $flash['type'] === 'success' ? '#065f46' : '#991b1b'; ?>; padding: 14px 18px; border-radius: 8px; margin-bottom: 20px; font-size: 0.9rem; display: flex; align-items: center; justify-content: space-between; box-shadow: 0 2px 6px rgba(0,0,0,0.04);">
                <div style="display: flex; align-items: center; gap: 10px;">
                    <i class="<?php echo $flash['type'] === 'success' ? 'fas fa-check-circle' : 'fas fa-exclamation-circle'; ?>" style="font-size: 1.15rem;"></i>
                    <span><?php echo $flash['message']; ?></span>
                </div>
                <button type="button" onclick="this.parentElement.remove()" style="background: none; border: none; font-size: 1.25rem; cursor: pointer; color: inherit; line-height: 1;" aria-label="Dismiss">&times;</button>
            </div>
        <?php endif; ?>

        <div class="dashboard-unified-card">
            <!-- Applicant Profile Header -->
            <div class="dash-header-section">
                <div class="profile-left">
                    <div class="profile-avatar">
                        <?php if (!empty($applicant['photo_path']) && file_exists(__DIR__ . '/' . $applicant['photo_path'])): ?>
                            <img src="<?php echo h($applicant['photo_path']); ?>" alt="Profile" style="width: 100%; height: 100%; object-fit: cover;">
                        <?php else: ?>
                            <span><?php echo h($initials); ?></span>
                        <?php endif; ?>
                    </div>
                    <div class="profile-info">
                        <div class="profile-title-row">
                            <h2 class="profile-name">Welcome <?php echo h($welcomeFullName); ?></h2>
                        </div>
                        <div class="profile-clock-row">
                            <span class="dash-live-clock"><i class="fas fa-clock"></i> <span id="liveClockText"><?php echo date('l, d M Y • h:i:s A'); ?></span></span>
                        </div>
                        <div class="profile-meta">
                            <span><i class="fas fa-envelope"></i> <?php echo h($applicant['email']); ?></span>
                            <?php if (!empty($applicant['phone'])): ?>
                                <span><i class="fas fa-phone"></i> <?php echo h($applicant['phone']); ?></span>
                            <?php endif; ?>
                        </div>
                    </div>
                </div>
                <div class="profile-actions">
                    <div class="notif-dropdown-wrapper">
                        <button type="button" class="btn-notif-bell" id="notifBellBtn" onclick="toggleNotificationDropdown()" aria-label="Notifications" aria-expanded="false" title="Official Status Notifications">
                            <i class="fas fa-bell"></i>
                            <?php if (!empty($applicantNotifications)): ?>
                                <span class="notif-bell-badge"><?php echo count($applicantNotifications); ?></span>
                            <?php endif; ?>
                        </button>
                        <div class="notif-dropdown-panel" id="notifDropdownPanel">
                            <div class="notif-dropdown-header">
                                <div style="display: flex; align-items: center; gap: 8px;">
                                    <i class="fas fa-bell" style="color: #d97706;"></i>
                                    <strong>Status Notifications</strong>
                                    <?php if (!empty($applicantNotifications)): ?>
                                        <span class="notif-count-pill"><?php echo count($applicantNotifications); ?> Update<?php echo count($applicantNotifications) > 1 ? 's' : ''; ?></span>
                                    <?php endif; ?>
                                </div>
                                <button type="button" class="btn-close-notif" onclick="closeNotificationDropdown()" aria-label="Close notifications">&times;</button>
                            </div>
                            <div class="notif-dropdown-body">
                                <?php if (!empty($applicantNotifications)): ?>
                                    <?php foreach ($applicantNotifications as $notif): ?>
                                        <div class="notif-banner notif-<?php echo $notif['type']; ?>">
                                            <div class="notif-left">
                                                <div class="notif-icon-box">
                                                    <i class="<?php echo $notif['icon']; ?>"></i>
                                                </div>
                                                <div class="notif-content">
                                                    <h4>
                                                        <?php echo h($notif['title']); ?>
                                                        <span class="notif-tag"><?php echo h($notif['tag']); ?></span>
                                                    </h4>
                                                    <p><?php echo $notif['message']; ?></p>
                                                    <?php if (!empty($notif['subtext'])): ?>
                                                        <p style="font-size: 0.8rem; color: #475569; margin-top: 4px; margin-bottom: 6px;">
                                                            <i class="fas fa-info-circle" style="color: #0284c7;"></i> <?php echo h($notif['subtext']); ?>
                                                        </p>
                                                    <?php endif; ?>
                                                    <div class="notif-meta">
                                                        <span><i class="fas fa-clock"></i> <?php echo h($notif['time']); ?></span>
                                                        <span>&bull;</span>
                                                        <span>Ref: <?php echo h($notif['app_num']); ?></span>
                                                    </div>
                                                </div>
                                            </div>
                                            <div class="notif-actions">
                                                <?php if (!empty($notif['action_onclick'])): ?>
                                                    <button type="button" onclick="<?php echo $notif['action_onclick']; ?>" class="btn-notif-action" style="cursor: pointer; border: none; font-family: inherit;">
                                                        <i class="<?php echo $notif['action_icon']; ?>"></i> <?php echo h($notif['action_label']); ?>
                                                    </button>
                                                <?php else: ?>
                                                    <a href="<?php echo h($notif['action_url']); ?>" <?php echo ($notif['type'] === 'approved') ? 'target="_blank"' : ''; ?> class="btn-notif-action">
                                                        <i class="<?php echo $notif['action_icon']; ?>"></i> <?php echo h($notif['action_label']); ?>
                                                    </a>
                                                <?php endif; ?>
                                            </div>
                                        </div>
                                    <?php endforeach; ?>
                                <?php else: ?>
                                    <div class="notif-dropdown-empty">
                                        <div class="notif-empty-icon">
                                            <i class="fas fa-bell"></i>
                                        </div>
                                        <div class="notif-empty-content">
                                            <h4>Status Notification Center</h4>
                                            <p>
                                                <?php if ($pendingAppsCount > 0): ?>
                                                    You have <strong><?php echo $pendingAppsCount; ?> application<?php echo $pendingAppsCount > 1 ? 's' : ''; ?> under processing</strong>. You will be automatically notified here as soon as <strong>approval has been given</strong> for your biometrics appointment and when your <strong>Residence Card is ready for collection</strong> at the enrollment center.
                                                <?php else: ?>
                                                    You will be automatically notified here as soon as <strong>approval has been given</strong> for your biometrics appointment and when your <strong>Residence Card is ready for collection</strong> at your designated enrollment center.
                                                <?php endif; ?>
                                            </p>
                                        </div>
                                    </div>
                                <?php endif; ?>
                            </div>
                        </div>
                    </div>
                    <a href="apply" class="btn-dash-action btn-dash-primary">
                        <i class="fas fa-plus-circle"></i> New Application
                    </a>
                </div>
            </div>

            <!-- Executive Stats KPI Strip -->
            <div class="dash-stats-strip">
                <div class="stat-item">
                    <div class="stat-icon-wrapper stat-icon-green">
                        <i class="fas fa-folder-open"></i>
                    </div>
                    <div class="stat-info">
                        <div class="stat-number"><?php echo $statTotal; ?></div>
                        <div class="stat-label">Total Applications</div>
                    </div>
                </div>
                <div class="stat-item">
                    <div class="stat-icon-wrapper stat-icon-blue">
                        <i class="fas fa-fingerprint"></i>
                    </div>
                    <div class="stat-info">
                        <div class="stat-number"><?php echo $statApproved; ?></div>
                        <div class="stat-label">Approved</div>
                    </div>
                </div>
                <div class="stat-item">
                    <div class="stat-icon-wrapper stat-icon-amber">
                        <i class="fas fa-hourglass-half"></i>
                    </div>
                    <div class="stat-info">
                        <div class="stat-number"><?php echo $statPending; ?></div>
                        <div class="stat-label">Under Processing</div>
                    </div>
                </div>
                <div class="stat-item">
                    <div class="stat-icon-wrapper stat-icon-gold">
                        <i class="fas fa-id-card"></i>
                    </div>
                    <div class="stat-info">
                        <div class="stat-number"><?php echo $statIssued; ?></div>
                        <div class="stat-label">Issued</div>
                    </div>
                </div>
            </div>

            <!-- Active Residence Card Summary Widget (if issued) -->
            <?php if ($activeCard): ?>
                <div class="dash-active-card-section">
                    <div class="section-header-row">
                        <h3><i class="fas fa-id-card" style="color: var(--nis-gold);"></i> Official Issued Residence Card</h3>
                        <span class="badge-status approved">Active Registry Dossier</span>
                    </div>
                    <div class="permit-card-widget">
                        <div>
                            <div style="font-size: 0.76rem; color: #94a3b8; text-transform: uppercase; letter-spacing: 0.5px;">Residence Card Number</div>
                            <div style="font-size: 1.5rem; font-weight: 800; font-family: monospace; color: #fef08a;">
                                No. <?php echo h($activeCard['card_number']); ?>
                            </div>
                            <div style="font-size: 0.84rem; color: #cbd5e1; margin-top: 4px;">
                                Issued: <strong><?php echo h($activeCard['issued_on']); ?></strong> &bull; Expires: <strong style="color: #fca5a5;"><?php echo h($activeCard['expires_on']); ?></strong>
                            </div>
                        </div>
                        <div>
                            <span class="badge-status approved" style="font-size: 0.85rem; padding: 8px 16px; border-radius: 6px; display: inline-flex; align-items: center; gap: 6px;">
                                <i class="fas fa-check-circle"></i> Active &amp; Valid Card
                            </span>
                        </div>
                    </div>
                </div>
            <?php endif; ?>

            <?php
            $totalRecords = count($applications) + ($hasDraft ? 1 : 0);
            ?>

            <!-- Saved Applications History -->
            <div class="dash-table-section">
                <div class="section-header-row">
                    <h3><i class="fas fa-folder-open" style="color: var(--nis-green);"></i> My Residence Permit Applications</h3>
                    <span style="font-size: 0.85rem; color: #64748b;">
                        Total Records: <strong><?php echo $totalRecords; ?></strong>
                    </span>
                </div>

                <?php if (empty($applications) && !$hasDraft): ?>
                    <div class="empty-history-box">
                        <i class="fas fa-file-invoice"></i>
                        <h4>No Applications on File</h4>
                        <p style="font-size: 0.9rem; margin-bottom: 1.5rem;">
                            You have not submitted a residence card application under this profile yet.
                        </p>
                        <a href="apply" class="btn-dash-action" style="background: #113f1f; color: #ffffff; padding: 11px 24px;">
                            <i class="fas fa-file-alt"></i> Start Residence Application
                        </a>
                    </div>
                <?php else: ?>
                    <div class="app-table-wrapper">
                        <table class="dash-table">
                            <thead>
                                <tr>
                                    <th class="sn-col">S/N</th>
                                    <th>Application No.</th>
                                    <th>Submission Date</th>
                                    <th>Center &amp; Appointment</th>
                                    <th>Status</th>
                                    <th style="text-align: right;">Actions</th>
                                </tr>
                            </thead>
                            <tbody>
                                <?php
                                $sn = 1;
                                if ($hasDraft):
                                ?>
                                    <tr style="background: #fffdf5;">
                                        <td class="sn-col"><?php echo $sn++; ?></td>
                                        <td>
                                            <strong style="font-family: monospace; font-size: 0.95rem; color: #b45309;">
                                                DRAFT-<?php echo str_pad($applicant['id'], 5, '0', STR_PAD_LEFT); ?>
                                            </strong>
                                            <div style="font-size: 0.74rem; color: #78350f; font-weight: 500;">
                                                Unfinished Application
                                            </div>
                                        </td>
                                        <td>
                                            <div style="font-weight: 600; color: #334155;">
                                                <?php echo !empty($draftInfo['saved_at']) ? date('d M Y', strtotime($draftInfo['saved_at'])) : date('d M Y'); ?>
                                            </div>
                                            <div style="font-size: 0.74rem; color: #b45309;">
                                                Saved Draft
                                            </div>
                                        </td>
                                        <td>
                                            <div style="font-weight: 600; color: #1e293b;">
                                                Step <?php echo $draftStep; ?> of 7
                                            </div>
                                            <div style="font-size: 0.76rem; color: #64748b;">
                                                <?php echo h($stepLabels[$draftStep] ?? "Step $draftStep"); ?>
                                            </div>
                                        </td>
                                        <td>
                                            <span class="badge-status draft">Draft</span>
                                        </td>
                                        <td style="text-align: right;">
                                            <div style="display: inline-flex; gap: 6px;">
                                                <a href="apply" class="btn-dash-action btn-dash-primary" style="padding: 7px 14px; font-size: 0.8rem;" title="Edit Application">
                                                    <i class="fas fa-edit"></i> Edit
                                                </a>
                                                <a href="apply?discard_draft=1" class="btn-dash-action" style="padding: 7px 10px; font-size: 0.8rem; color: #dc2626; border-color: #fca5a5;" onclick="return confirm('Are you sure you want to discard your saved application draft?');" title="Discard Draft">
                                                    <i class="fas fa-trash-alt"></i>
                                                </a>
                                            </div>
                                        </td>
                                    </tr>
                                <?php endif; ?>

                                <?php foreach ($applications as $app): ?>
                                    <?php
                                    $statusUpper = strtoupper($app['status']);
                                    $statusClass = 'pending';
                                    $statusText = 'Pending Approval';
                                    if ($statusUpper === 'APPROVED_FOR_BIOMETRICS') {
                                        $statusClass = 'approved';
                                        $statusText = 'Approved for Biometrics';
                                    } elseif ($statusUpper === 'READY_FOR_COLLECTION') {
                                        $statusClass = 'ready';
                                        $statusText = 'Ready for Collection';
                                    } elseif ($statusUpper === 'ISSUED') {
                                        $statusClass = 'approved';
                                        $statusText = 'Card Issued';
                                    } elseif ($statusUpper === 'QUERIED') {
                                        $statusClass = 'pending';
                                        $statusText = 'Queried (Action Required)';
                                    }
                                    ?>
                                    <tr>
                                        <td class="sn-col"><?php echo $sn++; ?></td>
                                        <td>
                                            <strong style="font-family: monospace; font-size: 0.95rem; color: #113f1f;">
                                                <?php echo h($app['application_number']); ?>
                                            </strong>
                                            <div style="font-size: 0.74rem; color: #64748b;">
                                                Ref: <?php echo h($app['reference_number'] ?? '—'); ?>
                                            </div>
                                        </td>
                                        <td>
                                            <div style="font-weight: 600; color: #334155;">
                                                <?php echo date('d M Y', strtotime($app['created_at'])); ?>
                                            </div>
                                            <div style="font-size: 0.74rem; color: #94a3b8;">
                                                <?php echo date('h:i A', strtotime($app['created_at'])); ?>
                                            </div>
                                        </td>
                                        <td>
                                            <div style="font-weight: 600; color: #1e293b;">
                                                <?php echo h($app['enrollment_center']); ?>
                                            </div>
                                            <div style="font-size: 0.76rem; color: #64748b;">
                                                <i class="fas fa-calendar-alt"></i> <?php echo h($app['appointment_date']); ?> (<?php echo h($app['appointment_time']); ?>)
                                            </div>
                                        </td>
                                        <td>
                                            <span class="badge-status <?php echo $statusClass; ?>">
                                                <?php echo h($statusText); ?>
                                            </span>
                                        </td>
                                        <td style="text-align: right;">
                                            <div style="display: inline-flex; gap: 6px; align-items: center;">
                                                <?php if ($statusUpper === 'QUERIED'): ?>
                                                    <?php
                                                    $queryData = [
                                                        'app_id' => (int)$app['id'],
                                                        'app_num' => $app['application_number'],
                                                        'notes' => !empty($app['approval_notes']) ? $app['approval_notes'] : 'One or more uploaded supporting documents are unclear, blurred, or require verification. Please review and re-upload a clear copy to proceed.',
                                                        'center' => !empty($app['enrollment_center']) ? $app['enrollment_center'] : 'NIS HQ, Airport Sauka Abuja',
                                                        'date' => !empty($app['appointment_date']) ? $app['appointment_date'] : '',
                                                        'passport' => $app['passport_number']
                                                    ];
                                                    ?>
                                                    <button type="button" class="btn-dash-action btn-dash-query" onclick='openQueryModal(<?php echo htmlspecialchars(json_encode($queryData), ENT_QUOTES, 'UTF-8'); ?>)' style="padding: 7px 13px; font-size: 0.8rem;" title="View Query Reason & Next Steps">
                                                        <i class="fas fa-eye"></i> View
                                                    </button>
                                                <?php endif; ?>
                                                <?php if ($statusUpper === 'APPROVED_FOR_BIOMETRICS' || $statusUpper === 'READY_FOR_COLLECTION' || $statusUpper === 'ISSUED'): ?>
                                                    <a href="print-application-slip?id=<?php echo (int)$app['id']; ?>" target="_blank" class="btn-dash-action" style="padding: 7px 12px; font-size: 0.8rem; background: #f0fdf4; color: #166534; border: 1px solid #bbf7d0;" title="Print Official Application Particulars Slip">
                                                        <i class="fas fa-file-invoice"></i> App Slip
                                                    </a>
                                                    <a href="print-appointment-slip?id=<?php echo (int)$app['id']; ?>" target="_blank" class="btn-dash-action btn-dash-gold" style="padding: 7px 12px; font-size: 0.8rem;" title="Print Official Biometrics Appointment Slip">
                                                        <i class="fas fa-print"></i> Appointment Slip
                                                    </a>
                                                <?php elseif ($statusUpper === 'PENDING' || $statusUpper === 'PENDING_APPROVAL' || $statusUpper === 'BIOMETRICS_CAPTURED'): ?>
                                                    <a href="print-application-slip?id=<?php echo (int)$app['id']; ?>" target="_blank" class="btn-dash-action" style="padding: 7px 12px; font-size: 0.8rem; background: #f0fdf4; color: #166534; border: 1px solid #bbf7d0;" title="Print Official Application Particulars Slip">
                                                        <i class="fas fa-file-invoice"></i> App Slip
                                                    </a>
                                                    <span style="font-size: 0.78rem; color: #94a3b8; font-weight: 500; display: inline-flex; align-items: center; gap: 4px;">
                                                        <i class="fas fa-hourglass-half"></i> In Review
                                                    </span>
                                                <?php endif; ?>
                                            </div>
                                        </td>
                                    </tr>
                                <?php endforeach; ?>
                            </tbody>
                        </table>
                    </div>
                <?php endif; ?>
            </div>
        </div>

        <!-- Query Reason & Resolution Modal -->
        <div id="queryReasonModal" class="query-modal-backdrop" onclick="handleQueryBackdropClick(event)">
            <div class="query-modal-dialog">
                <div class="query-modal-header">
                    <div class="query-modal-title">
                        <i class="fas fa-exclamation-triangle"></i>
                        <span>Application Query &amp; Resolution</span>
                    </div>
                    <button type="button" class="btn-query-modal-close" onclick="closeQueryModal()" aria-label="Close query modal">&times;</button>
                </div>
                <div class="query-modal-body">
                    <!-- Application Banner -->
                    <div class="query-app-banner">
                        <div>
                            <span style="font-size: 0.72rem; text-transform: uppercase; letter-spacing: 0.5px; color: #64748b; font-weight: 700; display: block; margin-bottom: 2px;">Application No.</span>
                            <span id="queryModalAppNum" style="font-family: monospace; font-weight: 800; font-size: 0.95rem; color: #0f172a;">---</span>
                        </div>
                        <div style="text-align: right;">
                            <span style="font-size: 0.72rem; text-transform: uppercase; letter-spacing: 0.5px; color: #64748b; font-weight: 700; display: block; margin-bottom: 2px;">Current Status</span>
                            <span style="display: inline-flex; align-items: center; gap: 5px; color: #b91c1c; font-weight: 700; background: #fee2e2; border: 1px solid #fca5a5; padding: 2px 10px; border-radius: 9999px; font-size: 0.74rem;">
                                <i class="fas fa-exclamation-circle"></i> Queried (Action Required)
                            </span>
                        </div>
                    </div>

                    <!-- 1. Reason for Query -->
                    <div class="query-reason-card">
                        <div class="query-reason-label">
                            <i class="fas fa-exclamation-circle"></i>
                            <span>Reason for Query</span>
                        </div>
                        <div id="queryModalReasonText" class="query-reason-text">
                            One or more uploaded supporting documents are unclear, blurred, or require verification.
                        </div>
                    </div>

                    <!-- 2. How to Resolve This Query -->
                    <div class="query-steps-title">
                        <i class="fas fa-tasks" style="color: #113f1f;"></i>
                        <span>How to Resolve This Query:</span>
                    </div>
                    <div class="query-steps-list">
                        <div class="query-step-item">
                            <span class="query-step-num">1</span>
                            <div>
                                <strong>Check Uploaded Documents:</strong> Queries usually relate to uploaded documents. Review the reason stated above to identify which document is unclear, blurred, or in doubt.
                            </div>
                        </div>
                        <div class="query-step-item">
                            <span class="query-step-num">2</span>
                            <div>
                                <strong>Re-upload Clear Copy:</strong> Re-upload a clear, legible replacement (PDF, JPG, or PNG under 5MB) for any document not clear or sure of, and resubmit for officer approval.
                            </div>
                        </div>
                    </div>

                    <!-- Direct Re-upload Form (Inline / Expandable) -->
                    <div id="queryReuploadFormWrap" style="display: none; background: #f8fafc; border: 1px solid #cbd5e1; border-radius: 8px; padding: 16px; margin-top: 12px; margin-bottom: 8px;">
                        <form method="POST" action="applicant-dashboard" enctype="multipart/form-data">
                            <?php echo csrf_field(); ?>
                            <input type="hidden" name="action_reupload_doc" value="1">
                            <input type="hidden" name="app_id" id="queryModalAppId" value="">
                            
                            <div style="font-weight: 700; font-size: 0.88rem; color: #0f172a; margin-bottom: 12px; display: flex; align-items: center; gap: 6px;">
                                <i class="fas fa-upload" style="color: #113f1f;"></i>
                                <span>Re-upload Replacement Supporting Document</span>
                            </div>
                            
                            <div style="margin-bottom: 12px;">
                                <label style="display: block; font-size: 0.8rem; font-weight: 700; color: #334155; margin-bottom: 4px;">
                                    Select Document Category <span style="color: #ef4444;">*</span>
                                </label>
                                <select name="doc_type" id="queryDocType" class="form-control" style="width: 100%; padding: 8px 10px; font-size: 0.85rem; border: 1px solid #cbd5e1; border-radius: 6px;" required>
                                    <option value="">-- Choose Document to Replace --</option>
                                    <option value="doc_passport_copy">International Passport Bio-data Page</option>
                                    <option value="doc_residence_visa">Valid Residence Visa / Entry Permit</option>
                                    <option value="doc_quota_approval">Expatriate Quota Approval / Letter</option>
                                    <option value="doc_domicile_proof">Proof of Domicile / Tenancy Agreement</option>
                                    <option value="doc_additional">Additional Supporting Document</option>
                                </select>
                            </div>

                            <div style="margin-bottom: 14px;">
                                <label style="display: block; font-size: 0.8rem; font-weight: 700; color: #334155; margin-bottom: 4px;">
                                    Choose Replacement File <span style="color: #ef4444;">*</span> <span style="font-weight: normal; color: #64748b; font-size: 0.76rem;">(PDF, JPG, PNG &bull; Max 5MB)</span>
                                </label>
                                <input type="file" name="replacement_file" id="queryReplacementFile" accept=".pdf,.jpg,.jpeg,.png" class="form-control" style="width: 100%; font-size: 0.82rem; padding: 6px; border: 1px solid #cbd5e1; border-radius: 6px;" required>
                            </div>

                            <div style="display: flex; gap: 10px; justify-content: flex-end; align-items: center;">
                                <button type="button" class="btn-dash-action" onclick="toggleReuploadForm(false)" style="padding: 7px 14px; font-size: 0.82rem;">
                                    Cancel
                                </button>
                                <button type="submit" class="btn-dash-action btn-dash-primary" style="padding: 7px 18px; font-size: 0.82rem;">
                                    <i class="fas fa-check"></i> Submit Replacement Document
                                </button>
                            </div>
                        </form>
                    </div>
                </div>
                <div class="query-modal-footer">
                    <button type="button" class="btn-dash-action" onclick="closeQueryModal()" style="padding: 9px 18px;">
                        Close
                    </button>
                    <button type="button" id="queryModalActionBtn" class="btn-dash-action btn-dash-primary" onclick="toggleReuploadForm(true)" style="padding: 9px 20px;">
                        <i class="fas fa-upload"></i> Re-upload Document
                    </button>
                </div>
            </div>
        </div>
    </main>

    <?php require __DIR__ . '/includes/portal-footer.php'; ?>

    <script>
    function toggleNotificationDropdown() {
        var panel = document.getElementById('notifDropdownPanel');
        var btn = document.getElementById('notifBellBtn');
        if (!panel) return;
        var isShowing = panel.classList.contains('show');
        if (isShowing) {
            panel.classList.remove('show');
            if (btn) btn.setAttribute('aria-expanded', 'false');
        } else {
            panel.classList.add('show');
            if (btn) btn.setAttribute('aria-expanded', 'true');
        }
    }

    function closeNotificationDropdown() {
        var panel = document.getElementById('notifDropdownPanel');
        var btn = document.getElementById('notifBellBtn');
        if (panel) {
            panel.classList.remove('show');
            if (btn) btn.setAttribute('aria-expanded', 'false');
        }
    }

    // Query Resolution Modal
    function openQueryModal(data) {
        if (!data) return;
        var modal = document.getElementById('queryReasonModal');
        var appNum = document.getElementById('queryModalAppNum');
        var reason = document.getElementById('queryModalReasonText');
        var appId = document.getElementById('queryModalAppId');
        var formWrap = document.getElementById('queryReuploadFormWrap');
        var actionBtn = document.getElementById('queryModalActionBtn');
        
        if (appNum) appNum.textContent = data.app_num || '---';
        if (reason) reason.textContent = (data.notes && data.notes.trim() !== '') ? data.notes : 'One or more uploaded supporting documents are unclear, blurred, or require verification. Please review and re-upload a clear copy to proceed.';
        if (appId && data.app_id) appId.value = data.app_id;
        
        if (formWrap) formWrap.style.display = 'none';
        if (actionBtn) actionBtn.style.display = 'inline-flex';
        
        if (modal) {
            modal.style.display = 'flex';
            document.body.style.overflow = 'hidden';
        }
    }

    function toggleReuploadForm(show) {
        var wrap = document.getElementById('queryReuploadFormWrap');
        var btn = document.getElementById('queryModalActionBtn');
        if (!wrap) return;
        if (show) {
            wrap.style.display = 'block';
            if (btn) btn.style.display = 'none';
            wrap.scrollIntoView({ behavior: 'smooth', block: 'nearest' });
        } else {
            wrap.style.display = 'none';
            if (btn) btn.style.display = 'inline-flex';
        }
    }

    function closeQueryModal() {
        var modal = document.getElementById('queryReasonModal');
        if (modal) {
            modal.style.display = 'none';
            document.body.style.overflow = '';
        }
    }

    function handleQueryBackdropClick(e) {
        if (e.target.id === 'queryReasonModal') {
            closeQueryModal();
        }
    }

    // Live Real-Time Clock
    function updateLiveClock() {
        var el = document.getElementById('liveClockText');
        if (!el) return;
        var now = new Date();
        var days = ['Sunday', 'Monday', 'Tuesday', 'Wednesday', 'Thursday', 'Friday', 'Saturday'];
        var months = ['Jan', 'Feb', 'Mar', 'Apr', 'May', 'Jun', 'Jul', 'Aug', 'Sep', 'Oct', 'Nov', 'Dec'];
        var dayName = days[now.getDay()];
        var day = String(now.getDate()).padStart(2, '0');
        var month = months[now.getMonth()];
        var year = now.getFullYear();
        var hours24 = now.getHours();
        var ampm = hours24 >= 12 ? 'PM' : 'AM';
        var hours12 = hours24 % 12;
        hours12 = hours12 ? hours12 : 12;
        var hours = String(hours12).padStart(2, '0');
        var minutes = String(now.getMinutes()).padStart(2, '0');
        var seconds = String(now.getSeconds()).padStart(2, '0');
        el.textContent = dayName + ', ' + day + ' ' + month + ' ' + year + ' • ' + hours + ':' + minutes + ':' + seconds + ' ' + ampm;
    }

    updateLiveClock();
    setInterval(updateLiveClock, 1000);

    document.addEventListener('click', function(e) {
        var wrapper = document.querySelector('.notif-dropdown-wrapper');
        if (wrapper && !wrapper.contains(e.target)) {
            closeNotificationDropdown();
        }
    });

    document.addEventListener('keydown', function(e) {
        if (e.key === 'Escape') {
            closeQueryModal();
            closeNotificationDropdown();
        }
    });
    </script>
</body>
</html>
