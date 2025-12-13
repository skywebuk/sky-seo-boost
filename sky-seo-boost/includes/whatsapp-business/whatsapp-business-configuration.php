<?php
/**
 * Sky SEO Boost - WhatsApp Business Configuration
 * 
 * Handles configuration settings for WhatsApp Business module
 * 
 * @package Sky_SEO_Boost
 * @subpackage WhatsApp_Business
 * @version 1.3.1
 * @since 3.2.0
 */

// Prevent direct access
if (!defined('ABSPATH')) {
    exit;
}

/**
 * WhatsApp Business Configuration Class
 */
class Sky_SEO_WhatsApp_Configuration {
    
    /**
     * Instance
     */
    private static $instance = null;
    
    /**
     * Option name
     */
    private $option_name = 'sky_seo_whatsapp_config';
    
    /**
     * Get instance
     */
    public static function get_instance() {
        if (self::$instance === null) {
            self::$instance = new self();
        }
        return self::$instance;
    }
    
    /**
     * Constructor
     */
    private function __construct() {
        // Register settings
        add_action('admin_init', [$this, 'register_settings']);
    }
    
    /**
     * Register settings
     */
    public function register_settings() {
        register_setting(
            'sky_seo_whatsapp_settings',
            $this->option_name,
            [$this, 'sanitize_settings']
        );
    }
    
    /**
     * Render configuration page
     */
    public function render_configuration_page() {
        $settings = get_option($this->option_name, $this->get_default_settings());
        ?>
        <div class="sky-seo-analytics-dashboard">
            <!-- Header -->
            <div class="sky-seo-dashboard-header">
                <div class="sky-header-top">
                </div>
            </div>
            
            <form method="post" action="options.php" id="sky-seo-whatsapp-config-form">
                <?php settings_fields('sky_seo_whatsapp_settings'); ?>
                
                <!-- Main Configuration Grid -->
                <div class="sky-seo-config-grid">
                    <!-- WhatsApp Account Settings -->
                    <div class="sky-seo-config-card">
                        <div class="sky-seo-config-card-header">
                            <h3>
                                <span class="dashicons dashicons-admin-users"></span>
                                <?php _e('WhatsApp Account', 'sky-seo-boost'); ?>
                            </h3>
                        </div>
                        
                        <div class="sky-seo-config-card-body">
                            <!-- Enable Widget -->
                            <div class="sky-seo-form-group">
                                <div class="sky-seo-toggle-field">
                                    <label class="sky-seo-toggle-switch">
                                        <input type="checkbox" 
                                               id="widget-enabled"
                                               name="<?php echo $this->option_name; ?>[enabled]" 
                                               value="1" 
                                               <?php checked(!empty($settings['enabled'])); ?>>
                                        <span class="sky-seo-slider"></span>
                                    </label>
                                    <label for="widget-enabled" class="sky-seo-toggle-label">
                                        <?php _e('Enable WhatsApp Widget', 'sky-seo-boost'); ?>
                                    </label>
                                </div>
                            </div>
                            
                            <!-- Phone Number -->
                            <div class="sky-seo-form-group">
                                <label for="whatsapp_phone">
                                    <?php _e('WhatsApp Phone Number', 'sky-seo-boost'); ?>
                                    <span class="required">*</span>
                                </label>
                                <input type="tel" 
                                       id="whatsapp_phone" 
                                       name="<?php echo $this->option_name; ?>[phone]" 
                                       value="<?php echo esc_attr($settings['phone']); ?>" 
                                       placeholder="+447123456789"
                                       pattern="^\+?[1-9]\d{1,14}$"
                                       class="sky-seo-input" 
                                       required>
                                <p class="sky-seo-field-description">
                                    <?php _e('Enter with country code (e.g., +447123456789 for UK)', 'sky-seo-boost'); ?>
                                </p>
                            </div>
                            
                            <!-- Display Name -->
                            <div class="sky-seo-form-group">
                                <label for="display_name">
                                    <?php _e('Display Name', 'sky-seo-boost'); ?>
                                </label>
                                <input type="text" 
                                       id="display_name" 
                                       name="<?php echo $this->option_name; ?>[display_name]" 
                                       value="<?php echo esc_attr($settings['display_name']); ?>" 
                                       placeholder="<?php echo esc_attr(get_bloginfo('name')); ?>"
                                       class="sky-seo-input">
                                <p class="sky-seo-field-description">
                                    <?php _e('The name shown in the WhatsApp popup', 'sky-seo-boost'); ?>
                                </p>
                            </div>
                            
                            <!-- Profile Photo -->
                            <div class="sky-seo-form-group">
                                <label for="profile_photo">
                                    <?php _e('Profile Photo', 'sky-seo-boost'); ?>
                                </label>
                                <div class="sky-seo-media-upload">
                                    <input type="hidden" 
                                           id="profile_photo" 
                                           name="<?php echo $this->option_name; ?>[profile_photo]" 
                                           value="<?php echo esc_attr($settings['profile_photo']); ?>">
                                    <div class="sky-seo-image-preview">
                                        <?php if (!empty($settings['profile_photo'])): ?>
                                            <img src="<?php echo esc_url($settings['profile_photo']); ?>" alt="Profile">
                                        <?php else: ?>
                                            <div class="sky-seo-placeholder">
                                                <span class="dashicons dashicons-format-image"></span>
                                            </div>
                                        <?php endif; ?>
                                    </div>
                                    <div class="sky-seo-upload-actions">
                                        <button type="button" class="button button-secondary upload-button">
                                            <?php _e('Select Image', 'sky-seo-boost'); ?>
                                        </button>
                                        <button type="button" class="button button-link remove-button" 
                                                <?php echo empty($settings['profile_photo']) ? 'style="display:none;"' : ''; ?>>
                                            <?php _e('Remove', 'sky-seo-boost'); ?>
                                        </button>
                                    </div>
                                </div>
                                <p class="sky-seo-field-description">
                                    <?php _e('Recommended: 200 by 200px square image', 'sky-seo-boost'); ?>
                                </p>
                            </div>
                            
                            <!-- Status -->
                            <div class="sky-seo-form-group">
                                <label for="status">
                                    <?php _e('Availability Status', 'sky-seo-boost'); ?>
                                </label>
                                <select id="status" 
                                        name="<?php echo $this->option_name; ?>[status]" 
                                        class="sky-seo-filter-select">
                                    <option value="online" <?php selected($settings['status'], 'online'); ?>>
                                        <?php _e('Online - Available to chat', 'sky-seo-boost'); ?>
                                    </option>
                                    <option value="offline" <?php selected($settings['status'], 'offline'); ?>>
                                        <?php _e('Offline - Currently unavailable', 'sky-seo-boost'); ?>
                                    </option>
                                </select>
                            </div>
                            
                            <!-- Status Text - NEW FIELD -->
                            <div class="sky-seo-form-group">
                                <label for="status_text">
                                    <?php _e('Status Text', 'sky-seo-boost'); ?>
                                </label>
                                <input type="text" 
                                       id="status_text" 
                                       name="<?php echo $this->option_name; ?>[status_text]" 
                                       value="<?php echo esc_attr($settings['status_text'] ?? __('Typically replies instantly', 'sky-seo-boost')); ?>" 
                                       placeholder="<?php _e('Typically replies instantly', 'sky-seo-boost'); ?>"
                                       class="sky-seo-input">
                                <p class="sky-seo-field-description">
                                    <?php _e('Text shown below your name in the popup header', 'sky-seo-boost'); ?>
                                </p>
                            </div>
                            
                            <!-- Verified Badge -->
                            <div class="sky-seo-form-group">
                                <div class="sky-seo-checkbox-field">
                                    <label>
                                        <input type="checkbox" 
                                               name="<?php echo $this->option_name; ?>[show_verified]" 
                                               value="1" 
                                               <?php checked(!empty($settings['show_verified'])); ?>>
                                        <?php _e('Show Verified Badge', 'sky-seo-boost'); ?>
                                    </label>
                                    <p class="sky-seo-field-description">
                                        <?php _e('Display a blue checkmark next to your business name', 'sky-seo-boost'); ?>
                                    </p>
                                </div>
                            </div>
                        </div>
                    </div>
                    
                    <!-- Message Settings -->
                    <div class="sky-seo-config-card">
                        <div class="sky-seo-config-card-header">
                            <h3>
                                <span class="dashicons dashicons-format-chat"></span>
                                <?php _e('Message Settings', 'sky-seo-boost'); ?>
                            </h3>
                        </div>
                        
                        <div class="sky-seo-config-card-body">
                            <!-- Welcome Message -->
                            <div class="sky-seo-form-group">
                                <label for="description">
                                    <?php _e('Welcome Message', 'sky-seo-boost'); ?>
                                </label>
                                <textarea id="description" 
                                          name="<?php echo $this->option_name; ?>[description]" 
                                          rows="4" 
                                          class="sky-seo-textarea"
                                          placeholder="<?php _e('Hi there! How can I help you?', 'sky-seo-boost'); ?>"><?php echo esc_textarea($settings['description']); ?></textarea>
                                <p class="sky-seo-field-description">
                                    <?php _e('Message shown in the popup before users start a chat', 'sky-seo-boost'); ?>
                                </p>
                            </div>
                            
                            <!-- Default Chat Message -->
                            <div class="sky-seo-form-group">
                                <label for="default_message">
                                    <?php _e('Pre-filled Chat Message', 'sky-seo-boost'); ?>
                                </label>
                                <textarea id="default_message" 
                                          name="<?php echo $this->option_name; ?>[default_message]" 
                                          rows="3" 
                                          class="sky-seo-textarea"
                                          placeholder="<?php _e('Hello, I would like to know more about your services.', 'sky-seo-boost'); ?>"><?php echo esc_textarea($settings['default_message'] ?? ''); ?></textarea>
                                <p class="sky-seo-field-description">
                                    <?php _e('This message will be pre-filled when users start a chat', 'sky-seo-boost'); ?>
                                </p>
                            </div>
                            
                            <!-- Start Chat Button Text - NEW FIELD -->
                            <div class="sky-seo-form-group">
                                <label for="start_chat_text">
                                    <?php _e('Start Chat Button Text', 'sky-seo-boost'); ?>
                                </label>
                                <input type="text" 
                                       id="start_chat_text" 
                                       name="<?php echo $this->option_name; ?>[start_chat_text]" 
                                       value="<?php echo esc_attr($settings['start_chat_text'] ?? __('Start Chat', 'sky-seo-boost')); ?>" 
                                       placeholder="<?php _e('Start Chat', 'sky-seo-boost'); ?>"
                                       class="sky-seo-input">
                                <p class="sky-seo-field-description">
                                    <?php _e('Text shown on the start chat button in the popup', 'sky-seo-boost'); ?>
                                </p>
                            </div>
                        </div>
                    </div>
                </div>
                
                <!-- Trackable Link -->
                <div class="sky-seo-trackable-link-card">
                    <div class="sky-seo-trackable-link-header">
                        <h3>
                            <span class="dashicons dashicons-admin-links"></span>
                            <?php _e('Trackable WhatsApp Link', 'sky-seo-boost'); ?>
                        </h3>
                    </div>
                    
                    <div class="sky-seo-trackable-link-body">
                        <p class="sky-seo-field-description" style="margin-bottom: 20px;">
                            <?php _e('Use this permanent link on any button or link element on your website. All clicks will be tracked as "Button Click" in the analytics with the page information.', 'sky-seo-boost'); ?>
                        </p>
                        
                        <?php 
                        // Generate the permanent trackable link
                        $trackable_link = add_query_arg([
                            'sky_whatsapp_button' => '1'
                        ], home_url());
                        ?>
                        
                        <!-- Always Visible Link Display -->
                        <div class="sky-seo-generated-link">
                            <label><?php _e('Your Trackable WhatsApp Link:', 'sky-seo-boost'); ?></label>
                            <div class="sky-seo-link-display">
                                <input type="text" 
                                       id="trackable-link" 
                                       class="sky-seo-input" 
                                       value="<?php echo esc_url($trackable_link); ?>"
                                       readonly>
                                <button type="button" id="copy-trackable-link" class="button button-secondary">
                                    <?php _e('Copy', 'sky-seo-boost'); ?>
                                </button>
                            </div>
                        </div>
                    </div>
                </div>
                
                <!-- Widget Behavior Settings -->
                <div class="sky-seo-config-card">
                    <div class="sky-seo-config-card-header">
                        <h3>
                            <span class="dashicons dashicons-admin-settings"></span>
                            <?php _e('Widget Behavior', 'sky-seo-boost'); ?>
                        </h3>
                    </div>
                    
                    <div class="sky-seo-config-card-body">
                        <!-- Position -->
                        <div class="sky-seo-form-group">
                            <label><?php _e('Widget Position', 'sky-seo-boost'); ?></label>
                            <div class="sky-seo-position-selector">
                                <label class="sky-seo-position-option">
                                    <input type="radio" 
                                           name="<?php echo $this->option_name; ?>[float_position]" 
                                           value="bottom-left" 
                                           <?php checked($settings['float_position'], 'bottom-left'); ?>>
                                    <div class="sky-seo-position-preview">
                                        <span class="sky-seo-position-label"><?php _e('Bottom Left', 'sky-seo-boost'); ?></span>
                                    </div>
                                </label>
                                
                                <label class="sky-seo-position-option">
                                    <input type="radio" 
                                           name="<?php echo $this->option_name; ?>[float_position]" 
                                           value="bottom-right" 
                                           <?php checked($settings['float_position'], 'bottom-right'); ?>>
                                    <div class="sky-seo-position-preview">
                                        <span class="sky-seo-position-label"><?php _e('Bottom Right', 'sky-seo-boost'); ?></span>
                                    </div>
                                </label>
                            </div>
                        </div>
                        
                        <!-- Popup Settings -->
                        <div class="sky-seo-form-group">
                            <label><?php _e('Popup Settings', 'sky-seo-boost'); ?></label>
                            
                            <div class="sky-seo-checkbox-field">
                                <label>
                                    <input type="checkbox" 
                                           name="<?php echo $this->option_name; ?>[show_popup]" 
                                           value="1" 
                                           <?php checked(!empty($settings['show_popup'])); ?>>
                                    <?php _e('Show Welcome Popup on Click', 'sky-seo-boost'); ?>
                                </label>
                            </div>
                            
                            <div class="sky-seo-inline-field" style="margin-top: 15px;">
                                <label for="popup_delay" style="margin-right: 10px;">
                                    <?php _e('Auto-show popup after', 'sky-seo-boost'); ?>
                                </label>
                                <input type="number" 
                                       id="popup_delay" 
                                       name="<?php echo $this->option_name; ?>[popup_delay]" 
                                       value="<?php echo esc_attr($settings['popup_delay'] ?? 0); ?>" 
                                       min="0" 
                                       max="300" 
                                       class="sky-seo-input-small">
                                <span><?php _e('seconds', 'sky-seo-boost'); ?></span>
                            </div>
                            <p class="sky-seo-field-description">
                                <?php _e('Set to 0 to disable auto-popup. Maximum: 300 seconds', 'sky-seo-boost'); ?>
                            </p>
                        </div>
                        
                        <!-- Device Visibility -->
                        <div class="sky-seo-form-group">
                            <label><?php _e('Device Visibility', 'sky-seo-boost'); ?></label>
                            <div class="sky-seo-checkbox-group">
                                <label>
                                    <input type="checkbox" 
                                           name="<?php echo $this->option_name; ?>[show_on_mobile]" 
                                           value="1" 
                                           <?php checked($settings['show_on_mobile'] ?? true, true); ?>>
                                    <?php _e('Show on Mobile', 'sky-seo-boost'); ?>
                                </label>
                                
                                <label>
                                    <input type="checkbox" 
                                           name="<?php echo $this->option_name; ?>[show_on_desktop]" 
                                           value="1" 
                                           <?php checked($settings['show_on_desktop'] ?? true, true); ?>>
                                    <?php _e('Show on Desktop', 'sky-seo-boost'); ?>
                                </label>
                            </div>
                        </div>
                    </div>
                </div>
                
                <!-- Display Rules -->
                <div class="sky-seo-config-card">
                    <div class="sky-seo-config-card-header">
                        <h3>
                            <span class="dashicons dashicons-visibility"></span>
                            <?php _e('Display Rules', 'sky-seo-boost'); ?>
                        </h3>
                    </div>
                    
                    <div class="sky-seo-config-card-body">
                        <!-- Page Type Selection -->
                        <div class="sky-seo-form-group">
                            <label><?php _e('Show Widget On', 'sky-seo-boost'); ?></label>
                            <div class="sky-seo-page-types-grid">
                                <label class="sky-seo-page-type-option">
                                    <input type="checkbox" 
                                           name="<?php echo $this->option_name; ?>[show_on_home]" 
                                           value="1" 
                                           <?php checked($settings['show_on_home'] ?? true, true); ?>>
                                    <span><?php _e('Homepage', 'sky-seo-boost'); ?></span>
                                </label>
                                
                                <label class="sky-seo-page-type-option">
                                    <input type="checkbox" 
                                           name="<?php echo $this->option_name; ?>[show_on_pages]" 
                                           value="1" 
                                           <?php checked($settings['show_on_pages'] ?? true, true); ?>>
                                    <span><?php _e('Pages', 'sky-seo-boost'); ?></span>
                                </label>
                                
                                <label class="sky-seo-page-type-option">
                                    <input type="checkbox" 
                                           name="<?php echo $this->option_name; ?>[show_on_posts]" 
                                           value="1" 
                                           <?php checked($settings['show_on_posts'] ?? true, true); ?>>
                                    <span><?php _e('Posts', 'sky-seo-boost'); ?></span>
                                </label>
                                
                                <?php if (class_exists('WooCommerce')): ?>
                                <label class="sky-seo-page-type-option">
                                    <input type="checkbox" 
                                           name="<?php echo $this->option_name; ?>[show_on_products]" 
                                           value="1" 
                                           <?php checked($settings['show_on_products'] ?? true, true); ?>>
                                    <span><?php _e('Products', 'sky-seo-boost'); ?></span>
                                </label>
                                <?php endif; ?>
                                
                                <label class="sky-seo-page-type-option">
                                    <input type="checkbox" 
                                           name="<?php echo $this->option_name; ?>[show_on_sky_areas]" 
                                           value="1" 
                                           <?php checked($settings['show_on_sky_areas'] ?? true, true); ?>>
                                    <span><?php _e('Areas', 'sky-seo-boost'); ?></span>
                                </label>
                                
                                <label class="sky-seo-page-type-option">
                                    <input type="checkbox" 
                                           name="<?php echo $this->option_name; ?>[show_on_sky_trending]" 
                                           value="1" 
                                           <?php checked($settings['show_on_sky_trending'] ?? true, true); ?>>
                                    <span><?php _e('Trending', 'sky-seo-boost'); ?></span>
                                </label>
                                
                                <label class="sky-seo-page-type-option">
                                    <input type="checkbox" 
                                           name="<?php echo $this->option_name; ?>[show_on_sky_sectors]" 
                                           value="1" 
                                           <?php checked($settings['show_on_sky_sectors'] ?? true, true); ?>>
                                    <span><?php _e('Sectors', 'sky-seo-boost'); ?></span>
                                </label>
                            </div>
                        </div>
                        
                        <!-- Exclude Pages -->
                        <div class="sky-seo-form-group">
                            <label for="exclude_pages">
                                <?php _e('Exclude Specific Pages', 'sky-seo-boost'); ?>
                            </label>
                            <select id="exclude_pages" 
                                    name="<?php echo $this->option_name; ?>[exclude_pages][]" 
                                    multiple 
                                    class="sky-seo-select2" 
                                    data-placeholder="<?php _e('Select pages to exclude...', 'sky-seo-boost'); ?>">
                                <?php
                                $pages = get_pages(['hierarchical' => true, 'sort_column' => 'menu_order, post_title']);
                                $excluded = $settings['exclude_pages'] ?? [];
                                
                                foreach ($pages as $page) {
                                    $depth = count(get_ancestors($page->ID, 'page'));
                                    $indent = str_repeat('— ', $depth);
                                    $selected = in_array($page->ID, $excluded) ? 'selected' : '';
                                    echo '<option value="' . $page->ID . '" ' . $selected . '>' . 
                                         $indent . esc_html($page->post_title) . '</option>';
                                }
                                ?>
                            </select>
                            <p class="sky-seo-field-description">
                                <?php _e('Select pages where the WhatsApp widget should not appear', 'sky-seo-boost'); ?>
                            </p>
                        </div>
                    </div>
                </div>
                
                <!-- Save Button -->
                <div class="sky-seo-form-actions">
                    <button type="submit" name="submit" class="button button-primary button-large">
                        <span class="dashicons dashicons-saved"></span>
                        <?php _e('Save Settings', 'sky-seo-boost'); ?>
                    </button>
                    <span class="spinner"></span>
                    <span class="sky-seo-success-message" style="display:none;">
                        <span class="dashicons dashicons-yes-alt"></span>
                        <?php _e('Settings saved successfully!', 'sky-seo-boost'); ?>
                    </span>
                </div>
            </form>
        </div>
        <?php
    }
    
    /**
     * Generate trackable WhatsApp link
     */
    public function generate_trackable_link($message = '', $source = '') {
        $settings = get_option($this->option_name, []);
        $phone = $settings['phone'] ?? '';
        
        if (empty($phone)) {
            return '';
        }
        
        // Clean phone number
        $clean_phone = preg_replace('/[^0-9+]/', '', $phone);
        
        // Use custom message or default
        $chat_message = !empty($message) ? $message : ($settings['default_message'] ?? '');
        
        // Generate unique tracking ID
        $tracking_id = wp_generate_uuid4();
        
        // Store tracking info in transient (30 days)
        set_transient('sky_whatsapp_link_' . $tracking_id, [
            'source' => sanitize_text_field($source),
            'message' => $chat_message,
            'created' => current_time('mysql'),
        ], 30 * DAY_IN_SECONDS);
        
        // Build WhatsApp URL
        $whatsapp_url = add_query_arg([
            'phone' => $clean_phone,
            'text' => urlencode($chat_message),
        ], 'https://wa.me/');
        
        // Create trackable redirect URL
        $track_url = add_query_arg([
            'sky_whatsapp_redirect' => 1,
            'tid' => $tracking_id,
            'source' => urlencode($source),
        ], home_url());
        
        return $track_url;
    }
    
    /**
     * AJAX handler for generating WhatsApp link
     */
    public function ajax_generate_whatsapp_link() {
        // Check nonce
        if (!check_ajax_referer('sky_seo_whatsapp_nonce', 'nonce', false)) {
            wp_send_json_error(['message' => __('Security check failed', 'sky-seo-boost')]);
        }
        
        // Check capabilities
        if (!current_user_can('manage_options')) {
            wp_send_json_error(['message' => __('Permission denied', 'sky-seo-boost')]);
        }
        
        $message = sanitize_textarea_field($_POST['message'] ?? '');
        $source = sanitize_text_field($_POST['source'] ?? '');
        
        $link = $this->generate_trackable_link($message, $source);
        
        if (empty($link)) {
            wp_send_json_error(['message' => __('Please configure WhatsApp phone number first', 'sky-seo-boost')]);
        }
        
        wp_send_json_success([
            'link' => $link,
            'message' => __('Trackable link generated successfully!', 'sky-seo-boost')
        ]);
    }
    
    /**
     * Get default settings
     */
    private function get_default_settings() {
        return [
            'enabled' => false,
            'phone' => '',
            'display_name' => get_bloginfo('name'),
            'profile_photo' => '',
            'status' => 'online',
            'status_text' => __('Typically replies instantly', 'sky-seo-boost'),
            'description' => __('Hi there! How can I help you?', 'sky-seo-boost'),
            'show_verified' => false,
            'float_position' => 'bottom-right',
            'show_popup' => true,
            'popup_delay' => 0,
            'default_message' => '',
            'start_chat_text' => __('Start Chat', 'sky-seo-boost'),
            'show_on_mobile' => true,
            'show_on_desktop' => true,
            'show_on_home' => true,
            'show_on_pages' => true,
            'show_on_posts' => true,
            'show_on_products' => true,
            'show_on_sky_areas' => true,
            'show_on_sky_trending' => true,
            'show_on_sky_sectors' => true,
            'exclude_pages' => [],
        ];
    }
    
    /**
     * Sanitize settings
     */
    public function sanitize_settings($input) {
        $sanitized = [];
        
        // Boolean fields
        $boolean_fields = [
            'enabled', 'show_verified', 'show_popup', 
            'show_on_mobile', 'show_on_desktop',
            'show_on_home', 'show_on_pages', 'show_on_posts',
            'show_on_products', 'show_on_sky_areas', 
            'show_on_sky_trending', 'show_on_sky_sectors'
        ];
        
        foreach ($boolean_fields as $field) {
            $sanitized[$field] = !empty($input[$field]);
        }
        
        // Phone validation
        $phone = sanitize_text_field($input['phone'] ?? '');
        if (!empty($phone)) {
            // Remove all non-digit characters except +
            $clean_phone = preg_replace('/[^0-9+]/', '', $phone);
            
            // Validate E.164 format
            if (!preg_match('/^\+?[1-9]\d{1,14}$/', $clean_phone)) {
                add_settings_error(
                    $this->option_name,
                    'invalid_phone',
                    __('Please enter a valid WhatsApp phone number with country code (e.g., +447123456789).', 'sky-seo-boost'),
                    'error'
                );
                $sanitized['phone'] = '';
            } else {
                $sanitized['phone'] = $clean_phone;
            }
        } else {
            $sanitized['phone'] = '';
        }
        
        // Text fields
        $sanitized['display_name'] = sanitize_text_field($input['display_name'] ?? '');
        $sanitized['profile_photo'] = esc_url_raw($input['profile_photo'] ?? '');
        $sanitized['status'] = in_array($input['status'] ?? 'online', ['online', 'offline']) 
            ? $input['status'] 
            : 'online';
        $sanitized['status_text'] = sanitize_text_field($input['status_text'] ?? __('Typically replies instantly', 'sky-seo-boost'));
        $sanitized['start_chat_text'] = sanitize_text_field($input['start_chat_text'] ?? __('Start Chat', 'sky-seo-boost'));
        
        // Textarea fields
        $sanitized['description'] = sanitize_textarea_field($input['description'] ?? '');
        $sanitized['default_message'] = sanitize_textarea_field($input['default_message'] ?? '');
        
        // Select fields
        $sanitized['float_position'] = in_array($input['float_position'] ?? 'bottom-right', ['bottom-left', 'bottom-right']) 
            ? $input['float_position'] 
            : 'bottom-right';
        
        // Number fields
        $sanitized['popup_delay'] = absint($input['popup_delay'] ?? 0);
        if ($sanitized['popup_delay'] > 300) {
            $sanitized['popup_delay'] = 300;
        }
        
        // Array fields
        if (isset($input['exclude_pages']) && is_array($input['exclude_pages'])) {
            $sanitized['exclude_pages'] = array_map('absint', $input['exclude_pages']);
        } else {
            $sanitized['exclude_pages'] = [];
        }
        
        return $sanitized;
    }
    
    /**
     * Save configuration via AJAX
     */
    public function save_configuration() {
        // Check nonce
        if (!check_ajax_referer('sky_seo_whatsapp_nonce', 'nonce', false)) {
            wp_send_json_error(['message' => __('Security check failed', 'sky-seo-boost')]);
        }
        
        // Check capabilities
        if (!current_user_can('manage_options')) {
            wp_send_json_error(['message' => __('Permission denied', 'sky-seo-boost')]);
        }
        
        try {
            // Parse form data
            parse_str($_POST['form_data'] ?? '', $form_data);
            
            // Get settings from form data
            $settings = $form_data[$this->option_name] ?? [];
            
            // Sanitize and save
            $sanitized = $this->sanitize_settings($settings);
            $result = update_option($this->option_name, $sanitized);
            
            if ($result !== false) {
                wp_send_json_success([
                    'message' => __('Settings saved successfully!', 'sky-seo-boost'),
                    'settings' => $sanitized
                ]);
            } else {
                // Check if values actually changed
                $current = get_option($this->option_name);
                if ($current === $sanitized) {
                    wp_send_json_success([
                        'message' => __('Settings saved successfully!', 'sky-seo-boost'),
                        'settings' => $sanitized
                    ]);
                } else {
                    wp_send_json_error([
                        'message' => __('Failed to save settings. Please try again.', 'sky-seo-boost')
                    ]);
                }
            }
            
        } catch (Exception $e) {
            wp_send_json_error([
                'message' => sprintf(__('Error: %s', 'sky-seo-boost'), $e->getMessage())
            ]);
        }
    }
}