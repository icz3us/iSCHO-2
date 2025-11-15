<?php
// More secure session configuration
session_set_cookie_params([
    'lifetime' => 0,
    'path' => '/',
    'domain' => '',
    'secure' => isset($_SERVER['HTTPS']), // Only use secure cookies over HTTPS
    'httponly' => true,
    'samesite' => 'Strict' // More secure than Lax
]);
session_start();
require './connect/connection.php';
require 'vendor/autoload.php';
use Firebase\JWT\JWT;
use Firebase\JWT\Key;

// Regenerate session ID periodically to prevent session fixation
if (!isset($_SESSION['last_regeneration'])) {
    $_SESSION['last_regeneration'] = time();
} elseif (time() - $_SESSION['last_regeneration'] > 300) { // Every 5 minutes
    session_regenerate_id(true);
    $_SESSION['last_regeneration'] = time();
}

if (isset($_SESSION['user_id']) && isset($_SESSION['user_role']) && isset($_SESSION['token'])) {
    $jwt_key = 'b7e2c1f4a8d9e3f6c2b1a7e5d4c3f8b9e6a2c7d1f3b5e9a4c8d2f7b3e1a6c4d5';
    try {
        $decoded = JWT::decode($_SESSION['token'], new Key($jwt_key, 'HS256'));
    } catch (Exception $e) {
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