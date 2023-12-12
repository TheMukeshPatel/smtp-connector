<?php

// Exit if accessed directly
if (!defined('ABSPATH')) {
    exit;
}

// Include the encryption and decryption functions
require_once(plugin_dir_path(__FILE__) . 'encryption-functions.php');

// Create custom plugin settings menu
add_action('admin_menu', 'smtp_connector_for_wp_create_menu');

function smtp_connector_for_wp_create_menu()
{
    add_options_page('SMTP Connector Settings', 'SMTP Connector', 'manage_options', 'smtp-connector-for-wp', 'smtp_connector_for_wp_settings_page');
    add_action('admin_init', 'smtp_connector_for_wp_settings_register');
}

function smtp_connector_for_wp_settings_register()
{
    // Sanitization function
    function smtp_connector_for_wp_sanitize_option($input)
    {
        return sanitize_text_field($input);
    }

    // Register each setting with sanitization
    register_setting('smtp-connector-for-wp-settings-group', 'smtp_connector_for_wp_host', 'smtp_connector_for_wp_sanitize_option');
    register_setting('smtp-connector-for-wp-settings-group', 'smtp_connector_for_wp_from_name', 'smtp_connector_for_wp_sanitize_option');
    register_setting('smtp-connector-for-wp-settings-group', 'smtp_connector_for_wp_username', 'smtp_connector_for_wp_sanitize_option');

    // Use encryption for password
    register_setting('smtp-connector-for-wp-settings-group', 'smtp_connector_for_wp_password', 'smtp_connector_for_wp_encrypt_password');
    register_setting('smtp-connector-for-wp-settings-group', 'smtp_connector_for_wp_security', 'smtp_connector_for_wp_sanitize_option');
    register_setting('smtp-connector-for-wp-settings-group', 'smtp_connector_for_wp_port', 'smtp_connector_for_wp_sanitize_option');
}

function smtp_connector_for_wp_encrypt_password($password)
{
    return encrypt_password($password);
}

function smtp_connector_for_wp_settings_page()
{
    if (!current_user_can('manage_options')) {
        wp_die(__('You do not have sufficient permissions to access this page.'));
    }
    ?>
    <div class="wrap">
        <h2>SMTP Connector Settings</h2>
        <p>Enter your SMTP details below. Check <a target="_blank" href="https://mpateldigital.com/smtp-connector/">this
                tutorial</a> to know more about configuration</p>
        <div id="poststuff">
            <div id="post-body" class="metabox-holder columns-2">
                <!-- Main Content Area -->
                <div id="post-body-content">
                    <form method="post" action="options.php">
                        <?php settings_fields('smtp-connector-for-wp-settings-group'); ?>
                        <?php do_settings_sections('smtp-connector-for-wp-settings-group'); ?>
                        <?php settings_fields('smtp-connector-for-wp-settings-group'); ?>
                        <table class="form-table">
                            <tr valign="top">
                                <th scope="row">SMTP Host</th>
                                <td><input type="text" name="smtp_connector_for_wp_host"
                                        value="<?php echo esc_attr(get_option('smtp_connector_for_wp_host')); ?>" /></td>
                            </tr>

                            <tr valign="top">
                                <th scope="row">From Name</th>
                                <td><input type="text" placeholder="Enter Website Name"
                                        name="smtp_connector_for_wp_from_name"
                                        value="<?php echo esc_attr(get_option('smtp_connector_for_wp_from_name')); ?>" />
                                </td>
                            </tr>

                            <tr valign="top">
                                <th scope="row">SMTP Username</th>
                                <td><input type="text" name="smtp_connector_for_wp_username"
                                        value="<?php echo esc_attr(get_option('smtp_connector_for_wp_username')); ?>" />
                                </td>
                            </tr>

                            <tr valign="top">
                                <th scope="row">SMTP Password</th>
                                <td><input type="password" name="smtp_connector_for_wp_password"
                                        value="<?php echo decrypt_password(get_option('smtp_connector_for_wp_password')); ?>" />
                                    <span>Use App Password for <a target="_blank"
                                            href="https://myaccount.google.com/apppasswords">Gmail</a> and <a
                                            target="_blank"
                                            href="https://help.zoho.com/portal/en/kb/bigin/channels/email/articles/generate-an-app-specific-password#To_generate_app_specific_password_for_Zoho_Mail">Zoho</a>.</span>
                                </td>

                            </tr>

                            <tr valign="top">
                                <th scope="row">SMTP Security</th>
                                <td>
                                    <select name="smtp_connector_for_wp_security">
                                        <option value="tls" <?php selected(get_option('smtp_connector_for_wp_security'), 'tls'); ?>>TLS</option>
                                        <option value="ssl" <?php selected(get_option('smtp_connector_for_wp_security'), 'ssl'); ?>>SSL</option>
                                    </select>
                                    <span>TLS is recommended, use this option for Gmail SMTP etc.</span>
                                </td>
                            </tr>

                            <tr valign="top">
                                <th scope="row">SMTP Port</th>
                                <td><input type="number" placeholder="587 Most in Cases like Gmail"
                                        name="smtp_connector_for_wp_port"
                                        value="<?php echo esc_attr(get_option('smtp_connector_for_wp_port')); ?>" />
                                    <span>Use 587 most in cases. Gmail, Zoho, Sandinblue, Sandgrid etc use this port.</span>
                                </td>
                            </tr>
                        </table>
                        <?php wp_nonce_field('smtp_connector_for_wp_save_settings', 'smtp_connector_for_wp_nonce'); ?>
                        <?php submit_button(); ?>
                    </form>
                </div>

                <!-- Sidebar Area -->
                <div id="postbox-container-1" class="postbox-container">
                    <div class="postbox">
                        <h3><span>Must Check</span></h3>
                        <div class="inside">
                            <ul>
                                <li><a href="https://mpateldigital.com/" target="_blank">Official Site</a></li>
                                <li><a href="https://mpateldigital.com/contact-us" target="_blank">Support</a></li>
                            </ul>
                        </div>
                    </div>
                </div>
            </div>
        </div>
    </div>
    <?php
}
?>
