<?php
require './route_guard.php';

use PHPMailer\PHPMailer\PHPMailer;
use PHPMailer\PHPMailer\Exception;

require 'vendor/autoload.php'; 
require_once __DIR__ . '/vendor/phpqrcode/qrlib.php';

if ($_SESSION['user_role'] !== 'Admin') {
    header('Location: login.php');
    exit;
}

// Handle AJAX request for verifying the token
if (isset($_POST['action']) && $_POST['action'] === 'verify_token') {
    $response = ['success' => false, 'message' => ''];

    if (isset($_POST['token'])) {
        $token = trim($_POST['token']);

        try {
            // Verify the token exists in the claim_tokens table
            $stmt = $pdo->prepare("SELECT user_id FROM claim_tokens WHERE token = ? AND used = 0");
            $stmt->execute([$token]);
            $result = $stmt->fetch(PDO::FETCH_ASSOC);

            if ($result) {
                $user_id = $result['user_id'];

                // Check if claim_status is already 'Claimed'
                $stmt = $pdo->prepare("SELECT claim_status FROM users_info WHERE user_id = ?");
                $stmt->execute([$user_id]);
                $claim_status = $stmt->fetchColumn();

                if ($claim_status === 'Claimed') {
                    $response['message'] = "This scholarship has already been claimed.";
                } else {
                    $response['success'] = true;
                    $response['message'] = "Token verified. Proceed to capture claim photo.";
                    $response['user_id'] = $user_id;
                }
            } else {
                $response['message'] = "Invalid or already used token.";
            }
        } catch (PDOException $e) {
            $response['message'] = "Error verifying claim: " . $e->getMessage();
        }
    } else {
        $response['message'] = "No token provided.";
    }

    // Send JSON response
    header('Content-Type: application/json');
    echo json_encode($response);
    exit;
}

// Handle photo capture and save
if (isset($_GET['view']) && $_GET['view'] === 'claim_photo' && isset($_GET['user_id'])) {
    $user_id = $_GET['user_id'];

    if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['action']) && $_POST['action'] === 'save_photo') {
        if (isset($_POST['image_data'])) {
            $image_data = $_POST['image_data'];
            $image_data = str_replace('data:image/png;base64,', '', $image_data);
            $image_data = str_replace(' ', '+', $image_data);
            $data = base64_decode($image_data);

            $file_name = 'claim_photos/' . $user_id . '_' . time() . '.png';
            if (!is_dir('claim_photos')) {
                mkdir('claim_photos', 0777, true);
            }
            file_put_contents($file_name, $data);

            try {
                $stmt = $pdo->prepare("INSERT INTO user_docs (user_id, claim_photo_path) VALUES (?, ?) ON DUPLICATE KEY UPDATE claim_photo_path = ?");
                $stmt->execute([$user_id, $file_name, $file_name]);

                // Fetch applicant details for email
                $stmt = $pdo->prepare("
                    SELECT u.firstname, u.lastname, u.email 
                    FROM users u 
                    WHERE u.id = ?
                ");
                $stmt->execute([$user_id]);
                $applicant = $stmt->fetch(PDO::FETCH_ASSOC);

                if ($applicant) {
                    $applicant_name = $applicant['firstname'] . ' ' . $applicant['lastname'];
                    $email = $applicant['email'];

                    // Update the claim_status in users_info to 'Claimed' without resetting application_status
                    $stmt = $pdo->prepare("UPDATE users_info SET claim_status = 'Claimed' WHERE user_id = ?");
                    $stmt->execute([$user_id]);

                    // Mark the token as used in claim_tokens
                    $stmt = $pdo->prepare("UPDATE claim_tokens SET used = 1, used_at = NOW() WHERE user_id = ? AND used = 0 LIMIT 1");
                    $stmt->execute([$user_id]);

                    // Send email to applicant confirming the claim
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

                        // Attach the claim photo directly using the saved file path
                        if (file_exists($file_name)) {
                            $mail->addAttachment($file_name, 'claim_photo.png');
                        }

                        $mail->isHTML(true);
                        $mail->Subject = 'Scholarship Claim Confirmation';
                        $mail->Body = "
                            <div style='font-family: Arial, sans-serif; max-width: 600px; margin: 0 auto; border: 1px solid #e0e0e0; border-radius: 8px;'>
                                <div style='background-color: #4f46e5; padding: 20px; text-align: center; border-top-left-radius: 8px; border-top-right-radius: 8px;'>
                                    <h1 style='color: #ffffff; margin: 0; font-size: 24px;'>Scholarship Claim Confirmation</h1>
                                </div>
                                <div style='padding: 30px; background-color: #ffffff;'>
                                    <p style='color: #1f2937; font-size: 16px; margin-bottom: 15px;'>Dear $applicant_name,</p>
                                    <p style='color: #1f2937; font-size: 16px; margin-bottom: 15px;'>We are pleased to confirm that your scholarship has been successfully <span style='color: #22c55e; font-weight: bold;'>claimed</span> on " . date('Y-m-d H:i:s') . ".</p>
                                    <p style='color: #1f2937; font-size: 16px; margin-bottom: 15px;'>Please find your claim photo attached to this email for your records.</p>
                                    <p style='color: #1f2937; font-size: 16px; margin-bottom: 15px;'>Thank you for completing the claim process. If you have any questions, please contact us at <a href='mailto:ischobsit@gmail.com' style='color: #4f46e5; text-decoration: none;'>ischobsit@gmail.com</a>.</p>
                                    <p style='color: #1f2937; font-size: 16px; margin-bottom: 0;'>Best regards,<br>iSCHO Admin Team</p>
                                </div>
                                <div style='background-color: #f9fafb; padding: 15px; text-align: center; border-bottom-left-radius: 8px; border-bottom-right-radius: 8px;'>
                                    <p style='color: #6b7280; font-size: 12px; margin: 0;'>© 2025 iSCHO. All rights reserved.</p>
                                </div>
                            </div>
                        ";
                        $mail->AltBody = "Dear $applicant_name,\n\nYour scholarship has been successfully claimed on " . date('Y-m-d H:i:s') . ".\nPlease find your claim photo attached to this email for your records.\n\nThank you for completing the claim process. If you have any questions, please contact us at ischobsit@gmail.com.\n\nBest regards,\niSCHO Admin Team";

                        $mail->send();
                        $_SESSION['photo_success'] = "Photo uploaded successfully! Claim process completed.";
                    } catch (Exception $e) {
                        $_SESSION['photo_error'] = "Photo saved successfully! Failed to send email: {$mail->ErrorInfo}";
                    }
                } else {
                    $_SESSION['photo_error'] = "Applicant not found.";
                }
            } catch (PDOException $e) {
                $_SESSION['photo_error'] = "Error saving photo: " . $e->getMessage();
            }

            // Redirect back to QR scanner view
            header('Location: admindashboard.php?view=qrscanner');
            exit;
        }
    }
}

// Fetch claimed applicant data for Scholarship Claiming Data view (ensure no duplicates)
$claimed_applicants = [];
if (isset($_GET['view']) && $_GET['view'] === 'claiming_data') {
    try {
        $stmt = $pdo->prepare("
            SELECT DISTINCT u.id, u.firstname, u.lastname, u.middlename, u.email, ui.claim_status, ud.claim_photo_path
            FROM users u
            LEFT JOIN users_info ui ON u.id = ui.user_id
            LEFT JOIN user_docs ud ON u.id = ud.user_id
            WHERE ui.claim_status = 'Claimed' AND ud.claim_photo_path IS NOT NULL
        ");
        $stmt->execute();
        $claimed_applicants = $stmt->fetchAll(PDO::FETCH_ASSOC);
    } catch (PDOException $e) {
        $_SESSION['claiming_data_error'] = "Error fetching claimed applicants: " . $e->getMessage();
    }
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
$under_review_applicants = 0;

try {
    // Count total applicants
    $stmt = $pdo->prepare("SELECT COUNT(*) FROM users WHERE role = 'Applicant'");
    $stmt->execute();
    $total_applicants = $stmt->fetchColumn();

    // Count approved applicants
    $stmt = $pdo->prepare("SELECT COUNT(*) FROM users_info WHERE application_status = 'Approved'");
    $stmt->execute();
    $approved_applicants = $stmt->fetchColumn();

    // Count denied applicants
    $stmt = $pdo->prepare("SELECT COUNT(*) FROM users_info WHERE application_status = 'Denied'");
    $stmt->execute();
    $denied_applicants = $stmt->fetchColumn();

    // Count under review applicants
    $stmt = $pdo->prepare("SELECT COUNT(*) FROM users_info WHERE application_status = 'Under Review'");
    $stmt->execute();
    $under_review_applicants = $stmt->fetchColumn();

} catch (PDOException $e) {
    error_log("Error: " . $e->getMessage());
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
            u.id, u.firstname, u.lastname, u.middlename, u.contact_no, u.email,
            ui.application_status,
            ui.claim_status,
            ui.municipality,
            ui.barangay,
            ui.sex AS gender,
            ui.civil_status,
            ui.birthdate,
            ui.place_of_birth,
            up.degree,
            up.course,
            up.current_college,
            ur.permanent_address,
            ur.residency_duration,
            ur.registered_voter,
            ur.father_voting_duration,
            ur.mother_voting_duration,
            ur.applicant_voting_duration,
            ur.guardian_name,
            ur.relationship,
            ur.guardian_address,
            ur.guardian_contact,
            uf.father_name,
            uf.father_address,
            uf.father_contact,
            uf.father_occupation,
            uf.father_office_address,
            uf.father_tel_no,
            uf.father_age,
            uf.father_dob,
            uf.father_citizenship,
            uf.father_religion,
            uf.mother_name,
            uf.mother_address,
            uf.mother_contact,
            uf.mother_occupation,
            uf.mother_office_address,
            uf.mother_tel_no,
            uf.mother_age,
            uf.mother_dob,
            uf.mother_citizenship,
            uf.mother_religion
        FROM users u
        LEFT JOIN users_info ui ON u.id = ui.user_id
        LEFT JOIN user_personal up ON u.id = up.user_id
        LEFT JOIN user_residency ur ON u.id = ur.user_id
        LEFT JOIN user_fam uf ON u.id = uf.user_id
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
                profile_picture_path,
                claim_photo_path 
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
            'claim_photo' => $doc['claim_photo_path'] ?? null,
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

    // Check for empty applicant data before approving
    if (isset($_POST['approve'])) {
        $applicant = null;
        foreach ($all_applicants as $a) {
            if ($a['id'] == $applicant_id) {
                $applicant = $a;
                break;
            }
        }

        if ($applicant) {
            // Get the application deadline
            $application_deadline = null;
            try {
                $stmt = $pdo->prepare("SELECT application_deadline FROM application_period ORDER BY updated_at DESC LIMIT 1");
                $stmt->execute();
                $result = $stmt->fetch(PDO::FETCH_ASSOC);
                if ($result) {
                    $application_deadline = $result['application_deadline'];
                }
            } catch (PDOException $e) {
                $_SESSION['status_update_error'] = "Error checking application deadline: " . $e->getMessage();
                header('Location: admindashboard.php?view=all' . (!empty($search_query) ? '&search=' . urlencode($search_query) : ''));
                exit;
            }

            // Validate schedule date against deadline
            if (isset($_POST['schedule_date']) && $application_deadline) {
                $schedule_date = new DateTime($_POST['schedule_date']);
                $deadline = new DateTime($application_deadline);
                
                if ($schedule_date <= $deadline) {
                    $_SESSION['status_update_error'] = "Schedule date must be after the application deadline (" . date('m/d/Y', strtotime($application_deadline)) . ").";
                    header('Location: admindashboard.php?view=all' . (!empty($search_query) ? '&search=' . urlencode($search_query) : ''));
                    exit;
                }
            }

            // Required fields from users_info
            $required_users_info = [
                'municipality', 'barangay', 'gender', 'civil_status', 'birthdate', 'place_of_birth'
            ];

            // Required fields from user_personal
            $required_user_personal = [
                'degree', 'course', 'current_college'
            ];

            // Required fields from user_residency
            $required_user_residency = [
                'permanent_address', 'residency_duration', 'registered_voter',
                'guardian_name', 'relationship', 'guardian_address', 'guardian_contact'
            ];

            // Required fields from user_fam
            $required_user_fam = [
                'father_name', 'father_address', 'father_contact', 'father_occupation',
                'father_age', 'father_dob', 'father_citizenship', 'father_religion',
                'mother_name', 'mother_address', 'mother_contact', 'mother_occupation',
                'mother_age', 'mother_dob', 'mother_citizenship', 'mother_religion'
            ];

            $missing_fields = [];
            $missing_docs = [];

            // Check users_info fields
            foreach ($required_users_info as $field) {
                if (empty($applicant[$field]) || $applicant[$field] == '-') {
                    $missing_fields[] = ucwords(str_replace('_', ' ', $field));
                }
            }

            // Check user_personal fields
            foreach ($required_user_personal as $field) {
                if (empty($applicant[$field]) || $applicant[$field] == '-') {
                    $missing_fields[] = ucwords(str_replace('_', ' ', $field));
                }
            }

            // Check user_residency fields
            foreach ($required_user_residency as $field) {
                if (empty($applicant[$field]) || $applicant[$field] == '-') {
                    $missing_fields[] = ucwords(str_replace('_', ' ', $field));
                }
            }

            // Check user_fam fields
            foreach ($required_user_fam as $field) {
                if (empty($applicant[$field]) || $applicant[$field] == '-') {
                    $missing_fields[] = ucwords(str_replace('_', ' ', $field));
                }
            }

            // Check required documents
            $documents = $applicant['documents'];
            $required_docs = ['cor', 'indigency', 'voter'];

            foreach ($required_docs as $doc) {
                if (empty($documents[$doc])) {
                    $missing_docs[] = ucwords(str_replace('_', ' ', $doc));
                }
            }

            if (!empty($missing_fields) || !empty($missing_docs)) {
                $error_message = "Cannot approve applicant due to missing information:\n";
                if (!empty($missing_fields)) {
                    $error_message .= "Missing fields: " . implode(', ', $missing_fields) . "\n";
                }
                if (!empty($missing_docs)) {
                    $error_message .= "Missing documents: " . implode(', ', $missing_docs);
                }
                $_SESSION['status_update_error'] = $error_message;
                header('Location: admindashboard.php?view=all' . (!empty($search_query) ? '&search=' . urlencode($search_query) : ''));
                exit;
            }
        }
    }

    try {
        // Check if the application_status is already 'Approved'
        $stmt = $pdo->prepare("SELECT application_status FROM users_info WHERE user_id = ?");
        $stmt->execute([$applicant_id]);
        $current_status = $stmt->fetchColumn();

        if ($status === 'Approved' && $current_status === 'Approved') {
            $_SESSION['status_update_error'] = "This applicant has already been approved.";
        } else {
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

                        // Generate unique token for QR code
                        $token = bin2hex(random_bytes(16));
                        $stmt = $pdo->prepare("INSERT INTO claim_tokens (user_id, token) VALUES (?, ?)");
                        $stmt->execute([$applicant_id, $token]);

                        // Generate QR code using phpqrcode
                        $qrCodeUrl = "  https://32bf-2001-fd8-b812-d700-9d24-2fe6-269-a01b.ngrok-free.app/ischo2/verify_claim.php?token=" . urlencode($token);
                        $qrCodePath = 'qrcodes/' . $token . '.png';
                        if (!is_dir('qrcodes')) {
                            mkdir('qrcodes', 0777, true);
                        }
                        QRcode::png($qrCodeUrl, $qrCodePath, 'L', 10, 2);

                        $mail->isHTML(true);
                        $mail->Subject = 'Scholarship Approval - Claim Your Scholarship';
                        $mail->Body = "
                            <div style='font-family: Arial, sans-serif; max-width: 600px; margin: 0 auto; border: 1px solid #e0e0e0; border-radius: 8px;'>
        <div style='background-color: #4f46e5; padding: 20px; text-align: center; border-top-left-radius: 8px; border-top-right-radius: 8px;'>
            <h1 style='color: #ffffff; margin: 0; font-size: 24px;'>Scholarship Approval</h1>
        </div>
        <div style='padding: 30px; background-color: #ffffff;'>
            <p style='color: #1f2937; font-size: 16px; margin-bottom: 15px;'>Dear $applicant_name,</p>
            <p style='color: #1f2937; font-size: 16px; margin-bottom: 15px;'>We are pleased to inform you that your scholarship application has been <span style='color: #22c55e; font-weight: bold;'>approved</span>!</p>
            <p style='color: #1f2937; font-size: 16px; margin-bottom: 15px;'>Please be informed that the Queueing Process is <b>First Come First Serve Basis!</b></p>
            <p style='color: #1f2937; font-size: 16px; margin-bottom: 15px;'>Please bring the following files:</p>
            <ol style='list-style-position: inside; margin: 0; padding: 0;'>
                <li style='padding: 8px; color: #1f2937; font-size: 16px; font-weight: bold;'>Certified Copy of your COR</li>
                <li style='padding: 8px; color: #1f2937; font-size: 16px; font-weight: bold;'>Voter's ID/Certificate</li>
                <li style='padding: 8px; color: #1f2937; font-size: 16px; font-weight: bold; margin-bottom: 15px;'>Government-issued ID & School ID</li>
            </ol>
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
            <p style='color: #1f2937; font-size: 16px; margin-bottom: 15px;'>Please present the QR code below at the meeting to claim your scholarship:</p>
            <img src='cid:qrcode' alt='QR Code' style='max-width: 200px; margin: 0 auto; display: block;'>
            <p style='color: #1f2937; font-size: 16px; margin-bottom: 15px;'>We look forward to discussing the next steps with you.</p>
            <p style='color: #1f2937; font-size: 16px; margin-bottom: 0;'>Best regards,<br>iSCHO Admin Team</p>
        </div>
        <div style='background-color: #f9fafb; padding: 15px; text-align: center; border-bottom-left-radius: 8px; border-bottom-right-radius: 8px;'>
            <p style='color: #6b7280; font-size: 12px; margin: 0;'>© 2025 iSCHO. All rights reserved.</p>
        </div>
    </div>
                        ";
                        $mail->AltBody = "Dear $applicant_name,\n\nYour scholarship application has been approved!\nPlease be available for a meeting on:\nDate: $schedule_date\nTime: $schedule_time\nPlace: $schedule_place\n\nPlease present the QR code sent to your email to claim your scholarship.\n\nBest regards,\nScholarship Admin Team";
                        $mail->addEmbeddedImage($qrCodePath, 'qrcode', 'qrcode.png');
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

                    // Insert notice into notices table with the email's plain-text body
                    try {
                        $stmt = $pdo->prepare("INSERT INTO notices (user_id, message) VALUES (?, ?)");
                        $stmt->execute([$applicant_id, $mail->AltBody]);
                        $_SESSION['notice_success'] = "Notice sent successfully!";
                    } catch (PDOException $e) {
                        $_SESSION['notice_error'] = "Failed to send notice: " . $e->getMessage();
                    }
                } catch (Exception $e) {
                    $_SESSION['email_error'] = "Failed to send email: {$mail->ErrorInfo}";
                }

                // Clean up QR code file if it exists
                if (isset($qrCodePath) && file_exists($qrCodePath)) {
                    unlink($qrCodePath);
                }
            } else {
                $_SESSION['email_error'] = "Failed to send email: Applicant email not found.";
            }
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
            $stmt = $pdo->prepare("UPDATE notices SET message = ?, updated_at = NOW() WHERE id = ?");
            $stmt->execute([$message, $notice_id]);
            if ($stmt->rowCount() === 0) {
                $_SESSION['notice_error'] = "No notice found with the provided ID.";
            } else {
                $_SESSION['notice_success'] = "Notice updated successfully!";
            }
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

// Handle AJAX request for application deadline
if (isset($_GET['ajax']) && $_GET['ajax'] === 'get_deadline') {
    header('Content-Type: application/json');
    try {
        $stmt = $pdo->prepare("SELECT application_deadline FROM application_period ORDER BY updated_at DESC LIMIT 1");
        $stmt->execute();
        $row = $stmt->fetch(PDO::FETCH_ASSOC);
        if ($row && !empty($row['application_deadline'])) {
            echo json_encode(['success' => true, 'deadline' => $row['application_deadline']]);
        } else {
            echo json_encode(['success' => false, 'message' => 'No deadline set.']);
        }
    } catch (PDOException $e) {
        echo json_encode(['success' => false, 'message' => 'Database error: ' . $e->getMessage()]);
    }
    exit;
}

$view = isset($_GET['view']) ? $_GET['view'] : 'dashboard';

// Fetch municipality statistics
$municipality_stats = [];
try {
    $stmt = $pdo->prepare("
        SELECT ui.municipality, COUNT(*) as total_count,
            SUM(CASE WHEN ui.application_status = 'Approved' THEN 1 ELSE 0 END) as approved_count,
            SUM(CASE WHEN ui.application_status = 'Denied' THEN 1 ELSE 0 END) as denied_count
        FROM users_info ui 
        JOIN users u ON ui.user_id = u.id
        WHERE ui.municipality IS NOT NULL 
            AND ui.municipality != ''
            AND u.role = 'Applicant'
        GROUP BY ui.municipality 
        ORDER BY ui.municipality ASC
    ");
    $stmt->execute();
    $municipality_stats = $stmt->fetchAll(PDO::FETCH_ASSOC);
} catch (PDOException $e) {
    error_log("Error fetching municipality statistics: " . $e->getMessage());
}

// Fetch current college statistics
$college_stats = [];
try {
    $stmt = $pdo->prepare("
        SELECT 
            up.current_college,
            COUNT(*) as total_count,
            SUM(CASE WHEN ui.application_status = 'Approved' THEN 1 ELSE 0 END) as approved_count,
            SUM(CASE WHEN ui.application_status = 'Denied' THEN 1 ELSE 0 END) as denied_count
        FROM user_personal up
        JOIN users u ON up.user_id = u.id
        JOIN users_info ui ON u.id = ui.user_id
        WHERE up.current_college IS NOT NULL 
            AND up.current_college != ''
            AND u.role = 'Applicant'
        GROUP BY up.current_college 
        ORDER BY up.current_college ASC
    ");
    $stmt->execute();
    $college_stats = $stmt->fetchAll(PDO::FETCH_ASSOC);
} catch (PDOException $e) {
    error_log("Error fetching college statistics: " . $e->getMessage());
}
?>

<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Admin Dashboard - iSCHO</title>
    <link href="https://fonts.googleapis.com/css2?family=Poppins:wght@300;400;500;600&display=swap" rel="stylesheet">
    <link href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.0.0/css/all.min.css" rel="stylesheet">
    <script src="https://cdn.jsdelivr.net/npm/chart.js"></script>
    <script src="https://cdn.jsdelivr.net/npm/jsqr@1.4.0/dist/jsQR.min.js"></script>
    <link rel="icon" type="image/png" href="./images/logo1.png">
    <link rel="stylesheet" href="adminstyles.css">
    
    <style>
    .chart-stats-section {
        margin: 2rem 0;
        padding: 1.5rem;
        background: #fff;
        border-radius: 8px;
        box-shadow: 0 2px 4px rgba(0, 0, 0, 0.1);
        overflow: hidden; /* Add this to prevent overflow issues */
    }

    .charts-flex {
        display: flex;
        gap: 2rem;
        flex-wrap: wrap;
    }

    .chart-card {
        flex: 1;
        min-width: 300px; /* Reduce minimum width for better mobile display */
        padding: 1.5rem;
        background: #fff;
        border-radius: 8px;
        box-shadow: 0 2px 4px rgba(0, 0, 0, 0.05);
    }

    .chart-container {
        position: relative;
        width: 100%;
        height: 300px !important; /* Force consistent height */
        max-height: 400px;
    }

    .chart-card h3 {
        margin-bottom: 1rem;
        color: #1f2937;
        font-size: 1.1rem;
        text-align: center;
    }

    /* Responsive adjustments */
    @media (max-width: 1200px) {
        .chart-card {
            min-width: calc(50% - 1rem); /* Two charts per row */
        }
    }

    @media (max-width: 768px) {
        .charts-flex {
            flex-direction: column;
            gap: 1.5rem;
        }
        
        .chart-card {
            min-width: 100%;
            margin: 0;
        }

        .chart-container {
            height: 250px !important; /* Slightly smaller height on mobile */
        }

        .chart-card h3 {
            font-size: 1rem;
        }
    }

    @media (max-width: 480px) {
        .chart-stats-section {
            padding: 1rem;
        }

        .chart-container {
            height: 200px !important; /* Even smaller height on very small screens */
        }
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

    .view-all-container {
        margin: 1rem 0;
        text-align: center;
    }

    .view-all-btn {
        background: none;
        border: none;
        color: #4f46e5;
        cursor: pointer;
        font-size: 0.9rem;
        padding: 0.5rem 1rem;
        display: flex;
        align-items: center;
        gap: 0.5rem;
        margin: 0 auto;
        transition: all 0.3s ease;
    }

    .view-all-btn:hover {
        color: #4338ca;
        text-decoration: underline;
    }

    .view-all-btn i {
        transition: transform 0.3s ease;
    }

    .view-all-btn.active i {
        transform: rotate(180deg);
    }

    .detailed-info {
        margin-top: 1rem;
        padding: 1.5rem;
        background: #f9fafb;
        border-radius: 8px;
        animation: slideDown 0.3s ease-out;
        max-height: 600px;
        overflow-y: auto;
        width: 100%;
    }

    .info-section {
        margin-bottom: 1.5rem;
        padding: 1.5rem;
        background: #ffffff;
        border-radius: 8px;
        box-shadow: 0 1px 3px rgba(0, 0, 0, 0.1);
        width: 100%;
    }

    .info-section:last-child {
        margin-bottom: 0;
    }

    .info-section h4 {
        color: #4f46e5;
        margin-bottom: 1rem;
        font-size: 1.1rem;
        font-weight: 600;
        padding-bottom: 0.5rem;
        border-bottom: 2px solid #e5e7eb;
    }

    .family-member {
        margin-bottom: 1.5rem;
        padding: 1.5rem;
        background: #f3f4f6;
        border-radius: 8px;
        width: 100%;
    }

    .family-member:last-child {
        margin-bottom: 0;
    }

    .family-member h5 {
        color: #4f46e5;
        margin-bottom: 1rem;
        font-size: 1rem;
        font-weight: 600;
    }

    .detailed-info p {
        margin-bottom: 0.75rem;
        line-height: 1.5;
        display: flex;
        justify-content: space-between;
        padding: 0.5rem 0;
        border-bottom: 1px solid #f3f4f6;
    }

    .detailed-info p:last-child {
        border-bottom: none;
    }

    .detailed-info p strong {
        color: #4b5563;
        min-width: 200px;
    }

    .detailed-info::-webkit-scrollbar {
        width: 8px;
    }

    .detailed-info::-webkit-scrollbar-track {
        background: #f1f1f1;
        border-radius: 4px;
    }

    .detailed-info::-webkit-scrollbar-thumb {
        background: #4f46e5;
        border-radius: 4px;
    }

    .detailed-info::-webkit-scrollbar-thumb:hover {
        background: #4338ca;
    }

    @keyframes slideDown {
        from {
            opacity: 0;
            transform: translateY(-10px);
        }
        to {
            opacity: 1;
            transform: translateY(0);
        }
    }

    /* Update modal content width */
    .modal-content {
        width: 90%;
        max-width: 1000px;
        margin: 2rem auto;
        padding: 2rem;
    }

    /* Ensure the modal can handle the wider content */
    .modal {
        padding: 2rem;
    }

    /* Make sure the applicant details section is also properly sized */
    .applicant-details {
        width: 100%;
        padding: 1rem;
    }

    /* Schedule Modal Styles */
    .schedule-modal .modal-content {
        width: 50%;
        max-width: 500px;
        margin: 2rem auto;
        padding: 1.5rem;
    }

    .schedule-modal-content h3 {
        font-size: 1.2rem;
        margin-bottom: 1.5rem;
    }

    /* Denial Modal Styles */
    .denial-modal .modal-content {
        width: 50%;
        max-width: 500px;
        margin: 2rem auto;
        padding: 1.5rem;
    }

    .denial-modal-content h3 {
        font-size: 1.2rem;
        margin-bottom: 1.5rem;
    }

    /* Add these styles */
    .stat-card {
        cursor: pointer;
        transition: transform 0.2s ease, box-shadow 0.2s ease;
    }

    .stat-card:hover {
        transform: translateY(-5px);
        box-shadow: 0 8px 16px rgba(0, 0, 0, 0.1);
    }

    .stats-modal .modal-content {
        width: 90%;
        max-width: 900px;
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

    /* Add these styles in the <style> section */
    .success-modal {
        display: none;
        position: fixed;
        top: 0;
        left: 0;
        width: 100%;
        height: 100%;
        background-color: rgba(0, 0, 0, 0.5);
        z-index: 1000;
        justify-content: center;
        align-items: center;
    }

    .success-modal-content {
        background-color: #ffffff;
        padding: 2rem;
        border-radius: 8px;
        text-align: center;
        position: relative;
        width: 90%;
        max-width: 400px;
        box-shadow: 0 4px 6px rgba(0, 0, 0, 0.1);
    }

    .success-icon {
        color: #22c55e;
        font-size: 3rem;
        margin-bottom: 1rem;
    }

    .success-modal h3 {
        color: #1f2937;
        margin-bottom: 0.5rem;
        font-size: 1.5rem;
    }

    .success-modal p {
        color: #6b7280;
        margin-bottom: 1.5rem;
    }

    .success-modal .close-btn {
        position: absolute;
        right: 1rem;
        top: 1rem;
        font-size: 1.5rem;
        color: #6b7280;
        cursor: pointer;
        background: none;
        border: none;
        padding: 0;
    }

    .success-modal .close-btn:hover {
        color: #1f2937;
    }

    .success-modal .ok-btn {
        background-color: #4f46e5;
        color: white;
        border: none;
        padding: 0.75rem 2rem;
        border-radius: 6px;
        font-size: 1rem;
        font-weight: 500;
        cursor: pointer;
        transition: background-color 0.2s;
    }

    .success-modal .ok-btn:hover {
        background-color: #4338ca;
    }

    .stat-card.under-review .icon {
        color: #f59e0b;
    }

    .stat-card.under-review h3 {
        background: linear-gradient(45deg, #f59e0b, #d97706);
        -webkit-background-clip: text;
        -webkit-text-fill-color: transparent;
    }

    /* Add these styles */
    .stat-card {
        cursor: pointer;
        transition: transform 0.2s ease, box-shadow 0.2s ease;
    }

    .deadline-info {
        margin-bottom: 2rem;
    }

    .deadline-card {
        background: linear-gradient(135deg, #4f46e5 0%, #4338ca 100%);
        color: white;
        padding: 1.5rem;
        border-radius: 12px;
        display: flex;
        align-items: center;
        gap: 1.5rem;
        box-shadow: 0 4px 6px rgba(0, 0, 0, 0.1);
    }

    .deadline-card i {
        font-size: 2rem;
        color: rgba(255, 255, 255, 0.9);
    }

    .deadline-details h3 {
        margin: 0;
        font-size: 1.2rem;
        font-weight: 500;
        color: rgba(255, 255, 255, 0.9);
    }

    .deadline-details p {
        margin: 0.5rem 0 0;
        font-size: 1.5rem;
        font-weight: 600;
        color: white;
    }

    @media (max-width: 768px) {
        .deadline-card {
            padding: 1.25rem;
        }

        .deadline-card i {
            font-size: 1.5rem;
        }

        .deadline-details h3 {
            font-size: 1rem;
        }

        .deadline-details p {
            font-size: 1.25rem;
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
            <div class="profile-pic" style="background-image: url('./images/pfp.avif'); background-size: cover; background-position: center;">
            </div>
            <div class="user-name">
                <div><?php echo htmlspecialchars($full_name); ?></div>
            </div>
            <ul>
                <li><a href="admindashboard.php?view=dashboard" id="dashboardLink" class="<?php echo $view === 'dashboard' ? 'active' : ''; ?>"><i class="fas fa-tachometer-alt"></i><span>Dashboard</span></a></li>
                <li><a href="admindashboard.php?view=all" id="allApplicantsLink" class="<?php echo $view === 'all' ? 'active' : ''; ?>"><i class="fas fa-users"></i><span>All Applicants</span></a></li>
                <li><a href="admindashboard.php?view=qrscanner" id="qrScannerLink" class="<?php echo $view === 'qrscanner' ? 'active' : ''; ?>"><i class="fas fa-qrcode"></i><span>QR Code Scanner</span></a></li>
                <li><a href="admindashboard.php?view=claiming_data" id="claimingDataLink" class="<?php echo $view === 'claiming_data' ? 'active' : ''; ?>"><i class="fas fa-award"></i><span>Scholarship Claiming Data</span></a></li>
                <li><a href="admindashboard.php?action=logout"><i class="fas fa-sign-out-alt"></i><span>Logout</span></a></li>
            </ul>
        </div>
        <div class="main-content">
            <div class="header">
                <h1>Admin Dashboard</h1>
                    <span class="username"><?php echo htmlspecialchars($full_name); ?></span>
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
                    <?php
                    $error_message = $_SESSION['status_update_error'];
                    $error_message = str_replace("\n", "<br>", htmlspecialchars($error_message));
                    echo $error_message;
                    unset($_SESSION['status_update_error']);
                    ?>
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
            <?php if (isset($_SESSION['claiming_data_error'])): ?>
                <div class="error-message">
                    <?php echo $_SESSION['claiming_data_error']; unset($_SESSION['claiming_data_error']); ?>
                </div>
            <?php endif; ?>
            <?php if (isset($_SESSION['photo_error'])): ?>
                <div class="error-message">
                    <?php echo $_SESSION['photo_error']; unset($_SESSION['photo_error']); ?>
                </div>
            <?php endif; ?>

            <!-- Dashboard View -->
            <?php if ($view === 'dashboard'): ?>
                <!-- Application Deadline Display -->
                <?php
                try {
                    $stmt = $pdo->prepare("SELECT application_deadline FROM application_period ORDER BY updated_at DESC LIMIT 1");
                    $stmt->execute();
                    $deadline_result = $stmt->fetch(PDO::FETCH_ASSOC);
                    $formatted_deadline = $deadline_result ? date('F d, Y', strtotime($deadline_result['application_deadline'])) : 'Not Set';
                ?>
                <div class="deadline-info">
                    <div class="deadline-card">
                        <i class="fas fa-calendar-alt"></i>
                        <div class="deadline-details">
                            <h3>Application Deadline</h3>
                            <p><?php echo htmlspecialchars($formatted_deadline); ?></p>
                        </div>
                    </div>
                </div>
                <?php
                } catch (PDOException $e) {
                    error_log("Error fetching deadline: " . $e->getMessage());
                }
                ?>
                <div class="stats">
                    <div class="stat-card total-applicants" onclick="openStatsModal('total-modal')">
                        <i class="fas fa-users icon"></i>
                        <h3><?php echo $total_applicants; ?></h3>
                        <p>Total Applicants</p>
                    </div>
                    <div class="stat-card under-review" onclick="openStatsModal('under-review-modal')">
                        <i class="fas fa-clock icon"></i>
                        <h3><?php echo $under_review_applicants; ?></h3>
                        <p>Pending</p>
                    </div>
                    <div class="stat-card approved" onclick="openStatsModal('approved-modal')">
                        <i class="fas fa-check-circle icon"></i>
                        <h3><?php echo $approved_applicants; ?></h3>
                        <p>Approved Applicants</p>
                    </div>
                    <div class="stat-card denied" onclick="openStatsModal('denied-modal')">
                        <i class="fas fa-times-circle icon"></i>
                        <h3><?php echo $denied_applicants; ?></h3>
                        <p>Denied Applicants</p>
                    </div>
                </div>
                <div class="chart-stats-section">
                    <h2>Chart Statistics</h2>
                    <div class="charts-flex">
                        <div class="chart-card municipality-distribution">
                            <h3>Municipality Distribution</h3>
                            <div class="chart-container" style="height: 300px;">
                                <canvas id="municipalityChart"></canvas>
                            </div>
                        </div>
                        <div class="chart-card college-distribution">
                            <h3>Current College Distribution</h3>
                            <div class="chart-container" style="height: 300px;">
                                <canvas id="collegeChart"></canvas>
                            </div>
                        </div>
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
                                    <th>Claim Status</th>
                                    <th>Actions</th>
                                </tr>
                            </thead>
                            <tbody>
                                <?php if (empty($all_applicants)): ?>
                                    <tr>
                                        <td colspan="6" style="text-align: center;">No applicants found.</td>
                                    </tr>
                                <?php else: ?>
                                    <?php foreach ($all_applicants as $applicant): ?>
                                        <tr>
                                            <td><?php echo htmlspecialchars($applicant['lastname'] . ', ' . $applicant['firstname'] . ' ' . $applicant['middlename']); ?></td>
                                            <td><?php echo htmlspecialchars($applicant['contact_no']); ?></td>
                                            <td><?php echo htmlspecialchars($applicant['email']); ?></td>
                                            <td><?php echo htmlspecialchars($applicant['application_status'] ?: 'Not Yet Submitted'); ?></td>
                                            <td><?php echo htmlspecialchars($applicant['claim_status'] ?: 'Not Claimed'); ?></td>
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

            <!-- QR Code Scanner View -->
            <?php if ($view === 'qrscanner'): ?>
                <div class="section qr-scanner-section">
                    <?php if (isset($_SESSION['photo_success'])): ?>
                        <div class="qr-message">
                            <?php echo $_SESSION['photo_success']; unset($_SESSION['photo_success']); ?>
                        </div>
                    <?php endif; ?>
                    <h2>QR Code Scanner</h2>
                    <video id="qr-video" autoplay playsinline></video>
                    <canvas id="qr-canvas"></canvas>
                    <div id="qr-result" class="qr-result">
                        <p>Scan a QR code to see the result here.</p>
                    </div>
                    <!-- Audio elements for success and error sounds -->
                    <audio id="success-sound" src="success.mp3" preload="auto"></audio>
                    <audio id="error-sound" src="error.mp3" preload="auto"></audio>
                </div>
            <?php endif; ?>

            <!-- Claim Photo View -->
            <?php if ($view === 'claim_photo' && isset($_GET['user_id'])): ?>
                <div class="section claim-photo-section">
                    <h2>Capture Claim Photo</h2>
                    <div class="video-container">
                        <video id="photo-video" autoplay playsinline></video>
                        <div class="photo-overlay">
                            <span>Center your face here</span>
                        </div>
                        <canvas id="photo-canvas" style="display: none;"></canvas>
                    </div>
                    <div class="photo-buttons">
                        <button id="capture-btn" class="capture-btn">Capture Photo</button>
                        <button id="save-btn" class="save-btn" style="display: none;">Save Photo</button>
                        <button id="retake-btn" class="retake-btn" style="display: none;">Retake Photo</button>
                    </div>
                    <div id="photo-preview" style="display: none;">
                        <img id="captured-photo" alt="Captured Photo">
                    </div>
                </div>
            <?php endif; ?>

            <!-- Scholarship Claiming Data View -->
            <?php if ($view === 'claiming_data'): ?>
                <div class="section">
                    <h2>Scholarship Claiming Data</h2>
                    <div class="table-container">
                        <table>
                            <thead>
                                <tr>
                                    <th>Name</th>
                                    <th>Email</th>
                                    <th>Claim Status</th>
                                    <th>Claim Photo</th>
                                </tr>
                            </thead>
                            <tbody>
                                <?php if (empty($claimed_applicants)): ?>
                                    <tr>
                                        <td colspan="4" style="text-align: center;">No claimed scholarships found.</td>
                                    </tr>
                                <?php else: ?>
                                    <?php foreach ($claimed_applicants as $applicant): ?>
                                        <tr>
                                            <td><?php echo htmlspecialchars($applicant['lastname'] . ', ' . $applicant['firstname'] . ' ' . $applicant['middlename']); ?></td>
                                            <td><?php echo htmlspecialchars($applicant['email']); ?></td>
                                            <td><?php echo htmlspecialchars($applicant['claim_status']); ?></td>
                                            <td>
                                                <?php if ($applicant['claim_photo_path']): ?>
                                                    <img src="<?php echo htmlspecialchars($applicant['claim_photo_path']); ?>" alt="Claim Photo" style="width: 100px; height: 100px; border-radius: 50%; object-fit: cover;">
                                                <?php else: ?>
                                                    No photo available
                                                <?php endif; ?>
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
                                <img src="<?php echo htmlspecialchars($applicant['documents']['profile_picture']); ?>" alt="Claim Photo" class="profile-pic">
                            <?php else: ?>
                                <div class="profile-pic">
                                    <?php echo htmlspecialchars($applicant['initials']); ?>
                                </div>
                            <?php endif; ?>
                            <p><strong>Name:</strong> <?php echo htmlspecialchars($applicant['lastname'] . ', ' . $applicant['firstname'] . ' ' . $applicant['middlename']); ?></p>
                            <p><strong>Contact Number:</strong> <?php echo htmlspecialchars($applicant['contact_no']); ?></p>
                            <p><strong>Email:</strong> <?php echo htmlspecialchars($applicant['email']); ?></p>
                            <p><strong>Status:</strong> <?php echo htmlspecialchars($applicant['application_status'] ?: 'Not Yet Submitted'); ?></p>
                            <p><strong>Claim Status:</strong> <?php echo htmlspecialchars($applicant['claim_status'] ?: 'Not Claimed'); ?></p>

                            <!-- View All Button -->
                            <div class="view-all-container">
                                <button class="view-all-btn" onclick="toggleDetailedInfo('detailed-info-<?php echo $applicant['id']; ?>')">
                                    View All Information <i class="fas fa-chevron-down"></i>
                                </button>
                            </div>

                            <!-- Detailed Information Section (Hidden by Default) -->
                            <div id="detailed-info-<?php echo $applicant['id']; ?>" class="detailed-info" style="display: none;">
                                <div class="info-section">
                                    <h4>Personal Information</h4>
                                    <p><strong>Birthdate:</strong> <?php echo htmlspecialchars($applicant['birthdate'] ?: '-'); ?></p>
                                    <p><strong>Gender:</strong> <?php echo htmlspecialchars($applicant['gender'] ?: '-'); ?></p>
                                    <p><strong>Civil Status:</strong> <?php echo htmlspecialchars($applicant['civil_status'] ?: '-'); ?></p>
                                    <p><strong>Place of Birth:</strong> <?php echo htmlspecialchars($applicant['place_of_birth'] ?: '-'); ?></p>
                                    <p><strong>Degree:</strong> <?php echo htmlspecialchars($applicant['degree'] ?: '-'); ?></p>
                                    <p><strong>Course:</strong> <?php echo htmlspecialchars($applicant['course'] ?: '-'); ?></p>
                                    <p><strong>Current College:</strong> <?php echo htmlspecialchars($applicant['current_college'] ?: '-'); ?></p>
                                </div>

                                <div class="info-section">
                                    <h4>Residency Information</h4>
                                    <p><strong>Permanent Address:</strong> <?php echo htmlspecialchars($applicant['permanent_address'] ?: '-'); ?></p>
                                    <p><strong>Municipality:</strong> <?php echo htmlspecialchars($applicant['municipality'] ?: '-'); ?></p>
                                    <p><strong>Barangay:</strong> <?php echo htmlspecialchars($applicant['barangay'] ?: '-'); ?></p>
                                    <p><strong>Residency Duration:</strong> <?php echo htmlspecialchars($applicant['residency_duration'] ?: '-'); ?></p>
                                    <p><strong>Registered Voter:</strong> <?php echo htmlspecialchars($applicant['registered_voter'] ?: '-'); ?></p>
                                    <p><strong>Father's Voting Duration:</strong> <?php echo htmlspecialchars($applicant['father_voting_duration'] ?: '-'); ?></p>
                                    <p><strong>Mother's Voting Duration:</strong> <?php echo htmlspecialchars($applicant['mother_voting_duration'] ?: '-'); ?></p>
                                    <p><strong>Applicant's Voting Duration:</strong> <?php echo htmlspecialchars($applicant['applicant_voting_duration'] ?: '-'); ?></p>
                                    <p><strong>Guardian Name:</strong> <?php echo htmlspecialchars($applicant['guardian_name'] ?: '-'); ?></p>
                                    <p><strong>Relationship:</strong> <?php echo htmlspecialchars($applicant['relationship'] ?: '-'); ?></p>
                                    <p><strong>Guardian Address:</strong> <?php echo htmlspecialchars($applicant['guardian_address'] ?: '-'); ?></p>
                                    <p><strong>Guardian Contact:</strong> <?php echo htmlspecialchars($applicant['guardian_contact'] ?: '-'); ?></p>
                                </div>

                                <div class="info-section">
                                    <h4>Family Background</h4>
                                    <div class="family-member">
                                        <h5>Father's Information</h5>
                                        <p><strong>Name:</strong> <?php echo htmlspecialchars($applicant['father_name'] ?: '-'); ?></p>
                                        <p><strong>Address:</strong> <?php echo htmlspecialchars($applicant['father_address'] ?: '-'); ?></p>
                                        <p><strong>Contact:</strong> <?php echo htmlspecialchars($applicant['father_contact'] ?: '-'); ?></p>
                                        <p><strong>Occupation:</strong> <?php echo htmlspecialchars($applicant['father_occupation'] ?: '-'); ?></p>
                                        <p><strong>Office Address:</strong> <?php echo htmlspecialchars($applicant['father_office_address'] ?: '-'); ?></p>
                                        <p><strong>Telephone No:</strong> <?php echo htmlspecialchars($applicant['father_tel_no'] ?: '-'); ?></p>
                                        <p><strong>Age:</strong> <?php echo htmlspecialchars($applicant['father_age'] ?: '-'); ?></p>
                                        <p><strong>Date of Birth:</strong> <?php echo htmlspecialchars($applicant['father_dob'] ?: '-'); ?></p>
                                        <p><strong>Citizenship:</strong> <?php echo htmlspecialchars($applicant['father_citizenship'] ?: '-'); ?></p>
                                        <p><strong>Religion:</strong> <?php echo htmlspecialchars($applicant['father_religion'] ?: '-'); ?></p>
                                    </div>

                                    <div class="family-member">
                                        <h5>Mother's Information</h5>
                                        <p><strong>Name:</strong> <?php echo htmlspecialchars($applicant['mother_name'] ?: '-'); ?></p>
                                        <p><strong>Address:</strong> <?php echo htmlspecialchars($applicant['mother_address'] ?: '-'); ?></p>
                                        <p><strong>Contact:</strong> <?php echo htmlspecialchars($applicant['mother_contact'] ?: '-'); ?></p>
                                        <p><strong>Occupation:</strong> <?php echo htmlspecialchars($applicant['mother_occupation'] ?: '-'); ?></p>
                                        <p><strong>Office Address:</strong> <?php echo htmlspecialchars($applicant['mother_office_address'] ?: '-'); ?></p>
                                        <p><strong>Telephone No:</strong> <?php echo htmlspecialchars($applicant['mother_tel_no'] ?: '-'); ?></p>
                                        <p><strong>Age:</strong> <?php echo htmlspecialchars($applicant['mother_age'] ?: '-'); ?></p>
                                        <p><strong>Date of Birth:</strong> <?php echo htmlspecialchars($applicant['mother_dob'] ?: '-'); ?></p>
                                        <p><strong>Citizenship:</strong> <?php echo htmlspecialchars($applicant['mother_citizenship'] ?: '-'); ?></p>
                                        <p><strong>Religion:</strong> <?php echo htmlspecialchars($applicant['mother_religion'] ?: '-'); ?></p>
                                    </div>
                                </div>

                                <div class="info-section">
                                    <h4>Documents</h4>
                                    <p><strong>Certificate of Registration:</strong> 
                                        <?php 
                                        echo isset($applicant['documents']['cor']) 
                                            ? '<button class="view-doc-btn" onclick="showDocumentModal(\'' . htmlspecialchars($applicant['documents']['cor']) . '\', \'Certificate of Registration\')">View</button>' 
                                            : '-'; 
                                        ?>
                                    </p>
                                    <p><strong>Certificate of Indigency:</strong> 
                                        <?php 
                                        echo isset($applicant['documents']['indigency']) 
                                            ? '<button class="view-doc-btn" onclick="showDocumentModal(\'' . htmlspecialchars($applicant['documents']['indigency']) . '\', \'Certificate of Indigency\')">View</button>' 
                                            : '-'; 
                                        ?>
                                    </p>
                                    <p><strong>Voter Certificate:</strong> 
                                        <?php 
                                        echo isset($applicant['documents']['voter']) 
                                            ? '<button class="view-doc-btn" onclick="showDocumentModal(\'' . htmlspecialchars($applicant['documents']['voter']) . '\', \'Voter Certificate\')">View</button>' 
                                            : '-'; 
                                        ?>
                                    </p>
                                </div>
                            </div>
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
                                            <button class="edit-notice-btn" onclick="showEditNoticeForm('editNoticeForm-<?php echo $notice['id']; ?>', '<?php echo $applicant['id']; ?>')">Edit</button>
                                            <button class="delete-notice-btn" onclick="deleteNotice('<?php echo $notice['id']; ?>')">Delete</button>
                                        </div>
                                    </div>

                                    <!-- Edit Notice Form (Hidden by Default) -->
                                    <div class="form-section" id="editNoticeForm-<?php echo $notice['id']; ?>" style="display: none;">
                                        <h3>Edit Notice</h3>
                                        <form method="POST" action="admindashboard.php?view=all<?php echo !empty($search_query) ? '&search=' . urlencode($search_query) : ''; ?>">
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

                        <!-- Modal Actions -->
                        <div class="modal-actions">
                            <button class="approve-btn" onclick="openScheduleModal('scheduleModal-<?php echo $applicant['id']; ?>')">Approve</button>
                            <button class="deny-btn" onclick="openDenialModal('denialModal-<?php echo $applicant['id']; ?>')">Deny</button>
                            <button class="notice-btn" onclick="showNoticeForm('noticeForm-<?php echo $applicant['id']; ?>')">Send Notice</button>
                        </div>

                        <!-- Send Notice Form (Hidden by Default) -->
                        <div class="form-section" id="noticeForm-<?php echo $applicant['id']; ?>">
                            <h3>Send Notice to <?php echo htmlspecialchars($applicant['firstname'] . ' ' . $applicant['lastname']); ?></h3>
                            <form method="POST" action="admindashboard.php?view=all<?php echo !empty($search_query) ? '&search=' . urlencode($search_query) : ''; ?>">
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
                <div class="modal schedule-modal" id="scheduleModal-<?php echo $applicant['id']; ?>">
                    <div class="modal-content schedule-modal-content">
                        <span class="close-btn" onclick="closeScheduleModal('scheduleModal-<?php echo $applicant['id']; ?>')">×</span>
                        <h3>Schedule Meeting</h3>
                        <form id="scheduleForm-<?php echo $applicant['id']; ?>" method="POST" action="admindashboard.php?view=all<?php echo !empty($search_query) ? '&search=' . urlencode($search_query) : ''; ?>">
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
                <div class="modal denial-modal" id="denialModal-<?php echo $applicant['id']; ?>">
                    <div class="modal-content denial-modal-content">
                        <span class="close-btn" onclick="closeDenialModal('denialModal-<?php echo $applicant['id']; ?>')">×</span>
                        <h3>Deny Application</h3>
                        <form id="denialForm-<?php echo $applicant['id']; ?>" method="POST" action="admindashboard.php?view=all<?php echo !empty($search_query) ? '&search=' . urlencode($search_query) : ''; ?>">
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

    <!-- Loading Modal HTML -->
    <div class="loading-modal" id="loading-modal" style="display: none;">
        <div class="loading-modal-content">
            <div class="spinner"></div>
            <p>Uploading, please wait...</p>
        </div>
    </div>

    <!-- Stats Modals -->
    <div id="total-modal" class="modal stats-modal">
        <div class="modal-content">
            <span class="close-btn" onclick="closeModal('total-modal')">×</span>
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
            <span class="close-btn" onclick="closeModal('approved-modal')">×</span>
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
            <span class="close-btn" onclick="closeModal('denied-modal')">×</span>
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

    <!-- Add Under Review Modal -->
    <div id="under-review-modal" class="modal stats-modal">
        <div class="modal-content">
            <span class="close-btn" onclick="closeModal('under-review-modal')">×</span>
            <h3>Pending Applicants</h3>
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
                            if ($applicant['application_status'] === 'Under Review'): ?>
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

    <!-- Success Modal HTML -->
    <div class="success-modal" id="success-modal">
        <div class="success-modal-content">
            <button class="close-btn" onclick="hideSuccessModal()">&times;</button>
            <i class="fas fa-check-circle success-icon"></i>
            <h3>Upload Successful!</h3>
            <p>The claim photo has been uploaded successfully.</p>
            <button class="ok-btn" onclick="hideSuccessModal()">OK</button>
        </div>
    </div>

    <!-- Document Viewer Modal -->
    <div id="documentModal" class="modal document-modal">
        <div class="modal-content document-modal-content">
            <span class="close-btn" onclick="closeDocumentModal()">&times;</span>
            <h3 id="documentTitle">Document Preview</h3>
            <div class="document-container">
                <div class="pdf-toolbar">
                    <div class="pdf-controls">
                        <button class="pdf-btn" onclick="zoomOut()"><i class="fas fa-search-minus"></i></button>
                        <select id="zoomLevel" onchange="setZoom(this.value)">
                            <option value="50">50%</option>
                            <option value="75">75%</option>
                            <option value="100" selected>100%</option>
                            <option value="125">125%</option>
                            <option value="150">150%</option>
                            <option value="200">200%</option>
                        </select>
                        <button class="pdf-btn" onclick="zoomIn()"><i class="fas fa-search-plus"></i></button>
                    </div>
                </div>
                <div class="pdf-viewer">
                    <iframe id="documentViewer" width="100%" height="100%" frameborder="0"></iframe>
                </div>
            </div>
        </div>
    </div>

    <script>
        document.addEventListener('DOMContentLoaded', function() {
            
            const ctxColumn = document.getElementById('applicationChart')?.getContext('2d');
            if (ctxColumn) {
                new Chart(ctxColumn, {
                    type: 'bar',
                    data: {
                        labels: ['Approved', 'Denied', 'Total'],
                        datasets: [{
                            label: 'Applications',
                            data: [<?php echo $approved_applicants; ?>, <?php echo $denied_applicants; ?>, <?php echo $total_applicants; ?>],
                            backgroundColor: [
                                'rgba(34, 197, 94, 0.8)',
                                'rgba(239, 68, 68, 0.8)',
                                'rgba(79, 70, 229, 0.8)'
                            ],
                            borderRadius: 8,
                            borderSkipped: false
                        }]
                    },
                    options: {
                        plugins: {
                            legend: { display: false },
                            tooltip: { enabled: true },
                            title: {
                                display: false
                            }
                        },
                        scales: {
                            y: {
                                beginAtZero: true,
                                ticks: { stepSize: 1 }
                            }
                        }
                    }
                });
            }

            
            const ctxPie = document.getElementById('genderChart')?.getContext('2d');
            if (ctxPie) {
                new Chart(ctxPie, {
                    type: 'doughnut',
                    data: {
                        labels: ['Male', 'Female', 'Other'],
                        datasets: [{
                            label: 'Applicants by Gender',
                            data: [<?php echo $male_count; ?>, <?php echo $female_count; ?>, <?php echo $other_count; ?>],
                            backgroundColor: [
                                'rgba(54, 162, 235, 0.8)',
                                'rgba(255, 99, 132, 0.8)',
                                'rgba(255, 206, 86, 0.8)'
                            ],
                            borderWidth: 2,
                            borderColor: '#fff'
                        }]
                    },
                    options: {
                        plugins: {
                            legend: {
                                position: 'bottom',
                                labels: { font: { size: 14, weight: 'bold' } }
                            },
                            tooltip: { enabled: true },
                            title: {
                                display: false
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

            
            const video = document.getElementById('qr-video');
            const canvasElement = document.getElementById('qr-canvas');
            const canvas = canvasElement?.getContext('2d');
            const qrResult = document.getElementById('qr-result');
            const successSound = document.getElementById('success-sound');
            const errorSound = document.getElementById('error-sound');
            let scanning = false;

            if (video && canvasElement && canvas && qrResult && successSound && errorSound) {
                async function startScanner() {
                    try {
                        const stream = await navigator.mediaDevices.getUserMedia({
                            video: { facingMode: 'environment' }
                        });
                        video.srcObject = stream;
                        video.play();
                        scanning = true;
                        qrResult.innerHTML = '<p>Scanning for QR code...</p>';
                        scanQRCode();
                    } catch (err) {
                        qrResult.innerHTML = '<p class="error">Error accessing camera: ' + err.message + '</p>';
                    }
                }

                function stopScanner() {
                    if (video.srcObject) {
                        video.srcObject.getTracks().forEach(track => track.stop());
                        video.srcObject = null;
                    }
                    scanning = false;
                }

                function scanQRCode() {
                    if (!scanning) return;

                    if (video.readyState === video.HAVE_ENOUGH_DATA) {
                        canvasElement.height = video.videoHeight;
                        canvasElement.width = video.videoWidth;
                        canvas.drawImage(video, 0, 0, canvasElement.width, canvasElement.height);
                        const imageData = canvas.getImageData(0, 0, canvasElement.width, canvasElement.height);
                        const code = jsQR(imageData.data, imageData.width, imageData.height, {
                            inversionAttempts: 'dontInvert'
                        });

                        if (code) {
                            const url = new URL(code.data);
                            const token = url.searchParams.get('token');

                            if (token) {
                                fetch('admindashboard.php', {
                                    method: 'POST',
                                    headers: {
                                        'Content-Type': 'application/x-www-form-urlencoded',
                                    },
                                    body: 'action=verify_token&token=' + encodeURIComponent(token)
                                })
                                .then(response => response.json())
                                .then(data => {
                                    if (data.success) {
                                        qrResult.classList.remove('error');
                                        qrResult.classList.add('success');
                                        qrResult.innerHTML = `<p>${data.message}</p>`;
                                        successSound.play().catch(error => {
                                            console.error('Error playing success sound:', error);
                                        });
                                        
                                        window.location.href = `admindashboard.php?view=claim_photo&user_id=${data.user_id}`;
                                    } else {
                                        qrResult.classList.remove('success');
                                        qrResult.classList.add('error');
                                        qrResult.innerHTML = `<p>${data.message}</p>`;
                                        errorSound.play().catch(error => {
                                            console.error('Error playing error sound:', error);
                                        });
                                    }
                                    stopScanner();
                                    setTimeout(() => {
                                        if (window.location.search.includes('view=qrscanner')) {
                                            startScanner();
                                        }
                                    }, 3000);
                                })
                                .catch(error => {
                                    qrResult.classList.remove('success');
                                    qrResult.classList.add('error');
                                    qrResult.innerHTML = '<p>Error verifying token: ' + error.message + '</p>';
                                    errorSound.play().catch(error => {
                                        console.error('Error playing error sound:', error);
                                    });
                                    stopScanner();
                                    setTimeout(() => {
                                        if (window.location.search.includes('view=qrscanner')) {
                                            startScanner();
                                        }
                                    }, 3000);
                                });
                            } else {
                                qrResult.classList.remove('success');
                                qrResult.classList.add('error');
                                qrResult.innerHTML = '<p>Invalid QR code: No token found.</p>';
                                errorSound.play().catch(error => {
                                    console.error('Error playing error sound:', error);
                                });
                                stopScanner();
                                setTimeout(() => {
                                    if (window.location.search.includes('view=qrscanner')) {
                                        startScanner();
                                    }
                                }, 3000);
                            }
                            return;
                        } else {
                            qrResult.innerHTML = '<p>Scanning for QR code...</p>';
                        }
                    }
                    requestAnimationFrame(scanQRCode);
                }

                
                if (window.location.search.includes('view=qrscanner')) {
                    startScanner();
                }

                
                window.addEventListener('beforeunload', () => {
                    if (video.srcObject) {
                        video.srcObject.getTracks().forEach(track => track.stop());
                    }
                });
            }

            
            const photoVideo = document.getElementById('photo-video');
            const photoCanvas = document.getElementById('photo-canvas');
            const photoContext = photoCanvas?.getContext('2d');
            const captureBtn = document.getElementById('capture-btn');
            const saveBtn = document.getElementById('save-btn');
            const retakeBtn = document.getElementById('retake-btn');
            const photoPreview = document.getElementById('photo-preview');
            const capturedPhoto = document.getElementById('captured-photo');
            let stream = null;

            async function startPhotoCapture() {
                try {
                    stream = await navigator.mediaDevices.getUserMedia({
                        video: { facingMode: 'user' }
                    });
                    photoVideo.srcObject = stream;
                    photoVideo.play();
                } catch (err) {
                    alert('Error accessing camera: ' + err.message);
                }
            }

            function stopPhotoCapture() {
                if (stream) {
                    stream.getTracks().forEach(track => track.stop());
                    stream = null;
                }
            }

            function capturePhoto() {
                if (!photoVideo || !photoCanvas || !photoContext) return;

                photoCanvas.width = photoVideo.videoWidth;
                photoCanvas.height = photoVideo.videoHeight;
                photoContext.drawImage(photoVideo, 0, 0, photoCanvas.width, photoCanvas.height);
                const imageData = photoCanvas.toDataURL('image/png');
                capturedPhoto.src = imageData;
                photoPreview.style.display = 'block';
                saveBtn.style.display = 'inline-block';
                retakeBtn.style.display = 'inline-block';
                captureBtn.style.display = 'none';
                stopPhotoCapture();
            }

            function retakePhoto() {
                photoPreview.style.display = 'none';
                saveBtn.style.display = 'none';
                retakeBtn.style.display = 'none';
                captureBtn.style.display = 'inline-block';
                startPhotoCapture();
            }

            function savePhoto() {
                const imageData = photoCanvas.toDataURL('image/png');
                showLoadingModal(); 
                fetch('admindashboard.php?view=claim_photo&user_id=<?php echo isset($_GET['user_id']) ? $_GET['user_id'] : ''; ?>', {
                    method: 'POST',
                    headers: {
                        'Content-Type': 'application/x-www-form-urlencoded',
                    },
                    body: 'action=save_photo&image_data=' + encodeURIComponent(imageData)
                })
                .then(response => response.text())
                .then(() => {
                    hideLoadingModal();
                    showSuccessModal();
                    // Add a delay before redirecting to allow the user to see the success message
                    setTimeout(() => {
                        window.location.href = 'admindashboard.php?view=qrscanner';
                    }, 3000);
                })
                .catch(error => {
                    hideLoadingModal(); 
                    alert('Error saving photo: ' + error.message);
                    retakePhoto();
                });
            }

            if (photoVideo && photoCanvas && captureBtn && saveBtn && retakeBtn && photoPreview && capturedPhoto) {
                captureBtn.addEventListener('click', capturePhoto);
                saveBtn.addEventListener('click', savePhoto);
                retakeBtn.addEventListener('click', retakePhoto);

                
                if (window.location.search.includes('view=claim_photo')) {
                    startPhotoCapture();
                }

                
                window.addEventListener('beforeunload', () => {
                    if (stream) {
                        stream.getTracks().forEach(track => track.stop());
                    }
                });
            }

            
            const qrMessage = document.querySelector('.qr-message');
            if (qrMessage && qrMessage.textContent.trim()) {
                qrMessage.style.display = 'block';
            }
        });

        function openModal(modalId) {
            const modal = document.getElementById(modalId);
            if (modal) {
                modal.style.display = 'block';
                document.body.style.overflow = 'hidden';
            }
        }

        function closeModal(modalId) {
            const modal = document.getElementById(modalId);
            if (modal) {
                modal.style.display = 'none';
                document.body.style.overflow = 'auto';
                
                const forms = modal.querySelectorAll('.form-section');
                forms.forEach(form => form.style.display = 'none');
            }
        }

        function openScheduleModal(modalId) {
            const modal = document.getElementById(modalId);
            if (modal) {
                modal.style.display = 'block';
                
                // Get the date input field
                const dateInput = modal.querySelector('input[type="date"]');
                if (dateInput) {
                    // Fetch the application deadline
                    fetch('admindashboard.php?ajax=get_deadline')
                        .then(response => response.json())
                        .then(data => {
                            if (data.success && data.deadline) {
                                // Set the minimum date to the day after the deadline
                                const deadline = new Date(data.deadline);
                                deadline.setDate(deadline.getDate() + 1);
                                const minDate = deadline.toISOString().split('T')[0];
                                dateInput.min = minDate;
                                
                                // If the current value is before the minimum date, clear it
                                if (dateInput.value && dateInput.value < minDate) {
                                    dateInput.value = '';
                                }
                            }
                        })
                        .catch(error => {
                            console.error('Error fetching deadline:', error);
                        });
                }
            }
        }

        function closeScheduleModal(modalId) {
            document.getElementById(modalId).style.display = 'none';
        }

        function openDenialModal(modalId) {
            document.getElementById(modalId).style.display = 'block';
        }

        function closeDenialModal(modalId) {
            document.getElementById(modalId).style.display = 'none';
        }

        function showNoticeForm(formId) {
            document.getElementById(formId).style.display = 'block';
            const modal = document.getElementById('modal-' + formId.split('-')[1]);
            if (modal) {
                modal.querySelector('.modal-content').classList.add('expanded');
            }
        }

        function hideNoticeForm(formId) {
            document.getElementById(formId).style.display = 'none';
            const modal = document.getElementById('modal-' + formId.split('-')[1]);
            if (modal) {
                modal.querySelector('.modal-content').classList.remove('expanded');
            }
        }

        function showEditNoticeForm(formId, applicantId) {
            document.getElementById(formId).style.display = 'block';
            const modal = document.getElementById('modal-' + applicantId);
            if (modal) modal.style.overflowY = 'auto';
        }

        function hideEditNoticeForm(formId) {
            document.getElementById(formId).style.display = 'none';
            const applicantId = formId.split('-')[1].split('-')[0];
            const modal = document.getElementById('modal-' + applicantId);
            if (modal) modal.style.overflowY = 'hidden';
        }

        function deleteNotice(noticeId) {
            if (confirm('Are you sure you want to delete this notice?')) {
                const form = document.createElement('form');
                form.method = 'POST';
                form.action = 'admindashboard.php?view=all';
                const input = document.createElement('input');
                input.type = 'hidden';
                input.name = 'delete_notice';
                input.value = '1';
                const noticeInput = document.createElement('input');
                noticeInput.type = 'hidden';
                noticeInput.name = 'notice_id';
                noticeInput.value = noticeId;
                form.appendChild(input);
                form.appendChild(noticeInput);
                document.body.appendChild(form);
                form.submit();
            }
        }

        
        function showLoadingModal() {
            const modal = document.getElementById('loading-modal');
            if (modal) {
                modal.style.display = 'flex';
            }
        }

        
        function hideLoadingModal() {
            const modal = document.getElementById('loading-modal');
            if (modal) {
                modal.style.display = 'none';
            }
        }

        // Add click-outside-to-close for all modals
        document.addEventListener('DOMContentLoaded', function() {
            document.querySelectorAll('.modal').forEach(function(modal) {
                modal.addEventListener('mousedown', function(e) {
                    if (e.target === modal) {
                        modal.classList.remove('active');
                        setTimeout(() => {
                            modal.style.display = 'none';
                            document.body.style.overflow = 'auto';
                        }, 300);
                    }
                });
            });
        });

        // Add validation for schedule date against application deadline
        document.addEventListener('DOMContentLoaded', function() {
            const scheduleForms = document.querySelectorAll('form[id^="scheduleForm-"]');
            scheduleForms.forEach(form => {
                form.addEventListener('submit', async function(e) {
                    e.preventDefault();
                    const dateInput = this.querySelector('input[type="date"]');
                    const selectedDate = new Date(dateInput.value);
                    try {
                        const response = await fetch('admindashboard.php?ajax=get_deadline');
                        const data = await response.json();
                        if (data.deadline) {
                            const deadline = new Date(data.deadline);
                            deadline.setHours(0, 0, 0, 0);
                            selectedDate.setHours(0, 0, 0, 0);
                            if (selectedDate <= deadline) {
                                alert('Schedule date must be after the application deadline (' + new Date(data.deadline).toLocaleDateString() + ').');
                                return;
                            }
                        }
                        this.submit();
                    } catch (error) {
                        console.error('Error checking deadline:', error);
                        alert('Error checking deadline. Please try again.');
                    }
                });
            });
        });

        function toggleDetailedInfo(elementId) {
            const element = document.getElementById(elementId);
            const button = element.previousElementSibling.querySelector('.view-all-btn');
            
            if (element.style.display === 'none') {
                element.style.display = 'block';
                button.classList.add('active');
                button.innerHTML = 'Hide Information <i class="fas fa-chevron-up"></i>';
            } else {
                element.style.display = 'none';
                button.classList.remove('active');
                button.innerHTML = 'View All Information <i class="fas fa-chevron-down"></i>';
            }
        }

        if (document.getElementById('municipalityChart')) {
            // Municipality Chart
            const ctxMunicipality = document.getElementById('municipalityChart').getContext('2d');
            if (ctxMunicipality) {
                const municipalityData = <?php echo json_encode($municipality_stats); ?>;
                console.log('Municipality Data:', municipalityData);
                
                new Chart(ctxMunicipality, {
                    type: 'bar',
                    data: {
                        labels: municipalityData.map(item => item.municipality),
                        datasets: [
                            {
                                label: 'Total Applicants',
                                data: municipalityData.map(item => parseInt(item.total_count) || 0),
                                backgroundColor: '#4f46e5',
                                borderColor: '#4338ca',
                                borderWidth: 1,
                                maxBarThickness: 30,
                                barPercentage: 0.8,
                                categoryPercentage: 0.9
                            },
                            {
                                label: 'Approved',
                                data: municipalityData.map(item => parseInt(item.approved_count) || 0),
                                backgroundColor: '#22c55e',
                                borderColor: '#16a34a',
                                borderWidth: 1,
                                maxBarThickness: 30,
                                barPercentage: 0.8,
                                categoryPercentage: 0.9
                            },
                            {
                                label: 'Denied',
                                data: municipalityData.map(item => parseInt(item.denied_count) || 0),
                                backgroundColor: '#ef4444',
                                borderColor: '#dc2626',
                                borderWidth: 1,
                                maxBarThickness: 30,
                                barPercentage: 0.8,
                                categoryPercentage: 0.9
                            }
                        ]
                    },
                    options: {
                        responsive: true,
                        maintainAspectRatio: false,
                        layout: {
                            padding: {
                                left: 10,
                                right: 10,
                                top: 20,
                                bottom: 10
                            }
                        },
                        scales: {
                            y: {
                                beginAtZero: true,
                                ticks: {
                                    stepSize: 1,
                                    font: {
                                        size: 10
                                    }
                                },
                                grid: {
                                    display: true
                                }
                            },
                            x: {
                                ticks: {
                                    maxRotation: 45,
                                    minRotation: 45,
                                    font: {
                                        size: 10
                                    },
                                    autoSkip: true,
                                    maxTicksLimit: 10
                                },
                                grid: {
                                    display: false
                                }
                            }
                        },
                        plugins: {
                            legend: {
                                display: true,
                                position: 'top',
                                labels: {
                                    font: {
                                        size: 12
                                    },
                                    usePointStyle: true,
                                    padding: 20
                                }
                            },
                            tooltip: {
                                enabled: true,
                                backgroundColor: 'rgba(0, 0, 0, 0.8)',
                                titleFont: {
                                    size: 12
                                },
                                bodyFont: {
                                    size: 12
                                },
                                padding: 10,
                                callbacks: {
                                    label: function(context) {
                                        return context.dataset.label + ': ' + context.parsed.y;
                                    }
                                }
                            }
                        }
                    }
                });
            }

            // College Chart
            const ctxCollege = document.getElementById('collegeChart').getContext('2d');
            if (ctxCollege) {
                const collegeData = <?php echo json_encode($college_stats); ?>;
                console.log('College Data:', collegeData);
                
                new Chart(ctxCollege, {
                    type: 'bar',
                    data: {
                        labels: collegeData.map(item => item.current_college),
                        datasets: [
                            {
                                label: 'Total Applicants',
                                data: collegeData.map(item => parseInt(item.total_count) || 0),
                                backgroundColor: '#4f46e5',
                                borderColor: '#4338ca',
                                borderWidth: 1,
                                maxBarThickness: 30,
                                barPercentage: 0.8,
                                categoryPercentage: 0.9
                            },
                            {
                                label: 'Approved',
                                data: collegeData.map(item => parseInt(item.approved_count) || 0),
                                backgroundColor: '#22c55e',
                                borderColor: '#16a34a',
                                borderWidth: 1,
                                maxBarThickness: 30,
                                barPercentage: 0.8,
                                categoryPercentage: 0.9
                            },
                            {
                                label: 'Denied',
                                data: collegeData.map(item => parseInt(item.denied_count) || 0),
                                backgroundColor: '#ef4444',
                                borderColor: '#dc2626',
                                borderWidth: 1,
                                maxBarThickness: 30,
                                barPercentage: 0.8,
                                categoryPercentage: 0.9
                            }
                        ]
                    },
                    options: {
                        responsive: true,
                        maintainAspectRatio: false,
                        layout: {
                            padding: {
                                left: 10,
                                right: 10,
                                top: 20,
                                bottom: 10
                            }
                        },
                        scales: {
                            y: {
                                beginAtZero: true,
                                ticks: {
                                    stepSize: 1,
                                    font: {
                                        size: 10
                                    }
                                },
                                grid: {
                                    display: true
                                }
                            },
                            x: {
                                ticks: {
                                    maxRotation: 45,
                                    minRotation: 45,
                                    font: {
                                        size: 10
                                    },
                                    autoSkip: true,
                                    maxTicksLimit: 10
                                },
                                grid: {
                                    display: false
                                }
                            }
                        },
                        plugins: {
                            legend: {
                                display: true,
                                position: 'top',
                                labels: {
                                    font: {
                                        size: 12
                                    },
                                    usePointStyle: true,
                                    padding: 20
                                }
                            },
                            tooltip: {
                                enabled: true,
                                backgroundColor: 'rgba(0, 0, 0, 0.8)',
                                titleFont: {
                                    size: 12
                                },
                                bodyFont: {
                                    size: 12
                                },
                                padding: 10,
                                callbacks: {
                                    label: function(context) {
                                        return context.dataset.label + ': ' + context.parsed.y;
                                    }
                                }
                            }
                        }
                    }
                });
            }
        }

        // Add these functions
        function openStatsModal(modalType) {
            const modal = document.getElementById(modalType);
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
        window.onclick = function(event) {
            if (event.target.classList.contains('modal')) {
                event.target.style.display = 'none';
                document.body.style.overflow = 'auto';
            }
        }

        function showSuccessModal() {
            const modal = document.getElementById('success-modal');
            if (modal) {
                modal.style.display = 'flex';
            }
        }

        function hideSuccessModal() {
            const modal = document.getElementById('success-modal');
            if (modal) {
                modal.style.display = 'none';
            }
        }

        // Modify the existing upload success handler to show both modals in sequence
        function handleUploadSuccess() {
            hideLoadingModal();
            showSuccessModal();
        }

        // Add click-outside-to-close for success modal
        document.addEventListener('DOMContentLoaded', function() {
            const successModal = document.getElementById('success-modal');
            if (successModal) {
                successModal.addEventListener('click', function(e) {
                    if (e.target === this) {
                        hideSuccessModal();
                    }
                });
            }
        });

        // Modify the photo upload handler JavaScript
        async function uploadPhoto() {
            showLoadingModal();
            const canvas = document.getElementById('photo-canvas');
            const imageData = canvas.toDataURL('image/png');

            try {
                const response = await fetch(window.location.href, {
                    method: 'POST',
                    headers: {
                        'Content-Type': 'application/x-www-form-urlencoded',
                    },
                    body: 'action=save_photo&image_data=' + encodeURIComponent(imageData)
                });

                if (response.ok) {
                    hideLoadingModal();
                    showSuccessModal();
                    // Add a delay before redirecting to allow the user to see the success message
                    setTimeout(() => {
                        window.location.href = 'admindashboard.php?view=qrscanner';
                    }, 2000);
                } else {
                    hideLoadingModal();
                    alert('Failed to upload photo. Please try again.');
                }
            } catch (error) {
                hideLoadingModal();
                alert('Error uploading photo: ' + error.message);
            }
        }

        // Initialize charts when document is ready
        document.addEventListener('DOMContentLoaded', function() {
            // Municipality Chart
            const ctxMunicipality = document.getElementById('municipalityChart')?.getContext('2d');
            if (ctxMunicipality) {
                const municipalityData = <?php echo json_encode($municipality_stats); ?>;
                console.log('Municipality Data:', municipalityData);
                
                new Chart(ctxMunicipality, {
                    type: 'bar',
                    data: {
                        labels: municipalityData.map(item => item.municipality),
                        datasets: [
                            {
                                label: 'Total Applicants',
                                data: municipalityData.map(item => parseInt(item.total_count) || 0),
                                backgroundColor: '#4f46e5',
                                borderColor: '#4338ca',
                                borderWidth: 1,
                                maxBarThickness: 30,
                                barPercentage: 0.8,
                                categoryPercentage: 0.9
                            },
                            {
                                label: 'Approved',
                                data: municipalityData.map(item => parseInt(item.approved_count) || 0),
                                backgroundColor: '#22c55e',
                                borderColor: '#16a34a',
                                borderWidth: 1,
                                maxBarThickness: 30,
                                barPercentage: 0.8,
                                categoryPercentage: 0.9
                            },
                            {
                                label: 'Denied',
                                data: municipalityData.map(item => parseInt(item.denied_count) || 0),
                                backgroundColor: '#ef4444',
                                borderColor: '#dc2626',
                                borderWidth: 1,
                                maxBarThickness: 30,
                                barPercentage: 0.8,
                                categoryPercentage: 0.9
                            }
                        ]
                    },
                    options: {
                        responsive: true,
                        maintainAspectRatio: false,
                        layout: {
                            padding: {
                                left: 10,
                                right: 10,
                                top: 20,
                                bottom: 10
                            }
                        },
                        scales: {
                            y: {
                                beginAtZero: true,
                                ticks: {
                                    stepSize: 1,
                                    font: {
                                        size: 10
                                    }
                                },
                                grid: {
                                    display: true
                                }
                            },
                            x: {
                                ticks: {
                                    maxRotation: 45,
                                    minRotation: 45,
                                    font: {
                                        size: 10
                                    },
                                    autoSkip: true,
                                    maxTicksLimit: 10
                                },
                                grid: {
                                    display: false
                                }
                            }
                        },
                        plugins: {
                            legend: {
                                display: true,
                                position: 'top',
                                labels: {
                                    font: {
                                        size: 12
                                    },
                                    usePointStyle: true,
                                    padding: 20
                                }
                            },
                            tooltip: {
                                enabled: true,
                                backgroundColor: 'rgba(0, 0, 0, 0.8)',
                                titleFont: {
                                    size: 12
                                },
                                bodyFont: {
                                    size: 12
                                },
                                padding: 10,
                                callbacks: {
                                    label: function(context) {
                                        return context.dataset.label + ': ' + context.parsed.y;
                                    }
                                }
                            }
                        }
                    }
                });
            }

            // College Chart
            const ctxCollege = document.getElementById('collegeChart')?.getContext('2d');
            if (ctxCollege) {
                const collegeData = <?php echo json_encode($college_stats); ?>;
                console.log('College Data:', collegeData);
                
                new Chart(ctxCollege, {
                    type: 'bar',
                    data: {
                        labels: collegeData.map(item => item.current_college),
                        datasets: [
                            {
                                label: 'Total Applicants',
                                data: collegeData.map(item => parseInt(item.total_count) || 0),
                                backgroundColor: '#4f46e5',
                                borderColor: '#4338ca',
                                borderWidth: 1,
                                maxBarThickness: 30,
                                barPercentage: 0.8,
                                categoryPercentage: 0.9
                            },
                            {
                                label: 'Approved',
                                data: collegeData.map(item => parseInt(item.approved_count) || 0),
                                backgroundColor: '#22c55e',
                                borderColor: '#16a34a',
                                borderWidth: 1,
                                maxBarThickness: 30,
                                barPercentage: 0.8,
                                categoryPercentage: 0.9
                            },
                            {
                                label: 'Denied',
                                data: collegeData.map(item => parseInt(item.denied_count) || 0),
                                backgroundColor: '#ef4444',
                                borderColor: '#dc2626',
                                borderWidth: 1,
                                maxBarThickness: 30,
                                barPercentage: 0.8,
                                categoryPercentage: 0.9
                            }
                        ]
                    },
                    options: {
                        responsive: true,
                        maintainAspectRatio: false,
                        layout: {
                            padding: {
                                left: 10,
                                right: 10,
                                top: 20,
                                bottom: 10
                            }
                        },
                        scales: {
                            y: {
                                beginAtZero: true,
                                ticks: {
                                    stepSize: 1,
                                    font: {
                                        size: 10
                                    }
                                },
                                grid: {
                                    display: true
                                }
                            },
                            x: {
                                ticks: {
                                    maxRotation: 45,
                                    minRotation: 45,
                                    font: {
                                        size: 10
                                    },
                                    autoSkip: true,
                                    maxTicksLimit: 10
                                },
                                grid: {
                                    display: false
                                }
                            }
                        },
                        plugins: {
                            legend: {
                                display: true,
                                position: 'top',
                                labels: {
                                    font: {
                                        size: 12
                                    },
                                    usePointStyle: true,
                                    padding: 20
                                }
                            },
                            tooltip: {
                                enabled: true,
                                backgroundColor: 'rgba(0, 0, 0, 0.8)',
                                titleFont: {
                                    size: 12
                                },
                                bodyFont: {
                                    size: 12
                                },
                                padding: 10,
                                callbacks: {
                                    label: function(context) {
                                        return context.dataset.label + ': ' + context.parsed.y;
                                    }
                                }
                            }
                        }
                    }
                });
            }
        });

        let currentZoom = 100;
        const zoomStep = 25;
        const maxZoom = 200;
        const minZoom = 50;

        function zoomIn() {
            if (currentZoom < maxZoom) {
                currentZoom += zoomStep;
                updateZoom();
            }
        }

        function zoomOut() {
            if (currentZoom > minZoom) {
                currentZoom -= zoomStep;
                updateZoom();
            }
        }

        function resetZoom() {
            currentZoom = 100;
            updateZoom();
        }

        function updateZoom() {
            const viewer = document.getElementById('documentViewer');
            viewer.style.width = `${currentZoom}%`;
        }

        function showDocumentModal(documentUrl, title) {
            const modal = document.getElementById('documentModal');
            const viewer = document.getElementById('documentViewer');
            const docTitle = document.getElementById('documentTitle');
            
            docTitle.textContent = title;
            viewer.src = documentUrl;
            document.getElementById('zoomLevel').value = '100';
            modal.style.display = 'block';
            document.body.style.overflow = 'hidden';
        }

        function setZoom(value) {
            const viewer = document.getElementById('documentViewer');
            viewer.style.width = value + '%';
            if (value > 100) {
                viewer.style.transform = `scale(${value/100})`;
                viewer.style.transformOrigin = 'top left';
            } else {
                viewer.style.transform = 'none';
            }
        }

        function zoomIn() {
            const select = document.getElementById('zoomLevel');
            const currentIndex = select.selectedIndex;
            if (currentIndex < select.options.length - 1) {
                select.selectedIndex = currentIndex + 1;
                setZoom(select.value);
            }
        }

        function zoomOut() {
            const select = document.getElementById('zoomLevel');
            const currentIndex = select.selectedIndex;
            if (currentIndex > 0) {
                select.selectedIndex = currentIndex - 1;
                setZoom(select.value);
            }
        }

        function closeDocumentModal() {
            const modal = document.getElementById('documentModal');
            const viewer = document.getElementById('documentViewer');
            
            viewer.src = '';
            modal.style.display = 'none';
            document.body.style.overflow = 'auto';
        }

        // Close document modal when clicking outside
        document.addEventListener('click', function(event) {
            const modal = document.getElementById('documentModal');
            if (event.target === modal) {
                closeDocumentModal();
            }
        });
    </script>
</body>
</html>