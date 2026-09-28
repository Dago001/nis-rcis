<?php
/**
 * NIS Residence Card Issuance System (NIS-RCIS)
 * Official Public Portal Privacy Policy & Data Protection Notice
 * Compliant with Nigeria Data Protection Act (NDPA 2023) & Immigration Act 2015
 */

require_once __DIR__ . '/includes/security.php';
require_once __DIR__ . '/config/constants.php';

$pageTitle = 'Privacy Policy & Data Protection';
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0, maximum-scale=5.0">
    <title>Privacy Policy — Nigeria Immigration Service</title>

    <link rel="icon" type="image/png" href="assets/images/nis-crest-logo-transparent.png?v=3">
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css">
    <link rel="stylesheet" href="assets/css/style.css?v=<?php echo APP_VERSION; ?>">

    <style>
        body {
            background: linear-gradient(135deg, rgba(248, 250, 252, 0.45) 0%, rgba(241, 245, 249, 0.55) 100%),
                        url('assets/images/login-bg.jpg') no-repeat center center fixed;
            background-size: cover;
            color: #1e293b;
            font-family: -apple-system, BlinkMacSystemFont, "Segoe UI", Roboto, "Helvetica Neue", Arial, sans-serif;
            min-height: 100vh;
            display: flex;
            flex-direction: column;
        }

        .policy-container {
            max-width: 860px;
            margin: 2.5rem auto 4rem;
            padding: 0 1.5rem;
            flex: 1 0 auto;
            width: 100%;
        }

        .policy-card {
            background: #ffffff;
            border-radius: 12px;
            padding: 2.5rem;
            box-shadow: 0 4px 20px rgba(0, 0, 0, 0.05);
            border: none;
        }

        .policy-header {
            padding-bottom: 1.75rem;
            margin-bottom: 2rem;
            text-align: center;
            border: none;
        }

        .policy-header img {
            width: 68px;
            height: auto;
            margin-bottom: 1rem;
        }

        .policy-header h1 {
            font-size: 1.6rem;
            font-weight: 700;
            color: #0f172a;
            margin-bottom: 0.5rem;
            letter-spacing: -0.2px;
        }

        .policy-header p {
            font-size: 0.9rem;
            color: #64748b;
            margin: 0;
        }

        .policy-section {
            margin-bottom: 2rem;
        }

        .policy-section h2 {
            font-size: 1.15rem;
            font-weight: 700;
            color: #0f172a;
            margin-bottom: 0.75rem;
            border: none;
        }

        .policy-section p, .policy-section li {
            font-size: 0.92rem;
            color: #475569;
            line-height: 1.7;
        }

        .policy-section ul {
            padding-left: 1.5rem;
            margin: 0.5rem 0 1rem;
        }

        .policy-section li {
            margin-bottom: 0.5rem;
        }

        .policy-notice-box {
            background: #f8fafc;
            border: none;
            border-radius: 8px;
            padding: 1.25rem 1.5rem;
            margin: 1.5rem 0 2rem;
            font-size: 0.9rem;
            line-height: 1.65;
            color: #334155;
        }
    </style>
</head>
<body>

    <?php require __DIR__ . '/includes/portal-header.php'; ?>

    <main class="policy-container">
        <div class="policy-card">
            <div class="policy-header">
                <img src="assets/images/nis-crest-logo-transparent.png?v=3" alt="NIS Crest">
                <h1>Privacy Policy &amp; Data Protection Notice</h1>
                <p>Official Data Protection &amp; Privacy Guidelines • Nigeria Immigration Service</p>
            </div>

            <div class="policy-notice-box">
                <strong>Official Governance Notice:</strong>
                The Nigeria Immigration Service (NIS) operates under the statutory mandate of the <em>Immigration Act 2015</em> and complies with the provisions of the <em>Nigeria Data Protection Act (NDPA 2023)</em>. All foreign nationals and expatriate residence card applicants are protected under high-assurance data privacy protocols.
            </div>

            <div class="policy-section">
                <h2>1. Information We Collect</h2>
                <p>When you register for an Applicant Profile and apply for a Nigerian Residence Permit, we collect the following categories of data:</p>
                <ul>
                    <li><strong>Personal &amp; Contact Particulars:</strong> Full legal name, nationality, date and place of birth, gender, residential domicile within Nigeria, telephone numbers, and email address.</li>
                    <li><strong>Passport &amp; Immigration Documents:</strong> International passport biometric data page, current validity period, residence entry visas, Expatriate Quota approvals, and business/employment endorsements.</li>
                    <li><strong>Physical &amp; Biometric Records:</strong> High-resolution facial photographs, live biometric fingerprints, iris scans captured at designated enrollment centers, blood group, height, and distinguishing physical marks.</li>
                    <li><strong>Next of Kin / Emergency Particulars:</strong> Emergency contact names, relationships, telephone numbers, and physical residential addresses.</li>
                </ul>
            </div>

            <div class="policy-section">
                <h2>2. Legal Basis &amp; Purpose of Processing</h2>
                <p>Data processing is conducted strictly pursuant to the sovereign immigration laws of the Federal Republic of Nigeria. Purposes include:</p>
                <ul>
                    <li>Evaluating expatriate eligibility for lawful residency permit issuance and statutory quota allocation.</li>
                    <li>Verifying physical identity, preventing identity fraud, and producing high-security smart residence cards.</li>
                    <li>Facilitating seamless card renewals, replacement requests, and official status lookups through your Applicant Profile.</li>
                    <li>Performing border management, national security compliance, and lawful verification by immigration officers.</li>
                </ul>
            </div>

            <div class="policy-section">
                <h2>3. Data Protection &amp; Biometric Encryption</h2>
                <p>All applicant dossiers, uploaded PDF/image credentials, and biometric templates are secured using 256-bit encryption in transit (TLS 1.3) and at rest within sovereign NIS Tier-III secure data centers. Physical biometrics are cryptographically hashed and safeguarded in conformity with ICAO standards.</p>
            </div>

            <div class="policy-section">
                <h2>4. Applicant Rights &amp; Profile Access</h2>
                <p>Under the Nigeria Data Protection Act (NDPA 2023), every registered applicant possesses statutory rights:</p>
                <ul>
                    <li><strong>Profile Access:</strong> You may sign into your Applicant Dashboard at any time to review your submitted applications, live issuance status, and permit history.</li>
                    <li><strong>Rectification:</strong> If any application particulars require modification prior to biometric capture, you can notify the designated enrollment command or update details during your renewal application.</li>
                    <li><strong>Status Tracking:</strong> Real-time transparent visibility into approval workflows, queried notices, and card collection readiness.</li>
                </ul>
            </div>

            <div class="policy-section">
                <h2>5. Inquiries &amp; Data Protection Officer</h2>
                <p>For questions regarding your applicant profile, data privacy rights, or expatriate dossier administration, please contact:</p>
                <p style="margin-top: 6px;">
                    <strong>Data Protection &amp; Legal Advisory Division</strong><br>
                    Nigeria Immigration Service Headquarters (NIS HQ)<br>
                    Airport Road, Sauka, Abuja FCT, Nigeria<br>
                    Email: <a href="mailto:privacy@immigration.gov.ng" style="color: #16a34a;">privacy@immigration.gov.ng</a> | <a href="mailto:inquiries@immigration.gov.ng" style="color: #16a34a;">inquiries@immigration.gov.ng</a>
                </p>
            </div>
        </div>
    </main>

    <?php require __DIR__ . '/includes/portal-footer.php'; ?>

</body>
</html>
