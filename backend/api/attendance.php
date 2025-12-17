<?php
/**
 * Attendance API Endpoint
 * EduManage Pro - School Management System
 *
 * Handles all CRUD operations for attendance
 */

require_once __DIR__ . '/../config/db.php';
require_once __DIR__ . '/../helpers/functions.php';

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

// Route requests based on method
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
        sendResponse(false, 'Method not allowed', null, ['method' => 'Unsupported HTTP method']);
}

/**
 * Handle GET requests
 */
function handleGet($db, $params) {
    try {
        $action = $params['action'] ?? 'list';

        switch ($action) {
            case 'list':
                getAttendanceList($db, $params);
                break;

            case 'student':
                getStudentAttendance($db, $params);
                break;

            case 'stats':
                getAttendanceStats($db, $params);
                break;

            case 'report':
                getAttendanceReport($db, $params);
                break;

            default:
                sendResponse(false, 'Invalid action');
        }
    } catch (Exception $e) {
        sendResponse(false, 'Error processing request', null, ['error' => $e->getMessage()]);
    }
}

/**
 * Get attendance list
 */
function getAttendanceList($db, $params) {
    $date = $params['date'] ?? date('Y-m-d');
    $class = $params['class'] ?? '';
    $section = $params['section'] ?? '';
    $status = $params['status'] ?? '';

    $where = ["DATE(date) = :date"];
    $bindings = [':date' => $date];

    if (!empty($class)) {
        $where[] = "students.class = :class";
        $bindings[':class'] = $class;
    }

    if (!empty($section)) {
        $where[] = "students.section = :section";
        $bindings[':section'] = $section;
    }

    if (!empty($status)) {
        $where[] = "attendance.status = :status";
        $bindings[':status'] = $status;
    }

    $whereClause = 'WHERE ' . implode(' AND ', $where);

    $query = "
        SELECT
            attendance.*,
            students.name as student_name,
            students.class,
            students.section,
            students.roll_no
        FROM attendance
        LEFT JOIN students ON attendance.student_id = students.id
        $whereClause
        ORDER BY students.class, students.section, students.roll_no
    ";

    $stmt = $db->prepare($query);
    $stmt->execute($bindings);
    $records = $stmt->fetchAll();

    sendResponse(true, 'Attendance records fetched successfully', ['records' => $records, 'date' => $date]);
}

/**
 * Get student attendance history
 */
function getStudentAttendance($db, $params) {
    if (empty($params['student_id'])) {
        sendResponse(false, 'Student ID is required');
    }

    $studentId = $params['student_id'];
    $startDate = $params['start_date'] ?? date('Y-m-d', strtotime('-30 days'));
    $endDate = $params['end_date'] ?? date('Y-m-d');

    $query = "
        SELECT * FROM attendance
        WHERE student_id = :student_id
        AND date BETWEEN :start_date AND :end_date
        ORDER BY date DESC
    ";

    $stmt = $db->prepare($query);
    $stmt->execute([
        ':student_id' => $studentId,
        ':start_date' => $startDate,
        ':end_date' => $endDate
    ]);

    $records = $stmt->fetchAll();

    sendResponse(true, 'Student attendance fetched successfully', [
        'student_id' => $studentId,
        'records' => $records,
        'start_date' => $startDate,
        'end_date' => $endDate
    ]);
}

/**
 * Get attendance statistics
 */
function getAttendanceStats($db, $params) {
    $date = $params['date'] ?? date('Y-m-d');
    $class = $params['class'] ?? '';
    $section = $params['section'] ?? '';

    $where = ["DATE(date) = :date"];
    $bindings = [':date' => $date];

    if (!empty($class)) {
        $where[] = "students.class = :class";
        $bindings[':class'] = $class;
    }

    if (!empty($section)) {
        $where[] = "students.section = :section";
        $bindings[':section'] = $section;
    }

    $whereClause = 'WHERE ' . implode(' AND ', $where);

    // Total students
    $totalQuery = "
        SELECT COUNT(DISTINCT student_id) as total
        FROM attendance
        LEFT JOIN students ON attendance.student_id = students.id
        $whereClause
    ";
    $totalStmt = $db->prepare($totalQuery);
    $totalStmt->execute($bindings);
    $total = $totalStmt->fetch()['total'];

    // Present count
    $presentWhere = $where;
    $presentWhere[] = "attendance.status = 'Present'";
    $presentWhereClause = 'WHERE ' . implode(' AND ', $presentWhere);

    $presentQuery = "
        SELECT COUNT(*) as total
        FROM attendance
        LEFT JOIN students ON attendance.student_id = students.id
        $presentWhereClause
    ";
    $presentStmt = $db->prepare($presentQuery);
    $presentStmt->execute($bindings);
    $present = $presentStmt->fetch()['total'];

    // Absent count
    $absentWhere = $where;
    $absentWhere[] = "attendance.status = 'Absent'";
    $absentWhereClause = 'WHERE ' . implode(' AND ', $absentWhere);

    $absentQuery = "
        SELECT COUNT(*) as total
        FROM attendance
        LEFT JOIN students ON attendance.student_id = students.id
        $absentWhereClause
    ";
    $absentStmt = $db->prepare($absentQuery);
    $absentStmt->execute($bindings);
    $absent = $absentStmt->fetch()['total'];

    // Late count
    $lateWhere = $where;
    $lateWhere[] = "attendance.status = 'Late'";
    $lateWhereClause = 'WHERE ' . implode(' AND ', $lateWhere);

    $lateQuery = "
        SELECT COUNT(*) as total
        FROM attendance
        LEFT JOIN students ON attendance.student_id = students.id
        $lateWhereClause
    ";
    $lateStmt = $db->prepare($lateQuery);
    $lateStmt->execute($bindings);
    $late = $lateStmt->fetch()['total'];

    $percentage = $total > 0 ? round(($present / $total) * 100, 2) : 0;

    $stats = [
        'date' => $date,
        'total' => $total,
        'present' => $present,
        'absent' => $absent,
        'late' => $late,
        'percentage' => $percentage
    ];

    sendResponse(true, 'Statistics fetched successfully', $stats);
}

/**
 * Get attendance report (monthly/weekly)
 */
function getAttendanceReport($db, $params) {
    $startDate = $params['start_date'] ?? date('Y-m-01');
    $endDate = $params['end_date'] ?? date('Y-m-t');
    $studentId = $params['student_id'] ?? null;

    if ($studentId) {
        // Individual student report
        $query = "
            SELECT
                DATE(date) as date,
                status,
                remarks
            FROM attendance
            WHERE student_id = :student_id
            AND date BETWEEN :start_date AND :end_date
            ORDER BY date ASC
        ";

        $stmt = $db->prepare($query);
        $stmt->execute([
            ':student_id' => $studentId,
            ':start_date' => $startDate,
            ':end_date' => $endDate
        ]);
    } else {
        // Overall report
        $query = "
            SELECT
                DATE(date) as date,
                COUNT(*) as total,
                SUM(CASE WHEN status = 'Present' THEN 1 ELSE 0 END) as present,
                SUM(CASE WHEN status = 'Absent' THEN 1 ELSE 0 END) as absent,
                SUM(CASE WHEN status = 'Late' THEN 1 ELSE 0 END) as late
            FROM attendance
            WHERE date BETWEEN :start_date AND :end_date
            GROUP BY DATE(date)
            ORDER BY date ASC
        ";

        $stmt = $db->prepare($query);
        $stmt->execute([
            ':start_date' => $startDate,
            ':end_date' => $endDate
        ]);
    }

    $report = $stmt->fetchAll();

    sendResponse(true, 'Report generated successfully', [
        'report' => $report,
        'start_date' => $startDate,
        'end_date' => $endDate
    ]);
}

/**
 * Handle POST - Mark attendance
 */
function handlePost($db, $data) {
    try {
        // Validate
        if (empty($data['records']) || !is_array($data['records'])) {
            sendResponse(false, 'Attendance records are required');
        }

        $date = $data['date'] ?? date('Y-m-d');
        $markedBy = getCurrentUserId();

        $db->beginTransaction();

        $successCount = 0;
        $errors = [];

        foreach ($data['records'] as $record) {
            if (empty($record['student_id']) || empty($record['status'])) {
                $errors[] = "Missing student_id or status";
                continue;
            }

            $studentId = sanitizeInput($record['student_id']);
            $status = sanitizeInput($record['status']);
            $remarks = isset($record['remarks']) ? sanitizeInput($record['remarks']) : null;

            // Check if attendance already exists
            $checkStmt = $db->prepare("
                SELECT id FROM attendance
                WHERE student_id = :student_id AND DATE(date) = :date
            ");
            $checkStmt->execute([
                ':student_id' => $studentId,
                ':date' => $date
            ]);

            if ($checkStmt->fetch()) {
                // Update existing
                $updateStmt = $db->prepare("
                    UPDATE attendance
                    SET status = :status, remarks = :remarks, marked_by = :marked_by, marked_at = NOW()
                    WHERE student_id = :student_id AND DATE(date) = :date
                ");
                $updateStmt->execute([
                    ':status' => $status,
                    ':remarks' => $remarks,
                    ':marked_by' => $markedBy,
                    ':student_id' => $studentId,
                    ':date' => $date
                ]);
            } else {
                // Insert new
                $insertStmt = $db->prepare("
                    INSERT INTO attendance (student_id, date, status, remarks, marked_by)
                    VALUES (:student_id, :date, :status, :remarks, :marked_by)
                ");
                $insertStmt->execute([
                    ':student_id' => $studentId,
                    ':date' => $date,
                    ':status' => $status,
                    ':remarks' => $remarks,
                    ':marked_by' => $markedBy
                ]);
            }

            $successCount++;
        }

        $db->commit();

        // Log activity
        logActivity($db, $markedBy, 'Marked attendance', 'Attendance', [
            'date' => $date,
            'count' => $successCount
        ]);

        sendResponse(true, "Attendance marked successfully for $successCount student(s)", [
            'success_count' => $successCount,
            'errors' => $errors
        ]);

    } catch (Exception $e) {
        if ($db->inTransaction()) {
            $db->rollBack();
        }
        sendResponse(false, 'Error marking attendance', null, ['error' => $e->getMessage()]);
    }
}

/**
 * Handle PUT - Update attendance
 */
function handlePut($db, $data) {
    try {
        if (empty($data['id'])) {
            sendResponse(false, 'Attendance ID is required');
        }

        $id = (int)$data['id'];
        $fields = [];
        $bindings = [':id' => $id];

        $updateableFields = ['status', 'remarks'];

        foreach ($updateableFields as $field) {
            if (isset($data[$field])) {
                $fields[] = "$field = :$field";
                $bindings[":$field"] = sanitizeInput($data[$field]);
            }
        }

        if (empty($fields)) {
            sendResponse(false, 'No fields to update');
        }

        $query = "UPDATE attendance SET " . implode(', ', $fields) . " WHERE id = :id";
        $stmt = $db->prepare($query);
        $stmt->execute($bindings);

        if ($stmt->rowCount() === 0) {
            sendResponse(false, 'Attendance record not found');
        }

        // Log activity
        logActivity($db, getCurrentUserId(), 'Updated attendance', 'Attendance', ['id' => $id]);

        sendResponse(true, 'Attendance updated successfully', ['id' => $id]);

    } catch (Exception $e) {
        sendResponse(false, 'Error updating attendance', null, ['error' => $e->getMessage()]);
    }
}

/**
 * Handle DELETE - Delete attendance
 */
function handleDelete($db, $params) {
    try {
        if (empty($params['id'])) {
            sendResponse(false, 'Attendance ID is required');
        }

        $id = (int)$params['id'];

        $stmt = $db->prepare("DELETE FROM attendance WHERE id = :id");
        $stmt->execute([':id' => $id]);

        if ($stmt->rowCount() === 0) {
            sendResponse(false, 'Attendance record not found');
        }

        // Log activity
        logActivity($db, getCurrentUserId(), 'Deleted attendance', 'Attendance', ['id' => $id]);

        sendResponse(true, 'Attendance deleted successfully', ['id' => $id]);

    } catch (Exception $e) {
        sendResponse(false, 'Error deleting attendance', null, ['error' => $e->getMessage()]);
    }
}
?>
