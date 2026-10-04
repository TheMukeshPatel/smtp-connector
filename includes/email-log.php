<?php
/**
 * Email Log screen (Settings > Email Log).
 *
 * @package SMTP_Connector
 */

// Exit if accessed directly
if (!defined('ABSPATH')) {
    exit;
}

add_action('admin_menu', 'smtp_connector_for_wp_add_log_menu');
function smtp_connector_for_wp_add_log_menu() {
    $hook = add_options_page(
        __('Email Log', 'smtp-connector'),
        __('Email Log', 'smtp-connector'),
        smtp_connector_for_wp_log_capability(),
        'smtp-email-log',
        'smtp_connector_for_wp_log_page'
    );
    if ($hook) {
        add_action('load-' . $hook, 'smtp_connector_for_wp_load_admin_assets');
    }
}

/**
 * Translated label for a stored status.
 *
 * @param string $status 'Sent', 'Fail' or 'Pending' (rows written by 1.3.x).
 * @return string
 */
function smtp_connector_for_wp_status_label($status) {
    switch ($status) {
        case 'Sent':
            return __('Sent', 'smtp-connector');
        case 'Fail':
            return __('Failed', 'smtp-connector');
        default:
            return __('Unknown', 'smtp-connector');
    }
}

/**
 * Date in the site's date and time format.
 *
 * @param string $mysql_date Stored sent_at value.
 * @return string
 */
function smtp_connector_for_wp_format_log_date($mysql_date) {
    return mysql2date(get_option('date_format') . ' ' . get_option('time_format'), $mysql_date);
}

function smtp_connector_for_wp_log_page() {
    global $wpdb;

    if (!current_user_can(smtp_connector_for_wp_log_capability())) {
        wp_die(esc_html__('You do not have sufficient permissions to access this page.', 'smtp-connector'));
    }

    $per_page_options = [20, 50, 100];
    // phpcs:disable WordPress.Security.NonceVerification.Recommended -- Read-only list navigation.
    $per_page     = isset($_GET['per_page']) ? absint($_GET['per_page']) : 20;
    $current_page = isset($_GET['paged']) ? max(1, absint($_GET['paged'])) : 1;
    $cleared      = isset($_GET['cleared']);
    // phpcs:enable WordPress.Security.NonceVerification.Recommended
    if (!in_array($per_page, $per_page_options, true)) {
        $per_page = 20;
    }

    // phpcs:ignore WordPress.DB.DirectDatabaseQuery.DirectQuery, WordPress.DB.DirectDatabaseQuery.NoCaching -- Plugin's own log table.
    $total        = (int) $wpdb->get_var("SELECT COUNT(*) FROM {$wpdb->prefix}smtp_connector_for_wp_logs");
    $total_pages  = max(1, (int) ceil($total / $per_page));
    $current_page = min($current_page, $total_pages);

    // Ordered by the unique id so rows sent in the same second can't repeat or vanish between pages.
    // phpcs:ignore WordPress.DB.DirectDatabaseQuery.DirectQuery, WordPress.DB.DirectDatabaseQuery.NoCaching -- Plugin's own log table.
    $logs = $wpdb->get_results($wpdb->prepare("SELECT id, email_to, subject, status, error, sent_at FROM {$wpdb->prefix}smtp_connector_for_wp_logs ORDER BY id DESC LIMIT %d OFFSET %d", $per_page, ($current_page - 1) * $per_page), ARRAY_A);

    $settings_url = admin_url('options-general.php?page=smtp-connector-for-wp');
    ?>
    <div class="wrap smtp-connector">
        <?php smtp_connector_for_wp_render_header('log'); ?>

        <?php if ($cleared) : ?>
            <div class="notice notice-success"><p><?php esc_html_e('All email logs have been deleted.', 'smtp-connector'); ?></p></div>
        <?php endif; ?>

        <?php if (!smtp_connector_for_wp_get_settings()['enable_log']) : ?>
            <div class="notice notice-info inline">
                <p>
                    <?php esc_html_e('Email logging is turned off, so new emails are not recorded.', 'smtp-connector'); ?>
                    <a href="<?php echo esc_url($settings_url); ?>"><?php esc_html_e('Turn it on in the settings', 'smtp-connector'); ?></a>
                </p>
            </div>
        <?php endif; ?>

        <section class="smtp-connector-card smtp-connector-log-card" aria-label="<?php esc_attr_e('Email log', 'smtp-connector'); ?>">
        <p class="smtp-connector__intro">
            <?php
            echo esc_html(sprintf(
                /* translators: %d: Number of days. */
                _n('Emails sent by this site. Logs older than %d day are deleted automatically.', 'Emails sent by this site. Logs older than %d days are deleted automatically.', smtp_connector_for_wp_log_retention_days(), 'smtp-connector'),
                smtp_connector_for_wp_log_retention_days()
            ));
            ?>
        </p>

        <div class="smtp-connector-log-toolbar">
            <form method="get">
                <input type="hidden" name="page" value="smtp-email-log" />
                <label for="smtp-connector-per-page" class="screen-reader-text"><?php esc_html_e('Emails per page', 'smtp-connector'); ?></label>
                <select id="smtp-connector-per-page" name="per_page">
                    <?php foreach ($per_page_options as $option) : ?>
                        <option value="<?php echo esc_attr($option); ?>" <?php selected($option, $per_page); ?>>
                            <?php
                            /* translators: %s: Number of emails shown on one page. */
                            echo esc_html(sprintf(__('%s per page', 'smtp-connector'), number_format_i18n($option)));
                            ?>
                        </option>
                    <?php endforeach; ?>
                </select>
                <button type="submit" class="button"><?php esc_html_e('Apply', 'smtp-connector'); ?></button>
            </form>
            <?php if ($total > 0) : ?>
                <form method="post" action="<?php echo esc_url(admin_url('admin-post.php')); ?>" class="smtp-connector-clear-form">
                    <input type="hidden" name="action" value="smtp_connector_for_wp_clear_logs" />
                    <?php wp_nonce_field('smtp_connector_for_wp_clear_logs_nonce', 'smtp_connector_for_wp_clear_logs_nonce'); ?>
                    <?php submit_button(__('Clear logs', 'smtp-connector'), 'delete', 'smtp-connector-clear-logs', false); ?>
                </form>
            <?php endif; ?>
        </div>

        <div class="tablenav top<?php echo $total_pages > 1 ? '' : ' smtp-connector-tablenav--single'; ?>">
            <?php smtp_connector_for_wp_log_pagination($total, $per_page, $current_page, $total_pages); ?>
            <br class="clear" />
        </div>

        <table class="wp-list-table widefat fixed striped smtp-connector-log">
            <thead>
                <tr>
                    <th scope="col" class="column-primary column-subject"><?php esc_html_e('Subject', 'smtp-connector'); ?></th>
                    <th scope="col" class="column-to"><?php esc_html_e('To', 'smtp-connector'); ?></th>
                    <th scope="col" class="column-status"><?php esc_html_e('Status', 'smtp-connector'); ?></th>
                    <th scope="col" class="column-date"><?php esc_html_e('Date', 'smtp-connector'); ?></th>
                </tr>
            </thead>
            <tbody>
                <?php if ($logs) : ?>
                    <?php foreach ($logs as $log) : ?>
                        <?php $status_class = 'Sent' === $log['status'] ? 'sent' : ('Fail' === $log['status'] ? 'failed' : 'unknown'); ?>
                        <tr>
                            <td class="column-primary column-subject" data-colname="<?php esc_attr_e('Subject', 'smtp-connector'); ?>">
                                <strong>
                                    <button type="button" class="button-link smtp-view-email" data-email-id="<?php echo esc_attr($log['id']); ?>" aria-haspopup="dialog">
                                        <?php echo '' !== $log['subject'] ? esc_html($log['subject']) : esc_html__('(no subject)', 'smtp-connector'); ?>
                                    </button>
                                </strong>
                                <button type="button" class="toggle-row"><span class="screen-reader-text"><?php esc_html_e('Show more details', 'smtp-connector'); ?></span></button>
                            </td>
                            <td class="column-to" data-colname="<?php esc_attr_e('To', 'smtp-connector'); ?>"><?php echo esc_html($log['email_to']); ?></td>
                            <td class="column-status" data-colname="<?php esc_attr_e('Status', 'smtp-connector'); ?>">
                                <span class="smtp-connector-status smtp-connector-status--<?php echo esc_attr($status_class); ?>"><?php echo esc_html(smtp_connector_for_wp_status_label($log['status'])); ?></span>
                                <?php if (!empty($log['error'])) : ?>
                                    <p class="smtp-connector-error"><?php echo esc_html($log['error']); ?></p>
                                <?php endif; ?>
                            </td>
                            <td class="column-date" data-colname="<?php esc_attr_e('Date', 'smtp-connector'); ?>"><?php echo esc_html(smtp_connector_for_wp_format_log_date($log['sent_at'])); ?></td>
                        </tr>
                    <?php endforeach; ?>
                <?php else : ?>
                    <tr class="no-items"><td class="colspanchange" colspan="4"><?php esc_html_e('No emails have been logged yet.', 'smtp-connector'); ?></td></tr>
                <?php endif; ?>
            </tbody>
        </table>

        <div class="tablenav bottom">
            <?php smtp_connector_for_wp_log_pagination($total, $per_page, $current_page, $total_pages); ?>
            <br class="clear" />
        </div>
        </section>
    </div>

    <div id="smtp-email-modal" class="smtp-connector-modal" hidden>
        <div class="smtp-connector-modal__backdrop" data-smtp-close></div>
        <div class="smtp-connector-modal__dialog" role="dialog" aria-modal="true" aria-labelledby="smtp-email-modal-title">
            <div class="smtp-connector-modal__header">
                <h2 id="smtp-email-modal-title"></h2>
                <button type="button" class="smtp-connector-modal__close" data-smtp-close aria-label="<?php esc_attr_e('Close', 'smtp-connector'); ?>">
                    <span class="dashicons dashicons-no-alt" aria-hidden="true"></span>
                </button>
            </div>
            <dl class="smtp-connector-modal__meta"></dl>
            <div id="smtp-email-modal-body" class="smtp-connector-modal__body"></div>
        </div>
    </div>
    <?php
}

/**
 * Prints the item count and pagination links.
 */
function smtp_connector_for_wp_log_pagination($total, $per_page, $current_page, $total_pages) {
    echo '<div class="tablenav-pages' . ($total_pages > 1 ? '' : ' one-page') . '">';
    echo '<span class="displaying-num">' . esc_html(sprintf(
        /* translators: %s: Number of emails. */
        _n('%s email', '%s emails', $total, 'smtp-connector'),
        number_format_i18n($total)
    )) . '</span>';

    if ($total_pages > 1) {
        $links = paginate_links([
            'base'      => add_query_arg(['page' => 'smtp-email-log', 'per_page' => $per_page, 'paged' => '%#%'], admin_url('options-general.php')),
            'format'    => '',
            'current'   => $current_page,
            'total'     => $total_pages,
            'prev_text' => __('&laquo; Previous', 'smtp-connector'),
            'next_text' => __('Next &raquo;', 'smtp-connector'),
        ]);
        echo '<span class="pagination-links">' . wp_kses_post($links) . '</span>';
    }
    echo '</div>';
}

/**
 * Whether a message body is HTML (rendered in a sandboxed frame) or plain text.
 *
 * @param string $content Message body.
 * @return bool
 */
function smtp_connector_for_wp_is_html($content) {
    return (bool) preg_match('/<(html|body|div|p|br|table|span|a|img|h[1-6]|ul|ol|li|strong|em|b|i|center|font)[\s>\/]/i', $content);
}

// Returns one logged email for the View dialog.
add_action('wp_ajax_smtp_connector_for_wp_fetch_email_content', 'smtp_connector_for_wp_fetch_email_content');
function smtp_connector_for_wp_fetch_email_content() {
    global $wpdb;

    if (!current_user_can(smtp_connector_for_wp_log_capability())) {
        wp_send_json_error(['message' => __('You are not allowed to view this email.', 'smtp-connector')], 403);
    }
    if (!check_ajax_referer('smtp_connector_for_wp_nonce', 'nonce', false)) {
        wp_send_json_error(['message' => __('This page has expired. Please reload it and try again.', 'smtp-connector')], 403);
    }

    $email_id = isset($_POST['email_id']) ? absint($_POST['email_id']) : 0;
    // phpcs:ignore WordPress.DB.DirectDatabaseQuery.DirectQuery, WordPress.DB.DirectDatabaseQuery.NoCaching -- Plugin's own log table.
    $email = $email_id ? $wpdb->get_row($wpdb->prepare("SELECT email_to, subject, content, status, error, sent_at FROM {$wpdb->prefix}smtp_connector_for_wp_logs WHERE id = %d", $email_id), ARRAY_A) : null;

    if (!$email) {
        wp_send_json_error(['message' => __('This email was not found. It may have been deleted.', 'smtp-connector')], 404);
    }

    // The browser shows the content in a sandboxed iframe (no scripts, forms or overlays on the
    // admin page) or as plain text; it is never inserted into the admin page itself.
    wp_send_json_success([
        'to'      => (string) $email['email_to'],
        'subject' => (string) $email['subject'],
        'date'    => smtp_connector_for_wp_format_log_date($email['sent_at']),
        'status'  => smtp_connector_for_wp_status_label($email['status']),
        'error'   => (string) $email['error'],
        'isHtml'  => smtp_connector_for_wp_is_html((string) $email['content']),
        'content' => (string) $email['content'],
    ]);
}

add_action('admin_post_smtp_connector_for_wp_clear_logs', 'smtp_connector_for_wp_clear_logs');
function smtp_connector_for_wp_clear_logs() {
    global $wpdb;

    if (!current_user_can(smtp_connector_for_wp_log_capability())) {
        wp_die(esc_html__('You do not have permission to clear logs.', 'smtp-connector'), '', ['response' => 403]);
    }
    check_admin_referer('smtp_connector_for_wp_clear_logs_nonce', 'smtp_connector_for_wp_clear_logs_nonce');

    $suppress = $wpdb->suppress_errors(true);
    // phpcs:ignore WordPress.DB.DirectDatabaseQuery.DirectQuery, WordPress.DB.DirectDatabaseQuery.NoCaching, WordPress.DB.DirectDatabaseQuery.SchemaChange -- Empties the plugin's own log table.
    $emptied = $wpdb->query("TRUNCATE TABLE {$wpdb->prefix}smtp_connector_for_wp_logs");
    $wpdb->suppress_errors($suppress);
    if (false === $emptied) {
        // TRUNCATE needs the DROP privilege; delete the rows instead.
        // phpcs:ignore WordPress.DB.DirectDatabaseQuery.DirectQuery, WordPress.DB.DirectDatabaseQuery.NoCaching -- Plugin's own log table.
        $wpdb->query("DELETE FROM {$wpdb->prefix}smtp_connector_for_wp_logs");
    }

    wp_safe_redirect(add_query_arg(['page' => 'smtp-email-log', 'cleared' => '1'], admin_url('options-general.php')));
    exit;
}
