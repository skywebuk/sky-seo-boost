<?php
/**
 * UTM Tracker Class - Handles UTM link generation, tracking, and analytics
 * UPDATED VERSION - Fixed data isolation, country display, and session handling
 */

// Prevent direct access
if (!defined('ABSPATH')) {
    exit;
}

class SkyInsightsUTMTracker {
    
    /**
     * Class properties
     */
    private static $instance = null;
    private $table_name;
    private $clicks_table;
    
    /**
     * Get singleton instance
     */
    public static function get_instance() {
        if (null === self::$instance) {
            self::$instance = new self();
        }
        return self::$instance;
    }
    
    /**
     * Constructor
     */
    private function __construct() {
        global $wpdb;
        $this->table_name = $wpdb->prefix . 'sky_insights_utm_links';
        $this->clicks_table = $wpdb->prefix . 'sky_insights_utm_clicks';
        
        add_action('init', array($this, 'init_hooks'));
    }
    
    /**
     * Initialize hooks
     */
    public function init_hooks() {
        // Track clicks with high priority
        add_action('init', array($this, 'track_utm_click'), 1);
        
        // Also track on template_redirect as fallback
        add_action('template_redirect', array($this, 'track_utm_click_fallback'), 1);
        
        // NEW: Track regular UTM parameters on WordPress pages
        // add_action('wp', array($this, 'track_regular_utm_parameters'), 5); // DISABLED to prevent auto-creation
        
        // Track conversions - WooCommerce
        add_action('woocommerce_thankyou', array($this, 'track_utm_conversion'), 10, 1);
        add_action('woocommerce_order_status_completed', array($this, 'track_utm_conversion_delayed'), 10, 1);
        add_action('woocommerce_order_status_processing', array($this, 'track_utm_conversion_delayed'), 10, 1);

        // Store UTM data in order
        add_action('woocommerce_checkout_create_order', array($this, 'save_utm_data_to_order'), 20, 2);

        // Track conversions - GiveWP Donations
        add_action('give_insert_payment', array($this, 'track_donation_conversion'), 10, 2);
        add_action('give_update_payment_status', array($this, 'track_donation_status_update'), 10, 3);

        // Track conversions - WooCommerce Donations (if different from orders)
        add_action('woocommerce_donation_created', array($this, 'track_wc_donation'), 10, 1);
        
        // Register AJAX handlers
        $this->register_ajax_handlers();
        
        // Debug logging
        add_action('init', array($this, 'debug_utm_tracking'));
        
        // Schedule cleanup of orphaned clicks
        if (!wp_next_scheduled('sky_utm_cleanup_orphaned_clicks')) {
            wp_schedule_event(time(), 'daily', 'sky_utm_cleanup_orphaned_clicks');
        }
        add_action('sky_utm_cleanup_orphaned_clicks', array($this, 'cleanup_orphaned_clicks'));
    }
    
    /**
     * NEW METHOD: Track regular UTM parameter visits (not sky_utm shortlinks)
     * This enables tracking of normal WordPress pages with UTM parameters
     */
    public function track_regular_utm_parameters() {
        // Skip if admin, AJAX, or it's a sky_utm link
        if (is_admin() || wp_doing_ajax() || isset($_GET['sky_utm'])) {
            return;
        }
        
        // Check if we have UTM parameters
        if (!isset($_GET['utm_source']) || empty($_GET['utm_source'])) {
            return;
        }
        
        // Get UTM parameters
        $utm_source = sanitize_text_field($_GET['utm_source']);
        $utm_medium = isset($_GET['utm_medium']) ? sanitize_text_field($_GET['utm_medium']) : '';
        $utm_campaign = isset($_GET['utm_campaign']) ? sanitize_text_field($_GET['utm_campaign']) : '';
        $utm_term = isset($_GET['utm_term']) ? sanitize_text_field($_GET['utm_term']) : '';
        $utm_content = isset($_GET['utm_content']) ? sanitize_text_field($_GET['utm_content']) : '';
        
        // Set cookies for conversion tracking (30 days)
        $cookie_duration = 30 * DAY_IN_SECONDS;
        $domain = COOKIE_DOMAIN ?: '';
        
        setcookie('sky_utm_source', $utm_source, time() + $cookie_duration, '/', $domain, is_ssl(), true);
        if ($utm_medium) setcookie('sky_utm_medium', $utm_medium, time() + $cookie_duration, '/', $domain, is_ssl(), true);
        if ($utm_campaign) setcookie('sky_utm_campaign', $utm_campaign, time() + $cookie_duration, '/', $domain, is_ssl(), true);
        if ($utm_term) setcookie('sky_utm_term', $utm_term, time() + $cookie_duration, '/', $domain, is_ssl(), true);
        if ($utm_content) setcookie('sky_utm_content', $utm_content, time() + $cookie_duration, '/', $domain, is_ssl(), true);
        
        // Store in session for better persistence through add-to-cart
        if (!session_id()) {
            @session_start();
        }
        $_SESSION['sky_utm_data'] = array(
            'utm_params' => array(
                'utm_source' => $utm_source,
                'utm_medium' => $utm_medium,
                'utm_campaign' => $utm_campaign,
                'utm_term' => $utm_term,
                'utm_content' => $utm_content
            ),
            'landing_page' => $_SERVER['REQUEST_URI'],
            'timestamp' => current_time('timestamp')
        );
        
        // Optional: Create an organic UTM link entry in database for tracking
        // This allows these visits to show in analytics
        $this->find_or_create_organic_utm_link($utm_source, $utm_medium, $utm_campaign);
    }
    
    /**
     * Find or create an organic UTM link entry for tracking purposes
     */
    private function find_or_create_organic_utm_link($utm_source, $utm_medium, $utm_campaign) {
        return false; // DISABLED to prevent auto-creation of UTM links
        
        global $wpdb;
        
        // Check if we already have this combination
        $existing = $wpdb->get_row($wpdb->prepare(
            "SELECT id FROM {$this->table_name} 
             WHERE utm_source = %s 
             AND utm_medium = %s 
             AND utm_campaign = %s 
             AND short_code LIKE 'org_%%'
             AND is_active = 1
             LIMIT 1",
            $utm_source,
            $utm_medium,
            $utm_campaign
        ));
        
        if ($existing) {
            // Update click count
            $wpdb->query($wpdb->prepare(
                "UPDATE {$this->table_name} SET clicks = clicks + 1 WHERE id = %d",
                $existing->id
            ));
            
            // Set link ID cookie for conversion tracking
            setcookie('sky_utm_link_id', $existing->id, time() + (30 * DAY_IN_SECONDS), '/', COOKIE_DOMAIN, is_ssl(), true);
            $_SESSION['sky_utm_data']['link_id'] = $existing->id;
            
            return $existing->id;
        }
        
        // Create new organic entry
        $short_code = 'org_' . substr(md5($utm_source . $utm_medium . $utm_campaign . time()), 0, 6);
        $destination_url = home_url($_SERVER['REQUEST_URI']);
        
        $result = $wpdb->insert(
            $this->table_name,
            array(
                'short_code' => $short_code,
                'destination_url' => $destination_url,
                'utm_source' => $utm_source,
                'utm_medium' => $utm_medium,
                'utm_campaign' => $utm_campaign,
                'utm_term' => '',
                'utm_content' => '',
                'created_by' => 0, // 0 for organic
                'created_at' => current_time('mysql'),
                'clicks' => 1,
                'conversions' => 0,
                'revenue' => 0.00,
                'is_active' => 1
            )
        );
        
        if ($result) {
            $link_id = $wpdb->insert_id;
            setcookie('sky_utm_link_id', $link_id, time() + (30 * DAY_IN_SECONDS), '/', COOKIE_DOMAIN, is_ssl(), true);
            $_SESSION['sky_utm_data']['link_id'] = $link_id;
            return $link_id;
        }
        
        return false;
    }
    
    /**
     * Public render method for dashboard
     */
    public function render_utm_dashboard() {
        // Call the dashboard page function if it exists
        if (function_exists('sky_seo_utm_dashboard_page')) {
            sky_seo_utm_dashboard_page();
        } else {
            // Fallback dashboard
            ?>
            <div class="wrap sky-seo-utm-wrap">
                <h1><?php _e('UTM Link Tracker', 'sky-seo-boost'); ?></h1>
                <div class="notice notice-error">
                    <p><?php _e('UTM Dashboard could not be loaded. Please check that all files are properly installed.', 'sky-seo-boost'); ?></p>
                </div>
            </div>
            <?php
        }
    }
    
    /**
     * Get total statistics without date filtering
     * NEW METHOD - For summary cards
     */
    public function get_total_statistics() {
        global $wpdb;
        
        // Get total clicks, conversions and revenue from ALL TIME
        $totals = $wpdb->get_row("
            SELECT 
                COUNT(DISTINCT c.id) as total_clicks,
                COUNT(DISTINCT CASE WHEN c.converted = 1 THEN c.id END) as total_conversions,
                COALESCE(SUM(CASE WHEN c.converted = 1 THEN o.meta_value END), 0) as total_revenue
            FROM {$this->table_name} l
            LEFT JOIN {$this->clicks_table} c ON l.id = c.link_id
            LEFT JOIN {$wpdb->postmeta} o ON c.order_id = o.post_id AND o.meta_key = '_order_total'
            WHERE l.is_active = 1
        ", ARRAY_A);
        
        return array(
            'total_clicks' => intval($totals['total_clicks']),
            'total_conversions' => intval($totals['total_conversions']),
            'total_revenue' => floatval($totals['total_revenue']),
            'conversion_rate' => $totals['total_clicks'] > 0 
                ? round(($totals['total_conversions'] / $totals['total_clicks']) * 100, 2) 
                : 0,
            'avg_order_value' => $totals['total_conversions'] > 0 
                ? round($totals['total_revenue'] / $totals['total_conversions'], 2) 
                : 0
        );
    }
    
    /**
     * Debug UTM tracking
     */
    public function debug_utm_tracking() {
        if (defined('WP_DEBUG') && WP_DEBUG && isset($_GET['debug_utm'])) {
            // Debug logging removed for performance
            // Debug logging removed for performance
            // Debug logging removed for performance
            // Debug logging removed for performance
            // Debug logging removed for performance
            // Debug logging removed for performance
            // Debug logging removed for performance
        }
    }
    
    /**
     * Register AJAX handlers
     */
    private function register_ajax_handlers() {
        $ajax_actions = array(
            'create_utm_link' => 'ajax_create_utm_link',
            'get_utm_links' => 'ajax_get_utm_links',
            'delete_utm_link' => 'ajax_delete_utm_link',
            'bulk_delete_utm_links' => 'ajax_bulk_delete_utm_links',
            'get_utm_analytics' => 'ajax_get_utm_analytics',
            'update_utm_link' => 'ajax_update_utm_link',
            'get_utm_click_details' => 'ajax_get_utm_click_details'
        );
        
        foreach ($ajax_actions as $action => $method) {
            add_action('wp_ajax_sky_insights_' . $action, array($this, $method));
        }
    }
    
    /**
     * Create database tables
     */
    public function create_tables() {
        global $wpdb;
        
        $charset_collate = $wpdb->get_charset_collate();
        
        // Links table
        $sql = "CREATE TABLE IF NOT EXISTS {$this->table_name} (
            id bigint(20) NOT NULL AUTO_INCREMENT,
            short_code varchar(10) NOT NULL,
            destination_url text NOT NULL,
            utm_source varchar(100) NOT NULL,
            utm_medium varchar(100) DEFAULT NULL,
            utm_campaign varchar(100) DEFAULT NULL,
            utm_term varchar(100) DEFAULT NULL,
            utm_content varchar(100) DEFAULT NULL,
            created_by bigint(20) NOT NULL,
            created_at datetime DEFAULT CURRENT_TIMESTAMP,
            clicks int(11) DEFAULT 0,
            conversions int(11) DEFAULT 0,
            revenue decimal(10,2) DEFAULT 0.00,
            is_active tinyint(1) DEFAULT 1,
            PRIMARY KEY (id),
            UNIQUE KEY short_code (short_code),
            KEY utm_source (utm_source),
            KEY created_at (created_at)
        ) $charset_collate;";
        
        // Clicks table
        $sql .= "CREATE TABLE IF NOT EXISTS {$this->clicks_table} (
            id bigint(20) NOT NULL AUTO_INCREMENT,
            link_id bigint(20) NOT NULL,
            clicked_at datetime DEFAULT CURRENT_TIMESTAMP,
            click_time datetime DEFAULT CURRENT_TIMESTAMP,
            ip_address varchar(45) DEFAULT NULL,
            user_agent text DEFAULT NULL,
            referrer text DEFAULT NULL,
            device_type varchar(20) DEFAULT NULL,
            browser varchar(50) DEFAULT NULL,
            session_id varchar(64) DEFAULT NULL,
            converted tinyint(1) DEFAULT 0,
            order_id bigint(20) DEFAULT NULL,
            country_code varchar(2) DEFAULT NULL,
            PRIMARY KEY (id),
            KEY link_id (link_id),
            KEY clicked_at (clicked_at),
            KEY converted (converted),
            KEY session_id (session_id)
        ) $charset_collate;";
        
        require_once(ABSPATH . 'wp-admin/includes/upgrade.php');
        dbDelta($sql);
        
        // Log table creation
        // Debug logging removed for performance
    }
    
    /**
     * Track UTM link clicks - Primary method
     */
    public function track_utm_click() {
        if (!isset($_GET['sky_utm']) || empty($_GET['sky_utm'])) {
            return;
        }
        
        // Prevent multiple processing
        if (defined('SKY_UTM_PROCESSED')) {
            return;
        }
        define('SKY_UTM_PROCESSED', true);
        
        $short_code = sanitize_text_field($_GET['sky_utm']);
        $this->process_utm_click($short_code);
    }
    
    /**
     * Track UTM link clicks - Fallback method
     */
    public function track_utm_click_fallback() {
        if (!isset($_GET['sky_utm']) || empty($_GET['sky_utm'])) {
            return;
        }
        
        // Only process if not already processed
        if (!defined('SKY_UTM_PROCESSED')) {
            $short_code = sanitize_text_field($_GET['sky_utm']);
            $this->process_utm_click($short_code);
        }
    }
    
    /**
     * Process UTM click
     */
    private function process_utm_click($short_code) {
        // Debug log
        // Debug logging removed for performance
        
        $link = $this->get_link_by_short_code($short_code);
        
        if (!$link) {
            // Debug logging removed for performance
            wp_die('Invalid UTM link', 'Invalid Link', array('response' => 404));
            return;
        }
        
        // Check if this is a bot
        $is_bot = $this->is_bot_visit();
        
        // Check for recent clicks to prevent duplicates
        $recent_click = $this->check_recent_click($link->id);
        
        if (!$recent_click) {
            // Track the click
            $click_id = $this->record_click_fixed($link->id, $is_bot);
            
            if ($click_id) {
                // Debug logging removed for performance
                
                // Set tracking cookies (if not a bot)
                if (!$is_bot) {
                    $this->set_tracking_cookies($link, $click_id);
                }
            }
        } else {
            // Debug logging removed for performance
        }
        
        // Build destination URL with UTM parameters
        $destination = $this->build_utm_url($link);
        
        // Debug log
        // Debug logging removed for performance
        
        // Use JavaScript redirect as fallback for social media apps
        if ($this->is_social_media_app()) {
            $this->javascript_redirect($destination);
        } else {
            // Standard redirect
            wp_redirect($destination, 302);
        }
        exit;
    }
    
    /**
     * Check for recent clicks to prevent duplicates
     */
    private function check_recent_click($link_id) {
        global $wpdb;
        
        $ip_address = $this->get_visitor_ip();
        
        $recent_click = $wpdb->get_var($wpdb->prepare(
            "SELECT COUNT(*) FROM {$this->clicks_table} 
             WHERE link_id = %d 
             AND ip_address = %s 
             AND clicked_at > DATE_SUB(NOW(), INTERVAL 5 MINUTE)",
            $link_id,
            $ip_address
        ));
        
        return ($recent_click > 0);
    }
    
    /**
     * Record click details - FIXED VERSION
     */
    private function record_click_fixed($link_id, $is_bot = false) {
        global $wpdb;
        
        // Debug logging removed for performance
        // Debug logging removed for performance
        // Debug logging removed for performance
        
        // IMPORTANT: Verify the link exists before recording click
        $link_exists = $wpdb->get_var($wpdb->prepare(
            "SELECT COUNT(*) FROM {$this->table_name} WHERE id = %d AND is_active = 1",
            $link_id
        ));
        
        if (!$link_exists) {
            // Debug logging removed for performance
            return false;
        }
        
        // Get visitor info
        $user_agent = isset($_SERVER['HTTP_USER_AGENT']) ? sanitize_text_field($_SERVER['HTTP_USER_AGENT']) : 'Unknown';
        $user_agent = substr($user_agent, 0, 1000); // Limit for TEXT field
        
        $referrer = isset($_SERVER['HTTP_REFERER']) ? esc_url_raw($_SERVER['HTTP_REFERER']) : '';
        $referrer = substr($referrer, 0, 1000); // Limit for TEXT field
        
        $visitor_info = $this->get_visitor_info($user_agent);
        
        // Generate unique session ID that includes link ID for better isolation
        $session_id = $this->generate_unique_session_id($link_id);
        
        $visitor_data = array(
            'link_id' => intval($link_id), // Ensure it's an integer
            'clicked_at' => current_time('mysql'),
            'click_time' => current_time('mysql'),
            'ip_address' => $this->get_visitor_ip(),
            'user_agent' => $user_agent,
            'referrer' => $referrer,
            'device_type' => $visitor_info['device_type'],
            'browser' => $visitor_info['browser'],
            'session_id' => $session_id,
            'converted' => 0,
            'order_id' => null,
            'country_code' => $this->get_visitor_country()
        );
        
        // Insert click record
        $result = $wpdb->insert($this->clicks_table, $visitor_data);
        
        if ($result === false) {
            // Debug logging removed for performance
            // Debug logging removed for performance
            return false;
        }
        
        $click_id = $wpdb->insert_id;
        // Debug logging removed for performance
        
        // Update link click count (only non-bot clicks)
        if (!$is_bot) {
            $wpdb->query($wpdb->prepare(
                "UPDATE {$this->table_name} SET clicks = clicks + 1 WHERE id = %d",
                $link_id
            ));
        }
        
        return $click_id;
    }
    
    /**
     * Check if visitor is a bot
     */
    private function is_bot_visit() {
        $user_agent = isset($_SERVER['HTTP_USER_AGENT']) ? strtolower($_SERVER['HTTP_USER_AGENT']) : '';
        
        $bot_patterns = array(
            'googlebot', 'bingbot', 'yandexbot', 'baiduspider',
            'facebookexternalhit', 'twitterbot', 'linkedinbot',
            'whatsapp', 'telegrambot', 'bot', 'crawler', 'spider'
        );
        
        foreach ($bot_patterns as $pattern) {
            if (strpos($user_agent, $pattern) !== false) {
                return true;
            }
        }
        
        return empty($user_agent);
    }
    
    /**
     * Check if this is a social media in-app browser
     */
    private function is_social_media_app() {
        $user_agent = isset($_SERVER['HTTP_USER_AGENT']) ? $_SERVER['HTTP_USER_AGENT'] : '';
        
        // Facebook in-app browser
        if (strpos($user_agent, 'FBAN') !== false || strpos($user_agent, 'FBAV') !== false) {
            return true;
        }
        
        // Instagram in-app browser
        if (strpos($user_agent, 'Instagram') !== false) {
            return true;
        }
        
        // Twitter in-app browser
        if (strpos($user_agent, 'Twitter') !== false) {
            return true;
        }
        
        // LinkedIn in-app browser
        if (strpos($user_agent, 'LinkedIn') !== false) {
            return true;
        }
        
        return false;
    }
    
    /**
     * JavaScript redirect for social media apps
     */
    private function javascript_redirect($url) {
        ?>
        <!DOCTYPE html>
        <html>
        <head>
            <meta charset="UTF-8">
            <title>Redirecting...</title>
            <meta name="viewport" content="width=device-width, initial-scale=1">
            <script>
                function redirect() {
                    window.location.replace('<?php echo esc_js($url); ?>');
                    setTimeout(function() {
                        window.location.href = '<?php echo esc_js($url); ?>';
                    }, 100);
                }
                
                if (document.readyState === 'loading') {
                    document.addEventListener('DOMContentLoaded', redirect);
                } else {
                    redirect();
                }
                redirect();
            </script>
            <noscript>
                <meta http-equiv="refresh" content="0;url=<?php echo esc_attr($url); ?>">
            </noscript>
        </head>
        <body>
            <p>Redirecting... If you are not redirected, <a href="<?php echo esc_attr($url); ?>">click here</a>.</p>
        </body>
        </html>
        <?php
    }
    
    /**
     * Get link by short code
     */
    private function get_link_by_short_code($short_code) {
        global $wpdb;
        
        return $wpdb->get_row($wpdb->prepare(
            "SELECT * FROM {$this->table_name} WHERE short_code = %s AND is_active = 1",
            $short_code
        ));
    }
    
    /**
     * Build URL with UTM parameters - UPDATED to remove utm_content
     */
    private function build_utm_url($link) {
        $utm_params = array_filter(array(
            'utm_source' => $link->utm_source,
            'utm_medium' => $link->utm_medium,
            'utm_campaign' => $link->utm_campaign,
            'utm_term' => $link->utm_term
            // REMOVED: 'utm_content' => $link->utm_content
        ));
        
        // Add timestamp to prevent caching
        $utm_params['utm_timestamp'] = time();
        
        return add_query_arg($utm_params, $link->destination_url);
    }
    
    /**
     * Set tracking cookies - UPDATED to remove utm_content
     */
    private function set_tracking_cookies($link, $click_id) {
        $expire = time() + (30 * DAY_IN_SECONDS);
        $secure = is_ssl();
        
        // Get domain for cookie
        $domain = parse_url(home_url(), PHP_URL_HOST);
        $domain = '.' . preg_replace('/^www\./', '', $domain);
        
        // Set link tracking cookies
        $this->set_cookie('sky_utm_link_id', $link->id, $expire, $domain, $secure);
        $this->set_cookie('sky_utm_click_id', $click_id, $expire, $domain, $secure);
        
        // Set UTM parameter cookies - REMOVED utm_content
        $utm_params = array(
            'utm_source' => $link->utm_source,
            'utm_medium' => $link->utm_medium,
            'utm_campaign' => $link->utm_campaign,
            'utm_term' => $link->utm_term
            // REMOVED: 'utm_content' => $link->utm_content
        );
        
        foreach ($utm_params as $key => $value) {
            if (!empty($value)) {
                $this->set_cookie('sky_' . $key, $value, $expire, $domain, $secure);
            }
        }
        
        // Also store in session for backup
        if (!session_id()) {
            session_start();
        }
        $_SESSION['sky_utm_data'] = array(
            'link_id' => $link->id,
            'click_id' => $click_id,
            'utm_params' => $utm_params,
            'timestamp' => time()
        );
        
        // Debug logging removed for performance
    }
    
    /**
     * Set cookie with compatibility
     */
    private function set_cookie($name, $value, $expire, $domain, $secure) {
        try {
            setcookie($name, $value, $expire, '/', $domain, $secure, false);
            $_COOKIE[$name] = $value; // Set immediately for this request
        } catch (Exception $e) {
            // Debug logging removed for performance
        }
    }
    
    /**
     * Get visitor IP address
     */
    private function get_visitor_ip() {
        $ip_keys = array(
            'HTTP_CF_CONNECTING_IP', // Cloudflare
            'HTTP_CLIENT_IP',
            'HTTP_X_FORWARDED_FOR',
            'HTTP_X_FORWARDED',
            'HTTP_X_CLUSTER_CLIENT_IP',
            'HTTP_FORWARDED_FOR',
            'HTTP_FORWARDED',
            'REMOTE_ADDR'
        );
        
        foreach ($ip_keys as $key) {
            if (array_key_exists($key, $_SERVER) === true) {
                foreach (explode(',', $_SERVER[$key]) as $ip) {
                    $ip = trim($ip);
                    
                    if (filter_var($ip, FILTER_VALIDATE_IP, 
                        FILTER_FLAG_NO_PRIV_RANGE | FILTER_FLAG_NO_RES_RANGE) !== false) {
                        return $ip;
                    }
                }
            }
        }
        
        return isset($_SERVER['REMOTE_ADDR']) ? $_SERVER['REMOTE_ADDR'] : '127.0.0.1';
    }
    
    /**
     * Get visitor information from user agent
     */
    private function get_visitor_info($user_agent) {
        $info = array(
            'device_type' => 'desktop',
            'browser' => 'Other'
        );
        
        $user_agent_lower = strtolower($user_agent);
        
        // Detect device type
        if (strpos($user_agent_lower, 'mobile') !== false || 
            strpos($user_agent_lower, 'android') !== false || 
            strpos($user_agent_lower, 'iphone') !== false) {
            $info['device_type'] = 'mobile';
        } elseif (strpos($user_agent_lower, 'tablet') !== false || 
                  strpos($user_agent_lower, 'ipad') !== false) {
            $info['device_type'] = 'tablet';
        }
        
        // Detect browser
        if (strpos($user_agent_lower, 'chrome') !== false && strpos($user_agent_lower, 'edg') === false) {
            $info['browser'] = 'Chrome';
        } elseif (strpos($user_agent_lower, 'safari') !== false && strpos($user_agent_lower, 'chrome') === false) {
            $info['browser'] = 'Safari';
        } elseif (strpos($user_agent_lower, 'firefox') !== false) {
            $info['browser'] = 'Firefox';
        } elseif (strpos($user_agent_lower, 'edg') !== false) {
            $info['browser'] = 'Edge';
        } elseif (strpos($user_agent_lower, 'fban') !== false || strpos($user_agent_lower, 'fbav') !== false) {
            $info['browser'] = 'Facebook';
        }
        
        return $info;
    }
    
    /**
     * Get visitor country
     */
    private function get_visitor_country() {
        // Try to get country from Cloudflare header first
        if (isset($_SERVER['HTTP_CF_IPCOUNTRY'])) {
            return substr($_SERVER['HTTP_CF_IPCOUNTRY'], 0, 2);
        }
        
        return null;
    }
    
    /**
     * Generate unique session ID
     */
    private function generate_session_id() {
        // Check if we already have a session ID in cookie
        if (isset($_COOKIE['sky_utm_session_id'])) {
            return sanitize_text_field($_COOKIE['sky_utm_session_id']);
        }
        
        // Generate new session ID
        $session_id = wp_generate_password(32, false);
        
        return $session_id;
    }
    
    /**
     * Save UTM data to order - UPDATED to remove utm_content
     */
    public function save_utm_data_to_order($order, $data) {
        // Start session if needed
        if (!session_id()) {
            session_start();
        }
        
        // Check cookies first
        $utm_source = isset($_COOKIE['sky_utm_source']) ? sanitize_text_field($_COOKIE['sky_utm_source']) : '';
        $utm_medium = isset($_COOKIE['sky_utm_medium']) ? sanitize_text_field($_COOKIE['sky_utm_medium']) : '';
        $utm_campaign = isset($_COOKIE['sky_utm_campaign']) ? sanitize_text_field($_COOKIE['sky_utm_campaign']) : '';
        $utm_term = isset($_COOKIE['sky_utm_term']) ? sanitize_text_field($_COOKIE['sky_utm_term']) : '';
        // REMOVED: $utm_content = isset($_COOKIE['sky_utm_content']) ? sanitize_text_field($_COOKIE['sky_utm_content']) : '';
        $link_id = isset($_COOKIE['sky_utm_link_id']) ? intval($_COOKIE['sky_utm_link_id']) : 0;
        
        // Fallback to session
        if (empty($utm_source) && !empty($_SESSION['sky_utm_data'])) {
            $session_data = $_SESSION['sky_utm_data'];
            $utm_source = $session_data['utm_params']['utm_source'] ?? '';
            $utm_medium = $session_data['utm_params']['utm_medium'] ?? '';
            $utm_campaign = $session_data['utm_params']['utm_campaign'] ?? '';
            $utm_term = $session_data['utm_params']['utm_term'] ?? '';
            // REMOVED: $utm_content = $session_data['utm_params']['utm_content'] ?? '';
            $link_id = $session_data['link_id'] ?? 0;
        }
        
        // Save to order meta
        if ($utm_source) {
            $order->update_meta_data('_utm_source', $utm_source);
        }
        if ($utm_medium) {
            $order->update_meta_data('_utm_medium', $utm_medium);
        }
        if ($utm_campaign) {
            $order->update_meta_data('_utm_campaign', $utm_campaign);
        }
        if ($utm_term) {
            $order->update_meta_data('_utm_term', $utm_term);
        }
        // REMOVED: utm_content saving
        if ($link_id) {
            $order->update_meta_data('_sky_utm_link_id', $link_id);
        }
        
        // Debug log
        // Debug logging removed for performance
    }
    
    /**
     * Track UTM conversion
     */
    public function track_utm_conversion($order_id) {
        $this->process_utm_conversion($order_id);
    }
    
    /**
     * Track delayed UTM conversion
     */
    public function track_utm_conversion_delayed($order_id) {
        $order = wc_get_order($order_id);
        if (!$order) {
            return;
        }
        
        // Check if already tracked
        if ($order->get_meta('_sky_utm_conversion_tracked')) {
            return;
        }
        
        // Process conversion
        $this->process_utm_conversion($order_id);
    }
    
    /**
     * Process UTM conversion
     */
    private function process_utm_conversion($order_id) {
        $order = wc_get_order($order_id);
        if (!$order) {
            return;
        }
        
        global $wpdb;
        
        // Debug logging removed for performance
        // Debug logging removed for performance
        
        // Get link ID from order or cookies/session
        $link_id = $order->get_meta('_sky_utm_link_id');
        
        if (!$link_id) {
            $link_id = $this->get_conversion_link_id($order);
        }
        
        if (!$link_id) {
            // Debug logging removed for performance
            return;
        }
        
        // Mark order as tracked
        $order->update_meta_data('_sky_utm_conversion_tracked', true);
        $order->update_meta_data('_sky_utm_link_id', $link_id);
        $order->save();
        
        // Update click as converted
        $session_id = isset($_COOKIE['sky_utm_session_id']) ? sanitize_text_field($_COOKIE['sky_utm_session_id']) : '';
        $click_id = isset($_COOKIE['sky_utm_click_id']) ? intval($_COOKIE['sky_utm_click_id']) : 0;
        
        if ($click_id && $link_id) {
            $conversion_update = $wpdb->update(
                $this->clicks_table,
                array('converted' => 1, 'order_id' => $order_id),
                array('id' => $click_id, 'link_id' => $link_id),
                array('%d', '%d'),
                array('%d', '%d')
            );
            
            if ($conversion_update !== false) {
                // Debug logging removed for performance
            }
        } elseif ($session_id && $link_id) {
            $conversion_update = $wpdb->update(
                $this->clicks_table,
                array('converted' => 1, 'order_id' => $order_id),
                array('session_id' => $session_id, 'link_id' => $link_id),
                array('%d', '%d'),
                array('%s', '%d')
            );
        }
        
        // Update link stats
        $order_total = $order->get_total();
        $wpdb->query($wpdb->prepare(
            "UPDATE {$this->table_name} 
            SET conversions = conversions + 1, revenue = revenue + %f 
            WHERE id = %d",
            $order_total,
            $link_id
        ));
        
        // Clear tracking cookies
        $this->clear_utm_cookies();
        
        // Debug log
        // Debug logging removed for performance
    }
    
    /**
     * Get conversion link ID
     */
    private function get_conversion_link_id($order) {
        global $wpdb;
        
        // Try cookie first
        $link_id = isset($_COOKIE['sky_utm_link_id']) ? intval($_COOKIE['sky_utm_link_id']) : 0;
        
        // Try session
        if (!$link_id && !empty($_SESSION['sky_utm_data'])) {
            $link_id = $_SESSION['sky_utm_data']['link_id'] ?? 0;
        }
        
        // Fallback to order meta UTM source
        if (!$link_id) {
            $utm_source = $order->get_meta('_utm_source');
            $utm_campaign = $order->get_meta('_utm_campaign');
            
            if ($utm_source) {
                $query = "SELECT id FROM {$this->table_name} WHERE utm_source = %s AND is_active = 1";
                $params = array($utm_source);
                
                if ($utm_campaign) {
                    $query .= " AND utm_campaign = %s";
                    $params[] = $utm_campaign;
                }
                
                $query .= " ORDER BY created_at DESC LIMIT 1";
                
                $link = $wpdb->get_row($wpdb->prepare($query, $params));
                
                if ($link) {
                    $link_id = $link->id;
                }
            }
        }
        
        return $link_id;
    }
    
    /**
     * Clear UTM tracking cookies - UPDATED to remove utm_content
     */
    private function clear_utm_cookies() {
        $cookies = array(
            'sky_utm_link_id', 'sky_utm_click_id',
            'sky_utm_source', 'sky_utm_medium', 
            'sky_utm_campaign', 'sky_utm_term',
            // REMOVED: 'sky_utm_content',
            'sky_utm_session_id'
        );
        
        $domain = '.' . preg_replace('/^www\./', '', parse_url(home_url(), PHP_URL_HOST));
        
        foreach ($cookies as $cookie) {
            // Clear with domain
            setcookie($cookie, '', time() - 3600, '/', $domain);
            // Clear without domain
            setcookie($cookie, '', time() - 3600, '/');
            // Remove from $_COOKIE
            unset($_COOKIE[$cookie]);
        }
        
        // Clear session data
        if (isset($_SESSION['sky_utm_data'])) {
            unset($_SESSION['sky_utm_data']);
        }
    }
    
    /**
     * AJAX: Create UTM link - UPDATED to remove utm_content
     */
    public function ajax_create_utm_link() {
        // Security check
        if (!$this->verify_ajax_request()) {
            wp_send_json_error('Security check failed.', 403);
            return;
        }
        
        // Validate inputs
        $validated_data = $this->validate_utm_link_data($_POST);
        if (is_wp_error($validated_data)) {
            wp_send_json_error($validated_data->get_error_message(), 400);
            return;
        }
        
        // Additional domain validation
        $destination_url = $validated_data['destination'];
        $site_url = get_site_url();
        $site_domain = parse_url($site_url, PHP_URL_HOST);
        $destination_domain = parse_url($destination_url, PHP_URL_HOST);
        
        // Check if destination domain matches site domain
        if ($destination_domain !== $site_domain && 
            $destination_domain !== 'www.' . $site_domain && 
            $site_domain !== 'www.' . $destination_domain) {
            wp_send_json_error('Destination URL must be from this website (' . $site_domain . ')', 400);
            return;
        }
        
        // Generate unique short code
        $short_code = $this->generate_short_code();
        
        // Insert into database - REMOVED utm_content
        global $wpdb;
        $result = $wpdb->insert($this->table_name, array(
            'short_code' => $short_code,
            'destination_url' => $validated_data['destination'],
            'utm_source' => $validated_data['utm_source'],
            'utm_medium' => $validated_data['utm_medium'],
            'utm_campaign' => $validated_data['utm_campaign'],
            'utm_term' => $validated_data['utm_term'],
            'utm_content' => null, // Set as null
            'created_by' => get_current_user_id(),
            'created_at' => current_time('mysql'),
            'is_active' => 1
        ));
        
        if ($result === false) {
            wp_send_json_error('Failed to create UTM link.', 500);
            return;
        }
        
        $link_id = $wpdb->insert_id;
        $tracking_url = home_url('?sky_utm=' . $short_code);
        
        wp_send_json_success(array(
            'id' => $link_id,
            'tracking_url' => $tracking_url,
            'short_code' => $short_code
        ));
    }
    
    /**
     * AJAX: Update UTM link
     */
    public function ajax_update_utm_link() {
        // Security check
        if (!$this->verify_ajax_request()) {
            wp_send_json_error('Security check failed.', 403);
            return;
        }
        
        // Get link ID
        $link_id = isset($_POST['id']) ? intval($_POST['id']) : 0;
        if (!$link_id) {
            wp_send_json_error('Invalid link ID.', 400);
            return;
        }
        
        // Validate inputs
        $validated_data = $this->validate_utm_link_data($_POST);
        if (is_wp_error($validated_data)) {
            wp_send_json_error($validated_data->get_error_message(), 400);
            return;
        }
        
        // Additional domain validation
        $destination_url = $validated_data['destination'];
        $site_url = get_site_url();
        $site_domain = parse_url($site_url, PHP_URL_HOST);
        $destination_domain = parse_url($destination_url, PHP_URL_HOST);
        
        // Check if destination domain matches site domain
        if ($destination_domain !== $site_domain && 
            $destination_domain !== 'www.' . $site_domain && 
            $site_domain !== 'www.' . $destination_domain) {
            wp_send_json_error('Destination URL must be from this website (' . $site_domain . ')', 400);
            return;
        }
        
        // Update in database
        global $wpdb;
        $result = $wpdb->update(
            $this->table_name,
            array(
                'destination_url' => $validated_data['destination'],
                'utm_source' => $validated_data['utm_source'],
                'utm_medium' => $validated_data['utm_medium'],
                'utm_campaign' => $validated_data['utm_campaign'],
                'utm_term' => $validated_data['utm_term']
            ),
            array('id' => $link_id),
            array('%s', '%s', '%s', '%s', '%s'),
            array('%d')
        );
        
        if ($result === false) {
            wp_send_json_error('Failed to update UTM link.', 500);
            return;
        }
        
        // Get updated link data
        $updated_link = $wpdb->get_row($wpdb->prepare(
            "SELECT * FROM {$this->table_name} WHERE id = %d",
            $link_id
        ), ARRAY_A);
        
        if ($updated_link) {
            $updated_link['tracking_url'] = home_url('?sky_utm=' . $updated_link['short_code']);
        }
        
        wp_send_json_success(array(
            'message' => 'UTM link updated successfully',
            'link' => $updated_link
        ));
    }
    
    /**
     * AJAX: Get UTM links - MODIFIED TO REMOVE DATE FILTERING
     */
    public function ajax_get_utm_links() {
        if (!$this->verify_ajax_request()) {
            wp_send_json_error('Security check failed.', 403);
            return;
        }
        
        // Get filters (removed date range)
        $filters = array(
            'utm_source' => isset($_POST['utm_source']) ? sanitize_text_field($_POST['utm_source']) : '',
            'utm_campaign' => isset($_POST['utm_campaign']) ? sanitize_text_field($_POST['utm_campaign']) : ''
        );
        
        // Get pagination parameters
        $page = isset($_POST['page']) ? max(1, intval($_POST['page'])) : 1;
        $per_page = isset($_POST['per_page']) ? intval($_POST['per_page']) : 20;
        $offset = ($page - 1) * $per_page;
        
        // Get total count first
        $total = $this->get_links_count_no_date($filters);
        
        // Get links with ALL stats (no date filtering)
        $links = $this->get_links_with_all_stats($filters, $per_page, $offset);
        
        // Get totals for summary cards
        $totals = $this->get_total_statistics();
        
        wp_send_json_success(array(
            'links' => $links,
            'total' => $total,
            'page' => $page,
            'per_page' => $per_page,
            'totals' => $totals // ADD THIS
        ));
    }
    
    /**
     * Get total count of links - WITHOUT DATE FILTERING
     */
    private function get_links_count_no_date($filters) {
        global $wpdb;
        
        // Build filter conditions
        $where_conditions = "WHERE l.is_active = 1";
        
        if (!empty($filters['utm_source'])) {
            $where_conditions .= $wpdb->prepare(" AND l.utm_source = %s", $filters['utm_source']);
        }
        
        if (!empty($filters['utm_campaign'])) {
            $where_conditions .= $wpdb->prepare(" AND l.utm_campaign = %s", $filters['utm_campaign']);
        }
        
        $query = "SELECT COUNT(DISTINCT l.id) as total FROM {$this->table_name} l {$where_conditions}";
        
        return (int) $wpdb->get_var($query);
    }
    
    /**
     * Get links with ALL statistics - no date filtering
     * NEW METHOD - Shows all historical data
     */
    private function get_links_with_all_stats($filters, $limit = null, $offset = 0) {
        global $wpdb;
        
        // Build filter conditions
        $where_conditions = "WHERE l.is_active = 1";
        
        if (!empty($filters['utm_source'])) {
            $where_conditions .= $wpdb->prepare(" AND l.utm_source = %s", $filters['utm_source']);
        }
        
        if (!empty($filters['utm_campaign'])) {
            $where_conditions .= $wpdb->prepare(" AND l.utm_campaign = %s", $filters['utm_campaign']);
        }
        
        // Add LIMIT and OFFSET
        $limit_clause = '';
        if ($limit !== null) {
            $limit_clause = $wpdb->prepare(" LIMIT %d OFFSET %d", $limit, $offset);
        }
        
        // Query that gets ALL stats without date filtering
        $query = "
            SELECT l.*, 
                   COUNT(DISTINCT c.id) as total_clicks,
                   COUNT(DISTINCT CASE WHEN c.converted = 1 THEN c.id END) as total_conversions,
                   COALESCE(SUM(CASE WHEN c.converted = 1 THEN o.meta_value END), 0) as total_revenue
            FROM {$this->table_name} l
            LEFT JOIN {$this->clicks_table} c ON l.id = c.link_id
            LEFT JOIN {$wpdb->postmeta} o ON c.order_id = o.post_id AND o.meta_key = '_order_total'
            {$where_conditions}
            GROUP BY l.id
            ORDER BY l.created_at DESC
            {$limit_clause}
        ";
        
        $links = $wpdb->get_results($query, ARRAY_A);
        
        // Process links
        foreach ($links as &$link) {
            $link['tracking_url'] = home_url('?sky_utm=' . $link['short_code']);
            
            // Use calculated totals as the main display stats
            $link['clicks'] = intval($link['total_clicks']);
            $link['conversions'] = intval($link['total_conversions']);
            $link['revenue'] = floatval($link['total_revenue']);
            
            // Calculate conversion rate
            $link['conversion_rate'] = $link['clicks'] > 0 
                ? round(($link['conversions'] / $link['clicks']) * 100, 2) 
                : 0;
                
            // Keep these for backward compatibility
            $link['period_clicks'] = $link['clicks'];
            $link['period_conversions'] = $link['conversions'];
            $link['period_revenue'] = $link['revenue'];
        }
        
        return $links;
    }
    
    /**
     * AJAX: Delete UTM link
     */
    public function ajax_delete_utm_link() {
        if (!$this->verify_ajax_request()) {
            wp_send_json_error('Security check failed.', 403);
            return;
        }
        
        $link_id = isset($_POST['link_id']) ? intval($_POST['link_id']) : 0;
        
        if (!$link_id) {
            wp_send_json_error('Invalid link ID.', 400);
            return;
        }
        
        global $wpdb;
        
        // Soft delete
        $result = $wpdb->update(
            $this->table_name,
            array('is_active' => 0),
            array('id' => $link_id),
            array('%d'),
            array('%d')
        );
        
        if ($result === false) {
            wp_send_json_error('Failed to delete link.', 500);
            return;
        }
        
        wp_send_json_success(array('message' => 'Link deleted successfully.'));
    }
    
    /**
     * AJAX: Bulk delete UTM links
     * Handles deletion of multiple selected links at once
     */
    public function ajax_bulk_delete_utm_links() {
        // Verify security
        if (!$this->verify_ajax_request()) {
            wp_send_json_error('Security check failed.', 403);
            return;
        }
        
        // Get link IDs from POST request
        $link_ids = isset($_POST['link_ids']) ? $_POST['link_ids'] : array();
        
        // Validate link IDs
        if (empty($link_ids) || !is_array($link_ids)) {
            wp_send_json_error('No links selected.', 400);
            return;
        }
        
        // Sanitize link IDs - ensure all are positive integers
        $link_ids = array_map('intval', $link_ids);
        $link_ids = array_filter($link_ids, function($id) {
            return $id > 0;
        });
        
        if (empty($link_ids)) {
            wp_send_json_error('Invalid link IDs.', 400);
            return;
        }
        
        global $wpdb;
        
        // Prepare placeholders for SQL IN clause
        $placeholders = implode(',', array_fill(0, count($link_ids), '%d'));
        
        // Soft delete multiple links (set is_active = 0 instead of deleting)
        $sql = $wpdb->prepare(
            "UPDATE {$this->table_name} 
             SET is_active = 0 
             WHERE id IN ($placeholders)",
            $link_ids
        );
        
        $result = $wpdb->query($sql);
        
        if ($result === false) {
            wp_send_json_error('Failed to delete links. Database error.', 500);
            return;
        }
        
        // Optional: Delete associated clicks data (uncomment if you want to delete click history too)
        /*
        $sql_clicks = $wpdb->prepare(
            "DELETE FROM {$this->clicks_table} 
             WHERE link_id IN ($placeholders)",
            $link_ids
        );
        $wpdb->query($sql_clicks);
        */
        
        // Log the bulk deletion if debug mode is enabled
        if (defined('WP_DEBUG') && WP_DEBUG) {
            error_log('UTM Bulk Delete: Deleted ' . $result . ' links with IDs: ' . implode(', ', $link_ids));
        }
        
        // Return success response with details
        wp_send_json_success(array(
            'message' => sprintf('Successfully deleted %d link(s).', $result),
            'deleted_count' => $result,
            'deleted_ids' => $link_ids
        ));
    }
    
    /**
     * AJAX: Get UTM analytics - UPDATED to include totals
     */
    public function ajax_get_utm_analytics() {
        if (!$this->verify_ajax_request()) {
            wp_send_json_error('Security check failed.', 403);
            return;
        }
        
        $date_range = isset($_POST['date_range']) ? sanitize_text_field($_POST['date_range']) : 'last7days';
        $dates = SkyInsightsUtils::calculate_date_range(
            $date_range,
            $_POST['date_from'] ?? '',
            $_POST['date_to'] ?? ''
        );
        
        $analytics = array(
            'source_performance' => $this->get_source_performance($dates),
            'daily_trends' => $this->get_daily_trends($dates),
            'device_breakdown' => $this->get_device_breakdown($dates),
            'totals' => $this->get_total_statistics() // ADD THIS LINE
        );
        
        wp_send_json_success($analytics);
    }
    
    /**
     * AJAX: Get UTM click details - FIXED VERSION
     */
    public function ajax_get_utm_click_details() {
        // Verify nonce
        if (!isset($_POST['nonce']) || !wp_verify_nonce($_POST['nonce'], 'sky_insights_nonce')) {
            wp_send_json_error('Security check failed.', 403);
            return;
        }
        
        // Check permissions
        if (!current_user_can('manage_options')) {
            wp_send_json_error('Insufficient permissions.', 403);
            return;
        }
        
        $link_id = isset($_POST['link_id']) ? intval($_POST['link_id']) : 0;
        
        if (!$link_id) {
            wp_send_json_error('Invalid link ID.', 400);
            return;
        }
        
        global $wpdb;
        
        // IMPORTANT: First verify this link exists and belongs to the current site
        $link_exists = $wpdb->get_var($wpdb->prepare(
            "SELECT COUNT(*) FROM {$this->table_name} WHERE id = %d AND is_active = 1",
            $link_id
        ));
        
        if (!$link_exists) {
            wp_send_json_error('Link not found.', 404);
            return;
        }
        
        // Get click details for this SPECIFIC link only
        $clicks = $wpdb->get_results($wpdb->prepare("
            SELECT 
                c.*,
                DATE_FORMAT(c.clicked_at, '%%Y-%%m-%%d %%H:%%i:%%s') as formatted_date
            FROM {$this->clicks_table} c
            WHERE c.link_id = %d
            ORDER BY c.clicked_at DESC
            LIMIT 100
        ", $link_id), ARRAY_A);
        
        // If no clicks found, return empty array
        if (empty($clicks)) {
            wp_send_json_success(array());
            return;
        }
        
        // Process clicks data to add more information
        foreach ($clicks as &$click) {
            // Verify each click belongs to the requested link
            if ($click['link_id'] != $link_id) {
                continue; // Skip any clicks that don't match
            }
            
            // Parse referrer for source
            if (!empty($click['referrer'])) {
                $parsed_ref = parse_url($click['referrer']);
                $click['referrer_source'] = isset($parsed_ref['host']) ? $parsed_ref['host'] : 'Direct';
                
                // Clean up common referrers
                $click['referrer_source'] = str_replace('www.', '', $click['referrer_source']);
                
                // Identify social sources
                if (strpos($click['referrer_source'], 'facebook.com') !== false || strpos($click['referrer_source'], 'fb.com') !== false) {
                    $click['referrer_source'] = 'Facebook';
                } elseif (strpos($click['referrer_source'], 'instagram.com') !== false) {
                    $click['referrer_source'] = 'Instagram';
                } elseif (strpos($click['referrer_source'], 't.co') !== false || strpos($click['referrer_source'], 'twitter.com') !== false) {
                    $click['referrer_source'] = 'X (Twitter)';
                } elseif (strpos($click['referrer_source'], 'linkedin.com') !== false) {
                    $click['referrer_source'] = 'LinkedIn';
                } elseif (strpos($click['referrer_source'], 'google.') !== false) {
                    $click['referrer_source'] = 'Google';
                }
            } else {
                $click['referrer_source'] = 'Direct';
            }
            
            // Format device info
            $click['device_display'] = ucfirst($click['device_type'] ?: 'Unknown');
            
            // Get country name from country code
            if ($click['country_code']) {
                $countries = array(
                    'US' => 'United States',
                    'GB' => 'United Kingdom',
                    'CA' => 'Canada',
                    'AU' => 'Australia',
                    'DE' => 'Germany',
                    'FR' => 'France',
                    'ES' => 'Spain',
                    'IT' => 'Italy',
                    'NL' => 'Netherlands',
                    'BR' => 'Brazil',
                    'IN' => 'India',
                    'JP' => 'Japan',
                    'CN' => 'China',
                    'KR' => 'South Korea',
                    'MX' => 'Mexico',
                    'AR' => 'Argentina',
                    'ZA' => 'South Africa',
                    'NZ' => 'New Zealand',
                    'IE' => 'Ireland',
                    'SE' => 'Sweden',
                    'NO' => 'Norway',
                    'DK' => 'Denmark',
                    'FI' => 'Finland',
                    'PL' => 'Poland',
                    'RU' => 'Russia',
                    'TR' => 'Turkey',
                    'SA' => 'Saudi Arabia',
                    'AE' => 'United Arab Emirates',
                    'EG' => 'Egypt',
                    'NG' => 'Nigeria',
                    'KE' => 'Kenya',
                    'SG' => 'Singapore',
                    'MY' => 'Malaysia',
                    'TH' => 'Thailand',
                    'ID' => 'Indonesia',
                    'PH' => 'Philippines',
                    'VN' => 'Vietnam',
                    'PK' => 'Pakistan',
                    'BD' => 'Bangladesh'
                );
                
                // FIX: Show "Others" for unlisted countries instead of the country code
                $click['country'] = isset($countries[$click['country_code']]) ? $countries[$click['country_code']] : 'Others';
            } else {
                $click['country'] = 'Unknown';
            }
            
            // Add city info if available (you can extend this)
            $click['city'] = '';
            
            // Format browser
            $click['browser'] = $click['browser'] ?: 'Unknown';
            
            // Add placeholder for device brand/model (you can extend this with device detection)
            $click['device_brand'] = '';
            $click['device_model'] = '';
        }
        
        // Final verification - remove any clicks that don't belong to this link
        $clicks = array_filter($clicks, function($click) use ($link_id) {
            return isset($click['link_id']) && $click['link_id'] == $link_id;
        });
        
        // Re-index array after filtering
        $clicks = array_values($clicks);
        
        wp_send_json_success($clicks);
    }
    
    /**
     * Get links with stats for date range - KEPT FOR ANALYTICS ONLY
     */
    private function get_links_with_stats($dates, $filters, $limit = null, $offset = 0) {
        global $wpdb;
        
        $date_conditions = SkyInsightsUtils::get_date_conditions($dates);
        
        // Build filter conditions
        $where_conditions = "WHERE l.is_active = 1";
        
        if (!empty($filters['utm_source'])) {
            $where_conditions .= $wpdb->prepare(" AND l.utm_source = %s", $filters['utm_source']);
        }
        
        if (!empty($filters['utm_campaign'])) {
            $where_conditions .= $wpdb->prepare(" AND l.utm_campaign = %s", $filters['utm_campaign']);
        }
        
        // Add LIMIT and OFFSET
        $limit_clause = '';
        if ($limit !== null) {
            $limit_clause = $wpdb->prepare(" LIMIT %d OFFSET %d", $limit, $offset);
        }
        
        $query = $wpdb->prepare("
            SELECT l.*, 
                   COUNT(DISTINCT c.id) as period_clicks,
                   COUNT(DISTINCT CASE WHEN c.converted = 1 THEN c.id END) as period_conversions,
                   COALESCE(SUM(CASE WHEN c.converted = 1 THEN o.meta_value END), 0) as period_revenue
            FROM {$this->table_name} l
            LEFT JOIN {$this->clicks_table} c ON l.id = c.link_id 
                AND c.clicked_at >= %s AND c.clicked_at <= %s
            LEFT JOIN {$wpdb->postmeta} o ON c.order_id = o.post_id AND o.meta_key = '_order_total'
            {$where_conditions}
            GROUP BY l.id
            ORDER BY l.created_at DESC
            {$limit_clause}
        ", $date_conditions['start'], $date_conditions['end']);
        
        $links = $wpdb->get_results($query, ARRAY_A);
        
        // Process links
        foreach ($links as &$link) {
            $link['tracking_url'] = home_url('?sky_utm=' . $link['short_code']);
            $link['conversion_rate'] = $link['period_clicks'] > 0 
                ? round(($link['period_conversions'] / $link['period_clicks']) * 100, 2) 
                : 0;
        }
        
        return $links;
    }
    
    /**
     * Get total count of links - KEPT FOR COMPATIBILITY
     */
    private function get_links_count($dates, $filters) {
        global $wpdb;
        
        // Build filter conditions
        $where_conditions = "WHERE l.is_active = 1";
        
        if (!empty($filters['utm_source'])) {
            $where_conditions .= $wpdb->prepare(" AND l.utm_source = %s", $filters['utm_source']);
        }
        
        if (!empty($filters['utm_campaign'])) {
            $where_conditions .= $wpdb->prepare(" AND l.utm_campaign = %s", $filters['utm_campaign']);
        }
        
        $query = "SELECT COUNT(DISTINCT l.id) as total FROM {$this->table_name} l {$where_conditions}";
        
        return (int) $wpdb->get_var($query);
    }
    
    /**
     * Get performance by source
     */
    private function get_source_performance($dates) {
        global $wpdb;
        
        $date_conditions = SkyInsightsUtils::get_date_conditions($dates);
        
        return $wpdb->get_results($wpdb->prepare("
            SELECT 
                l.utm_source,
                COUNT(DISTINCT c.id) as clicks,
                COUNT(DISTINCT CASE WHEN c.converted = 1 THEN c.id END) as conversions,
                COALESCE(SUM(CASE WHEN c.converted = 1 THEN o.meta_value END), 0) as revenue
            FROM {$this->table_name} l
            INNER JOIN {$this->clicks_table} c ON l.id = c.link_id
            LEFT JOIN {$wpdb->postmeta} o ON c.order_id = o.post_id AND o.meta_key = '_order_total'
            WHERE c.clicked_at >= %s AND c.clicked_at <= %s
            AND l.is_active = 1
            GROUP BY l.utm_source
            ORDER BY clicks DESC
        ", $date_conditions['start'], $date_conditions['end']), ARRAY_A);
    }
    
    /**
     * Get daily trends
     */
    private function get_daily_trends($dates) {
        global $wpdb;
        
        $date_conditions = SkyInsightsUtils::get_date_conditions($dates);
        
        return $wpdb->get_results($wpdb->prepare("
            SELECT 
                DATE(c.clicked_at) as date,
                COUNT(DISTINCT c.id) as clicks,
                COUNT(DISTINCT CASE WHEN c.converted = 1 THEN c.id END) as conversions
            FROM {$this->clicks_table} c
            INNER JOIN {$this->table_name} l ON c.link_id = l.id
            WHERE c.clicked_at >= %s AND c.clicked_at <= %s
            AND l.is_active = 1
            GROUP BY DATE(c.clicked_at)
            ORDER BY date ASC
        ", $date_conditions['start'], $date_conditions['end']), ARRAY_A);
    }
    
    /**
     * Get device breakdown
     */
    private function get_device_breakdown($dates) {
        global $wpdb;
        
        $date_conditions = SkyInsightsUtils::get_date_conditions($dates);
        
        return $wpdb->get_results($wpdb->prepare("
            SELECT 
                device_type,
                COUNT(*) as clicks,
                COUNT(CASE WHEN converted = 1 THEN 1 END) as conversions
            FROM {$this->clicks_table} c
            INNER JOIN {$this->table_name} l ON c.link_id = l.id
            WHERE c.clicked_at >= %s AND c.clicked_at <= %s
            AND l.is_active = 1
            GROUP BY device_type
        ", $date_conditions['start'], $date_conditions['end']), ARRAY_A);
    }
    
    /**
     * Verify AJAX request
     */
    private function verify_ajax_request() {
        // Check nonce
        if (!isset($_POST['nonce']) || !wp_verify_nonce($_POST['nonce'], 'sky_insights_nonce')) {
            return false;
        }
        
        // Check permissions
        if (!current_user_can('manage_woocommerce')) {
            return false;
        }
        
        return true;
    }
    
    /**
     * Validate UTM link data - UPDATED to remove utm_content
     */
    private function validate_utm_link_data($data) {
        $destination = isset($data['destination']) ? esc_url_raw($data['destination']) : '';
        $utm_source = isset($data['utm_source']) ? sanitize_text_field($data['utm_source']) : '';
        
        // Required fields
        if (empty($destination)) {
            return new WP_Error('missing_destination', 'Destination URL is required');
        }
        
        if (empty($utm_source)) {
            return new WP_Error('missing_utm_source', 'UTM Source is required');
        }
        
        // Validate URL
        if (!filter_var($destination, FILTER_VALIDATE_URL)) {
            // Try adding https://
            $destination_with_protocol = 'https://' . $destination;
            if (!filter_var($destination_with_protocol, FILTER_VALIDATE_URL)) {
                return new WP_Error('invalid_url', 'Please enter a valid URL');
            }
            $destination = $destination_with_protocol;
        }
        
        return array(
            'destination' => $destination,
            'utm_source' => $utm_source,
            'utm_medium' => isset($data['utm_medium']) ? sanitize_text_field($data['utm_medium']) : '',
            'utm_campaign' => isset($data['utm_campaign']) ? sanitize_text_field($data['utm_campaign']) : '',
            'utm_term' => isset($data['utm_term']) ? sanitize_text_field($data['utm_term']) : ''
            // REMOVED: 'utm_content' => isset($data['utm_content']) ? sanitize_text_field($data['utm_content']) : ''
        );
    }
    
    /**
     * Generate unique short code
     */
    private function generate_short_code() {
        $characters = '0123456789abcdefghijklmnopqrstuvwxyz';
        $code_length = 6;
        
        do {
            $code = '';
            for ($i = 0; $i < $code_length; $i++) {
                $code .= $characters[rand(0, strlen($characters) - 1)];
            }
            
            global $wpdb;
            $exists = $wpdb->get_var($wpdb->prepare(
                "SELECT COUNT(*) FROM {$this->table_name} WHERE short_code = %s",
                $code
            ));
        } while ($exists > 0);
        
        return $code;
    }
    
    /**
     * Generate unique session ID that includes link ID for better isolation
     * NEW METHOD - ADD THIS
     */
    private function generate_unique_session_id($link_id) {
        // Include link ID in session to prevent cross-contamination
        $base = $link_id . '_' . time() . '_' . mt_rand(1000, 9999);
        
        // Check for existing session cookie specific to this link
        $cookie_name = 'sky_utm_session_' . $link_id;
        if (isset($_COOKIE[$cookie_name])) {
            return sanitize_text_field($_COOKIE[$cookie_name]);
        }
        
        // Generate new session ID with link isolation
        $session_id = md5($base . '_' . $this->get_visitor_ip() . '_' . (isset($_SERVER['HTTP_USER_AGENT']) ? $_SERVER['HTTP_USER_AGENT'] : ''));
        
        return $session_id;
    }
    
    /**
     * Database cleanup function to remove orphaned clicks
     * NEW METHOD - ADD THIS
     */
    public function cleanup_orphaned_clicks() {
        global $wpdb;
        
        // Remove clicks for non-existent links
        $deleted = $wpdb->query("
            DELETE c FROM {$this->clicks_table} c
            LEFT JOIN {$this->table_name} l ON c.link_id = l.id
            WHERE l.id IS NULL OR l.is_active = 0
        ");
        
        // Log cleanup
        if ($deleted > 0) {
            // Debug logging removed for performance
        }
        
        return $deleted;
    }

    /**
     * Track GiveWP donation conversion
     *
     * @param int $payment_id Payment/Donation ID
     * @param array $payment_data Payment data
     */
    public function track_donation_conversion($payment_id, $payment_data = array()) {
        global $wpdb;

        // Get link ID from cookies or session
        $link_id = isset($_COOKIE['sky_utm_link_id']) ? intval($_COOKIE['sky_utm_link_id']) : 0;

        if (!$link_id) {
            // Try session
            if (isset($_COOKIE['sky_utm_session'])) {
                $session_data = get_transient('sky_utm_session_' . $_COOKIE['sky_utm_session']);
                if ($session_data && isset($session_data['link_id'])) {
                    $link_id = intval($session_data['link_id']);
                }
            }
        }

        if (!$link_id) {
            return; // No UTM tracking data
        }

        // Get donation amount
        $donation_amount = 0;
        if (function_exists('give_get_payment_amount')) {
            $donation_amount = give_get_payment_amount($payment_id);
        } elseif (isset($payment_data['price'])) {
            $donation_amount = floatval($payment_data['price']);
        }

        // Mark donation as tracked
        give_update_payment_meta($payment_id, '_sky_utm_link_id', $link_id);
        give_update_payment_meta($payment_id, '_sky_utm_conversion_tracked', true);

        // Update click as converted
        $session_id = isset($_COOKIE['sky_utm_session_id']) ? sanitize_text_field($_COOKIE['sky_utm_session_id']) : '';
        $click_id = isset($_COOKIE['sky_utm_click_id']) ? intval($_COOKIE['sky_utm_click_id']) : 0;

        if ($click_id && $link_id) {
            $wpdb->update(
                $this->clicks_table,
                array('converted' => 1, 'order_id' => $payment_id),
                array('id' => $click_id, 'link_id' => $link_id),
                array('%d', '%d'),
                array('%d', '%d')
            );
        } elseif ($session_id && $link_id) {
            $wpdb->update(
                $this->clicks_table,
                array('converted' => 1, 'order_id' => $payment_id),
                array('session_id' => $session_id, 'link_id' => $link_id),
                array('%d', '%d'),
                array('%s', '%d')
            );
        }

        // Update link stats
        $wpdb->query($wpdb->prepare(
            "UPDATE {$this->table_name}
            SET conversions = conversions + 1, revenue = revenue + %f
            WHERE id = %d",
            $donation_amount,
            $link_id
        ));

        // Clear tracking cookies
        $this->clear_utm_cookies();
    }

    /**
     * Track GiveWP donation status update
     *
     * @param int $payment_id Payment ID
     * @param string $new_status New status
     * @param string $old_status Old status
     */
    public function track_donation_status_update($payment_id, $new_status, $old_status) {
        // Only track on status change to 'publish' (completed)
        if ($new_status !== 'publish' || $old_status === 'publish') {
            return;
        }

        // Check if already tracked
        $already_tracked = give_get_payment_meta($payment_id, '_sky_utm_conversion_tracked', true);
        if ($already_tracked) {
            return;
        }

        // Track the conversion
        $this->track_donation_conversion($payment_id);
    }

    /**
     * Track WooCommerce donation (for WooCommerce Donations plugin)
     *
     * @param int $donation_id Donation ID
     */
    public function track_wc_donation($donation_id) {
        global $wpdb;

        // Get link ID from cookies or session
        $link_id = isset($_COOKIE['sky_utm_link_id']) ? intval($_COOKIE['sky_utm_link_id']) : 0;

        if (!$link_id) {
            return; // No UTM tracking data
        }

        // Get donation data
        $donation = wc_get_order($donation_id);
        if (!$donation) {
            return;
        }

        $donation_amount = $donation->get_total();

        // Mark donation as tracked
        $donation->update_meta_data('_sky_utm_link_id', $link_id);
        $donation->update_meta_data('_sky_utm_conversion_tracked', true);
        $donation->save();

        // Update click as converted
        $click_id = isset($_COOKIE['sky_utm_click_id']) ? intval($_COOKIE['sky_utm_click_id']) : 0;

        if ($click_id && $link_id) {
            $wpdb->update(
                $this->clicks_table,
                array('converted' => 1, 'order_id' => $donation_id),
                array('id' => $click_id, 'link_id' => $link_id),
                array('%d', '%d'),
                array('%d', '%d')
            );
        }

        // Update link stats
        $wpdb->query($wpdb->prepare(
            "UPDATE {$this->table_name}
            SET conversions = conversions + 1, revenue = revenue + %f
            WHERE id = %d",
            $donation_amount,
            $link_id
        ));
    }
}