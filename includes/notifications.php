<?php
/**
 * NIS Residence Card Issuance System (NIS-RCIS)
 * Approval & System Notifications Engine
 */

require_once __DIR__ . '/security.php';
require_once dirname(__DIR__) . '/config/database.php';
require_once dirname(__DIR__) . '/config/constants.php';

// Notification Type Identifiers
define('NOTIF_APPROVAL_REQUEST', 'APPROVAL_REQUEST');
define('NOTIF_APPROVAL_SENT', 'APPROVAL_SENT');
define('NOTIF_APPROVAL_RECEIVED', 'APPROVAL_RECEIVED');
define('NOTIF_CARD_QUERIED', 'CARD_QUERIED');

/**
 * Create a new notification for a specific user.
 */
function createNotification(int $userId, string $type, string $title, string $message, ?string $link = null, ?PDO $pdo = null): bool {
    if ($userId <= 0) {
        return false;
    }

    try {
        if ($pdo === null) {
            $pdo = Database::getConnection();
        }

        $stmt = $pdo->prepare("INSERT INTO notifications (user_id, type, title, message, link, is_read, created_at) 
                                VALUES (:uid, :type, :title, :msg, :link, 0, CURRENT_TIMESTAMP)");
        return $stmt->execute([
            ':uid' => $userId,
            ':type' => $type,
            ':title' => $title,
            ':msg' => $message,
            ':link' => $link
        ]);
    } catch (Throwable $e) {
        error_log("Failed to create notification: " . $e->getMessage());
        return false;
    }
}

/**
 * Dispatch notification to all approving authorities (SuperAdmins & ApprovingOfficers).
 */
function notifyApprovers(string $type, string $title, string $message, ?string $link = null, ?int $excludeUserId = null, ?PDO $pdo = null): void {
    try {
        if ($pdo === null) {
            $pdo = Database::getConnection();
        }

        $sql = "SELECT id FROM users WHERE role IN ('SuperAdmin', 'ApprovingOfficer') AND is_active = 1";
        if ($excludeUserId !== null && $excludeUserId > 0) {
            $sql .= " AND id != " . (int)$excludeUserId;
        }

        $stmt = $pdo->query($sql);
        $approvers = $stmt->fetchAll(PDO::FETCH_ASSOC);

        foreach ($approvers as $appr) {
            createNotification((int)$appr['id'], $type, $title, $message, $link, $pdo);
        }
    } catch (Throwable $e) {
        error_log("Failed to notify approvers: " . $e->getMessage());
    }
}

/**
 * Get count of unread notifications for a user.
 */
function getUnreadNotificationCount(int $userId, ?PDO $pdo = null): int {
    if ($userId <= 0) {
        return 0;
    }

    try {
        if ($pdo === null) {
            $pdo = Database::getConnection();
        }

        $stmt = $pdo->prepare("SELECT COUNT(*) FROM notifications WHERE user_id = :uid AND is_read = 0");
        $stmt->execute([':uid' => $userId]);
        return (int)$stmt->fetchColumn();
    } catch (Throwable $e) {
        return 0;
    }
}

/**
 * Get recent notifications for a user (default 15).
 */
function getRecentNotifications(int $userId, int $limit = 15, ?PDO $pdo = null): array {
    if ($userId <= 0) {
        return [];
    }

    try {
        if ($pdo === null) {
            $pdo = Database::getConnection();
        }

        $limit = max(1, min(50, $limit));
        $stmt = $pdo->prepare("SELECT * FROM notifications WHERE user_id = :uid ORDER BY id DESC LIMIT {$limit}");
        $stmt->execute([':uid' => $userId]);
        return $stmt->fetchAll(PDO::FETCH_ASSOC) ?: [];
    } catch (Throwable $e) {
        return [];
    }
}

/**
 * Mark a specific notification as read.
 */
function markNotificationRead(int $notificationId, int $userId, ?PDO $pdo = null): bool {
    if ($notificationId <= 0 || $userId <= 0) {
        return false;
    }

    try {
        if ($pdo === null) {
            $pdo = Database::getConnection();
        }

        $stmt = $pdo->prepare("UPDATE notifications SET is_read = 1 WHERE id = :id AND user_id = :uid");
        return $stmt->execute([':id' => $notificationId, ':uid' => $userId]);
    } catch (Throwable $e) {
        return false;
    }
}

/**
 * Mark all notifications as read for a user.
 */
function markAllNotificationsRead(int $userId, ?PDO $pdo = null): bool {
    if ($userId <= 0) {
        return false;
    }

    try {
        if ($pdo === null) {
            $pdo = Database::getConnection();
        }

        $stmt = $pdo->prepare("UPDATE notifications SET is_read = 1 WHERE user_id = :uid AND is_read = 0");
        return $stmt->execute([':uid' => $userId]);
    } catch (Throwable $e) {
        return false;
    }
}

/**
 * Helper: Human-friendly relative timestamp.
 */
function notifTimeAgo(string $datetime): string {
    $time = strtotime($datetime);
    if (!$time) {
        return $datetime;
    }

    $diff = time() - $time;
    if ($diff < 45) {
        return 'Just now';
    }
    if ($diff < 3600) {
        $mins = max(1, (int)floor($diff / 60));
        return $mins . 'm ago';
    }
    if ($diff < 86400) {
        $hrs = (int)floor($diff / 3600);
        return $hrs . 'h ago';
    }
    if ($diff < 604800) {
        $days = (int)floor($diff / 86400);
        return $days . 'd ago';
    }

    return date('d M, h:i A', $time);
}
