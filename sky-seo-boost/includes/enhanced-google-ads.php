<?php
/**
 * Sky SEO Boost - Enhanced Google Ads Tracking
 * 
 * Complete self-contained system for tracking Google Ads conversions
 * Integrated with Sky Insights UTM system for seamless tracking
 * 
 * @package Sky_SEO_Boost
 * @version 3.4.1
 * @since 3.1.0
 */

// Prevent direct access
if (!defined('ABSPATH')) {
    exit;
}

/**
 * Enhanced Google Ads Tracking Class
 */
class Sky_SEO_Enhanced_Google_Ads {
    
    /**
     * Instance
     */
    private static $instance = null;
    
    /**
     * Table name for storing Google Ads conversions
     */
    private $table_name;
    
    /**
     * Database version for tracking schema changes
     */
    private $db_version = '1.5';
    
    /**
     * Option name for storing database version
     */
    private $db_version_option = 'sky_seo_google_ads_db_version';
    
    /**
     * Flag to check if table exists
     */
    private $table_checked = false;
    
    /**
     * Get instance
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
        global $wpdb;
        $this->table_name = $wpdb->prefix . 'sky_seo_google_ads_conversions';
        
        // Initialize hooks
        $this->init_hooks();
        
        // Check and create table if needed
        $this->maybe_create_table();
    }
    
    /**
     * Initialize hooks
     */
    private function init_hooks() {
        // Check table on admin init and plugin activation
        add_action('admin_init', [$this, 'maybe_create_table']);
        add_action('plugins_loaded', [$this, 'maybe_create_table']);
        
        // Track visitors from Google Ads
        add_action('init', [$this, 'track_google_ads_visitor'], 5);
        
        // INTEGRATION: Sync with Sky Insights UTM when orders are tracked there
        add_action('woocommerce_thankyou', [$this, 'sync_from_sky_insights'], 25, 1);
        add_action('woocommerce_order_status_processing', [$this, 'sync_from_sky_insights'], 25, 1);
        add_action('woocommerce_order_status_completed', [$this, 'sync_from_sky_insights'], 25, 1);
        
        // Track WooCommerce conversions (original method as backup) - Adjusted priority
        add_action('woocommerce_thankyou', [$this, 'track_woocommerce_conversion'], 15, 1);
        
        // Track form submissions
        add_action('wp_ajax_sky_seo_track_form_submission', [$this, 'ajax_track_form_submission']);
        add_action('wp_ajax_nopriv_sky_seo_track_form_submission', [$this, 'ajax_track_form_submission']);
        
        // Enqueue scripts for form tracking
        add_action('wp_enqueue_scripts', [$this, 'enqueue_form_tracking_scripts'], 5);
        
        // Add form tracking script
        add_action('wp_footer', [$this, 'add_form_tracking_script'], 100);
        
        // Analytics dashboard hooks
        add_action('sky_seo_analytics_sections', [$this, 'add_analytics_section']);
        add_action('wp_ajax_sky_seo_get_google_ads_stats', [$this, 'ajax_get_google_ads_stats']);
        add_action('wp_ajax_sky_seo_get_form_submissions', [$this, 'ajax_get_form_submissions']);
        
        // Database creation on activation
        if (defined('SKY_SEO_BOOST_FILE')) {
            register_activation_hook(SKY_SEO_BOOST_FILE, [$this, 'create_database_table']);
        }
        
        // Clean old data daily
        add_action('sky_seo_daily_cleanup', [$this, 'cleanup_old_data']);
        if (!wp_next_scheduled('sky_seo_daily_cleanup')) {
            wp_schedule_event(time(), 'daily', 'sky_seo_daily_cleanup');
        }
        
        // INTEGRATION: Store session in WooCommerce session for persistence
        add_action('init', [$this, 'store_session_in_wc'], 20);
        
        // INTEGRATION: Save session to order during checkout
        add_action('woocommerce_checkout_create_order', [$this, 'save_session_to_order'], 10, 2);
    }
    
    /**
     * Check if table needs to be created
     */
    public function maybe_create_table() {
        // Only check once per request
        if ($this->table_checked) {
            return;
        }
        
        $this->table_checked = true;
        
        // Check if we need to create or update the table
        $current_db_version = get_option($this->db_version_option, '0');
        
        if (version_compare($current_db_version, $this->db_version, '<')) {
            $this->create_database_table();
        }
    }
    
    /**
     * Create or update database table
     */
    public function create_database_table() {
        global $wpdb;
        
        $charset_collate = $wpdb->get_charset_collate();
        
        // Use proper table name escaping
        $table_name = esc_sql($this->table_name);
        
        $sql = "CREATE TABLE IF NOT EXISTS `{$table_name}` (
            id BIGINT(20) UNSIGNED NOT NULL AUTO_INCREMENT,
            session_id VARCHAR(64) NOT NULL,
            gclid VARCHAR(255) DEFAULT NULL,
            utm_source VARCHAR(100) DEFAULT NULL,
            utm_medium VARCHAR(100) DEFAULT NULL,
            utm_campaign VARCHAR(255) DEFAULT NULL,
            landing_page VARCHAR(500) DEFAULT NULL,
            referrer VARCHAR(500) DEFAULT NULL,
            conversion_type VARCHAR(50) DEFAULT NULL,
            conversion_value DECIMAL(10,2) DEFAULT 0,
            order_id BIGINT(20) UNSIGNED DEFAULT NULL,
            form_id VARCHAR(100) DEFAULT NULL,
            form_name VARCHAR(255) DEFAULT NULL,
            form_data LONGTEXT DEFAULT NULL,
            user_agent VARCHAR(500) DEFAULT NULL,
            ip_address VARCHAR(45) DEFAULT NULL,
            created_at DATETIME NOT NULL,
            converted_at DATETIME DEFAULT NULL,
            PRIMARY KEY (id),
            INDEX session_id (session_id),
            INDEX gclid (gclid),
            INDEX conversion_type (conversion_type),
            INDEX created_at (created_at),
            INDEX converted_at (converted_at),
            INDEX order_id (order_id)
        ) $charset_collate;";
        
        require_once(ABSPATH . 'wp-admin/includes/upgrade.php');
        dbDelta($sql);
        
        // Add columns if they don't exist (for upgrades)
        $this->add_missing_columns();
        
        // Update the database version
        update_option($this->db_version_option, $this->db_version);
    }
    
    /**
     * Add missing columns for existing installations
     */
    private function add_missing_columns() {
        global $wpdb;
        
        $table_name = esc_sql($this->table_name);
        
        // Get existing columns
        $existing_columns = $wpdb->get_col("SHOW COLUMNS FROM `{$table_name}`");
        
        // Check if form_name column exists
        if (!in_array('form_name', $existing_columns)) {
            $wpdb->query("ALTER TABLE `{$table_name}` ADD COLUMN form_name VARCHAR(255) DEFAULT NULL AFTER form_id");
        }
        
        // Check if form_data column exists
        if (!in_array('form_data', $existing_columns)) {
            $wpdb->query("ALTER TABLE `{$table_name}` ADD COLUMN form_data LONGTEXT DEFAULT NULL AFTER form_name");
        }
    }
    
    /**
     * Ensure table exists before any database operation
     */
    private function ensure_table_exists() {
        global $wpdb;
        
        // Quick check if table exists
        $table_name = esc_sql($this->table_name);
        $table_exists = $wpdb->get_var($wpdb->prepare(
            "SHOW TABLES LIKE %s",
            $table_name
        )) === $this->table_name;
        
        if (!$table_exists) {
            $this->create_database_table();
            return true;
        }
        
        return $table_exists;
    }
    
    /**
     * INTEGRATION: Store session in WooCommerce session
     */
    public function store_session_in_wc() {
        if (!function_exists('WC') || !WC()->session) {
            return;
        }
        
        // If we have a Google Ads session cookie, store it in WC session
        if (!empty($_COOKIE['sky_seo_gads_session'])) {
            WC()->session->set('google_ads_session', sanitize_text_field($_COOKIE['sky_seo_gads_session']));
        }
        
        // If we detect GCLID, ensure session is created and stored
        if (!empty($_GET['gclid'])) {
            $session_id = $this->get_session_id();
            WC()->session->set('google_ads_session', $session_id);
            WC()->session->set('google_ads_gclid', sanitize_text_field($_GET['gclid']));
        }
    }
    
    /**
     * INTEGRATION: Save session to order during checkout
     */
    public function save_session_to_order($order, $data) {
        // Get session from multiple sources
        $session_id = '';
        
        // Priority 1: WooCommerce session
        if (function_exists('WC') && WC()->session) {
            $session_id = WC()->session->get('google_ads_session');
        }
        
        // Priority 2: Cookie
        if (empty($session_id) && !empty($_COOKIE['sky_seo_gads_session'])) {
            $session_id = sanitize_text_field($_COOKIE['sky_seo_gads_session']);
        }
        
        // Save to order if found
        if (!empty($session_id)) {
            $order->update_meta_data('_sky_gads_session', $session_id);
            
            // Also get GCLID from WC session if available
            if (function_exists('WC') && WC()->session) {
                $gclid = WC()->session->get('google_ads_gclid');
                if (!empty($gclid)) {
                    $order->update_meta_data('_gclid', sanitize_text_field($gclid));
                }
            }
        }
    }
    
    /**
     * INTEGRATION: Sync from Sky Insights UTM system
     */
    public function sync_from_sky_insights($order_id) {
        $order = wc_get_order($order_id);
        if (!$order) {
            return;
        }
        
        // Check if this is a Google order via UTM
        $utm_source = $order->get_meta('_utm_source');
        $utm_medium = $order->get_meta('_utm_medium');
        
        // Only process Google orders
        if ($utm_source !== 'google') {
            return;
        }
        
        // Check if already synced
        if ($order->get_meta('_sky_gads_auto_synced') === 'yes') {
            return;
        }
        
        // Ensure table exists
        $this->ensure_table_exists();
        
        global $wpdb;
        
        // Try to find existing session first
        $session_id = $order->get_meta('_sky_gads_session');
        $existing_record = null;
        
        if (!empty($session_id)) {
            // Check if session exists in our table
            $existing_record = $wpdb->get_row($wpdb->prepare(
                "SELECT * FROM {$this->table_name} WHERE session_id = %s LIMIT 1",
                $session_id
            ));
        }
        
        // If no existing session, try to match by time and IP
        if (!$existing_record) {
            $order_date = $order->get_date_created();
            if ($order_date) {
                $time_window_start = $order_date->modify('-2 hours')->format('Y-m-d H:i:s');
                $time_window_end = $order_date->format('Y-m-d H:i:s');
                $customer_ip = $order->get_customer_ip_address();
                
                if ($customer_ip) {
                    $existing_record = $wpdb->get_row($wpdb->prepare(
                        "SELECT * FROM {$this->table_name} 
                        WHERE ip_address = %s 
                        AND created_at BETWEEN %s AND %s 
                        AND converted_at IS NULL 
                        ORDER BY created_at DESC 
                        LIMIT 1",
                        $this->anonymize_ip($customer_ip),
                        $time_window_start,
                        $time_window_end
                    ));
                }
            }
        }
        
        if ($existing_record) {
            // Update existing record with conversion
            $wpdb->update(
                $this->table_name,
                [
                    'conversion_type' => 'woocommerce',
                    'conversion_value' => $order->get_total(),
                    'order_id' => $order_id,
                    'converted_at' => current_time('mysql')
                ],
                ['id' => $existing_record->id],
                ['%s', '%f', '%d', '%s'],
                ['%d']
            );
        } else {
            // Create new record from Sky Insights data
            $session_id = 'sky_insights_' . $order_id . '_' . time();
            
            $wpdb->insert($this->table_name, [
                'session_id' => $session_id,
                'gclid' => $order->get_meta('_gclid') ?: '',
                'utm_source' => 'google',
                'utm_medium' => $utm_medium ?: 'cpc',
                'utm_campaign' => $order->get_meta('_utm_campaign') ?: '',
                'landing_page' => '',
                'referrer' => '',
                'conversion_type' => 'woocommerce',
                'conversion_value' => $order->get_total(),
                'order_id' => $order_id,
                'user_agent' => 'Synced from Sky Insights',
                'ip_address' => $this->anonymize_ip($order->get_customer_ip_address() ?: ''),
                'created_at' => $order->get_date_created() ? $order->get_date_created()->format('Y-m-d H:i:s') : current_time('mysql'),
                'converted_at' => current_time('mysql')
            ], [
                '%s', '%s', '%s', '%s', '%s', '%s', '%s', '%s', '%f', '%d', '%s', '%s', '%s', '%s'
            ]);
            
            // Save session to order
            $order->update_meta_data('_sky_gads_session', $session_id);
        }
        
        // Mark as synced
        $order->update_meta_data('_sky_gads_auto_synced', 'yes');
        $order->save();
    }
    
    /**
     * Track Google Ads visitor
     */
    public function track_google_ads_visitor() {
        // Skip admin requests and bots
        if (is_admin() || $this->is_bot()) {
            return;
        }
        
        // Check if this is a Google Ads visitor
        if (!$this->is_google_ads_traffic()) {
            return;
        }
        
        // Ensure table exists
        $this->ensure_table_exists();
        
        // Get or create session ID
        $session_id = $this->get_session_id();
        
        // Check if we already tracked this session
        if ($this->session_already_tracked($session_id)) {
            return;
        }
        
        // Get tracking data
        $data = [
            'session_id' => $session_id,
            'gclid' => sanitize_text_field($_GET['gclid'] ?? ''),
            'utm_source' => sanitize_text_field($_GET['utm_source'] ?? ''),
            'utm_medium' => sanitize_text_field($_GET['utm_medium'] ?? ''),
            'utm_campaign' => sanitize_text_field($_GET['utm_campaign'] ?? ''),
            'landing_page' => esc_url_raw($_SERVER['REQUEST_URI']),
            'referrer' => isset($_SERVER['HTTP_REFERER']) ? esc_url_raw($_SERVER['HTTP_REFERER']) : '',
            'user_agent' => isset($_SERVER['HTTP_USER_AGENT']) ? sanitize_text_field(substr($_SERVER['HTTP_USER_AGENT'], 0, 500)) : '',
            'ip_address' => $this->get_anonymized_ip(),
            'created_at' => current_time('mysql')
        ];
        
        // Store in database with error handling
        global $wpdb;
        $result = $wpdb->insert($this->table_name, $data, [
            '%s', '%s', '%s', '%s', '%s', '%s', '%s', '%s', '%s', '%s'
        ]);
        
        if ($result !== false) {
            // Set secure cookie to track this visitor
            $this->set_tracking_cookie($session_id);
            
            // INTEGRATION: Store in WooCommerce session too
            if (function_exists('WC') && WC()->session) {
                WC()->session->set('google_ads_session', $session_id);
                if (!empty($_GET['gclid'])) {
                    WC()->session->set('google_ads_gclid', sanitize_text_field($_GET['gclid']));
                }
            }
        }
    }
    
    /**
     * Check if visitor is a bot
     */
    private function is_bot() {
        // Use Sky Insights UTM utility if available
        if (class_exists('SkyInsightsUtils') && method_exists('SkyInsightsUtils', 'is_bot_visit')) {
            return SkyInsightsUtils::is_bot_visit();
        }
        
        // Fallback bot detection
        $user_agent = isset($_SERVER['HTTP_USER_AGENT']) ? strtolower($_SERVER['HTTP_USER_AGENT']) : '';
        $bot_patterns = [
            'googlebot', 'bingbot', 'slurp', 'duckduckbot', 'baiduspider',
            'yandexbot', 'facebookexternalhit', 'twitterbot', 'linkedinbot',
            'whatsapp', 'telegrambot', 'skypeuripreview', 'nuzzel',
            'qwantify', 'pinterestbot', 'ahrefsbot', 'semrushbot',
            'screaming frog', 'mj12bot', 'dotbot', 'applebot',
            'crawler', 'spider', 'bot', 'scraper'
        ];
        
        foreach ($bot_patterns as $pattern) {
            if (strpos($user_agent, $pattern) !== false) {
                return true;
            }
        }
        
        return false;
    }
    
    /**
     * Set tracking cookie with security flags
     */
    private function set_tracking_cookie($session_id) {
        $cookie_options = [
            'expires' => time() + (30 * DAY_IN_SECONDS),
            'path' => '/',
            'domain' => '',
            'secure' => is_ssl(),
            'httponly' => true,
            'samesite' => 'Lax'
        ];
        
        // Use setcookie with options array for PHP 7.3+
        if (version_compare(PHP_VERSION, '7.3.0', '>=')) {
            setcookie('sky_seo_gads_session', $session_id, $cookie_options);
        } else {
            // Fallback for older PHP versions
            setcookie(
                'sky_seo_gads_session', 
                $session_id, 
                $cookie_options['expires'], 
                $cookie_options['path'], 
                $cookie_options['domain'], 
                $cookie_options['secure'], 
                $cookie_options['httponly']
            );
        }
        
        // Set in $_COOKIE for immediate access
        $_COOKIE['sky_seo_gads_session'] = $session_id;
    }
    
    /**
     * Check if current traffic is from Google Ads
     */
    private function is_google_ads_traffic() {
        // Check for gclid parameter
        if (!empty($_GET['gclid'])) {
            return true;
        }
        
        // Check for Google Ads UTM parameters
        if (!empty($_GET['utm_source']) && 
            (stripos($_GET['utm_source'], 'google') !== false || stripos($_GET['utm_source'], 'adwords') !== false) &&
            !empty($_GET['utm_medium']) && 
            (stripos($_GET['utm_medium'], 'cpc') !== false || stripos($_GET['utm_medium'], 'paid') !== false)) {
            return true;
        }
        
        // Check referrer
        $referrer = isset($_SERVER['HTTP_REFERER']) ? $_SERVER['HTTP_REFERER'] : '';
        if (strpos($referrer, 'googleads.g.doubleclick.net') !== false || 
            strpos($referrer, 'www.googleadservices.com') !== false) {
            return true;
        }
        
        return false;
    }
    
    /**
     * Get session ID with improved security
     */
    private function get_session_id() {
        // Check for existing session cookie
        if (!empty($_COOKIE['sky_seo_gads_session'])) {
            return sanitize_text_field($_COOKIE['sky_seo_gads_session']);
        }
        
        // Check WooCommerce session
        if (function_exists('WC') && WC()->session) {
            $wc_session = WC()->session->get('google_ads_session');
            if (!empty($wc_session)) {
                return sanitize_text_field($wc_session);
            }
        }
        
        // Generate new session ID using cryptographically secure method
        return 'sky_gads_' . bin2hex(random_bytes(16));
    }
    
    /**
     * Check if session already tracked
     */
    private function session_already_tracked($session_id) {
        global $wpdb;
        
        // Ensure table exists
        if (!$this->ensure_table_exists()) {
            return false;
        }
        
        $exists = $wpdb->get_var($wpdb->prepare(
            "SELECT 1 FROM {$this->table_name} WHERE session_id = %s LIMIT 1",
            $session_id
        ));
        
        return !empty($exists);
    }
    
    /**
     * Get anonymized IP address for GDPR compliance
     */
    private function get_anonymized_ip() {
        $ip = $this->get_user_ip();
        return $this->anonymize_ip($ip);
    }
    
    /**
     * Anonymize IP address
     */
    private function anonymize_ip($ip) {
        // Validate IP first
        if (empty($ip)) {
            return '0.0.0.0';
        }
        
        // Anonymize IP address
        if (filter_var($ip, FILTER_VALIDATE_IP, FILTER_FLAG_IPV4)) {
            // IPv4: Set last octet to 0
            $parts = explode('.', $ip);
            if (count($parts) === 4) {
                $parts[3] = '0';
                $ip = implode('.', $parts);
            }
        } elseif (filter_var($ip, FILTER_VALIDATE_IP, FILTER_FLAG_IPV6)) {
            // IPv6: Set last 80 bits to 0
            $packed = inet_pton($ip);
            if ($packed !== false) {
                $anonymized = inet_ntop($packed & pack('a16', "\xff\xff\xff\xff\xff\x00\x00\x00\x00\x00\x00\x00\x00\x00\x00\x00"));
                if ($anonymized !== false) {
                    $ip = $anonymized;
                }
            }
        }
        
        return $ip;
    }
    
    /**
     * Get user IP with multiple header checks
     */
    private function get_user_ip() {
        // Use Sky Insights utility if available
        if (class_exists('SkyInsightsUtils') && method_exists('SkyInsightsUtils', 'get_visitor_ip')) {
            return SkyInsightsUtils::get_visitor_ip();
        }
        
        // Fallback IP detection
        $ip_keys = ['HTTP_CF_CONNECTING_IP', 'HTTP_CLIENT_IP', 'HTTP_X_FORWARDED_FOR', 'HTTP_X_FORWARDED', 'HTTP_X_CLUSTER_CLIENT_IP', 'HTTP_FORWARDED_FOR', 'HTTP_FORWARDED', 'REMOTE_ADDR'];
        
        foreach ($ip_keys as $key) {
            if (!empty($_SERVER[$key])) {
                $ips = explode(',', $_SERVER[$key]);
                $ip = trim($ips[0]);
                if (filter_var($ip, FILTER_VALIDATE_IP, FILTER_FLAG_NO_PRIV_RANGE | FILTER_FLAG_NO_RES_RANGE)) {
                    return $ip;
                }
            }
        }
        
        return isset($_SERVER['REMOTE_ADDR']) ? $_SERVER['REMOTE_ADDR'] : '0.0.0.0';
    }
    
    /**
     * Track WooCommerce conversion (original method as backup)
     */
    public function track_woocommerce_conversion($order_id) {
        // Get settings
        $settings = get_option('sky_seo_settings', []);
        if (empty($settings['google_ads_enabled']) || 
            empty($settings['google_ads_conversion_type']) || 
            $settings['google_ads_conversion_type'] !== 'woocommerce') {
            return;
        }
        
        $order = wc_get_order($order_id);
        if (!$order) {
            return;
        }
        
        // Check if already tracked
        if ($order->get_meta('_sky_gads_conversion_tracked') === 'yes') {
            return;
        }
        
        // Get session from multiple sources
        $session_id = '';
        
        // Priority 1: Order meta
        $session_id = $order->get_meta('_sky_gads_session');
        
        // Priority 2: WooCommerce session
        if (empty($session_id) && function_exists('WC') && WC()->session) {
            $session_id = WC()->session->get('google_ads_session');
        }
        
        // Priority 3: Cookie
        if (empty($session_id) && !empty($_COOKIE['sky_seo_gads_session'])) {
            $session_id = sanitize_text_field($_COOKIE['sky_seo_gads_session']);
        }
        
        if (empty($session_id)) {
            return;
        }
        
        // Ensure table exists
        $this->ensure_table_exists();
        
        // Get the visitor record
        global $wpdb;
        $visitor = $wpdb->get_row($wpdb->prepare(
            "SELECT * FROM {$this->table_name} WHERE session_id = %s AND converted_at IS NULL ORDER BY created_at DESC LIMIT 1",
            $session_id
        ));
        
        if (!$visitor) {
            return;
        }
        
        // Update conversion record
        $result = $wpdb->update(
            $this->table_name,
            [
                'conversion_type' => 'woocommerce',
                'conversion_value' => $order->get_total(),
                'order_id' => $order_id,
                'converted_at' => current_time('mysql')
            ],
            ['id' => $visitor->id],
            ['%s', '%f', '%d', '%s'],
            ['%d']
        );
        
        if ($result !== false) {
            $order->update_meta_data('_sky_gads_conversion_tracked', 'yes');
            $order->save();
        }
    }
    
    /**
     * Enqueue scripts for form tracking
     */
    public function enqueue_form_tracking_scripts() {
        // Get settings
        $settings = get_option('sky_seo_settings', []);
        
        // Check if form tracking should be active
        if (empty($settings['google_ads_enabled']) || 
            empty($settings['google_ads_conversion_type']) || 
            $settings['google_ads_conversion_type'] !== 'form_submission') {
            return;
        }
        
        // Check if visitor has Google Ads session
        if (empty($_COOKIE['sky_seo_gads_session'])) {
            return;
        }
        
        // Register a dummy script to attach our localization
        wp_register_script('sky-seo-form-tracking', '', [], '', true);
        wp_enqueue_script('sky-seo-form-tracking');
        
        // Localize the script with AJAX URL and nonce
        wp_localize_script('sky-seo-form-tracking', 'skySeoFormTracking', [
            'ajaxurl' => admin_url('admin-ajax.php'),
            'nonce' => wp_create_nonce('sky_seo_form_tracking'),
            'session_active' => true,
            'debug' => defined('WP_DEBUG') && WP_DEBUG
        ]);
    }
    
    /**
     * AJAX handler for form submission tracking - FIXED VERSION
     */
    public function ajax_track_form_submission() {
        // Verify nonce
        if (!check_ajax_referer('sky_seo_form_tracking', 'nonce', false)) {
            wp_send_json_error('Invalid nonce', 403);
            return;
        }
        
        // Get settings
        $settings = get_option('sky_seo_settings', []);
        if (empty($settings['google_ads_enabled']) || 
            empty($settings['google_ads_conversion_type']) || 
            $settings['google_ads_conversion_type'] !== 'form_submission') {
            wp_send_json_error('Form tracking not enabled', 400);
            return;
        }
        
        // Check if visitor came from Google Ads
        $session_id = isset($_COOKIE['sky_seo_gads_session']) ? sanitize_text_field($_COOKIE['sky_seo_gads_session']) : '';
        if (empty($session_id)) {
            wp_send_json_error('Not a Google Ads visitor', 400);
            return;
        }
        
        // Ensure table exists
        $this->ensure_table_exists();
        
        // Get the visitor record
        global $wpdb;
        $visitor = $wpdb->get_row($wpdb->prepare(
            "SELECT * FROM {$this->table_name} WHERE session_id = %s AND converted_at IS NULL ORDER BY created_at DESC LIMIT 1",
            $session_id
        ));
        
        if (!$visitor) {
            wp_send_json_error('Visitor record not found', 404);
            return;
        }
        
        // Get form data
        $form_id = isset($_POST['form_id']) ? sanitize_text_field($_POST['form_id']) : 'unknown';
        $form_name = isset($_POST['form_name']) ? sanitize_text_field($_POST['form_name']) : '';
        
        // Parse form data - handle both string and array
        $form_data_raw = isset($_POST['form_data']) ? $_POST['form_data'] : '';
        $form_data = [];
        
        if (is_string($form_data_raw)) {
            // Try to decode JSON
            $decoded = json_decode(stripslashes($form_data_raw), true);
            if (json_last_error() === JSON_ERROR_NONE && is_array($decoded)) {
                $form_data = $decoded;
            }
        } elseif (is_array($form_data_raw)) {
            $form_data = $form_data_raw;
        }
        
        // Sanitize form data WITHOUT changing the field names
        $sanitized_data = [];
        foreach ($form_data as $key => $value) {
            if (is_string($value)) {
                // Keep the original key, only sanitize the value
                $sanitized_data[$key] = sanitize_text_field($value);
            }
        }
        
        // Map WPForms specific fields to standard fields
        if (!isset($sanitized_data['name']) && isset($sanitized_data['wpformsfields1'])) {
            $sanitized_data['name'] = $sanitized_data['wpformsfields1'];
        }
        if (!isset($sanitized_data['phone']) && isset($sanitized_data['wpformsfields2'])) {
            $sanitized_data['phone'] = $sanitized_data['wpformsfields2'];
        }
        if (!isset($sanitized_data['email']) && isset($sanitized_data['wpformsfields3'])) {
            $sanitized_data['email'] = $sanitized_data['wpformsfields3'];
        }
        
        // Update conversion record
        $result = $wpdb->update(
            $this->table_name,
            [
                'conversion_type' => 'form_submission',
                'form_id' => $form_id,
                'form_name' => $form_name,
                'form_data' => wp_json_encode($sanitized_data),
                'converted_at' => current_time('mysql')
            ],
            ['id' => $visitor->id],
            ['%s', '%s', '%s', '%s', '%s'],
            ['%d']
        );
        
        if ($result !== false) {
            wp_send_json_success([
                'message' => 'Form submission tracked',
                'data_received' => !empty($sanitized_data)
            ]);
        } else {
            wp_send_json_error('Failed to track submission', 500);
        }
    }
    
    /**
     * Add form tracking script - UPDATED WITH WPFORMS FIELD MAPPING
     */
    public function add_form_tracking_script() {
        // Get settings
        $settings = get_option('sky_seo_settings', []);
        if (empty($settings['google_ads_enabled']) || 
            empty($settings['google_ads_conversion_type']) || 
            $settings['google_ads_conversion_type'] !== 'form_submission') {
            return;
        }
        
        // Only add if visitor has Google Ads session
        if (empty($_COOKIE['sky_seo_gads_session'])) {
            return;
        }
        
        $nonce = wp_create_nonce('sky_seo_form_tracking');
        ?>
        <script>
        (function() {
            // Track form submissions with better error handling
            function trackFormSubmission(formId, formName, formData) {
                console.log('Sky SEO: Tracking form submission', {
                    formId: formId,
                    formName: formName,
                    formData: formData
                });
                
                var data = new FormData();
                data.append('action', 'sky_seo_track_form_submission');
                data.append('nonce', '<?php echo esc_js($nonce); ?>');
                data.append('form_id', formId);
                data.append('form_name', formName || '');
                data.append('form_data', JSON.stringify(formData || {}));
                
                fetch('<?php echo esc_js(admin_url('admin-ajax.php')); ?>', {
                    method: 'POST',
                    credentials: 'same-origin',
                    body: data
                })
                .then(function(response) {
                    return response.json();
                })
                .then(function(result) {
                    if (result.success) {
                        console.log('✓ Form submission tracked successfully!');
                    }
                })
                .catch(function(error) {
                    console.error('Sky SEO Form Tracking Error:', error);
                });
            }
            
            // Extract form data helper
            function extractFormData(form) {
                var data = {};
                
                // Get form data using FormData API for better compatibility
                try {
                    var formData = new FormData(form);
                    
                    // Convert FormData to object
                    for (var pair of formData.entries()) {
                        var name = pair[0];
                        var value = pair[1];
                        
                        // Skip empty values and buttons
                        if (!value || value === '') {
                            continue;
                        }
                        
                        // Store all data with original field names
                        data[name] = value;
                        
                        // Map WPForms fields specifically
                        if (name === 'wpforms[fields][1]' || name === 'wpformsfields1') {
                            data.name = value;
                        } else if (name === 'wpforms[fields][2]' || name === 'wpformsfields2') {
                            data.phone = value;
                        } else if (name === 'wpforms[fields][3]' || name === 'wpformsfields3') {
                            data.email = value;
                        }
                        
                        // Also check by value patterns
                        if (value.includes('@') && !data.email) {
                            data.email = value;
                        } else if (value.match(/^\+?\d[\d\s\-\(\)]+$/) && !data.phone) {
                            data.phone = value;
                        }
                        
                        // Generic field name matching
                        var nameLower = name.toLowerCase();
                        if ((nameLower.includes('name') || nameLower.includes('nome') || 
                            nameLower.includes('first') || nameLower.includes('last')) && !data.name) {
                            data.name = value;
                        } else if ((nameLower.includes('email') || nameLower.includes('mail')) && !data.email) {
                            data.email = value;
                        } else if ((nameLower.includes('phone') || nameLower.includes('tel') || 
                                   nameLower.includes('mobile') || nameLower.includes('contact')) && !data.phone) {
                            data.phone = value;
                        } else if ((nameLower.includes('message') || nameLower.includes('comment') || 
                                   nameLower.includes('inquiry')) && !data.message) {
                            data.message = value.substring(0, 200) + (value.length > 200 ? '...' : '');
                        }
                    }
                } catch (e) {
                    // Fallback for older browsers
                    var inputs = form.querySelectorAll('input[name], select[name], textarea[name]');
                    
                    inputs.forEach(function(input) {
                        var name = input.name;
                        var value = input.value;
                        
                        // Skip empty values and buttons
                        if (!value || input.type === 'submit' || input.type === 'button') {
                            return;
                        }
                        
                        // For radio buttons and checkboxes, only get checked ones
                        if ((input.type === 'radio' || input.type === 'checkbox') && !input.checked) {
                            return;
                        }
                        
                        // Store all data
                        data[name] = value;
                        
                        // Apply same mapping logic
                        if (name === 'wpforms[fields][1]' || name === 'wpformsfields1') {
                            data.name = value;
                        } else if (name === 'wpforms[fields][2]' || name === 'wpformsfields2') {
                            data.phone = value;
                        } else if (name === 'wpforms[fields][3]' || name === 'wpformsfields3') {
                            data.email = value;
                        }
                    });
                }
                
                console.log('Extracted form data:', data);
                return data;
            }
            
            // Get form name helper
            function getFormName(form) {
                // Try to get form title from various sources
                var formName = '';
                
                // Check for form title in common locations
                var titleElement = form.querySelector('h2, h3, .form-title, .gform_title, .wpforms-form-title, .wpforms-title, .elementor-form-title');
                if (titleElement) {
                    formName = titleElement.textContent.trim();
                }
                
                // Check for aria-label
                if (!formName && form.getAttribute('aria-label')) {
                    formName = form.getAttribute('aria-label');
                }
                
                // Check for data attributes
                if (!formName && form.getAttribute('data-form-name')) {
                    formName = form.getAttribute('data-form-name');
                }
                
                return formName;
            }
            
            // Make function globally available
            window.trackFormSubmission = trackFormSubmission;
            
            // Listen for ALL form submissions
            document.addEventListener('submit', function(e) {
                var form = e.target;
                if (form && form.tagName === 'FORM') {
                    // Small delay to ensure form values are captured
                    setTimeout(function() {
                        var formId = form.id || form.className || 'unknown-form-' + Date.now();
                        var formName = getFormName(form);
                        var formData = extractFormData(form);
                        
                        console.log('Form submission detected:', {
                            formId: formId,
                            formName: formName,
                            dataExtracted: formData
                        });
                        
                        // Track the submission
                        trackFormSubmission(formId, formName, formData);
                    }, 100);
                }
            }, true); // Use capture phase to catch all submissions
            
            // Contact Form 7
            if (typeof wpcf7 !== 'undefined') {
                document.addEventListener('wpcf7mailsent', function(event) {
                    var form = event.target;
                    var formName = getFormName(form) || 'Contact Form 7';
                    var formData = {};
                    
                    // Extract CF7 form data
                    var inputs = form.querySelectorAll('.wpcf7-form-control');
                    inputs.forEach(function(input) {
                        if (input.name && input.value && input.type !== 'submit') {
                            formData[input.name] = input.value;
                            
                            // Map common CF7 field names
                            if (input.name === 'your-name') formData.name = input.value;
                            if (input.name === 'your-email') formData.email = input.value;
                            if (input.name === 'your-phone' || input.name === 'your-tel') formData.phone = input.value;
                            if (input.name === 'your-message') formData.message = input.value;
                        }
                    });
                    
                    trackFormSubmission('cf7-' + event.detail.contactFormId, formName, formData);
                });
            }
            
            // Gravity Forms
            if (typeof jQuery !== 'undefined') {
                jQuery(document).on('gform_confirmation_loaded', function(event, formId) {
                    var form = jQuery('#gform_' + formId)[0];
                    if (form) {
                        var formName = getFormName(form);
                        var formData = extractFormData(form);
                        trackFormSubmission('gform-' + formId, formName, formData);
                    }
                });
            }
            
            // WPForms - UPDATED
            if (typeof jQuery !== 'undefined' && typeof wpforms !== 'undefined') {
                jQuery(document).on('wpformsAjaxSubmitSuccess', function(e, data) {
                    console.log('WPForms submission detected:', data);
                    
                    if (data && data.formId) {
                        var formId = 'wpforms-' + data.formId;
                        var form = jQuery('#wpforms-' + data.formId)[0] || jQuery('#wpforms-form-' + data.formId)[0];
                        var formName = 'WPForm';
                        var formData = {};
                        
                        if (form) {
                            formName = getFormName(form) || 'WPForm';
                            formData = extractFormData(form);
                        }
                        
                        // If form data is empty, try to extract from the page
                        if (Object.keys(formData).length === 0) {
                            jQuery('input[name*="wpforms"], select[name*="wpforms"], textarea[name*="wpforms"]').each(function() {
                                var $field = jQuery(this);
                                var name = $field.attr('name');
                                var value = $field.val();
                                
                                if (name && value) {
                                    formData[name] = value;
                                    
                                    // Extract field number
                                    var match = name.match(/\[fields\]\[(\d+)\]/);
                                    if (match) {
                                        var fieldNum = match[1];
                                        // Map based on field number
                                        if (fieldNum === '1') formData.name = value;
                                        else if (fieldNum === '2') formData.phone = value;
                                        else if (fieldNum === '3') formData.email = value;
                                    }
                                }
                            });
                        }
                        
                        console.log('WPForms data extracted:', formData);
                        trackFormSubmission(formId, formName, formData);
                    }
                });
                
                // Also listen for the standard form submit
                jQuery(document).on('wpformsAjaxSubmitBefore', function(e) {
                    if (e.target && e.target.id) {
                        var form = e.target;
                        var formId = form.id.replace('wpforms-form-', '');
                        var formName = getFormName(form) || 'WPForm';
                        var formData = extractFormData(form);
                        
                        // Store data temporarily
                        window.wpformsTemp = window.wpformsTemp || {};
                        window.wpformsTemp[formId] = {
                            formName: formName,
                            formData: formData
                        };
                    }
                });
            }
            
            // Elementor Forms
            if (typeof jQuery !== 'undefined') {
                jQuery(document).on('submit_success', function(event, data) {
                    if (data && data.form_id) {
                        var form = jQuery('[data-form-id="' + data.form_id + '"]')[0];
                        if (form) {
                            var formName = getFormName(form);
                            var formData = extractFormData(form);
                            trackFormSubmission('elementor-' + data.form_id, formName, formData);
                        }
                    }
                });
            }
            
            // Ninja Forms
            if (typeof Marionette !== 'undefined' && typeof Backbone !== 'undefined') {
                try {
                    var ninjaFormsController = Marionette.Object.extend({
                        initialize: function() {
                            this.listenTo(Backbone.Radio.channel('forms'), 'submit:response', this.actionSubmit);
                        },
                        actionSubmit: function(response) {
                            if (response && response.data && response.data.form_id) {
                                var form = jQuery('.nf-form-' + response.data.form_id)[0];
                                if (form) {
                                    var formName = getFormName(form);
                                    var formData = {};
                                    if (response.data.fields) {
                                        for (var fieldId in response.data.fields) {
                                            var field = response.data.fields[fieldId];
                                            if (field.value) {
                                                formData[field.key] = field.value;
                                            }
                                        }
                                    }
                                    trackFormSubmission('ninja-' + response.data.form_id, formName, formData);
                                }
                            }
                        }
                    });
                    new ninjaFormsController();
                } catch(e) {
                    // Ninja Forms not fully loaded
                }
            }
        })();
        </script>
        <?php
    }
    
    /**
     * Add analytics section
     */
    public function add_analytics_section() {
        ?>
        <div class="sky-seo-analytics-section" id="google-ads-tracking">
            <h2><?php _e('Google Paid Ads Tracking', 'sky-seo-boost'); ?></h2>
            <div class="sky-seo-google-ads-content">
                <div class="sky-seo-loading">
                    <span class="spinner is-active"></span>
                    <?php _e('Loading Google Ads data...', 'sky-seo-boost'); ?>
                </div>
            </div>
        </div>
        
        <script>
        jQuery(document).ready(function($) {
            // Load Google Ads stats
            function loadGoogleAdsStats() {
                $.ajax({
                    url: ajaxurl,
                    type: 'POST',
                    data: {
                        action: 'sky_seo_get_google_ads_stats',
                        nonce: typeof skySeoAjax !== 'undefined' ? skySeoAjax.nonce : '<?php echo wp_create_nonce('sky_seo_analytics_nonce'); ?>',
                        period: jQuery('#sky-seo-period').val() || '30'
                    },
                    success: function(response) {
                        if (response.success) {
                            $('.sky-seo-google-ads-content').html(response.data.html);
                        }
                    },
                    error: function() {
                        $('.sky-seo-google-ads-content').html('<p>Error loading Google Ads data.</p>');
                    }
                });
            }
            
            // Initial load
            loadGoogleAdsStats();
            
            // Reload on period change
            $('#sky-seo-period').on('change', loadGoogleAdsStats);
        });
        </script>
        <?php
    }
    
    /**
     * AJAX handler for getting Google Ads stats
     */
    public function ajax_get_google_ads_stats() {
        // Verify nonce
        if (!check_ajax_referer('sky_seo_analytics_nonce', 'nonce', false)) {
            wp_send_json_error('Invalid nonce', 403);
            return;
        }
        
        // Check capabilities
        if (!current_user_can('manage_options')) {
            wp_send_json_error('Insufficient permissions', 403);
            return;
        }
        
        // Ensure table exists
        $this->ensure_table_exists();
        
        // Get settings
        $settings = get_option('sky_seo_settings', []);
        $conversion_type = isset($settings['google_ads_conversion_type']) ? $settings['google_ads_conversion_type'] : 'woocommerce';
        
        // Get period
        $period = isset($_POST['period']) ? intval($_POST['period']) : 30;
        $start_date = date('Y-m-d H:i:s', strtotime("-{$period} days"));
        
        global $wpdb;
        
        // Get total visitors from Google Ads
        $total_visitors = $wpdb->get_var($wpdb->prepare(
            "SELECT COUNT(*) FROM {$this->table_name} WHERE created_at >= %s",
            $start_date
        ));
        
        // Get conversions
        $conversions = $wpdb->get_var($wpdb->prepare(
            "SELECT COUNT(*) FROM {$this->table_name} WHERE created_at >= %s AND converted_at IS NOT NULL",
            $start_date
        ));
        
        // Calculate conversion rate
        $conversion_rate = $total_visitors > 0 ? ($conversions / $total_visitors) * 100 : 0;
        
        // Start building HTML
        ob_start();
        ?>
        <div class="sky-seo-stats-grid">
            <div class="sky-seo-stat-card">
                <h3><?php _e('Total Google Ads Visitors', 'sky-seo-boost'); ?></h3>
                <div class="sky-seo-stat-value"><?php echo number_format($total_visitors); ?></div>
            </div>
            
            <div class="sky-seo-stat-card">
                <h3><?php _e('Total Conversions', 'sky-seo-boost'); ?></h3>
                <div class="sky-seo-stat-value"><?php echo number_format($conversions); ?></div>
            </div>
            
            <div class="sky-seo-stat-card">
                <h3><?php _e('Conversion Rate', 'sky-seo-boost'); ?></h3>
                <div class="sky-seo-stat-value"><?php echo number_format($conversion_rate, 2); ?>%</div>
            </div>
        </div>
        
        <?php if ($conversion_type === 'woocommerce' && class_exists('WooCommerce')): ?>
            <?php
            // Get WooCommerce specific stats
            $woo_stats = $wpdb->get_row($wpdb->prepare(
                "SELECT 
                    COUNT(*) as total_orders,
                    SUM(conversion_value) as total_revenue,
                    AVG(conversion_value) as avg_order_value
                FROM {$this->table_name} 
                WHERE created_at >= %s 
                    AND conversion_type = 'woocommerce' 
                    AND converted_at IS NOT NULL",
                $start_date
            ));
            ?>
            
            <div class="sky-seo-woo-stats">
                <h3><?php _e('WooCommerce Conversion Details', 'sky-seo-boost'); ?></h3>
                <div class="sky-seo-stats-grid">
                    <div class="sky-seo-stat-card">
                        <h4><?php _e('Total Orders', 'sky-seo-boost'); ?></h4>
                        <div class="sky-seo-stat-value"><?php echo number_format($woo_stats->total_orders); ?></div>
                    </div>
                    
                    <div class="sky-seo-stat-card">
                        <h4><?php _e('Total Revenue', 'sky-seo-boost'); ?></h4>
                        <div class="sky-seo-stat-value"><?php echo function_exists('wc_price') ? wc_price($woo_stats->total_revenue) : '$' . number_format($woo_stats->total_revenue, 2); ?></div>
                    </div>
                    
                    <div class="sky-seo-stat-card">
                        <h4><?php _e('Average Order Value', 'sky-seo-boost'); ?></h4>
                        <div class="sky-seo-stat-value"><?php echo function_exists('wc_price') ? wc_price($woo_stats->avg_order_value) : '$' . number_format($woo_stats->avg_order_value, 2); ?></div>
                    </div>
                </div>
            </div>
            
            <?php
            // Get top campaigns
            $top_campaigns = $wpdb->get_results($wpdb->prepare(
                "SELECT 
                    utm_campaign,
                    COUNT(*) as orders,
                    SUM(conversion_value) as revenue
                FROM {$this->table_name}
                WHERE created_at >= %s 
                    AND conversion_type = 'woocommerce' 
                    AND converted_at IS NOT NULL
                    AND utm_campaign != ''
                GROUP BY utm_campaign
                ORDER BY revenue DESC
                LIMIT 5",
                $start_date
            ));
            
            if ($top_campaigns):
            ?>
            <div class="sky-seo-campaigns">
                <h3><?php _e('Top Performing Campaigns', 'sky-seo-boost'); ?></h3>
                <table class="widefat">
                    <thead>
                        <tr>
                            <th><?php _e('Campaign', 'sky-seo-boost'); ?></th>
                            <th><?php _e('Orders', 'sky-seo-boost'); ?></th>
                            <th><?php _e('Revenue', 'sky-seo-boost'); ?></th>
                        </tr>
                    </thead>
                    <tbody>
                        <?php foreach ($top_campaigns as $campaign): ?>
                        <tr>
                            <td><?php echo esc_html($campaign->utm_campaign); ?></td>
                            <td><?php echo number_format($campaign->orders); ?></td>
                            <td><?php echo function_exists('wc_price') ? wc_price($campaign->revenue) : '$' . number_format($campaign->revenue, 2); ?></td>
                        </tr>
                        <?php endforeach; ?>
                    </tbody>
                </table>
            </div>
            <?php endif; ?>
            
        <?php elseif ($conversion_type === 'form_submission'): ?>
            <?php
            // Get form submission stats - check if form_name column exists
            $columns = $wpdb->get_col("SHOW COLUMNS FROM {$this->table_name}");
            $has_form_name = in_array('form_name', $columns);
            
            if ($has_form_name) {
                // Use new query with form_name
                $form_stats = $wpdb->get_results($wpdb->prepare(
                    "SELECT 
                        form_id,
                        MAX(form_name) as form_name,
                        COUNT(*) as submissions,
                        MAX(converted_at) as last_submission
                    FROM {$this->table_name}
                    WHERE created_at >= %s 
                        AND conversion_type = 'form_submission' 
                        AND converted_at IS NOT NULL
                    GROUP BY form_id
                    ORDER BY last_submission DESC",
                    $start_date
                ));
            } else {
                // Fallback for old table structure
                $form_stats = $wpdb->get_results($wpdb->prepare(
                    "SELECT 
                        form_id,
                        '' as form_name,
                        COUNT(*) as submissions,
                        MAX(converted_at) as last_submission
                    FROM {$this->table_name}
                    WHERE created_at >= %s 
                        AND conversion_type = 'form_submission' 
                        AND converted_at IS NOT NULL
                    GROUP BY form_id
                    ORDER BY last_submission DESC",
                    $start_date
                ));
            }
            ?>
            
            <div class="sky-seo-form-stats">
                <h3><?php _e('Form Submission Details', 'sky-seo-boost'); ?></h3>
                <table class="widefat">
                    <thead>
                        <tr>
                            <th><?php _e('Form', 'sky-seo-boost'); ?></th>
                            <th><?php _e('Submissions from Google Ads', 'sky-seo-boost'); ?></th>
                            <th><?php _e('Last Submission', 'sky-seo-boost'); ?></th>
                            <th><?php _e('Actions', 'sky-seo-boost'); ?></th>
                        </tr>
                    </thead>
                    <tbody>
                        <?php if ($form_stats && count($form_stats) > 0): ?>
                            <?php foreach ($form_stats as $form): ?>
                            <tr>
                                <td>
                                    <strong><?php echo esc_html($form->form_name ?: 'Unnamed Form'); ?></strong><br>
                                    <small style="color: #666;"><?php echo esc_html($form->form_id); ?></small>
                                </td>
                                <td><?php echo number_format($form->submissions); ?></td>
                                <td><?php echo human_time_diff(strtotime($form->last_submission), current_time('timestamp')) . ' ago'; ?></td>
                                <td>
                                    <button type="button" class="button button-small sky-seo-view-submissions" 
                                            data-form-id="<?php echo esc_attr($form->form_id); ?>"
                                            data-form-name="<?php echo esc_attr($form->form_name ?: 'Unnamed Form'); ?>">
                                        <?php _e('View Submissions', 'sky-seo-boost'); ?>
                                    </button>
                                </td>
                            </tr>
                            <?php endforeach; ?>
                        <?php else: ?>
                            <tr>
                                <td colspan="4"><?php _e('No form submissions tracked yet.', 'sky-seo-boost'); ?></td>
                            </tr>
                        <?php endif; ?>
                    </tbody>
                </table>
            </div>
            
            <!-- Submissions Popup Modal -->
            <div id="sky-seo-submissions-modal" style="display:none;">
                <div class="sky-seo-modal-overlay"></div>
                <div class="sky-seo-modal-content">
                    <div class="sky-seo-modal-header">
                        <h2 id="sky-seo-modal-title"><?php _e('Form Submissions', 'sky-seo-boost'); ?></h2>
                        <button type="button" class="sky-seo-modal-close">&times;</button>
                    </div>
                    <div class="sky-seo-modal-body">
                        <div class="sky-seo-loading">
                            <span class="spinner is-active"></span>
                            <?php _e('Loading submissions...', 'sky-seo-boost'); ?>
                        </div>
                    </div>
                </div>
            </div>
            
            <style>
            #sky-seo-submissions-modal {
                position: fixed;
                top: 0;
                left: 0;
                width: 100%;
                height: 100%;
                z-index: 100000;
            }
            
            .sky-seo-modal-overlay {
                position: absolute;
                top: 0;
                left: 0;
                width: 100%;
                height: 100%;
                background: rgba(0, 0, 0, 0.6);
            }
            
            .sky-seo-modal-content {
                position: absolute;
                top: 50%;
                left: 50%;
                transform: translate(-50%, -50%);
                background: #fff;
                border-radius: 8px;
                box-shadow: 0 4px 20px rgba(0, 0, 0, 0.3);
                width: 90%;
                max-width: 800px;
                max-height: 80vh;
                display: flex;
                flex-direction: column;
            }
            
            .sky-seo-modal-header {
                padding: 20px;
                border-bottom: 1px solid #e0e0e0;
                display: flex;
                justify-content: space-between;
                align-items: center;
            }
            
            .sky-seo-modal-header h2 {
                margin: 0;
                font-size: 20px;
            }
            
            .sky-seo-modal-close {
                background: none;
                border: none;
                font-size: 28px;
                cursor: pointer;
                color: #666;
                padding: 0;
                width: 30px;
                height: 30px;
                display: flex;
                align-items: center;
                justify-content: center;
            }
            
            .sky-seo-modal-close:hover {
                color: #000;
            }
            
            .sky-seo-modal-body {
                padding: 20px;
                overflow-y: auto;
                flex: 1;
            }
            
            .sky-seo-submission-item {
                background: #f8f9fa;
                border: 1px solid #e0e0e0;
                border-radius: 6px;
                padding: 15px;
                margin-bottom: 15px;
            }
            
            .sky-seo-submission-header {
                display: flex;
                justify-content: space-between;
                margin-bottom: 10px;
                font-size: 12px;
                color: #666;
            }
            
            .sky-seo-submission-data {
                display: grid;
                grid-template-columns: repeat(auto-fit, minmax(250px, 1fr));
                gap: 10px;
            }
            
            .sky-seo-data-field {
                background: #fff;
                padding: 10px;
                border-radius: 4px;
                border: 1px solid #e0e0e0;
            }
            
            .sky-seo-data-label {
                font-weight: 600;
                color: #333;
                font-size: 12px;
                text-transform: uppercase;
                margin-bottom: 4px;
            }
            
            .sky-seo-data-value {
                color: #666;
                word-break: break-word;
            }
            
            .sky-seo-pagination {
                display: flex;
                justify-content: center;
                align-items: center;
                gap: 10px;
                margin-top: 20px;
                padding-top: 20px;
                border-top: 1px solid #e0e0e0;
            }
            
            .sky-seo-pagination button {
                padding: 6px 12px;
            }
            
            .sky-seo-loading {
                text-align: center;
                padding: 40px;
            }
            </style>
            
            <script>
            jQuery(document).ready(function($) {
                var currentPage = 1;
                var currentFormId = '';
                
                // View submissions click handler
                $('.sky-seo-view-submissions').on('click', function() {
                    currentFormId = $(this).data('form-id');
                    var formName = $(this).data('form-name');
                    
                    $('#sky-seo-modal-title').text('Submissions for: ' + formName);
                    $('#sky-seo-submissions-modal').show();
                    
                    loadSubmissions(1);
                });
                
                // Close modal
                $('.sky-seo-modal-close, .sky-seo-modal-overlay').on('click', function() {
                    $('#sky-seo-submissions-modal').hide();
                });
                
                // Load submissions function
                function loadSubmissions(page) {
                    currentPage = page;
                    
                    $.ajax({
                        url: ajaxurl,
                        type: 'POST',
                        data: {
                            action: 'sky_seo_get_form_submissions',
                            nonce: '<?php echo wp_create_nonce('sky_seo_analytics_nonce'); ?>',
                            form_id: currentFormId,
                            page: page
                        },
                        success: function(response) {
                            if (response.success) {
                                displaySubmissions(response.data);
                            }
                        }
                    });
                }
                
                // Display submissions
                function displaySubmissions(data) {
                    var html = '';
                    
                    if (data.submissions && data.submissions.length > 0) {
                        data.submissions.forEach(function(submission) {
                            html += '<div class="sky-seo-submission-item">';
                            html += '<div class="sky-seo-submission-header">';
                            html += '<span>Submitted: ' + submission.submitted_at + '</span>';
                            html += '<span>Campaign: ' + (submission.utm_campaign || 'N/A') + '</span>';
                            html += '</div>';
                            html += '<div class="sky-seo-submission-data">';
                            
                            // Handle old records without form_data
                            if (submission.form_data.info) {
                                html += '<div class="sky-seo-data-field" style="grid-column: 1 / -1;">';
                                html += '<div class="sky-seo-data-value" style="font-style: italic; color: #999;">' + submission.form_data.info + '</div>';
                                html += '</div>';
                            } else {
                                // Priority fields
                                if (submission.form_data.name) {
                                    html += '<div class="sky-seo-data-field">';
                                    html += '<div class="sky-seo-data-label">Name</div>';
                                    html += '<div class="sky-seo-data-value">' + submission.form_data.name + '</div>';
                                    html += '</div>';
                                }
                                
                                if (submission.form_data.email) {
                                    html += '<div class="sky-seo-data-field">';
                                    html += '<div class="sky-seo-data-label">Email</div>';
                                    html += '<div class="sky-seo-data-value">' + submission.form_data.email + '</div>';
                                    html += '</div>';
                                }
                                
                                if (submission.form_data.phone) {
                                    html += '<div class="sky-seo-data-field">';
                                    html += '<div class="sky-seo-data-label">Phone</div>';
                                    html += '<div class="sky-seo-data-value">' + submission.form_data.phone + '</div>';
                                    html += '</div>';
                                }
                                
                                if (submission.form_data.message) {
                                    html += '<div class="sky-seo-data-field" style="grid-column: 1 / -1;">';
                                    html += '<div class="sky-seo-data-label">Message</div>';
                                    html += '<div class="sky-seo-data-value">' + submission.form_data.message + '</div>';
                                    html += '</div>';
                                }
                            }
                            
                            html += '</div>';
                            html += '</div>';
                        });
                        
                        // Pagination
                        if (data.total_pages > 1) {
                            html += '<div class="sky-seo-pagination">';
                            
                            if (currentPage > 1) {
                                html += '<button type="button" class="button" onclick="loadSubmissions(' + (currentPage - 1) + ')">Previous</button>';
                            }
                            
                            html += '<span>Page ' + currentPage + ' of ' + data.total_pages + '</span>';
                            
                            if (currentPage < data.total_pages) {
                                html += '<button type="button" class="button" onclick="loadSubmissions(' + (currentPage + 1) + ')">Next</button>';
                            }
                            
                            html += '</div>';
                        }
                    } else {
                        html = '<p>No submissions found for this form.</p>';
                    }
                    
                    $('.sky-seo-modal-body').html(html);
                }
                
                // Make loadSubmissions global for pagination buttons
                window.loadSubmissions = loadSubmissions;
            });
            </script>
        <?php endif; ?>
        
        <?php
        $html = ob_get_clean();
        
        wp_send_json_success(['html' => $html]);
    }
    
    /**
     * AJAX handler for getting form submissions details - UPDATED WITH FIX
     */
    public function ajax_get_form_submissions() {
        // Verify nonce
        if (!check_ajax_referer('sky_seo_analytics_nonce', 'nonce', false)) {
            wp_send_json_error('Invalid nonce', 403);
            return;
        }
        
        // Check capabilities
        if (!current_user_can('manage_options')) {
            wp_send_json_error('Insufficient permissions', 403);
            return;
        }
        
        $form_id = isset($_POST['form_id']) ? sanitize_text_field($_POST['form_id']) : '';
        $page = isset($_POST['page']) ? max(1, intval($_POST['page'])) : 1;
        $per_page = 10;
        $offset = ($page - 1) * $per_page;
        
        if (empty($form_id)) {
            wp_send_json_error('Form ID required', 400);
            return;
        }
        
        global $wpdb;
        
        // Get total count - include old records
        $total = $wpdb->get_var($wpdb->prepare(
            "SELECT COUNT(*) FROM {$this->table_name} 
             WHERE form_id = %s AND conversion_type = 'form_submission'",
            $form_id
        ));
        
        // Get submissions - handle both old (without form_data) and new records
        $submissions = $wpdb->get_results($wpdb->prepare(
            "SELECT 
                form_id,
                form_name,
                form_data,
                utm_campaign,
                utm_source,
                utm_medium,
                converted_at,
                ip_address
            FROM {$this->table_name}
            WHERE form_id = %s 
                AND conversion_type = 'form_submission'
            ORDER BY converted_at DESC
            LIMIT %d OFFSET %d",
            $form_id,
            $per_page,
            $offset
        ));
        
        $formatted_submissions = [];
        
        foreach ($submissions as $submission) {
            $form_data = [];
            
            // Handle new records with form_data
            if (!empty($submission->form_data)) {
                $form_data = json_decode($submission->form_data, true) ?: [];
            }
            
            // Prioritize important fields
            $priority_data = [];
            
            if (!empty($form_data)) {
                // Check for mapped fields first
                if (isset($form_data['name'])) {
                    $priority_data['name'] = $form_data['name'];
                }
                if (isset($form_data['email'])) {
                    $priority_data['email'] = $form_data['email'];
                }
                if (isset($form_data['phone'])) {
                    $priority_data['phone'] = $form_data['phone'];
                }
                if (isset($form_data['message'])) {
                    $priority_data['message'] = $form_data['message'];
                }
                
                // If no mapped fields, check WPForms specific fields
                if (empty($priority_data)) {
                    if (isset($form_data['wpformsfields1'])) {
                        $priority_data['name'] = $form_data['wpformsfields1'];
                    }
                    if (isset($form_data['wpformsfields2'])) {
                        $priority_data['phone'] = $form_data['wpformsfields2'];
                    }
                    if (isset($form_data['wpformsfields3'])) {
                        $priority_data['email'] = $form_data['wpformsfields3'];
                    }
                    
                    // Also check for message in other fields
                    foreach ($form_data as $key => $value) {
                        if (strpos($key, 'message') !== false || strpos($key, 'comment') !== false) {
                            $priority_data['message'] = $value;
                            break;
                        }
                    }
                }
                
                // If still no data found, try generic field name matching
                if (empty($priority_data)) {
                    foreach ($form_data as $key => $value) {
                        $key_lower = strtolower($key);
                        if (strpos($key_lower, 'name') !== false && !isset($priority_data['name'])) {
                            $priority_data['name'] = $value;
                        } elseif ((strpos($key_lower, 'email') !== false || strpos($key_lower, 'mail') !== false) && !isset($priority_data['email'])) {
                            $priority_data['email'] = $value;
                        } elseif ((strpos($key_lower, 'phone') !== false || strpos($key_lower, 'tel') !== false) && !isset($priority_data['phone'])) {
                            $priority_data['phone'] = $value;
                        } elseif ((strpos($key_lower, 'message') !== false || strpos($key_lower, 'comment') !== false) && !isset($priority_data['message'])) {
                            $priority_data['message'] = $value;
                        }
                    }
                }
            } else {
                // For old records without form_data, show basic info
                $priority_data['info'] = 'Submission tracked before detailed data collection was enabled';
            }
            
            $formatted_submissions[] = [
                'submitted_at' => human_time_diff(strtotime($submission->converted_at), current_time('timestamp')) . ' ago',
                'utm_campaign' => $submission->utm_campaign,
                'utm_source' => $submission->utm_source,
                'form_data' => $priority_data
            ];
        }
        
        wp_send_json_success([
            'submissions' => $formatted_submissions,
            'total' => $total,
            'total_pages' => ceil($total / $per_page),
            'current_page' => $page
        ]);
    }
    
    /**
     * Cleanup old data (older than 90 days)
     */
    public function cleanup_old_data() {
        global $wpdb;
        
        // Only cleanup if table exists
        if (!$this->ensure_table_exists()) {
            return;
        }
        
        // Allow filtering the retention period
        $retention_days = apply_filters('sky_seo_google_ads_retention_days', 90);
        
        $wpdb->query($wpdb->prepare(
            "DELETE FROM {$this->table_name} WHERE created_at < %s",
            date('Y-m-d H:i:s', strtotime("-{$retention_days} days"))
        ));
    }
}

// Initialize the enhanced Google Ads tracking
add_action('plugins_loaded', function() {
    Sky_SEO_Enhanced_Google_Ads::get_instance();
}, 5);