<?php
/**
 * NIS Residence Card Issuance System (NIS-RCIS)
 * Super Administrator Application & Registration Dossier (Aligned with apply.php)
 */

require_once __DIR__ . '/includes/auth.php';
require_once __DIR__ . '/config/database.php';
require_once __DIR__ . '/config/constants.php';

// Only Super Admin has access
requireRole(ROLE_SUPER_ADMIN);

$pageTitle = 'Issue New Residence Card';
$db = Database::getConnection();
$user = currentUser();
$errors = [];
$successMessage = '';

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    if (!validate_csrf()) {
        $errors[] = 'Security token validation failed. Please submit the form again.';
    } else {
        // -------------------------------------------------------------
        // 1. SECTION 1: Passport Details & Photograph
        // -------------------------------------------------------------
        $passportNumber = strtoupper(trim($_POST['passport_number'] ?? ''));
        $passportIssueDate = trim($_POST['passport_issue_date'] ?? '');
        $passportExpiry = trim($_POST['passport_expiry'] ?? '');

        if (empty($passportNumber)) {
            $errors[] = 'Passport Number is mandatory.';
        } elseif (!preg_match('/^[A-Z0-9]+$/', $passportNumber)) {
            $errors[] = 'Passport Number should accept only characters and numbers only.';
        } elseif (strlen($passportNumber) < 6 || strlen($passportNumber) > 12) {
            $errors[] = 'Passport Number must be between 6 and 12 characters and numbers.';
        }

        if (empty($passportIssueDate)) {
            $errors[] = 'Passport Issue Date is mandatory.';
        }

        if (empty($passportExpiry)) {
            $errors[] = 'Passport Expiry Date is mandatory.';
        } else {
            $expiryTimestamp = strtotime($passportExpiry);
            $sixMonthsFuture = strtotime('+6 months');
            if ($expiryTimestamp === false || $expiryTimestamp <= time()) {
                $errors[] = 'Passport has expired. A valid passport is required.';
            } elseif ($expiryTimestamp < $sixMonthsFuture) {
                $errors[] = 'Passport must have at least 6 months validity remaining.';
            }
        }

        // Photo Upload Handling
        $photoPath = null;
        $uploadDir = __DIR__ . '/uploads/photos/';
        if (!is_dir($uploadDir)) {
            @mkdir($uploadDir, 0755, true);
        }

        if (isset($_FILES['photo_file']) && $_FILES['photo_file']['error'] === UPLOAD_ERR_OK) {
            $fileTmp = $_FILES['photo_file']['tmp_name'];
            $fileSize = $_FILES['photo_file']['size'];
            $fileInfo = @getimagesize($fileTmp);

            if ($fileInfo === false) {
                $errors[] = 'Uploaded photo is not a valid image file.';
            } else {
                $mime = $fileInfo['mime'];
                $allowedMimes = ['image/jpeg', 'image/png', 'image/jpg'];
                if (!in_array($mime, $allowedMimes, true)) {
                    $errors[] = 'Photo must be in JPEG or PNG format.';
                } elseif ($fileSize > 2 * 1024 * 1024) {
                    $errors[] = 'Photo file size exceeds the 2MB limit.';
                } else {
                    $ext = ($mime === 'image/png') ? 'png' : 'jpg';
                    $newFilename = 'photo_' . date('Ymd_His') . '_' . bin2hex(random_bytes(4)) . '.' . $ext;
                    if (move_uploaded_file($fileTmp, $uploadDir . $newFilename)) {
                        $photoPath = 'uploads/photos/' . $newFilename;
                    } else {
                        $errors[] = 'Failed to save uploaded photograph.';
                    }
                }
            }
        } elseif (!empty($_POST['existing_photo_path'])) {
            $photoPath = trim($_POST['existing_photo_path']);
        } else {
            $errors[] = 'Passport photograph is required.';
        }

        // -------------------------------------------------------------
        // 2. SECTION 2: Personal Particulars & Contact Details
        // -------------------------------------------------------------
        $surname = strtoupper(trim($_POST['surname'] ?? ''));
        $forenames = strtoupper(trim($_POST['forenames'] ?? ''));
        $nationality = strtoupper(trim($_POST['nationality'] ?? ''));
        $sex = strtoupper(trim($_POST['sex'] ?? ''));
        $dob = trim($_POST['date_of_birth'] ?? '');
        $pob = strtoupper(trim($_POST['place_of_birth'] ?? ''));
        $profession = strtoupper(trim($_POST['profession'] ?? ''));
        $height = trim($_POST['height'] ?? '');
        $complexion = strtoupper(trim($_POST['complexion'] ?? ''));
        $eyeColor = strtoupper(trim($_POST['eye_color'] ?? ''));
        $hairColor = strtoupper(trim($_POST['hair_color'] ?? ''));
        $distinguishedFeatures = trim($_POST['distinguished_features'] ?? '');
        $taxIdNumber = trim($_POST['tax_id_number'] ?? '');
        $phone = trim($_POST['phone'] ?? '');
        $email = trim($_POST['email'] ?? '');

        // Assemble residential address from broken-down fields if provided
        $resStreet = trim($_POST['residential_address'] ?? '');
        $resCity = trim($_POST['residential_city'] ?? '');
        $resLga = trim($_POST['residential_lga'] ?? '');
        $resState = trim($_POST['residential_state'] ?? '');
        if ($resStreet || $resState || $resLga || $resCity) {
            $domicileParts = array_filter([
                $resStreet,
                $resCity,
                $resLga ? ($resLga . ' LGA') : '',
                $resState ? ($resState . ' STATE') : ''
            ]);
            $domicile = strtoupper(trim(implode(', ', $domicileParts)));
        } else {
            $domicile = strtoupper(trim($_POST['domicile'] ?? ''));
        }

        $changeOfAddress = strtoupper(trim($_POST['change_of_address'] ?? ''));
        $bloodGroup = strtoupper(trim($_POST['blood_group'] ?? 'UNKNOWN'));
        if (empty($bloodGroup)) $bloodGroup = 'UNKNOWN';

        if (empty($surname) || !preg_match("/^[\p{L}\s'\-]{2,50}$/u", $surname)) {
            $errors[] = 'Surname is mandatory and must contain only letters, spaces, or hyphens.';
        }
        if (empty($forenames) || !preg_match("/^[\p{L}\s'\-]{2,50}$/u", $forenames)) {
            $errors[] = 'Given Names are mandatory and must contain only letters.';
        }
        if (empty($nationality)) {
            $errors[] = 'Nationality is mandatory. Please select applicant country of origin.';
        }
        if (empty($dob)) {
            $errors[] = 'Date of birth is mandatory.';
        }
        if (empty($pob)) {
            $errors[] = 'Place of birth is mandatory.';
        }
        if (empty($sex)) {
            $errors[] = 'Gender selection is mandatory.';
        }
        if (empty($phone) || empty($email)) {
            $errors[] = 'Applicant phone number and email address are mandatory.';
        }
        if (empty($domicile)) {
            $errors[] = 'Residential Address in Nigeria is mandatory.';
        }

        // -------------------------------------------------------------
        // 3. SECTION 3: Next of Kin Information
        // -------------------------------------------------------------
        $emergencyName = strtoupper(trim($_POST['emergency_contact_name'] ?? ''));
        $emergencyRelation = strtoupper(trim($_POST['emergency_contact_relation'] ?? ''));
        $emergencyPhone = trim($_POST['emergency_contact_phone'] ?? '');

        // Assemble Next of Kin address from broken-down fields if provided
        $kinStreet = trim($_POST['kin_address'] ?? '');
        $kinCity = trim($_POST['kin_city'] ?? '');
        $kinLga = trim($_POST['kin_lga'] ?? '');
        $kinState = trim($_POST['kin_state'] ?? '');
        if ($kinStreet || $kinState || $kinLga || $kinCity) {
            $kinParts = array_filter([
                $kinStreet,
                $kinCity,
                $kinLga ? ($kinLga . ' LGA') : '',
                $kinState ? ($kinState . ' STATE') : ''
            ]);
            $emergencyAddress = strtoupper(trim(implode(', ', $kinParts)));
        } else {
            $emergencyAddress = strtoupper(trim($_POST['emergency_contact_address'] ?? ''));
        }

        if (empty($emergencyName)) {
            $errors[] = 'Next of Kin Full Name is mandatory.';
        }
        if (empty($emergencyRelation)) {
            $errors[] = 'Next of Kin Relationship is mandatory.';
        }
        if (empty($emergencyPhone)) {
            $errors[] = 'Next of Kin Phone Number is mandatory.';
        }
        if (empty($emergencyAddress)) {
            $errors[] = 'Next of Kin Residential Address is mandatory.';
        }

        // -------------------------------------------------------------
        // 4. SECTION 4: Supporting Document Upload
        // -------------------------------------------------------------
        $docUploadDir = __DIR__ . '/uploads/documents/';
        if (!is_dir($docUploadDir)) {
            @mkdir($docUploadDir, 0755, true);
        }

        $allowedDocExts = ['pdf', 'jpg', 'jpeg', 'png'];
        $allowedDocMimes = ['application/pdf', 'image/jpeg', 'image/png', 'image/pjpeg'];
        $maxDocSize = 5 * 1024 * 1024;

        $handleDocUpload = function($inputName, $filePrefix, $isMandatory, $docTitle) use (&$errors, $docUploadDir, $allowedDocExts, $allowedDocMimes, $maxDocSize) {
            $savedPath = null;
            if (isset($_FILES[$inputName]) && $_FILES[$inputName]['error'] === UPLOAD_ERR_OK) {
                $tmpName = $_FILES[$inputName]['tmp_name'];
                $origName = $_FILES[$inputName]['name'];
                $fileSize = $_FILES[$inputName]['size'];
                $ext = strtolower(pathinfo($origName, PATHINFO_EXTENSION));

                if (!in_array($ext, $allowedDocExts, true)) {
                    $errors[] = "{$docTitle}: Unsupported file format. Please upload PDF, JPG, or PNG.";
                } elseif ($fileSize > $maxDocSize) {
                    $errors[] = "{$docTitle}: File size exceeds 5MB limit.";
                } else {
                    $finfo = finfo_open(FILEINFO_MIME_TYPE);
                    $mime = finfo_file($finfo, $tmpName);
                    finfo_close($finfo);

                    if (!in_array($mime, $allowedDocMimes, true)) {
                        $errors[] = "{$docTitle}: Uploaded file failed security check ({$mime}).";
                    } else {
                        $newFilename = $filePrefix . '_' . date('Ymd_His') . '_' . bin2hex(random_bytes(6)) . '.' . $ext;
                        if (move_uploaded_file($tmpName, $docUploadDir . $newFilename)) {
                            $savedPath = 'uploads/documents/' . $newFilename;
                        } else {
                            $errors[] = "{$docTitle}: Failed to save file on server.";
                        }
                    }
                }
            } elseif (!empty($_POST['existing_' . $inputName])) {
                $savedPath = trim($_POST['existing_' . $inputName]);
            } elseif ($isMandatory) {
                $errors[] = "{$docTitle} is mandatory.";
            }
            return $savedPath;
        };

        $docPassportCopy   = $handleDocUpload('doc_passport_copy', 'doc_pass', true, 'Passport Bio-data Page');
        $docResidenceVisa  = $handleDocUpload('doc_residence_visa', 'doc_visa', true, 'STR Visa / Entry Visa');
        $docQuotaApproval  = $handleDocUpload('doc_quota_approval', 'doc_quota', true, 'Quota Approval / Employment Letter');
        $docDomicileProof  = $handleDocUpload('doc_domicile_proof', 'doc_domicile', false, 'Proof of Residence');
        $docAdditional     = $handleDocUpload('doc_additional', 'doc_add', false, 'Additional Document');

        // -------------------------------------------------------------
        // 6. SECTION 6: Fee & Payment (Paystack / Admin)
        // -------------------------------------------------------------
        $feeAmount = defined('RESIDENCE_CARD_FEE_NAIRA') ? (float)RESIDENCE_CARD_FEE_NAIRA : 35000.00;
        $paymentStatus = 'PAID';
        $paymentMethod = strtoupper(trim($_POST['payment_method'] ?? 'PAYSTACK'));
        $paymentRef = trim($_POST['payment_reference'] ?? '');
        if (empty($paymentRef)) {
            $paymentRef = 'ADMIN-' . date('Ymd') . '-' . rand(10000, 99999);
        }
        $paymentDate = date('Y-m-d H:i:s');

        // -------------------------------------------------------------
        // 7. SECTION 7: Biometrics Appointment Booking
        // -------------------------------------------------------------
        $enrollmentCenter = trim($_POST['enrollment_center'] ?? 'NIS HQ');
        if (empty($enrollmentCenter)) {
            $enrollmentCenter = 'NIS HQ';
        }
        $appointmentDate = trim($_POST['appointment_date'] ?? date('Y-m-d', strtotime('+1 day')));
        $appointmentTime = trim($_POST['appointment_time'] ?? '09:00 AM');

        // Duplicate Passport check
        if (empty($errors)) {
            $chk = $db->prepare("SELECT id, application_number FROM applications WHERE UPPER(passport_number) = :pn AND status NOT IN ('REJECTED') LIMIT 1");
            $chk->execute([':pn' => $passportNumber]);
            $dup = $chk->fetch();
            if ($dup) {
                $errors[] = "An active application (No. {$dup['application_number']}) already exists for Passport Number '{$passportNumber}'.";
            }
        }

        // Process Record Insertion into applications
        if (empty($errors)) {
            $appNumber = 'RC-' . date('Y') . '-' . str_pad((string)rand(100000, 999999), 6, '0', STR_PAD_LEFT);
            $referenceNumber = 'APP-' . strtoupper(substr(md5(uniqid('', true)), 0, 10));

            $insertStmt = $db->prepare("INSERT INTO applications (
                application_number, reference_number, surname, forenames, nationality,
                date_of_birth, place_of_birth, sex, height, complexion, eye_color, hair_color, distinguished_features,
                profession, domicile, change_of_address, passport_number, passport_issue_date, passport_expiry, national_id_number, tax_id_number,
                phone, email, emergency_contact_name, emergency_contact_relation, emergency_contact_phone,
                emergency_contact_address, blood_group, enrollment_center, appointment_date, appointment_time,
                fee_amount, payment_status, payment_method, payment_date, payment_reference, photo_path,
                doc_passport_copy, doc_residence_visa, doc_quota_approval, doc_domicile_proof, doc_additional, status
            ) VALUES (
                :app_num, :ref_num, :surname, :forenames, :nationality,
                :dob, :pob, :sex, :height, :complexion, :eye_color, :hair_color, :dist_feat,
                :profession, :domicile, :change_of_address, :passport_number, :passport_issue, :passport_expiry, null, :tax_id,
                :phone, :email, :em_name, :em_rel, :em_phone,
                :em_addr, :blood, :center, :app_date, :app_time,
                :fee, :pay_status, :pay_method, :pay_date, :pay_ref, :photo,
                :doc_pass, :doc_visa, :doc_quota, :doc_domicile, :doc_add, 'PENDING_APPROVAL'
            )");

            $insertStmt->execute([
                ':app_num' => $appNumber,
                ':ref_num' => $referenceNumber,
                ':surname' => $surname,
                ':forenames' => $forenames,
                ':nationality' => $nationality,
                ':dob' => $dob,
                ':pob' => $pob,
                ':sex' => $sex,
                ':height' => $height ?: null,
                ':complexion' => $complexion ?: null,
                ':eye_color' => $eyeColor ?: null,
                ':hair_color' => $hairColor ?: null,
                ':dist_feat' => !empty($distinguishedFeatures) ? $distinguishedFeatures : null,
                ':profession' => $profession,
                ':domicile' => $domicile,
                ':change_of_address' => !empty($changeOfAddress) ? $changeOfAddress : null,
                ':passport_number' => $passportNumber,
                ':passport_issue' => $passportIssueDate ?: null,
                ':passport_expiry' => $passportExpiry ?: null,
                ':tax_id' => $taxIdNumber ?: null,
                ':phone' => $phone,
                ':email' => $email,
                ':em_name' => $emergencyName,
                ':em_rel' => $emergencyRelation,
                ':em_phone' => $emergencyPhone,
                ':em_addr' => $emergencyAddress,
                ':blood' => $bloodGroup,
                ':center' => $enrollmentCenter,
                ':app_date' => $appointmentDate,
                ':app_time' => $appointmentTime,
                ':fee' => $feeAmount,
                ':pay_status' => $paymentStatus,
                ':pay_method' => $paymentMethod,
                ':pay_date' => $paymentDate,
                ':pay_ref' => $paymentRef,
                ':photo' => $photoPath,
                ':doc_pass' => $docPassportCopy,
                ':doc_visa' => $docResidenceVisa,
                ':doc_quota' => $docQuotaApproval,
                ':doc_domicile' => $docDomicileProof,
                ':doc_add' => $docAdditional
            ]);

            $newAppId = (int)$db->lastInsertId();
            logAudit('SUPERADMIN_APPLICATION_CREATED', $newAppId, "Super Administrator {$user['fullname']} created application No. {$appNumber} for {$surname}, {$forenames}");

            setFlash('success', "Residence Card Application No. <strong>{$appNumber}</strong> for <strong>{$surname}, {$forenames}</strong> has been enrolled successfully and placed into the Approval Queue.");
            header("Location: pending-approvals?status=PENDING_APPROVAL");
            exit();
        }
    }
}

require_once __DIR__ . '/includes/header.php';
require_once __DIR__ . '/includes/sidebar.php';
?>

<div class="page-title-box">
    <div>
        <h2>Issue New Residence Card</h2>
        <div class="subtitle">Super Administrator Residence Card Application &amp; Registration Portal</div>
    </div>
    <div>
        <a href="pending-approvals" class="btn btn-outline">
            View Approval Queue
        </a>
    </div>
</div>

<?php
$formAction = 'new-card';
$isSuperAdminEntry = true;
require_once __DIR__ . '/includes/application-form-wizard.php';

require_once __DIR__ . '/includes/footer.php';
?>
