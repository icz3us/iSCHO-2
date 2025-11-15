-- iSCHO Database Schema
-- Integrated Scholarship Application Portal Database
-- Created based on PHP backend analysis

-- Create database (uncomment if needed)
-- CREATE DATABASE IF NOT EXISTS ischo2 CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci;
-- USE ischo2;

-- Users table (main user authentication and basic info)
CREATE TABLE IF NOT EXISTS users (
    id INT AUTO_INCREMENT PRIMARY KEY,
    firstname VARCHAR(100) NOT NULL,
    lastname VARCHAR(100) NOT NULL,
    middlename VARCHAR(100) DEFAULT NULL,
    contact_no VARCHAR(20) NOT NULL,
    email VARCHAR(255) UNIQUE NOT NULL,
    password VARCHAR(255) NOT NULL,
    role ENUM('Applicant', 'Admin', 'Superadmin') NOT NULL DEFAULT 'Applicant',
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    updated_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    INDEX idx_email (email),
    INDEX idx_role (role)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- Users info table (extended applicant information)
CREATE TABLE IF NOT EXISTS users_info (
    id INT AUTO_INCREMENT PRIMARY KEY,
    user_id INT NOT NULL,
    municipality VARCHAR(100) DEFAULT NULL,
    barangay VARCHAR(100) DEFAULT NULL,
    sex ENUM('male', 'female') DEFAULT NULL,
    civil_status ENUM('single', 'married', 'divorced', 'widowed') DEFAULT NULL,
    birthdate DATE DEFAULT NULL,
    place_of_birth VARCHAR(255) DEFAULT NULL,
    application_status ENUM('Submitted', 'Under Review', 'Approved', 'Denied') DEFAULT NULL,
    claim_status ENUM('Claimed', 'Not Claimed') DEFAULT 'Not Claimed',
    claimed_at TIMESTAMP NULL DEFAULT NULL,
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    updated_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    FOREIGN KEY (user_id) REFERENCES users(id) ON DELETE CASCADE,
    INDEX idx_user_id (user_id),
    INDEX idx_application_status (application_status),
    INDEX idx_claim_status (claim_status)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- User personal/academic information
CREATE TABLE IF NOT EXISTS user_personal (
    id INT AUTO_INCREMENT PRIMARY KEY,
    user_id INT NOT NULL,
    degree ENUM('Bachelor of Science', 'Bachelor of Arts', 'Bachelor of Fine Arts', 'Bachelor of Business Administration') DEFAULT NULL,
    course VARCHAR(255) DEFAULT NULL,
    current_college VARCHAR(255) DEFAULT NULL,
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    updated_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    FOREIGN KEY (user_id) REFERENCES users(id) ON DELETE CASCADE,
    INDEX idx_user_id (user_id)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- User residency information
CREATE TABLE IF NOT EXISTS user_residency (
    id INT AUTO_INCREMENT PRIMARY KEY,
    user_id INT NOT NULL,
    province VARCHAR(100) DEFAULT NULL,
    municipality VARCHAR(100) DEFAULT NULL,
    barangay VARCHAR(100) DEFAULT NULL,
    zip_code VARCHAR(10) DEFAULT NULL,
    current_address TEXT DEFAULT NULL,
    permanent_address TEXT DEFAULT NULL,
    years_in_current_address INT DEFAULT NULL,
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    updated_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    FOREIGN KEY (user_id) REFERENCES users(id) ON DELETE CASCADE,
    INDEX idx_user_id (user_id)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- User family information
CREATE TABLE IF NOT EXISTS user_fam (
    id INT AUTO_INCREMENT PRIMARY KEY,
    user_id INT NOT NULL,
    father_name VARCHAR(255) DEFAULT NULL,
    father_occupation VARCHAR(255) DEFAULT NULL,
    father_contact VARCHAR(20) DEFAULT NULL,
    mother_name VARCHAR(255) DEFAULT NULL,
    mother_occupation VARCHAR(255) DEFAULT NULL,
    mother_contact VARCHAR(20) DEFAULT NULL,
    guardian_name VARCHAR(255) DEFAULT NULL,
    guardian_relationship VARCHAR(100) DEFAULT NULL,
    guardian_occupation VARCHAR(255) DEFAULT NULL,
    guardian_contact VARCHAR(20) DEFAULT NULL,
    family_income DECIMAL(15,2) DEFAULT NULL,
    number_of_siblings INT DEFAULT NULL,
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    updated_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    FOREIGN KEY (user_id) REFERENCES users(id) ON DELETE CASCADE,
    INDEX idx_user_id (user_id)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- User documents (file uploads)
CREATE TABLE IF NOT EXISTS user_docs (
    id INT AUTO_INCREMENT PRIMARY KEY,
    user_id INT NOT NULL,
    cor_file_path VARCHAR(500) DEFAULT NULL,
    indigency_file_path VARCHAR(500) DEFAULT NULL,
    voter_file_path VARCHAR(500) DEFAULT NULL,
    profile_picture_path VARCHAR(500) DEFAULT NULL,
    claim_photo_path VARCHAR(500) DEFAULT NULL,
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    updated_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    FOREIGN KEY (user_id) REFERENCES users(id) ON DELETE CASCADE,
    INDEX idx_user_id (user_id)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- OTP verifications for registration
CREATE TABLE IF NOT EXISTS otp_verifications (
    id INT AUTO_INCREMENT PRIMARY KEY,
    email VARCHAR(255) NOT NULL,
    otp VARCHAR(10) NOT NULL,
    user_id INT NULL DEFAULT NULL,
    verified TINYINT(1) DEFAULT 0,
    expires_at TIMESTAMP NOT NULL,
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    INDEX idx_email (email),
    INDEX idx_otp (otp),
    INDEX idx_expires_at (expires_at),
    INDEX idx_verified (verified)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- Password reset tokens
CREATE TABLE IF NOT EXISTS password_reset_tokens (
    id INT AUTO_INCREMENT PRIMARY KEY,
    user_id INT NOT NULL,
    token VARCHAR(255) NOT NULL,
    used TINYINT(1) DEFAULT 0,
    expires_at TIMESTAMP NOT NULL,
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    used_at TIMESTAMP NULL DEFAULT NULL,
    FOREIGN KEY (user_id) REFERENCES users(id) ON DELETE CASCADE,
    INDEX idx_token (token),
    INDEX idx_user_id (user_id),
    INDEX idx_expires_at (expires_at),
    INDEX idx_used (used)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- Application period management
CREATE TABLE IF NOT EXISTS application_period (
    id INT AUTO_INCREMENT PRIMARY KEY,
    application_deadline DATE NOT NULL,
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    updated_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    INDEX idx_deadline (application_deadline)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- Notices/announcements
CREATE TABLE IF NOT EXISTS notices (
    id INT AUTO_INCREMENT PRIMARY KEY,
    user_id INT NOT NULL,
    message TEXT NOT NULL,
    image_path VARCHAR(500) DEFAULT NULL,
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    updated_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    FOREIGN KEY (user_id) REFERENCES users(id) ON DELETE CASCADE,
    INDEX idx_user_id (user_id),
    INDEX idx_created_at (created_at)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- Claim tokens for scholarship claiming
CREATE TABLE IF NOT EXISTS claim_tokens (
    id INT AUTO_INCREMENT PRIMARY KEY,
    user_id INT NOT NULL,
    token VARCHAR(255) NOT NULL,
    used TINYINT(1) DEFAULT 0,
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    used_at TIMESTAMP NULL DEFAULT NULL,
    FOREIGN KEY (user_id) REFERENCES users(id) ON DELETE CASCADE,
    INDEX idx_user_id (user_id),
    INDEX idx_token (token),
    INDEX idx_used (used)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- Secret keys for superadmin operations
CREATE TABLE IF NOT EXISTS secret_keys (
    id INT AUTO_INCREMENT PRIMARY KEY,
    user_id INT NOT NULL,
    secret_key VARCHAR(500) NOT NULL,
    iv VARCHAR(500) NOT NULL,
    used TINYINT(1) DEFAULT 0,
    expires_at TIMESTAMP NOT NULL,
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    FOREIGN KEY (user_id) REFERENCES users(id) ON DELETE CASCADE,
    INDEX idx_user_id (user_id),
    INDEX idx_expires_at (expires_at),
    INDEX idx_used (used)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- Insert default superadmin user (password: Admin123!)
INSERT IGNORE INTO users (id, firstname, lastname, middlename, contact_no, email, password, role) 
VALUES (1, 'Super', 'Admin', '', '09123456789', 'admin@ischo.com', '$2y$10$92IXUNpkjO0rOQ5byMi.Ye4oKoEa3Ro9llC/.og/at2.uheWG/igi', 'Superadmin');

-- Insert sample application period (optional)
INSERT IGNORE INTO application_period (application_deadline) VALUES ('2025-12-31');
