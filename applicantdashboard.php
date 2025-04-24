<?php
require './route_guard.php';

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
        }
    } else {
        // Invalid token, destroy session
        $_SESSION = array();
        session_destroy();
        header('Location: login.php?message=Invalid session. Please log in again.');
        exit;
    }
}

// Handle logout
if (isset($_GET['action']) && $_GET['action'] === 'logout') {
    // Delete the token from the database
    $stmt = $pdo->prepare("DELETE FROM tokens WHERE user_id = ? AND token = ?");
    $stmt->execute([$_SESSION['user_id'], $_SESSION['token']]);
    
    $_SESSION = array();
    session_destroy();
    header('Location: login.php');
    exit;
}

// Check if user is logged in
if (!isset($_SESSION['user_id'])) {
    header('Location: login.php');
    exit;
}

// Check user role
if ($_SESSION['user_role'] !== 'Applicant') {
    header('Location: login.php');
    exit;
}

// Get user name components from session for display
$lastname = isset($_SESSION['lastname']) ? $_SESSION['lastname'] : '';
$firstname = isset($_SESSION['firstname']) ? $_SESSION['firstname'] : '';
$middlename = isset($_SESSION['middlename']) ? $_SESSION['middlename'] : '';

// If individual name components aren't set, try to parse from user_name
if (empty($lastname) || empty($firstname)) {
    $user_name = isset($_SESSION['user_name']) ? $_SESSION['user_name'] : 'User';
    $name_parts = explode(',', $user_name, 2);
    if (count($name_parts) === 2) {
        $lastname = trim($name_parts[0]);
        $first_and_middle = explode(' ', trim($name_parts[1]), 2);
        $firstname = $first_and_middle[0];
        $middlename = isset($first_and_middle[1]) ? $first_and_middle[1] : '';
    } else {
        $firstname = $user_name;
    }
}
$full_name = trim("$lastname, $firstname $middlename");
if (empty($full_name) || $full_name === ',') {
    $full_name = 'User';
}

// Check if user has already submitted an application and get application deadline
$user_id = $_SESSION['user_id'];
$has_application = false;
$application_deadline = null;
$is_application_open = false;

// Initialize variables to store existing data
$user_info = [];
$user_personal = [];
$user_residency = [];
$user_fam = [];
$user_docs = [];

try {
    // Check if user has an existing application
    $stmt = $pdo->prepare("SELECT COUNT(*) FROM user_info WHERE user_id = ?");
    $stmt->execute([$user_id]);
    $count = $stmt->fetchColumn();
    $has_application = $count > 0;

    if ($has_application) {
        // Fetch existing data from all relevant tables
        // user_info
        $stmt = $pdo->prepare("SELECT * FROM user_info WHERE user_id = ?");
        $stmt->execute([$user_id]);
        $user_info = $stmt->fetch(PDO::FETCH_ASSOC);

        // user_personal
        $stmt = $pdo->prepare("SELECT * FROM user_personal WHERE user_id = ?");
        $stmt->execute([$user_id]);
        $user_personal = $stmt->fetch(PDO::FETCH_ASSOC);

        // user_residency
        $stmt = $pdo->prepare("SELECT * FROM user_residency WHERE user_id = ?");
        $stmt->execute([$user_id]);
        $user_residency = $stmt->fetch(PDO::FETCH_ASSOC);

        // user_fam
        $stmt = $pdo->prepare("SELECT * FROM user_fam WHERE user_id = ?");
        $stmt->execute([$user_id]);
        $user_fam = $stmt->fetch(PDO::FETCH_ASSOC);

        // user_docs
        $stmt = $pdo->prepare("SELECT * FROM user_docs WHERE user_id = ?");
        $stmt->execute([$user_id]);
        $user_docs = $stmt->fetch(PDO::FETCH_ASSOC);
    }

    // Fetch the application deadline
    $stmt = $pdo->prepare("SELECT application_deadline FROM application_period ORDER BY updated_at DESC LIMIT 1");
    $stmt->execute();
    $result = $stmt->fetch(PDO::FETCH_ASSOC);
    if ($result) {
        $application_deadline = $result['application_deadline'];
        $current_date = date('Y-m-d');
        $is_application_open = $current_date <= $application_deadline;
    } else {
        // If no deadline is set, assume applications are closed
        $is_application_open = false;
    }
} catch (PDOException $e) {
    $_SESSION['application_error'] = "Error checking application status: " . $e->getMessage();
}

// Format the deadline for display
$formatted_deadline = $application_deadline ? date('m/d/Y', strtotime($application_deadline)) : 'N/A';

// Handle Profile Picture Upload
if ($is_application_open && $_SERVER['REQUEST_METHOD'] == 'POST' && cirugía($_FILES['profile_picture'])) {
    $upload_dir = './uploads/';
    if (!is_dir($upload_dir)) {
        mkdir($upload_dir, 0777, true);
    }

    $allowed_types = ['image/png', 'image/jpeg', 'image/jpg'];
    $max_size = 5 * 1024 * 1024; // 5MB

    $upload_error = '';
    $file = $_FILES['profile_picture'];
    $file_name = basename($file['name']);
    $file_type = $file['type'];
    $file_size = $file['size'];
    $file_tmp = $file['tmp_name'];

    // Validate file type
    if (!in_array($file_type, $allowed_types)) {
        $_SESSION['profile_picture_error'] = "Invalid file type for profile picture. Only PNG, JPEG allowed.";
    } elseif ($file_size > $max_size) {
        $_SESSION['profile_picture_error'] = "Profile picture is too large. Max size is 5MB.";
    } else {
        // Generate unique file name
        $file_ext = pathinfo($file_name, PATHINFO_EXTENSION);
        $new_file_name = 'profile_picture_' . $user_id . '_' . time() . '.' . $file_ext;
        $file_path = $upload_dir . $new_file_name;

        // Move the uploaded file
        if (move_uploaded_file($file_tmp, $file_path)) {
            try {
                $pdo->beginTransaction();

                if ($has_application) {
                    // If user already has an application, update the profile picture path
                    $stmt = $pdo->prepare("UPDATE user_docs SET profile_picture_path = ? WHERE user_id = ?");
                    $stmt->execute([$file_path, $user_id]);

                    // Delete old profile picture if it exists
                    if (!empty($user_docs['profile_picture_path']) && file_exists($user_docs['profile_picture_path'])) {
                        unlink($user_docs['profile_picture_path']);
                    }
                } else {
                    // If no application exists, insert a new record with just the profile picture
                    $stmt = $pdo->prepare("
                        INSERT INTO user_docs (user_id, profile_picture_path)
                        VALUES (?, ?)
                        ON DUPLICATE KEY UPDATE profile_picture_path = ?
                    ");
                    $stmt->execute([$user_id, $file_path, $file_path]);
                }

                $pdo->commit();
                $user_docs['profile_picture_path'] = $file_path; // Update local variable for display
                $_SESSION['profile_picture_success'] = "Profile picture uploaded successfully!";
            } catch (PDOException $e) {
                $pdo->rollBack();
                if (file_exists($file_path)) {
                    unlink($file_path);
                }
                $_SESSION['profile_picture_error'] = "Failed to upload profile picture: " . $e->getMessage();
            }
        } else {
            $_SESSION['profile_picture_error'] = "Failed to upload profile picture.";
        }
    }
    header('Location: applicantdashboard.php');
    exit;
}

// Handle Form Submission (only if application is open)
if ($is_application_open && $_SERVER['REQUEST_METHOD'] == 'POST' && isset($_POST['submit_application'])) {
    // Personal Information
    $lastname = trim($_POST['lastname']);
    $firstname = trim($_POST['firstname']);
    $middlename = trim($_POST['middlename']);
    $sex = trim($_POST['Sex']);
    $civil_status = trim($_POST['Civil-Status']);
    $birthdate = trim($_POST['birthdate']);

    // Educational Information
    $degree = trim($_POST['track']);
    $course = trim($_POST['Course']);
    $current_college = trim($_POST['secondary_school']);

    // Residency Information
    $permanent_address = trim($_POST['permanent_address']);
    $residency_duration = trim($_POST['residency_duration']);
    $registered_voter = trim($_POST['registered_voter']);
    $father_voting_duration = isset($_POST['father_voting_duration']) ? trim($_POST['father_voting_duration']) : null;
    $mother_voting_duration = isset($_POST['mother_voting_duration']) ? trim($_POST['mother_voting_duration']) : null;
    $applicant_voting_duration = isset($_POST['applicant_voting_duration']) ? trim($_POST['applicant_voting_duration']) : null;
    $guardian_name = trim($_POST['guardian_name']);
    $relationship = trim($_POST['relationship']);
    $guardian_address = trim($_POST['guardian_address']);
    $guardian_contact = trim($_POST['guardian_contact']);

    // Family Background
    $father_name = trim($_POST['father_name']);
    $father_address = trim($_POST['father_address']);
    $father_contact = trim($_POST['father_contact']);
    $father_occupation = trim($_POST['father_occupation']);
    $father_office_address = trim($_POST['father_office_address']);
    $father_tel_no = trim($_POST['father_tel_no']);
    $father_age = trim($_POST['father_age']);
    $father_dob = trim($_POST['father_dob']);
    $father_citizenship = trim($_POST['father_citizenship']);
    $father_religion = trim($_POST['father_religion']);

    $mother_name = trim($_POST['mother_name']);
    $mother_address = trim($_POST['mother_address']);
    $mother_contact = trim($_POST['mother_contact']);
    $mother_occupation = trim($_POST['mother_occupation']);
    $mother_office_address = trim($_POST['mother_office_address']);
    $mother_tel_no = trim($_POST['mother_tel_no']);
    $mother_age = trim($_POST['mother_age']);
    $mother_dob = trim($_POST['mother_dob']);
    $mother_citizenship = trim($_POST['mother_citizenship']);
    $mother_religion = trim($_POST['mother_religion']);

    // File Upload Handling
    $upload_dir = './uploads/';
    if (!is_dir($upload_dir)) {
        mkdir($upload_dir, 0777, true);
    }

    $allowed_types = ['application/pdf', 'image/png', 'image/jpeg', 'image/jpg'];
    $max_size = 5 * 1024 * 1024; // 5MB

    $upload_error = '';
    $file_paths = [
        'cor_file' => isset($user_docs['cor_file_path']) ? $user_docs['cor_file_path'] : '',
        'indigency_file' => isset($user_docs['indigency_file_path']) ? $user_docs['indigency_file_path'] : '',
        'voter_file' => isset($user_docs['voter_file_path']) ? $user_docs['voter_file_path'] : '',
        'profile_picture' => isset($user_docs['profile_picture_path']) ? $user_docs['profile_picture_path'] : ''
    ];

    foreach (['cor_file', 'indigency_file', 'voter_file'] as $file_key) {
        if (!empty($_FILES[$file_key]['name'])) {
            $file = $_FILES[$file_key];
            $file_name = basename($file['name']);
            $file_type = $file['type'];
            $file_size = $file['size'];
            $file_tmp = $file['tmp_name'];

            // Validate file type
            if (!in_array($file_type, $allowed_types)) {
                $upload_error = "Invalid file type for $file_key. Only PDF, PNG, JPEG allowed.";
                break;
            }

            // Validate file size
            if ($file_size > $max_size) {
                $upload_error = "File $file_key is too large. Max size is 5MB.";
                break;
            }

            // Generate unique file name to avoid overwriting
            $file_ext = pathinfo($file_name, PATHINFO_EXTENSION);
            $new_file_name = $file_key . '_' . $user_id . '_' . time() . '.' . $file_ext;
            $file_path = $upload_dir . $new_file_name;

            // Move the uploaded file
            if (!move_uploaded_file($file_tmp, $file_path)) {
                $upload_error = "Failed to upload $file_key.";
                break;
            }

            // If a new file is uploaded, delete the old file if it exists
            if (!empty($file_paths[$file_key]) && file_exists($file_paths[$file_key])) {
                unlink($file_paths[$file_key]);
            }

            $file_paths[$file_key] = $file_path;
        } elseif (!$has_application) {
            $upload_error = "Missing required file: $file_key.";
            break;
        }
    }

    // Proceed with database insertion if no upload errors
    if (empty($upload_error)) {
        try {
            $pdo->beginTransaction();

            if ($has_application) {
                // Update existing application
                // Update user_info
                $stmt = $pdo->prepare("
                    UPDATE user_info
                    SET lastname = ?, firstname = ?, middlename = ?, sex = ?, civil_status = ?, birthdate = ?
                    WHERE user_id = ?
                ");
                $stmt->execute([
                    $lastname, $firstname, $middlename, $sex, $civil_status, $birthdate, $user_id
                ]);

                // Update user_personal
                $stmt = $pdo->prepare("
                    UPDATE user_personal
                    SET degree = ?, course = ?, current_college = ?
                    WHERE user_id = ?
                ");
                $stmt->execute([
                    $degree, $course, $current_college, $user_id
                ]);

                // Update user_residency
                $stmt = $pdo->prepare("
                    UPDATE user_residency
                    SET permanent_address = ?, residency_duration = ?, registered_voter = ?,
                        father_voting_duration = ?, mother_voting_duration = ?, applicant_voting_duration = ?,
                        guardian_name = ?, relationship = ?, guardian_address = ?, guardian_contact = ?
                    WHERE user_id = ?
                ");
                $stmt->execute([
                    $permanent_address, $residency_duration, $registered_voter,
                    $father_voting_duration, $mother_voting_duration, $applicant_voting_duration,
                    $guardian_name, $relationship, $guardian_address, $guardian_contact, $user_id
                ]);

                // Update user_fam
                $stmt = $pdo->prepare("
                    UPDATE user_fam
                    SET father_name = ?, father_address = ?, father_contact = ?, father_occupation = ?,
                        father_office_address = ?, father_tel_no = ?, father_age = ?, father_dob = ?, 
                        father_citizenship = ?, father_religion = ?,
                        mother_name = ?, mother_address = ?, mother_contact = ?, mother_occupation = ?,
                        mother_office_address = ?, mother_tel_no = ?, mother_age = ?, mother_dob = ?, 
                        mother_citizenship = ?, mother_religion = ?
                    WHERE user_id = ?
                ");
                $stmt->execute([
                    $father_name, $father_address, $father_contact, $father_occupation,
                    $father_office_address, $father_tel_no, $father_age, $father_dob, 
                    $father_citizenship, $father_religion,
                    $mother_name, $mother_address, $mother_contact, $mother_occupation,
                    $mother_office_address, $mother_tel_no, $mother_age, $mother_dob, 
                    $mother_citizenship, $mother_religion, $user_id
                ]);

                // Update user_docs
                $stmt = $pdo->prepare("
                    UPDATE user_docs
                    SET cor_file_path = ?, indigency_file_path = ?, voter_file_path = ?
                    WHERE user_id = ?
                ");
                $stmt->execute([
                    $file_paths['cor_file'], $file_paths['indigency_file'], $file_paths['voter_file'], $user_id
                ]);
            } else {
                // Insert new application
                // Insert into user_info
                $stmt = $pdo->prepare("
                    INSERT INTO user_info (
                        user_id, lastname, firstname, middlename, sex, civil_status, birthdate
                    ) VALUES (?, ?, ?, ?, ?, ?, ?)
                ");
                $stmt->execute([
                    $user_id, $lastname, $firstname, $middlename, $sex, $civil_status, $birthdate
                ]);

                // Insert into user_personal
                $stmt = $pdo->prepare("
                    INSERT INTO user_personal (
                        user_id, degree, course, current_college
                    ) VALUES (?, ?, ?, ?)
                ");
                $stmt->execute([
                    $user_id, $degree, $course, $current_college
                ]);

                // Insert into user_residency
                $stmt = $pdo->prepare("
                    INSERT INTO user_residency (
                        user_id, permanent_address, residency_duration, registered_voter,
                        father_voting_duration, mother_voting_duration, applicant_voting_duration,
                        guardian_name, relationship, guardian_address, guardian_contact
                    ) VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?)
                ");
                $stmt->execute([
                    $user_id, $permanent_address, $residency_duration, $registered_voter,
                    $father_voting_duration, $mother_voting_duration, $applicant_voting_duration,
                    $guardian_name, $relationship, $guardian_address, $guardian_contact
                ]);

                // Insert into user_fam
                $stmt = $pdo->prepare("
                    INSERT INTO user_fam (
                        user_id, father_name, father_address, father_contact, father_occupation,
                        father_office_address, father_tel_no, father_age, father_dob, father_citizenship, father_religion,
                        mother_name, mother_address, mother_contact, mother_occupation,
                        mother_office_address, mother_tel_no, mother_age, mother_dob, mother_citizenship, mother_religion
                    ) VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?)
                ");
                $stmt->execute([
                    $user_id, $father_name, $father_address, $father_contact, $father_occupation,
                    $father_office_address, $father_tel_no, $father_age, $father_dob, $father_citizenship, $father_religion,
                    $mother_name, $mother_address, $mother_contact, $mother_occupation,
                    $mother_office_address, $mother_tel_no, $mother_age, $mother_dob, $mother_citizenship, $mother_religion
                ]);

                // Insert into user_docs
                $stmt = $pdo->prepare("
                    INSERT INTO user_docs (
                        user_id, cor_file_path, indigency_file_path, voter_file_path
                    ) VALUES (?, ?, ?, ?)
                ");
                $stmt->execute([
                    $user_id, $file_paths['cor_file'], $file_paths['indigency_file'], $file_paths['voter_file']
                ]);
            }

            $pdo->commit();
            $_SESSION['application_success'] = $has_application ? "Application updated successfully!" : "Application submitted successfully!";
            header('Location: applicantdashboard.php');
            exit;
        } catch (PDOException $e) {
            $pdo->rollBack();
            // Delete uploaded files if transaction fails
            foreach ($file_paths as $path) {
                if (!empty($path) && file_exists($path)) {
                    unlink($path);
                }
            }
            $_SESSION['application_error'] = "Application submission failed: " . $e->getMessage();
        }
    } else {
        $_SESSION['application_error'] = $upload_error;
    }
}
?>

<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Student Dashboard - iSCHO</title>
    <link href="https://fonts.googleapis.com/css2?family=Poppins:wght@300;400;500;600&display=swap" rel="stylesheet">
    <link href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.0.0/css/all.min.css" rel="stylesheet">
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

        .sidebar .logo img {
            width: 100%;
            max-width: 100px;
            height: auto;
            display: block;
            margin: 0 auto;
        }

        .sidebar .logo p {
            font-size: 0.8rem;
            color: white;
            margin-top: 0.25rem;
        }

        .profile-pic {
            width: 100px;
            height: 100px;
            border-radius: 50%;
            margin-bottom: 1rem;
            display: flex;
            align-items: center;
            justify-content: center;
            font-size: 1.5rem;
            color: var(--text-muted);
            background-color: #e5e7eb;
            position: relative;
            overflow: hidden;
            cursor: pointer;
        }

        .profile-pic:hover .overlay {
            opacity: 1;
        }

        .profile-pic .overlay {
            position: absolute;
            top: 0;
            left: 0;
            width: 100%;
            height: 100%;
            background: rgba(0, 0, 0, 0.5);
            display: flex;
            align-items: center;
            justify-content: center;
            opacity: 0;
            transition: opacity 0.3s ease;
        }

        .profile-pic .overlay i {
            color: white;
            font-size: 1.5rem;
        }

        .profile-pic input[type="file"] {
            display: none;
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

        .user-profile .avatar {
            width: 32px;
            height: 32px;
            border-radius: 50%;
            display: flex;
            align-items: center;
            justify-content: center;
            font-size: 1rem;
            color: white;
            background-color: var(--primary-color);
            overflow: hidden;
        }

        .welcome-text {
            margin-bottom: 2rem;
        }

        .welcome-text p {
            color: var(--text-muted);
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

        .stat-card i {
            font-size: 1.5rem;
            color: var(--primary-color);
            margin-bottom: 0.5rem;
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

        .notices ul {
            list-style: none;
        }

        .notices ul li {
            margin-bottom: 0.75rem;
            color: var(--text-color);
            font-size: 0.9rem;
        }

        .notices ul li::before {
            content: '•';
            color: var(--primary-color);
            font-weight: bold;
            display: inline-block;
            width: 1em;
            margin-left: -1em;
        }

        /* Application Form Styles */
        .application-form {
            background-color: var(--card-bg);
            padding: 2rem;
            border-radius: 12px;
            box-shadow: 0 2px 4px rgba(0, 0, 0, 0.05);
        }

        .progress-bar {
            display: flex;
            justify-content: space-between;
            align-items: center;
            margin-bottom: 2rem;
        }

        .progress-step {
            display: flex;
            flex-direction: column;
            align-items: center;
            position: relative;
            flex: 1;
        }

        .progress-step:not(:last-child)::after {
            content: '';
            position: absolute;
            top: 15px;
            left: 50%;
            width: 100%;
            height: 2px;
            background-color: var(--border-color);
            z-index: -1;
        }

        .progress-step .step-circle {
            width: 30px;
            height: 30px;
            background-color: var(--border-color);
            border-radius: 50%;
            display: flex;
            align-items: center;
            justify-content: center;
            font-size: 0.9rem;
            color: white;
            transition: background-color 0.3s ease;
        }

        .progress-step.active .step-circle {
            background-color: var(--primary-color);
        }

        .progress-step .step-label {
            font-size: 0.8rem;
            color: var(--text-muted);
            margin-top: 0.5rem;
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

        .form-group label .required {
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

        .form-control[readonly],
        .form-control:disabled {
            background-color: #f1f5f9;
            cursor: not-allowed;
        }

        .radio-group {
            display: flex;
            gap: 1rem;
            align-items: center;
        }

        .radio-group label {
            display: flex;
            align-items: center;
            font-size: 0.9rem;
            margin-bottom: 0;
        }

        .form-buttons {
            display: flex;
            justify-content: flex-end;
            gap: 1rem;
        }

        .form-buttons .prev-btn,
        .form-buttons .next-btn,
        .form-buttons .submit-btn {
            padding: 0.75rem 1.5rem;
            border: none;
            border-radius: 8px;
            font-size: 1rem;
            font-weight: 500;
            cursor: pointer;
            transition: background-color 0.3s ease;
        }

        .form-buttons .prev-btn {
            background-color: #6b7280;
            color: white;
        }

        .form-buttons .prev-btn:hover:not(:disabled) {
            background-color: #5a6268;
        }

        .form-buttons .next-btn,
        .form-buttons .submit-btn {
            background-color: var(--primary-color);
            color: white;
        }

        .form-buttons .next-btn:hover:not(:disabled),
        .form-buttons .submit-btn:hover:not(:disabled) {
            background-color: var(--primary-hover);
        }

        .form-buttons .prev-btn:disabled,
        .form-buttons .next-btn:disabled,
        .form-buttons .submit-btn:disabled {
            background-color: #d1d5db;
            cursor: not-allowed;
        }

        /* Family Background Specific Styles */
        .family-background {
            display: flex;
            gap: 2rem;
            flex-wrap: wrap;
        }

        .family-member {
            flex: 1;
            min-width: 300px;
        }

        .family-member h4 {
            font-size: 1rem;
            font-weight: 600;
            margin-bottom: 1rem;
            text-transform: uppercase;
            color: var(--text-color);
        }

        /* File Upload Styles */
        .file-upload-group {
            position: relative;
            display: flex;
            align-items: center;
            gap: 0.5rem;
        }

        .file-upload-group input[type="file"] {
            display: none;
        }

        .file-upload-label {
            display: inline-flex;
            align-items: center;
            padding: 0.75rem 1rem;
            background-color: #f1f5f9;
            border: 1px solid var(--border-color);
            border-radius: 8px;
            cursor: pointer;
            transition: all 0.3s ease;
        }

        .file-upload-label i {
            margin-right: 0.5rem;
            color: var(--text-muted);
        }

        .file-upload-label:hover:not(:disabled) {
            background-color: #e5e7eb;
        }

        .file-upload-label:disabled {
            background-color: #d1d5db;
            cursor: not-allowed;
        }

        .file-name {
            font-size: 0.9rem;
            color: var(--text-muted);
            margin-left: 0.5rem;
            max-width: 200px;
            overflow: hidden;
            text-overflow: ellipsis;
            white-space: nowrap;
        }

        .file-error {
            color: var(--error-color);
            font-size: 0.8rem;
            margin-top: 0.25rem;
        }

        /* Success/Error Messages */
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

        /* Info Message for Application Status */
        .info-message {
            background-color: rgba(59, 130, 246, 0.1);
            color: #3b82f6;
            padding: 0.75rem;
            border-radius: 8px;
            margin-bottom: 1.25rem;
            font-size: 0.9rem;
        }

        /* Hide sections by default */
        .dashboard-content {
            display: block;
        }

        .application-form {
            display: none;
        }

        /* Review Section Styles */
        .form-section p {
            margin-bottom: 0.5rem;
            font-size: 0.9rem;
        }

        .form-section p strong {
            display: inline-block;
            width: 200px;
            font-weight: 500;
        }

        .form-section h4 {
            font-size: 1rem;
            font-weight: 600;
            margin-top: 1rem;
            margin-bottom: 0.5rem;
            color: var(--text-color);
        }

        .form-section h5 {
            font-size: 0.95rem;
            font-weight: 500;
            margin-top: 0.75rem;
            margin-bottom: 0.5rem;
            color: var(--text-color);
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

            .sidebar .logo img {
                max-width: 80px;
            }

            .form-row {
                flex-direction: column;
            }

            .form-group {
                min-width: 100%;
            }

            .family-background {
                flex-direction: column;
                gap: 1.5rem;
            }

            .file-name {
                max-width: 150px;
            }
        }

        @media (max-width: 576px) {
            .sidebar {
                width: 60px;
                padding: 1rem;
                align-items: center;
            }

            .sidebar .logo p,
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
            }

            .sidebar .logo img {
                max-width: 40px;
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

            .progress-bar {
                flex-wrap: wrap;
                gap: 1rem;
            }

            .progress-step:not(:last-child)::after {
                display: none;
            }

            .form-buttons {
                flex-direction: column;
                gap: 0.5rem;
            }

            .form-buttons .prev-btn,
            .form-buttons .next-btn,
            .form-buttons .submit-btn {
                width: 100%;
            }

            .file-upload-label {
                padding: 0.5rem;
                font-size: 0.9rem;
            }

            .file-name {
                max-width: 100px;
            }
        }
    </style>
</head>
<body>
    <!-- Sidebar -->
    <div class="sidebar">
        <div class="logo">
            <img src="./images/logo1.png" alt="Logo">
            <p>Scholarship Application Portal</p>
        </div>
        <form id="profilePictureForm" method="POST" enctype="multipart/form-data">
            <div class="profile-pic" style="<?php echo !empty($user_docs['profile_picture_path']) ? 'background-image: url(\'' . htmlspecialchars($user_docs['profile_picture_path']) . '\'); background-size: cover; background-position: center;' : ''; ?>">
                <?php if (empty($user_docs['profile_picture_path'])): ?>
                    <!-- Show initials if no profile picture -->
                    <?php echo htmlspecialchars(substr($firstname, 0, 1) . substr($lastname, 0, 1)); ?>
                <?php endif; ?>
                <div class="overlay">
                    <i class="fas fa-camera"></i>
                </div>
                <input type="file" id="profile-picture" name="profile_picture" accept=".png,.jpg,.jpeg" <?php echo !$is_application_open ? 'disabled' : ''; ?> onchange="document.getElementById('profilePictureForm').submit();">
            </div>
        </form>
        <div class="user-name">
            <div><?php echo htmlspecialchars($lastname . ', ' . $firstname . ' ' . $middlename); ?></div>
        </div>
        <ul>
            <li><a onclick="showDashboard()" id="dashboardLink" class="active"><i class="fas fa-home"></i> <span>Dashboard</span></a></li>
            <li><a onclick="showApplicationForm()" id="applyLink"><i class="fas fa-file-alt"></i> <span>Apply Scholarship</span></a></li>
            <li><a href="?action=logout"><i class="fas fa-sign-out-alt"></i> <span>Logout</span></a></li>
        </ul>
    </div>

    <!-- Main Content -->
    <div class="main-content">
        <!-- Dashboard Content -->
        <div class="dashboard-content" id="dashboardContent">
            <div class="header">
                <h1>Dashboard</h1>
                <div class="user-profile">
                    <span><div><?php echo htmlspecialchars($lastname . ', ' . $firstname); ?></div></span>
                    <div class="avatar" style="<?php echo !empty($user_docs['profile_picture_path']) ? 'background-image: url(\'' . htmlspecialchars($user_docs['profile_picture_path']) . '\'); background-size: cover; background-position: center;' : ''; ?>">
                        <?php if (empty($user_docs['profile_picture_path'])): ?>
                            <?php echo htmlspecialchars(substr($firstname, 0, 1)); ?>
                        <?php endif; ?>
                    </div>
                </div>
            </div>

            <div class="welcome-text">
                <h2>Welcome to the Student Dashboard</h2>
                <p>Track your scholarship applications and stay updated with important notices.</p>
            </div>

            <!-- Success/Error Messages -->
            <?php if (isset($_SESSION['profile_picture_success'])): ?>
            <div class="success-message">
                <?php echo $_SESSION['profile_picture_success']; unset($_SESSION['profile_picture_success']); ?>
            </div>
            <?php endif; ?>

            <?php if (isset($_SESSION['profile_picture_error'])): ?>
            <div class="error-message">
                <?php echo $_SESSION['profile_picture_error']; unset($_SESSION['profile_picture_error']); ?>
            </div>
            <?php endif; ?>

            <?php if (isset($_SESSION['application_success'])): ?>
            <div class="success-message">
                <?php echo $_SESSION['application_success']; unset($_SESSION['application_success']); ?>
            </div>
            <?php endif; ?>
            
            <?php if (isset($_SESSION['application_error'])): ?>
            <div class="error-message">
                <?php echo $_SESSION['application_error']; unset($_SESSION['application_error']); ?>
            </div>
            <?php endif; ?>

            <!-- Stats Section -->
            <div class="stats">
                <div class="stat-card">
                    <i class="fas fa-file-alt"></i>
                    <p>Applications Status</p>
                    <h3>Under Review</h3>
                </div>
                <div class="stat-card">
                    <i class="fas fa-bell"></i>
                    <h3>5</h3>
                    <p>New Notices</p>
                </div>
            </div>

            <!-- Important Notices Section -->
            <div class="section notices">
                <h2>Important Notices</h2>
                <ul>
                    <li>Submit your financial documents by 15th April for Application #003.</li>
                    <li>New scholarship opportunity available - Apply by 20th April.</li>
                    <li>Application deadline for Spring Scholarship extended to 30th April.</li>
                </ul>
            </div>
        </div>

        <!-- Application Form -->
        <div class="application-form" id="applicationForm">
            <div class="header">
                <h1>Apply Scholarship</h1>
                <div class="user-profile">
                    <span><div><?php echo htmlspecialchars($lastname . ', ' . $firstname); ?></div></span>
                    <div class="avatar" style="<?php echo !empty($user_docs['profile_picture_path']) ? 'background-image: url(\'' . htmlspecialchars($user_docs['profile_picture_path']) . '\'); background-size: cover; background-position: center;' : ''; ?>">
                        <?php if (empty($user_docs['profile_picture_path'])): ?>
                            <?php echo htmlspecialchars(substr($firstname, 0, 1)); ?>
                        <?php endif; ?>
                    </div>
                </div>
            </div>

            <!-- Application Status Notices -->
            <?php if (!$is_application_open): ?>
            <div class="info-message">
                The application period has ended on <?php echo htmlspecialchars($formatted_deadline); ?>. You can no longer submit or edit your application.
            </div>
            <?php elseif ($has_application): ?>
            <div class="info-message">
                You have already submitted. You can edit your application until <?php echo htmlspecialchars($formatted_deadline); ?>.
            </div>
            <?php else: ?>
            <div class="info-message">
                Application is open until <?php echo htmlspecialchars($formatted_deadline); ?>. Please submit your application before the deadline.
            </div>
            <?php endif; ?>

            <!-- Progress Bar -->
            <div class="progress-bar" id="progressBar">
                <div class="progress-step active">
                    <div class="step-circle">1</div>
                    <div class="step-label">Personal Information</div>
                </div>
                <div class="progress-step">
                    <div class="step-circle">2</div>
                    <div class="step-label">Residency</div>
                </div>
                <div class="progress-step">
                    <div class="step-circle">3</div>
                    <div class="step-label">Family Background</div>
                </div>
                <div class="progress-step">
                    <div class="step-circle">4</div>
                    <div class="step-label">Documents</div>
                </div>
                <div class="progress-step">
                    <div class="step-circle">5</div>
                    <div class="step-label">Review</div>
                </div>
            </div>

            <!-- Form Section -->
            <form id="applicationFormContent" method="POST" enctype="multipart/form-data">
                <div class="form-section" id="step1">
                    <h3>Personal Information</h3>
                    <div class="form-row">
                        <div class="form-group">
                            <label for="lastname">Lastname <span class="required">*</span></label>
                            <div class="input-group">
                                <i class="fas fa-user"></i>
                                <input type="text" id="lastname" name="lastname" class="form-control" value="<?php echo htmlspecialchars($user_info['lastname'] ?? $lastname); ?>" required <?php echo !$is_application_open ? 'disabled' : ''; ?>>
                            </div>
                        </div>
                        <div class="form-group">
                            <label for="firstname">Firstname <span class="required">*</span></label>
                            <div class="input-group">
                                <i class="fas fa-user"></i>
                                <input type="text" id="firstname" name="firstname" class="form-control" value="<?php echo htmlspecialchars($user_info['firstname'] ?? $firstname); ?>" required <?php echo !$is_application_open ? 'disabled' : ''; ?>>
                            </div>
                        </div>
                        <div class="form-group">
                            <label for="middlename">Middlename <span class="required">*</span></label>
                            <div class="input-group">
                                <i class="fas fa-user"></i>
                                <input type="text" id="middlename" name="middlename" class="form-control" value="<?php echo htmlspecialchars($user_info['middlename'] ?? $middlename); ?>" required <?php echo !$is_application_open ? 'disabled' : ''; ?>>
                            </div>
                        </div>
                    </div>
                    <div class="form-row">
                        <div class="form-group">
                            <label for="sex">Sex <span class="required">*</span></label>
                            <div class="input-group">
                                <i class="fas fa-venus-mars"></i>
                                <select id="Sex" name="Sex" class="form-control" required style="padding-left: 2.5rem;" <?php echo !$is_application_open ? 'disabled' : ''; ?>>
                                    <option value="">Choose</option>
                                    <option value="Male" <?php echo (isset($user_info['sex']) && $user_info['sex'] === 'Male') ? 'selected' : ''; ?>>Male</option>
                                    <option value="Female" <?php echo (isset($user_info['sex']) && $user_info['sex'] === 'Female') ? 'selected' : ''; ?>>Female</option>
                                    <option value="Others" <?php echo (isset($user_info['sex']) && $user_info['sex'] === 'Others') ? 'selected' : ''; ?>>Others</option>
                                </select>
                            </div>
                        </div>
                        <div class="form-group">
                            <label for="civil-status">Civil Status <span class="required">*</span></label>
                            <div class="input-group">
                                <i class="fas fa-heart"></i>
                                <select id="Civil-Status" name="Civil-Status" class="form-control" required style="padding-left: 2.5rem;" <?php echo !$is_application_open ? 'disabled' : ''; ?>>
                                    <option value="">Choose</option>
                                    <option value="Single" <?php echo (isset($user_info['civil_status']) && $user_info['civil_status'] === 'Single') ? 'selected' : ''; ?>>Single</option>
                                    <option value="Married" <?php echo (isset($user_info['civil_status']) && $user_info['civil_status'] === 'Married') ? 'selected' : ''; ?>>Married</option>
                                    <option value="Divorced" <?php echo (isset($user_info['civil_status']) && $user_info['civil_status'] === 'Divorced') ? 'selected' : ''; ?>>Divorced</option>
                                </select>
                            </div>
                        </div>
                        <div class="form-group">
                            <label for="birthdate">Birthdate <span class="required">*</span></label>
                            <div class="input-group">
                                <i class="fas fa-calendar-alt"></i>
                                <input type="date" id="birthdate" name="birthdate" class="form-control" value="<?php echo htmlspecialchars($user_info['birthdate'] ?? '2000-02-09'); ?>" required <?php echo !$is_application_open ? 'disabled' : ''; ?>>
                            </div>
                        </div>
                    </div>
                    <div class="form-row">
                        <div class="form-group">
                            <label for="track">Degree<span class="required">*</span></label>
                            <div class="input-group">
                                <i class="fas fa-graduation-cap"></i>
                                <select id="track" name="track" class="form-control" required style="padding-left: 2.5rem;" <?php echo !$is_application_open ? 'disabled' : ''; ?>>
                                    <option value="">Choose</option>
                                    <option value="Bachelor of Science" <?php echo (isset($user_personal['degree']) && $user_personal['degree'] === 'Bachelor of Science') ? 'selected' : ''; ?>>Bachelor of Science</option>
                                    <option value="Bachelor of Arts" <?php echo (isset($user_personal['degree']) && $user_personal['degree'] === 'Bachelor of Arts') ? 'selected' : ''; ?>>Bachelor of Arts</option>
                                    <option value="Bachelor of Fine Arts" <?php echo (isset($user_personal['degree']) && $user_personal['degree'] === 'Bachelor of Fine Arts') ? 'selected' : ''; ?>>Bachelor of Fine Arts</option>
                                    <option value="Bachelor of Business Administration" <?php echo (isset($user_personal['degree']) && $user_personal['degree'] === 'Bachelor of Business Administration') ? 'selected' : ''; ?>>Bachelor of Business Administration</option>
                                </select>
                            </div>
                        </div>
                        <div class="form-group">
                            <label for="Course">Course <span class="required">*</span></label>
                            <div class="input-group">
                                <i class="fas fa-book"></i>
                                <input type="text" id="Course" name="Course" class="form-control" value="<?php echo htmlspecialchars($user_personal['course'] ?? ''); ?>" required <?php echo !$is_application_open ? 'disabled' : ''; ?>>
                            </div>
                        </div>
                    </div>
                    <div class="form-row">
                        <div class="form-group full-width">
                            <label for="Current">Current College/University <span class="required">*</span></label>
                            <div class="input-group">
                                <i class="fas fa-school"></i>
                                <input type="text" id="secondary-school" name="secondary_school" class="form-control" value="<?php echo htmlspecialchars($user_personal['current_college'] ?? ''); ?>" placeholder="Enter your current School" required <?php echo !$is_application_open ? 'disabled' : ''; ?>>
                            </div>
                        </div>
                    </div>
                    <div class="form-buttons">
                        <button type="button" class="next-btn" onclick="nextStep(1)">Next</button>
                    </div>
                </div>

                <div class="form-section" id="step2" style="display: none;">
                    <h3>Residency</h3>
                    <div class="form-row">
                        <div class="form-group full-width">
                            <label for="permanent-address">Permanent Address <span class="required">*</span></label>
                            <div class="input-group">
                                <i class="fas fa-map-marker-alt"></i>
                                <input type="text" id="permanent-address" name="permanent_address" class="form-control" value="<?php echo htmlspecialchars($user_residency['permanent_address'] ?? ''); ?>" required <?php echo !$is_application_open ? 'disabled' : ''; ?>>
                            </div>
                        </div>
                    </div>
                    <div class="form-row">
                        <div class="form-group">
                            <label for="residency-duration">No. of Months/Years of Residency <span class="required">*</span></label>
                            <div class="input-group">
                                <i class="fas fa-clock"></i>
                                <input type="text" id="residency-duration" name="residency_duration" class="form-control" value="<?php echo htmlspecialchars($user_residency['residency_duration'] ?? ''); ?>" required <?php echo !$is_application_open ? 'disabled' : ''; ?>>
                            </div>
                        </div>
                    </div>
                    <div class="form-row">
                        <div class="form-group">
                            <label>Are you and your parents a registered voter? <span class="required">*</span></label>
                            <div class="radio-group">
                                <label><input type="radio" name="registered_voter" value="yes" <?php echo (isset($user_residency['registered_voter']) && $user_residency['registered_voter'] === 'yes') ? 'checked' : ''; ?> required <?php echo !$is_application_open ? 'disabled' : ''; ?>> Yes</label>
                                <label><input type="radio" name="registered_voter" value="no" <?php echo (isset($user_residency['registered_voter']) && $user_residency['registered_voter'] === 'no') ? 'checked' : ''; ?> <?php echo !$is_application_open ? 'disabled' : ''; ?>> No</label>
                                <label><input type="radio" name="registered_voter" value="guardian" <?php echo (isset($user_residency['registered_voter']) && $user_residency['registered_voter'] === 'guardian') ? 'checked' : ''; ?> <?php echo !$is_application_open ? 'disabled' : ''; ?>> Guardian Only</label>
                            </div>
                        </div>
                    </div>
                    <div class="form-row">
                        <div class="form-group">
                            <h4 for="residency-length">If Yes, How Long?</h4>
                        </div>
                    </div>
                    <div class="form-row">
                        <div class="form-group">
                            <label for="father_voting_duration">Father</label>
                            <div class="input-group">
                                <i class="fas fa-clock"></i>
                                <select id="father_voting_duration" name="father_voting_duration" class="form-control" style="padding-left: 2.5rem;" <?php echo !$is_application_open ? 'disabled' : ''; ?>>
                                    <option value="">Choose</option>
                                    <option value="1" <?php echo (isset($user_residency['father_voting_duration']) && $user_residency['father_voting_duration'] === '1') ? 'selected' : ''; ?>>1</option>
                                    <option value="2" <?php echo (isset($user_residency['father_voting_duration']) && $user_residency['father_voting_duration'] === '2') ? 'selected' : ''; ?>>2</option>
                                    <option value="3" <?php echo (isset($user_residency['father_voting_duration']) && $user_residency['father_voting_duration'] === '3') ? 'selected' : ''; ?>>3</option>
                                    <option value="4" <?php echo (isset($user_residency['father_voting_duration']) && $user_residency['father_voting_duration'] === '4') ? 'selected' : ''; ?>>4</option>
                                    <option value="5" <?php echo (isset($user_residency['father_voting_duration']) && $user_residency['father_voting_duration'] === '5') ? 'selected' : ''; ?>>5</option>
                                    <option value="6" <?php echo (isset($user_residency['father_voting_duration']) && $user_residency['father_voting_duration'] === '6') ? 'selected' : ''; ?>>6</option>
                                    <option value="7" <?php echo (isset($user_residency['father_voting_duration']) && $user_residency['father_voting_duration'] === '7') ? 'selected' : ''; ?>>7</option>
                                    <option value="8" <?php echo (isset($user_residency['father_voting_duration']) && $user_residency['father_voting_duration'] === '8') ? 'selected' : ''; ?>>8</option>
                                    <option value="9" <?php echo (isset($user_residency['father_voting_duration']) && $user_residency['father_voting_duration'] === '9') ? 'selected' : ''; ?>>9</option>
                                    <option value="10+" <?php echo (isset($user_residency['father_voting_duration']) && $user_residency['father_voting_duration'] === '10+') ? 'selected' : ''; ?>>10+</option>
                                </select>
                            </div>
                        </div>
                        <div class="form-group">
                            <label for="mother_voting_duration">Mother</label>
                            <div class="input-group">
                                <i class="fas fa-clock"></i>
                                <select id="mother_voting_duration" name="mother_voting_duration" class="form-control" style="padding-left: 2.5rem;" <?php echo !$is_application_open ? 'disabled' : ''; ?>>
                                    <option value="">Choose</option>
                                    <option value="1" <?php echo (isset($user_residency['mother_voting_duration']) && $user_residency['mother_voting_duration'] === '1') ? 'selected' : ''; ?>>1</option>
                                    <option value="2" <?php echo (isset($user_residency['mother_voting_duration']) && $user_residency['mother_voting_duration'] === '2') ? 'selected' : ''; ?>>2</option>
                                    <option value="3" <?php echo (isset($user_residency['mother_voting_duration']) && $user_residency['mother_voting_duration'] === '3') ? 'selected' : ''; ?>>3</option>
                                    <option value="4" <?php echo (isset($user_residency['mother_voting_duration']) && $user_residency['mother_voting_duration'] === '4') ? 'selected' : ''; ?>>4</option>
                                    <option value="5" <?php echo (isset($user_residency['mother_voting_duration']) && $user_residency['mother_voting_duration'] === '5') ? 'selected' : ''; ?>>5</option>
                                    <option value="6" <?php echo (isset($user_residency['mother_voting_duration']) && $user_residency['mother_voting_duration'] === '6') ? 'selected' : ''; ?>>6</option>
                                    <option value="7" <?php echo (isset($user_residency['mother_voting_duration']) && $user_residency['mother_voting_duration'] === '7') ? 'selected' : ''; ?>>7</option>
                                    <option value="8" <?php echo (isset($user_residency['mother_voting_duration']) && $user_residency['mother_voting_duration'] === '8') ? 'selected' : ''; ?>>8</option>
                                    <option value="9" <?php echo (isset($user_residency['mother_voting_duration']) && $user_residency['mother_voting_duration'] === '9') ? 'selected' : ''; ?>>9</option>
                                    <option value="10+" <?php echo (isset($user_residency['mother_voting_duration']) && $user_residency['mother_voting_duration'] === '10+') ? 'selected' : ''; ?>>10+</option>
                                </select>
                            </div>
                        </div>
                        <div class="form-group">
                            <label for="applicant_voting_duration">Applicant</label>
                            <div class="input-group">
                                <i class="fas fa-clock"></i>
                                <select id="applicant_voting_duration" name="applicant_voting_duration" class="form-control" style="padding-left: 2.5rem;" <?php echo !$is_application_open ? 'disabled' : ''; ?>>
                                    <option value="">Choose</option>
                                    <option value="1" <?php echo (isset($user_residency['applicant_voting_duration']) && $user_residency['applicant_voting_duration'] === '1') ? 'selected' : ''; ?>>1</option>
                                    <option value="2" <?php echo (isset($user_residency['applicant_voting_duration']) && $user_residency['applicant_voting_duration'] === '2') ? 'selected' : ''; ?>>2</option>
                                    <option value="3" <?php echo (isset($user_residency['applicant_voting_duration']) && $user_residency['applicant_voting_duration'] === '3') ? 'selected' : ''; ?>>3</option>
                                    <option value="4" <?php echo (isset($user_residency['applicant_voting_duration']) && $user_residency['applicant_voting_duration'] === '4') ? 'selected' : ''; ?>>4</option>
                                    <option value="5" <?php echo (isset($user_residency['applicant_voting_duration']) && $user_residency['applicant_voting_duration'] === '5') ? 'selected' : ''; ?>>5</option>
                                    <option value="6" <?php echo (isset($user_residency['applicant_voting_duration']) && $user_residency['applicant_voting_duration'] === '6') ? 'selected' : ''; ?>>6</option>
                                    <option value="7" <?php echo (isset($user_residency['applicant_voting_duration']) && $user_residency['applicant_voting_duration'] === '7') ? 'selected' : ''; ?>>7</option>
                                    <option value="8" <?php echo (isset($user_residency['applicant_voting_duration']) && $user_residency['applicant_voting_duration'] === '8') ? 'selected' : ''; ?>>8</option>
                                    <option value="9" <?php echo (isset($user_residency['applicant_voting_duration']) && $user_residency['applicant_voting_duration'] === '9') ? 'selected' : ''; ?>>9</option>
                                    <option value="10+" <?php echo (isset($user_residency['applicant_voting_duration']) && $user_residency['applicant_voting_duration'] === '10+') ? 'selected' : ''; ?>>10+</option>
                                </select>
                            </div>
                        </div>
                    </div>
                    <div class="form-row">
                        <div class="form-group">
                            <label for="guardian-name">Please Indicate the Name of Guardian <span class="required">*</span></label>
                            <div class="input-group">
                                <i class="fas fa-user"></i>
                                <input type="text" id="guardian-name" name="guardian_name" class="form-control" value="<?php echo htmlspecialchars($user_residency['guardian_name'] ?? ''); ?>" required <?php echo !$is_application_open ? 'disabled' : ''; ?>>
                            </div>
                        </div>
                    </div>
                    <div class="form-row">
                        <div class="form-group">
                            <label for="relationship">Relationship to the Guardian <span class="required">*</span></label>
                            <div class="input-group">
                                <i class="fas fa-users"></i>
                                <input type="text" id="relationship" name="relationship" class="form-control" value="<?php echo htmlspecialchars($user_residency['relationship'] ?? ''); ?>" required <?php echo !$is_application_open ? 'disabled' : ''; ?>>
                            </div>
                        </div>
                    </div>
                    <div class="form-row">
                        <div class="form-group full-width">
                            <label for="guardian-address">Address of Your Guardian <span class="required">*</span></label>
                            <div class="input-group">
                                <i class="fas fa-map-marker-alt"></i>
                                <input type="text" id="guardian-address" name="guardian_address" class="form-control" value="<?php echo htmlspecialchars($user_residency['guardian_address'] ?? ''); ?>" required <?php echo !$is_application_open ? 'disabled' : ''; ?>>
                            </div>
                        </div>
                    </div>
                    <div class="form-row">
                        <div class="form-group">
                            <label for="guardian-contact">Contact Number of Your Guardian <span class="required">*</span></label>
                            <div class="input-group">
                                <i class="fas fa-phone"></i>
                                <input type="tel" id="guardian-contact" name="guardian_contact" class="form-control" value="<?php echo htmlspecialchars($user_residency['guardian_contact'] ?? ''); ?>" required <?php echo !$is_application_open ? 'disabled' : ''; ?>>
                            </div>
                        </div>
                    </div>
                    <div class="form-buttons">
                        <button type="button" class="prev-btn" onclick="prevStep(2)">Previous</button>
                        <button type="button" class="next-btn" onclick="nextStep(2)">Next</button>
                    </div>
                </div>

                <div class="form-section" id="step3" style="display: none;">
                    <h3>Family Background</h3>
                    <div class="family-background">
                        <!-- Father's Information -->
                        <div class="family-member">
                            <h4>Father</h4>
                            <div class="form-group">
                                <label for="father-name">Name <span class="required">*</span></label>
                                <div class="input-group">
                                    <i class="fas fa-user"></i>
                                    <input type="text" id="father-name" name="father_name" class="form-control" value="<?php echo htmlspecialchars($user_fam['father_name'] ?? ''); ?>" required <?php echo !$is_application_open ? 'disabled' : ''; ?>>
                                </div>
                            </div>
                            <div class="form-group">
                                <label for="father-address">Home Address <span class="required">*</span></label>
                                <div class="input-group">
                                    <i class="fas fa-map-marker-alt"></i>
                                    <input type="text" id="father-address" name="father_address" class="form-control" value="<?php echo htmlspecialchars($user_fam['father_address'] ?? ''); ?>" required <?php echo !$is_application_open ? 'disabled' : ''; ?>>
                                </div>
                            </div>
                            <div class="form-group">
                                <label for="father-contact">Contact No. <span class="required">*</span></label>
                                <div class="input-group">
                                    <i class="fas fa-phone"></i>
                                    <input type="tel" id="father-contact" name="father_contact" class="form-control" value="<?php echo htmlspecialchars($user_fam['father_contact'] ?? ''); ?>" required <?php echo !$is_application_open ? 'disabled' : ''; ?>>
                                </div>
                            </div>
                            <div class="form-group">
                                <label for="father-occupation">Present Occupation <span class="required">*</span></label>
                                <div class="input-group">
                                    <i class="fas fa-briefcase"></i>
                                    <input type="text" id="father-occupation" name="father_occupation" class="form-control" value="<?php echo htmlspecialchars($user_fam['father_occupation'] ?? ''); ?>" required <?php echo !$is_application_open ? 'disabled' : ''; ?>>
                                </div>
                            </div>
                            <div class="form-group">
                                <label for="father-office-address">Office Address (optional)</label>
                                <div class="input-group">
                                    <i class="fas fa-building"></i>
                                    <input type="text" id="father-office-address" name="father_office_address" class="form-control" value="<?php echo htmlspecialchars($user_fam['father_office_address'] ?? ''); ?>" <?php echo !$is_application_open ? 'disabled' : ''; ?>>
                                    </div>
                            </div>
                            <div class="form-group">
                                <label for="father-tel-no">Tel. No. (optional)</label>
                                <div class="input-group">
                                    <i class="fas fa-phone"></i>
                                    <input type="tel" id="father-tel-no" name="father_tel_no" class="form-control" value="<?php echo htmlspecialchars($user_fam['father_tel_no'] ?? ''); ?>" <?php echo !$is_application_open ? 'disabled' : ''; ?>>
                                </div>
                            </div>
                            <div class="form-group">
                                <label for="father-age">Age <span class="required">*</span></label>
                                <div class="input-group">
                                    <i class="fas fa-sort-numeric-up"></i>
                                    <input type="number" id="father-age" name="father_age" class="form-control" value="<?php echo htmlspecialchars($user_fam['father_age'] ?? ''); ?>" required <?php echo !$is_application_open ? 'disabled' : ''; ?>>
                                </div>
                            </div>
                            <div class="form-group">
                                <label for="father-dob">Date of Birth <span class="required">*</span></label>
                                <div class="input-group">
                                    <i class="fas fa-calendar-alt"></i>
                                    <input type="date" id="father-dob" name="father_dob" class="form-control" value="<?php echo htmlspecialchars($user_fam['father_dob'] ?? ''); ?>" required <?php echo !$is_application_open ? 'disabled' : ''; ?>>
                                </div>
                            </div>
                            <div class="form-group">
                                <label for="father-citizenship">Citizenship <span class="required">*</span></label>
                                <div class="input-group">
                                    <i class="fas fa-flag"></i>
                                    <input type="text" id="father-citizenship" name="father_citizenship" class="form-control" value="<?php echo htmlspecialchars($user_fam['father_citizenship'] ?? ''); ?>" required <?php echo !$is_application_open ? 'disabled' : ''; ?>>
                                </div>
                            </div>
                            <div class="form-group">
                                <label for="father-religion">Religion <span class="required">*</span></label>
                                <div class="input-group">
                                    <i class="fas fa-pray"></i>
                                    <input type="text" id="father-religion" name="father_religion" class="form-control" value="<?php echo htmlspecialchars($user_fam['father_religion'] ?? ''); ?>" required <?php echo !$is_application_open ? 'disabled' : ''; ?>>
                                </div>
                            </div>
                        </div>

                        <!-- Mother's Information -->
                        <div class="family-member">
                            <h4>Mother</h4>
                            <div class="form-group">
                                <label for="mother-name">Name <span class="required">*</span></label>
                                <div class="input-group">
                                    <i class="fas fa-user"></i>
                                    <input type="text" id="mother-name" name="mother_name" class="form-control" value="<?php echo htmlspecialchars($user_fam['mother_name'] ?? ''); ?>" required <?php echo !$is_application_open ? 'disabled' : ''; ?>>
                                </div>
                            </div>
                            <div class="form-group">
                                <label for="mother-address">Home Address <span class="required">*</span></label>
                                <div class="input-group">
                                    <i class="fas fa-map-marker-alt"></i>
                                    <input type="text" id="mother-address" name="mother_address" class="form-control" value="<?php echo htmlspecialchars($user_fam['mother_address'] ?? ''); ?>" required <?php echo !$is_application_open ? 'disabled' : ''; ?>>
                                </div>
                            </div>
                            <div class="form-group">
                                <label for="mother-contact">Contact No. <span class="required">*</span></label>
                                <div class="input-group">
                                    <i class="fas fa-phone"></i>
                                    <input type="tel" id="mother-contact" name="mother_contact" class="form-control" value="<?php echo htmlspecialchars($user_fam['mother_contact'] ?? ''); ?>" required <?php echo !$is_application_open ? 'disabled' : ''; ?>>
                                </div>
                            </div>
                            <div class="form-group">
                                <label for="mother-occupation">Present Occupation <span class="required">*</span></label>
                                <div class="input-group">
                                    <i class="fas fa-briefcase"></i>
                                    <input type="text" id="mother-occupation" name="mother_occupation" class="form-control" value="<?php echo htmlspecialchars($user_fam['mother_occupation'] ?? ''); ?>" required <?php echo !$is_application_open ? 'disabled' : ''; ?>>
                                </div>
                            </div>
                            <div class="form-group">
                                <label for="mother-office-address">Office Address (optional)</label>
                                <div class="input-group">
                                    <i class="fas fa-building"></i>
                                    <input type="text" id="mother-office-address" name="mother_office_address" class="form-control" value="<?php echo htmlspecialchars($user_fam['mother_office_address'] ?? ''); ?>" <?php echo !$is_application_open ? 'disabled' : ''; ?>>
                                </div>
                            </div>
                            <div class="form-group">
                                <label for="mother-tel-no">Tel. No. (optional)</label>
                                <div class="input-group">
                                    <i class="fas fa-phone"></i>
                                    <input type="tel" id="mother-tel-no" name="mother_tel_no" class="form-control" value="<?php echo htmlspecialchars($user_fam['mother_tel_no'] ?? ''); ?>" <?php echo !$is_application_open ? 'disabled' : ''; ?>>
                                </div>
                            </div>
                            <div class="form-group">
                                <label for="mother-age">Age <span class="required">*</span></label>
                                <div class="input-group">
                                    <i class="fas fa-sort-numeric-up"></i>
                                    <input type="number" id="mother-age" name="mother_age" class="form-control" value="<?php echo htmlspecialchars($user_fam['mother_age'] ?? ''); ?>" required <?php echo !$is_application_open ? 'disabled' : ''; ?>>
                                </div>
                            </div>
                            <div class="form-group">
                                <label for="mother-dob">Date of Birth <span class="required">*</span></label>
                                <div class="input-group">
                                    <i class="fas fa-calendar-alt"></i>
                                    <input type="date" id="mother-dob" name="mother_dob" class="form-control" value="<?php echo htmlspecialchars($user_fam['mother_dob'] ?? ''); ?>" required <?php echo !$is_application_open ? 'disabled' : ''; ?>>
                                </div>
                            </div>
                            <div class="form-group">
                                <label for="mother-citizenship">Citizenship <span class="required">*</span></label>
                                <div class="input-group">
                                    <i class="fas fa-flag"></i>
                                    <input type="text" id="mother-citizenship" name="mother_citizenship" class="form-control" value="<?php echo htmlspecialchars($user_fam['mother_citizenship'] ?? ''); ?>" required <?php echo !$is_application_open ? 'disabled' : ''; ?>>
                                </div>
                            </div>
                            <div class="form-group">
                                <label for="mother-religion">Religion <span class="required">*</span></label>
                                <div class="input-group">
                                    <i class="fas fa-pray"></i>
                                    <input type="text" id="mother-religion" name="mother_religion" class="form-control" value="<?php echo htmlspecialchars($user_fam['mother_religion'] ?? ''); ?>" required <?php echo !$is_application_open ? 'disabled' : ''; ?>>
                                </div>
                            </div>
                        </div>
                    </div>
                    <div class="form-buttons">
                        <button type="button" class="prev-btn" onclick="prevStep(3)">Previous</button>
                        <button type="button" class="next-btn" onclick="nextStep(3)">Next</button>
                    </div>
                </div>

                <!-- Step 4: Documents -->
                <div class="form-section" id="step4" style="display: none;">
                    <h3>Documents</h3>
                    <div class="form-row">
                        <div class="form-group">
                            <label for="cor-file">Certificate of Registration (COR) <span class="required">*</span></label>
                            <div class="file-upload-group">
                                <input type="file" id="cor-file" name="cor_file" accept=".pdf,.png,.jpg,.jpeg" <?php echo !$is_application_open ? 'disabled' : ''; ?>>
                                <label for="cor-file" class="file-upload-label" <?php echo !$is_application_open ? 'disabled' : ''; ?>>
                                    <i class="fas fa-upload"></i> Upload File
                                </label>
                                <span class="file-name"><?php echo isset($user_docs['cor_file_path']) ? basename($user_docs['cor_file_path']) : 'No file selected'; ?></span>
                            </div>
                            <div class="file-error" id="cor-file-error"></div>
                        </div>
                    </div>
                    <div class="form-row">
                        <div class="form-group">
                            <label for="indigency-file">Certificate of Indigency <span class="required">*</span></label>
                            <div class="file-upload-group">
                                <input type="file" id="indigency-file" name="indigency_file" accept=".pdf,.png,.jpg,.jpeg" <?php echo !$is_application_open ? 'disabled' : ''; ?>>
                                <label for="indigency-file" class="file-upload-label" <?php echo !$is_application_open ? 'disabled' : ''; ?>>
                                    <i class="fas fa-upload"></i> Upload File
                                </label>
                                <span class="file-name"><?php echo isset($user_docs['indigency_file_path']) ? basename($user_docs['indigency_file_path']) : 'No file selected'; ?></span>
                            </div>
                            <div class="file-error" id="indigency-file-error"></div>
                        </div>
                    </div>
                    <div class="form-row">
                        <div class="form-group">
                            <label for="voter-file">Voter's Certificate <span class="required">*</span></label>
                            <div class="file-upload-group">
                                <input type="file" id="voter-file" name="voter_file" accept=".pdf,.png,.jpg,.jpeg" <?php echo !$is_application_open ? 'disabled' : ''; ?>>
                                <label for="voter-file" class="file-upload-label" <?php echo !$is_application_open ? 'disabled' : ''; ?>>
                                    <i class="fas fa-upload"></i> Upload File
                                </label>
                                <span class="file-name"><?php echo isset($user_docs['voter_file_path']) ? basename($user_docs['voter_file_path']) : 'No file selected'; ?></span>
                            </div>
                            <div class="file-error" id="voter-file-error"></div>
                        </div>
                    </div>
                    <div class="form-buttons">
                        <button type="button" class="prev-btn" onclick="prevStep(4)">Previous</button>
                        <button type="button" class="next-btn" onclick="nextStep(4)">Next</button>
                    </div>
                </div>

                <!-- Step 5: Review Your Application -->
                <div class="form-section" id="step5" style="display: none;">
                    <h3>Review Your Application</h3>
                    <div class="form-section">
                        <h4>Personal Information</h4>
                        <p><strong>Lastname:</strong> <span id="review-lastname"><?php echo htmlspecialchars($user_info['lastname'] ?? $lastname); ?></span></p>
                        <p><strong>Firstname:</strong> <span id="review-firstname"><?php echo htmlspecialchars($user_info['firstname'] ?? $firstname); ?></span></p>
                        <p><strong>Middlename:</strong> <span id="review-middlename"><?php echo htmlspecialchars($user_info['middlename'] ?? $middlename); ?></span></p>
                        <p><strong>Sex:</strong> <span id="review-sex"><?php echo htmlspecialchars($user_info['sex'] ?? 'Not specified'); ?></span></p>
                        <p><strong>Civil Status:</strong> <span id="review-civil-status"><?php echo htmlspecialchars($user_info['civil_status'] ?? 'Not specified'); ?></span></p>
                        <p><strong>Birthdate:</strong> <span id="review-birthdate"><?php echo htmlspecialchars($user_info['birthdate'] ?? 'Not specified'); ?></span></p>
                        <p><strong>Degree:</strong> <span id="review-degree"><?php echo htmlspecialchars($user_personal['degree'] ?? 'Not specified'); ?></span></p>
                        <p><strong>Course:</strong> <span id="review-course"><?php echo htmlspecialchars($user_personal['course'] ?? 'Not specified'); ?></span></p>
                        <p><strong>Current College/University:</strong> <span id="review-current-college"><?php echo htmlspecialchars($user_personal['current_college'] ?? 'Not specified'); ?></span></p>
                    </div>

                    <div class="form-section">
                        <h4>Residency</h4>
                        <p><strong>Permanent Address:</strong> <span id="review-permanent-address"><?php echo htmlspecialchars($user_residency['permanent_address'] ?? 'Not specified'); ?></span></p>
                        <p><strong>Residency Duration:</strong> <span id="review-residency-duration"><?php echo htmlspecialchars($user_residency['residency_duration'] ?? 'Not specified'); ?></span></p>
                        <p><strong>Registered Voter:</strong> <span id="review-registered-voter"><?php echo htmlspecialchars($user_residency['registered_voter'] ?? 'Not specified'); ?></span></p>
                        <p><strong>Father Voting Duration:</strong> <span id="review-father-voting-duration"><?php echo htmlspecialchars($user_residency['father_voting_duration'] ?? 'Not specified'); ?></span></p>
                        <p><strong>Mother Voting Duration:</strong> <span id="review-mother-voting-duration"><?php echo htmlspecialchars($user_residency['mother_voting_duration'] ?? 'Not specified'); ?></span></p>
                        <p><strong>Applicant Voting Duration:</strong> <span id="review-applicant-voting-duration"><?php echo htmlspecialchars($user_residency['applicant_voting_duration'] ?? 'Not specified'); ?></span></p>
                        <p><strong>Guardian Name:</strong> <span id="review-guardian-name"><?php echo htmlspecialchars($userres['guardian_name'] ?? 'Not specified'); ?></span></p>
                        <p><strong>Relationship to Guardian:</strong> <span id="review-relationship"><?php echo htmlspecialchars($user_residency['relationship'] ?? 'Not specified'); ?></span></p>
                        <p><strong>Guardian Address:</strong> <span id="review-guardian-address"><?php echo htmlspecialchars($user_residency['guardian_address'] ?? 'Not specified'); ?></span></p>
                        <p><strong>Guardian Contact:</strong> <span id="review-guardian-contact"><?php echo htmlspecialchars($user_residency['guardian_contact'] ?? 'Not specified'); ?></span></p>
                    </div>

                    <div class="form-section">
                        <h4>Family Background</h4>
                        <h5>Father</h5>
                        <p><strong>Name:</strong> <span id="review-father-name"><?php echo htmlspecialchars($user_fam['father_name'] ?? 'Not specified'); ?></span></p>
                        <p><strong>Home Address:</strong> <span id="review-father-address"><?php echo htmlspecialchars($user_fam['father_address'] ?? 'Not specified'); ?></span></p>
                        <p><strong>Contact No.:</strong> <span id="review-father-contact"><?php echo htmlspecialchars($user_fam['father_contact'] ?? 'Not specified'); ?></span></p>
                        <p><strong>Present Occupation:</strong> <span id="review-father-occupation"><?php echo htmlspecialchars($user_fam['father_occupation'] ?? 'Not specified'); ?></span></p>
                        <p><strong>Office Address:</strong> <span id="review-father-office-address"><?php echo htmlspecialchars($user_fam['father_office_address'] ?? 'Not specified'); ?></span></p>
                        <p><strong>Tel. No.:</strong> <span id="review-father-tel-no"><?php echo htmlspecialchars($user_fam['father_tel_no'] ?? 'Not specified'); ?></span></p>
                        <p><strong>Age:</strong> <span id="review-father-age"><?php echo htmlspecialchars($user_fam['father_age'] ?? 'Not specified'); ?></span></p>
                        <p><strong>Date of Birth:</strong> <span id="review-father-dob"><?php echo htmlspecialchars($user_fam['father_dob'] ?? 'Not specified'); ?></span></p>
                        <p><strong>Citizenship:</strong> <span id="review-father-citizenship"><?php echo htmlspecialchars($user_fam['father_citizenship'] ?? 'Not specified'); ?></span></p>
                        <p><strong>Religion:</strong> <span id="review-father-religion"><?php echo htmlspecialchars($user_fam['father_religion'] ?? 'Not specified'); ?></span></p>

                        <h5>Mother</h5>
                        <p><strong>Name:</strong> <span id="review-mother-name"><?php echo htmlspecialchars($user_fam['mother_name'] ?? 'Not specified'); ?></span></p>
                        <p><strong>Home Address:</strong> <span id="review-mother-address"><?php echo htmlspecialchars($user_fam['mother_address'] ?? 'Not specified'); ?></span></p>
                        <p><strong>Contact No.:</strong> <span id="review-mother-contact"><?php echo htmlspecialchars($user_fam['mother_contact'] ?? 'Not specified'); ?></span></p>
                        <p><strong>Present Occupation:</strong> <span id="review-mother-occupation"><?php echo htmlspecialchars($user_fam['mother_occupation'] ?? 'Not specified'); ?></span></p>
                        <p><strong>Office Address:</strong> <span id="review-mother-office-address"><?php echo htmlspecialchars($user_fam['mother_office_address'] ?? 'Not specified'); ?></span></p>
                        <p><strong>Tel. No.:</strong> <span id="review-mother-tel-no"><?php echo htmlspecialchars($user_fam['mother_tel_no'] ?? 'Not specified'); ?></span></p>
                        <p><strong>Age:</strong> <span id="review-mother-age"><?php echo htmlspecialchars($user_fam['mother_age'] ?? 'Not specified'); ?></span></p>
                        <p><strong>Date of Birth:</strong> <span id="review-mother-dob"><?php echo htmlspecialchars($user_fam['mother_dob'] ?? 'Not specified'); ?></span></p>
                        <p><strong>Citizenship:</strong> <span id="review-mother-citizenship"><?php echo htmlspecialchars($user_fam['mother_citizenship'] ?? 'Not specified'); ?></span></p>
                        <p><strong>Religion:</strong> <span id="review-mother-religion"><?php echo htmlspecialchars($user_fam['mother_religion'] ?? 'Not specified'); ?></span></p>
                    </div>

                    <div class="form-section">
                        <h4>Documents</h4>
                        <p><strong>Certificate of Registration (COR):</strong> <span id="review-cor-file"><?php echo isset($user_docs['cor_file_path']) ? basename($user_docs['cor_file_path']) : 'Not uploaded'; ?></span></p>
                        <p><strong>Certificate of Indigency:</strong> <span id="review-indigency-file"><?php echo isset($user_docs['indigency_file_path']) ? basename($user_docs['indigency_file_path']) : 'Not uploaded'; ?></span></p>
                        <p><strong>Voter's Certificate:</strong> <span id="review-voter-file"><?php echo isset($user_docs['voter_file_path']) ? basename($user_docs['voter_file_path']) : 'Not uploaded'; ?></span></p>
                    </div>

                    <div class="form-buttons">
                        <button type="button" class="prev-btn" onclick="prevStep(5)">Previous</button>
                        <button type="submit" name="submit_application" class="submit-btn" <?php echo !$is_application_open ? 'disabled' : ''; ?>>Submit Application</button>
                    </div>
                </div>
            </form>
        </div>
    </div>

    <script>
        // Navigation between dashboard and application form
        function showDashboard() {
            document.getElementById('dashboardContent').style.display = 'block';
            document.getElementById('applicationForm').style.display = 'none';
            document.getElementById('dashboardLink').classList.add('active');
            document.getElementById('applyLink').classList.remove('active');
        }

        function showApplicationForm() {
            document.getElementById('dashboardContent').style.display = 'none';
            document.getElementById('applicationForm').style.display = 'block';
            document.getElementById('dashboardLink').classList.remove('active');
            document.getElementById('applyLink').classList.add('active');
        }

        // Form step navigation
        let currentStep = 1;
        const totalSteps = 5;

        function updateProgressBar(step) {
            const steps = document.querySelectorAll('.progress-step');
            steps.forEach((stepElement, index) => {
                if (index + 1 <= step) {
                    stepElement.classList.add('active');
                } else {
                    stepElement.classList.remove('active');
                }
            });
        }

        function nextStep(step) {
            if (step < totalSteps) {
                // Validate current step before proceeding
                if (!validateStep(step)) {
                    return;
                }

                document.getElementById(`step${step}`).style.display = 'none';
                currentStep = step + 1;
                document.getElementById(`step${currentStep}`).style.display = 'block';
                updateProgressBar(currentStep);

                // Update review section dynamically
                if (currentStep === 5) {
                    updateReviewSection();
                }
            }
        }

        function prevStep(step) {
            if (step > 1) {
                document.getElementById(`step${step}`).style.display = 'none';
                currentStep = step - 1;
                document.getElementById(`step${currentStep}`).style.display = 'block';
                updateProgressBar(currentStep);
            }
        }

        // Basic validation for each step
        function validateStep(step) {
            let isValid = true;
            let errorMessage = '';

            if (step === 1) {
                const lastname = document.getElementById('lastname').value.trim();
                const firstname = document.getElementById('firstname').value.trim();
                const middlename = document.getElementById('middlename').value.trim();
                const sex = document.getElementById('Sex').value;
                const civilStatus = document.getElementById('Civil-Status').value;
                const birthdate = document.getElementById('birthdate').value;
                const degree = document.getElementById('track').value;
                const course = document.getElementById('Course').value;
                const currentCollege = document.getElementById('secondary-school').value.trim();

                if (!lastname || !firstname || !middlename || !sex || !civilStatus || !birthdate || !degree || !course || !currentCollege) {
                    isValid = false;
                    errorMessage = 'Please fill out all required fields in Personal Information.';
                }
            } else if (step === 2) {
                const permanentAddress = document.getElementById('permanent-address').value.trim();
                const residencyDuration = document.getElementById('residency-duration').value.trim();
                const registeredVoter = document.querySelector('input[name="registered_voter"]:checked');
                const guardianName = document.getElementById('guardian-name').value.trim();
                const relationship = document.getElementById('relationship').value.trim();
                const guardianAddress = document.getElementById('guardian-address').value.trim();
                const guardianContact = document.getElementById('guardian-contact').value.trim();

                if (!permanentAddress || !residencyDuration || !registeredVoter || !guardianName || !relationship || !guardianAddress || !guardianContact) {
                    isValid = false;
                    errorMessage = 'Please fill out all required fields in Residency.';
                }
            } else if (step === 3) {
                const fatherName = document.getElementById('father-name').value.trim();
                const fatherAddress = document.getElementById('father-address').value.trim();
                const fatherContact = document.getElementById('father-contact').value.trim();
                const fatherOccupation = document.getElementById('father-occupation').value.trim();
                const fatherAge = document.getElementById('father-age').value.trim();
                const fatherDob = document.getElementById('father-dob').value;
                const fatherCitizenship = document.getElementById('father-citizenship').value.trim();
                const fatherReligion = document.getElementById('father-religion').value.trim();

                const motherName = document.getElementById('mother-name').value.trim();
                const motherAddress = document.getElementById('mother-address').value.trim();
                const motherContact = document.getElementById('mother-contact').value.trim();
                const motherOccupation = document.getElementById('mother-occupation').value.trim();
                const motherAge = document.getElementById('mother-age').value.trim();
                const motherDob = document.getElementById('mother-dob').value;
                const motherCitizenship = document.getElementById('mother-citizenship').value.trim();
                const motherReligion = document.getElementById('mother-religion').value.trim();

                if (!fatherName || !fatherAddress || !fatherContact || !fatherOccupation || !fatherAge || !fatherDob || !fatherCitizenship || !fatherReligion ||
                    !motherName || !motherAddress || !motherContact || !motherOccupation || !motherAge || !motherDob || !motherCitizenship || !motherReligion) {
                    isValid = false;
                    errorMessage = 'Please fill out all required fields in Family Background.';
                }
            } else if (step === 4) {
                const corFile = document.getElementById('cor-file').files.length;
                const indigencyFile = document.getElementById('indigency-file').files.length;
                const voterFile = document.getElementById('voter-file').files.length;

                // Check if files are uploaded or already exist
                const corExists = <?php echo isset($user_docs['cor_file_path']) ? 'true' : 'false'; ?>;
                const indigencyExists = <?php echo isset($user_docs['indigency_file_path']) ? 'true' : 'false'; ?>;
                const voterExists = <?php echo isset($user_docs['voter_file_path']) ? 'true' : 'false'; ?>;

                if (!corFile && !corExists) {
                    isValid = false;
                    document.getElementById('cor-file-error').textContent = 'Please upload your Certificate of Registration.';
                }
                if (!indigencyFile && !indigencyExists) {
                    isValid = false;
                    document.getElementById('indigency-file-error').textContent = 'Please upload your Certificate of Indigency.';
                }
                if (!voterFile && !voterExists) {
                    isValid = false;
                    document.getElementById('voter-file-error').textContent = 'Please upload your Voter\'s Certificate.';
                }
            }

            if (!isValid) {
                alert(errorMessage);
            }

            return isValid;
        }

        // Update review section dynamically
        function updateReviewSection() {
            // Personal Information
            document.getElementById('review-lastname').textContent = document.getElementById('lastname').value || 'Not specified';
            document.getElementById('review-firstname').textContent = document.getElementById('firstname').value || 'Not specified';
            document.getElementById('review-middlename').textContent = document.getElementById('middlename').value || 'Not specified';
            document.getElementById('review-sex').textContent = document.getElementById('Sex').value || 'Not specified';
            document.getElementById('review-civil-status').textContent = document.getElementById('Civil-Status').value || 'Not specified';
            document.getElementById('review-birthdate').textContent = document.getElementById('birthdate').value || 'Not specified';
            document.getElementById('review-degree').textContent = document.getElementById('track').value || 'Not specified';
            document.getElementById('review-course').textContent = document.getElementById('Course').value || 'Not specified';
            document.getElementById('review-current-college').textContent = document.getElementById('secondary-school').value || 'Not specified';

            // Residency
            document.getElementById('review-permanent-address').textContent = document.getElementById('permanent-address').value || 'Not specified';
            document.getElementById('review-residency-duration').textContent = document.getElementById('residency-duration').value || 'Not specified';
            const registeredVoter = document.querySelector('input[name="registered_voter"]:checked');
            document.getElementById('review-registered-voter').textContent = registeredVoter ? registeredVoter.value : 'Not specified';
            document.getElementById('review-father-voting-duration').textContent = document.getElementById('father_voting_duration').value || 'Not specified';
            document.getElementById('review-mother-voting-duration').textContent = document.getElementById('mother_voting_duration').value || 'Not specified';
            document.getElementById('review-applicant-voting-duration').textContent = document.getElementById('applicant_voting_duration').value || 'Not specified';
            document.getElementById('review-guardian-name').textContent = document.getElementById('guardian-name').value || 'Not specified';
            document.getElementById('review-relationship').textContent = document.getElementById('relationship').value || 'Not specified';
            document.getElementById('review-guardian-address').textContent = document.getElementById('guardian-address').value || 'Not specified';
            document.getElementById('review-guardian-contact').textContent = document.getElementById('guardian-contact').value || 'Not specified';

            // Family Background
            document.getElementById('review-father-name').textContent = document.getElementById('father-name').value || 'Not specified';
            document.getElementById('review-father-address').textContent = document.getElementById('father-address').value || 'Not specified';
            document.getElementById('review-father-contact').textContent = document.getElementById('father-contact').value || 'Not specified';
            document.getElementById('review-father-occupation').textContent = document.getElementById('father-occupation').value || 'Not specified';
            document.getElementById('review-father-office-address').textContent = document.getElementById('father-office-address').value || 'Not specified';
            document.getElementById('review-father-tel-no').textContent = document.getElementById('father-tel-no').value || 'Not specified';
            document.getElementById('review-father-age').textContent = document.getElementById('father-age').value || 'Not specified';
            document.getElementById('review-father-dob').textContent = document.getElementById('father-dob').value || 'Not specified';
            document.getElementById('review-father-citizenship').textContent = document.getElementById('father-citizenship').value || 'Not specified';
            document.getElementById('review-father-religion').textContent = document.getElementById('father-religion').value || 'Not specified';

            document.getElementById('review-mother-name').textContent = document.getElementById('mother-name').value || 'Not specified';
            document.getElementById('review-mother-address').textContent = document.getElementById('mother-address').value || 'Not specified';
            document.getElementById('review-mother-contact').textContent = document.getElementById('mother-contact').value || 'Not specified';
            document.getElementById('review-mother-occupation').textContent = document.getElementById('mother-occupation').value || 'Not specified';
            document.getElementById('review-mother-office-address').textContent = document.getElementById('mother-office-address').value || 'Not specified';
            document.getElementById('review-mother-tel-no').textContent = document.getElementById('mother-tel-no').value || 'Not specified';
            document.getElementById('review-mother-age').textContent = document.getElementById('mother-age').value || 'Not specified';
            document.getElementById('review-mother-dob').textContent = document.getElementById('mother-dob').value || 'Not specified';
            document.getElementById('review-mother-citizenship').textContent = document.getElementById('mother-citizenship').value || 'Not specified';
            document.getElementById('review-mother-religion').textContent = document.getElementById('mother-religion').value || 'Not specified';

            // Documents
            const corFile = document.getElementById('cor-file').files[0];
            document.getElementById('review-cor-file').textContent = corFile ? corFile.name : '<?php echo isset($user_docs['cor_file_path']) ? basename($user_docs['cor_file_path']) : 'Not uploaded'; ?>';
            const indigencyFile = document.getElementById('indigency-file').files[0];
            document.getElementById('review-indigency-file').textContent = indigencyFile ? indigencyFile.name : '<?php echo isset($user_docs['indigency_file_path']) ? basename($user_docs['indigency_file_path']) : 'Not uploaded'; ?>';
            const voterFile = document.getElementById('voter-file').files[0];
            document.getElementById('review-voter-file').textContent = voterFile ? voterFile.name : '<?php echo isset($user_docs['voter_file_path']) ? basename($user_docs['voter_file_path']) : 'Not uploaded'; ?>';
        }

        // Update file name display on file input change
        document.querySelectorAll('input[type="file"]').forEach(input => {
            input.addEventListener('change', function() {
                const fileNameSpan = this.parentElement.querySelector('.file-name');
                const fileError = this.parentElement.nextElementSibling;
                fileError.textContent = ''; 

                if (this.files.length > 0) {
                    const file = this.files[0];
                    const allowedTypes = ['application/pdf', 'image/png', 'image/jpeg', 'image/jpg'];
                    const maxSize = 5 * 1024 * 1024; 

                    if (!allowedTypes.includes(file.type)) {
                        fileError.textContent = 'Invalid file type. Only PDF, PNG, JPEG allowed.';
                        this.value = '';
                        fileNameSpan.textContent = 'No file selected';
                        return;
                    }

                    if (file.size > maxSize) {
                        fileError.textContent = 'File is too large. Max size is 5MB.';
                        this.value = '';
                        fileNameSpan.textContent = 'No file selected';
                        return;
                    }

                    fileNameSpan.textContent = file.name;
                } else {
                    fileNameSpan.textContent = 'No file selected';
                }
            });
        });
    
        document.querySelector('.profile-pic').addEventListener('click', function() {
            if (<?php echo $is_application_open ? 'true' : 'false'; ?>) {
                document.getElementById('profile-picture').click();
            }
        });

        document.addEventListener('DOMContentLoaded', function() {
            updateProgressBar(currentStep);
            <?php if ($has_application): ?>
                showApplicationForm();
            <?php endif; ?>
        });
    </script>
</body>
</html>