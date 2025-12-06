<?php
/**
 * Database Migration: Add Academic Year Support
 * Adds academic_year, application_start_date, and application_end_date fields to scholarship_programs table
 */

// Check if running from CLI or web
$is_cli = php_sapi_name() === 'cli';

// Check if connection.php exists
if (!file_exists('./connect/connection.php')) {
    if ($is_cli) {
        echo "ERROR: Connection file not found at ./connect/connection.php\n";
        echo "Please make sure you're running this script from the project root directory.\n";
    } else {
        echo "<!DOCTYPE html><html><head><title>Database Migration</title>";
        echo "<style>body{font-family:Arial,sans-serif;max-width:800px;margin:50px auto;padding:20px;}";
        echo ".success{color:green;}.error{color:red;}.info{color:blue;}</style></head><body>";
        echo "<h1>Database Migration: Academic Year Support</h1>";
        echo "<p class='error'>ERROR: Connection file not found at ./connect/connection.php</p>";
        echo "<p>Please make sure you're running this script from the project root directory.</p></body></html>";
    }
    exit(1);
}

require './connect/connection.php';

// Set output format based on environment
if (!$is_cli) {
    header('Content-Type: text/html; charset=utf-8');
    echo "<!DOCTYPE html><html><head><title>Database Migration</title>";
    echo "<style>body{font-family:Arial,sans-serif;max-width:800px;margin:50px auto;padding:20px;}";
    echo ".success{color:green;}.error{color:red;}.info{color:blue;}</style></head><body>";
    echo "<h1>Database Migration: Academic Year Support</h1>";
}

try {
    $pdo->beginTransaction();
    
    if ($is_cli) {
        echo "Starting migration...\n";
    } else {
        echo "<p class='info'>Starting migration...</p>";
    }
    
    // Check if columns already exist
    $stmt = $pdo->query("SHOW COLUMNS FROM scholarship_programs LIKE 'academic_year'");
    $academic_year_exists = $stmt->rowCount() > 0;
    
    $stmt = $pdo->query("SHOW COLUMNS FROM scholarship_programs LIKE 'application_start_date'");
    $start_date_exists = $stmt->rowCount() > 0;
    
    $stmt = $pdo->query("SHOW COLUMNS FROM scholarship_programs LIKE 'application_end_date'");
    $end_date_exists = $stmt->rowCount() > 0;
    
    // Add academic_year column if it doesn't exist
    if (!$academic_year_exists) {
        if ($is_cli) {
            echo "Adding academic_year column to scholarship_programs table...\n";
        } else {
            echo "<p>Adding academic_year column to scholarship_programs table...</p>";
        }
        
        $pdo->exec("
            ALTER TABLE scholarship_programs
            ADD COLUMN academic_year VARCHAR(20) DEFAULT NULL COMMENT 'Academic year (e.g., 2025 or 2025-2026)'
            AFTER program_description
        ");
        
        if ($is_cli) {
            echo "✓ academic_year column added successfully\n";
        } else {
            echo "<p class='success'>✓ academic_year column added successfully</p>";
        }
    } else {
        if ($is_cli) {
            echo "academic_year column already exists, skipping...\n";
        } else {
            echo "<p class='info'>academic_year column already exists, skipping...</p>";
        }
    }
    
    // Add application_start_date column if it doesn't exist
    if (!$start_date_exists) {
        if ($is_cli) {
            echo "Adding application_start_date column to scholarship_programs table...\n";
        } else {
            echo "<p>Adding application_start_date column to scholarship_programs table...</p>";
        }
        
        $pdo->exec("
            ALTER TABLE scholarship_programs
            ADD COLUMN application_start_date DATE DEFAULT NULL COMMENT 'Application period start date'
            AFTER academic_year
        ");
        
        if ($is_cli) {
            echo "✓ application_start_date column added successfully\n";
        } else {
            echo "<p class='success'>✓ application_start_date column added successfully</p>";
        }
    } else {
        if ($is_cli) {
            echo "application_start_date column already exists, skipping...\n";
        } else {
            echo "<p class='info'>application_start_date column already exists, skipping...</p>";
        }
    }
    
    // Add application_end_date column if it doesn't exist
    if (!$end_date_exists) {
        if ($is_cli) {
            echo "Adding application_end_date column to scholarship_programs table...\n";
        } else {
            echo "<p>Adding application_end_date column to scholarship_programs table...</p>";
        }
        
        $pdo->exec("
            ALTER TABLE scholarship_programs
            ADD COLUMN application_end_date DATE DEFAULT NULL COMMENT 'Application period end date'
            AFTER application_start_date
        ");
        
        if ($is_cli) {
            echo "✓ application_end_date column added successfully\n";
        } else {
            echo "<p class='success'>✓ application_end_date column added successfully</p>";
        }
    } else {
        if ($is_cli) {
            echo "application_end_date column already exists, skipping...\n";
        } else {
            echo "<p class='info'>application_end_date column already exists, skipping...</p>";
        }
    }
    
    // Add index for better query performance
    try {
        $pdo->exec("
            CREATE INDEX idx_academic_year ON scholarship_programs(academic_year)
        ");
        if ($is_cli) {
            echo "✓ Index on academic_year created\n";
        } else {
            echo "<p class='success'>✓ Index on academic_year created</p>";
        }
    } catch (PDOException $e) {
        // Index might already exist, that's okay
        if ($is_cli) {
            echo "Index on academic_year already exists or could not be created\n";
        } else {
            echo "<p class='info'>Index on academic_year already exists or could not be created</p>";
        }
    }
    
    $pdo->commit();
    
    if ($is_cli) {
        echo "\nMigration completed successfully!\n";
    } else {
        echo "<p class='success'><strong>Migration completed successfully!</strong></p>";
        echo "<p>You can now set academic years for scholarship programs in the Superadmin Dashboard.</p>";
        echo "</body></html>";
    }
    
} catch (PDOException $e) {
    $pdo->rollBack();
    
    if ($is_cli) {
        echo "ERROR: Migration failed: " . $e->getMessage() . "\n";
    } else {
        echo "<p class='error'><strong>ERROR:</strong> Migration failed: " . htmlspecialchars($e->getMessage()) . "</p>";
        echo "</body></html>";
    }
    exit(1);
}

?>
