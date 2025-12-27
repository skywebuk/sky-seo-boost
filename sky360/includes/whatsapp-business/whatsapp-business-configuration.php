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
                                <?php esc_html_e('WhatsApp Account', 'sky360'); ?>
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
                                        <?php esc_html_e('Enable WhatsApp Widget', 'sky360'); ?>
                                    </label>
                                </div>
                            </div>
                            
                            <!-- Phone Number -->
                            <div class="sky-seo-form-group">
                                <label for="whatsapp_phone">
                                    <?php esc_html_e('WhatsApp Phone Number', 'sky360'); ?>
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
                                    <?php esc_html_e('Enter with country code (e.g., +447123456789 for UK)', 'sky360'); ?>
                                </p>
                            </div>
                            
                            <!-- Display Name -->
                            <div class="sky-seo-form-group">
                                <label for="display_name">
                                    <?php esc_html_e('Display Name', 'sky360'); ?>
                                </label>
                                <input type="text" 
                                       id="display_name" 
                                       name="<?php echo $this->option_name; ?>[display_name]" 
                                       value="<?php echo esc_attr($settings['display_name']); ?>" 
                                       placeholder="<?php echo esc_attr(get_bloginfo('name')); ?>"
                                       class="sky-seo-input">
                                <p class="sky-seo-field-description">
                                    <?php esc_html_e('The name shown in the WhatsApp popup', 'sky360'); ?>
                                </p>
                            </div>

                            <!-- Profile Photo -->
                            <div class="sky-seo-form-group">
                                <label for="profile_photo">
                                    <?php esc_html_e('Profile Photo', 'sky360'); ?>
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
                                            <?php esc_html_e('Select Image', 'sky360'); ?>
                                        </button>
                                        <button type="button" class="button button-link remove-button"
                                                <?php echo empty($settings['profile_photo']) ? 'style="display:none;"' : ''; ?>>
                                            <?php esc_html_e('Remove', 'sky360'); ?>
                                        </button>
                                    </div>
                                </div>
                                <p class="sky-seo-field-description">
                                    <?php esc_html_e('Recommended: 200 by 200px square image', 'sky360'); ?>
                                </p>
                            </div>

                            <!-- Status -->
                            <div class="sky-seo-form-group">
                                <label for="status">
                                    <?php esc_html_e('Availability Status', 'sky360'); ?>
                                </label>
                                <select id="status"
                                        name="<?php echo esc_attr($this->option_name); ?>[status]"
                                        class="sky-seo-filter-select">
                                    <option value="online" <?php selected($settings['status'], 'online'); ?>>
                                        <?php esc_html_e('Online - Available to chat', 'sky360'); ?>
                                    </option>
                                    <option value="offline" <?php selected($settings['status'], 'offline'); ?>>
                                        <?php esc_html_e('Offline - Currently unavailable', 'sky360'); ?>
                                    </option>
                                </select>
                            </div>

                            <!-- Status Text - NEW FIELD -->
                            <div class="sky-seo-form-group">
                                <label for="status_text">
                                    <?php esc_html_e('Status Text', 'sky360'); ?>
                                </label>
                                <input type="text" 
                                       id="status_text" 
                                       name="<?php echo $this->option_name; ?>[status_text]" 
                                       value="<?php echo esc_attr($settings['status_text'] ?? __('Typically replies instantly', 'sky360')); ?>" 
                                       placeholder="<?php esc_attr_e('Typically replies instantly', 'sky360'); ?>"
                                       class="sky-seo-input">
                                <p class="sky-seo-field-description">
                                    <?php esc_html_e('Text shown below your name in the popup header', 'sky360'); ?>
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
                                        <?php esc_html_e('Show Verified Badge', 'sky360'); ?>
                                    </label>
                                    <p class="sky-seo-field-description">
                                        <?php esc_html_e('Display a blue checkmark next to your business name', 'sky360'); ?>
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
                                <?php esc_html_e('Message Settings', 'sky360'); ?>
                            </h3>
                        </div>

                        <div class="sky-seo-config-card-body">
                            <!-- Welcome Message -->
                            <div class="sky-seo-form-group">
                                <label for="description">
                                    <?php esc_html_e('Welcome Message', 'sky360'); ?>
                                </label>
                                <textarea id="description" 
                                          name="<?php echo $this->option_name; ?>[description]" 
                                          rows="4" 
                                          class="sky-seo-textarea"
                                          placeholder="<?php esc_attr_e('Hi there! How can I help you?', 'sky360'); ?>"><?php echo esc_textarea($settings['description']); ?></textarea>
                                <p class="sky-seo-field-description">
                                    <?php esc_html_e('Message shown in the popup before users start a chat', 'sky360'); ?>
                                </p>
                            </div>

                            <!-- Default Chat Message -->
                            <div class="sky-seo-form-group">
                                <label for="default_message">
                                    <?php esc_html_e('Pre-filled Chat Message', 'sky360'); ?>
                                </label>
                                <textarea id="default_message" 
                                          name="<?php echo $this->option_name; ?>[default_message]" 
                                          rows="3" 
                                          class="sky-seo-textarea"
                                          placeholder="<?php esc_attr_e('Hello, I would like to know more about your services.', 'sky360'); ?>"><?php echo esc_textarea($settings['default_message'] ?? ''); ?></textarea>
                                <p class="sky-seo-field-description">
                                    <?php esc_html_e('This message will be pre-filled when users start a chat', 'sky360'); ?>
                                </p>
                            </div>

                            <!-- Start Chat Button Text - NEW FIELD -->
                            <div class="sky-seo-form-group">
                                <label for="start_chat_text">
                                    <?php esc_html_e('Start Chat Button Text', 'sky360'); ?>
                                </label>
                                <input type="text" 
                                       id="start_chat_text" 
                                       name="<?php echo $this->option_name; ?>[start_chat_text]" 
                                       value="<?php echo esc_attr($settings['start_chat_text'] ?? __('Start Chat', 'sky360')); ?>" 
                                       placeholder="<?php esc_attr_e('Start Chat', 'sky360'); ?>"
                                       class="sky-seo-input">
                                <p class="sky-seo-field-description">
                                    <?php esc_html_e('Text shown on the start chat button in the popup', 'sky360'); ?>
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
                            <?php esc_html_e('Trackable WhatsApp Link', 'sky360'); ?>
                        </h3>
                    </div>
                    
                    <div class="sky-seo-trackable-link-body">
                        <p class="sky-seo-field-description" style="margin-bottom: 20px;">
                            <?php esc_html_e('Use this permanent link on any button or link element on your website. All clicks will be tracked as "Button Click" in the analytics with the page information.', 'sky360'); ?>
                        </p>
                        
                        <?php 
                        // Generate the permanent trackable link
                        $trackable_link = add_query_arg([
                            'sky_whatsapp_button' => '1'
                        ], home_url());
                        ?>
                        
                        <!-- Always Visible Link Display -->
                        <div class="sky-seo-generated-link">
                            <label><?php esc_html_e('Your Trackable WhatsApp Link:', 'sky360'); ?></label>
                            <div class="sky-seo-link-display">
                                <input type="text" 
                                       id="trackable-link" 
                                       class="sky-seo-input" 
                                       value="<?php echo esc_url($trackable_link); ?>"
                                       readonly>
                                <button type="button" id="copy-trackable-link" class="button button-secondary">
                                    <?php esc_html_e('Copy', 'sky360'); ?>
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
                            <?php esc_html_e('Widget Behavior', 'sky360'); ?>
                        </h3>
                    </div>
                    
                    <div class="sky-seo-config-card-body">
                        <!-- Position -->
                        <div class="sky-seo-form-group">
                            <label><?php esc_html_e('Widget Position', 'sky360'); ?></label>
                            <div class="sky-seo-position-selector">
                                <label class="sky-seo-position-option">
                                    <input type="radio" 
                                           name="<?php echo $this->option_name; ?>[float_position]" 
                                           value="bottom-left" 
                                           <?php checked($settings['float_position'], 'bottom-left'); ?>>
                                    <div class="sky-seo-position-preview">
                                        <span class="sky-seo-position-label"><?php esc_html_e('Bottom Left', 'sky360'); ?></span>
                                    </div>
                                </label>
                                
                                <label class="sky-seo-position-option">
                                    <input type="radio" 
                                           name="<?php echo $this->option_name; ?>[float_position]" 
                                           value="bottom-right" 
                                           <?php checked($settings['float_position'], 'bottom-right'); ?>>
                                    <div class="sky-seo-position-preview">
                                        <span class="sky-seo-position-label"><?php esc_html_e('Bottom Right', 'sky360'); ?></span>
                                    </div>
                                </label>
                            </div>
                        </div>
                        
                        <!-- Popup Settings -->
                        <div class="sky-seo-form-group">
                            <label><?php esc_html_e('Popup Settings', 'sky360'); ?></label>

                            <div class="sky-seo-checkbox-field">
                                <label>
                                    <input type="checkbox"
                                           name="<?php echo esc_attr($this->option_name); ?>[show_popup]"
                                           value="1"
                                           <?php checked(!empty($settings['show_popup'])); ?>>
                                    <?php esc_html_e('Show Welcome Popup on Click', 'sky360'); ?>
                                </label>
                            </div>

                            <div class="sky-seo-inline-field" style="margin-top: 15px;">
                                <label for="popup_delay" style="margin-right: 10px;">
                                    <?php esc_html_e('Auto-show popup after', 'sky360'); ?>
                                </label>
                                <input type="number"
                                       id="popup_delay"
                                       name="<?php echo esc_attr($this->option_name); ?>[popup_delay]"
                                       value="<?php echo esc_attr($settings['popup_delay'] ?? 0); ?>"
                                       min="0"
                                       max="300"
                                       class="sky-seo-input-small">
                                <span><?php esc_html_e('seconds', 'sky360'); ?></span>
                            </div>
                            <p class="sky-seo-field-description">
                                <?php esc_html_e('Set to 0 to disable auto-popup. Maximum: 300 seconds', 'sky360'); ?>
                            </p>
                        </div>

                        <!-- Device Visibility -->
                        <div class="sky-seo-form-group">
                            <label><?php esc_html_e('Device Visibility', 'sky360'); ?></label>
                            <div class="sky-seo-checkbox-group">
                                <label>
                                    <input type="checkbox"
                                           name="<?php echo esc_attr($this->option_name); ?>[show_on_mobile]"
                                           value="1"
                                           <?php checked($settings['show_on_mobile'] ?? true, true); ?>>
                                    <?php esc_html_e('Show on Mobile', 'sky360'); ?>
                                </label>

                                <label>
                                    <input type="checkbox"
                                           name="<?php echo esc_attr($this->option_name); ?>[show_on_desktop]"
                                           value="1"
                                           <?php checked($settings['show_on_desktop'] ?? true, true); ?>>
                                    <?php esc_html_e('Show on Desktop', 'sky360'); ?>
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
                            <?php esc_html_e('Display Rules', 'sky360'); ?>
                        </h3>
                    </div>

                    <div class="sky-seo-config-card-body">
                        <!-- Page Type Selection -->
                        <div class="sky-seo-form-group">
                            <label><?php esc_html_e('Show Widget On', 'sky360'); ?></label>
                            <div class="sky-seo-page-types-grid">
                                <label class="sky-seo-page-type-option">
                                    <input type="checkbox"
                                           name="<?php echo esc_attr($this->option_name); ?>[show_on_home]"
                                           value="1"
                                           <?php checked($settings['show_on_home'] ?? true, true); ?>>
                                    <span><?php esc_html_e('Homepage', 'sky360'); ?></span>
                                </label>

                                <label class="sky-seo-page-type-option">
                                    <input type="checkbox"
                                           name="<?php echo esc_attr($this->option_name); ?>[show_on_pages]"
                                           value="1"
                                           <?php checked($settings['show_on_pages'] ?? true, true); ?>>
                                    <span><?php esc_html_e('Pages', 'sky360'); ?></span>
                                </label>

                                <label class="sky-seo-page-type-option">
                                    <input type="checkbox"
                                           name="<?php echo esc_attr($this->option_name); ?>[show_on_posts]"
                                           value="1"
                                           <?php checked($settings['show_on_posts'] ?? true, true); ?>>
                                    <span><?php esc_html_e('Posts', 'sky360'); ?></span>
                                </label>

                                <?php if (class_exists('WooCommerce')): ?>
                                <label class="sky-seo-page-type-option">
                                    <input type="checkbox"
                                           name="<?php echo esc_attr($this->option_name); ?>[show_on_products]"
                                           value="1"
                                           <?php checked($settings['show_on_products'] ?? true, true); ?>>
                                    <span><?php esc_html_e('Products', 'sky360'); ?></span>
                                </label>
                                <?php endif; ?>

                                <label class="sky-seo-page-type-option">
                                    <input type="checkbox"
                                           name="<?php echo esc_attr($this->option_name); ?>[show_on_sky_areas]"
                                           value="1"
                                           <?php checked($settings['show_on_sky_areas'] ?? true, true); ?>>
                                    <span><?php esc_html_e('Areas', 'sky360'); ?></span>
                                </label>

                                <label class="sky-seo-page-type-option">
                                    <input type="checkbox"
                                           name="<?php echo esc_attr($this->option_name); ?>[show_on_sky_trending]"
                                           value="1"
                                           <?php checked($settings['show_on_sky_trending'] ?? true, true); ?>>
                                    <span><?php esc_html_e('Trending', 'sky360'); ?></span>
                                </label>

                                <label class="sky-seo-page-type-option">
                                    <input type="checkbox"
                                           name="<?php echo esc_attr($this->option_name); ?>[show_on_sky_sectors]"
                                           value="1"
                                           <?php checked($settings['show_on_sky_sectors'] ?? true, true); ?>>
                                    <span><?php esc_html_e('Sectors', 'sky360'); ?></span>
                                </label>
                            </div>
                        </div>

                        <!-- Exclude Pages -->
                        <div class="sky-seo-form-group">
                            <label for="exclude_pages">
                                <?php esc_html_e('Exclude Specific Pages', 'sky360'); ?>
                            </label>
                            <select id="exclude_pages"
                                    name="<?php echo esc_attr($this->option_name); ?>[exclude_pages][]"
                                    multiple
                                    class="sky-seo-select2"
                                    data-placeholder="<?php esc_attr_e('Select pages to exclude...', 'sky360'); ?>">
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
                                <?php esc_html_e('Select pages where the WhatsApp widget should not appear', 'sky360'); ?>
                            </p>
                        </div>
                    </div>
                </div>

                <!-- Business Hours Schedule -->
                <div class="sky-seo-config-card">
                    <div class="sky-seo-config-card-header">
                        <h3>
                            <span class="dashicons dashicons-clock"></span>
                            <?php esc_html_e('Business Hours', 'sky360'); ?>
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
                                    <?php esc_html_e('Enable Business Hours', 'sky360'); ?>
                                </label>
                            </div>
                            <p class="sky-seo-field-description">
                                <?php esc_html_e('Automatically show online/offline status based on your business hours', 'sky360'); ?>
                            </p>
                        </div>

                        <div class="sky-seo-business-hours-wrapper" id="business-hours-settings" style="<?php echo empty($settings['enable_business_hours']) ? 'display:none;' : ''; ?>">
                            <!-- Timezone -->
                            <div class="sky-seo-form-group">
                                <label for="timezone"><?php esc_html_e('Timezone', 'sky360'); ?></label>
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
                                    'monday' => __('Monday', 'sky360'),
                                    'tuesday' => __('Tuesday', 'sky360'),
                                    'wednesday' => __('Wednesday', 'sky360'),
                                    'thursday' => __('Thursday', 'sky360'),
                                    'friday' => __('Friday', 'sky360'),
                                    'saturday' => __('Saturday', 'sky360'),
                                    'sunday' => __('Sunday', 'sky360'),
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
                                <label for="offline_message"><?php esc_html_e('Offline Message', 'sky360'); ?></label>
                                <textarea id="offline_message"
                                          name="<?php echo esc_attr($this->option_name); ?>[offline_message]"
                                          rows="2"
                                          class="sky-seo-textarea"
                                          placeholder="<?php esc_attr_e('We are currently offline. Leave a message!', 'sky360'); ?>"><?php echo esc_textarea($settings['offline_message'] ?? ''); ?></textarea>
                                <p class="sky-seo-field-description">
                                    <?php esc_html_e('Message shown when outside business hours', 'sky360'); ?>
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
                            <?php esc_html_e('Engagement Features', 'sky360'); ?>
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
                                    <?php esc_html_e('Typing Animation', 'sky360'); ?>
                                </label>
                            </div>
                            <p class="sky-seo-field-description">
                                <?php esc_html_e('Show typing indicator (•••) before displaying the welcome message', 'sky360'); ?>
                            </p>
                        </div>

                        <div class="sky-seo-inline-field" id="typing-duration-field" style="margin-bottom: 25px; <?php echo empty($settings['enable_typing_animation']) && !($settings['enable_typing_animation'] ?? true) ? 'display:none;' : ''; ?>">
                            <label for="typing_duration"><?php esc_html_e('Typing duration', 'sky360'); ?></label>
                            <input type="number"
                                   id="typing_duration"
                                   name="<?php echo esc_attr($this->option_name); ?>[typing_duration]"
                                   value="<?php echo esc_attr($settings['typing_duration'] ?? 1500); ?>"
                                   min="500"
                                   max="5000"
                                   step="100"
                                   class="sky-seo-input-small">
                            <span><?php esc_html_e('milliseconds', 'sky360'); ?></span>
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
                                    <?php esc_html_e('Social Proof Counter', 'sky360'); ?>
                                </label>
                            </div>
                            <p class="sky-seo-field-description">
                                <?php esc_html_e('Display "X people contacted us today" to increase trust', 'sky360'); ?>
                            </p>
                        </div>

                        <div id="social-proof-settings" style="<?php echo empty($settings['enable_social_proof']) ? 'display:none;' : ''; ?>">
                            <div class="sky-seo-form-group">
                                <label for="social_proof_text"><?php esc_html_e('Display Text', 'sky360'); ?></label>
                                <input type="text"
                                       id="social_proof_text"
                                       name="<?php echo esc_attr($this->option_name); ?>[social_proof_text]"
                                       value="<?php echo esc_attr($settings['social_proof_text'] ?? __('%count% people contacted us today', 'sky360')); ?>"
                                       class="sky-seo-input"
                                       placeholder="<?php esc_attr_e('%count% people contacted us today', 'sky360'); ?>">
                                <p class="sky-seo-field-description">
                                    <?php esc_html_e('Use %count% as placeholder for the number', 'sky360'); ?>
                                </p>
                            </div>
                            <div class="sky-seo-inline-fields">
                                <div class="sky-seo-inline-field">
                                    <label for="social_proof_min"><?php esc_html_e('Min count', 'sky360'); ?></label>
                                    <input type="number"
                                           id="social_proof_min"
                                           name="<?php echo esc_attr($this->option_name); ?>[social_proof_min]"
                                           value="<?php echo esc_attr($settings['social_proof_min'] ?? 5); ?>"
                                           min="1"
                                           max="100"
                                           class="sky-seo-input-small">
                                </div>
                                <div class="sky-seo-inline-field">
                                    <label for="social_proof_max"><?php esc_html_e('Max count', 'sky360'); ?></label>
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
                            <?php esc_html_e('Personalized Greeting', 'sky360'); ?>
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
                                    <?php esc_html_e('Time-Based Greeting', 'sky360'); ?>
                                </label>
                            </div>
                            <p class="sky-seo-field-description">
                                <?php esc_html_e('Show different welcome messages based on visitor\'s local time', 'sky360'); ?>
                            </p>
                        </div>

                        <div id="personalized-greeting-settings" style="<?php echo empty($settings['enable_personalized_greeting']) ? 'display:none;' : ''; ?>">
                            <div class="sky-seo-greeting-grid">
                                <div class="sky-seo-form-group">
                                    <label for="greeting_morning">
                                        <span class="dashicons dashicons-admin-site-alt3"></span>
                                        <?php esc_html_e('Morning (6AM - 12PM)', 'sky360'); ?>
                                    </label>
                                    <textarea id="greeting_morning"
                                              name="<?php echo esc_attr($this->option_name); ?>[greeting_morning]"
                                              rows="2"
                                              class="sky-seo-textarea"><?php echo esc_textarea($settings['greeting_morning'] ?? __('Good morning! ☀️ How can we help you today?', 'sky360')); ?></textarea>
                                </div>
                                <div class="sky-seo-form-group">
                                    <label for="greeting_afternoon">
                                        <span class="dashicons dashicons-admin-site-alt3"></span>
                                        <?php esc_html_e('Afternoon (12PM - 6PM)', 'sky360'); ?>
                                    </label>
                                    <textarea id="greeting_afternoon"
                                              name="<?php echo esc_attr($this->option_name); ?>[greeting_afternoon]"
                                              rows="2"
                                              class="sky-seo-textarea"><?php echo esc_textarea($settings['greeting_afternoon'] ?? __('Good afternoon! How can we assist you?', 'sky360')); ?></textarea>
                                </div>
                                <div class="sky-seo-form-group">
                                    <label for="greeting_evening">
                                        <span class="dashicons dashicons-admin-site-alt3"></span>
                                        <?php esc_html_e('Evening (6PM - 10PM)', 'sky360'); ?>
                                    </label>
                                    <textarea id="greeting_evening"
                                              name="<?php echo esc_attr($this->option_name); ?>[greeting_evening]"
                                              rows="2"
                                              class="sky-seo-textarea"><?php echo esc_textarea($settings['greeting_evening'] ?? __('Good evening! How can we help you?', 'sky360')); ?></textarea>
                                </div>
                                <div class="sky-seo-form-group">
                                    <label for="greeting_night">
                                        <span class="dashicons dashicons-admin-site-alt3"></span>
                                        <?php esc_html_e('Night (10PM - 6AM)', 'sky360'); ?>
                                    </label>
                                    <textarea id="greeting_night"
                                              name="<?php echo esc_attr($this->option_name); ?>[greeting_night]"
                                              rows="2"
                                              class="sky-seo-textarea"><?php echo esc_textarea($settings['greeting_night'] ?? __('Hello! Thanks for reaching out. How can we help?', 'sky360')); ?></textarea>
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
                            <?php esc_html_e('QR Code Generator', 'sky360'); ?>
                        </h3>
                    </div>

                    <div class="sky-seo-config-card-body">
                        <p class="sky-seo-field-description" style="margin-bottom: 20px;">
                            <?php esc_html_e('Generate a QR code for your WhatsApp number. Perfect for print materials, business cards, and in-store displays.', 'sky360'); ?>
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
                                        <?php esc_html_e('Download PNG', 'sky360'); ?>
                                    </a>
                                    <a href="<?php echo esc_url(str_replace('size=200x200', 'size=500x500', $qr_url) . '&format=svg'); ?>"
                                       download="whatsapp-qr-code.svg"
                                       class="button button-secondary">
                                        <span class="dashicons dashicons-download"></span>
                                        <?php esc_html_e('Download SVG', 'sky360'); ?>
                                    </a>
                                </div>
                            </div>
                        <?php else: ?>
                            <div class="sky-seo-notice">
                                <span class="dashicons dashicons-warning"></span>
                                <?php esc_html_e('Please configure your WhatsApp phone number first to generate a QR code.', 'sky360'); ?>
                            </div>
                        <?php endif; ?>
                    </div>
                </div>

                <!-- Save Button -->
                <div class="sky-seo-form-actions">
                    <button type="submit" name="submit" class="button button-primary button-large">
                        <span class="dashicons dashicons-saved"></span>
                        <?php esc_html_e('Save Settings', 'sky360'); ?>
                    </button>
                    <span class="spinner"></span>
                    <span class="sky-seo-success-message" style="display:none;">
                        <span class="dashicons dashicons-yes-alt"></span>
                        <?php esc_html_e('Settings saved successfully!', 'sky360'); ?>
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
            wp_send_json_error(['message' => __('Security check failed', 'sky360')]);
        }
        
        // Check capabilities
        if (!current_user_can('manage_options')) {
            wp_send_json_error(['message' => __('Permission denied', 'sky360')]);
        }
        
        $message = sanitize_textarea_field($_POST['message'] ?? '');
        $source = sanitize_text_field($_POST['source'] ?? '');
        
        $link = $this->generate_trackable_link($message, $source);
        
        if (empty($link)) {
            wp_send_json_error(['message' => __('Please configure WhatsApp phone number first', 'sky360')]);
        }
        
        wp_send_json_success([
            'link' => $link,
            'message' => __('Trackable link generated successfully!', 'sky360')
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
            'status_text' => __('Typically replies instantly', 'sky360'),
            'description' => __('Hi there! How can I help you?', 'sky360'),
            'show_verified' => false,
            'float_position' => 'bottom-right',
            'show_popup' => true,
            'popup_delay' => 0,
            'default_message' => '',
            'start_chat_text' => __('Start Chat', 'sky360'),
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
            'offline_message' => __('We are currently offline. Leave a message and we\'ll get back to you!', 'sky360'),
            'timezone' => wp_timezone_string(),
            // Typing Animation
            'enable_typing_animation' => true,
            'typing_duration' => 1500,
            // Social Proof
            'enable_social_proof' => false,
            'social_proof_text' => __('%count% people contacted us today', 'sky360'),
            'social_proof_min' => 5,
            'social_proof_max' => 25,
            // Personalized Greeting
            'enable_personalized_greeting' => false,
            'greeting_morning' => __('Good morning! ☀️ How can we help you today?', 'sky360'),
            'greeting_afternoon' => __('Good afternoon! How can we assist you?', 'sky360'),
            'greeting_evening' => __('Good evening! How can we help you?', 'sky360'),
            'greeting_night' => __('Hello! Thanks for reaching out. How can we help?', 'sky360'),
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
            'enable_social_proof', 'enable_personalized_greeting'
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
                    __('Please enter a valid WhatsApp phone number with country code (e.g., +447123456789).', 'sky360'),
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
        $sanitized['status_text'] = sanitize_text_field($input['status_text'] ?? __('Typically replies instantly', 'sky360'));
        $sanitized['start_chat_text'] = sanitize_text_field($input['start_chat_text'] ?? __('Start Chat', 'sky360'));
        
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
            wp_send_json_error(['message' => __('Security check failed', 'sky360')]);
        }
        
        // Check capabilities
        if (!current_user_can('manage_options')) {
            wp_send_json_error(['message' => __('Permission denied', 'sky360')]);
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
                    'message' => __('Settings saved successfully!', 'sky360'),
                    'settings' => $sanitized
                ]);
            } else {
                // Check if values actually changed
                $current = get_option($this->option_name);
                if ($current === $sanitized) {
                    wp_send_json_success([
                        'message' => __('Settings saved successfully!', 'sky360'),
                        'settings' => $sanitized
                    ]);
                } else {
                    wp_send_json_error([
                        'message' => __('Failed to save settings. Please try again.', 'sky360')
                    ]);
                }
            }
            
        } catch (Exception $e) {
            wp_send_json_error([
                'message' => sprintf(__('Error: %s', 'sky360'), $e->getMessage())
            ]);
        }
    }
}