// Sky SEO Boost - General Settings JavaScript

jQuery(document).ready(function($) {
    // SEO Plugin Selection Handler
    const seoPluginSelect = $('#active_seo_plugin');
    const seoPluginSettings = $('#seo-plugin-settings');
    const seoPluginConfigContent = $('#seo-plugin-config-content');
    
    // Function to load plugin-specific settings
    function loadPluginSettings(plugin) {
        if (plugin === 'none') {
            seoPluginSettings.hide();
            return;
        }
        
        seoPluginSettings.show();
        seoPluginConfigContent.addClass('loading');
        
        $.ajax({
            url: ajaxurl,
            type: 'POST',
            data: {
                action: 'sky_seo_load_plugin_settings',
                plugin: plugin,
                nonce: skySeoSettings.nonce
            },
            success: function(response) {
                if (response.success) {
                    seoPluginConfigContent.html(response.data.content);
                    seoPluginConfigContent.removeClass('loading');
                }
            },
            error: function() {
                seoPluginConfigContent.html('<p>Error loading plugin settings.</p>');
                seoPluginConfigContent.removeClass('loading');
            }
        });
    }
    
    // Load settings on page load if plugin is selected
    if (seoPluginSelect.length && seoPluginSelect.val() !== 'none') {
        loadPluginSettings(seoPluginSelect.val());
    }
    
    // Load settings on plugin change
    seoPluginSelect.on('change', function() {
        loadPluginSettings($(this).val());
    });
    
    // Duplicate Feature Settings
    const duplicateFeatureEnabled = $('input[name="sky_seo_settings[duplicate_feature_enabled]"]');
    const duplicateOptions = $('.sky-seo-settings-card').find('input[name^="sky_seo_settings[duplicate_"]').not(duplicateFeatureEnabled);
    const duplicateSelects = $('.sky-seo-settings-card').find('select[name^="sky_seo_settings[duplicate_"]');
    
    // Enable/disable duplicate options based on main checkbox
    function toggleDuplicateOptions() {
        if (duplicateFeatureEnabled.is(':checked')) {
            duplicateOptions.prop('disabled', false);
            duplicateSelects.prop('disabled', false);
        } else {
            duplicateOptions.prop('disabled', true);
            duplicateSelects.prop('disabled', true);
        }
    }
    
    duplicateFeatureEnabled.on('change', toggleDuplicateOptions);
    
    // Initialize state on page load
    if (duplicateFeatureEnabled.length) {
        toggleDuplicateOptions();
    }
    
    // Post type selection logic
    const postTypeCheckboxes = $('input[name="sky_seo_settings[duplicate_post_types][]"]');
    const selectAllPostTypes = $('<a href="#" style="margin-left: 10px;">Select All</a>');
    const selectNonePostTypes = $('<a href="#" style="margin-left: 5px;">Select None</a>');
    
    postTypeCheckboxes.first().parent().before(
        $('<div style="margin-bottom: 10px;">').append(selectAllPostTypes).append(' | ').append(selectNonePostTypes)
    );
    
    selectAllPostTypes.on('click', function(e) {
        e.preventDefault();
        postTypeCheckboxes.prop('checked', true);
    });
    
    selectNonePostTypes.on('click', function(e) {
        e.preventDefault();
        postTypeCheckboxes.prop('checked', false);
    });
    
    // Sub-tab persistence
    const subTabs = $('.sky-seo-sub-tab');
    subTabs.on('click', function(e) {
        // Store active sub-tab in sessionStorage
        const subTabName = $(this).attr('href').split('sub_tab=')[1];
        if (subTabName) {
            sessionStorage.setItem('sky_seo_active_sub_tab', subTabName);
        }
    });
    
    // Smooth transitions for sub-tabs
    $('.sky-seo-settings-section').hide();
    $('.sky-seo-settings-section:first').show();
    
    // Add loading state when saving settings
    $('.sky-seo-settings-form').on('submit', function() {
        const submitButton = $(this).find('input[type="submit"]');
        submitButton.val('Saving...').prop('disabled', true);
    });
    
    // Tooltip for help text
    $('.sky-seo-settings-card .description').each(function() {
        if ($(this).text().length > 100) {
            $(this).addClass('sky-seo-truncate');
            const fullText = $(this).text();
            const truncatedText = fullText.substring(0, 100) + '...';
            
            $(this).text(truncatedText);
            $(this).append(' <a href="#" class="sky-seo-read-more">Read more</a>');
            
            $(this).on('click', '.sky-seo-read-more', function(e) {
                e.preventDefault();
                const parent = $(this).parent();
                if ($(this).text() === 'Read more') {
                    parent.text(fullText).append(' <a href="#" class="sky-seo-read-more">Read less</a>');
                } else {
                    parent.text(truncatedText).append(' <a href="#" class="sky-seo-read-more">Read more</a>');
                }
            });
        }
    });
    
    // Validation for sitemap priority fields
    $('input[id^="sitemap_priority_"]').on('blur', function() {
        const value = parseFloat($(this).val());
        if (isNaN(value) || value < 0 || value > 1) {
            $(this).addClass('error');
            if (!$(this).next('.field-error').length) {
                $(this).after('<span class="field-error">Value must be between 0 and 1</span>');
            }
        } else {
            $(this).removeClass('error');
            $(this).next('.field-error').remove();
        }
    });
    
    // Auto-save indicator
    let saveTimeout;
    $('.sky-seo-settings-form input, .sky-seo-settings-form select').on('change', function() {
        clearTimeout(saveTimeout);
        const $indicator = $('#sky-seo-autosave-indicator');
        
        if (!$indicator.length) {
            $('.sky-seo-settings-form .submit').append('<span id="sky-seo-autosave-indicator" style="margin-left: 10px; color: #666;">Changes detected</span>');
        } else {
            $indicator.text('Changes detected').css('color', '#666');
        }
        
        saveTimeout = setTimeout(function() {
            $('#sky-seo-autosave-indicator').text('Remember to save changes').css('color', '#d63638');
        }, 3000);
    });

    // Sitemap Diagnostic Tools

    // Flush Rewrite Rules Button
    $('#sky-seo-flush-rewrite').on('click', function(e) {
        e.preventDefault();
        const $btn = $(this);
        const originalHtml = $btn.html();

        $btn.prop('disabled', true).html('<span class="dashicons dashicons-update spin" style="margin-top: 3px;"></span> Flushing...');

        $.ajax({
            url: ajaxurl,
            type: 'POST',
            data: {
                action: 'sky_seo_flush_rewrite_rules',
                nonce: skySeoSettings.nonce
            },
            success: function(response) {
                if (response.success) {
                    $btn.html('<span class="dashicons dashicons-yes" style="margin-top: 3px; color: #46b450;"></span> Success!');

                    // Show success message
                    const $notice = $('<div class="notice notice-success inline" style="margin-top: 10px;"><p>' + response.data.message + '</p></div>');
                    $btn.closest('div').append($notice);

                    setTimeout(function() {
                        $notice.fadeOut(function() { $(this).remove(); });
                        $btn.prop('disabled', false).html(originalHtml);
                    }, 3000);
                } else {
                    $btn.html('<span class="dashicons dashicons-no" style="margin-top: 3px; color: #d63638;"></span> Failed');
                    alert('Error: ' + (response.data ? response.data.message : 'Unknown error'));

                    setTimeout(function() {
                        $btn.prop('disabled', false).html(originalHtml);
                    }, 2000);
                }
            },
            error: function() {
                $btn.html('<span class="dashicons dashicons-no" style="margin-top: 3px;"></span> Error');
                alert('AJAX error occurred. Please try again.');

                setTimeout(function() {
                    $btn.prop('disabled', false).html(originalHtml);
                }, 2000);
            }
        });
    });

    // Auto-Detect SEO Plugin Button
    $('#sky-seo-auto-detect-plugin').on('click', function(e) {
        e.preventDefault();
        const $btn = $(this);
        const originalHtml = $btn.html();

        $btn.prop('disabled', true).html('<span class="dashicons dashicons-update spin" style="margin-top: 3px;"></span> Detecting...');

        $.ajax({
            url: ajaxurl,
            type: 'POST',
            data: {
                action: 'sky_seo_auto_detect_plugin',
                nonce: skySeoSettings.nonce
            },
            success: function(response) {
                if (response.success) {
                    $btn.html('<span class="dashicons dashicons-yes" style="margin-top: 3px; color: #46b450;"></span> Detected!');

                    // Update dropdown
                    if (response.data.plugin !== 'none') {
                        $('#active_seo_plugin').val(response.data.plugin).trigger('change');
                    }

                    // Show success message
                    const $notice = $('<div class="notice notice-success inline" style="margin-top: 10px;"><p>' + response.data.message + '</p></div>');
                    $btn.closest('div').append($notice);

                    setTimeout(function() {
                        $notice.fadeOut(function() { $(this).remove(); });
                        $btn.prop('disabled', false).html(originalHtml);
                    }, 3000);
                } else {
                    $btn.html('<span class="dashicons dashicons-no" style="margin-top: 3px; color: #d63638;"></span> Not Found');
                    alert(response.data.message);

                    setTimeout(function() {
                        $btn.prop('disabled', false).html(originalHtml);
                    }, 2000);
                }
            },
            error: function() {
                $btn.html('<span class="dashicons dashicons-no" style="margin-top: 3px;"></span> Error');
                alert('AJAX error occurred. Please try again.');

                setTimeout(function() {
                    $btn.prop('disabled', false).html(originalHtml);
                }, 2000);
            }
        });
    });

    // Test Sitemaps Button
    $('#sky-seo-test-sitemaps').on('click', function(e) {
        e.preventDefault();
        const $btn = $(this);
        const $results = $('#sitemap-test-results');
        const originalHtml = $btn.html();

        $btn.prop('disabled', true).html('<span class="dashicons dashicons-update spin" style="margin-top: 3px;"></span> Testing...');
        $results.html('<p>Testing sitemap URLs... Please wait.</p>').show();

        $.ajax({
            url: ajaxurl,
            type: 'POST',
            data: {
                action: 'sky_seo_test_sitemaps',
                nonce: skySeoSettings.nonce
            },
            success: function(response) {
                if (response.success) {
                    $btn.prop('disabled', false).html(originalHtml);

                    // Build results table
                    let html = '<h4>Sitemap Test Results</h4>';
                    html += '<table class="widefat" style="margin-top: 10px;">';
                    html += '<thead><tr><th>URL</th><th>Status</th><th>Response</th></tr></thead>';
                    html += '<tbody>';

                    response.data.results.forEach(function(result) {
                        const statusIcon = result.status === 200 ?
                            '<span class="dashicons dashicons-yes" style="color: #46b450;"></span>' :
                            '<span class="dashicons dashicons-no" style="color: #d63638;"></span>';

                        const statusText = result.status === 200 ?
                            '<span style="color: #46b450;">✓ Accessible</span>' :
                            '<span style="color: #d63638;">✗ ' + result.status + ' Error</span>';

                        html += '<tr>';
                        html += '<td><code>' + result.url + '</code></td>';
                        html += '<td>' + statusIcon + ' ' + statusText + '</td>';
                        html += '<td>' + result.message + '</td>';
                        html += '</tr>';
                    });

                    html += '</tbody></table>';

                    $results.html(html);
                } else {
                    $btn.prop('disabled', false).html(originalHtml);
                    $results.html('<div class="notice notice-error inline"><p>Error: ' + response.data.message + '</p></div>');
                }
            },
            error: function() {
                $btn.prop('disabled', false).html(originalHtml);
                $results.html('<div class="notice notice-error inline"><p>AJAX error occurred. Please try again.</p></div>');
            }
        });
    });

    // Add spin animation for loading indicators
    if (!$('style:contains(".spin")').length) {
        $('<style>')
            .prop('type', 'text/css')
            .html('@keyframes spin { 0% { transform: rotate(0deg); } 100% { transform: rotate(360deg); } } .spin { animation: spin 1s linear infinite; }')
            .appendTo('head');
    }
});