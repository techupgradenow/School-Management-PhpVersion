<?php
/**
 * Exam Marks API Endpoint
 * EduManage Pro - School Management System
 *
 * Fast bulk entry and management of student exam marks
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
        $action = $_GET['action'] ?? 'list';
        switch ($action) {
            case 'list':
                getMarksList($db);
                break;
            case 'by_exam':
                getMarksByExam($db, $_GET['exam_id'] ?? '');
                break;
            case 'by_student':
                getMarksByStudent($db, $_GET['student_id'] ?? '');
                break;
            case 'class_results':
                getClassResults($db, $_GET['exam_id'] ?? '', $_GET['class'] ?? '', $_GET['section'] ?? '');
                break;
            default:
                sendResponse(false, 'Invalid action');
        }
        break;

    case 'POST':
        $action = $data['action'] ?? 'single';
        switch ($action) {
            case 'single':
                addSingleMark($db, $data);
                break;
            case 'bulk':
                addBulkMarks($db, $data);
                break;
            default:
                sendResponse(false, 'Invalid action');
        }
        break;

    case 'PUT':
        updateMark($db, $data);
        break;

    case 'DELETE':
        $id = $_GET['id'] ?? '';
        deleteMark($db, $id);
        break;

    default:
        sendResponse(false, 'Method not allowed');
}

/**
 * Get all marks with filters
 */
function getMarksList($db) {
    try {
        $examId = $_GET['exam_id'] ?? '';
        $studentId = $_GET['student_id'] ?? '';
        $class = $_GET['class'] ?? '';

        $sql = "
            SELECT em.*,
                   e.name as exam_name, e.class as exam_class, e.max_marks, e.pass_marks,
                   s.name as student_name, s.roll_no, s.class as student_class, s.section,
                   sub.name as subject_name
            FROM exam_marks em
            JOIN exams e ON em.exam_id = e.id
            JOIN students s ON em.student_id = s.id
            LEFT JOIN subjects sub ON e.subject_id = sub.id
            WHERE 1=1
        ";

        $params = [];

        if ($examId) {
            $sql .= " AND em.exam_id = ?";
            $params[] = $examId;
        }
        if ($studentId) {
            $sql .= " AND em.student_id = ?";
            $params[] = $studentId;
        }
        if ($class) {
            $sql .= " AND s.class = ?";
            $params[] = $class;
        }

        $sql .= " ORDER BY s.class, s.section, s.roll_no, e.exam_date";

        $stmt = $db->prepare($sql);
        $stmt->execute($params);
        $marks = $stmt->fetchAll();

        sendResponse(true, 'Marks fetched successfully', $marks);
    } catch (Exception $e) {
        sendResponse(false, 'Error fetching marks', null, ['error' => $e->getMessage()]);
    }
}

/**
 * Get marks for a specific exam
 */
function getMarksByExam($db, $examId) {
    if (!$examId) {
        sendResponse(false, 'Exam ID is required');
    }

    try {
        // Get exam details
        $examStmt = $db->prepare("
            SELECT e.*, sub.name as subject_name
            FROM exams e
            LEFT JOIN subjects sub ON e.subject_id = sub.id
            WHERE e.id = ?
        ");
        $examStmt->execute([$examId]);
        $exam = $examStmt->fetch();

        if (!$exam) {
            sendResponse(false, 'Exam not found');
        }

        // Get all students for this class with their marks
        $stmt = $db->prepare("
            SELECT s.id, s.name, s.roll_no, s.section, s.gender,
                   em.marks_obtained, em.remarks, em.id as mark_id
            FROM students s
            LEFT JOIN exam_marks em ON s.id = em.student_id AND em.exam_id = ?
            WHERE s.class = ? AND s.status = 'Active'
            ORDER BY s.section, s.roll_no, s.name
        ");
        $stmt->execute([$examId, $exam['class']]);
        $students = $stmt->fetchAll();

        // Calculate stats
        $totalStudents = count($students);
        $markedCount = 0;
        $passed = 0;
        $totalMarks = 0;
        $highest = 0;
        $lowest = $exam['max_marks'];

        foreach ($students as $s) {
            if ($s['marks_obtained'] !== null) {
                $markedCount++;
                $totalMarks += $s['marks_obtained'];
                if ($s['marks_obtained'] >= $exam['pass_marks']) $passed++;
                if ($s['marks_obtained'] > $highest) $highest = $s['marks_obtained'];
                if ($s['marks_obtained'] < $lowest) $lowest = $s['marks_obtained'];
            }
        }

        $stats = [
            'total_students' => $totalStudents,
            'marked' => $markedCount,
            'pending' => $totalStudents - $markedCount,
            'passed' => $passed,
            'failed' => $markedCount - $passed,
            'average' => $markedCount > 0 ? round($totalMarks / $markedCount, 2) : 0,
            'highest' => $markedCount > 0 ? $highest : 0,
            'lowest' => $markedCount > 0 ? $lowest : 0,
            'pass_percentage' => $markedCount > 0 ? round(($passed / $markedCount) * 100, 1) : 0
        ];

        sendResponse(true, 'Exam marks fetched', [
            'exam' => $exam,
            'students' => $students,
            'stats' => $stats
        ]);
    } catch (Exception $e) {
        sendResponse(false, 'Error fetching exam marks', null, ['error' => $e->getMessage()]);
    }
}

/**
 * Get all marks for a student
 */
function getMarksByStudent($db, $studentId) {
    if (!$studentId) {
        sendResponse(false, 'Student ID is required');
    }

    try {
        $stmt = $db->prepare("
            SELECT em.*,
                   e.name as exam_name, e.exam_date, e.max_marks, e.pass_marks,
                   sub.name as subject_name,
                   CASE WHEN em.marks_obtained >= e.pass_marks THEN 'Pass' ELSE 'Fail' END as result,
                   ROUND((em.marks_obtained / e.max_marks) * 100, 1) as percentage
            FROM exam_marks em
            JOIN exams e ON em.exam_id = e.id
            LEFT JOIN subjects sub ON e.subject_id = sub.id
            WHERE em.student_id = ?
            ORDER BY e.exam_date DESC
        ");
        $stmt->execute([$studentId]);
        $marks = $stmt->fetchAll();

        // Get student details
        $studentStmt = $db->prepare("SELECT * FROM students WHERE id = ?");
        $studentStmt->execute([$studentId]);
        $student = $studentStmt->fetch();

        sendResponse(true, 'Student marks fetched', [
            'student' => $student,
            'marks' => $marks
        ]);
    } catch (Exception $e) {
        sendResponse(false, 'Error fetching student marks', null, ['error' => $e->getMessage()]);
    }
}

/**
 * Get class results for an exam
 */
function getClassResults($db, $examId, $class, $section) {
    try {
        $sql = "
            SELECT s.id, s.name, s.roll_no, s.section,
                   em.marks_obtained,
                   e.max_marks, e.pass_marks,
                   ROUND((em.marks_obtained / e.max_marks) * 100, 1) as percentage,
                   CASE WHEN em.marks_obtained >= e.pass_marks THEN 'Pass' ELSE 'Fail' END as result
            FROM students s
            LEFT JOIN exam_marks em ON s.id = em.student_id AND em.exam_id = ?
            LEFT JOIN exams e ON em.exam_id = e.id
            WHERE s.status = 'Active'
        ";

        $params = [$examId];

        if ($class) {
            $sql .= " AND s.class = ?";
            $params[] = $class;
        }
        if ($section) {
            $sql .= " AND s.section = ?";
            $params[] = $section;
        }

        $sql .= " ORDER BY em.marks_obtained DESC, s.name";

        $stmt = $db->prepare($sql);
        $stmt->execute($params);
        $results = $stmt->fetchAll();

        // Add rank
        $rank = 0;
        $prevMarks = -1;
        foreach ($results as &$r) {
            if ($r['marks_obtained'] !== null) {
                if ($r['marks_obtained'] != $prevMarks) {
                    $rank++;
                }
                $r['rank'] = $rank;
                $prevMarks = $r['marks_obtained'];
            } else {
                $r['rank'] = '-';
            }
        }

        sendResponse(true, 'Class results fetched', $results);
    } catch (Exception $e) {
        sendResponse(false, 'Error fetching results', null, ['error' => $e->getMessage()]);
    }
}

/**
 * Add single mark
 */
function addSingleMark($db, $data) {
    try {
        $required = ['exam_id', 'student_id', 'marks_obtained'];
        $errors = validateRequired($data, $required);
        if (!empty($errors)) {
            sendResponse(false, 'Required fields missing', null, $errors);
        }

        $userId = $_SESSION['user_id'] ?? null;

        // Use INSERT ... ON DUPLICATE KEY UPDATE for fast upsert
        $stmt = $db->prepare("
            INSERT INTO exam_marks (exam_id, student_id, marks_obtained, remarks, entered_by)
            VALUES (?, ?, ?, ?, ?)
            ON DUPLICATE KEY UPDATE
                marks_obtained = VALUES(marks_obtained),
                remarks = VALUES(remarks),
                entered_by = VALUES(entered_by),
                updated_at = CURRENT_TIMESTAMP
        ");

        $stmt->execute([
            $data['exam_id'],
            $data['student_id'],
            $data['marks_obtained'],
            $data['remarks'] ?? null,
            $userId
        ]);

        sendResponse(true, 'Marks saved successfully');
    } catch (Exception $e) {
        sendResponse(false, 'Error saving marks', null, ['error' => $e->getMessage()]);
    }
}

/**
 * Add bulk marks - FAST bulk entry
 */
function addBulkMarks($db, $data) {
    try {
        if (!isset($data['marks']) || !is_array($data['marks']) || empty($data['marks'])) {
            sendResponse(false, 'Marks data is required');
        }

        $examId = $data['exam_id'] ?? '';
        if (!$examId) {
            sendResponse(false, 'Exam ID is required');
        }

        $userId = $_SESSION['user_id'] ?? null;
        $marks = $data['marks'];

        $db->beginTransaction();

        // Prepare statement once, execute multiple times (faster)
        $stmt = $db->prepare("
            INSERT INTO exam_marks (exam_id, student_id, marks_obtained, remarks, entered_by)
            VALUES (?, ?, ?, ?, ?)
            ON DUPLICATE KEY UPDATE
                marks_obtained = VALUES(marks_obtained),
                remarks = VALUES(remarks),
                entered_by = VALUES(entered_by),
                updated_at = CURRENT_TIMESTAMP
        ");

        $savedCount = 0;
        $errors = [];

        foreach ($marks as $mark) {
            if (!isset($mark['student_id']) || !isset($mark['marks_obtained'])) {
                continue;
            }

            try {
                $stmt->execute([
                    $examId,
                    $mark['student_id'],
                    $mark['marks_obtained'],
                    $mark['remarks'] ?? null,
                    $userId
                ]);
                $savedCount++;
            } catch (Exception $e) {
                $errors[] = "Student {$mark['student_id']}: {$e->getMessage()}";
            }
        }

        $db->commit();

        $response = [
            'saved' => $savedCount,
            'total' => count($marks)
        ];

        if (!empty($errors)) {
            $response['errors'] = $errors;
        }

        sendResponse(true, "Saved $savedCount marks successfully", $response);
    } catch (Exception $e) {
        $db->rollBack();
        sendResponse(false, 'Error saving bulk marks', null, ['error' => $e->getMessage()]);
    }
}

/**
 * Update mark
 */
function updateMark($db, $data) {
    try {
        if (!isset($data['id'])) {
            sendResponse(false, 'Mark ID is required');
        }

        $stmt = $db->prepare("
            UPDATE exam_marks
            SET marks_obtained = ?, remarks = ?
            WHERE id = ?
        ");

        $stmt->execute([
            $data['marks_obtained'],
            $data['remarks'] ?? null,
            $data['id']
        ]);

        sendResponse(true, 'Marks updated successfully');
    } catch (Exception $e) {
        sendResponse(false, 'Error updating marks', null, ['error' => $e->getMessage()]);
    }
}

/**
 * Delete mark
 */
function deleteMark($db, $id) {
    try {
        if (!$id) {
            sendResponse(false, 'Mark ID is required');
        }

        $stmt = $db->prepare("DELETE FROM exam_marks WHERE id = ?");
        $stmt->execute([$id]);

        sendResponse(true, 'Marks deleted successfully');
    } catch (Exception $e) {
        sendResponse(false, 'Error deleting marks', null, ['error' => $e->getMessage()]);
    }
}
?>
