<?php
/**
 * Permission Guard - Backend Permission Enforcement
 * EduManage Pro - School Management System
 *
 * This module enforces strict permission checks on every API request.
 * Permissions are validated server-side - never trust frontend alone.
 */

require_once __DIR__ . '/../config/db.php';
require_once __DIR__ . '/../config/env.php';

class PermissionGuard {
    private static $instance = null;
    private $db;
    private $userId;
    private $userRole;
    private $userUuid;
    private $permissions = [];
    private $accessibleModules = [];

    // All available modules in the system
    const ALL_MODULES = [
        'dashboard', 'students', 'teachers', 'classes', 'subjects',
        'exams', 'attendance', 'fees', 'library', 'transport',
        'reports', 'settings', 'users', 'hostel', 'payroll', 'timetable',
        'syllabus'
    ];

    // All available actions
    const ALL_ACTIONS = ['view', 'create', 'edit', 'delete', 'export'];

    // Roles with full access (bypass all checks)
    const SUPER_ROLES = ['superadmin'];

    // Roles with full access to all modules
    const ADMIN_ROLES = ['admin'];

    private function __construct() {
        $this->db = getDB();
        $this->loadUserFromSession();
    }

    /**
     * Get singleton instance
     */
    public static function getInstance() {
        if (self::$instance === null) {
            self::$instance = new self();
        }
        return self::$instance;
    }

    /**
     * Load user info from session
     */
    private function loadUserFromSession() {
        if (session_status() === PHP_SESSION_NONE) {
            session_start();
        }

        $this->userId = $_SESSION['user_id'] ?? null;
        $this->userRole = strtolower($_SESSION['user_role'] ?? '');

        // Development mode: Auto-login if no session exists
        if (!$this->userId && defined('DEV_AUTO_LOGIN') && DEV_AUTO_LOGIN === true) {
            $this->devAutoLogin();
        }

        if ($this->userId) {
            // Get user UUID for permission lookup
            $stmt = $this->db->prepare("SELECT uuid FROM users WHERE id = ?");
            $stmt->execute([$this->userId]);
            $user = $stmt->fetch();
            $this->userUuid = $user['uuid'] ?? null;

            // Load permissions from database
            $this->loadPermissions();
        }
    }

    /**
     * Development auto-login - creates session for first superadmin user
     * WARNING: NEVER enable in production!
     */
    private function devAutoLogin() {
        try {
            // Find first superadmin or admin user
            $stmt = $this->db->prepare("
                SELECT id, uuid, name, username, email, role
                FROM users
                WHERE role IN ('SuperAdmin', 'superadmin', 'Admin', 'admin') AND status = 'Active'
                ORDER BY FIELD(role, 'SuperAdmin', 'superadmin', 'Admin', 'admin')
                LIMIT 1
            ");
            $stmt->execute();
            $user = $stmt->fetch();

            if ($user) {
                // Get school_id for multi-tenant support
                $schoolStmt = $this->db->prepare("SELECT id FROM schools WHERE is_active = 1 LIMIT 1");
                $schoolStmt->execute();
                $school = $schoolStmt->fetch();

                // Set session variables
                $_SESSION['user_id'] = $user['id'];
                $_SESSION['user_name'] = $user['name'];
                $_SESSION['username'] = $user['username'];
                $_SESSION['user_email'] = $user['email'];
                $_SESSION['user_role'] = $user['role'];
                $_SESSION['logged_in'] = true;
                $_SESSION['school_id'] = $school['id'] ?? null;

                // Update instance variables
                $this->userId = $user['id'];
                $this->userRole = strtolower($user['role']);
                $this->userUuid = $user['uuid'];
            }
        } catch (Exception $e) {
            // Silent fail - just continue without auto-login
        }
    }

    /**
     * Load user permissions from database
     */
    private function loadPermissions() {
        if (!$this->userUuid) return;

        // SuperAdmin has all permissions
        if (in_array($this->userRole, self::SUPER_ROLES)) {
            foreach (self::ALL_MODULES as $module) {
                $this->permissions[$module] = [
                    'view' => true,
                    'create' => true,
                    'edit' => true,
                    'delete' => true,
                    'export' => true
                ];
                $this->accessibleModules[] = $module;
            }
            return;
        }

        // Admin has all permissions
        if (in_array($this->userRole, self::ADMIN_ROLES)) {
            foreach (self::ALL_MODULES as $module) {
                $this->permissions[$module] = [
                    'view' => true,
                    'create' => true,
                    'edit' => true,
                    'delete' => true,
                    'export' => true
                ];
                $this->accessibleModules[] = $module;
            }
            return;
        }

        // Load permissions from user_permissions table
        $stmt = $this->db->prepare("
            SELECT module, can_view, can_create, can_edit, can_delete, can_export
            FROM user_permissions
            WHERE user_uuid = ?
        ");
        $stmt->execute([$this->userUuid]);
        $rows = $stmt->fetchAll();

        foreach ($rows as $row) {
            $module = $row['module'];
            $hasAnyPermission = $row['can_view'] || $row['can_create'] ||
                               $row['can_edit'] || $row['can_delete'] || $row['can_export'];

            if ($hasAnyPermission) {
                $this->permissions[$module] = [
                    'view' => (bool) $row['can_view'],
                    'create' => (bool) $row['can_create'],
                    'edit' => (bool) $row['can_edit'],
                    'delete' => (bool) $row['can_delete'],
                    'export' => (bool) $row['can_export']
                ];

                // Module is accessible if user has at least view permission
                if ($row['can_view']) {
                    $this->accessibleModules[] = $module;
                }
            }
        }
    }

    /**
     * Check if user is authenticated
     */
    public function isAuthenticated() {
        return $this->userId !== null;
    }

    /**
     * Check if user has SuperAdmin or Admin role
     */
    public function isSuperUser() {
        return in_array($this->userRole, array_merge(self::SUPER_ROLES, self::ADMIN_ROLES));
    }

    /**
     * Check if user can access a module
     * @param string $module Module ID
     * @return bool
     */
    public function canAccessModule($module) {
        // Super users can access everything
        if ($this->isSuperUser()) {
            return true;
        }

        // Dashboard is always accessible for authenticated users
        if ($module === 'dashboard') {
            return $this->isAuthenticated();
        }

        return in_array($module, $this->accessibleModules);
    }

    /**
     * Check if user has specific permission
     * @param string $module Module ID
     * @param string $action Action type (view, create, edit, delete, export)
     * @return bool
     */
    public function hasPermission($module, $action) {
        // Super users have all permissions
        if ($this->isSuperUser()) {
            return true;
        }

        // Check if module is accessible
        if (!$this->canAccessModule($module)) {
            return false;
        }

        // Check specific permission
        return isset($this->permissions[$module][$action]) &&
               $this->permissions[$module][$action] === true;
    }

    /**
     * Get all accessible modules for current user
     * @return array
     */
    public function getAccessibleModules() {
        if ($this->isSuperUser()) {
            return self::ALL_MODULES;
        }
        return $this->accessibleModules;
    }

    /**
     * Get all permissions for current user
     * @return array
     */
    public function getPermissions() {
        return $this->permissions;
    }

    /**
     * Get current user role
     * @return string
     */
    public function getUserRole() {
        return $this->userRole;
    }

    /**
     * Require authentication - sends 401 if not authenticated
     * In development mode with DEV_AUTO_LOGIN, bypasses authentication
     */
    public function requireAuth() {
        // Development mode bypass
        if (defined('DEV_AUTO_LOGIN') && DEV_AUTO_LOGIN === true && defined('APP_ENV') && APP_ENV === 'development') {
            if (!$this->isAuthenticated()) {
                // Force auto-login for development
                $this->devAutoLogin();
                $this->loadPermissions();
            }
        }

        if (!$this->isAuthenticated()) {
            $this->sendUnauthorized('Authentication required');
        }
    }

    /**
     * Require module access - sends 403 if not allowed
     * @param string $module Module ID
     */
    public function requireModuleAccess($module) {
        $this->requireAuth();

        if (!$this->canAccessModule($module)) {
            $this->sendForbidden("Access to module '$module' is not permitted");
        }
    }

    /**
     * Require specific permission - sends 403 if not allowed
     * @param string $module Module ID
     * @param string $action Action type
     */
    public function requirePermission($module, $action) {
        $this->requireAuth();

        if (!$this->hasPermission($module, $action)) {
            $this->sendForbidden("Permission '$action' on module '$module' is not granted");
        }
    }

    /**
     * Send 401 Unauthorized response
     * @param string $message Error message
     */
    private function sendUnauthorized($message) {
        http_response_code(401);
        header('Content-Type: application/json');
        echo json_encode([
            'success' => false,
            'message' => $message,
            'error_code' => 'UNAUTHORIZED'
        ]);
        exit;
    }

    /**
     * Send 403 Forbidden response
     * @param string $message Error message
     */
    private function sendForbidden($message) {
        http_response_code(403);
        header('Content-Type: application/json');
        echo json_encode([
            'success' => false,
            'message' => $message,
            'error_code' => 'FORBIDDEN'
        ]);
        exit;
    }

    /**
     * Log permission denial for security audit
     * @param string $module
     * @param string $action
     */
    public function logPermissionDenied($module, $action) {
        try {
            $stmt = $this->db->prepare("
                INSERT INTO activity_logs (user_id, action, module, details, ip_address, created_at)
                VALUES (?, 'PERMISSION_DENIED', ?, ?, ?, NOW())
            ");
            $stmt->execute([
                $this->userId,
                $module,
                json_encode(['attempted_action' => $action]),
                $_SERVER['REMOTE_ADDR'] ?? 'unknown'
            ]);
        } catch (Exception $e) {
            // Silent fail - don't break the app for logging
        }
    }
}

/**
 * Helper functions for easy access
 */

function permissionGuard() {
    return PermissionGuard::getInstance();
}

function requireAuth() {
    permissionGuard()->requireAuth();
}

function requireModuleAccess($module) {
    permissionGuard()->requireModuleAccess($module);
}

function requirePermission($module, $action) {
    permissionGuard()->requirePermission($module, $action);
}

if (!function_exists('hasPermission')) {
    function hasPermission($module, $action) {
        return permissionGuard()->hasPermission($module, $action);
    }
}

function canAccessModule($module) {
    return permissionGuard()->canAccessModule($module);
}

function getAccessibleModules() {
    return permissionGuard()->getAccessibleModules();
}

function getUserPermissions() {
    return permissionGuard()->getPermissions();
}
?>
