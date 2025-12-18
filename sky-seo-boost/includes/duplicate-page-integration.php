<?php
// Prevent direct access
if (!defined('ABSPATH')) {
    exit;
}

/**
 * Sky SEO Boost - Built-in Duplicate Post Feature
 * Provides duplicate functionality for all post types with full Elementor support
 */
class Sky_SEO_Duplicate_Post_Feature {
    
    private $settings;
    private static $instance = null;
    private $hooks_added = false;
    
    /**
     * Get singleton instance
     */
    public static function get_instance() {
        if (null === self::$instance) {
            self::$instance = new self();
        }
        return self::$instance;
    }
    
    /**
     * Destroy instance
     */
    public static function destroy_instance() {
        if (self::$instance !== null) {
            self::$instance->remove_hooks();
            self::$instance = null;
        }
    }
    
    /**
     * Constructor
     */
    private function __construct() {
        // Load settings with proper defaults
        $this->load_settings();
        
        // Only initialize if feature is enabled
        if ($this->is_feature_enabled()) {
            $this->init_hooks();
        }
    }
    
    /**
     * Load settings with validation
     */
    private function load_settings() {
        $defaults = [
            'duplicate_feature_enabled' => 0, // Default to disabled
            'duplicate_post_types' => [],
            'duplicate_meta' => 1,
            'duplicate_taxonomies' => 1,
            'duplicate_status' => 'draft',
            'duplicate_title_prefix' => '',
            'duplicate_title_suffix' => ' (Copy)',
            'duplicate_author' => 'current',
            'duplicate_comments' => 0,
            'duplicate_admin_bar' => 0,
            'duplicate_redirect' => 'list'
        ];
        
        $saved_settings = get_option('sky_seo_settings', []);
        
        // Merge with defaults to ensure all keys exist
        $this->settings = wp_parse_args($saved_settings, $defaults);
        
        // Ensure duplicate_feature_enabled is properly set
        $this->settings['duplicate_feature_enabled'] = isset($saved_settings['duplicate_feature_enabled']) 
            ? (int) $saved_settings['duplicate_feature_enabled'] 
            : 0;
    }
    
    /**
     * Check if feature is enabled
     */
    private function is_feature_enabled() {
        return !empty($this->settings['duplicate_feature_enabled']) && $this->settings['duplicate_feature_enabled'] == 1;
    }
    
    /**
     * Initialize hooks only if feature is enabled
     */
    private function init_hooks() {
        if ($this->hooks_added) {
            return; // Prevent double initialization
        }
        
        // Core hooks
        add_action('init', [$this, 'init'], 10);
        add_filter('post_row_actions', [$this, 'add_duplicate_link'], 10, 2);
        add_filter('page_row_actions', [$this, 'add_duplicate_link'], 10, 2);
        add_action('admin_action_sky_seo_duplicate_post', [$this, 'duplicate_post']);
        add_action('admin_notices', [$this, 'duplication_admin_notice']);
        
        // Bulk actions
        add_action('admin_footer-edit.php', [$this, 'add_bulk_duplicate_script']);
        add_filter('bulk_actions-edit-post', [$this, 'register_bulk_duplicate']);
        add_filter('bulk_actions-edit-page', [$this, 'register_bulk_duplicate']);
        add_filter('handle_bulk_actions-edit-post', [$this, 'handle_bulk_duplicate'], 10, 3);
        add_filter('handle_bulk_actions-edit-page', [$this, 'handle_bulk_duplicate'], 10, 3);
        
        // Custom post types support
        add_action('init', [$this, 'register_custom_post_type_support'], 20);
        
        // Admin bar support
        if (!empty($this->settings['duplicate_admin_bar'])) {
            add_action('admin_bar_menu', [$this, 'add_admin_bar_link'], 100);
        }
        
        // AJAX handlers
        add_action('wp_ajax_sky_seo_duplicate_post', [$this, 'ajax_duplicate_post']);
        
        $this->hooks_added = true;
    }
    
    public function init() {
        // Additional initialization if needed
    }
    
    /**
     * Remove all hooks (for disabling the feature)
     */
    public function remove_hooks() {
        remove_action('init', [$this, 'init'], 10);
        remove_filter('post_row_actions', [$this, 'add_duplicate_link'], 10);
        remove_filter('page_row_actions', [$this, 'add_duplicate_link'], 10);
        remove_action('admin_action_sky_seo_duplicate_post', [$this, 'duplicate_post']);
        remove_action('admin_notices', [$this, 'duplication_admin_notice']);
        remove_action('admin_footer-edit.php', [$this, 'add_bulk_duplicate_script']);
        remove_filter('bulk_actions-edit-post', [$this, 'register_bulk_duplicate']);
        remove_filter('bulk_actions-edit-page', [$this, 'register_bulk_duplicate']);
        remove_filter('handle_bulk_actions-edit-post', [$this, 'handle_bulk_duplicate'], 10);
        remove_filter('handle_bulk_actions-edit-page', [$this, 'handle_bulk_duplicate'], 10);
        remove_action('init', [$this, 'register_custom_post_type_support'], 20);
        remove_action('admin_bar_menu', [$this, 'add_admin_bar_link'], 100);
        remove_action('wp_ajax_sky_seo_duplicate_post', [$this, 'ajax_duplicate_post']);
        
        // Remove custom post type filters
        $post_types = get_post_types(['public' => true], 'names');
        foreach ($post_types as $post_type) {
            remove_filter("{$post_type}_row_actions", [$this, 'add_duplicate_link'], 10);
            remove_filter("bulk_actions-edit-{$post_type}", [$this, 'register_bulk_duplicate']);
            remove_filter("handle_bulk_actions-edit-{$post_type}", [$this, 'handle_bulk_duplicate'], 10);
        }
        
        $this->hooks_added = false;
    }
    
    /**
     * Register support for all custom post types
     */
    public function register_custom_post_type_support() {
        if (!$this->is_feature_enabled()) {
            return;
        }
        
        $post_types = get_post_types(['public' => true], 'names');
        
        foreach ($post_types as $post_type) {
            if ($post_type === 'attachment') continue;
            
            // Check if post type is enabled for duplication
            $enabled_post_types = isset($this->settings['duplicate_post_types']) ? $this->settings['duplicate_post_types'] : [];
            
            // If no specific post types are set, enable for all
            if (!empty($enabled_post_types) && !in_array($post_type, $enabled_post_types)) {
                continue;
            }
            
            // Add row actions filter for each post type
            add_filter("{$post_type}_row_actions", [$this, 'add_duplicate_link'], 10, 2);
            
            // Add bulk actions for each post type
            add_filter("bulk_actions-edit-{$post_type}", [$this, 'register_bulk_duplicate']);
            add_filter("handle_bulk_actions-edit-{$post_type}", [$this, 'handle_bulk_duplicate'], 10, 3);
        }
    }
    
    /**
     * Add duplicate link to post/page row actions
     */
    public function add_duplicate_link($actions, $post) {
        // Skip if post is null
        if (!$post) {
            return $actions;
        }
        
        // Skip acf-field-group post type
        if ($post->post_type == 'acf-field-group') {
            return $actions;
        }
        
        // Check if user can create posts
        if (!current_user_can('edit_posts')) {
            return $actions;
        }
        
        // Check if post type is enabled for duplication
        $enabled_post_types = isset($this->settings['duplicate_post_types']) ? $this->settings['duplicate_post_types'] : [];
        
        // If specific post types are set, check if current post type is included
        if (!empty($enabled_post_types) && !in_array($post->post_type, $enabled_post_types)) {
            return $actions;
        }
        
        $duplicate_url = $this->get_duplicate_url($post->ID);
        $title = __('Duplicate this item', 'sky-seo-boost');
        
        $actions['duplicate'] = '<a href="' . esc_url($duplicate_url) . '" title="' . esc_attr($title) . '">' . __('Duplicate', 'sky-seo-boost') . '</a>';
        
        return $actions;
    }
    
    /**
     * Get duplicate URL
     */
    private function get_duplicate_url($post_id) {
        $action = 'sky_seo_duplicate_post';
        // Sanitize REQUEST_URI to prevent XSS and injection attacks
        $redirect_uri = isset($_SERVER['REQUEST_URI']) ? esc_url_raw(wp_unslash($_SERVER['REQUEST_URI'])) : '';
        $url = wp_nonce_url(
            add_query_arg(
                [
                    'action' => $action,
                    'post' => $post_id,
                    'redirect' => urlencode($redirect_uri)
                ],
                admin_url('admin.php')
            ),
            'sky-seo-duplicate-' . $post_id
        );

        return $url;
    }
    
    /**
     * Handle post duplication
     */
    public function duplicate_post() {
        // Verify request
        if (!isset($_GET['post']) || !isset($_GET['action']) || $_GET['action'] !== 'sky_seo_duplicate_post') {
            wp_die(__('No post to duplicate has been supplied!', 'sky-seo-boost'));
        }
        
        $post_id = absint($_GET['post']);
        
        // Security check
        check_admin_referer('sky-seo-duplicate-' . $post_id);
        
        // Check permissions
        if (!current_user_can('edit_post', $post_id)) {
            wp_die(__('You do not have permission to duplicate this post.', 'sky-seo-boost'));
        }
        
        // Duplicate the post
        $new_post_id = $this->create_duplicate($post_id);
        
        if (is_wp_error($new_post_id)) {
            wp_die($new_post_id->get_error_message());
        }
        
        // Redirect based on settings
        $redirect_to = isset($_GET['redirect']) ? urldecode($_GET['redirect']) : admin_url('edit.php');
        $post = get_post($post_id);
        
        if (!empty($this->settings['duplicate_redirect']) && $this->settings['duplicate_redirect'] === 'edit') {
            $redirect_to = get_edit_post_link($new_post_id, 'url');
        } else {
            $returnpage = '';
            if ($post && $post->post_type != 'post') {
                $returnpage = '?post_type=' . $post->post_type;
            }
            $redirect_to = admin_url('edit.php' . $returnpage);
        }
        
        wp_redirect($redirect_to);
        exit;
    }
    
    /**
     * Create duplicate post
     */
    private function create_duplicate($post_id) {
        global $wpdb;
        
        $post = get_post($post_id);
        
        if (!$post) {
            return new WP_Error('post_not_found', __('Post not found', 'sky-seo-boost'));
        }
        
        // WPML Phase 3: Get title prefix/suffix settings with translation support
        $title_prefix = isset($this->settings['duplicate_prefix']) ? $this->settings['duplicate_prefix'] : 'Copy of ';
        $title_suffix = isset($this->settings['duplicate_suffix']) ? $this->settings['duplicate_suffix'] : '';

        // Apply WPML translation if available
        if (function_exists('icl_t')) {
            $title_prefix = icl_t('sky-seo-boost', 'Duplicate: Default Prefix', $title_prefix);
            $title_suffix = icl_t('sky-seo-boost', 'Duplicate: Default Suffix', $title_suffix);
        }
        
        // Get current user
        $current_user = wp_get_current_user();
        $new_post_author = (isset($this->settings['duplicate_author']) && $this->settings['duplicate_author'] === 'original') 
                          ? $post->post_author 
                          : $current_user->ID;
        
        // Prepare new post data - IMPORTANT: use wp_slash for Elementor content
        $new_post = [
            'comment_status' => $post->comment_status,
            'ping_status' => $post->ping_status,
            'post_author' => $new_post_author,
            'post_content' => wp_slash($post->post_content), // This is crucial for Elementor
            'post_excerpt' => $post->post_excerpt,
            'post_parent' => $post->post_parent,
            'post_password' => $post->post_password,
            'post_status' => isset($this->settings['duplicate_status']) ? $this->settings['duplicate_status'] : 'draft',
            'post_title' => $title_prefix . $post->post_title . $title_suffix,
            'post_type' => $post->post_type,
            'to_ping' => $post->to_ping,
            'menu_order' => $post->menu_order,
        ];
        
        // Insert the post
        $new_post_id = wp_insert_post($new_post);
        
        if (is_wp_error($new_post_id)) {
            return $new_post_id;
        }
        
        // Copy taxonomies first (important for some themes/plugins)
        if (!empty($this->settings['duplicate_taxonomies'])) {
            $this->duplicate_post_taxonomies($post_id, $new_post_id);
        }
        
        // Copy post meta - this includes Elementor data
        if (!empty($this->settings['duplicate_meta'])) {
            $this->duplicate_post_meta($post_id, $new_post_id);
        }
        
        // Handle Elementor CSS regeneration
        if (function_exists('is_plugin_active') && is_plugin_active('elementor/elementor.php')) {
            // Clear Elementor cache and regenerate CSS
            if (class_exists('\Elementor\Core\Files\CSS\Post')) {
                try {
                    $css = new \Elementor\Core\Files\CSS\Post($new_post_id);
                    $css->update();
                } catch (Exception $e) {
                    // Silent fail - CSS generation is not critical
                }
            }
        }

        // Copy comments
        if (!empty($this->settings['duplicate_comments'])) {
            $this->duplicate_post_comments($post_id, $new_post_id);
        }
        
        // Allow other plugins to hook in
        do_action('sky_seo_post_duplicated', $new_post_id, $post_id);
        
        return $new_post_id;
    }
    
    /**
     * Duplicate post meta
     */
    private function duplicate_post_meta($old_post_id, $new_post_id) {
        $post_meta_keys = get_post_custom_keys($old_post_id);
        
        if (empty($post_meta_keys)) {
            return;
        }
        
        // Meta keys to exclude
        $exclude_meta = apply_filters('sky_seo_duplicate_exclude_meta', [
            '_wp_old_slug',
            '_edit_lock',
            '_edit_last',
            '_wp_old_date',
            '_dp_original',
            '_dp_is_rewrite_republish',
        ]);
        
        foreach ($post_meta_keys as $meta_key) {
            if (in_array($meta_key, $exclude_meta)) {
                continue;
            }
            
            $meta_values = get_post_custom_values($meta_key, $old_post_id);
            foreach ($meta_values as $meta_value) {
                $meta_value = maybe_unserialize($meta_value);
                update_post_meta($new_post_id, $meta_key, wp_slash($meta_value));
            }
        }
    }
    
    /**
     * Duplicate post taxonomies
     */
    private function duplicate_post_taxonomies($old_post_id, $new_post_id) {
        $post = get_post($new_post_id);
        if (!$post) {
            return;
        }
        
        $taxonomies = get_object_taxonomies($post->post_type);
        
        if (!empty($taxonomies) && is_array($taxonomies)) {
            foreach ($taxonomies as $taxonomy) {
                $post_terms = wp_get_object_terms($old_post_id, $taxonomy, ['fields' => 'slugs']);
                if (!is_wp_error($post_terms)) {
                    wp_set_object_terms($new_post_id, $post_terms, $taxonomy, false);
                }
            }
        }
    }
    
    /**
     * Duplicate post comments
     */
    private function duplicate_post_comments($old_post_id, $new_post_id) {
        $comments = get_comments([
            'post_id' => $old_post_id,
            'order' => 'ASC',
            'orderby' => 'comment_date_gmt',
            'status' => 'any',
        ]);
        
        $comment_id_map = [];
        
        foreach ($comments as $comment) {
            $parent = isset($comment_id_map[$comment->comment_parent]) ? $comment_id_map[$comment->comment_parent] : 0;
            
            $new_comment_data = [
                'comment_post_ID' => $new_post_id,
                'comment_author' => $comment->comment_author,
                'comment_author_email' => $comment->comment_author_email,
                'comment_author_url' => $comment->comment_author_url,
                'comment_author_IP' => $comment->comment_author_IP,
                'comment_date' => $comment->comment_date,
                'comment_date_gmt' => $comment->comment_date_gmt,
                'comment_content' => $comment->comment_content,
                'comment_karma' => $comment->comment_karma,
                'comment_approved' => $comment->comment_approved,
                'comment_agent' => $comment->comment_agent,
                'comment_type' => $comment->comment_type,
                'comment_parent' => $parent,
                'user_id' => $comment->user_id,
            ];
            
            $new_comment_id = wp_insert_comment($new_comment_data);
            $comment_id_map[$comment->comment_ID] = $new_comment_id;
        }
    }
    
    /**
     * Register bulk duplicate action
     */
    public function register_bulk_duplicate($bulk_actions) {
        $bulk_actions['duplicate'] = __('Duplicate', 'sky-seo-boost');
        return $bulk_actions;
    }
    
    /**
     * Handle bulk duplicate action
     */
    public function handle_bulk_duplicate($redirect_to, $doaction, $post_ids) {
        if ($doaction !== 'duplicate') {
            return $redirect_to;
        }
        
        if (!is_array($post_ids)) {
            return $redirect_to;
        }
        
        $duplicated = 0;
        
        foreach ($post_ids as $post_id) {
            if (!current_user_can('edit_post', $post_id)) {
                continue;
            }
            
            $new_post_id = $this->create_duplicate($post_id);
            
            if (!is_wp_error($new_post_id)) {
                $duplicated++;
            }
        }
        
        $redirect_to = add_query_arg('duplicated', $duplicated, $redirect_to);
        
        return $redirect_to;
    }
    
    /**
     * Show admin notice after duplication
     */
    public function duplication_admin_notice() {
        if (!empty($_REQUEST['duplicated'])) {
            $duplicated = intval($_REQUEST['duplicated']);
            printf(
                '<div class="notice notice-success is-dismissible"><p>' .
                _n(
                    '%s item duplicated successfully.',
                    '%s items duplicated successfully.',
                    $duplicated,
                    'sky-seo-boost'
                ) .
                '</p></div>',
                $duplicated
            );
        }
    }
    
    /**
     * Add duplicate link to admin bar
     */
    public function add_admin_bar_link($wp_admin_bar) {
        if (!is_admin() || !is_admin_bar_showing()) {
            return;
        }
        
        global $pagenow, $post;
        
        // Only show on post edit screens
        if ($pagenow !== 'post.php' && $pagenow !== 'post-new.php') {
            return;
        }
        
        if (!is_object($post) || !isset($post->ID)) {
            return;
        }
        
        if (!current_user_can('edit_post', $post->ID)) {
            return;
        }
        
        $args = [
            'id' => 'sky-seo-duplicate',
            'title' => __('Duplicate This', 'sky-seo-boost'),
            'href' => $this->get_duplicate_url($post->ID),
            'meta' => [
                'title' => __('Duplicate this item', 'sky-seo-boost'),
            ],
        ];
        
        $wp_admin_bar->add_node($args);
    }
    
    /**
     * Add JavaScript for enhanced functionality
     */
    public function add_bulk_duplicate_script() {
        ?>
        <script type="text/javascript">
        jQuery(document).ready(function($) {
            // Add duplicate link via AJAX
            $(document).on('click', '.sky-seo-quick-duplicate', function(e) {
                e.preventDefault();
                
                var $link = $(this);
                var post_id = $link.data('post-id');
                var $row = $link.closest('tr');
                
                $link.text('<?php _e('Duplicating...', 'sky-seo-boost'); ?>');
                
                $.ajax({
                    url: ajaxurl,
                    type: 'POST',
                    data: {
                        action: 'sky_seo_duplicate_post',
                        post_id: post_id,
                        nonce: '<?php echo wp_create_nonce('sky_seo_duplicate_ajax'); ?>'
                    },
                    success: function(response) {
                        if (response.success) {
                            $link.text('<?php _e('Duplicated!', 'sky-seo-boost'); ?>');
                            setTimeout(function() {
                                location.reload();
                            }, 1000);
                        } else {
                            $link.text('<?php _e('Error', 'sky-seo-boost'); ?>');
                            alert(response.data || '<?php _e('An error occurred', 'sky-seo-boost'); ?>');
                        }
                    },
                    error: function() {
                        $link.text('<?php _e('Error', 'sky-seo-boost'); ?>');
                        alert('<?php _e('An error occurred', 'sky-seo-boost'); ?>');
                    }
                });
            });
        });
        </script>
        <?php
    }
    
    /**
     * AJAX handler for quick duplicate
     */
    public function ajax_duplicate_post() {
        // Verify nonce
        if (!check_ajax_referer('sky_seo_duplicate_ajax', 'nonce', false)) {
            wp_send_json_error(__('Invalid security token', 'sky-seo-boost'));
        }
        
        $post_id = isset($_POST['post_id']) ? intval($_POST['post_id']) : 0;
        
        if (!$post_id) {
            wp_send_json_error(__('Invalid post ID', 'sky-seo-boost'));
        }
        
        if (!current_user_can('edit_post', $post_id)) {
            wp_send_json_error(__('Permission denied', 'sky-seo-boost'));
        }
        
        $new_post_id = $this->create_duplicate($post_id);
        
        if (is_wp_error($new_post_id)) {
            wp_send_json_error($new_post_id->get_error_message());
        }
        
        wp_send_json_success([
            'message' => __('Post duplicated successfully', 'sky-seo-boost'),
            'new_post_id' => $new_post_id,
            'edit_link' => get_edit_post_link($new_post_id, 'url'),
        ]);
    }
}

/**
 * Initialize the feature conditionally
 */
function sky_seo_init_duplicate_feature() {
    // Get settings to check if feature should be loaded
    $settings = get_option('sky_seo_settings', []);
    
    // Check if feature is enabled (default to 0/disabled)
    $enabled = isset($settings['duplicate_feature_enabled']) ? (int) $settings['duplicate_feature_enabled'] : 0;
    
    // Only initialize if the feature is explicitly enabled
    if ($enabled === 1) {
        Sky_SEO_Duplicate_Post_Feature::get_instance();
    } else {
        // Destroy any existing instance
        Sky_SEO_Duplicate_Post_Feature::destroy_instance();
    }
}

// Hook into plugins_loaded to ensure all dependencies are available
add_action('plugins_loaded', 'sky_seo_init_duplicate_feature', 15);

// Hook to handle settings updates
add_action('update_option_sky_seo_settings', function($old_value, $new_value) {
    // Re-initialize the feature based on new settings
    sky_seo_init_duplicate_feature();
}, 10, 2);