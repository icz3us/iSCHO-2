<?php
/**
 * Database Migration Script: Multi-Program Scholarship Support
 * 
 * This script adds support for multiple scholarship programs:
 * - Creates scholarship_programs table
 * - Adds program_id to users table (for admins)
 * - Adds program_id to users_info table (for applicants)
 * - Creates program_requirements table for program-specific requirements
 * - Inserts default programs: EduKalinga and Handog Edukasyon
 * 
 * USAGE:
 * - Via CLI: php migrate_multi_program_support.php
 * - Via Web Browser: Navigate to http://localhost/ischo2/migrate_multi_program_support.php
 */

// Check if running from CLI or web browser
$is_cli = php_sapi_name() === 'cli';

// Check if PDO MySQL driver is available
if (!extension_loaded('pdo_mysql')) {
    if ($is_cli) {
        echo "ERROR: PDO MySQL extension is not loaded.\n\n";
        echo "SOLUTIONS:\n";
        echo "1. Use XAMPP's PHP CLI:\n";
        echo "   C:\\xampp\\php\\php.exe migrate_multi_program_support.php\n\n";
        echo "2. Enable PDO MySQL in php.ini:\n";
        echo "   - Open C:\\xampp\\php\\php.ini\n";
        echo "   - Find: ;extension=pdo_mysql\n";
        echo "   - Remove semicolon: extension=pdo_mysql\n";
        echo "   - Restart XAMPP\n\n";
        echo "3. Run via web browser instead:\n";
        echo "   http://localhost/ischo2/migrate_multi_program_support.php\n";
    } else {
        echo "<!DOCTYPE html><html><head><title>Migration Error</title></head><body>";
        echo "<h2>ERROR: PDO MySQL extension is not loaded</h2>";
        echo "<p>Please enable the PDO MySQL extension in your php.ini file.</p>";
        echo "<p>In XAMPP, edit C:\\xampp\\php\\php.ini and uncomment: extension=pdo_mysql</p>";
        echo "</body></html>";
    }
    exit(1);
}

// Check if connection.php exists
if (!file_exists('./connect/connection.php')) {
    echo "ERROR: Connection file not found at ./connect/connection.php\n";
    echo "Please make sure you're running this script from the project root directory.\n";
    exit(1);
}

require './connect/connection.php';

// Set output format based on environment
if (!$is_cli) {
    header('Content-Type: text/html; charset=utf-8');
    echo "<!DOCTYPE html><html><head><title>Database Migration</title>";
    echo "<style>body{font-family:Arial,sans-serif;max-width:800px;margin:50px auto;padding:20px;}";
    echo ".success{color:green;}.error{color:red;}.info{color:blue;}</style></head><body>";
    echo "<h1>Database Migration: Multi-Program Support</h1>";
}

try {
    // Note: DDL statements (CREATE, ALTER) in MySQL cause implicit commits,
    // so we'll handle transactions carefully. We'll start a transaction but
    // be aware that some operations may auto-commit.
    $pdo->beginTransaction();
    
    if ($is_cli) {
        echo "Starting migration...\n";
    } else {
        echo "<p class='info'>Starting migration...</p>";
    }
    
    // 1. Create scholarship_programs table
    if ($is_cli) {
        echo "Creating scholarship_programs table...\n";
    } else {
        echo "<p>Creating scholarship_programs table...</p>";
    }
    $pdo->exec("
        CREATE TABLE IF NOT EXISTS scholarship_programs (
            id INT AUTO_INCREMENT PRIMARY KEY,
            program_name VARCHAR(255) NOT NULL UNIQUE,
            program_description TEXT DEFAULT NULL,
            is_active TINYINT(1) DEFAULT 1,
            created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
            updated_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
            INDEX idx_program_name (program_name),
            INDEX idx_is_active (is_active)
        ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci
    ");
    
    // 2. Add program_id to users table (for admins)
    if ($is_cli) {
        echo "Adding program_id to users table...\n";
    } else {
        echo "<p>Adding program_id to users table...</p>";
    }
    // Check if column exists first
    $stmt = $pdo->query("SHOW COLUMNS FROM users LIKE 'program_id'");
    if ($stmt->rowCount() == 0) {
        $pdo->exec("
            ALTER TABLE users 
            ADD COLUMN program_id INT NULL DEFAULT NULL AFTER role,
            ADD FOREIGN KEY (program_id) REFERENCES scholarship_programs(id) ON DELETE SET NULL,
            ADD INDEX idx_program_id (program_id)
        ");
    }
    
    // 3. Add program_id to users_info table (for applicants)
    if ($is_cli) {
        echo "Adding program_id to users_info table...\n";
    } else {
        echo "<p>Adding program_id to users_info table...</p>";
    }
    $stmt = $pdo->query("SHOW COLUMNS FROM users_info LIKE 'program_id'");
    if ($stmt->rowCount() == 0) {
        $pdo->exec("
            ALTER TABLE users_info 
            ADD COLUMN program_id INT NULL DEFAULT NULL AFTER user_id,
            ADD FOREIGN KEY (program_id) REFERENCES scholarship_programs(id) ON DELETE SET NULL,
            ADD INDEX idx_program_id (program_id)
        ");
    }
    
    // 4. Create program_requirements table
    if ($is_cli) {
        echo "Creating program_requirements table...\n";
    } else {
        echo "<p>Creating program_requirements table...</p>";
    }
    $pdo->exec("
        CREATE TABLE IF NOT EXISTS program_requirements (
            id INT AUTO_INCREMENT PRIMARY KEY,
            program_id INT NOT NULL,
            requirement_name VARCHAR(255) NOT NULL,
            requirement_description TEXT DEFAULT NULL,
            is_required TINYINT(1) DEFAULT 1,
            requirement_type ENUM('document', 'field', 'other') DEFAULT 'field',
            created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
            updated_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
            FOREIGN KEY (program_id) REFERENCES scholarship_programs(id) ON DELETE CASCADE,
            INDEX idx_program_id (program_id),
            INDEX idx_requirement_type (requirement_type)
        ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci
    ");
    
    // Add unique constraint to prevent duplicate requirements per program
    if ($is_cli) {
        echo "Adding unique constraint to prevent duplicate requirements...\n";
    } else {
        echo "<p>Adding unique constraint to prevent duplicate requirements...</p>";
    }
    
    // Check if unique constraint exists
    $stmt = $pdo->query("
        SELECT CONSTRAINT_NAME 
        FROM information_schema.TABLE_CONSTRAINTS 
        WHERE TABLE_SCHEMA = DATABASE() 
        AND TABLE_NAME = 'program_requirements' 
        AND CONSTRAINT_TYPE = 'UNIQUE' 
        AND CONSTRAINT_NAME = 'unique_program_requirement'
    ");
    
    if ($stmt->rowCount() == 0) {
        try {
            $pdo->exec("
                ALTER TABLE program_requirements 
                ADD UNIQUE KEY unique_program_requirement (program_id, requirement_name)
            ");
        } catch (PDOException $e) {
            // Constraint might already exist or table might not exist yet
            if ($is_cli) {
                echo "Note: Could not add unique constraint (may already exist): " . $e->getMessage() . "\n";
            } else {
                echo "<p>Note: Could not add unique constraint (may already exist)</p>";
            }
        }
    }
    
    // 5. Insert default programs
    if ($is_cli) {
        echo "Inserting default programs...\n";
    } else {
        echo "<p>Inserting default programs...</p>";
    }
    
    // Insert EduKalinga
    $stmt = $pdo->prepare("
        INSERT IGNORE INTO scholarship_programs (id, program_name, program_description, is_active) 
        VALUES (1, 'EduKalinga', 'EduKalinga Scholarship Program', 1)
    ");
    $stmt->execute();
    
    // Insert Handog Edukasyon
    $stmt = $pdo->prepare("
        INSERT IGNORE INTO scholarship_programs (id, program_name, program_description, is_active) 
        VALUES (2, 'Handog Edukasyon', 'Handog Edukasyon Scholarship Program', 1)
    ");
    $stmt->execute();
    
    // 6. Set default program_id for existing admins (assign to EduKalinga)
    if ($is_cli) {
        echo "Setting default program for existing admins...\n";
    } else {
        echo "<p>Setting default program for existing admins...</p>";
    }
    $pdo->exec("
        UPDATE users 
        SET program_id = 1 
        WHERE role = 'Admin' AND program_id IS NULL
    ");
    
    // 7. Set default program_id for existing applicants (assign to EduKalinga)
    if ($is_cli) {
        echo "Setting default program for existing applicants...\n";
    } else {
        echo "<p>Setting default program for existing applicants...</p>";
    }
    $pdo->exec("
        UPDATE users_info 
        SET program_id = 1 
        WHERE program_id IS NULL
    ");
    
    // 8. Insert default requirements for EduKalinga (existing requirements)
    // First, remove any existing duplicates for EduKalinga
    if ($is_cli) {
        echo "Cleaning up duplicate requirements for EduKalinga...\n";
    } else {
        echo "<p>Cleaning up duplicate requirements for EduKalinga...</p>";
    }
    
    // Delete existing requirements for EduKalinga to start fresh
    $pdo->exec("DELETE FROM program_requirements WHERE program_id = 1");
    
    if ($is_cli) {
        echo "Inserting default requirements for EduKalinga...\n";
    } else {
        echo "<p>Inserting default requirements for EduKalinga...</p>";
    }
    $edukalinga_requirements = [
        ['Certificate of Registration', 'COR file upload', 'document', 1],
        ['Certificate of Indigency', 'Indigency certificate file upload', 'document', 1],
        ['Voter\'s Certificate', 'Voter certificate file upload', 'document', 1],
    ];
    
    foreach ($edukalinga_requirements as $req) {
        // Check if requirement already exists to prevent duplicates
        $check_stmt = $pdo->prepare("
            SELECT id FROM program_requirements 
            WHERE program_id = 1 AND requirement_name = ?
        ");
        $check_stmt->execute([$req[0]]);
        if (!$check_stmt->fetch()) {
            $stmt = $pdo->prepare("
                INSERT INTO program_requirements (program_id, requirement_name, requirement_description, requirement_type, is_required)
                VALUES (1, ?, ?, ?, ?)
            ");
            $stmt->execute([$req[0], $req[1], $req[2], $req[3]]);
        }
    }
    
    // 9. Insert default requirements for Handog Edukasyon (can be customized later)
    // First, remove any existing duplicates for Handog Edukasyon
    if ($is_cli) {
        echo "Cleaning up duplicate requirements for Handog Edukasyon...\n";
    } else {
        echo "<p>Cleaning up duplicate requirements for Handog Edukasyon...</p>";
    }
    
    // Delete existing requirements for Handog Edukasyon to start fresh
    $pdo->exec("DELETE FROM program_requirements WHERE program_id = 2");
    
    if ($is_cli) {
        echo "Inserting default requirements for Handog Edukasyon...\n";
    } else {
        echo "<p>Inserting default requirements for Handog Edukasyon...</p>";
    }
    $handog_requirements = [
        ['Certificate of Registration', 'COR file upload', 'document', 1],
        ['Certificate of Indigency', 'Indigency certificate file upload', 'document', 1],
        ['Voter\'s Certificate', 'Voter certificate file upload', 'document', 1],
    ];
    
    foreach ($handog_requirements as $req) {
        // Check if requirement already exists to prevent duplicates
        $check_stmt = $pdo->prepare("
            SELECT id FROM program_requirements 
            WHERE program_id = 2 AND requirement_name = ?
        ");
        $check_stmt->execute([$req[0]]);
        if (!$check_stmt->fetch()) {
            $stmt = $pdo->prepare("
                INSERT INTO program_requirements (program_id, requirement_name, requirement_description, requirement_type, is_required)
                VALUES (2, ?, ?, ?, ?)
            ");
            $stmt->execute([$req[0], $req[1], $req[2], $req[3]]);
        }
    }
    
    // Commit transaction if still active
    // Note: DDL statements (CREATE, ALTER) cause implicit commits in MySQL,
    // so the transaction may have already been committed
    if ($pdo->inTransaction()) {
        $pdo->commit();
    }
    
    if ($is_cli) {
        echo "\nMigration completed successfully!\n";
        echo "Default programs created:\n";
        echo "  - EduKalinga (ID: 1)\n";
        echo "  - Handog Edukasyon (ID: 2)\n";
        echo "\nExisting admins and applicants have been assigned to EduKalinga.\n";
    } else {
        echo "<div class='success'>";
        echo "<h2>Migration Completed Successfully!</h2>";
        echo "<p><strong>Default programs created:</strong></p>";
        echo "<ul>";
        echo "<li>EduKalinga (ID: 1)</li>";
        echo "<li>Handog Edukasyon (ID: 2)</li>";
        echo "</ul>";
        echo "<p>Existing admins and applicants have been assigned to EduKalinga.</p>";
        echo "<p><a href='superadmindashboard.php'>Go to Super Admin Dashboard</a></p>";
        echo "</div></body></html>";
    }
    
} catch (PDOException $e) {
    // Rollback only if transaction is still active
    // DDL statements cause implicit commits, so transaction may already be committed
    if (isset($pdo) && $pdo->inTransaction()) {
        try {
            $pdo->rollBack();
        } catch (PDOException $rollbackError) {
            // Ignore rollback errors (transaction may have already been committed)
        }
    }
    if ($is_cli) {
        echo "\nERROR: Migration failed!\n";
        echo "Error: " . $e->getMessage() . "\n\n";
        echo "Common issues:\n";
        echo "1. Make sure MySQL is running in XAMPP\n";
        echo "2. Check that the database 'ischo' exists\n";
        echo "3. Verify database credentials in connect/connection.php\n";
        echo "4. Ensure you have proper database permissions\n";
    } else {
        echo "<div class='error'>";
        echo "<h2>ERROR: Migration Failed!</h2>";
        echo "<p><strong>Error:</strong> " . htmlspecialchars($e->getMessage()) . "</p>";
        echo "<p><strong>Common issues:</strong></p>";
        echo "<ul>";
        echo "<li>Make sure MySQL is running in XAMPP</li>";
        echo "<li>Check that the database 'ischo' exists</li>";
        echo "<li>Verify database credentials in connect/connection.php</li>";
        echo "<li>Ensure you have proper database permissions</li>";
        echo "</ul>";
        echo "</div></body></html>";
    }
    exit(1);
} catch (Exception $e) {
    if (isset($pdo) && $pdo->inTransaction()) {
        $pdo->rollBack();
    }
    if ($is_cli) {
        echo "\nERROR: Migration failed!\n";
        echo "Error: " . $e->getMessage() . "\n";
    } else {
        echo "<div class='error'>";
        echo "<h2>ERROR: Migration Failed!</h2>";
        echo "<p><strong>Error:</strong> " . htmlspecialchars($e->getMessage()) . "</p>";
        echo "</div></body></html>";
    }
    exit(1);
}

