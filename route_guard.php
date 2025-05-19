<?php
session_set_cookie_params([
    'lifetime' => 0,
    'path' => '/',
    'domain' => '',
    'secure' => false, 
    'httponly' => true,
    'samesite' => 'Lax'
]);
session_start();
require './connect/connection.php';
require 'vendor/autoload.php';
use Firebase\JWT\JWT;
use Firebase\JWT\Key;

if (isset($_SESSION['user_id']) && isset($_SESSION['user_role']) && isset($_SESSION['token'])) {
    /*
    echo "Session user_id: " . (isset($_SESSION['user_id']) ? $_SESSION['user_id'] : 'Not set') . "<br>";
    echo "Session user_role: " . (isset($_SESSION['user_role']) ? $_SESSION['user_role'] : 'Not set') . "<br>";
    echo "Session token: " . (isset($_SESSION['token']) ? $_SESSION['token'] : 'Not set') . "<br>";
    */

    $jwt_key = 'b7e2c1f4a8d9e3f6c2b1a7e5d4c3f8b9e6a2c7d1f3b5e9a4c8d2f7b3e1a6c4d5';
    try {
        $decoded = JWT::decode($_SESSION['token'], new Key($jwt_key, 'HS256'));
        // Optionally, you can check $decoded->sub == $_SESSION['user_id'] and $decoded->role == $_SESSION['user_role']
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
    // echo "Session user_id, user_role, or token not set.<br>";
    $current_page = basename($_SERVER['PHP_SELF']);
    // echo "Current page in route_guard.php: " . $current_page . "<br>";
    // Allow access to login.php, forgotpassword.php, and resetpassword.php
    $allowed_pages = ['login.php', 'forgotpassword.php', 'resetpassword.php'];
    if (!in_array($current_page, $allowed_pages)) {
        // echo "Redirecting to login.php.<br>";
        header('Location: login.php');
        exit;
    }
}
?>