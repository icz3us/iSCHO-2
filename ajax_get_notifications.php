<?php
/**
 * Get User Notifications API
 * Returns user notifications from Redis
 * 
 * @package iSCHO
 * @version 2.0
 */

require_once __DIR__ . '/route_guard.php';
require_once __DIR__ . '/utils/redis_notifications.php';

header('Content-Type: application/json');

if (!isset($_SESSION['user_id'])) {
    http_response_code(401);
    echo json_encode(['error' => 'Unauthorized']);
    exit;
}

$notifications = new RedisNotifications();
$unreadOnly = isset($_GET['unread_only']) && $_GET['unread_only'] === 'true';
$limit = isset($_GET['limit']) ? (int)$_GET['limit'] : 50;

$userNotifications = $notifications->getNotifications($_SESSION['user_id'], $limit, $unreadOnly);
$unreadCount = $notifications->getUnreadCount($_SESSION['user_id']);

echo json_encode([
    'success' => true,
    'notifications' => $userNotifications,
    'unread_count' => $unreadCount
]);

