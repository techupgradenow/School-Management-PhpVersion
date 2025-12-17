<?php
/**
 * Students API Endpoint
 * EduManage Pro - School Management System
 *
 * Handles all CRUD operations for students
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

// Route requests based on method and action
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
 * Handle GET requests - Fetch students
 */
function handleGet($db, $params) {
    try {
        $action = $params['action'] ?? 'list';

        switch ($action) {
            case 'list':
                getStudentsList($db, $params);
                break;

            case 'single':
                getSingleStudent($db, $params);
                break;

            case 'stats':
                getStudentsStats($db, $params);
                break;

            case 'search':
                searchStudents($db, $params);
                break;

            default:
                sendResponse(false, 'Invalid action', null, ['action' => 'Unknown action']);
        }
    } catch (Exception $e) {
        sendResponse(false, 'Error processing request', null, ['error' => $e->getMessage()]);
    }
}

/**
 * Get students list with filters and pagination
 */
function getStudentsList($db, $params) {
    $page = isset($params['page']) ? (int)$params['page'] : 1;
    $perPage = isset($params['perPage']) ? (int)$params['perPage'] : 10;
    $search = $params['search'] ?? '';
    $class = $params['class'] ?? '';
    $section = $params['section'] ?? '';
    $status = $params['status'] ?? '';
    $gender = $params['gender'] ?? '';

    // Build query
    $where = [];
    $bindings = [];

    if (!empty($search)) {
        $where[] = "(name LIKE :search OR id LIKE :search OR contact LIKE :search)";
        $bindings[':search'] = "%$search%";
    }

    if (!empty($class)) {
        $where[] = "class = :class";
        $bindings[':class'] = $class;
    }

    if (!empty($section)) {
        $where[] = "section = :section";
        $bindings[':section'] = $section;
    }

    if (!empty($status)) {
        $where[] = "status = :status";
        $bindings[':status'] = $status;
    }

    if (!empty($gender)) {
        $where[] = "gender = :gender";
        $bindings[':gender'] = $gender;
    }

    $whereClause = !empty($where) ? 'WHERE ' . implode(' AND ', $where) : '';

    // Get total count
    $countQuery = "SELECT COUNT(*) as total FROM students $whereClause";
    $countStmt = $db->prepare($countQuery);
    $countStmt->execute($bindings);
    $totalRecords = $countStmt->fetch()['total'];

    // Get paginated records
    $offset = ($page - 1) * $perPage;
    $query = "SELECT * FROM students $whereClause ORDER BY created_at DESC LIMIT :limit OFFSET :offset";
    $stmt = $db->prepare($query);

    foreach ($bindings as $key => $value) {
        $stmt->bindValue($key, $value);
    }
    $stmt->bindValue(':limit', $perPage, PDO::PARAM_INT);
    $stmt->bindValue(':offset', $offset, PDO::PARAM_INT);

    $stmt->execute();
    $students = $stmt->fetchAll();

    // Get documents for each student
    foreach ($students as &$student) {
        $docsQuery = "SELECT * FROM student_documents WHERE student_id = :student_id";
        $docsStmt = $db->prepare($docsQuery);
        $docsStmt->execute([':student_id' => $student['id']]);
        $student['documents'] = $docsStmt->fetchAll();
    }

    $response = [
        'students' => $students,
        'pagination' => [
            'page' => $page,
            'perPage' => $perPage,
            'total' => $totalRecords,
            'totalPages' => ceil($totalRecords / $perPage)
        ]
    ];

    sendResponse(true, 'Students fetched successfully', $response);
}

/**
 * Get single student by ID
 */
function getSingleStudent($db, $params) {
    if (empty($params['id'])) {
        sendResponse(false, 'Student ID is required', null, ['id' => 'Missing']);
    }

    $stmt = $db->prepare("SELECT * FROM students WHERE id = :id");
    $stmt->execute([':id' => $params['id']]);
    $student = $stmt->fetch();

    if (!$student) {
        sendResponse(false, 'Student not found', null, ['id' => 'Invalid']);
    }

    // Get documents
    $docsStmt = $db->prepare("SELECT * FROM student_documents WHERE student_id = :student_id");
    $docsStmt->execute([':student_id' => $student['id']]);
    $student['documents'] = $docsStmt->fetchAll();

    sendResponse(true, 'Student fetched successfully', $student);
}

/**
 * Get students statistics
 */
function getStudentsStats($db, $params) {
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

    // Total students
    $totalQuery = "SELECT COUNT(*) as total FROM students $whereClause";
    $totalStmt = $db->prepare($totalQuery);
    $totalStmt->execute($bindings);
    $total = $totalStmt->fetch()['total'];

    // Active students - use hardcoded value in query directly
    $activeWhere = $where;
    $activeWhere[] = "status = 'Active'";
    $activeWhereClause = 'WHERE ' . implode(' AND ', $activeWhere);

    $activeQuery = "SELECT COUNT(*) as total FROM students $activeWhereClause";
    $activeStmt = $db->prepare($activeQuery);
    $activeStmt->execute($bindings);
    $active = $activeStmt->fetch()['total'];

    // Male count
    $maleWhere = $where;
    $maleWhere[] = "gender = 'Male'";
    $maleWhereClause = 'WHERE ' . implode(' AND ', $maleWhere);

    $maleQuery = "SELECT COUNT(*) as total FROM students $maleWhereClause";
    $maleStmt = $db->prepare($maleQuery);
    $maleStmt->execute($bindings);
    $male = $maleStmt->fetch()['total'];

    // Female count
    $femaleWhere = $where;
    $femaleWhere[] = "gender = 'Female'";
    $femaleWhereClause = 'WHERE ' . implode(' AND ', $femaleWhere);

    $femaleQuery = "SELECT COUNT(*) as total FROM students $femaleWhereClause";
    $femaleStmt = $db->prepare($femaleQuery);
    $femaleStmt->execute($bindings);
    $female = $femaleStmt->fetch()['total'];

    // New admissions this month
    $monthWhere = $where;
    $monthWhere[] = "DATE_FORMAT(joining_date, '%Y-%m') = DATE_FORMAT(CURDATE(), '%Y-%m')";
    $monthWhereClause = 'WHERE ' . implode(' AND ', $monthWhere);

    $monthQuery = "SELECT COUNT(*) as total FROM students $monthWhereClause";
    $monthStmt = $db->prepare($monthQuery);
    $monthStmt->execute($bindings);
    $newThisMonth = $monthStmt->fetch()['total'];

    $stats = [
        'total' => $total,
        'active' => $active,
        'male' => $male,
        'female' => $female,
        'newThisMonth' => $newThisMonth
    ];

    sendResponse(true, 'Statistics fetched successfully', $stats);
}

/**
 * Search students
 */
function searchStudents($db, $params) {
    $query = $params['q'] ?? '';

    if (empty($query)) {
        sendResponse(false, 'Search query is required', null, ['query' => 'Missing']);
    }

    $stmt = $db->prepare("
        SELECT id, name, class, section, contact, status
        FROM students
        WHERE name LIKE :query OR id LIKE :query OR contact LIKE :query
        LIMIT 20
    ");

    $stmt->execute([':query' => "%$query%"]);
    $results = $stmt->fetchAll();

    sendResponse(true, 'Search completed', $results);
}

/**
 * Handle POST requests - Create student
 */
function handlePost($db, $data) {
    try {
        // Validate required fields
        $required = ['name', 'gender', 'class', 'section', 'parent_name', 'contact'];
        $errors = validateRequired($data, $required);

        if (!empty($errors)) {
            sendResponse(false, 'Validation failed', null, $errors);
        }

        // Sanitize input
        $name = sanitizeInput($data['name']);
        $gender = sanitizeInput($data['gender']);
        $class = sanitizeInput($data['class']);
        $section = sanitizeInput($data['section']);
        $parentName = sanitizeInput($data['parent_name']);
        $contact = sanitizeInput($data['contact']);
        $email = isset($data['email']) ? sanitizeInput($data['email']) : null;
        $address = isset($data['address']) ? sanitizeInput($data['address']) : null;
        $dob = isset($data['dob']) ? formatDateForDB($data['dob']) : null;
        $joiningDate = isset($data['joining_date']) ? formatDateForDB($data['joining_date']) : date('Y-m-d');
        $bloodGroup = isset($data['blood_group']) ? sanitizeInput($data['blood_group']) : null;
        $photo = isset($data['photo']) ? $data['photo'] : null;
        $status = isset($data['status']) ? sanitizeInput($data['status']) : 'Active';

        // Validate email if provided
        if ($email && !validateEmail($email)) {
            sendResponse(false, 'Invalid email format', null, ['email' => 'Invalid']);
        }

        // Generate student ID
        $studentId = generateId('STU', 10);

        // Generate admission number
        $admissionNo = generateAdmissionNo('ADM');

        // Begin transaction
        $db->beginTransaction();

        // Insert student
        $stmt = $db->prepare("
            INSERT INTO students (id, name, gender, class, section, parent_name, contact, email, address, dob, joining_date, blood_group, photo, status, admission_no)
            VALUES (:id, :name, :gender, :class, :section, :parent_name, :contact, :email, :address, :dob, :joining_date, :blood_group, :photo, :status, :admission_no)
        ");

        $stmt->execute([
            ':id' => $studentId,
            ':name' => $name,
            ':gender' => $gender,
            ':class' => $class,
            ':section' => $section,
            ':parent_name' => $parentName,
            ':contact' => $contact,
            ':email' => $email,
            ':address' => $address,
            ':dob' => $dob,
            ':joining_date' => $joiningDate,
            ':blood_group' => $bloodGroup,
            ':photo' => $photo,
            ':status' => $status,
            ':admission_no' => $admissionNo
        ]);

        // Insert documents if provided
        if (isset($data['documents']) && is_array($data['documents'])) {
            foreach ($data['documents'] as $doc) {
                $docStmt = $db->prepare("
                    INSERT INTO student_documents (student_id, name, type, file_name, file_type, file_data)
                    VALUES (:student_id, :name, :type, :file_name, :file_type, :file_data)
                ");

                $docStmt->execute([
                    ':student_id' => $studentId,
                    ':name' => sanitizeInput($doc['name']),
                    ':type' => sanitizeInput($doc['type']),
                    ':file_name' => sanitizeInput($doc['fileName']),
                    ':file_type' => sanitizeInput($doc['fileType']),
                    ':file_data' => $doc['file']
                ]);
            }
        }

        // Commit transaction
        $db->commit();

        // Log activity
        logActivity($db, getCurrentUserId(), 'Created student', 'Students', ['student_id' => $studentId, 'name' => $name]);

        sendResponse(true, 'Student created successfully', ['id' => $studentId, 'admission_no' => $admissionNo]);

    } catch (Exception $e) {
        // Rollback on error
        if ($db->inTransaction()) {
            $db->rollBack();
        }
        sendResponse(false, 'Error creating student', null, ['error' => $e->getMessage()]);
    }
}

/**
 * Handle PUT requests - Update student
 */
function handlePut($db, $data) {
    try {
        // Validate ID
        if (empty($data['id'])) {
            sendResponse(false, 'Student ID is required', null, ['id' => 'Missing']);
        }

        $studentId = sanitizeInput($data['id']);

        // Check if student exists
        $checkStmt = $db->prepare("SELECT id FROM students WHERE id = :id");
        $checkStmt->execute([':id' => $studentId]);

        if (!$checkStmt->fetch()) {
            sendResponse(false, 'Student not found', null, ['id' => 'Invalid']);
        }

        // Build update query
        $fields = [];
        $bindings = [':id' => $studentId];

        $updateableFields = ['name', 'gender', 'class', 'section', 'parent_name', 'contact', 'email', 'address', 'dob', 'joining_date', 'blood_group', 'photo', 'status', 'roll_no'];

        foreach ($updateableFields as $field) {
            if (isset($data[$field])) {
                $dbField = $field;
                if ($field === 'parent_name') $dbField = 'parent_name';
                if ($field === 'joining_date') $dbField = 'joining_date';
                if ($field === 'blood_group') $dbField = 'blood_group';
                if ($field === 'roll_no') $dbField = 'roll_no';

                if (in_array($field, ['dob', 'joining_date'])) {
                    $fields[] = "$dbField = :$field";
                    $bindings[":$field"] = formatDateForDB($data[$field]);
                } else {
                    $fields[] = "$dbField = :$field";
                    $bindings[":$field"] = sanitizeInput($data[$field]);
                }
            }
        }

        if (empty($fields)) {
            sendResponse(false, 'No fields to update', null, ['fields' => 'Empty']);
        }

        // Begin transaction
        $db->beginTransaction();

        // Update student
        $query = "UPDATE students SET " . implode(', ', $fields) . " WHERE id = :id";
        $stmt = $db->prepare($query);
        $stmt->execute($bindings);

        // Update documents if provided
        if (isset($data['documents']) && is_array($data['documents'])) {
            // Delete existing documents
            $deleteStmt = $db->prepare("DELETE FROM student_documents WHERE student_id = :student_id");
            $deleteStmt->execute([':student_id' => $studentId]);

            // Insert new documents
            foreach ($data['documents'] as $doc) {
                if (isset($doc['file']) && !empty($doc['file'])) {
                    $docStmt = $db->prepare("
                        INSERT INTO student_documents (student_id, name, type, file_name, file_type, file_data)
                        VALUES (:student_id, :name, :type, :file_name, :file_type, :file_data)
                    ");

                    $docStmt->execute([
                        ':student_id' => $studentId,
                        ':name' => sanitizeInput($doc['name']),
                        ':type' => sanitizeInput($doc['type']),
                        ':file_name' => sanitizeInput($doc['fileName']),
                        ':file_type' => sanitizeInput($doc['fileType']),
                        ':file_data' => $doc['file']
                    ]);
                }
            }
        }

        // Commit transaction
        $db->commit();

        // Log activity
        logActivity($db, getCurrentUserId(), 'Updated student', 'Students', ['student_id' => $studentId]);

        sendResponse(true, 'Student updated successfully', ['id' => $studentId]);

    } catch (Exception $e) {
        // Rollback on error
        if ($db->inTransaction()) {
            $db->rollBack();
        }
        sendResponse(false, 'Error updating student', null, ['error' => $e->getMessage()]);
    }
}

/**
 * Handle DELETE requests - Delete student
 */
function handleDelete($db, $params) {
    try {
        // Check for single ID or bulk delete
        if (isset($params['ids']) && !empty($params['ids'])) {
            // Bulk delete
            $ids = explode(',', $params['ids']);
            $ids = array_map('sanitizeInput', $ids);

            $placeholders = implode(',', array_fill(0, count($ids), '?'));
            $stmt = $db->prepare("DELETE FROM students WHERE id IN ($placeholders)");
            $stmt->execute($ids);

            $deletedCount = $stmt->rowCount();

            // Log activity
            logActivity($db, getCurrentUserId(), 'Bulk deleted students', 'Students', ['count' => $deletedCount, 'ids' => $ids]);

            sendResponse(true, "$deletedCount student(s) deleted successfully", ['count' => $deletedCount]);

        } elseif (isset($params['id']) && !empty($params['id'])) {
            // Single delete
            $studentId = sanitizeInput($params['id']);

            $stmt = $db->prepare("DELETE FROM students WHERE id = :id");
            $stmt->execute([':id' => $studentId]);

            if ($stmt->rowCount() === 0) {
                sendResponse(false, 'Student not found', null, ['id' => 'Invalid']);
            }

            // Log activity
            logActivity($db, getCurrentUserId(), 'Deleted student', 'Students', ['student_id' => $studentId]);

            sendResponse(true, 'Student deleted successfully', ['id' => $studentId]);

        } else {
            sendResponse(false, 'Student ID is required', null, ['id' => 'Missing']);
        }

    } catch (Exception $e) {
        sendResponse(false, 'Error deleting student', null, ['error' => $e->getMessage()]);
    }
}
?>
