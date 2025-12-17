<?php
/**
 * Authentication API Endpoint
 * EduManage Pro - School Management System
 *
 * Handles user authentication and session management
 */

require_once __DIR__ . '/../config/db.php';
require_once __DIR__ . '/../helpers/functions.php';

// Start session
session_start();

// Get request method
$method = $_SERVER['REQUEST_METHOD'];

// Get request data
$input = file_get_contents('php://input');
$data = json_decode($input, true);

// Get database connection
try {
    $db = getDB();
} catch (Exception $e) {
    sendResponse(false, 'Database connection failed', null, ['database' => $e->getMessage()]);
}

// Route requests based on method and action
switch ($method) {
    case 'POST':
        $action = $data['action'] ?? 'login';

        switch ($action) {
            case 'login':
                handleLogin($db, $data);
                break;

            case 'logout':
                handleLogout();
                break;

            case 'check':
                checkAuth();
                break;

            case 'change_password':
                handleChangePassword($db, $data);
                break;

            default:
                sendResponse(false, 'Invalid action', null, ['action' => 'Unknown action']);
        }
        break;

    case 'GET':
        $action = $_GET['action'] ?? 'check';

        switch ($action) {
            case 'check':
                checkAuth();
                break;

            default:
                sendResponse(false, 'Invalid action', null, ['action' => 'Unknown action']);
        }
        break;

    default:
        sendResponse(false, 'Method not allowed', null, ['method' => 'Unsupported HTTP method']);
}

/**
 * Handle login
 */
function handleLogin($db, $data) {
    try {
        // Validate required fields
        $required = ['username', 'password'];
        $errors = validateRequired($data, $required);

        if (!empty($errors)) {
            sendResponse(false, 'Please enter both username and password', null, $errors);
        }

        $username = sanitizeInput($data['username']);
        $password = $data['password']; // Don't sanitize password
        $rememberMe = isset($data['remember_me']) && $data['remember_me'] === true;

        // Find user - use positional parameters for PDO compatibility
        $stmt = $db->prepare("
            SELECT id, name, username, email, password, role, status, permissions
            FROM users
            WHERE username = ? OR email = ?
            LIMIT 1
        ");

        $stmt->execute([$username, $username]);
        $user = $stmt->fetch();

        // Check if user exists
        if (!$user) {
            sendResponse(false, 'Invalid username or password', null, ['credentials' => 'Invalid']);
        }

        // Check if account is active
        if ($user['status'] !== 'Active') {
            sendResponse(false, 'Your account is inactive. Please contact the school administrator.', null, ['status' => 'Inactive']);
        }

        // Verify password - support both hashed and legacy plain text passwords
        $passwordValid = false;
        $passwordInfo = password_get_info($user['password']);

        // Check if password is hashed (algo will be a non-zero integer for hashed passwords)
        // Plain text passwords will have algo as empty string or 0
        if (!empty($passwordInfo['algo']) && $passwordInfo['algo'] !== 0) {
            // Password is hashed - use password_verify
            $passwordValid = password_verify($password, $user['password']);
        } else {
            // Legacy plain text password - verify and upgrade to hash
            if ($password === $user['password']) {
                $passwordValid = true;
                // Upgrade to hashed password
                $hashedPassword = password_hash($password, PASSWORD_DEFAULT);
                $upgradeStmt = $db->prepare("UPDATE users SET password = ? WHERE id = ?");
                $upgradeStmt->execute([$hashedPassword, $user['id']]);
            }
        }

        if (!$passwordValid) {
            sendResponse(false, 'Invalid username or password', null, ['credentials' => 'Invalid']);
        }

        // Update last login
        $updateStmt = $db->prepare("UPDATE users SET last_login = NOW() WHERE id = ?");
        $updateStmt->execute([$user['id']]);

        // Set session variables
        $_SESSION['user_id'] = $user['id'];
        $_SESSION['user_name'] = $user['name'];
        $_SESSION['username'] = $user['username'];
        $_SESSION['user_email'] = $user['email'];
        $_SESSION['user_role'] = $user['role'];
        $_SESSION['permissions'] = $user['permissions'] ? json_decode($user['permissions'], true) : [];
        $_SESSION['logged_in'] = true;

        // Log activity
        logActivity($db, $user['id'], 'User logged in', 'Authentication', ['username' => $username]);

        // Prepare response
        $response = [
            'user' => [
                'id' => $user['id'],
                'name' => $user['name'],
                'username' => $user['username'],
                'email' => $user['email'],
                'role' => $user['role'],
                'permissions' => $_SESSION['permissions']
            ]
        ];

        sendResponse(true, 'Login successful! Welcome to EduManage Pro.', $response);

    } catch (Exception $e) {
        sendResponse(false, 'Error during login', null, ['error' => $e->getMessage(), 'line' => $e->getLine(), 'trace' => $e->getTraceAsString()]);
    }
}

/**
 * Handle logout
 */
function handleLogout() {
    try {
        // Log activity before destroying session
        if (isset($_SESSION['user_id'])) {
            try {
                $db = getDB();
                logActivity($db, $_SESSION['user_id'], 'User logged out', 'Authentication');
            } catch (Exception $e) {
                // Continue with logout even if logging fails
            }
        }

        // Destroy session
        session_unset();
        session_destroy();

        sendResponse(true, 'You have been logged out.', null);

    } catch (Exception $e) {
        sendResponse(false, 'Error during logout', null, ['error' => $e->getMessage()]);
    }
}

/**
 * Check if user is authenticated
 */
function checkAuth() {
    if (!isset($_SESSION['logged_in']) || $_SESSION['logged_in'] !== true) {
        sendResponse(false, 'Not authenticated', null, ['auth' => 'Required']);
    }

    $response = [
        'user' => [
            'id' => $_SESSION['user_id'] ?? null,
            'name' => $_SESSION['user_name'] ?? null,
            'username' => $_SESSION['username'] ?? null,
            'email' => $_SESSION['user_email'] ?? null,
            'role' => $_SESSION['user_role'] ?? null,
            'permissions' => $_SESSION['permissions'] ?? []
        ]
    ];

    sendResponse(true, 'Authenticated', $response);
}

/**
 * Handle password change
 */
function handleChangePassword($db, $data) {
    try {
        // Check authentication
        if (!isAuthenticated()) {
            sendResponse(false, 'Not authenticated', null, ['auth' => 'Required']);
        }

        // Validate required fields
        $required = ['current_password', 'new_password', 'confirm_password'];
        $errors = validateRequired($data, $required);

        if (!empty($errors)) {
            sendResponse(false, 'Please fill all fields', null, $errors);
        }

        $currentPassword = $data['current_password'];
        $newPassword = $data['new_password'];
        $confirmPassword = $data['confirm_password'];
        $userId = getCurrentUserId();

        // Validate password match
        if ($newPassword !== $confirmPassword) {
            sendResponse(false, 'New passwords do not match', null, ['confirm_password' => 'Mismatch']);
        }

        // Validate password length
        if (strlen($newPassword) < 6) {
            sendResponse(false, 'Password must be at least 6 characters', null, ['new_password' => 'Too short']);
        }

        // Get current password
        $stmt = $db->prepare("SELECT password FROM users WHERE id = :id");
        $stmt->execute([':id' => $userId]);
        $user = $stmt->fetch();

        if (!$user) {
            sendResponse(false, 'User not found', null, ['user' => 'Invalid']);
        }

        // Verify current password - support both hashed and plain text
        $currentValid = false;
        if (password_get_info($user['password'])['algo'] !== 0) {
            $currentValid = password_verify($currentPassword, $user['password']);
        } else {
            $currentValid = ($currentPassword === $user['password']);
        }

        if (!$currentValid) {
            sendResponse(false, 'Current password is incorrect', null, ['current_password' => 'Invalid']);
        }

        // Update password with proper hashing
        $hashedNewPassword = password_hash($newPassword, PASSWORD_DEFAULT);
        $updateStmt = $db->prepare("UPDATE users SET password = :password WHERE id = :id");
        $updateStmt->execute([
            ':password' => $hashedNewPassword,
            ':id' => $userId
        ]);

        // Log activity
        logActivity($db, $userId, 'Changed password', 'Authentication');

        sendResponse(true, 'Password changed successfully!', null);

    } catch (Exception $e) {
        sendResponse(false, 'Error changing password', null, ['error' => $e->getMessage()]);
    }
}
?>
