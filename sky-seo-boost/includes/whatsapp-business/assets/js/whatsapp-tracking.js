/**
 * Sky SEO Boost - WhatsApp Business Tracking JavaScript
 * 
 * @package Sky_SEO_Boost
 * @subpackage WhatsApp_Business
 * @version 1.2.5
 */

(function($) {
    'use strict';

    /**
     * WhatsApp Tracking Dashboard
     */
    const SkySeoWhatsAppTracking = {
        
        // Properties
        config: {},
        currentFilters: {
            search: '',
            dateRange: 'thismonth',
            customStartDate: '',
            customEndDate: '',
            source: 'all',
            device: 'all',
            country: 'all'
        },
        currentPage: 1,
        itemsPerPage: 20,
        sortColumn: 'last_click',
        sortOrder: 'desc',
        refreshTimer: null,
        currentModalTab: 'overview',
        
        /**
         * Initialize
         */
        init: function() {
            // Get localized data
            this.config = window.skySeoWhatsAppTracking || {};
            
            // Ensure modal HTML exists
            if ($('#sky-whatsapp-details-modal').length === 0) {
                // Modal HTML not found - will be handled gracefully
            }
            
            // Initialize components
            this.initializeDatePicker();
            this.initializeCustomDateRange();
            this.loadDashboardData();
            this.bindEvents();
            
            // Set default active button to "This Month"
            $('.sky-seo-period-selector button').removeClass('active');
            $('.sky-seo-period-selector button[data-period="thismonth"]').addClass('active');
            
            // Start auto-refresh
            this.startAutoRefresh();
        },
        
        /**
         * Bind events
         */
        bindEvents: function() {
            // Period selectors
            $('.sky-seo-period-selector button').on('click', this.handlePeriodChange.bind(this));
            
            // Search
            $('#conversation-search').on('input', this.debounce(this.handleSearch.bind(this), 300));
            
            // Filters
            $('.sky-seo-filter-dropdown').on('change', this.handleFilterChange.bind(this));
            
            // Sorting
            $('.sky-seo-conversations-table th.sortable').on('click', this.handleSort.bind(this));
            
            // Pagination
            $(document).on('click', '.sky-seo-pagination-button', this.handlePagination.bind(this));
            
            // Export
            $('.sky-seo-export-button').on('click', this.handleExport.bind(this));
            
            // Heatmap hover
            $(document).on('mouseenter', '.sky-seo-heatmap-cell', this.showHeatmapTooltip.bind(this));
            $(document).on('mouseleave', '.sky-seo-heatmap-cell', this.hideHeatmapTooltip.bind(this));
            
            // View details - Updated for better event handling
            $(document).off('click', '.sky-seo-conversation-action').on('click', '.sky-seo-conversation-action', this.viewPageDetails.bind(this));
            
            // Modal events
            $(document).on('click', '.sky-modal-close', this.closeModal.bind(this));
            $(document).on('click', '.sky-modal', function(e) {
                if ($(e.target).hasClass('sky-modal')) {
                    SkySeoWhatsAppTracking.closeModal();
                }
            });
            $(document).on('click', '.sky-modal-tab', this.switchModalTab.bind(this));
            
            // ESC key to close modal
            $(document).on('keydown', function(e) {
                if (e.key === 'Escape' && $('#sky-whatsapp-details-modal').is(':visible')) {
                    SkySeoWhatsAppTracking.closeModal();
                }
            });
            
            // Cleanup on window unload
            $(window).on('beforeunload', this.cleanup.bind(this));
        },
        
        /**
         * Initialize date picker
         */
        initializeDatePicker: function() {
            if ($.fn.datepicker) {
                $('.sky-seo-date-input').datepicker({
                    dateFormat: 'yy-mm-dd',
                    maxDate: new Date(),
                    onSelect: () => {
                        this.loadDashboardData();
                    }
                });
            }
        },
        
        /**
         * Initialize custom date range
         */
        initializeCustomDateRange: function() {
            // Create custom date range HTML
            const customDateHtml = `
                <div class="sky-seo-custom-date-range" style="display: none;">
                    <input type="text" class="sky-seo-date-input sky-seo-start-date" placeholder="Start Date">
                    <span class="sky-seo-date-separator">to</span>
                    <input type="text" class="sky-seo-date-input sky-seo-end-date" placeholder="End Date">
                    <button type="button" class="button button-small sky-seo-apply-date">Apply</button>
                </div>
            `;
            
            // Insert after period selector
            $('.sky-seo-period-selector').after(customDateHtml);
            
            // Initialize datepickers
            $('.sky-seo-start-date, .sky-seo-end-date').datepicker({
                dateFormat: 'yy-mm-dd',
                maxDate: new Date(),
                onSelect: (dateText, inst) => {
                    // Update min/max dates
                    if ($(inst.input).hasClass('sky-seo-start-date')) {
                        $('.sky-seo-end-date').datepicker('option', 'minDate', dateText);
                    } else {
                        $('.sky-seo-start-date').datepicker('option', 'maxDate', dateText);
                    }
                }
            });
            
            // Apply button handler
            $('.sky-seo-apply-date').on('click', () => {
                const startDate = $('.sky-seo-start-date').val();
                const endDate = $('.sky-seo-end-date').val();
                
                if (startDate && endDate) {
                    this.currentFilters.customStartDate = startDate;
                    this.currentFilters.customEndDate = endDate;
                    this.currentFilters.dateRange = 'custom';
                    this.loadDashboardData();
                }
            });
        },
        
        /**
         * Handle period change
         */
        handlePeriodChange: function(e) {
            const $button = $(e.target);
            const period = $button.data('period');
            
            // Update active state
            $button.siblings().removeClass('active');
            $button.addClass('active');
            
            // Show/hide custom date range
            if (period === 'custom') {
                $('.sky-seo-custom-date-range').slideDown(200);
                // Set default dates to this month
                const now = new Date();
                const firstDay = new Date(now.getFullYear(), now.getMonth(), 1);
                const lastDay = new Date(now.getFullYear(), now.getMonth() + 1, 0);
                
                $('.sky-seo-start-date').datepicker('setDate', firstDay);
                $('.sky-seo-end-date').datepicker('setDate', lastDay);
            } else {
                $('.sky-seo-custom-date-range').slideUp(200);
                // Update filter
                this.currentFilters.dateRange = period;
                this.currentPage = 1;
                
                // Reload data
                this.loadDashboardData();
            }
        },
        
        /**
         * Load dashboard data
         */
        loadDashboardData: function() {
            // Show loading state
            this.showLoading();
            
            // Prepare request data
            const data = {
                action: 'sky_seo_whatsapp_tracking_data',
                nonce: this.config.nonce,
                filters: this.currentFilters,
                page: this.currentPage,
                per_page: this.itemsPerPage,
                sort_column: this.sortColumn,
                sort_order: this.sortOrder
            };
            
            // AJAX request
            $.ajax({
                url: this.config.ajaxurl,
                type: 'POST',
                data: data,
                success: this.handleDataSuccess.bind(this),
                error: this.handleDataError.bind(this),
                complete: this.hideLoading.bind(this)
            });
        },
        
        /**
         * Handle data success
         */
        handleDataSuccess: function(response) {
            if (response.success) {
                const data = response.data;
                
                // Update metrics
                this.updateMetrics(data.metrics);
                
                // Update heatmap
                this.updateHeatmap(data.heatmap);
                
                // Update pages table
                this.updatePagesTable(data.conversations);
                
                // Update pagination
                this.updatePagination(data.pagination);
                
                // Update country filter options
                this.updateCountryFilter(data.conversations);
                
            } else {
                this.showError(response.data?.message || 'Failed to load data');
            }
        },
        
        /**
         * Handle data error
         */
        handleDataError: function() {
            this.showError(this.config.strings?.loadError || 'Failed to load tracking data');
        },
        
        /**
         * Update metrics - UPDATED FOR NEW METRICS
         */
        updateMetrics: function(metrics) {
            // Total clicks
            this.animateMetricValue('.total-clicks .sky-seo-metric-value', metrics.total_clicks);
            this.updateMetricChange('.total-clicks .sky-seo-metric-change', metrics.clicks_change);
            
            // Unique users
            this.animateMetricValue('.unique-users .sky-seo-metric-value', metrics.unique_users);
            this.updateMetricChange('.unique-users .sky-seo-metric-change', metrics.users_change);
            
            // Desktop clicks
            this.animateMetricValue('.desktop-clicks .sky-seo-metric-value', metrics.desktop_clicks);
            $('.desktop-clicks .sky-seo-metric-percentage').text(metrics.desktop_percentage + '%');
            
            // Mobile clicks
            this.animateMetricValue('.mobile-clicks .sky-seo-metric-value', metrics.mobile_clicks);
            $('.mobile-clicks .sky-seo-metric-percentage').text(metrics.mobile_percentage + '%');
            
            // Widget clicks
            this.animateMetricValue('.widget-clicks .sky-seo-metric-value', metrics.widget_clicks);
            $('.widget-clicks .sky-seo-metric-percentage').text(metrics.widget_percentage + '%');
            
            // Button clicks
            this.animateMetricValue('.button-clicks .sky-seo-metric-value', metrics.button_clicks);
            $('.button-clicks .sky-seo-metric-percentage').text(metrics.button_percentage + '%');
        },
        
        /**
         * Animate metric value
         */
        animateMetricValue: function(selector, newValue) {
            const $element = $(selector);
            const currentValue = parseInt($element.text().replace(/[^0-9]/g, '')) || 0;
            const targetValue = parseInt(newValue.toString().replace(/[^0-9]/g, '')) || 0;
            
            if (currentValue === targetValue) return;
            
            $({ value: currentValue }).animate({ value: targetValue }, {
                duration: 1000,
                easing: 'swing',
                step: function(now) {
                    $element.text(Math.ceil(now).toLocaleString());
                },
                complete: function() {
                    $element.text(targetValue.toLocaleString());
                }
            });
        },
        
        /**
         * Update metric change
         */
        updateMetricChange: function(selector, change) {
            const $element = $(selector);
            const isPositive = change >= 0;
            
            $element
                .removeClass('positive negative')
                .addClass(isPositive ? 'positive' : 'negative')
                .html(`
                    <span class="dashicons dashicons-arrow-${isPositive ? 'up' : 'down'}-alt"></span>
                    <span>${Math.abs(change)}%</span>
                `);
        },
        
        /**
         * Update heatmap
         */
        updateHeatmap: function(heatmapData) {
            const $grid = $('.sky-seo-heatmap-grid');
            $grid.empty();
            
            // Hour labels - simplified like the design
            const hourLabels = [];
            for (let hour = 0; hour < 24; hour++) {
                if (hour === 0) hourLabels.push('12AM');
                else if (hour < 12) hourLabels.push(hour + 'AM');
                else if (hour === 12) hourLabels.push('12PM');
                else hourLabels.push((hour - 12) + 'PM');
            }
            
            // Add empty corner cell
            $grid.append('<div class="sky-seo-heatmap-label"></div>');
            
            // Add hour labels - only show every 3 hours like in the design
            for (let hour = 0; hour < 24; hour++) {
                const label = (hour % 3 === 0) ? hourLabels[hour] : '';
                $grid.append(`<div class="sky-seo-heatmap-label">${label}</div>`);
            }
            
            // Days starting with Monday
            const days = ['Mon', 'Tue', 'Wed', 'Thu', 'Fri', 'Sat', 'Sun'];
            
            // Calculate max value for consistent scaling
            let maxValue = 0;
            Object.values(heatmapData).forEach(dayData => {
                if (dayData) {
                    Object.values(dayData).forEach(count => {
                        maxValue = Math.max(maxValue, count);
                    });
                }
            });
            
            // Create rows for each day
            days.forEach((day, dayIndex) => {
                // Day label
                $grid.append(`<div class="sky-seo-heatmap-label">${day}</div>`);
                
                // Hour cells
                for (let hour = 0; hour < 24; hour++) {
                    const value = heatmapData[dayIndex]?.[hour] || 0;
                    const intensity = this.getHeatmapIntensity(value, maxValue);
                    
                    // Show the number inside cells with small font
                    const displayValue = value > 0 ? value : '';
                    
                    $grid.append(`
                        <div class="sky-seo-heatmap-cell ${intensity}" 
                             data-day="${day}" 
                             data-hour="${hour}" 
                             data-value="${value}"
                             title="${value} clicks on ${day} at ${hourLabels[hour]}">
                            ${displayValue}
                        </div>
                    `);
                }
            });
            
            // Update legend to show "Less" and "More"
            $('.sky-seo-heatmap-legend').html(`
                <span class="sky-seo-legend-item">
                    <span style="color: #9ca3af; font-size: 12px;">Less</span>
                </span>
                <span class="sky-seo-legend-item">
                    <span class="sky-seo-legend-color" style="background: #dbeafe;"></span>
                </span>
                <span class="sky-seo-legend-item">
                    <span class="sky-seo-legend-color" style="background: #93c5fd;"></span>
                </span>
                <span class="sky-seo-legend-item">
                    <span class="sky-seo-legend-color" style="background: #60a5fa;"></span>
                </span>
                <span class="sky-seo-legend-item">
                    <span class="sky-seo-legend-color" style="background: #3b82f6;"></span>
                </span>
                <span class="sky-seo-legend-item">
                    <span style="color: #6b7280; font-size: 12px;">More</span>
                </span>
            `);
        },
        
        /**
         * Get heatmap intensity
         */
        getHeatmapIntensity: function(value, maxValue) {
            if (value === 0 || maxValue === 0) return 'empty';
            
            const percentage = (value / maxValue) * 100;
            
            if (percentage <= 20) return 'low';
            if (percentage <= 40) return 'medium-low';
            if (percentage <= 60) return 'medium';
            if (percentage <= 80) return 'medium-high';
            return 'high';
        },
        
        /**
         * Update pages table - NEW METHOD
         */
        updatePagesTable: function(pages) {
            const $tbody = $('.sky-seo-conversations-table tbody');
            
            if (!pages || pages.length === 0) {
                $tbody.html(`
                    <tr>
                        <td colspan="7" class="sky-seo-empty-state">
                            <div class="sky-seo-empty-icon">
                                <span class="dashicons dashicons-format-chat"></span>
                            </div>
                            <h3 class="sky-seo-empty-title">No page data found</h3>
                            <p class="sky-seo-empty-text">Page performance data will appear here when users interact with your WhatsApp widget</p>
                        </td>
                    </tr>
                `);
                return;
            }
            
            // Build table rows
            const rows = pages.map(page => this.buildPageRow(page)).join('');
            $tbody.html(rows);
        },
        
        /**
         * Build page row - UPDATED WITH CLICKABLE TITLE
         */
        buildPageRow: function(page) {
            // Ensure all values are defined with defaults
            const totalClicks = page.total_clicks || 0;
            const widgetClicks = page.widget_clicks || 0;
            const buttonClicks = page.button_clicks || 0;
            const topCity = page.top_city || 'Unknown';
            const lastActivity = page.last_click_formatted || 'Never';
            const pageTitle = page.page_title || 'Untitled Page';
            const pageUrl = page.page_url || '#';
            
            // Escape the page URL for the data attribute
            const escapedUrl = $('<div>').text(pageUrl).html();
            const escapedTitle = $('<div>').text(pageTitle).html();
            
            // Create clickable title with external link icon
            const clickableTitle = pageUrl && pageUrl !== '#' 
                ? `<a href="${escapedUrl}" target="_blank" style="text-decoration: none; color: inherit;">
                    ${escapedTitle}
                    <span class="dashicons dashicons-external" style="font-size: 14px; vertical-align: middle; margin-left: 5px; opacity: 0.6;"></span>
                   </a>`
                : escapedTitle;
            
            return `
                <tr>
                    <td>${clickableTitle}</td>
                    <td><span class="sky-click-count total">${totalClicks}</span></td>
                    <td><span class="sky-click-count widget">${widgetClicks}</span></td>
                    <td><span class="sky-click-count button">${buttonClicks}</span></td>
                    <td><span class="sky-top-city">${topCity}</span></td>
                    <td>${lastActivity}</td>
                    <td>
                        <a href="#" class="sky-seo-conversation-action" data-page-url="${escapedUrl}" data-page-title="${pageTitle}">
                            <span class="dashicons dashicons-visibility"></span>
                            View Details
                        </a>
                    </td>
                </tr>
            `;
        },
        
        /**
         * View page details - FIXED METHOD
         */
        viewPageDetails: function(e) {
            e.preventDefault();
            e.stopPropagation();
            
            const $button = $(e.currentTarget);
            const pageUrl = $button.attr('data-page-url');
            const pageTitle = $button.attr('data-page-title');

            // Show modal with loading state
            this.showModal();
            
            // Update modal title immediately
            $('.sky-modal-title').text(pageTitle + ' - Details');
            
            // Load page details via AJAX
            $.ajax({
                url: this.config.ajaxurl,
                type: 'POST',
                data: {
                    action: 'sky_seo_whatsapp_page_details',
                    nonce: this.config.nonce,
                    page_url: pageUrl,
                    page_title: pageTitle  // Send page title too
                },
                success: (response) => {
                    if (response.success) {
                        this.populateModal(response.data);
                    } else {
                        this.showError(response.data?.message || 'Failed to load details');
                        this.closeModal();
                    }
                },
                error: (xhr, status, error) => {
                    this.showError('Failed to load page details');
                    this.closeModal();
                }
            });
        },
        
        /**
         * Show modal
         */
        showModal: function() {
            const $modal = $('#sky-whatsapp-details-modal');
            $modal.show();
            
            // Show loading state
            $('.sky-modal-body').html(`
                <div class="sky-modal-loading">
                    <div class="sky-modal-spinner"></div>
                </div>
            `);
            
            // Prevent body scroll
            $('body').css('overflow', 'hidden');
        },
        
        /**
         * Close modal
         */
        closeModal: function() {
            $('#sky-whatsapp-details-modal').fadeOut(200);
            $('body').css('overflow', '');
            this.currentModalTab = 'overview';
        },
        
        /**
         * Populate modal with data
         */
        populateModal: function(data) {
            const { summary, locations, recent_clicks, hourly } = data;
            
            // Update modal title
            $('.sky-modal-title').text(summary.page_title || 'Page Details');
            
            // Build modal content
            const modalContent = `
                <div class="sky-modal-tabs">
                    <div class="sky-modal-tab active" data-tab="overview">Overview</div>
                    <div class="sky-modal-tab" data-tab="geographic">Geographic</div>
                    <div class="sky-modal-tab" data-tab="activity">Recent Activity</div>
                    <div class="sky-modal-tab" data-tab="time">Time Analysis</div>
                </div>
                
                <!-- Overview Tab -->
                <div class="sky-tab-content active" id="overview-tab">
                    <div class="sky-page-summary-stats">
                        <div class="sky-summary-stat">
                            <div class="sky-summary-stat-value">${summary.total_clicks}</div>
                            <div class="sky-summary-stat-label">Total Clicks</div>
                        </div>
                        <div class="sky-summary-stat">
                            <div class="sky-summary-stat-value">${summary.unique_users}</div>
                            <div class="sky-summary-stat-label">Unique Users</div>
                        </div>
                        <div class="sky-summary-stat">
                            <div class="sky-summary-stat-value">${summary.desktop_clicks}</div>
                            <div class="sky-summary-stat-label">Desktop</div>
                        </div>
                        <div class="sky-summary-stat">
                            <div class="sky-summary-stat-value">${summary.mobile_clicks}</div>
                            <div class="sky-summary-stat-label">Mobile</div>
                        </div>
                    </div>
                    
                    <div class="sky-modal-section">
                        <h4 class="sky-modal-section-title">Click Distribution</h4>
                        ${this.buildDistributionBars(summary)}
                    </div>
                </div>
                
                <!-- Geographic Tab -->
                <div class="sky-tab-content" id="geographic-tab">
                    <div class="sky-modal-section">
                        <h4 class="sky-modal-section-title">Top Locations</h4>
                        <div class="sky-locations-list">
                            ${this.buildLocationsList(locations)}
                        </div>
                    </div>
                </div>
                
                <!-- Recent Activity Tab -->
                <div class="sky-tab-content" id="activity-tab">
                    <div class="sky-modal-section">
                        <h4 class="sky-modal-section-title">Recent Clicks</h4>
                        <table class="sky-recent-clicks-table">
                            <thead>
                                <tr>
                                    <th>Time</th>
                                    <th>Device</th>
                                    <th>Location</th>
                                    <th>Source</th>
                                    <th>Browser/OS</th>
                                </tr>
                            </thead>
                            <tbody>
                                ${this.buildRecentClicksRows(recent_clicks)}
                            </tbody>
                        </table>
                    </div>
                </div>
                
                <!-- Time Analysis Tab -->
                <div class="sky-tab-content" id="time-tab">
                    <div class="sky-modal-section">
                        <h4 class="sky-modal-section-title">Hourly Activity</h4>
                        ${this.buildHourlyChart(hourly)}
                    </div>
                </div>
            `;
            
            $('.sky-modal-body').html(modalContent);
        },
        
        /**
         * Build distribution bars
         */
        buildDistributionBars: function(summary) {
            const total = parseInt(summary.total_clicks) || 1;
            const widgetPercent = Math.round((summary.widget_clicks / total) * 100);
            const buttonPercent = Math.round((summary.button_clicks / total) * 100);
            const desktopPercent = Math.round((summary.desktop_clicks / total) * 100);
            const mobilePercent = Math.round((summary.mobile_clicks / total) * 100);
            
            return `
                <div class="sky-distribution-bar">
                    <div class="sky-distribution-label">Widget</div>
                    <div class="sky-distribution-progress">
                        <div class="sky-distribution-fill widget" style="width: ${widgetPercent}%">
                            <span class="sky-distribution-percent">${widgetPercent}%</span>
                        </div>
                    </div>
                </div>
                <div class="sky-distribution-bar">
                    <div class="sky-distribution-label">Button</div>
                    <div class="sky-distribution-progress">
                        <div class="sky-distribution-fill button" style="width: ${buttonPercent}%">
                            <span class="sky-distribution-percent">${buttonPercent}%</span>
                        </div>
                    </div>
                </div>
                <div class="sky-distribution-bar">
                    <div class="sky-distribution-label">Desktop</div>
                    <div class="sky-distribution-progress">
                        <div class="sky-distribution-fill desktop" style="width: ${desktopPercent}%">
                            <span class="sky-distribution-percent">${desktopPercent}%</span>
                        </div>
                    </div>
                </div>
                <div class="sky-distribution-bar">
                    <div class="sky-distribution-label">Mobile</div>
                    <div class="sky-distribution-progress">
                        <div class="sky-distribution-fill mobile" style="width: ${mobilePercent}%">
                            <span class="sky-distribution-percent">${mobilePercent}%</span>
                        </div>
                    </div>
                </div>
            `;
        },
        
        /**
         * Build locations list
         */
        buildLocationsList: function(locations) {
            if (!locations || locations.length === 0) {
                return '<p>No location data available</p>';
            }
            
            return locations.map(loc => `
                <div class="sky-location-item">
                    <div class="sky-location-info">
                        <span class="sky-location-flag">${this.getCountryFlag(loc.country)}</span>
                        <span class="sky-location-name">${loc.city}, ${loc.country}</span>
                    </div>
                    <span class="sky-location-clicks">${loc.clicks} clicks</span>
                </div>
            `).join('');
        },
        
        /**
         * Build recent clicks rows
         */
        buildRecentClicksRows: function(clicks) {
            if (!clicks || clicks.length === 0) {
                return '<tr><td colspan="5">No recent activity</td></tr>';
            }
            
            return clicks.map(click => `
                <tr>
                    <td>${click.time_formatted}</td>
                    <td>${click.device_type}</td>
                    <td>${click.location}</td>
                    <td>${click.source_formatted}</td>
                    <td>${click.browser} / ${click.os}</td>
                </tr>
            `).join('');
        },
        
        /**
         * Build hourly chart
         */
        buildHourlyChart: function(hourlyData) {
            if (!hourlyData || hourlyData.length === 0) {
                return '<p>No hourly data available</p>';
            }
            
            // Find max value for scaling
            const maxClicks = Math.max(...hourlyData.map(h => parseInt(h.clicks)));
            
            // Create hourly array with all 24 hours
            const hours = [];
            for (let i = 0; i < 24; i++) {
                const hourData = hourlyData.find(h => parseInt(h.hour) === i);
                hours.push({
                    hour: i,
                    clicks: hourData ? parseInt(hourData.clicks) : 0
                });
            }
            
            const bars = hours.map(h => {
                const height = maxClicks > 0 ? (h.clicks / maxClicks) * 100 : 0;
                return `<div class="sky-hourly-bar" style="height: ${height}%" data-clicks="${h.clicks}"></div>`;
            }).join('');
            
            const labels = hours.filter((h, i) => i % 6 === 0).map(h => {
                const label = h.hour === 0 ? '12am' : (h.hour < 12 ? `${h.hour}am` : (h.hour === 12 ? '12pm' : `${h.hour - 12}pm`));
                return `<div class="sky-hourly-label">${label}</div>`;
            }).join('');
            
            return `
                <div class="sky-hourly-chart">${bars}</div>
                <div class="sky-hourly-labels">${labels}</div>
            `;
        },
        
        /**
         * Switch modal tab
         */
        switchModalTab: function(e) {
            const $tab = $(e.currentTarget);
            const tabId = $tab.data('tab');
            
            // Update active states
            $('.sky-modal-tab').removeClass('active');
            $tab.addClass('active');
            
            $('.sky-tab-content').removeClass('active');
            $(`#${tabId}-tab`).addClass('active');
        },
        
        /**
         * Get country flag
         */
        getCountryFlag: function(country) {
            const flags = {
                'United States': '🇺🇸',
                'United Kingdom': '🇬🇧',
                'Canada': '🇨🇦',
                'Australia': '🇦🇺',
                'Germany': '🇩🇪',
                'France': '🇫🇷',
                'Spain': '🇪🇸',
                'Italy': '🇮🇹',
                'Netherlands': '🇳🇱',
                'Brazil': '🇧🇷',
                'India': '🇮🇳',
                'China': '🇨🇳',
                'Japan': '🇯🇵',
                'South Korea': '🇰🇷',
                'Mexico': '🇲🇽',
                'Russia': '🇷🇺',
                'South Africa': '🇿🇦',
                'Egypt': '🇪🇬',
                'Nigeria': '🇳🇬',
                'Kenya': '🇰🇪',
            };
            
            return flags[country] || '🌍';
        },
        
        /**
         * Update pagination
         */
        updatePagination: function(pagination) {
            const { current_page, total_pages, total_items, per_page } = pagination;
            
            // Update info
            const start = total_items > 0 ? (current_page - 1) * per_page + 1 : 0;
            const end = Math.min(current_page * per_page, total_items);
            
            $('.sky-seo-pagination-info').text(
                total_items > 0 
                    ? `Showing ${start} to ${end} of ${total_items} pages`
                    : 'No pages to show'
            );
            
            // Build pagination controls
            let buttons = '';
            
            if (total_pages > 1) {
                // Previous button
                buttons += `
                    <button class="sky-seo-pagination-button" data-page="${current_page - 1}" ${current_page === 1 ? 'disabled' : ''}>
                        Previous
                    </button>
                `;
                
                // Page numbers
                const maxButtons = 5;
                let startPage = Math.max(1, current_page - Math.floor(maxButtons / 2));
                let endPage = Math.min(total_pages, startPage + maxButtons - 1);
                
                if (endPage - startPage < maxButtons - 1) {
                    startPage = Math.max(1, endPage - maxButtons + 1);
                }
                
                // First page
                if (startPage > 1) {
                    buttons += `<button class="sky-seo-pagination-button" data-page="1">1</button>`;
                    if (startPage > 2) {
                        buttons += `<span class="sky-seo-pagination-dots">...</span>`;
                    }
                }
                
                // Page range
                for (let i = startPage; i <= endPage; i++) {
                    buttons += `
                        <button class="sky-seo-pagination-button ${i === current_page ? 'active' : ''}" data-page="${i}">
                            ${i}
                        </button>
                    `;
                }
                
                // Last page
                if (endPage < total_pages) {
                    if (endPage < total_pages - 1) {
                        buttons += `<span class="sky-seo-pagination-dots">...</span>`;
                    }
                    buttons += `<button class="sky-seo-pagination-button" data-page="${total_pages}">${total_pages}</button>`;
                }
                
                // Next button
                buttons += `
                    <button class="sky-seo-pagination-button" data-page="${current_page + 1}" ${current_page === total_pages ? 'disabled' : ''}>
                        Next
                    </button>
                `;
            }
            
            $('.sky-seo-pagination-controls').html(buttons);
        },
        
        /**
         * Update country filter
         */
        updateCountryFilter: function(pages) {
            const $countryFilter = $('select[data-filter="country"]');
            const currentValue = $countryFilter.val();
            
            // Get unique countries from all cities
            const countries = new Set();
            pages.forEach(page => {
                if (page.cities) {
                    // Extract country from top_city format "City (count)"
                    const match = page.top_city.match(/^(.+)\s\(\d+\)$/);
                    if (match && match[1] !== 'Unknown') {
                        // This is simplified - in real implementation you'd need full country data
                        countries.add('All Countries');
                    }
                }
            });
            
            // For now, keep existing filter as is
        },
        
        /**
         * Handle search
         */
        handleSearch: function(e) {
            this.currentFilters.search = $(e.target).val();
            this.currentPage = 1;
            this.loadDashboardData();
        },
        
        /**
         * Handle filter change
         */
        handleFilterChange: function(e) {
            const $select = $(e.target);
            const filterType = $select.data('filter');
            const value = $select.val();
            
            this.currentFilters[filterType] = value;
            this.currentPage = 1;
            this.loadDashboardData();
        },
        
        /**
         * Handle sort
         */
        handleSort: function(e) {
            const $th = $(e.target).closest('th');
            const column = $th.data('sort');
            
            if (this.sortColumn === column) {
                this.sortOrder = this.sortOrder === 'asc' ? 'desc' : 'asc';
            } else {
                this.sortColumn = column;
                this.sortOrder = 'desc';
            }
            
            // Update UI
            $th.siblings().removeClass('asc desc');
            $th.removeClass('asc desc').addClass(this.sortOrder);
            
            // Reload data
            this.loadDashboardData();
        },
        
        /**
         * Handle pagination
         */
        handlePagination: function(e) {
            const $button = $(e.target).closest('.sky-seo-pagination-button');
            if ($button.prop('disabled')) return;
            
            this.currentPage = parseInt($button.data('page'));
            this.loadDashboardData();
            
            // Scroll to table top
            $('html, body').animate({
                scrollTop: $('.sky-seo-conversations-section').offset().top - 100
            }, 300);
        },
        
        /**
         * Handle export
         */
        handleExport: function(e) {
            e.preventDefault();
            
            const $button = $(e.target).closest('.sky-seo-export-button');
            const format = $button.data('format');
            const originalText = $button.html();
            
            // Show loading
            $button.prop('disabled', true).html('<span class="dashicons dashicons-update spinning"></span> Exporting...');
            
            // Prepare data
            const data = {
                action: 'sky_seo_whatsapp_export',
                nonce: this.config.nonce,
                format: format,
                filters: this.currentFilters
            };
            
            // Create form and submit
            const $form = $('<form>', {
                method: 'POST',
                action: this.config.ajaxurl
            });
            
            $.each(data, (key, value) => {
                if (typeof value === 'object') {
                    $.each(value, (subKey, subValue) => {
                        $form.append($('<input>', {
                            type: 'hidden',
                            name: `${key}[${subKey}]`,
                            value: subValue
                        }));
                    });
                } else {
                    $form.append($('<input>', {
                        type: 'hidden',
                        name: key,
                        value: value
                    }));
                }
            });
            
            $form.appendTo('body').submit().remove();
            
            // Reset button
            setTimeout(() => {
                $button.prop('disabled', false).html(originalText);
            }, 2000);
        },
        
        /**
         * Show heatmap tooltip
         */
        showHeatmapTooltip: function(e) {
            const $cell = $(e.target);
            const day = $cell.data('day');
            const hour = $cell.data('hour');
            const value = $cell.data('value');
            
            if (!value) return;
            
            const hourStr = this.formatHour(hour);
            const tooltip = `${value} clicks on ${day} at ${hourStr}`;
            
            // Create tooltip
            const $tooltip = $('<div class="sky-seo-tooltip"></div>').text(tooltip);
            
            // Position tooltip
            const offset = $cell.offset();
            const cellWidth = $cell.outerWidth();
            const cellHeight = $cell.outerHeight();
            
            $tooltip.css({
                top: offset.top - 40,
                left: offset.left + (cellWidth / 2) - ($tooltip.outerWidth() / 2)
            });
            
            $('body').append($tooltip);
            
            setTimeout(() => {
                $tooltip.addClass('show');
            }, 10);
        },
        
        /**
         * Hide heatmap tooltip
         */
        hideHeatmapTooltip: function() {
            $('.sky-seo-tooltip').removeClass('show');
            setTimeout(() => {
                $('.sky-seo-tooltip').remove();
            }, 200);
        },
        
        /**
         * Format hour
         */
        formatHour: function(hour) {
            if (hour === 0) return '12:00 AM';
            if (hour < 12) return `${hour}:00 AM`;
            if (hour === 12) return '12:00 PM';
            return `${hour - 12}:00 PM`;
        },
        
        /**
         * Start auto-refresh
         */
        startAutoRefresh: function() {
            // Clear existing timer
            if (this.refreshTimer) {
                clearInterval(this.refreshTimer);
            }
            
            // Refresh every 60 seconds
            this.refreshTimer = setInterval(() => {
                if (!document.hidden) {
                    this.loadDashboardData();
                }
            }, 60000);
        },
        
        /**
         * Show loading state
         */
        showLoading: function() {
            // Add loading overlay to main containers
            const containers = [
                '.sky-seo-metric-card',
                '.sky-seo-heatmap-section',
                '.sky-seo-conversations-section'
            ];
            
            containers.forEach(selector => {
                $(selector).each(function() {
                    if (!$(this).find('.sky-seo-loading-overlay').length) {
                        $(this).css('position', 'relative').append(`
                            <div class="sky-seo-loading-overlay">
                                <div class="sky-seo-loading-spinner"></div>
                            </div>
                        `);
                    }
                });
            });
        },
        
        /**
         * Hide loading state
         */
        hideLoading: function() {
            $('.sky-seo-loading-overlay').fadeOut(300, function() {
                $(this).remove();
            });
        },
        
        /**
         * Show error
         */
        showError: function(message) {
            const $notice = $(`
                <div class="notice notice-error is-dismissible">
                    <p>${message}</p>
                    <button type="button" class="notice-dismiss">
                        <span class="screen-reader-text">Dismiss this notice.</span>
                    </button>
                </div>
            `);
            
            $('.sky-seo-dashboard-header').after($notice);
            
            // Handle dismiss
            $notice.on('click', '.notice-dismiss', function() {
                $notice.fadeOut(() => $notice.remove());
            });
            
            setTimeout(() => {
                $notice.fadeOut(() => $notice.remove());
            }, 5000);
        },
        
        /**
         * Cleanup method
         */
        cleanup: function() {
            if (this.refreshTimer) {
                clearInterval(this.refreshTimer);
                this.refreshTimer = null;
            }
            
            // Remove event listeners
            $(document).off('click', '.sky-seo-conversation-action');
            $(document).off('click', '.sky-modal-close');
            $(document).off('click', '.sky-modal-tab');
            
            // Clear any tooltips
            $('.sky-seo-tooltip').remove();
        },
        
        /**
         * Debounce function
         */
        debounce: function(func, wait) {
            let timeout;
            return function executedFunction(...args) {
                const later = () => {
                    clearTimeout(timeout);
                    func(...args);
                };
                clearTimeout(timeout);
                timeout = setTimeout(later, wait);
            };
        }
    };
    
    // Initialize on document ready
    $(document).ready(function() {
        if ($('.sky-seo-whatsapp-tracking').length) {
            SkySeoWhatsAppTracking.init();
        }
    });
    
    // Make available globally
    window.SkySeoWhatsAppTracking = SkySeoWhatsAppTracking;

})(jQuery);