/**
 * Sky SEO Boost - Google Ads Dashboard JavaScript
 * With custom date range picker and real-time data loading
 */

(function($) {
    'use strict';

    // Ensure namespace exists
    window.SkyGoogleAds = window.SkyGoogleAds || {};

    // Utility functions
    function formatNumber(number, decimals = 2) {
        if (isNaN(number)) return '0';
        return new Intl.NumberFormat('en-US', {
            minimumFractionDigits: decimals,
            maximumFractionDigits: decimals
        }).format(number);
    }

    function formatCurrency(amount) {
        const symbol = window.skyGoogleAds?.currency || '$';
        return symbol + formatNumber(amount, 2);
    }

    function formatDate(dateString) {
        if (!dateString) return '';
        const date = new Date(dateString);
        return date.toLocaleDateString('en-US', {
            month: 'short',
            day: 'numeric',
            year: 'numeric'
        });
    }

    // Main Dashboard Module
    window.SkyGoogleAds.Dashboard = {
        currentDateRange: 'last30days',
        customDateFrom: '',
        customDateTo: '',

        /**
         * Initialize dashboard
         */
        init: function() {
            this.setupEventHandlers();
            this.loadDashboard();
        },

        /**
         * Setup event handlers
         */
        setupEventHandlers: function() {
            const self = this;

            // Date range preset change
            $('#sky-gads-date-range').on('change', function() {
                const range = $(this).val();
                self.currentDateRange = range;

                // Show/hide custom date inputs
                if (range === 'custom') {
                    $('.sky-gads-custom-dates').show();
                } else {
                    $('.sky-gads-custom-dates').hide();
                    self.loadDashboard();
                }
            });

            // Custom date range apply
            $('#sky-gads-date-apply').on('click', function() {
                self.customDateFrom = $('#sky-gads-date-from').val();
                self.customDateTo = $('#sky-gads-date-to').val();

                if (self.customDateFrom && self.customDateTo) {
                    self.loadDashboard();
                } else {
                    alert('Please select both start and end dates');
                }
            });

            // Refresh button
            $('#sky-gads-refresh').on('click', function() {
                self.loadDashboard();
            });
        },

        /**
         * Load dashboard data
         */
        loadDashboard: function() {
            const self = this;
            const $content = $('#sky-gads-dashboard-content');

            // Show loading state
            $content.html('<div class="sky-gads-loading"><span class="spinner is-active"></span><p>Loading analytics data...</p></div>');

            // Prepare data
            const data = {
                action: 'sky_seo_load_google_ads_dashboard',
                nonce: window.skyGoogleAds.nonce,
                date_range: self.currentDateRange,
                date_from: self.customDateFrom,
                date_to: self.customDateTo,
                conversion_type: window.skyGoogleAds.conversionType || 'woocommerce'
            };

            // Load data via AJAX
            $.ajax({
                url: window.skyGoogleAds.ajaxurl,
                type: 'POST',
                data: data,
                success: function(response) {
                    if (response.success) {
                        self.renderDashboard(response.data);
                    } else {
                        $content.html('<div class="sky-gads-empty"><h4>Error</h4><p>' + (response.data.message || 'Failed to load data') + '</p></div>');
                    }
                },
                error: function() {
                    $content.html('<div class="sky-gads-empty"><h4>Error</h4><p>Failed to load dashboard data. Please try again.</p></div>');
                }
            });
        },

        /**
         * Render dashboard with data
         */
        renderDashboard: function(data) {
            const stats = data.stats || {};
            const $content = $('#sky-gads-dashboard-content');

            let html = '';

            // Metrics Cards
            html += '<div class="sky-gads-metrics">';

            // Total Clicks
            html += '<div class="sky-gads-metric-card">';
            html += '<h4>Total Clicks</h4>';
            html += '<div class="sky-gads-metric-value">' + formatNumber(stats.total_clicks || 0, 0) + '</div>';
            html += '<div class="sky-gads-metric-sublabel">Google Ads visitors</div>';
            html += '</div>';

            // Conversions
            html += '<div class="sky-gads-metric-card">';
            html += '<h4>Conversions</h4>';
            html += '<div class="sky-gads-metric-value">' + formatNumber(stats.total_conversions || 0, 0) + '</div>';
            html += '<div class="sky-gads-metric-sublabel">Completed actions</div>';
            html += '</div>';

            // Conversion Rate
            html += '<div class="sky-gads-metric-card">';
            html += '<h4>Conversion Rate</h4>';
            html += '<div class="sky-gads-metric-value">' + formatNumber(stats.conversion_rate || 0, 2) + '%</div>';
            html += '<div class="sky-gads-metric-sublabel">Click to conversion</div>';
            html += '</div>';

            // Revenue (if WooCommerce)
            if (stats.revenue_data && window.skyGoogleAds.conversionType === 'woocommerce') {
                html += '<div class="sky-gads-metric-card">';
                html += '<h4>Total Revenue</h4>';
                html += '<div class="sky-gads-metric-value">' + formatCurrency(stats.revenue_data.total_revenue || 0) + '</div>';
                html += '<div class="sky-gads-metric-sublabel">From ' + formatNumber(stats.revenue_data.total_orders || 0, 0) + ' orders</div>';
                html += '</div>';

                // Average Order Value
                html += '<div class="sky-gads-metric-card">';
                html += '<h4>Avg Order Value</h4>';
                html += '<div class="sky-gads-metric-value">' + formatCurrency(stats.revenue_data.avg_order_value || 0) + '</div>';
                html += '<div class="sky-gads-metric-sublabel">Per order</div>';
                html += '</div>';
            }

            html += '</div>'; // End metrics

            // Top Campaigns
            if (stats.top_campaigns && stats.top_campaigns.length > 0) {
                html += '<div class="sky-gads-table-container">';
                html += '<h3><span class="dashicons dashicons-megaphone"></span> Top Performing Campaigns</h3>';
                html += '<table class="sky-gads-table">';
                html += '<thead><tr>';
                html += '<th>Campaign Name</th>';
                html += '<th class="text-center">Clicks</th>';
                html += '<th class="text-center">Conversions</th>';
                html += '<th class="text-center">CTR</th>';
                if (window.skyGoogleAds.conversionType === 'woocommerce') {
                    html += '<th class="text-right">Revenue</th>';
                }
                html += '</tr></thead><tbody>';

                stats.top_campaigns.forEach(function(campaign) {
                    const ctr = campaign.clicks > 0 ? (campaign.conversions / campaign.clicks) * 100 : 0;
                    const badgeClass = ctr > 5 ? 'high' : (ctr > 2 ? 'medium' : 'low');

                    html += '<tr>';
                    html += '<td><span class="sky-gads-campaign-name">' + campaign.utm_campaign + '</span></td>';
                    html += '<td class="text-center">' + formatNumber(campaign.clicks, 0) + '</td>';
                    html += '<td class="text-center">' + formatNumber(campaign.conversions, 0) + '</td>';
                    html += '<td class="text-center"><span class="sky-gads-badge ' + badgeClass + '">' + formatNumber(ctr, 2) + '%</span></td>';
                    if (window.skyGoogleAds.conversionType === 'woocommerce') {
                        html += '<td class="text-right">' + formatCurrency(campaign.revenue || 0) + '</td>';
                    }
                    html += '</tr>';
                });

                html += '</tbody></table></div>';
            }

            // Top Landing Pages
            if (stats.top_pages && stats.top_pages.length > 0) {
                html += '<div class="sky-gads-table-container">';
                html += '<h3><span class="dashicons dashicons-admin-links"></span> Top Landing Pages</h3>';
                html += '<table class="sky-gads-table">';
                html += '<thead><tr>';
                html += '<th>Landing Page</th>';
                html += '<th class="text-center">Clicks</th>';
                html += '<th class="text-center">Conversions</th>';
                html += '<th class="text-center">Conversion Rate</th>';
                html += '</tr></thead><tbody>';

                stats.top_pages.forEach(function(page) {
                    const rate = page.clicks > 0 ? (page.conversions / page.clicks) * 100 : 0;
                    const badgeClass = rate > 5 ? 'high' : (rate > 2 ? 'medium' : 'low');

                    html += '<tr>';
                    html += '<td><span class="sky-gads-landing-page">' + page.landing_page + '</span></td>';
                    html += '<td class="text-center">' + formatNumber(page.clicks, 0) + '</td>';
                    html += '<td class="text-center">' + formatNumber(page.conversions, 0) + '</td>';
                    html += '<td class="text-center"><span class="sky-gads-badge ' + badgeClass + '">' + formatNumber(rate, 2) + '%</span></td>';
                    html += '</tr>';
                });

                html += '</tbody></table></div>';
            }

            // Traffic by Region
            if (stats.country_data && stats.country_data.length > 0) {
                html += '<div class="sky-gads-table-container">';
                html += '<h3><span class="dashicons dashicons-location"></span> Traffic by Region</h3>';
                html += '<table class="sky-gads-table">';
                html += '<thead><tr>';
                html += '<th>IP Prefix</th>';
                html += '<th class="text-center">Clicks</th>';
                html += '<th class="text-center">Conversions</th>';
                html += '<th class="text-center">Conversion Rate</th>';
                html += '</tr></thead><tbody>';

                stats.country_data.forEach(function(location) {
                    const rate = location.clicks > 0 ? (location.conversions / location.clicks) * 100 : 0;
                    const badgeClass = rate > 5 ? 'high' : (rate > 2 ? 'medium' : 'low');

                    html += '<tr>';
                    html += '<td>' + location.ip_prefix + '.x.x</td>';
                    html += '<td class="text-center">' + formatNumber(location.clicks, 0) + '</td>';
                    html += '<td class="text-center">' + formatNumber(location.conversions, 0) + '</td>';
                    html += '<td class="text-center"><span class="sky-gads-badge ' + badgeClass + '">' + formatNumber(rate, 2) + '%</span></td>';
                    html += '</tr>';
                });

                html += '</tbody></table>';
                html += '<p style="margin-top: 16px; color: #86868b; font-size: 13px; font-style: italic;">';
                html += 'Note: IP addresses are anonymized for GDPR compliance. Full geographic data requires additional setup.';
                html += '</p>';
                html += '</div>';
            }

            // Empty state
            if (!stats.top_campaigns || stats.top_campaigns.length === 0) {
                if (stats.total_clicks === 0) {
                    html += '<div class="sky-gads-empty">';
                    html += '<span class="dashicons dashicons-chart-line" style="font-size: 48px;"></span>';
                    html += '<h4>No data available yet</h4>';
                    html += '<p>Start running Google Ads campaigns to see analytics here.</p>';
                    html += '</div>';
                }
            }

            $content.html(html);
        }
    };

    // Initialize when document is ready
    $(document).ready(function() {
        if ($('#sky-gads-dashboard-content').length) {
            window.SkyGoogleAds.Dashboard.init();
        }
    });

})(jQuery);
