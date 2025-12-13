<?php
/**
 * Sky SEO Boost - Two-Step Verification Loader
 *
 * This file loads and initializes the two-step verification system.
 *
 * @package Sky_SEO_Boost
 * @since 4.2.0
 */

// Prevent direct access
if (!defined('ABSPATH')) {
    exit;
}

/**
 * Load two-step verification classes and initialize the system.
 */
function sky_seo_load_two_step_verification() {
    try {
        // Verify plugin directory constant is defined
        if (!defined('SKY_SEO_BOOST_PLUGIN_DIR')) {
            error_log('Sky SEO Boost: SKY_SEO_BOOST_PLUGIN_DIR constant not defined');
            return false;
        }

        // Define the two-step verification directory
        $two_step_dir = SKY_SEO_BOOST_PLUGIN_DIR . 'includes/two-step-verification/';

        // Verify directory exists
        if (!is_dir($two_step_dir)) {
            error_log('Sky SEO Boost: Two-step verification directory not found: ' . $two_step_dir);
            return false;
        }

        // Always load the email provider class (needed for admin settings)
        $email_class_file = $two_step_dir . 'class-two-step-email.php';
        if (file_exists($email_class_file)) {
            require_once $email_class_file;
        } else {
            error_log('Sky SEO Boost: Email provider class file not found: ' . $email_class_file);
            return false;
        }

        // Always load the core class (needed for admin settings)
        $core_class_file = $two_step_dir . 'class-two-step-core.php';
        if (file_exists($core_class_file)) {
            require_once $core_class_file;
        } else {
            error_log('Sky SEO Boost: Core class file not found: ' . $core_class_file);
            return false;
        }

        // Verify classes are loaded
        if (!class_exists('Sky_SEO_Two_Step_Email') || !class_exists('Sky_SEO_Two_Step_Core')) {
            error_log('Sky SEO Boost: Two-step verification classes failed to load');
            return false;
        }

        // Check if two-step verification is enabled
        $settings = get_option('sky_seo_settings', array());
        $is_enabled = isset($settings['two_step_enabled']) && $settings['two_step_enabled'];

        // Only initialize authentication hooks if enabled
        if ($is_enabled) {
            Sky_SEO_Two_Step_Core::init();
        }

        return true;
    } catch (Exception $e) {
        error_log('Sky SEO Boost: Error loading two-step verification: ' . $e->getMessage());
        return false;
    }
}

// Hook into init with high priority to ensure it loads early
add_action('init', 'sky_seo_load_two_step_verification', 5);
