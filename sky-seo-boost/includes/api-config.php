<?php
/**
 * Centralized API Configuration
 * Defines timeouts, retry policies, and API endpoints
 *
 * @package Sky_SEO_Boost
 * @since 4.0.1
 */

// Prevent direct access
if (!defined('ABSPATH')) {
    exit;
}

/**
 * API Configuration Class
 *
 * Centralizes all API-related configuration including timeouts,
 * retry policies, cache durations, and fallback servers.
 */
class Sky_SEO_API_Config {

    /**
     * Timeout constants (in seconds)
     *
     * TIMEOUT_CRITICAL: For license checks and critical operations
     * TIMEOUT_STANDARD: For standard API calls
     * TIMEOUT_BACKGROUND: For non-blocking background calls (geolocation)
     * TIMEOUT_QUICK: For quick lookup calls
     */
    const TIMEOUT_CRITICAL = 10;
    const TIMEOUT_STANDARD = 5;
    const TIMEOUT_BACKGROUND = 3;
    const TIMEOUT_QUICK = 2;

    /**
     * Retry configuration
     *
     * MAX_RETRIES: Maximum number of retry attempts
     * RETRY_DELAY_BASE: Base delay in seconds before first retry
     * RETRY_EXPONENTIAL: Use exponential backoff for retry delays
     */
    const MAX_RETRIES = 3;
    const RETRY_DELAY_BASE = 1;
    const RETRY_EXPONENTIAL = true;

    /**
     * Cache duration constants (in seconds)
     *
     * CACHE_GEOLOCATION: How long to cache IP geolocation data
     * CACHE_LICENSE: How long to cache license validation results
     * CACHE_UPDATE_INFO: How long to cache plugin update information
     */
    const CACHE_GEOLOCATION = 2592000; // 30 days
    const CACHE_LICENSE = 86400; // 24 hours
    const CACHE_UPDATE_INFO = 43200; // 12 hours

    /**
     * Fallback license servers
     *
     * Used if primary license server is unavailable.
     * These are alternate endpoints for the same license server
     * to handle cases where certain paths are blocked.
     */
    const FALLBACK_LICENSE_SERVERS = [
        // Alternative endpoint paths (same server, different access methods)
        'https://skywebdesign.co.uk/wp-json/sky-license/v1/validate',
        // Add backup servers below if available
        // 'https://backup1.skywebdesign.co.uk/wp-content/plugins/sky-seo-license-manager/api-endpoint.php',
    ];

    /**
     * Enable fallback server support
     *
     * When true, the system will try fallback servers if primary fails
     */
    const ENABLE_FALLBACK = true;

    /**
     * Get timeout for specific API type
     *
     * @param string $type Type of timeout (critical/standard/background/quick)
     * @return int Timeout in seconds
     */
    public static function get_timeout($type = 'standard') {
        $timeouts = [
            'critical' => self::TIMEOUT_CRITICAL,
            'standard' => self::TIMEOUT_STANDARD,
            'background' => self::TIMEOUT_BACKGROUND,
            'quick' => self::TIMEOUT_QUICK,
        ];

        return isset($timeouts[$type]) ? $timeouts[$type] : self::TIMEOUT_STANDARD;
    }

    /**
     * Calculate retry delay with optional exponential backoff
     *
     * @param int $attempt Current attempt number (1-based)
     * @return int Delay in seconds
     */
    public static function get_retry_delay($attempt) {
        if (self::RETRY_EXPONENTIAL) {
            // Exponential backoff: 1s, 2s, 4s, 8s, etc.
            return self::RETRY_DELAY_BASE * pow(2, $attempt - 1);
        }

        // Linear delay
        return self::RETRY_DELAY_BASE;
    }

    /**
     * Get cache duration for specific cache type
     *
     * @param string $type Type of cache (geolocation/license/update_info)
     * @return int Cache duration in seconds
     */
    public static function get_cache_duration($type) {
        $durations = [
            'geolocation' => self::CACHE_GEOLOCATION,
            'license' => self::CACHE_LICENSE,
            'update_info' => self::CACHE_UPDATE_INFO,
        ];

        return isset($durations[$type]) ? $durations[$type] : DAY_IN_SECONDS;
    }

    /**
     * Check if retry should be attempted for given response
     *
     * @param mixed $response WP_Error or HTTP response array
     * @return bool True if retry should be attempted
     */
    public static function should_retry($response) {
        // Always retry on WP_Error (network issues)
        if (is_wp_error($response)) {
            return true;
        }

        // Get response code
        $code = wp_remote_retrieve_response_code($response);

        // Don't retry client errors (4xx) - these won't fix themselves
        if ($code >= 400 && $code < 500) {
            return false;
        }

        // Retry server errors (5xx) and other issues
        if ($code >= 500 || $code < 200) {
            return true;
        }

        // Success - no retry needed
        return false;
    }

    /**
     * Get fallback servers for license verification
     *
     * @return array Array of fallback server URLs
     */
    public static function get_fallback_servers() {
        return self::FALLBACK_LICENSE_SERVERS;
    }
}
