<?php
/**
 * Sky SEO Elementor Widgets Loader - Optimized Version
 * Loads all Elementor widgets and assets properly
 * 
 * @package SkySEOBoost
 * @subpackage Elementor
 * @since 3.1.0
 */

// Prevent direct access
if (!defined('ABSPATH')) {
    exit;
}

/**
 * Sky SEO Elementor Loader Class
 */
class Sky_SEO_Elementor_Loader {
    
    /**
     * Instance
     */
    private static $instance = null;
    
    /**
     * Widget files loaded flag
     */
    private static $widgets_loaded = false;
    
    /**
     * Widget classes to register
     */
    private $widget_classes = [
        'Sky_SEO_Business_Info_Elementor_Widget',
        'Sky_SEO_Reviews_Elementor_Widget'
    ];
    
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
    public function __construct() {
        // Early initialization
        add_action('init', [$this, 'init'], 5);
        
        // Elementor specific hooks
        add_action('elementor/widgets/register', [$this, 'register_widgets'], 10);
        add_action('elementor/elements/categories_registered', [$this, 'add_widget_category'], 10);
        
        // Asset loading - simplified approach
        add_action('wp_enqueue_scripts', [$this, 'register_frontend_assets'], 5);
        add_action('elementor/editor/before_enqueue_scripts', [$this, 'register_assets_for_editor'], 5);
        
        // Ensure assets are loaded when widgets are rendered
        add_action('elementor/widget/before_render_content', [$this, 'enqueue_widget_assets'], 10);
        add_action('elementor/frontend/after_enqueue_styles', [$this, 'enqueue_widget_styles'], 10);
    }
    
    /**
     * Initialize - load files
     */
    public function init() {
        // Load widget files once
        $this->load_widget_files();
    }
    
    /**
     * Load widget files
     */
    public function load_widget_files() {
        if (self::$widgets_loaded) {
            return;
        }
        
        $widget_dir = plugin_dir_path(__FILE__);
        
        // Widget file mapping
        $widget_files = [
            'elementor-business-info-widget.php',
            'elementor-reviews-widget.php'
        ];
        
        foreach ($widget_files as $file) {
            $file_path = $widget_dir . $file;
            
            if (file_exists($file_path)) {
                require_once $file_path;
            }
        }
        
        self::$widgets_loaded = true;
    }
    
    /**
     * Register frontend assets
     */
    public function register_frontend_assets() {
        $this->register_all_assets();
    }
    
    /**
     * Register assets for editor
     */
    public function register_assets_for_editor() {
        $this->register_all_assets();
        
        // Auto-enqueue in editor
        if (\Elementor\Plugin::$instance->editor->is_edit_mode()) {
            $this->enqueue_all_assets();
        }
    }
    
    /**
     * Register all assets
     */
    private function register_all_assets() {
        $base_url = plugin_dir_url(__FILE__);
        $version = defined('SKY_SEO_BOOST_VERSION') ? SKY_SEO_BOOST_VERSION : '3.1.0';
        
        // Check if assets exist
        $css_file = plugin_dir_path(__FILE__) . 'assets/css/elementor.css';
        $js_file = plugin_dir_path(__FILE__) . 'assets/js/elementor.js';
        
        // Register Google Fonts
        wp_register_style(
            'sky-google-fonts',
            'https://fonts.googleapis.com/css2?family=Roboto:wght@400;500;700&display=swap',
            [],
            null
        );
        
        // Register Swiper
        wp_register_style(
            'sky-swiper-css',
            'https://cdn.jsdelivr.net/npm/swiper@10/swiper-bundle.min.css',
            [],
            '10.3.1'
        );
        
        wp_register_script(
            'sky-swiper-js',
            'https://cdn.jsdelivr.net/npm/swiper@10/swiper-bundle.min.js',
            [],
            '10.3.1',
            true
        );
        
        // Register main style
        if (file_exists($css_file)) {
            wp_register_style(
                'sky-elementor-style',
                $base_url . 'assets/css/elementor.css',
                ['sky-google-fonts', 'sky-swiper-css'],
                $version
            );
        }
        
        // Register main script
        if (file_exists($js_file)) {
            wp_register_script(
                'sky-elementor-script',
                $base_url . 'assets/js/elementor.js',
                ['jquery', 'sky-swiper-js'],
                $version,
                true
            );
            
            // Localize script
            wp_localize_script('sky-elementor-script', 'skySeoElementor', [
                'ajaxUrl' => admin_url('admin-ajax.php'),
                'nonce' => wp_create_nonce('sky_seo_elementor'),
                'i18n' => [
                    'loading' => __('Loading...', 'sky360'),
                    'error' => __('An error occurred', 'sky360'),
                    'noReviews' => __('No reviews found', 'sky360'),
                    'readMore' => __('Read more', 'sky360'),
                    'readLess' => __('Read less', 'sky360')
                ]
            ]);
            
            // Add separate localization for reviews AJAX
            wp_localize_script('sky-elementor-script', 'sky_seo_ajax', [
                'ajax_url' => admin_url('admin-ajax.php'),
                'nonce' => wp_create_nonce('sky_seo_reviews_nonce')
            ]);
        }
    }
    
    /**
     * Enqueue all assets
     */
    private function enqueue_all_assets() {
        // Enqueue styles
        $styles = ['sky-google-fonts', 'sky-swiper-css', 'sky-elementor-style'];
        foreach ($styles as $style) {
            if (wp_style_is($style, 'registered')) {
                wp_enqueue_style($style);
            }
        }
        
        // Enqueue scripts
        $scripts = ['sky-swiper-js', 'sky-elementor-script'];
        foreach ($scripts as $script) {
            if (wp_script_is($script, 'registered')) {
                wp_enqueue_script($script);
            }
        }
    }
    
    /**
     * Enqueue widget assets when widget is rendered
     */
    public function enqueue_widget_assets($widget) {
        // Check if it's one of our widgets
        $widget_name = $widget->get_name();
        
        if (in_array($widget_name, ['sky_seo_business_info', 'sky_seo_reviews'])) {
            $this->enqueue_all_assets();
        }
    }
    
    /**
     * Enqueue widget styles
     */
    public function enqueue_widget_styles() {
        // Check if we're on a page with Elementor content
        if (\Elementor\Plugin::$instance->db->is_built_with_elementor(get_the_ID())) {
            // Register assets if not already done
            $this->register_all_assets();
            
            // Check if page has our widgets
            if ($this->page_has_our_widgets()) {
                $this->enqueue_all_assets();
            }
        }
    }
    
    /**
     * Check if page has our widgets
     */
    private function page_has_our_widgets() {
        $post_id = get_the_ID();
        
        if (!$post_id) {
            return false;
        }
        
        // Get Elementor data
        $elementor_data = get_post_meta($post_id, '_elementor_data', true);
        
        if (empty($elementor_data)) {
            return false;
        }
        
        // Check if our widget names are in the data
        $our_widgets = ['sky_seo_business_info', 'sky_seo_reviews'];
        
        foreach ($our_widgets as $widget) {
            if (strpos($elementor_data, '"widgetType":"' . $widget . '"') !== false) {
                return true;
            }
        }
        
        return false;
    }
    
    /**
     * Add widget category
     */
    public function add_widget_category($elements_manager) {
        $elements_manager->add_category('sky360', [
            'title' => __('Sky SEO Boost', 'sky360'),
            'icon' => 'fa fa-rocket',
        ]);
    }
    
    /**
     * Register widgets
     */
    public function register_widgets($widgets_manager) {
        // Check license and dependencies
        if (!$this->can_register_widgets()) {
            return;
        }
        
        // Ensure widget files are loaded
        $this->load_widget_files();
        
        // Register each widget
        foreach ($this->widget_classes as $widget_class) {
            if (class_exists($widget_class)) {
                try {
                    $widgets_manager->register(new $widget_class());
                } catch (Exception $e) {
                    error_log('Sky SEO Elementor: Failed to register widget ' . $widget_class . ' - ' . $e->getMessage());
                }
            }
        }
    }
    
    /**
     * Check if we can register widgets
     */
    private function can_register_widgets() {
        // Check if license function exists and is valid
        if (function_exists('sky_seo_is_licensed') && !sky_seo_is_licensed()) {
            return false;
        }
        
        // Check if Business API is available
        if (!class_exists('Sky_SEO_Business_API')) {
            return false;
        }
        
        return true;
    }
}

// Initialize the loader with proper timing
add_action('plugins_loaded', function() {
    // Wait for Elementor to load
    if (did_action('elementor/loaded')) {
        Sky_SEO_Elementor_Loader::get_instance();
    } else {
        // If Elementor hasn't loaded yet, wait for it
        add_action('elementor/loaded', function() {
            Sky_SEO_Elementor_Loader::get_instance();
        });
    }
}, 20);

// Fallback to ensure assets are loaded in frontend
add_action('wp_enqueue_scripts', function() {
    // Only proceed if Elementor is active and we're on frontend
    if (!is_admin() && class_exists('\Elementor\Plugin')) {
        // Get the loader instance
        if (class_exists('Sky_SEO_Elementor_Loader')) {
            $loader = Sky_SEO_Elementor_Loader::get_instance();
            
            // Register assets
            $loader->register_frontend_assets();
        }
    }
}, 100);

// Provide global function for debugging
if (!function_exists('sky_seo_elementor_debug')) {
    function sky_seo_elementor_debug() {
        $debug_info = [
            'loader_exists' => class_exists('Sky_SEO_Elementor_Loader'),
            'elementor_active' => did_action('elementor/loaded'),
            'is_admin' => is_admin(),
            'styles_registered' => [
                'sky-elementor-style' => wp_style_is('sky-elementor-style', 'registered'),
                'sky-google-fonts' => wp_style_is('sky-google-fonts', 'registered'),
                'sky-swiper-css' => wp_style_is('sky-swiper-css', 'registered'),
            ],
            'scripts_registered' => [
                'sky-elementor-script' => wp_script_is('sky-elementor-script', 'registered'),
                'sky-swiper-js' => wp_script_is('sky-swiper-js', 'registered'),
            ],
            'styles_enqueued' => [
                'sky-elementor-style' => wp_style_is('sky-elementor-style', 'enqueued'),
                'sky-google-fonts' => wp_style_is('sky-google-fonts', 'enqueued'),
                'sky-swiper-css' => wp_style_is('sky-swiper-css', 'enqueued'),
            ],
            'scripts_enqueued' => [
                'sky-elementor-script' => wp_script_is('sky-elementor-script', 'enqueued'),
                'sky-swiper-js' => wp_script_is('sky-swiper-js', 'enqueued'),
            ]
        ];
        
        return $debug_info;
    }
}