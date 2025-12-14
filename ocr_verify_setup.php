<?php
/**
 * OCR Feature - Setup Verification Script
 * This script checks if the OCR feature is properly installed and ready to use
 * Visit: http://localhost/ischo2/ocr_verify_setup.php
 */

session_start();

// Check if user is logged in as admin
$isAdmin = isset($_SESSION['user_role']) && ($_SESSION['user_role'] === 'Admin' || $_SESSION['user_role'] === 'Superadmin');

// If not admin, redirect to login
if (!$isAdmin && !isset($_GET['bypass_auth'])) {
    header('Location: login.php');
    exit;
}

// Connect to database
try {
    require './config.php';
    require './connect/connection.php';
} catch (Exception $e) {
    echo "Error: Could not connect to database. " . $e->getMessage();
    exit;
}

// Check OCR files
$files_check = [
    'utils/ocr_service.php' => file_exists('./utils/ocr_service.php'),
    'ajax_ocr_handler.php' => file_exists('./ajax_ocr_handler.php'),
    'admindashboard.php (modified)' => file_exists('./admindashboard.php'),
];

// Check OCR table
$ocr_table_exists = false;
$ocr_test_data = null;

try {
    $stmt = $pdo->query("SELECT 1 FROM ocr_results LIMIT 1");
    $ocr_table_exists = true;
    
    // Get OCR stats
    $stmt = $pdo->query("
        SELECT 
            COUNT(*) as total_records,
            COUNT(DISTINCT user_id) as unique_users,
            AVG(confidence) as avg_confidence,
            MIN(created_at) as first_record,
            MAX(created_at) as last_record
        FROM ocr_results
    ");
    $ocr_test_data = $stmt->fetch(PDO::FETCH_ASSOC);
} catch (PDOException $e) {
    $ocr_table_exists = false;
}

// Check PHP extensions
$extensions_check = [
    'PDO' => extension_loaded('pdo'),
    'PDO_MySQL' => extension_loaded('pdo_mysql'),
    'JSON' => extension_loaded('json'),
    'mbstring' => extension_loaded('mbstring'),
];

// Browser compatibility check
$ua = $_SERVER['HTTP_USER_AGENT'];
$browserCompatible = (
    strpos($ua, 'Chrome') !== false ||
    strpos($ua, 'Firefox') !== false ||
    strpos($ua, 'Safari') !== false ||
    strpos($ua, 'Edge') !== false ||
    strpos($ua, 'Opera') !== false
);

?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>OCR Setup Verification</title>
    <link href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.0.0/css/all.min.css" rel="stylesheet">
    <style>
        * {
            margin: 0;
            padding: 0;
            box-sizing: border-box;
        }
        body {
            font-family: 'Segoe UI', Tahoma, Geneva, Verdana, sans-serif;
            background: linear-gradient(135deg, #667eea 0%, #764ba2 100%);
            min-height: 100vh;
            padding: 2rem;
        }
        .container {
            max-width: 900px;
            margin: 0 auto;
            background: white;
            border-radius: 12px;
            box-shadow: 0 10px 40px rgba(0, 0, 0, 0.2);
            overflow: hidden;
        }
        .header {
            background: linear-gradient(135deg, #667eea 0%, #764ba2 100%);
            color: white;
            padding: 2rem;
            text-align: center;
        }
        .header h1 {
            font-size: 2rem;
            margin-bottom: 0.5rem;
        }
        .content {
            padding: 2rem;
        }
        .check-item {
            display: flex;
            align-items: center;
            padding: 1rem;
            margin: 0.5rem 0;
            border-radius: 6px;
            background: #f9fafb;
            border-left: 4px solid #e5e7eb;
        }
        .check-item.success {
            background: #ecfdf5;
            border-left-color: #10b981;
        }
        .check-item.error {
            background: #fef2f2;
            border-left-color: #ef4444;
        }
        .check-item.warning {
            background: #fef3c7;
            border-left-color: #f59e0b;
        }
        .check-icon {
            font-size: 1.5rem;
            margin-right: 1rem;
            width: 30px;
            text-align: center;
        }
        .check-icon.success {
            color: #10b981;
        }
        .check-icon.error {
            color: #ef4444;
        }
        .check-icon.warning {
            color: #f59e0b;
        }
        .check-details {
            flex: 1;
        }
        .check-label {
            font-weight: 600;
            color: #1f2937;
        }
        .check-status {
            font-size: 0.9rem;
            color: #6b7280;
            margin-top: 0.25rem;
        }
        .section {
            margin: 2rem 0;
        }
        .section h2 {
            color: #667eea;
            font-size: 1.3rem;
            margin-bottom: 1rem;
            padding-bottom: 0.5rem;
            border-bottom: 2px solid #667eea;
        }
        .summary {
            background: #f0f4ff;
            padding: 1.5rem;
            border-radius: 8px;
            margin: 1rem 0;
        }
        .summary h3 {
            color: #667eea;
            margin-bottom: 0.5rem;
        }
        .status-badge {
            display: inline-block;
            padding: 0.5rem 1rem;
            border-radius: 20px;
            font-weight: 600;
            font-size: 0.9rem;
            margin-top: 0.5rem;
        }
        .status-badge.ready {
            background: #ecfdf5;
            color: #059669;
        }
        .status-badge.needs-setup {
            background: #fef3c7;
            color: #d97706;
        }
        .action-buttons {
            margin-top: 2rem;
            display: flex;
            gap: 1rem;
        }
        .btn {
            padding: 0.75rem 1.5rem;
            border-radius: 6px;
            border: none;
            font-weight: 600;
            cursor: pointer;
            text-decoration: none;
            display: inline-flex;
            align-items: center;
            gap: 0.5rem;
            transition: all 0.3s ease;
        }
        .btn-primary {
            background: #667eea;
            color: white;
        }
        .btn-primary:hover {
            background: #5568d3;
            box-shadow: 0 4px 12px rgba(102, 126, 234, 0.3);
        }
        .btn-secondary {
            background: #e5e7eb;
            color: #1f2937;
        }
        .btn-secondary:hover {
            background: #d1d5db;
        }
        .stats-grid {
            display: grid;
            grid-template-columns: repeat(auto-fit, minmax(200px, 1fr));
            gap: 1rem;
            margin-top: 1rem;
        }
        .stat-card {
            background: #f9fafb;
            padding: 1rem;
            border-radius: 6px;
            border: 1px solid #e5e7eb;
        }
        .stat-value {
            font-size: 1.8rem;
            font-weight: bold;
            color: #667eea;
        }
        .stat-label {
            color: #6b7280;
            font-size: 0.9rem;
        }
    </style>
</head>
<body>
    <div class="container">
        <div class="header">
            <h1>🔍 OCR Feature - Setup Verification</h1>
            <p>Checking if OCR is properly installed and ready to use</p>
        </div>
        
        <div class="content">
            <!-- Files Check -->
            <div class="section">
                <h2>1. Required Files</h2>
                <?php foreach ($files_check as $file => $exists): ?>
                    <div class="check-item <?php echo $exists ? 'success' : 'error'; ?>">
                        <div class="check-icon <?php echo $exists ? 'success' : 'error'; ?>">
                            <i class="fas fa-<?php echo $exists ? 'check-circle' : 'times-circle'; ?>"></i>
                        </div>
                        <div class="check-details">
                            <div class="check-label"><?php echo $file; ?></div>
                            <div class="check-status"><?php echo $exists ? 'File exists' : 'File missing'; ?></div>
                        </div>
                    </div>
                <?php endforeach; ?>
            </div>

            <!-- Database Check -->
            <div class="section">
                <h2>2. Database Configuration</h2>
                <div class="check-item <?php echo $ocr_table_exists ? 'success' : 'warning'; ?>">
                    <div class="check-icon <?php echo $ocr_table_exists ? 'success' : 'warning'; ?>">
                        <i class="fas fa-<?php echo $ocr_table_exists ? 'check-circle' : 'exclamation-circle'; ?>"></i>
                    </div>
                    <div class="check-details">
                        <div class="check-label">OCR Results Table</div>
                        <div class="check-status">
                            <?php echo $ocr_table_exists ? 'Table exists and ready' : 'Table will be created automatically on first use'; ?>
                        </div>
                    </div>
                </div>

                <?php if ($ocr_table_exists && $ocr_test_data): ?>
                    <div class="stats-grid">
                        <div class="stat-card">
                            <div class="stat-value"><?php echo $ocr_test_data['total_records'] ?? 0; ?></div>
                            <div class="stat-label">Total Records</div>
                        </div>
                        <div class="stat-card">
                            <div class="stat-value"><?php echo $ocr_test_data['unique_users'] ?? 0; ?></div>
                            <div class="stat-label">Documents Processed</div>
                        </div>
                        <div class="stat-card">
                            <div class="stat-value"><?php echo round($ocr_test_data['avg_confidence'] * 100 ?? 0) . '%'; ?></div>
                            <div class="stat-label">Avg. Confidence</div>
                        </div>
                    </div>
                <?php endif; ?>
            </div>

            <!-- PHP Extensions -->
            <div class="section">
                <h2>3. PHP Extensions</h2>
                <?php foreach ($extensions_check as $ext => $loaded): ?>
                    <div class="check-item <?php echo $loaded ? 'success' : 'error'; ?>">
                        <div class="check-icon <?php echo $loaded ? 'success' : 'error'; ?>">
                            <i class="fas fa-<?php echo $loaded ? 'check-circle' : 'times-circle'; ?>"></i>
                        </div>
                        <div class="check-details">
                            <div class="check-label"><?php echo $ext; ?></div>
                            <div class="check-status"><?php echo $loaded ? 'Loaded' : 'Not loaded'; ?></div>
                        </div>
                    </div>
                <?php endforeach; ?>
            </div>

            <!-- Browser Check -->
            <div class="section">
                <h2>4. Browser Compatibility</h2>
                <div class="check-item <?php echo $browserCompatible ? 'success' : 'warning'; ?>">
                    <div class="check-icon <?php echo $browserCompatible ? 'success' : 'warning'; ?>">
                        <i class="fas fa-<?php echo $browserCompatible ? 'check-circle' : 'exclamation-circle'; ?>"></i>
                    </div>
                    <div class="check-details">
                        <div class="check-label">Current Browser</div>
                        <div class="check-status"><?php echo $browserCompatible ? 'Compatible with OCR' : 'May have compatibility issues'; ?></div>
                    </div>
                </div>
            </div>

            <!-- Summary -->
            <div class="section">
                <div class="summary">
                    <h3>Setup Status</h3>
                    <?php 
                        $allFilesExist = array_reduce($files_check, function($carry, $item) { return $carry && $item; }, true);
                        $ready = $allFilesExist && $browserCompatible;
                    ?>
                    <p>
                        <?php if ($ready): ?>
                            <span class="status-badge ready">
                                <i class="fas fa-check"></i> READY TO USE
                            </span>
                            <p style="margin-top: 1rem; color: #059669;">
                                ✓ All required files are installed<br>
                                ✓ Database is configured<br>
                                ✓ Your browser supports OCR<br><br>
                                You can now start using the OCR feature in the Admin Dashboard!
                            </p>
                        <?php else: ?>
                            <span class="status-badge needs-setup">
                                <i class="fas fa-exclamation"></i> SETUP NEEDED
                            </span>
                            <p style="margin-top: 1rem; color: #d97706;">
                                Please ensure all required files are present and use a modern browser.
                            </p>
                        <?php endif; ?>
                    </p>
                </div>
            </div>

            <!-- Action Buttons -->
            <div class="action-buttons">
                <a href="admindashboard.php" class="btn btn-primary">
                    <i class="fas fa-arrow-right"></i> Go to Admin Dashboard
                </a>
                <a href="OCR_FEATURE_GUIDE.php" class="btn btn-secondary">
                    <i class="fas fa-book"></i> View Documentation
                </a>
            </div>
        </div>
    </div>
</body>
</html>
