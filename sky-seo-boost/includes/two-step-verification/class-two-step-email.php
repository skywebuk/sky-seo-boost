<?php
/**
 * Sky SEO Boost - Email Two-Step Verification Provider
 *
 * Adapted from WordPress Two-Factor Plugin (https://github.com/WordPress/two-factor)
 * Licensed under GPL v2 or later
 *
 * @package Sky_SEO_Boost
 * @since 4.2.0
 */

// Prevent direct access
if (!defined('ABSPATH')) {
    exit;
}

/**
 * Class for creating an email-based two-step verification provider.
 */
class Sky_SEO_Two_Step_Email {

    /**
     * The user meta key for the token.
     *
     * @var string
     */
    const TOKEN_META_KEY = '_sky_seo_two_step_email_token';

    /**
     * The user meta key for the last failed timestamp.
     *
     * @var string
     */
    const LAST_FAILED_META_KEY = '_sky_seo_two_step_last_failed';

    /**
     * The user meta key for the failed attempts count.
     *
     * @var string
     */
    const FAILED_ATTEMPTS_META_KEY = '_sky_seo_two_step_failed_attempts';

    /**
     * Class constructor.
     */
    public function __construct() {
        add_action('sky_seo_two_step_user_settings', array($this, 'user_settings'), 10, 1);
    }

    /**
     * Get the name of the provider.
     *
     * @return string
     */
    public function get_label() {
        return __('Email Verification', 'sky-seo-boost');
    }

    /**
     * Check if this provider is available for the user.
     *
     * @param WP_User $user The user object.
     * @return bool
     */
    public function is_available_for_user($user) {
        // Email is always available if the user has an email address
        return !empty($user->user_email);
    }

    /**
     * Generate the verification token.
     *
     * @param WP_User $user The user object.
     * @return string
     */
    public function generate_token($user) {
        /**
         * Filter the token length.
         *
         * @param int $length The length of the token. Default 6.
         */
        $token_length = apply_filters('sky_seo_two_step_email_token_length', 6);

        // Generate 6-digit numeric code only
        $token = '';
        for ($i = 0; $i < $token_length; $i++) {
            $token .= wp_rand(0, 9);
        }

        // Hash the token for storage
        $hashed_token = wp_hash($token);

        // Store the hashed token with timestamp
        update_user_meta($user->ID, self::TOKEN_META_KEY, array(
            'token' => $hashed_token,
            'expiration' => time() + $this->get_token_ttl(),
        ));

        return $token;
    }

    /**
     * Validate the verification token.
     *
     * @param WP_User $user The user object.
     * @param string $token The token to validate.
     * @return bool
     */
    public function validate_token($user, $token) {
        $token_data = get_user_meta($user->ID, self::TOKEN_META_KEY, true);

        // Check if token exists and is not expired
        if (empty($token_data) || !is_array($token_data)) {
            return false;
        }

        if (empty($token_data['token']) || empty($token_data['expiration'])) {
            return false;
        }

        // Check expiration
        if (time() > $token_data['expiration']) {
            delete_user_meta($user->ID, self::TOKEN_META_KEY);
            return false;
        }

        // Validate token
        $hashed_input = wp_hash($token);

        if (hash_equals($token_data['token'], $hashed_input)) {
            // Token is valid, delete it (one-time use)
            delete_user_meta($user->ID, self::TOKEN_META_KEY);

            // Clear failed attempts
            delete_user_meta($user->ID, self::FAILED_ATTEMPTS_META_KEY);
            delete_user_meta($user->ID, self::LAST_FAILED_META_KEY);

            return true;
        }

        // Invalid token - record failed attempt
        $this->record_failed_attempt($user);

        return false;
    }

    /**
     * Record a failed authentication attempt.
     *
     * @param WP_User $user The user object.
     */
    private function record_failed_attempt($user) {
        $attempts = (int) get_user_meta($user->ID, self::FAILED_ATTEMPTS_META_KEY, true);
        $attempts++;

        update_user_meta($user->ID, self::FAILED_ATTEMPTS_META_KEY, $attempts);
        update_user_meta($user->ID, self::LAST_FAILED_META_KEY, time());
    }

    /**
     * Get the number of failed attempts.
     *
     * @param WP_User $user The user object.
     * @return int
     */
    public function get_failed_attempts($user) {
        return (int) get_user_meta($user->ID, self::FAILED_ATTEMPTS_META_KEY, true);
    }

    /**
     * Get the token time-to-live in seconds.
     *
     * @return int
     */
    public function get_token_ttl() {
        /**
         * Filter the token time-to-live.
         *
         * @param int $ttl The time-to-live in seconds. Default 900 (15 minutes).
         */
        return apply_filters('sky_seo_two_step_email_token_ttl', 15 * MINUTE_IN_SECONDS);
    }

    /**
     * Generate and email the verification token to the user.
     *
     * @param WP_User $user The user object.
     * @return bool
     */
    public function generate_and_email_token($user) {
        $token = $this->generate_token($user);

        // Prepare email
        $site_name = wp_specialchars_decode(get_bloginfo('name'), ENT_QUOTES);
        $user_name = $user->display_name;
        $expiry_minutes = $this->get_token_ttl() / MINUTE_IN_SECONDS;

        /* translators: %s: Site name */
        $subject = wp_strip_all_tags(sprintf(__('[%s] Two-Step Verification Code', 'sky-seo-boost'), $site_name));

        // Load the email template
        require_once dirname(__FILE__) . '/email-template.php';

        // Generate HTML email content
        $message = sky_seo_get_2fa_email_template($user_name, $site_name, $token, $expiry_minutes);

        /**
         * Filter the email verification message.
         *
         * @param string $message The email message.
         * @param string $token The verification token.
         * @param WP_User $user The user object.
         */
        $message = apply_filters('sky_seo_two_step_email_message', $message, $token, $user);

        /**
         * Filter the email verification subject.
         *
         * @param string $subject The email subject.
         * @param WP_User $user The user object.
         */
        $subject = apply_filters('sky_seo_two_step_email_subject', $subject, $user);

        // Set email content type to HTML
        add_filter('wp_mail_content_type', array($this, 'set_html_content_type'));

        // Send the email
        $result = wp_mail($user->user_email, $subject, $message);

        // Reset content type to avoid conflicts
        remove_filter('wp_mail_content_type', array($this, 'set_html_content_type'));

        return $result;
    }

    /**
     * Set email content type to HTML.
     *
     * @return string
     */
    public function set_html_content_type() {
        return 'text/html';
    }

    /**
     * Display the authentication page.
     *
     * @param WP_User $user The user object.
     */
    public function authentication_page($user) {
        // Check for resend action
        if (isset($_POST['sky-seo-two-step-resend-nonce']) &&
            wp_verify_nonce($_POST['sky-seo-two-step-resend-nonce'], 'sky-seo-two-step-resend-' . $user->ID)) {
            if ($this->generate_and_email_token($user)) {
                echo '<p class="message">' . esc_html__('A new verification code has been sent to your email address.', 'sky-seo-boost') . '</p>';
            } else {
                echo '<p class="message error">' . esc_html__('Failed to send verification code. Please try again.', 'sky-seo-boost') . '</p>';
            }
        } else {
            // Generate and send initial token
            $this->generate_and_email_token($user);
        }

        $failed_attempts = $this->get_failed_attempts($user);

        ?>
        <style>
            #login {
                width: 450px;
            }
            .sky-seo-two-step-form {
                background: #fff;
                border: 1px solid #dcdcde;
                border-radius: 4px;
                padding: 32px;
                margin: 20px 0;
            }
            .sky-seo-two-step-form h2 {
                margin-top: 0;
                font-size: 22px;
            }
            .sky-seo-two-step-form p {
                line-height: 1.6;
            }
            .sky-seo-two-step-form .message {
                padding: 12px;
                border-left: 4px solid #00a32a;
                background: #f0f6fc;
                margin: 16px 0;
            }
            .sky-seo-two-step-form .message.error {
                border-left-color: #d63638;
                background: #fcf0f1;
            }
            .sky-seo-two-step-form input[type="text"] {
                font-size: 32px;
                line-height: 1.5;
                letter-spacing: 0.4em;
                padding: 12px 16px;
                width: 100%;
                max-width: 100%;
                text-align: center;
                font-family: 'Courier New', monospace;
                font-weight: 700;
            }
            .sky-seo-two-step-form .submit {
                margin-top: 16px;
            }
            .sky-seo-two-step-resend {
                margin-top: 16px;
                padding-top: 16px;
                border-top: 1px solid #dcdcde;
            }
        </style>

        <div class="sky-seo-two-step-form">
            <h2><?php esc_html_e('Email Verification Required', 'sky-seo-boost'); ?></h2>

            <p>
                <?php
                printf(
                    /* translators: %s: User email address */
                    esc_html__('A verification code has been sent to %s. Please enter the code below to complete your login.', 'sky-seo-boost'),
                    '<strong>' . esc_html($user->user_email) . '</strong>'
                );
                ?>
            </p>

            <?php if ($failed_attempts > 0) : ?>
                <p class="message error">
                    <?php
                    printf(
                        /* translators: %d: Number of failed attempts */
                        esc_html(_n('Invalid code. %d failed attempt.', 'Invalid code. %d failed attempts.', $failed_attempts, 'sky-seo-boost')),
                        $failed_attempts
                    );
                    ?>
                </p>
            <?php endif; ?>

            <label for="sky-seo-two-step-code">
                <?php esc_html_e('Verification Code:', 'sky-seo-boost'); ?>
            </label>
            <input type="text"
                   name="sky-seo-two-step-email-code"
                   id="sky-seo-two-step-code"
                   class="input"
                   value=""
                   size="20"
                   pattern="[0-9]{6}"
                   inputmode="numeric"
                   maxlength="6"
                   autocomplete="off"
                   placeholder="••••••"
                   required />

            <p style="margin-top: 16px;">
                <label style="display: flex; align-items: center; gap: 8px; cursor: pointer;">
                    <input type="checkbox"
                           name="sky-seo-two-step-remember"
                           id="sky-seo-two-step-remember"
                           value="1"
                           style="margin: 0;" />
                    <span><?php esc_html_e('Remember this device for 14 days', 'sky-seo-boost'); ?></span>
                </label>
            </p>

            <div class="sky-seo-two-step-resend">
                <p><?php esc_html_e("Didn't receive the code?", 'sky-seo-boost'); ?></p>
                <button type="submit"
                        name="sky-seo-two-step-resend"
                        class="button button-secondary"
                        value="1">
                    <?php esc_html_e('Resend Verification Code', 'sky-seo-boost'); ?>
                </button>
                <input type="hidden"
                       name="sky-seo-two-step-resend-nonce"
                       value="<?php echo esc_attr(wp_create_nonce('sky-seo-two-step-resend-' . $user->ID)); ?>" />
            </div>
        </div>
        <?php
    }

    /**
     * Display user settings.
     *
     * @param WP_User $user The user object.
     */
    public function user_settings($user) {
        $is_enabled = Sky_SEO_Two_Step_Core::is_enabled_for_user($user);
        ?>
        <div class="sky-seo-two-step-email-settings">
            <p>
                <strong><?php esc_html_e('Email Verification Status:', 'sky-seo-boost'); ?></strong>
                <?php if ($is_enabled) : ?>
                    <span style="color: #00a32a;"><?php esc_html_e('Enabled', 'sky-seo-boost'); ?></span>
                <?php else : ?>
                    <span style="color: #d63638;"><?php esc_html_e('Disabled', 'sky-seo-boost'); ?></span>
                <?php endif; ?>
            </p>

            <p class="description">
                <?php
                printf(
                    /* translators: %s: User email address */
                    esc_html__('Verification codes will be sent to: %s', 'sky-seo-boost'),
                    '<strong>' . esc_html($user->user_email) . '</strong>'
                );
                ?>
            </p>
        </div>
        <?php
    }
}
