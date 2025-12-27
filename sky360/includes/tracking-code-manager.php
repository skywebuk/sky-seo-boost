<?php
// Prevent direct access
if (!defined('ABSPATH')) {
    exit;
}

// Track if gtag has already been loaded to avoid duplicates
global $sky_seo_gtag_loaded;
$sky_seo_gtag_loaded = false;

// Inject Tracking Codes
function sky_seo_inject_tracking_codes() {
    global $sky_seo_gtag_loaded;

    $settings = get_option('sky_seo_settings', []);
    $test_mode = !empty($settings['tracking_test_mode']);

    // Only inject codes for admins in test mode
    if ($test_mode && !current_user_can('manage_options')) {
        return;
    }

    // Google Analytics
    if (!empty($settings['ga_measurement_id']) && !empty($settings['ga_enabled'])) {
        $ga_id = esc_attr($settings['ga_measurement_id']);

        // Validate GA4 ID format
        if (!preg_match('/^G-[A-Z0-9]+$/', $settings['ga_measurement_id'])) {
            if (defined('WP_DEBUG') && WP_DEBUG) {
                error_log('Sky SEO Boost: Invalid GA4 Measurement ID format');
            }
        } else {
            $sky_seo_gtag_loaded = true;
            ?>
<!-- Sky SEO Boost: Google Analytics -->
<script async src="<?php echo esc_url('https://www.googletagmanager.com/gtag/js?id=' . $ga_id); ?>"></script>
<script>
  window.dataLayer = window.dataLayer || [];
  function gtag(){dataLayer.push(arguments);}
  gtag('js', new Date());
  gtag('config', '<?php echo esc_js($ga_id); ?>');
</script>
<!-- End Sky SEO Boost: Google Analytics -->
            <?php
        }
    }

    // Google Ads - IMPROVED VERSION
    if (!empty($settings['google_ads_conversion_id']) && !empty($settings['google_ads_enabled'])) {
        $ads_id = esc_attr($settings['google_ads_conversion_id']);
        $conversion_type = $settings['google_ads_conversion_type'] ?? 'woocommerce';
        $conversion_label = isset($settings['google_ads_conversion_label']) ? esc_js($settings['google_ads_conversion_label']) : '';

        // Validate Google Ads ID format
        if (!preg_match('/^AW-[0-9]+$/', $settings['google_ads_conversion_id'])) {
            if (defined('WP_DEBUG') && WP_DEBUG) {
                error_log('Sky SEO Boost: Invalid Google Ads Conversion ID format');
            }
        } else {
            // Only load gtag.js if not already loaded by GA4
            if (!$sky_seo_gtag_loaded) {
                ?>
<!-- Sky SEO Boost: Google Ads -->
<script async src="<?php echo esc_url('https://www.googletagmanager.com/gtag/js?id=' . $ads_id); ?>"></script>
<script>
  window.dataLayer = window.dataLayer || [];
  function gtag(){dataLayer.push(arguments);}
  gtag('js', new Date());
</script>
                <?php
                $sky_seo_gtag_loaded = true;
            }
            ?>
<script>
  gtag('config', '<?php echo esc_js($ads_id); ?>');
</script>
<!-- End Sky SEO Boost: Google Ads -->
            <?php

            // Handle different conversion types
            switch ($conversion_type) {
                case 'woocommerce':
                    // WooCommerce Purchase Tracking
                    if (!empty($conversion_label) && sky_seo_is_order_received_page()) {
                        // Validate order access
                        $order_total = sky_seo_get_order_total();
                        $order_id = sky_seo_get_order_id();
                        $currency = function_exists('get_woocommerce_currency') ? esc_js(get_woocommerce_currency()) : 'USD';

                        if ($order_id) {
                            ?>
<script>
  gtag('event', 'conversion', {
    'send_to': '<?php echo esc_js($ads_id); ?>/<?php echo esc_js($conversion_label); ?>',
    'value': <?php echo floatval($order_total); ?>,
    'currency': '<?php echo $currency; ?>',
    'transaction_id': '<?php echo esc_js($order_id); ?>'
  });
</script>
                            <?php
                        }
                    }
                    break;

                case 'form_submission':
                    // Form Submission Tracking (Thank You Page)
                    if (!empty($conversion_label)) {
                        $thank_you_page_id = $settings['google_ads_thank_you_page_id'] ?? '';
                        $custom_thank_you_url = $settings['google_ads_custom_thank_you_url'] ?? '';
                        $conversion_value = floatval($settings['google_ads_conversion_value'] ?? 0);

                        // Determine if we're on the thank you page
                        $is_thank_you_page = false;

                        // Check custom URL first - sanitize $_SERVER
                        if (!empty($custom_thank_you_url)) {
                            $request_uri = isset($_SERVER['REQUEST_URI']) ? esc_url_raw(wp_unslash($_SERVER['REQUEST_URI'])) : '';
                            $current_path = parse_url($request_uri, PHP_URL_PATH);
                            $custom_thank_you_url = sanitize_text_field($custom_thank_you_url);
                            if ($current_path === $custom_thank_you_url || $current_path === $custom_thank_you_url . '/') {
                                $is_thank_you_page = true;
                            }
                        }
                        // Otherwise check page ID
                        elseif (!empty($thank_you_page_id) && is_page(absint($thank_you_page_id))) {
                            $is_thank_you_page = true;
                        }

                        if ($is_thank_you_page) {
                            ?>
<script>
  gtag('event', 'conversion', {
    'send_to': '<?php echo esc_js($ads_id); ?>/<?php echo esc_js($conversion_label); ?>'<?php if ($conversion_value > 0): ?>,
    'value': <?php echo $conversion_value; ?>,
    'currency': 'USD'<?php endif; ?>
  });
</script>
                            <?php
                        }
                    }
                    break;

                case 'custom':
                    // Custom Event Tracking - Basic implementation
                    if (!empty($conversion_label)) {
                        $conversion_value = floatval($settings['google_ads_conversion_value'] ?? 0);
                        ?>
<script>
  // Sky SEO Boost: Custom conversion ready to be triggered
  // You can trigger this conversion with: skySeotriggerCustomConversion()
  function skySeotriggerCustomConversion() {
    gtag('event', 'conversion', {
      'send_to': '<?php echo esc_js($ads_id); ?>/<?php echo esc_js($conversion_label); ?>'<?php if ($conversion_value > 0): ?>,
      'value': <?php echo $conversion_value; ?>,
      'currency': 'USD'<?php endif; ?>
    });
  }
</script>
                        <?php
                    }
                    break;
            }
        }
    }

    // Google Tag Manager
    if (!empty($settings['gtm_container_id']) && !empty($settings['gtm_enabled'])) {
        $gtm_id = esc_js($settings['gtm_container_id']);

        // Validate GTM ID format
        if (!preg_match('/^GTM-[A-Z0-9]+$/', $settings['gtm_container_id'])) {
            if (defined('WP_DEBUG') && WP_DEBUG) {
                error_log('Sky SEO Boost: Invalid GTM Container ID format');
            }
        } else {
            ?>
<!-- Sky SEO Boost: Google Tag Manager -->
<script>(function(w,d,s,l,i){w[l]=w[l]||[];w[l].push({'gtm.start':
new Date().getTime(),event:'gtm.js'});var f=d.getElementsByTagName(s)[0],
j=d.createElement(s),dl=l!='dataLayer'?'&l='+l:'';j.async=true;j.src=
'https://www.googletagmanager.com/gtm.js?id='+i+dl;f.parentNode.insertBefore(j,f);
})(window,document,'script','dataLayer','<?php echo $gtm_id; ?>');</script>
<!-- End Sky SEO Boost: Google Tag Manager -->
            <?php
        }
    }

    // Meta Pixel (Facebook Pixel)
    if (!empty($settings['meta_pixel_id']) && !empty($settings['meta_pixel_enabled'])) {
        $pixel_id = esc_js($settings['meta_pixel_id']);

        // Validate Pixel ID format (numeric only)
        if (!preg_match('/^[0-9]+$/', $settings['meta_pixel_id'])) {
            if (defined('WP_DEBUG') && WP_DEBUG) {
                error_log('Sky SEO Boost: Invalid Meta Pixel ID format');
            }
        } else {
            ?>
<!-- Sky SEO Boost: Meta Pixel -->
<script>
!function(f,b,e,v,n,t,s)
{if(f.fbq)return;n=f.fbq=function(){n.callMethod?
n.callMethod.apply(n,arguments):n.queue.push(arguments)};
if(!f._fbq)f._fbq=n;n.push=n;n.loaded=!0;n.version='2.0';
n.queue=[];t=b.createElement(e);t.async=!0;
t.src=v;s=b.getElementsByTagName(e)[0];
s.parentNode.insertBefore(t,s)}(window, document,'script',
'https://connect.facebook.net/en_US/fbevents.js');
fbq('init', '<?php echo $pixel_id; ?>');
fbq('track', 'PageView');
</script>
<noscript><img height="1" width="1" style="display:none"
src="<?php echo esc_url('https://www.facebook.com/tr?id=' . $pixel_id . '&ev=PageView&noscript=1'); ?>"
/></noscript>
<!-- End Sky SEO Boost: Meta Pixel -->
            <?php
        }
    }
}

// Hook into wp_head
add_action('wp_head', 'sky_seo_inject_tracking_codes', 1);

// GTM Body Code
function sky_seo_inject_gtm_body() {
    $settings = get_option('sky_seo_settings', []);
    $test_mode = !empty($settings['tracking_test_mode']);

    if ($test_mode && !current_user_can('manage_options')) {
        return;
    }

    if (!empty($settings['gtm_container_id']) && !empty($settings['gtm_enabled'])) {
        // Validate GTM ID format
        if (!preg_match('/^GTM-[A-Z0-9]+$/', $settings['gtm_container_id'])) {
            return;
        }

        $gtm_id = esc_attr($settings['gtm_container_id']);
        ?>
<!-- Sky SEO Boost: Google Tag Manager (noscript) -->
<noscript><iframe src="<?php echo esc_url('https://www.googletagmanager.com/ns.html?id=' . $gtm_id); ?>"
height="0" width="0" style="display:none;visibility:hidden"></iframe></noscript>
<!-- End Sky SEO Boost: Google Tag Manager (noscript) -->
        <?php
    }
}
add_action('wp_body_open', 'sky_seo_inject_gtm_body');

// Helper Functions
function sky_seo_is_order_received_page() {
    if (!function_exists('is_order_received_page')) {
        // Fallback: Check if we're on the order-received endpoint
        global $wp;
        return isset($wp->query_vars['order-received']);
    }
    return is_order_received_page();
}

function sky_seo_get_order_total() {
    if (!function_exists('WC')) {
        return 0;
    }

    global $wp;
    if (isset($wp->query_vars['order-received'])) {
        $order_id = absint($wp->query_vars['order-received']);
        $order = wc_get_order($order_id);

        // Validate order exists and verify order key for security
        if ($order) {
            // Check order key if provided
            $order_key = isset($_GET['key']) ? sanitize_text_field(wp_unslash($_GET['key'])) : '';
            if (!empty($order_key) && $order->get_order_key() !== $order_key) {
                return 0; // Order key mismatch - don't expose data
            }
            return floatval($order->get_total());
        }
    }

    return 0;
}

function sky_seo_get_order_id() {
    if (!function_exists('WC')) {
        return '';
    }

    global $wp;
    if (isset($wp->query_vars['order-received'])) {
        $order_id = absint($wp->query_vars['order-received']);
        $order = wc_get_order($order_id);

        // Validate order exists and verify order key for security
        if ($order) {
            $order_key = isset($_GET['key']) ? sanitize_text_field(wp_unslash($_GET['key'])) : '';
            if (!empty($order_key) && $order->get_order_key() !== $order_key) {
                return ''; // Order key mismatch - don't expose data
            }
            return $order_id;
        }
    }

    return '';
}
