<?php
/**
 * Settings API Endpoint
 * EduManage Pro - School Management System
 */

require_once __DIR__ . '/../config/db.php';
require_once __DIR__ . '/../helpers/functions.php';

$method = $_SERVER['REQUEST_METHOD'];
$input = file_get_contents('php://input');
$data = json_decode($input, true);

try {
    $db = getDB();
} catch (Exception $e) {
    sendResponse(false, 'Database connection failed', null, ['database' => $e->getMessage()]);
}

switch ($method) {
    case 'GET':
        handleGet($db, $_GET);
        break;
    case 'POST':
        handlePost($db, $data);
        break;
    case 'PUT':
        handlePut($db, $data);
        break;
    default:
        sendResponse(false, 'Method not allowed');
}

function handleGet($db, $params) {
    $action = $params['action'] ?? 'all';

    switch ($action) {
        case 'all':
            getAllSettings($db);
            break;
        case 'get':
            getSetting($db, $params);
            break;
        case 'school':
            getSchoolInfo($db);
            break;
        case 'academic':
            getAcademicSettings($db);
            break;
        case 'classes':
            getClasses($db);
            break;
        case 'subjects':
            getSubjects($db, $params);
            break;
        default:
            sendResponse(false, 'Invalid action');
    }
}

function getAllSettings($db) {
    $query = "SELECT setting_key, setting_value, category FROM settings ORDER BY category, setting_key";
    $stmt = $db->query($query);
    $settings = $stmt->fetchAll();

    // Group by category
    $grouped = [];
    foreach ($settings as $setting) {
        $category = $setting['category'] ?? 'general';
        if (!isset($grouped[$category])) {
            $grouped[$category] = [];
        }
        $grouped[$category][$setting['setting_key']] = $setting['setting_value'];
    }

    sendResponse(true, 'Settings fetched successfully', $grouped);
}

function getSetting($db, $params) {
    if (empty($params['key'])) {
        sendResponse(false, 'Setting key is required');
    }

    $stmt = $db->prepare("SELECT setting_value FROM settings WHERE setting_key = :key");
    $stmt->execute([':key' => $params['key']]);
    $result = $stmt->fetch();

    if (!$result) {
        sendResponse(false, 'Setting not found');
    }

    sendResponse(true, 'Setting fetched', ['value' => $result['setting_value']]);
}

function getSchoolInfo($db) {
    $keys = ['school_name', 'school_address', 'school_phone', 'school_email', 'school_website', 'school_logo', 'principal_name', 'established_year'];

    $placeholders = implode(',', array_fill(0, count($keys), '?'));
    $stmt = $db->prepare("SELECT setting_key, setting_value FROM settings WHERE setting_key IN ($placeholders)");
    $stmt->execute($keys);
    $results = $stmt->fetchAll();

    $schoolInfo = [];
    foreach ($results as $row) {
        $schoolInfo[$row['setting_key']] = $row['setting_value'];
    }

    sendResponse(true, 'School info fetched', $schoolInfo);
}

function getAcademicSettings($db) {
    $keys = ['academic_year', 'session_start', 'session_end', 'grading_system', 'pass_percentage', 'working_days'];

    $placeholders = implode(',', array_fill(0, count($keys), '?'));
    $stmt = $db->prepare("SELECT setting_key, setting_value FROM settings WHERE setting_key IN ($placeholders)");
    $stmt->execute($keys);
    $results = $stmt->fetchAll();

    $academic = [];
    foreach ($results as $row) {
        $academic[$row['setting_key']] = $row['setting_value'];
    }

    sendResponse(true, 'Academic settings fetched', $academic);
}

function getClasses($db) {
    $query = "SELECT DISTINCT class FROM students ORDER BY class";
    $stmt = $db->query($query);
    $classes = $stmt->fetchAll(PDO::FETCH_COLUMN);

    // Also get sections
    $sectionsQuery = "SELECT DISTINCT section FROM students ORDER BY section";
    $sectionsStmt = $db->query($sectionsQuery);
    $sections = $sectionsStmt->fetchAll(PDO::FETCH_COLUMN);

    sendResponse(true, 'Classes and sections fetched', [
        'classes' => $classes,
        'sections' => $sections
    ]);
}

function getSubjects($db, $params) {
    $class = $params['class'] ?? '';

    $where = [];
    $bindings = [];

    if (!empty($class)) {
        $where[] = "class = :class";
        $bindings[':class'] = $class;
    }

    $whereClause = !empty($where) ? 'WHERE ' . implode(' AND ', $where) : '';

    $query = "SELECT * FROM subjects $whereClause ORDER BY name";
    $stmt = $db->prepare($query);
    $stmt->execute($bindings);
    $subjects = $stmt->fetchAll();

    sendResponse(true, 'Subjects fetched', ['subjects' => $subjects]);
}

function handlePost($db, $data) {
    $action = $data['action'] ?? 'save';

    switch ($action) {
        case 'save':
            saveSetting($db, $data);
            break;
        case 'bulk_save':
            bulkSaveSettings($db, $data);
            break;
        case 'add_subject':
            addSubject($db, $data);
            break;
        default:
            sendResponse(false, 'Invalid action');
    }
}

function saveSetting($db, $data) {
    if (empty($data['key']) || !isset($data['value'])) {
        sendResponse(false, 'Key and value are required');
    }

    $key = sanitizeInput($data['key']);
    $value = sanitizeInput($data['value']);
    $category = isset($data['category']) ? sanitizeInput($data['category']) : 'general';

    $stmt = $db->prepare("
        INSERT INTO settings (setting_key, setting_value, category)
        VALUES (:key, :value, :category)
        ON DUPLICATE KEY UPDATE setting_value = VALUES(setting_value), category = VALUES(category)
    ");

    $stmt->execute([
        ':key' => $key,
        ':value' => $value,
        ':category' => $category
    ]);

    logActivity($db, getCurrentUserId(), 'Updated setting', 'Settings', ['key' => $key]);

    sendResponse(true, 'Setting saved successfully');
}

function bulkSaveSettings($db, $data) {
    if (empty($data['settings']) || !is_array($data['settings'])) {
        sendResponse(false, 'Settings array is required');
    }

    $db->beginTransaction();

    try {
        $stmt = $db->prepare("
            INSERT INTO settings (setting_key, setting_value, category)
            VALUES (:key, :value, :category)
            ON DUPLICATE KEY UPDATE setting_value = VALUES(setting_value)
        ");

        foreach ($data['settings'] as $setting) {
            $stmt->execute([
                ':key' => sanitizeInput($setting['key']),
                ':value' => sanitizeInput($setting['value']),
                ':category' => isset($setting['category']) ? sanitizeInput($setting['category']) : 'general'
            ]);
        }

        $db->commit();

        logActivity($db, getCurrentUserId(), 'Bulk updated settings', 'Settings', ['count' => count($data['settings'])]);

        sendResponse(true, 'Settings saved successfully');

    } catch (Exception $e) {
        $db->rollBack();
        sendResponse(false, 'Error saving settings', null, ['error' => $e->getMessage()]);
    }
}

function addSubject($db, $data) {
    $required = ['name', 'code'];
    $errors = validateRequired($data, $required);

    if (!empty($errors)) {
        sendResponse(false, 'Validation failed', null, $errors);
    }

    $stmt = $db->prepare("
        INSERT INTO subjects (name, code, class, description, status)
        VALUES (:name, :code, :class, :description, :status)
    ");

    $stmt->execute([
        ':name' => sanitizeInput($data['name']),
        ':code' => sanitizeInput($data['code']),
        ':class' => isset($data['class']) ? sanitizeInput($data['class']) : null,
        ':description' => isset($data['description']) ? sanitizeInput($data['description']) : null,
        ':status' => isset($data['status']) ? sanitizeInput($data['status']) : 'Active'
    ]);

    $id = $db->lastInsertId();

    logActivity($db, getCurrentUserId(), 'Added subject', 'Settings', ['subject_id' => $id]);

    sendResponse(true, 'Subject added successfully', ['id' => $id]);
}

function handlePut($db, $data) {
    $action = $data['action'] ?? 'update_subject';

    switch ($action) {
        case 'update_subject':
            updateSubject($db, $data);
            break;
        default:
            sendResponse(false, 'Invalid action');
    }
}

function updateSubject($db, $data) {
    if (empty($data['id'])) {
        sendResponse(false, 'Subject ID is required');
    }

    $id = (int)$data['id'];
    $fields = [];
    $bindings = [':id' => $id];

    $updateableFields = ['name', 'code', 'class', 'description', 'status'];

    foreach ($updateableFields as $field) {
        if (isset($data[$field])) {
            $fields[] = "$field = :$field";
            $bindings[":$field"] = sanitizeInput($data[$field]);
        }
    }

    if (empty($fields)) {
        sendResponse(false, 'No fields to update');
    }

    $query = "UPDATE subjects SET " . implode(', ', $fields) . " WHERE id = :id";
    $stmt = $db->prepare($query);
    $stmt->execute($bindings);

    logActivity($db, getCurrentUserId(), 'Updated subject', 'Settings', ['subject_id' => $id]);

    sendResponse(true, 'Subject updated successfully');
}
?>
