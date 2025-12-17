<?php
/**
 * Institution Settings API
 * EduManage Pro - School/College Management System
 *
 * Manages institution type and settings
 */

require_once __DIR__ . '/../config/db.php';
require_once __DIR__ . '/../helpers/functions.php';

session_start();

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
        $action = $_GET['action'] ?? 'settings';
        switch ($action) {
            case 'settings':
                getSettings($db);
                break;
            case 'types':
                getInstitutionTypes($db);
                break;
            case 'current_type':
                getCurrentType($db);
                break;
            default:
                sendResponse(false, 'Invalid action');
        }
        break;

    case 'POST':
        $action = $data['action'] ?? 'update';
        switch ($action) {
            case 'update':
                updateSettings($db, $data);
                break;
            case 'set_type':
                setInstitutionType($db, $data);
                break;
            default:
                sendResponse(false, 'Invalid action');
        }
        break;

    default:
        sendResponse(false, 'Method not allowed');
}

/**
 * Get all institution settings
 */
function getSettings($db) {
    try {
        $stmt = $db->query("SELECT setting_key, setting_value FROM institution_settings");
        $rows = $stmt->fetchAll();

        $settings = [];
        foreach ($rows as $row) {
            $settings[$row['setting_key']] = $row['setting_value'];
        }

        sendResponse(true, 'Settings fetched successfully', $settings);
    } catch (Exception $e) {
        sendResponse(false, 'Error fetching settings', null, ['error' => $e->getMessage()]);
    }
}

/**
 * Get all institution types
 */
function getInstitutionTypes($db) {
    try {
        $stmt = $db->query("
            SELECT id, name, description, is_active
            FROM institution_types
            WHERE is_active = 1
            ORDER BY id
        ");
        $types = $stmt->fetchAll();

        sendResponse(true, 'Institution types fetched successfully', $types);
    } catch (Exception $e) {
        sendResponse(false, 'Error fetching institution types', null, ['error' => $e->getMessage()]);
    }
}

/**
 * Get current institution type with its details
 */
function getCurrentType($db) {
    try {
        // Get current type from settings
        $stmt = $db->prepare("SELECT setting_value FROM institution_settings WHERE setting_key = 'institution_type'");
        $stmt->execute();
        $result = $stmt->fetch();
        $currentType = $result ? $result['setting_value'] : 'School';

        // Get type details
        $stmt = $db->prepare("SELECT id, name, description FROM institution_types WHERE name = ?");
        $stmt->execute([$currentType]);
        $typeDetails = $stmt->fetch();

        // Get categories for this institution type
        $stmt = $db->prepare("
            SELECT id, category_key, category_name, description, is_system
            FROM dropdown_categories
            WHERE (institution_type_id = ? OR institution_type_id IS NULL) AND is_active = 1
            ORDER BY category_name
        ");
        $stmt->execute([$typeDetails['id'] ?? 1]);
        $categories = $stmt->fetchAll();

        sendResponse(true, 'Current institution type fetched', [
            'type' => $currentType,
            'details' => $typeDetails,
            'categories' => $categories
        ]);
    } catch (Exception $e) {
        sendResponse(false, 'Error fetching current type', null, ['error' => $e->getMessage()]);
    }
}

/**
 * Update institution settings
 */
function updateSettings($db, $data) {
    try {
        if (!isset($data['settings']) || !is_array($data['settings'])) {
            sendResponse(false, 'Settings data is required');
        }

        $db->beginTransaction();

        $stmt = $db->prepare("
            INSERT INTO institution_settings (setting_key, setting_value)
            VALUES (?, ?)
            ON DUPLICATE KEY UPDATE setting_value = VALUES(setting_value)
        ");

        foreach ($data['settings'] as $key => $value) {
            $stmt->execute([$key, $value]);
        }

        $db->commit();

        sendResponse(true, 'Settings updated successfully');
    } catch (Exception $e) {
        $db->rollBack();
        sendResponse(false, 'Error updating settings', null, ['error' => $e->getMessage()]);
    }
}

/**
 * Set institution type (School/College)
 */
function setInstitutionType($db, $data) {
    try {
        if (!isset($data['type'])) {
            sendResponse(false, 'Institution type is required');
        }

        $type = $data['type'];

        // Validate type exists
        $stmt = $db->prepare("SELECT id, name FROM institution_types WHERE name = ? AND is_active = 1");
        $stmt->execute([$type]);
        $typeRow = $stmt->fetch();

        if (!$typeRow) {
            sendResponse(false, 'Invalid institution type');
        }

        // Update setting
        $stmt = $db->prepare("
            INSERT INTO institution_settings (setting_key, setting_value)
            VALUES ('institution_type', ?)
            ON DUPLICATE KEY UPDATE setting_value = VALUES(setting_value)
        ");
        $stmt->execute([$type]);

        // Get categories for this type
        $stmt = $db->prepare("
            SELECT id, category_key, category_name, description
            FROM dropdown_categories
            WHERE (institution_type_id = ? OR institution_type_id IS NULL) AND is_active = 1
            ORDER BY category_name
        ");
        $stmt->execute([$typeRow['id']]);
        $categories = $stmt->fetchAll();

        sendResponse(true, "Institution type set to $type", [
            'type' => $type,
            'type_id' => $typeRow['id'],
            'categories' => $categories
        ]);
    } catch (Exception $e) {
        sendResponse(false, 'Error setting institution type', null, ['error' => $e->getMessage()]);
    }
}
?>
