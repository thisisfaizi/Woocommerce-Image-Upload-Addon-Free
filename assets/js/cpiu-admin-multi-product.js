/**
 * CPIU Admin Multi-Product JavaScript
 * 
 * Handles admin interface interactions for multi-product configuration
 * 
 * @package Custom_Product_Image_Upload
 * @since 1.2.0
 */

/* global jQuery, ajaxurl, cpiu_admin */
'use strict';

jQuery(document).ready(function($) {
    
    // Initialize color pickers
    $('.cpiu-color-field').wpColorPicker();
    
    // Initialize product search dropdowns
    initializeProductSearch();
    
    // Initialize form handlers
    initializeFormHandlers();
    
    // Initialize bulk operations
    initializeBulkOperations();
    
    // Initialize tab functionality
    initializeTabs();
    
    // Initialize edit modal
    initializeEditModal();
    
    // Initialize resolution validation toggles
    initializeResolutionToggles();
    
    // Add test button for debugging
    addTestButton();
    
    /**
     * Initialize product search dropdowns
     */
    function initializeProductSearch() {
        // Check if Select2 is available
        if (typeof $.fn.select2 === 'undefined') {
            console.error('CPIU: Select2 library is not loaded. Product search functionality will be disabled.');
            return;
        }
        
        $('.cpiu-product-select').select2({
            placeholder: cpiu_admin.strings.search_placeholder,
            allowClear: true,
            minimumInputLength: 2,
            ajax: {
                url: cpiu_admin.ajax_url,
                type: 'POST',
                dataType: 'json',
                delay: 250,
                data: function(params) {
                    console.log('CPIU - Sending AJAX request with nonce:', cpiu_admin.nonce);
                    console.log('CPIU - Search term:', params.term);
                    console.log('CPIU - Page:', params.page);
                    console.log('CPIU - All params:', params);
                    
                    // Ensure search_term is not undefined or null
                    var searchTerm = params.term || '';
                    
                    var data = {
                        action: 'cpiu_search_products',
                        nonce: cpiu_admin.nonce,
                        search_term: searchTerm,
                        page: params.page || 1
                    };
                    
                    console.log('CPIU - Final data being sent:', data);
                    return data;
                },
                processResults: function(data, params) {
                    params.page = params.page || 1;
                    
                    console.log('CPIU - AJAX Response:', data);
                    console.log('CPIU - Response success:', data.success);
                    console.log('CPIU - Response data:', data.data);
                    
                    // Check if the response is successful and has the expected structure
                    if (!data.success || !data.data || !data.data.products) {
                        console.error('CPIU - Invalid response structure:', data);
                        return {
                            results: [],
                            pagination: {
                                more: false
                            }
                        };
                    }
                    
                    return {
                        results: data.data.products.map(function(product) {
                            var displayText = product.title + ' (ID: ' + product.id + ')';
                            if (product.sku) {
                                displayText += ' - SKU: ' + product.sku;
                            }
                            if (product.type) {
                                displayText += ' [' + product.type.toUpperCase() + ']';
                            }
                            
                            // Add fuzzy score indicator if available
                            if (product.fuzzy_score && product.fuzzy_score < 100) {
                                displayText += ' ~' + Math.round(product.fuzzy_score) + '%';
                            }
                            
                            return {
                                id: product.id,
                                text: displayText,
                                sku: product.sku,
                                type: product.type,
                                title: product.title,
                                fuzzy_score: product.fuzzy_score || 100
                            };
                        }),
                        pagination: {
                            more: data.data.current < data.data.pages
                        }
                    };
                },
                cache: true
            },
            templateResult: formatProductOption,
            templateSelection: formatProductSelection
        });
    }
    
    /**
     * Format product option in dropdown
     */
    function formatProductOption(product) {
        if (product.loading) {
            return product.text;
        }
        
        if (!product.id) {
            return product.text;
        }
        
        // Create a more detailed product display
        var displayText = product.title || product.text;
        var additionalInfo = [];
        
        if (product.sku) {
            additionalInfo.push('SKU: ' + product.sku);
        }
        if (product.type) {
            additionalInfo.push('Type: ' + product.type.toUpperCase());
        }
        
        var infoText = additionalInfo.length > 0 ? ' (' + additionalInfo.join(', ') + ')' : '';
        
        return $('<div class="cpiu-product-option">' +
                 '<strong>' + displayText + '</strong>' +
                 '<br><small style="color: #666;">ID: ' + product.id + infoText + '</small>' +
                 '</div>');
    }
    
    /**
     * Format selected product
     */
    function formatProductSelection(product) {
        if (product.title) {
            return product.title + ' (ID: ' + product.id + ')';
        }
        return product.text || product.id;
    }
    
    /**
     * Initialize form handlers
     */
    function initializeFormHandlers() {
        // Default settings form
        $('#cpiu-default-settings-form').on('submit', function(e) {
            e.preventDefault();
            saveDefaultSettings();
        });
        
        // Add configuration form
        $('#cpiu-add-config-form').on('submit', function(e) {
            e.preventDefault();
            addConfiguration();
        });
        
        // Bulk configuration form
        $('#cpiu-bulk-config-form').on('submit', function(e) {
            e.preventDefault();
            bulkAddConfigurations();
        });
        
        // Edit configuration buttons
        $(document).on('click', '.cpiu-edit-config', function() {
            // Check if this is lite version with disabled edit
            if ($(this).hasClass('cpiu-lite-disabled-btn')) {
                // Show the modal with upgrade overlay
                $('#cpiu-edit-modal').removeClass('cpiu-modal-hidden');
                return;
            }
            
            var productId = $(this).data('product-id');
            editConfiguration(productId);
        });
        
        // Close modal upgrade overlay
        $(document).on('click', '.cpiu-modal-close-alt', function() {
            $('#cpiu-edit-modal').addClass('cpiu-modal-hidden');
        });
        
        // Delete configuration buttons
        $(document).on('click', '.cpiu-delete-config', function() {
            var productId = $(this).data('product-id');
            deleteConfiguration(productId);
        });
        
        // Export settings button
        $('#cpiu-export-settings').on('click', function() {
            exportSettings();
        });
        
        // Import settings form
        $('#cpiu-import-form').on('submit', function(e) {
            e.preventDefault();
            importSettings();
        });
        
        // Security logs buttons
        $('#cpiu-clear-logs').on('click', function() {
            clearSecurityLogs();
        });
        
        $('#cpiu-refresh-logs').on('click', function() {
            location.reload();
        });
    }
    
    /**
     * Save default settings
     */
    function saveDefaultSettings() {
        var form = $('#cpiu-default-settings-form');
        var formData = new FormData(form[0]);
        formData.append('action', 'cpiu_save_default_settings');
        formData.append('nonce', cpiu_admin.nonce);
        
        // Get color picker value
        var colorField = $('#default_button_color');
        if (colorField.wpColorPicker) {
            formData.set('settings[button_color]', colorField.wpColorPicker('color'));
        }

        // Explicitly set nested settings fields and convert units
        formData.set('settings[image_count]', $('#default_image_count').val());
        var maxMb = parseFloat($('#default_max_file_size').val() || '1');
        var maxBytes = Math.round(maxMb * 1024 * 1024);
        formData.set('settings[max_file_size]', maxBytes);
        formData.set('settings[button_text]', $('#default_button_text').val());

        // Allowed types as array fields
        // Clear any previous nested keys
        formData.delete('settings[allowed_types]');
        form.find('input[name="allowed_types[]"]:checked').each(function() {
            formData.append('settings[allowed_types][]', $(this).val());
        });
        
        // Resolution validation settings
        formData.set('settings[resolution_validation]', form.find('input[name="resolution_validation"]').is(':checked') ? 1 : 0);
        formData.set('settings[min_width]', form.find('input[name="min_width"]').val() || 0);
        formData.set('settings[min_height]', form.find('input[name="min_height"]').val() || 0);
        formData.set('settings[max_width]', form.find('input[name="max_width"]').val() || 0);
        formData.set('settings[max_height]', form.find('input[name="max_height"]').val() || 0);
        
        $.ajax({
            url: cpiu_admin.ajax_url,
            type: 'POST',
            data: formData,
            processData: false,
            contentType: false,
            beforeSend: function() {
                form.find('button[type="submit"]').prop('disabled', true).text('Saving...');
            },
            success: function(response) {
                if (response.success) {
                    showNotice(cpiu_admin.strings.save_success, 'success');
                    // Update other forms with new default settings
                    updateOtherFormsWithDefaults(response.data.settings);
                } else {
                    showNotice(response.data.message || cpiu_admin.strings.error, 'error');
                }
            },
            error: function() {
                showNotice(cpiu_admin.strings.error, 'error');
            },
            complete: function() {
                form.find('button[type="submit"]').prop('disabled', false).text('Save Default Settings');
            }
        });
    }
    
    /**
     * Add new configuration
     */
    function addConfiguration() {
        var form = $('#cpiu-add-config-form');
        var formData = new FormData(form[0]);
        formData.append('action', 'cpiu_save_configuration');
        formData.append('nonce', cpiu_admin.nonce);
        
        // Get color picker value
        var colorField = $('#button_color');
        if (colorField.wpColorPicker) {
            formData.set('config[button_color]', colorField.wpColorPicker('color'));
        }

        // Explicitly set nested config fields and convert units
        formData.set('config[image_count]', $('#image_count').val());
        var cMaxMb = parseFloat($('#max_file_size').val() || '1');
        var cMaxBytes = Math.round(cMaxMb * 1024 * 1024);
        formData.set('config[max_file_size]', cMaxBytes);
        formData.set('config[button_text]', $('#button_text').val());
        formData.set('config[enabled]', 1);

        // Allowed types as array fields
        formData.delete('config[allowed_types]');
        form.find('input[name="allowed_types[]"]:checked').each(function() {
            formData.append('config[allowed_types][]', $(this).val());
        });
        
        $.ajax({
            url: cpiu_admin.ajax_url,
            type: 'POST',
            data: formData,
            processData: false,
            contentType: false,
            beforeSend: function() {
                form.find('button[type="submit"]').prop('disabled', true).text('Adding...');
            },
            success: function(response) {
                if (response.success) {
                    showNotice(cpiu_admin.strings.save_success, 'success');
                    form[0].reset();
                    $('#product_search').val(null).trigger('change');
                    refreshConfigurationsTable();
                } else {
                    showNotice(response.data.message || cpiu_admin.strings.error, 'error');
                }
            },
            error: function() {
                showNotice(cpiu_admin.strings.error, 'error');
            },
            complete: function() {
                form.find('button[type="submit"]').prop('disabled', false).text('Add Configuration');
            }
        });
    }
    
    /**
     * Bulk add configurations
     */
    function bulkAddConfigurations() {
        var form = $('#cpiu-bulk-config-form');
        var formData = new FormData(form[0]);
        formData.append('action', 'cpiu_bulk_save_configurations');
        formData.append('nonce', cpiu_admin.nonce);
        
        // Get selected product IDs
        var selectedProducts = $('#bulk_product_search').val();
        if (!selectedProducts || selectedProducts.length === 0) {
            showNotice(cpiu_admin.strings.select_products, 'error');
            return;
        }
        
        // Send product_ids as array
        formData.delete('product_ids');
        selectedProducts.forEach(function(id) {
            formData.append('product_ids[]', id);
        });
        
        // Get color picker value
        var colorField = $('#bulk_button_color');
        if (colorField.wpColorPicker) {
            formData.set('config[button_color]', colorField.wpColorPicker('color'));
        }
        
        // Get checkbox values
        // Explicitly set nested config fields and convert units
        formData.set('config[image_count]', $('#bulk_image_count').val());
        var bMaxMb = parseFloat($('#bulk_max_file_size').val() || '1');
        var bMaxBytes = Math.round(bMaxMb * 1024 * 1024);
        formData.set('config[max_file_size]', bMaxBytes);
        formData.set('config[button_text]', $('#bulk_button_text').val());
        formData.set('config[enabled]', 1);

        // Allowed types as array fields
        formData.delete('config[allowed_types]');
        form.find('input[name="allowed_types[]"]:checked').each(function() {
            formData.append('config[allowed_types][]', $(this).val());
        });
        
        $.ajax({
            url: cpiu_admin.ajax_url,
            type: 'POST',
            data: formData,
            processData: false,
            contentType: false,
            beforeSend: function() {
                form.find('button[type="submit"]').prop('disabled', true).text('Applying...');
            },
            success: function(response) {
                if (response.success) {
                    showNotice(cpiu_admin.strings.save_success, 'success');
                    form[0].reset();
                    $('#bulk_product_search').val(null).trigger('change');
                    refreshConfigurationsTable();
                } else {
                    showNotice(response.data.message || cpiu_admin.strings.error, 'error');
                }
            },
            error: function() {
                showNotice(cpiu_admin.strings.error, 'error');
            },
            complete: function() {
                form.find('button[type="submit"]').prop('disabled', false).text('Apply to Selected Products');
            }
        });
    }
    
    /**
     * Edit configuration
     */
    function editConfiguration(productId) {
        // Get configuration data
        var formData = new FormData();
        formData.append('action', 'cpiu_get_configuration');
        formData.append('nonce', cpiu_admin.nonce);
        formData.append('product_id', productId);
        
        $.ajax({
            url: cpiu_admin.ajax_url,
            type: 'POST',
            data: formData,
            processData: false,
            contentType: false,
            success: function(response) {
                if (response.success) {
                    populateEditModal(response.data);
                    $('#cpiu-edit-modal').removeClass('cpiu-modal-hidden').show();
                } else {
                    showNotice(response.data.message || 'Failed to load configuration.', 'error');
                }
            },
            error: function() {
                showNotice('Network error while loading configuration.', 'error');
            }
        });
    }
    
    /**
     * Populate edit modal with configuration data
     */
    function populateEditModal(data) {
        var config = data.configuration;
        
        // Set product information
        $('#edit_product_id').val(data.product_id);
        $('#edit_product_id_display').text(data.product_id);
        $('#edit_product_name').text(data.product_name);
        
        // Set form values
        $('#edit_image_count').val(config.image_count);
        $('#edit_max_file_size').val((config.max_file_size / 1024 / 1024).toFixed(1)); // Convert to MB with 1 decimal place
        $('#edit_button_text').val(config.button_text || '');
        $('#edit_button_color').val(config.button_color || '');
        $('#edit_enabled').prop('checked', config.enabled === true || config.enabled === 1);
        
        // Set allowed types checkboxes
        $('#edit_allowed_types input[type="checkbox"]').prop('checked', false);
        if (config.allowed_types && Array.isArray(config.allowed_types)) {
            config.allowed_types.forEach(function(type) {
                $('#edit_allowed_types input[value="' + type + '"]').prop('checked', true);
            });
        }
        
        // Set resolution validation settings
        $('#edit_resolution_validation').prop('checked', config.resolution_validation === true || config.resolution_validation === 1);
        $('#edit_min_width').val(config.min_width || 0);
        $('#edit_min_height').val(config.min_height || 0);
        $('#edit_max_width').val(config.max_width || 0);
        $('#edit_max_height').val(config.max_height || 0);
        
        // Show/hide resolution settings based on checkbox
        $('.edit-resolution-settings').toggle(config.resolution_validation === true || config.resolution_validation === 1);
        
        // Initialize color picker for edit modal
        if ($('#edit_button_color').hasClass('wp-color-picker')) {
            $('#edit_button_color').wpColorPicker('color', config.button_color || '#4CAF50');
        } else {
            $('#edit_button_color').wpColorPicker({
                defaultColor: config.button_color || '#4CAF50'
            });
        }
    }
    
    /**
     * Save edit configuration
     */
    function saveEditConfiguration() {
        var productId = $('#edit_product_id').val();
        var formData = new FormData();
        
        // Collect form data in the format expected by the AJAX handler
        formData.append('action', 'cpiu_save_configuration');
        formData.append('nonce', cpiu_admin.nonce);
        formData.append('product_id', productId);
        
        // Create config object with proper structure
        var config = {
            image_count: parseInt($('#edit_image_count').val(), 10),
            max_file_size: Math.round(parseFloat($('#edit_max_file_size').val()) * 1024 * 1024), // Convert MB to bytes
            button_text: $('#edit_button_text').val(),
            button_color: $('#edit_button_color').val(),
            enabled: $('#edit_enabled').is(':checked') ? 1 : 0,
            resolution_validation: $('#edit_resolution_validation').is(':checked') ? 1 : 0,
            min_width: parseInt($('#edit_min_width').val(), 10) || 0,
			min_height: parseInt($('#edit_min_height').val(), 10) || 0,
			max_width: parseInt($('#edit_max_width').val(), 10) || 0,
			max_height: parseInt($('#edit_max_height').val(), 10) || 0
        };
        
        // Collect allowed types
        var allowedTypes = [];
        $('#edit_allowed_types input[type="checkbox"]:checked').each(function() {
            allowedTypes.push($(this).val());
        });
        config.allowed_types = allowedTypes;
        
        // Append config data
        formData.append('config[image_count]', config.image_count);
        formData.append('config[max_file_size]', config.max_file_size);
        formData.append('config[button_text]', config.button_text);
        formData.append('config[button_color]', config.button_color);
        formData.append('config[enabled]', config.enabled);
        formData.append('config[resolution_validation]', config.resolution_validation);
        formData.append('config[min_width]', config.min_width);
        formData.append('config[min_height]', config.min_height);
        formData.append('config[max_width]', config.max_width);
        formData.append('config[max_height]', config.max_height);
        
        // Append allowed types as array
        formData.delete('config[allowed_types]');
        allowedTypes.forEach(function(type) {
            formData.append('config[allowed_types][]', type);
        });
        
        $.ajax({
            url: cpiu_admin.ajax_url,
            type: 'POST',
            data: formData,
            processData: false,
            contentType: false,
            success: function(response) {
                if (response.success) {
                    showNotice('Configuration updated successfully!', 'success');
                    $('#cpiu-edit-modal').hide().addClass('cpiu-modal-hidden');
                    // Refresh the configurations table
                    refreshConfigurationsTable();
                } else {
                    showNotice(response.data.message || 'Failed to update configuration.', 'error');
                }
            },
            error: function() {
                showNotice('Network error while updating configuration.', 'error');
            }
        });
    }
    
    /**
     * Delete configuration
     */
    function deleteConfiguration(productId) {
        if (!confirm(cpiu_admin.strings.confirm_delete)) {
            return;
        }
        
        var formData = new FormData();
        formData.append('action', 'cpiu_delete_configuration');
        formData.append('nonce', cpiu_admin.nonce);
        formData.append('product_id', productId);
        
        $.ajax({
            url: cpiu_admin.ajax_url,
            type: 'POST',
            data: formData,
            processData: false,
            contentType: false,
            success: function(response) {
                if (response.success) {
                    showNotice(cpiu_admin.strings.delete_success, 'success');
                    refreshConfigurationsTable();
                } else {
                    showNotice(response.data.message || cpiu_admin.strings.error, 'error');
                }
            },
            error: function() {
                showNotice(cpiu_admin.strings.error, 'error');
            }
        });
    }
    
    /**
     * Initialize bulk operations
     */
    function initializeBulkOperations() {
        // Select all checkbox
        $('#cpiu-select-all-checkbox').on('change', function() {
            $('input[name="selected_products[]"]').prop('checked', $(this).is(':checked'));
        });
        
        // Select all button
        $('#cpiu-select-all').on('click', function() {
            $('input[name="selected_products[]"]').prop('checked', true);
            $('#cpiu-select-all-checkbox').prop('checked', true);
        });
        
        // Deselect all button
        $('#cpiu-deselect-all').on('click', function() {
            $('input[name="selected_products[]"]').prop('checked', false);
            $('#cpiu-select-all-checkbox').prop('checked', false);
        });
        
        // Bulk delete button
        $('#cpiu-bulk-delete').on('click', function() {
            var selectedProducts = $('input[name="selected_products[]"]:checked').map(function() {
                return $(this).val();
            }).get();
            
            if (selectedProducts.length === 0) {
                showNotice(cpiu_admin.strings.select_products, 'error');
                return;
            }
            
            if (!confirm(cpiu_admin.strings.confirm_bulk_delete)) {
                return;
            }
            
            var formData = new FormData();
            formData.append('action', 'cpiu_bulk_delete_configurations');
            formData.append('nonce', cpiu_admin.nonce);
            formData.append('product_ids', JSON.stringify(selectedProducts));
            
            $.ajax({
                url: cpiu_admin.ajax_url,
                type: 'POST',
                data: formData,
                processData: false,
                contentType: false,
                success: function(response) {
                    if (response.success) {
                        showNotice(response.data.message, 'success');
                        refreshConfigurationsTable();
                    } else {
                        showNotice(response.data.message || cpiu_admin.strings.error, 'error');
                    }
                },
                error: function() {
                    showNotice(cpiu_admin.strings.error, 'error');
                }
            });
        });
    }
    
    /**
     * Refresh configurations table
     */
    function refreshConfigurationsTable() {
        // Reload the current page with the same tab parameter to maintain the current view
        var currentUrl = window.location.href;
        window.location.href = currentUrl;
    }
    
    /**
     * Show notice message
     */
    function showNotice(message, type) {
        var noticeClass = 'notice notice-' + type + ' is-dismissible';
        var notice = $('<div class="' + noticeClass + '"><p>' + message + '</p></div>');
        
        // Remove existing notices
        $('.notice').remove();
        
        // Add new notice below tabs
        var nav = $('.cpiu-tab-nav');
        if (nav.length) {
            nav.after(notice);
        } else {
            $('.wrap').prepend(notice);
        }
        
        // Auto-dismiss after 5 seconds
        setTimeout(function() {
            notice.fadeOut();
        }, 5000);
    }
    
    /**
     * Validate form data
     */
    function validateForm(form) {
        var isValid = true;
        var errors = [];
        
        // Check required fields
        form.find('[required]').each(function() {
            if (!$(this).val()) {
                isValid = false;
                errors.push($(this).attr('name') + ' is required');
            }
        });
        
        // Check image count
        var imageCount = form.find('input[name*="image_count"]').val();
        if (imageCount && (imageCount < 1 || imageCount > 50)) {
            isValid = false;
            errors.push('Image count must be between 1 and 50');
        }
        
        // Check file size (minimum 1MB, no maximum limit)
        var fileSize = form.find('input[name*="max_file_size"]').val();
        if (fileSize && fileSize < 1) {
            isValid = false;
            errors.push('File size must be at least 1 MB');
        }
        
        // Check allowed types
        var allowedTypes = form.find('input[name*="allowed_types[]"]:checked');
        if (allowedTypes.length === 0) {
            isValid = false;
            errors.push('At least one file type must be selected');
        }
        
        if (!isValid) {
            showNotice(errors.join(', '), 'error');
        }
        
        return isValid;
    }
    
    /**
     * Initialize edit modal functionality
     */
    function initializeEditModal() {
        // Close modal when clicking close button or outside modal
        $(document).on('click', '.cpiu-modal-close, #cpiu-cancel-edit', function() {
            $('#cpiu-edit-modal').hide().addClass('cpiu-modal-hidden');
        });
        
        // Close modal when clicking outside
        $(document).on('click', '#cpiu-edit-modal', function(e) {
            if (e.target === this) {
                $(this).hide().addClass('cpiu-modal-hidden');
            }
        });
        
        // Save edit changes
        $(document).on('click', '#cpiu-save-edit', function() {
            saveEditConfiguration();
        });
    }
    
    /**
     * Initialize tab functionality
     */
    function initializeTabs() {
        // Handle tab clicks
        $('.cpiu-tab-nav .nav-tab').on('click', function(e) {
            e.preventDefault();
            
            var targetTab = $(this).data('tab');
            var targetUrl = $(this).attr('href');
            
            // Navigate to the new URL instead of just switching tabs
            window.location.href = targetUrl;
        });
        
        // No need for hash-based tab switching since we're using proper URLs
        // The server-side PHP will handle showing the correct tab based on the URL parameter
        
        // Refresh forms with current default settings when on Add Configuration or Bulk Operations tabs
        var currentTab = cpiu_admin.current_tab;
        if (currentTab === 'add-configuration' || currentTab === 'bulk-operations') {
            refreshFormsWithCurrentDefaults();
        }
    }
    
    /**
     * Update other forms with new default settings
     */
    function updateOtherFormsWithDefaults(settings) {
        if (!settings) {
            return;
        }
        
        // Update Add Configuration form
        $('#image_count').val(settings.image_count);
        $('#max_file_size').val((settings.max_file_size / 1024 / 1024).toFixed(1));
        $('#button_text').val(settings.button_text);
        
        // Update color picker
        var addColorField = $('#button_color');
        if (addColorField.wpColorPicker) {
            addColorField.wpColorPicker('color', settings.button_color);
        } else {
            addColorField.val(settings.button_color);
        }
        
        // Update allowed types checkboxes
        $('#cpiu-add-config-form input[name="allowed_types[]"]').prop('checked', false);
        if (settings.allowed_types && Array.isArray(settings.allowed_types)) {
            settings.allowed_types.forEach(function(type) {
                $('#cpiu-add-config-form input[name="allowed_types[]"][value="' + type + '"]').prop('checked', true);
            });
        }
        
        // Update resolution validation settings
        $('#cpiu-add-config-form input[name="resolution_validation"]').prop('checked', settings.resolution_validation === 1);
            $('#cpiu-add-config-form input[name="min_width"]').val(settings.min_width || 0);
            $('#cpiu-add-config-form input[name="min_height"]').val(settings.min_height || 0);
            $('#cpiu-add-config-form input[name="max_width"]').val(settings.max_width || 0);
            $('#cpiu-add-config-form input[name="max_height"]').val(settings.max_height || 0);
            $('#cpiu-add-config-form .resolution-settings').toggle(settings.resolution_validation === 1);
        
        // Update Bulk Operations form
        $('#bulk_image_count').val(settings.image_count);
        $('#bulk_max_file_size').val((settings.max_file_size / 1024 / 1024).toFixed(1));
        $('#bulk_button_text').val(settings.button_text);
        
        // Update bulk color picker
        var bulkColorField = $('#bulk_button_color');
        if (bulkColorField.wpColorPicker) {
            bulkColorField.wpColorPicker('color', settings.button_color);
        } else {
            bulkColorField.val(settings.button_color);
        }
        
        // Update bulk allowed types checkboxes
        $('#cpiu-bulk-config-form input[name="allowed_types[]"]').prop('checked', false);
        if (settings.allowed_types && Array.isArray(settings.allowed_types)) {
            settings.allowed_types.forEach(function(type) {
                $('#cpiu-bulk-config-form input[name="allowed_types[]"][value="' + type + '"]').prop('checked', true);
            });
        }
        
        // Update bulk resolution validation settings
        $('#cpiu-bulk-config-form input[name="resolution_validation"]').prop('checked', settings.resolution_validation === 1);
            $('#cpiu-bulk-config-form input[name="min_width"]').val(settings.min_width || 0);
            $('#cpiu-bulk-config-form input[name="min_height"]').val(settings.min_height || 0);
            $('#cpiu-bulk-config-form input[name="max_width"]').val(settings.max_width || 0);
            $('#cpiu-bulk-config-form input[name="max_height"]').val(settings.max_height || 0);
            $('#cpiu-bulk-config-form .resolution-settings').toggle(settings.resolution_validation === 1);
    }
    
    /**
     * Refresh forms with current default settings from server
     */
    function refreshFormsWithCurrentDefaults() {
        // Get current default settings from the default settings form
        var currentDefaults = {
            image_count: $('#default_image_count').val(),
            max_file_size: parseFloat($('#default_max_file_size').val() || '1') * 1024 * 1024,
            button_text: $('#default_button_text').val(),
            button_color: $('#default_button_color').val(),
            allowed_types: [],
            resolution_validation: $('#cpiu-default-settings-form input[name="resolution_validation"]').is(':checked') ? 1 : 0,
            min_width: parseInt($('#cpiu-default-settings-form input[name="min_width"]').val(), 10) || 0,
		min_height: parseInt($('#cpiu-default-settings-form input[name="min_height"]').val(), 10) || 0,
		max_width: parseInt($('#cpiu-default-settings-form input[name="max_width"]').val(), 10) || 0,
		max_height: parseInt($('#cpiu-default-settings-form input[name="max_height"]').val(), 10) || 0
        };
        
        // Get checked allowed types from default form
        $('#cpiu-default-settings-form input[name="allowed_types[]"]:checked').each(function() {
            currentDefaults.allowed_types.push($(this).val());
        });
        
        // Update other forms with current defaults
        updateOtherFormsWithDefaults(currentDefaults);
    }
    
    /**
     * Export settings
     */
    function exportSettings() {
        var button = $('#cpiu-export-settings');
        var resultDiv = $('#cpiu-export-result');
        
        button.prop('disabled', true).text('Exporting...');
        resultDiv.html('');
        
        $.ajax({
            url: cpiu_admin.ajax_url,
            type: 'POST',
            data: {
                action: 'cpiu_export_settings',
                nonce: cpiu_admin.nonce
            },
            success: function(response) {
                if (response.success) {
                    // Download the file
                    var data = JSON.stringify(response.data.export_data, null, 2);
                    var blob = new Blob([data], { type: 'application/json' });
                    var url = URL.createObjectURL(blob);
                    var a = document.createElement('a');
                    a.href = url;
                    a.download = 'cpiu-settings-export-' + new Date().toISOString().split('T')[0] + '.json';
                    document.body.appendChild(a);
                    a.click();
                    document.body.removeChild(a);
                    URL.revokeObjectURL(url);
                    
                    resultDiv.html('<div class="notice notice-success"><p>Settings exported successfully!</p></div>');
                } else {
                    resultDiv.html('<div class="notice notice-error"><p>Export failed: ' + response.data.message + '</p></div>');
                }
            },
            error: function() {
                resultDiv.html('<div class="notice notice-error"><p>Export failed due to network error.</p></div>');
            },
            complete: function() {
                button.prop('disabled', false).html('<span class="dashicons dashicons-download" style="vertical-align: middle; margin-right: 5px;"></span>Export Settings');
            }
        });
    }
    
    /**
     * Import settings
     */
    function importSettings() {
        var fileInput = $('#cpiu-import-file')[0];
        var button = $('#cpiu-import-settings');
        var resultDiv = $('#cpiu-import-result');
        
        if (!fileInput.files.length) {
            resultDiv.html('<div class="notice notice-error"><p>Please select a file to import.</p></div>');
            return;
        }
        
        var file = fileInput.files[0];
        var reader = new FileReader();
        
        reader.onload = function(e) {
            try {
                var importData = JSON.parse(e.target.result);
                
                button.prop('disabled', true).text('Importing...');
                resultDiv.html('');
                
                $.ajax({
                    url: cpiu_admin.ajax_url,
                    type: 'POST',
                    data: {
                        action: 'cpiu_import_settings',
                        nonce: cpiu_admin.nonce,
                        import_data: JSON.stringify(importData)
                    },
                    success: function(response) {
                        if (response.success) {
                            resultDiv.html('<div class="notice notice-success"><p>' + response.data.message + '</p></div>');
                            // Refresh the page to show updated configurations
                            setTimeout(function() {
                                location.reload();
                            }, 2000);
                        } else {
                            resultDiv.html('<div class="notice notice-error"><p>Import failed: ' + response.data.message + '</p></div>');
                        }
                    },
                    error: function() {
                        resultDiv.html('<div class="notice notice-error"><p>Import failed due to network error.</p></div>');
                    },
                    complete: function() {
                        button.prop('disabled', false).html('<span class="dashicons dashicons-upload" style="vertical-align: middle; margin-right: 5px;"></span>Import Settings');
                    }
                });
            } catch (error) {
                resultDiv.html('<div class="notice notice-error"><p>Invalid JSON file format.</p></div>');
            }
        };
        
        reader.readAsText(file);
    }
    
    /**
     * Clear security logs
     */
    function clearSecurityLogs() {
        if (!confirm('Are you sure you want to clear all security logs? This action cannot be undone.')) {
            return;
        }
        
        $.ajax({
            url: cpiu_admin.ajax_url,
            type: 'POST',
            data: {
                action: 'cpiu_clear_security_logs',
                nonce: cpiu_admin.nonce
            },
            success: function(response) {
                if (response.success) {
                    showNotice(response.data.message, 'success');
                    // Refresh the page to show updated logs
                    setTimeout(function() {
                        location.reload();
                    }, 2000);
                } else {
                    showNotice(response.data.message || 'Failed to clear logs.', 'error');
                }
            },
            error: function() {
                showNotice('Network error while clearing logs.', 'error');
            }
        });
    }
    
    /**
     * Initialize resolution validation toggles
     */
    function initializeResolutionToggles() {
        // Toggle resolution settings visibility
        $(document).on('change', 'input[name="resolution_validation"]', function() {
            var isChecked = $(this).is(':checked');
            var settingsRows = $(this).closest('form').find('.resolution-settings');
            settingsRows.toggle(isChecked);
        });
        
        // Toggle edit modal resolution settings
        $(document).on('change', '#edit_resolution_validation', function() {
            var isChecked = $(this).is(':checked');
            $('.edit-resolution-settings').toggle(isChecked);
        });
    }
    
    /**
     * Add test button for debugging AJAX
     */
    function addTestButton() {
        // Add a test button to the page after tabs nav
        var testButton = $('<button type="button" id="cpiu-test-ajax" style="margin: 10px 0; padding: 5px 10px; background: #0073aa; color: white; border: none; border-radius: 3px; cursor: pointer;">Test AJAX Search</button>');
        var nav = $('.cpiu-tab-nav');
        if (nav.length) {
            nav.after(testButton);
        } else {
            $('.wrap').prepend(testButton);
        }
        
        testButton.on('click', function() {
            console.log('CPIU - Testing AJAX call...');
            
            $.ajax({
                url: cpiu_admin.ajax_url,
                type: 'POST',
                data: {
                    action: 'cpiu_search_products',
                    nonce: cpiu_admin.nonce,
                    search_term: 'test',
                    page: 1
                },
                success: function(response) {
                    console.log('CPIU - Test AJAX success:', response);
                    alert('AJAX Test Success: ' + JSON.stringify(response));
                },
                error: function(xhr, status, error) {
                    console.log('CPIU - Test AJAX error:', xhr, status, error);
                    alert('AJAX Test Error: ' + error);
                }
            });
        });
    }
    
    /**
     * CDN Cache Management Functions
     */
    
    // Refresh CDN cache
    $('#cpiu-refresh-cdn-cache').on('click', function() {
        var $button = $(this);
        var originalText = $button.html();
        
        $button.prop('disabled', true).html('<span class="dashicons dashicons-update"></span> Refreshing...');
        
        $.ajax({
            url: cpiu_admin.ajax_url,
            type: 'POST',
            data: {
                action: 'cpiu_refresh_cdn_cache',
                nonce: cpiu_admin.nonce
            },
            success: function(response) {
                if (response.success) {
                    showNotice(response.data.message, 'success');
                    setTimeout(function() {
                        location.reload();
                    }, 2000);
                } else {
                    showNotice(response.data.message || 'Failed to refresh cache.', 'error');
                }
            },
            error: function() {
                showNotice('Network error while refreshing cache.', 'error');
            },
            complete: function() {
                $button.prop('disabled', false).html(originalText);
            }
        });
    });
    
    // Clear CDN cache
    $('#cpiu-clear-cdn-cache').on('click', function() {
        if (!confirm('Are you sure you want to clear all CDN cache? This will force re-downloading of external resources.')) {
            return;
        }
        
        $.ajax({
            url: cpiu_admin.ajax_url,
            type: 'POST',
            data: {
                action: 'cpiu_clear_cdn_cache',
                nonce: cpiu_admin.nonce
            },
            success: function(response) {
                if (response.success) {
                    showNotice(response.data.message, 'success');
                    setTimeout(function() {
                        location.reload();
                    }, 2000);
                } else {
                    showNotice(response.data.message || cpiu_admin.strings.failed_clear_cache, 'error');
                }
            },
            error: function() {
                showNotice(cpiu_admin.strings.network_error_cache, 'error');
            }
        });
    });
    
    // Save uninstall preference
    $('#cpiu-save-uninstall-preference').on('click', function() {
        var preference = $('input[name="cpiu_uninstall_preference"]:checked').val();
        
        if (!preference) {
            showNotice(cpiu_admin.strings.select_preference, 'error');
            return;
        }
        
        $.ajax({
            url: cpiu_admin.ajax_url,
            type: 'POST',
            data: {
                action: 'cpiu_set_uninstall_preference',
                nonce: cpiu_admin.nonce,
                preference: preference
            },
            success: function(response) {
                if (response.success) {
                    showNotice(response.data.message, 'success');
                } else {
                    showNotice(response.data.message || 'Failed to save preference.', 'error');
                }
            },
            error: function() {
                showNotice('Network error while saving preference.', 'error');
            }
        });
    });
    
    // Export settings for uninstall
    $('#cpiu-export-for-uninstall').on('click', function() {
        $.ajax({
            url: cpiu_admin.ajax_url,
            type: 'POST',
            data: {
                action: 'cpiu_export_for_uninstall',
                nonce: cpiu_admin.nonce
            },
            success: function(response) {
                if (response.success) {
                    // Create and trigger download
                    var blob = new Blob([response.data.data], { type: 'application/json' });
                    var url = window.URL.createObjectURL(blob);
                    var a = document.createElement('a');
                    a.href = url;
                    a.download = response.data.filename;
                    document.body.appendChild(a);
                    a.click();
                    window.URL.revokeObjectURL(url);
                    document.body.removeChild(a);
                    
                    showNotice(response.data.message, 'success');
                } else {
                    showNotice(response.data.message || 'Failed to export settings.', 'error');
                }
            },
            error: function() {
                showNotice('Network error while exporting settings.', 'error');
            }
        });
    });
});
