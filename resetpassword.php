<?php
require './route_guard.php';

// Start the session if not already started
if (session_status() === PHP_SESSION_NONE) {
    session_start();
}

// Handle token validation and password reset
$token = isset($_GET['token']) ? trim($_GET['token']) : '';
$reset_error = '';
$reset_success = '';
$show_form = false;

if (empty($token)) {
    $reset_error = "Invalid or missing reset token.";
} else {
    // Validate the token
    $stmt = $pdo->prepare("
        SELECT prt.user_id, prt.expires_at, prt.used, u.email 
        FROM password_reset_tokens prt 
        JOIN users u ON prt.user_id = u.id 
        WHERE prt.token = ? AND prt.used = 0
    ");
    $stmt->execute([$token]);
    $token_data = $stmt->fetch(PDO::FETCH_ASSOC);

    if (!$token_data) {
        $reset_error = "Invalid or already used reset token.";
    } elseif (strtotime($token_data['expires_at']) < time()) {
        $reset_error = "This reset token has expired.";
    } else {
        $show_form = true; // Token is valid, show the password reset form
    }
}

// Handle password reset form submission
if ($_SERVER['REQUEST_METHOD'] == 'POST' && $show_form) {
    $new_password = trim($_POST['new_password']);
    $confirm_password = trim($_POST['confirm_password']);

    // Validate password
    if (empty($new_password) || empty($confirm_password)) {
        $reset_error = "Please fill in both password fields.";
    } elseif ($new_password !== $confirm_password) {
        $reset_error = "Passwords do not match.";
    } elseif (!preg_match('/[A-Z]/', $new_password) || !preg_match('/[0-9]/', $new_password)) {
        $reset_error = "Password must contain at least one uppercase letter and one number.";
    } else {
        try {
            $pdo->beginTransaction();

            // Update the user's password
            $hashed_password = password_hash($new_password, PASSWORD_DEFAULT);
            $stmt = $pdo->prepare("UPDATE users SET password = ? WHERE id = ?");
            $stmt->execute([$hashed_password, $token_data['user_id']]);

            // Mark the token as used
            $stmt = $pdo->prepare("UPDATE password_reset_tokens SET used = 1 WHERE token = ?");
            $stmt->execute([$token]);

            $pdo->commit();

            $reset_success = "Password reset successfully. Please log in with your new password.";
            $show_form = false; // Hide the form after successful reset

            // Redirect to login page after a short delay
            header('Refresh: 3; url=login.php');
        } catch (PDOException $e) {
            $pdo->rollBack();
            $reset_error = "Failed to reset password: " . $e->getMessage();
            error_log("Password Reset Error: " . $e->getMessage());
        }
    }
}
?>

<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Reset Password</title>
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

        .reset-container {
            background-color: var(--card-bg);
            border-radius: 12px;
            box-shadow: 0 4px 6px rgba(0, 0, 0, 0.05), 0 10px 15px rgba(0, 0, 0, 0.03);
            width: 100%;
            max-width: 500px;
            padding: 2rem;
            backdrop-filter: blur(20px);
            -webkit-backdrop-filter: blur(10px);
        }

        .reset-header {
            text-align: center;
            margin-bottom: 2rem;
        }

        .reset-header h1 {
            font-size: 1.75rem;
            font-weight: 600;
            color: var(--text-color);
        }

        .reset-header p {
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

        .reset-btn {
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

        .reset-btn:hover {
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

        .password-requirements {
            font-size: 0.85rem;
            color: var(--text-muted);
            margin-top: 0.25rem;
        }

        .password-requirements.invalid {
            color: var(--error-color);
        }

        @media (max-width: 768px) {
            .reset-container {
                max-width: 90%;
                padding: 1.5rem;
            }

            .reset-header h1 {
                font-size: 1.5rem;
            }
        }

        @media (max-width: 480px) {
            .reset-container {
                padding: 1rem;
            }

            .reset-header h1 {
                font-size: 1.3rem;
            }
        }
    </style>
</head>
<body>
    <?php include('navbar.php'); ?>

    <div class="main-content">
        <div class="reset-container">
            <div class="reset-header">
                <h1>Reset Password</h1>
                <p>Enter your new password below</p>
            </div>

            <?php if (!empty($reset_error)): ?>
            <div class="error-message">
                <?php echo htmlspecialchars($reset_error); ?>
            </div>
            <?php endif; ?>

            <?php if (!empty($reset_success)): ?>
            <div class="success-message">
                <?php echo htmlspecialchars($reset_success); ?>
            </div>
            <?php endif; ?>

            <?php if ($show_form): ?>
            <form action="resetpassword.php?token=<?php echo htmlspecialchars($token); ?>" method="POST">
                <div class="form-group">
                    <label for="new_password">New Password</label>
                    <div class="input-group">
                        <i class="fas fa-lock"></i>
                        <input type="password" id="new_password" name="new_password" class="form-control" required pattern="(?=.*[A-Z])(?=.*[0-9]).*" title="Password must contain at least one uppercase letter and one number">
                    </div>
                    <div id="password-requirements" class="password-requirements">
                        Must contain at least one uppercase letter and one number
                    </div>
                </div>

                <div class="form-group">
                    <label for="confirm_password">Confirm New Password</label>
                    <div class="input-group">
                        <i class="fas fa-lock"></i>
                        <input type="password" id="confirm_password" name="confirm_password" class="form-control" required>
                    </div>
                </div>

                <button type="submit" class="reset-btn">Reset Password</button>
            </form>
            <?php endif; ?>

            <a href="login.php" class="back-to-login">Back to Login</a>
        </div>
    </div>

    <script>
        // Real-time password validation feedback
        document.addEventListener('DOMContentLoaded', function() {
            const passwordInput = document.getElementById('new_password');
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
            }
        });
    </script>
</body>
</html>