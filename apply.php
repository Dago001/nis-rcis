<?php
/**
 * NIS Residence Card Issuance System (NIS-RCIS)
 * Public Online Residence Card Application Portal (apply.php)
 * Guided Section-Break Application Wizard (Unified with new-card.php)
 */

require_once __DIR__ . '/includes/security.php';
require_once __DIR__ . '/config/database.php';
require_once __DIR__ . '/config/constants.php';

$db = Database::getConnection();
$errors = [];
$successApplication = null;

$isRenewal = (isset($_GET['type']) && $_GET['type'] === 'renewal');
$wizardTitle = $isRenewal ? "Residence Permit Renewal Application" : "Online Residence Card Application";
$wizardSubtitle = $isRenewal ? "Submit your renewal request with updated supporting documents." : "Guided Expatriate Registration, Document Upload & Appointment Booking";

// Require applicant authentication before accessing application form
if (empty($_SESSION['applicant_id'])) {
    $redirectTarget = 'apply' . (!empty($_SERVER['QUERY_STRING']) ? '?' . $_SERVER['QUERY_STRING'] : '');
    header("Location: applicant-login?redirect=" . urlencode($redirectTarget));
    exit();
}

$applicantId = (int)$_SESSION['applicant_id'];

// Handle Discard Draft Request
if (isset($_GET['discard_draft']) && !empty($applicantId)) {
    $db->prepare("UPDATE applicants SET draft_data = NULL, draft_step = 1 WHERE id = :id")->execute([':id' => $applicantId]);
    unset($_SESSION['application_draft']);
    setFlash('info', 'Your saved application draft has been discarded.');
    header("Location: apply" . ($isRenewal ? '?type=renewal' : ''));
    exit();
}

$applicantProfile = null;
$apStmt = $db->prepare("SELECT * FROM applicants WHERE id = :id LIMIT 1");
$apStmt->execute([':id' => $applicantId]);
$applicantProfile = $apStmt->fetch();

$savedDraftActive = false;
$initialStep = 1;

if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    // 1. Restore saved application draft if present
    if (!empty($applicantProfile['draft_data'])) {
        $draftData = json_decode($applicantProfile['draft_data'], true);
        if (is_array($draftData)) {
            foreach ($draftData as $k => $v) {
                $_POST[$k] = $v;
            }
            $savedDraftActive = true;
            $initialStep = (int)($draftData['current_step_saved'] ?? 1);
        }
    }

    // 2. Pre-populate default profile particulars if not already set
    if ($applicantProfile) {
        $_POST['surname'] = $_POST['surname'] ?? $applicantProfile['surname'];
        $_POST['forenames'] = $_POST['forenames'] ?? $applicantProfile['forenames'];
        $_POST['nationality'] = $_POST['nationality'] ?? $applicantProfile['nationality'];
        $_POST['passport_number'] = $_POST['passport_number'] ?? $applicantProfile['passport_number'];
        $_POST['email'] = $_POST['email'] ?? $applicantProfile['email'];
        $_POST['phone'] = $_POST['phone'] ?? $applicantProfile['phone'];
    }
    // Pre-populate if renewal specifies card_number
    if (!empty($_GET['card_number'])) {
        $rcStmt = $db->prepare("SELECT * FROM residence_cards WHERE card_number = :cn LIMIT 1");
        $rcStmt->execute([':cn' => trim($_GET['card_number'])]);
        $priorCard = $rcStmt->fetch();
        if ($priorCard) {
            $_POST['surname'] = $_POST['surname'] ?? $priorCard['surname'];
            $_POST['forenames'] = $_POST['forenames'] ?? $priorCard['forenames'];
            $_POST['nationality'] = $_POST['nationality'] ?? $priorCard['nationality'];
            $_POST['passport_number'] = $_POST['passport_number'] ?? $priorCard['passport_number'];
            $_POST['profession'] = $_POST['profession'] ?? $priorCard['profession'];
            $_POST['domicile'] = $_POST['domicile'] ?? $priorCard['domicile'];
            $_POST['date_of_birth'] = $_POST['date_of_birth'] ?? $priorCard['date_of_birth'];
            $_POST['place_of_birth'] = $_POST['place_of_birth'] ?? $priorCard['place_of_birth'];
            $_POST['sex'] = $_POST['sex'] ?? $priorCard['sex'];
            $_POST['blood_group'] = $_POST['blood_group'] ?? $priorCard['blood_group'];
            $_POST['height'] = $_POST['height'] ?? $priorCard['height'];
            $_POST['complexion'] = $_POST['complexion'] ?? $priorCard['complexion'];
            $_POST['eye_color'] = $_POST['eye_color'] ?? $priorCard['eye_color'];
            $_POST['hair_color'] = $_POST['hair_color'] ?? $priorCard['hair_color'];
            $_POST['distinguished_features'] = $_POST['distinguished_features'] ?? $priorCard['distinguished_features'];
            $_POST['emergency_contact_name'] = $_POST['emergency_contact_name'] ?? $priorCard['emergency_contact_name'];
            $_POST['emergency_contact_relation'] = $_POST['emergency_contact_relation'] ?? $priorCard['emergency_contact_relation'];
            $_POST['emergency_contact_phone'] = $_POST['emergency_contact_phone'] ?? $priorCard['emergency_contact_phone'];
            $_POST['emergency_contact_address'] = $_POST['emergency_contact_address'] ?? $priorCard['emergency_contact_address'];
        }
    }
}

// Handle Save & Exit Request (Draft Save)
if ($_SERVER['REQUEST_METHOD'] === 'POST' && !empty($_POST['action_save_and_exit'])) {
    if (!validate_csrf()) {
        $errors[] = 'Security validation token mismatch. Please reload and try again.';
    } else {
        // Save uploaded photo file if provided
        $savedPhotoPath = trim($_POST['existing_photo_path'] ?? '');
        if (isset($_FILES['photo_file']) && $_FILES['photo_file']['error'] === UPLOAD_ERR_OK) {
            $ext = strtolower(pathinfo($_FILES['photo_file']['name'], PATHINFO_EXTENSION));
            if (in_array($ext, ['jpg', 'jpeg', 'png']) && $_FILES['photo_file']['size'] <= 5 * 1024 * 1024) {
                $dir = __DIR__ . '/uploads/photos/';
                if (!is_dir($dir)) @mkdir($dir, 0755, true);
                $fname = 'photo_draft_' . $applicantId . '_' . time() . '.' . $ext;
                if (move_uploaded_file($_FILES['photo_file']['tmp_name'], $dir . $fname)) {
                    $savedPhotoPath = 'uploads/photos/' . $fname;
                }
            }
        }

        // Save uploaded document files if provided
        $docKeys = ['doc_passport_copy', 'doc_residence_visa', 'doc_quota_approval', 'doc_domicile_proof', 'doc_additional'];
        $savedDocPaths = [];
        foreach ($docKeys as $dk) {
            $savedDocPaths[$dk] = trim($_POST['existing_' . $dk] ?? '');
            if (isset($_FILES[$dk]) && $_FILES[$dk]['error'] === UPLOAD_ERR_OK) {
                $ext = strtolower(pathinfo($_FILES[$dk]['name'], PATHINFO_EXTENSION));
                if (in_array($ext, ['pdf', 'jpg', 'jpeg', 'png']) && $_FILES[$dk]['size'] <= 5 * 1024 * 1024) {
                    $dir = __DIR__ . '/uploads/documents/';
                    if (!is_dir($dir)) @mkdir($dir, 0755, true);
                    $fname = $dk . '_draft_' . $applicantId . '_' . time() . '.' . $ext;
                    if (move_uploaded_file($_FILES[$dk]['tmp_name'], $dir . $fname)) {
                        $savedDocPaths[$dk] = 'uploads/documents/' . $fname;
                    }
                }
            }
        }

        // Prepare draft payload
        $draftPayload = $_POST;
        $draftPayload['existing_photo_path'] = $savedPhotoPath;
        foreach ($savedDocPaths as $dk => $pv) {
            $draftPayload['existing_' . $dk] = $pv;
        }
        $savedStep = (int)($_POST['current_step_saved'] ?? 1);
        $draftPayload['current_step_saved'] = $savedStep;
        $draftPayload['saved_at'] = date('d M Y, h:i A');

        $db->prepare("UPDATE applicants SET draft_data = :dd, draft_step = :ds WHERE id = :id")
           ->execute([
               ':dd' => json_encode($draftPayload),
               ':ds' => $savedStep,
               ':id' => $applicantId
           ]);

        $_SESSION['application_draft'] = $draftPayload;
        setFlash('success', 'Your application progress has been successfully saved. You can continue anytime.');
        header("Location: applicant-dashboard");
        exit();
    }
}

// Handle Form Submission
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['action_submit_application'])) {
    if (!validate_csrf()) {
        $errors[] = 'Security validation token mismatch. Please reload and try again.';
    } else {
        // -------------------------------------------------------------
        // 1. SECTION 1: Passport Details & Photograph
        // -------------------------------------------------------------
        $passportNumber = strtoupper(trim($_POST['passport_number'] ?? ''));
        $passportIssueDate = trim($_POST['passport_issue_date'] ?? '');
        $passportExpiry = trim($_POST['passport_expiry'] ?? '');

        // Photo Upload Handling
        $photoPath = null;
        if (isset($_FILES['photo_file']) && $_FILES['photo_file']['error'] === UPLOAD_ERR_OK) {
            $tmpName = $_FILES['photo_file']['tmp_name'];
            $origName = $_FILES['photo_file']['name'];
            $fileSize = $_FILES['photo_file']['size'];
            $ext = strtolower(pathinfo($origName, PATHINFO_EXTENSION));

            if (!in_array($ext, ['jpg', 'jpeg', 'png'])) {
                $errors[] = 'Passport photograph must be in JPG or PNG format.';
            } elseif ($fileSize > 5 * 1024 * 1024) {
                $errors[] = 'Passport photograph size must not exceed 5MB.';
            } else {
                $finfo = finfo_open(FILEINFO_MIME_TYPE);
                $mime = finfo_file($finfo, $tmpName);
                finfo_close($finfo);

                if (!in_array($mime, ['image/jpeg', 'image/png'])) {
                    $errors[] = 'Uploaded file is not a valid image.';
                } else {
                    $uploadDir = __DIR__ . '/uploads/photos/';
                    if (!is_dir($uploadDir)) {
                        @mkdir($uploadDir, 0755, true);
                    }
                    $newFilename = 'photo_' . date('Ymd_His') . '_' . bin2hex(random_bytes(6)) . '.' . $ext;
                    if (move_uploaded_file($tmpName, $uploadDir . $newFilename)) {
                        $photoPath = 'uploads/photos/' . $newFilename;
                    } else {
                        $errors[] = 'Failed to save uploaded passport photo. Please try again.';
                    }
                }
            }
        } elseif (!empty($_POST['existing_photo_path'])) {
            $photoPath = trim($_POST['existing_photo_path']);
        } else {
            $errors[] = 'Passport photograph is required.';
        }

        // Passport Number Validation: characters and numbers only
        if (empty($passportNumber)) {
            $errors[] = 'Passport Number is required.';
        } elseif (!preg_match('/^[A-Z0-9]+$/', $passportNumber)) {
            $errors[] = 'Passport Number should accept only characters and numbers only.';
        } elseif (strlen($passportNumber) < 6 || strlen($passportNumber) > 12) {
            $errors[] = 'Passport Number must be between 6 and 12 characters and numbers.';
        }
        if (empty($passportIssueDate)) {
            $errors[] = 'Passport Issue Date is required.';
        } elseif (strtotime($passportIssueDate) > time()) {
            $errors[] = 'Passport Issue Date cannot be in the future.';
        }

        if (empty($passportExpiry)) {
            $errors[] = 'Passport Expiry Date is required.';
        } else {
            $expiryTs = strtotime($passportExpiry);
            $minValidityTs = strtotime('+6 months');
            if ($expiryTs === false) {
                $errors[] = 'Invalid Passport Expiry Date format.';
            } elseif ($expiryTs < $minValidityTs) {
                $errors[] = 'Your passport must be valid for at least 6 months (expiry date must be on or after ' . date('d/m/Y', $minValidityTs) . ').';
            }
        }

        // -------------------------------------------------------------
        // 2. SECTION 2: Personal Particulars & Applicant Contact
        // -------------------------------------------------------------
        $surname = strtoupper(trim($_POST['surname'] ?? ''));
        $forenames = strtoupper(trim($_POST['forenames'] ?? ''));
        $nationality = strtoupper(trim($_POST['nationality'] ?? ''));
        $dob = trim($_POST['date_of_birth'] ?? '');
        $pob = strtoupper(trim($_POST['place_of_birth'] ?? ''));
        $sex = strtoupper(trim($_POST['sex'] ?? ''));
        $height = strtoupper(trim($_POST['height'] ?? ''));
        $complexion = strtoupper(trim($_POST['complexion'] ?? ''));
        $eyeColor = strtoupper(trim($_POST['eye_color'] ?? ''));
        $hairColor = strtoupper(trim($_POST['hair_color'] ?? ''));
        $distinguishedFeatures = trim($_POST['distinguished_features'] ?? '');
        $profession = strtoupper(trim($_POST['profession'] ?? ''));
        $nationalIdNumber = null;
        $taxIdNumber = null;

        // Applicant Contact & Address
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

        $changeStreet = trim($_POST['change_address'] ?? '');
        $changeCity = trim($_POST['change_city'] ?? '');
        $changeLga = trim($_POST['change_lga'] ?? '');
        $changeState = trim($_POST['change_state'] ?? '');
        if ($changeStreet || $changeCity || $changeLga || $changeState) {
            $changeParts = array_filter([
                $changeStreet,
                $changeCity,
                $changeLga ? ($changeLga . ' LGA') : '',
                $changeState ? ($changeState . ' STATE') : ''
            ]);
            $changeOfAddress = strtoupper(trim(implode(', ', $changeParts)));
        } else {
            $changeOfAddress = strtoupper(trim($_POST['change_of_address'] ?? ''));
        }
        $bloodGroup = strtoupper(trim($_POST['blood_group'] ?? 'UNKNOWN'));
        if (empty($bloodGroup)) {
            $bloodGroup = 'UNKNOWN';
        }

        if (empty($surname) || !preg_match("/^[\p{L}\s'\-]{2,50}$/u", $surname)) {
            $errors[] = 'Surname is mandatory and must contain only letters.';
        }
        if (empty($forenames) || !preg_match("/^[\p{L}\s'\-]{2,50}$/u", $forenames)) {
            $errors[] = 'Given names are mandatory and must contain only letters.';
        }
        if (empty($nationality)) {
            $errors[] = 'Nationality is mandatory. Please select your country of origin.';
        }
        if (empty($dob)) {
            $errors[] = 'Date of birth is mandatory.';
        }
        if (empty($pob) || !preg_match("/^[\p{L}\s',.\-]{2,100}$/u", $pob)) {
            $errors[] = 'Place of birth is mandatory and must contain only letters.';
        }
        if (empty($profession) || !preg_match("/^[\p{L}\s'\,\.\-\/\&]{2,100}$/u", $profession)) {
            $errors[] = 'Profession / Occupation is mandatory and must contain only letters.';
        }
        if (!empty($distinguishedFeatures) && !preg_match("/^[\p{L}0-9\s',.\/\-]{2,150}$/u", $distinguishedFeatures)) {
            $errors[] = 'Distinguishing features contains invalid characters.';
        }
        if (empty($sex)) {
            $errors[] = 'Gender selection is mandatory.';
        }
        if (empty($phone) || empty($email)) {
            $errors[] = 'Phone number and email address are mandatory for status alerts.';
        } elseif (!filter_var($email, FILTER_VALIDATE_EMAIL)) {
            $errors[] = 'Valid email address is required.';
        } elseif (!preg_match('/^\+?[0-9\s\-\(\)]{7,20}$/', $phone)) {
            $errors[] = 'Valid phone number is required (minimum 7 digits).';
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
        } elseif (!preg_match('/^[+]?[0-9\s\-()]{7,20}$/', $emergencyPhone)) {
            $errors[] = 'Please enter a valid Next of Kin phone number (minimum 7 digits).';
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
        $maxDocSize = 5 * 1024 * 1024; // 5MB

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
                    $errors[] = "{$docTitle}: File size exceeds maximum allowable limit of 5MB.";
                } else {
                    $finfo = finfo_open(FILEINFO_MIME_TYPE);
                    $mime = finfo_file($finfo, $tmpName);
                    finfo_close($finfo);

                    if (!in_array($mime, $allowedDocMimes, true)) {
                        $errors[] = "{$docTitle}: Uploaded file failed MIME security validation ({$mime}).";
                    } else {
                        $newFilename = $filePrefix . '_' . date('Ymd_His') . '_' . bin2hex(random_bytes(6)) . '.' . $ext;
                        if (move_uploaded_file($tmpName, $docUploadDir . $newFilename)) {
                            $savedPath = 'uploads/documents/' . $newFilename;
                        } else {
                            $errors[] = "{$docTitle}: Failed to save uploaded file on server. Please try again.";
                        }
                    }
                }
            } elseif (!empty($_POST['existing_' . $inputName])) {
                $savedPath = trim($_POST['existing_' . $inputName]);
            } elseif ($isMandatory) {
                $errors[] = "{$docTitle} is mandatory. Please attach a valid PDF, JPG, or PNG document.";
            }
            return $savedPath;
        };

        $docPassportCopy   = $handleDocUpload('doc_passport_copy', 'doc_pass', true, 'International Passport Bio-data Page');
        $docResidenceVisa  = $handleDocUpload('doc_residence_visa', 'doc_visa', true, 'STR Visa / Valid Entry Visa');
        $docQuotaApproval  = $handleDocUpload('doc_quota_approval', 'doc_quota', true, 'Expatriate Quota Approval / Letter of Employment');
        $docDomicileProof  = $handleDocUpload('doc_domicile_proof', 'doc_domicile', false, 'Proof of Domicile / Residence');
        $docAdditional     = $handleDocUpload('doc_additional', 'doc_add', false, 'Tax Clearance / CAC / Additional Document');

        if (empty($_POST['docs_genuine_confirm'])) {
            $errors[] = 'You must confirm that all uploaded supporting documents are genuine and properly readable.';
        }

        // -------------------------------------------------------------
        // 5. SECTION 5: Review Application Declaration
        // -------------------------------------------------------------
        if (empty($_POST['review_acknowledgement_check'])) {
            $errors[] = 'You must confirm and accept the applicant declaration before completing your application.';
        }

        // -------------------------------------------------------------
        // 6. SECTION 6: Fee Payment (Paystack / Development Stage)
        // -------------------------------------------------------------
        $feeAmount = defined('RESIDENCE_CARD_FEE_NAIRA') ? (float)RESIDENCE_CARD_FEE_NAIRA : 35000.00;
        $paymentMethod = strtoupper(trim($_POST['payment_method'] ?? 'PAYSTACK'));
        $paymentDate = date('Y-m-d H:i:s');
        $paymentRef = trim($_POST['payment_reference'] ?? '');
        $paymentStatus = strtoupper(trim($_POST['payment_status'] ?? ''));

        // During development stage, allow proceeding directly if payment was deferred
        if (empty($paymentRef)) {
            $paymentRef = 'DEV-PAY-' . date('Ymd') . '-' . rand(10000, 99999);
        }
        if (empty($paymentStatus)) {
            $paymentStatus = 'PENDING';
        }

        // -------------------------------------------------------------
        // 7. SECTION 7: Biometrics Appointment Booking
        // -------------------------------------------------------------
        $enrollmentCenter = trim($_POST['enrollment_center'] ?? 'NIS HQ');
        if (empty($enrollmentCenter)) {
            $enrollmentCenter = 'NIS HQ';
        }
        $appointmentDate = trim($_POST['appointment_date'] ?? '');
        $appointmentTime = trim($_POST['appointment_time'] ?? '09:00 AM');

        if (empty($appointmentDate)) {
            $errors[] = 'Please select a date for your physical biometrics capturing appointment.';
        }

        if (empty($_POST['confirmationCheck'])) {
            $errors[] = 'You must solemnly declare and accept that all provided particulars are true and verified.';
        }

        // Duplicate Passport check (temporarily disabled per request)
        /*
        if (empty($errors)) {
            $chk = $db->prepare("SELECT id, application_number FROM applications WHERE UPPER(passport_number) = :pn AND status IN ('PENDING_APPROVAL', 'APPROVED_FOR_BIOMETRICS') LIMIT 1");
            $chk->execute([':pn' => $passportNumber]);
            $dup = $chk->fetch();
            if ($dup) {
                $errors[] = "An active online application (No. {$dup['application_number']}) is already in progress for Passport Number '{$passportNumber}'. Please track your existing application.";
            }
        }
        */

        // Process Record Insertion
        if (empty($errors)) {
            $appNumber = 'RC-' . date('Y') . '-' . str_pad((string)rand(100000, 999999), 6, '0', STR_PAD_LEFT);
            $referenceNumber = 'APP-' . strtoupper(substr(md5(uniqid('', true)), 0, 10));

            // Determine or Create Applicant Profile
            $applicantId = !empty($_SESSION['applicant_id']) ? (int)$_SESSION['applicant_id'] : null;
            $accountPassword = trim($_POST['account_password'] ?? '');
            if (!$applicantId && !empty($accountPassword) && strlen($accountPassword) >= 6) {
                $chkApp = $db->prepare("SELECT id FROM applicants WHERE email = :em LIMIT 1");
                $chkApp->execute([':em' => $email]);
                $existingApp = $chkApp->fetch();
                if ($existingApp) {
                    $applicantId = (int)$existingApp['id'];
                } else {
                    $pwdHash = password_hash($accountPassword, PASSWORD_BCRYPT);
                    $insApp = $db->prepare("INSERT INTO applicants (email, password_hash, surname, forenames, nationality, passport_number, phone, photo_path) 
                                            VALUES (:em, :pw, :sn, :fn, :nat, :pn, :ph, :photo)");
                    $insApp->execute([
                        ':em' => $email,
                        ':pw' => $pwdHash,
                        ':sn' => $surname,
                        ':fn' => $forenames,
                        ':nat' => $nationality,
                        ':pn' => $passportNumber,
                        ':ph' => $phone,
                        ':photo' => $photoPath
                    ]);
                    $applicantId = (int)$db->lastInsertId();
                }
                $_SESSION['applicant_id'] = $applicantId;
                $_SESSION['applicant_name'] = trim($forenames . ' ' . $surname);
                $_SESSION['applicant_email'] = $email;
                $_SESSION['applicant_nationality'] = $nationality;
                $_SESSION['applicant_passport'] = $passportNumber;
            } elseif (!$applicantId) {
                $chkApp = $db->prepare("SELECT id FROM applicants WHERE email = :em LIMIT 1");
                $chkApp->execute([':em' => $email]);
                $existingApp = $chkApp->fetch();
                if ($existingApp) {
                    $applicantId = (int)$existingApp['id'];
                }
            }

            if ($applicantId) {
                // Ensure applicant profile contains latest nationality, passport, and photo
                $updProf = $db->prepare("UPDATE applicants SET 
                    nationality = CASE WHEN nationality = '' OR nationality IS NULL THEN :nat ELSE nationality END,
                    passport_number = CASE WHEN passport_number = '' OR passport_number IS NULL THEN :pn ELSE passport_number END,
                    photo_path = CASE WHEN photo_path = '' OR photo_path IS NULL THEN :ph ELSE photo_path END
                    WHERE id = :aid");
                $updProf->execute([
                    ':nat' => $nationality,
                    ':pn' => $passportNumber,
                    ':ph' => $photoPath,
                    ':aid' => $applicantId
                ]);
                $_SESSION['applicant_nationality'] = $nationality;
                $_SESSION['applicant_passport'] = $passportNumber;
            }

            $insertStmt = $db->prepare("INSERT INTO applications (
                application_number, reference_number, applicant_id, surname, forenames, nationality,
                date_of_birth, place_of_birth, sex, height, complexion, eye_color, hair_color, distinguished_features,
                profession, domicile, change_of_address, passport_number, passport_issue_date, passport_expiry, national_id_number, tax_id_number,
                phone, email, emergency_contact_name, emergency_contact_relation, emergency_contact_phone,
                emergency_contact_address, blood_group, enrollment_center, appointment_date, appointment_time,
                fee_amount, payment_status, payment_method, payment_date, payment_reference, photo_path,
                doc_passport_copy, doc_residence_visa, doc_quota_approval, doc_domicile_proof, doc_additional, status
            ) VALUES (
                :app_num, :ref_num, :applicant_id, :surname, :forenames, :nationality,
                :dob, :pob, :sex, :height, :complexion, :eye_color, :hair_color, :dist_feat,
                :profession, :domicile, :change_of_address, :passport_number, :passport_issue, :passport_expiry, :nin, :tax_id,
                :phone, :email, :em_name, :em_rel, :em_phone,
                :em_addr, :blood, :center, :app_date, :app_time,
                :fee, :pay_status, :pay_method, :pay_date, :pay_ref, :photo,
                :doc_pass, :doc_visa, :doc_quota, :doc_domicile, :doc_add, 'PENDING_APPROVAL'
            )");

            $insertStmt->execute([
                ':app_num' => $appNumber,
                ':ref_num' => $referenceNumber,
                ':applicant_id' => $applicantId ?: null,
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
                ':nin' => $nationalIdNumber ?: null,
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

            // Clear saved draft upon successful submission
            if (!empty($applicantId)) {
                $db->prepare("UPDATE applicants SET draft_data = NULL, draft_step = 1 WHERE id = :aid")->execute([':aid' => $applicantId]);
                unset($_SESSION['application_draft']);
            }

            // Dispatch Notifications to Approving Officers
            try {
                require_once __DIR__ . '/includes/notifications.php';
                $approvers = $db->query("SELECT id FROM users WHERE role IN ('SuperAdmin', 'ApprovingOfficer') AND is_active = 1")->fetchAll();
                foreach ($approvers as $appr) {
                    createNotification(
                        (int)$appr['id'],
                        NOTIF_APPROVAL_RECEIVED,
                        'New Online Application Received',
                        "New Residence Card Application No. {$appNumber} for {$surname}, {$forenames} ({$nationality}) awaiting review.",
                        "pending-approvals",
                        $db
                    );
                }
            } catch (Throwable $e) {
                // Ignore notification error
            }

            $insertedAppId = (int)$db->lastInsertId();

            $successApplication = [
                'id' => $insertedAppId,
                'created_at' => date('Y-m-d H:i:s'),
                'app_number' => $appNumber,
                'reference_number' => $referenceNumber,
                'surname' => $surname,
                'forenames' => $forenames,
                'nationality' => $nationality,
                'passport_number' => $passportNumber,
                'passport_issue_date' => $passportIssueDate,
                'passport_expiry' => $passportExpiry,
                'enrollment_center' => $enrollmentCenter,
                'appointment_date' => $appointmentDate,
                'appointment_time' => $appointmentTime,
                'fee_amount' => $feeAmount,
                'payment_method' => $paymentMethod,
                'payment_ref' => $paymentRef,
                'payment_date' => $paymentDate,
                'email' => $email,
                'phone' => $phone,
                'distinguished_features' => $distinguishedFeatures,
                'photo_path' => $photoPath,
                'doc_passport_copy' => $docPassportCopy,
                'doc_residence_visa' => $docResidenceVisa,
                'doc_quota_approval' => $docQuotaApproval,
                'doc_domicile_proof' => $docDomicileProof,
                'doc_additional' => $docAdditional
            ];
        }
    }
}
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0, maximum-scale=5.0">
    <title>Online Residence Card Application — Nigeria Immigration Service</title>

    <!-- Favicon / URL Icon -->
    <link rel="icon" type="image/png" href="assets/images/nis-crest-logo-transparent.png?v=3">
    <link rel="icon" type="image/png" sizes="32x32" href="assets/images/favicon-32x32.png?v=3">
    <link rel="icon" type="image/png" sizes="16x16" href="assets/images/favicon-16x16.png?v=3">
    <link rel="shortcut icon" href="assets/images/favicon.ico?v=3" type="image/x-icon">
    <link rel="apple-touch-icon" href="assets/images/nis-crest-logo-transparent.png?v=3">

    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css">
    <link rel="stylesheet" href="assets/css/style.css?v=<?php echo APP_VERSION; ?>">

    <style>
        :root {
            --nis-green-dark: #113f1f;
            --nis-green: #1a5c2e;
            --nis-green-light: #27ae60;
            --nis-gold: #d4af37;
            --nis-gold-light: #fef9e7;
            --nis-slate: #1e293b;
            --nis-muted: #64748b;
            --nis-bg: #f8fafc;
            --nis-card: #ffffff;
            --nis-border: #cbd5e1;
            --nis-danger: #dc2626;
        }

        html {
            font-size: 16px;
        }

        body {
            background: linear-gradient(135deg, rgba(16, 55, 28, 0.68) 0%, rgba(15, 23, 42, 0.78) 100%),
                        url('assets/images/login-bg.jpg') no-repeat center center fixed;
            background-size: cover;
            color: var(--nis-slate);
            font-size: 0.95rem;
            line-height: 1.6;
            min-height: 100vh;
            display: flex;
            flex-direction: column;
        }

        /* Portal Nav */
        .portal-nav {
            background: #ffffff;
            border-bottom: none;
            box-shadow: 0 1px 4px rgba(0, 0, 0, 0.06);
            position: sticky;
            top: 0;
            z-index: 1000;
        }

        .nav-inner {
            max-width: 1100px;
            margin: 0 auto;
            padding: 12px 1.5rem;
            display: flex;
            justify-content: space-between;
            align-items: center;
        }

        .brand-link {
            display: flex;
            align-items: center;
            gap: 12px;
            text-decoration: none;
            color: inherit;
        }

        .brand-logo {
            height: 48px;
            width: auto;
        }

        .brand-titles h1 {
            font-size: 1.15rem;
            font-weight: 800;
            color: var(--nis-green-dark);
            text-transform: uppercase;
            letter-spacing: 0.5px;
        }

        .brand-titles h2 {
            font-size: 0.80rem;
            font-weight: 600;
            color: var(--nis-gold);
            letter-spacing: 0.4px;
        }

        .nav-actions a {
            text-decoration: none;
            color: var(--nis-slate);
            font-size: 0.92rem;
            font-weight: 600;
            margin-left: 1.25rem;
            transition: color 0.2s;
        }

        .nav-actions a:hover {
            color: var(--nis-green);
        }

        .app-container {
            max-width: 880px;
            margin: 1.5rem auto 3.5rem;
            padding: 0 1.25rem;
            flex: 1 0 auto;
            width: 100%;
        }

        .page-header-box {
            background: #ffffff;
            border-radius: 8px;
            padding: 1.5rem 2rem;
            margin-bottom: 1.5rem;
            box-shadow: 0 4px 16px rgba(0, 0, 0, 0.08);
            text-align: center;
        }

        .page-header-box h2 {
            font-size: 1.5rem;
            font-weight: 800;
            color: var(--nis-green-dark);
            margin-bottom: 0.35rem;
        }

        .page-header-box p {
            font-size: 0.9rem;
            color: #64748b;
            margin: 0 auto;
            max-width: 680px;
        }

        /* Success Card Enhancements - Matching Official NIS Appointment Slip */
        .success-card {
            background: #ffffff;
            border-radius: 8px;
            border: none;
            padding: 1.75rem 2.25rem 2rem;
            text-align: left;
            box-shadow: 0 4px 20px rgba(0, 0, 0, 0.06);
            max-width: 780px;
            margin: 0 auto 2.5rem;
            animation: fadeIn 0.35s ease-in-out;
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

        .success-status-unboxed {
            color: #92400e;
            font-weight: 700;
            background: none !important;
            padding: 0 !important;
            border-radius: 0 !important;
            display: inline !important;
        }

        /* Neatly arranged applicant metadata - NO BOXES */
        .success-meta-inline {
            display: flex;
            flex-wrap: wrap;
            align-items: center;
            gap: 6px 14px;
            font-size: 0.86rem;
            color: #475569;
            margin-top: 4px;
            padding: 4px 0;
        }

        .success-meta-inline .meta-item {
            display: inline-flex;
            align-items: center;
            gap: 5px;
        }

        .success-meta-inline .meta-label {
            color: #64748b;
            font-weight: 600;
        }

        .success-meta-inline strong {
            color: #0f172a;
            font-weight: 700;
        }

        .success-meta-inline .meta-sep {
            color: #cbd5e1;
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

        .success-actions-bar {
            display: flex;
            justify-content: center;
            align-items: center;
            gap: 1rem;
            flex-wrap: wrap;
            margin-top: 1.75rem;
            padding-top: 1.25rem;
            border-top: 1px solid #e2e8f0;
        }

        .btn-success-track {
            background: var(--nis-slate);
            color: #ffffff;
            padding: 10px 22px;
            border-radius: 6px;
            text-decoration: none;
            font-weight: 700;
            font-size: 0.88rem;
            display: inline-flex;
            align-items: center;
            transition: all 0.2s ease;
        }

        .btn-success-track:hover {
            background: #0f172a;
            color: #ffffff;
            transform: translateY(-1px);
        }

        .btn-success-print {
            background: var(--nis-green, #1a5c2e);
            color: #ffffff;
            padding: 11px 28px;
            border-radius: 6px;
            border: none;
            font-weight: 700;
            font-size: 0.92rem;
            cursor: pointer;
            text-decoration: none;
            display: inline-flex;
            align-items: center;
            gap: 8px;
            box-shadow: 0 4px 14px rgba(26, 92, 46, 0.25);
            transition: all 0.2s ease;
        }

        .btn-success-print:hover {
            background: #113f1f;
            color: #ffffff;
            transform: translateY(-1px);
            box-shadow: 0 6px 18px rgba(26, 92, 46, 0.35);
        }

        .btn-success-new {
            background: #ffffff;
            color: var(--nis-slate);
            border: 1.5px solid #cbd5e1;
            padding: 9px 18px;
            border-radius: 6px;
            text-decoration: none;
            font-weight: 600;
            font-size: 0.86rem;
            display: inline-flex;
            align-items: center;
            transition: all 0.2s ease;
        }

        .btn-success-new:hover {
            background: #f8fafc;
            border-color: #94a3b8;
            color: #0f172a;
        }



        @media print {
            .portal-nav,
            .portal-footer,
            .success-actions-bar {
                display: none !important;
            }
            body {
                background: #ffffff !important;
                color: #000000 !important;
            }
            .app-container {
                margin: 0 !important;
                padding: 0 !important;
                max-width: 100% !important;
            }
            .success-card {
                box-shadow: none !important;
                border: none !important;
                padding: 1.5rem !important;
            }
        }
    </style>
</head>
<body>

    <!-- Portal Navigation -->
    <?php require __DIR__ . '/includes/portal-header.php'; ?>

    <main class="app-container">

        <?php if ($successApplication): ?>
            <!-- Success Confirmation State (Official NIS Appointment Slip) -->
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
                    <?php echo generateBarcodeSvg($successApplication['app_number'], 44, 210); ?>
                </div>

                <!-- 1. Appointment Details -->
                <div class="success-sec-title">Appointment Details</div>
                <div class="success-dl">
                    <div class="success-dl-row">
                        <span class="success-dl-label">Application ID:</span>
                        <span class="success-dl-val font-mono" style="color: #0b6623; font-weight: 800;"><?php echo h($successApplication['app_number']); ?></span>
                    </div>
                    <div class="success-dl-row">
                        <span class="success-dl-label">Appointment Request Date:</span>
                        <span class="success-dl-val"><?php echo date('Y-m-d H:i:s', strtotime($successApplication['created_at'] ?? 'now')); ?></span>
                    </div>
                    <div class="success-dl-row">
                        <span class="success-dl-label">Appointment Date:</span>
                        <span class="success-dl-val"><?php echo date('Y-m-d', strtotime($successApplication['appointment_date'])); ?> <?php echo h($successApplication['appointment_time']); ?></span>
                    </div>
                    <div class="success-dl-row">
                        <span class="success-dl-label">Appointment for:</span>
                        <span class="success-dl-val">Residence Card</span>
                    </div>
                    <div class="success-dl-row">
                        <span class="success-dl-label">Center Address:</span>
                        <span class="success-dl-val">NIS HQ, Airport Sauka Abuja.</span>
                    </div>
                </div>

                <!-- 2. Personal & Contact Details -->
                <div class="success-sec-title">Personal &amp; Contact Details</div>
                <div class="success-dl">
                    <div class="success-dl-row">
                        <span class="success-dl-label">Reference ID:</span>
                        <span class="success-dl-val font-mono"><?php echo h($successApplication['reference_number'] ?? $successApplication['payment_ref']); ?></span>
                    </div>
                    <div class="success-dl-row">
                        <span class="success-dl-label">Name:</span>
                        <span class="success-dl-val" style="text-transform: uppercase; font-weight: 700;">
                            <?php echo strtoupper(h($successApplication['surname'] . ' ' . $successApplication['forenames'])); ?>
                        </span>
                    </div>
                </div>

                <!-- 3. Instructions -->
                <div class="success-instructions">
                    <div class="success-instructions-title">Instructions</div>
                    <ul>
                        <li>Ensure that you verify the status of the application on the portal prior to your appointment date. You can verify your status: <a href="track?app_num=<?php echo urlencode($successApplication['app_number']); ?>&passport=<?php echo urlencode($successApplication['passport_number']); ?>" style="color: #0b6623; font-weight: 700; text-decoration: underline;">here</a></li>
                        <li>You must bring the following items for the appointment:
                            <ol>
                                <li>Vetted application form</li>
                                <li>Printout of appointment slip</li>
                                <li>For reissue, the old residence card is mandatory.</li>
                            </ol>
                        </li>
                    </ul>
                </div>

                <!-- Action Buttons -->
                <div class="success-actions-bar">
                    <a href="print-appointment-slip?app_num=<?php echo urlencode($successApplication['app_number']); ?>&print=1" target="_blank" class="btn-success-print">
                        <i class="fas fa-print"></i> Print Slip
                    </a>
                </div>
            </div>

        <?php else: ?>


            <?php
            $formAction = 'apply' . ($isRenewal ? '?type=renewal' : '');
            $isSuperAdminEntry = false;
            require_once __DIR__ . '/includes/application-form-wizard.php';
            ?>

        <?php endif; ?>

    </main>

    <?php require_once __DIR__ . '/includes/portal-footer.php'; ?>
</body>
</html>
