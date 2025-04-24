<?php
require './route_guard.php';

// Function to generate a secure token
function generateToken() {
    return bin2hex(random_bytes(32)); // Generates a 64-character secure token
}

// Check for existing token and validate session timeout
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
            // Token is valid, redirect to appropriate dashboard
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
    } else {
        // Invalid token, destroy session
        $_SESSION = array();
        session_destroy();
        header('Location: login.php?message=Invalid session. Please log in again.');
        exit;
    }
}

// Handle Registration
if ($_SERVER['REQUEST_METHOD'] == 'POST' && isset($_POST['register'])) {
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
        try {
            $pdo->beginTransaction(); // Start a transaction to ensure data consistency

            // Insert into users table
            $hashed_password = password_hash($password, PASSWORD_DEFAULT);
            $stmt = $pdo->prepare("
                INSERT INTO users (
                    firstname, lastname, middlename, contact_no, email, username, password, role
                ) VALUES (?, ?, ?, ?, ?, ?, ?, ?)
            ");
            $stmt->execute([
                $firstname, $lastname, $middlename, $contact_no, $email, $username, $hashed_password, 'Applicant'
            ]);

            // Get the last inserted user ID
            $user_id = $pdo->lastInsertId();

            // Insert into users_info table
            $stmt = $pdo->prepare("
                INSERT INTO users_info (
                    user_id, municipality, barangay, sex, civil_status, nationality, birthdate, place_of_birth
                ) VALUES (?, ?, ?, ?, ?, ?, ?, ?)
            ");
            $stmt->execute([
                $user_id, $municipality, $barangay, $sex, $civil_status, $nationality, $birthdate, $place_of_birth
            ]);

            $pdo->commit(); // Commit the transaction

            $_SESSION['register_success'] = "Registration successful! Please log in.";
        } catch (PDOException $e) {
            $pdo->rollBack(); // Roll back the transaction on error
            $register_error = "Registration failed: " . $e->getMessage();
        }
    }

    if (!empty($register_error)) {
        $_SESSION['register_error'] = $register_error;
    }
}

// Handle Login
if ($_SERVER['REQUEST_METHOD'] == 'POST' && !isset($_POST['register'])) {
    $email = $_POST['email'];
    $password = $_POST['password'];

    $stmt = $pdo->prepare("SELECT id, firstname, lastname, middlename, role, password FROM users WHERE email = ?");
    $stmt->execute([$email]);
    $user = $stmt->fetch(PDO::FETCH_ASSOC);

    if ($user && password_verify($password, $user['password'])) {
        // Generate a new token
        $token = generateToken();
        $expires_at = date('Y-m-d H:i:s', strtotime('+30 minutes'));

        try {
            // Store the token in the database
            $stmt = $pdo->prepare("
                INSERT INTO tokens (user_id, token, expires_at)
                VALUES (?, ?, ?)
            ");
            $stmt->execute([$user['id'], $token, $expires_at]);
        } catch (PDOException $e) {
            $login_error = "Failed to create session token: " . $e->getMessage();
        }

        if (empty($login_error)) {
            // Set session variables
            $_SESSION['user_id'] = $user['id'];
            $_SESSION['firstname'] = $user['firstname'];
            $_SESSION['lastname'] = $user['lastname'];
            $_SESSION['middlename'] = $user['middlename'];
            $_SESSION['user_role'] = $user['role'];
            $_SESSION['token'] = $token;

            // Redirect based on role
            if ($user['role'] === 'Applicant') {
                header('Location: applicantdashboard.php');
                exit;
            } elseif ($user['role'] === 'Admin') {
                header('Location: teacher_dashboard.php');
                exit;
            } elseif ($user['role'] === 'admin') {
                header('Location: navigation.php');
                exit;
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

        .login-container, .register-container {
            background-color: var(--card-bg);
            border-radius: 12px;
            box-shadow: 0 4px 6px rgba(0, 0, 0, 0.05), 0 10px 15px rgba(0, 0, 0, 0.03);
            width: 100%;
            max-width: 900px;
            padding: 2rem;
            backdrop-filter: blur(20px);
            -webkit-backdrop-filter: blur(10px);
        }

        .login-header, .register-header {
            text-align: center;
            margin-bottom: 2rem;
        }

        .login-header h1, .register-header h1 {
            font-size: 1.75rem;
            font-weight: 600;
            color: var(--text-color);
        }

        .login-header p, .register-header p {
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

        .login-btn, .register-btn {
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

        .login-btn:hover, .register-btn:hover {
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
        .register-popup {
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

        .register-container {
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

        /* New Registration Form Styles */
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

        @media (max-width: 768px) {
            .login-container, .register-container {
                max-width: 90%;
                padding: 1.5rem;
            }

            .login-header h1, .register-header h1 {
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
            .login-container, .register-container {
                padding: 1rem;
            }

            .login-header h1, .register-header h1 {
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
            .form-buttons .register-btn {
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
                <?php echo $login_error; ?>
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
    <div class="register-popup" id="registerPopup" <?php echo (isset($_SESSION['register_error']) || isset($_SESSION['register_success'])) ? 'style="display: flex;"' : ''; ?>>
        <div class="register-container">
            <button class="close-btn" onclick="hideRegisterPopup()">×</button>
            <div class="register-header">
                <h1>Register</h1>
                <p>Create your account</p>
            </div>
            
            <?php if (isset($_SESSION['register_success'])): ?>
            <div class="success-message">
                <?php echo $_SESSION['register_success']; unset($_SESSION['register_success']); ?>
            </div>
            <?php endif; ?>
            
            <?php if (isset($_SESSION['register_error'])): ?>
            <div class="error-message">
                <?php echo $_SESSION['register_error']; unset($_SESSION['register_error']); ?>
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
                            <label for="civil-status">Civil Status</label>
                            <div class="input-group">
                                <i class="fas fa-ring"></i>
                                <select id="civil-status" name="civil_status" class="form-control" required style="padding-left: 2.5rem;">
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
                            <label for="place-of-birth">Place of Birth</label>
                            <div class="input-group">
                                <i class="fas fa-map-marker-alt"></i>
                                <input type="text" id="place-of-birth" name="place_of_birth" class="form-control" required>
                            </div>
                        </div>
                    </div>
                    <div class="form-row">
                        <div class="form-group">
                            <label for="contact-no">Contact No.</label>
                            <div class="input-group">
                                <i class="fas fa-phone"></i>
                                <input type="tel" id="contact-no" name="contact_no" class="form-control" required>
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
                                <input type="password" id="password" name="password" class="form-control" required>
                            </div>
                        </div>
                        <div class="form-group">
                            <label for="confirm-password">Confirm Password</label>
                            <div class="input-group">
                                <i class="fas fa-lock"></i>
                                <input type="password" id="confirm-password" name="confirm_password" class="form-control" required>
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

    <script>
        function showRegisterPopup() {
            document.getElementById('registerPopup').style.display = 'flex';
        }

        function hideRegisterPopup() {
            document.getElementById('registerPopup').style.display = 'none';
            <?php
            if (isset($_SESSION['register_error'])) {
                unset($_SESSION['register_error']);
            }
            if (isset($_SESSION['register_success'])) {
                unset($_SESSION['register_success']);
            }
            ?>
        }

        document.addEventListener('click', function(event) {
            const popup = document.getElementById('registerPopup');
            const container = document.querySelector('.register-container');
            if (event.target === popup) {
                hideRegisterPopup();
            }
        });
    </script>
</body>
</html>