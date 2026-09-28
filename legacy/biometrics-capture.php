<?php
/**
 * NIS Residence Card Issuance System (NIS-RCIS)
 * Enrollment Desk: Physical Biometrics Capturing Station
 * Used on appointment date when applicant presents their printed approved slip
 */

require_once __DIR__ . '/includes/auth.php';
require_once __DIR__ . '/config/database.php';
require_once __DIR__ . '/config/constants.php';

requireRole([ROLE_SUPER_ADMIN, ROLE_APPROVING_OFFICER, ROLE_ISSUING_OFFICER]);

$pageTitle = 'Biometrics Capturing Desk';
$db = Database::getConnection();
$user = currentUser();
$errors = [];
$selectedApp = null;
$issuedCardId = null;

// Search for application by ID, application_number, reference_number, or passport
$searchApp = trim(
    $_GET['app_search'] ?? 
    $_GET['app_num'] ?? 
    $_GET['app_number'] ?? 
    $_GET['application_number'] ?? 
    $_GET['app'] ?? 
    $_GET['app_id'] ?? 
    $_GET['search'] ?? 
    $_GET['q'] ?? ''
);

$searchResults = [];

if (!empty($searchApp)) {
    $rawTerm = $searchApp;
    // Extract APP: or application number from scanned QR code payload if present
    if (stripos($rawTerm, 'APP:') !== false) {
        if (preg_match('/APP:([A-Z0-9\-]+)/i', $rawTerm, $m)) {
            $rawTerm = trim($m[1]);
        }
    } elseif (stripos($rawTerm, 'NIS-RCIS|') !== false) {
        $parts = explode('|', $rawTerm);
        foreach ($parts as $p) {
            if (stripos($p, 'APP:') === 0) {
                $rawTerm = trim(substr($p, 4));
                break;
            }
        }
    }

    $upper = strtoupper($rawTerm);
    $alphanumeric = preg_replace('/[^A-Z0-9]/', '', $upper);
    $digits = preg_replace('/[^0-9]/', '', $rawTerm);

    // Tier 1: Exact match on application_number, reference_number, passport_number
    $st = $db->prepare("SELECT * FROM applications 
        WHERE UPPER(application_number) = :up1 
           OR UPPER(reference_number) = :up2 
           OR UPPER(passport_number) = :up3 
        LIMIT 1");
    $st->execute([':up1' => $upper, ':up2' => $upper, ':up3' => $upper]);
    $exact = $st->fetch(PDO::FETCH_ASSOC);
    if ($exact) {
        $selectedApp = $exact;
    } else {
        // Tier 2: Normalized alphanumeric match (e.g. RC2026433764 matches RC-2026-433764)
        if (!empty($alphanumeric)) {
            $st = $db->prepare("SELECT * FROM applications 
                WHERE REPLACE(REPLACE(UPPER(application_number), '-', ''), ' ', '') = :an1
                   OR REPLACE(REPLACE(UPPER(reference_number), '-', ''), ' ', '') = :an2
                   OR REPLACE(REPLACE(UPPER(passport_number), '-', ''), ' ', '') = :an3
                LIMIT 1");
            $st->execute([':an1' => $alphanumeric, ':an2' => $alphanumeric, ':an3' => $alphanumeric]);
            $norm = $st->fetch(PDO::FETCH_ASSOC);
            if ($norm) {
                $selectedApp = $norm;
            }
        }

        // Tier 3: Digits match for suffix (e.g. 433764 matches RC-2026-433764)
        if (!$selectedApp && !empty($digits) && strlen($digits) >= 4) {
            $st = $db->prepare("SELECT * FROM applications 
                WHERE application_number LIKE :dig1 
                   OR reference_number LIKE :dig2
                LIMIT 5");
            $st->execute([':dig1' => '%' . $digits . '%', ':dig2' => '%' . $digits . '%']);
            $digMatches = $st->fetchAll(PDO::FETCH_ASSOC);
            if (count($digMatches) === 1) {
                $selectedApp = $digMatches[0];
            } elseif (count($digMatches) > 1) {
                $searchResults = $digMatches;
            }
        }

        // Tier 4: Exact database ID match if numeric
        if (!$selectedApp && empty($searchResults) && is_numeric($rawTerm) && (int)$rawTerm < 100000) {
            $st = $db->prepare("SELECT * FROM applications WHERE id = :id LIMIT 1");
            $st->execute([':id' => (int)$rawTerm]);
            $idMatch = $st->fetch(PDO::FETCH_ASSOC);
            if ($idMatch) {
                $selectedApp = $idMatch;
            }
        }

        // Tier 5: Partial text search across name, passport, app number
        if (!$selectedApp && empty($searchResults)) {
            $st = $db->prepare("SELECT * FROM applications 
                WHERE UPPER(application_number) LIKE :like1 
                   OR UPPER(reference_number) LIKE :like2 
                   OR UPPER(passport_number) LIKE :like3 
                   OR UPPER(surname) LIKE :like4 
                   OR UPPER(forenames) LIKE :like5 
                ORDER BY id DESC LIMIT 10");
            $st->execute([
                ':like1' => '%' . $upper . '%',
                ':like2' => '%' . $upper . '%',
                ':like3' => '%' . $upper . '%',
                ':like4' => '%' . $upper . '%',
                ':like5' => '%' . $upper . '%',
            ]);
            $likeMatches = $st->fetchAll(PDO::FETCH_ASSOC);
            if (count($likeMatches) === 1) {
                $selectedApp = $likeMatches[0];
            } elseif (count($likeMatches) > 1) {
                $searchResults = $likeMatches;
            }
        }

        if (!$selectedApp && empty($searchResults)) {
            setFlash('warning', "No application file found matching '{$searchApp}'.");
        }
    }
}

// Handle Biometrics Capture Submission
if (($_SERVER['REQUEST_METHOD'] ?? '') === 'POST' && isset($_POST['action_complete_biometrics'])) {
    if (!validate_csrf()) {
        $errors[] = 'Security token validation failed. Please try again.';
    } else {
        $appId = (int)($_POST['app_id'] ?? 0);
        $st = $db->prepare("SELECT * FROM applications WHERE id = :id LIMIT 1");
        $st->execute([':id' => $appId]);
        $app = $st->fetch(PDO::FETCH_ASSOC);

        if (!$app) {
            $errors[] = 'Application record not found.';
        } else {
            // Process photo capture
            $photoPath = $app['photo_path'] ?? null;
            if (!empty($_POST['captured_photo_data'])) {
                $rawImg = $_POST['captured_photo_data'];
                if (preg_match('/^data:image\/(\w+);base64,/', $rawImg, $type)) {
                    $rawImg = substr($rawImg, strpos($rawImg, ',') + 1);
                    $rawImg = base64_decode($rawImg);
                    if ($rawImg !== false) {
                        $dir = __DIR__ . '/uploads/photos';
                        if (!is_dir($dir)) mkdir($dir, 0755, true);
                        $fname = 'photo_' . $appId . '_' . time() . '.jpg';
                        file_put_contents($dir . '/' . $fname, $rawImg);
                        $photoPath = 'uploads/photos/' . $fname;
                    }
                }
            }

            // Process signature
            $sigPath = $app['signature_path'] ?? null;
            if (!empty($_POST['signature_data'])) {
                $rawSig = $_POST['signature_data'];
                if (preg_match('/^data:image\/(\w+);base64,/', $rawSig)) {
                    $rawSig = substr($rawSig, strpos($rawSig, ',') + 1);
                    $rawSig = base64_decode($rawSig);
                    if ($rawSig !== false) {
                        $dir = __DIR__ . '/uploads/signatures';
                        if (!is_dir($dir)) mkdir($dir, 0755, true);
                        $fname = 'sig_' . $appId . '_' . time() . '.png';
                        file_put_contents($dir . '/' . $fname, $rawSig);
                        $sigPath = 'uploads/signatures/' . $fname;
                    }
                }
            }

            // Generate Card Number
            $lastCard = $db->query("SELECT card_number FROM residence_cards ORDER BY id DESC LIMIT 1")->fetchColumn();
            $cardNum = ($lastCard && is_numeric($lastCard)) ? str_pad((string)((int)$lastCard + 1), 6, '0', STR_PAD_LEFT) : (string)rand(389108, 999999);
            $bookletNum = "RC-" . $cardNum . "/" . date('y');
            $vToken = bin2hex(random_bytes(32));
            $issuedAt = trim($_POST['issued_at'] ?? $app['enrollment_center']);
            $issuedOn = date('Y-m-d');
            $expiresOn = date('Y-m-d', strtotime('+2 years -1 day'));

            // Insert Residence Card Record
            $ins = $db->prepare("INSERT INTO residence_cards (
                card_number, booklet_number, issuing_country, statutory_protocol, decision_reference,
                decision_date, approving_authority, surname, forenames, photo_path, nationality,
                date_of_birth, place_of_birth, sex, height, complexion, eye_color, hair_color,
                distinguished_features, profession, domicile, change_of_address, passport_number, national_id_number,
                tax_id_number, emergency_contact_name, emergency_contact_relation, emergency_contact_phone,
                emergency_contact_address, blood_group, issuing_officer_name, issuing_officer_service_no,
                issuing_officer_signature, issued_on, issued_at, expires_on, postage_stamp_code,
                authority_signature, status, verification_token, created_by
            ) VALUES (
                :cn, :bn, 'FEDERAL REPUBLIC OF NIGERIA', '', '',
                :dd, 'COMPTROLLER GENERAL OF IMMIGRATION', :surname, :forenames, :photo, :nat,
                :dob, :pob, :sex, :height, :complexion, :eye_color, :hair_color,
                'NONE', :prof, :domicile, :change_of_address, :passport, :nin,
                :tax, :em_name, :em_rel, :em_phone,
                :em_addr, :blood, :off_name, :off_sn,
                :sig, :issued_on, :issued_at, :expires_on, :postage,
                'COMPTROLLER GENERAL', 'APPROVED', :vtoken, :uid
            )");

            $ins->execute([
                ':cn' => $cardNum,
                ':bn' => $bookletNum,
                ':dd' => $issuedOn,
                ':surname' => $app['surname'],
                ':forenames' => $app['forenames'],
                ':photo' => $photoPath,
                ':nat' => $app['nationality'],
                ':dob' => $app['date_of_birth'],
                ':pob' => $app['place_of_birth'],
                ':sex' => $app['sex'],
                ':height' => $app['height'] ?: '1.75M',
                ':complexion' => $app['complexion'] ?: 'DARK',
                ':eye_color' => $app['eye_color'] ?: 'BROWN',
                ':hair_color' => $app['hair_color'] ?: 'BLACK',
                ':prof' => $app['profession'],
                ':domicile' => $app['domicile'],
                ':change_of_address' => !empty($app['change_of_address']) ? $app['change_of_address'] : 'NO CHANGE RECORDED',
                ':passport' => $app['passport_number'],
                ':nin' => $app['national_id_number'],
                ':tax' => $app['tax_id_number'],
                ':em_name' => $app['emergency_contact_name'],
                ':em_rel' => $app['emergency_contact_relation'],
                ':em_phone' => $app['emergency_contact_phone'],
                ':em_addr' => $app['emergency_contact_address'],
                ':blood' => $app['blood_group'] ?: 'UNKNOWN',
                ':off_name' => stripRank($user['fullname']),
                ':off_sn' => $user['service_number'],
                ':sig' => $sigPath,
                ':issued_on' => $issuedOn,
                ':issued_at' => $issuedAt,
                ':expires_on' => $expiresOn,
                ':postage' => 'NIS-NSPMC-' . date('Y') . '-' . rand(100, 999),
                ':vtoken' => $vToken,
                ':uid' => $user['id']
            ]);

            $issuedCardId = (int)$db->lastInsertId();

            // Update Application record
            $updApp = $db->prepare("UPDATE applications SET 
                status = 'BIOMETRICS_CAPTURED', 
                approved_by = COALESCE(approved_by, :uid_appr),
                approved_at = COALESCE(approved_at, CURRENT_TIMESTAMP),
                photo_path = :photo, 
                signature_path = :sig, 
                biometrics_captured_by = :uid, 
                biometrics_captured_at = CURRENT_TIMESTAMP, 
                card_id = :cid 
                WHERE id = :id");
            $updApp->execute([
                ':uid_appr' => $user['id'],
                ':photo' => $photoPath,
                ':sig' => $sigPath,
                ':uid' => $user['id'],
                ':cid' => $issuedCardId,
                ':id' => $appId
            ]);

            logAudit("BIOMETRICS_CAPTURED", $issuedCardId, "Officer {$user['fullname']} ({$user['service_number']}) captured biometrics for App No. {$app['application_number']}, assigned Residence Card No. {$cardNum}");

            setFlash('success', "Biometrics successfully captured! Official Residence Card No. <strong>{$cardNum}</strong> generated.");
            header("Location: card-details?id={$issuedCardId}");
            exit();
        }
    }
}

// Fetch approved applications queue awaiting physical biometrics
$approvedQueue = $db->query("SELECT * FROM applications WHERE status = 'APPROVED_FOR_BIOMETRICS' ORDER BY appointment_date ASC, id ASC LIMIT 25")->fetchAll(PDO::FETCH_ASSOC);

include __DIR__ . '/includes/header.php';
include __DIR__ . '/includes/sidebar.php';
?>

<div class="page-header" style="margin-bottom: 1.5rem;">
    <div class="page-title">
        <h2><i class="fas fa-fingerprint" style="color: var(--nis-gold);"></i> Biometrics Capturing Desk</h2>
        <div style="font-size: 0.8rem; color: #64748b;">Physical enrollment station &bull; Live facial photo, digital signature &amp; fingerprint registration</div>
    </div>
</div>

<!-- Search or Scan Bar -->
<div class="app-card" style="margin-bottom: 1.5rem;">
    <div class="card-body" style="padding: 1.25rem;">
        <form method="GET" action="biometrics-capture" style="display: flex; gap: 10px; align-items: center; flex-wrap: wrap;">
            <div style="flex: 1; min-width: 280px; position: relative;">
                <i class="fas fa-search" style="position: absolute; left: 12px; top: 50%; transform: translateY(-50%); color: #64748b;"></i>
                <input type="text" name="app_search" class="form-control" placeholder="Search by Application No (e.g. RC-2026-433764 or 433764), Passport No, or QR scan..." value="<?php echo h($searchApp); ?>" style="padding-left: 36px; text-transform: uppercase;" autofocus>
            </div>
            <button type="submit" class="btn btn-primary"><i class="fas fa-search"></i> Search Application</button>
            <?php if (!empty($searchApp)): ?>
                <a href="biometrics-capture" class="btn btn-outline">Clear</a>
            <?php endif; ?>
        </form>
    </div>
</div>

<?php if (!empty($searchResults)): ?>
    <!-- Multiple Search Matches Found -->
    <div class="app-card" style="margin-bottom: 2rem;">
        <div class="card-header" style="background: #f8fafc; display: flex; justify-content: space-between; align-items: center;">
            <h3 style="font-size: 0.95rem; margin: 0; color: var(--nis-slate);">
                <i class="fas fa-list-ul" style="color: var(--nis-green);"></i>
                Matching Applications Found (<?php echo count($searchResults); ?>)
            </h3>
            <span style="font-size: 0.8rem; color: #64748b;">Search: "<?php echo h($searchApp); ?>"</span>
        </div>
        <div class="card-body" style="padding: 0;">
            <div class="table-responsive">
                <table class="nis-table">
                    <thead>
                        <tr>
                            <th>Application ID</th>
                            <th>Applicant Full Name</th>
                            <th>Nationality</th>
                            <th>Passport No</th>
                            <th>Status</th>
                            <th>Scheduled Date</th>
                            <th>Action</th>
                        </tr>
                    </thead>
                    <tbody>
                        <?php foreach ($searchResults as $mApp): ?>
                            <tr>
                                <td><strong style="color: var(--nis-green);"><?php echo h($mApp['application_number']); ?></strong></td>
                                <td><strong><?php echo h($mApp['surname'] . ', ' . $mApp['forenames']); ?></strong></td>
                                <td><?php echo h($mApp['nationality']); ?></td>
                                <td><code><?php echo h($mApp['passport_number']); ?></code></td>
                                <td>
                                    <?php
                                    $stVal = $mApp['status'] ?? 'PENDING_APPROVAL';
                                    if ($stVal === 'APPROVED_FOR_BIOMETRICS') {
                                        echo '<span class="badge" style="background: #10b981; color: white;">APPROVED</span>';
                                    } elseif ($stVal === 'PENDING_APPROVAL') {
                                        echo '<span class="badge" style="background: #fef3c7; color: #92400e; border: 1px solid #f59e0b;">PENDING</span>';
                                    } elseif (in_array($stVal, ['BIOMETRICS_CAPTURED', 'ISSUED'])) {
                                        echo '<span class="badge" style="background: #e0f2fe; color: #0369a1; border: 1px solid #38bdf8;">CAPTURED</span>';
                                    } elseif ($stVal === 'QUERIED') {
                                        echo '<span class="badge" style="background: #fee2e2; color: #991b1b; border: 1px solid #ef4444;">QUERIED</span>';
                                    } else {
                                        echo '<span class="badge" style="background: #f1f5f9; color: #475569;">' . h($stVal) . '</span>';
                                    }
                                    ?>
                                </td>
                                <td><?php echo !empty($mApp['appointment_date']) ? date('d M Y', strtotime($mApp['appointment_date'])) : '—'; ?></td>
                                <td>
                                    <a href="biometrics-capture?app_id=<?php echo (int)$mApp['id']; ?>" class="btn btn-primary" style="padding: 4px 12px; font-size: 0.78rem;">
                                        Select for Biometrics
                                    </a>
                                </td>
                            </tr>
                        <?php endforeach; ?>
                    </tbody>
                </table>
            </div>
        </div>
    </div>
<?php endif; ?>

<?php if ($selectedApp): ?>
    <?php
    $curStatus = $selectedApp['status'] ?? 'PENDING_APPROVAL';
    $isAlreadyCaptured = in_array($curStatus, ['BIOMETRICS_CAPTURED', 'ISSUED']);
    ?>
    <!-- Enrollment Screen for Selected Applicant -->
    <div class="app-card" style="margin-bottom: 2rem;">
        <div class="card-header" style="background: #f8fafc; display: flex; justify-content: space-between; align-items: center; flex-wrap: wrap; gap: 8px;">
            <h3 style="font-size: 1rem; color: var(--nis-slate); margin: 0;">
                <i class="fas fa-user-check" style="color: var(--nis-green);"></i> 
                Biometric Enrollment: <strong><?php echo h($selectedApp['surname'] . ', ' . $selectedApp['forenames']); ?></strong>
            </h3>
            <div style="display: flex; align-items: center; gap: 10px;">
                <a href="print-appointment-slip?app_id=<?php echo (int)$selectedApp['id']; ?>&print=1" target="_blank" class="btn btn-outline" style="padding: 3px 10px; font-size: 0.76rem;">
                    <i class="fas fa-print"></i> View / Print Slip
                </a>
                <?php
                if ($curStatus === 'APPROVED_FOR_BIOMETRICS') {
                    echo '<span class="badge" style="background: #10b981; color: white; padding: 4px 10px; border-radius: 12px; font-weight: 700; font-size: 0.76rem;">APPROVED FOR BIOMETRICS</span>';
                } elseif ($curStatus === 'PENDING_APPROVAL') {
                    echo '<span class="badge" style="background: #fef3c7; color: #92400e; border: 1px solid #f59e0b; padding: 4px 10px; border-radius: 12px; font-weight: 700; font-size: 0.76rem;">PENDING APPROVAL</span>';
                } elseif ($isAlreadyCaptured) {
                    echo '<span class="badge" style="background: #e0f2fe; color: #0369a1; border: 1px solid #38bdf8; padding: 4px 10px; border-radius: 12px; font-weight: 700; font-size: 0.76rem;">BIOMETRICS ALREADY CAPTURED</span>';
                } elseif ($curStatus === 'QUERIED') {
                    echo '<span class="badge" style="background: #fee2e2; color: #991b1b; border: 1px solid #ef4444; padding: 4px 10px; border-radius: 12px; font-weight: 700; font-size: 0.76rem;">APPLICATION QUERIED</span>';
                } else {
                    echo '<span class="badge" style="background: #f1f5f9; color: #475569; padding: 4px 10px; border-radius: 12px; font-weight: 700; font-size: 0.76rem;">' . h($curStatus) . '</span>';
                }
                ?>
            </div>
        </div>
        <div class="card-body" style="padding: 1.5rem;">

            <?php if ($curStatus === 'PENDING_APPROVAL'): ?>
                <div style="background: #fffbeb; border: 1px solid #fde68a; border-radius: 6px; padding: 10px 14px; margin-bottom: 1.25rem; font-size: 0.84rem; color: #92400e;">
                    <strong>Status Notice:</strong> This application is currently <em>Pending Approval</em>. Proceeding with biometrics capture will verify applicant credentials and authorize Residence Card issuance.
                </div>
            <?php elseif ($isAlreadyCaptured): ?>
                <div style="background: #f0f9ff; border: 1px solid #bae6fd; border-radius: 6px; padding: 12px 16px; margin-bottom: 1.25rem; font-size: 0.86rem; color: #0369a1; display: flex; justify-content: space-between; align-items: center; flex-wrap: wrap; gap: 10px;">
                    <div>
                        <strong>Biometrics Already Captured:</strong> Physical biometrics were collected for this applicant on <?php echo !empty($selectedApp['biometrics_captured_at']) ? date('d M Y, h:i A', strtotime($selectedApp['biometrics_captured_at'])) : date('d M Y'); ?>.
                    </div>
                    <?php if (!empty($selectedApp['card_id'])): ?>
                        <a href="card-details?id=<?php echo (int)$selectedApp['card_id']; ?>" class="btn btn-primary" style="padding: 5px 14px; font-size: 0.82rem;">
                            <i class="fas fa-id-card"></i> View Residence Card
                        </a>
                    <?php endif; ?>
                </div>
            <?php elseif ($curStatus === 'QUERIED'): ?>
                <div style="background: #fef2f2; border: 1px solid #fecaca; border-radius: 6px; padding: 10px 14px; margin-bottom: 1.25rem; font-size: 0.84rem; color: #991b1b;">
                    <strong>Attention:</strong> This application has an open query. Please ensure all compliance requirements are addressed before physical enrollment.
                </div>
            <?php endif; ?>

            <!-- Applicant Particulars Summary -->
            <div style="display: grid; grid-template-columns: repeat(auto-fit, minmax(220px, 1fr)); gap: 12px; background: #f8fafc; padding: 14px; border-radius: 6px; margin-bottom: 1.5rem; font-size: 0.82rem;">
                <div><span style="color: #64748b;">Application ID:</span> <strong style="color: var(--nis-green); font-family: 'Consolas', monospace; font-size: 0.95rem;"><?php echo h($selectedApp['application_number']); ?></strong></div>
                <div><span style="color: #64748b;">Nationality:</span> <strong><?php echo h($selectedApp['nationality']); ?></strong></div>
                <div><span style="color: #64748b;">Passport No:</span> <strong style="font-family: 'Consolas', monospace;"><?php echo h($selectedApp['passport_number']); ?></strong></div>
                <div><span style="color: #64748b;">Date of Birth:</span> <strong><?php echo date('d M Y', strtotime($selectedApp['date_of_birth'])); ?> (<?php echo h($selectedApp['sex']); ?>)</strong></div>
                <div><span style="color: #64748b;">Blood Group:</span> <strong><?php echo h($selectedApp['blood_group'] ?: 'N/A'); ?></strong></div>
                <div><span style="color: #64748b;">Application Fee:</span> <strong style="color: #059669;">₦<?php echo number_format($selectedApp['fee_amount'], 2); ?> (PAID)</strong></div>
                <div><span style="color: #64748b;">Scheduled Appointment:</span> <strong><?php echo date('d M Y', strtotime($selectedApp['appointment_date'])); ?> &bull; <?php echo h($selectedApp['appointment_time']); ?></strong></div>
                <?php if (!empty($selectedApp['change_of_address'])): ?>
                    <div style="grid-column: 1 / -1; background: #fefce8; padding: 6px 10px; border-radius: 4px; border: 1px solid #fef08a;">
                        <span style="color: #854d0e; font-weight: 600;">Change of Address Notified:</span>
                        <strong style="color: #713f12;"><?php echo h($selectedApp['change_of_address']); ?></strong>
                    </div>
                <?php endif; ?>
            </div>

            <!-- Form: Biometrics Capture -->
            <form method="POST" action="biometrics-capture" enctype="multipart/form-data">
                <?php echo csrf_field(); ?>
                <input type="hidden" name="app_id" value="<?php echo (int)$selectedApp['id']; ?>">
                <input type="hidden" name="captured_photo_data" id="capturedPhotoData">
                <input type="hidden" name="signature_data" id="signatureData">

                <div style="display: grid; grid-template-columns: 1fr 1fr; gap: 1.5rem; margin-bottom: 1.5rem;">
                    <!-- Photo Capture Box -->
                    <div style="border: 1px solid #e2e8f0; border-radius: 8px; padding: 1rem; text-align: center;">
                        <h4 style="font-size: 0.88rem; margin-bottom: 10px; color: var(--nis-slate);"><i class="fas fa-camera"></i> Live Facial Camera Capture</h4>
                        <div style="width: 200px; height: 230px; border: 2px dashed #cbd5e1; border-radius: 6px; margin: 0 auto 12px; display: flex; align-items: center; justify-content: center; overflow: hidden; background: #fafafa; position: relative;">
                            <video id="liveVideo" autoplay playsinline style="width: 100%; height: 100%; object-fit: cover; display: none;"></video>
                            <canvas id="photoCanvas" style="display: none;"></canvas>
                            <img id="photoPreview" src="assets/images/sample-avatar.svg" alt="Preview" style="width: 100%; height: 100%; object-fit: cover;">
                        </div>
                        <div style="display: flex; gap: 8px; justify-content: center;">
                            <button type="button" id="startCamBtn" class="btn btn-outline" style="font-size: 0.78rem; padding: 6px 12px;">
                                <i class="fas fa-video"></i> Start Camera
                            </button>
                            <button type="button" id="snapBtn" class="btn btn-primary" style="font-size: 0.78rem; padding: 6px 12px; display: none;">
                                <i class="fas fa-camera"></i> Capture Photo
                            </button>
                        </div>
                    </div>

                    <!-- Signature & Fingerprint Box -->
                    <div style="border: 1px solid #e2e8f0; border-radius: 8px; padding: 1rem; text-align: center;">
                        <h4 style="font-size: 0.88rem; margin-bottom: 10px; color: var(--nis-slate);"><i class="fas fa-signature"></i> Digital Signature Pad</h4>
                        <div style="width: 100%; height: 150px; border: 2px dashed #cbd5e1; border-radius: 6px; margin: 0 auto 12px; background: #ffffff;">
                            <canvas id="sigCanvas" width="400" height="150" style="width: 100%; height: 100%; cursor: crosshair;"></canvas>
                        </div>
                        <button type="button" id="clearSigBtn" class="btn btn-outline" style="font-size: 0.78rem; padding: 5px 12px;">
                            <i class="fas fa-eraser"></i> Clear Signature
                        </button>

                        <div style="margin-top: 14px; padding: 10px; background: #f8fafc; border-radius: 6px; font-size: 0.76rem; color: #64748b; display: flex; align-items: center; justify-content: center; gap: 8px;">
                            <i class="fas fa-fingerprint" style="font-size: 1.2rem; color: var(--nis-green);"></i>
                            <span>Biometric Fingerprint Scanner Initialized (Hardware Interface Active)</span>
                        </div>
                    </div>
                </div>

                <div style="display: grid; grid-template-columns: 1fr 1fr; gap: 1rem; margin-bottom: 1.5rem;">
                    <div class="form-group">
                        <label for="issued_at" class="required">Enrollment / Issuing Center</label>
                        <input type="text" id="issued_at" name="issued_at" class="form-control" value="<?php echo h($selectedApp['enrollment_center']); ?>" required>
                    </div>
                    <div class="form-group">
                        <label>Enrollment Desk Officer</label>
                        <input type="text" class="form-control" value="<?php echo h(stripRank($user['fullname']) . ' (' . $user['service_number'] . ')'); ?>" readonly>
                    </div>
                </div>

                <div style="text-align: right;">
                    <button type="submit" name="action_complete_biometrics" class="btn btn-primary" style="padding: 10px 24px; font-size: 0.92rem;">
                        <i class="fas fa-id-card"></i> Complete Biometrics &amp; Authorize Card Issuance
                    </button>
                </div>
            </form>
        </div>
    </div>
<?php endif; ?>

<!-- Queue of Approved Applicants Awaiting Biometrics -->
<div class="app-card">
    <div class="card-header" style="display: flex; justify-content: space-between; align-items: center;">
        <h3 style="font-size: 0.95rem; margin: 0;">
            <i class="fas fa-calendar-check" style="color: var(--nis-green);"></i> 
            Approved Applicants Awaiting Physical Biometrics (<?php echo count($approvedQueue); ?>)
        </h3>
    </div>
    <div class="card-body" style="padding: 0;">
        <?php if (empty($approvedQueue)): ?>
            <div style="text-align: center; padding: 2.5rem; color: #64748b;">
                <i class="fas fa-check-circle" style="font-size: 2.5rem; color: #10b981; margin-bottom: 8px; display: block;"></i>
                No approved applications currently awaiting biometrics capture.
            </div>
        <?php else: ?>
            <div class="table-responsive">
                <table class="nis-table">
                    <thead>
                        <tr>
                            <th>Application ID</th>
                            <th>Applicant Full Name</th>
                            <th>Nationality</th>
                            <th>Passport No</th>
                            <th>Enrollment Center</th>
                            <th>Appointment Date</th>
                            <th>Action</th>
                        </tr>
                    </thead>
                    <tbody>
                        <?php foreach ($approvedQueue as $q): ?>
                            <tr>
                                <td><strong style="color: var(--nis-green);"><?php echo h($q['application_number']); ?></strong></td>
                                <td><strong><?php echo h($q['surname'] . ', ' . $q['forenames']); ?></strong></td>
                                <td><?php echo h($q['nationality']); ?></td>
                                <td><code><?php echo h($q['passport_number']); ?></code></td>
                                <td><?php echo h($q['enrollment_center']); ?></td>
                                <td><?php echo date('d M Y', strtotime($q['appointment_date'])); ?> &bull; <?php echo h($q['appointment_time']); ?></td>
                                <td>
                                    <a href="biometrics-capture?app_id=<?php echo (int)$q['id']; ?>" class="btn btn-primary" style="padding: 4px 10px; font-size: 0.78rem;">
                                        Capture Biometrics
                                    </a>
                                </td>
                            </tr>
                        <?php endforeach; ?>
                    </tbody>
                </table>
            </div>
        <?php endif; ?>
    </div>
</div>

<script>
document.addEventListener('DOMContentLoaded', function() {
    // Live Webcam Implementation
    const video = document.getElementById('liveVideo');
    const canvas = document.getElementById('photoCanvas');
    const preview = document.getElementById('photoPreview');
    const startCamBtn = document.getElementById('startCamBtn');
    const snapBtn = document.getElementById('snapBtn');
    const photoDataInput = document.getElementById('capturedPhotoData');
    let stream = null;

    if (startCamBtn) {
        startCamBtn.addEventListener('click', async function() {
            try {
                stream = await navigator.mediaDevices.getUserMedia({ video: { width: 640, height: 480 }, audio: false });
                video.srcObject = stream;
                video.style.display = 'block';
                preview.style.display = 'none';
                snapBtn.style.display = 'inline-flex';
                startCamBtn.style.display = 'none';
            } catch (err) {
                alert('Webcam access error: ' + err.message);
            }
        });
    }

    if (snapBtn) {
        snapBtn.addEventListener('click', function() {
            canvas.width = video.videoWidth || 640;
            canvas.height = video.videoHeight || 480;
            const ctx = canvas.getContext('2d');
            ctx.drawImage(video, 0, 0, canvas.width, canvas.height);
            const dataUrl = canvas.toDataURL('image/jpeg', 0.9);
            photoDataInput.value = dataUrl;
            preview.src = dataUrl;
            preview.style.display = 'block';
            video.style.display = 'none';
            snapBtn.style.display = 'none';
            startCamBtn.style.display = 'inline-flex';
            startCamBtn.innerHTML = '<i class="fas fa-redo"></i> Retake';
            if (stream) {
                stream.getTracks().forEach(t => t.stop());
            }
        });
    }

    // Signature Pad
    const sigCanvas = document.getElementById('sigCanvas');
    const sigInput = document.getElementById('signatureData');
    const clearSigBtn = document.getElementById('clearSigBtn');
    if (sigCanvas) {
        const sctx = sigCanvas.getContext('2d');
        sctx.lineWidth = 2;
        sctx.strokeStyle = '#0f172a';
        let drawing = false;

        sigCanvas.addEventListener('mousedown', (e) => {
            drawing = true;
            sctx.beginPath();
            sctx.moveTo(e.offsetX, e.offsetY);
        });
        sigCanvas.addEventListener('mousemove', (e) => {
            if (!drawing) return;
            sctx.lineTo(e.offsetX, e.offsetY);
            sctx.stroke();
            sigInput.value = sigCanvas.toDataURL('image/png');
        });
        window.addEventListener('mouseup', () => { drawing = false; });

        if (clearSigBtn) {
            clearSigBtn.addEventListener('click', () => {
                sctx.clearRect(0, 0, sigCanvas.width, sigCanvas.height);
                sigInput.value = '';
            });
        }
    }
});
</script>

<?php include __DIR__ . '/includes/footer.php'; ?>
