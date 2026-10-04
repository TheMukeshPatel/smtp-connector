<?php
/**
 * Encryption for the saved SMTP password.
 *
 * Passwords are stored as "sc2:" + base64(nonce . ciphertext), encrypted with libsodium's
 * secretbox (authenticated; bundled with WordPress since 5.2) and a key derived from the site's
 * secure_auth salt. Passwords saved by versions 1.1–1.3.x can still be read.
 *
 * @package SMTP_Connector
 */

// Exit if accessed directly
if (!defined('ABSPATH')) {
    exit;
}

define('SMTP_CONNECTOR_FOR_WP_CIPHER_PREFIX', 'sc2:');

/** Length of a secretbox nonce (SODIUM_CRYPTO_SECRETBOX_NONCEBYTES). */
define('SMTP_CONNECTOR_FOR_WP_NONCE_BYTES', 24);

/**
 * Key used to encrypt the password.
 *
 * wp_salt() uses SECURE_AUTH_KEY/SECURE_AUTH_SALT from wp-config.php and falls back to a
 * generated, stored salt when they are missing or still set to the sample phrase.
 *
 * @return string 32 raw bytes.
 */
function smtp_connector_for_wp_encryption_key() {
    return hash_hmac('sha256', 'smtp-connector-smtp-password', wp_salt('secure_auth'), true);
}

/**
 * Whether a stored value is in the current encrypted format.
 *
 * @param mixed $value Stored option value.
 * @return bool
 */
function smtp_connector_for_wp_is_encrypted_value($value) {
    return is_string($value) && 0 === strpos($value, SMTP_CONNECTOR_FOR_WP_CIPHER_PREFIX);
}

/**
 * Encrypts a password for storage.
 *
 * @param string $password Plain password.
 * @return string|false Encrypted value, or false if encryption is not possible.
 */
function smtp_connector_for_wp_encrypted_password($password) {
    try {
        $nonce  = random_bytes(SMTP_CONNECTOR_FOR_WP_NONCE_BYTES);
        $cipher = sodium_crypto_secretbox((string) $password, $nonce, smtp_connector_for_wp_encryption_key());
    } catch (Exception $e) {
        return false;
    }

    // phpcs:ignore WordPress.PHP.DiscouragedPHPFunctions.obfuscation_base64_encode -- Stores binary ciphertext as text, not obfuscation.
    return SMTP_CONNECTOR_FOR_WP_CIPHER_PREFIX . base64_encode($nonce . $cipher);
}

/**
 * Decrypts a stored password.
 *
 * @param mixed $stored Stored option value.
 * @return string|false The password, or false when it can't be decrypted (for example after
 *                      the security keys in wp-config.php changed).
 */
function smtp_connector_for_wp_decrypt_password($stored) {
    if (!is_string($stored) || '' === $stored) {
        return false;
    }

    if (smtp_connector_for_wp_is_encrypted_value($stored)) {
        // phpcs:ignore WordPress.PHP.DiscouragedPHPFunctions.obfuscation_base64_decode -- Reads the stored ciphertext.
        $raw = base64_decode(substr($stored, strlen(SMTP_CONNECTOR_FOR_WP_CIPHER_PREFIX)), true);
        if (false === $raw || strlen($raw) <= SMTP_CONNECTOR_FOR_WP_NONCE_BYTES) {
            return false;
        }
        try {
            $password = sodium_crypto_secretbox_open(
                substr($raw, SMTP_CONNECTOR_FOR_WP_NONCE_BYTES),
                substr($raw, 0, SMTP_CONNECTOR_FOR_WP_NONCE_BYTES),
                smtp_connector_for_wp_encryption_key()
            );
        } catch (Exception $e) {
            return false;
        }
        return false === $password ? false : $password;
    }

    // Saved by 1.1–1.3.x.
    $password = smtp_connector_for_wp_legacy_decrypt($stored);
    if (false === $password) {
        return false;
    }

    // 1.3.x encrypted the password twice when it was saved for the first time; remove that layer.
    $inner = smtp_connector_for_wp_legacy_decrypt($password);
    return false === $inner ? $password : $inner;
}

/**
 * Reads the format used up to 1.3.x: base64(IV . openssl_encrypt(AES-256-CBC)) keyed with SECURE_AUTH_KEY.
 *
 * @param string $value Stored value.
 * @return string|false
 */
function smtp_connector_for_wp_legacy_decrypt($value) {
    if (!function_exists('openssl_decrypt')) {
        return false;
    }

    // phpcs:ignore WordPress.PHP.DiscouragedPHPFunctions.obfuscation_base64_decode -- Reads the stored ciphertext.
    $data = base64_decode($value, true);
    if (false === $data || strlen($data) <= 16) {
        return false;
    }

    $iv         = substr($data, 0, 16);
    $ciphertext = substr($data, 16);
    // openssl_encrypt() returned base64 text, so anything else can't be one of our values.
    if (!preg_match('#^[A-Za-z0-9+/]+={0,2}$#', $ciphertext)) {
        return false;
    }

    // Up to 1.2.3 the literal constant name was used as key when SECURE_AUTH_KEY was not defined.
    $key = defined('SECURE_AUTH_KEY') ? SECURE_AUTH_KEY : 'SECURE_AUTH_KEY';

    $password = openssl_decrypt($ciphertext, 'aes-256-cbc', $key, 0, $iv);
    return false === $password ? false : $password;
}
