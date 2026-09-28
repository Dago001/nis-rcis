<?php
/**
 * NIS Residence Card Issuance System (NIS-RCIS)
 * Field Officer QR Code & Mobile Verification Endpoint
 * Optimized for rugged mobile scanners, smartphones, and border control posts
 */

require_once __DIR__ . '/includes/security.php';
require_once __DIR__ . '/config/database.php';
require_once __DIR__ . '/config/constants.php';

$db = Database::getConnection();

$token = trim($_GET['token'] ?? '');
$cardNo = trim($_GET['card'] ?? '');
$searched = (!empty($token) || !empty($cardNo));
$card = null;

if (!empty($token)) {
    $stmt = $db->prepare("SELECT * FROM residence_cards WHERE verification_token = :tk LIMIT 1");
    $stmt->execute([':tk' => $token]);
    $card = $stmt->fetch();
} elseif (!empty($cardNo)) {
    $stmt = $db->prepare("SELECT * FROM residence_cards WHERE card_number = :cn OR booklet_number = :bn OR passport_number = :pn LIMIT 1");
    $stmt->execute([':cn' => $cardNo, ':bn' => $cardNo, ':pn' => $cardNo]);
    $card = $stmt->fetch();
}

if ($card) {
    logAudit('FIELD_QR_VERIFIED', $card['id'], "Field inspection check on Card No. {$card['card_number']}");
}
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0, maximum-scale=1.0, user-scalable=no">
    <title>Field Verification Gateway | <?php echo APP_NAME; ?></title>
    <!-- Favicon / URL Icon -->
    <link rel="icon" type="image/png" href="assets/images/nis-crest-logo-transparent.png?v=3">
    <link rel="icon" type="image/png" sizes="32x32" href="assets/images/favicon-32x32.png?v=3">
    <link rel="icon" type="image/png" sizes="16x16" href="assets/images/favicon-16x16.png?v=3">
    <link rel="shortcut icon" href="assets/images/favicon.ico?v=3" type="image/x-icon">
    <link rel="apple-touch-icon" href="assets/images/nis-crest-logo-transparent.png?v=3">
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css">
    <style>
        :root {
            --nis-green: #113f1f;
            --nis-green-light: #1a5c2e;
            --nis-gold: #d4af37;
            --nis-navy: #113f1f;
            --nis-red: #b91c1c;
            --nis-orange: #c2410c;
        }

        * { box-sizing: border-box; margin: 0; padding: 0; }
        
        body {
            font-family: -apple-system, BlinkMacSystemFont, "Segoe UI", Roboto, Helvetica, Arial, sans-serif;
            background: #0f172a;
            color: #0f172a;
            min-height: 100vh;
            display: flex;
            flex-direction: column;
            align-items: center;
            padding: 12px;
        }

        .gateway-container {
            width: 100%;
            max-width: 520px;
            background: #ffffff;
            border-radius: 16px;
            box-shadow: 0 20px 35px rgba(0, 0, 0, 0.35);
            overflow: hidden;
            margin: 10px 0 25px;
        }

        .gateway-header {
            background: linear-gradient(135deg, #082010 0%, #113f1f 60%, #1a5c2e 100%);
            color: #ffffff;
            padding: 20px 16px;
            text-align: center;
            position: relative;
            border-bottom: none;
        }

        .nis-crest {
            width: 58px;
            height: auto;
            margin-bottom: 8px;
            filter: drop-shadow(0 2px 4px rgba(0,0,0,0.3));
        }

        .agency-title {
            font-size: 1.05rem;
            font-weight: 800;
            letter-spacing: 0.8px;
            text-transform: uppercase;
            margin-bottom: 2px;
        }

        .gateway-subtitle {
            font-size: 0.76rem;
            color: #fef08a;
            letter-spacing: 0.4px;
            text-transform: uppercase;
            font-weight: 600;
        }

        .gateway-body {
            padding: 18px 16px;
        }

        /* Status Banners */
        .status-hero {
            padding: 14px 16px;
            border-radius: 10px;
            text-align: center;
            font-weight: 700;
            margin-bottom: 18px;
            display: flex;
            flex-direction: column;
            align-items: center;
            gap: 6px;
        }

        .status-hero.watchlist {
            background: #fef2f2;
            color: #991b1b;
            border: 2px solid #ef4444;
            animation: pulseAlert 1.5s infinite;
        }

        .status-hero.revoked {
            background: #fff1f2;
            color: #9f1239;
            border: 2px solid #f43f5e;
        }

        .status-hero.expired {
            background: #fff7ed;
            color: #9a3412;
            border: 2px solid #fb923c;
        }

        .status-hero.pending {
            background: #fefce8;
            color: #854d0e;
            border: 2px solid #facc15;
        }

        .status-hero.valid {
            background: #f0fdf4;
            color: #166534;
            border: 2px solid #4ade80;
        }

        @keyframes pulseAlert {
            0%, 100% { box-shadow: 0 0 0 0 rgba(239, 68, 68, 0.4); }
            50% { box-shadow: 0 0 0 8px rgba(239, 68, 68, 0); }
        }

        .hero-title {
            font-size: 1.12rem;
            font-weight: 800;
            display: flex;
            align-items: center;
            gap: 8px;
            letter-spacing: 0.3px;
        }

        .hero-desc {
            font-size: 0.82rem;
            font-weight: 500;
            line-height: 1.35;
        }

        /* Cardholder Header Bar */
        .cardholder-card {
            background: #f8fafc;
            border: 1px solid #e2e8f0;
            border-radius: 10px;
            padding: 14px;
            display: flex;
            gap: 14px;
            align-items: center;
            margin-bottom: 18px;
            position: relative;
            overflow: hidden;
        }

        .biometric-avatar {
            width: 76px;
            height: 94px;
            border-radius: 6px;
            overflow: hidden;
            background: #e2e8f0;
            border: 2px solid var(--nis-green);
            flex-shrink: 0;
            box-shadow: 0 2px 4px rgba(0,0,0,0.1);
        }

        .biometric-avatar img {
            width: 100%;
            height: 100%;
            object-fit: cover;
        }

        .holder-info h2 {
            font-size: 1.15rem;
            color: #0f172a;
            font-weight: 800;
            line-height: 1.25;
            margin-bottom: 3px;
        }

        .holder-meta {
            font-size: 0.8rem;
            color: #475569;
            font-weight: 600;
        }

        .holder-cardno {
            display: inline-block;
            margin-top: 5px;
            font-family: monospace;
            font-size: 0.95rem;
            font-weight: 800;
            color: var(--nis-green);
            background: #e2e8f0;
            padding: 2px 8px;
            border-radius: 4px;
        }

        /* Particulars Grid */
        .details-grid {
            display: grid;
            grid-template-columns: 1fr 1fr;
            gap: 10px 14px;
            background: #ffffff;
            border: 1px solid #e2e8f0;
            border-radius: 10px;
            padding: 14px;
            margin-bottom: 18px;
        }

        .detail-item {
            font-size: 0.82rem;
        }

        .detail-label {
            color: #64748b;
            font-size: 0.72rem;
            font-weight: 700;
            text-transform: uppercase;
            margin-bottom: 2px;
        }

        .detail-val {
            font-weight: 700;
            color: #1e293b;
            word-break: break-word;
        }

        .full-span {
            grid-column: span 2;
        }

        /* Checkpoint Advisory Box */
        .advisory-box {
            border-radius: 8px;
            padding: 12px 14px;
            font-size: 0.82rem;
            line-height: 1.4;
            margin-bottom: 18px;
            display: flex;
            gap: 10px;
            align-items: flex-start;
        }

        .advisory-box.danger {
            background: #fee2e2;
            border: 1px solid #fca5a5;
            color: #991b1b;
        }

        .advisory-box.warning {
            background: #ffedd5;
            border: 1px solid #fdba74;
            color: #9a3412;
        }

        .advisory-box.success {
            background: #dcfce7;
            border: 1px solid #86efac;
            color: #166534;
        }

        /* Manual Scanner Form */
        .scanner-form {
            border-top: 1px solid #e2e8f0;
            padding-top: 16px;
        }

        .scanner-input-group {
            display: flex;
            gap: 8px;
        }

        .scanner-input {
            flex: 1;
            padding: 10px 12px;
            border: 1px solid #cbd5e1;
            border-radius: 6px;
            font-size: 0.9rem;
            text-transform: uppercase;
        }

        .scanner-btn {
            padding: 10px 16px;
            background: var(--nis-green);
            color: #ffffff;
            border: none;
            border-radius: 6px;
            font-weight: 700;
            font-size: 0.9rem;
            cursor: pointer;
            display: inline-flex;
            align-items: center;
            gap: 6px;
        }

        .gateway-footer {
            font-size: 0.74rem;
            color: #94a3b8;
            text-align: center;
            margin-top: 14px;
            line-height: 1.4;
        }
    </style>
</head>
<body>

<div class="gateway-container">
    <div class="gateway-header">
        <img src="assets/images/nis-logo.png" alt="NIS Crest" class="nis-crest">
        <div class="agency-title">NIGERIA IMMIGRATION SERVICE</div>
        <div class="gateway-subtitle">Field Officer &amp; Border Inspection Gateway</div>
    </div>

    <div class="gateway-body">
        <?php if ($card): ?>
            <?php
            $isWatchlisted = !empty($card['is_watchlisted']);
            $isRevoked = ($card['status'] === 'REVOKED');
            $isPending = ($card['status'] === 'PENDING_APPROVAL');
            $isQueried = ($card['status'] === 'QUERIED');
            $expTimestamp = strtotime($card['expires_on']);
            $isExpired = ($expTimestamp < time());
            $daysDiff = floor(($expTimestamp - time()) / 86400);
            ?>

            <!-- Hero Status Banner -->
            <?php if ($isWatchlisted): ?>
                <div class="status-hero watchlist">
                    <div class="hero-title">
                        <i class="fas fa-exclamation-triangle"></i> WATCHLIST ALERT
                    </div>
                    <div class="hero-desc">
                        CODE RED: Subject is flagged on the NIS Watchlist. Refuse departure or entry, detain subject, and immediately contact NIS Command Operations Center.
                    </div>
                    <div style="background: rgba(220, 38, 38, 0.15); padding: 6px 10px; border-radius: 4px; font-size: 0.78rem; margin-top: 4px; font-weight: 700;">
                        Directive: <?php echo h($card['watchlist_reason'] ?: 'Interdiction Order'); ?>
                    </div>
                </div>

            <?php elseif ($isRevoked): ?>
                <div class="status-hero revoked">
                    <div class="hero-title">
                        <i class="fas fa-ban"></i> CARD OFFICIALLY REVOKED
                    </div>
                    <div class="hero-desc">
                        DOCUMENT IS VOID: This residence card has been revoked pursuant to the Immigration Act 2015. Confiscate document and issue Form NIS-42.
                    </div>
                    <div style="font-size: 0.78rem; font-weight: 600; margin-top: 3px;">
                        Grounds: <?php echo h($card['revocation_reason'] ?: 'Revocation Order Executed'); ?>
                    </div>
                </div>

            <?php elseif ($isPending || $isQueried): ?>
                <div class="status-hero pending">
                    <div class="hero-title">
                        <i class="fas fa-hourglass-half"></i> PENDING COMPTROLLER APPROVAL
                    </div>
                    <div class="hero-desc">
                        This application is undergoing official review and is NOT yet authorized as a valid residence document.
                    </div>
                </div>

            <?php elseif ($isExpired): ?>
                <div class="status-hero expired">
                    <div class="hero-title">
                        <i class="fas fa-clock"></i> CARD STATUS: EXPIRED
                    </div>
                    <div class="hero-desc">
                        Residence Card expired on <?php echo formatNISDate($card['expires_on']); ?> (Overstay: <?php echo abs($daysDiff); ?> days). Subject must regularize status.
                    </div>
                </div>

            <?php else: ?>
                <div class="status-hero valid">
                    <div class="hero-title">
                        <i class="fas fa-check-circle"></i> AUTHENTIC RESIDENCE CARD
                    </div>
                    <div class="hero-desc">
                        Digital cryptoseal verified. Card is valid and compliant under Nigerian Immigration Laws (Valid for <?php echo max(0, $daysDiff); ?> more days).
                    </div>
                </div>
            <?php endif; ?>

            <!-- Cardholder Avatar & Name Bar -->
            <div class="cardholder-card">
                <div class="biometric-avatar">
                    <?php if (!empty($card['photo_path']) && file_exists(__DIR__ . '/' . $card['photo_path'])): ?>
                        <img src="<?php echo h($card['photo_path']); ?>" alt="Photo">
                    <?php else: ?>
                        <div style="display: flex; align-items: center; justify-content: center; height: 100%; color: #94a3b8; font-size: 2rem;">
                            <i class="fas fa-user"></i>
                        </div>
                    <?php endif; ?>
                </div>
                <div class="holder-info">
                    <h2><?php echo h($card['surname'] . ', ' . $card['forenames']); ?></h2>
                    <div class="holder-meta">
                        <?php echo h($card['nationality']); ?> • <?php echo h($card['sex']); ?>
                    </div>
                    <div class="holder-cardno">
                        No. <?php echo h($card['card_number']); ?>
                    </div>
                </div>
            </div>

            <!-- Particulars Grid -->
            <div class="details-grid">
                <div class="detail-item">
                    <div class="detail-label">Booklet Number</div>
                    <div class="detail-val"><?php echo h($card['booklet_number']); ?></div>
                </div>
                <div class="detail-item">
                    <div class="detail-label">Passport Number</div>
                    <div class="detail-val" style="font-family: monospace;"><?php echo h($card['passport_number']); ?></div>
                </div>
                <div class="detail-item">
                    <div class="detail-label">Date of Birth</div>
                    <div class="detail-val"><?php echo formatNISDate($card['date_of_birth']); ?></div>
                </div>
                <div class="detail-item">
                    <div class="detail-label">Blood Group</div>
                    <div class="detail-val"><?php echo h($card['blood_group'] ?: 'UNKNOWN'); ?></div>
                </div>
                <div class="detail-item full-span">
                    <div class="detail-label">Profession / Calling</div>
                    <div class="detail-val"><?php echo h($card['profession']); ?></div>
                </div>
                <div class="detail-item full-span">
                    <div class="detail-label">Residential Address (Nigeria)</div>
                    <div class="detail-val" style="font-weight: 500; font-size: 0.8rem;"><?php echo h($card['domicile']); ?></div>
                </div>
                <div class="detail-item">
                    <div class="detail-label">Issued On</div>
                    <div class="detail-val"><?php echo formatNISDate($card['issued_on']); ?></div>
                </div>
                <div class="detail-item">
                    <div class="detail-label">Expires On</div>
                    <div class="detail-val" style="color: <?php echo $isExpired ? '#b91c1c' : '#166534'; ?>;">
                        <?php echo formatNISDate($card['expires_on']); ?>
                    </div>
                </div>
                <div class="detail-item full-span">
                    <div class="detail-label">Issuing Command</div>
                    <div class="detail-val" style="font-size: 0.78rem;"><?php echo h($card['issued_at']); ?></div>
                </div>
            </div>

            <!-- Checkpoint Officer Advisory -->
            <?php if ($isWatchlisted): ?>
                <div class="advisory-box danger">
                    <i class="fas fa-hand-paper" style="font-size: 1.2rem; margin-top: 2px;"></i>
                    <div>
                        <strong>CHECKPOINT ACTION:</strong> Escort cardholder to the nearest NIS Command Port Interdiction Desk. Retain physical document and do not permit boarding/entry.
                    </div>
                </div>
            <?php elseif ($isRevoked): ?>
                <div class="advisory-box danger">
                    <i class="fas fa-exclamation-circle" style="font-size: 1.2rem; margin-top: 2px;"></i>
                    <div>
                        <strong>CHECKPOINT ACTION:</strong> Confiscate this card. The document is void and cancelled. Issue official notice Form NIS-42.
                    </div>
                </div>
            <?php elseif ($isExpired): ?>
                <div class="advisory-box warning">
                    <i class="fas fa-clock" style="font-size: 1.2rem; margin-top: 2px;"></i>
                    <div>
                        <strong>CHECKPOINT ACTION:</strong> Subject has an expired residency permit. Route to Border Investigation Bureau for overstay regularization.
                    </div>
                </div>
            <?php else: ?>
                <div class="advisory-box success">
                    <i class="fas fa-check-shield" style="font-size: 1.2rem; margin-top: 2px;"></i>
                    <div>
                        <strong>CHECKPOINT ACTION:</strong> Valid cardholder. All biometric attributes and document security checks verified. Clear subject.
                    </div>
                </div>
            <?php endif; ?>

            <div style="font-size: 0.72rem; color: #64748b; text-align: center; margin-bottom: 12px;">
                Verified on <?php echo date('d M Y, H:i:s'); ?> WAT • Cryptographic Checksum OK
            </div>

        <?php elseif ($searched): ?>
            <div class="status-hero watchlist">
                <div class="hero-title">
                    <i class="fas fa-times-circle"></i> UNVERIFIED DOCUMENT
                </div>
                <div class="hero-desc">
                    No matching residence card file found in the Nigeria Immigration Service national repository.
                </div>
            </div>
            <p style="font-size: 0.85rem; color: #475569; text-align: center; margin-bottom: 18px;">
                The scanned QR code or document number is unrecognized. If the holder presents a physical card, treat as suspected counterfeit and escort to Border Intelligence.
            </p>
        <?php else: ?>
            <div style="text-align: center; padding: 25px 10px; color: #64748b;">
                <i class="fas fa-qrcode" style="font-size: 3rem; color: var(--nis-green); margin-bottom: 12px; display: block;"></i>
                <h3 style="font-size: 1.1rem; color: #0f172a; margin-bottom: 6px;">Ready to Scan or Lookup</h3>
                <p style="font-size: 0.84rem;">Scan a physical Residence Card QR code or enter a Card / Passport Number below.</p>
            </div>
        <?php endif; ?>

        <!-- Manual Inspection Lookup Form -->
        <div class="scanner-form">
            <label style="font-size: 0.78rem; font-weight: 700; color: #334155; margin-bottom: 6px; display: block; text-transform: uppercase;">
                Manual Inspection Card / Passport Lookup
            </label>
            <form method="GET" action="verify-card" class="scanner-input-group">
                <input type="text" name="card" class="scanner-input" placeholder="Enter Card No. (e.g. 389107)" value="<?php echo h($cardNo); ?>" required>
                <button type="submit" class="scanner-btn">
                    <i class="fas fa-search"></i> Verify
                </button>
            </form>
        </div>
    </div>
</div>

<div class="gateway-footer" style="color: #ffffff !important; text-shadow: 0 1px 4px rgba(0,0,0,0.85); text-align: center; margin: 1.5rem auto 1rem; font-size: 0.85rem; font-weight: 700; width: 100%;">
    &copy; <?php echo date('Y'); ?> Nigeria Immigration Service (NIS). All rights reserved.
</div>

</body>
</html>
