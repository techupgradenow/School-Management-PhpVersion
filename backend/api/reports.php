<?php
/**
 * Reports API Endpoint
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
    default:
        sendResponse(false, 'Method not allowed');
}

function handleGet($db, $params) {
    $action = $params['action'] ?? 'dashboard';

    try {
        switch ($action) {
            case 'dashboard':
                getDashboardStats($db, $params);
                break;
            case 'students':
                getStudentsReport($db, $params);
                break;
            case 'attendance':
                getAttendanceReport($db, $params);
                break;
            case 'fees':
                getFeesReport($db, $params);
                break;
            case 'exams':
                getExamsReport($db, $params);
                break;
            case 'library':
                getLibraryReport($db, $params);
                break;
            default:
                sendResponse(false, 'Invalid action');
        }
    } catch (Exception $e) {
        sendResponse(false, 'Error generating report', null, ['error' => $e->getMessage()]);
    }
}

function getDashboardStats($db, $params) {
    $stats = [];

    // Students Stats
    try {
        $studentsQuery = $db->query("SELECT COUNT(*) as total, SUM(CASE WHEN status = 'Active' THEN 1 ELSE 0 END) as active FROM students");
        $students = $studentsQuery->fetch();
        $stats['students'] = ['total' => (int)$students['total'], 'active' => (int)$students['active']];
    } catch (Exception $e) {
        $stats['students'] = ['total' => 0, 'active' => 0];
    }

    // Teachers Stats
    try {
        $teachersQuery = $db->query("SELECT COUNT(*) as total, SUM(CASE WHEN status = 'Active' THEN 1 ELSE 0 END) as active FROM teachers");
        $teachers = $teachersQuery->fetch();
        $stats['teachers'] = ['total' => (int)$teachers['total'], 'active' => (int)$teachers['active']];
    } catch (Exception $e) {
        $stats['teachers'] = ['total' => 0, 'active' => 0];
    }

    // Today's Attendance
    try {
        $attendanceQuery = $db->prepare("SELECT COUNT(*) as total, SUM(CASE WHEN status = 'Present' THEN 1 ELSE 0 END) as present, SUM(CASE WHEN status = 'Absent' THEN 1 ELSE 0 END) as absent FROM attendance WHERE DATE(date) = CURDATE()");
        $attendanceQuery->execute();
        $attendance = $attendanceQuery->fetch();
        $stats['attendance'] = [
            'total' => (int)$attendance['total'],
            'present' => (int)$attendance['present'],
            'absent' => (int)$attendance['absent'],
            'percentage' => $attendance['total'] > 0 ? round(($attendance['present'] / $attendance['total']) * 100, 1) : 0
        ];
    } catch (Exception $e) {
        $stats['attendance'] = ['total' => 0, 'present' => 0, 'absent' => 0, 'percentage' => 0];
    }

    // Fees Stats (Current Month)
    try {
        $feesQuery = $db->prepare("SELECT COALESCE(SUM(amount_paid), 0) as collected, COUNT(*) as payments FROM fee_payments WHERE MONTH(payment_date) = MONTH(CURDATE()) AND YEAR(payment_date) = YEAR(CURDATE())");
        $feesQuery->execute();
        $fees = $feesQuery->fetch();
        $stats['fees'] = ['collected' => (float)$fees['collected'], 'payments' => (int)$fees['payments']];
    } catch (Exception $e) {
        $stats['fees'] = ['collected' => 0, 'payments' => 0];
    }

    // Library Stats
    try {
        $libraryQuery = $db->query("SELECT COUNT(*) as total_books, COALESCE(SUM(available), 0) as available FROM library_books");
        $library = $libraryQuery->fetch();
        $issuedQuery = $db->query("SELECT COUNT(*) as issued FROM library_issues WHERE return_date IS NULL");
        $issued = $issuedQuery->fetch()['issued'];
        $stats['library'] = ['total_books' => (int)$library['total_books'], 'available' => (int)$library['available'], 'issued' => (int)$issued];
    } catch (Exception $e) {
        $stats['library'] = ['total_books' => 0, 'available' => 0, 'issued' => 0];
    }

    // Transport Stats
    try {
        $routesQuery = $db->query("SELECT COUNT(*) as count FROM transport_routes WHERE status = 'Active'");
        $routes = $routesQuery->fetch()['count'];
        $transportStudentsQuery = $db->query("SELECT COUNT(*) as count FROM transport_assignments WHERE status = 'Active'");
        $transportStudents = $transportStudentsQuery->fetch()['count'];
        $stats['transport'] = ['routes' => (int)$routes, 'students' => (int)$transportStudents];
    } catch (Exception $e) {
        $stats['transport'] = ['routes' => 0, 'students' => 0];
    }

    // Hostel Stats
    try {
        $hostelBlocksQuery = $db->query("SELECT COUNT(*) as count FROM hostel_blocks WHERE status = 'Active'");
        $hostelBlocks = $hostelBlocksQuery->fetch()['count'];
    } catch (Exception $e) {
        $hostelBlocks = 0;
    }

    try {
        $hostelCapacityQuery = $db->query("SELECT COALESCE(SUM(capacity), 0) as capacity FROM hostel_rooms WHERE status = 'Available'");
        $hostelCapacity = $hostelCapacityQuery->fetch()['capacity'];
    } catch (Exception $e) {
        $hostelCapacity = 0;
    }

    try {
        $hostelOccupiedQuery = $db->query("SELECT COUNT(*) as count FROM hostel_allocations WHERE status = 'Active'");
        $hostelOccupied = $hostelOccupiedQuery->fetch()['count'];
    } catch (Exception $e) {
        $hostelOccupied = 0;
    }

    $stats['hostel'] = [
        'blocks' => (int)$hostelBlocks,
        'capacity' => (int)$hostelCapacity,
        'occupied' => (int)$hostelOccupied
    ];

    // Recent Activity
    $activityQuery = $db->query("
        SELECT action, module, created_at
        FROM activity_logs
        ORDER BY created_at DESC
        LIMIT 10
    ");
    $stats['recent_activity'] = $activityQuery->fetchAll();

    sendResponse(true, 'Dashboard statistics fetched successfully', $stats);
}

function getStudentsReport($db, $params) {
    $class = $params['class'] ?? '';
    $section = $params['section'] ?? '';

    $where = [];
    $bindings = [];

    if (!empty($class)) {
        $where[] = "class = :class";
        $bindings[':class'] = $class;
    }

    if (!empty($section)) {
        $where[] = "section = :section";
        $bindings[':section'] = $section;
    }

    $whereClause = !empty($where) ? 'WHERE ' . implode(' AND ', $where) : '';

    // Class-wise distribution
    $classQuery = $db->prepare("
        SELECT class, section, COUNT(*) as count
        FROM students
        $whereClause
        GROUP BY class, section
        ORDER BY class, section
    ");
    $classQuery->execute($bindings);
    $classDistribution = $classQuery->fetchAll();

    // Gender distribution
    $genderQuery = $db->prepare("
        SELECT gender, COUNT(*) as count
        FROM students
        $whereClause
        GROUP BY gender
    ");
    $genderQuery->execute($bindings);
    $genderDistribution = $genderQuery->fetchAll();

    // Status distribution
    $statusQuery = $db->prepare("
        SELECT status, COUNT(*) as count
        FROM students
        $whereClause
        GROUP BY status
    ");
    $statusQuery->execute($bindings);
    $statusDistribution = $statusQuery->fetchAll();

    // Monthly admissions (last 12 months)
    $monthlyQuery = $db->query("
        SELECT
            DATE_FORMAT(joining_date, '%Y-%m') as month,
            COUNT(*) as count
        FROM students
        WHERE joining_date >= DATE_SUB(CURDATE(), INTERVAL 12 MONTH)
        GROUP BY DATE_FORMAT(joining_date, '%Y-%m')
        ORDER BY month
    ");
    $monthlyAdmissions = $monthlyQuery->fetchAll();

    sendResponse(true, 'Students report generated', [
        'class_distribution' => $classDistribution,
        'gender_distribution' => $genderDistribution,
        'status_distribution' => $statusDistribution,
        'monthly_admissions' => $monthlyAdmissions
    ]);
}

function getAttendanceReport($db, $params) {
    $startDate = $params['start_date'] ?? date('Y-m-01');
    $endDate = $params['end_date'] ?? date('Y-m-t');
    $class = $params['class'] ?? '';

    // Daily attendance summary
    $dailyQuery = $db->prepare("
        SELECT
            DATE(date) as date,
            COUNT(*) as total,
            SUM(CASE WHEN status = 'Present' THEN 1 ELSE 0 END) as present,
            SUM(CASE WHEN status = 'Absent' THEN 1 ELSE 0 END) as absent,
            SUM(CASE WHEN status = 'Late' THEN 1 ELSE 0 END) as late
        FROM attendance
        WHERE date BETWEEN :start_date AND :end_date
        GROUP BY DATE(date)
        ORDER BY date
    ");
    $dailyQuery->execute([':start_date' => $startDate, ':end_date' => $endDate]);
    $dailySummary = $dailyQuery->fetchAll();

    // Class-wise attendance
    $classQuery = $db->prepare("
        SELECT
            s.class,
            s.section,
            COUNT(*) as total,
            SUM(CASE WHEN a.status = 'Present' THEN 1 ELSE 0 END) as present
        FROM attendance a
        JOIN students s ON a.student_id = s.id
        WHERE a.date BETWEEN :start_date AND :end_date
        GROUP BY s.class, s.section
        ORDER BY s.class, s.section
    ");
    $classQuery->execute([':start_date' => $startDate, ':end_date' => $endDate]);
    $classWise = $classQuery->fetchAll();

    // Calculate percentages
    foreach ($classWise as &$row) {
        $row['percentage'] = $row['total'] > 0 ? round(($row['present'] / $row['total']) * 100, 1) : 0;
    }

    sendResponse(true, 'Attendance report generated', [
        'period' => ['start' => $startDate, 'end' => $endDate],
        'daily_summary' => $dailySummary,
        'class_wise' => $classWise
    ]);
}

function getFeesReport($db, $params) {
    $startDate = $params['start_date'] ?? date('Y-01-01');
    $endDate = $params['end_date'] ?? date('Y-12-31');

    // Monthly collection
    $monthlyQuery = $db->prepare("
        SELECT
            DATE_FORMAT(payment_date, '%Y-%m') as month,
            SUM(amount_paid) as collected,
            COUNT(*) as payments
        FROM fee_payments
        WHERE payment_date BETWEEN :start_date AND :end_date
        GROUP BY DATE_FORMAT(payment_date, '%Y-%m')
        ORDER BY month
    ");
    $monthlyQuery->execute([':start_date' => $startDate, ':end_date' => $endDate]);
    $monthlyCollection = $monthlyQuery->fetchAll();

    // Payment method breakdown
    $methodQuery = $db->prepare("
        SELECT
            payment_mode as payment_method,
            SUM(amount_paid) as total,
            COUNT(*) as count
        FROM fee_payments
        WHERE payment_date BETWEEN :start_date AND :end_date
        GROUP BY payment_mode
    ");
    $methodQuery->execute([':start_date' => $startDate, ':end_date' => $endDate]);
    $methodBreakdown = $methodQuery->fetchAll();

    // Fee type breakdown
    $typeQuery = $db->prepare("
        SELECT
            fs.fee_type,
            SUM(fp.amount_paid) as total,
            COUNT(*) as count
        FROM fee_payments fp
        LEFT JOIN fee_structures fs ON fp.fee_structure_id = fs.id
        WHERE fp.payment_date BETWEEN :start_date AND :end_date
        GROUP BY fs.fee_type
    ");
    $typeQuery->execute([':start_date' => $startDate, ':end_date' => $endDate]);
    $typeBreakdown = $typeQuery->fetchAll();

    // Total summary
    $totalQuery = $db->prepare("
        SELECT
            COALESCE(SUM(amount_paid), 0) as total_collected,
            COUNT(*) as total_payments,
            COUNT(DISTINCT student_id) as students_paid
        FROM fee_payments
        WHERE payment_date BETWEEN :start_date AND :end_date
    ");
    $totalQuery->execute([':start_date' => $startDate, ':end_date' => $endDate]);
    $totals = $totalQuery->fetch();

    sendResponse(true, 'Fees report generated', [
        'period' => ['start' => $startDate, 'end' => $endDate],
        'monthly_collection' => $monthlyCollection,
        'method_breakdown' => $methodBreakdown,
        'type_breakdown' => $typeBreakdown,
        'totals' => [
            'collected' => (float)$totals['total_collected'],
            'payments' => (int)$totals['total_payments'],
            'students' => (int)$totals['students_paid']
        ]
    ]);
}

function getExamsReport($db, $params) {
    $examId = $params['exam_id'] ?? '';

    if (!empty($examId)) {
        // Specific exam results
        $examQuery = $db->prepare("SELECT * FROM exams WHERE id = :id");
        $examQuery->execute([':id' => $examId]);
        $exam = $examQuery->fetch();

        if (!$exam) {
            sendResponse(false, 'Exam not found');
        }

        // Get marks
        $marksQuery = $db->prepare("
            SELECT
                m.*,
                s.name as student_name,
                s.class,
                s.section,
                s.roll_no
            FROM exam_marks m
            JOIN students s ON m.student_id = s.id
            WHERE m.exam_id = :exam_id
            ORDER BY s.class, s.section, s.roll_no
        ");
        $marksQuery->execute([':exam_id' => $examId]);
        $marks = $marksQuery->fetchAll();

        // Statistics
        $statsQuery = $db->prepare("
            SELECT
                COUNT(*) as total_students,
                AVG(marks_obtained) as average,
                MAX(marks_obtained) as highest,
                MIN(marks_obtained) as lowest,
                SUM(CASE WHEN marks_obtained >= (total_marks * 0.33) THEN 1 ELSE 0 END) as passed
            FROM exam_marks
            WHERE exam_id = :exam_id
        ");
        $statsQuery->execute([':exam_id' => $examId]);
        $stats = $statsQuery->fetch();

        sendResponse(true, 'Exam report generated', [
            'exam' => $exam,
            'marks' => $marks,
            'statistics' => [
                'total_students' => (int)$stats['total_students'],
                'average' => round((float)$stats['average'], 2),
                'highest' => (float)$stats['highest'],
                'lowest' => (float)$stats['lowest'],
                'passed' => (int)$stats['passed'],
                'pass_percentage' => $stats['total_students'] > 0 ? round(($stats['passed'] / $stats['total_students']) * 100, 1) : 0
            ]
        ]);
    } else {
        // All exams summary
        $examsQuery = $db->query("
            SELECT
                e.*,
                COUNT(m.id) as students_appeared,
                AVG(m.marks_obtained) as average_marks
            FROM exams e
            LEFT JOIN exam_marks m ON e.id = m.exam_id
            GROUP BY e.id
            ORDER BY e.exam_date DESC
        ");
        $exams = $examsQuery->fetchAll();

        sendResponse(true, 'Exams summary generated', ['exams' => $exams]);
    }
}

function getLibraryReport($db, $params) {
    // Books by category
    $categoryQuery = $db->query("
        SELECT
            category,
            COUNT(*) as total_titles,
            COALESCE(SUM(quantity), 0) as total_copies,
            COALESCE(SUM(available), 0) as available
        FROM library_books
        GROUP BY category
    ");
    $byCategory = $categoryQuery->fetchAll();

    // Issue trends (last 30 days)
    $trendsQuery = $db->query("
        SELECT
            DATE(issue_date) as date,
            COUNT(*) as issues
        FROM library_issues
        WHERE issue_date >= DATE_SUB(CURDATE(), INTERVAL 30 DAY)
        GROUP BY DATE(issue_date)
        ORDER BY date
    ");
    $issueTrends = $trendsQuery->fetchAll();

    // Most borrowed books
    $popularQuery = $db->query("
        SELECT
            b.title,
            b.author,
            COUNT(i.id) as times_borrowed
        FROM library_books b
        JOIN library_issues i ON b.id = i.book_id
        GROUP BY b.id
        ORDER BY times_borrowed DESC
        LIMIT 10
    ");
    $popularBooks = $popularQuery->fetchAll();

    // Overdue books
    $overdueQuery = $db->query("
        SELECT
            i.*,
            b.title,
            s.name as student_name
        FROM library_issues i
        JOIN library_books b ON i.book_id = b.id
        JOIN students s ON i.student_id = s.id
        WHERE i.return_date IS NULL AND i.due_date < CURDATE()
        ORDER BY i.due_date
    ");
    $overdueBooks = $overdueQuery->fetchAll();

    sendResponse(true, 'Library report generated', [
        'by_category' => $byCategory,
        'issue_trends' => $issueTrends,
        'popular_books' => $popularBooks,
        'overdue_books' => $overdueBooks,
        'overdue_count' => count($overdueBooks)
    ]);
}
?>
