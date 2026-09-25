<?php
/**
 * NIS Residence Card Issuance System (NIS-RCIS)
 * Official A4 Residence Permit Certificate Print Engine
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

logAudit('CARD_PRINT_CERTIFICATE', $id, "Printed official residence certificate for Card No. {$card['card_number']}");

$verifyUrl = (isset($_SERVER['HTTPS']) && $_SERVER['HTTPS'] === 'on' ? "https" : "http") . "://$_SERVER[HTTP_HOST]" . dirname($_SERVER['REQUEST_URI']) . "/verify?token=" . urlencode($card['verification_token']);
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <title>Residence Certificate - No. <?php echo h($card['card_number']); ?> | <?php echo APP_NAME; ?></title>
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
            background-color: #525659;
            padding: 30px;
            display: flex;
            flex-direction: column;
            align-items: center;
        }

        .cert-container {
            width: 210mm;
            min-height: 297mm;
            background: #ffffff;
            padding: 20mm;
            box-sizing: border-box;
            box-shadow: 0 10px 30px rgba(0,0,0,0.3);
            position: relative;
            border: 4px double #1a5c2e;
            display: flex;
            flex-direction: column;
            justify-content: space-between;
        }

        .cert-container::before {
            content: '';
            position: absolute;
            top: 5mm; left: 5mm; right: 5mm; bottom: 5mm;
            border: 1px solid #d4af37;
            pointer-events: none;
        }

        @media print {
            body { background: #ffffff !important; padding: 0 !important; }
            .cert-container { box-shadow: none !important; margin: 0 auto; width: 100% !important; border: 4px double #1a5c2e !important; }
        }
    </style>
</head>
<body>

<div style="width: 210mm; margin-bottom: 20px; display: flex; justify-content: space-between; align-items: center; background: #ffffff; padding: 12px 20px; border-radius: 8px;" class="no-print">
    <div>
        <strong>Official A4 Residence Certificate</strong> • Card No. <?php echo h($card['card_number']); ?>
    </div>
    <div style="display: flex; gap: 10px;">
        <a href="card-details?id=<?php echo $id; ?>" class="btn btn-outline btn-sm">
            <i class="fas fa-arrow-left"></i> Back to File
        </a>
        <button onclick="window.print()" class="btn btn-primary btn-sm">
            <i class="fas fa-print"></i> Print Certificate (A4 Portrait)
        </button>
    </div>
</div>

<div class="cert-container">
    <!-- Header -->
    <div style="text-align: center;">
        <img src="assets/images/nis-logo.png" alt="NIS Logo" style="height: 75px; margin-bottom: 10px;">
        <h1 style="font-size: 18pt; color: #113f1f; margin: 0; font-family: 'Times New Roman', serif; letter-spacing: 1px;">
            FEDERAL REPUBLIC OF NIGERIA
        </h1>
        <h2 style="font-size: 13pt; color: #1a5c2e; margin: 5px 0; font-family: Arial, sans-serif;">
            NIGERIA IMMIGRATION SERVICE
        </h2>
        <div style="font-size: 9.5pt; color: #d4af37; font-weight: bold; letter-spacing: 2px;">
            DIRECTORATE OF VISA AND RESIDENCY
        </div>
        <div style="width: 80%; height: 2px; background: linear-gradient(90deg, transparent, #1a5c2e, transparent); margin: 15px auto;"></div>
        <h3 style="font-size: 15pt; color: #000000; letter-spacing: 2px; text-transform: uppercase; font-family: 'Times New Roman', serif; margin-top: 10px;">
            CERTIFICATE OF RESIDENCE STATUS
        </h3>
    </div>

    <!-- Body Information -->
    <div style="margin: 25px 0;">
        <div style="display: flex; gap: 20px; align-items: flex-start; margin-bottom: 25px;">
            <div style="width: 38mm; height: 48mm; border: 2px solid #1a5c2e; padding: 2px; background: #f8fafc; border-radius: 4px; overflow: hidden; flex-shrink: 0;">
                <?php if (!empty($card['photo_path']) && file_exists(__DIR__ . '/' . $card['photo_path'])): ?>
                    <img src="<?php echo h($card['photo_path']); ?>" alt="Photo" style="width: 100%; height: 100%; object-fit: cover;">
                <?php else: ?>
                    <div style="display: flex; align-items: center; justify-content: center; height: 100%; color: #94a3b8; font-size: 2rem;">
                        <i class="fas fa-user"></i>
                    </div>
                <?php endif; ?>
            </div>

            <div style="flex: 1; font-size: 10.5pt; line-height: 1.8;">
                <p style="margin-bottom: 12px;">
                    This is to certify that the individual named below has been lawfully granted Residence Status in the Federal Republic of Nigeria, subject to immigration laws and official conditions:
                </p>
                <table style="width: 100%; font-size: 10.5pt; border-collapse: collapse;">
                    <tr>
                        <td style="width: 35%; font-weight: bold; color: #374151;">Full Legal Name:</td>
                        <td style="font-weight: 800; color: #113f1f; font-size: 11.5pt; text-transform: uppercase;">
                            <?php echo h($card['surname'] . ', ' . $card['forenames']); ?>
                        </td>
                    </tr>
                    <tr>
                        <td style="font-weight: bold; color: #374151;">Residence Card No.:</td>
                        <td style="font-family: monospace; font-weight: bold; font-size: 12pt; color: #b91c1c;">
                            No. <?php echo h($card['card_number']); ?> (<?php echo h($card['booklet_number']); ?>)
                        </td>
                    </tr>
                    <tr>
                        <td style="font-weight: bold; color: #374151;">Nationality:</td>
                        <td style="font-weight: 700; color: #1a5c2e;"><?php echo h($card['nationality']); ?></td>
                    </tr>
                    <tr>
                        <td style="font-weight: bold; color: #374151;">Passport Number:</td>
                        <td><strong><?php echo h($card['passport_number']); ?></strong></td>
                    </tr>
                    <tr>
                        <td style="font-weight: bold; color: #374151;">Date &amp; Place of Birth:</td>
                        <td><?php echo formatNISDate($card['date_of_birth']); ?> at <?php echo h($card['place_of_birth']); ?></td>
                    </tr>
                    <tr>
                        <td style="font-weight: bold; color: #374151;">Profession:</td>
                        <td><?php echo h($card['profession']); ?></td>
                    </tr>
                    <tr>
                        <td style="font-weight: bold; color: #374151;">Address:</td>
                        <td><?php echo h($card['domicile']); ?></td>
                    </tr>
                    <tr>
                        <td style="font-weight: bold; color: #374151;">Effective Validity Period:</td>
                        <td>
                            From <strong><?php echo formatNISDate($card['issued_on']); ?></strong> 
                            until <strong style="color: #b91c1c;"><?php echo formatNISDate($card['expires_on']); ?></strong>
                        </td>
                    </tr>
                </table>
            </div>
        </div>

        <div style="background: #f8fafc; border-left: 4px solid #1a5c2e; padding: 12px 18px; font-size: 9pt; color: #475569; margin-top: 15px;">
            <strong>Official Endorsement:</strong> The bearer is entitled to live and pursue legitimate economic activities in accordance with Nigerian immigration laws and official regulations. Any change of address or profession must be promptly notified to the nearest NIS Command.
        </div>
    </div>

    <!-- Signatures, Seal & QR -->
    <div style="display: flex; justify-content: space-between; align-items: flex-end; margin-top: 30px; padding-top: 20px; border-top: 1px dashed #cbd5e1;">
        <!-- QR Code -->
        <div style="text-align: center;">
            <img src="https://api.qrserver.com/v1/create-qr-code/?size=100x100&data=<?php echo urlencode($verifyUrl); ?>" 
                 alt="QR Code" style="width: 85px; height: 85px;" onerror="this.src='assets/images/ecowas-emblem.svg';">
            <div style="font-size: 7.5pt; color: #64748b; margin-top: 4px;">SCAN FOR REAL-TIME<br>IMMIGRATION VALIDATION</div>
        </div>

        <!-- Official Seal -->
        <div style="text-align: center;">
            <img src="assets/images/official-stamp.svg" alt="Official Seal" style="width: 90px; height: 90px; opacity: 0.9;">
            <div style="font-size: 7.5pt; color: #64748b; margin-top: 2px;">OFFICIAL SEAL &amp; STAMP</div>
        </div>

        <!-- Comptroller Signature -->
        <div style="text-align: center; width: 200px;">
            <div style="font-family: 'Courier New', monospace; font-weight: bold; font-size: 11pt; color: #113f1f; margin-bottom: 5px;">
                <?php echo h($card['authority_signature']); ?>
            </div>
            <div style="border-top: 1px solid #000000; padding-top: 5px; font-weight: bold; font-size: 9pt;">
                FOR: COMPTROLLER GENERAL
            </div>
            <div style="font-size: 7.5pt; color: #4b5563;">
                Nigeria Immigration Service
            </div>
            <div style="font-size: 7pt; color: #6b7280; margin-top: 4px;">
                Station: <?php echo h($card['issued_at']); ?>
            </div>
        </div>
    </div>
</div>

</body>
</html>
