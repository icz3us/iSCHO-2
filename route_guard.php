<?php
session_start();
require './connect/connection.php';

// Route Guard Logic
if (isset($_SESSION['user_id']) && isset($_SESSION['token'])) {
    $stmt = $pdo->prepare("SELECT expires_at FROM tokens WHERE user_id = ? AND token = ?");
    $stmt->execute([$_SESSION['user_id'], $_SESSION['token']]);
    $token_data = $stmt->fetch(PDO::FETCH_ASSOC);

    if ($token_data) {
        $expires_at = strtotime($token_data['expires_at']);
        $current_time = time();

        if ($current_time > $expires_at) {
            // Token has expired, destroy session and remove token
            $stmt = $pdo->prepare("DELETE FROM tokens WHERE user_id = ? AND token = ?");
            $stmt->execute([$_SESSION['user_id'], $_SESSION['token']]);
            
            $_SESSION = array();
            session_destroy();
            header('Location: login.php?message=Session expired. Please log in again.');
            exit;
        } else {
            // Token is valid, redirect based on role if trying to access login.php
            $current_page = basename($_SERVER['PHP_SELF']);
            if ($current_page === 'login.php') {
                $user_role = $_SESSION['user_role'] ?? '';
                if ($user_role === 'Applicant') {
                    header('Location: applicantdashboard.php');
                    exit;
                } elseif ($user_role === 'Admin') {
                    header('Location: teacher_dashboard.php');
                    exit;
                } elseif ($user_role === 'admin') {
                    header('Location: navigation.php');
                    exit;
                }
            }
        }
    } else {
        // Invalid token, destroy session
        $_SESSION = array();
        session_destroy();
        header('Location: login.php?message=Invalid session. Please log in again.');
        exit;
    }
} else {
    // If not logged in, redirect to login.php unless already on login.php
    $current_page = basename($_SERVER['PHP_SELF']);
    if ($current_page !== 'login.php') {
        header('Location: login.php');
        exit;
    }
}
?>