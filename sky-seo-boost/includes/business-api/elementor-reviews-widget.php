<?php
/**
 * Sky SEO Reviews - Elementor Widget
 * Displays Google Reviews from Database
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
 * Sky SEO Reviews Widget Class
 * NO SELF-REGISTRATION - Registration happens through the loader
 */
class Sky_SEO_Reviews_Elementor_Widget extends \Elementor\Widget_Base {
    
    /**
     * Widget name
     */
    public function get_name() {
        return 'sky_seo_reviews';
    }
    
    /**
     * Widget title
     */
    public function get_title() {
        return __('Business Reviews', 'sky-seo-boost');
    }
    
    /**
     * Widget icon
     */
    public function get_icon() {
        return 'eicon-star-o';
    }
    
    /**
     * Widget categories
     */
    public function get_categories() {
        return ['general', 'sky-seo-boost'];
    }
    
    /**
     * Widget keywords
     */
    public function get_keywords() {
        return ['reviews', 'google', 'rating', 'testimonials', 'feedback'];
    }
    
    /**
     * Widget scripts
     */
    public function get_script_depends() {
        return ['sky-seo-reviews-script', 'swiper-js', 'elementor-frontend'];
    }
    
    /**
     * Widget styles
     */
    public function get_style_depends() {
        return ['sky-seo-reviews-style', 'swiper-css', 'elementor-icons'];
    }
    
    /**
     * Get business data from Database or API
     */
    private function get_business_data() {
        try {
            // First try the new database system
            if (class_exists('Sky_SEO_Reviews_Database')) {
                $reviews_db = Sky_SEO_Reviews_Database::get_instance();
                $data = $reviews_db->get_business_data();
                if ($data !== false) {
                    return $data;
                }
            }
            
            // Fallback to Business API
            if (class_exists('Sky_SEO_Business_API')) {
                $business_api = Sky_SEO_Business_API::get_instance();
                return $business_api->get_business_data();
            }
            
            return false;
        } catch (Exception $e) {
            error_log('Sky SEO Reviews Data Error: ' . $e->getMessage());
            return false;
        }
    }
    
    /**
     * Render icon helper
     */
    private function render_icon($icon_data) {
        if (empty($icon_data['value'])) {
            return;
        }
        
        // Check if it's an SVG
        if (!empty($icon_data['library']) && $icon_data['library'] === 'svg') {
            if (!empty($icon_data['value']['url'])) {
                echo '<img src="' . esc_url($icon_data['value']['url']) . '" alt="" />';
            }
            return;
        }
        
        // Font Awesome or other font icons
        if (!empty($icon_data['library'])) {
            $icon_class = $icon_data['value'];
            
            // Ensure Font Awesome 5 compatibility
            if (strpos($icon_data['library'], 'fa-') === 0) {
                // It's a Font Awesome icon
                echo '<i class="' . esc_attr($icon_class) . '" aria-hidden="true"></i>';
            } else {
                // Other icon libraries
                echo '<i class="' . esc_attr($icon_class) . '" aria-hidden="true"></i>';
            }
        }
    }
    
    /**
     * Get platform icon SVG
     */
    private function get_platform_icon($platform) {
        $icons = [
            'google' => '<svg class="sky-google-icon" width="18" height="18" viewBox="0 0 18 18" fill="none" xmlns="http://www.w3.org/2000/svg">
                        <path d="M17.64 9.2c0-.637-.057-1.251-.164-1.84H9v3.481h4.844c-.209 1.125-.843 2.078-1.796 2.717v2.258h2.908c1.702-1.567 2.684-3.874 2.684-6.615z" fill="#4285F4"/>
                        <path d="M9.003 18c2.43 0 4.467-.806 5.956-2.18L12.05 13.56c-.806.54-1.836.86-3.047.86-2.344 0-4.328-1.584-5.036-3.711H.96v2.332C2.44 15.983 5.485 18 9.003 18z" fill="#34A853"/>
                        <path d="M3.964 10.712c-.18-.54-.282-1.117-.282-1.71 0-.593.102-1.17.282-1.71V4.96H.957C.347 6.175 0 7.55 0 9.002c0 1.452.348 2.827.957 4.042l3.007-2.332z" fill="#FBBC05"/>
                        <path d="M9.003 3.58c1.321 0 2.508.454 3.44 1.345l2.582-2.58C13.464.891 11.428 0 9.002 0 5.485 0 2.44 2.017.96 4.958L3.967 7.29c.708-2.127 2.692-3.71 5.036-3.71z" fill="#EA4335"/>
                    </svg>',
            'facebook' => '<svg class="sky-facebook-icon" width="18" height="18" viewBox="0 0 18 18" fill="none" xmlns="http://www.w3.org/2000/svg">
                        <path d="M18 9C18 4.02944 13.9706 0 9 0C4.02944 0 0 4.02944 0 9C0 13.4921 3.29168 17.2155 7.59375 17.8907V11.6016H5.30859V9H7.59375V7.01719C7.59375 4.76156 8.93742 3.51562 10.9932 3.51562C11.9775 3.51562 13.0078 3.69141 13.0078 3.69141V5.90625H11.873C10.755 5.90625 10.4062 6.60006 10.4062 7.3125V9H12.9023L12.5033 11.6016H10.4062V17.8907C14.7083 17.2155 18 13.4921 18 9Z" fill="#1877F2"/>
                    </svg>',
            'trustpilot' => '<svg class="sky-trustpilot-icon" width="18" height="18" viewBox="0 0 18 18" fill="none" xmlns="http://www.w3.org/2000/svg">
                        <rect width="18" height="18" rx="2" fill="#00B67A"/>
                        <path d="M9 2L11.2 7.2H16.8L12.3 10.8L14.5 16L9 12.4L3.5 16L5.7 10.8L1.2 7.2H6.8L9 2Z" fill="white"/>
                    </svg>'
        ];
        
        return $icons[$platform] ?? $icons['google'];
    }
    
    /**
     * Get review likes from database
     */
    private function get_review_likes($review_id) {
        $likes = get_option('sky_seo_review_likes', []);
        return isset($likes[$review_id]) ? intval($likes[$review_id]) : 0;
    }
    
    /**
     * Check if user has liked a review
     */
    private function user_has_liked($review_id) {
        // Check cookie for liked reviews
        if (isset($_COOKIE['sky_liked_reviews'])) {
            $liked_reviews = json_decode(stripslashes($_COOKIE['sky_liked_reviews']), true);
            return is_array($liked_reviews) && in_array($review_id, $liked_reviews);
        }
        return false;
    }
    
    /**
     * Register widget controls
     */
    protected function register_controls() {
        
        // Content Section
        $this->start_controls_section(
            'sky_reviews_content_section',
            [
                'label' => __('Reviews Settings', 'sky-seo-boost'),
                'tab' => \Elementor\Controls_Manager::TAB_CONTENT,
            ]
        );
        
        $this->add_control(
            'sky_reviews_layout',
            [
                'label' => __('Layout', 'sky-seo-boost'),
                'type' => \Elementor\Controls_Manager::SELECT,
                'default' => 'slider', // UPDATED DEFAULT
                'options' => [
                    'slider' => __('Slider', 'sky-seo-boost'),
                    'grid' => __('Grid', 'sky-seo-boost'),
                ],
            ]
        );
        
        $this->add_control(
            'sky_platform_filter',
            [
                'label' => __('Platform Filter', 'sky-seo-boost'),
                'type' => \Elementor\Controls_Manager::SELECT,
                'default' => 'all', // UPDATED DEFAULT
                'options' => [
                    'all' => __('All Platforms', 'sky-seo-boost'),
                    'google' => __('Google Only', 'sky-seo-boost'),
                    'facebook' => __('Facebook Only', 'sky-seo-boost'),
                    'trustpilot' => __('Trustpilot Only', 'sky-seo-boost'),
                ],
            ]
        );
        
        $this->add_control(
            'sky_show_all_reviews',
            [
                'label' => __('Show All Reviews', 'sky-seo-boost'),
                'type' => \Elementor\Controls_Manager::SWITCHER,
                'default' => 'yes', // UPDATED DEFAULT
                'description' => __('Enable to show all available reviews. When disabled, limits reviews based on "Number of Reviews" setting.', 'sky-seo-boost'),
            ]
        );
        
        $this->add_control(
            'sky_reviews_count',
            [
                'label' => __('Number of Reviews', 'sky-seo-boost'),
                'type' => \Elementor\Controls_Manager::NUMBER,
                'default' => 5,
                'min' => 1,
                'max' => 50,
                'condition' => [
                    'sky_show_all_reviews!' => 'yes',
                ],
            ]
        );
        
        $this->add_control(
            'sky_randomize_reviews',
            [
                'label' => __('Randomize Reviews', 'sky-seo-boost'),
                'type' => \Elementor\Controls_Manager::SWITCHER,
                'default' => 'yes', // UPDATED DEFAULT
                'description' => __('Show reviews in random order on each page load', 'sky-seo-boost'),
            ]
        );
        
        $this->add_control(
            'sky_hide_empty_reviews',
            [
                'label' => __('Hide Empty Reviews', 'sky-seo-boost'),
                'type' => \Elementor\Controls_Manager::SWITCHER,
                'default' => 'no', // UPDATED DEFAULT
                'description' => __('Hide reviews that have no text content', 'sky-seo-boost'),
            ]
        );
        
        $this->add_control(
            'sky_show_likes',
            [
                'label' => __('Show Like Button', 'sky-seo-boost'),
                'type' => \Elementor\Controls_Manager::SWITCHER,
                'default' => 'yes', // UPDATED DEFAULT
                'description' => __('Show like button with count on each review', 'sky-seo-boost'),
            ]
        );
        
        $this->add_control(
            'sky_grid_columns',
            [
                'label' => __('Grid Columns', 'sky-seo-boost'),
                'type' => \Elementor\Controls_Manager::SELECT,
                'default' => '3',
                'options' => [
                    '1' => __('1 Column', 'sky-seo-boost'),
                    '2' => __('2 Columns', 'sky-seo-boost'),
                    '3' => __('3 Columns', 'sky-seo-boost'),
                    '4' => __('4 Columns', 'sky-seo-boost'),
                ],
                'condition' => [
                    'sky_reviews_layout' => 'grid',
                ],
            ]
        );
        
        $this->add_control(
            'sky_show_load_more',
            [
                'label' => __('Show Load More Button', 'sky-seo-boost'),
                'type' => \Elementor\Controls_Manager::SWITCHER,
                'default' => 'no',
                'condition' => [
                    'sky_reviews_layout' => 'grid',
                    'sky_show_all_reviews!' => 'yes',
                ],
            ]
        );

        $this->add_control(
            'sky_load_more_text',
            [
                'label' => __('Load More Text', 'sky-seo-boost'),
                'type' => \Elementor\Controls_Manager::TEXT,
                'default' => __('Load More', 'sky-seo-boost'),
                'condition' => [
                    'sky_reviews_layout' => 'grid',
                    'sky_show_load_more' => 'yes',
                ],
            ]
        );
        
        $this->add_control(
            'sky_min_rating',
            [
                'label' => __('Minimum Rating', 'sky-seo-boost'),
                'type' => \Elementor\Controls_Manager::NUMBER,
                'default' => 5, // UPDATED DEFAULT
                'min' => 0,
                'max' => 5,
            ]
        );
        
        $this->add_control(
            'sky_text_lines',
            [
                'label' => __('Text Lines Before Truncate', 'sky-seo-boost'),
                'type' => \Elementor\Controls_Manager::NUMBER,
                'default' => 5, // UPDATED DEFAULT
                'min' => 2,
                'max' => 10,
                'description' => __('Number of lines to show before "Read more" appears', 'sky-seo-boost'),
            ]
        );
        
        $this->add_control(
            'sky_dark_mode',
            [
                'label' => __('Dark Mode', 'sky-seo-boost'),
                'type' => \Elementor\Controls_Manager::SWITCHER,
                'default' => 'no', // DEFAULT TO OFF (LIGHT MODE)
                'description' => __('Enable dark mode for reviews (dark background, light text)', 'sky-seo-boost'),
                'separator' => 'before',
            ]
        );
        
        $this->end_controls_section();
        
        // Carousel Section - NEW
        $this->start_controls_section(
            'sky_carousel_section',
            [
                'label' => __('Carousel', 'sky-seo-boost'),
                'tab' => \Elementor\Controls_Manager::TAB_CONTENT,
                'condition' => [
                    'sky_reviews_layout' => 'slider',
                ],
            ]
        );
        
        $this->add_control(
            'sky_slider_items_per_view',
            [
                'label' => __('Slides per view', 'sky-seo-boost'),
                'type' => \Elementor\Controls_Manager::NUMBER,
                'default' => 3, // UPDATED DEFAULT
                'min' => 1,
                'max' => 6,
                'step' => 0.1,
                'description' => __('Set numbers of slides you want to display at the same time on slider\'s container for carousel mode.', 'sky-seo-boost'),
            ]
        );
        
        $this->add_control(
            'sky_slider_space_between',
            [
                'label' => __('Space between', 'sky-seo-boost'),
                'type' => \Elementor\Controls_Manager::SELECT,
                'default' => '0', // UPDATED DEFAULT
                'options' => [
                    '0' => __('0px', 'sky-seo-boost'),
                    '10' => __('10px', 'sky-seo-boost'),
                    '20' => __('20px', 'sky-seo-boost'),
                    '30' => __('30px', 'sky-seo-boost'),
                    '40' => __('40px', 'sky-seo-boost'),
                    '50' => __('50px', 'sky-seo-boost'),
                ],
            ]
        );
        
        $this->add_control(
            'sky_slider_scroll_per_page',
            [
                'label' => __('Scroll per page', 'sky-seo-boost'),
                'type' => \Elementor\Controls_Manager::SWITCHER,
                'default' => 'no', // UPDATED DEFAULT
                'description' => __('Scroll per page not per item. This affects next/prev buttons and mouse/touch dragging.', 'sky-seo-boost'),
            ]
        );
        
        $this->add_control(
            'sky_slider_loop',
            [
                'label' => __('Slider loop', 'sky-seo-boost'),
                'type' => \Elementor\Controls_Manager::SWITCHER,
                'default' => 'yes', // UPDATED DEFAULT
            ]
        );
        
        $this->add_control(
            'sky_slider_auto_height',
            [
                'label' => __('Auto height', 'sky-seo-boost'),
                'type' => \Elementor\Controls_Manager::SWITCHER,
                'default' => 'yes', // UPDATED DEFAULT
            ]
        );
        
        $this->add_control(
            'sky_slider_autoplay',
            [
                'label' => __('Slider autoplay', 'sky-seo-boost'),
                'type' => \Elementor\Controls_Manager::SWITCHER,
                'default' => 'yes', // UPDATED DEFAULT
            ]
        );
        
        $this->add_control(
            'sky_slider_autoplay_delay',
            [
                'label' => __('Autoplay delay (ms)', 'sky-seo-boost'),
                'type' => \Elementor\Controls_Manager::NUMBER,
                'default' => 3000, // UPDATED DEFAULT
                'min' => 1000,
                'max' => 10000,
                'step' => 100,
                'condition' => [
                    'sky_slider_autoplay' => 'yes',
                ],
            ]
        );
        
        $this->add_control(
            'sky_slider_init_on_scroll',
            [
                'label' => __('Init carousel on scroll', 'sky-seo-boost'),
                'type' => \Elementor\Controls_Manager::SWITCHER,
                'default' => 'no', // UPDATED DEFAULT
                'description' => __('This option allows you to init carousel script only when visitor scroll the page to the slider. Useful for performance optimization.', 'sky-seo-boost'),
            ]
        );
        
        $this->add_control(
            'sky_slider_disabled_overflow',
            [
                'label' => __('Disabled overflow', 'sky-seo-boost'),
                'type' => \Elementor\Controls_Manager::SWITCHER,
                'default' => 'no', // UPDATED DEFAULT
            ]
        );
        
        $this->add_control(
            'sky_slider_center_mode',
            [
                'label' => __('Center Mode', 'sky-seo-boost'),
                'type' => \Elementor\Controls_Manager::SWITCHER,
                'default' => 'yes', // UPDATED DEFAULT
                'description' => __('Center the active slide', 'sky-seo-boost'),
            ]
        );
        
        $this->add_control(
            'sky_show_review_button',
            [
                'label' => __('Show Review Us Button', 'sky-seo-boost'),
                'type' => \Elementor\Controls_Manager::SWITCHER,
                'default' => 'yes', // UPDATED DEFAULT
            ]
        );

        $this->add_control(
            'sky_review_button_text',
            [
                'label' => __('Review Button Text', 'sky-seo-boost'),
                'type' => \Elementor\Controls_Manager::TEXT,
                'default' => __('Review Us', 'sky-seo-boost'), // UPDATED DEFAULT
                'condition' => [
                    'sky_show_review_button' => 'yes',
                ],
            ]
        );
        
        $this->add_control(
            'sky_review_button_icon',
            [
                'label' => __('Button Icon', 'sky-seo-boost'),
                'type' => \Elementor\Controls_Manager::ICONS,
                'default' => [
                    'value' => 'fab fa-google',
                    'library' => 'fa-brands',
                ],
                'condition' => [
                    'sky_show_review_button' => 'yes',
                ],
            ]
        );
        
        $this->add_control(
            'sky_review_button_icon_position',
            [
                'label' => __('Icon Position', 'sky-seo-boost'),
                'type' => \Elementor\Controls_Manager::SELECT,
                'default' => 'before', // UPDATED DEFAULT
                'options' => [
                    'before' => __('Before Text', 'sky-seo-boost'),
                    'after' => __('After Text', 'sky-seo-boost'),
                ],
                'condition' => [
                    'sky_show_review_button' => 'yes',
                    'sky_review_button_icon[value]!' => '',
                ],
            ]
        );
        
        $this->end_controls_section();
        
        // Modal Settings Section
        $this->start_controls_section(
            'sky_modal_section',
            [
                'label' => __('Modal Settings', 'sky-seo-boost'),
                'tab' => \Elementor\Controls_Manager::TAB_CONTENT,
            ]
        );
        
        $this->add_control(
            'sky_modal_bg_color',
            [
                'label' => __('Background Color', 'sky-seo-boost'),
                'type' => \Elementor\Controls_Manager::COLOR,
                'default' => '#ffffff',
                'selectors' => [
                    '{{WRAPPER}} .sky-review-modal' => 'background-color: {{VALUE}};',
                ],
            ]
        );
        
        $this->add_control(
            'sky_modal_text_color',
            [
                'label' => __('Text Color', 'sky-seo-boost'),
                'type' => \Elementor\Controls_Manager::COLOR,
                'default' => '#202124',
                'selectors' => [
                    '{{WRAPPER}} .sky-modal-review-text' => 'color: {{VALUE}};',
                ],
            ]
        );
        
        $this->add_control(
            'sky_modal_border_radius',
            [
                'label' => __('Border Radius', 'sky-seo-boost'),
                'type' => \Elementor\Controls_Manager::SLIDER,
                'size_units' => ['px', '%'],
                'range' => [
                    'px' => [
                        'min' => 0,
                        'max' => 50,
                    ],
                ],
                'default' => [
                    'unit' => 'px',
                    'size' => 16,
                ],
                'selectors' => [
                    '{{WRAPPER}} .sky-review-modal' => 'border-radius: {{SIZE}}{{UNIT}};',
                ],
            ]
        );
        
        $this->add_group_control(
            \Elementor\Group_Control_Typography::get_type(),
            [
                'name' => 'sky_modal_typography',
                'label' => __('Modal Text Typography', 'sky-seo-boost'),
                'selector' => '{{WRAPPER}} .sky-modal-review-text',
            ]
        );
        
        $this->end_controls_section();
        
        // Style Section - Reviews
        $this->start_controls_section(
            'sky_style_reviews',
            [
                'label' => __('Reviews Style', 'sky-seo-boost'),
                'tab' => \Elementor\Controls_Manager::TAB_STYLE,
            ]
        );
        
        $this->add_control(
            'sky_review_spacing',
            [
                'label' => __('Review Spacing', 'sky-seo-boost'),
                'type' => \Elementor\Controls_Manager::SLIDER,
                'size_units' => ['px'],
                'range' => [
                    'px' => [
                        'min' => 10,
                        'max' => 50,
                    ],
                ],
                'default' => [
                    'unit' => 'px',
                    'size' => 20,
                ],
                'selectors' => [
                    '{{WRAPPER}} .sky-review-item + .sky-review-item' => 'margin-top: {{SIZE}}{{UNIT}};',
                ],
            ]
        );
        
        $this->add_responsive_control(
            'sky_review_padding',
            [
                'label' => __('Review Padding', 'sky-seo-boost'),
                'type' => \Elementor\Controls_Manager::DIMENSIONS,
                'size_units' => ['px', 'em'],
                'default' => [
                    'top' => '20',
                    'right' => '20',
                    'bottom' => '20',
                    'left' => '20',
                    'unit' => 'px',
                ],
                'selectors' => [
                    '{{WRAPPER}} .sky-review-item' => 'padding: {{TOP}}{{UNIT}} {{RIGHT}}{{UNIT}} {{BOTTOM}}{{UNIT}} {{LEFT}}{{UNIT}};',
                ],
            ]
        );
        
        $this->add_control(
            'sky_review_background',
            [
                'label' => __('Review Background', 'sky-seo-boost'),
                'type' => \Elementor\Controls_Manager::COLOR,
                'default' => '#F5F5F5', // LIGHT MODE DEFAULT
                'selectors' => [
                    '{{WRAPPER}} .sky-review-item' => 'background-color: {{VALUE}};',
                ],
            ]
        );
        
        $this->add_group_control(
            \Elementor\Group_Control_Border::get_type(),
            [
                'name' => 'sky_review_border',
                'selector' => '{{WRAPPER}} .sky-review-item',
                'fields_options' => [
                    'border' => [
                        'default' => 'solid',
                    ],
                    'width' => [
                        'default' => [
                            'top' => '1',
                            'right' => '1',
                            'bottom' => '1',
                            'left' => '1',
                            'isLinked' => true,
                        ],
                    ],
                    'color' => [
                        'default' => '#E0E0E0', // LIGHT MODE DEFAULT
                    ],
                ],
            ]
        );
        
        $this->add_responsive_control(
            'sky_review_border_radius',
            [
                'label' => __('Border Radius', 'sky-seo-boost'),
                'type' => \Elementor\Controls_Manager::DIMENSIONS,
                'size_units' => ['px', '%'],
                'default' => [
                    'top' => '12',
                    'right' => '12',
                    'bottom' => '12',
                    'left' => '12',
                    'unit' => 'px',
                    'isLinked' => true,
                ],
                'selectors' => [
                    '{{WRAPPER}} .sky-review-item' => 'border-radius: {{TOP}}{{UNIT}} {{RIGHT}}{{UNIT}} {{BOTTOM}}{{UNIT}} {{LEFT}}{{UNIT}};',
                ],
            ]
        );
        
        // Author Name
        $this->add_group_control(
            \Elementor\Group_Control_Typography::get_type(),
            [
                'name' => 'sky_author_typography',
                'label' => __('Author Typography', 'sky-seo-boost'),
                'selector' => '{{WRAPPER}} .sky-review-author-name',
                'separator' => 'before',
            ]
        );
        
        $this->add_control(
            'sky_author_color',
            [
                'label' => __('Author Color', 'sky-seo-boost'),
                'type' => \Elementor\Controls_Manager::COLOR,
                'default' => '#202124', // LIGHT MODE DEFAULT
                'selectors' => [
                    '{{WRAPPER}} .sky-review-author-name' => 'color: {{VALUE}};',
                ],
            ]
        );
        
        // Star Rating
        $this->add_control(
            'sky_star_heading',
            [
                'label' => __('Star Rating', 'sky-seo-boost'),
                'type' => \Elementor\Controls_Manager::HEADING,
                'separator' => 'before',
            ]
        );
        
        $this->add_control(
            'sky_star_color',
            [
                'label' => __('Star Color', 'sky-seo-boost'),
                'type' => \Elementor\Controls_Manager::COLOR,
                'default' => '#FBBC04', // Same for both modes
                'selectors' => [
                    '{{WRAPPER}} .sky-review-rating' => 'color: {{VALUE}};',
                ],
            ]
        );
        
        $this->add_control(
            'sky_star_size',
            [
                'label' => __('Star Size', 'sky-seo-boost'),
                'type' => \Elementor\Controls_Manager::SLIDER,
                'size_units' => ['px', 'em'],
                'range' => [
                    'px' => [
                        'min' => 12,
                        'max' => 50,
                    ],
                    'em' => [
                        'min' => 0.8,
                        'max' => 3.5,
                    ],
                ],
                'default' => [
                    'unit' => 'px',
                    'size' => 26,
                ],
                'selectors' => [
                    '{{WRAPPER}} .sky-review-rating' => 'font-size: {{SIZE}}{{UNIT}};',
                    '{{WRAPPER}} .sky-review-rating .sky-verified-review svg' => 'width: calc({{SIZE}}{{UNIT}} * 0.8); height: calc({{SIZE}}{{UNIT}} * 0.8);',
                ],
            ]
        );
        
        $this->add_control(
            'sky_star_spacing',
            [
                'label' => __('Star Spacing', 'sky-seo-boost'),
                'type' => \Elementor\Controls_Manager::SLIDER,
                'size_units' => ['px'],
                'range' => [
                    'px' => [
                        'min' => 0,
                        'max' => 10,
                    ],
                ],
                'default' => [
                    'unit' => 'px',
                    'size' => 3,
                ],
                'selectors' => [
                    '{{WRAPPER}} .sky-review-rating' => 'letter-spacing: {{SIZE}}{{UNIT}};',
                ],
            ]
        );
        
        // Review Text
        $this->add_group_control(
            \Elementor\Group_Control_Typography::get_type(),
            [
                'name' => 'sky_review_text_typography',
                'label' => __('Review Text Typography', 'sky-seo-boost'),
                'selector' => '{{WRAPPER}} .sky-review-text',
                'separator' => 'before',
            ]
        );
        
        $this->add_control(
            'sky_review_text_color',
            [
                'label' => __('Review Text Color', 'sky-seo-boost'),
                'type' => \Elementor\Controls_Manager::COLOR,
                'default' => '#202124', // LIGHT MODE DEFAULT
                'selectors' => [
                    '{{WRAPPER}} .sky-review-text' => 'color: {{VALUE}};',
                ],
            ]
        );
        
        // Time
        $this->add_group_control(
            \Elementor\Group_Control_Typography::get_type(),
            [
                'name' => 'sky_review_time_typography',
                'label' => __('Time Typography', 'sky-seo-boost'),
                'selector' => '{{WRAPPER}} .sky-review-time',
                'separator' => 'before',
            ]
        );
        
        $this->add_control(
            'sky_review_time_color',
            [
                'label' => __('Time Color', 'sky-seo-boost'),
                'type' => \Elementor\Controls_Manager::COLOR,
                'default' => '#5F6368', // LIGHT MODE DEFAULT
                'selectors' => [
                    '{{WRAPPER}} .sky-review-time' => 'color: {{VALUE}};',
                ],
            ]
        );
        
        // Read More Style
        $this->add_control(
            'sky_read_more_heading',
            [
                'label' => __('Read More Style', 'sky-seo-boost'),
                'type' => \Elementor\Controls_Manager::HEADING,
                'separator' => 'before',
            ]
        );
        
        $this->add_control(
            'sky_read_more_color',
            [
                'label' => __('Read More Color', 'sky-seo-boost'),
                'type' => \Elementor\Controls_Manager::COLOR,
                'default' => '#5F6368', // LIGHT MODE DEFAULT
                'selectors' => [
                    '{{WRAPPER}} .sky-read-more-toggle' => 'color: {{VALUE}};',
                ],
            ]
        );
        
        $this->add_control(
            'sky_read_more_hover_color',
            [
                'label' => __('Read More Hover Color', 'sky-seo-boost'),
                'type' => \Elementor\Controls_Manager::COLOR,
                'default' => '#1A73E8', // LIGHT MODE DEFAULT
                'selectors' => [
                    '{{WRAPPER}} .sky-read-more-toggle:hover' => 'color: {{VALUE}};',
                ],
            ]
        );
        
        $this->end_controls_section();
        
        // Review Button Style Section
        $this->start_controls_section(
            'sky_review_button_style',
            [
                'label' => __('Review Button Style', 'sky-seo-boost'),
                'tab' => \Elementor\Controls_Manager::TAB_STYLE,
                'condition' => [
                    'sky_reviews_layout' => 'slider',
                    'sky_show_review_button' => 'yes',
                ],
            ]
        );

        $this->add_control(
            'sky_review_button_bg_color',
            [
                'label' => __('Background Color', 'sky-seo-boost'),
                'type' => \Elementor\Controls_Manager::COLOR,
                'default' => '#1A73E8',
                'selectors' => [
                    '{{WRAPPER}} .sky-review-us-button' => 'background-color: {{VALUE}};',
                ],
            ]
        );

        $this->add_control(
            'sky_review_button_text_color',
            [
                'label' => __('Text Color', 'sky-seo-boost'),
                'type' => \Elementor\Controls_Manager::COLOR,
                'default' => '#ffffff',
                'selectors' => [
                    '{{WRAPPER}} .sky-review-us-button' => 'color: {{VALUE}} !important;',
                ],
            ]
        );

        $this->add_control(
            'sky_review_button_hover_bg_color',
            [
                'label' => __('Hover Background Color', 'sky-seo-boost'),
                'type' => \Elementor\Controls_Manager::COLOR,
                'default' => '#1557B0',
                'selectors' => [
                    '{{WRAPPER}} .sky-review-us-button:hover' => 'background-color: {{VALUE}};',
                ],
            ]
        );

        $this->add_responsive_control(
            'sky_review_button_padding',
            [
                'label' => __('Padding', 'sky-seo-boost'),
                'type' => \Elementor\Controls_Manager::DIMENSIONS,
                'size_units' => ['px', 'em'],
                'default' => [
                    'top' => '12',
                    'right' => '24',
                    'bottom' => '12',
                    'left' => '24',
                    'unit' => 'px',
                ],
                'selectors' => [
                    '{{WRAPPER}} .sky-review-us-button' => 'padding: {{TOP}}{{UNIT}} {{RIGHT}}{{UNIT}} {{BOTTOM}}{{UNIT}} {{LEFT}}{{UNIT}};',
                ],
            ]
        );

        $this->add_group_control(
            \Elementor\Group_Control_Typography::get_type(),
            [
                'name' => 'sky_review_button_typography',
                'selector' => '{{WRAPPER}} .sky-review-us-button',
            ]
        );

        $this->add_control(
            'sky_review_button_border_radius',
            [
                'label' => __('Border Radius', 'sky-seo-boost'),
                'type' => \Elementor\Controls_Manager::SLIDER,
                'size_units' => ['px', '%'],
                'range' => [
                    'px' => [
                        'min' => 0,
                        'max' => 50,
                    ],
                    '%' => [
                        'min' => 0,
                        'max' => 100,
                    ],
                ],
                'default' => [
                    'unit' => 'px',
                    'size' => 24,
                ],
                'selectors' => [
                    '{{WRAPPER}} .sky-review-us-button' => 'border-radius: {{SIZE}}{{UNIT}};',
                ],
            ]
        );

        $this->add_control(
            'sky_review_button_icon_size',
            [
                'label' => __('Icon Size', 'sky-seo-boost'),
                'type' => \Elementor\Controls_Manager::SLIDER,
                'size_units' => ['px'],
                'range' => [
                    'px' => [
                        'min' => 10,
                        'max' => 50,
                    ],
                ],
                'default' => [
                    'unit' => 'px',
                    'size' => 16,
                ],
                'selectors' => [
                    '{{WRAPPER}} .sky-review-us-button .sky-button-icon' => 'font-size: {{SIZE}}{{UNIT}};',
                    '{{WRAPPER}} .sky-review-us-button .sky-button-icon svg' => 'width: {{SIZE}}{{UNIT}}; height: {{SIZE}}{{UNIT}};',
                ],
                'condition' => [
                    'sky_review_button_icon[value]!' => '',
                ],
            ]
        );

        $this->add_control(
            'sky_review_button_icon_spacing',
            [
                'label' => __('Icon Spacing', 'sky-seo-boost'),
                'type' => \Elementor\Controls_Manager::SLIDER,
                'size_units' => ['px'],
                'range' => [
                    'px' => [
                        'min' => 0,
                        'max' => 30,
                    ],
                ],
                'default' => [
                    'unit' => 'px',
                    'size' => 8,
                ],
                'selectors' => [
                    '{{WRAPPER}} .sky-review-us-button .sky-icon-before' => 'margin-right: {{SIZE}}{{UNIT}};',
                    '{{WRAPPER}} .sky-review-us-button .sky-icon-after' => 'margin-left: {{SIZE}}{{UNIT}};',
                ],
                'condition' => [
                    'sky_review_button_icon[value]!' => '',
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
        
        // Check if Business API or Database is available
        if (!class_exists('Sky_SEO_Business_API') && !class_exists('Sky_SEO_Reviews_Database')) {
            if (\Elementor\Plugin::$instance->editor->is_edit_mode()) {
                echo '<div class="elementor-alert elementor-alert-warning">';
                echo __('Business API is not available. Please ensure the Sky SEO Boost plugin is properly configured.', 'sky-seo-boost');
                echo '</div>';
            }
            return;
        }
        
        // Enqueue required styles
        if (wp_style_is('sky-seo-reviews-style', 'registered')) {
            wp_enqueue_style('sky-seo-reviews-style');
        } elseif (wp_style_is('sky-elementor-style', 'registered')) {
            wp_enqueue_style('sky-elementor-style');
        }
        
        // Ensure Elementor icons are loaded
        if (!wp_style_is('elementor-icons', 'enqueued')) {
            wp_enqueue_style('elementor-icons');
        }
        
        // Ensure Font Awesome is loaded if needed
        if (!empty($settings['sky_review_button_icon']['value']) && !empty($settings['sky_review_button_icon']['library'])) {
            if (strpos($settings['sky_review_button_icon']['library'], 'fa') === 0) {
                wp_enqueue_style('font-awesome');
                wp_enqueue_style('font-awesome-5');
                wp_enqueue_style('font-awesome-5-all');
            }
        }
        
        // Enqueue required scripts
        if (wp_script_is('sky-seo-reviews-script', 'registered')) {
            wp_enqueue_script('sky-seo-reviews-script');
        } elseif (wp_script_is('sky-elementor-script', 'registered')) {
            wp_enqueue_script('sky-elementor-script');
        }
        
        // Localize script with AJAX data
        wp_localize_script('sky-seo-reviews-script', 'sky_seo_ajax', array(
            'ajax_url' => admin_url('admin-ajax.php'),
            'nonce' => wp_create_nonce('sky_seo_reviews_nonce')
        ));
        
        // Add inline style for text truncation
        $text_lines = intval($settings['sky_text_lines']);
        wp_add_inline_style('sky-seo-reviews-style', "
            #elementor-element-{$widget_id} .sky-review-text.sky-truncated {
                -webkit-line-clamp: {$text_lines};
            }
            
            /* Ensure verified badge displays properly */
            .sky-review-rating {
                display: flex !important;
                align-items: center !important;
                gap: 8px !important;
            }
            
            .sky-verified-review {
                display: inline-flex !important;
                align-items: center !important;
                margin-left: 6px !important;
            }
            
            .sky-verified-review svg {
                width: 16px !important;
                height: 16px !important;
                display: block !important;
            }
            
            /* Align Read More button to the left */
            #elementor-element-{$widget_id} .sky-review-footer {
                text-align: left !important;
                margin-top: 8px !important;
            }
            
            #elementor-element-{$widget_id} .sky-read-more-toggle {
                margin: 0 !important;
                padding: 0 !important;
                text-align: left !important;
            }
        ");
        
        $data = $this->get_business_data();
        
        if (!$data || empty($data['reviews'])) {
            if (\Elementor\Plugin::$instance->editor->is_edit_mode()) {
                echo '<div class="elementor-alert elementor-alert-info">';
                echo __('No reviews available. You can add manual reviews in the Business API settings or fetch Google reviews if you have a Place ID configured.', 'sky-seo-boost');
                echo '</div>';
            } else {
                // MODIFIED: Show review button even when no reviews exist
                $settings_data = get_option('sky_seo_business_settings', []);
                $place_id = $settings_data['place_id'] ?? '';
                $business_name = $settings_data['business_name'] ?? '';
                
                // Determine review URL
                if (!empty($place_id) && $place_id !== 'manual_reviews_only') {
                    $review_url = 'https://search.google.com/local/writereview?placeid=' . urlencode($place_id);
                } else {
                    if (!empty($business_name)) {
                        $review_url = 'https://www.google.com/search?q=' . urlencode($business_name . ' reviews');
                    } else {
                        $review_url = 'https://www.google.com';
                    }
                }
                
                echo '<div class="sky-no-reviews-container">';
                echo '<p>' . esc_html__('Be the first to leave a review!', 'sky-seo-boost') . '</p>';
                if ($settings['sky_show_review_button'] === 'yes') {
                    echo '<a href="' . esc_url($review_url) . '" target="_blank" rel="noopener noreferrer" class="sky-review-us-button">';
                    echo esc_html($settings['sky_review_button_text']);
                    echo '</a>';
                }
                echo '</div>';
            }
            return;
        }
        
        // Process reviews based on settings
        $reviews = $data['reviews'];
        
        // Filter by platform
        $platform_filter = $settings['sky_platform_filter'];
        if ($platform_filter !== 'all') {
            $reviews = array_filter($reviews, function($review) use ($platform_filter) {
                return ($review['platform'] ?? 'google') === $platform_filter;
            });
        }
        
        // Filter by minimum rating
        $min_rating = intval($settings['sky_min_rating']);
        if ($min_rating > 0) {
            $reviews = array_filter($reviews, function($review) use ($min_rating) {
                return isset($review['rating']) && $review['rating'] >= $min_rating;
            });
        }
        
        // Hide empty reviews if enabled
        if ($settings['sky_hide_empty_reviews'] === 'yes') {
            $reviews = array_filter($reviews, function($review) {
                return !empty(trim($review['text'] ?? ''));
            });
        }
        
        // Re-index array after filtering
        $reviews = array_values($reviews);
        
        // Randomize reviews if enabled
        if ($settings['sky_randomize_reviews'] === 'yes') {
            shuffle($reviews);
        }
        
        // Limit reviews if not showing all
        if ($settings['sky_show_all_reviews'] !== 'yes') {
            $reviews = array_slice($reviews, 0, intval($settings['sky_reviews_count']));
        }
        
        // Check if we still have reviews after filtering
        if (empty($reviews)) {
            echo '<p>' . esc_html__('No reviews match the current filter criteria', 'sky-seo-boost') . '</p>';
            return;
        }
        
        $layout = $settings['sky_reviews_layout'];
        $unique_class = 'sky-reviews-' . $widget_id;
        
        if ($layout === 'slider') {
            // Get place_id for the review link
            $settings_data = get_option('sky_seo_business_settings', []);
            $place_id = $settings_data['place_id'] ?? '';
            $business_name = $settings_data['business_name'] ?? '';
            
            // MODIFIED: Use Google search if no place_id, otherwise use write review link
            if (!empty($place_id) && $place_id !== 'manual_reviews_only') {
                $review_url = 'https://search.google.com/local/writereview?placeid=' . urlencode($place_id);
            } else {
                // Use Google search for the business name or just Google.com
                if (!empty($business_name)) {
                    $review_url = 'https://www.google.com/search?q=' . urlencode($business_name . ' reviews');
                } else {
                    $review_url = 'https://www.google.com';
                }
            }
            
            // Check if using custom Google icon
            $is_google_icon = !empty($settings['sky_review_button_icon']['value']) && $settings['sky_review_button_icon']['value'] === 'sky-google-logo';
            ?>
            <div class="sky-seo-reviews-slider <?php echo esc_attr($unique_class); ?> <?php echo $settings['sky_dark_mode'] === 'yes' ? 'sky-dark-mode' : ''; ?>" 
                 id="sky-reviews-slider-<?php echo esc_attr($widget_id); ?>" 
                 data-items="<?php echo esc_attr($settings['sky_slider_items_per_view']); ?>"
                 data-autoplay="<?php echo esc_attr($settings['sky_slider_autoplay']); ?>"
                 data-center="<?php echo esc_attr($settings['sky_slider_center_mode']); ?>"
                 data-space-between="<?php echo esc_attr($settings['sky_slider_space_between']); ?>"
                 data-scroll-per-page="<?php echo esc_attr($settings['sky_slider_scroll_per_page']); ?>"
                 data-loop="<?php echo esc_attr($settings['sky_slider_loop']); ?>"
                 data-auto-height="<?php echo esc_attr($settings['sky_slider_auto_height']); ?>"
                 data-autoplay-delay="<?php echo esc_attr($settings['sky_slider_autoplay_delay'] ?? 5000); ?>"
                 data-init-on-scroll="<?php echo esc_attr($settings['sky_slider_init_on_scroll']); ?>"
                 data-disabled-overflow="<?php echo esc_attr($settings['sky_slider_disabled_overflow']); ?>">
                <div class="swiper">
                    <div class="swiper-wrapper">
                        <?php foreach ($reviews as $review) : ?>
                            <div class="swiper-slide">
                                <?php $this->render_review_item($review, $settings); ?>
                            </div>
                        <?php endforeach; ?>
                    </div>
                </div>
                
                <!-- Navigation section below slider -->
                <div class="sky-slider-navigation">
                    <?php if ($settings['sky_show_review_button'] === 'yes') : ?>
                        <a href="<?php echo esc_url($review_url); ?>" 
                           target="_blank" 
                           rel="noopener noreferrer" 
                           class="sky-review-us-button">
                            <?php 
                            // Render icon if selected and position is before
                            if (!empty($settings['sky_review_button_icon']['value'])) {
                                if ($settings['sky_review_button_icon_position'] === 'before') {
                                    echo '<span class="sky-button-icon sky-icon-before">';
                                    if ($settings['sky_review_button_icon']['value'] === 'sky-google-logo') {
                                        echo $this->get_platform_icon('google');
                                    } else {
                                        \Elementor\Icons_Manager::render_icon($settings['sky_review_button_icon'], ['aria-hidden' => 'true']);
                                    }
                                    echo '</span>';
                                }
                            }
                            ?>
                            <span class="sky-button-text"><?php echo esc_html($settings['sky_review_button_text']); ?></span>
                            <?php 
                            // Render icon if selected and position is after
                            if (!empty($settings['sky_review_button_icon']['value'])) {
                                if ($settings['sky_review_button_icon_position'] === 'after') {
                                    echo '<span class="sky-button-icon sky-icon-after">';
                                    if ($settings['sky_review_button_icon']['value'] === 'sky-google-logo') {
                                        echo $this->get_platform_icon('google');
                                    } else {
                                        \Elementor\Icons_Manager::render_icon($settings['sky_review_button_icon'], ['aria-hidden' => 'true']);
                                    }
                                    echo '</span>';
                                }
                            }
                            ?>
                        </a>
                    <?php endif; ?>
                    
                    <div class="sky-navigation-arrows">
                        <div class="swiper-button-prev"></div>
                        <div class="swiper-button-next"></div>
                    </div>
                </div>
            </div>
            <?php
        } else {
            // Grid layout with Load More functionality
            $columns = intval($settings['sky_grid_columns']);
            $show_load_more = $settings['sky_show_load_more'] === 'yes' && $settings['sky_show_all_reviews'] !== 'yes';
            $initial_count = intval($settings['sky_reviews_count']);
            ?>
            <div class="sky-seo-reviews-grid sky-columns-<?php echo esc_attr($columns); ?> <?php echo esc_attr($unique_class); ?> <?php echo $settings['sky_dark_mode'] === 'yes' ? 'sky-dark-mode' : ''; ?>" 
                 id="sky-reviews-grid-<?php echo esc_attr($widget_id); ?>"
                 data-initial="<?php echo esc_attr($initial_count); ?>">
                <?php 
                foreach ($reviews as $index => $review) : 
                    $hidden_class = ($show_load_more && $index >= $initial_count) ? 'sky-review-hidden' : '';
                    ?>
                    <div class="sky-review-wrapper <?php echo esc_attr($hidden_class); ?>" data-index="<?php echo esc_attr($index); ?>">
                        <?php $this->render_review_item($review, $settings); ?>
                    </div>
                <?php endforeach; ?>
            </div>
            <?php if ($show_load_more && count($reviews) > $initial_count) : ?>
                <div class="sky-load-more-wrapper">
                    <button class="sky-load-more-btn" 
                            type="button"
                            data-grid-id="sky-reviews-grid-<?php echo esc_attr($widget_id); ?>">
                        <?php echo esc_html($settings['sky_load_more_text']); ?>
                    </button>
                </div>
            <?php endif;
        }
        
        // Add JavaScript for modal trigger only (main functionality is in elementor.js)
        ?>
        <script>
        jQuery(document).ready(function($) {
            // Ensure sky_seo_ajax is available
            if (typeof sky_seo_ajax === 'undefined') {
                window.sky_seo_ajax = {
                    ajax_url: '<?php echo admin_url('admin-ajax.php'); ?>',
                    nonce: '<?php echo wp_create_nonce('sky_seo_reviews_nonce'); ?>'
                };
            }
            
            // Initialize liked states from cookie
            setTimeout(function() {
                var likedReviews = getCookie('sky_liked_reviews');
                if (likedReviews) {
                    try {
                        var likes = JSON.parse(likedReviews);
                        likes.forEach(function(reviewId) {
                            $('.sky-review-like-button[data-review-id="' + reviewId + '"]').addClass('liked');
                        });
                    } catch(e) {}
                }
            }, 100);
            
            function getCookie(name) {
                var nameEQ = name + "=";
                var ca = document.cookie.split(';');
                for(var i=0;i < ca.length;i++) {
                    var c = ca[i];
                    while (c.charAt(0)==' ') c = c.substring(1,c.length);
                    if (c.indexOf(nameEQ) == 0) return c.substring(nameEQ.length,c.length);
                }
                return null;
            }
        });
        </script>
        <?php
    }
    
    /**
     * Render single review item - UPDATED with like button
     */
    private function render_review_item($review, $settings = []) {
        static $review_counter = 0;
        $review_counter++;
        $review_id = $this->get_id() . '-review-' . $review_counter;
        
        // Generate unique review ID based on author name and time
        $unique_review_id = md5($review['author_name'] . $review['time']);
        
        // Get likes for this review
        $likes = $this->get_review_likes($unique_review_id);
        
        // Check if user has liked this review
        $user_liked = $this->user_has_liked($unique_review_id);
        
        // Check if text is long enough to need truncation
        $text_length = strlen($review['text'] ?? '');
        $needs_truncation = $text_length > 200;
        
        // Get platform
        $platform = $review['platform'] ?? 'google';
        
        ?>
        <div class="sky-review-item" data-review-id="<?php echo esc_attr($unique_review_id); ?>">
            <div class="sky-review-content">
                <div class="sky-review-header">
                    <div class="sky-review-author">
                        <?php if (!empty($review['author_photo'])) : ?>
                            <img src="<?php echo esc_url($review['author_photo']); ?>" 
                                 alt="<?php echo esc_attr($review['author_name']); ?>" 
                                 class="sky-author-photo"
                                 loading="lazy">
                        <?php else : ?>
                            <div class="sky-author-photo-placeholder">
                                <?php echo esc_html(strtoupper(substr($review['author_name'], 0, 1))); ?>
                            </div>
                        <?php endif; ?>
                        <div class="sky-author-info">
                            <span class="sky-review-author-name"><?php echo esc_html($review['author_name']); ?></span>
                            <span class="sky-review-time"><?php echo esc_html($review['time']); ?></span>
                        </div>
                    </div>
                    <?php echo $this->get_platform_icon($platform); ?>
                </div>
                <div class="sky-review-rating">
                    <?php echo str_repeat('★', $review['rating']); ?>
                    <?php if ($platform === 'google') : // Show verified badge for all Google reviews ?>
                    <span class="sky-verified-review" title="<?php esc_attr_e('Verified Google Review', 'sky-seo-boost'); ?>">
                        <svg width="14" height="14" viewBox="0 0 14 14" fill="none" xmlns="http://www.w3.org/2000/svg">
                            <path d="M7 0C3.13 0 0 3.13 0 7C0 10.87 3.13 14 7 14C10.87 14 14 10.87 14 7C14 3.13 10.87 0 7 0ZM5.6 10.5L2.1 7L3.01 6.09L5.6 8.67L10.79 3.48L11.7 4.4L5.6 10.5Z" fill="#1976D2"/>
                        </svg>
                    </span>
                    <?php endif; ?>
                </div>
                <?php if (!empty($review['text'])) : ?>
                    <div class="sky-review-text <?php echo $needs_truncation ? 'sky-truncated' : ''; ?>" 
                         id="<?php echo esc_attr($review_id); ?>-text"
                         data-full-text="<?php echo esc_attr($review['text']); ?>"
                         data-full-length="<?php echo esc_attr($text_length); ?>">
                        <?php echo esc_html($review['text']); ?>
                    </div>
                <?php else : ?>
                    <div class="sky-review-text"><?php esc_html_e('No review text provided', 'sky-seo-boost'); ?></div>
                <?php endif; ?>
            </div>
            <div class="sky-review-footer">
                <button class="sky-read-more-toggle" 
                        type="button"
                        data-review-id="<?php echo esc_attr($review_id); ?>"
                        data-expanded="false"
                        style="<?php echo !$needs_truncation || empty($review['text']) ? 'display: none;' : ''; ?>">
                    <?php esc_html_e('READ MORE', 'sky-seo-boost'); ?>
                </button>
                <?php if ($settings['sky_show_likes'] === 'yes') : ?>
                <button class="sky-review-like-button <?php echo $user_liked ? 'liked' : ''; ?>" 
                        type="button"
                        data-review-id="<?php echo esc_attr($unique_review_id); ?>">
                    <span class="sky-like-icon">❤️</span>
                    <span class="sky-like-count"><?php echo esc_html($likes); ?></span>
                </button>
                <?php endif; ?>
            </div>
        </div>
        <?php
    }
}

// Do NOT add any hooks or registration here!
// Registration happens through the loader file only