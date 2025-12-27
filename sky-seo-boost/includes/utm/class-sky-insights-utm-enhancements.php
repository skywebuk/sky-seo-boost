<?php
/**
 * Sky Insights UTM Tracking Enhancements
 * Provides advanced tracking capabilities to handle cookie blocking and cross-device tracking
 * 
 * @package SkySEOBoost
 * @subpackage UTM
 * @since 2.2.2
 */

// Prevent direct access
if (!defined('ABSPATH')) {
    exit;
}

class SkyInsightsUTMEnhancements {
    
    /**
     * Instance of this class
     */
    private static $instance = null;
    
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
        // Initialize enhancements
        add_action('init', array($this, 'init_enhancements'), 5);
    }
    
    /**
     * Initialize all enhancements
     */
    public function init_enhancements() {
        // 1. Server-side session storage - Priority 5 to run early
        add_action('init', array($this, 'track_utm_server_side'), 5);
        add_action('parse_request', array($this, 'capture_utm_to_session'));
        
        // 2. URL parameter persistence - REMOVED to prevent automatic UTM appending
        // Only keep these if you specifically want UTM params on cart/checkout/return URLs
        // add_filter('woocommerce_get_cart_url', array($this, 'persist_utm_params'));
        // add_filter('woocommerce_get_checkout_url', array($this, 'persist_utm_params'));
        // add_filter('woocommerce_get_return_url', array($this, 'persist_utm_params'));
        
        // REMOVED: These were adding UTM params to all links
        // add_filter('post_type_link', array($this, 'persist_utm_params_in_permalinks'), 10, 2);
        // add_filter('page_link', array($this, 'persist_utm_params'));
        
        // 3. Local storage backup (JavaScript)
        add_action('wp_footer', array($this, 'add_localstorage_backup_script'));
        
        // 4. Email-based attribution
        add_action('woocommerce_checkout_update_order_meta', array($this, 'link_utm_to_customer_email'), 20, 2);
        add_action('woocommerce_checkout_process', array($this, 'capture_email_attribution'));
        
        // 5. First-click attribution
        add_action('woocommerce_thankyou', array($this, 'store_first_touch_attribution'), 5);
        
        // 6. Device fingerprinting
        add_action('init', array($this, 'track_device_fingerprint'), 25);
        
        // 7. Enhanced order tracking - Priority 25 to match working code
        add_action('woocommerce_checkout_create_order', array($this, 'enhanced_utm_order_tracking'), 25, 2);
        
        // AJAX handlers for JavaScript tracking
        add_action('wp_ajax_sky_utm_store_session', array($this, 'ajax_store_utm_session'));
        add_action('wp_ajax_nopriv_sky_utm_store_session', array($this, 'ajax_store_utm_session'));
        
        // Debug info for admins
        add_action('wp_footer', array($this, 'add_debug_info'));
    }
    
    /**
     * 1. SERVER-SIDE SESSION STORAGE
     * Store UTM data in database linked to session
     * RESTRICTED: Only tracks sky_utm shortlinks (dashboard-created links)
     */
    public function track_utm_server_side() {
        // ONLY track sky_utm shortlinks - ignore external UTM parameters
        if (isset($_GET['sky_utm'])) {
            $session_id = isset($_COOKIE['sky_utm_session']) ? $_COOKIE['sky_utm_session'] : wp_generate_password(32, false);
            
            // Get UTM data
            $utm_data = array(
                'utm_source' => sanitize_text_field($_GET['utm_source'] ?? ''),
                'utm_medium' => sanitize_text_field($_GET['utm_medium'] ?? ''),
                'utm_campaign' => sanitize_text_field($_GET['utm_campaign'] ?? ''),
                'utm_term' => sanitize_text_field($_GET['utm_term'] ?? ''),
                'utm_content' => sanitize_text_field($_GET['utm_content'] ?? ''),
                'timestamp' => current_time('timestamp'),
                'ip' => $this->get_visitor_ip(),
                'user_agent' => isset($_SERVER['HTTP_USER_AGENT']) ? $_SERVER['HTTP_USER_AGENT'] : '',
                'fingerprint' => $this->generate_device_fingerprint(),
                'landing_page' => $_SERVER['REQUEST_URI']
            );
            
            // Get link ID if using sky_utm - with table existence check
            if (isset($_GET['sky_utm'])) {
                global $wpdb;
                $table_name = $wpdb->prefix . 'sky_insights_utm_links';
                
                // Check if table exists before querying
                if ($wpdb->get_var("SHOW TABLES LIKE '$table_name'") == $table_name) {
                    $short_code = sanitize_text_field($_GET['sky_utm']);
                    $link = $wpdb->get_row($wpdb->prepare(
                        "SELECT id FROM $table_name WHERE short_code = %s",
                        $short_code
                    ));
                    if ($link) {
                        $utm_data['link_id'] = $link->id;
                    }
                }
            }
            
            // Store in transient
            set_transient('sky_utm_session_' . $session_id, $utm_data, 30 * DAY_IN_SECONDS);
            
            // Set session cookie if not exists - with safe cookie domain
            if (!isset($_COOKIE['sky_utm_session'])) {
                $cookie_domain = defined('COOKIE_DOMAIN') ? COOKIE_DOMAIN : '';
                setcookie('sky_utm_session', $session_id, time() + (30 * DAY_IN_SECONDS), '/', $cookie_domain, is_ssl(), true);
            }
            
            // Also store by IP for cross-session tracking
            $ip_key = 'sky_utm_ip_' . md5($this->get_visitor_ip());
            set_transient($ip_key, $utm_data, 7 * DAY_IN_SECONDS);
            
            // Store by fingerprint
            $fingerprint_key = 'sky_utm_fp_' . $this->generate_device_fingerprint();
            set_transient($fingerprint_key, $utm_data, 7 * DAY_IN_SECONDS);
        }
    }
    
    /**
     * Capture UTM parameters early in request
     * RESTRICTED: Only captures sky_utm shortlinks
     */
    public function capture_utm_to_session() {
        // Only capture sky_utm shortlinks, ignore external UTM
        if (!is_admin() && isset($_GET['sky_utm'])) {
            // Start session if not started
            if (!session_id() && !headers_sent()) {
                session_start();
            }
            
            // Store in session
            $_SESSION['sky_utm_captured'] = array(
                'params' => $this->get_utm_params_from_request(),
                'timestamp' => current_time('timestamp'),
                'url' => $_SERVER['REQUEST_URI']
            );
        }
    }
    
    /**
     * 3. LOCAL STORAGE BACKUP
     * JavaScript to store UTM data in localStorage
     */
    public function add_localstorage_backup_script() {
        if (is_admin()) {
            return;
        }
        
        $utm_params = $this->get_current_utm_params();
        $link_id = isset($_COOKIE['sky_utm_link_id']) ? intval($_COOKIE['sky_utm_link_id']) : 0;
        
        ?>
        <script>
        (function() {
            // Enhanced UTM Tracking with LocalStorage
            var utmData = <?php echo json_encode($utm_params); ?>;
            var linkId = <?php echo $link_id; ?>;
            var hasUTM = false;
            
            // Check URL parameters - ONLY track sky_utm shortlinks
            var urlParams = new URLSearchParams(window.location.search);
            if (urlParams.has('sky_utm')) {
                hasUTM = true;
                
                // Capture all UTM parameters
                var capturedData = {
                    utm_source: urlParams.get('utm_source') || utmData.utm_source || '',
                    utm_medium: urlParams.get('utm_medium') || utmData.utm_medium || '',
                    utm_campaign: urlParams.get('utm_campaign') || utmData.utm_campaign || '',
                    utm_term: urlParams.get('utm_term') || utmData.utm_term || '',
                    utm_content: urlParams.get('utm_content') || utmData.utm_content || '',
                    sky_utm: urlParams.get('sky_utm') || '',
                    link_id: linkId,
                    timestamp: Date.now(),
                    landing_page: window.location.pathname,
                    referrer: document.referrer,
                    url: window.location.href
                };
                
                // Store in localStorage
                try {
                    localStorage.setItem('sky_utm_data', JSON.stringify(capturedData));
                    sessionStorage.setItem('sky_utm_data', JSON.stringify(capturedData));
                } catch(e) {
                    // Storage not available
                }
                
                // Send to server via AJAX
                if (typeof jQuery !== 'undefined') {
                    jQuery.post('<?php echo admin_url('admin-ajax.php'); ?>', {
                        action: 'sky_utm_store_session',
                        utm_data: capturedData,
                        nonce: '<?php echo wp_create_nonce('sky_utm_session'); ?>'
                    });
                }
            }
            
            // Check for stored data
            try {
                var storedData = localStorage.getItem('sky_utm_data');
                if (storedData) {
                    // Restore from localStorage if no cookies
                    if (!hasUTM && !document.cookie.includes('sky_utm_')) {
                        var parsed = JSON.parse(storedData);
                        // Check if data is not older than 30 days
                        if (parsed.timestamp && (Date.now() - parsed.timestamp) < (30 * 24 * 60 * 60 * 1000)) {
                            // Restore data to cookies via AJAX
                            if (typeof jQuery !== 'undefined') {
                                jQuery.post('<?php echo admin_url('admin-ajax.php'); ?>', {
                                    action: 'sky_utm_store_session',
                                    utm_data: parsed,
                                    restore: true,
                                    nonce: '<?php echo wp_create_nonce('sky_utm_session'); ?>'
                                });
                            }
                        }
                    }
                }
            } catch(e) {
                // Could not restore from storage
            }
            
            // IMPORTANT: No automatic UTM parameter appending to links
            // UTM parameters should only be added to links manually when needed
        })();
        </script>
        <?php
    }
    
    /**
     * 4. EMAIL-BASED ATTRIBUTION
     * Link UTM data to customer email
     */
    public function link_utm_to_customer_email($order_id, $data) {
        $order = wc_get_order($order_id);
        if (!$order) {
            return;
        }
        
        $email = $order->get_billing_email();
        if (empty($email)) {
            return;
        }
        
        // Get current UTM data
        $utm_data = $this->get_all_possible_utm_data();
        
        if (!empty($utm_data)) {
            // Store email attribution
            $email_key = 'sky_utm_email_' . md5($email);
            $attribution_data = array(
                'utm_data' => $utm_data,
                'order_id' => $order_id,
                'timestamp' => current_time('timestamp')
            );
            
            // Store for 90 days
            set_transient($email_key, $attribution_data, 90 * DAY_IN_SECONDS);
            
            // Also store in user meta if customer exists
            $user = get_user_by('email', $email);
            if ($user) {
                update_user_meta($user->ID, '_sky_utm_attribution', $attribution_data);
            }
        }
    }
    
    /**
     * Capture email during checkout for attribution
     */
    public function capture_email_attribution() {
        if (isset($_POST['billing_email'])) {
            $email = sanitize_email($_POST['billing_email']);
            
            // Check if we have stored attribution for this email
            $email_key = 'sky_utm_email_' . md5($email);
            $stored_attribution = get_transient($email_key);
            
            if ($stored_attribution && isset($stored_attribution['utm_data'])) {
                // Restore UTM data to current session
                $this->restore_utm_data($stored_attribution['utm_data']);
            }
        }
    }
    
    /**
     * 5. FIRST-CLICK ATTRIBUTION
     * Store the first UTM source for a customer
     */
    public function store_first_touch_attribution($order_id) {
        $order = wc_get_order($order_id);
        if (!$order) {
            return;
        }
        
        $email = $order->get_billing_email();
        if (empty($email)) {
            return;
        }
        
        // Check if we already have first touch data
        $first_touch_key = 'sky_utm_first_touch_' . md5($email);
        $existing = get_option($first_touch_key);
        
        if (!$existing) {
            $utm_data = $this->get_all_possible_utm_data();
            
            if (!empty($utm_data)) {
                $first_touch_data = array(
                    'utm_data' => $utm_data,
                    'date' => current_time('mysql'),
                    'order_id' => $order_id,
                    'order_total' => $order->get_total()
                );
                
                // Store permanently
                update_option($first_touch_key, $first_touch_data);
                
                // Also add to order meta
                $order->update_meta_data('_sky_utm_first_touch', true);
                $order->save();
            }
        }
    }
    
    /**
     * 6. DEVICE FINGERPRINTING
     * Create device fingerprint for tracking
     */
    public function track_device_fingerprint() {
        if (is_admin() || !isset($_SERVER['HTTP_USER_AGENT'])) {
            return;
        }
        
        $fingerprint = $this->generate_device_fingerprint();
        
        // Check if we have UTM data for this fingerprint
        $fingerprint_key = 'sky_utm_fp_' . $fingerprint;
        $stored_data = get_transient($fingerprint_key);
        
        if ($stored_data && empty($_COOKIE['sky_utm_source'])) {
            // Restore UTM data from fingerprint
            if (isset($stored_data['utm_data'])) {
                $this->restore_utm_data($stored_data['utm_data']);
            } elseif (isset($stored_data['utm_source'])) {
                // Handle direct utm data storage
                $this->restore_utm_data($stored_data);
            }
        }
    }
    
    /**
     * Generate device fingerprint
     */
    private function generate_device_fingerprint() {
        $components = array(
            $_SERVER['HTTP_USER_AGENT'] ?? '',
            $_SERVER['HTTP_ACCEPT_LANGUAGE'] ?? '',
            $_SERVER['HTTP_ACCEPT_ENCODING'] ?? '',
            $_SERVER['HTTP_ACCEPT'] ?? ''
        );
        
        // Add screen resolution if available from JavaScript
        if (isset($_COOKIE['sky_screen_res'])) {
            $components[] = $_COOKIE['sky_screen_res'];
        }
        
        return md5(implode('|', $components));
    }
    
    /**
     * 7. ENHANCED ORDER TRACKING
     * Comprehensive UTM data collection for orders
     */
    public function enhanced_utm_order_tracking($order, $data) {
        // Try multiple methods to get UTM data
        $utm_data = array();
        
        // Check cookies first
        foreach (['utm_source', 'utm_medium', 'utm_campaign', 'utm_term', 'utm_content', 'utm_link_id'] as $param) {
            $key = ($param === 'utm_link_id') ? $param : str_replace('utm_', '', $param);
            if (isset($_COOKIE['sky_' . $param])) {
                $utm_data[$key] = $_COOKIE['sky_' . $param];
            }
        }
        
        // Check session storage
        if (isset($_COOKIE['sky_utm_session'])) {
            $session_data = get_transient('sky_utm_session_' . $_COOKIE['sky_utm_session']);
            if ($session_data) {
                $utm_data = array_merge($utm_data, $session_data);
                // Add tracking method
                $order->update_meta_data('_utm_tracking_method', 'session_storage');
            }
        }
        
        // Get all possible UTM data
        $all_utm_data = $this->get_all_possible_utm_data();
        if (!empty($all_utm_data)) {
            $utm_data = array_merge($utm_data, $all_utm_data);
        }
        
        if (!empty($utm_data)) {
            // Save comprehensive tracking data
            $tracking_data = array(
                'utm_data' => $utm_data,
                'attribution_method' => $this->get_attribution_method(),
                'device_fingerprint' => $this->generate_device_fingerprint(),
                'session_id' => $this->get_reliable_session_id(),
                'ip_address' => $this->get_visitor_ip(),
                'tracking_timestamp' => current_time('mysql')
            );
            
            // Save to order meta
            $order->update_meta_data('_sky_utm_enhanced_tracking', $tracking_data);
            $order->update_meta_data('_utm_enhanced_data', $utm_data);
            
            // Save individual UTM parameters for compatibility
            foreach ($utm_data as $key => $value) {
                if (!empty($value)) {
                    $meta_key = (strpos($key, 'utm_') === 0) ? '_' . $key : '_utm_' . $key;
                    $order->update_meta_data($meta_key, $value);
                }
            }
            
            // Order now has comprehensive UTM data
        }
    }
    
    /**
     * AJAX: Store UTM session data
     */
    public function ajax_store_utm_session() {
        // Verify nonce
        if (!isset($_POST['nonce']) || !wp_verify_nonce($_POST['nonce'], 'sky_utm_session')) {
            wp_die('Security check failed');
        }
        
        $utm_data = isset($_POST['utm_data']) ? $_POST['utm_data'] : array();
        $restore = isset($_POST['restore']) ? $_POST['restore'] : false;
        
        if ($restore && !empty($utm_data)) {
            // Restore UTM data to cookies
            $this->restore_utm_data($utm_data);
        } else {
            // Store new UTM data
            $this->track_utm_server_side();
        }
        
        wp_send_json_success('UTM data processed');
    }
    
    /**
     * Get current UTM parameters from all sources
     * RESTRICTED: Only returns params from sky_utm shortlinks
     */
    private function get_current_utm_params() {
        $params = array();

        // ONLY get params from sky_utm cookies (set by sky_utm shortlinks)
        // Ignore external utm_source, utm_medium, etc. from URL
        $utm_fields = array('utm_source', 'utm_medium', 'utm_campaign', 'utm_term', 'utm_content');
        foreach ($utm_fields as $field) {
            // Only check cookies (set by sky_utm shortlinks), ignore URL params
            if (isset($_COOKIE['sky_' . $field])) {
                $params[$field] = sanitize_text_field($_COOKIE['sky_' . $field]);
            }
        }

        return $params;
    }
    
    /**
     * Get UTM parameters from request
     * RESTRICTED: No longer captures external UTM params
     */
    private function get_utm_params_from_request() {
        // DISABLED: External UTM parameters are not captured
        // Only sky_utm shortlinks are tracked
        return array();
    }
    
    /**
     * Get all possible UTM data using multiple methods
     */
    private function get_all_possible_utm_data() {
        $utm_data = array();
        
        // 1. Check cookies
        $cookie_fields = array('utm_source', 'utm_medium', 'utm_campaign', 'utm_term', 'utm_content', 'utm_link_id');
        foreach ($cookie_fields as $field) {
            if (isset($_COOKIE['sky_' . $field])) {
                $utm_data[$field] = sanitize_text_field($_COOKIE['sky_' . $field]);
            }
        }
        
        // 2. Check session storage
        if (isset($_COOKIE['sky_utm_session'])) {
            $session_data = get_transient('sky_utm_session_' . $_COOKIE['sky_utm_session']);
            if ($session_data && is_array($session_data)) {
                // Map data correctly
                foreach ($session_data as $key => $value) {
                    if (strpos($key, 'utm_') === 0 && !empty($value)) {
                        $utm_data[$key] = $value;
                    }
                }
            }
        }
        
        // 3. Check IP-based storage
        $ip_data = get_transient('sky_utm_ip_' . md5($this->get_visitor_ip()));
        if ($ip_data && is_array($ip_data)) {
            foreach ($ip_data as $key => $value) {
                if (strpos($key, 'utm_') === 0 && !empty($value)) {
                    $utm_data[$key] = $value;
                }
            }
        }
        
        // 4. Check fingerprint-based storage
        $fp_data = get_transient('sky_utm_fp_' . $this->generate_device_fingerprint());
        if ($fp_data && is_array($fp_data)) {
            foreach ($fp_data as $key => $value) {
                if (strpos($key, 'utm_') === 0 && !empty($value)) {
                    $utm_data[$key] = $value;
                }
            }
        }
        
        // 5. Check PHP session
        if (isset($_SESSION['sky_utm_captured']) && isset($_SESSION['sky_utm_captured']['params'])) {
            $utm_data = array_merge($utm_data, $_SESSION['sky_utm_captured']['params']);
        }
        
        return array_filter($utm_data);
    }
    
    /**
     * Get attribution method used
     */
    private function get_attribution_method() {
        if (!empty($_COOKIE['sky_utm_source'])) {
            return 'cookie';
        } elseif (isset($_COOKIE['sky_utm_session']) && get_transient('sky_utm_session_' . $_COOKIE['sky_utm_session'])) {
            return 'session';
        } elseif (get_transient('sky_utm_ip_' . md5($this->get_visitor_ip()))) {
            return 'ip_address';
        } elseif (get_transient('sky_utm_fp_' . $this->generate_device_fingerprint())) {
            return 'fingerprint';
        } else {
            return 'unknown';
        }
    }
    
    /**
     * Get reliable session ID
     */
    private function get_reliable_session_id() {
        // Check for existing session cookie
        if (isset($_COOKIE['sky_utm_session'])) {
            return sanitize_text_field($_COOKIE['sky_utm_session']);
        }
        
        // Try WooCommerce session
        if (function_exists('WC') && class_exists('WooCommerce') && WC()->session) {
            return WC()->session->get_customer_id();
        }
        
        // Try PHP session
        if (session_id()) {
            return session_id();
        }
        
        // Generate new session ID
        $session_id = wp_generate_password(32, false);
        $cookie_domain = defined('COOKIE_DOMAIN') ? COOKIE_DOMAIN : '';
        setcookie('sky_utm_session', $session_id, time() + (30 * DAY_IN_SECONDS), '/', $cookie_domain, is_ssl(), true);
        
        return $session_id;
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
     * Restore UTM data to cookies
     */
    private function restore_utm_data($utm_data) {
        if (!is_array($utm_data)) {
            return;
        }
        
        $expire = time() + (7 * DAY_IN_SECONDS);
        $domain = defined('COOKIE_DOMAIN') ? COOKIE_DOMAIN : '';
        
        foreach ($utm_data as $key => $value) {
            if (!empty($value)) {
                // Handle both formats: with and without 'utm_' prefix
                $cookie_key = (strpos($key, 'utm_') === 0) ? 'sky_' . $key : 'sky_utm_' . $key;
                setcookie($cookie_key, $value, $expire, '/', $domain, is_ssl(), true);
                $_COOKIE[$cookie_key] = $value;
            }
        }
    }
    
    /**
     * Add debug info for testing
     */
    public function add_debug_info() {
        if (!current_user_can('manage_options') || !isset($_GET['utm_debug'])) {
            return;
        }
        
        echo '<div style="position: fixed; bottom: 0; left: 0; background: #000; color: #fff; padding: 10px; font-size: 12px; max-width: 500px; z-index: 99999;">';
        echo '<strong>UTM Debug Info:</strong><br>';
        echo 'Session ID: ' . (isset($_COOKIE['sky_utm_session']) ? $_COOKIE['sky_utm_session'] : 'None') . '<br>';
        
        if (isset($_COOKIE['sky_utm_session'])) {
            $session_data = get_transient('sky_utm_session_' . $_COOKIE['sky_utm_session']);
            if ($session_data) {
                echo 'Session Data: ' . json_encode($session_data) . '<br>';
            }
        }
        
        echo 'Cookies: ';
        foreach ($_COOKIE as $name => $value) {
            if (strpos($name, 'sky_utm') !== false || strpos($name, 'sky_') === 0) {
                echo $name . '=' . $value . ', ';
            }
        }
        
        echo '<br>Attribution Method: ' . $this->get_attribution_method();
        echo '</div>';
    }
}