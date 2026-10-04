<?php
/**
 * Removes all SMTP Connector data when the plugin is deleted.
 *
 * @package SMTP_Connector
 */

// If uninstall not called from WordPress, exit
if (!defined('WP_UNINSTALL_PLUGIN')) {
    exit;
}

require_once __DIR__ . '/includes/common.php';

/**
 * Deletes the settings, log table, transients and scheduled event of the current site.
 */
function smtp_connector_for_wp_uninstall_site() {
    global $wpdb;

    foreach (array_merge(smtp_connector_for_wp_setting_names(), smtp_connector_for_wp_internal_option_names()) as $option) {
        delete_option($option);
    }
    delete_transient('smtp-connector-for-wp-activation-notice');

    // Test email results are stored per user for a few minutes.
    // phpcs:ignore WordPress.DB.DirectDatabaseQuery.DirectQuery, WordPress.DB.DirectDatabaseQuery.NoCaching -- Uninstall clean-up.
    $wpdb->query($wpdb->prepare(
        "DELETE FROM {$wpdb->options} WHERE option_name LIKE %s OR option_name LIKE %s",
        $wpdb->esc_like('_transient_smtp_connector_for_wp_test_result_') . '%',
        $wpdb->esc_like('_transient_timeout_smtp_connector_for_wp_test_result_') . '%'
    ));

    wp_clear_scheduled_hook('smtp_connector_for_wp_cleanup_logs');

    // phpcs:ignore WordPress.DB.DirectDatabaseQuery.DirectQuery, WordPress.DB.DirectDatabaseQuery.NoCaching, WordPress.DB.DirectDatabaseQuery.SchemaChange -- Removes the plugin's own table.
    $wpdb->query("DROP TABLE IF EXISTS {$wpdb->prefix}smtp_connector_for_wp_logs");
}

if (is_multisite()) {
    foreach (get_sites(['fields' => 'ids', 'number' => 0]) as $smtp_connector_for_wp_site_id) {
        switch_to_blog($smtp_connector_for_wp_site_id);
        smtp_connector_for_wp_uninstall_site();
        restore_current_blog();
    }
} else {
    smtp_connector_for_wp_uninstall_site();
}
