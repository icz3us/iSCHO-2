<?php
require './route_guard.php';


if (session_status() === PHP_SESSION_NONE) {
    session_start();
}


$token = isset($_GET['token']) ? trim($_GET['token']) : '';
$reset_error = '';
$reset_success = '';
$show_form = false;
$user_id = null;

if (empty($token)) {
    $reset_error = "Invalid or missing reset token.";
} else {
    
    $stmt = $pdo->prepare("
        SELECT user_id, expires_at, used 
        FROM password_reset_tokens 
        WHERE token = ? AND used = 0
    ");
    $stmt->execute([$token]);
    $token_data = $stmt->fetch(PDO::FETCH_ASSOC);

    if (!$token_data) {
        $reset_error = "Invalid or already used reset token.";
    } elseif (strtotime($token_data['expires_at']) < time()) {
        $reset_error = "This reset token has expired.";
    } else {
        $user_id = $token_data['user_id'];

        
        $stmt = $pdo->prepare("SELECT id, role FROM users WHERE id = ?");
        $stmt->execute([$user_id]);
        $user = $stmt->fetch(PDO::FETCH_ASSOC);

        if (!$user) {
            $reset_error = "User not found.";
        } else {
            $show_form = true; 
        }
    }
}


if ($_SERVER['REQUEST_METHOD'] == 'POST' && $show_form) {
    $new_password = trim($_POST['new_password']);
    $confirm_password = trim($_POST['confirm_password']);

    
    if (empty($new_password) || empty($confirm_password)) {
        $reset_error = "Please fill in both password fields.";
    } elseif ($new_password !== $confirm_password) {
        $reset_error = "Passwords do not match.";
    } elseif (!preg_match('/[A-Z]/', $new_password) || !preg_match('/[0-9]/', $new_password)) {
        $reset_error = "Password must contain at least one uppercase letter and one number.";
    } elseif (strlen($new_password) < 8) {
        $reset_error = "Password must be at least 8 characters long.";
    } else {
        try {
            $pdo->beginTransaction();

            
            $hashed_password = password_hash($new_password, PASSWORD_DEFAULT);
            $stmt = $pdo->prepare("UPDATE users SET password = ? WHERE id = ?");
            $stmt->execute([$hashed_password, $user_id]);

            
            $stmt = $pdo->prepare("UPDATE password_reset_tokens SET used = 1 WHERE token = ?");
            $stmt->execute([$token]);

            $pdo->commit();

            $reset_success = "Password reset successfully. Please log in with your new password.";
            $show_form = false; 

            
            header('Refresh: 3; url=login.php');
        } catch (PDOException $e) {
            $pdo->rollBack();
            $reset_error = "Failed to reset password: " . $e->getMessage();
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

        .input-group i.fas.fa-lock {
            position: absolute;
            top: 50%;
            left: 1rem;
            transform: translateY(-50%);
            color: var(--text-muted);
            z-index: 2;
        }

        .form-control {
            width: 100%;
            padding: 0.75rem 2.5rem 0.75rem 2.5rem;
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

        .toggle-password {
            position: absolute;
            right: 1rem;
            top: 50%;
            transform: translateY(-50%);
            background: none;
            border: none;
            cursor: pointer;
            color: var(--text-muted);
            padding: 0;
            z-index: 2;
            height: 20px;
            width: 20px;
            display: flex;
            align-items: center;
            justify-content: center;
        }

        .toggle-password:hover {
            color: var(--primary-color);
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
                        <input type="password" id="new_password" name="new_password" class="form-control" required pattern="(?=.*[A-Z])(?=.*[0-9]).{8,}" title="Password must contain at least one uppercase letter, one number, and be at least 8 characters long">
                        <button type="button" class="toggle-password" onclick="togglePassword('new_password', this)" tabindex="-1"><i class="fas fa-eye"></i></button>
                    </div>
                    <div id="password-requirements" class="password-requirements">
                        Must contain at least one uppercase letter, one number, and be at least 8 characters long
                    </div>
                </div>

                <div class="form-group">
                    <label for="confirm_password">Confirm New Password</label>
                    <div class="input-group">
                        <i class="fas fa-lock"></i>
                        <input type="password" id="confirm_password" name="confirm_password" class="form-control" required>
                        <button type="button" class="toggle-password" onclick="togglePassword('confirm_password', this)" tabindex="-1"><i class="fas fa-eye"></i></button>
                    </div>
                </div>

                <button type="submit" class="reset-btn">Reset Password</button>
            </form>
            <?php endif; ?>

            <a href="login.php" class="back-to-login">Back to Login</a>
        </div>
    </div>

    <script>
        document.addEventListener('DOMContentLoaded', function() {
            const passwordInput = document.getElementById('new_password');
            const confirmPasswordInput = document.getElementById('confirm_password');
            const requirementsText = document.getElementById('password-requirements');

            if (passwordInput && requirementsText) {
                passwordInput.addEventListener('input', function() {
                    const password = passwordInput.value;
                    const hasUppercase = /[A-Z]/.test(password);
                    const hasNumber = /[0-9]/.test(password);
                    const isLongEnough = password.length >= 8;

                    if (hasUppercase && hasNumber && isLongEnough) {
                        requirementsText.classList.remove('invalid');
                    } else {
                        requirementsText.classList.add('invalid');
                    }
                });

                confirmPasswordInput.addEventListener('input', function() {
                    if (confirmPasswordInput.value !== passwordInput.value) {
                        confirmPasswordInput.setCustomValidity("Passwords do not match.");
                    } else {
                        confirmPasswordInput.setCustomValidity("");
                    }
                });
            }
        });

        function togglePassword(fieldId, btn) {
            const input = document.getElementById(fieldId);
            const icon = btn.querySelector('i');
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
    </script>
</body>
</html>