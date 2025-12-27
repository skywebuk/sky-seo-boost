/**
 * Sky SEO Business API Admin JavaScript
 * Simplified version without historical reviews
 * 
 * @package SkySEOBoost
 * @since 3.1.0
 */

jQuery(document).ready(function($) {
    'use strict';
    
    // Check current tab
    var currentTab = getUrlParameter('tab') || 'settings';
    
    // Initialize based on tab
    if (currentTab === 'settings') {
        initializeBusinessHours();
        initializeAPIActions();
        updateUsageStats();
    }
    // Reviews tab functionality is handled inline in the PHP
    
    /**
     * Get URL parameter
     */
    function getUrlParameter(name) {
        name = name.replace(/[\[]/, '\\[').replace(/[\]]/, '\\]');
        var regex = new RegExp('[\\?&]' + name + '=([^&#]*)');
        var results = regex.exec(location.search);
        return results === null ? '' : decodeURIComponent(results[1].replace(/\+/g, ' '));
    }
    
    /**
     * Initialize business hours functionality
     */
    function initializeBusinessHours() {
        // Status change handler
        $('.status-select').on('change', function() {
            var $select = $(this);
            var day = $select.data('day');
            var value = $select.val();
            var $row = $select.closest('.day-row');
            var $hoursDiv = $('#hours-' + day);
            var $statusMsg = $row.find('.status-message');
            var $statusMsg24 = $row.find('.status-message-24');
            var $addButton = $row.find('.add-hours');
            var $closedInput = $row.find('.closed-input');
            var $hours24Input = $row.find('.24hours-input');
            
            // Reset all states
            $closedInput.val('0');
            $hours24Input.val('0');
            $statusMsg.hide().text('');
            $statusMsg24.hide().text('');
            
            if (value === 'closed') {
                $hoursDiv.hide();
                $statusMsg.show().text(sky_business_api.i18n.closed_all_day);
                $addButton.hide();
                $closedInput.val('1');
            } else if (value === '24hours') {
                $hoursDiv.hide();
                $statusMsg24.show().text(sky_business_api.i18n.open_24_hours);
                $addButton.hide();
                $hours24Input.val('1');
            } else {
                $hoursDiv.show();
                $addButton.show();
                
                // Ensure at least one time slot
                if ($hoursDiv.find('.time-slot').length === 0) {
                    addTimeSlot(day, 0);
                }
            }
        });
        
        // Add time slot
        $('.add-hours').on('click', function() {
            var day = $(this).data('day');
            var $hoursDiv = $('#hours-' + day);
            var slotCount = $hoursDiv.find('.time-slot').length;
            
            addTimeSlot(day, slotCount);
        });
        
        // Remove time slot
        $(document).on('click', '.remove-slot', function() {
            $(this).closest('.time-slot').remove();
        });
        
        // Apply to all days
        $('#apply-to-all').on('click', function() {
            applyHoursToAllDays();
        });
        
        // Copy hours dialog
        $('#copy-hours').on('click', function() {
            showCopyHoursDialog();
        });
    }
    
    /**
     * Add time slot function
     */
    function addTimeSlot(day, index) {
        var html = '<div class="time-slot">';
        html += '<input type="time" name="sky_seo_business_hours[' + day + '_slots][' + index + '][open]" value="09:00" class="time-input" />';
        html += '<span class="time-separator">-</span>';
        html += '<input type="time" name="sky_seo_business_hours[' + day + '_slots][' + index + '][close]" value="17:00" class="time-input" />';
        if (index > 0) {
            html += '<button type="button" class="button-link remove-slot" data-day="' + day + '">';
            html += '<span class="dashicons dashicons-trash"></span>';
            html += '</button>';
        }
        html += '</div>';
        
        $('#hours-' + day).append(html);
    }
    
    /**
     * Apply hours to all days
     */
    function applyHoursToAllDays() {
        var $firstRow = $('.day-row').first();
        var status = $firstRow.find('.status-select').val();
        var slots = [];
        
        if (status === 'open') {
            $firstRow.find('.time-slot').each(function() {
                var open = $(this).find('input[type="time"]').eq(0).val();
                var close = $(this).find('input[type="time"]').eq(1).val();
                slots.push({open: open, close: close});
            });
        }
        
        $('.day-row').each(function() {
            var $row = $(this);
            var day = $row.data('day');
            
            $row.find('.status-select').val(status).trigger('change');
            
            if (status === 'open' && slots.length > 0) {
                var $hoursDiv = $('#hours-' + day);
                $hoursDiv.empty();
                
                slots.forEach(function(slot, index) {
                    addTimeSlot(day, index);
                    var $lastSlot = $hoursDiv.find('.time-slot').last();
                    $lastSlot.find('input[type="time"]').eq(0).val(slot.open);
                    $lastSlot.find('input[type="time"]').eq(1).val(slot.close);
                });
            }
        });
        
        showNotice(sky_business_api.i18n.hours_applied, 'success');
    }
    
    /**
     * Show copy hours dialog
     */
    function showCopyHoursDialog() {
        var html = '<div class="copy-dialog-overlay"></div>';
        html += '<div class="copy-dialog">';
        html += '<h3>' + sky_business_api.i18n.copy_hours_from + '</h3>';
        html += '<div class="copy-from-day">';
        html += '<label><strong>' + sky_business_api.i18n.select_source_day + ':</strong></label><br>';
        html += '<select id="copy-source-day" style="width: 100%; margin: 10px 0;">';
        $('.day-row').each(function() {
            var day = $(this).data('day');
            var dayName = $(this).find('.day-name strong').text();
            html += '<option value="' + day + '">' + dayName + '</option>';
        });
        html += '</select>';
        html += '</div>';
        html += '<div class="copy-to-days" style="margin-top: 15px;">';
        html += '<label><strong>' + sky_business_api.i18n.copy_to + ':</strong></label><br>';
        $('.day-row').each(function() {
            var day = $(this).data('day');
            var dayName = $(this).find('.day-name strong').text();
            html += '<label style="display: block; margin: 5px 0;">';
            html += '<input type="checkbox" class="day-checkbox" value="' + day + '"> ' + dayName;
            html += '</label>';
        });
        html += '</div>';
        html += '<div class="copy-actions">';
        html += '<button type="button" class="button cancel-copy">' + sky_business_api.i18n.cancel + '</button>';
        html += '<button type="button" class="button button-primary apply-copy">' + sky_business_api.i18n.apply + '</button>';
        html += '</div>';
        html += '</div>';
        
        $('body').append(html);
    }
    
    // Apply copy
    $(document).on('click', '.apply-copy', function() {
        var sourceDay = $('#copy-source-day').val();
        var $sourceRow = $('.day-row[data-day="' + sourceDay + '"]');
        var status = $sourceRow.find('.status-select').val();
        var slots = [];
        
        if (status === 'open') {
            $sourceRow.find('.time-slot').each(function() {
                var open = $(this).find('input[type="time"]').eq(0).val();
                var close = $(this).find('input[type="time"]').eq(1).val();
                slots.push({open: open, close: close});
            });
        }
        
        $('.day-checkbox:checked').each(function() {
            var targetDay = $(this).val();
            var $targetRow = $('.day-row[data-day="' + targetDay + '"]');
            
            $targetRow.find('.status-select').val(status).trigger('change');
            
            if (status === 'open' && slots.length > 0) {
                var $hoursDiv = $('#hours-' + targetDay);
                $hoursDiv.empty();
                
                slots.forEach(function(slot, index) {
                    addTimeSlot(targetDay, index);
                    var $lastSlot = $hoursDiv.find('.time-slot').last();
                    $lastSlot.find('input[type="time"]').eq(0).val(slot.open);
                    $lastSlot.find('input[type="time"]').eq(1).val(slot.close);
                });
            }
        });
        
        $('.copy-dialog-overlay, .copy-dialog').remove();
        showNotice(sky_business_api.i18n.hours_copied, 'success');
    });
    
    // Cancel copy
    $(document).on('click', '.cancel-copy, .copy-dialog-overlay', function() {
        $('.copy-dialog-overlay, .copy-dialog').remove();
    });
    
    /**
     * Initialize API action buttons
     */
    function initializeAPIActions() {
        // Test API connection
        $('#test-api').click(function() {
            var button = $(this);
            var spinner = $('.spinner');
            
            button.prop('disabled', true);
            spinner.addClass('is-active');
            
            $.post(ajaxurl, {
                action: 'sky_seo_test_api',
                _ajax_nonce: sky_business_api.nonce
            }, function(response) {
                button.prop('disabled', false);
                spinner.removeClass('is-active');
                
                if (response.success) {
                    showNotice(response.data, 'success');
                } else {
                    showNotice(response.data, 'error');
                }
            });
        });
        
        // Fetch new reviews
        $('#fetch-new-reviews').click(function() {
            var button = $(this);
            var spinner = $('.spinner');
            
            // Show confirmation
            if (!confirm(sky_business_api.i18n.fetch_confirm)) {
                return;
            }
            
            button.prop('disabled', true);
            spinner.addClass('is-active');
            
            $.post(ajaxurl, {
                action: 'sky_seo_fetch_new_reviews',
                place_id: $('#place_id').val(),
                force: false,
                _ajax_nonce: sky_business_api.nonce
            }, function(response) {
                button.prop('disabled', false);
                spinner.removeClass('is-active');
                
                if (response.success) {
                    showNotice(response.data.message, 'success');
                    // Refresh stats after fetch
                    updateUsageStats();
                    updateReviewStats();
                } else {
                    showNotice(response.data, 'error');
                }
            });
        });
        
        // Update Google Metadata (Review Count) Only
        $('#update-metadata').click(function() {
            var button = $(this);
            var spinner = $('.spinner');
            var originalText = button.text();
            
            button.prop('disabled', true).text('Updating...');
            spinner.addClass('is-active');
            
            $.post(ajaxurl, {
                action: 'sky_seo_update_google_metadata',
                _ajax_nonce: sky_business_api.nonce
            }, function(response) {
                button.prop('disabled', false).text(originalText);
                spinner.removeClass('is-active');
                
                if (response.success) {
                    showNotice(response.data.message, 'success');
                    // Refresh stats
                    updateReviewStats();
                } else {
                    showNotice(response.data || 'Error updating metadata', 'error');
                }
            });
        });
        
        // View stored reviews - Updated to use tab
        $('#view-reviews').click(function() {
            window.location.href = sky_business_api.admin_url + 'admin.php?page=sky-seo-business-api&tab=reviews';
        });
    }
    
    /**
     * Update usage statistics
     */
    function updateUsageStats() {
        $.post(ajaxurl, {
            action: 'sky_seo_get_api_usage',
            _ajax_nonce: sky_business_api.nonce
        }, function(response) {
            if (response.success) {
                var stats = response.data;
                var percentUsed = (stats.monthly_used / stats.monthly_limit) * 100;
                
                $('#monthly-usage-text').text(stats.monthly_used + ' / ' + stats.monthly_limit);
                $('#monthly-usage-bar').css('width', percentUsed + '%');
                
                if (percentUsed > 80) {
                    $('#monthly-usage-bar').css('background-color', '#dc3232');
                }
                
                $('#daily-usage-text').text(stats.daily_used + ' / ' + stats.daily_limit);
            }
        });
    }
    
    /**
     * Update review statistics
     */
    function updateReviewStats() {
        $.post(ajaxurl, {
            action: 'sky_seo_get_review_stats',
            place_id: $('#place_id').val(),
            _ajax_nonce: sky_business_api.nonce
        }, function(response) {
            if (response.success) {
                var stats = response.data;
                $('#total-reviews').text(stats.total_reviews);
                $('#average-rating').text(stats.average_rating);
                
                // Update star display
                var stars = '';
                for (var i = 0; i < Math.round(stats.average_rating); i++) {
                    stars += '★';
                }
                $('#average-rating').closest('.stat-subtitle').find('.rating-stars').text(stars);
                
                if (stats.last_fetch_time) {
                    var lastFetch = new Date(stats.last_fetch_time);
                    $('#last-fetch-time').text(lastFetch.toLocaleString());
                }
            }
        });
    }
    
    /**
     * Show notice message
     */
    function showNotice(message, type) {
        var noticeClass = type === 'success' ? 'notice-success' : 'notice-error';
        var html = '<div class="notice ' + noticeClass + ' is-dismissible"><p>' + message + '</p></div>';
        
        $('#test-results').html(html);
        
        // Make notice dismissible
        $('#test-results .notice').on('click', '.notice-dismiss', function() {
            $(this).parent().fadeOut();
        });
        
        // Auto-hide after 5 seconds
        setTimeout(function() {
            $('#test-results .notice').fadeOut();
        }, 5000);
    }
    
    // Update stats every 30 seconds only on settings tab
    if (currentTab === 'settings') {
        setInterval(function() {
            updateUsageStats();
            updateReviewStats();
        }, 30000);
    }
});