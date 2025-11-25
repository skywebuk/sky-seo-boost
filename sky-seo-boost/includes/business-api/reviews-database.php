<?php
/**
 * Sky SEO Reviews Database Manager - Updated with Manual Reviews
 * Handles permanent storage of Google Reviews and Manual Reviews
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
 * Reviews Database Manager Class
 */
class Sky_SEO_Reviews_Database {
    
    /**
     * Database table name
     */
    private $table_name;
    
    /**
     * Singleton instance
     */
    private static $instance = null;
    
    /**
     * Option names
     */
    private $option_name = 'sky_seo_business_settings';
    private $advanced_option_name = 'sky_seo_business_advanced_settings';
    private $db_version_option = 'sky_seo_reviews_db_version';
    private $current_db_version = '1.1.0'; // Updated version
    
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
        global $wpdb;
        $this->table_name = $wpdb->prefix . 'sky_seo_reviews';
        
        // Initialize
        $this->init();
    }
    
    /**
     * Initialize the class
     */
    private function init() {
        // Hook for database initialization - use high priority to ensure it runs
        add_action('init', [$this, 'maybe_create_table'], 5);
        add_action('plugins_loaded', [$this, 'check_db_version'], 5);
        
        // Admin hooks
        add_action('admin_init', [$this, 'handle_admin_actions']);
        
        // AJAX handlers
        add_action('wp_ajax_sky_seo_fetch_new_reviews', [$this, 'ajax_fetch_new_reviews']);
        add_action('wp_ajax_sky_seo_delete_review', [$this, 'ajax_delete_review']);
        add_action('wp_ajax_sky_seo_toggle_review_visibility', [$this, 'ajax_toggle_review_visibility']);
        add_action('wp_ajax_sky_seo_save_manual_review', [$this, 'ajax_save_manual_review']);
        add_action('wp_ajax_sky_seo_upload_review_photo', [$this, 'ajax_upload_review_photo']);
        
        // Schedule cron job for automatic review fetching
        add_action('sky_seo_fetch_reviews_cron', [$this, 'cron_fetch_new_reviews']);
        
        // Schedule cron job for automatic metadata update
        add_action('sky_seo_update_metadata_cron', [$this, 'cron_update_metadata']);
        
        // Setup cron schedule
        add_action('init', [$this, 'schedule_cron_events']);
        
        // Add custom cron schedule
        add_filter('cron_schedules', [$this, 'add_cron_schedules']);
        
        // Load Elementor widgets when Elementor is ready
        add_action('elementor/widgets/register', [$this, 'register_elementor_widgets'], 20);
        
        // Ensure widgets are loaded for frontend
        add_action('elementor/frontend/init', [$this, 'load_widget_files']);
    }
    
    /**
     * Add cron schedules
     */
    public function add_cron_schedules($schedules) {
        $schedules['every_48_hours'] = [
            'interval' => 48 * HOUR_IN_SECONDS,
            'display' => __('Every 48 hours', 'sky-seo-boost')
        ];
        return $schedules;
    }
    
    /**
     * Schedule cron events
     */
    public function schedule_cron_events() {
        if (!wp_next_scheduled('sky_seo_fetch_reviews_cron')) {
            wp_schedule_event(time(), 'every_48_hours', 'sky_seo_fetch_reviews_cron');
        }
        
        // Add daily metadata update
        if (!wp_next_scheduled('sky_seo_update_metadata_cron')) {
            wp_schedule_event(time(), 'daily', 'sky_seo_update_metadata_cron');
        }
    }
    
    /**
     * Check and update database version
     */
    public function check_db_version() {
        $installed_version = get_option($this->db_version_option, '0');
        
        if (version_compare($installed_version, $this->current_db_version, '<')) {
            $this->create_table();
            update_option($this->db_version_option, $this->current_db_version);
        }
    }
    
    /**
     * Create or update reviews table
     */
    public function maybe_create_table() {
        // Check if we need to create/update the table
        $this->check_db_version();
    }
    
    /**
     * Create the database table - UPDATED with platform column
     */
    public function create_table() {
        global $wpdb;
        
        $charset_collate = $wpdb->get_charset_collate();
        
        // First, create the table with dbDelta (this handles both create and update)
        $sql = "CREATE TABLE {$this->table_name} (
            id BIGINT(20) UNSIGNED NOT NULL AUTO_INCREMENT,
            review_id VARCHAR(255) NOT NULL,
            place_id VARCHAR(255) NOT NULL,
            data_id VARCHAR(255) DEFAULT NULL,
            platform VARCHAR(50) DEFAULT 'google',
            author_name VARCHAR(255) NOT NULL,
            author_photo TEXT DEFAULT NULL,
            author_url TEXT DEFAULT NULL,
            rating TINYINT(1) NOT NULL,
            text TEXT DEFAULT NULL,
            review_time DATETIME NOT NULL,
            relative_time VARCHAR(100) DEFAULT NULL,
            fetched_at DATETIME NOT NULL,
            is_visible TINYINT(1) DEFAULT 1,
            is_manual TINYINT(1) DEFAULT 0,
            language_code VARCHAR(10) DEFAULT 'en',
            response_text TEXT DEFAULT NULL,
            response_time DATETIME DEFAULT NULL,
            verified TINYINT(1) DEFAULT 1,
            PRIMARY KEY (id),
            UNIQUE KEY review_id (review_id),
            KEY idx_place_id (place_id),
            KEY idx_data_id (data_id),
            KEY idx_platform (platform),
            KEY idx_review_time (review_time),
            KEY idx_rating (rating),
            KEY idx_fetched_at (fetched_at),
            KEY idx_visible_rating (is_visible, rating),
            KEY idx_is_manual (is_manual)
        ) $charset_collate;";
        
        require_once(ABSPATH . 'wp-admin/includes/upgrade.php');
        dbDelta($sql);
        
        // Verify table exists
        $table_exists = $wpdb->get_var("SHOW TABLES LIKE '{$this->table_name}'") === $this->table_name;
        
        if ($table_exists) {
            // Ensure new columns exist for existing tables
            $this->ensure_columns_exist();
        }
        
        return $table_exists;
    }
    
    /**
     * Ensure new columns exist in existing tables
     */
    private function ensure_columns_exist() {
        global $wpdb;
        
        // Get existing columns
        $existing_columns = $wpdb->get_col("SHOW COLUMNS FROM {$this->table_name}");
        
        // Check and add platform column if missing
        if (!in_array('platform', $existing_columns)) {
            $wpdb->query("ALTER TABLE {$this->table_name} ADD COLUMN `platform` VARCHAR(50) DEFAULT 'google' AFTER `data_id`");
            
            // Update existing records
            $wpdb->query("UPDATE {$this->table_name} SET `platform` = 'google' WHERE `platform` IS NULL");
        }
        
        // Check and add is_manual column if missing
        if (!in_array('is_manual', $existing_columns)) {
            $wpdb->query("ALTER TABLE {$this->table_name} ADD COLUMN `is_manual` TINYINT(1) DEFAULT 0 AFTER `is_visible`");
            
            // Update existing records
            $wpdb->query("UPDATE {$this->table_name} SET `is_manual` = 0 WHERE `is_manual` IS NULL");
        }
        
        // Ensure indexes exist (using IF NOT EXISTS is safe here)
        $wpdb->query("CREATE INDEX IF NOT EXISTS idx_platform ON {$this->table_name} (platform)");
        $wpdb->query("CREATE INDEX IF NOT EXISTS idx_is_manual ON {$this->table_name} (is_manual)");
    }
    
    /**
     * Handle admin actions
     */
    public function handle_admin_actions() {
        // Force create table if requested
        if (isset($_GET['sky_seo_create_table']) && current_user_can('manage_options')) {
            check_admin_referer('sky_seo_create_table');
            
            $created = $this->create_table();
            
            if ($created) {
                wp_redirect(add_query_arg([
                    'page' => 'sky-seo-business-api',
                    'message' => 'table_created'
                ], admin_url('admin.php')));
            } else {
                wp_die('Failed to create table. Check error logs.');
            }
            exit;
        }
    }
    
    /**
     * Get reviews from database - UPDATED to include platform filter
     */
    public function get_reviews($args = []) {
        global $wpdb;
        
        // Ensure table exists
        if (!$this->table_exists()) {
            return [];
        }
        
        $defaults = [
            'place_id' => '',
            'limit' => 50,
            'offset' => 0,
            'min_rating' => 0,
            'order_by' => 'review_time',
            'order' => 'DESC',
            'visible_only' => true,
            'with_text_only' => false,
            'platform' => 'all' // New parameter
        ];
        
        $args = wp_parse_args($args, $defaults);
        
        // Build WHERE clause
        $where_clauses = ['1=1'];
        $prepare_args = [];
        
        if (!empty($args['place_id'])) {
            // MODIFIED: Always include manual reviews regardless of place_id
            $where_clauses[] = "((place_id = %s OR data_id = %s) OR is_manual = 1)";
            $prepare_args[] = $args['place_id'];
            $prepare_args[] = $args['place_id'];
        }
        
        if ($args['min_rating'] > 0) {
            $where_clauses[] = "rating >= %d";
            $prepare_args[] = intval($args['min_rating']);
        }
        
        if ($args['visible_only']) {
            $where_clauses[] = "is_visible = 1";
        }
        
        if ($args['with_text_only']) {
            $where_clauses[] = "text IS NOT NULL AND text != ''";
        }
        
        // Platform filter
        if ($args['platform'] !== 'all' && !empty($args['platform'])) {
            $where_clauses[] = "platform = %s";
            $prepare_args[] = $args['platform'];
        }
        
        $where_sql = implode(' AND ', $where_clauses);
        
        // Add ORDER BY
        $allowed_order_by = ['review_time', 'rating', 'fetched_at', 'id'];
        $order_by = in_array($args['order_by'], $allowed_order_by) ? $args['order_by'] : 'review_time';
        $order = strtoupper($args['order']) === 'ASC' ? 'ASC' : 'DESC';
        
        // Build query
        $sql = "SELECT * FROM {$this->table_name} WHERE {$where_sql} ORDER BY {$order_by} {$order}";
        
        // Add LIMIT
        if ($args['limit'] > 0) {
            $sql .= " LIMIT %d, %d";
            $prepare_args[] = intval($args['offset']);
            $prepare_args[] = intval($args['limit']);
        }
        
        // Execute query
        if (!empty($prepare_args)) {
            $sql = $wpdb->prepare($sql, $prepare_args);
        }
        
        $results = $wpdb->get_results($sql, ARRAY_A);
        
        // Format results
        $reviews = [];
        foreach ($results as $row) {
            $reviews[] = $this->format_review_data($row);
        }
        
        return $reviews;
    }
    
    /**
     * Get review statistics - UPDATED to include platform breakdown
     */
    public function get_review_stats($place_id = '') {
        global $wpdb;
        
        // Ensure table exists
        if (!$this->table_exists()) {
            return [
                'total_reviews' => 0,
                'average_rating' => 0,
                'rating_breakdown' => [5 => 0, 4 => 0, 3 => 0, 2 => 0, 1 => 0],
                'platform_breakdown' => ['google' => 0, 'facebook' => 0, 'trustpilot' => 0],
                'latest_review_time' => null,
                'last_fetch_time' => null
            ];
        }
        
        $where_clause = '';
        $prepare_args = [];
        
        if (!empty($place_id)) {
            // MODIFIED: Always include manual reviews in stats
            $where_clause = "WHERE ((place_id = %s OR data_id = %s) OR is_manual = 1) AND is_visible = 1";
            $prepare_args = [$place_id, $place_id];
        } else {
            $where_clause = "WHERE is_visible = 1";
        }
        
        // Get statistics
        $query = "SELECT 
                    COUNT(*) as total_reviews,
                    AVG(rating) as average_rating,
                    SUM(CASE WHEN rating = 5 THEN 1 ELSE 0 END) as five_star,
                    SUM(CASE WHEN rating = 4 THEN 1 ELSE 0 END) as four_star,
                    SUM(CASE WHEN rating = 3 THEN 1 ELSE 0 END) as three_star,
                    SUM(CASE WHEN rating = 2 THEN 1 ELSE 0 END) as two_star,
                    SUM(CASE WHEN rating = 1 THEN 1 ELSE 0 END) as one_star,
                    SUM(CASE WHEN platform = 'google' THEN 1 ELSE 0 END) as google_count,
                    SUM(CASE WHEN platform = 'facebook' THEN 1 ELSE 0 END) as facebook_count,
                    SUM(CASE WHEN platform = 'trustpilot' THEN 1 ELSE 0 END) as trustpilot_count,
                    MAX(review_time) as latest_review_time,
                    MAX(fetched_at) as last_fetch_time
                  FROM {$this->table_name} 
                  {$where_clause}";
        
        if (!empty($prepare_args)) {
            $query = $wpdb->prepare($query, $prepare_args);
        }
        
        $stats = $wpdb->get_row($query, ARRAY_A);
        
        // Format stats
        return [
            'total_reviews' => intval($stats['total_reviews'] ?? 0),
            'average_rating' => round(floatval($stats['average_rating'] ?? 0), 1),
            'rating_breakdown' => [
                5 => intval($stats['five_star'] ?? 0),
                4 => intval($stats['four_star'] ?? 0),
                3 => intval($stats['three_star'] ?? 0),
                2 => intval($stats['two_star'] ?? 0),
                1 => intval($stats['one_star'] ?? 0)
            ],
            'platform_breakdown' => [
                'google' => intval($stats['google_count'] ?? 0),
                'facebook' => intval($stats['facebook_count'] ?? 0),
                'trustpilot' => intval($stats['trustpilot_count'] ?? 0)
            ],
            'latest_review_time' => $stats['latest_review_time'],
            'last_fetch_time' => $stats['last_fetch_time']
        ];
    }
    
    /**
     * AJAX: Save manual review
     */
    public function ajax_save_manual_review() {
        check_ajax_referer('sky_seo_api_nonce', '_ajax_nonce');
        
        if (!current_user_can('manage_options')) {
            wp_send_json_error(__('Permission denied', 'sky-seo-boost'));
        }
        
        global $wpdb;
        
        // Get and validate input
        $review_id = isset($_POST['review_id']) ? intval($_POST['review_id']) : 0;
        $platform = sanitize_text_field($_POST['platform'] ?? 'google');
        $author_name = sanitize_text_field($_POST['author_name'] ?? '');
        $rating = intval($_POST['rating'] ?? 5);
        $text = sanitize_textarea_field($_POST['text'] ?? '');
        $review_date = sanitize_text_field($_POST['review_date'] ?? '');
        $author_photo_id = intval($_POST['author_photo_id'] ?? 0);
        
        // Validate required fields
        if (empty($author_name)) {
            wp_send_json_error(__('Author name is required', 'sky-seo-boost'));
        }
        
        if ($rating < 1 || $rating > 5) {
            wp_send_json_error(__('Invalid rating', 'sky-seo-boost'));
        }
        
        // Get place_id from settings
        $settings = get_option($this->option_name, []);
        $place_id = $settings['place_id'] ?? 'manual_reviews_only';
        
        // Parse review date
        $review_time = !empty($review_date) ? date('Y-m-d H:i:s', strtotime($review_date)) : current_time('mysql');
        
        // Get author photo URL
        $author_photo = '';
        if ($author_photo_id > 0) {
            $author_photo = wp_get_attachment_url($author_photo_id);
        }
        
        // Prepare data
        $data = [
            'place_id' => $place_id,
            'data_id' => $place_id,
            'platform' => $platform,
            'author_name' => $author_name,
            'author_photo' => $author_photo,
            'author_url' => '',
            'rating' => $rating,
            'text' => $text,
            'review_time' => $review_time,
            'relative_time' => human_time_diff(strtotime($review_time), current_time('timestamp')) . ' ago',
            'fetched_at' => current_time('mysql'),
            'is_visible' => 1,
            'is_manual' => 1,
            'language_code' => 'en',
            'verified' => 0 // Manual reviews are not verified
        ];
        
        if ($review_id > 0) {
            // Update existing review
            unset($data['review_id']); // Don't update the unique ID
            $result = $wpdb->update(
                $this->table_name,
                $data,
                ['id' => $review_id],
                $this->get_column_formats($data),
                ['%d']
            );
            
            if ($result !== false) {
                wp_send_json_success([
                    'message' => __('Review updated successfully', 'sky-seo-boost'),
                    'review_id' => $review_id
                ]);
            } else {
                wp_send_json_error(__('Failed to update review', 'sky-seo-boost'));
            }
        } else {
            // Generate unique review ID for new review
            $data['review_id'] = 'manual_' . $platform . '_' . uniqid();
            
            // Insert new review
            $result = $wpdb->insert(
                $this->table_name,
                $data,
                $this->get_column_formats($data)
            );
            
            if ($result !== false) {
                wp_send_json_success([
                    'message' => __('Review added successfully', 'sky-seo-boost'),
                    'review_id' => $wpdb->insert_id
                ]);
            } else {
                wp_send_json_error(__('Failed to add review', 'sky-seo-boost'));
            }
        }
    }
    
    /**
     * AJAX: Upload review photo
     */
    public function ajax_upload_review_photo() {
        check_ajax_referer('sky_seo_api_nonce', '_ajax_nonce');
        
        if (!current_user_can('manage_options')) {
            wp_send_json_error(__('Permission denied', 'sky-seo-boost'));
        }
        
        if (empty($_FILES['author_photo'])) {
            wp_send_json_error(__('No file uploaded', 'sky-seo-boost'));
        }
        
        // Handle file upload
        require_once(ABSPATH . 'wp-admin/includes/image.php');
        require_once(ABSPATH . 'wp-admin/includes/file.php');
        require_once(ABSPATH . 'wp-admin/includes/media.php');
        
        $upload_overrides = ['test_form' => false];
        $movefile = wp_handle_upload($_FILES['author_photo'], $upload_overrides);
        
        if ($movefile && !isset($movefile['error'])) {
            // Create attachment
            $attachment = [
                'guid' => $movefile['url'],
                'post_mime_type' => $movefile['type'],
                'post_title' => sanitize_file_name($_FILES['author_photo']['name']),
                'post_content' => '',
                'post_status' => 'inherit'
            ];
            
            $attach_id = wp_insert_attachment($attachment, $movefile['file']);
            
            if (!is_wp_error($attach_id)) {
                // Generate metadata
                $attach_data = wp_generate_attachment_metadata($attach_id, $movefile['file']);
                wp_update_attachment_metadata($attach_id, $attach_data);
                
                wp_send_json_success([
                    'attachment_id' => $attach_id,
                    'url' => $movefile['url']
                ]);
            } else {
                wp_send_json_error(__('Failed to create attachment', 'sky-seo-boost'));
            }
        } else {
            wp_send_json_error($movefile['error'] ?? __('Upload failed', 'sky-seo-boost'));
        }
    }
    
    /**
     * Get existing review IDs from database
     */
    private function get_existing_review_ids($place_id) {
        global $wpdb;
        
        $query = $wpdb->prepare(
            "SELECT review_id FROM {$this->table_name} 
             WHERE (place_id = %s OR data_id = %s) AND is_manual = 0",
            $place_id,
            $place_id
        );
        
        return $wpdb->get_col($query);
    }
    
    /**
     * Save reviews to database
     */
    public function save_reviews($reviews, $place_id) {
        global $wpdb;
        
        // Ensure table exists
        if (!$this->table_exists()) {
            $this->create_table();
        }
        
        $inserted = 0;
        $updated = 0;
        $skipped = 0;
        $errors = [];
        
        // Get all existing reviews for this place to check for duplicates
        $existing_reviews = $wpdb->get_results($wpdb->prepare(
            "SELECT review_id, id, text, rating, review_time 
             FROM {$this->table_name} 
             WHERE (place_id = %s OR data_id = %s) AND is_manual = 0",
            $place_id,
            $place_id
        ), OBJECT_K);
        
        foreach ($reviews as $review) {
            // Prepare data
            $data = [
                'review_id' => $review['review_id'] ?? $this->generate_review_id($review),
                'place_id' => $place_id,
                'data_id' => $review['data_id'] ?? $place_id,
                'platform' => 'google', // API reviews are always Google
                'author_name' => $review['user']['name'] ?? $review['author_name'] ?? 'Anonymous',
                'author_photo' => $review['user']['thumbnail'] ?? $review['author_photo'] ?? '',
                'author_url' => $review['user']['link'] ?? $review['author_url'] ?? '',
                'rating' => intval($review['rating'] ?? 0),
                'text' => $review['snippet'] ?? $review['text'] ?? '',
                'relative_time' => $review['date'] ?? $review['time'] ?? '',
                'fetched_at' => current_time('mysql'),
                'is_manual' => 0,
                'language_code' => $review['language'] ?? 'en',
                'verified' => isset($review['verified']) ? 1 : 0
            ];
            
            // Parse review time
            $review_time = $this->parse_review_time($data['relative_time']);
            if ($review_time) {
                $data['review_time'] = $review_time;
            } else {
                $data['review_time'] = current_time('mysql');
            }
            
            // Check if review exists
            if (isset($existing_reviews[$data['review_id']])) {
                $existing = $existing_reviews[$data['review_id']];
                
                // Check if we need to update (only if content changed)
                $needs_update = false;
                
                if ($existing->text !== $data['text'] || 
                    $existing->rating != $data['rating'] ||
                    strtotime($existing->review_time) !== strtotime($data['review_time'])) {
                    $needs_update = true;
                }
                
                if ($needs_update) {
                    // Update existing review (but don't overwrite visibility)
                    unset($data['is_visible']);
                    $result = $wpdb->update(
                        $this->table_name,
                        $data,
                        ['id' => $existing->id],
                        $this->get_column_formats($data),
                        ['%d']
                    );
                    
                    if ($result !== false) {
                        $updated++;
                    } else {
                        $errors[] = $wpdb->last_error;
                    }
                } else {
                    $skipped++;
                }
            } else {
                // Insert new review
                $data['is_visible'] = 1; // Default to visible
                $result = $wpdb->insert(
                    $this->table_name,
                    $data,
                    $this->get_column_formats($data)
                );
                
                if ($result !== false) {
                    $inserted++;
                } else {
                    $errors[] = $wpdb->last_error;
                }
            }
        }
        
        return [
            'inserted' => $inserted,
            'updated' => $updated,
            'skipped' => $skipped,
            'errors' => $errors,
            'total' => count($reviews)
        ];
    }
    
    /**
     * Fetch metadata from SerpAPI - NEW METHOD
     */
    private function fetch_google_metadata($place_id, $api_key) {
        $url = 'https://serpapi.com/search.json';
        
        // First try to get place info from reviews endpoint
        $params = [
            'engine' => 'google_maps_reviews',
            'data_id' => $place_id,
            'api_key' => $api_key
        ];
        
        $response = wp_remote_get(add_query_arg($params, $url), [
            'timeout' => 30,
            'headers' => [
                'User-Agent' => 'Sky SEO Boost WordPress Plugin'
            ]
        ]);
        
        if (!is_wp_error($response)) {
            $body = wp_remote_retrieve_body($response);
            $data = json_decode($body, true);
            
            // Extract place info if available
            if (isset($data['place_info'])) {
                $total_reviews = intval($data['place_info']['reviews'] ?? 0);
                $average_rating = floatval($data['place_info']['rating'] ?? 0);
                
                if ($total_reviews > 0 || $average_rating > 0) {
                    return [
                        'total_reviews' => $total_reviews,
                        'average_rating' => $average_rating
                    ];
                }
            }
        }
        
        return false;
    }
    
    /**
     * Simplified fetch_from_serpapi method - UPDATED to also fetch metadata
     */
    private function fetch_from_serpapi($place_id, $api_key) {
        $url = 'https://serpapi.com/search.json';
        
        // Base parameters
        $params = [
            'engine' => 'google_maps_reviews',
            'api_key' => $api_key,
            'hl' => 'en',
            'sort_by' => 'newestFirst'  // Just use newest first
        ];
        
        // Determine the correct parameter based on place_id format
        if (strpos($place_id, '0x') === 0) {
            $params['data_id'] = $place_id;
        } else {
            $params['place_id'] = $place_id;
        }
        
        // Get existing review IDs
        $existing_review_ids = $this->get_existing_review_ids($place_id);
        
        // Make API request
        $response = wp_remote_get(add_query_arg($params, $url), [
            'timeout' => 30,
            'headers' => [
                'User-Agent' => 'Sky SEO Boost WordPress Plugin'
            ]
        ]);
        
        if (is_wp_error($response)) {
            error_log('Sky SEO API Error: ' . $response->get_error_message());
            return [];
        }
        
        $body = wp_remote_retrieve_body($response);
        $data = json_decode($body, true);
        
        // Extract and save metadata if available
        if (isset($data['place_info'])) {
            $metadata = [
                'total_reviews' => intval($data['place_info']['reviews'] ?? 0),
                'average_rating' => floatval($data['place_info']['rating'] ?? 0),
                'last_updated' => current_time('mysql')
            ];
            
            // Save to option
            update_option('sky_seo_google_business_meta_' . md5($place_id), $metadata);
            
            error_log(sprintf(
                'Sky SEO: Updated Google metadata - Total reviews: %d, Rating: %.1f',
                $metadata['total_reviews'],
                $metadata['average_rating']
            ));
        }
        
        if (!isset($data['reviews']) || !is_array($data['reviews'])) {
            return [];
        }
        
        $new_reviews = [];
        
        // Process reviews
        foreach ($data['reviews'] as $review) {
            $review_id = $review['review_id'] ?? $this->generate_review_id($review);
            
            // Skip if we already have this review
            if (in_array($review_id, $existing_review_ids)) {
                continue;
            }
            
            $new_reviews[] = $review;
        }
        
        error_log(sprintf(
            'Sky SEO: Fetched %d reviews, %d are new',
            count($data['reviews']),
            count($new_reviews)
        ));
        
        return $new_reviews;
    }
    
    /**
     * Simplified fetch_new_reviews method - UPDATED
     */
    public function fetch_new_reviews($place_id = '', $force = false) {
        // Get settings
        $settings = get_option($this->option_name, []);
        
        if (empty($place_id)) {
            $place_id = $settings['place_id'] ?? '';
        }
        
        if (empty($place_id) || empty($settings['serpapi_key'])) {
            return [
                'success' => false,
                'message' => __('Missing place ID or API key', 'sky-seo-boost')
            ];
        }
        
        // Check if we should fetch (48 hours since last fetch)
        if (!$force) {
            $stats = $this->get_review_stats($place_id);
            if (!empty($stats['last_fetch_time'])) {
                $last_fetch = strtotime($stats['last_fetch_time']);
                $hours_since = (time() - $last_fetch) / 3600;
                
                if ($hours_since < 48) {
                    return [
                        'success' => false,
                        'message' => sprintf(
                            __('Reviews were fetched %.1f hours ago. Next fetch in %.1f hours.', 'sky-seo-boost'),
                            $hours_since,
                            48 - $hours_since
                        )
                    ];
                }
            }
        }
        
        // Check API limits
        if (class_exists('Sky_SEO_Business_API')) {
            $business_api = Sky_SEO_Business_API::get_instance();
            $api_check = $business_api->can_make_api_calls(1);
            if (!$api_check['allowed']) {
                return [
                    'success' => false,
                    'message' => $api_check['message']
                ];
            }
        }
        
        // Get current stats before fetching
        $stats_before = $this->get_review_stats($place_id);
        
        // Fetch from SerpApi (this now also updates metadata)
        $reviews = $this->fetch_from_serpapi($place_id, $settings['serpapi_key']);
        
        // Always track API usage even if no new reviews
        if (class_exists('Sky_SEO_Business_API')) {
            $business_api = Sky_SEO_Business_API::get_instance();
            $business_api->track_api_usage(1);
        }
        
        if (empty($reviews)) {
            return [
                'success' => true,
                'message' => __('No new reviews found. All reviews are up to date.', 'sky-seo-boost'),
                'data' => [
                    'fetched' => [
                        'inserted' => 0,
                        'updated' => 0,
                        'total' => 0
                    ],
                    'stats_before' => $stats_before,
                    'stats_after' => $stats_before
                ]
            ];
        }
        
        // Save to database
        $result = $this->save_reviews($reviews, $place_id);
        
        // Clear any cached data
        $this->clear_cache($place_id);
        
        // Get stats after fetching
        $stats_after = $this->get_review_stats($place_id);
        
        return [
            'success' => true,
            'message' => sprintf(
                __('Fetched %d reviews: %d new, %d updated. Total reviews: %d', 'sky-seo-boost'),
                $result['total'],
                $result['inserted'],
                $result['updated'],
                $stats_after['total_reviews']
            ),
            'data' => [
                'fetched' => $result,
                'stats_before' => $stats_before,
                'stats_after' => $stats_after
            ]
        ];
    }
    
    /**
     * Generate a unique review ID
     */
    private function generate_review_id($review) {
        $unique_string = ($review['user']['name'] ?? '') . 
                        ($review['rating'] ?? '') . 
                        ($review['date'] ?? $review['time'] ?? '') .
                        substr($review['snippet'] ?? $review['text'] ?? '', 0, 50);
        return md5($unique_string);
    }
    
    /**
     * Parse review time from relative string
     */
    private function parse_review_time($time_string) {
        if (empty($time_string)) {
            return false;
        }
        
        // Try to parse as absolute date first
        $timestamp = strtotime($time_string);
        if ($timestamp !== false && $timestamp > strtotime('2000-01-01')) {
            return date('Y-m-d H:i:s', $timestamp);
        }
        
        // Parse relative times
        $patterns = [
            '/(\d+)\s*hours?\s*ago/i' => '-$1 hours',
            '/(\d+)\s*days?\s*ago/i' => '-$1 days',
            '/(\d+)\s*weeks?\s*ago/i' => '-$1 weeks',
            '/(\d+)\s*months?\s*ago/i' => '-$1 months',
            '/(\d+)\s*years?\s*ago/i' => '-$1 years',
            '/yesterday/i' => '-1 day',
            '/today/i' => 'now',
            '/a\s*week\s*ago/i' => '-1 week',
            '/a\s*month\s*ago/i' => '-1 month',
            '/a\s*year\s*ago/i' => '-1 year'
        ];
        
        foreach ($patterns as $pattern => $replacement) {
            if (preg_match($pattern, $time_string, $matches)) {
                $time_str = preg_replace($pattern, $replacement, $time_string);
                $timestamp = strtotime($time_str);
                if ($timestamp !== false) {
                    return date('Y-m-d H:i:s', $timestamp);
                }
            }
        }
        
        return false;
    }
    
    /**
     * Format review data for output - UPDATED with platform
     */
    private function format_review_data($row) {
        return [
            'id' => intval($row['id']),
            'review_id' => $row['review_id'],
            'platform' => $row['platform'] ?? 'google',
            'author_name' => $row['author_name'],
            'author_photo' => $row['author_photo'],
            'author_url' => $row['author_url'],
            'rating' => intval($row['rating']),
            'text' => $row['text'],
            'time' => $row['relative_time'],
            'review_time' => $row['review_time'],
            'is_visible' => (bool) $row['is_visible'],
            'is_manual' => (bool) ($row['is_manual'] ?? 0),
            'has_response' => !empty($row['response_text']),
            'response_text' => $row['response_text'],
            'response_time' => $row['response_time'],
            'verified' => (bool) ($row['verified'] ?? 1)
        ];
    }
    
    /**
     * Get column formats for database operations
     */
    private function get_column_formats($data) {
        $formats = [];
        foreach ($data as $key => $value) {
            switch ($key) {
                case 'id':
                case 'rating':
                case 'is_visible':
                case 'is_manual':
                case 'verified':
                    $formats[] = '%d';
                    break;
                default:
                    $formats[] = '%s';
            }
        }
        return $formats;
    }
    
    /**
     * Check if table exists
     */
    private function table_exists() {
        global $wpdb;
        return $wpdb->get_var("SHOW TABLES LIKE '{$this->table_name}'") === $this->table_name;
    }
    
    /**
     * Clear cache for a place
     */
    private function clear_cache($place_id) {
        if (!empty($place_id)) {
            delete_transient('sky_seo_business_data_' . md5($place_id));
            delete_transient('sky_seo_business_data_id_' . md5($place_id));
            wp_cache_delete('business_data_' . md5($place_id), 'sky_seo_business');
            wp_cache_delete('business_data_widget', 'sky_seo_widget');
        }
    }
    
    /**
     * AJAX: Fetch new reviews
     */
    public function ajax_fetch_new_reviews() {
        check_ajax_referer('sky_seo_api_nonce', '_ajax_nonce');
        
        if (!current_user_can('manage_options')) {
            wp_send_json_error(__('Permission denied', 'sky-seo-boost'));
        }
        
        $place_id = sanitize_text_field($_POST['place_id'] ?? '');
        $force = isset($_POST['force']) && $_POST['force'] === 'true';
        
        $result = $this->fetch_new_reviews($place_id, $force);
        
        if ($result['success']) {
            wp_send_json_success($result);
        } else {
            wp_send_json_error($result['message']);
        }
    }
    
    /**
     * AJAX: Delete review (hide it)
     */
    public function ajax_delete_review() {
        check_ajax_referer('sky_seo_api_nonce', '_ajax_nonce');
        
        if (!current_user_can('manage_options')) {
            wp_send_json_error(__('Permission denied', 'sky-seo-boost'));
        }
        
        global $wpdb;
        
        $review_id = intval($_POST['review_id'] ?? 0);
        
        if ($review_id) {
            $result = $wpdb->update(
                $this->table_name,
                ['is_visible' => 0],
                ['id' => $review_id],
                ['%d'],
                ['%d']
            );
            
            if ($result !== false) {
                wp_send_json_success(__('Review hidden successfully', 'sky-seo-boost'));
            }
        }
        
        wp_send_json_error(__('Failed to hide review', 'sky-seo-boost'));
    }
    
    /**
     * AJAX: Toggle review visibility
     */
    public function ajax_toggle_review_visibility() {
        check_ajax_referer('sky_seo_api_nonce', '_ajax_nonce');
        
        if (!current_user_can('manage_options')) {
            wp_send_json_error(__('Permission denied', 'sky-seo-boost'));
        }
        
        global $wpdb;
        
        $review_id = intval($_POST['review_id'] ?? 0);
        
        if ($review_id) {
            // Get current visibility
            $current = $wpdb->get_var($wpdb->prepare(
                "SELECT is_visible FROM {$this->table_name} WHERE id = %d",
                $review_id
            ));
            
            $new_visibility = $current ? 0 : 1;
            
            $result = $wpdb->update(
                $this->table_name,
                ['is_visible' => $new_visibility],
                ['id' => $review_id],
                ['%d'],
                ['%d']
            );
            
            if ($result !== false) {
                wp_send_json_success([
                    'message' => $new_visibility ? __('Review shown', 'sky-seo-boost') : __('Review hidden', 'sky-seo-boost'),
                    'is_visible' => $new_visibility
                ]);
            }
        }
        
        wp_send_json_error(__('Failed to toggle review visibility', 'sky-seo-boost'));
    }
    
    /**
     * Cron job to fetch new reviews
     */
    public function cron_fetch_new_reviews() {
        $settings = get_option($this->option_name, []);
        
        if (!empty($settings['place_id']) && !empty($settings['serpapi_key'])) {
            $result = $this->fetch_new_reviews($settings['place_id'], false);
            
            // Log the result
            if ($result['success']) {
                error_log('Sky SEO Cron: ' . $result['message']);
            } else {
                error_log('Sky SEO Cron Error: ' . $result['message']);
            }
        }
    }
    
    /**
     * Cron job to update metadata daily
     */
    public function cron_update_metadata() {
        $settings = get_option($this->option_name, []);
        $place_id = $settings['place_id'] ?? '';
        
        if (!empty($place_id)) {
            // Clear old metadata
            delete_option('sky_seo_google_business_meta_' . md5($place_id));
            
            // Clear caches
            wp_cache_delete('business_data_widget', 'sky_seo_widget');
            wp_cache_delete('business_data_' . md5($place_id), 'sky_seo_business');
            
            // Fetch fresh metadata
            if (class_exists('Sky_SEO_Business_Info_Elementor_Widget')) {
                $widget = new Sky_SEO_Business_Info_Elementor_Widget();
                $metadata = $widget->fetch_google_metadata();
                
                if ($metadata) {
                    error_log(sprintf(
                        'Sky SEO Cron: Metadata updated - Reviews: %d, Rating: %.1f',
                        $metadata['total_reviews'],
                        $metadata['average_rating']
                    ));
                }
            }
        }
    }
    
    /**
     * Get data for widgets (replaces Business API method) - UPDATED
     */
    public function get_business_data() {
        $settings = get_option($this->option_name, []);
        $place_id = $settings['place_id'] ?? '';
        
        // MODIFIED: Allow manual reviews to show even without place_id
        // If no place_id, use a default one for manual reviews
        $use_place_id = !empty($place_id) ? $place_id : 'manual_reviews_only';
        
        // Get stats - will include all reviews (manual + API)
        $stats = $this->get_review_stats($use_place_id);
        
        // Get reviews - modified to always include manual reviews
        $reviews = $this->get_reviews([
            'place_id' => $use_place_id,
            'limit' => 100, // Get more reviews since we're not limited by API
            'visible_only' => true
        ]);
        
        // Get manual hours from Business API
        $opening_hours = [];
        $is_open_now = false;
        
        if (class_exists('Sky_SEO_Business_API')) {
            $business_api = Sky_SEO_Business_API::get_instance();
            $opening_hours = $business_api->get_manual_hours();
            $is_open_now = $business_api->is_business_open();
        }
        
        // Get Google metadata for official counts
        $google_meta = get_option('sky_seo_google_business_meta_' . md5($place_id), []);
        
        return [
            'name' => $settings['business_name'] ?? '',
            'place_id' => $use_place_id,
            'rating' => !empty($google_meta['average_rating']) ? $google_meta['average_rating'] : $stats['average_rating'],
            'total_reviews' => !empty($google_meta['total_reviews']) ? $google_meta['total_reviews'] : $stats['total_reviews'],
            'reviews' => $reviews,
            'opening_hours' => $opening_hours,
            'is_open_now' => $is_open_now,
            'stats' => $stats
        ];
    }
    
    /**
     * Register Elementor widgets
     */
    public function register_elementor_widgets($widgets_manager) {
        // Load widget files
        $widget_dir = plugin_dir_path(__FILE__);
        
        // Load Business Info Widget
        $info_widget_file = $widget_dir . 'elementor-business-info-widget.php';
        if (file_exists($info_widget_file)) {
            require_once $info_widget_file;
            if (class_exists('Sky_SEO_Business_Info_Elementor_Widget')) {
                try {
                    $widgets_manager->register(new Sky_SEO_Business_Info_Elementor_Widget());
                } catch (Exception $e) {
                    // Silent fail
                }
            }
        }
        
        // Load Reviews Widget
        $reviews_widget_file = $widget_dir . 'elementor-reviews-widget.php';
        if (file_exists($reviews_widget_file)) {
            require_once $reviews_widget_file;
            if (class_exists('Sky_SEO_Reviews_Elementor_Widget')) {
                try {
                    $widgets_manager->register(new Sky_SEO_Reviews_Elementor_Widget());
                } catch (Exception $e) {
                    // Silent fail
                }
            }
        }
    }
    
    /**
     * Load widget files for frontend
     */
    public function load_widget_files() {
        $widget_dir = plugin_dir_path(__FILE__);
        
        // Ensure widget files are loaded
        if (file_exists($widget_dir . 'elementor-business-info-widget.php')) {
            require_once $widget_dir . 'elementor-business-info-widget.php';
        }
        
        if (file_exists($widget_dir . 'elementor-reviews-widget.php')) {
            require_once $widget_dir . 'elementor-reviews-widget.php';
        }
    }
}

// Initialize the class on init hook to ensure WordPress is fully loaded
add_action('init', function() {
    Sky_SEO_Reviews_Database::get_instance();
}, 1); // High priority to ensure early initialization

// Activation hook to ensure database is created/updated
register_activation_hook(SKY_SEO_BOOST_FILE, function() {
    // Force database creation on activation
    $instance = Sky_SEO_Reviews_Database::get_instance();
    $instance->create_table();
    
    // Force version update
    update_option('sky_seo_reviews_db_version', '1.1.0');
});

// Clean up on deactivation
register_deactivation_hook(SKY_SEO_BOOST_FILE, function() {
    wp_clear_scheduled_hook('sky_seo_fetch_reviews_cron');
    wp_clear_scheduled_hook('sky_seo_update_metadata_cron');
});