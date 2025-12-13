/**
 * Sky SEO Boost - WhatsApp Business Frontend JavaScript
 * 
 * @package Sky_SEO_Boost
 * @subpackage WhatsApp_Business
 * @version 1.0.1
 */

(function($) {
    'use strict';

    // WhatsApp Frontend Handler
    const SkySeoWhatsAppFront = {
        
        // Configuration
        config: {},
        popupShown: false,
        sessionStarted: false,
        isProcessingClick: false,
        clickDebounceTimer: null,
        
        // Initialize
        init: function() {
            // Get config from localized data
            this.config = window.skySeoWhatsAppFront ? window.skySeoWhatsAppFront.config : {};

            // Debug log
            console.log('WhatsApp Frontend Init:', this.config);

            // Detect and apply RTL support
            this.detectRTL();

            // Initialize components
            this.initFloatingWidget();
            this.initElementorWidgets();
            this.initButtonWidgets();
            this.bindEvents();
            this.initAutoPopup();
            this.checkMobileApp();
        },

        // Detect RTL languages and apply appropriate classes
        detectRTL: function() {
            const html = document.documentElement;
            const body = document.body;
            const lang = html.getAttribute('lang') || body.getAttribute('lang') || '';
            const dir = html.getAttribute('dir') || body.getAttribute('dir') || '';

            // RTL languages
            const rtlLanguages = ['ar', 'he', 'fa', 'ur', 'yi', 'ji'];
            const isRTL = dir === 'rtl' || rtlLanguages.some(rtlLang => lang.startsWith(rtlLang));

            if (isRTL) {
                // Add RTL class and dir attribute to widget and all popup elements
                $('.sky-whatsapp-widget').addClass('sky-whatsapp-rtl').attr('dir', 'rtl');
                $('.sky-whatsapp-popup').attr('dir', 'rtl');
                $('.sky-whatsapp-popup-inner').attr('dir', 'rtl');
                $('.sky-whatsapp-popup-header').attr('dir', 'rtl');
                $('.sky-whatsapp-popup-body').attr('dir', 'rtl');
                $('.sky-whatsapp-info').attr('dir', 'rtl');
                $('.sky-whatsapp-message').attr('dir', 'rtl');

                // CRITICAL FIX: Apply inline styles to force RTL layout
                // This ensures the styles are applied regardless of CSS specificity issues
                $('.sky-whatsapp-popup-header').css({
                    'flex-direction': 'row-reverse',
                    'direction': 'rtl'
                });

                $('.sky-whatsapp-avatar, .sky-whatsapp-avatar-placeholder').css({
                    'margin-right': '0',
                    'margin-left': '15px'
                });

                $('.sky-whatsapp-info').css({
                    'padding-right': '0',
                    'padding-left': '40px',
                    'text-align': 'right'
                });

                $('.sky-whatsapp-close, button.sky-whatsapp-close').css({
                    'right': 'auto',
                    'left': '11px'
                });

                $('.sky-whatsapp-message-container').css({
                    'flex-direction': 'row-reverse'
                });

                $('.sky-whatsapp-message').css({
                    'margin-left': '0',
                    'margin-right': '8px',
                    'text-align': 'right'
                });

                // Also add to body if not already present
                if (!body.hasAttribute('dir')) {
                    body.setAttribute('dir', 'rtl');
                }
                if (!body.classList.contains('rtl')) {
                    body.classList.add('rtl');
                }

                console.log('WhatsApp Widget: RTL mode enabled with inline styles for language:', lang);
            }
        },
        
        // Bind events
        bindEvents: function() {
            // Floating widget button click
            $(document).on('click', '.sky-whatsapp-button', this.handleButtonClick.bind(this));
            
            // Popup close button
            $(document).on('click', '.sky-whatsapp-close', function(e) {
                e.preventDefault();
                e.stopPropagation();
                SkySeoWhatsAppFront.closePopup();
            });
            
            // Start chat button
            $(document).on('click', '.sky-whatsapp-start-chat', this.startChat.bind(this));
            
            // Click outside popup to close
            $(document).on('mousedown', function(e) {
                if ($(e.target).closest('.sky-whatsapp-button').length ||
                    $(e.target).closest('.sky-whatsapp-popup').length) {
                    return;
                }
                
                const $popup = $('.sky-whatsapp-popup.show');
                if ($popup.length && 
                    !$(e.target).closest('.sky-whatsapp-widget').length && 
                    !$(e.target).closest('.sky-whatsapp-elementor-widget').length &&
                    !$(e.target).closest('.sky-whatsapp-temp-popup').length) {
                    SkySeoWhatsAppFront.closePopup();
                }
            });
            
            // Escape key to close popup
            $(document).on('keydown', function(e) {
                if (e.key === 'Escape') {
                    SkySeoWhatsAppFront.closePopup();
                }
            });
            
            // Track page visibility for accurate analytics
            document.addEventListener('visibilitychange', this.handleVisibilityChange.bind(this));
        },
        
        // Initialize floating widget
        initFloatingWidget: function() {
            const widget = $('.sky-whatsapp-widget');
            
            if (widget.length === 0) {
                return;
            }
            
            // Set initial state
            this.sessionStarted = true;
            
            // Show widget immediately without animation
            widget.css({
                'opacity': '1',
                'transform': 'none'
            });
            
            // Check for saved popup state
            if (this.getCookie('sky_whatsapp_popup_closed') !== 'true') {
                this.popupShown = false;
            }
        },
        
        // Initialize Elementor widgets
        initElementorWidgets: function() {
            $('.sky-whatsapp-elementor-widget').each(function() {
                const widget = $(this);
                const popupDelay = widget.data('popup-delay');
                
                if (popupDelay && popupDelay > 0) {
                    setTimeout(function() {
                        widget.find('.sky-whatsapp-popup').addClass('show');
                        SkySeoWhatsAppFront.popupShown = true;
                    }, popupDelay * 1000);
                }
            });
        },
        
        // Initialize button widgets
        initButtonWidgets: function() {
            $('.sky-whatsapp-button-wrapper a').on('click', function(e) {
                const button = $(this);
                
                if (button.hasClass('sky-whatsapp-popup-trigger')) {
                    e.preventDefault();
                    SkySeoWhatsAppFront.showPopupForButton(button);
                } else {
                    // Track button click
                    SkySeoWhatsAppFront.trackClick('button', button.data('source') || 'button');
                }
            });
        },
        
        // Handle button click
        handleButtonClick: function(e) {
            e.preventDefault();
            e.stopPropagation();
            
            console.log('WhatsApp button clicked');
            
            const button = $(e.currentTarget);
            const widget = button.closest('.sky-whatsapp-widget, .sky-whatsapp-elementor-widget');
            const popup = widget.find('.sky-whatsapp-popup');
            
            // Add click animation to button
            button.addClass('clicked');
            setTimeout(function() {
                button.removeClass('clicked');
            }, 400);
            
            if (popup.length && this.config.showPopup !== false) {
                this.togglePopup(popup);
            } else {
                this.startChat(e);
            }
        },
        
        // Toggle popup
        togglePopup: function(popup) {
            if (popup.hasClass('show')) {
                this.closePopup();
            } else {
                this.showPopup(popup);
            }
        },
        
        // Show popup
        showPopup: function(popup) {
            if (!popup || !popup.length || popup.hasClass('show')) {
                return;
            }

            // Close any other open popups
            $('.sky-whatsapp-popup.show').not(popup).each(function() {
                $(this).removeClass('show closing');
            });

            // Remove any closing class
            popup.removeClass('closing');

            // Show popup with simple fade
            popup.css('display', 'block');

            // Force reflow
            popup[0].offsetHeight;

            // Add show class
            requestAnimationFrame(function() {
                popup.addClass('show');
            });

            this.popupShown = true;

            // Re-apply RTL styles if needed (in case they were lost)
            const html = document.documentElement;
            const body = document.body;
            const lang = html.getAttribute('lang') || body.getAttribute('lang') || '';
            const dir = html.getAttribute('dir') || body.getAttribute('dir') || '';
            const rtlLanguages = ['ar', 'he', 'fa', 'ur', 'yi', 'ji'];
            const isRTL = dir === 'rtl' || rtlLanguages.some(rtlLang => lang.startsWith(rtlLang));

            if (isRTL) {
                setTimeout(function() {
                    $('.sky-whatsapp-popup-header').css({
                        'flex-direction': 'row-reverse',
                        'direction': 'rtl'
                    });
                    $('.sky-whatsapp-avatar, .sky-whatsapp-avatar-placeholder').css({
                        'margin-right': '0',
                        'margin-left': '15px'
                    });
                    $('.sky-whatsapp-info').css({
                        'padding-right': '0',
                        'padding-left': '40px',
                        'text-align': 'right'
                    });
                    $('.sky-whatsapp-message-container').css({
                        'flex-direction': 'row-reverse'
                    });
                    $('.sky-whatsapp-message').css({
                        'margin-left': '0',
                        'margin-right': '8px',
                        'text-align': 'right'
                    });
                }, 50);
            }

            // Track popup view
            this.trackEvent('popup_view');

            // Focus on close button for accessibility
            setTimeout(function() {
                popup.find('.sky-whatsapp-close').focus();
            }, 300);
        },
        
        // Close popup
        closePopup: function() {
            const popup = $('.sky-whatsapp-popup.show');
            
            if (popup.length) {
                // Add closing class
                popup.addClass('closing').removeClass('show');
                
                // Hide after transition
                setTimeout(function() {
                    popup.removeClass('closing').css('display', 'none');
                }, 200);
                
                // Set cookie to remember closed state
                this.setCookie('sky_whatsapp_popup_closed', 'true', 1);
                
                this.popupShown = false;
            }
        },
        
        // Show popup for button widget
        showPopupForButton: function(button) {
            // Create temporary popup
            const phone = button.data('phone');
            const message = button.data('message');
            
            const popupHtml = this.createPopupHtml({
                phone: phone,
                message: message
            });
            
            $('body').append(popupHtml);
            
            const popup = $('.sky-whatsapp-temp-popup');
            setTimeout(function() {
                popup.find('.sky-whatsapp-popup').addClass('show');
            }, 100);
        },
        
        // Start WhatsApp chat
        startChat: function(e) {
            if (e) {
                e.preventDefault();
            }
            
            const button = $(e ? e.currentTarget : null);
            const phone = button ? button.data('phone') : this.config.phone;
            const message = button ? button.data('message') : this.config.message;
            
            if (!phone) {
                console.error('WhatsApp phone number not configured');
                return;
            }
            
            // Track click BEFORE opening WhatsApp
            this.trackClick('widget', 'floating-widget');
            
            // Build WhatsApp URL
            const baseUrl = this.isMobile() ? 'whatsapp://send' : 'https://web.whatsapp.com/send';
            const params = new URLSearchParams();
            
            // Format phone number (remove spaces, dashes, etc.)
            const cleanPhone = phone.replace(/[^0-9+]/g, '');
            params.append('phone', cleanPhone);
            
            if (message) {
                params.append('text', message);
            }
            
            const whatsappUrl = `${baseUrl}?${params.toString()}`;
            
            // Add loading state
            const widget = $('.sky-whatsapp-widget');
            widget.addClass('sky-whatsapp-loading');
            
            // Small delay to ensure tracking completes
            setTimeout(() => {
                // Open WhatsApp
                if (this.isMobile() && this.hasWhatsAppApp()) {
                    // Mobile app
                    window.location.href = whatsappUrl;
                } else {
                    // Desktop or mobile web
                    window.open(whatsappUrl, '_blank', 'width=800,height=600');
                }
                
                // Remove loading state
                setTimeout(function() {
                    widget.removeClass('sky-whatsapp-loading');
                }, 1000);
                
                // Close popup if open
                this.closePopup();
            }, 100);
        },
        
        // Track click event with debouncing
        trackClick: function(clickType, source) {
            // Clear any existing timer
            if (this.clickDebounceTimer) {
                clearTimeout(this.clickDebounceTimer);
            }
            
            // Set new timer
            this.clickDebounceTimer = setTimeout(() => {
                this.performTracking(clickType, source);
            }, 100);
        },
        
        // Perform actual tracking
        performTracking: function(clickType, source) {
            // Prevent duplicate tracking
            if (this.isProcessingClick) {
                console.log('Already processing a click, skipping');
                return;
            }
            
            this.isProcessingClick = true;
            
            // Get page data
            const pageData = {
                page_url: window.location.href,
                page_title: document.title,
                referrer: document.referrer,
                click_type: clickType,
                source: source || ''
            };
            
            console.log('Tracking WhatsApp click:', pageData);
            
            // Ensure we have AJAX URL and nonce
            const ajaxUrl = window.skySeoWhatsAppFront?.ajaxurl || '/wp-admin/admin-ajax.php';
            const nonce = window.skySeoWhatsAppFront?.nonce || '';
            
            // Send tracking request
            $.ajax({
                url: ajaxUrl,
                type: 'POST',
                data: {
                    action: 'sky_seo_track_whatsapp_click',
                    nonce: nonce,
                    ...pageData
                },
                success: (response) => {
                    console.log('Track response:', response);
                    if (response.success) {
                        console.log('WhatsApp click tracked successfully');
                    } else {
                        console.error('Failed to track click:', response.data?.message);
                    }
                },
                error: (xhr, status, error) => {
                    console.error('Failed to track WhatsApp click:', error);
                    console.error('XHR:', xhr);
                    console.error('Status:', status);
                },
                complete: () => {
                    // Reset flag after a delay to prevent rapid re-clicks
                    setTimeout(() => {
                        this.isProcessingClick = false;
                    }, 1000);
                }
            });
            
            // Also track in Google Analytics if available
            if (typeof gtag !== 'undefined') {
                gtag('event', 'click', {
                    event_category: 'WhatsApp',
                    event_label: clickType,
                    value: source
                });
            }
            
            // Track in Facebook Pixel if available
            if (typeof fbq !== 'undefined') {
                fbq('track', 'Contact', {
                    content_name: 'WhatsApp',
                    content_category: clickType
                });
            }
        },
        
        // Track custom event
        trackEvent: function(eventName, data = {}) {
            // Internal tracking
            if (window.skySeoWhatsAppFront) {
                $.ajax({
                    url: window.skySeoWhatsAppFront.ajaxurl,
                    type: 'POST',
                    data: {
                        action: 'sky_seo_track_whatsapp_event',
                        nonce: window.skySeoWhatsAppFront.nonce,
                        event: eventName,
                        data: data
                    }
                });
            }
            
            // Google Analytics
            if (typeof gtag !== 'undefined') {
                gtag('event', eventName, {
                    event_category: 'WhatsApp',
                    ...data
                });
            }
        },
        
        // Initialize auto popup
        initAutoPopup: function() {
            if (!this.config.showPopup || this.popupShown) {
                return;
            }
            
            const popupDelay = parseInt(this.config.popupDelay) || 0;
            
            if (popupDelay > 0) {
                setTimeout(function() {
                    if (!SkySeoWhatsAppFront.popupShown && !SkySeoWhatsAppFront.getCookie('sky_whatsapp_popup_closed')) {
                        const popup = $('.sky-whatsapp-popup').first();
                        if (popup.length) {
                            SkySeoWhatsAppFront.showPopup(popup);
                        }
                    }
                }, popupDelay * 1000);
            }
        },
        
        // Check if mobile device
        isMobile: function() {
            return /Android|webOS|iPhone|iPad|iPod|BlackBerry|IEMobile|Opera Mini/i.test(navigator.userAgent);
        },
        
        // Check if WhatsApp app is installed (heuristic)
        hasWhatsAppApp: function() {
            const ua = navigator.userAgent.toLowerCase();
            
            // Check for WhatsApp in user agent
            if (ua.includes('whatsapp')) {
                return true;
            }
            
            // On iOS, we can't directly check, but most iOS users have WhatsApp
            if (/iphone|ipad|ipod/.test(ua)) {
                return true;
            }
            
            // On Android, most users have WhatsApp
            if (/android/.test(ua)) {
                return true;
            }
            
            return false;
        },
        
        // Check for mobile app and adjust behavior
        checkMobileApp: function() {
            if (this.isMobile()) {
                // Add mobile class
                $('body').addClass('sky-whatsapp-mobile');
                
                // Adjust popup behavior for mobile
                if (window.innerWidth < 768) {
                    $('.sky-whatsapp-popup').on('touchstart', function(e) {
                        if ($(e.target).hasClass('sky-whatsapp-popup')) {
                            SkySeoWhatsAppFront.closePopup();
                        }
                    });
                }
            }
        },
        
        // Handle visibility change
        handleVisibilityChange: function() {
            if (document.visibilityState === 'visible') {
                // Page is visible again
                this.trackEvent('page_visible');
            } else {
                // Page is hidden
                this.trackEvent('page_hidden');
            }
        },
        
        // Create popup HTML
        createPopupHtml: function(config) {
            return `
            <div class="sky-whatsapp-temp-popup" style="position: fixed; bottom: 20px; right: 20px; z-index: 999999;">
                <div class="sky-whatsapp-popup">
                    <div class="sky-whatsapp-popup-inner">
                        <div class="sky-whatsapp-popup-header">
                            <div class="sky-whatsapp-avatar-placeholder">
                                <svg viewBox="0 0 212 212" width="40" height="40">
                                    <path fill="#DFE5E7" d="M106 0C47.6 0 0 47.6 0 106s47.6 106 106 106 106-47.6 106-106S164.4 0 106 0zm0 40c22.1 0 40 17.9 40 40s-17.9 40-40 40-40-17.9-40-40 17.9-40 40-40zm0 150c-26.7 0-50.5-12.1-66.3-31.1 8.1-15.8 24.3-26.9 43.3-28.7 2.6 1.2 5.4 2.2 8.4 2.8 4.2.9 8.5 1.4 13 1.4s8.8-.5 13-1.4c3-.6 5.8-1.6 8.4-2.8 19 1.8 35.2 12.9 43.3 28.7C156.5 177.9 132.7 190 106 190z"/>
                                </svg>
                            </div>
                            <div class="sky-whatsapp-info">
                                <h4>WhatsApp Support</h4>
                                <p class="sky-whatsapp-status">Online</p>
                            </div>
                            <button class="sky-whatsapp-close" onclick="jQuery('.sky-whatsapp-temp-popup').remove()">&times;</button>
                        </div>
                        <div class="sky-whatsapp-popup-body">
                            <div class="sky-whatsapp-message-container">
                                <div class="sky-whatsapp-message">
                                    <p>Hi there 👋<br><br>How can I help you?</p>
                                    <span class="message-time">${new Date().toLocaleTimeString([], {hour: '2-digit', minute:'2-digit'})}</span>
                                </div>
                            </div>
                        </div>
                        <div class="sky-whatsapp-popup-footer">
                            <button class="sky-whatsapp-start-chat" data-phone="${config.phone}" data-message="${config.message || ''}">
                                Start Chat
                            </button>
                        </div>
                    </div>
                </div>
            </div>`;
        },
        
        // Cookie utilities
        setCookie: function(name, value, hours) {
            const date = new Date();
            date.setTime(date.getTime() + (hours * 60 * 60 * 1000));
            const expires = "expires=" + date.toUTCString();
            document.cookie = name + "=" + value + ";" + expires + ";path=/";
        },
        
        getCookie: function(name) {
            const nameEQ = name + "=";
            const ca = document.cookie.split(';');
            
            for (let i = 0; i < ca.length; i++) {
                let c = ca[i];
                while (c.charAt(0) === ' ') {
                    c = c.substring(1, c.length);
                }
                if (c.indexOf(nameEQ) === 0) {
                    return c.substring(nameEQ.length, c.length);
                }
            }
            return null;
        },
        
        // Utility to format phone number for display
        formatPhoneNumber: function(phone) {
            // Remove all non-numeric characters except +
            const cleaned = phone.replace(/[^0-9+]/g, '');
            
            // Format based on length and country code
            if (cleaned.startsWith('+1') && cleaned.length === 12) {
                // US/Canada format: +1 (XXX) XXX-XXXX
                return cleaned.replace(/(\+1)(\d{3})(\d{3})(\d{4})/, '$1 ($2) $3-$4');
            } else if (cleaned.startsWith('+44') && cleaned.length === 13) {
                // UK format: +44 XXXX XXX XXX
                return cleaned.replace(/(\+44)(\d{4})(\d{3})(\d{3})/, '$1 $2 $3 $4');
            } else {
                // Generic international format
                return cleaned;
            }
        },
        
        // Enhanced mobile detection
        getDeviceType: function() {
            const ua = navigator.userAgent.toLowerCase();
            
            if (/tablet|ipad|playbook|silk/i.test(ua)) {
                return 'tablet';
            }
            
            if (/mobile|iphone|ipod|android|blackberry|opera|mini|windows\sce|palm|smartphone|iemobile/i.test(ua)) {
                return 'mobile';
            }
            
            return 'desktop';
        },
        
        // Performance optimization - lazy load images
        lazyLoadImages: function() {
            const images = document.querySelectorAll('.sky-whatsapp-avatar[data-src]');
            
            if ('IntersectionObserver' in window) {
                const imageObserver = new IntersectionObserver(function(entries, observer) {
                    entries.forEach(function(entry) {
                        if (entry.isIntersecting) {
                            const image = entry.target;
                            image.src = image.dataset.src;
                            image.classList.add('loaded');
                            imageObserver.unobserve(image);
                        }
                    });
                });
                
                images.forEach(function(image) {
                    imageObserver.observe(image);
                });
            } else {
                // Fallback for older browsers
                images.forEach(function(image) {
                    image.src = image.dataset.src;
                    image.classList.add('loaded');
                });
            }
        }
    };
    
    // Initialize when DOM is ready
    $(document).ready(function() {
        // Add small delay to ensure all resources are loaded
        setTimeout(function() {
            SkySeoWhatsAppFront.init();
        }, 100);
    });
    
    // Also initialize for Elementor preview
    $(window).on('elementor/frontend/init', function() {
        if (window.elementorFrontend && window.elementorFrontend.isEditMode()) {
            // Re-initialize for Elementor editor
            setTimeout(function() {
                SkySeoWhatsAppFront.init();
            }, 1000);
        }
    });
    
    
    // WhatsApp Widget - Delayed Effects JavaScript
// This code adds pulse effect and notification badge after 10 seconds

document.addEventListener('DOMContentLoaded', function() {
    // Find the WhatsApp button
    const whatsappButton = document.querySelector('.sky-whatsapp-button');
    const whatsappWidget = document.querySelector('.sky-whatsapp-widget');
    
    if (!whatsappButton) {
        console.error('WhatsApp button not found');
        return;
    }
    
    // Delay time in milliseconds (10 seconds = 10000ms)
    const DELAY_TIME = 10000;
    
    // Function to show effects after delay
    function showDelayedEffects() {
        // Add pulse effect first
        whatsappButton.classList.add('show-pulse');
        console.log('WhatsApp pulse effect activated');
        
        // Add notification badge with a slight delay for better visual effect
        setTimeout(function() {
            whatsappButton.classList.add('show-notification');
            console.log('WhatsApp notification badge shown');
        }, 300);
    }
    
    // Start the delay timer when page loads
    const delayTimer = setTimeout(showDelayedEffects, DELAY_TIME);
    
    // Optional: Handle button click to remove notification
    whatsappButton.addEventListener('click', function() {
        // Remove notification badge when clicked
        this.classList.remove('show-notification');
        
        // Optional: Also stop pulse effect when clicked
        // this.classList.remove('show-pulse');
    });
    
    // Optional: Remove effects when popup opens
    // You'll need to add this where your popup open logic is
    function onPopupOpen() {
        whatsappWidget.classList.add('popup-open');
        whatsappButton.classList.remove('show-notification');
    }
    
    // Optional: Restore effects when popup closes
    function onPopupClose() {
        whatsappWidget.classList.remove('popup-open');
        // Optionally re-show notification after popup closes
        // whatsappButton.classList.add('show-notification');
    }
    
    // Optional: Function to manually trigger effects (useful for testing)
    window.showWhatsAppEffects = function() {
        clearTimeout(delayTimer);
        showDelayedEffects();
    };
    
    // Optional: Function to hide effects
    window.hideWhatsAppEffects = function() {
        whatsappButton.classList.remove('show-pulse', 'show-notification');
    };
    
    // Optional: Function to update notification number
    window.updateWhatsAppNotification = function(number) {
        // Create or update style for custom number
        let styleEl = document.getElementById('whatsapp-notification-style');
        if (!styleEl) {
            styleEl = document.createElement('style');
            styleEl.id = 'whatsapp-notification-style';
            document.head.appendChild(styleEl);
        }
        
        styleEl.innerHTML = `
            .sky-whatsapp-button.show-notification.custom-number::before {
                content: '${number}' !important;
            }
        `;
        
        whatsappButton.classList.add('custom-number');
    };
});

})(jQuery);