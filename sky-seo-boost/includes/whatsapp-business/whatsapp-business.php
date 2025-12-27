<?php
/**
 * Sky SEO Boost - WhatsApp Business Module
 * 
 * Main loader file for WhatsApp Business integration
 * 
 * @package Sky_SEO_Boost
 * @subpackage WhatsApp_Business
 * @version 1.0.5
 * @since 3.2.0
 */

// Prevent direct access
if (!defined('ABSPATH')) {
    exit;
}

/**
 * WhatsApp Business Module Main Class
 */
class Sky_SEO_WhatsApp_Business {
    
    /**
     * Instance
     */
    private static $instance = null;
    
    /**
     * Module path
     */
    private $module_path;
    
    /**
     * Module URL
     */
    private $module_url;
    
    /**
     * Database table name
     */
    private $table_name;
    
    /**
     * Configuration instance
     */
    private $configuration = null;
    
    /**
     * Tracking instance
     */
    private $tracking = null;
    
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
        
        // Set paths
        $this->module_path = plugin_dir_path(__FILE__);
        $this->module_url = plugin_dir_url(__FILE__);
        $this->table_name = $wpdb->prefix . 'sky_seo_whatsapp_tracking';
        
        // Initialize
        $this->init();
    }
    
    /**
     * Initialize module
     */
    private function init() {
        // Check if main plugin is licensed
        if (!function_exists('sky_seo_is_licensed') || !sky_seo_is_licensed()) {
            return;
        }
        
        // Handle trackable button links VERY EARLY
        add_action('init', [$this, 'handle_trackable_button_redirect'], 1);
        
        // Load module files
        $this->load_dependencies();
        
        // Initialize components BEFORE hooks
        $this->initialize_components();
        
        // Initialize hooks
        $this->init_hooks();
        
        // Create/update database table
        $this->create_database_table();
        
        // Fix database structure
        add_action('admin_init', [$this, 'fix_database_structure']);
    }
    
    /**
     * Load dependencies
     */
    private function load_dependencies() {
        // Load configuration module
        if (file_exists($this->module_path . 'whatsapp-business-configuration.php')) {
            require_once $this->module_path . 'whatsapp-business-configuration.php';
        }
        
        // Load tracking module
        if (file_exists($this->module_path . 'whatsapp-business-tracking.php')) {
            require_once $this->module_path . 'whatsapp-business-tracking.php';
        }
    }
    
    /**
     * Initialize components
     */
    private function initialize_components() {
        // Initialize configuration
        if (class_exists('Sky_SEO_WhatsApp_Configuration')) {
            $this->configuration = Sky_SEO_WhatsApp_Configuration::get_instance();
        }
        
        // Initialize tracking
        if (class_exists('Sky_SEO_WhatsApp_Tracking')) {
            $this->tracking = Sky_SEO_WhatsApp_Tracking::get_instance();
        }
    }
    
    /**
     * Initialize hooks
     */
    private function init_hooks() {
        // Admin menu
        add_action('admin_menu', [$this, 'add_admin_menu'], 15);
        
        // Enqueue scripts and styles
        add_action('admin_enqueue_scripts', [$this, 'enqueue_admin_assets']);
        add_action('wp_enqueue_scripts', [$this, 'enqueue_frontend_assets']);
        
        // AJAX handlers for configuration
        add_action('wp_ajax_sky_seo_save_whatsapp_config', [$this, 'ajax_save_configuration']);
        
        // Tracking handlers (including no-priv for frontend)
        add_action('wp_ajax_sky_seo_track_whatsapp_click', [$this, 'ajax_track_click']);
        add_action('wp_ajax_nopriv_sky_seo_track_whatsapp_click', [$this, 'ajax_track_click']);
        
        // FIXED: Register ALL tracking AJAX handlers with correct names
        add_action('wp_ajax_sky_seo_whatsapp_tracking_data', [$this, 'ajax_get_tracking_data']);
        add_action('wp_ajax_sky_seo_whatsapp_export', [$this, 'ajax_export_data']);
        add_action('wp_ajax_sky_seo_whatsapp_page_details', [$this, 'ajax_get_page_details']); // FIXED: Changed from conversation_details
        
        // Add query var for WhatsApp button
        add_filter('query_vars', [$this, 'add_query_vars']);
        
        // Handle template redirect for WhatsApp button
        add_action('template_redirect', [$this, 'handle_whatsapp_redirect'], 1);
        
        // Frontend display
        add_action('wp_footer', [$this, 'render_floating_widget']);
        
        // Activation/Deactivation
        register_activation_hook(SKY_SEO_BOOST_FILE, [$this, 'activate']);
        register_deactivation_hook(SKY_SEO_BOOST_FILE, [$this, 'deactivate']);
    }
    
    /**
     * Fix database structure
     */
    public function fix_database_structure() {
        global $wpdb;
        
        // Check if table exists first
        $table_exists = $wpdb->get_var("SHOW TABLES LIKE '{$this->table_name}'") === $this->table_name;
        
        if (!$table_exists) {
            // Create table if it doesn't exist
            $this->create_database_table();
            return;
        }
        
        // Get current database version
        $db_version = get_option('sky_seo_whatsapp_db_version', '1.0.0');
        
        // Check if we need to update
        if (version_compare($db_version, '1.0.3', '<')) {
            // Check if source column exists
            $columns = $wpdb->get_col("SHOW COLUMNS FROM {$this->table_name}");
            
            $columns_to_add = [];
            
            if (!in_array('source', $columns)) {
                $columns_to_add[] = "ADD COLUMN source VARCHAR(255) DEFAULT NULL AFTER click_type";
            }
            
            // Add indexes if they don't exist
            $existing_indexes = $wpdb->get_results("SHOW INDEX FROM {$this->table_name}");
            $index_names = array_column($existing_indexes, 'Key_name');
            
            if (!in_array('idx_page_title', $index_names)) {
                $columns_to_add[] = "ADD INDEX idx_page_title (page_title)";
            }
            
            if (!in_array('idx_session_id', $index_names)) {
                $columns_to_add[] = "ADD INDEX idx_session_id (session_id)";
            }
            
            // Execute all alterations
            if (!empty($columns_to_add)) {
                foreach ($columns_to_add as $alteration) {
                    $result = $wpdb->query("ALTER TABLE {$this->table_name} {$alteration}");
                    
                    if ($result === false) {
                        error_log("WhatsApp DB Update Error: " . $wpdb->last_error);
                    }
                }
                
                // Update database version
                update_option('sky_seo_whatsapp_db_version', '1.0.3');
                
                // Add admin notice
                add_action('admin_notices', function() {
                    ?>
                    <div class="notice notice-success is-dismissible">
                        <p><?php esc_html_e('Sky SEO WhatsApp: Database structure updated successfully.', 'sky360'); ?></p>
                    </div>
                    <?php
                });
            } else {
                // Just update version
                update_option('sky_seo_whatsapp_db_version', '1.0.3');
            }
        }
    }
    
    /**
     * Add admin menu
     */
    public function add_admin_menu() {
        add_submenu_page(
            'sky-seo-boost',
            __('WhatsApp Chat', 'sky360'),
            __('WhatsApp Chat', 'sky360'),
            'manage_options',
            'sky-seo-whatsapp',
            [$this, 'render_admin_page']
        );
    }
    
    /**
     * Render admin page - Updated with Tracking as default tab
     */
    public function render_admin_page() {
        // Force database fix when viewing the page
        $this->fix_database_structure();

        // Get current tab - CHANGED: Default is now 'tracking'
        $current_tab = isset($_GET['tab']) ? sanitize_text_field($_GET['tab']) : 'tracking';

        // Define tabs
        $tabs = [
            ['slug' => 'tracking', 'label' => __('Tracking', 'sky360'), 'icon' => 'dashicons-chart-bar'],
            ['slug' => 'configuration', 'label' => __('Configuration', 'sky360'), 'icon' => 'dashicons-admin-settings'],
        ];

        // Start Sky360 page wrapper
        sky360_admin_page_start();
        sky360_render_admin_header(
            __('WhatsApp Chat', 'sky360'),
            __('Track WhatsApp conversations and configure chat widget', 'sky360')
        );
        sky360_render_nav_tabs($tabs, $current_tab, 'sky-seo-whatsapp');
        sky360_content_wrapper_start();
        ?>
            <div class="sky-seo-tab-content">
                <?php
                // CHANGED: Switch default case to tracking
                switch ($current_tab) {
                    case 'configuration':
                        if ($this->configuration) {
                            $this->configuration->render_configuration_page();
                        } else {
                            echo '<div class="sky-seo-empty-state">';
                            echo '<div class="sky-seo-empty-icon"><span class="dashicons dashicons-warning"></span></div>';
                            echo '<p>' . esc_html__('Configuration module not loaded.', 'sky360') . '</p>';
                            echo '</div>';
                        }
                        break;

                    case 'tracking':
                    default:
                        if ($this->tracking) {
                            $this->tracking->render_tracking_page();
                        } else {
                            echo '<div class="sky-seo-empty-state">';
                            echo '<div class="sky-seo-empty-icon"><span class="dashicons dashicons-warning"></span></div>';
                            echo '<p>' . esc_html__('Tracking module not loaded.', 'sky360') . '</p>';
                            echo '</div>';
                        }
                        break;
                }
                ?>
            </div>
        <?php
        sky360_content_wrapper_end();
        sky360_admin_page_end();
    }
    
    /**
     * Enqueue admin assets
     */
    public function enqueue_admin_assets($hook) {
        // Only load on our admin page
        if (strpos($hook, 'sky-seo-whatsapp') === false) {
            return;
        }
        
        $version = defined('SKY_SEO_BOOST_VERSION') ? SKY_SEO_BOOST_VERSION : '1.2.0';
        
        // Get current tab - CHANGED: Default is now 'tracking'
        $current_tab = isset($_GET['tab']) ? sanitize_text_field($_GET['tab']) : 'tracking';
        
        // Always enqueue the main admin CSS for consistent styling
        wp_enqueue_style(
            'sky-seo-whatsapp-admin',
            $this->module_url . 'assets/css/whatsapp-admin.css',
            ['wp-components', 'dashicons'],
            $version
        );
        
        // Enqueue additional CSS based on tab
        if ($current_tab === 'tracking') {
            wp_enqueue_style(
                'sky-seo-whatsapp-tracking',
                $this->module_url . 'assets/css/whatsapp-tracking.css',
                ['dashicons'],
                $version
            );
        }
        
        // Enqueue JS based on tab
        if ($current_tab === 'configuration') {
            wp_enqueue_script(
                'sky-seo-whatsapp-admin',
                $this->module_url . 'assets/js/whatsapp-admin.js',
                ['jquery', 'wp-util', 'wp-media-utils'],
                $version,
                true
            );
            
            // Localize configuration script
            wp_localize_script('sky-seo-whatsapp-admin', 'skySeoWhatsApp', [
                'ajaxurl' => admin_url('admin-ajax.php'),
                'nonce' => wp_create_nonce('sky_seo_whatsapp_nonce'),
                'strings' => [
                    'saving' => __('Saving...', 'sky360'),
                    'saved' => __('Settings saved successfully!', 'sky360'),
                    'error' => __('An error occurred. Please try again.', 'sky360'),
                    'phoneRequired' => __('Phone number is required', 'sky360'),
                    'phoneInvalid' => __('Please enter a valid phone number with country code', 'sky360'),
                    'selectImage' => __('Select Profile Photo', 'sky360'),
                    'useImage' => __('Use this image', 'sky360'),
                    'unsavedChanges' => __('You have unsaved changes. Are you sure you want to leave?', 'sky360'),
                ]
            ]);
            
            // Media uploader for configuration
            wp_enqueue_media();
            
        } elseif ($current_tab === 'tracking') {
            // Chart.js for tracking
            wp_enqueue_script(
                'chart-js', 
                'https://cdn.jsdelivr.net/npm/chart.js@4.4.0/dist/chart.umd.js', 
                [], 
                '4.4.0', 
                true
            );
            
            // jQuery UI for datepicker
            wp_enqueue_script('jquery-ui-datepicker');
            wp_enqueue_style(
                'jquery-ui-datepicker-style',
                'https://ajax.googleapis.com/ajax/libs/jqueryui/1.12.1/themes/smoothness/jquery-ui.css',
                [],
                '1.12.1'
            );
            
            // Tracking JS
            wp_enqueue_script(
                'sky-seo-whatsapp-tracking',
                $this->module_url . 'assets/js/whatsapp-tracking.js',
                ['jquery', 'chart-js', 'jquery-ui-datepicker'],
                $version,
                true
            );
            
            // Localize tracking script
            wp_localize_script('sky-seo-whatsapp-tracking', 'skySeoWhatsAppTracking', [
                'ajaxurl' => admin_url('admin-ajax.php'),
                'nonce' => wp_create_nonce('sky_seo_whatsapp_nonce'),
                'strings' => [
                    'confirmClear' => __('Are you sure you want to clear all tracking data? This cannot be undone.', 'sky360'),
                    'exporting' => __('Exporting...', 'sky360'),
                    'noData' => __('No data to export.', 'sky360'),
                    'loadError' => __('Failed to load tracking data', 'sky360'),
                    'exportError' => __('Failed to export data', 'sky360'),
                    'loading' => __('Loading...', 'sky360'),
                ]
            ]);
        }
    }
    
    /**
     * Enqueue frontend assets - ENHANCED WITH NEW FEATURES
     */
    public function enqueue_frontend_assets() {
        // Get settings
        $settings = get_option('sky_seo_whatsapp_config', []);

        // Check if enabled
        if (empty($settings['enabled']) || $settings['status'] === 'offline') {
            return;
        }

        $version = defined('SKY_SEO_BOOST_VERSION') ? SKY_SEO_BOOST_VERSION : '1.0.0';

        // Enqueue Dashicons for frontend (needed for social proof icon)
        wp_enqueue_style('dashicons');

        // Enqueue CSS
        wp_enqueue_style(
            'sky-seo-whatsapp-frontend',
            $this->module_url . 'assets/css/whatsapp-frontend.css',
            ['dashicons'],
            $version
        );

        // Enqueue JS
        wp_enqueue_script(
            'sky-seo-whatsapp-frontend',
            $this->module_url . 'assets/js/whatsapp-frontend.js',
            ['jquery'],
            $version,
            true
        );

        // Build smart triggers config
        $smart_triggers = [
            'enabled' => !empty($settings['enable_smart_triggers']),
            'scroll' => [
                'enabled' => !empty($settings['trigger_scroll_enabled']),
                'percentage' => isset($settings['trigger_scroll_percentage']) ? intval($settings['trigger_scroll_percentage']) : 50,
            ],
            'exitIntent' => !empty($settings['trigger_exit_intent']),
            'timeOnPage' => [
                'enabled' => !empty($settings['trigger_time_on_page']),
                'seconds' => isset($settings['trigger_time_seconds']) ? intval($settings['trigger_time_seconds']) : 30,
            ],
            'pageViews' => [
                'enabled' => !empty($settings['trigger_page_views']),
                'count' => isset($settings['trigger_page_views_count']) ? intval($settings['trigger_page_views_count']) : 2,
            ],
        ];

        // Localize script with enhanced config
        wp_localize_script('sky-seo-whatsapp-frontend', 'skySeoWhatsAppFront', [
            'ajaxurl' => admin_url('admin-ajax.php'),
            'nonce' => wp_create_nonce('sky_seo_whatsapp_track_nonce'),
            'config' => [
                'phone' => isset($settings['phone']) ? $settings['phone'] : '',
                'message' => isset($settings['default_message']) ? $settings['default_message'] : '',
                'position' => isset($settings['float_position']) ? $settings['float_position'] : 'bottom-right',
                'showPopup' => isset($settings['show_popup']) ? $settings['show_popup'] : true,
                'popupDelay' => isset($settings['popup_delay']) ? intval($settings['popup_delay']) : 0,
                // NEW: Typing animation
                'typingAnimation' => !empty($settings['enable_typing_animation']),
                'typingDuration' => isset($settings['typing_duration']) ? intval($settings['typing_duration']) : 1500,
                // NEW: Smart triggers
                'smartTriggers' => $smart_triggers,
            ]
        ]);
    }
    
    /**
     * Create database table
     */
    private function create_database_table() {
        global $wpdb;
        
        $charset_collate = $wpdb->get_charset_collate();
        
        $sql = "CREATE TABLE IF NOT EXISTS {$this->table_name} (
            id bigint(20) NOT NULL AUTO_INCREMENT,
            click_time datetime DEFAULT CURRENT_TIMESTAMP,
            ip_address varchar(45) DEFAULT NULL,
            country varchar(100) DEFAULT NULL,
            city varchar(100) DEFAULT NULL,
            referrer_url text,
            page_url text,
            page_title varchar(255) DEFAULT NULL,
            device_type varchar(50) DEFAULT NULL,
            browser varchar(100) DEFAULT NULL,
            os varchar(100) DEFAULT NULL,
            click_type varchar(50) DEFAULT NULL,
            source varchar(255) DEFAULT NULL,
            user_agent text,
            session_id varchar(128) DEFAULT NULL,
            user_id bigint(20) DEFAULT NULL,
            PRIMARY KEY (id),
            KEY click_time (click_time),
            KEY country (country),
            KEY city (city),
            KEY device_type (device_type),
            KEY click_type (click_type),
            KEY source (source),
            KEY idx_page_title (page_title),
            KEY idx_session_id (session_id)
        ) $charset_collate;";
        
        require_once(ABSPATH . 'wp-admin/includes/upgrade.php');
        dbDelta($sql);
        
        // Force check and add missing columns
        $this->ensure_all_columns();
        
        // Update database version
        update_option('sky_seo_whatsapp_db_version', '1.0.3');
    }
    
    /**
     * Ensure all columns exist
     */
    private function ensure_all_columns() {
        global $wpdb;
        
        // Check if table exists first
        $table_exists = $wpdb->get_var("SHOW TABLES LIKE '{$this->table_name}'") === $this->table_name;
        
        if (!$table_exists) {
            return; // Table doesn't exist, create_database_table will handle it
        }
        
        // Get existing columns
        $existing_columns = $wpdb->get_col("SHOW COLUMNS FROM {$this->table_name}");
        
        // Define all required columns with their definitions
        $required_columns = [
            'source' => "ADD COLUMN source VARCHAR(255) DEFAULT NULL AFTER click_type"
        ];
        
        foreach ($required_columns as $column => $definition) {
            if (!in_array($column, $existing_columns)) {
                $wpdb->query("ALTER TABLE {$this->table_name} {$definition}");
            }
        }
    }
    
    /**
     * Render floating widget - ENHANCED WITH ALL NEW FEATURES
     */
    public function render_floating_widget() {
        $settings = get_option('sky_seo_whatsapp_config', []);

        // Check if enabled
        if (empty($settings['enabled'])) {
            return;
        }

        // Don't show in admin
        if (is_admin()) {
            return;
        }

        // Check display rules
        if (!$this->should_display_widget()) {
            return;
        }

        // Get configuration
        $phone = isset($settings['phone']) ? $settings['phone'] : '';
        $display_name = isset($settings['display_name']) ? $settings['display_name'] : '';
        $profile_photo = isset($settings['profile_photo']) ? $settings['profile_photo'] : '';
        $show_verified = isset($settings['show_verified']) ? $settings['show_verified'] : false;
        $position = isset($settings['float_position']) ? $settings['float_position'] : 'bottom-right';
        $show_popup = isset($settings['show_popup']) ? $settings['show_popup'] : true;
        $status = isset($settings['status']) ? $settings['status'] : 'online';
        $default_message = isset($settings['default_message']) ? $settings['default_message'] : '';

        // Get custom text fields
        $status_text = isset($settings['status_text']) ? $settings['status_text'] : __('Typically replies instantly', 'sky360');
        $start_chat_text = isset($settings['start_chat_text']) ? $settings['start_chat_text'] : __('Start Chat', 'sky360');

        // NEW: Check business hours
        $business_hours = $this->check_business_hours();
        $is_open = $business_hours['is_open'];
        $offline_message = $business_hours['offline_message'];

        // NEW: Get personalized greeting (or default description)
        $description = $this->get_personalized_greeting();
        if (empty($description)) {
            $description = isset($settings['description']) ? $settings['description'] : '';
        }

        // NEW: Get social proof
        $social_proof = $this->get_social_proof();

        // NEW: Typing animation settings
        $enable_typing = !empty($settings['enable_typing_animation']);
        $typing_duration = isset($settings['typing_duration']) ? intval($settings['typing_duration']) : 1500;

        // NEW: Smart triggers settings
        $smart_triggers = [
            'enabled' => !empty($settings['enable_smart_triggers']),
            'scroll' => [
                'enabled' => !empty($settings['trigger_scroll_enabled']),
                'percentage' => isset($settings['trigger_scroll_percentage']) ? intval($settings['trigger_scroll_percentage']) : 50,
            ],
            'exit_intent' => !empty($settings['trigger_exit_intent']),
            'time_on_page' => [
                'enabled' => !empty($settings['trigger_time_on_page']),
                'seconds' => isset($settings['trigger_time_seconds']) ? intval($settings['trigger_time_seconds']) : 30,
            ],
            'page_views' => [
                'enabled' => !empty($settings['trigger_page_views']),
                'count' => isset($settings['trigger_page_views_count']) ? intval($settings['trigger_page_views_count']) : 2,
            ],
        ];

        if (empty($phone)) {
            return;
        }

        // Position classes
        $position_class = 'sky-whatsapp-' . $position;

        // Status class (online/offline based on business hours)
        $status_class = $is_open ? 'sky-whatsapp-online' : 'sky-whatsapp-offline';

        ?>
        <div class="sky-whatsapp-widget <?php echo esc_attr($position_class); ?> <?php echo esc_attr($status_class); ?>"
             data-phone="<?php echo esc_attr($phone); ?>"
             data-message="<?php echo esc_attr($default_message); ?>"
             data-typing="<?php echo $enable_typing ? 'true' : 'false'; ?>"
             data-typing-duration="<?php echo esc_attr($typing_duration); ?>"
             data-is-open="<?php echo $is_open ? 'true' : 'false'; ?>"
             data-smart-triggers="<?php echo esc_attr(wp_json_encode($smart_triggers)); ?>">

            <div class="sky-whatsapp-button">
                <svg viewBox="0 0 24 24" width="24" height="24">
                    <path fill="currentColor" d="M17.472 14.382c-.297-.149-1.758-.867-2.03-.967-.273-.099-.471-.148-.67.15-.197.297-.767.966-.94 1.164-.173.199-.347.223-.644.075-.297-.15-1.255-.463-2.39-1.475-.883-.788-1.48-1.761-1.653-2.059-.173-.297-.018-.458.13-.606.134-.133.298-.347.446-.52.149-.174.198-.298.298-.497.099-.198.05-.371-.025-.52-.075-.149-.669-1.612-.916-2.207-.242-.579-.487-.5-.669-.51-.173-.008-.371-.01-.57-.01-.198 0-.52.074-.792.372-.272.297-1.04 1.016-1.04 2.479 0 1.462 1.065 2.875 1.213 3.074.149.198 2.096 3.2 5.077 4.487.709.306 1.262.489 1.694.625.712.227 1.36.195 1.871.118.571-.085 1.758-.719 2.006-1.413.248-.694.248-1.289.173-1.413-.074-.124-.272-.198-.57-.347m-5.421 7.403h-.004a9.87 9.87 0 01-5.031-1.378l-.361-.214-3.741.982.998-3.648-.235-.374a9.86 9.86 0 01-1.51-5.26c.001-5.45 4.436-9.884 9.888-9.884 2.64 0 5.122 1.03 6.988 2.898a9.825 9.825 0 012.893 6.994c-.003 5.45-4.437 9.884-9.885 9.884m8.413-18.297A11.815 11.815 0 0012.05 0C5.495 0 .16 5.335.157 11.892c0 2.096.547 4.142 1.588 5.945L.057 24l6.305-1.654a11.882 11.882 0 005.683 1.448h.005c6.554 0 11.89-5.335 11.893-11.893a11.821 11.821 0 00-3.48-8.413Z"/>
                </svg>
                <?php if (!$is_open): ?>
                    <span class="sky-whatsapp-offline-badge" title="<?php esc_attr_e('Currently offline', 'sky360'); ?>"></span>
                <?php endif; ?>
            </div>

            <?php if ($show_popup): ?>
            <div class="sky-whatsapp-popup">
                <div class="sky-whatsapp-popup-inner">
                    <div class="sky-whatsapp-popup-header">
                        <?php if ($profile_photo): ?>
                            <img src="<?php echo esc_url($profile_photo); ?>" alt="<?php echo esc_attr($display_name); ?>" class="sky-whatsapp-avatar">
                        <?php else: ?>
                            <div class="sky-whatsapp-avatar-placeholder">
                                <svg viewBox="0 0 212 212" width="40" height="40">
                                    <path fill="#DFE5E7" d="M106 0C47.6 0 0 47.6 0 106s47.6 106 106 106 106-47.6 106-106S164.4 0 106 0zm0 40c22.1 0 40 17.9 40 40s-17.9 40-40 40-40-17.9-40-40 17.9-40 40-40zm0 150c-26.7 0-50.5-12.1-66.3-31.1 8.1-15.8 24.3-26.9 43.3-28.7 2.6 1.2 5.4 2.2 8.4 2.8 4.2.9 8.5 1.4 13 1.4s8.8-.5 13-1.4c3-.6 5.8-1.6 8.4-2.8 19 1.8 35.2 12.9 43.3 28.7C156.5 177.9 132.7 190 106 190z"/>
                                </svg>
                            </div>
                        <?php endif; ?>
                        <div class="sky-whatsapp-info">
                            <h4>
                                <?php echo esc_html($display_name ?: __('WhatsApp Support', 'sky360')); ?>
                                <?php if ($show_verified): ?>
                                    <img src="<?php echo esc_url($this->module_url . 'assets/icon/verified.svg'); ?>"
                                         alt="<?php esc_attr_e('Verified', 'sky360'); ?>"
                                         class="sky-whatsapp-verified">
                                <?php endif; ?>
                            </h4>
                            <p class="sky-whatsapp-status <?php echo $is_open ? 'online' : 'offline'; ?>">
                                <?php if (!$is_open): ?>
                                    <span class="sky-whatsapp-status-indicator offline"></span>
                                    <?php esc_html_e('Offline', 'sky360'); ?>
                                <?php else: ?>
                                    <span class="sky-whatsapp-status-indicator online"></span>
                                    <?php echo esc_html($status_text); ?>
                                <?php endif; ?>
                            </p>
                        </div>
                        <button class="sky-whatsapp-close" aria-label="<?php esc_attr_e('Close', 'sky360'); ?>">&times;</button>
                    </div>

                    <div class="sky-whatsapp-popup-body">
                        <?php if (!$is_open && $offline_message): ?>
                            <!-- Offline Message -->
                            <div class="sky-whatsapp-offline-notice">
                                <span class="dashicons dashicons-clock"></span>
                                <p><?php echo esc_html($offline_message); ?></p>
                            </div>
                        <?php endif; ?>

                        <?php if ($description): ?>
                            <div class="sky-whatsapp-message-container">
                                <?php if ($enable_typing): ?>
                                    <!-- Typing Animation -->
                                    <div class="sky-whatsapp-typing" style="display: none;">
                                        <span class="sky-whatsapp-typing-dot"></span>
                                        <span class="sky-whatsapp-typing-dot"></span>
                                        <span class="sky-whatsapp-typing-dot"></span>
                                    </div>
                                <?php endif; ?>

                                <div class="sky-whatsapp-message" <?php echo $enable_typing ? 'style="display: none;"' : ''; ?>>
                                    <div class="sky-whatsapp-message-header">
                                        <span class="sky-whatsapp-message-name"><?php echo esc_html($display_name ?: __('Support', 'sky360')); ?></span>
                                        <?php if ($show_verified): ?>
                                            <span class="sky-whatsapp-message-verified">
                                                <img src="<?php echo esc_url($this->module_url . 'assets/icon/verified.svg'); ?>"
                                                     alt="<?php esc_attr_e('Verified', 'sky360'); ?>">
                                            </span>
                                        <?php endif; ?>
                                    </div>
                                    <p><?php
                                        $desc_parts = explode('\n', $description);
                                        echo implode('<br>', array_map('esc_html', $desc_parts));
                                    ?></p>
                                    <span class="message-time"><?php echo esc_html(wp_date('g:i')); ?></span>
                                </div>
                            </div>
                        <?php endif; ?>

                        <?php if ($social_proof['enabled']): ?>
                            <!-- Social Proof Counter -->
                            <div class="sky-whatsapp-social-proof">
                                <svg class="sky-whatsapp-social-icon" xmlns="http://www.w3.org/2000/svg" width="16" height="16" viewBox="0 0 24 24" fill="currentColor"><path d="M12 12c2.21 0 4-1.79 4-4s-1.79-4-4-4-4 1.79-4 4 1.79 4 4 4zm-6 2c-1.66 0-3-1.34-3-3s1.34-3 3-3c.68 0 1.3.23 1.8.61-.46.95-.72 2.02-.72 3.14 0 .82.14 1.6.39 2.33-.45.56-1.13.92-1.47.92zm12 0c-.34 0-1.02-.36-1.47-.92.25-.73.39-1.51.39-2.33 0-1.12-.26-2.19-.72-3.14.5-.38 1.12-.61 1.8-.61 1.66 0 3 1.34 3 3s-1.34 3-3 3zm-6 1c-2.67 0-8 1.34-8 4v2h16v-2c0-2.66-5.33-4-8-4zm-8 0c-.29 0-.62.02-.97.05.02.01.97.95.97 1.95v2H1v-1.5C1 14.9 3.13 14.6 4.97 14.18c.1-.41.21-.8.35-1.18zm14.03 0c.14.38.25.77.35 1.18 1.84.42 3.97.72 3.97 2.32V18h-3v-2c0-1-.95-1.94-.97-1.95-.35-.03-.68-.05-.97-.05z"/></svg>
                                <span class="sky-whatsapp-social-proof-text"><?php echo esc_html($social_proof['text']); ?></span>
                            </div>
                        <?php endif; ?>
                    </div>

                    <div class="sky-whatsapp-popup-footer">
                        <button class="sky-whatsapp-start-chat"
                                data-phone="<?php echo esc_attr($phone); ?>"
                                data-message="<?php echo esc_attr($default_message); ?>">
                            <?php echo esc_html($start_chat_text); ?>
                        </button>
                    </div>
                </div>
            </div>
            <?php endif; ?>
        </div>
        <?php
    }
    
    /**
     * Handle trackable button redirect - IMPROVED
     */
    public function handle_trackable_button_redirect() {
        // Check if this is a WhatsApp button click
        if (!isset($_GET['sky_whatsapp_button']) || $_GET['sky_whatsapp_button'] != '1') {
            return;
        }
        
        // Start session if not started
        if (!session_id()) {
            session_start();
        }
        
        // Get WhatsApp settings
        $settings = get_option('sky_seo_whatsapp_config', []);
        $phone = isset($settings['phone']) ? $settings['phone'] : '';
        $message = isset($settings['default_message']) ? $settings['default_message'] : '';
        
        if (empty($phone)) {
            wp_die(esc_html__('WhatsApp not configured. Please configure your WhatsApp phone number.', 'sky360'));
        }
        
        // Get referrer page info for tracking
        $referrer_url = isset($_SERVER['HTTP_REFERER']) ? esc_url_raw($_SERVER['HTTP_REFERER']) : '';
        $referrer_title = '';
        
        // Try to get page title from referrer URL
        if ($referrer_url) {
            $page_id = url_to_postid($referrer_url);
            if ($page_id) {
                $referrer_title = get_the_title($page_id);
            }
        }
        
        // Prevent duplicate tracking within same hour
        $session_key = 'whatsapp_tracked_' . md5($referrer_url . date('YmdH'));
        $track_click = true;
        
        if (isset($_SESSION[$session_key])) {
            $track_click = false;
        } else {
            $_SESSION[$session_key] = true;
        }
        
        // Track the button click if not already tracked
        if ($track_click) {
            if ($this->tracking) {
                // Set up POST data for tracking
                $_POST['page_url'] = $referrer_url;
                $_POST['page_title'] = $referrer_title ?: 'Unknown Page';
                $_POST['referrer'] = $referrer_url;
                $_POST['click_type'] = 'button';
                $_POST['source'] = 'Button Click';
                
                $this->tracking->track_click_internal('button', 'Button Click');
            } else {
                // If tracking isn't loaded yet, do a direct database insert
                global $wpdb;
                $table_name = $wpdb->prefix . 'sky_seo_whatsapp_tracking';
                
                // Check if table exists before inserting
                if ($wpdb->get_var("SHOW TABLES LIKE '{$table_name}'") === $table_name) {
                    // Simple tracking data
                    $wpdb->insert($table_name, [
                        'click_time' => current_time('mysql'),
                        'page_url' => $referrer_url,
                        'page_title' => $referrer_title ?: 'Unknown Page',
                        'referrer_url' => $referrer_url,
                        'click_type' => 'button',
                        'source' => 'Button Click',
                        'ip_address' => isset($_SERVER['REMOTE_ADDR']) ? sanitize_text_field(wp_unslash($_SERVER['REMOTE_ADDR'])) : '',
                        'user_agent' => isset($_SERVER['HTTP_USER_AGENT']) ? sanitize_text_field(wp_unslash($_SERVER['HTTP_USER_AGENT'])) : '',
                        'device_type' => wp_is_mobile() ? 'mobile' : 'desktop',
                        'session_id' => isset($_COOKIE['sky_whatsapp_session']) ? $_COOKIE['sky_whatsapp_session'] : wp_generate_uuid4()
                    ]);
                }
            }
        }
        
        // Clean phone number
        $clean_phone = preg_replace('/[^0-9+]/', '', $phone);
        
        // Detect if mobile or desktop
        $is_mobile = wp_is_mobile();
        
        // Build WhatsApp URL based on device
        if ($is_mobile) {
            // Mobile: Use wa.me which opens WhatsApp app
            $whatsapp_url = sprintf(
                'https://wa.me/%s?text=%s',
                $clean_phone,
                urlencode($message)
            );
        } else {
            // Desktop: Use web.whatsapp.com
            $whatsapp_url = sprintf(
                'https://web.whatsapp.com/send?phone=%s&text=%s',
                $clean_phone,
                urlencode($message)
            );
        }
        
        // Redirect to WhatsApp
        wp_redirect($whatsapp_url);
        exit;
    }
    
    /**
     * Add query vars
     */
    public function add_query_vars($vars) {
        $vars[] = 'sky_whatsapp_button';
        return $vars;
    }
    
    /**
     * Handle WhatsApp redirect using template_redirect
     */
    public function handle_whatsapp_redirect() {
        if (get_query_var('sky_whatsapp_button') === '1') {
            $this->handle_trackable_button_redirect();
        }
    }
    
    /**
     * Check if currently within business hours
     *
     * @return array ['is_open' => bool, 'offline_message' => string]
     */
    private function check_business_hours() {
        $settings = get_option('sky_seo_whatsapp_config', []);

        // If business hours not enabled, always open
        if (empty($settings['enable_business_hours'])) {
            return ['is_open' => true, 'offline_message' => ''];
        }

        $business_hours = isset($settings['business_hours']) ? $settings['business_hours'] : [];
        $timezone = isset($settings['timezone']) ? $settings['timezone'] : wp_timezone_string();
        $offline_message = isset($settings['offline_message']) ? $settings['offline_message'] : __('We are currently offline. Please leave a message and we\'ll get back to you.', 'sky360');

        // Get current time in configured timezone
        $tz = new DateTimeZone($timezone);
        $now = new DateTime('now', $tz);
        $current_day = strtolower($now->format('l'));
        $current_time = $now->format('H:i');

        // Check if today is enabled
        if (empty($business_hours[$current_day]['enabled'])) {
            return ['is_open' => false, 'offline_message' => $offline_message];
        }

        $open_time = isset($business_hours[$current_day]['open']) ? $business_hours[$current_day]['open'] : '09:00';
        $close_time = isset($business_hours[$current_day]['close']) ? $business_hours[$current_day]['close'] : '17:00';

        // Compare times
        $is_open = ($current_time >= $open_time && $current_time <= $close_time);

        return [
            'is_open' => $is_open,
            'offline_message' => $is_open ? '' : $offline_message
        ];
    }

    /**
     * Get personalized greeting based on time of day
     *
     * @return string Greeting message
     */
    private function get_personalized_greeting() {
        $settings = get_option('sky_seo_whatsapp_config', []);

        // If personalized greeting not enabled, return default description
        if (empty($settings['enable_personalized_greeting'])) {
            return isset($settings['description']) ? $settings['description'] : '';
        }

        $timezone = isset($settings['timezone']) ? $settings['timezone'] : wp_timezone_string();
        $tz = new DateTimeZone($timezone);
        $now = new DateTime('now', $tz);
        $hour = (int) $now->format('G');

        // Morning: 5am - 12pm
        if ($hour >= 5 && $hour < 12) {
            return isset($settings['greeting_morning']) ? $settings['greeting_morning'] : __('Good morning! ☀️ How can we help you today?', 'sky360');
        }
        // Afternoon: 12pm - 5pm
        elseif ($hour >= 12 && $hour < 17) {
            return isset($settings['greeting_afternoon']) ? $settings['greeting_afternoon'] : __('Good afternoon! How can we assist you?', 'sky360');
        }
        // Evening: 5pm - 9pm
        elseif ($hour >= 17 && $hour < 21) {
            return isset($settings['greeting_evening']) ? $settings['greeting_evening'] : __('Good evening! How can we help you?', 'sky360');
        }
        // Night: 9pm - 5am
        else {
            return isset($settings['greeting_night']) ? $settings['greeting_night'] : __('Hello! Thanks for reaching out. How can we help?', 'sky360');
        }
    }

    /**
     * Generate social proof count
     *
     * @return array ['enabled' => bool, 'text' => string, 'count' => int]
     */
    private function get_social_proof() {
        $settings = get_option('sky_seo_whatsapp_config', []);

        if (empty($settings['enable_social_proof'])) {
            return ['enabled' => false, 'text' => '', 'count' => 0];
        }

        $text = isset($settings['social_proof_text']) ? $settings['social_proof_text'] : __('%count% people contacted us today', 'sky360');
        $min = isset($settings['social_proof_min']) ? intval($settings['social_proof_min']) : 5;
        $max = isset($settings['social_proof_max']) ? intval($settings['social_proof_max']) : 25;

        // Generate a consistent random number based on the day (changes daily)
        $seed = date('Ymd');
        mt_srand(crc32($seed));
        $count = mt_rand($min, $max);
        mt_srand(); // Reset seed

        return [
            'enabled' => true,
            'text' => str_replace('%count%', $count, $text),
            'count' => $count
        ];
    }

    /**
     * Check if widget should be displayed on current page
     */
    private function should_display_widget() {
        $settings = get_option('sky_seo_whatsapp_config', []);
        
        // Check homepage
        if (is_front_page() && empty($settings['show_on_home'])) {
            return false;
        }
        
        // Check pages
        if (is_page() && empty($settings['show_on_pages'])) {
            return false;
        }
        
        // Check posts
        if (is_single() && get_post_type() === 'post' && empty($settings['show_on_posts'])) {
            return false;
        }
        
        // Check products
        if (class_exists('WooCommerce') && is_product() && empty($settings['show_on_products'])) {
            return false;
        }
        
        // Check Sky SEO post types
        if (is_singular('sky_areas') && empty($settings['show_on_sky_areas'])) {
            return false;
        }
        
        if (is_singular('sky_trending') && empty($settings['show_on_sky_trending'])) {
            return false;
        }
        
        if (is_singular('sky_sectors') && empty($settings['show_on_sky_sectors'])) {
            return false;
        }
        
        // Check excluded pages
        if (!empty($settings['exclude_pages']) && is_page($settings['exclude_pages'])) {
            return false;
        }
        
        return true;
    }
    
    /**
     * AJAX: Save configuration
     */
    public function ajax_save_configuration() {
        // Verify nonce
        if (!check_ajax_referer('sky_seo_whatsapp_nonce', 'nonce', false)) {
            wp_die('Security check failed');
        }
        
        // Check permissions
        if (!current_user_can('manage_options')) {
            wp_die('Insufficient permissions');
        }
        
        // Delegate to configuration class
        if ($this->configuration) {
            $this->configuration->save_configuration();
        } else {
            wp_send_json_error(['message' => __('Configuration module not loaded', 'sky360')]);
        }
    }
    
    /**
     * AJAX: Track click
     */
    public function ajax_track_click() {
        // Verify nonce
        if (!check_ajax_referer('sky_seo_whatsapp_track_nonce', 'nonce', false)) {
            wp_send_json_error(['message' => 'Security check failed']);
            return;
        }
        
        // Delegate to tracking class
        if ($this->tracking) {
            $this->tracking->track_click();
        } else {
            wp_send_json_error(['message' => 'Tracking module not loaded']);
        }
    }
    
    /**
     * AJAX: Get tracking data
     */
    public function ajax_get_tracking_data() {
        // Delegate to tracking class
        if ($this->tracking) {
            $this->tracking->ajax_get_tracking_data();
        } else {
            wp_send_json_error(['message' => 'Tracking module not loaded']);
        }
    }
    
    /**
     * AJAX: Export data
     */
    public function ajax_export_data() {
        // Delegate to tracking class
        if ($this->tracking) {
            $this->tracking->ajax_export_data();
        } else {
            wp_send_json_error(['message' => 'Tracking module not loaded']);
        }
    }
    
    /**
     * AJAX: Get page details - FIXED METHOD NAME
     */
    public function ajax_get_page_details() {
        // Delegate to tracking class
        if ($this->tracking) {
            $this->tracking->ajax_get_page_details();
        } else {
            wp_send_json_error(['message' => 'Tracking module not loaded']);
        }
    }
    
    /**
     * Activate module
     */
    public function activate() {
        $this->create_database_table();
        
        // Set default options
        $default_config = [
            'enabled' => false,
            'phone' => '',
            'display_name' => get_bloginfo('name'),
            'status' => 'online',
            'status_text' => __('Typically replies instantly', 'sky360'),
            'description' => __('Hi! Click below to chat with us on WhatsApp', 'sky360'),
            'show_verified' => false,
            'float_position' => 'bottom-right',
            'show_popup' => true,
            'default_message' => __('Hello, I would like to know more about your services.', 'sky360'),
            'start_chat_text' => __('Start Chat', 'sky360'),
            'show_on_mobile' => true,
            'show_on_desktop' => true,
            'show_on_home' => true,
            'show_on_pages' => true,
            'show_on_posts' => true,
            'show_on_products' => true,
            'show_on_sky_areas' => true,
            'show_on_sky_trending' => true,
            'show_on_sky_sectors' => true,
            'exclude_pages' => [],
        ];
        
        add_option('sky_seo_whatsapp_config', $default_config);
    }
    
    /**
     * Deactivate module
     */
    public function deactivate() {
        // Clean up scheduled events if any
    }
}

// Initialize module
add_action('plugins_loaded', function() {
    Sky_SEO_WhatsApp_Business::get_instance();
}, 20);