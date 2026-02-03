<?php
/**
 * Attendance API Endpoint
 * EduManage Pro - School Management System
 *
 * Handles all CRUD operations for attendance
 * Protected by Permission Guard - enforces strict RBAC
 */

require_once __DIR__ . '/../config/db.php';
require_once __DIR__ . '/../helpers/functions.php';
require_once __DIR__ . '/../helpers/permission_guard.php';
require_once __DIR__ . '/../helpers/TenantContext.php';

// Start session for permission checks
if (session_status() === PHP_SESSION_NONE) {
    session_start();
}

/**
 * Get current school ID from session/context
 * CRITICAL: All queries must filter by school_id for multi-tenant isolation
 * Optimized: Uses static cache and accepts optional db parameter
 */
function getCurrentSchoolId($existingDb = null) {
    static $cachedSchoolId = null;

    if ($cachedSchoolId !== null) {
        return $cachedSchoolId;
    }

    if (isset($_SESSION['school_id']) && !empty($_SESSION['school_id'])) {
        $cachedSchoolId = $_SESSION['school_id'];
        return $cachedSchoolId;
    }

    $db = $existingDb ?? (function_exists('getDB') ? getDB() : null);

    if ($db) {
        try {
            $tenant = TenantContext::getInstance($db);
            $schoolId = $tenant->getSchoolId();
            if ($schoolId) {
                $cachedSchoolId = $schoolId;
                return $cachedSchoolId;
            }
        } catch (Exception $e) {}

        try {
            $stmt = $db->query("SELECT id FROM schools WHERE is_active = 1 LIMIT 1");
            $school = $stmt->fetch();
            if ($school) {
                $_SESSION['school_id'] = $school['id'];
                $cachedSchoolId = $school['id'];
                return $cachedSchoolId;
            }
        } catch (Exception $e) {}
    }

    return null;
}

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

// Require authentication and module access for ALL requests
requireAuth();
requireModuleAccess('attendance');

// Route requests based on method with permission checks
switch ($method) {
    case 'GET':
        requirePermission('attendance', 'view');
        handleGet($db, $_GET);
        break;

    case 'POST':
        requirePermission('attendance', 'create');
        handlePost($db, $data);
        break;

    case 'PUT':
        requirePermission('attendance', 'edit');
        handlePut($db, $data);
        break;

    case 'DELETE':
        requirePermission('attendance', 'delete');
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
 * IMPORTANT: Filters by school_id for multi-tenant isolation
 */
function getAttendanceList($db, $params) {
    $date = $params['date'] ?? date('Y-m-d');
    $class = $params['class'] ?? '';
    $section = $params['section'] ?? '';
    $status = $params['status'] ?? '';
    $schoolId = getCurrentSchoolId();

    $where = ["DATE(attendance.date) = :date"];
    $bindings = [':date' => $date];

    // CRITICAL: Always filter by school_id for data isolation
    if ($schoolId) {
        $where[] = "attendance.school_id = :school_id";
        $bindings[':school_id'] = $schoolId;
    }

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
        LEFT JOIN students ON attendance.student_id = students.id" .
        ($schoolId ? " AND students.school_id = attendance.school_id" : "") . "
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
 * IMPORTANT: Filters by school_id for multi-tenant isolation
 */
function getStudentAttendance($db, $params) {
    if (empty($params['student_id'])) {
        sendResponse(false, 'Student ID is required');
    }

    $studentId = $params['student_id'];
    $startDate = $params['start_date'] ?? date('Y-m-d', strtotime('-30 days'));
    $endDate = $params['end_date'] ?? date('Y-m-d');
    $schoolId = getCurrentSchoolId();

    $sql = "SELECT * FROM attendance WHERE student_id = :student_id AND date BETWEEN :start_date AND :end_date";
    $bindings = [
        ':student_id' => $studentId,
        ':start_date' => $startDate,
        ':end_date' => $endDate
    ];

    if ($schoolId) {
        $sql .= " AND school_id = :school_id";
        $bindings[':school_id'] = $schoolId;
    }

    $sql .= " ORDER BY date DESC";

    $stmt = $db->prepare($sql);
    $stmt->execute($bindings);

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
 * Optimized: Single query with conditional aggregation (was 4 separate queries)
 */
function getAttendanceStats($db, $params) {
    $date = $params['date'] ?? date('Y-m-d');
    $class = $params['class'] ?? '';
    $section = $params['section'] ?? '';
    $schoolId = getCurrentSchoolId($db);

    $where = ["DATE(attendance.date) = :date"];
    $bindings = [':date' => $date];

    if ($schoolId) {
        $where[] = "attendance.school_id = :school_id";
        $bindings[':school_id'] = $schoolId;
    }

    if (!empty($class)) {
        $where[] = "students.class = :class";
        $bindings[':class'] = $class;
    }

    if (!empty($section)) {
        $where[] = "students.section = :section";
        $bindings[':section'] = $section;
    }

    $whereClause = 'WHERE ' . implode(' AND ', $where);

    // Single optimized query with conditional aggregation
    $query = "
        SELECT
            COUNT(DISTINCT attendance.student_id) as total,
            SUM(CASE WHEN attendance.status = 'Present' THEN 1 ELSE 0 END) as present,
            SUM(CASE WHEN attendance.status = 'Absent' THEN 1 ELSE 0 END) as absent,
            SUM(CASE WHEN attendance.status = 'Late' THEN 1 ELSE 0 END) as late
        FROM attendance
        LEFT JOIN students ON attendance.student_id = students.id
        $whereClause
    ";

    $stmt = $db->prepare($query);
    $stmt->execute($bindings);
    $result = $stmt->fetch();

    $total = (int)$result['total'];
    $present = (int)$result['present'];
    $absent = (int)$result['absent'];
    $late = (int)$result['late'];
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
