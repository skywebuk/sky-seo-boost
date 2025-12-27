// Sky SEO Boost - Analytics Dashboard JavaScript

jQuery(document).ready(function($) {
    // Initialize Analytics Dashboard
    if ($('.sky-seo-analytics-dashboard').length) {
        initAnalyticsDashboard();
    }
    
    function initAnalyticsDashboard() {
        // Date preset handler
        $('#date_range').on('change', function() {
            if ($(this).val() === 'custom') {
                $('.sky-seo-custom-dates').show();
            } else {
                $('.sky-seo-custom-dates').hide();
            }
        });
        
        // Initialize charts if data exists
        if (typeof skySeoAnalyticsData !== 'undefined') {
            initTrafficSourcesChart();
            initContentTypesChart();
            initTrendsChart();
            initGeographicChart();
            initSparklines();
            initMiniCharts();
        }
        
        // Content search
        $('#sky-seo-content-search').on('input', function() {
            filterContentTable();
        });
        
        // Content filters
        $('#sky-seo-content-type-filter, #sky-seo-content-traffic-filter, #sky-seo-content-sort').on('change', function() {
            filterContentTable();
        });
        
        // Sortable table headers
        $('.sky-seo-analytics-table th.sortable').on('click', function() {
            const $th = $(this);
            const column = $th.data('sort');
            const isAsc = $th.hasClass('asc');
            
            // Remove sort classes from all headers
            $('.sky-seo-analytics-table th').removeClass('asc desc');
            
            // Add sort class to clicked header
            $th.addClass(isAsc ? 'desc' : 'asc');
            
            // Sort table rows
            sortTable(column, !isAsc);
        });
        
        // View details button - with event delegation for dynamic content
        $(document).on('click', '.sky-seo-view-details', function() {
            const postId = $(this).data('post-id');
            showPostDetails(postId);
        });
        
        // Load more button
        $(document).on('click', '.sky-seo-load-more', function() {
            const $button = $(this);
            const currentPage = parseInt($button.data('page'));
            const totalPages = parseInt($button.data('total-pages'));
            
            // Disable button and show loading
            $button.prop('disabled', true).text('Loading...');
            
            // Load more content
            $.ajax({
                url: ajaxurl,
                type: 'POST',
                data: {
                    action: 'sky_seo_load_more_content',
                    page: currentPage,
                    start_date: skySeoAnalyticsData.start_date,
                    end_date: skySeoAnalyticsData.end_date,
                    post_type: skySeoAnalyticsData.post_type,
                    nonce: skySeoAnalyticsData.ajax_nonce
                },
                success: function(response) {
                    if (response.success) {
                        // Append new rows
                        $('#sky-seo-content-tbody').append(response.data.html);
                        
                        // Initialize mini charts for new content
                        initNewMiniCharts();
                        
                        // Update pagination
                        if (currentPage < totalPages) {
                            $button.data('page', currentPage + 1).prop('disabled', false).text('Load More');
                            $('.pagination-info').text(`Page ${currentPage} of ${totalPages}`);
                        } else {
                            $button.remove();
                            $('.pagination-info').text(`All ${totalPages} pages loaded`);
                        }
                        
                        // Hide button if no more content
                        if (!response.data.has_more) {
                            $button.remove();
                        }
                    }
                },
                error: function() {
                    $button.prop('disabled', false).text('Load More');
                    alert('Error loading more content. Please try again.');
                }
            });
        });
    }
    
    // Filter content table
    function filterContentTable() {
        const searchTerm = $('#sky-seo-content-search').val().toLowerCase();
        const typeFilter = $('#sky-seo-content-type-filter').val();
        const trafficFilter = $('#sky-seo-content-traffic-filter').val();
        const sortBy = $('#sky-seo-content-sort').val();
        
        let $rows = $('#sky-seo-content-tbody tr').toArray();
        let visibleRows = [];
        
        // Filter rows
        $rows.forEach(function(row) {
            const $row = $(row);
            const title = $row.find('.column-title strong').text().toLowerCase();
            const type = $row.find('.sky-seo-content-type').attr('class').match(/sky-seo-type-(\S+)/);
            const postType = type ? type[1] : '';
            
            // Check search filter
            const matchesSearch = !searchTerm || title.includes(searchTerm);
            
            // Check type filter
            const matchesType = !typeFilter || postType === typeFilter;
            
            // Check traffic filter
            let matchesTraffic = true;
            if (trafficFilter) {
                const trafficData = {
                    google: parseInt($row.find('.column-google').data('google') || 0),
                    social: parseInt($row.find('.column-social').data('social') || 0),
                    direct: parseInt($row.find('.column-direct').data('direct') || 0)
                };
                
                // Show rows where the selected traffic source is the highest
                const maxTraffic = Math.max(trafficData.google, trafficData.social, trafficData.direct);
                matchesTraffic = trafficData[trafficFilter] === maxTraffic && maxTraffic > 0;
            }
            
            if (matchesSearch && matchesType && matchesTraffic) {
                visibleRows.push(row);
            }
        });
        
        // Sort visible rows
        if (sortBy && sortBy !== 'total_clicks') {
            visibleRows.sort(function(a, b) {
                const $a = $(a);
                const $b = $(b);
                
                if (sortBy === 'recent') {
                    // Sort by date (assuming we have date data)
                    return 0; // Placeholder - would need date data
                } else {
                    const aVal = parseInt($a.find(`.column-${sortBy.replace('_clicks', '')}`).data(sortBy.replace('_clicks', '')) || 0);
                    const bVal = parseInt($b.find(`.column-${sortBy.replace('_clicks', '')}`).data(sortBy.replace('_clicks', '')) || 0);
                    return bVal - aVal; // Descending order
                }
            });
        }
        
        // Hide all rows first
        $('#sky-seo-content-tbody tr').hide();
        
        // Show filtered and sorted rows
        visibleRows.forEach(function(row) {
            $(row).show();
        });
        
        // Update results count
        const totalVisible = visibleRows.length;
        const totalRows = $rows.length;
        
        if (totalVisible !== totalRows) {
            $('.pagination-info').text(`Showing ${totalVisible} of ${totalRows} items`);
        }
    }
    
    // Initialize Geographic Chart
    function initGeographicChart() {
        const ctx = document.getElementById('sky-seo-geography-chart');
        if (!ctx || !skySeoAnalyticsData.geographic_data) return;
        
        ctx.height = 250;
        
        const countries = skySeoAnalyticsData.geographic_data.countries || [];
        const cities = skySeoAnalyticsData.geographic_data.cities || [];
        
        // Combine and limit data
        const allLocations = [
            ...countries.map(c => ({
                label: `${sky_seo_get_flag(c.country_code)} ${c.country_name}`,
                value: parseInt(c.total_clicks),
                type: 'country'
            })),
            ...cities.slice(0, 10).map(c => ({
                label: `${c.city_name}, ${c.country_name}`,
                value: parseInt(c.total_clicks),
                type: 'city'
            }))
        ].sort((a, b) => b.value - a.value).slice(0, 15);
        
        const chart = new Chart(ctx, {
            type: 'bar',
            data: {
                labels: allLocations.map(l => l.label),
                datasets: [{
                    label: 'Clicks',
                    data: allLocations.map(l => l.value),
                    backgroundColor: allLocations.map(l => l.type === 'country' ? '#3B82F6' : '#10B981'),
                    borderRadius: 4
                }]
            },
            options: {
                responsive: true,
                maintainAspectRatio: false,
                indexAxis: 'y',
                plugins: {
                    legend: {
                        display: false
                    },
                    tooltip: {
                        callbacks: {
                            label: function(context) {
                                return `${context.parsed.x.toLocaleString()} clicks`;
                            }
                        }
                    }
                },
                scales: {
                    x: {
                        beginAtZero: true,
                        grid: {
                            borderDash: [2, 2]
                        }
                    },
                    y: {
                        grid: {
                            display: false
                        }
                    }
                }
            }
        });
    }
    
    // Helper function to get flag emoji
    function sky_seo_get_flag(countryCode) {
        if (!countryCode || countryCode.length !== 2) return '🌐';
        
        const offset = 0x1F1E6 - 'A'.charCodeAt(0);
        const flag = String.fromCodePoint(
            countryCode.charCodeAt(0) + offset,
            countryCode.charCodeAt(1) + offset
        );
        return flag;
    }
    
    // Initialize mini charts for newly loaded content
    function initNewMiniCharts() {
        $('.sky-seo-mini-chart').each(function() {
            const $this = $(this);
            if ($this.find('.sky-seo-mini-bars').length === 0) { // Only initialize if not already done
                const trend = $this.data('trend');
                if (!trend || trend.length === 0) return;
                
                // Create mini bar chart
                const chartHtml = trend.map((value, index) => {
                    const height = Math.max(5, (value / Math.max(...trend)) * 30);
                    return `<div class="sky-seo-mini-bar" style="height: ${height}px;"></div>`;
                }).join('');
                
                $this.html(`<div class="sky-seo-mini-bars">${chartHtml}</div>`);
            }
        });
    }
    
    // Initialize Traffic Sources Pie Chart
    function initTrafficSourcesChart() {
        const ctx = document.getElementById('sky-seo-sources-chart');
        if (!ctx) return;
        
        // Set explicit dimensions
        ctx.width = 300;
        ctx.height = 280;
        
        const data = skySeoAnalyticsData.traffic_sources;
        const chart = new Chart(ctx, {
            type: 'doughnut',
            data: {
                labels: data.map(d => d.label),
                datasets: [{
                    data: data.map(d => d.value),
                    backgroundColor: data.map(d => d.color),
                    borderWidth: 0
                }]
            },
            options: {
                responsive: true,
                maintainAspectRatio: false,
                plugins: {
                    legend: {
                        display: false
                    },
                    tooltip: {
                        callbacks: {
                            label: function(context) {
                                const total = context.dataset.data.reduce((a, b) => a + b, 0);
                                const percentage = ((context.parsed / total) * 100).toFixed(1);
                                return context.label + ': ' + context.parsed.toLocaleString() + ' (' + percentage + '%)';
                            }
                        }
                    }
                },
                cutout: '60%'
            }
        });
        
        // Create custom legend
        createChartLegend(data, 'sources-legend');
    }
    
    // Initialize Content Types Chart
    function initContentTypesChart() {
        const ctx = document.getElementById('sky-seo-content-chart');
        if (!ctx) return;
        
        // Set explicit dimensions
        ctx.width = 300;
        ctx.height = 280;
        
        const data = skySeoAnalyticsData.content_types;
        const chart = new Chart(ctx, {
            type: 'bar',
            data: {
                labels: data.map(d => d.label),
                datasets: [{
                    data: data.map(d => d.value),
                    backgroundColor: data.map(d => d.color),
                    borderRadius: 6
                }]
            },
            options: {
                responsive: true,
                maintainAspectRatio: false,
                plugins: {
                    legend: {
                        display: false
                    }
                },
                scales: {
                    y: {
                        beginAtZero: true,
                        grid: {
                            borderDash: [2, 2]
                        }
                    },
                    x: {
                        grid: {
                            display: false
                        }
                    }
                }
            }
        });
    }
    
    // Initialize Trends Chart
    function initTrendsChart() {
        const ctx = document.getElementById('sky-seo-trends-chart');
        if (!ctx) return;
        
        // Set explicit dimensions for wide chart
        ctx.height = 250;
        
        const data = skySeoAnalyticsData.daily_trends;
        const datasets = [
            {
                label: 'Total Clicks',
                data: data.map(d => d.total),
                borderColor: '#3B82F6',
                backgroundColor: 'rgba(59, 130, 246, 0.1)',
                tension: 0.4
            },
            {
                label: 'Google',
                data: data.map(d => d.google),
                borderColor: '#10B981',
                backgroundColor: 'rgba(16, 185, 129, 0.1)',
                tension: 0.4,
                hidden: true
            },
            {
                label: 'Social',
                data: data.map(d => d.social),
                borderColor: '#8B5CF6',
                backgroundColor: 'rgba(139, 92, 246, 0.1)',
                tension: 0.4,
                hidden: true
            },
            {
                label: 'Direct',
                data: data.map(d => d.direct),
                borderColor: '#F59E0B',
                backgroundColor: 'rgba(245, 158, 11, 0.1)',
                tension: 0.4,
                hidden: true
            }
        ];
        
        // Add comparison data if available
        if (skySeoAnalyticsData.compare_trends) {
            datasets.push({
                label: 'Previous Period',
                data: skySeoAnalyticsData.compare_trends.map(d => d.total),
                borderColor: '#9CA3AF',
                backgroundColor: 'transparent',
                borderDash: [5, 5],
                tension: 0.4
            });
        }
        
        const chart = new Chart(ctx, {
            type: 'line',
            data: {
                labels: data.map(d => formatDate(d.date)),
                datasets: datasets
            },
            options: {
                responsive: true,
                maintainAspectRatio: false,
                interaction: {
                    mode: 'index',
                    intersect: false
                },
                plugins: {
                    legend: {
                        position: 'bottom'
                    },
                    tooltip: {
                        callbacks: {
                            title: function(tooltipItems) {
                                return formatDate(data[tooltipItems[0].dataIndex].date, 'long');
                            }
                        }
                    }
                },
                scales: {
                    y: {
                        beginAtZero: true,
                        grid: {
                            borderDash: [2, 2]
                        }
                    },
                    x: {
                        grid: {
                            display: false
                        }
                    }
                }
            }
        });
    }
    
    // Initialize Sparklines
    function initSparklines() {
        $('.sky-seo-metric-sparkline').each(function() {
            const $this = $(this);
            const values = $this.data('values');
            if (!values || values.length === 0) return;
            
            // Create mini line chart using canvas
            const canvas = $('<canvas>').attr({
                width: 100,
                height: 40
            })[0];
            $this.append(canvas);
            
            const ctx = canvas.getContext('2d');
            const max = Math.max(...values);
            const min = Math.min(...values);
            const range = max - min || 1;
            
            ctx.strokeStyle = '#3B82F6';
            ctx.lineWidth = 2;
            ctx.beginPath();
            
            values.forEach((value, index)=> {
               const x = (index / (values.length - 1)) * 100;
               const y = 40 - ((value - min) / range) * 35;
               
               if (index === 0) {
                   ctx.moveTo(x, y);
               } else {
                   ctx.lineTo(x, y);
               }
           });
           
           ctx.stroke();
       });
   }
   
   // Initialize Mini Charts
   function initMiniCharts() {
       $('.sky-seo-mini-chart').each(function() {
           const $this = $(this);
           const trend = $this.data('trend');
           if (!trend || trend.length === 0) return;
           
           // Create mini bar chart
           const chartHtml = trend.map((value, index) => {
               const height = Math.max(5, (value / Math.max(...trend)) * 30);
               return `<div class="sky-seo-mini-bar" style="height: ${height}px;"></div>`;
           }).join('');
           
           $this.html(`<div class="sky-seo-mini-bars">${chartHtml}</div>`);
       });
   }
   
   // Create Chart Legend
   function createChartLegend(data, containerId) {
       const $container = $('#' + containerId);
       if (!$container.length) return;
       
       const legendHtml = data.map(item => `
           <div class="sky-seo-legend-item">
               <div class="sky-seo-legend-color" style="background-color: ${item.color};"></div>
               <span>${item.label}: ${item.value.toLocaleString()}</span>
           </div>
       `).join('');
       
       $container.html(legendHtml);
   }
   
   // Sort Table
   function sortTable(column, ascending) {
       const $tbody = $('#sky-seo-content-tbody');
       const $rows = $tbody.find('tr:visible').toArray();
       
       $rows.sort((a, b) => {
           let aVal, bVal;
           
           switch (column) {
               case 'clicks':
                   aVal = parseInt($(a).find('.column-clicks').data('clicks'));
                   bVal = parseInt($(b).find('.column-clicks').data('clicks'));
                   break;
               case 'google':
                   aVal = parseInt($(a).find('.column-google').data('google'));
                   bVal = parseInt($(b).find('.column-google').data('google'));
                   break;
               case 'social':
                   aVal = parseInt($(a).find('.column-social').data('social'));
                   bVal = parseInt($(b).find('.column-social').data('social'));
                   break;
               case 'direct':
                   aVal = parseInt($(a).find('.column-direct').data('direct'));
                   bVal = parseInt($(b).find('.column-direct').data('direct'));
                   break;
               default:
                   aVal = $(a).find('.column-' + column).text();
                   bVal = $(b).find('.column-' + column).text();
           }
           
           if (ascending) {
               return aVal > bVal ? 1 : -1;
           } else {
               return aVal < bVal ? 1 : -1;
           }
       });
       
       $tbody.html($rows);
   }
   
   // Show Post Details - UPDATED
   function showPostDetails(postId) {
       // Create modal
       const modal = $(`
           <div class="sky-seo-modal">
               <div class="sky-seo-modal-content">
                   <div class="sky-seo-modal-header">
                       <h3>Loading...</h3>
                       <button class="sky-seo-modal-close">&times;</button>
                   </div>
                   <div class="sky-seo-modal-body">
                       <div class="sky-seo-loading"></div>
                   </div>
               </div>
           </div>
       `);
       
       $('body').append(modal);
       
       // Close modal handlers
       modal.find('.sky-seo-modal-close').on('click', function() {
           modal.remove();
       });
       
       modal.on('click', function(e) {
           if (e.target === this) {
               modal.remove();
           }
       });
       
       // Load post details via AJAX
       $.ajax({
           url: ajaxurl,
           type: 'POST',
           data: {
               action: 'sky_seo_get_post_details',
               post_id: postId,
               nonce: skySeoAnalyticsData.ajax_nonce
           },
           success: function(response) {
               if (response.success) {
                   modal.find('.sky-seo-modal-header h3').text('Country Breakdown');
                   modal.find('.sky-seo-modal-body').html(response.data.html);
               } else {
                   modal.find('.sky-seo-modal-body').html('<p>Error loading post details.</p>');
               }
           },
           error: function() {
               modal.find('.sky-seo-modal-body').html('<p>Error loading post details.</p>');
           }
       });
   }
   
   // Format Date
   function formatDate(dateStr, format = 'short') {
       const date = new Date(dateStr);
       if (format === 'long') {
           return date.toLocaleDateString('en-US', {
               weekday: 'long',
               year: 'numeric',
               month: 'long',
               day: 'numeric'
           });
       } else {
           return date.toLocaleDateString('en-US', {
               month: 'short',
               day: 'numeric'
           });
       }
   }
   
   // Real-time Updates (Optional)
   function startRealTimeUpdates() {
       setInterval(function() {
           if ($('.sky-seo-analytics-dashboard').length && !document.hidden) {
               updateMetrics();
           }
       }, 30000); // Update every 30 seconds
   }
   
   // Update Metrics via AJAX
   function updateMetrics() {
       const $dashboard = $('.sky-seo-analytics-dashboard');
       $dashboard.addClass('sky-seo-loading');
       
       // Would need to implement AJAX endpoint
       setTimeout(() => {
           $dashboard.removeClass('sky-seo-loading');
       }, 1000);
   }
});