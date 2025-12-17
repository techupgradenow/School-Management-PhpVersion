/**
 * Dynamic Dropdown Component - OPTIMIZED VERSION
 * EduManage Pro - School/College Management System
 *
 * FEATURES:
 * 1. "+ Add New" option INSIDE dropdown (last item)
 * 2. NO external + button
 * 3. Single API call to load ALL dropdowns
 * 4. localStorage caching with 5-minute expiry
 * 5. Auto-select newly added value
 */

(function($) {
    'use strict';

    // Configuration
    const CONFIG = {
        API_BASE: '../backend/api',
        CACHE_KEY: 'edu_dropdown_cache',
        CACHE_EXPIRY: 5 * 60 * 1000, // 5 minutes
        INSTITUTION_KEY: 'edu_institution_type',
        ADD_NEW_VALUE: '__add_new__'
    };

    // Cache variables
    let memoryCache = null;
    let institutionType = 'School';
    let isInitialized = false;
    let initCallbacks = [];

    /**
     * Get cached data from localStorage
     */
    function getStoredCache() {
        try {
            const stored = localStorage.getItem(CONFIG.CACHE_KEY);
            if (stored) {
                const data = JSON.parse(stored);
                if (data.timestamp && (Date.now() - data.timestamp) < CONFIG.CACHE_EXPIRY) {
                    return data.dropdowns;
                }
            }
        } catch (e) {
            console.warn('Error reading dropdown cache:', e);
        }
        return null;
    }

    /**
     * Store data in localStorage
     */
    function setStoredCache(dropdowns) {
        try {
            localStorage.setItem(CONFIG.CACHE_KEY, JSON.stringify({
                timestamp: Date.now(),
                dropdowns: dropdowns
            }));
        } catch (e) {
            console.warn('Error storing dropdown cache:', e);
        }
    }

    /**
     * Load all dropdowns in a single API call
     */
    function loadAllDropdowns(callback) {
        // Check memory cache first
        if (memoryCache) {
            if (typeof callback === 'function') callback(memoryCache);
            return;
        }

        // Check localStorage cache
        const storedCache = getStoredCache();
        if (storedCache) {
            memoryCache = storedCache;
            if (typeof callback === 'function') callback(memoryCache);
            return;
        }

        // Fetch from API
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
                    console.error('Invalid dropdown response');
                    if (typeof callback === 'function') callback({});
                }
            },
            error: function(xhr, status, error) {
                console.warn('Dropdown API error, using fallback:', error);
                try {
                    const stored = localStorage.getItem(CONFIG.CACHE_KEY);
                    if (stored) {
                        memoryCache = JSON.parse(stored).dropdowns || {};
                    }
                } catch (e) {
                    memoryCache = {};
                }
                if (typeof callback === 'function') callback(memoryCache);
            }
        });
    }

    /**
     * Get category display name
     */
    function getCategoryName(categoryKey) {
        if (memoryCache && memoryCache[categoryKey]) {
            return memoryCache[categoryKey].category_name || categoryKey;
        }
        // Fallback: Convert key to readable name
        return categoryKey.replace(/_/g, ' ').replace(/\b\w/g, l => l.toUpperCase());
    }

    /**
     * Main DynamicDropdown API
     */
    window.DynamicDropdown = {

        /**
         * Initialize - loads all dropdowns once
         */
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

        /**
         * Get institution type
         */
        getInstitutionType: function() {
            return institutionType || localStorage.getItem(CONFIG.INSTITUTION_KEY) || 'School';
        },

        /**
         * Load all dropdowns
         */
        loadAll: function(callback) {
            loadAllDropdowns(callback);
        },

        /**
         * Get values for a category
         */
        getValues: function(categoryKey, callback) {
            if (!memoryCache) {
                this.init(function() {
                    DynamicDropdown.getValues(categoryKey, callback);
                });
                return;
            }

            const categoryData = memoryCache[categoryKey];
            const values = categoryData ? categoryData.values || [] : [];

            if (typeof callback === 'function') {
                callback(values);
            }
            return values;
        },

        /**
         * Populate a select element with "+ Add New" as last option
         */
        populate: function(selector, categoryKey, options) {
            options = $.extend({
                placeholder: 'Select...',
                selectedValue: '',
                showAddNew: true,  // Show "+ Add New" inside dropdown
                useValueAsKey: false,
                onChange: null
            }, options);

            var $select = $(selector);
            if ($select.length === 0) return;

            // Ensure initialized
            if (!memoryCache) {
                this.init(function() {
                    DynamicDropdown.populate(selector, categoryKey, options);
                });
                return;
            }

            var categoryData = memoryCache[categoryKey];
            var values = categoryData ? categoryData.values || [] : [];
            var categoryName = getCategoryName(categoryKey);

            // Store current value before clearing
            var currentValue = $select.val();

            $select.empty();

            // Add placeholder
            if (options.placeholder) {
                $select.append($('<option>', {
                    value: '',
                    text: options.placeholder
                }));
            }

            // Add values from database
            $.each(values, function(i, item) {
                var optionValue = options.useValueAsKey ? item.value : item.id;
                $select.append($('<option>', {
                    value: optionValue,
                    text: item.value,
                    'data-id': item.id
                }));
            });

            // Add "+ Add New" option at the end (INSIDE dropdown)
            if (options.showAddNew !== false) {
                $select.append($('<option>', {
                    value: CONFIG.ADD_NEW_VALUE,
                    text: '+ Add New ' + categoryName,
                    'class': 'add-new-option',
                    'data-category': categoryKey
                }));
            }

            // Set selected value
            if (options.selectedValue) {
                $select.val(options.selectedValue);
            } else if (currentValue && currentValue !== CONFIG.ADD_NEW_VALUE) {
                $select.val(currentValue);
            }

            // Remove any existing handler and add new one
            $select.off('change.dynamicDropdown').on('change.dynamicDropdown', function() {
                var val = $(this).val();
                var $this = $(this);

                // Check if "+ Add New" was selected
                if (val === CONFIG.ADD_NEW_VALUE) {
                    // Reset to placeholder while modal is open
                    $this.val('');

                    // Show add modal
                    DynamicDropdown.showAddModal(categoryKey, function(newValue) {
                        // After adding, refresh and select the new value
                        DynamicDropdown.refresh(function() {
                            DynamicDropdown.populate(selector, categoryKey, $.extend(options, {
                                selectedValue: newValue
                            }));
                            // Trigger change event for any listeners
                            $this.trigger('change');
                        });
                    });
                } else if (options.onChange) {
                    options.onChange(val, $this);
                }
            });
        },

        /**
         * Populate multiple dropdowns at once
         */
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

        /**
         * Show add new modal
         */
        showAddModal: function(categoryKey, callback) {
            var categoryName = getCategoryName(categoryKey);

            // Create modal if not exists
            if ($('#addDropdownModal').length === 0) {
                var modalHtml = `
                    <div class="modal-overlay" id="addDropdownModal">
                        <div class="modal-container" style="max-width: 420px;">
                            <div class="modal-header" style="background: linear-gradient(135deg, #f59e0b, #d97706); color: white; border-radius: 16px 16px 0 0;">
                                <h3 id="addDropdownTitle" style="margin: 0; font-size: 18px; font-weight: 600;">
                                    <i class="fas fa-plus-circle" style="margin-right: 8px;"></i>
                                    Add New Value
                                </h3>
                                <button type="button" class="modal-close" id="closeAddDropdownModal" style="background: rgba(255,255,255,0.2); border: none; color: white; width: 32px; height: 32px; border-radius: 50%; cursor: pointer; font-size: 18px;">&times;</button>
                            </div>
                            <div class="modal-body" style="padding: 24px;">
                                <form id="addDropdownForm">
                                    <input type="hidden" id="addDropdownCategory" />
                                    <div class="form-group" style="margin-bottom: 20px;">
                                        <label for="addDropdownValue" style="display: block; margin-bottom: 8px; font-weight: 600; color: #374151;">
                                            Enter New Value <span style="color: #ef4444;">*</span>
                                        </label>
                                        <input type="text" id="addDropdownValue"
                                            style="width: 100%; padding: 14px 16px; border: 2px solid #e2e8f0; border-radius: 10px; font-size: 15px; transition: border-color 0.2s; box-sizing: border-box;"
                                            required placeholder="Enter value..." />
                                    </div>
                                    <div class="form-actions" style="display: flex; gap: 12px; justify-content: flex-end;">
                                        <button type="button" id="cancelAddDropdown"
                                            style="padding: 12px 24px; border: 2px solid #e2e8f0; border-radius: 10px; background: white; color: #64748b; font-weight: 600; cursor: pointer; transition: all 0.2s;">
                                            Cancel
                                        </button>
                                        <button type="submit"
                                            style="padding: 12px 24px; border: none; border-radius: 10px; background: linear-gradient(135deg, #f59e0b, #d97706); color: white; font-weight: 600; cursor: pointer; transition: all 0.2s;">
                                            <i class="fas fa-plus"></i> Add
                                        </button>
                                    </div>
                                </form>
                            </div>
                        </div>
                    </div>
                `;
                $('body').append(modalHtml);

                // Style the modal overlay
                $('#addDropdownModal').css({
                    'position': 'fixed',
                    'top': '0',
                    'left': '0',
                    'right': '0',
                    'bottom': '0',
                    'background': 'rgba(0,0,0,0.5)',
                    'display': 'none',
                    'align-items': 'center',
                    'justify-content': 'center',
                    'z-index': '10000'
                });

                $('#addDropdownModal .modal-container').css({
                    'background': 'white',
                    'border-radius': '16px',
                    'box-shadow': '0 25px 50px rgba(0,0,0,0.25)',
                    'transform': 'scale(0.9)',
                    'opacity': '0',
                    'transition': 'all 0.3s ease'
                });

                // Bind events
                $('#closeAddDropdownModal, #cancelAddDropdown').on('click', function() {
                    DynamicDropdown.hideModal();
                });

                $('#addDropdownModal').on('click', function(e) {
                    if (e.target === this) {
                        DynamicDropdown.hideModal();
                    }
                });

                // Handle input focus styling
                $('#addDropdownValue').on('focus', function() {
                    $(this).css('border-color', '#f59e0b');
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

                    // Disable button while saving
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

            // Store callback
            window.addDropdownCallback = callback;

            // Update modal content
            $('#addDropdownTitle').html('<i class="fas fa-plus-circle" style="margin-right: 8px;"></i> Add New ' + categoryName);
            $('#addDropdownCategory').val(categoryKey);
            $('#addDropdownValue').val('').attr('placeholder', 'Enter new ' + categoryName.toLowerCase() + '...');

            // Show modal with animation
            $('#addDropdownModal').css('display', 'flex');
            setTimeout(function() {
                $('#addDropdownModal .modal-container').css({
                    'transform': 'scale(1)',
                    'opacity': '1'
                });
                $('#addDropdownValue').focus();
            }, 10);
        },

        /**
         * Hide modal
         */
        hideModal: function() {
            $('#addDropdownModal .modal-container').css({
                'transform': 'scale(0.9)',
                'opacity': '0'
            });
            setTimeout(function() {
                $('#addDropdownModal').css('display', 'none');
            }, 300);
        },

        /**
         * Add new value to database
         */
        addValue: function(categoryKey, value, callback) {
            $.ajax({
                url: CONFIG.API_BASE + '/dropdowns.php',
                type: 'POST',
                contentType: 'application/json',
                data: JSON.stringify({
                    action: 'add_value',
                    category_key: categoryKey,
                    value: value
                }),
                dataType: 'json',
                success: function(response) {
                    if (response.success) {
                        DynamicDropdown.notify(response.message || 'Value added successfully!', 'success');
                        DynamicDropdown.clearCache();
                        if (typeof callback === 'function') {
                            callback(true, value);
                        }
                    } else {
                        DynamicDropdown.notify(response.message || 'Error adding value', 'error');
                        if (typeof callback === 'function') {
                            callback(false);
                        }
                    }
                },
                error: function(xhr, status, error) {
                    DynamicDropdown.notify('Error adding value: ' + error, 'error');
                    if (typeof callback === 'function') {
                        callback(false);
                    }
                }
            });
        },

        /**
         * Clear cache
         */
        clearCache: function(categoryKey) {
            if (categoryKey && memoryCache) {
                delete memoryCache[categoryKey];
            } else {
                memoryCache = null;
                isInitialized = false;
            }
            localStorage.removeItem(CONFIG.CACHE_KEY);
        },

        /**
         * Force refresh all data
         */
        refresh: function(callback) {
            this.clearCache();
            loadAllDropdowns(callback);
        },

        /**
         * Show notification
         */
        notify: function(message, type) {
            type = type || 'info';

            if (typeof window.notify === 'function') {
                window.notify(message, type);
                return;
            }

            // Remove existing notifications
            $('.dropdown-notification').remove();

            var bgColor = type === 'success' ? '#dcfce7' : type === 'error' ? '#fef2f2' : '#dbeafe';
            var textColor = type === 'success' ? '#16a34a' : type === 'error' ? '#dc2626' : '#2563eb';
            var borderColor = type === 'success' ? '#22c55e' : type === 'error' ? '#ef4444' : '#3b82f6';
            var icon = type === 'success' ? 'check-circle' : type === 'error' ? 'exclamation-circle' : 'info-circle';

            var $notification = $(`
                <div class="dropdown-notification" style="
                    position: fixed;
                    top: 20px;
                    right: 20px;
                    padding: 14px 20px;
                    border-radius: 10px;
                    font-size: 14px;
                    font-weight: 500;
                    z-index: 10001;
                    background: ${bgColor};
                    color: ${textColor};
                    border: 1px solid ${borderColor};
                    box-shadow: 0 4px 12px rgba(0,0,0,0.15);
                    transform: translateX(120%);
                    transition: transform 0.3s ease;
                ">
                    <i class="fas fa-${icon}" style="margin-right: 8px;"></i>
                    ${message}
                </div>
            `);

            $('body').append($notification);

            setTimeout(function() {
                $notification.css('transform', 'translateX(0)');
            }, 10);

            setTimeout(function() {
                $notification.css('transform', 'translateX(120%)');
                setTimeout(function() {
                    $notification.remove();
                }, 300);
            }, 3000);
        },

        /**
         * Get cache status (for debugging)
         */
        getCacheStatus: function() {
            return {
                initialized: isInitialized,
                hasMemoryCache: !!memoryCache,
                categoriesLoaded: memoryCache ? Object.keys(memoryCache).length : 0,
                institutionType: institutionType
            };
        }
    };

    // Auto-initialize on document ready
    $(document).ready(function() {
        // Add CSS for "+ Add New" option styling
        if ($('#dynamic-dropdown-styles').length === 0) {
            var styles = `
                <style id="dynamic-dropdown-styles">
                    /* Style for "+ Add New" option inside dropdown */
                    select option.add-new-option,
                    select option[value="__add_new__"] {
                        color: #f59e0b !important;
                        font-weight: 600 !important;
                        background: #fffbeb !important;
                        border-top: 1px solid #fcd34d;
                    }

                    /* Hover effect for select */
                    select:focus {
                        border-color: #f59e0b !important;
                        outline: none;
                        box-shadow: 0 0 0 3px rgba(245, 158, 11, 0.1);
                    }

                    /* Modern select styling */
                    select[data-dropdown] {
                        appearance: none;
                        -webkit-appearance: none;
                        -moz-appearance: none;
                        background-image: url("data:image/svg+xml,%3Csvg xmlns='http://www.w3.org/2000/svg' width='12' height='12' viewBox='0 0 12 12'%3E%3Cpath fill='%2364748b' d='M6 8L1 3h10z'/%3E%3C/svg%3E");
                        background-repeat: no-repeat;
                        background-position: right 12px center;
                        padding-right: 36px !important;
                        cursor: pointer;
                    }
                </style>
            `;
            $('head').append(styles);
        }

        // Pre-load all dropdowns on page load
        DynamicDropdown.init(function() {
            // Auto-populate any select with data-dropdown attribute
            $('select[data-dropdown]').each(function() {
                var $select = $(this);
                var category = $select.data('dropdown');
                var placeholder = $select.find('option:first').text() || 'Select...';
                var showAddNew = $select.data('show-add-new') !== false; // Default true

                DynamicDropdown.populate($select, category, {
                    placeholder: placeholder,
                    showAddNew: showAddNew
                });
            });
        });
    });

})(jQuery);
