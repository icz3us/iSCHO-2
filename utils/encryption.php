<?php
// Encryption helpers for iSCHO
if (!function_exists('get_app_key')) {
    function get_app_key() {
        if (defined('ISCHO_APP_KEY') && ISCHO_APP_KEY) {
            $hex = ISCHO_APP_KEY;
            $key = @hex2bin($hex);
            if ($key === false) {
                throw new Exception('APP key is not a valid hex string.');
            }
            if (strlen($key) < 32) {
                throw new Exception('APP key must be 32 bytes (64 hex chars).');
            }
            return $key;
        }
        throw new Exception('Application encryption key not configured. Set ISCHO_APP_KEY or create config/.app_key');
    }
}

if (!function_exists('encrypt_for_db')) {
    function encrypt_for_db($plaintext) {
        if ($plaintext === null || $plaintext === '') return $plaintext;
        try {
            $key = get_app_key();
        } catch (Exception $e) {
            return $plaintext; // fallback: return plaintext if no key (avoid breaking app)
        }

        $iv = random_bytes(12); // 96-bit IV for GCM
        $tag = '';
        $ciphertext = openssl_encrypt($plaintext, 'aes-256-gcm', $key, OPENSSL_RAW_DATA, $iv, $tag, '', 16);
        if ($ciphertext === false) {
            return $plaintext;
        }
        // store as base64(iv || tag || ciphertext)
        return base64_encode($iv . $tag . $ciphertext);
    }
}

if (!function_exists('decrypt_from_db')) {
    function decrypt_from_db($b64payload) {
        if ($b64payload === null || $b64payload === '') return $b64payload;
        try {
            $key = get_app_key();
        } catch (Exception $e) {
            return $b64payload; // fallback: return raw if no key
        }

        $data = base64_decode($b64payload, true);
        if ($data === false) return $b64payload;
        // iv (12) + tag (16) + ciphertext
        if (strlen($data) < 28) return $b64payload;
        $iv = substr($data, 0, 12);
        $tag = substr($data, 12, 16);
        $ciphertext = substr($data, 28);
        $plaintext = openssl_decrypt($ciphertext, 'aes-256-gcm', $key, OPENSSL_RAW_DATA, $iv, $tag);
        if ($plaintext === false) return $b64payload;
        return $plaintext;
    }
}
