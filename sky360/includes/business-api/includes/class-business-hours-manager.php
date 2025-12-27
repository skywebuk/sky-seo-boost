<?php
/**
 * Sky SEO Business Hours Manager
 * Handles all business hours related functionality
 * 
 * @package SkySEOBoost
 * @subpackage BusinessAPI
 * @since 3.1.0
 */

// Prevent direct access
if (!defined('ABSPATH')) {
    exit;
}

/**
 * Business Hours Manager Class
 */
class Sky_SEO_Business_Hours_Manager {
    
    /**
     * Hours option name
     */
    private $hours_option_name;
    
    /**
     * Cache group
     */
    private $cache_group;
    
    /**
     * Days of the week
     */
    private $days = ['Monday', 'Tuesday', 'Wednesday', 'Thursday', 'Friday', 'Saturday', 'Sunday'];
    
    /**
     * Constructor
     */
    public function __construct($hours_option_name, $cache_group) {
        $this->hours_option_name = $hours_option_name;
        $this->cache_group = $cache_group;
    }
    
    /**
     * Get manual hours with validation
     */
    public function get_manual_hours() {
        // Cache the formatted hours
        $cache_key = 'manual_hours';
        $formatted_hours = wp_cache_get($cache_key, $this->cache_group);
        
        if ($formatted_hours !== false) {
            return $formatted_hours;
        }
        
        $hours_settings = get_option($this->hours_option_name, []);
        $formatted_hours = [];
        
        foreach ($this->days as $day) {
            $formatted_hours[$day] = $this->format_day_hours($day, $hours_settings);
        }
        
        // Cache for 1 hour
        wp_cache_set($cache_key, $formatted_hours, $this->cache_group, HOUR_IN_SECONDS);
        
        return $formatted_hours;
    }
    
    /**
     * Format hours for a single day
     */
    private function format_day_hours($day, $hours_settings) {
        $day_key = strtolower($day);
        
        // Check if 24 hours
        if (!empty($hours_settings[$day_key . '_24hours'])) {
            return __('24 hours', 'sky360');
        }
        
        // Check if closed
        if (!empty($hours_settings[$day_key . '_closed'])) {
            return __('Closed', 'sky360');
        }
        
        // Get time slots
        $slots = [];
        
        // Check for multiple time slots
        if (isset($hours_settings[$day_key . '_slots']) && is_array($hours_settings[$day_key . '_slots'])) {
            foreach ($hours_settings[$day_key . '_slots'] as $slot) {
                if (!empty($slot['open']) && !empty($slot['close'])) {
                    $slots[] = $this->format_time_slot($slot['open'], $slot['close']);
                }
            }
        }
        
        // Fallback to simple open/close if no slots
        if (empty($slots)) {
            $open_time = $hours_settings[$day_key . '_open'] ?? '';
            $close_time = $hours_settings[$day_key . '_close'] ?? '';
            
            if ($open_time && $close_time) {
                $slots[] = $this->format_time_slot($open_time, $close_time);
            }
        }
        
        return !empty($slots) ? implode(', ', $slots) : __('Closed', 'sky360');
    }
    
    /**
     * Format a time slot with validation
     */
    private function format_time_slot($open_time, $close_time) {
        $open_timestamp = strtotime($open_time);
        $close_timestamp = strtotime($close_time);
        
        if ($open_timestamp === false || $close_timestamp === false) {
            return '';
        }
        
        $open_formatted = date_i18n('g:i A', $open_timestamp);
        $close_formatted = date_i18n('g:i A', $close_timestamp);
        
        return sprintf('%s - %s', $open_formatted, $close_formatted);
    }
    
    /**
     * Check if business is currently open with caching
     */
    public function is_business_open() {
        // Cache for 1 minute
        $cache_key = 'is_open_' . current_time('Y-m-d-H-i');
        $is_open = wp_cache_get($cache_key, $this->cache_group);
        
        if ($is_open !== false) {
            return $is_open;
        }
        
        $hours_settings = get_option($this->hours_option_name, []);
        $timezone_string = sanitize_text_field($hours_settings['timezone'] ?? 'UTC');
        
        try {
            $timezone = new DateTimeZone($timezone_string);
            $now = new DateTime('now', $timezone);
        } catch (Exception $e) {
            error_log('Sky SEO Business API: Invalid timezone - ' . $e->getMessage());
            $now = new DateTime('now');
        }
        
        $current_day = $now->format('l');
        $day_key = strtolower($current_day);
        
        // Check if 24 hours
        if (!empty($hours_settings[$day_key . '_24hours'])) {
            wp_cache_set($cache_key, true, $this->cache_group, 60);
            return true;
        }
        
        // Check if closed today
        if (!empty($hours_settings[$day_key . '_closed'])) {
            wp_cache_set($cache_key, false, $this->cache_group, 60);
            return false;
        }
        
        $current_minutes = intval($now->format('H')) * 60 + intval($now->format('i'));
        
        // Check time slots
        if (isset($hours_settings[$day_key . '_slots']) && is_array($hours_settings[$day_key . '_slots'])) {
            foreach ($hours_settings[$day_key . '_slots'] as $slot) {
                if ($this->is_within_time_slot($current_minutes, $slot)) {
                    wp_cache_set($cache_key, true, $this->cache_group, 60);
                    return true;
                }
            }
        }
        
        wp_cache_set($cache_key, false, $this->cache_group, 60);
        return false;
    }
    
    /**
     * Check if current time is within a time slot
     */
    private function is_within_time_slot($current_minutes, $slot) {
        if (empty($slot['open']) || empty($slot['close'])) {
            return false;
        }
        
        $open_parts = explode(':', $slot['open']);
        $close_parts = explode(':', $slot['close']);
        
        if (count($open_parts) !== 2 || count($close_parts) !== 2) {
            return false;
        }
        
        $open_minutes = intval($open_parts[0]) * 60 + intval($open_parts[1]);
        $close_minutes = intval($close_parts[0]) * 60 + intval($close_parts[1]);
        
        // Handle overnight hours
        if ($close_minutes < $open_minutes) {
            return ($current_minutes >= $open_minutes || $current_minutes < $close_minutes);
        } else {
            return ($current_minutes >= $open_minutes && $current_minutes < $close_minutes);
        }
    }
    
    /**
     * Sanitize hours settings
     */
    public function sanitize_hours_settings($input) {
        $sanitized = [];
        
        // Sanitize timezone
        $sanitized['timezone'] = sanitize_text_field($input['timezone'] ?? 'UTC');
        
        // Validate timezone
        $valid_timezones = timezone_identifiers_list();
        if (!in_array($sanitized['timezone'], $valid_timezones)) {
            $sanitized['timezone'] = 'UTC';
        }
        
        // Sanitize day settings
        $days = ['monday', 'tuesday', 'wednesday', 'thursday', 'friday', 'saturday', 'sunday'];
        
        foreach ($days as $day) {
            // Status
            $status = sanitize_text_field($input[$day . '_status'] ?? 'open');
            $sanitized[$day . '_closed'] = ($status === 'closed') ? '1' : '0';
            $sanitized[$day . '_24hours'] = ($status === '24hours') ? '1' : '0';
            
            // Slots
            if (isset($input[$day . '_slots']) && is_array($input[$day . '_slots'])) {
                $sanitized[$day . '_slots'] = [];
                foreach ($input[$day . '_slots'] as $index => $slot) {
                    if (!empty($slot['open']) && !empty($slot['close'])) {
                        $sanitized[$day . '_slots'][$index] = [
                            'open' => $this->sanitize_time($slot['open']),
                            'close' => $this->sanitize_time($slot['close'])
                        ];
                    }
                }
            }
        }
        
        // Clear cache when hours are updated
        wp_cache_delete('manual_hours', $this->cache_group);
        
        return $sanitized;
    }
    
    /**
     * Sanitize time input
     */
    private function sanitize_time($time) {
        // Validate time format HH:MM
        if (preg_match('/^([01]?[0-9]|2[0-3]):[0-5][0-9]$/', $time)) {
            return $time;
        }
        return '00:00';
    }
    
    /**
     * Get hours for a specific day
     */
    public function get_day_hours($day) {
        $hours = $this->get_manual_hours();
        return isset($hours[$day]) ? $hours[$day] : __('Closed', 'sky360');
    }
    
    /**
     * Export hours configuration
     */
    public function export_hours() {
        return get_option($this->hours_option_name, []);
    }
    
    /**
     * Import hours configuration
     */
    public function import_hours($hours_data) {
        if (is_array($hours_data)) {
            $sanitized = $this->sanitize_hours_settings($hours_data);
            return update_option($this->hours_option_name, $sanitized);
        }
        return false;
    }
}