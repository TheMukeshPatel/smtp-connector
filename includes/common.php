<?php
/**
 * Settings, log table and install/upgrade helpers.
 *
 * Shared by the plugin and by uninstall.php, so this file only defines functions.
 *
 * @package SMTP_Connector
 */

// Exit if accessed directly
if (!defined('ABSPATH')) {
    exit;
}

/**
 * SMTP server, login and sender options (removed by "Reset Settings").
 *
 * @return string[]
 */
function smtp_connector_for_wp_smtp_setting_names() {
    return [
        'smtp_connector_for_wp_host',
        'smtp_connector_for_wp_port',
        'smtp_connector_for_wp_security',
        'smtp_connector_for_wp_auth',
        'smtp_connector_for_wp_username',
        'smtp_connector_for_wp_password',
        'smtp_connector_for_wp_from_email',
        'smtp_connector_for_wp_from_name',
    ];
}

/**
 * Every option saved from the Settings screen.
 *
 * @return string[]
 */
function smtp_connector_for_wp_setting_names() {
    return array_merge(smtp_connector_for_wp_smtp_setting_names(), [
        'smtp_connector_for_wp_enable_log',
        'smtp_connector_for_wp_log_retention',
    ]);
}

/**
 * Options the plugin keeps for itself.
 *
 * @return string[]
 */
function smtp_connector_for_wp_internal_option_names() {
    return [
        'smtp_connector_for_wp_db_version',
        'smtp_connector_for_wp_install_lock',
    ];
}

/**
 * Capability needed to read and clear the email log.
 *
 * On multisite only network administrators get it by default: a site administrator could
 * otherwise request a password reset for a super admin on their site and read the link.
 *
 * @return string
 */
function smtp_connector_for_wp_log_capability() {
    /**
     * Filters the capability required to view and clear the email log.
     *
     * @param string $capability Default 'manage_options', or 'manage_network_options' on multisite.
     */
    return (string) apply_filters('smtp_connector_for_wp_log_capability', is_multisite() ? 'manage_network_options' : 'manage_options');
}

/**
 * Current settings with defaults filled in.
 *
 * @return array
 */
function smtp_connector_for_wp_get_settings() {
    $security = (string) get_option('smtp_connector_for_wp_security', 'tls');
    if (!in_array($security, ['tls', 'ssl', 'none'], true)) {
        $security = 'tls';
    }

    return [
        'host'       => trim((string) get_option('smtp_connector_for_wp_host', '')),
        'port'       => absint(get_option('smtp_connector_for_wp_port', 587)),
        'security'   => $security,
        'auth'       => '0' !== (string) get_option('smtp_connector_for_wp_auth', '1'),
        'username'   => (string) get_option('smtp_connector_for_wp_username', ''),
        'from_email' => (string) get_option('smtp_connector_for_wp_from_email', ''),
        'from_name'  => (string) get_option('smtp_connector_for_wp_from_name', ''),
        'enable_log' => '0' !== (string) get_option('smtp_connector_for_wp_enable_log', '1'),
    ];
}

/**
 * Number of days email logs are kept.
 *
 * @return int
 */
function smtp_connector_for_wp_log_retention_days() {
    $days = absint(get_option('smtp_connector_for_wp_log_retention', 30));
    return ($days >= 1 && $days <= 3650) ? $days : 30;
}

/**
 * Settings that still have to be filled in before SMTP can be used.
 *
 * The From Email is not required: sites set up with 1.2.0 or older never saved one and keep
 * sending through SMTP with WordPress's default sender.
 *
 * @return string[] Keys: host, port, username, password.
 */
function smtp_connector_for_wp_missing_settings() {
    $settings = smtp_connector_for_wp_get_settings();
    $missing  = [];

    if ('' === $settings['host']) {
        $missing[] = 'host';
    }
    if ($settings['port'] < 1 || $settings['port'] > 65535) {
        $missing[] = 'port';
    }
    if ($settings['auth'] && '' === $settings['username']) {
        $missing[] = 'username';
    }
    if ($settings['auth'] && '' === (string) get_option('smtp_connector_for_wp_password', '')) {
        $missing[] = 'password';
    }

    return $missing;
}

/**
 * Whether enough is configured to send through SMTP. Until then WordPress keeps its default mailer.
 *
 * @return bool
 */
function smtp_connector_for_wp_is_configured() {
    return [] === smtp_connector_for_wp_missing_settings();
}

/**
 * Full name of the email log table.
 *
 * @return string
 */
function smtp_connector_for_wp_log_table() {
    global $wpdb;
    return $wpdb->prefix . 'smtp_connector_for_wp_logs';
}

/**
 * Whether the email log table exists.
 *
 * @return bool
 */
function smtp_connector_for_wp_log_table_exists() {
    global $wpdb;
    $table = smtp_connector_for_wp_log_table();
    // phpcs:ignore WordPress.DB.DirectDatabaseQuery.DirectQuery, WordPress.DB.DirectDatabaseQuery.NoCaching -- Schema check, result must be live.
    $found = $wpdb->get_var($wpdb->prepare('SHOW TABLES LIKE %s', $wpdb->esc_like($table)));
    // Case-insensitive: with lower_case_table_names=1 MySQL reports the name in lower case.
    return is_string($found) && 0 === strcasecmp($found, $table);
}

/**
 * Creates or upgrades the log table, migrates stored data and schedules the log cleanup.
 *
 * Runs on activation and, because updates don't run activation hooks, whenever the stored
 * schema version differs from SMTP_CONNECTOR_FOR_WP_DB_VERSION.
 */
function smtp_connector_for_wp_install() {
    global $wpdb;
    require_once ABSPATH . 'wp-admin/includes/upgrade.php';

    // A table from 1.3.x may hold months of logs that were never cleaned up.
    $is_update = smtp_connector_for_wp_log_table_exists();

    smtp_connector_for_wp_drop_legacy_indexes();

    $table           = smtp_connector_for_wp_log_table();
    $charset_collate = $wpdb->get_charset_collate();

    // dbDelta() needs one column per line, two spaces after PRIMARY KEY and KEY instead of INDEX.
    $sql = "CREATE TABLE {$table} (
  id bigint(20) unsigned NOT NULL AUTO_INCREMENT,
  email_to varchar(255) NOT NULL,
  subject text NOT NULL,
  content longtext NOT NULL,
  status varchar(10) NOT NULL,
  error text,
  sent_at datetime NOT NULL,
  PRIMARY KEY  (id),
  KEY sent_at_idx (sent_at)
) {$charset_collate};";
    dbDelta($sql);

    smtp_connector_for_wp_upgrade_password_storage();

    // Existing sites keep their behaviour: SMTP login on, email log on, 30 days.
    add_option('smtp_connector_for_wp_auth', '1');
    add_option('smtp_connector_for_wp_enable_log', '1');
    add_option('smtp_connector_for_wp_log_retention', 30);

    // After an update, wait a week before the first clean-up so old logs aren't deleted before the
    // admin had a chance to change "Keep logs for".
    smtp_connector_for_wp_schedule_cleanup($is_update ? 7 * DAY_IN_SECONDS : 0);
    update_option('smtp_connector_for_wp_db_version', SMTP_CONNECTOR_FOR_WP_DB_VERSION);
}

/**
 * Runs the install routine once after the plugin files were updated.
 */
function smtp_connector_for_wp_maybe_upgrade() {
    if (SMTP_CONNECTOR_FOR_WP_DB_VERSION === get_option('smtp_connector_for_wp_db_version')) {
        return;
    }

    // Lowers the chance that two requests arriving together both run the upgrade (doing it twice
    // is harmless, just wasted work).
    if (!add_option('smtp_connector_for_wp_install_lock', time(), '', false)) {
        if ((int) get_option('smtp_connector_for_wp_install_lock') > time() - 10 * MINUTE_IN_SECONDS) {
            return;
        }
        update_option('smtp_connector_for_wp_install_lock', time(), false);
    }

    smtp_connector_for_wp_install();
    delete_option('smtp_connector_for_wp_install_lock');
}

/**
 * Version 1.3.x created two indexes that no query uses; the email_to one is also longer than
 * older MySQL versions allow.
 */
function smtp_connector_for_wp_drop_legacy_indexes() {
    global $wpdb;

    $suppress = $wpdb->suppress_errors(true);
    // phpcs:ignore WordPress.DB.DirectDatabaseQuery.DirectQuery, WordPress.DB.DirectDatabaseQuery.NoCaching -- Schema check during install.
    $rows = (array) $wpdb->get_results("SHOW INDEX FROM {$wpdb->prefix}smtp_connector_for_wp_logs", ARRAY_A);
    $wpdb->suppress_errors($suppress);

    $indexes = wp_list_pluck($rows, 'Key_name');
    if (in_array('email_to_idx', $indexes, true)) {
        // phpcs:ignore WordPress.DB.DirectDatabaseQuery.DirectQuery, WordPress.DB.DirectDatabaseQuery.NoCaching, WordPress.DB.DirectDatabaseQuery.SchemaChange -- One-time schema upgrade.
        $wpdb->query("ALTER TABLE {$wpdb->prefix}smtp_connector_for_wp_logs DROP INDEX email_to_idx");
    }
    if (in_array('status_idx', $indexes, true)) {
        // phpcs:ignore WordPress.DB.DirectDatabaseQuery.DirectQuery, WordPress.DB.DirectDatabaseQuery.NoCaching, WordPress.DB.DirectDatabaseQuery.SchemaChange -- One-time schema upgrade.
        $wpdb->query("ALTER TABLE {$wpdb->prefix}smtp_connector_for_wp_logs DROP INDEX status_idx");
    }
}

/**
 * Re-saves a password stored by 1.1–1.3.x in the current format (repairing the double
 * encryption 1.3.x applied on the first save).
 */
function smtp_connector_for_wp_upgrade_password_storage() {
    $stored = get_option('smtp_connector_for_wp_password', '');
    if (!is_string($stored) || '' === $stored || smtp_connector_for_wp_is_encrypted_value($stored)) {
        return;
    }

    $password = smtp_connector_for_wp_decrypt_password($stored);
    if (false === $password) {
        // Saved with security keys that have changed since; the admin notice asks for it again.
        return;
    }

    // The pre_update_option filter encrypts it.
    update_option('smtp_connector_for_wp_password', $password);
}

/**
 * Makes sure the daily log cleanup is scheduled.
 *
 * @param int $delay Seconds until the first run.
 */
function smtp_connector_for_wp_schedule_cleanup($delay = 0) {
    if (!wp_next_scheduled('smtp_connector_for_wp_cleanup_logs')) {
        wp_schedule_event(time() + absint($delay), 'daily', 'smtp_connector_for_wp_cleanup_logs');
    }
}
