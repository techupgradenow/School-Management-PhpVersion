/**
 * Permission Enforcement System
 * EduManage Pro - School/College Management System
 *
 * This module controls UI visibility and access based on user permissions.
 * Permissions are loaded from localStorage after login.
 *
 * STRICT RBAC RULES:
 * 1. Module Access: Only modules selected during user creation are visible in sidebar
 * 2. Permission-Based Actions: View, Create, Edit, Delete, Export per module
 * 3. SuperAdmin/Admin: Always have full access to all modules
 * 4. UI Behavior: Hide (not disable) unauthorized elements
 * 5. Backend: Always validates - this is just frontend enhancement
 */

const PermissionManager = {
    // Current user's permissions
    permissions: {},

    // Current user's role
    userRole: '',

    // Current user info
    userInfo: null,

    // Module mapping (URL path to module ID)
    moduleMapping: {
        'dashboard': 'dashboard',
        'students': 'students',
        'teachers': 'teachers',
        'classes': 'classes',
        'subjects': 'subjects',
        'exams': 'exams',
        'attendance': 'attendance',
        'fees': 'fees',
        'library': 'library',
        'transport': 'transport',
        'reports': 'reports',
        'settings': 'settings',
        'users': 'users',
        'hostel': 'hostel',
        'payroll': 'payroll',
        'timetable': 'timetable',
        'marks-entry': 'exams',
        'reportcard': 'reports',
        'admitcard': 'exams',
        'events': 'events'
    },

    // Page to module mapping for sidebar
    pageToModule: {
        'dashboard': 'dashboard',
        'users': 'users',
        'students': 'students',
        'teachers': 'teachers',
        'attendance': 'attendance',
        'exams': 'exams',
        'admitcard': 'exams',
        'timetable': 'timetable',
        'fees': 'fees',
        'payroll': 'payroll',
        'library': 'library',
        'transport': 'transport',
        'hostel': 'hostel',
        'reports': 'reports',
        'events': 'events',
        'settings': 'settings'
    },

    /**
     * Initialize permission manager
     * Call this on every page load
     */
    init: function() {
        this.loadPermissions();
        this.applyPermissions();
        this.setupNavigationGuard();
        this.enforceModuleAccess();
        return this;
    },

    /**
     * Load permissions from localStorage
     */
    loadPermissions: function() {
        try {
            // Try multiple localStorage keys for compatibility
            let stored = localStorage.getItem('userPermissions');
            let userInfo = localStorage.getItem('userInfo');

            // Fallback to edu_user if userInfo not found
            if (!userInfo) {
                userInfo = localStorage.getItem('edu_user');
            }

            // Fallback to edu_permissions if stored not found
            if (!stored) {
                stored = localStorage.getItem('edu_permissions');
            }

            // Parse user info
            if (userInfo) {
                this.userInfo = JSON.parse(userInfo);
                this.userRole = this.userInfo.role ? this.userInfo.role.toLowerCase() : '';

                // Get permissions from user object if not stored separately
                if (!stored && this.userInfo.permissions) {
                    this.permissions = this.userInfo.permissions;
                    // Also store for future use
                    localStorage.setItem('userPermissions', JSON.stringify(this.permissions));
                }
            }

            // Parse stored permissions
            if (stored && typeof stored === 'string') {
                this.permissions = JSON.parse(stored);
            }

            // SuperAdmin and Admin have all permissions automatically
            if (this.userRole === 'superadmin' || this.userRole === 'admin') {
                this.permissions = this.getAllPermissions();
            }

            console.log('Permissions loaded:', this.permissions);
            console.log('User role:', this.userRole);
        } catch (e) {
            console.error('Error loading permissions:', e);
            this.permissions = {};
        }
    },

    /**
     * Get all permissions (for SuperAdmin/Admin)
     */
    getAllPermissions: function() {
        const modules = ['dashboard', 'students', 'teachers', 'classes', 'subjects',
                        'exams', 'attendance', 'fees', 'library', 'transport',
                        'reports', 'settings', 'users', 'hostel', 'payroll', 'timetable', 'events'];
        const perms = {};
        modules.forEach(m => {
            perms[m] = { view: true, create: true, edit: true, delete: true, export: true };
        });
        return perms;
    },

    /**
     * Check if user has permission for a module action
     * @param {string} module - Module ID (e.g., 'students', 'teachers')
     * @param {string} action - Action type: 'view', 'create', 'edit', 'delete', 'export'
     * @returns {boolean}
     */
    hasPermission: function(module, action) {
        // SuperAdmin and Admin have all permissions
        if (this.userRole === 'superadmin' || this.userRole === 'admin') {
            return true;
        }

        // Dashboard is always accessible for authenticated users
        if (module === 'dashboard' && action === 'view') {
            return true;
        }

        // First check if user can access the module at all
        if (!this.canAccessModule(module)) {
            return false;
        }

        // Check specific permissions from database
        if (this.permissions[module]) {
            return this.permissions[module][action] === true;
        }

        return false;
    },

    /**
     * Check if user can access a module at all
     * Module must have been selected during user creation AND have view permission
     * @param {string} module - Module ID
     * @returns {boolean}
     */
    canAccessModule: function(module) {
        // SuperAdmin and Admin can access all modules
        if (this.userRole === 'superadmin' || this.userRole === 'admin') {
            return true;
        }

        // Dashboard is always accessible
        if (module === 'dashboard') {
            return true;
        }

        // Check if module exists in permissions (module was selected during user creation)
        // AND user has at least view permission
        if (this.permissions[module]) {
            return this.permissions[module].view === true;
        }

        return false;
    },

    /**
     * Get list of accessible modules for current user
     * @returns {array}
     */
    getAccessibleModules: function() {
        // SuperAdmin and Admin can access all modules
        if (this.userRole === 'superadmin' || this.userRole === 'admin') {
            return Object.keys(this.moduleMapping);
        }

        // Return modules where user has view permission
        const accessible = ['dashboard']; // Dashboard always accessible
        Object.keys(this.permissions).forEach(module => {
            if (this.permissions[module] && this.permissions[module].view === true) {
                if (!accessible.includes(module)) {
                    accessible.push(module);
                }
            }
        });

        return accessible;
    },

    /**
     * Get current page's module ID
     */
    getCurrentModule: function() {
        const path = window.location.pathname;
        const page = path.split('/').pop().replace('.html', '');
        return this.moduleMapping[page] || page;
    },

    /**
     * Enforce module access - hide sidebar items and redirect if unauthorized
     */
    enforceModuleAccess: function() {
        const accessibleModules = this.getAccessibleModules();

        // Get all sidebar items with data-page attribute
        const sidebarItems = document.querySelectorAll('.sidebar-menu li[data-page]');

        sidebarItems.forEach(item => {
            const page = item.getAttribute('data-page');
            const module = this.pageToModule[page] || page;

            // Skip logout button and other non-module items
            if (!page || page === 'logout') {
                return;
            }

            // Check if user can access this module
            if (!this.canAccessModule(module)) {
                // Hide the sidebar item completely
                item.style.display = 'none';
                item.classList.add('permission-hidden');
            } else {
                // Show the sidebar item
                item.style.display = '';
                item.classList.remove('permission-hidden');
            }
        });

        // Also handle the navigation links within index.html
        document.querySelectorAll('.sidebar-nav a, .nav-menu a, [data-module]').forEach(link => {
            const href = link.getAttribute('href') || '';
            const dataModule = link.getAttribute('data-module');
            let module = dataModule;

            if (!module && href) {
                const page = href.split('/').pop().replace('.html', '');
                module = this.moduleMapping[page] || page;
            }

            if (module && !this.canAccessModule(module)) {
                link.style.display = 'none';
                link.classList.add('permission-hidden');

                // Also hide parent li if exists
                const parentLi = link.closest('li');
                if (parentLi) {
                    parentLi.style.display = 'none';
                }
            }
        });
    },

    /**
     * Apply permissions to current page
     * Hides/shows elements based on data-permission attributes
     */
    applyPermissions: function() {
        const currentModule = this.getCurrentModule();

        // Check if user can access this page
        // Only block if we have permissions loaded AND user doesn't have access
        // Don't block if permissions are empty (might still be loading)
        if (currentModule && currentModule !== 'login' && currentModule !== 'index' && currentModule !== 'dashboard') {
            // Only enforce access denial if we have permissions loaded
            const hasAnyPermissions = Object.keys(this.permissions).length > 0;
            if (hasAnyPermissions && !this.canAccessModule(currentModule)) {
                this.showAccessDenied();
                return;
            }
        }

        // Hide/show elements with data-permission attribute
        // Format: data-permission="module:action" or data-permission="action" (uses current module)
        document.querySelectorAll('[data-permission]').forEach(el => {
            const perm = el.getAttribute('data-permission');
            let module, action;

            if (perm.includes(':')) {
                [module, action] = perm.split(':');
            } else {
                module = currentModule;
                action = perm;
            }

            if (!this.hasPermission(module, action)) {
                el.style.display = 'none';
                el.classList.add('permission-hidden');
            } else {
                el.style.display = '';
                el.classList.remove('permission-hidden');
            }
        });

        // Handle elements with data-require-role attribute
        document.querySelectorAll('[data-require-role]').forEach(el => {
            const requiredRoles = el.getAttribute('data-require-role').split(',').map(r => r.trim().toLowerCase());
            if (!requiredRoles.includes(this.userRole)) {
                el.style.display = 'none';
                el.classList.add('permission-hidden');
            }
        });

        // Apply to common button patterns
        this.applyToCommonElements(currentModule);
    },

    /**
     * Apply permissions to common UI elements
     */
    applyToCommonElements: function(module) {
        // Add buttons
        document.querySelectorAll('.btn-add, .btn-create, [onclick*="showAdd"], [onclick*="Add"]').forEach(el => {
            if (!el.hasAttribute('data-permission') && !this.hasPermission(module, 'create')) {
                el.style.display = 'none';
            }
        });

        // Edit buttons
        document.querySelectorAll('.btn-edit, .action-btn.edit, [onclick*="edit"], [onclick*="Edit"]').forEach(el => {
            if (!el.hasAttribute('data-permission') && !this.hasPermission(module, 'edit')) {
                el.style.display = 'none';
            }
        });

        // Delete buttons
        document.querySelectorAll('.btn-delete, .action-btn.delete, [onclick*="delete"], [onclick*="Delete"]').forEach(el => {
            if (!el.hasAttribute('data-permission') && !this.hasPermission(module, 'delete')) {
                el.style.display = 'none';
            }
        });

        // Export buttons
        document.querySelectorAll('.btn-export, [onclick*="export"], [onclick*="Export"]').forEach(el => {
            if (!el.hasAttribute('data-permission') && !this.hasPermission(module, 'export')) {
                el.style.display = 'none';
            }
        });
    },

    /**
     * Setup navigation guard to prevent access to unauthorized pages
     */
    setupNavigationGuard: function() {
        // Update sidebar navigation
        document.querySelectorAll('.sidebar-nav a, .nav-menu a, [data-module]').forEach(link => {
            const href = link.getAttribute('href') || '';
            const dataModule = link.getAttribute('data-module');
            let module = dataModule;

            if (!module && href) {
                const page = href.split('/').pop().replace('.html', '');
                module = this.moduleMapping[page] || page;
            }

            if (module && !this.canAccessModule(module)) {
                link.style.display = 'none';
                link.classList.add('permission-hidden');

                // Also hide parent li if exists
                const parentLi = link.closest('li');
                if (parentLi) {
                    parentLi.style.display = 'none';
                }
            }
        });
    },

    /**
     * Show access denied message
     */
    showAccessDenied: function() {
        const container = document.querySelector('.main-container') ||
                         document.querySelector('.content') ||
                         document.querySelector('main') ||
                         document.body;

        container.innerHTML = `
            <div style="display: flex; flex-direction: column; align-items: center; justify-content: center; min-height: 60vh; text-align: center; padding: 40px;">
                <div style="width: 80px; height: 80px; background: #FEE2E2; border-radius: 50%; display: flex; align-items: center; justify-content: center; margin-bottom: 24px;">
                    <i class="fas fa-lock" style="font-size: 32px; color: #EF4444;"></i>
                </div>
                <h1 style="font-size: 24px; font-weight: 700; color: #0F172A; margin-bottom: 12px;">Access Denied</h1>
                <p style="font-size: 16px; color: #64748B; margin-bottom: 24px; max-width: 400px;">
                    You don't have permission to access this page. Please contact your administrator if you believe this is an error.
                </p>
                <a href="dashboard.html" style="display: inline-flex; align-items: center; gap: 8px; padding: 12px 24px; background: #2563EB; color: white; text-decoration: none; border-radius: 8px; font-weight: 500;">
                    <i class="fas fa-arrow-left"></i> Go to Dashboard
                </a>
            </div>
        `;
    },

    /**
     * Check permission before action (use in onclick handlers)
     * @param {string} module
     * @param {string} action
     * @param {string} message - Optional custom message
     * @returns {boolean}
     */
    checkPermission: function(module, action, message) {
        if (!this.hasPermission(module, action)) {
            const msg = message || `You don't have permission to ${action} in ${module}`;
            this.showPermissionError(msg);
            return false;
        }
        return true;
    },

    /**
     * Show permission error toast
     */
    showPermissionError: function(message) {
        // Try to use existing toast function
        if (typeof showToast === 'function') {
            showToast(message, 'error');
        } else {
            // Create simple toast
            const toast = document.createElement('div');
            toast.style.cssText = `
                position: fixed; top: 20px; right: 20px; z-index: 99999;
                padding: 16px 24px; background: #EF4444; color: white;
                border-radius: 8px; font-weight: 500; box-shadow: 0 4px 12px rgba(0,0,0,0.15);
                display: flex; align-items: center; gap: 12px; animation: slideIn 0.3s ease;
            `;
            toast.innerHTML = `<i class="fas fa-exclamation-circle"></i> ${message}`;
            document.body.appendChild(toast);

            setTimeout(() => toast.remove(), 3500);
        }
    },

    /**
     * Render action buttons based on permissions
     * @param {string} module - Module ID
     * @param {string} itemId - Item ID for the buttons
     * @param {object} options - Button options
     * @returns {string} HTML string
     */
    renderActionButtons: function(module, itemId, options = {}) {
        const buttons = [];

        if (this.hasPermission(module, 'view')) {
            buttons.push(`<button class="action-btn view" title="View" onclick="${options.viewFn || 'viewItem'}('${itemId}')"><i class="fas fa-eye"></i></button>`);
        }

        if (this.hasPermission(module, 'edit')) {
            buttons.push(`<button class="action-btn edit" title="Edit" onclick="${options.editFn || 'editItem'}('${itemId}')"><i class="fas fa-edit"></i></button>`);
        }

        if (this.hasPermission(module, 'delete')) {
            buttons.push(`<button class="action-btn delete" title="Delete" onclick="${options.deleteFn || 'deleteItem'}('${itemId}')"><i class="fas fa-trash"></i></button>`);
        }

        return `<div class="action-buttons">${buttons.join('')}</div>`;
    },

    /**
     * Handle API 403 errors gracefully
     * @param {Response} response - Fetch response object
     * @param {string} action - Action being performed
     */
    handleApiError: function(response, action) {
        if (response.status === 401) {
            this.showPermissionError('Session expired. Please login again.');
            setTimeout(() => {
                window.location.href = '../login.html';
            }, 2000);
            return true;
        }

        if (response.status === 403) {
            this.showPermissionError(`You don't have permission to ${action}.`);
            return true;
        }

        return false;
    },

    /**
     * Debug: Show current permissions in console
     */
    debug: function() {
        console.log('=== Permission Debug ===');
        console.log('User Role:', this.userRole);
        console.log('User Info:', this.userInfo);
        console.log('Permissions:', this.permissions);
        console.log('Current Module:', this.getCurrentModule());
        console.log('Accessible Modules:', this.getAccessibleModules());
        console.log('========================');
    }
};

// Auto-initialize when DOM is ready
document.addEventListener('DOMContentLoaded', function() {
    // Don't run on login page
    if (!window.location.pathname.includes('login')) {
        PermissionManager.init();
    }
});

// Export for use in other scripts
window.PermissionManager = PermissionManager;
