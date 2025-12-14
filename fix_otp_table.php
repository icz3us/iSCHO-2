<?php
/**
 * Fix OTP Verifications Table Structure
 * 
 * This script ensures the otp_verifications table has user_id as nullable,
 * which is required since users don't exist yet during registration.
 */

require './connect/connection.php';

// Check if running from CLI or web browser
$is_cli = php_sapi_name() === 'cli';

if (!$is_cli) {
    header('Content-Type: text/html; charset=utf-8');
    echo "<!DOCTYPE html><html><head><title>Fix OTP Table</title>";
    echo "<style>body{font-family:Arial,sans-serif;max-width:800px;margin:50px auto;padding:20px;}";
    echo ".success{color:green;}.error{color:red;}.info{color:blue;}</style></head><body>";
    echo "<h1>Fix OTP Verifications Table</h1>";
}

try {
    // Check current structure
    if ($is_cli) {
        echo "Checking otp_verifications table structure...\n";
    } else {
        echo "<p class='info'>Checking otp_verifications table structure...</p>";
    }
    
    $stmt = $pdo->query("SHOW COLUMNS FROM otp_verifications LIKE 'user_id'");
    $column = $stmt->fetch(PDO::FETCH_ASSOC);
    
    if ($column) {
        // Check if it's nullable
        $is_nullable = strtoupper($column['Null']) === 'YES';
        
        if (!$is_nullable) {
            if ($is_cli) {
                echo "Making user_id nullable...\n";
            } else {
                echo "<p>Making user_id nullable...</p>";
            }
            
            // Alter the column to be nullable
            $pdo->exec("ALTER TABLE otp_verifications MODIFY COLUMN user_id INT NULL DEFAULT NULL");
            
            if ($is_cli) {
                echo "Successfully updated user_id column to be nullable.\n";
            } else {
                echo "<p class='success'>Successfully updated user_id column to be nullable.</p>";
            }
        } else {
            if ($is_cli) {
                echo "user_id column is already nullable. No changes needed.\n";
            } else {
                echo "<p class='success'>user_id column is already nullable. No changes needed.</p>";
            }
        }
    } else {
        // Column doesn't exist, add it
        if ($is_cli) {
            echo "Adding user_id column as nullable...\n";
        } else {
            echo "<p>Adding user_id column as nullable...</p>";
        }
        
        $pdo->exec("ALTER TABLE otp_verifications ADD COLUMN user_id INT NULL DEFAULT NULL AFTER otp");
        
        if ($is_cli) {
            echo "Successfully added user_id column.\n";
        } else {
            echo "<p class='success'>Successfully added user_id column.</p>";
        }
    }
    
    if ($is_cli) {
        echo "\nTable structure is now correct!\n";
    } else {
        echo "<div class='success'>";
        echo "<h2>Table structure is now correct!</h2>";
        echo "<p>You can now register users without errors.</p>";
        echo "<p><a href='login.php'>Go to Login Page</a></p>";
        echo "</div></body></html>";
    }
    
} catch (PDOException $e) {
    if ($is_cli) {
        echo "\nERROR: " . $e->getMessage() . "\n";
    } else {
        echo "<div class='error'>";
        echo "<h2>ERROR</h2>";
        echo "<p>" . htmlspecialchars($e->getMessage()) . "</p>";
        echo "</div></body></html>";
    }
    exit(1);
}

