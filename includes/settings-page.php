<?php
/**
 * Settings screen (Settings > SMTP Connector), SMTP test and admin notices.
 *
 * @package SMTP_Connector
 */

// Exit if accessed directly
if (!defined('ABSPATH')) {
    exit;
}

/*
 * Menu and assets
 */

add_action('admin_menu', 'smtp_connector_for_wp_create_menu');
function smtp_connector_for_wp_create_menu() {
    $hook = add_options_page(
        __('SMTP Connector Settings', 'smtp-connector'),
        __('SMTP Connector', 'smtp-connector'),
        'manage_options',
        'smtp-connector-for-wp',
        'smtp_connector_for_wp_settings_page'
    );
    if ($hook) {
        add_action('load-' . $hook, 'smtp_connector_for_wp_load_admin_assets');
    }
}

/**
 * Loads the admin CSS/JS, only on the plugin's own screens.
 */
function smtp_connector_for_wp_load_admin_assets() {
    add_action('admin_enqueue_scripts', 'smtp_connector_for_wp_enqueue_admin_assets');
}

function smtp_connector_for_wp_enqueue_admin_assets() {
    $url = plugin_dir_url(SMTP_CONNECTOR_FOR_WP_FILE);

    wp_enqueue_style('smtp-connector-admin', $url . 'assets/css/admin.css', [], SMTP_CONNECTOR_FOR_WP_VERSION);
    wp_enqueue_script('smtp-connector-admin', $url . 'assets/js/admin.js', [], SMTP_CONNECTOR_FOR_WP_VERSION, true);
    wp_localize_script('smtp-connector-admin', 'smtpConnectorAdmin', [
        'ajaxUrl' => admin_url('admin-ajax.php'),
        'nonce'   => wp_create_nonce('smtp_connector_for_wp_nonce'),
        'i18n'    => [
            'loading'      => __('Loading...', 'smtp-connector'),
            'loadError'    => __('This email could not be loaded. Please reload the page and try again.', 'smtp-connector'),
            'noSubject'    => __('(no subject)', 'smtp-connector'),
            'emptyMessage' => __('This email has no content.', 'smtp-connector'),
            'emailContent' => __('Email content', 'smtp-connector'),
            'to'           => __('To', 'smtp-connector'),
            'date'         => __('Date', 'smtp-connector'),
            'status'       => __('Status', 'smtp-connector'),
            'error'        => __('Error', 'smtp-connector'),
            'confirmClear' => __('Delete all email logs? This cannot be undone.', 'smtp-connector'),
            'confirmReset' => __('Reset the SMTP server, login and sender settings, including the saved password? WordPress will send emails with its default mailer until you set up SMTP again.', 'smtp-connector'),
        ],
    ]);
}

/*
 * Settings registration
 */

add_action('admin_init', 'smtp_connector_for_wp_settings_register');
function smtp_connector_for_wp_settings_register() {
    $group = 'smtp-connector-for-wp-settings-group';

    register_setting($group, 'smtp_connector_for_wp_host', ['type' => 'string', 'sanitize_callback' => 'smtp_connector_for_wp_sanitize_host']);
    register_setting($group, 'smtp_connector_for_wp_port', ['type' => 'integer', 'sanitize_callback' => 'smtp_connector_for_wp_sanitize_port']);
    register_setting($group, 'smtp_connector_for_wp_security', ['type' => 'string', 'sanitize_callback' => 'smtp_connector_for_wp_sanitize_security']);
    register_setting($group, 'smtp_connector_for_wp_auth', ['type' => 'string', 'sanitize_callback' => 'smtp_connector_for_wp_sanitize_checkbox']);
    register_setting($group, 'smtp_connector_for_wp_username', ['type' => 'string', 'sanitize_callback' => 'sanitize_text_field']);
    // Encrypted by the pre_update_option filter in smtp-connector.php, which runs once per save.
    register_setting($group, 'smtp_connector_for_wp_password', ['type' => 'string', 'sanitize_callback' => 'smtp_connector_for_wp_sanitize_password']);
    register_setting($group, 'smtp_connector_for_wp_from_email', ['type' => 'string', 'sanitize_callback' => 'smtp_connector_for_wp_sanitize_from_email']);
    register_setting($group, 'smtp_connector_for_wp_from_name', ['type' => 'string', 'sanitize_callback' => 'sanitize_text_field']);
    register_setting($group, 'smtp_connector_for_wp_enable_log', ['type' => 'string', 'sanitize_callback' => 'smtp_connector_for_wp_sanitize_checkbox']);
    register_setting($group, 'smtp_connector_for_wp_log_retention', ['type' => 'integer', 'sanitize_callback' => 'smtp_connector_for_wp_sanitize_retention']);
}

/**
 * Adds a settings error once (sanitize callbacks can run twice on the first save).
 */
function smtp_connector_for_wp_add_settings_error($setting, $message) {
    foreach (get_settings_errors($setting) as $existing) {
        if ($existing['message'] === $message) {
            return;
        }
    }
    add_settings_error($setting, $setting, $message);
}

function smtp_connector_for_wp_sanitize_host($value) {
    return trim(sanitize_text_field((string) $value));
}

function smtp_connector_for_wp_sanitize_port($value) {
    $port = absint($value);
    if ($port >= 1 && $port <= 65535) {
        return $port;
    }
    smtp_connector_for_wp_add_settings_error('smtp_connector_for_wp_port', __('Please enter an SMTP port between 1 and 65535.', 'smtp-connector'));
    return smtp_connector_for_wp_get_settings()['port'];
}

function smtp_connector_for_wp_sanitize_security($value) {
    return in_array($value, ['tls', 'ssl', 'none'], true) ? $value : 'tls';
}

function smtp_connector_for_wp_sanitize_checkbox($value) {
    return ('1' === (string) $value || 'on' === $value) ? '1' : '0';
}

function smtp_connector_for_wp_sanitize_password($value) {
    // Passwords may contain any character, so they are not altered here.
    return is_string($value) ? $value : '';
}

function smtp_connector_for_wp_sanitize_from_email($value) {
    $value = trim((string) $value);
    if ('' === $value) {
        return '';
    }
    $email = sanitize_email($value);
    if (is_email($email)) {
        return $email;
    }
    smtp_connector_for_wp_add_settings_error('smtp_connector_for_wp_from_email', __('Please enter a valid From Email address.', 'smtp-connector'));
    return smtp_connector_for_wp_get_settings()['from_email'];
}

function smtp_connector_for_wp_sanitize_retention($value) {
    $days = absint($value);
    if ($days >= 1 && $days <= 3650) {
        return $days;
    }
    smtp_connector_for_wp_add_settings_error('smtp_connector_for_wp_log_retention', __('Please keep email logs for 1 to 3650 days.', 'smtp-connector'));
    return smtp_connector_for_wp_log_retention_days();
}

// Suggested text for the site's privacy policy (Settings > Privacy).
add_action('admin_init', 'smtp_connector_for_wp_privacy_policy_content');
function smtp_connector_for_wp_privacy_policy_content() {
    if (!function_exists('wp_add_privacy_policy_content')) {
        return;
    }
    $content = '<p class="privacy-policy-tutorial">'
        . esc_html__('SMTP Connector sends the emails of this site through the SMTP server set up by the site owner. When the email log is turned on, a copy of every email the site sends (recipient addresses, subject, message and delivery status) is stored in the site database and deleted automatically after the number of days chosen in Settings > SMTP Connector.', 'smtp-connector')
        . '</p><p><strong class="privacy-policy-tutorial">' . esc_html__('Suggested text:', 'smtp-connector') . ' </strong>'
        . esc_html__('When this website sends you an email, a copy of that email (your email address, the subject and the message) may be kept in our email log to help us fix delivery problems. The copy is deleted automatically after a limited time.', 'smtp-connector')
        . '</p>';
    wp_add_privacy_policy_content('SMTP Connector', wp_kses_post($content));
}

/*
 * Admin notices (Dashboard and Plugins screens; the plugin's own screens show them inline)
 */

add_action('admin_notices', 'smtp_connector_for_wp_admin_notices');
function smtp_connector_for_wp_admin_notices() {
    if (!current_user_can('manage_options')) {
        return;
    }

    $screen    = get_current_screen();
    $screen_id = $screen ? $screen->id : '';
    if (!in_array($screen_id, ['dashboard', 'plugins'], true)) {
        return;
    }

    $settings_url = admin_url('options-general.php?page=smtp-connector-for-wp');

    if ('plugins' === $screen_id && get_transient('smtp-connector-for-wp-activation-notice')) {
        delete_transient('smtp-connector-for-wp-activation-notice');
        if (!smtp_connector_for_wp_is_configured()) {
            printf(
                '<div class="notice notice-success is-dismissible"><p>%1$s <a href="%2$s">%3$s</a></p></div>',
                esc_html__('Thank you for using SMTP Connector!', 'smtp-connector'),
                esc_url($settings_url),
                esc_html__('Set up your SMTP server', 'smtp-connector')
            );
            return;
        }
    }

    if (smtp_connector_for_wp_password_unreadable()) {
        printf(
            '<div class="notice notice-error"><p><strong>%1$s</strong> %2$s <a href="%3$s">%4$s</a></p></div>',
            esc_html__('SMTP Connector cannot read the saved SMTP password, so emails are not being sent.', 'smtp-connector'),
            esc_html__('This happens when the security keys in wp-config.php change or the site is moved.', 'smtp-connector'),
            esc_url($settings_url),
            esc_html__('Enter the password again', 'smtp-connector')
        );
    } elseif (!smtp_connector_for_wp_is_configured()) {
        printf(
            '<div class="notice notice-warning"><p>%1$s <a href="%2$s">%3$s</a></p></div>',
            esc_html__('SMTP Connector is not set up yet, so WordPress sends emails with its default mailer.', 'smtp-connector'),
            esc_url($settings_url),
            esc_html__('Configure SMTP', 'smtp-connector')
        );
    }
}

/*
 * Settings page
 */

function smtp_connector_for_wp_settings_page() {
    if (!current_user_can('manage_options')) {
        wp_die(esc_html__('You do not have sufficient permissions to access this page.', 'smtp-connector'));
    }

    // phpcs:ignore WordPress.Security.NonceVerification.Recommended -- Only selects the tab to display.
    $active_tab = (isset($_GET['tab']) && 'test' === sanitize_key(wp_unslash($_GET['tab']))) ? 'test' : 'settings';
    ?>
    <div class="wrap smtp-connector">
        <?php smtp_connector_for_wp_render_header($active_tab); ?>
        <?php smtp_connector_for_wp_settings_page_notices($active_tab); ?>

        <div class="smtp-connector__layout">
            <div class="smtp-connector__main">
                <?php
                if ('test' === $active_tab) {
                    smtp_connector_for_wp_render_test_tab();
                } else {
                    smtp_connector_for_wp_render_settings_tab();
                }
                ?>
            </div>
            <aside class="smtp-connector__aside">
                <?php smtp_connector_for_wp_render_status_card(); ?>
                <?php smtp_connector_for_wp_render_help_card(); ?>
            </aside>
        </div>
    </div>
    <?php
}

/**
 * Notices shown at the top of the settings screen.
 *
 * @param string $active_tab 'settings' or 'test'.
 */
function smtp_connector_for_wp_settings_page_notices($active_tab) {
    // phpcs:disable WordPress.Security.NonceVerification.Recommended -- Display-only flags set by this plugin's own redirects.
    $was_reset = isset($_GET['reset']);
    $test_ran  = isset($_GET['smtp_test']);
    // phpcs:enable WordPress.Security.NonceVerification.Recommended
    $configured = smtp_connector_for_wp_is_configured();
    $settings   = smtp_connector_for_wp_get_settings();

    if ($was_reset) {
        echo '<div class="notice notice-success"><p>' . esc_html__('All SMTP settings have been reset. WordPress sends emails with its default mailer until you set up SMTP again.', 'smtp-connector') . '</p></div>';
    }

    if (smtp_connector_for_wp_password_unreadable()) {
        echo '<div class="notice notice-error"><p><strong>' . esc_html__('The saved SMTP password cannot be read, so emails are not being sent.', 'smtp-connector') . '</strong> '
            . esc_html__('This happens when the security keys in wp-config.php change or the site is moved. Please enter the password again and save.', 'smtp-connector') . '</p></div>';
    }

    if ('test' === $active_tab && $test_ran) {
        $key    = 'smtp_connector_for_wp_test_result_' . get_current_user_id();
        $result = get_transient($key);
        delete_transient($key);
        if (is_array($result) && !empty($result['success'])) {
            echo '<div class="notice notice-success"><p>' . esc_html(sprintf(
                /* translators: %s: Email address the test email was sent to. */
                __('Test email sent to %s. Check the inbox (and the spam folder) in a minute or two.', 'smtp-connector'),
                $result['email']
            )) . '</p></div>';
        } elseif (is_array($result)) {
            echo '<div class="notice notice-error"><p><strong>' . esc_html__('The test email could not be sent.', 'smtp-connector') . '</strong> ' . esc_html($result['message']) . '</p></div>';
        }
    }

    if ('settings' !== $active_tab) {
        return;
    }

    if ($configured && !is_email($settings['from_email'])) {
        echo '<div class="notice notice-warning inline"><p>' . esc_html__('From email is empty, so emails are sent from the default WordPress address, which your SMTP server may reject. Please enter a from email below.', 'smtp-connector') . '</p></div>';
    }

    if ($configured && 'tls' === $settings['security'] && 465 === $settings['port']) {
        echo '<div class="notice notice-warning inline"><p>' . esc_html__('Port 465 is normally used with SSL. With TLS the connection usually hangs until it times out. Choose SSL, or use port 587 with TLS.', 'smtp-connector') . '</p></div>';
    } elseif ($configured && 'ssl' === $settings['security'] && 587 === $settings['port']) {
        echo '<div class="notice notice-warning inline"><p>' . esc_html__('Port 587 is normally used with TLS. With SSL the connection fails. Choose TLS, or use port 465 with SSL.', 'smtp-connector') . '</p></div>';
    }

    if (!$configured && !$was_reset) {
        $labels  = smtp_connector_for_wp_setting_labels();
        $missing = array_map(function ($key) use ($labels) {
            return $labels[$key];
        }, smtp_connector_for_wp_missing_settings());
        echo '<div class="notice notice-info inline"><p>' . esc_html(sprintf(
            /* translators: %s: Comma-separated list of settings, e.g. "SMTP host, SMTP password". */
            __('SMTP is not active yet. Please fill in: %s. Until then WordPress sends emails with its default mailer.', 'smtp-connector'),
            implode(', ', $missing)
        )) . '</p></div>';
    }
}

/**
 * Field labels used in messages about missing settings.
 *
 * @return array
 */
function smtp_connector_for_wp_setting_labels() {
    return [
        'host'     => __('SMTP host', 'smtp-connector'),
        'port'     => __('SMTP port', 'smtp-connector'),
        'username' => __('SMTP username', 'smtp-connector'),
        'password' => __('SMTP password', 'smtp-connector'),
    ];
}

/**
 * Server details for the quick setup buttons. Only fills in the form; nothing is saved until "Save settings".
 *
 * @return array
 */
function smtp_connector_for_wp_providers() {
    return [
        'gmail'    => ['Gmail', 'smtp.gmail.com', 587, 'tls', __('Use your Gmail address as username and an app password (Google Account > Security > App passwords) as password.', 'smtp-connector')],
        'zoho'     => ['Zoho Mail', 'smtp.zoho.com', 587, 'tls', __('Accounts in the India or EU data centre use smtp.zoho.in or smtp.zoho.eu. With two-factor login on, use an app-specific password.', 'smtp-connector')],
        // phpcs:ignore PluginCheck.CodeAnalysis.Offloading.OffloadedContent -- An SMTP host name typed into the form, not a remote file.
        'ses'      => ['Amazon SES', 'email-smtp.us-east-1.amazonaws.com', 587, 'tls', __('Replace us-east-1 with your SES region, and use the SMTP credentials created in the SES console (not your AWS access keys).', 'smtp-connector')],
        'brevo'    => ['Brevo', 'smtp-relay.brevo.com', 587, 'tls', __('Use the SMTP login and SMTP key shown in Brevo under SMTP & API.', 'smtp-connector')],
        'mailgun'  => ['Mailgun', 'smtp.mailgun.org', 587, 'tls', __('Domains in the EU region use smtp.eu.mailgun.org. Username and password are the SMTP credentials of your Mailgun domain.', 'smtp-connector')],
        'sendgrid' => ['SendGrid', 'smtp.sendgrid.net', 587, 'tls', __('The username is the word apikey, and the password is your SendGrid API key.', 'smtp-connector')],
    ];
}

function smtp_connector_for_wp_render_settings_tab() {
    $settings        = smtp_connector_for_wp_get_settings();
    $password_stored = '' !== (string) get_option('smtp_connector_for_wp_password', '');
    // A stored password that can't be decrypted (security keys changed) has to be typed again.
    $password_saved      = $password_stored && false !== smtp_connector_for_wp_get_smtp_password();
    $password_unreadable = $password_stored && !$password_saved;
    $encryption          = [
        'tls'  => __('TLS', 'smtp-connector'),
        'ssl'  => __('SSL', 'smtp-connector'),
        'none' => __('None', 'smtp-connector'),
    ];
    ?>
    <form method="post" action="options.php" class="smtp-connector__form">
        <?php settings_fields('smtp-connector-for-wp-settings-group'); ?>

        <section class="smtp-connector-card" aria-labelledby="smtp-connector-quick-title">
            <h2 id="smtp-connector-quick-title" class="smtp-connector-card__title"><?php esc_html_e('Quick setup', 'smtp-connector'); ?></h2>
            <p class="smtp-connector-card__intro"><?php esc_html_e('Choose your email provider to fill in the server details, then add your login below.', 'smtp-connector'); ?></p>
            <div class="smtp-connector-providers" role="group" aria-label="<?php esc_attr_e('Email providers', 'smtp-connector'); ?>">
                <?php foreach (smtp_connector_for_wp_providers() as $id => $provider) : ?>
                    <button type="button" class="smtp-connector-provider" aria-pressed="false" data-provider="<?php echo esc_attr($id); ?>" data-host="<?php echo esc_attr($provider[1]); ?>" data-port="<?php echo esc_attr($provider[2]); ?>" data-security="<?php echo esc_attr($provider[3]); ?>" data-hint="<?php echo esc_attr($provider[4]); ?>"><?php echo esc_html($provider[0]); ?></button>
                <?php endforeach; ?>
            </div>
            <p class="smtp-connector-providers__hint" aria-live="polite" hidden></p>
        </section>

        <section class="smtp-connector-card" aria-labelledby="smtp-connector-server-title">
            <h2 id="smtp-connector-server-title" class="smtp-connector-card__title"><?php esc_html_e('SMTP server', 'smtp-connector'); ?></h2>
            <div class="smtp-connector-field">
                <label for="smtp-connector-host"><?php esc_html_e('SMTP host', 'smtp-connector'); ?></label>
                <input type="text" id="smtp-connector-host" name="smtp_connector_for_wp_host" class="smtp-connector-input smtp-connector-input--mono" value="<?php echo esc_attr($settings['host']); ?>" placeholder="smtp.example.com" autocomplete="off" spellcheck="false" />
                <p class="description"><?php esc_html_e('The SMTP server of your email provider, for example smtp.gmail.com.', 'smtp-connector'); ?></p>
            </div>
            <div class="smtp-connector-fields">
                <fieldset class="smtp-connector-field">
                    <legend><?php esc_html_e('Encryption', 'smtp-connector'); ?></legend>
                    <div class="smtp-connector-segmented">
                        <?php foreach ($encryption as $value => $label) : ?>
                            <input type="radio" id="smtp-connector-security-<?php echo esc_attr($value); ?>" name="smtp_connector_for_wp_security" value="<?php echo esc_attr($value); ?>" <?php checked($settings['security'], $value); ?> />
                            <label for="smtp-connector-security-<?php echo esc_attr($value); ?>"><?php echo esc_html($label); ?></label>
                        <?php endforeach; ?>
                    </div>
                </fieldset>
                <div class="smtp-connector-field smtp-connector-field--port">
                    <label for="smtp-connector-port"><?php esc_html_e('Port', 'smtp-connector'); ?></label>
                    <input type="number" id="smtp-connector-port" name="smtp_connector_for_wp_port" class="smtp-connector-input smtp-connector-input--mono" value="<?php echo esc_attr($settings['port']); ?>" min="1" max="65535" required />
                </div>
            </div>
            <p class="description"><?php esc_html_e('TLS normally uses port 587 and SSL port 465. Choose None only for a server in your own network: the password and emails are then sent unencrypted.', 'smtp-connector'); ?></p>
        </section>

        <section class="smtp-connector-card" aria-labelledby="smtp-connector-login-title">
            <div class="smtp-connector-card__head">
                <h2 id="smtp-connector-login-title" class="smtp-connector-card__title"><?php esc_html_e('Login', 'smtp-connector'); ?></h2>
                <label class="smtp-connector-switch" for="smtp-connector-auth">
                    <input type="checkbox" id="smtp-connector-auth" class="smtp-connector-switch__input" name="smtp_connector_for_wp_auth" value="1" <?php checked($settings['auth']); ?> />
                    <span><?php esc_html_e('Log in with a username and password', 'smtp-connector'); ?></span>
                </label>
            </div>
            <div class="smtp-connector-fields smtp-connector-auth-field">
                <div class="smtp-connector-field">
                    <label for="smtp-connector-username"><?php esc_html_e('SMTP username', 'smtp-connector'); ?></label>
                    <input type="text" id="smtp-connector-username" name="smtp_connector_for_wp_username" class="smtp-connector-input" value="<?php echo esc_attr($settings['username']); ?>" autocomplete="off" spellcheck="false" />
                    <p class="description"><?php esc_html_e('Usually your full email address.', 'smtp-connector'); ?></p>
                </div>
                <div class="smtp-connector-field">
                    <label for="smtp-connector-password"><?php esc_html_e('SMTP password', 'smtp-connector'); ?></label>
                    <input type="password" id="smtp-connector-password" name="smtp_connector_for_wp_password" class="smtp-connector-input" value="" autocomplete="new-password" spellcheck="false"<?php echo $password_saved ? ' placeholder="' . esc_attr__('Saved (leave empty to keep it)', 'smtp-connector') . '"' : ''; ?> />
                    <p class="description">
                        <?php
                        if ($password_saved) {
                            esc_html_e('A password is saved. Leave this field empty to keep it, or type a new one to replace it.', 'smtp-connector');
                        } elseif ($password_unreadable) {
                            echo '<strong class="smtp-connector-text-error">' . esc_html__('The saved password can no longer be read because the security keys in wp-config.php changed. Please enter the password again.', 'smtp-connector') . '</strong>';
                        } else {
                            esc_html_e('Stored encrypted with your site\'s security keys.', 'smtp-connector');
                        }
                        ?>
                    </p>
                </div>
            </div>
            <p class="description smtp-connector-auth-field">
                <?php
                printf(
                    /* translators: 1: Link to the Gmail app passwords page, 2: Link to the Zoho help page. */
                    esc_html__('Gmail and Zoho need an app password instead of your normal password: %1$s, %2$s.', 'smtp-connector'),
                    '<a href="' . esc_url('https://myaccount.google.com/apppasswords') . '" target="_blank" rel="noopener noreferrer">' . esc_html__('Gmail', 'smtp-connector') . '</a>',
                    '<a href="' . esc_url('https://help.zoho.com/portal/en/kb/bigin/channels/email/articles/generate-an-app-specific-password#To_generate_app_specific_password_for_Zoho_Mail') . '" target="_blank" rel="noopener noreferrer">' . esc_html__('Zoho', 'smtp-connector') . '</a>'
                );
                ?>
            </p>
            <p class="description smtp-connector-auth-off"><?php esc_html_e('Turn this off only for servers that accept email without logging in, such as a relay in your own network.', 'smtp-connector'); ?></p>
        </section>

        <section class="smtp-connector-card" aria-labelledby="smtp-connector-sender-title">
            <h2 id="smtp-connector-sender-title" class="smtp-connector-card__title"><?php esc_html_e('Sender', 'smtp-connector'); ?></h2>
            <div class="smtp-connector-fields">
                <div class="smtp-connector-field">
                    <label for="smtp-connector-from-email"><?php esc_html_e('From email', 'smtp-connector'); ?></label>
                    <input type="email" id="smtp-connector-from-email" name="smtp_connector_for_wp_from_email" class="smtp-connector-input" value="<?php echo esc_attr($settings['from_email']); ?>" placeholder="<?php esc_attr_e('e.g. wordpress@example.com', 'smtp-connector'); ?>" />
                </div>
                <div class="smtp-connector-field">
                    <label for="smtp-connector-from-name"><?php esc_html_e('From name', 'smtp-connector'); ?></label>
                    <input type="text" id="smtp-connector-from-name" name="smtp_connector_for_wp_from_name" class="smtp-connector-input" value="<?php echo esc_attr($settings['from_name']); ?>" placeholder="<?php echo esc_attr(get_bloginfo('name')); ?>" />
                </div>
            </div>
            <p class="description"><?php esc_html_e('Every email is sent from this address. Use an address your SMTP account is allowed to send from. An empty name uses your site title.', 'smtp-connector'); ?></p>
        </section>

        <section class="smtp-connector-card" aria-labelledby="smtp-connector-log-title">
            <div class="smtp-connector-card__head">
                <h2 id="smtp-connector-log-title" class="smtp-connector-card__title"><?php esc_html_e('Email log', 'smtp-connector'); ?></h2>
                <label class="smtp-connector-switch" for="smtp-connector-enable-log">
                    <input type="checkbox" id="smtp-connector-enable-log" class="smtp-connector-switch__input" name="smtp_connector_for_wp_enable_log" value="1" <?php checked($settings['enable_log']); ?> />
                    <span><?php esc_html_e('Keep a copy of every email', 'smtp-connector'); ?></span>
                </label>
            </div>
            <div class="smtp-connector-field smtp-connector-field--inline">
                <label for="smtp-connector-log-retention"><?php esc_html_e('Keep logs for', 'smtp-connector'); ?></label>
                <input type="number" id="smtp-connector-log-retention" name="smtp_connector_for_wp_log_retention" class="smtp-connector-input smtp-connector-input--small" value="<?php echo esc_attr(smtp_connector_for_wp_log_retention_days()); ?>" min="1" max="3650" required />
                <span><?php echo esc_html(_n('day', 'days', smtp_connector_for_wp_log_retention_days(), 'smtp-connector')); ?></span>
            </div>
            <p class="description">
                <?php
                if (is_multisite()) {
                    esc_html_e('Older logs are deleted automatically once a day. Logs can contain personal data and password reset links, so only network administrators can see them.', 'smtp-connector');
                } else {
                    esc_html_e('Older logs are deleted automatically once a day. Logs can contain personal data and password reset links, so only administrators can see them.', 'smtp-connector');
                }
                ?>
            </p>
        </section>

        <div class="smtp-connector-savebar">
            <?php submit_button(__('Save settings', 'smtp-connector'), 'primary', 'submit', false); ?>
            <span class="smtp-connector-savebar__hint"><?php esc_html_e('Changes apply to every email the site sends.', 'smtp-connector'); ?></span>
        </div>
    </form>

    <section class="smtp-connector-card smtp-connector-card--danger" aria-labelledby="smtp-connector-reset-title">
        <h2 id="smtp-connector-reset-title" class="smtp-connector-card__title"><?php esc_html_e('Reset settings', 'smtp-connector'); ?></h2>
        <p><?php esc_html_e('Deletes the SMTP server, login and sender settings, including the saved password. Email log settings and logs are kept.', 'smtp-connector'); ?></p>
        <form method="post" action="<?php echo esc_url(admin_url('admin-post.php')); ?>" class="smtp-connector-reset-form">
            <input type="hidden" name="action" value="smtp_connector_for_wp_reset_settings" />
            <?php wp_nonce_field('smtp_connector_for_wp_reset_nonce', 'smtp_connector_for_wp_reset_nonce'); ?>
            <?php submit_button(__('Reset settings', 'smtp-connector'), 'delete', 'smtp-connector-reset', false); ?>
        </form>
    </section>
    <?php
}

function smtp_connector_for_wp_render_test_tab() {
    $configured = smtp_connector_for_wp_is_configured();
    ?>
    <section class="smtp-connector-card" aria-labelledby="smtp-connector-test-title">
        <h2 id="smtp-connector-test-title" class="smtp-connector-card__title"><?php esc_html_e('Send a test email', 'smtp-connector'); ?></h2>
        <p class="smtp-connector-card__intro"><?php esc_html_e('Send a short email through your SMTP server to check that everything works. If it fails, you see the exact error the server returned.', 'smtp-connector'); ?></p>

        <?php if (!$configured) : ?>
            <div class="notice notice-warning inline">
                <p>
                    <?php esc_html_e('Save your SMTP settings first.', 'smtp-connector'); ?>
                    <a href="<?php echo esc_url(admin_url('options-general.php?page=smtp-connector-for-wp')); ?>"><?php esc_html_e('Go to settings', 'smtp-connector'); ?></a>
                </p>
            </div>
        <?php endif; ?>

        <form method="post" action="<?php echo esc_url(admin_url('admin-post.php')); ?>" class="smtp-connector-test-form">
            <input type="hidden" name="action" value="smtp_connector_for_wp_test_email" />
            <?php wp_nonce_field('smtp_connector_for_wp_test_nonce', 'smtp_connector_for_wp_test_nonce'); ?>
            <div class="smtp-connector-field">
                <label for="smtp-connector-test-email"><?php esc_html_e('Send to', 'smtp-connector'); ?></label>
                <div class="smtp-connector-inline-form">
                    <input type="email" id="smtp-connector-test-email" name="smtp_connector_for_wp_test_to" class="smtp-connector-input" value="<?php echo esc_attr(wp_get_current_user()->user_email); ?>" required />
                    <?php submit_button(__('Send test email', 'smtp-connector'), 'primary', 'submit', false, $configured ? [] : ['disabled' => 'disabled']); ?>
                </div>
            </div>
        </form>
    </section>
    <?php
}

/*
 * Form handlers
 */

add_action('admin_post_smtp_connector_for_wp_test_email', 'smtp_connector_for_wp_handle_test_email');
function smtp_connector_for_wp_handle_test_email() {
    if (!current_user_can('manage_options')) {
        wp_die(esc_html__('You do not have permission to perform this action.', 'smtp-connector'), '', ['response' => 403]);
    }
    check_admin_referer('smtp_connector_for_wp_test_nonce', 'smtp_connector_for_wp_test_nonce');

    $email = isset($_POST['smtp_connector_for_wp_test_to']) ? sanitize_email(wp_unslash($_POST['smtp_connector_for_wp_test_to'])) : '';

    if (!is_email($email)) {
        $result = ['success' => false, 'message' => __('Please enter a valid email address.', 'smtp-connector')];
    } elseif (!smtp_connector_for_wp_is_configured()) {
        $result = ['success' => false, 'message' => __('Please save your SMTP settings before sending a test email.', 'smtp-connector')];
    } else {
        $result = smtp_connector_for_wp_send_test_email($email);
    }
    $result['email'] = $email;

    set_transient('smtp_connector_for_wp_test_result_' . get_current_user_id(), $result, 5 * MINUTE_IN_SECONDS);

    wp_safe_redirect(add_query_arg([
        'page'      => 'smtp-connector-for-wp',
        'tab'       => 'test',
        'smtp_test' => '1',
    ], admin_url('options-general.php')));
    exit;
}

/**
 * Sends the test email and returns the result with the SMTP error, if any.
 *
 * @param string $email Recipient.
 * @return array{success: bool, message: string}
 */
function smtp_connector_for_wp_send_test_email($email) {
    $error   = '';
    $capture = function ($wp_error) use (&$error) {
        if (is_wp_error($wp_error)) {
            $error = $wp_error->get_error_message();
        }
    };
    add_action('wp_mail_failed', $capture);

    $site_name = wp_specialchars_decode(get_bloginfo('name'), ENT_QUOTES);
    /* translators: %s: Site title. */
    $subject = sprintf(__('SMTP Connector test email from %s', 'smtp-connector'), $site_name);
    $body    = '<p>' . esc_html__('This test email was sent by the SMTP Connector plugin through your SMTP server. Your settings work!', 'smtp-connector') . '</p>'
        . '<p>' . esc_html(home_url()) . '</p>';
    $sent = wp_mail($email, $subject, $body, ['Content-Type: text/html; charset=UTF-8']);

    remove_action('wp_mail_failed', $capture);

    if ($sent) {
        return ['success' => true, 'message' => ''];
    }
    return [
        'success' => false,
        'message' => '' !== $error ? $error : __('The email could not be sent. Please check your SMTP settings.', 'smtp-connector'),
    ];
}

add_action('admin_post_smtp_connector_for_wp_reset_settings', 'smtp_connector_for_wp_reset_settings');
function smtp_connector_for_wp_reset_settings() {
    if (!current_user_can('manage_options')) {
        wp_die(esc_html__('You do not have permission to perform this action.', 'smtp-connector'), '', ['response' => 403]);
    }
    check_admin_referer('smtp_connector_for_wp_reset_nonce', 'smtp_connector_for_wp_reset_nonce');

    // Email log settings (and the logs) are kept.
    foreach (smtp_connector_for_wp_smtp_setting_names() as $option) {
        delete_option($option);
    }

    wp_safe_redirect(add_query_arg(['page' => 'smtp-connector-for-wp', 'reset' => '1'], admin_url('options-general.php')));
    exit;
}
