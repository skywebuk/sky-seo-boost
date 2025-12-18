<?php
/**
 * WooCommerce Order UTM Display - IMPROVED VERSION
 * Adds UTM tracking indicators to orders list and order detail pages
 * ONLY for Sky Insights tracked orders
 */

// Prevent direct access
if (!defined('ABSPATH')) {
    exit;
}

class SkyInsightsWCOrderUTMDisplay {
    
    /**
     * Constructor
     */
    public function __construct() {
        // Orders list page - traditional posts storage
        add_filter('manage_edit-shop_order_columns', array($this, 'add_utm_column'), 20);
        add_action('manage_shop_order_posts_custom_column', array($this, 'render_utm_column'), 20, 2);
        
        // Orders list page - HPOS (High Performance Order Storage)
        add_filter('woocommerce_shop_order_list_table_columns', array($this, 'add_utm_column'), 20);
        add_action('woocommerce_shop_order_list_table_custom_column', array($this, 'render_utm_column_hpos'), 20, 2);
        
        // Individual order detail page
        add_action('add_meta_boxes', array($this, 'add_utm_meta_box'), 10);
        
        // Add custom CSS
        add_action('admin_head', array($this, 'add_custom_styles'));
        
        // Add sortable column functionality
        add_filter('manage_edit-shop_order_sortable_columns', array($this, 'make_utm_column_sortable'));
        add_filter('woocommerce_shop_order_list_table_sortable_columns', array($this, 'make_utm_column_sortable'));
        
        // Add filtering capability
        add_action('restrict_manage_posts', array($this, 'add_utm_filter_dropdown'), 20);
        add_filter('request', array($this, 'filter_orders_by_utm_source'));
        
        // Add bulk export action
        add_filter('bulk_actions-edit-shop_order', array($this, 'add_bulk_export_utm_action'));
        add_filter('handle_bulk_actions-edit-shop_order', array($this, 'handle_bulk_export_utm_action'), 10, 3);
    }
    
    /**
     * Add UTM column to orders list
     */
    public function add_utm_column($columns) {
        // Add after 'order_status' column
        $new_columns = array();
        
        foreach ($columns as $key => $value) {
            $new_columns[$key] = $value;
            
            if ($key === 'order_status') {
                $new_columns['utm_tracked'] = __('Campaign', 'sky-insights');
            }
        }
        
        return $new_columns;
    }
    
    /**
     * Make UTM column sortable
     */
    public function make_utm_column_sortable($columns) {
        $columns['utm_tracked'] = 'utm_source';
        return $columns;
    }
    
    /**
     * Add UTM source filter dropdown
     */
    public function add_utm_filter_dropdown() {
        global $typenow, $wpdb;
        
        if ($typenow === 'shop_order') {
            // Get all unique UTM sources
            $sources = $wpdb->get_col("
                SELECT DISTINCT meta_value 
                FROM {$wpdb->postmeta} 
                WHERE meta_key = '_utm_source' 
                AND meta_value != ''
                ORDER BY meta_value
            ");
            
            if (!empty($sources)) {
                $selected = isset($_GET['utm_source_filter']) ? $_GET['utm_source_filter'] : '';
                ?>
                <select name="utm_source_filter" id="utm_source_filter">
                    <option value=""><?php _e('All Campaigns', 'sky-insights'); ?></option>
                    <?php foreach ($sources as $source): ?>
                        <option value="<?php echo esc_attr($source); ?>" <?php selected($selected, $source); ?>>
                            <?php echo esc_html(ucfirst($source)); ?>
                        </option>
                    <?php endforeach; ?>
                </select>
                <?php
            }
        }
    }
    
    /**
     * Filter orders by UTM source
     */
    public function filter_orders_by_utm_source($vars) {
        global $typenow;
        
        if ($typenow === 'shop_order' && isset($_GET['utm_source_filter']) && !empty($_GET['utm_source_filter'])) {
            $vars['meta_key'] = '_utm_source';
            $vars['meta_value'] = sanitize_text_field($_GET['utm_source_filter']);
        }
        
        return $vars;
    }
    
    /**
     * Render UTM column content for traditional storage
     */
    public function render_utm_column($column, $post_id) {
        if ($column === 'utm_tracked') {
            $this->display_utm_indicator($post_id);
        }
    }
    
    /**
     * Render UTM column content for HPOS
     */
    public function render_utm_column_hpos($column, $order) {
        if ($column === 'utm_tracked') {
            $order_id = $order->get_id();
            $this->display_utm_indicator($order_id);
        }
    }
    
    /**
     * Check if order is truly from Sky Insights UTM system
     */
    private function is_sky_insights_utm_order($order) {
        if (!$order) return false;
        
        // Get the link ID from order meta
        $utm_link_id = $order->get_meta('_sky_utm_link_id');
        
        // If no link ID, it's not a Sky Insights order
        if (empty($utm_link_id)) {
            return false;
        }
        
        // IMPORTANT: Verify this link ID actually exists in the Sky Insights UTM table
        global $wpdb;
        $utm_links_table = $wpdb->prefix . 'sky_insights_utm_links';
        
        // Check if table exists first
        if ($wpdb->get_var("SHOW TABLES LIKE '$utm_links_table'") != $utm_links_table) {
            return false;
        }
        
        // Check if the link ID exists and is active
        $link_exists = $wpdb->get_var($wpdb->prepare(
            "SELECT COUNT(*) FROM {$utm_links_table} 
             WHERE id = %d 
             AND is_active = 1",
            $utm_link_id
        ));
        
        // Only return true if the link actually exists in the database
        return ($link_exists > 0);
    }
    
    /**
     * Get campaign data for order
     */
    private function get_campaign_data($order) {
        if (!$order) return null;
        
        // Get all UTM data
        $data = array(
            'source' => $order->get_meta('_utm_source'),
            'medium' => $order->get_meta('_utm_medium'),
            'campaign' => $order->get_meta('_utm_campaign'),
            'term' => $order->get_meta('_utm_term'),
            'link_id' => $order->get_meta('_sky_utm_link_id'),
            'is_sky_insights' => $this->is_sky_insights_utm_order($order)
        );
        
        // Get click and conversion data if available
        if ($data['is_sky_insights'] && $data['link_id']) {
            global $wpdb;
            $clicks_table = $wpdb->prefix . 'sky_insights_utm_clicks';
            
            if ($wpdb->get_var("SHOW TABLES LIKE '$clicks_table'") == $clicks_table) {
                $click_data = $wpdb->get_row($wpdb->prepare(
                    "SELECT clicked_at, device_type, browser, country_code, referrer 
                     FROM {$clicks_table} 
                     WHERE link_id = %d 
                     AND order_id = %d 
                     LIMIT 1",
                    $data['link_id'],
                    $order->get_id()
                ));
                
                if ($click_data) {
                    $data['click_date'] = $click_data->clicked_at;
                    $data['device'] = $click_data->device_type;
                    $data['browser'] = $click_data->browser;
                    $data['country'] = $click_data->country_code;
                    $data['referrer'] = $click_data->referrer;
                }
            }
        }
        
        return $data;
    }
    
    /**
     * Get the source link where the customer clicked from
     */
    private function get_source_link($order, $campaign_data) {
        // First check if we have a referrer from click data
        if (!empty($campaign_data['referrer'])) {
            return $campaign_data['referrer'];
        }
        
        // If no referrer, build expected source based on UTM source
        $utm_source = strtolower($campaign_data['source'] ?? '');
        $utm_medium = strtolower($campaign_data['medium'] ?? '');
        
        // Common referrer patterns
        $source_patterns = array(
            'mailchimp' => 'https://mailchimp.com',
            'facebook' => 'https://www.facebook.com',
            'instagram' => 'https://www.instagram.com',
            'x' => 'https://x.com',
            'twitter' => 'https://twitter.com',
            'linkedin' => 'https://www.linkedin.com',
            'google' => 'https://www.google.com',
            'whatsapp' => 'https://web.whatsapp.com',
            'youtube' => 'https://www.youtube.com',
            'tiktok' => 'https://www.tiktok.com'
        );
        
        // Check if source matches a known pattern
        foreach ($source_patterns as $pattern => $url) {
            if (strpos($utm_source, $pattern) !== false) {
                return $url;
            }
        }
        
        // For email campaigns, check common email providers
        if ($utm_medium === 'email') {
            $email_patterns = array(
                'sendgrid' => 'https://sendgrid.com',
                'sendinblue' => 'https://sendinblue.com',
                'mailgun' => 'https://mailgun.com',
                'constantcontact' => 'https://constantcontact.com',
                'activecampaign' => 'https://activecampaign.com',
                'klaviyo' => 'https://klaviyo.com',
                'getresponse' => 'https://getresponse.com',
                'aweber' => 'https://aweber.com',
                'convertkit' => 'https://convertkit.com',
                'drip' => 'https://drip.com'
            );
            
            foreach ($email_patterns as $pattern => $url) {
                if (strpos($utm_source, $pattern) !== false || strpos($utm_campaign ?? '', $pattern) !== false) {
                    return $url;
                }
            }
            
            // Generic email
            if ($utm_source === 'newsletter' || $utm_source === 'email') {
                return 'Email Campaign';
            }
        }
        
        // For SMS
        if ($utm_medium === 'sms' || $utm_source === 'sms') {
            return 'SMS Campaign';
        }
        
        // Check if link_id exists and get the actual tracking URL
        if (!empty($campaign_data['link_id'])) {
            global $wpdb;
            $links_table = $wpdb->prefix . 'sky_insights_utm_links';
            
            if ($wpdb->get_var("SHOW TABLES LIKE '$links_table'") == $links_table) {
                $short_code = $wpdb->get_var($wpdb->prepare(
                    "SELECT short_code FROM {$links_table} WHERE id = %d",
                    $campaign_data['link_id']
                ));
                
                if ($short_code) {
                    return home_url('?sky_utm=' . $short_code);
                }
            }
        }
        
        return '';
    }
    
    /**
     * Display UTM indicator - IMPROVED VERSION
     */
    private function display_utm_indicator($order_id) {
        $order = wc_get_order($order_id);
        if (!$order) {
            echo '<div style="text-align: center;">-</div>';
            return;
        }
        
        $campaign_data = $this->get_campaign_data($order);
        
        // Only show tick for Sky Insights tracked orders
        if ($campaign_data && $campaign_data['is_sky_insights']) {
            // Get the source from Sky Insights tracking
            $utm_source = $campaign_data['source'];
            $utm_medium = $campaign_data['medium'];
            $utm_campaign = $campaign_data['campaign'];
            $utm_link_id = $campaign_data['link_id'];
            
            // Format the source text
            $source_text = '';
            $tooltip_text = '';
            
            if ($utm_source) {
                // Capitalize first letter
                $source_text = ucfirst(strtolower($utm_source));
                
                // Special formatting for common sources
                $source_map = array(
                    'facebook' => 'Facebook',
                    'instagram' => 'Instagram',
                    'google' => 'Google',
                    'newsletter' => 'Newsletter',
                    'email' => 'Email',
                    'x' => 'X',
                    'twitter' => 'X',
                    'linkedin' => 'LinkedIn',
                    'youtube' => 'YouTube',
                    'tiktok' => 'TikTok',
                    'whatsapp' => 'WhatsApp',
                    'sms' => 'SMS'
                );
                
                $source_lower = strtolower($utm_source);
                if (isset($source_map[$source_lower])) {
                    $source_text = $source_map[$source_lower];
                }
                
                // Build tooltip
                $tooltip_parts = array();
                if ($utm_medium) $tooltip_parts[] = "Medium: {$utm_medium}";
                if ($utm_campaign) $tooltip_parts[] = "Campaign: {$utm_campaign}";
                $tooltip_text = implode(' | ', $tooltip_parts);
            } else {
                $source_text = 'Sky Link #' . $utm_link_id;
            }
            
            echo '<div style="text-align: center;" title="' . esc_attr($tooltip_text) . '">';
            echo '<span class="sky-utm-tick" style="display: inline-block; font-size: 18px; color: #46B450;">✓</span>';
            echo '<div class="sky-utm-source-label sky-utm-' . esc_attr($utm_medium) . '">' . esc_html($source_text) . '</div>';
            echo '</div>';
        } else {
            // Check if has external UTM
            $external_utm = $order->get_meta('_utm_source');
            if (!empty($external_utm)) {
                echo '<div style="text-align: center;" title="External UTM tracking">';
                echo '<div style="font-size: 11px; color: #86868b;">' . esc_html(ucfirst($external_utm)) . '</div>';
                echo '</div>';
            } else {
                echo '<div style="text-align: center; color: #ccc;">-</div>';
            }
        }
    }
    
    /**
     * Add UTM meta box to order detail page
     */
    public function add_utm_meta_box() {
        $screen = class_exists('\Automattic\WooCommerce\Internal\DataStores\Orders\CustomOrdersTableController') 
            && wc_get_container()->get('\Automattic\WooCommerce\Internal\DataStores\Orders\CustomOrdersTableController')->custom_orders_table_usage_is_enabled()
            ? wc_get_page_screen_id('shop-order')
            : 'shop_order';
            
        add_meta_box(
            'sky_insights_utm_tracking',
            __('Campaign Tracking', 'sky-insights'),
            array($this, 'render_utm_meta_box'),
            $screen,
            'side',
            'default'
        );
    }
    
    /**
     * Render UTM meta box content - IMPROVED VERSION
     */
    public function render_utm_meta_box($post_or_order) {
        // Get order object
        $order = ($post_or_order instanceof WP_Post) 
            ? wc_get_order($post_or_order->ID) 
            : $post_or_order;
            
        if (!$order) {
            echo '<p>' . __('Order not found.', 'sky-insights') . '</p>';
            return;
        }
        
        $campaign_data = $this->get_campaign_data($order);
        
        // Check if this is a Sky Insights tracked order
        if ($campaign_data && $campaign_data['is_sky_insights']) {
            ?>
            <div class="sky-utm-tracking-info">
                <div class="sky-utm-status">
                    <svg width="20" height="20" viewBox="0 0 20 20" fill="none" style="vertical-align: middle;">
                        <circle cx="10" cy="10" r="9" fill="#34c759"/>
                        <path d="M6 10L8.5 12.5L14 7" stroke="white" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"/>
                    </svg>
                    <strong style="color: #34c759; margin-left: 5px;">
                        <?php _e('Tracked via Sky Insights', 'sky-insights'); ?>
                    </strong>
                </div>
                
                <table class="sky-utm-details" style="width: 100%; margin-top: 10px;">
                    <?php if ($campaign_data['source']): ?>
                    <tr>
                        <td style="padding: 4px 0;"><strong><?php _e('Source:', 'sky-insights'); ?></strong></td>
                        <td style="padding: 4px 0;"><?php echo esc_html($campaign_data['source']); ?></td>
                    </tr>
                    <?php endif; ?>
                    
                    <?php if ($campaign_data['medium']): ?>
                    <tr>
                        <td style="padding: 4px 0;"><strong><?php _e('Medium:', 'sky-insights'); ?></strong></td>
                        <td style="padding: 4px 0;"><?php echo esc_html($campaign_data['medium']); ?></td>
                    </tr>
                    <?php endif; ?>
                    
                    <?php if ($campaign_data['campaign']): ?>
                    <tr>
                        <td style="padding: 4px 0;"><strong><?php _e('Campaign:', 'sky-insights'); ?></strong></td>
                        <td style="padding: 4px 0;"><?php echo esc_html($campaign_data['campaign']); ?></td>
                    </tr>
                    <?php endif; ?>
                    
                    <?php if ($campaign_data['term']): ?>
                    <tr>
                        <td style="padding: 4px 0;"><strong><?php _e('Term:', 'sky-insights'); ?></strong></td>
                        <td style="padding: 4px 0;"><?php echo esc_html($campaign_data['term']); ?></td>
                    </tr>
                    <?php endif; ?>
                    
                    <?php if ($campaign_data['link_id']): ?>
                    <tr>
                        <td style="padding: 4px 0;"><strong><?php _e('Link ID:', 'sky-insights'); ?></strong></td>
                        <td style="padding: 4px 0;">#<?php echo esc_html($campaign_data['link_id']); ?></td>
                    </tr>
                    <?php endif; ?>
                    
                    <?php if (isset($campaign_data['click_date'])): ?>
                    <tr>
                        <td colspan="2" style="padding: 8px 0 4px 0; border-top: 1px solid #e0e0e0;">
                            <strong><?php _e('Click Details:', 'sky-insights'); ?></strong>
                        </td>
                    </tr>
                    <tr>
                        <td style="padding: 2px 0; font-size: 12px;"><?php _e('Date:', 'sky-insights'); ?></td>
                        <td style="padding: 2px 0; font-size: 12px;"><?php echo date_i18n(get_option('date_format') . ' ' . get_option('time_format'), strtotime($campaign_data['click_date'])); ?></td>
                    </tr>
                    <?php if ($campaign_data['device']): ?>
                    <tr>
                        <td style="padding: 2px 0; font-size: 12px;"><?php _e('Device:', 'sky-insights'); ?></td>
                        <td style="padding: 2px 0; font-size: 12px;"><?php echo esc_html(ucfirst($campaign_data['device'])); ?></td>
                    </tr>
                    <?php endif; ?>
                    <?php if ($campaign_data['browser']): ?>
                    <tr>
                        <td style="padding: 2px 0; font-size: 12px;"><?php _e('Browser:', 'sky-insights'); ?></td>
                        <td style="padding: 2px 0; font-size: 12px;"><?php echo esc_html($campaign_data['browser']); ?></td>
                    </tr>
                    <?php endif; ?>
                    <?php endif; ?>
                    
                    <?php if ($order->get_meta('_sky_utm_conversion_tracked')): ?>
                    <tr>
                        <td colspan="2" style="padding: 8px 0 4px 0; color: #666; font-size: 12px;">
                            <em><?php _e('✓ Conversion tracked in Sky Insights', 'sky-insights'); ?></em>
                        </td>
                    </tr>
                    <?php endif; ?>
                </table>
                
                <?php 
                // Get the referrer/source link
                $source_link = $this->get_source_link($order, $campaign_data);
                if ($source_link): 
                ?>
                <div style="margin-top: 10px; padding-top: 10px; border-top: 1px solid #e0e0e0;">
                    <strong style="font-size: 12px; display: block; margin-bottom: 6px;"><?php _e('Clicked from:', 'sky-insights'); ?></strong>
                    <div style="background: #f7f7f7; padding: 8px; border-radius: 4px; word-break: break-all;">
                        <a href="<?php echo esc_url($source_link); ?>" target="_blank" style="color: #0073aa; text-decoration: none; font-size: 12px;">
                            <?php echo esc_html($source_link); ?>
                        </a>
                    </div>
                </div>
                <?php endif; ?>
            </div>
            <?php
        } else {
            // Not a Sky Insights tracked order
            $general_utm = $order->get_meta('_utm_source');
            
            ?>
            <div class="sky-utm-tracking-info">
                <div class="sky-utm-status">
                    <span style="color: #86868b;">
                        <?php _e('Not tracked via Sky Insights', 'sky-insights'); ?>
                    </span>
                </div>
                <p style="margin-top: 10px; color: #666; font-size: 12px;">
                    <?php 
                    if (!empty($general_utm)) {
                        _e('This order has external UTM parameters but was not tracked through Sky Insights UTM links.', 'sky-insights');
                        echo '<br><br>';
                        echo '<strong>External source:</strong> ' . esc_html($general_utm);
                        
                        // Show all external UTM params if available
                        $external_params = array(
                            'medium' => $order->get_meta('_utm_medium'),
                            'campaign' => $order->get_meta('_utm_campaign'),
                            'term' => $order->get_meta('_utm_term'),
                            'content' => $order->get_meta('_utm_content')
                        );
                        
                        foreach ($external_params as $key => $value) {
                            if (!empty($value)) {
                                echo '<br><strong>' . ucfirst($key) . ':</strong> ' . esc_html($value);
                            }
                        }
                    } else {
                        _e('This order was not tracked through any UTM campaign.', 'sky-insights');
                    }
                    ?>
                </p>
                <?php 
                // Try to determine source for external UTM
                $external_source = '';
                if (!empty($general_utm)) {
                    $external_source = $this->get_source_link($order, array(
                        'source' => $general_utm,
                        'medium' => $order->get_meta('_utm_medium'),
                        'campaign' => $order->get_meta('_utm_campaign')
                    ));
                }
                
                if ($external_source && $external_source !== 'Email Campaign' && $external_source !== 'SMS Campaign'): 
                ?>
                <div style="margin-top: 10px; padding-top: 10px; border-top: 1px solid #e0e0e0;">
                    <strong style="font-size: 12px; display: block; margin-bottom: 6px;"><?php _e('Likely clicked from:', 'sky-insights'); ?></strong>
                    <div style="background: #f7f7f7; padding: 8px; border-radius: 4px; word-break: break-all;">
                        <?php if (strpos($external_source, 'http') === 0): ?>
                            <a href="<?php echo esc_url($external_source); ?>" target="_blank" style="color: #0073aa; text-decoration: none; font-size: 12px;">
                                <?php echo esc_html($external_source); ?>
                            </a>
                        <?php else: ?>
                            <span style="color: #666; font-size: 12px;"><?php echo esc_html($external_source); ?></span>
                        <?php endif; ?>
                    </div>
                </div>
                <?php endif; ?>
                <div style="margin-top: 10px; padding-top: 10px; border-top: 1px solid #e0e0e0;">
                    <a href="<?php echo admin_url('admin.php?page=sky-seo-utm'); ?>" 
                       class="button button-small">
                        <?php _e('Create UTM Links', 'sky-insights'); ?>
                    </a>
                </div>
            </div>
            <?php
        }
    }
    
    /**
     * Add bulk export action
     */
    public function add_bulk_export_utm_action($bulk_actions) {
        $bulk_actions['export_utm_orders'] = __('Export UTM Campaign Data', 'sky-insights');
        return $bulk_actions;
    }
    
    /**
     * Handle bulk export action
     */
    public function handle_bulk_export_utm_action($redirect_to, $action, $post_ids) {
        if ($action !== 'export_utm_orders') {
            return $redirect_to;
        }
        
        // Implement CSV export here
        $this->export_utm_orders_csv($post_ids);
        
        return $redirect_to;
    }
    
    /**
     * Export UTM orders to CSV
     */
    private function export_utm_orders_csv($order_ids) {
        // Set headers for CSV download
        header('Content-Type: text/csv');
        header('Content-Disposition: attachment; filename="utm-orders-' . date('Y-m-d') . '.csv"');
        
        $output = fopen('php://output', 'w');
        
        // CSV headers
        fputcsv($output, array(
            'Order ID',
            'Date',
            'Customer',
            'Total',
            'UTM Source',
            'UTM Medium',
            'UTM Campaign',
            'UTM Term',
            'Sky Insights Tracked',
            'Device',
            'Browser'
        ));
        
        // Export data
        foreach ($order_ids as $order_id) {
            $order = wc_get_order($order_id);
            if (!$order) continue;
            
            $campaign_data = $this->get_campaign_data($order);
            
            fputcsv($output, array(
                $order->get_id(),
                $order->get_date_created()->format('Y-m-d H:i:s'),
                $order->get_billing_email(),
                $order->get_total(),
                $campaign_data['source'] ?? '',
                $campaign_data['medium'] ?? '',
                $campaign_data['campaign'] ?? '',
                $campaign_data['term'] ?? '',
                $campaign_data['is_sky_insights'] ? 'Yes' : 'No',
                $campaign_data['device'] ?? '',
                $campaign_data['browser'] ?? ''
            ));
        }
        
        fclose($output);
        exit;
    }
    
    /**
     * Add custom styles - IMPROVED VERSION
     */
    public function add_custom_styles() {
        ?>
        <style>
        
        /* Orders list styles */
        .column-utm_tracked {
            width: 100px;
            text-align: center;
        }
        
        .sky-utm-tick {
            font-weight: bold;
            line-height: 1;
            padding: 5px;
        }
        
        /* Source label styling */
        .sky-utm-source-label {
            font-size: 11px;
            color: #1976d2;
            margin-top: 3px;
            font-weight: 600;
            text-transform: uppercase;
            letter-spacing: 0.3px;
            background-color: #e3f2fd;
            padding: 2px 6px;
            border-radius: 3px;
            display: inline-block;
            line-height: 1.2;
            cursor: help;
        }
        
        /* Different colors for different mediums */
        .sky-utm-source-label.sky-utm-social {
            background-color: #e7f3ff;
            color: #1877f2;
        }
        
        .sky-utm-source-label.sky-utm-email {
            background-color: #f3e5f5;
            color: #7b1fa2;
        }
        
        .sky-utm-source-label.sky-utm-sms {
            background-color: #e8f5e9;
            color: #2e7d32;
        }
        
        .sky-utm-source-label.sky-utm-cpc {
            background-color: #fff3cd;
            color: #856404;
        }
        
        /* Make the column header centered */
        th.column-utm_tracked {
            text-align: center;
        }
        
        /* Meta box styles */
        .sky-utm-tracking-info {
            padding: 5px;
        }
        
        .sky-utm-details {
            font-size: 13px;
            line-height: 1.5;
        }
        
        .sky-utm-details td {
            vertical-align: top;
        }
        
        #sky_insights_utm_tracking .inside {
            padding: 10px;
        }
        
        /* Responsive */
        @media screen and (max-width: 782px) {
            .column-utm_tracked {
                display: none;
            }
        }
        
        /* Hover effects */
        .sky-utm-source-label:hover {
            transform: translateY(-1px);
            box-shadow: 0 2px 4px rgba(0,0,0,0.1);
        }
        
        /* Filter dropdown styling */
        #utm_source_filter {
            margin-left: 5px;
        }
        </style>
        <?php
    }
}

// Initialize the class
new SkyInsightsWCOrderUTMDisplay();