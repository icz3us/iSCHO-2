<?php
/**
 * Cleanup Duplicate Program Requirements
 * 
 * This script removes duplicate requirements from the program_requirements table
 * to prevent redundancy in the application.
 */

require './connect/connection.php';

// Check if running from CLI or web browser
$is_cli = php_sapi_name() === 'cli';

if (!$is_cli) {
    header('Content-Type: text/html; charset=utf-8');
    echo "<!DOCTYPE html><html><head><title>Cleanup Duplicates</title>";
    echo "<style>body{font-family:Arial,sans-serif;max-width:800px;margin:50px auto;padding:20px;}";
    echo ".success{color:green;}.error{color:red;}.info{color:blue;}</style></head><body>";
    echo "<h1>Cleanup Duplicate Requirements</h1>";
}

try {
    if ($is_cli) {
        echo "Starting cleanup of duplicate requirements...\n\n";
    } else {
        echo "<p class='info'>Starting cleanup of duplicate requirements...</p>";
    }
    
    // Find and remove duplicates
    // Keep the first occurrence of each requirement_name per program_id
    $stmt = $pdo->query("
        SELECT program_id, requirement_name, COUNT(*) as count
        FROM program_requirements
        GROUP BY program_id, requirement_name
        HAVING COUNT(*) > 1
    ");
    $duplicates = $stmt->fetchAll(PDO::FETCH_ASSOC);
    
    if (empty($duplicates)) {
        if ($is_cli) {
            echo "No duplicate requirements found. Database is clean!\n";
        } else {
            echo "<p class='success'>No duplicate requirements found. Database is clean!</p>";
        }
    } else {
        $total_removed = 0;
        
        foreach ($duplicates as $dup) {
            // Get all IDs for this duplicate requirement
            $stmt = $pdo->prepare("
                SELECT id FROM program_requirements
                WHERE program_id = ? AND requirement_name = ?
                ORDER BY id ASC
            ");
            $stmt->execute([$dup['program_id'], $dup['requirement_name']]);
            $ids = $stmt->fetchAll(PDO::FETCH_COLUMN);
            
            // Keep the first one, delete the rest
            if (count($ids) > 1) {
                $ids_to_delete = array_slice($ids, 1); // Skip first, delete rest
                $placeholders = str_repeat('?,', count($ids_to_delete) - 1) . '?';
                
                $delete_stmt = $pdo->prepare("
                    DELETE FROM program_requirements
                    WHERE id IN ($placeholders)
                ");
                $delete_stmt->execute($ids_to_delete);
                
                $removed = $delete_stmt->rowCount();
                $total_removed += $removed;
                
                if ($is_cli) {
                    echo "Removed $removed duplicate(s) for '{$dup['requirement_name']}' in program_id {$dup['program_id']}\n";
                } else {
                    echo "<p>Removed $removed duplicate(s) for '<strong>{$dup['requirement_name']}</strong>' in program_id {$dup['program_id']}</p>";
                }
            }
        }
        
        if ($is_cli) {
            echo "\nCleanup completed! Total duplicates removed: $total_removed\n";
        } else {
            echo "<p class='success'><strong>Cleanup completed!</strong> Total duplicates removed: $total_removed</p>";
        }
    }
    
    // Show current requirements count per program
    if ($is_cli) {
        echo "\nCurrent requirements per program:\n";
    } else {
        echo "<h2>Current Requirements Summary</h2>";
    }
    
    $stmt = $pdo->query("
        SELECT sp.id, sp.program_name, COUNT(pr.id) as requirement_count
        FROM scholarship_programs sp
        LEFT JOIN program_requirements pr ON sp.id = pr.program_id
        WHERE sp.is_active = 1
        GROUP BY sp.id, sp.program_name
        ORDER BY sp.program_name
    ");
    $summary = $stmt->fetchAll(PDO::FETCH_ASSOC);
    
    foreach ($summary as $row) {
        if ($is_cli) {
            echo "  - {$row['program_name']}: {$row['requirement_count']} requirements\n";
        } else {
            echo "<p><strong>{$row['program_name']}</strong>: {$row['requirement_count']} requirements</p>";
        }
    }
    
    if (!$is_cli) {
        echo "<p><a href='applicantdashboard.php?view=Application'>Go to Application Form</a></p>";
        echo "</body></html>";
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

