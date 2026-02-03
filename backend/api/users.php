<?php
/**
 * User Management API
 * EduManage Pro - School/College Management System
 *
 * Handles CRUD operations for users, roles, and permissions
 * Protected by Permission Guard - enforces strict RBAC
 *
 * Architecture:
 * - Users identified internally by UUID (never exposed)
 * - Display ID (USR001, etc.) used in all API responses
 * - Permissions stored in separate user_permissions table
 * - Foreign key CASCADE ensures data integrity
 */

header('Content-Type: application/json');
header('Access-Control-Allow-Origin: *');
header('Access-Control-Allow-Methods: GET, POST, PUT, DELETE, OPTIONS');
header('Access-Control-Allow-Headers: Content-Type');

if ($_SERVER['REQUEST_METHOD'] === 'OPTIONS') {
    exit(0);
}

// Include helpers
require_once __DIR__ . '/../helpers/cache.php';
require_once __DIR__ . '/../helpers/permission_guard.php';

// Include centralized environment configuration (Remote Hostinger DB only)
require_once __DIR__ . '/../config/env.php';

// Start session for permission checks
if (session_status() === PHP_SESSION_NONE) {
    session_start();
}

// Validate we're using remote database (blocks localhost)
validateRemoteDatabase();

try {
    $pdo = new PDO(
        getDbDsn(),
        DB_USER,
        DB_PASS,
        [
            PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION,
            PDO::ATTR_DEFAULT_FETCH_MODE => PDO::FETCH_ASSOC,
            PDO::ATTR_EMULATE_PREPARES => false,
            PDO::ATTR_TIMEOUT => DB_CONNECT_TIMEOUT
        ]
    );
} catch (PDOException $e) {
    http_response_code(500);
    echo json_encode(['success' => false, 'message' => 'Database connection failed']);
    exit;
}

// Get action from request
$action = '';
if ($_SERVER['REQUEST_METHOD'] === 'GET') {
    $action = $_GET['action'] ?? 'list';
} else {
    $input = json_decode(file_get_contents('php://input'), true);
    $action = $input['action'] ?? '';
}

// Require authentication and module access for ALL requests
requireAuth();
requireModuleAccess('users');

// Route to appropriate handler with permission checks
switch ($action) {
    case 'list':
        requirePermission('users', 'view');
        listUsers($pdo);
        break;
    case 'get':
        requirePermission('users', 'view');
        getUser($pdo, $_GET['id'] ?? ($input['id'] ?? ''));
        break;
    case 'create':
        requirePermission('users', 'create');
        createUser($pdo, $input);
        break;
    case 'update':
        requirePermission('users', 'edit');
        updateUser($pdo, $input);
        break;
    case 'update_status':
        requirePermission('users', 'edit');
        updateUserStatus($pdo, $input);
        break;
    case 'reset_password':
        requirePermission('users', 'edit');
        resetPassword($pdo, $input);
        break;
    case 'delete':
        requirePermission('users', 'delete');
        deleteUser($pdo, $input);
        break;
    case 'get_stats':
        requirePermission('users', 'view');
        getUserStats($pdo);
        break;
    case 'get_permissions':
        requirePermission('users', 'view');
        getPermissions($pdo, $_GET['role'] ?? ($input['role'] ?? ''));
        break;
    case 'save_permissions':
        requirePermission('users', 'edit');
        savePermissions($pdo, $input);
        break;
    case 'get_activity':
        requirePermission('users', 'view');
        getLoginActivity($pdo);
        break;
    case 'get_logs':
        requirePermission('users', 'view');
        getAccessLogs($pdo);
        break;
    default:
        echo json_encode(['success' => false, 'message' => 'Invalid action']);
}

// ============================================
// Helper Functions for UUID and Permissions
// ============================================

/**
 * Get user UUID by display ID (internal use only)
 */
function getUserUuidById($pdo, $displayId) {
    $stmt = $pdo->prepare("SELECT uuid FROM users WHERE id = ?");
    $stmt->execute([$displayId]);
    $result = $stmt->fetch();
    return $result ? $result['uuid'] : null;
}

/**
 * Get permissions from user_permissions table as associative array
 */
function getPermissionsFromTable($pdo, $userUuid) {
    $stmt = $pdo->prepare("
        SELECT module, can_view, can_create, can_edit, can_delete, can_export
        FROM user_permissions
        WHERE user_uuid = ?
    ");
    $stmt->execute([$userUuid]);
    $rows = $stmt->fetchAll();

    $permissions = [];
    foreach ($rows as $row) {
        $permissions[$row['module']] = [
            'view' => (bool) $row['can_view'],
            'create' => (bool) $row['can_create'],
            'edit' => (bool) $row['can_edit'],
            'delete' => (bool) $row['can_delete'],
            'export' => (bool) $row['can_export']
        ];
    }
    return $permissions;
}

/**
 * Get permissions for multiple users in a single query (batch loading)
 * Prevents N+1 query problem when listing users
 */
function getBatchPermissions($pdo, $userUuids) {
    if (empty($userUuids)) {
        return [];
    }

    $placeholders = implode(',', array_fill(0, count($userUuids), '?'));
    $stmt = $pdo->prepare("
        SELECT user_uuid, module, can_view, can_create, can_edit, can_delete, can_export
        FROM user_permissions
        WHERE user_uuid IN ($placeholders)
    ");
    $stmt->execute($userUuids);
    $rows = $stmt->fetchAll();

    // Group permissions by user_uuid
    $permissionsByUser = [];
    foreach ($rows as $row) {
        $uuid = $row['user_uuid'];
        if (!isset($permissionsByUser[$uuid])) {
            $permissionsByUser[$uuid] = [];
        }
        $permissionsByUser[$uuid][$row['module']] = [
            'view' => (bool) $row['can_view'],
            'create' => (bool) $row['can_create'],
            'edit' => (bool) $row['can_edit'],
            'delete' => (bool) $row['can_delete'],
            'export' => (bool) $row['can_export']
        ];
    }

    return $permissionsByUser;
}

/**
 * Save permissions to user_permissions table
 */
function savePermissionsToTable($pdo, $userUuid, $permissions) {
    if (!$permissions || !is_array($permissions)) {
        return;
    }

    // Delete existing permissions for this user
    $stmt = $pdo->prepare("DELETE FROM user_permissions WHERE user_uuid = ?");
    $stmt->execute([$userUuid]);

    // Insert new permissions
    $insertStmt = $pdo->prepare("
        INSERT INTO user_permissions (user_uuid, module, can_view, can_create, can_edit, can_delete, can_export)
        VALUES (?, ?, ?, ?, ?, ?, ?)
    ");

    foreach ($permissions as $module => $perms) {
        $canView = isset($perms['view']) ? ($perms['view'] ? 1 : 0) : 0;
        $canCreate = isset($perms['create']) ? ($perms['create'] ? 1 : 0) : 0;
        $canEdit = isset($perms['edit']) ? ($perms['edit'] ? 1 : 0) : 0;
        $canDelete = isset($perms['delete']) ? ($perms['delete'] ? 1 : 0) : 0;
        $canExport = isset($perms['export']) ? ($perms['export'] ? 1 : 0) : 0;

        $insertStmt->execute([
            $userUuid,
            $module,
            $canView,
            $canCreate,
            $canEdit,
            $canDelete,
            $canExport
        ]);
    }
}

/**
 * Delete all permissions for a user
 */
function deleteUserPermissions($pdo, $userUuid) {
    $stmt = $pdo->prepare("DELETE FROM user_permissions WHERE user_uuid = ?");
    $stmt->execute([$userUuid]);
}

// ============================================
// Main API Functions
// ============================================

/**
 * List all users with optional filtering
 * Returns permissions from user_permissions table
 * Optimized: Uses batch loading to prevent N+1 query problem
 */
function listUsers($pdo) {
    try {
        $role = $_GET['role'] ?? '';
        $status = $_GET['status'] ?? '';
        $search = $_GET['search'] ?? '';
        $limit = isset($_GET['limit']) ? (int)$_GET['limit'] : 100;
        $offset = isset($_GET['offset']) ? (int)$_GET['offset'] : 0;

        // Select users with pagination for better performance
        $sql = "SELECT id, uuid, name, username, email, role, status, last_login, created_at, updated_at FROM users WHERE 1=1";
        $params = [];

        if ($role) {
            $sql .= " AND role = ?";
            $params[] = $role;
        }
        if ($status) {
            $sql .= " AND status = ?";
            $params[] = $status;
        }
        if ($search) {
            $sql .= " AND (name LIKE ? OR username LIKE ? OR email LIKE ? OR id LIKE ?)";
            $searchTerm = "%$search%";
            $params = array_merge($params, [$searchTerm, $searchTerm, $searchTerm, $searchTerm]);
        }

        $sql .= " ORDER BY created_at DESC LIMIT ? OFFSET ?";
        $params[] = $limit;
        $params[] = $offset;

        $stmt = $pdo->prepare($sql);
        $stmt->execute($params);
        $users = $stmt->fetchAll();

        // Batch load permissions in a single query (prevents N+1 problem)
        $uuids = array_column($users, 'uuid');
        $permissionsByUser = getBatchPermissions($pdo, $uuids);

        // Map permissions to users and remove uuid
        foreach ($users as &$user) {
            $user['permissions'] = $permissionsByUser[$user['uuid']] ?? [];
            unset($user['uuid']);
        }

        // Get total count for pagination
        $countSql = "SELECT COUNT(*) FROM users WHERE 1=1";
        $countParams = [];
        if ($role) {
            $countSql .= " AND role = ?";
            $countParams[] = $role;
        }
        if ($status) {
            $countSql .= " AND status = ?";
            $countParams[] = $status;
        }
        if ($search) {
            $countSql .= " AND (name LIKE ? OR username LIKE ? OR email LIKE ? OR id LIKE ?)";
            $searchTerm = "%$search%";
            $countParams = array_merge($countParams, [$searchTerm, $searchTerm, $searchTerm, $searchTerm]);
        }
        $countStmt = $pdo->prepare($countSql);
        $countStmt->execute($countParams);
        $totalCount = $countStmt->fetchColumn();

        echo json_encode([
            'success' => true,
            'data' => $users,
            'count' => count($users),
            'total' => (int)$totalCount,
            'limit' => $limit,
            'offset' => $offset
        ]);
    } catch (PDOException $e) {
        echo json_encode(['success' => false, 'message' => 'Error fetching users: ' . $e->getMessage()]);
    }
}

/**
 * Get single user by ID
 */
function getUser($pdo, $id) {
    if (!$id) {
        echo json_encode(['success' => false, 'message' => 'User ID required']);
        return;
    }

    try {
        $stmt = $pdo->prepare("SELECT id, uuid, name, username, email, role, status, last_login, created_at FROM users WHERE id = ?");
        $stmt->execute([$id]);
        $user = $stmt->fetch();

        if ($user) {
            // Get permissions from user_permissions table
            $user['permissions'] = getPermissionsFromTable($pdo, $user['uuid']);
            // Remove uuid from response (never expose)
            unset($user['uuid']);

            echo json_encode(['success' => true, 'data' => $user]);
        } else {
            echo json_encode(['success' => false, 'message' => 'User not found']);
        }
    } catch (PDOException $e) {
        echo json_encode(['success' => false, 'message' => 'Error fetching user']);
    }
}

/**
 * Create new user with UUID
 */
function createUser($pdo, $input) {
    $required = ['name', 'username', 'email', 'password', 'role'];
    foreach ($required as $field) {
        if (empty($input[$field])) {
            echo json_encode(['success' => false, 'message' => "Field '$field' is required"]);
            return;
        }
    }

    try {
        $pdo->beginTransaction();

        // Check if username or email already exists
        $stmt = $pdo->prepare("SELECT id FROM users WHERE username = ? OR email = ?");
        $stmt->execute([$input['username'], $input['email']]);
        if ($stmt->fetch()) {
            $pdo->rollBack();
            echo json_encode(['success' => false, 'message' => 'Username or email already exists']);
            return;
        }

        // Generate display ID (USR001, USR002, etc.)
        $stmt = $pdo->query("SELECT MAX(CAST(SUBSTRING(id, 4) AS UNSIGNED)) as max_num FROM users WHERE id LIKE 'USR%'");
        $result = $stmt->fetch();
        $nextNum = ($result['max_num'] ?? 0) + 1;
        $userId = 'USR' . str_pad($nextNum, 3, '0', STR_PAD_LEFT);

        // Generate UUID (internal key, never exposed)
        $uuid = generateUUID();

        // Hash password using PASSWORD_DEFAULT (bcrypt)
        $hashedPassword = password_hash($input['password'], PASSWORD_DEFAULT);

        $status = $input['status'] ?? 'Active';

        // Insert user with UUID
        $stmt = $pdo->prepare("
            INSERT INTO users (id, uuid, name, username, email, password, role, status)
            VALUES (?, ?, ?, ?, ?, ?, ?, ?)
        ");
        $stmt->execute([
            $userId,
            $uuid,
            $input['name'],
            $input['username'],
            $input['email'],
            $hashedPassword,
            $input['role'],
            $status
        ]);

        // Save permissions to user_permissions table if provided
        if (isset($input['permissions']) && !empty($input['permissions'])) {
            savePermissionsToTable($pdo, $uuid, $input['permissions']);
        }

        $pdo->commit();

        // Invalidate cache
        Cache::clear('users_');

        // Log the action
        logAction($pdo, 'create', 'users', $userId, "Created user: {$input['name']}");

        echo json_encode([
            'success' => true,
            'message' => 'User created successfully',
            'data' => ['id' => $userId]
        ]);
    } catch (PDOException $e) {
        $pdo->rollBack();
        echo json_encode(['success' => false, 'message' => 'Error creating user: ' . $e->getMessage()]);
    }
}

/**
 * Update existing user
 */
function updateUser($pdo, $input) {
    if (empty($input['id'])) {
        echo json_encode(['success' => false, 'message' => 'User ID required']);
        return;
    }

    try {
        $pdo->beginTransaction();

        // Get user UUID for permissions update
        $userUuid = getUserUuidById($pdo, $input['id']);
        if (!$userUuid) {
            $pdo->rollBack();
            echo json_encode(['success' => false, 'message' => 'User not found']);
            return;
        }

        // Build update query dynamically
        $updates = [];
        $params = [];

        $allowedFields = ['name', 'username', 'email', 'role', 'status'];
        foreach ($allowedFields as $field) {
            if (isset($input[$field])) {
                $updates[] = "$field = ?";
                $params[] = $input[$field];
            }
        }

        // Handle password separately
        if (!empty($input['password'])) {
            $updates[] = "password = ?";
            $params[] = password_hash($input['password'], PASSWORD_DEFAULT);
        }

        // Update user fields if any
        if (!empty($updates)) {
            $params[] = $input['id'];
            $sql = "UPDATE users SET " . implode(', ', $updates) . " WHERE id = ?";
            $stmt = $pdo->prepare($sql);
            $stmt->execute($params);
        }

        // Update permissions in user_permissions table if provided
        if (isset($input['permissions'])) {
            if (!empty($input['permissions'])) {
                savePermissionsToTable($pdo, $userUuid, $input['permissions']);
            } else {
                // Clear all permissions if empty
                deleteUserPermissions($pdo, $userUuid);
            }
        }

        $pdo->commit();

        // Invalidate cache
        Cache::invalidateUser($input['id']);

        logAction($pdo, 'update', 'users', $input['id'], "Updated user: {$input['id']}");

        echo json_encode(['success' => true, 'message' => 'User updated successfully']);
    } catch (PDOException $e) {
        $pdo->rollBack();
        echo json_encode(['success' => false, 'message' => 'Error updating user: ' . $e->getMessage()]);
    }
}

/**
 * Update user status (enable/disable)
 */
function updateUserStatus($pdo, $input) {
    if (empty($input['id']) || empty($input['status'])) {
        echo json_encode(['success' => false, 'message' => 'User ID and status required']);
        return;
    }

    try {
        $stmt = $pdo->prepare("UPDATE users SET status = ? WHERE id = ?");
        $stmt->execute([$input['status'], $input['id']]);

        logAction($pdo, 'update', 'users', $input['id'], "Changed status to: {$input['status']}");

        echo json_encode(['success' => true, 'message' => 'User status updated']);
    } catch (PDOException $e) {
        echo json_encode(['success' => false, 'message' => 'Error updating status']);
    }
}

/**
 * Reset user password
 */
function resetPassword($pdo, $input) {
    if (empty($input['id']) || empty($input['password'])) {
        echo json_encode(['success' => false, 'message' => 'User ID and new password required']);
        return;
    }

    try {
        $hashedPassword = password_hash($input['password'], PASSWORD_DEFAULT);
        $stmt = $pdo->prepare("UPDATE users SET password = ? WHERE id = ?");
        $stmt->execute([$hashedPassword, $input['id']]);

        logAction($pdo, 'update', 'users', $input['id'], "Password reset");

        echo json_encode(['success' => true, 'message' => 'Password reset successfully']);
    } catch (PDOException $e) {
        echo json_encode(['success' => false, 'message' => 'Error resetting password']);
    }
}

/**
 * Delete user (CASCADE will delete permissions automatically)
 */
function deleteUser($pdo, $input) {
    if (empty($input['id'])) {
        echo json_encode(['success' => false, 'message' => 'User ID required']);
        return;
    }

    // Prevent deleting SuperAdmin
    try {
        $stmt = $pdo->prepare("SELECT role FROM users WHERE id = ?");
        $stmt->execute([$input['id']]);
        $user = $stmt->fetch();

        if ($user && $user['role'] === 'SuperAdmin') {
            echo json_encode(['success' => false, 'message' => 'Cannot delete SuperAdmin user']);
            return;
        }

        // Delete user (CASCADE will delete related permissions automatically)
        $stmt = $pdo->prepare("DELETE FROM users WHERE id = ?");
        $stmt->execute([$input['id']]);

        // Invalidate cache
        Cache::invalidateUser($input['id']);

        logAction($pdo, 'delete', 'users', $input['id'], "Deleted user");

        echo json_encode(['success' => true, 'message' => 'User deleted successfully']);
    } catch (PDOException $e) {
        echo json_encode(['success' => false, 'message' => 'Error deleting user']);
    }
}

/**
 * Get user statistics by role
 */
function getUserStats($pdo) {
    try {
        $stats = [
            'total' => 0,
            'admins' => 0,
            'teachers' => 0,
            'students' => 0,
            'parents' => 0,
            'active' => 0,
            'inactive' => 0
        ];

        $stmt = $pdo->query("SELECT role, status, COUNT(*) as count FROM users GROUP BY role, status");
        while ($row = $stmt->fetch()) {
            $stats['total'] += $row['count'];

            if ($row['status'] === 'Active') {
                $stats['active'] += $row['count'];
            } else {
                $stats['inactive'] += $row['count'];
            }

            switch ($row['role']) {
                case 'Admin':
                case 'SuperAdmin':
                    $stats['admins'] += $row['count'];
                    break;
                case 'Teacher':
                    $stats['teachers'] += $row['count'];
                    break;
                case 'Student':
                    $stats['students'] += $row['count'];
                    break;
                case 'Parent':
                    $stats['parents'] += $row['count'];
                    break;
            }
        }

        echo json_encode(['success' => true, 'data' => $stats]);
    } catch (PDOException $e) {
        echo json_encode(['success' => false, 'message' => 'Error fetching stats']);
    }
}

/**
 * Get permissions for a role
 */
function getPermissions($pdo, $role) {
    if (!$role) {
        echo json_encode(['success' => false, 'message' => 'Role required']);
        return;
    }

    try {
        // Check if role_permissions table exists
        $stmt = $pdo->query("SHOW TABLES LIKE 'role_permissions'");
        if (!$stmt->fetch()) {
            // Return default permissions
            echo json_encode([
                'success' => true,
                'data' => getDefaultPermissions($role)
            ]);
            return;
        }

        $stmt = $pdo->prepare("SELECT permissions FROM role_permissions WHERE role = ?");
        $stmt->execute([$role]);
        $result = $stmt->fetch();

        if ($result) {
            echo json_encode([
                'success' => true,
                'data' => json_decode($result['permissions'], true)
            ]);
        } else {
            echo json_encode([
                'success' => true,
                'data' => getDefaultPermissions($role)
            ]);
        }
    } catch (PDOException $e) {
        echo json_encode(['success' => false, 'message' => 'Error fetching permissions']);
    }
}

/**
 * Save permissions for a role
 */
function savePermissions($pdo, $input) {
    if (empty($input['role']) || empty($input['permissions'])) {
        echo json_encode(['success' => false, 'message' => 'Role and permissions required']);
        return;
    }

    try {
        // Create table if not exists
        $pdo->exec("
            CREATE TABLE IF NOT EXISTS role_permissions (
                id INT AUTO_INCREMENT PRIMARY KEY,
                role VARCHAR(50) NOT NULL UNIQUE,
                permissions JSON NOT NULL,
                updated_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP
            )
        ");

        $permissions = json_encode($input['permissions']);

        $stmt = $pdo->prepare("
            INSERT INTO role_permissions (role, permissions)
            VALUES (?, ?)
            ON DUPLICATE KEY UPDATE permissions = ?
        ");
        $stmt->execute([$input['role'], $permissions, $permissions]);

        logAction($pdo, 'update', 'permissions', $input['role'], "Updated permissions for role: {$input['role']}");

        echo json_encode(['success' => true, 'message' => 'Permissions saved successfully']);
    } catch (PDOException $e) {
        echo json_encode(['success' => false, 'message' => 'Error saving permissions: ' . $e->getMessage()]);
    }
}

/**
 * Get login activity
 */
function getLoginActivity($pdo) {
    try {
        // Check if login_activity table exists
        $stmt = $pdo->query("SHOW TABLES LIKE 'login_activity'");
        if (!$stmt->fetch()) {
            // Return sample data
            echo json_encode([
                'success' => true,
                'data' => []
            ]);
            return;
        }

        $stmt = $pdo->query("
            SELECT la.*, u.name as user_name
            FROM login_activity la
            LEFT JOIN users u ON la.user_id = u.id
            ORDER BY la.created_at DESC
            LIMIT 50
        ");

        echo json_encode(['success' => true, 'data' => $stmt->fetchAll()]);
    } catch (PDOException $e) {
        echo json_encode(['success' => false, 'message' => 'Error fetching activity']);
    }
}

/**
 * Get access logs
 */
function getAccessLogs($pdo) {
    try {
        // Check if access_logs table exists
        $stmt = $pdo->query("SHOW TABLES LIKE 'access_logs'");
        if (!$stmt->fetch()) {
            echo json_encode(['success' => true, 'data' => []]);
            return;
        }

        $stmt = $pdo->query("
            SELECT al.*, u.name as user_name
            FROM access_logs al
            LEFT JOIN users u ON al.user_id = u.id
            ORDER BY al.created_at DESC
            LIMIT 100
        ");

        echo json_encode(['success' => true, 'data' => $stmt->fetchAll()]);
    } catch (PDOException $e) {
        echo json_encode(['success' => false, 'message' => 'Error fetching logs']);
    }
}

/**
 * Log user action
 */
function logAction($pdo, $action, $module, $targetId, $details) {
    try {
        // Create access_logs table if not exists
        $pdo->exec("
            CREATE TABLE IF NOT EXISTS access_logs (
                id INT AUTO_INCREMENT PRIMARY KEY,
                user_id VARCHAR(50),
                action VARCHAR(50) NOT NULL,
                module VARCHAR(50) NOT NULL,
                target_id VARCHAR(50),
                details TEXT,
                ip_address VARCHAR(45),
                created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP
            )
        ");

        $userId = $_SESSION['user_id'] ?? 'SYSTEM';
        $ip = $_SERVER['REMOTE_ADDR'] ?? '127.0.0.1';

        $stmt = $pdo->prepare("
            INSERT INTO access_logs (user_id, action, module, target_id, details, ip_address)
            VALUES (?, ?, ?, ?, ?, ?)
        ");
        $stmt->execute([$userId, $action, $module, $targetId, $details, $ip]);
    } catch (PDOException $e) {
        // Silently fail - logging should not break main operation
    }
}

/**
 * Generate UUID v4
 */
function generateUUID() {
    return sprintf(
        '%04x%04x-%04x-%04x-%04x-%04x%04x%04x',
        mt_rand(0, 0xffff), mt_rand(0, 0xffff),
        mt_rand(0, 0xffff),
        mt_rand(0, 0x0fff) | 0x4000,
        mt_rand(0, 0x3fff) | 0x8000,
        mt_rand(0, 0xffff), mt_rand(0, 0xffff), mt_rand(0, 0xffff)
    );
}

/**
 * Get default permissions for a role
 */
function getDefaultPermissions($role) {
    $modules = [
        'Dashboard', 'Students', 'Teachers', 'Attendance', 'Exams & Grades',
        'Timetable', 'Fee Management', 'Payroll', 'Library', 'Reports',
        'Settings', 'User Management'
    ];

    $permissions = [];

    foreach ($modules as $module) {
        switch ($role) {
            case 'Admin':
                $permissions[$module] = [
                    'view' => true,
                    'add' => true,
                    'edit' => true,
                    'delete' => $module !== 'User Management'
                ];
                break;
            case 'Teacher':
                $permissions[$module] = [
                    'view' => in_array($module, ['Dashboard', 'Students', 'Attendance', 'Exams & Grades', 'Timetable', 'Library']),
                    'add' => in_array($module, ['Attendance', 'Exams & Grades']),
                    'edit' => in_array($module, ['Attendance', 'Exams & Grades']),
                    'delete' => false
                ];
                break;
            case 'Student':
                $permissions[$module] = [
                    'view' => in_array($module, ['Dashboard', 'Attendance', 'Exams & Grades', 'Timetable', 'Fee Management', 'Library']),
                    'add' => false,
                    'edit' => false,
                    'delete' => false
                ];
                break;
            case 'Parent':
                $permissions[$module] = [
                    'view' => in_array($module, ['Dashboard', 'Students', 'Attendance', 'Exams & Grades', 'Fee Management']),
                    'add' => false,
                    'edit' => false,
                    'delete' => false
                ];
                break;
            default:
                $permissions[$module] = [
                    'view' => true,
                    'add' => true,
                    'edit' => true,
                    'delete' => true
                ];
        }
    }

    return $permissions;
}
?>
