<?php
/**
 * Plugin Name:       SMTP Connector – Free SMTP & Email Log
 * Plugin URI:        https://mpateldigital.com/smtp-connector/
 * Description:       Send every WordPress email through your own SMTP server: Gmail, Zoho, Amazon SES, Brevo, Mailgun and more. With test email and email log.
 * Version:           1.4.0
 * Requires at least: 5.9
 * Requires PHP:      7.2
 * Author:            Mukesh Patel
 * Author URI:        https://mpatel.org/
 * License:           GPLv2 or later
 * License URI:       https://www.gnu.org/licenses/gpl-2.0.html
 * Text Domain:       smtp-connector
 *
 * @package SMTP_Connector
 */

// Exit if accessed directly
if (!defined('ABSPATH')) {
    exit;
}

define('SMTP_CONNECTOR_FOR_WP_VERSION', '1.4.0');
define('SMTP_CONNECTOR_FOR_WP_DB_VERSION', '2');
define('SMTP_CONNECTOR_FOR_WP_FILE', __FILE__);

require_once plugin_dir_path(__FILE__) . 'includes/common.php';
require_once plugin_dir_path(__FILE__) . 'includes/encryption-functions.php';

// Settings and email log screens only exist in the admin
if (is_admin()) {
    require_once plugin_dir_path(__FILE__) . 'includes/admin-ui.php';
    require_once plugin_dir_path(__FILE__) . 'includes/settings-page.php';
    require_once plugin_dir_path(__FILE__) . 'includes/email-log.php';
}

/*
 * Activation, deactivation and updates
 */

register_activation_hook(__FILE__, 'smtp_connector_for_wp_activation_hook');
function smtp_connector_for_wp_activation_hook() {
    smtp_connector_for_wp_install();
    set_transient('smtp-connector-for-wp-activation-notice', true, 5 * MINUTE_IN_SECONDS);
}

register_deactivation_hook(__FILE__, 'smtp_connector_for_wp_deactivation_hook');
function smtp_connector_for_wp_deactivation_hook() {
    wp_clear_scheduled_hook('smtp_connector_for_wp_cleanup_logs');
}

// Plugin updates (and network activation) don't run the activation hook on every site.
add_action('init', 'smtp_connector_for_wp_maybe_upgrade', 5);
add_action('init', 'smtp_connector_for_wp_schedule_cleanup');

/*
 * Plugin list links
 */

add_filter('plugin_action_links_' . plugin_basename(__FILE__), 'smtp_connector_for_wp_add_action_links');
function smtp_connector_for_wp_add_action_links($links) {
    $settings_link = '<a href="' . esc_url(admin_url('options-general.php?page=smtp-connector-for-wp')) . '">' . esc_html__('Settings', 'smtp-connector') . '</a>';
    array_unshift($links, $settings_link);

    $links[] = '<a href="https://mpateldigital.com/contact-us/" target="_blank" rel="noopener noreferrer" title="' . esc_attr__('Hire for Technical Support', 'smtp-connector') . '" style="color: #d42e06; font-weight: 500;">' . esc_html__('Hire Me', 'smtp-connector') . '</a>';
    $links[] = '<a href="https://ko-fi.com/mukeshpatel" target="_blank" rel="noopener noreferrer" title="' . esc_attr__('Show your support', 'smtp-connector') . '" style="color: #080;">' . esc_html__('Buy developer a coffee', 'smtp-connector') . '</a>';

    return $links;
}

/*
 * Sending
 */

// Send through the configured SMTP server. Nothing changes until the plugin is configured.
add_action('phpmailer_init', 'smtp_connector_for_wp_custom_phpmailer');
function smtp_connector_for_wp_custom_phpmailer($phpmailer) {
    if (!smtp_connector_for_wp_is_configured()) {
        return;
    }

    $settings = smtp_connector_for_wp_get_settings();

    $phpmailer->isSMTP();
    $phpmailer->Host = $settings['host'];
    $phpmailer->Port = $settings['port'];
    /**
     * Filters how many seconds to wait while connecting to the SMTP server.
     *
     * @param int $timeout Seconds. Default 30.
     */
    $phpmailer->Timeout = max(1, (int) apply_filters('smtp_connector_for_wp_smtp_timeout', 30));
    /**
     * Filters how many seconds to wait for each reply of the SMTP server. PHPMailer waits up to
     * 300 seconds; some servers need a while after the message (for example to scan attachments).
     *
     * @param int $timeout Seconds. Default 120.
     */
    $phpmailer->getSMTPInstance()->Timelimit = max(1, (int) apply_filters('smtp_connector_for_wp_smtp_reply_timeout', 120));

    if ('none' === $settings['security']) {
        $phpmailer->SMTPSecure  = '';
        $phpmailer->SMTPAutoTLS = false;
    } else {
        $phpmailer->SMTPSecure  = $settings['security'];
        $phpmailer->SMTPAutoTLS = true;
    }

    $phpmailer->SMTPAuth = $settings['auth'];
    if ($settings['auth']) {
        $phpmailer->Username = $settings['username'];
        $phpmailer->Password = (string) smtp_connector_for_wp_get_smtp_password();
    }
}

// Use the configured sender for every email. Setting it here (instead of in phpmailer_init) also
// works on sites whose default address, like wordpress@localhost, WordPress itself rejects.
add_filter('wp_mail_from', 'smtp_connector_for_wp_mail_from', 99);
function smtp_connector_for_wp_mail_from($from_email) {
    $configured_email = smtp_connector_for_wp_get_settings()['from_email'];
    if (!smtp_connector_for_wp_is_configured() || !is_email($configured_email)) {
        return $from_email;
    }
    return $configured_email;
}

// An empty From Name uses the site title, as suggested on the settings screen.
add_filter('wp_mail_from_name', 'smtp_connector_for_wp_mail_from_name', 99);
function smtp_connector_for_wp_mail_from_name($from_name) {
    if (!smtp_connector_for_wp_is_configured()) {
        return $from_name;
    }
    $configured_name = smtp_connector_for_wp_get_settings()['from_name'];
    if ('' === $configured_name) {
        $configured_name = wp_specialchars_decode(get_bloginfo('name'), ENT_QUOTES);
    }
    return '' !== $configured_name ? $configured_name : $from_name;
}

// Encrypt the SMTP password exactly once per save. A sanitize callback can't do this: WordPress
// runs those twice when an option is saved for the first time.
add_filter('pre_update_option_smtp_connector_for_wp_password', 'smtp_connector_for_wp_pre_update_password', 10, 2);
function smtp_connector_for_wp_pre_update_password($value, $old_value) {
    $value = is_string($value) ? $value : '';

    // An empty field keeps the saved password, and so does re-saving the stored value unchanged.
    if ('' === $value || $value === $old_value) {
        return $old_value;
    }
    // Already encrypted for this site.
    if (smtp_connector_for_wp_is_encrypted_value($value) && false !== smtp_connector_for_wp_decrypt_password($value)) {
        return $value;
    }

    $encrypted = smtp_connector_for_wp_encrypted_password($value);
    return false === $encrypted ? $old_value : $encrypted;
}

// add_option() (used by `wp option add` and some import tools) has no pre-filter: encrypt right after.
add_action('add_option_smtp_connector_for_wp_password', 'smtp_connector_for_wp_encrypt_added_password', 10, 2);
function smtp_connector_for_wp_encrypt_added_password($option, $value) {
    if (!is_string($value) || '' === $value || smtp_connector_for_wp_is_encrypted_value($value)) {
        return;
    }
    // A value saved by 1.1–1.3.x (e.g. from a backup) is converted, anything else is a plain password.
    $legacy    = smtp_connector_for_wp_decrypt_password($value);
    $encrypted = smtp_connector_for_wp_encrypted_password(false === $legacy ? $value : $legacy);
    if (false !== $encrypted) {
        update_option($option, $encrypted);
    }
}

/**
 * The saved SMTP password, or false when it can't be decrypted.
 *
 * @return string|false
 */
function smtp_connector_for_wp_get_smtp_password() {
    static $passwords = [];

    $stored = (string) get_option('smtp_connector_for_wp_password', '');
    if (!array_key_exists($stored, $passwords)) {
        $passwords[$stored] = smtp_connector_for_wp_decrypt_password($stored);
    }
    return $passwords[$stored];
}

/**
 * Whether SMTP needs the saved password but it can't be decrypted (security keys changed, site moved).
 *
 * @return bool
 */
function smtp_connector_for_wp_password_unreadable() {
    return smtp_connector_for_wp_is_configured()
        && smtp_connector_for_wp_get_settings()['auth']
        && false === smtp_connector_for_wp_get_smtp_password();
}

// When the saved password can't be decrypted, stop before connecting and say why, instead of
// logging in to the SMTP server with an empty or wrong password.
add_filter('pre_wp_mail', 'smtp_connector_for_wp_check_password_before_sending', 10, 2);
function smtp_connector_for_wp_check_password_before_sending($return, $atts) {
    if (null !== $return || !smtp_connector_for_wp_password_unreadable()) {
        return $return;
    }

    $mail_data = [];
    foreach (['to', 'subject', 'message', 'headers', 'attachments'] as $key) {
        $mail_data[$key] = isset($atts[$key]) ? $atts[$key] : '';
    }
    $message = __('SMTP Connector could not decrypt the saved SMTP password, so the email was not sent. This happens when the security keys in wp-config.php change or the site is moved. Please enter the password again in Settings > SMTP Connector.', 'smtp-connector');

    /** This action is documented in wp-includes/pluggable.php */
    do_action('wp_mail_failed', new WP_Error('wp_mail_failed', $message, $mail_data)); // phpcs:ignore WordPress.NamingConventions.PrefixAllGlobals.NonPrefixedHooknameFound -- Core hook, fired the way wp_mail() fires it.

    return false;
}

/*
 * Email log
 */

add_action('wp_mail_succeeded', 'smtp_connector_for_wp_log_sent_email');
function smtp_connector_for_wp_log_sent_email($mail_data) {
    smtp_connector_for_wp_insert_log((array) $mail_data, 'Sent', '');
}

add_action('wp_mail_failed', 'smtp_connector_for_wp_log_failed_email');
function smtp_connector_for_wp_log_failed_email($error) {
    if (!is_wp_error($error)) {
        return;
    }
    $mail_data = $error->get_error_data();
    smtp_connector_for_wp_insert_log(is_array($mail_data) ? $mail_data : [], 'Fail', $error->get_error_message());
}

/**
 * Stores one email in the log.
 *
 * @param array  $mail_data Email data from wp_mail(): to, subject, message, headers, attachments.
 * @param string $status    'Sent' or 'Fail'.
 * @param string $error     Error message for failed emails.
 */
function smtp_connector_for_wp_insert_log(array $mail_data, $status, $error) {
    global $wpdb;

    if (!smtp_connector_for_wp_get_settings()['enable_log']) {
        return;
    }
    // The table is created on the first page load after activation or an update.
    if (SMTP_CONNECTOR_FOR_WP_DB_VERSION !== get_option('smtp_connector_for_wp_db_version')) {
        return;
    }

    $row = [
        'email_to' => smtp_connector_for_wp_format_recipients(isset($mail_data['to']) ? $mail_data['to'] : []),
        'subject'  => isset($mail_data['subject']) ? (string) $mail_data['subject'] : '',
        'content'  => isset($mail_data['message']) ? (string) $mail_data['message'] : '',
        'status'   => $status,
        'error'    => (string) $error,
        'sent_at'  => current_time('mysql'),
    ];
    // Invalid UTF-8 would make the database refuse the whole row.
    foreach (['email_to', 'subject', 'content', 'error'] as $field) {
        $row[$field] = wp_check_invalid_utf8($row[$field], true);
    }

    $suppress = $wpdb->suppress_errors(true);
    // phpcs:ignore WordPress.DB.DirectDatabaseQuery.DirectQuery -- Writes to the plugin's own log table.
    $inserted = $wpdb->insert(smtp_connector_for_wp_log_table(), $row, ['%s', '%s', '%s', '%s', '%s', '%s']);
    $wpdb->suppress_errors($suppress);

    if (false === $inserted && !smtp_connector_for_wp_log_table_exists()) {
        // The table was removed: create it again on the next page load.
        delete_option('smtp_connector_for_wp_db_version');
    }
}

/**
 * Recipient list as shown in the log, e.g. "Jane Doe <jane@example.com>, bob@example.net".
 *
 * @param string|string[] $to Recipients passed to wp_mail().
 * @return string At most 255 characters (the column size).
 */
function smtp_connector_for_wp_format_recipients($to) {
    if (!is_array($to)) {
        $to = explode(',', (string) $to);
    }

    $recipients = [];
    foreach ($to as $recipient) {
        $recipient = trim((string) $recipient);
        if ('' !== $recipient) {
            $recipients[] = $recipient;
        }
    }

    $list = implode(', ', $recipients);
    if (mb_strlen($list) > 255) {
        $list = mb_substr($list, 0, 252) . '...';
    }
    return $list;
}

// Delete logs older than the retention period, in batches so large logs don't block a request.
add_action('smtp_connector_for_wp_cleanup_logs', 'smtp_connector_for_wp_delete_old_logs');
function smtp_connector_for_wp_delete_old_logs() {
    global $wpdb;

    // sent_at is stored in the site's timezone (current_time('mysql')), so use the same clock here.
    $cutoff     = wp_date('Y-m-d H:i:s', time() - smtp_connector_for_wp_log_retention_days() * DAY_IN_SECONDS);
    $batch_size = 500;
    $batches    = 0;

    do {
        // phpcs:ignore WordPress.DB.DirectDatabaseQuery.DirectQuery, WordPress.DB.DirectDatabaseQuery.NoCaching -- Plugin's own log table.
        $ids = $wpdb->get_col($wpdb->prepare("SELECT id FROM {$wpdb->prefix}smtp_connector_for_wp_logs WHERE sent_at < %s ORDER BY id ASC LIMIT %d", $cutoff, $batch_size));
        if (empty($ids)) {
            break;
        }
        $ids = array_map('absint', $ids);
        // phpcs:ignore WordPress.DB.DirectDatabaseQuery.DirectQuery, WordPress.DB.DirectDatabaseQuery.NoCaching -- Plugin's own log table.
        $wpdb->query($wpdb->prepare("DELETE FROM {$wpdb->prefix}smtp_connector_for_wp_logs WHERE id IN (" . implode(',', array_fill(0, count($ids), '%d')) . ')', $ids));
        $batches++;
    } while (count($ids) === $batch_size && $batches < 50);

    if (50 === $batches) {
        // More old rows left: continue in a few minutes.
        wp_schedule_single_event(time() + 5 * MINUTE_IN_SECONDS, 'smtp_connector_for_wp_cleanup_logs');
    }
}
