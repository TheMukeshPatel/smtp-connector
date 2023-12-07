<?php
/*
Plugin Name: SMTP Connector
Description: A 100% Free SMTP Plugin that Allows you to set a custom SMTP for sending emails in WordPress. Connect Gmail, MailGun, Amazon SES, SendinBlue, Zoho, and More to send Emails in WordPress.
Version: 1.0.0
Author: Mukesh Patel
Author URI: https://mpateldigital.com/
Plugin URI: https://mpateldigital.com/smtp-connector/
License: GPLv3
Text Domain: smtp-connector
*/

// Exit if accessed directly
if (!defined('ABSPATH')) {
    exit;
}

/* Register activation hook. */
register_activation_hook(__FILE__, 'smtp_connector_for_wp_activation_hook');

/**
 * Runs only when the plugin is activated.
 * @since 1.0.0
 */
function smtp_connector_for_wp_activation_hook()
{

    /* Create transient data */
    set_transient('smtp_connector_for_wp-activation-notice', true, 5);
}

/* Add admin notice */
add_action('admin_notices', 'smtp_connector_for_wp_notice');

/**
 * Admin Notice on Activation of Plugin
 * @since 1.0.0
 */
function smtp_connector_for_wp_notice()
{

    /* Check for transient, if available display notice */
    if (get_transient('smtp_connector_for_wp-activation-notice')) {
        ?>
        <style>
            div#message.updated {
                display: none;
            }
        </style>
        <div class="updated notice is-dismissible">
            <p>
                <?php _e('😊 Thank you for using Simple SMTP for WP. Please enter your SMTP details on <b><a href="options-general.php?page=smtp-connector-for-wp">Settings page</a></b>', 'smtp_connector_for_wp'); ?>
            </p>
        </div>
        <?php
        /* Delete transient, only display this notice once. */
        delete_transient('smtp_connector_for_wp-activation-notice');
    }
}


// Include settings page
require_once(plugin_dir_path(__FILE__) . 'includes/settings-page.php');

// Hook into phpmailer_init
add_action('phpmailer_init', 'smtp_connector_for_wp_custom_phpmailer');


function smtp_connector_for_wp_custom_phpmailer($phpmailer)
{
    $phpmailer->isSMTP();
    $phpmailer->Host = get_option('smtp_connector_for_wp_host');
    $phpmailer->SMTPAuth = true;
    $phpmailer->FromName = get_option('smtp_connector_for_wp_from_name');
    $phpmailer->Username = get_option('smtp_connector_for_wp_username');
    $phpmailer->Password = get_option('smtp_connector_for_wp_password');
    $phpmailer->SMTPSecure = get_option('smtp_connector_for_wp_security');
    $phpmailer->Port = get_option('smtp_connector_for_wp_port');
}

// Add settings link on plugin page
function smtp_connector_for_wp_settings_page_link($links)
{
    $settings_link = '<a href="options-general.php?page=smtp-connector-for-wp">Settings</a>';
    array_unshift($links, $settings_link);
    return $links;
}

$plugin = plugin_basename(__FILE__);
add_filter("plugin_action_links_$plugin", 'smtp_connector_for_wp_settings_page_link');
