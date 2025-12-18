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
                                <?php esc_html_e('WhatsApp Account', 'sky-seo-boost'); ?>
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
                                        <?php esc_html_e('Enable WhatsApp Widget', 'sky-seo-boost'); ?>
                                    </label>
                                </div>
                            </div>
                            
                            <!-- Phone Number -->
                            <div class="sky-seo-form-group">
                                <label for="whatsapp_phone">
                                    <?php esc_html_e('WhatsApp Phone Number', 'sky-seo-boost'); ?>
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
                                    <?php esc_html_e('Enter with country code (e.g., +447123456789 for UK)', 'sky-seo-boost'); ?>
                                </p>
                            </div>
                            
                            <!-- Display Name -->
                            <div class="sky-seo-form-group">
                                <label for="display_name">
                                    <?php esc_html_e('Display Name', 'sky-seo-boost'); ?>
                                </label>
                                <input type="text" 
                                       id="display_name" 
                                       name="<?php echo $this->option_name; ?>[display_name]" 
                                       value="<?php echo esc_attr($settings['display_name']); ?>" 
                                       placeholder="<?php echo esc_attr(get_bloginfo('name')); ?>"
                                       class="sky-seo-input">
                                <p class="sky-seo-field-description">
                                    <?php esc_html_e('The name shown in the WhatsApp popup', 'sky-seo-boost'); ?>
                                </p>
                            </div>

                            <!-- Profile Photo -->
                            <div class="sky-seo-form-group">
                                <label for="profile_photo">
                                    <?php esc_html_e('Profile Photo', 'sky-seo-boost'); ?>
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
                                            <?php esc_html_e('Select Image', 'sky-seo-boost'); ?>
                                        </button>
                                        <button type="button" class="button button-link remove-button"
                                                <?php echo empty($settings['profile_photo']) ? 'style="display:none;"' : ''; ?>>
                                            <?php esc_html_e('Remove', 'sky-seo-boost'); ?>
                                        </button>
                                    </div>
                                </div>
                                <p class="sky-seo-field-description">
                                    <?php esc_html_e('Recommended: 200 by 200px square image', 'sky-seo-boost'); ?>
                                </p>
                            </div>

                            <!-- Status -->
                            <div class="sky-seo-form-group">
                                <label for="status">
                                    <?php esc_html_e('Availability Status', 'sky-seo-boost'); ?>
                                </label>
                                <select id="status"
                                        name="<?php echo esc_attr($this->option_name); ?>[status]"
                                        class="sky-seo-filter-select">
                                    <option value="online" <?php selected($settings['status'], 'online'); ?>>
                                        <?php esc_html_e('Online - Available to chat', 'sky-seo-boost'); ?>
                                    </option>
                                    <option value="offline" <?php selected($settings['status'], 'offline'); ?>>
                                        <?php esc_html_e('Offline - Currently unavailable', 'sky-seo-boost'); ?>
                                    </option>
                                </select>
                            </div>

                            <!-- Status Text - NEW FIELD -->
                            <div class="sky-seo-form-group">
                                <label for="status_text">
                                    <?php esc_html_e('Status Text', 'sky-seo-boost'); ?>
                                </label>
                                <input type="text" 
                                       id="status_text" 
                                       name="<?php echo $this->option_name; ?>[status_text]" 
                                       value="<?php echo esc_attr($settings['status_text'] ?? __('Typically replies instantly', 'sky-seo-boost')); ?>" 
                                       placeholder="<?php esc_attr_e('Typically replies instantly', 'sky-seo-boost'); ?>"
                                       class="sky-seo-input">
                                <p class="sky-seo-field-description">
                                    <?php esc_html_e('Text shown below your name in the popup header', 'sky-seo-boost'); ?>
                                </p>
                            </div>

                            <!-- Verified Badge -->
                            <div class="sky-seo-form-group">
                                <div class="sky-seo-checkbox-field">
                                    <label>
                                        <input type="checkbox"
                                               name="<?php echo esc_attr($this->option_name); ?>[show_verified]"
                                               value="1"
                                               <?php checked(!empty($settings['show_verified'])); ?>>
                                        <?php esc_html_e('Show Verified Badge', 'sky-seo-boost'); ?>
                                    </label>
                                    <p class="sky-seo-field-description">
                                        <?php esc_html_e('Display a blue checkmark next to your business name', 'sky-seo-boost'); ?>
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
                                <?php esc_html_e('Message Settings', 'sky-seo-boost'); ?>
                            </h3>
                        </div>

                        <div class="sky-seo-config-card-body">
                            <!-- Welcome Message -->
                            <div class="sky-seo-form-group">
                                <label for="description">
                                    <?php esc_html_e('Welcome Message', 'sky-seo-boost'); ?>
                                </label>
                                <textarea id="description" 
                                          name="<?php echo $this->option_name; ?>[description]" 
                                          rows="4" 
                                          class="sky-seo-textarea"
                                          placeholder="<?php esc_attr_e('Hi there! How can I help you?', 'sky-seo-boost'); ?>"><?php echo esc_textarea($settings['description']); ?></textarea>
                                <p class="sky-seo-field-description">
                                    <?php esc_html_e('Message shown in the popup before users start a chat', 'sky-seo-boost'); ?>
                                </p>
                            </div>

                            <!-- Default Chat Message -->
                            <div class="sky-seo-form-group">
                                <label for="default_message">
                                    <?php esc_html_e('Pre-filled Chat Message', 'sky-seo-boost'); ?>
                                </label>
                                <textarea id="default_message" 
                                          name="<?php echo $this->option_name; ?>[default_message]" 
                                          rows="3" 
                                          class="sky-seo-textarea"
                                          placeholder="<?php esc_attr_e('Hello, I would like to know more about your services.', 'sky-seo-boost'); ?>"><?php echo esc_textarea($settings['default_message'] ?? ''); ?></textarea>
                                <p class="sky-seo-field-description">
                                    <?php esc_html_e('This message will be pre-filled when users start a chat', 'sky-seo-boost'); ?>
                                </p>
                            </div>

                            <!-- Start Chat Button Text - NEW FIELD -->
                            <div class="sky-seo-form-group">
                                <label for="start_chat_text">
                                    <?php esc_html_e('Start Chat Button Text', 'sky-seo-boost'); ?>
                                </label>
                                <input type="text" 
                                       id="start_chat_text" 
                                       name="<?php echo $this->option_name; ?>[start_chat_text]" 
                                       value="<?php echo esc_attr($settings['start_chat_text'] ?? __('Start Chat', 'sky-seo-boost')); ?>" 
                                       placeholder="<?php esc_attr_e('Start Chat', 'sky-seo-boost'); ?>"
                                       class="sky-seo-input">
                                <p class="sky-seo-field-description">
                                    <?php esc_html_e('Text shown on the start chat button in the popup', 'sky-seo-boost'); ?>
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
                            <?php esc_html_e('Trackable WhatsApp Link', 'sky-seo-boost'); ?>
                        </h3>
                    </div>
                    
                    <div class="sky-seo-trackable-link-body">
                        <p class="sky-seo-field-description" style="margin-bottom: 20px;">
                            <?php esc_html_e('Use this permanent link on any button or link element on your website. All clicks will be tracked as "Button Click" in the analytics with the page information.', 'sky-seo-boost'); ?>
                        </p>
                        
                        <?php 
                        // Generate the permanent trackable link
                        $trackable_link = add_query_arg([
                            'sky_whatsapp_button' => '1'
                        ], home_url());
                        ?>
                        
                        <!-- Always Visible Link Display -->
                        <div class="sky-seo-generated-link">
                            <label><?php esc_html_e('Your Trackable WhatsApp Link:', 'sky-seo-boost'); ?></label>
                            <div class="sky-seo-link-display">
                                <input type="text" 
                                       id="trackable-link" 
                                       class="sky-seo-input" 
                                       value="<?php echo esc_url($trackable_link); ?>"
                                       readonly>
                                <button type="button" id="copy-trackable-link" class="button button-secondary">
                                    <?php esc_html_e('Copy', 'sky-seo-boost'); ?>
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
                            <?php esc_html_e('Widget Behavior', 'sky-seo-boost'); ?>
                        </h3>
                    </div>
                    
                    <div class="sky-seo-config-card-body">
                        <!-- Position -->
                        <div class="sky-seo-form-group">
                            <label><?php esc_html_e('Widget Position', 'sky-seo-boost'); ?></label>
                            <div class="sky-seo-position-selector">
                                <label class="sky-seo-position-option">
                                    <input type="radio" 
                                           name="<?php echo $this->option_name; ?>[float_position]" 
                                           value="bottom-left" 
                                           <?php checked($settings['float_position'], 'bottom-left'); ?>>
                                    <div class="sky-seo-position-preview">
                                        <span class="sky-seo-position-label"><?php esc_html_e('Bottom Left', 'sky-seo-boost'); ?></span>
                                    </div>
                                </label>
                                
                                <label class="sky-seo-position-option">
                                    <input type="radio" 
                                           name="<?php echo $this->option_name; ?>[float_position]" 
                                           value="bottom-right" 
                                           <?php checked($settings['float_position'], 'bottom-right'); ?>>
                                    <div class="sky-seo-position-preview">
                                        <span class="sky-seo-position-label"><?php esc_html_e('Bottom Right', 'sky-seo-boost'); ?></span>
                                    </div>
                                </label>
                            </div>
                        </div>
                        
                        <!-- Popup Settings -->
                        <div class="sky-seo-form-group">
                            <label><?php esc_html_e('Popup Settings', 'sky-seo-boost'); ?></label>

                            <div class="sky-seo-checkbox-field">
                                <label>
                                    <input type="checkbox"
                                           name="<?php echo esc_attr($this->option_name); ?>[show_popup]"
                                           value="1"
                                           <?php checked(!empty($settings['show_popup'])); ?>>
                                    <?php esc_html_e('Show Welcome Popup on Click', 'sky-seo-boost'); ?>
                                </label>
                            </div>

                            <div class="sky-seo-inline-field" style="margin-top: 15px;">
                                <label for="popup_delay" style="margin-right: 10px;">
                                    <?php esc_html_e('Auto-show popup after', 'sky-seo-boost'); ?>
                                </label>
                                <input type="number"
                                       id="popup_delay"
                                       name="<?php echo esc_attr($this->option_name); ?>[popup_delay]"
                                       value="<?php echo esc_attr($settings['popup_delay'] ?? 0); ?>"
                                       min="0"
                                       max="300"
                                       class="sky-seo-input-small">
                                <span><?php esc_html_e('seconds', 'sky-seo-boost'); ?></span>
                            </div>
                            <p class="sky-seo-field-description">
                                <?php esc_html_e('Set to 0 to disable auto-popup. Maximum: 300 seconds', 'sky-seo-boost'); ?>
                            </p>
                        </div>

                        <!-- Device Visibility -->
                        <div class="sky-seo-form-group">
                            <label><?php esc_html_e('Device Visibility', 'sky-seo-boost'); ?></label>
                            <div class="sky-seo-checkbox-group">
                                <label>
                                    <input type="checkbox"
                                           name="<?php echo esc_attr($this->option_name); ?>[show_on_mobile]"
                                           value="1"
                                           <?php checked($settings['show_on_mobile'] ?? true, true); ?>>
                                    <?php esc_html_e('Show on Mobile', 'sky-seo-boost'); ?>
                                </label>

                                <label>
                                    <input type="checkbox"
                                           name="<?php echo esc_attr($this->option_name); ?>[show_on_desktop]"
                                           value="1"
                                           <?php checked($settings['show_on_desktop'] ?? true, true); ?>>
                                    <?php esc_html_e('Show on Desktop', 'sky-seo-boost'); ?>
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
                            <?php esc_html_e('Display Rules', 'sky-seo-boost'); ?>
                        </h3>
                    </div>

                    <div class="sky-seo-config-card-body">
                        <!-- Page Type Selection -->
                        <div class="sky-seo-form-group">
                            <label><?php esc_html_e('Show Widget On', 'sky-seo-boost'); ?></label>
                            <div class="sky-seo-page-types-grid">
                                <label class="sky-seo-page-type-option">
                                    <input type="checkbox"
                                           name="<?php echo esc_attr($this->option_name); ?>[show_on_home]"
                                           value="1"
                                           <?php checked($settings['show_on_home'] ?? true, true); ?>>
                                    <span><?php esc_html_e('Homepage', 'sky-seo-boost'); ?></span>
                                </label>

                                <label class="sky-seo-page-type-option">
                                    <input type="checkbox"
                                           name="<?php echo esc_attr($this->option_name); ?>[show_on_pages]"
                                           value="1"
                                           <?php checked($settings['show_on_pages'] ?? true, true); ?>>
                                    <span><?php esc_html_e('Pages', 'sky-seo-boost'); ?></span>
                                </label>

                                <label class="sky-seo-page-type-option">
                                    <input type="checkbox"
                                           name="<?php echo esc_attr($this->option_name); ?>[show_on_posts]"
                                           value="1"
                                           <?php checked($settings['show_on_posts'] ?? true, true); ?>>
                                    <span><?php esc_html_e('Posts', 'sky-seo-boost'); ?></span>
                                </label>

                                <?php if (class_exists('WooCommerce')): ?>
                                <label class="sky-seo-page-type-option">
                                    <input type="checkbox"
                                           name="<?php echo esc_attr($this->option_name); ?>[show_on_products]"
                                           value="1"
                                           <?php checked($settings['show_on_products'] ?? true, true); ?>>
                                    <span><?php esc_html_e('Products', 'sky-seo-boost'); ?></span>
                                </label>
                                <?php endif; ?>

                                <label class="sky-seo-page-type-option">
                                    <input type="checkbox"
                                           name="<?php echo esc_attr($this->option_name); ?>[show_on_sky_areas]"
                                           value="1"
                                           <?php checked($settings['show_on_sky_areas'] ?? true, true); ?>>
                                    <span><?php esc_html_e('Areas', 'sky-seo-boost'); ?></span>
                                </label>

                                <label class="sky-seo-page-type-option">
                                    <input type="checkbox"
                                           name="<?php echo esc_attr($this->option_name); ?>[show_on_sky_trending]"
                                           value="1"
                                           <?php checked($settings['show_on_sky_trending'] ?? true, true); ?>>
                                    <span><?php esc_html_e('Trending', 'sky-seo-boost'); ?></span>
                                </label>

                                <label class="sky-seo-page-type-option">
                                    <input type="checkbox"
                                           name="<?php echo esc_attr($this->option_name); ?>[show_on_sky_sectors]"
                                           value="1"
                                           <?php checked($settings['show_on_sky_sectors'] ?? true, true); ?>>
                                    <span><?php esc_html_e('Sectors', 'sky-seo-boost'); ?></span>
                                </label>
                            </div>
                        </div>

                        <!-- Exclude Pages -->
                        <div class="sky-seo-form-group">
                            <label for="exclude_pages">
                                <?php esc_html_e('Exclude Specific Pages', 'sky-seo-boost'); ?>
                            </label>
                            <select id="exclude_pages"
                                    name="<?php echo esc_attr($this->option_name); ?>[exclude_pages][]"
                                    multiple
                                    class="sky-seo-select2"
                                    data-placeholder="<?php esc_attr_e('Select pages to exclude...', 'sky-seo-boost'); ?>">
                                <?php
                                $pages = get_pages(['hierarchical' => true, 'sort_column' => 'menu_order, post_title']);
                                $excluded = $settings['exclude_pages'] ?? [];
                                
                                foreach ($pages as $page) {
                                    $depth = count(get_ancestors($page->ID, 'page'));
                                    $indent = str_repeat('— ', $depth);
                                    $selected = in_array($page->ID, $excluded) ? 'selected' : '';
                                    echo '<option value="' . esc_attr($page->ID) . '" ' . esc_attr($selected) . '>' .
                                         esc_html($indent . $page->post_title) . '</option>';
                                }
                                ?>
                            </select>
                            <p class="sky-seo-field-description">
                                <?php esc_html_e('Select pages where the WhatsApp widget should not appear', 'sky-seo-boost'); ?>
                            </p>
                        </div>
                    </div>
                </div>

                <!-- Business Hours Schedule -->
                <div class="sky-seo-config-card">
                    <div class="sky-seo-config-card-header">
                        <h3>
                            <span class="dashicons dashicons-clock"></span>
                            <?php esc_html_e('Business Hours', 'sky-seo-boost'); ?>
                        </h3>
                    </div>

                    <div class="sky-seo-config-card-body">
                        <div class="sky-seo-form-group">
                            <div class="sky-seo-toggle-field">
                                <label class="sky-seo-toggle-switch">
                                    <input type="checkbox"
                                           id="enable-business-hours"
                                           name="<?php echo esc_attr($this->option_name); ?>[enable_business_hours]"
                                           value="1"
                                           <?php checked(!empty($settings['enable_business_hours'])); ?>>
                                    <span class="sky-seo-slider"></span>
                                </label>
                                <label for="enable-business-hours" class="sky-seo-toggle-label">
                                    <?php esc_html_e('Enable Business Hours', 'sky-seo-boost'); ?>
                                </label>
                            </div>
                            <p class="sky-seo-field-description">
                                <?php esc_html_e('Automatically show online/offline status based on your business hours', 'sky-seo-boost'); ?>
                            </p>
                        </div>

                        <div class="sky-seo-business-hours-wrapper" id="business-hours-settings" style="<?php echo empty($settings['enable_business_hours']) ? 'display:none;' : ''; ?>">
                            <!-- Timezone -->
                            <div class="sky-seo-form-group">
                                <label for="timezone"><?php esc_html_e('Timezone', 'sky-seo-boost'); ?></label>
                                <select id="timezone"
                                        name="<?php echo esc_attr($this->option_name); ?>[timezone]"
                                        class="sky-seo-filter-select">
                                    <?php
                                    $current_tz = $settings['timezone'] ?? wp_timezone_string();
                                    $timezones = timezone_identifiers_list();
                                    foreach ($timezones as $tz) {
                                        echo '<option value="' . esc_attr($tz) . '" ' . selected($current_tz, $tz, false) . '>' . esc_html($tz) . '</option>';
                                    }
                                    ?>
                                </select>
                            </div>

                            <!-- Schedule Grid -->
                            <div class="sky-seo-schedule-grid">
                                <?php
                                $days = [
                                    'monday' => __('Monday', 'sky-seo-boost'),
                                    'tuesday' => __('Tuesday', 'sky-seo-boost'),
                                    'wednesday' => __('Wednesday', 'sky-seo-boost'),
                                    'thursday' => __('Thursday', 'sky-seo-boost'),
                                    'friday' => __('Friday', 'sky-seo-boost'),
                                    'saturday' => __('Saturday', 'sky-seo-boost'),
                                    'sunday' => __('Sunday', 'sky-seo-boost'),
                                ];
                                $hours = $settings['business_hours'] ?? $this->get_default_settings()['business_hours'];
                                foreach ($days as $day_key => $day_label): ?>
                                    <div class="sky-seo-schedule-row">
                                        <label class="sky-seo-day-toggle">
                                            <input type="checkbox"
                                                   name="<?php echo esc_attr($this->option_name); ?>[business_hours][<?php echo esc_attr($day_key); ?>][enabled]"
                                                   value="1"
                                                   <?php checked(!empty($hours[$day_key]['enabled'])); ?>>
                                            <span class="sky-seo-day-name"><?php echo esc_html($day_label); ?></span>
                                        </label>
                                        <div class="sky-seo-time-inputs">
                                            <input type="time"
                                                   name="<?php echo esc_attr($this->option_name); ?>[business_hours][<?php echo esc_attr($day_key); ?>][open]"
                                                   value="<?php echo esc_attr($hours[$day_key]['open'] ?? '09:00'); ?>"
                                                   class="sky-seo-time-input">
                                            <span class="sky-seo-time-separator">-</span>
                                            <input type="time"
                                                   name="<?php echo esc_attr($this->option_name); ?>[business_hours][<?php echo esc_attr($day_key); ?>][close]"
                                                   value="<?php echo esc_attr($hours[$day_key]['close'] ?? '17:00'); ?>"
                                                   class="sky-seo-time-input">
                                        </div>
                                    </div>
                                <?php endforeach; ?>
                            </div>

                            <!-- Offline Message -->
                            <div class="sky-seo-form-group" style="margin-top: 20px;">
                                <label for="offline_message"><?php esc_html_e('Offline Message', 'sky-seo-boost'); ?></label>
                                <textarea id="offline_message"
                                          name="<?php echo esc_attr($this->option_name); ?>[offline_message]"
                                          rows="2"
                                          class="sky-seo-textarea"
                                          placeholder="<?php esc_attr_e('We are currently offline. Leave a message!', 'sky-seo-boost'); ?>"><?php echo esc_textarea($settings['offline_message'] ?? ''); ?></textarea>
                                <p class="sky-seo-field-description">
                                    <?php esc_html_e('Message shown when outside business hours', 'sky-seo-boost'); ?>
                                </p>
                            </div>
                        </div>
                    </div>
                </div>

                <!-- Typing Animation & Social Proof -->
                <div class="sky-seo-config-card">
                    <div class="sky-seo-config-card-header">
                        <h3>
                            <span class="dashicons dashicons-format-status"></span>
                            <?php esc_html_e('Engagement Features', 'sky-seo-boost'); ?>
                        </h3>
                    </div>

                    <div class="sky-seo-config-card-body">
                        <!-- Typing Animation -->
                        <div class="sky-seo-form-group">
                            <div class="sky-seo-toggle-field">
                                <label class="sky-seo-toggle-switch">
                                    <input type="checkbox"
                                           id="enable-typing-animation"
                                           name="<?php echo esc_attr($this->option_name); ?>[enable_typing_animation]"
                                           value="1"
                                           <?php checked($settings['enable_typing_animation'] ?? true); ?>>
                                    <span class="sky-seo-slider"></span>
                                </label>
                                <label for="enable-typing-animation" class="sky-seo-toggle-label">
                                    <?php esc_html_e('Typing Animation', 'sky-seo-boost'); ?>
                                </label>
                            </div>
                            <p class="sky-seo-field-description">
                                <?php esc_html_e('Show typing indicator (•••) before displaying the welcome message', 'sky-seo-boost'); ?>
                            </p>
                        </div>

                        <div class="sky-seo-inline-field" id="typing-duration-field" style="margin-bottom: 25px; <?php echo empty($settings['enable_typing_animation']) && !($settings['enable_typing_animation'] ?? true) ? 'display:none;' : ''; ?>">
                            <label for="typing_duration"><?php esc_html_e('Typing duration', 'sky-seo-boost'); ?></label>
                            <input type="number"
                                   id="typing_duration"
                                   name="<?php echo esc_attr($this->option_name); ?>[typing_duration]"
                                   value="<?php echo esc_attr($settings['typing_duration'] ?? 1500); ?>"
                                   min="500"
                                   max="5000"
                                   step="100"
                                   class="sky-seo-input-small">
                            <span><?php esc_html_e('milliseconds', 'sky-seo-boost'); ?></span>
                        </div>

                        <!-- Social Proof Counter -->
                        <div class="sky-seo-form-group">
                            <div class="sky-seo-toggle-field">
                                <label class="sky-seo-toggle-switch">
                                    <input type="checkbox"
                                           id="enable-social-proof"
                                           name="<?php echo esc_attr($this->option_name); ?>[enable_social_proof]"
                                           value="1"
                                           <?php checked(!empty($settings['enable_social_proof'])); ?>>
                                    <span class="sky-seo-slider"></span>
                                </label>
                                <label for="enable-social-proof" class="sky-seo-toggle-label">
                                    <?php esc_html_e('Social Proof Counter', 'sky-seo-boost'); ?>
                                </label>
                            </div>
                            <p class="sky-seo-field-description">
                                <?php esc_html_e('Display "X people contacted us today" to increase trust', 'sky-seo-boost'); ?>
                            </p>
                        </div>

                        <div id="social-proof-settings" style="<?php echo empty($settings['enable_social_proof']) ? 'display:none;' : ''; ?>">
                            <div class="sky-seo-form-group">
                                <label for="social_proof_text"><?php esc_html_e('Display Text', 'sky-seo-boost'); ?></label>
                                <input type="text"
                                       id="social_proof_text"
                                       name="<?php echo esc_attr($this->option_name); ?>[social_proof_text]"
                                       value="<?php echo esc_attr($settings['social_proof_text'] ?? __('%count% people contacted us today', 'sky-seo-boost')); ?>"
                                       class="sky-seo-input"
                                       placeholder="<?php esc_attr_e('%count% people contacted us today', 'sky-seo-boost'); ?>">
                                <p class="sky-seo-field-description">
                                    <?php esc_html_e('Use %count% as placeholder for the number', 'sky-seo-boost'); ?>
                                </p>
                            </div>
                            <div class="sky-seo-inline-fields">
                                <div class="sky-seo-inline-field">
                                    <label for="social_proof_min"><?php esc_html_e('Min count', 'sky-seo-boost'); ?></label>
                                    <input type="number"
                                           id="social_proof_min"
                                           name="<?php echo esc_attr($this->option_name); ?>[social_proof_min]"
                                           value="<?php echo esc_attr($settings['social_proof_min'] ?? 5); ?>"
                                           min="1"
                                           max="100"
                                           class="sky-seo-input-small">
                                </div>
                                <div class="sky-seo-inline-field">
                                    <label for="social_proof_max"><?php esc_html_e('Max count', 'sky-seo-boost'); ?></label>
                                    <input type="number"
                                           id="social_proof_max"
                                           name="<?php echo esc_attr($this->option_name); ?>[social_proof_max]"
                                           value="<?php echo esc_attr($settings['social_proof_max'] ?? 25); ?>"
                                           min="1"
                                           max="100"
                                           class="sky-seo-input-small">
                                </div>
                            </div>
                        </div>
                    </div>
                </div>

                <!-- Personalized Greeting -->
                <div class="sky-seo-config-card">
                    <div class="sky-seo-config-card-header">
                        <h3>
                            <span class="dashicons dashicons-smiley"></span>
                            <?php esc_html_e('Personalized Greeting', 'sky-seo-boost'); ?>
                        </h3>
                    </div>

                    <div class="sky-seo-config-card-body">
                        <div class="sky-seo-form-group">
                            <div class="sky-seo-toggle-field">
                                <label class="sky-seo-toggle-switch">
                                    <input type="checkbox"
                                           id="enable-personalized-greeting"
                                           name="<?php echo esc_attr($this->option_name); ?>[enable_personalized_greeting]"
                                           value="1"
                                           <?php checked(!empty($settings['enable_personalized_greeting'])); ?>>
                                    <span class="sky-seo-slider"></span>
                                </label>
                                <label for="enable-personalized-greeting" class="sky-seo-toggle-label">
                                    <?php esc_html_e('Time-Based Greeting', 'sky-seo-boost'); ?>
                                </label>
                            </div>
                            <p class="sky-seo-field-description">
                                <?php esc_html_e('Show different welcome messages based on visitor\'s local time', 'sky-seo-boost'); ?>
                            </p>
                        </div>

                        <div id="personalized-greeting-settings" style="<?php echo empty($settings['enable_personalized_greeting']) ? 'display:none;' : ''; ?>">
                            <div class="sky-seo-greeting-grid">
                                <div class="sky-seo-form-group">
                                    <label for="greeting_morning">
                                        <span class="dashicons dashicons-admin-site-alt3"></span>
                                        <?php esc_html_e('Morning (6AM - 12PM)', 'sky-seo-boost'); ?>
                                    </label>
                                    <textarea id="greeting_morning"
                                              name="<?php echo esc_attr($this->option_name); ?>[greeting_morning]"
                                              rows="2"
                                              class="sky-seo-textarea"><?php echo esc_textarea($settings['greeting_morning'] ?? ''); ?></textarea>
                                </div>
                                <div class="sky-seo-form-group">
                                    <label for="greeting_afternoon">
                                        <span class="dashicons dashicons-admin-site-alt3"></span>
                                        <?php esc_html_e('Afternoon (12PM - 6PM)', 'sky-seo-boost'); ?>
                                    </label>
                                    <textarea id="greeting_afternoon"
                                              name="<?php echo esc_attr($this->option_name); ?>[greeting_afternoon]"
                                              rows="2"
                                              class="sky-seo-textarea"><?php echo esc_textarea($settings['greeting_afternoon'] ?? ''); ?></textarea>
                                </div>
                                <div class="sky-seo-form-group">
                                    <label for="greeting_evening">
                                        <span class="dashicons dashicons-admin-site-alt3"></span>
                                        <?php esc_html_e('Evening (6PM - 10PM)', 'sky-seo-boost'); ?>
                                    </label>
                                    <textarea id="greeting_evening"
                                              name="<?php echo esc_attr($this->option_name); ?>[greeting_evening]"
                                              rows="2"
                                              class="sky-seo-textarea"><?php echo esc_textarea($settings['greeting_evening'] ?? ''); ?></textarea>
                                </div>
                                <div class="sky-seo-form-group">
                                    <label for="greeting_night">
                                        <span class="dashicons dashicons-admin-site-alt3"></span>
                                        <?php esc_html_e('Night (10PM - 6AM)', 'sky-seo-boost'); ?>
                                    </label>
                                    <textarea id="greeting_night"
                                              name="<?php echo esc_attr($this->option_name); ?>[greeting_night]"
                                              rows="2"
                                              class="sky-seo-textarea"><?php echo esc_textarea($settings['greeting_night'] ?? ''); ?></textarea>
                                </div>
                            </div>
                        </div>
                    </div>
                </div>

                <!-- QR Code Generator -->
                <div class="sky-seo-config-card">
                    <div class="sky-seo-config-card-header">
                        <h3>
                            <span class="dashicons dashicons-screenoptions"></span>
                            <?php esc_html_e('QR Code Generator', 'sky-seo-boost'); ?>
                        </h3>
                    </div>

                    <div class="sky-seo-config-card-body">
                        <p class="sky-seo-field-description" style="margin-bottom: 20px;">
                            <?php esc_html_e('Generate a QR code for your WhatsApp number. Perfect for print materials, business cards, and in-store displays.', 'sky-seo-boost'); ?>
                        </p>

                        <?php if (!empty($settings['phone'])): ?>
                            <div class="sky-seo-qr-container">
                                <div class="sky-seo-qr-preview" id="whatsapp-qr-code">
                                    <?php
                                    $phone = preg_replace('/[^0-9]/', '', $settings['phone']);
                                    $qr_url = 'https://api.qrserver.com/v1/create-qr-code/?size=200x200&data=' . urlencode('https://wa.me/' . $phone);
                                    ?>
                                    <img src="<?php echo esc_url($qr_url); ?>" alt="WhatsApp QR Code" style="max-width: 200px;">
                                </div>
                                <div class="sky-seo-qr-actions">
                                    <a href="<?php echo esc_url($qr_url . '&format=png'); ?>"
                                       download="whatsapp-qr-code.png"
                                       class="button button-secondary">
                                        <span class="dashicons dashicons-download"></span>
                                        <?php esc_html_e('Download PNG', 'sky-seo-boost'); ?>
                                    </a>
                                    <a href="<?php echo esc_url(str_replace('size=200x200', 'size=500x500', $qr_url) . '&format=svg'); ?>"
                                       download="whatsapp-qr-code.svg"
                                       class="button button-secondary">
                                        <span class="dashicons dashicons-download"></span>
                                        <?php esc_html_e('Download SVG', 'sky-seo-boost'); ?>
                                    </a>
                                </div>
                            </div>
                        <?php else: ?>
                            <div class="sky-seo-notice">
                                <span class="dashicons dashicons-warning"></span>
                                <?php esc_html_e('Please configure your WhatsApp phone number first to generate a QR code.', 'sky-seo-boost'); ?>
                            </div>
                        <?php endif; ?>
                    </div>
                </div>

                <!-- Smart Triggers -->
                <div class="sky-seo-config-card">
                    <div class="sky-seo-config-card-header">
                        <h3>
                            <span class="dashicons dashicons-performance"></span>
                            <?php esc_html_e('Smart Triggers', 'sky-seo-boost'); ?>
                        </h3>
                    </div>

                    <div class="sky-seo-config-card-body">
                        <div class="sky-seo-form-group">
                            <div class="sky-seo-toggle-field">
                                <label class="sky-seo-toggle-switch">
                                    <input type="checkbox"
                                           id="enable-smart-triggers"
                                           name="<?php echo esc_attr($this->option_name); ?>[enable_smart_triggers]"
                                           value="1"
                                           <?php checked(!empty($settings['enable_smart_triggers'])); ?>>
                                    <span class="sky-seo-slider"></span>
                                </label>
                                <label for="enable-smart-triggers" class="sky-seo-toggle-label">
                                    <?php esc_html_e('Enable Smart Triggers', 'sky-seo-boost'); ?>
                                </label>
                            </div>
                            <p class="sky-seo-field-description">
                                <?php esc_html_e('Automatically show the popup based on visitor behavior', 'sky-seo-boost'); ?>
                            </p>
                        </div>

                        <div id="smart-triggers-settings" style="<?php echo empty($settings['enable_smart_triggers']) ? 'display:none;' : ''; ?>">
                            <!-- Scroll Trigger -->
                            <div class="sky-seo-trigger-option">
                                <label class="sky-seo-checkbox-inline">
                                    <input type="checkbox"
                                           name="<?php echo esc_attr($this->option_name); ?>[trigger_scroll_enabled]"
                                           value="1"
                                           <?php checked(!empty($settings['trigger_scroll_enabled'])); ?>>
                                    <?php esc_html_e('Scroll Percentage', 'sky-seo-boost'); ?>
                                </label>
                                <div class="sky-seo-trigger-value">
                                    <input type="number"
                                           name="<?php echo esc_attr($this->option_name); ?>[trigger_scroll_percentage]"
                                           value="<?php echo esc_attr($settings['trigger_scroll_percentage'] ?? 50); ?>"
                                           min="10"
                                           max="100"
                                           class="sky-seo-input-small">
                                    <span>%</span>
                                </div>
                                <p class="sky-seo-field-description"><?php esc_html_e('Show popup when visitor scrolls down this percentage of the page', 'sky-seo-boost'); ?></p>
                            </div>

                            <!-- Exit Intent -->
                            <div class="sky-seo-trigger-option">
                                <label class="sky-seo-checkbox-inline">
                                    <input type="checkbox"
                                           name="<?php echo esc_attr($this->option_name); ?>[trigger_exit_intent]"
                                           value="1"
                                           <?php checked(!empty($settings['trigger_exit_intent'])); ?>>
                                    <?php esc_html_e('Exit Intent', 'sky-seo-boost'); ?>
                                </label>
                                <p class="sky-seo-field-description"><?php esc_html_e('Show popup when visitor moves mouse towards closing the tab (desktop only)', 'sky-seo-boost'); ?></p>
                            </div>

                            <!-- Time on Page -->
                            <div class="sky-seo-trigger-option">
                                <label class="sky-seo-checkbox-inline">
                                    <input type="checkbox"
                                           name="<?php echo esc_attr($this->option_name); ?>[trigger_time_on_page]"
                                           value="1"
                                           <?php checked(!empty($settings['trigger_time_on_page'])); ?>>
                                    <?php esc_html_e('Time on Page', 'sky-seo-boost'); ?>
                                </label>
                                <div class="sky-seo-trigger-value">
                                    <input type="number"
                                           name="<?php echo esc_attr($this->option_name); ?>[trigger_time_seconds]"
                                           value="<?php echo esc_attr($settings['trigger_time_seconds'] ?? 30); ?>"
                                           min="5"
                                           max="300"
                                           class="sky-seo-input-small">
                                    <span><?php esc_html_e('seconds', 'sky-seo-boost'); ?></span>
                                </div>
                                <p class="sky-seo-field-description"><?php esc_html_e('Show popup after visitor has been on the page for this duration', 'sky-seo-boost'); ?></p>
                            </div>

                            <!-- Page Views -->
                            <div class="sky-seo-trigger-option">
                                <label class="sky-seo-checkbox-inline">
                                    <input type="checkbox"
                                           name="<?php echo esc_attr($this->option_name); ?>[trigger_page_views]"
                                           value="1"
                                           <?php checked(!empty($settings['trigger_page_views'])); ?>>
                                    <?php esc_html_e('Page Views', 'sky-seo-boost'); ?>
                                </label>
                                <div class="sky-seo-trigger-value">
                                    <input type="number"
                                           name="<?php echo esc_attr($this->option_name); ?>[trigger_page_views_count]"
                                           value="<?php echo esc_attr($settings['trigger_page_views_count'] ?? 2); ?>"
                                           min="1"
                                           max="10"
                                           class="sky-seo-input-small">
                                    <span><?php esc_html_e('pages', 'sky-seo-boost'); ?></span>
                                </div>
                                <p class="sky-seo-field-description"><?php esc_html_e('Show popup after visitor has viewed this many pages', 'sky-seo-boost'); ?></p>
                            </div>
                        </div>
                    </div>
                </div>

                <!-- Save Button -->
                <div class="sky-seo-form-actions">
                    <button type="submit" name="submit" class="button button-primary button-large">
                        <span class="dashicons dashicons-saved"></span>
                        <?php esc_html_e('Save Settings', 'sky-seo-boost'); ?>
                    </button>
                    <span class="spinner"></span>
                    <span class="sky-seo-success-message" style="display:none;">
                        <span class="dashicons dashicons-yes-alt"></span>
                        <?php esc_html_e('Settings saved successfully!', 'sky-seo-boost'); ?>
                    </span>
                </div>
            </form>
        </div>

        <script>
        jQuery(document).ready(function($) {
            // Toggle sections based on checkboxes
            $('#enable-business-hours').on('change', function() {
                $('#business-hours-settings').toggle(this.checked);
            });
            $('#enable-typing-animation').on('change', function() {
                $('#typing-duration-field').toggle(this.checked);
            });
            $('#enable-social-proof').on('change', function() {
                $('#social-proof-settings').toggle(this.checked);
            });
            $('#enable-personalized-greeting').on('change', function() {
                $('#personalized-greeting-settings').toggle(this.checked);
            });
            $('#enable-smart-triggers').on('change', function() {
                $('#smart-triggers-settings').toggle(this.checked);
            });
        });
        </script>
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
            // Business Hours
            'enable_business_hours' => false,
            'business_hours' => [
                'monday'    => ['enabled' => true, 'open' => '09:00', 'close' => '17:00'],
                'tuesday'   => ['enabled' => true, 'open' => '09:00', 'close' => '17:00'],
                'wednesday' => ['enabled' => true, 'open' => '09:00', 'close' => '17:00'],
                'thursday'  => ['enabled' => true, 'open' => '09:00', 'close' => '17:00'],
                'friday'    => ['enabled' => true, 'open' => '09:00', 'close' => '17:00'],
                'saturday'  => ['enabled' => false, 'open' => '10:00', 'close' => '14:00'],
                'sunday'    => ['enabled' => false, 'open' => '00:00', 'close' => '00:00'],
            ],
            'offline_message' => __('We are currently offline. Leave a message and we\'ll get back to you!', 'sky-seo-boost'),
            'timezone' => wp_timezone_string(),
            // Typing Animation
            'enable_typing_animation' => true,
            'typing_duration' => 1500,
            // Social Proof
            'enable_social_proof' => false,
            'social_proof_text' => __('%count% people contacted us today', 'sky-seo-boost'),
            'social_proof_min' => 5,
            'social_proof_max' => 25,
            // Personalized Greeting
            'enable_personalized_greeting' => false,
            'greeting_morning' => __('Good morning! ☀️ How can we help you today?', 'sky-seo-boost'),
            'greeting_afternoon' => __('Good afternoon! How can we assist you?', 'sky-seo-boost'),
            'greeting_evening' => __('Good evening! How can we help you?', 'sky-seo-boost'),
            'greeting_night' => __('Hello! Thanks for reaching out. How can we help?', 'sky-seo-boost'),
            // Smart Triggers
            'enable_smart_triggers' => false,
            'trigger_scroll_enabled' => false,
            'trigger_scroll_percentage' => 50,
            'trigger_exit_intent' => false,
            'trigger_time_on_page' => false,
            'trigger_time_seconds' => 30,
            'trigger_page_views' => false,
            'trigger_page_views_count' => 2,
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
            'show_on_sky_trending', 'show_on_sky_sectors',
            // New feature toggles
            'enable_business_hours', 'enable_typing_animation',
            'enable_social_proof', 'enable_personalized_greeting',
            'enable_smart_triggers', 'trigger_scroll_enabled',
            'trigger_exit_intent', 'trigger_time_on_page', 'trigger_page_views'
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

        // Business Hours
        $days = ['monday', 'tuesday', 'wednesday', 'thursday', 'friday', 'saturday', 'sunday'];
        $sanitized['business_hours'] = [];
        foreach ($days as $day) {
            $sanitized['business_hours'][$day] = [
                'enabled' => !empty($input['business_hours'][$day]['enabled']),
                'open' => $this->sanitize_time($input['business_hours'][$day]['open'] ?? '09:00'),
                'close' => $this->sanitize_time($input['business_hours'][$day]['close'] ?? '17:00'),
            ];
        }
        $sanitized['offline_message'] = sanitize_textarea_field($input['offline_message'] ?? '');
        $sanitized['timezone'] = sanitize_text_field($input['timezone'] ?? wp_timezone_string());

        // Typing Animation
        $sanitized['typing_duration'] = absint($input['typing_duration'] ?? 1500);
        if ($sanitized['typing_duration'] < 500) $sanitized['typing_duration'] = 500;
        if ($sanitized['typing_duration'] > 5000) $sanitized['typing_duration'] = 5000;

        // Social Proof
        $sanitized['social_proof_text'] = sanitize_text_field($input['social_proof_text'] ?? '');
        $sanitized['social_proof_min'] = absint($input['social_proof_min'] ?? 5);
        $sanitized['social_proof_max'] = absint($input['social_proof_max'] ?? 25);
        if ($sanitized['social_proof_min'] > $sanitized['social_proof_max']) {
            $sanitized['social_proof_min'] = $sanitized['social_proof_max'];
        }

        // Personalized Greeting
        $sanitized['greeting_morning'] = sanitize_textarea_field($input['greeting_morning'] ?? '');
        $sanitized['greeting_afternoon'] = sanitize_textarea_field($input['greeting_afternoon'] ?? '');
        $sanitized['greeting_evening'] = sanitize_textarea_field($input['greeting_evening'] ?? '');
        $sanitized['greeting_night'] = sanitize_textarea_field($input['greeting_night'] ?? '');

        // Smart Triggers
        $sanitized['trigger_scroll_percentage'] = absint($input['trigger_scroll_percentage'] ?? 50);
        if ($sanitized['trigger_scroll_percentage'] > 100) $sanitized['trigger_scroll_percentage'] = 100;
        $sanitized['trigger_time_seconds'] = absint($input['trigger_time_seconds'] ?? 30);
        if ($sanitized['trigger_time_seconds'] > 300) $sanitized['trigger_time_seconds'] = 300;
        $sanitized['trigger_page_views_count'] = absint($input['trigger_page_views_count'] ?? 2);
        if ($sanitized['trigger_page_views_count'] > 10) $sanitized['trigger_page_views_count'] = 10;

        return $sanitized;
    }

    /**
     * Sanitize time string (HH:MM format)
     */
    private function sanitize_time($time) {
        if (preg_match('/^([01]?[0-9]|2[0-3]):([0-5][0-9])$/', $time)) {
            return $time;
        }
        return '09:00';
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