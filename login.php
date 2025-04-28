<?php
require './route_guard.php';
require 'vendor/autoload.php'; 

use PHPMailer\PHPMailer\PHPMailer;
use PHPMailer\PHPMailer\Exception;

if (session_status() === PHP_SESSION_NONE) {
    session_start();
}

function generateToken() {
    return bin2hex(random_bytes(32)); 
}

function generateOTP() {
    return str_pad(mt_rand(0, 999999), 6, '0', STR_PAD_LEFT);
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

if ($_SERVER['REQUEST_METHOD'] == 'POST' && isset($_POST['register']) && !isset($_POST['verify_otp'])) {
    $municipality = trim($_POST['municipality']);
    $barangay = trim($_POST['barangay']);
    $firstname = trim($_POST['firstname']);
    $lastname = trim($_POST['lastname']);
    $middlename = trim($_POST['middlename']);
    $sex = trim($_POST['sex']);
    $civil_status = trim($_POST['civil_status']);
    $nationality = trim($_POST['nationality']);
    $birthdate = trim($_POST['birthdate']);
    $place_of_birth = trim($_POST['place_of_birth']);
    $contact_no = trim($_POST['contact_no']);
    $email = trim($_POST['email']);
    $username = trim($_POST['username']);
    $password = trim($_POST['password']);
    $confirm_password = trim($_POST['confirm_password']);

    $register_error = '';

    if (!preg_match('/[A-Z]/', $password) || !preg_match('/[0-9]/', $password)) {
        $register_error = "Password must contain at least one uppercase letter and one number.";
    }

    if ($password !== $confirm_password) {
        $register_error = "Passwords do not match.";
    }

    $stmt = $pdo->prepare("SELECT id FROM users WHERE email = ?");
    $stmt->execute([$email]);
    if ($stmt->fetch()) {
        $register_error = "Email is already registered.";
    }

    $stmt = $pdo->prepare("SELECT id FROM users WHERE username = ?");
    $stmt->execute([$username]);
    if ($stmt->fetch()) {
        $register_error = "Username is already taken.";
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
            'nationality' => $nationality,
            'birthdate' => $birthdate,
            'place_of_birth' => $place_of_birth,
            'contact_no' => $contact_no,
            'email' => $email,
            'username' => $username,
            'password' => $password,
            'otp' => $otp,
            'otp_expires_at' => $expires_at
        ];

        $email_result = sendOTP($email, $otp);
        if ($email_result !== true) {
            $register_error = $email_result;
            unset($_SESSION['pending_registration']);
            $_SESSION['show_register_popup'] = true; 
        } else {
            $_SESSION['otp_email'] = $email;
            $_SESSION['show_otp_popup'] = true;
            $_SESSION['show_register_popup'] = true; 
        }
    }

    if (!empty($register_error)) {
        $_SESSION['register_error'] = $register_error;
        $_SESSION['show_register_popup'] = true; 
    }
}

if ($_SERVER['REQUEST_METHOD'] == 'POST' && isset($_POST['verify_otp'])) {

    if (!isset($_SESSION['pending_registration'])) {
        $_SESSION['otp_error'] = "Registration data not found. Please try registering again.";
        unset($_SESSION['otp_email']);
        unset($_SESSION['show_otp_popup']);
        $_SESSION['show_register_popup'] = false; 
    } else {
        $otp_code = trim($_POST['otp']);
        $email = $_SESSION['otp_email'];
        $pending_data = $_SESSION['pending_registration'];

        $otp_input_str = (string)$otp_code;
 
        error_log("OTP Verification Attempt - Email: $email, Input OTP: $otp_code, Session OTP: " . $pending_data['otp'] . ", Expires At: " . $pending_data['otp_expires_at'] . ", Current Time: " . time() . ", Expires Timestamp: " . strtotime($pending_data['otp_expires_at']));

        if ($otp_input_str != $db_otp_str || strtotime($pending_data['otp_expires_at']) <= time()) {

            $_SESSION['otp_validation_error'] = "Wrong OTP, Please try again";
            error_log("OTP Verification Failed - Email: $email, Input OTP: $otp_code");
            $_SESSION['show_otp_popup'] = true; 
            $_SESSION['show_register_popup'] = true; 
        } else {

            try {
                $pdo->beginTransaction();

                $hashed_password = password_hash($pending_data['password'], PASSWORD_DEFAULT);
                $stmt = $pdo->prepare("
                    INSERT INTO users (
                        firstname, lastname, middlename, contact_no, email, username, password, role, otp, otp_expires_at, otp_verified
                    ) VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?)
                ");
                $stmt->execute([
                    $pending_data['firstname'],
                    $pending_data['lastname'],
                    $pending_data['middlename'],
                    $pending_data['contact_no'],
                    $pending_data['email'],
                    $pending_data['username'],
                    $hashed_password,
                    'Applicant',
                    $pending_data['otp'],
                    $pending_data['otp_expires_at'],

                ]);

                $user_id = $pdo->lastInsertId();

                $stmt = $pdo->prepare("
                    INSERT INTO users_info (
                        user_id, municipality, barangay, sex, civil_status, nationality, birthdate, place_of_birth
                    ) VALUES (?, ?, ?, ?, ?, ?, ?, ?)
                ");
                $stmt->execute([
                    $user_id,
                    $pending_data['municipality'],
                    $pending_data['barangay'],
                    $pending_data['sex'],
                    $pending_data['civil_status'],
                    $pending_data['nationality'],
                    $pending_data['birthdate'],
                    $pending_data['place_of_birth']
                ]);

                $pdo->commit();

                $_SESSION['login_success'] = "Registered Successfully, Please Login";

                unset($_SESSION['pending_registration']);
                unset($_SESSION['otp_email']);
                $_SESSION['show_otp_popup'] = false; 
                $_SESSION['show_register_popup'] = false; 
            } catch (PDOException $e) {
                $pdo->rollBack();
                $_SESSION['otp_error'] = "Failed to complete registration: " . $e->getMessage();
                error_log("Registration Error: " . $e->getMessage());
                $_SESSION['show_otp_popup'] = true; 
                $_SESSION['show_register_popup'] = true; 
            }
        }
    }
}


if ($_SERVER['REQUEST_METHOD'] == 'POST' && !isset($_POST['register']) && !isset($_POST['verify_otp'])) {
    $email = $_POST['email'];
    $password = $_POST['password'];

    $stmt = $pdo->prepare("SELECT id, firstname, lastname, middlename, role, password FROM users WHERE email = ?");
    $stmt->execute([$email]);
    $user = $stmt->fetch(PDO::FETCH_ASSOC);

    if ($user && password_verify($password, $user['password'])) {

        $token = generateToken();
        $expires_at = date('Y-m-d H:i:s', strtotime('+30 minutes'));

        try {

            $stmt = $pdo->prepare("
                INSERT INTO tokens (user_id, token, expires_at)
                VALUES (?, ?, ?)
            ");
            $result = $stmt->execute([$user['id'], $token, $expires_at]);
        } catch (PDOException $e) {
            $login_error = "Failed to create session token: " . $e->getMessage();
            error_log("Token Insert Error: " . $e->getMessage());
        }

        if (empty($login_error)) {

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
        }
    } else {
        $login_error = "Invalid email or password";
    }
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
            --primary-color: #4f46e5;
            --primary-hover: #4338ca;
            --bg-color: #f9fafb;
            --card-bg: rgba(255, 255, 255, 0.95);
            --text-color: #1f2937;
            --text-muted: #6b7280;
            --border-color: #e5e7eb;
            --error-color: #ef4444;
            --success-color: #22c55e;
        }

        * {
            margin: 0;
            padding: 0;
            box-sizing: border-box;
            font-family: 'Poppins', sans-serif;
        }

        body {
            background: url('./images/gc1.jpg') no-repeat center center fixed;
            background-size: cover;
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
            background: rgba(0, 0, 0, 0.2);
            z-index: 1;
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

        .login-container, .register-container, .otp-container {
            background-color: var(--card-bg);
            border-radius: 12px;
            box-shadow: 0 4px 6px rgba(0, 0, 0, 0.05), 0 10px 15px rgba(0, 0, 0, 0.03);
            width: 100%;
            max-width: 900px;
            padding: 2rem;
            backdrop-filter: blur(20px);
            -webkit-backdrop-filter: blur(10px);
        }

        .otp-container {
            max-width: 500px;
        }

        .login-header, .register-header, .otp-header {
            text-align: center;
            margin-bottom: 2rem;
        }

        .login-header h1, .register-header h1, .otp-header h1 {
            font-size: 1.75rem;
            font-weight: 600;
            color: var(--text-color);
        }

        .login-header p, .register-header p, .otp-header p {
            color: var(--text-muted);
            font-size: 0.9rem;
            margin-top: 0.5rem;
        }

        .form-group {
            margin-bottom: 1.25rem;
        }

        .form-group label {
            display: block;
            margin-bottom: 0.5rem;
            font-weight: 500;
            font-size: 0.9rem;
            color: var(--text-color);
        }

        .input-group {
            position: relative;
        }

        .input-group i {
            position: absolute;
            top: 50%;
            left: 1rem;
            transform: translateY(-50%);
            color: var(--text-muted);
        }

        .form-control {
            width: 100%;
            padding: 0.75rem 1rem 0.75rem 2.5rem;
            border: 1px solid var(--border-color);
            border-radius: 8px;
            font-size: 1rem;
            transition: all 0.3s ease;
            background-color: rgba(255, 255, 255, 0.8);
        }

        .form-control:focus {
            outline: none;
            border-color: var(--primary-color);
            box-shadow: 0 0 0 3px rgba(79, 70, 229, 0.1);
        }

        .login-btn, .register-btn, .otp-btn {
            width: 100%;
            background-color: var(--primary-color);
            color: white;
            border: none;
            border-radius: 8px;
            padding: 0.75rem 1rem;
            font-size: 1rem;
            font-weight: 500;
            cursor: pointer;
            transition: background-color 0.3s ease;
        }

        .login-btn:hover, .register-btn:hover, .otp-btn:hover {
            background-color: var(--primary-hover);
        }

        .forgot-password {
            display: block;
            text-align: center;
            margin-top: 1rem;
            color: var(--primary-color);
            text-decoration: none;
            font-size: 0.9rem;
        }

        .forgot-password:hover {
            text-decoration: underline;
        }

        .error-message {
            background-color: rgba(239, 68, 68, 0.1);
            color: var(--error-color);
            padding: 0.75rem;
            border-radius: 8px;
            margin-bottom: 1.25rem;
            font-size: 0.9rem;
        }

        .success-message {
            background-color: rgba(34, 197, 94, 0.1);
            color: var(--success-color);
            padding: 0.75rem;
            border-radius: 8px;
            margin-bottom: 1.25rem;
            font-size: 0.9rem;
        }

        .otp-error-message {
            color: var(--error-color);
            font-size: 0.85rem;
            margin-top: 0.25rem;
        }

        .register-link {
            text-align: center;
            margin-top: 1rem;
            font-size: 0.9rem;
        }

        .register-link a {
            color: var(--primary-color);
            text-decoration: none;
            cursor: pointer;
        }

        .register-link a:hover {
            text-decoration: underline;
        }

        /* Popup Styles */
        .register-popup, .otp-popup {
            display: none;
            position: fixed;
            top: 0;
            left: 0;
            width: 100%;
            height: 100%;
            background: rgba(0, 0, 0, 0.5);
            z-index: 1000;
            align-items: center;
            justify-content: center;
        }

        .register-container, .otp-container {
            position: relative;
            max-height: 90vh;
            overflow-y: auto;
        }

        .close-btn {
            position: absolute;
            top: 10px;
            right: 10px;
            font-size: 1.5rem;
            color: var(--text-muted);
            cursor: pointer;
            background: none;
            border: none;
            padding: 0;
        }

        .close-btn:hover {
            color: var(--text-color);
        }

        /* Registration Form Styles */
        .form-section {
            margin-bottom: 2rem;
        }

        .form-section h3 {
            font-size: 1.2rem;
            font-weight: 600;
            color: var(--primary-color);
            margin-bottom: 1rem;
            border-bottom: 2px solid var(--primary-color);
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
            background-color: #6b7280;
            color: white;
            border: none;
            border-radius: 8px;
            padding: 0.75rem 1rem;
            font-size: 1rem;
            font-weight: 500;
            cursor: pointer;
            transition: background-color 0.3s ease;
        }

        .form-buttons .cancel-btn:hover {
            background-color: #5a6268;
        }

        .password-requirements {
            font-size: 0.85rem;
            color: var(--text-muted);
            margin-top: 0.25rem;
        }

        .password-requirements.invalid {
            color: var(--error-color);
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
                <h1>Login Page</h1>
                <p>Please enter your credentials to login</p>
            </div>
            
            <?php if (isset($login_error)): ?>
            <div class="error-message">
                <?php echo htmlspecialchars($login_error); ?>
            </div>
            <?php endif; ?>
            
            <?php if (isset($_SESSION['login_success'])): ?>
            <div class="success-message">
                <?php echo htmlspecialchars($_SESSION['login_success']); ?>
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
                    <label for="password">Password</label>
                    <div class="input-group">
                        <i class="fas fa-lock"></i>
                        <input type="password" id="password" name="password" class="form-control" required>
                    </div>
                </div>
                
                <button type="submit" class="login-btn">Sign In</button>
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
            
            <form action="login.php" method="POST">
                <!-- Residency Section -->
                <div class="form-section">
                    <h3>Residency</h3>
                    <div class="form-row">
                        <div class="form-group">
                            <label for="municipality">Municipality</label>
                            <div class="input-group">
                                <i class="fas fa-map-marker-alt"></i>
                                <input type="text" id="municipality" name="municipality" class="form-control" required>
                            </div>
                        </div>
                        <div class="form-group">
                            <label for="barangay">Barangay</label>
                            <div class="input-group">
                                <i class="fas fa-map-marker-alt"></i>
                                <input type="text" id="barangay" name="barangay" class="form-control" required>
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
                                <input type="text" id="firstname" name="firstname" class="form-control" required>
                            </div>
                        </div>
                        <div class="form-group">
                            <label for="lastname">Last Name</label>
                            <div class="input-group">
                                <i class="fas fa-user"></i>
                                <input type="text" id="lastname" name="lastname" class="form-control" required>
                            </div>
                        </div>
                        <div class="form-group">
                            <label for="middlename">Middle Name</label>
                            <div class="input-group">
                                <i class="fas fa-user"></i>
                                <input type="text" id="middlename" name="middlename" class="form-control">
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
                                    <option value="other">Other</option>
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
                            <label for="nationality">Nationality</label>
                            <div class="input-group">
                                <i class="fas fa-globe"></i>
                                <input type="text" id="nationality" name="nationality" class="form-control" required>
                            </div>
                        </div>
                        <div class="form-group">
                            <label for="birthdate">Birthdate</label>
                            <div class="input-group">
                                <i class="fas fa-calendar-alt"></i>
                                <input type="date" id="birthdate" name="birthdate" class="form-control" required>
                            </div>
                        </div>
                    </div>
                    <div class="form-row">
                        <div class="form-group full-width">
                            <label for="place_of_birth">Place of Birth</label>
                            <div class="input-group">
                                <i class="fas fa-map-marker-alt"></i>
                                <input type="text" id="place_of_birth" name="place_of_birth" class="form-control" required>
                            </div>
                        </div>
                    </div>
                    <div class="form-row">
                        <div class="form-group">
                            <label for="contact_no">Contact No.</label>
                            <div class="input-group">
                                <i class="fas fa-phone"></i>
                                <input type="tel" id="contact_no" name="contact_no" class="form-control" required>
                            </div>
                        </div>
                        <div class="form-group">
                            <label for="email">Email Address</label>
                            <div class="input-group">
                                <i class="fas fa-envelope"></i>
                                <input type="email" id="email" name="email" class="form-control" required>
                            </div>
                        </div>
                    </div>
                    <div class="form-row">
                        <div class="form-group">
                            <label for="username">Username</label>
                            <div class="input-group">
                                <i class="fas fa-user"></i>
                                <input type="text" id="username" name="username" class="form-control" required>
                            </div>
                        </div>
                        <div class="form-group">
                            <label for="password">Password</label>
                            <div class="input-group">
                                <i class="fas fa-lock"></i>
                                <input type="password" id="password" name="password" class="form-control" required pattern="(?=.*[A-Z])(?=.*[0-9]).*" title="Password must contain at least one uppercase letter and one number">
                            </div>
                            <div id="password-requirements" class="password-requirements">
                                Must contain at least one uppercase letter and one number
                            </div>
                        </div>
                        <div class="form-group">
                            <label for="confirm_password">Confirm Password</label>
                            <div class="input-group">
                                <i class="fas fa-lock"></i>
                                <input type="password" id="confirm_password" name="confirm_password" class="form-control" required>
                            </div>
                        </div>
                    </div>
                </div>

                <!-- Form Buttons -->
                <div class="form-buttons">
                    <button type="button" class="cancel-btn" onclick="hideRegisterPopup()">Cancel</button>
                    <button type="submit" class="register-btn" name="register">Register</button>
                </div>
            </form>
        </div>
    </div>


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
            
            <form action="login.php" method="POST">
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
                    <button type="submit" class="otp-btn" name="verify_otp">Verify</button>
                </div>
            </form>
        </div>
    </div>

    <script>
        function showRegisterPopup() {
            console.log("showRegisterPopup called"); 
            const registerPopup = document.getElementById('registerPopup');
            if (registerPopup) {
                registerPopup.style.display = 'flex';
            } else {
                console.error("registerPopup element not found");
            }
        }

        function hideRegisterPopup() {
            console.log("hideRegisterPopup called"); 
            const registerPopup = document.getElementById('registerPopup');
            const otpPopup = document.getElementById('otpPopup');
            if (registerPopup) registerPopup.style.display = 'none';
            if (otpPopup) otpPopup.style.display = 'none';
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
            console.log("showOTPPopup called"); // Debugging log
            const otpPopup = document.getElementById('otpPopup');
            if (otpPopup) {
                otpPopup.style.display = 'flex';
            } else {
                console.error("otpPopup element not found");
            }
        }

        function hideOTPPopup() {
            console.log("hideOTPPopup called"); // Debugging log
            const otpPopup = document.getElementById('otpPopup');
            const registerPopup = document.getElementById('registerPopup');
            if (otpPopup) otpPopup.style.display = 'none';
            if (registerPopup) registerPopup.style.display = 'none';
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

        document.addEventListener('click', function(event) {
            const registerPopup = document.getElementById('registerPopup');
            const otpPopup = document.getElementById('otpPopup');

            if (event.target === registerPopup) {
                hideRegisterPopup();
            }
            if (event.target === otpPopup) {
                hideOTPPopup();
            }
        });

        document.addEventListener('DOMContentLoaded', function() {
            const passwordInput = document.getElementById('password');
            const requirementsText = document.getElementById('password-requirements');

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
        });
    </script>
</body>
</html>