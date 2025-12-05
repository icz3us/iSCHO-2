<?php
/**
 * AJAX endpoint to fetch program-specific requirements
 * This is public information, so we don't require authentication
 */

require './connect/connection.php';

header('Content-Type: application/json');

if (!isset($_GET['program_id']) || empty($_GET['program_id'])) {
    echo json_encode(['success' => false, 'message' => 'Program ID is required']);
    exit;
}

$program_id = intval($_GET['program_id']);

try {
    // Fetch program requirements - prevent duplicates by using DISTINCT and grouping
    $stmt = $pdo->prepare("
        SELECT DISTINCT requirement_name, requirement_description, requirement_type, is_required
        FROM program_requirements
        WHERE program_id = ? AND is_required = 1
        GROUP BY requirement_name, requirement_description, requirement_type, is_required
        ORDER BY requirement_name
    ");
    $stmt->execute([$program_id]);
    $requirements = $stmt->fetchAll(PDO::FETCH_ASSOC);
    
    // Additional deduplication by requirement_name to prevent any duplicates
    $unique_requirements = [];
    $seen_names = [];
    foreach ($requirements as $req) {
        $name = strtolower(trim($req['requirement_name']));
        if (!in_array($name, $seen_names)) {
            $seen_names[] = $name;
            $unique_requirements[] = $req;
        }
    }
    $requirements = $unique_requirements;
    
    // If no specific requirements found, return default requirements
    if (empty($requirements)) {
        $requirements = [
            [
                'requirement_name' => 'Certificate of Registration',
                'requirement_description' => 'COR file upload',
                'requirement_type' => 'document',
                'is_required' => 1
            ],
            [
                'requirement_name' => 'Certificate of Indigency',
                'requirement_description' => 'Indigency certificate file upload',
                'requirement_type' => 'document',
                'is_required' => 1
            ],
            [
                'requirement_name' => 'Voter\'s Certificate',
                'requirement_description' => 'Voter certificate file upload',
                'requirement_type' => 'document',
                'is_required' => 1
            ]
        ];
    }
    
    echo json_encode([
        'success' => true,
        'requirements' => $requirements
    ]);
    
} catch (PDOException $e) {
    echo json_encode([
        'success' => false,
        'message' => 'Error fetching requirements: ' . $e->getMessage()
    ]);
}

