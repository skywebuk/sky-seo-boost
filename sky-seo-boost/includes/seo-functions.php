<?php
// Prevent direct access
if (!defined('ABSPATH')) {
    exit;
}

/**
 * Click Tracking Configuration Constants
 */
define('SKY_SEO_DUPLICATE_VIEW_WINDOW', 1800); // 30 minutes in seconds
define('SKY_SEO_RATE_LIMIT_WINDOW', 300); // 5 minutes in seconds
define('SKY_SEO_MAX_REQUESTS_PER_MINUTE', 10);
define('SKY_SEO_SUSPICIOUS_SCORE_THRESHOLD', 3);
define('SKY_SEO_CACHE_BOT_IP_DURATION', HOUR_IN_SECONDS);
define('SKY_SEO_CACHE_DATACENTER_IP_DURATION', HOUR_IN_SECONDS);

// Track Page Clicks with Bot Detection
function sky_seo_track_page_clicks() {
    // Get all tracked post types
    $tracked_types = sky_seo_get_tracked_post_types();
    
    if (is_singular($tracked_types) && !is_preview() && !is_admin()) {
        // CHECK 1: Detect and exclude bots/spiders
        if (sky_seo_is_bot()) {
            // Still track bot traffic but mark it as bot
            sky_seo_record_click(true, false);
            return;
        }
        
        // CHECK 2: Basic spam detection
        if (sky_seo_is_spam_traffic()) {
            // Track as suspicious traffic
            sky_seo_record_click(false, true);
            return;
        }
        
        // Track as human traffic
        sky_seo_record_click(false, false);
    }
}

// Record click with bot/spam status
function sky_seo_record_click($is_bot = false, $is_spam = false) {
    global $wpdb;

    // Enable debug logging when WP_DEBUG is on
    $debug = defined('WP_DEBUG') && WP_DEBUG;

    $post_id = get_the_ID();
    $table_name = $wpdb->prefix . 'sky_seo_clicks';
    
    // Get user agent
    $user_agent = isset($_SERVER['HTTP_USER_AGENT']) ? substr($_SERVER['HTTP_USER_AGENT'], 0, 255) : '';
    
    // Get location data
    $location = sky_seo_get_location_from_ip();
    $country_code = $location['country_code'];
    $country_name = $location['country_name'];
    $city_name = $location['city_name'];
    
    // Get referrer
    $referer = isset($_SERVER['HTTP_REFERER']) ? $_SERVER['HTTP_REFERER'] : '';
    
    // Categorize referer - IMPROVED LOGIC
    $google_domains = [
        // Major domains
        'google.com', 'google.co', 'google.ca', 'google.co.uk', 'google.fr', 'google.de',
        'google.it', 'google.es', 'google.com.au', 'google.co.in', 'google.com.br',
        'google.co.jp', 'google.ru', 'google.com.mx', 'google.cn', 'google.com.sg',
        'google.ae', 'google.sa', 'google.com.eg', 'google.co.za', 'google.com.pk',
        
        // European domains
        'google.nl', 'google.be', 'google.ch', 'google.at', 'google.se', 'google.no',
        'google.dk', 'google.fi', 'google.ie', 'google.pt', 'google.gr', 'google.pl',
        'google.cz', 'google.hu', 'google.ro', 'google.bg', 'google.hr', 'google.sk',
        'google.si', 'google.lt', 'google.lv', 'google.ee', 'google.lu', 'google.is',
        
        // Additional international domains
        'google.com.tr', 'google.com.hk', 'google.com.tw', 'google.co.kr', 'google.co.th',
        'google.com.vn', 'google.com.ph', 'google.com.my', 'google.co.id', 'google.com.np',
        'google.lk', 'google.com.bd', 'google.com.af', 'google.kz', 'google.uz',
        'google.com.ua', 'google.co.il', 'google.jo', 'google.com.lb', 'google.com.qa',
        'google.com.kw', 'google.com.om', 'google.co.ke', 'google.com.ng', 'google.com.gh',
        'google.co.tz', 'google.co.ug', 'google.co.ma', 'google.dz', 'google.com.ly',
        'google.tn', 'google.com.et', 'google.sn', 'google.com.na', 'google.co.zw',
        'google.co.bw', 'google.co.mz', 'google.mg', 'google.mu', 'google.com.jm',
        'google.com.pr', 'google.com.do', 'google.com.cu', 'google.com.gt', 'google.com.sv',
        'google.hn', 'google.com.ni', 'google.co.cr', 'google.com.pa', 'google.com.co',
        'google.co.ve', 'google.com.ec', 'google.com.pe', 'google.com.bo', 'google.com.py',
        'google.com.uy', 'google.com.ar', 'google.cl', 'google.com.fj', 'google.co.nz',
        'google.com.sb', 'google.com.vc', 'google.tt', 'google.bs', 'google.dm',
        
        // Google service domains
        'googleusercontent.com', 'googlesyndication.com', 'googleadservices.com',
        'googleapis.com', 'google-analytics.com', 'googletagmanager.com',
        'googlevideo.com', 'gstatic.com', 'google.page.link', 'g.co'
    ];
    
    $social_domains = [
        // Facebook family
        'facebook.com', 'fb.com', 'm.facebook.com', 'l.facebook.com',
        'lm.facebook.com', 'web.facebook.com', 'business.facebook.com',
        
        // Twitter/X
        'twitter.com', 'x.com', 't.co', 'mobile.twitter.com', 'twimg.com',
        
        // Professional networks
        'linkedin.com', 'lnkd.in', 'xing.com', 'glassdoor.com',
        
        // Visual platforms
        'instagram.com', 'pinterest.com', 'pinterest.co.uk', 'pinterest.ca',
        'pinterest.de', 'pinterest.fr', 'flickr.com', 'imgur.com',
        
        // Video platforms
        'youtube.com', 'youtu.be', 'm.youtube.com', 'tiktok.com', 'vimeo.com',
        'dailymotion.com', 'twitch.tv', 'kick.com',
        
        // Messaging/Chat platforms
        'whatsapp.com', 'web.whatsapp.com', 'telegram.org', 't.me',
        'discord.com', 'discord.gg', 'slack.com', 'teams.microsoft.com',
        
        // Reddit ecosystem
        'reddit.com', 'redd.it', 'old.reddit.com', 'np.reddit.com',
        
        // New/Alternative platforms
        'threads.net', 'mastodon.social', 'mastodon.world', 'mastodon.online',
        'bsky.app', 'bsky.social', 'bluesky.social', 'truth.social',
        'gettr.com', 'parler.com', 'gab.com', 'minds.com',
        
        // Regional social networks
        'vk.com', 'vk.ru', 'ok.ru', 'weibo.com', 'weibo.cn',
        'qzone.qq.com', 'douyin.com', 'line.me', 'kakaotalk.com',
        
        // Content platforms
        'medium.com', 'substack.com', 'quora.com', 'tumblr.com',
        'mix.com', 'flipboard.com', 'pocket.com', 'feedly.com',
        
        // Professional/Industry platforms
        'behance.net', 'dribbble.com', 'deviantart.com', 'github.com',
        'stackoverflow.com', 'producthunt.com', 'hackernews.ycombinator.com'
    ];
    
    $click_type = 'direct_clicks';
    $referer_host = '';

    // Whitelist of allowed click types for SQL safety
    $allowed_click_types = ['google_clicks', 'social_clicks', 'direct_clicks'];

    if (!empty($referer)) {
        $referer_parsed = parse_url($referer);
        $referer_host = isset($referer_parsed['host']) ? strtolower($referer_parsed['host']) : '';
        
        // Handle app referrers specially
        if (strpos($referer, 'android-app://') === 0 || strpos($referer, 'fb://') === 0 || 
            strpos($referer, 'twitter://') === 0 || strpos($referer, 'whatsapp://') === 0) {
            $click_type = 'social_clicks';
            // Set special app-based referrer
            if (strpos($referer, 'whatsapp') !== false) {
                $referer = 'whatsapp-app';
            } elseif (strpos($referer, 'fb://') !== false) {
                $referer = 'facebook-app';
            } elseif (strpos($referer, 'twitter://') !== false) {
                $referer = 'twitter-app';
            } elseif (strpos($referer, 'instagram') !== false) {
                $referer = 'instagram-app';
            } elseif (strpos($referer, 'tiktok') !== false) {
                $referer = 'tiktok-app';
            } elseif (strpos($referer, 'linkedin') !== false) {
                $referer = 'linkedin-app';
            } elseif (strpos($referer, 'pinterest') !== false) {
                $referer = 'pinterest-app';
            } elseif (strpos($referer, 'reddit') !== false) {
                $referer = 'reddit-app';
            }
        } else {
            // Check if it's a search engine
            $is_google = false;
            foreach ($google_domains as $domain) {
                if (strpos($referer_host, $domain) !== false) {
                    $is_google = true;
                    break;
                }
            }
            
            // Check for other search engines
            $search_engines = ['bing.com', 'yahoo.com', 'duckduckgo.com', 'baidu.com', 'yandex'];
            $is_search = $is_google;
            
            if (!$is_search) {
                foreach ($search_engines as $engine) {
                    if (strpos($referer_host, $engine) !== false) {
                        $is_search = true;
                        break;
                    }
                }
            }
            
            if ($is_search) {
                $click_type = 'google_clicks';
            } else {
                // Check if it's social media
                foreach ($social_domains as $domain) {
                    if (strpos($referer_host, $domain) !== false) {
                        $click_type = 'social_clicks';
                        break;
                    }
                }
            }
        }
        
        // Sanitize the referrer URL for database storage
        $referer = esc_url_raw($referer);
        $referer = substr($referer, 0, 255); // Limit to field size
    }
    
    // Special handling for common in-app browsers
    if (empty($referer) && !empty($user_agent)) {
        $ua_lower = strtolower($user_agent);
        
        // Check for in-app browsers
        if (strpos($ua_lower, 'fbav') !== false || strpos($ua_lower, 'fb_iab') !== false || 
            strpos($ua_lower, 'fban') !== false) {
            $click_type = 'social_clicks';
            $referer = 'facebook-app';
        } elseif (strpos($ua_lower, 'instagram') !== false) {
            $click_type = 'social_clicks';
            $referer = 'instagram-app';
        } elseif (strpos($ua_lower, 'twitter') !== false) {
            $click_type = 'social_clicks';
            $referer = 'twitter-app';
        } elseif (strpos($ua_lower, 'whatsapp') !== false) {
            $click_type = 'social_clicks';
            $referer = 'whatsapp-app';
        } elseif (strpos($ua_lower, 'linkedin') !== false) {
            $click_type = 'social_clicks';
            $referer = 'linkedin-app';
        } elseif (strpos($ua_lower, 'pinterest') !== false) {
            $click_type = 'social_clicks';
            $referer = 'pinterest-app';
        } elseif (strpos($ua_lower, 'tiktok') !== false) {
            $click_type = 'social_clicks';
            $referer = 'tiktok-app';
        } elseif (strpos($ua_lower, 'reddit') !== false) {
            $click_type = 'social_clicks';
            $referer = 'reddit-app';
        }
    }
    
    if ($debug) {
        error_log('Sky SEO Tracking - Referrer: ' . $referer);
        error_log('Sky SEO Tracking - Referrer Host: ' . $referer_host);
    }
    
    // Determine is_bot value
    $is_bot_value = 0; // 0 = human
    if ($is_bot) {
        $is_bot_value = 1; // 1 = bot
    } elseif ($is_spam) {
        $is_bot_value = 2; // 2 = suspicious/spam
    }
    
    if ($debug) {
        error_log('Sky SEO Tracking - Click type: ' . $click_type);
        error_log('Sky SEO Tracking - Is bot value: ' . $is_bot_value);
    }

    // Use more precise time tracking
    $click_time = current_time('mysql');
    $click_date = current_time('Y-m-d');

    // FIXED: Atomic duplicate prevention using wp_cache_add (prevents race conditions)
    $ip = sky_seo_get_user_ip();
    $user_identifier = md5($ip . '_' . $user_agent);
    $cache_key = 'sky_seo_viewed_' . $post_id . '_' . $user_identifier;
    $current_time = time();

    // Try to add to cache (atomic operation - only succeeds if key doesn't exist)
    $added = wp_cache_add($cache_key, $current_time, 'sky_seo_views', SKY_SEO_DUPLICATE_VIEW_WINDOW);

    if (!$added) {
        // Key already exists - this is a duplicate view
        if ($debug) {
            error_log('Sky SEO Tracking - Duplicate view prevented for post ' . $post_id);
        }
        return; // Don't record duplicate view
    }

    // Also set transient as fallback for non-cached environments
    set_transient($cache_key, $current_time, SKY_SEO_DUPLICATE_VIEW_WINDOW);
    
    // Also check for rapid-fire clicks from same IP (anti-spam)
    // Note: $ip is already defined above from sky_seo_get_user_ip()
    $rate_key = 'sky_seo_click_' . md5($ip . '_' . $post_id);
    $last_click = get_transient($rate_key);
    
    if ($last_click) {
        if ($debug) {
            error_log('Sky SEO Tracking - Rate limit prevented duplicate click');
        }
        return; // Too fast, likely spam
    }
    
    // Set transient to prevent rapid clicks (5 minute cooldown)
    set_transient($rate_key, true, SKY_SEO_RATE_LIMIT_WINDOW);
    
    // Start database transaction for data consistency
    $wpdb->query('START TRANSACTION');

    try {
        // Check if we already have an entry for this post today (with row lock)
        $existing = $wpdb->get_row($wpdb->prepare(
            "SELECT * FROM $table_name WHERE post_id = %d AND DATE(click_time) = %s FOR UPDATE",
            $post_id, $click_date
        ));

        if ($existing) {
            // Validate click type against whitelist for SQL safety
            if (!in_array($click_type, $allowed_click_types, true)) {
                $wpdb->query('ROLLBACK');
                error_log('Sky SEO Tracking - Invalid click type: ' . $click_type);
                return;
            }

            // Build SQL query safely without variable column names
            $update_fields = [
                'clicks = clicks + 1',
                'is_bot = %d',
                'user_agent = %s',
                'country_code = CASE WHEN country_code = \'\' OR country_code IS NULL THEN %s ELSE country_code END',
                'country_name = CASE WHEN country_name = \'\' OR country_name IS NULL THEN %s ELSE country_name END',
                'city_name = CASE WHEN city_name = \'\' OR city_name IS NULL THEN %s ELSE city_name END'
            ];

            // Add specific click type increment safely
            if ($click_type === 'google_clicks') {
                $update_fields[] = 'google_clicks = google_clicks + 1';
            } elseif ($click_type === 'social_clicks') {
                $update_fields[] = 'social_clicks = social_clicks + 1';
            } elseif ($click_type === 'direct_clicks') {
                $update_fields[] = 'direct_clicks = direct_clicks + 1';
            }

            $sql = "UPDATE $table_name SET " . implode(', ', $update_fields) . " WHERE id = %d";

            $prepared_sql = $wpdb->prepare(
                $sql,
                $is_bot_value,
                $user_agent,
                $country_code,
                $country_name,
                $city_name,
                $existing->id
            );

            $result = $wpdb->query($prepared_sql);

            if ($result === false) {
                $wpdb->query('ROLLBACK');
                if ($debug) {
                    error_log('Sky SEO Tracking - Failed to update record: ' . $wpdb->last_error);
                }
                return;
            }

            if ($debug) {
                error_log('Sky SEO Tracking - Updated record ID: ' . $existing->id);
            }
        } else {
            // Validate click type against whitelist for SQL safety
            if (!in_array($click_type, $allowed_click_types, true)) {
                $wpdb->query('ROLLBACK');
                error_log('Sky SEO Tracking - Invalid click type: ' . $click_type);
                return;
            }

            // WPML: Get current post language
            $post_language = '';
            if (defined('ICL_LANGUAGE_CODE')) {
                $post_language = ICL_LANGUAGE_CODE;
            } elseif (function_exists('pll_get_post_language')) {
                $post_language = pll_get_post_language($post_id);
            }

            // Insert new record with all data
            $data = [
                'post_id' => $post_id,
                'clicks' => 1,
                'google_clicks' => ($click_type === 'google_clicks') ? 1 : 0,
                'social_clicks' => ($click_type === 'social_clicks') ? 1 : 0,
                'direct_clicks' => ($click_type === 'direct_clicks') ? 1 : 0,
                'is_bot' => $is_bot_value,
                'user_agent' => $user_agent,
                'referrer_url' => $referer,
                'click_time' => $click_time,
                'country_code' => $country_code,
                'country_name' => $country_name,
                'city_name' => $city_name,
                'post_language' => $post_language,
            ];

            $format = ['%d', '%d', '%d', '%d', '%d', '%d', '%s', '%s', '%s', '%s', '%s', '%s', '%s'];

            $result = $wpdb->insert($table_name, $data, $format);

            if ($result === false) {
                $wpdb->query('ROLLBACK');
                if ($debug) {
                    error_log('Sky SEO Tracking - Failed to insert record: ' . $wpdb->last_error);
                }
                return;
            }

            if ($debug) {
                error_log('Sky SEO Tracking - Inserted new record for post: ' . $post_id);
            }
        }

        // Commit transaction on success
        $wpdb->query('COMMIT');

    } catch (Exception $e) {
        // Rollback on any exception
        $wpdb->query('ROLLBACK');
        error_log('Sky SEO Tracking - Transaction failed: ' . $e->getMessage());
    }
}

// Check if visitor is a bot - IMPROVED VERSION
function sky_seo_is_bot() {
    $user_agent = isset($_SERVER['HTTP_USER_AGENT']) ? strtolower($_SERVER['HTTP_USER_AGENT']) : '';
    
    // Bot patterns - more comprehensive list
    $bot_patterns = [
        // Search engine bots
        'googlebot', 'google-inspectiontool', 'google-site-verification',
        'bingbot', 'msnbot', 'bingpreview',
        'slurp', 'yahoo',
        'duckduckbot', 'duckduckgo',
        'baiduspider', 'baidu',
        'yandexbot', 'yandex',
        'seznambot', 'seznam',
        'facebookexternalhit', 'facebookcatalog',
        'twitterbot',
        'linkedinbot',
        'whatsapp',
        'telegram',
        'applebot',
        
        // SEO tools and crawlers
        'ahrefsbot', 'ahrefs',
        'semrushbot', 'semrush',
        'dotbot', 'moz.com',
        'majesticbot', 'mj12bot',
        'blexbot',
        'serpstatbot',
        'petalbot',
        'aspiegelbot',
        'dataprovider.com',
        
        // Other crawlers
        'crawler', 'spider', 'bot', 'scraper', 'crawling',
        'wget', 'curl', 'python-requests', 'scrapy',
        'phantomjs', 'headless', 'puppeteer', 'selenium',
        
        // Monitoring services
        'pingdom', 'uptimerobot', 'statuscake',
        'nagios', 'zabbix', 'newrelic',
        
        // Feed readers
        'feedly', 'feedburner', 'feedfetcher',
        
        // Preview bots
        'skypeuripreview', 'discordbot',
        'slackbot', 'slack-imgproxy',
        
        // Academic/Research
        'ia_archiver', 'alexa', 'surveybot',
        
        // Security scanners
        'nessus', 'nikto', 'sqlmap', 'openvas',
        
        // Additional patterns
        'go-http-client', 'java/', 'libwww-perl',
        'mechanize', 'zgrab', 'masscan'
    ];
    
    foreach ($bot_patterns as $pattern) {
        if (strpos($user_agent, $pattern) !== false) {
            return true;
        }
    }
    
    // Check for empty user agent (often bots)
    if (empty($user_agent)) {
        return true;
    }
    
    // Additional bot detection methods
    
    // 1. Check for missing expected browser features
    if (isset($_SERVER['HTTP_ACCEPT'])) {
        $accept = strtolower($_SERVER['HTTP_ACCEPT']);
        // Real browsers usually accept HTML
        if (strpos($accept, 'text/html') === false && strpos($accept, '*/*') === false) {
            return true;
        }
    }
    
    // 2. Check for bot-like behavior patterns
    // Most real browsers send these headers
    $expected_headers = ['HTTP_ACCEPT_LANGUAGE', 'HTTP_ACCEPT_ENCODING'];
    $missing_headers = 0;
    
    foreach ($expected_headers as $header) {
        if (!isset($_SERVER[$header]) || empty($_SERVER[$header])) {
            $missing_headers++;
        }
    }
    
    // If multiple expected headers are missing, likely a bot
    if ($missing_headers >= 2) {
        return true;
    }
    
    // 3. Check for known bot IP ranges with caching
    $ip = sky_seo_get_user_ip();
    if (sky_seo_is_bot_ip($ip)) {
        return true;
    }

    return false;
}

// Check if IP belongs to known bot ranges (with caching)
function sky_seo_is_bot_ip($ip) {
    // Cache bot IP check results for 1 hour to avoid repeated CIDR checks
    $cache_key = 'sky_seo_bot_ip_' . md5($ip);
    $cached_result = get_transient($cache_key);

    if ($cached_result !== false) {
        return $cached_result === 'yes';
    }

    // Perform the check
    $is_bot = false;

    // Known bot IP prefix patterns (fast string comparison)
    $bot_ip_patterns = [
        '66.249.', // Googlebot
        '66.102.', // Google
        '64.233.', // Google
        '72.14.',  // Google
        '209.85.', // Google
        '216.239.', // Google
        '64.68.',  // Yahoo
        '67.195.', // Yahoo
        '157.55.', // Bing
        '207.46.', // Bing
        '65.52.',  // Microsoft
        '131.253.', // Microsoft
        '40.77.',  // Microsoft
        '52.167.', // Microsoft Azure
        '13.66.',  // Microsoft Azure
        '54.208.', // Amazon AWS
        '52.44.',  // Amazon AWS
        '52.20.',  // Amazon AWS
        '52.4.',   // Amazon AWS
    ];

    foreach ($bot_ip_patterns as $pattern) {
        if (strpos($ip, $pattern) === 0) {
            $is_bot = true;
            break;
        }
    }

    // Cache result for 1 hour
    set_transient($cache_key, $is_bot ? 'yes' : 'no', SKY_SEO_CACHE_BOT_IP_DURATION);

    return $is_bot;
}

// Basic spam traffic detection
function sky_seo_is_spam_traffic() {
    // Check referrer spam
    $referrer = isset($_SERVER['HTTP_REFERER']) ? strtolower($_SERVER['HTTP_REFERER']) : '';
    
    if (!empty($referrer)) {
        $spam_domains = [
            'semalt.com', 'buttons-for-website.com', 'best-seo-offer.com',
            'best-seo-solution.com', 'simple-share-buttons.com', 'darodar.com',
            'economicnews.trade', 'finance.info', 'free-traffic.xyz',
            'videos-for-your-business.com', 'success-seo.com', 'get-free-traffic-now.com',
            'free-social-buttons.com', 'hulfingtonpost.com', 'о-о-6-о-о.com',
            'humanorightswatch.org', 'reddit.com/r/SEO', 'theguardlan.com',
            'social-buttons.com', 'sharebutton.net', 'soundfrost.org',
            'srecorder.com', 'responsive-test.net', 'savetubevideo.com',
            'semalt.semalt.com', 'forum.topic54443.darodar.com',
            'video--production.com', 'keywords-monitoring-your-success.com',
            'traffic2money.com', 'erot.co', 'lombia.co', 'econom.co',
            'kambasoft.com', 'savetubevideo.com', 'shopping.ilovevitaly.com'
        ];
        
        foreach ($spam_domains as $spam) {
            if (strpos($referrer, $spam) !== false) {
                return true;
            }
        }
    }
    
    // Check for suspicious IP patterns
    $ip = sky_seo_get_user_ip();

    // Check if IP is from known data centers/hosting providers
    // BUT skip this check if user is behind Cloudflare (legitimate users use Cloudflare)
    $is_cloudflare_user = !empty($_SERVER['HTTP_CF_CONNECTING_IP']);
    if (!$is_cloudflare_user && sky_seo_is_datacenter_ip($ip)) {
        return true;
    }
    
    // Check for suspicious behavior patterns
    if (sky_seo_detect_suspicious_behavior()) {
        return true;
    }
    
    // Check for rapid fire requests (more than 10 requests per minute)
    $rate_limit_key = 'sky_seo_rate_' . md5($ip);
    $requests = get_transient($rate_limit_key);
    
    if ($requests === false) {
        set_transient($rate_limit_key, 1, 60); // 60 seconds
    } else {
        if ($requests > SKY_SEO_MAX_REQUESTS_PER_MINUTE) {
            return true; // Too many requests, likely automated
        }
        set_transient($rate_limit_key, $requests + 1, 60);
    }
    
    return false;
}

// Check if IP belongs to known data centers
// Add new function for behavior detection
function sky_seo_detect_suspicious_behavior() {
    // Check for missing expected headers
    $suspicious_score = 0;
    
    // Real browsers send Accept-Language
    if (!isset($_SERVER['HTTP_ACCEPT_LANGUAGE'])) {
        $suspicious_score += 2;
    }
    
    // Real browsers send Accept-Encoding
    if (!isset($_SERVER['HTTP_ACCEPT_ENCODING'])) {
        $suspicious_score += 2;
    }
    
    // Check for DNT header abuse (some bots always send DNT:1)
    if (isset($_SERVER['HTTP_DNT']) && $_SERVER['HTTP_DNT'] === '1' && 
        !isset($_SERVER['HTTP_ACCEPT_LANGUAGE'])) {
        $suspicious_score += 1;
    }
    
    // Check for missing or suspicious Accept header
    if (!isset($_SERVER['HTTP_ACCEPT']) || 
        $_SERVER['HTTP_ACCEPT'] === '*/*' ||
        strlen($_SERVER['HTTP_ACCEPT']) < 10) {
        $suspicious_score += 1;
    }
    
    // Score threshold
    return $suspicious_score >= SKY_SEO_SUSPICIOUS_SCORE_THRESHOLD;
}

function sky_seo_is_datacenter_ip($ip) {
    // Cache datacenter IP check results for 1 hour to avoid repeated CIDR checks
    $cache_key = 'sky_seo_datacenter_ip_' . md5($ip);
    $cached_result = get_transient($cache_key);

    if ($cached_result !== false) {
        return $cached_result === 'yes';
    }

    // Common data center IP ranges (simplified - you might want to use a service)
    $datacenter_ranges = [
        '173.245.48.0/20',   // Cloudflare
        '103.21.244.0/22',   // Cloudflare
        '103.22.200.0/22',   // Cloudflare
        '103.31.4.0/22',     // Cloudflare
        '141.101.64.0/18',   // Cloudflare
        '108.162.192.0/18',  // Cloudflare
        '190.93.240.0/20',   // Cloudflare
        '188.114.96.0/20',   // Cloudflare
        '197.234.240.0/22',  // Cloudflare
        '198.41.128.0/17',   // Cloudflare
        '162.158.0.0/15',    // Cloudflare
        '172.64.0.0/13',     // Cloudflare
        '131.0.72.0/22',     // Cloudflare
        '104.16.0.0/13',     // Cloudflare
        '104.24.0.0/14',     // Cloudflare
        '172.67.0.0/13',     // Cloudflare
        '192.30.252.0/22',   // GitHub
        '185.199.108.0/22',  // GitHub
        '140.82.112.0/20',   // GitHub
        '13.0.0.0/8',        // Amazon AWS
        '52.0.0.0/8',        // Amazon AWS
        '54.0.0.0/8',        // Amazon AWS
        '34.0.0.0/8',        // Google Cloud
        '35.0.0.0/8',        // Google Cloud
        '104.196.0.0/14',    // Google Cloud
        '40.0.0.0/8',        // Microsoft Azure
        '20.0.0.0/8',        // Microsoft Azure
        '51.0.0.0/8',        // Microsoft Azure
        '159.65.0.0/16',     // DigitalOcean
        '161.35.0.0/16',     // DigitalOcean
        '167.172.0.0/16',    // DigitalOcean
        '178.62.0.0/16',     // DigitalOcean
        '198.211.0.0/16',    // Linode
        '192.155.80.0/20',   // Linode
        '50.116.0.0/18',     // Linode
        '45.33.0.0/16',      // Linode
        '23.239.0.0/19',     // Linode
        '104.237.128.0/19',  // Linode
        '172.104.0.0/15',    // Linode
        '139.162.0.0/16',    // Linode
    ];

    $is_datacenter = false;
    foreach ($datacenter_ranges as $range) {
        if (sky_seo_ip_in_range($ip, $range)) {
            $is_datacenter = true;
            break;
        }
    }

    // Cache result for 1 hour
    set_transient($cache_key, $is_datacenter ? 'yes' : 'no', SKY_SEO_CACHE_DATACENTER_IP_DURATION);

    return $is_datacenter;
}

// Check if IP is in CIDR range
function sky_seo_ip_in_range($ip, $cidr) {
    list($subnet, $mask) = explode('/', $cidr);
    $subnet_long = ip2long($subnet);
    $ip_long = ip2long($ip);
    $mask_long = -1 << (32 - $mask);
    $subnet_long &= $mask_long;
    
    return ($ip_long & $mask_long) == $subnet_long;
}

// Get country and city from IP address - IMPROVED VERSION
function sky_seo_get_location_from_ip() {
    $ip = sky_seo_get_user_ip();
    
    // Debug
    $debug = false; // Set to true to enable debug logging
    if ($debug) {
        error_log('Sky SEO Tracking - User IP: ' . $ip);
    }
    
    // Check if we have a cached result
    $cache_key = 'sky_seo_location_' . md5($ip);
    $cached = get_transient($cache_key);
    
    if ($cached !== false) {
        return $cached;
    }
    
    // Default location data
    $location_data = [
        'country_code' => 'XX',
        'country_name' => 'Unknown',
        'city_name' => 'Unknown'
    ];

    // Skip for localhost/private IPs
    if ($ip === '127.0.0.1' || $ip === '::1' || filter_var($ip, FILTER_VALIDATE_IP, FILTER_FLAG_NO_PRIV_RANGE | FILTER_FLAG_NO_RES_RANGE) === false) {
        $location_data = [
            'country_code' => 'LO',
            'country_name' => 'Localhost',
            'city_name' => 'Localhost'
        ];
        set_transient($cache_key, $location_data, HOUR_IN_SECONDS);
        return $location_data;
    }

    // SECURITY FIX: Rate limiting to prevent API quota exhaustion
    // Limit to 40 requests per minute (safely under ip-api.com's 45/min limit)
    $rate_limit_key = 'sky_seo_geo_api_rate';
    $api_calls = get_transient($rate_limit_key);

    if ($api_calls !== false && $api_calls >= 40) {
        // Rate limit exceeded - return unknown but cache it
        if ($debug) {
            error_log('Sky SEO Tracking - Geolocation API rate limit reached, returning cached unknown');
        }
        set_transient($cache_key, $location_data, HOUR_IN_SECONDS);
        return $location_data;
    }

    // Increment rate counter
    if ($api_calls === false) {
        set_transient($rate_limit_key, 1, MINUTE_IN_SECONDS);
    } else {
        set_transient($rate_limit_key, $api_calls + 1, MINUTE_IN_SECONDS);
    }

    // Try multiple geo IP services
    $services = [
        // ip-api.com (no API key required, 45 requests per minute)
        // SECURITY FIX: Changed from HTTP to HTTPS
        [
            'url' => 'https://ip-api.com/json/' . $ip . '?fields=status,countryCode,country,city',
            'parse' => function($data) {
                if ($data && isset($data['status']) && $data['status'] === 'success') {
                    return [
                        'country_code' => $data['countryCode'] ?? 'XX',
                        'country_name' => $data['country'] ?? 'Unknown',
                        'city_name' => $data['city'] ?? 'Unknown'
                    ];
                }
                return false;
            }
        ],
        // ipapi.co (1000 requests per day free)
        [
            'url' => 'https://ipapi.co/' . $ip . '/json/',
            'parse' => function($data) {
                if ($data && isset($data['country_code']) && !isset($data['error'])) {
                    return [
                        'country_code' => $data['country_code'] ?? 'XX',
                        'country_name' => $data['country_name'] ?? 'Unknown',
                        'city_name' => $data['city'] ?? 'Unknown'
                    ];
                }
                return false;
            }
        ],
        // ipwhois.app (10000 requests per month free)
        [
            'url' => 'https://ipwhois.app/json/' . $ip,
            'parse' => function($data) {
                if ($data && isset($data['success']) && $data['success'] === true) {
                    return [
                        'country_code' => $data['country_code'] ?? 'XX',
                        'country_name' => $data['country'] ?? 'Unknown',
                        'city_name' => $data['city'] ?? 'Unknown'
                    ];
                }
                return false;
            }
        ]
    ];
    
    // Try each service until one works
    foreach ($services as $index => $service) {
        // Use new API handler with background timeout and no retry
        // (We'll try next service instead of retrying same service)
        $response = Sky_SEO_API_Request_Handler::request(
            $service['url'],
            [],
            'background',  // Use background timeout (3 seconds)
            false          // No retry - try next service instead
        );

        if (!is_wp_error($response)) {
            $body = Sky_SEO_API_Request_Handler::get_response_body($response);
            if ($body !== null) {
                $data = json_decode($body, true);

                $result = $service['parse']($data);
                if ($result !== false) {
                    // Validate the location data
                    $validated_location = sky_seo_validate_location_data($result);

                    if ($validated_location !== false) {
                        $location_data = $validated_location;
                        if ($debug) {
                            error_log('Sky SEO Tracking - Location detected from service #' . ($index + 1) . ': ' .
                                     $location_data['city_name'] . ', ' . $location_data['country_name']);
                        }
                        break;
                    } else if ($debug) {
                        error_log('Sky SEO Tracking - Invalid location data from service #' . ($index + 1));
                    }
                }
            }
        } else if ($debug) {
            error_log('Sky SEO Tracking - Service #' . ($index + 1) . ' failed: ' . $response->get_error_message());
        }
    }

    // Cache for 30 days (using configured cache duration)
    $cache_duration = Sky_SEO_API_Config::get_cache_duration('geolocation');
    set_transient($cache_key, $location_data, $cache_duration);
    
    return $location_data;
}

/**
 * Validate location data
 */
function sky_seo_validate_location_data($location) {
    // Check country code is valid ISO format
    if (!isset($location['country_code']) || !preg_match('/^[A-Z]{2}$/', $location['country_code'])) {
        return false;
    }
    
    // List of invalid/test country codes
    $invalid_codes = ['XX', 'ZZ', 'AA', 'TEST', '--'];
    if (in_array($location['country_code'], $invalid_codes)) {
        return false;
    }
    
    // Sanitize country name
    if (isset($location['country_name'])) {
        $location['country_name'] = substr(sanitize_text_field($location['country_name']), 0, 100);
        if (empty($location['country_name']) || $location['country_name'] === 'Unknown') {
            $location['country_name'] = $location['country_code']; // Use code as fallback
        }
    }
    
    // Sanitize city name
    if (isset($location['city_name'])) {
        $location['city_name'] = substr(sanitize_text_field($location['city_name']), 0, 100);
        // Remove generic/invalid city names
        $invalid_cities = ['Unknown', 'N/A', 'None', '(Unknown)', '-'];
        if (in_array($location['city_name'], $invalid_cities)) {
            $location['city_name'] = '';
        }
    }
    
    return $location;
}

// Get user IP address - SECURE VERSION with proper proxy verification
function sky_seo_get_user_ip() {
    // SECURITY FIX: Only trust proxy headers from verified sources

    // Check for Cloudflare with IP range verification
    if (!empty($_SERVER['HTTP_CF_CONNECTING_IP'])) {
        // Verify request is actually from Cloudflare
        if (sky_seo_is_cloudflare_request()) {
            $ip = filter_var($_SERVER['HTTP_CF_CONNECTING_IP'], FILTER_VALIDATE_IP);
            if ($ip !== false) {
                return $ip;
            }
        }
    }

    // SECURITY FIX: Don't trust other proxy headers unless verified
    // Only check REMOTE_ADDR directly to prevent IP spoofing
    // If you use a CDN/proxy other than Cloudflare, add verification similar to Cloudflare above

    // Use REMOTE_ADDR (most reliable, cannot be spoofed)
    if (isset($_SERVER['REMOTE_ADDR'])) {
        $ip = filter_var($_SERVER['REMOTE_ADDR'], FILTER_VALIDATE_IP);
        if ($ip !== false) {
            return $ip;
        }
    }

    // Last resort default
    return '127.0.0.1';
}

/**
 * Verify request is from Cloudflare by checking IP ranges
 */
function sky_seo_is_cloudflare_request() {
    if (!isset($_SERVER['REMOTE_ADDR'])) {
        return false;
    }

    $remote_ip = $_SERVER['REMOTE_ADDR'];

    // Cloudflare IPv4 ranges (updated regularly)
    $cloudflare_ipv4_ranges = [
        '173.245.48.0/20',
        '103.21.244.0/22',
        '103.22.200.0/22',
        '103.31.4.0/22',
        '141.101.64.0/18',
        '108.162.192.0/18',
        '190.93.240.0/20',
        '188.114.96.0/20',
        '197.234.240.0/22',
        '198.41.128.0/17',
        '162.158.0.0/15',
        '104.16.0.0/13',
        '104.24.0.0/14',
        '172.64.0.0/13',
        '131.0.72.0/22',
    ];

    // Check if remote IP is in Cloudflare ranges
    foreach ($cloudflare_ipv4_ranges as $range) {
        if (sky_seo_ip_in_range($remote_ip, $range)) {
            return true;
        }
    }

    return false;
}

// Async click verification via AJAX - helps detect JavaScript-enabled browsers
add_action('wp_footer', function() {
    if (is_singular(sky_seo_get_tracked_post_types())) {
        $nonce = wp_create_nonce('sky_seo_verify_human_' . get_the_ID());
        ?>
        <script>
        document.addEventListener('DOMContentLoaded', function() {
            // Send verification after 2 seconds (real users stay on page)
            setTimeout(function() {
                if (typeof jQuery !== 'undefined') {
                    jQuery.post('<?php echo esc_url(admin_url('admin-ajax.php')); ?>', {
                        action: 'sky_seo_verify_human',
                        post_id: <?php echo get_the_ID(); ?>,
                        nonce: '<?php echo esc_js($nonce); ?>'
                    });
                }
            }, 2000);
        });
        </script>
        <?php
    }
});

// AJAX handler for human verification
add_action('wp_ajax_sky_seo_verify_human', 'sky_seo_handle_human_verification');
add_action('wp_ajax_nopriv_sky_seo_verify_human', 'sky_seo_handle_human_verification');

function sky_seo_handle_human_verification() {
    // This confirms JavaScript execution - likely a real browser
    $post_id = isset($_POST['post_id']) ? intval($_POST['post_id']) : 0;

    // Verify nonce for security
    if ($post_id && !wp_verify_nonce($_POST['nonce'] ?? '', 'sky_seo_verify_human_' . $post_id)) {
        wp_die('Security check failed');
    }

    if ($post_id) {
        // This is confirmed human interaction, update the last record if it was marked as unknown
        global $wpdb;
        $table_name = $wpdb->prefix . 'sky_seo_clicks';
        
        // Update the most recent click for this post to be marked as human
        $wpdb->query($wpdb->prepare(
            "UPDATE $table_name 
            SET is_bot = 0 
            WHERE post_id = %d 
            AND click_time >= DATE_SUB(NOW(), INTERVAL 5 MINUTE)
            AND (is_bot IS NULL OR is_bot = 0)
            ORDER BY id DESC 
            LIMIT 1",
            $post_id
        ));
    }
    
    wp_die();
}

// Add Social Meta Tags (Open Graph and Twitter Cards)
function sky_seo_add_social_meta_tags() {
    $settings = get_option('sky_seo_settings', ['active_seo_plugin' => 'none']);
    $active_seo_plugin = $settings['active_seo_plugin'] ?? 'none';
    $tracked_types = sky_seo_get_tracked_post_types();

    if (is_singular($tracked_types) && ($active_seo_plugin === 'none' || !sky_seo_is_seo_plugin_handling_social($active_seo_plugin))) {
        $post_id = get_the_ID();
        $title = get_the_title($post_id);
        $description = get_the_excerpt($post_id) ?: wp_trim_words(get_the_content(null, false, $post_id), 55);
        $url = get_permalink($post_id);
        $image = get_the_post_thumbnail_url($post_id, 'full') ?: get_the_post_thumbnail_url($post_id, 'large');

        // Open Graph tags
        echo '<meta property="og:title" content="' . esc_attr($title) . '">' . "\n";
        echo '<meta property="og:description" content="' . esc_attr($description) . '">' . "\n";
        echo '<meta property="og:url" content="' . esc_url($url) . '">' . "\n";
        echo '<meta property="og:type" content="article">' . "\n";
        if ($image) {
            echo '<meta property="og:image" content="' . esc_url($image) . '">' . "\n";
        }

        // Twitter Card tags
        echo '<meta name="twitter:card" content="summary_large_image">' . "\n";
        echo '<meta name="twitter:title" content="' . esc_attr($title) . '">' . "\n";
        echo '<meta name="twitter:description" content="' . esc_attr($description) . '">' . "\n";
        if ($image) {
            echo '<meta name="twitter:image" content="' . esc_url($image) . '">' . "\n";
        }
    }
}

// Check if SEO plugin handles social meta tags
function sky_seo_is_seo_plugin_handling_social($plugin) {
    switch ($plugin) {
        case 'yoast':
            return defined('WPSEO_VERSION');
        case 'aioseo':
            return defined('AIOSEO_VERSION');
        case 'rankmath':
            return class_exists('RankMath');
        case 'seopress':
            return defined('SEOPRESS_VERSION');
        case 'squirrly':
            return defined('SQ_VERSION');
        default:
            return false;
    }
}

// Get tracked post types
function sky_seo_get_tracked_post_types() {
    // Default post types including page and post
    $default = ['sky_areas', 'sky_trending', 'sky_sectors', 'page', 'post'];
    
    // Add WooCommerce product if available
    if (class_exists('WooCommerce')) {
        $default[] = 'product';
    }
    
    // Get custom tracked types from settings
    $settings = get_option('sky_seo_settings', []);
    $custom = isset($settings['tracked_post_types']) ? $settings['tracked_post_types'] : [];
    
    // Merge and remove duplicates
    $post_types = array_unique(array_merge($default, $custom));
    
    // Apply filter for extensibility
    return apply_filters('sky_seo_tracked_post_types', $post_types);
}

/**
 * WPML Phase 4: Get current language code for schema markup
 * Returns ISO 639-1 language code (e.g., "en", "es", "fr")
 *
 * @return string Language code for schema.org inLanguage property
 */
function sky_seo_get_schema_language() {
    // Try WPML first
    if (defined('ICL_LANGUAGE_CODE')) {
        return ICL_LANGUAGE_CODE;
    }

    // Try Polylang
    if (function_exists('pll_current_language')) {
        return pll_current_language();
    }

    // Fallback to WordPress locale
    $locale = get_locale();

    // Convert locale to ISO 639-1 format (e.g., en_US -> en)
    if (strpos($locale, '_') !== false) {
        return substr($locale, 0, strpos($locale, '_'));
    }

    return 'en'; // Default to English
}

// Add schema markup - WPML Phase 4: Updated with language awareness
function sky_seo_add_schema_markup() {
    $settings = get_option('sky_seo_settings', []);
    $active_seo_plugin = $settings['active_seo_plugin'] ?? 'none';
    $tracked_types = sky_seo_get_tracked_post_types();

    if (is_singular($tracked_types) && ($active_seo_plugin === 'none' || !sky_seo_is_seo_plugin_handling_social($active_seo_plugin))) {
        try {
            $post_id = get_the_ID();
            $post_type = get_post_type($post_id);

            // WPML Phase 4: Get current language for schema
            $language = sky_seo_get_schema_language();

            $schema = [
                '@context' => 'https://schema.org',
                '@type' => 'WebPage',
                'name' => get_the_title($post_id),
                'description' => get_the_excerpt($post_id) ?: wp_trim_words(get_the_content(null, false, $post_id), 55),
                'url' => get_permalink($post_id),
                'inLanguage' => $language, // WPML Phase 4: Language awareness
            ];

            // Add specific schema based on post type
            if ($post_type === 'sky_areas') {
                $schema['coverageArea'] = [
                    '@type' => 'Place',
                    'name' => get_the_title($post_id),
                ];
            } elseif ($post_type === 'sky_trending') {
                $schema['keywords'] = get_the_title($post_id);
            } elseif ($post_type === 'sky_sectors') {
                $schema['about'] = [
                    '@type' => 'Thing',
                    'name' => get_the_title($post_id),
                ];
            } elseif ($post_type === 'product' && class_exists('WooCommerce')) {
                $product = wc_get_product($post_id);
                if ($product) {
                    $schema['@type'] = 'Product';
                    $schema['offers'] = [
                        '@type' => 'Offer',
                        'price' => $product->get_price(),
                        'priceCurrency' => get_woocommerce_currency(),
                        'availability' => $product->is_in_stock() ? 'https://schema.org/InStock' : 'https://schema.org/OutOfStock',
                    ];
                }
            } elseif ($post_type === 'post') {
                $schema['@type'] = 'BlogPosting';
                $schema['datePublished'] = get_the_date('c', $post_id);
                $schema['dateModified'] = get_the_modified_date('c', $post_id);
                $schema['author'] = [
                    '@type' => 'Person',
                    'name' => get_the_author_meta('display_name', get_post_field('post_author', $post_id)),
                ];
            }

            // WPML Phase 4: Add image if available
            $image_url = get_the_post_thumbnail_url($post_id, 'full');
            if ($image_url) {
                $schema['image'] = $image_url;
            }

            echo '<script type="application/ld+json">' . wp_json_encode($schema, JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES) . '</script>' . "\n";
        } catch (Exception $e) {
            error_log('Sky SEO Boost: Error generating schema markup: ' . $e->getMessage());
        }
    }
}

/**
 * WPML Phase 4: Add FAQ Schema Markup with multilingual support
 * Outputs FAQPage schema from the FAQ metabox data
 */
function sky_seo_add_faq_schema() {
    $settings = get_option('sky_seo_settings', []);
    $active_seo_plugin = $settings['active_seo_plugin'] ?? 'none';
    $tracked_types = sky_seo_get_tracked_post_types();

    if (is_singular($tracked_types) && ($active_seo_plugin === 'none' || !sky_seo_is_seo_plugin_handling_social($active_seo_plugin))) {
        try {
            $post_id = get_the_ID();

            // Get FAQ data from post meta (WPML automatically returns translated version)
            $faqs = get_post_meta($post_id, '_sky_seo_faqs', true);

            if (!empty($faqs) && is_array($faqs)) {
                // WPML Phase 4: Get current language for schema
                $language = sky_seo_get_schema_language();

                // Build FAQPage schema
                $faq_schema = [
                    '@context' => 'https://schema.org',
                    '@type' => 'FAQPage',
                    'inLanguage' => $language, // WPML Phase 4: Language awareness
                    'mainEntity' => []
                ];

                foreach ($faqs as $faq) {
                    if (!empty($faq['question']) && !empty($faq['answer'])) {
                        $faq_schema['mainEntity'][] = [
                            '@type' => 'Question',
                            'name' => sanitize_text_field($faq['question']),
                            'acceptedAnswer' => [
                                '@type' => 'Answer',
                                'text' => sanitize_textarea_field($faq['answer'])
                            ]
                        ];
                    }
                }

                // Only output if we have at least one valid FAQ
                if (!empty($faq_schema['mainEntity'])) {
                    echo '<script type="application/ld+json">' . wp_json_encode($faq_schema, JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES) . '</script>' . "\n";
                }
            }
        } catch (Exception $e) {
            error_log('Sky SEO Boost: Error generating FAQ schema: ' . $e->getMessage());
        }
    }
}

/**
 * WPML Phase 4: Add Breadcrumb Schema with multilingual support
 * Generates breadcrumb trail with language-specific URLs
 */
function sky_seo_add_breadcrumb_schema() {
    $settings = get_option('sky_seo_settings', []);
    $active_seo_plugin = $settings['active_seo_plugin'] ?? 'none';
    $tracked_types = sky_seo_get_tracked_post_types();

    // Only output breadcrumbs if no SEO plugin is active (they handle their own)
    if (is_singular($tracked_types) && $active_seo_plugin === 'none') {
        try {
            $post_id = get_the_ID();
            $post_type = get_post_type($post_id);

            // WPML Phase 4: Get current language for schema
            $language = sky_seo_get_schema_language();

            // Build breadcrumb list
            $breadcrumbs = [
                '@context' => 'https://schema.org',
                '@type' => 'BreadcrumbList',
                'inLanguage' => $language, // WPML Phase 4: Language awareness
                'itemListElement' => []
            ];

            // Home page (WPML-compatible URL)
            $breadcrumbs['itemListElement'][] = [
                '@type' => 'ListItem',
                'position' => 1,
                'name' => __('Home', 'sky-seo-boost'),
                'item' => home_url('/')
            ];

            $position = 2;

            // Add post type archive if available (WPML-compatible URL)
            if (in_array($post_type, ['sky_areas', 'sky_trending', 'sky_sectors', 'product'])) {
                $archive_link = get_post_type_archive_link($post_type);

                // WPML: Apply language filter to archive URL
                if (function_exists('apply_filters') && defined('ICL_LANGUAGE_CODE')) {
                    $archive_link = apply_filters('wpml_permalink', $archive_link, ICL_LANGUAGE_CODE);
                }

                $post_type_obj = get_post_type_object($post_type);
                if ($archive_link && $post_type_obj) {
                    $breadcrumbs['itemListElement'][] = [
                        '@type' => 'ListItem',
                        'position' => $position++,
                        'name' => $post_type_obj->labels->name,
                        'item' => $archive_link
                    ];
                }
            }

            // Current page
            $breadcrumbs['itemListElement'][] = [
                '@type' => 'ListItem',
                'position' => $position,
                'name' => get_the_title($post_id),
                'item' => get_permalink($post_id) // WPML automatically provides translated URL
            ];

            echo '<script type="application/ld+json">' . wp_json_encode($breadcrumbs, JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES) . '</script>' . "\n";
        } catch (Exception $e) {
            error_log('Sky SEO Boost: Error generating breadcrumb schema: ' . $e->getMessage());
        }
    }
}

/**
 * WPML Phase 4: Add Organization Schema with multilingual support
 * Outputs on homepage with site information
 */
function sky_seo_add_organization_schema() {
    if (is_front_page()) {
        try {
            // WPML Phase 4: Get current language for schema
            $language = sky_seo_get_schema_language();

            $organization_schema = [
                '@context' => 'https://schema.org',
                '@type' => 'Organization',
                'name' => get_bloginfo('name'),
                'url' => home_url('/'),
                'inLanguage' => $language, // WPML Phase 4: Language awareness
            ];

            // Add logo if available
            $custom_logo_id = get_theme_mod('custom_logo');
            if ($custom_logo_id) {
                $logo_url = wp_get_attachment_image_url($custom_logo_id, 'full');
                if ($logo_url) {
                    $organization_schema['logo'] = $logo_url;
                }
            }

            // Add description if available
            $description = get_bloginfo('description');
            if ($description) {
                $organization_schema['description'] = $description;
            }

            // Add social media profiles if configured in settings
            $settings = get_option('sky_seo_settings', []);
            $social_profiles = [];

            if (!empty($settings['social_facebook'])) {
                $social_profiles[] = $settings['social_facebook'];
            }
            if (!empty($settings['social_twitter'])) {
                $social_profiles[] = $settings['social_twitter'];
            }
            if (!empty($settings['social_linkedin'])) {
                $social_profiles[] = $settings['social_linkedin'];
            }
            if (!empty($settings['social_instagram'])) {
                $social_profiles[] = $settings['social_instagram'];
            }

            if (!empty($social_profiles)) {
                $organization_schema['sameAs'] = $social_profiles;
            }

            echo '<script type="application/ld+json">' . wp_json_encode($organization_schema, JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES) . '</script>' . "\n";
        } catch (Exception $e) {
            error_log('Sky SEO Boost: Error generating organization schema: ' . $e->getMessage());
        }
    }
}

// Enhanced SEO Plugin Support - WITH FIX 3 APPLIED
function sky_seo_register_seo_plugin_support() {
    try {
        $settings = get_option('sky_seo_settings', [
            'active_seo_plugin' => 'none',
            'sitemap_priority_areas' => 0.9,     // CHANGED from 0.8
            'sitemap_priority_trending' => 0.9,  // CHANGED from 0.7
            'sitemap_priority_sectors' => 0.8,   // CHANGED from 0.6
            'sitemap_frequency_areas' => 'daily',    // CHANGED from 'weekly'
            'sitemap_frequency_trending' => 'daily',  // KEPT daily
            'sitemap_frequency_sectors' => 'weekly',  // CHANGED from 'monthly'
        ]);
        $active_seo_plugin = $settings['active_seo_plugin'] ?? 'none';
        $tracked_types = sky_seo_get_tracked_post_types();

        // Sitemap priorities and frequencies - FIX 3 UPDATED VALUES
        $priorities = [
            'sky_areas' => 0.9,      // CHANGED from 0.8
            'sky_trending' => 0.9,   // CHANGED from 0.7
            'sky_sectors' => 0.8,    // CHANGED from 0.6
            'page' => 0.7,
            'post' => 0.6,
            'product' => 0.8,
        ];
        $frequencies = [
            'sky_areas' => 'daily',     // CHANGED from 'weekly'
            'sky_trending' => 'daily',  // KEPT daily
            'sky_sectors' => 'weekly',  // CHANGED from 'monthly'
            'page' => 'monthly',
            'post' => 'weekly',
            'product' => 'daily',
        ];

        if ($active_seo_plugin === 'yoast' && defined('WPSEO_VERSION')) {
            add_filter('wpseo_sitemap_register_post_type', function($sitemap_post_types) use ($tracked_types) {
                return array_merge($sitemap_post_types, $tracked_types);
            });
            add_filter('wpseo_post_type_archive_link', function($link, $post_type) {
                // WPML-compatible: Get translated post type archive URL dynamically
                if (in_array($post_type, ['sky_areas', 'sky_trending', 'sky_sectors'])) {
                    // Use WordPress's built-in function to get archive link
                    $archive_link = get_post_type_archive_link($post_type);

                    // If WPML is active, ensure the URL is in the current language
                    if (function_exists('apply_filters')) {
                        $current_lang = defined('ICL_LANGUAGE_CODE') ? ICL_LANGUAGE_CODE : null;
                        if ($current_lang) {
                            $archive_link = apply_filters('wpml_permalink', $archive_link, $current_lang);
                        }
                    }

                    return $archive_link;
                }

                return $link;
            }, 10, 2);
            add_filter('wpseo_sitemap_entry', function($url, $type) use ($priorities, $frequencies) {
                if (in_array($type, array_keys($priorities))) {
                    $url['priority'] = $priorities[$type] ?? 0.5;
                    $url['changefreq'] = $frequencies[$type] ?? 'weekly';
                }
                return $url;
            }, 10, 2);
            add_filter('wpseo_sitemap_priority', function($priority, $post_type) use ($priorities) {
                return $priorities[$post_type] ?? $priority;
            }, 10, 2);
        } elseif ($active_seo_plugin === 'rankmath' && defined('RANK_MATH_VERSION')) {
            // FIXED: Changed priority from 20 to 5 to run earlier
            add_filter('rank_math/sitemap/post_types', function($sitemap_post_types) use ($tracked_types) {
                foreach ($tracked_types as $pt) {
                    $sitemap_post_types[$pt] = $pt;
                }
                return $sitemap_post_types;
            }, 5);
            add_filter('rank_math/sitemap/post_type_priority', function($priority, $post_type) use ($priorities) {
                return $priorities[$post_type] ?? $priority;
            }, 10, 2);
            add_filter('rank_math/sitemap/post_type_frequency', function($frequency, $post_type) use ($frequencies) {
                return $frequencies[$post_type] ?? $frequency;
            }, 10, 2);
        } elseif ($active_seo_plugin === 'seopress' && defined('SEOPRESS_VERSION')) {
            add_filter('seopress_sitemaps_post_types_list', function($sitemap_post_types) use ($tracked_types) {
                foreach ($tracked_types as $pt) {
                    $sitemap_post_types[$pt] = ['public' => true];
                }
                return $sitemap_post_types;
            });
        } elseif ($active_seo_plugin === 'tsf' && defined('THE_SEO_FRAMEWORK_VERSION')) {
            add_filter('the_seo_framework_sitemap_additional_post_types', function($sitemap_post_types) use ($tracked_types) {
                return array_merge($sitemap_post_types, array_fill_keys($tracked_types, true));
            });
            add_filter('the_seo_framework_sitemap_priority', function($priority, $post_id) use ($priorities) {
                $post_type = get_post_type($post_id);
                return $priorities[$post_type] ?? $priority;
            }, 10, 2);
        } elseif ($active_seo_plugin === 'aioseo' && defined('AIOSEO_VERSION')) {
            add_filter('aioseo_sitemap_post_types', function($sitemap_post_types) use ($tracked_types) {
                return array_merge($sitemap_post_types, $tracked_types);
            });
            add_filter('aioseo_sitemap_priority', function($priority, $post_type) use ($priorities) {
                return $priorities[$post_type] ?? $priority;
            }, 10, 2);
        } elseif ($active_seo_plugin === 'squirrly' && defined('SQ_VERSION')) {
            add_filter('sq_sitemap', function($sitemap) use ($tracked_types) {
                foreach ($tracked_types as $pt) {
                    $sitemap[$pt] = ['enabled' => true, 'priority' => 0.5];
                }
                return $sitemap;
            });
        }

        // Allow other plugins to register support
        do_action('sky_seo_boost_register_seo_support', $tracked_types, $priorities, $frequencies);
    } catch (Exception $e) {
        error_log('Sky SEO Boost: Error registering SEO plugin support: ' . $e->getMessage());
    }
}

/**
 * Immediately ping search engines when custom post types are published
 * NEW FUNCTION - FIX 4
 */
function sky_seo_ping_search_engines_immediately($post_id) {
    $post_type = get_post_type($post_id);

    if (in_array($post_type, ['sky_areas', 'sky_trending', 'sky_sectors'])) {
        $sitemap_url = home_url('/sitemap_index.xml');

        // Ping Google (non-blocking using async request handler)
        Sky_SEO_API_Request_Handler::request_async(
            'https://www.google.com/ping?sitemap=' . urlencode($sitemap_url)
        );

        // Ping Bing (non-blocking using async request handler)
        Sky_SEO_API_Request_Handler::request_async(
            'https://www.bing.com/ping?sitemap=' . urlencode($sitemap_url)
        );

        // Log for debugging
        if (defined('WP_DEBUG') && WP_DEBUG) {
            error_log("Sky SEO: Pinged search engines for {$post_type} post ID {$post_id}");
        }
    }
}

// Hook to all custom post type publishes - FIX 4
add_action('publish_sky_areas', 'sky_seo_ping_search_engines_immediately');
add_action('publish_sky_trending', 'sky_seo_ping_search_engines_immediately');
add_action('publish_sky_sectors', 'sky_seo_ping_search_engines_immediately');

?>