<?php
/**
 * NIS Residence Card Issuance System (NIS-RCIS)
 * Responsive Navigation Sidebar Component
 */
$currentPage = preg_replace('/\.php$/', '', basename($_SERVER['PHP_SELF'] ?? ''));
$userRole = $_SESSION['role'] ?? ROLE_ISSUING_OFFICER;

// Dynamic Pending Approvals Count (Cards + Online Applications)
$pendingCount = 0;
try {
    $dbSide = Database::getConnection();
    $pendingCards = (int)$dbSide->query("SELECT COUNT(*) FROM residence_cards WHERE status = 'PENDING_APPROVAL'")->fetchColumn();
    $pendingApps = (int)$dbSide->query("SELECT COUNT(*) FROM applications WHERE status = 'PENDING_APPROVAL'")->fetchColumn();
    $pendingCount = $pendingCards + $pendingApps;
} catch (Throwable $e) {
    $pendingCount = 0;
}
?>
<!-- Mobile Overlay -->
<div class="sidebar-overlay" id="sidebarOverlay"></div>

<!-- Sidebar Drawer -->
<aside class="app-sidebar" id="appSidebar">
    <ul class="sidebar-menu">
        <li class="sidebar-heading">Core Navigation</li>
        
        <li class="sidebar-item">
            <a href="dashboard" class="sidebar-link <?php echo ($currentPage === 'dashboard') ? 'active' : ''; ?>">
                <i class="fas fa-home"></i>
                <span class="sidebar-text">Dashboard</span>
            </a>
        </li>

        <li class="sidebar-heading">Residence Card Operations</li>

        <?php if ($userRole !== ROLE_AUDITOR && $userRole !== ROLE_INSPECTOR): ?>
        <li class="sidebar-item">
            <a href="biometrics-capture" class="sidebar-link <?php echo ($currentPage === 'biometrics-capture') ? 'active' : ''; ?>">
                <i class="fas fa-fingerprint"></i>
                <span class="sidebar-text">Biometrics Desk</span>
            </a>
        </li>
        <?php endif; ?>

        <li class="sidebar-item">
            <a href="pending-approvals" class="sidebar-link <?php echo ($currentPage === 'pending-approvals') ? 'active' : ''; ?>">
                <i class="fas fa-clipboard-check"></i>
                <span class="sidebar-text">Pending Approvals</span>
                <?php if ($pendingCount > 0): ?>
                    <span class="badge" style="margin-left: auto; background: var(--nis-gold); color: #082010; font-size: 0.72rem; padding: 2px 7px; border-radius: 10px; font-weight: 800;"><?php echo $pendingCount; ?></span>
                <?php endif; ?>
            </a>
        </li>

        <li class="sidebar-item">
            <a href="view-cards" class="sidebar-link <?php echo ($currentPage === 'view-cards' || $currentPage === 'card-details') ? 'active' : ''; ?>">
                <i class="fas fa-id-card"></i>
                <span class="sidebar-text">All Residence Cards</span>
            </a>
        </li>

        <?php if ($userRole !== ROLE_AUDITOR && $userRole !== ROLE_INSPECTOR): ?>
        <li class="sidebar-item">
            <a href="renew-card" class="sidebar-link <?php echo ($currentPage === 'renew-card') ? 'active' : ''; ?>">
                <i class="fas fa-sync-alt"></i>
                <span class="sidebar-text">Card Renewals</span>
            </a>
        </li>
        <?php endif; ?>

        <li class="sidebar-heading">Reports &amp; Intelligence</li>

        <li class="sidebar-item">
            <a href="reports" class="sidebar-link <?php echo ($currentPage === 'reports') ? 'active' : ''; ?>">
                <i class="fas fa-chart-bar"></i>
                <span class="sidebar-text">Reports &amp; Analytics</span>
            </a>
        </li>

        <li class="sidebar-heading">Administration</li>

        <li class="sidebar-item">
            <a href="profile" class="sidebar-link <?php echo ($currentPage === 'profile') ? 'active' : ''; ?>">
                <i class="fas fa-user-circle"></i>
                <span class="sidebar-text">Profile</span>
            </a>
        </li>

        <?php if ($userRole === ROLE_SUPER_ADMIN): ?>
        <li class="sidebar-item">
            <a href="users" class="sidebar-link <?php echo ($currentPage === 'users') ? 'active' : ''; ?>">
                <i class="fas fa-user-shield"></i>
                <span class="sidebar-text">Account Management</span>
            </a>
        </li>

        <li class="sidebar-item">
            <a href="audit-logs" class="sidebar-link <?php echo ($currentPage === 'audit-logs') ? 'active' : ''; ?>">
                <i class="fas fa-shield-alt"></i>
                <span class="sidebar-text">Security Audit Logs</span>
            </a>
        </li>
        <?php endif; ?>
    </ul>

    <div class="sidebar-footer">
        <div><strong>NIS-RCIS v<?php echo APP_VERSION; ?></strong></div>
        <div style="font-size: 0.7rem; margin-top: 4px;">Residence Card Issuance System</div>
    </div>
</aside>

<div class="main-wrapper">
    <div class="content-container">
        <?php 
        // Render any active flash notifications
        $flash = getFlash();
        if ($flash): 
        ?>
        <div class="alert alert-<?php echo h($flash['type']); ?> alert-dismissible">
            <i class="fas fa-info-circle"></i>
            <div><?php echo $flash['message']; ?></div>
        </div>
        <?php endif; ?>
