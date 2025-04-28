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

if (isset($_SESSION['user_id']) && isset($_SESSION['user_role']) && isset($_SESSION['token'])) {
    /*
    echo "Session user_id: " . (isset($_SESSION['user_id']) ? $_SESSION['user_id'] : 'Not set') . "<br>";
    echo "Session user_role: " . (isset($_SESSION['user_role']) ? $_SESSION['user_role'] : 'Not set') . "<br>";
    echo "Session token: " . (isset($_SESSION['token']) ? $_SESSION['token'] : 'Not set') . "<br>";
    */

    $stmt = $pdo->prepare("SELECT expires_at FROM tokens WHERE user_id = ? AND token = ?");
    $stmt->execute([$_SESSION['user_id'], $_SESSION['token']]);
    $token_data = $stmt->fetch(PDO::FETCH_ASSOC);

    if ($token_data) {
        // echo "Token found in database. Expires at: " . $token_data['expires_at'] . "<br>";

        $expires_at = strtotime($token_data['expires_at']);
        $current_time = time();

        if ($current_time > $expires_at) {
            // echo "Token expired. Redirecting to login.php.<br>";
            $stmt = $pdo->prepare("DELETE FROM tokens WHERE user_id = ? AND token = ?");
            $stmt->execute([$_SESSION['user_id'], $_SESSION['token']]);
            
            $_SESSION = array();
            session_destroy();
            header('Location: login.php?message=Session expired. Please log in again.');
            exit;
        } else {
            // echo "Token is goods.<br>";
            $current_page = basename($_SERVER['PHP_SELF']);

            // echo "Current page in route_guard.php: " . $current_page . "<br>";
            if ($current_page === 'login.php') {
                if ($_SESSION['user_role'] === 'Applicant') {
                    // echo "Redirecting to applicantdashboard.php.<br>";
                    header('Location: applicantdashboard.php');
                    exit;
                } elseif ($_SESSION['user_role'] === 'Admin') {
                    // echo "Redirecting to admindashboard.php.<br>";
                    header('Location: admindashboard.php');
                    exit;
                } elseif ($_SESSION['user_role'] === 'Superadmin') {
                    // echo "Redirecting to superadmindashboard.php.<br>";
                    header('Location: superadmindashboard.php');
                    exit;
                }
            }
        }
    } else {
        /*
        echo "Token not found in database for user_id " . $_SESSION['user_id'] . " and token " . $_SESSION['token'] . ".<br>";
        echo "Redirecting to login.php.<br>";
        */
        $_SESSION = array();
        session_destroy();
        header('Location: login.php?message=Invalid session. Please log in again.');
        exit;
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