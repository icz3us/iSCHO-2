<?php
/**
 * Redis Notification System
 * Real-time notifications using Redis pub/sub and storage
 * 
 * @package iSCHO
 * @version 2.0
 */

require_once __DIR__ . '/../connect/redis_connection.php';

class RedisNotifications {
    private $redis;

    public function __construct() {
        $this->redis = RedisManager::getInstance();
    }

    /**
     * Send notification to user
     * 
     * @param int $user_id User ID
     * @param string $type Notification type (e.g., 'application_status', 'document_approved')
     * @param string $message Notification message
     * @param array $data Additional data
     * @param int $ttl Time to live in seconds (default: 7 days)
     * @return bool
     */
    public function sendNotification($user_id, $type, $message, $data = [], $ttl = 604800) {
        $notification = [
            'id' => uniqid('notif_', true),
            'user_id' => $user_id,
            'type' => $type,
            'message' => $message,
            'data' => $data,
            'read' => false,
            'created_at' => time()
        ];

        // Store notification
        $key = "notifications:{$user_id}:{$notification['id']}";
        $stored = $this->redis->set($key, $notification, $ttl);

        // Add to user's notification list
        $listKey = "notifications:{$user_id}:list";
        $client = $this->redis->getClient();
        if ($client) {
            $client->lpush($listKey, $notification['id']);
            $client->expire($listKey, $ttl);
        }

        // Publish notification for real-time updates
        $this->publishNotification($user_id, $notification);

        return $stored;
    }

    /**
     * Get user notifications
     * 
     * @param int $user_id User ID
     * @param int $limit Number of notifications to retrieve
     * @param bool $unreadOnly Only get unread notifications
     * @return array
     */
    public function getNotifications($user_id, $limit = 50, $unreadOnly = false) {
        $listKey = "notifications:{$user_id}:list";
        $client = $this->redis->getClient();
        
        if (!$client) {
            return [];
        }

        // Get notification IDs
        $notificationIds = $client->lrange($listKey, 0, $limit - 1);
        
        $notifications = [];
        foreach ($notificationIds as $id) {
            $key = "notifications:{$user_id}:{$id}";
            $notification = $this->redis->get($key);
            
            if ($notification === null) {
                continue;
            }

            if ($unreadOnly && $notification['read']) {
                continue;
            }

            $notifications[] = $notification;
        }

        // Sort by created_at descending
        usort($notifications, function($a, $b) {
            return $b['created_at'] - $a['created_at'];
        });

        return $notifications;
    }

    /**
     * Mark notification as read
     * 
     * @param int $user_id User ID
     * @param string $notification_id Notification ID
     * @return bool
     */
    public function markAsRead($user_id, $notification_id) {
        $key = "notifications:{$user_id}:{$notification_id}";
        $notification = $this->redis->get($key);
        
        if ($notification === null) {
            return false;
        }

        $notification['read'] = true;
        $ttl = $this->redis->ttl($key);
        
        if ($ttl > 0) {
            return $this->redis->set($key, $notification, $ttl);
        }

        return $this->redis->set($key, $notification, 604800); // 7 days default
    }

    /**
     * Mark all notifications as read for user
     * 
     * @param int $user_id User ID
     * @return int Number of notifications marked as read
     */
    public function markAllAsRead($user_id) {
        $notifications = $this->getNotifications($user_id, 1000, true);
        $count = 0;

        foreach ($notifications as $notification) {
            if ($this->markAsRead($user_id, $notification['id'])) {
                $count++;
            }
        }

        return $count;
    }

    /**
     * Get unread notification count
     * 
     * @param int $user_id User ID
     * @return int
     */
    public function getUnreadCount($user_id) {
        $notifications = $this->getNotifications($user_id, 1000, true);
        return count($notifications);
    }

    /**
     * Delete notification
     * 
     * @param int $user_id User ID
     * @param string $notification_id Notification ID
     * @return bool
     */
    public function deleteNotification($user_id, $notification_id) {
        $key = "notifications:{$user_id}:{$notification_id}";
        $deleted = $this->redis->delete($key);

        // Remove from list
        $listKey = "notifications:{$user_id}:list";
        $client = $this->redis->getClient();
        if ($client) {
            $client->lrem($listKey, 0, $notification_id);
        }

        return $deleted;
    }

    /**
     * Publish notification for real-time updates
     * 
     * @param int $user_id User ID
     * @param array $notification Notification data
     * @return void
     */
    private function publishNotification($user_id, $notification) {
        $client = $this->redis->getClient();
        if ($client) {
            try {
                $channel = "notifications:{$user_id}";
                $client->publish($channel, json_encode($notification));
            } catch (Exception $e) {
                error_log("Notification Publish Error: " . $e->getMessage());
            }
        }
    }
}

