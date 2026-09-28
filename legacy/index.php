<?php
/**
 * NIS Residence Card Issuance System (NIS-RCIS)
 * Public-Facing e-Services Portal Homepage
 * Modeled after Nigeria Immigration Service Passport Application Architecture
 */

require_once __DIR__ . '/includes/security.php';
require_once __DIR__ . '/config/database.php';
require_once __DIR__ . '/config/constants.php';
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0, maximum-scale=5.0">
    <title>Nigeria Immigration Service — Residence Card Portal (NIS-RCIS)</title>
    <!-- Favicon / URL Icon -->
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

        body {
            background-color: var(--nis-bg);
            color: var(--nis-slate);
            line-height: 1.6;
        }



        /* Hero Banner with Official NIS Image (Translucent Overlay) */
        .hero-section {
            background: #0c2b16;
            color: #ffffff;
            padding: 5.5rem 2rem 5rem;
            text-align: center;
            position: relative;
            border-bottom: none;
            overflow: hidden;
        }

        .hero-section::before {
            content: "";
            position: absolute;
            top: 0;
            left: 0;
            right: 0;
            bottom: 0;
            background: url('assets/images/nis-hq-interior.jpg') center/cover no-repeat;
            opacity: 0.35;
            z-index: 1;
        }

        .hero-content {
            max-width: 880px;
            margin: 0 auto;
            position: relative;
            z-index: 2;
        }

        .hero-title {
            font-size: 2.65rem;
            font-weight: 800;
            line-height: 1.25;
            margin-bottom: 1.25rem;
            color: #ffffff;
            letter-spacing: -0.5px;
            text-shadow: 0 2px 10px rgba(0, 0, 0, 0.4);
        }

        .hero-subtitle {
            font-size: 1.08rem;
            color: #f1f5f9;
            line-height: 1.7;
            margin-bottom: 2.5rem;
            max-width: 760px;
            margin-left: auto;
            margin-right: auto;
            text-shadow: 0 1px 4px rgba(0, 0, 0, 0.3);
        }

        .hero-actions {
            display: flex;
            justify-content: center;
            gap: 1.1rem;
            flex-wrap: wrap;
        }

        .btn-hero-primary {
            background: var(--nis-gold);
            color: #0d381c;
            font-weight: 700;
            font-size: 0.96rem;
            padding: 14px 30px;
            border-radius: 6px;
            text-decoration: none;
            display: inline-flex;
            align-items: center;
            gap: 9px;
            box-shadow: 0 4px 14px rgba(0, 0, 0, 0.3);
            transition: all 0.2s ease;
            border: none;
        }

        .btn-hero-primary:hover {
            background: #ffd700;
            transform: translateY(-2px);
            box-shadow: 0 6px 20px rgba(212, 175, 55, 0.4);
        }

        .btn-hero-secondary {
            background: rgba(255, 255, 255, 0.15);
            color: #ffffff;
            font-weight: 600;
            font-size: 0.96rem;
            padding: 13px 28px;
            border-radius: 6px;
            text-decoration: none;
            display: inline-flex;
            align-items: center;
            gap: 9px;
            border: 1.5px solid rgba(255, 255, 255, 0.65);
            backdrop-filter: blur(6px);
            box-shadow: 0 2px 8px rgba(0, 0, 0, 0.15);
            transition: all 0.2s ease;
        }

        .btn-hero-secondary:hover {
            background: #ffffff;
            color: #0d381c;
            border-color: #ffffff;
            transform: translateY(-2px);
        }

        /* Main Container */
        .main-container {
            max-width: 1200px;
            margin: 3.5rem auto 4rem;
            padding: 0 1.5rem;
            position: relative;
            z-index: 10;
        }

        /* 4 Feature Service Cards */
        .cards-grid {
            display: grid;
            grid-template-columns: repeat(auto-fit, minmax(260px, 1fr));
            gap: 1.5rem;
            margin-bottom: 3.5rem;
        }

        .service-card {
            background: #ffffff;
            border-radius: 8px;
            padding: 2rem 1.5rem;
            border: none !important;
            box-shadow: 0 6px 18px rgba(0, 0, 0, 0.06);
            display: flex;
            flex-direction: column;
            align-items: flex-start;
            transition: transform 0.2s ease, box-shadow 0.2s ease;
            position: relative;
        }

        .service-card:hover {
            transform: translateY(-4px);
            box-shadow: 0 12px 26px rgba(0, 0, 0, 0.1);
        }

        .service-card.gold-accent {
            border: none !important;
        }

        .service-icon {
            width: 52px;
            height: 52px;
            border-radius: 8px;
            background: rgba(26, 92, 46, 0.1);
            color: var(--nis-green);
            display: flex;
            align-items: center;
            justify-content: center;
            font-size: 1.45rem;
            margin-bottom: 1.25rem;
        }

        .service-card.gold-accent .service-icon {
            background: rgba(212, 175, 55, 0.15);
            color: var(--nis-gold-dark);
        }

        .service-card h3 {
            font-size: 1.15rem;
            color: var(--nis-slate);
            font-weight: 700;
            margin-bottom: 0.6rem;
        }

        .service-card p {
            font-size: 0.86rem;
            color: var(--nis-muted);
            margin-bottom: 1.5rem;
            flex-grow: 1;
            line-height: 1.55;
        }

        .service-btn {
            font-size: 0.85rem;
            font-weight: 700;
            text-decoration: none;
            color: var(--nis-green);
            display: inline-flex;
            align-items: center;
            gap: 6px;
            transition: gap 0.2s;
        }

        .service-btn:hover {
            color: var(--nis-green-dark);
            gap: 10px;
        }

        /* How It Works Section */
        .workflow-section {
            background: #ffffff;
            border-radius: 10px;
            padding: 3rem 2.5rem;
            border: none !important;
            box-shadow: 0 4px 15px rgba(0, 0, 0, 0.04);
            margin-bottom: 3.5rem;
        }

        .section-header {
            text-align: center;
            margin-bottom: 2.75rem;
        }

        .section-tag {
            font-size: 0.76rem;
            font-weight: 700;
            letter-spacing: 1.2px;
            color: var(--nis-green);
            text-transform: uppercase;
            margin-bottom: 0.35rem;
        }

        .section-title {
            font-size: 1.85rem;
            font-weight: 800;
            color: var(--nis-slate);
        }

        .workflow-steps {
            display: grid;
            grid-template-columns: repeat(auto-fit, minmax(200px, 1fr));
            gap: 1.5rem;
            position: relative;
        }

        .step-box {
            text-align: center;
            position: relative;
            padding: 1rem;
        }

        .step-number {
            width: 44px;
            height: 44px;
            border-radius: 50%;
            background: linear-gradient(135deg, var(--nis-green), var(--nis-green-dark));
            color: #ffffff;
            font-weight: 800;
            font-size: 1.1rem;
            display: flex;
            align-items: center;
            justify-content: center;
            margin: 0 auto 1rem;
            border: 3px solid #ffffff;
            box-shadow: 0 3px 10px rgba(26, 92, 46, 0.25);
        }

        .step-box h4 {
            font-size: 1rem;
            font-weight: 700;
            color: var(--nis-slate);
            margin-bottom: 0.5rem;
        }

        .step-box p {
            font-size: 0.82rem;
            color: var(--nis-muted);
            line-height: 1.5;
        }

        /* Fast Status Lookup Banner (Light Green Theme & Clean Alignment) */
        .lookup-banner {
            background: #eef9f1;
            border-radius: 12px;
            padding: 2.25rem 2.5rem;
            color: #113f1f;
            display: flex;
            flex-direction: column;
            gap: 1.25rem;
            box-shadow: 0 6px 22px rgba(22, 101, 52, 0.08);
            border: none !important;
            margin-bottom: 2.5rem;
        }

        .lookup-text h3 {
            font-size: 1.4rem;
            font-weight: 800;
            margin-bottom: 0.35rem;
            color: #113f1f;
        }

        .lookup-text p {
            font-size: 0.9rem;
            color: #334155;
            margin: 0;
            line-height: 1.5;
        }

        .lookup-form {
            display: grid;
            grid-template-columns: 1fr 1fr auto;
            gap: 14px;
            align-items: end;
            width: 100%;
        }

        .lookup-field {
            display: flex;
            flex-direction: column;
            gap: 5px;
        }

        .lookup-label {
            font-size: 0.78rem;
            font-weight: 700;
            color: #166534;
            text-transform: uppercase;
            letter-spacing: 0.5px;
        }

        .lookup-input {
            width: 100%;
            padding: 12px 15px;
            border-radius: 6px;
            border: 1.5px solid #86efac;
            background: #ffffff;
            color: #0f172a;
            font-size: 0.9rem;
            font-weight: 600;
            outline: none;
            text-transform: uppercase;
            transition: border-color 0.2s, box-shadow 0.2s;
            height: 44px;
        }

        .lookup-input:focus {
            border-color: #16a34a;
            box-shadow: 0 0 0 3px rgba(22, 163, 74, 0.2);
        }

        .lookup-action {
            display: flex;
        }

        .lookup-btn {
            background: #155724;
            color: #ffffff;
            border: none;
            font-weight: 700;
            font-size: 0.9rem;
            padding: 0 28px;
            border-radius: 6px;
            cursor: pointer;
            display: inline-flex;
            align-items: center;
            justify-content: center;
            gap: 8px;
            box-shadow: 0 3px 10px rgba(21, 87, 36, 0.2);
            transition: all 0.2s;
            white-space: nowrap;
            height: 44px;
        }

        .lookup-btn:hover {
            background: #0f3d19;
            transform: translateY(-1px);
        }

        @media (max-width: 768px) {
            .lookup-form {
                grid-template-columns: 1fr;
            }
            .lookup-btn {
                width: 100%;
            }
        }

        /* Footer */
        .portal-footer {
            background: #082010;
            color: #94a3b8;
            padding: 3rem 2rem 1.5rem;
            font-size: 0.84rem;
            border-top: none;
        }

        .footer-inner {
            max-width: 1200px;
            margin: 0 auto;
            display: grid;
            grid-template-columns: repeat(auto-fit, minmax(240px, 1fr));
            gap: 2.5rem;
            margin-bottom: 2.5rem;
        }

        .footer-col h4 {
            color: #ffffff;
            font-size: 0.95rem;
            font-weight: 700;
            margin-bottom: 1rem;
            text-transform: uppercase;
            letter-spacing: 0.5px;
        }

        .footer-col ul {
            list-style: none;
        }

        .footer-col ul li {
            margin-bottom: 0.5rem;
        }

        .footer-col ul li a {
            color: #94a3b8;
            text-decoration: none;
            transition: color 0.2s;
        }

        .footer-col ul li a:hover {
            color: var(--nis-gold);
        }

        .footer-bottom {
            max-width: 1200px;
            margin: 0 auto;
            padding-top: 1.5rem;
            border-top: none;
            display: flex;
            justify-content: space-between;
            align-items: center;
            flex-wrap: wrap;
            gap: 1rem;
            font-size: 0.78rem;
        }

        @media (max-width: 768px) {
            .hero-title { font-size: 1.85rem; }
            .lookup-banner { flex-direction: column; align-items: stretch; }
            .lookup-form { max-width: 100%; }
        }
    </style>
</head>
<body>

    <!-- Main Navigation Bar -->
    <?php require __DIR__ . '/includes/portal-header.php'; ?>

    <!-- Hero Section -->
    <section class="hero-section">
        <div class="hero-content">
            <h1 class="hero-title">Nigeria Residence Card Portal</h1>
            <p class="hero-subtitle">
                The official portal for foreign nationals residing in Nigeria. Submit your residency application, upload documents, pay processing fees, and schedule an appointment for biometrics capturing.
            </p>
            <?php 
            $applyUrl = !empty($_SESSION['applicant_id']) ? 'apply' : 'applicant-login?redirect=apply'; 
            $renewUrl = !empty($_SESSION['applicant_id']) ? 'apply?type=renewal' : 'applicant-login?redirect=' . urlencode('apply?type=renewal'); 
            ?>
            <div class="hero-actions">
                <a href="applicant-login" class="btn-hero-primary">
                    <i class="fas fa-file-alt"></i> Apply for Residence Card
                </a>
            </div>
        </div>
    </section>

    <!-- Main Content Area -->
    <main class="main-container">
        <!-- 4 Action Cards -->
        <div class="cards-grid">
            <div class="service-card">
                <div class="service-icon"><i class="fas fa-id-card"></i></div>
                <h3>New Application</h3>
                <p>Apply online for a residence card with your valid travel passport and required supporting documents.</p>
                <a href="<?php echo $applyUrl; ?>" class="service-btn">Apply Now <i class="fas fa-arrow-right"></i></a>
            </div>

            <div class="service-card gold-accent">
                <div class="service-icon"><i class="fas fa-tasks"></i></div>
                <h3>Track Application</h3>
                <p>Check the status of your submitted application from officer review to biometrics and card collection.</p>
                <a href="track" class="service-btn">Check Status <i class="fas fa-arrow-right"></i></a>
            </div>

            <div class="service-card">
                <div class="service-icon"><i class="fas fa-print"></i></div>
                <h3>Print Appointment Slip</h3>
                <p>Download and print your approved appointment slip to present at your designated enrollment center.</p>
                <a href="track" class="service-btn">Reprint Slip <i class="fas fa-arrow-right"></i></a>
            </div>

            <div class="service-card gold-accent">
                <div class="service-icon"><i class="fas fa-sync-alt"></i></div>
                <h3>Renewal &amp; Extension</h3>
                <p>Apply to renew an existing residence card approaching expiration or update your residency details.</p>
                <a href="<?php echo $renewUrl; ?>" class="service-btn">Renew Card <i class="fas fa-arrow-right"></i></a>
            </div>
        </div>

        <!-- Workflow Section -->
        <section class="workflow-section">
            <div class="section-header">
                <div class="section-tag">How It Works</div>
                <h2 class="section-title">Application Process</h2>
            </div>

            <div class="workflow-steps">
                <div class="step-box">
                    <div class="step-number">1</div>
                    <h4>Apply &amp; Upload</h4>
                    <p>Complete your personal and passport particulars and upload required supporting documents.</p>
                </div>

                <div class="step-box">
                    <div class="step-number">2</div>
                    <h4>Pay &amp; Schedule</h4>
                    <p>Pay the application fee online and select your preferred biometrics appointment date.</p>
                </div>

                <div class="step-box">
                    <div class="step-number">3</div>
                    <h4>Officer Review</h4>
                    <p>Your application and submitted documents are reviewed by an NIS approving officer.</p>
                </div>

                <div class="step-box">
                    <div class="step-number">4</div>
                    <h4>Biometrics Capturing</h4>
                    <p>Attend your appointment at the designated enrollment center for fingerprint and facial capture.</p>
                </div>

                <div class="step-box">
                    <div class="step-number">5</div>
                    <h4>Card Collection</h4>
                    <p>Receive an alert when your card is ready for collection at your enrollment center.</p>
                </div>
            </div>
        </section>

        <!-- Fast Track Lookup Form -->
        <div class="lookup-banner">
            <div class="lookup-text">
                <h3>Track Your Application Status</h3>
                <p>Enter your Application ID (e.g. RC-2026-XXXXXX) and Passport Number to track progress or download your approved slip.</p>
            </div>
            <form action="track" method="GET" class="lookup-form">
                <div class="lookup-field">
                    <label class="lookup-label" for="lookup_app_num">Application ID</label>
                    <input type="text" id="lookup_app_num" name="app_num" class="lookup-input" placeholder="Application ID (RC-...)" required autocomplete="off">
                </div>
                <div class="lookup-field">
                    <label class="lookup-label" for="lookup_passport">Passport Number</label>
                    <input type="text" id="lookup_passport" name="passport" class="lookup-input" placeholder="Passport Number" required autocomplete="off">
                </div>
                <div class="lookup-action">
                    <button type="submit" class="lookup-btn">Track Status</button>
                </div>
            </form>
        </div>
    </main>

    <?php require_once __DIR__ . '/includes/portal-footer.php'; ?>

</body>
</html>
