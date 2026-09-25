<?php
/**
 * NIS Residence Card Issuance System (NIS-RCIS)
 * Top Header Navigation Component
 */
require_once __DIR__ . '/auth.php';
require_once __DIR__ . '/notifications.php';
$user = currentUser();
$headerUnreadCount = getUnreadNotificationCount((int)$user['id']);
$headerRecentNotifs = getRecentNotifications((int)$user['id'], 10);
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta http-equiv="X-UA-Compatible" content="IE=edge">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title><?php echo isset($pageTitle) ? h($pageTitle) . ' | ' : ''; ?><?php echo APP_FULL_NAME; ?></title>
    
    <!-- Favicon / URL Icon -->
    <link rel="icon" type="image/png" href="assets/images/nis-crest-logo-transparent.png?v=3">
    <link rel="icon" type="image/png" sizes="32x32" href="assets/images/favicon-32x32.png?v=3">
    <link rel="icon" type="image/png" sizes="16x16" href="assets/images/favicon-16x16.png?v=3">
    <link rel="shortcut icon" href="assets/images/favicon.ico?v=3" type="image/x-icon">
    <link rel="apple-touch-icon" href="assets/images/nis-crest-logo-transparent.png?v=3">
    
    <!-- Font Awesome -->
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css">
    
    <!-- Core & Print Stylesheets -->
    <link rel="stylesheet" href="assets/css/style.css?v=<?php echo file_exists(__DIR__ . '/../assets/css/style.css') ? filemtime(__DIR__ . '/../assets/css/style.css') : time(); ?>">
    <link rel="stylesheet" href="assets/css/print-card.css?v=<?php echo file_exists(__DIR__ . '/../assets/css/print-card.css') ? filemtime(__DIR__ . '/../assets/css/print-card.css') : time(); ?>">
</head>
<body>

<!-- Header Navigation Bar -->
<header class="app-header">
    <div class="header-left">
        <a href="dashboard" class="brand-container">
            <img src="assets/images/nis-crest-logo-transparent.png" alt="NIS Official Logo" class="brand-logo" onerror="this.src='assets/images/nis-logo.png';">
            <div class="brand-text">
                <h1>NIS RESIDENCE CARD</h1>
                <h2>Residence Card Issuance Portal</h2>
            </div>
        </a>
    </div>

    <div class="header-center">
    </div>

    <div class="header-right">
        <!-- Notification Bell Dropdown -->
        <div class="notification-dropdown" id="notificationDropdown">
            <button class="notification-trigger" id="notificationTrigger" type="button" title="Approval Notifications" aria-label="Notifications">
                <i class="fas fa-bell"></i>
                <span class="notification-badge" id="notificationBadge" <?php echo $headerUnreadCount > 0 ? '' : 'style="display: none;"'; ?>>
                    <?php echo $headerUnreadCount > 99 ? '99+' : $headerUnreadCount; ?>
                </span>
            </button>

            <div class="notification-menu" id="notificationMenu">
                <div class="notification-menu-header">
                    <div class="notification-menu-title">
                        <i class="fas fa-bell" style="color: var(--nis-gold);"></i>
                        <span>Notifications</span>
                        <span class="notification-header-count" id="notifHeaderCount">
                            <?php echo $headerUnreadCount > 0 ? "({$headerUnreadCount} new)" : ''; ?>
                        </span>
                    </div>
                    <?php if ($headerUnreadCount > 0): ?>
                        <button type="button" class="mark-all-read-btn" id="markAllReadBtn" title="Mark all as read">
                            <i class="fas fa-check-double"></i> Mark all read
                        </button>
                    <?php endif; ?>
                </div>

                <div class="notification-list" id="notificationList">
                    <?php if (empty($headerRecentNotifs)): ?>
                        <div class="notification-empty" id="notifEmptyState">
                            <div class="empty-icon"><i class="far fa-bell-slash"></i></div>
                            <div class="empty-text">No notifications yet</div>
                            <div class="empty-sub">You will be notified when approvals are sent or received</div>
                        </div>
                    <?php else: ?>
                        <?php foreach ($headerRecentNotifs as $notif): 
                            $isUnread = (int)$notif['is_read'] === 0;
                            $iconClass = 'fa-info-circle';
                            $iconColor = '#3b82f6';
                            $bgTint = '#eff6ff';

                            switch ($notif['type']) {
                                case 'APPROVAL_RECEIVED':
                                    $iconClass = 'fa-check-circle';
                                    $iconColor = '#10b981';
                                    $bgTint = '#ecfdf5';
                                    break;
                                case 'APPROVAL_REQUEST':
                                    $iconClass = 'fa-file-signature';
                                    $iconColor = '#f59e0b';
                                    $bgTint = '#fffbeb';
                                    break;
                                case 'APPROVAL_SENT':
                                    $iconClass = 'fa-paper-plane';
                                    $iconColor = '#0284c7';
                                    $bgTint = '#f0f9ff';
                                    break;
                                case 'CARD_QUERIED':
                                    $iconClass = 'fa-exclamation-triangle';
                                    $iconColor = '#ef4444';
                                    $bgTint = '#fef2f2';
                                    break;
                            }
                        ?>
                            <a href="notifications-api?action=go&id=<?php echo (int)$notif['id']; ?>" 
                               class="notification-item <?php echo $isUnread ? 'unread' : ''; ?>" 
                               data-notif-id="<?php echo (int)$notif['id']; ?>">
                                <div class="notification-icon" style="color: <?php echo $iconColor; ?>; background: <?php echo $bgTint; ?>;">
                                    <i class="fas <?php echo $iconClass; ?>"></i>
                                </div>
                                <div class="notification-content">
                                    <div class="notification-item-title">
                                        <span><?php echo h($notif['title']); ?></span>
                                        <?php if ($isUnread): ?>
                                            <span class="unread-dot"></span>
                                        <?php endif; ?>
                                    </div>
                                    <div class="notification-item-msg"><?php echo h($notif['message']); ?></div>
                                    <div class="notification-item-time">
                                        <i class="far fa-clock"></i> <?php echo notifTimeAgo($notif['created_at']); ?>
                                    </div>
                                </div>
                            </a>
                        <?php endforeach; ?>
                    <?php endif; ?>
                </div>

                <div class="notification-menu-footer">
                    <?php if ($user['role'] === ROLE_SUPER_ADMIN || $user['role'] === ROLE_APPROVING_OFFICER): ?>
                        <a href="pending-approvals">
                            <span>Pending Approvals Queue</span>
                            <i class="fas fa-arrow-right"></i>
                        </a>
                    <?php else: ?>
                        <a href="view-cards">
                            <span>View My Records</span>
                            <i class="fas fa-arrow-right"></i>
                        </a>
                    <?php endif; ?>
                </div>
            </div>
        </div>

        <!-- User Profile Dropdown -->
        <div class="user-dropdown">
            <button class="profile-trigger" id="profileTrigger" type="button">
                <div class="user-avatar-circle" style="overflow: hidden;">
                    <?php if (!empty($user['photo_path']) && file_exists(__DIR__ . '/../' . $user['photo_path'])): ?>
                        <img src="<?php echo h($user['photo_path']); ?>" alt="Avatar" style="width: 100%; height: 100%; object-fit: cover; border-radius: 50%;">
                    <?php else: ?>
                        <?php echo strtoupper(substr($user['fullname'], 0, 1)); ?>
                    <?php endif; ?>
                </div>
                <span class="officer-name"><?php echo h($user['fullname']); ?></span>
                <i class="fas fa-chevron-down" style="font-size: 0.75rem;"></i>
            </button>

            <div class="dropdown-menu" id="userDropdownMenu">
                <div class="dropdown-header">
                    <div class="user-name"><?php echo h($user['fullname']); ?></div>
                    <div class="user-role"><?php echo h($user['role']); ?> • <?php echo h($user['service_number']); ?></div>
                </div>
                <a href="profile" class="dropdown-item">
                    <i class="fas fa-user-circle"></i> Profile
                </a>
                <div class="dropdown-divider"></div>
                <a href="logout" class="dropdown-item text-danger" data-confirm="Are you sure you wish to log out from NIS-RCIS?">
                    <i class="fas fa-sign-out-alt"></i> Logout
                </a>
            </div>
        </div>
    </div>
</header>
