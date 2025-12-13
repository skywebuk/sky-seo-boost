<?php
/**
 * Sky SEO Boost - WhatsApp Business Tracking
 * 
 * Handles tracking and analytics for WhatsApp clicks
 * 
 * @package Sky_SEO_Boost
 * @subpackage WhatsApp_Business
 * @version 1.2.6
 * @since 3.2.0
 */

// Prevent direct access
if (!defined('ABSPATH')) {
    exit;
}

/**
 * WhatsApp Business Tracking Class
 */
class Sky_SEO_WhatsApp_Tracking {
    
    /**
     * Instance
     */
    private static $instance = null;
    
    /**
     * Table name
     */
    private $table_name;
    
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
        $this->table_name = $wpdb->prefix . 'sky_seo_whatsapp_tracking';
        
        // Add handler for trackable link redirects
        add_action('init', [$this, 'handle_trackable_link_redirect']);
    }
    
    /**
     * Handle trackable link redirect
     */
    public function handle_trackable_link_redirect() {
        if (isset($_GET['sky_whatsapp_redirect']) && $_GET['sky_whatsapp_redirect'] == '1') {
            $tracking_id = isset($_GET['tid']) ? sanitize_text_field($_GET['tid']) : '';
            $source = isset($_GET['source']) ? sanitize_text_field($_GET['source']) : '';
            
            // Get WhatsApp settings
            $settings = get_option('sky_seo_whatsapp_config', []);
            $phone = isset($settings['phone']) ? $settings['phone'] : '';
            
            if (empty($phone)) {
                wp_die('WhatsApp not configured');
            }
            
            // Get tracking info from transient
            $tracking_info = get_transient('sky_whatsapp_link_' . $tracking_id);
            
            // Track the click
            $this->track_click_internal('link', $source);
            
            // Build WhatsApp URL
            $message = '';
            if ($tracking_info && isset($tracking_info['message'])) {
                $message = $tracking_info['message'];
            } elseif (isset($settings['default_message'])) {
                $message = $settings['default_message'];
            }
            
            $whatsapp_url = add_query_arg([
                'phone' => preg_replace('/[^0-9+]/', '', $phone),
                'text' => urlencode($message),
            ], 'https://wa.me/');
            
            // Redirect to WhatsApp
            wp_redirect($whatsapp_url);
            exit;
        }
    }
    
    /**
     * Track click internally (public for main class access)
     */
    public function track_click_internal($click_type = 'widget', $source = '') {
        global $wpdb;
        
        try {
            // Check if table exists
            $table_exists = $wpdb->get_var("SHOW TABLES LIKE '{$this->table_name}'") === $this->table_name;
            if (!$table_exists) {
                error_log('WhatsApp tracking table does not exist');
                return false;
            }
            
            // Get user data
            $user_agent = isset($_SERVER['HTTP_USER_AGENT']) ? sanitize_text_field($_SERVER['HTTP_USER_AGENT']) : '';
            $ip_address = $this->get_user_ip();
            
            // Get device info
            $device_info = $this->get_device_info($user_agent);
            
            // Get location info with improved handling
            $location = $this->get_location_by_ip($ip_address);
            
            // Generate session ID
            $session_id = $this->get_or_create_session_id();
            
            // Get user ID if logged in
            $user_id = get_current_user_id();
            
            // Get page info from POST data if available (for button clicks)
            $page_url = isset($_POST['page_url']) ? esc_url_raw($_POST['page_url']) : (isset($_SERVER['HTTP_REFERER']) ? esc_url_raw($_SERVER['HTTP_REFERER']) : home_url());
            $page_title = isset($_POST['page_title']) ? sanitize_text_field($_POST['page_title']) : '';
            
            // If no page title provided, try to get it
            if (empty($page_title)) {
                $page_id = url_to_postid($page_url);
                if ($page_id) {
                    $page_title = get_the_title($page_id);
                }
            }
            
            // Insert tracking data
            $data = [
                'click_time' => current_time('mysql'),
                'ip_address' => $ip_address,
                'country' => $location['country'] ?? '',
                'city' => $location['city'] ?? '',
                'referrer_url' => isset($_POST['referrer']) ? esc_url_raw($_POST['referrer']) : (isset($_SERVER['HTTP_REFERER']) ? esc_url_raw($_SERVER['HTTP_REFERER']) : ''),
                'page_url' => $page_url,
                'page_title' => $page_title,
                'device_type' => $device_info['device_type'],
                'browser' => $device_info['browser'],
                'os' => $device_info['os'],
                'click_type' => $click_type,
                'source' => $source,
                'user_agent' => $user_agent,
                'session_id' => $session_id,
                'user_id' => $user_id ?: null,
            ];
            
            $result = $wpdb->insert($this->table_name, $data);
            
            if ($result === false) {
                error_log('WhatsApp tracking insert failed: ' . $wpdb->last_error);
                return false;
            }
            
            return $wpdb->insert_id;
            
        } catch (Exception $e) {
            error_log('WhatsApp tracking error: ' . $e->getMessage());
            return false;
        }
    }
    
    /**
     * Render tracking page
     */
    public function render_tracking_page() {
        // Check if table exists
        global $wpdb;
        $table_exists = $wpdb->get_var("SHOW TABLES LIKE '{$this->table_name}'") === $this->table_name;
        
        if (!$table_exists) {
            ?>
            <div class="notice notice-error">
                <p><?php _e('WhatsApp tracking table does not exist. Please deactivate and reactivate the plugin.', 'sky-seo-boost'); ?></p>
            </div>
            <?php
            return;
        }
        ?>
        <div class="sky-seo-analytics-dashboard sky-seo-whatsapp-tracking">
            <!-- Dashboard Header -->
            <div class="sky-seo-dashboard-header">
                <div class="sky-header-top">
                    <!-- Date Controls -->
                    <div class="sky-seo-date-controls">
                        <div class="sky-seo-period-selector">
                            <button type="button" data-period="7days">
                                <?php _e('7 Days', 'sky-seo-boost'); ?>
                            </button>
                            <button type="button" data-period="30days">
                                <?php _e('30 Days', 'sky-seo-boost'); ?>
                            </button>
                            <button type="button" data-period="thismonth" class="active">
                                <?php _e('This Month', 'sky-seo-boost'); ?>
                            </button>
                            <button type="button" data-period="lastmonth">
                                <?php _e('Last Month', 'sky-seo-boost'); ?>
                            </button>
                            <button type="button" data-period="90days">
                                <?php _e('90 Days', 'sky-seo-boost'); ?>
                            </button>
                            <button type="button" data-period="custom">
                                <?php _e('Custom', 'sky-seo-boost'); ?>
                            </button>
                        </div>
                    </div>
                </div>
            </div>

            <!-- Metrics Grid -->
            <div class="sky-seo-whatsapp-metrics">
                <!-- Total Clicks -->
                <div class="sky-seo-metric-card total-clicks">
                    <div class="sky-seo-metric-header">
                        <h3 class="sky-seo-metric-title"><?php _e('Total Clicks', 'sky-seo-boost'); ?></h3>
                        <div class="sky-seo-metric-icon">
                            <span class="dashicons dashicons-admin-links"></span>
                        </div>
                    </div>
                    <div class="sky-seo-metric-value">0</div>
                    <div class="sky-seo-metric-change positive">
                        <span class="dashicons dashicons-arrow-up-alt"></span>
                        <span>0%</span>
                    </div>
                    <div class="sky-seo-metric-subtitle"><?php _e('vs previous period', 'sky-seo-boost'); ?></div>
                </div>
                
                <!-- Unique Users -->
                <div class="sky-seo-metric-card unique-users">
                    <div class="sky-seo-metric-header">
                        <h3 class="sky-seo-metric-title"><?php _e('Unique Users', 'sky-seo-boost'); ?></h3>
                        <div class="sky-seo-metric-icon">
                            <span class="dashicons dashicons-groups"></span>
                        </div>
                    </div>
                    <div class="sky-seo-metric-value">0</div>
                    <div class="sky-seo-metric-change positive">
                        <span class="dashicons dashicons-arrow-up-alt"></span>
                        <span>0%</span>
                    </div>
                    <div class="sky-seo-metric-subtitle"><?php _e('unique visitors', 'sky-seo-boost'); ?></div>
                </div>
                
                <!-- Desktop Clicks -->
                <div class="sky-seo-metric-card desktop-clicks">
                    <div class="sky-seo-metric-header">
                        <h3 class="sky-seo-metric-title"><?php _e('Desktop Clicks', 'sky-seo-boost'); ?></h3>
                        <div class="sky-seo-metric-icon">
                            <span class="dashicons dashicons-desktop"></span>
                        </div>
                    </div>
                    <div class="sky-seo-metric-value">0</div>
                    <div class="sky-seo-metric-percentage">0%</div>
                    <div class="sky-seo-metric-subtitle"><?php _e('of total clicks', 'sky-seo-boost'); ?></div>
                </div>
                
                <!-- Mobile Clicks -->
                <div class="sky-seo-metric-card mobile-clicks">
                    <div class="sky-seo-metric-header">
                        <h3 class="sky-seo-metric-title"><?php _e('Mobile Clicks', 'sky-seo-boost'); ?></h3>
                        <div class="sky-seo-metric-icon">
                            <span class="dashicons dashicons-smartphone"></span>
                        </div>
                    </div>
                    <div class="sky-seo-metric-value">0</div>
                    <div class="sky-seo-metric-percentage">0%</div>
                    <div class="sky-seo-metric-subtitle"><?php _e('of total clicks', 'sky-seo-boost'); ?></div>
                </div>
                
                <!-- Widget Clicks -->
                <div class="sky-seo-metric-card widget-clicks">
                    <div class="sky-seo-metric-header">
                        <h3 class="sky-seo-metric-title"><?php _e('Widget Clicks', 'sky-seo-boost'); ?></h3>
                        <div class="sky-seo-metric-icon">
                            <span class="dashicons dashicons-format-chat"></span>
                        </div>
                    </div>
                    <div class="sky-seo-metric-value">0</div>
                    <div class="sky-seo-metric-percentage">0%</div>
                    <div class="sky-seo-metric-subtitle"><?php _e('floating widget', 'sky-seo-boost'); ?></div>
                </div>
                
                <!-- Button Clicks -->
                <div class="sky-seo-metric-card button-clicks">
                    <div class="sky-seo-metric-header">
                        <h3 class="sky-seo-metric-title"><?php _e('Button Clicks', 'sky-seo-boost'); ?></h3>
                        <div class="sky-seo-metric-icon">
                            <span class="dashicons dashicons-admin-links"></span>
                        </div>
                    </div>
                    <div class="sky-seo-metric-value">0</div>
                    <div class="sky-seo-metric-percentage">0%</div>
                    <div class="sky-seo-metric-subtitle"><?php _e('button links', 'sky-seo-boost'); ?></div>
                </div>
            </div>

            <!-- Click Heatmap -->
            <div class="sky-seo-heatmap-section">
                <div class="sky-seo-heatmap-header">
                    <h3 class="sky-seo-heatmap-title"><?php _e('Click Activity Heatmap', 'sky-seo-boost'); ?></h3>
                    <p class="sky-seo-heatmap-subtitle"><?php _e('See when your users are most active', 'sky-seo-boost'); ?></p>
                </div>
                <div class="sky-seo-heatmap-grid">
                    <!-- Populated by JS -->
                </div>
                <div class="sky-seo-heatmap-legend">
                    <span class="sky-seo-legend-item">
                        <span class="sky-seo-legend-color" style="background: #f9fafb;"></span>
                        <?php _e('Low', 'sky-seo-boost'); ?>
                    </span>
                    <span class="sky-seo-legend-item">
                        <span class="sky-seo-legend-color" style="background: #86efac;"></span>
                        <?php _e('Medium', 'sky-seo-boost'); ?>
                    </span>
                    <span class="sky-seo-legend-item">
                        <span class="sky-seo-legend-color" style="background: #22c55e;"></span>
                        <?php _e('High', 'sky-seo-boost'); ?>
                    </span>
                    <span class="sky-seo-legend-item">
                        <span class="sky-seo-legend-color" style="background: #16a34a;"></span>
                        <?php _e('Very High', 'sky-seo-boost'); ?>
                    </span>
                </div>
            </div>

            <!-- Pages Summary Table -->
            <div class="sky-seo-conversations-section">
                <div class="sky-seo-conversations-header">
                    <h3 class="sky-seo-conversations-title"><?php _e('Page Performance', 'sky-seo-boost'); ?></h3>
                    <div class="sky-seo-table-filters">
                        <div class="sky-seo-search-box">
                            <span class="dashicons dashicons-search sky-seo-search-icon"></span>
                            <input type="text" 
                                   id="conversation-search" 
                                   class="sky-seo-search-input" 
                                   placeholder="<?php _e('Search pages...', 'sky-seo-boost'); ?>">
                        </div>
                        <select class="sky-seo-filter-dropdown" data-filter="device">
                            <option value="all"><?php _e('All Devices', 'sky-seo-boost'); ?></option>
                            <option value="mobile"><?php _e('Mobile', 'sky-seo-boost'); ?></option>
                            <option value="desktop"><?php _e('Desktop', 'sky-seo-boost'); ?></option>
                        </select>
                        <select class="sky-seo-filter-dropdown" data-filter="country">
                            <option value="all"><?php _e('All Countries', 'sky-seo-boost'); ?></option>
                        </select>
                    </div>
                </div>
                
                <table class="sky-seo-conversations-table">
                    <thead>
                        <tr>
                            <th class="sortable" data-sort="page_title"><?php _e('Page Title', 'sky-seo-boost'); ?></th>
                            <th class="sortable" data-sort="total_clicks"><?php _e('Total Clicks', 'sky-seo-boost'); ?></th>
                            <th><?php _e('Widget', 'sky-seo-boost'); ?></th>
                            <th><?php _e('Button', 'sky-seo-boost'); ?></th>
                            <th><?php _e('Top City', 'sky-seo-boost'); ?></th>
                            <th class="sortable" data-sort="last_click"><?php _e('Last Activity', 'sky-seo-boost'); ?></th>
                            <th><?php _e('Actions', 'sky-seo-boost'); ?></th>
                        </tr>
                    </thead>
                    <tbody>
                        <!-- Populated by JS -->
                    </tbody>
                </table>
                
                <div class="sky-seo-table-pagination">
                    <div class="sky-seo-pagination-info">
                        <?php _e('Showing 0 pages', 'sky-seo-boost'); ?>
                    </div>
                    <div class="sky-seo-pagination-controls">
                        <!-- Populated by JS -->
                    </div>
                </div>
                
                <div class="sky-seo-export-controls">
                    <a href="#" class="sky-seo-export-button" data-format="csv">
                        <span class="dashicons dashicons-download"></span>
                        <?php _e('Export CSV', 'sky-seo-boost'); ?>
                    </a>
                    <a href="#" class="sky-seo-export-button" data-format="excel">
                        <span class="dashicons dashicons-media-spreadsheet"></span>
                        <?php _e('Export Excel', 'sky-seo-boost'); ?>
                    </a>
                </div>
            </div>
        </div>
        
        <!-- Details Modal HTML -->
        <div id="sky-whatsapp-details-modal" class="sky-modal" style="display: none;">
            <div class="sky-modal-content">
                <div class="sky-modal-header">
                    <h3 class="sky-modal-title"><?php _e('Page Details', 'sky-seo-boost'); ?></h3>
                    <button class="sky-modal-close">&times;</button>
                </div>
                <div class="sky-modal-body">
                    <!-- Content will be loaded via AJAX -->
                </div>
            </div>
        </div>
        <?php
    }
    
    /**
     * AJAX handler for tracking data
     */
    public function ajax_get_tracking_data() {
        // Verify nonce
        if (!check_ajax_referer('sky_seo_whatsapp_nonce', 'nonce', false)) {
            wp_send_json_error(['message' => __('Security check failed', 'sky-seo-boost')]);
            return;
        }
        
        // Check permissions
        if (!current_user_can('manage_options')) {
            wp_send_json_error(['message' => __('Insufficient permissions', 'sky-seo-boost')]);
            return;
        }
        
        try {
            // Get filters
            $filters = [
                'dateRange' => sanitize_text_field($_POST['filters']['dateRange'] ?? 'thismonth'),
                'customStartDate' => sanitize_text_field($_POST['filters']['customStartDate'] ?? ''),
                'customEndDate' => sanitize_text_field($_POST['filters']['customEndDate'] ?? ''),
                'search' => sanitize_text_field($_POST['filters']['search'] ?? ''),
                'source' => sanitize_text_field($_POST['filters']['source'] ?? 'all'),
                'device' => sanitize_text_field($_POST['filters']['device'] ?? 'all'),
                'country' => sanitize_text_field($_POST['filters']['country'] ?? 'all'),
            ];
            
            // Get pagination
            $page = absint($_POST['page'] ?? 1);
            $per_page = absint($_POST['per_page'] ?? 20);
            $sort_column = sanitize_text_field($_POST['sort_column'] ?? 'last_click');
            $sort_order = sanitize_text_field($_POST['sort_order'] ?? 'desc');
            
            // Get data
            $data = $this->get_dashboard_data($filters, $page, $per_page, $sort_column, $sort_order);
            
            wp_send_json_success($data);
            
        } catch (Exception $e) {
            wp_send_json_error(['message' => $e->getMessage()]);
        }
    }
    
    /**
     * Get dashboard data
     */
    private function get_dashboard_data($filters, $page, $per_page, $sort_column, $sort_order) {
        global $wpdb;
        
        // Check if table exists
        $table_exists = $wpdb->get_var("SHOW TABLES LIKE '{$this->table_name}'") === $this->table_name;
        
        if (!$table_exists) {
            // Return empty data if table doesn't exist
            return $this->get_empty_data($per_page);
        }
        
        // Build WHERE clause
        $where = $this->build_where_clause($filters);
        
        // Get metrics
        $metrics = $this->calculate_metrics($where, $filters['dateRange'], $filters);
        
        // Get heatmap data
        $heatmap = $this->get_heatmap_data($where);
        
        // Get grouped pages data instead of individual conversations
        $offset = ($page - 1) * $per_page;
        $pages_data = $this->get_grouped_pages($where, $offset, $per_page, $sort_column, $sort_order);
        
        // Get total count of unique pages
        $total_pages_query = "SELECT COUNT(DISTINCT COALESCE(page_title, 'Untitled Page')) FROM {$this->table_name} WHERE 1=1 {$where}";
        $total_items = $wpdb->get_var($total_pages_query);
        
        return [
            'metrics' => $metrics,
            'heatmap' => $heatmap,
            'conversations' => $pages_data, // Using same key for compatibility
            'pagination' => [
                'current_page' => $page,
                'total_pages' => ceil($total_items / $per_page),
                'total_items' => $total_items,
                'per_page' => $per_page,
            ],
        ];
    }
    

    /**
     * Get grouped pages data - FIXED VERSION
     */
    private function get_grouped_pages($where, $offset, $limit, $sort_column, $sort_order) {
        global $wpdb;
        
        try {
            // Map sort columns
            $sort_map = [
                'page_title' => 'page_title',
                'total_clicks' => 'total_clicks',
                'last_click' => 'last_click',
            ];
            
            $order_by = isset($sort_map[$sort_column]) ? $sort_map[$sort_column] : 'last_click';
            $order = strtoupper($sort_order) === 'ASC' ? 'ASC' : 'DESC';
            
            // Updated query to better handle cities
            $query = "SELECT SQL_CALC_FOUND_ROWS
                    MIN(page_url) as page_url,
                    COALESCE(page_title, 'Untitled Page') as page_title,
                    COUNT(*) as total_clicks,
                    SUM(CASE WHEN (click_type = 'widget' OR (COALESCE(source, '') = '' AND click_type != 'button')) THEN 1 ELSE 0 END) as widget_clicks,
                    SUM(CASE WHEN (click_type = 'button' OR source = 'Button Click') THEN 1 ELSE 0 END) as button_clicks,
                    MAX(click_time) as last_click,
                    GROUP_CONCAT(DISTINCT CASE WHEN city IS NOT NULL AND city != '' AND city != 'Unknown' THEN city ELSE NULL END ORDER BY city ASC SEPARATOR '|') as cities,
                    GROUP_CONCAT(CASE WHEN city IS NOT NULL AND city != '' AND city != 'Unknown' THEN city ELSE NULL END SEPARATOR '|') as all_cities
                FROM {$this->table_name} 
                WHERE 1=1 {$where}
                GROUP BY COALESCE(page_title, 'Untitled Page')
                ORDER BY {$order_by} {$order}
                LIMIT %d OFFSET %d";
            
            $results = $wpdb->get_results($wpdb->prepare($query, $limit, $offset));
            
            // Process results to get top city
            foreach ($results as &$row) {
                // Calculate top city with better NULL handling
                if (!empty($row->all_cities)) {
                    $cities = array_filter(explode('|', $row->all_cities), function($city) {
                        return !empty($city) && $city !== 'NULL' && $city !== 'Unknown';
                    });
                    
                    if (!empty($cities)) {
                        $city_counts = array_count_values($cities);
                        arsort($city_counts);
                        
                        $top_city = key($city_counts);
                        $top_city_count = current($city_counts);
                        
                        $row->top_city = $top_city ? "{$top_city} ({$top_city_count})" : 'Unknown';
                    } else {
                        $row->top_city = 'Unknown';
                    }
                } else {
                    $row->top_city = 'Unknown';
                }
                
                // Format last click time
                $row->last_click_formatted = $this->format_relative_time($row->last_click);
                
                // Ensure all numeric values are integers
                $row->total_clicks = intval($row->total_clicks);
                $row->widget_clicks = intval($row->widget_clicks);
                $row->button_clicks = intval($row->button_clicks);
            }
            
            return $results;
            
        } catch (Exception $e) {
            error_log('WhatsApp pages query error: ' . $e->getMessage());
            return [];
        }
    }

    
    /**
     * Format relative time
     */
    private function format_relative_time($timestamp) {
        $time = strtotime($timestamp);
        $diff = time() - $time;
        
        if ($diff < 60) {
            return __('Just now', 'sky-seo-boost');
        } elseif ($diff < 3600) {
            $mins = round($diff / 60);
            return sprintf(_n('%d minute ago', '%d minutes ago', $mins, 'sky-seo-boost'), $mins);
        } elseif ($diff < 86400) {
            $hours = round($diff / 3600);
            return sprintf(_n('%d hour ago', '%d hours ago', $hours, 'sky-seo-boost'), $hours);
        } elseif ($diff < 604800) {
            $days = round($diff / 86400);
            return sprintf(_n('%d day ago', '%d days ago', $days, 'sky-seo-boost'), $days);
        } else {
            return date_i18n('M j, Y', $time);
        }
    }

    /**
     * AJAX handler for page details
     */
    public function ajax_get_page_details() {
        // Verify nonce
        if (!check_ajax_referer('sky_seo_whatsapp_nonce', 'nonce', false)) {
            wp_send_json_error(['message' => __('Security check failed', 'sky-seo-boost')]);
            return;
        }
        
        // Check permissions
        if (!current_user_can('manage_options')) {
            wp_send_json_error(['message' => __('Insufficient permissions', 'sky-seo-boost')]);
            return;
        }
        
        $page_title = isset($_POST['page_title']) ? sanitize_text_field($_POST['page_title']) : '';
        $page_url = isset($_POST['page_url']) ? esc_url_raw($_POST['page_url']) : '';
        
        if (empty($page_title) && empty($page_url)) {
            wp_send_json_error(['message' => __('Invalid page', 'sky-seo-boost')]);
            return;
        }
        
        global $wpdb;
        
        try {
            // Build WHERE clause based on page title (since we're grouping by title)
            $where_clause = '';
            if (!empty($page_title)) {
                $where_clause = $wpdb->prepare("WHERE page_title = %s", $page_title);
            } else {
                $where_clause = $wpdb->prepare("WHERE page_url = %s", $page_url);
            }
            
            // Get page summary
            $summary = $wpdb->get_row("
                SELECT 
                    MIN(page_title) as page_title,
                    MIN(page_url) as page_url,
                    COUNT(*) as total_clicks,
                    COUNT(DISTINCT session_id) as unique_users,
                    SUM(CASE WHEN device_type = 'desktop' THEN 1 ELSE 0 END) as desktop_clicks,
                    SUM(CASE WHEN device_type IN ('mobile', 'tablet') THEN 1 ELSE 0 END) as mobile_clicks,
                    SUM(CASE WHEN (click_type = 'widget' OR (source = '' AND click_type != 'button')) THEN 1 ELSE 0 END) as widget_clicks,
                    SUM(CASE WHEN (click_type = 'button' OR source = 'Button Click') THEN 1 ELSE 0 END) as button_clicks
                FROM {$this->table_name}
                {$where_clause}
            ");
            
            // Get geographic data
            $locations = $wpdb->get_results("
                SELECT 
                    city,
                    country,
                    COUNT(*) as clicks
                FROM {$this->table_name}
                {$where_clause} AND city != '' AND city != 'Unknown'
                GROUP BY city, country
                ORDER BY clicks DESC
                LIMIT 10
            ");
            
            // Get recent clicks
            $recent_clicks = $wpdb->get_results("
                SELECT 
                    click_time,
                    device_type,
                    browser,
                    os,
                    city,
                    country,
                    click_type,
                    source,
                    page_url
                FROM {$this->table_name}
                {$where_clause}
                ORDER BY click_time DESC
                LIMIT 20
            ");
            
            // Get hourly distribution
            $hourly = $wpdb->get_results("
                SELECT 
                    HOUR(click_time) as hour,
                    COUNT(*) as clicks
                FROM {$this->table_name}
                {$where_clause}
                GROUP BY hour
                ORDER BY hour
            ");
            
            // Format recent clicks
            foreach ($recent_clicks as &$click) {
                $click->time_formatted = $this->format_relative_time($click->click_time);
                $click->location = !empty($click->city) && $click->city !== 'Unknown' ? "{$click->city}, {$click->country}" : ($click->country ?: 'Unknown');
                $click->source_formatted = ($click->source === 'Button Click' || $click->click_type === 'button') ? 'Button' : 'Widget';
            }
            
            // Ensure numeric values
            if ($summary) {
                $summary->total_clicks = intval($summary->total_clicks);
                $summary->unique_users = intval($summary->unique_users);
                $summary->desktop_clicks = intval($summary->desktop_clicks);
                $summary->mobile_clicks = intval($summary->mobile_clicks);
                $summary->widget_clicks = intval($summary->widget_clicks);
                $summary->button_clicks = intval($summary->button_clicks);
            }
            
            // Prepare response
            $data = [
                'summary' => $summary,
                'locations' => $locations,
                'recent_clicks' => $recent_clicks,
                'hourly' => $hourly
            ];
            
            wp_send_json_success($data);
            
        } catch (Exception $e) {
            error_log('WhatsApp page details error: ' . $e->getMessage());
            wp_send_json_error(['message' => __('Failed to load page details', 'sky-seo-boost')]);
        }
    }
    
    /**
     * Get empty data structure
     */
    private function get_empty_data($per_page) {
        return [
            'metrics' => [
                'total_clicks' => 0,
                'clicks_change' => 0,
                'unique_users' => 0,
                'users_change' => 0,
                'desktop_clicks' => 0,
                'desktop_percentage' => 0,
                'mobile_clicks' => 0,
                'mobile_percentage' => 0,
                'widget_clicks' => 0,
                'widget_percentage' => 0,
                'button_clicks' => 0,
                'button_percentage' => 0,
            ],
            'heatmap' => [],
            'conversations' => [],
            'pagination' => [
                'current_page' => 1,
                'total_pages' => 0,
                'total_items' => 0,
                'per_page' => $per_page,
            ],
        ];
    }
    
    /**
     * Calculate metrics - UPDATED to include new metrics
     */
    private function calculate_metrics($where, $date_range, $filters = []) {
        global $wpdb;
        
        try {
            // Current period
            $current_clicks = $wpdb->get_var("SELECT COUNT(*) FROM {$this->table_name} WHERE 1=1 {$where}");
            $current_users = $wpdb->get_var("SELECT COUNT(DISTINCT session_id) FROM {$this->table_name} WHERE 1=1 {$where}");
            
            // Get device breakdown
            $desktop_clicks = $wpdb->get_var("SELECT COUNT(*) FROM {$this->table_name} WHERE device_type = 'desktop' {$where}");
            $mobile_clicks = $wpdb->get_var("SELECT COUNT(*) FROM {$this->table_name} WHERE device_type IN ('mobile', 'tablet') {$where}");
            
            // Get source breakdown
            $widget_clicks = $wpdb->get_var("SELECT COUNT(*) FROM {$this->table_name} WHERE (click_type = 'widget' OR (source = '' AND click_type != 'button')) {$where}");
            $button_clicks = $wpdb->get_var("SELECT COUNT(*) FROM {$this->table_name} WHERE (click_type = 'button' OR source = 'Button Click') {$where}");
            
            // Calculate percentages
            $desktop_percentage = $current_clicks > 0 ? round(($desktop_clicks / $current_clicks) * 100, 1) : 0;
            $mobile_percentage = $current_clicks > 0 ? round(($mobile_clicks / $current_clicks) * 100, 1) : 0;
            $widget_percentage = $current_clicks > 0 ? round(($widget_clicks / $current_clicks) * 100, 1) : 0;
            $button_percentage = $current_clicks > 0 ? round(($button_clicks / $current_clicks) * 100, 1) : 0;
            
            // Previous period for comparison
            $prev_filters = $filters;
            if ($date_range === 'custom' && !empty($filters['customStartDate']) && !empty($filters['customEndDate'])) {
                // Calculate previous period for custom range
                $start = new DateTime($filters['customStartDate']);
                $end = new DateTime($filters['customEndDate']);
                $diff = $start->diff($end)->days + 1;
                
                $prev_start = clone $start;
                $prev_end = clone $end;
                $prev_start->sub(new DateInterval("P{$diff}D"));
                $prev_end->sub(new DateInterval("P{$diff}D"));
                
                $prev_filters['customStartDate'] = $prev_start->format('Y-m-d');
                $prev_filters['customEndDate'] = $prev_end->format('Y-m-d');
            }
            
            $prev_where = $this->build_where_clause(array_merge($prev_filters, [
                'dateRange' => $this->get_previous_period($date_range)
            ]));
            
            $prev_clicks = $wpdb->get_var("SELECT COUNT(*) FROM {$this->table_name} WHERE 1=1 {$prev_where}");
            $prev_users = $wpdb->get_var("SELECT COUNT(DISTINCT session_id) FROM {$this->table_name} WHERE 1=1 {$prev_where}");
            
            // Calculate changes
            $clicks_change = $prev_clicks > 0 ? round((($current_clicks - $prev_clicks) / $prev_clicks) * 100, 1) : 0;
            $users_change = $prev_users > 0 ? round((($current_users - $prev_users) / $prev_users) * 100, 1) : 0;
            
            return [
                'total_clicks' => intval($current_clicks),
                'clicks_change' => $clicks_change,
                'unique_users' => intval($current_users),
                'users_change' => $users_change,
                'desktop_clicks' => intval($desktop_clicks),
                'desktop_percentage' => $desktop_percentage,
                'mobile_clicks' => intval($mobile_clicks),
                'mobile_percentage' => $mobile_percentage,
                'widget_clicks' => intval($widget_clicks),
                'widget_percentage' => $widget_percentage,
                'button_clicks' => intval($button_clicks),
                'button_percentage' => $button_percentage,
            ];
            
        } catch (Exception $e) {
            error_log('WhatsApp metrics calculation error: ' . $e->getMessage());
            return $this->get_empty_data(20)['metrics'];
        }
    }
    
    /**
     * Get heatmap data
     */
    private function get_heatmap_data($where) {
    global $wpdb;
    
    try {
        // Fixed query for Monday = 0 ordering
        $query = "SELECT 
                CASE DAYOFWEEK(click_time)
                    WHEN 1 THEN 6  -- Sunday = 6
                    WHEN 2 THEN 0  -- Monday = 0
                    WHEN 3 THEN 1  -- Tuesday = 1
                    WHEN 4 THEN 2  -- Wednesday = 2
                    WHEN 5 THEN 3  -- Thursday = 3
                    WHEN 6 THEN 4  -- Friday = 4
                    WHEN 7 THEN 5  -- Saturday = 5
                END as day,
                HOUR(click_time) as hour,
                COUNT(*) as count 
            FROM {$this->table_name} 
            WHERE 1=1 {$where}
            GROUP BY day, hour";
        
        $data = $wpdb->get_results($query);
        
        // Format for heatmap
        $heatmap = [];
        foreach ($data as $row) {
            if (!isset($heatmap[$row->day])) {
                $heatmap[$row->day] = [];
            }
            $heatmap[$row->day][$row->hour] = $row->count;
        }
        
        return $heatmap;
        
    } catch (Exception $e) {
        error_log('WhatsApp heatmap data error: ' . $e->getMessage());
        return [];
    }
}
    
    /**
     * Build WHERE clause - IMPROVED SECURITY
     */
    private function build_where_clause($filters) {
        global $wpdb;
        $where_parts = [];
        
        // Date range
        $date_condition = $this->get_date_condition($filters['dateRange'] ?? 'thismonth', $filters);
        if ($date_condition) {
            $where_parts[] = $date_condition;
        }
        
        // Search
        if (!empty($filters['search'])) {
            $search = '%' . $wpdb->esc_like($filters['search']) . '%';
            $where_parts[] = $wpdb->prepare("(page_title LIKE %s OR page_url LIKE %s)", $search, $search);
        }
        
        // Device filter
        if (!empty($filters['device']) && $filters['device'] !== 'all') {
            $allowed_devices = ['desktop', 'mobile', 'tablet'];
            if (in_array($filters['device'], $allowed_devices)) {
                $where_parts[] = $wpdb->prepare("device_type = %s", $filters['device']);
            }
        }
        
        // Country filter
        if (!empty($filters['country']) && $filters['country'] !== 'all') {
            $where_parts[] = $wpdb->prepare("country = %s", $filters['country']);
        }
        
        // Combine with AND
        return !empty($where_parts) ? ' AND ' . implode(' AND ', $where_parts) : '';
    }
    
    /**
     * Get date condition - FIXED SECURITY ISSUE
     */
    private function get_date_condition($range, $filters = []) {
        global $wpdb;
        
        switch ($range) {
            case '7days':
                return "click_time >= DATE_SUB(NOW(), INTERVAL 7 DAY)";
            case '30days':
                return "click_time >= DATE_SUB(NOW(), INTERVAL 30 DAY)";
            case '90days':
                return "click_time >= DATE_SUB(NOW(), INTERVAL 90 DAY)";
            case 'today':
                return "DATE(click_time) = CURDATE()";
            case 'yesterday':
                return "DATE(click_time) = DATE_SUB(CURDATE(), INTERVAL 1 DAY)";
            case 'thismonth':
                return "MONTH(click_time) = MONTH(CURRENT_DATE()) AND YEAR(click_time) = YEAR(CURRENT_DATE())";
            case 'lastmonth':
                return "MONTH(click_time) = MONTH(DATE_SUB(CURRENT_DATE(), INTERVAL 1 MONTH)) 
                        AND YEAR(click_time) = YEAR(DATE_SUB(CURRENT_DATE(), INTERVAL 1 MONTH))";
            case 'custom':
                // Handle custom date range with validation
                if (!empty($filters['customStartDate']) && !empty($filters['customEndDate'])) {
                    $start = sanitize_text_field($filters['customStartDate']);
                    $end = sanitize_text_field($filters['customEndDate']);
                    
                    // Validate date format (YYYY-MM-DD)
                    if (!preg_match('/^\d{4}-\d{2}-\d{2}$/', $start) || 
                        !preg_match('/^\d{4}-\d{2}-\d{2}$/', $end)) {
                        return "";
                    }
                    
                    // Additional validation: check if dates are valid
                    $start_time = strtotime($start);
                    $end_time = strtotime($end);
                    
                    if ($start_time === false || $end_time === false || $start_time > $end_time) {
                        return "";
                    }
                    
                    return $wpdb->prepare(
                        "DATE(click_time) BETWEEN %s AND %s",
                        $start,
                        $end
                    );
                }
                return "";
            case 'prev7days':
                return "click_time >= DATE_SUB(NOW(), INTERVAL 14 DAY) AND click_time < DATE_SUB(NOW(), INTERVAL 7 DAY)";
            case 'prev30days':
                return "click_time >= DATE_SUB(NOW(), INTERVAL 60 DAY) AND click_time < DATE_SUB(NOW(), INTERVAL 30 DAY)";
            case 'prev90days':
                return "click_time >= DATE_SUB(NOW(), INTERVAL 180 DAY) AND click_time < DATE_SUB(NOW(), INTERVAL 90 DAY)";
            case 'prevthismonth':
                // Previous year, same month
                return "MONTH(click_time) = MONTH(CURRENT_DATE()) 
                        AND YEAR(click_time) = YEAR(DATE_SUB(CURRENT_DATE(), INTERVAL 1 YEAR))";
            case 'prevlastmonth':
                // Previous year, last month
                return "MONTH(click_time) = MONTH(DATE_SUB(CURRENT_DATE(), INTERVAL 1 MONTH)) 
                        AND YEAR(click_time) = YEAR(DATE_SUB(CURRENT_DATE(), INTERVAL 13 MONTH))";
            default:
                return "";
        }
    }
    
    /**
     * Get previous period for comparison - UPDATED METHOD
     */
    private function get_previous_period($current) {
        $periods = [
            '7days' => 'prev7days',
            '30days' => 'prev30days',
            '90days' => 'prev90days',
            'thismonth' => 'prevthismonth',
            'lastmonth' => 'prevlastmonth',
            'custom' => 'custom', // Handle custom comparison separately
        ];
        
        return $periods[$current] ?? 'prev7days';
    }
    
    /**
     * AJAX handler for export
     */
    public function ajax_export_data() {
        // Verify nonce
        if (!check_ajax_referer('sky_seo_whatsapp_nonce', 'nonce', false)) {
            wp_die(__('Security check failed', 'sky-seo-boost'));
        }
        
        // Check permissions
        if (!current_user_can('manage_options')) {
            wp_die(__('Insufficient permissions', 'sky-seo-boost'));
        }
        
        $format = sanitize_text_field($_POST['format'] ?? 'csv');
        $filters = $_POST['filters'] ?? [];
        
        // Get all data
        global $wpdb;
        $where = $this->build_where_clause($filters);
        
        $query = "SELECT * FROM {$this->table_name} WHERE 1=1 {$where} ORDER BY click_time DESC";
        $data = $wpdb->get_results($query);
        
        $filename = 'whatsapp-analytics-' . date('Y-m-d-His');
        
        if ($format === 'csv') {
            $this->export_csv($data, $filename);
        } else {
            $this->export_excel($data, $filename);
        }
    }
    
    /**
     * Export as CSV
     */
    private function export_csv($data, $filename) {
        header('Content-Type: text/csv; charset=utf-8');
        header('Content-Disposition: attachment; filename="' . $filename . '.csv"');
        
        $output = fopen('php://output', 'w');
        
        // Add BOM for Excel
        fprintf($output, chr(0xEF).chr(0xBB).chr(0xBF));
        
        // Headers
        fputcsv($output, [
            'Date/Time',
            'Page Title',
            'Page URL',
            'Country',
            'City',
            'Device Type',
            'Browser',
            'OS',
            'Source',
            'Referrer'
        ]);
        
        // Data
        foreach ($data as $row) {
            fputcsv($output, [
                $row->click_time,
                $row->page_title,
                $row->page_url,
                $row->country,
                $row->city,
                $row->device_type,
                $row->browser,
                $row->os,
                $row->source ?: $row->click_type,
                $row->referrer_url
            ]);
        }
        
        fclose($output);
        exit;
    }
    
    /**
     * Export as Excel
     */
    private function export_excel($data, $filename) {
        header('Content-Type: application/vnd.ms-excel; charset=utf-8');
        header('Content-Disposition: attachment; filename="' . $filename . '.xls"');
        
        echo '<html xmlns:x="urn:schemas-microsoft-com:office:excel">';
        echo '<head><meta charset="UTF-8"></head>';
        echo '<body>';
        echo '<table border="1">';
        
        // Headers
        echo '<tr>';
        echo '<th>Date/Time</th>';
        echo '<th>Page Title</th>';
        echo '<th>Page URL</th>';
        echo '<th>Country</th>';
        echo '<th>City</th>';
        echo '<th>Device Type</th>';
        echo '<th>Browser</th>';
        echo '<th>OS</th>';
        echo '<th>Source</th>';
        echo '<th>Referrer</th>';
        echo '</tr>';
        
        // Data
        foreach ($data as $row) {
            echo '<tr>';
            echo '<td>' . esc_html($row->click_time) . '</td>';
            echo '<td>' . esc_html($row->page_title) . '</td>';
            echo '<td>' . esc_html($row->page_url) . '</td>';
            echo '<td>' . esc_html($row->country) . '</td>';
            echo '<td>' . esc_html($row->city) . '</td>';
            echo '<td>' . esc_html($row->device_type) . '</td>';
            echo '<td>' . esc_html($row->browser) . '</td>';
            echo '<td>' . esc_html($row->os) . '</td>';
            echo '<td>' . esc_html($row->source ?: $row->click_type) . '</td>';
            echo '<td>' . esc_html($row->referrer_url) . '</td>';
            echo '</tr>';
        }
        
        echo '</table>';
        echo '</body>';
        echo '</html>';
        
        exit;
    }
    
    /**
     * Track WhatsApp click - IMPROVED ERROR HANDLING
     */
    public function track_click() {
        global $wpdb;
        
        try {
            // Check if table exists
            $table_exists = $wpdb->get_var("SHOW TABLES LIKE '{$this->table_name}'") === $this->table_name;
            
            if (!$table_exists) {
                throw new Exception('Tracking table does not exist');
            }
            
            // Get click data
            $page_url = isset($_POST['page_url']) ? esc_url_raw($_POST['page_url']) : '';
            $page_title = isset($_POST['page_title']) ? sanitize_text_field($_POST['page_title']) : '';
            $referrer_url = isset($_POST['referrer']) ? esc_url_raw($_POST['referrer']) : '';
            $click_type = isset($_POST['click_type']) ? sanitize_text_field($_POST['click_type']) : 'widget';
            $source = isset($_POST['source']) ? sanitize_text_field($_POST['source']) : '';
            
            // Get user data
            $user_agent = isset($_SERVER['HTTP_USER_AGENT']) ? sanitize_text_field($_SERVER['HTTP_USER_AGENT']) : '';
            $ip_address = $this->get_user_ip();
            
            // Get device info
            $device_info = $this->get_device_info($user_agent);
            
            // Get location info with improved handling
            $location = $this->get_location_by_ip($ip_address);
            
            // Generate session ID
            $session_id = $this->get_or_create_session_id();
            
            // Get user ID if logged in
            $user_id = get_current_user_id();
            
            // Insert tracking data
            $data = [
                'click_time' => current_time('mysql'),
                'ip_address' => $ip_address,
                'country' => $location['country'] ?? '',
                'city' => $location['city'] ?? '',
                'referrer_url' => $referrer_url,
                'page_url' => $page_url,
                'page_title' => $page_title,
                'device_type' => $device_info['device_type'],
                'browser' => $device_info['browser'],
                'os' => $device_info['os'],
                'click_type' => $click_type,
                'source' => $source,
                'user_agent' => $user_agent,
                'session_id' => $session_id,
                'user_id' => $user_id ?: null,
            ];
            
            $result = $wpdb->insert($this->table_name, $data);
            
            if ($result === false) {
                throw new Exception($wpdb->last_error ?: 'Database insert failed');
            }
            
            // Get insert ID for confirmation
            $insert_id = $wpdb->insert_id;
            
            wp_send_json_success([
                'message' => __('Click tracked successfully', 'sky-seo-boost'),
                'id' => $insert_id
            ]);
            
        } catch (Exception $e) {
            // Log error
            error_log('WhatsApp tracking error: ' . $e->getMessage());
            
            wp_send_json_error([
                'message' => __('Error tracking click', 'sky-seo-boost'),
                'error' => WP_DEBUG ? $e->getMessage() : 'An error occurred'
            ]);
        }
    }
    
    /**
     * Get user IP address
     */
    private function get_user_ip() {
        $ip_keys = ['HTTP_CF_CONNECTING_IP', 'HTTP_CLIENT_IP', 'HTTP_X_FORWARDED_FOR', 'REMOTE_ADDR'];
        
        foreach ($ip_keys as $key) {
            if (array_key_exists($key, $_SERVER) === true) {
                $ip = $_SERVER[$key];
                if (strpos($ip, ',') !== false) {
                    $ip = explode(',', $ip)[0];
                }
                
                $ip = trim($ip);
                
                if (filter_var($ip, FILTER_VALIDATE_IP, FILTER_FLAG_NO_PRIV_RANGE | FILTER_FLAG_NO_RES_RANGE)) {
                    return $ip;
                }
            }
        }
        
        return isset($_SERVER['REMOTE_ADDR']) ? $_SERVER['REMOTE_ADDR'] : '127.0.0.1';
    }
    
    /**
     * Get device info from user agent
     */
    private function get_device_info($user_agent) {
        $device_type = 'desktop';
        $browser = 'Unknown';
        $os = 'Unknown';
        
        // Device type detection
        if (preg_match('/mobile/i', $user_agent)) {
            $device_type = 'mobile';
        } elseif (preg_match('/tablet|ipad/i', $user_agent)) {
            $device_type = 'tablet';
        }
        
        // Browser detection
        if (preg_match('/firefox/i', $user_agent)) {
            $browser = 'Firefox';
        } elseif (preg_match('/chrome/i', $user_agent) && !preg_match('/edge/i', $user_agent)) {
            $browser = 'Chrome';
        } elseif (preg_match('/safari/i', $user_agent) && !preg_match('/chrome/i', $user_agent)) {
            $browser = 'Safari';
        } elseif (preg_match('/edge/i', $user_agent)) {
            $browser = 'Edge';
        } elseif (preg_match('/opera|opr/i', $user_agent)) {
            $browser = 'Opera';
        } elseif (preg_match('/trident|msie/i', $user_agent)) {
            $browser = 'Internet Explorer';
        }
        
        // OS detection
        if (preg_match('/windows/i', $user_agent)) {
            $os = 'Windows';
        } elseif (preg_match('/macintosh|mac os x/i', $user_agent)) {
            $os = 'macOS';
        } elseif (preg_match('/linux/i', $user_agent)) {
            $os = 'Linux';
        } elseif (preg_match('/android/i', $user_agent)) {
            $os = 'Android';
        } elseif (preg_match('/iphone|ipad|ipod/i', $user_agent)) {
            $os = 'iOS';
        }
        
        return [
            'device_type' => $device_type,
            'browser' => $browser,
            'os' => $os,
        ];
    }
    
    /**
     * Get location by IP - IMPROVED VERSION WITH FALLBACKS
     */
    private function get_location_by_ip($ip) {
        // Check cache first
        $cache_key = 'sky_whatsapp_geo_' . md5($ip);
        $cached = get_transient($cache_key);
        
        if ($cached !== false) {
            return $cached;
        }
        
        // Skip for local IPs
        if (filter_var($ip, FILTER_VALIDATE_IP, FILTER_FLAG_NO_PRIV_RANGE | FILTER_FLAG_NO_RES_RANGE) === false) {
            $location = [
                'country' => 'Local',
                'city' => 'Local Network',
            ];
            set_transient($cache_key, $location, DAY_IN_SECONDS);
            return $location;
        }
        
        // Try multiple services with fallbacks
        $location = $this->try_ip_api($ip);
        
        if (empty($location['city']) || $location['city'] === 'Unknown') {
            // Try alternative service
            $location = $this->try_ipinfo($ip);
        }
        
        if (empty($location['city']) || $location['city'] === 'Unknown') {
            // Use CloudFlare headers if available
            $location = $this->try_cloudflare_headers();
        }
        
        // Default if all fail
        if (empty($location['city'])) {
            $location = [
                'country' => 'Unknown',
                'city' => 'Unknown',
            ];
        }
        
        // Cache for 1 day (even if unknown to prevent repeated API calls)
        set_transient($cache_key, $location, DAY_IN_SECONDS);
        
        return $location;
    }
    
    /**
     * Try IP-API service
     */
    private function try_ip_api($ip) {
        $response = wp_remote_get("http://ip-api.com/json/{$ip}?fields=status,country,city,regionName", [
            'timeout' => 3,
            'sslverify' => false,
        ]);
        
        if (!is_wp_error($response)) {
            $data = json_decode(wp_remote_retrieve_body($response), true);
            
            if ($data && isset($data['status']) && $data['status'] === 'success') {
                return [
                    'country' => !empty($data['country']) ? $data['country'] : 'Unknown',
                    'city' => !empty($data['city']) ? $data['city'] : (!empty($data['regionName']) ? $data['regionName'] : 'Unknown'),
                ];
            }
        }
        
        return ['country' => '', 'city' => ''];
    }
    
    /**
     * Try IPInfo service as fallback
     */
    private function try_ipinfo($ip) {
        $response = wp_remote_get("https://ipinfo.io/{$ip}/json", [
            'timeout' => 3,
            'sslverify' => false,
        ]);
        
        if (!is_wp_error($response)) {
            $data = json_decode(wp_remote_retrieve_body($response), true);
            
            if ($data && !isset($data['bogon']) && !isset($data['error'])) {
                return [
                    'country' => !empty($data['country']) ? $data['country'] : 'Unknown',
                    'city' => !empty($data['city']) ? $data['city'] : 'Unknown',
                ];
            }
        }
        
        return ['country' => '', 'city' => ''];
    }
    
    /**
     * Try CloudFlare headers as last resort
     */
    private function try_cloudflare_headers() {
        $country = isset($_SERVER['HTTP_CF_IPCOUNTRY']) ? $_SERVER['HTTP_CF_IPCOUNTRY'] : '';
        $city = isset($_SERVER['HTTP_CF_IPCITY']) ? $_SERVER['HTTP_CF_IPCITY'] : '';
        
        if ($country || $city) {
            return [
                'country' => $country ?: 'Unknown',
                'city' => $city ?: 'Unknown',
            ];
        }
        
        return ['country' => '', 'city' => ''];
    }
    
    /**
     * Get country flag emoji
     */
    private function get_country_flag($country) {
        $flags = [
            'United States' => '🇺🇸',
            'United Kingdom' => '🇬🇧',
            'Canada' => '🇨🇦',
            'Australia' => '🇦🇺',
            'Germany' => '🇩🇪',
            'France' => '🇫🇷',
            'Spain' => '🇪🇸',
            'Italy' => '🇮🇹',
            'Netherlands' => '🇳🇱',
            'Brazil' => '🇧🇷',
            'India' => '🇮🇳',
            'China' => '🇨🇳',
            'Japan' => '🇯🇵',
            'South Korea' => '🇰🇷',
            'Mexico' => '🇲🇽',
            'Russia' => '🇷🇺',
            'South Africa' => '🇿🇦',
            'Egypt' => '🇪🇬',
            'Nigeria' => '🇳🇬',
            'Kenya' => '🇰🇪',
        ];
        
        return $flags[$country] ?? '🌍';
    }
    
    /**
     * Get or create session ID - IMPROVED SECURITY
     */
    private function get_or_create_session_id() {
        $cookie_name = 'sky_whatsapp_session';
        
        if (isset($_COOKIE[$cookie_name])) {
            $session_id = sanitize_text_field($_COOKIE[$cookie_name]);
            
            // Validate UUID format
            if (preg_match('/^[a-f0-9]{8}-[a-f0-9]{4}-[a-f0-9]{4}-[a-f0-9]{4}-[a-f0-9]{12}$/i', $session_id)) {
                return $session_id;
            }
        }
        
        $session_id = wp_generate_uuid4();
        
        // Set cookie with improved security
        $secure = is_ssl();
        $httponly = true;
        $samesite = 'Lax';
        
        // Use modern cookie setting if PHP >= 7.3
        if (version_compare(PHP_VERSION, '7.3.0', '>=')) {
            setcookie(
                $cookie_name, 
                $session_id, 
                [
                    'expires' => time() + (86400 * 30),
                    'path' => '/',
                    'domain' => COOKIE_DOMAIN ?: '',
                    'secure' => $secure,
                    'httponly' => $httponly,
                    'samesite' => $samesite
                ]
            );
        } else {
            // Fallback for older PHP versions
            $path = '/; samesite=' . $samesite;
            setcookie(
                $cookie_name,
                $session_id,
                time() + (86400 * 30),
                $path,
                COOKIE_DOMAIN ?: '',
                $secure,
                $httponly
            );
        }
        
        return $session_id;
    }
}