<?php
// Prevent direct access
if (!defined('ABSPATH')) {
   exit;
}

// Hook to modify the update-core.php page
add_action('admin_head-update-core.php', 'sky_seo_add_update_icon_styles');
add_filter('plugin_row_meta', 'sky_seo_add_update_icon', 10, 4);

// Add CSS for the update icon
function sky_seo_add_update_icon_styles() {
    ?>
    <style>
        .sky-update-icon {
            display: inline-block;
            width: 16px;
            height: 16px;
            margin-left: 5px;
            vertical-align: middle;
            animation: pulse 2s infinite;
        }
        
        @keyframes pulse {
            0%, 100% { opacity: 1; }
            50% { opacity: 0.6; }
        }
    </style>
    <?php
}

// Add icon to plugins that have updates available
function sky_seo_add_update_icon($plugin_meta, $plugin_file, $plugin_data, $status) {
    // Only add on update-core.php page
    if (!isset($GLOBALS['pagenow']) || $GLOBALS['pagenow'] !== 'update-core.php') {
        return $plugin_meta;
    }
    
    // Check if this plugin has an update
    $update_plugins = get_site_transient('update_plugins');
    if (isset($update_plugins->response[$plugin_file])) {
        // Add the icon to the plugin name
        add_filter('gettext', function($translated, $text, $domain) use ($plugin_data) {
            if ($text === $plugin_data['Name']) {
                $icon_url = SKY_SEO_BOOST_PLUGIN_URL . 'assets/img/icon.svg';
                return $translated . ' <img src="' . esc_url($icon_url) . '" class="sky-update-icon" alt="Update available">';
            }
            return $translated;
        }, 10, 3);
    }
    
    return $plugin_meta;
}

// Ensure Edit link is visible in admin bar
add_action('admin_bar_menu', 'sky_seo_ensure_edit_link_visible', 999);
function sky_seo_ensure_edit_link_visible($wp_admin_bar) {
    if (!is_admin() && is_singular()) {
        $post_id = get_queried_object_id();
        
        // Check if the default edit link exists
        $edit_node = $wp_admin_bar->get_node('edit');
        
        // If it doesn't exist, add it
        if (!$edit_node && current_user_can('edit_post', $post_id)) {
            $wp_admin_bar->add_node(array(
                'id'    => 'edit',
                'title' => __('Edit', 'sky360'),
                'href'  => get_edit_post_link($post_id)
            ));
        }
    }
}

// Enqueue Admin Scripts and Styles
function sky_seo_enqueue_admin_scripts($hook) {
   $post_types = ['sky_areas', 'sky_trending', 'sky_sectors'];
   $plugin_url = plugins_url('', dirname(__FILE__));
   $version = '1.0.27';
   
   // Get current post type
   $current_post_type = '';
   if (isset($_GET['post_type'])) {
       $current_post_type = $_GET['post_type'];
   } elseif (isset($_GET['post'])) {
       $current_post_type = get_post_type($_GET['post']);
   } elseif (isset($GLOBALS['typenow'])) {
       $current_post_type = $GLOBALS['typenow'];
   } elseif (isset($GLOBALS['post']) && is_object($GLOBALS['post'])) {
       $current_post_type = get_post_type($GLOBALS['post']->ID);
   }
   
   // Check if we're on any Sky SEO Boost admin page
   $is_sky_seo_page = strpos($hook, 'sky-seo') !== false || 
                      (isset($_GET['page']) && strpos($_GET['page'], 'sky-seo') !== false);
   
   // Check if we're on edit.php for our post types
   $is_edit_page = $hook === 'edit.php' && in_array($current_post_type, $post_types);

   // Always load base admin styles and scripts on our pages
   if (in_array($hook, ['edit.php', 'post.php', 'post-new.php']) && in_array($current_post_type, $post_types) 
       || $is_sky_seo_page 
       || $is_edit_page
       || $hook === 'index.php') {
       
       // Load base admin CSS
       wp_enqueue_style('sky-seo-admin', $plugin_url . '/assets/css/admin.css', [], $version);
       
       // Load base admin JS with Chart.js
       wp_enqueue_script('chart-js', 'https://cdn.jsdelivr.net/npm/chart.js', [], '3.9.1', true);
       wp_enqueue_script('sky-seo-admin', $plugin_url . '/assets/js/admin.js', ['jquery', 'chart-js'], $version, true);
   }
   
   // Load All Content page specific styles - FORCE LOAD on edit.php for our post types
   if (($is_sky_seo_page && isset($_GET['page']) && $_GET['page'] === 'sky-seo-all-content')
       || ($hook === 'edit.php' && in_array($current_post_type, $post_types))) {
       wp_enqueue_style('sky-seo-all-content', $plugin_url . '/assets/css/all-content.css', ['sky-seo-admin'], $version);

       // Add inline CSS to ensure our styles take precedence
       wp_add_inline_style('sky-seo-all-content', '
           /* Force our layout on edit.php pages */
           body.post-type-sky_areas .wrap > *:not(.sky-seo-post-list-wrap),
           body.post-type-sky_trending .wrap > *:not(.sky-seo-post-list-wrap),
           body.post-type-sky_sectors .wrap > *:not(.sky-seo-post-list-wrap) {
               display: none !important;
           }
       ');
   }
   
   // Load Analytics Dashboard assets on main plugin page (sky-seo-boost)
   if ($is_sky_seo_page && isset($_GET['page']) && $_GET['page'] === 'sky-seo-boost') {
       wp_enqueue_style('sky-seo-analytics-dashboard', $plugin_url . '/assets/css/analytics-dashboard.css', ['sky-seo-admin'], $version);
       wp_enqueue_script('sky-seo-analytics-dashboard', $plugin_url . '/assets/js/analytics-dashboard.js', ['jquery', 'chart-js', 'sky-seo-admin'], $version, true);
       
       // Localize script for AJAX
       wp_localize_script('sky-seo-analytics-dashboard', 'skySeoAjax', [
           'ajaxurl' => admin_url('admin-ajax.php'),
           'nonce' => wp_create_nonce('sky_seo_analytics_nonce')
       ]);
   }
   
   // Load general settings styles and scripts
   if ($is_sky_seo_page && isset($_GET['page']) && $_GET['page'] === 'sky-seo-settings') {
       // Always load general settings CSS on settings page
       wp_enqueue_style('sky-seo-general-settings', $plugin_url . '/assets/css/general-settings.css', ['sky-seo-admin'], $version);
       wp_enqueue_script('sky-seo-general-settings', $plugin_url . '/assets/js/general-settings.js', ['jquery', 'sky-seo-admin'], $version, true);
       
       // Localize script for AJAX
       wp_localize_script('sky-seo-general-settings', 'skySeoSettings', [
           'ajaxurl' => admin_url('admin-ajax.php'),
           'nonce' => wp_create_nonce('sky_seo_settings_nonce') // FIXED: Changed to match AJAX handlers
       ]);
       
       // Load tab-specific styles
       $tab = isset($_GET['tab']) ? sanitize_text_field($_GET['tab']) : 'general';

       // Load tracking config CSS for tracking and analytics tabs
       if (in_array($tab, ['tracking', 'analytics-tab'], true)) {
           wp_enqueue_style('sky-seo-tracking-config', $plugin_url . '/assets/css/tracking-config.css', ['sky-seo-admin'], $version);

           // Localize analytics dashboard script for AJAX if on analytics tab
           if ($tab === 'analytics-tab') {
               wp_localize_script('sky-seo-analytics-dashboard', 'skySeoAjax', [
                   'ajaxurl' => admin_url('admin-ajax.php'),
                   'nonce' => wp_create_nonce('sky_seo_analytics_nonce')
               ]);
           }
       }
       // Note: sky-seo-general-settings CSS is already loaded above for all settings tabs
   }
}

// Register Settings
function sky_seo_register_settings() {
   register_setting('sky_seo_settings', 'sky_seo_settings', [
       'sanitize_callback' => 'sky_seo_sanitize_settings',
   ]);
}

// Add Internal Linking Metabox
function sky_seo_add_internal_linking_metabox() {
   $post_types = ['sky_areas', 'sky_trending', 'sky_sectors'];
   add_meta_box(
       'sky_seo_internal_linking',
       __('Internal Linking Suggestions', 'sky360'),
       'sky_seo_render_internal_linking_metabox',
       $post_types,
       'side',
       'high'
   );
}

// Render Internal Linking Metabox
function sky_seo_render_internal_linking_metabox($post) {
   $title = get_the_title($post->ID);
   $keywords = explode(' ', strtolower($title));
   $suggestions = [];

   $query = new WP_Query([
       'post_type' => ['sky_areas', 'sky_trending', 'sky_sectors'],
       'posts_per_page' => 5,
       'post__not_in' => [$post->ID],
       'post_status' => 'publish',
       's' => implode(' ', $keywords),
   ]);

   if ($query->have_posts()) {
       while ($query->have_posts()) {
           $query->the_post();
           $suggestions[] = [
               'title' => get_the_title(),
               'permalink' => get_permalink(),
           ];
       }
       wp_reset_postdata();
   }

   if (empty($suggestions)) {
       echo '<p>' . esc_html__('No related posts found. Try linking to other relevant content.', 'sky360') . '</p>';
   } else {
       echo '<p>' . esc_html__('Suggested posts to link to:', 'sky360') . '</p>';
       echo '<ul>';
       foreach ($suggestions as $suggestion) {
           echo '<li><a href="' . esc_url($suggestion['permalink']) . '" class="sky-seo-link-suggestion" data-url="' . esc_url($suggestion['permalink']) . '">' . esc_html($suggestion['title']) . '</a></li>';
       }
       echo '</ul>';
       echo '<p class="description">' . esc_html__('Click a link to copy its URL.', 'sky360') . '</p>';
   }
}

// Add Dashboard Widget
function sky_seo_add_dashboard_widget() {
   wp_add_dashboard_widget(
       'sky_seo_dashboard_widget',
       __('Sky SEO Boost: Top Pages', 'sky360'),
       'sky_seo_render_dashboard_widget'
   );
}

// Add Total Clicks column to WordPress default post types
add_filter('manage_posts_columns', 'sky_seo_add_clicks_column');
add_filter('manage_pages_columns', 'sky_seo_add_clicks_column');
add_filter('manage_product_posts_columns', 'sky_seo_add_clicks_column');

function sky_seo_add_clicks_column($columns) {
    // Add after title column
    $new_columns = array();
    foreach ($columns as $key => $value) {
        $new_columns[$key] = $value;
        if ($key === 'title') {
            $new_columns['sky_seo_clicks'] = '<span class="dashicons dashicons-chart-bar" title="' . __('Total Clicks', 'sky360') . '"></span> ' . __('Clicks', 'sky360');
        }
    }
    return $new_columns;
}

// Display click data in the column
add_action('manage_posts_custom_column', 'sky_seo_display_clicks_column', 10, 2);
add_action('manage_pages_custom_column', 'sky_seo_display_clicks_column', 10, 2);

function sky_seo_display_clicks_column($column, $post_id) {
    if ($column === 'sky_seo_clicks') {
        global $wpdb;
        $clicks_table = $wpdb->prefix . 'sky_seo_clicks';
        
        // Get total clicks
        $total_clicks = $wpdb->get_var($wpdb->prepare(
            "SELECT SUM(clicks) FROM $clicks_table WHERE post_id = %d",
            $post_id
        ));
        
        $total_clicks = $total_clicks ?: 0;
        
        // Get trend data
        $yesterday_clicks = $wpdb->get_var($wpdb->prepare(
            "SELECT SUM(clicks) FROM $clicks_table WHERE post_id = %d AND DATE(click_time) = DATE(NOW() - INTERVAL 1 DAY)",
            $post_id
        ));
        $today_clicks = $wpdb->get_var($wpdb->prepare(
            "SELECT SUM(clicks) FROM $clicks_table WHERE post_id = %d AND DATE(click_time) = CURDATE()",
            $post_id
        ));
        
        echo '<div style="text-align: center;">';
        echo '<strong>' . number_format_i18n($total_clicks) . '</strong>';
        
        if ($total_clicks > 0 && ($today_clicks || $yesterday_clicks)) {
            echo '<div style="font-size: 12px; margin-top: 2px;">';
            if ($today_clicks > $yesterday_clicks) {
                echo '<span style="color: #15803d;" title="' . __('Trending up', 'sky360') . '">↑</span>';
            } elseif ($today_clicks < $yesterday_clicks && $yesterday_clicks > 0) {
                echo '<span style="color: #dc2626;" title="' . __('Trending down', 'sky360') . '">↓</span>';
            }
            echo '</div>';
        }
        echo '</div>';
    }
}

// Make the column sortable
add_filter('manage_edit-post_sortable_columns', 'sky_seo_make_clicks_sortable');
add_filter('manage_edit-page_sortable_columns', 'sky_seo_make_clicks_sortable');
add_filter('manage_edit-product_sortable_columns', 'sky_seo_make_clicks_sortable');

function sky_seo_make_clicks_sortable($columns) {
    $columns['sky_seo_clicks'] = 'sky_seo_clicks';
    return $columns;
}

// Handle sorting
add_action('pre_get_posts', 'sky_seo_orderby_clicks');

// Global variables for click sorting filters
$sky_seo_clicks_join_added = false;
$sky_seo_clicks_orderby_added = false;

function sky_seo_orderby_clicks($query) {
    global $sky_seo_clicks_join_added, $sky_seo_clicks_orderby_added;

    if (!is_admin() || !$query->is_main_query()) {
        return;
    }

    if ($query->get('orderby') === 'sky_seo_clicks') {
        // Add filters only once
        if (!$sky_seo_clicks_join_added) {
            add_filter('posts_join', 'sky_seo_clicks_join_filter');
            $sky_seo_clicks_join_added = true;
        }
        if (!$sky_seo_clicks_orderby_added) {
            add_filter('posts_orderby', 'sky_seo_clicks_orderby_filter');
            $sky_seo_clicks_orderby_added = true;
        }

        // Store order for the orderby filter
        $GLOBALS['sky_seo_clicks_order'] = $query->get('order') ?: 'DESC';

        // Remove filters after the query is done to prevent affecting other queries
        add_action('the_posts', 'sky_seo_remove_clicks_filters', 10, 2);
    }
}

function sky_seo_clicks_join_filter($join) {
    global $wpdb;
    $clicks_table = $wpdb->prefix . 'sky_seo_clicks';
    $join .= " LEFT JOIN (
        SELECT post_id, SUM(clicks) as total_clicks
        FROM {$clicks_table}
        GROUP BY post_id
    ) as clicks_data ON {$wpdb->posts}.ID = clicks_data.post_id";
    return $join;
}

function sky_seo_clicks_orderby_filter($orderby) {
    $order = isset($GLOBALS['sky_seo_clicks_order']) ? $GLOBALS['sky_seo_clicks_order'] : 'DESC';
    return "COALESCE(clicks_data.total_clicks, 0) {$order}";
}

function sky_seo_remove_clicks_filters($posts, $query) {
    global $sky_seo_clicks_join_added, $sky_seo_clicks_orderby_added;

    // Only remove filters if this is the main admin query
    if (is_admin() && $query->is_main_query()) {
        remove_filter('posts_join', 'sky_seo_clicks_join_filter');
        remove_filter('posts_orderby', 'sky_seo_clicks_orderby_filter');
        remove_action('the_posts', 'sky_seo_remove_clicks_filters', 10);
        $sky_seo_clicks_join_added = false;
        $sky_seo_clicks_orderby_added = false;
        unset($GLOBALS['sky_seo_clicks_order']);
    }
    return $posts;
}

// Add styles for the clicks column
add_action('admin_head', function() {
    global $pagenow, $typenow;
    
    if ($pagenow === 'edit.php' && in_array($typenow, ['post', 'page', 'product'])) {
        ?>
        <style>
            .column-sky_seo_clicks {
                width: 100px;
                text-align: center;
            }
            .column-sky_seo_clicks .dashicons {
                font-size: 16px;
                width: 16px;
                height: 16px;
                vertical-align: text-bottom;
                margin-right: 4px;
                color: #787c82;
            }
        </style>
        <?php
    }
});

// Render Dashboard Widget
function sky_seo_render_dashboard_widget() {
   global $wpdb;
   $clicks_table = $wpdb->prefix . 'sky_seo_clicks';

   $top_pages = $wpdb->get_results(
       "SELECT p.ID, p.post_title, SUM(c.clicks) as total_clicks 
        FROM {$wpdb->posts} p
        JOIN $clicks_table c ON p.ID = c.post_id
        WHERE c.click_time >= DATE_SUB(NOW(), INTERVAL 30 DAY)
        GROUP BY p.ID
        ORDER BY total_clicks DESC
        LIMIT 10"
   );

   if ($top_pages) {
       echo '<ul>';
       foreach ($top_pages as $page) {
           echo '<li>';
           echo '<a href="' . get_permalink($page->ID) . '">' . esc_html($page->post_title) . '</a>';
           echo ' (' . number_format_i18n($page->total_clicks) . ' ' . __('clicks', 'sky360') . ')';
           echo '</li>';
       }
       echo '</ul>';
   } else {
       echo '<p>' . __('No data available yet.', 'sky360') . '</p>';
   }

   echo '<p class="description">' . __('Last 30 days', 'sky360') . '</p>';
}

// Add FAQ Schema Support
add_action('add_meta_boxes', 'sky_seo_add_faq_metabox');

function sky_seo_add_faq_metabox() {
    $post_types = ['sky_areas', 'sky_trending', 'sky_sectors', 'page', 'post'];
    add_meta_box(
        'sky_seo_faq_schema',
        __('FAQ Schema', 'sky360'),
        'sky_seo_render_faq_metabox',
        $post_types,
        'normal',
        'low'
    );
}

// Render FAQ Metabox
function sky_seo_render_faq_metabox($post) {
    wp_nonce_field('sky_seo_faq_nonce', 'sky_seo_faq_nonce');
    $faqs = get_post_meta($post->ID, '_sky_seo_faqs', true) ?: [];
    ?>
    <div id="sky-seo-faq-container">
        <div id="sky-seo-faq-items">
            <?php if (!empty($faqs)) : ?>
                <?php foreach ($faqs as $index => $faq) : ?>
                    <div class="sky-seo-faq-item" style="margin-top:10px;padding-top:10px;border-top:1px solid #ddd;">
                        <input type="text" name="sky_seo_faq_question[]" value="<?php echo esc_attr($faq['question']); ?>" placeholder="Question" style="width:100%;margin-bottom:5px;">
                        <textarea name="sky_seo_faq_answer[]" placeholder="Answer" style="width:100%;height:60px;"><?php echo esc_textarea($faq['answer']); ?></textarea>
                        <button type="button" class="button sky-seo-remove-faq" style="margin-top:5px;">Remove</button>
                    </div>
                <?php endforeach; ?>
            <?php endif; ?>
        </div>
        <button type="button" class="button button-primary" id="sky-seo-add-faq" style="margin-top:10px;">Add FAQ</button>
    </div>
    <script>
    jQuery(document).ready(function($) {
        $('#sky-seo-add-faq').on('click', function() {
            var newFaq = '<div class="sky-seo-faq-item" style="margin-top:10px;padding-top:10px;border-top:1px solid #ddd;">' +
                '<input type="text" name="sky_seo_faq_question[]" placeholder="Question" style="width:100%;margin-bottom:5px;">' +
                '<textarea name="sky_seo_faq_answer[]" placeholder="Answer" style="width:100%;height:60px;"></textarea>' +
                '<button type="button" class="button sky-seo-remove-faq" style="margin-top:5px;">Remove</button>' +
                '</div>';
            $('#sky-seo-faq-items').append(newFaq);
        });
        
        $(document).on('click', '.sky-seo-remove-faq', function() {
            $(this).closest('.sky-seo-faq-item').remove();
        });
    });
    </script>
    <?php
}

// Save FAQ data
function sky_seo_save_faq_data($post_id) {
    if (!isset($_POST['sky_seo_faq_nonce']) || !wp_verify_nonce($_POST['sky_seo_faq_nonce'], 'sky_seo_faq_nonce')) {
        return;
    }
    
    if (defined('DOING_AUTOSAVE') && DOING_AUTOSAVE) {
        return;
    }
    
    if (!current_user_can('edit_post', $post_id)) {
        return;
    }
    
    $faqs = [];
    if (isset($_POST['sky_seo_faq_question']) && isset($_POST['sky_seo_faq_answer'])) {
        $questions = $_POST['sky_seo_faq_question'];
        $answers = $_POST['sky_seo_faq_answer'];
        
        for ($i = 0; $i < count($questions); $i++) {
            if (!empty($questions[$i]) && !empty($answers[$i])) {
                $faqs[] = [
                    'question' => sanitize_text_field($questions[$i]),
                    'answer' => sanitize_textarea_field($answers[$i])
                ];
            }
        }
    }
    
    if (!empty($faqs)) {
        update_post_meta($post_id, '_sky_seo_faqs', $faqs);
    } else {
        delete_post_meta($post_id, '_sky_seo_faqs');
    }
}
add_action('save_post', 'sky_seo_save_faq_data');

/**
 * Include custom post types in main RSS feed for faster discovery
 * NEW FUNCTION - FIX 5
 */
function sky_seo_include_custom_posts_in_feed($query) {
    if ($query->is_feed() && $query->is_main_query()) {
        $post_types = get_query_var('post_type');
        if (empty($post_types)) {
            $query->set('post_type', ['post', 'sky_areas', 'sky_trending', 'sky_sectors']);
        }
    }
    return $query;
}
add_action('pre_get_posts', 'sky_seo_include_custom_posts_in_feed');

/**
 * Add custom post type feeds
 * NEW FUNCTION - FIX 5
 */
function sky_seo_add_custom_feeds() {
    add_feed('sky-areas', 'sky_seo_areas_feed');
    add_feed('sky-trending', 'sky_seo_trending_feed');  
    add_feed('sky-sectors', 'sky_seo_sectors_feed');
}
add_action('init', 'sky_seo_add_custom_feeds');

function sky_seo_areas_feed() {
    load_template(SKY_SEO_BOOST_PLUGIN_DIR . 'templates/feed-areas.php');
}

function sky_seo_trending_feed() {
    load_template(SKY_SEO_BOOST_PLUGIN_DIR . 'templates/feed-trending.php');
}

function sky_seo_sectors_feed() {
    load_template(SKY_SEO_BOOST_PLUGIN_DIR . 'templates/feed-sectors.php');
}