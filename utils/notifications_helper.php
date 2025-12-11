<?php
/**
 * Notification Helper Functions
 * Convenience functions for sending notifications
 * 
 * @package iSCHO
 * @version 2.0
 */

require_once __DIR__ . '/redis_notifications.php';

/**
 * Send notification to user
 * 
 * @param int $user_id User ID
 * @param string $type Notification type
 * @param string $message Notification message
 * @param array $data Additional data
 * @return bool
 */
function sendUserNotification($user_id, $type, $message, $data = []) {
    $notifications = new RedisNotifications();
    return $notifications->sendNotification($user_id, $type, $message, $data);
}

/**
 * Get user notifications
 * 
 * @param int $user_id User ID
 * @param int $limit Number of notifications
 * @param bool $unreadOnly Only get unread
 * @return array
 */
function getUserNotifications($user_id, $limit = 50, $unreadOnly = false) {
    $notifications = new RedisNotifications();
    return $notifications->getNotifications($user_id, $limit, $unreadOnly);
}

/**
 * Get unread notification count
 * 
 * @param int $user_id User ID
 * @return int
 */
function getUnreadNotificationCount($user_id) {
    $notifications = new RedisNotifications();
    return $notifications->getUnreadCount($user_id);
}

