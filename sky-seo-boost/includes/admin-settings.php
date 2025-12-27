<?php
// Prevent direct access
if (!defined('ABSPATH')) {
    exit;
}

/**
 * WPML Phase 3: Register admin interface strings for translation
 *
 * This function registers all admin UI strings with WPML String Translation
 * so they can be translated in different languages.
 *
 * @since 4.3.0
 */
function sky_seo_register_admin_wpml_strings() {
    // Only register if WPML String Translation is active
    if (!function_exists('icl_register_string')) {
        return;
    }

    // Tab Names
    icl_register_string('sky360', 'Admin Tab: General', 'General');
    icl_register_string('sky360', 'Admin Tab: Tracking Codes', 'Tracking Codes');
    icl_register_string('sky360', 'Admin Tab: SEO Plugin Integration', 'SEO Plugin Integration');
    icl_register_string('sky360', 'Admin Tab: Duplicate Post', 'Duplicate Post');

    // General Settings Section
    icl_register_string('sky360', 'Admin: General Plugin Settings', 'General Plugin Settings');
    icl_register_string('sky360', 'Admin: Posts Per Page', 'Posts Per Page');
    icl_register_string('sky360', 'Admin: Posts Per Page Description', 'Number of posts to display per page in admin listings.');
    icl_register_string('sky360', 'Admin: Indexing & Sitemap Settings', 'Indexing & Sitemap Settings');
    icl_register_string('sky360', 'Admin: Sitemap Priorities', 'Sitemap Priorities');
    icl_register_string('sky360', 'Admin: Sitemap Priorities Description', 'Higher values = more important for search engines (0.1-1.0)');
    icl_register_string('sky360', 'Admin: Areas Priority', 'Areas Priority');
    icl_register_string('sky360', 'Admin: Trending Priority', 'Trending Priority');
    icl_register_string('sky360', 'Admin: Sectors Priority', 'Sectors Priority');
    icl_register_string('sky360', 'Admin: Update Frequencies', 'Update Frequencies');
    icl_register_string('sky360', 'Admin: Update Frequencies Description', 'Tell search engines how often content typically changes');
    icl_register_string('sky360', 'Admin: Areas Frequency', 'Areas Frequency');
    icl_register_string('sky360', 'Admin: Trending Frequency', 'Trending Frequency');
    icl_register_string('sky360', 'Admin: Sectors Frequency', 'Sectors Frequency');

    // Frequency Options
    icl_register_string('sky360', 'Admin Frequency: Always', 'Always');
    icl_register_string('sky360', 'Admin Frequency: Hourly', 'Hourly');
    icl_register_string('sky360', 'Admin Frequency: Daily', 'Daily');
    icl_register_string('sky360', 'Admin Frequency: Weekly', 'Weekly');
    icl_register_string('sky360', 'Admin Frequency: Monthly', 'Monthly');
    icl_register_string('sky360', 'Admin Frequency: Yearly', 'Yearly');
    icl_register_string('sky360', 'Admin Frequency: Never', 'Never');

    // Indexing Boost Features
    icl_register_string('sky360', 'Admin: Indexing Boost Features', 'Indexing Boost Features');
    icl_register_string('sky360', 'Admin: Auto-Ping Search Engines', 'Auto-Ping Search Engines');
    icl_register_string('sky360', 'Admin: Auto-Ping Description', 'Automatically notify search engines when new content is published');
    icl_register_string('sky360', 'Admin: Auto-Ping Details', 'Pings Google and Bing when you publish new Areas, Trending, or Sectors posts.');
    icl_register_string('sky360', 'Admin: RSS Feed Integration', 'RSS Feed Integration');
    icl_register_string('sky360', 'Admin: RSS Feed Description', 'Include custom post types in RSS feed');
    icl_register_string('sky360', 'Admin: RSS Feed Details', 'Helps search engines discover new content through RSS feeds.');
    icl_register_string('sky360', 'Admin: Force Indexing', 'Force Indexing');
    icl_register_string('sky360', 'Admin: Force Indexing Description', 'Add enhanced meta tags to force indexing of new posts');
    icl_register_string('sky360', 'Admin: Force Indexing Details', 'Adds special meta tags that encourage search engines to index content immediately.');

    // SEO Integration
    icl_register_string('sky360', 'Admin: SEO Plugin Integration', 'SEO Plugin Integration');
    icl_register_string('sky360', 'Admin: Active SEO Plugin', 'Active SEO Plugin');
    icl_register_string('sky360', 'Admin: SEO Plugin None', 'None');
    icl_register_string('sky360', 'Admin: SEO Plugin Yoast', 'Yoast SEO');
    icl_register_string('sky360', 'Admin: SEO Plugin RankMath', 'RankMath');
    icl_register_string('sky360', 'Admin: SEO Plugin SEOPress', 'SEOPress');
    icl_register_string('sky360', 'Admin: SEO Plugin TSF', 'The SEO Framework');
    icl_register_string('sky360', 'Admin: SEO Plugin AIOSEO', 'All in One SEO');
    icl_register_string('sky360', 'Admin: SEO Plugin Squirrly', 'Squirrly SEO');
    icl_register_string('sky360', 'Admin: SEO Plugin Description', 'Select your active SEO plugin to enable meta tag integration.');
    icl_register_string('sky360', 'Admin: Integration Status', 'Integration Status');
    icl_register_string('sky360', 'Admin: Sitemap Diagnostics & Tools', 'Sitemap Diagnostics & Tools');
    icl_register_string('sky360', 'Admin: Quick Fixes', 'Quick Fixes');
    icl_register_string('sky360', 'Admin: Quick Fixes Description', 'Try these fixes if your sitemaps are showing 404 errors:');
    icl_register_string('sky360', 'Admin Button: Flush Rewrite Rules', 'Flush Rewrite Rules');
    icl_register_string('sky360', 'Admin: Flush Rewrite Description', 'This refreshes WordPress permalinks and can fix 404 errors on sitemaps.');
    icl_register_string('sky360', 'Admin Button: Auto-Detect SEO Plugin', 'Auto-Detect SEO Plugin');
    icl_register_string('sky360', 'Admin: Auto-Detect Description', 'Automatically detect and configure your installed SEO plugin.');
    icl_register_string('sky360', 'Admin Button: Test Sitemaps', 'Test Sitemaps');
    icl_register_string('sky360', 'Admin: Test Sitemaps Description', 'Check if all your sitemap URLs are accessible (no 404 errors).');

    // Duplicate Post Settings
    icl_register_string('sky360', 'Admin: Duplicate Post Settings', 'Duplicate Post Settings');
    icl_register_string('sky360', 'Admin: Enable Duplicate Feature', 'Enable Duplicate Feature');
    icl_register_string('sky360', 'Admin: Duplicate Feature Description', 'Add "Duplicate" link to post/page actions');
    icl_register_string('sky360', 'Admin: Post Types to Duplicate', 'Post Types to Duplicate');
    icl_register_string('sky360', 'Admin: Duplicate Status', 'Duplicate Status');
    icl_register_string('sky360', 'Admin: Duplicate Status Description', 'Status of duplicated posts');
    icl_register_string('sky360', 'Admin Status: Draft', 'Draft');
    icl_register_string('sky360', 'Admin Status: Pending Review', 'Pending Review');
    icl_register_string('sky360', 'Admin Status: Private', 'Private');
    icl_register_string('sky360', 'Admin: Redirect After Duplicate', 'Redirect After Duplicate');
    icl_register_string('sky360', 'Admin: Redirect Description', 'Redirect to edit screen after duplication');
    icl_register_string('sky360', 'Admin: Title Prefix', 'Title Prefix');
    icl_register_string('sky360', 'Admin: Title Prefix Description', 'Text to add before the title (e.g., "Copy of ")');
    icl_register_string('sky360', 'Admin: Title Suffix', 'Title Suffix');
    icl_register_string('sky360', 'Admin: Title Suffix Description', 'Text to add after the title (optional)');
    icl_register_string('sky360', 'Admin: Duplicate Author', 'Duplicate Author');
    icl_register_string('sky360', 'Admin: Duplicate Author Description', 'Who should be set as the author of duplicated posts');
    icl_register_string('sky360', 'Admin Author: Original Author', 'Original Author');
    icl_register_string('sky360', 'Admin Author: Current User', 'Current User');
    icl_register_string('sky360', 'Admin: Copy Custom Fields', 'Copy Custom Fields');
    icl_register_string('sky360', 'Admin: Copy Custom Fields Description', 'Copy all custom fields to duplicate');
    icl_register_string('sky360', 'Admin: Copy Taxonomies', 'Copy Taxonomies');
    icl_register_string('sky360', 'Admin: Copy Taxonomies Description', 'Copy categories, tags, and custom taxonomies');
    icl_register_string('sky360', 'Admin: Show in Admin Bar', 'Show in Admin Bar');
    icl_register_string('sky360', 'Admin: Admin Bar Description', 'Show duplicate link in admin bar when viewing a post');

    // Default duplicate prefix/suffix
    icl_register_string('sky360', 'Duplicate: Default Prefix', 'Copy of ');
    icl_register_string('sky360', 'Duplicate: Default Suffix', '');

    // Button Labels
    icl_register_string('sky360', 'Admin Button: Save Changes', 'Save Changes');

    // Analytics Labels
    icl_register_string('sky360', 'Analytics: Total Clicks', 'Total Clicks');
    icl_register_string('sky360', 'Analytics: Google Clicks', 'Google Clicks');
    icl_register_string('sky360', 'Analytics: Social Clicks', 'Social Clicks');
    icl_register_string('sky360', 'Analytics: Direct Clicks', 'Direct Clicks');
    icl_register_string('sky360', 'Analytics: Human Clicks', 'Human Clicks');
    icl_register_string('sky360', 'Analytics: Bot Clicks', 'Bot Clicks');
    icl_register_string('sky360', 'Analytics: All', 'All');
    icl_register_string('sky360', 'Analytics: Human', 'Human');
    icl_register_string('sky360', 'Analytics: Google', 'Google');
    icl_register_string('sky360', 'Analytics: Social', 'Social');
    icl_register_string('sky360', 'Analytics: Direct', 'Direct');
    icl_register_string('sky360', 'Analytics: Clicks', 'Clicks');
    icl_register_string('sky360', 'Analytics: Title', 'Title');
    icl_register_string('sky360', 'Analytics: Type', 'Type');
    icl_register_string('sky360', 'Analytics: Traffic Sources', 'Traffic Sources');
    icl_register_string('sky360', 'Analytics: Post Type', 'Post Type');
    icl_register_string('sky360', 'Analytics: Total Clicks (Human Only)', 'Total Clicks (Human Only)');
    icl_register_string('sky360', 'Analytics: Sort by Total Clicks', 'Sort by Total Clicks');
    icl_register_string('sky360', 'Analytics: Sort by Google Clicks', 'Sort by Google Clicks');
    icl_register_string('sky360', 'Analytics: Sort by Social Clicks', 'Sort by Social Clicks');
    icl_register_string('sky360', 'Analytics: Sort by Direct Clicks', 'Sort by Direct Clicks');

    // Dashboard Widget Strings
    icl_register_string('sky360', 'Dashboard: Duplicate', 'Duplicate');
    icl_register_string('sky360', 'Dashboard: Duplicate this item', 'Duplicate this item');
    icl_register_string('sky360', 'Dashboard: Duplicate This', 'Duplicate This');
    icl_register_string('sky360', 'Dashboard: Duplicating...', 'Duplicating...');
    icl_register_string('sky360', 'Dashboard: Duplicated!', 'Duplicated!');
    icl_register_string('sky360', 'Dashboard: Error', 'Error');
    icl_register_string('sky360', 'Dashboard: An error occurred', 'An error occurred');
}

// Register admin strings on init
add_action('init', 'sky_seo_register_admin_wpml_strings', 20);

/**
 * WPML Phase 3: Get translated string from WPML for admin interface
 *
 * @param string $original Original string
 * @param string $name String name/identifier
 * @return string Translated string or original if WPML not active
 * @since 4.3.0
 */
function sky_seo_get_translated_admin_string($original, $name) {
    // If WPML String Translation is active, get translation
    if (function_exists('icl_t')) {
        return icl_t('sky360', $name, $original);
    }

    // Fallback to WordPress i18n
    return __($original, 'sky360');
}

// Add AJAX handler for Google Ads test
add_action('wp_ajax_sky_seo_test_google_ads', 'sky_seo_ajax_test_google_ads');
function sky_seo_ajax_test_google_ads() {
    // Verify nonce
    if (!isset($_POST['nonce']) || !wp_verify_nonce($_POST['nonce'], 'sky_seo_test_ads')) {
        wp_send_json_error('Security check failed');
    }
    
    // Check permissions
    if (!current_user_can('manage_options')) {
        wp_send_json_error('Permission denied');
    }
    
    // Get settings
    $settings = get_option('sky_seo_settings', []);
    
    // Check if Google Ads is configured
    if (empty($settings['google_ads_conversion_id']) || empty($settings['google_ads_enabled'])) {
        wp_send_json_error('Google Ads is not properly configured');
    }
    
    // If we reach here, settings are saved correctly
    wp_send_json_success([
        'message' => 'Google Ads settings are configured correctly',
        'conversion_id' => $settings['google_ads_conversion_id'],
        'enabled' => $settings['google_ads_enabled']
    ]);
}

// Sanitize Settings
function sky_seo_sanitize_settings($input) {
    // Get existing settings first
    $existing_settings = get_option('sky_seo_settings', []);
    
    // Start with existing settings to preserve other tab values
    $sanitized = $existing_settings;
    
    // Handle null input
    if (!is_array($input)) {
        $input = [];
    }
    
    // Check if this is a duplicate form submission based on referrer or hidden field
    $is_duplicate_form_submission = false;
    if (isset($_POST['_wp_http_referer'])) {
        $referrer = wp_unslash($_POST['_wp_http_referer']);
        if (strpos($referrer, 'tab=duplicate') !== false) {
            $is_duplicate_form_submission = true;
        }
    }
    
    // Determine which form/tab is being submitted by checking for unique fields
    $is_tracking_form = isset($input['ga_measurement_id']) || isset($input['google_ads_conversion_id']) || 
                        isset($input['gtm_container_id']) || isset($input['meta_pixel_id']);
    
    $is_seo_form = isset($input['active_seo_plugin']);
    
    $is_duplicate_form = isset($input['_duplicate_form']) || $is_duplicate_form_submission || 
                         isset($input['duplicate_status']) || isset($input['duplicate_redirect']) || 
                         array_key_exists('duplicate_feature_enabled', $input) || array_key_exists('duplicate_post_types', $input);
    
    $is_indexing_form = isset($input['sitemap_priority_areas']) || isset($input['sitemap_priority_trending']) || 
                        isset($input['sitemap_priority_sectors']) || isset($input['sitemap_frequency_areas']) ||
                        isset($input['sitemap_frequency_trending']) || isset($input['sitemap_frequency_sectors']);
    
    // Only process the fields from the form that was actually submitted
    
    if ($is_seo_form) {
        // SEO Plugin Integration fields only
        $valid_plugins = ['none', 'yoast', 'rankmath', 'seopress', 'tsf', 'aioseo', 'squirrly'];
        $sanitized['active_seo_plugin'] = in_array($input['active_seo_plugin'], $valid_plugins)
            ? sanitize_text_field($input['active_seo_plugin'])
            : 'none';
        
        if (isset($input['seo_plugin_settings']) && is_array($input['seo_plugin_settings'])) {
            $sanitized['seo_plugin_settings'] = array_map('sanitize_text_field', $input['seo_plugin_settings']);
        }
    }
    
    if ($is_tracking_form) {
        // Tracking Settings only
        if (isset($input['ga_measurement_id'])) {
            $sanitized['ga_measurement_id'] = sanitize_text_field($input['ga_measurement_id']);
        }
        $sanitized['ga_enabled'] = isset($input['ga_enabled']) ? 1 : 0;
        
        // Google Ads - UPDATED FIELDS
        if (isset($input['google_ads_conversion_id'])) {
            $sanitized['google_ads_conversion_id'] = sanitize_text_field($input['google_ads_conversion_id']);
        }
        if (isset($input['google_ads_conversion_type'])) {
            $valid_types = ['woocommerce', 'form_submission', 'custom'];
            $sanitized['google_ads_conversion_type'] = in_array($input['google_ads_conversion_type'], $valid_types)
                ? $input['google_ads_conversion_type']
                : 'woocommerce';
        }
        if (isset($input['google_ads_conversion_label'])) {
            $sanitized['google_ads_conversion_label'] = sanitize_text_field($input['google_ads_conversion_label']);
        }
        if (isset($input['google_ads_thank_you_page_id'])) {
            $sanitized['google_ads_thank_you_page_id'] = absint($input['google_ads_thank_you_page_id']);
        }
        if (isset($input['google_ads_custom_thank_you_url'])) {
            // Ensure it starts with /
            $custom_url = sanitize_text_field($input['google_ads_custom_thank_you_url']);
            if (!empty($custom_url) && substr($custom_url, 0, 1) !== '/') {
                $custom_url = '/' . $custom_url;
            }
            $sanitized['google_ads_custom_thank_you_url'] = $custom_url;
        }
        if (isset($input['google_ads_conversion_value'])) {
            $sanitized['google_ads_conversion_value'] = floatval($input['google_ads_conversion_value']);
        }
        $sanitized['google_ads_enabled'] = isset($input['google_ads_enabled']) ? 1 : 0;
        
        // Keep backward compatibility - remove old fields
        if (isset($sanitized['google_ads_purchase_label'])) {
            unset($sanitized['google_ads_purchase_label']);
        }
        if (isset($sanitized['google_ads_thank_you_page'])) {
            unset($sanitized['google_ads_thank_you_page']);
        }
        
        if (isset($input['gtm_container_id'])) {
            $sanitized['gtm_container_id'] = sanitize_text_field($input['gtm_container_id']);
        }
        $sanitized['gtm_enabled'] = isset($input['gtm_enabled']) ? 1 : 0;
        
        if (isset($input['meta_pixel_id'])) {
            $sanitized['meta_pixel_id'] = sanitize_text_field($input['meta_pixel_id']);
        }
        $sanitized['meta_pixel_enabled'] = isset($input['meta_pixel_enabled']) ? 1 : 0;
        
        $sanitized['tracking_test_mode'] = isset($input['tracking_test_mode']) ? 1 : 0;
    }
    
    if ($is_duplicate_form) {
        // Duplicate Feature Settings only
        // IMPORTANT: Handle checkbox properly when unchecked
        if (array_key_exists('duplicate_feature_enabled', $input)) {
            $sanitized['duplicate_feature_enabled'] = !empty($input['duplicate_feature_enabled']) ? 1 : 0;
        } else {
            // If checkbox is not in input array, it means it was unchecked
            $sanitized['duplicate_feature_enabled'] = 0;
        }
        
        if (isset($input['duplicate_post_types'])) {
            $sanitized['duplicate_post_types'] = is_array($input['duplicate_post_types'])
                ? array_map('sanitize_text_field', $input['duplicate_post_types'])
                : [];
        } else {
            // No post types selected
            $sanitized['duplicate_post_types'] = [];
        }
        
        $sanitized['duplicate_status'] = isset($input['duplicate_status']) && in_array($input['duplicate_status'], ['draft', 'pending', 'private'])
            ? $input['duplicate_status']
            : 'draft';
            
        $sanitized['duplicate_redirect'] = isset($input['duplicate_redirect']) ? 1 : 0;
        $sanitized['duplicate_prefix'] = isset($input['duplicate_prefix']) 
            ? sanitize_text_field($input['duplicate_prefix'])
            : 'Copy of ';
        $sanitized['duplicate_suffix'] = isset($input['duplicate_suffix']) 
            ? sanitize_text_field($input['duplicate_suffix'])
            : '';
        
        // Additional options
        $sanitized['duplicate_author'] = isset($input['duplicate_author']) && in_array($input['duplicate_author'], ['original', 'current'])
            ? $input['duplicate_author']
            : 'original';
            
        $sanitized['duplicate_meta'] = isset($input['duplicate_meta']) ? 1 : 0;
        $sanitized['duplicate_taxonomies'] = isset($input['duplicate_taxonomies']) ? 1 : 0;
        $sanitized['duplicate_comments'] = isset($input['duplicate_comments']) ? 1 : 0;
        $sanitized['duplicate_admin_bar'] = isset($input['duplicate_admin_bar']) ? 1 : 0;
    }
    
    if ($is_indexing_form || isset($input['posts_per_page'])) {
        // General and Indexing Settings
        if (isset($input['posts_per_page'])) {
            $sanitized['posts_per_page'] = absint($input['posts_per_page']);
        }
        
        // Priority values (0.0 to 1.0)
        if (isset($input['sitemap_priority_areas'])) {
            $priority = floatval($input['sitemap_priority_areas']);
            $sanitized['sitemap_priority_areas'] = max(0, min(1, $priority));
        }
        if (isset($input['sitemap_priority_trending'])) {
            $priority = floatval($input['sitemap_priority_trending']);
            $sanitized['sitemap_priority_trending'] = max(0, min(1, $priority));
        }
        if (isset($input['sitemap_priority_sectors'])) {
            $priority = floatval($input['sitemap_priority_sectors']);
            $sanitized['sitemap_priority_sectors'] = max(0, min(1, $priority));
        }
        
        // Frequency values
        $valid_frequencies = ['always', 'hourly', 'daily', 'weekly', 'monthly', 'yearly', 'never'];
        if (isset($input['sitemap_frequency_areas'])) {
            $sanitized['sitemap_frequency_areas'] = in_array($input['sitemap_frequency_areas'], $valid_frequencies)
                ? $input['sitemap_frequency_areas']
                : 'weekly';
        }
        if (isset($input['sitemap_frequency_trending'])) {
            $sanitized['sitemap_frequency_trending'] = in_array($input['sitemap_frequency_trending'], $valid_frequencies)
                ? $input['sitemap_frequency_trending']
                : 'daily';
        }
        if (isset($input['sitemap_frequency_sectors'])) {
            $sanitized['sitemap_frequency_sectors'] = in_array($input['sitemap_frequency_sectors'], $valid_frequencies)
                ? $input['sitemap_frequency_sectors']
                : 'weekly';
        }
        
        // Max entries
        if (isset($input['sitemap_max_entries'])) {
            $max_entries = intval($input['sitemap_max_entries']);
            $sanitized['sitemap_max_entries'] = max(100, min(50000, $max_entries));
        }
        
        // Checkboxes
        $sanitized['sitemap_include_areas'] = isset($input['sitemap_include_areas']) ? 1 : 0;
        $sanitized['sitemap_include_trending'] = isset($input['sitemap_include_trending']) ? 1 : 0;
        $sanitized['sitemap_include_sectors'] = isset($input['sitemap_include_sectors']) ? 1 : 0;
        $sanitized['sitemap_include_images'] = isset($input['sitemap_include_images']) ? 1 : 0;
        
        // Robots.txt
        $sanitized['robots_sitemap_reference'] = isset($input['robots_sitemap_reference']) ? 1 : 0;
        
        if (isset($input['robots_additional_rules'])) {
            $sanitized['robots_additional_rules'] = sanitize_textarea_field($input['robots_additional_rules']);
        }
        
        // Additional indexing options
        $sanitized['auto_ping_search_engines'] = isset($input['auto_ping_search_engines']) ? 1 : 0;
        $sanitized['include_in_rss'] = isset($input['include_in_rss']) ? 1 : 0;
        $sanitized['force_index_new_posts'] = isset($input['force_index_new_posts']) ? 1 : 0;
    }

    return $sanitized;
}

// Settings Page with Tabs
function sky_seo_settings_page() {
    $active_tab = isset($_GET['tab']) ? sanitize_text_field($_GET['tab']) : 'general';
    ?>
    <div class="sky360-admin-page">
        <!-- WordPress Notices Area - appears above everything -->
        <div class="sky360-notices-area">
            <?php settings_errors('sky_seo_settings'); ?>
        </div>

        <div class="sky360-page-wrapper">
            <!-- Top Bar -->
            <div class="sky360-topbar">
                <div class="sky360-topbar-left">
                    <div class="sky360-topbar-logo">
                        <span class="dashicons dashicons-chart-line" style="color: white; font-size: 24px; width: 24px; height: 24px;"></span>
                    </div>
                    <div>
                        <h1 class="sky360-topbar-title"><?php esc_html_e('Sky360', 'sky360'); ?></h1>
                        <p class="sky360-topbar-subtitle"><?php esc_html_e('Settings & Configuration', 'sky360'); ?></p>
                    </div>
                </div>
                <div class="sky360-topbar-right">
                    <span class="sky360-topbar-badge">v<?php echo esc_html(SKY360_VERSION); ?></span>
                    <a href="https://skywebdesign.co.uk/support" target="_blank" class="sky360-topbar-button">
                        <span class="dashicons dashicons-sos" style="font-size: 16px; width: 16px; height: 16px;"></span>
                        <?php esc_html_e('Support', 'sky360'); ?>
                    </a>
                </div>
            </div>

            <!-- Navigation Tabs -->
            <div class="sky-seo-navigation-wrapper">
                <ul class="sky-seo-navigation-menu">
                    <li>
                        <a href="?page=sky-seo-settings&tab=general" class="nav-link <?php echo $active_tab === 'general' ? 'active' : ''; ?>">
                            <span class="dashicons dashicons-admin-generic"></span>
                            <?php esc_html_e('General', 'sky360'); ?>
                        </a>
                    </li>
                    <li>
                        <a href="?page=sky-seo-settings&tab=tracking" class="nav-link <?php echo $active_tab === 'tracking' ? 'active' : ''; ?>">
                            <span class="dashicons dashicons-chart-area"></span>
                            <?php esc_html_e('Tracking Codes', 'sky360'); ?>
                        </a>
                    </li>
                    <li>
                        <a href="?page=sky-seo-settings&tab=seo-integration" class="nav-link <?php echo $active_tab === 'seo-integration' ? 'active' : ''; ?>">
                            <span class="dashicons dashicons-search"></span>
                            <?php esc_html_e('SEO Plugin Integration', 'sky360'); ?>
                        </a>
                    </li>
                    <li>
                        <a href="?page=sky-seo-settings&tab=duplicate" class="nav-link <?php echo $active_tab === 'duplicate' ? 'active' : ''; ?>">
                            <span class="dashicons dashicons-admin-page"></span>
                            <?php esc_html_e('Duplicate Post', 'sky360'); ?>
                        </a>
                    </li>
                    <?php
                    // Allow other features to add tabs
                    do_action('sky_seo_settings_tabs', $active_tab);
                    ?>
                </ul>
            </div>

            <!-- Content Wrapper -->
            <div class="sky-seo-content-wrapper">
                <?php
                // Allow other features to add content before default tabs
                do_action('sky_seo_settings_content', $active_tab);

                // Default tab content
                if ($active_tab === 'general') {
                    sky_seo_general_settings_tab();
                } elseif ($active_tab === 'tracking') {
                    sky_seo_analytics_check_tab();
                } elseif ($active_tab === 'seo-integration') {
                    sky_seo_seo_integration_tab();
                } elseif ($active_tab === 'duplicate') {
                    sky_seo_duplicate_settings_tab();
                }
                ?>
            </div>

            <!-- Footer -->
            <div class="sky-seo-footer" style="margin-top: 24px;">
                <div class="sky-seo-powered-by">
                    <?php esc_html_e('Powered by', 'sky360'); ?>
                    <a href="https://skywebdesign.co.uk" target="_blank">Sky Web Design</a>
                </div>
            </div>
        </div>
    </div>
    <?php
}

// General Settings Tab (now includes Indexing Settings)
function sky_seo_general_settings_tab() {
    $settings = get_option('sky_seo_settings', [
        'posts_per_page' => 20,
        'sitemap_priority_areas' => 0.9,
        'sitemap_priority_trending' => 0.9,
        'sitemap_priority_sectors' => 0.8,
        'sitemap_frequency_areas' => 'daily',
        'sitemap_frequency_trending' => 'daily',
        'sitemap_frequency_sectors' => 'weekly',
        'auto_ping_search_engines' => 1,
        'include_in_rss' => 1,
        'force_index_new_posts' => 1,
    ]);
    ?>
    
    <form method="post" action="options.php" class="sky-seo-settings-form">
        <?php
        settings_fields('sky_seo_settings');
        do_settings_sections('sky_seo_settings');
        ?>
        
        <!-- General Settings Content -->
        <div class="sky-seo-settings-section">
            
            <!-- General Plugin Settings -->
            <div class="sky-seo-settings-card">
                <h3><?php _e('General Plugin Settings', 'sky360'); ?></h3>
                <table class="form-table">
                    <tr>
                        <th scope="row"><?php _e('Posts Per Page', 'sky360'); ?></th>
                        <td>
                            <input type="number" name="sky_seo_settings[posts_per_page]" 
                                   value="<?php echo esc_attr($settings['posts_per_page'] ?? 20); ?>" 
                                   min="1" max="100" />
                            <p class="description"><?php _e('Number of posts to display per page in admin listings.', 'sky360'); ?></p>
                        </td>
                    </tr>
                </table>
            </div>
            
            <!-- Indexing & Sitemap Settings -->
            <div class="sky-seo-settings-card">
                <h3><?php _e('Indexing & Sitemap Settings', 'sky360'); ?></h3>
                <table class="form-table">
                    <tr>
                        <th scope="row" colspan="2">
                            <h4 style="margin: 0;"><?php _e('Sitemap Priorities', 'sky360'); ?></h4>
                            <p class="description" style="font-weight: normal;"><?php _e('Higher values = more important for search engines (0.1-1.0)', 'sky360'); ?></p>
                        </th>
                    </tr>
                    <tr>
                        <th scope="row"><?php _e('Areas Priority', 'sky360'); ?></th>
                        <td>
                            <input type="number" step="0.1" min="0.1" max="1.0" 
                                   name="sky_seo_settings[sitemap_priority_areas]" 
                                   value="<?php echo esc_attr($settings['sitemap_priority_areas'] ?? 0.9); ?>" />
                            <p class="description"><?php _e('Sitemap priority for Areas (0.1-1.0). Higher = more important. Default: 0.9', 'sky360'); ?></p>
                        </td>
                    </tr>
                    <tr>
                        <th scope="row"><?php _e('Trending Priority', 'sky360'); ?></th>
                        <td>
                            <input type="number" step="0.1" min="0.1" max="1.0" 
                                   name="sky_seo_settings[sitemap_priority_trending]" 
                                   value="<?php echo esc_attr($settings['sitemap_priority_trending'] ?? 0.9); ?>" />
                            <p class="description"><?php _e('Sitemap priority for Trending Searches. Default: 0.9', 'sky360'); ?></p>
                        </td>
                    </tr>
                    <tr>
                        <th scope="row"><?php _e('Sectors Priority', 'sky360'); ?></th>
                        <td>
                            <input type="number" step="0.1" min="0.1" max="1.0" 
                                   name="sky_seo_settings[sitemap_priority_sectors]" 
                                   value="<?php echo esc_attr($settings['sitemap_priority_sectors'] ?? 0.8); ?>" />
                            <p class="description"><?php _e('Sitemap priority for Sectors. Default: 0.8', 'sky360'); ?></p>
                        </td>
                    </tr>
                    <tr>
                        <th scope="row" colspan="2">
                            <h4 style="margin: 20px 0 0 0;"><?php _e('Update Frequencies', 'sky360'); ?></h4>
                            <p class="description" style="font-weight: normal;"><?php _e('Tell search engines how often content typically changes', 'sky360'); ?></p>
                        </th>
                    </tr>
                    <tr>
                        <th scope="row"><?php _e('Areas Frequency', 'sky360'); ?></th>
                        <td>
                            <select name="sky_seo_settings[sitemap_frequency_areas]">
                                <option value="always" <?php selected($settings['sitemap_frequency_areas'] ?? 'daily', 'always'); ?>><?php _e('Always', 'sky360'); ?></option>
                                <option value="hourly" <?php selected($settings['sitemap_frequency_areas'] ?? 'daily', 'hourly'); ?>><?php _e('Hourly', 'sky360'); ?></option>
                                <option value="daily" <?php selected($settings['sitemap_frequency_areas'] ?? 'daily', 'daily'); ?>><?php _e('Daily', 'sky360'); ?></option>
                                <option value="weekly" <?php selected($settings['sitemap_frequency_areas'] ?? 'daily', 'weekly'); ?>><?php _e('Weekly', 'sky360'); ?></option>
                                <option value="monthly" <?php selected($settings['sitemap_frequency_areas'] ?? 'daily', 'monthly'); ?>><?php _e('Monthly', 'sky360'); ?></option>
                                <option value="yearly" <?php selected($settings['sitemap_frequency_areas'] ?? 'daily', 'yearly'); ?>><?php _e('Yearly', 'sky360'); ?></option>
                                <option value="never" <?php selected($settings['sitemap_frequency_areas'] ?? 'daily', 'never'); ?>><?php _e('Never', 'sky360'); ?></option>
                            </select>
                        </td>
                    </tr>
                    <tr>
                        <th scope="row"><?php _e('Trending Frequency', 'sky360'); ?></th>
                        <td>
                            <select name="sky_seo_settings[sitemap_frequency_trending]">
                                <option value="always" <?php selected($settings['sitemap_frequency_trending'] ?? 'daily', 'always'); ?>><?php _e('Always', 'sky360'); ?></option>
                                <option value="hourly" <?php selected($settings['sitemap_frequency_trending'] ?? 'daily', 'hourly'); ?>><?php _e('Hourly', 'sky360'); ?></option>
                                <option value="daily" <?php selected($settings['sitemap_frequency_trending'] ?? 'daily', 'daily'); ?>><?php _e('Daily', 'sky360'); ?></option>
                                <option value="weekly" <?php selected($settings['sitemap_frequency_trending'] ?? 'daily', 'weekly'); ?>><?php _e('Weekly', 'sky360'); ?></option>
                                <option value="monthly" <?php selected($settings['sitemap_frequency_trending'] ?? 'daily', 'monthly'); ?>><?php _e('Monthly', 'sky360'); ?></option>
                                <option value="yearly" <?php selected($settings['sitemap_frequency_trending'] ?? 'daily', 'yearly'); ?>><?php _e('Yearly', 'sky360'); ?></option>
                                <option value="never" <?php selected($settings['sitemap_frequency_trending'] ?? 'daily', 'never'); ?>><?php _e('Never', 'sky360'); ?></option>
                            </select>
                        </td>
                    </tr>
                    <tr>
                        <th scope="row"><?php _e('Sectors Frequency', 'sky360'); ?></th>
                        <td>
                            <select name="sky_seo_settings[sitemap_frequency_sectors]">
                                <option value="always" <?php selected($settings['sitemap_frequency_sectors'] ?? 'weekly', 'always'); ?>><?php _e('Always', 'sky360'); ?></option>
                                <option value="hourly" <?php selected($settings['sitemap_frequency_sectors'] ?? 'weekly', 'hourly'); ?>><?php _e('Hourly', 'sky360'); ?></option>
                                <option value="daily" <?php selected($settings['sitemap_frequency_sectors'] ?? 'weekly', 'daily'); ?>><?php _e('Daily', 'sky360'); ?></option>
                                <option value="weekly" <?php selected($settings['sitemap_frequency_sectors'] ?? 'weekly', 'weekly'); ?>><?php _e('Weekly', 'sky360'); ?></option>
                                <option value="monthly" <?php selected($settings['sitemap_frequency_sectors'] ?? 'weekly', 'monthly'); ?>><?php _e('Monthly', 'sky360'); ?></option>
                                <option value="yearly" <?php selected($settings['sitemap_frequency_sectors'] ?? 'weekly', 'yearly'); ?>><?php _e('Yearly', 'sky360'); ?></option>
                                <option value="never" <?php selected($settings['sitemap_frequency_sectors'] ?? 'weekly', 'never'); ?>><?php _e('Never', 'sky360'); ?></option>
                            </select>
                        </td>
                    </tr>
                </table>
            </div>
            
            <!-- Indexing Boost Settings -->
            <div class="sky-seo-settings-card">
                <h3><?php _e('Indexing Boost Features', 'sky360'); ?></h3>
                <table class="form-table">
                    <tr>
                        <th scope="row"><?php _e('Auto-Ping Search Engines', 'sky360'); ?></th>
                        <td>
                            <label>
                                <input type="checkbox" name="sky_seo_settings[auto_ping_search_engines]" value="1" 
                                       <?php checked($settings['auto_ping_search_engines'] ?? 1); ?> />
                                <?php _e('Automatically notify search engines when new content is published', 'sky360'); ?>
                            </label>
                            <p class="description"><?php _e('Pings Google and Bing when you publish new Areas, Trending, or Sectors posts.', 'sky360'); ?></p>
                        </td>
                    </tr>
                    <tr>
                        <th scope="row"><?php _e('RSS Feed Integration', 'sky360'); ?></th>
                        <td>
                            <label>
                                <input type="checkbox" name="sky_seo_settings[include_in_rss]" value="1" 
                                       <?php checked($settings['include_in_rss'] ?? 1); ?> />
                                <?php _e('Include custom post types in RSS feed', 'sky360'); ?>
                            </label>
                            <p class="description"><?php _e('Helps search engines discover new content through RSS feeds.', 'sky360'); ?></p>
                        </td>
                    </tr>
                    <tr>
                        <th scope="row"><?php _e('Force Indexing', 'sky360'); ?></th>
                        <td>
                            <label>
                                <input type="checkbox" name="sky_seo_settings[force_index_new_posts]" value="1" 
                                       <?php checked($settings['force_index_new_posts'] ?? 1); ?> />
                                <?php _e('Add enhanced meta tags to force indexing of new posts', 'sky360'); ?>
                            </label>
                            <p class="description"><?php _e('Adds special meta tags that encourage search engines to index content immediately.', 'sky360'); ?></p>
                        </td>
                    </tr>
                </table>
            </div>
            
        </div>
        
        <?php submit_button(); ?>
    </form>
    <?php
}

// SEO Plugin Integration Tab
function sky_seo_seo_integration_tab() {
    $settings = get_option('sky_seo_settings', [
        'active_seo_plugin' => 'none',
        'seo_plugin_settings' => []
    ]);
    ?>
    
    <form method="post" action="options.php" class="sky-seo-settings-form">
        <?php
        settings_fields('sky_seo_settings');
        do_settings_sections('sky_seo_settings');
        ?>
        
        <div class="sky-seo-settings-section">
            
            <!-- SEO Plugin Selection -->
            <div class="sky-seo-settings-card">
                <h3><?php esc_html_e('SEO Plugin Integration', 'sky360'); ?></h3>
                <table class="form-table">
                    <tr>
                        <th scope="row"><?php esc_html_e('Active SEO Plugin', 'sky360'); ?></th>
                        <td>
                            <select name="sky_seo_settings[active_seo_plugin]" id="active_seo_plugin">
                                <option value="none" <?php selected($settings['active_seo_plugin'] ?? 'none', 'none'); ?>><?php esc_html_e('None', 'sky360'); ?></option>
                                <option value="yoast" <?php selected($settings['active_seo_plugin'] ?? 'none', 'yoast'); ?>><?php esc_html_e('Yoast SEO', 'sky360'); ?></option>
                                <option value="rankmath" <?php selected($settings['active_seo_plugin'] ?? 'none', 'rankmath'); ?>><?php esc_html_e('RankMath', 'sky360'); ?></option>
                                <option value="seopress" <?php selected($settings['active_seo_plugin'] ?? 'none', 'seopress'); ?>><?php esc_html_e('SEOPress', 'sky360'); ?></option>
                                <option value="tsf" <?php selected($settings['active_seo_plugin'] ?? 'none', 'tsf'); ?>><?php esc_html_e('The SEO Framework', 'sky360'); ?></option>
                                <option value="aioseo" <?php selected($settings['active_seo_plugin'] ?? 'none', 'aioseo'); ?>><?php esc_html_e('All in One SEO', 'sky360'); ?></option>
                                <option value="squirrly" <?php selected($settings['active_seo_plugin'] ?? 'none', 'squirrly'); ?>><?php esc_html_e('Squirrly SEO', 'sky360'); ?></option>
                            </select>
                            <p class="description"><?php esc_html_e('Select your active SEO plugin to enable meta tag integration.', 'sky360'); ?></p>
                        </td>
                    </tr>
                </table>
                
                <!-- Plugin-specific settings will be shown via JavaScript -->
                <div id="seo-plugin-settings" style="margin-top: 20px;">
                    <!-- Dynamic content loaded here -->
                </div>
            </div>
            
            <!-- Status Card -->
            <div class="sky-seo-settings-card">
                <h3><?php esc_html_e('Integration Status', 'sky360'); ?></h3>
                <div id="seo-integration-status">
                    <?php sky_seo_display_integration_status(); ?>
                </div>
            </div>

            <!-- Sitemap Diagnostics Card -->
            <div class="sky-seo-settings-card">
                <h3><?php esc_html_e('Sitemap Diagnostics & Tools', 'sky360'); ?></h3>
                <div id="sitemap-diagnostics">
                    <?php sky_seo_display_sitemap_diagnostics(); ?>
                </div>

                <div style="margin-top: 20px; padding-top: 20px; border-top: 1px solid #ddd;">
                    <h4><?php esc_html_e('Quick Fixes', 'sky360'); ?></h4>
                    <p class="description"><?php esc_html_e('Try these fixes if your sitemaps are showing 404 errors:', 'sky360'); ?></p>

                    <div style="margin-top: 15px;">
                        <button type="button" id="sky-seo-flush-rewrite" class="button button-secondary">
                            <span class="dashicons dashicons-update" style="margin-top: 3px;"></span>
                            <?php esc_html_e('Flush Rewrite Rules', 'sky360'); ?>
                        </button>
                        <p class="description" style="margin-top: 5px;">
                            <?php esc_html_e('This refreshes WordPress permalinks and can fix 404 errors on sitemaps.', 'sky360'); ?>
                        </p>
                    </div>

                    <div style="margin-top: 15px;">
                        <button type="button" id="sky-seo-auto-detect-plugin" class="button button-secondary">
                            <span class="dashicons dashicons-search" style="margin-top: 3px;"></span>
                            <?php esc_html_e('Auto-Detect SEO Plugin', 'sky360'); ?>
                        </button>
                        <p class="description" style="margin-top: 5px;">
                            <?php esc_html_e('Automatically detect and configure your installed SEO plugin.', 'sky360'); ?>
                        </p>
                    </div>

                    <div style="margin-top: 15px;">
                        <button type="button" id="sky-seo-test-sitemaps" class="button button-secondary">
                            <span class="dashicons dashicons-admin-site" style="margin-top: 3px;"></span>
                            <?php esc_html_e('Test Sitemaps', 'sky360'); ?>
                        </button>
                        <p class="description" style="margin-top: 5px;">
                            <?php esc_html_e('Check if all your sitemap URLs are accessible (no 404 errors).', 'sky360'); ?>
                        </p>
                    </div>

                    <div id="sitemap-test-results" style="margin-top: 15px; display: none;">
                        <!-- Results will be displayed here -->
                    </div>
                </div>
            </div>

        </div>

        <?php submit_button(); ?>
    </form>
    <?php
}

// Duplicate Settings Tab
function sky_seo_duplicate_settings_tab() {
    $settings = get_option('sky_seo_settings', []);
    
    // Set defaults
    $defaults = [
        'duplicate_feature_enabled' => 0,
        'duplicate_post_types' => ['post', 'page'],
        'duplicate_status' => 'draft',
        'duplicate_redirect' => 0,
        'duplicate_prefix' => 'Copy of ',
        'duplicate_suffix' => '',
        'duplicate_author' => 'original',
        'duplicate_meta' => 1,
        'duplicate_taxonomies' => 1,
        'duplicate_comments' => 0,
        'duplicate_admin_bar' => 0,
    ];
    
    foreach ($defaults as $key => $default) {
        if (!isset($settings[$key])) {
            $settings[$key] = $default;
        }
    }
    ?>
    
    <form method="post" action="options.php" class="sky-seo-settings-form">
        <?php
        settings_fields('sky_seo_settings');
        do_settings_sections('sky_seo_settings');
        ?>
        
        <!-- Hidden field to identify duplicate form -->
        <input type="hidden" name="sky_seo_settings[_duplicate_form]" value="1" />
        
        <div class="sky-seo-settings-section">
            
            <!-- Feature Enable/Disable -->
            <div class="sky-seo-settings-card">
                <h3><?php _e('Duplicate Post Feature', 'sky360'); ?></h3>
                <table class="form-table">
                    <tr>
                        <th scope="row"><?php _e('Enable Feature', 'sky360'); ?></th>
                        <td>
                            <label>
                                <input type="checkbox" name="sky_seo_settings[duplicate_feature_enabled]" value="1" 
                                       <?php checked($settings['duplicate_feature_enabled']); ?> 
                                       id="duplicate_feature_enabled" />
                                <?php _e('Enable duplicate post functionality', 'sky360'); ?>
                            </label>
                            <p class="description"><?php _e('Turn on/off the entire duplicate post feature.', 'sky360'); ?></p>
                        </td>
                    </tr>
                </table>
            </div>
            
            <!-- Duplicate Settings (shown only when enabled) -->
            <div class="sky-seo-settings-card" id="duplicate-settings-container" style="<?php echo empty($settings['duplicate_feature_enabled']) ? 'display:none;' : ''; ?>">
                <h3><?php _e('Duplicate Settings', 'sky360'); ?></h3>
                <table class="form-table">
                    <tr>
                        <th scope="row"><?php _e('Post Types', 'sky360'); ?></th>
                        <td>
                            <fieldset>
                                <?php
                                $post_types = get_post_types(['public' => true], 'objects');
                                foreach ($post_types as $post_type) :
                                    if ($post_type->name === 'attachment') continue;
                                ?>
                                    <label style="display: block; margin-bottom: 5px;">
                                        <input type="checkbox" name="sky_seo_settings[duplicate_post_types][]" 
                                               value="<?php echo esc_attr($post_type->name); ?>"
                                               <?php checked(in_array($post_type->name, $settings['duplicate_post_types'])); ?> />
                                        <?php echo esc_html($post_type->labels->name); ?>
                                    </label>
                                <?php endforeach; ?>
                            </fieldset>
                            <p class="description"><?php _e('Select which post types can be duplicated.', 'sky360'); ?></p>
                        </td>
                    </tr>
                    <tr>
                        <th scope="row"><?php _e('Default Status', 'sky360'); ?></th>
                        <td>
                            <select name="sky_seo_settings[duplicate_status]">
                                <option value="draft" <?php selected($settings['duplicate_status'], 'draft'); ?>><?php _e('Draft', 'sky360'); ?></option>
                                <option value="pending" <?php selected($settings['duplicate_status'], 'pending'); ?>><?php _e('Pending', 'sky360'); ?></option>
                                <option value="private" <?php selected($settings['duplicate_status'], 'private'); ?>><?php _e('Private', 'sky360'); ?></option>
                            </select>
                            <p class="description"><?php _e('Status for duplicated posts.', 'sky360'); ?></p>
                        </td>
                    </tr>
                    <tr>
                        <th scope="row"><?php _e('After Duplication', 'sky360'); ?></th>
                        <td>
                            <label>
                                <input type="checkbox" name="sky_seo_settings[duplicate_redirect]" value="1" 
                                       <?php checked($settings['duplicate_redirect']); ?> />
                                <?php _e('Redirect to edit screen', 'sky360'); ?>
                            </label>
                            <p class="description"><?php _e('Automatically open the duplicated post for editing.', 'sky360'); ?></p>
                        </td>
                    </tr>
                    <tr>
                        <th scope="row"><?php _e('Title Prefix', 'sky360'); ?></th>
                        <td>
                            <input type="text" name="sky_seo_settings[duplicate_prefix]" 
                                   value="<?php echo esc_attr($settings['duplicate_prefix']); ?>" 
                                   class="regular-text" />
                            <p class="description"><?php _e('Text to add before the title of duplicated posts.', 'sky360'); ?></p>
                        </td>
                    </tr>
                    <tr>
                        <th scope="row"><?php _e('Title Suffix', 'sky360'); ?></th>
                        <td>
                            <input type="text" name="sky_seo_settings[duplicate_suffix]" 
                                   value="<?php echo esc_attr($settings['duplicate_suffix']); ?>" 
                                   class="regular-text" />
                            <p class="description"><?php _e('Text to add after the title of duplicated posts.', 'sky360'); ?></p>
                        </td>
                    </tr>
                    <tr>
                        <th scope="row"><?php _e('Post Author', 'sky360'); ?></th>
                        <td>
                            <select name="sky_seo_settings[duplicate_author]">
                                <option value="original" <?php selected($settings['duplicate_author'] ?? 'original', 'original'); ?>><?php _e('Keep original author', 'sky360'); ?></option>
                                <option value="current" <?php selected($settings['duplicate_author'] ?? 'original', 'current'); ?>><?php _e('Current user', 'sky360'); ?></option>
                            </select>
                        </td>
                    </tr>
                    <tr>
                        <th scope="row"><?php _e('Duplicate Options', 'sky360'); ?></th>
                        <td>
                            <fieldset>
                                <label style="display: block; margin-bottom: 5px;">
                                    <input type="checkbox" name="sky_seo_settings[duplicate_meta]" value="1" 
                                           <?php checked($settings['duplicate_meta'] ?? 1); ?> />
                                    <?php _e('Copy custom fields (post meta)', 'sky360'); ?>
                                </label>
                                <label style="display: block; margin-bottom: 5px;">
                                    <input type="checkbox" name="sky_seo_settings[duplicate_taxonomies]" value="1"
                                           <?php checked($settings['duplicate_taxonomies'] ?? 1); ?> />
                                    <?php _e('Copy categories, tags, and custom taxonomies', 'sky360'); ?>
                                </label>
                                <label style="display: block; margin-bottom: 5px;">
                                    <input type="checkbox" name="sky_seo_settings[duplicate_comments]" value="1"
                                           <?php checked($settings['duplicate_comments'] ?? 0); ?> />
                                    <?php _e('Copy comments', 'sky360'); ?>
                                </label>
                                <label style="display: block;">
                                    <input type="checkbox" name="sky_seo_settings[duplicate_admin_bar]" value="1" 
                                           <?php checked($settings['duplicate_admin_bar'] ?? 0); ?> />
                                    <?php _e('Show duplicate link in admin bar', 'sky360'); ?>
                                </label>
                            </fieldset>
                        </td>
                    </tr>
                </table>
            </div>
            
        </div>
        
        <?php submit_button(); ?>
    </form>
    
    <script type="text/javascript">
    jQuery(document).ready(function($) {
        $('#duplicate_feature_enabled').on('change', function() {
            if ($(this).is(':checked')) {
                $('#duplicate-settings-container').slideDown();
            } else {
                $('#duplicate-settings-container').slideUp();
            }
        });
    });
    </script>
    <?php
}

// Display SEO Integration Status
function sky_seo_display_integration_status() {
    $settings = get_option('sky_seo_settings', []);

    // Validate plugin key against whitelist
    $valid_plugins = ['none', 'yoast', 'rankmath', 'seopress', 'tsf', 'aioseo', 'squirrly'];
    $active_plugin = isset($settings['active_seo_plugin']) && in_array($settings['active_seo_plugin'], $valid_plugins, true)
        ? $settings['active_seo_plugin']
        : 'none';

    if ($active_plugin === 'none') {
        echo '<p>' . esc_html__('No SEO plugin integration active.', 'sky360') . '</p>';
        return;
    }
    
    $plugin_status = sky_seo_check_plugin_status($active_plugin);
    
    if ($plugin_status['installed'] && $plugin_status['active']) {
        echo '<div class="notice notice-success inline"><p>';
        echo sprintf(
            esc_html__('%s is installed and active. Meta tags will be handled by %s.', 'sky360'),
            esc_html($plugin_status['name']),
            esc_html($plugin_status['name'])
        );
        echo '</p></div>';
    } elseif ($plugin_status['installed']) {
        echo '<div class="notice notice-warning inline"><p>';
        echo sprintf(
            esc_html__('%s is installed but not active. Please activate it to enable integration.', 'sky360'),
            esc_html($plugin_status['name'])
        );
        echo '</p></div>';
    } else {
        echo '<div class="notice notice-error inline"><p>';
        echo sprintf(
            esc_html__('%s is not installed. Please install and activate it to enable integration.', 'sky360'),
            esc_html($plugin_status['name'])
        );
        echo '</p></div>';
    }
}

// Check plugin status helper
function sky_seo_check_plugin_status($plugin_key) {
    $plugins = [
        'yoast' => [
            'name' => 'Yoast SEO',
            'file' => 'wordpress-seo/wp-seo.php'
        ],
        'rankmath' => [
            'name' => 'RankMath',
            'file' => 'seo-by-rank-math/rank-math.php'
        ],
        'seopress' => [
            'name' => 'SEOPress',
            'file' => 'wp-seopress/seopress.php'
        ],
        'tsf' => [
            'name' => 'The SEO Framework',
            'file' => 'autodescription/autodescription.php'
        ],
        'aioseo' => [
            'name' => 'All in One SEO',
            'file' => 'all-in-one-seo-pack/all_in_one_seo_pack.php'
        ],
        'squirrly' => [
            'name' => 'Squirrly SEO',
            'file' => 'squirrly-seo/squirrly.php'
        ]
    ];
    
    if (!isset($plugins[$plugin_key])) {
        return ['installed' => false, 'active' => false, 'name' => 'Unknown'];
    }
    
    $plugin_info = $plugins[$plugin_key];
    $installed = file_exists(WP_PLUGIN_DIR . '/' . $plugin_info['file']);
    $active = is_plugin_active($plugin_info['file']);
    
    return [
        'installed' => $installed,
        'active' => $active,
        'name' => $plugin_info['name']
    ];
}

// Display Sitemap Diagnostics
function sky_seo_display_sitemap_diagnostics() {
    $settings = get_option('sky_seo_settings', []);
    $active_plugin = $settings['active_seo_plugin'] ?? 'none';

    // Auto-detect installed SEO plugins
    $detected_plugins = [];
    $seo_plugins = [
        'yoast' => ['file' => 'wordpress-seo/wp-seo.php', 'name' => 'Yoast SEO', 'constant' => 'WPSEO_VERSION'],
        'rankmath' => ['file' => 'seo-by-rank-math/rank-math.php', 'name' => 'Rank Math', 'constant' => 'RANK_MATH_VERSION'],
        'seopress' => ['file' => 'wp-seopress/seopress.php', 'name' => 'SEOPress', 'constant' => 'SEOPRESS_VERSION'],
        'aioseo' => ['file' => 'all-in-one-seo-pack/all_in_one_seo_pack.php', 'name' => 'All in One SEO', 'constant' => 'AIOSEO_VERSION'],
    ];

    foreach ($seo_plugins as $key => $plugin) {
        if (is_plugin_active($plugin['file']) && defined($plugin['constant'])) {
            $detected_plugins[$key] = $plugin['name'];
        }
    }

    ?>
    <div style="background: #f9f9f9; padding: 15px; border-radius: 5px; margin-bottom: 15px;">
        <h4 style="margin-top: 0;"><?php esc_html_e('SEO Plugin Detection', 'sky360'); ?></h4>

        <?php if (empty($detected_plugins)): ?>
            <div class="notice notice-warning inline" style="margin: 10px 0;">
                <p><strong><?php esc_html_e('No SEO plugin detected!', 'sky360'); ?></strong></p>
                <p><?php esc_html_e('No compatible SEO plugin is currently active. Please install and activate Rank Math, Yoast SEO, or another supported SEO plugin.', 'sky360'); ?></p>
            </div>
        <?php else: ?>
            <div class="notice notice-success inline" style="margin: 10px 0;">
                <p><strong><?php esc_html_e('Detected SEO Plugins:', 'sky360'); ?></strong></p>
                <ul style="margin-left: 20px;">
                    <?php foreach ($detected_plugins as $key => $name): ?>
                        <li>
                            <?php echo esc_html($name); ?>
                            <?php if ($active_plugin === $key): ?>
                                <span class="dashicons dashicons-yes-alt" style="color: #46b450;"></span>
                                <em><?php esc_html_e('(Currently selected)', 'sky360'); ?></em>
                            <?php else: ?>
                                <span class="dashicons dashicons-warning" style="color: #ffb900;"></span>
                                <em><?php esc_html_e('(Not selected in settings above)', 'sky360'); ?></em>
                            <?php endif; ?>
                        </li>
                    <?php endforeach; ?>
                </ul>
            </div>
        <?php endif; ?>

        <h4><?php esc_html_e('Sitemap URLs', 'sky360'); ?></h4>
        <p class="description"><?php esc_html_e('These are the common sitemap URLs used by SEO plugins. Click to test if they are accessible:', 'sky360'); ?></p>

        <table class="widefat" style="margin-top: 10px;">
            <thead>
                <tr>
                    <th><?php esc_html_e('Sitemap Type', 'sky360'); ?></th>
                    <th><?php esc_html_e('URL', 'sky360'); ?></th>
                    <th><?php esc_html_e('Action', 'sky360'); ?></th>
                </tr>
            </thead>
            <tbody>
                <?php
                $sitemaps = [
                    'Main Sitemap Index' => home_url('/sitemap_index.xml'),
                    'WordPress Core Sitemap' => home_url('/wp-sitemap.xml'),
                    'Posts Sitemap' => home_url('/post-sitemap.xml'),
                    'Pages Sitemap' => home_url('/page-sitemap.xml'),
                ];

                foreach ($sitemaps as $label => $url):
                ?>
                    <tr>
                        <td><strong><?php echo esc_html($label); ?></strong></td>
                        <td><code><?php echo esc_html($url); ?></code></td>
                        <td>
                            <a href="<?php echo esc_url($url); ?>" target="_blank" class="button button-small">
                                <?php esc_html_e('Open', 'sky360'); ?>
                            </a>
                        </td>
                    </tr>
                <?php endforeach; ?>
            </tbody>
        </table>

        <?php if ($active_plugin === 'none' && !empty($detected_plugins)): ?>
            <div class="notice notice-warning inline" style="margin-top: 15px;">
                <p>
                    <strong><?php esc_html_e('Action Required:', 'sky360'); ?></strong>
                    <?php esc_html_e('You have SEO plugin(s) installed but none selected above. Please select your SEO plugin from the dropdown above and save changes.', 'sky360'); ?>
                </p>
            </div>
        <?php endif; ?>

        <h4 style="margin-top: 20px;"><?php esc_html_e('Custom Post Types in Sitemap', 'sky360'); ?></h4>
        <p class="description"><?php esc_html_e('These custom post types should be included in your sitemap:', 'sky360'); ?></p>

        <ul style="margin-left: 20px;">
            <li><code>sky_areas</code> - <?php esc_html_e('Areas post type', 'sky360'); ?></li>
            <li><code>sky_trending</code> - <?php esc_html_e('Trending Insights post type', 'sky360'); ?></li>
            <li><code>sky_sectors</code> - <?php esc_html_e('Sectors post type', 'sky360'); ?></li>
        </ul>

        <?php if ($active_plugin !== 'none'): ?>
            <div class="notice notice-info inline" style="margin-top: 10px;">
                <p>
                    <?php printf(
                        __('Your custom post types are automatically registered with %s. If you\'re seeing 404 errors, try the "Flush Rewrite Rules" button below.', 'sky360'),
                        '<strong>' . esc_html($detected_plugins[$active_plugin] ?? $active_plugin) . '</strong>'
                    ); ?>
                </p>
            </div>
        <?php endif; ?>
    </div>
    <?php
}

// AJAX Handler: Flush Rewrite Rules
add_action('wp_ajax_sky_seo_flush_rewrite_rules', 'sky_seo_ajax_flush_rewrite_rules');
function sky_seo_ajax_flush_rewrite_rules() {
    // Verify nonce
    check_ajax_referer('sky_seo_settings_nonce', 'nonce');

    // Check permissions
    if (!current_user_can('manage_options')) {
        wp_send_json_error(['message' => 'Permission denied.']);
        return;
    }

    // Flush rewrite rules
    flush_rewrite_rules();

    // Also flush Rank Math cache if active
    if (defined('RANK_MATH_VERSION')) {
        delete_transient('rank_math_sitemap_cache');
    }

    // Flush Yoast cache if active
    if (defined('WPSEO_VERSION')) {
        delete_transient('wpseo_sitemap_cache');
    }

    wp_send_json_success([
        'message' => 'Rewrite rules flushed successfully! Your sitemaps should now work correctly. Try accessing your sitemap URLs again.'
    ]);
}

// AJAX Handler: Auto-Detect SEO Plugin
add_action('wp_ajax_sky_seo_auto_detect_plugin', 'sky_seo_ajax_auto_detect_plugin');
function sky_seo_ajax_auto_detect_plugin() {
    // Verify nonce
    check_ajax_referer('sky_seo_settings_nonce', 'nonce');

    // Check permissions
    if (!current_user_can('manage_options')) {
        wp_send_json_error(['message' => 'Permission denied.']);
        return;
    }

    // Check for installed and active SEO plugins
    $seo_plugins = [
        'rankmath' => ['file' => 'seo-by-rank-math/rank-math.php', 'name' => 'Rank Math', 'constant' => 'RANK_MATH_VERSION'],
        'yoast' => ['file' => 'wordpress-seo/wp-seo.php', 'name' => 'Yoast SEO', 'constant' => 'WPSEO_VERSION'],
        'aioseo' => ['file' => 'all-in-one-seo-pack/all_in_one_seo_pack.php', 'name' => 'All in One SEO', 'constant' => 'AIOSEO_VERSION'],
        'seopress' => ['file' => 'wp-seopress/seopress.php', 'name' => 'SEOPress', 'constant' => 'SEOPRESS_VERSION'],
    ];

    $detected_plugin = 'none';
    $plugin_name = '';

    foreach ($seo_plugins as $key => $plugin) {
        if (is_plugin_active($plugin['file']) && defined($plugin['constant'])) {
            $detected_plugin = $key;
            $plugin_name = $plugin['name'];
            break; // Use first detected plugin
        }
    }

    if ($detected_plugin === 'none') {
        wp_send_json_error([
            'message' => 'No supported SEO plugin detected. Please install and activate Rank Math, Yoast SEO, or another supported plugin.'
        ]);
        return;
    }

    // Save detected plugin to settings
    $settings = get_option('sky_seo_settings', []);
    $settings['active_seo_plugin'] = $detected_plugin;
    update_option('sky_seo_settings', $settings);

    // Flush rewrite rules after changing SEO plugin
    flush_rewrite_rules();

    wp_send_json_success([
        'plugin' => $detected_plugin,
        'message' => sprintf('Detected and configured %s! The settings have been saved automatically. Remember to click "Save Changes" above to ensure all settings are saved.', $plugin_name)
    ]);
}

// AJAX Handler: Test Sitemaps
add_action('wp_ajax_sky_seo_test_sitemaps', 'sky_seo_ajax_test_sitemaps');
function sky_seo_ajax_test_sitemaps() {
    // Verify nonce
    check_ajax_referer('sky_seo_settings_nonce', 'nonce');

    // Check permissions
    if (!current_user_can('manage_options')) {
        wp_send_json_error(['message' => 'Permission denied.']);
        return;
    }

    // Get current SEO plugin
    $settings = get_option('sky_seo_settings', []);
    $active_plugin = $settings['active_seo_plugin'] ?? 'none';

    // Define sitemap URLs to test based on active plugin
    $sitemaps_to_test = [
        home_url('/sitemap_index.xml'),
        home_url('/wp-sitemap.xml'),
    ];

    // Add plugin-specific sitemaps
    if ($active_plugin === 'rankmath') {
        $sitemaps_to_test[] = home_url('/sitemap_index.xml');
        $sitemaps_to_test[] = home_url('/post-sitemap.xml');
        $sitemaps_to_test[] = home_url('/page-sitemap.xml');
    } elseif ($active_plugin === 'yoast') {
        $sitemaps_to_test[] = home_url('/sitemap_index.xml');
        $sitemaps_to_test[] = home_url('/post-sitemap.xml');
    }

    // Remove duplicates
    $sitemaps_to_test = array_unique($sitemaps_to_test);

    $results = [];

    foreach ($sitemaps_to_test as $url) {
        $response = wp_remote_get($url, [
            'timeout' => 10,
            'sslverify' => true // Enable SSL verification for security
        ]);

        if (is_wp_error($response)) {
            $results[] = [
                'url' => $url,
                'status' => 0,
                'message' => 'Error: ' . $response->get_error_message()
            ];
        } else {
            $status_code = wp_remote_retrieve_response_code($response);
            $body = wp_remote_retrieve_body($response);

            // Check if response looks like XML
            $is_xml = (strpos($body, '<?xml') === 0 || strpos($body, '<urlset') !== false || strpos($body, '<sitemapindex') !== false);

            if ($status_code === 200 && $is_xml) {
                // Count URLs in sitemap
                $url_count = substr_count($body, '<url>') + substr_count($body, '<sitemap>');
                $results[] = [
                    'url' => $url,
                    'status' => 200,
                    'message' => sprintf('Valid XML sitemap with %d entries', $url_count)
                ];
            } elseif ($status_code === 200) {
                $results[] = [
                    'url' => $url,
                    'status' => 200,
                    'message' => 'Accessible but not valid XML (might be HTML or redirect)'
                ];
            } else {
                $results[] = [
                    'url' => $url,
                    'status' => $status_code,
                    'message' => sprintf('HTTP %d - %s', $status_code, wp_remote_retrieve_response_message($response))
                ];
            }
        }
    }

    wp_send_json_success([
        'results' => $results,
        'active_plugin' => $active_plugin
    ]);
}