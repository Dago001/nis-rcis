<?php
/**
 * NIS Residence Card Issuance System (NIS-RCIS)
 * Residence Card Renewal Engine & Extension Management
 */

require_once __DIR__ . '/includes/auth.php';
require_once __DIR__ . '/config/database.php';
require_once __DIR__ . '/config/constants.php';

requireRole([ROLE_SUPER_ADMIN, ROLE_APPROVING_OFFICER, ROLE_ISSUING_OFFICER]);

$db = Database::getConnection();
$user = currentUser();
$pageTitle = 'Card Renewal';
$selectedCard = null;
$cardId = (int)($_GET['id'] ?? 0);
$errors = [];

// If ID provided, fetch card
if ($cardId > 0) {
    $stmt = $db->prepare("SELECT * FROM residence_cards WHERE id = :id LIMIT 1");
    $stmt->execute([':id' => $cardId]);
    $selectedCard = $stmt->fetch();
}

// Handle Card Number search if submitted
if (isset($_GET['lookup_number']) && !empty(trim($_GET['lookup_number']))) {
    $lookup = strtoupper(trim($_GET['lookup_number']));
    $stmt = $db->prepare("SELECT * FROM residence_cards WHERE card_number = :cn OR passport_number = :pn LIMIT 1");
    $stmt->execute([':cn' => $lookup, ':pn' => $lookup]);
    $selectedCard = $stmt->fetch();
    if (!$selectedCard) {
        $errors[] = "No active residence card found matching number: {$lookup}";
    }
}

// Handle Renewal Submission
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['submit_renewal'])) {
    if (!validate_csrf()) {
        $errors[] = 'Security token validation failed. Please submit the form again.';
    } else {
        $targetCardId = (int)($_POST['card_id'] ?? 0);
        $fromDate = trim($_POST['from_date'] ?? '');
        $toDate = trim($_POST['to_date'] ?? '');
        $renewedAt = strtoupper(trim($_POST['renewed_at'] ?? $user['command']));
        $endorsingOfficer = strtoupper(trim(stripRank($_POST['endorsing_officer'] ?? $user['fullname'])));
        $officerServiceNo = strtoupper(trim($_POST['officer_service_no'] ?? $user['service_number']));
        $feePaid = 0.00;
        $receiptNo = '';
        $remarks = strtoupper(trim($_POST['remarks'] ?? 'RENEWAL GRANTED'));

        if ($targetCardId <= 0) $errors[] = 'Valid Residence Card must be selected for renewal.';
        if (empty($fromDate)) $errors[] = 'Renewal Start Date is required.';
        if (empty($toDate)) $errors[] = 'Renewal Expiration Date is required.';

        if (empty($errors)) {
            // Count existing renewals to determine renewal number
            $countStmt = $db->prepare("SELECT COUNT(*) FROM card_renewals WHERE card_id = :cid");
            $countStmt->execute([':cid' => $targetCardId]);
            $renewalNumber = (int)$countStmt->fetchColumn() + 1;

            // Insert Renewal Record
            $insStmt = $db->prepare("INSERT INTO card_renewals (
                card_id, renewal_number, from_date, to_date, renewed_at, endorsing_officer, officer_service_no, fee_paid, receipt_number, remarks
            ) VALUES (
                :card_id, :renewal_number, :from_date, :to_date, :renewed_at, :endorsing_officer, :officer_service_no, :fee_paid, :receipt_number, :remarks
            )");

            $insStmt->execute([
                ':card_id' => $targetCardId,
                ':renewal_number' => $renewalNumber,
                ':from_date' => $fromDate,
                ':to_date' => $toDate,
                ':renewed_at' => $renewedAt,
                ':endorsing_officer' => $endorsingOfficer,
                ':officer_service_no' => $officerServiceNo,
                ':fee_paid' => $feePaid,
                ':receipt_number' => $receiptNo,
                ':remarks' => $remarks
            ]);

            // Update Residence Card's expires_on and status
            $updCard = $db->prepare("UPDATE residence_cards SET expires_on = :exp, status = 'RENEWED', updated_at = CURRENT_TIMESTAMP WHERE id = :id");
            $updCard->execute([':exp' => $toDate, ':id' => $targetCardId]);

            logAudit('CARD_RENEWED', $targetCardId, "Granted Renewal #{$renewalNumber} until {$toDate} by {$user['fullname']}");

            setFlash('success', "Renewal #{$renewalNumber} granted successfully! Residence Card validity extended until <strong>" . formatNISDate($toDate) . "</strong>.");
            header("Location: card-details?id={$targetCardId}");
            exit();
        }
    }
}

require_once __DIR__ . '/includes/header.php';
require_once __DIR__ . '/includes/sidebar.php';
?>

<div class="page-title-box">
    <div>
        <h2>Card Renewal</h2>
    </div>
    <div>
        <a href="view-cards" class="btn btn-outline">
            <i class="fas fa-arrow-left"></i> Return to Directory
        </a>
    </div>
</div>

<?php if (!empty($errors)): ?>
    <div class="alert alert-danger">
        <i class="fas fa-exclamation-circle"></i>
        <div>
            <strong>Renewal Validation Notice:</strong>
            <ul style="margin-left: 20px; margin-top: 5px;">
                <?php foreach ($errors as $err): ?>
                    <li><?php echo h($err); ?></li>
                <?php endforeach; ?>
            </ul>
        </div>
    </div>
<?php endif; ?>

<!-- Quick Search Box if No Card is Selected -->
<?php if (!$selectedCard): ?>
    <div class="app-card" style="margin-bottom: 2rem;">
        <div class="card-header">
            <h3><i class="fas fa-search"></i> Select Residence Card for Extension</h3>
        </div>
        <div class="card-body">
            <form method="GET" action="renew-card" style="display: flex; gap: 12px; max-width: 600px;">
                <input type="text" name="lookup_number" class="form-control" 
                       placeholder="Enter Card Number (No.) or Passport Number" required autofocus>
                <button type="submit" class="btn btn-primary">
                    <i class="fas fa-search"></i> Find Card
                </button>
            </form>
        </div>
    </div>
<?php else: ?>
    <div class="app-card">
        <div class="card-header">
            <h3><i class="fas fa-id-card"></i> Target Residence File: No. <?php echo h($selectedCard['card_number']); ?></h3>
            <a href="renew-card" class="btn btn-outline btn-sm">Select Different Card</a>
        </div>
        <div class="card-body" style="background: #fafbfc; border-bottom: 1px solid var(--nis-border);">
            <div style="display: flex; gap: 20px; align-items: center;">
                <div style="width: 65px; height: 80px; border: 1px solid #cbd5e1; border-radius: 4px; overflow: hidden; background: #e2e8f0;">
                    <?php if (!empty($selectedCard['photo_path']) && file_exists(__DIR__ . '/' . $selectedCard['photo_path'])): ?>
                        <img src="<?php echo h($selectedCard['photo_path']); ?>" alt="Photo" style="width: 100%; height: 100%; object-fit: cover;">
                    <?php else: ?>
                        <i class="fas fa-user" style="font-size: 2rem; color: #94a3b8; display: flex; align-items: center; justify-content: center; height: 100%;"></i>
                    <?php endif; ?>
                </div>
                <div style="font-size: 0.92rem; line-height: 1.6;">
                    <div><strong>Full Name:</strong> <?php echo h($selectedCard['surname'] . ', ' . $selectedCard['forenames']); ?></div>
                    <div><strong>Nationality:</strong> <?php echo h($selectedCard['nationality']); ?> • <strong>Passport:</strong> <?php echo h($selectedCard['passport_number']); ?></div>
                    <div><strong>Current Expiration Date:</strong> <span style="color: #b91c1c; font-weight: bold;"><?php echo formatNISDate($selectedCard['expires_on']); ?></span></div>
                </div>
            </div>
        </div>

        <div class="card-body">
            <form method="POST" action="renew-card">
                <?php echo csrf_field(); ?>
                <input type="hidden" name="submit_renewal" value="1">
                <input type="hidden" name="card_id" value="<?php echo (int)$selectedCard['id']; ?>">

                <div class="form-row">
                    <div class="form-group">
                        <label for="from_date" class="required">Renewal Effective From</label>
                        <?php 
                        $calcFrom = date('Y-m-d', strtotime($selectedCard['expires_on'] . ' +1 day'));
                        ?>
                        <input type="date" id="from_date" name="from_date" class="form-control" 
                               value="<?php echo h($_POST['from_date'] ?? $calcFrom); ?>" required>
                    </div>

                    <div class="form-group">
                        <label for="to_date" class="required">Renewal Valid Until (New Expiration)</label>
                        <?php 
                        $calcTo = date('Y-m-d', strtotime($calcFrom . ' +2 years -1 day'));
                        ?>
                        <input type="date" id="to_date" name="to_date" class="form-control" 
                               value="<?php echo h($_POST['to_date'] ?? $calcTo); ?>" required>
                        <span class="help-text">Standard extension period: 24 Calendar Months.</span>
                    </div>
                </div>

                <div class="form-row">
                    <div class="form-group">
                        <label for="renewed_at" class="required">Renewed At (Enrollment Center)</label>
                        <select id="renewed_at" name="renewed_at" class="form-select" required>
                            <?php foreach ($GLOBALS['ENROLLMENT_CENTERS'] as $cmdKey => $cmdName): ?>
                                <option value="<?php echo h($cmdKey); ?>" <?php echo (($user['command']) === $cmdKey) ? 'selected' : ''; ?>>
                                    <?php echo h($cmdName); ?>
                                </option>
                            <?php endforeach; ?>
                        </select>
                    </div>

                    <div class="form-group">
                        <label for="endorsing_officer">Endorsing Officer</label>
                        <input type="text" id="endorsing_officer" name="endorsing_officer" class="form-control" 
                               value="<?php echo h(stripRank($user['fullname'])); ?>" readonly>
                    </div>
                </div>

                <div class="form-row">
                    <div class="form-group" style="grid-column: span 2;">
                        <label for="remarks">Renewal Endorsement Remarks</label>
                        <input type="text" id="remarks" name="remarks" class="form-control" 
                               value="<?php echo h($_POST['remarks'] ?? 'RESIDENCE EXTENSION GRANTED'); ?>">
                    </div>
                </div>

                <div style="margin-top: 1.5rem; padding-top: 1.5rem; border-top: 1px solid var(--nis-border); display: flex; justify-content: flex-end; gap: 12px;">
                    <a href="card-details?id=<?php echo (int)$selectedCard['id']; ?>" class="btn btn-outline">Cancel</a>
                    <button type="submit" class="btn btn-primary btn-lg">
                        <i class="fas fa-save"></i> Save Record
                    </button>
                </div>
            </form>
        </div>
    </div>
<?php endif; ?>

<?php
require_once __DIR__ . '/includes/footer.php';
?>
