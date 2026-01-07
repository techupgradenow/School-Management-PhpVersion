<?php
/**
 * Enhanced Dynamic Dropdown API with Dropdown ID Support
 * EduManage Pro - School/College Management System
 *
 * This API supports both:
 * 1. Legacy category_key-based dropdowns (hardcoded)
 * 2. New dropdown_id-based dropdowns (dynamic/configurable)
 *
 * Key Features:
 * - Get values by Dropdown ID
 * - Add values to specific Dropdown ID
 * - Backward compatible with existing code
 * - Clean separation between hardcoded and dynamic dropdowns
 */

require_once __DIR__ . '/../config/db.php';
require_once __DIR__ . '/../helpers/functions.php';

header('Content-Type: application/json');
header('Access-Control-Allow-Origin: *');
header('Access-Control-Allow-Methods: GET, POST, PUT, DELETE, OPTIONS');
header('Access-Control-Allow-Headers: Content-Type');

// Handle preflight requests
if ($_SERVER['REQUEST_METHOD'] === 'OPTIONS') {
    http_response_code(200);
    exit;
}

session_start();

$method = $_SERVER['REQUEST_METHOD'];
$input = file_get_contents('php://input');
$data = json_decode($input, true);

try {
    $db = getDB();
} catch (Exception $e) {
    sendResponse(false, 'Database connection failed', null, ['error' => $e->getMessage()]);
}

// Route requests
switch ($method) {
    case 'GET':
        handleGetRequest($db);
        break;

    case 'POST':
        handlePostRequest($db, $data);
        break;

    case 'PUT':
        handlePutRequest($db, $data);
        break;

    case 'DELETE':
        handleDeleteRequest($db);
        break;

    default:
        sendResponse(false, 'Method not allowed');
}

/**
 * Handle GET requests
 */
function handleGetRequest($db) {
    $action = $_GET['action'] ?? 'values';

    switch ($action) {
        case 'by_dropdown_id':
            getValuesByDropdownId($db);
            break;

        case 'all_dynamic':
            getAllDynamicDropdowns($db);
            break;

        case 'all_hardcoded':
            getAllHardcodedDropdowns($db);
            break;

        case 'is_dynamic':
            checkIsDynamic($db);
            break;

        case 'all':
            // Backward compatible - returns all dropdowns grouped by category
            getAllDropdownsLegacy($db);
            break;

        case 'values':
        default:
            getDropdownValuesLegacy($db);
            break;
    }
}

/**
 * Handle POST requests
 */
function handlePostRequest($db, $data) {
    $action = $data['action'] ?? 'add_value';

    switch ($action) {
        case 'add_by_dropdown_id':
            addValueByDropdownId($db, $data);
            break;

        case 'add_value':
        default:
            // Backward compatible - add value by category_key
            addValueLegacy($db, $data);
            break;
    }
}

/**
 * Handle PUT requests
 */
function handlePutRequest($db, $data) {
    $action = $data['action'] ?? 'update';

    switch ($action) {
        case 'update_by_dropdown_id':
            updateValueByDropdownId($db, $data);
            break;

        default:
            updateValueLegacy($db, $data);
            break;
    }
}

/**
 * Handle DELETE requests
 */
function handleDeleteRequest($db) {
    $dropdown_id = $_GET['dropdown_id'] ?? null;
    $value = $_GET['value'] ?? null;
    $value_id = $_GET['value_id'] ?? null;

    if ($dropdown_id && ($value || $value_id)) {
        deleteValueByDropdownId($db, $dropdown_id, $value, $value_id);
    } else {
        // Legacy delete by value_id
        deleteValueLegacy($db);
    }
}

// ============================================================================
// NEW API FUNCTIONS - DROPDOWN ID BASED
// ============================================================================

/**
 * Get values by Dropdown ID
 * GET /dropdowns_v2.php?action=by_dropdown_id&dropdown_id=455
 */
function getValuesByDropdownId($db) {
    $dropdown_id = $_GET['dropdown_id'] ?? null;
    $active_only = isset($_GET['active_only']) ? filter_var($_GET['active_only'], FILTER_VALIDATE_BOOLEAN) : true;

    if (!$dropdown_id) {
        sendResponse(false, 'Dropdown ID is required', null, ['error' => 'Missing dropdown_id parameter']);
    }

    try {
        $sql = "SELECT
                    dv.id,
                    dv.value,
                    dv.display_order,
                    dv.is_active,
                    dc.category_key,
                    dc.category_name,
                    dc.dropdown_id
                FROM dropdown_values dv
                INNER JOIN dropdown_categories dc ON dv.category_id = dc.id
                WHERE dc.dropdown_id = :dropdown_id";

        if ($active_only) {
            $sql .= " AND dv.is_active = 1";
        }

        $sql .= " ORDER BY dv.display_order, dv.value";

        $stmt = $db->prepare($sql);
        $stmt->bindValue(':dropdown_id', $dropdown_id, PDO::PARAM_INT);
        $stmt->execute();
        $values = $stmt->fetchAll(PDO::FETCH_ASSOC);

        // Get category info
        $stmt = $db->prepare("SELECT id, category_key, category_name FROM dropdown_categories WHERE dropdown_id = :dropdown_id");
        $stmt->bindValue(':dropdown_id', $dropdown_id, PDO::PARAM_INT);
        $stmt->execute();
        $category = $stmt->fetch(PDO::FETCH_ASSOC);

        sendResponse(true, 'Values retrieved successfully', [
            'dropdown_id' => (int)$dropdown_id,
            'category_key' => $category['category_key'] ?? null,
            'category_name' => $category['category_name'] ?? "Dropdown $dropdown_id",
            'values' => $values,
            'count' => count($values)
        ]);

    } catch (PDOException $e) {
        sendResponse(false, 'Database error', null, ['error' => $e->getMessage()]);
    }
}

/**
 * Add value by Dropdown ID
 * POST /dropdowns_v2.php
 * Body: {"action":"add_by_dropdown_id","dropdown_id":455,"value":"Guardian"}
 */
function addValueByDropdownId($db, $data) {
    $dropdown_id = $data['dropdown_id'] ?? null;
    $value = trim($data['value'] ?? '');

    if (!$dropdown_id) {
        sendResponse(false, 'Dropdown ID is required');
    }

    if (empty($value)) {
        sendResponse(false, 'Value is required');
    }

    try {
        // Get category ID from dropdown_id
        $stmt = $db->prepare("SELECT id, category_name FROM dropdown_categories WHERE dropdown_id = :dropdown_id");
        $stmt->bindValue(':dropdown_id', $dropdown_id, PDO::PARAM_INT);
        $stmt->execute();
        $category = $stmt->fetch(PDO::FETCH_ASSOC);

        if (!$category) {
            sendResponse(false, "Dropdown ID $dropdown_id does not exist");
        }

        $category_id = $category['id'];
        $category_name = $category['category_name'];

        // Check if value already exists
        $stmt = $db->prepare("SELECT id FROM dropdown_values WHERE category_id = :category_id AND LOWER(TRIM(value)) = LOWER(:value)");
        $stmt->bindValue(':category_id', $category_id, PDO::PARAM_INT);
        $stmt->bindValue(':value', $value, PDO::PARAM_STR);
        $stmt->execute();

        if ($stmt->fetch()) {
            sendResponse(false, "Value '$value' already exists in this dropdown");
        }

        // Get max display order
        $stmt = $db->prepare("SELECT COALESCE(MAX(display_order), 0) + 1 AS next_order FROM dropdown_values WHERE category_id = :category_id");
        $stmt->bindValue(':category_id', $category_id, PDO::PARAM_INT);
        $stmt->execute();
        $result = $stmt->fetch(PDO::FETCH_ASSOC);
        $display_order = $result['next_order'];

        // Insert new value
        $stmt = $db->prepare("INSERT INTO dropdown_values (category_id, value, display_order, is_active) VALUES (:category_id, :value, :display_order, 1)");
        $stmt->bindValue(':category_id', $category_id, PDO::PARAM_INT);
        $stmt->bindValue(':value', $value, PDO::PARAM_STR);
        $stmt->bindValue(':display_order', $display_order, PDO::PARAM_INT);
        $stmt->execute();

        $value_id = $db->lastInsertId();

        sendResponse(true, "Value added successfully to $category_name", [
            'value_id' => $value_id,
            'value' => $value,
            'dropdown_id' => (int)$dropdown_id,
            'display_order' => $display_order
        ]);

    } catch (PDOException $e) {
        sendResponse(false, 'Database error', null, ['error' => $e->getMessage()]);
    }
}

/**
 * Update value by Dropdown ID
 * PUT /dropdowns_v2.php
 * Body: {"action":"update_by_dropdown_id","dropdown_id":455,"value_id":123,"new_value":"Updated Value"}
 */
function updateValueByDropdownId($db, $data) {
    $dropdown_id = $data['dropdown_id'] ?? null;
    $value_id = $data['value_id'] ?? null;
    $new_value = trim($data['new_value'] ?? '');

    if (!$dropdown_id || !$value_id) {
        sendResponse(false, 'Dropdown ID and Value ID are required');
    }

    if (empty($new_value)) {
        sendResponse(false, 'New value is required');
    }

    try {
        // Verify value belongs to dropdown
        $stmt = $db->prepare("
            SELECT dv.id, dc.category_name
            FROM dropdown_values dv
            INNER JOIN dropdown_categories dc ON dv.category_id = dc.id
            WHERE dv.id = :value_id AND dc.dropdown_id = :dropdown_id
        ");
        $stmt->bindValue(':value_id', $value_id, PDO::PARAM_INT);
        $stmt->bindValue(':dropdown_id', $dropdown_id, PDO::PARAM_INT);
        $stmt->execute();
        $existing = $stmt->fetch(PDO::FETCH_ASSOC);

        if (!$existing) {
            sendResponse(false, 'Value not found in specified dropdown');
        }

        // Update value
        $stmt = $db->prepare("UPDATE dropdown_values SET value = :value, updated_at = NOW() WHERE id = :value_id");
        $stmt->bindValue(':value', $new_value, PDO::PARAM_STR);
        $stmt->bindValue(':value_id', $value_id, PDO::PARAM_INT);
        $stmt->execute();

        sendResponse(true, 'Value updated successfully', [
            'value_id' => $value_id,
            'new_value' => $new_value,
            'dropdown_id' => (int)$dropdown_id
        ]);

    } catch (PDOException $e) {
        sendResponse(false, 'Database error', null, ['error' => $e->getMessage()]);
    }
}

/**
 * Delete value by Dropdown ID
 * DELETE /dropdowns_v2.php?dropdown_id=455&value=Guardian
 * OR DELETE /dropdowns_v2.php?dropdown_id=455&value_id=123
 */
function deleteValueByDropdownId($db, $dropdown_id, $value, $value_id) {
    try {
        $sql = "UPDATE dropdown_values dv
                INNER JOIN dropdown_categories dc ON dv.category_id = dc.id
                SET dv.is_active = 0, dv.updated_at = NOW()
                WHERE dc.dropdown_id = :dropdown_id";

        if ($value_id) {
            $sql .= " AND dv.id = :value_id";
        } elseif ($value) {
            $sql .= " AND dv.value = :value";
        }

        $stmt = $db->prepare($sql);
        $stmt->bindValue(':dropdown_id', $dropdown_id, PDO::PARAM_INT);

        if ($value_id) {
            $stmt->bindValue(':value_id', $value_id, PDO::PARAM_INT);
        } elseif ($value) {
            $stmt->bindValue(':value', $value, PDO::PARAM_STR);
        }

        $stmt->execute();

        if ($stmt->rowCount() > 0) {
            sendResponse(true, 'Value deleted successfully', [
                'dropdown_id' => (int)$dropdown_id,
                'deleted' => $value_id ? "ID: $value_id" : $value
            ]);
        } else {
            sendResponse(false, 'Value not found');
        }

    } catch (PDOException $e) {
        sendResponse(false, 'Database error', null, ['error' => $e->getMessage()]);
    }
}

/**
 * Get all dynamic dropdowns (those with dropdown_id)
 * GET /dropdowns_v2.php?action=all_dynamic
 */
function getAllDynamicDropdowns($db) {
    try {
        $stmt = $db->query("SELECT * FROM vw_dynamic_dropdowns WHERE is_active = 1 ORDER BY dropdown_id");
        $dropdowns = $stmt->fetchAll(PDO::FETCH_ASSOC);

        sendResponse(true, 'Dynamic dropdowns retrieved', [
            'dropdowns' => $dropdowns,
            'count' => count($dropdowns)
        ]);

    } catch (PDOException $e) {
        sendResponse(false, 'Database error', null, ['error' => $e->getMessage()]);
    }
}

/**
 * Get all hardcoded dropdowns (those WITHOUT dropdown_id)
 * GET /dropdowns_v2.php?action=all_hardcoded
 */
function getAllHardcodedDropdowns($db) {
    try {
        $stmt = $db->query("SELECT * FROM vw_hardcoded_dropdowns WHERE is_active = 1 ORDER BY category_key");
        $dropdowns = $stmt->fetchAll(PDO::FETCH_ASSOC);

        sendResponse(true, 'Hardcoded dropdowns retrieved', [
            'dropdowns' => $dropdowns,
            'count' => count($dropdowns)
        ]);

    } catch (PDOException $e) {
        sendResponse(false, 'Database error', null, ['error' => $e->getMessage()]);
    }
}

/**
 * Check if dropdown is dynamic
 * GET /dropdowns_v2.php?action=is_dynamic&category_key=relation
 */
function checkIsDynamic($db) {
    $category_key = $_GET['category_key'] ?? null;

    if (!$category_key) {
        sendResponse(false, 'Category key is required');
    }

    try {
        $stmt = $db->prepare("SELECT fn_is_dynamic_dropdown(:category_key) AS is_dynamic");
        $stmt->bindValue(':category_key', $category_key, PDO::PARAM_STR);
        $stmt->execute();
        $result = $stmt->fetch(PDO::FETCH_ASSOC);

        sendResponse(true, 'Check completed', [
            'category_key' => $category_key,
            'is_dynamic' => (bool)$result['is_dynamic'],
            'dropdown_type' => $result['is_dynamic'] ? 'Dynamic (Dropdown ID)' : 'Hardcoded (Legacy)'
        ]);

    } catch (PDOException $e) {
        sendResponse(false, 'Database error', null, ['error' => $e->getMessage()]);
    }
}

// ============================================================================
// LEGACY API FUNCTIONS - BACKWARD COMPATIBLE
// ============================================================================

/**
 * Legacy: Get all dropdowns grouped by category
 */
function getAllDropdownsLegacy($db) {
    try {
        // Get institution type
        $stmt = $db->query("SELECT setting_value FROM institution_settings WHERE setting_key = 'institution_type' LIMIT 1");
        $result = $stmt->fetch();
        $institutionType = $result ? $result['setting_value'] : 'School';

        // Get all categories
        $stmt = $db->prepare("
            SELECT dc.*, it.name AS institution_type
            FROM dropdown_categories dc
            LEFT JOIN institution_types it ON dc.institution_type_id = it.id
            WHERE dc.is_active = 1
            ORDER BY dc.category_key
        ");
        $stmt->execute();
        $categories = $stmt->fetchAll(PDO::FETCH_ASSOC);

        $dropdowns = [];

        foreach ($categories as $category) {
            // Get values for each category
            $stmt = $db->prepare("SELECT * FROM dropdown_values WHERE category_id = :category_id AND is_active = 1 ORDER BY display_order, value");
            $stmt->bindValue(':category_id', $category['id'], PDO::PARAM_INT);
            $stmt->execute();
            $values = $stmt->fetchAll(PDO::FETCH_ASSOC);

            $dropdowns[$category['category_key']] = [
                'category_id' => $category['id'],
                'category_name' => $category['category_name'],
                'dropdown_id' => $category['dropdown_id'], // Will be NULL for hardcoded
                'is_dynamic' => !is_null($category['dropdown_id']),
                'values' => $values
            ];
        }

        sendResponse(true, 'Dropdowns loaded', [
            'dropdowns' => $dropdowns,
            'institution_type' => $institutionType
        ]);

    } catch (PDOException $e) {
        sendResponse(false, 'Database error', null, ['error' => $e->getMessage()]);
    }
}

/**
 * Legacy: Get dropdown values by category_key
 */
function getDropdownValuesLegacy($db) {
    $category_key = $_GET['category'] ?? $_GET['category_key'] ?? null;

    if (!$category_key) {
        sendResponse(false, 'Category key is required');
    }

    try {
        $stmt = $db->prepare("
            SELECT dv.*, dc.dropdown_id
            FROM dropdown_values dv
            INNER JOIN dropdown_categories dc ON dv.category_id = dc.id
            WHERE dc.category_key = :category_key AND dv.is_active = 1
            ORDER BY dv.display_order, dv.value
        ");
        $stmt->bindValue(':category_key', $category_key, PDO::PARAM_STR);
        $stmt->execute();
        $values = $stmt->fetchAll(PDO::FETCH_ASSOC);

        sendResponse(true, 'Values retrieved', ['values' => $values]);

    } catch (PDOException $e) {
        sendResponse(false, 'Database error', null, ['error' => $e->getMessage()]);
    }
}

/**
 * Legacy: Add value by category_key
 */
function addValueLegacy($db, $data) {
    $category_key = $data['category_key'] ?? null;
    $value = trim($data['value'] ?? '');

    if (!$category_key || empty($value)) {
        sendResponse(false, 'Category key and value are required');
    }

    try {
        // Get category
        $stmt = $db->prepare("SELECT id, category_name FROM dropdown_categories WHERE category_key = :category_key");
        $stmt->bindValue(':category_key', $category_key, PDO::PARAM_STR);
        $stmt->execute();
        $category = $stmt->fetch(PDO::FETCH_ASSOC);

        if (!$category) {
            sendResponse(false, "Category '$category_key' not found");
        }

        $category_id = $category['id'];

        // Check if value exists
        $stmt = $db->prepare("SELECT id FROM dropdown_values WHERE category_id = :category_id AND LOWER(TRIM(value)) = LOWER(:value)");
        $stmt->bindValue(':category_id', $category_id, PDO::PARAM_INT);
        $stmt->bindValue(':value', $value, PDO::PARAM_STR);
        $stmt->execute();

        if ($stmt->fetch()) {
            sendResponse(false, "Value already exists");
        }

        // Get display order
        $stmt = $db->prepare("SELECT COALESCE(MAX(display_order), 0) + 1 AS next_order FROM dropdown_values WHERE category_id = :category_id");
        $stmt->bindValue(':category_id', $category_id, PDO::PARAM_INT);
        $stmt->execute();
        $result = $stmt->fetch(PDO::FETCH_ASSOC);
        $display_order = $result['next_order'];

        // Insert
        $stmt = $db->prepare("INSERT INTO dropdown_values (category_id, value, display_order, is_active) VALUES (:category_id, :value, :display_order, 1)");
        $stmt->bindValue(':category_id', $category_id, PDO::PARAM_INT);
        $stmt->bindValue(':value', $value, PDO::PARAM_STR);
        $stmt->bindValue(':display_order', $display_order, PDO::PARAM_INT);
        $stmt->execute();

        sendResponse(true, 'Value added successfully', ['value_id' => $db->lastInsertId(), 'value' => $value]);

    } catch (PDOException $e) {
        sendResponse(false, 'Database error', null, ['error' => $e->getMessage()]);
    }
}

/**
 * Legacy: Update value
 */
function updateValueLegacy($db, $data) {
    $value_id = $data['value_id'] ?? $data['id'] ?? null;
    $new_value = trim($data['value'] ?? '');

    if (!$value_id || empty($new_value)) {
        sendResponse(false, 'Value ID and new value are required');
    }

    try {
        $stmt = $db->prepare("UPDATE dropdown_values SET value = :value, updated_at = NOW() WHERE id = :value_id");
        $stmt->bindValue(':value', $new_value, PDO::PARAM_STR);
        $stmt->bindValue(':value_id', $value_id, PDO::PARAM_INT);
        $stmt->execute();

        sendResponse(true, 'Value updated successfully', ['value_id' => $value_id, 'value' => $new_value]);

    } catch (PDOException $e) {
        sendResponse(false, 'Database error', null, ['error' => $e->getMessage()]);
    }
}

/**
 * Legacy: Delete value
 */
function deleteValueLegacy($db) {
    $value_id = $_GET['id'] ?? $_GET['value_id'] ?? null;

    if (!$value_id) {
        sendResponse(false, 'Value ID is required');
    }

    try {
        $stmt = $db->prepare("UPDATE dropdown_values SET is_active = 0, updated_at = NOW() WHERE id = :value_id");
        $stmt->bindValue(':value_id', $value_id, PDO::PARAM_INT);
        $stmt->execute();

        sendResponse(true, 'Value deleted successfully', ['value_id' => $value_id]);

    } catch (PDOException $e) {
        sendResponse(false, 'Database error', null, ['error' => $e->getMessage()]);
    }
}

// sendResponse() function is already defined in functions.php
?>
