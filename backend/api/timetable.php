<?php
/**
 * Timetable API Endpoint
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
            getTimetableList($db, $params);
            break;
        case 'class':
            getClassTimetable($db, $params);
            break;
        case 'teacher':
            getTeacherTimetable($db, $params);
            break;
        case 'periods':
            getPeriods($db, $params);
            break;
        default:
            sendResponse(false, 'Invalid action');
    }
}

function getTimetableList($db, $params) {
    $class = $params['class'] ?? '';
    $section = $params['section'] ?? '';
    $day = $params['day'] ?? '';

    $where = [];
    $bindings = [];

    if (!empty($class)) {
        $where[] = "t.class = :class";
        $bindings[':class'] = $class;
    }

    if (!empty($section)) {
        $where[] = "t.section = :section";
        $bindings[':section'] = $section;
    }

    if (!empty($day)) {
        $where[] = "t.day_of_week = :day";
        $bindings[':day'] = $day;
    }

    $whereClause = !empty($where) ? 'WHERE ' . implode(' AND ', $where) : '';

    $query = "
        SELECT
            t.*,
            s.name as subject_name,
            te.name as teacher_name
        FROM timetable t
        LEFT JOIN subjects s ON t.subject_id = s.id
        LEFT JOIN teachers te ON t.teacher_id = te.id
        $whereClause
        ORDER BY t.day_of_week, t.period_number
    ";

    $stmt = $db->prepare($query);
    $stmt->execute($bindings);
    $timetable = $stmt->fetchAll();

    sendResponse(true, 'Timetable fetched successfully', ['timetable' => $timetable]);
}

function getClassTimetable($db, $params) {
    if (empty($params['class'])) {
        sendResponse(false, 'Class is required');
    }

    $class = $params['class'];
    $section = $params['section'] ?? 'A';

    $query = "
        SELECT
            t.*,
            s.name as subject_name,
            s.code as subject_code,
            te.name as teacher_name
        FROM timetable t
        LEFT JOIN subjects s ON t.subject_id = s.id
        LEFT JOIN teachers te ON t.teacher_id = te.id
        WHERE t.class = :class AND t.section = :section
        ORDER BY
            FIELD(t.day_of_week, 'Monday', 'Tuesday', 'Wednesday', 'Thursday', 'Friday', 'Saturday'),
            t.period_number
    ";

    $stmt = $db->prepare($query);
    $stmt->execute([':class' => $class, ':section' => $section]);
    $records = $stmt->fetchAll();

    // Organize by day
    $timetable = [
        'Monday' => [],
        'Tuesday' => [],
        'Wednesday' => [],
        'Thursday' => [],
        'Friday' => [],
        'Saturday' => []
    ];

    foreach ($records as $record) {
        $day = $record['day_of_week'];
        if (isset($timetable[$day])) {
            $timetable[$day][] = $record;
        }
    }

    sendResponse(true, 'Class timetable fetched successfully', [
        'class' => $class,
        'section' => $section,
        'timetable' => $timetable
    ]);
}

function getTeacherTimetable($db, $params) {
    if (empty($params['teacher_id'])) {
        sendResponse(false, 'Teacher ID is required');
    }

    $teacherId = $params['teacher_id'];

    $query = "
        SELECT
            t.*,
            s.name as subject_name,
            s.code as subject_code
        FROM timetable t
        LEFT JOIN subjects s ON t.subject_id = s.id
        WHERE t.teacher_id = :teacher_id
        ORDER BY
            FIELD(t.day_of_week, 'Monday', 'Tuesday', 'Wednesday', 'Thursday', 'Friday', 'Saturday'),
            t.period_number
    ";

    $stmt = $db->prepare($query);
    $stmt->execute([':teacher_id' => $teacherId]);
    $records = $stmt->fetchAll();

    // Organize by day
    $timetable = [
        'Monday' => [],
        'Tuesday' => [],
        'Wednesday' => [],
        'Thursday' => [],
        'Friday' => [],
        'Saturday' => []
    ];

    foreach ($records as $record) {
        $day = $record['day_of_week'];
        if (isset($timetable[$day])) {
            $timetable[$day][] = $record;
        }
    }

    sendResponse(true, 'Teacher timetable fetched successfully', [
        'teacher_id' => $teacherId,
        'timetable' => $timetable
    ]);
}

function getPeriods($db, $params) {
    $query = "SELECT * FROM periods ORDER BY period_number";
    $stmt = $db->query($query);
    $periods = $stmt->fetchAll();

    sendResponse(true, 'Periods fetched successfully', ['periods' => $periods]);
}

function handlePost($db, $data) {
    $action = $data['action'] ?? 'create';

    switch ($action) {
        case 'create':
            createTimetableEntry($db, $data);
            break;
        case 'bulk_create':
            bulkCreateTimetable($db, $data);
            break;
        default:
            sendResponse(false, 'Invalid action');
    }
}

function createTimetableEntry($db, $data) {
    $required = ['class', 'section', 'day_of_week', 'period_number', 'subject_id', 'teacher_id'];
    $errors = validateRequired($data, $required);

    if (!empty($errors)) {
        sendResponse(false, 'Validation failed', null, $errors);
    }

    // Check for conflicts
    $conflictCheck = $db->prepare("
        SELECT id FROM timetable
        WHERE class = :class AND section = :section
        AND day_of_week = :day AND period_number = :period
    ");
    $conflictCheck->execute([
        ':class' => $data['class'],
        ':section' => $data['section'],
        ':day' => $data['day_of_week'],
        ':period' => $data['period_number']
    ]);

    if ($conflictCheck->fetch()) {
        sendResponse(false, 'Timetable entry already exists for this slot');
    }

    // Check teacher availability
    $teacherCheck = $db->prepare("
        SELECT id FROM timetable
        WHERE teacher_id = :teacher_id
        AND day_of_week = :day AND period_number = :period
    ");
    $teacherCheck->execute([
        ':teacher_id' => $data['teacher_id'],
        ':day' => $data['day_of_week'],
        ':period' => $data['period_number']
    ]);

    if ($teacherCheck->fetch()) {
        sendResponse(false, 'Teacher is already assigned to another class at this time');
    }

    $stmt = $db->prepare("
        INSERT INTO timetable (class, section, day_of_week, period_number, subject_id, teacher_id, room)
        VALUES (:class, :section, :day_of_week, :period_number, :subject_id, :teacher_id, :room)
    ");

    $stmt->execute([
        ':class' => sanitizeInput($data['class']),
        ':section' => sanitizeInput($data['section']),
        ':day_of_week' => sanitizeInput($data['day_of_week']),
        ':period_number' => (int)$data['period_number'],
        ':subject_id' => (int)$data['subject_id'],
        ':teacher_id' => sanitizeInput($data['teacher_id']),
        ':room' => isset($data['room']) ? sanitizeInput($data['room']) : null
    ]);

    $id = $db->lastInsertId();

    logActivity($db, getCurrentUserId(), 'Created timetable entry', 'Timetable', ['id' => $id]);

    sendResponse(true, 'Timetable entry created successfully', ['id' => $id]);
}

function bulkCreateTimetable($db, $data) {
    if (empty($data['entries']) || !is_array($data['entries'])) {
        sendResponse(false, 'Entries array is required');
    }

    $db->beginTransaction();
    $successCount = 0;
    $errors = [];

    try {
        foreach ($data['entries'] as $entry) {
            $stmt = $db->prepare("
                INSERT INTO timetable (class, section, day_of_week, period_number, subject_id, teacher_id, room)
                VALUES (:class, :section, :day_of_week, :period_number, :subject_id, :teacher_id, :room)
                ON DUPLICATE KEY UPDATE
                    subject_id = VALUES(subject_id),
                    teacher_id = VALUES(teacher_id),
                    room = VALUES(room)
            ");

            $stmt->execute([
                ':class' => sanitizeInput($entry['class']),
                ':section' => sanitizeInput($entry['section']),
                ':day_of_week' => sanitizeInput($entry['day_of_week']),
                ':period_number' => (int)$entry['period_number'],
                ':subject_id' => (int)$entry['subject_id'],
                ':teacher_id' => sanitizeInput($entry['teacher_id']),
                ':room' => isset($entry['room']) ? sanitizeInput($entry['room']) : null
            ]);

            $successCount++;
        }

        $db->commit();

        logActivity($db, getCurrentUserId(), 'Bulk created timetable', 'Timetable', ['count' => $successCount]);

        sendResponse(true, "Successfully created $successCount timetable entries", ['count' => $successCount]);

    } catch (Exception $e) {
        $db->rollBack();
        sendResponse(false, 'Error creating timetable', null, ['error' => $e->getMessage()]);
    }
}

function handlePut($db, $data) {
    if (empty($data['id'])) {
        sendResponse(false, 'Timetable ID is required');
    }

    $id = (int)$data['id'];
    $fields = [];
    $bindings = [':id' => $id];

    $updateableFields = ['subject_id', 'teacher_id', 'room'];

    foreach ($updateableFields as $field) {
        if (isset($data[$field])) {
            $fields[] = "$field = :$field";
            $bindings[":$field"] = sanitizeInput($data[$field]);
        }
    }

    if (empty($fields)) {
        sendResponse(false, 'No fields to update');
    }

    $query = "UPDATE timetable SET " . implode(', ', $fields) . " WHERE id = :id";
    $stmt = $db->prepare($query);
    $stmt->execute($bindings);

    logActivity($db, getCurrentUserId(), 'Updated timetable entry', 'Timetable', ['id' => $id]);

    sendResponse(true, 'Timetable entry updated successfully', ['id' => $id]);
}

function handleDelete($db, $params) {
    if (empty($params['id'])) {
        sendResponse(false, 'Timetable ID is required');
    }

    $id = (int)$params['id'];

    $stmt = $db->prepare("DELETE FROM timetable WHERE id = :id");
    $stmt->execute([':id' => $id]);

    if ($stmt->rowCount() === 0) {
        sendResponse(false, 'Timetable entry not found');
    }

    logActivity($db, getCurrentUserId(), 'Deleted timetable entry', 'Timetable', ['id' => $id]);

    sendResponse(true, 'Timetable entry deleted successfully');
}
?>
