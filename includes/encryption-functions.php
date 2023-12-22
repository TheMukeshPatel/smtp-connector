<?php

// Exit if accessed directly
if (!defined('ABSPATH')) {
    exit;
}

// Include this file where encryption and decryption functions are needed

define('SMTP_SECURITY_KEY', SECURE_AUTH_KEY); // Actual constant security key

function smtp_connector_for_wp_encrypted_password($password)
{
    $cipher = 'aes-256-cbc';
    $ivlen = openssl_cipher_iv_length($cipher);
    $iv = openssl_random_pseudo_bytes($ivlen);
    $encrypted = openssl_encrypt($password, $cipher, SMTP_SECURITY_KEY, 0, $iv);
    return base64_encode($iv . $encrypted);
}

function smtp_connector_for_wp_decrypted_password($encrypted_password)
{
    $cipher = 'aes-256-cbc';
    $data = base64_decode($encrypted_password);
    $ivlen = openssl_cipher_iv_length($cipher);
    $iv = substr($data, 0, $ivlen);
    $decrypted = openssl_decrypt(substr($data, $ivlen), $cipher, SMTP_SECURITY_KEY, 0, $iv);
    return $decrypted;
}
?>
