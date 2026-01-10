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
        // Teacher Absence Management
        case 'absences':
            getTeacherAbsences($db, $params);
            break;
        case 'absence':
            getAbsenceDetails($db, $params);
            break;
        // Substitution Management
        case 'substitutions':
            getSubstitutions($db, $params);
            break;
        case 'available_teachers':
            getAvailableTeachersForSubstitution($db, $params);
            break;
        case 'today_absences':
            getTodayAbsences($db, $params);
            break;
        case 'pending_substitutions':
            getPendingSubstitutions($db, $params);
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
        // Teacher Absence
        case 'mark_absence':
            markTeacherAbsence($db, $data);
            break;
        case 'approve_absence':
            approveTeacherAbsence($db, $data);
            break;
        case 'cancel_absence':
            cancelTeacherAbsence($db, $data);
            break;
        // Substitution
        case 'assign_substitute':
            assignSubstituteTeacher($db, $data);
            break;
        case 'auto_assign_substitutes':
            autoAssignSubstitutes($db, $data);
            break;
        case 'cancel_substitution':
            cancelSubstitution($db, $data);
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

// ============================================
// TEACHER ABSENCE MANAGEMENT
// ============================================

/**
 * Get teacher absences list
 */
function getTeacherAbsences($db, $params) {
    $teacherId = $params['teacher_id'] ?? null;
    $status = $params['status'] ?? null;
    $startDate = $params['start_date'] ?? null;
    $endDate = $params['end_date'] ?? null;

    $where = [];
    $bindings = [];

    if ($teacherId) {
        $where[] = "ta.teacher_id = :teacher_id";
        $bindings[':teacher_id'] = $teacherId;
    }

    if ($status) {
        $where[] = "ta.status = :status";
        $bindings[':status'] = $status;
    }

    if ($startDate) {
        $where[] = "ta.start_date >= :start_date";
        $bindings[':start_date'] = $startDate;
    }

    if ($endDate) {
        $where[] = "ta.end_date <= :end_date";
        $bindings[':end_date'] = $endDate;
    }

    $whereClause = !empty($where) ? 'WHERE ' . implode(' AND ', $where) : '';

    $query = "
        SELECT
            ta.*,
            t.name as teacher_name,
            t.employee_id,
            t.department,
            (SELECT COUNT(*) FROM substitution_records sr WHERE sr.absence_id = ta.id) as substitution_count
        FROM teacher_absences ta
        LEFT JOIN teachers t ON ta.teacher_id = t.id
        $whereClause
        ORDER BY ta.start_date DESC
    ";

    $stmt = $db->prepare($query);
    $stmt->execute($bindings);
    $absences = $stmt->fetchAll();

    sendResponse(true, 'Teacher absences fetched successfully', ['absences' => $absences]);
}

/**
 * Get absence details with affected periods
 */
function getAbsenceDetails($db, $params) {
    if (empty($params['id'])) {
        sendResponse(false, 'Absence ID is required');
    }

    $id = (int)$params['id'];

    // Get absence details
    $stmt = $db->prepare("
        SELECT
            ta.*,
            t.name as teacher_name,
            t.employee_id,
            t.department
        FROM teacher_absences ta
        LEFT JOIN teachers t ON ta.teacher_id = t.id
        WHERE ta.id = :id
    ");
    $stmt->execute([':id' => $id]);
    $absence = $stmt->fetch();

    if (!$absence) {
        sendResponse(false, 'Absence not found');
    }

    // Get affected timetable entries
    $periodStmt = $db->prepare("
        SELECT
            tt.*,
            s.name as subject_name,
            sr.id as substitution_id,
            sr.substitute_teacher_id,
            sr.status as substitution_status,
            st.name as substitute_teacher_name
        FROM timetable tt
        LEFT JOIN subjects s ON tt.subject_id = s.id
        LEFT JOIN substitution_records sr ON sr.original_teacher_id = tt.teacher_id
            AND sr.substitution_date BETWEEN :start_date AND :end_date
            AND sr.class_id = tt.class
            AND sr.section_id = tt.section
        LEFT JOIN teachers st ON sr.substitute_teacher_id = st.id
        WHERE tt.teacher_id = :teacher_id
        ORDER BY FIELD(tt.day_of_week, 'Monday', 'Tuesday', 'Wednesday', 'Thursday', 'Friday', 'Saturday'),
                 tt.period_no
    ");
    $periodStmt->execute([
        ':teacher_id' => $absence['teacher_id'],
        ':start_date' => $absence['start_date'],
        ':end_date' => $absence['end_date']
    ]);
    $affectedPeriods = $periodStmt->fetchAll();

    sendResponse(true, 'Absence details fetched successfully', [
        'absence' => $absence,
        'affected_periods' => $affectedPeriods
    ]);
}

/**
 * Get today's absences
 */
function getTodayAbsences($db, $params) {
    $today = date('Y-m-d');

    $query = "
        SELECT
            ta.*,
            t.name as teacher_name,
            t.employee_id,
            t.department,
            t.subject as primary_subject
        FROM teacher_absences ta
        LEFT JOIN teachers t ON ta.teacher_id = t.id
        WHERE ta.status = 'APPROVED'
        AND :today BETWEEN ta.start_date AND ta.end_date
        ORDER BY t.name
    ";

    $stmt = $db->prepare($query);
    $stmt->execute([':today' => $today]);
    $absences = $stmt->fetchAll();

    // For each absent teacher, get their affected periods today
    $dayOfWeek = date('l'); // Monday, Tuesday, etc.
    foreach ($absences as &$absence) {
        $periodStmt = $db->prepare("
            SELECT
                tt.*,
                s.name as subject_name,
                sr.substitute_teacher_id,
                sr.status as substitution_status,
                st.name as substitute_teacher_name
            FROM timetable tt
            LEFT JOIN subjects s ON tt.subject_id = s.id
            LEFT JOIN substitution_records sr ON sr.original_teacher_id = tt.teacher_id
                AND sr.substitution_date = :today
                AND sr.class_id = tt.class
                AND sr.section_id = tt.section
                AND sr.slot_id = tt.period_no
            LEFT JOIN teachers st ON sr.substitute_teacher_id = st.id
            WHERE tt.teacher_id = :teacher_id
            AND tt.day_of_week = :day_of_week
            ORDER BY tt.period_no
        ");
        $periodStmt->execute([
            ':teacher_id' => $absence['teacher_id'],
            ':today' => $today,
            ':day_of_week' => $dayOfWeek
        ]);
        $absence['periods'] = $periodStmt->fetchAll();
    }

    sendResponse(true, 'Today\'s absences fetched successfully', [
        'date' => $today,
        'day' => $dayOfWeek,
        'absences' => $absences
    ]);
}

/**
 * Mark teacher absence
 */
function markTeacherAbsence($db, $data) {
    $required = ['teacher_id', 'start_date', 'end_date', 'absence_type'];
    $errors = validateRequired($data, $required);

    if (!empty($errors)) {
        sendResponse(false, 'Validation failed', null, $errors);
    }

    // Check for overlapping absences
    $overlapCheck = $db->prepare("
        SELECT id FROM teacher_absences
        WHERE teacher_id = :teacher_id
        AND status NOT IN ('CANCELLED', 'REJECTED')
        AND (
            (start_date <= :end_date AND end_date >= :start_date)
        )
    ");
    $overlapCheck->execute([
        ':teacher_id' => $data['teacher_id'],
        ':start_date' => $data['start_date'],
        ':end_date' => $data['end_date']
    ]);

    if ($overlapCheck->fetch()) {
        sendResponse(false, 'Overlapping absence already exists for this teacher');
    }

    $stmt = $db->prepare("
        INSERT INTO teacher_absences (teacher_id, start_date, end_date, absence_type, reason, status)
        VALUES (:teacher_id, :start_date, :end_date, :absence_type, :reason, :status)
    ");

    $status = isset($data['auto_approve']) && $data['auto_approve'] ? 'APPROVED' : 'PENDING';

    $stmt->execute([
        ':teacher_id' => sanitizeInput($data['teacher_id']),
        ':start_date' => $data['start_date'],
        ':end_date' => $data['end_date'],
        ':absence_type' => sanitizeInput($data['absence_type']),
        ':reason' => isset($data['reason']) ? sanitizeInput($data['reason']) : null,
        ':status' => $status
    ]);

    $absenceId = $db->lastInsertId();

    // If auto-approve, trigger substitution creation
    if ($status === 'APPROVED') {
        createSubstitutionRecords($db, $absenceId);
    }

    logActivity($db, getCurrentUserId(), 'Marked teacher absence', 'TeacherAbsence', [
        'absence_id' => $absenceId,
        'teacher_id' => $data['teacher_id']
    ]);

    sendResponse(true, 'Teacher absence marked successfully', ['id' => $absenceId, 'status' => $status]);
}

/**
 * Approve teacher absence
 */
function approveTeacherAbsence($db, $data) {
    if (empty($data['id'])) {
        sendResponse(false, 'Absence ID is required');
    }

    $id = (int)$data['id'];

    $stmt = $db->prepare("
        UPDATE teacher_absences
        SET status = 'APPROVED',
            approved_by = :approved_by,
            approved_at = NOW()
        WHERE id = :id AND status = 'PENDING'
    ");

    $stmt->execute([
        ':id' => $id,
        ':approved_by' => getCurrentUserId()
    ]);

    if ($stmt->rowCount() === 0) {
        sendResponse(false, 'Absence not found or already processed');
    }

    // Create substitution records
    createSubstitutionRecords($db, $id);

    logActivity($db, getCurrentUserId(), 'Approved teacher absence', 'TeacherAbsence', ['id' => $id]);

    sendResponse(true, 'Teacher absence approved successfully');
}

/**
 * Cancel teacher absence
 */
function cancelTeacherAbsence($db, $data) {
    if (empty($data['id'])) {
        sendResponse(false, 'Absence ID is required');
    }

    $id = (int)$data['id'];

    $db->beginTransaction();
    try {
        // Cancel the absence
        $stmt = $db->prepare("
            UPDATE teacher_absences
            SET status = 'CANCELLED'
            WHERE id = :id
        ");
        $stmt->execute([':id' => $id]);

        // Cancel related substitution records
        $subStmt = $db->prepare("
            UPDATE substitution_records
            SET status = 'CANCELLED'
            WHERE absence_id = :absence_id
        ");
        $subStmt->execute([':absence_id' => $id]);

        $db->commit();

        logActivity($db, getCurrentUserId(), 'Cancelled teacher absence', 'TeacherAbsence', ['id' => $id]);

        sendResponse(true, 'Teacher absence cancelled successfully');
    } catch (Exception $e) {
        $db->rollBack();
        sendResponse(false, 'Error cancelling absence', null, ['error' => $e->getMessage()]);
    }
}

/**
 * Create substitution records for an absence
 */
function createSubstitutionRecords($db, $absenceId) {
    // Get absence details
    $absenceStmt = $db->prepare("SELECT * FROM teacher_absences WHERE id = :id");
    $absenceStmt->execute([':id' => $absenceId]);
    $absence = $absenceStmt->fetch();

    if (!$absence) return;

    // Get all days between start and end date
    $startDate = new DateTime($absence['start_date']);
    $endDate = new DateTime($absence['end_date']);
    $endDate->modify('+1 day'); // Include end date

    $interval = new DateInterval('P1D');
    $dateRange = new DatePeriod($startDate, $interval, $endDate);

    // Get teacher's timetable
    $timetableStmt = $db->prepare("
        SELECT * FROM timetable WHERE teacher_id = :teacher_id
    ");
    $timetableStmt->execute([':teacher_id' => $absence['teacher_id']]);
    $timetableEntries = $timetableStmt->fetchAll();

    // Create substitution record for each affected period
    $insertStmt = $db->prepare("
        INSERT INTO substitution_records
        (absence_id, timetable_entry_id, original_teacher_id, substitution_date, slot_id, class_id, section_id, subject_id, status, assignment_type)
        VALUES (:absence_id, :timetable_entry_id, :original_teacher_id, :substitution_date, :slot_id, :class_id, :section_id, :subject_id, 'PENDING', 'AUTO')
        ON DUPLICATE KEY UPDATE status = 'PENDING'
    ");

    foreach ($dateRange as $date) {
        $dayOfWeek = $date->format('l'); // Monday, Tuesday, etc.
        $dateStr = $date->format('Y-m-d');

        foreach ($timetableEntries as $entry) {
            if ($entry['day_of_week'] === $dayOfWeek) {
                try {
                    $insertStmt->execute([
                        ':absence_id' => $absenceId,
                        ':timetable_entry_id' => $entry['id'],
                        ':original_teacher_id' => $absence['teacher_id'],
                        ':substitution_date' => $dateStr,
                        ':slot_id' => $entry['period_no'],
                        ':class_id' => $entry['class'],
                        ':section_id' => $entry['section'],
                        ':subject_id' => $entry['subject_id']
                    ]);
                } catch (Exception $e) {
                    // Skip duplicates
                }
            }
        }
    }
}

// ============================================
// SUBSTITUTION MANAGEMENT
// ============================================

/**
 * Get substitutions list
 */
function getSubstitutions($db, $params) {
    $date = $params['date'] ?? date('Y-m-d');
    $status = $params['status'] ?? null;

    $where = ["sr.substitution_date = :date"];
    $bindings = [':date' => $date];

    if ($status) {
        $where[] = "sr.status = :status";
        $bindings[':status'] = $status;
    }

    $whereClause = 'WHERE ' . implode(' AND ', $where);

    $query = "
        SELECT
            sr.*,
            ot.name as original_teacher_name,
            st.name as substitute_teacher_name,
            s.name as subject_name,
            c.class_name,
            p.start_time,
            p.end_time
        FROM substitution_records sr
        LEFT JOIN teachers ot ON sr.original_teacher_id = ot.id
        LEFT JOIN teachers st ON sr.substitute_teacher_id = st.id
        LEFT JOIN subjects s ON sr.subject_id = s.id
        LEFT JOIN classes c ON sr.class_id = c.id
        LEFT JOIN periods p ON sr.slot_id = p.period_number
        $whereClause
        ORDER BY sr.slot_id, sr.class_id
    ";

    $stmt = $db->prepare($query);
    $stmt->execute($bindings);
    $substitutions = $stmt->fetchAll();

    sendResponse(true, 'Substitutions fetched successfully', ['substitutions' => $substitutions]);
}

/**
 * Get pending substitutions (no substitute assigned)
 */
function getPendingSubstitutions($db, $params) {
    $date = $params['date'] ?? date('Y-m-d');

    $query = "
        SELECT
            sr.*,
            ot.name as original_teacher_name,
            s.name as subject_name,
            p.start_time,
            p.end_time,
            p.name as period_name
        FROM substitution_records sr
        LEFT JOIN teachers ot ON sr.original_teacher_id = ot.id
        LEFT JOIN subjects s ON sr.subject_id = s.id
        LEFT JOIN periods p ON sr.slot_id = p.period_number
        WHERE sr.substitution_date = :date
        AND sr.status = 'PENDING'
        AND sr.substitute_teacher_id IS NULL
        ORDER BY sr.slot_id, sr.class_id
    ";

    $stmt = $db->prepare($query);
    $stmt->execute([':date' => $date]);
    $pending = $stmt->fetchAll();

    sendResponse(true, 'Pending substitutions fetched successfully', ['pending' => $pending]);
}

/**
 * Get available teachers for substitution
 */
function getAvailableTeachersForSubstitution($db, $params) {
    if (empty($params['date']) || empty($params['period'])) {
        sendResponse(false, 'Date and period are required');
    }

    $date = $params['date'];
    $period = (int)$params['period'];
    $subjectId = $params['subject_id'] ?? null;
    $dayOfWeek = date('l', strtotime($date));

    // Get all teachers who are:
    // 1. Not absent on this date
    // 2. Not already teaching at this period on this day
    // 3. Not already assigned as substitute at this period on this date

    $query = "
        SELECT
            t.id,
            t.name,
            t.employee_id,
            t.department,
            t.subject as primary_subject,
            CASE
                WHEN t.subject = (SELECT name FROM subjects WHERE id = :subject_id) THEN 1
                ELSE 0
            END as subject_match
        FROM teachers t
        WHERE t.status = 'Active'
        -- Not absent
        AND t.id NOT IN (
            SELECT ta.teacher_id
            FROM teacher_absences ta
            WHERE ta.status = 'APPROVED'
            AND :date BETWEEN ta.start_date AND ta.end_date
        )
        -- Not teaching at this slot
        AND t.id NOT IN (
            SELECT tt.teacher_id
            FROM timetable tt
            WHERE tt.day_of_week = :day_of_week
            AND tt.period_no = :period
        )
        -- Not already assigned as substitute at this slot
        AND t.id NOT IN (
            SELECT sr.substitute_teacher_id
            FROM substitution_records sr
            WHERE sr.substitution_date = :date2
            AND sr.slot_id = :period2
            AND sr.status IN ('ASSIGNED', 'COMPLETED')
            AND sr.substitute_teacher_id IS NOT NULL
        )
        ORDER BY subject_match DESC, t.name
    ";

    $stmt = $db->prepare($query);
    $stmt->execute([
        ':date' => $date,
        ':date2' => $date,
        ':day_of_week' => $dayOfWeek,
        ':period' => $period,
        ':period2' => $period,
        ':subject_id' => $subjectId ?? 0
    ]);
    $teachers = $stmt->fetchAll();

    sendResponse(true, 'Available teachers fetched successfully', [
        'teachers' => $teachers,
        'date' => $date,
        'period' => $period
    ]);
}

/**
 * Assign substitute teacher
 */
function assignSubstituteTeacher($db, $data) {
    $required = ['substitution_id', 'substitute_teacher_id'];
    $errors = validateRequired($data, $required);

    if (!empty($errors)) {
        sendResponse(false, 'Validation failed', null, $errors);
    }

    $substitutionId = (int)$data['substitution_id'];
    $substituteTeacherId = sanitizeInput($data['substitute_teacher_id']);

    // Check if substitution exists and is pending
    $checkStmt = $db->prepare("SELECT * FROM substitution_records WHERE id = :id");
    $checkStmt->execute([':id' => $substitutionId]);
    $record = $checkStmt->fetch();

    if (!$record) {
        sendResponse(false, 'Substitution record not found');
    }

    if ($record['status'] !== 'PENDING') {
        sendResponse(false, 'Substitution already processed');
    }

    // Update substitution record
    $stmt = $db->prepare("
        UPDATE substitution_records
        SET substitute_teacher_id = :substitute_id,
            status = 'ASSIGNED',
            assignment_type = 'MANUAL'
        WHERE id = :id
    ");

    $stmt->execute([
        ':id' => $substitutionId,
        ':substitute_id' => $substituteTeacherId
    ]);

    logActivity($db, getCurrentUserId(), 'Assigned substitute teacher', 'Substitution', [
        'substitution_id' => $substitutionId,
        'substitute_teacher_id' => $substituteTeacherId
    ]);

    sendResponse(true, 'Substitute teacher assigned successfully');
}

/**
 * Auto-assign substitutes for a date
 */
function autoAssignSubstitutes($db, $data) {
    $date = $data['date'] ?? date('Y-m-d');
    $dayOfWeek = date('l', strtotime($date));

    // Get all pending substitutions for this date
    $pendingStmt = $db->prepare("
        SELECT sr.*, s.name as subject_name
        FROM substitution_records sr
        LEFT JOIN subjects s ON sr.subject_id = s.id
        WHERE sr.substitution_date = :date
        AND sr.status = 'PENDING'
        AND sr.substitute_teacher_id IS NULL
        ORDER BY sr.slot_id
    ");
    $pendingStmt->execute([':date' => $date]);
    $pending = $pendingStmt->fetchAll();

    if (empty($pending)) {
        sendResponse(true, 'No pending substitutions to assign', ['assigned' => 0]);
    }

    $assignedCount = 0;
    $failedAssignments = [];

    foreach ($pending as $record) {
        // Find available teacher (prioritize subject match)
        $availableStmt = $db->prepare("
            SELECT t.id, t.name, t.subject as primary_subject
            FROM teachers t
            WHERE t.status = 'Active'
            -- Not absent
            AND t.id NOT IN (
                SELECT ta.teacher_id FROM teacher_absences ta
                WHERE ta.status = 'APPROVED'
                AND :date BETWEEN ta.start_date AND ta.end_date
            )
            -- Not teaching at this slot
            AND t.id NOT IN (
                SELECT tt.teacher_id FROM timetable tt
                WHERE tt.day_of_week = :day_of_week AND tt.period_no = :period
            )
            -- Not already assigned as substitute
            AND t.id NOT IN (
                SELECT sr.substitute_teacher_id FROM substitution_records sr
                WHERE sr.substitution_date = :date2 AND sr.slot_id = :period2
                AND sr.status IN ('ASSIGNED', 'COMPLETED')
                AND sr.substitute_teacher_id IS NOT NULL
            )
            -- Exclude original teacher
            AND t.id != :original_teacher_id
            ORDER BY
                CASE WHEN t.subject = :subject_name THEN 0 ELSE 1 END,
                t.name
            LIMIT 1
        ");

        $availableStmt->execute([
            ':date' => $date,
            ':date2' => $date,
            ':day_of_week' => $dayOfWeek,
            ':period' => $record['slot_id'],
            ':period2' => $record['slot_id'],
            ':original_teacher_id' => $record['original_teacher_id'],
            ':subject_name' => $record['subject_name'] ?? ''
        ]);

        $teacher = $availableStmt->fetch();

        if ($teacher) {
            // Assign this teacher
            $assignStmt = $db->prepare("
                UPDATE substitution_records
                SET substitute_teacher_id = :substitute_id,
                    status = 'ASSIGNED',
                    assignment_type = 'AUTO'
                WHERE id = :id
            ");
            $assignStmt->execute([
                ':id' => $record['id'],
                ':substitute_id' => $teacher['id']
            ]);
            $assignedCount++;
        } else {
            $failedAssignments[] = [
                'id' => $record['id'],
                'class' => $record['class_id'],
                'section' => $record['section_id'],
                'period' => $record['slot_id'],
                'reason' => 'No available teacher found'
            ];
        }
    }

    logActivity($db, getCurrentUserId(), 'Auto-assigned substitutes', 'Substitution', [
        'date' => $date,
        'assigned_count' => $assignedCount
    ]);

    sendResponse(true, "Auto-assigned $assignedCount substitutes", [
        'assigned' => $assignedCount,
        'failed' => $failedAssignments
    ]);
}

/**
 * Cancel substitution
 */
function cancelSubstitution($db, $data) {
    if (empty($data['id'])) {
        sendResponse(false, 'Substitution ID is required');
    }

    $id = (int)$data['id'];

    $stmt = $db->prepare("
        UPDATE substitution_records
        SET status = 'CANCELLED',
            substitute_teacher_id = NULL
        WHERE id = :id
    ");

    $stmt->execute([':id' => $id]);

    if ($stmt->rowCount() === 0) {
        sendResponse(false, 'Substitution not found');
    }

    logActivity($db, getCurrentUserId(), 'Cancelled substitution', 'Substitution', ['id' => $id]);

    sendResponse(true, 'Substitution cancelled successfully');
}
?>
