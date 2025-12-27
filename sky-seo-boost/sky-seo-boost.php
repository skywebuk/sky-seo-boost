<?php
/**
 * Plugin Name: Sky360
 * Plugin URI: https://skywebdesign.co.uk/sky360
 * Description: Complete business toolkit with SEO content management, analytics, WhatsApp Business, UTM tracking, and Google Ads integration.
 * Version: 5.0.0
 * Author: Sky Web Design
 * Author URI: https://skywebdesign.co.uk
 * License: GPL v2 or later
 * License URI: https://www.gnu.org/licenses/gpl-2.0.html
 * Text Domain: sky360
 * Domain Path: /languages
 */

// Prevent direct access
if (!defined('ABSPATH')) {
    exit;
}

// Define plugin constants
define('SKY360_PLUGIN_URL', plugin_dir_url(__FILE__));
define('SKY360_PLUGIN_DIR', plugin_dir_path(__FILE__));
define('SKY360_VERSION', '5.0.0');
define('SKY360_FILE', __FILE__);

// Legacy constant aliases for backward compatibility
define('SKY_SEO_BOOST_PLUGIN_URL', SKY360_PLUGIN_URL);
define('SKY_SEO_BOOST_PLUGIN_DIR', SKY360_PLUGIN_DIR);
define('SKY_SEO_BOOST_VERSION', SKY360_VERSION);
define('SKY_SEO_BOOST_FILE', SKY360_FILE);

// Include additional PHP files
$plugin_dir = SKY_SEO_BOOST_PLUGIN_DIR;

// Load API infrastructure FIRST (required by other modules)
if (file_exists($plugin_dir . 'includes/api-config.php')) {
    require_once $plugin_dir . 'includes/api-config.php';
}

if (file_exists($plugin_dir . 'includes/api-request-handler.php')) {
    require_once $plugin_dir . 'includes/api-request-handler.php';
}

if (file_exists($plugin_dir . 'includes/license-signature-validator.php')) {
    require_once $plugin_dir . 'includes/license-signature-validator.php';
}

// Load seo-functions.php as it contains shared helper functions
if (file_exists($plugin_dir . 'includes/seo-functions.php')) {
    require_once $plugin_dir . 'includes/seo-functions.php';
}

// Then load admin functions
if (file_exists($plugin_dir . 'includes/admin-functions.php')) {
    require_once $plugin_dir . 'includes/admin-functions.php';
}

// Load analytics functions after seo-functions to ensure helper functions are available
if (file_exists($plugin_dir . 'includes/analytics-functions.php')) {
    require_once $plugin_dir . 'includes/analytics-functions.php';
}

// Load Business API module
if (file_exists($plugin_dir . 'includes/business-api/business-api.php')) {
    require_once $plugin_dir . 'includes/business-api/business-api.php';
}

// Load Elementor widgets
if (file_exists($plugin_dir . 'includes/elementor-links-widget.php')) {
    require_once $plugin_dir . 'includes/elementor-links-widget.php';
}

// Load UTM tracking module
if (file_exists($plugin_dir . 'includes/utm/utm-loader.php')) {
    require_once $plugin_dir . 'includes/utm/utm-loader.php';
}

// Load WhatsApp Business module
if (file_exists($plugin_dir . 'includes/whatsapp-business/whatsapp-business.php')) {
    require_once $plugin_dir . 'includes/whatsapp-business/whatsapp-business.php';
}

// Load Google Ads tracking system
if (file_exists($plugin_dir . 'includes/google-ads/google-ads-tracking.php')) {
    require_once $plugin_dir . 'includes/google-ads/google-ads-tracking.php';
}

// Load Google Ads admin interface
if (file_exists($plugin_dir . 'includes/google-ads/admin-google-ads.php')) {
    require_once $plugin_dir . 'includes/google-ads/admin-google-ads.php';
}

// Load tracking code manager
if (file_exists($plugin_dir . 'includes/tracking-code-manager.php')) {
    require_once $plugin_dir . 'includes/tracking-code-manager.php';
}

// Load license manager
if (file_exists($plugin_dir . 'includes/license-manager.php')) {
    require_once $plugin_dir . 'includes/license-manager.php';
}

// Load indexing boost features (NEW)
if (file_exists($plugin_dir . 'includes/indexing-boost.php')) {
    require_once $plugin_dir . 'includes/indexing-boost.php';
}

// Load admin pages that depend on the above functions
if (file_exists($plugin_dir . 'includes/admin-content.php')) {
    require_once $plugin_dir . 'includes/admin-content.php';
}

if (file_exists($plugin_dir . 'includes/admin-settings.php')) {
    require_once $plugin_dir . 'includes/admin-settings.php';
}

if (file_exists($plugin_dir . 'includes/admin-analytics-check.php')) {
    require_once $plugin_dir . 'includes/admin-analytics-check.php';
}

if (file_exists($plugin_dir . 'includes/admin-post-list.php')) {
    require_once $plugin_dir . 'includes/admin-post-list.php';
}

// Load two-step verification system (NEW)
if (file_exists($plugin_dir . 'includes/two-step-verification/two-step-loader.php')) {
    require_once $plugin_dir . 'includes/two-step-verification/two-step-loader.php';
}

// Load two-step verification admin settings
if (file_exists($plugin_dir . 'includes/two-step-verification/admin-two-step-settings.php')) {
    require_once $plugin_dir . 'includes/two-step-verification/admin-two-step-settings.php';
}

// Load features last
if (file_exists($plugin_dir . 'includes/duplicate-page-integration.php')) {
    require_once $plugin_dir . 'includes/duplicate-page-integration.php';
}

class Sky_SEO_Boost {
    private static $instance = null;
    private $license_valid = false;

    public static function get_instance() {
        if (self::$instance === null) {
            self::$instance = new self();
        }
        return self::$instance;
    }

    private function __construct() {
        // Check if license is valid
        if (function_exists('sky_seo_is_licensed')) {
            $this->license_valid = sky_seo_is_licensed();
        }
        
        // Always allow admin menu for license access
        add_action('admin_menu', [$this, 'add_admin_menu'], 5);
        
        // Only initialize full functionality if license is valid
        if ($this->license_valid) {
            // Core hooks - CRITICAL: Register post types EARLY (priority 1)
            add_action('init', [$this, 'register_post_types'], 1);
            add_action('wp_loaded', [$this, 'register_elementor_support']);
            // REMOVED: add_action('wp', [$this, 'set_permalink_structure']);

            // Admin functions
            add_action('admin_enqueue_scripts', 'sky_seo_enqueue_admin_scripts');
            add_action('admin_init', 'sky_seo_register_settings');
            add_action('add_meta_boxes', 'sky_seo_add_internal_linking_metabox');
            add_action('wp_dashboard_setup', 'sky_seo_add_dashboard_widget');

            // SEO functions - Register SEO support AFTER post types (priority 15)
            add_action('wp_head', 'sky_seo_add_schema_markup');
            add_action('wp_head', 'sky_seo_add_social_meta_tags');
            // WPML Phase 4: Additional schema types with multilingual support
            add_action('wp_head', 'sky_seo_add_faq_schema');
            add_action('wp_head', 'sky_seo_add_breadcrumb_schema');
            add_action('wp_head', 'sky_seo_add_organization_schema');
            add_action('init', 'sky_seo_register_seo_plugin_support', 15);
            add_action('template_redirect', 'sky_seo_track_page_clicks');

            // Analytics functions
            add_action('admin_init', 'sky_seo_analytics_init');

            // Tracking code manager
            add_action('wp_head', 'sky_seo_inject_tracking_codes', 1);
        } else {
            // Show admin notice on all admin pages
            add_action('admin_notices', [$this, 'show_unlicensed_notice']);
        }
    }
    
    public function show_unlicensed_notice() {
        ?>
        <div class="notice notice-error">
            <p>
                <strong><?php _e('Sky SEO Boost:', 'sky360'); ?></strong>
                <?php _e('This plugin requires a valid license to function. All features are currently disabled.', 'sky360'); ?>
                <a href="<?php echo admin_url('admin.php?page=sky-seo-settings&tab=license'); ?>" class="button button-primary" style="margin-left: 10px;">
                    <?php _e('Activate License', 'sky360'); ?>
                </a>
            </p>
        </div>
        <?php
    }

    public function register_post_types() {
        try {
            // Areas We Cover Post Type
            $area_labels = [
                'name' => _x('Areas We Cover', 'Post Type General Name', 'sky360'),
                'singular_name' => _x('Area', 'Post Type Singular Name', 'sky360'),
                'menu_name' => __('Areas We Cover', 'sky360'),
                'name_admin_bar' => __('Area', 'sky360'),
                'add_new' => __('Add New', 'sky360'),
                'add_new_item' => __('Add New Area', 'sky360'),
                'edit_item' => __('Edit Area', 'sky360'),
                'view_item' => __('View Area', 'sky360'),
                'all_items' => __('Areas', 'sky360'),
                'search_items' => __('Search Areas', 'sky360'),
            ];

            register_post_type('sky_areas', [
                'labels' => $area_labels,
                'public' => true,
                'publicly_queryable' => true,
                'show_ui' => true,
                'show_in_menu' => false,
                'query_var' => true,
                'rewrite' => ['slug' => 'areas', 'with_front' => false], // CHANGED
                'capability_type' => 'page',
                'has_archive' => true,
                'hierarchical' => true,
                'menu_position' => null,
                'supports' => ['title', 'editor', 'author', 'thumbnail', 'excerpt', 'page-attributes', 'custom-fields'],
                'show_in_rest' => true,
                'rest_base' => 'areas', // ADDED
                'show_in_sitemap' => true, // ADDED
            ]);

            // Trending Searches Post Type
            $trending_labels = [
                'name' => _x('Trending Searches', 'Post Type General Name', 'sky360'),
                'singular_name' => _x('Trending Search', 'Post Type Singular Name', 'sky360'),
                'menu_name' => __('Trending Searches', 'sky360'),
                'name_admin_bar' => __('Trending Search', 'sky360'),
                'add_new' => __('Add New', 'sky360'),
                'add_new_item' => __('Add New Trending Search', 'sky360'),
                'edit_item' => __('Edit Trending Search', 'sky360'),
                'view_item' => __('View Trending Search', 'sky360'),
                'all_items' => __('Trending Searches', 'sky360'),
                'search_items' => __('Search Trending', 'sky360'),
            ];

            register_post_type('sky_trending', [
                'labels' => $trending_labels,
                'public' => true,
                'publicly_queryable' => true,
                'show_ui' => true,
                'show_in_menu' => false,
                'query_var' => true,
                'rewrite' => ['slug' => 'insights', 'with_front' => false], // CHANGED
                'capability_type' => 'page',
                'has_archive' => true,
                'hierarchical' => true,
                'menu_position' => null,
                'supports' => ['title', 'editor', 'author', 'thumbnail', 'excerpt', 'page-attributes', 'custom-fields'],
                'show_in_rest' => true,
                'rest_base' => 'insights', // ADDED
                'show_in_sitemap' => true, // ADDED
            ]);

            // Sectors Post Type
            $sectors_labels = [
                'name' => _x('Sectors', 'Post Type General Name', 'sky360'),
                'singular_name' => _x('Sector', 'Post Type Singular Name', 'sky360'),
                'menu_name' => __('Sectors', 'sky360'),
                'name_admin_bar' => __('Sector', 'sky360'),
                'add_new' => __('Add New', 'sky360'),
                'add_new_item' => __('Add New Sector', 'sky360'),
                'edit_item' => __('Edit Sector', 'sky360'),
                'view_item' => __('View Sector', 'sky360'),
                'all_items' => __('Sectors', 'sky360'),
                'search_items' => __('Search Sectors', 'sky360'),
            ];

            register_post_type('sky_sectors', [
                'labels' => $sectors_labels,
                'public' => true,
                'publicly_queryable' => true,
                'show_ui' => true,
                'show_in_menu' => false,
                'query_var' => true,
                'rewrite' => ['slug' => 'sectors', 'with_front' => false], // CHANGED
                'capability_type' => 'page',
                'has_archive' => true,
                'hierarchical' => true,
                'menu_position' => null,
                'supports' => ['title', 'editor', 'author', 'thumbnail', 'excerpt', 'page-attributes', 'custom-fields'],
                'show_in_rest' => true,
                'rest_base' => 'sectors', // ADDED
                'show_in_sitemap' => true, // ADDED
            ]);

            // FORCE FLUSH REWRITE RULES ON VERSION CHANGE
            $current_version = get_option('sky_seo_rewrite_version', '0');
            if ($current_version !== SKY_SEO_BOOST_VERSION) {
                flush_rewrite_rules();
                update_option('sky_seo_rewrite_version', SKY_SEO_BOOST_VERSION);
            }

        } catch (Exception $e) {
            error_log('Sky SEO Boost: Error registering post types: ' . $e->getMessage());
        }

        // WPML Integration: Register translatable post type slugs
        $this->register_wpml_post_type_slugs();
    }

    /**
     * Register WPML translatable post type slugs
     * Allows URLs like /areas/, /tendencias/, /zones/ based on language
     *
     * @since 4.2.0
     */
    private function register_wpml_post_type_slugs() {
        // Only run if WPML is active
        if (!defined('ICL_SITEPRESS_VERSION')) {
            return;
        }

        // Register slug translations for WPML
        add_filter('wpml_custom_post_type_slug_translation', function($translations, $post_type) {
            // Define translatable slugs with default translations
            $slug_translations = [
                'sky_areas' => apply_filters('sky_seo_areas_translated_slugs', [
                    'en' => 'areas',
                    'es' => 'areas',
                    'fr' => 'zones',
                    'de' => 'gebiete',
                    'it' => 'aree',
                    'pt' => 'areas',
                    'nl' => 'gebieden',
                    'pl' => 'obszary',
                    'ru' => 'oblasti',
                    'ar' => 'مناطق',
                ]),
                'sky_trending' => apply_filters('sky_seo_trending_translated_slugs', [
                    'en' => 'insights',
                    'es' => 'tendencias',
                    'fr' => 'tendances',
                    'de' => 'trends',
                    'it' => 'tendenze',
                    'pt' => 'tendencias',
                    'nl' => 'trends',
                    'pl' => 'trendy',
                    'ru' => 'tendencii',
                    'ar' => 'اتجاهات',
                ]),
                'sky_sectors' => apply_filters('sky_seo_sectors_translated_slugs', [
                    'en' => 'sectors',
                    'es' => 'sectores',
                    'fr' => 'secteurs',
                    'de' => 'sektoren',
                    'it' => 'settori',
                    'pt' => 'setores',
                    'nl' => 'sectoren',
                    'pl' => 'sektory',
                    'ru' => 'sektora',
                    'ar' => 'قطاعات',
                ])
            ];

            // Return translations if this post type has them
            if (isset($slug_translations[$post_type])) {
                return $slug_translations[$post_type];
            }

            return $translations;
        }, 10, 2);

        // Register with WPML's post type slug translation system
        add_action('init', function() {
            if (function_exists('wpml_register_post_type_slug_translation')) {
                wpml_register_post_type_slug_translation('sky_areas');
                wpml_register_post_type_slug_translation('sky_trending');
                wpml_register_post_type_slug_translation('sky_sectors');
            }
        }, 20);
    }

    public function register_elementor_support() {
        try {
            if (did_action('elementor/loaded')) {
                add_action('elementor/widgets/register', function ($widgets_manager) {
                    if (class_exists('Sky_SEO_Area_Links_Widget')) {
                        $widgets_manager->register_widget_type(new Sky_SEO_Area_Links_Widget());
                    }
                });
            }
        } catch (Exception $e) {
            error_log('Sky SEO Boost: Error registering Elementor support: ' . $e->getMessage());
        }
    }

    // REMOVED set_permalink_structure() function entirely as per Fix 2

    public function add_admin_menu() {
        // Get custom icon - using base64 encoded SVG for crisp display
        $icon_url = $this->get_menu_icon();

        // Main Sky360 Menu - position 2 is right under Dashboard
        add_menu_page(
            __('Sky360', 'sky360'),
            __('Sky360', 'sky360'),
            'edit_pages',
            'sky-seo-boost',
            $this->license_valid ? 'sky_seo_analytics_tab' : [$this, 'show_license_required_page'],
            $icon_url,
            2  // Position 2 = right under Dashboard
        );

        // Remove duplicate first submenu
        global $submenu;
        if (isset($submenu['sky-seo-boost'][0])) {
            unset($submenu['sky-seo-boost'][0]);
        }

        // Only show other menu items if licensed
        if ($this->license_valid) {
            // All Content Submenu
            add_submenu_page(
                'sky-seo-boost',
                __('All Content', 'sky360'),
                __('All Content', 'sky360'),
                'edit_pages',
                'sky-seo-all-content',
                'sky_seo_all_content_page'
            );

            // Areas submenu
            add_submenu_page(
                'sky-seo-boost',
                __('Areas We Cover', 'sky360'),
                __('Areas', 'sky360'),
                'edit_pages',
                'edit.php?post_type=sky_areas'
            );

            // Trending Searches submenu
            add_submenu_page(
                'sky-seo-boost',
                __('Trending Searches', 'sky360'),
                __('Trending Searches', 'sky360'),
                'edit_pages',
                'edit.php?post_type=sky_trending'
            );

            // Sectors submenu
            add_submenu_page(
                'sky-seo-boost',
                __('Sectors', 'sky360'),
                __('Sectors', 'sky360'),
                'edit_pages',
                'edit.php?post_type=sky_sectors'
            );

            // UTM Tracking submenu
            if (function_exists('sky_seo_render_utm_interface')) {
                add_submenu_page(
                    'sky-seo-boost',
                    __('UTM Tracking', 'sky360'),
                    __('UTM Tracking', 'sky360'),
                    'manage_options',
                    'sky-seo-utm',
                    'sky_seo_render_utm_interface'
                );
            }

            // Google Ads submenu
            if (function_exists('sky_seo_render_google_ads_page')) {
                add_submenu_page(
                    'sky-seo-boost',
                    __('Google Ads', 'sky360'),
                    __('Google Ads', 'sky360'),
                    'manage_options',
                    'sky-seo-google-ads',
                    'sky_seo_render_google_ads_page'
                );
            }
        }

        // Settings submenu - always show for license access
        add_submenu_page(
            'sky-seo-boost',
            __('Settings', 'sky360'),
            __('Settings', 'sky360'),
            'manage_options',
            'sky-seo-settings',
            'sky_seo_settings_page'
        );
    }

    /**
     * Get the menu icon as base64 encoded data URI
     */
    private function get_menu_icon() {
        $icon_path = SKY360_PLUGIN_DIR . 'assets/img/skyweb_logo_black.png';

        if (file_exists($icon_path)) {
            $icon_data = file_get_contents($icon_path);
            return 'data:image/png;base64,' . base64_encode($icon_data);
        }

        // Fallback to dashicon
        return 'dashicons-chart-line';
    }

    public function show_license_required_page() {
        ?>
        <div class="wrap">
            <h1><?php esc_html_e('Sky360', 'sky360'); ?></h1>
            <div class="notice notice-error">
                <p>
                    <strong><?php esc_html_e('License Required', 'sky360'); ?></strong><br>
                    <?php esc_html_e('This plugin requires a valid license to function. Please activate your license to unlock all features.', 'sky360'); ?>
                </p>
                <p>
                    <a href="<?php echo esc_url(admin_url('admin.php?page=sky-seo-settings&tab=license')); ?>" class="button button-primary">
                        <?php esc_html_e('Activate License', 'sky360'); ?>
                    </a>
                </p>
            </div>
        </div>
        <?php
    }

    public function show_activation_notice() {
        ?>
        <div class="notice notice-info is-dismissible">
            <p><?php esc_html_e('Thank you for installing Sky360! Please configure your settings to get started.', 'sky360'); ?></p>
        </div>
        <?php
    }
}

// Initialize the plugin
try {
    Sky_SEO_Boost::get_instance();
} catch (Exception $e) {
    error_log('Sky SEO Boost: Error initializing plugin: ' . $e->getMessage());
}

// Activation hook - SIMPLIFIED VERSION
register_activation_hook(__FILE__, 'sky_seo_boost_activate');

function sky_seo_boost_activate() {
    try {
        global $wpdb;
        
        // Create Sky SEO Boost click tracking table
        $table_name = $wpdb->prefix . 'sky_seo_clicks';
        $charset_collate = $wpdb->get_charset_collate();

        $sql = "CREATE TABLE IF NOT EXISTS $table_name (
            id BIGINT(20) UNSIGNED NOT NULL AUTO_INCREMENT,
            post_id BIGINT(20) UNSIGNED NOT NULL,
            clicks BIGINT(20) UNSIGNED NOT NULL DEFAULT 0,
            google_clicks BIGINT(20) UNSIGNED NOT NULL DEFAULT 0,
            social_clicks BIGINT(20) UNSIGNED NOT NULL DEFAULT 0,
            direct_clicks BIGINT(20) UNSIGNED NOT NULL DEFAULT 0,
            is_bot TINYINT(1) DEFAULT 0,
            user_agent VARCHAR(255) DEFAULT '',
            referrer_url VARCHAR(255) DEFAULT '',
            click_time DATETIME NOT NULL,
            country_code VARCHAR(2) DEFAULT '',
            country_name VARCHAR(100) DEFAULT '',
            city_name VARCHAR(100) DEFAULT '',
            post_language VARCHAR(7) DEFAULT '',
            PRIMARY KEY (id),
            INDEX post_id (post_id),
            INDEX click_time (click_time),
            INDEX post_time (post_id, click_time),
            INDEX country_code (country_code),
            INDEX city_name (city_name),
            INDEX is_bot (is_bot),
            INDEX post_language (post_language)
        ) $charset_collate;";

        require_once(ABSPATH . 'wp-admin/includes/upgrade.php');
        dbDelta($sql);

        // PERFORMANCE FIX: Add compound index if it doesn't exist
        $index_exists = $wpdb->get_results("SHOW INDEX FROM $table_name WHERE Key_name = 'post_time'");
        if (empty($index_exists)) {
            $wpdb->query("ALTER TABLE $table_name ADD INDEX post_time (post_id, click_time)");
        }

        // WPML INTEGRATION: Add language column to existing tables (upgrade)
        $column_exists = $wpdb->get_results("SHOW COLUMNS FROM $table_name LIKE 'post_language'");
        if (empty($column_exists)) {
            $wpdb->query("ALTER TABLE $table_name ADD COLUMN post_language VARCHAR(7) DEFAULT '' AFTER city_name");
            $wpdb->query("ALTER TABLE $table_name ADD INDEX post_language (post_language)");
            error_log('Sky SEO Boost: Added post_language column for WPML support');
        }

        // Set activation transient for UTM module
        set_transient('sky_seo_utm_just_activated', true, 60);
        
        // Update UTM database version
        update_option('sky_seo_utm_db_version', '1.0.1');

        // Only register post types if licensed
        if (class_exists('Sky_SEO_License_Manager')) {
            $license_manager = Sky_SEO_License_Manager::get_instance();
            if ($license_manager->is_license_valid()) {
                Sky_SEO_Boost::get_instance()->register_post_types();
                flush_rewrite_rules();
            }
        }
        
        // Clear any existing license check schedules
        wp_clear_scheduled_hook('sky_seo_daily_license_check');
        wp_clear_scheduled_hook('sky_seo_hourly_server_check');
        
    } catch (Exception $e) {
        error_log('Sky SEO Boost: Error during activation: ' . $e->getMessage());
    }
}

// Deactivation hook
register_deactivation_hook(__FILE__, function() {
    flush_rewrite_rules();
    
    // Clear license check schedules
    wp_clear_scheduled_hook('sky_seo_daily_license_check');
    wp_clear_scheduled_hook('sky_seo_hourly_server_check');
    
    // Clear UTM schedules
    wp_clear_scheduled_hook('sky_seo_utm_check_tables');
    wp_clear_scheduled_hook('sky_seo_utm_cleanup_old_data');
    
    // Clear transients
    delete_transient('sky_seo_license_valid');
    delete_transient('sky_seo_license_status');
    delete_transient('sky_seo_license_server_check');
    delete_transient('sky_seo_utm_just_activated');
});

// Show activation notice if needed
add_action('admin_notices', function() {
    $deactivation_notice = get_option('sky_seo_deactivation_notice');
    if ($deactivation_notice) {
        ?>
        <div class="notice notice-error is-dismissible">
            <p>
                <strong><?php _e('Sky SEO Boost:', 'sky360'); ?></strong>
                <?php
                if ($deactivation_notice === 'server_unavailable') {
                    _e('The plugin was deactivated because the license server is unavailable. Please contact support.', 'sky360');
                } else {
                    _e('The plugin was deactivated due to a license issue.', 'sky360');
                }
                ?>
            </p>
        </div>
        <?php
        delete_option('sky_seo_deactivation_notice');
    }
});