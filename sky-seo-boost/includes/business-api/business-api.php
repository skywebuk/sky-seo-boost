<?php
/**
 * Sky SEO Boost - Business API Core
 * Main file with essential functionality only
 * 
 * @package SkySEOBoost
 * @since 3.1.0
 */

// Prevent direct access
if (!defined('ABSPATH')) {
    exit;
}

/**
 * Business API Class - Core Functionality
 */
class Sky_SEO_Business_API {
    
    /**
     * Singleton instance
     */
    private static $instance = null;
    
    /**
     * Option names
     */
    private $option_name = 'sky_seo_business_settings';
    private $advanced_option_name = 'sky_seo_business_advanced_settings';
    private $hours_option_name = 'sky_seo_business_hours';
    private $usage_option_name = 'sky_seo_api_usage_tracking';
    
    /**
     * Cache group
     */
    private $cache_group = 'sky_seo_business';
    
    /**
     * Helper classes
     */
    private $hours_manager;
    private $admin_renderer;
    private $api_handler;
    
    /**
     * Get singleton instance
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
        $this->load_dependencies();
        $this->init_components();
        $this->init_hooks();
    }
    
    /**
     * Load dependency files
     */
    private function load_dependencies() {
        $base_dir = plugin_dir_path(__FILE__);
        
        // Load helper classes
        require_once $base_dir . 'includes/class-business-hours-manager.php';
        require_once $base_dir . 'includes/class-business-admin-renderer.php';
        require_once $base_dir . 'includes/class-business-api-handler.php';
    }
    
    /**
     * Initialize components
     */
    private function init_components() {
        $this->hours_manager = new Sky_SEO_Business_Hours_Manager($this->hours_option_name, $this->cache_group);
        $this->admin_renderer = new Sky_SEO_Business_Admin_Renderer($this);
        $this->api_handler = new Sky_SEO_Business_API_Handler($this);
    }
    
    /**
     * Initialize hooks
     */
    private function init_hooks() {
        // Admin menu
        add_action('admin_menu', [$this, 'add_admin_menu']);
        add_action('admin_init', [$this, 'register_settings']);
        
        // AJAX handlers
        add_action('wp_ajax_sky_seo_test_api', [$this->api_handler, 'ajax_test_api']);
        add_action('wp_ajax_sky_seo_get_api_usage', [$this->api_handler, 'ajax_get_api_usage']);
        add_action('wp_ajax_sky_seo_fetch_reviews', [$this->api_handler, 'ajax_fetch_reviews']);
        add_action('wp_ajax_sky_seo_get_review_text', [$this->api_handler, 'ajax_get_review_text']);
        add_action('wp_ajax_sky_seo_edit_review', [$this->api_handler, 'ajax_edit_review']);
        add_action('wp_ajax_sky_seo_update_google_metadata', [$this->api_handler, 'ajax_update_google_metadata']);
        add_action('wp_ajax_sky_seo_fetch_new_reviews', [$this->api_handler, 'ajax_fetch_new_reviews']);
        
        // Register Elementor widget
        add_action('init', [$this, 'load_elementor_widget'], 1);
        
        // Admin scripts
        add_action('admin_enqueue_scripts', [$this, 'enqueue_admin_scripts']);
    }
    
    /**
     * Get option names (for other classes)
     */
    public function get_option_names() {
        return [
            'main' => $this->option_name,
            'advanced' => $this->advanced_option_name,
            'hours' => $this->hours_option_name,
            'usage' => $this->usage_option_name
        ];
    }
    
    /**
     * Get cache group
     */
    public function get_cache_group() {
        return $this->cache_group;
    }
    
    /**
     * Track API usage
     */
    public function track_api_usage($calls = 1) {
        return $this->api_handler->track_api_usage($calls);
    }
    
    /**
     * Check if we can make API calls
     */
    public function can_make_api_calls($calls = 1) {
        return $this->api_handler->can_make_api_calls($calls);
    }
    
    /**
     * Get business data
     */
    public function get_business_data() {
        // Try database first
        if (class_exists('Sky_SEO_Reviews_Database')) {
            $reviews_db = Sky_SEO_Reviews_Database::get_instance();
            $db_data = $reviews_db->get_business_data();
            if ($db_data !== false) {
                return $db_data;
            }
        }
        
        // Fallback to cached data
        $settings = get_option($this->option_name, []);
        if (empty($settings['place_id'])) {
            return false;
        }
        
        $cache_key = 'business_data_' . md5($settings['place_id']);
        $cached_data = wp_cache_get($cache_key, $this->cache_group);
        
        if ($cached_data !== false) {
            $cached_data['opening_hours'] = $this->get_manual_hours();
            $cached_data['is_open_now'] = $this->is_business_open();
            return $cached_data;
        }
        
        return false;
    }
    
    /**
     * Get manual hours
     */
    public function get_manual_hours() {
        return $this->hours_manager->get_manual_hours();
    }
    
    /**
     * Check if business is open
     */
    public function is_business_open() {
        return $this->hours_manager->is_business_open();
    }
    
    /**
     * Load Elementor widget file
     */
    public function load_elementor_widget() {
        $loader_file = SKY_SEO_BOOST_PLUGIN_DIR . 'includes/business-api/elementor-loader.php';
        if (file_exists($loader_file)) {
            require_once $loader_file;
        }
    }
    
    /**
     * Enqueue admin scripts
     */
    public function enqueue_admin_scripts($hook) {
        // Sanitize the page parameter to prevent XSS
        $current_page = isset($_GET['page']) ? sanitize_text_field(wp_unslash($_GET['page'])) : '';

        // Include business API page regardless of tab
        if ($current_page !== 'sky-seo-business-api') {
            return;
        }
        
        $plugin_url = plugin_dir_url(__FILE__);
        $version = defined('SKY_SEO_BOOST_VERSION') ? SKY_SEO_BOOST_VERSION : '3.1.0';
        
        // Enqueue styles
        wp_enqueue_style(
            'sky-business-api-admin', 
            $plugin_url . 'assets/css/business-api-admin.css', 
            [], 
            $version
        );
        
        // Enqueue scripts
        wp_enqueue_script(
            'sky-business-api-admin',
            $plugin_url . 'assets/js/business-api-admin.js',
            ['jquery'],
            $version,
            true
        );
        
        // Localize script
        wp_localize_script('sky-business-api-admin', 'sky_business_api', [
            'ajax_url' => admin_url('admin-ajax.php'),
            'nonce' => wp_create_nonce('sky_seo_api_nonce'),
            'admin_url' => admin_url(),
            'i18n' => $this->get_js_translations()
        ]);
    }
    
    /**
     * Get JavaScript translations
     */
    private function get_js_translations() {
        return [
            'closed_all_day' => esc_html__('Closed all day', 'sky360'),
            'open_24_hours' => esc_html__('Open 24 hours', 'sky360'),
            'hours_applied' => esc_html__('Hours applied to all days!', 'sky360'),
            'hours_copied' => esc_html__('Hours copied successfully!', 'sky360'),
            'copy_hours_from' => esc_html__('Copy Hours From', 'sky360'),
            'select_source_day' => esc_html__('Select source day', 'sky360'),
            'copy_to' => esc_html__('Copy to', 'sky360'),
            'cancel' => esc_html__('Cancel', 'sky360'),
            'apply' => esc_html__('Apply', 'sky360'),
            'fetch_confirm' => esc_html__('This will check for new reviews. Continue?', 'sky360')
        ];
    }
    
    /**
     * Add admin menu - UPDATED to remove separate Reviews submenu
     */
    public function add_admin_menu() {
        add_submenu_page(
            'sky-seo-boost',
            __('Business API Settings', 'sky360'),
            __('Business API', 'sky360'),
            'manage_options',
            'sky-seo-business-api',
            [$this->admin_renderer, 'render_admin_page']
        );
        
        // No longer add separate Reviews submenu - it's now a tab
    }
    
    /**
     * Register settings
     */
    public function register_settings() {
        register_setting('sky_seo_business_api', $this->option_name, [
            'sanitize_callback' => [$this, 'sanitize_business_settings']
        ]);
        register_setting('sky_seo_business_api', $this->advanced_option_name, [
            'sanitize_callback' => [$this, 'sanitize_advanced_settings']
        ]);
        register_setting('sky_seo_business_api', $this->hours_option_name, [
            'sanitize_callback' => [$this->hours_manager, 'sanitize_hours_settings']
        ]);
    }
    
    /**
     * Sanitize business settings
     */
    public function sanitize_business_settings($input) {
        $sanitized = [];
        
        $sanitized['serpapi_key'] = sanitize_text_field($input['serpapi_key'] ?? '');
        $sanitized['place_id'] = sanitize_text_field($input['place_id'] ?? '');
        $sanitized['business_name'] = sanitize_text_field($input['business_name'] ?? '');
        
        return $sanitized;
    }
    
    /**
     * Sanitize advanced settings
     */
    public function sanitize_advanced_settings($input) {
        if (!is_array($input)) {
            return [];
        }

        $sanitized = [];
        foreach ($input as $key => $value) {
            if (is_array($value)) {
                $sanitized[sanitize_key($key)] = array_map('sanitize_text_field', $value);
            } elseif (is_bool($value)) {
                $sanitized[sanitize_key($key)] = (bool) $value;
            } elseif (is_numeric($value)) {
                $sanitized[sanitize_key($key)] = intval($value);
            } else {
                $sanitized[sanitize_key($key)] = sanitize_text_field($value);
            }
        }

        return $sanitized;
    }
}

// AJAX Handler for review likes
add_action('wp_ajax_sky_seo_handle_review_like', 'sky_seo_handle_review_like');
add_action('wp_ajax_nopriv_sky_seo_handle_review_like', 'sky_seo_handle_review_like');

function sky_seo_handle_review_like() {
    // Verify nonce
    if (!isset($_POST['nonce']) || !wp_verify_nonce($_POST['nonce'], 'sky_seo_reviews_nonce')) {
        wp_die('Security check failed');
    }
    
    $review_id = sanitize_text_field($_POST['review_id']);
    $is_liked = $_POST['is_liked'] === 'true';
    
    // Get current likes from database
    $all_likes = get_option('sky_seo_review_likes', []);
    $current_likes = isset($all_likes[$review_id]) ? intval($all_likes[$review_id]) : 0;
    
    // Update likes count
    if ($is_liked) {
        $all_likes[$review_id] = $current_likes + 1;
    } else {
        $all_likes[$review_id] = max(0, $current_likes - 1);
    }
    
    // Save to database
    update_option('sky_seo_review_likes', $all_likes);
    
    // Update user's liked reviews in cookie
    $liked_reviews = [];
    if (isset($_COOKIE['sky_liked_reviews'])) {
        $liked_reviews = json_decode(stripslashes($_COOKIE['sky_liked_reviews']), true);
        if (!is_array($liked_reviews)) {
            $liked_reviews = [];
        }
    }
    
    if ($is_liked) {
        if (!in_array($review_id, $liked_reviews)) {
            $liked_reviews[] = $review_id;
        }
    } else {
        $liked_reviews = array_diff($liked_reviews, [$review_id]);
    }
    
    // Set cookie for 1 year
    setcookie('sky_liked_reviews', json_encode(array_values($liked_reviews)), time() + (365 * 24 * 60 * 60), '/');
    
    // Return updated count
    wp_send_json_success([
        'likes' => $all_likes[$review_id],
        'liked_reviews' => array_values($liked_reviews)
    ]);
}

// Initialize the class
Sky_SEO_Business_API::get_instance();

// Load Reviews Database Manager if not already loaded
if (file_exists(plugin_dir_path(__FILE__) . 'reviews-database.php')) {
    require_once plugin_dir_path(__FILE__) . 'reviews-database.php';
}