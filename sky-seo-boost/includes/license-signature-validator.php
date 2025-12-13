<?php
/**
 * License Response Signature Validator
 *
 * Verifies that license server responses are authentic and unmodified
 * using RSA cryptographic signatures.
 *
 * @package Sky_SEO_Boost
 * @since 4.0.1
 *
 * UPDATED: November 2024 - Production public key installed
 */
 
// Prevent direct access
if (!defined('ABSPATH')) {
    exit;
}
 
/**
 * License Signature Validator Class
 *
 * Provides cryptographic verification of license server responses
 * to prevent man-in-the-middle attacks and response tampering.
 */
class Sky_SEO_License_Signature_Validator {
 
    /**
     * Public key for signature verification
     *
     * PRODUCTION KEY - Matches private key on license server
     * Generated: November 2024
     * DO NOT modify this key unless rotating the server's private key
     */
    const PUBLIC_KEY = <<<EOD
-----BEGIN PUBLIC KEY-----
MIIBIjANBgkqhkiG9w0BAQEFAAOCAQ8AMIIBCgKCAQEA5hGeVa8XhqJTglap6qmz
ySDsa+iVQjOOggCtsqocf/VteruomJgkH2INL9LyZNnCqmLPmrqJ8lC+Y2urJ1x8
cIE+Dxa1zfqwxJwnhF+imzxtzFcy6zf/iSbzob9JAcPoJBuH0wAs5DzE2nhpd7Hf
MUCcVpGSx/SWIwG42gUnQ8MvpFiTJV6sJGm+gC8Tx8UMM0NUHw2V9rgoyOvpoVni
/Zm6Aq97A4dQEqvwdd0WlhGLpnq7A7VYLpBiU2rAJoy2Drngb8B5CkQpFZ0AFKbS
NQ/B4txsB+dNdQjLk8zc1T8jq/i/FqlfP5FWeyT2gHZxY5FF86tcWFkLh1A0B/qQ
pQIDAQAB
-----END PUBLIC KEY-----
EOD;
 
    /**
     * Maximum timestamp age in seconds (10 minutes)
     * Prevents replay attacks using old responses
     *
     * Increased from 5 to 10 minutes to accommodate:
     * - Server clock skew on shared hosting
     * - Network latency issues
     * - NTP sync delays on some hosts
     */
    const MAX_TIMESTAMP_AGE = 600;
 
    /**
     * Verify complete license response
     *
     * Performs full validation including signature and timestamp checks
     *
     * @param array $response_data Response from license server
     * @return bool True if response is valid and authentic
     */
    public static function verify_response($response_data) {
        // Check OpenSSL availability
        if (!self::is_available()) {
            if (defined('WP_DEBUG') && WP_DEBUG) {
                error_log('Sky SEO: OpenSSL not available - cannot verify license signature');
            }
            return false;
        }
 
        // Validate response structure
        if (!is_array($response_data)) {
            if (defined('WP_DEBUG') && WP_DEBUG) {
                error_log('Sky SEO: Response data is not an array');
            }
            return false;
        }
 
        // Check for required signature field
        if (!isset($response_data['signature'])) {
            if (defined('WP_DEBUG') && WP_DEBUG) {
                error_log('Sky SEO: Response missing signature field');
            }
            return false;
        }
 
        // Check for required timestamp field
        if (!isset($response_data['timestamp'])) {
            if (defined('WP_DEBUG') && WP_DEBUG) {
                error_log('Sky SEO: Response missing timestamp field');
            }
            return false;
        }
 
        // Verify timestamp is recent (prevents replay attacks)
        if (!self::verify_timestamp($response_data['timestamp'])) {
            return false;
        }
 
        // Verify cryptographic signature
        $signature = $response_data['signature'];
        if (!self::verify_signature($response_data, $signature)) {
            return false;
        }
 
        return true;
    }
 
    /**
     * Verify RSA signature
     *
     * Uses RSA-SHA256 to verify the response hasn't been tampered with
     *
     * @param array $response_data Response data including signature
     * @param string $signature Base64-encoded signature
     * @return bool True if signature is valid
     */
    public static function verify_signature($response_data, $signature) {
        try {
            // Decode base64 signature
            $signature_decoded = base64_decode($signature, true);
            if ($signature_decoded === false) {
                if (defined('WP_DEBUG') && WP_DEBUG) {
                    error_log('Sky SEO: Invalid base64 signature');
                }
                return false;
            }
 
            // Create canonical JSON (must match server-side exactly)
            $canonical_data = self::create_canonical_json($response_data);
 
            // Load public key
            $public_key = openssl_pkey_get_public(self::PUBLIC_KEY);
            if ($public_key === false) {
                error_log('Sky SEO: Failed to load public key - ' . openssl_error_string());
                return false;
            }
 
            // Verify signature using SHA256
            $result = openssl_verify(
                $canonical_data,
                $signature_decoded,
                $public_key,
                OPENSSL_ALGO_SHA256
            );
 
            openssl_free_key($public_key);
 
            if ($result === 1) {
                // Signature valid
                return true;
            } elseif ($result === 0) {
                // Signature invalid - response may be tampered
                error_log('Sky SEO: Signature verification failed - response may be tampered');
                return false;
            } else {
                // Verification error
                error_log('Sky SEO: Signature verification error - ' . openssl_error_string());
                return false;
            }
 
        } catch (Exception $e) {
            error_log('Sky SEO: Exception during signature verification - ' . $e->getMessage());
            return false;
        }
    }
 
    /**
     * Create canonical JSON representation
     *
     * Must match server-side canonical JSON exactly for signature verification
     *
     * @param array $data Response data
     * @return string Canonical JSON string
     */
    private static function create_canonical_json($data) {
        // Remove signature field (we're verifying it, not signing it)
        if (isset($data['signature'])) {
            unset($data['signature']);
        }
 
        // Sort recursively for consistent ordering
        $data = self::sort_array_recursive($data);
 
        // Create JSON with consistent formatting
        $json = json_encode($data, JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE);
 
        if ($json === false) {
            error_log('Sky SEO: Failed to create canonical JSON - ' . json_last_error_msg());
            return '';
        }
 
        return $json;
    }
 
    /**
     * Recursively sort array by keys
     *
     * Ensures consistent key ordering for signature verification
     *
     * @param mixed $array Array to sort
     * @return mixed Sorted array
     */
    private static function sort_array_recursive($array) {
        if (!is_array($array)) {
            return $array;
        }
 
        ksort($array);
 
        foreach ($array as $key => $value) {
            if (is_array($value)) {
                $array[$key] = self::sort_array_recursive($value);
            }
        }
 
        return $array;
    }
 
    /**
     * Verify timestamp freshness
     *
     * Prevents replay attacks by rejecting old responses
     *
     * @param int $timestamp Unix timestamp from response
     * @param int|null $max_age Maximum allowed age in seconds
     * @return bool True if timestamp is valid and recent
     */
    public static function verify_timestamp($timestamp, $max_age = null) {
        if ($max_age === null) {
            $max_age = self::MAX_TIMESTAMP_AGE;
        }
 
        // Validate timestamp format
        if (!is_numeric($timestamp) || $timestamp <= 0) {
            if (defined('WP_DEBUG') && WP_DEBUG) {
                error_log('Sky SEO: Invalid timestamp format');
            }
            return false;
        }
 
        // Check timestamp age
        $current_time = time();
        $age = abs($current_time - $timestamp);
 
        if ($age > $max_age) {
            if (defined('WP_DEBUG') && WP_DEBUG) {
                error_log(sprintf(
                    'Sky SEO: Response timestamp too old (Age: %d seconds, Max: %d seconds)',
                    $age,
                    $max_age
                ));
            }
            return false;
        }
 
        // Check for future timestamps (clock skew detection)
        if ($timestamp > ($current_time + 30)) {
            if (defined('WP_DEBUG') && WP_DEBUG) {
                error_log('Sky SEO: Response timestamp is in the future - possible clock skew or manipulation');
            }
            return false;
        }
 
        return true;
    }
 
    /**
     * Check if OpenSSL functions are available
     *
     * @return bool True if OpenSSL is available
     */
    public static function is_available() {
        return function_exists('openssl_verify') &&
               function_exists('openssl_pkey_get_public') &&
               function_exists('openssl_free_key');
    }
 
    /**
     * Get validator status information
     *
     * Useful for debugging and diagnostics
     *
     * @return array Status information
     */
    public static function get_status() {
        $status = [
            'available' => self::is_available(),
            'functions' => [
                'openssl_verify' => function_exists('openssl_verify'),
                'openssl_pkey_get_public' => function_exists('openssl_pkey_get_public'),
                'openssl_free_key' => function_exists('openssl_free_key'),
            ],
        ];
 
        if (defined('OPENSSL_VERSION_TEXT')) {
            $status['version'] = OPENSSL_VERSION_TEXT;
        }
 
        return $status;
    }
 
    /**
     * Validate that the public key can be loaded
     *
     * @return bool True if public key is valid
     */
    public static function validate_public_key() {
        if (!self::is_available()) {
            return false;
        }
 
        $public_key = openssl_pkey_get_public(self::PUBLIC_KEY);
 
        if ($public_key === false) {
            return false;
        }
 
        openssl_free_key($public_key);
        return true;
    }
}