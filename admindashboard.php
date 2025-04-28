<?php
require './route_guard.php';

use PHPMailer\PHPMailer\PHPMailer;
use PHPMailer\PHPMailer\Exception;

require 'vendor/autoload.php'; 

if ($_SESSION['user_role'] !== 'Admin') {
    header('Location: login.php');
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
    $full_name = 'Admin';
}

$initials = '';
if ($firstname && $lastname) {
    $initials = strtoupper(substr($firstname, 0, 1) . substr($lastname, 0, 1));
} else {
    $initials = 'AD'; 
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

$male_count = 0;
$female_count = 0;
$other_count = 0;

try {
    $stmt = $pdo->prepare("
        SELECT sex, COUNT(*) as count 
        FROM users_info ui
        JOIN users u ON ui.user_id = u.id
        WHERE u.role = 'Applicant'
        GROUP BY sex
    ");
    $stmt->execute();
    $gender_data = $stmt->fetchAll(PDO::FETCH_ASSOC);

    foreach ($gender_data as $row) {
        $sex = strtolower($row['sex'] ?? '');
        if ($sex === 'male') {
            $male_count = $row['count'];
        } elseif ($sex === 'female') {
            $female_count = $row['count'];
        } else {
            $other_count += $row['count'];
        }
    }
} catch (PDOException $e) {
    $_SESSION['analytics_error'] = "Error fetching gender analytics: " . $e->getMessage();
}

$all_applicants = [];
$search_query = isset($_GET['search']) ? trim($_GET['search']) : '';

try {
    $query = "
        SELECT DISTINCT
            u.id, u.firstname, u.lastname, u.middlename, u.contact_no, u.email, u.username,
            ui.application_status,
            ui.birthdate, ui.sex AS gender,
            ui.municipality, ui.barangay,
            uf.father_name, uf.mother_name,
            ur.permanent_address AS address
        FROM users u
        LEFT JOIN users_info ui ON u.id = ui.user_id
        LEFT JOIN user_fam uf ON u.id = uf.user_id
        LEFT JOIN user_residency ur ON u.id = ur.user_id
        WHERE u.role = 'Applicant'
    ";

    $params = [];
    if (!empty($search_query)) {
        $query .= "
            AND (
                CONCAT(u.firstname, ' ', u.lastname, ' ', u.middlename) LIKE ?
                OR u.email LIKE ?
                OR u.contact_no LIKE ?
            )
        ";
        $search_term = "%$search_query%";
        $params = [$search_term, $search_term, $search_term];
    }

    $stmt = $pdo->prepare($query);
    $stmt->execute($params);
    $all_applicants = $stmt->fetchAll(PDO::FETCH_ASSOC);

    foreach ($all_applicants as &$applicant) {
        $stmt = $pdo->prepare("
            SELECT 
                cor_file_path, 
                indigency_file_path, 
                voter_file_path,
                profile_picture_path 
            FROM user_docs 
            WHERE user_id = ?
            LIMIT 1
        ");
        $stmt->execute([$applicant['id']]);
        $doc = $stmt->fetch(PDO::FETCH_ASSOC);
        $applicant['documents'] = [
            'cor' => $doc['cor_file_path'] ?? null,
            'indigency' => $doc['indigency_file_path'] ?? null,
            'voter' => $doc['voter_file_path'] ?? null,
            'profile_picture' => $doc['profile_picture_path'] ?? null,
        ];

        $stmt = $pdo->prepare("
            SELECT id, message, created_at 
            FROM notices 
            WHERE user_id = ? 
            ORDER BY created_at DESC
        ");
        $stmt->execute([$applicant['id']]);
        $applicant['notices'] = $stmt->fetchAll(PDO::FETCH_ASSOC);

        $applicant_firstname = $applicant['firstname'] ?? '';
        $applicant_lastname = $applicant['lastname'] ?? '';
        $applicant['initials'] = ($applicant_firstname && $applicant_lastname) 
            ? strtoupper(substr($applicant_firstname, 0, 1) . substr($applicant_lastname, 0, 1))
            : 'NA'; 
    }
    unset($applicant);
} catch (PDOException $e) {
    $_SESSION['applicant_list_error'] = "Error fetching applicants: " . $e->getMessage();
}

if ($_SERVER['REQUEST_METHOD'] == 'POST' && (isset($_POST['approve']) || isset($_POST['deny']))) {
    $applicant_id = trim($_POST['applicant_id']);
    $status = isset($_POST['approve']) ? 'Approved' : 'Denied';

    try {
        $stmt = $pdo->prepare("UPDATE users_info SET application_status = ? WHERE user_id = ?");
        $stmt->execute([$status, $applicant_id]);
        $_SESSION['status_update_success'] = "Applicant $status successfully!";
        $email = '';
        $applicant_name = '';
        foreach ($all_applicants as $applicant) {
            if ($applicant['id'] == $applicant_id) {
                $email = $applicant['email'];
                $applicant_name = $applicant['firstname'] . ' ' . $applicant['lastname'];
                break;
            }
        }

        if ($email) {
            $mail = new PHPMailer(true);
            try {
                $mail->isSMTP();
                $mail->Host = 'smtp.gmail.com'; 
                $mail->SMTPAuth = true;
                $mail->Username = 'ischobsit@gmail.com'; 
                $mail->Password = 'wcep jxly qzwn ybud'; 
                $mail->SMTPSecure = PHPMailer::ENCRYPTION_STARTTLS;
                $mail->Port = 587;

                $mail->setFrom('ischobsit@gmail.com', 'Scholarship Admin');
                $mail->addAddress($email, $applicant_name);

                if ($status === 'Approved' && isset($_POST['schedule_date']) && isset($_POST['schedule_time']) && isset($_POST['schedule_place'])) {
                    $schedule_date = $_POST['schedule_date'];
                    $schedule_time = $_POST['schedule_time'];
                    $schedule_place = $_POST['schedule_place'];

                    $mail->isHTML(true);
                    $mail->Subject = 'Scholarship Approval - Schedule Confirmation';
                    $mail->Body = "
                        <div style='font-family: Arial, sans-serif; max-width: 600px; margin: 0 auto; border: 1px solid #e0e0e0; border-radius: 8px;'>
                            <div style='background-color: #4f46e5; padding: 20px; text-align: center; border-top-left-radius: 8px; border-top-right-radius: 8px;'>
                                <h1 style='color: #ffffff; margin: 0; font-size: 24px;'>Scholarship Approval</h1>
                            </div>
                            <div style='padding: 30px; background-color: #ffffff;'>
                                <p style='color: #1f2937; font-size: 16px; margin-bottom: 15px;'>Dear $applicant_name,</p>
                                <p style='color: #1f2937; font-size: 16px; margin-bottom: 15px;'>We are pleased to inform you that your scholarship application has been <span style='color: #22c55e; font-weight: bold;'>approved</span>!</p>
                                <p style='color: #1f2937; font-size: 16px; margin-bottom: 15px;'>Please be available for a meeting on the following schedule:</p>
                                <table style='width: 100%; border-collapse: collapse; margin-bottom: 15px;'>
                                    <tr>
                                        <td style='padding: 8px; color: #1f2937; font-size: 16px; font-weight: bold; width: 100px;'>Date:</td>
                                        <td style='padding: 8px; color: #1f2937; font-size: 16px;'>$schedule_date</td>
                                    </tr>
                                    <tr>
                                        <td style='padding: 8px; color: #1f2937; font-size: 16px; font-weight: bold; width: 100px;'>Time:</td>
                                        <td style='padding: 8px; color: #1f2937; font-size: 16px;'>$schedule_time</td>
                                    </tr>
                                    <tr>
                                        <td style='padding: 8px; color: #1f2937; font-size: 16px; font-weight: bold; width: 100px;'>Place:</td>
                                        <td style='padding: 8px; color: #1f2937; font-size: 16px;'>$schedule_place</td>
                                    </tr>
                                </table>
                                <p style='color: #1f2937; font-size: 16px; margin-bottom: 15px;'>We look forward to discussing the next steps with you.</p>
                                <p style='color: #1f2937; font-size: 16px; margin-bottom: 0;'>Best regards,<br>iSCHO Admin Team</p>
                            </div>
                            <div style='background-color: #f9fafb; padding: 15px; text-align: center; border-bottom-left-radius: 8px; border-bottom-right-radius: 8px;'>
                                <p style='color: #6b7280; font-size: 12px; margin: 0;'>© 2025 iSCHO. All rights reserved.</p>
                            </div>
                        </div>
                    ";
                    $mail->AltBody = "Dear $applicant_name,\n\nYour scholarship application has been approved!\nPlease be available for a meeting on:\nDate: $schedule_date\nTime: $schedule_time\nPlace: $schedule_place\n\nBest regards,\nScholarship Admin Team";
                } elseif ($status === 'Denied' && isset($_POST['denial_reason'])) {
                    $denial_reason = $_POST['denial_reason'];

                    $mail->isHTML(true);
                    $mail->Subject = 'Scholarship Application Denial';
                    $mail->Body = "
                        <div style='font-family: Arial, sans-serif; max-width: 600px; margin: 0 auto; border: 1px solid #e0e0e0; border-radius: 8px;'>
                            <div style='background-color: #ef4444; padding: 20px; text-align: center; border-top-left-radius: 8px; border-top-right-radius: 8px;'>
                                <h1 style='color: #ffffff; margin: 0; font-size: 24px;'>Scholarship Application Update</h1>
                            </div>
                            <div style='padding: 30px; background-color: #ffffff;'>
                                <p style='color: #1f2937; font-size: 16px; margin-bottom: 15px;'>Dear $applicant_name,</p>
                                <p style='color: #1f2937; font-size: 16px; margin-bottom: 15px;'>We regret to inform you that your scholarship application has been <span style='color: #ef4444; font-weight: bold;'>denied</span>.</p>
                                <p style='color: #1f2937; font-size: 16px; margin-bottom: 15px;'><strong>Reason for Denial:</strong> $denial_reason</p>
                                <p style='color: #1f2937; font-size: 16px; margin-bottom: 15px;'>If you have any questions or would like to appeal this decision, please contact us at <a href='mailto:ischobsit@gmail.com' style='color: #4f46e5; text-decoration: none;'>ischobsit@gmail.com</a>.</p>
                                <p style='color: #1f2937; font-size: 16px; margin-bottom: 0;'>Best regards,<br>iSCHO Admin Team</p>
                            </div>
                            <div style='background-color: #f9fafb; padding: 15px; text-align: center; border-bottom-left-radius: 8px; border-bottom-right-radius: 8px;'>
                                <p style='color: #6b7280; font-size: 12px; margin: 0;'>© 2025 iSCHO. All rights reserved.</p>
                            </div>
                        </div>
                    ";
                    $mail->AltBody = "Dear $applicant_name,\n\nWe regret to inform you that your scholarship application has been denied.\nReason for Denial: $denial_reason\n\nIf you have any questions or would like to appeal this decision, please contact us.\n\nBest regards,\nScholarship Admin Team";
                }

                $mail->send();
                $_SESSION['email_success'] = "Email sent successfully to $email!";
            } catch (Exception $e) {
                $_SESSION['email_error'] = "Failed to send email: {$mail->ErrorInfo}";
            }
        } else {
            $_SESSION['email_error'] = "Failed to send email: Applicant email not found.";
        }
    } catch (PDOException $e) {
        $_SESSION['status_update_error'] = "Failed to update status: " . $e->getMessage();
    }
    header('Location: admindashboard.php?view=all' . (!empty($search_query) ? '&search=' . urlencode($search_query) : ''));
    exit;
}

if ($_SERVER['REQUEST_METHOD'] == 'POST' && isset($_POST['send_notice'])) {
    $applicant_id = trim($_POST['applicant_id']);
    $message = trim($_POST['message']);

    if (empty($message)) {
        $_SESSION['notice_error'] = "Notice message cannot be empty.";
    } else {
        try {
            $stmt = $pdo->prepare("INSERT INTO notices (user_id, message) VALUES (?, ?)");
            $stmt->execute([$applicant_id, $message]);
            $_SESSION['notice_success'] = "Notice sent successfully!";
        } catch (PDOException $e) {
            $_SESSION['notice_error'] = "Failed to send notice: " . $e->getMessage();
        }
    }
    header('Location: admindashboard.php?view=all' . (!empty($search_query) ? '&search=' . urlencode($search_query) : ''));
    exit;
}

if ($_SERVER['REQUEST_METHOD'] == 'POST' && isset($_POST['edit_notice'])) {
    $notice_id = trim($_POST['notice_id']);
    $message = trim($_POST['message']);

    if (empty($message)) {
        $_SESSION['notice_error'] = "Notice message cannot be empty.";
    } else {
        try {
            $stmt = $pdo->prepare("UPDATE notices SET message = ? WHERE id = ?");
            $stmt->execute([$message, $notice_id]);
            $_SESSION['notice_success'] = "Notice updated successfully!";
        } catch (PDOException $e) {
            $_SESSION['notice_error'] = "Failed to update notice: " . $e->getMessage();
        }
    }
    header('Location: admindashboard.php?view=all' . (!empty($search_query) ? '&search=' . urlencode($search_query) : ''));
    exit;
}

if ($_SERVER['REQUEST_METHOD'] == 'POST' && isset($_POST['delete_notice'])) {
    $notice_id = trim($_POST['notice_id']);

    try {
        $stmt = $pdo->prepare("DELETE FROM notices WHERE id = ?");
        $stmt->execute([$notice_id]);
        $_SESSION['notice_success'] = "Notice deleted successfully!";
    } catch (PDOException $e) {
        $_SESSION['notice_error'] = "Failed to delete notice: " . $e->getMessage();
    }
    header('Location: admindashboard.php?view=all' . (!empty($search_query) ? '&search=' . urlencode($search_query) : ''));
    exit;
}

$view = isset($_GET['view']) ? $_GET['view'] : 'dashboard';
?>

<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Admin Dashboard</title>
    <link href="https://fonts.googleapis.com/css2?family=Poppins:wght@300;400;500;600&display=swap" rel="stylesheet">
    <link href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.0.0/css/all.min.css" rel="stylesheet">
    <script src="https://cdn.jsdelivr.net/npm/chart.js"></script>
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

        .charts-container {
            display: flex;
            flex-wrap: wrap;
            gap: 2rem;
            justify-content: center;
            margin-top: 2rem;
        }

        .chart-container {
            width: 350px;
            height: 350px;
        }

        .chart-container canvas {
            width: 100% !important;
            height: 100% !important;
        }

        .section {
            background-color: var(--card-bg);
            padding: 1.5rem;
            border-radius: 12px;
            box-shadow: 0 2px 4px rgba(0, 0, 0, 0.05);
            margin-bottom: 2rem;
        }

        .section-header {
            display: flex;
            justify-content: space-between;
            align-items: center;
            margin-bottom: 1rem;
        }

        .section h2 {
            font-size: 1.25rem;
            font-weight: 600;
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

        .search-container {
            display: flex;
            align-items: center;
        }

        .search-form {
            display: flex;
            align-items: center;
            max-width: 300px;
            width: 100%;
        }

        .search-input {
            width: 100%;
            padding: 0.5rem 1rem 0.5rem 2.5rem;
            border: 1px solid var(--border-color);
            border-radius: 8px;
            font-size: 0.9rem;
            transition: all 0.3s ease;
        }

        .search-input:focus {
            outline: none;
            border-color: var(--primary-color);
            box-shadow: 0 0 0 3px rgba(79, 70, 229, 0.1);
        }

        .search-form .input-group i {
            position: absolute;
            top: 50%;
            left: 1rem;
            transform: translateY(-50%);
            color: var(--text-muted);
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

        .details-btn {
            padding: 0.5rem 1rem;
            border: none;
            border-radius: 8px;
            font-size: 0.9rem;
            font-weight: 500;
            cursor: pointer;
            transition: background-color 0.3s ease;
            background-color: var(--primary-color);
            color: white;
        }

        .details-btn:hover {
            background-color: var(--primary-hover);
        }

        .modal {
            display: none;
            position: fixed;
            top: 0;
            left: 0;
            width: 100%;
            height: 100%;
            background-color: rgba(0, 0, 0, 0.5);
            justify-content: center;
            align-items: center;
            z-index: 1000;
        }

        .modal-content {
            background-color: var(--card-bg);
            padding: 2rem;
            border-radius: 12px;
            width: 90%;
            max-width: 600px;
            max-height: 80vh;
            overflow-y: auto;
            position: relative;
        }

        .modal-content h3 {
            font-size: 1.5rem;
            font-weight: 600;
            margin-bottom: 1rem;
        }

        .close-btn {
            position: absolute;
            top: 1rem;
            right: 1rem;
            font-size: 1.5rem;
            cursor: pointer;
            color: var(--text-muted);
        }

        .applicant-details {
            display: flex;
            flex-direction: column;
            align-items: center;
        }

        .applicant-details .profile-pic {
            width: 100px;
            height: 100px;
            border-radius: 50%;
            margin-bottom: 1rem;
            object-fit: cover;
        }

        .applicant-details p {
            margin-bottom: 0.5rem;
            font-size: 0.9rem;
            width: 100%;
        }

        .applicant-details p strong {
            display: inline-block;
            width: 150px;
            font-weight: 500;
        }

        .applicant-details a {
            color: var(--primary-color);
            text-decoration: none;
        }

        .applicant-details a:hover {
            text-decoration: underline;
        }

        .notices-section {
            margin-top: 1.5rem;
        }

        .notices-section h4 {
            font-size: 1.1rem;
            font-weight: 600;
            margin-bottom: 1rem;
            color: var(--text-color);
        }

        .notice-item {
            background-color: #f9fafb;
            padding: 1rem;
            border-radius: 8px;
            margin-bottom: 0.75rem;
            border-left: 4px solid var(--primary-color);
        }

        .notice-item p {
            margin-bottom: 0.5rem;
            font-size: 0.9rem;
            color: var(--text-muted);
        }

        .notice-item .notice-actions {
            display: flex;
            gap: 0.5rem;
        }

        .edit-notice-btn,
        .delete-notice-btn {
            padding: 0.4rem 0.8rem;
            border: none;
            border-radius: 6px;
            font-size: 0.85rem;
            font-weight: 500;
            cursor: pointer;
            transition: background-color 0.3s ease;
        }

        .edit-notice-btn {
            background-color: #f97316;
            color: white;
        }

        .edit-notice-btn:hover {
            background-color: #ea580c;
        }

        .delete-notice-btn {
            background-color: var(--error-color);
            color: white;
        }

        .delete-notice-btn:hover {
            background-color: #dc2626;
        }

        .modal-actions {
            display: flex;
            gap: 0.5rem;
            margin-top: 1rem;
            justify-content: flex spotting;
        }

        .approve-btn,
        .deny-btn,
        .notice-btn {
            padding: 0.5rem 1rem;
            border: none;
            border-radius: 8px;
            font-size: 0.9rem;
            font-weight: 500;
            cursor: pointer;
            transition: background-color 0.3s ease;
        }

        .approve-btn {
            background-color: var(--success-color);
            color: white;
        }

        .approve-btn:hover {
            background-color: #16a34a;
        }

        .deny-btn {
            background-color: var(--error-color);
            color: white;
        }

        .deny-btn:hover {
            background-color: #dc2626;
        }

        .notice-btn {
            background-color: var(--primary-color);
            color: white;
        }

        .notice-btn:hover {
            background-color: var(--primary-hover);
        }

        .form-section {
            margin-bottom: 2rem;
            display: none;
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

        .form-control.textarea {
            padding: 0.75rem 1rem;
            resize: vertical;
        }

        .form-buttons {
            display: flex;
            justify-content: flex-end;
            gap: 1rem;
        }

        .form-buttons .submit-btn,
        .form-buttons .delete-btn {
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

            .section-header {
                flex-direction: column;
                align-items: flex-start;
            }

            .search-container {
                width: 100%;
                justify-content: flex-start;
            }

            .search-form {
                max-width: 100%;
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
            .form-buttons .delete-btn {
                width: 100%;
            }

            .action-buttons {
                flex-direction: column;
                gap: 0.5rem;
            }

            .details-btn,
            .approve-btn,
            .deny-btn,
            .notice-btn {
                width: 100%;
            }

            .notice-actions {
                flex-direction: column;
                gap: 0.5rem;
            }

            .edit-notice-btn,
            .delete-notice-btn {
                width: 100%;
            }

            .section-header {
                flex-direction: column;
                align-items: flex-start;
            }

            .search-container {
                width: 100%;
                justify-content: flex-start;
            }

            .search-form {
                max-width: 100%;
            }
        }
    </style>
</head>
<body>
    <div class="container">
        <div class="sidebar">
            <div class="logo">
                <span>Admin Portal</span>
            </div>
            <div class="profile-pic">
                <?php echo htmlspecialchars($initials); ?>
            </div>
            <div class="user-name">
                <div><?php echo htmlspecialchars($full_name); ?></div>
            </div>
            <ul>
                <li><a href="admindashboard.php?view=dashboard" id="dashboardLink" class="<?php echo $view === 'dashboard' ? 'active' : ''; ?>"><i class="fas fa-tachometer-alt"></i><span>Dashboard</span></a></li>
                <li><a href="admindashboard.php?view=all" id="allApplicantsLink" class="<?php echo $view === 'all' ? 'active' : ''; ?>"><i class="fas fa-users"></i><span>All Applicants</span></a></li>
                <li><a href="admindashboard.php?action=logout"><i class="fas fa-sign-out-alt"></i><span>Logout</span></a></li>
            </ul>
        </div>
        <div class="main-content">
            <div class="header">
                <h1>Admin Dashboard</h1>
                <div class="user-profile">
                    <span class="username"><?php echo htmlspecialchars($full_name); ?></span>
                    <div class="avatar"><?php echo htmlspecialchars($initials); ?></div>
                </div>
            </div>

            <div class="welcome-text">
                <p>Welcome to the Admin Dashboard</p>
            </div>

            <!-- Display Success/Error Messages -->
            <?php if (isset($_SESSION['analytics_error'])): ?>
                <div class="error-message">
                    <?php echo $_SESSION['analytics_error']; unset($_SESSION['analytics_error']); ?>
                </div>
            <?php endif; ?>
            <?php if (isset($_SESSION['applicant_list_error'])): ?>
                <div class="error-message">
                    <?php echo $_SESSION['applicant_list_error']; unset($_SESSION['applicant_list_error']); ?>
                </div>
            <?php endif; ?>
            <?php if (isset($_SESSION['status_update_success'])): ?>
                <div class="success-message">
                    <?php echo $_SESSION['status_update_success']; unset($_SESSION['status_update_success']); ?>
                </div>
            <?php endif; ?>
            <?php if (isset($_SESSION['status_update_error'])): ?>
                <div class="error-message">
                    <?php echo $_SESSION['status_update_error']; unset($_SESSION['status_update_error']); ?>
                </div>
            <?php endif; ?>
            <?php if (isset($_SESSION['notice_success'])): ?>
                <div class="success-message">
                    <?php echo $_SESSION['notice_success']; unset($_SESSION['notice_success']); ?>
                </div>
            <?php endif; ?>
            <?php if (isset($_SESSION['notice_error'])): ?>
                <div class="error-message">
                    <?php echo $_SESSION['notice_error']; unset($_SESSION['notice_error']); ?>
                </div>
            <?php endif; ?>
            <?php if (isset($_SESSION['email_success'])): ?>
                <div class="success-message">
                    <?php echo $_SESSION['email_success']; unset($_SESSION['email_success']); ?>
                </div>
            <?php endif; ?>
            <?php if (isset($_SESSION['email_error'])): ?>
                <div class="error-message">
                    <?php echo $_SESSION['email_error']; unset($_SESSION['email_error']); ?>
                </div>
            <?php endif; ?>

            <!-- Dashboard View -->
            <?php if ($view === 'dashboard'): ?>
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
                </div>
                <div class="charts-container">
                    <div class="chart-container">
                        <canvas id="applicationChart" width="350" height="350"></canvas>
                    </div>
                    <div class="chart-container">
                        <canvas id="genderChart" width="350" height="350"></canvas>
                    </div>
                </div>
            <?php endif; ?>

            <!-- All Applicants View -->
            <?php if ($view === 'all'): ?>
                <div class="section">
                    <div class="section-header">
                        <h2>All Applicants</h2>
                        <div class="search-container">
                            <form class="search-form" method="GET" action="admindashboard.php">
                                <input type="hidden" name="view" value="all">
                                <div class="input-group">
                                    <i class="fas fa-search"></i>
                                    <input type="text" name="search" class="search-input" placeholder="Search by name, email, or contact number" value="<?php echo htmlspecialchars($search_query); ?>">
                                </div>
                            </form>
                        </div>
                    </div>
                    <div class="table-container">
                        <table>
                            <thead>
                                <tr>
                                    <th>Name</th>
                                    <th>Contact Number</th>
                                    <th>Email</th>
                                    <th>Status</th>
                                    <th>Actions</th>
                                </tr>
                            </thead>
                            <tbody>
                                <?php if (empty($all_applicants)): ?>
                                    <tr>
                                        <td colspan="5" style="text-align: center;">No applicants found.</td>
                                    </tr>
                                <?php else: ?>
                                    <?php foreach ($all_applicants as $applicant): ?>
                                        <tr>
                                            <td><?php echo htmlspecialchars($applicant['lastname'] . ', ' . $applicant['firstname'] . ' ' . $applicant['middlename']); ?></td>
                                            <td><?php echo htmlspecialchars($applicant['contact_no']); ?></td>
                                            <td><?php echo htmlspecialchars($applicant['email']); ?></td>
                                            <td><?php echo htmlspecialchars($applicant['application_status'] ?: 'Not Yet Evaluated'); ?></td>
                                            <td>
                                                <div class="action-buttons">
                                                    <button class="details-btn" onclick="openModal('modal-<?php echo $applicant['id']; ?>')">View Details</button>
                                                </div>
                                            </td>
                                        </tr>
                                    <?php endforeach; ?>
                                <?php endif; ?>
                            </tbody>
                        </table>
                    </div>
                </div>
            <?php endif; ?>

            <!-- Modals for Each Applicant -->
            <?php foreach ($all_applicants as $applicant): ?>
                <div class="modal" id="modal-<?php echo $applicant['id']; ?>">
                    <div class="modal-content">
                        <span class="close-btn" onclick="closeModal('modal-<?php echo $applicant['id']; ?>')">×</span>
                        <h3>Applicant Details</h3>
                        <div class="applicant-details">
                            <?php if (!empty($applicant['documents']['profile_picture'])): ?>
                                <img src="<?php echo htmlspecialchars($applicant['documents']['profile_picture']); ?>" alt="Profile Picture" class="profile-pic">
                            <?php else: ?>
                                <div class="profile-pic">
                                    <?php echo htmlspecialchars($applicant['initials']); ?>
                                </div>
                            <?php endif; ?>
                            <p><strong>Name:</strong> <?php echo htmlspecialchars($applicant['lastname'] . ', ' . $applicant['firstname'] . ' ' . $applicant['middlename']); ?></p>
                            <p><strong>Contact Number:</strong> <?php echo htmlspecialchars($applicant['contact_no']); ?></p>
                            <p><strong>Email:</strong> <?php echo htmlspecialchars($applicant['email']); ?></p>
                            <p><strong>Birthdate:</strong> <?php echo htmlspecialchars($applicant['birthdate'] ?: '-'); ?></p>
                            <p><strong>Gender:</strong> <?php echo htmlspecialchars($applicant['gender'] ?: '-'); ?></p>
                            <p><strong>Father's Name:</strong> <?php echo htmlspecialchars($applicant['father_name'] ?: '-'); ?></p>
                            <p><strong>Mother's Name:</strong> <?php echo htmlspecialchars($applicant['mother_name'] ?: '-'); ?></p>
                            <p><strong>Address:</strong> <?php echo htmlspecialchars($applicant['address'] ?: '-'); ?></p>
                            <p><strong>Municipality:</strong> <?php echo htmlspecialchars($applicant['municipality'] ?: '-'); ?></p>
                            <p><strong>Barangay:</strong> <?php echo htmlspecialchars($applicant['barangay'] ?: '-'); ?></p>
                            <p><strong>Certificate of Registration:</strong> 
                                <?php 
                                echo isset($applicant['documents']['cor']) 
                                    ? '<a href="' . htmlspecialchars($applicant['documents']['cor']) . '" target="_blank">View</a>' 
                                    : '-'; 
                                ?>
                            </p>
                            <p><strong>Certificate of Indigency:</strong> 
                                <?php 
                                echo isset($applicant['documents']['indigency']) 
                                    ? '<a href="' . htmlspecialchars($applicant['documents']['indigency']) . '" target="_blank">View</a>' 
                                    : '-'; 
                                ?>
                            </p>
                            <p><strong>Voter Certificate:</strong> 
                                <?php 
                                echo isset($applicant['documents']['voter']) 
                                    ? '<a href="' . htmlspecialchars($applicant['documents']['voter']) . '" target="_blank">View</a>' 
                                    : '-'; 
                                ?>
                            </p>
                        </div>

                        <!-- Notices Section -->
                        <div class="notices-section">
                            <h4>Notices Sent</h4>
                            <?php if (empty($applicant['notices'])): ?>
                                <p>No notices sent to this applicant.</p>
                            <?php else: ?>
                                <?php foreach ($applicant['notices'] as $notice): ?>
                                    <div class="notice-item">
                                        <p><strong>Message:</strong> <?php echo htmlspecialchars($notice['message']); ?></p>
                                        <p><strong>Sent On:</strong> <?php echo htmlspecialchars($notice['created_at']); ?></p>
                                        <div class="notice-actions">
                                            <button class="edit-notice-btn" onclick="showEditNoticeForm('editNoticeForm-<?php echo $notice['id']; ?>')">Edit</button>
                                            <button class="delete-notice-btn" onclick="deleteNotice('<?php echo $notice['id']; ?>')">Delete</button>
                                        </div>
                                    </div>

                                    <!-- Edit Notice Form (Hidden by Default) -->
                                    <div class="form-section" id="editNoticeForm-<?php echo $notice['id']; ?>">
                                        <h3>Edit Notice</h3>
                                        <form method="POST">
                                            <input type="hidden" name="notice_id" value="<?php echo $notice['id']; ?>">
                                            <div class="form-row">
                                                <div class="form-group">
                                                    <label for="edit-message-<?php echo $notice['id']; ?>">Message <span class="required">*</span></label>
                                                    <div class="input-group">
                                                        <i class="fas fa-comment"></i>
                                                        <textarea id="edit-message-<?php echo $notice['id']; ?>" name="message" class="form-control textarea" required><?php echo htmlspecialchars($notice['message']); ?></textarea>
                                                    </div>
                                                </div>
                                            </div>
                                            <div class="form-buttons">
                                                <button type="submit" name="edit_notice" class="submit-btn">Update Notice</button>
                                                <button type="button" class="delete-btn" onclick="hideEditNoticeForm('editNoticeForm-<?php echo $notice['id']; ?>')">Cancel</button>
                                            </div>
                                        </form>
                                    </div>
                                <?php endforeach; ?>
                            <?php endif; ?>
                        </div>

                        <div class="modal-actions">
                            <button class="approve-btn" onclick="openScheduleModal('scheduleModal-<?php echo $applicant['id']; ?>')">Approve</button>
                            <button class="deny-btn" onclick="openDenialModal('denialModal-<?php echo $applicant['id']; ?>')">Deny</button>
                            <button class="notice-btn" onclick="showNoticeForm('noticeForm-<?php echo $applicant['id']; ?>')">Send Notice</button>
                        </div>

                        <!-- Send Notice Form (Hidden by Default) -->
                        <div class="form-section" id="noticeForm-<?php echo $applicant['id']; ?>">
                            <h3>Send Notice to <?php echo htmlspecialchars($applicant['firstname'] . ' ' . $applicant['lastname']); ?></h3>
                            <form method="POST">
                                <input type="hidden" name="applicant_id" value="<?php echo $applicant['id']; ?>">
                                <div class="form-row">
                                    <div class="form-group">
                                        <label for="message-<?php echo $applicant['id']; ?>">Message <span class="required">*</span></label>
                                        <div class="input-group">
                                            <i class="fas fa-comment"></i>
                                            <textarea id="message-<?php echo $applicant['id']; ?>" name="message" class="form-control textarea" required></textarea>
                                        </div>
                                    </div>
                                </div>
                                <div class="form-buttons">
                                    <button type="submit" name="send_notice" class="submit-btn">Send Notice</button>
                                    <button type="button" class="delete-btn" onclick="hideNoticeForm('noticeForm-<?php echo $applicant['id']; ?>')">Cancel</button>
                                </div>
                            </form>
                        </div>
                    </div>
                </div>

                <!-- Schedule Modal for Date, Time, and Place -->
                <div class="modal" id="scheduleModal-<?php echo $applicant['id']; ?>">
                    <div class="modal-content">
                        <span class="close-btn" onclick="closeScheduleModal('scheduleModal-<?php echo $applicant['id']; ?>')">×</span>
                        <h3>Schedule Meeting</h3>
                        <form id="scheduleForm-<?php echo $applicant['id']; ?>" method="POST">
                            <input type="hidden" name="applicant_id" value="<?php echo $applicant['id']; ?>">
                            <input type="hidden" name="approve" value="1">
                            <div class="form-row">
                                <div class="form-group">
                                    <label for="schedule-date-<?php echo $applicant['id']; ?>">Date <span class="required">*</span></label>
                                    <div class="input-group">
                                        <i class="fas fa-calendar-alt"></i>
                                        <input type="date" id="schedule-date-<?php echo $applicant['id']; ?>" name="schedule_date" class="form-control" required>
                                    </div>
                                </div>
                                <div class="form-group">
                                    <label for="schedule-time-<?php echo $applicant['id']; ?>">Time <span class="required">*</span></label>
                                    <div class="input-group">
                                        <i class="fas fa-clock"></i>
                                        <input type="time" id="schedule-time-<?php echo $applicant['id']; ?>" name="schedule_time" class="form-control" required>
                                    </div>
                                </div>
                            </div>
                            <div class="form-row">
                                <div class="form-group">
                                    <label for="schedule-place-<?php echo $applicant['id']; ?>">Place <span class="required">*</span></label>
                                    <div class="input-group">
                                        <i class="fas fa-map-marker-alt"></i>
                                        <input type="text" id="schedule-place-<?php echo $applicant['id']; ?>" name="schedule_place" class="form-control" required placeholder="Enter meeting location">
                                    </div>
                                </div>
                            </div>
                            <div class="form-buttons">
                                <button type="submit" class="submit-btn">Confirm Schedule</button>
                                <button type="button" class="delete-btn" onclick="closeScheduleModal('scheduleModal-<?php echo $applicant['id']; ?>')">Cancel</button>
                            </div>
                        </form>
                    </div>
                </div>

                <!-- Denial Reason Modal -->
                <div class="modal" id="denialModal-<?php echo $applicant['id']; ?>">
                    <div class="modal-content">
                        <span class="close-btn" onclick="closeDenialModal('denialModal-<?php echo $applicant['id']; ?>')">×</span>
                        <h3>Deny Application</h3>
                        <form id="denialForm-<?php echo $applicant['id']; ?>" method="POST">
                            <input type="hidden" name="applicant_id" value="<?php echo $applicant['id']; ?>">
                            <input type="hidden" name="deny" value="1">
                            <div class="form-row">
                                <div class="form-group">
                                    <label for="denial-reason-<?php echo $applicant['id']; ?>">Reason for Denial <span class="required">*</span></label>
                                    <div class="input-group">
                                        <i class="fas fa-exclamation-circle"></i>
                                        <textarea id="denial-reason-<?php echo $applicant['id']; ?>" name="denial_reason" class="form-control textarea" required placeholder="Enter the reason for denying the application"></textarea>
                                    </div>
                                </div>
                            </div>
                            <div class="form-buttons">
                                <button type="submit" class="submit-btn">Confirm Denial</button>
                                <button type="button" class="delete-btn" onclick="closeDenialModal('denialModal-<?php echo $applicant['id']; ?>')">Cancel</button>
                            </div>
                        </form>
                    </div>
                </div>
            <?php endforeach; ?>
        </div>
    </div>

    <script>
        document.addEventListener('DOMContentLoaded', function() {
            // Column Chart
            const ctxColumn = document.getElementById('applicationChart')?.getContext('2d');
            if (ctxColumn) {
                new Chart(ctxColumn, {
                    type: 'bar',
                    data: {
                        labels: ['Approved', 'Denied', 'Total Applications'],
                        datasets: [{
                            label: 'Application Statistics',
                            data: [<?php echo $approved_applicants; ?>, <?php echo $denied_applicants; ?>, <?php echo $total_applicants; ?>],
                            backgroundColor: [
                                'rgba(34, 197, 94, 0.7)',  
                                'rgba(239, 68, 68, 0.7)',   
                                'rgba(79, 70, 229, 0.7)'    
                            ],
                            borderColor: [
                                'rgba(34, 197, 94, 1)',
                                'rgba(239, 68, 68, 1)',
                                'rgba(79, 70, 229, 1)'
                            ],
                            borderWidth: 1
                        }]
                    },
                    options: {
                        responsive: true,
                        maintainAspectRatio: false,
                        scales: {
                            y: {
                                beginAtZero: true,
                                title: {
                                    display: true,
                                    text: 'Number of Applications'
                                }
                            },
                            x: {
                                title: {
                                    display: true,
                                    text: 'Application Status'
                                }
                            }
                        },
                        plugins: {
                            legend: {
                                display: false
                            },
                            title: {
                                display: true,
                                text: 'Application Statistics'
                            }
                        }
                    }
                });
            }

            // Pie Chart
            const ctxPie = document.getElementById('genderChart')?.getContext('2d');
            if (ctxPie) {
                new Chart(ctxPie, {
                    type: 'pie',
                    data: {
                        labels: ['Male', 'Female', 'Other'],
                        datasets: [{
                            label: 'Applicants by Gender',
                            data: [<?php echo $male_count; ?>, <?php echo $female_count; ?>, <?php echo $other_count; ?>],
                            backgroundColor: [
                                'rgba(54, 162, 235, 0.7)',  
                                'rgba(255, 99, 132, 0.7)',  
                                'rgba(255, 206, 86, 0.7)'   
                            ],
                            borderColor: [
                                'rgba(54, 162, 235, 1)',
                                'rgba(255, 99, 132, 1)',
                                'rgba(255, 206, 86, 1)'
                            ],
                            borderWidth: 1
                        }]
                    },
                    options: {
                        responsive: true,
                        maintainAspectRatio: false,
                        plugins: {
                            legend: {
                                position: 'bottom'
                            },
                            title: {
                                display: true,
                                text: 'Applicants by Gender'
                            }
                        }
                    }
                });
            }

            const searchInput = document.querySelector('.search-input');
            let debounceTimeout;
            if (searchInput) {
                searchInput.addEventListener('input', function() {
                    clearTimeout(debounceTimeout);
                    debounceTimeout = setTimeout(() => {
                        this.closest('form').submit();
                    }, 500);
                });
            }
        });

        function openModal(modalId) {
            document.getElementById(modalId).style.display = 'flex';
        }

        function closeModal(modalId) {
            document.getElementById(modalId).style.display = 'none';
            hideNoticeForm('noticeForm-' + modalId.split('-')[1]);
            const editForms = document.querySelectorAll(`[id^="editNoticeForm-"]`);
            editForms.forEach(form => form.style.display = 'none');
        }

        function openScheduleModal(modalId) {
            document.getElementById(modalId).style.display = 'flex';
        }

        function closeScheduleModal(modalId) {
            document.getElementById(modalId).style.display = 'none';
        }

        function openDenialModal(modalId) {
            document.getElementById(modalId).style.display = 'flex';
        }

        function closeDenialModal(modalId) {
            document.getElementById(modalId).style.display = 'none';
        }

        function approveApplicant(applicantId) {
            openScheduleModal('scheduleModal-' + applicantId);
        }

        function denyApplicant(applicantId) {
            openDenialModal('denialModal-' + applicantId);
        }

        function showNoticeForm(formId) {
            document.getElementById(formId).style.display = 'block';
        }

        function hideNoticeForm(formId) {
            document.getElementById(formId).style.display = 'none';
        }

        function showEditNoticeForm(formId) {
            const modalId = formId.split('-')[1].split('-')[0]; 
            document.querySelectorAll(`[id^="editNoticeForm-"]`).forEach(form => form.style.display = 'none');
            document.getElementById(`noticeForm-${modalId}`).style.display = 'none';
            document.getElementById(formId).style.display = 'block';
        }

        function hideEditNoticeForm(formId) {
            document.getElementById(formId).style.display = 'none';
        }

        function deleteNotice(noticeId) {
            if (confirm('Are you sure you want to delete this notice?')) {
                const form = document.createElement('form');
                form.method = 'POST';
                form.style.display = 'none';
                const input = document.createElement('input');
                input.type = 'hidden';
                input.name = 'notice_id';
                input.value = noticeId;
                const deleteInput = document.createElement('input');
                deleteInput.type = 'hidden';
                deleteInput.name = 'delete_notice';
                deleteInput.value = '1';
                form.appendChild(input);
                form.appendChild(deleteInput);
                document.body.appendChild(form);
                form.submit();
            }
        }

        window.onclick = function(event) {
            const modals = document.getElementsByClassName('modal');
            for (let i = 0; i < modals.length; i++) {
                if (event.target == modals[i]) {
                    modals[i].style.display = 'none';
                    const modalId = modals[i].id.split('-')[1];
                    if (modals[i].id.includes('scheduleModal') || modals[i].id.includes('denialModal')) {  
                    } else {
                        hideNoticeForm('noticeForm-' + modalId);
                        document.querySelectorAll(`[id^="editNoticeForm-"]`).forEach(form => form.style.display = 'none');
                    }
                }
            }
        }
    </script>
</body>
</html>