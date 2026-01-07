<?php
/**
 * Activity History API Endpoint
 * EduManage Pro - School Management System
 *
 * Provides access to activity history logs for all modules
 */

require_once __DIR__ . '/../config/db.php';
require_once __DIR__ . '/../helpers/functions.php';
require_once __DIR__ . '/../helpers/ActivityLogger.php';

// Get request method
$method = $_SERVER['REQUEST_METHOD'];

// Get request data
$input = file_get_contents('php://input');
$data = json_decode($input, true);

// Get database connection
try {
    $db = getDB();
    $activityLogger = new ActivityLogger($db);
} catch (Exception $e) {
    sendResponse(false, 'Database connection failed', null, ['database' => $e->getMessage()]);
}

// Only GET method is allowed for viewing history
if ($method !== 'GET') {
    sendResponse(false, 'Method not allowed', null, ['method' => 'Only GET is supported']);
}

// Handle request
$action = $_GET['action'] ?? 'list';

switch ($action) {
    case 'list':
        getActivityList($db, $_GET);
        break;

    case 'entity':
        getEntityHistory($db, $_GET, $activityLogger);
        break;

    case 'recent':
        getRecentActivity($db, $_GET, $activityLogger);
        break;

    case 'stats':
        getModuleStats($db, $_GET, $activityLogger);
        break;

    case 'user':
        getUserActivity($db, $_GET, $activityLogger);
        break;

    case 'search':
        searchHistory($db, $_GET, $activityLogger);
        break;

    case 'summary':
        getSummary($db);
        break;

    default:
        sendResponse(false, 'Invalid action', null, ['action' => 'Unknown action']);
}

/**
 * Get paginated activity list
 */
function getActivityList($db, $params) {
    $page = isset($params['page']) ? (int)$params['page'] : 1;
    $perPage = isset($params['perPage']) ? (int)$params['perPage'] : 20;
    $module = $params['module'] ?? '';
    $action = $params['filter_action'] ?? '';
    $dateFrom = $params['date_from'] ?? '';
    $dateTo = $params['date_to'] ?? '';

    // Build query
    $where = ["status = 'SUCCESS'"];
    $bindings = [];

    if (!empty($module)) {
        $where[] = "module = :module";
        $bindings[':module'] = $module;
    }

    if (!empty($action)) {
        $where[] = "action = :action";
        $bindings[':action'] = $action;
    }

    if (!empty($dateFrom)) {
        $where[] = "DATE(created_at) >= :date_from";
        $bindings[':date_from'] = $dateFrom;
    }

    if (!empty($dateTo)) {
        $where[] = "DATE(created_at) <= :date_to";
        $bindings[':date_to'] = $dateTo;
    }

    $whereClause = 'WHERE ' . implode(' AND ', $where);

    // Get total count
    $countQuery = "SELECT COUNT(*) as total FROM activity_history $whereClause";
    $countStmt = $db->prepare($countQuery);
    $countStmt->execute($bindings);
    $totalRecords = $countStmt->fetch()['total'];

    // Get paginated records
    $offset = ($page - 1) * $perPage;
    $query = "SELECT id, module, entity_id, entity_name, action, changed_fields,
              performed_by, performed_by_name, user_role, ip_address,
              description, created_at
              FROM activity_history $whereClause
              ORDER BY created_at DESC
              LIMIT :limit OFFSET :offset";

    $stmt = $db->prepare($query);

    foreach ($bindings as $key => $value) {
        $stmt->bindValue($key, $value);
    }
    $stmt->bindValue(':limit', $perPage, PDO::PARAM_INT);
    $stmt->bindValue(':offset', $offset, PDO::PARAM_INT);

    $stmt->execute();
    $activities = $stmt->fetchAll();

    // Parse JSON fields
    foreach ($activities as &$activity) {
        if (!empty($activity['changed_fields'])) {
            $activity['changed_fields'] = json_decode($activity['changed_fields'], true);
        }
    }

    $response = [
        'activities' => $activities,
        'pagination' => [
            'page' => $page,
            'perPage' => $perPage,
            'total' => $totalRecords,
            'totalPages' => ceil($totalRecords / $perPage)
        ]
    ];

    sendResponse(true, 'Activity history fetched successfully', $response);
}

/**
 * Get history for a specific entity
 */
function getEntityHistory($db, $params, $activityLogger) {
    if (empty($params['module']) || empty($params['entity_id'])) {
        sendResponse(false, 'Module and entity_id are required', null, ['params' => 'Missing']);
    }

    $limit = isset($params['limit']) ? (int)$params['limit'] : 50;
    $history = $activityLogger->getEntityHistory($params['module'], $params['entity_id'], $limit);

    // Parse JSON fields
    foreach ($history as &$record) {
        if (!empty($record['old_data'])) {
            $record['old_data'] = json_decode($record['old_data'], true);
        }
        if (!empty($record['new_data'])) {
            $record['new_data'] = json_decode($record['new_data'], true);
        }
        if (!empty($record['changed_fields'])) {
            $record['changed_fields'] = json_decode($record['changed_fields'], true);
        }
    }

    sendResponse(true, 'Entity history fetched successfully', $history);
}

/**
 * Get recent activity across all modules
 */
function getRecentActivity($db, $params, $activityLogger) {
    $limit = isset($params['limit']) ? (int)$params['limit'] : 50;
    $module = $params['module'] ?? null;

    $activities = $activityLogger->getRecentActivity($limit, $module);

    sendResponse(true, 'Recent activity fetched successfully', $activities);
}

/**
 * Get module statistics
 */
function getModuleStats($db, $params, $activityLogger) {
    if (empty($params['module'])) {
        sendResponse(false, 'Module is required', null, ['module' => 'Missing']);
    }

    $days = isset($params['days']) ? (int)$params['days'] : 30;
    $stats = $activityLogger->getModuleStats($params['module'], $days);

    sendResponse(true, 'Module statistics fetched successfully', $stats);
}

/**
 * Get user activity
 */
function getUserActivity($db, $params, $activityLogger) {
    if (empty($params['user_id'])) {
        sendResponse(false, 'User ID is required', null, ['user_id' => 'Missing']);
    }

    $days = isset($params['days']) ? (int)$params['days'] : 30;
    $activity = $activityLogger->getUserActivity($params['user_id'], $days);

    sendResponse(true, 'User activity fetched successfully', $activity);
}

/**
 * Search activity history
 */
function searchHistory($db, $params, $activityLogger) {
    $searchTerm = $params['q'] ?? '';
    $limit = isset($params['limit']) ? (int)$params['limit'] : 50;
    $offset = isset($params['offset']) ? (int)$params['offset'] : 0;

    $filters = [
        'module' => $params['module'] ?? null,
        'action' => $params['filter_action'] ?? null,
        'user_id' => $params['user_id'] ?? null,
        'date_from' => $params['date_from'] ?? null,
        'date_to' => $params['date_to'] ?? null
    ];

    $results = $activityLogger->searchHistory($searchTerm, $filters, $limit, $offset);

    // Parse JSON fields
    foreach ($results as &$record) {
        if (!empty($record['old_data'])) {
            $record['old_data'] = json_decode($record['old_data'], true);
        }
        if (!empty($record['new_data'])) {
            $record['new_data'] = json_decode($record['new_data'], true);
        }
        if (!empty($record['changed_fields'])) {
            $record['changed_fields'] = json_decode($record['changed_fields'], true);
        }
    }

    sendResponse(true, 'Search completed', $results);
}

/**
 * Get activity summary dashboard
 */
function getSummary($db) {
    try {
        // Total activities
        $totalStmt = $db->query("SELECT COUNT(*) as total FROM activity_history WHERE status = 'SUCCESS'");
        $total = $totalStmt->fetch()['total'];

        // Activities today
        $todayStmt = $db->query("SELECT COUNT(*) as total FROM activity_history WHERE DATE(created_at) = CURDATE() AND status = 'SUCCESS'");
        $today = $todayStmt->fetch()['total'];

        // Activities this week
        $weekStmt = $db->query("SELECT COUNT(*) as total FROM activity_history WHERE created_at >= DATE_SUB(CURDATE(), INTERVAL 7 DAY) AND status = 'SUCCESS'");
        $thisWeek = $weekStmt->fetch()['total'];

        // By action type
        $actionStmt = $db->query("
            SELECT action, COUNT(*) as count
            FROM activity_history
            WHERE status = 'SUCCESS'
            GROUP BY action
            ORDER BY count DESC
        ");
        $byAction = $actionStmt->fetchAll();

        // By module
        $moduleStmt = $db->query("
            SELECT module, COUNT(*) as count
            FROM activity_history
            WHERE status = 'SUCCESS'
            GROUP BY module
            ORDER BY count DESC
            LIMIT 10
        ");
        $byModule = $moduleStmt->fetchAll();

        // Most active users
        $userStmt = $db->query("
            SELECT performed_by_name, user_role, COUNT(*) as count
            FROM activity_history
            WHERE status = 'SUCCESS' AND performed_by_name IS NOT NULL
            GROUP BY performed_by_name, user_role
            ORDER BY count DESC
            LIMIT 5
        ");
        $topUsers = $userStmt->fetchAll();

        // Recent activity (last 10)
        $recentStmt = $db->query("
            SELECT module, entity_name, action, performed_by_name, description, created_at
            FROM activity_history
            WHERE status = 'SUCCESS'
            ORDER BY created_at DESC
            LIMIT 10
        ");
        $recentActivity = $recentStmt->fetchAll();

        $summary = [
            'totals' => [
                'all_time' => $total,
                'today' => $today,
                'this_week' => $thisWeek
            ],
            'by_action' => $byAction,
            'by_module' => $byModule,
            'top_users' => $topUsers,
            'recent_activity' => $recentActivity
        ];

        sendResponse(true, 'Summary fetched successfully', $summary);

    } catch (Exception $e) {
        sendResponse(false, 'Error fetching summary', null, ['error' => $e->getMessage()]);
    }
}
?>
