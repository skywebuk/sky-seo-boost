<?php
// File: includes/license-manager.php

// Prevent direct access
if (!defined('ABSPATH')) {
    exit;
}

/**
 * Sky SEO Boost - License Management System (Optimized)
 * Production-ready version with single daily check to prevent IP blocking
 */
class Sky_SEO_License_Manager {
    
    /**
     * License server URL
     */
    const LICENSE_SERVER_URL = 'https://skywebdesign.co.uk/wp-content/plugins/sky-seo-license-manager/api-endpoint.php';
    
    /**
     * Update server URL
     */
    const UPDATE_SERVER_URL = 'https://skywebdesign.co.uk/wp-content/plugins/sky-seo-license-manager/update-handler.php';
    
    /**
     * Option name for storing license data
     */
    const LICENSE_OPTION_KEY = 'sky_seo_license_data';
    
    /**
     * Option for last check timestamp
     */
    const LAST_CHECK_OPTION = 'sky_seo_last_license_check';
    
    /**
     * Option for license status cache
     */
    const LICENSE_STATUS_CACHE = 'sky_seo_license_status_cache';
    
    /**
     * Check frequency - 24 hours
     */
    const CHECK_FREQUENCY = 86400; // 24 hours in seconds
    
    /**
     * Grace period when server is unreachable - 3 days
     */
    const GRACE_PERIOD = 259200; // 3 days in seconds
    
    /**
     * Instance of the class
     */
    private static $instance = null;
    
    /**
     * License validity flag
     */
    private $is_valid = null;
    
    /**
     * Get instance of the class
     */
    public static function get_instance() {
        if (self::$instance === null) {
            self::$instance = new self();
        }
        return self::$instance;
    }
    
    /**
     * Constructor
     */
    private function __construct() {
        // Check if signature verification is available
        if (!Sky_SEO_License_Signature_Validator::is_available()) {
            add_action('admin_notices', [$this, 'show_openssl_warning']);
        }

        // Perform initial license check (respects daily limit)
        $this->check_license_if_needed();

        // Initialize core functionality based on license status
        if (!$this->is_license_valid()) {
            // License invalid - show notices and restrict functionality
            add_action('admin_notices', [$this, 'show_license_required_notice']);
            add_action('admin_init', [$this, 'disable_plugin_features']);

            // Still allow license management
            $this->init_license_management();

            return; // Stop here if no valid license
        }

        // Full initialization only if license is valid
        $this->init_license_management();
        $this->init_update_checker();

        // Schedule SINGLE daily license check
        add_action('wp', [$this, 'schedule_daily_check']);
        add_action('sky_seo_daily_license_check', [$this, 'perform_scheduled_check']);
    }
    
    /**
     * Check license if needed (respects daily limit)
     */
    private function check_license_if_needed() {
        // Get last check time
        $last_check = get_option(self::LAST_CHECK_OPTION, 0);
        $time_since_check = time() - $last_check;
        
        // Only check if it's been more than 24 hours
        if ($time_since_check < self::CHECK_FREQUENCY) {
            // Use cached status
            $cached_status = get_option(self::LICENSE_STATUS_CACHE, null);
            if ($cached_status !== null) {
                $this->is_valid = $cached_status;
                return;
            }
        }
        
        // Time for a check - but only if we have a license key
        $license_data = $this->get_license_data();
        if (!empty($license_data['key'])) {
            $this->perform_license_verification();
        } else {
            $this->is_valid = false;
            update_option(self::LICENSE_STATUS_CACHE, false);
        }
    }
    
    /**
     * Perform actual license verification (rate-limited)
     */
    private function perform_license_verification() {
        $license_data = $this->get_license_data();

        if (empty($license_data['key'])) {
            $this->is_valid = false;
            update_option(self::LICENSE_STATUS_CACHE, false);
            return;
        }

        // Make request to license server using new API handler
        $response = Sky_SEO_API_Request_Handler::request(
            self::LICENSE_SERVER_URL,
            [
                'body' => [
                    'license' => $license_data['key'],
                    'domain' => $this->get_current_domain()
                ]
            ],
            'critical',  // Use critical timeout (10 seconds)
            true         // Enable retry logic
        );

        // Update last check time immediately
        update_option(self::LAST_CHECK_OPTION, time());

        if (is_wp_error($response)) {
            // Server unreachable - use grace period
            $this->handle_server_error();
            return;
        }

        // Parse JSON response
        $data = Sky_SEO_API_Request_Handler::get_json_response($response);

        if ($data === null) {
            if (defined('WP_DEBUG') && WP_DEBUG) {
                error_log('Sky SEO: Failed to parse license server response');
            }
            $this->handle_server_error();
            return;
        }

        // OPTIONAL: Verify signature if present (enhanced security)
        if (isset($data['signature'])) {
            // Signature present - verify it
            if (!Sky_SEO_License_Signature_Validator::is_available()) {
                // OpenSSL not available - cannot verify
                if (defined('WP_DEBUG') && WP_DEBUG) {
                    error_log('Sky SEO: OpenSSL not available - cannot verify license signature');
                }
                // Continue without signature verification
            } else {
                // OpenSSL available - verify signature
                if (!Sky_SEO_License_Signature_Validator::verify_response($data)) {
                    error_log('Sky SEO: License response signature verification failed - possible tampering or man-in-the-middle attack');
                    $this->handle_server_error();
                    return;
                }

                // Signature verified successfully
                if (defined('WP_DEBUG') && WP_DEBUG) {
                    error_log('Sky SEO: License response signature verified successfully');
                }
            }
        } else {
            // Signature not present - log warning but continue
            // TODO: Make signature mandatory once license server is updated
            if (defined('WP_DEBUG') && WP_DEBUG) {
                error_log('Sky SEO: License response missing signature - enhanced security disabled. Update license server to add signatures.');
            }
        }

        // Signature verified or OpenSSL unavailable - check authentication status
        $is_valid = !empty($data['authenticated']) && $data['authenticated'] === true;

        // Update cache
        $this->is_valid = $is_valid;
        update_option(self::LICENSE_STATUS_CACHE, $is_valid);

        // Update license data with check time
        $license_data['last_check'] = time();
        $license_data['last_check_success'] = true;
        update_option(self::LICENSE_OPTION_KEY, $license_data);

        // If license is now invalid, handle it
        if (!$is_valid && !empty($license_data['key'])) {
            $this->handle_invalid_license();
        }
    }
    
    /**
     * Handle server error with grace period
     */
    private function handle_server_error() {
        $license_data = $this->get_license_data();
        
        // Check if we're within grace period
        $last_successful_check = isset($license_data['last_check']) ? $license_data['last_check'] : 0;
        $time_since_success = time() - $last_successful_check;
        
        if ($time_since_success < self::GRACE_PERIOD) {
            // Within grace period - assume valid
            $this->is_valid = true;
            update_option(self::LICENSE_STATUS_CACHE, true);
            
            // Log the issue
            if (defined('WP_DEBUG') && WP_DEBUG) {
                error_log('Sky SEO: License server unreachable, using grace period. Time remaining: ' . 
                         (self::GRACE_PERIOD - $time_since_success) . ' seconds');
            }
        } else {
            // Grace period expired - disable
            $this->is_valid = false;
            update_option(self::LICENSE_STATUS_CACHE, false);
            
            // Add admin notice about server issue
            set_transient('sky_seo_server_error', true, DAY_IN_SECONDS);
        }
    }
    
    /**
     * Handle invalid license detection
     */
    private function handle_invalid_license() {
        // Set transient for admin notice
        set_transient('sky_seo_license_invalid', true, DAY_IN_SECONDS);
        
        // Log the event
        if (defined('WP_DEBUG') && WP_DEBUG) {
            error_log('Sky SEO: License validation failed - plugin features disabled');
        }
    }
    
    /**
     * Initialize update checker (simplified)
     */
    private function init_update_checker() {
        $plugin_file = $this->get_plugin_file();
        if (!$plugin_file) {
            return;
        }
        
        // Only hook into standard WordPress update checks
        add_filter('pre_set_site_transient_update_plugins', [$this, 'check_for_update']);
        add_filter('plugins_api', [$this, 'plugin_info'], 10, 3);
    }
    
    /**
     * Check for updates (only when WordPress checks)
     */
    public function check_for_update($transient) {
        if (empty($transient->checked)) {
            return $transient;
        }
        
        $plugin_file = $this->get_plugin_file();
        if (!$plugin_file) {
            return $transient;
        }
        
        $plugin_basename = plugin_basename($plugin_file);
        $plugin_data = get_plugin_data($plugin_file);
        $current_version = $plugin_data['Version'];
        
        // Only check if we have a valid license
        if (!$this->is_license_valid()) {
            return $transient;
        }
        
        // Check for cached update info (valid for 12 hours)
        $cache_key = 'sky_seo_update_info_' . md5($current_version);
        $update_info = get_transient($cache_key);
        
        if ($update_info === false) {
            // Get update info from server
            $license_data = $this->get_license_data();

            // Use new API handler with standard timeout and retry
            $response = Sky_SEO_API_Request_Handler::request(
                self::UPDATE_SERVER_URL,
                [
                    'body' => [
                        'license' => $license_data['key'],
                        'domain' => $this->get_current_domain(),
                        'version' => $current_version,
                        'slug' => 'sky-seo-boost'
                    ]
                ],
                'standard',  // Use standard timeout (5 seconds)
                true         // Enable retry logic
            );

            if (!is_wp_error($response)) {
                $body = Sky_SEO_API_Request_Handler::get_response_body($response);
                if ($body !== null) {
                    $update_info = json_decode($body);

                    if ($update_info && isset($update_info->version)) {
                        // Cache for 12 hours
                        $cache_duration = Sky_SEO_API_Config::get_cache_duration('update_info');
                        set_transient($cache_key, $update_info, $cache_duration);
                    }
                }
            }
        }
        
        // Add update to transient if available
        if ($update_info && isset($update_info->version) && version_compare($current_version, $update_info->version, '<')) {
            $update = new stdClass();
            $update->id = 'sky-seo-boost';
            $update->slug = 'sky-seo-boost';
            $update->plugin = $plugin_basename;
            $update->new_version = $update_info->version;
            $update->url = $update_info->info_url ?? '';
            $update->package = $update_info->download_url ?? '';
            $update->tested = $update_info->tested ?? '';
            $update->requires = $update_info->requires ?? '';
            $update->requires_php = $update_info->requires_php ?? '';
            
            $transient->response[$plugin_basename] = $update;
        }
        
        return $transient;
    }
    
    /**
     * Provide plugin information for updates
     */
    public function plugin_info($result, $action, $args) {
        if ($action !== 'plugin_information') {
            return $result;
        }
        
        if (!isset($args->slug) || $args->slug !== 'sky-seo-boost') {
            return $result;
        }
        
        $plugin_file = $this->get_plugin_file();
        if (!$plugin_file) {
            return $result;
        }
        
        $plugin_data = get_plugin_data($plugin_file);
        
        // Get cached update info
        $cache_key = 'sky_seo_update_info_' . md5($plugin_data['Version']);
        $update_info = get_transient($cache_key);
        
        if (!$update_info) {
            return $result;
        }
        
        return (object) [
            'name' => $plugin_data['Name'],
            'slug' => 'sky-seo-boost',
            'version' => $update_info->version ?? '',
            'author' => $plugin_data['Author'],
            'author_profile' => $plugin_data['AuthorURI'],
            'requires' => $update_info->requires ?? '',
            'tested' => $update_info->tested ?? '',
            'requires_php' => $update_info->requires_php ?? '',
            'sections' => [
                'description' => $plugin_data['Description'],
                'changelog' => $update_info->changelog ?? 'No changelog available.'
            ],
            'download_link' => $update_info->download_url ?? ''
        ];
    }
    
    /**
     * Get the plugin file path
     */
    private function get_plugin_file() {
        if (defined('SKY_SEO_BOOST_FILE')) {
            return SKY_SEO_BOOST_FILE;
        }
        
        $possible_files = [
            WP_PLUGIN_DIR . '/sky-seo-boost/sky-seo-boost.php',
            WP_PLUGIN_DIR . '/sky-seo-boost_beta_v2/sky-seo-boost.php',
            WP_PLUGIN_DIR . '/sky-seo-boost-pro/sky-seo-boost.php'
        ];
        
        foreach ($possible_files as $file) {
            if (file_exists($file)) {
                return $file;
            }
        }
        
        $plugins = get_plugins();
        foreach ($plugins as $plugin_file => $plugin_data) {
            if (strpos($plugin_file, 'sky-seo-boost.php') !== false) {
                return WP_PLUGIN_DIR . '/' . $plugin_file;
            }
        }
        
        return false;
    }
    
    /**
     * Initialize license management functionality
     */
    private function init_license_management() {
        // Add license tab to settings
        add_action('sky_seo_settings_tabs', [$this, 'add_license_tab']);
        add_action('sky_seo_settings_content', [$this, 'render_license_content']);
        
        // AJAX handlers
        add_action('wp_ajax_sky_seo_activate_license', [$this, 'ajax_activate_license']);
        add_action('wp_ajax_sky_seo_deactivate_license', [$this, 'ajax_deactivate_license']);
        add_action('wp_ajax_sky_seo_manual_check', [$this, 'ajax_manual_check']);
    }
    
    /**
     * Schedule daily license check
     */
    public function schedule_daily_check() {
        if (!wp_next_scheduled('sky_seo_daily_license_check')) {
            // Schedule for 3 AM local time
            $timestamp = strtotime('tomorrow 3:00 am');
            wp_schedule_event($timestamp, 'daily', 'sky_seo_daily_license_check');
        }
    }
    
    /**
     * Perform scheduled daily check
     */
    public function perform_scheduled_check() {
        // This is the ONLY automatic check that happens
        $license_data = $this->get_license_data();
        
        if (!empty($license_data['key'])) {
            $this->perform_license_verification();
            
            // Clear update cache to check for updates
            $plugin_file = $this->get_plugin_file();
            if ($plugin_file) {
                $plugin_data = get_plugin_data($plugin_file);
                $cache_key = 'sky_seo_update_info_' . md5($plugin_data['Version']);
                delete_transient($cache_key);
            }
        }
    }
    
    /**
     * Check if license is valid
     */
    public function is_license_valid() {
        if ($this->is_valid !== null) {
            return $this->is_valid;
        }
        
        // Check cached status
        $cached_status = get_option(self::LICENSE_STATUS_CACHE, null);
        if ($cached_status !== null) {
            $this->is_valid = $cached_status;
            return $this->is_valid;
        }
        
        // No cached status - need to check
        $this->check_license_if_needed();
        return $this->is_valid ?? false;
    }
    
    /**
     * Disable plugin features when unlicensed
     */
    public function disable_plugin_features() {
        // Remove custom post types
        add_action('init', function() {
            global $wp_post_types;
            $custom_types = ['sky_areas', 'sky_trending', 'sky_sectors'];
            foreach ($custom_types as $type) {
                if (isset($wp_post_types[$type])) {
                    unset($wp_post_types[$type]);
                }
            }
        }, 999);
        
        // Block frontend access
        add_action('template_redirect', function() {
            if (is_singular(['sky_areas', 'sky_trending', 'sky_sectors']) || 
                is_post_type_archive(['sky_areas', 'sky_trending', 'sky_sectors'])) {
                wp_die(
                    __('This content is not available. The Sky SEO Boost plugin requires a valid license.', 'sky-seo-boost'),
                    __('License Required', 'sky-seo-boost'),
                    ['response' => 403]
                );
            }
        });
        
        // Restrict admin menu
        add_action('admin_menu', function() {
            global $submenu;
            if (isset($submenu['sky-seo-boost'])) {
                foreach ($submenu['sky-seo-boost'] as $key => $item) {
                    if ($item[2] !== 'sky-seo-settings') {
                        unset($submenu['sky-seo-boost'][$key]);
                    }
                }
            }
        }, 999);
        
        // Redirect from plugin pages to license page
        $current_page = isset($_GET['page']) ? $_GET['page'] : '';
        $current_tab = isset($_GET['tab']) ? $_GET['tab'] : '';
        
        if ($current_page !== 'sky-seo-settings' || $current_tab !== 'license') {
            if (strpos($current_page, 'sky-seo') !== false) {
                wp_redirect(admin_url('admin.php?page=sky-seo-settings&tab=license'));
                exit;
            }
        }
    }
    
    /**
     * Show license required notice
     */
    public function show_license_required_notice() {
        $screen = get_current_screen();

        // Show on dashboard and plugin-related pages
        if ($screen && ($screen->id === 'dashboard' || strpos($screen->id, 'sky-seo') !== false || strpos($screen->id, 'plugins') !== false)) {
            // Check for server error
            if (get_transient('sky_seo_server_error')) {
                ?>
                <div class="notice notice-warning">
                    <p>
                        <strong><?php _e('Sky SEO Boost - License Server Issue:', 'sky-seo-boost'); ?></strong>
                        <?php _e('Unable to verify license. The plugin is running in grace period mode. If this persists for more than 3 days, the plugin will be disabled.', 'sky-seo-boost'); ?>
                    </p>
                </div>
                <?php
            } else {
                ?>
                <div class="notice notice-error">
                    <p>
                        <strong><?php _e('Sky SEO Boost - License Required:', 'sky-seo-boost'); ?></strong>
                        <?php _e('This plugin requires a valid license to function. All features have been disabled.', 'sky-seo-boost'); ?>
                        <a href="<?php echo admin_url('admin.php?page=sky-seo-settings&tab=license'); ?>" class="button button-primary" style="margin-left: 10px;">
                            <?php _e('Activate License', 'sky-seo-boost'); ?>
                        </a>
                    </p>
                </div>
                <?php
            }
        }
    }

    /**
     * Show OpenSSL warning notice
     */
    public function show_openssl_warning() {
        $screen = get_current_screen();

        // Only show on Sky SEO Boost settings pages
        if ($screen && strpos($screen->id, 'sky-seo') !== false) {
            ?>
            <div class="notice notice-warning">
                <p>
                    <strong><?php _e('Sky SEO Boost - Security Warning:', 'sky-seo-boost'); ?></strong>
                    <?php _e('The OpenSSL PHP extension is not available. License response signatures cannot be verified, which may pose a security risk.', 'sky-seo-boost'); ?>
                    <?php _e('Please contact your hosting provider to enable the OpenSSL PHP extension for enhanced security.', 'sky-seo-boost'); ?>
                </p>
            </div>
            <?php
        }
    }
    
    /**
     * Add license tab to settings
     */
    public function add_license_tab($active_tab) {
        ?>
        <a href="?page=sky-seo-settings&tab=license" 
           class="nav-tab <?php echo $active_tab === 'license' ? 'nav-tab-active' : ''; ?>">
            <?php _e('License', 'sky-seo-boost'); ?>
            <?php if (!$this->is_license_valid()) : ?>
                <span style="color: #d63638; margin-left: 5px;">●</span>
            <?php endif; ?>
        </a>
        <?php
    }
    
    /**
     * Render license settings content
     */
    public function render_license_content($active_tab) {
        if ($active_tab !== 'license') {
            return;
        }
        
        $license_data = $this->get_license_data();
        $license_status = $this->get_license_status();
        ?>
        <div class="sky-seo-license-settings">
            <div class="sky-seo-license-card">
                <h3><?php _e('Plugin License', 'sky-seo-boost'); ?></h3>
                
                <div class="sky-seo-license-status-box">
                    <div class="status-indicator <?php echo esc_attr($license_status['status']); ?>">
                        <?php if ($license_status['status'] === 'active') : ?>
                            <span class="dashicons dashicons-yes-alt"></span>
                            <?php _e('License Active', 'sky-seo-boost'); ?>
                        <?php elseif ($license_status['status'] === 'inactive') : ?>
                            <span class="dashicons dashicons-warning"></span>
                            <?php _e('License Inactive', 'sky-seo-boost'); ?>
                        <?php else : ?>
                            <span class="dashicons dashicons-no"></span>
                            <?php _e('No License', 'sky-seo-boost'); ?>
                        <?php endif; ?>
                    </div>
                    
                    <?php if (!empty($license_status['message'])) : ?>
                        <p class="status-message"><?php echo esc_html($license_status['message']); ?></p>
                    <?php endif; ?>
                </div>
                
                <form id="sky-seo-license-form" class="sky-seo-license-form">
                    <table class="form-table">
                        <tr>
                            <th scope="row">
                                <label for="license_key"><?php _e('License Key', 'sky-seo-boost'); ?></label>
                            </th>
                            <td>
                                <input type="text" 
                                       id="license_key" 
                                       name="license_key" 
                                       value="<?php echo esc_attr($license_data['key'] ?? ''); ?>" 
                                       class="regular-text" 
                                       placeholder="<?php esc_attr_e('Enter your license key', 'sky-seo-boost'); ?>"
                                       <?php echo ($license_status['status'] === 'active') ? 'readonly' : ''; ?> />
                                
                                <?php if ($license_status['status'] === 'active') : ?>
                                    <button type="button" 
                                            id="sky-seo-deactivate-license" 
                                            class="button button-secondary">
                                        <?php _e('Deactivate License', 'sky-seo-boost'); ?>
                                    </button>
                                    <button type="button" 
                                            id="sky-seo-manual-check" 
                                            class="button button-secondary"
                                            title="<?php esc_attr_e('Manual check is limited to once per hour', 'sky-seo-boost'); ?>">
                                        <?php _e('Verify Now', 'sky-seo-boost'); ?>
                                    </button>
                                <?php else : ?>
                                    <button type="button" 
                                            id="sky-seo-activate-license" 
                                            class="button button-primary">
                                        <?php _e('Activate License', 'sky-seo-boost'); ?>
                                    </button>
                                <?php endif; ?>
                            </td>
                        </tr>
                        
                        <?php if ($license_status['status'] === 'active') : ?>
                        <tr>
                            <th scope="row"><?php _e('Licensed Domain', 'sky-seo-boost'); ?></th>
                            <td>
                                <code><?php echo esc_html($license_data['domain'] ?? $this->get_current_domain()); ?></code>
                            </td>
                        </tr>
                        
                        <tr>
                            <th scope="row"><?php _e('Last Verified', 'sky-seo-boost'); ?></th>
                            <td>
                                <?php 
                                $last_check = get_option(self::LAST_CHECK_OPTION, 0);
                                if ($last_check > 0) {
                                    echo esc_html(human_time_diff($last_check, current_time('timestamp'))) . ' ' . __('ago', 'sky-seo-boost');
                                    
                                    // Show next check time
                                    $next_check = $last_check + self::CHECK_FREQUENCY;
                                    if ($next_check > time()) {
                                        echo '<br><small>' . esc_html__('Next automatic check in', 'sky-seo-boost') . ' ' .
                                             esc_html(human_time_diff(time(), $next_check)) . '</small>';
                                    }
                                } else {
                                    _e('Never', 'sky-seo-boost');
                                }
                                ?>
                            </td>
                        </tr>
                        
                        <tr>
                            <th scope="row"><?php _e('Updates', 'sky-seo-boost'); ?></th>
                            <td>
                                <?php
                                $plugin_file = $this->get_plugin_file();
                                if ($plugin_file) {
                                    $plugin_data = get_plugin_data($plugin_file);
                                    echo sprintf(esc_html__('Current version: %s', 'sky-seo-boost'), esc_html($plugin_data['Version']));
                                    echo ' | <a href="' . esc_url(admin_url('plugins.php')) . '">' . esc_html__('Check for updates', 'sky-seo-boost') . '</a>';
                                }
                                ?>
                            </td>
                        </tr>
                        <?php endif; ?>
                    </table>
                </form>
                
                <div class="sky-seo-license-info">
                    <h4><?php _e('License Information', 'sky-seo-boost'); ?></h4>
                    <ul>
                        <li><?php _e('License verification occurs automatically once every 24 hours.', 'sky-seo-boost'); ?></li>
                        <li><?php _e('If the license server is temporarily unavailable, the plugin will continue working for up to 3 days.', 'sky-seo-boost'); ?></li>
                        <li><?php _e('Each license is valid for one domain only.', 'sky-seo-boost'); ?></li>
                        <li><?php _e('Automatic updates are available only for licensed users.', 'sky-seo-boost'); ?></li>
                        <li><?php printf(__('Need a license? <a href="%s" target="_blank">Purchase one here</a>.', 'sky-seo-boost'), 'https://skywebdesign.co.uk/sky-seo-boost'); ?></li>
                    </ul>
                </div>
            </div>
        </div>
        
        <style>
        .sky-seo-license-settings {
            max-width: 800px;
        }
        
        .sky-seo-license-card {
            background: #fff;
            border: 1px solid #ccd0d4;
            box-shadow: 0 1px 1px rgba(0,0,0,.04);
            padding: 20px;
            margin-top: 20px;
        }
        
        .sky-seo-license-card h3 {
            margin-top: 0;
            padding-bottom: 10px;
            border-bottom: 1px solid #eee;
        }
        
        .sky-seo-license-status-box {
            background: #f8f9fa;
            border: 1px solid #e1e4e8;
            border-radius: 4px;
            padding: 15px;
            margin: 20px 0;
        }
        
        .status-indicator {
            font-size: 16px;
            font-weight: 600;
            display: flex;
            align-items: center;
            gap: 8px;
        }
        
        .status-indicator.active {
            color: #008a20;
        }
        
        .status-indicator.inactive {
            color: #d63638;
        }
        
        .status-indicator.none {
            color: #50575e;
        }
        
        .status-indicator .dashicons {
            font-size: 24px;
            width: 24px;
            height: 24px;
        }
        
        .status-message {
            margin: 10px 0 0;
            color: #50575e;
        }
        
        .sky-seo-license-form {
            margin: 20px 0;
        }
        
        .sky-seo-license-form .button {
            margin-left: 10px;
        }
        
        .sky-seo-license-info {
            background: #f0f8ff;
            border: 1px solid #b3d9ff;
            border-radius: 4px;
            padding: 15px;
            margin-top: 20px;
        }
        
        .sky-seo-license-info h4 {
            margin-top: 0;
            margin-bottom: 10px;
            color: #0066cc;
        }
        
        .sky-seo-license-info ul {
            margin: 0;
            padding-left: 20px;
            color: #333;
        }
        
        .sky-seo-license-info li {
            margin-bottom: 5px;
        }
        
        .sky-seo-license-notice {
            display: none;
            margin: 10px 0;
            padding: 10px;
            border-radius: 4px;
        }
        
        .sky-seo-license-notice.success {
            background: #d4edda;
            border: 1px solid #c3e6cb;
            color: #155724;
        }
        
        .sky-seo-license-notice.error {
            background: #f8d7da;
            border: 1px solid #f5c6cb;
            color: #721c24;
        }
        
        .sky-seo-license-notice.info {
            background: #d1ecf1;
            border: 1px solid #bee5eb;
            color: #0c5460;
        }
        
        code {
            background: #e9ecef;
            padding: 2px 6px;
            border-radius: 3px;
            font-family: Consolas, Monaco, monospace;
        }
        </style>
        
        <script>
        jQuery(document).ready(function($) {
            var $form = $('#sky-seo-license-form');
            var $licenseKey = $('#license_key');
            var $activateBtn = $('#sky-seo-activate-license');
            var $deactivateBtn = $('#sky-seo-deactivate-license');
            var $manualCheckBtn = $('#sky-seo-manual-check');
            
            // Show notice
            function showNotice(message, type) {
                var $existingNotice = $('.sky-seo-license-notice');
                if ($existingNotice.length) {
                    $existingNotice.remove();
                }
                
                var $notice = $('<div class="sky-seo-license-notice ' + type + '">' + message + '</div>');
                $form.before($notice);
                $notice.fadeIn();
                
                if (type !== 'error') {
                    setTimeout(function() {
                        $notice.fadeOut(function() {
                            $(this).remove();
                        });
                    }, 5000);
                }
            }
            
            // Activate license
            $activateBtn.on('click', function() {
                var licenseKey = $licenseKey.val().trim();
                
                if (!licenseKey) {
                    showNotice('<?php _e('Please enter a license key.', 'sky-seo-boost'); ?>', 'error');
                    return;
                }
                
                $activateBtn.prop('disabled', true).text('<?php _e('Activating...', 'sky-seo-boost'); ?>');
                
                $.ajax({
                    url: ajaxurl,
                    type: 'POST',
                    data: {
                        action: 'sky_seo_activate_license',
                        license_key: licenseKey,
                        nonce: '<?php echo wp_create_nonce('sky_seo_license_nonce'); ?>'
                    },
                    success: function(response) {
                        if (response.success) {
                            showNotice(response.data.message, 'success');
                            setTimeout(function() {
                                location.reload();
                            }, 1500);
                        } else {
                            showNotice(response.data.message, 'error');
                            $activateBtn.prop('disabled', false).text('<?php _e('Activate License', 'sky-seo-boost'); ?>');
                        }
                    },
                    error: function() {
                        showNotice('<?php _e('An error occurred. Please try again.', 'sky-seo-boost'); ?>', 'error');
                        $activateBtn.prop('disabled', false).text('<?php _e('Activate License', 'sky-seo-boost'); ?>');
                    }
                });
            });
            
            // Deactivate license
            $deactivateBtn.on('click', function() {
                if (!confirm('<?php _e('Are you sure you want to deactivate this license? The plugin will stop working immediately.', 'sky-seo-boost'); ?>')) {
                    return;
                }
                
                $deactivateBtn.prop('disabled', true).text('<?php _e('Deactivating...', 'sky-seo-boost'); ?>');
                
                $.ajax({
                    url: ajaxurl,
                    type: 'POST',
                    data: {
                        action: 'sky_seo_deactivate_license',
                        nonce: '<?php echo wp_create_nonce('sky_seo_license_nonce'); ?>'
                    },
                    success: function(response) {
                        if (response.success) {
                            showNotice(response.data.message, 'success');
                            setTimeout(function() {
                                location.reload();
                            }, 1500);
                        } else {
                            showNotice(response.data.message, 'error');
                            $deactivateBtn.prop('disabled', false).text('<?php _e('Deactivate License', 'sky-seo-boost'); ?>');
                        }
                    },
                    error: function() {
                        showNotice('<?php _e('An error occurred. Please try again.', 'sky-seo-boost'); ?>', 'error');
                        $deactivateBtn.prop('disabled', false).text('<?php _e('Deactivate License', 'sky-seo-boost'); ?>');
                    }
                });
            });
            
            // Manual check (rate limited)
            $manualCheckBtn.on('click', function() {
                $manualCheckBtn.prop('disabled', true).text('<?php _e('Checking...', 'sky-seo-boost'); ?>');
                
                $.ajax({
                    url: ajaxurl,
                    type: 'POST',
                    data: {
                        action: 'sky_seo_manual_check',
                        nonce: '<?php echo wp_create_nonce('sky_seo_license_nonce'); ?>'
                    },
                    success: function(response) {
                        if (response.success) {
                            showNotice(response.data.message, 'success');
                            if (response.data.reload) {
                                setTimeout(function() {
                                    location.reload();
                                }, 1500);
                            }
                        } else {
                            showNotice(response.data.message, 'error');
                        }
                        $manualCheckBtn.prop('disabled', false).text('<?php _e('Verify Now', 'sky-seo-boost'); ?>');
                    },
                    error: function() {
                        showNotice('<?php _e('An error occurred. Please try again.', 'sky-seo-boost'); ?>', 'error');
                        $manualCheckBtn.prop('disabled', false).text('<?php _e('Verify Now', 'sky-seo-boost'); ?>');
                    }
                });
            });
            
            // Enter key activates license
            $licenseKey.on('keypress', function(e) {
                if (e.which === 13 && $activateBtn.is(':visible')) {
                    e.preventDefault();
                    $activateBtn.click();
                }
            });
        });
        </script>
        <?php
    }
    
    /**
     * AJAX handler for license activation
     */
    public function ajax_activate_license() {
        check_ajax_referer('sky_seo_license_nonce', 'nonce');

        if (!current_user_can('manage_options')) {
            wp_send_json_error(['message' => __('Unauthorized access.', 'sky-seo-boost')]);
        }

        $license_key = isset($_POST['license_key']) ? sanitize_text_field($_POST['license_key']) : '';

        if (empty($license_key)) {
            wp_send_json_error(['message' => __('Please provide a license key.', 'sky-seo-boost')]);
        }

        // Validate license key format (basic validation)
        if (strlen($license_key) < 10 || strlen($license_key) > 100) {
            wp_send_json_error(['message' => __('Invalid license key format.', 'sky-seo-boost')]);
        }

        $result = $this->activate_license($license_key);

        if ($result['success']) {
            wp_send_json_success(['message' => $result['message']]);
        } else {
            wp_send_json_error(['message' => $result['message']]);
        }
    }
    
    /**
     * AJAX handler for license deactivation
     */
    public function ajax_deactivate_license() {
        check_ajax_referer('sky_seo_license_nonce', 'nonce');
        
        if (!current_user_can('manage_options')) {
            wp_send_json_error(['message' => __('Unauthorized access.', 'sky-seo-boost')]);
        }
        
        $result = $this->deactivate_license();
        
        if ($result['success']) {
            wp_send_json_success(['message' => $result['message']]);
        } else {
            wp_send_json_error(['message' => $result['message']]);
        }
    }
    
    /**
     * AJAX handler for manual check (rate limited)
     */
    public function ajax_manual_check() {
        check_ajax_referer('sky_seo_license_nonce', 'nonce');
        
        if (!current_user_can('manage_options')) {
            wp_send_json_error(['message' => __('Unauthorized access.', 'sky-seo-boost')]);
        }
        
        // Check rate limit (once per hour for manual checks)
        $last_manual_check = get_transient('sky_seo_last_manual_check');
        if ($last_manual_check !== false) {
            wp_send_json_error([
                'message' => __('Manual verification is limited to once per hour. Please wait before trying again.', 'sky-seo-boost')
            ]);
        }
        
        // Set rate limit
        set_transient('sky_seo_last_manual_check', time(), HOUR_IN_SECONDS);
        
        // Force a fresh check
        delete_option(self::LICENSE_STATUS_CACHE);
        delete_option(self::LAST_CHECK_OPTION); // This allows immediate check
        
        $this->perform_license_verification();
        
        if ($this->is_license_valid()) {
            wp_send_json_success([
                'message' => __('License verified successfully. Status: Active', 'sky-seo-boost'),
                'reload' => false
            ]);
        } else {
            wp_send_json_error([
                'message' => __('License verification failed. Please check your license key.', 'sky-seo-boost'),
                'reload' => true
            ]);
        }
    }
    
    /**
     * Activate license
     */
    private function activate_license($license_key) {
        $domain = $this->get_current_domain();

        // Verify with license server using new API handler
        $response = Sky_SEO_API_Request_Handler::request(
            self::LICENSE_SERVER_URL,
            [
                'body' => [
                    'license' => $license_key,
                    'domain' => $domain
                ]
            ],
            'critical',  // Use critical timeout (10 seconds)
            true         // Enable retry logic
        );

        if (is_wp_error($response)) {
            return [
                'success' => false,
                'message' => __('Unable to connect to license server. Please try again later.', 'sky-seo-boost')
            ];
        }

        // Parse JSON response
        $data = Sky_SEO_API_Request_Handler::get_json_response($response);

        if ($data === null) {
            return [
                'success' => false,
                'message' => __('Invalid response from license server. Please try again later.', 'sky-seo-boost')
            ];
        }

        // OPTIONAL: Verify signature if present (enhanced security)
        if (isset($data['signature'])) {
            // Signature present - verify it
            if (!Sky_SEO_License_Signature_Validator::is_available()) {
                // OpenSSL not available - log warning but continue
                if (defined('WP_DEBUG') && WP_DEBUG) {
                    error_log('Sky SEO: OpenSSL not available - cannot verify license activation signature');
                }
            } else {
                // OpenSSL available - verify signature
                if (!Sky_SEO_License_Signature_Validator::verify_response($data)) {
                    error_log('Sky SEO: License activation response signature verification failed');
                    return [
                        'success' => false,
                        'message' => __('License server response verification failed. Please contact support.', 'sky-seo-boost')
                    ];
                }

                // Signature verified successfully
                if (defined('WP_DEBUG') && WP_DEBUG) {
                    error_log('Sky SEO: License activation signature verified successfully');
                }
            }
        } else {
            // Signature not present - log warning but continue
            // TODO: Make signature mandatory once license server is updated
            if (defined('WP_DEBUG') && WP_DEBUG) {
                error_log('Sky SEO: License activation response missing signature - enhanced security disabled.');
            }
        }

        // Check authentication status
        if (!empty($data['authenticated']) && $data['authenticated'] === true) {
            // Save license data
            $license_data = [
                'key' => $license_key,
                'domain' => $domain,
                'activated_at' => time(),
                'last_check' => time()
            ];

            update_option(self::LICENSE_OPTION_KEY, $license_data);
            update_option(self::LICENSE_STATUS_CACHE, true);
            update_option(self::LAST_CHECK_OPTION, time());

            // Clear any error transients
            delete_transient('sky_seo_server_error');
            delete_transient('sky_seo_license_invalid');

            // FIXED: Flush rewrite rules to ensure sitemaps work immediately after license activation
            flush_rewrite_rules();

            return [
                'success' => true,
                'message' => __('License activated successfully! The page will reload to enable all features.', 'sky-seo-boost')
            ];
        } else {
            $error_message = isset($data['error']) ? $data['error'] : __('Invalid license key or domain mismatch.', 'sky-seo-boost');
            return [
                'success' => false,
                'message' => $error_message
            ];
        }
    }
    
    /**
     * Deactivate license
     */
    private function deactivate_license() {
        // Clear all license data
        delete_option(self::LICENSE_OPTION_KEY);
        delete_option(self::LICENSE_STATUS_CACHE);
        delete_option(self::LAST_CHECK_OPTION);
        
        // Clear transients
        delete_transient('sky_seo_server_error');
        delete_transient('sky_seo_license_invalid');
        delete_transient('sky_seo_last_manual_check');
        
        // Clear update cache
        $plugin_file = $this->get_plugin_file();
        if ($plugin_file) {
            $plugin_data = get_plugin_data($plugin_file);
            $cache_key = 'sky_seo_update_info_' . md5($plugin_data['Version']);
            delete_transient($cache_key);
        }
        
        return [
            'success' => true,
            'message' => __('License deactivated successfully. The plugin will be disabled.', 'sky-seo-boost')
        ];
    }
    
    /**
     * Get license data
     */
    private function get_license_data() {
        return get_option(self::LICENSE_OPTION_KEY, []);
    }
    
    /**
     * Get license status for display
     */
    private function get_license_status() {
        $license_data = $this->get_license_data();
        
        if (empty($license_data['key'])) {
            return [
                'status' => 'none',
                'message' => __('No license key entered. All plugin features are disabled.', 'sky-seo-boost')
            ];
        }
        
        if ($this->is_license_valid()) {
            return [
                'status' => 'active',
                'message' => __('Your license is active and valid.', 'sky-seo-boost')
            ];
        } else {
            return [
                'status' => 'inactive',
                'message' => __('Your license is invalid or expired. All plugin features are disabled.', 'sky-seo-boost')
            ];
        }
    }
    
    /**
     * Get current domain
     */
    private function get_current_domain() {
        $domain = wp_parse_url(home_url(), PHP_URL_HOST);
        
        // Remove www. prefix if present
        if (strpos($domain, 'www.') === 0) {
            $domain = substr($domain, 4);
        }
        
        return $domain;
    }
}

// Initialize the license manager
Sky_SEO_License_Manager::get_instance();

// Add global function to check license status
if (!function_exists('sky_seo_is_licensed')) {
    function sky_seo_is_licensed() {
        $license_manager = Sky_SEO_License_Manager::get_instance();
        return $license_manager->is_license_valid();
    }
}