<?php
/**
 * Sky SEO Boost - Elementor Area Links Widget
 * 
 * @package SkySEOBoost
 * @since 1.0.0
 */

// Prevent direct access
if (!defined('ABSPATH')) {
    exit;
}

/**
 * Check if Elementor is active before registering widget
 */
add_action('init', function() {
    if (!did_action('elementor/loaded')) {
        return;
    }
    
    // Register the widget when Elementor is ready
    add_action('elementor/widgets/register', 'sky_seo_register_area_links_widget');
});

/**
 * Register Area Links Widget
 */
function sky_seo_register_area_links_widget($widgets_manager) {
    
    class Sky_SEO_Area_Links_Widget extends \Elementor\Widget_Base {

        /**
         * Constructor - Register WPML strings
         */
        public function __construct($data = [], $args = null) {
            parent::__construct($data, $args);

            // WPML Phase 2: Register widget strings for translation
            $this->register_wpml_strings();
        }

        /**
         * WPML Phase 2: Register all widget strings with WPML
         */
        private function register_wpml_strings() {
            // Only register if WPML String Translation is active
            if (!function_exists('icl_register_string')) {
                return;
            }

            // Register widget category name
            icl_register_string('sky360', 'Widget Category: Sky SEO Boost', 'Sky SEO Boost');

            // Register widget title
            icl_register_string('sky360', 'Widget: Sky SEO Location Links', 'Sky SEO Location Links');

            // Register all control labels
            icl_register_string('sky360', 'Widget Label: Content', 'Content');
            icl_register_string('sky360', 'Widget Label: Display Mode', 'Display Mode');
            icl_register_string('sky360', 'Widget Option: Show All Pages in Category', 'Show All Pages in Category');
            icl_register_string('sky360', 'Widget Option: Show Children of Specific Page', 'Show Children of Specific Page');
            icl_register_string('sky360', 'Widget Label: Select Main Category', 'Select Main Category');
            icl_register_string('sky360', 'Widget Label: Select Parent Page', 'Select Parent Page');
            icl_register_string('sky360', 'Widget Label: Layout Style', 'Layout Style');
            icl_register_string('sky360', 'Widget Option: Style 1 - Cards', 'Style 1 - Cards');
            icl_register_string('sky360', 'Widget Option: Style 2 - List', 'Style 2 - List');
            icl_register_string('sky360', 'Widget Label: Columns', 'Columns');
            icl_register_string('sky360', 'Widget Label: Order By', 'Order By');
            icl_register_string('sky360', 'Widget Option: Menu Order', 'Menu Order');
            icl_register_string('sky360', 'Widget Option: Title', 'Title');
            icl_register_string('sky360', 'Widget Option: Date', 'Date');
            icl_register_string('sky360', 'Widget Label: Order', 'Order');
            icl_register_string('sky360', 'Widget Option: Ascending', 'Ascending');
            icl_register_string('sky360', 'Widget Option: Descending', 'Descending');
            icl_register_string('sky360', 'Widget Label: View More Text', 'View More Text');
            icl_register_string('sky360', 'Widget Default: View more', 'View more');

            // Register dropdown options
            icl_register_string('sky360', 'Widget Dropdown: Select', '— Select —');
        }

        /**
         * WPML Phase 2: Get translated string from WPML
         *
         * @param string $original Original string
         * @param string $name String name/identifier
         * @return string Translated string or original if WPML not active
         */
        private function get_translated_string($original, $name) {
            // If WPML String Translation is active, get translation
            if (function_exists('icl_t')) {
                return icl_t('sky360', $name, $original);
            }

            // Fallback to WordPress i18n
            return __($original, 'sky360');
        }

        /**
         * Get widget name
         */
        public function get_name() {
            return 'sky_seo_area_links';
        }
        
        /**
         * Get widget title
         */
        public function get_title() {
            return __('Sky SEO Location Links', 'sky360');
        }
        
        /**
         * Get widget icon
         */
        public function get_icon() {
            return 'eicon-post-list';
        }
        
        /**
         * Get widget categories
         */
        public function get_categories() {
            return ['general', 'sky360'];
        }
        
        /**
         * Get main category types (Areas, Trending Searches, Sectors)
         */
        private function get_main_categories() {
            $categories = [];
            
            // Check if Sky SEO custom post types exist
            if (post_type_exists('sky_areas')) {
                $categories['sky_areas'] = __('Areas We Cover', 'sky360');
            }
            if (post_type_exists('sky_trending')) {
                $categories['sky_trending'] = __('Trending Searches', 'sky360');
            }
            if (post_type_exists('sky_sectors')) {
                $categories['sky_sectors'] = __('Sectors', 'sky360');
            }
            
            // Also get any top-level regular pages
            $pages = get_pages([
                'parent' => 0,
                'sort_order' => 'ASC',
                'sort_column' => 'post_title',
                'post_status' => 'publish',
                'number' => 50
            ]);
            
            if (!empty($pages)) {
                if (!empty($categories)) {
                    $categories['_separator'] = '—— Regular Pages ——';
                }
                
                foreach ($pages as $page) {
                    $categories['page_' . $page->ID] = $page->post_title;
                }
            }
            
            return $categories;
        }
        
        /**
         * Get all parent items with their hierarchy
         */
        private function get_all_parent_pages() {
            $options = ['' => __('— Select —', 'sky360')];
            
            // Get Sky SEO custom post types
            $post_types = ['sky_areas', 'sky_trending', 'sky_sectors'];
            
            foreach ($post_types as $post_type) {
                if (!post_type_exists($post_type)) {
                    continue;
                }
                
                $post_type_obj = get_post_type_object($post_type);
                $options['_' . $post_type] = '—— ' . $post_type_obj->labels->name . ' ——';
                
                // Get all posts of this type
                $posts = get_posts([
                    'post_type' => $post_type,
                    'posts_per_page' => -1,
                    'orderby' => 'menu_order title',
                    'order' => 'ASC',
                    'post_status' => 'publish',
                    'post_parent' => 0
                ]);
                
                foreach ($posts as $post) {
                    $options[$post_type . '_' . $post->ID] = $post->post_title;
                    
                    // Get children
                    $children = get_posts([
                        'post_type' => $post_type,
                        'posts_per_page' => -1,
                        'orderby' => 'menu_order title',
                        'order' => 'ASC',
                        'post_status' => 'publish',
                        'post_parent' => $post->ID
                    ]);
                    
                    foreach ($children as $child) {
                        $options[$post_type . '_' . $child->ID] = '— ' . $child->post_title;
                    }
                }
            }
            
            // Also include regular pages
            $pages = get_pages([
                'parent' => 0,
                'sort_order' => 'ASC',
                'sort_column' => 'post_title',
                'post_status' => 'publish'
            ]);
            
            if (!empty($pages)) {
                $options['_pages'] = '—— Regular Pages ——';
                foreach ($pages as $page) {
                    $options['page_' . $page->ID] = $page->post_title;
                    
                    // Get child pages
                    $children = get_pages([
                        'parent' => $page->ID,
                        'sort_order' => 'ASC',
                        'sort_column' => 'post_title',
                        'post_status' => 'publish'
                    ]);
                    
                    foreach ($children as $child) {
                        $options['page_' . $child->ID] = '— ' . $child->post_title;
                    }
                }
            }
            
            return $options;
        }
        
        /**
         * Get click count for a page
         */
        private function get_page_clicks($post_id) {
            global $wpdb;
            $table_name = $wpdb->prefix . 'sky_seo_clicks';
            
            if ($wpdb->get_var("SHOW TABLES LIKE '$table_name'") != $table_name) {
                return 0;
            }
            
            $total_clicks = $wpdb->get_var($wpdb->prepare(
                "SELECT SUM(clicks) FROM $table_name WHERE post_id = %d",
                $post_id
            ));
            
            return $total_clicks ? intval($total_clicks) : 0;
        }
        
        /**
         * Get initials from title
         */
        private function get_title_initials($title) {
            $words = explode(' ', $title);
            $initials = '';
            foreach ($words as $word) {
                if (!empty($word)) {
                    $initials .= strtoupper(substr($word, 0, 1));
                }
            }
            return substr($initials, 0, 2); // Max 2 letters
        }
        
        /**
         * Register widget controls
         */
        protected function register_controls() {
            
            // Content Section
            $this->start_controls_section(
                'content_section',
                [
                    'label' => __('Content', 'sky360'),
                    'tab' => \Elementor\Controls_Manager::TAB_CONTENT,
                ]
            );
            
            $this->add_control(
                'display_mode',
                [
                    'label' => __('Display Mode', 'sky360'),
                    'type' => \Elementor\Controls_Manager::SELECT,
                    'default' => 'category',
                    'options' => [
                        'category' => __('Show All Pages in Category', 'sky360'),
                        'specific' => __('Show Children of Specific Page', 'sky360'),
                    ],
                ]
            );
            
            $this->add_control(
                'main_category',
                [
                    'label' => __('Select Main Category', 'sky360'),
                    'type' => \Elementor\Controls_Manager::SELECT2,
                    'options' => $this->get_main_categories(),
                    'default' => '',
                    'label_block' => true,
                    'condition' => [
                        'display_mode' => 'category',
                    ],
                ]
            );
            
            $this->add_control(
                'parent_page',
                [
                    'label' => __('Select Parent Page', 'sky360'),
                    'type' => \Elementor\Controls_Manager::SELECT2,
                    'options' => $this->get_all_parent_pages(),
                    'default' => '',
                    'label_block' => true,
                    'condition' => [
                        'display_mode' => 'specific',
                    ],
                ]
            );
            
            $this->add_control(
                'layout_style',
                [
                    'label' => __('Layout Style', 'sky360'),
                    'type' => \Elementor\Controls_Manager::SELECT,
                    'default' => 'style1',
                    'options' => [
                        'style1' => __('Style 1 - Cards', 'sky360'),
                        'style2' => __('Style 2 - List', 'sky360'),
                    ],
                ]
            );
            
            $this->add_control(
                'columns',
                [
                    'label' => __('Columns', 'sky360'),
                    'type' => \Elementor\Controls_Manager::SELECT,
                    'default' => '3',
                    'options' => [
                        '1' => '1',
                        '2' => '2',
                        '3' => '3',
                        '4' => '4',
                    ],
                ]
            );
            
            $this->add_control(
                'order_by',
                [
                    'label' => __('Order By', 'sky360'),
                    'type' => \Elementor\Controls_Manager::SELECT,
                    'default' => 'title',
                    'options' => [
                        'menu_order' => __('Menu Order', 'sky360'),
                        'title' => __('Title', 'sky360'),
                        'date' => __('Date', 'sky360'),
                    ],
                ]
            );
            
            $this->add_control(
                'order',
                [
                    'label' => __('Order', 'sky360'),
                    'type' => \Elementor\Controls_Manager::SELECT,
                    'default' => 'ASC',
                    'options' => [
                        'ASC' => __('Ascending', 'sky360'),
                        'DESC' => __('Descending', 'sky360'),
                    ],
                ]
            );
            
            $this->add_control(
                'view_more_text',
                [
                    'label' => __('View More Text', 'sky360'),
                    'type' => \Elementor\Controls_Manager::TEXT,
                    'default' => __('View more', 'sky360'),
                    'condition' => [
                        'layout_style' => 'style1',
                    ],
                ]
            );
            
            $this->end_controls_section();
            
            // Style Section - General
            $this->start_controls_section(
                'section_general_style',
                [
                    'label' => __('General', 'sky360'),
                    'tab' => \Elementor\Controls_Manager::TAB_STYLE,
                ]
            );
            
            $this->add_responsive_control(
                'item_spacing',
                [
                    'label' => __('Item Spacing', 'sky360'),
                    'type' => \Elementor\Controls_Manager::SLIDER,
                    'size_units' => ['px'],
                    'range' => [
                        'px' => [
                            'min' => 0,
                            'max' => 50,
                        ],
                    ],
                    'default' => [
                        'unit' => 'px',
                        'size' => 20,
                    ],
                    'selectors' => [
                        '{{WRAPPER}} .sky-seo-area-links-grid' => 'gap: {{SIZE}}{{UNIT}};',
                    ],
                ]
            );
            
            $this->end_controls_section();
            
            // Style Section - Cards
            $this->start_controls_section(
                'section_card_style',
                [
                    'label' => __('Card Style', 'sky360'),
                    'tab' => \Elementor\Controls_Manager::TAB_STYLE,
                    'condition' => [
                        'layout_style' => 'style1',
                    ],
                ]
            );
            
            // Card Container
            $this->add_control(
                'card_heading',
                [
                    'label' => __('Card Container', 'sky360'),
                    'type' => \Elementor\Controls_Manager::HEADING,
                ]
            );
            
            $this->add_control(
                'card_background',
                [
                    'label' => __('Background Color', 'sky360'),
                    'type' => \Elementor\Controls_Manager::COLOR,
                    'default' => '#F5F5F5',
                    'selectors' => [
                        '{{WRAPPER}} .sky-seo-card-item' => 'background-color: {{VALUE}}',
                    ],
                ]
            );
            
            $this->add_control(
                'card_hover_background',
                [
                    'label' => __('Hover Background Color', 'sky360'),
                    'type' => \Elementor\Controls_Manager::COLOR,
                    'selectors' => [
                        '{{WRAPPER}} .sky-seo-card-item:hover' => 'background-color: {{VALUE}}',
                    ],
                ]
            );
            
            $this->add_responsive_control(
                'card_padding',
                [
                    'label' => __('Padding', 'sky360'),
                    'type' => \Elementor\Controls_Manager::DIMENSIONS,
                    'size_units' => ['px', 'em', '%'],
                    'default' => [
                        'top' => '25',
                        'right' => '30',
                        'bottom' => '25',
                        'left' => '30',
                        'unit' => 'px',
                    ],
                    'selectors' => [
                        '{{WRAPPER}} .sky-seo-card-item' => 'padding: {{TOP}}{{UNIT}} {{RIGHT}}{{UNIT}} {{BOTTOM}}{{UNIT}} {{LEFT}}{{UNIT}};',
                    ],
                ]
            );
            
            $this->add_group_control(
                \Elementor\Group_Control_Border::get_type(),
                [
                    'name' => 'card_border',
                    'selector' => '{{WRAPPER}} .sky-seo-card-item',
                ]
            );
            
            $this->add_control(
                'card_border_radius',
                [
                    'label' => __('Border Radius', 'sky360'),
                    'type' => \Elementor\Controls_Manager::DIMENSIONS,
                    'size_units' => ['px', '%'],
                    'default' => [
                        'top' => '8',
                        'right' => '8',
                        'bottom' => '8',
                        'left' => '8',
                        'unit' => 'px',
                    ],
                    'selectors' => [
                        '{{WRAPPER}} .sky-seo-card-item' => 'border-radius: {{TOP}}{{UNIT}} {{RIGHT}}{{UNIT}} {{BOTTOM}}{{UNIT}} {{LEFT}}{{UNIT}};',
                    ],
                ]
            );
            
            $this->add_group_control(
                \Elementor\Group_Control_Box_Shadow::get_type(),
                [
                    'name' => 'card_box_shadow',
                    'selector' => '{{WRAPPER}} .sky-seo-card-item',
                ]
            );
            
            $this->add_group_control(
                \Elementor\Group_Control_Box_Shadow::get_type(),
                [
                    'name' => 'card_hover_box_shadow',
                    'label' => __('Hover Box Shadow', 'sky360'),
                    'selector' => '{{WRAPPER}} .sky-seo-card-item:hover',
                ]
            );
            
            // Initials Box
            $this->add_control(
                'initials_heading',
                [
                    'label' => __('Initials Box', 'sky360'),
                    'type' => \Elementor\Controls_Manager::HEADING,
                    'separator' => 'before',
                ]
            );
            
            $this->add_control(
                'initials_background',
                [
                    'label' => __('Background Color', 'sky360'),
                    'type' => \Elementor\Controls_Manager::COLOR,
                    'default' => '#DDDDDD',
                    'selectors' => [
                        '{{WRAPPER}} .sky-seo-card-number' => 'background-color: {{VALUE}}',
                    ],
                ]
            );
            
            $this->add_control(
                'initials_color',
                [
                    'label' => __('Text Color', 'sky360'),
                    'type' => \Elementor\Controls_Manager::COLOR,
                    'default' => '#666666',
                    'selectors' => [
                        '{{WRAPPER}} .sky-seo-card-number' => 'color: {{VALUE}}',
                    ],
                ]
            );
            
            $this->add_responsive_control(
                'initials_size',
                [
                    'label' => __('Box Size', 'sky360'),
                    'type' => \Elementor\Controls_Manager::SLIDER,
                    'size_units' => ['px'],
                    'range' => [
                        'px' => [
                            'min' => 40,
                            'max' => 100,
                        ],
                    ],
                    'default' => [
                        'unit' => 'px',
                        'size' => 60,
                    ],
                    'selectors' => [
                        '{{WRAPPER}} .sky-seo-card-number' => 'width: {{SIZE}}{{UNIT}}; height: {{SIZE}}{{UNIT}};',
                    ],
                ]
            );
            
            $this->add_group_control(
                \Elementor\Group_Control_Typography::get_type(),
                [
                    'name' => 'initials_typography',
                    'selector' => '{{WRAPPER}} .sky-seo-card-number',
                ]
            );
            
            $this->add_group_control(
                \Elementor\Group_Control_Border::get_type(),
                [
                    'name' => 'initials_border',
                    'selector' => '{{WRAPPER}} .sky-seo-card-number',
                ]
            );
            
            $this->add_control(
                'initials_border_radius',
                [
                    'label' => __('Border Radius', 'sky360'),
                    'type' => \Elementor\Controls_Manager::DIMENSIONS,
                    'size_units' => ['px', '%'],
                    'default' => [
                        'top' => '4',
                        'right' => '4',
                        'bottom' => '4',
                        'left' => '4',
                        'unit' => 'px',
                    ],
                    'selectors' => [
                        '{{WRAPPER}} .sky-seo-card-number' => 'border-radius: {{TOP}}{{UNIT}} {{RIGHT}}{{UNIT}} {{BOTTOM}}{{UNIT}} {{LEFT}}{{UNIT}};',
                    ],
                ]
            );
            
            $this->end_controls_section();
            
            // Style Section - Typography
            $this->start_controls_section(
                'section_typography_style',
                [
                    'label' => __('Typography & Colors', 'sky360'),
                    'tab' => \Elementor\Controls_Manager::TAB_STYLE,
                ]
            );
            
            // Title Typography
            $this->add_control(
                'title_heading',
                [
                    'label' => __('Title', 'sky360'),
                    'type' => \Elementor\Controls_Manager::HEADING,
                    'condition' => [
                        'layout_style' => 'style1',
                    ],
                ]
            );
            
            $this->add_group_control(
                \Elementor\Group_Control_Typography::get_type(),
                [
                    'name' => 'title_typography',
                    'selector' => '{{WRAPPER}} .sky-seo-card-title a',
                    'condition' => [
                        'layout_style' => 'style1',
                    ],
                ]
            );
            
            $this->start_controls_tabs('title_color_tabs', [
                'condition' => [
                    'layout_style' => 'style1',
                ],
            ]);
            
            $this->start_controls_tab(
                'title_color_normal',
                ['label' => __('Normal', 'sky360')]
            );
            
            $this->add_control(
                'title_color',
                [
                    'label' => __('Color', 'sky360'),
                    'type' => \Elementor\Controls_Manager::COLOR,
                    'default' => '#D4A574',
                    'selectors' => [
                        '{{WRAPPER}} .sky-seo-card-title a' => 'color: {{VALUE}}',
                    ],
                ]
            );
            
            $this->end_controls_tab();
            
            $this->start_controls_tab(
                'title_color_hover',
                ['label' => __('Hover', 'sky360')]
            );
            
            $this->add_control(
                'title_hover_color',
                [
                    'label' => __('Color', 'sky360'),
                    'type' => \Elementor\Controls_Manager::COLOR,
                    'default' => '#B8935F',
                    'selectors' => [
                        '{{WRAPPER}} .sky-seo-card-title a:hover' => 'color: {{VALUE}}',
                    ],
                ]
            );
            
            $this->end_controls_tab();
            $this->end_controls_tabs();
            
            // View More Link
            $this->add_control(
                'view_more_heading',
                [
                    'label' => __('View More Link', 'sky360'),
                    'type' => \Elementor\Controls_Manager::HEADING,
                    'separator' => 'before',
                    'condition' => [
                        'layout_style' => 'style1',
                    ],
                ]
            );
            
            $this->add_group_control(
                \Elementor\Group_Control_Typography::get_type(),
                [
                    'name' => 'view_more_typography',
                    'selector' => '{{WRAPPER}} .sky-seo-view-more',
                    'condition' => [
                        'layout_style' => 'style1',
                    ],
                ]
            );
            
            $this->start_controls_tabs('view_more_color_tabs', [
                'condition' => [
                    'layout_style' => 'style1',
                ],
            ]);
            
            $this->start_controls_tab(
                'view_more_color_normal',
                ['label' => __('Normal', 'sky360')]
            );
            
            $this->add_control(
                'view_more_color',
                [
                    'label' => __('Color', 'sky360'),
                    'type' => \Elementor\Controls_Manager::COLOR,
                    'default' => '#999999',
                    'selectors' => [
                        '{{WRAPPER}} .sky-seo-view-more' => 'color: {{VALUE}}',
                        '{{WRAPPER}} .sky-seo-view-more svg' => 'stroke: {{VALUE}}',
                    ],
                ]
            );
            
            $this->end_controls_tab();
            
            $this->start_controls_tab(
                'view_more_color_hover',
                ['label' => __('Hover', 'sky360')]
            );
            
            $this->add_control(
                'view_more_hover_color',
                [
                    'label' => __('Color', 'sky360'),
                    'type' => \Elementor\Controls_Manager::COLOR,
                    'default' => '#666666',
                    'selectors' => [
                        '{{WRAPPER}} .sky-seo-view-more:hover' => 'color: {{VALUE}}',
                        '{{WRAPPER}} .sky-seo-view-more:hover svg' => 'stroke: {{VALUE}}',
                    ],
                ]
            );
            
            $this->end_controls_tab();
            $this->end_controls_tabs();
            
            // List Style Typography (Style 2)
            $this->add_control(
                'list_heading',
                [
                    'label' => __('List Links', 'sky360'),
                    'type' => \Elementor\Controls_Manager::HEADING,
                    'separator' => 'before',
                    'condition' => [
                        'layout_style' => 'style2',
                    ],
                ]
            );
            
            $this->add_group_control(
                \Elementor\Group_Control_Typography::get_type(),
                [
                    'name' => 'list_typography',
                    'selector' => '{{WRAPPER}} .sky-seo-list-link',
                    'condition' => [
                        'layout_style' => 'style2',
                    ],
                ]
            );
            
            $this->add_control(
                'list_color',
                [
                    'label' => __('Color', 'sky360'),
                    'type' => \Elementor\Controls_Manager::COLOR,
                    'default' => '#333333',
                    'selectors' => [
                        '{{WRAPPER}} .sky-seo-list-link' => 'color: {{VALUE}}',
                    ],
                    'condition' => [
                        'layout_style' => 'style2',
                    ],
                ]
            );
            
            $this->add_control(
                'list_hover_color',
                [
                    'label' => __('Hover Color', 'sky360'),
                    'type' => \Elementor\Controls_Manager::COLOR,
                    'default' => '#D4A574',
                    'selectors' => [
                        '{{WRAPPER}} .sky-seo-list-link:hover' => 'color: {{VALUE}}',
                    ],
                    'condition' => [
                        'layout_style' => 'style2',
                    ],
                ]
            );
            
            $this->end_controls_section();
        }
        
        /**
         * Render widget output
         */
        protected function render() {
            $settings = $this->get_settings_for_display();
            $display_mode = $settings['display_mode'];
            
            $items = [];
            
            if ($display_mode === 'category') {
                if (empty($settings['main_category'])) {
                    return;
                }
                
                $selected = $settings['main_category'];
                
                if (in_array($selected, ['sky_areas', 'sky_trending', 'sky_sectors'])) {
                    $items = get_posts([
                        'post_type' => $selected,
                        'posts_per_page' => -1,
                        'orderby' => $settings['order_by'],
                        'order' => $settings['order'],
                        'post_status' => 'publish',
                        'suppress_filters' => false // WPML Phase 2: Enable language filtering
                    ]);
                } elseif (strpos($selected, 'page_') === 0) {
                    $page_id = str_replace('page_', '', $selected);
                    $items = get_pages([
                        'child_of' => $page_id,
                        'sort_order' => $settings['order'],
                        'sort_column' => $settings['order_by'],
                        'post_status' => 'publish',
                    ]);
                }
            } else {
                if (empty($settings['parent_page'])) {
                    return;
                }
                
                $selected = $settings['parent_page'];
                
                if (strpos($selected, 'sky_areas_') === 0 || 
                    strpos($selected, 'sky_trending_') === 0 || 
                    strpos($selected, 'sky_sectors_') === 0) {
                    
                    $parts = explode('_', $selected, 3);
                    $post_type = $parts[0] . '_' . $parts[1];
                    $parent_id = intval($parts[2]);
                    
                    $items = get_posts([
                        'post_type' => $post_type,
                        'post_parent' => $parent_id,
                        'posts_per_page' => -1,
                        'orderby' => $settings['order_by'],
                        'order' => $settings['order'],
                        'post_status' => 'publish',
                        'suppress_filters' => false // WPML Phase 2: Enable language filtering
                    ]);
                } elseif (strpos($selected, 'page_') === 0) {
                    $page_id = intval(str_replace('page_', '', $selected));
                    $items = get_pages([
                        'parent' => $page_id,
                        'sort_order' => $settings['order'],
                        'sort_column' => $settings['order_by'],
                        'post_status' => 'publish',
                    ]);
                }
            }
            
            if (empty($items)) {
                return;
            }
            
            $columns = intval($settings['columns']);
            $layout_style = $settings['layout_style'];
            
            ?>
            <div class="sky-seo-area-links-widget sky-seo-layout-<?php echo esc_attr($layout_style); ?>">
                <div class="sky-seo-area-links-grid" style="<?php echo $columns > 1 ? 'display: grid; grid-template-columns: repeat(' . $columns . ', 1fr);' : ''; ?>">
                    <?php 
                    if ($layout_style === 'style1') {
                        // Card Layout
                        foreach ($items as $item) : 
                            $initials = $this->get_title_initials($item->post_title);
                        ?>
                            <div class="sky-seo-card-item">
                                <div class="sky-seo-card-header">
                                    <span class="sky-seo-card-number"><?php echo esc_html($initials); ?></span>
                                    <div class="sky-seo-card-content">
                                        <h3 class="sky-seo-card-title">
                                            <a href="<?php echo get_permalink($item->ID); ?>">
                                                <?php echo esc_html($item->post_title); ?>
                                            </a>
                                        </h3>
                                        <a href="<?php echo get_permalink($item->ID); ?>" class="sky-seo-view-more">
                                            <?php
                                            // WPML Phase 2: Use translated "View more" text
                                            $view_more_text = !empty($settings['view_more_text']) ? $settings['view_more_text'] : 'View more';
                                            echo esc_html($this->get_translated_string($view_more_text, 'Widget Default: View more'));
                                            ?>
                                            <svg width="16" height="16" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2">
                                                <path d="M7 17l10-10M17 7v10m0-10H7"/>
                                            </svg>
                                        </a>
                                    </div>
                                </div>
                            </div>
                        <?php 
                        endforeach;
                    } else {
                        // List Layout (Style 2)
                        foreach ($items as $item) : ?>
                            <div class="sky-seo-area-links-item">
                                <a href="<?php echo get_permalink($item->ID); ?>" class="sky-seo-list-link">
                                    <span class="sky-seo-arrow">▶</span>
                                    <span class="sky-seo-link-text"><?php echo esc_html($item->post_title); ?></span>
                                </a>
                            </div>
                        <?php 
                        endforeach;
                    }
                    ?>
                </div>
            </div>
            <?php
        }
    }
    
    // Register the widget
    $widgets_manager->register(new Sky_SEO_Area_Links_Widget());
}

// Add custom CSS for the widget
add_action('wp_head', function() {
    ?>
    <style>
        /* General */
        .sky-seo-area-links-widget {
            width: 100%;
        }
        
        /* Style 1 - Cards */
        .sky-seo-layout-style1 .sky-seo-card-item {
            transition: all 0.3s ease;
        }
        
        .sky-seo-card-header {
            display: flex;
            align-items: flex-start;
            gap: 15px;
        }
        
        .sky-seo-card-number {
            display: flex;
            align-items: center;
            justify-content: center;
            flex-shrink: 0;
            transition: all 0.3s ease;
        }
        
        .sky-seo-card-content {
            flex: 1;
        }
        
        .sky-seo-card-title {
            margin: 0 0 10px 0;
        }
        
        .sky-seo-card-title a {
            text-decoration: none;
            display: block;
            transition: color 0.3s ease;
        }
        
        .sky-seo-view-more {
            text-decoration: none;
            display: inline-flex;
            align-items: center;
            gap: 5px;
            transition: all 0.3s ease;
        }
        
        .sky-seo-view-more:hover {
            gap: 8px;
        }
        
        .sky-seo-view-more svg {
            width: 16px;
            height: 16px;
            transition: transform 0.3s ease;
        }
        
        .sky-seo-view-more:hover svg {
            transform: translate(2px, -2px);
        }
        
        /* Style 2 - List */
        .sky-seo-layout-style2 .sky-seo-area-links-item {
            padding: 12px 0;
            border-bottom: 1px solid #E5E5E5;
        }
        
        .sky-seo-layout-style2 .sky-seo-area-links-item:last-child {
            border-bottom: none;
        }
        
        .sky-seo-list-link {
            display: flex;
            align-items: center;
            gap: 10px;
            text-decoration: none;
            transition: all 0.3s ease;
        }
        
        .sky-seo-list-link:hover {
            padding-left: 5px;
        }
        
        .sky-seo-arrow {
            font-size: 12px;
            color: #999999;
            transition: all 0.3s ease;
        }
        
        .sky-seo-list-link:hover .sky-seo-arrow {
            transform: translateX(3px);
        }
        
        /* Responsive */
        @media (max-width: 768px) {
            .sky-seo-area-links-grid {
                grid-template-columns: 1fr !important;
            }
        }
    </style>
    <?php
});

// Optional: Create a custom widget category for Sky SEO widgets
add_action('elementor/elements/categories_registered', function($elements_manager) {
    $elements_manager->add_category(
        'sky360',
        [
            'title' => __('Sky SEO Boost', 'sky360'),
            'icon' => 'eicon-code',
        ]
    );
});