<?php
/**
 * Sky SEO Boost - Advanced Indexing Features (Enhanced)
 * 
 * Optimized indexing features for custom post types with maximum compatibility
 * 
 * @package Sky_SEO_Boost
 * @since 3.0.0
 * @version 5.0.0 - Enhanced with smart indexing features
 */

if (!defined('ABSPATH')) exit;

class Sky_SEO_Indexing_Boost {
    
    /**
     * Priority post types that need aggressive indexing
     */
    private $priority_post_types = ['sky_areas', 'sky_trending', 'sky_sectors'];
    
    /**
     * Active SEO plugin
     */
    private $active_seo_plugin = 'none';
    
    /**
     * Constructor
     */
    public function __construct() {
        // Get active SEO plugin
        $settings = get_option('sky_seo_settings', []);
        $this->active_seo_plugin = $settings['active_seo_plugin'] ?? 'none';
        
        // Core indexing features
        add_action('transition_post_status', [$this, 'handle_post_status_change'], 10, 3);
        add_action('wp_head', [$this, 'add_indexing_meta_tags'], 1);
        add_filter('robots_txt', [$this, 'enhance_robots_txt'], 10, 2);
        
        // Sitemap handling
        add_action('publish_sky_areas', [$this, 'force_sitemap_refresh']);
        add_action('publish_sky_trending', [$this, 'force_sitemap_refresh']);
        add_action('publish_sky_sectors', [$this, 'force_sitemap_refresh']);
        
        // Sitemap priority boosting
        $this->setup_sitemap_priority_filters();
        
        // IndexNow API support
        add_action('publish_sky_areas', [$this, 'notify_indexnow']);
        add_action('publish_sky_trending', [$this, 'notify_indexnow']);
        add_action('publish_sky_sectors', [$this, 'notify_indexnow']);
        
        // RSS feed enhancement
        add_filter('pre_get_posts', [$this, 'include_custom_posts_in_feed']);
        add_action('rss2_item', [$this, 'add_rss_fields']);
        add_action('rss_item', [$this, 'add_rss_fields']);
        
        // Add XML sitemap ping for all post types
        add_action('publish_post', [$this, 'ping_on_publish']);
        add_action('publish_page', [$this, 'ping_on_publish']);
        
        // Google Indexing API support (if configured)
        if (get_option('sky_seo_google_indexing_api_credentials')) {
            add_action('publish_sky_areas', [$this, 'notify_google_indexing_api']);
            add_action('publish_sky_trending', [$this, 'notify_google_indexing_api']);
            add_action('publish_sky_sectors', [$this, 'notify_google_indexing_api']);
        }
        
        // Add support for JSON-LD WebSub for real-time updates
        add_action('wp_head', [$this, 'add_websub_links'], 2);
        
        // Optimize post dates for freshness
        add_filter('get_the_modified_time', [$this, 'boost_modified_time'], 10, 3);
        
        // Add internal linking data attributes (for search engines, not content modification)
        add_filter('the_permalink', [$this, 'add_indexing_hints'], 10, 2);
    }
    
    /**
     * Setup sitemap priority filters based on SEO plugin
     */
    private function setup_sitemap_priority_filters() {
        switch ($this->active_seo_plugin) {
            case 'yoast':
                add_filter('wpseo_sitemap_entry', [$this, 'boost_yoast_sitemap_priority'], 10, 3);
                add_filter('wpseo_sitemap_urlimages', [$this, 'prioritize_images'], 10, 2);
                break;
                
            case 'rankmath':
                add_filter('rank_math/sitemap/entry', [$this, 'boost_rankmath_sitemap_priority'], 10, 3);
                add_filter('rank_math/sitemap/priority', [$this, 'rankmath_priority'], 10, 2);
                add_filter('rank_math/sitemap/frequency', [$this, 'rankmath_frequency'], 10, 2);
                break;
                
            case 'aioseo':
                add_filter('aioseo_sitemap_priority', [$this, 'boost_aioseo_priority'], 10, 3);
                add_filter('aioseo_sitemap_frequency', [$this, 'boost_aioseo_frequency'], 10, 3);
                break;
                
            case 'seopress':
                add_filter('seopress_sitemaps_single_priority', [$this, 'boost_seopress_priority'], 10, 2);
                add_filter('seopress_sitemaps_single_frequency', [$this, 'boost_seopress_frequency'], 10, 2);
                break;
        }
    }
    
    /**
     * Handle post status changes for immediate indexing
     */
    public function handle_post_status_change($new_status, $old_status, $post) {
        if ($new_status === 'publish' && $old_status !== 'publish') {
            if (in_array($post->post_type, $this->priority_post_types)) {
                // Immediate actions for new published posts
                $this->send_priority_indexing_signals($post->ID);
                
                // More aggressive ping schedule for priority posts
                wp_schedule_single_event(time() + 60, 'sky_seo_delayed_ping', [$post->ID]); // 1 minute
                wp_schedule_single_event(time() + 300, 'sky_seo_delayed_ping', [$post->ID]); // 5 minutes
                wp_schedule_single_event(time() + 900, 'sky_seo_delayed_ping', [$post->ID]); // 15 minutes
                wp_schedule_single_event(time() + 3600, 'sky_seo_delayed_ping', [$post->ID]); // 1 hour
                wp_schedule_single_event(time() + 21600, 'sky_seo_delayed_ping', [$post->ID]); // 6 hours
                wp_schedule_single_event(time() + 86400, 'sky_seo_delayed_ping', [$post->ID]); // 24 hours
                
                // Store publish time for priority calculations
                update_post_meta($post->ID, '_sky_seo_publish_timestamp', time());
            }
        }
        
        // Also handle updates to published posts
        if ($new_status === 'publish' && $old_status === 'publish') {
            if (in_array($post->post_type, $this->priority_post_types)) {
                // Ping for updates too
                $this->ping_search_engines(get_permalink($post->ID));
                $this->notify_indexnow($post->ID);
            }
        }
    }
    
    /**
     * Send priority indexing signals
     */
    private function send_priority_indexing_signals($post_id) {
        $url = get_permalink($post_id);
        
        // 1. Ping search engines immediately
        $this->ping_search_engines($url);
        
        // 2. Send additional specialized pings
        $this->send_priority_pings($url);
        
        // 3. Update sitemap timestamp
        $this->update_sitemap_timestamp();
        
        // 4. Clear minimal caches
        $this->clear_post_cache($post_id);
        
        // 5. Trigger IndexNow immediately
        $this->notify_indexnow($post_id);
        
        // 6. Log for debugging
        if (defined('WP_DEBUG') && WP_DEBUG) {
            error_log(sprintf('Sky SEO Boost: Priority indexing signals sent for %s (ID: %d)', $url, $post_id));
        }
    }
    
    /**
     * Enhanced search engine pinging
     */
    private function ping_search_engines($url) {
        $sitemap_url = home_url('/sitemap_index.xml');
        
        // Multiple Google endpoints for redundancy
        $google_endpoints = [
            'https://www.google.com/ping?sitemap=' . urlencode($sitemap_url),
            'https://www.google.com/webmasters/tools/ping?sitemap=' . urlencode($sitemap_url)
        ];
        
        foreach ($google_endpoints as $endpoint) {
            wp_remote_get($endpoint, [
                'timeout' => 5,
                'blocking' => false,
                'user-agent' => 'Mozilla/5.0 (compatible; Sky SEO Boost/5.0; +' . home_url() . ')'
            ]);
        }
        
        // Bing
        wp_remote_get('https://www.bing.com/ping?sitemap=' . urlencode($sitemap_url), [
            'timeout' => 5,
            'blocking' => false
        ]);
        
        // Also ping with specific URL
        wp_remote_get('https://www.bing.com/ping?sitemap=' . urlencode($url), [
            'timeout' => 5,
            'blocking' => false
        ]);
    }
    
    /**
     * Send priority pings for faster discovery
     */
    private function send_priority_pings($url) {
        // Google PubSubHubbub
        $hub_url = 'https://pubsubhubbub.appspot.com/';
        $feed_url = get_bloginfo('rss2_url');
        
        wp_remote_post($hub_url, [
            'body' => [
                'hub.mode' => 'publish',
                'hub.url' => $feed_url
            ],
            'timeout' => 5,
            'blocking' => false
        ]);
        
        // WebSub (successor to PubSubHubbub)
        wp_remote_post('https://websub.rocks/hub', [
            'body' => [
                'hub.mode' => 'publish',
                'hub.topic' => $feed_url
            ],
            'timeout' => 5,
            'blocking' => false
        ]);
    }
    
    /**
     * Update sitemap timestamp to trigger re-crawl
     */
    private function update_sitemap_timestamp() {
        // Update option to force sitemap regeneration
        update_option('sky_seo_sitemap_last_modified', current_time('mysql'));
        
        // Clear sitemap cache based on active SEO plugin
        switch ($this->active_seo_plugin) {
            case 'yoast':
                if (class_exists('WPSEO_Sitemaps_Cache')) {
                    WPSEO_Sitemaps_Cache::clear();
                }
                break;
                
            case 'rankmath':
                if (class_exists('RankMath\Sitemap\Cache')) {
                    RankMath\Sitemap\Cache::invalidate_storage();
                }
                break;
                
            case 'aioseo':
                if (function_exists('aioseo')) {
                    aioseo()->sitemap->cache->clear();
                }
                break;
                
            case 'seopress':
                delete_transient('_seopress_sitemap_ids_');
                break;
        }
    }
    
    /**
     * Clear post-specific cache only
     */
    private function clear_post_cache($post_id) {
        // Skip during AJAX or REST requests
        if (wp_doing_ajax() || (defined('REST_REQUEST') && REST_REQUEST)) {
            return;
        }
        
        // Clear WordPress post cache
        clean_post_cache($post_id);
        
        // Clear plugin-specific post cache
        if (function_exists('w3tc_flush_post')) {
            w3tc_flush_post($post_id);
        }
        
        if (function_exists('rocket_clean_post')) {
            rocket_clean_post($post_id);
        }
        
        if (class_exists('LiteSpeed_Cache_API') && method_exists('LiteSpeed_Cache_API', 'purge_post')) {
            LiteSpeed_Cache_API::purge_post($post_id);
        }
        
        // WP Fastest Cache
        if (function_exists('wpfc_clear_post_cache_by_id')) {
            wpfc_clear_post_cache_by_id($post_id);
        }
    }
    
    /**
     * Add enhanced meta tags for indexing
     */
    public function add_indexing_meta_tags() {
        if (!is_singular($this->priority_post_types)) {
            return;
        }
        
        if (is_admin() || wp_doing_ajax()) {
            return;
        }
        
        global $post;
        
        // Priority indexing tags
        echo '<meta name="robots" content="index, follow, max-image-preview:large, max-snippet:-1, max-video-preview:-1">' . "\n";
        echo '<meta name="googlebot" content="index, follow, max-image-preview:large, max-snippet:-1, max-video-preview:-1">' . "\n";
        echo '<meta name="bingbot" content="index, follow, max-image-preview:large, max-snippet:-1, max-video-preview:-1">' . "\n";
        
        // Crawl hints
        echo '<meta name="revisit-after" content="1 days">' . "\n";
        echo '<meta name="google-site-verification" content="indexing-priority-high">' . "\n";
        
        // Last modified with microseconds for uniqueness
        echo '<meta property="article:modified_time" content="' . get_the_modified_time('c', $post) . '">' . "\n";
        echo '<meta property="og:updated_time" content="' . get_the_modified_time('c', $post) . '">' . "\n";
        
        // Canonical (if not handled by SEO plugin)
        if ($this->active_seo_plugin === 'none') {
            echo '<link rel="canonical" href="' . get_permalink($post) . '">' . "\n";
        }
        
        // Add alternate links for better discovery
        echo '<link rel="alternate" type="application/rss+xml" title="RSS" href="' . get_post_comments_feed_link($post->ID) . '">' . "\n";
    }
    
    /**
     * Add WebSub links for real-time updates
     */
    public function add_websub_links() {
        if (!is_singular($this->priority_post_types) && !is_home() && !is_archive()) {
            return;
        }
        
        echo '<link rel="hub" href="https://pubsubhubbub.appspot.com/">' . "\n";
        echo '<link rel="hub" href="https://websub.rocks/hub">' . "\n";
        echo '<link rel="self" href="' . get_bloginfo('rss2_url') . '">' . "\n";
    }
    
    /**
     * Boost sitemap priority for Yoast
     */
    public function boost_yoast_sitemap_priority($url, $type, $post) {
        if (in_array($post->post_type, $this->priority_post_types)) {
            $post_age_days = (time() - strtotime($post->post_date)) / DAY_IN_SECONDS;
            
            if ($post_age_days <= 1) {
                $url['pri'] = 1.0; // Maximum priority for brand new
            } elseif ($post_age_days <= 7) {
                $url['pri'] = 0.9; // Very high for week old
            } elseif ($post_age_days <= 30) {
                $url['pri'] = 0.8; // High for month old
            } else {
                $url['pri'] = 0.7; // Still elevated
            }
            
            // Increase change frequency for newer posts
            if ($post_age_days <= 7) {
                $url['chf'] = 'hourly';
            } elseif ($post_age_days <= 30) {
                $url['chf'] = 'daily';
            } else {
                $url['chf'] = 'weekly';
            }
        }
        
        return $url;
    }
    
    /**
     * Boost sitemap priority for RankMath
     */
    public function boost_rankmath_sitemap_priority($url, $type, $object) {
        if (isset($object->post_type) && in_array($object->post_type, $this->priority_post_types)) {
            $post_age_days = (time() - strtotime($object->post_date)) / DAY_IN_SECONDS;
            
            if ($post_age_days <= 1) {
                $url['priority'] = 1.0;
            } elseif ($post_age_days <= 7) {
                $url['priority'] = 0.9;
            } elseif ($post_age_days <= 30) {
                $url['priority'] = 0.8;
            }
        }
        
        return $url;
    }
    
    /**
     * RankMath priority filter
     */
    public function rankmath_priority($priority, $type) {
        if (in_array($type, $this->priority_post_types)) {
            return 1.0;
        }
        return $priority;
    }
    
    /**
     * RankMath frequency filter
     */
    public function rankmath_frequency($frequency, $type) {
        if (in_array($type, $this->priority_post_types)) {
            return 'hourly';
        }
        return $frequency;
    }
    
    /**
     * Boost AIOSEO priority
     */
    public function boost_aioseo_priority($priority, $type, $object_id) {
        $post = get_post($object_id);
        if ($post && in_array($post->post_type, $this->priority_post_types)) {
            $post_age_days = (time() - strtotime($post->post_date)) / DAY_IN_SECONDS;
            
            if ($post_age_days <= 7) {
                return 1.0;
            } elseif ($post_age_days <= 30) {
                return 0.8;
            }
        }
        return $priority;
    }
    
    /**
     * Boost AIOSEO frequency
     */
    public function boost_aioseo_frequency($frequency, $type, $object_id) {
        $post = get_post($object_id);
        if ($post && in_array($post->post_type, $this->priority_post_types)) {
            return 'hourly';
        }
        return $frequency;
    }
    
    /**
     * Boost SEOPress priority
     */
    public function boost_seopress_priority($priority, $post) {
        if (in_array($post->post_type, $this->priority_post_types)) {
            $post_age_days = (time() - strtotime($post->post_date)) / DAY_IN_SECONDS;
            
            if ($post_age_days <= 7) {
                return 1.0;
            } elseif ($post_age_days <= 30) {
                return 0.8;
            }
        }
        return $priority;
    }
    
    /**
     * Boost SEOPress frequency
     */
    public function boost_seopress_frequency($frequency, $post) {
        if (in_array($post->post_type, $this->priority_post_types)) {
            return 'hourly';
        }
        return $frequency;
    }
    
    /**
     * Prioritize images in sitemap
     */
    public function prioritize_images($images, $post_id) {
        $post = get_post($post_id);
        if ($post && in_array($post->post_type, $this->priority_post_types)) {
            // Ensure featured image is first
            if (has_post_thumbnail($post_id)) {
                $featured_id = get_post_thumbnail_id($post_id);
                $featured_url = wp_get_attachment_url($featured_id);
                
                // Move featured image to front
                $new_images = [['src' => $featured_url, 'title' => get_the_title($post_id)]];
                foreach ($images as $image) {
                    if ($image['src'] !== $featured_url) {
                        $new_images[] = $image;
                    }
                }
                return $new_images;
            }
        }
        return $images;
    }
    
    /**
     * Enhance robots.txt for better sitemap discovery
     */
    public function enhance_robots_txt($output, $public) {
        if ($public) {
            $output .= "\n# Sky SEO Boost - Priority Indexing\n";
            $output .= "Sitemap: " . home_url('/sitemap_index.xml') . "\n";
            
            // Add news sitemap if it exists
            $output .= "Sitemap: " . home_url('/news-sitemap.xml') . "\n";
            
            // Add custom post type sitemaps
            foreach ($this->priority_post_types as $post_type) {
                $output .= "Sitemap: " . home_url("/sitemap-{$post_type}.xml") . "\n";
            }
            
            // Priority crawl paths
            $output .= "\n# Priority Paths\n";
            $output .= "User-agent: *\n";
            $output .= "Crawl-delay: 0\n";
            $output .= "Allow: /areas/\n";
            $output .= "Allow: /insights/\n";
            $output .= "Allow: /sectors/\n";
            $output .= "Allow: /wp-json/\n";
            $output .= "Allow: /*?*\n";
            
            // IndexNow key location
            $key = $this->get_indexnow_key();
            if ($key) {
                $output .= "\n# IndexNow\n";
                $output .= "Allow: /" . $key . ".txt\n";
            }
        }
        
        return $output;
    }
    
    /**
     * Force sitemap refresh
     */
    public function force_sitemap_refresh() {
        // Clear transients
        delete_transient('sky_seo_sitemap_cache');
        delete_transient('_transient_timeout_wpseo_sitemap_cache_1');
        delete_transient('_transient_wpseo_sitemap_cache_1');
        
        // Trigger sitemap pings immediately and after delay
        $this->ping_search_engines(home_url('/sitemap_index.xml'));
        wp_schedule_single_event(time() + 60, 'sky_seo_ping_sitemaps');
        
        // Update timestamp
        update_option('sky_seo_sitemap_last_modified', current_time('mysql'));
    }
    
    /**
     * Notify IndexNow API for instant indexing
     */
    public function notify_indexnow($post_id) {
        $url = get_permalink($post_id);
        $key = $this->get_or_create_indexnow_key();
        
        if (!$key) {
            return;
        }
        
        // Extended endpoints list
        $endpoints = [
            'https://api.indexnow.org/indexnow',
            'https://www.bing.com/indexnow',
            'https://yandex.com/indexnow',
            'https://api.seznam.cz/indexnow'
        ];
        
        $data = [
            'host' => parse_url(home_url(), PHP_URL_HOST),
            'key' => $key,
            'keyLocation' => home_url("/{$key}.txt"),
            'urlList' => [$url]
        ];
        
        foreach ($endpoints as $endpoint) {
            wp_remote_post($endpoint, [
                'body' => wp_json_encode($data),
                'headers' => [
                    'Content-Type' => 'application/json',
                    'Host' => parse_url($endpoint, PHP_URL_HOST)
                ],
                'timeout' => 5,
                'blocking' => false,
                'user-agent' => 'Sky SEO Boost/5.0'
            ]);
        }
        
        // Log IndexNow submission
        if (defined('WP_DEBUG') && WP_DEBUG) {
            error_log('Sky SEO Boost: IndexNow notified for ' . $url);
        }
    }
    
    /**
     * Get or create IndexNow API key
     */
    private function get_or_create_indexnow_key() {
        $key = get_option('sky_seo_indexnow_key');
        
        if (!$key) {
            $key = wp_generate_uuid4();
            update_option('sky_seo_indexnow_key', $key);
            
            // Create key file in root
            $this->create_indexnow_key_file($key);
        }
        
        return $key;
    }
    
    /**
     * Get existing IndexNow key
     */
    private function get_indexnow_key() {
        return get_option('sky_seo_indexnow_key', '');
    }
    
    /**
     * Create IndexNow key file
     */
    private function create_indexnow_key_file($key) {
        $file_path = ABSPATH . $key . '.txt';
        
        if (!file_exists($file_path)) {
            $file_handle = @fopen($file_path, 'w');
            if ($file_handle) {
                fwrite($file_handle, $key);
                fclose($file_handle);
                
                // Set proper permissions
                @chmod($file_path, 0644);
            }
        }
    }
    
    /**
     * Include custom posts in RSS feed
     */
    public function include_custom_posts_in_feed($query) {
        if ($query->is_feed() && $query->is_main_query()) {
            // Include custom post types in feed
            $post_types = array_merge(['post'], $this->priority_post_types);
            $query->set('post_type', $post_types);
            
            // Increase feed items for better discovery
            $query->set('posts_per_page', 20);
            
            // Order by modified date to show updates
            $query->set('orderby', 'modified');
            $query->set('order', 'DESC');
        }
        
        return $query;
    }
    
    /**
     * Add extra RSS fields for better indexing
     */
    public function add_rss_fields() {
        global $post;
        
        if (in_array($post->post_type, $this->priority_post_types)) {
            // Add update frequency hint
            echo '<sy:updatePeriod>hourly</sy:updatePeriod>' . "\n";
            echo '<sy:updateFrequency>1</sy:updateFrequency>' . "\n";
            
            // Add priority hint
            echo '<priority>1.0</priority>' . "\n";
        }
    }
    
    /**
     * Ping on any publish
     */
    public function ping_on_publish($post_id) {
        // Quick ping for all content
        $this->ping_search_engines(get_permalink($post_id));
    }
    
    /**
     * Boost modified time for fresh content signal
     */
    public function boost_modified_time($time, $format, $post) {
        // For very new posts, always return current time to appear fresh
        if (is_object($post) && in_array($post->post_type, $this->priority_post_types)) {
            $post_age = time() - strtotime($post->post_date);
            
            // If less than 24 hours old, always return current time
            if ($post_age < DAY_IN_SECONDS) {
                return current_time($format);
            }
        }
        
        return $time;
    }
    
    /**
     * Add indexing hints to permalinks
     */
    public function add_indexing_hints($permalink, $post) {
        // Only add for priority post types in admin/feed context
        if (is_admin() || is_feed()) {
            if (is_object($post) && in_array($post->post_type, $this->priority_post_types)) {
                // Add timestamp parameter for uniqueness (helps with re-crawling)
                $permalink = add_query_arg('indexed', time(), $permalink);
            }
        }
        
        return $permalink;
    }
    
    /**
     * Notify Google Indexing API (requires configuration)
     */
    public function notify_google_indexing_api($post_id) {
        // Check if credentials are configured
        $credentials_path = get_option('sky_seo_google_indexing_api_credentials');
        
        if (!$credentials_path || !file_exists($credentials_path)) {
            return;
        }
        
        $url = get_permalink($post_id);
        
        // Use action hook for actual implementation
        do_action('sky_seo_google_indexing_api_notify', $url, $post_id);
    }
}

// Initialize the indexing boost class
new Sky_SEO_Indexing_Boost();

// Handle delayed pings with more details
add_action('sky_seo_delayed_ping', function($post_id) {
    if (get_post_status($post_id) === 'publish') {
        $sitemap_url = home_url('/sitemap_index.xml');
        $post_url = get_permalink($post_id);
        
        // Ping with both sitemap and specific URL
        wp_remote_get('https://www.google.com/ping?sitemap=' . urlencode($sitemap_url), [
            'timeout' => 5,
            'blocking' => false
        ]);
        
        wp_remote_get('https://www.bing.com/ping?sitemap=' . urlencode($sitemap_url), [
            'timeout' => 5,
            'blocking' => false
        ]);
        
        // Also trigger IndexNow for delayed pings
        $boost = new Sky_SEO_Indexing_Boost();
        $boost->notify_indexnow($post_id);
    }
});

// Handle scheduled sitemap pings
add_action('sky_seo_ping_sitemaps', function() {
    $sitemap_url = home_url('/sitemap_index.xml');
    
    // Ping all major search engines
    $endpoints = [
        'https://www.google.com/ping?sitemap=' . urlencode($sitemap_url),
        'https://www.bing.com/ping?sitemap=' . urlencode($sitemap_url)
    ];
    
    foreach ($endpoints as $endpoint) {
        wp_remote_get($endpoint, [
            'timeout' => 5,
            'blocking' => false
        ]);
    }
});
