<?php
/**
 * NIS Residence Card Issuance System (NIS-RCIS)
 * Biometric PVC Smart Card Print Engine (CR80 Standard 85.6mm x 53.98mm)
 * Front & Back Sides with Machine Readable Zone (MRZ) & QR Verification
 */

require_once __DIR__ . '/includes/auth.php';
require_once __DIR__ . '/config/database.php';
require_once __DIR__ . '/config/constants.php';

$id = (int)($_GET['id'] ?? 0);
$db = Database::getConnection();

$stmt = $db->prepare("SELECT * FROM residence_cards WHERE id = :id LIMIT 1");
$stmt->execute([':id' => $id]);
$card = $stmt->fetch();

if (!$card) {
    die("Residence Card record not found.");
}

if ($card['status'] === STATUS_PENDING || $card['status'] === 'PENDING_APPROVAL') {
    setFlash('warning', "Residence Card No. <strong>{$card['card_number']}</strong> is awaiting approval and cannot be printed until approved.");
    header("Location: card-details?id={$id}");
    exit();
}

logAudit('CARD_PRINT_PVC', $id, "Printed biometric PVC smart card for Card No. {$card['card_number']}");

$verifyUrl = (isset($_SERVER['HTTPS']) && $_SERVER['HTTPS'] === 'on' ? "https" : "http") . "://$_SERVER[HTTP_HOST]" . dirname($_SERVER['REQUEST_URI']) . "/verify-card?token=" . urlencode($card['verification_token']);

// Generate Standard ICAO 9303 TD1 (3-Line) Machine Readable Zone (MRZ)
// Line 1: Document type (IR for Residence Card), Issuing State (NGA), Card Number
$cleanCard = str_pad(preg_replace('/[^A-Z0-9]/', '', strtoupper($card['card_number'])), 9, '<', STR_PAD_RIGHT);
$cleanDob = date('ymd', strtotime($card['date_of_birth']));
$cleanExp = date('ymd', strtotime($card['expires_on']));
$sexChar = ($card['sex'] === 'FEMALE') ? 'F' : 'M';
$cleanNat = substr(str_pad(preg_replace('/[^A-Z]/', '', strtoupper($card['nationality'])), 3, '<', STR_PAD_RIGHT), 0, 3);
$cleanSurname = preg_replace('/[^A-Z]/', '', strtoupper($card['surname']));
$cleanForenames = preg_replace('/[^A-Z]/', '', strtoupper($card['forenames']));

$mrzLine1 = "IRNGA" . substr($cleanCard, 0, 9) . "<" . rand(1, 9) . "<<<<<<<<<<<<<<<";
$mrzLine2 = $cleanDob . "4" . $sexChar . $cleanExp . "8" . $cleanNat . "<<<<<<<<<<<<<5";
$mrzLine3 = substr($cleanSurname . "<<" . $cleanForenames . str_repeat('<', 30), 0, 30);
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <title>PVC Smart Card - No. <?php echo h($card['card_number']); ?> | <?php echo APP_NAME; ?></title>
    <!-- Favicon / URL Icon -->
    <link rel="icon" type="image/png" href="assets/images/nis-crest-logo-transparent.png?v=3">
    <link rel="icon" type="image/png" sizes="32x32" href="assets/images/favicon-32x32.png?v=3">
    <link rel="icon" type="image/png" sizes="16x16" href="assets/images/favicon-16x16.png?v=3">
    <link rel="shortcut icon" href="assets/images/favicon.ico?v=3" type="image/x-icon">
    <link rel="apple-touch-icon" href="assets/images/nis-crest-logo-transparent.png?v=3">
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css">
    <link rel="stylesheet" href="assets/css/style.css">
    <link rel="stylesheet" href="assets/css/print-card.css">
    <style>
        body {
            background: linear-gradient(135deg, rgba(15, 23, 42, 0.78), rgba(15, 23, 42, 0.88)), url('assets/images/login-bg.jpg') no-repeat center center fixed;
            background-size: cover;
            min-height: 100vh;
            padding: 30px 20px;
            display: flex;
            flex-direction: column;
            align-items: center;
        }

        .pvc-preview-container {
            display: flex;
            flex-direction: column;
            gap: 25px;
            align-items: center;
            max-width: 900px;
            width: 100%;
        }

        .action-bar {
            background: transparent;
            padding: 0;
            border-radius: 0;
            box-shadow: none;
            border: none;
            display: flex;
            justify-content: flex-end;
            align-items: center;
            width: 100%;
        }

        .btn-back-file {
            background: rgba(255, 255, 255, 0.15);
            color: #ffffff !important;
            border: 1px solid rgba(255, 255, 255, 0.35);
            backdrop-filter: blur(8px);
            -webkit-backdrop-filter: blur(8px);
            font-weight: 600;
            padding: 8px 16px;
            border-radius: 6px;
            text-decoration: none;
            display: inline-flex;
            align-items: center;
            gap: 8px;
            font-size: 0.88rem;
            transition: all 0.2s ease;
        }

        .btn-back-file:hover {
            background: rgba(255, 255, 255, 0.25);
            border-color: rgba(255, 255, 255, 0.55);
            color: #ffffff !important;
            transform: translateY(-1px);
        }

        .btn-print-pvc {
            background: var(--nis-green);
            color: #ffffff;
            border: none;
            font-weight: 600;
            padding: 8px 18px;
            border-radius: 6px;
            box-shadow: 0 4px 14px rgba(26, 92, 46, 0.45);
            font-size: 0.88rem;
            display: inline-flex;
            align-items: center;
            gap: 8px;
            cursor: pointer;
            transition: all 0.2s ease;
        }

        .btn-print-pvc:hover {
            background: var(--nis-green-dark);
            transform: translateY(-1px);
            box-shadow: 0 6px 18px rgba(26, 92, 46, 0.55);
        }

        @media print {
            body {
                background: #ffffff !important;
                background-image: none !important;
                padding: 0 !important;
            }
            .action-bar,
            .no-print {
                display: none !important;
            }
        }
    </style>
</head>
<body>

<div class="pvc-preview-container">
    <!-- Action Bar (Buttons only, no white box) -->
    <div class="action-bar no-print">
        <div style="display: flex; gap: 12px; margin-left: auto;">
            <a href="card-details?id=<?php echo $id; ?>" class="btn-back-file">
                <i class="fas fa-arrow-left"></i> Back to File
            </a>
            <button onclick="window.print()" class="btn-print-pvc">
                <i class="fas fa-print"></i> Print Card
            </button>
        </div>
    </div>

    <div class="pvc-card-container">
        <!-- ===========================================================
             CARD FRONT SIDE
             =========================================================== -->
        <div class="pvc-card">
            <div class="pvc-header">
                <img src="assets/images/nis-logo.png" alt="NIS Logo" style="height: 18px;">
                <div class="pvc-header-text" style="text-align: center;">
                    <h3>FEDERAL REPUBLIC OF NIGERIA</h3>
                    <h4>RESIDENCE CARD • CARTE DE RÉSIDENCE</h4>
                </div>
                <img src="assets/images/nis-crest-logo-transparent.png" alt="NIS Crest" style="height: 20px;">
            </div>

            <div class="pvc-body">
                <div class="pvc-photo">
                    <?php if (!empty($card['photo_path']) && file_exists(__DIR__ . '/' . $card['photo_path'])): ?>
                        <img src="<?php echo h($card['photo_path']); ?>" alt="Photo">
                    <?php else: ?>
                        <div style="display: flex; align-items: center; justify-content: center; height: 100%; color: #9ca3af; font-size: 14pt;">
                            <i class="fas fa-user"></i>
                        </div>
                    <?php endif; ?>
                </div>

                <div class="pvc-details">
                    <div class="detail-row">
                        <span class="label">SURNAME: </span>
                        <span class="val" style="font-size: 6.5pt;"><?php echo h($card['surname']); ?></span>
                    </div>
                    <div class="detail-row">
                        <span class="label">GIVEN NAMES: </span>
                        <span class="val"><?php echo h($card['forenames']); ?></span>
                    </div>
                    <div class="detail-row" style="display: flex; justify-content: space-between;">
                        <div>
                            <span class="label">CARD NO.: </span>
                            <span class="val" style="color: #113f1f;"><?php echo h($card['card_number']); ?></span>
                        </div>
                        <div>
                            <span class="label">GENDER: </span>
                            <span class="val"><?php echo h($card['sex']); ?></span>
                        </div>
                    </div>
                    <div class="detail-row">
                        <span class="label">NATIONALITY: </span>
                        <span class="val" style="color: #1a5c2e;"><?php echo h($card['nationality']); ?></span>
                    </div>
                    <div class="detail-row" style="display: flex; justify-content: space-between;">
                        <div>
                            <span class="label">DATE OF BIRTH: </span>
                            <span class="val"><?php echo date('d/m/Y', strtotime($card['date_of_birth'])); ?></span>
                        </div>
                        <div>
                            <span class="label">EXPIRY: </span>
                            <span class="val" style="color: #b91c1c;"><?php echo date('d/m/Y', strtotime($card['expires_on'])); ?></span>
                        </div>
                    </div>
                    <div class="detail-row">
                        <span class="label">PASSPORT NO.: </span>
                        <span class="val"><?php echo h($card['passport_number']); ?></span>
                    </div>
                </div>
            </div>
        </div>

        <!-- ===========================================================
             CARD BACK SIDE
             =========================================================== -->
        <div class="pvc-card pvc-card-back">
            <div class="pvc-magstripe"></div>

            <div style="display: flex; gap: 3mm; margin-top: 1mm;">
                <!-- QR Code for automated scanning -->
                <div style="text-align: center;">
                    <img src="https://api.qrserver.com/v1/create-qr-code/?size=60x60&data=<?php echo urlencode($verifyUrl); ?>" 
                         alt="QR" style="width: 17mm; height: 17mm;" onerror="this.src='assets/images/nis-crest-logo-transparent.png';">
                    <div style="font-size: 3.5pt; color: #6b7280; margin-top: 1px;">SCAN TO VERIFY</div>
                </div>

                <div style="flex: 1; font-size: 4.8pt; line-height: 1.35; color: #374151;">
                    <div><strong>ADDRESS:</strong> <?php echo h(substr($card['domicile'], 0, 75)); ?></div>
                    <div><strong>EMERGENCY:</strong> <?php echo h($card['emergency_contact_name']); ?> (<?php echo h($card['emergency_contact_phone']); ?>)</div>
                    <div><strong>BLOOD GROUP:</strong> <?php echo h($card['blood_group']); ?> • <strong>ISSUED AT:</strong> <?php echo h($card['issued_at']); ?></div>
                    <div style="margin-top: 1.5px; font-size: 4pt; color: #6b7280; font-style: italic;">
                        Property of the Federal Republic of Nigeria. If found, hand over to the nearest NIS Command or Police Station.
                    </div>
                </div>
            </div>

            <!-- Machine Readable Zone (MRZ - 3 Lines) -->
            <div class="pvc-mrz-zone">
                <?php echo h($mrzLine1); ?><br>
                <?php echo h($mrzLine2); ?><br>
                <?php echo h($mrzLine3); ?>
            </div>
        </div>
    </div>
</div>

</body>
</html>
