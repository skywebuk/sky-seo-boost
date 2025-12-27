<?php
/**
 * Sky SEO Business Info - Elementor Widget
 * Displays business status, hours, and ratings
 * Uses manual hours input and API for ratings
 * 
 * @package SkySEOBoost
 * @subpackage Elementor
 * @since 3.0.0
 */

// Prevent direct access
if (!defined('ABSPATH')) {
    exit;
}

// Make sure Elementor is loaded
if (!class_exists('\Elementor\Widget_Base')) {
    return;
}

/**
 * Sky SEO Business Info Widget Class
 * NO SELF-REGISTRATION - Registration happens through the loader
 */
class Sky_SEO_Business_Info_Elementor_Widget extends \Elementor\Widget_Base {
    
    /**
     * Cache group for widget data
     */
    private $cache_group = 'sky_seo_widget';
    
    /**
     * Widget name
     */
    public function get_name() {
        return 'sky_seo_business_info';
    }
    
    /**
     * Widget title
     */
    public function get_title() {
        return __('Business Hours & Info', 'sky360');
    }
    
    /**
     * Widget icon
     */
    public function get_icon() {
        return 'eicon-clock-o';
    }
    
    /**
     * Widget categories
     */
    public function get_categories() {
        return ['general', 'sky360'];
    }
    
    /**
     * Widget keywords
     */
    public function get_keywords() {
        return ['business', 'hours', 'opening', 'times', 'status', 'rating', 'reviews'];
    }
    
    /**
     * Widget scripts
     */
    public function get_script_depends() {
        return ['sky-elementor-script'];
    }
    
    /**
     * Widget styles
     */
    public function get_style_depends() {
        return ['sky-elementor-style'];
    }
    
    /**
     * Get business data from API or Database with caching
     */
    private function get_business_data() {
        // Check cache first
        $cache_key = 'business_data_widget';
        $cached_data = wp_cache_get($cache_key, $this->cache_group);
        
        if ($cached_data !== false) {
            return $cached_data;
        }
        
        try {
            // Get settings for place ID
            $settings = get_option('sky_seo_business_settings', []);
            $place_id = $settings['place_id'] ?? '';
            
            // First try the new database system
            if (class_exists('Sky_SEO_Reviews_Database')) {
                $reviews_db = Sky_SEO_Reviews_Database::get_instance();
                $data = $reviews_db->get_business_data();
                if ($data !== false) {
                    // Always check for Google's official metadata
                    $google_meta = get_option('sky_seo_google_business_meta_' . md5($place_id), []);
                    
                    // Override with Google's official counts if available
                    if (!empty($google_meta['total_reviews'])) {
                        $data['total_reviews'] = $google_meta['total_reviews'];
                    }
                    if (!empty($google_meta['average_rating'])) {
                        $data['rating'] = $google_meta['average_rating'];
                    }
                    
                    wp_cache_set($cache_key, $data, $this->cache_group, 300); // 5 minutes cache
                    return $data;
                }
            }
            
            // Fallback to Business API
            if (class_exists('Sky_SEO_Business_API')) {
                $business_api = Sky_SEO_Business_API::get_instance();
                $data = $business_api->get_business_data();
                if ($data !== false) {
                    // Check for Google's official metadata here too
                    $google_meta = get_option('sky_seo_google_business_meta_' . md5($place_id), []);
                    
                    // Override with Google's official counts if available
                    if (!empty($google_meta['total_reviews'])) {
                        $data['total_reviews'] = $google_meta['total_reviews'];
                    }
                    if (!empty($google_meta['average_rating'])) {
                        $data['rating'] = $google_meta['average_rating'];
                    }
                    
                    wp_cache_set($cache_key, $data, $this->cache_group, 300); // 5 minutes cache
                    return $data;
                }
            }
            
            return false;
        } catch (Exception $e) {
            error_log('Sky SEO Business Widget Error: ' . $e->getMessage());
            return false;
        }
    }
    
    /**
     * Fetch Google business metadata (total reviews, rating) - FIXED VERSION
     */
    public function fetch_google_metadata() {
        $settings = get_option('sky_seo_business_settings', []);
        $place_id = $settings['place_id'] ?? '';
        $api_key = $settings['serpapi_key'] ?? '';
        $business_name = $settings['business_name'] ?? '';
        
        if (empty($place_id) || empty($api_key)) {
            return false;
        }
        
        // Check if we have recent metadata (less than 24 hours old)
        $google_meta = get_option('sky_seo_google_business_meta_' . md5($place_id), []);
        if (!empty($google_meta['last_updated'])) {
            $last_updated = strtotime($google_meta['last_updated']);
            if (time() - $last_updated < 86400) { // 24 hours
                return $google_meta;
            }
        }
        
        // Fetch fresh data from Google
        $url = 'https://serpapi.com/search.json';
        
        // FIXED: Try place_id parameter first (works for most cases)
        $params = [
            'engine' => 'google_maps_reviews',
            'place_id' => $place_id,
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
            
            // Log the response for debugging
            if (defined('WP_DEBUG') && WP_DEBUG) {
                error_log('Sky SEO Google Metadata Response (place_id): ' . substr($body, 0, 500));
            }
            
            // Extract from place_info if available
            if (isset($data['place_info']) && !isset($data['error'])) {
                $total_reviews = 0;
                $average_rating = 0;
                
                // Look for reviews count in place_info
                if (isset($data['place_info']['reviews'])) {
                    $total_reviews = intval($data['place_info']['reviews']);
                }
                
                // Look for rating in place_info
                if (isset($data['place_info']['rating'])) {
                    $average_rating = floatval($data['place_info']['rating']);
                }
                
                // If we found the data, save and return it
                if ($total_reviews > 0 || $average_rating > 0) {
                    $meta_data = [
                        'total_reviews' => $total_reviews,
                        'average_rating' => $average_rating,
                        'last_updated' => current_time('mysql')
                    ];
                    
                    update_option('sky_seo_google_business_meta_' . md5($place_id), $meta_data);
                    
                    // Clear caches
                    wp_cache_delete('business_data_widget', $this->cache_group);
                    wp_cache_delete('business_data_' . md5($place_id), 'sky_seo_business');
                    
                    // Track API usage
                    if (class_exists('Sky_SEO_Business_API')) {
                        $business_api = Sky_SEO_Business_API::get_instance();
                        $business_api->track_api_usage(1);
                    }
                    
                    error_log(sprintf(
                        'Sky SEO: Successfully updated Google metadata - Reviews: %d, Rating: %.1f',
                        $total_reviews,
                        $average_rating
                    ));
                    
                    return $meta_data;
                }
            }
            
            // If we got an error, try with data_id parameter
            if (isset($data['error']) || !isset($data['place_info'])) {
                // Only try data_id if place_id is in 0x format
                if (strpos($place_id, '0x') === 0) {
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
                        
                        if (defined('WP_DEBUG') && WP_DEBUG) {
                            error_log('Sky SEO Google Metadata Response (data_id): ' . substr($body, 0, 500));
                        }
                        
                        if (isset($data['place_info']) && !isset($data['error'])) {
                            $total_reviews = intval($data['place_info']['reviews'] ?? 0);
                            $average_rating = floatval($data['place_info']['rating'] ?? 0);
                            
                            if ($total_reviews > 0 || $average_rating > 0) {
                                $meta_data = [
                                    'total_reviews' => $total_reviews,
                                    'average_rating' => $average_rating,
                                    'last_updated' => current_time('mysql')
                                ];
                                
                                update_option('sky_seo_google_business_meta_' . md5($place_id), $meta_data);
                                
                                // Clear caches
                                wp_cache_delete('business_data_widget', $this->cache_group);
                                wp_cache_delete('business_data_' . md5($place_id), 'sky_seo_business');
                                
                                // Track API usage
                                if (class_exists('Sky_SEO_Business_API')) {
                                    $business_api = Sky_SEO_Business_API::get_instance();
                                    $business_api->track_api_usage(1);
                                }
                                
                                return $meta_data;
                            }
                        }
                    }
                }
            }
        }
        
        // If direct lookup didn't work, try searching by business name
        if (!empty($business_name)) {
            $params = [
                'engine' => 'google_maps',
                'q' => $business_name,
                'type' => 'search',
                'api_key' => $api_key
            ];
            
            $response = wp_remote_get(add_query_arg($params, $url), [
                'timeout' => 30,
                'headers' => [
                    'User-Agent' => 'Sky SEO Boost WordPress Plugin'
                ]
            ]);
            
            if (is_wp_error($response)) {
                error_log('Sky SEO Google Metadata Error: ' . $response->get_error_message());
                return false;
            }
            
            $body = wp_remote_retrieve_body($response);
            $data = json_decode($body, true);
            
            // Extract Google's official counts
            $total_reviews = 0;
            $average_rating = 0;
            
            // For search results, we need to find our business
            if (isset($data['local_results']) && is_array($data['local_results'])) {
                foreach ($data['local_results'] as $result) {
                    // Match by place_id, data_id, or business name
                    $is_match = false;
                    
                    if (isset($result['place_id']) && $result['place_id'] === $place_id) {
                        $is_match = true;
                    } elseif (isset($result['data_id']) && $result['data_id'] === $place_id) {
                        $is_match = true;
                    } elseif (isset($result['title']) && strcasecmp(trim($result['title']), trim($business_name)) === 0) {
                        $is_match = true;
                    }
                    
                    if ($is_match) {
                        // Found our business!
                        if (isset($result['reviews'])) {
                            $total_reviews = intval($result['reviews']);
                        }
                        if (isset($result['rating'])) {
                            $average_rating = floatval($result['rating']);
                        }
                        
                        // Also check for reviews_link which might have the count
                        if (isset($result['reviews_link']) && preg_match('/\((\d+)\)/', $result['reviews_link'], $matches)) {
                            $extracted_count = intval($matches[1]);
                            if ($extracted_count > $total_reviews) {
                                $total_reviews = $extracted_count;
                            }
                        }
                        
                        break;
                    }
                }
            }
            
            // Store the metadata if we found it
            if ($total_reviews > 0 || $average_rating > 0) {
                $meta_data = [
                    'total_reviews' => $total_reviews,
                    'average_rating' => $average_rating,
                    'last_updated' => current_time('mysql')
                ];
                
                update_option('sky_seo_google_business_meta_' . md5($place_id), $meta_data);
                
                // Clear caches
                wp_cache_delete('business_data_widget', $this->cache_group);
                wp_cache_delete('business_data_' . md5($place_id), 'sky_seo_business');
                
                // Track API usage
                if (class_exists('Sky_SEO_Business_API')) {
                    $business_api = Sky_SEO_Business_API::get_instance();
                    $business_api->track_api_usage(1);
                }
                
                return $meta_data;
            }
        }
        
        return false;
    }
    
    /**
     * Get current business status based on manual hours with caching
     */
    private function get_current_status() {
        // Cache key changes every minute
        $cache_key = 'business_status_' . date('Y-m-d-H-i');
        $cached_status = wp_cache_get($cache_key, $this->cache_group);
        
        if ($cached_status !== false) {
            return $cached_status;
        }
        
        try {
            if (class_exists('Sky_SEO_Business_API')) {
                $business_api = Sky_SEO_Business_API::get_instance();
                $is_open = $business_api->is_business_open();
                
                // Get timezone from settings
                $hours_settings = get_option('sky_seo_business_hours', []);
                $timezone_string = isset($hours_settings['timezone']) ? $hours_settings['timezone'] : 'UTC';
                
                try {
                    $timezone = new DateTimeZone($timezone_string);
                    $now = new DateTime('now', $timezone);
                } catch (Exception $e) {
                    $now = new DateTime('now');
                }
                
                $status = [
                    'is_open' => $is_open,
                    'class' => $is_open ? 'open' : 'closed',
                    'current_day' => $now->format('l'),
                    'current_time' => $now->format('H:i')
                ];
                
                // Cache for 1 minute
                wp_cache_set($cache_key, $status, $this->cache_group, 60);
                
                return $status;
            }
            
            $default_status = [
                'is_open' => false,
                'class' => 'closed',
                'current_day' => date('l'),
                'current_time' => date('H:i')
            ];
            
            wp_cache_set($cache_key, $default_status, $this->cache_group, 60);
            return $default_status;
            
        } catch (Exception $e) {
            error_log('Sky SEO Status Error: ' . $e->getMessage());
            return [
                'is_open' => false,
                'class' => 'closed',
                'current_day' => date('l'),
                'current_time' => date('H:i')
            ];
        }
    }
    
    /**
     * Register widget controls
     */
    protected function register_controls() {
        $this->register_content_controls();
        $this->register_style_controls();
    }
    
    /**
     * Register content controls
     */
    private function register_content_controls() {
        // Content Section
        $this->start_controls_section(
            'content_section',
            [
                'label' => __('Display Options', 'sky360'),
                'tab' => \Elementor\Controls_Manager::TAB_CONTENT,
            ]
        );
        
        $this->add_control(
            'display_type',
            [
                'label' => __('Display Type', 'sky360'),
                'type' => \Elementor\Controls_Manager::SELECT,
                'default' => 'status',
                'options' => [
                    'status' => __('Open/Closed Status', 'sky360'),
                    'hours_today' => __('Today\'s Hours', 'sky360'),
                    'hours_week' => __('Weekly Schedule', 'sky360'),
                    'rating' => __('Star Rating', 'sky360'),
                ],
            ]
        );
        
        // Status Options
        $this->add_control(
            'status_heading',
            [
                'label' => __('Status Options', 'sky360'),
                'type' => \Elementor\Controls_Manager::HEADING,
                'separator' => 'before',
                'condition' => [
                    'display_type' => 'status',
                ],
            ]
        );
        
        $this->add_control(
            'open_text',
            [
                'label' => __('Open Text', 'sky360'),
                'type' => \Elementor\Controls_Manager::TEXT,
                'default' => __('Shop open now', 'sky360'),
                'condition' => [
                    'display_type' => 'status',
                ],
            ]
        );
        
        $this->add_control(
            'closed_text',
            [
                'label' => __('Closed Text', 'sky360'),
                'type' => \Elementor\Controls_Manager::TEXT,
                'default' => __('Closed', 'sky360'),
                'condition' => [
                    'display_type' => 'status',
                ],
            ]
        );
        
        $this->add_control(
            'show_hours_with_status',
            [
                'label' => __('Show Hours Details Below', 'sky360'),
                'type' => \Elementor\Controls_Manager::SWITCHER,
                'default' => '',
                'condition' => [
                    'display_type' => 'status',
                ],
            ]
        );
        
        $this->add_control(
            'show_rating_with_status',
            [
                'label' => __('Show Rating with Status', 'sky360'),
                'type' => \Elementor\Controls_Manager::SWITCHER,
                'default' => 'yes',
                'condition' => [
                    'display_type' => 'status',
                ],
            ]
        );
        
        // Today's Hours Options
        $this->add_control(
            'today_label',
            [
                'label' => __('Label', 'sky360'),
                'type' => \Elementor\Controls_Manager::TEXT,
                'default' => __("Today's Hours", 'sky360'),
                'condition' => [
                    'display_type' => 'hours_today',
                ],
            ]
        );
        
        // Weekly Hours Options
        $this->add_control(
            'week_title',
            [
                'label' => __('Title', 'sky360'),
                'type' => \Elementor\Controls_Manager::TEXT,
                'default' => __('Business Hours', 'sky360'),
                'condition' => [
                    'display_type' => 'hours_week',
                ],
            ]
        );
        
        $this->add_control(
            'show_week_title',
            [
                'label' => __('Show Title', 'sky360'),
                'type' => \Elementor\Controls_Manager::SWITCHER,
                'default' => 'yes',
                'condition' => [
                    'display_type' => 'hours_week',
                ],
            ]
        );
        
        $this->add_control(
            'highlight_today',
            [
                'label' => __('Highlight Today', 'sky360'),
                'type' => \Elementor\Controls_Manager::SWITCHER,
                'default' => 'yes',
                'condition' => [
                    'display_type' => 'hours_week',
                ],
            ]
        );
        
        // Rating Options
        $this->add_control(
            'rating_heading',
            [
                'label' => __('Rating Options', 'sky360'),
                'type' => \Elementor\Controls_Manager::HEADING,
                'separator' => 'before',
                'condition' => [
                    'display_type' => 'rating',
                ],
            ]
        );
        
        $this->add_control(
            'show_rating_value',
            [
                'label' => __('Show Numeric Rating', 'sky360'),
                'type' => \Elementor\Controls_Manager::SWITCHER,
                'default' => 'yes',
                'condition' => [
                    'display_type' => 'rating',
                ],
            ]
        );
        
        $this->add_control(
            'show_review_count',
            [
                'label' => __('Show Review Count', 'sky360'),
                'type' => \Elementor\Controls_Manager::SWITCHER,
                'default' => 'yes',
                'conditions' => [
                    'relation' => 'or',
                    'terms' => [
                        [
                            'name' => 'display_type',
                            'operator' => '==',
                            'value' => 'rating',
                        ],
                        [
                            'relation' => 'and',
                            'terms' => [
                                [
                                    'name' => 'display_type',
                                    'operator' => '==',
                                    'value' => 'status',
                                ],
                                [
                                    'name' => 'show_rating_with_status',
                                    'operator' => '==',
                                    'value' => 'yes',
                                ],
                            ],
                        ],
                    ],
                ],
            ]
        );
        
        // Schema Options
        $this->add_control(
            'enable_schema',
            [
                'label' => __('Enable Schema Markup', 'sky360'),
                'type' => \Elementor\Controls_Manager::SWITCHER,
                'default' => 'yes',
                'separator' => 'before',
                'description' => __('Add structured data for better SEO', 'sky360'),
            ]
        );
        
        $this->end_controls_section();
    }
    
    /**
     * Register style controls
     */
    private function register_style_controls() {
        // Style Section - General
        $this->start_controls_section(
            'style_general',
            [
                'label' => __('General Style', 'sky360'),
                'tab' => \Elementor\Controls_Manager::TAB_STYLE,
            ]
        );
        
        $this->add_responsive_control(
            'text_align',
            [
                'label' => __('Alignment', 'sky360'),
                'type' => \Elementor\Controls_Manager::CHOOSE,
                'options' => [
                    'left' => [
                        'title' => __('Left', 'sky360'),
                        'icon' => 'eicon-text-align-left',
                    ],
                    'center' => [
                        'title' => __('Center', 'sky360'),
                        'icon' => 'eicon-text-align-center',
                    ],
                    'right' => [
                        'title' => __('Right', 'sky360'),
                        'icon' => 'eicon-text-align-right',
                    ],
                ],
                'default' => 'left',
                'selectors' => [
                    '{{WRAPPER}} .sky-seo-business-widget' => 'text-align: {{VALUE}};',
                ],
            ]
        );
        
        $this->add_responsive_control(
            'container_padding',
            [
                'label' => __('Padding', 'sky360'),
                'type' => \Elementor\Controls_Manager::DIMENSIONS,
                'size_units' => ['px', 'em', '%'],
                'selectors' => [
                    '{{WRAPPER}} .sky-seo-business-widget' => 'padding: {{TOP}}{{UNIT}} {{RIGHT}}{{UNIT}} {{BOTTOM}}{{UNIT}} {{LEFT}}{{UNIT}};',
                ],
            ]
        );
        
        $this->add_group_control(
            \Elementor\Group_Control_Background::get_type(),
            [
                'name' => 'container_background',
                'label' => __('Background', 'sky360'),
                'types' => ['classic', 'gradient'],
                'selector' => '{{WRAPPER}} .sky-seo-business-widget',
            ]
        );
        
        $this->add_group_control(
            \Elementor\Group_Control_Border::get_type(),
            [
                'name' => 'container_border',
                'selector' => '{{WRAPPER}} .sky-seo-business-widget',
            ]
        );
        
        $this->add_responsive_control(
            'container_border_radius',
            [
                'label' => __('Border Radius', 'sky360'),
                'type' => \Elementor\Controls_Manager::DIMENSIONS,
                'size_units' => ['px', '%'],
                'selectors' => [
                    '{{WRAPPER}} .sky-seo-business-widget' => 'border-radius: {{TOP}}{{UNIT}} {{RIGHT}}{{UNIT}} {{BOTTOM}}{{UNIT}} {{LEFT}}{{UNIT}};',
                ],
            ]
        );
        
        $this->add_group_control(
            \Elementor\Group_Control_Box_Shadow::get_type(),
            [
                'name' => 'container_shadow',
                'selector' => '{{WRAPPER}} .sky-seo-business-widget',
            ]
        );
        
        $this->end_controls_section();
        
        // Additional style sections...
        $this->register_status_style_controls();
        $this->register_hours_style_controls();
        $this->register_rating_style_controls();
    }
    
    /**
     * Register status style controls
     */
    private function register_status_style_controls() {
        $this->start_controls_section(
            'style_status',
            [
                'label' => __('Status Badge Style', 'sky360'),
                'tab' => \Elementor\Controls_Manager::TAB_STYLE,
                'condition' => [
                    'display_type' => 'status',
                ],
            ]
        );
        
        $this->add_control(
            'badge_layout',
            [
                'label' => __('Badge Layout', 'sky360'),
                'type' => \Elementor\Controls_Manager::SELECT,
                'default' => 'inline',
                'options' => [
                    'inline' => __('Inline', 'sky360'),
                    'stacked' => __('Stacked', 'sky360'),
                ],
            ]
        );
        
        $this->add_group_control(
            \Elementor\Group_Control_Typography::get_type(),
            [
                'name' => 'badge_typography',
                'selector' => '{{WRAPPER}} .sky-seo-status-badge',
            ]
        );
        
        $this->add_responsive_control(
            'badge_padding',
            [
                'label' => __('Padding', 'sky360'),
                'type' => \Elementor\Controls_Manager::DIMENSIONS,
                'size_units' => ['px', 'em'],
                'default' => [
                    'top' => '8',
                    'right' => '16',
                    'bottom' => '8',
                    'left' => '16',
                    'unit' => 'px',
                ],
                'selectors' => [
                    '{{WRAPPER}} .sky-seo-status-badge' => 'padding: {{TOP}}{{UNIT}} {{RIGHT}}{{UNIT}} {{BOTTOM}}{{UNIT}} {{LEFT}}{{UNIT}};',
                ],
            ]
        );
        
        // Open State
        $this->add_control(
            'open_state_heading',
            [
                'label' => __('Open State', 'sky360'),
                'type' => \Elementor\Controls_Manager::HEADING,
                'separator' => 'before',
            ]
        );
        
        $this->add_control(
            'open_text_color',
            [
                'label' => __('Text Color', 'sky360'),
                'type' => \Elementor\Controls_Manager::COLOR,
                'default' => '#188038',
                'selectors' => [
                    '{{WRAPPER}} .sky-seo-status-open .sky-seo-status-badge' => 'color: {{VALUE}};',
                ],
            ]
        );
        
        $this->add_control(
            'open_background_color',
            [
                'label' => __('Background Color', 'sky360'),
                'type' => \Elementor\Controls_Manager::COLOR,
                'default' => 'transparent',
                'selectors' => [
                    '{{WRAPPER}} .sky-seo-status-open .sky-seo-status-badge' => 'background-color: {{VALUE}};',
                ],
            ]
        );
        
        // Closed State
        $this->add_control(
            'closed_state_heading',
            [
                'label' => __('Closed State', 'sky360'),
                'type' => \Elementor\Controls_Manager::HEADING,
                'separator' => 'before',
            ]
        );
        
        $this->add_control(
            'closed_text_color',
            [
                'label' => __('Text Color', 'sky360'),
                'type' => \Elementor\Controls_Manager::COLOR,
                'default' => '#d93025',
                'selectors' => [
                    '{{WRAPPER}} .sky-seo-status-closed .sky-seo-status-badge' => 'color: {{VALUE}};',
                ],
            ]
        );
        
        $this->add_control(
            'closed_background_color',
            [
                'label' => __('Background Color', 'sky360'),
                'type' => \Elementor\Controls_Manager::COLOR,
                'default' => 'transparent',
                'selectors' => [
                    '{{WRAPPER}} .sky-seo-status-closed .sky-seo-status-badge' => 'background-color: {{VALUE}};',
                ],
            ]
        );
        
        $this->end_controls_section();
    }
    
    /**
     * Register hours style controls
     */
    private function register_hours_style_controls() {
        $this->start_controls_section(
            'style_hours',
            [
                'label' => __('Hours Style', 'sky360'),
                'tab' => \Elementor\Controls_Manager::TAB_STYLE,
                'conditions' => [
                    'relation' => 'or',
                    'terms' => [
                        [
                            'name' => 'display_type',
                            'operator' => '==',
                            'value' => 'hours_today',
                        ],
                        [
                            'name' => 'display_type',
                            'operator' => '==',
                            'value' => 'hours_week',
                        ],
                    ],
                ],
            ]
        );
        
        // Label Typography
        $this->add_group_control(
            \Elementor\Group_Control_Typography::get_type(),
            [
                'name' => 'hours_label_typography',
                'label' => __('Label Typography', 'sky360'),
                'selector' => '{{WRAPPER}} .sky-seo-hours-label, {{WRAPPER}} .sky-seo-hours-title',
            ]
        );
        
        $this->add_control(
            'hours_label_color',
            [
                'label' => __('Label Color', 'sky360'),
                'type' => \Elementor\Controls_Manager::COLOR,
                'default' => '#333333',
                'selectors' => [
                    '{{WRAPPER}} .sky-seo-hours-label' => 'color: {{VALUE}};',
                    '{{WRAPPER}} .sky-seo-hours-title' => 'color: {{VALUE}};',
                ],
            ]
        );
        
        $this->end_controls_section();
    }
    
    /**
     * Register rating style controls
     */
    private function register_rating_style_controls() {
        $this->start_controls_section(
            'style_rating',
            [
                'label' => __('Rating Style', 'sky360'),
                'tab' => \Elementor\Controls_Manager::TAB_STYLE,
                'conditions' => [
                    'relation' => 'or',
                    'terms' => [
                        [
                            'name' => 'display_type',
                            'operator' => '==',
                            'value' => 'rating',
                        ],
                        [
                            'relation' => 'and',
                            'terms' => [
                                [
                                    'name' => 'display_type',
                                    'operator' => '==',
                                    'value' => 'status',
                                ],
                                [
                                    'name' => 'show_rating_with_status',
                                    'operator' => '==',
                                    'value' => 'yes',
                                ],
                            ],
                        ],
                    ],
                ],
            ]
        );
        
        $this->add_control(
            'star_size',
            [
                'label' => __('Star Size', 'sky360'),
                'type' => \Elementor\Controls_Manager::SLIDER,
                'size_units' => ['px', 'em', 'rem'],
                'range' => [
                    'px' => [
                        'min' => 10,
                        'max' => 100,
                    ],
                ],
                'default' => [
                    'unit' => 'px',
                    'size' => 18,
                ],
                'selectors' => [
                    '{{WRAPPER}} .sky-seo-rating-stars' => 'font-size: {{SIZE}}{{UNIT}};',
                ],
            ]
        );
        
        $this->add_control(
            'star_color',
            [
                'label' => __('Star Color', 'sky360'),
                'type' => \Elementor\Controls_Manager::COLOR,
                'default' => '#FFA500',
                'selectors' => [
                    '{{WRAPPER}} .sky-seo-rating-stars' => 'color: {{VALUE}};',
                ],
            ]
        );
        
        $this->end_controls_section();
    }
    
    /**
     * Render widget output
     */
    protected function render() {
        // Check if we're in a valid Elementor context
        if (!did_action('elementor/loaded')) {
            return;
        }
        
        $settings = $this->get_settings_for_display();
        $widget_id = $this->get_id();
        
        // Check if Business API is available
        if (!class_exists('Sky_SEO_Business_API') && !class_exists('Sky_SEO_Reviews_Database')) {
            if (\Elementor\Plugin::$instance->editor->is_edit_mode()) {
                echo '<div class="elementor-alert elementor-alert-warning">';
                echo esc_html__('Business API is not available. Please ensure the Sky SEO Boost plugin is properly configured.', 'sky360');
                echo '</div>';
            }
            return;
        }
        
        // Always try to fetch Google metadata first
        $this->fetch_google_metadata();
        
        // Get business data (which will now include Google's counts)
        wp_cache_delete('business_data_widget', $this->cache_group);
        $data = $this->get_business_data();
        
        // Add unique ID to prevent conflicts
        echo '<div id="sky-business-widget-' . esc_attr($widget_id) . '" class="sky-seo-widget sky-seo-business-widget sky-seo-business-' . esc_attr($settings['display_type']) . '">';
        
        // Add schema markup if enabled
        if ($settings['enable_schema'] === 'yes' && $data) {
            $this->render_schema_markup($data);
        }
        
        switch ($settings['display_type']) {
            case 'status':
                $this->render_status($settings);
                break;
            case 'hours_today':
                $this->render_hours_today($settings);
                break;
            case 'hours_week':
                $this->render_hours_week($settings);
                break;
            case 'rating':
                $this->render_rating($settings);
                break;
        }
        
        echo '</div>';
    }
    
    /**
     * Render schema markup
     */
    private function render_schema_markup($data) {
        $schema = [
            '@context' => 'https://schema.org',
            '@type' => 'LocalBusiness',
            'name' => esc_html($data['name'] ?? ''),
            'openingHours' => [],
        ];
        
        if (isset($data['rating']) && $data['rating'] > 0) {
            $schema['aggregateRating'] = [
                '@type' => 'AggregateRating',
                'ratingValue' => floatval($data['rating']),
                'reviewCount' => intval($data['total_reviews'] ?? 0)
            ];
        }
        
        if (isset($data['opening_hours']) && is_array($data['opening_hours'])) {
            foreach ($data['opening_hours'] as $day => $hours) {
                if ($hours !== 'Closed' && $hours !== __('Closed', 'sky360')) {
                    $day_abbr = substr($day, 0, 2);
                    if ($hours === '24 hours' || $hours === __('24 hours', 'sky360')) {
                        $schema['openingHours'][] = $day_abbr . ' 00:00-23:59';
                    } else {
                        // Convert hours to schema format
                        $converted_hours = $this->convert_to_24h_format($hours);
                        if ($converted_hours) {
                            $schema['openingHours'][] = $day_abbr . ' ' . $converted_hours;
                        }
                    }
                }
            }
        }
        
        echo '<script type="application/ld+json">' . wp_json_encode($schema) . '</script>';
    }
    
    /**
     * Convert time format for schema - FIXED VERSION
     */
    private function convert_to_24h_format($hours) {
        // Handle multiple time slots (e.g., "9:00 AM - 12:00 PM, 1:00 PM - 5:00 PM")
        if (strpos($hours, ',') !== false) {
            $slots = explode(',', $hours);
            $converted_slots = [];
            
            foreach ($slots as $slot) {
                $converted = $this->convert_single_slot_to_24h(trim($slot));
                if ($converted) {
                    $converted_slots[] = $converted;
                }
            }
            
            // Schema only supports one time range per day, so use the first slot
            return !empty($converted_slots) ? $converted_slots[0] : '';
        }
        
        // Single time slot
        return $this->convert_single_slot_to_24h($hours);
    }
    
    /**
     * Convert a single time slot to 24h format
     */
    private function convert_single_slot_to_24h($slot) {
        // Match patterns like "9:00 AM - 5:00 PM"
        if (preg_match('/(\d{1,2}):(\d{2})\s*(AM|PM)\s*[-–]\s*(\d{1,2}):(\d{2})\s*(AM|PM)/i', $slot, $matches)) {
            $open_hour = intval($matches[1]);
            $open_min = $matches[2];
            $open_period = strtoupper($matches[3]);
            
            $close_hour = intval($matches[4]);
            $close_min = $matches[5];
            $close_period = strtoupper($matches[6]);
            
            // Convert to 24h format
            if ($open_period === 'PM' && $open_hour !== 12) {
                $open_hour += 12;
            } elseif ($open_period === 'AM' && $open_hour === 12) {
                $open_hour = 0;
            }
            
            if ($close_period === 'PM' && $close_hour !== 12) {
                $close_hour += 12;
            } elseif ($close_period === 'AM' && $close_hour === 12) {
                $close_hour = 0;
            }
            
            return sprintf('%02d:%s-%02d:%s', $open_hour, $open_min, $close_hour, $close_min);
        }
        
        return '';
    }
    
    /**
     * Render status display
     */
    private function render_status($settings) {
        $status = $this->get_current_status();
        $data = $this->get_business_data();
        $layout_class = 'sky-seo-layout-' . $settings['badge_layout'];
        
        ?>
        <div class="sky-seo-business-status sky-seo-status-<?php echo esc_attr($status['class']); ?> <?php echo esc_attr($layout_class); ?>">
            <span class="sky-seo-status-badge">
                <?php echo esc_html($status['is_open'] ? $settings['open_text'] : $settings['closed_text']); ?>
            </span>
            
            <?php if ($settings['show_rating_with_status'] === 'yes' && $data && isset($data['rating']) && $data['rating'] > 0) : ?>
                <span class="sky-seo-rating-value"><?php echo number_format($data['rating'], 1); ?></span>
                <span class="sky-seo-rating-stars" role="img" aria-label="<?php echo esc_attr(sprintf(__('%s out of 5 stars', 'sky360'), number_format($data['rating'], 1))); ?>">
                    <?php echo str_repeat('★', round($data['rating'])); ?>
                </span>
                <?php if ($settings['show_review_count'] === 'yes' && isset($data['total_reviews']) && $data['total_reviews'] > 0) : ?>
                    <span class="sky-seo-rating-count">(<?php echo number_format($data['total_reviews']); ?>)</span>
                <?php endif; ?>
            <?php endif; ?>
            
            <?php if ($settings['show_hours_with_status'] === 'yes' && $data && isset($data['opening_hours'])) : ?>
                <span class="sky-seo-status-text">
                    <?php
                    $current_day = $status['current_day'];
                    $today_hours = isset($data['opening_hours'][$current_day]) ? $data['opening_hours'][$current_day] : __('Hours not available', 'sky360');
                    echo esc_html($current_day . ': ' . $today_hours);
                    ?>
                </span>
            <?php endif; ?>
        </div>
        <?php
    }
    
    /**
     * Render today's hours
     */
    private function render_hours_today($settings) {
        $data = $this->get_business_data();
        $status = $this->get_current_status();
        $today_hours = __('Closed', 'sky360');
        
        if ($data && isset($data['opening_hours'])) {
            $current_day = $status['current_day'];
            $today_hours = isset($data['opening_hours'][$current_day]) ? $data['opening_hours'][$current_day] : __('Closed', 'sky360');
        }
        
        ?>
        <div class="sky-seo-hours-today">
            <span class="sky-seo-hours-label"><?php echo esc_html($settings['today_label']); ?>:</span>
            <span class="sky-seo-hours-time"><?php echo esc_html($today_hours); ?></span>
        </div>
        <?php
    }
    
    /**
     * Render weekly hours
     */
    private function render_hours_week($settings) {
        $days = [
            'Monday' => __('Monday', 'sky360'),
            'Tuesday' => __('Tuesday', 'sky360'),
            'Wednesday' => __('Wednesday', 'sky360'),
            'Thursday' => __('Thursday', 'sky360'),
            'Friday' => __('Friday', 'sky360'),
            'Saturday' => __('Saturday', 'sky360'),
            'Sunday' => __('Sunday', 'sky360')
        ];
        
        $data = $this->get_business_data();
        $weekly_hours = [];
        
        if ($data && isset($data['opening_hours'])) {
            $weekly_hours = $data['opening_hours'];
        }
        
        $status = $this->get_current_status();
        $current_day = $status['current_day'];
        
        ?>
        <div class="sky-seo-hours-week">
            <?php if ($settings['show_week_title'] === 'yes') : ?>
                <h3 class="sky-seo-hours-title"><?php echo esc_html($settings['week_title']); ?></h3>
            <?php endif; ?>
            
            <table class="sky-seo-hours-table" role="table">
                <caption class="screen-reader-text"><?php esc_html_e('Business opening hours by day', 'sky360'); ?></caption>
                <tbody>
                <?php foreach ($days as $day_key => $day_label) : ?>
                    <tr class="<?php echo ($settings['highlight_today'] === 'yes' && $day_key == $current_day) ? 'sky-seo-today' : ''; ?>">
                        <td class="sky-seo-day"><?php echo esc_html($day_label); ?></td>
                        <td class="sky-seo-hours">
                            <?php 
                            $hours = isset($weekly_hours[$day_key]) ? $weekly_hours[$day_key] : __('Closed', 'sky360');
                            echo esc_html($hours); 
                            ?>
                        </td>
                    </tr>
                <?php endforeach; ?>
                </tbody>
            </table>
        </div>
        <?php
    }
    
    /**
     * Render rating
     */
    private function render_rating($settings) {
        $data = $this->get_business_data();
        
        if (!$data || !isset($data['rating']) || $data['rating'] == 0) {
            if (\Elementor\Plugin::$instance->editor->is_edit_mode()) {
                echo '<div class="elementor-alert elementor-alert-info">';
                echo esc_html__('No rating data available. Please configure the Business API.', 'sky360');
                echo '</div>';
            }
            return;
        }
        
        $layout_class = isset($settings['rating_layout']) ? 'sky-seo-layout-' . $settings['rating_layout'] : '';
        
        ?>
        <div class="sky-seo-rating <?php echo esc_attr($layout_class); ?>">
            <?php if ($settings['show_rating_value'] === 'yes') : ?>
                <span class="sky-seo-rating-value"><?php echo number_format($data['rating'], 1); ?></span>
            <?php endif; ?>
            
            <span class="sky-seo-rating-stars" role="img" aria-label="<?php echo esc_attr(sprintf(__('%s out of 5 stars', 'sky360'), number_format($data['rating'], 1))); ?>">
                <?php echo str_repeat('★', round($data['rating'])); ?>
            </span>
            
            <?php if ($settings['show_review_count'] === 'yes' && isset($data['total_reviews']) && $data['total_reviews'] > 0) : ?>
                <span class="sky-seo-rating-count">
                    (<?php echo number_format($data['total_reviews']); ?> <?php echo esc_html(_n('review', 'reviews', $data['total_reviews'], 'sky360')); ?>)
                </span>
            <?php endif; ?>
        </div>
        <?php
    }
    
    /**
     * Render widget in the editor
     */
    protected function content_template() {
        ?>
        <#
        var displayType = settings.display_type;
        
        // Preview data for editor
        var previewData = {
            rating: 4.5,
            totalReviews: 123,
            hours: {
                Monday: '9:00 AM - 5:00 PM',
                Tuesday: '9:00 AM - 5:00 PM',
                Wednesday: '9:00 AM - 5:00 PM',
                Thursday: '9:00 AM - 5:00 PM',
                Friday: '9:00 AM - 5:00 PM',
                Saturday: '10:00 AM - 3:00 PM',
                Sunday: 'Closed'
            }
        };
        
        var currentDay = 'Monday';
        var isOpen = true;
        var widgetId = view.model.id;
        #>
        <div id="sky-business-widget-{{ widgetId }}" class="sky-seo-widget sky-seo-business-widget sky-seo-business-{{ displayType }}">
            <# if ( displayType === 'status' ) { #>
                <div class="sky-seo-business-status sky-seo-status-{{ isOpen ? 'open' : 'closed' }} sky-seo-layout-{{ settings.badge_layout }}">
                    <span class="sky-seo-status-badge">
                        {{ isOpen ? settings.open_text : settings.closed_text }}
                    </span>
                    <# if ( settings.show_rating_with_status === 'yes' ) { #>
                        <span class="sky-seo-rating-value">{{ previewData.rating }}</span>
                        <span class="sky-seo-rating-stars">★★★★★</span>
                        <# if ( settings.show_review_count === 'yes' ) { #>
                            <span class="sky-seo-rating-count">({{ previewData.totalReviews }})</span>
                        <# } #>
                    <# } #>
                    <# if ( settings.show_hours_with_status === 'yes' ) { #>
                        <span class="sky-seo-status-text">{{ currentDay }}: {{ previewData.hours[currentDay] }}</span>
                    <# } #>
                </div>
            <# } else if ( displayType === 'hours_today' ) { #>
                <div class="sky-seo-hours-today">
                    <span class="sky-seo-hours-label">{{ settings.today_label }}:</span>
                    <span class="sky-seo-hours-time">{{ previewData.hours[currentDay] }}</span>
                </div>
            <# } else if ( displayType === 'hours_week' ) { #>
                <div class="sky-seo-hours-week">
                    <# if ( settings.show_week_title === 'yes' ) { #>
                        <h3 class="sky-seo-hours-title">{{ settings.week_title }}</h3>
                    <# } #>
                    <table class="sky-seo-hours-table">
                        <# 
                        var days = ['Monday', 'Tuesday', 'Wednesday', 'Thursday', 'Friday', 'Saturday', 'Sunday'];
                        _.each(days, function(day) { 
                            var isToday = (day === currentDay && settings.highlight_today === 'yes');
                        #>
                        <tr class="{{ isToday ? 'sky-seo-today' : '' }}">
                            <td class="sky-seo-day">{{ day }}</td>
                            <td class="sky-seo-hours">{{ previewData.hours[day] }}</td>
                        </tr>
                        <# }); #>
                    </table>
                </div>
            <# } else if ( displayType === 'rating' ) { #>
                <div class="sky-seo-rating sky-seo-layout-{{ settings.rating_layout }}">
                    <# if ( settings.show_rating_value === 'yes' ) { #>
                        <span class="sky-seo-rating-value">{{ previewData.rating }}</span>
                    <# } #>
                    <span class="sky-seo-rating-stars" style="font-size: {{ settings.star_size.size }}{{ settings.star_size.unit }};">★★★★★</span>
                    <# if ( settings.show_review_count === 'yes' ) { #>
                        <span class="sky-seo-rating-count">({{ previewData.totalReviews }} reviews)</span>
                    <# } #>
                </div>
            <# } #>
        </div>
        <?php
    }
}

// Do NOT add any hooks or registration here!
// Registration happens through the loader file only