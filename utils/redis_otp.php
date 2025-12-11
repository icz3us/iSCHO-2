<?php
/**
 * Redis OTP Manager
 * Manages OTP codes using Redis with automatic expiration
 * 
 * @package iSCHO
 * @version 2.0
 */

require_once __DIR__ . '/../connect/redis_connection.php';

class RedisOTP {
    private $redis;
    private $defaultTTL = 600; // 10 minutes default

    public function __construct($defaultTTL = 600) {
        $this->redis = RedisManager::getInstance();
        $this->defaultTTL = $defaultTTL;
    }

    /**
     * Generate and store OTP for email
     * 
     * @param string $email Email address
     * @param int $ttl Time to live in seconds (default: 600 = 10 minutes)
     * @return string|false OTP code or false on failure
     */
    public function generateOTP($email, $ttl = null) {
        if ($ttl === null) {
            $ttl = $this->defaultTTL;
        }

        // Generate 6-digit OTP
        $otp = str_pad(mt_rand(0, 999999), 6, '0', STR_PAD_LEFT);
        
        // Store OTP with email as key
        $key = "otp:{$email}";
        $data = [
            'code' => $otp,
            'email' => $email,
            'created_at' => time(),
            'attempts' => 0
        ];

        if ($this->redis->set($key, $data, $ttl)) {
            return $otp;
        }

        return false;
    }

    /**
     * Verify OTP code
     * 
     * @param string $email Email address
     * @param string $otp OTP code to verify
     * @param int $maxAttempts Maximum verification attempts (default: 5)
     * @return array Result with 'valid' and 'message' keys
     */
    public function verifyOTP($email, $otp, $maxAttempts = 5) {
        $key = "otp:{$email}";
        $data = $this->redis->get($key);

        if ($data === null) {
            return [
                'valid' => false,
                'message' => 'OTP not found or expired. Please request a new OTP.'
            ];
        }

        // Check attempts
        if (isset($data['attempts']) && $data['attempts'] >= $maxAttempts) {
            $this->redis->delete($key);
            return [
                'valid' => false,
                'message' => 'Maximum verification attempts exceeded. Please request a new OTP.'
            ];
        }

        // Increment attempts
        $data['attempts'] = ($data['attempts'] ?? 0) + 1;
        $ttl = $this->redis->ttl($key);
        if ($ttl > 0) {
            $this->redis->set($key, $data, $ttl);
        }

        // Verify OTP
        if (isset($data['code']) && $data['code'] === $otp) {
            // OTP is valid, delete it (one-time use)
            $this->redis->delete($key);
            return [
                'valid' => true,
                'message' => 'OTP verified successfully.'
            ];
        }

        return [
            'valid' => false,
            'message' => 'Invalid OTP code. Please try again.'
        ];
    }

    /**
     * Check if OTP exists for email
     * 
     * @param string $email Email address
     * @return bool
     */
    public function hasOTP($email) {
        return $this->redis->exists("otp:{$email}");
    }

    /**
     * Get OTP data (without code) for email
     * 
     * @param string $email Email address
     * @return array|null
     */
    public function getOTPInfo($email) {
        $key = "otp:{$email}";
        $data = $this->redis->get($key);
        
        if ($data === null) {
            return null;
        }

        // Remove code for security
        unset($data['code']);
        return $data;
    }

    /**
     * Delete OTP for email
     * 
     * @param string $email Email address
     * @return bool
     */
    public function deleteOTP($email) {
        return $this->redis->delete("otp:{$email}");
    }

    /**
     * Get remaining TTL for OTP
     * 
     * @param string $email Email address
     * @return int|false TTL in seconds or false if not found
     */
    public function getTTL($email) {
        return $this->redis->ttl("otp:{$email}");
    }
}

