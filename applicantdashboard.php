<?php
require './route_guard.php';

// Remove all tokens table logic. Only use JWT for session validation.

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
$claim_status = 'Not Claimed';
$claim_photo_path = '';

$users_info = [];
$user_personal = [];
$user_residency = [];
$user_fam = [];
$user_docs = [];
$contact_no = ''; // Add this line to store contact number

try {
    // Fetch contact number from users table
    $stmt = $pdo->prepare("SELECT contact_no FROM users WHERE id = ?");
    $stmt->execute([$user_id]);
    $user = $stmt->fetch(PDO::FETCH_ASSOC);
    $contact_no = $user['contact_no'] ?? '';

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

        // Fetch claim status and photo
        $claim_status = isset($users_info['claim_status']) && $users_info['claim_status'] === 'Claimed' ? 'Claimed' : 'Not Claimed';
        $claim_photo_path = !empty($user_docs['claim_photo_path']) ? $user_docs['claim_photo_path'] : '';
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
        'contact_no' => trim($_POST['contact_no'] ?? ''), // Add this line
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
        'profile_picture' => $user_docs['profile_picture_path'] ?? '',
        'claim_photo' => $user_docs['claim_photo_path'] ?? ''
    ];

    foreach (['cor_file', 'indigency_file', 'voter_file', 'profile_picture'] as $file_key) {
        if (!empty($_FILES[$file_key]['name'])) {
            $file = $_FILES[$file_key];
            $file_name = basename($file['name']);
            $file_type = $file['type'];
            $file_size = $file['size'];
            $file_tmp = $file['tmp_name'];

            // Only allow images for profile_picture
            if ($file_key === 'profile_picture') {
                $allowed_profile_types = ['image/png', 'image/jpeg', 'image/jpg'];
                if (!in_array($file_type, $allowed_profile_types)) {
                    $upload_error = "Invalid file type for profile_picture. Only PNG, JPEG allowed.";
                    break;
                }
            } else {
                if (!in_array($file_type, $allowed_types)) {
                    $upload_error = "Invalid file type for $file_key. Only PDF, PNG, JPEG allowed.";
                    break;
                }
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
                SET lastname = ?, firstname = ?, middlename = ?, contact_no = ?
                WHERE id = ?
            ");
            $stmt->execute([$lastname, $firstname, $middlename, trim($_POST['contact_no']), $user_id]);

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

// PHP function to get claim data (if more details are needed in the future)
function getClaimData($users_info, $user_docs) {
    return [
        'claim_status' => $users_info['claim_status'] ?? 'Not Claimed',
        'claim_photo_path' => $user_docs['claim_photo_path'] ?? '',
        'claimed_at' => $users_info['claimed_at'] ?? '',
        // Add more fields as needed
    ];
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

if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['action']) && $_POST['action'] === 'get_barangays') {
    // Prevent any output before headers
    ob_clean();
    
    // Set headers
    header('Content-Type: application/json');
    header('Cache-Control: no-cache, must-revalidate');
    
    try {
        if (!isset($_POST['municipality']) || empty($_POST['municipality'])) {
            throw new Exception('Municipality is required');
        }

        require_once 'philippine_locations.php';
        $municipality = trim($_POST['municipality']);
        $barangays = getBarangays($municipality);
        
        if (empty($barangays)) {
            throw new Exception('No barangays found for the selected municipality');
        }
        
        echo json_encode($barangays);
    } catch (Exception $e) {
        echo json_encode(['error' => $e->getMessage()]);
    }
    exit;
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
    <link rel="stylesheet" href="applicantstyles.css">
    <style>

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
            box-shadow: 4px 0 15px rgba(0, 0, 0, 0.1);
            border-right: 1px solid rgba(255, 255, 255, 0.1);
        }

        .sidebar .logo {
            margin-bottom: 2.5rem;
            text-align: center;
            width: 100%;
            padding: 1rem;
            border-bottom: 1px solid rgba(255, 255, 255, 0.1);
        }

        .sidebar .logo span {
            font-size: 1.3rem;
            color: white;
            font-weight: 600;
            letter-spacing: 0.5px;
        }

        .profile-pic {
            width: 100px;
            height: 100px;
            border-radius: 50%;
            margin-bottom: 1.5rem;
            display: flex;
            align-items: center;
            justify-content: center;
            font-size: 2rem;
            font-weight: 500;
            color: var(--text-color);
            background-color: #e5e7eb;
            border: 3px solid rgba(255, 255, 255, 0.1);
            box-shadow: 0 4px 10px rgba(0, 0, 0, 0.1);
            transition: transform 0.3s ease;
        }

        .profile-pic:hover {
            transform: scale(1.05);
        }

        .user-name {
            margin-bottom: 2.5rem;
            text-align: center;
            width: 100%;
            padding: 0 1rem;
        }

        .user-name div {
            font-size: 1.1rem;
            font-weight: 500;
            color: white;
            margin-bottom: 0.25rem;
            overflow: hidden;
            text-overflow: ellipsis;
            white-space: nowrap;
        }

        .sidebar ul {
            list-style: none;
            width: 100%;
            padding: 0 0.5rem;
        }

        .sidebar ul li {
            margin-bottom: 0.75rem;
        }

        .sidebar ul li a {
            color: rgba(255, 255, 255, 0.8);
            text-decoration: none;
            display: flex;
            align-items: center;
            padding: 0.875rem 1.25rem;
            border-radius: 12px;
            transition: all 0.3s ease;
            font-size: 1rem;
            cursor: pointer;
            border: 1px solid transparent;
        }

        .sidebar ul li a i {
            margin-right: 1rem;
            font-size: 1.2rem;
            width: 24px;
            text-align: center;
            transition: transform 0.3s ease;
        }

        .sidebar ul li a:hover {
            background-color: rgba(255, 255, 255, 0.1);
            color: white;
            border-color: rgba(255, 255, 255, 0.1);
        }

        .sidebar ul li a:hover i {
            transform: translateX(3px);
        }

        .sidebar ul li a.active {
            background-color: var(--primary-color);
            color: white;
            box-shadow: 0 4px 12px rgba(79, 70, 229, 0.2);
        }

        @media (max-width: 768px) {
            .sidebar {
                width: 200px;
            }

            .profile-pic {
                width: 80px;
                height: 80px;
                font-size: 1.5rem;
            }

            .sidebar ul li a {
                padding: 0.75rem 1rem;
            }
        }

        @media (max-width: 576px) {
            .sidebar {
                width: 60px;
                padding: 1rem 0.5rem;
                align-items: center;
                overflow-x: hidden;
            }

            .sidebar .logo {
                margin-bottom: 1.5rem;
                padding: 0.5rem;
            }

            .sidebar .logo span,
            .user-name,
            .sidebar ul li a span {
                display: none;
            }

            .sidebar ul {
                width: 100%;
                padding: 0;
            }

            .sidebar ul li {
                margin-bottom: 0.5rem;
                width: 100%;
            }

            .sidebar ul li a {
                justify-content: center;
                padding: 0.75rem 0.5rem;
                width: 100%;
                border-radius: 8px;
            }

            .sidebar ul li a i {
                margin-right: 0;
                font-size: 1.2rem;
            }

            .profile-pic {
                width: 40px;
                height: 40px;
                font-size: 1.2rem;
                margin-bottom: 1rem;
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
        <div class="profile-pic" style="<?php echo !empty($user_docs['profile_picture_path']) ? 'background-image: url(\'' . htmlspecialchars($user_docs['profile_picture_path']) . '\'); background-size: cover; background-position: center;' : ''; ?>">
            <?php if (empty($user_docs['profile_picture_path'])): ?>
                <?php echo htmlspecialchars(substr($firstname, 0, 1) . substr($lastname, 0, 1)); ?>
            <?php endif; ?>
        </div>
        <div class="user-name">
            <div><?php echo htmlspecialchars($lastname . ', ' . $firstname ); ?></div>
            <div><?php echo htmlspecialchars($middlename); ?></div>
        </div>
        <ul>
            <li><a onclick="showDashboard()" id="dashboardLink" class="active" href="?view=dash"><i class="fas fa-home"></i> <span>Dashboard</span></a></li>
            <li><a onclick="showApplicationForm()" id="applyLink" href="?view=Application"><i class="fas fa-file-alt"></i> <span>Apply Scholarship</span></a></li>
            <li><a onclick="showFAQs()" id="faqsLink" href="?view=faqs"><i class="fas fa-question-circle"></i> <span>FAQs & Help</span></a></li>
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
            <p><b>Track your scholarship applications and stay updated with important notices.</b></p>
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

        <!-- Claim Status Section -->
        <div class="section claim-status">
            <h2>Claim Status</h2>
            <div class="stat-card">
                <i class="fas fa-check-circle"></i>
                <p>Claim Status</p>
                <h3 class="<?php echo strtolower($claim_status) === 'claimed' ? 'approved' : 'not-submitted'; ?>">
                    <?php echo htmlspecialchars($claim_status); ?>
                </h3>
                <?php if (!empty($user_docs['claim_photo_path'])): ?>
                    <img src="<?php echo htmlspecialchars($user_docs['claim_photo_path']); ?>" alt="Claim Photo" style="max-width:100%; max-height:200px; margin:1rem 0; border-radius:8px; border:1px solid #eee;">
                <?php endif; ?>
                <?php if (!empty($users_info['claimed_at'])): ?>
                    <p><strong>Claimed At:</strong> <?php echo htmlspecialchars($users_info['claimed_at']); ?></p>
                <?php endif; ?>
            </div>
        </div>

        <!-- Important Notices Section -->
        <div class="section notices">
            <h2>Important Notices</h2>
            <ul>
                <?php if (empty($notices)): ?>
                    <li class="empty-notice">No new notices available.</li>
                <?php else: ?>
                    <?php foreach ($notices as $notice): ?>
                        <li>
                            <div class="notice-content"><?php echo htmlspecialchars($notice['message']); ?></div>
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
            <!-- Step 1: Personal Information -->
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
                        <label for="contact_no">Contact Number <span class="required">*</span></label>
                        <div class="input-group">
                            <i class="fas fa-phone"></i>
                            <input type="tel" id="contact_no" name="contact_no" class="form-control" value="<?php echo htmlspecialchars($contact_no); ?>" required <?php echo !$is_application_open ? 'disabled' : ''; ?>>
                        </div>
                    </div>
                </div>
                <div class="form-row">
                    <div class="form-group">
                        <label for="municipality">Municipality <span class="required">*</span></label>
                        <div class="input-group">
                            <i class="fas fa-map"></i>
                            <select id="municipality" name="municipality" class="form-control" required style="padding-left: 2.5rem;" <?php echo !$is_application_open ? 'disabled' : ''; ?>>
                                <option value="">Select Municipality</option>
                                <?php
                                require_once 'philippine_locations.php';
                                $municipalities = getAllMunicipalities();
                                foreach ($municipalities as $mun) {
                                    $selected = (isset($users_info['municipality']) && $users_info['municipality'] === $mun) ? 'selected' : '';
                                    echo "<option value='" . htmlspecialchars($mun) . "' $selected>" . htmlspecialchars($mun) . "</option>";
                                }
                                ?>
                            </select>
                        </div>
                    </div>
                    <div class="form-group">
                        <label for="barangay">Barangay <span class="required">*</span></label>
                        <div class="input-group">
                            <i class="fas fa-map-marker"></i>
                            <select id="barangay" name="barangay" class="form-control" required style="padding-left: 2.5rem;" <?php echo !$is_application_open ? 'disabled' : ''; ?>>
                                <option value="">Select Barangay</option>
                                <?php
                                if (isset($users_info['municipality']) && !empty($users_info['municipality'])) {
                                    $barangays = getBarangays($users_info['municipality']);
                                    foreach ($barangays as $brgy) {
                                        $selected = (isset($users_info['barangay']) && $users_info['barangay'] === $brgy) ? 'selected' : '';
                                        echo "<option value='" . htmlspecialchars($brgy) . "' $selected>" . htmlspecialchars($brgy) . "</option>";
                                    }
                                }
                                ?>
                            </select>
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

            <!-- Step 2: Residency -->
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
                            <label><input type="radio" name="registered_voter" value="guardian" <?php echo (isset($user_residency['registered_voter']) && $user_residency['registered_voter'] === 'guardian') ? 'checked' : ''; ?> <?php echo !$is_application_open ? 'disabled' : ''; ?>> Parents Only</label>
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

            <!-- Step 3: Family Background -->
            <div class="form-section" id="step3" style="display: none;">
                <h3>Family Background. N/A if none.</h3>
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

            <!-- Step 4: Documents -->
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
                <div class="form-row">
                    <div class="form-group full-width">
                        <label for="profile_picture">2x2 Picture <span class="required">*</span></label>
                        <div class="file-upload-group">
                            <input type="file" id="profile_picture" name="profile_picture" accept=".png,.jpg,.jpeg" <?php echo !$is_application_open ? 'disabled' : ''; ?>>
                            <label for="profile_picture" class="file-upload-label" <?php echo !$is_application_open ? 'disabled' : ''; ?>>
                                <i class="fas fa-upload"></i> Choose File
                            </label>
                            <span class="file-name"><?php echo !empty($user_docs['profile_picture_path']) ? basename($user_docs['profile_picture_path']) : 'No file chosen'; ?></span>
                        </div>
                        <?php if (isset($_SESSION['application_error']) && strpos($_SESSION['application_error'], 'profile_picture') !== false): ?>
                            <div class="file-error"><?php echo $_SESSION['application_error']; ?></div>
                        <?php endif; ?>
                    </div>
                </div>
                <div class="form-buttons">
                    <button type="button" class="prev-btn" onclick="prevStep(4)">Previous</button>
                    <button type="button" class="next-btn" onclick="nextStep(4)">Next</button>
                </div>
            </div>

            <!-- Step 5: Review and Submit -->
            <div class="form-section" id="step5" style="display: none;">
                <h3>Review and Submit</h3>
                <p>Please review the file carefully before uploading to ensure it complies with all content and data protection guidelines.</p>
                <h4>Disclaimer:</h4>
                <p>By uploading this file, you affirm that you have the legal right and necessary consent to share its contents. You acknowledge responsibility for ensuring that the file does not contain unauthorized, confidential, or sensitive personal information without proper consent. In compliance with the Data Privacy Act of 2012 (Republic Act No. 10173), any personal data included in this file must be handled with utmost care, lawfully obtained, and used only for legitimate purposes. The uploader bears full accountability for the legality and appropriateness of the data submitted.</p>
                <div class="form-row">
                    <div class="form-group full-width">
                        <h4>Personal Information</h4>
                        <p><strong>Lastname:</strong> <span id="review_lastname"><?php echo htmlspecialchars($lastname); ?></span></p>
                        <p><strong>Firstname:</strong> <span id="review_firstname"><?php echo htmlspecialchars($firstname); ?></span></p>
                        <p><strong>Middlename:</strong> <span id="review_middlename"><?php echo htmlspecialchars($middlename); ?></span></p>
                        <p><strong>Sex:</strong> <span id="review_sex"><?php echo htmlspecialchars($users_info['sex'] ?? 'Not provided'); ?></span></p>
                        <p><strong>Civil Status:</strong> <span id="review_civil_status"><?php echo htmlspecialchars($users_info['civil_status'] ?? 'Not provided'); ?></span></p>
                        <p><strong>Birthdate:</strong> <span id="review_birthdate"><?php echo htmlspecialchars($users_info['birthdate'] ?? 'Not provided'); ?></span></p>
                        <p><strong>Contact Number:</strong> <span id="review_contact_no"><?php echo htmlspecialchars($contact_no); ?></span></p>
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
        </form>
    </div>
    <!-- FAQs & Help Section -->
    <div class="faqs-content" id="faqsContent" style="display:none; max-width:900px; margin:0 auto;">
        <div class="header">
            <h1>FAQs & Help</h1>
        </div>
        <h4>For more Information/Questions, please contact the scholarship administrator at the following</h4>
            <h4>Email: ischobsit2@gmail.com</h4>
            <h4 style="padding-bottom: 25px;">Phone: 09123456789</h4>
        <div class="faqs-list">
            <div class="faq-item">
                <button class="faq-question" onclick="toggleFAQ(0)">How do I apply for a scholarship?</button>
                <div class="faq-answer">To apply, click on 'Apply Scholarship' in the sidebar, fill out all required information, upload the necessary documents, and submit your application before the deadline.</div>
            </div>
            <div class="faq-item">
                <button class="faq-question" onclick="toggleFAQ(1)">What documents do I need to upload?</button>
                <div class="faq-answer">You need to upload a Certificate of Registration, Certificate of Indigency, Voter's Certificate, and a 2x2 picture. Make sure each file meets the format and size requirements.</div>
            </div>
            <div class="faq-item">
                <button class="faq-question" onclick="toggleFAQ(2)">How can I check the status of my application?</button>
                <div class="faq-answer">Go to the Dashboard to view your current application status. You will also receive notifications for any updates or required actions.</div>
            </div>
            <div class="faq-item">
                <button class="faq-question" onclick="toggleFAQ(3)">What should I do if I encounter an error during file upload?</button>
                <div class="faq-answer">Ensure your files are in the correct format (PDF, PNG, JPG, JPEG) and do not exceed the maximum size of 5MB. If the problem persists, try refreshing the page or contact support.</div>
            </div>
            <div class="faq-item">
                <button class="faq-question" onclick="toggleFAQ(4)">Can I edit my application after submitting?</button>
                <div class="faq-answer">Yes, you can edit your application until the application deadline. After the deadline, editing will be disabled.</div>
            </div>
            <div class="faq-item">
                <button class="faq-question" onclick="toggleFAQ(5)">How will I know if my application is approved?</button>
                <div class="faq-answer">You will receive a notification in your dashboard and an email if your application is approved. You can also check your application status on the Dashboard.</div>
            </div>
            <div class="faq-item">
                <button class="faq-question" onclick="toggleFAQ(6)">Who do I contact for further assistance?</button>
                <div class="faq-answer">For further assistance, please use the contact information provided in the portal or reach out to your scholarship administrator.</div>
            </div>
            <div class="faq-item">
                <button class="faq-question" onclick="toggleFAQ(7)">What is the QR Code for and how do I use it?</button>
                <div class="faq-answer">The QR Code is generated when your scholarship application is approved. You must present this QR Code when claiming your scholarship. It is used by administrators to verify your identity and application status quickly and securely during the claim process. You can find your QR Code in your dashboard or in the email notification if your application is approved.</div>
            </div>
            <div class="faq-item">
                <button class="faq-question" onclick="toggleFAQ(8)">How do I claim my scholarship money?</button>
                <div class="faq-answer">To claim your scholarship money, go to the designated location and time as indicated in your approval notice or dashboard. Bring your QR Code (printed or on your device) and a valid ID. The staff will scan your QR Code to verify your identity and application status before releasing the scholarship funds.</div>
            </div>
        </div>
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
        document.getElementById('review_contact_no').textContent = document.getElementById('contact_no').value || 'Not provided';
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
        document.getElementById('faqsContent').style.display = 'none';
        document.getElementById('dashboardLink').classList.add('active');
        document.getElementById('applyLink').classList.remove('active');
        document.getElementById('faqsLink').classList.remove('active');
    }

    function showApplicationForm() {
        document.getElementById('dashboardContent').style.display = 'none';
        document.getElementById('applicationForm').style.display = 'block';
        document.getElementById('faqsContent').style.display = 'none';
        document.getElementById('applyLink').classList.add('active');
        document.getElementById('dashboardLink').classList.remove('active');
        document.getElementById('faqsLink').classList.remove('active');
        showStep(1);
    }

    function showFAQs() {
        document.getElementById('dashboardContent').style.display = 'none';
        document.getElementById('applicationForm').style.display = 'none';
        document.getElementById('faqsContent').style.display = 'block';
        document.getElementById('faqsLink').classList.add('active');
        document.getElementById('dashboardLink').classList.remove('active');
        document.getElementById('applyLink').classList.remove('active');
    }

    function toggleFAQ(idx) {
        var answers = document.querySelectorAll('.faq-answer');
        var questions = document.querySelectorAll('.faq-question');
        answers.forEach(function(ans, i) {
            if (i === idx) {
                ans.style.display = ans.style.display === 'block' ? 'none' : 'block';
                questions[i].classList.toggle('open');
            } else {
                ans.style.display = 'none';
                questions[i].classList.remove('open');
            }
        });
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

    function openClaimModal() {
        document.getElementById('claimModal').style.display = 'flex';
    }

    function closeClaimModal() {
        document.getElementById('claimModal').style.display = 'none';
    }

    // Show correct section based on ?view= parameter
    function handleViewParam() {
        const params = new URLSearchParams(window.location.search);
        const view = params.get('view');
        if (view === 'Application') {
            showApplicationForm();
        } else if (view === 'faqs') {
            showFAQs();
        } else {
            showDashboard();
        }
    }
    handleViewParam();
</script>
<style>
.faqs-content { background: #fff; border-radius: 12px; box-shadow: 0 4px 12px rgba(0,0,0,0.07); padding: 2rem; margin-top: 2rem; }
.faqs-list { max-width: 700px; margin: 0 auto; }
.faq-item { margin-bottom: 1.5rem; }
.faq-question { width: 100%; text-align: left; background: #f1f1f1; border: none; outline: none; padding: 1rem; font-size: 1.1rem; font-weight: 500; border-radius: 8px; cursor: pointer; transition: background 0.2s; }
.faq-question.open, .faq-question:hover { background: #e0e7ff; }
.faq-answer { display: none; padding: 1rem; background: #f9fafb; border-radius: 0 0 8px 8px; border-top: 1px solid #e5e7eb; margin-top: -8px; font-size: 1rem; }
</style>

<script>
// Philippine mobile number validation for all relevant fields except Tel No.
document.addEventListener('DOMContentLoaded', function() {
    function isPhilippineMobile(num) {
        return /^(09\d{9}|\+639\d{9})$/.test(num.replace(/[\s\-()]/g, ''));
    }
    function validatePhilippineMobile(input) {
        if (!isPhilippineMobile(input.value) && input.value !== '') {
            input.setCustomValidity('Please enter a valid Philippine mobile number (09XXXXXXXXX or +639XXXXXXXXX)');
            input.classList.add('invalid-ph-mobile');
        } else {
            input.setCustomValidity('');
            input.classList.remove('invalid-ph-mobile');
        }
    }
    // List of input IDs/names to validate (except Tel No.)
    const phoneFields = [
        'guardian_contact',
        'father_contact',
        'mother_contact',
        'contact_no'
    ];
    phoneFields.forEach(function(name) {
        document.querySelectorAll('input[name="'+name+'"], input#'+name).forEach(function(input) {
            if (input) {
                input.addEventListener('input', function() {
                    validatePhilippineMobile(input);
                });
                // Initial validation
                validatePhilippineMobile(input);
            }
        });
    });
});
</script>

<style>
/* Profile picture fixed size, not vertically responsive */
.profile-pic {
    width: 100px;
    height: 100px;
    min-width: 100px;
    min-height: 100px;
    max-width: 100px;
    max-height: 100px;
}
@media (max-width: 576px) {
    .profile-pic {
        width: 36px;
        height: 36px;
        min-width: 36px;
        min-height: 36px;
        max-width: 36px;
        max-height: 36px;
    }
    /* Hide profile picture in sidebar on mobile */
    .sidebar .profile-pic {
        display: none !important;
    }
}
.invalid-ph-mobile {
    border-color: var(--error-color) !important;
}
</style>

<style>
@media (max-width: 576px) {
    /* Show sidebar profile picture again */
    .sidebar .profile-pic {
        display: flex !important;
    }
    /* Hide dashboard .user-profile on mobile */
    .dashboard-content .user-profile {
        display: none !important;
    }
}
</style>

<script>
// ... existing code ...
        document.addEventListener('DOMContentLoaded', function() {
            const nameInputs = document.querySelectorAll('input[name="firstname"], input[name="lastname"], input[name="middlename"]');
            nameInputs.forEach(input => {
                input.addEventListener('input', () => validateNameInput(input));
            });

            const phoneInput = document.querySelector('input[name="contact_no"]');
            if (phoneInput) {
                phoneInput.addEventListener('input', () => validatePhoneNumber(phoneInput));
            }

            const birthdateInput = document.querySelector('input[name="birthdate"]');
            if (birthdateInput) {
                birthdateInput.addEventListener('change', () => validateBirthdate(birthdateInput));
            }

            // Only add password validation to registration password field
            const passwordInput = document.getElementById('reg-password');
            if (passwordInput) {
                passwordInput.addEventListener('input', () => validatePassword(passwordInput));
            }

            // Add dynamic municipality and barangay handling
            const municipalitySelect = document.getElementById('municipality');
            const barangaySelect = document.getElementById('barangay');

            if (municipalitySelect && barangaySelect) {
                municipalitySelect.addEventListener('change', function() {
                    const selectedMunicipality = this.value;
                    
                    // Reset barangay dropdown
                    barangaySelect.innerHTML = '<option value="">Select Barangay</option>';
                    barangaySelect.disabled = true;

                    if (selectedMunicipality) {
                        // Show loading state
                        barangaySelect.disabled = true;
                        barangaySelect.innerHTML = '<option value="">Loading...</option>';

                        // Fetch barangays for selected municipality
                        const formData = new FormData();
                        formData.append('action', 'get_barangays');
                        formData.append('municipality', selectedMunicipality);

                        fetch('login.php', {
                            method: 'POST',
                            body: formData
                        })
                        .then(response => {
                            if (!response.ok) {
                                throw new Error('Network response was not ok');
                            }
                            return response.text().then(text => {
                                try {
                                    return JSON.parse(text);
                                } catch (e) {
                                    console.error('Server response:', text);
                                    throw new Error('Invalid JSON response from server');
                                }
                            });
                        })
                        .then(data => {
                            if (data.error) {
                                throw new Error(data.error);
                            }
                            
                            // Populate barangay dropdown
                            barangaySelect.innerHTML = '<option value="">Select Barangay</option>';
                            data.forEach(barangay => {
                                const option = document.createElement('option');
                                option.value = barangay;
                                option.textContent = barangay;
                                barangaySelect.appendChild(option);
                            });
                            barangaySelect.disabled = false;
                        })
                        .catch(error => {
                            console.error('Error fetching barangays:', error);
                            barangaySelect.innerHTML = '<option value="">Error: ' + error.message + '</option>';
                            barangaySelect.disabled = true;
                        });
                    }
                });
            }
        });
// ... existing code ...
</script>

<script>
document.addEventListener('DOMContentLoaded', function() {
    // Add N/A option to voting duration dropdowns
    const votingDurationDropdowns = ['father_voting_duration', 'mother_voting_duration', 'applicant_voting_duration'];
    votingDurationDropdowns.forEach(id => {
        const select = document.getElementById(id);
        if (select) {
            const naOption = document.createElement('option');
            naOption.value = 'N/A';
            naOption.textContent = 'N/A';
            select.appendChild(naOption);
        }
    });

    // Handle registered voter radio button selection
    const registeredVoterRadios = document.querySelectorAll('input[name="registered_voter"]');
    registeredVoterRadios.forEach(radio => {
        radio.addEventListener('change', function() {
            const votingDurationDropdowns = [
                document.getElementById('father_voting_duration'),
                document.getElementById('mother_voting_duration'),
                document.getElementById('applicant_voting_duration')
            ];

            if (this.value === 'no') {
                // If "No" is selected, disable dropdowns and set to N/A
                votingDurationDropdowns.forEach(dropdown => {
                    if (dropdown) {
                        dropdown.disabled = true;
                        dropdown.value = 'N/A';
                    }
                });
            } else {
                // If "Yes" or "Guardian Only" is selected, enable dropdowns
                votingDurationDropdowns.forEach(dropdown => {
                    if (dropdown) {
                        dropdown.disabled = false;
                        // Reset to empty selection if it was N/A
                        if (dropdown.value === 'N/A') {
                            dropdown.value = '';
                        }
                    }
                });
            }
        });
    });

    // Initial state check
    const selectedVoterOption = document.querySelector('input[name="registered_voter"]:checked');
    if (selectedVoterOption && selectedVoterOption.value === 'no') {
        const votingDurationDropdowns = [
            document.getElementById('father_voting_duration'),
            document.getElementById('mother_voting_duration'),
            document.getElementById('applicant_voting_duration')
        ];
        votingDurationDropdowns.forEach(dropdown => {
            if (dropdown) {
                dropdown.disabled = true;
                dropdown.value = 'N/A';
            }
        });
    }
});
</script>

</body>
</html>