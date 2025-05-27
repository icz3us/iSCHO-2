<?php
require './route_guard.php';
require 'vendor/autoload.php'; // Load PHPMailer via Composer

use PHPMailer\PHPMailer\PHPMailer;
use PHPMailer\PHPMailer\Exception;

// Start the session if not already started
if (session_status() === PHP_SESSION_NONE) {
    session_start();
}

// Function to generate a secure token
function generateToken() {
    return bin2hex(random_bytes(32)); // Generates a 64-character secure token
}

// Function to send password reset email using PHPMailer
function sendPasswordResetEmail($email, $token) {
    $mail = new PHPMailer(true);

    try {
        // Server settings
        $mail->isSMTP();
        $mail->Host       = 'smtp.gmail.com'; // Replace with your SMTP host
        $mail->SMTPAuth   = true;
        $mail->Username   = 'ischobsit@gmail.com'; // Replace with your SMTP username
        $mail->Password   = 'wcep jxly qzwn ybud'; // Replace with your SMTP password or app-specific password
        $mail->SMTPSecure = PHPMailer::ENCRYPTION_STARTTLS;
        $mail->Port       = 587;

        // Recipients
        $mail->setFrom('ischobsit@gmail.com', 'ISCHO App');
        $mail->addAddress($email);

        $reset_link = "  https://32bf-2001-fd8-b812-d700-9d24-2fe6-269-a01b.ngrok-free.app/ischo2/resetpassword.php?token=" . urlencode($token); // Updated URL

        $mail->isHTML(true);
        $mail->Subject = "Password Reset Request for iSCHO";
        $mail->Body = "
    <div style='font-family: Arial, sans-serif; max-width: 600px; margin: 0 auto; border: 1px solid #e0e0e0; border-radius: 8px;'>
        <!-- Header -->
        <div style='background-color: #4f46e5; padding: 20px; text-align: center; border-top-left-radius: 8px; border-top-right-radius: 8px;'>
            <h1 style='color: #ffffff; margin: 0; font-size: 24px;'>Password Reset Request</h1>
        </div>
        <!-- Body -->
        <div style='padding: 30px; background-color: #ffffff;'>
            <p style='color: #1f2937; font-size: 16px; margin-bottom: 15px;'>Hello,</p>
            <p style='color: #1f2937; font-size: 16px; margin-bottom: 15px;'>We received a request to reset your password for your iSCHO account. Click the button below to reset your password:</p>
            <div style='text-align: center; margin: 20px 0;'>
                <a href='$reset_link' style='display: inline-block; background-color: #4f46e5; color: #ffffff; padding: 15px 25px; border-radius: 5px; font-size: 16px; font-weight: bold; text-decoration: none;'>Reset Password</a>
            </div>
            <p style='color: #1f2937; font-size: 16px; margin-bottom: 15px;'>This link is valid for <strong>30 minutes</strong>. If you did not request a password reset, please ignore this email or contact us at <a href='mailto:ischobsit@gmail.com' style='color: #4f46e5; text-decoration: none;'>support@example.com</a>.</p>
            <p style='color: #1f2937; font-size: 16px; margin-bottom: 0;'>Best regards,<br>iSCHO Admin Team</p>
        </div>
        <!-- Footer -->
        <div style='background-color: #f9fafb; padding: 15px; text-align: center; border-bottom-left-radius: 8px; border-bottom-right-radius: 8px;'>
            <p style='color: #6b7280; font-size: 12px; margin: 0;'>© 2025 iSCHO. All rights reserved.</p>
        </div>
    </div>
";
        $mail->AltBody = "Hello,\n\nWe received a request to reset your password for your iSCHO account. Please click the link below to reset your password:\n\n$reset_link\n\nThis link is valid for 30 minutes. If you did not request a password reset, please ignore this email or contact us at support@example.com.\n\nBest regards,\niSCHO Admin Team";

        $mail->send();
        return true;
    } catch (Exception $e) {
        return "Failed to send password reset email: {$mail->ErrorInfo}";
    }
}

// Handle Forgot Password Form Submission
if ($_SERVER['REQUEST_METHOD'] == 'POST') {
    $email = trim($_POST['email']);
    $forgot_error = '';
    $forgot_success = '';

    // Validate email
    if (empty($email) || !filter_var($email, FILTER_VALIDATE_EMAIL)) {
        $forgot_error = "Please enter a valid email address.";
    } else {
        // Check if email exists in the users table
        $stmt = $pdo->prepare("SELECT id FROM users WHERE email = ?");
        $stmt->execute([$email]);
        $user = $stmt->fetch(PDO::FETCH_ASSOC);

        if (!$user) {
            $forgot_error = "No account found with that email address.";
        } else {
            $user_id = $user['id'];

            // Delete existing tokens for this user to prevent multiple active tokens
            $stmt = $pdo->prepare("DELETE FROM password_reset_tokens WHERE user_id = ?");
            $stmt->execute([$user_id]);

            // Generate a reset token
            $token = generateToken();
            $expires_at = date('Y-m-d H:i:s', strtotime('+30 minutes'));

            try {
                // Store the reset token in the password_reset_tokens table
                $stmt = $pdo->prepare("
                    INSERT INTO password_reset_tokens (user_id, token, expires_at)
                    VALUES (?, ?, ?)
                ");
                $stmt->execute([$user_id, $token, $expires_at]);
            } catch (PDOException $e) {
                $forgot_error = "Failed to generate reset token: " . $e->getMessage();
            }

            if (empty($forgot_error)) {
                // Send the reset email
                $email_result = sendPasswordResetEmail($email, $token);
                if ($email_result !== true) {
                    $forgot_error = $email_result;
                } else {
                    $forgot_success = "A password reset link has been sent to your email address.";
                }
            }
        }
    }

    if (!empty($forgot_error)) {
        $_SESSION['forgot_error'] = $forgot_error;
    }
    if (!empty($forgot_success)) {
        $_SESSION['forgot_success'] = $forgot_success;
    }
}
?>

<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Forgot Password</title>
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
            background: url('./images/bg.jpg') no-repeat center center fixed;
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

        .forgot-container {
            background-color: var(--card-bg);
            border-radius: 12px;
            box-shadow: 0 4px 6px rgba(0, 0, 0, 0.05), 0 10px 15px rgba(0, 0, 0, 0.03);
            width: 100%;
            max-width: 500px;
            padding: 2rem;
            backdrop-filter: blur(20px);
            -webkit-backdrop-filter: blur(10px);
        }

        .forgot-header {
            text-align: center;
            margin-bottom: 2rem;
        }

        .forgot-header h1 {
            font-size: 1.75rem;
            font-weight: 600;
            color: var(--text-color);
        }

        .forgot-header p {
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

        .forgot-btn {
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

        .forgot-btn:hover {
            background-color: var(--primary-hover);
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

        .back-to-login {
            display: block;
            text-align: center;
            margin-top: 1rem;
            color: var(--primary-color);
            text-decoration: none;
            font-size: 0.9rem;
        }

        .back-to-login:hover {
            text-decoration: underline;
        }

        @media (max-width: 768px) {
            .forgot-container {
                max-width: 90%;
                padding: 1.5rem;
            }

            .forgot-header h1 {
                font-size: 1.5rem;
            }
        }

        @media (max-width: 480px) {
            .forgot-container {
                padding: 1rem;
            }

            .forgot-header h1 {
                font-size: 1.3rem;
            }
        }
    </style>
</head>
<body>
    <?php include('navbar.php'); ?>

    <div class="main-content">
        <div class="forgot-container">
            <div class="forgot-header">
                <h1>Forgot Password</h1>
                <p>Enter your email to receive a password reset link</p>
            </div>

            <?php if (isset($_SESSION['forgot_error'])): ?>
            <div class="error-message">
                <?php echo htmlspecialchars($_SESSION['forgot_error']); unset($_SESSION['forgot_error']); ?>
            </div>
            <?php endif; ?>

            <?php if (isset($_SESSION['forgot_success'])): ?>
            <div class="success-message">
                <?php echo htmlspecialchars($_SESSION['forgot_success']); unset($_SESSION['forgot_success']); ?>
            </div>
            <?php endif; ?>

            <form action="forgotpassword.php" method="POST">
                <div class="form-group">
                    <label for="email">Email Address</label>
                    <div class="input-group">
                        <i class="fas fa-envelope"></i>
                        <input type="email" id="email" name="email" class="form-control" required>
                    </div>
                </div>

                <button type="submit" class="forgot-btn">Send Reset Link</button>
            </form>

            <a href="login.php" class="back-to-login">Back to Login</a>
        </div>
    </div>
</body>
</html>