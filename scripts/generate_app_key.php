<?php
// Generates a 32-byte key and writes it as hex to config/.app_key
@mkdir(__DIR__ . '/../config', 0700, true);
$key = bin2hex(random_bytes(32));
$path = __DIR__ . '/../config/.app_key';
file_put_contents($path, $key);
echo "Generated app key and saved to config/.app_key\n";
echo "APP KEY (hex): $key\n";
echo "Set environment variable ISCHO_APP_KEY to this value for production, or keep the file protected.";
