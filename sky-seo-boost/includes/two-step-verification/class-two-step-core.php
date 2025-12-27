<?php
/**
 * Sky SEO Boost - Two-Step Verification Core
 *
 * Adapted from WordPress Two-Factor Plugin (https://github.com/WordPress/two-factor)
 * and Jetpack Force 2FA (https://github.com/Automattic/jetpack)
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
 * Core class for two-step verification functionality.
 */
class Sky_SEO_Two_Step_Core {

    /**
     * User meta key for enabled status.
     *
     * @var string
     */
    const ENABLED_META_KEY = '_sky_seo_two_step_enabled';

    /**
     * User meta key for login nonce.
     *
     * @var string
     */
    const LOGIN_NONCE_META_KEY = '_sky_seo_two_step_login_nonce';

    /**
     * User meta key for rate limiting.
     *
     * @var string
     */
    const RATE_LIMIT_META_KEY = '_sky_seo_two_step_rate_limit';

    /**
     * User meta key for interim authentication token.
     *
     * @var string
     */
    const INTERIM_AUTH_META_KEY = '_sky_seo_two_step_interim_auth';

    /**
     * User meta key for trusted devices.
     *
     * @var string
     */
    const TRUSTED_DEVICES_META_KEY = '_sky_seo_two_step_trusted_devices';

    /**
     * Cookie name for remember device token.
     *
     * @var string
     */
    const REMEMBER_COOKIE_NAME = 'sky_seo_2fa_remember';

    /**
     * Remember device duration (14 days in seconds).
     *
     * @var int
     */
    const REMEMBER_DURATION = 14 * DAY_IN_SECONDS;

    /**
     * The instance of the email provider.
     *
     * @var Sky_SEO_Two_Step_Email
     */
    private static $email_provider;

    /**
     * Initialize the two-step verification.
     */
    public static function init() {
        try {
            // Verify email provider class exists
            if (!class_exists('Sky_SEO_Two_Step_Email')) {
                error_log('Sky SEO Boost: Sky_SEO_Two_Step_Email class not found');
                return false;
            }

            // Load the email provider
            self::$email_provider = new Sky_SEO_Two_Step_Email();

            // Verify email provider was created successfully
            if (!self::$email_provider) {
                error_log('Sky SEO Boost: Failed to create email provider instance');
                return false;
            }

            // Hook into WordPress authentication
            add_filter('authenticate', array(__CLASS__, 'authenticate'), 50, 3);
            add_action('login_form', array(__CLASS__, 'login_form'));
            add_action('login_enqueue_scripts', array(__CLASS__, 'login_enqueue_scripts'));

            // Handle custom 2FA action for front-end redirects
            add_action('login_form_sky_seo_2fa', array(__CLASS__, 'handle_2fa_redirect'));

            // Admin hooks
            if (is_admin()) {
                add_action('admin_init', array(__CLASS__, 'admin_init'));
                add_action('show_user_profile', array(__CLASS__, 'user_profile_fields'));
                add_action('edit_user_profile', array(__CLASS__, 'user_profile_fields'));
                add_action('personal_options_update', array(__CLASS__, 'user_profile_update'));
                add_action('edit_user_profile_update', array(__CLASS__, 'user_profile_update'));
            }

            return true;
        } catch (Exception $e) {
            error_log('Sky SEO Boost: Error initializing two-step verification: ' . $e->getMessage());
            return false;
        }
    }

    /**
     * Check if two-step verification is enabled for a user.
     *
     * @param WP_User $user The user object.
     * @return bool
     */
    public static function is_enabled_for_user($user) {
        if (!is_a($user, 'WP_User')) {
            return false;
        }

        $enabled = get_user_meta($user->ID, self::ENABLED_META_KEY, true);
        return !empty($enabled);
    }

    /**
     * Check if two-step verification is enforced for a user based on role.
     *
     * @param WP_User $user The user object.
     * @return bool
     */
    public static function is_enforced_for_user($user) {
        if (!is_a($user, 'WP_User')) {
            return false;
        }

        $settings = get_option('sky_seo_settings', array());
        $enforce_for_roles = isset($settings['two_step_enforce_roles']) ? $settings['two_step_enforce_roles'] : array();

        if (empty($enforce_for_roles)) {
            return false;
        }

        // Check if user has any of the enforced roles
        foreach ($enforce_for_roles as $role) {
            if (in_array($role, $user->roles)) {
                return true;
            }
        }

        return false;
    }

    /**
     * Check if user should be prompted for two-step verification.
     *
     * @param WP_User $user The user object.
     * @return bool
     */
    public static function should_verify($user) {
        // If enabled for user, require verification
        if (self::is_enabled_for_user($user)) {
            return true;
        }

        // If enforced for user's role, require verification
        if (self::is_enforced_for_user($user)) {
            return true;
        }

        return false;
    }

    /**
     * Check if we're in a context where login_header() and login_footer() are available.
     *
     * @return bool
     */
    private static function can_use_login_functions() {
        return function_exists('login_header') && function_exists('login_footer');
    }

    /**
     * Create an interim authentication token for redirecting to wp-login.php.
     *
     * @param int $user_id The user ID.
     * @param string $password The user's password (will be stored encrypted).
     * @return string The interim token.
     */
    private static function create_interim_token($user_id, $password = '') {
        $token = wp_generate_password(43, false);
        $expiration = time() + (5 * MINUTE_IN_SECONDS); // 5 minute validity

        // Encrypt the password for temporary storage using OpenSSL
        $encrypted_password = '';
        if (!empty($password)) {
            $encrypted_password = self::encrypt_password($password);
        }

        update_user_meta($user_id, self::INTERIM_AUTH_META_KEY, array(
            'token' => wp_hash($token),
            'expiration' => $expiration,
            'password' => $encrypted_password,
        ));

        return $token;
    }

    /**
     * Encrypt a password for temporary storage.
     *
     * @param string $password The password to encrypt.
     * @return string The encrypted password.
     */
    private static function encrypt_password($password) {
        $key = wp_salt('auth');
        $iv = openssl_random_pseudo_bytes(16);
        $encrypted = openssl_encrypt($password, 'AES-256-CBC', $key, 0, $iv);
        return base64_encode($iv . '::' . $encrypted);
    }

    /**
     * Decrypt a password from temporary storage.
     *
     * @param string $encrypted_password The encrypted password.
     * @return string The decrypted password.
     */
    private static function decrypt_password($encrypted_password) {
        $key = wp_salt('auth');
        $data = base64_decode($encrypted_password);
        $parts = explode('::', $data, 2);
        if (count($parts) !== 2) {
            return '';
        }
        list($iv, $encrypted) = $parts;
        return openssl_decrypt($encrypted, 'AES-256-CBC', $key, 0, $iv);
    }

    /**
     * Verify and consume an interim authentication token.
     *
     * @param int $user_id The user ID.
     * @param string $token The token to verify.
     * @return array|false Returns token data array on success, false on failure.
     */
    private static function verify_interim_token($user_id, $token) {
        $token_data = get_user_meta($user_id, self::INTERIM_AUTH_META_KEY, true);

        if (empty($token_data) || !is_array($token_data)) {
            return false;
        }

        if (empty($token_data['token']) || empty($token_data['expiration'])) {
            return false;
        }

        // Check expiration
        if (time() > $token_data['expiration']) {
            delete_user_meta($user_id, self::INTERIM_AUTH_META_KEY);
            return false;
        }

        // Verify token using timing-safe comparison
        if (hash_equals($token_data['token'], wp_hash($token))) {
            // Token is valid, delete it (one-time use)
            delete_user_meta($user_id, self::INTERIM_AUTH_META_KEY);

            // Return the token data (including encrypted password)
            return $token_data;
        }

        return false;
    }

    /**
     * Generate device fingerprint for identification.
     * Uses only user agent for stability - HTTP_ACCEPT_LANGUAGE can vary between requests.
     *
     * @return string
     */
    private static function generate_device_fingerprint() {
        $user_agent = isset($_SERVER['HTTP_USER_AGENT']) ? sanitize_text_field(wp_unslash($_SERVER['HTTP_USER_AGENT'])) : '';

        // Create a fingerprint from browser user agent only (more stable than including accept-language)
        return wp_hash($user_agent);
    }

    /**
     * Check if current device is trusted for a user.
     *
     * @param int $user_id The user ID.
     * @return bool
     */
    private static function is_device_trusted($user_id) {
        // Check if remember cookie exists
        if (!isset($_COOKIE[self::REMEMBER_COOKIE_NAME])) {
            return false;
        }

        $cookie_token = sanitize_text_field($_COOKIE[self::REMEMBER_COOKIE_NAME]);
        $device_fingerprint = self::generate_device_fingerprint();

        // Get trusted devices for this user
        $trusted_devices = get_user_meta($user_id, self::TRUSTED_DEVICES_META_KEY, true);

        if (empty($trusted_devices) || !is_array($trusted_devices)) {
            return false;
        }

        // Check each trusted device
        foreach ($trusted_devices as $device) {
            if (!isset($device['token']) || !isset($device['fingerprint']) || !isset($device['expiration'])) {
                continue;
            }

            // Check if token matches and hasn't expired
            if (hash_equals($device['token'], wp_hash($cookie_token)) &&
                $device['fingerprint'] === $device_fingerprint &&
                time() < $device['expiration']) {
                return true;
            }
        }

        return false;
    }

    /**
     * Save trusted device for a user.
     *
     * @param int $user_id The user ID.
     * @return string The device token for cookie.
     */
    private static function save_trusted_device($user_id) {
        // Generate secure random token
        $token = wp_generate_password(43, false);
        $device_fingerprint = self::generate_device_fingerprint();
        $expiration = time() + self::REMEMBER_DURATION;

        // Get existing trusted devices
        $trusted_devices = get_user_meta($user_id, self::TRUSTED_DEVICES_META_KEY, true);

        if (empty($trusted_devices) || !is_array($trusted_devices)) {
            $trusted_devices = array();
        }

        // Clean up expired devices
        $trusted_devices = array_filter($trusted_devices, function($device) {
            return isset($device['expiration']) && time() < $device['expiration'];
        });

        // Add new device
        $trusted_devices[] = array(
            'token' => wp_hash($token),
            'fingerprint' => $device_fingerprint,
            'created' => time(),
            'expiration' => $expiration,
            'ip' => isset($_SERVER['REMOTE_ADDR']) ? sanitize_text_field(wp_unslash($_SERVER['REMOTE_ADDR'])) : '',
            'user_agent' => isset($_SERVER['HTTP_USER_AGENT']) ? sanitize_text_field(wp_unslash($_SERVER['HTTP_USER_AGENT'])) : '',
        );

        // Limit to 10 devices per user
        if (count($trusted_devices) > 10) {
            // Remove oldest devices
            usort($trusted_devices, function($a, $b) {
                return $a['created'] - $b['created'];
            });
            $trusted_devices = array_slice($trusted_devices, -10);
        }

        // Save to user meta
        update_user_meta($user_id, self::TRUSTED_DEVICES_META_KEY, $trusted_devices);

        // Get cookie path - use '/' if COOKIEPATH is empty or not defined
        $cookie_path = defined('COOKIEPATH') && COOKIEPATH ? COOKIEPATH : '/';
        $cookie_domain = defined('COOKIE_DOMAIN') ? COOKIE_DOMAIN : '';

        // Set cookie using both methods for maximum compatibility
        // Method 1: Modern array syntax (PHP 7.3+)
        if (version_compare(PHP_VERSION, '7.3.0', '>=')) {
            setcookie(
                self::REMEMBER_COOKIE_NAME,
                $token,
                array(
                    'expires' => $expiration,
                    'path' => $cookie_path,
                    'domain' => $cookie_domain,
                    'secure' => is_ssl(),
                    'httponly' => true,
                    'samesite' => 'Lax'
                )
            );
        } else {
            // Method 2: Legacy syntax for older PHP versions
            setcookie(
                self::REMEMBER_COOKIE_NAME,
                $token,
                $expiration,
                $cookie_path,
                $cookie_domain,
                is_ssl(),
                true
            );
        }

        return $token;
    }

    /**
     * Remove all trusted devices for a user.
     *
     * @param int $user_id The user ID.
     */
    public static function remove_all_trusted_devices($user_id) {
        delete_user_meta($user_id, self::TRUSTED_DEVICES_META_KEY);

        // Clear cookie
        if (isset($_COOKIE[self::REMEMBER_COOKIE_NAME])) {
            // Get cookie path - use '/' if COOKIEPATH is empty or not defined
            $cookie_path = defined('COOKIEPATH') && COOKIEPATH ? COOKIEPATH : '/';
            $cookie_domain = defined('COOKIE_DOMAIN') ? COOKIE_DOMAIN : '';

            if (version_compare(PHP_VERSION, '7.3.0', '>=')) {
                setcookie(
                    self::REMEMBER_COOKIE_NAME,
                    '',
                    array(
                        'expires' => time() - 3600,
                        'path' => $cookie_path,
                        'domain' => $cookie_domain,
                        'secure' => is_ssl(),
                        'httponly' => true,
                        'samesite' => 'Lax'
                    )
                );
            } else {
                setcookie(
                    self::REMEMBER_COOKIE_NAME,
                    '',
                    time() - 3600,
                    $cookie_path,
                    $cookie_domain,
                    is_ssl(),
                    true
                );
            }
        }
    }

    /**
     * Get all trusted devices for a user.
     *
     * @param int $user_id The user ID.
     * @return array
     */
    public static function get_trusted_devices($user_id) {
        $trusted_devices = get_user_meta($user_id, self::TRUSTED_DEVICES_META_KEY, true);

        if (empty($trusted_devices) || !is_array($trusted_devices)) {
            return array();
        }

        // Clean up expired devices
        $trusted_devices = array_filter($trusted_devices, function($device) {
            return isset($device['expiration']) && time() < $device['expiration'];
        });

        // Save cleaned list
        update_user_meta($user_id, self::TRUSTED_DEVICES_META_KEY, $trusted_devices);

        return $trusted_devices;
    }

    /**
     * Authenticate user with two-step verification.
     *
     * @param WP_User|WP_Error|null $user User object or error.
     * @param string $username Username.
     * @param string $password Password.
     * @return WP_User|WP_Error
     */
    public static function authenticate($user, $username, $password) {
        try {
            // Skip if not a valid user
            if (!is_a($user, 'WP_User')) {
                return $user;
            }

            // Verify email provider is initialized
            if (!self::$email_provider) {
                error_log('Sky SEO Boost: Email provider not initialized in authenticate()');
                return $user; // Allow login to proceed without 2FA if there's an error
            }

            // Skip if two-step not required for this user
            if (!self::should_verify($user)) {
                return $user;
            }

            // Check if device is trusted (skip 2FA for trusted devices)
            if (self::is_device_trusted($user->ID)) {
                return $user;
            }

            // Check if we're processing the two-step verification form
            if (isset($_POST['sky-seo-two-step-email-code'])) {
                return self::process_two_step_form($user);
            }

            // Show the two-step verification form
            // (will redirect to wp-login.php if on front-end)
            self::show_two_step_form($user, $username, $password);
            exit;
        } catch (Exception $e) {
            error_log('Sky SEO Boost: Error in two-step authentication: ' . $e->getMessage());
            // Return user to allow login to proceed - security is secondary to site accessibility
            return $user;
        }
    }

    /**
     * Process the two-step verification form.
     *
     * @param WP_User $user The user object.
     * @return WP_User|WP_Error
     */
    private static function process_two_step_form($user) {
        try {
            // Verify email provider is available
            if (!self::$email_provider) {
                error_log('Sky SEO Boost: Email provider not available in process_two_step_form()');
                return new WP_Error(
                    'two_step_error',
                    __('Two-step verification system error. Please contact the administrator.', 'sky360')
                );
            }

            // Verify nonce
            if (!isset($_POST['sky-seo-two-step-nonce']) ||
                !self::verify_login_nonce($user->ID, $_POST['sky-seo-two-step-nonce'])) {
                return new WP_Error(
                    'invalid_nonce',
                    __('Session expired. Please log in again.', 'sky360')
                );
            }

            // Check rate limiting
            if (self::is_rate_limited($user)) {
                $delay = self::get_rate_limit_delay($user);
                return new WP_Error(
                    'rate_limited',
                    sprintf(
                        /* translators: %d: Number of seconds */
                        __('Too many failed attempts. Please wait %d seconds before trying again.', 'sky360'),
                        $delay
                    )
                );
            }

            // Handle resend - only if value is explicitly '1' (button was clicked)
            if (isset($_POST['sky-seo-two-step-resend']) && $_POST['sky-seo-two-step-resend'] === '1') {
                $username = isset($_POST['log']) ? sanitize_user(wp_unslash($_POST['log'])) : '';
                $password = isset($_POST['pwd']) ? wp_unslash($_POST['pwd']) : '';
                self::show_two_step_form($user, $username, $password);
                exit;
            }

            // Validate the verification code
            $code = sanitize_text_field($_POST['sky-seo-two-step-email-code']);

            if (self::$email_provider->validate_token($user, $code)) {
                // Success - clear the nonce and allow login
                delete_user_meta($user->ID, self::LOGIN_NONCE_META_KEY);
                delete_user_meta($user->ID, self::RATE_LIMIT_META_KEY);

                // Handle "Remember This Device" option
                if (isset($_POST['sky-seo-two-step-remember']) && $_POST['sky-seo-two-step-remember'] === '1') {
                    self::save_trusted_device($user->ID);
                }

                return $user;
            }

            // Failed verification
            self::record_rate_limit($user);

            return new WP_Error(
                'invalid_code',
                __('Invalid verification code. Please try again.', 'sky360')
            );
        } catch (Exception $e) {
            error_log('Sky SEO Boost: Error in process_two_step_form(): ' . $e->getMessage());
            return new WP_Error(
                'two_step_error',
                __('An error occurred during verification. Please try again.', 'sky360')
            );
        }
    }

    /**
     * Redirect front-end login to wp-login.php for 2FA processing.
     *
     * @param WP_User $user The user object.
     * @param string $password The user's password for temporary storage.
     */
    private static function redirect_to_login_for_2fa($user, $password = '') {
        // Create interim authentication token with encrypted password
        $interim_token = self::create_interim_token($user->ID, $password);

        // Note: Email will be sent by authentication_page() when the form is displayed
        // No need to send it here to avoid duplicate emails

        // Get the original redirect destination (sanitize to prevent open redirect)
        $redirect_to = isset($_REQUEST['redirect_to']) ? esc_url_raw(wp_unslash($_REQUEST['redirect_to'])) : '';

        // If no redirect is specified, try to capture the page they came from
        if (empty($redirect_to) && !empty($_SERVER['HTTP_REFERER'])) {
            $referer = esc_url_raw(wp_unslash($_SERVER['HTTP_REFERER']));
            // Only use referer if it's from the same site (security check)
            if (strpos($referer, home_url()) === 0) {
                $redirect_to = $referer;
            }
        }

        // Build the redirect URL to wp-login.php
        $login_url = add_query_arg(
            array(
                'action' => 'sky_seo_2fa',
                'user_id' => $user->ID,
                'interim_token' => urlencode($interim_token),
                'redirect_to' => urlencode($redirect_to),
            ),
            wp_login_url()
        );

        // Perform the redirect
        wp_safe_redirect($login_url);
        exit;
    }

    /**
     * Show the two-step verification form.
     *
     * @param WP_User $user The user object.
     * @param string $username The username from login form.
     * @param string $password The password from login form.
     */
    private static function show_two_step_form($user, $username = '', $password = '') {
        try {
            // Verify email provider is available
            if (!self::$email_provider) {
                error_log('Sky SEO Boost: Email provider not available in show_two_step_form()');
                wp_die(
                    __('Two-step verification system error. Please contact the administrator.', 'sky360'),
                    __('Authentication Error', 'sky360'),
                    array('response' => 500)
                );
            }

            // Check if we're in a front-end context (login_header/login_footer not available)
            if (!self::can_use_login_functions()) {
                // Front-end login detected - redirect to wp-login.php for proper 2FA handling
                self::redirect_to_login_for_2fa($user, $password);
                exit;
            }

            // Create a login nonce for this session
            $login_nonce = self::create_login_nonce($user->ID);

            // Get the redirect URL (sanitize to prevent open redirect)
            $redirect_to = isset($_REQUEST['redirect_to']) ? esc_url_raw(wp_unslash($_REQUEST['redirect_to'])) : admin_url();

            // Start output buffering to capture the login page
            ob_start();

            login_header(
                __('Two-Step Verification', 'sky360'),
                '',
                ''
            );

            ?>
            <form name="sky-seo-two-step-form" id="sky-seo-two-step-form" action="<?php echo esc_url(site_url('wp-login.php', 'login_post')); ?>" method="post">
                <?php self::$email_provider->authentication_page($user); ?>

                <p class="submit">
                    <input type="submit" name="wp-submit" id="wp-submit" class="button button-primary button-large" value="<?php esc_attr_e('Verify', 'sky360'); ?>" />
                </p>

                <input type="hidden" name="sky-seo-two-step-nonce" value="<?php echo esc_attr($login_nonce); ?>" />
                <input type="hidden" name="log" value="<?php echo esc_attr($username); ?>" />
                <input type="hidden" name="pwd" value="<?php echo esc_attr($password); ?>" />
                <input type="hidden" name="redirect_to" value="<?php echo esc_attr($redirect_to); ?>" />
                <input type="hidden" name="rememberme" value="<?php echo isset($_POST['rememberme']) ? esc_attr($_POST['rememberme']) : ''; ?>" />
            </form>

            <p id="backtoblog">
                <a href="<?php echo esc_url(home_url('/')); ?>"><?php
                    printf(
                        /* translators: %s: Site title */
                        esc_html__('&larr; Go to %s', 'sky360'),
                        esc_html(get_bloginfo('title'))
                    );
                ?></a>
            </p>

            <script type="text/javascript">
            document.addEventListener('DOMContentLoaded', function() {
                var codeInput = document.getElementById('sky-seo-two-step-code');
                if (codeInput) {
                    codeInput.focus();
                }
            });
            </script>
            <?php

            login_footer();

            // Output the buffer and exit
            echo ob_get_clean();
        } catch (Exception $e) {
            error_log('Sky SEO Boost: Error in show_two_step_form(): ' . $e->getMessage());
            wp_die(
                __('An error occurred during authentication. Please try again or contact the administrator.', 'sky360'),
                __('Authentication Error', 'sky360'),
                array('response' => 500)
            );
        }
    }

    /**
     * Create a login nonce for the user.
     *
     * @param int $user_id The user ID.
     * @return string
     */
    private static function create_login_nonce($user_id) {
        $nonce = wp_generate_password(32, false);
        $expiration = time() + (10 * MINUTE_IN_SECONDS);

        update_user_meta($user_id, self::LOGIN_NONCE_META_KEY, array(
            'nonce' => wp_hash($nonce),
            'expiration' => $expiration,
        ));

        return $nonce;
    }

    /**
     * Verify a login nonce.
     *
     * @param int $user_id The user ID.
     * @param string $nonce The nonce to verify.
     * @return bool
     */
    private static function verify_login_nonce($user_id, $nonce) {
        $nonce_data = get_user_meta($user_id, self::LOGIN_NONCE_META_KEY, true);

        if (empty($nonce_data) || !is_array($nonce_data)) {
            return false;
        }

        if (empty($nonce_data['nonce']) || empty($nonce_data['expiration'])) {
            return false;
        }

        // Check expiration
        if (time() > $nonce_data['expiration']) {
            delete_user_meta($user_id, self::LOGIN_NONCE_META_KEY);
            return false;
        }

        // Verify nonce
        return hash_equals($nonce_data['nonce'], wp_hash($nonce));
    }

    /**
     * Check if a user is rate limited.
     *
     * @param WP_User $user The user object.
     * @return bool
     */
    private static function is_rate_limited($user) {
        $rate_limit = get_user_meta($user->ID, self::RATE_LIMIT_META_KEY, true);

        if (empty($rate_limit)) {
            return false;
        }

        $attempts = isset($rate_limit['attempts']) ? (int) $rate_limit['attempts'] : 0;
        $last_attempt = isset($rate_limit['last_attempt']) ? (int) $rate_limit['last_attempt'] : 0;

        if ($attempts === 0) {
            return false;
        }

        // Calculate delay based on attempts (exponential backoff)
        $delay = min(pow(2, $attempts - 1), 900); // Max 15 minutes

        return (time() - $last_attempt) < $delay;
    }

    /**
     * Get the current rate limit delay for a user.
     *
     * @param WP_User $user The user object.
     * @return int
     */
    private static function get_rate_limit_delay($user) {
        $rate_limit = get_user_meta($user->ID, self::RATE_LIMIT_META_KEY, true);

        if (empty($rate_limit)) {
            return 0;
        }

        $attempts = isset($rate_limit['attempts']) ? (int) $rate_limit['attempts'] : 0;
        $last_attempt = isset($rate_limit['last_attempt']) ? (int) $rate_limit['last_attempt'] : 0;

        $delay = min(pow(2, $attempts - 1), 900); // Max 15 minutes
        $remaining = $delay - (time() - $last_attempt);

        return max(0, $remaining);
    }

    /**
     * Record a rate limit event.
     *
     * @param WP_User $user The user object.
     */
    private static function record_rate_limit($user) {
        $rate_limit = get_user_meta($user->ID, self::RATE_LIMIT_META_KEY, true);

        if (empty($rate_limit)) {
            $rate_limit = array(
                'attempts' => 0,
                'last_attempt' => 0,
            );
        }

        $rate_limit['attempts']++;
        $rate_limit['last_attempt'] = time();

        update_user_meta($user->ID, self::RATE_LIMIT_META_KEY, $rate_limit);
    }

    /**
     * Add login form HTML.
     */
    public static function login_form() {
        // Add any necessary HTML to the standard login form
    }

    /**
     * Enqueue scripts on the login page.
     */
    public static function login_enqueue_scripts() {
        // Add any necessary scripts/styles
    }

    /**
     * Admin initialization.
     */
    public static function admin_init() {
        // Any admin-specific initialization
    }

    /**
     * Display two-step verification fields in user profile.
     *
     * @param WP_User $user The user object.
     */
    public static function user_profile_fields($user) {
        if (!current_user_can('edit_user', $user->ID)) {
            return;
        }

        $is_enabled = self::is_enabled_for_user($user);
        $is_enforced = self::is_enforced_for_user($user);

        wp_nonce_field('sky_seo_two_step_user_options', '_sky_seo_two_step_nonce');

        ?>
        <h2><?php esc_html_e('Two-Step Verification', 'sky360'); ?></h2>
        <table class="form-table">
            <tr>
                <th><?php esc_html_e('Email Verification', 'sky360'); ?></th>
                <td>
                    <?php if ($is_enforced) : ?>
                        <p>
                            <strong><?php esc_html_e('Status:', 'sky360'); ?></strong>
                            <span style="color: #00a32a;"><?php esc_html_e('Enforced by administrator', 'sky360'); ?></span>
                        </p>
                        <p class="description">
                            <?php esc_html_e('Two-step verification is required for your user role and cannot be disabled.', 'sky360'); ?>
                        </p>
                    <?php else : ?>
                        <label for="sky_seo_two_step_enabled">
                            <input type="checkbox"
                                   name="sky_seo_two_step_enabled"
                                   id="sky_seo_two_step_enabled"
                                   value="1"
                                   <?php checked($is_enabled); ?> />
                            <?php esc_html_e('Enable two-step verification via email', 'sky360'); ?>
                        </label>
                        <p class="description">
                            <?php esc_html_e('When enabled, you will receive a verification code via email each time you log in.', 'sky360'); ?>
                        </p>
                    <?php endif; ?>

                    <?php do_action('sky_seo_two_step_user_settings', $user); ?>
                </td>
            </tr>

            <?php if ($is_enabled || $is_enforced) : ?>
            <tr>
                <th><?php esc_html_e('Trusted Devices', 'sky360'); ?></th>
                <td>
                    <?php
                    $trusted_devices = self::get_trusted_devices($user->ID);

                    if (!empty($trusted_devices)) :
                    ?>
                        <p><?php esc_html_e('These devices are trusted and will not require 2FA verification:', 'sky360'); ?></p>
                        <table class="widefat striped" style="max-width: 600px; margin-top: 10px;">
                            <thead>
                                <tr>
                                    <th><?php esc_html_e('Device', 'sky360'); ?></th>
                                    <th><?php esc_html_e('Added', 'sky360'); ?></th>
                                    <th><?php esc_html_e('Expires', 'sky360'); ?></th>
                                </tr>
                            </thead>
                            <tbody>
                                <?php foreach ($trusted_devices as $device) : ?>
                                <tr>
                                    <td>
                                        <strong><?php echo esc_html(self::parse_user_agent($device['user_agent'])); ?></strong><br />
                                        <small style="color: #666;"><?php echo esc_html($device['ip']); ?></small>
                                    </td>
                                    <td><?php echo esc_html(date_i18n(get_option('date_format'), $device['created'])); ?></td>
                                    <td><?php echo esc_html(date_i18n(get_option('date_format'), $device['expiration'])); ?></td>
                                </tr>
                                <?php endforeach; ?>
                            </tbody>
                        </table>

                        <p style="margin-top: 10px;">
                            <button type="submit" name="sky_seo_two_step_clear_devices" value="1" class="button" onclick="return confirm('<?php esc_attr_e('Are you sure you want to remove all trusted devices? You will need to verify with 2FA on all devices.', 'sky360'); ?>');">
                                <?php esc_html_e('Remove All Trusted Devices', 'sky360'); ?>
                            </button>
                        </p>
                    <?php else : ?>
                        <p class="description">
                            <?php esc_html_e('No trusted devices. Use "Remember this device" when logging in to skip 2FA verification for 14 days.', 'sky360'); ?>
                        </p>
                    <?php endif; ?>
                </td>
            </tr>
            <?php endif; ?>
        </table>
        <?php
    }

    /**
     * Parse user agent string to get browser/device name.
     *
     * @param string $user_agent The user agent string.
     * @return string
     */
    private static function parse_user_agent($user_agent) {
        if (empty($user_agent)) {
            return __('Unknown Device', 'sky360');
        }

        // Simple browser detection
        if (strpos($user_agent, 'Firefox') !== false) {
            return 'Firefox';
        } elseif (strpos($user_agent, 'Chrome') !== false) {
            return 'Chrome';
        } elseif (strpos($user_agent, 'Safari') !== false) {
            return 'Safari';
        } elseif (strpos($user_agent, 'Edge') !== false) {
            return 'Edge';
        } elseif (strpos($user_agent, 'Opera') !== false || strpos($user_agent, 'OPR') !== false) {
            return 'Opera';
        }

        return __('Unknown Browser', 'sky360');
    }

    /**
     * Save two-step verification user settings.
     *
     * @param int $user_id The user ID.
     */
    public static function user_profile_update($user_id) {
        if (!current_user_can('edit_user', $user_id)) {
            return;
        }

        if (!isset($_POST['_sky_seo_two_step_nonce']) ||
            !wp_verify_nonce($_POST['_sky_seo_two_step_nonce'], 'sky_seo_two_step_user_options')) {
            return;
        }

        // Don't allow disabling if enforced
        $user = get_userdata($user_id);
        if (self::is_enforced_for_user($user)) {
            update_user_meta($user_id, self::ENABLED_META_KEY, 1);
            return;
        }

        // Update enabled status
        $enabled = isset($_POST['sky_seo_two_step_enabled']) ? 1 : 0;
        update_user_meta($user_id, self::ENABLED_META_KEY, $enabled);

        // Clear any pending tokens if disabling
        if (!$enabled) {
            delete_user_meta($user_id, Sky_SEO_Two_Step_Email::TOKEN_META_KEY);
            delete_user_meta($user_id, Sky_SEO_Two_Step_Email::FAILED_ATTEMPTS_META_KEY);
            delete_user_meta($user_id, Sky_SEO_Two_Step_Email::LAST_FAILED_META_KEY);
            delete_user_meta($user_id, self::LOGIN_NONCE_META_KEY);
            delete_user_meta($user_id, self::RATE_LIMIT_META_KEY);
            // Also clear trusted devices
            self::remove_all_trusted_devices($user_id);
        }

        // Handle clear trusted devices request
        if (isset($_POST['sky_seo_two_step_clear_devices']) && $_POST['sky_seo_two_step_clear_devices'] === '1') {
            self::remove_all_trusted_devices($user_id);
        }
    }

    /**
     * Handle 2FA redirect from front-end login.
     * This is called when action=sky_seo_2fa is set on wp-login.php.
     */
    public static function handle_2fa_redirect() {
        // Verify required parameters
        if (!isset($_GET['user_id']) || !isset($_GET['interim_token'])) {
            wp_die(
                __('Invalid authentication request. Please try logging in again.', 'sky360'),
                __('Authentication Error', 'sky360'),
                array('response' => 400)
            );
        }

        $user_id = absint($_GET['user_id']);
        $interim_token = sanitize_text_field($_GET['interim_token']);

        // Get user object
        $user = get_userdata($user_id);
        if (!$user) {
            wp_die(
                __('Invalid user. Please try logging in again.', 'sky360'),
                __('Authentication Error', 'sky360'),
                array('response' => 400)
            );
        }

        // Verify interim token and get stored data
        $token_data = self::verify_interim_token($user_id, $interim_token);
        if (!$token_data) {
            login_header(
                __('Two-Step Verification', 'sky360'),
                '<p class="message error">' . __('Authentication session expired. Please try logging in again.', 'sky360') . '</p>'
            );
            login_footer();
            exit;
        }

        // Decrypt the password using proper decryption
        $password = '';
        if (!empty($token_data['password'])) {
            $password = self::decrypt_password($token_data['password']);
        }

        // Create a login nonce for this session
        $login_nonce = self::create_login_nonce($user_id);

        // Get the redirect URL with intelligent fallback
        // Priority: 1) Explicit redirect_to param, 2) Captured referer, 3) WooCommerce account, 4) Home
        if (isset($_GET['redirect_to']) && !empty($_GET['redirect_to'])) {
            $redirect_to = esc_url_raw(urldecode($_GET['redirect_to']));
        } else {
            // No redirect specified - use smart defaults for front-end logins
            // Check if WooCommerce is active and redirect to My Account page (works with custom slugs)
            if (function_exists('wc_get_page_permalink')) {
                $redirect_to = wc_get_page_permalink('myaccount');
            } else {
                // Fallback to home page for non-WooCommerce sites
                $redirect_to = home_url();
            }
        }

        // Display the 2FA form
        login_header(
            __('Two-Step Verification', 'sky360'),
            '',
            ''
        );

        ?>
        <form name="sky-seo-two-step-form" id="sky-seo-two-step-form" action="<?php echo esc_url(site_url('wp-login.php', 'login_post')); ?>" method="post">
            <?php self::$email_provider->authentication_page($user); ?>

            <p class="submit">
                <input type="submit" name="wp-submit" id="wp-submit" class="button button-primary button-large" value="<?php esc_attr_e('Verify', 'sky360'); ?>" />
            </p>

            <input type="hidden" name="sky-seo-two-step-nonce" value="<?php echo esc_attr($login_nonce); ?>" />
            <input type="hidden" name="log" value="<?php echo esc_attr($user->user_login); ?>" />
            <input type="hidden" name="pwd" value="<?php echo esc_attr($password); ?>" />
            <input type="hidden" name="redirect_to" value="<?php echo esc_attr($redirect_to); ?>" />
        </form>

        <p id="backtoblog">
            <a href="<?php echo esc_url(home_url('/')); ?>"><?php
                printf(
                    /* translators: %s: Site title */
                    esc_html__('&larr; Go to %s', 'sky360'),
                    esc_html(get_bloginfo('title'))
                );
            ?></a>
        </p>

        <script type="text/javascript">
        document.addEventListener('DOMContentLoaded', function() {
            var codeInput = document.getElementById('sky-seo-two-step-code');
            if (codeInput) {
                codeInput.focus();
            }
        });
        </script>
        <?php

        login_footer();
        exit;
    }
}
