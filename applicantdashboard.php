<?php
require './route_guard.php';

if (isset($_SESSION['user_id']) && isset($_SESSION['token'])) {
    $stmt = $pdo->prepare("SELECT expires_at FROM tokens WHERE user_id = ? AND token = ?");
    $stmt->execute([$_SESSION['user_id'], $_SESSION['token']]);
    $token_data = $stmt->fetch(PDO::FETCH_ASSOC);

    if ($token_data) {
        $expires_at = strtotime($token_data['expires_at']);
        $current_time = time();

        if ($current_time > $expires_at) {
            $stmt = $pdo->prepare("DELETE FROM tokens WHERE user_id = ? AND token = ?");
            $stmt->execute([$_SESSION['user_id'], $_SESSION['token']]);
            
            $_SESSION = array();
            session_destroy();
            header('Location: login.php?message=Session expired. Please log in again.');
            exit;
        }
    } else {
        $_SESSION = array();
        session_destroy();
        header('Location: login.php?message=Invalid session. Please log in again.');
        exit;
    }
}

// Handle logout
if (isset($_GET['action']) && $_GET['action'] === 'logout') {
    $stmt = $pdo->prepare("DELETE FROM tokens WHERE user_id = ? AND token = ?");
    $stmt->execute([$_SESSION['user_id'], $_SESSION['token']]);
    
    $_SESSION = array();
    session_destroy();
    header('Location: login.php');
    exit;
}

if (!isset($_SESSION['user_id'])) {
    header('Location: login.php');
    exit;
}

if ($_SESSION['user_role'] !== 'Applicant') {
    header('Location: login.php');
    exit;
}

$lastname = isset($_SESSION['lastname']) ? $_SESSION['lastname'] : '';
$firstname = isset($_SESSION['firstname']) ? $_SESSION['firstname'] : '';
$middlename = isset($_SESSION['middlename']) ? $_SESSION['middlename'] : '';

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

$user_id = $_SESSION['user_id'];
$has_application = false;
$application_deadline = null;
$is_application_open = false;
$application_status = 'Not Yet Submitted';
$notices = [];

$users_info = [];
$user_personal = [];
$user_residency = [];
$user_fam = [];
$user_docs = [];

try {
    $stmt = $pdo->prepare("SELECT COUNT(*) FROM users_info WHERE user_id = ?");
    $stmt->execute([$user_id]);
    $count = $stmt->fetchColumn();
    $has_application = $count > 0;

    if ($has_application) {
        // Fetch user_info
        $stmt = $pdo->prepare("SELECT * FROM users_info WHERE user_id = ?");
        $stmt->execute([$user_id]);
        $users_info = $stmt->fetch(PDO::FETCH_ASSOC);

        // Fetch user_personal
        $stmt = $pdo->prepare("SELECT * FROM user_personal WHERE user_id = ?");
        $stmt->execute([$user_id]);
        $user_personal = $stmt->fetch(PDO::FETCH_ASSOC) ?: [];

        // Fetch user_residency
        $stmt = $pdo->prepare("SELECT * FROM user_residency WHERE user_id = ?");
        $stmt->execute([$user_id]);
        $user_residency = $stmt->fetch(PDO::FETCH_ASSOC) ?: [];

        // Fetch user_fam
        $stmt = $pdo->prepare("SELECT * FROM user_fam WHERE user_id = ?");
        $stmt->execute([$user_id]);
        $user_fam = $stmt->fetch(PDO::FETCH_ASSOC) ?: [];

        // Fetch user_docs
        $stmt = $pdo->prepare("SELECT * FROM user_docs WHERE user_id = ?");
        $stmt->execute([$user_id]);
        $user_docs = $stmt->fetch(PDO::FETCH_ASSOC) ?: [];

        // Set application status, explicitly checking for NULL
        $application_status = is_null($users_info['application_status']) ? 'Not Yet Submitted' : $users_info['application_status'];
    }

    $stmt = $pdo->prepare("SELECT message, created_at FROM notices WHERE user_id = ? ORDER BY created_at DESC");
    $stmt->execute([$user_id]);
    $notices = $stmt->fetchAll(PDO::FETCH_ASSOC);

    $stmt = $pdo->prepare("SELECT application_deadline FROM application_period ORDER BY updated_at DESC LIMIT 1");
    $stmt->execute();
    $result = $stmt->fetch(PDO::FETCH_ASSOC);
    if ($result) {
        $application_deadline = $result['application_deadline'];
        $current_date = date('Y-m-d');
        $is_application_open = $current_date <= $application_deadline;
    } else {
        $_SESSION['application_error'] = "No application period found. Please contact the administrator.";
        $is_application_open = false;
    }
} catch (PDOException $e) {
    $_SESSION['application_error'] = "Error checking application status: " . $e->getMessage();
}

$formatted_deadline = $application_deadline ? date('m/d/Y', strtotime($application_deadline)) : 'Not Set';

// Handle Profile Picture Upload
if ($is_application_open && $_SERVER['REQUEST_METHOD'] == 'POST' && isset($_FILES['profile_picture'])) {
    $upload_dir = './Uploads/';
    if (!is_dir($upload_dir)) {
        mkdir($upload_dir, 0777, true);
    }

    $allowed_types = ['image/png', 'image/jpeg', 'image/jpg'];
    $max_size = 5 * 1024 * 1024; // 5MB

    $file = $_FILES['profile_picture'];
    $file_name = basename($file['name']);
    $file_type = $file['type'];
    $file_size = $file['size'];
    $file_tmp = $file['tmp_name'];

    if (!in_array($file_type, $allowed_types)) {
        $_SESSION['profile_picture_error'] = "Invalid file type for profile picture. Only PNG, JPEG allowed.";
    } elseif ($file_size > $max_size) {
        $_SESSION['profile_picture_error'] = "Profile picture is too large. Max size is 5MB.";
    } else {
        $file_ext = pathinfo($file_name, PATHINFO_EXTENSION);
        $new_file_name = 'profile_picture_' . $user_id . '_' . time() . '.' . $file_ext;
        $file_path = $upload_dir . $new_file_name;

        if (move_uploaded_file($file_tmp, $file_path)) {
            try {
                $pdo->beginTransaction();

                if ($has_application && !empty($user_docs)) {
                    $stmt = $pdo->prepare("UPDATE user_docs SET profile_picture_path = ? WHERE user_id = ?");
                    $stmt->execute([$file_path, $user_id]);

                    if (!empty($user_docs['profile_picture_path']) && file_exists($user_docs['profile_picture_path'])) {
                        unlink($user_docs['profile_picture_path']);
                    }
                } else {
                    $stmt = $pdo->prepare("
                        INSERT INTO user_docs (user_id, profile_picture_path, cor_file_path, indigency_file_path, voter_file_path)
                        VALUES (?, ?, '', '', '')
                        ON DUPLICATE KEY UPDATE profile_picture_path = ?
                    ");
                    $stmt->execute([$user_id, $file_path, $file_path]);
                }

                $pdo->commit();
                $user_docs['profile_picture_path'] = $file_path;
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

// Handle Form Submission
if ($is_application_open && $_SERVER['REQUEST_METHOD'] == 'POST' && isset($_POST['submit_application'])) {
    // Personal Information
    $lastname = trim($_POST['lastname'] ?? '');
    $firstname = trim($_POST['firstname'] ?? '');
    $middlename = trim($_POST['middlename'] ?? '');
    $sex = trim($_POST['Sex'] ?? '');
    $civil_status = trim($_POST['Civil-Status'] ?? '');
    $birthdate = trim($_POST['birthdate'] ?? '');
    $municipality = trim($_POST['municipality'] ?? '');
    $barangay = trim($_POST['barangay'] ?? '');
    $nationality = trim($_POST['nationality'] ?? '');
    $place_of_birth = trim($_POST['place_of_birth'] ?? '');

    // Educational Information
    $degree = trim($_POST['track'] ?? '');
    $course = trim($_POST['Course'] ?? '');
    $current_college = trim($_POST['secondary_school'] ?? '');

    // Residency Information
    $permanent_address = trim($_POST['permanent_address'] ?? '');
    $residency_duration = trim($_POST['residency_duration'] ?? '');
    $registered_voter = trim($_POST['registered_voter'] ?? '');
    $father_voting_duration = trim($_POST['father_voting_duration'] ?? null);
    $mother_voting_duration = trim($_POST['mother_voting_duration'] ?? null);
    $applicant_voting_duration = trim($_POST['applicant_voting_duration'] ?? null);
    $guardian_name = trim($_POST['guardian_name'] ?? '');
    $relationship = trim($_POST['relationship'] ?? '');
    $guardian_address = trim($_POST['guardian_address'] ?? '');
    $guardian_contact = trim($_POST['guardian_contact'] ?? '');

    // Family Background
    $father_name = trim($_POST['father_name'] ?? '');
    $father_address = trim($_POST['father_address'] ?? '');
    $father_contact = trim($_POST['father_contact'] ?? '');
    $father_occupation = trim($_POST['father_occupation'] ?? '');
    $father_office_address = trim($_POST['father_office_address'] ?? '');
    $father_tel_no = trim($_POST['father_tel_no'] ?? '');
    $father_age = trim($_POST['father_age'] ?? '');
    $father_dob = trim($_POST['father_dob'] ?? '');
    $father_citizenship = trim($_POST['father_citizenship'] ?? '');
    $father_religion = trim($_POST['father_religion'] ?? '');

    $mother_name = trim($_POST['mother_name'] ?? '');
    $mother_address = trim($_POST['mother_address'] ?? '');
    $mother_contact = trim($_POST['mother_contact'] ?? '');
    $mother_occupation = trim($_POST['mother_occupation'] ?? '');
    $mother_office_address = trim($_POST['mother_office_address'] ?? '');
    $mother_tel_no = trim($_POST['mother_tel_no'] ?? '');
    $mother_age = trim($_POST['mother_age'] ?? '');
    $mother_dob = trim($_POST['mother_dob'] ?? '');
    $mother_citizenship = trim($_POST['mother_citizenship'] ?? '');
    $mother_religion = trim($_POST['mother_religion'] ?? '');

    // Validation
    $required_fields = [
        'lastname' => $lastname,
        'firstname' => $firstname,
        'middlename' => $middlename,
        'sex' => $sex,
        'civil_status' => $civil_status,
        'birthdate' => $birthdate,
        'municipality' => $municipality,
        'barangay' => $barangay,
        'nationality' => $nationality,
        'place_of_birth' => $place_of_birth,
        'degree' => $degree,
        'course' => $course,
        'current_college' => $current_college,
        'permanent_address' => $permanent_address,
        'residency_duration' => $residency_duration,
        'registered_voter' => $registered_voter,
        'guardian_name' => $guardian_name,
        'relationship' => $relationship,
        'guardian_address' => $guardian_address,
        'guardian_contact' => $guardian_contact,
        'father_name' => $father_name,
        'father_address' => $father_address,
        'father_contact' => $father_contact,
        'father_occupation' => $father_occupation,
        'father_age' => $father_age,
        'father_dob' => $father_dob,
        'father_citizenship' => $father_citizenship,
        'father_religion' => $father_religion,
        'mother_name' => $mother_name,
        'mother_address' => $mother_address,
        'mother_contact' => $mother_contact,
        'mother_occupation' => $mother_occupation,
        'mother_age' => $mother_age,
        'mother_dob' => $mother_dob,
        'mother_citizenship' => $mother_citizenship,
        'mother_religion' => $mother_religion
    ];

    foreach ($required_fields as $field_name => $value) {
        if (empty($value)) {
            $_SESSION['application_error'] = "Missing required field: $field_name.";
            header('Location: applicantdashboard.php?view=Application');
            exit;
        }
    }

    // File Upload Handling
    $upload_dir = './Uploads/';
    if (!is_dir($upload_dir)) {
        mkdir($upload_dir, 0777, true);
    }

    $allowed_types = ['application/pdf', 'image/png', 'image/jpeg', 'image/jpg'];
    $max_size = 5 * 1024 * 1024;

    $upload_error = '';
    $file_paths = [
        'cor_file' => $user_docs['cor_file_path'] ?? '',
        'indigency_file' => $user_docs['indigency_file_path'] ?? '',
        'voter_file' => $user_docs['voter_file_path'] ?? '',
        'profile_picture' => $user_docs['profile_picture_path'] ?? ''
    ];

    foreach (['cor_file', 'indigency_file', 'voter_file'] as $file_key) {
        if (!empty($_FILES[$file_key]['name'])) {
            $file = $_FILES[$file_key];
            $file_name = basename($file['name']);
            $file_type = $file['type'];
            $file_size = $file['size'];
            $file_tmp = $file['tmp_name'];

            if (!in_array($file_type, $allowed_types)) {
                $upload_error = "Invalid file type for $file_key. Only PDF, PNG, JPEG allowed.";
                break;
            }

            if ($file_size > $max_size) {
                $upload_error = "File $file_key is too large. Max size is 5MB.";
                break;
            }

            $file_ext = pathinfo($file_name, PATHINFO_EXTENSION);
            $new_file_name = $file_key . '_' . $user_id . '_' . time() . '.' . $file_ext;
            $file_path = $upload_dir . $new_file_name;

            if (!move_uploaded_file($file_tmp, $file_path)) {
                $upload_error = "Failed to upload $file_key.";
                break;
            }

            if (!empty($file_paths[$file_key]) && file_exists($file_paths[$file_key])) {
                unlink($file_paths[$file_key]);
            }

            $file_paths[$file_key] = $file_path;
        } elseif (!$has_application && empty($file_paths[$file_key])) {
            $upload_error = "Missing required file: $file_key.";
            break;
        }
    }

    if (empty($upload_error)) {
        try {
            $pdo->beginTransaction();

            // Update users table
            $stmt = $pdo->prepare("
                UPDATE users
                SET lastname = ?, firstname = ?, middlename = ?
                WHERE id = ?
            ");
            $stmt->execute([$lastname, $firstname, $middlename, $user_id]);

            if ($has_application) {
                // Update users_info
                $stmt = $pdo->prepare("
                    UPDATE users_info
                    SET sex = ?, civil_status = ?, birthdate = ?, municipality = ?, barangay = ?, 
                        nationality = ?, place_of_birth = ?, application_status = ?
                    WHERE user_id = ?
                ");
                $stmt->execute([
                    $sex, $civil_status, $birthdate, $municipality, $barangay, 
                    $nationality, $place_of_birth, 'Under Review', $user_id
                ]);

                // Update user_personal
                if (!empty($user_personal)) {
                    $stmt = $pdo->prepare("
                        UPDATE user_personal
                        SET degree = ?, course = ?, current_college = ?
                        WHERE user_id = ?
                    ");
                    $stmt->execute([$degree, $course, $current_college, $user_id]);
                } else {
                    $stmt = $pdo->prepare("
                        INSERT INTO user_personal (user_id, degree, course, current_college)
                        VALUES (?, ?, ?, ?)
                    ");
                    $stmt->execute([$user_id, $degree, $course, $current_college]);
                }

                // Update user_residency
                if (!empty($user_residency)) {
                    $stmt = $pdo->prepare("
                        UPDATE user_residency
                        SET permanent_address = ?, residency_duration = ?, registered_voter = ?,
                            father_voting_duration = ?, mother_voting_duration = ?, 
                            applicant_voting_duration = ?, guardian_name = ?, relationship = ?, 
                            guardian_address = ?, guardian_contact = ?
                        WHERE user_id = ?
                    ");
                    $stmt->execute([
                        $permanent_address, $residency_duration, $registered_voter,
                        $father_voting_duration, $mother_voting_duration, $applicant_voting_duration,
                        $guardian_name, $relationship, $guardian_address, $guardian_contact, $user_id
                    ]);
                } else {
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
                }

                // Update user_fam
                if (!empty($user_fam)) {
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
                } else {
                    $stmt = $pdo->prepare("
                        INSERT INTO user_fam (
                            user_id, father_name, father_address, father_contact, father_occupation,
                            father_office_address, father_tel_no, father_age, father_dob, 
                            father_citizenship, father_religion,
                            mother_name, mother_address, mother_contact, mother_occupation,
                            mother_office_address, mother_tel_no, mother_age, mother_dob, 
                            mother_citizenship, mother_religion
                        ) VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?)
                    ");
                    $stmt->execute([
                        $user_id, $father_name, $father_address, $father_contact, $father_occupation,
                        $father_office_address, $father_tel_no, $father_age, $father_dob, 
                        $father_citizenship, $father_religion,
                        $mother_name, $mother_address, $mother_contact, $mother_occupation,
                        $mother_office_address, $mother_tel_no, $mother_age, $mother_dob, 
                        $mother_citizenship, $mother_religion
                    ]);
                }

                // Update user_docs
                if (!empty($user_docs)) {
                    $stmt = $pdo->prepare("
                        UPDATE user_docs
                        SET cor_file_path = ?, indigency_file_path = ?, voter_file_path = ?
                        WHERE user_id = ?
                    ");
                    $stmt->execute([
                        $file_paths['cor_file'], $file_paths['indigency_file'], 
                        $file_paths['voter_file'], $user_id
                    ]);
                } else {
                    $stmt = $pdo->prepare("
                        INSERT INTO user_docs (
                            user_id, cor_file_path, indigency_file_path, voter_file_path, profile_picture_path
                        ) VALUES (?, ?, ?, ?, ?)
                    ");
                    $stmt->execute([
                        $user_id, $file_paths['cor_file'], $file_paths['indigency_file'], 
                        $file_paths['voter_file'], $file_paths['profile_picture']
                    ]);
                }
            } else {
                // Insert into users_info
                $stmt = $pdo->prepare("
                    INSERT INTO users_info (
                        user_id, municipality, barangay, sex, civil_status, nationality, 
                        birthdate, place_of_birth, application_status
                    ) VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?)
                ");
                $stmt->execute([
                    $user_id, $municipality, $barangay, $sex, $civil_status, $nationality, 
                    $birthdate, $place_of_birth, 'Under Review'
                ]);

                // Insert into user_personal
                $stmt = $pdo->prepare("
                    INSERT INTO user_personal (user_id, degree, course, current_college)
                    VALUES (?, ?, ?, ?)
                ");
                $stmt->execute([$user_id, $degree, $course, $current_college]);

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
                        father_office_address, father_tel_no, father_age, father_dob, 
                        father_citizenship, father_religion,
                        mother_name, mother_address, mother_contact, mother_occupation,
                        mother_office_address, mother_tel_no, mother_age, mother_dob, 
                        mother_citizenship, mother_religion
                    ) VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?)
                ");
                $stmt->execute([
                    $user_id, $father_name, $father_address, $father_contact, $father_occupation,
                    $father_office_address, $father_tel_no, $father_age, $father_dob, 
                    $father_citizenship, $father_religion,
                    $mother_name, $mother_address, $mother_contact, $mother_occupation,
                    $mother_office_address, $mother_tel_no, $mother_age, $mother_dob, 
                    $mother_citizenship, $mother_religion
                ]);

                // Insert into user_docs
                $stmt = $pdo->prepare("
                    INSERT INTO user_docs (
                        user_id, cor_file_path, indigency_file_path, voter_file_path, profile_picture_path
                    ) VALUES (?, ?, ?, ?, ?)
                ");
                $stmt->execute([
                    $user_id, $file_paths['cor_file'], $file_paths['indigency_file'], 
                    $file_paths['voter_file'], $file_paths['profile_picture']
                ]);
            }

            $pdo->commit();
            $_SESSION['application_success'] = $has_application ? "Application updated successfully!" : "Application submitted successfully!";
            header('Location: applicantdashboard.php');
            exit;
        } catch (PDOException $e) {
            $pdo->rollBack();
            foreach ($file_paths as $path) {
                if (!empty($path) && file_exists($path) && $path !== $file_paths['profile_picture']) {
                    unlink($path);
                }
            }
            $_SESSION['application_error'] = "Application submission failed: " . $e->getMessage();
            header('Location: applicantdashboard.php?view=Application');
            exit;
        }
    } else {
        $_SESSION['application_error'] = $upload_error;
        header('Location: applicantdashboard.php?view=Application');
        exit;
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
            --approved-color: #22c55e;
            --denied-color: #ef4444;
            --review-color: #f59e0b;
            --not-submitted-color: #6b7280;
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

        .stat-card h3.approved {
            color: var(--approved-color);
        }

        .stat-card h3.denied {
            color: var(--denied-color);
        }

        .stat-card h3.review {
            color: var(--review-color);
        }

        .stat-card h3.not-submitted {
            color: var(--not-submitted-color);
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
            display: flex;
            justify-content: space-between;
            align-items: center;
        }

        .notices ul li::before {
            color: var(--primary-color);
            font-weight: bold;
            display: inline-block;
            width: 1em;
            margin-left: -1em;
        }

        .notice-date {
            color: var(--text-muted);
            font-size: 0.8rem;
        }

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

        .info-message {
            background-color: rgba(59, 130, 246, 0.1);
            color: #3b82f6;
            padding: 0.75rem;
            border-radius: 8px;
            margin-bottom: 1.25rem;
            font-size: 0.9rem;
        }

        .dashboard-content {
            display: block;
        }

        .application-form {
            display: none;
        }

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
                    <?php echo htmlspecialchars(substr($firstname, 0, 1) . substr($lastname, 0, 1)); ?>
                <?php endif; ?>
                <div class="overlay">
                    <i class="fas fa-camera"></i>
                </div>
                <input type="file" id="profile-picture" name="profile_picture" accept=".png,.jpg,.jpeg" <?php echo !$is_application_open ? 'disabled' : ''; ?> onchange="document.getElementById('profilePictureForm').submit();">
            </div>
        </form>
        <div class="user-name">
            <div><?php echo htmlspecialchars($lastname . ', ' . $firstname ); ?></div>
            <div><?php echo htmlspecialchars($middlename); ?></div>
        </div>
        <ul>
            <li><a onclick="showDashboard()" id="dashboardLink" class="active" href="?view=dash"><i class="fas fa-home"></i> <span>Dashboard</span></a></li>
            <li><a onclick="showApplicationForm()" id="applyLink" href="?view=Application"><i class="fas fa-file-alt"></i> <span>Apply Scholarship</span></a></li>
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
                    <p>Application Status</p>
                    <h3 class="<?php 
                        echo $application_status === 'Not Yet Submitted' ? 'not-submitted' : 
                        (strtolower($application_status) === 'approved' ? 'approved' : 
                        (strtolower($application_status) === 'denied' ? 'denied' : 'review')); 
                    ?>">
                        <?php echo htmlspecialchars($application_status); ?>
                    </h3>
                </div>
                <div class="stat-card">
                    <i class="fas fa-bell"></i>
                    <h3><?php echo count($notices); ?></h3>
                    <p>New Notices</p>
                </div>
            </div>

            <!-- Important Notices Section -->
            <div class="section notices">
                <h2>Important Notices</h2>
                <ul>
                    <?php if (empty($notices)): ?>
                        <li>No new notices available.</li>
                    <?php else: ?>
                        <?php foreach ($notices as $notice): ?>
                            <li>
                                <?php echo htmlspecialchars($notice['message']); ?>
                                <span class="notice-date"><?php echo date('M d, Y', strtotime($notice['created_at'])); ?></span>
                            </li>
                        <?php endforeach; ?>
                    <?php endif; ?>
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
            <?php elseif ($has_application && $application_status !== 'Not Yet Submitted'): ?>
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
                                <input type="text" id="lastname" name="lastname" class="form-control" value="<?php echo htmlspecialchars($lastname); ?>" required <?php echo !$is_application_open ? 'disabled' : ''; ?>>
                            </div>
                        </div>
                        <div class="form-group">
                            <label for="firstname">Firstname <span class="required">*</span></label>
                            <div class="input-group">
                                <i class="fas fa-user"></i>
                                <input type="text" id="firstname" name="firstname" class="form-control" value="<?php echo htmlspecialchars($firstname); ?>" required <?php echo !$is_application_open ? 'disabled' : ''; ?>>
                            </div>
                        </div>
                        <div class="form-group">
                            <label for="middlename">Middlename (N/A if None) <span class="required">*</span></label>
                            <div class="input-group">
                                <i class="fas fa-user"></i>
                                <input type="text" id="middlename" name="middlename" class="form-control" value="<?php echo htmlspecialchars($middlename); ?>" required <?php echo !$is_application_open ? 'disabled' : ''; ?>>
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
                                    <option value="male" <?php echo (isset($users_info['sex']) && $users_info['sex'] === 'male') ? 'selected' : ''; ?>>Male</option>
                                    <option value="female" <?php echo (isset($users_info['sex']) && $users_info['sex'] === 'female') ? 'selected' : ''; ?>>Female</option>
                                    <option value="other" <?php echo (isset($users_info['sex']) && $users_info['sex'] === 'other') ? 'selected' : ''; ?>>Other</option>
                                </select>
                            </div>
                        </div>
                        <div class="form-group">
                            <label for="civil-status">Civil Status <span class="required">*</span></label>
                            <div class="input-group">
                                <i class="fas fa-heart"></i>
                                <select id="Civil-Status" name="Civil-Status" class="form-control" required style="padding-left: 2.5rem;" <?php echo !$is_application_open ? 'disabled' : ''; ?>>
                                    <option value="">Choose</option>
                                    <option value="single" <?php echo (isset($users_info['civil_status']) && $users_info['civil_status'] === 'single') ? 'selected' : ''; ?>>Single</option>
                                    <option value="married" <?php echo (isset($users_info['civil_status']) && $users_info['civil_status'] === 'married') ? 'selected' : ''; ?>>Married</option>
                                    <option value="divorced" <?php echo (isset($users_info['civil_status']) && $users_info['civil_status'] === 'divorced') ? 'selected' : ''; ?>>Divorced</option>
                                    <option value="widowed" <?php echo (isset($users_info['civil_status']) && $users_info['civil_status'] === 'widowed') ? 'selected' : ''; ?>>Widowed</option>
                                </select>
                            </div>
                        </div>
                        <div class="form-group">
                            <label for="birthdate">Birthdate <span class="required">*</span></label>
                            <div class="input-group">
                                <i class="fas fa-calendar-alt"></i>
                                <input type="date" id="birthdate" name="birthdate" class="form-control" value="<?php echo htmlspecialchars($users_info['birthdate'] ?? ''); ?>" required <?php echo !$is_application_open ? 'disabled' : ''; ?>>
                            </div>
                        </div>
                    </div>
                    <div class="form-row">
                        <div class="form-group">
                            <label for="municipality">Municipality <span class="required">*</span></label>
                            <div class="input-group">
                                <i class="fas fa-map"></i>
                                <input type="text" id="municipality" name="municipality" class="form-control" value="<?php echo htmlspecialchars($users_info['municipality'] ?? ''); ?>" required <?php echo !$is_application_open ? 'disabled' : ''; ?>>
                            </div>
                        </div>
                        <div class="form-group">
                            <label for="barangay">Barangay <span class="required">*</span></label>
                            <div class="input-group">
                                <i class="fas fa-map-marker"></i>
                                <input type="text" id="barangay" name="barangay" class="form-control" value="<?php echo htmlspecialchars($users_info['barangay'] ?? ''); ?>" required <?php echo !$is_application_open ? 'disabled' : ''; ?>>
                            </div>
                        </div>
                    </div>
                    <div class="form-row">
                        <div class="form-group">
                            <label for="nationality">Nationality <span class="required">*</span></label>
                            <div class="input-group">
                                <i class="fas fa-flag"></i>
                                <input type="text" id="nationality" name="nationality" class="form-control" value="<?php echo htmlspecialchars($users_info['nationality'] ?? ''); ?>" required <?php echo !$is_application_open ? 'disabled' : ''; ?>>
                            </div>
                        </div>
                        <div class="form-group">
                            <label for="place_of_birth">Place of Birth <span class="required">*</span></label>
                            <div class="input-group">
                                <i class="fas fa-map-pin"></i>
                                <input type="text" id="place_of_birth" name="place_of_birth" class="form-control" value="<?php echo htmlspecialchars($users_info['place_of_birth'] ?? ''); ?>" required <?php echo !$is_application_open ? 'disabled' : ''; ?>>
                            </div>
                        </div>
                    </div>
                    <div class="form-row">
                        <div class="form-group">
                            <label for="track">Degree <span class="required">*</span></label>
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
                            <label for="secondary-school">Current College/University <span class="required">*</span></label>
                            <div class="input-group">
                                <i class="fas fa-school"></i>
                                <input type="text" id="secondary-school" name="secondary_school" class="form-control" value="<?php echo htmlspecialchars($user_personal['current_college'] ?? ''); ?>" required <?php echo !$is_application_open ? 'disabled' : ''; ?>>
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
            <h4>Guardian Information</h4>
        </div>
    </div>
    <div class="form-row">
        <div class="form-group">
            <label for="guardian_name">Name <span class="required">*</span></label>
            <div class="input-group">
                <i class="fas fa-user"></i>
                <input type="text" id="guardian_name" name="guardian_name" class="form-control" value="<?php echo htmlspecialchars($user_residency['guardian_name'] ?? ''); ?>" required <?php echo !$is_application_open ? 'disabled' : ''; ?>>
            </div>
        </div>
        <div class="form-group">
            <label for="relationship">Relationship <span class="required">*</span></label>
            <div class="input-group">
                <i class="fas fa-users"></i>
                <input type="text" id="relationship" name="relationship" class="form-control" value="<?php echo htmlspecialchars($user_residency['relationship'] ?? ''); ?>" required <?php echo !$is_application_open ? 'disabled' : ''; ?>>
            </div>
        </div>
    </div>
    <div class="form-row">
        <div class="form-group full-width">
            <label for="guardian_address">Address <span class="required">*</span></label>
            <div class="input-group">
                <i class="fas fa-map-marker-alt"></i>
                <input type="text" id="guardian_address" name="guardian_address" class="form-control" value="<?php echo htmlspecialchars($user_residency['guardian_address'] ?? ''); ?>" required <?php echo !$is_application_open ? 'disabled' : ''; ?>>
            </div>
        </div>
    </div>
    <div class="form-row">
        <div class="form-group">
            <label for="guardian_contact">Contact Number <span class="required">*</span></label>
            <div class="input-group">
                <i class="fas fa-phone"></i>
                <input type="tel" id="guardian_contact" name="guardian_contact" class="form-control" value="<?php echo htmlspecialchars($user_residency['guardian_contact'] ?? ''); ?>" required <?php echo !$is_application_open ? 'disabled' : ''; ?>>
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
        <div class="family-member">
            <h4>Father</h4>
            <div class="form-row">
                <div class="form-group">
                    <label for="father_name">Name <span class="required">*</span></label>
                    <div class="input-group">
                        <i class="fas fa-user"></i>
                        <input type="text" id="father_name" name="father_name" class="form-control" value="<?php echo htmlspecialchars($user_fam['father_name'] ?? ''); ?>" required <?php echo !$is_application_open ? 'disabled' : ''; ?>>
                    </div>
                </div>
            </div>
            <div class="form-row">
                <div class="form-group">
                    <label for="father_address">Address <span class="required">*</span></label>
                    <div class="input-group">
                        <i class="fas fa-map-marker-alt"></i>
                        <input type="text" id="father_address" name="father_address" class="form-control" value="<?php echo htmlspecialchars($user_fam['father_address'] ?? ''); ?>" required <?php echo !$is_application_open ? 'disabled' : ''; ?>>
                    </div>
                </div>
            </div>
            <div class="form-row">
                <div class="form-group">
                    <label for="father_contact">Contact Number <span class="required">*</span></label>
                    <div class="input-group">
                        <i class="fas fa-phone"></i>
                        <input type="tel" id="father_contact" name="father_contact" class="form-control" value="<?php echo htmlspecialchars($user_fam['father_contact'] ?? ''); ?>" required <?php echo !$is_application_open ? 'disabled' : ''; ?>>
                    </div>
                </div>
                <div class="form-group">
                    <label for="father_occupation">Occupation <span class="required">*</span></label>
                    <div class="input-group">
                        <i class="fas fa-briefcase"></i>
                        <input type="text" id="father_occupation" name="father_occupation" class="form-control" value="<?php echo htmlspecialchars($user_fam['father_occupation'] ?? ''); ?>" required <?php echo !$is_application_open ? 'disabled' : ''; ?>>
                    </div>
                </div>
            </div>
            <div class="form-row">
                <div class="form-group">
                    <label for="father_office_address">Office Address <span class="required">*</span></label>
                    <div class="input-group">
                        <i class="fas fa-building"></i>
                        <input type="text" id="father_office_address" name="father_office_address" class="form-control" value="<?php echo htmlspecialchars($user_fam['father_office_address'] ?? ''); ?>" required <?php echo !$is_application_open ? 'disabled' : ''; ?>>
                    </div>
                </div>
                <div class="form-group">
                    <label for="father_tel_no">Tel No. <span class="required">*</span></label>
                    <div class="input-group">
                        <i class="fas fa-phone"></i>
                        <input type="tel" id="father_tel_no" name="father_tel_no" class="form-control" value="<?php echo htmlspecialchars($user_fam['father_tel_no'] ?? ''); ?>" required <?php echo !$is_application_open ? 'disabled' : ''; ?>>
                    </div>
                </div>
            </div>
            <div class="form-row">
                <div class="form-group">
                    <label for="father_age">Age <span class="required">*</span></label>
                    <div class="input-group">
                        <i class="fas fa-birthday-cake"></i>
                        <input type="number" id="father_age" name="father_age" class="form-control" value="<?php echo htmlspecialchars($user_fam['father_age'] ?? ''); ?>" required <?php echo !$is_application_open ? 'disabled' : ''; ?>>
                    </div>
                </div>
                <div class="form-group">
                    <label for="father_dob">Date of Birth <span class="required">*</span></label>
                    <div class="input-group">
                        <i class="fas fa-calendar-alt"></i>
                        <input type="date" id="father_dob" name="father_dob" class="form-control" value="<?php echo htmlspecialchars($user_fam['father_dob'] ?? ''); ?>" required <?php echo !$is_application_open ? 'disabled' : ''; ?>>
                    </div>
                </div>
            </div>
            <div class="form-row">
                <div class="form-group">
                    <label for="father_citizenship">Citizenship <span class="required">*</span></label>
                    <div class="input-group">
                        <i class="fas fa-flag"></i>
                        <input type="text" id="father_citizenship" name="father_citizenship" class="form-control" value="<?php echo htmlspecialchars($user_fam['father_citizenship'] ?? ''); ?>" required <?php echo !$is_application_open ? 'disabled' : ''; ?>>
                    </div>
                </div>
                <div class="form-group">
                    <label for="father_religion">Religion <span class="required">*</span></label>
                    <div class="input-group">
                        <i class="fas fa-church"></i>
                        <input type="text" id="father_religion" name="father_religion" class="form-control" value="<?php echo htmlspecialchars($user_fam['father_religion'] ?? ''); ?>" required <?php echo !$is_application_open ? 'disabled' : ''; ?>>
                    </div>
                </div>
            </div>
        </div>
        <div class="family-member">
            <h4>Mother</h4>
            <div class="form-row">
                <div class="form-group">
                    <label for="mother_name">Name <span class="required">*</span></label>
                    <div class="input-group">
                        <i class="fas fa-user"></i>
                        <input type="text" id="mother_name" name="mother_name" class="form-control" value="<?php echo htmlspecialchars($user_fam['mother_name'] ?? ''); ?>" required <?php echo !$is_application_open ? 'disabled' : ''; ?>>
                    </div>
                </div>
            </div>
            <div class="form-row">
                <div class="form-group">
                    <label for="mother_address">Address <span class="required">*</span></label>
                    <div class="input-group">
                        <i class="fas fa-map-marker-alt"></i>
                        <input type="text" id="mother_address" name="mother_address" class="form-control" value="<?php echo htmlspecialchars($user_fam['mother_address'] ?? ''); ?>" required <?php echo !$is_application_open ? 'disabled' : ''; ?>>
                    </div>
                </div>
            </div>
            <div class="form-row">
                <div class="form-group">
                    <label for="mother_contact">Contact Number <span class="required">*</span></label>
                    <div class="input-group">
                        <i class="fas fa-phone"></i>
                        <input type="tel" id="mother_contact" name="mother_contact" class="form-control" value="<?php echo htmlspecialchars($user_fam['mother_contact'] ?? ''); ?>" required <?php echo !$is_application_open ? 'disabled' : ''; ?>>
                    </div>
                </div>
                <div class="form-group">
                    <label for="mother_occupation">Occupation <span class="required">*</span></label>
                    <div class="input-group">
                        <i class="fas fa-briefcase"></i>
                        <input type="text" id="mother_occupation" name="mother_occupation" class="form-control" value="<?php echo htmlspecialchars($user_fam['mother_occupation'] ?? ''); ?>" required <?php echo !$is_application_open ? 'disabled' : ''; ?>>
                    </div>
                </div>
            </div>
            <div class="form-row">
                <div class="form-group">
                    <label for="mother_office_address">Office Address <span class="required">*</span></label>
                    <div class="input-group">
                        <i class="fas fa-building"></i>
                        <input type="text" id="mother_office_address" name="mother_office_address" class="form-control" value="<?php echo htmlspecialchars($user_fam['mother_office_address'] ?? ''); ?>" required <?php echo !$is_application_open ? 'disabled' : ''; ?>>
                    </div>
                </div>
                <div class="form-group">
                    <label for="mother_tel_no">Tel No. <span class="required">*</span></label>
                    <div class="input-group">
                        <i class="fas fa-phone"></i>
                        <input type="tel" id="mother_tel_no" name="mother_tel_no" class="form-control" value="<?php echo htmlspecialchars($user_fam['mother_tel_no'] ?? ''); ?>" required <?php echo !$is_application_open ? 'disabled' : ''; ?>>
                    </div>
                </div>
            </div>
            <div class="form-row">
                <div class="form-group">
                    <label for="mother_age">Age <span class="required">*</span></label>
                    <div class="input-group">
                        <i class="fas fa-birthday-cake"></i>
                        <input type="number" id="mother_age" name="mother_age" class="form-control" value="<?php echo htmlspecialchars($user_fam['mother_age'] ?? ''); ?>" required <?php echo !$is_application_open ? 'disabled' : ''; ?>>
                    </div>
                </div>
                <div class="form-group">
                    <label for="mother_dob">Date of Birth <span class="required">*</span></label>
                    <div class="input-group">
                        <i class="fas fa-calendar-alt"></i>
                        <input type="date" id="mother_dob" name="mother_dob" class="form-control" value="<?php echo htmlspecialchars($user_fam['mother_dob'] ?? ''); ?>" required <?php echo !$is_application_open ? 'disabled' : ''; ?>>
                    </div>
                </div>
            </div>
            <div class="form-row">
                <div class="form-group">
                    <label for="mother_citizenship">Citizenship <span class="required">*</span></label>
                    <div class="input-group">
                        <i class="fas fa-flag"></i>
                        <input type="text" id="mother_citizenship" name="mother_citizenship" class="form-control" value="<?php echo htmlspecialchars($user_fam['mother_citizenship'] ?? ''); ?>" required <?php echo !$is_application_open ? 'disabled' : ''; ?>>
                    </div>
                </div>
                <div class="form-group">
                    <label for="mother_religion">Religion <span class="required">*</span></label>
                    <div class="input-group">
                        <i class="fas fa-church"></i>
                        <input type="text" id="mother_religion" name="mother_religion" class="form-control" value="<?php echo htmlspecialchars($user_fam['mother_religion'] ?? ''); ?>" required <?php echo !$is_application_open ? 'disabled' : ''; ?>>
                    </div>
                </div>
            </div>
        </div>
    </div>
    <div class="form-buttons">
        <button type="button" class="prev-btn" onclick="prevStep(3)">Previous</button>
        <button type="button" class="next-btn" onclick="nextStep(3)">Next</button>
    </div>
</div>

<div class="form-section" id="step4" style="display: none;">
    <h3>Documents</h3>
    <div class="form-row">
        <div class="form-group full-width">
            <label for="cor_file">Certificate of Registration (COR) <span class="required">*</span></label>
            <div class="file-upload-group">
                <input type="file" id="cor_file" name="cor_file" accept=".pdf,.png,.jpg,.jpeg" <?php echo !$is_application_open ? 'disabled' : ''; ?>>
                <label for="cor_file" class="file-upload-label" <?php echo !$is_application_open ? 'disabled' : ''; ?>>
                    <i class="fas fa-upload"></i> Choose File
                </label>
                <span class="file-name"><?php echo !empty($user_docs['cor_file_path']) ? basename($user_docs['cor_file_path']) : 'No file chosen'; ?></span>
            </div>
            <?php if (isset($_SESSION['application_error']) && strpos($_SESSION['application_error'], 'cor_file') !== false): ?>
                <div class="file-error"><?php echo $_SESSION['application_error']; ?></div>
            <?php endif; ?>
        </div>
    </div>
    <div class="form-row">
        <div class="form-group full-width">
            <label for="indigency_file">Certificate of Indigency <span class="required">*</span></label>
            <div class="file-upload-group">
                <input type="file" id="indigency_file" name="indigency_file" accept=".pdf,.png,.jpg,.jpeg" <?php echo !$is_application_open ? 'disabled' : ''; ?>>
                <label for="indigency_file" class="file-upload-label" <?php echo !$is_application_open ? 'disabled' : ''; ?>>
                    <i class="fas fa-upload"></i> Choose File
                </label>
                <span class="file-name"><?php echo !empty($user_docs['indigency_file_path']) ? basename($user_docs['indigency_file_path']) : 'No file chosen'; ?></span>
            </div>
            <?php if (isset($_SESSION['application_error']) && strpos($_SESSION['application_error'], 'indigency_file') !== false): ?>
                <div class="file-error"><?php echo $_SESSION['application_error']; ?></div>
            <?php endif; ?>
        </div>
    </div>
    <div class="form-row">
        <div class="form-group full-width">
            <label for="voter_file">Voter's Certificate <span class="required">*</span></label>
            <div class="file-upload-group">
                <input type="file" id="voter_file" name="voter_file" accept=".pdf,.png,.jpg,.jpeg" <?php echo !$is_application_open ? 'disabled' : ''; ?>>
                <label for="voter_file" class="file-upload-label" <?php echo !$is_application_open ? 'disabled' : ''; ?>>
                    <i class="fas fa-upload"></i> Choose File
                </label>
                <span class="file-name"><?php echo !empty($user_docs['voter_file_path']) ? basename($user_docs['voter_file_path']) : 'No file chosen'; ?></span>
            </div>
            <?php if (isset($_SESSION['application_error']) && strpos($_SESSION['application_error'], 'voter_file') !== false): ?>
                <div class="file-error"><?php echo $_SESSION['application_error']; ?></div>
            <?php endif; ?>
        </div>
    </div>
    <div class="form-buttons">
        <button type="button" class="prev-btn" onclick="prevStep(4)">Previous</button>
        <button type="button" class="next-btn" onclick="nextStep(4)">Next</button>
    </div>
</div>

<!-- Replace the existing step5 form-section with this -->
<div class="form-section" id="step5" style="display: none;">
    <h3>Review and Submit</h3>
    <div class="form-row">
        <div class="form-group full-width">
            <h4>Personal Information</h4>
            <p><strong>Lastname:</strong> <span id="review_lastname"><?php echo htmlspecialchars($lastname); ?></span></p>
            <p><strong>Firstname:</strong> <span id="review_firstname"><?php echo htmlspecialchars($firstname); ?></span></p>
            <p><strong>Middlename:</strong> <span id="review_middlename"><?php echo htmlspecialchars($middlename); ?></span></p>
            <p><strong>Sex:</strong> <span id="review_sex"><?php echo htmlspecialchars($users_info['sex'] ?? 'Not provided'); ?></span></p>
            <p><strong>Civil Status:</strong> <span id="review_civil_status"><?php echo htmlspecialchars($users_info['civil_status'] ?? 'Not provided'); ?></span></p>
            <p><strong>Birthdate:</strong> <span id="review_birthdate"><?php echo htmlspecialchars($users_info['birthdate'] ?? 'Not provided'); ?></span></p>
            <p><strong>Municipality:</strong> <span id="review_municipality"><?php echo htmlspecialchars($users_info['municipality'] ?? 'Not provided'); ?></span></p>
            <p><strong>Barangay:</strong> <span id="review_barangay"><?php echo htmlspecialchars($users_info['barangay'] ?? 'Not provided'); ?></span></p>
            <p><strong>Nationality:</strong> <span id="review_nationality"><?php echo htmlspecialchars($users_info['nationality'] ?? 'Not provided'); ?></span></p>
            <p><strong>Place of Birth:</strong> <span id="review_place_of_birth"><?php echo htmlspecialchars($users_info['place_of_birth'] ?? 'Not provided'); ?></span></p>
            <p><strong>Degree:</strong> <span id="review_degree"><?php echo htmlspecialchars($user_personal['degree'] ?? 'Not provided'); ?></span></p>
            <p><strong>Course:</strong> <span id="review_course"><?php echo htmlspecialchars($user_personal['course'] ?? 'Not provided'); ?></span></p>
            <p><strong>Current College:</strong> <span id="review_current_college"><?php echo htmlspecialchars($user_personal['current_college'] ?? 'Not provided'); ?></span></p>
        </div>
    </div>
    <div class="form-row">
        <div class="form-group full-width">
            <h4>Residency</h4>
            <p><strong>Permanent Address:</strong> <span id="review_permanent_address"><?php echo htmlspecialchars($user_residency['permanent_address'] ?? 'Not provided'); ?></span></p>
            <p><strong>Residency Duration:</strong> <span id="review_residency_duration"><?php echo htmlspecialchars($user_residency['residency_duration'] ?? 'Not provided'); ?></span></p>
            <p><strong>Registered Voter:</strong> <span id="review_registered_voter"><?php echo htmlspecialchars($user_residency['registered_voter'] ?? 'Not provided'); ?></span></p>
            <p><strong>Father Voting Duration:</strong> <span id="review_father_voting_duration"><?php echo htmlspecialchars($user_residency['father_voting_duration'] ?? 'Not provided'); ?></span></p>
            <p><strong>Mother Voting Duration:</strong> <span id="review_mother_voting_duration"><?php echo htmlspecialchars($user_residency['mother_voting_duration'] ?? 'Not provided'); ?></span></p>
            <p><strong>Applicant Voting Duration:</strong> <span id="review_applicant_voting_duration"><?php echo htmlspecialchars($user_residency['applicant_voting_duration'] ?? 'Not provided'); ?></span></p>
            <h5>Guardian Information</h5>
            <p><strong>Name:</strong> <span id="review_guardian_name"><?php echo htmlspecialchars($user_residency['guardian_name'] ?? 'Not provided'); ?></span></p>
            <p><strong>Relationship:</strong> <span id="review_relationship"><?php echo htmlspecialchars($user_residency['relationship'] ?? 'Not provided'); ?></span></p>
            <p><strong>Address:</strong> <span id="review_guardian_address"><?php echo htmlspecialchars($user_residency['guardian_address'] ?? 'Not provided'); ?></span></p>
            <p><strong>Contact Number:</strong> <span id="review_guardian_contact"><?php echo htmlspecialchars($user_residency['guardian_contact'] ?? 'Not provided'); ?></span></p>
        </div>
    </div>
    <div class="form-row">
        <div class="form-group full-width">
            <h4>Family Background</h4>
            <h5>Father</h5>
            <p><strong>Name:</strong> <span id="review_father_name"><?php echo htmlspecialchars($user_fam['father_name'] ?? 'Not provided'); ?></span></p>
            <p><strong>Address:</strong> <span id="review_father_address"><?php echo htmlspecialchars($user_fam['father_address'] ?? 'Not provided'); ?></span></p>
            <p><strong>Contact Number:</strong> <span id="review_father_contact"><?php echo htmlspecialchars($user_fam['father_contact'] ?? 'Not provided'); ?></span></p>
            <p><strong>Occupation:</strong> <span id="review_father_occupation"><?php echo htmlspecialchars($user_fam['father_occupation'] ?? 'Not provided'); ?></span></p>
            <p><strong>Office Address:</strong> <span id="review_father_office_address"><?php echo htmlspecialchars($user_fam['father_office_address'] ?? 'Not provided'); ?></span></p>
            <p><strong>Tel No.:</strong> <span id="review_father_tel_no"><?php echo htmlspecialchars($user_fam['father_tel_no'] ?? 'Not provided'); ?></span></p>
            <p><strong>Age:</strong> <span id="review_father_age"><?php echo htmlspecialchars($user_fam['father_age'] ?? 'Not provided'); ?></span></p>
            <p><strong>Date of Birth:</strong> <span id="review_father_dob"><?php echo htmlspecialchars($user_fam['father_dob'] ?? 'Not provided'); ?></span></p>
            <p><strong>Citizenship:</strong> <span id="review_father_citizenship"><?php echo htmlspecialchars($user_fam['father_citizenship'] ?? 'Not provided'); ?></span></p>
            <p><strong>Religion:</strong> <span id="review_father_religion"><?php echo htmlspecialchars($user_fam['father_religion'] ?? 'Not provided'); ?></span></p>
            <h5>Mother</h5>
            <p><strong>Name:</strong> <span id="review_mother_name"><?php echo htmlspecialchars($user_fam['mother_name'] ?? 'Not provided'); ?></span></p>
            <p><strong>Address:</strong> <span id="review_mother_address"><?php echo htmlspecialchars($user_fam['mother_address'] ?? 'Not provided'); ?></span></p>
            <p><strong>Contact Number:</strong> <span id="review_mother_contact"><?php echo htmlspecialchars($user_fam['mother_contact'] ?? 'Not provided'); ?></span></p>
            <p><strong>Occupation:</strong> <span id="review_mother_occupation"><?php echo htmlspecialchars($user_fam['mother_occupation'] ?? 'Not provided'); ?></span></p>
            <p><strong>Office Address:</strong> <span id="review_mother_office_address"><?php echo htmlspecialchars($user_fam['mother_office_address'] ?? 'Not provided'); ?></span></p>
            <p><strong>Tel No.:</strong> <span id="review_mother_tel_no"><?php echo htmlspecialchars($user_fam['mother_tel_no'] ?? 'Not provided'); ?></span></p>
            <p><strong>Age:</strong> <span id="review_mother_age"><?php echo htmlspecialchars($user_fam['mother_age'] ?? 'Not provided'); ?></span></p>
            <p><strong>Date of Birth:</strong> <span id="review_mother_dob"><?php echo htmlspecialchars($user_fam['mother_dob'] ?? 'Not provided'); ?></span></p>
            <p><strong>Citizenship:</strong> <span id="review_mother_citizenship"><?php echo htmlspecialchars($user_fam['mother_citizenship'] ?? 'Not provided'); ?></span></p>
            <p><strong>Religion:</strong> <span id="review_mother_religion"><?php echo htmlspecialchars($user_fam['mother_religion'] ?? 'Not provided'); ?></span></p>
        </div>
    </div>
    <div class="form-row">
        <div class="form-group full-width">
            <h4>Documents</h4>
            <p><strong>Certificate of Registration:</strong> <span id="review_cor_file"><?php echo !empty($user_docs['cor_file_path']) ? htmlspecialchars(basename($user_docs['cor_file_path'])) : 'Not uploaded'; ?></span></p>
            <p><strong>Certificate of Indigency:</strong> <span id="review_indigency_file"><?php echo !empty($user_docs['indigency_file_path']) ? htmlspecialchars(basename($user_docs['indigency_file_path'])) : 'Not uploaded'; ?></span></p>
            <p><strong>Voter's Certificate:</strong> <span id="review_voter_file"><?php echo !empty($user_docs['voter_file_path']) ? htmlspecialchars(basename($user_docs['voter_file_path'])) : 'Not uploaded'; ?></span></p>
        </div>
    </div>
    <div class="form-buttons">
        <button type="button" class="prev-btn" onclick="prevStep(5)">Previous</button>
        <button type="submit" name="submit_application" class="submit-btn" <?php echo !$is_application_open ? 'disabled' : ''; ?>>Submit Application</button>
    </div>
</div>

<script>
    let currentStep = 1;

    function showStep(step) {
        document.querySelectorAll('.form-section').forEach(section => {
            section.style.display = 'none';
        });
        document.getElementById(`step${step}`).style.display = 'block';

        document.querySelectorAll('.progress-step').forEach((stepElement, index) => {
            stepElement.classList.toggle('active', index + 1 <= step);
        });

        if (step === 5) {
            updateReviewSection();
        }

        currentStep = step;
    }

    function nextStep(current) {
        if (current < 5) {
            showStep(current + 1);
        }
    }

    function prevStep(current) {
        if (current > 1) {
            showStep(current - 1);
        }
    }

    function updateReviewSection() {
        // Personal Information
        document.getElementById('review_lastname').textContent = document.getElementById('lastname').value || 'Not provided';
        document.getElementById('review_firstname').textContent = document.getElementById('firstname').value || 'Not provided';
        document.getElementById('review_middlename').textContent = document.getElementById('middlename').value || 'Not provided';
        document.getElementById('review_sex').textContent = document.getElementById('Sex').value || 'Not provided';
        document.getElementById('review_civil_status').textContent = document.getElementById('Civil-Status').value || 'Not provided';
        document.getElementById('review_birthdate').textContent = document.getElementById('birthdate').value || 'Not provided';
        document.getElementById('review_municipality').textContent = document.getElementById('municipality').value || 'Not provided';
        document.getElementById('review_barangay').textContent = document.getElementById('barangay').value || 'Not provided';
        document.getElementById('review_nationality').textContent = document.getElementById('nationality').value || 'Not provided';
        document.getElementById('review_place_of_birth').textContent = document.getElementById('place_of_birth').value || 'Not provided';
        document.getElementById('review_degree').textContent = document.getElementById('track').value || 'Not provided';
        document.getElementById('review_course').textContent = document.getElementById('Course').value || 'Not provided';
        document.getElementById('review_current_college').textContent = document.getElementById('secondary-school').value || 'Not provided';

        // Residency
        document.getElementById('review_permanent_address').textContent = document.getElementById('permanent-address').value || 'Not provided';
        document.getElementById('review_residency_duration').textContent = document.getElementById('residency-duration').value || 'Not provided';
        const registeredVoter = document.querySelector('input[name="registered_voter"]:checked');
        document.getElementById('review_registered_voter').textContent = registeredVoter ? registeredVoter.value : 'Not provided';
        document.getElementById('review_father_voting_duration').textContent = document.getElementById('father_voting_duration').value || 'Not provided';
        document.getElementById('review_mother_voting_duration').textContent = document.getElementById('mother_voting_duration').value || 'Not provided';
        document.getElementById('review_applicant_voting_duration').textContent = document.getElementById('applicant_voting_duration').value || 'Not provided';
        document.getElementById('review_guardian_name').textContent = document.getElementById('guardian_name').value || 'Not provided';
        document.getElementById('review_relationship').textContent = document.getElementById('relationship').value || 'Not provided';
        document.getElementById('review_guardian_address').textContent = document.getElementById('guardian_address').value || 'Not provided';
        document.getElementById('review_guardian_contact').textContent = document.getElementById('guardian_contact').value || 'Not provided';

        // Family Background - Father
        document.getElementById('review_father_name').textContent = document.getElementById('father_name').value || 'Not provided';
        document.getElementById('review_father_address').textContent = document.getElementById('father_address').value || 'Not provided';
        document.getElementById('review_father_contact').textContent = document.getElementById('father_contact').value || 'Not provided';
        document.getElementById('review_father_occupation').textContent = document.getElementById('father_occupation').value || 'Not provided';
        document.getElementById('review_father_office_address').textContent = document.getElementById('father_office_address').value || 'Not provided';
        document.getElementById('review_father_tel_no').textContent = document.getElementById('father_tel_no').value || 'Not provided';
        document.getElementById('review_father_age').textContent = document.getElementById('father_age').value || 'Not provided';
        document.getElementById('review_father_dob').textContent = document.getElementById('father_dob').value || 'Not provided';
        document.getElementById('review_father_citizenship').textContent = document.getElementById('father_citizenship').value || 'Not provided';
        document.getElementById('review_father_religion').textContent = document.getElementById('father_religion').value || 'Not provided';

        // Family Background - Mother
        document.getElementById('review_mother_name').textContent = document.getElementById('mother_name').value || 'Not provided';
        document.getElementById('review_mother_address').textContent = document.getElementById('mother_address').value || 'Not provided';
        document.getElementById('review_mother_contact').textContent = document.getElementById('mother_contact').value || 'Not provided';
        document.getElementById('review_mother_occupation').textContent = document.getElementById('mother_occupation').value || 'Not provided';
        document.getElementById('review_mother_office_address').textContent = document.getElementById('mother_office_address').value || 'Not provided';
        document.getElementById('review_mother_tel_no').textContent = document.getElementById('mother_tel_no').value || 'Not provided';
        document.getElementById('review_mother_age').textContent = document.getElementById('mother_age').value || 'Not provided';
        document.getElementById('review_mother_dob').textContent = document.getElementById('mother_dob').value || 'Not provided';
        document.getElementById('review_mother_citizenship').textContent = document.getElementById('mother_citizenship').value || 'Not provided';
        document.getElementById('review_mother_religion').textContent = document.getElementById('mother_religion').value || 'Not provided';

        // Documents
        document.getElementById('review_cor_file').textContent = document.getElementById('cor_file').files.length > 0 ? document.getElementById('cor_file').files[0].name : '<?php echo !empty($user_docs['cor_file_path']) ? htmlspecialchars(basename($user_docs['cor_file_path'])) : 'Not uploaded'; ?>';
        document.getElementById('review_indigency_file').textContent = document.getElementById('indigency_file').files.length > 0 ? document.getElementById('indigency_file').files[0].name : '<?php echo !empty($user_docs['indigency_file_path']) ? htmlspecialchars(basename($user_docs['indigency_file_path'])) : 'Not uploaded'; ?>';
        document.getElementById('review_voter_file').textContent = document.getElementById('voter_file').files.length > 0 ? document.getElementById('voter_file').files[0].name : '<?php echo !empty($user_docs['voter_file_path']) ? htmlspecialchars(basename($user_docs['voter_file_path'])) : 'Not uploaded'; ?>';
    }

    function showDashboard() {
        document.getElementById('dashboardContent').style.display = 'block';
        document.getElementById('applicationForm').style.display = 'none';
        document.getElementById('dashboardLink').classList.add('active');
        document.getElementById('applyLink').classList.remove('active');
    }

    function showApplicationForm() {
        document.getElementById('dashboardContent').style.display = 'none';
        document.getElementById('applicationForm').style.display = 'block';
        document.getElementById('applyLink').classList.add('active');
        document.getElementById('dashboardLink').classList.remove('active');
        showStep(1);
    }

    document.querySelectorAll('.file-upload-group input[type="file"]').forEach(input => {
        input.addEventListener('change', function() {
            const fileName = this.files.length > 0 ? this.files[0].name : 'No file chosen';
            this.nextElementSibling.nextElementSibling.textContent = fileName;
        });
    });

    document.querySelector('.profile-pic').addEventListener('click', function() {
        if (!<?php echo json_encode(!$is_application_open); ?>) {
            document.getElementById('profile-picture').click();
        }
    });

    <?php if (isset($_GET['view']) && $_GET['view'] === 'Application'): ?>
        showApplicationForm();
    <?php else: ?>
        showDashboard();
    <?php endif; ?>
</script>