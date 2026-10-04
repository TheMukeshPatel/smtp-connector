# Changelog

All notable changes to SMTP Connector are documented in this file.
The `readme.txt` changelog (shown on WordPress.org) is kept in sync with it.

Format: [Keep a Changelog](https://keepachangelog.com/en/1.1.0/). Versions follow the plugin header `Version`.

## [1.4.0] - 2026-10-04

The biggest update so far. I went through the whole plugin, fixed every problem I could find, and gave the settings screen a proper redesign.

### Fixed
- Every email was sent twice since 1.3.0. The plugin called `$phpmailer->send()` inside `phpmailer_init`, and WordPress then sent the same email again.
- The first time the SMTP password was saved (and again after "Reset Settings"), it was encrypted twice, so SMTP login failed until the settings were saved a second time. Affected passwords are repaired automatically on update.
- "Leave blank to keep the existing password" now really works. An empty password field keeps the saved password.
- All emails failed while the plugin wasn't configured yet, or after "Reset Settings". WordPress now keeps using its default mailer until SMTP is set up.
- Sites on `localhost` or an intranet host name couldn't send any email. The From address is now set through `wp_mail_from`.
- The whole site stopped with "Encryption key is missing." when `SECURE_AUTH_KEY` wasn't defined in `wp-config.php`.
- The email log showed wrong recipient addresses (`a@x.com, b@y.com` was saved as `a@x.comby.com`), wrong statuses, and emails stuck at "Pending". Emails with long recipient lists are logged too.
- Old email logs were never deleted on sites updated from 1.2.x, or on multisite subsites.
- Log clean-up used the database server's timezone instead of the site's timezone.
- Translations never loaded. The text domain is now `smtp-connector`, matching the WordPress.org slug.
- Deleting the plugin now removes all of its data, on every site of a multisite network.
- The log table definition now works with `dbDelta()` and with MySQL versions that limit index length to 767 bytes.

### Security
- Every action checks permissions as well as the nonce. "Reset Settings" had only a nonce check.
- Email previews open in a sandboxed iframe. Email HTML can't run scripts, add forms or cover the admin screen, and images from other websites (tracking pixels) are blocked.
- The SMTP password is stored with authenticated encryption (libsodium secretbox). The key comes from the site's own security keys in `wp-config.php`, which are unique to each site.
  - Passwords saved by 1.1–1.3.x are converted automatically.
  - Passwords set with WP-CLI or from code (`update_option`/`add_option`) are encrypted too.
  - If the security keys change, the plugin pauses sending, shows a warning, and asks for the password again.
- On multisite, only network administrators can see the email log, because it can contain other users' password reset links. The capability can be changed with the `smtp_connector_for_wp_log_capability` filter.

### Added
- A redesigned settings screen built from cards, with one-click quick setup for Gmail, Zoho, Amazon SES, Brevo, Mailgun and SendGrid.
- A status card that shows how emails travel (From → SMTP server → recipient) and whether SMTP is active, paused or not set up yet.
- A "None" encryption option, and a switch to turn SMTP login off for local or relay servers.
- An on/off switch for the email log, and a setting for how many days logs are kept.
- The SMTP error of a failed email is shown in the email log and on the Send test email screen.
- A warning when the port doesn't match the encryption (for example TLS with port 465).
- Suggested text for the site's privacy policy (Settings > Privacy).
- Filters `smtp_connector_for_wp_smtp_timeout` (connect, default 30 s) and `smtp_connector_for_wp_smtp_reply_timeout` (each server reply, default 120 s).

### Changed
- The Settings, Send test email and Email log screens share one header with tabs, and work on phones. Reset and Clear logs ask for confirmation, and every action shows a result message.
- Admin CSS and JavaScript are enqueued files that load only on the plugin's own screens, instead of inline `<script>` and `<style>` tags.
- The "not configured" notice is shown only to administrators, and only on the Dashboard and Plugins screens.
- "Reset Settings" keeps the email log settings and the logs.
- An empty From name now uses the site title.
- A wrong or unreachable SMTP server no longer blocks the page for up to 10 minutes.
- On sites updated from 1.3.x, old logs are cleaned up starting one week after the update, so there's time to change "Keep logs for".
- Requires WordPress 5.9 or newer and PHP 7.2 or newer.
- The readme has 5 tags (WordPress.org ignores the rest), a new description, and installation steps for all three methods.
- The author website is now https://mpatel.org/.
- The plugin is now called "SMTP Connector – Free SMTP & Email Log" (the folder and slug stay `smtp-connector`, so updates work as before).
- LICENSE contains the GPLv2 text, matching "GPLv2 or later" in the plugin header and the readme.

## [1.3.2] - 2025-05-15
### Changed
- Compatibility check with the latest WordPress version.

## [1.3.1] - 2024-11-10
### Fixed
- Error when capturing email information for the log, caused by an undefined function.

## [1.3.0] - 2024-11-10
### Added
- Email log: track all emails sent through SMTP.
### Changed
- Improved security.
- The password is no longer shown on the settings page.
- Improved error log handling.
### Fixed
- The encryption key is checked before encrypting and decrypting.

## [1.2.3] - 2024-08-06
### Added
- "Reset Settings" button to remove all plugin settings.
- A notice when the plugin is not properly configured.
### Changed
- Uninstall routine that removes all settings and data when the plugin is deleted.
- The SMTP test moved to Settings > SMTP Connector > SMTP Test.
- Minor code improvements.

## [1.2.2] - 2024-04-05
### Changed
- Compatible with the latest version of WordPress.

## [1.2.1] - 2024-02-16
### Added
- "From Email" option.
### Fixed
- Error while sending email with Elastic Email because of the missing "From Email".

## [1.2.0] - 2024-02-16
### Added
- Email testing feature (thanks to danilo-tecnosys for the suggestion).

## [1.1.2] - 2024-01-01
### Fixed
- Error while sending emails, caused by a wrong function name for decrypting the password.

## [1.1.1] - 2023-12-23
### Changed
- Improved security and removed unused code.
### Fixed
- Minor bugs.

## [1.1.0] - 2023-12-12
### Added
- The password is saved encrypted.
- Developer support link.
### Fixed
- The settings page is included only in the admin.
- Missing opening PHP tag in the empty `index.php` files (thanks to @rrikesh).

## [1.0.1] - 2023-12-07
### Fixed
- Minor code issues.

## [1.0.0] - 2023-12-07
- Initial release.
