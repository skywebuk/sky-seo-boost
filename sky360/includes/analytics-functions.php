<?php
// Prevent direct access
if (!defined('ABSPATH')) {
    exit;
}

// Initialize Analytics
function sky_seo_analytics_init() {
    // Handle CSV export
    // This check also validates the nonce for the export action
    if (isset($_GET['action']) && $_GET['action'] === 'export_analytics') {
        // Ensure only authenticated requests can trigger export
        // The nonce check should be placed after the action check for clearer logic
        if (check_admin_referer('sky_seo_export_analytics')) {
            sky_seo_export_analytics_csv();
        } else {
            wp_die(__('Security check failed. Please try again.', 'sky360'));
        }
    }

    // Handle AJAX requests
    add_action('wp_ajax_sky_seo_get_analytics_data', 'sky_seo_ajax_get_analytics_data');
    add_action('wp_ajax_sky_seo_get_post_details', 'sky_seo_ajax_get_post_details');
    add_action('wp_ajax_sky_seo_load_more_content', 'sky_seo_ajax_load_more_content');
}

// Enhanced Analytics Tab with Human/Bot Traffic Separation
function sky_seo_analytics_tab() {
    // Get all trackable post types
    $tracked_post_types = sky_seo_get_tracked_post_types();
    $selected_post_type = isset($_GET['post_type_filter']) ? sanitize_text_field($_GET['post_type_filter']) : 'all';

    // Set default date range to 'last_30_days' if not set
    $date_range = isset($_GET['date_range']) ? sanitize_text_field($_GET['date_range']) : 'last_30_days';
    $start_date = isset($_GET['start_date']) ? sanitize_text_field($_GET['start_date']) : '';
    $end_date = isset($_GET['end_date']) ? sanitize_text_field($_GET['end_date']) : '';

    // Convert show_all_traffic to a boolean
    $show_all_traffic = isset($_GET['show_all_traffic']) && $_GET['show_all_traffic'] === '1';
    // The `$human_only` flag dictates if we filter out bots. It's true when not showing all traffic.
    $human_only_filter = !$show_all_traffic;

    // Handle date presets
    if ($date_range !== 'custom') {
        $dates = sky_seo_get_date_range($date_range);
        $start_date = $dates['start'];
        $end_date = $dates['end'];
    }

    // Get comparison data if enabled
    $compare_mode = isset($_GET['compare']) && $_GET['compare'] === '1';
    $compare_start = '';
    $compare_end = '';
    if ($compare_mode) {
        $compare_dates = sky_seo_get_comparison_dates($start_date, $end_date);
        $compare_start = $compare_dates['start'];
        $compare_end = $compare_dates['end'];
    }

    global $wpdb;
    $clicks_table = $wpdb->prefix . 'sky_seo_clicks';
    $posts_table = $wpdb->prefix . 'posts';

    // Get traffic quality metrics (these should always reflect all traffic for quality overview)
    $traffic_quality = sky_seo_get_traffic_quality_metrics($start_date, $end_date, $selected_post_type);

    // Get current period data based on traffic type selection
    $current_data = sky_seo_get_analytics_data($start_date, $end_date, $selected_post_type, $human_only_filter);

    // Get comparison period data if needed
    $compare_data = $compare_mode ? sky_seo_get_analytics_data($compare_start, $compare_end, $selected_post_type, $human_only_filter) : null;

    // Calculate percentage changes
    $changes = sky_seo_calculate_changes($current_data, $compare_data);

    // Get total content count for pagination
    $total_content = sky_seo_get_content_count($start_date, $end_date, $selected_post_type, $human_only_filter);
    $total_pages = ceil($total_content / 20);

    ?>
    <div class="sky-seo-analytics-dashboard">
        <!-- Header with Date Controls -->
<div class="sky-seo-dashboard-header">
    <div class="sky-header-top">
        <h2><?php _e('Analytics Dashboard', 'sky360'); ?></h2>
        
<div class="sky-powered-by">
    <a href="https://skywebdesign.co.uk/" target="_blank">
        <span><?php _e('Powered by Sky Web', 'sky360'); ?></span>
        <img src="<?php echo SKY_SEO_BOOST_PLUGIN_URL; ?>assets/img/logo.svg" alt="Sky Web">
    </a>
</div>
    </div>
    <div class="sky-seo-date-controls">
                <form method="get" class="sky-seo-date-form">
    <input type="hidden" name="page" value="sky360">

                    <!-- Quick Date Presets -->
                    <div class="sky-seo-date-presets">
                        <select name="date_range" id="date_range" class="sky-seo-date-preset">
                            <option value="today" <?php selected($date_range, 'today'); ?>><?php _e('Today', 'sky360'); ?></option>
                            <option value="yesterday" <?php selected($date_range, 'yesterday'); ?>><?php _e('Yesterday', 'sky360'); ?></option>
                            <option value="last_7_days" <?php selected($date_range, 'last_7_days'); ?>><?php _e('Last 7 Days', 'sky360'); ?></option>
                            <option value="last_30_days" <?php selected($date_range, 'last_30_days'); ?>><?php _e('Last 30 Days', 'sky360'); ?></option>
                            <option value="last_90_days" <?php selected($date_range, 'last_90_days'); ?>><?php _e('Last 90 Days', 'sky360'); ?></option>
                            <option value="this_month" <?php selected($date_range, 'this_month'); ?>><?php _e('This Month', 'sky360'); ?></option>
                            <option value="last_month" <?php selected($date_range, 'last_month'); ?>><?php _e('Last Month', 'sky360'); ?></option>
                            <option value="custom" <?php selected($date_range, 'custom'); ?>><?php _e('Custom Range', 'sky360'); ?></option>
                        </select>
                    </div>

                    <!-- Custom Date Range -->
                    <div class="sky-seo-custom-dates" <?php echo $date_range !== 'custom' ? 'style="display:none;"' : ''; ?>>
                        <input type="date" name="start_date" value="<?php echo esc_attr($start_date); ?>" class="sky-seo-date-input">
                        <span><?php _e('to', 'sky360'); ?></span>
                        <input type="date" name="end_date" value="<?php echo esc_attr($end_date); ?>" class="sky-seo-date-input">
                    </div>

                    <!-- Post Type Filter -->
                    <select name="post_type_filter" class="sky-seo-filter-select">
                        <option value="all" <?php selected($selected_post_type, 'all'); ?>><?php _e('All Content Types', 'sky360'); ?></option>
                        <optgroup label="<?php _e('Sky SEO Post Types', 'sky360'); ?>">
                            <?php // Iterate through available sky_seo post types if they are registered ?>
                            <option value="sky_areas" <?php selected($selected_post_type, 'sky_areas'); ?>><?php _e('Areas', 'sky360'); ?></option>
                            <option value="sky_trending" <?php selected($selected_post_type, 'sky_trending'); ?>><?php _e('Trending Searches', 'sky360'); ?></option>
                            <option value="sky_sectors" <?php selected($selected_post_type, 'sky_sectors'); ?>><?php _e('Sectors', 'sky360'); ?></option>
                        </optgroup>
                        <optgroup label="<?php _e('WordPress Content', 'sky360'); ?>">
                            <option value="page" <?php selected($selected_post_type, 'page'); ?>><?php _e('Pages', 'sky360'); ?></option>
                            <option value="post" <?php selected($selected_post_type, 'post'); ?>><?php _e('Posts', 'sky360'); ?></option>
                        </optgroup>
                        <?php if (class_exists('WooCommerce')) : ?>
                            <optgroup label="<?php _e('WooCommerce', 'sky360'); ?>">
                                <option value="product" <?php selected($selected_post_type, 'product'); ?>><?php _e('Products', 'sky360'); ?></option>
                            </optgroup>
                        <?php endif; ?>
                    </select>

                    <!-- Compare Toggle -->
                    <label class="sky-seo-compare-toggle">
                        <input type="checkbox" name="compare" value="1" <?php checked($compare_mode); ?>>
                        <?php _e('Compare Periods', 'sky360'); ?>
                    </label>

                    <button type="submit" class="button button-primary"><?php _e('Apply', 'sky360'); ?></button>
                    <?php
                    // Correctly generate the export URL with nonce and current filters
$export_url_args = [
    'page'               => 'sky360', // Add this line
    'action'             => 'export_analytics',
    'post_type_filter'   => $selected_post_type,
    'date_range'         => $date_range,
    'start_date'         => $start_date,
    'end_date'           => $end_date,
    'show_all_traffic'   => $show_all_traffic ? '1' : '0',
    '_wpnonce'           => wp_create_nonce('sky_seo_export_analytics') // Add nonce for security
];
                    $export_url = add_query_arg($export_url_args, admin_url('admin.php?page=sky360'));
                    $export_url = wp_nonce_url($export_url, 'sky_seo_export_analytics');
                    ?>
                    <a href="<?php echo esc_url($export_url); ?>" class="button"><?php _e('Export CSV', 'sky360'); ?></a>
                </form>
            </div>
        </div>

        <!-- Traffic Quality Overview -->
<div class="sky-seo-traffic-quality">
    <div class="sky-seo-quality-header">
        <h3>
            <svg width="24" height="24" viewBox="0 0 24 24" fill="none" xmlns="http://www.w3.org/2000/svg" style="vertical-align: middle; margin-right: 8px;">
                <path d="M9 11H7a4 4 0 1 1 0-8h2m6 0h2a4 4 0 1 1 0 8h-2m-6 0h6" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"/>
                <path d="M5 21a4 4 0 1 1 8 0M15 21a4 4 0 1 1 8 0" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"/>
                <path d="M9 17v-6m6 6v-6" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"/>
            </svg>
            <?php _e('Traffic Quality Overview', 'sky360'); ?>
        </h3>
        <span class="description"><?php echo sprintf(__('Period: %s to %s', 'sky360'), $start_date, $end_date); ?></span>
    </div>

    <div class="sky-seo-quality-metrics">
        <div class="sky-seo-quality-metric human-traffic">
            <div class="metric-icon">
                <svg width="32" height="32" viewBox="0 0 24 24" fill="none" xmlns="http://www.w3.org/2000/svg">
                    <path d="M20 21v-2a4 4 0 0 0-4-4H8a4 4 0 0 0-4 4v2" stroke="#10b981" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"/>
                    <circle cx="12" cy="7" r="4" stroke="#10b981" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"/>
                </svg>
            </div>
            <h4><?php _e('Real Visitor Traffic', 'sky360'); ?></h4>
            <div class="value"><?php echo number_format_i18n($traffic_quality['human_clicks']); ?></div>
            <div class="percentage <?php echo $traffic_quality['human_percentage'] > 50 ? 'positive' : 'negative'; ?>">
                <?php echo number_format_i18n($traffic_quality['human_percentage'], 1); ?>%
                <svg width="16" height="16" viewBox="0 0 24 24" fill="none" style="vertical-align: middle;">
                    <path d="M7 17l10-10M17 7v10h-10" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"/>
                </svg>
            </div>
            <div class="description"><?php _e('Verified human visitors', 'sky360'); ?></div>
        </div>

        <div class="sky-seo-quality-metric bot-traffic">
            <div class="metric-icon">
                <svg width="32" height="32" viewBox="0 0 24 24" fill="none" xmlns="http://www.w3.org/2000/svg">
                    <rect x="3" y="11" width="18" height="10" rx="2" stroke="#ef4444" stroke-width="2"/>
                    <circle cx="9" cy="7" r="3" stroke="#ef4444" stroke-width="2"/>
                    <circle cx="15" cy="7" r="3" stroke="#ef4444" stroke-width="2"/>
                    <path d="M9 7v4M15 7v4" stroke="#ef4444" stroke-width="2"/>
                    <circle cx="8" cy="16" r="1" fill="#ef4444"/>
                    <circle cx="16" cy="16" r="1" fill="#ef4444"/>
                </svg>
            </div>
            <h4><?php _e('BOT TRAFFIC', 'sky360'); ?></h4>
            <div class="value"><?php echo number_format_i18n($traffic_quality['bot_clicks']); ?></div>
            <div class="percentage">
                <?php echo number_format_i18n($traffic_quality['bot_percentage'], 1); ?>%
                <svg width="16" height="16" viewBox="0 0 24 24" fill="none" style="vertical-align: middle;">
                    <path d="M7 7l10 10M7 17h10V7" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"/>
                </svg>
            </div>
            <div class="description"><?php _e('Search engines & crawlers', 'sky360'); ?></div>
        </div>

        <div class="sky-seo-quality-metric suspicious-traffic">
            <div class="metric-icon">
                <svg width="32" height="32" viewBox="0 0 24 24" fill="none" xmlns="http://www.w3.org/2000/svg">
                    <path d="M10.29 3.86L1.82 18a2 2 0 0 0 1.71 3h16.94a2 2 0 0 0 1.71-3L13.71 3.86a2 2 0 0 0-3.42 0z" stroke="#f59e0b" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"/>
                    <line x1="12" y1="9" x2="12" y2="13" stroke="#f59e0b" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"/>
                    <line x1="12" y1="17" x2="12.01" y2="17" stroke="#f59e0b" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"/>
                </svg>
            </div>
            <h4><?php _e('SUSPICIOUS TRAFFIC', 'sky360'); ?></h4>
            <div class="value"><?php echo number_format_i18n($traffic_quality['suspicious_clicks']); ?></div>
            <div class="percentage">
                <?php echo number_format_i18n($traffic_quality['suspicious_percentage'], 1); ?>%
                <svg width="16" height="16" viewBox="0 0 24 24" fill="none" style="vertical-align: middle;">
                    <path d="M7 7l10 10M7 17h10V7" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"/>
                </svg>
            </div>
            <div class="description"><?php _e('Potential spam/automated', 'sky360'); ?></div>
        </div>

        <div class="sky-seo-quality-metric total-traffic">
            <div class="metric-icon">
                <svg width="32" height="32" viewBox="0 0 24 24" fill="none" xmlns="http://www.w3.org/2000/svg">
                    <polyline points="22 12 18 12 15 21 9 3 6 12 2 12" stroke="#6366f1" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"/>
                </svg>
            </div>
            <h4><?php _e('TOTAL TRAFFIC', 'sky360'); ?></h4>
            <div class="value"><?php echo number_format_i18n($traffic_quality['total_clicks']); ?></div>
            <div class="description"><?php _e('All recorded clicks', 'sky360'); ?></div>
        </div>
    </div>

    <!-- Visual Breakdown -->
    <div class="sky-seo-traffic-breakdown">
        <div class="sky-seo-breakdown-bars">
            <?php if ($traffic_quality['human_percentage'] > 0) : ?>
                <div class="sky-seo-breakdown-segment human" 
                     style="width: <?php echo esc_attr(str_replace(',', '.', $traffic_quality['human_percentage'])); ?>%;" 
                     title="<?php echo esc_attr(sprintf(__('Human: %s%%', 'sky360'), number_format_i18n($traffic_quality['human_percentage'], 1))); ?>">
                    <span class="segment-label"><?php echo round($traffic_quality['human_percentage']) . '%'; ?></span>
                </div>
            <?php endif; ?>

            <?php if ($traffic_quality['bot_percentage'] > 0) : ?>
                <div class="sky-seo-breakdown-segment bot" 
                     style="width: <?php echo esc_attr(str_replace(',', '.', $traffic_quality['bot_percentage'])); ?>%;" 
                     title="<?php echo esc_attr(sprintf(__('Bots: %s%%', 'sky360'), number_format_i18n($traffic_quality['bot_percentage'], 1))); ?>">
                    <span class="segment-label"><?php echo round($traffic_quality['bot_percentage']) . '%'; ?></span>
                </div>
            <?php endif; ?>

            <?php if ($traffic_quality['suspicious_percentage'] > 0) : ?>
                <div class="sky-seo-breakdown-segment suspicious" 
                     style="width: <?php echo esc_attr(str_replace(',', '.', $traffic_quality['suspicious_percentage'])); ?>%;" 
                     title="<?php echo esc_attr(sprintf(__('Suspicious: %s%%', 'sky360'), number_format_i18n($traffic_quality['suspicious_percentage'], 1))); ?>">
                    <span class="segment-label"><?php if ($traffic_quality['suspicious_percentage'] > 5) echo round($traffic_quality['suspicious_percentage']) . '%'; ?></span>
                </div>
            <?php endif; ?>
        </div>

        <div class="sky-seo-breakdown-legend">
            <div class="sky-seo-legend-item">
                <div class="sky-seo-legend-color" style="background: #10b981;"></div>
                <span><?php _e('Human Traffic', 'sky360'); ?></span>
            </div>
            <div class="sky-seo-legend-item">
                <div class="sky-seo-legend-color" style="background: #ef4444;"></div>
                <span><?php _e('Bot Traffic', 'sky360'); ?></span>
            </div>
            <div class="sky-seo-legend-item">
                <div class="sky-seo-legend-color" style="background: #f59e0b;"></div>
                <span><?php _e('Suspicious', 'sky360'); ?></span>
            </div>
        </div>
    </div>
</div>

        <!-- Key Metrics Cards -->
        <div class="sky-seo-metrics-grid">
            <!-- Total Clicks -->
            <div class="sky-seo-metric-card">
                <div class="sky-seo-metric-icon">
                    <span class="dashicons dashicons-chart-line"></span>
                </div>
                <div class="sky-seo-metric-content">
                    <h3><?php _e('Total Clicks', 'sky360'); ?>
                        <span style="font-size: 12px; color: #666; font-weight: normal;">
                            (<?php echo $show_all_traffic ? __('All', 'sky360') : __('Human', 'sky360'); ?>)
                        </span>
                    </h3>
                    <div class="sky-seo-metric-value"><?php echo number_format_i18n($current_data['total_clicks']); ?></div>
                    <?php if ($compare_mode && $changes['total_clicks'] !== null) : ?>
                        <div class="sky-seo-metric-change <?php echo $changes['total_clicks'] >= 0 ? 'positive' : 'negative'; ?>">
                            <span class="dashicons dashicons-arrow-<?php echo $changes['total_clicks'] >= 0 ? 'up' : 'down'; ?>-alt"></span>
                            <?php echo abs($changes['total_clicks']); ?>%
                        </div>
                    <?php endif; ?>
                </div>
                <div class="sky-seo-metric-sparkline" data-values="<?php echo esc_attr(json_encode($current_data['sparkline_clicks'])); ?>"></div>
            </div>

            <!-- Unique Content Viewed -->
            <div class="sky-seo-metric-card">
                <div class="sky-seo-metric-icon">
                    <span class="dashicons dashicons-groups"></span>
                </div>
                <div class="sky-seo-metric-content">
                    <h3><?php _e('Unique Content Viewed', 'sky360'); ?></h3>
                    <div class="sky-seo-metric-value"><?php echo number_format_i18n($current_data['unique_posts']); ?></div>
                    <?php if ($compare_mode && $changes['unique_posts'] !== null) : ?>
                        <div class="sky-seo-metric-change <?php echo $changes['unique_posts'] >= 0 ? 'positive' : 'negative'; ?>">
                            <span class="dashicons dashicons-arrow-<?php echo $changes['unique_posts'] >= 0 ? 'up' : 'down'; ?>-alt"></span>
                            <?php echo abs($changes['unique_posts']); ?>%
                        </div>
                    <?php endif; ?>
                </div>
                <div class="sky-seo-metric-sparkline" data-values="<?php echo esc_attr(json_encode($current_data['sparkline_unique'])); ?>"></div>
            </div>

            <!-- Average Clicks per Post -->
            <div class="sky-seo-metric-card">
                <div class="sky-seo-metric-icon">
                    <span class="dashicons dashicons-performance"></span>
                </div>
                <div class="sky-seo-metric-content">
                    <h3><?php _e('Avg. Clicks/Content', 'sky360'); ?></h3>
                    <div class="sky-seo-metric-value"><?php echo number_format_i18n($current_data['avg_clicks'], 1); ?></div>
                    <?php if ($compare_mode && $changes['avg_clicks'] !== null) : ?>
                        <div class="sky-seo-metric-change <?php echo $changes['avg_clicks'] >= 0 ? 'positive' : 'negative'; ?>">
                            <span class="dashicons dashicons-arrow-<?php echo $changes['avg_clicks'] >= 0 ? 'up' : 'down'; ?>-alt"></span>
                            <?php echo abs($changes['avg_clicks']); ?>%
                        </div>
                    <?php endif; ?>
                </div>
                <div class="sky-seo-metric-sparkline" data-values="<?php echo esc_attr(json_encode($current_data['sparkline_avg'])); ?>"></div>
            </div>

            <!-- Click Growth Rate -->
            <div class="sky-seo-metric-card">
                <div class="sky-seo-metric-icon">
                    <span class="dashicons dashicons-trending-up"></span>
                </div>
                <div class="sky-seo-metric-content">
                    <h3><?php _e('Growth Rate', 'sky360'); ?></h3>
                    <div class="sky-seo-metric-value">
                        <?php
                        $growth_rate = $current_data['growth_rate'];
                        echo ($growth_rate >= 0 ? '+' : '') . number_format_i18n($growth_rate, 1) . '%';
                        ?>
                    </div>
                    <div class="sky-seo-metric-subtitle"><?php _e('vs previous period', 'sky360'); ?></div>
                </div>
                <div class="sky-seo-metric-sparkline" data-values="<?php echo esc_attr(json_encode($current_data['sparkline_growth'])); ?>"></div>
            </div>
        </div>
        
        <!-- Top Traffic Sources -->
<div class="sky-seo-top-sources">
    <div class="sky-seo-sources-header">
        <h3>
            <svg width="24" height="24" viewBox="0 0 24 24" fill="none" xmlns="http://www.w3.org/2000/svg" style="vertical-align: middle; margin-right: 8px;">
                <path d="M21 16V8a2 2 0 0 0-1-1.73l-7-4a2 2 0 0 0-2 0l-7 4A2 2 0 0 0 3 8v8a2 2 0 0 0 1 1.73l7 4a2 2 0 0 0 2 0l7-4A2 2 0 0 0 21 16z" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"/>
                <polyline points="3.27 6.96 12 12.01 20.73 6.96" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"/>
                <line x1="12" y1="22.08" x2="12" y2="12" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"/>
            </svg>
            <?php _e('Top Traffic Sources', 'sky360'); ?>
        </h3>
        <span class="sky-seo-sources-subtitle"><?php _e('Where your visitors come from', 'sky360'); ?></span>
    </div>
    
    <div class="sky-seo-sources-grid">
        <?php
        // Get top traffic sources
        $top_sources = sky_seo_get_top_traffic_sources($start_date, $end_date, $selected_post_type, $human_only_filter);
        
        foreach ($top_sources as $source) :
            $icon_svg = sky_seo_get_source_icon($source->source_name);
            $color_class = sky_seo_get_source_color_class($source->source_name);
        ?>
            <div class="sky-seo-source-box <?php echo esc_attr($color_class); ?>">
                <div class="source-icon">
                    <?php echo $icon_svg; ?>
                </div>
                <div class="source-content">
                    <h4><?php echo esc_html($source->source_name); ?></h4>
                    <div class="source-value"><?php echo number_format_i18n($source->total_clicks); ?></div>
                    <div class="source-percentage">
                        <?php 
                        $percentage = ($source->total_clicks / max($current_data['total_clicks'], 1)) * 100;
                        echo number_format_i18n($percentage, 1) . '%'; 
                        ?>
                    </div>
                </div>
                <div class="source-trend">
                    <?php if ($source->trend > 0) : ?>
                        <svg width="16" height="16" viewBox="0 0 24 24" fill="none">
                            <path d="M22 7l-10 10L8 13l-6 6" stroke="#10b981" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"/>
                            <path d="M16 7h6v6" stroke="#10b981" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"/>
                        </svg>
                    <?php else : ?>
                        <svg width="16" height="16" viewBox="0 0 24 24" fill="none">
                            <path d="M22 17l-10-10L8 11l-6-6" stroke="#ef4444" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"/>
                            <path d="M16 17h6v-6" stroke="#ef4444" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"/>
                        </svg>
                    <?php endif; ?>
                </div>
            </div>
        <?php endforeach; ?>
    </div>
</div>

        <!-- Bot Activity Report (only show when viewing all traffic) -->
        <?php if ($show_all_traffic) : ?>
            <div class="sky-seo-bot-activity">
                <h3><?php _e('Recent Bot Activity', 'sky360'); ?></h3>
                <table class="sky-seo-bot-table">
                    <thead>
                        <tr>
                            <th><?php _e('Bot Type', 'sky360'); ?></th>
                            <th><?php _e('Name', 'sky360'); ?></th>
                            <th><?php _e('Visits', 'sky360'); ?></th>
                            <th><?php _e('Last Seen', 'sky360'); ?></th>
                            <th><?php _e('Purpose', 'sky360'); ?></th>
                        </tr>
                    </thead>
                    <tbody>
                        <?php
                        $bot_activity = sky_seo_get_bot_activity($start_date, $end_date);
                        if (!empty($bot_activity)) :
                            foreach ($bot_activity as $bot) :
                                ?>
                                <tr>
                                    <td>
                                        <span class="bot-type-badge <?php echo esc_attr($bot->type); ?>">
                                            <?php echo esc_html(ucfirst($bot->type)); ?>
                                        </span>
                                    </td>
                                    <td><?php echo esc_html($bot->bot_name); ?></td>
                                    <td><?php echo number_format_i18n($bot->visits); ?></td>
                                    <td><?php echo human_time_diff(strtotime($bot->last_seen), current_time('timestamp')) . ' ' . __('ago', 'sky360'); ?></td>
                                    <td><?php echo esc_html($bot->purpose); ?></td>
                                </tr>
                            <?php
                            endforeach;
                        else :
                            ?>
                            <tr>
                                <td colspan="5" style="text-align: center; color: #999;">
                                    <?php _e('No bot activity detected in this period.', 'sky360'); ?>
                                </td>
                            </tr>
                        <?php endif; ?>
                    </tbody>
                </table>
            </div>
        <?php endif; ?>

        <!-- Charts Row -->
        <div class="sky-seo-charts-row">
            <!-- Traffic Sources -->
            <div class="sky-seo-chart-card">
                <h3><?php _e('Traffic Sources', 'sky360'); ?></h3>
                <canvas id="sky-seo-sources-chart" width="300" height="300"></canvas>
                <div class="sky-seo-chart-legend" id="sources-legend"></div>
            </div>

            <!-- Content Performance -->
            <div class="sky-seo-chart-card">
                <h3><?php _e('Content Type Performance', 'sky360'); ?></h3>
                <canvas id="sky-seo-content-chart" width="300" height="300"></canvas>
                <div class="sky-seo-chart-legend" id="content-legend"></div>
            </div>

            <!-- Daily Trends -->
            <div class="sky-seo-chart-card sky-seo-chart-wide">
                <h3><?php _e('Click Trends', 'sky360'); ?></h3>
                <canvas id="sky-seo-trends-chart" height="300"></canvas>
            </div>
        </div>

        <!-- Geographic Performance Chart -->
        <div class="sky-seo-chart-card sky-seo-chart-wide">
            <h3><?php _e('Geographic Performance', 'sky360'); ?></h3>
            <canvas id="sky-seo-geography-chart" height="300"></canvas>
        </div>
        
                <!-- Allow other modules to add sections -->
        <?php do_action('sky_seo_analytics_sections'); ?>

        <!-- Top Content Table -->
        <div class="sky-seo-content-table-card">
            <div class="sky-seo-table-header">
                <h3><?php _e('Top Performing Content', 'sky360'); ?></h3>
                <div class="sky-seo-table-actions">
                    <input type="text" id="sky-seo-content-search" placeholder="<?php _e('Search content...', 'sky360'); ?>" class="sky-seo-search-input">
                    <select id="sky-seo-content-type-filter" class="sky-seo-filter-select">
                        <option value=""><?php _e('All Types', 'sky360'); ?></option>
                        <option value="sky_areas"><?php _e('Areas', 'sky360'); ?></option>
                        <option value="sky_trending"><?php _e('Trending', 'sky360'); ?></option>
                        <option value="sky_sectors"><?php _e('Sectors', 'sky360'); ?></option>
                        <option value="page"><?php _e('Pages', 'sky360'); ?></option>
                        <option value="post"><?php _e('Posts', 'sky360'); ?></option>
                        <?php if (class_exists('WooCommerce')) : ?>
                            <option value="product"><?php _e('Products', 'sky360'); ?></option>
                        <?php endif; ?>
                    </select>
                    <select id="sky-seo-content-traffic-filter" class="sky-seo-filter-select">
                        <option value=""><?php _e('All Traffic', 'sky360'); ?></option>
                        <option value="google"><?php _e('Google Traffic', 'sky360'); ?></option>
                        <option value="social"><?php _e('Social Traffic', 'sky360'); ?></option>
                        <option value="direct"><?php _e('Direct Traffic', 'sky360'); ?></option>
                    </select>
                    <select id="sky-seo-content-sort" class="sky-seo-filter-select">
                        <option value="total_clicks"><?php _e('Sort by Total Clicks', 'sky360'); ?></option>
                        <option value="google_clicks"><?php _e('Sort by Google Clicks', 'sky360'); ?></option>
                        <option value="social_clicks"><?php _e('Sort by Social Clicks', 'sky360'); ?></option>
                        <option value="direct_clicks"><?php _e('Sort by Direct Clicks', 'sky360'); ?></option>
                        <option value="recent"><?php _e('Sort by Recent', 'sky360'); ?></option>
                    </select>
                </div>
            </div>

            <table class="sky-seo-analytics-table widefat">
                <thead>
                    <tr>
                        <th class="column-title"><?php _e('Title', 'sky360'); ?></th>
                        <th class="column-type"><?php _e('Type', 'sky360'); ?></th>
                        <th class="column-clicks sortable" data-sort="clicks"><?php _e('Total Clicks', 'sky360'); ?></th>
                        <th class="column-google sortable" data-sort="google"><?php _e('Google', 'sky360'); ?></th>
                        <th class="column-social sortable" data-sort="social"><?php _e('Social', 'sky360'); ?></th>
                        <th class="column-direct sortable" data-sort="direct"><?php _e('Direct', 'sky360'); ?></th>
                        <th class="column-trend"><?php _e('Trend', 'sky360'); ?></th>
                        <th class="column-actions"><?php _e('Actions', 'sky360'); ?></th>
                    </tr>
                </thead>
                <tbody id="sky-seo-content-tbody">
                    <?php
                    $top_content = sky_seo_get_top_content($start_date, $end_date, $selected_post_type, 20, 1, $human_only_filter);
                    foreach ($top_content as $content) :
                        $trend_data = sky_seo_get_content_trend($content->ID, $start_date, $end_date, $human_only_filter);
                        ?>
                        <tr data-post-id="<?php echo esc_attr($content->ID); ?>">
                            <td class="column-title">
                                <strong><?php echo esc_html($content->post_title); ?></strong>
                                <div class="row-actions">
                                    <span class="edit"><a href="<?php echo esc_url(get_edit_post_link($content->ID)); ?>"><?php _e('Edit', 'sky360'); ?></a> | </span>
                                    <span class="view"><a href="<?php echo esc_url(get_permalink($content->ID)); ?>" target="_blank"><?php _e('View', 'sky360'); ?></a></span>
                                </div>
                            </td>
                            <td class="column-type">
                                <span class="sky-seo-content-type sky-seo-type-<?php echo esc_attr($content->post_type); ?>">
                                    <?php echo esc_html(sky_seo_get_post_type_label($content->post_type)); ?>
                                </span>
                            </td>
                            <td class="column-clicks" data-clicks="<?php echo esc_attr($content->total_clicks); ?>">
                                <?php echo number_format_i18n($content->total_clicks); ?>
                            </td>
                            <td class="column-google" data-google="<?php echo esc_attr($content->google_clicks); ?>">
                                <?php echo number_format_i18n($content->google_clicks); ?>
                                <span class="sky-seo-percentage">(<?php echo round(($content->google_clicks / max($content->total_clicks, 1)) * 100); ?>%)</span>
                            </td>
                            <td class="column-social" data-social="<?php echo esc_attr($content->social_clicks); ?>">
                                <?php echo number_format_i18n($content->social_clicks); ?>
                                <span class="sky-seo-percentage">(<?php echo round(($content->social_clicks / max($content->total_clicks, 1)) * 100); ?>%)</span>
                            </td>
                            <td class="column-direct" data-direct="<?php echo esc_attr($content->direct_clicks); ?>">
                                <?php echo number_format_i18n($content->direct_clicks); ?>
                                <span class="sky-seo-percentage">(<?php echo round(($content->direct_clicks / max($content->total_clicks, 1)) * 100); ?>%)</span>
                            </td>
                            <td class="column-trend">
                                <div class="sky-seo-mini-chart" data-trend="<?php echo esc_attr(json_encode($trend_data)); ?>"></div>
                            </td>
                            <td class="column-actions">
                                <button class="button button-small sky-seo-view-details" data-post-id="<?php echo esc_attr($content->ID); ?>">
                                    <?php _e('Details', 'sky360'); ?>
                                </button>
                            </td>
                        </tr>
                    <?php endforeach; ?>
                </tbody>
            </table>

            <!-- Pagination -->
            <?php if ($total_pages > 1) : ?>
                <div class="sky-seo-content-pagination">
                    <div class="pagination-info">
                        <?php printf(__('Page 1 of %s', 'sky360'), number_format_i18n($total_pages)); ?>
                    </div>
                    <div class="pagination-controls">
                        <button class="button sky-seo-load-more" data-page="2" data-total-pages="<?php echo esc_attr($total_pages); ?>">
                            <?php _e('Load More', 'sky360'); ?>
                        </button>
                    </div>
                </div>
            <?php endif; ?>
        </div>

        <!-- Pass data to JavaScript -->
        <script>
            var skySeoAnalyticsData = {
                traffic_sources: <?php echo json_encode($current_data['traffic_sources']); ?>,
                content_types: <?php echo json_encode($current_data['content_types']); ?>,
                daily_trends: <?php echo json_encode($current_data['daily_trends']); ?>,
                geographic_data: <?php echo json_encode(sky_seo_get_geographic_data($start_date, $end_date, $selected_post_type, $human_only_filter)); ?>,
                compare_trends: <?php echo $compare_mode ? json_encode($compare_data['daily_trends']) : 'null'; ?>,
                date_range: '<?php echo esc_js($date_range); ?>',
                start_date: '<?php echo esc_js($start_date); ?>',
                end_date: '<?php echo esc_js($end_date); ?>',
                post_type: '<?php echo esc_js($selected_post_type); ?>',
                // Pass as boolean 'true'/'false' strings for JS or convert directly to boolean
                show_all_traffic: <?php echo $show_all_traffic ? 'true' : 'false'; ?>,
                ajax_nonce: '<?php echo wp_create_nonce('sky_seo_analytics_nonce'); ?>'
            };
        </script>
    </div>
    <?php
}

// Get traffic quality metrics
function sky_seo_get_traffic_quality_metrics($start_date, $end_date, $post_type = 'all') {
    global $wpdb;
    $clicks_table = $wpdb->prefix . 'sky_seo_clicks';
    $posts_table = $wpdb->prefix . 'posts';

    $where_date = '';
    if ($start_date && $end_date) {
        $where_date = $wpdb->prepare(" AND c.click_time BETWEEN %s AND %s",
            $start_date . ' 00:00:00',
            $end_date . ' 23:59:59'
        );
    }

    $where_post_type = '';
    // Apply post type filter only if it's not 'all'
    if ($post_type !== 'all') {
        $where_post_type = $wpdb->prepare(" AND p.post_type = %s", $post_type);
    } else {
        // If 'all' is selected, include all tracked post types
        $tracked_types = sky_seo_get_tracked_post_types();
        // Ensure there are tracked types to prevent empty IN clause
        if (!empty($tracked_types)) {
            $placeholders = implode(',', array_fill(0, count($tracked_types), '%s'));
            $where_post_type = $wpdb->prepare(" AND p.post_type IN ($placeholders)", ...$tracked_types);
        } else {
            // No tracked types, so no results from this filter
            $where_post_type = " AND 1=0 ";
        }
    }

    // Get metrics
    $query = "SELECT
        SUM(CASE WHEN c.is_bot = 0 OR c.is_bot IS NULL THEN c.clicks ELSE 0 END) as human_clicks,
        SUM(CASE WHEN c.is_bot = 1 THEN c.clicks ELSE 0 END) as bot_clicks,
        SUM(CASE WHEN c.is_bot = 2 THEN c.clicks ELSE 0 END) as suspicious_clicks,
        SUM(c.clicks) as total_clicks
    FROM $clicks_table c
    LEFT JOIN $posts_table p ON c.post_id = p.ID
    WHERE 1=1 $where_date $where_post_type";

    $results = $wpdb->get_row($query);

    // Ensure results are not null and calculate percentages safely
    $human_clicks = isset($results->human_clicks) ? (int)$results->human_clicks : 0;
    $bot_clicks = isset($results->bot_clicks) ? (int)$results->bot_clicks : 0;
    $suspicious_clicks = isset($results->suspicious_clicks) ? (int)$results->suspicious_clicks : 0;
    $total_clicks = isset($results->total_clicks) ? (int)$results->total_clicks : 0;

    $total = $total_clicks > 0 ? $total_clicks : 1; // Prevent division by zero

    return [
        'human_clicks' => $human_clicks,
        'bot_clicks' => $bot_clicks,
        'suspicious_clicks' => $suspicious_clicks,
        'total_clicks' => $total_clicks,
        'human_percentage' => ($human_clicks / $total) * 100,
        'bot_percentage' => ($bot_clicks / $total) * 100,
        'suspicious_percentage' => ($suspicious_clicks / $total) * 100,
    ];
}

// Get quality class based on percentage
function sky_seo_get_quality_class($percentage) {
    if ($percentage >= 80) return 'good';
    if ($percentage >= 60) return 'warning';
    return 'bad';
}

// Get bot activity report
function sky_seo_get_bot_activity($start_date, $end_date) {
    global $wpdb;
    $clicks_table = $wpdb->prefix . 'sky_seo_clicks';

    $where_date = '';
    if ($start_date && $end_date) {
        $where_date = $wpdb->prepare(" AND click_time BETWEEN %s AND %s",
            $start_date . ' 00:00:00',
            $end_date . ' 23:59:59'
        );
    }

    // This data is for display only, no need to sanitize `user_agent` as intensely before displaying.
    // However, it should be sanitized before insertion into the DB.
    // The `purpose` definitions are hardcoded, which is fine for known bot types.
    $query = "SELECT
        user_agent,
        SUM(clicks) as visits,
        MAX(click_time) as last_seen,
        CASE
            WHEN LOWER(user_agent) LIKE '%googlebot%' THEN 'search'
            WHEN LOWER(user_agent) LIKE '%bingbot%' THEN 'search'
            WHEN LOWER(user_agent) LIKE '%ahrefs%' OR LOWER(user_agent) LIKE '%ahrefsbot%' THEN 'crawler'
            WHEN LOWER(user_agent) LIKE '%semrush%' OR LOWER(user_agent) LIKE '%semrushbot%' THEN 'crawler'
            WHEN LOWER(user_agent) LIKE '%dotbot%' THEN 'crawler'
            WHEN LOWER(user_agent) LIKE '%mj12bot%' THEN 'crawler'
            WHEN LOWER(user_agent) LIKE '%spam%' THEN 'spam'
            WHEN LOWER(user_agent) LIKE '%bot%' THEN 'crawler'
            ELSE 'monitor'
        END AS type,
        CASE
            WHEN LOWER(user_agent) LIKE '%googlebot%' THEN 'Googlebot'
            WHEN LOWER(user_agent) LIKE '%bingbot%' THEN 'Bingbot'
            WHEN LOWER(user_agent) LIKE '%ahrefs%' OR LOWER(user_agent) LIKE '%ahrefsbot%' THEN 'Ahrefs Bot'
            WHEN LOWER(user_agent) LIKE '%semrush%' OR LOWER(user_agent) LIKE '%semrushbot%' THEN 'SEMrush Bot'
            WHEN LOWER(user_agent) LIKE '%dotbot%' THEN 'DotBot'
            WHEN LOWER(user_agent) LIKE '%mj12bot%' THEN 'Majestic Bot'
            ELSE 'Other Bot'
        END AS bot_name,
        CASE
            WHEN LOWER(user_agent) LIKE '%googlebot%' THEN 'Search engine indexing'
            WHEN LOWER(user_agent) LIKE '%bingbot%' THEN 'Search engine indexing'
            WHEN LOWER(user_agent) LIKE '%ahrefs%' OR LOWER(user_agent) LIKE '%ahrefsbot%' THEN 'SEO analysis'
            WHEN LOWER(user_agent) LIKE '%semrush%' OR LOWER(user_agent) LIKE '%semrushbot%' THEN 'SEO analysis'
            WHEN LOWER(user_agent) LIKE '%dotbot%' THEN 'SEO analysis'
            WHEN LOWER(user_agent) LIKE '%mj12bot%' THEN 'SEO analysis'
            WHEN LOWER(user_agent) LIKE '%spam%' THEN 'Spam/Malicious activity'
            ELSE 'Site monitoring'
        END AS purpose
    FROM $clicks_table
    WHERE is_bot = 1 $where_date
    GROUP BY user_agent
    ORDER BY visits DESC
    LIMIT 10";

    return $wpdb->get_results($query);
}

// AJAX handler for loading more content
function sky_seo_ajax_load_more_content() {
    check_ajax_referer('sky_seo_analytics_nonce', 'nonce');

    $page = isset($_POST['page']) ? intval($_POST['page']) : 1;
    $start_date = isset($_POST['start_date']) ? sanitize_text_field($_POST['start_date']) : '';
    $end_date = isset($_POST['end_date']) ? sanitize_text_field($_POST['end_date']) : '';
    $post_type = isset($_POST['post_type']) ? sanitize_text_field($_POST['post_type']) : 'all';
    // Convert string 'true'/'false' from JS to boolean
    $show_all_traffic = isset($_POST['show_all_traffic']) ? filter_var($_POST['show_all_traffic'], FILTER_VALIDATE_BOOLEAN) : false;
    $human_only_filter = !$show_all_traffic; // Correctly derive human_only flag

    $top_content = sky_seo_get_top_content($start_date, $end_date, $post_type, 20, $page, $human_only_filter);

    ob_start();
    foreach ($top_content as $content) :
        $trend_data = sky_seo_get_content_trend($content->ID, $start_date, $end_date, $human_only_filter);
        ?>
        <tr data-post-id="<?php echo esc_attr($content->ID); ?>">
            <td class="column-title">
                <strong><?php echo esc_html($content->post_title); ?></strong>
                <div class="row-actions">
                    <span class="edit"><a href="<?php echo esc_url(get_edit_post_link($content->ID)); ?>"><?php _e('Edit', 'sky360'); ?></a> | </span>
                    <span class="view"><a href="<?php echo esc_url(get_permalink($content->ID)); ?>" target="_blank"><?php _e('View', 'sky360'); ?></a></span>
                </div>
            </td>
            <td class="column-type">
                <span class="sky-seo-content-type sky-seo-type-<?php echo esc_attr($content->post_type); ?>">
                    <?php echo esc_html(sky_seo_get_post_type_label($content->post_type)); ?>
                </span>
            </td>
            <td class="column-clicks" data-clicks="<?php echo esc_attr($content->total_clicks); ?>">
                <?php echo number_format_i18n($content->total_clicks); ?>
            </td>
            <td class="column-google" data-google="<?php echo esc_attr($content->google_clicks); ?>">
                <?php echo number_format_i18n($content->google_clicks); ?>
                <span class="sky-seo-percentage">(<?php echo round(($content->google_clicks / max($content->total_clicks, 1)) * 100); ?>%)</span>
            </td>
            <td class="column-social" data-social="<?php echo esc_attr($content->social_clicks); ?>">
                <?php echo number_format_i18n($content->social_clicks); ?>
                <span class="sky-seo-percentage">(<?php echo round(($content->social_clicks / max($content->total_clicks, 1)) * 100); ?>%)</span>
            </td>
            <td class="column-direct" data-direct="<?php echo esc_attr($content->direct_clicks); ?>">
                <?php echo number_format_i18n($content->direct_clicks); ?>
                <span class="sky-seo-percentage">(<?php echo round(($content->direct_clicks / max($content->total_clicks, 1)) * 100); ?>%)</span>
            </td>
            <td class="column-trend">
                <div class="sky-seo-mini-chart" data-trend="<?php echo esc_attr(json_encode($trend_data)); ?>"></div>
            </td>
            <td class="column-actions">
                <button class="button button-small sky-seo-view-details" data-post-id="<?php echo esc_attr($content->ID); ?>">
                    <?php _e('Details', 'sky360'); ?>
                </button>
            </td>
        </tr>
    <?php endforeach;

    $html = ob_get_clean();
    $has_more = count($top_content) === 20; // If exact limit length, assume there might be more

    wp_send_json_success(['html' => $html, 'has_more' => $has_more]);
}

// AJAX handler for post details
function sky_seo_ajax_get_post_details() {
    check_ajax_referer('sky_seo_analytics_nonce', 'nonce');

    $post_id = isset($_POST['post_id']) && is_numeric($_POST['post_id']) ? intval($_POST['post_id']) : 0;

    if (!$post_id) {
        wp_send_json_error('Invalid post ID');
    }

    global $wpdb;
    $clicks_table = $wpdb->prefix . 'sky_seo_clicks';

    // Get country breakdown
    // Assuming you want *all* data for the post details, not filtered by human_only
    $countries = $wpdb->get_results($wpdb->prepare("
        SELECT
            country_name,
            country_code,
            SUM(clicks) as views
        FROM $clicks_table
        WHERE post_id = %d
        AND country_code IS NOT NULL AND country_code != '' -- Use IS NOT NULL for robustness
        AND country_code NOT IN ('XX', 'LO')
        GROUP BY country_code, country_name
        ORDER BY views DESC
        LIMIT 20
    ", $post_id));

    // Get post title
    $post_title = get_the_title($post_id);
    if (!$post_title) {
        $post_title = __('Content not found', 'sky360'); // Fallback title
    }

    // Build HTML response
    ob_start();
    ?>
    <div class="sky-seo-post-details">
        <h3><?php echo esc_html($post_title); ?></h3>
        <div class="sky-seo-country-breakdown">
            <h4><?php _e('Traffic by Country', 'sky360'); ?></h4>
            <?php if (!empty($countries)) : ?>
                <table class="sky-seo-country-table">
                    <thead>
                        <tr>
                            <th><?php _e('Country', 'sky360'); ?></th>
                            <th><?php _e('Views', 'sky360'); ?></th>
                            <th><?php _e('Percentage', 'sky360'); ?></th>
                        </tr>
                    </thead>
                    <tbody>
                        <?php
                        $total_views = array_sum(array_column($countries, 'views'));
                        foreach ($countries as $country) :
                            $percentage = ($country->views / max($total_views, 1)) * 100;
                            $country_name = $country->country_name ?: __('Unknown', 'sky360');
                            ?>
                            <tr>
                                <td>
                                    <div class="sky-seo-country-info">
                                        <span class="sky-seo-country-flag"><?php echo sky_seo_get_country_flag($country->country_code); ?></span>
                                        <span class="sky-seo-country-name"><?php echo esc_html($country_name); ?></span>
                                    </div>
                                </td>
                                <td class="sky-seo-country-views"><?php echo number_format_i18n($country->views); ?></td>
                                <td class="sky-seo-country-percentage">
                                    <div class="sky-seo-percentage-bar">
                                        <div class="sky-seo-percentage-fill" style="width: <?php echo esc_attr(str_replace(',', '.', $percentage)); ?>%"></div> <?php // Use str_replace for float values in style ?>
                                        <span class="sky-seo-percentage-text"><?php echo number_format_i18n($percentage, 1); ?>%</span>
                                    </div>
                                </td>
                            </tr>
                        <?php endforeach; ?>
                    </tbody>
                </table>
            <?php else : ?>
                <p class="sky-seo-no-data"><?php _e('No country data available for this post.', 'sky360'); ?></p>
            <?php endif; ?>
        </div>
    </div>
    <?php
    $html = ob_get_clean();

    wp_send_json_success(['html' => $html]);
}

// Get country flag emoji
function sky_seo_get_country_flag($country_code) {
    if (strlen($country_code) !== 2) {
        return '🌐'; // Default to globe if invalid code
    }

    $country_code = strtoupper($country_code);

    // Handle special cases (e.g., Unknown, Localhost)
    if ($country_code === 'XX' || $country_code === 'LO' || $country_code === 'ZZ') { // ZZ is often used for unknown
        return '🌐';
    }

    // Convert country code to flag emoji
    $offset = 0x1F1E6 - ord('A');
    $flag = mb_chr(ord($country_code[0]) + $offset, 'UTF-8') .
            mb_chr(ord($country_code[1]) + $offset, 'UTF-8');

    return $flag;
}

// Get geographic data for chart
function sky_seo_get_geographic_data($start_date, $end_date, $post_type = 'all', $human_only = true) {
    global $wpdb;
    $clicks_table = $wpdb->prefix . 'sky_seo_clicks';
    $posts_table = $wpdb->prefix . 'posts';

    $where_date = '';
    if ($start_date && $end_date) {
        $where_date = $wpdb->prepare(" AND c.click_time BETWEEN %s AND %s",
            $start_date . ' 00:00:00',
            $end_date . ' 23:59:59'
        );
    }

    $where_post_type = '';
    // Apply post type filter only if it's not 'all'
    if ($post_type !== 'all') {
        $where_post_type = $wpdb->prepare(" AND p.post_type = %s", $post_type);
    } else {
        // If 'all' is selected, include all tracked post types
        $tracked_types = sky_seo_get_tracked_post_types();
        if (!empty($tracked_types)) {
            $placeholders = implode(',', array_fill(0, count($tracked_types), '%s'));
            $where_post_type = $wpdb->prepare(" AND p.post_type IN ($placeholders)", ...$tracked_types);
        } else {
            $where_post_type = " AND 1=0 "; // No tracked types, no results
        }
    }

    $where_bot = $human_only ? " AND (c.is_bot = 0 OR c.is_bot IS NULL)" : "";

    // Get top countries
    $countries = $wpdb->get_results("
        SELECT
            country_name,
            country_code,
            SUM(c.clicks) as total_clicks
        FROM $clicks_table c
        LEFT JOIN $posts_table p ON c.post_id = p.ID
        WHERE country_code IS NOT NULL AND country_code != ''
        AND country_code NOT IN ('XX', 'LO', 'ZZ')
        $where_date
        $where_post_type
        $where_bot
        GROUP BY country_code, country_name
        ORDER BY total_clicks DESC
        LIMIT 10
    ");

    // Get top cities
    $cities = $wpdb->get_results("
        SELECT
            city_name,
            country_name,
            country_code,
            SUM(c.clicks) as total_clicks
        FROM $clicks_table c
        LEFT JOIN $posts_table p ON c.post_id = p.ID
        WHERE city_name IS NOT NULL AND city_name != ''
        AND city_name != 'Unknown'
        AND country_code NOT IN ('XX', 'LO', 'ZZ')
        $where_date
        $where_post_type
        $where_bot
        GROUP BY city_name, country_code
        ORDER BY total_clicks DESC
        LIMIT 15
    ");

    return [
        'countries' => $countries,
        'cities' => $cities
    ];
}

function sky_seo_get_date_range($preset) {
    $today = current_time('Y-m-d');
    $dates = ['start' => '', 'end' => $today];

    switch ($preset) {
        case 'today':
            $dates['start'] = $today;
            break;
        case 'yesterday':
            $dates['start'] = date('Y-m-d', strtotime('-1 day'));
            $dates['end'] = date('Y-m-d', strtotime('-1 day'));
            break;
        case 'last_7_days':
            $dates['start'] = date('Y-m-d', strtotime('-6 days'));
            break;
        case 'last_30_days':
            $dates['start'] = date('Y-m-d', strtotime('-29 days'));
            break;
        case 'last_90_days':
            $dates['start'] = date('Y-m-d', strtotime('-89 days'));
            break;
        case 'this_month':
            $dates['start'] = date('Y-m-01');
            break;
        case 'last_month':
            $dates['start'] = date('Y-m-01', strtotime('first day of last month'));
            $dates['end'] = date('Y-m-t', strtotime('last day of last month'));
            break;
        // 'custom' case is handled by direct $_GET parameters
    }

    return $dates;
}

function sky_seo_get_comparison_dates($start, $end) {
    $start_timestamp = strtotime($start);
    $end_timestamp = strtotime($end);
    $duration_in_seconds = $end_timestamp - $start_timestamp;
    $duration_in_days = floor($duration_in_seconds / (24 * 60 * 60)) + 1; // +1 to include both start and end days

    // The comparison period should be the same length as the current period,
    // ending one day before the current period starts.
    $compare_end_timestamp = $start_timestamp - (24 * 60 * 60); // One day before current period starts
    $compare_start_timestamp = $compare_end_timestamp - $duration_in_seconds; // Same duration backwards

    return [
        'start' => date('Y-m-d', $compare_start_timestamp),
        'end' => date('Y-m-d', $compare_end_timestamp)
    ];
}

function sky_seo_get_analytics_data($start_date, $end_date, $post_type = 'all', $human_only = true) {
    global $wpdb;
    $clicks_table = $wpdb->prefix . 'sky_seo_clicks';
    $posts_table = $wpdb->prefix . 'posts';

    $where_date = '';
    if ($start_date && $end_date) {
        $where_date = $wpdb->prepare(" AND c.click_time BETWEEN %s AND %s",
            $start_date . ' 00:00:00',
            $end_date . ' 23:59:59'
        );
    }

    $where_post_type = '';
    // Apply post type filter only if it's not 'all'
    if ($post_type !== 'all') {
        $where_post_type = $wpdb->prepare(" AND p.post_type = %s", $post_type);
    } else {
        // If 'all' is selected, include all tracked post types
        $tracked_types = sky_seo_get_tracked_post_types();
        if (!empty($tracked_types)) {
            $placeholders = implode(',', array_fill(0, count($tracked_types), '%s'));
            $where_post_type = $wpdb->prepare(" AND p.post_type IN ($placeholders)", ...$tracked_types);
        } else {
            $where_post_type = " AND 1=0 "; // No tracked types, no results
        }
    }

    // Add bot filter
    $where_bot = $human_only ? " AND (c.is_bot = 0 OR c.is_bot IS NULL)" : "";

    // Get aggregated stats
    $stats = $wpdb->get_row("
        SELECT
            SUM(c.clicks) as total_clicks,
            SUM(c.google_clicks) as google_clicks,
            SUM(c.social_clicks) as social_clicks,
            SUM(c.direct_clicks) as direct_clicks,
            COUNT(DISTINCT c.post_id) as unique_posts
        FROM $clicks_table c
        LEFT JOIN $posts_table p ON c.post_id = p.ID
        WHERE 1=1 $where_date $where_post_type $where_bot
    ");

    $data = [
        'total_clicks' => isset($stats->total_clicks) ? (int)$stats->total_clicks : 0,
        'google_clicks' => isset($stats->google_clicks) ? (int)$stats->google_clicks : 0,
        'social_clicks' => isset($stats->social_clicks) ? (int)$stats->social_clicks : 0,
        'direct_clicks' => isset($stats->direct_clicks) ? (int)$stats->direct_clicks : 0,
        'unique_posts' => isset($stats->unique_posts) ? (int)$stats->unique_posts : 0,
        'avg_clicks' => (isset($stats->total_clicks) && $stats->total_clicks > 0 && isset($stats->unique_posts) && $stats->unique_posts > 0) ? round($stats->total_clicks / $stats->unique_posts, 1) : 0,
    ];

    // Traffic sources for pie chart
    $data['traffic_sources'] = [
        ['label' => __('Google', 'sky360'), 'value' => $data['google_clicks'], 'color' => '#4285F4'],
        ['label' => __('Social', 'sky360'), 'value' => $data['social_clicks'], 'color' => '#8B5CF6'],
        ['label' => __('Direct', 'sky360'), 'value' => $data['direct_clicks'], 'color' => '#F59E0B'],
    ];

    // Content types performance
    $tracked_types_with_data = sky_seo_get_tracked_post_types();
    if (!empty($tracked_types_with_data)) {
        $placeholders = implode(',', array_fill(0, count($tracked_types_with_data), '%s'));
        $content_stats = $wpdb->get_results($wpdb->prepare("
            SELECT
                p.post_type,
                SUM(c.clicks) as total_clicks
            FROM $clicks_table c
            LEFT JOIN $posts_table p ON c.post_id = p.ID
            WHERE p.post_type IN ($placeholders) $where_date $where_bot
            GROUP BY p.post_type
        ", ...$tracked_types_with_data));

        $data['content_types'] = [];
        foreach ($content_stats as $stat) {
            $data['content_types'][] = [
                'label' => sky_seo_get_post_type_label($stat->post_type),
                'value' => (int)$stat->total_clicks,
                'color' => sky_seo_get_post_type_color($stat->post_type)
            ];
        }
    } else {
        $data['content_types'] = [];
    }


    // Daily trends
    $data['daily_trends'] = sky_seo_get_daily_trends($start_date, $end_date, $where_post_type, $where_bot);

    // Sparkline data (last 7 data points)
    $data['sparkline_clicks'] = array_slice(array_column($data['daily_trends'], 'total'), -7);
    $data['sparkline_unique'] = array_slice(array_column($data['daily_trends'], 'unique'), -7);
    $data['sparkline_avg'] = array_slice(array_column($data['daily_trends'], 'avg'), -7);
    $data['sparkline_growth'] = sky_seo_calculate_growth_trend($data['sparkline_clicks']);

    // Calculate growth rate against previous period
    $previous_period = sky_seo_get_comparison_dates($start_date, $end_date);
    $previous_stats = $wpdb->get_var("
        SELECT SUM(c.clicks)
        FROM {$clicks_table} c
        LEFT JOIN {$posts_table} p ON c.post_id = p.ID
        WHERE c.click_time BETWEEN '{$previous_period['start']} 00:00:00' AND '{$previous_period['end']} 23:59:59'
        {$where_post_type} {$where_bot}
    ");

    $previous_total_clicks = (int)$previous_stats; // Ensure it's an integer
    $data['growth_rate'] = $previous_total_clicks > 0
        ? round((($data['total_clicks'] - $previous_total_clicks) / $previous_total_clicks) * 100, 1)
        : 0; // If previous is 0, growth is 0 unless current is >0 then it's infinite, handle as 0 or 100%

    // If previous was 0 and current is and current is > 0, set growth rate to 100% (or some indicator)
    if ($previous_total_clicks === 0 && $data['total_clicks'] > 0) {
        $data['growth_rate'] = 100; // Or a very large number, or 'Inf'
    }


    return $data;
}

function sky_seo_get_daily_trends($start_date, $end_date, $where_post_type = '', $where_bot = '') {
    global $wpdb;
    $clicks_table = $wpdb->prefix . 'sky_seo_clicks';
    $posts_table = $wpdb->prefix . 'posts';

    $daily_data = $wpdb->get_results("
        SELECT
            DATE(c.click_time) as date,
            SUM(c.clicks) as total,
            SUM(c.google_clicks) as google,
            SUM(c.social_clicks) as social,
            SUM(c.direct_clicks) as direct,
            COUNT(DISTINCT c.post_id) as unique_posts
        FROM $clicks_table c
        LEFT JOIN $posts_table p ON c.post_id = p.ID
        WHERE c.click_time BETWEEN '$start_date 00:00:00' AND '$end_date 23:59:59'
        $where_post_type
        $where_bot
        GROUP BY DATE(c.click_time)
        ORDER BY date ASC
    ");

    $trends = [];
    foreach ($daily_data as $day) {
        $trends[] = [
            'date' => $day->date,
            'total' => intval($day->total),
            'google' => intval($day->google),
            'social' => intval($day->social),
            'direct' => intval($day->direct),
            'unique' => intval($day->unique_posts),
            'avg' => $day->unique_posts > 0 ? round($day->total / $day->unique_posts, 1) : 0
        ];
    }

    return $trends;
}

function sky_seo_calculate_changes($current, $previous) {
    if (!$previous) return ['total_clicks' => null, 'unique_posts' => null, 'avg_clicks' => null];

    $changes = [];
    $metrics = ['total_clicks', 'unique_posts', 'avg_clicks'];

    foreach ($metrics as $metric) {
        $current_value = (float)$current[$metric];
        $previous_value = (float)$previous[$metric];

        if ($previous_value > 0) {
            $changes[$metric] = round((($current_value - $previous_value) / $previous_value) * 100, 1);
        } else {
            // If previous value is 0:
            // If current value is also 0, change is 0%.
            // If current value is > 0, the change is considered 100% or effectively infinite.
            $changes[$metric] = $current_value > 0 ? 100 : 0; // Set to 100% for an increase from zero
        }
    }

    return $changes;
}

// Updated function with pagination and human/bot filter
function sky_seo_get_top_content($start_date, $end_date, $post_type, $limit = 20, $page = 1, $human_only = true) {
    global $wpdb;
    $clicks_table = $wpdb->prefix . 'sky_seo_clicks';
    $posts_table = $wpdb->prefix . 'posts';
    $offset = ($page - 1) * $limit;

    $where_date = '';
    if ($start_date && $end_date) {
        $where_date = $wpdb->prepare(" AND c.click_time BETWEEN %s AND %s",
            $start_date . ' 00:00:00',
            $end_date . ' 23:59:59'
        );
    }

    $where_post_type = '';
    // Apply post type filter only if it's not 'all'
    if ($post_type !== 'all') {
        $where_post_type = $wpdb->prepare(" AND p.post_type = %s", $post_type);
    } else {
        // If 'all' is selected, include all tracked post types
        $tracked_types = sky_seo_get_tracked_post_types();
        if (!empty($tracked_types)) {
            $placeholders = implode(',', array_fill(0, count($tracked_types), '%s'));
            $where_post_type = $wpdb->prepare(" AND p.post_type IN ($placeholders)", ...$tracked_types);
        } else {
            $where_post_type = " AND 1=0 "; // No tracked types, no results
        }
    }

    // Add bot filter
    $where_bot = $human_only ? " AND (c.is_bot = 0 OR c.is_bot IS NULL)" : "";

    $query = $wpdb->prepare("
        SELECT
            p.ID,
            p.post_title,
            p.post_type,
            SUM(c.clicks) as total_clicks,
            SUM(c.google_clicks) as google_clicks,
            SUM(c.social_clicks) as social_clicks,
            SUM(c.direct_clicks) as direct_clicks
        FROM $posts_table p
        LEFT JOIN $clicks_table c ON p.ID = c.post_id
        WHERE p.post_status = 'publish'
        $where_post_type
        $where_date
        $where_bot
        GROUP BY p.ID
        HAVING total_clicks > 0
        ORDER BY total_clicks DESC
        LIMIT %d OFFSET %d
    ", $limit, $offset);

    // Error handling - if $where_post_type made the query invalid, $wpdb->prepare might return false or null results
    // so it's good to check if($query) before executing
    if ($query === false) {
        return []; // Return empty array if query preparation failed
    }

    return $wpdb->get_results($query);
}

// Updated function to get content count with human/bot filter
function sky_seo_get_content_count($start_date, $end_date, $post_type, $human_only = true) {
    global $wpdb;
    $clicks_table = $wpdb->prefix . 'sky_seo_clicks';
    $posts_table = $wpdb->prefix . 'posts';

    $where_date = '';
    if ($start_date && $end_date) {
        $where_date = $wpdb->prepare(" AND c.click_time BETWEEN %s AND %s",
            $start_date . ' 00:00:00',
            $end_date . ' 23:59:59'
        );
    }

    $where_post_type = '';
    // Apply post type filter only if it's not 'all'
    if ($post_type !== 'all') {
        $where_post_type = $wpdb->prepare(" AND p.post_type = %s", $post_type);
    } else {
        // If 'all' is selected, include all tracked post types
        $tracked_types = sky_seo_get_tracked_post_types();
        if (!empty($tracked_types)) {
            $placeholders = implode(',', array_fill(0, count($tracked_types), '%s'));
            $where_post_type = $wpdb->prepare(" AND p.post_type IN ($placeholders)", ...$tracked_types);
        } else {
            $where_post_type = " AND 1=0 "; // No tracked types, no results
        }
    }

    // Add bot filter
    $where_bot = $human_only ? " AND (c.is_bot = 0 OR c.is_bot IS NULL)" : "";

    $query = "
        SELECT COUNT(DISTINCT p.ID)
        FROM $posts_table p
        LEFT JOIN $clicks_table c ON p.ID = c.post_id
        WHERE p.post_status = 'publish'
        $where_post_type
        $where_date
        $where_bot
        AND c.clicks > 0
    ";

    return (int)$wpdb->get_var($query); // Cast to int to ensure numeric return
}

function sky_seo_get_content_trend($post_id, $start_date, $end_date, $human_only = true) {
    global $wpdb;
    $clicks_table = $wpdb->prefix . 'sky_seo_clicks';
    // Add bot filter
    $where_bot = $human_only ? " AND (is_bot = 0 OR is_bot IS NULL)" : "";

    $trend_data = $wpdb->get_results($wpdb->prepare("
        SELECT
            DATE(click_time) as date,
            SUM(clicks) as clicks
        FROM $clicks_table
        WHERE post_id = %d
        AND click_time BETWEEN %s AND %s
        $where_bot
        GROUP BY DATE(click_time)
        ORDER BY date ASC
        -- LIMIT 7 is here, but if the date range is very short, it might return less than 7 days
        -- This is typically fine for sparklines, which adapt to number of points
    ", $post_id, $start_date . ' 00:00:00', $end_date . ' 23:59:59'));

    return array_column($trend_data, 'clicks');
}

function sky_seo_get_post_type_label($post_type) {
    $labels = [
        'sky_areas' => __('Area', 'sky360'),
        'sky_trending' => __('Trending', 'sky360'),
        'sky_sectors' => __('Sector', 'sky360'),
        'page' => __('Page', 'sky360'),
        'post' => __('Post', 'sky360'),
        'product' => __('Product', 'sky360'),
    ];
    // Corrected array access
    return isset($labels[$post_type]) ? $labels[$post_type] : ucfirst($post_type); // Default to capitalized post type if not found
}

function sky_seo_get_post_type_color($post_type) {
    $colors = [
        'sky_areas' => '#3B82F6',
        'sky_trending' => '#10B981',
        'sky_sectors' => '#F59E0B',
        'page' => '#8B5CF6',
        'post' => '#EF4444',
        'product' => '#EC4899',
    ];
    // Corrected array access
    return isset($colors[$post_type]) ? $colors[$post_type] : '#6B7280'; // Default color
}

function sky_seo_calculate_growth_trend($data) {
    $trend = [];
    // Corrected variable access and loop
    for ($i = 0; $i < count($data); $i++) {
        if ($i === 0) {
            $trend[] = 0; // First point has no previous point for comparison
        } else {
            $previous_value = (float)$data[$i-1];
            $current_value = (float)$data[$i];

            $growth = $previous_value > 0
                ? round((($current_value - $previous_value) / $previous_value) * 100, 1)
                : ($current_value > 0 ? 100 : 0); // If previous is 0 and current > 0, 100% growth

            $trend[] = $growth;
        }
    }
    return $trend;
}

// AJAX handler for real-time updates
function sky_seo_ajax_get_analytics_data() {
    check_ajax_referer('sky_seo_analytics_nonce', 'nonce');

    $start_date = isset($_POST['start_date']) ? sanitize_text_field($_POST['start_date']) : '';
    $end_date = isset($_POST['end_date']) ? sanitize_text_field($_POST['end_date']) : '';
    $post_type = isset($_POST['post_type']) ? sanitize_text_field($_POST['post_type']) : 'all';
    // Convert string 'true'/'false' from JS to boolean
    $human_only = isset($_POST['human_only']) ? filter_var($_POST['human_only'], FILTER_VALIDATE_BOOLEAN) : false;

    // Call the analytics data function with the correct human_only parameter
    $data = sky_seo_get_analytics_data($start_date, $end_date, $post_type, $human_only);

    wp_send_json_success($data);
}

// Export Analytics to CSV with human/bot filter
function sky_seo_export_analytics_csv() {
    // This check is already done in sky_seo_analytics_init() before calling this function.
    // However, including it here provides an extra layer of safety, especially if this function were called directly.
    // check_admin_referer('sky_seo_export_analytics'); // No need to re-check if sky_seo_analytics_init already did.

    $tracked_types = sky_seo_get_tracked_post_types();

    // Use consistent variable names
    $selected_post_type = isset($_GET['post_type_filter']) ? sanitize_text_field($_GET['post_type_filter']) : 'all';
    $start_date = isset($_GET['start_date']) ? sanitize_text_field($_GET['start_date']) : '';
    $end_date = isset($_GET['end_date']) ? sanitize_text_field($_GET['end_date']) : '';
    // Ensure boolean conversion for show_all_traffic
    $show_all_traffic = isset($_GET['show_all_traffic']) ? filter_var($_GET['show_all_traffic'], FILTER_VALIDATE_BOOLEAN) : false;

    global $wpdb;
    $clicks_table = $wpdb->prefix . 'sky_seo_clicks';
    $posts_table = $wpdb->prefix . 'posts';

    $where_date = '';
    if ($start_date && $end_date) {
        $start = date('Y-m-d 00:00:00', strtotime($start_date));
        $end = date('Y-m-d 23:59:59', strtotime($end_date));
        $where_date = $wpdb->prepare(" AND c.click_time BETWEEN %s AND %s", $start, $end);
    }

    $where_post_type = '';
    // Apply post type filter only if it's not 'all'
    if ($selected_post_type !== 'all') {
        $where_post_type = $wpdb->prepare(" AND p.post_type = %s", $selected_post_type);
    } else {
        // If 'all' is selected, include all tracked post types
        if (!empty($tracked_types)) {
            $placeholders = implode(',', array_fill(0, count($tracked_types), '%s'));
            $where_post_type = $wpdb->prepare(" AND p.post_type IN ($placeholders)", ...$tracked_types);
        } else {
            $where_post_type = " AND 1=0 "; // No tracked types, no results
        }
    }

    // Add bot filter: if show_all_traffic is FALSE, then we only want human traffic
    $where_bot = !$show_all_traffic ? " AND (c.is_bot = 0 OR c.is_bot IS NULL)" : "";

    $query = "
        SELECT p.post_title, p.post_type,
            SUM(c.clicks) as total_clicks,
            SUM(c.google_clicks) as google_clicks,
            SUM(c.social_clicks) as social_clicks,
            SUM(c.direct_clicks) as direct_clicks,
            SUM(CASE WHEN c.is_bot = 0 OR c.is_bot IS NULL THEN c.clicks ELSE 0 END) as human_clicks,
            SUM(CASE WHEN c.is_bot = 1 THEN c.clicks ELSE 0 END) as bot_clicks,
            SUM(CASE WHEN c.is_bot = 2 THEN c.clicks ELSE 0 END) as suspicious_clicks,
            MAX(c.referrer_url) as top_referrer
        FROM $posts_table p
        LEFT JOIN $clicks_table c ON p.ID = c.post_id
        WHERE p.post_status = 'publish' $where_post_type $where_date $where_bot
        GROUP BY p.ID
        HAVING SUM(c.clicks) > 0 -- Only include posts with clicks
        ORDER BY total_clicks DESC
    ";

    $results = $wpdb->get_results($query);

    header('Content-Type: text/csv; charset=utf-8');
    header('Content-Disposition: attachment; filename=sky-seo-analytics-' . date('Y-m-d') . '.csv');

    $output = fopen('php://output', 'w');

    // Add BOM for Excel UTF-8 compatibility
    fprintf($output, chr(0xEF).chr(0xBB).chr(0xBF));

    // Headers based on traffic type
    if ($show_all_traffic) {
        fputcsv($output, [
            'Title',
            'Post Type',
            'Total Clicks',
            'Human Clicks',
            'Bot Clicks',
            'Suspicious Clicks',
            'Google Clicks',
            'Social Clicks',
            'Direct Clicks',
            'Top Referrer'
        ]);
    } else {
        // When showing only human traffic, 'Total Clicks' implicitly means human clicks
        fputcsv($output, [
            'Title',
            'Post Type',
            'Total Clicks (Human Only)', // Clarify for the user
            'Google Clicks',
            'Social Clicks',
            'Direct Clicks',
            'Top Referrer'
        ]);
    }

    foreach ($results as $row) {
        $referrer_host = $row->top_referrer ? parse_url($row->top_referrer, PHP_URL_HOST) : '-';
        if ($show_all_traffic) {
            fputcsv($output, [
                $row->post_title,
                sky_seo_get_post_type_label($row->post_type),
                $row->total_clicks ?: 0,
                $row->human_clicks ?: 0,
                $row->bot_clicks ?: 0,
                $row->suspicious_clicks ?: 0,
                $row->google_clicks ?: 0,
                $row->social_clicks ?: 0,
                $row->direct_clicks ?: 0,
                $referrer_host,
            ]);
        } else {
            fputcsv($output, [
                $row->post_title, 
                sky_seo_get_post_type_label($row->post_type),
                $row->total_clicks ?: 0,
                $row->google_clicks ?: 0,
                $row->social_clicks ?: 0,
                $row->direct_clicks ?: 0,
                $referrer_host,
            ]);
        }
    }

    fclose($output);
    exit;
}

// Get top traffic sources
function sky_seo_get_top_traffic_sources($start_date, $end_date, $post_type = 'all', $human_only = true) {
    global $wpdb;
    $clicks_table = $wpdb->prefix . 'sky_seo_clicks';
    $posts_table = $wpdb->prefix . 'posts';
    
    $where_date = '';
    if ($start_date && $end_date) {
        $where_date = $wpdb->prepare(" AND c.click_time BETWEEN %s AND %s",
            $start_date . ' 00:00:00',
            $end_date . ' 23:59:59'
        );
    }
    
    $where_post_type = '';
    if ($post_type !== 'all') {
        $where_post_type = $wpdb->prepare(" AND p.post_type = %s", $post_type);
    } else {
        $tracked_types = sky_seo_get_tracked_post_types();
        if (!empty($tracked_types)) {
            $placeholders = implode(',', array_fill(0, count($tracked_types), '%s'));
            $where_post_type = $wpdb->prepare(" AND p.post_type IN ($placeholders)", ...$tracked_types);
        }
    }
    
    $where_bot = $human_only ? " AND (c.is_bot = 0 OR c.is_bot IS NULL)" : "";
    
    // Get aggregated source data
    $query = "
        SELECT 
            CASE
                -- App Traffic First (exact matches)
                WHEN c.referrer_url = 'whatsapp-app' THEN 'WhatsApp'
                WHEN c.referrer_url = 'facebook-app' THEN 'Facebook App'
                WHEN c.referrer_url = 'instagram-app' THEN 'Instagram App'
                WHEN c.referrer_url = 'tiktok-app' THEN 'TikTok App'
                WHEN c.referrer_url = 'linkedin-app' THEN 'LinkedIn App'
                WHEN c.referrer_url = 'pinterest-app' THEN 'Pinterest App'
                WHEN c.referrer_url = 'twitter-app' THEN 'Twitter/X App'
                WHEN c.referrer_url = 'reddit-app' THEN 'Reddit App'
                
                -- Search Engines
                WHEN c.referrer_url LIKE '%google.%' THEN 'Google'
                WHEN c.referrer_url LIKE '%bing.com%' THEN 'Bing'
                WHEN c.referrer_url LIKE '%yahoo.%' THEN 'Yahoo'
                WHEN c.referrer_url LIKE '%duckduckgo.%' THEN 'DuckDuckGo'
                
                -- Social Media (web links)
                WHEN c.referrer_url LIKE '%facebook.com%' OR c.referrer_url LIKE '%fb.com%' THEN 'Facebook'
                WHEN c.referrer_url LIKE '%instagram.com%' OR c.referrer_url LIKE '%ig.me%' THEN 'Instagram'
                WHEN c.referrer_url LIKE '%twitter.com%' OR c.referrer_url LIKE '%x.com%' OR c.referrer_url LIKE '%t.co%' THEN 'X (Twitter)'
                WHEN c.referrer_url LIKE '%linkedin.com%' OR c.referrer_url LIKE '%lnkd.in%' THEN 'LinkedIn'
                WHEN c.referrer_url LIKE '%pinterest.%' OR c.referrer_url LIKE '%pin.it%' THEN 'Pinterest'
                WHEN c.referrer_url LIKE '%reddit.com%' OR c.referrer_url LIKE '%redd.it%' THEN 'Reddit'
                WHEN c.referrer_url LIKE '%tiktok.com%' OR c.referrer_url LIKE '%vm.tiktok.com%' THEN 'TikTok'
                WHEN c.referrer_url LIKE '%youtube.com%' OR c.referrer_url LIKE '%youtu.be%' THEN 'YouTube'
                WHEN c.referrer_url LIKE '%snapchat.com%' THEN 'Snapchat'
                
                -- Direct Traffic
                WHEN c.referrer_url = '' OR c.referrer_url IS NULL THEN 'Direct'
                
                -- Everything else
                ELSE 'Other'
            END as source_name,
            SUM(c.clicks) as total_clicks,
            COUNT(DISTINCT DATE(c.click_time)) as active_days
        FROM $clicks_table c
        LEFT JOIN $posts_table p ON c.post_id = p.ID
        WHERE 1=1 $where_date $where_post_type $where_bot
        GROUP BY source_name
        ORDER BY total_clicks DESC
        LIMIT 10
    ";
    
    $results = $wpdb->get_results($query);
    
    // Calculate trend for each source
    foreach ($results as &$result) {
        // Simple trend: if active days > 1, it's trending up
        $result->trend = $result->active_days > 1 ? 1 : 0;
    }
    
    return $results;
}

// Get source icon SVG
function sky_seo_get_source_icon($source_name) {
    $icons = [
        'Google' => '<svg width="24" height="24" viewBox="0 0 24 24" fill="none"><path d="M22.56 12.25c0-.78-.07-1.53-.2-2.25H12v4.26h5.92c-.26 1.37-1.04 2.53-2.21 3.31v2.77h3.57c2.08-1.92 3.28-4.74 3.28-8.09z" fill="#4285F4"/><path d="M12 23c2.97 0 5.46-.98 7.28-2.66l-3.57-2.77c-.98.66-2.23 1.06-3.71 1.06-2.86 0-5.29-1.93-6.16-4.53H2.18v2.84C3.99 20.53 7.7 23 12 23z" fill="#34A853"/><path d="M5.84 14.09c-.22-.66-.35-1.36-.35-2.09s.13-1.43.35-2.09V7.07H2.18C1.43 8.55 1 10.22 1 12s.43 3.45 1.18 4.93l2.85-2.22.81-.62z" fill="#FBBC05"/><path d="M12 5.38c1.62 0 3.06.56 4.21 1.64l3.15-3.15C17.45 2.09 14.97 1 12 1 7.7 1 3.99 3.47 2.18 7.07l3.66 2.84c.87-2.6 3.3-4.53 6.16-4.53z" fill="#EA4335"/></svg>',
        
        'YouTube' => '<svg width="24" height="24" viewBox="0 0 24 24" fill="none"><path d="M23.498 6.186a3.016 3.016 0 0 0-2.122-2.136C19.505 3.545 12 3.545 12 3.545s-7.505 0-9.377.505A3.017 3.017 0 0 0 .502 6.186C0 8.07 0 12 0 12s0 3.93.502 5.814a3.016 3.016 0 0 0 2.122 2.136c1.871.505 9.376.505 9.376.505s7.505 0 9.377-.505a3.015 3.015 0 0 0 2.122-2.136C24 15.93 24 12 24 12s0-3.93-.502-5.814zM9.545 15.568V8.432L15.818 12l-6.273 3.568z" fill="#FF0000"/></svg>',
        
        'Facebook' => '<svg width="24" height="24" viewBox="0 0 24 24" fill="none"><path d="M24 12.073c0-6.627-5.373-12-12-12s-12 5.373-12 12c0 5.99 4.388 10.954 10.125 11.854v-8.385H7.078v-3.47h3.047V9.43c0-3.007 1.792-4.669 4.533-4.669 1.312 0 2.686.235 2.686.235v2.953H15.83c-1.491 0-1.956.925-1.956 1.874v2.25h3.328l-.532 3.47h-2.796v8.385C19.612 23.027 24 18.062 24 12.073z" fill="#1877F2"/></svg>',
        
        'Instagram' => '<svg width="24" height="24" viewBox="0 0 24 24" fill="none"><rect x="2" y="2" width="20" height="20" rx="5" ry="5" fill="url(#instagram-gradient)"/><path d="M16 12a4 4 0 1 1-8 0 4 4 0 0 1 8 0z" stroke="white" stroke-width="2"/><circle cx="17.5" cy="6.5" r="1.5" fill="white"/><defs><linearGradient id="instagram-gradient" x1="0%" y1="0%" x2="100%" y2="100%"><stop offset="0%" style="stop-color:#f09433"/><stop offset="25%" style="stop-color:#e6683c"/><stop offset="50%" style="stop-color:#dc2743"/><stop offset="75%" style="stop-color:#cc2366"/><stop offset="100%" style="stop-color:#bc1888"/></linearGradient></defs></svg>',
        
        'X (Twitter)' => '<svg width="24" height="24" viewBox="0 0 24 24" fill="none"><path d="M18.244 2.25h3.308l-7.227 8.26 8.502 11.24H16.17l-5.214-6.817L4.99 21.75H1.68l7.73-8.835L1.254 2.25H8.08l4.713 6.231zm-1.161 17.52h1.833L7.084 4.126H5.117z" fill="#000"/></svg>',
        
        'LinkedIn' => '<svg width="24" height="24" viewBox="0 0 24 24" fill="none"><path d="M20.447 20.452h-3.554v-5.569c0-1.328-.027-3.037-1.852-3.037-1.853 0-2.136 1.445-2.136 2.939v5.667H9.351V9h3.414v1.561h.046c.477-.9 1.637-1.85 3.37-1.85 3.601 0 4.267 2.37 4.267 5.455v6.286zM5.337 7.433c-1.144 0-2.063-.926-2.063-2.065 0-1.138.92-2.063 2.063-2.063 1.14 0 2.064.925 2.064 2.063 0 1.139-.925 2.065-2.064 2.065zm1.782 13.019H3.555V9h3.564v11.452zM22.225 0H1.771C.792 0 0 .774 0 1.729v20.542C0 23.227.792 24 1.771 24h20.451C23.2 24 24 23.227 24 22.271V1.729C24 .774 23.2 0 22.222 0h.003z" fill="#0077B5"/></svg>',
        
        'Pinterest' => '<svg width="24" height="24" viewBox="0 0 24 24" fill="none"><path d="M12 0C5.373 0 0 5.372 0 12c0 5.084 3.163 9.426 7.627 11.174-.105-.949-.2-2.405.042-3.441.218-.937 1.407-5.965 1.407-5.965s-.359-.719-.359-1.782c0-1.668.967-2.914 2.171-2.914 1.023 0 1.518.769 1.518 1.69 0 1.029-.655 2.568-.994 3.995-.283 1.194.599 2.169 1.777 2.169 2.133 0 3.772-2.249 3.772-5.495 0-2.873-2.064-4.882-5.012-4.882-3.414 0-5.418 2.561-5.418 5.207 0 1.031.397 2.138.893 2.738a.36.36 0 01.083.345l-.333 1.36c-.053.22-.174.267-.402.161-1.499-.698-2.436-2.889-2.436-4.649 0-3.785 2.75-7.262 7.929-7.262 4.163 0 7.398 2.967 7.398 6.931 0 4.136-2.607 7.464-6.227 7.464-1.216 0-2.359-.631-2.75-1.378l-.748 2.853c-.271 1.043-1.002 2.35-1.492 3.146C9.57 23.812 10.763 24 12 24c6.627 0 12-5.373 12-12 0-6.628-5.373-12-12-12z" fill="#E60023"/></svg>',
        
        'Reddit' => '<svg width="24" height="24" viewBox="0 0 24 24" fill="none"><path d="M12 0A12 12 0 0 0 0 12a12 12 0 0 0 12 12 12 12 0 0 0 12-12A12 12 0 0 0 12 0zm5.01 4.744c.688 0 1.25.561 1.25 1.249a1.25 1.25 0 0 1-2.498.056l-2.597-.547-.8 3.747c1.824.07 3.48.632 4.674 1.488.308-.309.73-.491 1.207-.491.968 0 1.754.786 1.754 1.754 0 .716-.435 1.333-1.01 1.614a3.111 3.111 0 0 1 .042.52c0 2.694-3.13 4.87-7.004 4.87-3.874 0-7.004-2.176-7.004-4.87 0-.183.015-.366.043-.534A1.748 1.748 0 0 1 4.028 12c0-.968.786-1.754 1.754-1.754.463 0 .898.196 1.207.49 1.207-.883 2.878-1.43 4.744-1.487l.885-4.182a.342.342 0 0 1 .14-.197.35.35 0 0 1 .238-.042l2.906.617a1.214 1.214 0 0 1 1.108-.701zM9.25 12C8.561 12 8 12.562 8 13.25c0 .687.561 1.248 1.25 1.248.687 0 1.248-.561 1.248-1.249 0-.688-.561-1.249-1.249-1.249zm5.5 0c-.687 0-1.248.561-1.248 1.25 0 .687.561 1.248 1.249 1.248.688 0 1.249-.561 1.249-1.249 0-.687-.562-1.249-1.25-1.249zm-5.466 3.99a.327.327 0 0 0-.231.094.33.33 0 0 0 0 .463c.842.842 2.484.913 2.961.913.477 0 2.105-.056 2.961-.913a.361.361 0 0 0 .029-.463.33.33 0 0 0-.464 0c-.547.533-1.684.73-2.512.73-.828 0-1.979-.196-2.512-.73a.326.326 0 0 0-.232-.095z" fill="#FF4500"/></svg>',
        
        'TikTok' => '<svg width="24" height="24" viewBox="0 0 24 24" fill="none"><path d="M12.525.02c1.31-.02 2.61-.01 3.91-.02.08 1.53.63 3.09 1.75 4.17 1.12 1.11 2.7 1.62 4.24 1.79v4.03c-1.44-.05-2.89-.35-4.2-.97-.57-.26-1.1-.59-1.62-.93-.01 2.92.01 5.84-.02 8.75-.08 1.4-.54 2.79-1.35 3.94-1.31 1.92-3.58 3.17-5.91 3.21-1.43.08-2.86-.31-4.08-1.03-2.02-1.19-3.44-3.37-3.65-5.71-.02-.5-.03-1-.01-1.49.18-1.9 1.12-3.72 2.58-4.96 1.66-1.44 3.98-2.13 6.15-1.72.02 1.48-.04 2.96-.04 4.44-.99-.32-2.15-.23-3.02.37-.63.41-1.11 1.04-1.36 1.75-.21.51-.15 1.07-.14 1.61.24 1.64 1.82 3.02 3.5 2.87 1.12-.01 2.19-.66 2.77-1.61.19-.33.4-.67.41-1.06.1-1.79.06-3.57.07-5.36.01-4.03-.01-8.05.02-12.07z" fill="#000"/></svg>',
        
        'Bing' => '<svg width="24" height="24" viewBox="0 0 24 24" fill="none"><path d="M3.605 0l5.919 2.041v17.62l-3.535-2.05-2.384-7.306V0zm5.919 21.024l9.852-5.704-4.463-2.097-.827-2.297-4.562 1.63v8.468z" fill="#00809D"/></svg>',
        
        'Direct' => '<svg width="24" height="24" viewBox="0 0 24 24" fill="none"><path d="M12 2L2 7v10c0 5.55 3.84 10.74 9 12 5.16-1.26 9-6.45 9-12V7l-10-5z" stroke="currentColor" stroke-width="2" fill="none"/><path d="M12 11a2 2 0 100-4 2 2 0 000 4z" stroke="currentColor" stroke-width="2"/><path d="M12 11v6" stroke="currentColor" stroke-width="2"/></svg>',
        
        'Other' => '<svg width="24" height="24" viewBox="0 0 24 24" fill="none"><circle cx="12" cy="12" r="10" stroke="currentColor" stroke-width="2" fill="none"/><path d="M12 16v-4m0-4h.01" stroke="currentColor" stroke-width="2" stroke-linecap="round"/></svg>',
        
        'WhatsApp' => '<svg width="24" height="24" viewBox="0 0 24 24" fill="none"><path d="M17.472 14.382c-.297-.149-1.758-.867-2.03-.967-.273-.099-.471-.148-.67.15-.197.297-.767.966-.94 1.164-.173.199-.347.223-.644.075-.297-.15-1.255-.463-2.39-1.475-.883-.788-1.48-1.761-1.653-2.059-.173-.297-.018-.458.13-.606.134-.133.298-.347.446-.52.149-.174.198-.298.298-.497.099-.198.05-.371-.025-.52-.075-.149-.669-1.612-.916-2.207-.242-.579-.487-.5-.669-.51-.173-.008-.371-.01-.57-.01-.198 0-.52.074-.792.372-.272.297-1.04 1.016-1.04 2.479 0 1.462 1.065 2.875 1.213 3.074.149.198 2.096 3.2 5.077 4.487.709.306 1.262.489 1.693.625.712.227 1.36.195 1.871.118.571-.085 1.758-.719 2.006-1.413.248-.694.248-1.289.173-1.413-.074-.124-.272-.198-.57-.347m-5.421 7.403h-.004a9.87 9.87 0 01-5.031-1.378l-.361-.214-3.741.982.998-3.648-.235-.374a9.86 9.86 0 01-1.51-5.26c.001-5.45 4.436-9.884 9.888-9.884 2.64 0 5.122 1.03 6.988 2.898a9.825 9.825 0 012.893 6.994c-.003 5.45-4.437 9.884-9.885 9.884m8.413-18.297A11.815 11.815 0 0012.05 0C5.495 0 .16 5.335.157 11.892c0 2.096.547 4.142 1.588 5.945L.057 24l6.305-1.654a11.882 11.882 0 005.683 1.448h.005c6.554 0 11.89-5.335 11.893-11.893a11.821 11.821 0 00-3.48-8.413Z" fill="#25D366"/></svg>',
        
        'Facebook App' => '<svg width="24" height="24" viewBox="0 0 24 24" fill="none"><path d="M24 12.073c0-6.627-5.373-12-12-12s-12 5.373-12 12c0 5.99 4.388 10.954 10.125 11.854v-8.385H7.078v-3.47h3.047V9.43c0-3.007 1.792-4.669 4.533-4.669 1.312 0 2.686.235 2.686.235v2.953H15.83c-1.491 0-1.956.925-1.956 1.874v2.25h3.328l-.532 3.47h-2.796v8.385C19.612 23.027 24 18.062 24 12.073z" fill="#1877F2"/><circle cx="18" cy="6" r="3" fill="#FF0000" stroke="#FFF" stroke-width="0.5"/></svg>',
        
        'Instagram App' => '<svg width="24" height="24" viewBox="0 0 24 24" fill="none"><rect x="2" y="2" width="20" height="20" rx="5" ry="5" fill="url(#ig-app-gradient)"/><path d="M16 12a4 4 0 1 1-8 0 4 4 0 0 1 8 0z" stroke="white" stroke-width="2"/><circle cx="17.5" cy="6.5" r="1.5" fill="white"/><circle cx="18" cy="6" r="3" fill="#FF0000" stroke="#FFF" stroke-width="0.5"/><defs><linearGradient id="ig-app-gradient" x1="0%" y1="0%" x2="100%" y2="100%"><stop offset="0%" style="stop-color:#f09433"/><stop offset="25%" style="stop-color:#e6683c"/><stop offset="50%" style="stop-color:#dc2743"/><stop offset="75%" style="stop-color:#cc2366"/><stop offset="100%" style="stop-color:#bc1888"/></linearGradient></defs></svg>',
        
        'TikTok App' => '<svg width="24" height="24" viewBox="0 0 24 24" fill="none"><circle cx="12" cy="12" r="12" fill="#000"/><path d="M12.525 6.02c1.31-.02 2.61-.01 3.91-.02.08 1.53.63 3.09 1.75 4.17 1.12 1.11 2.7 1.62 4.24 1.79v4.03c-1.44-.05-2.89-.35-4.2-.97-.57-.26-1.1-.59-1.62-.93-.01 2.92.01 5.84-.02 8.75-.08 1.4-.54 2.79-1.35 3.94-1.31 1.92-3.58 3.17-5.91 3.21-1.43.08-2.86-.31-4.08-1.03-2.02-1.19-3.44-3.37-3.65-5.71-.02-.5-.03-1-.01-1.49.18-1.9 1.12-3.72 2.58-4.96 1.66-1.44 3.98-2.13 6.15-1.72.02 1.48-.04 2.96-.04 4.44-.99-.32-2.15-.23-3.02.37-.63.41-1.11 1.04-1.36 1.75-.21.51-.15 1.07-.14 1.61.24 1.64 1.82 3.02 3.5 2.87 1.12-.01 2.19-.66 2.77-1.61.19-.33.4-.67.41-1.06.1-1.79.06-3.57.07-5.36.01-4.03-.01-8.05.02-12.07z" fill="#FFF"/></svg>',
        
        'LinkedIn App' => '<svg width="24" height="24" viewBox="0 0 24 24" fill="none"><path d="M20.447 20.452h-3.554v-5.569c0-1.328-.027-3.037-1.852-3.037-1.853 0-2.136 1.445-2.136 2.939v5.667H9.351V9h3.414v1.561h.046c.477-.9 1.637-1.85 3.37-1.85 3.601 0 4.267 2.37 4.267 5.455v6.286zM5.337 7.433c-1.144 0-2.063-.926-2.063-2.065 0-1.138.92-2.063 2.063-2.063 1.14 0 2.064.925 2.064 2.063 0 1.139-.925 2.065-2.064 2.065zm1.782 13.019H3.555V9h3.564v11.452zM22.225 0H1.771C.792 0 0 .774 0 1.729v20.542C0 23.227.792 24 1.771 24h20.451C23.2 24 24 23.227 24 22.271V1.729C24 .774 23.2 0 22.222 0h.003z" fill="#0077B5"/><circle cx="18" cy="6" r="3" fill="#FF0000" stroke="#FFF" stroke-width="0.5"/></svg>',
        
        'Pinterest App' => '<svg width="24" height="24" viewBox="0 0 24 24" fill="none"><path d="M12 0C5.373 0 0 5.372 0 12c0 5.084 3.163 9.426 7.627 11.174-.105-.949-.2-2.405.042-3.441.218-.937 1.407-5.965 1.407-5.965s-.359-.719-.359-1.782c0-1.668.967-2.914 2.171-2.914 1.023 0 1.518.769 1.518 1.69 0 1.029-.655 2.568-.994 3.995-.283 1.194.599 2.169 1.777 2.169 2.133 0 3.772-2.249 3.772-5.495 0-2.873-2.064-4.882-5.012-4.882-3.414 0-5.418 2.561-5.418 5.207 0 1.031.397 2.138.893 2.738a.36.36 0 01.083.345l-.333 1.36c-.053.22-.174.267-.402.161-1.499-.698-2.436-2.889-2.436-4.649 0-3.785 2.75-7.262 7.929-7.262 4.163 0 7.398 2.967 7.398 6.931 0 4.136-2.607 7.464-6.227 7.464-1.216 0-2.359-.631-2.75-1.378l-.748 2.853c-.271 1.043-1.002 2.35-1.492 3.146C9.57 23.812 10.763 24 12 24c6.627 0 12-5.373 12-12 0-6.628-5.373-12-12-12z" fill="#E60023"/><circle cx="18" cy="6" r="3" fill="#FF0000" stroke="#FFF" stroke-width="0.5"/></svg>',
        
        'Twitter/X App' => '<svg width="24" height="24" viewBox="0 0 24 24" fill="none"><path d="M18.244 2.25h3.308l-7.227 8.26 8.502 11.24H16.17l-5.214-6.817L4.99 21.75H1.68l7.73-8.835L1.254 2.25H8.08l4.713 6.231zm-1.161 17.52h1.833L7.084 4.126H5.117z" fill="#000"/><circle cx="18" cy="6" r="3" fill="#FF0000" stroke="#FFF" stroke-width="0.5"/></svg>',
        
        'Reddit App' => '<svg width="24" height="24" viewBox="0 0 24 24" fill="none"><path d="M12 0A12 12 0 0 0 0 12a12 12 0 0 0 12 12 12 12 0 0 0 12-12A12 12 0 0 0 12 0zm5.01 4.744c.688 0 1.25.561 1.25 1.249a1.25 1.25 0 0 1-2.498.056l-2.597-.547-.8 3.747c1.824.07 3.48.632 4.674 1.488.308-.309.73-.491 1.207-.491.968 0 1.754.786 1.754 1.754 0 .716-.435 1.333-1.01 1.614a3.111 3.111 0 0 1 .042.52c0 2.694-3.13 4.87-7.004 4.87-3.874 0-7.004-2.176-7.004-4.87 0-.183.015-.366.043-.534A1.748 1.748 0 0 1 4.028 12c0-.968.786-1.754 1.754-1.754.463 0 .898.196 1.207.49 1.207-.883 2.878-1.43 4.744-1.487l.885-4.182a.342.342 0 0 1 .14-.197.35.35 0 0 1 .238-.042l2.906.617a1.214 1.214 0 0 1 1.108-.701zM9.25 12C8.561 12 8 12.562 8 13.25c0 .687.561 1.248 1.25 1.248.687 0 1.248-.561 1.248-1.249 0-.688-.561-1.249-1.249-1.249zm5.5 0c-.687 0-1.248.561-1.248 1.25 0 .687.561 1.248 1.249 1.248.688 0 1.249-.561 1.249-1.249 0-.687-.562-1.249-1.25-1.249zm-5.466 3.99a.327.327 0 0 0-.231.094.33.33 0 0 0 0 .463c.842.842 2.484.913 2.961.913.477 0 2.105-.056 2.961-.913a.361.361 0 0 0 .029-.463.33.33 0 0 0-.464 0c-.547.533-1.684.73-2.512.73-.828 0-1.979-.196-2.512-.73a.326.326 0 0 0-.232-.095z" fill="#FF4500"/><circle cx="18" cy="6" r="3" fill="#FF0000" stroke="#FFF" stroke-width="0.5"/></svg>'
        
    ];
    
    return isset($icons[$source_name]) ? $icons[$source_name] : $icons['Other'];
}

// Get source color class
function sky_seo_get_source_color_class($source_name) {
    $classes = [
        'Google' => 'source-google',
        'YouTube' => 'source-youtube',
        'Facebook' => 'source-facebook',
        'Instagram' => 'source-instagram',
        'X (Twitter)' => 'source-twitter',
        'LinkedIn' => 'source-linkedin',
        'Pinterest' => 'source-pinterest',
        'Reddit' => 'source-reddit',
        'TikTok' => 'source-tiktok',
        'Bing' => 'source-bing',
        'Direct' => 'source-direct',
        'Other' => 'source-other'
    ];
    
    return isset($classes[$source_name]) ? $classes[$source_name] : 'source-other';
}