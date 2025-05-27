<?php
$host = 'ischo.cd2yqo8eytpf.ap-southeast-1.rds.amazonaws.com';
$dbname = 'ischo2';
$user = 'admin';
$pass = 'X7!pL#e9Bz^W';

try {
    $pdo = new PDO("mysql:host=$host;dbname=$dbname", $user, $pass);
    $pdo->setAttribute(PDO::ATTR_ERRMODE, PDO::ERRMODE_EXCEPTION);
} catch (PDOException $e) {
    echo "Database connection failed: " . $e->getMessage();
    die();
}

