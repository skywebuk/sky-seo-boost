<?php
/**
 * Sky SEO Boost - Google Ads Dashboard Admin Interface
 * Clean, modern design matching UTM dashboard style
 *
 * @package Sky_SEO_Boost
 * @version 3.4.3
 * @since 3.4.3
 */

// Prevent direct access
if (!defined('ABSPATH')) {
    exit;
}

/**
 * Render the Google Ads admin page
 */
function sky_seo_render_google_ads_page() {
    // Check permissions
    if (!current_user_can('manage_options')) {
        wp_die(__('You do not have sufficient permissions to access this page.', 'sky-seo-boost'));
    }

    // Enqueue styles and scripts
    wp_enqueue_style(
        'sky-google-ads-dashboard',
        plugin_dir_url(__FILE__) . 'google-ads.css',
        [],
        '3.4.3'
    );

    wp_enqueue_script(
        'sky-google-ads-dashboard',
        plugin_dir_url(__FILE__) . 'google-ads-dashboard.js',
        ['jquery'],
        '3.4.3',
        true
    );

    // Get settings
    $settings = get_option('sky_seo_settings', []);
    $google_ads_enabled = isset($settings['google_ads_enabled']) ? $settings['google_ads_enabled'] : false;
    $conversion_type = isset($settings['google_ads_conversion_type']) ? $settings['google_ads_conversion_type'] : 'woocommerce';

    // Handle settings save
    if (isset($_POST['sky_seo_google_ads_settings_nonce']) &&
        wp_verify_nonce($_POST['sky_seo_google_ads_settings_nonce'], 'sky_seo_google_ads_settings')) {

        $settings['google_ads_enabled'] = isset($_POST['google_ads_enabled']);
        $settings['google_ads_conversion_type'] = sanitize_text_field($_POST['google_ads_conversion_type']);

        update_option('sky_seo_settings', $settings);

        // Use WordPress standard admin notices
        add_settings_error(
            'sky_seo_google_ads_settings',
            'settings_saved',
            __('Settings saved successfully.', 'sky-seo-boost'),
            'success'
        );
        settings_errors('sky_seo_google_ads_settings');

        // Refresh values
        $google_ads_enabled = $settings['google_ads_enabled'];
        $conversion_type = $settings['google_ads_conversion_type'];
    }

    // Localize script
    wp_localize_script('sky-google-ads-dashboard', 'skyGoogleAds', [
        'ajaxurl' => admin_url('admin-ajax.php'),
        'nonce' => wp_create_nonce('sky_seo_google_ads_dashboard'),
        'conversionType' => $conversion_type,
        'currency' => function_exists('get_woocommerce_currency_symbol') ? get_woocommerce_currency_symbol() : '$'
    ]);

    // Get current tab
    $active_tab = isset($_GET['tab']) ? sanitize_text_field($_GET['tab']) : 'dashboard';

    ?>
    <div class="wrap">
        <h1 style="display: none;">Google Ads</h1>

        <div class="sky-google-ads-dashboard">
            <!-- Header -->
            <div class="sky-gads-header">
                <h2><span class="dashicons dashicons-google" style="color: #4285f4;"></span> Google Ads Tracking & Analytics</h2>
                <p>Track and analyze your Google Ads campaigns, conversions, and ROI</p>

                <!-- Navigation Tabs -->
                <div style="margin-top: 20px; border-bottom: 2px solid #e5e5e7; display: flex; gap: 24px;">
                    <a href="?page=sky-seo-google-ads&tab=dashboard"
                       style="padding: 12px 0; font-weight: 600; text-decoration: none; color: <?php echo $active_tab === 'dashboard' ? '#007aff' : '#86868b'; ?>; border-bottom: 2px solid <?php echo $active_tab === 'dashboard' ? '#007aff' : 'transparent'; ?>; margin-bottom: -2px;">
                        <span class="dashicons dashicons-dashboard"></span> Dashboard
                    </a>
                    <a href="?page=sky-seo-google-ads&tab=settings"
                       style="padding: 12px 0; font-weight: 600; text-decoration: none; color: <?php echo $active_tab === 'settings' ? '#007aff' : '#86868b'; ?>; border-bottom: 2px solid <?php echo $active_tab === 'settings' ? '#007aff' : 'transparent'; ?>; margin-bottom: -2px;">
                        <span class="dashicons dashicons-admin-settings"></span> Settings
                    </a>
                </div>
            </div>

            <?php if ($active_tab === 'dashboard'): ?>
                <?php sky_seo_render_google_ads_dashboard($google_ads_enabled, $conversion_type); ?>
            <?php elseif ($active_tab === 'settings'): ?>
                <?php sky_seo_render_google_ads_settings($google_ads_enabled, $conversion_type); ?>
            <?php endif; ?>
        </div>
    </div>
    <?php
}

/**
 * Render dashboard tab
 */
function sky_seo_render_google_ads_dashboard($enabled, $conversion_type) {
    if (!$enabled) {
        ?>
        <div class="sky-gads-notice">
            <h3><?php _e('Google Ads Tracking is Disabled', 'sky-seo-boost'); ?></h3>
            <p><?php _e('Enable Google Ads tracking in the settings tab to start collecting data.', 'sky-seo-boost'); ?></p>
            <a href="?page=sky-seo-google-ads&tab=settings" class="sky-gads-button">
                <?php _e('Go to Settings', 'sky-seo-boost'); ?>
            </a>
        </div>
        <?php
        return;
    }

    ?>
    <!-- Date Range Controls -->
    <div class="sky-gads-header">
        <div class="sky-gads-date-controls">
            <label for="sky-gads-date-range"><?php _e('Date Range:', 'sky-seo-boost'); ?></label>
            <select id="sky-gads-date-range" name="date_range">
                <option value="last7days"><?php _e('Last 7 days', 'sky-seo-boost'); ?></option>
                <option value="last30days" selected><?php _e('Last 30 days', 'sky-seo-boost'); ?></option>
                <option value="last60days"><?php _e('Last 60 days', 'sky-seo-boost'); ?></option>
                <option value="last90days"><?php _e('Last 90 days', 'sky-seo-boost'); ?></option>
                <option value="custom"><?php _e('Custom Range', 'sky-seo-boost'); ?></option>
            </select>

            <div class="sky-gads-custom-dates" style="display: none; flex: 1; display: none;">
                <label for="sky-gads-date-from" style="margin-right: 8px;"><?php _e('From:', 'sky-seo-boost'); ?></label>
                <input type="date" id="sky-gads-date-from" name="date_from" />

                <label for="sky-gads-date-to" style="margin-left: 16px; margin-right: 8px;"><?php _e('To:', 'sky-seo-boost'); ?></label>
                <input type="date" id="sky-gads-date-to" name="date_to" />

                <button type="button" id="sky-gads-date-apply" class="sky-gads-date-apply">
                    <?php _e('Apply', 'sky-seo-boost'); ?>
                </button>
            </div>

            <button type="button" id="sky-gads-refresh" class="sky-gads-button secondary" style="margin-left: auto;">
                <span class="dashicons dashicons-update"></span>
                <?php _e('Refresh', 'sky-seo-boost'); ?>
            </button>
        </div>
    </div>

    <!-- Dashboard Content -->
    <div id="sky-gads-dashboard-content">
        <div class="sky-gads-loading">
            <span class="spinner is-active"></span>
            <p><?php _e('Loading analytics data...', 'sky-seo-boost'); ?></p>
        </div>
    </div>
    <?php
}

/**
 * Render settings tab
 */
function sky_seo_render_google_ads_settings($enabled, $conversion_type) {
    ?>
    <form method="post" action="">
        <?php wp_nonce_field('sky_seo_google_ads_settings', 'sky_seo_google_ads_settings_nonce'); ?>

        <div class="sky-gads-settings-panel">
            <h3><?php _e('Tracking Configuration', 'sky-seo-boost'); ?></h3>

            <div class="sky-gads-form-group">
                <label>
                    <input type="checkbox" name="google_ads_enabled" value="1" <?php checked($enabled); ?>>
                    <?php _e('Enable Google Ads Tracking', 'sky-seo-boost'); ?>
                </label>
                <p class="sky-gads-form-help">
                    <?php _e('Track visitors from Google Ads campaigns and measure conversions.', 'sky-seo-boost'); ?>
                </p>
            </div>

            <div class="sky-gads-form-group">
                <label for="google_ads_conversion_type">
                    <?php _e('Conversion Type', 'sky-seo-boost'); ?>
                </label>
                <select name="google_ads_conversion_type" id="google_ads_conversion_type">
                    <option value="woocommerce" <?php selected($conversion_type, 'woocommerce'); ?>>
                        <?php _e('WooCommerce Orders', 'sky-seo-boost'); ?>
                    </option>
                    <option value="form_submission" <?php selected($conversion_type, 'form_submission'); ?>>
                        <?php _e('Form Submissions', 'sky-seo-boost'); ?>
                    </option>
                </select>
                <p class="sky-gads-form-help">
                    <?php _e('Choose what type of conversion to track from Google Ads visitors.', 'sky-seo-boost'); ?>
                </p>
            </div>
        </div>

        <div class="sky-gads-info-box">
            <p>
                <strong><?php _e('How it works:', 'sky-seo-boost'); ?></strong><br>
                <?php _e('This system automatically detects visitors from Google Ads (via GCLID parameter or Google UTM parameters) and tracks their conversions. The data is stored securely with GDPR-compliant IP anonymization.', 'sky-seo-boost'); ?>
            </p>
        </div>

        <p style="margin-top: 20px;">
            <?php submit_button(__('Save Settings', 'sky-seo-boost'), 'sky-gads-button', 'submit', false); ?>
        </p>
    </form>
    <?php
}

/**
 * AJAX handler for loading dashboard data
 */
add_action('wp_ajax_sky_seo_load_google_ads_dashboard', 'sky_seo_ajax_load_google_ads_dashboard');

function sky_seo_ajax_load_google_ads_dashboard() {
    // Verify nonce
    if (!check_ajax_referer('sky_seo_google_ads_dashboard', 'nonce', false)) {
        wp_send_json_error(['message' => __('Invalid nonce', 'sky-seo-boost')]);
        return;
    }

    // Check permissions
    if (!current_user_can('manage_options')) {
        wp_send_json_error(['message' => __('Insufficient permissions', 'sky-seo-boost')]);
        return;
    }

    global $wpdb;
    $table_name = $wpdb->prefix . 'sky_seo_google_ads_conversions';

    // Get parameters
    $date_range = isset($_POST['date_range']) ? sanitize_text_field($_POST['date_range']) : 'last30days';
    $conversion_type = isset($_POST['conversion_type']) ? sanitize_text_field($_POST['conversion_type']) : 'woocommerce';

    // Calculate date range
    if ($date_range === 'custom') {
        $date_from = isset($_POST['date_from']) ? sanitize_text_field($_POST['date_from']) . ' 00:00:00' : '';
        $date_to = isset($_POST['date_to']) ? sanitize_text_field($_POST['date_to']) . ' 23:59:59' : '';

        if (empty($date_from) || empty($date_to)) {
            wp_send_json_error(['message' => __('Invalid custom date range', 'sky-seo-boost')]);
            return;
        }
        $start_date = $date_from;
    } else {
        // Parse preset ranges
        $days = 30;
        if ($date_range === 'last7days') $days = 7;
        elseif ($date_range === 'last60days') $days = 60;
        elseif ($date_range === 'last90days') $days = 90;

        $start_date = date('Y-m-d H:i:s', strtotime("-{$days} days"));
    }

    // Check if table exists
    $table_exists = $wpdb->get_var($wpdb->prepare(
        "SHOW TABLES LIKE %s",
        $table_name
    )) === $table_name;

    if (!$table_exists) {
        wp_send_json_error(['message' => __('Google Ads tracking table does not exist.', 'sky-seo-boost')]);
        return;
    }

    // Get statistics
    $stats = sky_seo_get_google_ads_stats($table_name, $start_date, $conversion_type);

    wp_send_json_success(['stats' => $stats]);
}

/**
 * Get Google Ads statistics
 */
function sky_seo_get_google_ads_stats($table_name, $start_date, $conversion_type) {
    global $wpdb;

    // Total clicks (sessions)
    $total_clicks = $wpdb->get_var($wpdb->prepare(
        "SELECT COUNT(*) FROM {$table_name} WHERE created_at >= %s",
        $start_date
    ));

    // Total conversions
    $total_conversions = $wpdb->get_var($wpdb->prepare(
        "SELECT COUNT(*) FROM {$table_name} WHERE created_at >= %s AND converted_at IS NOT NULL",
        $start_date
    ));

    // Conversion rate
    $conversion_rate = $total_clicks > 0 ? ($total_conversions / $total_clicks) * 100 : 0;

    // Get revenue and orders (if WooCommerce)
    $revenue_data = null;
    if ($conversion_type === 'woocommerce') {
        $revenue_data = $wpdb->get_row($wpdb->prepare(
            "SELECT
                COUNT(*) as total_orders,
                SUM(conversion_value) as total_revenue,
                AVG(conversion_value) as avg_order_value
            FROM {$table_name}
            WHERE created_at >= %s
                AND conversion_type = 'woocommerce'
                AND converted_at IS NOT NULL",
            $start_date
        ));
    }

    // Top campaigns
    $top_campaigns = $wpdb->get_results($wpdb->prepare(
        "SELECT
            utm_campaign,
            COUNT(*) as clicks,
            SUM(CASE WHEN converted_at IS NOT NULL THEN 1 ELSE 0 END) as conversions,
            SUM(CASE WHEN converted_at IS NOT NULL THEN conversion_value ELSE 0 END) as revenue
        FROM {$table_name}
        WHERE created_at >= %s
            AND utm_campaign IS NOT NULL
            AND utm_campaign != ''
        GROUP BY utm_campaign
        ORDER BY revenue DESC, conversions DESC
        LIMIT 10",
        $start_date
    ));

    // Top landing pages
    $top_pages = $wpdb->get_results($wpdb->prepare(
        "SELECT
            landing_page,
            COUNT(*) as clicks,
            SUM(CASE WHEN converted_at IS NOT NULL THEN 1 ELSE 0 END) as conversions
        FROM {$table_name}
        WHERE created_at >= %s
            AND landing_page IS NOT NULL
            AND landing_page != ''
        GROUP BY landing_page
        ORDER BY clicks DESC
        LIMIT 10",
        $start_date
    ));

    // Country analytics (from IP address - basic implementation)
    $country_data = $wpdb->get_results($wpdb->prepare(
        "SELECT
            SUBSTRING_INDEX(ip_address, '.', 2) as ip_prefix,
            COUNT(*) as clicks,
            SUM(CASE WHEN converted_at IS NOT NULL THEN 1 ELSE 0 END) as conversions
        FROM {$table_name}
        WHERE created_at >= %s
            AND ip_address IS NOT NULL
            AND ip_address != '0.0.0.0'
        GROUP BY ip_prefix
        ORDER BY clicks DESC
        LIMIT 10",
        $start_date
    ));

    return [
        'total_clicks' => $total_clicks,
        'total_conversions' => $total_conversions,
        'conversion_rate' => $conversion_rate,
        'revenue_data' => $revenue_data,
        'top_campaigns' => $top_campaigns,
        'top_pages' => $top_pages,
        'country_data' => $country_data
    ];
}
