<?php
/**
 * NIS Residence Card Issuance System (NIS-RCIS)
 * Unified Public Portal Header & Navigation Bar
 * Shared across all public portal pages (index.php, apply.php, track.php, applicant-dashboard.php, etc.)
 */

if (session_status() === PHP_SESSION_NONE) {
    session_start();
}

$currentScript = preg_replace('/\.php$/', '', basename($_SERVER['PHP_SELF'] ?? ''));
$isApplicantLoggedIn = !empty($_SESSION['applicant_id']);
$applicantName = $_SESSION['applicant_name'] ?? 'Applicant';
?>
<style>
/* Unified Portal Navigation Styles */
.portal-nav {
    background: #ffffff;
    border-bottom: 1px solid #e2e8f0;
    box-shadow: 0 2px 8px rgba(0, 0, 0, 0.05);
    position: sticky;
    top: 0;
    z-index: 1000;
    width: 100%;
}

.nav-inner {
    max-width: 1200px;
    margin: 0 auto;
    padding: 10px 1.5rem;
    display: flex;
    justify-content: space-between;
    align-items: center;
    gap: 1rem;
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
    color: #113f1f;
    margin: 0;
    letter-spacing: 0.5px;
    text-transform: uppercase;
    line-height: 1.2;
}

.brand-titles h2 {
    font-size: 0.78rem;
    font-weight: 600;
    color: #d4af37;
    margin: 2px 0 0;
    letter-spacing: 0.4px;
    line-height: 1.2;
}

.nav-links {
    display: flex;
    list-style: none;
    margin: 0;
    padding: 0;
    align-items: center;
    gap: 6px;
}

.nav-links li a {
    text-decoration: none;
    color: #334155;
    font-size: 0.88rem;
    font-weight: 600;
    padding: 8px 14px;
    border-radius: 6px;
    display: inline-flex;
    align-items: center;
    gap: 6px;
    transition: all 0.2s ease;
}

.nav-links li a:hover {
    color: #113f1f;
    background: #f1f5f9;
}

.nav-links li a.active {
    color: #113f1f;
    background: #f0fdf4;
    font-weight: 700;
}

.nav-links li a.btn-applicant-profile {
    background: #113f1f;
    color: #ffffff;
    font-weight: 700;
    padding: 8px 16px;
}

.nav-links li a.btn-applicant-profile:hover {
    background: #1a5c2e;
    color: #ffffff;
}

/* Applicant Dropdown Menu */
.nav-applicant-item {
    position: relative;
}

.nav-caret {
    font-size: 0.68rem;
    margin-left: 6px;
    opacity: 0.8;
    transition: transform 0.2s ease;
}

.nav-applicant-item:hover .nav-caret {
    transform: rotate(180deg);
}

.nav-applicant-name {
    max-width: 170px;
    overflow: hidden;
    text-overflow: ellipsis;
    white-space: nowrap;
    display: inline-block;
    vertical-align: middle;
}

.nav-applicant-dropdown {
    display: none;
    position: absolute;
    top: 100%;
    right: 0;
    margin-top: 0;
    padding-top: 6px;
    background: transparent;
    border: none;
    box-shadow: none;
    z-index: 1050;
    min-width: 140px;
}

/* Hover bridge pseudo-element to prevent any hover gap/flicker */
.nav-applicant-dropdown::before {
    content: '';
    position: absolute;
    top: 0;
    left: 0;
    right: 0;
    height: 8px;
    background: transparent;
}

.nav-applicant-item:hover .nav-applicant-dropdown,
.nav-applicant-item:focus-within .nav-applicant-dropdown {
    display: block;
}

.nav-applicant-dropdown .dropdown-item,
.nav-applicant-dropdown .signout-item {
    display: flex;
    align-items: center;
    justify-content: center;
    gap: 8px;
    padding: 8px 14px;
    font-size: 0.84rem;
    font-weight: 700;
    color: #dc2626 !important;
    background: #ffffff !important;
    border: 1px solid #fee2e2 !important;
    border-radius: 6px;
    text-decoration: none !important;
    white-space: nowrap;
    box-shadow: 0 4px 14px rgba(0, 0, 0, 0.1) !important;
    transition: background-color 0.15s ease, color 0.15s ease, border-color 0.15s ease;
}

.nav-applicant-dropdown .signout-item:hover {
    background: #fef2f2 !important;
    color: #b91c1c !important;
    border-color: #fca5a5 !important;
    text-decoration: none !important;
    box-shadow: 0 4px 16px rgba(220, 38, 38, 0.12) !important;
}

/* Mobile Toggle */
.nav-mobile-toggle {
    display: none;
    background: transparent;
    border: none;
    font-size: 1.35rem;
    color: #113f1f;
    cursor: pointer;
    padding: 6px 10px;
}

@media (max-width: 900px) {
    .nav-mobile-toggle {
        display: block;
    }

    .nav-links {
        display: none;
        flex-direction: column;
        position: absolute;
        top: 100%;
        left: 0;
        right: 0;
        background: #ffffff;
        border-bottom: 1px solid #e2e8f0;
        box-shadow: 0 8px 16px rgba(0, 0, 0, 0.08);
        padding: 12px 1.5rem 16px;
        gap: 8px;
        align-items: stretch;
    }

    .nav-links.show {
        display: flex;
    }

    .nav-links li a {
        padding: 10px 14px;
        width: 100%;
    }

    .nav-applicant-dropdown {
        position: static;
        box-shadow: none;
        border: none;
        background: transparent;
        margin-top: 4px;
        display: flex;
        padding: 0;
        width: 100%;
    }
    .nav-applicant-dropdown .signout-item {
        width: 100%;
        padding: 9px 14px;
        background: transparent !important;
        border: none !important;
        box-shadow: none !important;
    }
}
</style>

<nav class="portal-nav">
    <div class="nav-inner">
        <a href="index" class="brand-link">
            <img src="assets/images/nis-crest-logo-transparent.png?v=3" alt="NIS Crest" class="brand-logo" onerror="this.src='assets/images/nis-logo.png';">
            <div class="brand-titles">
                <h1>NIS RESIDENCE CARD</h1>
                <h2>Residence Card Issuance Portal</h2>
            </div>
        </a>

        <button class="nav-mobile-toggle" type="button" aria-label="Toggle navigation" onclick="togglePortalNav()">
            <i class="fas fa-bars"></i>
        </button>

        <ul class="nav-links" id="portalNavLinks">
            <?php if (in_array($currentScript, ['index', 'applicant-login', 'privacy-policy'])): ?>
                <li>
                    <a href="index" class="<?php echo ($currentScript === 'index') ? 'active' : ''; ?>">
                        Home
                    </a>
                </li>
                <?php if ($isApplicantLoggedIn): ?>
                    <li class="nav-applicant-item">
                        <a href="applicant-dashboard" class="btn-applicant-profile <?php echo ($currentScript === 'applicant-dashboard') ? 'active' : ''; ?>" title="<?php echo h($applicantName); ?>">
                            <i class="fas fa-user-circle"></i> <span class="nav-applicant-name"><?php echo h($applicantName); ?></span> <i class="fas fa-chevron-down nav-caret"></i>
                        </a>
                        <div class="nav-applicant-dropdown">
                            <a href="applicant-logout" class="dropdown-item signout-item">
                                <i class="fas fa-sign-out-alt"></i> Sign Out
                            </a>
                        </div>
                    </li>
                <?php else: ?>
                    <li>
                        <a href="applicant-login" class="btn-applicant-profile <?php echo ($currentScript === 'applicant-login') ? 'active' : ''; ?>">
                            Sign In
                        </a>
                    </li>
                <?php endif; ?>
            <?php else: ?>
                <li>
                    <a href="index">
                        Home
                    </a>
                </li>
                <li>
                    <a href="<?php echo $isApplicantLoggedIn ? 'apply' : 'applicant-login?redirect=apply'; ?>" class="<?php echo ($currentScript === 'apply') ? 'active' : ''; ?>">
                        Apply
                    </a>
                </li>
                <li>
                    <a href="track" class="<?php echo ($currentScript === 'track' && empty($_GET['action'])) ? 'active' : ''; ?>">
                        Track Status
                    </a>
                </li>
                <li>
                    <a href="track?action=slip" class="<?php echo ($currentScript === 'track' && isset($_GET['action']) && $_GET['action'] === 'slip') ? 'active' : ''; ?>">
                        Print Slip
                    </a>
                </li>
                <?php if ($isApplicantLoggedIn): ?>
                    <li class="nav-applicant-item">
                        <a href="applicant-dashboard" class="btn-applicant-profile <?php echo ($currentScript === 'applicant-dashboard') ? 'active' : ''; ?>" title="<?php echo h($applicantName); ?>">
                            <i class="fas fa-user-circle"></i> <span class="nav-applicant-name"><?php echo h($applicantName); ?></span> <i class="fas fa-chevron-down nav-caret"></i>
                        </a>
                        <div class="nav-applicant-dropdown">
                            <a href="applicant-logout" class="dropdown-item signout-item">
                                <i class="fas fa-sign-out-alt"></i> Sign Out
                            </a>
                        </div>
                    </li>
                <?php else: ?>
                    <li>
                        <a href="applicant-login" class="btn-applicant-profile <?php echo ($currentScript === 'applicant-login') ? 'active' : ''; ?>">
                            <i class="fas fa-user"></i> Applicant Portal
                        </a>
                    </li>
                <?php endif; ?>
            <?php endif; ?>
        </ul>
    </div>
</nav>

<script>
function togglePortalNav() {
    const nav = document.getElementById('portalNavLinks');
    if (nav) {
        nav.classList.toggle('show');
    }
}
</script>
