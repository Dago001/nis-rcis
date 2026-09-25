<?php
/**
 * NIS Residence Card Issuance System (NIS-RCIS)
 * Notifications API Endpoint (AJAX & Direct Redirects)
 */

require_once __DIR__ . '/includes/auth.php';
require_once __DIR__ . '/includes/notifications.php';

$user = currentUser();
$userId = (int)$user['id'];
$action = trim($_GET['action'] ?? ($_POST['action'] ?? 'fetch'));

// Handle direct navigation click: mark as read and redirect to target URL
if ($action === 'go') {
    $notifId = (int)($_GET['id'] ?? 0);
    $targetUrl = 'dashboard';

    if ($notifId > 0) {
        $db = Database::getConnection();
        $stmt = $db->prepare("SELECT link FROM notifications WHERE id = :id AND user_id = :uid LIMIT 1");
        $stmt->execute([':id' => $notifId, ':uid' => $userId]);
        $row = $stmt->fetch(PDO::FETCH_ASSOC);
        if ($row && !empty($row['link'])) {
            $targetUrl = $row['link'];
        }
        markNotificationRead($notifId, $userId, $db);
    }

    header("Location: " . $targetUrl);
    exit();
}

// All other actions return JSON
header('Content-Type: application/json; charset=UTF-8');

if ($action === 'mark_all_read') {
    $success = markAllNotificationsRead($userId);
    echo json_encode(['success' => $success]);
    exit();
}

if ($action === 'mark_read') {
    $notifId = (int)($_POST['id'] ?? ($_GET['id'] ?? 0));
    $success = markNotificationRead($notifId, $userId);
    echo json_encode(['success' => $success]);
    exit();
}

// Default: fetch latest notifications and unread count
$unreadCount = getUnreadNotificationCount($userId);
$rawNotifs = getRecentNotifications($userId, 15);

$notifications = [];
foreach ($rawNotifs as $n) {
    $notifications[] = [
        'id' => (int)$n['id'],
        'type' => $n['type'],
        'title' => $n['title'],
        'message' => $n['message'],
        'link' => $n['link'] ?: '#',
        'is_read' => (int)$n['is_read'],
        'created_at' => $n['created_at'],
        'time_ago' => notifTimeAgo($n['created_at'])
    ];
}

echo json_encode([
    'success' => true,
    'unread_count' => $unreadCount,
    'notifications' => $notifications
]);
exit();
