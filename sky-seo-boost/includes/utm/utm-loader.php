<?php
/**
 * Sky SEO Boost - UTM Tracking Module
 * MODIFIED VERSION - Added bulk delete functionality
 * 
 * @package SkySEOBoost
 * @subpackage UTM
 * @since 2.2.0
 * @modified Added checkbox column for bulk selection and deletion
 */

// Prevent direct access
if (!defined('ABSPATH')) {
    exit;
}

// Define UTM module constants only if not already defined
if (!defined('SKY_SEO_UTM_DB_VERSION')) {
    define('SKY_SEO_UTM_DB_VERSION', '1.0.1');
}

// Check if plugin directory constant is defined
if (!defined('SKY_SEO_BOOST_PLUGIN_DIR')) {
    return; // Exit if main plugin hasn't loaded yet
}

// Load dependencies immediately
$utm_dir = SKY_SEO_BOOST_PLUGIN_DIR . 'includes/utm/';

// Load utilities first
if (!class_exists('SkyInsightsUtils') && file_exists($utm_dir . 'class-sky-insights-utils.php')) {
    require_once $utm_dir . 'class-sky-insights-utils.php';
}

// Load the main UTM tracker class
if (!class_exists('SkyInsightsUTMTracker') && file_exists($utm_dir . 'class-sky-insights-utm-tracker.php')) {
    require_once $utm_dir . 'class-sky-insights-utm-tracker.php';
}

// Load UTM tracking enhancements
if (!class_exists('SkyInsightsUTMEnhancements') && file_exists($utm_dir . 'class-sky-insights-utm-enhancements.php')) {
    require_once $utm_dir . 'class-sky-insights-utm-enhancements.php';
}

// Load WooCommerce order display integration if WooCommerce is active
add_action('plugins_loaded', function() use ($utm_dir) {
    if (class_exists('WooCommerce') && !class_exists('SkyInsightsWCOrderUTMDisplay') && file_exists($utm_dir . 'class-sky-insights-wc-order-utm-display.php')) {
        require_once $utm_dir . 'class-sky-insights-wc-order-utm-display.php';
    }
});

/**
 * Create UTM tables directly - ROBUST VERSION
 */
function sky_seo_create_utm_tables_directly() {
    global $wpdb;
    
    $charset_collate = $wpdb->get_charset_collate();
    
    // Links table
    $links_table = $wpdb->prefix . 'sky_insights_utm_links';
    $sql_links = "CREATE TABLE IF NOT EXISTS `$links_table` (
        `id` bigint(20) NOT NULL AUTO_INCREMENT,
        `short_code` varchar(10) NOT NULL,
        `destination_url` text NOT NULL,
        `utm_source` varchar(100) NOT NULL,
        `utm_medium` varchar(100) DEFAULT NULL,
        `utm_campaign` varchar(100) DEFAULT NULL,
        `utm_term` varchar(100) DEFAULT NULL,
        `utm_content` varchar(100) DEFAULT NULL,
        `created_by` bigint(20) NOT NULL,
        `created_at` datetime DEFAULT CURRENT_TIMESTAMP,
        `clicks` int(11) DEFAULT 0,
        `conversions` int(11) DEFAULT 0,
        `revenue` decimal(10,2) DEFAULT 0.00,
        `is_active` tinyint(1) DEFAULT 1,
        PRIMARY KEY (`id`),
        UNIQUE KEY `short_code` (`short_code`),
        KEY `utm_source` (`utm_source`),
        KEY `created_at` (`created_at`)
    ) $charset_collate;";
    
    // Clicks table
    $clicks_table = $wpdb->prefix . 'sky_insights_utm_clicks';
    $sql_clicks = "CREATE TABLE IF NOT EXISTS `$clicks_table` (
        `id` bigint(20) NOT NULL AUTO_INCREMENT,
        `link_id` bigint(20) NOT NULL,
        `clicked_at` datetime DEFAULT CURRENT_TIMESTAMP,
        `click_time` datetime DEFAULT CURRENT_TIMESTAMP,
        `ip_address` varchar(45) DEFAULT NULL,
        `user_agent` text DEFAULT NULL,
        `referrer` text DEFAULT NULL,
        `device_type` varchar(20) DEFAULT NULL,
        `browser` varchar(50) DEFAULT NULL,
        `session_id` varchar(64) DEFAULT NULL,
        `converted` tinyint(1) DEFAULT 0,
        `order_id` bigint(20) DEFAULT NULL,
        `country_code` varchar(2) DEFAULT NULL,
        PRIMARY KEY (`id`),
        KEY `link_id` (`link_id`),
        KEY `clicked_at` (`clicked_at`),
        KEY `converted` (`converted`),
        KEY `session_id` (`session_id`)
    ) $charset_collate;";
    
    require_once(ABSPATH . 'wp-admin/includes/upgrade.php');
    
    // Execute queries using dbDelta
    $result1 = dbDelta($sql_links);
    $result2 = dbDelta($sql_clicks);
    
    // Also try direct query as fallback
    $wpdb->query($sql_links);
    $wpdb->query($sql_clicks);
    
    // Verify tables were created
    $links_exists = $wpdb->get_var("SHOW TABLES LIKE '$links_table'") == $links_table;
    $clicks_exists = $wpdb->get_var("SHOW TABLES LIKE '$clicks_table'") == $clicks_table;
    
    if ($links_exists && $clicks_exists) {
        update_option('sky_seo_utm_tables_created', true);
        update_option('sky_seo_utm_db_version', SKY_SEO_UTM_DB_VERSION);
        return true;
    }
    
    return false;
}

/**
 * Plugin activation hook
 */
function sky_seo_utm_activate() {
    // Set activation flag
    set_transient('sky_seo_utm_just_activated', true, 60);
    
    // Create tables immediately
    $tables_created = sky_seo_create_utm_tables_directly();
    
    if (!$tables_created) {
        // Try alternative method
        if (class_exists('SkyInsightsUTMTracker')) {
            $tracker = SkyInsightsUTMTracker::get_instance();
            if (method_exists($tracker, 'create_tables')) {
                $tracker->create_tables();
            }
        }
    }
    
    // Flush rewrite rules
    flush_rewrite_rules();
}

// Register activation hook
if (defined('SKY_SEO_BOOST_FILE')) {
    register_activation_hook(SKY_SEO_BOOST_FILE, 'sky_seo_utm_activate');
}

/**
 * Initialize UTM tracking - IMPROVED VERSION
 */
function sky_seo_init_utm_tracking() {
    // IMPORTANT: Create tables first before any other initialization
    sky_seo_ensure_utm_tables();
    
    // Initialize the tracker if licensed
    if (function_exists('sky_seo_is_licensed') && sky_seo_is_licensed() && class_exists('SkyInsightsUTMTracker')) {
        $tracker = SkyInsightsUTMTracker::get_instance();
        
        // Ensure tables exist before initializing tracker
        if (method_exists($tracker, 'create_tables')) {
            $tracker->create_tables();
        }
        
        // Ensure hooks are initialized
        if (method_exists($tracker, 'init_hooks')) {
            $tracker->init_hooks();
        }
    }
    
    // Initialize tracking enhancements
    if (function_exists('sky_seo_is_licensed') && sky_seo_is_licensed() && class_exists('SkyInsightsUTMEnhancements')) {
        SkyInsightsUTMEnhancements::get_instance();
    }
    
    // Check database version and update if needed
    sky_seo_check_utm_db_version();
    
    // Schedule maintenance tasks
    sky_seo_utm_schedule_tasks();
    
    // Track UTM parameters from URL
    sky_seo_track_utm_params();
}
add_action('init', 'sky_seo_init_utm_tracking', 20);

/**
 * Early table creation check
 */
add_action('plugins_loaded', function() {
    // Only run in admin and not during AJAX
    if (is_admin() && (!defined('DOING_AJAX') || !DOING_AJAX)) {
        global $wpdb;
        $utm_links_table = $wpdb->prefix . 'sky_insights_utm_links';
        $links_exists = $wpdb->get_var("SHOW TABLES LIKE '$utm_links_table'") == $utm_links_table;
        
        if (!$links_exists) {
            sky_seo_create_utm_tables_directly();
        }
    }
}, 5);

/**
 * Check and update database version
 */
function sky_seo_check_utm_db_version() {
    $current_version = get_option('sky_seo_utm_db_version', '0');
    
    if (version_compare($current_version, SKY_SEO_UTM_DB_VERSION, '<')) {
        // Run updates
        sky_seo_ensure_utm_tables();
        sky_seo_update_utm_clicks_columns();
        
        // Update version
        update_option('sky_seo_utm_db_version', SKY_SEO_UTM_DB_VERSION);
    }
}

/**
 * Ensure UTM tables exist with proper structure - IMPROVED VERSION
 */
function sky_seo_ensure_utm_tables() {
    global $wpdb;
    
    $utm_links_table = $wpdb->prefix . 'sky_insights_utm_links';
    $utm_clicks_table = $wpdb->prefix . 'sky_insights_utm_clicks';
    
    // Check if tables exist
    $links_exists = $wpdb->get_var("SHOW TABLES LIKE '$utm_links_table'") == $utm_links_table;
    $clicks_exists = $wpdb->get_var("SHOW TABLES LIKE '$utm_clicks_table'") == $utm_clicks_table;
    
    // Force table creation if either is missing
    if (!$links_exists || !$clicks_exists) {
        
        
        // Create tables directly
        $tables_created = sky_seo_create_utm_tables_directly();
        
        if ($tables_created) {
            
            
            // Show success notice only in admin
            if (is_admin() && !wp_doing_ajax()) {
                add_action('admin_notices', function() {
                    echo '<div class="notice notice-success is-dismissible">';
                    echo '<p><strong>Sky SEO Boost:</strong> UTM database tables have been created successfully.</p>';
                    echo '</div>';
                });
            }
        } else {
            
            
            if (is_admin() && !wp_doing_ajax()) {
                add_action('admin_notices', function() {
                    echo '<div class="notice notice-error">';
                    echo '<p><strong>Sky SEO Boost Error:</strong> Failed to create UTM database tables. Please check database permissions.</p>';
                    echo '</div>';
                });
            }
        }
    }
    
    // Always check for column updates
    sky_seo_update_utm_clicks_columns();
    
    // Update last check time
    update_option('sky_seo_utm_tables_checked', current_time('mysql'));
}

/**
 * Update UTM clicks table columns to ensure compatibility
 */
function sky_seo_update_utm_clicks_columns() {
    global $wpdb;
    
    $utm_clicks_table = $wpdb->prefix . 'sky_insights_utm_clicks';
    
    // Only proceed if table exists
    if ($wpdb->get_var("SHOW TABLES LIKE '$utm_clicks_table'") != $utm_clicks_table) {
        return;
    }
    
    // List of columns that should exist with their definitions
    $required_columns = array(
        'click_time' => "datetime DEFAULT CURRENT_TIMESTAMP",
        'device_type' => "varchar(20) DEFAULT NULL",
        'browser' => "varchar(50) DEFAULT NULL",
        'session_id' => "varchar(64) DEFAULT NULL",
        'converted' => "tinyint(1) DEFAULT 0",
        'order_id' => "bigint(20) DEFAULT NULL",
        'country_code' => "varchar(2) DEFAULT NULL"
    );
    
    // Check existing columns
    $existing_columns = array();
    $columns = $wpdb->get_results("DESCRIBE $utm_clicks_table");
    if ($columns) {
        foreach ($columns as $column) {
            $existing_columns[] = $column->Field;
        }
    }
    
    // Add missing columns
    foreach ($required_columns as $column_name => $column_definition) {
        if (!in_array($column_name, $existing_columns)) {
            $position = '';
            
            // Determine position for specific columns
            if ($column_name === 'click_time') {
                $position = 'AFTER clicked_at';
            }
            
            $query = "ALTER TABLE $utm_clicks_table ADD COLUMN $column_name $column_definition $position";
            $result = $wpdb->query($query);
            
            if ($result === false) {
                
            }
        }
    }
    
    // Add missing indexes
    sky_seo_utm_add_indexes();
}

/**
 * Add database indexes for better performance
 */
function sky_seo_utm_add_indexes() {
    global $wpdb;
    
    $utm_clicks_table = $wpdb->prefix . 'sky_insights_utm_clicks';
    
    // Only proceed if table exists
    if ($wpdb->get_var("SHOW TABLES LIKE '$utm_clicks_table'") != $utm_clicks_table) {
        return;
    }
    
    $required_indexes = array(
        'session_id' => "ADD INDEX idx_session_id (session_id)",
        'converted' => "ADD INDEX idx_converted (converted)"
    );
    
    foreach ($required_indexes as $index_name => $index_query) {
        $index_exists = $wpdb->get_var($wpdb->prepare(
            "SELECT COUNT(1) IndexExists FROM INFORMATION_SCHEMA.STATISTICS 
             WHERE table_schema = %s AND table_name = %s AND index_name = %s",
            DB_NAME,
            $utm_clicks_table,
            'idx_' . $index_name
        ));
        
        if (!$index_exists) {
            $wpdb->query("ALTER TABLE $utm_clicks_table $index_query");
        }
    }
}

/**
 * Track UTM parameters from URL and set cookies
 * RESTRICTED: Only tracks sky_utm shortlinks created in dashboard
 * External UTM parameters are ignored
 */
function sky_seo_track_utm_params() {
    // DISABLED: External UTM tracking removed
    // This function now does nothing - only sky_utm shortlinks are tracked
    // External utm_source, utm_medium, etc. parameters are completely ignored
    return;
}

/**
 * Schedule UTM maintenance tasks
 */
function sky_seo_utm_schedule_tasks() {
    // Schedule table checks
    if (!wp_next_scheduled('sky_seo_utm_check_tables')) {
        wp_schedule_event(time(), 'daily', 'sky_seo_utm_check_tables');
    }
    
    // Schedule cleanup of old data
    if (!wp_next_scheduled('sky_seo_utm_cleanup_old_data')) {
        wp_schedule_event(time(), 'weekly', 'sky_seo_utm_cleanup_old_data');
    }
}

// Hook scheduled tasks
add_action('sky_seo_utm_check_tables', 'sky_seo_ensure_utm_tables');
add_action('sky_seo_utm_cleanup_old_data', 'sky_seo_utm_cleanup_old_data');

/**
 * Cleanup old UTM data
 */
function sky_seo_utm_cleanup_old_data() {
    global $wpdb;
    
    // Keep data for 90 days by default
    $days_to_keep = apply_filters('sky_seo_utm_data_retention_days', 90);
    $cutoff_date = date('Y-m-d H:i:s', strtotime("-{$days_to_keep} days"));
    
    // Clean up old clicks
    $utm_clicks_table = $wpdb->prefix . 'sky_insights_utm_clicks';
    if ($wpdb->get_var("SHOW TABLES LIKE '$utm_clicks_table'") == $utm_clicks_table) {
        $wpdb->query($wpdb->prepare(
            "DELETE FROM $utm_clicks_table WHERE clicked_at < %s",
            $cutoff_date
        ));
    }
}

/**
 * Render UTM interface with table check
 */
function sky_seo_render_utm_interface_with_check() {
    global $wpdb;
    
    // Check if tables exist
    $utm_links_table = $wpdb->prefix . 'sky_insights_utm_links';
    $utm_clicks_table = $wpdb->prefix . 'sky_insights_utm_clicks';
    
    $links_exists = $wpdb->get_var("SHOW TABLES LIKE '$utm_links_table'") == $utm_links_table;
    $clicks_exists = $wpdb->get_var("SHOW TABLES LIKE '$utm_clicks_table'") == $utm_clicks_table;
    
    if (!$links_exists || !$clicks_exists) {
        // Try to create tables automatically
        sky_seo_create_utm_tables_directly();
        
        // Check again
        $links_exists = $wpdb->get_var("SHOW TABLES LIKE '$utm_links_table'") == $utm_links_table;
        $clicks_exists = $wpdb->get_var("SHOW TABLES LIKE '$utm_clicks_table'") == $utm_clicks_table;
        
        if (!$links_exists || !$clicks_exists) {
            // Show error if tables still don't exist
            ?>
            <div class="wrap">
                <h1><?php echo esc_html(get_admin_page_title()); ?></h1>
                <div class="notice notice-error">
                    <p><strong>Database tables could not be created automatically.</strong></p>
                    <p>Please check your database permissions or contact your hosting provider.</p>
                </div>
            </div>
            <?php
            return;
        }
    }
    
    // Tables exist, show normal interface
    sky_seo_render_utm_interface();
}

/**
 * Dashboard page function
 */
function sky_seo_utm_dashboard_page() {
    sky_seo_render_utm_interface_with_check();
}

/**
 * Render UTM interface
 */
function sky_seo_render_utm_interface() {
    // Check if constants are defined
    if (!defined('SKY_SEO_BOOST_PLUGIN_URL')) {
        echo '<div class="notice notice-error"><p>Error: Sky SEO Boost plugin constants not defined.</p></div>';
        return;
    }

    // Start Sky360 page wrapper
    sky360_admin_page_start();
    sky360_render_admin_header(
        __('UTM Tracking', 'sky360'),
        __('Create and track UTM campaign links', 'sky360')
    );
    sky360_content_wrapper_start();
    ?>
        <!-- Check if WooCommerce is active -->
        <?php if (!class_exists('WooCommerce')) : ?>
            <div class="notice notice-warning" style="margin: 0 0 20px 0;">
                <p><?php _e('WooCommerce is required for conversion tracking features.', 'sky360'); ?></p>
            </div>
        <?php endif; ?>

        <div id="sky-utm-dashboard" class="sky-seo-utm-dashboard">
            
            <!-- UTM Builder Section -->
            <div class="sky-utm-builder">
                <h3>
                    <svg width="20" height="20" viewBox="0 0 20 20" fill="none">
                        <path d="M10 2L3 7V17C3 17.5523 3.44772 18 4 18H16C16.5523 18 17 17.5523 17 17V7L10 2Z" stroke="currentColor" stroke-width="2"/>
                        <path d="M10 18V12" stroke="currentColor" stroke-width="2"/>
                    </svg>
                    <?php _e('Create UTM Link', 'sky360'); ?>
                </h3>
                
                <form id="sky-utm-builder-form" method="post">
                    <div class="sky-utm-form-grid">
                        <div class="sky-utm-form-group full-width">
                            <label for="utm-destination"><?php _e('Destination URL', 'sky360'); ?> <span class="required">*</span></label>
                            <input type="url" id="utm-destination" name="destination" placeholder="https://<?php echo esc_attr($_SERVER['HTTP_HOST']); ?>/your-page" required>
                        </div>
                        
                        <div class="sky-utm-form-group">
                            <label for="utm-source"><?php _e('Campaign Source', 'sky360'); ?> <span class="required">*</span></label>
                            <input type="text" id="utm-source" name="utm_source" placeholder="e.g., facebook, newsletter" required>
                        </div>
                        
                        <div class="sky-utm-form-group">
                            <label for="utm-medium"><?php _e('Campaign Medium', 'sky360'); ?></label>
                            <input type="text" id="utm-medium" name="utm_medium" placeholder="e.g., social, email, cpc">
                        </div>
                        
                        <div class="sky-utm-form-group">
                            <label for="utm-campaign"><?php _e('Campaign Name', 'sky360'); ?></label>
                            <input type="text" id="utm-campaign" name="utm_campaign" placeholder="e.g., summer_sale">
                        </div>
                        
                        <div class="sky-utm-form-group">
                            <label for="utm-term"><?php _e('Campaign Term', 'sky360'); ?></label>
                            <input type="text" id="utm-term" name="utm_term" placeholder="e.g., running shoes">
                        </div>
                    </div>
                    
<!-- Quick Create Templates -->
<div class="sky-utm-templates">
    <span><?php _e('Quick create:', 'sky360'); ?></span>
    <button type="button" class="sky-utm-quick-create" data-template="social-facebook">
        <svg width="14" height="14" viewBox="0 0 24 24" fill="currentColor">
            <path d="M24 12.073c0-6.627-5.373-12-12-12s-12 5.373-12 12c0 5.99 4.388 10.954 10.125 11.854v-8.385H7.078v-3.47h3.047V9.43c0-3.007 1.792-4.669 4.533-4.669 1.312 0 2.686.235 2.686.235v2.953H15.83c-1.491 0-1.956.925-1.956 1.874v2.25h3.328l-.532 3.47h-2.796v8.385C19.612 23.027 24 18.062 24 12.073z"/>
        </svg>
        Facebook
    </button>
    <button type="button" class="sky-utm-quick-create" data-template="social-x">
        <svg width="14" height="14" viewBox="0 0 24 24" fill="currentColor">
            <path d="M18.244 2.25h3.308l-7.227 8.26 8.502 11.24H16.17l-5.214-6.817L4.99 21.75H1.68l7.73-8.835L1.254 2.25H8.08l4.713 6.231zm-1.161 17.52h1.833L7.084 4.126H5.117z"/>
        </svg>
        X
    </button>
    <button type="button" class="sky-utm-quick-create" data-template="social-instagram">
        <svg width="14" height="14" viewBox="0 0 24 24" fill="currentColor">
            <path d="M12 2.163c3.204 0 3.584.012 4.85.07 3.252.148 4.771 1.691 4.919 4.919.058 1.265.069 1.645.069 4.849 0 3.205-.012 3.584-.069 4.849-.149 3.225-1.664 4.771-4.919 4.919-1.266.058-1.644.07-4.85.07-3.204 0-3.584-.012-4.849-.07-3.26-.149-4.771-1.699-4.919-4.92-.058-1.265-.07-1.644-.07-4.849 0-3.204.013-3.583.07-4.849.149-3.227 1.664-4.771 4.919-4.919 1.266-.057 1.645-.069 4.849-.069zm0-2.163c-3.259 0-3.667.014-4.947.072-4.358.2-6.78 2.618-6.98 6.98-.059 1.281-.073 1.689-.073 4.948 0 3.259.014 3.668.072 4.948.2 4.358 2.618 6.78 6.98 6.98 1.281.058 1.689.072 4.948.072 3.259 0 3.668-.014 4.948-.072 4.354-.2 6.782-2.618 6.979-6.98.059-1.28.073-1.689.073-4.948 0-3.259-.014-3.667-.072-4.947-.196-4.354-2.617-6.78-6.979-6.98-1.281-.059-1.69-.073-4.949-.073z"/>
            <path d="M12 5.838c-3.403 0-6.162 2.759-6.162 6.162s2.759 6.163 6.162 6.163 6.162-2.759 6.162-6.163c0-3.403-2.759-6.162-6.162-6.162zm0 10.162c-2.209 0-4-1.79-4-4 0-2.209 1.791-4 4-4s4 1.791 4 4c0 2.21-1.791 4-4 4zm6.406-11.845c-.796 0-1.441.645-1.441 1.44s.645 1.44 1.441 1.44 1.44-.645 1.44-1.44-.644-1.44-1.44-1.44z"/>
        </svg>
        Instagram
    </button>
    <button type="button" class="sky-utm-quick-create" data-template="social-whatsapp">
        <svg width="14" height="14" viewBox="0 0 24 24" fill="currentColor">
            <path d="M17.472 14.382c-.297-.149-1.758-.867-2.03-.967-.273-.099-.471-.148-.67.15-.197.297-.767.966-.94 1.164-.173.199-.347.223-.644.075-.297-.15-1.255-.463-2.39-1.475-.883-.788-1.48-1.761-1.653-2.059-.173-.297-.018-.458.13-.606.134-.133.298-.347.446-.52.149-.174.198-.298.298-.497.099-.198.05-.371-.025-.52-.075-.149-.669-1.612-.916-2.207-.242-.579-.487-.5-.669-.51-.173-.008-.371-.01-.57-.01-.198 0-.52.074-.792.372-.272.297-1.04 1.016-1.04 2.479 0 1.462 1.065 2.875 1.213 3.074.149.198 2.096 3.2 5.077 4.487.709.306 1.262.489 1.694.625.712.227 1.36.195 1.871.118.571-.085 1.758-.719 2.006-1.413.248-.694.248-1.289.173-1.413-.074-.124-.272-.198-.57-.347m-5.421 7.403h-.004a9.87 9.87 0 01-5.031-1.378l-.361-.214-3.741.982.998-3.648-.235-.374a9.86 9.86 0 01-1.51-5.26c.001-5.45 4.436-9.884 9.888-9.884 2.64 0 5.122 1.03 6.988 2.898a9.825 9.825 0 012.893 6.994c-.003 5.45-4.437 9.884-9.885 9.884m8.413-18.297A11.815 11.815 0 0012.05 0C5.495 0 .16 5.335.157 11.892c0 2.096.547 4.142 1.588 5.945L.057 24l6.305-1.654a11.882 11.882 0 005.683 1.448h.005c6.554 0 11.89-5.335 11.893-11.893a11.821 11.821 0 00-3.48-8.413Z"/>
        </svg>
        WhatsApp
    </button>
    <button type="button" class="sky-utm-quick-create" data-template="sms">
        <svg width="14" height="14" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2">
            <path d="M21 15a2 2 0 01-2 2H7l-4 4V5a2 2 0 012-2h14a2 2 0 012 2z"/>
            <line x1="9" y1="10" x2="15" y2="10"/>
        </svg>
        SMS
    </button>
    <button type="button" class="sky-utm-quick-create" data-template="email-newsletter">
        <svg width="14" height="14" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2">
            <rect x="2" y="4" width="20" height="16" rx="2"/>
            <path d="M22 7L12 13L2 7"/>
        </svg>
        Email Newsletter
    </button>
    <button type="button" class="sky-utm-quick-create" data-template="google-cpc">
        <svg width="14" height="14" viewBox="0 0 24 24" fill="currentColor">
            <path d="M22.56 12.25c0-.78-.07-1.53-.2-2.25H12v4.26h5.92c-.26 1.37-1.04 2.53-2.21 3.31v2.77h3.57c2.08-1.92 3.28-4.74 3.28-8.09z" fill="#4285F4"/>
            <path d="M12 23c2.97 0 5.46-.98 7.28-2.66l-3.57-2.77c-.98.66-2.23 1.06-3.71 1.06-2.86 0-5.29-1.93-6.16-4.53H2.18v2.84C3.99 20.53 7.7 23 12 23z" fill="#34A853"/>
            <path d="M5.84 14.09c-.22-.66-.35-1.36-.35-2.09s.13-1.43.35-2.09V7.07H2.18C1.43 8.55 1 10.22 1 12s.43 3.45 1.18 4.93l2.85-2.22.81-.62z" fill="#FBBC05"/>
            <path d="M12 5.38c1.62 0 3.06.56 4.21 1.64l3.15-3.15C17.45 2.09 14.97 1 12 1 7.7 1 3.99 3.47 2.18 7.07l3.66 2.84c.87-2.6 3.3-4.53 6.16-4.53z" fill="#EA4335"/>
        </svg>
        Google Ads
    </button>
</div>
                    <button type="submit" id="sky-utm-create-button" class="button button-primary">
                        <svg width="16" height="16" viewBox="0 0 16 16" fill="none">
                            <path d="M8 3V13M3 8H13" stroke="currentColor" stroke-width="2" stroke-linecap="round"/>
                        </svg>
                        <?php _e('Create UTM Link', 'sky360'); ?>
                    </button>
                </form>
                
                <div id="sky-utm-link-result" style="display: none;"></div>
            </div>
            
            <!-- Analytics Summary -->
            <div class="sky-utm-analytics-cards">
                <div class="sky-utm-metric-card">
                    <h4><?php _e('Total Clicks', 'sky360'); ?></h4>
                    <div class="sky-utm-metric-value" id="sky-utm-total-clicks">0</div>
                </div>
                
                <div class="sky-utm-metric-card">
                    <h4><?php _e('Conversions', 'sky360'); ?></h4>
                    <div class="sky-utm-metric-value" id="sky-utm-total-conversions">0</div>
                </div>
                
                <div class="sky-utm-metric-card">
                    <h4><?php _e('Conversion Rate', 'sky360'); ?></h4>
                    <div class="sky-utm-metric-value" id="sky-utm-conversion-rate">0%</div>
                </div>
                
                <div class="sky-utm-metric-card">
                    <h4><?php _e('Revenue', 'sky360'); ?></h4>
                    <div class="sky-utm-metric-value" id="sky-utm-total-revenue"><?php echo function_exists('get_woocommerce_currency_symbol') ? get_woocommerce_currency_symbol() : '$'; ?>0</div>
                </div>
                
                <div class="sky-utm-metric-card">
                    <h4><?php _e('Avg Order Value', 'sky360'); ?></h4>
                    <div class="sky-utm-metric-value" id="sky-utm-avg-order-value"><?php echo function_exists('get_woocommerce_currency_symbol') ? get_woocommerce_currency_symbol() : '$'; ?>0</div>
                </div>
            </div>

            <!-- Links Table -->
            <div class="sky-utm-links-container">
                <div class="sky-utm-table-header">
                    <h3><?php _e('UTM Links', 'sky360'); ?></h3>
                    
                    <div class="sky-utm-filters">
                        <select id="sky-utm-filter-source" class="sky-utm-filter">
                            <option value=""><?php _e('All Sources', 'sky360'); ?></option>
                        </select>
                        
                        <select id="sky-utm-filter-campaign" class="sky-utm-filter">
                            <option value=""><?php _e('All Campaigns', 'sky360'); ?></option>
                        </select>
                    </div>
                </div>
                
                <table id="sky-utm-links-table" class="wp-list-table widefat fixed striped">
                    <thead>
                        <tr>
                            <th style="width: 30px;" class="text-center">
                                <input type="checkbox" id="sky-utm-select-all" title="Select all">
                            </th>
                            <th><?php _e('Link', 'sky360'); ?></th>
                            <th><?php _e('UTM Parameters', 'sky360'); ?></th>
                            <th class="sortable-column" data-sort="clicks" style="cursor: pointer;" title="Click to sort">
                                <?php _e('Clicks', 'sky360'); ?>
                            </th>
                            <th class="sortable-column" data-sort="conversions" style="cursor: pointer;" title="Click to sort">
                                <?php _e('Conversions', 'sky360'); ?>
                            </th>
                            <th class="sortable-column" data-sort="conversion_rate" style="cursor: pointer;" title="Click to sort">
                                <?php _e('Conv. Rate', 'sky360'); ?>
                            </th>
                            <th class="sortable-column" data-sort="revenue" style="cursor: pointer;" title="Click to sort">
                                <?php _e('Revenue', 'sky360'); ?>
                            </th>
                            <th><?php _e('Actions', 'sky360'); ?></th>
                        </tr>
                    </thead>
                    <tbody>
                        <tr>
                            <td colspan="8" class="text-center"><?php _e('Loading...', 'sky360'); ?></td>
                        </tr>
                    </tbody>
                </table>
            </div>

        </div>
    <?php
    sky360_content_wrapper_end();
    sky360_admin_page_end();
}

/**
 * Enqueue UTM admin assets
 */
function sky_seo_enqueue_utm_admin_assets($hook) {
    // Only load on our pages
    if (strpos($hook, 'sky-seo-utm') === false) {
        return;
    }
    
    // Check if constants are defined
    if (!defined('SKY_SEO_BOOST_PLUGIN_URL') || !defined('SKY_SEO_BOOST_VERSION')) {
        return;
    }
    
    $utm_url = SKY_SEO_BOOST_PLUGIN_URL . 'includes/utm/';
    
    // Enqueue styles
    wp_enqueue_style(
        'sky-seo-utm-styles',
        $utm_url . 'utm.css',
        array(),
        SKY_SEO_BOOST_VERSION
    );
    
    // Enqueue scripts
    wp_enqueue_script('jquery');
    
    // Enqueue Chart.js
    wp_enqueue_script(
        'chartjs',
        'https://cdn.jsdelivr.net/npm/chart.js@4.4.0/dist/chart.umd.js',
        array(),
        '4.4.0',
        true
    );
    
    // Enqueue UTM dashboard script
    wp_enqueue_script(
        'sky-seo-utm-dashboard',
        $utm_url . 'dashboard-utm.js',
        array('jquery', 'chartjs'),
        SKY_SEO_BOOST_VERSION,
        true
    );
    
    // Localize script
    wp_localize_script('sky-seo-utm-dashboard', 'skyInsights', array(
        'ajaxurl' => admin_url('admin-ajax.php'),
        'nonce' => wp_create_nonce('sky_insights_nonce'),
        'currency' => function_exists('get_woocommerce_currency_symbol') ? get_woocommerce_currency_symbol() : '$',
        'dateFormat' => get_option('date_format'),
        'isDebug' => defined('WP_DEBUG') && WP_DEBUG
    ));
}
add_action('admin_enqueue_scripts', 'sky_seo_enqueue_utm_admin_assets');

/**
 * Register AJAX handlers for UTM tracking
 */
function sky_seo_register_utm_ajax_handlers() {
    // Only register if the tracker class exists
    if (!class_exists('SkyInsightsUTMTracker')) {
        return;
    }
    
    // Get the tracker instance
    $tracker = SkyInsightsUTMTracker::get_instance();
    
    // List of AJAX actions and their methods
    $ajax_actions = array(
        'sky_insights_create_utm_link' => array($tracker, 'ajax_create_utm_link'),
        'sky_insights_get_utm_links' => array($tracker, 'ajax_get_utm_links'),
        'sky_insights_delete_utm_link' => array($tracker, 'ajax_delete_utm_link'),
        'sky_insights_get_utm_analytics' => array($tracker, 'ajax_get_utm_analytics'),
        'sky_insights_update_utm_link' => array($tracker, 'ajax_update_utm_link'),
        'sky_insights_get_utm_click_details' => array($tracker, 'ajax_get_utm_click_details')
    );
    
    // Register each AJAX action
    foreach ($ajax_actions as $action => $callback) {
        // For logged in users
        add_action('wp_ajax_' . $action, $callback);
    }
}

// Hook this to init with priority after the tracker is loaded
add_action('init', 'sky_seo_register_utm_ajax_handlers', 25);

// Deactivation cleanup
if (defined('SKY_SEO_BOOST_FILE')) {
    register_deactivation_hook(SKY_SEO_BOOST_FILE, 'sky_seo_utm_deactivate');
}

function sky_seo_utm_deactivate() {
    // Clear scheduled hooks
    wp_clear_scheduled_hook('sky_seo_utm_check_tables');
    wp_clear_scheduled_hook('sky_seo_utm_cleanup_old_data');
    
    // Clear transients
    delete_transient('sky_seo_utm_just_activated');
}