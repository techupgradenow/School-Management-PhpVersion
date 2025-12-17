<?php
/**
 * Helper Functions
 * EduManage Pro - School Management System
 */

/**
 * Send JSON response
 */
function sendResponse($success, $message = '', $data = null, $errors = null) {
    $response = [
        'success' => $success,
        'message' => $message
    ];

    if ($data !== null) {
        $response['data'] = $data;
    }

    if ($errors !== null) {
        $response['errors'] = $errors;
    }

    echo json_encode($response, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES);
    exit();
}

/**
 * Sanitize input data
 */
function sanitizeInput($data) {
    if (is_array($data)) {
        return array_map('sanitizeInput', $data);
    }
    return htmlspecialchars(strip_tags(trim($data)), ENT_QUOTES, 'UTF-8');
}

/**
 * Validate required fields
 */
function validateRequired($data, $requiredFields) {
    $errors = [];

    foreach ($requiredFields as $field) {
        if (!isset($data[$field]) || empty(trim($data[$field]))) {
            $errors[$field] = ucfirst(str_replace('_', ' ', $field)) . ' is required';
        }
    }

    return $errors;
}

/**
 * Validate email format
 */
function validateEmail($email) {
    return filter_var($email, FILTER_VALIDATE_EMAIL) !== false;
}

/**
 * Generate unique ID with prefix
 */
function generateId($prefix = 'ID', $length = 6) {
    $timestamp = time();
    $random = mt_rand(100000, 999999);
    return $prefix . substr($timestamp, -4) . substr($random, 0, $length - 4);
}

/**
 * Log activity
 */
function logActivity($db, $userId, $action, $module, $details = null, $ipAddress = null) {
    try {
        if ($ipAddress === null) {
            $ipAddress = $_SERVER['REMOTE_ADDR'] ?? 'Unknown';
        }

        $stmt = $db->prepare("
            INSERT INTO activity_logs (user_id, action, module, details, ip_address)
            VALUES (:user_id, :action, :module, :details, :ip_address)
        ");

        $stmt->execute([
            ':user_id' => $userId,
            ':action' => $action,
            ':module' => $module,
            ':details' => $details ? json_encode($details) : null,
            ':ip_address' => $ipAddress
        ]);

        return true;
    } catch (Exception $e) {
        error_log("Activity Log Error: " . $e->getMessage());
        return false;
    }
}

/**
 * Hash password
 */
function hashPassword($password) {
    return password_hash($password, PASSWORD_DEFAULT);
}

/**
 * Verify password
 */
function verifyPassword($password, $hash) {
    return password_verify($password, $hash);
}

/**
 * Format date for display
 */
function formatDate($date, $format = 'd M Y') {
    if (empty($date) || $date === '0000-00-00' || $date === null) {
        return '-';
    }

    try {
        $dt = new DateTime($date);
        return $dt->format($format);
    } catch (Exception $e) {
        return $date;
    }
}

/**
 * Format date for database
 */
function formatDateForDB($date) {
    if (empty($date)) {
        return null;
    }

    try {
        $dt = new DateTime($date);
        return $dt->format('Y-m-d');
    } catch (Exception $e) {
        return null;
    }
}

/**
 * Format currency
 */
function formatCurrency($amount, $currency = 'INR', $symbol = '₹') {
    if ($amount === null || $amount === '') {
        return $symbol . '0';
    }

    $formatted = number_format((float)$amount, 2);
    return $symbol . $formatted;
}

/**
 * Check if user is authenticated
 */
function isAuthenticated() {
    session_start();
    return isset($_SESSION['user_id']) && !empty($_SESSION['user_id']);
}

/**
 * Get current user ID from session
 */
function getCurrentUserId() {
    if (!isAuthenticated()) {
        return null;
    }
    return $_SESSION['user_id'] ?? null;
}

/**
 * Get current user role from session
 */
function getCurrentUserRole() {
    if (!isAuthenticated()) {
        return null;
    }
    return $_SESSION['user_role'] ?? null;
}

/**
 * Check if user has permission
 */
function hasPermission($module, $action = 'view') {
    if (!isAuthenticated()) {
        return false;
    }

    $role = getCurrentUserRole();

    // SuperAdmin and Admin have all permissions
    if (in_array($role, ['SuperAdmin', 'Admin'])) {
        return true;
    }

    // Check specific permissions from session
    $permissions = $_SESSION['permissions'] ?? [];

    if (isset($permissions[$module])) {
        return isset($permissions[$module][$action]) && $permissions[$module][$action] === true;
    }

    return false;
}

/**
 * Validate date format
 */
function isValidDate($date, $format = 'Y-m-d') {
    $d = DateTime::createFromFormat($format, $date);
    return $d && $d->format($format) === $date;
}

/**
 * Calculate age from date of birth
 */
function calculateAge($dob) {
    if (empty($dob) || $dob === '0000-00-00') {
        return null;
    }

    try {
        $birthDate = new DateTime($dob);
        $today = new DateTime();
        $age = $today->diff($birthDate);
        return $age->y;
    } catch (Exception $e) {
        return null;
    }
}

/**
 * Generate receipt number
 */
function generateReceiptNo($prefix = 'RCP') {
    return $prefix . date('Ymd') . mt_rand(1000, 9999);
}

/**
 * Generate admission number
 */
function generateAdmissionNo($prefix = 'ADM') {
    return $prefix . date('Y') . str_pad(mt_rand(1, 9999), 4, '0', STR_PAD_LEFT);
}

/**
 * Upload file (base64)
 */
function uploadBase64File($base64String, $allowedTypes = ['image/jpeg', 'image/png', 'application/pdf']) {
    if (empty($base64String)) {
        return ['success' => false, 'message' => 'No file data provided'];
    }

    // Check if it's already a data URL
    if (strpos($base64String, 'data:') === 0) {
        // Extract MIME type and base64 data
        if (preg_match('/^data:(.*?);base64,(.*)$/', $base64String, $matches)) {
            $mimeType = $matches[1];
            $base64Data = $matches[2];
        } else {
            return ['success' => false, 'message' => 'Invalid base64 format'];
        }
    } else {
        // Assume it's raw base64
        $base64Data = $base64String;
        $mimeType = 'application/octet-stream';
    }

    // Validate MIME type
    if (!in_array($mimeType, $allowedTypes)) {
        return ['success' => false, 'message' => 'File type not allowed'];
    }

    // Decode base64
    $fileData = base64_decode($base64Data);
    if ($fileData === false) {
        return ['success' => false, 'message' => 'Failed to decode file'];
    }

    // Check file size (max 5MB)
    if (strlen($fileData) > 5 * 1024 * 1024) {
        return ['success' => false, 'message' => 'File size exceeds 5MB'];
    }

    return [
        'success' => true,
        'fileData' => $base64Data,
        'mimeType' => $mimeType,
        'size' => strlen($fileData)
    ];
}

/**
 * Paginate results
 */
function paginate($query, $page = 1, $perPage = 10) {
    $offset = ($page - 1) * $perPage;
    return $query . " LIMIT $perPage OFFSET $offset";
}

/**
 * Get total pages
 */
function getTotalPages($totalRecords, $perPage = 10) {
    return ceil($totalRecords / $perPage);
}

/**
 * Clean phone number
 */
function cleanPhoneNumber($phone) {
    return preg_replace('/[^0-9+]/', '', $phone);
}

/**
 * Validate Indian phone number
 */
function isValidIndianPhone($phone) {
    $cleaned = cleanPhoneNumber($phone);
    return preg_match('/^(\+91|0)?[6-9]\d{9}$/', $cleaned);
}

/**
 * Create notification
 */
function createNotification($db, $title, $message, $type = 'info', $targetUserId = null, $targetRole = null, $icon = 'fa-bell') {
    try {
        $stmt = $db->prepare("
            INSERT INTO notifications (title, message, type, icon, target_user_id, target_role)
            VALUES (:title, :message, :type, :icon, :target_user_id, :target_role)
        ");

        $stmt->execute([
            ':title' => $title,
            ':message' => $message,
            ':type' => $type,
            ':icon' => $icon,
            ':target_user_id' => $targetUserId,
            ':target_role' => $targetRole
        ]);

        return true;
    } catch (Exception $e) {
        error_log("Notification Error: " . $e->getMessage());
        return false;
    }
}

/**
 * Get file extension from MIME type
 */
function getExtensionFromMimeType($mimeType) {
    $mimeMap = [
        'image/jpeg' => 'jpg',
        'image/png' => 'png',
        'image/gif' => 'gif',
        'application/pdf' => 'pdf',
        'application/msword' => 'doc',
        'application/vnd.openxmlformats-officedocument.wordprocessingml.document' => 'docx',
    ];

    return $mimeMap[$mimeType] ?? 'bin';
}

/**
 * Generate random password
 */
function generateRandomPassword($length = 8) {
    $characters = '0123456789abcdefghijklmnopqrstuvwxyzABCDEFGHIJKLMNOPQRSTUVWXYZ!@#$%';
    $password = '';
    $charLength = strlen($characters);

    for ($i = 0; $i < $length; $i++) {
        $password .= $characters[mt_rand(0, $charLength - 1)];
    }

    return $password;
}
?>
