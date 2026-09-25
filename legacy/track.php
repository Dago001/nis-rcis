<?php
/**
 * NIS Residence Card Issuance System (NIS-RCIS)
 * Public Application Tracker & Slip Access (track.php)
 */

require_once __DIR__ . '/includes/security.php';
require_once __DIR__ . '/config/database.php';
require_once __DIR__ . '/config/constants.php';

$db = Database::getConnection();
$searched = false;
$application = null;
$error = '';

$appNum = strtoupper(trim($_GET['app_num'] ?? $_POST['app_num'] ?? ''));
$passport = strtoupper(trim($_GET['passport'] ?? $_POST['passport'] ?? ''));

if (!empty($appNum) || !empty($passport)) {
    $searched = true;
    if (empty($appNum) && empty($passport)) {
        $error = 'Please enter either an Application ID or a Passport Number.';
    } else {
        $sql = "SELECT * FROM applications WHERE 1=1";
        $params = [];
        if (!empty($appNum)) {
            $sql .= " AND (UPPER(application_number) = :app1 OR UPPER(reference_number) = :app2)";
            $params[':app1'] = $appNum;
            $params[':app2'] = $appNum;
        }
        if (!empty($passport)) {
            $sql .= " AND UPPER(passport_number) = :pass";
            $params[':pass'] = $passport;
        }
        $sql .= " ORDER BY id DESC LIMIT 1";

        $stmt = $db->prepare($sql);
        $stmt->execute($params);
        $application = $stmt->fetch(PDO::FETCH_ASSOC);

        if (!$application) {
            $error = "No application record found matching the supplied details. Please verify your Application ID and Passport Number.";
        }
    }
}
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0, maximum-scale=5.0">
    <title>Track Residence Card Application — Nigeria Immigration Service</title>
    <link rel="icon" type="image/png" href="assets/images/nis-crest-logo-transparent.png?v=3">
    <link rel="icon" type="image/png" sizes="32x32" href="assets/images/favicon-32x32.png?v=3">
    <link rel="icon" type="image/png" sizes="16x16" href="assets/images/favicon-16x16.png?v=3">
    <link rel="shortcut icon" href="assets/images/favicon.ico?v=3" type="image/x-icon">
    <link rel="apple-touch-icon" href="assets/images/nis-crest-logo-transparent.png?v=3">
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css">
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
            --nis-border: #e2e8f0;
        }

        * {
            margin: 0;
            padding: 0;
            box-sizing: border-box;
            font-family: 'Segoe UI', -apple-system, BlinkMacSystemFont, Tahoma, Geneva, Verdana, sans-serif;
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
            padding: 10px 1.5rem;
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
            font-size: 1.1rem;
            font-weight: 800;
            color: var(--nis-green-dark);
            text-transform: uppercase;
        }

        .brand-titles h2 {
            font-size: 0.76rem;
            font-weight: 600;
            color: var(--nis-gold);
        }

        .container {
            max-width: 960px;
            margin: 2.5rem auto 4rem;
            padding: 0 1.5rem;
            flex: 1 0 auto;
            width: 100%;
        }

        /* Clean White Search Card */
        .search-card {
            background: #ffffff;
            border-radius: 12px;
            padding: 2.25rem;
            border: 1px solid var(--nis-border, #cbd5e1);
            box-shadow: 0 4px 20px rgba(0, 0, 0, 0.08);
            margin-bottom: 2rem;
            color: #113f1f;
        }

        .search-card h2 {
            font-size: 1.4rem;
            font-weight: 800;
            color: var(--nis-green-dark, #113f1f);
            margin-bottom: 0.4rem;
            letter-spacing: -0.2px;
        }

        .search-card p {
            color: #64748b;
            font-size: 0.92rem;
            margin-bottom: 1.25rem;
            line-height: 1.5;
        }

        .search-form-row {
            display: grid;
            grid-template-columns: 1fr 1fr auto;
            gap: 14px;
            align-items: end;
            width: 100%;
            margin-top: 1rem;
        }

        .search-field {
            display: flex;
            flex-direction: column;
            gap: 6px;
        }

        .search-label {
            font-size: 0.76rem;
            font-weight: 700;
            text-transform: uppercase;
            letter-spacing: 0.5px;
            color: #475569;
        }

        .search-input {
            width: 100%;
            padding: 12px 14px;
            border: 1.5px solid #cbd5e1;
            background: #ffffff;
            border-radius: 7px;
            font-size: 0.92rem;
            color: #0f172a;
            font-weight: 600;
            outline: none;
            text-transform: uppercase;
            transition: border-color 0.2s, box-shadow 0.2s;
        }

        .search-input:focus {
            border-color: #166534;
            box-shadow: 0 0 0 3px rgba(22, 101, 52, 0.15);
        }

        .btn-search {
            background: #115e2e;
            color: #ffffff;
            font-weight: 700;
            padding: 12px 26px;
            border-radius: 7px;
            border: none !important;
            cursor: pointer;
            font-size: 0.92rem;
            height: 47px;
            display: inline-flex;
            align-items: center;
            justify-content: center;
            gap: 8px;
            white-space: nowrap;
            transition: background 0.2s, transform 0.1s;
        }

        .btn-search:hover {
            background: #0b4520;
            color: #ffffff;
        }

        @media (max-width: 768px) {
            .search-form-row {
                grid-template-columns: 1fr;
            }
            .btn-search {
                width: 100%;
            }
        }

        .status-card {
            background: #ffffff;
            border-radius: 12px;
            padding: 2rem;
            border: 1px solid var(--nis-border, #cbd5e1);
            box-shadow: 0 4px 20px rgba(0, 0, 0, 0.08);
            margin-bottom: 2rem;
        }

        /* Stepper Visual */
        .stepper {
            display: flex;
            justify-content: space-between;
            align-items: center;
            position: relative;
            margin: 2.5rem 0 2rem;
        }

        .stepper::before {
            content: "";
            position: absolute;
            top: 20px;
            left: 5%;
            right: 5%;
            height: 4px;
            background: #e2e8f0;
            z-index: 1;
        }

        .step-node {
            position: relative;
            z-index: 2;
            text-align: center;
            width: 110px;
        }

        .step-circle {
            width: 40px;
            height: 40px;
            border-radius: 50%;
            background: #e2e8f0;
            color: #64748b;
            font-weight: 700;
            display: flex;
            align-items: center;
            justify-content: center;
            margin: 0 auto 8px;
            border: 3px solid #ffffff;
            box-shadow: 0 2px 6px rgba(0, 0, 0, 0.1);
        }

        .step-node.completed .step-circle {
            background: #10b981;
            color: #ffffff;
        }

        .step-node.active .step-circle {
            background: var(--nis-gold);
            color: #113f1f;
            box-shadow: 0 0 0 4px rgba(212, 175, 55, 0.25);
        }

        .step-label {
            font-size: 0.74rem;
            font-weight: 700;
            color: var(--nis-muted);
            text-transform: uppercase;
        }

        .step-node.active .step-label {
            color: var(--nis-slate);
        }

        .step-node.completed .step-label {
            color: #10b981;
        }

        /* Detail table */
        .detail-table {
            width: 100%;
            border-collapse: collapse;
            margin-top: 1.5rem;
        }

        .detail-table td {
            padding: 10px 14px;
            border-bottom: 1px solid #e2e8f0;
            font-size: 0.86rem;
        }

        .detail-table td.lbl {
            font-weight: 600;
            color: var(--nis-muted);
            width: 35%;
        }

        .detail-table td.val {
            font-weight: 700;
            color: var(--nis-slate);
        }

        /* Approved Banner */
        .approved-banner {
            background: #f0fdf4;
            border: 2px solid #86efac;
            border-radius: 8px;
            padding: 1.5rem;
            margin-top: 2rem;
            display: flex;
            justify-content: space-between;
            align-items: center;
            flex-wrap: wrap;
            gap: 1rem;
        }

        .btn-print-slip {
            background: linear-gradient(135deg, var(--nis-gold), #b38f20);
            color: #113f1f;
            font-weight: 700;
            padding: 11px 24px;
            border-radius: 6px;
            text-decoration: none;
            display: inline-flex;
            align-items: center;
            gap: 8px;
            box-shadow: 0 4px 12px rgba(0, 0, 0, 0.15);
        }

        .alert-collection {
            background: #ecfdf5;
            border: 2px solid #10b981;
            padding: 1.5rem;
            border-radius: 8px;
            margin-top: 1.5rem;
            display: flex;
            align-items: center;
            gap: 14px;
        }

        .alert-danger {
            background: #fef2f2;
            color: #991b1b;
            border: 1px solid #fecaca;
            padding: 12px 16px;
            border-radius: 6px;
            margin-bottom: 1.5rem;
        }


    </style>
</head>
<body>

    <?php require __DIR__ . '/includes/portal-header.php'; ?>

    <main class="container">
        <!-- Search Box -->
        <div class="search-card">
            <h2>Track Your Application Status</h2>
            <p>Enter your Application ID (e.g. RC-2026-XXXXXX) and Passport Number to track progress or download your approved slip.</p>

            <?php if (!empty($error)): ?>
                <div class="alert-danger" style="margin-top: 1rem; margin-bottom: 1rem;">
                    <i class="fas fa-exclamation-triangle"></i> <?php echo h($error); ?>
                </div>
            <?php endif; ?>

            <form action="track" method="GET" class="search-form-row">
                <div class="search-field">
                    <label class="search-label" for="track_app_num">Application ID</label>
                    <input type="text" id="track_app_num" name="app_num" class="search-input" placeholder="Application ID (RC-...)" value="<?php echo h($appNum); ?>" required autocomplete="off">
                </div>
                <div class="search-field">
                    <label class="search-label" for="track_passport">Passport Number</label>
                    <input type="text" id="track_passport" name="passport" class="search-input" placeholder="Passport Number" value="<?php echo h($passport); ?>" required autocomplete="off">
                </div>
                <div class="search-action">
                    <button type="submit" class="btn-search">Track Status</button>
                </div>
            </form>
        </div>

        <?php if ($application): ?>
            <?php
            $status = $application['status'];
            // Status stage index:
            // 1: Submitted & Paid
            // 2: Officer Vetting (PENDING_APPROVAL)
            // 3: Approved for Biometrics (APPROVED_FOR_BIOMETRICS)
            // 4: Biometrics Captured (BIOMETRICS_CAPTURED)
            // 5: Ready for Collection (READY_FOR_COLLECTION / ISSUED)
            $stepIndex = 1;
            if ($status === 'PENDING_APPROVAL') $stepIndex = 2;
            elseif ($status === 'APPROVED_FOR_BIOMETRICS') $stepIndex = 3;
            elseif ($status === 'BIOMETRICS_CAPTURED') $stepIndex = 4;
            elseif ($status === 'READY_FOR_COLLECTION' || $status === 'ISSUED') $stepIndex = 5;
            ?>

            <div class="status-card">
                <div style="display: flex; justify-content: space-between; align-items: center; flex-wrap: wrap; gap: 10px; border-bottom: 2px solid #e2e8f0; padding-bottom: 1rem;">
                    <div>
                        <span style="font-size: 0.76rem; font-weight: 700; color: var(--nis-muted); text-transform: uppercase;">Application Reference</span>
                        <h3 style="font-size: 1.4rem; color: var(--nis-green); font-weight: 800;"><?php echo h($application['application_number']); ?></h3>
                    </div>
                    <div>
                        <?php if ($status === 'APPROVED_FOR_BIOMETRICS'): ?>
                            <span style="background: #ecfdf5; color: #059669; font-weight: 700; padding: 6px 14px; border-radius: 20px; font-size: 0.85rem; border: 1px solid #a7f3d0;">
                                <i class="fas fa-check-circle"></i> APPROVED FOR BIOMETRICS
                            </span>
                        <?php elseif ($status === 'PENDING_APPROVAL'): ?>
                            <span style="background: #fef3c7; color: #92400e; font-weight: 700; padding: 6px 14px; border-radius: 20px; font-size: 0.85rem; border: 1px solid #fde68a;">
                                <i class="fas fa-clock"></i> PENDING APPROVAL
                            </span>
                        <?php elseif ($status === 'READY_FOR_COLLECTION'): ?>
                            <span style="background: #ecfdf5; color: #047857; font-weight: 700; padding: 6px 14px; border-radius: 20px; font-size: 0.85rem; border: 1px solid #6ee7b7;">
                                <i class="fas fa-bell"></i> READY FOR COLLECTION
                            </span>
                        <?php elseif ($status === 'ISSUED'): ?>
                            <span style="background: #ecfdf5; color: #065f46; font-weight: 700; padding: 6px 14px; border-radius: 20px; font-size: 0.85rem; border: 1px solid #34d399;">
                                <i class="fas fa-id-card"></i> CARD ISSUED
                            </span>
                        <?php elseif ($status === 'QUERIED'): ?>
                            <span style="background: #fee2e2; color: #b91c1c; font-weight: 700; padding: 6px 14px; border-radius: 20px; font-size: 0.85rem; border: 1px solid #fca5a5;">
                                <i class="fas fa-exclamation-circle"></i> QUERIED BY OFFICER
                            </span>
                        <?php endif; ?>
                    </div>
                </div>

                <!-- Progress Stepper -->
                <div class="stepper">
                    <div class="step-node <?php echo $stepIndex >= 1 ? ($stepIndex > 1 ? 'completed' : 'active') : ''; ?>">
                        <div class="step-circle"><i class="fas fa-file-invoice"></i></div>
                        <div class="step-label">Paid &amp; Submitted</div>
                    </div>

                    <div class="step-node <?php echo $stepIndex >= 2 ? ($stepIndex > 2 ? 'completed' : 'active') : ''; ?>">
                        <div class="step-circle"><i class="fas fa-user-check"></i></div>
                        <div class="step-label">Officer Review</div>
                    </div>

                    <div class="step-node <?php echo $stepIndex >= 3 ? ($stepIndex > 3 ? 'completed' : 'active') : ''; ?>">
                        <div class="step-circle"><i class="fas fa-stamp"></i></div>
                        <div class="step-label">Approved</div>
                    </div>

                    <div class="step-node <?php echo $stepIndex >= 4 ? ($stepIndex > 4 ? 'completed' : 'active') : ''; ?>">
                        <div class="step-circle"><i class="fas fa-fingerprint"></i></div>
                        <div class="step-label">Biometrics</div>
                    </div>

                    <div class="step-node <?php echo $stepIndex >= 5 ? 'active completed' : ''; ?>">
                        <div class="step-circle"><i class="fas fa-id-card"></i></div>
                        <div class="step-label">Card Ready</div>
                    </div>
                </div>

                <!-- Action Banners -->
                <?php if ($status === 'APPROVED_FOR_BIOMETRICS' || $status === 'BIOMETRICS_CAPTURED' || $status === 'READY_FOR_COLLECTION' || $status === 'ISSUED'): ?>
                    <div class="approved-banner">
                        <div>
                            <h4 style="color: #166534; font-size: 1.05rem; font-weight: 800; margin-bottom: 4px;">
                                <i class="fas fa-check-circle"></i> Application Approved
                            </h4>
                            <p style="color: #1e3a8a; font-size: 0.84rem;">
                                You are scheduled for biometric enrollment on <strong><?php echo date('l, d F Y', strtotime($application['appointment_date'])); ?></strong>.
                            </p>
                        </div>
                        <a href="print-appointment-slip?app_id=<?php echo urlencode($application['id']); ?>" target="_blank" class="btn-print-slip">
                            <i class="fas fa-print"></i> Print Approved Biometrics Slip
                        </a>
                    </div>
                <?php endif; ?>

                <?php if ($status === 'READY_FOR_COLLECTION'): ?>
                    <div class="alert-collection">
                        <div style="font-size: 2rem; color: #059669;"><i class="fas fa-bell"></i></div>
                        <div>
                            <h4 style="font-size: 1rem; font-weight: 800; color: #065f46; margin-bottom: 2px;">Your Residence Card is Ready for Collection!</h4>
                            <p style="font-size: 0.84rem; color: #047857;">
                                Please visit <strong><?php echo h($application['enrollment_center']); ?></strong> with your original travel passport and identity slip to collect your official PVC Residence Card.
                            </p>
                        </div>
                    </div>
                <?php endif; ?>

                <?php if ($status === 'QUERIED'): ?>
                    <div style="background: #fef2f2; border: 1.5px solid #f87171; border-radius: 8px; padding: 1.25rem; margin-top: 1.5rem;">
                        <h4 style="color: #991b1b; font-size: 0.95rem; font-weight: 700; margin-bottom: 4px;"><i class="fas fa-exclamation-circle"></i> Approving Officer Query Notice:</h4>
                        <p style="color: #7f1d1d; font-size: 0.85rem;"><?php echo h($application['approval_notes'] ?? 'Discrepancy detected in submitted particulars. Please contact the enrollment center.'); ?></p>
                    </div>
                <?php endif; ?>

                <!-- Application Particulars -->
                <table class="detail-table">
                    <tr>
                        <td class="lbl">Applicant Full Name:</td>
                        <td class="val"><?php echo h($application['surname'] . ', ' . $application['forenames']); ?></td>
                    </tr>
                    <tr>
                        <td class="lbl">Nationality:</td>
                        <td class="val"><?php echo h($application['nationality']); ?></td>
                    </tr>
                    <tr>
                        <td class="lbl">Passport Number:</td>
                        <td class="val"><?php echo h($application['passport_number']); ?></td>
                    </tr>
                    <tr>
                        <td class="lbl">Application Fee:</td>
                        <td class="val">₦<?php echo number_format($application['fee_amount'], 2); ?> (Reference: <?php echo h($application['payment_reference']); ?>)</td>
                    </tr>
                    <tr>
                        <td class="lbl">Designated Enrollment Center:</td>
                        <td class="val"><?php echo h($application['enrollment_center']); ?></td>
                    </tr>
                    <tr>
                        <td class="lbl">Scheduled Appointment Date:</td>
                        <td class="val"><?php echo date('d F Y', strtotime($application['appointment_date'])); ?> &bull; <?php echo h($application['appointment_time']); ?></td>
                    </tr>
                    <tr>
                        <td class="lbl">Date of Application:</td>
                        <td class="val"><?php echo date('d M Y, h:i A', strtotime($application['created_at'])); ?></td>
                    </tr>
                    <?php if (!empty($application['doc_passport_copy']) || !empty($application['doc_residence_visa']) || !empty($application['doc_quota_approval'])): ?>
                    <tr>
                        <td class="lbl">Attached Documents:</td>
                        <td class="val">
                            <div style="display: flex; flex-wrap: wrap; gap: 6px; margin-top: 4px;">
                                <?php if (!empty($application['doc_passport_copy'])): ?>
                                    <span style="display: inline-flex; align-items: center; gap: 5px; font-size: 0.78rem; background: #f1f5f9; padding: 4px 8px; border-radius: 4px; border: 1px solid #cbd5e1;"><i class="fas fa-file-pdf" style="color: #ef4444;"></i> Passport Bio-Data</span>
                                <?php endif; ?>
                                <?php if (!empty($application['doc_residence_visa'])): ?>
                                    <span style="display: inline-flex; align-items: center; gap: 5px; font-size: 0.78rem; background: #f1f5f9; padding: 4px 8px; border-radius: 4px; border: 1px solid #cbd5e1;"><i class="fas fa-file-pdf" style="color: #ef4444;"></i> Entry / STR Visa</span>
                                <?php endif; ?>
                                <?php if (!empty($application['doc_quota_approval'])): ?>
                                    <span style="display: inline-flex; align-items: center; gap: 5px; font-size: 0.78rem; background: #f1f5f9; padding: 4px 8px; border-radius: 4px; border: 1px solid #cbd5e1;"><i class="fas fa-file-pdf" style="color: #ef4444;"></i> Quota / Employment</span>
                                <?php endif; ?>
                                <?php if (!empty($application['doc_domicile_proof'])): ?>
                                    <span style="display: inline-flex; align-items: center; gap: 5px; font-size: 0.78rem; background: #f1f5f9; padding: 4px 8px; border-radius: 4px; border: 1px solid #cbd5e1;"><i class="fas fa-file-alt" style="color: #0284c7;"></i> Residence Proof</span>
                                <?php endif; ?>
                                <?php if (!empty($application['doc_additional'])): ?>
                                    <span style="display: inline-flex; align-items: center; gap: 5px; font-size: 0.78rem; background: #f1f5f9; padding: 4px 8px; border-radius: 4px; border: 1px solid #cbd5e1;"><i class="fas fa-file-alt" style="color: #0284c7;"></i> Additional Doc</span>
                                <?php endif; ?>
                            </div>
                        </td>
                    </tr>
                    <?php endif; ?>
                </table>
            </div>
        <?php endif; ?>
    </main>

    <?php require_once __DIR__ . '/includes/portal-footer.php'; ?>

</body>
</html>
