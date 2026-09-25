<?php
/**
 * NIS Residence Card Issuance System (NIS-RCIS)
 * Official Approved Application & Biometrics Slip (Print View)
 * Presented by applicant on appointment day for physical capturing
 */

require_once __DIR__ . '/includes/security.php';
require_once __DIR__ . '/config/database.php';
require_once __DIR__ . '/config/constants.php';

$db = Database::getConnection();
$appId = (int)($_GET['app_id'] ?? $_GET['id'] ?? 0);
$appNum = trim($_GET['app_num'] ?? $_GET['app'] ?? '');

$application = null;
if ($appId > 0) {
    $stmt = $db->prepare("SELECT * FROM applications WHERE id = :id LIMIT 1");
    $stmt->execute([':id' => $appId]);
    $application = $stmt->fetch(PDO::FETCH_ASSOC);
} elseif (!empty($appNum)) {
    $stmt = $db->prepare("SELECT * FROM applications WHERE application_number = :an1 OR reference_number = :an2 LIMIT 1");
    $stmt->execute([':an1' => $appNum, ':an2' => $appNum]);
    $application = $stmt->fetch(PDO::FETCH_ASSOC);
}

if (!$application) {
    die("Application file not found. Please verify your Application ID or Tracking Number.");
}

$trackingNo = $application['application_number'] ?: ('RC-' . str_pad((string)$application['id'], 8, '0', STR_PAD_LEFT));
$appRefId = $application['reference_number'] ?: ($application['payment_reference'] ?: ('REF-' . date('Ymd', strtotime($application['created_at'])) . '-' . $application['id']));
$appIdDisplay = '170977' . str_pad((string)$application['id'], 6, '0', STR_PAD_LEFT);
$applicantFullName = strtoupper(trim($application['surname'] . ' ' . $application['forenames']));
$requestDate = !empty($application['created_at']) ? date('Y-m-d H:i:s', strtotime($application['created_at'])) : date('Y-m-d H:i:s');
$appointmentDateDisplay = !empty($application['appointment_date']) ? (date('Y-m-d', strtotime($application['appointment_date'])) . ' ' . ($application['appointment_time'] ?? '09:00 AM')) : '2026-09-03 09:00 AM';
$centerAddress = 'NIS HQ, Airport Sauka Abuja.';
$nationality = $application['nationality'] ?? '—';
$passportNumber = $application['passport_number'] ?? '—';
$statusDisplay = ($application['status'] === 'APPROVED_FOR_BIOMETRICS') ? 'Approved for Biometrics' : 'Pending Approval';
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Appointment Slip — Nigeria Immigration Service</title>
    <link rel="icon" type="image/png" href="assets/images/nis-crest-logo-transparent.png?v=3">
    <link rel="shortcut icon" href="assets/images/favicon.ico?v=3" type="image/x-icon">
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css">
    <style>
        * {
            margin: 0;
            padding: 0;
            box-sizing: border-box;
            font-family: Inter, -apple-system, BlinkMacSystemFont, "Segoe UI", Roboto, sans-serif;
        }

        body {
            background-color: #f8fafc;
            color: #0f172a;
            padding: 24px 16px;
        }

        /* Success Card (Aligned 100% with apply.php appointment slip) */
        .success-card {
            background: #ffffff;
            border-radius: 12px;
            border: none;
            box-shadow: 0 4px 16px rgba(0, 0, 0, 0.06);
            padding: 2.25rem 2.5rem;
            max-width: 760px;
            margin: 0 auto;
            position: relative;
        }

        .success-header-wrapper {
            text-align: center;
            margin-bottom: 0.85rem;
        }

        .success-crest-logo {
            width: 60px;
            height: auto;
            margin-bottom: 0.4rem;
            display: inline-block;
        }

        .success-agency-name {
            font-size: 1.15rem;
            font-weight: 800;
            color: #0b6623;
            text-transform: uppercase;
            letter-spacing: 0.5px;
            margin: 0 0 2px;
        }

        .success-doc-title {
            font-size: 1.05rem;
            font-weight: 700;
            color: #0f172a;
            margin: 0 0 0.5rem;
        }

        .success-subtitle {
            color: #64748b;
            max-width: 640px;
            margin: 0 auto 0.75rem;
            font-size: 0.86rem;
            line-height: 1.5;
            text-align: center;
        }

        .success-barcode-wrap {
            display: flex;
            justify-content: flex-end;
            margin: 0.5rem 0 0.75rem;
        }

        .success-sec-title {
            font-size: 1.02rem;
            font-weight: 800;
            color: #0f172a;
            padding-bottom: 4px;
            border-bottom: 1px solid #d1d5db;
            margin: 1.25rem 0 0.75rem;
        }

        .success-dl {
            display: flex;
            flex-direction: column;
            gap: 6px;
            margin-bottom: 0.75rem;
            font-size: 0.88rem;
        }

        .success-dl-row {
            display: flex;
            flex-wrap: wrap;
            align-items: baseline;
            gap: 8px;
            line-height: 1.4;
        }

        .success-dl-label {
            font-weight: 700;
            color: #0f172a;
            min-width: 205px;
        }

        .success-dl-val {
            color: #1e293b;
            font-weight: 600;
        }

        .font-mono {
            font-family: ui-monospace, SFMono-Regular, Menlo, Monaco, Consolas, monospace;
        }

        .success-status-unboxed {
            color: #92400e;
            font-weight: 700;
            background: none !important;
            padding: 0 !important;
            border-radius: 0 !important;
            display: inline !important;
        }

        /* Instructions matching official slip */
        .success-instructions {
            margin-top: 1.25rem;
            font-size: 0.86rem;
            color: #1e293b;
            line-height: 1.55;
        }

        .success-instructions-title {
            font-size: 1.02rem;
            font-weight: 800;
            color: #0f172a;
            margin-bottom: 0.5rem;
        }

        .success-instructions ul {
            list-style: none;
            padding: 0;
            margin: 0;
        }

        .success-instructions li {
            margin-bottom: 8px;
            position: relative;
            padding-left: 1.25rem;
        }

        .success-instructions li::before {
            content: "•";
            position: absolute;
            left: 0;
            top: 0;
            font-weight: bold;
            font-size: 1.1rem;
        }

        .success-instructions ol {
            margin-top: 4px;
            margin-left: 1.5rem;
            padding: 0;
        }

        .success-instructions ol li {
            padding-left: 0;
            margin-bottom: 4px;
        }

        .success-instructions ol li::before {
            display: none;
        }

        /* Screen Action Controls (Hidden in Print) */
        .screen-actions-bar {
            max-width: 760px;
            margin: 1.25rem auto 0;
            display: flex;
            justify-content: center;
            align-items: center;
            gap: 12px;
        }

        .btn-action-print {
            background: #1a5c2e;
            color: #ffffff;
            border: none;
            padding: 11px 28px;
            font-size: 0.92rem;
            font-weight: 700;
            border-radius: 6px;
            cursor: pointer;
            display: inline-flex;
            align-items: center;
            gap: 8px;
            box-shadow: 0 4px 14px rgba(26, 92, 46, 0.25);
            transition: all 0.2s ease;
        }

        .btn-action-print:hover {
            background: #113f1f;
            box-shadow: 0 6px 18px rgba(26, 92, 46, 0.35);
        }

        .btn-action-close {
            background: #ffffff;
            color: #4b5563;
            border: 1px solid #d1d5db;
            padding: 10px 20px;
            font-size: 0.92rem;
            font-weight: 600;
            border-radius: 6px;
            cursor: pointer;
            transition: all 0.2s ease;
        }

        .btn-action-close:hover {
            background: #f3f4f6;
            color: #111827;
        }

        @page {
            size: A4 portrait;
            margin: 15mm 18mm 15mm 18mm;
        }

        @media print {
            body {
                background: #ffffff !important;
                padding: 0 !important;
                color: #000000 !important;
            }

            .success-card {
                box-shadow: none !important;
                border: none !important;
                padding: 1.5rem !important;
                max-width: 100% !important;
            }

            .screen-actions-bar {
                display: none !important;
            }

            a {
                color: inherit !important;
                text-decoration: underline !important;
            }
        }
    </style>
</head>
<body>

    <!-- Official NIS Appointment Slip Sheet (Identical to apply.php) -->
    <div class="success-card">
        <!-- Top Status Header -->
        <div class="success-header-wrapper">
            <img src="assets/images/nis-crest-logo-transparent.png?v=3" alt="NIS Crest" class="success-crest-logo" onerror="this.src='assets/images/nis-logo.png';">
            <div class="success-agency-name">NIGERIA IMMIGRATION SERVICE</div>
            <div class="success-doc-title">Appointment Slip</div>
            <p class="success-subtitle">
                Your application has been received and forwarded for Approval. Once approved, you will be required to print your application Slip for your Biometrics.
            </p>
        </div>

        <!-- Top-Right Barcode -->
        <div class="success-barcode-wrap">
            <?php echo generateBarcodeSvg($trackingNo, 44, 210); ?>
        </div>

        <!-- 1. Appointment Details -->
        <div class="success-sec-title">Appointment Details</div>
        <div class="success-dl">
            <div class="success-dl-row">
                <span class="success-dl-label">Application ID:</span>
                <span class="success-dl-val font-mono" style="color: #0b6623; font-weight: 800;"><?php echo h($trackingNo); ?></span>
            </div>
            <div class="success-dl-row">
                <span class="success-dl-label">Appointment Request Date:</span>
                <span class="success-dl-val"><?php echo h($requestDate); ?></span>
            </div>
            <div class="success-dl-row">
                <span class="success-dl-label">Appointment Date:</span>
                <span class="success-dl-val"><?php echo h($appointmentDateDisplay); ?></span>
            </div>
            <div class="success-dl-row">
                <span class="success-dl-label">Appointment for:</span>
                <span class="success-dl-val">Residence Card</span>
            </div>
            <div class="success-dl-row">
                <span class="success-dl-label">Center Address:</span>
                <span class="success-dl-val"><?php echo h($centerAddress); ?></span>
            </div>
        </div>

        <!-- 2. Personal & Contact Details -->
        <div class="success-sec-title">Personal &amp; Contact Details</div>
        <div class="success-dl">
            <div class="success-dl-row">
                <span class="success-dl-label">Reference ID:</span>
                <span class="success-dl-val font-mono"><?php echo h($appRefId); ?></span>
            </div>
            <div class="success-dl-row">
                <span class="success-dl-label">Name:</span>
                <span class="success-dl-val" style="text-transform: uppercase; font-weight: 700;">
                    <?php echo h($applicantFullName); ?>
                </span>
            </div>
        </div>

        <!-- 3. Instructions -->
        <div class="success-instructions">
            <div class="success-instructions-title">Instructions</div>
            <ul>
                <li>Ensure that you verify the status of the application on the portal prior to your appointment date. You can verify your status: <a href="track?app_num=<?php echo urlencode($trackingNo); ?>&passport=<?php echo urlencode($passportNumber); ?>" style="color: #0b6623; font-weight: 700; text-decoration: underline;">here</a></li>
                <li>You must bring the following items for the appointment:
                    <ol>
                        <li>Vetted application form</li>
                        <li>Printout of appointment slip</li>
                        <li>For reissue, the old residence card is mandatory.</li>
                    </ol>
                </li>
            </ul>
        </div>
    </div>

    <!-- On-screen Action Toolbar -->
    <div class="screen-actions-bar">
        <button type="button" onclick="window.print()" class="btn-action-print">
            <i class="fas fa-print"></i> Print Slip
        </button>
        <button type="button" onclick="window.close()" class="btn-action-close">
            Close Window
        </button>
    </div>

    <?php if (!empty($_GET['print'])): ?>
    <script>
        window.addEventListener('load', function() {
            setTimeout(function() {
                window.print();
            }, 300);
        });
    </script>
    <?php endif; ?>

</body>
</html>
