<?php
session_start();

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $action = $_POST['action'] ?? '';
    
    switch ($action) {
        case 'clear_register':
            unset($_SESSION['show_register_popup']);
            unset($_SESSION['register_error']);
            unset($_SESSION['pending_registration']);
            break;
            
        case 'clear_otp':
            unset($_SESSION['show_otp_popup']);
            unset($_SESSION['otp_error']);
            unset($_SESSION['otp_validation_error']);
            unset($_SESSION['otp_email']);
            unset($_SESSION['otp_verification_pending']);
            break;
    }
    
    echo json_encode(['success' => true]);
} else {
    echo json_encode(['success' => false, 'error' => 'Invalid request method']);
}
?> 