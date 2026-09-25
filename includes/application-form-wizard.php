<?php
/**
 * NIS Residence Card Issuance System (NIS-RCIS)
 * Unified 6-Step Application Form Wizard Component
 * Shared between public apply.php and Super Admin new-card.php
 */

$formAction = $formAction ?? 'apply';
$isSuperAdminEntry = $isSuperAdminEntry ?? false;
$feeAmount = $feeAmount ?? (defined('RESIDENCE_CARD_FEE_NAIRA') ? (float)RESIDENCE_CARD_FEE_NAIRA : 35000.00);

$nigerianStates = [
    "Abia", "Adamawa", "Akwa Ibom", "Anambra", "Bauchi", "Bayelsa", "Benue", "Borno",
    "Cross River", "Delta", "Ebonyi", "Edo", "Ekiti", "Enugu", "Federal Capital Territory (FCT)",
    "Gombe", "Imo", "Jigawa", "Kaduna", "Kano", "Katsina", "Kebbi", "Kogi", "Kwara",
    "Lagos", "Nasarawa", "Niger", "Ogun", "Ondo", "Osun", "Oyo", "Plateau", "Rivers",
    "Sokoto", "Taraba", "Yobe", "Zamfara"
];
?>
<script src="assets/js/nigeria-states-lgas.js"></script>
<script src="https://js.paystack.co/v1/inline.js"></script>

<style>
.wizard-stepper-box {
    background: rgba(255, 255, 255, 0.98);
    backdrop-filter: blur(10px);
    -webkit-backdrop-filter: blur(10px);
    border: 1px solid var(--nis-border, #cbd5e1);
    border-radius: 10px;
    padding: 0.85rem 1.5rem 0.8rem;
    margin-bottom: 1.25rem;
    box-shadow: 0 4px 18px rgba(0, 0, 0, 0.07);
    position: sticky;
    top: 70px;
    z-index: 990;
    transition: all 0.2s ease;
}
.wizard-header-intro {
    text-align: center;
    padding-bottom: 0.25rem;
    margin-bottom: 0.65rem;
    border-bottom: none;
}
.wizard-header-intro h2 {
    font-size: 1.22rem;
    font-weight: 800;
    color: var(--nis-green-dark, #113f1f);
    margin: 0 0 0.2rem 0;
    letter-spacing: -0.2px;
}
.wizard-header-intro p {
    font-size: 0.82rem;
    color: #64748b;
    margin: 0 auto;
    max-width: 780px;
    line-height: 1.4;
}
.wizard-steps {
    display: flex;
    justify-content: space-between;
    list-style: none;
    padding: 0;
    margin: 0;
    gap: 8px;
    position: relative;
}
.wizard-step-item {
    flex: 1;
    display: flex;
    flex-direction: column;
    align-items: center;
    cursor: pointer;
    text-align: center;
    position: relative;
}
.wizard-step-circle {
    width: 32px;
    height: 32px;
    border-radius: 50%;
    background: #f1f5f9;
    color: #475569;
    border: 2px solid #cbd5e1;
    display: flex;
    align-items: center;
    justify-content: center;
    font-weight: 700;
    font-size: 0.88rem;
    margin-bottom: 4px;
    transition: all 0.2s ease;
}
.wizard-step-item.active .wizard-step-circle {
    background: var(--nis-green, #1a5c2e);
    border-color: var(--nis-green, #1a5c2e);
    color: #ffffff;
}
.wizard-step-item.completed .wizard-step-circle {
    background: #10b981;
    border-color: #10b981;
    color: #ffffff;
}
.wizard-step-label {
    font-size: 0.76rem;
    font-weight: 600;
    color: #64748b;
    line-height: 1.25;
}
.wizard-step-item.active .wizard-step-label {
    color: var(--nis-green-dark, #113f1f);
    font-weight: 700;
}
.form-step-pane {
    display: none;
}
.form-step-pane.active {
    display: block;
}
.form-grid-2 {
    display: grid;
    grid-template-columns: 1fr 1fr;
    gap: 16px 20px;
    margin-bottom: 16px;
}
.form-grid-3 {
    display: grid;
    grid-template-columns: 1fr 1fr 1fr;
    gap: 16px 20px;
    margin-bottom: 16px;
}
@media (max-width: 768px) {
    .wizard-stepper-box {
        padding: 1.25rem 1rem 1.25rem;
    }
    .wizard-header-intro h2 {
        font-size: 1.35rem;
    }
    .wizard-header-intro p {
        font-size: 0.88rem;
    }
    .form-grid-2, .form-grid-3 {
        grid-template-columns: 1fr;
    }
    .wizard-steps {
        overflow-x: auto;
        padding-bottom: 8px;
    }
    .wizard-step-label {
        font-size: 0.74rem;
    }
}
/* Form Controls & Labels inside Wizard */
#applicationWizardForm .form-group {
    margin-bottom: 14px;
}
#applicationWizardForm .form-group label {
    font-size: 0.92rem;
    font-weight: 600;
    margin-bottom: 6px;
    display: block;
    color: var(--nis-slate, #1e293b);
}
#applicationWizardForm .form-control,
#applicationWizardForm .form-select,
#applicationWizardForm select {
    font-size: 0.95rem !important;
    padding: 9px 13px;
    height: auto;
    border-radius: 6px;
}
#applicationWizardForm .form-control::placeholder,
#applicationWizardForm .form-select::placeholder,
#applicationWizardForm input::placeholder,
#applicationWizardForm textarea::placeholder {
    font-size: 0.88rem !important;
}
#applicationWizardForm .help-text {
    font-size: 0.84rem !important;
    color: #64748b;
    line-height: 1.45;
}

/* Photo Enrollment & Passport Photograph Specifications */
.photo-enroll-card {
    display: flex;
    gap: 24px;
    background: #f8fafc;
    border: 1px solid #e2e8f0;
    border-radius: 8px;
    padding: 20px;
    margin-bottom: 22px;
    align-items: flex-start;
}
@media (max-width: 640px) {
    .photo-enroll-card {
        flex-direction: column;
        align-items: center;
        text-align: center;
    }
}
.photo-preview-wrap {
    width: 120px;
    height: 152px;
    background: #ffffff;
    border: 2px dashed #cbd5e1;
    border-radius: 6px;
    display: flex;
    align-items: center;
    justify-content: center;
    overflow: hidden;
    position: relative;
    flex-shrink: 0;
}
.photo-preview-wrap img {
    width: 100%;
    height: 100%;
    object-fit: cover;
    object-position: center;
    display: none;
}
.photo-upload-details {
    flex: 1;
    min-width: 0;
}
.photo-action-bar {
    display: flex;
    align-items: center;
    gap: 12px;
    flex-wrap: wrap;
    margin-top: 14px;
    margin-bottom: 0;
}
.icao-photo-specs {
    background: #ffffff;
    border: 1px solid #e2e8f0;
    border-radius: 6px;
    padding: 14px 18px;
}
.icao-spec-title {
    font-size: 0.90rem;
    font-weight: 700;
    color: #1e293b;
    margin-bottom: 8px;
}
.icao-spec-list {
    list-style: disc;
    padding-left: 20px;
    margin: 0;
}
.icao-spec-list li {
    font-size: 0.85rem;
    color: #475569;
    line-height: 1.5;
    margin-bottom: 4px;
}
.icao-spec-list li:last-child {
    margin-bottom: 0;
}
.step-nav-footer {
    display: flex;
    justify-content: space-between;
    align-items: center;
    margin-top: 1.75rem;
    padding-top: 1.25rem;
    border-top: 1px solid var(--nis-border, #cbd5e1);
    flex-wrap: wrap;
    gap: 10px;
}
.step-nav-footer .btn {
    font-size: 0.95rem;
    padding: 10px 24px;
    font-weight: 600;
}

/* Review Section & Data Grid Styling */
.review-section-box {
    background: #ffffff;
    border: 1px solid #e2e8f0;
    border-radius: 8px;
    padding: 1.25rem 1.5rem;
    margin-bottom: 1.25rem;
    box-shadow: 0 1px 3px rgba(0, 0, 0, 0.03);
}
.review-section-title {
    font-size: 0.82rem;
    font-weight: 800;
    color: var(--nis-green-dark, #113f1f);
    text-transform: uppercase;
    letter-spacing: 0.6px;
    border-bottom: 1.5px solid #f1f5f9;
    padding-bottom: 8px;
    margin-bottom: 14px;
    display: flex;
    align-items: center;
    gap: 8px;
}
.review-grid-2 {
    display: grid;
    grid-template-columns: 1fr 1fr;
    gap: 14px 24px;
}
.review-grid-3 {
    display: grid;
    grid-template-columns: 1fr 1fr 1fr;
    gap: 14px 24px;
}
@media (max-width: 768px) {
    .review-grid-2, .review-grid-3 {
        grid-template-columns: 1fr;
    }
}
.review-item-label {
    font-size: 0.74rem;
    font-weight: 700;
    color: #64748b;
    text-transform: uppercase;
    letter-spacing: 0.4px;
    margin-bottom: 4px;
    display: block;
}
.review-item-value {
    font-size: 0.94rem;
    font-weight: 600;
    color: #0f172a;
    line-height: 1.45;
}
.review-dossier-banner {
    display: flex;
    align-items: center;
    gap: 20px;
    background: #f8fafc;
    border: 1px solid #e2e8f0;
    border-radius: 8px;
    padding: 16px 20px;
    margin-bottom: 1.5rem;
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
.btn-doc-eye {
    display: inline-flex;
    align-items: center;
    gap: 4px;
    padding: 3px 8px;
    font-size: 0.76rem;
    font-weight: 600;
    color: #113f1f;
    background: #ffffff;
    border: 1px solid #cbd5e1;
    border-radius: 4px;
    cursor: pointer;
    text-decoration: none;
    transition: all 0.15s ease;
}
.btn-doc-eye:hover {
    background: #f1f5f9;
    border-color: #94a3b8;
}

@media (max-width: 768px) {
    .wizard-stepper-box {
        position: relative;
        top: 0;
        padding: 0.85rem 0.85rem 0.65rem;
    }
    .wizard-header-intro h2 {
        font-size: 1.15rem;
    }
    .wizard-steps {
        overflow-x: auto;
        -webkit-overflow-scrolling: touch;
        gap: 12px;
        padding-bottom: 6px;
    }
    .wizard-step-item {
        flex: 0 0 auto;
        min-width: 68px;
    }
    .wizard-step-label {
        font-size: 0.72rem;
        white-space: nowrap;
    }
    .step-nav-footer {
        flex-direction: column-reverse;
        align-items: stretch;
        gap: 10px;
    }
    .step-nav-footer > div {
        display: flex;
        flex-direction: column;
        width: 100%;
        gap: 8px !important;
    }
    .step-nav-footer .btn {
        width: 100%;
        text-align: center;
        justify-content: center;
        padding: 12px 16px;
    }
    .review-dossier-banner {
        flex-direction: column;
        text-align: center;
        align-items: center;
    }
    .photo-enroll-card {
        flex-direction: column;
        align-items: center;
        text-align: center;
    }
    .photo-action-bar {
        justify-content: center;
    }
}

@media (max-width: 480px) {
    #applicationWizardForm {
        padding: 1.15rem 1rem !important;
    }
    .review-section-box {
        padding: 1rem;
    }
    #applicationWizardForm input,
    #applicationWizardForm select,
    #applicationWizardForm textarea {
        font-size: 16px !important; /* Prevents auto-zoom on mobile iOS */
    }
}
</style>

<!-- Wizard Step Navigation -->
<div class="wizard-stepper-box">
    <?php if (!empty($wizardTitle)): ?>
        <div class="wizard-header-intro">
            <h2><?php echo h($wizardTitle); ?></h2>
            <?php if (!empty($wizardSubtitle)): ?>
                <p><?php echo h($wizardSubtitle); ?></p>
            <?php endif; ?>
        </div>
    <?php endif; ?>

    <ol class="wizard-steps">
        <li class="wizard-step-item active" id="stepPill-1" onclick="goToStep(1)">
            <div class="wizard-step-circle">1</div>
            <span class="wizard-step-label">Passport &amp; Photo</span>
        </li>
        <li class="wizard-step-item" id="stepPill-2" onclick="goToStep(2)">
            <div class="wizard-step-circle">2</div>
            <span class="wizard-step-label">Particulars &amp; Contact</span>
        </li>
        <li class="wizard-step-item" id="stepPill-3" onclick="goToStep(3)">
            <div class="wizard-step-circle">3</div>
            <span class="wizard-step-label">Next of Kin</span>
        </li>
        <li class="wizard-step-item" id="stepPill-4" onclick="goToStep(4)">
            <div class="wizard-step-circle">4</div>
            <span class="wizard-step-label">Documents</span>
        </li>
        <li class="wizard-step-item" id="stepPill-5" onclick="goToStep(5)">
            <div class="wizard-step-circle">5</div>
            <span class="wizard-step-label">Review</span>
        </li>
        <li class="wizard-step-item" id="stepPill-6" onclick="goToStep(6)">
            <div class="wizard-step-circle">6</div>
            <span class="wizard-step-label">Fee Payment</span>
        </li>
        <li class="wizard-step-item" id="stepPill-7" onclick="goToStep(7)">
            <div class="wizard-step-circle">7</div>
            <span class="wizard-step-label">Appointment Booking</span>
        </li>
    </ol>
</div>

<?php if (!empty($errors)): ?>
    <div class="alert alert-danger" style="background: #fef2f2; border: 1.5px solid #f87171; color: #991b1b; padding: 14px 18px; border-radius: 8px; margin-bottom: 1.5rem;">
        <strong>Please correct the following:</strong>
        <ul style="margin-left: 20px; margin-top: 6px;">
            <?php foreach ($errors as $err): ?>
                <li><?php echo h($err); ?></li>
            <?php endforeach; ?>
        </ul>
    </div>
<?php endif; ?>

<form method="POST" action="<?php echo h($formAction); ?>" enctype="multipart/form-data" id="applicationWizardForm" class="app-card" style="padding: 1.75rem; background: #ffffff; border: 1px solid var(--nis-border, #cbd5e1); border-radius: 8px;" autocomplete="off" novalidate>
    <?php echo csrf_field(); ?>
    <input type="hidden" name="action_submit_application" value="1">
    <input type="hidden" name="action_save_and_exit" id="action_save_and_exit" value="0">
    <input type="hidden" name="current_step_saved" id="current_step_saved" value="1">
    <input type="hidden" name="existing_photo_path" id="existing_photo_path" value="<?php echo h($_POST['existing_photo_path'] ?? ''); ?>">
    <input type="hidden" name="existing_doc_passport_copy" id="existing_doc_passport_copy" value="<?php echo h($_POST['existing_doc_passport_copy'] ?? ''); ?>">
    <input type="hidden" name="existing_doc_residence_visa" id="existing_doc_residence_visa" value="<?php echo h($_POST['existing_doc_residence_visa'] ?? ''); ?>">
    <input type="hidden" name="existing_doc_quota_approval" id="existing_doc_quota_approval" value="<?php echo h($_POST['existing_doc_quota_approval'] ?? ''); ?>">
    <input type="hidden" name="existing_doc_domicile_proof" id="existing_doc_domicile_proof" value="<?php echo h($_POST['existing_doc_domicile_proof'] ?? ''); ?>">
    <input type="hidden" name="existing_doc_additional" id="existing_doc_additional" value="<?php echo h($_POST['existing_doc_additional'] ?? ''); ?>">

    <!-- ========================================================= -->
    <!-- SECTION 1: Passport Details & Photograph                  -->
    <!-- ========================================================= -->
    <div class="form-step-pane active" id="pane-1">
        <h3 style="font-size: 1.05rem; color: var(--nis-green-dark, #113f1f); margin-bottom: 6px; font-weight: 700;">
            1. Passport Details &amp; Photograph
        </h3>
        <p style="font-size: 0.92rem; color: #64748b; margin-bottom: 1.35rem; line-height: 1.5;">
            Upload applicant photograph and provide valid international passport particulars.
        </p>

        <div class="photo-enroll-card">
            <div class="photo-preview-wrap">
                <img id="photoPreviewImg" src="<?php echo !empty($_POST['existing_photo_path']) ? h($_POST['existing_photo_path']) : ''; ?>" alt="Photo Preview" <?php echo !empty($_POST['existing_photo_path']) ? 'style="display: block;"' : ''; ?>>
                <div id="photoPlaceholder" style="text-align: center; color: #94a3b8; padding: 10px; <?php echo !empty($_POST['existing_photo_path']) ? 'display: none;' : ''; ?>">
                    <i class="fas fa-camera" style="font-size: 2rem; margin-bottom: 6px; color: #cbd5e1; display: block;"></i>
                    <div style="font-weight: 700; font-size: 0.88rem; color: #475569;">Passport Photo</div>
                    <div style="font-size: 0.78rem; color: #94a3b8;">35mm &times; 45mm</div>
                </div>
            </div>
            <div class="photo-upload-details">
                <div class="icao-photo-specs">
                    <div class="icao-spec-title">
                        Passport Photograph Specifications (ICAO Standard)
                    </div>
                    <ul class="icao-spec-list">
                        <li>Recent front-facing portrait with full face visible, looking directly at the camera</li>
                        <li>Plain white or light grey background with even lighting (no shadows, borders, or patterns)</li>
                        <li>Taken within the last 6 months to reflect current likeness</li>
                        <li>Neutral facial expression with both eyes open and mouth closed</li>
                        <li>File format: JPG or PNG &bull; Maximum file size: 2MB (Dimensions: 35mm &times; 45mm)</li>
                    </ul>
                </div>

                <div class="photo-action-bar">
                    <label for="photo_file" class="btn btn-primary" style="cursor: pointer; display: inline-flex; align-items: center; gap: 8px; padding: 9px 20px; font-weight: 600;">
                        Upload Passport Photo
                    </label>
                    <input type="file" id="photo_file" name="photo_file" accept="image/jpeg,image/png,image/jpg" style="display: none;">
                    <span id="photoFileName" style="font-size: 0.86rem; color: #475569; font-weight: 500;"></span>
                </div>
            </div>
        </div>

        <div class="form-grid-2">
            <div class="form-group">
                <label for="passport_number" class="required">Passport Number</label>
                <input type="text" id="passport_number" name="passport_number" class="form-control"
                       value="<?php echo h($_POST['passport_number'] ?? ''); ?>" required placeholder="ENTER PASSPORT NUMBER" maxlength="12" pattern="[A-Za-z0-9]+" title="Passport Number should accept only characters and numbers only" style="text-transform: uppercase;" oninput="this.value = this.value.replace(/[^a-zA-Z0-9]/g, '').toUpperCase(); checkPassportPriceDisplay();">
            </div>

            <div class="form-group">
                <label for="passport_issue_date" class="required">Passport Issue Date</label>
                <input type="date" id="passport_issue_date" name="passport_issue_date" class="form-control"
                       max="<?php echo date('Y-m-d'); ?>"
                       value="<?php echo h($_POST['passport_issue_date'] ?? ''); ?>" required onchange="checkPassportPriceDisplay()">
            </div>
        </div>

        <div class="form-grid-2">
            <div class="form-group">
                <label for="passport_expiry" class="required">Passport Expiry Date</label>
                <input type="date" id="passport_expiry" name="passport_expiry" class="form-control"
                       value="<?php echo h($_POST['passport_expiry'] ?? ''); ?>" required onchange="checkPassportPriceDisplay()">
            </div>

            <div class="form-group" style="display: flex; flex-direction: column; justify-content: flex-end;">
                <div style="font-size: 0.84rem; color: #64748b; padding-bottom: 8px; line-height: 1.45;">
                    Passport must have at least 6 months validity remaining from date of application.
                </div>
            </div>
        </div>

        <!-- Residence Card Price Preview Display -->
        <div id="residenceCardPriceBox" style="display: none; padding: 8px 14px; margin-top: 1rem; margin-bottom: 0.5rem; background: #f8fafc; border: 1px solid #e2e8f0; border-radius: 6px; font-size: 0.88rem; color: #334155;">
            Residence Card Fee: <strong style="color: #113f1f; font-size: 0.95rem; font-weight: 700;">₦35,000.00</strong>
        </div>

        <div class="step-nav-footer">
            <button type="button" class="btn btn-outline" style="color: #64748b; border-color: #cbd5e1;" onclick="triggerSaveAndExit()">
                <i class="fas fa-save"></i> Save &amp; Exit
            </button>
            <button type="button" class="btn btn-primary" onclick="goToStep(2)">
                Next
            </button>
        </div>
    </div>

    <!-- ========================================================= -->
    <!-- SECTION 2: Personal Particulars & Contact Details          -->
    <!-- Strictly arranged in 2 columns (form-grid-2)              -->
    <!-- ========================================================= -->
    <div class="form-step-pane" id="pane-2">
        <h3 style="font-size: 1.05rem; color: var(--nis-green-dark, #113f1f); margin-bottom: 6px; font-weight: 700;">
            2. Personal Particulars &amp; Contact Details
        </h3>
        <p style="font-size: 0.86rem; color: #64748b; margin-bottom: 1.25rem;">
            Enter personal details exactly as recorded in your international passport.
        </p>

        <!-- Row 1: Names -->
        <div class="form-grid-2">
            <div class="form-group">
                <label for="surname" class="required">Surname</label>
                <input type="text" id="surname" name="surname" class="form-control"
                       pattern="[a-zA-Z\s'-]+"
                       oninput="this.value = this.value.replace(/[^a-zA-Z\s'-]/g, '')"
                       value="<?php echo h($_POST['surname'] ?? ''); ?>" required placeholder="ENTER SURNAME">
            </div>

            <div class="form-group">
                <label for="forenames" class="required">Given Names</label>
                <input type="text" id="forenames" name="forenames" class="form-control"
                       pattern="[a-zA-Z\s'-]+"
                       oninput="this.value = this.value.replace(/[^a-zA-Z\s'-]/g, '')"
                       value="<?php echo h($_POST['forenames'] ?? ''); ?>" required placeholder="ENTER GIVEN NAMES">
            </div>
        </div>

        <!-- Row 2: Nationality & Gender -->
        <div class="form-grid-2">
            <div class="form-group">
                <label for="nationality" class="required">Nationality</label>
                <select id="nationality" name="nationality" class="form-select" required>
                    <option value="" disabled <?php echo empty($_POST['nationality']) ? 'selected' : ''; ?>>SELECT NATIONALITY</option>
                    <?php foreach ($GLOBALS['NATIONALITIES'] as $code => $name): ?>
                        <option value="<?php echo h($code); ?>" <?php echo (($_POST['nationality'] ?? '') === $code) ? 'selected' : ''; ?>>
                            <?php echo h($name); ?>
                        </option>
                    <?php endforeach; ?>
                </select>
            </div>

            <div class="form-group">
                <label for="sex" class="required">Gender</label>
                <select id="sex" name="sex" class="form-select" required>
                    <option value="" disabled <?php echo empty($_POST['sex']) ? 'selected' : ''; ?>>SELECT GENDER</option>
                    <option value="MALE" <?php echo (($_POST['sex'] ?? '') === 'MALE') ? 'selected' : ''; ?>>MALE</option>
                    <option value="FEMALE" <?php echo (($_POST['sex'] ?? '') === 'FEMALE') ? 'selected' : ''; ?>>FEMALE</option>
                </select>
            </div>
        </div>

        <!-- Row 3: DOB & POB -->
        <div class="form-grid-2">
            <div class="form-group">
                <label for="date_of_birth" class="required">Date of Birth</label>
                <input type="date" id="date_of_birth" name="date_of_birth" class="form-control"
                       max="<?php echo date('Y-m-d', strtotime('-1 year')); ?>"
                       value="<?php echo h($_POST['date_of_birth'] ?? ''); ?>" required>
            </div>

            <div class="form-group">
                <label for="place_of_birth" class="required">Place of Birth</label>
                <input type="text" id="place_of_birth" name="place_of_birth" class="form-control"
                       pattern="[a-zA-Z\s',.-]+"
                       oninput="this.value = this.value.replace(/[^a-zA-Z\s',.-]/g, '')"
                       value="<?php echo h($_POST['place_of_birth'] ?? ''); ?>" required placeholder="ENTER PLACE OF BIRTH">
            </div>
        </div>

        <!-- Row 4: Profession & Height -->
        <div class="form-grid-2">
            <div class="form-group">
                <label for="profession" class="required">Profession / Occupation</label>
                <input type="text" id="profession" name="profession" class="form-control"
                       pattern="[a-zA-Z\s'/\-]+"
                       oninput="this.value = this.value.replace(/[^a-zA-Z\s'/\-]/g, '')"
                       value="<?php echo h($_POST['profession'] ?? ''); ?>" required placeholder="ENTER PROFESSION">
            </div>

            <div class="form-group">
                <label for="height">Height (cm)</label>
                <input type="text" id="height" name="height" class="form-control"
                       value="<?php echo h($_POST['height'] ?? ''); ?>" placeholder="e.g. 178">
            </div>
        </div>

        <!-- Row 5: Blood Group & Complexion -->
        <div class="form-grid-2">
            <div class="form-group">
                <label for="blood_group">Blood Group</label>
                <select id="blood_group" name="blood_group" class="form-select">
                    <option value="" disabled <?php echo empty($_POST['blood_group']) ? 'selected' : ''; ?>>SELECT BLOOD GROUP</option>
                    <?php foreach ($GLOBALS['BLOOD_GROUPS'] as $bg): ?>
                        <option value="<?php echo h($bg); ?>" <?php echo (($_POST['blood_group'] ?? '') === $bg) ? 'selected' : ''; ?>>
                            <?php echo h($bg); ?>
                        </option>
                    <?php endforeach; ?>
                </select>
            </div>

            <div class="form-group">
                <label for="complexion">Complexion</label>
                <select id="complexion" name="complexion" class="form-select">
                    <option value="" disabled <?php echo empty($_POST['complexion']) ? 'selected' : ''; ?>>SELECT COMPLEXION</option>
                    <?php foreach ($GLOBALS['COMPLEXIONS'] as $ck => $cv): ?>
                        <option value="<?php echo h($ck); ?>" <?php echo (($_POST['complexion'] ?? '') === $ck) ? 'selected' : ''; ?>>
                            <?php echo h($cv); ?>
                        </option>
                    <?php endforeach; ?>
                </select>
            </div>
        </div>

        <!-- Row 6: Colour of Eyes & Colour of Hair -->
        <div class="form-grid-2">
            <div class="form-group">
                <label for="eye_color">Colour of Eyes</label>
                <select id="eye_color" name="eye_color" class="form-select">
                    <option value="" disabled <?php echo empty($_POST['eye_color']) ? 'selected' : ''; ?>>SELECT COLOUR OF EYES</option>
                    <?php foreach ($GLOBALS['EYE_COLORS'] as $ek => $ev): ?>
                        <option value="<?php echo h($ek); ?>" <?php echo (($_POST['eye_color'] ?? '') === $ek) ? 'selected' : ''; ?>>
                            <?php echo h($ev); ?>
                        </option>
                    <?php endforeach; ?>
                </select>
            </div>

            <div class="form-group">
                <label for="hair_color">Colour of Hair</label>
                <select id="hair_color" name="hair_color" class="form-select">
                    <option value="" disabled <?php echo empty($_POST['hair_color']) ? 'selected' : ''; ?>>SELECT COLOUR OF HAIR</option>
                    <?php foreach ($GLOBALS['HAIR_COLORS'] as $hk => $hv): ?>
                        <option value="<?php echo h($hk); ?>" <?php echo (($_POST['hair_color'] ?? '') === $hk) ? 'selected' : ''; ?>>
                            <?php echo h($hv); ?>
                        </option>
                    <?php endforeach; ?>
                </select>
            </div>
        </div>

        <!-- Row 7: Distinguishing Features -->
        <div class="form-grid-2">
            <div class="form-group">
                <label for="distinguished_features">Distinguishing Features</label>
                <input type="text" id="distinguished_features" name="distinguished_features" class="form-control"
                       maxlength="150"
                       oninput="this.value = this.value.replace(/[^a-zA-Z0-9\s,.'\/\-]/g, '')"
                       value="<?php echo h($_POST['distinguished_features'] ?? ''); ?>" placeholder="e.g. Scar on left cheek, None">
            </div>
            <div class="form-group">
                <!-- Keep 2-column grid balance -->
            </div>
        </div>

        <!-- Row 8: Email & Phone -->
        <div class="form-grid-2">
            <div class="form-group">
                <label for="email" class="required">Email Address</label>
                <input type="email" id="email" name="email" class="form-control"
                       value="<?php echo h($_POST['email'] ?? ''); ?>" required placeholder="ENTER EMAIL ADDRESS">
            </div>

            <div class="form-group">
                <label for="phone" class="required">Phone Number</label>
                <input type="tel" id="phone" name="phone" class="form-control"
                       pattern="^\+?[0-9\s\-()]{7,20}$"
                       oninput="this.value = this.value.replace(/[^0-9+\s\-()]/g, '')"
                       value="<?php echo h($_POST['phone'] ?? ''); ?>" required placeholder="ENTER PHONE NUMBER">
            </div>
        </div>

        <!-- Row 9: Residential Address Breakdown (Street & State) -->
        <div class="form-grid-2">
            <div class="form-group">
                <label for="residential_address" class="required">Residential Address in Nigeria (Street Address)</label>
                <input type="text" id="residential_address" name="residential_address" class="form-control"
                       value="<?php echo h($_POST['residential_address'] ?? ''); ?>" required placeholder="HOUSE NO., STREET NAME, ESTATE / AREA"
                       oninput="syncResidentialAddress()">
            </div>

            <div class="form-group">
                <label for="residential_state" class="required">State</label>
                <select id="residential_state" name="residential_state" class="form-select" required
                        onchange="populateLgas(this.value, 'residential_lga'); syncResidentialAddress();">
                    <option value="" disabled <?php echo empty($_POST['residential_state']) ? 'selected' : ''; ?>>SELECT STATE</option>
                    <?php foreach ($nigerianStates as $st): ?>
                        <option value="<?php echo h($st); ?>" <?php echo (($_POST['residential_state'] ?? '') === $st) ? 'selected' : ''; ?>>
                            <?php echo h($st); ?>
                        </option>
                    <?php endforeach; ?>
                </select>
            </div>
        </div>

        <!-- Row 10: LGA & City / Town -->
        <div class="form-grid-2">
            <div class="form-group">
                <label for="residential_lga" class="required">Local Government Area (LGA)</label>
                <select id="residential_lga" name="residential_lga" class="form-select" required onchange="syncResidentialAddress();">
                    <option value="">-- SELECT LGA (SELECT STATE FIRST) --</option>
                </select>
            </div>

            <div class="form-group">
                <label for="residential_city" class="required">City / Town</label>
                <input type="text" id="residential_city" name="residential_city" class="form-control"
                       value="<?php echo h($_POST['residential_city'] ?? ''); ?>" required placeholder="e.g. IKEJA, WUSE II, LEKKI, VICTORIA ISLAND"
                       oninput="syncResidentialAddress()">
            </div>
        </div>

        <!-- Row 11: Change of Address (Optional) Breakdown (Street & State) -->
        <div class="form-grid-2">
            <div class="form-group">
                <label for="change_address">Change of Address (Street Address) <span style="font-size: 0.8rem; color: #64748b;">(Optional)</span></label>
                <input type="text" id="change_address" name="change_address" class="form-control"
                       value="<?php echo h($_POST['change_address'] ?? ''); ?>" placeholder="NEW HOUSE NO., STREET NAME, AREA"
                       oninput="syncChangeAddress()">
            </div>

            <div class="form-group">
                <label for="change_state">State <span style="font-size: 0.8rem; color: #64748b;">(Optional)</span></label>
                <select id="change_state" name="change_state" class="form-select"
                        onchange="populateLgas(this.value, 'change_lga'); syncChangeAddress();">
                    <option value="">SELECT STATE</option>
                    <?php foreach ($nigerianStates as $st): ?>
                        <option value="<?php echo h($st); ?>" <?php echo (($_POST['change_state'] ?? '') === $st) ? 'selected' : ''; ?>>
                            <?php echo h($st); ?>
                        </option>
                    <?php endforeach; ?>
                </select>
            </div>
        </div>

        <!-- Row 12: Change of Address (Optional) Breakdown (LGA & City / Town) -->
        <div class="form-grid-2">
            <div class="form-group">
                <label for="change_lga">Local Government Area (LGA) <span style="font-size: 0.8rem; color: #64748b;">(Optional)</span></label>
                <select id="change_lga" name="change_lga" class="form-select" onchange="syncChangeAddress();">
                    <option value="">-- SELECT LGA (SELECT STATE FIRST) --</option>
                </select>
            </div>

            <div class="form-group">
                <label for="change_city">City / Town <span style="font-size: 0.8rem; color: #64748b;">(Optional)</span></label>
                <input type="text" id="change_city" name="change_city" class="form-control"
                       value="<?php echo h($_POST['change_city'] ?? ''); ?>" placeholder="e.g. IKEJA, WUSE II, LEKKI"
                       oninput="syncChangeAddress()">
            </div>
        </div>
        <input type="hidden" id="change_of_address" name="change_of_address" value="<?php echo h($_POST['change_of_address'] ?? ''); ?>">
        <input type="hidden" id="domicile" name="domicile" value="<?php echo h($_POST['domicile'] ?? ''); ?>">

        <?php if (empty($isSuperAdminEntry)): ?>
            <?php if (empty($_SESSION['applicant_id'])): ?>
                <!-- Applicant Account Setup -->
                <div style="background: #f0fdf4; border: 1.5px solid #bbf7d0; border-radius: 8px; padding: 14px 18px; margin-top: 1.25rem;">
                    <div style="display: flex; align-items: center; gap: 8px; margin-bottom: 6px;">
                        <i class="fas fa-user-shield" style="color: #16a34a; font-size: 1.1rem;"></i>
                        <strong style="font-size: 0.92rem; color: #166534;">Applicant Profile Setup (Save Application &amp; Easy Renewals)</strong>
                    </div>
                    <p style="font-size: 0.82rem; color: #475569; margin: 0 0 10px; line-height: 1.4;">
                        Create a password for your applicant account so you can track this application, print slips, and log in anytime to apply for card renewals.
                    </p>
                    <div class="form-grid-2" style="margin-bottom: 0;">
                        <div class="form-group" style="margin-bottom: 0;">
                            <label for="account_password" style="font-size: 0.80rem; font-weight: 700; color: #1e293b;">Account Password</label>
                            <input type="password" id="account_password" name="account_password" class="form-control" placeholder="Create password (min 6 characters)">
                        </div>
                        <div class="form-group" style="margin-bottom: 0;">
                            <label for="account_password_confirm" style="font-size: 0.80rem; font-weight: 700; color: #1e293b;">Confirm Password</label>
                            <input type="password" id="account_password_confirm" name="account_password_confirm" class="form-control" placeholder="Repeat password">
                        </div>
                    </div>
                </div>
            <?php endif; ?>
        <?php endif; ?>

        <div class="step-nav-footer">
            <div style="display: flex; gap: 8px; align-items: center; flex-wrap: wrap;">
                <button type="button" class="btn btn-outline" onclick="goToStep(1)">Back</button>
                <button type="button" class="btn btn-outline" style="color: #64748b; border-color: #cbd5e1;" onclick="triggerSaveAndExit()"><i class="fas fa-save"></i> Save &amp; Exit</button>
            </div>
            <button type="button" class="btn btn-primary" onclick="goToStep(3)">Next</button>
        </div>
    </div>

    <!-- ========================================================= -->
    <!-- SECTION 3: Next of Kin Information                        -->
    <!-- Strictly arranged in 2 columns (form-grid-2)              -->
    <!-- ========================================================= -->
    <div class="form-step-pane" id="pane-3">
        <h3 style="font-size: 1.05rem; color: var(--nis-green-dark, #113f1f); margin-bottom: 6px; font-weight: 700;">
            3. Next of Kin Information
        </h3>
        <p style="font-size: 0.92rem; color: #64748b; margin-bottom: 1.35rem; line-height: 1.5;">
            Provide contact details for applicant's next of kin or emergency contact.
        </p>

        <div class="form-grid-2">
            <div class="form-group">
                <label for="emergency_contact_name" class="required">Next of Kin Full Name</label>
                <input type="text" id="emergency_contact_name" name="emergency_contact_name" class="form-control"
                       value="<?php echo h($_POST['emergency_contact_name'] ?? ''); ?>" required placeholder="ENTER NEXT OF KIN NAME">
            </div>

            <div class="form-group">
                <label for="emergency_contact_relation" class="required">Relationship to Applicant</label>
                <select id="emergency_contact_relation" name="emergency_contact_relation" class="form-select" required>
                    <option value="" disabled <?php echo empty($_POST['emergency_contact_relation']) ? 'selected' : ''; ?>>SELECT RELATIONSHIP</option>
                    <?php foreach ($GLOBALS['RELATIONSHIPS'] as $rk => $rv): ?>
                        <option value="<?php echo h($rk); ?>" <?php echo (($_POST['emergency_contact_relation'] ?? '') === $rk) ? 'selected' : ''; ?>>
                            <?php echo h($rv); ?>
                        </option>
                    <?php endforeach; ?>
                </select>
            </div>
        </div>

        <div class="form-grid-2">
            <div class="form-group">
                <label for="emergency_contact_phone" class="required">Next of Kin Phone Number</label>
                <input type="tel" id="emergency_contact_phone" name="emergency_contact_phone" class="form-control"
                       value="<?php echo h($_POST['emergency_contact_phone'] ?? ''); ?>" required placeholder="ENTER PHONE NUMBER">
            </div>

            <div class="form-group">
                <label for="emergency_contact_email">Next of Kin Email Address <span style="font-size: 0.8rem; color: #64748b;">(Optional)</span></label>
                <input type="email" id="emergency_contact_email" name="emergency_contact_email" class="form-control"
                       value="<?php echo h($_POST['emergency_contact_email'] ?? ''); ?>" placeholder="name@domain.com">
            </div>
        </div>

        <div class="form-grid-2">
            <div class="form-group">
                <label for="kin_address" class="required">Next of Kin Street Address</label>
                <input type="text" id="kin_address" name="kin_address" class="form-control"
                       value="<?php echo h($_POST['kin_address'] ?? ''); ?>" required placeholder="HOUSE NO., STREET NAME, AREA"
                       oninput="syncKinAddress()">
            </div>

            <div class="form-group">
                <label for="kin_state" class="required">Next of Kin State</label>
                <select id="kin_state" name="kin_state" class="form-select" required
                        onchange="populateLgas(this.value, 'kin_lga'); syncKinAddress();">
                    <option value="" disabled <?php echo empty($_POST['kin_state']) ? 'selected' : ''; ?>>SELECT STATE</option>
                    <?php foreach ($nigerianStates as $st): ?>
                        <option value="<?php echo h($st); ?>" <?php echo (($_POST['kin_state'] ?? '') === $st) ? 'selected' : ''; ?>>
                            <?php echo h($st); ?>
                        </option>
                    <?php endforeach; ?>
                </select>
            </div>
        </div>

        <div class="form-grid-2">
            <div class="form-group">
                <label for="kin_lga" class="required">Local Government Area (LGA)</label>
                <select id="kin_lga" name="kin_lga" class="form-select" required onchange="syncKinAddress();">
                    <option value="">-- SELECT LGA (SELECT STATE FIRST) --</option>
                </select>
            </div>

            <div class="form-group">
                <label for="kin_city" class="required">City / Town</label>
                <input type="text" id="kin_city" name="kin_city" class="form-control"
                       value="<?php echo h($_POST['kin_city'] ?? ''); ?>" required placeholder="CITY / TOWN"
                       oninput="syncKinAddress()">
            </div>
        </div>
        <input type="hidden" id="emergency_contact_address" name="emergency_contact_address" value="<?php echo h($_POST['emergency_contact_address'] ?? ''); ?>">

        <div class="step-nav-footer">
            <div style="display: flex; gap: 8px; align-items: center; flex-wrap: wrap;">
                <button type="button" class="btn btn-outline" onclick="goToStep(2)">Back</button>
                <button type="button" class="btn btn-outline" style="color: #64748b; border-color: #cbd5e1;" onclick="triggerSaveAndExit()"><i class="fas fa-save"></i> Save &amp; Exit</button>
            </div>
            <button type="button" class="btn btn-primary" onclick="goToStep(4)">Next</button>
        </div>
    </div>

    <!-- ========================================================= -->
    <!-- SECTION 4: Supporting Documents Upload                    -->
    <!-- Strictly arranged in 2 columns (form-grid-2)              -->
    <!-- ========================================================= -->
    <div class="form-step-pane" id="pane-4">
        <h3 style="font-size: 1.05rem; color: var(--nis-green-dark, #113f1f); margin-bottom: 6px; font-weight: 700;">
            4. Upload Supporting Documents
        </h3>
        <p style="font-size: 0.92rem; color: #64748b; margin-bottom: 1.35rem; line-height: 1.5;">
            Upload legible scanned copies of original documents in PDF, JPG, or PNG format (Max 5MB each). You can preview or remove any uploaded document.
        </p>

        <div class="form-grid-2">
            <!-- Doc 1: Passport Biodata Page -->
            <div class="form-group doc-card" id="doc_card_passport_copy" style="background: #f8fafc; border: 1px solid #e2e8f0; border-radius: 8px; padding: 14px; position: relative;">
                <div style="display: flex; justify-content: space-between; align-items: center; margin-bottom: 6px;">
                    <label for="doc_passport_copy" class="required" style="margin-bottom: 0; font-weight: 700; color: #1e293b;">International Passport Data Page</label>
                    <span id="doc_badge_passport_copy" style="display: none; background: #dcfce7; color: #166534; font-size: 0.72rem; font-weight: 700; padding: 2px 8px; border-radius: 4px;">
                        <i class="fas fa-check-circle"></i> Attached
                    </span>
                </div>
                <input type="file" id="doc_passport_copy" name="doc_passport_copy" class="form-control" accept=".pdf,.jpg,.jpeg,.png" style="font-size: 0.82rem; padding: 6px;" onchange="handleDocChange(this, 'passport_copy')">
                <span class="help-text" style="font-size: 0.74rem; color: #64748b; display: block; margin-top: 4px;">Clear scan of the passport biodata page</span>

                <div id="doc_actions_passport_copy" style="display: none; margin-top: 10px; padding-top: 8px; border-top: 1px dashed #cbd5e1; justify-content: space-between; align-items: center; flex-wrap: wrap; gap: 8px;">
                    <div style="font-size: 0.80rem; color: #1e293b; font-weight: 600; display: flex; align-items: center; gap: 6px; overflow: hidden; text-overflow: ellipsis; white-space: nowrap; max-width: 60%;">
                        <i class="fas fa-file-pdf" style="color: var(--nis-green, #1a5c2e);"></i>
                        <span id="doc_name_passport_copy">—</span>
                    </div>
                    <div style="display: flex; gap: 6px;">
                        <button type="button" class="btn btn-outline" style="font-size: 0.76rem; padding: 4px 10px; font-weight: 600;" onclick="previewDoc('doc_passport_copy', 'existing_doc_passport_copy', 'International Passport Data Page')">
                            <i class="fas fa-eye"></i> Preview
                        </button>
                        <button type="button" class="btn btn-outline" style="font-size: 0.76rem; padding: 4px 10px; font-weight: 600; color: #dc2626; border-color: #fca5a5;" onclick="deleteDoc('doc_passport_copy', 'existing_doc_passport_copy', 'passport_copy')">
                            <i class="fas fa-trash-alt"></i> Delete
                        </button>
                    </div>
                </div>
            </div>

            <!-- Doc 2: Residence Permit / Entry Visa -->
            <div class="form-group doc-card" id="doc_card_residence_visa" style="background: #f8fafc; border: 1px solid #e2e8f0; border-radius: 8px; padding: 14px; position: relative;">
                <div style="display: flex; justify-content: space-between; align-items: center; margin-bottom: 6px;">
                    <label for="doc_residence_visa" class="required" style="margin-bottom: 0; font-weight: 700; color: #1e293b;">Residence Permit / Entry Visa</label>
                    <span id="doc_badge_residence_visa" style="display: none; background: #dcfce7; color: #166534; font-size: 0.72rem; font-weight: 700; padding: 2px 8px; border-radius: 4px;">
                        <i class="fas fa-check-circle"></i> Attached
                    </span>
                </div>
                <input type="file" id="doc_residence_visa" name="doc_residence_visa" class="form-control" accept=".pdf,.jpg,.jpeg,.png" style="font-size: 0.82rem; padding: 6px;" onchange="handleDocChange(this, 'residence_visa')">
                <span class="help-text" style="font-size: 0.74rem; color: #64748b; display: block; margin-top: 4px;">Valid Nigerian entry visa or residency endorsement</span>

                <div id="doc_actions_residence_visa" style="display: none; margin-top: 10px; padding-top: 8px; border-top: 1px dashed #cbd5e1; justify-content: space-between; align-items: center; flex-wrap: wrap; gap: 8px;">
                    <div style="font-size: 0.80rem; color: #1e293b; font-weight: 600; display: flex; align-items: center; gap: 6px; overflow: hidden; text-overflow: ellipsis; white-space: nowrap; max-width: 60%;">
                        <i class="fas fa-file-pdf" style="color: var(--nis-green, #1a5c2e);"></i>
                        <span id="doc_name_residence_visa">—</span>
                    </div>
                    <div style="display: flex; gap: 6px;">
                        <button type="button" class="btn btn-outline" style="font-size: 0.76rem; padding: 4px 10px; font-weight: 600;" onclick="previewDoc('doc_residence_visa', 'existing_doc_residence_visa', 'Residence Permit / Entry Visa')">
                            <i class="fas fa-eye"></i> Preview
                        </button>
                        <button type="button" class="btn btn-outline" style="font-size: 0.76rem; padding: 4px 10px; font-weight: 600; color: #dc2626; border-color: #fca5a5;" onclick="deleteDoc('doc_residence_visa', 'existing_doc_residence_visa', 'residence_visa')">
                            <i class="fas fa-trash-alt"></i> Delete
                        </button>
                    </div>
                </div>
            </div>
        </div>

        <div class="form-grid-2">
            <!-- Doc 3: Quota Approval -->
            <div class="form-group doc-card" id="doc_card_quota_approval" style="background: #f8fafc; border: 1px solid #e2e8f0; border-radius: 8px; padding: 14px; position: relative;">
                <div style="display: flex; justify-content: space-between; align-items: center; margin-bottom: 6px;">
                    <label for="doc_quota_approval" class="required" style="margin-bottom: 0; font-weight: 700; color: #1e293b;">Expatriate Quota Approval / Letter</label>
                    <span id="doc_badge_quota_approval" style="display: none; background: #dcfce7; color: #166534; font-size: 0.72rem; font-weight: 700; padding: 2px 8px; border-radius: 4px;">
                        <i class="fas fa-check-circle"></i> Attached
                    </span>
                </div>
                <input type="file" id="doc_quota_approval" name="doc_quota_approval" class="form-control" accept=".pdf,.jpg,.jpeg,.png" style="font-size: 0.82rem; padding: 6px;" onchange="handleDocChange(this, 'quota_approval')">
                <span class="help-text" style="font-size: 0.74rem; color: #64748b; display: block; margin-top: 4px;">Ministry of Interior expatriate quota letter</span>

                <div id="doc_actions_quota_approval" style="display: none; margin-top: 10px; padding-top: 8px; border-top: 1px dashed #cbd5e1; justify-content: space-between; align-items: center; flex-wrap: wrap; gap: 8px;">
                    <div style="font-size: 0.80rem; color: #1e293b; font-weight: 600; display: flex; align-items: center; gap: 6px; overflow: hidden; text-overflow: ellipsis; white-space: nowrap; max-width: 60%;">
                        <i class="fas fa-file-pdf" style="color: var(--nis-green, #1a5c2e);"></i>
                        <span id="doc_name_quota_approval">—</span>
                    </div>
                    <div style="display: flex; gap: 6px;">
                        <button type="button" class="btn btn-outline" style="font-size: 0.76rem; padding: 4px 10px; font-weight: 600;" onclick="previewDoc('doc_quota_approval', 'existing_doc_quota_approval', 'Expatriate Quota Approval / Letter')">
                            <i class="fas fa-eye"></i> Preview
                        </button>
                        <button type="button" class="btn btn-outline" style="font-size: 0.76rem; padding: 4px 10px; font-weight: 600; color: #dc2626; border-color: #fca5a5;" onclick="deleteDoc('doc_quota_approval', 'existing_doc_quota_approval', 'quota_approval')">
                            <i class="fas fa-trash-alt"></i> Delete
                        </button>
                    </div>
                </div>
            </div>

            <!-- Doc 4: Proof of Domicile -->
            <div class="form-group doc-card" id="doc_card_domicile_proof" style="background: #f8fafc; border: 1px solid #e2e8f0; border-radius: 8px; padding: 14px; position: relative;">
                <div style="display: flex; justify-content: space-between; align-items: center; margin-bottom: 6px;">
                    <label for="doc_domicile_proof" style="margin-bottom: 0; font-weight: 700; color: #1e293b;">Proof of Domicile in Nigeria <span style="font-size: 0.76rem; color: #64748b; font-weight: 500;">(Optional)</span></label>
                    <span id="doc_badge_domicile_proof" style="display: none; background: #dcfce7; color: #166534; font-size: 0.72rem; font-weight: 700; padding: 2px 8px; border-radius: 4px;">
                        <i class="fas fa-check-circle"></i> Attached
                    </span>
                </div>
                <input type="file" id="doc_domicile_proof" name="doc_domicile_proof" class="form-control" accept=".pdf,.jpg,.jpeg,.png" style="font-size: 0.82rem; padding: 6px;" onchange="handleDocChange(this, 'domicile_proof')">
                <span class="help-text" style="font-size: 0.74rem; color: #64748b; display: block; margin-top: 4px;">Utility bill, tenancy agreement or company letter</span>

                <div id="doc_actions_domicile_proof" style="display: none; margin-top: 10px; padding-top: 8px; border-top: 1px dashed #cbd5e1; justify-content: space-between; align-items: center; flex-wrap: wrap; gap: 8px;">
                    <div style="font-size: 0.80rem; color: #1e293b; font-weight: 600; display: flex; align-items: center; gap: 6px; overflow: hidden; text-overflow: ellipsis; white-space: nowrap; max-width: 60%;">
                        <i class="fas fa-file-pdf" style="color: var(--nis-green, #1a5c2e);"></i>
                        <span id="doc_name_domicile_proof">—</span>
                    </div>
                    <div style="display: flex; gap: 6px;">
                        <button type="button" class="btn btn-outline" style="font-size: 0.76rem; padding: 4px 10px; font-weight: 600;" onclick="previewDoc('doc_domicile_proof', 'existing_doc_domicile_proof', 'Proof of Domicile in Nigeria')">
                            <i class="fas fa-eye"></i> Preview
                        </button>
                        <button type="button" class="btn btn-outline" style="font-size: 0.76rem; padding: 4px 10px; font-weight: 600; color: #dc2626; border-color: #fca5a5;" onclick="deleteDoc('doc_domicile_proof', 'existing_doc_domicile_proof', 'domicile_proof')">
                            <i class="fas fa-trash-alt"></i> Delete
                        </button>
                    </div>
                </div>
            </div>
        </div>

        <div class="form-grid-2">
            <!-- Doc 5: Additional Document -->
            <div class="form-group doc-card" id="doc_card_additional" style="background: #f8fafc; border: 1px solid #e2e8f0; border-radius: 8px; padding: 14px; position: relative;">
                <div style="display: flex; justify-content: space-between; align-items: center; margin-bottom: 6px;">
                    <label for="doc_additional" style="margin-bottom: 0; font-weight: 700; color: #1e293b;">Additional Supporting Document <span style="font-size: 0.76rem; color: #64748b; font-weight: 500;">(Optional)</span></label>
                    <span id="doc_badge_additional" style="display: none; background: #dcfce7; color: #166534; font-size: 0.72rem; font-weight: 700; padding: 2px 8px; border-radius: 4px;">
                        <i class="fas fa-check-circle"></i> Attached
                    </span>
                </div>
                <input type="file" id="doc_additional" name="doc_additional" class="form-control" accept=".pdf,.jpg,.jpeg,.png" style="font-size: 0.82rem; padding: 6px;" onchange="handleDocChange(this, 'additional')">
                <span class="help-text" style="font-size: 0.74rem; color: #64748b; display: block; margin-top: 4px;">Marriage cert, CERPAC card, or other relevant record</span>

                <div id="doc_actions_additional" style="display: none; margin-top: 10px; padding-top: 8px; border-top: 1px dashed #cbd5e1; justify-content: space-between; align-items: center; flex-wrap: wrap; gap: 8px;">
                    <div style="font-size: 0.80rem; color: #1e293b; font-weight: 600; display: flex; align-items: center; gap: 6px; overflow: hidden; text-overflow: ellipsis; white-space: nowrap; max-width: 60%;">
                        <i class="fas fa-file-pdf" style="color: var(--nis-green, #1a5c2e);"></i>
                        <span id="doc_name_additional">—</span>
                    </div>
                    <div style="display: flex; gap: 6px;">
                        <button type="button" class="btn btn-outline" style="font-size: 0.76rem; padding: 4px 10px; font-weight: 600;" onclick="previewDoc('doc_additional', 'existing_doc_additional', 'Additional Supporting Document')">
                            <i class="fas fa-eye"></i> Preview
                        </button>
                        <button type="button" class="btn btn-outline" style="font-size: 0.76rem; padding: 4px 10px; font-weight: 600; color: #dc2626; border-color: #fca5a5;" onclick="deleteDoc('doc_additional', 'existing_doc_additional', 'additional')">
                            <i class="fas fa-trash-alt"></i> Delete
                        </button>
                    </div>
                </div>
            </div>

            <!-- Verification Notice Box -->
            <div class="form-group" style="display: flex; flex-direction: column; justify-content: center; background: #ecfdf5; border: 1px solid #a7f3d0; border-radius: 8px; padding: 14px;">
                <div style="font-size: 0.82rem; color: #065f46; font-weight: 700; display: flex; align-items: center; gap: 6px;">
                    <i class="fas fa-shield-alt"></i> Document Verification Standards
                </div>
                <div style="font-size: 0.78rem; color: #047857; margin-top: 4px; line-height: 1.45;">
                    Uploaded documents are authenticated prior to card issuance. Ensure documents are clearly readable and genuine.
                </div>
            </div>
        </div>

        <!-- Mandatory Document Confirmation Checkbox (Unboxed) -->
        <div style="margin-top: 1.5rem; margin-bottom: 1.25rem; padding: 4px 0;">
            <label for="docs_genuine_confirm" style="display: flex; gap: 10px; align-items: center; cursor: pointer; margin: 0;">
                <input type="checkbox" id="docs_genuine_confirm" name="docs_genuine_confirm" value="1" style="width: 18px; height: 18px; accent-color: #113f1f; cursor: pointer;" <?php echo !empty($_POST['docs_genuine_confirm']) ? 'checked' : ''; ?>>
                <span style="font-size: 0.92rem; color: #1e293b; line-height: 1.5; font-weight: 600;">
                    I hereby confirm that all the documents I have provided are genuine &amp; properly readable.
                </span>
            </label>
        </div>

        <div class="step-nav-footer">
            <div style="display: flex; gap: 8px; align-items: center; flex-wrap: wrap;">
                <button type="button" class="btn btn-outline" onclick="goToStep(3)">Back</button>
                <button type="button" class="btn btn-outline" style="color: #64748b; border-color: #cbd5e1;" onclick="triggerSaveAndExit()"><i class="fas fa-save"></i> Save &amp; Exit</button>
            </div>
            <button type="button" class="btn btn-primary" onclick="goToStep(5)">Review Application</button>
        </div>
    </div>

    <!-- ========================================================= -->
    <!-- SECTION 5: Review Application                             -->
    <!-- Verified Applicant Dossier before Payment                 -->
    <!-- ========================================================= -->
    <div class="form-step-pane" id="pane-5">
        <div style="margin-bottom: 1.25rem;">
            <h3 style="font-size: 1.15rem; color: var(--nis-green-dark, #113f1f); margin: 0 0 4px; font-weight: 700;">
                5. Review Application
            </h3>
            <p style="font-size: 0.88rem; color: #64748b; margin: 0;">
                Please inspect your entered particulars and verify their accuracy before proceeding to fee payment.
            </p>
        </div>

        <!-- Applicant Identification Header Card -->
        <div class="review-dossier-banner">
            <div class="review-photo-thumb" id="rev_photo_wrap">
                <img id="rev_photo_img" src="" alt="Applicant Photo" style="display: none;">
                <i id="rev_photo_placeholder" class="fas fa-user" style="font-size: 2.2rem; color: #cbd5e1;"></i>
            </div>
            <div style="flex: 1;">
                <div id="rev_name" style="font-size: 1.25rem; font-weight: normal; color: #0f172a; margin-bottom: 6px; letter-spacing: -0.2px; text-transform: uppercase;">
                    —
                </div>
                <div style="display: flex; flex-wrap: wrap; gap: 14px; align-items: center; font-size: 0.88rem; color: #475569;">
                    <span>Passport: <strong id="rev_passport" style="color: #113f1f; font-family: monospace; font-size: 0.88rem;">—</strong></span>
                    <span style="color: #cbd5e1;">&bull;</span>
                    <span>Nationality: <strong id="rev_nat_gender" style="font-size: 0.88rem;">—</strong></span>
                    <span style="color: #cbd5e1;">&bull;</span>
                    <span>DOB: <strong id="rev_dob_pob" style="font-size: 0.88rem;">—</strong></span>
                    <span style="color: #cbd5e1;">&bull;</span>
                    <span>Amount to be Paid: <strong id="rev_amount_to_pay" style="color: #0b6623; font-weight: 800; font-size: 0.88rem;">&#8358;<?php echo number_format($feeAmount, 2); ?></strong></span>
                </div>
            </div>
        </div>

        <!-- Review Card 1: Passport & Identification Particulars -->
        <div class="review-section-box">
            <div class="review-section-title">
                1. Passport &amp; Identification Particulars
            </div>
            <div class="review-grid-3">
                <div>
                    <span class="review-item-label">Passport Number</span>
                    <div id="rev_passport2" class="review-item-value" style="font-family: monospace; font-size: 0.94rem; color: #113f1f; font-weight: 800;">—</div>
                </div>
                <div>
                    <span class="review-item-label">Passport Validity Period</span>
                    <div id="rev_passport_val" class="review-item-value">—</div>
                </div>
                <div>
                    <span class="review-item-label">Profession / Occupation</span>
                    <div id="rev_prof" class="review-item-value">—</div>
                </div>
                <div>
                    <span class="review-item-label">Designated Enrollment Center</span>
                    <div class="review-item-value" style="font-weight: 700; color: #113f1f;">NIS Headquarters, Abuja</div>
                </div>
                <div>
                    <span class="review-item-label">Issuance Fee (To be Paid)</span>
                    <div class="review-item-value" style="font-weight: 800; color: #0b6623;">&#8358;<?php echo number_format($feeAmount, 2); ?></div>
                </div>
            </div>
        </div>

        <!-- Review Card 2: Personal Particulars & Biometrics -->
        <div class="review-section-box">
            <div class="review-section-title">
                2. Personal Particulars &amp; Biometrics
            </div>
            <div class="review-grid-3">
                <div>
                    <span class="review-item-label">Full Legal Name</span>
                    <div id="rev_name2" class="review-item-value" style="font-weight: 700;">—</div>
                </div>
                <div>
                    <span class="review-item-label">Nationality &amp; Gender</span>
                    <div id="rev_nat_gender2" class="review-item-value">—</div>
                </div>
                <div>
                    <span class="review-item-label">Date &amp; Place of Birth</span>
                    <div id="rev_dob_pob2" class="review-item-value">—</div>
                </div>
                <div>
                    <span class="review-item-label">Blood Group</span>
                    <div id="rev_blood" class="review-item-value">—</div>
                </div>
                <div>
                    <span class="review-item-label">Height &amp; Complexion</span>
                    <div id="rev_height_comp" class="review-item-value">—</div>
                </div>
                <div>
                    <span class="review-item-label">Eye Color &amp; Hair Color</span>
                    <div id="rev_eye_hair" class="review-item-value">—</div>
                </div>
                <div style="grid-column: 1 / -1;">
                    <span class="review-item-label">Distinguishing Physical Marks / Features</span>
                    <div id="rev_features" class="review-item-value">—</div>
                </div>
            </div>
        </div>

        <!-- Review Card 3: Contact, Residence & Next of Kin -->
        <div class="review-section-box">
            <div class="review-section-title">
                3. Contact, Residence &amp; Next of Kin Particulars
            </div>
            <div class="review-grid-2">
                <div>
                    <span class="review-item-label">Telephone &amp; Email</span>
                    <div id="rev_contact" class="review-item-value">—</div>
                </div>
                <div>
                    <span class="review-item-label">Emergency Next of Kin (Name &amp; Relationship)</span>
                    <div id="rev_kin" class="review-item-value">—</div>
                </div>
                <div>
                    <span class="review-item-label">Residential Address in Nigeria</span>
                    <div id="rev_address" class="review-item-value">—</div>
                </div>
                <div>
                    <span class="review-item-label">Next of Kin Contact &amp; Residential Address</span>
                    <div id="rev_kin_addr" class="review-item-value">—</div>
                </div>
                <div style="grid-column: 1 / -1;">
                    <span class="review-item-label">Change of Address in Nigeria</span>
                    <div id="rev_change_addr" class="review-item-value">—</div>
                </div>
            </div>
        </div>

        <!-- Review Card 4: Attached Supporting Documents -->
        <div class="review-section-box">
            <div class="review-section-title">
                4. Attached Supporting Documents
            </div>
            <div style="display: grid; grid-template-columns: repeat(auto-fit, minmax(200px, 1fr)); gap: 12px 16px;">
                <div style="padding: 10px 14px; background: #f8fafc; border: 1px solid #e2e8f0; border-radius: 6px;">
                    <span class="review-item-label">Passport Bio-data Page</span>
                    <div id="rev_doc_passport" style="font-size: 0.85rem; font-weight: 700; margin-top: 4px;">
                        Checking
                    </div>
                </div>
                <div style="padding: 10px 14px; background: #f8fafc; border: 1px solid #e2e8f0; border-radius: 6px;">
                    <span class="review-item-label">Residence Permit / Entry Visa</span>
                    <div id="rev_doc_visa" style="font-size: 0.85rem; font-weight: 700; margin-top: 4px;">
                        Checking
                    </div>
                </div>
                <div style="padding: 10px 14px; background: #f8fafc; border: 1px solid #e2e8f0; border-radius: 6px;">
                    <span class="review-item-label">Quota Approval / Letter</span>
                    <div id="rev_doc_quota" style="font-size: 0.85rem; font-weight: 700; margin-top: 4px;">
                        Checking
                    </div>
                </div>
                <div style="padding: 10px 14px; background: #f8fafc; border: 1px solid #e2e8f0; border-radius: 6px;">
                    <span class="review-item-label">Proof of Domicile</span>
                    <div id="rev_doc_domicile" style="font-size: 0.85rem; font-weight: 600; margin-top: 4px;">
                        Optional
                    </div>
                </div>
                <div style="padding: 10px 14px; background: #f8fafc; border: 1px solid #e2e8f0; border-radius: 6px;">
                    <span class="review-item-label">Additional Document</span>
                    <div id="rev_doc_additional" style="font-size: 0.85rem; font-weight: 600; margin-top: 4px;">
                        Optional
                    </div>
                </div>
            </div>
        </div>

        <!-- Applicant Declaration Card -->
        <div class="review-lock-card" id="reviewLockCard" style="background: #ffffff; border: 1px solid #e2e8f0; border-radius: 8px; padding: 1.15rem 1.25rem; margin-top: 1.5rem;">
            <div style="display: flex; gap: 12px; align-items: flex-start;">
                <input type="checkbox" id="review_acknowledgement_check" name="review_acknowledgement_check" value="1" style="width: 20px; height: 20px; margin-top: 2px; accent-color: #113f1f; cursor: pointer; flex-shrink: 0;" onchange="handleReviewLockChange(this)" <?php echo !empty($_POST['review_acknowledgement_check']) ? 'checked' : ''; ?>>
                <div style="flex: 1;">
                    <label for="review_acknowledgement_check" style="font-size: 0.92rem; font-weight: 700; color: #0f172a; cursor: pointer; display: block; margin-bottom: 4px;">
                        Applicant Declaration
                    </label>
                    <p style="font-size: 0.86rem; color: #475569; margin: 0; line-height: 1.5;">
                        I hereby confirm that all information provided in this application is true, accurate, and complete. I understand that submitting false or misleading information attracts legal penalties under Nigerian law.
                    </p>
                </div>
            </div>
        </div>

        <div class="step-nav-footer" style="margin-top: 1.5rem; padding-top: 1.25rem; display: flex; justify-content: space-between; align-items: center; gap: 10px; flex-wrap: wrap;">
            <div style="display: flex; gap: 8px; align-items: center; flex-wrap: wrap;">
                <button type="button" class="btn btn-outline" id="backToDocsBtn" style="font-size: 0.88rem; padding: 8px 18px; font-weight: 600;" onclick="handleBackFromReview()">
                    Back
                </button>
                <button type="button" class="btn btn-outline" style="color: #64748b; border-color: #cbd5e1; font-size: 0.88rem; padding: 8px 16px; font-weight: 600;" onclick="triggerSaveAndExit()">
                    <i class="fas fa-save"></i> Save &amp; Exit
                </button>
            </div>
            <div style="display: flex; gap: 8px; align-items: center; flex-wrap: wrap;">
                <button type="button" class="btn btn-outline" id="confirmPayLaterBtn" style="color: #113f1f; border-color: #113f1f; font-weight: 600; font-size: 0.88rem; padding: 8px 18px;" onclick="handleConfirmPayLaterClick()">
                    Confirm &amp; Pay Later
                </button>
                <button type="button" class="btn btn-primary" id="confirmPayNowBtn" style="font-weight: 600; background: var(--nis-green, #1a5c2e); font-size: 0.88rem; padding: 8px 20px;" onclick="handleConfirmPayNowClick()">
                    Confirm &amp; Pay Now
                </button>
            </div>
        </div>
    </div>

    <!-- ========================================================= -->
    <!-- SECTION 6: Fee Payment (Paystack Secure Checkout)         -->
    <!-- Payment confirmation gates access to Appointment Booking  -->
    <!-- ========================================================= -->
    <div class="form-step-pane" id="pane-6">
        <h3 style="font-size: 1.05rem; color: var(--nis-green-dark, #113f1f); margin-bottom: 6px; font-weight: 700;">
            6. Fee Payment
        </h3>
        <p style="font-size: 0.92rem; color: #64748b; margin-bottom: 1.35rem; line-height: 1.5;">
            Official NIS Residence Card issuance fee must be paid successfully before you can proceed to book your biometric appointment.
        </p>

        <!-- Fee Assessment Card -->
        <div style="background: #ffffff; border: 1px solid #cbd5e1; border-radius: 8px; padding: 1.5rem; margin-bottom: 1.5rem; box-shadow: 0 2px 6px rgba(0,0,0,0.04);">
            <div style="display: flex; justify-content: space-between; align-items: center; border-bottom: 1px solid #f1f5f9; padding-bottom: 12px; margin-bottom: 16px; flex-wrap: wrap; gap: 8px;">
                <span style="font-size: 0.88rem; font-weight: 600; color: #64748b; text-transform: uppercase; letter-spacing: 0.4px;">Total Issuance Fee</span>
                <div style="display: flex; align-items: center; gap: 12px;">
                    <button type="button" class="btn btn-outline" onclick="printApplicationSlip()" style="color: #113f1f; border-color: #cbd5e1; font-weight: 600; font-size: 0.82rem; padding: 5px 12px; display: inline-flex; align-items: center; gap: 6px;">
                        <i class="fas fa-print"></i> Print Application Slip
                    </button>
                    <span style="font-size: 1.15rem; font-weight: 800; color: var(--nis-green-dark, #113f1f);">₦35,000.00</span>
                </div>
            </div>

            <!-- Billing Details -->
            <div style="display: grid; grid-template-columns: repeat(auto-fit, minmax(240px, 1fr)); gap: 12px; margin-bottom: 18px; background: #f8fafc; border: 1px solid #e2e8f0; border-radius: 6px; padding: 12px 16px;">
                <div>
                    <span style="font-size: 0.72rem; color: #64748b; font-weight: 700; text-transform: uppercase; display: block;">Applicant Full Name</span>
                    <strong id="pay_applicant_name" style="font-size: 0.88rem; color: #0f172a;">—</strong>
                </div>
                <div>
                    <span style="font-size: 0.72rem; color: #64748b; font-weight: 700; text-transform: uppercase; display: block;">Billing Email</span>
                    <strong id="pay_applicant_email" style="font-size: 0.88rem; color: #0f172a;">—</strong>
                </div>
            </div>

            <!-- Hidden Inputs for Form Submission -->
            <input type="hidden" name="payment_method" id="payment_method" value="PAYSTACK">
            <input type="hidden" name="payment_status" id="payment_status" value="<?php echo (!empty($isSuperAdminEntry)) ? 'PAID' : 'PENDING'; ?>">
            <input type="hidden" name="payment_reference" id="payment_reference" value="<?php echo (!empty($isSuperAdminEntry)) ? ('ADMIN-' . date('Ymd') . '-' . rand(10000, 99999)) : (h($_POST['payment_reference'] ?? '')); ?>">

            <?php if (!empty($isSuperAdminEntry)): ?>
                <!-- Admin Mode Notice -->
                <div style="background: #f0fdf4; border: 1px solid #bbf7d0; border-radius: 6px; padding: 12px 16px;">
                    <div style="font-size: 0.86rem; font-weight: 700; color: #166534;">
                        <i class="fas fa-user-shield"></i> Super Administrator Entry Mode
                    </div>
                    <div style="font-size: 0.82rem; color: #15803d; margin-top: 4px;">
                        Administrative bypass is active. Payment reference has been auto-generated for back-office records.
                    </div>
                </div>
            <?php else: ?>
                <!-- Public Applicant Paystack Section -->
                <!-- State A: Payment Pending (Unconfirmed) -->
                <div id="paystackPendingContainer" style="text-align: center; padding: 24px 16px; background: #fafafa; border: 1.5px dashed #cbd5e1; border-radius: 8px;">
                    <div style="margin-bottom: 16px;">
                        <div style="display: inline-flex; align-items: center; justify-content: center; width: 52px; height: 52px; background: #ecfdf5; border-radius: 50%; color: #059669; font-size: 1.4rem; margin-bottom: 8px;">
                            <i class="fas fa-credit-card"></i>
                        </div>
                        <h4 style="font-size: 1.05rem; font-weight: 700; color: #0f172a; margin: 0 0 4px;">Pay with Paystack</h4>
                        <p style="font-size: 0.85rem; color: #64748b; margin: 0 auto; max-width: 440px;">
                            Click below to launch secure Paystack checkout. You can pay with Card, Bank Transfer, USSD, or Apple Pay.
                        </p>
                    </div>

                    <button type="button" id="paystackTriggerBtn" class="btn" onclick="initiatePaystackPayment()" style="background: var(--nis-green, #113f1f); color: #ffffff; padding: 12px 32px; font-weight: 700; font-size: 1rem; border-radius: 6px; border: none; cursor: pointer; display: inline-flex; align-items: center; gap: 10px; box-shadow: 0 4px 12px rgba(17, 63, 31, 0.3); transition: transform 0.15s ease;">
                        <i class="fas fa-lock"></i> Pay ₦35,000.00 via Paystack
                    </button>

                    <div style="margin-top: 14px; font-size: 0.80rem; color: #94a3b8; display: flex; align-items: center; justify-content: center; gap: 8px;">
                        <i class="fas fa-shield-alt"></i> 256-Bit SSL Encrypted &bull; PCI-DSS Level 1 Certified
                    </div>
                </div>

                <!-- State B: Payment Confirmed (Unboxed view) -->
                <div id="paystackConfirmedContainer" style="display: none; padding: 12px 0 6px;">
                    <div style="display: flex; align-items: flex-start; gap: 14px;">
                        <div style="width: 36px; height: 36px; border-radius: 50%; background: #16a34a; color: #ffffff; display: flex; align-items: center; justify-content: center; font-size: 1.1rem; flex-shrink: 0;">
                            <i class="fas fa-check"></i>
                        </div>
                        <div style="flex: 1;">
                            <div style="display: flex; align-items: center; justify-content: space-between; flex-wrap: wrap; gap: 8px;">
                                <h4 style="font-size: 1.02rem; font-weight: 800; color: #15803d; margin: 0;">Payment Confirmed Successfully</h4>
                                <span style="color: #15803d; font-size: 0.76rem; font-weight: 700; letter-spacing: 0.3px;">
                                    PAID &bull; VERIFIED
                                </span>
                            </div>
                            <p style="font-size: 0.86rem; color: #334155; margin: 4px 0 12px; line-height: 1.45;">
                                Your transaction has been authorized and verified. You may now proceed to book your biometric appointment.
                            </p>
                            <div style="display: grid; grid-template-columns: repeat(auto-fit, minmax(180px, 1fr)); gap: 12px; padding-top: 10px; border-top: 1px solid #f1f5f9;">
                                <div>
                                    <span style="font-size: 0.70rem; color: #64748b; font-weight: 700; text-transform: uppercase; display: block;">Transaction Reference</span>
                                    <strong id="displayPayRef" style="font-family: monospace; font-size: 0.88rem; color: #0f172a;">—</strong>
                                </div>
                                <div>
                                    <span style="font-size: 0.70rem; color: #64748b; font-weight: 700; text-transform: uppercase; display: block;">Amount Paid</span>
                                    <strong style="font-size: 0.88rem; color: #166534;">₦35,000.00</strong>
                                </div>
                                <div>
                                    <span style="font-size: 0.70rem; color: #64748b; font-weight: 700; text-transform: uppercase; display: block;">Confirmation Time</span>
                                    <strong id="displayPayTime" style="font-size: 0.88rem; color: #0f172a;">—</strong>
                                </div>
                            </div>
                        </div>
                    </div>
                </div>
            <?php endif; ?>
        </div>

        <div class="step-nav-footer" style="display: flex; justify-content: space-between; align-items: center; gap: 10px; flex-wrap: wrap;">
            <div style="display: flex; gap: 8px; align-items: center; flex-wrap: wrap;">
                <button type="button" class="btn btn-outline" onclick="goToStep(5)">Back to Review</button>
                <button type="button" class="btn btn-outline" onclick="printApplicationSlip()" style="color: #113f1f; border-color: #113f1f; font-weight: 600; display: inline-flex; align-items: center; gap: 8px; padding: 8px 18px; font-size: 0.88rem;">
                    <i class="fas fa-print"></i> Print Application Slip
                </button>
            </div>
            <button type="button" id="proceedToAppointmentBtn" class="btn btn-primary" onclick="proceedFromPaymentStep()">
                Book Appointment &rarr;
            </button>
        </div>
    </div>

    <!-- ========================================================= -->
    <!-- SECTION 7: Appointment Booking                            -->
    <!-- Unlocked strictly upon confirmed fee payment              -->
    <!-- ========================================================= -->
    <div class="form-step-pane" id="pane-7">
        <h3 style="font-size: 1.05rem; color: var(--nis-green-dark, #113f1f); margin-bottom: 6px; font-weight: 700;">
            7. Appointment Booking
        </h3>
        <p style="font-size: 0.90rem; color: #64748b; margin-bottom: 1.25rem; line-height: 1.5;">
            Payment confirmed. Please select your preferred appointment date and time for biometric capture.
        </p>

        <!-- Biometrics Appointment Schedule -->
        <div style="margin-bottom: 1.5rem;">
            <div style="border-bottom: 1.5px solid #e2e8f0; padding-bottom: 8px; margin-bottom: 16px;">
                <h4 style="font-size: 0.96rem; font-weight: 700; color: #0f172a; margin: 0; display: flex; align-items: center; gap: 8px;">
                    <i class="fas fa-calendar-alt" style="color: var(--nis-green, #113f1f);"></i> Enrollment Center &amp; Schedule
                </h4>
            </div>

            <div style="display: grid; grid-template-columns: repeat(auto-fit, minmax(230px, 1fr)); gap: 16px; align-items: start;">
                <!-- Column 1: Enrollment Center (NIS HQ, Non-Editable) -->
                <div class="form-group" style="margin-bottom: 0;">
                    <label for="enrollment_center" style="font-weight: 700; color: #1e293b; font-size: 0.84rem; margin-bottom: 6px; display: block;">
                        Enrollment Center
                    </label>
                    <input type="text" id="enrollment_center" name="enrollment_center" value="NIS HQ" readonly style="background-color: #f1f5f9; font-weight: 700; color: #0f172a; border: 1px solid #cbd5e1; cursor: not-allowed; width: 100%; padding: 10px 12px; border-radius: 6px; font-size: 0.92rem; box-sizing: border-box;">
                    <small style="color: #64748b; font-size: 0.76rem; margin-top: 5px; display: block; line-height: 1.35;">
                        <i class="fas fa-map-marker-alt" style="color: var(--nis-green, #113f1f);"></i> H/Q Nigeria Immigration Service Headquarters, Sauka Abuja
                    </small>
                </div>

                <!-- Column 2: Appointment Date -->
                <div class="form-group" style="margin-bottom: 0;">
                    <label for="appointment_date" class="required" style="font-weight: 700; color: #1e293b; font-size: 0.84rem; margin-bottom: 6px; display: block;">
                        Appointment Date
                    </label>
                    <input type="date" id="appointment_date" name="appointment_date" class="form-control"
                            min="<?php echo date('Y-m-d', strtotime('+1 day')); ?>"
                            value="<?php echo h($_POST['appointment_date'] ?? date('Y-m-d', strtotime('+3 days'))); ?>" 
                            required style="font-weight: 600; color: #0f172a; border-color: #cbd5e1; padding: 10px 12px; font-size: 0.90rem; width: 100%; box-sizing: border-box;"
                            onchange="updateLiveSlotDisplay()">
                    <small style="color: #64748b; font-size: 0.76rem; margin-top: 5px; display: block;">
                        <i class="fas fa-info-circle" style="color: #0284c7;"></i> Select preferred attendance date
                    </small>
                </div>

                <!-- Column 3: Time Slot -->
                <div class="form-group" style="margin-bottom: 0;">
                    <label for="appointment_time" class="required" style="font-weight: 700; color: #1e293b; font-size: 0.84rem; margin-bottom: 6px; display: block;">
                        Time Slot
                    </label>
                    <select id="appointment_time" name="appointment_time" class="form-select" required style="font-weight: 600; color: #0f172a; border-color: #cbd5e1; padding: 10px 12px; font-size: 0.90rem; width: 100%; box-sizing: border-box;" onchange="updateLiveSlotDisplay()">
                        <option value="09:00 AM" selected>09:00 AM – 10:00 AM</option>
                        <option value="10:00 AM">10:00 AM – 11:00 AM</option>
                        <option value="11:00 AM">11:00 AM – 12:00 PM</option>
                        <option value="12:00 PM">12:00 PM – 01:00 PM</option>
                        <option value="02:00 PM">02:00 PM – 03:00 PM</option>
                        <option value="03:00 PM">03:00 PM – 04:00 PM</option>
                    </select>
                    <small style="color: #64748b; font-size: 0.76rem; margin-top: 5px; display: block;">
                        <i class="fas fa-clock" style="color: #0284c7;"></i> Enrollment attendance window
                    </small>
                </div>
            </div>

            <!-- Selected Slot Confirmation Note (Unboxed) -->
            <div style="font-size: 0.84rem; color: #1e293b; font-weight: 600; margin-top: 14px; padding-top: 10px; border-top: 1px dashed #e2e8f0; display: flex; align-items: center; gap: 8px;">
                <i class="fas fa-calendar-check" style="color: #16a34a; font-size: 1rem;"></i>
                <span>Confirmed Schedule: <strong id="live_slot_display" style="color: var(--nis-green-dark, #113f1f);">—</strong></span>
            </div>
        </div>

        <!-- Solemn Declaration (Unboxed) -->
        <div style="margin: 1.25rem 0 1.5rem; padding-top: 1rem; border-top: 1px solid #f1f5f9;">
            <label style="display: flex; align-items: flex-start; gap: 10px; font-weight: 600; font-size: 0.90rem; color: #1e293b; cursor: pointer; line-height: 1.5;">
                <input type="checkbox" id="confirmationCheck" name="confirmationCheck" required style="margin-top: 3px; width: 18px; height: 18px; accent-color: var(--nis-green); flex-shrink: 0;">
                <span>I solemnly declare that all particulars furnished in this online application are true, complete, and verified against the applicant's international passport.</span>
            </label>
        </div>

        <div class="step-nav-footer">
            <button type="button" class="btn btn-outline" onclick="goToStep(6)">Back to Payment</button>
            <button type="submit" class="btn btn-primary" style="padding: 11px 28px; font-weight: 700; font-size: 0.94rem;">
                Book Appointment
            </button>
        </div>
    </div>
</form>

<!-- Confirm & Pay Now Confirmation Modal -->
<div id="payNowConfirmModal" style="display: none; position: fixed; inset: 0; background: rgba(15, 23, 42, 0.75); z-index: 999999; align-items: center; justify-content: center; padding: 20px; backdrop-filter: blur(2px);">
    <div style="background: #ffffff; border-radius: 12px; max-width: 500px; width: 100%; box-shadow: 0 25px 50px -12px rgba(0, 0, 0, 0.3); border: 1px solid #cbd5e1; overflow: hidden;">
        <div style="padding: 16px 20px; background: #f0fdf4; border-bottom: 1px solid #dcfce7; display: flex; align-items: center; gap: 12px;">
            <div style="width: 38px; height: 38px; border-radius: 50%; background: #dcfce7; color: #166534; display: flex; align-items: center; justify-content: center; font-size: 1.15rem; flex-shrink: 0; border: 1px solid #bbf7d0;">
                <i class="fas fa-check-circle"></i>
            </div>
            <div>
                <h4 style="margin: 0; font-size: 1.05rem; font-weight: 700; color: #166534;">Confirm Application Submission</h4>
            </div>
        </div>
        <div style="padding: 22px 24px; color: #334155; font-size: 0.95rem; line-height: 1.6; background: #ffffff;">
            Note that by pressing OK, your application will be submitted and there will be no correction of data after payment. Are you sure you want to continue?
        </div>
        <div style="padding: 14px 20px; background: #f8fafc; border-top: 1px solid #e2e8f0; display: flex; justify-content: flex-end; gap: 10px; flex-wrap: wrap;">
            <button type="button" class="btn btn-outline" onclick="closePayNowConfirmModal()" style="font-weight: 600; padding: 8px 20px; border-radius: 6px;">
                Cancel
            </button>
            <button type="button" class="btn btn-primary" id="confirmPayNowOkBtn" onclick="proceedAfterPayNowConfirmed()" style="font-weight: 700; padding: 8px 24px; background: var(--nis-green, #1a5c2e); border-color: var(--nis-green, #1a5c2e); border-radius: 6px;">
                OK
            </button>
        </div>
    </div>
</div>



<!-- Paystack Interactive Checkout Modal (High-Visibility Buttons & Guaranteed Reliability) -->
<div id="paystackSimulatorModal" style="display: none; position: fixed; inset: 0; background: rgba(15, 23, 42, 0.75); z-index: 9999999; align-items: center; justify-content: center; padding: 16px; backdrop-filter: blur(3px);">
    <div style="background: #ffffff; border-radius: 12px; max-width: 440px; width: 100%; max-height: 92vh; box-shadow: 0 25px 50px -12px rgba(0, 0, 0, 0.35); overflow-y: auto; border: 1px solid #cbd5e1; font-family: -apple-system, BlinkMacSystemFont, 'Segoe UI', Roboto, sans-serif;">
        <!-- Paystack Header -->
        <div style="background: var(--nis-green, #113f1f); color: #ffffff; padding: 14px 20px; display: flex; justify-content: space-between; align-items: center;">
            <div style="display: flex; align-items: center; gap: 8px;">
                <span style="background: #165b2d; border: 1px solid #22c55e; color: #ffffff; font-size: 0.72rem; font-weight: 800; padding: 3px 7px; border-radius: 4px; letter-spacing: 0.5px;">PAYSTACK</span>
                <span style="font-size: 0.90rem; font-weight: 600; color: #ffffff;">Secured Checkout</span>
            </div>
            <button type="button" onclick="closePaystackSimulatorModal()" style="background: transparent; border: none; color: #cbd5e1; font-size: 1.4rem; cursor: pointer; line-height: 1; padding: 0 4px;" title="Cancel and Close">&times;</button>
        </div>

        <!-- Amount & Merchant Header -->
        <div style="padding: 12px 20px; background: #f8fafc; border-bottom: 1px solid #e2e8f0; display: flex; justify-content: space-between; align-items: center;">
            <div>
                <span style="font-size: 0.72rem; color: #64748b; text-transform: uppercase; font-weight: 600; display: block;">Billing Email</span>
                <strong id="pstk_modal_email" style="font-size: 0.85rem; color: #1e293b;">applicant@nis.gov.ng</strong>
            </div>
            <div style="text-align: right;">
                <span style="font-size: 0.72rem; color: #64748b; text-transform: uppercase; font-weight: 600; display: block;">Amount Due</span>
                <strong style="font-size: 1.20rem; color: var(--nis-green, #113f1f); font-weight: 800;">₦35,000.00</strong>
            </div>
        </div>

        <!-- Payment Method Tabs -->
        <div style="display: flex; border-bottom: 1px solid #e2e8f0; background: #ffffff;">
            <button type="button" id="pstkTabCard" onclick="switchPaystackTab('card')" style="flex: 1; text-align: center; padding: 11px 10px; font-size: 0.84rem; font-weight: 700; color: var(--nis-green, #113f1f); border: none; background: transparent; border-bottom: 2px solid var(--nis-green, #113f1f); cursor: pointer; display: flex; align-items: center; justify-content: center; gap: 6px;">
                <i class="fas fa-credit-card"></i> Pay with Card
            </button>
            <button type="button" id="pstkTabTransfer" onclick="switchPaystackTab('transfer')" style="flex: 1; text-align: center; padding: 11px 10px; font-size: 0.84rem; font-weight: 600; color: #64748b; border: none; background: transparent; border-bottom: 2px solid transparent; cursor: pointer; display: flex; align-items: center; justify-content: center; gap: 6px;">
                <i class="fas fa-university"></i> Bank Transfer
            </button>
        </div>

        <!-- Simulated Card Form -->
        <div id="pstkCardView" style="padding: 16px 20px 18px;">
            <div style="margin-bottom: 12px;">
                <label style="display: block; font-size: 0.78rem; font-weight: 600; color: #475569; margin-bottom: 4px;">Card Number</label>
                <input type="text" value="4084 0840 0840 0840" readonly style="width: 100%; padding: 9px 12px; font-size: 0.90rem; border: 1px solid #cbd5e1; border-radius: 6px; background: #f8fafc; color: #1e293b; font-family: monospace; font-weight: 600; box-sizing: border-box;">
            </div>

            <div style="display: grid; grid-template-columns: 1fr 1fr; gap: 12px; margin-bottom: 16px;">
                <div>
                    <label style="display: block; font-size: 0.78rem; font-weight: 600; color: #475569; margin-bottom: 4px;">Card Expiry</label>
                    <input type="text" value="12 / 28" readonly style="width: 100%; padding: 9px 12px; font-size: 0.90rem; border: 1px solid #cbd5e1; border-radius: 6px; background: #f8fafc; color: #1e293b; font-family: monospace; font-weight: 600; box-sizing: border-box;">
                </div>
                <div>
                    <label style="display: block; font-size: 0.78rem; font-weight: 600; color: #475569; margin-bottom: 4px;">CVV</label>
                    <input type="text" value="123" readonly style="width: 100%; padding: 9px 12px; font-size: 0.90rem; border: 1px solid #cbd5e1; border-radius: 6px; background: #f8fafc; color: #1e293b; font-family: monospace; font-weight: 600; box-sizing: border-box;">
                </div>
            </div>

            <!-- Highly Visible, High Contrast Buttons -->
            <button type="button" id="paystackSimulatorSubmitBtn" onclick="executeSimulatorPayment()" style="width: 100%; background: var(--nis-green, #113f1f); color: #ffffff; padding: 11px 18px; font-size: 0.95rem; font-weight: 700; border-radius: 6px; border: none; cursor: pointer; display: flex; align-items: center; justify-content: center; gap: 8px; box-shadow: 0 3px 10px rgba(17, 63, 31, 0.3);">
                <i class="fas fa-lock"></i> Pay ₦35,000.00
            </button>

            <button type="button" onclick="closePaystackSimulatorModal()" style="width: 100%; margin-top: 8px; background: #ffffff; color: #475569; padding: 8px 14px; font-size: 0.85rem; font-weight: 600; border-radius: 6px; border: 1px solid #cbd5e1; cursor: pointer; text-align: center;">
                Cancel Payment
            </button>
        </div>

        <!-- Simulated Bank Transfer Form -->
        <div id="pstkTransferView" style="display: none; padding: 16px 20px 18px;">
            <div style="text-align: center; margin-bottom: 9px; line-height: 1.3;">
                <div style="font-size: 0.74rem; color: #64748b;">Transfer exactly <strong style="font-size: 1.05rem; color: var(--nis-green, #113f1f); font-weight: 800; margin: 0 2px;">₦35,000.00</strong></div>
                <div style="font-size: 0.72rem; color: #64748b; margin-top: 1px;">to the dedicated virtual bank account below</div>
            </div>

            <div style="background: #f8fafc; border: 1.5px solid #e2e8f0; border-radius: 8px; padding: 9px 14px; margin-bottom: 9px;">
                <div style="display: flex; justify-content: space-between; align-items: center; margin-bottom: 6px; padding-bottom: 5px; border-bottom: 1px solid #f1f5f9;">
                    <span style="font-size: 0.76rem; color: #64748b;">Bank Name</span>
                    <strong style="font-size: 0.82rem; color: #0f172a;">Titan-Paystack / Wema</strong>
                </div>
                <div style="display: flex; justify-content: space-between; align-items: center; margin-bottom: 6px; padding-bottom: 5px; border-bottom: 1px solid #f1f5f9;">
                    <span style="font-size: 0.76rem; color: #64748b;">Account Number</span>
                    <div style="display: flex; align-items: center; gap: 6px;">
                        <strong style="font-size: 0.96rem; color: var(--nis-green, #113f1f); font-family: monospace; letter-spacing: 0.5px;">9928374102</strong>
                        <button type="button" onclick="copyTransferAcct()" id="btnCopyAcct" style="background: #e2e8f0; border: none; padding: 2px 7px; border-radius: 4px; font-size: 0.72rem; color: #334155; cursor: pointer;" title="Copy Account Number">
                            <i class="fas fa-copy"></i> Copy
                        </button>
                    </div>
                </div>
                <div style="display: flex; justify-content: space-between; align-items: center;">
                    <span style="font-size: 0.76rem; color: #64748b;">Beneficiary</span>
                    <strong style="font-size: 0.80rem; color: #0f172a;">NIS RCIS Fee / Paystack</strong>
                </div>
            </div>

            <div style="background: #fffbeb; border: 1px solid #fde68a; border-radius: 6px; padding: 6px 10px; margin-bottom: 11px; font-size: 0.74rem; color: #92400e; display: flex; align-items: center; gap: 6px;">
                <i class="fas fa-clock" style="color: #d97706; font-size: 0.80rem;"></i>
                <span>Virtual account expires in 30 minutes &bull; Confirms automatically</span>
            </div>

            <button type="button" id="paystackTransferSubmitBtn" onclick="executeSimulatorTransferPayment()" style="width: 100%; background: var(--nis-green, #113f1f); color: #ffffff; padding: 11px 16px; font-size: 0.95rem; font-weight: 700; border-radius: 6px; border: none; cursor: pointer; display: flex; align-items: center; justify-content: center; gap: 8px; box-shadow: 0 3px 10px rgba(17, 63, 31, 0.3);">
                <i class="fas fa-check-circle"></i> I Have Sent The Money
            </button>

            <button type="button" onclick="closePaystackSimulatorModal()" style="width: 100%; margin-top: 8px; background: #ffffff; color: #475569; padding: 8px 14px; font-size: 0.85rem; font-weight: 600; border-radius: 6px; border: 1px solid #cbd5e1; cursor: pointer; text-align: center;">
                Cancel Payment
            </button>
        </div>
    </div>
</div>

<!-- Document Preview Modal -->
<div id="docPreviewModal" style="display: none; position: fixed; inset: 0; background: rgba(15, 23, 42, 0.75); z-index: 99999; align-items: center; justify-content: center; padding: 20px; backdrop-filter: blur(2px);">
    <div style="background: #ffffff; border-radius: 12px; width: 100%; max-width: 820px; max-height: 90vh; display: flex; flex-direction: column; overflow: hidden; box-shadow: 0 25px 50px -12px rgba(0, 0, 0, 0.25); border: 1px solid #cbd5e1;">
        <div style="padding: 14px 20px; background: #0f172a; color: #ffffff; display: flex; justify-content: space-between; align-items: center;">
            <div style="font-weight: 700; font-size: 0.95rem; display: flex; align-items: center; gap: 8px;">
                <i class="fas fa-file-alt" style="color: #4ade80;"></i>
                <span id="docPreviewTitle">Document Preview</span>
            </div>
            <button type="button" onclick="closeDocPreviewModal()" style="background: none; border: none; color: #94a3b8; font-size: 1.5rem; cursor: pointer; padding: 0 6px; line-height: 1; transition: color 0.15s ease;" onmouseover="this.style.color='#fff'" onmouseout="this.style.color='#94a3b8'">
                &times;
            </button>
        </div>
        <div id="docPreviewBody" style="flex: 1; padding: 16px; overflow-y: auto; text-align: center; min-height: 380px; display: flex; align-items: center; justify-content: center; background: #f8fafc;">
            <!-- Document preview iframe or image dynamically loaded here -->
        </div>
        <div style="padding: 12px 20px; background: #f1f5f9; display: flex; justify-content: flex-end;">
            <button type="button" class="btn btn-outline" onclick="closeDocPreviewModal()" style="font-size: 0.85rem; padding: 6px 18px; font-weight: 600;">
                Close Preview
            </button>
        </div>
    </div>
</div>

<script>
// Wizard step navigation & validation
let currentStep = 1;
const totalSteps = 7;
let isPaymentConfirmed = <?php echo (!empty($isSuperAdminEntry) || (!empty($_POST['payment_reference']) && ($_POST['payment_status'] ?? '') === 'PAID')) ? 'true' : 'false'; ?>;
let isApplicationLocked = false;
let activeObjectUrl = null;

function triggerSaveAndExit() {
    const form = document.getElementById('applicationWizardForm');
    if (!form) return;
    
    // Sync address fields before saving
    syncResidentialAddress();
    syncKinAddress();
    syncChangeAddress();

    document.getElementById('action_save_and_exit').value = '1';
    document.getElementById('current_step_saved').value = currentStep;
    
    // Disable client-side HTML5 validation so partial drafts can be saved
    form.noValidate = true;
    form.submit();
}

function handleDocChange(input, key) {
    if (!input.files || input.files.length === 0) return;
    const file = input.files[0];
    
    // Enforce 5MB limit
    if (file.size > 5 * 1024 * 1024) {
        alert('The selected file (' + file.name + ') exceeds the 5MB size limit. Please choose a smaller file.');
        input.value = '';
        return;
    }

    const nameEl = document.getElementById('doc_name_' + key);
    if (nameEl) nameEl.textContent = file.name;

    const badgeEl = document.getElementById('doc_badge_' + key);
    if (badgeEl) badgeEl.style.display = 'inline-flex';

    const actionsEl = document.getElementById('doc_actions_' + key);
    if (actionsEl) actionsEl.style.display = 'flex';
}

function previewDoc(inputFieldId, existingFieldId, docTitle) {
    const fileInp = document.getElementById(inputFieldId);
    const existVal = document.getElementById(existingFieldId)?.value;
    const modal = document.getElementById('docPreviewModal');
    const titleEl = document.getElementById('docPreviewTitle');
    const bodyEl = document.getElementById('docPreviewBody');

    if (titleEl) titleEl.textContent = docTitle + ' — Preview';

    if (activeObjectUrl) {
        URL.revokeObjectURL(activeObjectUrl);
        activeObjectUrl = null;
    }

    let file = (fileInp && fileInp.files && fileInp.files[0]) ? fileInp.files[0] : null;

    if (file) {
        const fileType = file.type;
        activeObjectUrl = URL.createObjectURL(file);

        if (fileType.startsWith('image/')) {
            bodyEl.innerHTML = '<img src="' + activeObjectUrl + '" alt="Document Preview" style="max-width: 100%; max-height: 70vh; object-fit: contain; border-radius: 6px; box-shadow: 0 4px 12px rgba(0,0,0,0.1);">';
        } else if (fileType === 'application/pdf' || file.name.toLowerCase().endsWith('.pdf')) {
            bodyEl.innerHTML = '<iframe src="' + activeObjectUrl + '" style="width: 100%; height: 70vh; border: none; border-radius: 6px;" title="PDF Preview"></iframe>';
        } else {
            bodyEl.innerHTML = '<div style="padding: 2rem; color: #475569;"><i class="fas fa-file-alt" style="font-size: 3rem; color: #cbd5e1; margin-bottom: 1rem; display: block;"></i><p style="font-size: 0.95rem;">Preview format not supported directly in browser.</p><p style="font-size: 0.85rem; color: #64748b;">Selected File: <strong>' + file.name + '</strong> (' + Math.round(file.size / 1024) + ' KB)</p></div>';
        }
        modal.style.display = 'flex';
    } else if (existVal && existVal.trim() !== '') {
        const isPdf = existVal.toLowerCase().endsWith('.pdf');
        if (isPdf) {
            bodyEl.innerHTML = '<iframe src="' + existVal + '" style="width: 100%; height: 70vh; border: none; border-radius: 6px;" title="Document Preview"></iframe>';
        } else {
            bodyEl.innerHTML = '<img src="' + existVal + '" alt="Document Preview" style="max-width: 100%; max-height: 70vh; object-fit: contain; border-radius: 6px; box-shadow: 0 4px 12px rgba(0,0,0,0.1);">';
        }
        modal.style.display = 'flex';
    } else {
        alert('No document file has been selected or attached yet.');
    }
}

function closeDocPreviewModal() {
    const modal = document.getElementById('docPreviewModal');
    if (modal) modal.style.display = 'none';
    const bodyEl = document.getElementById('docPreviewBody');
    if (bodyEl) bodyEl.innerHTML = '';
    if (activeObjectUrl) {
        URL.revokeObjectURL(activeObjectUrl);
        activeObjectUrl = null;
    }
}

function deleteDoc(inputFieldId, existingFieldId, key) {
    if (!confirm('Are you sure you want to remove this attached document?')) return;
    
    const fileInp = document.getElementById(inputFieldId);
    if (fileInp) fileInp.value = '';

    const existInp = document.getElementById(existingFieldId);
    if (existInp) existInp.value = '';

    const badgeEl = document.getElementById('doc_badge_' + key);
    if (badgeEl) badgeEl.style.display = 'none';

    const actionsEl = document.getElementById('doc_actions_' + key);
    if (actionsEl) actionsEl.style.display = 'none';

    const nameEl = document.getElementById('doc_name_' + key);
    if (nameEl) nameEl.textContent = '—';
}

function handleConfirmPayNowClick() {
    const ackCheck = document.getElementById('review_acknowledgement_check');
    if (!ackCheck || !ackCheck.checked) {
        alert('Please review and check the declaration box confirming your details before proceeding.');
        ackCheck?.focus();
        return;
    }

    const modal = document.getElementById('payNowConfirmModal');
    if (modal) {
        modal.style.display = 'flex';
    } else {
        if (confirm('Note that by pressing OK, your application will be submitted and there will be no correction of data after payment. Are you sure you want to continue?')) {
            proceedAfterPayNowConfirmed();
        }
    }
}

function closePayNowConfirmModal() {
    const modal = document.getElementById('payNowConfirmModal');
    if (modal) modal.style.display = 'none';
}

function proceedAfterPayNowConfirmed() {
    closePayNowConfirmModal();
    const ackCheck = document.getElementById('review_acknowledgement_check');
    if (ackCheck) {
        ackCheck.checked = true;
        handleReviewLockChange(ackCheck, true);
    }
    goToStep(6);
}

function handleConfirmPayLaterClick() {
    const ackCheck = document.getElementById('review_acknowledgement_check');
    if (!ackCheck || !ackCheck.checked) {
        alert('Please review and check the declaration box confirming your details before proceeding.');
        ackCheck?.focus();
        return;
    }

    if (confirm('Your application will be confirmed and saved. You can complete your fee payment anytime from your dashboard to book your appointment. Do you wish to continue and save?')) {
        handleReviewLockChange(ackCheck, true);
        document.getElementById('current_step_saved').value = '6';
        triggerSaveAndExit();
    }
}

function handleReviewLockChange(chk, skipConfirm = false) {
    if (!chk) return;
    const lockCard = document.getElementById('reviewLockCard');
    const fb = document.getElementById('reviewLockFeedback');

    if (chk.checked) {
        isApplicationLocked = true;

        // Update Lock Card Style
        if (lockCard) {
            lockCard.style.background = '#f0fdf4';
            lockCard.style.borderColor = '#86efac';
        }

        // Update Feedback Message
        if (fb) {
            fb.style.color = '#166534';
            fb.innerHTML = 'Application particulars verified and confirmed. You may now proceed with fee payment.';
        }
    } else {
        isApplicationLocked = false;

        // Reset Lock Card Style
        if (lockCard) {
            lockCard.style.background = '#ffffff';
            lockCard.style.borderColor = '#e2e8f0';
        }

        // Reset Feedback Message
        if (fb) {
            fb.innerHTML = '';
        }
    }
}

function handleBackFromReview() {
    goToStep(4);
}

function validateStep(step) {
    if (step === 1) {
        const photoInp = document.getElementById('photo_file');
        const existingPhoto = document.getElementById('existing_photo_path')?.value;
        if ((!photoInp.files || photoInp.files.length === 0) && !existingPhoto) {
            alert('Please upload a passport photograph before proceeding.');
            return false;
        }

        const passNum = document.getElementById('passport_number');
        const pVal = passNum.value.trim().toUpperCase();
        if (!pVal) {
            alert('Passport Number is required.');
            passNum.focus();
            return false;
        }
        if (!/^[A-Z0-9]+$/.test(pVal)) {
            alert('Passport Number should accept only characters and numbers only.');
            passNum.focus();
            return false;
        }
        if (pVal.length < 6 || pVal.length > 12) {
            alert('Passport Number must be between 6 and 12 characters and numbers.');
            passNum.focus();
            return false;
        }

        const issueDate = document.getElementById('passport_issue_date');
        if (!issueDate.value) {
            alert('Passport Issue Date is required.');
            issueDate.focus();
            return false;
        }
        if (new Date(issueDate.value).getTime() > new Date().getTime()) {
            alert('Passport Issue Date cannot be in the future.');
            issueDate.focus();
            return false;
        }

        const expiryDate = document.getElementById('passport_expiry');
        if (!expiryDate.value) {
            alert('Passport Expiry Date is required.');
            expiryDate.focus();
            return false;
        }
        const expTs = new Date(expiryDate.value).getTime();
        const sixMonths = new Date();
        sixMonths.setMonth(sixMonths.getMonth() + 6);
        if (expTs < sixMonths.getTime()) {
            alert('Passport must have at least 6 months validity remaining from today.');
            expiryDate.focus();
            return false;
        }
    } else if (step === 2) {
        syncResidentialAddress();
        const requiredIds = ['surname', 'forenames', 'nationality', 'sex', 'date_of_birth', 'place_of_birth', 'profession', 'email', 'phone', 'residential_address', 'residential_state', 'residential_lga', 'residential_city'];
        for (let id of requiredIds) {
            const el = document.getElementById(id);
            if (!el || !el.value.trim()) {
                const label = el?.closest('.form-group')?.querySelector('label')?.innerText.replace('*', '').trim() || id;
                alert('Please provide mandatory field: ' + label);
                el?.focus();
                return false;
            }
        }

        // Character-only validations
        const surnameEl = document.getElementById('surname');
        if (surnameEl && !/^[a-zA-Z\s'-]+$/.test(surnameEl.value.trim())) {
            alert('Surname must only contain alphabetical characters (letters, spaces, hyphens).');
            surnameEl.focus();
            return false;
        }

        const forenamesEl = document.getElementById('forenames');
        if (forenamesEl && !/^[a-zA-Z\s'-]+$/.test(forenamesEl.value.trim())) {
            alert('Given Names must only contain alphabetical characters (letters, spaces, hyphens).');
            forenamesEl.focus();
            return false;
        }

        const pobEl = document.getElementById('place_of_birth');
        if (pobEl && !/^[a-zA-Z\s',.-]+$/.test(pobEl.value.trim())) {
            alert('Place of Birth must only contain alphabetical characters (letters, spaces, commas, hyphens).');
            pobEl.focus();
            return false;
        }

        const profEl = document.getElementById('profession');
        if (profEl && !/^[a-zA-Z\s'/\-]+$/.test(profEl.value.trim())) {
            alert('Profession / Occupation must only contain alphabetical characters (letters, spaces, hyphens, slashes).');
            profEl.focus();
            return false;
        }

        const featEl = document.getElementById('distinguished_features');
        if (featEl && featEl.value.trim()) {
            if (!/^[a-zA-Z0-9\s,.'\/\-]{2,150}$/.test(featEl.value.trim())) {
                alert('Distinguishing Features must contain valid descriptive text (letters, numbers, punctuation, or "None").');
                featEl.focus();
                return false;
            }
        }

        // Email format validation
        const emailEl = document.getElementById('email');
        if (emailEl && emailEl.value.trim()) {
            const emailRegex = /^[^\s@]+@[^\s@]+\.[^\s@]+$/;
            if (!emailRegex.test(emailEl.value.trim())) {
                alert('Please enter a valid email address (e.g. name@domain.com).');
                emailEl.focus();
                return false;
            }
        }

        // Phone format validation
        const phoneEl = document.getElementById('phone');
        if (phoneEl && phoneEl.value.trim()) {
            const phoneRegex = /^[+]?[0-9\s\-()]{7,20}$/;
            if (!phoneRegex.test(phoneEl.value.trim())) {
                alert('Please enter a valid phone number (minimum 7 digits).');
                phoneEl.focus();
                return false;
            }
        }

        const pwdEl = document.getElementById('account_password');
        const pwdConfEl = document.getElementById('account_password_confirm');
        if (pwdEl && pwdEl.value) {
            if (pwdEl.value.length < 6) {
                alert('Account password must be at least 6 characters long.');
                pwdEl.focus();
                return false;
            }
            if (pwdConfEl && pwdEl.value !== pwdConfEl.value) {
                alert('Account password and confirmation password do not match.');
                pwdConfEl.focus();
                return false;
            }
        }
    } else if (step === 3) {
        syncKinAddress();
        const kinIds = ['emergency_contact_name', 'emergency_contact_relation', 'emergency_contact_phone', 'kin_address', 'kin_state', 'kin_lga', 'kin_city'];
        for (let id of kinIds) {
            const el = document.getElementById(id);
            if (!el || !el.value.trim()) {
                const label = el?.closest('.form-group')?.querySelector('label')?.innerText.replace('*', '').trim() || id;
                alert('Please provide Next of Kin field: ' + label);
                el?.focus();
                return false;
            }
        }

        const kinPhoneEl = document.getElementById('emergency_contact_phone');
        if (kinPhoneEl && kinPhoneEl.value.trim()) {
            const phoneRegex = /^[+]?[0-9\s\-()]{7,20}$/;
            if (!phoneRegex.test(kinPhoneEl.value.trim())) {
                alert('Please enter a valid next of kin phone number (minimum 7 digits).');
                kinPhoneEl.focus();
                return false;
            }
        }
    } else if (step === 4) {
        const passDoc = document.getElementById('doc_passport_copy');
        const existPassDoc = document.getElementById('existing_doc_passport_copy')?.value;
        if ((!passDoc.files || passDoc.files.length === 0) && !existPassDoc) {
            alert('Supporting document required: International Passport Data Page.');
            passDoc.focus();
            return false;
        }

        const visaDoc = document.getElementById('doc_residence_visa');
        const existVisaDoc = document.getElementById('existing_doc_residence_visa')?.value;
        if ((!visaDoc.files || visaDoc.files.length === 0) && !existVisaDoc) {
            alert('Supporting document required: Residence Permit / Entry Visa.');
            visaDoc.focus();
            return false;
        }

        const quotaDoc = document.getElementById('doc_quota_approval');
        const existQuotaDoc = document.getElementById('existing_doc_quota_approval')?.value;
        if ((!quotaDoc.files || quotaDoc.files.length === 0) && !existQuotaDoc) {
            alert('Supporting document required: Expatriate Quota Approval / Employment Letter.');
            quotaDoc.focus();
            return false;
        }

        // Mandatory Document Genuineness Confirmation Checkbox
        const docConfirm = document.getElementById('docs_genuine_confirm');
        if (docConfirm && !docConfirm.checked) {
            alert('Please confirm that all uploaded documents are genuine & properly readable before proceeding.');
            docConfirm.focus();
            return false;
        }
    } else if (step === 5) {
        // Step 5 is Review Application: must be acknowledged and locked
        const ackCheck = document.getElementById('review_acknowledgement_check');
        if (!isApplicationLocked && (!ackCheck || !ackCheck.checked)) {
            alert('Please review and check the declaration box confirming your details before proceeding.');
            ackCheck?.focus();
            return false;
        }
    } else if (step === 6) {
        // Step 6 is Fee Payment: Only when payment is successful can you proceed to book appointment
        if (!isPaymentConfirmed) {
            alert('Please complete your fee payment successfully before proceeding to book your appointment.');
            return false;
        }
        return true;
    } else if (step === 7) {
        // Step 7 is Appointment Booking
        const appDate = document.getElementById('appointment_date');
        if (!appDate || !appDate.value) {
            alert('Please select an appointment date for physical enrollment.');
            appDate?.focus();
            return false;
        }
        const confirmCheck = document.getElementById('confirmationCheck');
        if (confirmCheck && !confirmCheck.checked) {
            alert('Please check the declaration box to verify applicant particulars before submitting.');
            confirmCheck.focus();
            return false;
        }
    }
    return true;
}

function proceedFromPaymentStep() {
    if (!isPaymentConfirmed) {
        alert('Please complete your fee payment successfully before proceeding to book your appointment.');
        return;
    }
    goToStep(7);
}

function goToStep(step) {
    if (step < 1 || step > totalSteps) return;

    // Prevent navigating back to steps 1-4 if application particulars are locked
    if (isApplicationLocked && step < 5) {
        alert('Your application particulars have been locked and confirmed. You cannot return to edit previous sections.');
        return;
    }

    if (step > currentStep) {
        for (let i = currentStep; i < step; i++) {
            if (!validateStep(i)) return;
        }
    }

    for (let i = 1; i <= totalSteps; i++) {
        const pane = document.getElementById('pane-' + i);
        const pill = document.getElementById('stepPill-' + i);
        if (pane) pane.classList.remove('active');
        if (pill) {
            pill.classList.remove('active');
            if (i < step) {
                pill.classList.add('completed');
            } else {
                pill.classList.remove('completed');
            }
        }
    }

    const activePane = document.getElementById('pane-' + step);
    const activePill = document.getElementById('stepPill-' + step);
    if (activePane) activePane.classList.add('active');
    if (activePill) activePill.classList.add('active');

    currentStep = step;

    if (step === 5) {
        populateReviewPane();
    } else if (step === 6) {
        populatePaymentPane();
    } else if (step === 7) {
        updateLiveSlotDisplay();
        const payRef = document.getElementById('payment_reference')?.value;
        const step7Ref = document.getElementById('step7PayRef');
        if (step7Ref && payRef) {
            step7Ref.textContent = 'REF: ' + payRef;
        }
    }

    window.scrollTo({ top: 120, behavior: 'smooth' });
}

function syncResidentialAddress() {
    const street = document.getElementById('residential_address')?.value.trim() || '';
    const city = document.getElementById('residential_city')?.value.trim() || '';
    const lga = document.getElementById('residential_lga')?.value.trim() || '';
    const state = document.getElementById('residential_state')?.value.trim() || '';
    const parts = [
        street,
        city,
        lga ? lga + ' LGA' : '',
        state ? state + ' STATE' : ''
    ].filter(Boolean);
    const full = parts.join(', ');
    const domEl = document.getElementById('domicile');
    if (domEl) {
        domEl.value = full;
    }
}

function syncKinAddress() {
    const street = document.getElementById('kin_address')?.value.trim() || '';
    const city = document.getElementById('kin_city')?.value.trim() || '';
    const lga = document.getElementById('kin_lga')?.value.trim() || '';
    const state = document.getElementById('kin_state')?.value.trim() || '';
    const parts = [
        street,
        city,
        lga ? lga + ' LGA' : '',
        state ? state + ' STATE' : ''
    ].filter(Boolean);
    const full = parts.join(', ');
    const kinEl = document.getElementById('emergency_contact_address');
    if (kinEl) {
        kinEl.value = full;
    }
}

function syncChangeAddress() {
    const street = document.getElementById('change_address')?.value.trim() || '';
    const city = document.getElementById('change_city')?.value.trim() || '';
    const lga = document.getElementById('change_lga')?.value.trim() || '';
    const state = document.getElementById('change_state')?.value.trim() || '';
    const parts = [
        street,
        city,
        lga ? lga + ' LGA' : '',
        state ? state + ' STATE' : ''
    ].filter(Boolean);
    const full = parts.join(', ');
    const changeEl = document.getElementById('change_of_address');
    if (changeEl) {
        changeEl.value = full;
    }
}

function checkPassportPriceDisplay() {
    const pNum = document.getElementById('passport_number')?.value.trim();
    const pIss = document.getElementById('passport_issue_date')?.value.trim();
    const pExp = document.getElementById('passport_expiry')?.value.trim();
    const priceBox = document.getElementById('residenceCardPriceBox');
    if (priceBox) {
        if (pNum && pIss && pExp) {
            priceBox.style.display = 'block';
        } else {
            priceBox.style.display = 'none';
        }
    }
}

document.addEventListener('DOMContentLoaded', function() {
    checkPassportPriceDisplay();
    const passNumEl = document.getElementById('passport_number');
    if (passNumEl) {
        const sanitizePass = function() {
            passNumEl.value = passNumEl.value.replace(/[^a-zA-Z0-9]/g, '').toUpperCase();
        };
        passNumEl.addEventListener('input', sanitizePass);
        passNumEl.addEventListener('paste', function() {
            setTimeout(sanitizePass, 10);
        });
        passNumEl.addEventListener('blur', sanitizePass);
    }
    ['passport_number', 'passport_issue_date', 'passport_expiry'].forEach(function(id) {
        const el = document.getElementById(id);
        if (el) {
            el.addEventListener('input', checkPassportPriceDisplay);
            el.addEventListener('change', checkPassportPriceDisplay);
            el.addEventListener('blur', checkPassportPriceDisplay);
        }
    });

    const resState = document.getElementById('residential_state');
    if (resState && resState.value) {
        populateLgas(resState.value, 'residential_lga', '<?php echo addslashes($_POST['residential_lga'] ?? ''); ?>');
    }
    const kinState = document.getElementById('kin_state');
    if (kinState && kinState.value) {
        populateLgas(kinState.value, 'kin_lga', '<?php echo addslashes($_POST['kin_lga'] ?? ''); ?>');
    }
    const changeState = document.getElementById('change_state');
    if (changeState && changeState.value) {
        populateLgas(changeState.value, 'change_lga', '<?php echo addslashes($_POST['change_lga'] ?? ''); ?>');
    }

    // Initialize existing documents badges and actions if present from saved draft
    const docKeys = ['passport_copy', 'residence_visa', 'quota_approval', 'domicile_proof', 'additional'];
    docKeys.forEach(function(key) {
        const existVal = document.getElementById('existing_doc_' + key)?.value;
        if (existVal && existVal.trim() !== '') {
            const badgeEl = document.getElementById('doc_badge_' + key);
            if (badgeEl) badgeEl.style.display = 'inline-flex';
            const actionsEl = document.getElementById('doc_actions_' + key);
            if (actionsEl) actionsEl.style.display = 'flex';
            const nameEl = document.getElementById('doc_name_' + key);
            if (nameEl) {
                const parts = existVal.split('/');
                nameEl.textContent = parts[parts.length - 1];
            }
        }
    });

    // Check if review acknowledgement was already checked previously
    const ackCheck = document.getElementById('review_acknowledgement_check');
    if (ackCheck && ackCheck.checked) {
        isApplicationLocked = true;
        handleReviewLockChange(ackCheck);
    }

    if (isPaymentConfirmed) {
        const existingRef = document.getElementById('payment_reference')?.value;
        if (existingRef) {
            confirmSuccessfulPayment(existingRef);
        }
    }

    <?php if (!empty($initialStep) && $initialStep > 1): ?>
        goToStep(<?php echo (int)$initialStep; ?>);
    <?php endif; ?>
});

function populateReviewPane() {
    const getVal = (id) => document.getElementById(id)?.value?.trim() || '—';
    const getSelectText = (id) => {
        const el = document.getElementById(id);
        return (el && el.selectedIndex >= 0 && el.value) ? el.options[el.selectedIndex].text : '—';
    };

    // Photo preview in dossier header
    const photoFileInp = document.getElementById('photo_file');
    const existingPhoto = document.getElementById('existing_photo_path')?.value;
    const revPhotoImg = document.getElementById('rev_photo_img');
    const revPhotoPh = document.getElementById('rev_photo_placeholder');
    if (photoFileInp && photoFileInp.files && photoFileInp.files[0]) {
        if (revPhotoImg) {
            revPhotoImg.src = URL.createObjectURL(photoFileInp.files[0]);
            revPhotoImg.style.display = 'block';
        }
        if (revPhotoPh) revPhotoPh.style.display = 'none';
    } else if (existingPhoto && existingPhoto.trim() !== '') {
        if (revPhotoImg) {
            revPhotoImg.src = existingPhoto;
            revPhotoImg.style.display = 'block';
        }
        if (revPhotoPh) revPhotoPh.style.display = 'none';
    }

    const pNum = getVal('passport_number');
    const pIssue = getVal('passport_issue_date');
    const pExp = getVal('passport_expiry');
    const sName = getVal('surname');
    const fNames = getVal('forenames');
    const fullName = (sName !== '—' && fNames !== '—') ? (sName + ', ' + fNames) : (sName !== '—' ? sName : fNames);
    const nat = getSelectText('nationality');
    const gen = getSelectText('sex');
    const dob = getVal('date_of_birth');
    const pob = getVal('place_of_birth');

    // Header Dossier Card
    if (document.getElementById('rev_name')) document.getElementById('rev_name').textContent = fullName;
    if (document.getElementById('rev_passport')) document.getElementById('rev_passport').textContent = pNum;
    if (document.getElementById('rev_nat_gender')) document.getElementById('rev_nat_gender').textContent = nat + ' • ' + gen;
    if (document.getElementById('rev_dob_pob')) document.getElementById('rev_dob_pob').textContent = dob + (pob !== '—' ? ' (' + pob + ')' : '');

    // Card 1: Passport & ID Particulars
    if (document.getElementById('rev_passport2')) document.getElementById('rev_passport2').textContent = pNum;
    if (document.getElementById('rev_passport_val')) document.getElementById('rev_passport_val').textContent = (pIssue !== '—' && pExp !== '—') ? (pIssue + ' to ' + pExp) : '—';
    if (document.getElementById('rev_prof')) document.getElementById('rev_prof').textContent = getVal('profession');

    // Card 2: Personal Particulars & Biometrics
    if (document.getElementById('rev_name2')) document.getElementById('rev_name2').textContent = fullName;
    if (document.getElementById('rev_nat_gender2')) document.getElementById('rev_nat_gender2').textContent = nat + ' (' + gen + ')';
    if (document.getElementById('rev_dob_pob2')) document.getElementById('rev_dob_pob2').textContent = dob + ' • ' + pob;
    if (document.getElementById('rev_blood')) document.getElementById('rev_blood').textContent = getSelectText('blood_group');
    
    const hVal = getVal('height');
    const compVal = getSelectText('complexion');
    if (document.getElementById('rev_height_comp')) {
        document.getElementById('rev_height_comp').textContent = (hVal !== '—' ? hVal + ' cm' : 'Unspecified') + (compVal !== '—' ? ' • ' + compVal : '');
    }

    const eyeVal = getSelectText('eye_color');
    const hairVal = getSelectText('hair_color');
    if (document.getElementById('rev_eye_hair')) {
        document.getElementById('rev_eye_hair').textContent = (eyeVal !== '—' ? 'Eyes: ' + eyeVal : 'Eyes: —') + ' • ' + (hairVal !== '—' ? 'Hair: ' + hairVal : 'Hair: —');
    }

    if (document.getElementById('rev_features')) document.getElementById('rev_features').textContent = getVal('distinguished_features') || 'None recorded';

    // Card 3: Contact, Residence & Next of Kin
    syncResidentialAddress();
    syncKinAddress();
    syncChangeAddress();
    if (document.getElementById('rev_contact')) document.getElementById('rev_contact').textContent = getVal('email') + ' • ' + getVal('phone');
    
    const kinName = getVal('emergency_contact_name');
    const kinRel = getSelectText('emergency_contact_relation');
    if (document.getElementById('rev_kin')) {
        document.getElementById('rev_kin').textContent = kinName + (kinRel !== '—' ? ' (' + kinRel + ')' : '');
    }

    if (document.getElementById('rev_address')) document.getElementById('rev_address').textContent = getVal('domicile');
    
    const kinPhone = getVal('emergency_contact_phone');
    const kinAddr = getVal('emergency_contact_address');
    if (document.getElementById('rev_kin_addr')) {
        document.getElementById('rev_kin_addr').textContent = (kinPhone !== '—' ? 'Tel: ' + kinPhone + ' • ' : '') + kinAddr;
    }

    if (document.getElementById('rev_change_addr')) document.getElementById('rev_change_addr').textContent = getVal('change_of_address') || 'None recorded';

    // Card 4: Attached Supporting Documents Status
    const checkDoc = (inpId, existId) => {
        const fileInp = document.getElementById(inpId);
        const existVal = document.getElementById(existId)?.value;
        return (fileInp && fileInp.files && fileInp.files.length > 0) || (existVal && existVal.trim() !== '');
    };
    const setDocRev = (revId, isAttached, isOptional, inpId, existId, docTitle) => {
        const el = document.getElementById(revId);
        if (el) {
            if (isAttached) {
                el.innerHTML = '<div style="display: flex; align-items: center; justify-content: space-between; gap: 6px;">' +
                    '<span style="color: #166534; font-weight: 700;">Attached</span>' +
                    '<button type="button" class="btn-doc-eye" onclick="previewDoc(\'' + inpId + '\', \'' + existId + '\', \'' + docTitle.replace(/'/g, "\\'") + '\')"><i class="fas fa-eye"></i> View</button>' +
                    '</div>';
            } else if (isOptional) {
                el.innerHTML = '<span style="color: #64748b; font-weight: 500;">Optional</span>';
            } else {
                el.innerHTML = '<span style="color: #dc2626; font-weight: 600;">Pending Upload</span>';
            }
        }
    };
    setDocRev('rev_doc_passport', checkDoc('doc_passport_copy', 'existing_doc_passport_copy'), false, 'doc_passport_copy', 'existing_doc_passport_copy', 'Passport Bio-data Page');
    setDocRev('rev_doc_visa', checkDoc('doc_residence_visa', 'existing_doc_residence_visa'), false, 'doc_residence_visa', 'existing_doc_residence_visa', 'Residence Permit / Entry Visa');
    setDocRev('rev_doc_quota', checkDoc('doc_quota_approval', 'existing_doc_quota_approval'), false, 'doc_quota_approval', 'existing_doc_quota_approval', 'Expatriate Quota Approval / Letter');
    setDocRev('rev_doc_domicile', checkDoc('doc_domicile_proof', 'existing_doc_domicile_proof'), true, 'doc_domicile_proof', 'existing_doc_domicile_proof', 'Proof of Domicile in Nigeria');
    setDocRev('rev_doc_additional', checkDoc('doc_additional', 'existing_doc_additional'), true, 'doc_additional', 'existing_doc_additional', 'Additional Supporting Document');
}

function populatePaymentPane() {
    const fn = document.getElementById('forenames')?.value.trim() || '';
    const sn = document.getElementById('surname')?.value.trim() || '';
    const fullName = (fn + ' ' + sn).trim() || 'NIS Applicant';
    const email = document.getElementById('email')?.value.trim() || 'applicant@nis.gov.ng';
    
    const nameEl = document.getElementById('pay_applicant_name');
    if (nameEl) nameEl.textContent = fullName;
    const emailEl = document.getElementById('pay_applicant_email');
    if (emailEl) emailEl.textContent = email;
}

function printApplicationSlip() {
    if (typeof populateReviewPane === 'function') {
        populateReviewPane();
    }
    if (typeof populatePaymentPane === 'function') {
        populatePaymentPane();
    }

    const pane5 = document.getElementById('pane-5');
    if (!pane5) {
        alert('Unable to load application particulars.');
        return;
    }

    const clone = pane5.cloneNode(true);

    // Remove the step heading ("5. Review Application") and subtitle
    const topHeading = clone.querySelector('div:first-child');
    if (topHeading && (topHeading.textContent.includes('5. Review') || topHeading.textContent.includes('Review Application'))) {
        topHeading.remove();
    }

    // Remove the step navigation footer
    const navFooter = clone.querySelector('.step-nav-footer');
    if (navFooter) navFooter.remove();

    // Strip interactive document preview eye buttons in Section 4
    clone.querySelectorAll('.btn-doc-eye').forEach(el => el.remove());

    // In Declaration, remove the checkbox input
    const ackCheck = clone.querySelector('#review_acknowledgement_check');
    if (ackCheck) ackCheck.remove();

    // Carry over photo preview accurately
    const origPhotoImg = document.getElementById('rev_photo_img');
    const clonePhotoImg = clone.querySelector('#rev_photo_img');
    const clonePhotoPh = clone.querySelector('#rev_photo_placeholder');
    if (origPhotoImg && origPhotoImg.src && origPhotoImg.style.display !== 'none') {
        if (clonePhotoImg) {
            clonePhotoImg.src = origPhotoImg.src;
            clonePhotoImg.style.display = 'block';
        }
        if (clonePhotoPh) clonePhotoPh.style.display = 'none';
    }

    const appRef = document.getElementById('payment_reference')?.value || ('DRAFT-' + Date.now().toString().slice(-8));
    const todayStr = new Date().toLocaleDateString('en-GB', { day: 'numeric', month: 'short', year: 'numeric' });
    const nowTimeStr = new Date().toLocaleTimeString('en-GB', { hour: '2-digit', minute: '2-digit' });

    const basePath = window.location.pathname.substring(0, window.location.pathname.lastIndexOf('/') + 1);
    const baseUrl = window.location.origin + basePath;

    const fullSlipHtml = `<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <base href="${baseUrl}">
    <title>Application Slip — Nigeria Immigration Service</title>
    <link rel="icon" type="image/png" href="assets/images/nis-crest-logo-transparent.png?v=3">
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css">
    <style>
        * { box-sizing: border-box; margin: 0; padding: 0; font-family: Inter, -apple-system, BlinkMacSystemFont, "Segoe UI", Roboto, sans-serif; }
        body { background-color: #f8fafc; color: #0f172a; padding: 24px 16px; font-size: 0.90rem; line-height: 1.5; }
        
        .slip-card {
            background: #ffffff;
            border-radius: 12px;
            box-shadow: 0 4px 16px rgba(0, 0, 0, 0.06);
            padding: 2.25rem 2.5rem;
            max-width: 820px;
            margin: 0 auto 2rem;
            position: relative;
        }

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
    <div class="screen-actions-bar">
        <button type="button" onclick="window.print()" class="btn-action-print">
            <i class="fas fa-print"></i> Print Slip
        </button>
        <button type="button" onclick="window.close()" class="btn-action-close">
            Close Window
        </button>
    </div>

    <div class="slip-card">
        <div class="slip-header-wrapper">
            <img src="assets/images/nis-crest-logo-transparent.png?v=3" alt="NIS Crest" class="slip-crest-logo" onerror="this.src='assets/images/nis-logo.png';">
            <div class="slip-agency-name">NIGERIA IMMIGRATION SERVICE</div>
            <div class="slip-doc-title">Residence Card Application Slip</div>
            <p class="slip-subtitle">
                Official Online Application Summary &bull; Particulars Vetted at Fee Assessment Stage
            </p>
        </div>

        <div class="slip-meta-strip">
            <div><span style="color: #64748b; font-weight: 600;">Application Stage:</span> <strong style="color: #0b6623;">PARTICULARS VETTED &bull; FEE PAYMENT STAGE</strong></div>
            <div><span style="color: #64748b; font-weight: 600;">Date:</span> <strong>${todayStr} ${nowTimeStr}</strong></div>
            <div><span style="color: #64748b; font-weight: 600;">Ref:</span> <strong style="font-family: monospace;">${appRef}</strong></div>
        </div>

        <div id="slipBodyContainer">
            ${clone.innerHTML}
        </div>

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

    <script>
        window.addEventListener('load', function() {
            setTimeout(function() {
                window.print();
            }, 350);
        });
    <\/script>
</body>
</html>`;

    let printWin = null;
    try {
        printWin = window.open('', '_blank', 'width=880,height=950,scrollbars=yes,resizable=yes');
    } catch (e) {
        printWin = null;
    }

    if (printWin && !printWin.closed && typeof printWin.closed !== 'undefined') {
        printWin.document.open();
        printWin.document.write(fullSlipHtml);
        printWin.document.close();
        printWin.focus();
    } else {
        let printFrame = document.getElementById('slipPrintFrame');
        if (!printFrame) {
            printFrame = document.createElement('iframe');
            printFrame.id = 'slipPrintFrame';
            printFrame.style.position = 'fixed';
            printFrame.style.right = '0';
            printFrame.style.bottom = '0';
            printFrame.style.width = '0';
            printFrame.style.height = '0';
            printFrame.style.border = '0';
            document.body.appendChild(printFrame);
        }
        const frameDoc = printFrame.contentWindow.document;
        frameDoc.open();
        frameDoc.write(fullSlipHtml);
        frameDoc.close();
        setTimeout(() => {
            printFrame.contentWindow.focus();
            printFrame.contentWindow.print();
        }, 400);
    }
}

function updateLiveSlotDisplay() {
    const dateInput = document.getElementById('appointment_date')?.value;
    const timeSelect = document.getElementById('appointment_time');
    const timeText = timeSelect && timeSelect.selectedIndex >= 0 ? timeSelect.options[timeSelect.selectedIndex].text : '';
    const display = document.getElementById('live_slot_display');
    if (display) {
        if (dateInput) {
            const d = new Date(dateInput + 'T00:00:00');
            const dateStr = !isNaN(d.getTime()) 
                ? d.toLocaleDateString('en-GB', { weekday: 'short', day: 'numeric', month: 'short', year: 'numeric' })
                : dateInput;
            display.textContent = dateStr + ' (' + timeText + ') — NIS HQ';
        } else {
            display.textContent = 'Please select a date';
        }
    }
}

function initiatePaystackPayment() {
    const email = document.getElementById('email')?.value.trim() || 'applicant@nis.gov.ng';
    const fn = document.getElementById('forenames')?.value.trim() || '';
    const sn = document.getElementById('surname')?.value.trim() || '';
    const fullName = (fn + ' ' + sn).trim() || 'NIS Applicant';
    const passport = document.getElementById('passport_number')?.value.trim() || '';
    const generatedRef = 'PSTK_' + Date.now() + '_' + Math.random().toString(36).substring(2, 8).toUpperCase();
    const paystackKey = '<?php echo defined("PAYSTACK_PUBLIC_KEY") ? PAYSTACK_PUBLIC_KEY : "pk_test_sample_nis_rcis_testkey"; ?>';

    // If key is dummy/placeholder, use our built-in Paystack checkout modal with high-visibility buttons
    const isPlaceholder = !paystackKey || paystackKey.includes('sample') || paystackKey.includes('testkey');

    if (isPlaceholder) {
        openPaystackSimulatorModal(email, fullName, passport);
        return;
    }

    if (typeof PaystackPop !== 'undefined') {
        try {

            const handler = PaystackPop.setup({
                key: paystackKey,
                email: email,
                amount: <?php echo defined("RESIDENCE_CARD_FEE_KOBO") ? RESIDENCE_CARD_FEE_KOBO : 3500000; ?>,
                currency: 'NGN',
                ref: generatedRef,
                metadata: {
                    custom_fields: [
                        { display_name: "Applicant Name", variable_name: "applicant_name", value: fullName },
                        { display_name: "Passport Number", variable_name: "passport_number", value: passport },
                        { display_name: "Service", variable_name: "service", value: "NIS Residence Card Issuance" }
                    ]
                },
                callback: function(response) {
                    closePaystackSafely();
                    confirmSuccessfulPayment(response.reference || generatedRef);
                },
                onClose: function() {
                    closePaystackSafely();
                }
            });
            handler.openIframe();
        } catch (e) {
            console.warn('Paystack inline modal error, falling back to simulator modal:', e);
            closePaystackSafely();
            openPaystackSimulatorModal(email, fullName, passport);
        }
    } else {
        openPaystackSimulatorModal(email, fullName, passport);
    }
}

function openPaystackSimulatorModal(email, fullName, passport) {
    const emailEl = document.getElementById('pstk_modal_email');
    if (emailEl) emailEl.textContent = email || 'applicant@nis.gov.ng';
    switchPaystackTab('card');
    const modal = document.getElementById('paystackSimulatorModal');
    if (modal) modal.style.display = 'flex';
}

function closePaystackSimulatorModal() {
    const modal = document.getElementById('paystackSimulatorModal');
    if (modal) modal.style.display = 'none';
}

function switchPaystackTab(tab) {
    const cardTab = document.getElementById('pstkTabCard');
    const transferTab = document.getElementById('pstkTabTransfer');
    const cardView = document.getElementById('pstkCardView');
    const transferView = document.getElementById('pstkTransferView');

    if (tab === 'transfer') {
        if (cardTab) {
            cardTab.style.color = '#64748b';
            cardTab.style.fontWeight = '600';
            cardTab.style.borderBottom = '2px solid transparent';
        }
        if (transferTab) {
            transferTab.style.color = 'var(--nis-green, #113f1f)';
            transferTab.style.fontWeight = '700';
            transferTab.style.borderBottom = '2px solid var(--nis-green, #113f1f)';
        }
        if (cardView) cardView.style.display = 'none';
        if (transferView) transferView.style.display = 'block';
    } else {
        if (cardTab) {
            cardTab.style.color = 'var(--nis-green, #113f1f)';
            cardTab.style.fontWeight = '700';
            cardTab.style.borderBottom = '2px solid var(--nis-green, #113f1f)';
        }
        if (transferTab) {
            transferTab.style.color = '#64748b';
            transferTab.style.fontWeight = '600';
            transferTab.style.borderBottom = '2px solid transparent';
        }
        if (cardView) cardView.style.display = 'block';
        if (transferView) transferView.style.display = 'none';
    }
}

function copyTransferAcct() {
    const acct = '9928374102';
    if (navigator.clipboard && navigator.clipboard.writeText) {
        navigator.clipboard.writeText(acct).then(function() {
            setCopiedFeedback();
        }).catch(function() {
            fallbackCopy(acct);
        });
    } else {
        fallbackCopy(acct);
    }
}

function fallbackCopy(text) {
    const input = document.createElement('input');
    input.value = text;
    document.body.appendChild(input);
    input.select();
    try {
        document.execCommand('copy');
        setCopiedFeedback();
    } catch (e) {}
    document.body.removeChild(input);
}

function setCopiedFeedback() {
    const btn = document.getElementById('btnCopyAcct');
    if (btn) {
        btn.innerHTML = '<i class="fas fa-check"></i> Copied!';
        btn.style.background = '#bbf7d0';
        btn.style.color = '#166534';
        setTimeout(function() {
            btn.innerHTML = '<i class="fas fa-copy"></i> Copy';
            btn.style.background = '#e2e8f0';
            btn.style.color = '#334155';
        }, 2000);
    }
}

function executeSimulatorPayment() {
    const btn = document.getElementById('paystackSimulatorSubmitBtn');
    if (btn) {
        btn.disabled = true;
        btn.innerHTML = '<i class="fas fa-spinner fa-spin"></i> Authorizing ₦35,000.00...';
    }
    setTimeout(function() {
        closePaystackSimulatorModal();
        if (btn) {
            btn.disabled = false;
            btn.innerHTML = '<i class="fas fa-lock"></i> Pay ₦35,000.00';
        }
        const ref = 'PSTK_' + Date.now() + '_' + Math.random().toString(36).substring(2, 8).toUpperCase();
        confirmSuccessfulPayment(ref);
    }, 1000);
}

function executeSimulatorTransferPayment() {
    const btn = document.getElementById('paystackTransferSubmitBtn');
    if (btn) {
        btn.disabled = true;
        btn.innerHTML = '<i class="fas fa-spinner fa-spin"></i> Verifying Transfer...';
    }
    setTimeout(function() {
        closePaystackSimulatorModal();
        if (btn) {
            btn.disabled = false;
            btn.innerHTML = '<i class="fas fa-check-circle"></i> I Have Sent The Money';
        }
        const ref = 'PSTK_TRF_' + Date.now() + '_' + Math.random().toString(36).substring(2, 8).toUpperCase();
        confirmSuccessfulPayment(ref);
    }, 1200);
}

function closePaystackSafely() {
    // Remove any Paystack popups/iframes if present
    const iframes = document.querySelectorAll('iframe[name^="paystack-"], iframe.paystack_pop, iframe.paystack_checkout');
    iframes.forEach(function(f) {
        try {
            if (f && f.parentNode) f.parentNode.removeChild(f);
        } catch (e) {}
    });
    document.body.style.overflow = '';
}

function simulatePaystackPayment(ref) {
    const email = document.getElementById('email')?.value.trim() || 'applicant@nis.gov.ng';
    openPaystackSimulatorModal(email);
}

function confirmSuccessfulPayment(reference) {
    isPaymentConfirmed = true;

    // Set hidden inputs
    const payRefInput = document.getElementById('payment_reference');
    if (payRefInput) payRefInput.value = reference;
    const payStatusInput = document.getElementById('payment_status');
    if (payStatusInput) payStatusInput.value = 'PAID';
    const payMethodInput = document.getElementById('payment_method');
    if (payMethodInput) payMethodInput.value = 'PAYSTACK';

    // Update UI in Step 6
    const pendingContainer = document.getElementById('paystackPendingContainer');
    if (pendingContainer) pendingContainer.style.display = 'none';

    const confirmedContainer = document.getElementById('paystackConfirmedContainer');
    if (confirmedContainer) confirmedContainer.style.display = 'block';

    const refDisplay = document.getElementById('displayPayRef');
    if (refDisplay) refDisplay.textContent = reference;

    const timeDisplay = document.getElementById('displayPayTime');
    if (timeDisplay) timeDisplay.textContent = new Date().toLocaleString('en-GB');

    // Update Step 7 confirmation pill
    const step7Ref = document.getElementById('step7PayRef');
    if (step7Ref) step7Ref.textContent = 'REF: ' + reference;

    // Unlock proceed to appointment button
    const proceedBtn = document.getElementById('proceedToAppointmentBtn');
    if (proceedBtn) {
        proceedBtn.removeAttribute('disabled');
        proceedBtn.style.opacity = '1';
        proceedBtn.style.cursor = 'pointer';
    }
}

// Photo preview handler
const photoInput = document.getElementById('photo_file');
const photoPreviewImg = document.getElementById('photoPreviewImg');
const photoPlaceholder = document.getElementById('photoPlaceholder');
const photoFileName = document.getElementById('photoFileName');

if (photoInput) {
    photoInput.addEventListener('change', function(e) {
        const file = e.target.files[0];
        if (file) {
            const reader = new FileReader();
            reader.onload = function(evt) {
                photoPreviewImg.src = evt.target.result;
                photoPreviewImg.style.display = 'block';
                photoPlaceholder.style.display = 'none';
                photoFileName.textContent = file.name;
            };
            reader.readAsDataURL(file);
        }
    });
}

// Intercept form submission to validate all steps
const wizardForm = document.getElementById('applicationWizardForm');
if (wizardForm) {
    wizardForm.addEventListener('submit', function(e) {
        // If save & exit action is triggered, skip client validation
        if (document.getElementById('action_save_and_exit')?.value === '1') {
            return true;
        }

        for (let s = 1; s <= totalSteps; s++) {
            if (!validateStep(s)) {
                e.preventDefault();
                goToStep(s);
                return false;
            }
        }
        return true;
    });
}
</script>
