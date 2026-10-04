<?php
/**
 * Shared admin UI: page header with tabs, the delivery status card and the help card.
 *
 * @package SMTP_Connector
 */

// Exit if accessed directly
if (!defined('ABSPATH')) {
    exit;
}

/**
 * Current state of the connection, for the header pill and the status card.
 *
 * @return array{key: string, label: string}
 */
function smtp_connector_for_wp_connection_state() {
    if (smtp_connector_for_wp_password_unreadable()) {
        return ['key' => 'paused', 'label' => __('Paused: password needed', 'smtp-connector')];
    }
    if (smtp_connector_for_wp_is_configured()) {
        return ['key' => 'active', 'label' => __('SMTP active', 'smtp-connector')];
    }
    return ['key' => 'inactive', 'label' => __('Not set up', 'smtp-connector')];
}

/**
 * Page header shared by the settings, test and email log screens.
 *
 * @param string $current 'settings', 'test' or 'log'.
 */
function smtp_connector_for_wp_render_header($current) {
    $settings_url = admin_url('options-general.php?page=smtp-connector-for-wp');
    $tabs         = [
        'settings' => [__('Settings', 'smtp-connector'), $settings_url],
        'test'     => [__('Send test email', 'smtp-connector'), add_query_arg('tab', 'test', $settings_url)],
    ];
    if (current_user_can(smtp_connector_for_wp_log_capability())) {
        $tabs['log'] = [__('Email log', 'smtp-connector'), admin_url('options-general.php?page=smtp-email-log')];
    }
    $state = smtp_connector_for_wp_connection_state();
    ?>
    <header class="smtp-connector-header">
        <div class="smtp-connector-header__top">
            <div class="smtp-connector-header__brand">
                <span class="smtp-connector-header__icon dashicons dashicons-email-alt" aria-hidden="true"></span>
                <h1><?php esc_html_e('SMTP Connector', 'smtp-connector'); ?></h1>
                <span class="smtp-connector-header__version"><?php echo esc_html('v' . SMTP_CONNECTOR_FOR_WP_VERSION); ?></span>
            </div>
            <span class="smtp-connector-pill smtp-connector-pill--<?php echo esc_attr($state['key']); ?>"><?php echo esc_html($state['label']); ?></span>
        </div>
        <nav class="smtp-connector-tabs" aria-label="<?php esc_attr_e('SMTP Connector', 'smtp-connector'); ?>">
            <?php foreach ($tabs as $tab => $item) : ?>
                <a href="<?php echo esc_url($item[1]); ?>" class="smtp-connector-tabs__item<?php echo esc_attr($tab === $current ? ' is-active' : ''); ?>"<?php echo $tab === $current ? ' aria-current="page"' : ''; ?>><?php echo esc_html($item[0]); ?></a>
            <?php endforeach; ?>
        </nav>
    </header>
    <hr class="wp-header-end">
    <?php
}

/**
 * The route every email takes, and what to do next.
 */
function smtp_connector_for_wp_render_status_card() {
    $state    = smtp_connector_for_wp_connection_state();
    $settings = smtp_connector_for_wp_get_settings();
    $from     = is_email($settings['from_email']) ? $settings['from_email'] : (string) wp_parse_url(home_url(), PHP_URL_HOST);
    $security = [
        'tls'  => __('TLS', 'smtp-connector'),
        'ssl'  => __('SSL', 'smtp-connector'),
        'none' => __('No encryption', 'smtp-connector'),
    ];
    $settings_url = admin_url('options-general.php?page=smtp-connector-for-wp');
    ?>
    <section class="smtp-connector-card smtp-connector-status smtp-connector-status--<?php echo esc_attr($state['key']); ?>" aria-labelledby="smtp-connector-status-title">
        <h2 id="smtp-connector-status-title" class="smtp-connector-card__title"><?php esc_html_e('How your emails travel', 'smtp-connector'); ?></h2>
        <ol class="smtp-connector-route">
            <li class="smtp-connector-route__stop">
                <span class="smtp-connector-route__label"><?php esc_html_e('From', 'smtp-connector'); ?></span>
                <span class="smtp-connector-route__value"><?php echo esc_html($from); ?></span>
            </li>
            <li class="smtp-connector-route__stop smtp-connector-route__stop--server">
                <?php if ('inactive' === $state['key']) : ?>
                    <span class="smtp-connector-route__label"><?php esc_html_e('Through', 'smtp-connector'); ?></span>
                    <span class="smtp-connector-route__value"><?php esc_html_e('WordPress default mailer', 'smtp-connector'); ?></span>
                <?php else : ?>
                    <span class="smtp-connector-route__label"><?php esc_html_e('Through', 'smtp-connector'); ?></span>
                    <code class="smtp-connector-route__value"><?php echo esc_html($settings['host'] . ':' . $settings['port']); ?></code>
                    <span class="smtp-connector-route__meta"><?php echo esc_html($security[$settings['security']]); ?></span>
                <?php endif; ?>
            </li>
            <li class="smtp-connector-route__stop">
                <span class="smtp-connector-route__label"><?php esc_html_e('To', 'smtp-connector'); ?></span>
                <span class="smtp-connector-route__value"><?php esc_html_e("Your recipients' inboxes", 'smtp-connector'); ?></span>
            </li>
        </ol>

        <?php if ('active' === $state['key']) : ?>
            <p class="smtp-connector-status__text"><?php esc_html_e('Every email from this site goes through your SMTP server.', 'smtp-connector'); ?></p>
            <a class="button" href="<?php echo esc_url(add_query_arg('tab', 'test', $settings_url)); ?>"><?php esc_html_e('Send test email', 'smtp-connector'); ?></a>
        <?php elseif ('paused' === $state['key']) : ?>
            <p class="smtp-connector-status__text"><?php esc_html_e('Sending is paused: the saved password can no longer be read because the security keys in wp-config.php changed. Enter the password again to continue.', 'smtp-connector'); ?></p>
            <a class="button button-primary" href="<?php echo esc_url($settings_url . '#smtp-connector-password'); ?>"><?php esc_html_e('Enter the password', 'smtp-connector'); ?></a>
        <?php else : ?>
            <p class="smtp-connector-status__text"><?php esc_html_e('Fill in your SMTP server details to send through your email provider. Until then WordPress keeps using its default mailer.', 'smtp-connector'); ?></p>
        <?php endif; ?>
    </section>
    <?php
}

/**
 * Links to documentation, support and the developer.
 */
function smtp_connector_for_wp_render_help_card() {
    ?>
    <section class="smtp-connector-card smtp-connector-help" aria-labelledby="smtp-connector-help-title">
        <h2 id="smtp-connector-help-title" class="smtp-connector-card__title"><?php esc_html_e('Need help?', 'smtp-connector'); ?></h2>
        <ul class="smtp-connector-help__links">
            <li><a href="https://mpateldigital.com/smtp-connector/" target="_blank" rel="noopener noreferrer"><?php esc_html_e('Setup guide', 'smtp-connector'); ?></a></li>
            <li><a href="https://mpateldigital.com/contact-us/" target="_blank" rel="noopener noreferrer"><?php esc_html_e('Get support', 'smtp-connector'); ?></a></li>
            <li><a href="https://wordpress.org/support/plugin/smtp-connector/reviews/#new-post" target="_blank" rel="noopener noreferrer"><?php esc_html_e('Rate the plugin', 'smtp-connector'); ?></a></li>
            <li><a href="https://ko-fi.com/mukeshpatel" target="_blank" rel="noopener noreferrer"><?php esc_html_e('Buy the developer a coffee', 'smtp-connector'); ?></a></li>
        </ul>
        <p class="smtp-connector-help__author">
            <?php
            printf(
                /* translators: %s: Link to the developer's website. */
                esc_html__('Made by %s', 'smtp-connector'),
                '<a href="' . esc_url('https://mpatel.org/') . '" target="_blank" rel="noopener noreferrer">' . esc_html__('Mukesh Patel', 'smtp-connector') . '</a>'
            );
            ?>
        </p>
    </section>
    <?php
}
