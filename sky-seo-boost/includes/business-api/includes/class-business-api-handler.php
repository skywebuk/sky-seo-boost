<?php
/**
 * Sky SEO Business API Handler
 * Handles all API-related functionality and AJAX calls
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
 * Business API Handler Class
 */
class Sky_SEO_Business_API_Handler {
    
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
     * Track API usage with validation
     */
    public function track_api_usage($calls = 1) {
        $calls = absint($calls);
        if ($calls === 0) {
            return false;
        }
        
        $option_names = $this->get_option_names();
        $usage = get_option($option_names['usage'], $this->get_default_usage());
        
        $current_month = current_time('Y-m');
        $current_day = current_time('Y-m-d');
        
        // Initialize if needed
        if (!isset($usage['monthly_usage'][$current_month])) {
            $usage['monthly_usage'][$current_month] = 0;
        }
        if (!isset($usage['daily_usage'][$current_day])) {
            $usage['daily_usage'][$current_day] = 0;
        }
        
        // Update usage
        $usage['monthly_usage'][$current_month] += $calls;
        $usage['daily_usage'][$current_day] += $calls;
        
        // Clean old data (keep only current and last month)
        $usage['monthly_usage'] = array_slice($usage['monthly_usage'], -2, 2, true);
        $usage['daily_usage'] = array_slice($usage['daily_usage'], -31, 31, true);
        
        return update_option($option_names['usage'], $usage);
    }
    
    /**
     * Get default usage structure
     */
    private function get_default_usage() {
        return [
            'monthly_usage' => [],
            'daily_usage' => [],
            'last_reset' => current_time('Y-m-01')
        ];
    }
    
    /**
     * Get current month's API usage
     */
    public function get_api_usage_stats() {
        $option_names = $this->get_option_names();
        $usage = get_option($option_names['usage'], $this->get_default_usage());
        
        $current_month = current_time('Y-m');
        $current_day = current_time('Y-m-d');
        
        return [
            'monthly_used' => absint($usage['monthly_usage'][$current_month] ?? 0),
            'monthly_limit' => 100,
            'daily_used' => absint($usage['daily_usage'][$current_day] ?? 0),
            'daily_limit' => 3,
            'days_in_month' => absint(date('t')),
            'current_day' => absint(date('j'))
        ];
    }
    
    /**
     * Check if we can make API calls
     */
    public function can_make_api_calls($calls = 1) {
        $calls = absint($calls);
        $stats = $this->get_api_usage_stats();
        
        // Check monthly limit
        if ($stats['monthly_used'] + $calls > $stats['monthly_limit']) {
            return [
                'allowed' => false,
                'message' => sprintf(
                    __('Monthly API limit reached (%d/%d). Resets on the 1st.', 'sky-seo-boost'),
                    $stats['monthly_used'],
                    $stats['monthly_limit']
                )
            ];
        }
        
        // Check daily limit (soft limit with warning)
        if ($stats['daily_used'] + $calls > $stats['daily_limit']) {
            $projected_usage = ($stats['monthly_used'] / $stats['current_day']) * $stats['days_in_month'];
            
            if ($projected_usage > $stats['monthly_limit'] * 0.9) {
                return [
                    'allowed' => false,
                    'message' => sprintf(
                        __('Daily limit reached to preserve monthly quota. Used %d/%d this month.', 'sky-seo-boost'),
                        $stats['monthly_used'],
                        $stats['monthly_limit']
                    )
                ];
            }
        }
        
        return ['allowed' => true, 'message' => ''];
    }
    
    /**
     * AJAX: Get API usage stats
     */
    public function ajax_get_api_usage() {
        check_ajax_referer('sky_seo_api_nonce', '_ajax_nonce');
        
        if (!current_user_can('manage_options')) {
            wp_send_json_error(__('Permission denied', 'sky-seo-boost'));
        }
        
        $stats = $this->get_api_usage_stats();
        wp_send_json_success($stats);
    }
    
    /**
     * AJAX: Test API connection
     */
    public function ajax_test_api() {
        check_ajax_referer('sky_seo_api_nonce', '_ajax_nonce');
        
        if (!current_user_can('manage_options')) {
            wp_send_json_error(__('Permission denied', 'sky-seo-boost'));
        }
        
        $option_names = $this->get_option_names();
        $settings = get_option($option_names['main'], []);
        
        if (empty($settings['serpapi_key'])) {
            wp_send_json_error(__('Please enter your SerpApi key', 'sky-seo-boost'));
        }
        
        // Test API with account endpoint
        $url = 'https://serpapi.com/account.json';
        $response = wp_remote_get(add_query_arg(['api_key' => $settings['serpapi_key']], $url), [
            'timeout' => 15,
            'sslverify' => true
        ]);
        
        if (is_wp_error($response)) {
            error_log('Sky SEO API Test Error: ' . $response->get_error_message());
            wp_send_json_error($response->get_error_message());
        }
        
        $body = wp_remote_retrieve_body($response);
        $data = json_decode($body, true);
        
        if (json_last_error() !== JSON_ERROR_NONE) {
            error_log('Sky SEO API Test JSON Error: ' . json_last_error_msg());
            wp_send_json_error(__('Invalid response from API', 'sky-seo-boost'));
        }
        
        if (isset($data['error'])) {
            wp_send_json_error($data['error']);
        }
        
        $message = sprintf(
            __('✓ Connected! You have %d/%d searches remaining this month from SerpApi.', 'sky-seo-boost'),
            intval($data['searches_per_month'] ?? 0) - intval($data['searches_this_month'] ?? 0),
            intval($data['searches_per_month'] ?? 0)
        );
        
        wp_send_json_success($message);
    }
    
    /**
     * AJAX: Fetch reviews (forwards to Reviews Database)
     */
    public function ajax_fetch_reviews() {
        check_ajax_referer('sky_seo_api_nonce', '_ajax_nonce');
        
        if (!current_user_can('manage_options')) {
            wp_send_json_error(__('Permission denied', 'sky-seo-boost'));
        }
        
        if (!class_exists('Sky_SEO_Reviews_Database')) {
            wp_send_json_error(__('Reviews database not available', 'sky-seo-boost'));
        }
        
        $reviews_db = Sky_SEO_Reviews_Database::get_instance();
        $place_id = sanitize_text_field($_POST['place_id'] ?? '');
        $force = isset($_POST['force']) && $_POST['force'] === 'true';
        
        $result = $reviews_db->fetch_new_reviews($place_id, $force);
        
        if ($result['success']) {
            wp_send_json_success($result);
        } else {
            wp_send_json_error($result['message']);
        }
    }
    
    /**
     * AJAX: Fetch new reviews (alias for fetch_reviews)
     */
    public function ajax_fetch_new_reviews() {
        $this->ajax_fetch_reviews();
    }
    
    /**
     * AJAX: Get full review text
     */
    public function ajax_get_review_text() {
        check_ajax_referer('get_review_text', '_ajax_nonce');
        
        if (!current_user_can('manage_options')) {
            wp_send_json_error(__('Permission denied', 'sky-seo-boost'));
        }
        
        $review_id = intval($_POST['review_id'] ?? 0);
        
        if (!$review_id) {
            wp_send_json_error(__('Invalid review ID', 'sky-seo-boost'));
        }
        
        global $wpdb;
        $table_name = $wpdb->prefix . 'sky_seo_reviews';
        
        $review_text = $wpdb->get_var($wpdb->prepare(
            "SELECT text FROM $table_name WHERE id = %d",
            $review_id
        ));
        
        if ($review_text !== null) {
            wp_send_json_success($review_text);
        } else {
            wp_send_json_error(__('Review not found', 'sky-seo-boost'));
        }
    }
    
    /**
     * AJAX: Edit review text
     */
    public function ajax_edit_review() {
        check_ajax_referer('edit_review', '_ajax_nonce');
        
        if (!current_user_can('manage_options')) {
            wp_send_json_error(__('Permission denied', 'sky-seo-boost'));
        }
        
        $review_id = intval($_POST['review_id'] ?? 0);
        $review_text = sanitize_textarea_field($_POST['review_text'] ?? '');
        
        if (!$review_id) {
            wp_send_json_error(__('Invalid review ID', 'sky-seo-boost'));
        }
        
        global $wpdb;
        $table_name = $wpdb->prefix . 'sky_seo_reviews';
        
        $result = $wpdb->update(
            $table_name,
            ['text' => $review_text],
            ['id' => $review_id],
            ['%s'],
            ['%d']
        );
        
        if ($result !== false) {
            wp_send_json_success(__('Review updated successfully', 'sky-seo-boost'));
        } else {
            wp_send_json_error(__('Failed to update review', 'sky-seo-boost'));
        }
    }
    
    /**
     * AJAX: Update Google metadata (review count and rating)
     */
    public function ajax_update_google_metadata() {
        check_ajax_referer('sky_seo_api_nonce', '_ajax_nonce');
        
        if (!current_user_can('manage_options')) {
            wp_send_json_error(__('Permission denied', 'sky-seo-boost'));
        }
        
        $option_names = $this->get_option_names();
        $settings = get_option($option_names['main'], []);
        $place_id = $settings['place_id'] ?? '';
        $api_key = $settings['serpapi_key'] ?? '';
        
        if (empty($place_id) || empty($api_key)) {
            wp_send_json_error(__('Missing Place ID or API key', 'sky-seo-boost'));
        }
        
        // Check API limits
        $api_check = $this->can_make_api_calls(1);
        if (!$api_check['allowed']) {
            wp_send_json_error($api_check['message']);
        }
        
        // Force update by clearing existing metadata
        delete_option('sky_seo_google_business_meta_' . md5($place_id));
        
        // Clear caches
        wp_cache_delete('business_data_widget', 'sky_seo_widget');
        wp_cache_delete('business_data_' . md5($place_id), 'sky_seo_business');
        
        // Fetch fresh metadata
        if (class_exists('Sky_SEO_Business_Info_Elementor_Widget')) {
            $widget = new Sky_SEO_Business_Info_Elementor_Widget();
            $metadata = $widget->fetch_google_metadata();
            
            if ($metadata !== false && isset($metadata['total_reviews'])) {
                $message = sprintf(
                    __('✓ Metadata updated! Reviews: %d, Rating: %.1f', 'sky-seo-boost'),
                    $metadata['total_reviews'],
                    $metadata['average_rating']
                );
                
                wp_send_json_success(['message' => $message, 'data' => $metadata]);
            }
        }
        
        // If widget not available, do it directly
        $url = 'https://serpapi.com/search.json';
        $params = [
            'engine' => 'google_maps_reviews',
            'place_id' => $place_id,
            'api_key' => $api_key
        ];
        
        $response = wp_remote_get(add_query_arg($params, $url), [
            'timeout' => 30,
            'headers' => ['User-Agent' => 'Sky SEO Boost WordPress Plugin']
        ]);
        
        if (!is_wp_error($response)) {
            $body = wp_remote_retrieve_body($response);
            $data = json_decode($body, true);
            
            if (isset($data['place_info']) && !isset($data['error'])) {
                $metadata = [
                    'total_reviews' => intval($data['place_info']['reviews'] ?? 0),
                    'average_rating' => floatval($data['place_info']['rating'] ?? 0),
                    'last_updated' => current_time('mysql')
                ];
                
                update_option('sky_seo_google_business_meta_' . md5($place_id), $metadata);
                
                // Track API usage
                $this->track_api_usage(1);
                
                $message = sprintf(
                    __('✓ Metadata updated! Reviews: %d, Rating: %.1f', 'sky-seo-boost'),
                    $metadata['total_reviews'],
                    $metadata['average_rating']
                );
                
                wp_send_json_success(['message' => $message, 'data' => $metadata]);
            } else {
                wp_send_json_error(__('No place info found in response', 'sky-seo-boost'));
            }
        } else {
            wp_send_json_error($response->get_error_message());
        }
    }
    
    /**
     * Get review stats (forwards to Reviews Database)
     */
    public function get_review_stats($place_id = '') {
        if (!class_exists('Sky_SEO_Reviews_Database')) {
            return null;
        }
        
        $reviews_db = Sky_SEO_Reviews_Database::get_instance();
        return $reviews_db->get_review_stats($place_id);
    }
}