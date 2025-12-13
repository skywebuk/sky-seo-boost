<?php
/**
 * Centralized API Request Handler with Retry Logic
 *
 * Provides a unified interface for making HTTP requests with automatic
 * retry logic, consistent timeouts, and proper error handling.
 *
 * @package Sky_SEO_Boost
 * @since 4.0.1
 */

// Prevent direct access
if (!defined('ABSPATH')) {
    exit;
}

/**
 * API Request Handler Class
 *
 * Handles all external API requests with retry logic and consistent configuration.
 */
class Sky_SEO_API_Request_Handler {

    /**
     * Make API request with retry logic
     *
     * @param string $url API endpoint URL
     * @param array $args Request arguments (supports all wp_remote_* args)
     * @param string $timeout_type Type of timeout (critical/standard/background/quick)
     * @param bool $enable_retry Enable automatic retry logic on failures
     * @return array|WP_Error Response array or WP_Error on failure
     */
    public static function request($url, $args = [], $timeout_type = 'standard', $enable_retry = true) {
        // Validate URL
        if (empty($url) || !filter_var($url, FILTER_VALIDATE_URL)) {
            return new WP_Error(
                'invalid_url',
                __('Invalid API endpoint URL.', 'sky-seo-boost')
            );
        }

        // Set timeout based on type
        $args['timeout'] = Sky_SEO_API_Config::get_timeout($timeout_type);

        // Set default SSL verify (always true for security)
        if (!isset($args['sslverify'])) {
            $args['sslverify'] = true;
        }

        // Set user agent if not specified
        if (!isset($args['user-agent']) && !isset($args['headers']['User-Agent'])) {
            $version = defined('SKY_SEO_BOOST_VERSION') ? SKY_SEO_BOOST_VERSION : '4.0.1';
            $args['user-agent'] = 'Sky SEO Boost/' . $version . ' (WordPress/' . get_bloginfo('version') . ')';
        }

        // Determine request method
        $method = self::determine_method($args);

        // Make request with or without retry logic
        if ($enable_retry) {
            return self::request_with_retry($url, $args, $method);
        }

        // Single request without retry
        return self::execute_request($url, $args, $method);
    }

    /**
     * Execute request with retry logic
     *
     * @param string $url API endpoint URL
     * @param array $args Request arguments
     * @param string $method HTTP method
     * @return array|WP_Error Response or error
     */
    private static function request_with_retry($url, $args, $method) {
        $last_error = null;
        $max_retries = Sky_SEO_API_Config::MAX_RETRIES;

        for ($attempt = 1; $attempt <= $max_retries; $attempt++) {
            // Execute request
            $response = self::execute_request($url, $args, $method);

            // Check if retry is needed
            if (!Sky_SEO_API_Config::should_retry($response)) {
                // Success or non-retryable error
                return $response;
            }

            // Store error for final return if all retries fail
            $last_error = $response;

            // Don't sleep after last attempt
            if ($attempt < $max_retries) {
                $delay = Sky_SEO_API_Config::get_retry_delay($attempt);

                // Log retry attempt if debugging is enabled
                self::log_retry($attempt, $max_retries, $url, $response, $delay);

                // Sleep before next retry
                sleep($delay);
            }
        }

        // All retries exhausted, log final failure
        self::log_retry_exhausted($url, $last_error);

        return $last_error;
    }

    /**
     * Execute single HTTP request
     *
     * @param string $url API endpoint URL
     * @param array $args Request arguments
     * @param string $method HTTP method
     * @return array|WP_Error Response or error
     */
    private static function execute_request($url, $args, $method) {
        try {
            switch ($method) {
                case 'POST':
                    return wp_remote_post($url, $args);

                case 'GET':
                    return wp_remote_get($url, $args);

                case 'PUT':
                case 'DELETE':
                case 'PATCH':
                case 'HEAD':
                    $args['method'] = $method;
                    return wp_remote_request($url, $args);

                default:
                    return wp_remote_get($url, $args);
            }
        } catch (Exception $e) {
            return new WP_Error(
                'request_exception',
                $e->getMessage()
            );
        }
    }

    /**
     * Determine HTTP method from arguments
     *
     * @param array $args Request arguments
     * @return string HTTP method
     */
    private static function determine_method($args) {
        // Explicit method specified
        if (isset($args['method'])) {
            return strtoupper($args['method']);
        }

        // If body is present, default to POST
        if (isset($args['body']) && !empty($args['body'])) {
            return 'POST';
        }

        // Default to GET
        return 'GET';
    }

    /**
     * Log retry attempt (only if WP_DEBUG is enabled)
     *
     * @param int $attempt Current attempt number
     * @param int $max_retries Maximum retry attempts
     * @param string $url Request URL
     * @param mixed $response Failed response
     * @param int $delay Delay before next retry
     */
    private static function log_retry($attempt, $max_retries, $url, $response, $delay) {
        if (!defined('WP_DEBUG') || !WP_DEBUG) {
            return;
        }

        // Get error message
        $error_msg = self::get_error_message($response);

        // Mask sensitive URL parts (API keys, tokens, etc.)
        $safe_url = self::mask_sensitive_url($url);

        error_log(sprintf(
            'Sky SEO API: Retry attempt %d/%d for %s (Error: %s, Next retry in: %ds)',
            $attempt,
            $max_retries,
            $safe_url,
            $error_msg,
            $delay
        ));
    }

    /**
     * Log when all retries are exhausted
     *
     * @param string $url Request URL
     * @param mixed $response Final failed response
     */
    private static function log_retry_exhausted($url, $response) {
        $error_msg = self::get_error_message($response);
        $safe_url = self::mask_sensitive_url($url);

        error_log(sprintf(
            'Sky SEO API: All retry attempts exhausted for %s (Final error: %s)',
            $safe_url,
            $error_msg
        ));
    }

    /**
     * Get error message from response
     *
     * @param mixed $response WP_Error or HTTP response
     * @return string Error message
     */
    private static function get_error_message($response) {
        if (is_wp_error($response)) {
            return $response->get_error_message();
        }

        $code = wp_remote_retrieve_response_code($response);
        $message = wp_remote_retrieve_response_message($response);

        return sprintf('HTTP %d: %s', $code, $message);
    }

    /**
     * Mask sensitive parts of URL for logging
     *
     * @param string $url URL to mask
     * @return string Masked URL
     */
    private static function mask_sensitive_url($url) {
        // Parse URL
        $parsed = parse_url($url);

        if (!$parsed) {
            return '[invalid-url]';
        }

        // Keep scheme and host
        $safe_url = $parsed['scheme'] . '://' . $parsed['host'];

        // Add path without query string
        if (isset($parsed['path'])) {
            $safe_url .= $parsed['path'];
        }

        // Indicate query string was present but don't show it
        if (isset($parsed['query'])) {
            $safe_url .= '?[params-hidden]';
        }

        return $safe_url;
    }

    /**
     * Make a non-blocking request (fire and forget)
     *
     * Useful for analytics pings, search engine notifications, etc.
     *
     * @param string $url API endpoint URL
     * @param array $args Request arguments
     * @return bool True if request was initiated
     */
    public static function request_async($url, $args = []) {
        // Validate URL
        if (empty($url) || !filter_var($url, FILTER_VALIDATE_URL)) {
            return false;
        }

        // Set non-blocking
        $args['blocking'] = false;

        // Set short timeout for async requests
        $args['timeout'] = Sky_SEO_API_Config::get_timeout('quick');

        // Set user agent
        if (!isset($args['user-agent'])) {
            $version = defined('SKY_SEO_BOOST_VERSION') ? SKY_SEO_BOOST_VERSION : '4.0.1';
            $args['user-agent'] = 'Sky SEO Boost/' . $version;
        }

        // Determine method
        $method = self::determine_method($args);

        // Execute non-blocking request
        $response = self::execute_request($url, $args, $method);

        // For non-blocking requests, WP returns true immediately
        return !is_wp_error($response);
    }

    /**
     * Get response body safely
     *
     * @param array|WP_Error $response HTTP response
     * @return string|null Response body or null on error
     */
    public static function get_response_body($response) {
        if (is_wp_error($response)) {
            return null;
        }

        return wp_remote_retrieve_body($response);
    }

    /**
     * Get JSON decoded response body
     *
     * @param array|WP_Error $response HTTP response
     * @param bool $assoc Return associative array instead of object
     * @return mixed|null Decoded JSON or null on error
     */
    public static function get_json_response($response, $assoc = true) {
        $body = self::get_response_body($response);

        if ($body === null) {
            return null;
        }

        $decoded = json_decode($body, $assoc);

        if (json_last_error() !== JSON_ERROR_NONE) {
            if (defined('WP_DEBUG') && WP_DEBUG) {
                error_log('Sky SEO API: JSON decode error: ' . json_last_error_msg());
            }
            return null;
        }

        return $decoded;
    }

    /**
     * Check if response is successful (2xx status code)
     *
     * @param array|WP_Error $response HTTP response
     * @return bool True if successful
     */
    public static function is_successful($response) {
        if (is_wp_error($response)) {
            return false;
        }

        $code = wp_remote_retrieve_response_code($response);
        return $code >= 200 && $code < 300;
    }
}
