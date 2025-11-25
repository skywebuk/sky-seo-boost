/**
 * Sky Insights UTM Dashboard JavaScript - COMPLETE VERSION
 * Includes bulk delete, column sorting, working filters, and pagination
 */

(function($) {
    'use strict';

    // Ensure SkyInsights namespace exists
    window.SkyInsights = window.SkyInsights || {};

    // Utility functions
    function formatNumber(number, decimals = 2) {
        if (isNaN(number)) return '0';
        return new Intl.NumberFormat('en-US', {
            minimumFractionDigits: decimals,
            maximumFractionDigits: decimals
        }).format(number);
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

    function copyToClipboard(text) {
        const temp = $('<input>');
        $('body').append(temp);
        temp.val(text).select();
        document.execCommand('copy');
        temp.remove();
    }

    // Load Chart.js library
    async function loadChartLibrary() {
        // Wait for Chart.js to be available
        let attempts = 0;
        while (typeof Chart === 'undefined' && attempts < 10) {
            await new Promise(resolve => setTimeout(resolve, 100));
            attempts++;
        }
        
        if (typeof Chart === 'undefined') {
            console.error('Chart.js failed to load');
            return false;
        }
        return true;
    }

    // UTM Dashboard Module
    window.SkyInsights.UTM = {
        currentPage: 1,
        perPage: 20,
        totalPages: 1,
        editingLinkId: null,
        selectedLinks: [], // Track selected links for bulk operations
        currentLinks: [], // Store current links data for sorting
        sortColumn: null, // Track current sort column
        sortDirection: 'desc', // Track sort direction

        /**
         * Initialize UTM dashboard
         */
        init: function() {
            const self = this;
            
            console.log('Initializing UTM Dashboard with all features...');
            
            // Set up event handlers
            this.setupEventHandlers();
            
            // Load initial data
            this.loadLinks().then(() => {
                // Load analytics after links are loaded
                self.loadAnalytics();
            }).catch(error => {
                console.error('Error loading initial data:', error);
            });
        },

        /**
         * Set up event handlers - COMPLETE with all features
         */
        setupEventHandlers: function() {
            const self = this;
            
            // Form submission
            $('#sky-utm-builder-form').on('submit', function(e) {
                e.preventDefault();
                self.handleFormSubmit();
            });
            
            // Quick create templates
            $('.sky-utm-quick-create').on('click', function() {
                const $btn = $(this);
                const template = $btn.data('template');
                
                // Define template mappings
                const templates = {
                    'social-facebook': {
                        source: 'facebook',
                        medium: 'social',
                        campaign: 'fb_campaign_' + new Date().toISOString().split('T')[0]
                    },
                    'social-x': {
                        source: 'x',
                        medium: 'social',
                        campaign: 'x_campaign_' + new Date().toISOString().split('T')[0]
                    },
                    'social-instagram': {
                        source: 'instagram',
                        medium: 'social',
                        campaign: 'ig_campaign_' + new Date().toISOString().split('T')[0]
                    },
                    'social-whatsapp': {
                        source: 'whatsapp',
                        medium: 'social',
                        campaign: 'whatsapp_campaign_' + new Date().toISOString().split('T')[0]
                    },
                    'sms': {
                        source: 'sms',
                        medium: 'message',
                        campaign: 'sms_campaign_' + new Date().toISOString().split('T')[0]
                    },
                    'email-newsletter': {
                        source: 'newsletter',
                        medium: 'email',
                        campaign: 'email_campaign_' + new Date().toISOString().split('T')[0]
                    },
                    'google-cpc': {
                        source: 'google',
                        medium: 'cpc',
                        campaign: 'google_campaign_' + new Date().toISOString().split('T')[0]
                    }
                };
                
                if (templates[template]) {
                    $('#utm-source').val(templates[template].source);
                    $('#utm-medium').val(templates[template].medium);
                    $('#utm-campaign').val(templates[template].campaign);
                }
            });
            
            // Link actions - Edit, Delete, View Details
            $(document).on('click', '.sky-utm-edit-link', function() {
                const linkId = $(this).data('id');
                self.editLink(linkId);
            });
            
            $(document).on('click', '.sky-utm-delete-link', function() {
                const linkId = $(this).data('id');
                if (confirm('Are you sure you want to delete this UTM link?')) {
                    self.deleteLink(linkId);
                }
            });
            
            $(document).on('click', '.sky-utm-view-details', function() {
                const linkId = $(this).data('id');
                self.viewClickDetails(linkId);
            });
            
            // Copy link
            $(document).on('click', '.sky-utm-copy-link', function(e) {
                e.preventDefault();
                const url = $(this).data('url');
                copyToClipboard(url);
                
                // Show feedback
                const $btn = $(this);
                const originalHtml = $btn.html();
                $btn.html('✓ Copied!');
                setTimeout(() => {
                    $btn.html(originalHtml);
                }, 2000);
            });
            
            // Modal close
            $(document).on('click', '.sky-utm-modal-close, .sky-utm-modal', function(e) {
                if ($(e.target).hasClass('sky-utm-modal') || $(e.target).hasClass('sky-utm-modal-close')) {
                    $('.sky-utm-modal').remove();
                }
            });
            
            // FIXED: Filter changes - reset page to 1 and reload
            $('#sky-utm-filter-source, #sky-utm-filter-campaign').on('change', function() {
                self.currentPage = 1; // Reset to first page when filtering
                self.loadLinks();
            });
            
            // Date range change for analytics
            $('#sky-utm-date-range').on('change', function() {
                const range = $(this).val();
                self.loadAnalytics(range);
            });
            
            // Select all checkbox
            $(document).on('change', '#sky-utm-select-all', function() {
                const isChecked = $(this).prop('checked');
                $('.sky-utm-select-link').prop('checked', isChecked);
                self.updateSelectedLinks();
                self.updateBulkActionsVisibility();
            });
            
            // Individual checkbox selection
            $(document).on('change', '.sky-utm-select-link', function() {
                self.updateSelectedLinks();
                self.updateSelectAllCheckbox();
                self.updateBulkActionsVisibility();
            });
            
            // Bulk delete button
            $(document).on('click', '#sky-utm-bulk-delete', function() {
                if (self.selectedLinks.length > 0) {
                    const count = self.selectedLinks.length;
                    if (confirm(`Are you sure you want to delete ${count} selected link(s)? This action cannot be undone.`)) {
                        self.bulkDeleteLinks();
                    }
                }
            });
            
            // Column sorting handlers
            $(document).on('click', '.sortable-column', function() {
                const column = $(this).data('sort');
                self.sortTable(column);
            });
            
            // PAGINATION handlers
            $(document).on('click', '.sky-utm-pagination a', function(e) {
                e.preventDefault();
                const page = $(this).data('page');
                if (page && page !== self.currentPage) {
                    self.currentPage = page;
                    self.loadLinks();
                }
            });
        },
        
        /**
         * Sort table by column
         */
        sortTable: function(column) {
            const self = this;
            
            // Toggle direction if same column, otherwise default to desc
            if (self.sortColumn === column) {
                self.sortDirection = self.sortDirection === 'desc' ? 'asc' : 'desc';
            } else {
                self.sortColumn = column;
                self.sortDirection = 'desc';
            }
            
            // Sort the current links array
            const sortedLinks = [...self.currentLinks].sort((a, b) => {
                let valueA, valueB;
                
                switch(column) {
                    case 'clicks':
                        valueA = parseInt(a.clicks) || 0;
                        valueB = parseInt(b.clicks) || 0;
                        break;
                    case 'conversions':
                        valueA = parseInt(a.conversions) || 0;
                        valueB = parseInt(b.conversions) || 0;
                        break;
                    case 'conversion_rate':
                        valueA = parseFloat(a.conversion_rate) || 0;
                        valueB = parseFloat(b.conversion_rate) || 0;
                        break;
                    case 'revenue':
                        valueA = parseFloat(a.revenue) || 0;
                        valueB = parseFloat(b.revenue) || 0;
                        break;
                    default:
                        return 0;
                }
                
                if (self.sortDirection === 'desc') {
                    return valueB - valueA;
                } else {
                    return valueA - valueB;
                }
            });
            
            // Re-render the table with sorted data
            self.renderLinksTable(sortedLinks);
            
            // Update sort indicators
            self.updateSortIndicators();
        },
        
        /**
         * Update sort indicators in table headers
         */
        updateSortIndicators: function() {
            const self = this;
            
            // Remove all existing indicators
            $('.sortable-column .sort-indicator').remove();
            
            // Add indicator to current sort column
            if (self.sortColumn) {
                const $header = $(`.sortable-column[data-sort="${self.sortColumn}"]`);
                const indicator = self.sortDirection === 'desc' ? '↓' : '↑';
                $header.append(`<span class="sort-indicator"> ${indicator}</span>`);
            }
        },
        
        /**
         * Update selected links array
         */
        updateSelectedLinks: function() {
            this.selectedLinks = [];
            $('.sky-utm-select-link:checked').each((index, element) => {
                this.selectedLinks.push($(element).val());
            });
        },
        
        /**
         * Update select all checkbox state
         */
        updateSelectAllCheckbox: function() {
            const totalCheckboxes = $('.sky-utm-select-link').length;
            const checkedCheckboxes = $('.sky-utm-select-link:checked').length;
            
            if (totalCheckboxes === 0) {
                $('#sky-utm-select-all').prop('checked', false).prop('indeterminate', false);
            } else if (checkedCheckboxes === 0) {
                $('#sky-utm-select-all').prop('checked', false).prop('indeterminate', false);
            } else if (checkedCheckboxes === totalCheckboxes) {
                $('#sky-utm-select-all').prop('checked', true).prop('indeterminate', false);
            } else {
                $('#sky-utm-select-all').prop('checked', false).prop('indeterminate', true);
            }
        },
        
        /**
         * Show/hide bulk actions based on selection
         */
        updateBulkActionsVisibility: function() {
            const $bulkActions = $('#sky-utm-bulk-actions');
            const selectedCount = this.selectedLinks.length;
            
            if (selectedCount > 0) {
                if ($bulkActions.length === 0) {
                    // Create bulk actions bar if it doesn't exist
                    const bulkActionsHtml = `
                        <div id="sky-utm-bulk-actions" class="sky-utm-bulk-actions">
                            <span class="sky-utm-selected-count">${selectedCount} link(s) selected</span>
                            <button id="sky-utm-bulk-delete" class="button button-secondary">
                                <svg width="14" height="14" viewBox="0 0 14 14" fill="none" style="margin-right: 5px;">
                                    <path d="M1 3H13M5 3V1H9V3M2 3V12C2 12.5523 2.44772 13 3 13H11C11.5523 13 12 12.5523 12 12V3H2Z" stroke="currentColor" stroke-width="1.5"/>
                                </svg>
                                Delete Selected
                            </button>
                        </div>
                    `;
                    $('.sky-utm-table-header').after(bulkActionsHtml);
                } else {
                    $('.sky-utm-selected-count').text(`${selectedCount} link(s) selected`);
                }
                $('#sky-utm-bulk-actions').show();
            } else {
                $('#sky-utm-bulk-actions').hide();
            }
        },
        
        /**
         * Bulk delete selected links
         */
        bulkDeleteLinks: function() {
            const self = this;
            
            if (this.selectedLinks.length === 0) {
                alert('No links selected');
                return;
            }
            
            const $bulkButton = $('#sky-utm-bulk-delete');
            $bulkButton.prop('disabled', true).text('Deleting...');
            
            const data = {
                action: 'sky_insights_bulk_delete_utm_links',
                nonce: skyInsights.nonce,
                link_ids: this.selectedLinks
            };
            
            $.post(skyInsights.ajaxurl, data, function(response) {
                if (response.success) {
                    // Success notification
                    const deletedCount = response.data.deleted_count || self.selectedLinks.length;
                    
                    // Show success message
                    const $notification = $(`
                        <div class="notice notice-success is-dismissible" style="margin: 10px 0;">
                            <p>Successfully deleted ${deletedCount} link(s).</p>
                        </div>
                    `);
                    $('.sky-utm-links-container').before($notification);
                    
                    // Auto-dismiss after 3 seconds
                    setTimeout(() => {
                        $notification.fadeOut(function() {
                            $(this).remove();
                        });
                    }, 3000);
                    
                    // Reset selection
                    self.selectedLinks = [];
                    $('#sky-utm-select-all').prop('checked', false);
                    
                    // Reload links
                    self.loadLinks();
                    
                    // Hide bulk actions
                    $('#sky-utm-bulk-actions').hide();
                } else {
                    alert('Failed to delete links: ' + (response.data || 'Unknown error'));
                }
                
                $bulkButton.prop('disabled', false).html(`
                    <svg width="14" height="14" viewBox="0 0 14 14" fill="none" style="margin-right: 5px;">
                        <path d="M1 3H13M5 3V1H9V3M2 3V12C2 12.5523 2.44772 13 3 13H11C11.5523 13 12 12.5523 12 12V3H2Z" stroke="currentColor" stroke-width="1.5"/>
                    </svg>
                    Delete Selected
                `);
            }).fail(function() {
                alert('An error occurred while deleting links. Please try again.');
                $bulkButton.prop('disabled', false).html(`
                    <svg width="14" height="14" viewBox="0 0 14 14" fill="none" style="margin-right: 5px;">
                        <path d="M1 3H13M5 3V1H9V3M2 3V12C2 12.5523 2.44772 13 3 13H11C11.5523 13 12 12.5523 12 12V3H2Z" stroke="currentColor" stroke-width="1.5"/>
                    </svg>
                    Delete Selected
                `);
            });
        },

        /**
         * Load UTM links - FIXED with proper filter parameters
         */
        loadLinks: function() {
            const self = this;
            
            return new Promise((resolve, reject) => {
                // FIXED: Use correct parameter names for filters
                const data = {
                    action: 'sky_insights_get_utm_links',
                    nonce: skyInsights.nonce,
                    utm_source: $('#sky-utm-filter-source').val() || '',
                    utm_campaign: $('#sky-utm-filter-campaign').val() || '',
                    page: self.currentPage,
                    per_page: self.perPage
                };
                
                // Show loading state
                $('#sky-utm-links-table tbody').html(`
                    <tr>
                        <td colspan="8" class="text-center">Loading...</td>
                    </tr>
                `);
                
                $.post(skyInsights.ajaxurl, data, function(response) {
                    if (response.success) {
                        // Store the links data for sorting
                        self.currentLinks = response.data.links || [];
                        
                        // Apply any existing sort
                        if (self.sortColumn) {
                            self.sortTable(self.sortColumn);
                        } else {
                            self.renderLinksTable(self.currentLinks);
                        }
                        
                        // Update filters dropdown options
                        self.updateFilters(response.data.links);
                        
                        // Update summary cards
                        if (response.data.totals) {
                            self.updateSummaryCards(response.data.totals);
                        }
                        
                        // Update pagination
                        if (response.data.total) {
                            self.updatePagination(response.data.total);
                        }
                        
                        resolve();
                    } else {
                        console.error('Failed to load links:', response);
                        reject(response.data);
                    }
                }).fail(function(jqXHR, textStatus, errorThrown) {
                    console.error('AJAX error:', textStatus, errorThrown);
                    $('#sky-utm-links-table tbody').html(`
                        <tr>
                            <td colspan="8" class="text-center">Error loading links. Please try again.</td>
                        </tr>
                    `);
                    reject(errorThrown);
                });
            });
        },
        
        /**
         * Update pagination UI
         */
        updatePagination: function(total) {
            const self = this;
            
            // Calculate total pages
            self.totalPages = Math.ceil(total / self.perPage);
            
            // Remove existing pagination
            $('.sky-utm-pagination').remove();
            
            // Don't show pagination if only one page
            if (self.totalPages <= 1) {
                return;
            }
            
            // Create pagination HTML
            let paginationHtml = '<div class="sky-utm-pagination tablenav"><div class="tablenav-pages">';
            paginationHtml += `<span class="displaying-num">${total} items</span>`;
            paginationHtml += '<span class="pagination-links">';
            
            // First page link
            if (self.currentPage > 1) {
                paginationHtml += `<a class="first-page button" href="#" data-page="1" title="Go to first page">«</a>`;
                paginationHtml += `<a class="prev-page button" href="#" data-page="${self.currentPage - 1}" title="Go to previous page">‹</a>`;
            } else {
                paginationHtml += '<span class="tablenav-pages-navspan button disabled" aria-hidden="true">«</span>';
                paginationHtml += '<span class="tablenav-pages-navspan button disabled" aria-hidden="true">‹</span>';
            }
            
            // Page number input
            paginationHtml += `<span class="paging-input">`;
            paginationHtml += `<label for="current-page-selector" class="screen-reader-text">Current Page</label>`;
            paginationHtml += `<input class="current-page" id="current-page-selector" type="text" name="paged" value="${self.currentPage}" size="1" aria-describedby="table-paging">`;
            paginationHtml += `<span class="tablenav-paging-text"> of <span class="total-pages">${self.totalPages}</span></span>`;
            paginationHtml += `</span>`;
            
            // Next/Last page links
            if (self.currentPage < self.totalPages) {
                paginationHtml += `<a class="next-page button" href="#" data-page="${self.currentPage + 1}" title="Go to next page">›</a>`;
                paginationHtml += `<a class="last-page button" href="#" data-page="${self.totalPages}" title="Go to last page">»</a>`;
            } else {
                paginationHtml += '<span class="tablenav-pages-navspan button disabled" aria-hidden="true">›</span>';
                paginationHtml += '<span class="tablenav-pages-navspan button disabled" aria-hidden="true">»</span>';
            }
            
            paginationHtml += '</span></div></div>';
            
            // Add pagination after the table
            $('#sky-utm-links-table').after(paginationHtml);
            
            // Handle page input
            $('#current-page-selector').on('keypress', function(e) {
                if (e.which === 13) {
                    e.preventDefault();
                    const newPage = parseInt($(this).val());
                    if (newPage > 0 && newPage <= self.totalPages && newPage !== self.currentPage) {
                        self.currentPage = newPage;
                        self.loadLinks();
                    }
                }
            });
        },
        
        /**
         * Load analytics
         */
        loadAnalytics: function(dateRange = 'last7days') {
            const self = this;
            
            const data = {
                action: 'sky_insights_get_utm_analytics',
                nonce: skyInsights.nonce,
                date_range: 'last7days', 
                date_from: '',
                date_to: ''
            };
            
            $.post(skyInsights.ajaxurl, data, function(response) {
                if (response.success) {
                    self.renderAnalytics(response.data);
                }
            });
        },

        /**
         * Handle form submission
         */
        handleFormSubmit: function() {
            const self = this;
            
            const $form = $('#sky-utm-builder-form');
            const $button = $('#sky-utm-create-button');
            const $result = $('#sky-utm-link-result');
            
            // Validate form
            if (!$form[0].checkValidity()) {
                $form[0].reportValidity();
                return;
            }
            
            // Disable button
            $button.prop('disabled', true);
            
            // Prepare data
            const data = {
                action: self.editingLinkId ? 'sky_insights_update_utm_link' : 'sky_insights_create_utm_link',
                nonce: skyInsights.nonce,
                destination: $('#utm-destination').val(),
                utm_source: $('#utm-source').val(),
                utm_medium: $('#utm-medium').val(),
                utm_campaign: $('#utm-campaign').val(),
                utm_term: $('#utm-term').val()
            };
            
            if (self.editingLinkId) {
                data.id = self.editingLinkId;
            }
            
            // Submit via AJAX
            $.post(skyInsights.ajaxurl, data, function(response) {
                if (response.success) {
                    // Show success message
                    const trackingUrl = response.data.tracking_url || 
                                      (response.data.link ? response.data.link.tracking_url : '');
                    
                    if (trackingUrl) {
                        $result.html(`
                            <div class="sky-utm-success">
                                <h4>${self.editingLinkId ? 'Link Updated!' : 'Success! Your UTM link is ready:'}</h4>
                                <div class="sky-utm-link-display">
                                    <input type="text" value="${trackingUrl}" readonly>
                                    <button class="sky-utm-copy-link" data-url="${trackingUrl}">Copy</button>
                                </div>
                            </div>
                        `).show();
                    }
                    
                    // Reset form
                    $form[0].reset();
                    
                    // Cancel edit mode
                    if (self.editingLinkId) {
                        self.cancelEdit();
                    }
                    
                    // Reset to first page and reload links
                    self.currentPage = 1;
                    self.loadLinks();
                    
                    // Hide success message after 5 seconds
                    setTimeout(() => {
                        $result.fadeOut();
                    }, 5000);
                } else {
                    // Show error
                    $result.html(`
                        <div class="sky-utm-error">
                            <p>Error: ${response.data}</p>
                        </div>
                    `).show();
                }
                
                // Re-enable button
                $button.prop('disabled', false);
            }).fail(function() {
                $result.html(`
                    <div class="sky-utm-error">
                        <p>An error occurred. Please try again.</p>
                    </div>
                `).show();
                $button.prop('disabled', false);
            });
        },

        /**
         * Edit link
         */
        editLink: function(linkId) {
            const self = this;
            const $row = $(`tr[data-link-id="${linkId}"]`);
            
            if ($row.length) {
                // Get link data from row
                const $linkCell = $row.find('.sky-utm-link-cell');
                const $paramsCell = $row.find('.sky-utm-params');
                const destination = $linkCell.find('.sky-utm-destination').data('url');
                
                // Fill form
                $('#utm-destination').val(destination);
                $('#utm-source').val($paramsCell.data('source') || '');
                $('#utm-medium').val($paramsCell.data('medium') || '');
                $('#utm-campaign').val($paramsCell.data('campaign') || '');
                $('#utm-term').val($paramsCell.data('term') || '');
                
                // Update button text and add cancel button
                $('#sky-utm-create-button').text('Update Link');
                
                if (!$('#sky-utm-cancel-edit').length) {
                    $('#sky-utm-create-button').after(`
                        <button type="button" id="sky-utm-cancel-edit" class="button button-secondary" style="margin-left: 10px;">
                            Cancel
                        </button>
                    `);
                    
                    $('#sky-utm-cancel-edit').on('click', function() {
                        self.cancelEdit();
                    });
                }
                
                // Scroll to form
                $('html, body').animate({
                    scrollTop: $('#sky-utm-builder-form').offset().top - 50
                }, 500);
                
                // Set editing mode
                self.editingLinkId = linkId;
            }
        },

        /**
         * Cancel edit mode
         */
        cancelEdit: function() {
            this.editingLinkId = null;
            $('#sky-utm-builder-form')[0].reset();
            $('#sky-utm-create-button').html('<svg width="16" height="16" viewBox="0 0 16 16" fill="none"><path d="M8 3V13M3 8H13" stroke="currentColor" stroke-width="2" stroke-linecap="round"/></svg> Create UTM Link');
            $('#sky-utm-cancel-edit').remove();
        },

        /**
         * Delete link
         */
        deleteLink: function(linkId) {
            const self = this;
            
            const data = {
                action: 'sky_insights_delete_utm_link',
                nonce: skyInsights.nonce,
                link_id: linkId
            };
            
            $.post(skyInsights.ajaxurl, data, function(response) {
                if (response.success) {
                    // Remove row with animation
                    $(`tr[data-link-id="${linkId}"]`).fadeOut(400, function() {
                        $(this).remove();
                        self.loadLinks();
                    });
                } else {
                    alert('Failed to delete link: ' + response.data);
                }
            });
        },

        /**
         * View click details
         */
        viewClickDetails: function(linkId) {
            const self = this;
            
            const data = {
                action: 'sky_insights_get_utm_click_details',
                nonce: skyInsights.nonce,
                link_id: linkId
            };
            
            $.post(skyInsights.ajaxurl, data, function(response) {
                if (response.success) {
                    self.showClickDetailsModal(response.data);
                } else {
                    alert('Failed to load click details: ' + response.data);
                }
            });
        },

        /**
         * Show click details modal
         */
        showClickDetailsModal: function(clicks) {
            const $modal = $('<div class="sky-utm-modal">');
            const $content = $('<div class="sky-utm-modal-content">');
            
            $content.html(`
                <div class="sky-utm-modal-header">
                    <h3>Click Details</h3>
                    <button class="sky-utm-modal-close">&times;</button>
                </div>
                <div class="sky-utm-modal-body">
                    <div class="sky-utm-click-details-table">
                        <table class="widefat">
                            <thead>
                                <tr>
                                    <th>Date/Time</th>
                                    <th>Source</th>
                                    <th>Browser</th>
                                    <th>Device</th>
                                    <th>Location</th>
                                    <th>Converted</th>
                                </tr>
                            </thead>
                            <tbody id="sky-utm-clicks-tbody"></tbody>
                        </table>
                    </div>
                </div>
            `);
            
            const $tbody = $content.find('#sky-utm-clicks-tbody');
            
            if (clicks && clicks.length > 0) {
                clicks.forEach(click => {
                    const deviceInfo = click.device_brand && click.device_model ? 
                        `${click.device_brand} ${click.device_model}` : 
                        (click.device_display || click.device_type || 'Unknown');
                    
                    const location = click.city && click.country ? 
                        `${click.city}, ${click.country}` : 
                        (click.country || 'Unknown');
                    
                    const converted = click.converted ? 
                        '<span style="color: #34c759;">✓ Yes</span>' : 
                        '<span style="color: #86868b;">No</span>';
                    
                    $tbody.append(`
                        <tr>
                            <td>${formatDate(click.clicked_at)}</td>
                            <td>${click.referrer || 'Direct'}</td>
                            <td>${click.browser || 'Unknown'}</td>
                            <td>${deviceInfo}</td>
                            <td>${location}</td>
                            <td>${converted}</td>
                        </tr>
                    `);
                });
            } else {
                $tbody.append('<tr><td colspan="6" class="text-center">No clicks recorded</td></tr>');
            }
            
            $modal.append($content);
            $('body').append($modal);
        },

        /**
         * Render links table - with sorting support
         */
        renderLinksTable: function(links) {
            const $tbody = $('#sky-utm-links-table tbody');
            $tbody.empty();
            
            // Reset selected links when reloading table
            this.selectedLinks = [];
            
            if (!links || links.length === 0) {
                $tbody.append(`
                    <tr>
                        <td colspan="8" class="text-center">No UTM links found. Create your first link above!</td>
                    </tr>
                `);
                return;
            }
            
            const currencySymbol = skyInsights.currency || '$';
            
            links.forEach(link => {
                const $row = $('<tr>').attr('data-link-id', link.id);
                
                // Checkbox column
                $row.append(`
                    <td class="text-center" style="width: 30px;">
                        <input type="checkbox" class="sky-utm-select-link" value="${link.id}">
                    </td>
                `);
                
                // Link/Destination
                $row.append(`
                    <td>
                        <div class="sky-utm-link-cell">
                            <div class="sky-utm-tracking-url">
                                <a href="${link.tracking_url}" target="_blank">${link.tracking_url}</a>
                                <button class="sky-utm-copy-link" data-url="${link.tracking_url}" title="Copy link">
                                    <svg width="14" height="14" viewBox="0 0 14 14" fill="none">
                                        <rect x="4" y="4" width="8" height="8" stroke="currentColor" stroke-width="1.5" rx="1"/>
                                        <path d="M2 2H8C8.55228 2 9 2.44772 9 3V9" stroke="currentColor" stroke-width="1.5"/>
                                    </svg>
                                </button>
                            </div>
                            <div class="sky-utm-destination" data-url="${link.destination_url}">
                                <span class="dashicons dashicons-admin-links"></span>
                                ${link.destination_url}
                            </div>
                        </div>
                    </td>
                `);
                
                // UTM Parameters
                $row.append(`
                    <td class="sky-utm-params" data-source="${link.utm_source || ''}" 
                        data-medium="${link.utm_medium || ''}" 
                        data-campaign="${link.utm_campaign || ''}" 
                        data-term="${link.utm_term || ''}">
                        ${link.utm_source || '-'}
                    </td>
                `);
                
                // Stats columns
                $row.append(`
                    <td class="text-center">${formatNumber(link.clicks || 0, 0)}</td>
                    <td class="text-center">${formatNumber(link.conversions || 0, 0)}</td>
                    <td class="text-center">
                        <span class="sky-utm-conversion-rate ${link.conversion_rate > 5 ? 'high' : (link.conversion_rate > 2 ? 'medium' : 'low')}">
                            ${link.conversion_rate || 0}%
                        </span>
                    </td>
                    <td class="text-right">${currencySymbol}${formatNumber(link.revenue || 0, 2)}</td>
                `);
                
                // Actions
                $row.append(`
                    <td class="text-center">
                        <button class="sky-utm-edit-link" data-id="${link.id}" title="Edit link">
                            <svg width="14" height="14" viewBox="0 0 14 14" fill="none">
                                <path d="M10.586 1.414a2 2 0 012.828 2.828l-8.793 8.793-3.647.912.912-3.647 8.7-8.886z" stroke="currentColor" stroke-width="1.5" stroke-linecap="round" stroke-linejoin="round"/>
                            </svg>
                        </button>
                        <button class="sky-utm-view-details" data-id="${link.id}" title="View click details">
                            <svg width="14" height="14" viewBox="0 0 14 14" fill="none">
                                <circle cx="7" cy="7" r="6" stroke="currentColor" stroke-width="1.5"/>
                                <path d="M7 4V7L9 9" stroke="currentColor" stroke-width="1.5" stroke-linecap="round"/>
                            </svg>
                        </button>
                        <button class="sky-utm-delete-link" data-id="${link.id}" title="Delete link">
                            <svg width="14" height="14" viewBox="0 0 14 14" fill="none">
                                <path d="M1 3H13M5 3V1H9V3M2 3V12C2 12.5523 2.44772 13 3 13H11C11.5523 13 12 12.5523 12 12V3H2Z" stroke="currentColor" stroke-width="1.5"/>
                            </svg>
                        </button>
                    </td>
                `);
                
                $tbody.append($row);
            });
            
            // Update sort indicators after rendering
            this.updateSortIndicators();
        },

        /**
         * Render analytics
         */
        renderAnalytics: async function(data) {
            // Charts only - summary cards are updated separately
            
            // Only render charts that exist in the DOM
            if (data.daily_trends && data.daily_trends.length > 0) {
                await this.renderPerformanceChart(data.daily_trends);
            }
            
            if (data.source_performance && data.source_performance.length > 0) {
                await this.renderSourceChart(data.source_performance);
            }
            
            if (data.device_breakdown && data.device_breakdown.length > 0) {
                await this.renderDeviceChart(data.device_breakdown);
            }
        },

        /**
         * Render performance chart
         */
        renderPerformanceChart: async function(trends) {
            await loadChartLibrary();
            
            const ctx = document.getElementById('sky-utm-performance-chart');
            if (!ctx) {
                console.error('Performance chart canvas not found');
                return;
            }
            
            // Destroy existing chart if it exists
            if (window.skyUTMPerformanceChart) {
                window.skyUTMPerformanceChart.destroy();
            }
            
            const labels = trends.map(d => formatDate(d.date));
            const clicksData = trends.map(d => parseInt(d.clicks) || 0);
            const conversionsData = trends.map(d => parseInt(d.conversions) || 0);
            
            window.skyUTMPerformanceChart = new Chart(ctx, {
                type: 'line',
                data: {
                    labels: labels,
                    datasets: [{
                        label: 'Clicks',
                        data: clicksData,
                        borderColor: '#007cba',
                        backgroundColor: 'rgba(0, 124, 186, 0.1)',
                        tension: 0.3,
                        fill: true
                    }, {
                        label: 'Conversions',
                        data: conversionsData,
                        borderColor: '#34c759',
                        backgroundColor: 'rgba(52, 199, 89, 0.1)',
                        tension: 0.3,
                        fill: true
                    }]
                },
                options: {
                    responsive: true,
                    maintainAspectRatio: false,
                    plugins: {
                        legend: {
                            display: true,
                            position: 'bottom'
                        }
                    },
                    scales: {
                        y: {
                            beginAtZero: true,
                            ticks: {
                                precision: 0
                            }
                        }
                    }
                }
            });
        },

        /**
         * Render source chart
         */
        renderSourceChart: async function(sources) {
            await loadChartLibrary();
            
            const ctx = document.getElementById('sky-utm-source-chart');
            if (!ctx) {
                console.error('Source chart canvas not found');
                return;
            }
            
            // Destroy existing chart if it exists
            if (window.skyUTMSourceChart) {
                window.skyUTMSourceChart.destroy();
            }
            
            const labels = sources.map(s => s.source || 'Direct');
            const data = sources.map(s => parseInt(s.clicks) || 0);
            
            window.skyUTMSourceChart = new Chart(ctx, {
                type: 'doughnut',
                data: {
                    labels: labels,
                    datasets: [{
                        data: data,
                        backgroundColor: [
                            '#007cba',
                            '#34c759',
                            '#ff9500',
                            '#ff3b30',
                            '#af52de',
                            '#5ac8fa'
                        ]
                    }]
                },
                options: {
                    responsive: true,
                    maintainAspectRatio: false,
                    plugins: {
                        legend: {
                            display: true,
                            position: 'bottom'
                        }
                    }
                }
            });
        },

        /**
         * Render device chart
         */
        renderDeviceChart: async function(devices) {
            await loadChartLibrary();
            
            const ctx = document.getElementById('sky-utm-device-chart');
            if (!ctx) {
                console.error('Device chart canvas not found');
                return;
            }
            
            // Destroy existing chart if it exists
            if (window.skyUTMDeviceChart) {
                window.skyUTMDeviceChart.destroy();
            }
            
            const labels = devices.map(d => d.device_type || 'Unknown');
            const data = devices.map(d => parseInt(d.clicks) || 0);
            
            window.skyUTMDeviceChart = new Chart(ctx, {
                type: 'bar',
                data: {
                    labels: labels,
                    datasets: [{
                        label: 'Clicks',
                        data: data,
                        backgroundColor: '#007cba'
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
                            ticks: {
                                precision: 0
                            }
                        }
                    }
                }
            });
        },

        /**
         * Update summary cards
         */
        updateSummaryCards: function(totals) {
            if (!totals) return;
            
            const currencySymbol = skyInsights.currency || '$';
            
            $('#sky-utm-total-clicks').text(formatNumber(totals.total_clicks || 0, 0));
            $('#sky-utm-total-conversions').text(formatNumber(totals.total_conversions || 0, 0));
            $('#sky-utm-conversion-rate').text((totals.conversion_rate || 0) + '%');
            $('#sky-utm-total-revenue').text(currencySymbol + formatNumber(totals.total_revenue || 0, 2));
            $('#sky-utm-avg-order-value').text(currencySymbol + formatNumber(totals.avg_order_value || 0, 2));
        },

        /**
         * Update filters - FIXED to populate dropdowns correctly
         */
        updateFilters: function(links) {
            if (!links || links.length === 0) return;
            
            // Extract unique sources and campaigns
            const sources = [...new Set(links.map(link => link.utm_source).filter(s => s))];
            const campaigns = [...new Set(links.map(link => link.utm_campaign).filter(c => c))];
            
            // Update source filter
            if (sources.length > 0) {
                const $sourceFilter = $('#sky-utm-filter-source');
                const currentSource = $sourceFilter.val();
                $sourceFilter.empty().append('<option value="">All Sources</option>');
                
                sources.sort().forEach(source => {
                    $sourceFilter.append(`<option value="${source}">${source}</option>`);
                });
                
                // Restore previous selection if it exists
                if (currentSource && sources.includes(currentSource)) {
                    $sourceFilter.val(currentSource);
                }
            }
            
            // Update campaign filter  
            if (campaigns.length > 0) {
                const $campaignFilter = $('#sky-utm-filter-campaign');
                const currentCampaign = $campaignFilter.val();
                $campaignFilter.empty().append('<option value="">All Campaigns</option>');
                
                campaigns.sort().forEach(campaign => {
                    $campaignFilter.append(`<option value="${campaign}">${campaign}</option>`);
                });
                
                // Restore previous selection if it exists
                if (currentCampaign && campaigns.includes(currentCampaign)) {
                    $campaignFilter.val(currentCampaign);
                }
            }
        }
    };

    // Initialize when document is ready
    $(document).ready(function() {
        if ($('#sky-utm-dashboard').length) {
            SkyInsights.UTM.init();
        }
    });

})(jQuery);