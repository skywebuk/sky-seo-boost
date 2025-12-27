<?php
/**
 * Sky SEO Boost - Two-Step Verification Settings Page
 *
 * Adds a settings tab for configuring two-step verification.
 *
 * @package Sky_SEO_Boost
 * @since 4.2.0
 */

// Prevent direct access
if (!defined('ABSPATH')) {
    exit;
}

/**
 * Add two-step verification tab to settings page.
 *
 * @param string $active_tab The currently active tab.
 */
function sky_seo_two_step_add_settings_tab($active_tab) {
    // Safety check: Only show tab if core class is loaded
    if (!class_exists('Sky_SEO_Two_Step_Core')) {
        return;
    }

    ?>
    <a href="?page=sky360-settings&tab=two-step"
       class="nav-tab <?php echo $active_tab === 'two-step' ? 'nav-tab-active' : ''; ?>">
        <span class="dashicons dashicons-shield" style="vertical-align: middle;"></span>
        <?php esc_html_e('Two-Step Verification', 'sky360'); ?>
    </a>
    <?php
}
add_action('sky_seo_settings_tabs', 'sky_seo_two_step_add_settings_tab');

/**
 * Render two-step verification settings content.
 *
 * @param string $active_tab The currently active tab.
 */
function sky_seo_two_step_render_settings($active_tab) {
    if ($active_tab !== 'two-step') {
        return;
    }

    // Safety check: Ensure the core class is loaded
    if (!class_exists('Sky_SEO_Two_Step_Core')) {
        echo '<div class="notice notice-error"><p>' . __('Two-step verification system is not properly loaded. Please contact support.', 'sky360') . '</p></div>';
        return;
    }

    $settings = get_option('sky_seo_settings', array());
    $is_enabled = isset($settings['two_step_enabled']) && $settings['two_step_enabled'];
    $enforce_roles = isset($settings['two_step_enforce_roles']) ? $settings['two_step_enforce_roles'] : array();

    // Get all available roles
    global $wp_roles;
    $all_roles = $wp_roles->get_names();

    // Get statistics
    $stats = sky_seo_two_step_get_statistics();

    ?>
    <div class="sky-seo-two-step-settings">
        <h2><?php esc_html_e('Two-Step Verification Settings', 'sky360'); ?></h2>

        <div class="sky-seo-card">
            <h3><?php esc_html_e('Email-Based Two-Step Verification', 'sky360'); ?></h3>

            <p class="description">
                <?php esc_html_e('Two-step verification adds an extra layer of security to your WordPress site. When enabled, users will receive a verification code via email each time they log in.', 'sky360'); ?>
            </p>

            <form method="post" action="">
                <?php wp_nonce_field('sky_seo_two_step_settings', 'sky_seo_two_step_nonce'); ?>

                <table class="form-table">
                    <tr>
                        <th scope="row">
                            <label for="two_step_enabled">
                                <?php esc_html_e('Enable Two-Step Verification', 'sky360'); ?>
                            </label>
                        </th>
                        <td>
                            <label class="sky-seo-toggle">
                                <input type="checkbox"
                                       name="two_step_enabled"
                                       id="two_step_enabled"
                                       value="1"
                                       <?php checked($is_enabled); ?> />
                                <span class="sky-seo-toggle-slider"></span>
                            </label>
                            <p class="description">
                                <?php esc_html_e('Enable email-based two-step verification system for your WordPress site.', 'sky360'); ?>
                            </p>
                        </td>
                    </tr>

                    <tr class="two-step-enforce-row" <?php echo !$is_enabled ? 'style="display:none;"' : ''; ?>>
                        <th scope="row">
                            <?php esc_html_e('Enforce for User Roles', 'sky360'); ?>
                        </th>
                        <td>
                            <fieldset>
                                <legend class="screen-reader-text">
                                    <span><?php esc_html_e('Select user roles', 'sky360'); ?></span>
                                </legend>

                                <div class="sky-seo-role-checkboxes">
                                    <?php foreach ($all_roles as $role_key => $role_name) : ?>
                                        <label style="display: block; margin-bottom: 8px;">
                                            <input type="checkbox"
                                                   name="two_step_enforce_roles[]"
                                                   value="<?php echo esc_attr($role_key); ?>"
                                                   <?php checked(in_array($role_key, $enforce_roles)); ?> />
                                            <?php echo esc_html($role_name); ?>
                                        </label>
                                    <?php endforeach; ?>
                                </div>

                                <p class="description">
                                    <?php esc_html_e('Users with these roles will be required to use two-step verification. Individual users can also enable it voluntarily from their profile.', 'sky360'); ?>
                                </p>
                            </fieldset>
                        </td>
                    </tr>

                    <tr class="two-step-token-row" <?php echo !$is_enabled ? 'style="display:none;"' : ''; ?>>
                        <th scope="row">
                            <label for="two_step_token_length">
                                <?php esc_html_e('Verification Code Length', 'sky360'); ?>
                            </label>
                        </th>
                        <td>
                            <input type="number"
                                   name="two_step_token_length"
                                   id="two_step_token_length"
                                   value="<?php echo esc_attr(isset($settings['two_step_token_length']) ? $settings['two_step_token_length'] : 6); ?>"
                                   min="6"
                                   max="12"
                                   class="small-text" />
                            <p class="description">
                                <?php esc_html_e('Number of characters in the verification code. Default: 6', 'sky360'); ?>
                            </p>
                        </td>
                    </tr>

                    <tr class="two-step-ttl-row" <?php echo !$is_enabled ? 'style="display:none;"' : ''; ?>>
                        <th scope="row">
                            <label for="two_step_token_ttl">
                                <?php esc_html_e('Code Expiration Time', 'sky360'); ?>
                            </label>
                        </th>
                        <td>
                            <input type="number"
                                   name="two_step_token_ttl"
                                   id="two_step_token_ttl"
                                   value="<?php echo esc_attr(isset($settings['two_step_token_ttl']) ? $settings['two_step_token_ttl'] : 15); ?>"
                                   min="5"
                                   max="60"
                                   class="small-text" />
                            <?php esc_html_e('minutes', 'sky360'); ?>
                            <p class="description">
                                <?php esc_html_e('How long verification codes remain valid. Default: 15 minutes', 'sky360'); ?>
                            </p>
                        </td>
                    </tr>
                </table>

                <?php submit_button(__('Save Two-Step Settings', 'sky360')); ?>
            </form>
        </div>

        <?php if ($is_enabled) : ?>
        <div class="sky-seo-card" style="margin-top: 20px;">
            <h3><?php esc_html_e('Two-Step Verification Statistics', 'sky360'); ?></h3>

            <div class="sky-seo-stats-grid">
                <div class="sky-seo-stat-box">
                    <div class="stat-icon">
                        <span class="dashicons dashicons-admin-users"></span>
                    </div>
                    <div class="stat-content">
                        <div class="stat-value"><?php echo number_format_i18n($stats['total_enabled']); ?></div>
                        <div class="stat-label"><?php esc_html_e('Users with 2FA Enabled', 'sky360'); ?></div>
                    </div>
                </div>

                <div class="sky-seo-stat-box">
                    <div class="stat-icon">
                        <span class="dashicons dashicons-lock"></span>
                    </div>
                    <div class="stat-content">
                        <div class="stat-value"><?php echo number_format_i18n($stats['enforced_count']); ?></div>
                        <div class="stat-label"><?php esc_html_e('Users with Enforced 2FA', 'sky360'); ?></div>
                    </div>
                </div>

                <div class="sky-seo-stat-box">
                    <div class="stat-icon">
                        <span class="dashicons dashicons-yes-alt"></span>
                    </div>
                    <div class="stat-content">
                        <div class="stat-value"><?php echo number_format_i18n($stats['voluntary_count']); ?></div>
                        <div class="stat-label"><?php esc_html_e('Voluntary 2FA Users', 'sky360'); ?></div>
                    </div>
                </div>

                <div class="sky-seo-stat-box">
                    <div class="stat-icon">
                        <span class="dashicons dashicons-warning"></span>
                    </div>
                    <div class="stat-content">
                        <div class="stat-value"><?php echo number_format_i18n($stats['not_enabled_count']); ?></div>
                        <div class="stat-label"><?php esc_html_e('Users Without 2FA', 'sky360'); ?></div>
                    </div>
                </div>
            </div>
        </div>
        <?php endif; ?>
    </div>

    <style>
        .sky-seo-two-step-settings {
            max-width: 1200px;
        }

        .sky-seo-card {
            background: #fff;
            border: 1px solid #ccd0d4;
            box-shadow: 0 1px 1px rgba(0,0,0,.04);
            padding: 20px;
            border-radius: 4px;
        }

        .sky-seo-card h3 {
            margin-top: 0;
            padding-bottom: 10px;
            border-bottom: 1px solid #eee;
        }

        .sky-seo-toggle {
            position: relative;
            display: inline-block;
            width: 50px;
            height: 24px;
        }

        .sky-seo-toggle input {
            opacity: 0;
            width: 0;
            height: 0;
        }

        .sky-seo-toggle-slider {
            position: absolute;
            cursor: pointer;
            top: 0;
            left: 0;
            right: 0;
            bottom: 0;
            background-color: #ccc;
            transition: .4s;
            border-radius: 24px;
        }

        .sky-seo-toggle-slider:before {
            position: absolute;
            content: "";
            height: 18px;
            width: 18px;
            left: 3px;
            bottom: 3px;
            background-color: white;
            transition: .4s;
            border-radius: 50%;
        }

        .sky-seo-toggle input:checked + .sky-seo-toggle-slider {
            background-color: #2271b1;
        }

        .sky-seo-toggle input:checked + .sky-seo-toggle-slider:before {
            transform: translateX(26px);
        }

        .sky-seo-stats-grid {
            display: grid;
            grid-template-columns: repeat(auto-fit, minmax(200px, 1fr));
            gap: 20px;
            margin-top: 20px;
        }

        .sky-seo-stat-box {
            background: #f8f9fa;
            border: 1px solid #e1e4e8;
            border-radius: 6px;
            padding: 20px;
            display: flex;
            align-items: center;
            gap: 15px;
        }

        .sky-seo-stat-box .stat-icon {
            font-size: 32px;
            color: #2271b1;
        }

        .sky-seo-stat-box .stat-icon .dashicons {
            width: 32px;
            height: 32px;
            font-size: 32px;
        }

        .sky-seo-stat-box .stat-value {
            font-size: 28px;
            font-weight: 600;
            color: #1e293b;
        }

        .sky-seo-stat-box .stat-label {
            font-size: 13px;
            color: #64748b;
            margin-top: 4px;
        }

        .sky-seo-role-checkboxes {
            max-height: 300px;
            overflow-y: auto;
            padding: 10px;
            background: #f8f9fa;
            border: 1px solid #e1e4e8;
            border-radius: 4px;
        }
    </style>

    <script>
    jQuery(document).ready(function($) {
        // Show/hide conditional rows
        $('#two_step_enabled').on('change', function() {
            if ($(this).is(':checked')) {
                $('.two-step-enforce-row, .two-step-token-row, .two-step-ttl-row').fadeIn();
            } else {
                $('.two-step-enforce-row, .two-step-token-row, .two-step-ttl-row').fadeOut();
            }
        });
    });
    </script>
    <?php
}
add_action('sky_seo_settings_content', 'sky_seo_two_step_render_settings');

/**
 * Save two-step verification settings.
 */
function sky_seo_two_step_save_settings() {
    // Safety check: Only save if core class is loaded
    if (!class_exists('Sky_SEO_Two_Step_Core')) {
        return;
    }

    if (!isset($_POST['sky_seo_two_step_nonce']) ||
        !wp_verify_nonce($_POST['sky_seo_two_step_nonce'], 'sky_seo_two_step_settings')) {
        return;
    }

    if (!current_user_can('manage_options')) {
        return;
    }

    try {
        $settings = get_option('sky_seo_settings', array());

        // Save enabled status
        $settings['two_step_enabled'] = isset($_POST['two_step_enabled']) ? 1 : 0;

        // Save enforced roles
        $settings['two_step_enforce_roles'] = isset($_POST['two_step_enforce_roles']) ?
            array_map('sanitize_text_field', $_POST['two_step_enforce_roles']) : array();

        // Save token length
        if (isset($_POST['two_step_token_length'])) {
            $token_length = (int) $_POST['two_step_token_length'];
            $settings['two_step_token_length'] = max(6, min(12, $token_length));
        }

        // Save token TTL
        if (isset($_POST['two_step_token_ttl'])) {
            $token_ttl = (int) $_POST['two_step_token_ttl'];
            $settings['two_step_token_ttl'] = max(5, min(60, $token_ttl));
        }

        update_option('sky_seo_settings', $settings);

        // Add success message
        add_settings_error(
            'sky_seo_two_step_messages',
            'sky_seo_two_step_message',
            __('Two-step verification settings saved successfully.', 'sky360'),
            'success'
        );
    } catch (Exception $e) {
        error_log('Sky SEO Boost: Error saving two-step verification settings: ' . $e->getMessage());
        add_settings_error(
            'sky_seo_two_step_messages',
            'sky_seo_two_step_error',
            __('Error saving two-step verification settings. Please try again.', 'sky360'),
            'error'
        );
    }
}
add_action('admin_init', 'sky_seo_two_step_save_settings');

/**
 * Get two-step verification statistics.
 *
 * @return array
 */
function sky_seo_two_step_get_statistics() {
    // Safety check
    if (!class_exists('Sky_SEO_Two_Step_Core')) {
        return array(
            'total_enabled' => 0,
            'enforced_count' => 0,
            'voluntary_count' => 0,
            'not_enabled_count' => 0,
        );
    }

    // Use count_users() for total count (much more efficient on large sites)
    $user_counts = count_users();
    $total_users = $user_counts['total_users'];

    // Use optimized meta query to count users with 2FA enabled
    $enabled_users = get_users(array(
        'meta_key' => Sky_SEO_Two_Step_Core::ENABLED_META_KEY,
        'meta_value' => '1',
        'fields' => 'ID',
        'number' => -1,
    ));
    $voluntary_count = count($enabled_users);

    // Get enforced roles from settings
    $settings = get_option('sky_seo_settings', array());
    $enforce_roles = isset($settings['two_step_enforce_roles']) ? $settings['two_step_enforce_roles'] : array();

    // Count enforced users (users with enforced roles)
    $enforced_count = 0;
    if (!empty($enforce_roles)) {
        foreach ($enforce_roles as $role) {
            if (isset($user_counts['avail_roles'][$role])) {
                $enforced_count += $user_counts['avail_roles'][$role];
            }
        }
    }

    // Calculate total enabled (voluntary + enforced, but avoid double counting)
    // Users who have both enabled AND enforced role are counted once
    $total_enabled = $voluntary_count + $enforced_count;

    // Subtract any overlap (users with voluntary enabled who are also in enforced roles)
    if ($enforced_count > 0 && $voluntary_count > 0 && !empty($enforce_roles)) {
        $overlap_users = get_users(array(
            'meta_key' => Sky_SEO_Two_Step_Core::ENABLED_META_KEY,
            'meta_value' => '1',
            'role__in' => $enforce_roles,
            'fields' => 'ID',
            'number' => -1,
        ));
        $overlap = count($overlap_users);
        $total_enabled = $voluntary_count + $enforced_count - $overlap;
    }

    $stats = array(
        'total_enabled' => $total_enabled,
        'enforced_count' => $enforced_count,
        'voluntary_count' => $voluntary_count,
        'not_enabled_count' => max(0, $total_users - $total_enabled),
    );

    return $stats;
}
