(function($) {
    'use strict';
    
    $(document).ready(function() {
        // Initialize all review sliders
        initializeReviewSliders();
        
        // Auto-refresh status every minute
        initializeStatusRefresh();
        
        // Initialize hours table interactions
        initializeHoursTable();
        
        // Initialize read more functionality
        initializeReadMore();
        
        // Setup read more buttons after everything is loaded
        setTimeout(function() {
            setupReadMoreButtons();
        }, 500);
        
        // Initialize load more functionality
        initializeLoadMore();
        
        // Initialize review modal
        initializeReviewModal();
        
        // Initialize like functionality
        initializeLikeSystem();
    });
    
    /**
     * Initialize review sliders with enhanced carousel settings
     */
    function initializeReviewSliders() {
        $('.sky-seo-reviews-slider').each(function() {
            var $slider = $(this);
            var sliderId = $slider.attr('id');
            
            // Get all carousel settings from data attributes
            var itemsPerView = parseFloat($slider.data('items')) || 3;
            var spaceBetween = parseInt($slider.data('space-between')) || 30;
            var scrollPerPage = $slider.data('scroll-per-page') === 'yes';
            var loop = $slider.data('loop') === 'yes';
            var autoHeight = $slider.data('auto-height') === 'yes';
            var autoplay = $slider.data('autoplay') === 'yes';
            var autoplayDelay = parseInt($slider.data('autoplay-delay')) || 5000;
            var initOnScroll = $slider.data('init-on-scroll') === 'yes';
            var disabledOverflow = $slider.data('disabled-overflow') === 'yes';
            var centerMode = $slider.data('center') === 'yes';
            
            // Function to initialize Swiper
            var initializeSwiper = function() {
                if (typeof Swiper !== 'undefined' && sliderId) {
                    // Find the actual swiper container
                    var swiperEl = $slider.find('.swiper').length ? $slider.find('.swiper')[0] : $slider[0];
                    
                    // First, equalize all heights before initializing Swiper
                    equalizeReviewHeights(swiperEl);
                    
                    // Create Swiper configuration
                    var swiperConfig = {
                        slidesPerView: itemsPerView,
                        spaceBetween: spaceBetween,
                        loop: loop,
                        centeredSlides: centerMode,
                        grabCursor: true,
                        watchSlidesProgress: true,
                        watchOverflow: !disabledOverflow,
                        slidesPerGroup: scrollPerPage ? itemsPerView : 1,
                        autoHeight: autoHeight,
                        speed: 400,
                        navigation: {
                            nextEl: '#' + sliderId + ' .sky-navigation-arrows .swiper-button-next',
                            prevEl: '#' + sliderId + ' .sky-navigation-arrows .swiper-button-prev',
                        },
                        autoplay: autoplay ? { 
                            delay: autoplayDelay,
                            disableOnInteraction: false,
                            pauseOnMouseEnter: true
                        } : false,
                        breakpoints: {
                            320: {
                                slidesPerView: 1,
                                spaceBetween: 10,
                                centeredSlides: false,
                                slidesPerGroup: 1
                            },
                            480: {
                                slidesPerView: 1.2,
                                spaceBetween: 12,
                                centeredSlides: false,
                                slidesPerGroup: 1
                            },
                            640: {
                                slidesPerView: 1.5,
                                spaceBetween: 15,
                                centeredSlides: false,
                                slidesPerGroup: 1
                            },
                            768: {
                                slidesPerView: Math.min(2, itemsPerView),
                                spaceBetween: 16,
                                centeredSlides: false,
                                slidesPerGroup: scrollPerPage ? Math.min(2, itemsPerView) : 1
                            },
                            1024: {
                                slidesPerView: itemsPerView,
                                spaceBetween: spaceBetween,
                                centeredSlides: centerMode,
                                slidesPerGroup: scrollPerPage ? itemsPerView : 1
                            }
                        },
                        on: {
                            init: function() {
                                // Ensure heights are equalized after Swiper init
                                setTimeout(function() {
                                    equalizeReviewHeights(swiperEl);
                                }, 100);
                                
                                // Mark slider as initialized
                                $slider.addClass('sky-swiper-initialized');
                            },
                            resize: function() {
                                equalizeReviewHeights(swiperEl);
                            },
                            breakpointChange: function() {
                                // Re-equalize on breakpoint change
                                setTimeout(function() {
                                    equalizeReviewHeights(swiperEl);
                                }, 100);
                            }
                        }
                    };
                    
                    // Initialize Swiper
                    var swiper = new Swiper(swiperEl, swiperConfig);
                    
                    // Store swiper instance for later use
                    $slider.data('swiper-instance', swiper);
                }
            };
            
            // Check if we should initialize on scroll
            if (initOnScroll) {
                var sliderInitialized = false;
                var checkSliderInView = function() {
                    if (!sliderInitialized) {
                        var rect = $slider[0].getBoundingClientRect();
                        var windowHeight = window.innerHeight || document.documentElement.clientHeight;
                        
                        // Check if slider is in viewport
                        if (rect.top <= windowHeight && rect.bottom >= 0) {
                            sliderInitialized = true;
                            initializeSwiper();
                            // Remove scroll listener once initialized
                            $(window).off('scroll', checkSliderInView);
                        }
                    }
                };
                
                // Add scroll listener
                $(window).on('scroll', checkSliderInView);
                // Check immediately in case already in view
                checkSliderInView();
            } else {
                // Initialize immediately
                initializeSwiper();
            }
        });
    }
    
    /**
     * Equalize review heights - but only for non-expanded items
     */
    function equalizeReviewHeights(sliderEl) {
        var $slider = $(sliderEl).closest('.sky-seo-reviews-slider');
        var $allSlides = $slider.find('.swiper-slide');
        
        if ($allSlides.length === 0) return;
        
        // If we haven't stored the standard height yet, calculate it
        if (!$slider.data('standard-height')) {
            var maxHeight = 0;
            
            // Temporarily reset all to auto to get natural heights
            $allSlides.find('.sky-review-item').each(function() {
                $(this).css('height', 'auto');
            });
            
            // Find the maximum height
            $allSlides.find('.sky-review-item').each(function() {
                var height = $(this).outerHeight();
                if (height > maxHeight) {
                    maxHeight = height;
                }
            });
            
            // Store this as the standard height
            $slider.data('standard-height', maxHeight);
        }
        
        var standardHeight = $slider.data('standard-height');
        
        // Apply the standard height to all non-expanded items
        $allSlides.find('.sky-review-item').each(function() {
            var $item = $(this);
            if (!$item.hasClass('sky-expanded-review')) {
                $item.css('height', standardHeight + 'px');
            }
        });
    }
    
    /**
     * Setup read more buttons based on character count
     */
    function setupReadMoreButtons() {
        $('.sky-review-text').each(function() {
            var $text = $(this);
            var $button = $text.closest('.sky-review-item').find('.sky-read-more-toggle');
            var textContent = $text.text().trim();
            
            // Show read more button if text is longer than threshold
            var characterThreshold = 200;
            
            if (textContent.length > characterThreshold) {
                $button.show();
                $text.addClass('sky-truncated');
                
                // Store the full text
                $text.data('full-text', textContent);
                
                // Set initial button text
                $button.text('READ MORE');
            } else {
                $button.hide();
                $text.removeClass('sky-truncated');
            }
        });
        
        // After setting up buttons, equalize heights for all sliders
        $('.sky-seo-reviews-slider').each(function() {
            var swiperEl = $(this).find('.swiper').length ? $(this).find('.swiper')[0] : this;
            equalizeReviewHeights(swiperEl);
        });
    }
    
    /**
     * Initialize read more functionality - UPDATED to use modal
     */
    function initializeReadMore() {
        $(document).on('click', '.sky-read-more-toggle', function(e) {
            e.preventDefault();
            
            var $button = $(this);
            var $reviewItem = $button.closest('.sky-review-item');
            var reviewData = extractReviewData($reviewItem);
            
            openReviewModal(reviewData);
        });
    }
    
    /**
     * Extract review data from review item
     */
    function extractReviewData($reviewItem) {
        var $authorPhoto = $reviewItem.find('.sky-author-photo');
        var $authorPlaceholder = $reviewItem.find('.sky-author-photo-placeholder');
        var $reviewText = $reviewItem.find('.sky-review-text');
        var fullText = $reviewText.data('full-text') || $reviewText.text();
        
        return {
            id: $reviewItem.data('review-id'),
            authorName: $reviewItem.find('.sky-review-author-name').text(),
            authorPhoto: $authorPhoto.length ? $authorPhoto.attr('src') : '',
            authorInitial: $authorPlaceholder.length ? $authorPlaceholder.text() : '',
            rating: $reviewItem.find('.sky-review-rating').clone().find('.sky-verified-review').remove().end().text().trim().length,
            time: $reviewItem.find('.sky-review-time').text(),
            text: fullText,
            platform: $reviewItem.find('.sky-google-icon').length ? 'google' : 
                     ($reviewItem.find('.sky-facebook-icon').length ? 'facebook' : 'trustpilot'),
            likes: parseInt($reviewItem.find('.sky-like-count').text()) || 0,
            isLiked: $reviewItem.find('.sky-review-like-button').hasClass('liked')
        };
    }
    
    /**
     * Open review modal
     */
    function openReviewModal(reviewData) {
        // Create modal if it doesn't exist
        if (!$('#sky-review-modal').length) {
            createReviewModal();
        }
        
        var $modal = $('#sky-review-modal');
        var $overlay = $('#sky-review-modal-overlay');
        
        // Update modal content
        if (reviewData.authorPhoto) {
            $modal.find('.sky-modal-author-photo').show().attr('src', reviewData.authorPhoto);
            $modal.find('.sky-modal-author-photo-placeholder').hide();
        } else {
            $modal.find('.sky-modal-author-photo').hide();
            $modal.find('.sky-modal-author-photo-placeholder').show().text(reviewData.authorInitial);
        }
        
        $modal.find('.sky-modal-author-name').text(reviewData.authorName);
        $modal.find('.sky-modal-rating').html('★'.repeat(reviewData.rating));
        $modal.find('.sky-modal-time').text(reviewData.time);
        $modal.find('.sky-modal-review-text').text(reviewData.text);
        
        // Update platform badge
        var platformIcon = '';
        if (reviewData.platform === 'google') {
            platformIcon = '<svg class="sky-google-icon" width="18" height="18" viewBox="0 0 18 18" fill="none" xmlns="http://www.w3.org/2000/svg"><path d="M17.64 9.2c0-.637-.057-1.251-.164-1.84H9v3.481h4.844c-.209 1.125-.843 2.078-1.796 2.717v2.258h2.908c1.702-1.567 2.684-3.874 2.684-6.615z" fill="#4285F4"/><path d="M9.003 18c2.43 0 4.467-.806 5.956-2.18L12.05 13.56c-.806.54-1.836.86-3.047.86-2.344 0-4.328-1.584-5.036-3.711H.96v2.332C2.44 15.983 5.485 18 9.003 18z" fill="#34A853"/><path d="M3.964 10.712c-.18-.54-.282-1.117-.282-1.71 0-.593.102-1.17.282-1.71V4.96H.957C.347 6.175 0 7.55 0 9.002c0 1.452.348 2.827.957 4.042l3.007-2.332z" fill="#FBBC05"/><path d="M9.003 3.58c1.321 0 2.508.454 3.44 1.345l2.582-2.58C13.464.891 11.428 0 9.002 0 5.485 0 2.44 2.017.96 4.958L3.967 7.29c.708-2.127 2.692-3.71 5.036-3.71z" fill="#EA4335"/></svg>';
        } else if (reviewData.platform === 'facebook') {
            platformIcon = '<svg class="sky-facebook-icon" width="18" height="18" viewBox="0 0 18 18" fill="none" xmlns="http://www.w3.org/2000/svg"><path d="M18 9C18 4.02944 13.9706 0 9 0C4.02944 0 0 4.02944 0 9C0 13.4921 3.29168 17.2155 7.59375 17.8907V11.6016H5.30859V9H7.59375V7.01719C7.59375 4.76156 8.93742 3.51562 10.9932 3.51562C11.9775 3.51562 13.0078 3.69141 13.0078 3.69141V5.90625H11.873C10.755 5.90625 10.4062 6.60006 10.4062 7.3125V9H12.9023L12.5033 11.6016H10.4062V17.8907C14.7083 17.2155 18 13.4921 18 9Z" fill="#1877F2"/></svg>';
        } else {
            platformIcon = '<svg class="sky-trustpilot-icon" width="18" height="18" viewBox="0 0 18 18" fill="none" xmlns="http://www.w3.org/2000/svg"><rect width="18" height="18" rx="2" fill="#00B67A"/><path d="M9 2L11.2 7.2H16.8L12.3 10.8L14.5 16L9 12.4L3.5 16L5.7 10.8L1.2 7.2H6.8L9 2Z" fill="white"/></svg>';
        }
        
        $modal.find('.sky-modal-platform-badge').html(platformIcon + ' ' + reviewData.platform.charAt(0).toUpperCase() + reviewData.platform.slice(1) + ' Review');
        
        // Update like button
        var $likeButton = $modal.find('.sky-modal-like-button');
        $likeButton.data('review-id', reviewData.id);
        $likeButton.find('.sky-like-count').text(reviewData.likes);
        
        // Check if user has liked this review from cookie
        var likedReviews = getLikedReviewsFromCookie();
        if (likedReviews.includes(reviewData.id) || reviewData.isLiked) {
            $likeButton.addClass('liked');
        } else {
            $likeButton.removeClass('liked');
        }
        
        // Show modal
        $overlay.addClass('active');
        $('body').css('overflow', 'hidden');
    }
    
    /**
     * Create review modal HTML
     */
    function createReviewModal() {
        var modalHtml = `
            <div id="sky-review-modal-overlay" class="sky-review-modal-overlay">
                <div id="sky-review-modal" class="sky-review-modal">
                    <div class="sky-modal-header">
                        <div class="sky-modal-author-info">
                            <img class="sky-modal-author-photo" src="" alt="">
                            <div class="sky-modal-author-photo-placeholder"></div>
                            <div class="sky-modal-author-details">
                                <div class="sky-modal-author-name"></div>
                                <div class="sky-modal-review-meta">
                                    <span class="sky-modal-rating"></span>
                                    <span class="sky-modal-time"></span>
                                </div>
                            </div>
                        </div>
                        <button class="sky-modal-close" type="button">
                            <svg width="24" height="24" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round">
                                <line x1="18" y1="6" x2="6" y2="18"></line>
                                <line x1="6" y1="6" x2="18" y2="18"></line>
                            </svg>
                        </button>
                    </div>
                    <div class="sky-modal-content">
                        <div class="sky-modal-review-text"></div>
                    </div>
                    <div class="sky-modal-footer">
                        <div class="sky-modal-platform-badge"></div>
                        <button class="sky-modal-like-button" type="button">
                            <span class="sky-like-icon">❤️</span>
                            <span class="sky-like-count">0</span>
                        </button>
                    </div>
                </div>
            </div>
        `;
        
        $('body').append(modalHtml);
    }
    
    /**
     * Initialize review modal events
     */
    function initializeReviewModal() {
        // Close modal on overlay click
        $(document).on('click', '#sky-review-modal-overlay', function(e) {
            if (e.target === this) {
                closeReviewModal();
            }
        });
        
        // Close modal on close button click
        $(document).on('click', '.sky-modal-close', function() {
            closeReviewModal();
        });
        
        // Close modal on ESC key
        $(document).on('keydown', function(e) {
            if (e.keyCode === 27 && $('#sky-review-modal-overlay').hasClass('active')) {
                closeReviewModal();
            }
        });
        
        // Handle custom modal open event
        $(document).on('sky-open-review-modal', function(e, reviewData) {
            openReviewModal(reviewData);
        });
    }
    
    /**
     * Close review modal
     */
    function closeReviewModal() {
        $('#sky-review-modal-overlay').removeClass('active');
        $('body').css('overflow', '');
    }
    
    /**
     * Initialize like system
     */
    function initializeLikeSystem() {
        // Get liked reviews from cookie on page load
        var likedReviews = getLikedReviewsFromCookie();
        
        // Apply liked state to buttons based on cookie
        if (likedReviews.length > 0) {
            likedReviews.forEach(function(reviewId) {
                $('.sky-review-like-button[data-review-id="' + reviewId + '"]').addClass('liked');
            });
        }
        
        // Handle like button clicks in review items
        $(document).on('click', '.sky-review-like-button', function(e) {
            e.preventDefault();
            var $button = $(this);
            var reviewId = $button.closest('.sky-review-item').data('review-id');
            handleLikeClick($button, reviewId);
        });
        
        // Handle like button clicks in modal
        $(document).on('click', '.sky-modal-like-button', function(e) {
            e.preventDefault();
            var $button = $(this);
            var reviewId = $button.data('review-id');
            handleLikeClick($button, reviewId);
            
            // Also update the corresponding review item
            var $reviewItem = $('.sky-review-item[data-review-id="' + reviewId + '"]');
            if ($reviewItem.length) {
                var $reviewLikeButton = $reviewItem.find('.sky-review-like-button');
                $reviewLikeButton.find('.sky-like-count').text($button.find('.sky-like-count').text());
                if ($button.hasClass('liked')) {
                    $reviewLikeButton.addClass('liked');
                } else {
                    $reviewLikeButton.removeClass('liked');
                }
            }
        });
    }
    
    /**
     * Get liked reviews from cookie
     */
    function getLikedReviewsFromCookie() {
        var cookieValue = getCookie('sky_liked_reviews');
        if (cookieValue) {
            try {
                return JSON.parse(cookieValue);
            } catch (e) {
                return [];
            }
        }
        return [];
    }
    
    /**
     * Get cookie value
     */
    function getCookie(name) {
        var nameEQ = name + "=";
        var ca = document.cookie.split(';');
        for(var i=0;i < ca.length;i++) {
            var c = ca[i];
            while (c.charAt(0)==' ') c = c.substring(1,c.length);
            if (c.indexOf(nameEQ) == 0) return c.substring(nameEQ.length,c.length);
        }
        return null;
    }
    
    /**
     * Handle like button click
     */
    function handleLikeClick($button, reviewId) {
        var isLiked = $button.hasClass('liked');
        var currentCount = parseInt($button.find('.sky-like-count').text()) || 0;
        
        if (isLiked) {
            // Unlike
            $button.removeClass('liked');
            currentCount = Math.max(0, currentCount - 1);
        } else {
            // Like
            $button.addClass('liked');
            currentCount++;
        }
        
        $button.find('.sky-like-count').text(currentCount);
        
        // Save to database via AJAX
        if (typeof sky_seo_ajax !== 'undefined') {
            $.ajax({
                url: sky_seo_ajax.ajax_url,
                type: 'POST',
                data: {
                    action: 'sky_seo_handle_review_like',
                    nonce: sky_seo_ajax.nonce,
                    review_id: reviewId,
                    is_liked: !isLiked
                },
                success: function(response) {
                    if (response.success) {
                        // Update count with server response
                        $button.find('.sky-like-count').text(response.data.likes);
                        
                        // Update all instances of this review
                        updateAllReviewLikes(reviewId, response.data.likes, !isLiked);
                        
                        // Update cookie data in JavaScript (server already set the cookie)
                        if (response.data.liked_reviews) {
                            // The cookie is already set by PHP, but we can use this for immediate updates
                        }
                    }
                },
                error: function() {
                    // Revert on error
                    if (isLiked) {
                        $button.addClass('liked');
                        $button.find('.sky-like-count').text(currentCount);
                    } else {
                        $button.removeClass('liked');
                        $button.find('.sky-like-count').text(currentCount);
                    }
                }
            });
        }
    }
    
    /**
     * Update all instances of a review with new like count
     */
    function updateAllReviewLikes(reviewId, likes, isLiked) {
        // Update in review items
        $('.sky-review-item[data-review-id="' + reviewId + '"]').each(function() {
            var $button = $(this).find('.sky-review-like-button');
            $button.find('.sky-like-count').text(likes);
            if (isLiked) {
                $button.addClass('liked');
            } else {
                $button.removeClass('liked');
            }
        });
        
        // Update in modal if open
        var $modalButton = $('.sky-modal-like-button[data-review-id="' + reviewId + '"]');
        if ($modalButton.length) {
            $modalButton.find('.sky-like-count').text(likes);
            if (isLiked) {
                $modalButton.addClass('liked');
            } else {
                $modalButton.removeClass('liked');
            }
        }
    }
    
    /**
     * Initialize load more functionality
     */
    function initializeLoadMore() {
        $(document).on('click', '.sky-load-more-btn', function(e) {
            e.preventDefault();
            
            var $button = $(this);
            var gridId = $button.data('grid-id');
            var $grid = $('#' + gridId);
            var $hiddenItems = $grid.find('.sky-review-wrapper.sky-review-hidden');
            var itemsToShow = 3; // Number of items to show per click
            
            // Disable button during loading
            $button.prop('disabled', true);
            
            // Show next batch of items
            $hiddenItems.slice(0, itemsToShow).each(function(index) {
                var $item = $(this);
                setTimeout(function() {
                    $item.removeClass('sky-review-hidden').addClass('fade-in');
                    
                    // Setup read more for newly loaded items
                    var $reviewText = $item.find('.sky-review-text');
                    var $readMoreBtn = $item.find('.sky-read-more-toggle');
                    var textContent = $reviewText.text().trim();
                    
                    if (textContent.length > 200) {
                        $readMoreBtn.show();
                        $reviewText.addClass('sky-truncated');
                        $reviewText.data('full-text', textContent);
                        $readMoreBtn.text('READ MORE');
                    }
                }, index * 100);
            });
            
            // Re-enable button
            setTimeout(function() {
                $button.prop('disabled', false);
                
                // Hide button if no more items
                if ($grid.find('.sky-review-wrapper.sky-review-hidden').length === 0) {
                    $button.fadeOut();
                }
            }, itemsToShow * 100);
        });
    }
    
    /**
     * Initialize status refresh
     */
    function initializeStatusRefresh() {
        function refreshBusinessStatus() {
            $('.sky-seo-business-status').each(function() {
                var $status = $(this);
                $status.addClass('loading');
                setTimeout(function() {
                    $status.removeClass('loading');
                }, 500);
            });
        }
        
        // Refresh every 60 seconds
        setInterval(refreshBusinessStatus, 60000);
    }
    
    /**
     * Initialize hours table interactions
     */
    function initializeHoursTable() {
        $('.sky-seo-hours-table').on('mouseenter', 'tr', function() {
            $(this).addClass('hover');
        }).on('mouseleave', 'tr', function() {
            $(this).removeClass('hover');
        });
    }
    
    // Reinitialize on Elementor editor refresh
    if (window.elementor) {
        elementor.hooks.addAction('panel/open_editor/widget/sky_seo_reviews', function(panel, model, view) {
            setTimeout(function() {
                initializeReviewSliders();
                initializeReadMore();
                setupReadMoreButtons();
                initializeLoadMore();
                initializeReviewModal();
                initializeLikeSystem();
            }, 300);
        });
        
        elementor.hooks.addAction('panel/open_editor/widget/sky_seo_business_info', function(panel, model, view) {
            setTimeout(function() {
                initializeStatusRefresh();
                initializeHoursTable();
            }, 100);
        });
    }
    
    // Handle window resize
    var resizeTimer;
    $(window).on('resize', function() {
        clearTimeout(resizeTimer);
        resizeTimer = setTimeout(function() {
            $('.sky-seo-reviews-slider').each(function() {
                var swiperEl = $(this).find('.swiper').length ? $(this).find('.swiper')[0] : this;
                // Reset standard height on resize
                $(this).removeData('standard-height');
                equalizeReviewHeights(swiperEl);
                
                // Update swiper instance
                var swiper = $(this).data('swiper-instance');
                if (swiper) {
                    swiper.update();
                }
            });
        }, 250);
    });
    
    // Handle images loading
    $(window).on('load', function() {
        setTimeout(function() {
            $('.sky-seo-reviews-slider').each(function() {
                var swiperEl = $(this).find('.swiper').length ? $(this).find('.swiper')[0] : this;
                equalizeReviewHeights(swiperEl);
            });
            
            setupReadMoreButtons();
        }, 200);
    });
    
})(jQuery);