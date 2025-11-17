<?php
// Simple encrypted backup script for iSCHO
// Usage: php scripts/backup.php
require_once __DIR__ . '/../config.php';
require_once __DIR__ . '/../utils/encryption.php';

$timestamp = date('Ymd_His');
$backupDir = __DIR__ . '/../backups';
if (!is_dir($backupDir)) mkdir($backupDir, 0700, true);

$sqlFile = "$backupDir/backup_$timestamp.sql";
$zipFile = "$backupDir/backup_$timestamp.zip";
$encFile = "$backupDir/backup_$timestamp.enc";

// Attempt to run mysqldump (assumes XAMPP default credentials). Adjust if needed.
$cmd = "mysqldump -u root ischo > " . escapeshellarg($sqlFile);
exec($cmd, $output, $ret);
if ($ret !== 0) {
    echo "mysqldump failed. Ensure mysqldump is in PATH and credentials are correct.\n";
    if (file_exists($sqlFile)) unlink($sqlFile);
    exit(1);
}

// Zip the .sql file
$zip = new ZipArchive();
if ($zip->open($zipFile, ZipArchive::CREATE) !== TRUE) {
    echo "Failed to create zip file.\n";
    unlink($sqlFile);
    exit(1);
}
$zip->addFile($sqlFile, basename($sqlFile));
$zip->close();
unlink($sqlFile);

// Encrypt zip file using APP key
try {
    $key = get_app_key();
} catch (Exception $e) {
    echo "Encryption key not configured: " . $e->getMessage() . "\n";
    // keep unencrypted zip (but warn)
    echo "Backup created without encryption at: $zipFile\n";
    exit(1);
}

$data = file_get_contents($zipFile);
$iv = random_bytes(12);
$tag = '';
$cipher = openssl_encrypt($data, 'aes-256-gcm', $key, OPENSSL_RAW_DATA, $iv, $tag, '', 16);
if ($cipher === false) {
    echo "Encryption failed.\n";
    exit(1);
}
file_put_contents($encFile, base64_encode($iv . $tag . $cipher));
unlink($zipFile);

echo "Encrypted backup created: $encFile\n";
echo "Keep your APP key safe to decrypt backups.\n";
