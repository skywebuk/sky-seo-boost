/**
 * Sky SEO Boost - WhatsApp Business Admin JavaScript
 * 
 * @package Sky_SEO_Boost
 * @subpackage WhatsApp_Business
 * @version 1.2.0
 */

(function($) {
    'use strict';

    /**
     * WhatsApp Admin Handler
     */
    const SkySeoWhatsAppAdmin = {
        
        // Properties
        config: {},
        mediaUploader: null,
        isDirty: false,
        
        /**
         * Initialize
         */
        init: function() {
            // Get localized data
            this.config = window.skySeoWhatsApp || {};
            
            // Bind events
            this.bindEvents();
            
            // Initialize components
            this.initializeSelect2();
            this.initializeTooltips();
            this.setupFormValidation();
            
            // Track form changes
            this.trackFormChanges();
        },
        
        /**
         * Bind all events
         */
        bindEvents: function() {
            // Form submission
            $('#sky-seo-whatsapp-config-form').on('submit', this.handleFormSubmit.bind(this));
            
            // Media upload
            $('.upload-button').on('click', this.handleMediaUpload.bind(this));
            $('.remove-button').on('click', this.handleMediaRemove.bind(this));
            
            // Copy trackable link
            $('#copy-trackable-link').on('click', this.copyTrackableLink.bind(this));
            
            // Toggle handlers
            $('#widget-enabled').on('change', this.handleWidgetToggle.bind(this));
            $('input[name*="[show_popup]"]').on('change', this.handlePopupToggle.bind(this));
            
            // Real-time phone validation
            $('#whatsapp_phone').on('input', this.validatePhoneNumber.bind(this));
            
            // Position preview
            $('input[name*="[float_position]"]').on('change', this.updatePositionPreview.bind(this));
            
            // Page type selections
            $('.sky-seo-page-type-option input').on('change', this.updatePageTypeStyles.bind(this));
            
            // Auto-save draft
            this.initializeAutoSave();
            
            // Keyboard shortcuts
            this.setupKeyboardShortcuts();
        },
        
        /**
         * Initialize Select2
         */
        initializeSelect2: function() {
            if ($.fn.select2) {
                $('.sky-seo-select2').select2({
                    placeholder: $(this).data('placeholder') || 'Select an option',
                    allowClear: true,
                    width: '100%'
                });
            }
        },
        
        /**
         * Initialize tooltips
         */
        initializeTooltips: function() {
            // Add tooltips to help icons
            $('.sky-seo-help-tip').each(function() {
                $(this).tipTip({
                    content: $(this).data('tip'),
                    fadeIn: 50,
                    fadeOut: 50,
                    delay: 200
                });
            });
        },
        
        /**
         * Setup form validation
         */
        setupFormValidation: function() {
            const $form = $('#sky-seo-whatsapp-config-form');
            
            // Add validation attributes
            $('#whatsapp_phone').attr({
                'pattern': '^\\+?[1-9]\\d{1,14}$',
                'title': this.config.strings?.phoneValidation || 'Please enter a valid phone number with country code'
            });
            
            // Custom validation on submit
            $form.on('invalid', function(e) {
                e.preventDefault();
                const $field = $(e.target);
                const $group = $field.closest('.sky-seo-form-group');
                
                // Add error class
                $group.addClass('has-error');
                
                // Show error message
                let $error = $group.find('.field-error');
                if (!$error.length) {
                    $error = $('<span class="field-error"></span>');
                    $field.after($error);
                }
                
                $error.text($field.attr('title') || 'This field is required');
            });
            
            // Remove error on input
            $form.find('input, textarea, select').on('input change', function() {
                const $group = $(this).closest('.sky-seo-form-group');
                $group.removeClass('has-error');
                $group.find('.field-error').remove();
            });
        },
        
        /**
         * Track form changes for unsaved changes warning
         */
        trackFormChanges: function() {
            const $form = $('#sky-seo-whatsapp-config-form');
            
            // Track initial form state
            const initialState = $form.serialize();
            
            // Monitor changes
            $form.on('change input', 'input, textarea, select', () => {
                this.isDirty = ($form.serialize() !== initialState);
            });
            
            // Warn on navigation if changes unsaved
            $(window).on('beforeunload', (e) => {
                if (this.isDirty) {
                    const message = this.config.strings?.unsavedChanges || 'You have unsaved changes. Are you sure you want to leave?';
                    e.returnValue = message;
                    return message;
                }
            });
        },
        
        /**
         * Handle form submission
         */
        handleFormSubmit: function(e) {
            e.preventDefault();
            
            const $form = $(e.target);
            const $submitBtn = $form.find('button[type="submit"]');
            const $spinner = $form.find('.spinner');
            const $successMsg = $form.find('.sky-seo-success-message');
            
            // Validate form
            if (!this.validateForm($form)) {
                return false;
            }
            
            // Show loading state
            $submitBtn.prop('disabled', true);
            $spinner.addClass('is-active');
            $successMsg.hide();
            
            // Prepare data
            const formData = $form.serialize();
            
            // Submit via AJAX
            $.ajax({
                url: this.config.ajaxurl,
                type: 'POST',
                data: {
                    action: 'sky_seo_save_whatsapp_config',
                    nonce: this.config.nonce,
                    form_data: formData
                },
                success: (response) => {
                    if (response.success) {
                        // Show success message
                        $successMsg.fadeIn();
                        
                        // Reset dirty state
                        this.isDirty = false;
                        
                        // Hide success message after 3 seconds
                        setTimeout(() => {
                            $successMsg.fadeOut();
                        }, 3000);
                        
                        // Trigger custom event
                        $(document).trigger('sky-seo-whatsapp-saved', response.data);
                    } else {
                        this.showError(response.data?.message || this.config.strings?.error);
                    }
                },
                error: () => {
                    this.showError(this.config.strings?.error || 'An error occurred. Please try again.');
                },
                complete: () => {
                    $submitBtn.prop('disabled', false);
                    $spinner.removeClass('is-active');
                }
            });
        },
        
        /**
         * Validate form
         */
        validateForm: function($form) {
            let isValid = true;
            
            // Check if widget is enabled
            const isEnabled = $('#widget-enabled').is(':checked');
            if (!isEnabled) {
                return true; // Skip validation if disabled
            }
            
            // Validate phone number
            const $phone = $('#whatsapp_phone');
            const phoneValue = $phone.val().trim();
            
            if (!phoneValue) {
                this.showFieldError($phone, this.config.strings?.phoneRequired || 'Phone number is required');
                isValid = false;
            } else if (!this.isValidPhoneNumber(phoneValue)) {
                this.showFieldError($phone, this.config.strings?.phoneInvalid || 'Please enter a valid phone number with country code');
                isValid = false;
            }
            
            return isValid;
        },
        
        /**
         * Validate phone number format
         */
        isValidPhoneNumber: function(phone) {
            // E.164 format validation
            const phoneRegex = /^\+?[1-9]\d{1,14}$/;
            return phoneRegex.test(phone.replace(/[\s\-\(\)]/g, ''));
        },
        
        /**
         * Real-time phone validation
         */
        validatePhoneNumber: function(e) {
            const $input = $(e.target);
            const value = $input.val().trim();
            const $group = $input.closest('.sky-seo-form-group');
            const $hint = $group.find('.phone-hint');
            
            // Remove any non-digit characters except +
            const cleaned = value.replace(/[^\d+]/g, '');
            
            if (cleaned !== value) {
                $input.val(cleaned);
            }
            
            // Show/hide validation hint
            if (cleaned && !this.isValidPhoneNumber(cleaned)) {
                if (!$hint.length) {
                    $input.after('<span class="phone-hint" style="color: #d63638; font-size: 12px;">Include country code (e.g., +44 for UK)</span>');
                }
            } else {
                $hint.remove();
            }
        },
        
        /**
         * Handle media upload
         */
        handleMediaUpload: function(e) {
            e.preventDefault();
            
            const $button = $(e.target);
            const $container = $button.closest('.sky-seo-media-upload');
            const $input = $container.find('input[type="hidden"]');
            const $preview = $container.find('.sky-seo-image-preview');
            const $removeBtn = $container.find('.remove-button');
            
            // Create media uploader
            if (this.mediaUploader) {
                this.mediaUploader.open();
                return;
            }
            
            this.mediaUploader = wp.media({
                title: this.config.strings?.selectImage || 'Select Profile Photo',
                button: {
                    text: this.config.strings?.useImage || 'Use this image'
                },
                multiple: false,
                library: {
                    type: 'image'
                }
            });
            
            // Handle selection
            this.mediaUploader.on('select', () => {
                const attachment = this.mediaUploader.state().get('selection').first().toJSON();
                
                // Update preview
                $preview.html(`<img src="${attachment.url}" alt="Profile">`);
                
                // Update input value
                $input.val(attachment.url).trigger('change');
                
                // Show remove button
                $removeBtn.show();
            });
            
            this.mediaUploader.open();
        },
        
        /**
         * Handle media remove
         */
        handleMediaRemove: function(e) {
            e.preventDefault();
            
            const $button = $(e.target);
            const $container = $button.closest('.sky-seo-media-upload');
            const $input = $container.find('input[type="hidden"]');
            const $preview = $container.find('.sky-seo-image-preview');
            
            // Reset preview
            $preview.html('<div class="sky-seo-placeholder"><span class="dashicons dashicons-format-image"></span></div>');
            
            // Clear input
            $input.val('').trigger('change');
            
            // Hide remove button
            $button.hide();
        },
        
        /**
         * Copy trackable link
         */
        copyTrackableLink: function(e) {
            e.preventDefault();
            
            const $button = $(e.target);
            const $input = $('#trackable-link');
            const originalText = $button.text();
            
            // Select and copy
            $input.select();
            document.execCommand('copy');
            
            // Update button text
            $button.text('Copied!');
            setTimeout(() => {
                $button.text(originalText);
            }, 2000);
            
            // Show success message
            this.showSuccess('Link copied to clipboard!');
        },
        
        /**
         * Handle widget toggle
         */
        handleWidgetToggle: function(e) {
            const isEnabled = $(e.target).is(':checked');
            const $form = $('#sky-seo-whatsapp-config-form');
            
            // Toggle form fields
            $form.find('.sky-seo-form-group').not(':first').toggle(isEnabled);
            
            // Show/hide related sections
            if (isEnabled) {
                $('.sky-seo-content-table-card').slideDown();
            } else {
                $('.sky-seo-content-table-card').slideUp();
            }
        },
        
        /**
         * Handle popup toggle
         */
        handlePopupToggle: function(e) {
            const showPopup = $(e.target).is(':checked');
            const $delayField = $('#popup_delay').closest('.sky-seo-inline-field');
            
            // Toggle delay field visibility
            $delayField.toggle(showPopup);
        },
        
        /**
         * Update position preview
         */
        updatePositionPreview: function(e) {
            const position = $(e.target).val();
            
            // Update visual indicator if needed
            $('.sky-seo-position-preview').removeClass('active');
            $(e.target).next('.sky-seo-position-preview').addClass('active');
        },
        
        /**
         * Update page type styles
         */
        updatePageTypeStyles: function(e) {
            const $checkbox = $(e.target);
            const $option = $checkbox.closest('.sky-seo-page-type-option');
            
            if ($checkbox.is(':checked')) {
                $option.addClass('selected');
            } else {
                $option.removeClass('selected');
            }
        },
        
        /**
         * Initialize auto-save
         */
        initializeAutoSave: function() {
            let autoSaveTimer;
            const autoSaveDelay = 30000; // 30 seconds
            
            $('#sky-seo-whatsapp-config-form').on('change input', 'input, textarea, select', () => {
                if (!this.isDirty) return;
                
                clearTimeout(autoSaveTimer);
                autoSaveTimer = setTimeout(() => {
                    this.autoSaveSettings();
                }, autoSaveDelay);
            });
        },
        
        /**
         * Auto-save settings
         */
        autoSaveSettings: function() {
            const $form = $('#sky-seo-whatsapp-config-form');
            const formData = $form.serialize();
            
            // Save to local storage
            if (window.localStorage) {
                localStorage.setItem('sky_whatsapp_draft', formData);
                localStorage.setItem('sky_whatsapp_draft_time', Date.now());
                
                // Show draft saved indicator
                this.showDraftSaved();
            }
        },
        
        /**
         * Show draft saved indicator
         */
        showDraftSaved: function() {
            const $indicator = $('<span class="draft-saved">Draft saved</span>');
            $('.sky-seo-form-actions').append($indicator);
            
            setTimeout(() => {
                $indicator.fadeOut(() => $indicator.remove());
            }, 3000);
        },
        
        /**
         * Setup keyboard shortcuts
         */
        setupKeyboardShortcuts: function() {
            $(document).on('keydown', (e) => {
                // Ctrl/Cmd + S to save
                if ((e.ctrlKey || e.metaKey) && e.key === 's') {
                    e.preventDefault();
                    $('#sky-seo-whatsapp-config-form').submit();
                }
                
                // Ctrl/Cmd + C to copy link
                if ((e.ctrlKey || e.metaKey) && e.key === 'c' && $('#trackable-link').is(':focus')) {
                    e.preventDefault();
                    $('#copy-trackable-link').click();
                }
            });
        },
        
        /**
         * Show field error
         */
        showFieldError: function($field, message) {
            const $group = $field.closest('.sky-seo-form-group');
            
            // Remove existing error
            $group.find('.field-error').remove();
            
            // Add error class and message
            $group.addClass('has-error');
            $field.after(`<span class="field-error" style="color: #d63638; font-size: 12px; display: block; margin-top: 4px;">${message}</span>`);
            
            // Focus field
            $field.focus();
        },
        
        /**
         * Show error message
         */
        showError: function(message) {
            // Create notice
            const $notice = $(`
                <div class="notice notice-error is-dismissible">
                    <p>${message}</p>
                </div>
            `);
            
            // Insert after header
            $('.sky-seo-dashboard-header').after($notice);
            
            // Auto remove after 5 seconds
            setTimeout(() => {
                $notice.fadeOut(() => $notice.remove());
            }, 5000);
            
            // Scroll to top
            $('html, body').animate({ scrollTop: 0 }, 300);
        },
        
        /**
         * Show success message
         */
        showSuccess: function(message) {
            // Create notice
            const $notice = $(`
                <div class="notice notice-success is-dismissible">
                    <p>${message}</p>
                </div>
            `);
            
            // Insert after header
            $('.sky-seo-dashboard-header').after($notice);
            
            // Auto remove after 3 seconds
            setTimeout(() => {
                $notice.fadeOut(() => $notice.remove());
            }, 3000);
        },
        
        /**
         * Restore draft if available
         */
        restoreDraft: function() {
            if (!window.localStorage) return;
            
            const draft = localStorage.getItem('sky_whatsapp_draft');
            const draftTime = localStorage.getItem('sky_whatsapp_draft_time');
            
            if (!draft || !draftTime) return;
            
            // Check if draft is less than 24 hours old
            const dayAgo = Date.now() - (24 * 60 * 60 * 1000);
            if (parseInt(draftTime) < dayAgo) {
                localStorage.removeItem('sky_whatsapp_draft');
                localStorage.removeItem('sky_whatsapp_draft_time');
                return;
            }
            
            // Ask user if they want to restore
            if (confirm(this.config.strings?.restoreDraft || 'A draft was found. Would you like to restore it?')) {
                // Parse and restore form data
                const params = new URLSearchParams(draft);
                for (const [key, value] of params) {
                    const $field = $(`[name="${key}"]`);
                    if ($field.length) {
                        if ($field.is(':checkbox')) {
                            $field.prop('checked', value === '1');
                        } else {
                            $field.val(value);
                        }
                    }
                }
                
                // Clear draft
                localStorage.removeItem('sky_whatsapp_draft');
                localStorage.removeItem('sky_whatsapp_draft_time');
                
                this.showSuccess('Draft restored successfully!');
            }
        }
    };
    
    // Initialize on document ready
    $(document).ready(function() {
        SkySeoWhatsAppAdmin.init();
        
        // Check for draft
        SkySeoWhatsAppAdmin.restoreDraft();
    });
    
    // Make available globally for debugging
    window.SkySeoWhatsAppAdmin = SkySeoWhatsAppAdmin;

})(jQuery);