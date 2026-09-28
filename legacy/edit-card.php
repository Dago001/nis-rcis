<?php
/**
 * NIS Residence Card Issuance System (NIS-RCIS)
 * Edit Residence Card Particulars
 */

require_once __DIR__ . '/includes/auth.php';
require_once __DIR__ . '/config/database.php';
require_once __DIR__ . '/config/constants.php';
require_once __DIR__ . '/includes/security.php';

// Only authorized officers can edit cards
requireRole([ROLE_SUPER_ADMIN, ROLE_APPROVING_OFFICER, ROLE_ISSUING_OFFICER]);

$pageTitle = 'Edit Residence Card';
$db = Database::getConnection();
$user = currentUser();
$errors = [];
$successMsg = '';

$cardId = (int)($_GET['id'] ?? ($_POST['card_id'] ?? 0));
if ($cardId <= 0) {
    header('Location: view-cards?error=' . urlencode('Invalid card identifier specified.'));
    exit;
}

// Fetch existing record
$stmt = $db->prepare("SELECT * FROM residence_cards WHERE id = :id LIMIT 1");
$stmt->execute([':id' => $cardId]);
$card = $stmt->fetch(PDO::FETCH_ASSOC);

if (!$card) {
    header('Location: view-cards?error=' . urlencode('The specified residence card record was not found.'));
    exit;
}

// Handle Form Submission
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['update_card'])) {
    if (!validate_csrf()) {
        $errors[] = 'Security token validation failed. Please submit the form again.';
    } else {
        // Biometrics & Personal Particulars
        $surname = strtoupper(trim($_POST['surname'] ?? ''));
        $forenames = strtoupper(trim($_POST['forenames'] ?? ''));
        $nationality = strtoupper(trim($_POST['nationality'] ?? ''));
        if ($nationality === 'OTHER') {
            $otherNat = strtoupper(trim($_POST['other_nationality'] ?? ''));
            if (!empty($otherNat)) {
                $nationality = $otherNat;
            }
        }
        $dateOfBirth = trim($_POST['date_of_birth'] ?? '');
        $placeOfBirth = strtoupper(trim($_POST['place_of_birth'] ?? ''));
        $sex = strtoupper(trim($_POST['sex'] ?? 'MALE'));
        $height = strtoupper(trim($_POST['height'] ?? ''));
        $complexion = strtoupper(trim($_POST['complexion'] ?? 'DARK'));
        $eyeColor = strtoupper(trim($_POST['eye_color'] ?? 'BLACK'));
        $hairColor = strtoupper(trim($_POST['hair_color'] ?? 'BLACK'));
        $distFeatures = strtoupper(trim($_POST['distinguished_features'] ?? 'NONE'));
        if (empty($distFeatures)) $distFeatures = 'NONE';
        $profession = strtoupper(trim($_POST['profession'] ?? ''));
        $domicile = strtoupper(trim($_POST['domicile'] ?? ''));
        $passportNumber = strtoupper(trim($_POST['passport_number'] ?? ''));
        $nationalIdNumber = trim($_POST['national_id_number'] ?? '');
        $taxIdNumber = trim($_POST['tax_id_number'] ?? '');

        // Emergency Contact
        $emergencyName = strtoupper(trim($_POST['emergency_contact_name'] ?? ''));
        $emergencyRelation = strtoupper(trim($_POST['emergency_contact_relation'] ?? ''));
        $emergencyPhone = trim($_POST['emergency_contact_phone'] ?? '');
        $emergencyAddress = strtoupper(trim($_POST['emergency_contact_address'] ?? ''));
        $bloodGroup = strtoupper(trim($_POST['blood_group'] ?? 'UNKNOWN'));
        $changeOfAddress = strtoupper(trim($_POST['change_of_address'] ?? ''));
        if (empty($changeOfAddress)) $changeOfAddress = 'NO CHANGE RECORDED';

        // Validity & Command Dates
        $issuedOn = trim($_POST['issued_on'] ?? $card['issued_on']);
        $expiresOn = trim($_POST['expires_on'] ?? $card['expires_on']);
        $issuedAt = strtoupper(trim($_POST['issued_at'] ?? $card['issued_at']));
        $status = strtoupper(trim($_POST['status'] ?? $card['status']));

        // SuperAdmin can optionally update Card Number
        $cardNumber = $card['card_number'];
        if ($user['role'] === ROLE_SUPER_ADMIN && !empty(trim($_POST['card_number'] ?? ''))) {
            $newCardNum = strtoupper(trim($_POST['card_number']));
            if ($newCardNum !== $card['card_number']) {
                $chkCn = $db->prepare("SELECT id FROM residence_cards WHERE card_number = :cn AND id != :id LIMIT 1");
                $chkCn->execute([':cn' => $newCardNum, ':id' => $cardId]);
                if ($chkCn->fetch()) {
                    $errors[] = "Residence Card Number '{$newCardNum}' is already assigned to another record.";
                } else {
                    $cardNumber = $newCardNum;
                }
            }
        }

        // Server-Side Validation according to Immigration regulations
        if (empty($surname)) {
            $errors[] = 'Applicant Surname is mandatory.';
        } elseif (!preg_match("/^[\p{L}\s'\-]{2,50}$/u", $surname)) {
            $errors[] = 'Applicant Surname must only contain letters, spaces, hyphens, or apostrophes (numbers and special symbols are not allowed).';
        }

        if (empty($forenames)) {
            $errors[] = 'Applicant Given Name is mandatory.';
        } elseif (!preg_match("/^[\p{L}\s'\-]{2,50}$/u", $forenames)) {
            $errors[] = 'Applicant Given Name must only contain letters, spaces, hyphens, or apostrophes (numbers and special symbols are not allowed).';
        }

        if (empty($nationality)) {
            $errors[] = 'Nationality is mandatory.';
        } elseif ($nationality === 'OTHER' && empty($otherNat)) {
            $errors[] = 'Please specify the applicant\'s nationality.';
        }

        if (empty($dateOfBirth)) {
            $errors[] = 'Date of Birth is mandatory.';
        } else {
            $dobDateTime = DateTime::createFromFormat('Y-m-d', $dateOfBirth);
            $today = new DateTime('today');
            if (!$dobDateTime || $dobDateTime->format('Y-m-d') !== $dateOfBirth) {
                $errors[] = 'Date of Birth is invalid.';
            } elseif ($dobDateTime > $today) {
                $errors[] = 'Date of Birth cannot be in the future.';
            } elseif ((int)$dobDateTime->format('Y') < 1900) {
                $errors[] = 'Date of Birth year must be 1900 or later.';
            }
        }

        if (empty($placeOfBirth)) {
            $errors[] = 'Place of Birth is mandatory.';
        } elseif (!preg_match("/^[\p{L}\s'\-,\.]{2,60}$/u", $placeOfBirth)) {
            $errors[] = 'Place of Birth must only contain valid location text.';
        }

        if (empty($sex) || !in_array($sex, ['MALE', 'FEMALE'])) {
            $errors[] = 'Valid Sex specification is mandatory.';
        }

        if (empty($profession)) {
            $errors[] = 'Profession is mandatory.';
        } elseif (!preg_match("/^[\p{L}\s'\-\/\.]{2,60}$/u", $profession)) {
            $errors[] = 'Profession must only contain valid alphabetical text.';
        }

        // Passport number validation & Duplicate check (excluding current card)
        if (empty($passportNumber)) {
            $errors[] = 'Passport Number is mandatory.';
        } elseif (!preg_match('/^[A-Z0-9]{6,15}$/', $passportNumber)) {
            $errors[] = 'Passport Number must be 6 to 15 alphanumeric characters without spaces or symbols.';
        } else {
            $passStmt = $db->prepare("SELECT id, card_number, surname, forenames FROM residence_cards WHERE UPPER(passport_number) = :pn AND id != :id LIMIT 1");
            $passStmt->execute([':pn' => $passportNumber, ':id' => $cardId]);
            $existingPassport = $passStmt->fetch(PDO::FETCH_ASSOC);
            if ($existingPassport) {
                $errors[] = "A residence card (No. {$existingPassport['card_number']}) is already registered for Passport Number '{$passportNumber}' ({$existingPassport['surname']}, {$existingPassport['forenames']}). Duplicate passport numbers are strictly prohibited.";
            }
        }

        if (empty($domicile)) {
            $errors[] = 'Address (residential address in Nigeria) is mandatory.';
        } elseif (strlen($domicile) < 5) {
            $errors[] = 'Address must be at least 5 characters.';
        }

        // Emergency Contact validation
        if (empty($emergencyName)) {
            $errors[] = 'Emergency Contact Name is mandatory.';
        } elseif (!preg_match("/^[\p{L}\s'\-]{2,70}$/u", $emergencyName)) {
            $errors[] = 'Emergency Contact Name must only contain letters, spaces, hyphens, or apostrophes (numbers and special symbols are not allowed).';
        }

        if (empty($emergencyRelation)) {
            $errors[] = 'Emergency Contact Relationship is mandatory.';
        }

        // Phone Number validation according to E.164 international standard (7 to 15 digits)
        if (empty($emergencyPhone)) {
            $errors[] = 'Emergency Contact Phone Number is mandatory.';
        } else {
            $cleanPhoneDigits = preg_replace('/[^0-9]/', '', $emergencyPhone);
            if (!preg_match('/^\+?[0-9\s\-()]{7,25}$/', $emergencyPhone) || strlen($cleanPhoneDigits) < 7 || strlen($cleanPhoneDigits) > 15) {
                $errors[] = 'Emergency Contact Phone Number must be a valid international telephone number with 7 to 15 digits (e.g. +234 801 234 5678).';
            }
        }

        if (empty($emergencyAddress)) {
            $errors[] = 'Emergency Contact Address is mandatory.';
        }

        // Validity Dates
        if (empty($issuedOn)) {
            $errors[] = 'Issued On date is mandatory.';
        }
        if (empty($expiresOn)) {
            $errors[] = 'Expiration Date is mandatory.';
        } else {
            $issDateTime = DateTime::createFromFormat('Y-m-d', $issuedOn);
            $expDateTime = DateTime::createFromFormat('Y-m-d', $expiresOn);
            if ($issDateTime && $expDateTime && $expDateTime <= $issDateTime) {
                $errors[] = 'Card Expiration Date must be strictly after the Issued On date.';
            }
        }

        // Handle Photo Upload (if provided)
        $photoPath = $card['photo_path'];
        $photoBase64 = trim($_POST['photo_base64'] ?? '');
        if (!empty($photoBase64) && str_starts_with($photoBase64, 'data:image/')) {
            $uploadDir = __DIR__ . '/uploads/photos/';
            if (!is_dir($uploadDir)) {
                mkdir($uploadDir, 0755, true);
            }
            $imgParts = explode(';base64,', $photoBase64);
            if (count($imgParts) === 2) {
                $imageType = str_replace('data:image/', '', $imgParts[0]);
                $imgExt = in_array($imageType, ['jpeg', 'jpg', 'png', 'webp']) ? $imageType : 'jpg';
                $newFileName = 'photo_' . $cardNumber . '_' . time() . '.' . $imgExt;
                $decodedImg = base64_decode($imgParts[1]);
                if ($decodedImg !== false) {
                    file_put_contents($uploadDir . $newFileName, $decodedImg);
                    $photoPath = 'uploads/photos/' . $newFileName;
                }
            }
        } elseif (isset($_FILES['photo_file']) && $_FILES['photo_file']['error'] === UPLOAD_ERR_OK) {
            $uploadDir = __DIR__ . '/uploads/photos/';
            if (!is_dir($uploadDir)) {
                mkdir($uploadDir, 0755, true);
            }
            $finfo = finfo_open(FILEINFO_MIME_TYPE);
            $mimeType = finfo_file($finfo, $_FILES['photo_file']['tmp_name']);
            finfo_close($finfo);

            $allowedMimes = ['image/jpeg' => 'jpg', 'image/png' => 'png', 'image/webp' => 'webp'];
            if (isset($allowedMimes[$mimeType])) {
                $ext = $allowedMimes[$mimeType];
                $newFileName = 'photo_' . $cardNumber . '_' . time() . '.' . $ext;
                if (move_uploaded_file($_FILES['photo_file']['tmp_name'], $uploadDir . $newFileName)) {
                    $photoPath = 'uploads/photos/' . $newFileName;
                }
            } else {
                $errors[] = 'Invalid photo format. Only JPG, PNG, and WebP are allowed.';
            }
        }

        if (empty($errors)) {
            $updateStmt = $db->prepare("UPDATE residence_cards SET 
                card_number = :card_number,
                surname = :surname,
                forenames = :forenames,
                photo_path = :photo_path,
                nationality = :nationality,
                date_of_birth = :date_of_birth,
                place_of_birth = :place_of_birth,
                sex = :sex,
                height = :height,
                complexion = :complexion,
                eye_color = :eye_color,
                hair_color = :hair_color,
                distinguished_features = :distinguished_features,
                profession = :profession,
                domicile = :domicile,
                passport_number = :passport_number,
                national_id_number = :national_id_number,
                tax_id_number = :tax_id_number,
                emergency_contact_name = :emergency_contact_name,
                emergency_contact_relation = :emergency_contact_relation,
                emergency_contact_phone = :emergency_contact_phone,
                emergency_contact_address = :emergency_contact_address,
                blood_group = :blood_group,
                change_of_address = :change_of_address,
                issued_on = :issued_on,
                issued_at = :issued_at,
                expires_on = :expires_on,
                status = :status,
                updated_at = CURRENT_TIMESTAMP
                WHERE id = :id");

            $updateStmt->execute([
                ':card_number' => $cardNumber,
                ':surname' => $surname,
                ':forenames' => $forenames,
                ':photo_path' => $photoPath,
                ':nationality' => $nationality,
                ':date_of_birth' => $dateOfBirth,
                ':place_of_birth' => $placeOfBirth,
                ':sex' => $sex,
                ':height' => $height,
                ':complexion' => $complexion,
                ':eye_color' => $eyeColor,
                ':hair_color' => $hairColor,
                ':distinguished_features' => $distFeatures,
                ':profession' => $profession,
                ':domicile' => $domicile,
                ':passport_number' => $passportNumber,
                ':national_id_number' => $nationalIdNumber,
                ':tax_id_number' => $taxIdNumber,
                ':emergency_contact_name' => $emergencyName,
                ':emergency_contact_relation' => $emergencyRelation,
                ':emergency_contact_phone' => $emergencyPhone,
                ':emergency_contact_address' => $emergencyAddress,
                ':blood_group' => $bloodGroup,
                ':change_of_address' => $changeOfAddress,
                ':issued_on' => $issuedOn,
                ':issued_at' => $issuedAt,
                ':expires_on' => $expiresOn,
                ':status' => $status,
                ':id' => $cardId
            ]);

            // Audit log
            logAudit('CARD_UPDATED', $cardId, "Residence Card No. {$cardNumber} particulars updated by Officer {$user['service_number']}");

            // Dispatch Notifications if resubmitted for approval
            if ($status === 'PENDING_APPROVAL') {
                require_once __DIR__ . '/includes/notifications.php';
                notifyApprovers(
                    NOTIF_APPROVAL_REQUEST,
                    'Rectified Card Resubmitted for Approval',
                    "Residence Card No. {$cardNumber} for {$surname}, {$forenames} has been updated and resubmitted for approval.",
                    "card-details?id={$cardId}",
                    (int)$user['id'],
                    $db
                );
                createNotification(
                    (int)$user['id'],
                    NOTIF_APPROVAL_SENT,
                    'Resubmitted for Approval',
                    "Residence Card No. {$cardNumber} for {$surname}, {$forenames} was resubmitted for Comptroller approval.",
                    "card-details?id={$cardId}",
                    $db
                );
            }

            header("Location: card-details?id={$cardId}&updated=1");
            exit;
        }
    }
}

// Refresh card if not updated or if errors occurred
$stmt->execute([':id' => $cardId]);
$card = $stmt->fetch(PDO::FETCH_ASSOC);

require_once __DIR__ . '/includes/header.php';
require_once __DIR__ . '/includes/sidebar.php';
?>

<div class="page-title-box">
    <div>
        <h2>
            <i class="fas fa-edit"></i> Edit Residence Card: 
            <span style="color: var(--nis-green-dark); font-family: monospace;">No. <?php echo h($card['card_number']); ?></span>
        </h2>
        <div class="subtitle">
            <?php echo h($card['surname'] . ', ' . $card['forenames']); ?> &bull; <?php echo h($card['nationality']); ?> &bull; Passport: <code style="color: var(--nis-navy); font-weight: 700;"><?php echo h($card['passport_number']); ?></code>
        </div>
    </div>
    <div style="display: flex; gap: 8px; flex-wrap: wrap;">
        <a href="card-details?id=<?php echo $cardId; ?>" class="btn btn-outline">
            <i class="fas fa-arrow-left"></i> Back to File
        </a>
        <a href="view-cards" class="btn btn-outline">
            <i class="fas fa-list"></i> Directory
        </a>
    </div>
</div>

<?php if ($card['status'] === 'QUERIED' && !empty($card['rejection_reason'])): ?>
    <div class="alert alert-warning" style="border-left: 6px solid #d97706; margin-bottom: 1.5rem; background: #fffbeb; padding: 14px 18px; border-radius: 6px;">
        <div style="display: flex; align-items: flex-start; gap: 12px;">
            <i class="fas fa-exclamation-circle" style="font-size: 1.3rem; color: #d97706; margin-top: 2px;"></i>
            <div>
                <strong style="font-size: 0.96rem; color: #92400e;">Comptroller Query Directive to Rectify:</strong>
                <div style="font-size: 0.9rem; color: #78350f; font-weight: 600; margin-top: 4px;">
                    <?php echo h($card['rejection_reason']); ?>
                </div>
                <div style="font-size: 0.8rem; color: #92400e; margin-top: 4px;">
                    Please rectify the flagged discrepancy below, update the details or biometric photo as instructed, and save changes to resubmit for approval.
                </div>
            </div>
        </div>
    </div>
<?php endif; ?>

<?php if (!empty($errors)): ?>
    <div class="alert alert-danger" style="margin-bottom: 1.5rem; border-left: 4px solid #ef4444; background: #fef2f2; color: #991b1b; padding: 14px 18px; border-radius: 6px;">
        <div style="display: flex; align-items: flex-start; gap: 10px;">
            <i class="fas fa-exclamation-triangle" style="font-size: 1.1rem; margin-top: 2px;"></i>
            <div>
                <strong>Please rectify the following validation errors before saving:</strong>
                <ul style="margin: 8px 0 0; padding-left: 20px;">
                    <?php foreach ($errors as $err): ?>
                        <li><?php echo h($err); ?></li>
                    <?php endforeach; ?>
                </ul>
            </div>
        </div>
    </div>
<?php endif; ?>

<form method="POST" action="edit-card?id=<?php echo $cardId; ?>" enctype="multipart/form-data" id="editCardForm">
    <?php echo csrf_field(); ?>
    <input type="hidden" name="card_id" value="<?php echo $cardId; ?>">
    <input type="hidden" id="photoBase64" name="photo_base64" value="">

    <!-- Form Break Navigation Bar -->
    <div class="form-break-toolbar">
        <div style="display: flex; align-items: center; gap: 8px;">
            <span style="font-size: 0.82rem; font-weight: 700; color: var(--nis-slate); text-transform: uppercase; letter-spacing: 0.5px;">
                <i class="fas fa-columns text-success"></i> Form Section View:
            </span>
        </div>
        <div class="form-break-tabs">
            <button type="button" class="tab-break-btn active" id="btnTabAll" onclick="setEditSection('all')">
                <i class="fas fa-th-large"></i> Two-Column View
            </button>
            <button type="button" class="tab-break-btn" id="btnTabPart1" onclick="setEditSection('part1')">
                <i class="fas fa-user-check"></i> Part 1: Identity &amp; Biodata
            </button>
            <button type="button" class="tab-break-btn" id="btnTabPart2" onclick="setEditSection('part2')">
                <i class="fas fa-address-book"></i> Part 2: Contact &amp; Validity
            </button>
        </div>
    </div>

    <div class="edit-two-col-layout" id="editTwoColLayout">
        <!-- ============================================================== -->
        <!-- COLUMN 1: Personal Identity, Biometric Photo & Physical Specs -->
        <!-- ============================================================== -->
        <div class="edit-form-col" id="editCol1">
            <!-- Biometric Photograph Card -->
            <div class="app-card" style="box-shadow: var(--shadow-sm); overflow: hidden;">
                <div class="card-header" style="background: #f8fafc;">
                    <h3><i class="fas fa-camera text-success"></i> Biometric Photograph</h3>
                </div>
                <div class="card-body" style="text-align: center; padding: 1.5rem 1.25rem;">
                    <div style="width: 145px; height: 180px; margin: 0 auto 15px; border: 3px solid var(--nis-green); border-radius: 8px; overflow: hidden; background: #f1f5f9; display: flex; align-items: center; justify-content: center; box-shadow: 0 4px 10px rgba(0,0,0,0.1);">
                        <img id="photoPreviewImg" 
                             src="<?php echo (!empty($card['photo_path']) && file_exists(__DIR__ . '/' . $card['photo_path'])) ? h($card['photo_path']) : ''; ?>" 
                             alt="Biometric Photo" 
                             style="width: 100%; height: 100%; object-fit: cover; <?php echo (empty($card['photo_path']) || !file_exists(__DIR__ . '/' . $card['photo_path'])) ? 'display: none;' : ''; ?>">
                        <div id="photoPlaceholder" style="<?php echo (!empty($card['photo_path']) && file_exists(__DIR__ . '/' . $card['photo_path'])) ? 'display: none;' : ''; ?> color: #94a3b8; font-size: 0.8rem;">
                            <i class="fas fa-user" style="font-size: 3.5rem; display: block; margin-bottom: 6px; color: #cbd5e1;"></i>
                            No Photo
                        </div>
                    </div>

                    <div style="font-size: 0.74rem; color: #64748b; font-weight: 600; text-transform: uppercase; letter-spacing: 0.5px; margin-bottom: 12px;">
                        Passport Photo (35mm &times; 45mm)
                    </div>

                    <div style="display: flex; flex-direction: column; gap: 8px; text-align: left;">
                        <label for="photoFileInput" style="font-size: 0.8rem; font-weight: 700; color: #334155; margin-bottom: 2px;">Replace / Update Photo</label>
                        <input type="file" id="photoFileInput" name="photo_file" accept="image/jpeg,image/png,image/webp" class="form-control" style="font-size: 0.82rem; padding: 6px;">
                        <span class="help-text" style="font-size: 0.72rem; color: #64748b;">Accepted formats: JPG, PNG or WebP (max 5MB)</span>
                    </div>
                </div>
            </div>

            <!-- Personal Biodata & Physical Particulars Card -->
            <div class="app-card" style="box-shadow: var(--shadow-sm); overflow: hidden;">
                <div class="card-header" style="background: #f8fafc;">
                    <h3><i class="fas fa-user-check text-success"></i> 1. Personal Biodata &amp; Physical Characteristics</h3>
                </div>
                <div class="card-body">
                    <div class="form-two-col">
                        <div class="form-group">
                            <label for="surname" class="required">Surname</label>
                            <input type="text" id="surname" name="surname" class="form-control" 
                                   value="<?php echo h($_POST['surname'] ?? $card['surname']); ?>" required 
                                   pattern="^[A-Za-z\s'\-]{2,50}$" title="Surname must contain only letters, spaces, hyphens, or apostrophes (no numbers or symbols)">
                        </div>

                        <div class="form-group">
                            <label for="forenames" class="required">Given Name</label>
                            <input type="text" id="forenames" name="forenames" class="form-control" 
                                   value="<?php echo h($_POST['forenames'] ?? $card['forenames']); ?>" required 
                                   pattern="^[A-Za-z\s'\-]{2,50}$" title="Given name must contain only letters, spaces, hyphens, or apostrophes (no numbers or symbols)">
                        </div>

                        <div class="form-group">
                            <label for="nationality" class="required">Nationality</label>
                            <?php $currentNat = $_POST['nationality'] ?? $card['nationality']; ?>
                            <select id="nationality" name="nationality" class="form-select" required>
                                <option value="" disabled>Select nationality</option>
                                <?php 
                                $natFound = false;
                                foreach ($GLOBALS['NATIONALITIES'] as $code => $name): 
                                    $isSelected = ($currentNat === $code);
                                    if ($isSelected) $natFound = true;
                                ?>
                                    <option value="<?php echo h($code); ?>" <?php echo $isSelected ? 'selected' : ''; ?>>
                                        <?php echo h($name); ?>
                                    </option>
                                <?php endforeach; ?>
                                <option value="OTHER" <?php echo (!$natFound && !empty($currentNat)) ? 'selected' : ''; ?>>Other Sovereign State</option>
                            </select>

                            <div id="otherNationalityContainer" style="display: <?php echo (!$natFound && !empty($currentNat)) ? 'block' : 'none'; ?>; margin-top: 8px;">
                                <input type="text" id="other_nationality" name="other_nationality" class="form-control" 
                                       value="<?php echo (!$natFound) ? h($currentNat) : ''; ?>" placeholder="Specify nationality">
                            </div>
                        </div>

                        <div class="form-group">
                            <label for="date_of_birth" class="required">Date of Birth</label>
                            <input type="date" id="date_of_birth" name="date_of_birth" class="form-control" 
                                   value="<?php echo h($_POST['date_of_birth'] ?? $card['date_of_birth']); ?>" required max="<?php echo date('Y-m-d'); ?>">
                        </div>

                        <div class="form-group">
                            <label for="place_of_birth" class="required">Place of Birth</label>
                            <input type="text" id="place_of_birth" name="place_of_birth" class="form-control" 
                                   value="<?php echo h($_POST['place_of_birth'] ?? $card['place_of_birth']); ?>" required 
                                   pattern="^[A-Za-z\s'\-,\.]{2,60}$" title="Place of Birth must contain valid location text">
                        </div>

                        <div class="form-group">
                            <label for="sex" class="required">Gender</label>
                            <?php $currentSex = $_POST['sex'] ?? $card['sex']; ?>
                            <select id="sex" name="sex" class="form-select" required>
                                <option value="" disabled <?php echo empty($currentSex) ? 'selected' : ''; ?>>SELECT GENDER</option>
                                <option value="MALE" <?php echo ($currentSex === 'MALE') ? 'selected' : ''; ?>>Male</option>
                                <option value="FEMALE" <?php echo ($currentSex === 'FEMALE') ? 'selected' : ''; ?>>Female</option>
                            </select>
                        </div>

                        <div class="form-group">
                            <label for="profession" class="required">Profession</label>
                            <input type="text" id="profession" name="profession" class="form-control" 
                                   value="<?php echo h($_POST['profession'] ?? $card['profession']); ?>" required 
                                   pattern="^[A-Za-z\s'\-\/\.]{2,60}$" title="Profession must contain only letters and valid punctuation">
                        </div>

                        <div class="form-group">
                            <label for="passport_number" class="required">Passport Number</label>
                            <input type="text" id="passport_number" name="passport_number" class="form-control" 
                                   value="<?php echo h($_POST['passport_number'] ?? $card['passport_number']); ?>" required 
                                   pattern="^[A-Za-z0-9]{6,15}$" title="Passport Number must be 6 to 15 alphanumeric characters without spaces or symbols"
                                   autocomplete="off" style="text-transform: uppercase;">
                            <div id="passportDuplicateAlert" style="display: none; font-size: 0.75rem; margin-top: 4px;"></div>
                        </div>

                        <!-- SECTION BREAK: Physical Specifications -->
                        <div class="form-section-break">
                            <span class="break-line"></span>
                            <span class="break-pill"><i class="fas fa-fingerprint"></i> Biometric Physical Specifications</span>
                            <span class="break-line"></span>
                        </div>

                        <div class="form-group">
                            <label for="height">Height</label>
                            <input type="text" id="height" name="height" class="form-control" 
                                   value="<?php echo h($_POST['height'] ?? $card['height']); ?>" placeholder="e.g. 1.78 M">
                        </div>

                        <div class="form-group">
                            <label for="complexion">Complexion</label>
                            <?php $currComp = $_POST['complexion'] ?? $card['complexion']; ?>
                            <select id="complexion" name="complexion" class="form-select">
                                <?php foreach ($GLOBALS['COMPLEXIONS'] as $key => $label): ?>
                                    <option value="<?php echo h($key); ?>" <?php echo ($currComp === $key) ? 'selected' : ''; ?>>
                                        <?php echo h($label); ?>
                                    </option>
                                <?php endforeach; ?>
                            </select>
                        </div>

                        <div class="form-group">
                            <label for="eye_color">Colour of Eyes</label>
                            <?php $currEyes = $_POST['eye_color'] ?? $card['eye_color']; ?>
                            <select id="eye_color" name="eye_color" class="form-select">
                                <?php foreach ($GLOBALS['EYE_COLORS'] as $key => $label): ?>
                                    <option value="<?php echo h($key); ?>" <?php echo ($currEyes === $key) ? 'selected' : ''; ?>>
                                        <?php echo h($label); ?>
                                    </option>
                                <?php endforeach; ?>
                            </select>
                        </div>

                        <div class="form-group">
                            <label for="hair_color">Colour of Hair</label>
                            <?php $currHair = $_POST['hair_color'] ?? $card['hair_color']; ?>
                            <select id="hair_color" name="hair_color" class="form-select">
                                <?php foreach ($GLOBALS['HAIR_COLORS'] as $key => $label): ?>
                                    <option value="<?php echo h($key); ?>" <?php echo ($currHair === $key) ? 'selected' : ''; ?>>
                                        <?php echo h($label); ?>
                                    </option>
                                <?php endforeach; ?>
                            </select>
                        </div>

                        <div class="form-group">
                            <label for="blood_group">Blood Group</label>
                            <?php $currBg = $_POST['blood_group'] ?? $card['blood_group']; ?>
                            <select id="blood_group" name="blood_group" class="form-select">
                                <?php foreach ($GLOBALS['BLOOD_GROUPS'] as $bgCode => $bgLabel): ?>
                                    <option value="<?php echo h($bgCode); ?>" <?php echo ($currBg === $bgCode) ? 'selected' : ''; ?>>
                                        <?php echo h($bgLabel); ?>
                                    </option>
                                <?php endforeach; ?>
                            </select>
                        </div>

                        <div class="form-group">
                            <label for="distinguished_features">Distinguished Features</label>
                            <input type="text" id="distinguished_features" name="distinguished_features" class="form-control" 
                                   value="<?php echo h($_POST['distinguished_features'] ?? $card['distinguished_features']); ?>" placeholder="e.g. None">
                        </div>
                    </div>

                    <!-- Step Break Continue Button (Part 1 Mode) -->
                    <div id="part1NextBtnBox" style="display: none; margin-top: 15px; padding-top: 15px; border-top: 1px solid #e2e8f0;">
                        <button type="button" class="btn btn-primary" style="width: 100%; justify-content: center;" onclick="goToPart2()">
                            Continue to Part 2: Contact, Address &amp; Validity <i class="fas fa-arrow-right"></i>
                        </button>
                    </div>
                </div>
            </div>
        </div>

        <!-- ============================================================== -->
        <!-- COLUMN 2: Registry Status, Address, Contact & Assigned Center -->
        <!-- ============================================================== -->
        <div class="edit-form-col" id="editCol2">
            <!-- Part 2 Back Button Box -->
            <div id="part2BackBtnBox" style="display: none;">
                <button type="button" class="btn btn-outline" style="width: 100%; justify-content: center; margin-bottom: 5px;" onclick="setEditSection('part1')">
                    <i class="fas fa-arrow-left"></i> Back to Part 1: Identity &amp; Biodata
                </button>
            </div>

            <!-- Registry Record Status Card -->
            <div class="app-card" style="box-shadow: var(--shadow-sm); overflow: hidden;">
                <div class="card-header" style="background: #f8fafc;">
                    <h3><i class="fas fa-id-badge text-success"></i> Registry Record Status</h3>
                </div>
                <div class="card-body" style="padding: 1.25rem;">
                    <div class="form-two-col">
                        <div class="form-group">
                            <label for="card_number">Residence Card Number</label>
                            <input type="text" id="card_number" name="card_number" class="form-control" 
                                   value="<?php echo h($card['card_number']); ?>" 
                                   <?php echo ($user['role'] !== ROLE_SUPER_ADMIN) ? 'readonly' : ''; ?>
                                   style="font-family: monospace; font-weight: 700; font-size: 0.95rem; background: <?php echo ($user['role'] !== ROLE_SUPER_ADMIN) ? '#f8fafc' : '#ffffff'; ?>;">
                            <?php if ($user['role'] !== ROLE_SUPER_ADMIN): ?>
                                <span class="help-text" style="font-size: 0.72rem; color: #64748b; margin-top: 4px; display: block;"><i class="fas fa-lock" style="font-size: 0.7rem;"></i> Assigned card identifier locked.</span>
                            <?php endif; ?>
                        </div>

                        <div class="form-group">
                            <label for="status" class="required">Card Lifecycle Status</label>
                            <select id="status" name="status" class="form-select" required style="font-weight: 600;">
                                <?php 
                                $statuses = ['PENDING_APPROVAL', 'ISSUED', 'RENEWED', 'QUERIED', 'EXPIRED', 'REVOKED'];
                                foreach ($statuses as $st): 
                                ?>
                                    <option value="<?php echo $st; ?>" <?php echo ($card['status'] === $st) ? 'selected' : ''; ?>>
                                        <?php echo $st; ?>
                                    </option>
                                <?php endforeach; ?>
                            </select>
                        </div>
                    </div>

                    <div style="background: #f8fafc; border: 1px solid #e2e8f0; border-radius: 6px; padding: 12px; font-size: 0.82rem; margin-top: 14px; display: flex; flex-direction: column; gap: 7px;">
                        <div style="display: flex; justify-content: space-between; align-items: center;">
                            <span style="color: #64748b; font-weight: 600;">Booklet No:</span>
                            <strong style="color: #1e293b;"><?php echo h($card['booklet_number'] ?: '—'); ?></strong>
                        </div>
                        <div style="display: flex; justify-content: space-between; align-items: center;">
                            <span style="color: #64748b; font-weight: 600;">Enrolled Date:</span>
                            <strong style="color: #1e293b;"><?php echo formatNISDate($card['created_at']); ?></strong>
                        </div>
                        <div style="display: flex; justify-content: space-between; align-items: center;">
                            <span style="color: #64748b; font-weight: 600;">Enrolling Officer:</span>
                            <strong style="color: #1e293b; text-align: right; max-width: 140px; word-break: break-word;"><?php echo h($card['issuing_officer_name']); ?></strong>
                        </div>
                    </div>
                </div>
            </div>

            <!-- Nigerian Residential Address & Emergency Contact Particulars Card -->
            <div class="app-card" style="box-shadow: var(--shadow-sm); overflow: hidden;">
                <div class="card-header" style="background: #f8fafc;">
                    <h3><i class="fas fa-address-book text-success"></i> 2. Nigerian Residential Address &amp; Emergency Contact</h3>
                </div>
                <div class="card-body">
                    <div class="form-two-col">
                        <div class="form-group col-full">
                            <label for="domicile" class="required">Residential Address in Nigeria</label>
                            <textarea id="domicile" name="domicile" class="form-control" rows="2" required minlength="5"><?php echo h($_POST['domicile'] ?? $card['domicile']); ?></textarea>
                        </div>

                        <!-- SECTION BREAK: Emergency Contact -->
                        <div class="form-section-break">
                            <span class="break-line"></span>
                            <span class="break-pill"><i class="fas fa-phone-alt"></i> Emergency Contact &amp; Next of Kin</span>
                            <span class="break-line"></span>
                        </div>

                        <div class="form-group">
                            <label for="emergency_contact_name" class="required">Emergency Contact Name</label>
                            <input type="text" id="emergency_contact_name" name="emergency_contact_name" class="form-control" 
                                    value="<?php echo h($_POST['emergency_contact_name'] ?? $card['emergency_contact_name']); ?>" required 
                                    pattern="^[A-Za-z\s'\-]{2,70}$" title="Emergency contact name must contain only letters, spaces, hyphens, or apostrophes (no numbers or symbols)">
                        </div>

                        <div class="form-group">
                            <label for="emergency_contact_relation" class="required">Relationship to Holder</label>
                            <?php $currRel = $_POST['emergency_contact_relation'] ?? $card['emergency_contact_relation']; ?>
                            <select id="emergency_contact_relation" name="emergency_contact_relation" class="form-select" required>
                                <?php foreach ($GLOBALS['RELATIONSHIPS'] as $relKey => $relName): ?>
                                    <option value="<?php echo h($relKey); ?>" <?php echo ($currRel === $relKey) ? 'selected' : ''; ?>>
                                        <?php echo h($relName); ?>
                                    </option>
                                <?php endforeach; ?>
                            </select>
                        </div>

                        <div class="form-group">
                            <label for="emergency_contact_phone" class="required">Contact Telephone Number</label>
                            <input type="text" id="emergency_contact_phone" name="emergency_contact_phone" class="form-control" 
                                    value="<?php echo h($_POST['emergency_contact_phone'] ?? $card['emergency_contact_phone']); ?>" required 
                                    pattern="^\+?[0-9\s\-()]{7,25}$" title="Valid telephone number with minimum 7 digits">
                            <span class="help-text" style="font-size: 0.72rem; color: #64748b;">E.164 format with country code (e.g. +234 801 234 5678)</span>
                        </div>

                        <div class="form-group">
                            <label for="change_of_address">Change of Address</label>
                            <input type="text" id="change_of_address" name="change_of_address" class="form-control" 
                                    value="<?php echo h($_POST['change_of_address'] ?? $card['change_of_address']); ?>" placeholder="e.g. NO CHANGE RECORDED">
                        </div>

                        <div class="form-group col-full">
                            <label for="emergency_contact_address" class="required">Emergency Contact Address</label>
                            <input type="text" id="emergency_contact_address" name="emergency_contact_address" class="form-control" 
                                    value="<?php echo h($_POST['emergency_contact_address'] ?? $card['emergency_contact_address']); ?>" required>
                        </div>
                    </div>
                </div>
            </div>

            <!-- Validity & Command Station Card -->
            <div class="app-card" style="box-shadow: var(--shadow-sm); overflow: hidden;">
                <div class="card-header" style="background: #f8fafc;">
                    <h3><i class="fas fa-stamp text-success"></i> 3. Validity &amp; Command Station</h3>
                </div>
                <div class="card-body">
                    <div class="form-two-col">
                        <div class="form-group">
                            <label for="issued_on" class="required">Issued On</label>
                            <input type="date" id="issued_on" name="issued_on" class="form-control" 
                                    value="<?php echo h($_POST['issued_on'] ?? $card['issued_on']); ?>" required>
                        </div>

                        <div class="form-group">
                            <label for="expires_on" class="required">Expires On</label>
                            <input type="date" id="expires_on" name="expires_on" class="form-control" 
                                    value="<?php echo h($_POST['expires_on'] ?? $card['expires_on']); ?>" required>
                        </div>

                        <div class="form-group col-full">
                            <label for="issued_at" class="required">Issued At (Command Station)</label>
                            <input type="text" id="issued_at" name="issued_at" class="form-control" 
                                    value="<?php echo h($_POST['issued_at'] ?? $card['issued_at']); ?>" required>
                        </div>
                    </div>
                </div>
                <div class="card-footer" style="padding: 16px 20px; background: #f8fafc; border-top: 1px solid var(--nis-border); display: flex; justify-content: flex-end; align-items: center; gap: 12px;">
                    <a href="card-details?id=<?php echo $cardId; ?>" class="btn btn-outline">
                        <i class="fas fa-times"></i> Cancel &amp; Back to File
                    </a>
                    <button type="submit" name="update_card" value="1" class="btn btn-primary" style="padding: 10px 28px; font-weight: 700; font-size: 0.95rem;">
                        <i class="fas fa-save"></i> Save Changes
                    </button>
                </div>
            </div>
        </div>
    </div>
</form>

<script>
document.addEventListener('DOMContentLoaded', function() {
    const cardId = <?php echo (int)$cardId; ?>;
    const photoFileInput = document.getElementById('photoFileInput');
    const photoPreviewImg = document.getElementById('photoPreviewImg');
    const photoPlaceholder = document.getElementById('photoPlaceholder');

    // Photo preview on file change
    if (photoFileInput) {
        photoFileInput.addEventListener('change', function() {
            if (this.files && this.files[0]) {
                const file = this.files[0];
                if (file.size > 5 * 1024 * 1024) {
                    alert('Uploaded image exceeds 5MB limit.');
                    this.value = '';
                    return;
                }
                const reader = new FileReader();
                reader.onload = function(e) {
                    photoPreviewImg.src = e.target.result;
                    photoPreviewImg.style.display = 'block';
                    if (photoPlaceholder) photoPlaceholder.style.display = 'none';
                };
                reader.readAsDataURL(file);
            }
        });
    }

    // Dynamic Other Nationality toggle
    const natSelect = document.getElementById('nationality');
    const otherNatBox = document.getElementById('otherNationalityContainer');
    const otherNatInput = document.getElementById('other_nationality');
    if (natSelect && otherNatBox) {
        natSelect.addEventListener('change', function() {
            if (this.value === 'OTHER') {
                otherNatBox.style.display = 'block';
                if (otherNatInput) otherNatInput.required = true;
            } else {
                otherNatBox.style.display = 'none';
                if (otherNatInput) otherNatInput.required = false;
            }
        });
    }

    // Real-time restriction on Name Fields (reject numbers and symbols)
    const nameFields = ['surname', 'forenames', 'emergency_contact_name'];
    nameFields.forEach(id => {
        const input = document.getElementById(id);
        if (!input) return;
        input.addEventListener('input', function() {
            const raw = this.value;
            const cleaned = raw.replace(/[^A-Za-z\s'\-]/g, '');
            if (raw !== cleaned) {
                this.value = cleaned;
            }
        });
    });

    // Real-time Passport Number Formatting & Duplicate Check (excluding current card)
    const passportInput = document.getElementById('passport_number');
    const dupAlert = document.getElementById('passportDuplicateAlert');
    if (passportInput) {
        let passTimer = null;
        passportInput.addEventListener('input', function() {
            const cleaned = this.value.toUpperCase().replace(/[^A-Z0-9]/g, '');
            if (this.value !== cleaned) {
                this.value = cleaned;
            }
            clearTimeout(passTimer);
            const val = this.value.trim();
            if (val.length >= 6) {
                passTimer = setTimeout(() => {
                    fetch('api/check-passport?passport=' + encodeURIComponent(val) + '&exclude_id=' + cardId)
                        .then(r => r.json())
                        .then(data => {
                            if (!data.valid && data.is_duplicate) {
                                passportInput.dataset.duplicate = 'true';
                                if (dupAlert) {
                                    dupAlert.style.display = 'block';
                                    dupAlert.style.color = '#dc2626';
                                    dupAlert.innerHTML = '<i class="fas fa-exclamation-triangle"></i> ' + data.message;
                                }
                            } else {
                                delete passportInput.dataset.duplicate;
                                if (dupAlert) {
                                    dupAlert.style.display = 'block';
                                    dupAlert.style.color = '#16a34a';
                                    dupAlert.innerHTML = '<i class="fas fa-check-circle"></i> Passport number is valid.';
                                }
                            }
                        })
                        .catch(() => {});
                }, 350);
            } else {
                delete passportInput.dataset.duplicate;
                if (dupAlert) dupAlert.style.display = 'none';
            }
        });
    }

    // Client-side submit guard & multi-step validity check
    const form = document.getElementById('editCardForm');
    if (form) {
        form.addEventListener('submit', function(e) {
            // If submitted while a section is hidden and invalid, switch to 'all' so browser can focus & report
            if (!this.checkValidity()) {
                window.setEditSection('all');
            }
            if (passportInput && passportInput.dataset.duplicate === 'true') {
                e.preventDefault();
                alert('Cannot save: Passport number is already registered to another residence card.');
                passportInput.focus();
                return false;
            }
        });
    }

    // Section view switcher & form breaks
    window.setEditSection = function(mode) {
        const layout = document.getElementById('editTwoColLayout');
        const col1 = document.getElementById('editCol1');
        const col2 = document.getElementById('editCol2');
        const tabAll = document.getElementById('btnTabAll');
        const tabPart1 = document.getElementById('btnTabPart1');
        const tabPart2 = document.getElementById('btnTabPart2');
        const nextBtnBox = document.getElementById('part1NextBtnBox');
        const backBtnBox = document.getElementById('part2BackBtnBox');

        [tabAll, tabPart1, tabPart2].forEach(btn => { if (btn) btn.classList.remove('active'); });

        if (mode === 'part1') {
            if (tabPart1) tabPart1.classList.add('active');
            if (layout) layout.style.display = 'block';
            if (col1) col1.style.display = 'flex';
            if (col2) col2.style.display = 'none';
            if (nextBtnBox) nextBtnBox.style.display = 'block';
            if (backBtnBox) backBtnBox.style.display = 'none';
        } else if (mode === 'part2') {
            if (tabPart2) tabPart2.classList.add('active');
            if (layout) layout.style.display = 'block';
            if (col1) col1.style.display = 'none';
            if (col2) col2.style.display = 'flex';
            if (nextBtnBox) nextBtnBox.style.display = 'none';
            if (backBtnBox) backBtnBox.style.display = 'block';
        } else {
            // 'all' - Two-Column View
            if (tabAll) tabAll.classList.add('active');
            if (layout) layout.style.display = '';
            if (col1) col1.style.display = '';
            if (col2) col2.style.display = '';
            if (nextBtnBox) nextBtnBox.style.display = 'none';
            if (backBtnBox) backBtnBox.style.display = 'none';
        }

        const toolbar = document.querySelector('.form-break-toolbar');
        if (toolbar && window.scrollY > toolbar.offsetTop) {
            toolbar.scrollIntoView({ behavior: 'smooth', block: 'start' });
        }
    };

    window.goToPart2 = function() {
        const col1 = document.getElementById('editCol1');
        if (col1) {
            const inputs = col1.querySelectorAll('input, select, textarea');
            for (let input of inputs) {
                if (!input.checkValidity()) {
                    input.reportValidity();
                    return;
                }
            }
        }
        window.setEditSection('part2');
    };
});
</script>

<?php
require_once __DIR__ . '/includes/footer.php';
?>
