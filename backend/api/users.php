<?php
/**
 * User Management API
 * EduManage Pro - School/College Management System
 *
 * Handles CRUD operations for users, roles, and permissions
 */

header('Content-Type: application/json');
header('Access-Control-Allow-Origin: *');
header('Access-Control-Allow-Methods: GET, POST, PUT, DELETE, OPTIONS');
header('Access-Control-Allow-Headers: Content-Type');

if ($_SERVER['REQUEST_METHOD'] === 'OPTIONS') {
    exit(0);
}

// Database configuration
$host = 'localhost';
$dbname = 'edumanage_pro';
$username = 'root';
$password = '';

try {
    $pdo = new PDO(
        "mysql:host=$host;dbname=$dbname;charset=utf8mb4",
        $username,
        $password,
        [
            PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION,
            PDO::ATTR_DEFAULT_FETCH_MODE => PDO::FETCH_ASSOC,
            PDO::ATTR_EMULATE_PREPARES => false
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

// Route to appropriate handler
switch ($action) {
    case 'list':
        listUsers($pdo);
        break;
    case 'get':
        getUser($pdo, $_GET['id'] ?? ($input['id'] ?? ''));
        break;
    case 'create':
        createUser($pdo, $input);
        break;
    case 'update':
        updateUser($pdo, $input);
        break;
    case 'update_status':
        updateUserStatus($pdo, $input);
        break;
    case 'reset_password':
        resetPassword($pdo, $input);
        break;
    case 'delete':
        deleteUser($pdo, $input);
        break;
    case 'get_stats':
        getUserStats($pdo);
        break;
    case 'get_permissions':
        getPermissions($pdo, $_GET['role'] ?? ($input['role'] ?? ''));
        break;
    case 'save_permissions':
        savePermissions($pdo, $input);
        break;
    case 'get_activity':
        getLoginActivity($pdo);
        break;
    case 'get_logs':
        getAccessLogs($pdo);
        break;
    default:
        echo json_encode(['success' => false, 'message' => 'Invalid action']);
}

/**
 * List all users with optional filtering
 */
function listUsers($pdo) {
    try {
        $role = $_GET['role'] ?? '';
        $status = $_GET['status'] ?? '';
        $search = $_GET['search'] ?? '';

        $sql = "SELECT id, name, username, email, role, status, last_login, created_at, updated_at FROM users WHERE 1=1";
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

        $sql .= " ORDER BY created_at DESC";

        $stmt = $pdo->prepare($sql);
        $stmt->execute($params);
        $users = $stmt->fetchAll();

        echo json_encode([
            'success' => true,
            'data' => $users,
            'count' => count($users)
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
        $stmt = $pdo->prepare("SELECT id, name, username, email, role, status, permissions, last_login, created_at FROM users WHERE id = ?");
        $stmt->execute([$id]);
        $user = $stmt->fetch();

        if ($user) {
            if ($user['permissions']) {
                $user['permissions'] = json_decode($user['permissions'], true);
            }
            echo json_encode(['success' => true, 'data' => $user]);
        } else {
            echo json_encode(['success' => false, 'message' => 'User not found']);
        }
    } catch (PDOException $e) {
        echo json_encode(['success' => false, 'message' => 'Error fetching user']);
    }
}

/**
 * Create new user
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
        // Check if username or email already exists
        $stmt = $pdo->prepare("SELECT id FROM users WHERE username = ? OR email = ?");
        $stmt->execute([$input['username'], $input['email']]);
        if ($stmt->fetch()) {
            echo json_encode(['success' => false, 'message' => 'Username or email already exists']);
            return;
        }

        // Generate user ID
        $stmt = $pdo->query("SELECT MAX(CAST(SUBSTRING(id, 4) AS UNSIGNED)) as max_num FROM users WHERE id LIKE 'USR%'");
        $result = $stmt->fetch();
        $nextNum = ($result['max_num'] ?? 0) + 1;
        $userId = 'USR' . str_pad($nextNum, 3, '0', STR_PAD_LEFT);

        // Hash password
        $hashedPassword = password_hash($input['password'], PASSWORD_DEFAULT);

        $status = $input['status'] ?? 'Active';

        $stmt = $pdo->prepare("
            INSERT INTO users (id, name, username, email, password, role, status)
            VALUES (?, ?, ?, ?, ?, ?, ?)
        ");
        $stmt->execute([
            $userId,
            $input['name'],
            $input['username'],
            $input['email'],
            $hashedPassword,
            $input['role'],
            $status
        ]);

        // Log the action
        logAction($pdo, 'create', 'users', $userId, "Created user: {$input['name']}");

        echo json_encode([
            'success' => true,
            'message' => 'User created successfully',
            'data' => ['id' => $userId]
        ]);
    } catch (PDOException $e) {
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

        if (empty($updates)) {
            echo json_encode(['success' => false, 'message' => 'No fields to update']);
            return;
        }

        $params[] = $input['id'];
        $sql = "UPDATE users SET " . implode(', ', $updates) . " WHERE id = ?";

        $stmt = $pdo->prepare($sql);
        $stmt->execute($params);

        logAction($pdo, 'update', 'users', $input['id'], "Updated user: {$input['id']}");

        echo json_encode(['success' => true, 'message' => 'User updated successfully']);
    } catch (PDOException $e) {
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
 * Delete user
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

        $stmt = $pdo->prepare("DELETE FROM users WHERE id = ?");
        $stmt->execute([$input['id']]);

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
