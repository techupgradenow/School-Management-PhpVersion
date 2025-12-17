<?php
/**
 * Dynamic Dropdown Management API
 * EduManage Pro - School/College Management System
 *
 * Manages all dropdown values dynamically from database
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
        $action = $_GET['action'] ?? 'values';
        switch ($action) {
            case 'values':
                getDropdownValues($db);
                break;
            case 'categories':
                getCategories($db);
                break;
            case 'all':
                getAllDropdowns($db);
                break;
            case 'by_category':
                getByCategory($db, $_GET['category'] ?? '');
                break;
            default:
                sendResponse(false, 'Invalid action');
        }
        break;

    case 'POST':
        $action = $data['action'] ?? 'add_value';
        switch ($action) {
            case 'add_value':
                addDropdownValue($db, $data);
                break;
            case 'add_category':
                addCategory($db, $data);
                break;
            default:
                sendResponse(false, 'Invalid action');
        }
        break;

    case 'PUT':
        updateDropdownValue($db, $data);
        break;

    case 'DELETE':
        $id = $_GET['id'] ?? '';
        $type = $_GET['type'] ?? 'value';
        if ($type === 'category') {
            deleteCategory($db, $id);
        } else {
            deleteDropdownValue($db, $id);
        }
        break;

    default:
        sendResponse(false, 'Method not allowed');
}

/**
 * Get dropdown values with filters
 */
function getDropdownValues($db) {
    try {
        $categoryKey = $_GET['category'] ?? '';
        $institutionType = $_GET['institution_type'] ?? '';
        $parentId = $_GET['parent_id'] ?? '';
        $activeOnly = isset($_GET['active_only']) ? filter_var($_GET['active_only'], FILTER_VALIDATE_BOOLEAN) : true;

        // Get current institution type if not specified
        if (empty($institutionType)) {
            $stmt = $db->prepare("SELECT setting_value FROM institution_settings WHERE setting_key = 'institution_type'");
            $stmt->execute();
            $result = $stmt->fetch();
            $institutionType = $result ? $result['setting_value'] : 'School';
        }

        // Get institution type ID
        $stmt = $db->prepare("SELECT id FROM institution_types WHERE name = ?");
        $stmt->execute([$institutionType]);
        $typeResult = $stmt->fetch();
        $institutionTypeId = $typeResult ? $typeResult['id'] : 1;

        $sql = "
            SELECT dv.id, dv.value, dv.display_order, dv.parent_id, dv.metadata, dv.is_active,
                   dc.category_key, dc.category_name, dc.institution_type_id
            FROM dropdown_values dv
            JOIN dropdown_categories dc ON dv.category_id = dc.id
            WHERE (dc.institution_type_id = ? OR dc.institution_type_id IS NULL)
        ";
        $params = [$institutionTypeId];

        if ($categoryKey) {
            $sql .= " AND dc.category_key = ?";
            $params[] = $categoryKey;
        }

        if ($parentId) {
            $sql .= " AND dv.parent_id = ?";
            $params[] = $parentId;
        }

        if ($activeOnly) {
            $sql .= " AND dv.is_active = 1";
        }

        $sql .= " ORDER BY dc.category_name, dv.display_order, dv.value";

        $stmt = $db->prepare($sql);
        $stmt->execute($params);
        $values = $stmt->fetchAll();

        // Group by category if no specific category requested
        if (!$categoryKey) {
            $grouped = [];
            foreach ($values as $v) {
                $key = $v['category_key'];
                if (!isset($grouped[$key])) {
                    $grouped[$key] = [
                        'category_key' => $key,
                        'category_name' => $v['category_name'],
                        'values' => []
                    ];
                }
                $grouped[$key]['values'][] = [
                    'id' => $v['id'],
                    'value' => $v['value'],
                    'display_order' => $v['display_order'],
                    'parent_id' => $v['parent_id'],
                    'metadata' => $v['metadata'] ? json_decode($v['metadata'], true) : null,
                    'is_active' => $v['is_active']
                ];
            }
            sendResponse(true, 'Dropdown values fetched', array_values($grouped));
        } else {
            sendResponse(true, 'Dropdown values fetched', $values);
        }
    } catch (Exception $e) {
        sendResponse(false, 'Error fetching dropdown values', null, ['error' => $e->getMessage()]);
    }
}

/**
 * Get dropdown by category key
 */
function getByCategory($db, $categoryKey) {
    if (!$categoryKey) {
        sendResponse(false, 'Category key is required');
    }

    try {
        // Get current institution type
        $stmt = $db->prepare("SELECT setting_value FROM institution_settings WHERE setting_key = 'institution_type'");
        $stmt->execute();
        $result = $stmt->fetch();
        $institutionType = $result ? $result['setting_value'] : 'School';

        // Get institution type ID
        $stmt = $db->prepare("SELECT id FROM institution_types WHERE name = ?");
        $stmt->execute([$institutionType]);
        $typeResult = $stmt->fetch();
        $institutionTypeId = $typeResult ? $typeResult['id'] : 1;

        $stmt = $db->prepare("
            SELECT dv.id, dv.value, dv.display_order, dv.parent_id, dv.metadata, dv.is_active,
                   dc.id as category_id, dc.category_key, dc.category_name
            FROM dropdown_values dv
            JOIN dropdown_categories dc ON dv.category_id = dc.id
            WHERE dc.category_key = ?
              AND (dc.institution_type_id = ? OR dc.institution_type_id IS NULL)
              AND dv.is_active = 1
            ORDER BY dv.display_order, dv.value
        ");
        $stmt->execute([$categoryKey, $institutionTypeId]);
        $values = $stmt->fetchAll();

        // Get category info
        $stmt = $db->prepare("
            SELECT id, category_key, category_name, description, is_system
            FROM dropdown_categories
            WHERE category_key = ? AND (institution_type_id = ? OR institution_type_id IS NULL)
            LIMIT 1
        ");
        $stmt->execute([$categoryKey, $institutionTypeId]);
        $category = $stmt->fetch();

        sendResponse(true, 'Dropdown fetched', [
            'category' => $category,
            'values' => $values
        ]);
    } catch (Exception $e) {
        sendResponse(false, 'Error fetching dropdown', null, ['error' => $e->getMessage()]);
    }
}

/**
 * Get all categories
 */
function getCategories($db) {
    try {
        $institutionType = $_GET['institution_type'] ?? '';

        // Get current institution type if not specified
        if (empty($institutionType)) {
            $stmt = $db->prepare("SELECT setting_value FROM institution_settings WHERE setting_key = 'institution_type'");
            $stmt->execute();
            $result = $stmt->fetch();
            $institutionType = $result ? $result['setting_value'] : 'School';
        }

        // Get institution type ID
        $stmt = $db->prepare("SELECT id FROM institution_types WHERE name = ?");
        $stmt->execute([$institutionType]);
        $typeResult = $stmt->fetch();
        $institutionTypeId = $typeResult ? $typeResult['id'] : 1;

        $stmt = $db->prepare("
            SELECT dc.id, dc.category_key, dc.category_name, dc.description, dc.is_system, dc.is_active,
                   it.name as institution_type,
                   COUNT(dv.id) as value_count
            FROM dropdown_categories dc
            LEFT JOIN institution_types it ON dc.institution_type_id = it.id
            LEFT JOIN dropdown_values dv ON dc.id = dv.category_id AND dv.is_active = 1
            WHERE (dc.institution_type_id = ? OR dc.institution_type_id IS NULL) AND dc.is_active = 1
            GROUP BY dc.id
            ORDER BY dc.category_name
        ");
        $stmt->execute([$institutionTypeId]);
        $categories = $stmt->fetchAll();

        sendResponse(true, 'Categories fetched', $categories);
    } catch (Exception $e) {
        sendResponse(false, 'Error fetching categories', null, ['error' => $e->getMessage()]);
    }
}

/**
 * Get all dropdowns for current institution type (bulk fetch)
 */
function getAllDropdowns($db) {
    try {
        // Get current institution type
        $stmt = $db->prepare("SELECT setting_value FROM institution_settings WHERE setting_key = 'institution_type'");
        $stmt->execute();
        $result = $stmt->fetch();
        $institutionType = $result ? $result['setting_value'] : 'School';

        // Get institution type ID
        $stmt = $db->prepare("SELECT id FROM institution_types WHERE name = ?");
        $stmt->execute([$institutionType]);
        $typeResult = $stmt->fetch();
        $institutionTypeId = $typeResult ? $typeResult['id'] : 1;

        // Get all categories with their values
        $stmt = $db->prepare("
            SELECT dc.id as category_id, dc.category_key, dc.category_name, dc.is_system,
                   dv.id as value_id, dv.value, dv.display_order, dv.parent_id, dv.metadata
            FROM dropdown_categories dc
            LEFT JOIN dropdown_values dv ON dc.id = dv.category_id AND dv.is_active = 1
            WHERE (dc.institution_type_id = ? OR dc.institution_type_id IS NULL) AND dc.is_active = 1
            ORDER BY dc.category_name, dv.display_order, dv.value
        ");
        $stmt->execute([$institutionTypeId]);
        $rows = $stmt->fetchAll();

        // Group by category
        $dropdowns = [];
        foreach ($rows as $row) {
            $key = $row['category_key'];
            if (!isset($dropdowns[$key])) {
                $dropdowns[$key] = [
                    'category_id' => $row['category_id'],
                    'category_key' => $key,
                    'category_name' => $row['category_name'],
                    'is_system' => $row['is_system'],
                    'values' => []
                ];
            }
            if ($row['value_id']) {
                $dropdowns[$key]['values'][] = [
                    'id' => $row['value_id'],
                    'value' => $row['value'],
                    'display_order' => $row['display_order'],
                    'parent_id' => $row['parent_id'],
                    'metadata' => $row['metadata'] ? json_decode($row['metadata'], true) : null
                ];
            }
        }

        sendResponse(true, 'All dropdowns fetched', [
            'institution_type' => $institutionType,
            'dropdowns' => $dropdowns
        ]);
    } catch (Exception $e) {
        sendResponse(false, 'Error fetching dropdowns', null, ['error' => $e->getMessage()]);
    }
}

/**
 * Add new dropdown value
 */
function addDropdownValue($db, $data) {
    try {
        // Validate required fields
        if (!isset($data['category_key']) || !isset($data['value'])) {
            sendResponse(false, 'Category and value are required');
        }

        $categoryKey = trim($data['category_key']);
        $value = trim($data['value']);

        if (empty($value)) {
            sendResponse(false, 'Value cannot be empty');
        }

        // Get current institution type
        $stmt = $db->prepare("SELECT setting_value FROM institution_settings WHERE setting_key = 'institution_type'");
        $stmt->execute();
        $result = $stmt->fetch();
        $institutionType = $result ? $result['setting_value'] : 'School';

        // Get institution type ID
        $stmt = $db->prepare("SELECT id FROM institution_types WHERE name = ?");
        $stmt->execute([$institutionType]);
        $typeResult = $stmt->fetch();
        $institutionTypeId = $typeResult ? $typeResult['id'] : 1;

        // Get category ID
        $stmt = $db->prepare("
            SELECT id, category_name FROM dropdown_categories
            WHERE category_key = ? AND (institution_type_id = ? OR institution_type_id IS NULL)
            LIMIT 1
        ");
        $stmt->execute([$categoryKey, $institutionTypeId]);
        $category = $stmt->fetch();

        if (!$category) {
            sendResponse(false, 'Invalid category');
        }

        // Check for duplicate
        $stmt = $db->prepare("
            SELECT id FROM dropdown_values
            WHERE category_id = ? AND LOWER(value) = LOWER(?)
        ");
        $stmt->execute([$category['id'], $value]);
        if ($stmt->fetch()) {
            sendResponse(false, 'This value already exists in ' . $category['category_name']);
        }

        // Get max display order
        $stmt = $db->prepare("SELECT MAX(display_order) as max_order FROM dropdown_values WHERE category_id = ?");
        $stmt->execute([$category['id']]);
        $maxOrder = $stmt->fetch()['max_order'] ?? 0;

        // Insert new value
        $stmt = $db->prepare("
            INSERT INTO dropdown_values (category_id, value, display_order, parent_id, metadata)
            VALUES (?, ?, ?, ?, ?)
        ");
        $stmt->execute([
            $category['id'],
            $value,
            $maxOrder + 1,
            $data['parent_id'] ?? null,
            isset($data['metadata']) ? json_encode($data['metadata']) : null
        ]);

        $newId = $db->lastInsertId();

        sendResponse(true, "Added '$value' to {$category['category_name']}", [
            'id' => $newId,
            'value' => $value,
            'category_key' => $categoryKey,
            'category_name' => $category['category_name']
        ]);
    } catch (Exception $e) {
        sendResponse(false, 'Error adding value', null, ['error' => $e->getMessage()]);
    }
}

/**
 * Add new category
 */
function addCategory($db, $data) {
    try {
        if (!isset($data['category_key']) || !isset($data['category_name'])) {
            sendResponse(false, 'Category key and name are required');
        }

        $categoryKey = strtolower(trim(preg_replace('/[^a-zA-Z0-9_]/', '_', $data['category_key'])));
        $categoryName = trim($data['category_name']);
        $description = trim($data['description'] ?? '');
        $institutionTypeId = $data['institution_type_id'] ?? null;

        if (empty($categoryKey) || empty($categoryName)) {
            sendResponse(false, 'Category key and name cannot be empty');
        }

        // Check for duplicate
        $stmt = $db->prepare("
            SELECT id FROM dropdown_categories
            WHERE category_key = ? AND (institution_type_id = ? OR (? IS NULL AND institution_type_id IS NULL))
        ");
        $stmt->execute([$categoryKey, $institutionTypeId, $institutionTypeId]);
        if ($stmt->fetch()) {
            sendResponse(false, 'A category with this key already exists');
        }

        $stmt = $db->prepare("
            INSERT INTO dropdown_categories (category_key, category_name, institution_type_id, description, is_system)
            VALUES (?, ?, ?, ?, 0)
        ");
        $stmt->execute([$categoryKey, $categoryName, $institutionTypeId, $description]);

        $newId = $db->lastInsertId();

        sendResponse(true, "Category '$categoryName' created", [
            'id' => $newId,
            'category_key' => $categoryKey,
            'category_name' => $categoryName
        ]);
    } catch (Exception $e) {
        sendResponse(false, 'Error creating category', null, ['error' => $e->getMessage()]);
    }
}

/**
 * Update dropdown value
 */
function updateDropdownValue($db, $data) {
    try {
        if (!isset($data['id'])) {
            sendResponse(false, 'Value ID is required');
        }

        $id = $data['id'];
        $updates = [];
        $params = [];

        if (isset($data['value'])) {
            $value = trim($data['value']);
            if (empty($value)) {
                sendResponse(false, 'Value cannot be empty');
            }

            // Check for duplicate
            $stmt = $db->prepare("
                SELECT dv.id FROM dropdown_values dv
                WHERE dv.category_id = (SELECT category_id FROM dropdown_values WHERE id = ?)
                  AND LOWER(dv.value) = LOWER(?)
                  AND dv.id != ?
            ");
            $stmt->execute([$id, $value, $id]);
            if ($stmt->fetch()) {
                sendResponse(false, 'This value already exists');
            }

            $updates[] = "value = ?";
            $params[] = $value;
        }

        if (isset($data['display_order'])) {
            $updates[] = "display_order = ?";
            $params[] = $data['display_order'];
        }

        if (isset($data['is_active'])) {
            $updates[] = "is_active = ?";
            $params[] = $data['is_active'] ? 1 : 0;
        }

        if (isset($data['metadata'])) {
            $updates[] = "metadata = ?";
            $params[] = json_encode($data['metadata']);
        }

        if (empty($updates)) {
            sendResponse(false, 'No fields to update');
        }

        $params[] = $id;
        $sql = "UPDATE dropdown_values SET " . implode(', ', $updates) . " WHERE id = ?";
        $stmt = $db->prepare($sql);
        $stmt->execute($params);

        sendResponse(true, 'Value updated successfully');
    } catch (Exception $e) {
        sendResponse(false, 'Error updating value', null, ['error' => $e->getMessage()]);
    }
}

/**
 * Delete dropdown value
 */
function deleteDropdownValue($db, $id) {
    try {
        if (!$id) {
            sendResponse(false, 'Value ID is required');
        }

        // Check if value exists and is not system
        $stmt = $db->prepare("
            SELECT dv.id, dc.is_system
            FROM dropdown_values dv
            JOIN dropdown_categories dc ON dv.category_id = dc.id
            WHERE dv.id = ?
        ");
        $stmt->execute([$id]);
        $row = $stmt->fetch();

        if (!$row) {
            sendResponse(false, 'Value not found');
        }

        // Soft delete instead of hard delete
        $stmt = $db->prepare("UPDATE dropdown_values SET is_active = 0 WHERE id = ?");
        $stmt->execute([$id]);

        sendResponse(true, 'Value deleted successfully');
    } catch (Exception $e) {
        sendResponse(false, 'Error deleting value', null, ['error' => $e->getMessage()]);
    }
}

/**
 * Delete category
 */
function deleteCategory($db, $id) {
    try {
        if (!$id) {
            sendResponse(false, 'Category ID is required');
        }

        // Check if category is system category
        $stmt = $db->prepare("SELECT is_system FROM dropdown_categories WHERE id = ?");
        $stmt->execute([$id]);
        $row = $stmt->fetch();

        if (!$row) {
            sendResponse(false, 'Category not found');
        }

        if ($row['is_system']) {
            sendResponse(false, 'System categories cannot be deleted');
        }

        // Soft delete
        $stmt = $db->prepare("UPDATE dropdown_categories SET is_active = 0 WHERE id = ?");
        $stmt->execute([$id]);

        // Also deactivate all values
        $stmt = $db->prepare("UPDATE dropdown_values SET is_active = 0 WHERE category_id = ?");
        $stmt->execute([$id]);

        sendResponse(true, 'Category deleted successfully');
    } catch (Exception $e) {
        sendResponse(false, 'Error deleting category', null, ['error' => $e->getMessage()]);
    }
}
?>
