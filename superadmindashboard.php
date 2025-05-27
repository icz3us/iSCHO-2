<?php
require './route_guard.php';
require 'vendor/autoload.php'; 

use PHPMailer\PHPMailer\PHPMailer;
use PHPMailer\PHPMailer\Exception;

if ($_SESSION['user_role'] !== 'Superadmin') {
    header('Location: login.php');
    exit;
}

define('ENCRYPTION_KEY', ''); 

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

        $mail->isHTML(true);
        $mail->Subject = "Your Secret Key for Resetting All Applicants";
        $mail->Body = "
    <div style='font-family: Arial, sans-serif; max-width: 600px; margin: 0 auto; border: 1px solid #e0e0e0; border-radius: 8px;'>
        <div style='background-color: #4f46e5; padding: 20px; text-align: center; border-top-left-radius: 8px; border-top-right-radius: 8px;'>
            <h1 style='color: #ffffff; margin: 0; font-size: 24px;'>Your Secret Key</h1>
        </div>
        <div style='padding: 30px; background-color: #ffffff;'>
            <p style='color: #1f2937; font-size: 16px; margin-bottom: 15px;'>Hello Superadmin,</p>
            <p style='color: #1f2937; font-size: 16px; margin-bottom: 15px;'>To proceed with resetting all applicants' data, please use the following Secret Key:</p>
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
        $mail->AltBody = "Hello Superadmin,\n\nTo proceed with resetting all applicants' data, please use the following Secret Key:\n\n$secretKey\n\nThis Secret Key is valid for 10 minutes. Please do not share this key with anyone.\n\nIf you did not request this action, please contact us at ischobsit@gmail.com.\n\nBest regards,\niSCHO Admin Team";

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

                        $stmt = $pdo->prepare("UPDATE users_info SET application_status = NULL, claim_status = NULL WHERE user_id IN ($in_params)");
                        $stmt->execute($applicant_ids);

                        $stmt = $pdo->prepare("DELETE FROM notices WHERE user_id IN ($in_params)");
                        $stmt->execute($applicant_ids);
                    }

                    $stmt = $pdo->prepare("UPDATE secret_keys SET used = 1 WHERE secret_key = ?");
                    $stmt->execute([$key_data['secret_key']]);

                    $pdo->commit();
                    $_SESSION['applicants_reset_success'] = "All applicant data reset successfully!";
                    $_SESSION['show_secret_key_popup'] = false;

                    // Send notice and email to all applicants
                    $stmt = $pdo->prepare("SELECT id, firstname, lastname, email FROM users WHERE role = 'Applicant'");
                    $stmt->execute();
                    $applicants = $stmt->fetchAll(PDO::FETCH_ASSOC);
                    $notice_message = "The application period has been reset. You may now apply again for the scholarship. Please log in to your account to start your new application.";
                    $email_subject = "Scholarship Application is Now Open Again!";
                    $email_errors = [];
                    if (!is_dir('logs')) {
                        mkdir('logs', 0777, true);
                    }
                    foreach ($applicants as $applicant) {
                        // Insert notice
                        try {
                            $stmtNotice = $pdo->prepare("INSERT INTO notices (user_id, message) VALUES (?, ?)");
                            $stmtNotice->execute([$applicant['id'], $notice_message]);
                        } catch (Exception $e) {
                            $errorMsg = date('Y-m-d H:i:s') . " - Notice error for user_id {$applicant['id']}: " . $e->getMessage() . "\n";
                            error_log($errorMsg, 3, 'logs/email_errors.log');
                            $email_errors[] = $errorMsg;
                        }
                        // Send email
                        $mail = new PHPMailer(true);
                        try {
                            $mail->isSMTP();
                            $mail->Host = 'smtp.gmail.com';
                            $mail->SMTPAuth = true;
                            $mail->Username = 'ischobsit@gmail.com';
                            $mail->Password = 'wcep jxly qzwn ybud';
                            $mail->SMTPSecure = PHPMailer::ENCRYPTION_STARTTLS;
                            $mail->Port = 587;

                            $mail->setFrom('ischobsit@gmail.com', 'iSCHO Admin Team');
                            $mail->addAddress($applicant['email'], $applicant['firstname'] . ' ' . $applicant['lastname']);

                            $mail->isHTML(true);
                            $mail->Subject = $email_subject;
                            $mail->Body = "<div style='font-family: Arial, sans-serif; max-width: 600px; margin: 0 auto; border: 1px solid #e0e0e0; border-radius: 8px;'><div style='background-color: #4f46e5; padding: 20px; text-align: center; border-top-left-radius: 8px; border-top-right-radius: 8px;'><h1 style='color: #ffffff; margin: 0; font-size: 24px;'>Scholarship Application Reset</h1></div><div style='padding: 30px; background-color: #ffffff;'><p style='color: #1f2937; font-size: 16px; margin-bottom: 15px;'>Dear " . htmlspecialchars($applicant['firstname'] . ' ' . $applicant['lastname']) . ",</p><p style='color: #1f2937; font-size: 16px; margin-bottom: 15px;'>The application period has been <span style='color: #4f46e5; font-weight: bold;'>reset</span>. You may now apply again for the scholarship.</p><p style='color: #1f2937; font-size: 16px; margin-bottom: 15px;'>Please log in to your account to start your new application.</p><div style='text-align: center; margin: 30px 0;'><a href='  https://32bf-2001-fd8-b812-d700-9d24-2fe6-269-a01b.ngrok-free.app/ischo2/login.php' style='display: inline-block; background-color: #4f46e5; color: #fff; padding: 12px 32px; border-radius: 6px; font-size: 16px; text-decoration: none; font-weight: 600;'>Login to iSCHO</a></div><p style='color: #6b7280; font-size: 14px; margin-bottom: 0;'>If you have any questions, please contact us at <a href='mailto:ischobsit@gmail.com' style='color: #4f46e5; text-decoration: none;'>ischobsit@gmail.com</a>.</p></div><div style='background-color: #f9fafb; padding: 15px; text-align: center; border-bottom-left-radius: 8px; border-bottom-right-radius: 8px;'><p style='color: #6b7280; font-size: 12px; margin: 0;'>© 2025 iSCHO. All rights reserved.</p></div></div>";
                            $mail->AltBody = "Dear " . $applicant['firstname'] . ' ' . $applicant['lastname'] . ",\n\nThe application period has been reset. You may now apply again for the scholarship. Please log in to your account to start your new application.\n\nBest regards,\niSCHO Admin Team";
                            $mail->send();
                        } catch (Exception $e) {
                            $errorMsg = date('Y-m-d H:i:s') . " - Email error for user_id {$applicant['id']} ({$applicant['email']}): " . $e->getMessage() . "\n";
                            error_log($errorMsg, 3, 'logs/email_errors.log');
                            $email_errors[] = $errorMsg;
                        }
                    }
                } catch (PDOException $e) {
                    $pdo->rollBack();
                    $_SESSION['secret_key_error'] = "Failed to reset applicants: " . $e->getMessage();
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
    session_start();
    $_SESSION = array();
    if (ini_get("session.use_cookies")) {
        $params = session_get_cookie_params();
        setcookie(session_name(), '', time() - 42000,
            $params["path"], $params["domain"],
            $params["secure"], $params["httponly"]
        );
    }
    session_destroy();
    header('Location: login.php?message=Logged out successfully.');
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
$total_admins = 0;

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

    $stmt = $pdo->prepare("SELECT COUNT(*) FROM users WHERE role = 'Admin'");
    $stmt->execute();
    $total_admins = $stmt->fetchColumn();
} catch (PDOException $e) {
    error_log("Error fetching statistics: " . $e->getMessage());
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
    $stmt = $pdo->prepare("SELECT id, firstname, lastname, middlename, contact_no, email FROM users WHERE role = 'Admin'");
    $stmt->execute();
    $admins = $stmt->fetchAll(PDO::FETCH_ASSOC);
} catch (PDOException $e) {
    $_SESSION['admin_list_error'] = "Error fetching admins: " . $e->getMessage();
}

// Fetch all applicants for the modals
$all_applicants = [];
try {
    $stmt = $pdo->prepare("
        SELECT u.id, u.firstname, u.lastname, u.middlename, u.contact_no, u.email,
               ui.application_status, ui.claim_status, ui.municipality
        FROM users u
        LEFT JOIN users_info ui ON u.id = ui.user_id
        WHERE u.role = 'Applicant'
    ");
    $stmt->execute();
    $all_applicants = $stmt->fetchAll(PDO::FETCH_ASSOC);
} catch (PDOException $e) {
    error_log("Error fetching applicants: " . $e->getMessage());
}

// Fetch all admins for the modal
$all_admins = [];
try {
    $stmt = $pdo->prepare("
        SELECT id, firstname, lastname, middlename, email, contact_no
        FROM users
        WHERE role = 'Admin'
    ");
    $stmt->execute();
    $all_admins = $stmt->fetchAll(PDO::FETCH_ASSOC);
} catch (PDOException $e) {
    error_log("Error fetching admins: " . $e->getMessage());
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
            $pdo->beginTransaction();

            $stmt = $pdo->prepare("
                INSERT INTO application_period (application_deadline, updated_at)
                VALUES (?, NOW())
            ");
            $stmt->execute([$application_deadline]);

            // Get all applicants
            $stmt = $pdo->prepare("SELECT id, firstname, lastname, email FROM users WHERE role = 'Applicant'");
            $stmt->execute();
            $applicants = $stmt->fetchAll(PDO::FETCH_ASSOC);

            $formatted_deadline = date('m/d/Y', strtotime($application_deadline));
            $notice_message = "The application deadline has been updated to " . $formatted_deadline . ". Please ensure to submit your application before this date.";
            $email_errors = [];

            if (!is_dir('logs')) {
                mkdir('logs', 0777, true);
            }

            foreach ($applicants as $applicant) {
                // Insert notice
                try {
                    $stmtNotice = $pdo->prepare("INSERT INTO notices (user_id, message) VALUES (?, ?)");
                    $stmtNotice->execute([$applicant['id'], $notice_message]);
                } catch (Exception $e) {
                    $errorMsg = date('Y-m-d H:i:s') . " - Notice error for user_id {$applicant['id']}: " . $e->getMessage() . "\n";
                    error_log($errorMsg, 3, 'logs/email_errors.log');
                    $email_errors[] = $errorMsg;
                }
            }

            $pdo->commit();
            $_SESSION['application_period_success'] = "Application period updated successfully!";
        } catch (PDOException $e) {
            $pdo->rollBack();
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

    if (empty($register_error)) {
        try {
            $pdo->beginTransaction();

            $hashed_password = password_hash($password, PASSWORD_DEFAULT);
            $stmt = $pdo->prepare("
                INSERT INTO users (
                    firstname, lastname, middlename, contact_no, email, password, role
                ) VALUES (?, ?, ?, ?, ?, ?, 'Admin')
            ");
            $stmt->execute([
                $firstname, $lastname, $middlename, $contact_no, $email, $hashed_password
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

    $update_error = '';

    $stmt = $pdo->prepare("SELECT id FROM users WHERE email = ? AND id != ?");
    $stmt->execute([$email, $admin_id]);
    if ($stmt->fetch()) {
        $update_error = "Email is already registered.";
    }

    if (empty($update_error)) {
        try {
            $stmt = $pdo->prepare("
                UPDATE users 
                SET firstname = ?, lastname = ?, middlename = ?, contact_no = ?, email = ? 
                WHERE id = ? AND role = 'Admin'
            ");
            $stmt->execute([$firstname, $lastname, $middlename, $contact_no, $email, $admin_id]);
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
            --primary-light: rgba(79, 70, 229, 0.1);
            --sidebar-bg: #1a202c;
            --text-color: #1f2937;
            --text-muted: #6b7280;
            --card-bg: #ffffff;
            --border-color: #e5e7eb;
            --error-color: #ef4444;
            --success-color: #22c55e;
            --warning-color: #f59e0b;
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
            background: linear-gradient(180deg, var(--sidebar-bg) 0%, #111827 100%);
            color: white;
            padding: 2rem 1.5rem;
            height: 100vh;
            position: fixed;
            top: 0;
            left: 0;
            display: flex;
            flex-direction: column;
            align-items: center;
            box-shadow: 4px 0 25px rgba(0, 0, 0, 0.15);
            border-right: 1px solid rgba(255, 255, 255, 0.1);
            transition: all 0.3s ease;
            z-index: 1000;
        }

        .sidebar .logo {
            margin-bottom: 2.5rem;
            text-align: center;
            width: 100%;
            padding: 1rem;
            border-bottom: 1px solid rgba(255, 255, 255, 0.1);
            display: flex;
            align-items: center;
            justify-content: center;
            gap: 1rem;
        }

        .sidebar .logo img {
            width: 40px;
            height: 40px;
            object-fit: contain;
        }

        .sidebar .logo span {
            font-size: 1.4rem;
            color: white;
            font-weight: 600;
            letter-spacing: 0.5px;
        }

        .profile-pic {
            width: 120px;
            height: 120px;
            border-radius: 50%; /* Changed from 20px to 50% for circular shape */
            margin-bottom: 1.5rem;
            position: relative;
            overflow: hidden;
            border: 3px solid rgba(255, 255, 255, 0.1);
            box-shadow: 0 8px 20px rgba(0, 0, 0, 0.2);
            transition: all 0.3s ease;
        }

        .profile-pic:hover {
            transform: translateY(-5px);
            border-color: var(--primary-color);
            box-shadow: 0 10px 25px rgba(79, 70, 229, 0.3);
        }

        .profile-pic::after {
            content: '';
            position: absolute;
            top: 0;
            left: 0;
            right: 0;
            bottom: 0;
            border-radius: 50%;
            box-shadow: inset 0 0 10px rgba(0, 0, 0, 0.2);
            pointer-events: none;
        }

        .profile-pic img {
            width: 100%;
            height: 100%;
            object-fit: cover;
        }

        .user-name {
            margin-bottom: 2.5rem;
            text-align: center;
            width: 100%;
            padding: 0 1rem;
        }

        .user-name div {
            font-size: 1.2rem;
            font-weight: 600;
            color: white;
            margin-bottom: 0.5rem;
            overflow: hidden;
            text-overflow: ellipsis;
            white-space: nowrap;
        }

        .user-name span {
            font-size: 0.9rem;
            color: rgba(255, 255, 255, 0.7);
            display: block;
        }

        .sidebar ul {
            list-style: none;
            width: 100%;
            padding: 0 0.5rem;
        }

        .sidebar ul li {
            margin-bottom: 0.75rem;
            width: 100%;
        }

        .sidebar ul li a {
            color: rgba(255, 255, 255, 0.8);
            text-decoration: none;
            display: flex;
            align-items: center;
            padding: 1rem 1.25rem;
            border-radius: 12px;
            transition: all 0.3s ease;
            font-size: 1rem;
            cursor: pointer;
            position: relative;
            overflow: hidden;
        }

        .sidebar ul li a::before {
            content: '';
            position: absolute;
            top: 0;
            left: 0;
            width: 100%;
            height: 100%;
            background: linear-gradient(45deg, var(--primary-color), var(--primary-hover));
            opacity: 0;
            transition: opacity 0.3s ease;
            z-index: 0;
        }

        .sidebar ul li a:hover::before {
            opacity: 0.1;
        }

        .sidebar ul li a i {
            margin-right: 1rem;
            font-size: 1.2rem;
            width: 24px;
            text-align: center;
            transition: transform 0.3s ease;
            position: relative;
            z-index: 1;
        }

        .sidebar ul li a span {
            position: relative;
            z-index: 1;
        }

        .sidebar ul li a:hover {
            color: white;
            transform: translateX(5px);
        }

        .sidebar ul li a.active {
            background: linear-gradient(45deg, var(--primary-color), var(--primary-hover));
            color: white;
            box-shadow: 0 4px 15px rgba(79, 70, 229, 0.3);
        }

        .sidebar ul li a.active::before {
            opacity: 0;
        }

        .main-content {
            margin-left: 280px;
            padding: 2rem;
            width: calc(100% - 280px);
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
            grid-template-columns: repeat(auto-fit, minmax(250px, 1fr));
            gap: 1.5rem;
            margin-bottom: 2rem;
        }

        .stat-card {
            background: linear-gradient(135deg, var(--card-bg) 0%, #f8fafc 100%);
            padding: 2rem;
            border-radius: 16px;
            box-shadow: 0 4px 6px rgba(0, 0, 0, 0.05);
            text-align: left;
            border: 1px solid var(--border-color);
            cursor: pointer;
            transition: all 0.3s ease;
            position: relative;
            overflow: hidden;
        }

        /* Total Applicants Card */
        .stat-card:nth-child(1) h3,
        .stat-card:nth-child(1) .icon {
            color: #4f46e5;
        }

        /* Approved Applicants Card */
        .stat-card:nth-child(2) h3,
        .stat-card:nth-child(2) .icon {
            color: #22c55e;
        }

        /* Denied Applicants Card */
        .stat-card:nth-child(3) h3,
        .stat-card:nth-child(3) .icon {
            color: #ef4444;
        }

        /* Total Admins Card */
        .stat-card:nth-child(4) h3,
        .stat-card:nth-child(4) .icon {
            color: #f59e0b;
        }

        .stat-card::before {
            content: '';
            position: absolute;
            top: 0;
            left: 0;
            width: 100%;
            height: 100%;
            background: linear-gradient(135deg, var(--primary-light) 0%, transparent 100%);
            opacity: 0;
            transition: opacity 0.3s ease;
        }

        .stat-card:hover {
            transform: translateY(-5px);
            box-shadow: 0 12px 20px rgba(79, 70, 229, 0.1);
        }

        .stat-card:hover::before {
            opacity: 1;
        }

        .stat-card h3 {
            font-size: 2.5rem;
            font-weight: 700;
            margin-bottom: 0.5rem;
            position: relative;
        }

        .stat-card p {
            color: var(--text-muted);
            font-size: 1rem;
            font-weight: 500;
            position: relative;
        }

        .stat-card .icon {
            position: absolute;
            top: 1rem;
            right: 1rem;
            font-size: 1.5rem;
            opacity: 0.8;
        }

        .section {
            background: var(--card-bg);
            padding: 2rem;
            border-radius: 16px;
            box-shadow: 0 4px 6px rgba(0, 0, 0, 0.05);
            margin-bottom: 2rem;
            border: 1px solid var(--border-color);
        }

        .section h2 {
            font-size: 1.5rem;
            font-weight: 600;
            margin-bottom: 1.5rem;
            color: var(--text-color);
            display: flex;
            align-items: center;
            gap: 0.75rem;
        }

        .section h2 i {
            color: var(--primary-color);
        }

        .deadline-card {
            background: linear-gradient(135deg, var(--primary-color) 0%, var(--primary-hover) 100%);
            padding: 2rem;
            border-radius: 16px;
            color: white;
            margin-bottom: 2rem;
            box-shadow: 0 8px 16px rgba(79, 70, 229, 0.2);
        }

        .deadline-card h3 {
            font-size: 2rem;
            font-weight: 700;
            margin-bottom: 0.5rem;
        }

        .deadline-card p {
            opacity: 0.9;
            font-size: 1.1rem;
            margin-bottom: 1.5rem;
        }

        .form-control {
            background: rgba(255, 255, 255, 0.9);
            border: 2px solid rgba(255, 255, 255, 0.1);
            color: var(--text-color);
            font-size: 1rem;
            padding: 0.75rem 1rem 0.75rem 2.5rem;
            border-radius: 12px;
            width: 100%;
            transition: all 0.3s ease;
        }

        .form-control:focus {
            background: white;
            border-color: rgba(255, 255, 255, 0.5);
            box-shadow: 0 4px 12px rgba(0, 0, 0, 0.1);
        }

        .submit-btn {
            background: white;
            color: var(--primary-color);
            border: none;
            padding: 0.75rem 1.5rem;
            border-radius: 12px;
            font-weight: 600;
            transition: all 0.3s ease;
        }

        .submit-btn:hover {
            background: rgba(255, 255, 255, 0.9);
            transform: translateY(-2px);
        }

        .reset-all-btn {
            background: var(--error-color);
            color: white;
            border: none;
            padding: 1rem 2rem;
            border-radius: 12px;
            font-weight: 600;
            transition: all 0.3s ease;
            display: flex;
            align-items: center;
            gap: 0.75rem;
        }

        .reset-all-btn:hover {
            background: #dc2626;
            transform: translateY(-2px);
        }

        .reset-all-btn i {
            font-size: 1.2rem;
        }

        .warning-text {
            color: var(--warning-color);
            font-size: 0.9rem;
            margin-bottom: 1rem;
            display: flex;
            align-items: center;
            gap: 0.5rem;
        }

        .warning-text i {
            font-size: 1.1rem;
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
        transition: all 0.3s ease;
        background-color: rgba(255, 255, 255, 0.8);
        }

        .form-control:focus {
            outline: none;
            border-color: var(--primary-color);
            box-shadow: 0 0 0 3px rgba(79, 70, 229, 0.1);
        }

        .form-buttons {
            display: flex;
            gap: 1rem;
            margin-top: 2rem;
            justify-content: flex-end; /* Align buttons to the right */
        }

        .form-buttons button {
            padding: 0.75rem 1.5rem;
            border-radius: 12px;
            font-weight: 600;
            transition: all 0.3s ease;
            display: flex;
            align-items: center;
            gap: 0.5rem;
            min-width: 140px; /* Set minimum width */
            justify-content: center; /* Center button content */
        }

        .form-buttons .submit-btn {
            background: linear-gradient(45deg, var(--primary-color), var(--primary-hover));
            color: white;
            border: none;
        }

        .form-buttons .submit-btn:hover {
            transform: translateY(-2px);
            box-shadow: 0 4px 12px rgba(79, 70, 229, 0.2);
        }

        .form-buttons .delete-btn,
        .form-buttons .reset-all-btn {
            padding: 0.75rem 1.5rem;
            border: none;
            border-radius: 8px;
            font-size: 1rem;
            font-weight: 500;
            cursor: pointer;
            transition: background-color 0.3s ease;
        }

        .form-buttons .delete-btn {
            background-color: #6b7280;
            color: white;
        }

        .form-buttons .delete-btn:hover {
            background-color: #5a6268;
        }

        .form-buttons .reset-all-btn {
            background-color: var(--error-color);
            color: white;
        }

        .form-buttons .reset-all-btn:hover {
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
                width: 80px;
                padding: 1.5rem 0.5rem;
            }

            .sidebar .logo span,
            .user-name,
            .sidebar ul li a span {
                display: none;
            }

            .profile-pic {
                width: 50px; /* Decreased from 80px */
                height: 50px;
                border-radius: 50%;
                margin-bottom: 1rem;
            }

            .sidebar ul li a {
                padding: 1rem;
                justify-content: center;
            }

            .sidebar ul li a i {
                margin: 0;
                font-size: 1.4rem;
            }

            .main-content {
                margin-left: 80px;
                width: calc(100% - 80px);
            }

            .modal-content {
                width: 95%;
                margin: 2vh auto;
            }
        }

        @media (max-width: 576px) {
            .sidebar {
                width: 60px;
                padding: 1rem 0.5rem;
                align-items: center;
                overflow-x: hidden;
            }

            .profile-pic {
                width: 40px; /* Decreased from 60px */
                height: 40px;
                border-radius: 50%;
                margin-bottom: 0.75rem; /* Reduced margin */
            }

            .sidebar .logo {
                margin-bottom: 1rem; /* Reduced margin */
                padding: 0.5rem;
            }
        }

        .input-group.password-group {
            position: relative;
        }
        .input-group.password-group .form-control {
            padding-right: 2.5rem;
        }
        .password-toggle {
            position: absolute;
            right: 0.75rem;
            top: 50%;
            transform: translateY(-50%);
            background: none;
            border: none;
            color: var(--text-muted);
            cursor: pointer;
            padding: 0;
            z-index: 2;
            height: 1.5rem;
            width: 1.5rem;
            display: flex;
            align-items: center;
            justify-content: center;
        }
        .password-toggle:hover {
            color: var(--text-color);
        }

        .stats-modal .modal-content {
            width: 90%;
            max-width: 1200px;
            max-height: 80vh;
            overflow-y: auto;
            padding: 2rem;
        }

        .stats-modal .table-container {
            margin-top: 1rem;
            overflow-x: auto;
        }

        .stats-modal table {
                width: 100%;
            border-collapse: collapse;
            margin-top: 1rem;
        }

        .stats-modal th,
        .stats-modal td {
            padding: 1rem;
            text-align: left;
            border-bottom: 1px solid #e5e7eb;
        }

        .stats-modal th {
            background-color: #f9fafb;
            font-weight: 600;
            color: #1f2937;
        }

        .stats-modal tr:hover {
            background-color: #f9fafb;
        }

        .stats-modal .close-btn {
            position: absolute;
            right: 1.5rem;
            top: 1.5rem;
        }

        .modal {
            display: none;
            position: fixed;
            top: 0;
            left: 0;
            width: 100%;
            height: 100%;
            background-color: rgba(0, 0, 0, 0.5);
            z-index: 1000;
        }

        .modal-content {
            position: relative;
            background-color: #fff;
            margin: 5% auto;
            padding: 2rem;
            width: 90%;
            max-width: 900px;
            border-radius: 8px;
            box-shadow: 0 4px 6px rgba(0, 0, 0, 0.1);
        }

        .stats-modal .modal-content {
            max-height: 80vh;
            overflow-y: auto;
        }

        .close-btn {
            position: absolute;
            right: 1.5rem;
            top: 1.5rem;
            font-size: 1.5rem;
            cursor: pointer;
            color: #6b7280;
        }

        .close-btn:hover {
            color: #1f2937;
        }

        .stat-card {
            cursor: pointer;
            transition: transform 0.2s ease, box-shadow 0.2s ease;
        }

        .stat-card:hover {
            transform: translateY(-5px);
            box-shadow: 0 8px 16px rgba(0, 0, 0, 0.1);
        }

        /* New styles for Register Admin and Manage Admin pages */
        .page-header {
            background: linear-gradient(135deg, var(--primary-color) 0%, var(--primary-hover) 100%);
            padding: 2rem;
            border-radius: 16px;
            color: white;
            margin-bottom: 2rem;
            box-shadow: 0 8px 16px rgba(79, 70, 229, 0.2);
        }

        .page-header h2 {
            font-size: 1.8rem;
            font-weight: 600;
                margin-bottom: 0.5rem;
        }

        .page-header p {
            opacity: 0.9;
            font-size: 1rem;
        }

        .admin-form {
            background: white;
            border-radius: 16px;
            box-shadow: 0 4px 6px rgba(0, 0, 0, 0.05);
            padding: 2rem;
            margin: 0 0 2rem;
            border: 1px solid var(--border-color);
                width: 100%;
            }

        .admin-form form {
                width: 100%;
        }

        .admin-form .form-section {
            background: linear-gradient(to right, rgba(79, 70, 229, 0.05), transparent);
            padding: 2rem;
            border-radius: 12px;
            margin-bottom: 2rem;
            width: 100%;
        }

        .form-row {
            display: flex;
            flex-wrap: wrap;
            gap: 1.5rem;
            margin-bottom: 1.5rem;
            width: 100%;
        }

        .form-group {
            flex: 1;
            min-width: 250px;
        }

        /* Register Admin specific styles */
        #adminRegisterForm .form-section {
            background: #fff;
            border: 1px solid var(--border-color);
            box-shadow: 0 2px 4px rgba(0, 0, 0, 0.05);
            margin-top: 1.5rem;
        }

        #adminRegisterForm .form-group {
            align-items: flex-start;
        }

        /* Mobile Responsive Adjustments */
        @media (max-width: 768px) {
            .admin-form {
                padding: 1.5rem;
                margin: 0 auto 2rem;
                max-width: 100%;
            }

            .admin-form .form-section {
                padding: 1.5rem;
            }

            .form-row {
                flex-direction: column;
                gap: 1rem;
                align-items: center;
            }

            .form-group {
                width: 100%;
                min-width: 100%;
            }

            #adminRegisterForm .form-section {
                margin-top: 1rem;
            }

            #adminRegisterForm .form-group {
                align-items: center;
            }
        }

        @media (max-width: 576px) {
            .admin-form {
                padding: 1rem;
            }

            .admin-form .form-section {
                padding: 1rem;
            }
        }

        .admin-form .form-section h3 {
            color: var(--primary-color);
            font-size: 1.4rem;
            margin-bottom: 1.5rem;
            display: flex;
            align-items: center;
            gap: 0.75rem;
        }

        .admin-form .form-section h3 i {
            font-size: 1.2rem;
        }

        .form-group label {
            color: var(--text-color);
            font-weight: 500;
            margin-bottom: 0.75rem;
            display: flex;
            align-items: center;
            gap: 0.5rem;
        }

        .form-group .input-group {
            position: relative;
            transition: all 0.3s ease;
        }

        .form-group .input-group:focus-within {
            transform: translateY(-2px);
        }

        .form-group .input-group i {
            color: var(--primary-color);
            opacity: 0.8;
            transition: opacity 0.3s ease;
        }

        .form-group .input-group:focus-within i {
            opacity: 1;
        }

        .form-control {
            background: white;
            border: 2px solid var(--border-color);
            padding: 0.75rem 1rem 0.75rem 2.5rem;
            border-radius: 12px;
            font-size: 1rem;
            transition: all 0.3s ease;
            width: 100%;
        }

        .form-control:focus {
            border-color: var(--primary-color);
            box-shadow: 0 0 0 4px rgba(79, 70, 229, 0.1);
        }

        .password-group .password-toggle {
            right: 1rem;
            color: var(--primary-color);
            opacity: 0.8;
            transition: all 0.3s ease;
        }

        .password-group .password-toggle:hover {
            opacity: 1;
            transform: scale(1.1);
            }

            .form-buttons {
            display: flex;
            gap: 1rem;
            margin-top: 2rem;
            justify-content: flex-end; /* Align buttons to the right */
        }

        .form-buttons button {
            padding: 0.75rem 1.5rem;
            border-radius: 12px;
            font-weight: 600;
            transition: all 0.3s ease;
            display: flex;
            align-items: center;
                gap: 0.5rem;
            min-width: 140px; /* Set minimum width */
            justify-content: center; /* Center button content */
        }

        .form-buttons .submit-btn {
            background: linear-gradient(45deg, var(--primary-color), var(--primary-hover));
            color: white;
            border: none;
        }

        .form-buttons .submit-btn:hover {
            transform: translateY(-2px);
            box-shadow: 0 4px 12px rgba(79, 70, 229, 0.2);
        }

        .form-buttons .delete-btn {
            background: #f3f4f6;
            color: var(--text-color);
            border: 1px solid var(--border-color);
        }

        .form-buttons .delete-btn:hover {
            background: #e5e7eb;
        }

        /* Manage Admins Table Styles */
        .manage-admins .section {
            background: white;
            border-radius: 16px;
            box-shadow: 0 4px 6px rgba(0, 0, 0, 0.05);
            padding: 2rem;
            margin-bottom: 2rem;
            border: 1px solid var(--border-color);
        }

        .table-container {
            margin-top: 1.5rem;
            border-radius: 12px;
            overflow-x: auto;
            border: 1px solid var(--border-color);
            -webkit-overflow-scrolling: touch; /* Smooth scrolling on iOS */
            position: relative;
                width: 100%;
            }

        table {
            width: 100%;
            min-width: 800px; /* Ensure minimum width for content */
            border-collapse: separate;
            border-spacing: 0;
        }

        th {
            background: #f8fafc;
                padding: 1rem;
            font-weight: 600;
            text-align: left;
            color: var(--text-color);
            border-bottom: 2px solid var(--border-color);
            white-space: nowrap; /* Prevent header text wrapping */
        }

        td {
            padding: 1rem;
            border-bottom: 1px solid var(--border-color);
            color: var(--text-muted);
            font-size: 0.95rem;
            white-space: nowrap; /* Prevent text wrapping */
        }

        tr:hover {
            background: linear-gradient(to right, rgba(79, 70, 229, 0.05), transparent);
        }

        .action-buttons {
            display: flex;
            gap: 0.5rem;
        }

        .action-buttons button {
            padding: 0.5rem 1rem;
            border-radius: 8px;
            font-size: 0.9rem;
            font-weight: 500;
            display: flex;
            align-items: center;
            gap: 0.5rem;
            transition: all 0.3s ease;
        }

        .action-buttons .edit-btn {
            background: var(--success-color);
            color: white;
            border: none;
        }

        .action-buttons .edit-btn:hover {
            background: #16a34a;
            transform: translateY(-2px);
        }

        .action-buttons .delete-btn-table {
            background: var(--error-color);
            color: white;
            border: none;
        }

        .action-buttons .delete-btn-table:hover {
            background: #dc2626;
            transform: translateY(-2px);
        }

        /* Success/Error Message Styles */
        .success-message,
        .error-message {
            padding: 1rem;
            border-radius: 12px;
            margin-bottom: 1rem;
            display: flex;
            align-items: center;
            gap: 0.75rem;
            animation: slideIn 0.3s ease;
        }

        .success-message {
            background: #f0fdf4;
            border: 1px solid #86efac;
            color: #16a34a;
        }

        .error-message {
            background: #fef2f2;
            border: 1px solid #fecaca;
            color: #dc2626;
        }

        @keyframes slideIn {
            from {
                transform: translateY(-10px);
                opacity: 0;
            }
            to {
                transform: translateY(0);
                opacity: 1;
            }
        }

        /* Table Container and Responsive Styles */
        .table-container {
            margin-top: 1.5rem;
            border-radius: 12px;
            overflow-x: auto;
            border: 1px solid var(--border-color);
            -webkit-overflow-scrolling: touch; /* Smooth scrolling on iOS */
            position: relative;
            width: 100%;
        }

        /* Add horizontal scroll indicator */
        .table-container::after {
            content: '→';
            position: absolute;
            right: 10px;
            top: 50%;
            transform: translateY(-50%);
            background: rgba(79, 70, 229, 0.9);
            color: white;
            padding: 8px;
            border-radius: 50%;
            font-size: 14px;
            animation: bounce 1s infinite;
            display: none;
        }

        @keyframes bounce {
            0%, 100% { transform: translateY(-50%) translateX(0); }
            50% { transform: translateY(-50%) translateX(5px); }
        }

        .manage-admins table {
            width: 100%;
            min-width: 800px; /* Ensure minimum width for content */
            border-collapse: separate;
            border-spacing: 0;
        }

        .manage-admins th {
            background: #f8fafc;
            padding: 1rem;
            font-weight: 600;
            text-align: left;
            color: var(--text-color);
            border-bottom: 2px solid var(--border-color);
            white-space: nowrap; /* Prevent header text wrapping */
        }

        .manage-admins td {
            padding: 1rem;
            border-bottom: 1px solid var(--border-color);
            color: var(--text-muted);
            font-size: 0.95rem;
            white-space: nowrap; /* Prevent text wrapping */
        }

        .manage-admins .action-buttons {
            display: flex;
            gap: 0.5rem;
            flex-wrap: nowrap;
        }

        .manage-admins .action-buttons button {
            display: inline-flex;
            align-items: center;
            gap: 0.5rem;
            padding: 0.2rem 1rem;
            border-radius: 8px;
            font-size: 0.9rem;
            font-weight: 500;
            transition: all 0.3s ease;
        }

        /* Mobile-specific styles */
        @media (max-width: 768px) {
            .table-container {
                margin: 1rem -1rem;
                width: calc(100% + 2rem);
                border-radius: 0;
                border-left: none;
                border-right: none;
            }

            .manage-admins table {
                font-size: 0.9rem;
            }

            .manage-admins th,
            .manage-admins td {
                padding: 0.75rem 1rem;
            }

            .manage-admins .action-buttons button {
                padding: 0.5rem;
                min-width: auto;
            }

            .manage-admins .action-buttons button i {
                margin: 0;
            }

            .manage-admins .action-buttons button span {
                display: none;
            }
        }

        /* Even smaller screens */
        @media (max-width: 576px) {
            .table-container {
                margin: 1rem -0.5rem;
                width: calc(100% + 1rem);
            }

            .manage-admins th,
            .manage-admins td {
                padding: 0.5rem 0.75rem;
            }
        }

        #editAdminForm {
            background: #fff;
            border-radius: 16px;
            padding: 1.5rem;
            width: 100%;
            max-width: 500px;
            margin: 1rem auto;
        }

        #editAdminForm .form-section {
            background: #f8f9ff;
            border-radius: 12px;
            padding: 1.5rem;
            width: 100%;
        }

        #editAdminForm h3 {
            color: #6366f1;
            font-size: 1.25rem;
            margin-bottom: 1.5rem;
            text-align: center;
            font-weight: 600;
        }

        #editAdminForm .form-group {
            margin-bottom: 1.25rem;
        }

        #editAdminForm .form-group label {
            display: flex;
            align-items: center;
            gap: 0.5rem;
            color: #374151;
            font-size: 0.95rem;
            margin-bottom: 0.5rem;
        }

        #editAdminForm .form-control {
            background: #fff;
            border: 1px solid #e5e7eb;
            border-radius: 8px;
            padding: 0.75rem 1rem;
            width: 100%;
            font-size: 0.95rem;
        }

        #editAdminForm .form-buttons {
            display: flex;
            justify-content: space-between;
            gap: 1rem;
            margin-top: 1.5rem;
        }

        #editAdminForm .form-buttons button {
            padding: 0.75rem 1.5rem;
            border-radius: 8px;
            font-weight: 500;
            font-size: 0.95rem;
            min-width: 120px;
            display: flex;
            align-items: center;
            justify-content: center;
            gap: 0.5rem;
        }

        #editAdminForm .form-buttons .delete-btn {
            background: #f3f4f6;
            color: #4b5563;
            border: none;
        }

        #editAdminForm .form-buttons .submit-btn {
            background: #6366f1;
            color: #fff;
            border: none;
        }

        @media (max-width: 768px) {
            #editAdminForm {
                padding: 1rem;
                margin: 0.5rem auto;
                border-radius: 12px;
            }

            #editAdminForm .form-section {
                padding: 1rem;
            }

            #editAdminForm h3 {
                font-size: 1.1rem;
                margin-bottom: 1rem;
            }

            #editAdminForm .form-group {
                margin-bottom: 1rem;
            }

            #editAdminForm .form-control {
                padding: 0.6rem 0.75rem;
            }

            #editAdminForm .form-buttons {
                flex-direction: column-reverse;
                gap: 0.75rem;
                margin-top: 1rem;
            }

            #editAdminForm .form-buttons button {
                width: 100%;
                padding: 0.6rem 1rem;
            }
        }

        @media (max-width: 576px) {
            #editAdminForm {
                padding: 0.75rem;
                margin: 0 auto;
            }

            #editAdminForm .form-section {
                padding: 0.75rem;
            }

            #editAdminForm .form-group label {
                font-size: 0.9rem;
            }

            #editAdminForm .form-control {
                font-size: 0.9rem;
            }
        }

        #editAdminForm .input-group {
            position: relative;
            width: 100%;
        }

        #editAdminForm .input-group i {
            position: absolute;
            left: 1rem;
            top: 50%;
            transform: translateY(-50%);
            color: #6366f1;
            opacity: 0.7;
        }

        #editAdminForm .form-control {
            background: #fff;
            border: 1px solid #e5e7eb;
            border-radius: 8px;
            padding: 0.75rem 1rem 0.75rem 2.5rem;
            width: 100%;
            font-size: 0.95rem;
        }

        @media (max-width: 768px) {
            #editAdminForm .input-group i {
                display: none;
            }

            #editAdminForm .form-control {
                padding: 0.75rem 1rem;
            }

            #editAdminForm .password-group .password-toggle {
                right: 0.75rem;
            }
        }

        @media (max-width: 576px) {
            #editAdminForm .form-control {
                padding: 0.6rem 0.75rem;
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
            <div class="profile-pic" style="background-image: url('./images/pfp.avif'); background-size: cover; background-position: center;"></div>
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
            <?php if (isset($_SESSION['applicants_reset_success'])): ?>
                <div class="success-message">
                    <?php echo $_SESSION['applicants_reset_success']; unset($_SESSION['applicants_reset_success']); ?>
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
                    <div class="stat-card" onclick="openStatsModal('total-modal')">
                        <i class="fas fa-users icon"></i>
                        <h3><?php echo $total_applicants; ?></h3>
                        <p>Total Applicants</p>
                    </div>
                    <div class="stat-card" onclick="openStatsModal('approved-modal')">
                        <i class="fas fa-check-circle icon"></i>
                        <h3><?php echo $approved_applicants; ?></h3>
                        <p>Approved Applicants</p>
                    </div>
                    <div class="stat-card" onclick="openStatsModal('denied-modal')">
                        <i class="fas fa-times-circle icon"></i>
                        <h3><?php echo $denied_applicants; ?></h3>
                        <p>Denied Applicants</p>
                    </div>
                    <div class="stat-card" onclick="openStatsModal('admin-modal')">
                        <i class="fas fa-user-shield icon"></i>
                        <h3><?php echo $total_admins; ?></h3>
                        <p>Total Admins</p>
                </div>
                </div>
                <div class="deadline-card">
                        <h3><?php echo htmlspecialchars($formatted_deadline); ?></h3>
                    <p>Current Application Deadline</p>
                    <form method="POST">
                            <div class="form-group">
                                <label for="application_deadline">Set New Deadline <span class="required">*</span></label>
                                <div class="input-group">
                                    <i class="fas fa-calendar-alt"></i>
                                    <input type="date" id="application_deadline" name="application_deadline" class="form-control" required>
                                </div>
                            </div>
                            <div class="form-buttons">
                            <button type="submit" name="update_application_period" class="submit-btn">
                                <i class="fas fa-clock"></i>
                                Update Deadline
                            </button>
                            </div>
                        </form>
                </div>
                <!-- Reset All Applicants Button -->
                <div class="section">
                    <h2><i class="fas fa-sync-alt"></i> Manage Applicants</h2>
                    <div class="warning-text">
                        <i class="fas fa-exclamation-triangle"></i>
                        <span>This action will reset all applicant data. Please use with caution.</span>
                    </div>
                    <p>This function should be used to reset all applicants' data once the Application Period has officially ended, ensuring that the system is cleared and ready for the next cycle while preserving applicant accounts.</p>
                    <form method="POST" onsubmit="return confirm('Are you sure you want to reset all applicant data? A Secret Key will be sent to your email for verification.');">
                        <div class="form-buttons">
                            <button type="submit" name="request_secret_key" class="reset-all-btn">
                                <i class="fas fa-redo-alt"></i>
                                Reset All Applicants
                            </button>
                        </div>
                    </form>
                </div>
            </div>

            <!-- Admin Registration Form -->
            <div class="admin-form" id="adminForm">
                <div class="page-header">
                    <h2><i class="fas fa-user-plus"></i> Register an Admin</h2>
                    <p>Create a new administrator account with the necessary permissions.</p>
                </div>
                
                <!-- Admin Information Section -->
                    <div class="form-section">
                    <h3><i class="fas fa-user-circle"></i> Admin Information</h3>
                </div>

                <form id="adminRegisterForm" method="POST">
                        <div class="form-row">
                            <div class="form-group">
                            <label for="firstname"><i class="fas fa-user"></i> Firstname <span class="required">*</span></label>
                                <div class="input-group">
                                    <i class="fas fa-user"></i>
                                <input type="text" id="firstname" name="firstname" class="form-control" required placeholder="Enter firstname">
                                </div>
                            </div>
                            <div class="form-group">
                            <label for="lastname"><i class="fas fa-user"></i> Lastname <span class="required">*</span></label>
                                <div class="input-group">
                                    <i class="fas fa-user"></i>
                                <input type="text" id="lastname" name="lastname" class="form-control" required placeholder="Enter lastname">
                                </div>
                            </div>
                            <div class="form-group">
                            <label for="middlename"><i class="fas fa-user"></i> Middlename</label>
                                <div class="input-group">
                                    <i class="fas fa-user"></i>
                                <input type="text" id="middlename" name="middlename" class="form-control" placeholder="Enter middlename">
                                </div>
                            </div>
                        </div>
                        <div class="form-row">
                            <div class="form-group">
                            <label for="contact_no"><i class="fas fa-phone"></i> Contact Number <span class="required">*</span></label>
                                <div class="input-group">
                                    <i class="fas fa-phone"></i>
                                <input type="tel" id="contact_no" name="contact_no" class="form-control" required placeholder="Enter contact number">
                                </div>
                            </div>
                            <div class="form-group">
                            <label for="email"><i class="fas fa-envelope"></i> Email <span class="required">*</span></label>
                                <div class="input-group">
                                    <i class="fas fa-envelope"></i>
                                <input type="email" id="email" name="email" class="form-control" required placeholder="Enter email address">
                                </div>
                            </div>
                        </div>
                        <div class="form-row">
                            <div class="form-group">
                            <label for="password"><i class="fas fa-lock"></i> Password <span class="required">*</span></label>
                                <div class="input-group password-group">
                                    <i class="fas fa-lock"></i>
                                <input type="password" id="password" name="password" class="form-control" required placeholder="Enter password">
                                    <button type="button" class="password-toggle" onclick="togglePassword('password')">
                                        <i class="fas fa-eye"></i>
                                    </button>
                                </div>
                            </div>
                            <div class="form-group">
                            <label for="confirm_password"><i class="fas fa-lock"></i> Confirm Password <span class="required">*</span></label>
                                <div class="input-group password-group">
                                    <i class="fas fa-lock"></i>
                                <input type="password" id="confirm_password" name="confirm_password" class="form-control" required placeholder="Confirm password">
                                    <button type="button" class="password-toggle" onclick="togglePassword('confirm_password')">
                                        <i class="fas fa-eye"></i>
                                    </button>
                            </div>
                        </div>
                    </div>
                    <div class="form-buttons">
                        <button type="submit" name="register_admin" class="submit-btn">
                            <i class="fas fa-user-plus"></i>
                            Register
                        </button>
                    </div>
                </form>
            </div>

            <!-- Manage Admins Section -->
            <div class="manage-admins" id="manageAdmins">
                <div class="page-header">
                    <h2><i class="fas fa-users-cog"></i> Manage Admins</h2>
                    <p>View, edit, and manage all administrator accounts in the system.</p>
                </div>
                <div class="section">
                <div class="table-container">
                    <table>
                        <thead>
                            <tr>
                                    <th><i class="fas fa-user"></i> Firstname</th>
                                    <th><i class="fas fa-user"></i> Lastname</th>
                                    <th><i class="fas fa-user"></i> Middlename</th>
                                    <th><i class="fas fa-phone"></i> Contact Number</th>
                                    <th><i class="fas fa-envelope"></i> Email</th>
                                    <th><i class="fas fa-cog"></i> Actions</th>
                            </tr>
                        </thead>
                        <tbody>
                            <?php if (empty($admins)): ?>
                                <tr>
                                    <td colspan="6" style="text-align: center;">No Admins found.</td>
                                </tr>
                            <?php else: ?>
                                <?php foreach ($admins as $admin): ?>
                                    <tr>
                                        <td><?php echo htmlspecialchars($admin['firstname']); ?></td>
                                        <td><?php echo htmlspecialchars($admin['lastname']); ?></td>
                                        <td><?php echo htmlspecialchars($admin['middlename'] ?: '-'); ?></td>
                                        <td><?php echo htmlspecialchars($admin['contact_no']); ?></td>
                                        <td><?php echo htmlspecialchars($admin['email']); ?></td>
                                        <td>
                                            <div class="action-buttons">
                                                    <button class="edit-btn" onclick="showEditForm('<?php echo $admin['id']; ?>', '<?php echo htmlspecialchars(addslashes($admin['firstname'])); ?>', '<?php echo htmlspecialchars(addslashes($admin['lastname'])); ?>', '<?php echo htmlspecialchars(addslashes($admin['middlename'])); ?>', '<?php echo htmlspecialchars(addslashes($admin['contact_no'])); ?>', '<?php echo htmlspecialchars(addslashes($admin['email'])); ?>')">
                                                        <i class="fas fa-edit"></i>
                                                        <span>Edit</span>
                                                    </button>
                                                    <form method="POST" onsubmit="return confirm('Are you sure you want to delete this Admin?');" style="display: inline;">
                                                    <input type="hidden" name="admin_id" value="<?php echo $admin['id']; ?>">
                                                        <button type="submit" name="delete_admin" class="delete-btn-table">
                                                            <i class="fas fa-trash-alt"></i>
                                                            <span>Delete</span>
                                                        </button>
                                                </form>
                                            </div>
                                        </td>
                                    </tr>
                                <?php endforeach; ?>
                            <?php endif; ?>
                        </tbody>
                    </table>
                </div>

                    <!-- Edit Admin Form -->
                    <form id="editAdminForm" class="admin-form" method="POST" style="display:none; margin-top: 2rem;">
                    <input type="hidden" name="admin_id" id="edit-admin-id">
                        <div class="form-section">
                            <h3><i class="fas fa-user-edit"></i> Edit Admin Information</h3>
                                <div class="form-row">
                                    <div class="form-group">
                                    <label for="edit-firstname"><i class="fas fa-user"></i> Firstname <span class="required">*</span></label>
                                        <div class="input-group">
                                            <i class="fas fa-user"></i>
                                <input type="text" id="edit-firstname" name="firstname" class="form-control" required>
                                        </div>
                                    </div>
                                    <div class="form-group">
                                    <label for="edit-lastname"><i class="fas fa-user"></i> Lastname <span class="required">*</span></label>
                                        <div class="input-group">
                                            <i class="fas fa-user"></i>
                                <input type="text" id="edit-lastname" name="lastname" class="form-control" required>
                                        </div>
                                    </div>
                                    <div class="form-group">
                                    <label for="edit-middlename"><i class="fas fa-user"></i> Middlename</label>
                                        <div class="input-group">
                                            <i class="fas fa-user"></i>
                                <input type="text" id="edit-middlename" name="middlename" class="form-control">
                                        </div>
                                    </div>
                                </div>
                                <div class="form-row">
                                    <div class="form-group">
                                    <label for="edit-contact_no"><i class="fas fa-phone"></i> Contact Number <span class="required">*</span></label>
                                        <div class="input-group">
                                            <i class="fas fa-phone"></i>
                                <input type="tel" id="edit-contact_no" name="contact_no" class="form-control" required>
                                        </div>
                                    </div>
                                    <div class="form-group">
                                    <label for="edit-email"><i class="fas fa-envelope"></i> Email <span class="required">*</span></label>
                                        <div class="input-group">
                                            <i class="fas fa-envelope"></i>
                                <input type="email" id="edit-email" name="email" class="form-control" required>
                                    </div>
                                    </div>
                                </div>
                            </div>
                            <div class="form-buttons">
                            <button type="button" class="delete-btn" onclick="hideAllEditForms()">
                                <i class="fas fa-times"></i> Cancel
                            </button>
                            <button type="submit" name="update_admin" class="submit-btn">
                                <i class="fas fa-save"></i> Update
                            </button>
                            </div>
                        </form>
                </div>
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

    <!-- Add the modal popups -->
    <div id="total-modal" class="modal stats-modal">
        <div class="modal-content">
            <span class="close-btn" onclick="closeStatsModal('total-modal')">&times;</span>
            <h3>Total Applicants</h3>
            <div class="table-container">
                <table>
                    <thead>
                        <tr>
                            <th>Name</th>
                            <th>Email</th>
                            <th>Contact Number</th>
                            <th>Status</th>
                        </tr>
                    </thead>
                    <tbody>
                        <?php foreach ($all_applicants as $applicant): ?>
                            <tr>
                                <td><?php echo htmlspecialchars($applicant['lastname'] . ', ' . $applicant['firstname'] . ' ' . $applicant['middlename']); ?></td>
                                <td><?php echo htmlspecialchars($applicant['email']); ?></td>
                                <td><?php echo htmlspecialchars($applicant['contact_no']); ?></td>
                                <td><?php echo htmlspecialchars($applicant['application_status'] ?: 'Not Yet Submitted'); ?></td>
                            </tr>
                        <?php endforeach; ?>
                    </tbody>
                </table>
            </div>
        </div>
    </div>

    <div id="approved-modal" class="modal stats-modal">
        <div class="modal-content">
            <span class="close-btn" onclick="closeStatsModal('approved-modal')">&times;</span>
            <h3>Approved Applicants</h3>
            <div class="table-container">
                <table>
                    <thead>
                        <tr>
                            <th>Name</th>
                            <th>Email</th>
                            <th>Contact Number</th>
                            <th>Claim Status</th>
                        </tr>
                    </thead>
                    <tbody>
                        <?php foreach ($all_applicants as $applicant): 
                            if ($applicant['application_status'] === 'Approved'): ?>
                            <tr>
                                <td><?php echo htmlspecialchars($applicant['lastname'] . ', ' . $applicant['firstname'] . ' ' . $applicant['middlename']); ?></td>
                                <td><?php echo htmlspecialchars($applicant['email']); ?></td>
                                <td><?php echo htmlspecialchars($applicant['contact_no']); ?></td>
                                <td><?php echo htmlspecialchars($applicant['claim_status'] ?: 'Not Claimed'); ?></td>
                            </tr>
                        <?php endif; endforeach; ?>
                    </tbody>
                </table>
            </div>
        </div>
    </div>

    <div id="denied-modal" class="modal stats-modal">
        <div class="modal-content">
            <span class="close-btn" onclick="closeStatsModal('denied-modal')">&times;</span>
            <h3>Denied Applicants</h3>
            <div class="table-container">
                <table>
                    <thead>
                        <tr>
                            <th>Name</th>
                            <th>Email</th>
                            <th>Contact Number</th>
                            <th>Municipality</th>
                        </tr>
                    </thead>
                    <tbody>
                        <?php foreach ($all_applicants as $applicant): 
                            if ($applicant['application_status'] === 'Denied'): ?>
                            <tr>
                                <td><?php echo htmlspecialchars($applicant['lastname'] . ', ' . $applicant['firstname'] . ' ' . $applicant['middlename']); ?></td>
                                <td><?php echo htmlspecialchars($applicant['email']); ?></td>
                                <td><?php echo htmlspecialchars($applicant['contact_no']); ?></td>
                                <td><?php echo htmlspecialchars($applicant['municipality']); ?></td>
                            </tr>
                        <?php endif; endforeach; ?>
                    </tbody>
                </table>
            </div>
        </div>
    </div>

    <div id="admin-modal" class="modal stats-modal">
        <div class="modal-content">
            <span class="close-btn" onclick="closeStatsModal('admin-modal')">&times;</span>
            <h3>Total Admins</h3>
            <div class="table-container">
                <table>
                    <thead>
                        <tr>
                            <th>Name</th>
                            <th>Email</th>
                            <th>Contact Number</th>
                        </tr>
                    </thead>
                    <tbody>
                        <?php foreach ($all_admins as $admin): ?>
                            <tr>
                                <td><?php echo htmlspecialchars($admin['lastname'] . ', ' . $admin['firstname'] . ' ' . $admin['middlename']); ?></td>
                                <td><?php echo htmlspecialchars($admin['email']); ?></td>
                                <td><?php echo htmlspecialchars($admin['contact_no']); ?></td>
                            </tr>
                        <?php endforeach; ?>
                    </tbody>
                </table>
            </div>
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

        function showEditForm(adminId, firstname, lastname, middlename, contact_no, email) {
            hideAllEditForms();
            document.getElementById('editAdminForm').style.display = 'block';
            document.getElementById('edit-admin-id').value = adminId;
            document.getElementById('edit-firstname').value = firstname;
            document.getElementById('edit-lastname').value = lastname;
            document.getElementById('edit-middlename').value = middlename;
            document.getElementById('edit-contact_no').value = contact_no;
            document.getElementById('edit-email').value = email;
        }

        function hideAllEditForms() {
            document.getElementById('editAdminForm').style.display = 'none';
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

        // Add password toggle functionality
        function togglePassword(inputId) {
            const passwordInput = document.getElementById(inputId);
            const toggleButton = passwordInput.nextElementSibling;
            const icon = toggleButton.querySelector('i');

            if (passwordInput.type === 'password') {
                passwordInput.type = 'text';
                icon.classList.remove('fa-eye');
                icon.classList.add('fa-eye-slash');
            } else {
                passwordInput.type = 'password';
                icon.classList.remove('fa-eye-slash');
                icon.classList.add('fa-eye');
            }
        }

        function openStatsModal(modalId) {
            const modal = document.getElementById(modalId);
            if (modal) {
                modal.style.display = 'block';
                document.body.style.overflow = 'hidden';
            }
        }

        function closeStatsModal(modalId) {
            const modal = document.getElementById(modalId);
            if (modal) {
                modal.style.display = 'none';
                document.body.style.overflow = 'auto';
            }
        }

        // Close modal when clicking outside
        window.addEventListener('click', function(event) {
            if (event.target.classList.contains('modal')) {
                event.target.style.display = 'none';
                document.body.style.overflow = 'auto';
            }
        });
    </script>
</body>
</html>