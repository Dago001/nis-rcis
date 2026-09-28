<?php
/**
 * NIS Residence Card Issuance System (NIS-RCIS)
 * High-Fidelity Official NIS Booklet Template
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

// Fetch renewals
$renewStmt = $db->prepare("SELECT * FROM card_renewals WHERE card_id = :cid ORDER BY renewal_number ASC LIMIT 3");
$renewStmt->execute([':cid' => $id]);
$renewals = $renewStmt->fetchAll();

logAudit('CARD_PRINT_BOOKLET', $id, "Printed official 5-page booklet for Card No. {$card['card_number']}");

$verifyUrl = (isset($_SERVER['HTTPS']) && $_SERVER['HTTPS'] === 'on' ? "https" : "http") . "://$_SERVER[HTTP_HOST]" . dirname($_SERVER['REQUEST_URI']) . "/verify-card?token=" . urlencode($card['verification_token']);
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <title>Booklet Print - No. <?php echo h($card['card_number']); ?> | <?php echo APP_NAME; ?></title>
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
            background-color: #4a5568;
            margin: 0;
            padding: 20px;
        }

        .booklet-container {
            display: flex;
            flex-direction: column;
            align-items: center;
            gap: 30px;
        }

        .action-bar {
            background: #ffffff;
            padding: 12px 24px;
            border-radius: 8px;
            box-shadow: 0 4px 15px rgba(0,0,0,0.25);
            display: flex;
            gap: 12px;
            align-items: center;
            width: 280mm;
            box-sizing: border-box;
            justify-content: space-between;
        }

        .back-btn {
            display: inline-flex;
            align-items: center;
            gap: 6px;
            background: #ffffff;
            color: #334155;
            border: 1px solid #cbd5e1;
            padding: 8px 16px;
            border-radius: 6px;
            font-size: 0.85rem;
            font-weight: 600;
            text-decoration: none;
            transition: all 0.2s ease;
        }
        .back-btn:hover {
            background: #f1f5f9;
            color: #0f172a;
            border-color: #94a3b8;
        }

        .print-btn {
            display: inline-flex;
            align-items: center;
            gap: 6px;
            background: #113f1f;
            color: #ffffff;
            border: none;
            padding: 8px 20px;
            border-radius: 6px;
            font-size: 0.85rem;
            font-weight: 600;
            cursor: pointer;
            transition: all 0.2s ease;
            box-shadow: 0 2px 6px rgba(17,63,31,0.25);
        }
        .print-btn:hover {
            background: #0b2814;
            box-shadow: 0 4px 10px rgba(17,63,31,0.35);
        }
    </style>
</head>
<body>

<div class="booklet-container">
    <!-- Top Print Control Bar -->
    <div class="action-bar no-print">
        <div style="display: flex; align-items: center; gap: 10px;">
            <img src="assets/images/nis-logo.png" alt="Logo" style="height: 36px;">
            <div>
                <strong style="color: var(--nis-green-dark);">Official Residence Card Booklet Printout</strong>
                <div style="font-size: 0.78rem; color: #64748b;">Card No. <?php echo h($card['card_number']); ?></div>
            </div>
        </div>
        <div style="display: flex; gap: 10px;">
            <a href="card-details?id=<?php echo (int)$card['id']; ?>" class="back-btn"><i class="fas fa-arrow-left"></i> Return to Card Details</a>
            <button onclick="window.print()" class="print-btn"><i class="fas fa-print"></i> Print Booklet</button>
        </div>
    </div>

    <!-- ===============================================================
         SHEET 1: COVER & OFFICIAL DECISION (Pages 1 & 2 of Official Template)
         =============================================================== -->
    <div class="booklet-page-sheet booklet-grid-2col">

        <!-- PAGE 1 (Cover) -->
        <div class="booklet-column" style="justify-content: space-between;">
            <div>
                <div class="cover-emblem-box">
                    <img src="assets/images/ecowas-emblem.svg" alt="CEDEAO ECOWAS Emblem" class="ecowas-emblem">
                </div>

                <div class="residence-card-title-box">
                    RESIDENCE CARD
                </div>

                <div class="issuing-country-row">
                    ISSUING COUNTRY: <span style="font-weight: 800; border-bottom: 1px dotted #000;"><?php echo h($card['issuing_country']); ?></span>
                </div>
            </div>

            <div>
                <div class="card-serial-row">
                    <span>No.</span>
                    <span class="card-serial-number"><?php echo h($card['card_number']); ?></span>
                    <span style="font-size: 11pt; color: #4a5568;">/<?php echo date('y', strtotime($card['issued_on'])); ?></span>
                </div>
                <div class="booklet-page-number">1</div>
            </div>
        </div>

        <!-- PAGE 2 (Official Decision) -->
        <div class="booklet-column" style="justify-content: space-between;">
            <div>
                <div class="decision-block" style="margin-top: 20px;">
                    <div style="font-weight: bold; margin-bottom: 8px;">Decision:</div>
                    <div class="dotted-line-field">
                        DATED: <?php echo formatNISDate($card['decision_date']); ?>
                    </div>
                    <div class="dotted-line-field">
                        AUTHORITY: <?php echo h($card['approving_authority']); ?>
                    </div>
                    <div class="dotted-line-field" style="color: #64748b; font-style: italic;">
                        RESIDENCE RIGHTS CONFERRED FOR THE SPECIFIED DURATION
                    </div>
                </div>
            </div>

            <div>
                <div class="booklet-page-number">2</div>
            </div>
        </div>
    </div>

    <!-- ===============================================================
         SHEET 2: INSIDE PAGES (Pages 3, 4 & 5 of Uploaded Template)
         =============================================================== -->
    <div class="booklet-page-sheet booklet-grid-3col">
        <!-- PAGE 3 (Biometrics & Profile) -->
        <div class="booklet-column" style="justify-content: space-between;">
            <div>
                <!-- Applicant Photo Box -->
                <div class="applicant-photo-box">
                    <?php if (!empty($card['photo_path']) && file_exists(__DIR__ . '/' . $card['photo_path'])): ?>
                        <img src="<?php echo h($card['photo_path']); ?>" alt="Photo">
                    <?php else: ?>
                        <span style="font-size: 8pt; color: #a0aec0; text-align: center;">PHOTO<br>35x45mm</span>
                    <?php endif; ?>
                </div>

                <div class="bio-field-row" style="margin-top: 50mm;">
                    <span class="bio-label">Surname:</span>
                    <span class="bio-value"><?php echo h($card['surname']); ?></span>
                </div>

                <div class="bio-field-row">
                    <span class="bio-label">Given Name:</span>
                    <span class="bio-value"><?php echo h($card['forenames']); ?></span>
                </div>

                <div class="bio-field-row">
                    <span class="bio-label">Nationality:</span>
                    <span class="bio-value"><?php echo h($card['nationality']); ?></span>
                </div>

                <div class="bio-field-row">
                    <span class="bio-label">Date and Place of birth:</span>
                    <span class="bio-value"><?php echo formatNISDate($card['date_of_birth']); ?> (<?php echo h($card['place_of_birth']); ?>)</span>
                </div>

                <div class="bio-field-row">
                    <span class="bio-label">Sex:</span> <span style="font-weight: bold; margin-right: 15px;"><?php echo h($card['sex']); ?></span>
                    <span class="bio-label">Height:</span> <span style="font-weight: bold;"><?php echo h($card['height']); ?></span>
                </div>

                <div class="bio-field-row">
                    <span class="bio-label">Complexion:</span>
                    <span class="bio-value"><?php echo h($card['complexion']); ?></span>
                </div>

                <div class="bio-field-row">
                    <span class="bio-label">Colour of eyes:</span> <span style="font-weight: bold; margin-right: 10px;"><?php echo h($card['eye_color']); ?></span>
                    <span class="bio-label">Colour of hair:</span> <span style="font-weight: bold;"><?php echo h($card['hair_color']); ?></span>
                </div>

                <div class="bio-field-row">
                    <span class="bio-label">Distinguished features:</span>
                    <span class="bio-value"><?php echo h($card['distinguished_features']); ?></span>
                </div>

                <div class="bio-field-row">
                    <span class="bio-label">Profession:</span>
                    <span class="bio-value"><?php echo h($card['profession']); ?></span>
                </div>

                <div class="bio-field-row">
                    <span class="bio-label">Address:</span>
                    <span class="bio-value" style="font-size: 8pt;"><?php echo h($card['domicile']); ?></span>
                </div>
            </div>

            <div>
                <div class="booklet-page-number">3</div>
            </div>
        </div>

        <!-- PAGE 4 (Contact & Issuing Officer Endorsement) -->
        <div class="booklet-column" style="justify-content: space-between;">
            <div>
                <div class="bio-field-row" style="margin-top: 5mm;">
                    <span class="bio-label">Person to be contacted, if necessary:</span>
                    <span class="bio-value"><?php echo h($card['emergency_contact_name']); ?> (<?php echo h($card['emergency_contact_relation']); ?>)</span>
                    <div style="font-size: 8.5pt; font-weight: 600;"><?php echo h($card['emergency_contact_phone']); ?></div>
                    <div style="font-size: 8pt; color: #4a5568;"><?php echo h($card['emergency_contact_address']); ?></div>
                </div>

                <div class="bio-field-row" style="margin-top: 10mm;">
                    <span class="bio-label">Blood group:</span>
                    <span class="bio-value" style="width: auto; padding: 0 10px;"><?php echo h($card['blood_group']); ?></span>
                </div>

                <div class="bio-field-row" style="margin-top: 12mm;">
                    <span class="bio-label">Change of Address:</span>
                    <div class="dotted-line-field" style="margin-top: 4px;"><?php echo h($card['change_of_address']); ?></div>
                    <div class="dotted-line-field"></div>
                </div>

                <div class="endorsement-note">
                    (To be endorsed by Authority)
                </div>
            </div>

            <div>
                <div class="officer-signature-area">
                    <div><?php echo h($card['issuing_officer_name']); ?></div>
                    <div style="font-size: 7.5pt; font-weight: normal; color: #4a5568;">
                        Officer Service No.: <?php echo h($card['issuing_officer_service_no']); ?>
                    </div>
                    <div style="margin-top: 4px; font-size: 8pt;">
                        Signature and stamp of issuing Officer:
                    </div>
                </div>
                <div class="booklet-page-number">4</div>
            </div>
        </div>

        <!-- PAGE 5 (Validity, Renewals & Authority Postage Stamp) -->
        <div class="booklet-column" style="justify-content: space-between;">
            <div>
                <div class="validity-block" style="margin-top: 5mm;">
                    <div class="bio-field-row">
                        <span class="bio-label">Issued on:</span>
                        <span class="bio-value"><?php echo formatNISDate($card['issued_on']); ?></span>
                    </div>

                    <div class="bio-field-row">
                        <span class="bio-label">At:</span>
                        <span class="bio-value"><?php echo h($card['issued_at']); ?></span>
                    </div>

                    <div class="bio-field-row">
                        <span class="bio-label">Expires on:</span>
                        <span class="bio-value" style="color: #991b1b; font-weight: 800;"><?php echo formatNISDate($card['expires_on']); ?></span>
                    </div>
                </div>

                <div style="font-weight: bold; text-align: center; margin-top: 6mm; letter-spacing: 1px; font-size: 9.5pt;">
                    RENEWALS
                </div>

                <table class="renewals-table">
                    <thead>
                        <tr>
                            <th>From</th>
                            <th>To</th>
                            <th>Endorsement</th>
                        </tr>
                    </thead>
                    <tbody>
                        <!-- Row 1: Initial issuance period from date of issuance to expiry date -->
                        <tr>
                            <td><?php echo date('d/m/Y', strtotime($card['issued_on'])); ?></td>
                            <td><strong><?php echo date('d/m/Y', strtotime($card['expires_on'])); ?></strong></td>
                            <td style="font-size: 7.5pt; font-weight: bold;"><?php echo h($card['issuing_officer_service_no'] ?: 'NIS HQ'); ?></td>
                        </tr>
                        <?php if (!empty($renewals)): ?>
                            <?php foreach ($renewals as $rn): ?>
                                <tr>
                                    <td><?php echo date('d/m/Y', strtotime($rn['from_date'])); ?></td>
                                    <td><strong><?php echo date('d/m/Y', strtotime($rn['to_date'])); ?></strong></td>
                                    <td style="font-size: 7.5pt;"><?php echo h($rn['officer_service_no']); ?></td>
                                </tr>
                            <?php endforeach; ?>
                        <?php endif; ?>
                        <!-- Blank rows for future endorsements if total rows < 3 -->
                        <?php 
                        $totalFilled = 1 + count($renewals);
                        for ($i = $totalFilled; $i < 3; $i++): 
                        ?>
                            <tr>
                                <td style="height: 18px;">................</td>
                                <td>................</td>
                                <td>................</td>
                            </tr>
                        <?php endfor; ?>
                    </tbody>
                </table>

                <!-- Postage Stamp Box -->
                <div class="authority-stamp-box">
                    <div style="font-size: 7.5pt; color: #64748b; margin-top: auto; padding: 4px;">
                        Postage Stamp and Signature of Authority
                    </div>
                </div>

                <!-- Digital Verification QR Code -->
                <div class="verification-qr-box">
                    <img src="https://api.qrserver.com/v1/create-qr-code/?size=90x90&data=<?php echo urlencode($verifyUrl); ?>" 
                         alt="QR Code" onerror="this.src='assets/images/ecowas-emblem.svg';">
                    <div style="font-size: 6.5pt; color: #718096; margin-top: 2px;">SCAN TO VERIFY OFFICIAL RECORD</div>
                </div>
            </div>

            <div>
                <div class="booklet-page-number">5</div>
            </div>
        </div>
    </div>
</div>

</body>
</html>
