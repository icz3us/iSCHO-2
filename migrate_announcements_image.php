<?php
require 'connect/connection.php';

try {
    // Add image_path column to notices table
    $stmt = $pdo->prepare("ALTER TABLE notices ADD COLUMN image_path VARCHAR(500) DEFAULT NULL");
    $stmt->execute();
    
    echo "Successfully added image_path column to notices table.\n";
} catch (PDOException $e) {
    // Check if the column already exists
    if (strpos($e->getMessage(), 'Duplicate column name') !== false) {
        echo "Column image_path already exists in notices table.\n";
    } else {
        echo "Error adding image_path column: " . $e->getMessage() . "\n";
    }
}
?>