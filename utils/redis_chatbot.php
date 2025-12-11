<?php
/**
 * Redis Chatbot Conversation Storage
 * Stores chatbot conversations in Redis
 * 
 * @package iSCHO
 * @version 2.0
 */

require_once __DIR__ . '/../connect/redis_connection.php';

class RedisChatbot {
    private $redis;
    private $defaultTTL = 86400; // 24 hours default

    public function __construct($defaultTTL = 86400) {
        $this->redis = RedisManager::getInstance();
        $this->defaultTTL = $defaultTTL;
    }

    /**
     * Store conversation message
     * 
     * @param int $user_id User ID (0 for anonymous)
     * @param string $session_id Session ID
     * @param string $role Message role ('user' or 'assistant')
     * @param string $message Message content
     * @param array $metadata Additional metadata
     * @return bool
     */
    public function addMessage($user_id, $session_id, $role, $message, $metadata = []) {
        $messageData = [
            'id' => uniqid('msg_', true),
            'user_id' => $user_id,
            'session_id' => $session_id,
            'role' => $role,
            'message' => $message,
            'metadata' => $metadata,
            'timestamp' => time()
        ];

        // Store message
        $messageKey = "chatbot:{$session_id}:messages:{$messageData['id']}";
        $this->redis->set($messageKey, $messageData, $this->defaultTTL);

        // Add to conversation list
        $listKey = "chatbot:{$session_id}:list";
        $client = $this->redis->getClient();
        if ($client) {
            $client->lpush($listKey, $messageData['id']);
            $client->expire($listKey, $this->defaultTTL);
        }

        // Update session info
        $this->updateSession($user_id, $session_id);

        return true;
    }

    /**
     * Get conversation history
     * 
     * @param string $session_id Session ID
     * @param int $limit Number of messages to retrieve
     * @return array
     */
    public function getConversation($session_id, $limit = 50) {
        $listKey = "chatbot:{$session_id}:list";
        $client = $this->redis->getClient();
        
        if (!$client) {
            return [];
        }

        // Get message IDs
        $messageIds = $client->lrange($listKey, 0, $limit - 1);
        
        $messages = [];
        foreach ($messageIds as $id) {
            $messageKey = "chatbot:{$session_id}:messages:{$id}";
            $message = $this->redis->get($messageKey);
            
            if ($message !== null) {
                $messages[] = $message;
            }
        }

        // Sort by timestamp ascending (oldest first)
        usort($messages, function($a, $b) {
            return $a['timestamp'] - $b['timestamp'];
        });

        return $messages;
    }

    /**
     * Get conversation context for AI (last N messages)
     * 
     * @param string $session_id Session ID
     * @param int $limit Number of recent messages (default: 10)
     * @return string Formatted context string
     */
    public function getContext($session_id, $limit = 10) {
        $messages = $this->getConversation($session_id, $limit);
        
        $context = [];
        foreach ($messages as $msg) {
            $role = $msg['role'] === 'user' ? 'User' : 'Assistant';
            $context[] = "{$role}: {$msg['message']}";
        }

        return implode("\n", $context);
    }

    /**
     * Update session information
     * 
     * @param int $user_id User ID
     * @param string $session_id Session ID
     * @return bool
     */
    private function updateSession($user_id, $session_id) {
        $sessionKey = "chatbot:{$session_id}:info";
        $sessionData = $this->redis->get($sessionKey);
        
        if ($sessionData === null) {
            $sessionData = [
                'user_id' => $user_id,
                'session_id' => $session_id,
                'created_at' => time(),
                'last_activity' => time(),
                'message_count' => 0
            ];
        }

        $sessionData['last_activity'] = time();
        $sessionData['message_count'] = ($sessionData['message_count'] ?? 0) + 1;

        return $this->redis->set($sessionKey, $sessionData, $this->defaultTTL);
    }

    /**
     * Get session information
     * 
     * @param string $session_id Session ID
     * @return array|null
     */
    public function getSession($session_id) {
        $sessionKey = "chatbot:{$session_id}:info";
        return $this->redis->get($sessionKey);
    }

    /**
     * Clear conversation for session
     * 
     * @param string $session_id Session ID
     * @return bool
     */
    public function clearConversation($session_id) {
        $listKey = "chatbot:{$session_id}:list";
        $client = $this->redis->getClient();
        
        if (!$client) {
            return false;
        }

        // Get all message IDs
        $messageIds = $client->lrange($listKey, 0, -1);
        
        // Delete all messages
        foreach ($messageIds as $id) {
            $messageKey = "chatbot:{$session_id}:messages:{$id}";
            $this->redis->delete($messageKey);
        }

        // Delete list and session info
        $this->redis->delete($listKey);
        $this->redis->delete("chatbot:{$session_id}:info");

        return true;
    }

    /**
     * Get all sessions for a user
     * 
     * @param int $user_id User ID
     * @return array
     */
    public function getUserSessions($user_id) {
        $pattern = "chatbot:*:info";
        $keys = $this->redis->keys($pattern);
        
        $sessions = [];
        foreach ($keys as $key) {
            $session = $this->redis->get($key);
            if ($session && isset($session['user_id']) && $session['user_id'] == $user_id) {
                $sessions[] = $session;
            }
        }

        return $sessions;
    }
}

