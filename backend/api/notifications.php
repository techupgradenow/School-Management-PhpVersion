<?php
/**
 * Notifications API Endpoint
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
    case 'DELETE':
        handleDelete($db, $_GET);
        break;
    default:
        sendResponse(false, 'Method not allowed');
}

function handleGet($db, $params) {
    $action = $params['action'] ?? 'list';

    switch ($action) {
        case 'list':
            getNotificationsList($db, $params);
            break;
        case 'unread':
            getUnreadNotifications($db, $params);
            break;
        case 'count':
            getUnreadCount($db, $params);
            break;
        default:
            sendResponse(false, 'Invalid action');
    }
}

function getNotificationsList($db, $params) {
    $page = isset($params['page']) ? (int)$params['page'] : 1;
    $perPage = isset($params['perPage']) ? (int)$params['perPage'] : 20;
    $userId = $params['user_id'] ?? null;

    $where = [];
    $bindings = [];

    if ($userId) {
        $where[] = "(target_user_id = :user_id OR target_user_id IS NULL)";
        $bindings[':user_id'] = $userId;
    }

    $whereClause = !empty($where) ? 'WHERE ' . implode(' AND ', $where) : '';

    // Get total count
    $countQuery = "SELECT COUNT(*) as total FROM notifications $whereClause";
    $countStmt = $db->prepare($countQuery);
    $countStmt->execute($bindings);
    $total = $countStmt->fetch()['total'];

    // Get paginated records
    $offset = ($page - 1) * $perPage;
    $query = "SELECT * FROM notifications $whereClause ORDER BY created_at DESC LIMIT :limit OFFSET :offset";
    $stmt = $db->prepare($query);

    foreach ($bindings as $key => $value) {
        $stmt->bindValue($key, $value);
    }
    $stmt->bindValue(':limit', $perPage, PDO::PARAM_INT);
    $stmt->bindValue(':offset', $offset, PDO::PARAM_INT);

    $stmt->execute();
    $notifications = $stmt->fetchAll();

    sendResponse(true, 'Notifications fetched successfully', [
        'notifications' => $notifications,
        'pagination' => [
            'page' => $page,
            'perPage' => $perPage,
            'total' => $total,
            'totalPages' => ceil($total / $perPage)
        ]
    ]);
}

function getUnreadNotifications($db, $params) {
    $userId = $params['user_id'] ?? null;
    $limit = isset($params['limit']) ? (int)$params['limit'] : 10;

    $where = ["is_read = 0"];
    $bindings = [];

    if ($userId) {
        $where[] = "(target_user_id = :user_id OR target_user_id IS NULL)";
        $bindings[':user_id'] = $userId;
    }

    $whereClause = 'WHERE ' . implode(' AND ', $where);

    $query = "SELECT * FROM notifications $whereClause ORDER BY created_at DESC LIMIT :limit";
    $stmt = $db->prepare($query);

    foreach ($bindings as $key => $value) {
        $stmt->bindValue($key, $value);
    }
    $stmt->bindValue(':limit', $limit, PDO::PARAM_INT);

    $stmt->execute();
    $notifications = $stmt->fetchAll();

    sendResponse(true, 'Unread notifications fetched', ['notifications' => $notifications]);
}

function getUnreadCount($db, $params) {
    $userId = $params['user_id'] ?? null;

    $where = ["is_read = 0"];
    $bindings = [];

    if ($userId) {
        $where[] = "(target_user_id = :user_id OR target_user_id IS NULL)";
        $bindings[':user_id'] = $userId;
    }

    $whereClause = 'WHERE ' . implode(' AND ', $where);

    $query = "SELECT COUNT(*) as count FROM notifications $whereClause";
    $stmt = $db->prepare($query);
    $stmt->execute($bindings);
    $count = $stmt->fetch()['count'];

    sendResponse(true, 'Unread count fetched', ['count' => $count]);
}

function handlePost($db, $data) {
    $action = $data['action'] ?? 'create';

    switch ($action) {
        case 'create':
            createNotificationEntry($db, $data);
            break;
        case 'mark_read':
            markAsRead($db, $data);
            break;
        case 'mark_all_read':
            markAllAsRead($db, $data);
            break;
        default:
            sendResponse(false, 'Invalid action');
    }
}

function createNotificationEntry($db, $data) {
    $required = ['title', 'message'];
    $errors = validateRequired($data, $required);

    if (!empty($errors)) {
        sendResponse(false, 'Validation failed', null, $errors);
    }

    $title = sanitizeInput($data['title']);
    $message = sanitizeInput($data['message']);
    $type = isset($data['type']) ? sanitizeInput($data['type']) : 'info';
    $icon = isset($data['icon']) ? sanitizeInput($data['icon']) : 'fa-bell';
    $targetUserId = isset($data['target_user_id']) ? $data['target_user_id'] : null;
    $targetRole = isset($data['target_role']) ? sanitizeInput($data['target_role']) : null;

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

    $id = $db->lastInsertId();

    sendResponse(true, 'Notification created successfully', ['id' => $id]);
}

function markAsRead($db, $data) {
    if (empty($data['id'])) {
        sendResponse(false, 'Notification ID is required');
    }

    $stmt = $db->prepare("UPDATE notifications SET is_read = 1 WHERE id = :id");
    $stmt->execute([':id' => $data['id']]);

    sendResponse(true, 'Notification marked as read');
}

function markAllAsRead($db, $data) {
    $userId = $data['user_id'] ?? null;

    $where = [];
    $bindings = [];

    if ($userId) {
        $where[] = "(target_user_id = :user_id OR target_user_id IS NULL)";
        $bindings[':user_id'] = $userId;
    }

    $whereClause = !empty($where) ? 'WHERE ' . implode(' AND ', $where) : '';

    $stmt = $db->prepare("UPDATE notifications SET is_read = 1 $whereClause");
    $stmt->execute($bindings);

    sendResponse(true, 'All notifications marked as read');
}

function handlePut($db, $data) {
    if (empty($data['id'])) {
        sendResponse(false, 'Notification ID is required');
    }

    $id = (int)$data['id'];
    $fields = [];
    $bindings = [':id' => $id];

    if (isset($data['is_read'])) {
        $fields[] = "is_read = :is_read";
        $bindings[':is_read'] = $data['is_read'] ? 1 : 0;
    }

    if (empty($fields)) {
        sendResponse(false, 'No fields to update');
    }

    $query = "UPDATE notifications SET " . implode(', ', $fields) . " WHERE id = :id";
    $stmt = $db->prepare($query);
    $stmt->execute($bindings);

    sendResponse(true, 'Notification updated successfully');
}

function handleDelete($db, $params) {
    if (empty($params['id'])) {
        sendResponse(false, 'Notification ID is required');
    }

    $stmt = $db->prepare("DELETE FROM notifications WHERE id = :id");
    $stmt->execute([':id' => $params['id']]);

    sendResponse(true, 'Notification deleted successfully');
}
?>
