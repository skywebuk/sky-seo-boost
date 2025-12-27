<?php
/**
 * Sky SEO Business Admin Renderer
 * Handles all admin page rendering
 * 
 * @package SkySEOBoost
 * @subpackage BusinessAPI
 * @since 3.1.0
 */

// Prevent direct access
if (!defined('ABSPATH')) {
    exit;
}

/**
 * Business Admin Renderer Class
 */
class Sky_SEO_Business_Admin_Renderer {
    
    /**
     * Main API instance
     */
    private $api;
    
    /**
     * Constructor
     */
    public function __construct($api_instance) {
        $this->api = $api_instance;
    }
    
    /**
     * Get option names
     */
    private function get_option_names() {
        return $this->api->get_option_names();
    }
    
    /**
     * Render admin page with tabs
     */
    public function render_admin_page() {
        // Get current tab
        $current_tab = isset($_GET['tab']) ? sanitize_text_field($_GET['tab']) : 'settings';

        // Check if Reviews Database is available
        $show_reviews_tab = class_exists('Sky_SEO_Reviews_Database');

        // Start admin page wrapper
        sky360_admin_page_start();

        // Render header
        sky360_render_admin_header(
            __('Business API', 'sky360'),
            __('Manage your Google Business Profile and reviews', 'sky360')
        );

        // Build tabs array
        $tabs = [
            [
                'slug' => 'settings',
                'label' => __('Settings', 'sky360'),
                'icon' => 'dashicons-admin-generic'
            ]
        ];

        if ($show_reviews_tab) {
            $tabs[] = [
                'slug' => 'reviews',
                'label' => __('Reviews', 'sky360'),
                'icon' => 'dashicons-star-filled'
            ];
        }

        // Render navigation tabs
        sky360_render_nav_tabs($tabs, $current_tab, 'sky-seo-business-api');

        // Start content wrapper
        sky360_content_wrapper_start();

        // Render appropriate tab content
        switch ($current_tab) {
            case 'reviews':
                if ($show_reviews_tab) {
                    $this->render_reviews_tab();
                } else {
                    $this->render_settings_tab();
                }
                break;

            case 'settings':
            default:
                $this->render_settings_tab();
                break;
        }

        // End content wrapper
        sky360_content_wrapper_end();

        // End admin page wrapper
        sky360_admin_page_end();
    }
    
    /**
     * Render settings tab content
     */
    private function render_settings_tab() {
        $option_names = $this->get_option_names();
        $settings = get_option($option_names['main'], []);
        $advanced_settings = get_option($option_names['advanced'], []);
        $hours_settings = get_option($option_names['hours'], []);
        
        // Get API handler for stats
        $api_handler = new Sky_SEO_Business_API_Handler($this->api);
        $usage_stats = $api_handler->get_api_usage_stats();
        
        // Get review stats if database available
        $review_stats = null;
        if (class_exists('Sky_SEO_Reviews_Database')) {
            $reviews_db = Sky_SEO_Reviews_Database::get_instance();
            $review_stats = $reviews_db->get_review_stats($settings['place_id'] ?? '');
        }
        ?>
        <?php $this->render_dashboard($usage_stats, $review_stats); ?>
        
        <form method="post" action="options.php">
            <?php settings_fields('sky_seo_business_api'); ?>
            
            <?php $this->render_api_settings($settings); ?>
            <?php $this->render_hours_settings($hours_settings); ?>
            <?php $this->render_action_buttons($review_stats); ?>
            
            <?php submit_button(); ?>
        </form>
        
        <div id="test-results"></div>
        
        <?php $this->render_inline_scripts(); ?>
        <?php
    }
    
    /**
     * Render reviews tab content
     */
    private function render_reviews_tab() {
        if (!class_exists('Sky_SEO_Reviews_Database')) {
            wp_die(__('Reviews database not available', 'sky360'));
        }
        
        $reviews_db = Sky_SEO_Reviews_Database::get_instance();
        $option_names = $this->get_option_names();
        $settings = get_option($option_names['main'], []);
        $place_id = $settings['place_id'] ?? '';
        
        // Handle actions
        $this->handle_review_actions();
        
        // Get filters and pagination
        $search = isset($_GET['s']) ? sanitize_text_field($_GET['s']) : '';
        $filter_rating = isset($_GET['rating']) ? intval($_GET['rating']) : 0;
        $filter_visibility = isset($_GET['visibility']) ? sanitize_text_field($_GET['visibility']) : 'all';
        $filter_platform = isset($_GET['platform']) ? sanitize_text_field($_GET['platform']) : 'all';
        $page = isset($_GET['paged']) ? max(1, intval($_GET['paged'])) : 1;
        $per_page = 20;
        $offset = ($page - 1) * $per_page;
        
        // Get reviews data
        $reviews_data = $this->get_filtered_reviews($place_id, $search, $filter_rating, $filter_visibility, $filter_platform, $per_page, $offset);
        $reviews = $reviews_data['reviews'];
        $total_items = $reviews_data['total'];
        $total_pages = ceil($total_items / $per_page);
        
        // Display admin notices
        $this->display_review_notices();
        ?>
        

        <a href="#" class="page-title-action" id="add-manual-review">
            <?php _e('Add Manual Review', 'sky360'); ?>
        </a>
        
        <?php if (!empty($place_id)) : ?>
        <a href="#" class="page-title-action" id="refresh-reviews">
            <?php _e('Refresh Google Reviews', 'sky360'); ?>
        </a>
        <?php endif; ?>
        
        <hr class="wp-header-end">
        
        <?php $this->render_reviews_filters($search, $filter_rating, $filter_visibility, $filter_platform); ?>
        
        <?php if (empty($reviews)) : ?>
            <?php $this->render_no_reviews_message($place_id); ?>
        <?php else : ?>
            <form method="post" action="<?php echo admin_url('admin.php?page=sky-seo-business-api&tab=reviews'); ?>">
                <?php wp_nonce_field('bulk_review_action'); ?>
                
                <?php $this->render_bulk_actions(); ?>
                
                <table class="wp-list-table widefat fixed striped reviews-table">
                    <?php $this->render_reviews_table_header(); ?>
                    <tbody>
                        <?php foreach ($reviews as $review) : ?>
                            <?php $this->render_review_row($review); ?>
                        <?php endforeach; ?>
                    </tbody>
                    <tfoot>
                        <?php $this->render_reviews_table_header(); ?>
                    </tfoot>
                </table>
                
                <?php $this->render_bulk_actions('bottom'); ?>
            </form>
            
            <?php $this->render_pagination($page, $total_pages, $search, $filter_rating, $filter_visibility, $filter_platform, $total_items); ?>
        <?php endif; ?>
        
        <?php $this->render_review_modals(); ?>
        <?php $this->render_manual_review_modal(); ?>
        <?php $this->render_reviews_scripts($settings); ?>
        <?php
    }
    
    /**
     * Render dashboard section
     */
    private function render_dashboard($usage_stats, $review_stats) {
        ?>
        <div class="api-usage-dashboard">
            <h2><?php _e('Overview', 'sky360'); ?></h2>
            <div class="usage-stats-grid">
                <div class="usage-stat">
                    <h4><?php _e('API Usage', 'sky360'); ?></h4>
                    <p class="stat-value">
                        <span id="monthly-usage-text"><?php echo $usage_stats['monthly_used']; ?> / <?php echo $usage_stats['monthly_limit']; ?></span>
                    </p>
                    <div class="usage-progress">
                        <div class="usage-progress-bar" id="monthly-usage-bar" style="width: <?php echo ($usage_stats['monthly_used'] / $usage_stats['monthly_limit']) * 100; ?>%"></div>
                    </div>
                    <p class="stat-subtitle">
                        <?php 
                        $remaining_searches = $usage_stats['monthly_limit'] - $usage_stats['monthly_used'];
                        $remaining_days = $usage_stats['days_in_month'] - $usage_stats['current_day'] + 1;
                        $recommended_per_day = $remaining_days > 0 ? round($remaining_searches / $remaining_days, 1) : 0;
                        printf(__('%d days left • %.1f/day recommended', 'sky360'), $remaining_days, $recommended_per_day);
                        ?>
                    </p>
                </div>
                
                <?php if ($review_stats) : ?>
                <div class="usage-stat">
                    <h4><?php _e('Reviews', 'sky360'); ?></h4>
                    <p class="stat-value">
                        <span id="total-reviews"><?php echo $review_stats['total_reviews']; ?></span>
                    </p>
                    <p class="stat-subtitle">
                        <span class="rating-stars"><?php echo str_repeat('★', round($review_stats['average_rating'])); ?></span>
                        <span><?php echo $review_stats['average_rating']; ?> <?php _e('average', 'sky360'); ?></span>
                    </p>
                    <?php if (isset($review_stats['platform_breakdown'])) : ?>
                    <p class="stat-subtitle" style="margin-top: 10px; font-size: 11px;">
                        <?php 
                        echo sprintf(
                            __('Google: %d | FB: %d | Trust: %d', 'sky360'),
                            $review_stats['platform_breakdown']['google'],
                            $review_stats['platform_breakdown']['facebook'],
                            $review_stats['platform_breakdown']['trustpilot']
                        );
                        ?>
                    </p>
                    <?php endif; ?>
                </div>
                
                <div class="usage-stat">
                    <h4><?php _e('Last Sync', 'sky360'); ?></h4>
                    <p class="stat-value" style="font-size: 24px;">
                        <?php 
                        if ($review_stats['last_fetch_time']) {
                            echo human_time_diff(strtotime($review_stats['last_fetch_time'])) . ' ' . __('ago', 'sky360');
                        } else {
                            _e('Never', 'sky360');
                        }
                        ?>
                    </p>
                    <?php if ($review_stats['total_reviews'] > 0) : ?>
                    <a href="<?php echo admin_url('admin.php?page=sky-seo-business-api&tab=reviews'); ?>" class="button button-small" style="margin-top: 10px;">
                        <?php _e('Manage Reviews', 'sky360'); ?>
                    </a>
                    <?php else : ?>
                    <p class="stat-subtitle"><?php _e('No reviews fetched yet', 'sky360'); ?></p>
                    <?php endif; ?>
                </div>
                <?php else : ?>
                <div class="usage-stat">
                    <h4><?php _e('Reviews', 'sky360'); ?></h4>
                    <p class="stat-value">0</p>
                    <p class="stat-subtitle"><?php _e('No reviews yet', 'sky360'); ?></p>
                </div>
                
                <div class="usage-stat">
                    <h4><?php _e('Status', 'sky360'); ?></h4>
                    <p class="stat-value" style="font-size: 24px;"><?php _e('Not Configured', 'sky360'); ?></p>
                    <p class="stat-subtitle"><?php _e('Add API key to start', 'sky360'); ?></p>
                </div>
                <?php endif; ?>
            </div>
        </div>
        <?php
    }
    
    /**
     * Render API settings section
     */
    private function render_api_settings($settings) {
        $option_names = $this->get_option_names();
        ?>
        <h2 class="form-section-title"><?php _e('API Configuration', 'sky360'); ?></h2>
        <table class="form-table" role="presentation">
            <tr>
                <th scope="row">
                    <label for="serpapi_key"><?php _e('SerpApi Key', 'sky360'); ?></label>
                </th>
                <td>
                    <div class="api-key-wrapper">
                        <input type="password" 
                               id="serpapi_key"
                               name="<?php echo esc_attr($option_names['main']); ?>[serpapi_key]" 
                               value="<?php echo esc_attr($settings['serpapi_key'] ?? ''); ?>" 
                               class="regular-text api-key-field"
                               autocomplete="off" />
                        <button type="button" class="toggle-api-key-visibility" onclick="toggleApiKeyVisibility(this)">
                            <span class="dashicons dashicons-visibility"></span>
                        </button>
                    </div>
                    <p class="description">
                        <?php _e('Get your API key from', 'sky360'); ?> 
                        <a href="https://serpapi.com/users/sign_up" target="_blank" rel="noopener noreferrer">SerpApi.com</a>
                    </p>
                </td>
            </tr>
            
            <tr>
                <th scope="row">
                    <label for="place_id"><?php _e('Place ID', 'sky360'); ?></label>
                </th>
                <td>
                    <div class="place-id-wrapper">
                        <input type="text" 
                               id="place_id"
                               name="<?php echo esc_attr($option_names['main']); ?>[place_id]" 
                               value="<?php echo esc_attr($settings['place_id'] ?? ''); ?>" 
                               class="regular-text" />
                        <button type="button" class="button copy-place-id" onclick="copyPlaceId()">
                            <?php _e('Copy', 'sky360'); ?>
                        </button>
                    </div>
                    <p class="description">
                        <?php _e('Enter your Google Place ID or data_id. This uniquely identifies your business on Google.', 'sky360'); ?>
                    </p>
                </td>
            </tr>
            
            <tr>
                <th scope="row">
                    <label for="business_name"><?php _e('Business Name', 'sky360'); ?></label>
                </th>
                <td>
                    <input type="text" 
                           id="business_name"
                           name="<?php echo esc_attr($option_names['main']); ?>[business_name]" 
                           value="<?php echo esc_attr($settings['business_name'] ?? ''); ?>" 
                           class="regular-text"
                           placeholder="<?php esc_attr_e('Your Business Name', 'sky360'); ?>" />
                    <p class="description">
                        <?php _e('Enter your business name as it appears on Google.', 'sky360'); ?>
                    </p>
                </td>
            </tr>
        </table>
        <?php
    }
    
    /**
     * Render hours settings section
     */
    private function render_hours_settings($hours_settings) {
        $option_names = $this->get_option_names();
        ?>
        <h2 class="form-section-title"><?php _e('Business Hours', 'sky360'); ?></h2>
        <table class="form-table" role="presentation">
            <tr>
                <th scope="row">
                    <label for="timezone"><?php _e('Timezone', 'sky360'); ?></label>
                </th>
                <td>
                    <select id="timezone" name="<?php echo esc_attr($option_names['hours']); ?>[timezone]" class="timezone-select">
                        <?php echo wp_timezone_choice($hours_settings['timezone'] ?? 'UTC'); ?>
                    </select>
                    <p class="description">
                        <?php _e('Select your business timezone for accurate open/closed status.', 'sky360'); ?>
                    </p>
                </td>
            </tr>
        </table>
        
        <div class="sky-hours-container">
            <div class="hours-actions">
                <button type="button" class="button" id="apply-to-all">
                    <?php _e('Apply to all days', 'sky360'); ?>
                </button>
                <button type="button" class="button" id="copy-hours">
                    <?php _e('Copy hours from...', 'sky360'); ?>
                </button>
            </div>
            
            <?php $this->render_hours_table($hours_settings); ?>
        </div>
        <?php
    }
    
    /**
     * Render hours table
     */
    private function render_hours_table($hours_settings) {
        $option_names = $this->get_option_names();
        $days = [
            'monday' => __('Monday', 'sky360'),
            'tuesday' => __('Tuesday', 'sky360'),
            'wednesday' => __('Wednesday', 'sky360'),
            'thursday' => __('Thursday', 'sky360'),
            'friday' => __('Friday', 'sky360'),
            'saturday' => __('Saturday', 'sky360'),
            'sunday' => __('Sunday', 'sky360')
        ];
        ?>
        <table class="sky-hours-table">
            <thead>
                <tr>
                    <th><?php _e('Day', 'sky360'); ?></th>
                    <th><?php _e('Status', 'sky360'); ?></th>
                    <th><?php _e('Hours', 'sky360'); ?></th>
                    <th><?php _e('Actions', 'sky360'); ?></th>
                </tr>
            </thead>
            <tbody>
                <?php foreach ($days as $day_key => $day_label) : 
                    $is_closed = isset($hours_settings[$day_key . '_closed']) && $hours_settings[$day_key . '_closed'] == '1';
                    $is_24hours = isset($hours_settings[$day_key . '_24hours']) && $hours_settings[$day_key . '_24hours'] == '1';
                    $slots = $hours_settings[$day_key . '_slots'] ?? [];
                    
                    // Fallback for simple hours
                    if (empty($slots) && !$is_closed && !$is_24hours) {
                        $open_time = $hours_settings[$day_key . '_open'] ?? '09:00';
                        $close_time = $hours_settings[$day_key . '_close'] ?? '17:00';
                        if ($open_time && $close_time) {
                            $slots = [['open' => $open_time, 'close' => $close_time]];
                        }
                    }
                ?>
                <tr class="day-row" data-day="<?php echo $day_key; ?>">
                    <td class="day-name">
                        <strong><?php echo esc_html($day_label); ?></strong>
                    </td>
                    <td class="day-status">
                        <select class="status-select" name="<?php echo esc_attr($option_names['hours']); ?>[<?php echo $day_key; ?>_status]" data-day="<?php echo $day_key; ?>">
                            <option value="open" <?php selected(!$is_closed && !$is_24hours); ?>><?php _e('Open', 'sky360'); ?></option>
                            <option value="closed" <?php selected($is_closed); ?>><?php _e('Closed', 'sky360'); ?></option>
                            <option value="24hours" <?php selected($is_24hours); ?>><?php _e('24 hours', 'sky360'); ?></option>
                        </select>
                        <input type="hidden" name="<?php echo esc_attr($option_names['hours']); ?>[<?php echo $day_key; ?>_closed]" value="<?php echo $is_closed ? '1' : '0'; ?>" class="closed-input" />
                        <input type="hidden" name="<?php echo esc_attr($option_names['hours']); ?>[<?php echo $day_key; ?>_24hours]" value="<?php echo $is_24hours ? '1' : '0'; ?>" class="24hours-input" />
                    </td>
                    <td class="day-hours">
                        <div class="hours-slots" id="hours-<?php echo $day_key; ?>" style="display: <?php echo ($is_closed || $is_24hours) ? 'none' : 'block'; ?>;">
                            <?php 
                            if (!$is_closed && !$is_24hours) :
                                $slot_index = 0;
                                foreach ($slots as $slot) :
                            ?>
                            <div class="time-slot">
                                <input type="time" 
                                       name="<?php echo esc_attr($option_names['hours']); ?>[<?php echo $day_key; ?>_slots][<?php echo $slot_index; ?>][open]" 
                                       value="<?php echo esc_attr($slot['open'] ?? '09:00'); ?>" 
                                       class="time-input" />
                                <span class="time-separator">-</span>
                                <input type="time" 
                                       name="<?php echo esc_attr($option_names['hours']); ?>[<?php echo $day_key; ?>_slots][<?php echo $slot_index; ?>][close]" 
                                       value="<?php echo esc_attr($slot['close'] ?? '17:00'); ?>" 
                                       class="time-input" />
                                <?php if ($slot_index > 0) : ?>
                                <button type="button" class="button-link remove-slot" data-day="<?php echo $day_key; ?>">
                                    <span class="dashicons dashicons-trash"></span>
                                </button>
                                <?php endif; ?>
                            </div>
                            <?php 
                                $slot_index++;
                                endforeach;
                            endif; 
                            ?>
                        </div>
                        <div class="status-message" style="display: <?php echo $is_closed ? 'block' : 'none'; ?>;">
                            <?php echo $is_closed ? __('Closed all day', 'sky360') : ''; ?>
                        </div>
                        <div class="status-message-24" style="display: <?php echo $is_24hours ? 'block' : 'none'; ?>;">
                            <?php echo $is_24hours ? __('Open 24 hours', 'sky360') : ''; ?>
                        </div>
                    </td>
                    <td class="day-actions">
                        <button type="button" class="button-link add-hours" data-day="<?php echo $day_key; ?>" style="display: <?php echo (!$is_closed && !$is_24hours) ? 'inline-block' : 'none'; ?>;">
                            <?php _e('Add hours', 'sky360'); ?>
                        </button>
                    </td>
                </tr>
                <?php endforeach; ?>
            </tbody>
        </table>
        <?php
    }
    
    /**
 * Render action buttons
 */
private function render_action_buttons($review_stats) {
    ?>
    <div class="action-buttons">
        <button type="button" class="button" id="test-api">
            <?php _e('Test API Connection', 'sky360'); ?>
        </button>
        <button type="button" class="button" id="update-metadata">
            <?php _e('Update Review Count', 'sky360'); ?>
        </button>
        <?php if (class_exists('Sky_SEO_Reviews_Database')) : ?>
        <button type="button" class="button button-primary" id="fetch-new-reviews">
            <?php _e('Fetch New Reviews', 'sky360'); ?>
        </button>
        <?php if ($review_stats && $review_stats['total_reviews'] > 0) : ?>
        <button type="button" class="button" id="view-reviews">
            <?php _e('View Stored Reviews', 'sky360'); ?>
        </button>
        <?php endif; ?>
        <?php endif; ?>
        <span class="spinner" style="float: none;"></span>
    </div>
    <?php
}
    /**
     * Render inline scripts
     */
    private function render_inline_scripts() {
        ?>
        <script>
        // Toggle API key visibility
        function toggleApiKeyVisibility(button) {
            var input = document.getElementById('serpapi_key');
            var icon = button.querySelector('.dashicons');
            
            if (input.type === 'password') {
                input.type = 'text';
                icon.classList.remove('dashicons-visibility');
                icon.classList.add('dashicons-hidden');
            } else {
                input.type = 'password';
                icon.classList.remove('dashicons-hidden');
                icon.classList.add('dashicons-visibility');
            }
        }
        
        // Copy Place ID to clipboard
        function copyPlaceId() {
            var input = document.getElementById('place_id');
            var button = event.target;
            var originalText = button.textContent;
            
            if (input.value) {
                // Create temporary textarea to copy from
                var temp = document.createElement('textarea');
                temp.value = input.value;
                document.body.appendChild(temp);
                temp.select();
                document.execCommand('copy');
                document.body.removeChild(temp);
                
                // Update button to show success
                button.textContent = '<?php echo esc_js(__('Copied!', 'sky360')); ?>';
                button.classList.add('copied');
                
                // Reset button after 2 seconds
                setTimeout(function() {
                    button.textContent = originalText;
                    button.classList.remove('copied');
                }, 2000);
            }
        }
        </script>
        <?php
    }
    
    /**
     * Handle review actions
     */
    private function handle_review_actions() {
        $action = isset($_GET['action']) ? sanitize_text_field($_GET['action']) : '';
        $review_id = isset($_GET['review']) ? intval($_GET['review']) : 0;
        
        // Handle single review actions
        if ($review_id && in_array($action, ['hide', 'show', 'delete'])) {
            check_admin_referer('review_action_' . $review_id);
            
            global $wpdb;
            $table_name = $wpdb->prefix . 'sky_seo_reviews';
            
            switch ($action) {
                case 'hide':
                    $wpdb->update($table_name, ['is_visible' => 0], ['id' => $review_id]);
                    wp_redirect(add_query_arg(['page' => 'sky-seo-business-api', 'tab' => 'reviews', 'message' => 'hidden'], admin_url('admin.php')));
                    exit;
                    
                case 'show':
                    $wpdb->update($table_name, ['is_visible' => 1], ['id' => $review_id]);
                    wp_redirect(add_query_arg(['page' => 'sky-seo-business-api', 'tab' => 'reviews', 'message' => 'shown'], admin_url('admin.php')));
                    exit;
                    
                case 'delete':
                    $wpdb->delete($table_name, ['id' => $review_id]);
                    wp_redirect(add_query_arg(['page' => 'sky-seo-business-api', 'tab' => 'reviews', 'message' => 'deleted'], admin_url('admin.php')));
                    exit;
            }
        }
        
        // Handle bulk actions
        if (isset($_POST['bulk_action']) && isset($_POST['review_ids'])) {
            check_admin_referer('bulk_review_action');
            
            $bulk_action = sanitize_text_field($_POST['bulk_action']);
            $review_ids = array_map('intval', $_POST['review_ids']);
            
            if (!empty($review_ids)) {
                global $wpdb;
                $table_name = $wpdb->prefix . 'sky_seo_reviews';
                $placeholders = implode(',', array_fill(0, count($review_ids), '%d'));
                
                switch ($bulk_action) {
                    case 'hide':
                        $wpdb->query($wpdb->prepare("UPDATE $table_name SET is_visible = 0 WHERE id IN ($placeholders)", $review_ids));
                        break;
                    case 'show':
                        $wpdb->query($wpdb->prepare("UPDATE $table_name SET is_visible = 1 WHERE id IN ($placeholders)", $review_ids));
                        break;
                    case 'delete':
                        $wpdb->query($wpdb->prepare("DELETE FROM $table_name WHERE id IN ($placeholders)", $review_ids));
                        break;
                }
                
                wp_redirect(add_query_arg(['page' => 'sky-seo-business-api', 'tab' => 'reviews', 'message' => 'bulk_updated'], admin_url('admin.php')));
                exit;
            }
        }
    }
    
    /**
     * Get filtered reviews - UPDATED with platform filter
     */
    private function get_filtered_reviews($place_id, $search, $filter_rating, $filter_visibility, $filter_platform, $per_page, $offset) {
        global $wpdb;
        $table_name = $wpdb->prefix . 'sky_seo_reviews';
        
        $where_clauses = ["(place_id = %s OR data_id = %s)"];
        $where_values = [$place_id, $place_id];
        
        // Search filter
        if (!empty($search)) {
            $where_clauses[] = "(author_name LIKE %s OR text LIKE %s)";
            $search_term = '%' . $wpdb->esc_like($search) . '%';
            $where_values[] = $search_term;
            $where_values[] = $search_term;
        }
        
        // Rating filter
        if ($filter_rating > 0) {
            $where_clauses[] = "rating = %d";
            $where_values[] = $filter_rating;
        }
        
        // Visibility filter
        if ($filter_visibility === 'visible') {
            $where_clauses[] = "is_visible = 1";
        } elseif ($filter_visibility === 'hidden') {
            $where_clauses[] = "is_visible = 0";
        }
        
        // Platform filter
        if ($filter_platform !== 'all' && !empty($filter_platform)) {
            $where_clauses[] = "platform = %s";
            $where_values[] = $filter_platform;
        }
        
        $where_sql = implode(' AND ', $where_clauses);
        
        // Get total count
        $count_query = "SELECT COUNT(*) FROM $table_name WHERE $where_sql";
        $total_items = $wpdb->get_var($wpdb->prepare($count_query, $where_values));
        
        // Get reviews
        $reviews_query = "SELECT * FROM $table_name WHERE $where_sql ORDER BY review_time DESC LIMIT %d OFFSET %d";
        $query_values = array_merge($where_values, [$per_page, $offset]);
        $reviews = $wpdb->get_results($wpdb->prepare($reviews_query, $query_values), ARRAY_A);
        
        return [
            'reviews' => $reviews,
            'total' => $total_items
        ];
    }
    
    /**
     * Display admin notices
     */
    private function display_review_notices() {
        if (isset($_GET['message'])) {
            $message_type = sanitize_text_field($_GET['message']);
            $messages = [
                'hidden' => __('Review hidden successfully.', 'sky360'),
                'shown' => __('Review shown successfully.', 'sky360'),
                'deleted' => __('Review deleted successfully.', 'sky360'),
                'updated' => __('Review updated successfully.', 'sky360'),
                'bulk_updated' => __('Selected reviews updated successfully.', 'sky360'),
            ];
            
            if (isset($messages[$message_type])) {
                echo '<div class="notice notice-success is-dismissible"><p>' . esc_html($messages[$message_type]) . '</p></div>';
            }
        }
    }
    
    /**
     * Render reviews filters - UPDATED with platform filter
     */
    private function render_reviews_filters($search, $filter_rating, $filter_visibility, $filter_platform) {
        ?>
        <div class="reviews-filters">
            <form method="get" action="<?php echo admin_url('admin.php'); ?>">
                <input type="hidden" name="page" value="sky-seo-business-api">
                <input type="hidden" name="tab" value="reviews">
                
                <div class="filter-row">
                    <input type="search" 
                           name="s" 
                           value="<?php echo esc_attr($search); ?>" 
                           placeholder="<?php esc_attr_e('Search reviews...', 'sky360'); ?>"
                           class="search-input">
                    
                    <select name="platform" class="filter-select">
                        <option value="all"><?php _e('All Platforms', 'sky360'); ?></option>
                        <option value="google" <?php selected($filter_platform, 'google'); ?>><?php _e('Google', 'sky360'); ?></option>
                        <option value="facebook" <?php selected($filter_platform, 'facebook'); ?>><?php _e('Facebook', 'sky360'); ?></option>
                        <option value="trustpilot" <?php selected($filter_platform, 'trustpilot'); ?>><?php _e('Trustpilot', 'sky360'); ?></option>
                        <option value="yelp" <?php selected($filter_platform, 'yelp'); ?>><?php _e('Yelp', 'sky360'); ?></option>
                        <option value="tripadvisor" <?php selected($filter_platform, 'tripadvisor'); ?>><?php _e('TripAdvisor', 'sky360'); ?></option>
                        <option value="booking" <?php selected($filter_platform, 'booking'); ?>><?php _e('Booking.com', 'sky360'); ?></option>
                        <option value="other" <?php selected($filter_platform, 'other'); ?>><?php _e('Other', 'sky360'); ?></option>
                    </select>
                    
                    <select name="rating" class="filter-select">
                        <option value="0"><?php _e('All Ratings', 'sky360'); ?></option>
                        <?php for ($i = 5; $i >= 1; $i--) : ?>
                            <option value="<?php echo $i; ?>" <?php selected($filter_rating, $i); ?>>
                                <?php echo str_repeat('★', $i); ?> (<?php echo $i; ?>)
                            </option>
                        <?php endfor; ?>
                    </select>
                    
                    <select name="visibility" class="filter-select">
                        <option value="all" <?php selected($filter_visibility, 'all'); ?>><?php _e('All Reviews', 'sky360'); ?></option>
                        <option value="visible" <?php selected($filter_visibility, 'visible'); ?>><?php _e('Visible Only', 'sky360'); ?></option>
                        <option value="hidden" <?php selected($filter_visibility, 'hidden'); ?>><?php _e('Hidden Only', 'sky360'); ?></option>
                    </select>
                    
                    <input type="submit" class="button" value="<?php esc_attr_e('Filter', 'sky360'); ?>">
                    
                    <?php if (!empty($search) || $filter_rating > 0 || $filter_visibility !== 'all' || $filter_platform !== 'all') : ?>
                        <a href="<?php echo admin_url('admin.php?page=sky-seo-business-api&tab=reviews'); ?>" class="button">
                            <?php _e('Clear Filters', 'sky360'); ?>
                        </a>
                    <?php endif; ?>
                </div>
            </form>
        </div>
        <?php
    }
    
    /**
     * Render no reviews message
     */
    private function render_no_reviews_message($place_id) {
        ?>
        <div class="no-reviews-message">
            <?php if (empty($place_id)) : ?>
                <p><?php _e('Please configure your Place ID in the Business API settings first.', 'sky360'); ?></p>
                <p><a href="<?php echo admin_url('admin.php?page=sky-seo-business-api'); ?>" class="button">
                    <?php _e('Go to Settings', 'sky360'); ?>
                </a></p>
            <?php else : ?>
                <p><?php _e('No reviews found matching your criteria.', 'sky360'); ?></p>
                <p>
                    <a href="#" class="button button-primary" id="add-first-manual-review">
                        <?php _e('Add Manual Review', 'sky360'); ?>
                    </a>
                    <a href="#" class="button" id="fetch-first-reviews">
                        <?php _e('Fetch Google Reviews', 'sky360'); ?>
                    </a>
                </p>
            <?php endif; ?>
        </div>
        <?php
    }
    
    /**
     * Render bulk actions
     */
    private function render_bulk_actions($position = 'top') {
        ?>
        <div class="tablenav <?php echo $position; ?>">
            <div class="alignleft actions bulkactions">
                <label for="bulk-action-selector-<?php echo $position; ?>" class="screen-reader-text">
                    <?php _e('Select bulk action', 'sky360'); ?>
                </label>
                <select name="bulk_action" id="bulk-action-selector-<?php echo $position; ?>">
                    <option value=""><?php _e('Bulk Actions', 'sky360'); ?></option>
                    <option value="hide"><?php _e('Hide Selected', 'sky360'); ?></option>
                    <option value="show"><?php _e('Show Selected', 'sky360'); ?></option>
                    <option value="delete"><?php _e('Delete Selected', 'sky360'); ?></option>
                </select>
                <input type="submit" class="button action" value="<?php esc_attr_e('Apply', 'sky360'); ?>">
            </div>
            <br class="clear">
        </div>
        <?php
    }
    
    /**
     * Render reviews table header - UPDATED with platform column
     */
    private function render_reviews_table_header() {
        ?>
        <thead>
            <tr>
                <td class="manage-column column-cb check-column">
                    <label class="screen-reader-text" for="cb-select-all-1">
                        <?php _e('Select All', 'sky360'); ?>
                    </label>
                    <input id="cb-select-all-1" type="checkbox">
                </td>
                <th class="manage-column column-platform" style="width: 50px;"><?php _e('Source', 'sky360'); ?></th>
                <th class="manage-column column-author"><?php _e('Author', 'sky360'); ?></th>
                <th class="manage-column column-rating"><?php _e('Rating', 'sky360'); ?></th>
                <th class="manage-column column-review"><?php _e('Review', 'sky360'); ?></th>
                <th class="manage-column column-date"><?php _e('Date', 'sky360'); ?></th>
                <th class="manage-column column-status"><?php _e('Status', 'sky360'); ?></th>
                <th class="manage-column column-actions"><?php _e('Actions', 'sky360'); ?></th>
            </tr>
        </thead>
        <?php
    }
    
    /**
     * Render review row - UPDATED with platform icon
     */
    private function render_review_row($review) {
        $row_class = $review['is_visible'] ? '' : 'hidden-review';
        $platform = $review['platform'] ?? 'google';
        
        // Platform icons
        $platform_icons = [
            'google' => '<svg width="18" height="18" viewBox="0 0 18 18" fill="none" xmlns="http://www.w3.org/2000/svg">
                        <path d="M17.64 9.2c0-.637-.057-1.251-.164-1.84H9v3.481h4.844c-.209 1.125-.843 2.078-1.796 2.717v2.258h2.908c1.702-1.567 2.684-3.874 2.684-6.615z" fill="#4285F4"/>
                        <path d="M9.003 18c2.43 0 4.467-.806 5.956-2.18L12.05 13.56c-.806.54-1.836.86-3.047.86-2.344 0-4.328-1.584-5.036-3.711H.96v2.332C2.44 15.983 5.485 18 9.003 18z" fill="#34A853"/>
                        <path d="M3.964 10.712c-.18-.54-.282-1.117-.282-1.71 0-.593.102-1.17.282-1.71V4.96H.957C.347 6.175 0 7.55 0 9.002c0 1.452.348 2.827.957 4.042l3.007-2.332z" fill="#FBBC05"/>
                        <path d="M9.003 3.58c1.321 0 2.508.454 3.44 1.345l2.582-2.58C13.464.891 11.428 0 9.002 0 5.485 0 2.44 2.017.96 4.958L3.967 7.29c.708-2.127 2.692-3.71 5.036-3.71z" fill="#EA4335"/>
                    </svg>',
            'facebook' => '<svg width="18" height="18" viewBox="0 0 18 18" fill="none" xmlns="http://www.w3.org/2000/svg">
                        <path d="M18 9C18 4.02944 13.9706 0 9 0C4.02944 0 0 4.02944 0 9C0 13.4921 3.29168 17.2155 7.59375 17.8907V11.6016H5.30859V9H7.59375V7.01719C7.59375 4.76156 8.93742 3.51562 10.9932 3.51562C11.9775 3.51562 13.0078 3.69141 13.0078 3.69141V5.90625H11.873C10.755 5.90625 10.4062 6.60006 10.4062 7.3125V9H12.9023L12.5033 11.6016H10.4062V17.8907C14.7083 17.2155 18 13.4921 18 9Z" fill="#1877F2"/>
                    </svg>',
            'trustpilot' => '<svg width="18" height="18" viewBox="0 0 18 18" fill="none" xmlns="http://www.w3.org/2000/svg">
                        <rect width="18" height="18" rx="2" fill="#00B67A"/>
                        <path d="M9 2L11.2 7.2H16.8L12.3 10.8L14.5 16L9 12.4L3.5 16L5.7 10.8L1.2 7.2H6.8L9 2Z" fill="white"/>
                    </svg>',
            'yelp' => '<svg width="18" height="18" viewBox="0 0 18 18" fill="none" xmlns="http://www.w3.org/2000/svg">
                        <rect width="18" height="18" rx="2" fill="#D32323"/>
                        <path d="M9 4L6 9H9V14L12 9H9V4Z" fill="white"/>
                    </svg>',
            'tripadvisor' => '<svg width="18" height="18" viewBox="0 0 18 18" fill="none" xmlns="http://www.w3.org/2000/svg">
                        <circle cx="9" cy="9" r="9" fill="#34E0A1"/>
                        <circle cx="6" cy="9" r="2" fill="white"/>
                        <circle cx="12" cy="9" r="2" fill="white"/>
                        <circle cx="6" cy="9" r="1" fill="#000"/>
                        <circle cx="12" cy="9" r="1" fill="#000"/>
                    </svg>',
            'booking' => '<svg width="18" height="18" viewBox="0 0 18 18" fill="none" xmlns="http://www.w3.org/2000/svg">
                        <rect width="18" height="18" rx="2" fill="#003580"/>
                        <text x="4" y="13" fill="white" font-size="10" font-weight="bold">B.</text>
                    </svg>',
            'houzz' => '<svg width="18" height="18" viewBox="0 0 18 18" fill="none" xmlns="http://www.w3.org/2000/svg">
                        <rect width="18" height="18" rx="2" fill="#4DBC15"/>
                        <path d="M9 3V15M5 7H9V11H5V7ZM9 7H13V11H9" stroke="white" stroke-width="2"/>
                    </svg>',
            'bbb' => '<svg width="18" height="18" viewBox="0 0 18 18" fill="none" xmlns="http://www.w3.org/2000/svg">
                        <rect width="18" height="18" rx="2" fill="#005A8C"/>
                        <text x="2" y="13" fill="white" font-size="8" font-weight="bold">BBB</text>
                    </svg>',
            'angi' => '<svg width="18" height="18" viewBox="0 0 18 18" fill="none" xmlns="http://www.w3.org/2000/svg">
                        <rect width="18" height="18" rx="2" fill="#FF6138"/>
                        <text x="4" y="13" fill="white" font-size="10" font-weight="bold">A</text>
                    </svg>',
            'homeadvisor' => '<svg width="18" height="18" viewBox="0 0 18 18" fill="none" xmlns="http://www.w3.org/2000/svg">
                        <rect width="18" height="18" rx="2" fill="#F68B1E"/>
                        <path d="M9 3L3 9H5V15H13V9H15L9 3Z" fill="white"/>
                    </svg>',
            'thumbtack' => '<svg width="18" height="18" viewBox="0 0 18 18" fill="none" xmlns="http://www.w3.org/2000/svg">
                        <rect width="18" height="18" rx="2" fill="#009FD9"/>
                        <circle cx="9" cy="7" r="3" fill="white"/>
                        <path d="M9 10V15" stroke="white" stroke-width="2"/>
                    </svg>',
            'zillow' => '<svg width="18" height="18" viewBox="0 0 18 18" fill="none" xmlns="http://www.w3.org/2000/svg">
                        <rect width="18" height="18" rx="2" fill="#006AFF"/>
                        <text x="5" y="13" fill="white" font-size="10" font-weight="bold">Z</text>
                    </svg>',
            'airbnb' => '<svg width="18" height="18" viewBox="0 0 18 18" fill="none" xmlns="http://www.w3.org/2000/svg">
                        <rect width="18" height="18" rx="2" fill="#FF5A5F"/>
                        <path d="M9 4C7 7 6 9 6 11C6 13 7.5 14 9 14C10.5 14 12 13 12 11C12 9 11 7 9 4Z" fill="white"/>
                    </svg>',
            'amazon' => '<svg width="18" height="18" viewBox="0 0 18 18" fill="none" xmlns="http://www.w3.org/2000/svg">
                        <rect width="18" height="18" rx="2" fill="#FF9900"/>
                        <text x="4" y="13" fill="white" font-size="10" font-weight="bold">a</text>
                    </svg>',
            'other' => '<svg width="18" height="18" viewBox="0 0 18 18" fill="none" xmlns="http://www.w3.org/2000/svg">
                        <rect width="18" height="18" rx="2" fill="#6B7280"/>
                        <circle cx="5" cy="9" r="1.5" fill="white"/>
                        <circle cx="9" cy="9" r="1.5" fill="white"/>
                        <circle cx="13" cy="9" r="1.5" fill="white"/>
                    </svg>'
        ];
        ?>
        <tr class="<?php echo esc_attr($row_class); ?>">
            <th scope="row" class="check-column">
                <input type="checkbox" name="review_ids[]" value="<?php echo esc_attr($review['id']); ?>">
            </th>
            <td class="column-platform">
                <span title="<?php echo esc_attr(ucfirst($platform)); ?>">
                    <?php echo $platform_icons[$platform] ?? $platform_icons['google']; ?>
                </span>
            </td>
            <td class="column-author">
                <strong><?php echo esc_html($review['author_name']); ?></strong>
                <?php if (!empty($review['author_photo'])) : ?>
                    <br><img src="<?php echo esc_url($review['author_photo']); ?>" 
                             alt="" 
                             class="author-photo-small">
                <?php endif; ?>
                <?php if (!empty($review['is_manual'])) : ?>
                    <br><small style="color: #666;"><?php _e('(Manual)', 'sky360'); ?></small>
                <?php endif; ?>
            </td>
            <td class="column-rating">
                <span class="rating-stars"><?php echo str_repeat('★', $review['rating']); ?></span>
                <br><span class="rating-number">(<?php echo $review['rating']; ?>/5)</span>
            </td>
            <td class="column-review">
                <div class="review-text-preview" id="review-text-<?php echo $review['id']; ?>">
                    <?php echo esc_html(wp_trim_words($review['text'] ?: __('(No review text)', 'sky360'), 30)); ?>
                </div>
                <?php if (!empty($review['text']) && str_word_count($review['text']) > 30) : ?>
                    <a href="#" class="expand-review" data-review-id="<?php echo $review['id']; ?>">
                        <?php _e('Read more', 'sky360'); ?>
                    </a>
                <?php endif; ?>
            </td>
            <td class="column-date">
                <?php echo date_i18n(get_option('date_format'), strtotime($review['review_time'])); ?>
                <br><small><?php echo esc_html($review['relative_time']); ?></small>
            </td>
            <td class="column-status">
                <?php if ($review['is_visible']) : ?>
                    <span class="status-badge status-visible"><?php _e('Visible', 'sky360'); ?></span>
                <?php else : ?>
                    <span class="status-badge status-hidden"><?php _e('Hidden', 'sky360'); ?></span>
                <?php endif; ?>
            </td>
            <td class="column-actions">
                <div class="row-actions">
                    <span class="edit">
                        <a href="#" 
                           class="edit-review" 
                           data-review-id="<?php echo $review['id']; ?>"
                           data-review-text="<?php echo esc_attr($review['text']); ?>"
                           data-is-manual="<?php echo $review['is_manual'] ?? 0; ?>"
                           data-platform="<?php echo esc_attr($platform); ?>"
                           data-author-name="<?php echo esc_attr($review['author_name']); ?>"
                           data-rating="<?php echo esc_attr($review['rating']); ?>"
                           data-review-date="<?php echo esc_attr(date('Y-m-d', strtotime($review['review_time']))); ?>"
                           data-author-photo="<?php echo esc_attr($review['author_photo']); ?>">
                            <?php _e('Edit', 'sky360'); ?>
                        </a> | 
                    </span>
                    <?php if ($review['is_visible']) : ?>
                        <span class="hide">
                            <a href="<?php echo wp_nonce_url(admin_url('admin.php?page=sky-seo-business-api&tab=reviews&action=hide&review=' . $review['id']), 'review_action_' . $review['id']); ?>" 
                               class="hide-link">
                                <?php _e('Hide', 'sky360'); ?>
                            </a> | 
                        </span>
                    <?php else : ?>
                        <span class="show">
                            <a href="<?php echo wp_nonce_url(admin_url('admin.php?page=sky-seo-business-api&tab=reviews&action=show&review=' . $review['id']), 'review_action_' . $review['id']); ?>" 
                               class="show-link">
                                <?php _e('Show', 'sky360'); ?>
                            </a> | 
                        </span>
                    <?php endif; ?>
                    <span class="delete">
                        <a href="<?php echo wp_nonce_url(admin_url('admin.php?page=sky-seo-business-api&tab=reviews&action=delete&review=' . $review['id']), 'review_action_' . $review['id']); ?>" 
                           class="delete-link"
                           onclick="return confirm('<?php esc_attr_e('Are you sure you want to delete this review?', 'sky360'); ?>');">
                            <?php _e('Delete', 'sky360'); ?>
                        </a>
                    </span>
                </div>
            </td>
        </tr>
        <?php
    }
    
    /**
     * Render pagination - UPDATED with platform parameter
     */
    private function render_pagination($page, $total_pages, $search, $filter_rating, $filter_visibility, $filter_platform, $total_items) {
        if ($total_pages <= 1) {
            return;
        }
        
        $pagination_args = [
            'base' => add_query_arg(['paged' => '%#%', 'tab' => 'reviews']),
            'format' => '',
            'current' => $page,
            'total' => $total_pages,
            'prev_text' => '&laquo;',
            'next_text' => '&raquo;',
        ];
        
        // Preserve filters in pagination links
        $pagination_args['add_args']['tab'] = 'reviews';
        if (!empty($search)) {
            $pagination_args['add_args']['s'] = $search;
        }
        if ($filter_rating > 0) {
            $pagination_args['add_args']['rating'] = $filter_rating;
        }
        if ($filter_visibility !== 'all') {
            $pagination_args['add_args']['visibility'] = $filter_visibility;
        }
        if ($filter_platform !== 'all') {
            $pagination_args['add_args']['platform'] = $filter_platform;
        }
        
        ?>
        <div class="tablenav bottom">
            <div class="tablenav-pages">
                <span class="displaying-num">
                    <?php printf(_n('%s item', '%s items', $total_items, 'sky360'), number_format_i18n($total_items)); ?>
                </span>
                <?php echo paginate_links($pagination_args); ?>
            </div>
        </div>
        <?php
    }
    
    /**
     * Render review modals
     */
    private function render_review_modals() {
        ?>
        <!-- Edit Review Modal -->
        <div id="edit-review-modal" class="review-modal" style="display:none;">
            <div class="modal-content">
                <h3><?php _e('Edit Review', 'sky360'); ?></h3>
                <form id="edit-review-form" method="post">
                    <?php wp_nonce_field('edit_review', 'edit_review_nonce'); ?>
                    <input type="hidden" id="edit-review-id" name="review_id" value="">
                    <textarea id="edit-review-text" name="review_text" rows="6" class="large-text"></textarea>
                    <div class="modal-actions">
                        <button type="submit" class="button button-primary"><?php _e('Save Changes', 'sky360'); ?></button>
                        <button type="button" class="button cancel-edit"><?php _e('Cancel', 'sky360'); ?></button>
                    </div>
                </form>
            </div>
        </div>
        <?php
    }
    
    /**
     * Render manual review modal
     */
    private function render_manual_review_modal() {
        ?>
        <!-- Add/Edit Manual Review Modal -->
        <div id="manual-review-modal" class="review-modal" style="display:none;">
            <div class="modal-content" style="max-width: 700px;">
                <h3 id="manual-review-title"><?php _e('Add Manual Review', 'sky360'); ?></h3>
                <form id="manual-review-form" method="post" enctype="multipart/form-data">
                    <?php wp_nonce_field('manual_review', 'manual_review_nonce'); ?>
                    <input type="hidden" id="manual-review-id" name="review_id" value="">
                    
                    <table class="form-table">
                        <tr>
                            <th scope="row">
                                <label for="review-platform"><?php _e('Platform', 'sky360'); ?></label>
                            </th>
                            <td>
                                <select id="review-platform" name="platform" class="regular-text">
                                    <option value="google"><?php _e('Google', 'sky360'); ?></option>
                                    <option value="facebook"><?php _e('Facebook', 'sky360'); ?></option>
                                    <option value="trustpilot"><?php _e('Trustpilot', 'sky360'); ?></option>
                                    <option value="yelp"><?php _e('Yelp', 'sky360'); ?></option>
                                    <option value="tripadvisor"><?php _e('TripAdvisor', 'sky360'); ?></option>
                                    <option value="booking"><?php _e('Booking.com', 'sky360'); ?></option>
                                    <option value="houzz"><?php _e('Houzz', 'sky360'); ?></option>
                                    <option value="bbb"><?php _e('BBB', 'sky360'); ?></option>
                                    <option value="angi"><?php _e('Angi', 'sky360'); ?></option>
                                    <option value="homeadvisor"><?php _e('HomeAdvisor', 'sky360'); ?></option>
                                    <option value="thumbtack"><?php _e('Thumbtack', 'sky360'); ?></option>
                                    <option value="zillow"><?php _e('Zillow', 'sky360'); ?></option>
                                    <option value="airbnb"><?php _e('Airbnb', 'sky360'); ?></option>
                                    <option value="amazon"><?php _e('Amazon', 'sky360'); ?></option>
                                    <option value="other"><?php _e('Other', 'sky360'); ?></option>
                                </select>
                            </td>
                        </tr>
                        <tr>
                            <th scope="row">
                                <label for="review-author-name"><?php _e('Author Name', 'sky360'); ?> <span class="required">*</span></label>
                            </th>
                            <td>
                                <input type="text" id="review-author-name" name="author_name" class="regular-text" required>
                            </td>
                        </tr>
                        <tr>
                            <th scope="row">
                                <label for="review-rating"><?php _e('Rating', 'sky360'); ?> <span class="required">*</span></label>
                            </th>
                            <td>
                                <select id="review-rating" name="rating" class="regular-text" required>
                                    <option value="5"><?php echo str_repeat('★', 5); ?> (5)</option>
                                    <option value="4"><?php echo str_repeat('★', 4); ?> (4)</option>
                                    <option value="3"><?php echo str_repeat('★', 3); ?> (3)</option>
                                    <option value="2"><?php echo str_repeat('★', 2); ?> (2)</option>
                                    <option value="1"><?php echo str_repeat('★', 1); ?> (1)</option>
                                </select>
                            </td>
                        </tr>
                        <tr>
                            <th scope="row">
                                <label for="review-date"><?php _e('Review Date', 'sky360'); ?></label>
                            </th>
                            <td>
                                <input type="date" id="review-date" name="review_date" class="regular-text" value="<?php echo date('Y-m-d'); ?>">
                            </td>
                        </tr>
                        <tr>
                            <th scope="row">
                                <label for="review-author-photo"><?php _e('Author Photo', 'sky360'); ?></label>
                            </th>
                            <td>
                                <div id="author-photo-preview" style="margin-bottom: 10px; display: none;">
                                    <img src="" alt="" style="max-width: 100px; max-height: 100px; border-radius: 50%;">
                                </div>
                                <input type="file" id="review-author-photo" name="author_photo" accept="image/*">
                                <input type="hidden" id="review-author-photo-id" name="author_photo_id" value="">
                                <p class="description"><?php _e('Upload a photo for the reviewer (optional)', 'sky360'); ?></p>
                            </td>
                        </tr>
                        <tr>
                            <th scope="row">
                                <label for="review-text"><?php _e('Review Text', 'sky360'); ?></label>
                            </th>
                            <td>
                                <textarea id="review-text" name="text" rows="6" class="large-text"></textarea>
                            </td>
                        </tr>
                    </table>
                    
                    <div class="modal-actions">
                        <button type="submit" class="button button-primary"><?php _e('Save Review', 'sky360'); ?></button>
                        <button type="button" class="button cancel-manual-review"><?php _e('Cancel', 'sky360'); ?></button>
                    </div>
                </form>
            </div>
        </div>
        
        <style>
        .review-modal .form-table th {
            width: 150px;
            padding: 15px 10px;
        }
        .review-modal .form-table td {
            padding: 15px 10px;
        }
        .review-modal .required {
            color: #dc3232;
        }
        #author-photo-preview img {
            border: 2px solid #ddd;
            object-fit: cover;
        }
        .modal-content {
            max-height: 90vh;
            overflow-y: auto;
        }
        .column-platform {
            text-align: center;
        }
        .column-platform svg {
            vertical-align: middle;
        }
        </style>
        <?php
    }
    
    /**
     * Render reviews scripts - UPDATED with manual review support
     */
    private function render_reviews_scripts($settings) {
        ?>
        <script>
        jQuery(document).ready(function($) {
            // Select all checkboxes
            $('#cb-select-all-1, #cb-select-all-2').on('change', function() {
                $('.reviews-table tbody input[type="checkbox"]').prop('checked', this.checked);
            });
            
            // Refresh reviews
            $('#refresh-reviews, #fetch-first-reviews').on('click', function(e) {
                e.preventDefault();
                var $button = $(this);
                $button.prop('disabled', true);
                
                $.post(ajaxurl, {
                    action: 'sky_seo_fetch_new_reviews',
                    place_id: '<?php echo esc_js($settings['place_id'] ?? ''); ?>',
                    force: false,
                    _ajax_nonce: '<?php echo wp_create_nonce('sky_seo_api_nonce'); ?>'
                }, function(response) {
                    if (response.success) {
                        alert(response.data.message);
                        location.reload();
                    } else {
                        alert(response.data || 'Error fetching reviews');
                        $button.prop('disabled', false);
                    }
                });
            });
            
            // Expand review text
            $('.expand-review').on('click', function(e) {
                e.preventDefault();
                var reviewId = $(this).data('review-id');
                var $preview = $('#review-text-' + reviewId);
                
                // Toggle expanded state
                if ($preview.hasClass('expanded')) {
                    // Contract
                    var shortText = $preview.data('short-text');
                    $preview.removeClass('expanded').text(shortText);
                    $(this).text('<?php echo esc_js(__('Read more', 'sky360')); ?>');
                } else {
                    // Expand - fetch full text via AJAX
                    var $link = $(this);
                    $.post(ajaxurl, {
                        action: 'sky_seo_get_review_text',
                        review_id: reviewId,
                        _ajax_nonce: '<?php echo wp_create_nonce('get_review_text'); ?>'
                    }, function(response) {
                        if (response.success) {
                            $preview.data('short-text', $preview.text());
                            $preview.addClass('expanded').text(response.data);
                            $link.text('<?php echo esc_js(__('Read less', 'sky360')); ?>');
                        }
                    });
                }
            });
            
            // Edit review modal - UPDATED for manual reviews
            $('.edit-review').on('click', function(e) {
                e.preventDefault();
                var $link = $(this);
                var reviewId = $link.data('review-id');
                var reviewText = $link.data('review-text');
                var isManual = $link.data('is-manual');
                
                if (isManual) {
                    // Open manual review modal for editing
                    $('#manual-review-title').text('<?php echo esc_js(__('Edit Manual Review', 'sky360')); ?>');
                    $('#manual-review-id').val(reviewId);
                    $('#review-platform').val($link.data('platform'));
                    $('#review-author-name').val($link.data('author-name'));
                    $('#review-rating').val($link.data('rating'));
                    $('#review-date').val($link.data('review-date'));
                    $('#review-text').val(reviewText);
                    
                    // Show existing photo if available
                    var authorPhoto = $link.data('author-photo');
                    if (authorPhoto) {
                        $('#author-photo-preview img').attr('src', authorPhoto);
                        $('#author-photo-preview').show();
                    } else {
                        $('#author-photo-preview').hide();
                    }
                    
                    $('#manual-review-modal').fadeIn();
                } else {
                    // Open simple edit modal for API reviews
                    $('#edit-review-id').val(reviewId);
                    $('#edit-review-text').val(reviewText);
                    $('#edit-review-modal').fadeIn();
                }
            });
            
            $('.cancel-edit, .review-modal').on('click', function(e) {
                if (e.target === this) {
                    $('#edit-review-modal').fadeOut();
                }
            });
            
            // Handle edit form submission
            $('#edit-review-form').on('submit', function(e) {
                e.preventDefault();
                
                var $form = $(this);
                var reviewId = $('#edit-review-id').val();
                var reviewText = $('#edit-review-text').val();
                
                $.post(ajaxurl, {
                    action: 'sky_seo_edit_review',
                    review_id: reviewId,
                    review_text: reviewText,
                    _ajax_nonce: $('#edit_review_nonce').val()
                }, function(response) {
                    if (response.success) {
                        // Update the preview text
                        $('#review-text-' + reviewId).text(reviewText.substring(0, 150) + (reviewText.length > 150 ? '...' : ''));
                        $('#edit-review-modal').fadeOut();
                        
                        // Show success message
                        $('<div class="notice notice-success is-dismissible"><p>' + response.data + '</p></div>')
                            .insertAfter('.wp-header-end')
                            .delay(3000)
                            .fadeOut();
                    } else {
                        alert(response.data || 'Error updating review');
                    }
                });
            });
            
            // Add manual review button
            $('#add-manual-review, #add-first-manual-review').on('click', function(e) {
                e.preventDefault();
                $('#manual-review-title').text('<?php echo esc_js(__('Add Manual Review', 'sky360')); ?>');
                $('#manual-review-form')[0].reset();
                $('#manual-review-id').val('');
                $('#review-date').val('<?php echo date('Y-m-d'); ?>');
                $('#author-photo-preview').hide();
                $('#manual-review-modal').fadeIn();
            });
            
            // Close manual review modal
            $('.cancel-manual-review').on('click', function() {
                $('#manual-review-modal').fadeOut();
            });
            
            // Handle manual review photo upload
            $('#review-author-photo').on('change', function() {
                var file = this.files[0];
                if (file) {
                    var reader = new FileReader();
                    reader.onload = function(e) {
                        $('#author-photo-preview img').attr('src', e.target.result);
                        $('#author-photo-preview').show();
                    };
                    reader.readAsDataURL(file);
                }
            });
            
            // Handle manual review form submission
            $('#manual-review-form').on('submit', function(e) {
                e.preventDefault();
                
                var $form = $(this);
                var $submitButton = $form.find('button[type="submit"]');
                var formData = new FormData(this);
                
                // If there's a photo, upload it first
                var photoFile = $('#review-author-photo')[0].files[0];
                
                $submitButton.prop('disabled', true).text('<?php echo esc_js(__('Saving...', 'sky360')); ?>');
                
                if (photoFile) {
                    // Upload photo first
                    var photoData = new FormData();
                    photoData.append('author_photo', photoFile);
                    photoData.append('action', 'sky_seo_upload_review_photo');
                    photoData.append('_ajax_nonce', '<?php echo wp_create_nonce('sky_seo_api_nonce'); ?>');
                    
                    $.ajax({
                        url: ajaxurl,
                        type: 'POST',
                        data: photoData,
                        processData: false,
                        contentType: false,
                        success: function(response) {
                            if (response.success) {
                                // Add photo ID to form data
                                saveManualReview(response.data.attachment_id);
                            } else {
                                alert(response.data || 'Error uploading photo');
                                $submitButton.prop('disabled', false).text('<?php echo esc_js(__('Save Review', 'sky360')); ?>');
                            }
                        }
                    });
                } else {
                    // No photo, save directly
                    saveManualReview(0);
                }
                
                function saveManualReview(photoId) {
                    var reviewData = {
                        action: 'sky_seo_save_manual_review',
                        review_id: $('#manual-review-id').val(),
                        platform: $('#review-platform').val(),
                        author_name: $('#review-author-name').val(),
                        rating: $('#review-rating').val(),
                        text: $('#review-text').val(),
                        review_date: $('#review-date').val(),
                        author_photo_id: photoId,
                        _ajax_nonce: '<?php echo wp_create_nonce('sky_seo_api_nonce'); ?>'
                    };
                    
                    $.post(ajaxurl, reviewData, function(response) {
                        if (response.success) {
                            $('#manual-review-modal').fadeOut();
                            
                            // Show success message and reload
                            $('<div class="notice notice-success is-dismissible"><p>' + response.data.message + '</p></div>')
                                .insertAfter('.wp-header-end');
                            
                            setTimeout(function() {
                                location.reload();
                            }, 1000);
                        } else {
                            alert(response.data || 'Error saving review');
                            $submitButton.prop('disabled', false).text('<?php echo esc_js(__('Save Review', 'sky360')); ?>');
                        }
                    });
                }
            });
            
            // Update View Reviews button to use tab
            $('#view-reviews').on('click', function(e) {
                e.preventDefault();
                window.location.href = '<?php echo admin_url('admin.php?page=sky-seo-business-api&tab=reviews'); ?>';
            });
        });
        </script>
        <?php
    }
}