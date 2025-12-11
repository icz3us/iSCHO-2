<?php
// More secure session configuration
// Force HTTPS for non-localhost environments when not already HTTPS
if (empty($_SERVER['HTTPS']) || $_SERVER['HTTPS'] === 'off') {
    $host = $_SERVER['HTTP_HOST'] ?? '';
    if ($host && stripos($host, 'localhost') === false && stripos($host, '127.0.0.1') === false) {
        $https_url = 'https://' . $host . $_SERVER['REQUEST_URI'];
        header('Location: ' . $https_url);
        exit;
    }
}
// HSTS header (only set when using HTTPS)
if (!empty($_SERVER['HTTPS']) && $_SERVER['HTTPS'] !== 'off') {
    header('Strict-Transport-Security: max-age=31536000; includeSubDomains');
}

session_set_cookie_params([
    'lifetime' => 0,
    'path' => '/',
    'domain' => '',
    'secure' => (!empty($_SERVER['HTTPS']) && $_SERVER['HTTPS'] !== 'off'), // Only use secure cookies over HTTPS
    'httponly' => true,
    'samesite' => 'Strict' // More secure than Lax
]);

// Initialize Redis session handler (before session_start)
// Falls back to file-based sessions if Redis is not available
require_once __DIR__ . '/utils/redis_session.php';
$redisSessionActive = initRedisSession(1800); // 30 minutes TTL

// Start session (will use Redis if available, otherwise file-based)
session_start();
require './connect/connection.php';
require 'vendor/autoload.php';
require_once __DIR__ . '/utils/redis_jwt.php';

use Firebase\JWT\JWT;
use Firebase\JWT\Key;

$redisJWT = new RedisJWT();

// Regenerate session ID periodically to prevent session fixation
if (!isset($_SESSION['last_regeneration'])) {
    $_SESSION['last_regeneration'] = time();
} elseif (time() - $_SESSION['last_regeneration'] > 300) { // Every 5 minutes
    session_regenerate_id(true);
    $_SESSION['last_regeneration'] = time();
}

if (isset($_SESSION['user_id']) && isset($_SESSION['user_role']) && isset($_SESSION['token'])) {
    $jwt_key = 'b7e2c1f4a8d9e3f6c2b1a7e5d4c3f8b9e6a2c7d1f3b5e9a4c8d2f7b3e1a6c4d5';
    
    // Check if token is blacklisted
    if ($redisJWT->isBlacklisted($_SESSION['token'])) {
        $_SESSION = array();
        session_destroy();
        header('Location: login.php?message=Session expired. Please log in again.');
        exit;
    }
    
    try {
        $decoded = JWT::decode($_SESSION['token'], new Key($jwt_key, 'HS256'));
    } catch (Exception $e) {
        // Blacklist invalid token
        $redisJWT->blacklistToken($_SESSION['token']);
        $_SESSION = array();
        session_destroy();
        header('Location: login.php?message=Invalid or expired session. Please log in again.');
        exit;
    }

    $current_page = basename($_SERVER['PHP_SELF']);
    if ($current_page === 'login.php') {
        if ($_SESSION['user_role'] === 'Applicant') {
            header('Location: applicantdashboard.php');
            exit;
        } elseif ($_SESSION['user_role'] === 'Admin') {
            header('Location: admindashboard.php');
            exit;
        } elseif ($_SESSION['user_role'] === 'Superadmin') {
            header('Location: superadmindashboard.php');
            exit;
        }
    }
} else {
    $current_page = basename($_SERVER['PHP_SELF']);
    // Allow access to login.php, forgotpassword.php, resetpassword.php, and announcements.php
    $allowed_pages = ['login.php', 'forgotpassword.php', 'resetpassword.php', 'announcements.php'];
    if (!in_array($current_page, $allowed_pages)) {
        header('Location: login.php');
        exit;
    }
}
?>