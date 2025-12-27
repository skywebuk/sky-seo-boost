// Sky SEO Boost Admin Base JavaScript

jQuery(document).ready(function($) {
    // Client-side search filter for All Content table
    const searchInput = document.getElementById('sky-seo-search');
    if (searchInput) {
        searchInput.addEventListener('input', function() {
            const searchTerm = this.value.toLowerCase();
            const rows = document.querySelectorAll('#sky-seo-table-body tr');
            rows.forEach(row => {
                const title = row.querySelector('td:nth-child(2) a').textContent.toLowerCase();
                row.style.display = title.includes(searchTerm) ? '' : 'none';
            });
        });
    }

    // Select all checkboxes for bulk actions
    const selectAll = document.getElementById('select-all');
    if (selectAll) {
        selectAll.addEventListener('change', function() {
            const checkboxes = document.querySelectorAll('input[name="post_ids[]"]');
            checkboxes.forEach(checkbox => {
                checkbox.checked = selectAll.checked;
            });
        });
    }

    // Copy internal link suggestion URL
    const linkSuggestions = document.querySelectorAll('.sky-seo-link-suggestion');
    linkSuggestions.forEach(link => {
        link.addEventListener('click', function(e) {
            e.preventDefault();
            const url = this.dataset.url;
            navigator.clipboard.writeText(url).then(() => {
                // Create tooltip
                const tooltip = document.createElement('span');
                tooltip.className = 'sky-seo-tooltip';
                tooltip.textContent = 'Copied!';
                tooltip.style.cssText = 'position: absolute; background: #333; color: #fff; padding: 4px 8px; border-radius: 4px; font-size: 12px; z-index: 1000;';
                
                // Position tooltip
                const rect = this.getBoundingClientRect();
                tooltip.style.left = rect.left + 'px';
                tooltip.style.top = (rect.top - 30) + 'px';
                
                document.body.appendChild(tooltip);
                
                // Remove tooltip after 2 seconds
                setTimeout(() => {
                    tooltip.remove();
                }, 2000);
            }).catch(err => {
                console.error('Failed to copy link:', err);
                alert('Failed to copy link.');
            });
        });
    });

    // Table sorting for Analytics tab (base functionality)
    const sortHeaders = document.querySelectorAll('.sky-seo-table.sortable th.sort');
    sortHeaders.forEach(header => {
        header.addEventListener('click', function() {
            const table = this.closest('table');
            const tbody = table.querySelector('tbody');
            const index = Array.from(this.parentElement.children).indexOf(this);
            const isAsc = this.classList.contains('asc');
            const sortKey = this.dataset.sort;

            // Toggle sort direction
            sortHeaders.forEach(h => h.classList.remove('asc', 'desc'));
            this.classList.add(isAsc ? 'desc' : 'asc');

            const rows = Array.from(tbody.querySelectorAll('tr'));
            rows.sort((a, b) => {
                const aValue = a.children[index].dataset[sortKey] || a.children[index].textContent;
                const bValue = b.children[index].dataset[sortKey] || b.children[index].textContent;

                if (sortKey && sortKey.includes('clicks')) {
                    const aNum = parseInt(aValue) || 0;
                    const bNum = parseInt(bValue) || 0;
                    return isAsc ? bNum - aNum : aNum - bNum;
                } else {
                    const aStr = aValue.toString().toLowerCase();
                    const bStr = bValue.toString().toLowerCase();
                    return isAsc ? bStr.localeCompare(aStr) : aStr.localeCompare(bStr);
                }
            });

            tbody.innerHTML = '';
            rows.forEach(row => tbody.appendChild(row));
        });
    });

    // Simple ID validation for tracking fields
    const trackingInputs = document.querySelectorAll('input[id$="_id"], input[id$="_label"]');
    trackingInputs.forEach(input => {
        input.addEventListener('blur', function() {
            const value = this.value.trim();
            const fieldId = this.id;
            
            // Remove any existing error messages
            const existingError = this.parentElement.querySelector('.field-error');
            if (existingError) {
                existingError.remove();
            }
            
            if (value === '') return; // Empty is valid (disabled)
            
            let isValid = true;
            let errorMessage = '';
            
            // Validation patterns
            if (fieldId === 'ga_measurement_id') {
                isValid = /^G-[A-Z0-9]{10}$/.test(value);
                errorMessage = 'Invalid format. Expected: G-XXXXXXXXXX';
            } else if (fieldId === 'google_ads_conversion_id') {
                isValid = /^AW-[0-9]{9,10}$/.test(value);
                errorMessage = 'Invalid format. Expected: AW-XXXXXXXXX';
            } else if (fieldId === 'gtm_container_id') {
                isValid = /^GTM-[A-Z0-9]{6,7}$/.test(value);
                errorMessage = 'Invalid format. Expected: GTM-XXXXXXX';
            } else if (fieldId === 'meta_pixel_id') {
                isValid = /^[0-9]{15,16}$/.test(value);
                errorMessage = 'Invalid format. Expected: 15-16 digits';
            }
            
            if (!isValid && errorMessage) {
                const error = document.createElement('span');
                error.className = 'field-error';
                error.style.color = '#dc2626';
                error.style.fontSize = '0.875rem';
                error.style.display = 'block';
                error.style.marginTop = '0.25rem';
                error.textContent = errorMessage;
                this.parentElement.appendChild(error);
            }
        });
    });

    // Add copy functionality for IDs
    const addCopyButtons = () => {
        const idFields = document.querySelectorAll('input[id$="_id"], input[id$="_label"]');
        idFields.forEach(field => {
            if (field.value && !field.parentElement.querySelector('.copy-id-btn')) {
                const copyBtn = document.createElement('button');
                copyBtn.type = 'button';
                copyBtn.className = 'button copy-id-btn';
                copyBtn.textContent = 'Copy';
                copyBtn.style.marginLeft = '10px';
                copyBtn.addEventListener('click', function() {
                    navigator.clipboard.writeText(field.value).then(() => {
                        copyBtn.textContent = 'Copied!';
                        setTimeout(() => {
                            copyBtn.textContent = 'Copy';
                        }, 2000);
                    });
                });
                field.parentElement.appendChild(copyBtn);
            }
        });
    };
    
    // Run on page load and when values change
    addCopyButtons();
    document.querySelectorAll('input[id$="_id"], input[id$="_label"]').forEach(field => {
        field.addEventListener('input', addCopyButtons);
    });

    // Settings page tab persistence
    if (window.location.href.includes('page=sky-seo-settings')) {
        const tabLinks = document.querySelectorAll('.nav-tab');
        tabLinks.forEach(link => {
            link.addEventListener('click', function() {
                // Store active tab in sessionStorage
                const tabName = this.href.split('tab=')[1];
                if (tabName) {
                    sessionStorage.setItem('sky_seo_active_tab', tabName);
                }
            });
        });
    }

    // Smooth scroll to top when changing tabs
    const navTabs = document.querySelectorAll('.nav-tab');
    navTabs.forEach(tab => {
        tab.addEventListener('click', function(e) {
            if (this.classList.contains('nav-tab-active')) {
                e.preventDefault();
                window.scrollTo({ top: 0, behavior: 'smooth' });
            }
        });
    });

    // Auto-save notification for settings
    const settingsForms = document.querySelectorAll('form[action="options.php"]');
    settingsForms.forEach(form => {
        form.addEventListener('submit', function() {
            const submitBtn = this.querySelector('input[type="submit"]');
            if (submitBtn) {
                submitBtn.value = 'Saving...';
                submitBtn.disabled = true;
            }
        });
    });

    // Enhance WordPress notices
    const notices = document.querySelectorAll('.notice.is-dismissible');
    notices.forEach(notice => {
        // Auto-dismiss success notices after 5 seconds
        if (notice.classList.contains('notice-success')) {
            setTimeout(() => {
                if (notice.querySelector('.notice-dismiss')) {
                    notice.querySelector('.notice-dismiss').click();
                }
            }, 5000);
        }
    });

    // Confirm before bulk delete
    const bulkActionSelect = document.querySelector('select[name="action"]');
    if (bulkActionSelect) {
        const form = bulkActionSelect.closest('form');
        if (form) {
            form.addEventListener('submit', function(e) {
                if (bulkActionSelect.value === 'delete') {
                    const checkedBoxes = form.querySelectorAll('input[name="post_ids[]"]:checked');
                    if (checkedBoxes.length > 0) {
                        const confirmDelete = confirm(`Are you sure you want to delete ${checkedBoxes.length} item(s)? This action cannot be undone.`);
                        if (!confirmDelete) {
                            e.preventDefault();
                        }
                    }
                }
            });
        }
    }

    // Initialize tooltips for help icons
    const helpIcons = document.querySelectorAll('.sky-seo-help');
    helpIcons.forEach(icon => {
        icon.addEventListener('mouseenter', function() {
            const tooltip = this.getAttribute('data-tooltip');
            if (tooltip) {
                const tooltipEl = document.createElement('div');
                tooltipEl.className = 'sky-seo-help-tooltip';
                tooltipEl.textContent = tooltip;
                tooltipEl.style.cssText = 'position: absolute; background: #333; color: #fff; padding: 8px 12px; border-radius: 4px; font-size: 12px; z-index: 1000; max-width: 200px;';
                
                const rect = this.getBoundingClientRect();
                tooltipEl.style.left = rect.left + 'px';
                tooltipEl.style.top = (rect.bottom + 5) + 'px';
                
                document.body.appendChild(tooltipEl);
                this.tooltipElement = tooltipEl;
            }
        });
        
        icon.addEventListener('mouseleave', function() {
            if (this.tooltipElement) {
                this.tooltipElement.remove();
                delete this.tooltipElement;
            }
        });
    });
});