<?php
require './route_guard.php';
require 'vendor/autoload.php'; 

use PHPMailer\PHPMailer\PHPMailer;
use PHPMailer\PHPMailer\Exception;


if ($_SESSION['user_role'] !== 'Superadmin') {
    header('Location: login.php');
    exit;
}


define('ENCRYPTION_KEY', 'your_secure_encryption_key_here_32_bytes'); 


function encryptSecretKey($data) {
    $key = ENCRYPTION_KEY;
    $iv = openssl_random_pseudo_bytes(openssl_cipher_iv_length('aes-256-cbc'));
    $encrypted = openssl_encrypt($data, 'aes-256-cbc', $key, 0, $iv);
    if ($encrypted === false) {
        throw new Exception("Encryption failed");
    }
    return [
        'encrypted' => base64_encode($encrypted),
        'iv' => base64_encode($iv)
    ];
}


function decryptSecretKey($encrypted, $iv) {
    $key = ENCRYPTION_KEY;
    $encrypted = base64_decode($encrypted);
    $iv = base64_decode($iv);
    $decrypted = openssl_decrypt($encrypted, 'aes-256-cbc', $key, 0, $iv);
    if ($decrypted === false) {
        throw new Exception("Decryption failed");
    }
    return $decrypted;
}


function generateSecretKey() {
    return bin2hex(random_bytes(32)); 
}


function sendSecretKey($email, $secretKey) {
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

        // Content
        $mail->isHTML(true);
        $mail->Subject = "Your Secret Key for Deleting All Applicants";
        $mail->Body = "
    <div style='font-family: Arial, sans-serif; max-width: 600px; margin: 0 auto; border: 1px solid #e0e0e0; border-radius: 8px;'>
        <div style='background-color: #4f46e5; padding: 20px; text-align: center; border-top-left-radius: 8px; border-top-right-radius: 8px;'>
            <h1 style='color: #ffffff; margin: 0; font-size: 24px;'>Your Secret Key</h1>
        </div>
        <div style='padding: 30px; background-color: #ffffff;'>
            <p style='color: #1f2937; font-size: 16px; margin-bottom: 15px;'>Hello Superadmin,</p>
            <p style='color: #1f2937; font-size: 16px; margin-bottom: 15px;'>To proceed with deleting all applicants, please use the following Secret Key:</p>
            <div style='text-align: center; margin: 20px 0;'>
                <span style='display: inline-block; background-color: #f3f4f6; padding: 15px 25px; border-radius: 5px; font-size: 18px; font-weight: bold; color: #4f46e5; letter-spacing: 1px; word-break: break-all;'>$secretKey</span>
            </div>
            <p style='color: #1f2937; font-size: 16px; margin-bottom: 15px;'>This Secret Key is valid for <strong>10 minutes</strong>. Please do not share this key with anyone.</p>
            <p style='color: #1f2937; font-size: 16px; margin-bottom: 15px;'>If you did not request this action, please contact us at <a href='mailto:ischobsit@gmail.com' style='color: #4f46e5; text-decoration: none;'>ischobsit@gmail.com</a>.</p>
            <p style='color: #1f2937; font-size: 16px; margin-bottom: 0;'>Best regards,<br>iSCHO Admin Team</p>
        </div>
        <div style='background-color: #f9fafb; padding: 15px; text-align: center; border-bottom-left-radius: 8px; border-bottom-right-radius: 8px;'>
            <p style='color: #6b7280; font-size: 12px; margin: 0;'>© 2025 iSCHO. All rights reserved.</p>
        </div>
    </div>
";
        $mail->AltBody = "Hello Superadmin,\n\nTo proceed with deleting all applicants, please use the following Secret Key:\n\n$secretKey\n\nThis Secret Key is valid for 10 minutes. Please do not share this key with anyone.\n\nIf you did not request this action, please contact us at ischobsit@gmail.com.\n\nBest regards,\niSCHO Admin Team";

        $mail->send();
        return true;
    } catch (Exception $e) {
        return "Failed to send Secret Key: {$mail->ErrorInfo}";
    }
}


if ($_SERVER['REQUEST_METHOD'] == 'POST' && isset($_POST['request_secret_key'])) {
    $user_id = $_SESSION['user_id'];
    $stmt = $pdo->prepare("SELECT email FROM users WHERE id = ? AND role = 'Superadmin'");
    $stmt->execute([$user_id]);
    $user = $stmt->fetch(PDO::FETCH_ASSOC);
    $email = $user['email'] ?? 'ischobsit@gmail.com'; 


    $secretKey = generateSecretKey();
    $expires_at = date('Y-m-d H:i:s', strtotime('+10 minutes'));

    try {

        $encryptedData = encryptSecretKey($secretKey);
        $encryptedKey = $encryptedData['encrypted'];
        $iv = $encryptedData['iv'];

        $stmt = $pdo->prepare("
            INSERT INTO secret_keys (user_id, secret_key, iv, expires_at)
            VALUES (?, ?, ?, ?)
        ");
        $stmt->execute([$user_id, $encryptedKey, $iv, $expires_at]);

        $email_result = sendSecretKey($email, $secretKey);
        if ($email_result !== true) {
            $_SESSION['secret_key_error'] = $email_result;
        } else {
            $_SESSION['show_secret_key_popup'] = true;
        }
    } catch (Exception $e) {
        $_SESSION['secret_key_error'] = "Failed to generate or encrypt Secret Key: " . $e->getMessage();
    }
    header('Location: superadmindashboard.php');
    exit;
}

if ($_SERVER['REQUEST_METHOD'] == 'POST' && isset($_POST['verify_secret_key'])) {
    $input_key = trim($_POST['secret_key']);
    $user_id = $_SESSION['user_id'];

    try {
        $stmt = $pdo->prepare("
            SELECT secret_key, iv, expires_at, used
            FROM secret_keys
            WHERE user_id = ? AND used = 0
            ORDER BY created_at DESC
            LIMIT 1
        ");
        $stmt->execute([$user_id]);
        $key_data = $stmt->fetch(PDO::FETCH_ASSOC);

        if ($key_data && strtotime($key_data['expires_at']) > time()) {
            $decrypted_key = decryptSecretKey($key_data['secret_key'], $key_data['iv']);
            if ($decrypted_key === $input_key) {
                try {
                    $pdo->beginTransaction();

                    $stmt = $pdo->prepare("SELECT id FROM users WHERE role = 'Applicant'");
                    $stmt->execute();
                    $applicant_ids = $stmt->fetchAll(PDO::FETCH_COLUMN, 0);

                    if (!empty($applicant_ids)) {
                        $in_params = str_repeat('?,', count($applicant_ids) - 1) . '?';

                        $stmt = $pdo->prepare("DELETE FROM user_docs WHERE user_id IN ($in_params)");
                        $stmt->execute($applicant_ids);

                        $stmt = $pdo->prepare("DELETE FROM user_fam WHERE user_id IN ($in_params)");
                        $stmt->execute($applicant_ids);

                        $stmt = $pdo->prepare("DELETE FROM user_residency WHERE user_id IN ($in_params)");
                        $stmt->execute($applicant_ids);

                        $stmt = $pdo->prepare("DELETE FROM users_info WHERE user_id IN ($in_params)");
                        $stmt->execute($applicant_ids);

                        $stmt = $pdo->prepare("DELETE FROM notices WHERE user_id IN ($in_params)");
                        $stmt->execute($applicant_ids);

                        $stmt = $pdo->prepare("DELETE FROM tokens WHERE user_id IN ($in_params)");
                        $stmt->execute($applicant_ids);

                        $stmt = $pdo->prepare("DELETE FROM users WHERE role = 'Applicant'");
                        $stmt->execute();
                    }

                    $stmt = $pdo->prepare("UPDATE secret_keys SET used = 1 WHERE secret_key = ?");
                    $stmt->execute([$key_data['secret_key']]);

                    $pdo->commit();
                    $_SESSION['applicants_delete_success'] = "All applicant data deleted successfully!";
                    $_SESSION['show_secret_key_popup'] = false;
                } catch (PDOException $e) {
                    $pdo->rollBack();
                    $_SESSION['secret_key_error'] = "Failed to delete applicants: " . $e->getMessage();
                    $_SESSION['show_secret_key_popup'] = true;
                }
            } else {
                $_SESSION['secret_key_error'] = "Invalid Secret Key.";
                $_SESSION['show_secret_key_popup'] = true;
            }
        } else {
            $_SESSION['secret_key_error'] = "Invalid or expired Secret Key.";
            $_SESSION['show_secret_key_popup'] = true;
        }
    } catch (Exception $e) {
        $_SESSION['secret_key_error'] = "Error verifying or decrypting Secret Key: " . $e->getMessage();
        $_SESSION['show_secret_key_popup'] = true;
    }
    header('Location: superadmindashboard.php');
    exit;
}


if (isset($_GET['action']) && $_GET['action'] === 'logout') {
    $stmt = $pdo->prepare("DELETE FROM tokens WHERE user_id = ? AND token = ?");
    $stmt->execute([$_SESSION['user_id'], $_SESSION['token']]);
    
    $_SESSION = array();
    session_destroy();
    header('Location: login.php');
    exit;
}


$lastname = isset($_SESSION['lastname']) ? $_SESSION['lastname'] : '';
$firstname = isset($_SESSION['firstname']) ? $_SESSION['firstname'] : '';
$middlename = isset($_SESSION['middlename']) ? $_SESSION['middlename'] : '';

$full_name = trim("$lastname, $firstname $middlename");
if (empty($full_name) || $full_name === ',') {
    $full_name = 'Super Admin';
}


$total_applicants = 0;
$approved_applicants = 0;
$denied_applicants = 0;

try {
    $stmt = $pdo->prepare("SELECT COUNT(*) FROM users WHERE role = 'Applicant'");
    $stmt->execute();
    $total_applicants = $stmt->fetchColumn();

    $stmt = $pdo->prepare("SELECT COUNT(*) FROM users_info WHERE application_status = 'Approved'");
    $stmt->execute();
    $approved_applicants = $stmt->fetchColumn();

    $stmt = $pdo->prepare("SELECT COUNT(*) FROM users_info WHERE application_status = 'Denied'");
    $stmt->execute();
    $denied_applicants = $stmt->fetchColumn();
} catch (PDOException $e) {
    $_SESSION['analytics_error'] = "Error fetching analytics: " . $e->getMessage();
}


$current_application_deadline = null;
try {
    $stmt = $pdo->prepare("SELECT application_deadline FROM application_period ORDER BY updated_at DESC LIMIT 1");
    $stmt->execute();
    $result = $stmt->fetch(PDO::FETCH_ASSOC);
    if ($result) {
        $current_application_deadline = $result['application_deadline'];
    }
} catch (PDOException $e) {
    $_SESSION['application_period_error'] = "Error fetching application period: " . $e->getMessage();
}
$formatted_deadline = $current_application_deadline ? date('m/d/Y', strtotime($current_application_deadline)) : 'Not Set';


$admins = [];
try {
    $stmt = $pdo->prepare("SELECT id, firstname, lastname, middlename, contact_no, email, username FROM users WHERE role = 'Admin'");
    $stmt->execute();
    $admins = $stmt->fetchAll(PDO::FETCH_ASSOC);
} catch (PDOException $e) {
    $_SESSION['admin_list_error'] = "Error fetching admins: " . $e->getMessage();
}


if ($_SERVER['REQUEST_METHOD'] == 'POST' && isset($_POST['update_application_period'])) {
    $application_deadline = trim($_POST['application_deadline']);
    $update_error = '';

    
    if (empty($application_deadline)) {
        $update_error = "Application deadline is required.";
    } else {
        $deadline_date = DateTime::createFromFormat('Y-m-d', $application_deadline);
        if (!$deadline_date || $deadline_date->format('Y-m-d') !== $application_deadline) {
            $update_error = "Invalid date format. Please use YYYY-MM-DD.";
        }
    }

    if (empty($update_error)) {
        try {
            $stmt = $pdo->prepare("
                INSERT INTO application_period (application_deadline, updated_at)
                VALUES (?, NOW())
            ");
            $stmt->execute([$application_deadline]);
            $_SESSION['application_period_success'] = "Application period updated successfully!";
        } catch (PDOException $e) {
            $_SESSION['application_period_error'] = "Failed to update application period: " . $e->getMessage();
        }
    } else {
        $_SESSION['application_period_error'] = $update_error;
    }
    header('Location: superadmindashboard.php');
    exit;
}


if ($_SERVER['REQUEST_METHOD'] == 'POST' && isset($_POST['register_admin'])) {
    $firstname = trim($_POST['firstname']);
    $lastname = trim($_POST['lastname']);
    $middlename = trim($_POST['middlename']);
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
            $pdo->beginTransaction();

            $hashed_password = password_hash($password, PASSWORD_DEFAULT);
            $stmt = $pdo->prepare("
                INSERT INTO users (
                    firstname, lastname, middlename, contact_no, email, username, password, role
                ) VALUES (?, ?, ?, ?, ?, ?, ?, 'Admin')
            ");
            $stmt->execute([
                $firstname, $lastname, $middlename, $contact_no, $email, $username, $hashed_password
            ]);

            $pdo->commit();
            $_SESSION['admin_register_success'] = "Admin account created successfully!";
        } catch (PDOException $e) {
            $pdo->rollBack();
            $register_error = "Admin registration failed: " . $e->getMessage();
        }
    }

    if (!empty($register_error)) {
        $_SESSION['admin_register_error'] = $register_error;
    }
    header('Location: superadmindashboard.php');
    exit;
}


if ($_SERVER['REQUEST_METHOD'] == 'POST' && isset($_POST['update_admin'])) {
    $admin_id = trim($_POST['admin_id']);
    $firstname = trim($_POST['firstname']);
    $lastname = trim($_POST['lastname']);
    $middlename = trim($_POST['middlename']);
    $contact_no = trim($_POST['contact_no']);
    $email = trim($_POST['email']);
    $username = trim($_POST['username']);

    $update_error = '';

    $stmt = $pdo->prepare("SELECT id FROM users WHERE email = ? AND id != ?");
    $stmt->execute([$email, $admin_id]);
    if ($stmt->fetch()) {
        $update_error = "Email is already registered.";
    }

    $stmt = $pdo->prepare("SELECT id FROM users WHERE username = ? AND id != ?");
    $stmt->execute([$username, $admin_id]);
    if ($stmt->fetch()) {
        $update_error = "Username is already taken.";
    }

    if (empty($update_error)) {
        try {
            $stmt = $pdo->prepare("
                UPDATE users 
                SET firstname = ?, lastname = ?, middlename = ?, contact_no = ?, email = ?, username = ? 
                WHERE id = ? AND role = 'Admin'
            ");
            $stmt->execute([$firstname, $lastname, $middlename, $contact_no, $email, $username, $admin_id]);
            $_SESSION['admin_update_success'] = "Admin updated successfully!";
        } catch (PDOException $e) {
            $update_error = "Update failed: " . $e->getMessage();
        }
    }

    if (!empty($update_error)) {
        $_SESSION['admin_update_error'] = $update_error;
    }
    header('Location: superadmindashboard.php');
    exit;
}


if ($_SERVER['REQUEST_METHOD'] == 'POST' && isset($_POST['delete_admin'])) {
    $admin_id = trim($_POST['admin_id']);
    try {
        $stmt = $pdo->prepare("DELETE FROM users WHERE id = ? AND role = 'Admin'");
        $stmt->execute([$admin_id]);
        $_SESSION['admin_delete_success'] = "Admin deleted successfully!";
    } catch (PDOException $e) {
        $_SESSION['admin_delete_error'] = "Delete failed: " . $e->getMessage();
    }
    header('Location: superadmindashboard.php');
    exit;
}
?>

<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Super Admin Dashboard</title>
    <link href="https://fonts.googleapis.com/css2?family=Poppins:wght@300;400;500;600&display=swap" rel="stylesheet">
    <link href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.0.0/css/all.min.css" rel="stylesheet">
    <link rel="icon" type="image/png" href="./images/logo1.png">
    <style>
        :root {
            --primary-color: #4f46e5;
            --primary-hover: #4338ca;
            --sidebar-bg: #1a202c;
            --text-color: #1f2937;
            --text-muted: #6b7280;
            --card-bg: #ffffff;
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
            background-color: #f9fafb;
            color: var(--text-color);
            min-height: 100vh;
            display: flex;
        }

        .container {
            display: flex;
            width: 100%;
        }

        .sidebar {
            width: 250px;
            background-color: var(--sidebar-bg);
            color: white;
            padding: 1.5rem;
            height: 100vh;
            position: fixed;
            top: 0;
            left: 0;
            display: flex;
            flex-direction: column;
            align-items: center;
        }

        .sidebar .logo {
            margin-bottom: 2rem;
            text-align: center;
            width: 100%;
        }

        .sidebar .logo span {
            font-size: 1.2rem;
            color: white;
        }

        .profile-pic {
            width: 100px;
            height: 100px;
            border-radius: 50%;
            margin-bottom: 1rem;
            display: flex;
            align-items: center;
            justify-content: center;
            font-size: 2rem;
            font-weight: 500;
            color: var(--text-color);
            background-color: #e5e7eb;
        }

        .user-name {
            margin-bottom: 2rem;
            text-align: center;
            width: 100%;
        }

        .user-name div {
            font-size: 1rem;
            font-weight: 400;
            color: white;
            margin-bottom: 0.25rem;
            overflow: hidden;
            text-overflow: ellipsis;
            white-space: nowrap;
        }

        .sidebar ul {
            list-style: none;
            width: 100%;
        }

        .sidebar ul li {
            margin-bottom: 1rem;
        }

        .sidebar ul li a {
            color: white;
            text-decoration: none;
            display: flex;
            align-items: center;
            padding: 0.75rem 1rem;
            border-radius: 6px;
            transition: background-color 0.3s ease;
            font-size: 1rem;
            cursor: pointer;
        }

        .sidebar ul li a i {
            margin-right: 0.75rem;
        }

        .sidebar ul li a:hover,
        .sidebar ul li a.active {
            background-color: var(--primary-color);
        }

        .main-content {
            margin-left: 250px;
            padding: 2rem;
            width: calc(100% - 250px);
        }

        .header {
            display: flex;
            justify-content: space-between;
            align-items: center;
            margin-bottom: 2rem;
        }

        .header h1 {
            font-size: 1.75rem;
            font-weight: 600;
        }

        .user-profile {
            display: flex;
            align-items: center;
        }

        .user-profile .username {
            margin-right: 0.75rem;
            font-size: 1rem;
            color: var(--text-color);
        }

        .user-profile .avatar {
            width: 32px;
            height: 32px;
            border-radius: 50%;
            display: flex;
            align-items: center;
            justify-content: center;
            font-size: 1rem;
            font-weight: 500;
            color: white;
            background-color: var(--primary-color);
        }

        .welcome-text {
            margin-bottom: 2rem;
        }

        .welcome-text p {
            color: var(--text-muted);
            font-size: 0.9rem;
        }

        .stats {
            display: grid;
            grid-template-columns: repeat(auto-fit, minmax(200px, 1fr));
            gap: 1.5rem;
            margin-bottom: 2rem;
        }

        .stat-card {
            background-color: var(--card-bg);
            padding: 1.5rem;
            border-radius: 12px;
            box-shadow: 0 2px 4px rgba(0, 0, 0, 0.05);
            text-align: center;
        }

        .stat-card h3 {
            font-size: 1.5rem;
            font-weight: 600;
            margin-bottom: 0.25rem;
        }

        .stat-card p {
            color: var(--text-muted);
            font-size: 0.9rem;
        }

        .section {
            background-color: var(--card-bg);
            padding: 1.5rem;
            border-radius: 12px;
            box-shadow: 0 2px 4px rgba(0, 0, 0, 0.05);
            margin-bottom: 2rem;
        }

        .section h2 {
            font-size: 1.25rem;
            font-weight: 600;
            margin-bottom: 1rem;
        }

        .success-message {
            background-color: rgba(34, 197, 94, 0.1);
            color: var(--success-color);
            padding: 0.75rem;
            border-radius: 8px;
            margin-bottom: 1.25rem;
            font-size: 0.9rem;
        }

        .error-message {
            background-color: rgba(239, 68, 68, 0.1);
            color: var(--error-color);
            padding: 0.75rem;
            border-radius: 8px;
            margin-bottom: 1.25rem;
            font-size: 0.9rem;
        }

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

        .form-group {
            flex: 1;
            min-width: 200px;
            margin-bottom: 1.25rem;
        }

        .form-group label {
            display: block;
            margin-bottom: 0.5rem;
            font-weight: 500;
            font-size: 0.9rem;
            color: var(--text-color);
        }

        .form-group .required {
            color: var(--error-color);
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
            transition: all: 0.3s ease;
            background-color: rgba(255, 255, 255, 0.8);
        }

        .form-control:focus {
            outline: none;
            border-color: var(--primary-color);
            box-shadow: 0 0 0 3px rgba(79, 70, 229, 0.1);
        }

        .form-buttons {
            display: flex;
            justify-content: flex-end;
            gap: 1rem;
        }

        .form-buttons .submit-btn,
        .form-buttons .delete-btn,
        .form-buttons .delete-all-btn {
            padding: 0.75rem 1.5rem;
            border: none;
            border-radius: 8px;
            font-size: 1rem;
            font-weight: 500;
            cursor: pointer;
            transition: background-color 0.3s ease;
        }

        .form-buttons .submit-btn {
            background-color: var(--primary-color);
            color: white;
        }

        .form-buttons .submit-btn:hover {
            background-color: var(--primary-hover);
        }

        .form-buttons .delete-btn {
            background-color: #6b7280;
            color: white;
        }

        .form-buttons .delete-btn:hover {
            background-color: #5a6268;
        }

        .form-buttons .delete-all-btn {
            background-color: var(--error-color);
            color: white;
        }

        .form-buttons .delete-all-btn:hover {
            background-color: #dc2626;
        }

        .table-container {
            overflow-x: auto;
        }

        table {
            width: 100%;
            border-collapse: collapse;
            margin-bottom: 1rem;
        }

        th, td {
            padding: 0.75rem;
            text-align: left;
            border-bottom: 1px solid var(--border-color);
        }

        th {
            background-color: #f9fafb;
            font-weight: 600;
            color: var(--text-color);
        }

        td {
            color: var(--text-muted);
            font-size: 0.9rem;
        }

        tr:hover {
            background-color: #f1f5f9;
        }

        .action-buttons {
            display: flex;
            gap: 0.5rem;
        }

        .edit-btn,
        .delete-btn-table {
            padding: 0.5rem 1rem;
            border: none;
            border-radius: 8px;
            font-size: 0.9rem;
            font-weight: 500;
            cursor: pointer;
            transition: background-color 0.3s ease;
        }

        .edit-btn {
            background-color: var(--success-color);
            color: white;
        }

        .edit-btn:hover {
            background-color: #16a34a;
        }

        .delete-btn-table {
            background-color: var(--error-color);
            color: white;
        }

        .delete-btn-table:hover {
            background-color: #dc2626;
        }

        .dashboard-content {
            display: block;
        }

        .admin-form,
        .manage-admins {
            display: none;
        }

        /* Secret Key Popup Styles */
        .secret-key-popup {
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

        .secret-key-container {
            background-color: var(--card-bg);
            border-radius: 12px;
            box-shadow: 0 4px 6px rgba(0, 0, 0, 0.05);
            width: 100%;
            max-width: 500px;
            padding: 2rem;
            position: relative;
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

        .secret-key-header {
            text-align: center;
            margin-bottom: 2rem;
        }

        .secret-key-header h1 {
            font-size: 1.5rem;
            font-weight: 600;
            color: var(--text-color);
        }

        .secret-key-header p {
            color: var(--text-muted);
            font-size: 0.9rem;
            margin-top: 0.5rem;
        }

        .verify-btn {
            background-color: var(--primary-color);
            color: white;
            border: none;
            border-radius: 8px;
            padding: 0.75rem 1.5rem;
            font-size: 1rem;
            font-weight: 500;
            cursor: pointer;
            transition: background-color 0.3s ease;
        }

        .verify-btn:hover {
            background-color: var(--primary-hover);
        }

        @media (max-width: 768px) {
            .sidebar {
                width: 200px;
            }

            .main-content {
                margin-left: 200px;
                width: calc(100% - 200px);
            }

            .stats {
                grid-template-columns: repeat(auto-fit, minmax(150px, 1fr));
            }

            .profile-pic {
                width: 80px;
                height: 80px;
            }

            .form-row {
                flex-direction: column;
            }

            .form-group {
                min-width: 100%;
            }

            .secret-key-container {
                max-width: 90%;
                padding: 1.5rem;
            }
        }

        @media (max-width: 576px) {
            .sidebar {
                width: 60px;
                padding: 1rem;
                align-items: center;
            }

            .sidebar .logo span,
            .user-name,
            .sidebar ul li a span {
                display: none;
            }

            .sidebar ul li a {
                justify-content: center;
                padding: 0.5rem;
            }

            .profile-pic {
                width: 40px;
                height: 40px;
                font-size: 1.2rem;
            }

            .main-content {
                margin-left: 60px;
                width: calc(100% - 60px);
                padding: 1rem;
            }

            .header h1 {
                font-size: 1.5rem;
            }

            .stats {
                grid-template-columns: 1fr;
            }

            .form-buttons {
                flex-direction: column;
                gap: 0.5rem;
            }

            .form-buttons .submit-btn,
            .form-buttons .delete-btn,
            .form-buttons .delete-all-btn,
            .form-buttons .verify-btn {
                width: 100%;
            }

            .secret-key-container {
                padding: 1rem;
            }

            .secret-key-header h1 {
                font-size: 1.3rem;
            }
        }
    </style>
</head>
<body>
    <div class="container">
        <div class="sidebar">
            <div class="logo">
                <span>Super Admin Portal</span>
            </div>
            <div class="profile-pic">
                <!-- Profile pic content -->
            </div>
            <div class="user-name">
                <div><?php echo htmlspecialchars($full_name); ?></div>
            </div>
            <ul>
                <li><a href="#" id="dashboardLink" class="active" onclick="showDashboard()"><i class="fas fa-tachometer-alt"></i><span>Dashboard</span></a></li>
                <li><a href="#" id="registerAdminLink" onclick="showRegisterAdmin()"><i class="fas fa-user-plus"></i><span>Register an Admin</span></a></li>
                <li><a href="#" id="manageAdminsLink" onclick="showManageAdmins()"><i class="fas fa-users"></i><span>Manage Admins</span></a></li>
                <li><a href="superadmindashboard.php?action=logout"><i class="fas fa-sign-out-alt"></i><span>Logout</span></a></li>
            </ul>
        </div>
        <div class="main-content">
            <div class="header">
                <h1>Dashboard</h1>
                <div class="user-profile">
                    <span class="username"><?php echo htmlspecialchars($full_name); ?></span>
                </div>
            </div>

            <div class="welcome-text">
                <p>Welcome to the Super Admin Dashboard</p>
            </div>

            <!-- Display Success/Error Messages -->
            <?php if (isset($_SESSION['admin_register_success'])): ?>
                <div class="success-message">
                    <?php echo $_SESSION['admin_register_success']; unset($_SESSION['admin_register_success']); ?>
                </div>
            <?php endif; ?>
            <?php if (isset($_SESSION['admin_register_error'])): ?>
                <div class="error-message">
                    <?php echo $_SESSION['admin_register_error']; unset($_SESSION['admin_register_error']); ?>
                </div>
            <?php endif; ?>
            <?php if (isset($_SESSION['analytics_error'])): ?>
                <div class="error-message">
                    <?php echo $_SESSION['analytics_error']; unset($_SESSION['analytics_error']); ?>
                </div>
            <?php endif; ?>
            <?php if (isset($_SESSION['admin_list_error'])): ?>
                <div class="error-message">
                    <?php echo $_SESSION['admin_list_error']; unset($_SESSION['admin_list_error']); ?>
                </div>
            <?php endif; ?>
            <?php if (isset($_SESSION['admin_update_success'])): ?>
                <div class="success-message">
                    <?php echo $_SESSION['admin_update_success']; unset($_SESSION['admin_update_success']); ?>
                </div>
            <?php endif; ?>
            <?php if (isset($_SESSION['admin_update_error'])): ?>
                <div class="error-message">
                    <?php echo $_SESSION['admin_update_error']; unset($_SESSION['admin_update_error']); ?>
                </div>
            <?php endif; ?>
            <?php if (isset($_SESSION['admin_delete_success'])): ?>
                <div class="success-message">
                    <?php echo $_SESSION['admin_delete_success']; unset($_SESSION['admin_delete_success']); ?>
                </div>
            <?php endif; ?>
            <?php if (isset($_SESSION['admin_delete_error'])): ?>
                <div class="error-message">
                    <?php echo $_SESSION['admin_delete_error']; unset($_SESSION['admin_delete_error']); ?>
                </div>
            <?php endif; ?>
            <?php if (isset($_SESSION['application_period_success'])): ?>
                <div class="success-message">
                    <?php echo $_SESSION['application_period_success']; unset($_SESSION['application_period_success']); ?>
                </div>
            <?php endif; ?>
            <?php if (isset($_SESSION['application_period_error'])): ?>
                <div class="error-message">
                    <?php echo $_SESSION['application_period_error']; unset($_SESSION['application_period_error']); ?>
                </div>
            <?php endif; ?>
            <?php if (isset($_SESSION['applicants_delete_success'])): ?>
                <div class="success-message">
                    <?php echo $_SESSION['applicants_delete_success']; unset($_SESSION['applicants_delete_success']); ?>
                </div>
            <?php endif; ?>
            <?php if (isset($_SESSION['applicants_delete_error'])): ?>
                <div class="error-message">
                    <?php echo $_SESSION['applicants_delete_error']; unset($_SESSION['applicants_delete_error']); ?>
                </div>
            <?php endif; ?>
            <?php if (isset($_SESSION['secret_key_error'])): ?>
                <div class="error-message">
                    <?php echo $_SESSION['secret_key_error']; unset($_SESSION['secret_key_error']); ?>
                </div>
            <?php endif; ?>

            <!-- Dashboard Content -->
            <div class="dashboard-content" id="dashboardContent">
                <div class="stats">
                    <div class="stat-card">
                        <h3><?php echo $total_applicants; ?></h3>
                        <p>Total Applicants</p>
                    </div>
                    <div class="stat-card">
                        <h3><?php echo $approved_applicants; ?></h3>
                        <p>Approved Applicants</p>
                    </div>
                    <div class="stat-card">
                        <h3><?php echo $denied_applicants; ?></h3>
                        <p>Denied Applicants</p>
                    </div>
                    <div class="stat-card">
                        <h3><?php echo htmlspecialchars($formatted_deadline); ?></h3>
                        <p>Application Deadline</p>
                        <form method="POST" style="margin-top: 1rem;">
                            <div class="form-group">
                                <label for="application_deadline">Set New Deadline <span class="required">*</span></label>
                                <div class="input-group">
                                    <i class="fas fa-calendar-alt"></i>
                                    <input type="date" id="application_deadline" name="application_deadline" class="form-control" required>
                                </div>
                            </div>
                            <div class="form-buttons">
                                <button type="submit" name="update_application_period" class="submit-btn">Update Deadline</button>
                            </div>
                        </form>
                    </div>
                </div>
                <!-- Delete All Applicants Button -->
                <div class="section">
                    <h2>Manage Applicants</h2>
                    <p>This function should be used to delete all data once the Application Period has officially ended, </p>
                    <p>ensuring that the system is cleared and ready for the next cycle.</p>
                    <form method="POST" onsubmit="return confirm('Are you sure you want to delete all applicant data? A Secret Key will be sent to your email for verification.');">
                        <div class="form-buttons">
                            <button type="submit" name="request_secret_key" class="delete-all-btn">Delete All Applicants</button>
                        </div>
                    </form>
                </div>
            </div>

            <!-- Admin Registration Form -->
            <div class="admin-form" id="adminForm">
                <h2>Register an Admin</h2>
                <form id="adminRegisterForm" method="POST">
                    <div class="form-section">
                        <h3>Admin Information</h3>
                        <div class="form-row">
                            <div class="form-group">
                                <label for="firstname">Firstname <span class="required">*</span></label>
                                <div class="input-group">
                                    <i class="fas fa-user"></i>
                                    <input type="text" id="firstname" name="firstname" class="form-control" required>
                                </div>
                            </div>
                            <div class="form-group">
                                <label for="lastname">Lastname <span class="required">*</span></label>
                                <div class="input-group">
                                    <i class="fas fa-user"></i>
                                    <input type="text" id="lastname" name="lastname" class="form-control" required>
                                </div>
                            </div>
                            <div class="form-group">
                                <label for="middlename">Middlename</label>
                                <div class="input-group">
                                    <i class="fas fa-user"></i>
                                    <input type="text" id="middlename" name="middlename" class="form-control">
                                </div>
                            </div>
                        </div>
                        <div class="form-row">
                            <div class="form-group">
                                <label for="contact_no">Contact Number <span class="required">*</span></label>
                                <div class="input-group">
                                    <i class="fas fa-phone"></i>
                                    <input type="tel" id="contact_no" name="contact_no" class="form-control" required>
                                </div>
                            </div>
                            <div class="form-group">
                                <label for="email">Email <span class="required">*</span></label>
                                <div class="input-group">
                                    <i class="fas fa-envelope"></i>
                                    <input type="email" id="email" name="email" class="form-control" required>
                                </div>
                            </div>
                            <div class="form-group">
                                <label for="username">Username <span class="required">*</span></label>
                                <div class="input-group">
                                    <i class="fas fa-user-circle"></i>
                                    <input type="text" id="username" name="username" class="form-control" required>
                                </div>
                            </div>
                        </div>
                        <div class="form-row">
                            <div class="form-group">
                                <label for="password">Password <span class="required">*</span></label>
                                <div class="input-group">
                                    <i class="fas fa-lock"></i>
                                    <input type="password" id="password" name="password" class="form-control" required>
                                </div>
                            </div>
                            <div class="form-group">
                                <label for="confirm_password">Confirm Password <span class="required">*</span></label>
                                <div class="input-group">
                                    <i class="fas fa-lock"></i>
                                    <input type="password" id="confirm_password" name="confirm_password" class="form-control" required>
                                </div>
                            </div>
                        </div>
                    </div>
                    <div class="form-buttons">
                        <button type="submit" name="register_admin" class="submit-btn">Register Admin</button>
                    </div>
                </form>
            </div>

            <!-- Manage Admins Section -->
            <div class="manage-admins" id="manageAdmins">
                <h2>Manage Admins</h2>
                <div class="table-container">
                    <table>
                        <thead>
                            <tr>
                                <th>Firstname</th>
                                <th>Lastname</th>
                                <th>Middlename</th>
                                <th>Contact Number</th>
                                <th>Email</th>
                                <th>Username</th>
                                <th>Actions</th>
                            </tr>
                        </thead>
                        <tbody>
                            <?php if (empty($admins)): ?>
                                <tr>
                                    <td colspan="7" style="text-align: center;">No Admins found.</td>
                                </tr>
                            <?php else: ?>
                                <?php foreach ($admins as $admin): ?>
                                    <tr>
                                        <td><?php echo htmlspecialchars($admin['firstname']); ?></td>
                                        <td><?php echo htmlspecialchars($admin['lastname']); ?></td>
                                        <td><?php echo htmlspecialchars($admin['middlename'] ?: '-'); ?></td>
                                        <td><?php echo htmlspecialchars($admin['contact_no']); ?></td>
                                        <td><?php echo htmlspecialchars($admin['email']); ?></td>
                                        <td><?php echo htmlspecialchars($admin['username']); ?></td>
                                        <td>
                                            <div class="action-buttons">
                                                <button class="edit-btn" onclick="showEditForm('<?php echo $admin['id']; ?>')">Edit</button>
                                                <form method="POST" onsubmit="return confirm('Are you sure you want to delete this Admin?');">
                                                    <input type="hidden" name="admin_id" value="<?php echo $admin['id']; ?>">
                                                    <button type="submit" name="delete_admin" class="delete-btn-table">Delete</button>
                                                </form>
                                            </div>
                                        </td>
                                    </tr>
                                <?php endforeach; ?>
                            <?php endif; ?>
                        </tbody>
                    </table>
                </div>

                <!-- Edit Admin Forms (hidden by default) -->
                <?php foreach ($admins as $admin): ?>
                    <div class="admin-form" id="editAdminForm-<?php echo $admin['id']; ?>" style="display: none;">
                        <h2>Edit Admin: <?php echo htmlspecialchars($admin['username']); ?></h2>
                        <form method="POST">
                            <input type="hidden" name="admin_id" value="<?php echo $admin['id']; ?>">
                            <div class="form-section">
                                <h3>Admin Information</h3>
                                <div class="form-row">
                                    <div class="form-group">
                                        <label for="firstname-<?php echo $admin['id']; ?>">Firstname <span class="required">*</span></label>
                                        <div class="input-group">
                                            <i class="fas fa-user"></i>
                                            <input type="text" id="firstname-<?php echo $admin['id']; ?>" name="firstname" class="form-control" value="<?php echo htmlspecialchars($admin['firstname']); ?>" required>
                                        </div>
                                    </div>
                                    <div class="form-group">
                                        <label for="lastname-<?php echo $admin['id']; ?>">Lastname <span class="required">*</span></label>
                                        <div class="input-group">
                                            <i class="fas fa-user"></i>
                                            <input type="text" id="lastname-<?php echo $admin['id']; ?>" name="lastname" class="form-control" value="<?php echo htmlspecialchars($admin['lastname']); ?>" required>
                                        </div>
                                    </div>
                                    <div class="form-group">
                                        <label for="middlename-<?php echo $admin['id']; ?>">Middlename</label>
                                        <div class="input-group">
                                            <i class="fas fa-user"></i>
                                            <input type="text" id="middlename-<?php echo $admin['id']; ?>" name="middlename" class="form-control" value="<?php echo htmlspecialchars($admin['middlename']); ?>">
                                        </div>
                                    </div>
                                </div>
                                <div class="form-row">
                                    <div class="form-group">
                                        <label for="contact_no-<?php echo $admin['id']; ?>">Contact Number <span class="required">*</span></label>
                                        <div class="input-group">
                                            <i class="fas fa-phone"></i>
                                            <input type="tel" id="contact_no-<?php echo $admin['id']; ?>" name="contact_no" class="form-control" value="<?php echo htmlspecialchars($admin['contact_no']); ?>" required>
                                        </div>
                                    </div>
                                    <div class="form-group">
                                        <label for="email-<?php echo $admin['id']; ?>">Email <span class="required">*</span></label>
                                        <div class="input-group">
                                            <i class="fas fa-envelope"></i>
                                            <input type="email" id="email-<?php echo $admin['id']; ?>" name="email" class="form-control" value="<?php echo htmlspecialchars($admin['email']); ?>" required>
                                        </div>
                                    </div>
                                    <div class="form-group">
                                        <label for="username-<?php echo $admin['id']; ?>">Username <span class="required">*</span></label>
                                        <div class="input-group">
                                            <i class="fas fa-user-circle"></i>
                                            <input type="text" id="username-<?php echo $admin['id']; ?>" name="username" class="form-control" value="<?php echo htmlspecialchars($admin['username']); ?>" required>
                                        </div>
                                    </div>
                                </div>
                            </div>
                            <div class="form-buttons">
                                <button type="submit" name="update_admin" class="submit-btn">Update Admin</button>
                                <button type="button" class="delete-btn" onclick="showManageAdmins()">Cancel</button>
                            </div>
                        </form>
                    </div>
                <?php endforeach; ?>
            </div>
        </div>
    </div>

    <!-- Secret Key Verification Popup -->
    <div class="secret-key-popup" id="secretKeyPopup" <?php echo (isset($_SESSION['show_secret_key_popup']) && $_SESSION['show_secret_key_popup']) ? 'style="display: flex;"' : ''; ?>>
        <div class="secret-key-container">
            <button class="close-btn" onclick="hideSecretKeyPopup()">×</button>
            <div class="secret-key-header">
                <h1>Verify Secret Key</h1>
                <p>Please enter the Secret Key sent to your email</p>
            </div>

            <form method="POST">
                <div class="form-group">
                    <label for="secret_key">Secret Key <span class="required">*</span></label>
                    <div class="input-group">
                        <i class="fas fa-key"></i>
                        <input type="text" id="secret_key" name="secret_key" class="form-control" required placeholder="Enter Secret Key">
                    </div>
                </div>

                <div class="form-buttons">
                    <button type="button" class="delete-btn" onclick="hideSecretKeyPopup()">Cancel</button>
                    <button type="submit" name="verify_secret_key" class="verify-btn">Verify</button>
                </div>
            </form>
        </div>
    </div>

    <script>
        function showDashboard() {
            document.getElementById('dashboardContent').style.display = 'block';
            document.getElementById('adminForm').style.display = 'none';
            document.getElementById('manageAdmins').style.display = 'none';
            document.getElementById('dashboardLink').classList.add('active');
            document.getElementById('registerAdminLink').classList.remove('active');
            document.getElementById('manageAdminsLink').classList.remove('active');
            hideAllEditForms();
        }

        function showRegisterAdmin() {
            document.getElementById('dashboardContent').style.display = 'none';
            document.getElementById('adminForm').style.display = 'block';
            document.getElementById('manageAdmins').style.display = 'none';
            document.getElementById('dashboardLink').classList.remove('active');
            document.getElementById('registerAdminLink').classList.add('active');
            document.getElementById('manageAdminsLink').classList.remove('active');
            hideAllEditForms();
        }

        function showManageAdmins() {
            document.getElementById('dashboardContent').style.display = 'none';
            document.getElementById('adminForm').style.display = 'none';
            document.getElementById('manageAdmins').style.display = 'block';
            document.getElementById('dashboardLink').classList.remove('active');
            document.getElementById('registerAdminLink').classList.remove('active');
            document.getElementById('manageAdminsLink').classList.add('active');
            hideAllEditForms();
        }

        function showEditForm(adminId) {
            hideAllEditForms();
            document.getElementById('editAdminForm-' + adminId).style.display = 'block';
        }

        function hideAllEditForms() {
            document.querySelectorAll('.admin-form[id^="editAdminForm-"]').forEach(form => {
                form.style.display = 'none';
            });
        }

        function hideSecretKeyPopup() {
            document.getElementById('secretKeyPopup').style.display = 'none';
        }

        document.addEventListener('click', function(event) {
            const secretKeyPopup = document.getElementById('secretKeyPopup');
            if (event.target === secretKeyPopup) {
                hideSecretKeyPopup();
            }
        });

        // validation for Admin registration form
        document.getElementById('adminRegisterForm').addEventListener('submit', function(e) {
            const password = document.getElementById('password').value;
            const confirmPassword = document.getElementById('confirm_password').value;
            const email = document.getElementById('email').value;
            const contactNo = document.getElementById('contact_no').value;

            if (password !== confirmPassword) {
                e.preventDefault();
                alert('Passwords do not match.');
                return;
            }

            if (password.length < 8) {
                e.preventDefault();
                alert('Password must be at least 8 characters long.');
                return;
            }

            const emailRegex = /^[^\s@]+@[^\s@]+\.[^\s@]+$/;
            if (!emailRegex.test(email)) {
                e.preventDefault();
                alert('Please enter a valid email address.');
                return;
            }

            const contactRegex = /^\d{10,15}$/;
            if (!contactRegex.test(contactNo)) {
                e.preventDefault();
                alert('Please enter a valid contact number (10-15 digits).');
                return;
            }
        });

        // validation for Edit Admin forms
        document.querySelectorAll('form[id^="editAdminForm-"]').forEach(form => {
            form.addEventListener('submit', function(e) {
                const adminId = this.querySelector('input[name="admin_id"]').value;
                const email = document.getElementById('email-' + adminId).value;
                const contactNo = document.getElementById('contact_no-' + adminId).value;

                const emailRegex = /^[^\s@]+@[^\s@]+\.[^\s@]+$/;
                if (!emailRegex.test(email)) {
                    e.preventDefault();
                    alert('Please enter a valid email address.');
                    return;
                }

                const contactRegex = /^\d{10,15}$/;
                if (!contactRegex.test(contactNo)) {
                    e.preventDefault();
                    alert('Please enter a valid contact number (10-15 digits).');
                    return;
                }
            });
        });

        // validation for Application Period form
        document.querySelector('form[method="POST"]').addEventListener('submit', function(e) {
            if (this.querySelector('[name="update_application_period"]')) {
                const deadlineInput = document.getElementById('application_deadline');
                const selectedDate = new Date(deadlineInput.value);
                const today = new Date();
                today.setHours(0, 0, 0, 0); 

                if (selectedDate < today) {
                    e.preventDefault();
                    alert('Application deadline cannot be in the past.');
                }
            }
        });
    </script>
</body>
</html>