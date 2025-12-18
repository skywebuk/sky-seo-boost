<?php
// Prevent direct access
if (!defined('ABSPATH')) {
    exit;
}

// Add custom styles and hide default elements
add_action('admin_head', function() {
    global $typenow, $pagenow;
    
    if ($pagenow === 'edit.php' && in_array($typenow, ['sky_areas', 'sky_trending', 'sky_sectors'])) {
        ?>
        <style>
            /* Hide WordPress default elements */
            .wp-list-table,
            .tablenav,
            .search-box,
            .subsubsub,
            .page-title-action {
                display: none !important;
            }
            
            /* Show our custom content */
            .sky-seo-custom-list {
                display: block !important;
                margin-top: 20px;
            }
            
            /* Hierarchical indentation */
            .sky-seo-content-table .level-0 .row-title { padding-left: 0; }
            .sky-seo-content-table .level-1 .row-title { padding-left: 20px; }
            .sky-seo-content-table .level-2 .row-title { padding-left: 40px; }
            .sky-seo-content-table .level-3 .row-title { padding-left: 60px; }
            .sky-seo-content-table .level-4 .row-title { padding-left: 80px; }
            .sky-seo-content-table .level-5 .row-title { padding-left: 100px; }
            
            /* Add hierarchy indicator */
            .sky-seo-content-table .level-1 .row-title:before,
            .sky-seo-content-table .level-2 .row-title:before,
            .sky-seo-content-table .level-3 .row-title:before,
            .sky-seo-content-table .level-4 .row-title:before,
            .sky-seo-content-table .level-5 .row-title:before {
                content: "— ";
                color: #999;
            }
        </style>
        <?php
    }
});

// Add our custom content after the title
add_action('all_admin_notices', function() {
    global $typenow, $pagenow;
    
    if ($pagenow === 'edit.php' && in_array($typenow, ['sky_areas', 'sky_trending', 'sky_sectors'])) {
        // Handle bulk actions
        if (isset($_POST['action']) && isset($_POST['post']) && is_array($_POST['post']) && check_admin_referer('bulk-posts')) {
            $action = sanitize_text_field($_POST['action']);
            $post_ids = array_map('intval', $_POST['post']);
            
            if (!empty($post_ids)) {
                switch ($action) {
                    case 'publish':
                        foreach ($post_ids as $post_id) {
                            if (current_user_can('publish_posts', $post_id)) {
                                wp_publish_post($post_id);
                            }
                        }
                        echo '<div class="notice notice-success is-dismissible"><p>' . esc_html__('Selected posts published.', 'sky-seo-boost') . '</p></div>';
                        break;
                    case 'draft':
                        foreach ($post_ids as $post_id) {
                            if (current_user_can('edit_post', $post_id)) {
                                wp_update_post(['ID' => $post_id, 'post_status' => 'draft']);
                            }
                        }
                        echo '<div class="notice notice-success is-dismissible"><p>' . esc_html__('Selected posts set to draft.', 'sky-seo-boost') . '</p></div>';
                        break;
                    case 'trash':
                        foreach ($post_ids as $post_id) {
                            if (current_user_can('delete_post', $post_id)) {
                                wp_trash_post($post_id);
                            }
                        }
                        echo '<div class="notice notice-success is-dismissible"><p>' . esc_html__('Selected posts moved to trash.', 'sky-seo-boost') . '</p></div>';
                        break;
                    case 'untrash':
                        foreach ($post_ids as $post_id) {
                            if (current_user_can('delete_post', $post_id)) {
                                wp_untrash_post($post_id);
                            }
                        }
                        echo '<div class="notice notice-success is-dismissible"><p>' . esc_html__('Selected posts restored from trash.', 'sky-seo-boost') . '</p></div>';
                        break;
                    case 'delete':
                        foreach ($post_ids as $post_id) {
                            if (current_user_can('delete_post', $post_id)) {
                                wp_delete_post($post_id, true);
                            }
                        }
                        echo '<div class="notice notice-success is-dismissible"><p>' . esc_html__('Selected posts permanently deleted.', 'sky-seo-boost') . '</p></div>';
                        break;
                    case 'duplicate':
                        // Handle duplicate action
                        $duplicated = 0;
                        foreach ($post_ids as $post_id) {
                            if (current_user_can('edit_post', $post_id)) {
                                // Check if duplicate feature is available
                                if (class_exists('Sky_SEO_Duplicate_Post_Feature')) {
                                    $duplicator = new Sky_SEO_Duplicate_Post_Feature();
                                    $new_post_id = $duplicator->create_duplicate($post_id);
                                    if (!is_wp_error($new_post_id)) {
                                        $duplicated++;
                                    }
                                }
                            }
                        }
                        if ($duplicated > 0) {
                            echo '<div class="notice notice-success is-dismissible"><p>' . sprintf(_n('%s item duplicated successfully.', '%s items duplicated successfully.', $duplicated, 'sky-seo-boost'), $duplicated) . '</p></div>';
                        }
                        break;
                }
            }
        }
        
        sky_seo_render_custom_post_list();
    }
});

// Helper function to get all posts in hierarchical order
function sky_seo_get_hierarchical_posts($post_type, $args = array()) {
    $defaults = array(
        'post_type' => $post_type,
        'posts_per_page' => -1,
        'orderby' => 'menu_order title',
        'order' => 'ASC',
        'post_status' => array('publish', 'draft', 'pending', 'private'),
        'post_parent' => 0
    );
    
    $args = wp_parse_args($args, $defaults);
    
    // Special handling for trash - don't filter by parent
    if (isset($args['post_status']) && $args['post_status'] === 'trash') {
        unset($args['post_parent']);
    }
    
    $posts = get_posts($args);
    
    if ($args['post_status'] === 'trash') {
        // For trash, just return the posts without hierarchy
        return $posts;
    }
    
    $hierarchical_posts = array();
    
    foreach ($posts as $post) {
        $hierarchical_posts[] = $post;
        // Get children
        $children_args = $args;
        $children_args['post_parent'] = $post->ID;
        $children = sky_seo_get_hierarchical_posts($post_type, $children_args);
        $hierarchical_posts = array_merge($hierarchical_posts, $children);
    }
    
    return $hierarchical_posts;
}

// Helper function to get post level
function sky_seo_get_post_level($post_id) {
    $level = 0;
    $post = get_post($post_id);
    
    while ($post->post_parent) {
        $level++;
        $post = get_post($post->post_parent);
    }
    
    return $level;
}

// Render the custom post list
function sky_seo_render_custom_post_list() {
    global $typenow, $wpdb;
    
    // Get plugin settings
    $settings = get_option('sky_seo_settings', []);
    $duplicate_enabled = !empty($settings['duplicate_feature_enabled']);
    
    // Get parameters
    $paged = isset($_GET['paged']) ? max(1, intval($_GET['paged'])) : 1;
    $posts_per_page = 20;
    $post_status = isset($_GET['post_status']) ? sanitize_text_field($_GET['post_status']) : 'all';
    $search_term = isset($_GET['s']) ? sanitize_text_field($_GET['s']) : '';
    $start_date = isset($_GET['start_date']) ? sanitize_text_field($_GET['start_date']) : '';
    $end_date = isset($_GET['end_date']) ? sanitize_text_field($_GET['end_date']) : '';
    
    // For hierarchical display, we need to get all posts first
    $query_args = [
        'post_type' => $typenow,
        'orderby' => 'menu_order title',
        'order' => 'ASC',
    ];
    
    // Post status filter - Fixed to properly handle trash
    if ($post_status === 'trash') {
        $query_args['post_status'] = 'trash';
    } elseif ($post_status !== 'all') {
        $query_args['post_status'] = $post_status;
    } else {
        $query_args['post_status'] = ['publish', 'draft', 'pending', 'private'];
    }
    
    // Search
    if (!empty($search_term)) {
        $query_args['s'] = $search_term;
    }
    
    // Get all posts in hierarchical order
    $all_posts = sky_seo_get_hierarchical_posts($typenow, $query_args);
    
    // Paginate the hierarchical list
    $total_items = count($all_posts);
    $total_pages = ceil($total_items / $posts_per_page);
    $offset = ($paged - 1) * $posts_per_page;
    $posts_for_page = array_slice($all_posts, $offset, $posts_per_page);
    
    $clicks_table = $wpdb->prefix . 'sky_seo_clicks';
    
    // Get post type label and count
    $post_type_obj = get_post_type_object($typenow);
    $count_posts = wp_count_posts($typenow);
    
    // Calculate date filter for clicks
    $where_date = '';
    if ($start_date && $end_date) {
        $start = date('Y-m-d 00:00:00', strtotime($start_date));
        $end = date('Y-m-d 23:59:59', strtotime($end_date));
        $where_date = $wpdb->prepare(" AND click_time BETWEEN %s AND %s", $start, $end);
    }
    ?>
    
    <div class="sky-seo-custom-list">
        <div class="sky-seo-all-content-wrap">
            <!-- Header Actions -->
            <div class="sky-seo-header-actions">
                <a href="<?php echo admin_url('post-new.php?post_type=' . $typenow); ?>" class="button button-primary">
                    <span class="dashicons dashicons-plus-alt"></span> <?php echo esc_html($post_type_obj->labels->add_new_item); ?>
                </a>
            </div>
            
            <!-- Status Filter Tabs -->
            <ul class="sky-seo-status-tabs">
                <li class="<?php echo $post_status === 'all' ? 'active' : ''; ?>">
                    <a href="<?php echo admin_url('edit.php?post_type=' . $typenow); ?>">
                        <?php _e('All', 'sky-seo-boost'); ?> 
                        <span class="count">(<?php echo number_format_i18n($count_posts->publish + $count_posts->draft + $count_posts->pending + $count_posts->private); ?>)</span>
                    </a>
                </li>
                <li class="<?php echo $post_status === 'publish' ? 'active' : ''; ?>">
                    <a href="<?php echo admin_url('edit.php?post_status=publish&post_type=' . $typenow); ?>">
                        <?php _e('Published', 'sky-seo-boost'); ?> 
                        <span class="count">(<?php echo number_format_i18n($count_posts->publish); ?>)</span>
                    </a>
                </li>
                <li class="<?php echo $post_status === 'draft' ? 'active' : ''; ?>">
                    <a href="<?php echo admin_url('edit.php?post_status=draft&post_type=' . $typenow); ?>">
                        <?php _e('Draft', 'sky-seo-boost'); ?> 
                        <span class="count">(<?php echo number_format_i18n($count_posts->draft); ?>)</span>
                    </a>
                </li>
                <?php if ($count_posts->trash > 0) : ?>
                <li class="<?php echo $post_status === 'trash' ? 'active' : ''; ?>">
                    <a href="<?php echo admin_url('edit.php?post_status=trash&post_type=' . $typenow); ?>">
                        <?php _e('Trash', 'sky-seo-boost'); ?> 
                        <span class="count">(<?php echo number_format_i18n($count_posts->trash); ?>)</span>
                    </a>
                </li>
                <?php endif; ?>
            </ul>

            <!-- Filters -->
            <div class="sky-seo-content-filters-card">
                <form method="get" class="sky-seo-filters-form">
                    <input type="hidden" name="post_type" value="<?php echo esc_attr($typenow); ?>">
                    <?php if ($post_status !== 'all') : ?>
                        <input type="hidden" name="post_status" value="<?php echo esc_attr($post_status); ?>">
                    <?php endif; ?>
                    
                    <div class="sky-seo-filter-row">
                        <div class="sky-seo-filter-group">
                            <label for="s"><?php _e('Search', 'sky-seo-boost'); ?></label>
                            <input type="text" name="s" id="s" placeholder="<?php _e('Search posts...', 'sky-seo-boost'); ?>" value="<?php echo esc_attr($search_term); ?>">
                        </div>
                        
                        <div class="sky-seo-filter-group">
                            <label for="start_date"><?php _e('Date Range', 'sky-seo-boost'); ?></label>
                            <div class="sky-seo-date-range">
                                <input type="date" name="start_date" id="start_date" value="<?php echo esc_attr($start_date); ?>">
                                <span class="sky-seo-date-separator">—</span>
                                <input type="date" name="end_date" id="end_date" value="<?php echo esc_attr($end_date); ?>">
                            </div>
                        </div>
                        
                        <div class="sky-seo-filter-actions">
                            <button type="submit" class="button"><?php _e('Apply Filters', 'sky-seo-boost'); ?></button>
                            <a href="<?php echo admin_url('edit.php?post_type=' . $typenow . ($post_status === 'trash' ? '&post_status=trash' : '')); ?>" class="button button-link"><?php _e('Clear', 'sky-seo-boost'); ?></a>
                        </div>
                    </div>
                </form>
            </div>

            <form method="post" class="sky-seo-content-form">
                <?php wp_nonce_field('bulk-posts'); ?>
                
                <!-- Bulk Actions Bar -->
                <div class="sky-seo-bulk-actions-bar">
                    <div class="alignleft actions">
                        <select name="action" class="sky-seo-bulk-select">
                            <option value="-1"><?php _e('Bulk Actions', 'sky-seo-boost'); ?></option>
                            <?php if ($post_status === 'trash') : ?>
                                <option value="untrash"><?php _e('Restore', 'sky-seo-boost'); ?></option>
                                <option value="delete"><?php _e('Delete Permanently', 'sky-seo-boost'); ?></option>
                            <?php else : ?>
                                <option value="publish"><?php _e('Publish', 'sky-seo-boost'); ?></option>
                                <option value="draft"><?php _e('Set to Draft', 'sky-seo-boost'); ?></option>
                                <option value="trash"><?php _e('Move to Trash', 'sky-seo-boost'); ?></option>
                                <?php if ($duplicate_enabled) : ?>
                                    <option value="duplicate"><?php _e('Duplicate', 'sky-seo-boost'); ?></option>
                                <?php endif; ?>
                            <?php endif; ?>
                        </select>
                        <input type="submit" class="button action" value="<?php _e('Apply', 'sky-seo-boost'); ?>">
                    </div>
                    
                    <div class="sky-seo-results-info">
                        <?php
                        $showing_from = $offset + 1;
                        $showing_to = min($offset + $posts_per_page, $total_items);
                        
                        if ($total_items > 0) {
                            printf(
                                __('Showing %1$s–%2$s of %3$s items', 'sky-seo-boost'),
                                number_format_i18n($showing_from),
                                number_format_i18n($showing_to),
                                number_format_i18n($total_items)
                            );
                        } else {
                            _e('No items found', 'sky-seo-boost');
                        }
                        ?>
                    </div>
                </div>

                <!-- Content Table -->
                <div class="sky-seo-content-table-wrapper">
                    <table class="sky-seo-content-table">
                        <thead>
                            <tr>
                                <th class="check-column">
                                    <input type="checkbox" id="cb-select-all-1">
                                </th>
                                <th class="column-title"><?php _e('Title', 'sky-seo-boost'); ?></th>
                                <th class="column-author"><?php _e('Author', 'sky-seo-boost'); ?></th>
                                <th class="column-clicks">
                                    <span class="dashicons dashicons-chart-bar"></span>
                                    <?php _e('Total Clicks', 'sky-seo-boost'); ?>
                                </th>
                                <th class="column-traffic"><?php _e('Traffic Sources', 'sky-seo-boost'); ?></th>
                                <th class="column-status"><?php _e('Status', 'sky-seo-boost'); ?></th>
                                <th class="column-date"><?php _e('Date', 'sky-seo-boost'); ?></th>
                            </tr>
                        </thead>
                        <tbody>
                            <?php if (!empty($posts_for_page)) : 
                                foreach ($posts_for_page as $post) : 
                                    setup_postdata($post);
                                    $post_id = $post->ID;
                                    $level = sky_seo_get_post_level($post_id);
                                    
                                    // Get click data
                                    $total_clicks = $wpdb->get_var($wpdb->prepare("SELECT SUM(clicks) FROM $clicks_table WHERE post_id = %d" . $where_date, $post_id));
                                    $google_clicks = $wpdb->get_var($wpdb->prepare("SELECT SUM(google_clicks) FROM $clicks_table WHERE post_id = %d" . $where_date, $post_id));
                                    $social_clicks = $wpdb->get_var($wpdb->prepare("SELECT SUM(social_clicks) FROM $clicks_table WHERE post_id = %d" . $where_date, $post_id));
                                    $direct_clicks = $wpdb->get_var($wpdb->prepare("SELECT SUM(direct_clicks) FROM $clicks_table WHERE post_id = %d" . $where_date, $post_id));
                                    
                                    $total_clicks = $total_clicks ?: 0;
                                    $google_clicks = $google_clicks ?: 0;
                                    $social_clicks = $social_clicks ?: 0;
                                    $direct_clicks = $direct_clicks ?: 0;
                                ?>
                                <tr class="level-<?php echo $level; ?>">
                                    <td class="check-column">
                                        <input type="checkbox" name="post[]" value="<?php echo $post_id; ?>">
                                    </td>
                                    <td class="column-title">
                                        <strong>
                                            <?php if ($post_status === 'trash') : ?>
                                                <?php echo get_the_title($post_id); ?>
                                            <?php else : ?>
                                                <a href="<?php echo get_edit_post_link($post_id); ?>" class="row-title">
                                                    <?php echo get_the_title($post_id); ?>
                                                </a>
                                            <?php endif; ?>
                                        </strong>
                                        <div class="row-actions">
                                            <?php if ($post_status === 'trash') : ?>
                                                <span class="untrash">
                                                    <a href="<?php echo wp_nonce_url(admin_url(sprintf('post.php?post=%d&action=untrash', $post_id)), 'untrash-post_' . $post_id); ?>"><?php _e('Restore', 'sky-seo-boost'); ?></a> | 
                                                </span>
                                                <span class="delete">
                                                    <a href="<?php echo get_delete_post_link($post_id, '', true); ?>" class="submitdelete"><?php _e('Delete Permanently', 'sky-seo-boost'); ?></a>
                                                </span>
                                            <?php else : ?>
                                                <span class="edit">
                                                    <a href="<?php echo get_edit_post_link($post_id); ?>"><?php _e('Edit', 'sky-seo-boost'); ?></a> | 
                                                </span>
                                                <?php 
                                                // Add Elementor edit link if Elementor is active and the post can be edited with Elementor
                                                if (defined('ELEMENTOR_VERSION') && \Elementor\Plugin::$instance->documents->get($post_id)) : ?>
                                                    <span class="edit-with-elementor">
                                                        <a href="<?php echo esc_url(\Elementor\Plugin::$instance->documents->get($post_id)->get_edit_url()); ?>" target="_blank"><?php _e('Edit with Elementor', 'sky-seo-boost'); ?></a> | 
                                                    </span>
                                                <?php endif; ?>
                                                <span class="view">
                                                    <a href="<?php echo get_permalink($post_id); ?>" target="_blank"><?php _e('View', 'sky-seo-boost'); ?></a> | 
                                                </span>
                                                <span class="trash">
                                                    <a href="<?php echo get_delete_post_link($post_id); ?>" class="submitdelete"><?php _e('Trash', 'sky-seo-boost'); ?></a>
                                                </span>
                                                <?php if ($duplicate_enabled) : ?>
                                                    <?php
                                                    $duplicate_url = wp_nonce_url(
                                                        add_query_arg(
                                                            [
                                                                'action' => 'sky_seo_duplicate_post',
                                                                'post' => $post_id,
                                                                'redirect' => urlencode($_SERVER['REQUEST_URI'])
                                                            ],
                                                            admin_url('admin.php')
                                                        ),
                                                        'sky-seo-duplicate-' . $post_id
                                                    );
                                                    ?>
                                                    <span class="duplicate"> | 
                                                        <a href="<?php echo esc_url($duplicate_url); ?>"><?php _e('Duplicate This', 'sky-seo-boost'); ?></a>
                                                    </span>
                                                <?php endif; ?>
                                            <?php endif; ?>
                                        </div>
                                    </td>
                                    <td class="column-author">
                                        <?php echo get_the_author_meta('display_name', $post->post_author); ?>
                                    </td>
                                    <td class="column-clicks">
                                        <div class="sky-seo-clicks-display">
                                            <strong><?php echo number_format_i18n($total_clicks); ?></strong>
                                            <?php if ($total_clicks > 0) : ?>
                                                <div class="sky-seo-click-trend">
                                                    <?php
                                                    $yesterday_clicks = $wpdb->get_var($wpdb->prepare(
                                                        "SELECT SUM(clicks) FROM $clicks_table WHERE post_id = %d AND DATE(click_time) = DATE(NOW() - INTERVAL 1 DAY)",
                                                        $post_id
                                                    ));
                                                    $today_clicks = $wpdb->get_var($wpdb->prepare(
                                                        "SELECT SUM(clicks) FROM $clicks_table WHERE post_id = %d AND DATE(click_time) = CURDATE()",
                                                        $post_id
                                                    ));
                                                    
                                                    if ($today_clicks > $yesterday_clicks) {
                                                        echo '<span class="trend-up" title="' . __('Trending up', 'sky-seo-boost') . '">↑</span>';
                                                    } elseif ($today_clicks < $yesterday_clicks && $yesterday_clicks > 0) {
                                                        echo '<span class="trend-down" title="' . __('Trending down', 'sky-seo-boost') . '">↓</span>';
                                                    }
                                                    ?>
                                                </div>
                                            <?php endif; ?>
                                        </div>
                                    </td>
                                    <td class="column-traffic">
                                        <?php if ($total_clicks > 0) : ?>
                                            <div class="sky-seo-traffic-breakdown">
                                                <div class="sky-seo-traffic-bar">
                                                    <?php
                                                    $google_percent = ($google_clicks / $total_clicks) * 100;
                                                    $social_percent = ($social_clicks / $total_clicks) * 100;
                                                    $direct_percent = ($direct_clicks / $total_clicks) * 100;
                                                    ?>
                                                    <div class="bar-segment google" style="width: <?php echo $google_percent; ?>%;" title="<?php echo esc_attr(sprintf(__('Google: %s clicks', 'sky-seo-boost'), number_format_i18n($google_clicks))); ?>"></div>
                                                    <div class="bar-segment social" style="width: <?php echo $social_percent; ?>%;" title="<?php echo esc_attr(sprintf(__('Social: %s clicks', 'sky-seo-boost'), number_format_i18n($social_clicks))); ?>"></div>
                                                    <div class="bar-segment direct" style="width: <?php echo $direct_percent; ?>%;" title="<?php echo esc_attr(sprintf(__('Direct: %s clicks', 'sky-seo-boost'), number_format_i18n($direct_clicks))); ?>"></div>
                                                </div>
                                                <div class="sky-seo-traffic-legend">
                                                    <span class="legend-item google"><?php echo round($google_percent); ?>%</span>
                                                    <span class="legend-item social"><?php echo round($social_percent); ?>%</span>
                                                    <span class="legend-item direct"><?php echo round($direct_percent); ?>%</span>
                                                </div>
                                            </div>
                                        <?php else : ?>
                                            <span class="no-data">—</span>
                                        <?php endif; ?>
                                    </td>
                                    <td class="column-status">
                                        <?php
                                        $status = $post->post_status;
                                        $status_labels = [
                                            'publish' => ['label' => __('Published', 'sky-seo-boost'), 'class' => 'published'],
                                            'draft' => ['label' => __('Draft', 'sky-seo-boost'), 'class' => 'draft'],
                                            'pending' => ['label' => __('Pending', 'sky-seo-boost'), 'class' => 'pending'],
                                            'private' => ['label' => __('Private', 'sky-seo-boost'), 'class' => 'private'],
                                            'trash' => ['label' => __('Trash', 'sky-seo-boost'), 'class' => 'trash']
                                        ];
                                        $status_info = isset($status_labels[$status]) ? $status_labels[$status] : ['label' => $status, 'class' => 'default'];
                                        ?>
                                        <span class="sky-seo-status-badge <?php echo esc_attr($status_info['class']); ?>">
                                            <?php echo esc_html($status_info['label']); ?>
                                        </span>
                                    </td>
                                    <td class="column-date">
                                        <div class="sky-seo-date-info">
                                            <span class="date"><?php echo get_the_date('', $post_id); ?></span>
                                            <span class="time"><?php echo get_the_time('', $post_id); ?></span>
                                        </div>
                                    </td>
                                </tr>
                            <?php endforeach; 
                                wp_reset_postdata();
                            else : ?>
                                <tr>
                                    <td colspan="7" class="no-items">
                                        <div class="sky-seo-no-content">
                                            <span class="dashicons dashicons-info"></span>
                                            <p><?php _e('No posts found.', 'sky-seo-boost'); ?></p>
                                        </div>
                                    </td>
                                </tr>
                            <?php endif; ?>
                        </tbody>
                    </table>
                </div>
                
                <?php
                // Pagination
                if ($total_pages > 1) :
                ?>
                    <div class="sky-seo-pagination">
                        <div class="pagination-info">
                            <?php
                            printf(
                                __('Page %1$s of %2$s', 'sky-seo-boost'),
                                number_format_i18n($paged),
                                number_format_i18n($total_pages)
                            );
                            ?>
                        </div>
                        
                        <div class="pagination-links">
                            <?php
                            $base_url = admin_url('edit.php?post_type=' . $typenow);
                            if ($post_status !== 'all') {
                                $base_url = add_query_arg('post_status', $post_status, $base_url);
                            }
                            if (!empty($search_term)) {
                                $base_url = add_query_arg('s', $search_term, $base_url);
                            }
                            
                            // First page
                            if ($paged > 2) {
                                echo '<a href="' . esc_url($base_url) . '" class="first-page button">
                                    <span class="dashicons dashicons-controls-skipback"></span>
                                </a>';
                            }
                            
                            // Previous page
                            if ($paged > 1) {
                                echo '<a href="' . esc_url(add_query_arg('paged', $paged - 1, $base_url)) . '" class="prev-page button">
                                    <span class="dashicons dashicons-arrow-left-alt2"></span> ' . __('Previous', 'sky-seo-boost') . '
                                </a>';
                            }
                            
                            // Page numbers
                            echo '<div class="page-numbers">';
                            
                            $range = 2;
                            $start = max(1, $paged - $range);
                            $end = min($total_pages, $paged + $range);
                            
                            if ($start > 1) {
                                echo '<span class="dots">…</span>';
                            }
                            
                            for ($i = $start; $i <= $end; $i++) {
                                if ($i == $paged) {
                                    echo '<span class="current">' . $i . '</span>';
                                } else {
                                    echo '<a href="' . esc_url(add_query_arg('paged', $i, $base_url)) . '" class="page-number">' . $i . '</a>';
                                }
                            }
                            
                            if ($end < $total_pages) {
                                echo '<span class="dots">…</span>';
                            }
                            
                            echo '</div>';
                            
                            // Next page
                            if ($paged < $total_pages) {
                                echo '<a href="' . esc_url(add_query_arg('paged', $paged + 1, $base_url)) . '" class="next-page button">
                                    ' . __('Next', 'sky-seo-boost') . ' <span class="dashicons dashicons-arrow-right-alt2"></span>
                                </a>';
                            }
                            
                            // Last page
                            if ($paged < $total_pages - 1) {
                                echo '<a href="' . esc_url(add_query_arg('paged', $total_pages, $base_url)) . '" class="last-page button">
                                    <span class="dashicons dashicons-controls-skipforward"></span>
                                </a>';
                            }
                            ?>
                        </div>
                    </div>
                <?php
                endif;
                ?>
            </form>
        </div>
    </div>
    <?php
}

// Add JavaScript to handle checkboxes
add_action('admin_footer', function() {
    global $typenow;
    if (in_array($typenow, ['sky_areas', 'sky_trending', 'sky_sectors'])) {
        ?>
        <script>
        jQuery(document).ready(function($) {
            // Handle select all checkbox
            $('#cb-select-all-1').on('change', function() {
                $('input[name="post[]"]').prop('checked', $(this).is(':checked'));
            });
        });
        </script>
        <?php
    }
});