<?php
/**
 * Advanced Attendance Report API
 * EduManage Pro - School Management System
 *
 * Comprehensive reporting, analytics, audit tracking, and monitoring
 * Features:
 * - Smart filtering with multiple parameters
 * - KPI dashboard statistics
 * - Day-wise attendance view
 * - Monthly attendance matrix
 * - Student-wise attendance profile
 * - Analytics and trends
 * - Audit trail for corrections
 * - Export functionality
 */

require_once __DIR__ . '/../config/db.php';
require_once __DIR__ . '/../helpers/functions.php';
require_once __DIR__ . '/../helpers/permission_guard.php';

// Start session for permission checks
if (session_status() === PHP_SESSION_NONE) {
    session_start();
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

// Require authentication and module access
requireAuth();
requireModuleAccess('attendance');

// Route requests based on method
switch ($method) {
    case 'GET':
        requirePermission('attendance', 'view');
        handleGet($db, $_GET);
        break;

    case 'POST':
        handlePost($db, $data);
        break;

    case 'PUT':
        requirePermission('attendance', 'edit');
        handlePut($db, $data);
        break;

    default:
        sendResponse(false, 'Method not allowed');
}

/**
 * Handle GET requests
 */
function handleGet($db, $params) {
    try {
        $action = $params['action'] ?? 'dashboard';

        switch ($action) {
            case 'dashboard':
                getDashboardStats($db, $params);
                break;

            case 'day_view':
                getDayWiseView($db, $params);
                break;

            case 'monthly_matrix':
                getMonthlyMatrix($db, $params);
                break;

            case 'student_profile':
                getStudentProfile($db, $params);
                break;

            case 'analytics':
                getAnalytics($db, $params);
                break;

            case 'audit_log':
                getAuditLog($db, $params);
                break;

            case 'frequent_absent':
                getFrequentAbsentees($db, $params);
                break;

            case 'late_trends':
                getLateTrends($db, $params);
                break;

            case 'class_comparison':
                getClassComparison($db, $params);
                break;

            case 'teachers':
                getTeachersList($db);
                break;

            case 'classes':
                getClassesList($db);
                break;

            case 'sections':
                getSectionsList($db, $params);
                break;

            case 'export':
                exportReport($db, $params);
                break;

            default:
                sendResponse(false, 'Invalid action');
        }
    } catch (Exception $e) {
        sendResponse(false, 'Error processing request', null, ['error' => $e->getMessage()]);
    }
}

/**
 * Get Dashboard Statistics (KPI Cards)
 */
function getDashboardStats($db, $params) {
    $date = $params['date'] ?? date('Y-m-d');
    $dateFrom = $params['date_from'] ?? $date;
    $dateTo = $params['date_to'] ?? $date;
    $class = $params['class'] ?? '';
    $section = $params['section'] ?? '';

    // Build conditions
    $conditions = [];
    $bindings = [];

    if ($dateFrom === $dateTo) {
        $conditions[] = "DATE(a.date) = :date";
        $bindings[':date'] = $date;
    } else {
        $conditions[] = "DATE(a.date) BETWEEN :date_from AND :date_to";
        $bindings[':date_from'] = $dateFrom;
        $bindings[':date_to'] = $dateTo;
    }

    if (!empty($class)) {
        $conditions[] = "s.class = :class";
        $bindings[':class'] = $class;
    }

    if (!empty($section)) {
        $conditions[] = "s.section = :section";
        $bindings[':section'] = $section;
    }

    $whereClause = !empty($conditions) ? 'WHERE ' . implode(' AND ', $conditions) : '';

    // Get total students in class/section
    $studentConditions = [];
    $studentBindings = [];
    if (!empty($class)) {
        $studentConditions[] = "class = :class";
        $studentBindings[':class'] = $class;
    }
    if (!empty($section)) {
        $studentConditions[] = "section = :section";
        $studentBindings[':section'] = $section;
    }
    $studentWhere = !empty($studentConditions) ? 'WHERE ' . implode(' AND ', $studentConditions) . " AND status = 'Active'" : "WHERE status = 'Active'";

    $totalStudentsQuery = "SELECT COUNT(*) as total FROM students $studentWhere";
    $stmt = $db->prepare($totalStudentsQuery);
    $stmt->execute($studentBindings);
    $totalStudents = $stmt->fetch()['total'];

    // Get attendance stats
    $statsQuery = "
        SELECT
            COUNT(DISTINCT a.student_id) as marked_students,
            SUM(CASE WHEN a.status = 'Present' THEN 1 ELSE 0 END) as present_count,
            SUM(CASE WHEN a.status = 'Absent' THEN 1 ELSE 0 END) as absent_count,
            SUM(CASE WHEN a.status = 'Late' THEN 1 ELSE 0 END) as late_count,
            COUNT(*) as total_records
        FROM attendance a
        LEFT JOIN students s ON a.student_id = s.id
        $whereClause
    ";

    $stmt = $db->prepare($statsQuery);
    $stmt->execute($bindings);
    $stats = $stmt->fetch();

    // Calculate not marked students for single date
    $notMarked = 0;
    if ($dateFrom === $dateTo && (!empty($class) || !empty($section))) {
        $notMarked = $totalStudents - ($stats['marked_students'] ?? 0);
        if ($notMarked < 0) $notMarked = 0;
    }

    // Calculate percentages
    $totalMarked = $stats['marked_students'] ?? 0;
    $presentPct = $totalMarked > 0 ? round(($stats['present_count'] / $totalMarked) * 100, 1) : 0;
    $absentPct = $totalMarked > 0 ? round(($stats['absent_count'] / $totalMarked) * 100, 1) : 0;
    $latePct = $totalMarked > 0 ? round(($stats['late_count'] / $totalMarked) * 100, 1) : 0;

    // Check if attendance is marked for today
    $attendanceMarked = $totalMarked > 0;

    $response = [
        'date' => $date,
        'date_from' => $dateFrom,
        'date_to' => $dateTo,
        'total_students' => (int)$totalStudents,
        'marked_students' => (int)$totalMarked,
        'attendance_marked' => $attendanceMarked,
        'present_count' => (int)($stats['present_count'] ?? 0),
        'absent_count' => (int)($stats['absent_count'] ?? 0),
        'late_count' => (int)($stats['late_count'] ?? 0),
        'not_marked_count' => (int)$notMarked,
        'present_percentage' => $presentPct,
        'absent_percentage' => $absentPct,
        'late_percentage' => $latePct
    ];

    sendResponse(true, 'Dashboard stats fetched successfully', $response);
}

/**
 * Get Day-wise Attendance View
 */
function getDayWiseView($db, $params) {
    $date = $params['date'] ?? date('Y-m-d');
    $class = $params['class'] ?? '';
    $section = $params['section'] ?? '';
    $status = $params['status'] ?? '';
    $search = $params['search'] ?? '';
    $teacher = $params['teacher'] ?? '';

    if (empty($class)) {
        sendResponse(false, 'Class is required');
        return;
    }

    $conditions = ["DATE(a.date) = :date", "s.class = :class"];
    $bindings = [':date' => $date, ':class' => $class];

    if (!empty($section)) {
        $conditions[] = "s.section = :section";
        $bindings[':section'] = $section;
    }

    if (!empty($status)) {
        $conditions[] = "a.status = :status";
        $bindings[':status'] = $status;
    }

    if (!empty($search)) {
        $conditions[] = "(s.name LIKE :search OR s.roll_no LIKE :search2)";
        $bindings[':search'] = "%$search%";
        $bindings[':search2'] = "%$search%";
    }

    if (!empty($teacher)) {
        $conditions[] = "a.marked_by = :teacher";
        $bindings[':teacher'] = $teacher;
    }

    $whereClause = 'WHERE ' . implode(' AND ', $conditions);

    $query = "
        SELECT
            a.id,
            a.student_id,
            s.name as student_name,
            s.roll_no,
            s.class,
            s.section,
            a.status,
            a.marked_at,
            a.remarks,
            a.marked_by,
            u.name as teacher_name,
            CASE WHEN a.is_manual_override = 1 THEN 'Yes' ELSE 'No' END as manual_override,
            CASE WHEN TIME(a.marked_at) > '09:30:00' AND a.status = 'Present' THEN 1 ELSE 0 END as late_marked
        FROM attendance a
        LEFT JOIN students s ON a.student_id = s.id
        LEFT JOIN users u ON a.marked_by = u.id
        $whereClause
        ORDER BY s.roll_no ASC
    ";

    $stmt = $db->prepare($query);
    $stmt->execute($bindings);
    $records = $stmt->fetchAll();

    // Get students without attendance for this date
    $allStudentsQuery = "
        SELECT id, name, roll_no, class, section
        FROM students
        WHERE class = :class
        " . (!empty($section) ? "AND section = :section" : "") . "
        AND status = 'Active'
        AND id NOT IN (
            SELECT student_id FROM attendance WHERE DATE(date) = :date
        )
        ORDER BY roll_no ASC
    ";

    $allBindings = [':class' => $class, ':date' => $date];
    if (!empty($section)) {
        $allBindings[':section'] = $section;
    }

    $stmt = $db->prepare($allStudentsQuery);
    $stmt->execute($allBindings);
    $notMarkedStudents = $stmt->fetchAll();

    sendResponse(true, 'Day-wise attendance fetched', [
        'date' => $date,
        'records' => $records,
        'not_marked' => $notMarkedStudents,
        'total_marked' => count($records),
        'total_not_marked' => count($notMarkedStudents)
    ]);
}

/**
 * Get Monthly Attendance Matrix
 */
function getMonthlyMatrix($db, $params) {
    $month = $params['month'] ?? date('m');
    $year = $params['year'] ?? date('Y');
    $class = $params['class'] ?? '';
    $section = $params['section'] ?? '';

    if (empty($class)) {
        sendResponse(false, 'Class is required');
        return;
    }

    $startDate = "$year-$month-01";
    $endDate = date('Y-m-t', strtotime($startDate));
    $daysInMonth = (int)date('t', strtotime($startDate));

    // Get all students in class/section
    $studentConditions = ["class = :class", "status = 'Active'"];
    $studentBindings = [':class' => $class];

    if (!empty($section)) {
        $studentConditions[] = "section = :section";
        $studentBindings[':section'] = $section;
    }

    $studentQuery = "SELECT id, name, roll_no, class, section FROM students WHERE " . implode(' AND ', $studentConditions) . " ORDER BY roll_no ASC";
    $stmt = $db->prepare($studentQuery);
    $stmt->execute($studentBindings);
    $students = $stmt->fetchAll();

    // Get all attendance records for the month
    $attendanceBindings = [':class' => $class, ':start_date' => $startDate, ':end_date' => $endDate];
    $attendanceConditions = ["s.class = :class", "DATE(a.date) BETWEEN :start_date AND :end_date"];

    if (!empty($section)) {
        $attendanceConditions[] = "s.section = :section";
        $attendanceBindings[':section'] = $section;
    }

    $attendanceQuery = "
        SELECT
            a.student_id,
            DATE(a.date) as date,
            DAY(a.date) as day,
            a.status
        FROM attendance a
        LEFT JOIN students s ON a.student_id = s.id
        WHERE " . implode(' AND ', $attendanceConditions);

    $stmt = $db->prepare($attendanceQuery);
    $stmt->execute($attendanceBindings);
    $attendanceRecords = $stmt->fetchAll();

    // Build attendance lookup
    $attendanceLookup = [];
    foreach ($attendanceRecords as $record) {
        $key = $record['student_id'] . '_' . $record['day'];
        $attendanceLookup[$key] = $record['status'];
    }

    // Build matrix
    $matrix = [];
    foreach ($students as $student) {
        $row = [
            'student_id' => $student['id'],
            'name' => $student['name'],
            'roll_no' => $student['roll_no'],
            'days' => [],
            'present' => 0,
            'absent' => 0,
            'late' => 0,
            'not_marked' => 0
        ];

        for ($day = 1; $day <= $daysInMonth; $day++) {
            $key = $student['id'] . '_' . $day;
            $status = $attendanceLookup[$key] ?? null;

            // Check if it's a weekend (Saturday/Sunday)
            $dateStr = sprintf('%s-%s-%02d', $year, $month, $day);
            $dayOfWeek = date('N', strtotime($dateStr));
            $isWeekend = ($dayOfWeek == 6 || $dayOfWeek == 7);

            if ($isWeekend) {
                $row['days'][$day] = 'W'; // Weekend
            } elseif ($status === null) {
                $row['days'][$day] = '-'; // Not marked
                $row['not_marked']++;
            } else {
                switch ($status) {
                    case 'Present':
                        $row['days'][$day] = 'P';
                        $row['present']++;
                        break;
                    case 'Absent':
                        $row['days'][$day] = 'A';
                        $row['absent']++;
                        break;
                    case 'Late':
                        $row['days'][$day] = 'L';
                        $row['late']++;
                        break;
                    default:
                        $row['days'][$day] = '-';
                }
            }
        }

        // Calculate percentage
        $totalWorking = $row['present'] + $row['absent'] + $row['late'];
        $row['percentage'] = $totalWorking > 0 ? round(($row['present'] / $totalWorking) * 100, 1) : 0;

        $matrix[] = $row;
    }

    sendResponse(true, 'Monthly matrix generated', [
        'month' => $month,
        'year' => $year,
        'days_in_month' => $daysInMonth,
        'class' => $class,
        'section' => $section,
        'matrix' => $matrix,
        'total_students' => count($students)
    ]);
}

/**
 * Get Student Attendance Profile
 */
function getStudentProfile($db, $params) {
    $studentId = $params['student_id'] ?? '';

    if (empty($studentId)) {
        sendResponse(false, 'Student ID is required');
        return;
    }

    // Get student info
    $studentQuery = "SELECT id, name, roll_no, class, section, admission_date FROM students WHERE id = :id";
    $stmt = $db->prepare($studentQuery);
    $stmt->execute([':id' => $studentId]);
    $student = $stmt->fetch();

    if (!$student) {
        sendResponse(false, 'Student not found');
        return;
    }

    // Get overall attendance stats
    $statsQuery = "
        SELECT
            COUNT(*) as total_days,
            SUM(CASE WHEN status = 'Present' THEN 1 ELSE 0 END) as present,
            SUM(CASE WHEN status = 'Absent' THEN 1 ELSE 0 END) as absent,
            SUM(CASE WHEN status = 'Late' THEN 1 ELSE 0 END) as late,
            MIN(date) as first_attendance,
            MAX(date) as last_attendance
        FROM attendance
        WHERE student_id = :student_id
    ";
    $stmt = $db->prepare($statsQuery);
    $stmt->execute([':student_id' => $studentId]);
    $stats = $stmt->fetch();

    // Calculate monthly attendance for last 6 months
    $monthlyQuery = "
        SELECT
            DATE_FORMAT(date, '%Y-%m') as month,
            COUNT(*) as total,
            SUM(CASE WHEN status = 'Present' THEN 1 ELSE 0 END) as present,
            SUM(CASE WHEN status = 'Absent' THEN 1 ELSE 0 END) as absent,
            SUM(CASE WHEN status = 'Late' THEN 1 ELSE 0 END) as late
        FROM attendance
        WHERE student_id = :student_id
        AND date >= DATE_SUB(CURDATE(), INTERVAL 6 MONTH)
        GROUP BY DATE_FORMAT(date, '%Y-%m')
        ORDER BY month ASC
    ";
    $stmt = $db->prepare($monthlyQuery);
    $stmt->execute([':student_id' => $studentId]);
    $monthlyStats = $stmt->fetchAll();

    // Calculate consecutive absences
    $consecutiveQuery = "
        SELECT date, status
        FROM attendance
        WHERE student_id = :student_id
        ORDER BY date DESC
        LIMIT 30
    ";
    $stmt = $db->prepare($consecutiveQuery);
    $stmt->execute([':student_id' => $studentId]);
    $recentAttendance = $stmt->fetchAll();

    $consecutiveAbsent = 0;
    foreach ($recentAttendance as $record) {
        if ($record['status'] === 'Absent') {
            $consecutiveAbsent++;
        } else {
            break;
        }
    }

    // Calculate overall percentage
    $totalDays = $stats['total_days'] ?? 0;
    $presentDays = $stats['present'] ?? 0;
    $overallPercentage = $totalDays > 0 ? round(($presentDays / $totalDays) * 100, 1) : 0;

    // Determine risk level
    $riskLevel = 'Normal';
    $riskColor = 'green';
    if ($overallPercentage < 60) {
        $riskLevel = 'Critical';
        $riskColor = 'red';
    } elseif ($overallPercentage < 75) {
        $riskLevel = 'Warning';
        $riskColor = 'orange';
    }

    sendResponse(true, 'Student profile fetched', [
        'student' => $student,
        'stats' => [
            'total_days' => (int)$totalDays,
            'present' => (int)$presentDays,
            'absent' => (int)($stats['absent'] ?? 0),
            'late' => (int)($stats['late'] ?? 0),
            'overall_percentage' => $overallPercentage,
            'first_attendance' => $stats['first_attendance'],
            'last_attendance' => $stats['last_attendance']
        ],
        'monthly_stats' => $monthlyStats,
        'consecutive_absences' => $consecutiveAbsent,
        'risk_level' => $riskLevel,
        'risk_color' => $riskColor
    ]);
}

/**
 * Get Analytics Data
 */
function getAnalytics($db, $params) {
    $period = $params['period'] ?? 'month'; // week, month, year
    $class = $params['class'] ?? '';
    $section = $params['section'] ?? '';

    // Determine date range
    switch ($period) {
        case 'week':
            $startDate = date('Y-m-d', strtotime('-7 days'));
            break;
        case 'year':
            $startDate = date('Y-01-01');
            break;
        default:
            $startDate = date('Y-m-01');
    }
    $endDate = date('Y-m-d');

    $conditions = ["DATE(a.date) BETWEEN :start_date AND :end_date"];
    $bindings = [':start_date' => $startDate, ':end_date' => $endDate];

    if (!empty($class)) {
        $conditions[] = "s.class = :class";
        $bindings[':class'] = $class;
    }
    if (!empty($section)) {
        $conditions[] = "s.section = :section";
        $bindings[':section'] = $section;
    }

    $whereClause = 'WHERE ' . implode(' AND ', $conditions);

    // Daily attendance trend
    $trendQuery = "
        SELECT
            DATE(a.date) as date,
            COUNT(*) as total,
            SUM(CASE WHEN a.status = 'Present' THEN 1 ELSE 0 END) as present,
            SUM(CASE WHEN a.status = 'Absent' THEN 1 ELSE 0 END) as absent,
            SUM(CASE WHEN a.status = 'Late' THEN 1 ELSE 0 END) as late
        FROM attendance a
        LEFT JOIN students s ON a.student_id = s.id
        $whereClause
        GROUP BY DATE(a.date)
        ORDER BY date ASC
    ";
    $stmt = $db->prepare($trendQuery);
    $stmt->execute($bindings);
    $trend = $stmt->fetchAll();

    // Day of week analysis
    $dayOfWeekQuery = "
        SELECT
            DAYNAME(a.date) as day_name,
            DAYOFWEEK(a.date) as day_num,
            COUNT(*) as total,
            SUM(CASE WHEN a.status = 'Absent' THEN 1 ELSE 0 END) as absent
        FROM attendance a
        LEFT JOIN students s ON a.student_id = s.id
        $whereClause
        GROUP BY DAYNAME(a.date), DAYOFWEEK(a.date)
        ORDER BY day_num
    ";
    $stmt = $db->prepare($dayOfWeekQuery);
    $stmt->execute($bindings);
    $dayOfWeek = $stmt->fetchAll();

    // Status distribution
    $statusQuery = "
        SELECT
            a.status,
            COUNT(*) as count
        FROM attendance a
        LEFT JOIN students s ON a.student_id = s.id
        $whereClause
        GROUP BY a.status
    ";
    $stmt = $db->prepare($statusQuery);
    $stmt->execute($bindings);
    $statusDistribution = $stmt->fetchAll();

    sendResponse(true, 'Analytics data fetched', [
        'period' => $period,
        'start_date' => $startDate,
        'end_date' => $endDate,
        'trend' => $trend,
        'day_of_week' => $dayOfWeek,
        'status_distribution' => $statusDistribution
    ]);
}

/**
 * Get Frequently Absent Students
 */
function getFrequentAbsentees($db, $params) {
    $days = $params['days'] ?? 30;
    $minAbsent = $params['min_absent'] ?? 3;
    $class = $params['class'] ?? '';
    $section = $params['section'] ?? '';

    $conditions = ["a.status = 'Absent'", "a.date >= DATE_SUB(CURDATE(), INTERVAL :days DAY)"];
    $bindings = [':days' => $days];

    if (!empty($class)) {
        $conditions[] = "s.class = :class";
        $bindings[':class'] = $class;
    }
    if (!empty($section)) {
        $conditions[] = "s.section = :section";
        $bindings[':section'] = $section;
    }

    $whereClause = 'WHERE ' . implode(' AND ', $conditions);

    $query = "
        SELECT
            s.id,
            s.name,
            s.roll_no,
            s.class,
            s.section,
            COUNT(*) as absent_count,
            GROUP_CONCAT(DATE_FORMAT(a.date, '%d %b') ORDER BY a.date DESC SEPARATOR ', ') as absent_dates
        FROM attendance a
        LEFT JOIN students s ON a.student_id = s.id
        $whereClause
        GROUP BY s.id, s.name, s.roll_no, s.class, s.section
        HAVING absent_count >= :min_absent
        ORDER BY absent_count DESC
        LIMIT 50
    ";

    $bindings[':min_absent'] = $minAbsent;
    $stmt = $db->prepare($query);
    $stmt->execute($bindings);
    $absentees = $stmt->fetchAll();

    sendResponse(true, 'Frequent absentees fetched', [
        'days' => $days,
        'min_absent' => $minAbsent,
        'absentees' => $absentees,
        'total' => count($absentees)
    ]);
}

/**
 * Get Late Arrival Trends
 */
function getLateTrends($db, $params) {
    $days = $params['days'] ?? 30;
    $class = $params['class'] ?? '';
    $section = $params['section'] ?? '';

    $conditions = ["a.status = 'Late'", "a.date >= DATE_SUB(CURDATE(), INTERVAL :days DAY)"];
    $bindings = [':days' => $days];

    if (!empty($class)) {
        $conditions[] = "s.class = :class";
        $bindings[':class'] = $class;
    }
    if (!empty($section)) {
        $conditions[] = "s.section = :section";
        $bindings[':section'] = $section;
    }

    $whereClause = 'WHERE ' . implode(' AND ', $conditions);

    // Late count by student
    $studentQuery = "
        SELECT
            s.id,
            s.name,
            s.roll_no,
            s.class,
            s.section,
            COUNT(*) as late_count
        FROM attendance a
        LEFT JOIN students s ON a.student_id = s.id
        $whereClause
        GROUP BY s.id, s.name, s.roll_no, s.class, s.section
        ORDER BY late_count DESC
        LIMIT 20
    ";

    $stmt = $db->prepare($studentQuery);
    $stmt->execute($bindings);
    $lateStudents = $stmt->fetchAll();

    // Late trend by date
    $trendQuery = "
        SELECT
            DATE(a.date) as date,
            COUNT(*) as late_count
        FROM attendance a
        LEFT JOIN students s ON a.student_id = s.id
        $whereClause
        GROUP BY DATE(a.date)
        ORDER BY date ASC
    ";

    $stmt = $db->prepare($trendQuery);
    $stmt->execute($bindings);
    $lateTrend = $stmt->fetchAll();

    sendResponse(true, 'Late trends fetched', [
        'days' => $days,
        'late_students' => $lateStudents,
        'late_trend' => $lateTrend
    ]);
}

/**
 * Get Class Comparison
 */
function getClassComparison($db, $params) {
    $month = $params['month'] ?? date('m');
    $year = $params['year'] ?? date('Y');

    $startDate = "$year-$month-01";
    $endDate = date('Y-m-t', strtotime($startDate));

    // Get total students per class (from students table)
    $studentsQuery = "
        SELECT class, COUNT(*) as total_students
        FROM students
        WHERE status = 'Active'
        GROUP BY class
    ";
    $stmt = $db->prepare($studentsQuery);
    $stmt->execute();
    $studentCounts = [];
    while ($row = $stmt->fetch()) {
        $studentCounts[$row['class']] = $row['total_students'];
    }

    // Get attendance stats per class for the month
    $query = "
        SELECT
            s.class,
            COUNT(DISTINCT a.student_id) as students_with_attendance,
            SUM(CASE WHEN a.status = 'Present' THEN 1 ELSE 0 END) as present,
            SUM(CASE WHEN a.status = 'Absent' THEN 1 ELSE 0 END) as absent,
            SUM(CASE WHEN a.status = 'Late' THEN 1 ELSE 0 END) as late,
            COUNT(*) as total_records
        FROM attendance a
        LEFT JOIN students s ON a.student_id = s.id
        WHERE DATE(a.date) BETWEEN :start_date AND :end_date
        GROUP BY s.class
        ORDER BY s.class + 0 ASC
    ";

    $stmt = $db->prepare($query);
    $stmt->execute([':start_date' => $startDate, ':end_date' => $endDate]);
    $rawData = $stmt->fetchAll();

    // Process and combine data
    $comparison = [];
    foreach ($rawData as $row) {
        $totalStudents = $studentCounts[$row['class']] ?? $row['students_with_attendance'];
        $totalRecords = $row['total_records'];
        $presentCount = $row['present'];

        // Calculate attendance rate based on present records vs total records
        $attendanceRate = $totalRecords > 0 ? round(($presentCount / $totalRecords) * 100, 1) : 0;

        $comparison[] = [
            'class' => $row['class'],
            'total_students' => $totalStudents,
            'present' => $row['present'],
            'absent' => $row['absent'],
            'late' => $row['late'],
            'attendance_rate' => $attendanceRate
        ];
    }

    sendResponse(true, 'Class comparison fetched', [
        'month' => $month,
        'year' => $year,
        'comparison' => $comparison
    ]);
}

/**
 * Get Audit Log
 */
function getAuditLog($db, $params) {
    $days = $params['days'] ?? 30;
    $class = $params['class'] ?? '';
    $studentId = $params['student_id'] ?? '';

    $conditions = ["al.created_at >= DATE_SUB(NOW(), INTERVAL :days DAY)"];
    $bindings = [':days' => $days];

    if (!empty($class)) {
        $conditions[] = "s.class = :class";
        $bindings[':class'] = $class;
    }

    if (!empty($studentId)) {
        $conditions[] = "al.student_id = :student_id";
        $bindings[':student_id'] = $studentId;
    }

    $whereClause = 'WHERE ' . implode(' AND ', $conditions);

    $query = "
        SELECT
            al.*,
            s.name as student_name,
            s.class,
            s.section,
            u.name as changed_by_name
        FROM attendance_audit_log al
        LEFT JOIN students s ON al.student_id = s.id
        LEFT JOIN users u ON al.changed_by = u.id
        $whereClause
        ORDER BY al.created_at DESC
        LIMIT 100
    ";

    $stmt = $db->prepare($query);
    $stmt->execute($bindings);
    $logs = $stmt->fetchAll();

    sendResponse(true, 'Audit log fetched', ['logs' => $logs]);
}

/**
 * Get Teachers List (who marked attendance)
 */
function getTeachersList($db) {
    $query = "
        SELECT DISTINCT
            u.id,
            u.name
        FROM users u
        INNER JOIN attendance a ON a.marked_by = u.id
        ORDER BY u.name ASC
    ";

    $stmt = $db->prepare($query);
    $stmt->execute();
    $teachers = $stmt->fetchAll();

    sendResponse(true, 'Teachers list fetched', ['teachers' => $teachers]);
}

/**
 * Get Classes List
 */
function getClassesList($db) {
    $query = "SELECT id, class_name as name FROM classes ORDER BY class_name ASC";
    $stmt = $db->prepare($query);
    $stmt->execute();
    $classes = $stmt->fetchAll();

    sendResponse(true, 'Classes list fetched', ['classes' => $classes]);
}

/**
 * Get Sections List for a Class
 */
function getSectionsList($db, $params) {
    $className = $params['class'] ?? '';

    if (empty($className)) {
        // Return all sections
        $query = "
            SELECT s.id, s.section_name as name, c.class_name as class
            FROM sections s
            JOIN classes c ON s.class_id = c.id
            ORDER BY c.class_name, s.section_name
        ";
        $stmt = $db->prepare($query);
        $stmt->execute();
    } else {
        // Return sections for specific class
        $query = "
            SELECT s.id, s.section_name as name
            FROM sections s
            JOIN classes c ON s.class_id = c.id
            WHERE c.class_name = :class_name
            ORDER BY s.section_name
        ";
        $stmt = $db->prepare($query);
        $stmt->execute([':class_name' => $className]);
    }

    $sections = $stmt->fetchAll();
    sendResponse(true, 'Sections list fetched', ['sections' => $sections]);
}

/**
 * Export Report
 */
function exportReport($db, $params) {
    $format = $params['format'] ?? 'json';
    $type = $params['type'] ?? 'day'; // day, month, student
    $class = $params['class'] ?? '';
    $section = $params['section'] ?? '';
    $date = $params['date'] ?? date('Y-m-d');
    $month = $params['month'] ?? date('m');
    $year = $params['year'] ?? date('Y');

    // Get data based on type
    switch ($type) {
        case 'month':
            $params['class'] = $class;
            $params['section'] = $section;
            $params['month'] = $month;
            $params['year'] = $year;
            // Return matrix data for export
            // The frontend will handle actual file generation
            getMonthlyMatrix($db, $params);
            return;

        case 'student':
            if (!empty($params['student_id'])) {
                getStudentProfile($db, $params);
                return;
            }
            break;

        default:
            // Day-wise export
            $params['date'] = $date;
            $params['class'] = $class;
            $params['section'] = $section;
            getDayWiseView($db, $params);
            return;
    }

    sendResponse(false, 'Invalid export parameters');
}

/**
 * Handle POST requests
 */
function handlePost($db, $data) {
    $action = $data['action'] ?? '';

    switch ($action) {
        case 'lock_attendance':
            requirePermission('attendance', 'edit');
            lockAttendance($db, $data);
            break;

        case 'send_alert':
            requirePermission('attendance', 'edit');
            sendAlert($db, $data);
            break;

        default:
            sendResponse(false, 'Invalid action');
    }
}

/**
 * Lock attendance for a date
 */
function lockAttendance($db, $data) {
    $date = $data['date'] ?? '';
    $class = $data['class'] ?? '';
    $reason = $data['reason'] ?? '';

    if (empty($date) || empty($class)) {
        sendResponse(false, 'Date and class are required');
        return;
    }

    // Update attendance records to locked
    $query = "
        UPDATE attendance a
        JOIN students s ON a.student_id = s.id
        SET a.is_locked = 1, a.locked_at = NOW(), a.locked_by = :user_id
        WHERE DATE(a.date) = :date AND s.class = :class
    ";

    $stmt = $db->prepare($query);
    $stmt->execute([
        ':user_id' => getCurrentUserId(),
        ':date' => $date,
        ':class' => $class
    ]);

    sendResponse(true, 'Attendance locked successfully', ['affected' => $stmt->rowCount()]);
}

/**
 * Send alert (placeholder - actual implementation depends on notification system)
 */
function sendAlert($db, $data) {
    $type = $data['alert_type'] ?? '';
    $studentId = $data['student_id'] ?? '';
    $message = $data['message'] ?? '';

    // Log the alert request
    // In production, this would integrate with SMS/Email service

    sendResponse(true, 'Alert queued for sending', [
        'type' => $type,
        'student_id' => $studentId,
        'message' => $message
    ]);
}

/**
 * Handle PUT - Update attendance with audit
 */
function handlePut($db, $data) {
    $attendanceId = $data['id'] ?? '';
    $newStatus = $data['status'] ?? '';
    $reason = $data['reason'] ?? '';

    if (empty($attendanceId) || empty($newStatus) || empty($reason)) {
        sendResponse(false, 'ID, status, and reason are required');
        return;
    }

    // Get current attendance record
    $currentQuery = "SELECT * FROM attendance WHERE id = :id";
    $stmt = $db->prepare($currentQuery);
    $stmt->execute([':id' => $attendanceId]);
    $current = $stmt->fetch();

    if (!$current) {
        sendResponse(false, 'Attendance record not found');
        return;
    }

    // Check if locked
    if (!empty($current['is_locked'])) {
        sendResponse(false, 'This attendance record is locked and cannot be modified');
        return;
    }

    // Check 24-hour rule (unless admin override)
    $attendanceDate = new DateTime($current['date']);
    $now = new DateTime();
    $diff = $now->diff($attendanceDate);

    if ($diff->days >= 1 && !isAdmin()) {
        sendResponse(false, 'Attendance can only be edited within 24 hours. Contact admin for override.');
        return;
    }

    $db->beginTransaction();

    try {
        // Log the change in audit table
        $auditQuery = "
            INSERT INTO attendance_audit_log
            (attendance_id, student_id, attendance_date, old_status, new_status, reason, changed_by, created_at)
            VALUES (:attendance_id, :student_id, :attendance_date, :old_status, :new_status, :reason, :changed_by, NOW())
        ";
        $stmt = $db->prepare($auditQuery);
        $stmt->execute([
            ':attendance_id' => $attendanceId,
            ':student_id' => $current['student_id'],
            ':attendance_date' => $current['date'],
            ':old_status' => $current['status'],
            ':new_status' => $newStatus,
            ':reason' => $reason,
            ':changed_by' => getCurrentUserId()
        ]);

        // Update the attendance record
        $updateQuery = "
            UPDATE attendance
            SET status = :status,
                is_manual_override = 1,
                remarks = CONCAT(IFNULL(remarks, ''), ' [Edited: ', :reason, ']'),
                updated_at = NOW()
            WHERE id = :id
        ";
        $stmt = $db->prepare($updateQuery);
        $stmt->execute([
            ':status' => $newStatus,
            ':reason' => $reason,
            ':id' => $attendanceId
        ]);

        $db->commit();

        sendResponse(true, 'Attendance updated successfully', [
            'id' => $attendanceId,
            'old_status' => $current['status'],
            'new_status' => $newStatus
        ]);

    } catch (Exception $e) {
        $db->rollBack();
        sendResponse(false, 'Failed to update attendance', null, ['error' => $e->getMessage()]);
    }
}

/**
 * Check if current user is admin
 */
function isAdmin() {
    return isset($_SESSION['user']['role']) && $_SESSION['user']['role'] === 'admin';
}
