<?php
/**
 * Exams API Endpoint
 * EduManage Pro - School Management System
 *
 * Handles all CRUD operations for exams and marks
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

// Route requests
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
                getExamsList($db, $params);
                break;

            case 'single':
                getSingleExam($db, $params);
                break;

            case 'marks':
                getExamMarks($db, $params);
                break;

            case 'student_marks':
                getStudentMarks($db, $params);
                break;

            case 'stats':
                getExamStats($db, $params);
                break;

            case 'results':
                getExamResults($db, $params);
                break;

            default:
                sendResponse(false, 'Invalid action');
        }
    } catch (Exception $e) {
        sendResponse(false, 'Error processing request', null, ['error' => $e->getMessage()]);
    }
}

/**
 * Get exams list
 */
function getExamsList($db, $params) {
    $page = isset($params['page']) ? (int)$params['page'] : 1;
    $perPage = isset($params['perPage']) ? (int)$params['perPage'] : 10;
    $class = $params['class'] ?? '';
    $status = $params['status'] ?? '';
    $search = $params['search'] ?? '';

    $where = [];
    $bindings = [];

    if (!empty($class)) {
        $where[] = "exams.class = :class";
        $bindings[':class'] = $class;
    }

    if (!empty($status)) {
        $where[] = "exams.status = :status";
        $bindings[':status'] = $status;
    }

    if (!empty($search)) {
        $where[] = "(exams.name LIKE :search OR exams.id LIKE :search)";
        $bindings[':search'] = "%$search%";
    }

    $whereClause = !empty($where) ? 'WHERE ' . implode(' AND ', $where) : '';

    // Get total count
    $countQuery = "SELECT COUNT(*) as total FROM exams $whereClause";
    $countStmt = $db->prepare($countQuery);
    $countStmt->execute($bindings);
    $totalRecords = $countStmt->fetch()['total'];

    // Get paginated records
    $offset = ($page - 1) * $perPage;
    $query = "
        SELECT
            exams.*,
            subjects.name as subject_name,
            subjects.code as subject_code
        FROM exams
        LEFT JOIN subjects ON exams.subject_id = subjects.id
        $whereClause
        ORDER BY exams.exam_date DESC, exams.created_at DESC
        LIMIT :limit OFFSET :offset
    ";

    $stmt = $db->prepare($query);
    foreach ($bindings as $key => $value) {
        $stmt->bindValue($key, $value);
    }
    $stmt->bindValue(':limit', $perPage, PDO::PARAM_INT);
    $stmt->bindValue(':offset', $offset, PDO::PARAM_INT);

    $stmt->execute();
    $exams = $stmt->fetchAll();

    $response = [
        'exams' => $exams,
        'pagination' => [
            'page' => $page,
            'perPage' => $perPage,
            'total' => $totalRecords,
            'totalPages' => ceil($totalRecords / $perPage)
        ]
    ];

    sendResponse(true, 'Exams fetched successfully', $response);
}

/**
 * Get single exam
 */
function getSingleExam($db, $params) {
    if (empty($params['id'])) {
        sendResponse(false, 'Exam ID is required');
    }

    $stmt = $db->prepare("
        SELECT
            exams.*,
            subjects.name as subject_name,
            subjects.code as subject_code
        FROM exams
        LEFT JOIN subjects ON exams.subject_id = subjects.id
        WHERE exams.id = :id
    ");
    $stmt->execute([':id' => $params['id']]);
    $exam = $stmt->fetch();

    if (!$exam) {
        sendResponse(false, 'Exam not found');
    }

    sendResponse(true, 'Exam fetched successfully', $exam);
}

/**
 * Get exam marks for all students
 */
function getExamMarks($db, $params) {
    if (empty($params['exam_id'])) {
        sendResponse(false, 'Exam ID is required');
    }

    $examId = $params['exam_id'];

    $query = "
        SELECT
            exam_marks.*,
            students.name as student_name,
            students.roll_no,
            students.class,
            students.section
        FROM exam_marks
        LEFT JOIN students ON exam_marks.student_id = students.id
        WHERE exam_marks.exam_id = :exam_id
        ORDER BY students.roll_no
    ";

    $stmt = $db->prepare($query);
    $stmt->execute([':exam_id' => $examId]);
    $marks = $stmt->fetchAll();

    sendResponse(true, 'Marks fetched successfully', ['marks' => $marks]);
}

/**
 * Get student marks across all exams
 */
function getStudentMarks($db, $params) {
    if (empty($params['student_id'])) {
        sendResponse(false, 'Student ID is required');
    }

    $studentId = $params['student_id'];
    $class = $params['class'] ?? '';

    $where = ["exam_marks.student_id = :student_id"];
    $bindings = [':student_id' => $studentId];

    if (!empty($class)) {
        $where[] = "exams.class = :class";
        $bindings[':class'] = $class;
    }

    $whereClause = 'WHERE ' . implode(' AND ', $where);

    $query = "
        SELECT
            exam_marks.*,
            exams.name as exam_name,
            exams.exam_date,
            exams.max_marks,
            exams.pass_marks,
            subjects.name as subject_name,
            subjects.code as subject_code
        FROM exam_marks
        LEFT JOIN exams ON exam_marks.exam_id = exams.id
        LEFT JOIN subjects ON exams.subject_id = subjects.id
        $whereClause
        ORDER BY exams.exam_date DESC
    ";

    $stmt = $db->prepare($query);
    $stmt->execute($bindings);
    $marks = $stmt->fetchAll();

    sendResponse(true, 'Student marks fetched successfully', ['marks' => $marks]);
}

/**
 * Get exam statistics
 */
function getExamStats($db, $params) {
    $examId = $params['exam_id'] ?? '';

    if (empty($examId)) {
        sendResponse(false, 'Exam ID is required');
    }

    // Get exam details
    $examStmt = $db->prepare("SELECT * FROM exams WHERE id = :id");
    $examStmt->execute([':id' => $examId]);
    $exam = $examStmt->fetch();

    if (!$exam) {
        sendResponse(false, 'Exam not found');
    }

    // Total students appeared
    $totalStmt = $db->prepare("SELECT COUNT(*) as total FROM exam_marks WHERE exam_id = :exam_id");
    $totalStmt->execute([':exam_id' => $examId]);
    $total = $totalStmt->fetch()['total'];

    // Passed students
    $passedStmt = $db->prepare("
        SELECT COUNT(*) as total
        FROM exam_marks
        WHERE exam_id = :exam_id AND marks_obtained >= :pass_marks
    ");
    $passedStmt->execute([
        ':exam_id' => $examId,
        ':pass_marks' => $exam['pass_marks']
    ]);
    $passed = $passedStmt->fetch()['total'];

    // Failed students
    $failed = $total - $passed;

    // Average marks
    $avgStmt = $db->prepare("SELECT AVG(marks_obtained) as average FROM exam_marks WHERE exam_id = :exam_id");
    $avgStmt->execute([':exam_id' => $examId]);
    $average = round($avgStmt->fetch()['average'] ?? 0, 2);

    // Highest marks
    $highestStmt = $db->prepare("SELECT MAX(marks_obtained) as highest FROM exam_marks WHERE exam_id = :exam_id");
    $highestStmt->execute([':exam_id' => $examId]);
    $highest = $highestStmt->fetch()['highest'] ?? 0;

    // Lowest marks
    $lowestStmt = $db->prepare("SELECT MIN(marks_obtained) as lowest FROM exam_marks WHERE exam_id = :exam_id");
    $lowestStmt->execute([':exam_id' => $examId]);
    $lowest = $lowestStmt->fetch()['lowest'] ?? 0;

    $stats = [
        'exam' => $exam,
        'total_students' => $total,
        'passed' => $passed,
        'failed' => $failed,
        'pass_percentage' => $total > 0 ? round(($passed / $total) * 100, 2) : 0,
        'average_marks' => $average,
        'highest_marks' => $highest,
        'lowest_marks' => $lowest
    ];

    sendResponse(true, 'Statistics fetched successfully', $stats);
}

/**
 * Get exam results with grades
 */
function getExamResults($db, $params) {
    if (empty($params['exam_id'])) {
        sendResponse(false, 'Exam ID is required');
    }

    $examId = $params['exam_id'];

    $query = "
        SELECT
            exam_marks.*,
            students.name as student_name,
            students.roll_no,
            students.class,
            students.section,
            exams.max_marks,
            exams.pass_marks
        FROM exam_marks
        LEFT JOIN students ON exam_marks.student_id = students.id
        LEFT JOIN exams ON exam_marks.exam_id = exams.id
        WHERE exam_marks.exam_id = :exam_id
        ORDER BY exam_marks.marks_obtained DESC
    ";

    $stmt = $db->prepare($query);
    $stmt->execute([':exam_id' => $examId]);
    $results = $stmt->fetchAll();

    // Calculate grades
    foreach ($results as &$result) {
        $percentage = ($result['marks_obtained'] / $result['max_marks']) * 100;
        $result['percentage'] = round($percentage, 2);
        $result['grade'] = calculateGrade($percentage);
        $result['status'] = $result['marks_obtained'] >= $result['pass_marks'] ? 'Pass' : 'Fail';
    }

    sendResponse(true, 'Results fetched successfully', ['results' => $results]);
}

/**
 * Calculate grade based on percentage
 */
function calculateGrade($percentage) {
    if ($percentage >= 90) return 'A+';
    if ($percentage >= 80) return 'A';
    if ($percentage >= 70) return 'B+';
    if ($percentage >= 60) return 'B';
    if ($percentage >= 50) return 'C';
    if ($percentage >= 40) return 'D';
    return 'F';
}

/**
 * Handle POST - Create exam or enter marks
 */
function handlePost($db, $data) {
    try {
        $action = $data['action'] ?? 'create_exam';

        switch ($action) {
            case 'create_exam':
                createExam($db, $data);
                break;

            case 'enter_marks':
                enterMarks($db, $data);
                break;

            default:
                sendResponse(false, 'Invalid action');
        }
    } catch (Exception $e) {
        sendResponse(false, 'Error processing request', null, ['error' => $e->getMessage()]);
    }
}

/**
 * Create exam
 */
function createExam($db, $data) {
    // Validate required fields
    $required = ['name', 'class', 'subject_id', 'exam_date', 'max_marks', 'pass_marks'];
    $errors = validateRequired($data, $required);

    if (!empty($errors)) {
        sendResponse(false, 'Validation failed', null, $errors);
    }

    // Sanitize input
    $name = sanitizeInput($data['name']);
    $class = sanitizeInput($data['class']);
    $subjectId = sanitizeInput($data['subject_id']);
    $examDate = formatDateForDB($data['exam_date']);
    $startTime = isset($data['start_time']) ? $data['start_time'] : null;
    $endTime = isset($data['end_time']) ? $data['end_time'] : null;
    $maxMarks = (int)$data['max_marks'];
    $passMarks = (int)$data['pass_marks'];
    $description = isset($data['description']) ? sanitizeInput($data['description']) : null;
    $status = isset($data['status']) ? sanitizeInput($data['status']) : 'Scheduled';

    // Generate exam ID
    $examId = generateId('EXM', 10);

    // Insert exam
    $stmt = $db->prepare("
        INSERT INTO exams (id, name, class, subject_id, exam_date, start_time, end_time, max_marks, pass_marks, description, status)
        VALUES (:id, :name, :class, :subject_id, :exam_date, :start_time, :end_time, :max_marks, :pass_marks, :description, :status)
    ");

    $stmt->execute([
        ':id' => $examId,
        ':name' => $name,
        ':class' => $class,
        ':subject_id' => $subjectId,
        ':exam_date' => $examDate,
        ':start_time' => $startTime,
        ':end_time' => $endTime,
        ':max_marks' => $maxMarks,
        ':pass_marks' => $passMarks,
        ':description' => $description,
        ':status' => $status
    ]);

    // Log activity
    logActivity($db, getCurrentUserId(), 'Created exam', 'Exams', ['exam_id' => $examId, 'name' => $name]);

    sendResponse(true, 'Exam created successfully', ['id' => $examId]);
}

/**
 * Enter marks for students
 */
function enterMarks($db, $data) {
    try {
        if (empty($data['marks']) || !is_array($data['marks'])) {
            sendResponse(false, 'Marks data is required');
        }

        $db->beginTransaction();

        $successCount = 0;
        $errors = [];

        foreach ($data['marks'] as $mark) {
            if (empty($mark['exam_id']) || empty($mark['student_id'])) {
                $errors[] = "Missing exam_id or student_id";
                continue;
            }

            $examId = sanitizeInput($mark['exam_id']);
            $studentId = sanitizeInput($mark['student_id']);
            $marksObtained = (float)$mark['marks_obtained'];
            $remarks = isset($mark['remarks']) ? sanitizeInput($mark['remarks']) : null;
            $enteredBy = getCurrentUserId();

            // Check if marks already exist
            $checkStmt = $db->prepare("
                SELECT id FROM exam_marks
                WHERE exam_id = :exam_id AND student_id = :student_id
            ");
            $checkStmt->execute([
                ':exam_id' => $examId,
                ':student_id' => $studentId
            ]);

            if ($checkStmt->fetch()) {
                // Update existing
                $updateStmt = $db->prepare("
                    UPDATE exam_marks
                    SET marks_obtained = :marks_obtained, remarks = :remarks, entered_by = :entered_by
                    WHERE exam_id = :exam_id AND student_id = :student_id
                ");
                $updateStmt->execute([
                    ':marks_obtained' => $marksObtained,
                    ':remarks' => $remarks,
                    ':entered_by' => $enteredBy,
                    ':exam_id' => $examId,
                    ':student_id' => $studentId
                ]);
            } else {
                // Insert new
                $insertStmt = $db->prepare("
                    INSERT INTO exam_marks (exam_id, student_id, marks_obtained, remarks, entered_by)
                    VALUES (:exam_id, :student_id, :marks_obtained, :remarks, :entered_by)
                ");
                $insertStmt->execute([
                    ':exam_id' => $examId,
                    ':student_id' => $studentId,
                    ':marks_obtained' => $marksObtained,
                    ':remarks' => $remarks,
                    ':entered_by' => $enteredBy
                ]);
            }

            $successCount++;
        }

        $db->commit();

        // Log activity
        logActivity($db, getCurrentUserId(), 'Entered exam marks', 'Exams', ['count' => $successCount]);

        sendResponse(true, "Marks entered successfully for $successCount student(s)", [
            'success_count' => $successCount,
            'errors' => $errors
        ]);

    } catch (Exception $e) {
        if ($db->inTransaction()) {
            $db->rollBack();
        }
        sendResponse(false, 'Error entering marks', null, ['error' => $e->getMessage()]);
    }
}

/**
 * Handle PUT - Update exam
 */
function handlePut($db, $data) {
    try {
        if (empty($data['id'])) {
            sendResponse(false, 'Exam ID is required');
        }

        $examId = sanitizeInput($data['id']);

        // Check if exam exists
        $checkStmt = $db->prepare("SELECT id FROM exams WHERE id = :id");
        $checkStmt->execute([':id' => $examId]);

        if (!$checkStmt->fetch()) {
            sendResponse(false, 'Exam not found');
        }

        // Build update query
        $fields = [];
        $bindings = [':id' => $examId];

        $updateableFields = ['name', 'class', 'subject_id', 'exam_date', 'start_time', 'end_time', 'max_marks', 'pass_marks', 'description', 'status'];

        foreach ($updateableFields as $field) {
            if (isset($data[$field])) {
                $dbField = $field;

                if ($field === 'exam_date') {
                    $fields[] = "$dbField = :$field";
                    $bindings[":$field"] = formatDateForDB($data[$field]);
                } elseif (in_array($field, ['max_marks', 'pass_marks'])) {
                    $fields[] = "$dbField = :$field";
                    $bindings[":$field"] = (int)$data[$field];
                } else {
                    $fields[] = "$dbField = :$field";
                    $bindings[":$field"] = sanitizeInput($data[$field]);
                }
            }
        }

        if (empty($fields)) {
            sendResponse(false, 'No fields to update');
        }

        // Update exam
        $query = "UPDATE exams SET " . implode(', ', $fields) . " WHERE id = :id";
        $stmt = $db->prepare($query);
        $stmt->execute($bindings);

        // Log activity
        logActivity($db, getCurrentUserId(), 'Updated exam', 'Exams', ['exam_id' => $examId]);

        sendResponse(true, 'Exam updated successfully', ['id' => $examId]);

    } catch (Exception $e) {
        sendResponse(false, 'Error updating exam', null, ['error' => $e->getMessage()]);
    }
}

/**
 * Handle DELETE - Delete exam
 */
function handleDelete($db, $params) {
    try {
        if (empty($params['id'])) {
            sendResponse(false, 'Exam ID is required');
        }

        $examId = sanitizeInput($params['id']);

        $stmt = $db->prepare("DELETE FROM exams WHERE id = :id");
        $stmt->execute([':id' => $examId]);

        if ($stmt->rowCount() === 0) {
            sendResponse(false, 'Exam not found');
        }

        // Log activity
        logActivity($db, getCurrentUserId(), 'Deleted exam', 'Exams', ['exam_id' => $examId]);

        sendResponse(true, 'Exam deleted successfully', ['id' => $examId]);

    } catch (Exception $e) {
        sendResponse(false, 'Error deleting exam', null, ['error' => $e->getMessage()]);
    }
}
?>
