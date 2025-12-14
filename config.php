<?php
// Application configuration loader
// Load APP key from environment variable or from config file
$app_key = getenv('ISCHO_APP_KEY') ?: null;
$app_key_file = __DIR__ . '/config/.app_key';
if (!$app_key && file_exists($app_key_file)) {
    $app_key = trim(file_get_contents($app_key_file));
}

if ($app_key) {
    define('ISCHO_APP_KEY', $app_key);
} else {
    define('ISCHO_APP_KEY', null);
}
