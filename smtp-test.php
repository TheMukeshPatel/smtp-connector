<?php
// Exit if accessed directly
if (!defined('ABSPATH')) {
    exit;
}

// Include the encryption and decryption functions
require_once(plugin_dir_path(__FILE__) . 'includes/encryption-functions.php');

// Include settings page only if in admin
if (is_admin()) {
    require_once(plugin_dir_path(__FILE__) . 'includes/settings-page.php');
}

if (isset($_POST['smtp_test_email'])) {
    $test_email = sanitize_email($_POST['smtp_test_email']);

    // Attempt to send a test email
    $result = smtp_connector_for_wp_send_test_email($test_email);

    // Display success or error message
    if ($result === true) {
        $message = __('Success, email sent. Please check your email.', 'smtp_connector_for_wp');
        $message_type = 'success';
    } else {
        $message = __('Unable to send email. Please recheck SMTP information.', 'smtp_connector_for_wp');
        $message_type = 'error';
    }
}
?>

<div class="wrap">
    <h2><?php _e('SMTP Test', 'smtp_connector_for_wp'); ?></h2>
    <p>Make sure to save SMTP information on the <a href="<?php echo esc_url(admin_url('options-general.php?page=smtp-connector-for-wp')); ?>">SMTP Connector Settings page</a> before testing.</p>
    <?php if (!empty($message)) : ?>
        <div class="notice notice-<?php echo $message_type; ?>">
            <p><?php echo $message; ?></p>
        </div>
    <?php endif; ?>
    <form method="post">
        <table class="form-table">
            <tr>
                <th scope="row"><?php _e('Test Email Address:', 'smtp_connector_for_wp'); ?></th>
                <td>
                    <input type="email" name="smtp_test_email" placeholder="<?php _e('Enter test email address', 'smtp_connector_for_wp'); ?>" required>
                </td>
            </tr>
        </table>
        <?php wp_nonce_field('smtp_test_nonce', 'smtp_test_nonce'); ?>
        <?php submit_button(__('Send Test Email', 'smtp_connector_for_wp')); ?>
    </form>
</div>
