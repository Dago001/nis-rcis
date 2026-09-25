<?php
/**
 * NIS Residence Card Issuance System (NIS-RCIS)
 * Residence Card Detailed File View & Dossier
 */

require_once __DIR__ . '/includes/auth.php';
require_once __DIR__ . '/config/database.php';
require_once __DIR__ . '/config/constants.php';

$db = Database::getConnection();
$user = currentUser();
$id = (int)($_GET['id'] ?? 0);

if ($id <= 0) {
    setFlash('danger', 'Invalid card record identifier requested.');
    header("Location: view-cards");
    exit();
}

$stmt = $db->prepare("SELECT * FROM residence_cards WHERE id = :id LIMIT 1");
$stmt->execute([':id' => $id]);
$card = $stmt->fetch();

if (!$card) {
    setFlash('danger', 'The requested residence card file was not found in the national registry.');
    header("Location: view-cards");
    exit();
}

// Fetch any renewal records
$renewStmt = $db->prepare("SELECT * FROM card_renewals WHERE card_id = :cid ORDER BY renewal_number ASC");
$renewStmt->execute([':cid' => $id]);
$renewals = $renewStmt->fetchAll();

$canManageSecurity = hasRole([ROLE_SUPER_ADMIN, ROLE_APPROVING_OFFICER]);
$canApprove = hasRole([ROLE_SUPER_ADMIN, ROLE_APPROVING_OFFICER]);

// 1. Handle Approval & Query Actions
if (($_SERVER['REQUEST_METHOD'] ?? '') === 'POST' && isset($_POST['action_approval'])) {
    if (!validate_csrf()) {
        setFlash('danger', 'Security validation failed.');
    } else {
        requireRole([ROLE_SUPER_ADMIN, ROLE_APPROVING_OFFICER]);
        $type = trim($_POST['approval_type'] ?? '');

        if ($type === 'APPROVE') {
            $upd = $db->prepare("UPDATE residence_cards SET 
                status = 'ISSUED', 
                approved_by = :uid, 
                approved_at = CURRENT_TIMESTAMP, 
                rejection_reason = NULL,
                updated_at = CURRENT_TIMESTAMP 
                WHERE id = :id");
            $upd->execute([':uid' => $user['id'], ':id' => $id]);

            logAudit("CARD_APPROVED", $id, "Card approved by {$user['fullname']} ({$user['service_number']})");

            // Dispatch Notifications
            require_once __DIR__ . '/includes/notifications.php';
            if (!empty($card['created_by']) && (int)$card['created_by'] !== (int)$user['id']) {
                createNotification(
                    (int)$card['created_by'],
                    NOTIF_APPROVAL_RECEIVED,
                    'Card Approved for Issuance',
                    "Residence Card No. {$card['card_number']} for {$card['surname']}, {$card['forenames']} was APPROVED by {$user['fullname']}. Ready for printing.",
                    "card-details?id={$id}",
                    $db
                );
            }
            createNotification(
                (int)$user['id'],
                NOTIF_APPROVAL_SENT,
                'Approval Decision Sent',
                "You approved Residence Card No. {$card['card_number']} ({$card['surname']}, {$card['forenames']}) for PVC issuance.",
                "card-details?id={$id}",
                $db
            );

            setFlash('success', "Residence Card No. <strong>{$card['card_number']}</strong> has been officially APPROVED and authorized for PVC printing.");
            header("Location: card-details?id={$id}");
            exit();

        } elseif ($type === 'QUERY') {
            $category = trim($_POST['query_category'] ?? 'General Query');
            $notes = trim($_POST['query_notes'] ?? '');
            $reason = $category . ($notes !== '' ? ': ' . $notes : '');

            $upd = $db->prepare("UPDATE residence_cards SET 
                status = 'QUERIED', 
                rejection_reason = :reason, 
                approved_by = :uid, 
                approved_at = CURRENT_TIMESTAMP, 
                updated_at = CURRENT_TIMESTAMP 
                WHERE id = :id");
            $upd->execute([':reason' => $reason, ':uid' => $user['id'], ':id' => $id]);

            logAudit("CARD_QUERIED", $id, "Card queried: {$reason}");

            // Dispatch Notifications
            require_once __DIR__ . '/includes/notifications.php';
            if (!empty($card['created_by']) && (int)$card['created_by'] !== (int)$user['id']) {
                createNotification(
                    (int)$card['created_by'],
                    NOTIF_CARD_QUERIED,
                    'Query Received on Card',
                    "Residence Card No. {$card['card_number']} was QUERIED by {$user['fullname']}: {$reason}. Please rectify particulars.",
                    "card-details?id={$id}",
                    $db
                );
            }
            createNotification(
                (int)$user['id'],
                NOTIF_APPROVAL_SENT,
                'Query Sent to Issuing Officer',
                "Query directive issued on Residence Card No. {$card['card_number']}: {$reason}",
                "card-details?id={$id}",
                $db
            );

            setFlash('warning', "Residence Card No. <strong>{$card['card_number']}</strong> has been QUERIED and returned to issuing officer.");
            header("Location: card-details?id={$id}");
            exit();
        }
    }
}

// 2. Handle Revocation
if (($_SERVER['REQUEST_METHOD'] ?? '') === 'POST' && isset($_POST['action_revoke'])) {
    if (!validate_csrf()) {
        setFlash('danger', 'Security validation failed.');
    } else {
        requireRole([ROLE_SUPER_ADMIN, ROLE_APPROVING_OFFICER]);
        $grounds = trim($_POST['revocation_grounds'] ?? 'Official Grounds');
        $notes = trim($_POST['revocation_notes'] ?? '');
        $fullReason = $grounds . ($notes !== '' ? ' — ' . $notes : '');

        $upd = $db->prepare("UPDATE residence_cards SET 
            status = 'REVOKED', 
            revocation_reason = :reason, 
            revoked_by = :uid, 
            revoked_at = CURRENT_TIMESTAMP, 
            updated_at = CURRENT_TIMESTAMP 
            WHERE id = :id");
        $upd->execute([':reason' => $fullReason, ':uid' => $user['id'], ':id' => $id]);

        logAudit("CARD_REVOKED", $id, "Card No. {$card['card_number']} REVOKED by {$user['fullname']}: {$fullReason}");
        setFlash('danger', "Residence Card No. <strong>{$card['card_number']}</strong> has been officially REVOKED pursuant to the Immigration Act.");
        header("Location: card-details?id={$id}");
        exit();
    }
}

// 3. Handle Reinstatement (SuperAdmin Only)
if (($_SERVER['REQUEST_METHOD'] ?? '') === 'POST' && isset($_POST['action_reinstate'])) {
    if (!validate_csrf()) {
        setFlash('danger', 'Security validation failed.');
    } else {
        requireRole(ROLE_SUPER_ADMIN);
        $upd = $db->prepare("UPDATE residence_cards SET 
            status = 'ISSUED', 
            revocation_reason = NULL, 
            revoked_by = NULL, 
            revoked_at = NULL, 
            updated_at = CURRENT_TIMESTAMP 
            WHERE id = :id");
        $upd->execute([':id' => $id]);

        logAudit("CARD_REINSTATED", $id, "Revoked card reinstated to active status by SuperAdmin {$user['fullname']}");
        setFlash('success', "Residence Card No. <strong>{$card['card_number']}</strong> has been reinstated to ACTIVE status.");
        header("Location: card-details?id={$id}");
        exit();
    }
}

// 4. Handle Watchlist Flagging
if (($_SERVER['REQUEST_METHOD'] ?? '') === 'POST' && isset($_POST['action_watchlist_toggle'])) {
    if (!validate_csrf()) {
        setFlash('danger', 'Security validation failed.');
    } else {
        requireRole([ROLE_SUPER_ADMIN, ROLE_APPROVING_OFFICER]);
        $targetState = (int)($_POST['watchlist_state'] ?? 0);

        if ($targetState === 1) {
            $reason = trim($_POST['watchlist_reason'] ?? 'National Security / Immigration Directive');
            $upd = $db->prepare("UPDATE residence_cards SET 
                is_watchlisted = 1, 
                watchlist_reason = :reason, 
                watchlisted_by = :uid, 
                watchlisted_at = CURRENT_TIMESTAMP, 
                updated_at = CURRENT_TIMESTAMP 
                WHERE id = :id");
            $upd->execute([':reason' => $reason, ':uid' => $user['id'], ':id' => $id]);

            logAudit("WATCHLIST_FLAGGED", $id, "Holder {$card['surname']} flagged on Watchlist: {$reason}");
            setFlash('danger', "Subject <strong>{$card['surname']}, {$card['forenames']}</strong> has been FLAGGED ON THE WATCHLIST.");
        } else {
            $upd = $db->prepare("UPDATE residence_cards SET 
                is_watchlisted = 0, 
                watchlist_reason = NULL, 
                watchlisted_by = NULL, 
                watchlisted_at = NULL, 
                updated_at = CURRENT_TIMESTAMP 
                WHERE id = :id");
            $upd->execute([':id' => $id]);

            logAudit("WATCHLIST_REMOVED", $id, "Watchlist stop order removed for Card No. {$card['card_number']} by {$user['fullname']}");
            setFlash('success', "Watchlist flag successfully removed for <strong>{$card['surname']}</strong>.");
        }
        header("Location: card-details?id={$id}");
        exit();
    }
}

// 5. Handle Ready for Collection Notification
if (($_SERVER['REQUEST_METHOD'] ?? '') === 'POST' && isset($_POST['action_ready_for_collection'])) {
    if (!validate_csrf()) {
        setFlash('danger', 'Security validation failed.');
    } else {
        requireRole([ROLE_SUPER_ADMIN, ROLE_APPROVING_OFFICER, ROLE_ISSUING_OFFICER]);
        $db->prepare("UPDATE residence_cards SET status = 'ISSUED' WHERE id = :id")->execute([':id' => $id]);
        $db->prepare("UPDATE applications SET status = 'READY_FOR_COLLECTION', card_ready_notified = 1, card_ready_notified_at = CURRENT_TIMESTAMP WHERE card_id = :cid OR UPPER(passport_number) = :pn")
           ->execute([':cid' => $id, ':pn' => strtoupper($card['passport_number'])]);

        logAudit("CARD_READY_NOTIFIED", $id, "Officer {$user['fullname']} marked Residence Card No. {$card['card_number']} as Ready for Collection. Notification dispatched.");
        setFlash('success', "Residence Card No. <strong>{$card['card_number']}</strong> marked as <strong>READY FOR COLLECTION</strong>. The applicant has been notified!");
        header("Location: card-details?id={$id}");
        exit();
    }
}

// Refresh card data after any updates
$stmt->execute([':id' => $id]);
$card = $stmt->fetch();

$pageTitle = "Residence Card No. " . $card['card_number'] . " — " . $card['surname'];
require_once __DIR__ . '/includes/header.php';
require_once __DIR__ . '/includes/sidebar.php';
?>

<div class="page-title-box">
    <div>
        <h2>
            <i class="fas fa-folder-open"></i> Residence File: 
            <span style="color: var(--nis-green-dark); font-family: monospace;">No. <?php echo h($card['card_number']); ?></span>
        </h2>
        <div class="subtitle"><?php echo h($card['surname'] . ', ' . $card['forenames']); ?> • <?php echo h($card['nationality']); ?></div>
    </div>
    <div style="display: flex; gap: 8px; flex-wrap: wrap;">
        <a href="view-cards" class="btn btn-outline">
            <i class="fas fa-arrow-left"></i> Directory
        </a>

        <?php if (in_array($card['status'], ['ISSUED', 'RENEWED', 'APPROVED'])): ?>
            <a href="print-idcard?id=<?php echo $id; ?>" class="btn btn-primary">
                <i class="fas fa-id-badge"></i> Print Card
            </a>
            <a href="renew-card?id=<?php echo $id; ?>" class="btn btn-outline">
                <i class="fas fa-sync-alt"></i> Grant Renewal
            </a>
            <form method="POST" action="card-details?id=<?php echo $id; ?>" style="display: inline;">
                <?php echo csrf_field(); ?>
                <input type="hidden" name="action_ready_for_collection" value="1">
                <button type="submit" class="btn btn-primary" style="background: var(--nis-green); border-color: var(--nis-green-dark);" onclick="return confirm('Notify applicant that their Residence Card is ready for collection?');">
                    <i class="fas fa-bell"></i> Notify Ready for Collection
                </button>
            </form>
        <?php endif; ?>

        <?php if ($canManageSecurity): ?>
            <?php if (empty($card['is_watchlisted'])): ?>
                <button type="button" class="btn btn-outline" style="color: #c0392b; border-color: #fca5a5;" onclick="openWatchlistModal(1)">
                    <i class="fas fa-flag"></i> Flag Watchlist
                </button>
            <?php else: ?>
                <button type="button" class="btn btn-outline" style="background: #fef2f2; color: #991b1b; border-color: #ef4444;" onclick="openWatchlistModal(0)">
                    <i class="fas fa-shield-alt"></i> Remove Watchlist
                </button>
            <?php endif; ?>

            <?php if ($card['status'] !== 'REVOKED'): ?>
                <button type="button" class="btn btn-danger" onclick="openRevokeModal()">
                    <i class="fas fa-ban"></i> Revoke Card
                </button>
            <?php endif; ?>
        <?php endif; ?>
    </div>
</div>

<!-- Alert Banners -->
<?php if (!empty($card['is_watchlisted'])): ?>
    <div class="alert-watchlist">
        <div style="display: flex; justify-content: space-between; align-items: flex-start; gap: 1rem; flex-wrap: wrap;">
            <div>
                <div style="font-size: 1.15rem; font-weight: 800; display: flex; align-items: center; gap: 8px;">
                    <i class="fas fa-exclamation-triangle"></i> WATCHLIST ALERT — ACTIVE IMMIGRATION STOP ORDER
                </div>
                <div style="font-size: 0.92rem; margin-top: 4px; font-weight: 600;">
                    Subject is flagged on the National Watchlist pursuant to Immigration Regulations.
                </div>
                <div style="font-size: 0.85rem; margin-top: 8px; background: rgba(220, 38, 38, 0.08); padding: 8px 12px; border-radius: 6px;">
                    <strong>Flagging Reason / Directive:</strong> <?php echo h($card['watchlist_reason'] ?: 'National Security Interdiction'); ?>
                    <?php if (!empty($card['watchlisted_at'])): ?>
                        <div style="font-size: 0.76rem; color: #7f1d1d; margin-top: 3px;">
                            Flagged on <?php echo formatNISDate($card['watchlisted_at']); ?>
                        </div>
                    <?php endif; ?>
                </div>
            </div>
            <?php if ($canManageSecurity): ?>
                <button type="button" class="btn btn-outline btn-sm" style="background: #ffffff; color: #991b1b; border-color: #ef4444;" onclick="openWatchlistModal(0)">
                    <i class="fas fa-shield-alt"></i> Clear Flag
                </button>
            <?php endif; ?>
        </div>
    </div>
<?php endif; ?>

<?php if ($card['status'] === 'REVOKED'): ?>
    <div class="alert alert-danger" style="border-left: 6px solid #dc2626; margin-bottom: 1.5rem;">
        <div style="display: flex; justify-content: space-between; align-items: center; width: 100%; flex-wrap: wrap; gap: 12px;">
            <div>
                <div style="font-size: 1.1rem; font-weight: 800; display: flex; align-items: center; gap: 8px;">
                    <i class="fas fa-ban"></i> RESIDENCE CARD OFFICIALLY REVOKED / CANCELLED
                </div>
                <div style="font-size: 0.88rem; margin-top: 4px;">
                    <strong>Revocation Grounds:</strong> <?php echo h($card['revocation_reason'] ?: 'Revocation Order Executed'); ?>
                    <?php if (!empty($card['revoked_at'])): ?>
                        • Executed on <?php echo formatNISDate($card['revoked_at']); ?>
                    <?php endif; ?>
                </div>
            </div>
            <?php if (hasRole(ROLE_SUPER_ADMIN)): ?>
                <form method="POST" action="card-details?id=<?php echo $id; ?>" onsubmit="return confirm('Reinstate this revoked residence card to active status?');">
                    <?php echo csrf_field(); ?>
                    <input type="hidden" name="action_reinstate" value="1">
                    <button type="submit" class="btn btn-outline btn-sm" style="background: #ffffff; color: var(--nis-green); border-color: var(--nis-green);">
                        <i class="fas fa-undo"></i> Reinstate to Active
                    </button>
                </form>
            <?php endif; ?>
        </div>
    </div>
<?php elseif ($card['status'] === 'PENDING_APPROVAL'): ?>
    <div class="alert-comptroller-review">
        <div style="display: flex; justify-content: space-between; align-items: center; width: 100%; flex-wrap: wrap; gap: 12px;">
            <div>
                <div style="font-size: 1.1rem; font-weight: 800; display: flex; align-items: center; gap: 8px;">
                    <i class="fas fa-hourglass-half"></i> AWAITING APPROVAL
                </div>
                <div style="font-size: 0.88rem; margin-top: 4px;">
                    Enrolled by <strong><?php echo h($card['issuing_officer_name']); ?></strong> (<?php echo h($card['issuing_officer_service_no']); ?>) on <?php echo formatNISDate($card['issued_on']); ?>. Card issuance and printing are suspended pending approval.
                </div>
            </div>
            <?php if ($canApprove): ?>
                <div style="display: flex; gap: 8px;">
                    <button type="button" class="btn btn-primary btn-sm" onclick="openApproveModal()">
                        <i class="fas fa-check-circle"></i> Approve &amp; Issue
                    </button>
                    <button type="button" class="btn btn-outline btn-sm" style="color: #c0392b; border-color: #fca5a5;" onclick="openQueryModal()">
                        <i class="fas fa-times-circle"></i> Query Application
                    </button>
                </div>
            <?php endif; ?>
        </div>
    </div>
<?php elseif ($card['status'] === 'QUERIED'): ?>
    <div style="background: #fef2f2; color: #991b1b; border: none; border-radius: 8px; padding: 16px 20px; margin-bottom: 1.5rem; display: flex; align-items: center; gap: 14px;">
        <div style="font-size: 1.6rem; color: #dc2626; flex-shrink: 0;">
            <i class="fas fa-exclamation-circle"></i>
        </div>
        <div>
            <div style="font-size: 1.05rem; font-weight: 800; color: #991b1b; letter-spacing: -0.2px;">
                APPLICATION QUERIED
            </div>
            <div style="font-size: 0.90rem; margin-top: 4px; color: #7f1d1d; line-height: 1.5;">
                <strong style="color: #991b1b;">Query Directive / Discrepancy:</strong> <?php echo h($card['rejection_reason']); ?>
            </div>
        </div>
    </div>
<?php endif; ?>

<!-- Main Institutional Dossier Layout -->
<div class="dossier-layout">
    <!-- Left Column: Biometric Identity & Executive Registry Summary -->
    <div class="app-card" style="box-shadow: var(--shadow-sm); overflow: hidden;">
        <div class="card-body" style="text-align: center; padding: 1.5rem 1.25rem;">
            <!-- Biometric Portrait Frame -->
            <div style="width: 140px; height: 175px; margin: 0 auto 16px; border-radius: 8px; overflow: hidden; border: 3px solid var(--nis-green); box-shadow: 0 4px 12px rgba(0,0,0,0.12); background: #f1f5f9; display: flex; align-items: center; justify-content: center; position: relative;">
                <?php if (!empty($card['photo_path']) && file_exists(__DIR__ . '/' . $card['photo_path'])): ?>
                    <img src="<?php echo h($card['photo_path']); ?>" alt="Applicant Biometric Portrait" style="width: 100%; height: 100%; object-fit: cover;">
                <?php else: ?>
                    <div style="color: #94a3b8; font-size: 0.8rem; text-align: center;">
                        <i class="fas fa-user" style="font-size: 3.5rem; display: block; margin-bottom: 6px; color: #cbd5e1;"></i>
                        No Photo
                    </div>
                <?php endif; ?>
            </div>

            <div style="font-size: 0.72rem; text-transform: uppercase; color: #64748b; font-weight: 700; letter-spacing: 0.5px;">Resident Legal Name</div>
            <h3 style="font-size: 1.18rem; font-weight: 800; color: #0f172a; margin: 4px 0 2px; line-height: 1.3;">
                <?php echo h($card['surname'] . ', ' . $card['forenames']); ?>
            </h3>
            <div style="font-size: 0.85rem; color: #64748b; margin-bottom: 12px; font-weight: 500;">
                <?php echo h($card['profession'] ?: 'Registered Resident'); ?>
            </div>

            <!-- Status Badges -->
            <?php 
            $st = strtoupper($card['status']);
            $badgeClass = 'badge-issued';
            if ($st === 'PENDING_APPROVAL') $badgeClass = 'badge-pending';
            elseif ($st === 'QUERIED') $badgeClass = 'badge-queried';
            elseif ($st === 'RENEWED') $badgeClass = 'badge-renewed';
            elseif ($st === 'EXPIRED' || $st === 'REVOKED') $badgeClass = 'badge-expired';
            ?>
            <div style="margin-bottom: 16px; display: flex; flex-direction: column; gap: 6px; align-items: center;">
                <span class="badge <?php echo $badgeClass; ?>" style="font-size: 0.82rem; padding: 6px 14px; font-weight: 800; letter-spacing: 0.5px;">
                    STATUS: <?php echo h($card['status']); ?>
                </span>
                <?php if (!empty($card['is_watchlisted'])): ?>
                    <span class="badge badge-watchlist" style="font-size: 0.75rem; padding: 5px 12px;">
                        <i class="fas fa-flag"></i> WATCHLIST
                    </span>
                <?php endif; ?>
            </div>

            <!-- Quick Document Particulars Card -->
            <div style="background: #f8fafc; border: 1px solid #e2e8f0; border-radius: 6px; padding: 12px 14px; font-size: 0.82rem; text-align: left; display: flex; flex-direction: column; gap: 9px;">
                <div style="display: flex; justify-content: space-between; align-items: center;">
                    <span style="color: #64748b; font-weight: 600;">Passport:</span>
                    <strong style="color: var(--nis-navy); font-family: monospace; font-size: 0.88rem;"><?php echo h($card['passport_number']); ?></strong>
                </div>
                <div style="display: flex; justify-content: space-between; align-items: center;">
                    <span style="color: #64748b; font-weight: 600;">Nationality:</span>
                    <strong style="color: var(--nis-green);"><?php echo h($card['nationality']); ?></strong>
                </div>
                <div style="display: flex; justify-content: space-between; align-items: center;">
                    <span style="color: #64748b; font-weight: 600;">Command Station:</span>
                    <span style="font-weight: 600; text-align: right; max-width: 135px; word-break: break-word;"><?php echo h($card['issued_at']); ?></span>
                </div>
                <div style="display: flex; justify-content: space-between; align-items: center;">
                    <span style="color: #64748b; font-weight: 600;">Issued On:</span>
                    <strong><?php echo formatNISDate($card['issued_on']); ?></strong>
                </div>
                <div style="display: flex; justify-content: space-between; align-items: center;">
                    <span style="color: #64748b; font-weight: 600;">Expires On:</span>
                    <strong style="color: #b91c1c;"><?php echo formatNISDate($card['expires_on']); ?></strong>
                </div>
                <?php if (!empty($card['approved_at'])): ?>
                    <div style="margin-top: 4px; border-top: 1px dashed #cbd5e1; padding-top: 7px; display: flex; justify-content: space-between; align-items: center; color: var(--nis-green);">
                        <span style="font-weight: 600;">Signed Off:</span>
                        <strong><?php echo formatNISDate($card['approved_at']); ?></strong>
                    </div>
                <?php endif; ?>
            </div>

            <?php if (in_array($card['status'], ['ISSUED', 'RENEWED'])): ?>
                <div style="margin-top: 16px;">
                    <a href="print-idcard?id=<?php echo $id; ?>" class="btn btn-primary" style="width: 100%; justify-content: center;">
                        <i class="fas fa-print"></i> Print Residence Card
                    </a>
                </div>
            <?php endif; ?>
        </div>
    </div>

    <!-- Right Column: Official Institutional Specification Tables -->
    <div style="display: flex; flex-direction: column; gap: 1.5rem;">
        <!-- Section 1: Biodata & Civil Identity -->
        <div class="app-card">
            <div class="card-header" style="display: flex; justify-content: space-between; align-items: center;">
                <h3><i class="fas fa-id-card text-success"></i> Biodata &amp; Civil Identity</h3>
                <span style="font-size: 0.78rem; color: #15803d; font-weight: 600; display: inline-flex; align-items: center; gap: 4px;">
                    <i class="fas fa-check-circle"></i> Verified Registry Record
                </span>
            </div>
            <div class="card-body" style="padding: 0; overflow-x: auto;">
                <table class="dossier-spec-table">
                    <tbody>
                        <tr>
                            <th class="spec-label">Surname</th>
                            <td class="spec-val"><?php echo h($card['surname']); ?></td>
                            <th class="spec-label">Given Name(s)</th>
                            <td class="spec-val"><?php echo h($card['forenames']); ?></td>
                        </tr>
                        <tr>
                            <th class="spec-label">Nationality</th>
                            <td class="spec-val">
                                <strong style="color: var(--nis-green);"><?php echo h($card['nationality']); ?></strong>
                            </td>
                            <th class="spec-label">Sex / Gender</th>
                            <td class="spec-val"><?php echo h($card['sex']); ?></td>
                        </tr>
                        <tr>
                            <th class="spec-label">Date of Birth</th>
                            <td class="spec-val"><?php echo formatNISDate($card['date_of_birth']); ?></td>
                            <th class="spec-label">Place of Birth</th>
                            <td class="spec-val"><?php echo h($card['place_of_birth']); ?></td>
                        </tr>
                        <tr>
                            <th class="spec-label">Passport Number</th>
                            <td class="spec-val">
                                <code style="font-size: 0.95rem; font-weight: 700; color: var(--nis-navy);"><?php echo h($card['passport_number']); ?></code>
                            </td>
                            <th class="spec-label">Issuing Country</th>
                            <td class="spec-val"><?php echo h($card['issuing_country'] ?: 'Federal Republic of Nigeria'); ?></td>
                        </tr>
                        <tr>
                            <th class="spec-label">Profession / Vocation</th>
                            <td class="spec-val"><?php echo h($card['profession'] ?: '—'); ?></td>
                            <th class="spec-label">Registry Booklet No.</th>
                            <td class="spec-val"><?php echo h($card['booklet_number'] ?: '—'); ?></td>
                        </tr>
                    </tbody>
                </table>
            </div>
        </div>

        <!-- Section 2: Biometric & Physical Specifications -->
        <div class="app-card">
            <div class="card-header">
                <h3><i class="fas fa-fingerprint text-success"></i> Biometric &amp; Physical Specifications</h3>
            </div>
            <div class="card-body" style="padding: 0; overflow-x: auto;">
                <table class="dossier-spec-table">
                    <tbody>
                        <tr>
                            <th class="spec-label">Height</th>
                            <td class="spec-val"><?php echo h($card['height'] ?: '—'); ?></td>
                            <th class="spec-label">Blood Group</th>
                            <td class="spec-val">
                                <?php if (!empty($card['blood_group']) && $card['blood_group'] !== 'UNKNOWN'): ?>
                                    <span class="badge" style="background: #fee2e2; color: #991b1b; font-weight: 700; padding: 2px 8px;"><?php echo h($card['blood_group']); ?></span>
                                <?php else: ?>
                                    —
                                <?php endif; ?>
                            </td>
                        </tr>
                        <tr>
                            <th class="spec-label">Complexion</th>
                            <td class="spec-val"><?php echo h($card['complexion'] ?: '—'); ?></td>
                            <th class="spec-label">Colour of Eyes</th>
                            <td class="spec-val"><?php echo h($card['eye_color'] ?: '—'); ?></td>
                        </tr>
                        <tr>
                            <th class="spec-label">Colour of Hair</th>
                            <td class="spec-val"><?php echo h($card['hair_color'] ?: '—'); ?></td>
                            <th class="spec-label">Distinguishing Marks</th>
                            <td class="spec-val"><?php echo h($card['distinguished_features'] ?: 'None recorded'); ?></td>
                        </tr>
                        <tr>
                            <th class="spec-label">Residential Address</th>
                            <td class="spec-val-full" colspan="3">
                                <strong><?php echo h($card['domicile']); ?></strong>
                            </td>
                        </tr>
                    </tbody>
                </table>
            </div>
        </div>

        <!-- Section 3: Emergency Contact & Next of Kin -->
        <div class="app-card">
            <div class="card-header">
                <h3><i class="fas fa-address-book text-success"></i> Emergency Contact &amp; Next of Kin</h3>
            </div>
            <div class="card-body" style="padding: 0; overflow-x: auto;">
                <table class="dossier-spec-table">
                    <tbody>
                        <tr>
                            <th class="spec-label">Contact Full Name</th>
                            <td class="spec-val"><?php echo h($card['emergency_contact_name']); ?></td>
                            <th class="spec-label">Relationship</th>
                            <td class="spec-val"><?php echo h($card['emergency_contact_relation']); ?></td>
                        </tr>
                        <tr>
                            <th class="spec-label">Telephone Number</th>
                            <td class="spec-val">
                                <a href="tel:<?php echo h($card['emergency_contact_phone']); ?>" style="color: var(--nis-green); font-weight: 700; text-decoration: none;">
                                    <i class="fas fa-phone-alt" style="font-size: 0.8rem; margin-right: 4px;"></i><?php echo h($card['emergency_contact_phone']); ?>
                                </a>
                            </td>
                            <th class="spec-label">Change of Address</th>
                            <td class="spec-val"><?php echo h($card['change_of_address'] ?: 'No change recorded'); ?></td>
                        </tr>
                        <tr>
                            <th class="spec-label">Emergency Address</th>
                            <td class="spec-val-full" colspan="3">
                                <?php echo h($card['emergency_contact_address']); ?>
                            </td>
                        </tr>
                    </tbody>
                </table>
            </div>
        </div>

        <!-- Section 4: Issuance & Authorization -->
        <div class="app-card">
            <div class="card-header">
                <h3><i class="fas fa-stamp text-success"></i> Issuance &amp; Authorization</h3>
            </div>
            <div class="card-body" style="padding: 0; overflow-x: auto;">
                <table class="dossier-spec-table">
                    <tbody>
                        <tr>
                            <th class="spec-label">Issuing Command / Station</th>
                            <td class="spec-val"><?php echo h($card['issued_at']); ?></td>
                            <th class="spec-label">Adjudication Decision</th>
                            <td class="spec-val"><?php echo formatNISDate($card['decision_date']); ?></td>
                        </tr>
                        <tr>
                            <th class="spec-label">Date of Issuance</th>
                            <td class="spec-val"><?php echo formatNISDate($card['issued_on']); ?></td>
                            <th class="spec-label">Card Expiry Date</th>
                            <td class="spec-val"><strong style="color: #b91c1c;"><?php echo formatNISDate($card['expires_on']); ?></strong></td>
                        </tr>
                        <tr>
                            <th class="spec-label">Enrolling Officer</th>
                            <td class="spec-val"><?php echo h($card['issuing_officer_name']); ?> (NIS No: <?php echo h($card['issuing_officer_service_no']); ?>)</td>
                            <th class="spec-label">Approval Status</th>
                            <td class="spec-val">
                                <?php if (!empty($card['approved_at'])): ?>
                                    <span style="color: var(--nis-green); font-weight: 700;">
                                        <i class="fas fa-check-circle"></i> Approved on <?php echo formatNISDate($card['approved_at']); ?>
                                    </span>
                                <?php elseif ($card['status'] === 'QUERIED'): ?>
                                    <span style="color: #b91c1c; font-weight: 700;">
                                        <i class="fas fa-exclamation-circle"></i> Queried by Comptroller
                                    </span>
                                <?php elseif ($card['status'] === 'PENDING_APPROVAL'): ?>
                                    <span style="color: #0284c7; font-weight: 700;">
                                        <i class="fas fa-hourglass-half"></i> Awaiting Approval Sign-Off
                                    </span>
                                <?php else: ?>
                                    <span style="color: #64748b;"><?php echo h($card['status']); ?></span>
                                <?php endif; ?>
                            </td>
                        </tr>
                        <?php if ($card['status'] === 'QUERIED' && !empty($card['rejection_reason'])): ?>
                        <tr>
                            <th class="spec-label" style="background: #fef2f2; color: #991b1b;">Query Discrepancy</th>
                            <td class="spec-val-full" colspan="3" style="background: #fef2f2;">
                                <strong style="color: #991b1b;"><?php echo h($card['rejection_reason']); ?></strong>
                            </td>
                        </tr>
                        <?php endif; ?>
                    </tbody>
                </table>
            </div>
        </div>

        <?php if (!empty($renewals)): ?>
        <!-- Renewal History -->
        <div class="app-card">
            <div class="card-header">
                <h3><i class="fas fa-history text-success"></i> Renewal History</h3>
            </div>
            <div class="card-body" style="padding: 0;">
                <table class="nis-table">
                    <thead>
                        <tr>
                            <th>Renewal #</th>
                            <th>Effective From</th>
                            <th>Valid Until</th>
                            <th>Renewed At</th>
                            <th>Endorsing Officer</th>
                        </tr>
                    </thead>
                    <tbody>
                        <?php foreach ($renewals as $r): ?>
                            <tr>
                                <td><strong>Renewal #<?php echo (int)$r['renewal_number']; ?></strong></td>
                                <td><?php echo formatNISDate($r['from_date']); ?></td>
                                <td><strong style="color: #b91c1c;"><?php echo formatNISDate($r['to_date']); ?></strong></td>
                                <td><?php echo h($r['renewed_at']); ?></td>
                                <td><?php echo h($r['endorsing_officer']); ?> (<?php echo h($r['officer_service_no']); ?>)</td>
                            </tr>
                        <?php endforeach; ?>
                    </tbody>
                </table>
            </div>
        </div>
        <?php endif; ?>
    </div>
</div>

<!-- Modal: Revoke Card -->
<div class="nis-modal-backdrop" id="revokeModal">
    <div class="nis-modal-dialog">
        <form method="POST" action="card-details?id=<?php echo $id; ?>">
            <?php echo csrf_field(); ?>
            <input type="hidden" name="action_revoke" value="1">
            <div class="nis-modal-header" style="background: #fde8e8; color: #9b1c1c;">
                <h3><i class="fas fa-ban"></i> Residence Card Revocation</h3>
                <button type="button" class="btn btn-outline btn-sm" onclick="closeModal('revokeModal')" style="padding: 2px 8px; border: none; font-size: 1.1rem;">&times;</button>
            </div>
            <div class="nis-modal-body">
                <p style="font-size: 0.9rem; color: #1e293b; margin-bottom: 14px;">
                    Execute formal cancellation for Residence Card <strong style="font-family: monospace; color: #b91c1c;">No. <?php echo h($card['card_number']); ?></strong> (<?php echo h($card['surname'] . ', ' . $card['forenames']); ?>):
                </p>

                <div class="form-group" style="margin-bottom: 12px;">
                    <label style="font-size: 0.8rem; font-weight: 700; color: #334155; margin-bottom: 4px; display: block;">
                        Legal Grounds *
                    </label>
                    <select name="revocation_grounds" class="form-select" required>
                        <option value="Section 18 Immigration Act 2015">Section 18 Immigration Act 2015 — Obtained by Fraud, Misrepresentation or Concealment</option>
                        <option value="Section 36 Immigration Act 2015">Section 36 Immigration Act 2015 — Cessation of Qualifying Employment / Overstay</option>
                        <option value="Section 39 Immigration Act 2015">Section 39 Immigration Act 2015 — Conviction of Felony / Moral Turpitude Offence</option>
                        <option value="Section 42 Immigration Act 2015">Section 42 Immigration Act 2015 — Public Interest / National Security Directive</option>
                        <option value="Section 45 Immigration Act 2015">Section 45 Immigration Act 2015 — Voluntary Surrender / Relinquishment</option>
                        <option value="Ministerial Determination Order">Ministerial Determination / Comptroller-General Executive Order</option>
                    </select>
                </div>

                <div class="form-group" style="margin-bottom: 14px;">
                    <label style="font-size: 0.8rem; font-weight: 700; color: #334155; margin-bottom: 4px; display: block;">
                        Revocation Order Particulars &amp; File Reference *
                    </label>
                    <textarea name="revocation_notes" class="form-control" style="min-height: 80px;" placeholder="Specify gazette number, court judgment reference, investigation report, or Comptroller directive..." required></textarea>
                </div>

                <div style="background: #fef2f2; border: 1px solid #fecaca; border-radius: 6px; padding: 10px; font-size: 0.8rem; color: #991b1b; display: flex; gap: 8px; align-items: flex-start;">
                    <input type="checkbox" id="confirmRevokeCheck" required style="margin-top: 3px;">
                    <label for="confirmRevokeCheck" style="margin: 0; cursor: pointer;">
                        I certify that this revocation has been authorized in accordance with the Immigration Act 2015 and that the border verification system will immediately flag this card as VOID.
                    </label>
                </div>
            </div>
            <div class="nis-modal-footer">
                <button type="button" class="btn btn-outline" onclick="closeModal('revokeModal')">Cancel</button>
                <button type="submit" class="btn btn-danger"><i class="fas fa-ban"></i> Execute Revocation</button>
            </div>
        </form>
    </div>
</div>

<!-- Modal: Watchlist Toggle -->
<div class="nis-modal-backdrop" id="watchlistModal">
    <div class="nis-modal-dialog">
        <form method="POST" action="card-details?id=<?php echo $id; ?>">
            <?php echo csrf_field(); ?>
            <input type="hidden" name="action_watchlist_toggle" value="1">
            <input type="hidden" name="watchlist_state" id="watchlistStateInput" value="1">

            <div class="nis-modal-header" id="watchlistModalHeader" style="background: #fee2e2; color: #991b1b;">
                <h3 id="watchlistModalTitle"><i class="fas fa-flag"></i> Watchlist Management</h3>
                <button type="button" class="btn btn-outline btn-sm" onclick="closeModal('watchlistModal')" style="padding: 2px 8px; border: none; font-size: 1.1rem;">&times;</button>
            </div>
            <div class="nis-modal-body">
                <div id="watchlistAddSection">
                    <p style="font-size: 0.9rem; color: #1e293b; margin-bottom: 12px;">
                        Flag subject <strong style="color: #b91c1c;"><?php echo h($card['surname'] . ', ' . $card['forenames']); ?></strong> (Passport: <?php echo h($card['passport_number']); ?>) on the National Immigration Watchlist:
                    </p>

                    <div class="form-group" style="margin-bottom: 12px;">
                        <label style="font-size: 0.8rem; font-weight: 700; color: #334155; margin-bottom: 4px; display: block;">
                            Interdiction Category *
                        </label>
                        <select name="watchlist_reason" class="form-select">
                            <option value="INTERPOL Red Notice / International Warrant">INTERPOL Red Notice / International Warrant</option>
                            <option value="Law Enforcement / EFCC / ICPC Investigation">Law Enforcement / EFCC / ICPC Investigation</option>
                            <option value="Judicial Stop Order / Court Injunction">Judicial Stop Order / Court Injunction</option>
                            <option value="Deportation / Repatriation Order Pending">Deportation / Repatriation Order Pending</option>
                            <option value="Suspected Identity Theft / Document Fraud">Suspected Identity Theft / Document Fraud</option>
                            <option value="National Security Interdiction Directive">National Security Interdiction Directive</option>
                        </select>
                    </div>
                </div>

                <div id="watchlistRemoveSection" style="display: none;">
                    <p style="font-size: 0.9rem; color: #1e293b; margin-bottom: 12px;">
                        Are you sure you want to remove the stop order and clear <strong><?php echo h($card['surname'] . ', ' . $card['forenames']); ?></strong> from the Watchlist?
                    </p>
                    <div style="background: #f8fafc; border: 1px solid var(--nis-border); border-radius: 6px; padding: 10px; font-size: 0.82rem; color: #475569;">
                        The subject will no longer be flagged at border control posts or during field inspections.
                    </div>
                </div>
            </div>
            <div class="nis-modal-footer">
                <button type="button" class="btn btn-outline" onclick="closeModal('watchlistModal')">Cancel</button>
                <button type="submit" id="watchlistSubmitBtn" class="btn btn-danger"><i class="fas fa-shield-alt"></i> Apply Watchlist Flag</button>
            </div>
        </form>
    </div>
</div>

<!-- Modal: Approve Card (Single Dossier View) -->
<div class="nis-modal-backdrop" id="approveModal">
    <div class="nis-modal-dialog">
        <form method="POST" action="card-details?id=<?php echo $id; ?>">
            <?php echo csrf_field(); ?>
            <input type="hidden" name="action_approval" value="1">
            <input type="hidden" name="approval_type" value="APPROVE">

            <div class="nis-modal-header" style="background: #def7ec; color: #03543f;">
                <h3><i class="fas fa-clipboard-check"></i> Authorize &amp; Approve Residence Card</h3>
                <button type="button" class="btn btn-outline btn-sm" onclick="closeModal('approveModal')" style="padding: 2px 8px; border: none; font-size: 1.1rem;">&times;</button>
            </div>
            <div class="nis-modal-body">
                <p style="font-size: 0.92rem; color: #1e293b; margin-bottom: 12px;">
                    Approve issuance for Card <strong style="font-family: monospace; color: var(--nis-green-dark);">No. <?php echo h($card['card_number']); ?></strong>:
                </p>
                <div style="background: #f8fafc; border: 1px solid var(--nis-border); border-radius: 6px; padding: 12px; margin-bottom: 12px; font-size: 0.86rem;">
                    <div><strong>Applicant:</strong> <?php echo h($card['surname'] . ', ' . $card['forenames']); ?></div>
                    <div><strong>Nationality:</strong> <?php echo h($card['nationality']); ?></div>
                    <div><strong>Passport No:</strong> <?php echo h($card['passport_number']); ?></div>
                    <div><strong>Issuing Officer:</strong> <?php echo h($card['issuing_officer_name']); ?></div>
                </div>
                <div style="font-size: 0.8rem; color: #475569;">
                    <i class="fas fa-info-circle" style="color: var(--nis-green);"></i> 
                    Approval grants immediate permission for Biometric PVC Smart Card production.
                </div>
            </div>
            <div class="nis-modal-footer">
                <button type="button" class="btn btn-outline" onclick="closeModal('approveModal')">Cancel</button>
                <button type="submit" class="btn btn-primary"><i class="fas fa-check-circle"></i> Confirm Official Approval</button>
            </div>
        </form>
    </div>
</div>

<!-- Modal: Query Application -->
<div class="nis-modal-backdrop" id="queryModal">
    <div class="nis-modal-dialog">
        <form method="POST" action="card-details?id=<?php echo $id; ?>">
            <?php echo csrf_field(); ?>
            <input type="hidden" name="action_approval" value="1">
            <input type="hidden" name="approval_type" value="QUERY">

            <div class="nis-modal-header" style="background: #fef3c7; color: #92400e;">
                <h3><i class="fas fa-exclamation-circle"></i> Query Residence Card Application</h3>
                <button type="button" class="btn btn-outline btn-sm" onclick="closeModal('queryModal')" style="padding: 2px 8px; border: none; font-size: 1.1rem;">&times;</button>
            </div>
            <div class="nis-modal-body">
                <div class="form-group" style="margin-bottom: 12px;">
                    <label style="font-size: 0.8rem; font-weight: 700; color: #334155; margin-bottom: 4px; display: block;">
                        Discrepancy Category *
                    </label>
                    <select name="query_category" class="form-select" required>
                        <option value="Biometric Photo Discrepancy">Biometric Photo Discrepancy (Face blurred / Non-compliant background)</option>
                        <option value="Passport Verification Mismatch">Passport Verification Mismatch (Number / Expiry date mismatch)</option>
                        <option value="Incomplete Address Particulars">Incomplete Address Particulars (Address unverifiable)</option>
                        <option value="Expatriate Quota Reference Query">Expatriate Quota Reference Query (Requires quota approval evidence)</option>
                        <option value="Tax ID / Regulatory Documentation">Tax ID / Regulatory Documentation Incomplete</option>
                    </select>
                </div>
                <div class="form-group">
                    <label style="font-size: 0.8rem; font-weight: 700; color: #334155; margin-bottom: 4px; display: block;">
                        Specific Directives for Issuing Officer *
                    </label>
                    <textarea name="query_notes" class="form-control" style="min-height: 80px;" placeholder="Explain the defect and corrective actions required..." required></textarea>
                </div>
            </div>
            <div class="nis-modal-footer">
                <button type="button" class="btn btn-outline" onclick="closeModal('queryModal')">Cancel</button>
                <button type="submit" class="btn btn-danger"><i class="fas fa-paper-plane"></i> Issue Query</button>
            </div>
        </form>
    </div>
</div>

<script>
function openRevokeModal() {
    document.getElementById('revokeModal').classList.add('active');
}

function openWatchlistModal(state) {
    document.getElementById('watchlistStateInput').value = state;
    if (state === 1) {
        document.getElementById('watchlistAddSection').style.display = 'block';
        document.getElementById('watchlistRemoveSection').style.display = 'none';
        document.getElementById('watchlistModalHeader').style.background = '#fee2e2';
        document.getElementById('watchlistModalHeader').style.color = '#991b1b';
        document.getElementById('watchlistSubmitBtn').innerHTML = '<i class="fas fa-flag"></i> Apply Watchlist Flag';
        document.getElementById('watchlistSubmitBtn').className = 'btn btn-danger';
    } else {
        document.getElementById('watchlistAddSection').style.display = 'none';
        document.getElementById('watchlistRemoveSection').style.display = 'block';
        document.getElementById('watchlistModalHeader').style.background = '#def7ec';
        document.getElementById('watchlistModalHeader').style.color = '#03543f';
        document.getElementById('watchlistSubmitBtn').innerHTML = '<i class="fas fa-shield-alt"></i> Remove from Watchlist';
        document.getElementById('watchlistSubmitBtn').className = 'btn btn-primary';
    }
    document.getElementById('watchlistModal').classList.add('active');
}

function openApproveModal() {
    document.getElementById('approveModal').classList.add('active');
}

function openQueryModal() {
    document.getElementById('queryModal').classList.add('active');
}

function closeModal(modalId) {
    document.getElementById(modalId).classList.remove('active');
}

document.querySelectorAll('.nis-modal-backdrop').forEach(modal => {
    modal.addEventListener('click', function(e) {
        if (e.target === this) {
            this.classList.remove('active');
        }
    });
});
</script>

<?php
require_once __DIR__ . '/includes/footer.php';
?>
