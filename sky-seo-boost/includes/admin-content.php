<?php
// Prevent direct access
if (!defined('ABSPATH')) {
    exit;
}

// All Content Admin Page
function sky_seo_all_content_page() {
    $post_types = ['sky_areas', 'sky_trending', 'sky_sectors'];
    $paged = isset($_GET['paged']) ? max(1, intval($_GET['paged'])) : 1;
    $posts_per_page = 20;
    $selected_post_type = isset($_GET['post_type_filter']) ? sanitize_text_field($_GET['post_type_filter']) : 'all';
    $start_date = isset($_GET['start_date']) ? sanitize_text_field($_GET['start_date']) : '';
    $end_date = isset($_GET['end_date']) ? sanitize_text_field($_GET['end_date']) : '';
    $search_term = isset($_GET['search']) ? sanitize_text_field($_GET['search']) : '';

    // Handle bulk actions
    if (isset($_POST['action']) && isset($_POST['post_ids']) && is_array($_POST['post_ids']) && check_admin_referer('sky_seo_bulk_action')) {
        $action = sanitize_text_field($_POST['action']);
        $post_ids = array_map('intval', $_POST['post_ids']);
        if (!empty($post_ids)) {
            switch ($action) {
                case 'publish':
                    foreach ($post_ids as $post_id) {
                        if (current_user_can('publish_posts', $post_id)) {
                            wp_publish_post($post_id);
                        }
                    }
                    echo '<div class="notice notice-success is-dismissible"><p>' . esc_html__('Selected posts published.', 'sky360') . '</p></div>';
                    break;
                case 'draft':
                    foreach ($post_ids as $post_id) {
                        if (current_user_can('edit_post', $post_id)) {
                            wp_update_post(['ID' => $post_id, 'post_status' => 'draft']);
                        }
                    }
                    echo '<div class="notice notice-success is-dismissible"><p>' . esc_html__('Selected posts set to draft.', 'sky360') . '</p></div>';
                    break;
                case 'delete':
                    foreach ($post_ids as $post_id) {
                        if (current_user_can('delete_post', $post_id)) {
                            wp_delete_post($post_id, true);
                        }
                    }
                    echo '<div class="notice notice-success is-dismissible"><p>' . esc_html__('Selected posts deleted.', 'sky360') . '</p></div>';
                    break;
            }
        }
    }

    $query_args = [
        'post_type' => $post_types,
        'posts_per_page' => $posts_per_page,
        'paged' => $paged,
        'post_status' => ['publish', 'draft'],
        'orderby' => 'date',
        'order' => 'DESC',
    ];

    if ($selected_post_type !== 'all' && in_array($selected_post_type, $post_types)) {
        $query_args['post_type'] = $selected_post_type;
    }

    if (!empty($search_term)) {
        $query_args['s'] = $search_term;
    }

    $query = new WP_Query($query_args);

    global $wpdb;
    $clicks_table = $wpdb->prefix . 'sky_seo_clicks';
    $where_date = '';
    if ($start_date && $end_date) {
        $start = date('Y-m-d 00:00:00', strtotime($start_date));
        $end = date('Y-m-d 23:59:59', strtotime($end_date));
        $where_date = $wpdb->prepare(" AND click_time BETWEEN %s AND %s", $start, $end);
    }
    // Start Sky360 page wrapper
    sky360_admin_page_start();
    sky360_render_admin_header(
        __('All Content', 'sky360'),
        __('Manage Areas, Trending Searches, and Sectors', 'sky360'),
        [
            ['url' => admin_url('post-new.php?post_type=sky_areas'), 'label' => __('New Area', 'sky360'), 'icon' => 'dashicons-plus-alt'],
            ['url' => admin_url('post-new.php?post_type=sky_trending'), 'label' => __('New Trending', 'sky360'), 'icon' => 'dashicons-plus-alt'],
            ['url' => admin_url('post-new.php?post_type=sky_sectors'), 'label' => __('New Sector', 'sky360'), 'icon' => 'dashicons-plus-alt'],
        ]
    );
    sky360_content_wrapper_start();
    ?>
<div class="sky-seo-all-content-wrap">

        <div class="sky-seo-content-filters-card">
            <form method="get" class="sky-seo-filters-form">
                <input type="hidden" name="page" value="sky-seo-all-content">
                
                <div class="sky-seo-filter-row">
                    <div class="sky-seo-filter-group">
                        <label for="search"><?php _e('Search', 'sky360'); ?></label>
                        <input type="text" name="search" id="search" placeholder="<?php _e('Search by title...', 'sky360'); ?>" value="<?php echo esc_attr($search_term); ?>">
                    </div>
                    
                    <div class="sky-seo-filter-group">
                        <label for="post_type_filter"><?php _e('Post Type', 'sky360'); ?></label>
                        <select name="post_type_filter" id="post_type_filter">
                            <option value="all" <?php selected($selected_post_type, 'all'); ?>><?php _e('All Post Types', 'sky360'); ?></option>
                            <option value="sky_areas" <?php selected($selected_post_type, 'sky_areas'); ?>><?php _e('Areas', 'sky360'); ?></option>
                            <option value="sky_trending" <?php selected($selected_post_type, 'sky_trending'); ?>><?php _e('Trending Searches', 'sky360'); ?></option>
                            <option value="sky_sectors" <?php selected($selected_post_type, 'sky_sectors'); ?>><?php _e('Sectors', 'sky360'); ?></option>
                        </select>
                    </div>
                    
                    <div class="sky-seo-filter-group">
                        <label for="start_date"><?php _e('Date Range', 'sky360'); ?></label>
                        <div class="sky-seo-date-range">
                            <input type="date" name="start_date" id="start_date" value="<?php echo esc_attr($start_date); ?>" placeholder="<?php _e('Start', 'sky360'); ?>">
                            <span class="sky-seo-date-separator">—</span>
                            <input type="date" name="end_date" id="end_date" value="<?php echo esc_attr($end_date); ?>" placeholder="<?php _e('End', 'sky360'); ?>">
                        </div>
                    </div>
                    
                    <div class="sky-seo-filter-actions">
                        <button type="submit" class="button"><?php _e('Apply Filters', 'sky360'); ?></button>
                        <a href="?page=sky-seo-all-content" class="button button-link"><?php _e('Clear', 'sky360'); ?></a>
                    </div>
                </div>
            </form>
        </div>

        <form method="post" class="sky-seo-content-form">
            <?php wp_nonce_field('sky_seo_bulk_action'); ?>
            
            <div class="sky-seo-bulk-actions-bar">
                <div class="alignleft actions">
                    <select name="action" class="sky-seo-bulk-select">
                        <option value="-1"><?php _e('Bulk Actions', 'sky360'); ?></option>
                        <option value="publish"><?php _e('Publish', 'sky360'); ?></option>
                        <option value="draft"><?php _e('Set to Draft', 'sky360'); ?></option>
                        <option value="delete"><?php _e('Delete', 'sky360'); ?></option>
                    </select>
                    <input type="submit" class="button action" value="<?php _e('Apply', 'sky360'); ?>">
                </div>
                
                <div class="sky-seo-results-info">
                    <?php
                    $total_items = $query->found_posts;
                    $showing_from = (($paged - 1) * $posts_per_page) + 1;
                    $showing_to = min($paged * $posts_per_page, $total_items);
                    
                    if ($total_items > 0) {
                        printf(
                            __('Showing %1$s–%2$s of %3$s items', 'sky360'),
                            number_format_i18n($showing_from),
                            number_format_i18n($showing_to),
                            number_format_i18n($total_items)
                        );
                    } else {
                        _e('No items found', 'sky360');
                    }
                    ?>
                </div>
            </div>

            <div class="sky-seo-content-table-wrapper">
                <table class="sky-seo-content-table">
                    <thead>
                        <tr>
                            <th class="check-column">
                                <input type="checkbox" id="select-all" class="sky-seo-select-all">
                            </th>
                            <th class="column-title"><?php esc_html_e('Title', 'sky360'); ?></th>
                            <th class="column-type"><?php esc_html_e('Type', 'sky360'); ?></th>
                            <th class="column-clicks">
                                <span class="dashicons dashicons-chart-bar"></span>
                                <?php esc_html_e('Total Clicks', 'sky360'); ?>
                            </th>
                            <th class="column-traffic"><?php esc_html_e('Traffic Sources', 'sky360'); ?></th>
                            <th class="column-status"><?php esc_html_e('Status', 'sky360'); ?></th>
                            <th class="column-date"><?php esc_html_e('Date', 'sky360'); ?></th>
                        </tr>
                    </thead>
                    <tbody id="sky-seo-table-body">
                        <?php if ($query->have_posts()) : while ($query->have_posts()) : $query->the_post(); 
                            $post_id = get_the_ID();
                            
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
                            <tr>
                                <td class="check-column">
                                    <input type="checkbox" name="post_ids[]" value="<?php echo esc_attr($post_id); ?>">
                                </td>
                                <td class="column-title">
                                    <strong>
                                        <a href="<?php echo esc_url(get_edit_post_link()); ?>" class="row-title">
                                            <?php the_title(); ?>
                                        </a>
                                    </strong>
                                    <div class="row-actions">
                                        <span class="edit">
                                            <a href="<?php echo esc_url(get_edit_post_link()); ?>"><?php _e('Edit', 'sky360'); ?></a> | 
                                        </span>
                                        <span class="view">
                                            <a href="<?php echo esc_url(get_permalink()); ?>" target="_blank"><?php _e('View', 'sky360'); ?></a> | 
                                        </span>
                                        <span class="trash">
                                            <a href="<?php echo esc_url(get_delete_post_link($post_id)); ?>" class="submitdelete"><?php _e('Trash', 'sky360'); ?></a>
                                        </span>
                                    </div>
                                </td>
                                <td class="column-type">
                                    <?php
                                    $post_type = get_post_type();
                                    $type_labels = [
                                        'sky_areas' => ['label' => __('Area', 'sky360'), 'icon' => 'location-alt'],
                                        'sky_trending' => ['label' => __('Trending', 'sky360'), 'icon' => 'trending-up'],
                                        'sky_sectors' => ['label' => __('Sector', 'sky360'), 'icon' => 'building']
                                    ];
                                    $type_info = isset($type_labels[$post_type]) ? $type_labels[$post_type] : ['label' => $post_type, 'icon' => 'admin-post'];
                                    ?>
                                    <div class="sky-seo-post-type sky-seo-type-<?php echo esc_attr($post_type); ?>">
                                        <span class="dashicons dashicons-<?php echo esc_attr($type_info['icon']); ?>"></span>
                                        <?php echo esc_html($type_info['label']); ?>
                                    </div>
                                </td>
                                <td class="column-clicks">
                                    <div class="sky-seo-clicks-display">
                                        <strong><?php echo number_format_i18n($total_clicks); ?></strong>
                                        <?php if ($total_clicks > 0) : ?>
                                            <div class="sky-seo-click-trend">
                                                <?php
                                                // Simple trend indicator
                                                $yesterday_clicks = $wpdb->get_var($wpdb->prepare(
                                                    "SELECT SUM(clicks) FROM $clicks_table WHERE post_id = %d AND DATE(click_time) = DATE(NOW() - INTERVAL 1 DAY)",
                                                    $post_id
                                                ));
                                                $today_clicks = $wpdb->get_var($wpdb->prepare(
                                                    "SELECT SUM(clicks) FROM $clicks_table WHERE post_id = %d AND DATE(click_time) = CURDATE()",
                                                    $post_id
                                                ));
                                                
                                                if ($today_clicks > $yesterday_clicks) {
                                                    echo '<span class="trend-up" title="' . __('Trending up', 'sky360') . '">↑</span>';
                                                } elseif ($today_clicks < $yesterday_clicks && $yesterday_clicks > 0) {
                                                    echo '<span class="trend-down" title="' . __('Trending down', 'sky360') . '">↓</span>';
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
                                                <div class="bar-segment google" style="width: <?php echo $google_percent; ?>%;" title="<?php echo esc_attr(sprintf(__('Google: %s clicks', 'sky360'), number_format_i18n($google_clicks))); ?>"></div>
                                                <div class="bar-segment social" style="width: <?php echo $social_percent; ?>%;" title="<?php echo esc_attr(sprintf(__('Social: %s clicks', 'sky360'), number_format_i18n($social_clicks))); ?>"></div>
                                                <div class="bar-segment direct" style="width: <?php echo $direct_percent; ?>%;" title="<?php echo esc_attr(sprintf(__('Direct: %s clicks', 'sky360'), number_format_i18n($direct_clicks))); ?>"></div>
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
                                    $status = get_post_status();
                                    $status_labels = [
                                        'publish' => ['label' => __('Published', 'sky360'), 'class' => 'published'],
                                        'draft' => ['label' => __('Draft', 'sky360'), 'class' => 'draft'],
                                        'pending' => ['label' => __('Pending', 'sky360'), 'class' => 'pending'],
                                        'private' => ['label' => __('Private', 'sky360'), 'class' => 'private']
                                    ];
                                    $status_info = isset($status_labels[$status]) ? $status_labels[$status] : ['label' => $status, 'class' => 'default'];
                                    ?>
                                    <span class="sky-seo-status-badge <?php echo esc_attr($status_info['class']); ?>">
                                        <?php echo esc_html($status_info['label']); ?>
                                    </span>
                                </td>
                                <td class="column-date">
                                    <div class="sky-seo-date-info">
                                        <span class="date"><?php echo esc_html(get_the_date()); ?></span>
                                        <span class="time"><?php echo esc_html(get_the_time()); ?></span>
                                    </div>
                                </td>
                            </tr>
                        <?php endwhile; else : ?>
                            <tr>
                                <td colspan="7" class="no-items">
                                    <div class="sky-seo-no-content">
                                        <span class="dashicons dashicons-info"></span>
                                        <p><?php esc_html_e('No content found.', 'sky360'); ?></p>
                                        <?php if (!empty($search_term) || $selected_post_type !== 'all' || !empty($start_date)) : ?>
                                            <p class="description"><?php _e('Try adjusting your filters or', 'sky360'); ?> 
                                                <a href="?page=sky-seo-all-content"><?php _e('clear all filters', 'sky360'); ?></a>
                                            </p>
                                        <?php endif; ?>
                                    </div>
                                </td>
                            </tr>
                        <?php endif; ?>
                    </tbody>
                </table>
            </div>
            
            <?php
            // Improved Pagination
            $total_pages = $query->max_num_pages;
            if ($total_pages > 1) :
            ?>
                <div class="sky-seo-pagination">
                    <div class="pagination-info">
                        <?php
                        printf(
                            __('Page %1$s of %2$s', 'sky360'),
                            number_format_i18n($paged),
                            number_format_i18n($total_pages)
                        );
                        ?>
                    </div>
                    
                    <div class="pagination-links">
                        <?php
                        // First page link
                        if ($paged > 2) {
                            echo '<a href="' . esc_url(add_query_arg('paged', 1)) . '" class="first-page button">
                                <span class="dashicons dashicons-controls-skipback"></span>
                            </a>';
                        }
                        
                        // Previous page link
                        if ($paged > 1) {
                            echo '<a href="' . esc_url(add_query_arg('paged', $paged - 1)) . '" class="prev-page button">
                                <span class="dashicons dashicons-arrow-left-alt2"></span> ' . __('Previous', 'sky360') . '
                            </a>';
                        }
                        
                        // Page numbers
                        echo '<div class="page-numbers">';
                        
                        // Calculate range of pages to show
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
                                echo '<a href="' . esc_url(add_query_arg('paged', $i)) . '" class="page-number">' . $i . '</a>';
                            }
                        }
                        
                        if ($end < $total_pages) {
                            echo '<span class="dots">…</span>';
                        }
                        
                        echo '</div>';
                        
                        // Next page link
                        if ($paged < $total_pages) {
                            echo '<a href="' . esc_url(add_query_arg('paged', $paged + 1)) . '" class="next-page button">
                                ' . __('Next', 'sky360') . ' <span class="dashicons dashicons-arrow-right-alt2"></span>
                            </a>';
                        }
                        
                        // Last page link
                        if ($paged < $total_pages - 1) {
                            echo '<a href="' . esc_url(add_query_arg('paged', $total_pages)) . '" class="last-page button">
                                <span class="dashicons dashicons-controls-skipforward"></span>
                            </a>';
                        }
                        ?>
                    </div>
                </div>
            <?php
            endif;
            wp_reset_postdata();
            ?>
        </form>
    </div>
    <?php
    sky360_content_wrapper_end();
    sky360_admin_page_end();
}
?>