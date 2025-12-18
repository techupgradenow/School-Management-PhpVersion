/**
 * Dynamic Dropdown Component - ENHANCED UI VERSION
 * EduManage Pro - School/College Management System
 *
 * FEATURES:
 * 1. Custom styled dropdown (not native select)
 * 2. Searchable options
 * 3. "+ Add New" option at the end
 * 4. Smooth animations
 * 5. Keyboard navigation
 * 6. Mobile friendly
 */

(function($) {
    'use strict';

    // Configuration - Detect correct API path based on current page location
    const currentPath = window.location.pathname;
    const isInPagesFolder = currentPath.includes('/pages/');
    const API_PATH = isInPagesFolder ? '../../backend/api' : '../backend/api';

    const CONFIG = {
        API_BASE: API_PATH,
        CACHE_KEY: 'edu_dropdown_cache_v3',
        CACHE_EXPIRY: 5 * 60 * 1000,
        INSTITUTION_KEY: 'edu_institution_type',
        ADD_NEW_VALUE: '__add_new__'
    };

    // Clear old cache
    localStorage.removeItem('edu_dropdown_cache');
    localStorage.removeItem('edu_dropdown_cache_v2');

    let memoryCache = null;
    let institutionType = 'School';
    let isInitialized = false;
    let initCallbacks = [];
    let activeDropdown = null;

    function getStoredCache() {
        try {
            const stored = localStorage.getItem(CONFIG.CACHE_KEY);
            if (stored) {
                const data = JSON.parse(stored);
                if (data.timestamp && (Date.now() - data.timestamp) < CONFIG.CACHE_EXPIRY) {
                    return data.dropdowns;
                }
            }
        } catch (e) {}
        return null;
    }

    function setStoredCache(dropdowns) {
        try {
            localStorage.setItem(CONFIG.CACHE_KEY, JSON.stringify({
                timestamp: Date.now(),
                dropdowns: dropdowns
            }));
        } catch (e) {}
    }

    function loadAllDropdowns(callback) {
        if (memoryCache) {
            if (typeof callback === 'function') callback(memoryCache);
            return;
        }

        const storedCache = getStoredCache();
        if (storedCache) {
            memoryCache = storedCache;
            if (typeof callback === 'function') callback(memoryCache);
            return;
        }

        $.ajax({
            url: CONFIG.API_BASE + '/dropdowns.php',
            type: 'GET',
            data: { action: 'all' },
            dataType: 'json',
            timeout: 10000,
            success: function(response) {
                if (response.success && response.data && response.data.dropdowns) {
                    memoryCache = response.data.dropdowns;
                    institutionType = response.data.institution_type || 'School';
                    setStoredCache(memoryCache);
                    localStorage.setItem(CONFIG.INSTITUTION_KEY, institutionType);
                    if (typeof callback === 'function') callback(memoryCache);
                } else {
                    if (typeof callback === 'function') callback({});
                }
            },
            error: function() {
                try {
                    const stored = localStorage.getItem(CONFIG.CACHE_KEY);
                    if (stored) memoryCache = JSON.parse(stored).dropdowns || {};
                } catch (e) { memoryCache = {}; }
                if (typeof callback === 'function') callback(memoryCache);
            }
        });
    }

    function getCategoryName(categoryKey) {
        if (memoryCache && memoryCache[categoryKey]) {
            return memoryCache[categoryKey].category_name || categoryKey;
        }
        return categoryKey.replace(/_/g, ' ').replace(/\b\w/g, l => l.toUpperCase());
    }

    // Close dropdown when clicking outside
    $(document).on('click', function(e) {
        if (!$(e.target).closest('.custom-dropdown').length) {
            $('.custom-dropdown').removeClass('open');
            activeDropdown = null;
        }
    });

    // Close on escape key
    $(document).on('keydown', function(e) {
        if (e.key === 'Escape' && activeDropdown) {
            $(activeDropdown).removeClass('open');
            activeDropdown = null;
        }
    });

    window.DynamicDropdown = {

        init: function(callback) {
            if (isInitialized && memoryCache) {
                if (typeof callback === 'function') callback();
                return;
            }

            if (initCallbacks.length > 0 && !isInitialized) {
                initCallbacks.push(callback);
                return;
            }

            initCallbacks.push(callback);

            loadAllDropdowns(function(cache) {
                isInitialized = true;
                $('body').attr('data-institution-type', institutionType);
                initCallbacks.forEach(function(cb) {
                    if (typeof cb === 'function') cb();
                });
                initCallbacks = [];
            });
        },

        getInstitutionType: function() {
            return institutionType || localStorage.getItem(CONFIG.INSTITUTION_KEY) || 'School';
        },

        loadAll: function(callback) {
            loadAllDropdowns(callback);
        },

        getValues: function(categoryKey, callback) {
            if (!memoryCache) {
                this.init(function() {
                    DynamicDropdown.getValues(categoryKey, callback);
                });
                return;
            }

            const categoryData = memoryCache[categoryKey];
            const values = categoryData ? categoryData.values || [] : [];

            if (typeof callback === 'function') callback(values);
            return values;
        },

        /**
         * Create custom styled dropdown
         */
        populate: function(selector, categoryKey, options) {
            options = $.extend({
                placeholder: 'Select...',
                selectedValue: '',
                showAddNew: true,
                useValueAsKey: true,
                onChange: null,
                searchable: true
            }, options);

            var $select = $(selector);
            if ($select.length === 0) return;

            if (!memoryCache) {
                this.init(function() {
                    DynamicDropdown.populate(selector, categoryKey, options);
                });
                return;
            }

            var categoryData = memoryCache[categoryKey];
            var values = categoryData ? categoryData.values || [] : [];
            var categoryName = getCategoryName(categoryKey);

            // Check if already has custom dropdown wrapper
            var $wrapper = $select.closest('.custom-dropdown');
            if ($wrapper.length === 0) {
                // Create custom dropdown structure
                $select.wrap('<div class="custom-dropdown"></div>');
                $wrapper = $select.closest('.custom-dropdown');

                // Create display element
                var $display = $('<div class="dropdown-display"><span class="dropdown-text">' + options.placeholder + '</span><i class="fas fa-chevron-down dropdown-arrow"></i></div>');
                $wrapper.prepend($display);

                // Create options container
                var $optionsContainer = $('<div class="dropdown-options-container"></div>');

                // Add search box if searchable
                if (options.searchable && values.length > 5) {
                    $optionsContainer.append('<div class="dropdown-search"><div class="dropdown-search-wrapper"><i class="fas fa-search search-icon"></i><input type="text" placeholder="Search..." class="dropdown-search-input"></div></div>');
                }

                $optionsContainer.append('<div class="dropdown-options"></div>');
                $wrapper.append($optionsContainer);

                // Hide original select
                $select.hide();
            }

            var $display = $wrapper.find('.dropdown-display');
            var $optionsList = $wrapper.find('.dropdown-options');
            var $searchInput = $wrapper.find('.dropdown-search-input');

            // Clear existing options
            $optionsList.empty();

            // Add placeholder option
            $optionsList.append('<div class="dropdown-option placeholder" data-value="">' + options.placeholder + '</div>');

            // Add options from database
            $.each(values, function(i, item) {
                var optionValue = options.useValueAsKey ? item.value : item.id;
                $optionsList.append('<div class="dropdown-option" data-value="' + optionValue + '" data-id="' + item.id + '">' + item.value + '</div>');
            });

            // Add "+ Add New" option
            if (options.showAddNew !== false) {
                $optionsList.append('<div class="dropdown-option add-new" data-value="' + CONFIG.ADD_NEW_VALUE + '"><i class="fas fa-plus-circle"></i> Add New ' + categoryName + '</div>');
            }

            // Set current value
            var currentVal = options.selectedValue || $select.val();
            if (currentVal && currentVal !== CONFIG.ADD_NEW_VALUE) {
                var $selectedOption = $optionsList.find('.dropdown-option[data-value="' + currentVal + '"]');
                if ($selectedOption.length) {
                    $wrapper.find('.dropdown-text').text($selectedOption.text());
                    $selectedOption.addClass('selected');
                    $select.val(currentVal);
                }
            }

            // Toggle dropdown
            $display.off('click').on('click', function(e) {
                e.stopPropagation();
                var isOpen = $wrapper.hasClass('open');

                // Close all other dropdowns
                $('.custom-dropdown').removeClass('open');

                if (!isOpen) {
                    $wrapper.addClass('open');
                    activeDropdown = $wrapper[0];
                    if ($searchInput.length) {
                        $searchInput.val('').focus();
                        $optionsList.find('.dropdown-option').show();
                    }
                } else {
                    activeDropdown = null;
                }
            });

            // Search functionality
            $searchInput.off('input').on('input', function() {
                var searchTerm = $(this).val().toLowerCase();
                $optionsList.find('.dropdown-option').each(function() {
                    var text = $(this).text().toLowerCase();
                    var isAddNew = $(this).hasClass('add-new');
                    var isPlaceholder = $(this).hasClass('placeholder');

                    if (isAddNew || isPlaceholder || text.includes(searchTerm)) {
                        $(this).show();
                    } else {
                        $(this).hide();
                    }
                });
            });

            // Prevent search input click from closing
            $searchInput.off('click').on('click', function(e) {
                e.stopPropagation();
            });

            // Option selection
            $optionsList.find('.dropdown-option').off('click').on('click', function(e) {
                e.stopPropagation();
                var $option = $(this);
                var value = $option.data('value');

                if (value === CONFIG.ADD_NEW_VALUE) {
                    $wrapper.removeClass('open');
                    DynamicDropdown.showAddModal(categoryKey, function(newValue) {
                        DynamicDropdown.refresh(function() {
                            DynamicDropdown.populate(selector, categoryKey, $.extend(options, {
                                selectedValue: newValue
                            }));
                            $select.trigger('change');
                        });
                    });
                    return;
                }

                // Update display
                $wrapper.find('.dropdown-text').text($option.text());
                $optionsList.find('.dropdown-option').removeClass('selected');
                $option.addClass('selected');

                // Update hidden select
                $select.val(value).trigger('change');

                // Close dropdown
                $wrapper.removeClass('open');
                activeDropdown = null;

                if (options.onChange) {
                    options.onChange(value, $select);
                }
            });

            // Store category for later use
            $wrapper.data('category', categoryKey);
            $wrapper.data('options', options);
        },

        populateMultiple: function(configs) {
            var self = this;
            if (!memoryCache) {
                this.init(function() {
                    self.populateMultiple(configs);
                });
                return;
            }
            configs.forEach(function(config) {
                self.populate(config.selector, config.category, config.options || {});
            });
        },

        showAddModal: function(categoryKey, callback) {
            var categoryName = getCategoryName(categoryKey);

            if ($('#addDropdownModal').length === 0) {
                var modalHtml = `
                    <div class="modal-overlay" id="addDropdownModal" style="position:fixed;top:0;left:0;right:0;bottom:0;background:rgba(0,0,0,0.5);display:none;align-items:center;justify-content:center;z-index:10000;">
                        <div class="modal-container" style="background:white;border-radius:16px;width:400px;max-width:90%;box-shadow:0 25px 50px rgba(0,0,0,0.25);transform:scale(0.9);opacity:0;transition:all 0.3s ease;">
                            <div class="modal-header" style="background:linear-gradient(135deg,#3b82f6,#2563eb);color:white;padding:20px 24px;border-radius:16px 16px 0 0;">
                                <h3 id="addDropdownTitle" style="margin:0;font-size:18px;font-weight:600;display:flex;align-items:center;gap:10px;">
                                    <i class="fas fa-plus-circle"></i>
                                    <span>Add New Value</span>
                                </h3>
                                <button type="button" id="closeAddDropdownModal" style="position:absolute;right:16px;top:16px;background:rgba(255,255,255,0.2);border:none;color:white;width:32px;height:32px;border-radius:50%;cursor:pointer;font-size:18px;">&times;</button>
                            </div>
                            <div class="modal-body" style="padding:24px;">
                                <form id="addDropdownForm">
                                    <input type="hidden" id="addDropdownCategory">
                                    <div style="margin-bottom:20px;">
                                        <label style="display:block;margin-bottom:8px;font-weight:600;color:#374151;">
                                            Enter New Value <span style="color:#ef4444;">*</span>
                                        </label>
                                        <input type="text" id="addDropdownValue" style="width:100%;padding:14px 16px;border:2px solid #e2e8f0;border-radius:10px;font-size:15px;box-sizing:border-box;transition:border-color 0.2s;" required placeholder="Enter value...">
                                    </div>
                                    <div style="display:flex;gap:12px;justify-content:flex-end;">
                                        <button type="button" id="cancelAddDropdown" style="padding:12px 24px;border:2px solid #e2e8f0;border-radius:10px;background:white;color:#64748b;font-weight:600;cursor:pointer;">Cancel</button>
                                        <button type="submit" style="padding:12px 24px;border:none;border-radius:10px;background:linear-gradient(135deg,#3b82f6,#2563eb);color:white;font-weight:600;cursor:pointer;">
                                            <i class="fas fa-plus"></i> Add
                                        </button>
                                    </div>
                                </form>
                            </div>
                        </div>
                    </div>
                `;
                $('body').append(modalHtml);

                $('#closeAddDropdownModal, #cancelAddDropdown').on('click', function() {
                    DynamicDropdown.hideModal();
                });

                $('#addDropdownModal').on('click', function(e) {
                    if (e.target === this) DynamicDropdown.hideModal();
                });

                $('#addDropdownValue').on('focus', function() {
                    $(this).css('border-color', '#3b82f6');
                }).on('blur', function() {
                    $(this).css('border-color', '#e2e8f0');
                });

                $('#addDropdownForm').on('submit', function(e) {
                    e.preventDefault();
                    var category = $('#addDropdownCategory').val();
                    var value = $('#addDropdownValue').val().trim();

                    if (!value) {
                        DynamicDropdown.notify('Please enter a value', 'error');
                        return;
                    }

                    var $btn = $(this).find('button[type="submit"]');
                    $btn.prop('disabled', true).html('<i class="fas fa-spinner fa-spin"></i> Saving...');

                    DynamicDropdown.addValue(category, value, function(success, newValue) {
                        $btn.prop('disabled', false).html('<i class="fas fa-plus"></i> Add');
                        if (success) {
                            DynamicDropdown.hideModal();
                            $('#addDropdownValue').val('');
                            if (typeof window.addDropdownCallback === 'function') {
                                window.addDropdownCallback(newValue);
                            }
                        }
                    });
                });
            }

            window.addDropdownCallback = callback;
            $('#addDropdownTitle span').text('Add New ' + categoryName);
            $('#addDropdownCategory').val(categoryKey);
            $('#addDropdownValue').val('').attr('placeholder', 'Enter new ' + categoryName.toLowerCase() + '...');

            $('#addDropdownModal').css('display', 'flex');
            setTimeout(function() {
                $('#addDropdownModal .modal-container').css({ 'transform': 'scale(1)', 'opacity': '1' });
                $('#addDropdownValue').focus();
            }, 10);
        },

        hideModal: function() {
            $('#addDropdownModal .modal-container').css({ 'transform': 'scale(0.9)', 'opacity': '0' });
            setTimeout(function() {
                $('#addDropdownModal').css('display', 'none');
            }, 300);
        },

        addValue: function(categoryKey, value, callback) {
            $.ajax({
                url: CONFIG.API_BASE + '/dropdowns.php',
                type: 'POST',
                contentType: 'application/json',
                data: JSON.stringify({ action: 'add_value', category_key: categoryKey, value: value }),
                dataType: 'json',
                success: function(response) {
                    if (response.success) {
                        DynamicDropdown.notify(response.message || 'Value added!', 'success');
                        DynamicDropdown.clearCache();
                        if (typeof callback === 'function') callback(true, value);
                    } else {
                        DynamicDropdown.notify(response.message || 'Error adding value', 'error');
                        if (typeof callback === 'function') callback(false);
                    }
                },
                error: function(xhr, status, error) {
                    DynamicDropdown.notify('Error: ' + error, 'error');
                    if (typeof callback === 'function') callback(false);
                }
            });
        },

        clearCache: function(categoryKey) {
            if (categoryKey && memoryCache) {
                delete memoryCache[categoryKey];
            } else {
                memoryCache = null;
                isInitialized = false;
            }
            localStorage.removeItem(CONFIG.CACHE_KEY);
        },

        refresh: function(callback) {
            this.clearCache();
            loadAllDropdowns(callback);
        },

        notify: function(message, type) {
            type = type || 'info';
            if (typeof window.notify === 'function') {
                window.notify(message, type);
                return;
            }

            $('.dropdown-notification').remove();
            var bgColor = type === 'success' ? '#dcfce7' : type === 'error' ? '#fef2f2' : '#dbeafe';
            var textColor = type === 'success' ? '#16a34a' : type === 'error' ? '#dc2626' : '#2563eb';
            var icon = type === 'success' ? 'check-circle' : type === 'error' ? 'exclamation-circle' : 'info-circle';

            var $notification = $('<div class="dropdown-notification" style="position:fixed;top:20px;right:20px;padding:14px 20px;border-radius:10px;font-size:14px;font-weight:500;z-index:10001;background:' + bgColor + ';color:' + textColor + ';box-shadow:0 4px 12px rgba(0,0,0,0.15);transform:translateX(120%);transition:transform 0.3s ease;"><i class="fas fa-' + icon + '" style="margin-right:8px;"></i>' + message + '</div>');

            $('body').append($notification);
            setTimeout(function() { $notification.css('transform', 'translateX(0)'); }, 10);
            setTimeout(function() {
                $notification.css('transform', 'translateX(120%)');
                setTimeout(function() { $notification.remove(); }, 300);
            }, 3000);
        },

        getCacheStatus: function() {
            return {
                initialized: isInitialized,
                hasMemoryCache: !!memoryCache,
                categoriesLoaded: memoryCache ? Object.keys(memoryCache).length : 0,
                institutionType: institutionType
            };
        }
    };

    // Add CSS styles
    $(document).ready(function() {
        if ($('#custom-dropdown-styles').length === 0) {
            var styles = `
                <style id="custom-dropdown-styles">
                    /* Custom Dropdown - Modern Professional Design */
                    .custom-dropdown {
                        position: relative;
                        width: 100%;
                        font-family: 'Inter', 'Segoe UI', -apple-system, BlinkMacSystemFont, sans-serif;
                    }

                    /* Main Display Box */
                    .dropdown-display {
                        display: flex;
                        align-items: center;
                        justify-content: space-between;
                        padding: 14px 18px;
                        background: #ffffff;
                        border: 2px solid #e5e7eb;
                        border-radius: 12px;
                        cursor: pointer;
                        transition: all 0.25s cubic-bezier(0.4, 0, 0.2, 1);
                        min-height: 50px;
                        box-sizing: border-box;
                    }
                    .dropdown-display:hover {
                        border-color: #d1d5db;
                        background: #fafafa;
                    }
                    .custom-dropdown.open .dropdown-display {
                        border-color: #6366f1;
                        box-shadow: 0 0 0 4px rgba(99, 102, 241, 0.12);
                        background: #ffffff;
                    }
                    .dropdown-text {
                        flex: 1;
                        color: #374151;
                        font-size: 14px;
                        font-weight: 500;
                        white-space: nowrap;
                        overflow: hidden;
                        text-overflow: ellipsis;
                    }
                    .dropdown-arrow {
                        color: #9ca3af;
                        font-size: 11px;
                        transition: transform 0.3s cubic-bezier(0.4, 0, 0.2, 1);
                        margin-left: 12px;
                    }
                    .custom-dropdown.open .dropdown-arrow {
                        transform: rotate(180deg);
                        color: #6366f1;
                    }

                    /* Options Container - Professional Popup */
                    .dropdown-options-container {
                        position: absolute;
                        top: calc(100% + 8px);
                        left: 0;
                        right: 0;
                        background: #ffffff;
                        border: 1px solid #e5e7eb;
                        border-radius: 16px;
                        box-shadow:
                            0 4px 6px -1px rgba(0, 0, 0, 0.1),
                            0 10px 15px -3px rgba(0, 0, 0, 0.1),
                            0 20px 25px -5px rgba(0, 0, 0, 0.1),
                            0 0 0 1px rgba(0, 0, 0, 0.02);
                        z-index: 9999;
                        opacity: 0;
                        visibility: hidden;
                        transform: translateY(-12px) scale(0.98);
                        transition: all 0.25s cubic-bezier(0.4, 0, 0.2, 1);
                        max-height: 360px;
                        overflow: hidden;
                        display: flex;
                        flex-direction: column;
                    }
                    .custom-dropdown.open .dropdown-options-container {
                        opacity: 1;
                        visibility: visible;
                        transform: translateY(0) scale(1);
                    }

                    /* Search Box */
                    .dropdown-search {
                        padding: 12px;
                        background: linear-gradient(180deg, #f9fafb 0%, #ffffff 100%);
                        border-bottom: 1px solid #f3f4f6;
                        flex-shrink: 0;
                    }
                    .dropdown-search-wrapper {
                        position: relative;
                        display: block;
                    }
                    .dropdown-search-input {
                        width: 100%;
                        padding: 11px 16px 11px 40px;
                        border: 2px solid #e5e7eb;
                        border-radius: 10px;
                        font-size: 14px;
                        font-weight: 500;
                        color: #374151;
                        outline: none;
                        box-sizing: border-box;
                        transition: all 0.2s ease;
                        background: #ffffff;
                    }
                    .dropdown-search-input::placeholder {
                        color: #9ca3af;
                        font-weight: 400;
                    }
                    .dropdown-search-input:focus {
                        border-color: #6366f1;
                        box-shadow: 0 0 0 3px rgba(99, 102, 241, 0.1);
                    }
                    .dropdown-search-wrapper .search-icon {
                        position: absolute;
                        left: 14px;
                        top: 50%;
                        transform: translateY(-50%);
                        color: #9ca3af;
                        font-size: 13px;
                        transition: color 0.2s;
                        pointer-events: none;
                        z-index: 2;
                    }
                    .dropdown-search-wrapper:focus-within .search-icon {
                        color: #6366f1;
                    }

                    /* Options List */
                    .dropdown-options {
                        overflow-y: auto;
                        max-height: 280px;
                        flex: 1;
                        padding: 8px 0;
                    }

                    /* Individual Option */
                    .dropdown-option {
                        padding: 12px 20px;
                        cursor: pointer;
                        transition: all 0.15s ease;
                        font-size: 14px;
                        font-weight: 500;
                        color: #4b5563;
                        display: flex;
                        align-items: center;
                        gap: 10px;
                        margin: 2px 8px;
                        border-radius: 8px;
                    }
                    .dropdown-option:hover {
                        background: #f3f4f6;
                        color: #1f2937;
                    }
                    .dropdown-option.selected {
                        background: linear-gradient(135deg, #eef2ff 0%, #e0e7ff 100%);
                        color: #4f46e5;
                        font-weight: 600;
                    }
                    .dropdown-option.selected::before {
                        content: '\\f00c';
                        font-family: 'Font Awesome 6 Free';
                        font-weight: 900;
                        font-size: 12px;
                        color: #6366f1;
                    }
                    .dropdown-option.placeholder {
                        color: #9ca3af;
                        font-style: normal;
                        font-weight: 400;
                    }

                    /* Add New Option - Special Styling */
                    .dropdown-option.add-new {
                        background: linear-gradient(135deg, #fef3c7 0%, #fde68a 100%);
                        color: #b45309;
                        font-weight: 600;
                        margin: 8px;
                        margin-top: 4px;
                        border-radius: 10px;
                        border: 2px dashed #fbbf24;
                        position: sticky;
                        bottom: 8px;
                        box-shadow: 0 2px 8px rgba(251, 191, 36, 0.3);
                    }
                    .dropdown-option.add-new:hover {
                        background: linear-gradient(135deg, #fde68a 0%, #fcd34d 100%);
                        transform: translateY(-1px);
                        box-shadow: 0 4px 12px rgba(251, 191, 36, 0.4);
                    }
                    .dropdown-option.add-new i {
                        font-size: 14px;
                    }

                    /* Empty State */
                    .dropdown-empty {
                        padding: 32px 20px;
                        text-align: center;
                        color: #9ca3af;
                    }
                    .dropdown-empty i {
                        font-size: 32px;
                        margin-bottom: 12px;
                        opacity: 0.5;
                    }

                    /* Custom Scrollbar */
                    .dropdown-options::-webkit-scrollbar {
                        width: 8px;
                    }
                    .dropdown-options::-webkit-scrollbar-track {
                        background: transparent;
                        margin: 8px 0;
                    }
                    .dropdown-options::-webkit-scrollbar-thumb {
                        background: #d1d5db;
                        border-radius: 10px;
                        border: 2px solid transparent;
                        background-clip: padding-box;
                    }
                    .dropdown-options::-webkit-scrollbar-thumb:hover {
                        background: #9ca3af;
                        border: 2px solid transparent;
                        background-clip: padding-box;
                    }

                    /* Responsive */
                    /* Toolbar & Filter Context Styles */
                    .toolbar-actions .custom-dropdown,
                    .table-toolbar .custom-dropdown {
                        width: auto;
                        min-width: 120px;
                        flex: 0 0 auto;
                    }
                    .toolbar-actions .custom-dropdown .dropdown-display,
                    .table-toolbar .custom-dropdown .dropdown-display {
                        min-height: 44px;
                        padding: 10px 14px;
                        border-radius: 10px;
                    }
                    .toolbar-actions .custom-dropdown .dropdown-options-container,
                    .table-toolbar .custom-dropdown .dropdown-options-container {
                        min-width: 180px;
                    }

                    /* Filter Group - Inline Layout Fix */
                    .filter-group {
                        display: flex !important;
                        flex-direction: row !important;
                        align-items: center !important;
                    }
                    .filter-group .custom-dropdown {
                        width: auto;
                        flex: 1;
                        min-width: 120px;
                        max-width: 250px;
                    }
                    .filter-group .custom-dropdown .dropdown-display {
                        min-height: 44px;
                        padding: 10px 16px;
                        border-radius: 10px;
                    }
                    .filter-group .custom-dropdown .dropdown-options-container {
                        min-width: 200px;
                    }

                    /* Stats Filters Row Layout */
                    .stats-filters {
                        display: flex !important;
                        flex-direction: row !important;
                        flex-wrap: wrap !important;
                        align-items: center !important;
                    }
                    .stats-filters .custom-dropdown {
                        width: auto;
                        flex: 1;
                        min-width: 120px;
                    }
                    .stats-filters .custom-dropdown .dropdown-display {
                        min-height: 44px;
                        padding: 10px 16px;
                        border-radius: 10px;
                    }
                    .stats-filters .custom-dropdown .dropdown-options-container {
                        min-width: 200px;
                    }

                    @media (max-width: 768px) {
                        .dropdown-options-container {
                            max-height: 300px;
                            border-radius: 12px;
                        }
                        .dropdown-search {
                            padding: 12px;
                        }
                        .dropdown-option {
                            padding: 14px 16px;
                        }
                        .toolbar-actions .custom-dropdown,
                        .table-toolbar .custom-dropdown {
                            min-width: 100px;
                        }
                        .stats-filters .custom-dropdown,
                        .filter-group .custom-dropdown {
                            min-width: 120px;
                        }
                    }

                    /* Animation for options appearing */
                    @keyframes dropdownOptionFade {
                        from {
                            opacity: 0;
                            transform: translateY(-4px);
                        }
                        to {
                            opacity: 1;
                            transform: translateY(0);
                        }
                    }
                    .custom-dropdown.open .dropdown-option {
                        animation: dropdownOptionFade 0.2s ease forwards;
                    }
                    .custom-dropdown.open .dropdown-option:nth-child(1) { animation-delay: 0.02s; }
                    .custom-dropdown.open .dropdown-option:nth-child(2) { animation-delay: 0.04s; }
                    .custom-dropdown.open .dropdown-option:nth-child(3) { animation-delay: 0.06s; }
                    .custom-dropdown.open .dropdown-option:nth-child(4) { animation-delay: 0.08s; }
                    .custom-dropdown.open .dropdown-option:nth-child(5) { animation-delay: 0.1s; }
                </style>
            `;
            $('head').append(styles);
        }

        // Initialize and auto-populate dropdowns
        DynamicDropdown.init(function() {
            $('select[data-dropdown]').each(function() {
                var $select = $(this);
                var category = $select.data('dropdown');
                var placeholder = $select.find('option:first').text() || 'Select...';
                var showAddNew = $select.data('show-add-new') !== false;

                DynamicDropdown.populate($select, category, {
                    placeholder: placeholder,
                    showAddNew: showAddNew
                });
            });
        });
    });

})(jQuery);
