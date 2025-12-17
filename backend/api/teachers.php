<?php
/**
 * Teachers API Endpoint
 * EduManage Pro - School Management System
 *
 * Handles all CRUD operations for teachers
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
                getTeachersList($db, $params);
                break;

            case 'single':
                getSingleTeacher($db, $params);
                break;

            case 'stats':
                getTeachersStats($db);
                break;

            default:
                sendResponse(false, 'Invalid action');
        }
    } catch (Exception $e) {
        sendResponse(false, 'Error processing request', null, ['error' => $e->getMessage()]);
    }
}

/**
 * Get teachers list
 */
function getTeachersList($db, $params) {
    $page = isset($params['page']) ? (int)$params['page'] : 1;
    $perPage = isset($params['perPage']) ? (int)$params['perPage'] : 10;
    $search = $params['search'] ?? '';
    $subject = $params['subject'] ?? '';
    $status = $params['status'] ?? '';

    $where = [];
    $bindings = [];

    if (!empty($search)) {
        $where[] = "(name LIKE :search OR id LIKE :search OR contact LIKE :search)";
        $bindings[':search'] = "%$search%";
    }

    if (!empty($subject)) {
        $where[] = "subject = :subject";
        $bindings[':subject'] = $subject;
    }

    if (!empty($status)) {
        $where[] = "status = :status";
        $bindings[':status'] = $status;
    }

    $whereClause = !empty($where) ? 'WHERE ' . implode(' AND ', $where) : '';

    // Get total count
    $countQuery = "SELECT COUNT(*) as total FROM teachers $whereClause";
    $countStmt = $db->prepare($countQuery);
    $countStmt->execute($bindings);
    $totalRecords = $countStmt->fetch()['total'];

    // Get paginated records
    $offset = ($page - 1) * $perPage;
    $query = "SELECT * FROM teachers $whereClause ORDER BY created_at DESC LIMIT :limit OFFSET :offset";
    $stmt = $db->prepare($query);

    foreach ($bindings as $key => $value) {
        $stmt->bindValue($key, $value);
    }
    $stmt->bindValue(':limit', $perPage, PDO::PARAM_INT);
    $stmt->bindValue(':offset', $offset, PDO::PARAM_INT);

    $stmt->execute();
    $teachers = $stmt->fetchAll();

    $response = [
        'teachers' => $teachers,
        'pagination' => [
            'page' => $page,
            'perPage' => $perPage,
            'total' => $totalRecords,
            'totalPages' => ceil($totalRecords / $perPage)
        ]
    ];

    sendResponse(true, 'Teachers fetched successfully', $response);
}

/**
 * Get single teacher
 */
function getSingleTeacher($db, $params) {
    if (empty($params['id'])) {
        sendResponse(false, 'Teacher ID is required');
    }

    $stmt = $db->prepare("SELECT * FROM teachers WHERE id = :id");
    $stmt->execute([':id' => $params['id']]);
    $teacher = $stmt->fetch();

    if (!$teacher) {
        sendResponse(false, 'Teacher not found');
    }

    sendResponse(true, 'Teacher fetched successfully', $teacher);
}

/**
 * Get teachers statistics
 */
function getTeachersStats($db) {
    // Total teachers
    $totalStmt = $db->query("SELECT COUNT(*) as total FROM teachers");
    $total = $totalStmt->fetch()['total'];

    // Active teachers
    $activeStmt = $db->query("SELECT COUNT(*) as total FROM teachers WHERE status = 'Active'");
    $active = $activeStmt->fetch()['total'];

    // Male/Female count
    $maleStmt = $db->query("SELECT COUNT(*) as total FROM teachers WHERE gender = 'Male'");
    $male = $maleStmt->fetch()['total'];

    $femaleStmt = $db->query("SELECT COUNT(*) as total FROM teachers WHERE gender = 'Female'");
    $female = $femaleStmt->fetch()['total'];

    // Subjects taught
    $subjectsStmt = $db->query("SELECT COUNT(DISTINCT subject) as total FROM teachers");
    $subjects = $subjectsStmt->fetch()['total'];

    $stats = [
        'total' => $total,
        'active' => $active,
        'male' => $male,
        'female' => $female,
        'subjects' => $subjects
    ];

    sendResponse(true, 'Statistics fetched successfully', $stats);
}

/**
 * Handle POST - Create teacher
 */
function handlePost($db, $data) {
    try {
        // Validate required fields
        $required = ['name', 'gender', 'subject', 'contact'];
        $errors = validateRequired($data, $required);

        if (!empty($errors)) {
            sendResponse(false, 'Validation failed', null, $errors);
        }

        // Sanitize input
        $name = sanitizeInput($data['name']);
        $gender = sanitizeInput($data['gender']);
        $subject = sanitizeInput($data['subject']);
        $contact = sanitizeInput($data['contact']);
        $email = isset($data['email']) ? sanitizeInput($data['email']) : null;
        $address = isset($data['address']) ? sanitizeInput($data['address']) : null;
        $qualification = isset($data['qualification']) ? sanitizeInput($data['qualification']) : null;
        $experience = isset($data['experience']) ? (int)$data['experience'] : null;
        $joiningDate = isset($data['joining_date']) ? formatDateForDB($data['joining_date']) : date('Y-m-d');
        $salary = isset($data['salary']) ? (float)$data['salary'] : null;
        $photo = isset($data['photo']) ? $data['photo'] : null;
        $status = isset($data['status']) ? sanitizeInput($data['status']) : 'Active';
        $employeeId = isset($data['employee_id']) ? sanitizeInput($data['employee_id']) : generateId('EMP', 8);
        $department = isset($data['department']) ? sanitizeInput($data['department']) : null;
        $designation = isset($data['designation']) ? sanitizeInput($data['designation']) : null;

        // Validate email if provided
        if ($email && !validateEmail($email)) {
            sendResponse(false, 'Invalid email format', null, ['email' => 'Invalid']);
        }

        // Generate teacher ID
        $teacherId = generateId('TCH', 10);

        // Insert teacher
        $stmt = $db->prepare("
            INSERT INTO teachers (id, name, gender, subject, contact, email, address, qualification, experience, joining_date, salary, photo, status, employee_id, department, designation)
            VALUES (:id, :name, :gender, :subject, :contact, :email, :address, :qualification, :experience, :joining_date, :salary, :photo, :status, :employee_id, :department, :designation)
        ");

        $stmt->execute([
            ':id' => $teacherId,
            ':name' => $name,
            ':gender' => $gender,
            ':subject' => $subject,
            ':contact' => $contact,
            ':email' => $email,
            ':address' => $address,
            ':qualification' => $qualification,
            ':experience' => $experience,
            ':joining_date' => $joiningDate,
            ':salary' => $salary,
            ':photo' => $photo,
            ':status' => $status,
            ':employee_id' => $employeeId,
            ':department' => $department,
            ':designation' => $designation
        ]);

        // Log activity
        logActivity($db, getCurrentUserId(), 'Created teacher', 'Teachers', ['teacher_id' => $teacherId, 'name' => $name]);

        sendResponse(true, 'Teacher created successfully', ['id' => $teacherId, 'employee_id' => $employeeId]);

    } catch (Exception $e) {
        sendResponse(false, 'Error creating teacher', null, ['error' => $e->getMessage()]);
    }
}

/**
 * Handle PUT - Update teacher
 */
function handlePut($db, $data) {
    try {
        // Validate ID
        if (empty($data['id'])) {
            sendResponse(false, 'Teacher ID is required');
        }

        $teacherId = sanitizeInput($data['id']);

        // Check if teacher exists
        $checkStmt = $db->prepare("SELECT id FROM teachers WHERE id = :id");
        $checkStmt->execute([':id' => $teacherId]);

        if (!$checkStmt->fetch()) {
            sendResponse(false, 'Teacher not found');
        }

        // Build update query
        $fields = [];
        $bindings = [':id' => $teacherId];

        $updateableFields = ['name', 'gender', 'subject', 'contact', 'email', 'address', 'qualification', 'experience', 'joining_date', 'salary', 'photo', 'status', 'department', 'designation'];

        foreach ($updateableFields as $field) {
            if (isset($data[$field])) {
                $dbField = $field;

                if (in_array($field, ['joining_date'])) {
                    $fields[] = "$dbField = :$field";
                    $bindings[":$field"] = formatDateForDB($data[$field]);
                } elseif (in_array($field, ['salary', 'experience'])) {
                    $fields[] = "$dbField = :$field";
                    $bindings[":$field"] = (float)$data[$field];
                } else {
                    $fields[] = "$dbField = :$field";
                    $bindings[":$field"] = sanitizeInput($data[$field]);
                }
            }
        }

        if (empty($fields)) {
            sendResponse(false, 'No fields to update');
        }

        // Update teacher
        $query = "UPDATE teachers SET " . implode(', ', $fields) . " WHERE id = :id";
        $stmt = $db->prepare($query);
        $stmt->execute($bindings);

        // Log activity
        logActivity($db, getCurrentUserId(), 'Updated teacher', 'Teachers', ['teacher_id' => $teacherId]);

        sendResponse(true, 'Teacher updated successfully', ['id' => $teacherId]);

    } catch (Exception $e) {
        sendResponse(false, 'Error updating teacher', null, ['error' => $e->getMessage()]);
    }
}

/**
 * Handle DELETE - Delete teacher
 */
function handleDelete($db, $params) {
    try {
        if (isset($params['ids']) && !empty($params['ids'])) {
            // Bulk delete
            $ids = explode(',', $params['ids']);
            $ids = array_map('sanitizeInput', $ids);

            $placeholders = implode(',', array_fill(0, count($ids), '?'));
            $stmt = $db->prepare("DELETE FROM teachers WHERE id IN ($placeholders)");
            $stmt->execute($ids);

            $deletedCount = $stmt->rowCount();
            logActivity($db, getCurrentUserId(), 'Bulk deleted teachers', 'Teachers', ['count' => $deletedCount]);

            sendResponse(true, "$deletedCount teacher(s) deleted successfully", ['count' => $deletedCount]);

        } elseif (isset($params['id']) && !empty($params['id'])) {
            // Single delete
            $teacherId = sanitizeInput($params['id']);

            $stmt = $db->prepare("DELETE FROM teachers WHERE id = :id");
            $stmt->execute([':id' => $teacherId]);

            if ($stmt->rowCount() === 0) {
                sendResponse(false, 'Teacher not found');
            }

            logActivity($db, getCurrentUserId(), 'Deleted teacher', 'Teachers', ['teacher_id' => $teacherId]);

            sendResponse(true, 'Teacher deleted successfully', ['id' => $teacherId]);

        } else {
            sendResponse(false, 'Teacher ID is required');
        }

    } catch (Exception $e) {
        sendResponse(false, 'Error deleting teacher', null, ['error' => $e->getMessage()]);
    }
}
?>
