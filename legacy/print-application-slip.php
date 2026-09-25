<?php
/**
 * NIS Residence Card Issuance System (NIS-RCIS)
 * Official Vetted Residence Card Application Slip (Print View)
 * Displays all particulars from Sections 1 to 4 exactly as shown on the Review section
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

if (!$application && !empty($_SESSION['applicant_id'])) {
    $stmt = $db->prepare("SELECT * FROM applications WHERE applicant_id = :aid ORDER BY id DESC LIMIT 1");
    $stmt->execute([':aid' => $_SESSION['applicant_id']]);
    $application = $stmt->fetch(PDO::FETCH_ASSOC);
}

if (!$application) {
    die("Application particulars not found. Please verify your Application ID or Reference Number.");
}

$trackingNo = $application['application_number'] ?: ('RC-' . str_pad((string)$application['id'], 8, '0', STR_PAD_LEFT));
$appRefId = $application['reference_number'] ?: ($application['payment_reference'] ?: ('REF-' . date('Ymd', strtotime($application['created_at'])) . '-' . $application['id']));
$surname = strtoupper(trim($application['surname'] ?? ''));
$forenames = strtoupper(trim($application['forenames'] ?? ''));
$fullName = ($surname && $forenames) ? ($surname . ', ' . $forenames) : ($surname ?: $forenames);
$passportNumber = strtoupper(trim($application['passport_number'] ?? '—'));
$passportIssue = !empty($application['passport_issue_date']) ? $application['passport_issue_date'] : '—';
$passportExpiry = !empty($application['passport_expiry']) ? $application['passport_expiry'] : '—';
$passportValidity = ($passportIssue !== '—' && $passportExpiry !== '—') ? ($passportIssue . ' to ' . $passportExpiry) : '—';

$nationality = strtoupper(trim($application['nationality'] ?? '—'));
$gender = strtoupper(trim($application['sex'] ?? '—'));
$dob = !empty($application['date_of_birth']) ? $application['date_of_birth'] : '—';
$pob = strtoupper(trim($application['place_of_birth'] ?? ''));
$profession = strtoupper(trim($application['profession'] ?? '—'));
$center = !empty($application['enrollment_center']) ? $application['enrollment_center'] : 'NIS Headquarters, Abuja';
if (strtoupper($center) === 'NIS HQ') {
    $center = 'NIS Headquarters, Abuja';
}
$feeAmount = (float)($application['fee_amount'] ?? 35000.00);

$blood = !empty($application['blood_group']) ? $application['blood_group'] : '—';
$height = !empty($application['height']) ? ($application['height'] . ' cm') : 'Unspecified';
$complexion = !empty($application['complexion']) ? $application['complexion'] : '';
$heightComp = $height . ($complexion ? ' • ' . $complexion : '');

$eyes = !empty($application['eye_color']) ? $application['eye_color'] : '—';
$hair = !empty($application['hair_color']) ? $application['hair_color'] : '—';
$distFeatures = !empty($application['distinguished_features']) ? $application['distinguished_features'] : 'None recorded';

$email = !empty($application['email']) ? $application['email'] : '—';
$phone = !empty($application['phone']) ? $application['phone'] : '—';
$kinName = !empty($application['emergency_contact_name']) ? $application['emergency_contact_name'] : '—';
$kinRel = !empty($application['emergency_contact_relation']) ? $application['emergency_contact_relation'] : '';
$kinFull = $kinName . ($kinRel ? ' (' . $kinRel . ')' : '');

$address = !empty($application['domicile']) ? $application['domicile'] : '—';
$kinPhone = !empty($application['emergency_contact_phone']) ? ('Tel: ' . $application['emergency_contact_phone'] . ' • ') : '';
$kinAddr = !empty($application['emergency_contact_address']) ? $application['emergency_contact_address'] : '—';
$kinContactAddr = $kinPhone . $kinAddr;
$changeAddr = !empty($application['change_of_address']) ? $application['change_of_address'] : 'None recorded';

$hasPhoto = !empty($application['photo_path']) && file_exists(__DIR__ . '/' . $application['photo_path']);
$photoUrl = $hasPhoto ? $application['photo_path'] : '';

$todayDate = date('d M Y, H:i');
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Application Slip — Nigeria Immigration Service</title>
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
            font-size: 0.90rem;
            line-height: 1.5;
        }

        .slip-card {
            background: #ffffff;
            border-radius: 12px;
            box-shadow: 0 4px 16px rgba(0, 0, 0, 0.06);
            padding: 2.25rem 2.5rem;
            max-width: 820px;
            margin: 0 auto 2rem;
            position: relative;
        }

        /* Screen Action Toolbar */
        .screen-actions-bar {
            max-width: 820px;
            margin: 0 auto 1.25rem;
            display: flex;
            justify-content: space-between;
            align-items: center;
            gap: 12px;
        }

        .btn-action-print {
            background: #1a5c2e;
            color: #ffffff;
            border: none;
            padding: 10px 24px;
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
            padding: 9px 18px;
            font-size: 0.88rem;
            font-weight: 600;
            border-radius: 6px;
            cursor: pointer;
            transition: all 0.2s ease;
        }

        .btn-action-close:hover {
            background: #f3f4f6;
            color: #111827;
        }

        /* Top Header */
        .slip-header-wrapper {
            text-align: center;
            margin-bottom: 1.25rem;
        }

        .slip-crest-logo {
            width: 60px;
            height: auto;
            margin-bottom: 0.4rem;
            display: inline-block;
        }

        .slip-agency-name {
            font-size: 1.15rem;
            font-weight: 800;
            color: #0b6623;
            text-transform: uppercase;
            letter-spacing: 0.5px;
            margin: 0 0 2px;
        }

        .slip-doc-title {
            font-size: 1.05rem;
            font-weight: 700;
            color: #0f172a;
            margin: 0 0 0.4rem;
        }

        .slip-subtitle {
            color: #64748b;
            max-width: 640px;
            margin: 0 auto 0.75rem;
            font-size: 0.86rem;
            line-height: 1.5;
            text-align: center;
        }

        .slip-barcode-wrap {
            display: flex;
            justify-content: flex-end;
            margin: 0.25rem 0 0.75rem;
        }

        .slip-meta-strip {
            display: flex;
            justify-content: space-between;
            align-items: center;
            background: #f8fafc;
            border: 1px solid #e2e8f0;
            border-radius: 6px;
            padding: 9px 16px;
            margin-bottom: 1.35rem;
            font-size: 0.84rem;
            flex-wrap: wrap;
            gap: 8px;
        }

        /* Review Section styles identical to Step 5 */
        .review-dossier-banner {
            display: flex;
            align-items: center;
            gap: 20px;
            background: #f8fafc;
            border: 1px solid #e2e8f0;
            border-radius: 8px;
            padding: 16px 20px;
            margin-bottom: 1.25rem;
        }

        .review-photo-thumb {
            width: 72px;
            height: 92px;
            border-radius: 6px;
            background: #ffffff;
            border: 1.5px solid #cbd5e1;
            overflow: hidden;
            flex-shrink: 0;
            display: flex;
            align-items: center;
            justify-content: center;
        }

        .review-photo-thumb img {
            width: 100%;
            height: 100%;
            object-fit: cover;
        }

        .review-section-box {
            background: #ffffff;
            border: 1px solid #e2e8f0;
            border-radius: 8px;
            padding: 1.15rem 1.4rem;
            margin-bottom: 1.15rem;
        }

        .review-section-title {
            font-size: 0.82rem;
            font-weight: 800;
            color: #113f1f;
            text-transform: uppercase;
            letter-spacing: 0.6px;
            border-bottom: 1.5px solid #f1f5f9;
            padding-bottom: 8px;
            margin-bottom: 12px;
            display: flex;
            align-items: center;
            gap: 8px;
        }

        .review-grid-2 {
            display: grid;
            grid-template-columns: 1fr 1fr;
            gap: 12px 20px;
        }

        .review-grid-3 {
            display: grid;
            grid-template-columns: 1fr 1fr 1fr;
            gap: 12px 20px;
        }

        .review-item-label {
            font-size: 0.72rem;
            font-weight: 700;
            color: #64748b;
            text-transform: uppercase;
            letter-spacing: 0.4px;
            margin-bottom: 3px;
            display: block;
        }

        .review-item-value {
            font-size: 0.90rem;
            font-weight: 600;
            color: #0f172a;
            line-height: 1.4;
        }

        .review-lock-card {
            background: #ffffff;
            border: 1px solid #e2e8f0;
            border-radius: 8px;
            padding: 1.1rem 1.25rem;
            margin-top: 1.25rem;
        }

        .slip-signatures {
            margin-top: 1.75rem;
            padding-top: 1.25rem;
            border-top: 1px dashed #cbd5e1;
            display: grid;
            grid-template-columns: 1fr 1fr;
            gap: 30px;
            font-size: 0.84rem;
            color: #334155;
        }

        @page {
            size: A4 portrait;
            margin: 12mm 15mm 12mm 15mm;
        }

        @media print {
            body {
                background: #ffffff !important;
                padding: 0 !important;
                color: #000000 !important;
            }

            .screen-actions-bar {
                display: none !important;
            }

            .slip-card {
                box-shadow: none !important;
                border: none !important;
                padding: 0 !important;
                max-width: 100% !important;
                margin: 0 !important;
            }

            .review-section-box {
                break-inside: avoid;
                page-break-inside: avoid;
                border: 1px solid #cbd5e1 !important;
            }

            .review-dossier-banner {
                break-inside: avoid;
                page-break-inside: avoid;
                border: 1px solid #cbd5e1 !important;
            }

            .slip-signatures {
                break-inside: avoid;
                page-break-inside: avoid;
            }
        }
    </style>
</head>
<body>

    <!-- On-screen Toolbar -->
    <div class="screen-actions-bar">
        <button type="button" onclick="window.print()" class="btn-action-print">
            <i class="fas fa-print"></i> Print Slip
        </button>
        <button type="button" onclick="window.close()" class="btn-action-close">
            Close Window
        </button>
    </div>

    <!-- Official NIS Application Slip -->
    <div class="slip-card">
        <!-- Top Status Header -->
        <div class="slip-header-wrapper">
            <img src="assets/images/nis-crest-logo-transparent.png?v=3" alt="NIS Crest" class="slip-crest-logo" onerror="this.src='assets/images/nis-logo.png';">
            <div class="slip-agency-name">NIGERIA IMMIGRATION SERVICE</div>
            <div class="slip-doc-title">Residence Card Application Slip</div>
            <p class="slip-subtitle">
                Official Online Application Summary &bull; Particulars Vetted at Fee Assessment Stage
            </p>
        </div>

        <!-- Top-Right Barcode -->
        <div class="slip-barcode-wrap">
            <?php echo generateBarcodeSvg($trackingNo, 40, 200); ?>
        </div>

        <!-- Metadata Strip -->
        <div class="slip-meta-strip">
            <div><span style="color: #64748b; font-weight: 600;">Application ID:</span> <strong style="color: #0b6623; font-family: monospace; font-size: 0.92rem;"><?php echo h($trackingNo); ?></strong></div>
            <div><span style="color: #64748b; font-weight: 600;">Reference ID:</span> <strong style="font-family: monospace;"><?php echo h($appRefId); ?></strong></div>
            <div><span style="color: #64748b; font-weight: 600;">Generated:</span> <strong><?php echo h($todayDate); ?></strong></div>
        </div>

        <!-- Applicant Dossier Header Banner (Identical to Step 5) -->
        <div class="review-dossier-banner">
            <div class="review-photo-thumb">
                <?php if ($hasPhoto): ?>
                    <img src="<?php echo h($photoUrl); ?>" alt="Applicant Photo">
                <?php else: ?>
                    <i class="fas fa-user" style="font-size: 2.2rem; color: #cbd5e1;"></i>
                <?php endif; ?>
            </div>
            <div style="flex: 1;">
                <div style="font-size: 1.25rem; font-weight: normal; color: #0f172a; margin-bottom: 6px; letter-spacing: -0.2px; text-transform: uppercase;">
                    <?php echo h($fullName); ?>
                </div>
                <div style="display: flex; flex-wrap: wrap; gap: 14px; align-items: center; font-size: 0.88rem; color: #475569;">
                    <span>Passport: <strong style="color: #113f1f; font-family: monospace; font-size: 0.88rem;"><?php echo h($passportNumber); ?></strong></span>
                    <span style="color: #cbd5e1;">&bull;</span>
                    <span>Nationality: <strong style="font-size: 0.88rem;"><?php echo h($nationality . ' • ' . $gender); ?></strong></span>
                    <span style="color: #cbd5e1;">&bull;</span>
                    <span>DOB: <strong style="font-size: 0.88rem;"><?php echo h($dob . ($pob ? ' (' . $pob . ')' : '')); ?></strong></span>
                    <span style="color: #cbd5e1;">&bull;</span>
                    <span>Amount to be Paid: <strong style="color: #0b6623; font-weight: 800; font-size: 0.88rem;">&#8358;<?php echo number_format($feeAmount, 2); ?></strong></span>
                </div>
            </div>
        </div>

        <!-- Section 1: Passport & Identification Particulars -->
        <div class="review-section-box">
            <div class="review-section-title">
                1. Passport &amp; Identification Particulars
            </div>
            <div class="review-grid-3">
                <div>
                    <span class="review-item-label">Passport Number</span>
                    <div class="review-item-value" style="font-family: monospace; font-size: 0.94rem; color: #113f1f; font-weight: 800;"><?php echo h($passportNumber); ?></div>
                </div>
                <div>
                    <span class="review-item-label">Passport Validity Period</span>
                    <div class="review-item-value"><?php echo h($passportValidity); ?></div>
                </div>
                <div>
                    <span class="review-item-label">Profession / Occupation</span>
                    <div class="review-item-value"><?php echo h($profession); ?></div>
                </div>
                <div>
                    <span class="review-item-label">Designated Enrollment Center</span>
                    <div class="review-item-value" style="font-weight: 700; color: #113f1f;"><?php echo h($center); ?></div>
                </div>
                <div>
                    <span class="review-item-label">Issuance Fee (To be Paid)</span>
                    <div class="review-item-value" style="font-weight: 800; color: #0b6623;">&#8358;<?php echo number_format($feeAmount, 2); ?></div>
                </div>
            </div>
        </div>

        <!-- Section 2: Personal Particulars & Biometrics -->
        <div class="review-section-box">
            <div class="review-section-title">
                2. Personal Particulars &amp; Biometrics
            </div>
            <div class="review-grid-3">
                <div>
                    <span class="review-item-label">Full Legal Name</span>
                    <div class="review-item-value" style="font-weight: 700;"><?php echo h($fullName); ?></div>
                </div>
                <div>
                    <span class="review-item-label">Nationality &amp; Gender</span>
                    <div class="review-item-value"><?php echo h($nationality . ' (' . $gender . ')'); ?></div>
                </div>
                <div>
                    <span class="review-item-label">Date &amp; Place of Birth</span>
                    <div class="review-item-value"><?php echo h($dob . ' • ' . $pob); ?></div>
                </div>
                <div>
                    <span class="review-item-label">Blood Group</span>
                    <div class="review-item-value"><?php echo h($blood); ?></div>
                </div>
                <div>
                    <span class="review-item-label">Height &amp; Complexion</span>
                    <div class="review-item-value"><?php echo h($heightComp); ?></div>
                </div>
                <div>
                    <span class="review-item-label">Eye Color &amp; Hair Color</span>
                    <div class="review-item-value"><?php echo h('Eyes: ' . $eyes . ' • Hair: ' . $hair); ?></div>
                </div>
                <div style="grid-column: 1 / -1;">
                    <span class="review-item-label">Distinguishing Physical Marks / Features</span>
                    <div class="review-item-value"><?php echo h($distFeatures); ?></div>
                </div>
            </div>
        </div>

        <!-- Section 3: Contact, Residence & Next of Kin Particulars -->
        <div class="review-section-box">
            <div class="review-section-title">
                3. Contact, Residence &amp; Next of Kin Particulars
            </div>
            <div class="review-grid-2">
                <div>
                    <span class="review-item-label">Telephone &amp; Email</span>
                    <div class="review-item-value"><?php echo h($email . ' • ' . $phone); ?></div>
                </div>
                <div>
                    <span class="review-item-label">Emergency Next of Kin (Name &amp; Relationship)</span>
                    <div class="review-item-value"><?php echo h($kinFull); ?></div>
                </div>
                <div>
                    <span class="review-item-label">Residential Address in Nigeria</span>
                    <div class="review-item-value"><?php echo h($address); ?></div>
                </div>
                <div>
                    <span class="review-item-label">Next of Kin Contact &amp; Residential Address</span>
                    <div class="review-item-value"><?php echo h($kinContactAddr); ?></div>
                </div>
                <div style="grid-column: 1 / -1;">
                    <span class="review-item-label">Change of Address in Nigeria</span>
                    <div class="review-item-value"><?php echo h($changeAddr); ?></div>
                </div>
            </div>
        </div>

        <!-- Section 4: Attached Supporting Documents Status -->
        <div class="review-section-box">
            <div class="review-section-title">
                4. Attached Supporting Documents
            </div>
            <div style="display: grid; grid-template-columns: repeat(auto-fit, minmax(200px, 1fr)); gap: 12px 16px;">
                <div style="padding: 10px 14px; background: #f8fafc; border: 1px solid #e2e8f0; border-radius: 6px;">
                    <span class="review-item-label">Passport Bio-data Page</span>
                    <div style="font-size: 0.85rem; font-weight: 700; margin-top: 4px; color: <?php echo !empty($application['doc_passport_copy']) ? '#166534' : '#dc2626'; ?>;">
                        <?php echo !empty($application['doc_passport_copy']) ? 'Attached / Uploaded' : 'Pending Upload'; ?>
                    </div>
                </div>
                <div style="padding: 10px 14px; background: #f8fafc; border: 1px solid #e2e8f0; border-radius: 6px;">
                    <span class="review-item-label">Residence Permit / Entry Visa</span>
                    <div style="font-size: 0.85rem; font-weight: 700; margin-top: 4px; color: <?php echo !empty($application['doc_residence_visa']) ? '#166534' : '#dc2626'; ?>;">
                        <?php echo !empty($application['doc_residence_visa']) ? 'Attached / Uploaded' : 'Pending Upload'; ?>
                    </div>
                </div>
                <div style="padding: 10px 14px; background: #f8fafc; border: 1px solid #e2e8f0; border-radius: 6px;">
                    <span class="review-item-label">Quota Approval / Letter</span>
                    <div style="font-size: 0.85rem; font-weight: 700; margin-top: 4px; color: <?php echo !empty($application['doc_quota_approval']) ? '#166534' : '#dc2626'; ?>;">
                        <?php echo !empty($application['doc_quota_approval']) ? 'Attached / Uploaded' : 'Pending Upload'; ?>
                    </div>
                </div>
                <div style="padding: 10px 14px; background: #f8fafc; border: 1px solid #e2e8f0; border-radius: 6px;">
                    <span class="review-item-label">Proof of Domicile</span>
                    <div style="font-size: 0.85rem; font-weight: 600; margin-top: 4px; color: <?php echo !empty($application['doc_domicile_proof']) ? '#166534' : '#64748b'; ?>;">
                        <?php echo !empty($application['doc_domicile_proof']) ? 'Attached / Uploaded' : 'Optional'; ?>
                    </div>
                </div>
                <div style="padding: 10px 14px; background: #f8fafc; border: 1px solid #e2e8f0; border-radius: 6px;">
                    <span class="review-item-label">Additional Document</span>
                    <div style="font-size: 0.85rem; font-weight: 600; margin-top: 4px; color: <?php echo !empty($application['doc_additional']) ? '#166534' : '#64748b'; ?>;">
                        <?php echo !empty($application['doc_additional']) ? 'Attached / Uploaded' : 'Optional'; ?>
                    </div>
                </div>
            </div>
        </div>

        <!-- Applicant Declaration Card -->
        <div class="review-lock-card">
            <div style="font-size: 0.92rem; font-weight: 700; color: #0f172a; margin-bottom: 4px;">
                Applicant Declaration
            </div>
            <p style="font-size: 0.86rem; color: #475569; margin: 0; line-height: 1.5;">
                I hereby confirm that all information provided in this application is true, accurate, and complete. I understand that submitting false or misleading information attracts legal penalties under Nigerian law.
            </p>
        </div>

        <!-- Official Signatures Block -->
        <div class="slip-signatures">
            <div>
                <div style="margin-bottom: 35px; color: #64748b; font-size: 0.74rem; text-transform: uppercase; font-weight: 700;">Applicant Signature &amp; Date</div>
                <div style="border-bottom: 1px solid #94a3b8; width: 85%;"></div>
            </div>
            <div>
                <div style="margin-bottom: 35px; color: #64748b; font-size: 0.74rem; text-transform: uppercase; font-weight: 700;">Vetting Immigration Officer (Official Stamp)</div>
                <div style="border-bottom: 1px solid #94a3b8; width: 85%;"></div>
            </div>
        </div>
    </div>

    <?php if (!empty($_GET['print'])): ?>
    <script>
        window.addEventListener('load', function() {
            setTimeout(function() {
                window.print();
            }, 350);
        });
    </script>
    <?php endif; ?>

</body>
</html>
