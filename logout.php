<?php
/**
 * Logout Handler with JWT Blacklisting
 * 
 * @package iSCHO
 * @version 2.0
 */

require_once __DIR__ . '/utils/redis_jwt.php';
require_once __DIR__ . '/route_guard.php';

// Blacklist the JWT token on logout
if (isset($_SESSION['token'])) {
    $redisJWT = new RedisJWT();
    $redisJWT->blacklistToken($_SESSION['token']);
    error_log("User logged out - Token blacklisted: User ID " . ($_SESSION['user_id'] ?? 'unknown'));
}

// Destroy session
session_destroy();

// Redirect to login
header('Location: login.php?message=Logged out successfully.');
exit;

