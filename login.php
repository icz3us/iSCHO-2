<?php
require './route_guard.php';
require_once __DIR__ . '/vendor/autoload.php'; 
require 'philippine_locations.php';
require_once __DIR__ . '/config.php';
require_once __DIR__ . '/utils/encryption.php';

use PHPMailer\PHPMailer\PHPMailer;
use PHPMailer\PHPMailer\Exception;
use Firebase\JWT\JWT;
use Firebase\JWT\Key;

if (session_status() === PHP_SESSION_NONE) {
    session_start();
}

date_default_timezone_set('Asia/Manila'); 


function generateOTP() {
    return str_pad(mt_rand(0, 999999), 6, '0', STR_PAD_LEFT);
}

function generateJWT($user_id, $role) {
    $key = 'b7e2c1f4a8d9e3f6c2b1a7e5d4c3f8b9e6a2c7d1f3b5e9a4c8d2f7b3e1a6c4d5'; 
    $payload = [
        'iss' => 'ischobsit',
        'iat' => time(),
        'exp' => time() + 1800, 
        'sub' => $user_id,
        'role' => $role
    ];
    return JWT::encode($payload, $key, 'HS256');
}

function sendOTP($email, $otp) {
    $mail = new PHPMailer(true);

    try {
        $mail->isSMTP();
        $mail->Host       = 'smtp.gmail.com'; 
        $mail->SMTPAuth   = true;
        $mail->Username   = 'ischobsit@gmail.com'; 
        $mail->Password   = 'wcep jxly qzwn ybud'; 
        $mail->SMTPSecure = PHPMailer::ENCRYPTION_STARTTLS;
        $mail->Port       = 587;

        $mail->setFrom('ischobsit@gmail.com', 'ISCHO App');
        $mail->addAddress($email);

        $mail->isHTML(true);
        $mail->Subject = "Your OTP for iSCHO Registration";
        $mail->Body = "
    <div style='font-family: Arial, sans-serif; max-width: 600px; margin: 0 auto; border: 1px solid #e0e0e0; border-radius: 8px;'>
        <!-- Header -->
        <div style='background-color: #4f46e5; padding: 20px; text-align: center; border-top-left-radius: 8px; border-top-right-radius: 8px;'>
            <h1 style='color: #ffffff; margin: 0; font-size: 24px;'>Your OTP Code</h1>
        </div>
        <!-- Body -->
        <div style='padding: 30px; background-color: #ffffff;'>
            <p style='color: #1f2937; font-size: 16px; margin-bottom: 15px;'>Hello,</p>
            <p style='color: #1f2937; font-size: 16px; margin-bottom: 15px;'>Thank you for registering with us! To complete your registration, please use the following One-Time Password (OTP):</p>
            <div style='text-align: center; margin: 20px 0;'>
                <span style='display: inline-block; background-color: #f3f4f6; padding: 15px 25px; border-radius: 5px; font-size: 24px; font-weight: bold; color: #4f46e5; letter-spacing: 2px;'>$otp</span>
            </div>
            <p style='color: #1f2937; font-size: 16px; margin-bottom: 15px;'>This OTP is valid for <strong>10 minutes</strong>. Please do not share this code with anyone.</p>
            <p style='color: #1f2937; font-size: 16px; margin-bottom: 15px;'>If you did not request this OTP, please ignore this email or contact us at <a href='mailto:ischobsit@gmail.com' style='color: #4f46e5; text-decoration: none;'>ischobsit@gmail.com</a>.</p>
            <p style='color: #1f2937; font-size: 16px; margin-bottom: 0;'>Best regards,<br>iSCHO Admin Team</p>
        </div>
        <!-- Footer -->
        <div style='background-color: #f9fafb; padding: 15px; text-align: center; border-bottom-left-radius: 8px; border-bottom-right-radius: 8px;'>
            <p style='color: #6b7280; font-size: 12px; margin: 0;'>© 2025 iSCHO. All rights reserved.</p>
        </div>
    </div>
";
        $mail->AltBody = "Hello,\n\nThank you for registering with us! To complete your registration, please use the following One-Time Password (OTP):\n\n$otp\n\nThis OTP is valid for 10 minutes. Please do not share this code with anyone.\n\nIf you did not request this OTP, please ignore this email or contact us at ischobsit@gmail.com.\n\nBest regards,\nScholarship Admin Team";
        $mail->send();
        return true;
    } catch (Exception $e) {
        return "Failed to send OTP: {$mail->ErrorInfo}";
    }
}

function validateName($name) {
    if (preg_match('/[0-9]/', $name)) {
        return false;
    }
 
    if (preg_match('/[^a-zA-Z\s\-\']/', $name)) {
        return false;
    }
    return true;
}

function validatePhilippineNumber($number) {

    $number = preg_replace('/[\s\-\(\)]/', '', $number);
    
    
    if (!preg_match('/^(\+63|09)\d{9}$/', $number)) {
        return false;
    }
    return true;
}

function validateAge($birthdate) {
    $birth = new DateTime($birthdate);
    $today = new DateTime();
    $age = $today->diff($birth)->y;
    
    return ($age >= 16 && $age <= 100);
}

function validatePhilippineLocation($municipality, $barangay) {
    return validateLocation($municipality, $barangay);
}

if ($_SERVER['REQUEST_METHOD'] == 'POST' && isset($_POST['register']) && !isset($_POST['verify_otp'])) {
    error_log("Registration handler triggered");
    $municipality = trim($_POST['municipality']);
    $barangay = trim($_POST['barangay']);
    $firstname = trim($_POST['firstname']);
    $lastname = trim($_POST['lastname']);
    $middlename = trim($_POST['middlename']);
    $sex = trim($_POST['sex']);
    $civil_status = trim($_POST['civil_status']);
    $birthdate = trim($_POST['birthdate']);
    $place_of_birth = trim($_POST['place_of_birth']);
    $contact_no = trim($_POST['contact_no']);
    $email = trim($_POST['email']);
    $password = trim($_POST['password']);
    $confirm_password = trim($_POST['confirm_password']);

    $register_error = '';

    // Validate names
    if (!validateName($firstname)) {
        $register_error = "First name should not contain numbers or special characters.";
    } elseif (!validateName($lastname)) {
        $register_error = "Last name should not contain numbers or special characters.";
    } elseif (!empty($middlename) && !validateName($middlename)) {
        $register_error = "Middle name should not contain numbers or special characters.";
    }

    // Validate Philippine phone number
    if (!validatePhilippineNumber($contact_no)) {
        $register_error = "Please enter a valid Philippine mobile number (e.g., 09XXXXXXXXX or +639XXXXXXXXX).";
    }

    // Validate age
    if (!validateAge($birthdate)) {
        $register_error = "You must be a valid age";
    }

    // Validate Philippine location
    if (!validatePhilippineLocation($municipality, $barangay)) {
        $register_error = "Please enter a valid Philippine municipality/city and barangay.";
    }

    // Existing password validation
    if (!preg_match('/[A-Z]/', $password) || !preg_match('/[0-9]/', $password)) {
        $register_error = "Password must contain at least one uppercase letter and one number.";
    }

    if ($password !== $confirm_password) {
        $register_error = "Passwords do not match.";
    }

    // Existing email check
    $stmt = $pdo->prepare("SELECT id FROM users WHERE email = ?");
    $stmt->execute([$email]);
    if ($stmt->fetch()) {
        $register_error = "Email is already registered.";
    }

    if (empty($register_error)) {
        $otp = generateOTP();
        $expires_at = date('Y-m-d H:i:s', strtotime('+10 minutes'));

        $_SESSION['pending_registration'] = [
            'municipality' => $municipality,
            'barangay' => $barangay,
            'firstname' => $firstname,
            'lastname' => $lastname,
            'middlename' => $middlename,
            'sex' => $sex,
            'civil_status' => $civil_status,
            'birthdate' => $birthdate,
            'place_of_birth' => $place_of_birth,
            'contact_no' => $contact_no,
            'email' => $email,
            'password' => $password
        ];

        try {
            $stmt = $pdo->prepare("
                INSERT INTO otp_verifications (email, otp, expires_at, user_id)
                VALUES (?, ?, ?, NULL)
            ");
            $stmt->execute([$email, $otp, $expires_at]);
            error_log("OTP stored successfully for email: $email, OTP: $otp, Expires At: $expires_at");
        } catch (PDOException $e) {
            $register_error = "Failed to store OTP: " . $e->getMessage();
            error_log("OTP Storage Error: " . $e->getMessage());
            unset($_SESSION['pending_registration']);
            $_SESSION['show_register_popup'] = true; 
        }

        if (empty($register_error)) {
            $email_result = sendOTP($email, $otp);
            if ($email_result !== true) {
                $register_error = $email_result;
                error_log("Email Sending Error: $register_error");
                unset($_SESSION['pending_registration']);
                $_SESSION['show_register_popup'] = true; 
            } else {
                $_SESSION['otp_email'] = $email;
                $_SESSION['otp_verification_pending'] = true; 
                $_SESSION['show_otp_popup'] = true;
                $_SESSION['show_register_popup'] = false; 
                error_log("OTP email sent successfully to: $email");
            }
        }
    }

    if (!empty($register_error)) {
        $_SESSION['register_error'] = $register_error;
        $_SESSION['show_register_popup'] = true; 
    }
}

if ($_SERVER['REQUEST_METHOD'] == 'POST' && isset($_POST['verify_otp']) && isset($_SESSION['otp_verification_pending'])) {
    error_log("OTP verification handler triggered");
    if (!isset($_SESSION['pending_registration'])) {
        $_SESSION['otp_error'] = "Registration data not found. Please try registering again.";
        unset($_SESSION['otp_email']);
        unset($_SESSION['otp_verification_pending']);
        unset($_SESSION['show_otp_popup']);
        $_SESSION['show_register_popup'] = false; 
        error_log("OTP Verification Failed: Registration data not found in session");
    } else {
        $otp_code = trim($_POST['otp']);
        $email = $_SESSION['otp_email'];
        $pending_data = $_SESSION['pending_registration'];

        try {
            $stmt = $pdo->prepare("
                SELECT id, otp, expires_at, verified
                FROM otp_verifications
                WHERE email = ? AND verified = 0
                ORDER BY created_at DESC
                LIMIT 1
            ");
            $stmt->execute([$email]);
            $otp_record = $stmt->fetch(PDO::FETCH_ASSOC);

            if (!$otp_record) {
                $stmt = $pdo->prepare("SELECT id, otp, expires_at, verified FROM otp_verifications WHERE email = ? ORDER BY created_at DESC LIMIT 1");
                $stmt->execute([$email]);
                $debug_record = $stmt->fetch(PDO::FETCH_ASSOC);
                
                if ($debug_record) {
                    error_log("OTP Debug - Record found but not matched: Email: $email, OTP: {$debug_record['otp']}, Verified: {$debug_record['verified']}, Expires At: {$debug_record['expires_at']}");
                    $_SESSION['otp_error'] = "No valid OTP found for this email. The OTP may have been used or expired.";
                } else {
                    error_log("OTP Debug - No OTP record found for email: $email");
                    $_SESSION['otp_error'] = "No OTP record exists for this email. Please try registering again.";
                }
                $_SESSION['show_otp_popup'] = true; 
                $_SESSION['show_register_popup'] = false; 
            } else {
                $stored_otp = (string)$otp_record['otp'];
                $otp_input = (string)$otp_code;
                $expires_at = strtotime($otp_record['expires_at']);
                $current_time = time();

                error_log("OTP Verification Attempt - Email: $email, Input OTP: $otp_input, Stored OTP: $stored_otp, Expires At: " . $otp_record['expires_at'] . ", Current Time: $current_time, Expires Timestamp: $expires_at");

                if ($otp_input !== $stored_otp) {
                    $_SESSION['otp_validation_error'] = "Invalid OTP. Please try again.";
                    error_log("OTP Verification Failed - Email: $email, Input OTP: $otp_input, Stored OTP: $stored_otp");
                    $_SESSION['show_otp_popup'] = true; 
                    $_SESSION['show_register_popup'] = false; 
                } elseif ($expires_at <= $current_time) {
                    $_SESSION['otp_validation_error'] = "OTP has expired. Please request a new one.";
                    error_log("OTP Verification Failed - OTP Expired for Email: $email");
                    $_SESSION['show_otp_popup'] = true; 
                    $_SESSION['show_register_popup'] = false; 
                } else {
                    $transactionStarted = false;
                    try {
                        $pdo->exec("SET autocommit = 0");
                        error_log("Autocommit disabled for email: $email");

                        if ($pdo->inTransaction()) {
                            $pdo->exec("SET autocommit = 1");
                            throw new PDOException("A transaction is already active. Cannot start a new one.");
                        }

                        $pdo->beginTransaction();
                        $transactionStarted = true;
                        error_log("Transaction started successfully for email: $email");

                        $hashed_password = password_hash($pending_data['password'], PASSWORD_DEFAULT);
                        // Generate a username based on email to avoid duplicate entry errors
                        $username = $pending_data['email'];
                        if (strpos($username, '@') !== false) {
                            $username = substr($username, 0, strpos($username, '@'));
                        }
                        
                        $stmt = $pdo->prepare("
                            INSERT INTO users (
                                firstname, lastname, middlename, contact_no, email, username, password, role
                            ) VALUES (?, ?, ?, ?, ?, ?, ?, ?)
                        ");
                        // Encrypt sensitive personal fields before storing
                        $enc_firstname = encrypt_for_db($pending_data['firstname']);
                        $enc_lastname = encrypt_for_db($pending_data['lastname']);
                        $enc_middlename = encrypt_for_db($pending_data['middlename']);
                        $stmt->execute([
                            $enc_firstname,
                            $enc_lastname,
                            $enc_middlename,
                            $pending_data['contact_no'],
                            $pending_data['email'],
                            $username,
                            $hashed_password,
                            'Applicant'
                        ]);

                        $user_id = $pdo->lastInsertId();
                        error_log("Inserted into users table, user_id: $user_id");

                        $stmt = $pdo->prepare("
                            INSERT INTO users_info (
                                user_id, municipality, barangay, sex, civil_status, birthdate, place_of_birth
                            ) VALUES (?, ?, ?, ?, ?, ?, ?)
                        ");
                        $enc_municipality = encrypt_for_db($pending_data['municipality']);
                        $enc_barangay = encrypt_for_db($pending_data['barangay']);
                        $enc_place_of_birth = encrypt_for_db($pending_data['place_of_birth']);
                        $stmt->execute([
                            $user_id,
                            $enc_municipality,
                            $enc_barangay,
                            $pending_data['sex'],
                            $pending_data['civil_status'],
                            $pending_data['birthdate'],
                            $enc_place_of_birth
                        ]);
                        error_log("Inserted into users_info table for user_id: $user_id");

                        $stmt = $pdo->prepare("
                            UPDATE otp_verifications
                            SET verified = 1, user_id = ?
                            WHERE id = ?
                        ");
                        $stmt->execute([$user_id, $otp_record['id']]);
                        error_log("Updated otp_verifications, set verified = 1 and user_id = $user_id for OTP record id: " . $otp_record['id']);

                        $pdo->commit();
                        error_log("Transaction committed successfully for email: $email, user_id: $user_id");

                        $pdo->exec("SET autocommit = 1");
                        error_log("Autocommit restored for email: $email");

                        $_SESSION['login_success'] = "Registered Successfully, Please Login";

                        unset($_SESSION['pending_registration']);
                        unset($_SESSION['otp_email']);
                        unset($_SESSION['otp_verification_pending']);
                        unset($_SESSION['show_otp_popup']);
                        unset($_SESSION['show_register_popup']);
                        unset($_SESSION['register_error']);
                        unset($_SESSION['otp_error']);
                        unset($_SESSION['otp_validation_error']);

                        error_log("Registration Successful - Email: $email, User ID: $user_id");
                    } catch (PDOException $e) {
                        if ($transactionStarted) {
                            try {
                                $pdo->rollBack();
                                error_log("Transaction rolled back due to error: " . $e->getMessage());
                            } catch (PDOException $rollbackError) {
                                error_log("Rollback Error: " . $rollbackError->getMessage());
                            }
                        }
                        try {
                            $pdo->exec("SET autocommit = 1");
                            error_log("Autocommit restored after failure for email: $email");
                        } catch (PDOException $autocommitError) {
                            error_log("Failed to restore autocommit: " . $autocommitError->getMessage());
                        }
                        $_SESSION['otp_error'] = "Failed to complete registration: " . $e->getMessage();
                        error_log("Registration Error: " . $e->getMessage());
                        $_SESSION['show_otp_popup'] = true; 
                        $_SESSION['show_register_popup'] = false; 
                    }
                }
            }
        } catch (PDOException $e) {
            $_SESSION['otp_error'] = "Failed to verify OTP: " . $e->getMessage();
            error_log("OTP Verification Error: " . $e->getMessage());
            $_SESSION['show_otp_popup'] = true; 
            $_SESSION['show_register_popup'] = false; 
        }
    }
}

// Handle Login
if ($_SERVER['REQUEST_METHOD'] == 'POST' && !isset($_POST['register']) && !isset($_POST['verify_otp'])) {
    error_log("Login handler triggered");
    $email = $_POST['email'];
    $password = $_POST['password'];

    $stmt = $pdo->prepare("SELECT id, firstname, lastname, middlename, role, password FROM users WHERE email = ?");
    $stmt->execute([$email]);
    $user = $stmt->fetch(PDO::FETCH_ASSOC);

    if ($user && password_verify($password, $user['password'])) {
        $token = generateJWT($user['id'], $user['role']);
        // No need to insert token into the database for stateless JWT

            $_SESSION['user_id'] = $user['id'];
            $_SESSION['firstname'] = $user['firstname'];
            $_SESSION['lastname'] = $user['lastname'];
            $_SESSION['middlename'] = $user['middlename'];
            $_SESSION['user_role'] = $user['role'];
            $_SESSION['token'] = $token;

            if ($user['role'] === 'Applicant') {
                header('Location: applicantdashboard.php');
                exit;
            } elseif ($user['role'] === 'Admin') {
                header('Location: admindashboard.php');
                exit;
            } elseif ($user['role'] === 'Superadmin') {
                header('Location: superadmindashboard.php');
                exit;
            } else {
                $login_error = "Unknown role: " . htmlspecialchars($user['role']) . ". Please contact support.";
        }
    } else {
        $login_error = "Invalid email or password";
    }
}

// Add this after your existing PHP code but before the HTML
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['action']) && $_POST['action'] === 'get_barangays') {
    // Prevent any output before headers
    ob_clean();
    
    // Set headers
    header('Content-Type: application/json');
    header('Cache-Control: no-cache, must-revalidate');
    
    try {
        if (!isset($_POST['municipality']) || empty($_POST['municipality'])) {
            throw new Exception('Municipality is required');
        }

        $municipality = trim($_POST['municipality']);
        $barangays = getBarangays($municipality);
        
        if (empty($barangays)) {
            throw new Exception('No barangays found for the selected municipality');
        }
        
        echo json_encode($barangays);
    } catch (Exception $e) {
        echo json_encode(['error' => $e->getMessage()]);
    }
    exit;
}
?>

<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Login</title>
    <link href="https://fonts.googleapis.com/css2?family=Poppins:wght@300;400;500;600&display=swap" rel="stylesheet">
    <link href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.0.0/css/all.min.css" rel="stylesheet">
    <link rel="icon" type="image/png" href="./images/logo1.png">
    <style>
        :root {
            --primary-color: #6366f1;
            --primary-hover: #818cf8;
            --primary-light: rgba(99, 102, 241, 0.1);
            --secondary-color: #a855f7;
            --accent-color: #ec4899;
            --bg-gradient: linear-gradient(135deg, #667eea 0%, #764ba2 50%, #f093fb 100%);
            --bg-gradient-dark: linear-gradient(180deg, #0f172a 0%, #1e1b4b 50%, #312e81 100%);
            --bg-gradient-card: linear-gradient(135deg, rgba(30, 27, 75, 0.95) 0%, rgba(49, 46, 129, 0.9) 100%);
            --bg-color: #0f172a;
            --card-bg: rgba(30, 27, 75, 0.95);
            --card-bg-hover: rgba(49, 46, 129, 0.98);
            --text-color: #f8fafc;
            --text-muted: #cbd5e1;
            --text-bright: #ffffff;
            --border-color: rgba(99, 102, 241, 0.3);
            --border-hover: rgba(99, 102, 241, 0.5);
            --error-color: #ef4444;
            --success-color: #22c55e;
            --shadow-sm: 0 2px 8px rgba(0, 0, 0, 0.3);
            --shadow-md: 0 4px 16px rgba(0, 0, 0, 0.4);
            --shadow-lg: 0 10px 30px rgba(0, 0, 0, 0.5);
            --shadow-xl: 0 20px 50px rgba(0, 0, 0, 0.6);
        }

        * {
            margin: 0;
            padding: 0;
            box-sizing: border-box;
            font-family: 'Poppins', sans-serif;
        }

        body {
            background: var(--bg-gradient-dark);
            background-attachment: fixed;
            color: var(--text-color);
            min-height: 100vh;
            position: relative;
        }

        body::before {
            content: '';
            position: absolute;
            top: 0;
            left: 0;
            width: 100%;
            height: 100%;
            background: url('./images/bg.jpg') no-repeat center center fixed;
            background-size: cover;
            opacity: 0.15;
            z-index: 1;
        }

        body::after {
            content: '';
            position: absolute;
            top: 0;
            left: 0;
            width: 100%;
            height: 100%;
            background: radial-gradient(circle at 30% 50%, rgba(99, 102, 241, 0.1) 0%, transparent 50%),
                        radial-gradient(circle at 70% 80%, rgba(168, 85, 247, 0.1) 0%, transparent 50%);
            z-index: 1;
            pointer-events: none;
        }

        .main-content {
            display: flex;
            align-items: center;
            justify-content: center;
            min-height: calc(100vh - 70px);
            padding: 20px;
            position: relative;
            z-index: 2;
        }

        .login-container, .otp-container {
            background: var(--bg-gradient-card);
            backdrop-filter: blur(20px);
            -webkit-backdrop-filter: blur(20px);
            border-radius: 24px;
            box-shadow: var(--shadow-xl);
            width: 100%;
            max-width: 500px;
            padding: 2.5rem;
            border: 1px solid var(--border-color);
            position: relative;
            overflow: hidden;
            transition: all 0.3s ease;
        }

        .login-container::before, .otp-container::before {
            content: '';
            position: absolute;
            top: 0;
            left: 0;
            width: 100%;
            height: 4px;
            background: var(--bg-gradient);
            z-index: 1;
        }

        .login-container:hover, .otp-container:hover {
            transform: translateY(-5px);
            box-shadow: var(--shadow-xl);
            border-color: var(--border-hover);
        }

        .register-container {
            background: var(--bg-gradient-card);
            backdrop-filter: blur(20px);
            -webkit-backdrop-filter: blur(20px);
            border-radius: 24px;
            box-shadow: var(--shadow-xl);
            width: 100%;
            max-width: 900px;
            padding: 2.5rem;
            border: 1px solid var(--border-color);
            position: relative;
            overflow: hidden;
            transition: all 0.3s ease;
        }

        .register-container::before {
            content: '';
            position: absolute;
            top: 0;
            left: 0;
            width: 100%;
            height: 4px;
            background: var(--bg-gradient);
            z-index: 1;
        }

        .register-container:hover {
            transform: translateY(-5px);
            box-shadow: var(--shadow-xl);
            border-color: var(--border-hover);
        }

        .otp-container {
            max-width: 500px;
        }

        .login-header, .register-header, .otp-header {
            text-align: center;
            margin-bottom: 2rem;
            display: flex;
            flex-direction: column;
            align-items: center;
            gap: 1rem;
        }

        .login-header img {
            width: 100px;
            height: auto;
            margin-bottom: 0.5rem;
            filter: drop-shadow(0 10px 20px rgba(99, 102, 241, 0.3));
            transition: transform 0.3s ease;
        }

        .login-header img:hover {
            transform: scale(1.05);
        }

        .login-header h1 {
            font-size: 1.75rem;
            font-weight: 700;
            background: var(--bg-gradient);
            -webkit-background-clip: text;
            -webkit-text-fill-color: transparent;
            background-clip: text;
            margin-bottom: 0.5rem;
        }

        .login-header h2 {
            font-size: 1.3rem;
            font-weight: 600;
            color: var(--text-bright);
            margin-bottom: 0.5rem;
        }

        .login-header p, .register-header p, .otp-header p {
            color: var(--text-muted);
            font-size: 0.95rem;
            margin-top: 0.5rem;
        }

        .form-group {
            margin-bottom: 1.25rem;
        }

        .form-group label {
            display: block;
            margin-bottom: 0.5rem;
            font-weight: 500;
            font-size: 0.95rem;
            color: var(--text-bright);
        }

        .input-group {
            position: relative;
            display: flex;
            align-items: center;
        }

        .input-group i:not(.toggle-password) {
            position: absolute;
            top: 50%;
            left: 1rem;
            transform: translateY(-50%);
            color: var(--text-muted);
            z-index: 2;
        }

        .form-control {
            width: 100%;
            padding: 0.875rem 2.5rem 0.875rem 2.5rem;
            border: 1px solid var(--border-color);
            border-radius: 12px;
            font-size: 1rem;
            transition: all 0.3s ease;
            background: rgba(15, 23, 42, 0.5);
            color: var(--text-bright);
            backdrop-filter: blur(10px);
        }

        .form-control::placeholder {
            color: var(--text-muted);
        }

        .form-control:focus {
            outline: none;
            border-color: var(--primary-color);
            box-shadow: 0 0 0 3px rgba(99, 102, 241, 0.2);
            background: rgba(15, 23, 42, 0.7);
            transform: translateY(-2px);
        }

        .toggle-password {
            position: absolute;
            right: 1rem;
            top: 50%;
            transform: translateY(-50%);
            cursor: pointer;
            color: var(--text-muted);
            font-size: 1rem;
            z-index: 2;
            transition: all 0.3s ease;
        }

        .toggle-password:hover {
            color: var(--primary-color);
            transform: translateY(-50%) scale(1.1);
        }

        .login-btn, .register-btn, .otp-btn {
            width: 100%;
            background: var(--bg-gradient);
            color: white;
            border: none;
            border-radius: 12px;
            padding: 0.875rem 1.5rem;
            font-size: 1rem;
            font-weight: 600;
            cursor: pointer;
            transition: all 0.3s ease;
            box-shadow: 0 4px 15px rgba(99, 102, 241, 0.3);
            position: relative;
            overflow: hidden;
        }

        .login-btn::before, .register-btn::before, .otp-btn::before {
            content: '';
            position: absolute;
            top: 50%;
            left: 50%;
            width: 0;
            height: 0;
            border-radius: 50%;
            background: rgba(255, 255, 255, 0.2);
            transform: translate(-50%, -50%);
            transition: width 0.6s, height 0.6s;
        }

        .login-btn:hover::before, .register-btn:hover::before, .otp-btn:hover::before {
            width: 300px;
            height: 300px;
        }

        .login-btn:hover, .register-btn:hover, .otp-btn:hover {
            transform: translateY(-2px);
            box-shadow: 0 6px 20px rgba(99, 102, 241, 0.4);
        }

        .login-btn span, .register-btn span, .otp-btn span {
            position: relative;
            z-index: 1;
        }

        .forgot-password {
            display: block;
            text-align: center;
            margin-top: 1rem;
            background: var(--bg-gradient);
            -webkit-background-clip: text;
            -webkit-text-fill-color: transparent;
            background-clip: text;
            text-decoration: none;
            font-size: 0.9rem;
            font-weight: 500;
            transition: all 0.3s ease;
        }

        .forgot-password:hover {
            text-decoration: underline;
            transform: translateY(-1px);
        }

        .error-message {
            background: rgba(239, 68, 68, 0.15);
            backdrop-filter: blur(10px);
            color: #fca5a5;
            padding: 1rem;
            border-radius: 12px;
            margin-bottom: 1.25rem;
            font-size: 0.9rem;
            border: 1px solid rgba(239, 68, 68, 0.3);
            display: flex;
            align-items: center;
            gap: 0.5rem;
        }

        .error-message::before {
            content: '⚠';
            font-size: 1.2rem;
        }

        .success-message {
            background: rgba(34, 197, 94, 0.15);
            backdrop-filter: blur(10px);
            color: #86efac;
            padding: 1rem;
            border-radius: 12px;
            margin-bottom: 1.25rem;
            font-size: 0.9rem;
            border: 1px solid rgba(34, 197, 94, 0.3);
            display: flex;
            align-items: center;
            gap: 0.5rem;
        }

        .success-message::before {
            content: '✓';
            font-size: 1.2rem;
        }

        .otp-error-message {
            color: var(--error-color);
            font-size: 0.85rem;
            margin-top: 0.25rem;
        }

        .register-link {
            text-align: center;
            margin-top: 1.5rem;
            font-size: 0.9rem;
        }

        .register-link a {
            background: var(--bg-gradient);
            -webkit-background-clip: text;
            -webkit-text-fill-color: transparent;
            background-clip: text;
            text-decoration: none;
            cursor: pointer;
            font-weight: 500;
            transition: all 0.3s ease;
        }

        .register-link a:hover {
            text-decoration: underline;
            transform: translateY(-1px);
        }

        .register-popup, .otp-popup {
            display: none;
            position: fixed;
            top: 0;
            left: 0;
            width: 100%;
            height: 100%;
            background: rgba(0, 0, 0, 0.7);
            backdrop-filter: blur(5px);
            z-index: 1000;
            align-items: center;
            justify-content: center;
            opacity: 0;
            visibility: hidden;
            transition: opacity 0.3s ease, visibility 0.3s ease;
        }

        .register-popup.active, .otp-popup.active {
            opacity: 1;
            visibility: visible;
        }

        .register-container, .otp-container {
            position: relative;
            max-height: 90vh;
            overflow-y: auto;
            transform: scale(0.7);
            opacity: 0;
            transition: transform 0.3s ease, opacity 0.3s ease;
        }

        .register-popup.active .register-container,
        .otp-popup.active .otp-container {
            transform: scale(1);
            opacity: 1;
        }

        @keyframes modalFadeIn {
            from {
                opacity: 0;
                transform: scale(0.7);
            }
            to {
                opacity: 1;
                transform: scale(1);
            }
        }

        @keyframes modalFadeOut {
            from {
                opacity: 1;
                transform: scale(1);
            }
            to {
                opacity: 0;
                transform: scale(0.7);
            }
        }

        .close-btn {
            position: absolute;
            top: 15px;
            right: 15px;
            font-size: 1.75rem;
            color: var(--text-muted);
            cursor: pointer;
            background: rgba(15, 23, 42, 0.5);
            border: 1px solid var(--border-color);
            border-radius: 50%;
            padding: 0.5rem;
            width: 40px;
            height: 40px;
            display: flex;
            align-items: center;
            justify-content: center;
            transition: all 0.3s ease;
            z-index: 10;
        }

        .close-btn:hover {
            color: var(--text-bright);
            background: rgba(239, 68, 68, 0.2);
            border-color: var(--error-color);
            transform: rotate(90deg);
        }

        .form-section {
            margin-bottom: 2rem;
        }

        .form-section h3 {
            font-size: 1.3rem;
            font-weight: 600;
            background: var(--bg-gradient);
            -webkit-background-clip: text;
            -webkit-text-fill-color: transparent;
            background-clip: text;
            margin-bottom: 1rem;
            border-bottom: 2px solid;
            border-image: var(--bg-gradient) 1;
            padding-bottom: 0.5rem;
        }

        .form-row {
            display: flex;
            flex-wrap: wrap;
            gap: 1rem;
            margin-bottom: 1rem;
        }

        .form-row .form-group {
            flex: 1;
            min-width: 200px;
        }

        .form-row .form-group.full-width {
            flex: 100%;
        }

        .form-buttons {
            display: flex;
            justify-content: flex-end;
            gap: 1rem;
        }

        .form-buttons .cancel-btn {
            background: linear-gradient(135deg, #475569 0%, #64748b 100%);
            color: white;
            border: none;
            border-radius: 12px;
            padding: 0.75rem 1.5rem;
            font-size: 1rem;
            font-weight: 500;
            cursor: pointer;
            transition: all 0.3s ease;
            box-shadow: 0 4px 15px rgba(71, 85, 105, 0.3);
        }

        .form-buttons .cancel-btn:hover {
            background: linear-gradient(135deg, #334155 0%, #475569 100%);
            transform: translateY(-2px);
            box-shadow: 0 6px 20px rgba(71, 85, 105, 0.4);
        }

        .password-requirements {
            font-size: 0.85rem;
            color: var(--text-muted);
            margin-top: 0.25rem;
        }

        .password-requirements.invalid {
            color: var(--error-color);
        }

        .validation-text {
            font-size: 0.85rem;
            color: var(--text-muted);
            margin-top: 0.25rem;
            transition: color 0.3s ease;
        }

        .validation-text.invalid {
            color: var(--error-color);
        }

        .validation-text.valid {
            color: var(--success-color);
        }

        select.form-control {
            appearance: none;
            background-image: url("data:image/svg+xml,%3Csvg xmlns='http://www.w3.org/2000/svg' width='12' height='12' viewBox='0 0 12 12'%3E%3Cpath fill='%2394a3b8' d='M6 9L1 4h10z'/%3E%3C/svg%3E");
            background-repeat: no-repeat;
            background-position: right 1rem center;
            padding-right: 2.5rem;
            cursor: pointer;
        }

        select.form-control:focus {
            background-image: url("data:image/svg+xml,%3Csvg xmlns='http://www.w3.org/2000/svg' width='12' height='12' viewBox='0 0 12 12'%3E%3Cpath fill='%236366f1' d='M6 9L1 4h10z'/%3E%3C/svg%3E");
        }

        textarea.form-control {
            min-height: 100px;
            resize: vertical;
        }

        input[type="checkbox"], input[type="radio"] {
            width: 18px;
            height: 18px;
            accent-color: var(--primary-color);
            cursor: pointer;
        }

        input[type="checkbox"]:checked, input[type="radio"]:checked {
            accent-color: var(--primary-color);
        }

        @media (max-width: 768px) {
            .login-container, .register-container, .otp-container {
                max-width: 90%;
                padding: 1.5rem;
            }

            .login-header h1, .register-header h1, .otp-header h1 {
                font-size: 1.5rem;
            }

            .form-row {
                flex-direction: column;
            }

            .form-row .form-group {
                min-width: 100%;
            }
        }

        @media (max-width: 480px) {
            .login-container, .register-container, .otp-container {
                padding: 1rem;
            }

            .login-header h1, .register-header h1, .otp-header h1 {
                font-size: 1.3rem;
            }

            .form-section h3 {
                font-size: 1rem;
            }

            .form-buttons {
                flex-direction: column;
                gap: 0.5rem;
            }

            .form-buttons .cancel-btn,
            .form-buttons .register-btn,
            .form-buttons .otp-btn {
                width: 100%;
            }
        }
    </style>
</head>

<body>
    <?php include('navbar.php'); ?>
    
    <div class="main-content">
        <div class="login-container">
            <div class="login-header">
                <img src="./images/logo1.png" alt="iSCHO Logo">
                <h1>Integrated Scholarship Application Portal</h1>
                <h2>Login Page</h2>
                <p>Please enter your credentials to login</p>
            </div>
            
            <?php if (isset($login_error)): ?>
            <div class="error-message">
                <?php echo htmlspecialchars($login_error); ?>
            </div>
            <?php endif; ?>
            
            <?php if (isset($_SESSION['login_success'])): ?>
            <div class="success-message">
                <?php echo htmlspecialchars($_SESSION['login_success']); unset($_SESSION['login_success']); ?>
            </div>
            <?php endif; ?>
            
            <form action="login.php" method="POST">
                <div class="form-group">
                    <label for="email">Email Address</label>
                    <div class="input-group">
                        <i class="fas fa-envelope"></i>
                        <input type="email" id="email" name="email" class="form-control" required>
                    </div>
                </div>
                
                <div class="form-group">
                    <label for="login-password">Password</label>
                    <div class="input-group">
                        <i class="fas fa-lock"></i>
                        <input type="password" id="login-password" name="password" class="form-control" required>
                        <i class="fas fa-eye toggle-password" onclick="togglePassword('login-password')"></i>
                    </div>
                </div>
                
                <button type="submit" class="login-btn"><span>Sign In</span></button>
            </form>
            
            <a href="forgotpassword.php" class="forgot-password">Forgot your password?</a>
            <div class="register-link">
               Don't have an account? <a onclick="showRegisterPopup()">Register here</a>
            </div>
        </div>
    </div>

    <!-- Register Popup -->
    <div class="register-popup" id="registerPopup" <?php echo (isset($_SESSION['show_register_popup']) && $_SESSION['show_register_popup']) ? 'style="display: flex;"' : ''; ?>>
        <div class="register-container">
            <button class="close-btn" onclick="hideRegisterPopup()">×</button>
            <div class="register-header">
                <h1>Register</h1>
                <p>Create your account</p>
            </div>
            
            <?php if (isset($_SESSION['register_error'])): ?>
            <div class="error-message">
                <?php echo htmlspecialchars($_SESSION['register_error']); ?>
            </div>
            <?php endif; ?>
            
            <form action="login.php" method="POST" id="registerForm">
                <!-- Residency Section -->
                <div class="form-section">
                    <h3>Residency</h3>
                    <div class="form-row">
                        <div class="form-group">
                            <label for="municipality">Municipality</label>
                            <div class="input-group">
                                <i class="fas fa-map-marker-alt"></i>
                                <select id="municipality" name="municipality" class="form-control" required style="padding-left: 2.5rem;">
                                    <option value="">Select Municipality</option>
                                    <?php
                                    $municipalities = getAllMunicipalities();
                                    foreach ($municipalities as $mun) {
                                        echo "<option value='" . htmlspecialchars($mun) . "'>" . htmlspecialchars($mun) . "</option>";
                                    }
                                    ?>
                                </select>
                            </div>
                        </div>
                        <div class="form-group">
                            <label for="barangay">Barangay</label>
                            <div class="input-group">
                                <i class="fas fa-map-marker-alt"></i>
                                <select id="barangay" name="barangay" class="form-control" required style="padding-left: 2.5rem;" disabled>
                                    <option value="">Select Barangay</option>
                                </select>
                            </div>
                        </div>
                    </div>
                </div>

                <!-- Personal Information Section -->
                <div class="form-section">
                    <h3>Personal Information</h3>
                    <div class="form-row">
                        <div class="form-group">
                            <label for="firstname">First Name</label>
                            <div class="input-group">
                                <i class="fas fa-user"></i>
                                <input type="text" id="firstname" name="firstname" class="form-control" required onkeypress="return /[a-zA-Z\s]/.test(event.key)">
                            </div>
                        </div>
                        <div class="form-group">
                            <label for="lastname">Last Name</label>
                            <div class="input-group">
                                <i class="fas fa-user"></i>
                                <input type="text" id="lastname" name="lastname" class="form-control" required onkeypress="return /[a-zA-Z\s]/.test(event.key)">
                            </div>
                        </div>
                        <div class="form-group">
                            <label for="middlename">Middle Name</label>
                            <div class="input-group">
                                <i class="fas fa-user"></i>
                                <input type="text" id="middlename" name="middlename" class="form-control" onkeypress="return /[a-zA-Z\s]/.test(event.key)">
                            </div>
                        </div>
                    </div>
                    <div class="form-row">
                        <div class="form-group">
                            <label for="sex">Sex</label>
                            <div class="input-group">
                                <i class="fas fa-venus-mars"></i>
                                <select id="sex" name="sex" class="form-control" required style="padding-left: 2.5rem;">
                                    <option value="">Select Sex</option>
                                    <option value="male">Male</option>
                                    <option value="female">Female</option>
                                </select>
                            </div>
                        </div>
                        <div class="form-group">
                            <label for="civil_status">Civil Status</label>
                            <div class="input-group">
                                <i class="fas fa-ring"></i>
                                <select id="civil_status" name="civil_status" class="form-control" required style="padding-left: 2.5rem;">
                                    <option value="">Select Status</option>
                                    <option value="single">Single</option>
                                    <option value="married">Married</option>
                                    <option value="divorced">Divorced</option>
                                    <option value="widowed">Widowed</option>
                                </select>
                            </div>
                        </div>
                        <div class="form-group">
                            <label for="birthdate">Birthdate</label>
                            <div class="input-group">
                                <i class="fas fa-calendar-alt"></i>
                                <input type="date" id="birthdate" name="birthdate" class="form-control" required>
                            </div>
                            <div class="validation-text" id="birthdate-validation">Must be a valid age</div>
                        </div>
                    </div>
                    <div class="form-row">
                        <div class="form-group">
                            <label for="place_of_birth">Place of Birth</label>
                            <div class="input-group">
                                <i class="fas fa-map-pin"></i>
                                <input type="text" id="place_of_birth" name="place_of_birth" class="form-control" required>
                            </div>
                        </div>
                    </div>
                    <div class="form-row">
                        <div class="form-group">
                            <label for="contact_no">Contact No.</label>
                            <div class="input-group">
                                <i class="fas fa-phone"></i>
                                <input type="text" id="contact_no" name="contact_no" class="form-control" required onkeypress="return /[0-9]/.test(event.key)" maxlength="11">
                            </div>
                            <div class="validation-text" id="contact-validation">Must be a valid Philippine mobile number (e.g., 09XXXXXXXXX)</div>
                        </div>
                        <div class="form-group">
                            <label for="reg-email">Email Address</label>
                            <div class="input-group">
                                <i class="fas fa-envelope"></i>
                                <input type="email" id="reg-email" name="email" class="form-control" required onInput="updateEmailValidation()">
                            </div>
                            <div class="validation-text" id="email-validation">Please enter an email address</div>
                        </div>
                    </div>
                    <div class="form-row">
                        <div class="form-group">
                            <label for="reg-password">Password</label>
                            <div class="input-group">
                                <i class="fas fa-lock"></i>
                                <input type="password" id="reg-password" name="password" class="form-control" required pattern="(?=.*[A-Z])(?=.*[0-9]).*" title="Password must contain at least one uppercase letter and one number" onkeyup="validatePasswords()">
                                <i class="fas fa-eye toggle-password" onclick="togglePassword('reg-password')"></i>
                            </div>
                            <div class="validation-text" id="password-validation">Must contain at least one uppercase letter and one number</div>
                        </div>
                        <div class="form-group">
                            <label for="reg-confirm-password">Confirm Password</label>
                            <div class="input-group">
                                <i class="fas fa-lock"></i>
                                <input type="password" id="reg-confirm-password" name="confirm_password" class="form-control" required onkeyup="validatePasswords()">
                                <i class="fas fa-eye toggle-password" onclick="togglePassword('reg-confirm-password')"></i>
                            </div>
                            <div class="validation-text" id="confirm-password-validation"></div>
                        </div>
                    </div>
                </div>

                <div class="register-checks-actions" style="display: flex; flex-direction: column; align-items: flex-start; gap: 0.5rem; width: 100%;">
                    <div class="form-group" style="margin-bottom: 0.5rem; width: 100%;">
                        <label style="display: flex; align-items: center; font-size: 0.95rem;">
                            <input type="checkbox" id="agree_terms" style="margin-right: 0.5rem;">
                            I Agree To <a href="#" id="termsLink" style="color: var(--primary-color); text-decoration: underline; cursor: pointer; margin-left: 0.2rem;">Terms and Conditions</a>
                        </label>
                    </div>
                    <div class="form-group" style="margin-bottom: 1rem; width: 100%;">
                        <label style="display: flex; align-items: center; font-size: 0.95rem;">
                            <input type="checkbox" id="agree_privacy" style="margin-right: 0.5rem;">
                            I hereby consent to the collection and use of my data in compliance with the Data Privacy Act of 2012.
                        </label>
                    </div>
                    <div class="form-buttons" style="width: 100%; display: flex; flex-direction: row; gap: 1rem;">
                        <button type="button" class="cancel-btn" onclick="hideRegisterPopup()">Cancel</button>
                        <button type="submit" class="register-btn" name="register" id="registerBtn" disabled style="opacity: 0.6; cursor: not-allowed;"><span>Register</span></button>
                    </div>
                </div>
            </form>
        </div>
    </div>

    <!-- OTP Popup -->
    <div class="otp-popup" id="otpPopup" <?php echo (isset($_SESSION['show_otp_popup']) && $_SESSION['show_otp_popup']) ? 'style="display: flex;"' : ''; ?>>
        <div class="otp-container">
            <button class="close-btn" onclick="hideOTPPopup()">×</button>
            <div class="otp-header">
                <h1>Verify OTP</h1>
                <p>Please enter the OTP sent to your email</p>
            </div>
            
            <?php if (isset($_SESSION['otp_error'])): ?>
            <div class="error-message">
                <?php echo htmlspecialchars($_SESSION['otp_error']); ?>
            </div>
            <?php endif; ?>
            
            <form action="login.php" method="POST" id="otpForm">
                <div class="form-group">
                    <label for="otp">One-Time Password (OTP)</label>
                    <div class="input-group">
                        <i class="fas fa-key"></i>
                        <input type="text" id="otp" name="otp" class="form-control" required maxlength="6" pattern="\d{6}" placeholder="Enter 6-digit OTP">
                    </div>
                    <?php if (isset($_SESSION['otp_validation_error'])): ?>
                    <div class="otp-error-message">
                        <?php echo htmlspecialchars($_SESSION['otp_validation_error']); ?>
                    </div>
                    <?php endif; ?>
                </div>
                
                <div class="form-buttons">
                    <button type="button" class="cancel-btn" onclick="hideOTPPopup()">Cancel</button>
                    <button type="submit" class="otp-btn" name="verify_otp"><span>Verify</span></button>
                </div>
            </form>
        </div>
    </div>

    <!-- Terms and Conditions Modal -->
    <div id="termsModal" style="display:none; position:fixed; z-index:2000; left:0; top:0; width:100vw; height:100vh; background:rgba(0,0,0,0.5); align-items:center; justify-content:center;">
        <div style="background:#fff; padding:2rem; border-radius:10px; max-width:600px; width:90%; position:relative;">
            <span onclick="closeTermsModal()" style="position:absolute; top:10px; right:20px; font-size:2rem; cursor:pointer;">&times;</span>
            <h2 style="margin-bottom:1rem;">Terms and Conditions</h2>
            <div style="max-height:60vh; overflow-y:auto; text-align:left; font-size:1rem;">
                <ol>
                    <li><b>Eligibility:</b> Only qualified students may apply for the scholarship. Providing false information will result in disqualification.</li>
                    <li><b>Document Submission:</b> All required documents must be submitted in the specified format and within the application period.</li>
                    <li><b>Data Usage:</b> Your personal data will be used solely for scholarship processing and will not be shared with unauthorized parties.</li>
                    <li><b>Application Review:</b> Submission does not guarantee approval. All applications are subject to review and verification by the administrators.</li>
                    <li><b>Notification:</b> Applicants will be notified of their application status via the portal and/or email.</li>
                    <li><b>Claiming Scholarship:</b> Approved applicants must present the required documents and QR code to claim their scholarship.</li>
                    <li><b>Changes to Terms:</b> The scholarship provider reserves the right to modify these terms at any time. Continued use of the portal constitutes acceptance of any changes.</li>
                    <li><b>Email Validation:</b> Please use a valid email domain (e.g., gmail.com, yahoo.com, outlook.com, hotmail.com, live.com, aol.com, icloud.com, msn.com, me.com, mac.com, googlemail.com)</li>
                </ol>
                <p style="margin-top:1rem; font-size:0.95rem; color:#666;">By registering, you acknowledge that you have read, understood, and agreed to these terms and conditions.</p>
            </div>
        </div>
    </div>

    <script>
        function showRegisterPopup() {
            console.log("showRegisterPopup called"); 
            const registerPopup = document.getElementById('registerPopup');
            if (registerPopup) {
                registerPopup.style.display = 'flex';
                setTimeout(() => {
                    registerPopup.classList.add('active');
                }, 10);
            } else {
                console.error("registerPopup element not found");
            }
        }

        function hideRegisterPopup() {
            console.log("hideRegisterPopup called"); 
            const registerPopup = document.getElementById('registerPopup');
            const otpPopup = document.getElementById('otpPopup');
            
            if (registerPopup) {
                registerPopup.classList.remove('active');
                setTimeout(() => {
                    registerPopup.style.display = 'none';
                }, 300);
            }
            
            if (otpPopup) {
                otpPopup.classList.remove('active');
                setTimeout(() => {
                    otpPopup.style.display = 'none';
                }, 300);
            }

            fetch('clear_session.php', {
                method: 'POST',
                headers: {
                    'Content-Type': 'application/x-www-form-urlencoded',
                },
                body: 'action=clear_register'
            }).then(response => response.text()).then(data => {
                console.log(data);
            });
        }

        function showOTPPopup() {
            console.log("showOTPPopup called"); 
            const otpPopup = document.getElementById('otpPopup');
            if (otpPopup) {
                otpPopup.style.display = 'flex';
                setTimeout(() => {
                    otpPopup.classList.add('active');
                }, 10);
            } else {
                console.error("otpPopup element not found");
            }
        }

        function hideOTPPopup() {
            console.log("hideOTPPopup called"); 
            const otpPopup = document.getElementById('otpPopup');
            const registerPopup = document.getElementById('registerPopup');
            
            if (otpPopup) {
                otpPopup.classList.remove('active');
                setTimeout(() => {
                    otpPopup.style.display = 'none';
                }, 300);
            }
            
            if (registerPopup) {
                registerPopup.classList.remove('active');
                setTimeout(() => {
                    registerPopup.style.display = 'none';
                }, 300);
            }

            fetch('clear_session.php', {
                method: 'POST',
                headers: {
                    'Content-Type': 'application/x-www-form-urlencoded',
                },
                body: 'action=clear_otp'
            }).then(response => response.text()).then(data => {
                console.log(data);
            });
        }

        function togglePassword(inputId) {
            console.log("togglePassword called for inputId:", inputId);
            const input = document.getElementById(inputId);
            const icon = input.nextElementSibling;
            if (!input || !icon) {
                console.error("Input or icon not found for inputId:", inputId);
                return;
            }
            if (input.type === 'password') {
                input.type = 'text';
                icon.classList.remove('fa-eye');
                icon.classList.add('fa-eye-slash');
            } else {
                input.type = 'password';
                icon.classList.remove('fa-eye-slash');
                icon.classList.add('fa-eye');
            }
        }

        document.addEventListener('DOMContentLoaded', function() {
            const passwordInput = document.getElementById('reg-password');
            const requirementsText = document.getElementById('password-validation');

            if (passwordInput && requirementsText) {
                passwordInput.addEventListener('input', function() {
                    const password = passwordInput.value;
                    const hasUppercase = /[A-Z]/.test(password);
                    const hasNumber = /[0-9]/.test(password);

                    if (hasUppercase && hasNumber) {
                        requirementsText.classList.remove('invalid');
                    } else {
                        requirementsText.classList.add('invalid');
                    }
                });
            } else {
                console.error("Password input or requirements text element not found");
            }

            // Check if OTP popup should be shown
            <?php if (isset($_SESSION['show_otp_popup']) && $_SESSION['show_otp_popup']): ?>
            showOTPPopup();
            <?php endif; ?>

            // Check if register popup should be shown
            <?php if (isset($_SESSION['show_register_popup']) && $_SESSION['show_register_popup']): ?>
            showRegisterPopup();
            <?php endif; ?>
        });

        function validateNameInput(input) {
            const value = input.value;
            const hasNumber = /[0-9]/.test(value);
            const hasSpecialChar = /[^a-zA-Z\s\-\']/.test(value);
            const validationText = document.getElementById(input.id + '-validation');
            
            if (hasNumber || hasSpecialChar) {
                input.setCustomValidity('Name should not contain numbers or special characters');
                validationText.classList.add('invalid');
                validationText.classList.remove('valid');
            } else {
                input.setCustomValidity('');
                validationText.classList.remove('invalid');
                validationText.classList.add('valid');
            }
        }

        function validatePhoneNumber(input) {
            const value = input.value.replace(/[\s\-\(\)]/g, '');
            const isValid = /^(\+63|09)\d{9}$/.test(value);
            const validationText = document.getElementById('contact-validation');
            
            if (!isValid) {
                input.setCustomValidity('Please enter a valid Philippine mobile number (e.g., 09XXXXXXXXX or +639XXXXXXXXX)');
                validationText.classList.add('invalid');
                validationText.classList.remove('valid');
            } else {
                input.setCustomValidity('');
                validationText.classList.remove('invalid');
                validationText.classList.add('valid');
            }
        }

        function validateBirthdate(input) {
            const birthdate = new Date(input.value);
            const today = new Date();
            const age = today.getFullYear() - birthdate.getFullYear();
            const validationText = document.getElementById('birthdate-validation');
            
            if (age < 16 || age > 100) {
                input.setCustomValidity('You must be a valid age');
                validationText.classList.add('invalid');
                validationText.classList.remove('valid');
            } else {
                input.setCustomValidity('');
                validationText.classList.remove('invalid');
                validationText.classList.add('valid');
            }
        }

        function validatePassword(input) {
            const password = input.value;
            const hasUppercase = /[A-Z]/.test(password);
            const hasNumber = /[0-9]/.test(password);
            const validationText = document.getElementById('password-validation');
            
            if (!hasUppercase || !hasNumber) {
                input.setCustomValidity('Password must contain at least one uppercase letter and one number');
                validationText.classList.add('invalid');
                validationText.classList.remove('valid');
            } else {
                input.setCustomValidity('');
                validationText.classList.remove('invalid');
                validationText.classList.add('valid');
            }
        }

        // Add event listeners for real-time validation
        document.addEventListener('DOMContentLoaded', function() {
            const nameInputs = document.querySelectorAll('input[name="firstname"], input[name="lastname"], input[name="middlename"]');
            nameInputs.forEach(input => {
                input.addEventListener('input', () => validateNameInput(input));
            });

            const phoneInput = document.querySelector('input[name="contact_no"]');
            if (phoneInput) {
                phoneInput.addEventListener('input', () => validatePhoneNumber(phoneInput));
            }

            const birthdateInput = document.querySelector('input[name="birthdate"]');
            if (birthdateInput) {
                birthdateInput.addEventListener('change', () => validateBirthdate(birthdateInput));
            }

            // Only add password validation to registration password field
            const passwordInput = document.getElementById('reg-password');
            if (passwordInput) {
                passwordInput.addEventListener('input', () => validatePassword(passwordInput));
            }
        });

        // Add dynamic municipality and barangay handling
        document.addEventListener('DOMContentLoaded', function() {
            const municipalitySelect = document.getElementById('municipality');
            const barangaySelect = document.getElementById('barangay');

            if (municipalitySelect && barangaySelect) {
                municipalitySelect.addEventListener('change', function() {
                    const selectedMunicipality = this.value;
                    
                    // Reset barangay dropdown
                    barangaySelect.innerHTML = '<option value="">Select Barangay</option>';
                    barangaySelect.disabled = true;

                    if (selectedMunicipality) {
                        // Show loading state
                        barangaySelect.disabled = true;
                        barangaySelect.innerHTML = '<option value="">Loading...</option>';

                        // Fetch barangays for selected municipality
                        const formData = new FormData();
                        formData.append('action', 'get_barangays');
                        formData.append('municipality', selectedMunicipality);

                        fetch('login.php', {
                            method: 'POST',
                            body: formData
                        })
                        .then(response => {
                            if (!response.ok) {
                                throw new Error('Network response was not ok');
                            }
                            return response.text().then(text => {
                                try {
                                    return JSON.parse(text);
                                } catch (e) {
                                    console.error('Server response:', text);
                                    throw new Error('Invalid JSON response from server');
                                }
                            });
                        })
                        .then(data => {
                            if (data.error) {
                                throw new Error(data.error);
                            }
                            
                            // Populate barangay dropdown
                            barangaySelect.innerHTML = '<option value="">Select Barangay</option>';
                            data.forEach(barangay => {
                                const option = document.createElement('option');
                                option.value = barangay;
                                option.textContent = barangay;
                                barangaySelect.appendChild(option);
                            });
                            barangaySelect.disabled = false;
                        })
                        .catch(error => {
                            console.error('Error fetching barangays:', error);
                            barangaySelect.innerHTML = '<option value="">Error: ' + error.message + '</option>';
                            barangaySelect.disabled = true;
                        });
                    }
                });
            }
        });

        // Terms and Privacy Checkbox Logic
        document.addEventListener('DOMContentLoaded', function() {
            var agreeTerms = document.getElementById('agree_terms');
            var agreePrivacy = document.getElementById('agree_privacy');
            var registerBtn = document.getElementById('registerBtn');
            function updateRegisterBtn() {
                if (agreeTerms.checked && agreePrivacy.checked) {
                    registerBtn.disabled = false;
                    registerBtn.style.opacity = '1';
                    registerBtn.style.cursor = 'pointer';
                } else {
                    registerBtn.disabled = true;
                    registerBtn.style.opacity = '0.6';
                    registerBtn.style.cursor = 'not-allowed';
                }
            }
            if (agreeTerms && agreePrivacy && registerBtn) {
                agreeTerms.addEventListener('change', updateRegisterBtn);
                agreePrivacy.addEventListener('change', updateRegisterBtn);
                updateRegisterBtn();
            }
            // Terms Modal
            var termsLink = document.getElementById('termsLink');
            var termsModal = document.getElementById('termsModal');
            if (termsLink && termsModal) {
                termsLink.addEventListener('click', function(e) {
                    e.preventDefault();
                    termsModal.style.display = 'flex';
                });
            }
        });
        function closeTermsModal() {
            var termsModal = document.getElementById('termsModal');
            if (termsModal) termsModal.style.display = 'none';
        }

        function validatePasswords() {
            const password = document.getElementById('reg-password').value;
            const confirmPassword = document.getElementById('reg-confirm-password').value;
            const confirmValidation = document.getElementById('confirm-password-validation');
            const registerBtn = document.getElementById('registerBtn');
            
            if (confirmPassword === '') {
                confirmValidation.textContent = 'Please confirm your password';
                confirmValidation.className = 'validation-text';
                return false;
            } else if (password !== confirmPassword) {
                confirmValidation.textContent = 'Passwords do not match';
                confirmValidation.className = 'validation-text invalid';
                return false;
            } else {
                confirmValidation.textContent = 'Passwords match';
                confirmValidation.className = 'validation-text valid';
                return true;
            }
        }

        function validateForm() {
            const requiredFields = document.querySelectorAll('#registerForm [required]');
            const agreeTerms = document.getElementById('agree_terms');
            const agreePrivacy = document.getElementById('agree_privacy');
            const registerBtn = document.getElementById('registerBtn');
            const password = document.getElementById('reg-password');
            const confirmPassword = document.getElementById('reg-confirm-password');
            const emailInput = document.getElementById('reg-email');
            
            let isValid = true;
            
            // Check all required fields
            requiredFields.forEach(field => {
                if (!field.value) isValid = false;
            });
            
            // Check passwords match
            if (password.value !== confirmPassword.value) isValid = false;
            
            // Check terms and privacy
            if (!agreeTerms.checked || !agreePrivacy.checked) isValid = false;
            
            // Check email validity
            if (emailInput && !validateEmail(emailInput.value)) isValid = false;
            
            // Enable/disable register button
            registerBtn.disabled = !isValid;
            registerBtn.style.opacity = isValid ? '1' : '0.6';
            registerBtn.style.cursor = isValid ? 'pointer' : 'not-allowed';
                    registerBtn.disabled = !isValid;
        }

        // Add event listeners for form validation
        document.addEventListener('DOMContentLoaded', function() {
            const form = document.getElementById('registerForm');
            const inputs = form.querySelectorAll('input, select');
            
            inputs.forEach(input => {
                input.addEventListener('input', validateForm);
                input.addEventListener('change', validateForm);
            });
            
            // Initial validation
            validateForm();
        });

        // List of valid email domains
        const validEmailDomains = [
            /* Popular Email Services */
            'gmail.com', 'yahoo.com', 'outlook.com', 'hotmail.com', 'live.com', 'aol.com', 'icloud.com',
            /* Academic Domains */
            'edu.ph', 'edu', 'ac.uk', 'student.edu',
            /* Business/Professional */
            'msn.com', 'me.com', 'mac.com', 'googlemail.com',
            /* Regional Yahoo Domains */
            'yahoo.com.ph', 'yahoo.co.uk', 'yahoo.co.jp', 'yahoo.fr', 'yahoo.de', 'yahoo.it', 'yahoo.es', 'yahoo.ca',
            /* Regional Domains */
            'ymail.com', 'rocketmail.com'
        ];

        function validateEmail(email) {
            if (!email) return false;
            
            // Basic email format validation
            const emailRegex = /^[^\s@]+@[^\s@]+\.[^\s@]+$/;
            if (!emailRegex.test(email)) return false;

            // Extract domain from email
            const domain = email.split('@')[1].toLowerCase();
            
            // Check if domain is in valid domains list
            return validEmailDomains.includes(domain);
        }

        function updateEmailValidation() {
            const emailInput = document.getElementById('reg-email');
            const emailValidation = document.getElementById('email-validation');
            const registerBtn = document.getElementById('registerBtn');
            const agreeTerms = document.getElementById('agree_terms');
            const agreePrivacy = document.getElementById('agree_privacy');

            if (emailInput && emailValidation) {
                const email = emailInput.value;
                const isValidEmail = validateEmail(email);

                if (!email) {
                    emailValidation.textContent = 'Please enter an email address';
                    emailValidation.className = 'validation-text';
                    emailInput.setCustomValidity('Please enter an email address');
                } else if (!isValidEmail) {
                    emailValidation.textContent = 'Please use a valid email domain';
                    emailValidation.className = 'validation-text invalid';
                    emailInput.setCustomValidity('Please use a valid email domain');
                } else {
                    emailValidation.textContent = 'Valid email domain';
                    emailValidation.className = 'validation-text valid';
                    emailInput.setCustomValidity('');
                }

                // Update register button state
                if (registerBtn) {
                    const canRegister = isValidEmail && agreeTerms.checked && agreePrivacy.checked;
                    registerBtn.disabled = !canRegister;
                    registerBtn.style.opacity = canRegister ? '1' : '0.6';
                    registerBtn.style.cursor = canRegister ? 'pointer' : 'not-allowed';
                    registerBtn.disabled = !canRegister;
                }
            }
        }
    </script>
</body>
</html>