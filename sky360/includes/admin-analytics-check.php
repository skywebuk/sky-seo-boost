<?php
// Prevent direct access
if (!defined('ABSPATH')) {
    exit;
}

// Analytics Check Tab
function sky_seo_analytics_check_tab() {
    // Sanitize GET parameter
    $refresh = isset($_GET['refresh']) && sanitize_text_field(wp_unslash($_GET['refresh'])) === '1';
    $transient_key = 'sky_seo_analytics_check';
    $results = $refresh ? false : get_transient($transient_key);

    if ($results === false) {
        $results = sky_seo_check_analytics_codes();
        set_transient($transient_key, $results, 24 * HOUR_IN_SECONDS);
    }

    $settings = get_option('sky_seo_settings', []);
    ?>
    <div class="sky-seo-analytics-wrap">
        <form method="post" action="options.php">
            <?php
            settings_fields('sky_seo_settings');
            do_settings_sections('sky_seo_settings');
            ?>
            
            <!-- Google Analytics -->
            <div class="sky-seo-tracking-card">
                <div class="sky-seo-tracking-header">
                    <div class="sky-seo-tracking-title">
                        <img src="data:image/svg+xml;base64,PHN2ZyB3aWR0aD0iMjQiIGhlaWdodD0iMjQiIHZpZXdCb3g9IjAgMCAyNCAyNCIgZmlsbD0ibm9uZSIgeG1sbnM9Imh0dHA6Ly93d3cudzMub3JnLzIwMDAvc3ZnIj4KPHBhdGggZD0iTTEyIDJDNi40OCAyIDIgNi40OCAyIDEyUzYuNDggMjIgMTIgMjJTMjIgMTcuNTIgMjIgMTJTMTcuNTIgMiAxMiAyWk0xMiA5QzEzLjY2IDkgMTUgMTAuMzQgMTUgMTJTMTMuNjYgMTUgMTIgMTVTOSAxMy42NiA5IDEyUzEwLjM0IDkgMTIgOVpNMTIgMjBDOS4zMyAyMCA3LjA2IDE4LjUgNS44NyAxNi4zM0M1LjkgMTUuMSA5LjA5IDE0LjA4IDEyIDE0LjA4QzE0LjkxIDE0LjA4IDE4LjEgMTUuMSAxOC4xMyAxNi4zM0MxNi45NCAxOC41IDE0LjY3IDIwIDEyIDIwWiIgZmlsbD0iIzQyODVGNCIvPgo8L3N2Zz4=" alt="Google Analytics" />
                        <h3><?php _e('Google Analytics', 'sky360'); ?></h3>
                    </div>
                    <div class="sky-seo-detection-status">
                        <span class="sky-seo-status-label"><?php _e('Current Detection Status:', 'sky360'); ?></span>
                        <span class="sky-seo-status-indicator <?php echo $results['analytics']['detected'] ? 'detected' : 'not-detected'; ?>">
                            <?php echo $results['analytics']['detected'] ? __('Detected', 'sky360') : __('Not Detected', 'sky360'); ?>
                        </span>
                    </div>
                </div>
                <div class="sky-seo-tracking-body">
                    <div class="sky-seo-field-group">
                        <label for="ga_measurement_id"><?php _e('Measurement ID', 'sky360'); ?></label>
                        <input type="text" name="sky_seo_settings[ga_measurement_id]" id="ga_measurement_id" 
                               value="<?php echo esc_attr(isset($settings['ga_measurement_id']) ? $settings['ga_measurement_id'] : ''); ?>" 
                               class="regular-text" placeholder="G-XXXXXXXXXX" />
                        <p class="description"><?php _e('Enter your Google Analytics 4 Measurement ID', 'sky360'); ?></p>
                    </div>
                    <div class="sky-seo-field-group">
                        <label class="sky-seo-checkbox-label">
                            <input type="checkbox" name="sky_seo_settings[ga_enabled]" value="1" 
                                   <?php checked(isset($settings['ga_enabled']) ? $settings['ga_enabled'] : false); ?> />
                            <?php _e('Enable Google Analytics tracking', 'sky360'); ?>
                        </label>
                    </div>
                </div>
            </div>

            <!-- Google Ads - IMPROVED VERSION -->
            <div class="sky-seo-tracking-card">
                <div class="sky-seo-tracking-header">
                    <div class="sky-seo-tracking-title">
                        <img src="data:image/svg+xml;base64,PHN2ZyB3aWR0aD0iMjQiIGhlaWdodD0iMjQiIHZpZXdCb3g9IjAgMCAyNCAyNCIgZmlsbD0ibm9uZSIgeG1sbnM9Imh0dHA6Ly93d3cudzMub3JnLzIwMDAvc3ZnIj4KPHBhdGggZD0iTTE5LjUgM0gxNi41TDYgMjFIMTBMMTkuNSAzWiIgZmlsbD0iIzFBNzNFOCIvPgo8cGF0aCBkPSJNNCAxNi41VjIwLjVIOEwxMC41IDE1LjVMOCAxMC41TDQgMTYuNVoiIGZpbGw9IiMzNEE4NTMiLz4KPHBhdGggZD0iTTE2IDE2LjVMMTMuNSAyMS41SDE3LjVIMjBWMTYuNUgxNloiIGZpbGw9IiNGQkJDMDQiLz4KPC9zdmc+" alt="Google Ads" />
                        <h3><?php _e('Google Ads', 'sky360'); ?></h3>
                    </div>
                    <div class="sky-seo-detection-status">
                        <span class="sky-seo-status-label"><?php _e('Current Detection Status:', 'sky360'); ?></span>
                        <span class="sky-seo-status-indicator <?php echo $results['ads']['detected'] ? 'detected' : 'not-detected'; ?>">
                            <?php echo $results['ads']['detected'] ? __('Detected', 'sky360') : __('Not Detected', 'sky360'); ?>
                        </span>
                    </div>
                </div>
                <div class="sky-seo-tracking-body">
                    <div class="sky-seo-field-group">
                        <label for="google_ads_conversion_id"><?php _e('Conversion ID', 'sky360'); ?></label>
                        <input type="text" name="sky_seo_settings[google_ads_conversion_id]" id="google_ads_conversion_id" 
                               value="<?php echo esc_attr(isset($settings['google_ads_conversion_id']) ? $settings['google_ads_conversion_id'] : ''); ?>" 
                               class="regular-text" placeholder="AW-XXXXXXXXX" />
                        <p class="description"><?php _e('Enter your Google Ads Conversion ID', 'sky360'); ?></p>
                    </div>
                    
                    <!-- NEW: Conversion Type Dropdown -->
                    <div class="sky-seo-field-group">
                        <label for="google_ads_conversion_type"><?php _e('Conversion Type', 'sky360'); ?></label>
                        <select name="sky_seo_settings[google_ads_conversion_type]" id="google_ads_conversion_type" class="regular-text">
                            <option value="woocommerce" <?php selected(isset($settings['google_ads_conversion_type']) ? $settings['google_ads_conversion_type'] : 'woocommerce', 'woocommerce'); ?>>
                                <?php _e('WooCommerce Purchase', 'sky360'); ?>
                            </option>
                            <option value="form_submission" <?php selected(isset($settings['google_ads_conversion_type']) ? $settings['google_ads_conversion_type'] : '', 'form_submission'); ?>>
                                <?php _e('Form Submission (Thank You Page)', 'sky360'); ?>
                            </option>
                            <option value="custom" <?php selected(isset($settings['google_ads_conversion_type']) ? $settings['google_ads_conversion_type'] : '', 'custom'); ?>>
                                <?php _e('Custom Event', 'sky360'); ?>
                            </option>
                        </select>
                        <p class="description"><?php _e('Select the type of conversion you want to track', 'sky360'); ?></p>
                    </div>
                    
                    <!-- Conversion Label (shows for all types) -->
                    <div class="sky-seo-field-group">
                        <label for="google_ads_conversion_label"><?php _e('Conversion Label', 'sky360'); ?></label>
                        <input type="text" name="sky_seo_settings[google_ads_conversion_label]" id="google_ads_conversion_label" 
                               value="<?php echo esc_attr(isset($settings['google_ads_conversion_label']) ? $settings['google_ads_conversion_label'] : ''); ?>" 
                               class="regular-text" placeholder="e.g. AQsOCIO1ou8aELe0rocD" />
                        <p class="description">
                            <?php _e('Enter ONLY the conversion label (the part after the slash).', 'sky360'); ?><br>
                            <strong><?php _e('Example:', 'sky360'); ?></strong> <?php _e('If Google Ads shows', 'sky360'); ?> <code>AW-820746807/AQsOCIO1ou8aELe0rocD</code>, <?php _e('enter only', 'sky360'); ?> <code>AQsOCIO1ou8aELe0rocD</code>
                        </p>
                    </div>
                    
                    <!-- Thank You Page URL (shows only for form_submission type) -->
                    <div class="sky-seo-field-group" id="thank_you_page_field" style="<?php echo (isset($settings['google_ads_conversion_type']) && $settings['google_ads_conversion_type'] === 'form_submission') ? '' : 'display:none;'; ?>">
                        <label for="google_ads_thank_you_page"><?php _e('Thank You Page', 'sky360'); ?></label>
                        <?php
                        // Get all pages for dropdown
                        $pages = get_pages();
                        $selected_page_id = isset($settings['google_ads_thank_you_page_id']) ? $settings['google_ads_thank_you_page_id'] : '';
                        ?>
                        <select name="sky_seo_settings[google_ads_thank_you_page_id]" id="google_ads_thank_you_page_id" class="regular-text">
                            <option value=""><?php _e('— Select Thank You Page —', 'sky360'); ?></option>
                            <?php
                            foreach ($pages as $page) {
                                $page_url = str_replace(home_url(), '', get_permalink($page->ID));
                                echo '<option value="' . esc_attr($page->ID) . '" ' . selected($selected_page_id, $page->ID, false) . '>';
                                echo esc_html($page->post_title) . ' (' . esc_html($page_url) . ')';
                                echo '</option>';
                            }
                            ?>
                        </select>
                        <p class="description"><?php _e('Select the thank you page where users are redirected after form submission', 'sky360'); ?></p>
                        
                        <!-- Advanced option: Custom URL -->
                        <div style="margin-top: 10px;">
                            <label>
                                <input type="checkbox" id="use_custom_thank_you_url" <?php checked(!empty($settings['google_ads_custom_thank_you_url'])); ?> />
                                <?php _e('Use custom URL instead', 'sky360'); ?>
                            </label>
                            <div id="custom_thank_you_url_field" style="margin-top: 5px; <?php echo empty($settings['google_ads_custom_thank_you_url']) ? 'display:none;' : ''; ?>">
                                <input type="text" name="sky_seo_settings[google_ads_custom_thank_you_url]" 
                                       value="<?php echo esc_attr(isset($settings['google_ads_custom_thank_you_url']) ? $settings['google_ads_custom_thank_you_url'] : ''); ?>" 
                                       class="regular-text" placeholder="/thank-you" />
                                <p class="description"><?php _e('Enter custom URL path (e.g., /thank-you)', 'sky360'); ?></p>
                            </div>
                        </div>
                    </div>
                    
                    <!-- Conversion Value (optional) -->
                    <div class="sky-seo-field-group" id="conversion_value_field" style="<?php echo (isset($settings['google_ads_conversion_type']) && $settings['google_ads_conversion_type'] === 'woocommerce') ? 'display:none;' : ''; ?>">
                        <label for="google_ads_conversion_value"><?php _e('Conversion Value', 'sky360'); ?> <span class="description">(<?php _e('Optional', 'sky360'); ?>)</span></label>
                        <input type="number" step="0.01" name="sky_seo_settings[google_ads_conversion_value]" id="google_ads_conversion_value" 
                               value="<?php echo esc_attr(isset($settings['google_ads_conversion_value']) ? $settings['google_ads_conversion_value'] : ''); ?>" 
                               class="small-text" placeholder="0.00" />
                        <p class="description"><?php _e('Fixed conversion value (leave empty for dynamic WooCommerce values)', 'sky360'); ?></p>
                    </div>
                    
                    <div class="sky-seo-field-group">
                        <label class="sky-seo-checkbox-label">
                            <input type="checkbox" name="sky_seo_settings[google_ads_enabled]" value="1" 
                                   <?php checked(isset($settings['google_ads_enabled']) ? $settings['google_ads_enabled'] : false); ?> />
                            <?php _e('Enable Google Ads tracking', 'sky360'); ?>
                        </label>
                    </div>
                    
                    <!-- NEW: Test Google Ads Button -->
                    <div class="sky-seo-field-group">
                        <label><?php _e('Test Configuration', 'sky360'); ?></label>
                        <div>
                            <button type="button" id="test-google-ads" class="button button-secondary">
                                <?php _e('Test Google Ads Code', 'sky360'); ?>
                            </button>
                            <p class="description"><?php _e('Verify your Google Ads configuration is working correctly', 'sky360'); ?></p>
                            <div id="test-result"></div>
                        </div>
                    </div>
                </div>
            </div>

            <!-- Google Tag Manager -->
            <div class="sky-seo-tracking-card">
                <div class="sky-seo-tracking-header">
                    <div class="sky-seo-tracking-title">
                        <img src="data:image/svg+xml;base64,PHN2ZyB3aWR0aD0iMjQiIGhlaWdodD0iMjQiIHZpZXdCb3g9IjAgMCAyNCAyNCIgZmlsbD0ibm9uZSIgeG1sbnM9Imh0dHA6Ly93d3cudzMub3JnLzIwMDAvc3ZnIj4KPHBhdGggZD0iTTEyIDJDNi40OCAyIDIgNi40OCAyIDEyUzYuNDggMjIgMTIgMjJTMjIgMTcuNTIgMjIgMTJTMTcuNTIgMiAxMiAyWk0xNyAxN0g3VjdIMTdWMTdaIiBmaWxsPSIjNDI4NUY0Ii8+CjxwYXRoIGQ9Ik05IDlIMTVWMTVIOVY5WiIgZmlsbD0iI0ZGRiIvPgo8L3N2Zz4=" alt="Google Tag Manager" />
                        <h3><?php _e('Google Tag Manager', 'sky360'); ?></h3>
                    </div>
                    <div class="sky-seo-detection-status">
                        <span class="sky-seo-status-label"><?php _e('Current Detection Status:', 'sky360'); ?></span>
                        <span class="sky-seo-status-indicator <?php echo $results['gtm']['detected'] ? 'detected' : 'not-detected'; ?>">
                            <?php echo $results['gtm']['detected'] ? __('Detected', 'sky360') : __('Not Detected', 'sky360'); ?>
                        </span>
                    </div>
                </div>
                <div class="sky-seo-tracking-body">
                    <div class="sky-seo-field-group">
                        <label for="gtm_container_id"><?php _e('Container ID', 'sky360'); ?></label>
                        <input type="text" name="sky_seo_settings[gtm_container_id]" id="gtm_container_id" 
                               value="<?php echo esc_attr(isset($settings['gtm_container_id']) ? $settings['gtm_container_id'] : ''); ?>" 
                               class="regular-text" placeholder="GTM-XXXXXXX" />
                        <p class="description"><?php _e('Enter your Google Tag Manager Container ID', 'sky360'); ?></p>
                    </div>
                    <div class="sky-seo-field-group">
                        <label class="sky-seo-checkbox-label">
                            <input type="checkbox" name="sky_seo_settings[gtm_enabled]" value="1" 
                                   <?php checked(isset($settings['gtm_enabled']) ? $settings['gtm_enabled'] : false); ?> />
                            <?php _e('Enable Google Tag Manager', 'sky360'); ?>
                        </label>
                    </div>
                </div>
            </div>

            <!-- Meta Pixel / Facebook Pixel -->
            <div class="sky-seo-tracking-card">
                <div class="sky-seo-tracking-header">
                    <div class="sky-seo-tracking-title">
                        <img src="data:image/svg+xml;base64,PHN2ZyB3aWR0aD0iMjQiIGhlaWdodD0iMjQiIHZpZXdCb3g9IjAgMCAyNCAyNCIgZmlsbD0ibm9uZSIgeG1sbnM9Imh0dHA6Ly93d3cudzMub3JnLzIwMDAvc3ZnIj4KPHBhdGggZD0iTTI0IDEyLjA3M0MyNCAxOC4wNjIgMTkuNDg2IDIzLjAyNyAxMy42IDIzLjkzMVYxNS4xOEgxNi44MDVMMTcuNDA3IDEyLjA3M0gxMy42VjkuNzFDMTMuNiA4LjcwMyAxNC4wODcgNy43MjMgMTUuNjA4IDcuNzIzSDE3LjU1NlY1LjA1MkMxNy41NTYgNS4wNTIgMTYuNDUgNC45MDQgMTUuMzA0IDQuOTA0QzEzLjAwMiA0LjkwNCAxMS40IDYuNTg3IDExLjQgOS4zNjJWMTIuMDczSDguNjVWMTUuMThIMTEuNFYyMy45MzFDNS41MTQgMjMuMDI3IDEgMTguMDYyIDEgMTIuMDczQzEgNS40MDcgNS44MzMgMCAxMi41IDAgMTkuMTY3IDAgMjQgNS40MDcgMjQgMTIuMDczWiIgZmlsbD0iIzE4NzdGMiIvPgo8L3N2Zz4=" alt="Meta Pixel" />
                        <h3><?php _e('Meta Pixel (Facebook)', 'sky360'); ?></h3>
                    </div>
                    <div class="sky-seo-detection-status">
                        <span class="sky-seo-status-label"><?php _e('Current Detection Status:', 'sky360'); ?></span>
                        <span class="sky-seo-status-indicator <?php echo $results['facebook_pixel']['detected'] ? 'detected' : 'not-detected'; ?>">
                            <?php echo $results['facebook_pixel']['detected'] ? __('Detected', 'sky360') : __('Not Detected', 'sky360'); ?>
                        </span>
                    </div>
                </div>
                <div class="sky-seo-tracking-body">
                    <div class="sky-seo-field-group">
                        <label for="meta_pixel_id"><?php _e('Pixel ID', 'sky360'); ?></label>
                        <input type="text" name="sky_seo_settings[meta_pixel_id]" id="meta_pixel_id" 
                               value="<?php echo esc_attr(isset($settings['meta_pixel_id']) ? $settings['meta_pixel_id'] : ''); ?>" 
                               class="regular-text" placeholder="XXXXXXXXXXXXXXXX" />
                        <p class="description"><?php _e('Enter your Meta Pixel ID', 'sky360'); ?></p>
                    </div>
                    <div class="sky-seo-field-group">
                        <label class="sky-seo-checkbox-label">
                            <input type="checkbox" name="sky_seo_settings[meta_pixel_enabled]" value="1" 
                                   <?php checked(isset($settings['meta_pixel_enabled']) ? $settings['meta_pixel_enabled'] : false); ?> />
                            <?php _e('Enable Meta Pixel tracking', 'sky360'); ?>
                        </label>
                    </div>
                </div>
            </div>

            <!-- Test Mode & Actions -->
            <div class="sky-seo-tracking-card">
                <div class="sky-seo-tracking-header">
                    <div class="sky-seo-tracking-title">
                        <h3><?php _e('Test Mode & Actions', 'sky360'); ?></h3>
                    </div>
                </div>
                <div class="sky-seo-tracking-body">
                    <div class="sky-seo-field-group">
                        <p class="description"><?php _e('Enable test mode to show tracking codes only to administrators. This helps you verify tracking codes are working correctly before going live.', 'sky360'); ?></p>
                        <label class="sky-seo-checkbox-label">
                            <input type="checkbox" name="sky_seo_settings[tracking_test_mode]" value="1" 
                                   <?php checked(isset($settings['tracking_test_mode']) ? $settings['tracking_test_mode'] : false); ?> />
                            <?php _e('Enable Test Mode', 'sky360'); ?>
                        </label>
                    </div>
                    <div class="sky-seo-actions">
                        <?php submit_button(__('Save Tracking Settings', 'sky360'), 'primary', 'submit', false); ?>
                        <a href="?page=sky360-settings&tab=tracking&refresh=1" class="button button-secondary"><?php _e('Refresh Detection Status', 'sky360'); ?></a>
                    </div>
                </div>
            </div>
        </form>
    </div>
    
    <!-- JavaScript for dynamic form behavior -->
    <script type="text/javascript">
    jQuery(document).ready(function($) {
        // Show/hide fields based on conversion type
        $('#google_ads_conversion_type').on('change', function() {
            var conversionType = $(this).val();
            
            if (conversionType === 'form_submission') {
                $('#thank_you_page_field').show();
                $('#conversion_value_field').show();
            } else if (conversionType === 'woocommerce') {
                $('#thank_you_page_field').hide();
                $('#conversion_value_field').hide();
            } else { // custom
                $('#thank_you_page_field').hide();
                $('#conversion_value_field').show();
            }
        });
        
        // Handle custom thank you URL toggle
        $('#use_custom_thank_you_url').on('change', function() {
            if ($(this).is(':checked')) {
                $('#custom_thank_you_url_field').show();
                $('#google_ads_thank_you_page_id').prop('disabled', true);
            } else {
                $('#custom_thank_you_url_field').hide();
                $('#google_ads_thank_you_page_id').prop('disabled', false);
                $('input[name="sky_seo_settings[google_ads_custom_thank_you_url]"]').val('');
            }
        });
        
        // Auto-fix conversion label if user pastes full string
        $('#google_ads_conversion_label').on('blur', function() {
            var value = $(this).val().trim();
            
            // Check if user pasted the full conversion string (contains /)
            if (value.includes('/')) {
                // Extract only the part after the slash
                var parts = value.split('/');
                if (parts.length === 2) {
                    $(this).val(parts[1]);
                    
                    // Show a helpful notice
                    var $field = $(this).closest('.sky-seo-field-group');
                    var $notice = $('<div class="notice notice-info inline" style="margin-top: 5px;"><p>✓ Automatically extracted the conversion label from the full string.</p></div>');
                    $field.find('.notice').remove(); // Remove any existing notices
                    $field.append($notice);
                    
                    // Remove notice after 3 seconds
                    setTimeout(function() {
                        $notice.fadeOut(function() {
                            $(this).remove();
                        });
                    }, 3000);
                }
            }
        });
        
        // Test Google Ads Code
        $('#test-google-ads').on('click', function() {
            var $button = $(this);
            var $result = $('#test-result');
            var conversionId = $('#google_ads_conversion_id').val();
            var conversionLabel = $('#google_ads_conversion_label').val();
            var isEnabled = $('input[name="sky_seo_settings[google_ads_enabled]"]').is(':checked');
            
            // Reset result
            $result.removeClass('success error warning').text('');
            
            if (!conversionId) {
                $result.addClass('error').text('Please enter a Conversion ID first');
                return;
            }
            
            if (!isEnabled) {
                $result.addClass('error').text('Please enable Google Ads tracking first');
                return;
            }
            
            $button.prop('disabled', true).text('Testing...');
            
            // Make AJAX request to check frontend
            $.ajax({
                url: ajaxurl,
                type: 'POST',
                data: {
                    action: 'sky_seo_test_google_ads',
                    nonce: '<?php echo wp_create_nonce('sky_seo_test_ads'); ?>',
                    conversion_id: conversionId
                },
                success: function(response) {
                    if (response.success) {
                        var html = '<div class="test-header">✓ Settings saved correctly!</div>';
                        
                        // Configuration details
                        html += '<div class="test-details">';
                        html += '<strong>Conversion ID:</strong> ' + conversionId + '<br>';
                        if (conversionLabel) {
                            html += '<strong>Conversion Label:</strong> ' + conversionLabel + '<br>';
                        }
                        html += '<strong>Test Mode:</strong> ' + ($('input[name="sky_seo_settings[tracking_test_mode]"]').is(':checked') ? 'ON (only admins see code)' : 'OFF');
                        html += '</div>';
                        
                        // Next steps
                        html += '<div class="test-steps">';
                        html += '<strong>Next Steps to Verify:</strong>';
                        html += '<ol>';
                        html += '<li>Visit your site\'s frontend (not admin area)</li>';
                        html += '<li>Open browser console (F12 → Console tab)</li>';
                        html += '<li>Look for "Sky SEO Boost - Google Ads loaded" message</li>';
                        html += '</ol>';
                        html += '<button type="button" class="button button-secondary" onclick="window.open(\'' + '<?php echo home_url(); ?>' + '\', \'_blank\')">Open Site in New Tab</button>';
                        html += '</div>';
                        
                        $result.removeClass('error').addClass('success').html(html);
                        
                    } else {
                        $result.removeClass('success').addClass('error').html('<div class="test-header">✗ ' + (response.data || 'Test failed') + '</div>');
                    }
                },
                error: function() {
                    $result.addClass('error').text('Error running test. Please try again.');
                },
                complete: function() {
                    $button.prop('disabled', false).text('Test Google Ads Code');
                }
            });
        });
    });
    </script>
    
    <style type="text/css">
    #test-result { margin-top: 15px; }
    
    #test-result.success { 
        background: #fff;
        border: 1px solid #46b450;
        border-left: 4px solid #46b450;
        padding: 15px;
        border-radius: 4px;
        box-shadow: 0 1px 1px rgba(0,0,0,.04);
    }
    
    #test-result.error { 
        background: #fff;
        border: 1px solid #dc3232;
        border-left: 4px solid #dc3232;
        padding: 15px;
        border-radius: 4px;
        box-shadow: 0 1px 1px rgba(0,0,0,.04);
    }
    
    #test-result .test-header {
        font-weight: 600;
        color: #46b450;
        margin-bottom: 10px;
        font-size: 14px;
    }
    
    #test-result.error .test-header {
        color: #dc3232;
    }
    
    #test-result .test-details {
        background: #f8f9fa;
        padding: 10px;
        border-radius: 3px;
        margin: 10px 0;
        font-family: monospace;
        font-size: 13px;
        line-height: 1.6;
    }
    
    #test-result .test-details strong {
        color: #23282d;
        font-weight: 600;
    }
    
    #test-result .test-steps {
        margin-top: 15px;
        padding-top: 15px;
        border-top: 1px solid #e1e1e1;
    }
    
    #test-result .test-steps ol {
        margin: 10px 0 10px 20px;
        color: #555;
    }
    
    #test-result .test-steps li {
        margin-bottom: 5px;
    }
    
    #test-result .button-secondary {
        margin-top: 10px;
    }
    
    /* Clean up the notice for auto-fix */
    .sky-seo-field-group .notice {
        margin: 10px 0 0 0;
        padding: 8px 12px;
    }
    
    .sky-seo-field-group .notice p {
        margin: 0;
        font-size: 13px;
    }
    </style>
    <?php
}

// Check for Analytics Codes
function sky_seo_check_analytics_codes() {
    $results = [
        'analytics' => ['detected' => false],
        'ads' => ['detected' => false],
        'gtm' => ['detected' => false],
        'facebook_pixel' => ['detected' => false],
    ];

    $response = wp_remote_get(home_url(), ['timeout' => 10, 'sslverify' => false]);
    if (is_wp_error($response)) {
        return $results;
    }

    $body = wp_remote_retrieve_body($response);
    if (empty($body)) {
        return $results;
    }

    // Google Analytics (gtag.js or analytics.js)
    if (preg_match('/(UA-|G-)[A-Z0-9-]+\b/', $body) || strpos($body, 'analytics.js') !== false || strpos($body, 'gtag.js') !== false) {
        $results['analytics']['detected'] = true;
    }

    // Google Ads (adsbygoogle.js or conversion tracking)
    if (strpos($body, 'adsbygoogle.js') !== false || strpos($body, 'googleadservices.com') !== false || preg_match('/AW-[0-9]+/', $body)) {
        $results['ads']['detected'] = true;
    }

    // Google Tag Manager (gtm.js or GTM- ID)
    if (strpos($body, 'gtm.js') !== false || preg_match('/GTM-[A-Z0-9]+/', $body)) {
        $results['gtm']['detected'] = true;
    }

    // Facebook Pixel (Meta Pixel)
    if (strpos($body, 'fbq(') !== false || strpos($body, 'facebook.com/tr/') !== false) {
        $results['facebook_pixel']['detected'] = true;
    }

    return $results;
}