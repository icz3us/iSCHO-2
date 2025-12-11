<?php
/**
 * Redis Job Queue System
 * Simple job queue implementation using Redis
 * 
 * @package iSCHO
 * @version 2.0
 */

require_once __DIR__ . '/../connect/redis_connection.php';

class RedisQueue {
    private $redis;
    private $queueName;

    public function __construct($queueName = 'default') {
        $this->redis = RedisManager::getInstance();
        $this->queueName = $queueName;
    }

    /**
     * Add job to queue
     * 
     * @param string $jobType Job type identifier
     * @param array $data Job data
     * @param int $priority Priority (higher = more important, default: 0)
     * @return string|false Job ID or false on failure
     */
    public function push($jobType, $data, $priority = 0) {
        $job = [
            'id' => uniqid('job_', true),
            'type' => $jobType,
            'data' => $data,
            'priority' => $priority,
            'created_at' => time(),
            'attempts' => 0,
            'status' => 'pending'
        ];

        $jobKey = "queue:{$this->queueName}:jobs:{$job['id']}";
        $stored = $this->redis->set($jobKey, $job, 86400); // 24 hours TTL

        if (!$stored) {
            return false;
        }

        // Add to queue list (sorted by priority)
        $queueKey = "queue:{$this->queueName}:list";
        $client = $this->redis->getClient();
        if ($client) {
            // Use sorted set for priority queue
            $client->zadd($queueKey, $priority, $job['id']);
            $client->expire($queueKey, 86400);
        }

        return $job['id'];
    }

    /**
     * Get next job from queue
     * 
     * @return array|null Job data or null if queue is empty
     */
    public function pop() {
        $queueKey = "queue:{$this->queueName}:list";
        $client = $this->redis->getClient();
        
        if (!$client) {
            return null;
        }

        // Get highest priority job
        $jobIds = $client->zrevrange($queueKey, 0, 0);
        
        if (empty($jobIds)) {
            return null;
        }

        $jobId = $jobIds[0];
        $jobKey = "queue:{$this->queueName}:jobs:{$jobId}";
        $job = $this->redis->get($jobKey);

        if ($job === null) {
            // Job expired or deleted, remove from queue
            $client->zrem($queueKey, $jobId);
            return $this->pop(); // Try next job
        }

        // Remove from queue
        $client->zrem($queueKey, $jobId);

        // Update job status
        $job['status'] = 'processing';
        $job['started_at'] = time();
        $this->redis->set($jobKey, $job, 86400);

        return $job;
    }

    /**
     * Mark job as completed
     * 
     * @param string $jobId Job ID
     * @param array $result Result data
     * @return bool
     */
    public function complete($jobId, $result = []) {
        $jobKey = "queue:{$this->queueName}:jobs:{$jobId}";
        $job = $this->redis->get($jobKey);

        if ($job === null) {
            return false;
        }

        $job['status'] = 'completed';
        $job['completed_at'] = time();
        $job['result'] = $result;

        return $this->redis->set($jobKey, $job, 3600); // Keep completed jobs for 1 hour
    }

    /**
     * Mark job as failed
     * 
     * @param string $jobId Job ID
     * @param string $error Error message
     * @param bool $retry Whether to retry the job
     * @return bool
     */
    public function fail($jobId, $error, $retry = false) {
        $jobKey = "queue:{$this->queueName}:jobs:{$jobId}";
        $job = $this->redis->get($jobKey);

        if ($job === null) {
            return false;
        }

        $job['status'] = 'failed';
        $job['failed_at'] = time();
        $job['error'] = $error;
        $job['attempts'] = ($job['attempts'] ?? 0) + 1;

        // Retry if needed (max 3 attempts)
        if ($retry && $job['attempts'] < 3) {
            $job['status'] = 'pending';
            unset($job['failed_at']);
            unset($job['error']);
            
            // Re-add to queue
            $queueKey = "queue:{$this->queueName}:list";
            $client = $this->redis->getClient();
            if ($client) {
                $client->zadd($queueKey, $job['priority'], $jobId);
            }
        }

        return $this->redis->set($jobKey, $job, 3600);
    }

    /**
     * Get queue size
     * 
     * @return int
     */
    public function size() {
        $queueKey = "queue:{$this->queueName}:list";
        $client = $this->redis->getClient();
        
        if (!$client) {
            return 0;
        }

        return $client->zcard($queueKey);
    }

    /**
     * Get job status
     * 
     * @param string $jobId Job ID
     * @return array|null
     */
    public function getJob($jobId) {
        $jobKey = "queue:{$this->queueName}:jobs:{$jobId}";
        return $this->redis->get($jobKey);
    }

    /**
     * Clear queue
     * 
     * @return bool
     */
    public function clear() {
        $queueKey = "queue:{$this->queueName}:list";
        $client = $this->redis->getClient();
        
        if (!$client) {
            return false;
        }

        // Get all job IDs
        $jobIds = $client->zrange($queueKey, 0, -1);
        
        // Delete all jobs
        foreach ($jobIds as $jobId) {
            $jobKey = "queue:{$this->queueName}:jobs:{$jobId}";
            $this->redis->delete($jobKey);
        }

        // Clear queue list
        return $client->del($queueKey) > 0;
    }
}

