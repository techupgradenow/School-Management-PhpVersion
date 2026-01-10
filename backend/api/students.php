<?php
/**
 * Students API Endpoint
 * EduManage Pro - School Management System
 *
 * Handles all CRUD operations for students with activity history tracking
 * Protected by Permission Guard - enforces strict RBAC
 */

require_once __DIR__ . '/../config/db.php';
require_once __DIR__ . '/../helpers/functions.php';
require_once __DIR__ . '/../helpers/ActivityLogger.php';
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
    $activityLogger = new ActivityLogger($db);
} catch (Exception $e) {
    sendResponse(false, 'Database connection failed', null, ['database' => $e->getMessage()]);
}

// ============================================
// PERMISSION ENFORCEMENT
// ============================================

// Require authentication for all requests
requireAuth();

// Require module access - user must have access to students module
requireModuleAccess('students');

// Route requests based on method and action
switch ($method) {
    case 'GET':
        // View permission required for GET requests
        requirePermission('students', 'view');
        handleGet($db, $_GET);
        break;

    case 'POST':
        // Create permission required for POST requests
        requirePermission('students', 'create');
        handlePost($db, $data, $activityLogger);
        break;

    case 'PUT':
        // Edit permission required for PUT requests
        requirePermission('students', 'edit');
        handlePut($db, $data, $activityLogger);
        break;

    case 'DELETE':
        // Delete permission required for DELETE requests
        requirePermission('students', 'delete');
        handleDelete($db, $_GET, $activityLogger);
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

            case 'history':
                getStudentHistory($db, $params);
                break;

            case 'classes':
                getDistinctClasses($db);
                break;

            case 'sections':
                getDistinctSections($db);
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

    // Active students
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
 * Get student activity history
 */
function getStudentHistory($db, $params) {
    global $activityLogger;

    if (empty($params['id'])) {
        sendResponse(false, 'Student ID is required', null, ['id' => 'Missing']);
    }

    $limit = isset($params['limit']) ? (int)$params['limit'] : 50;
    $history = $activityLogger->getEntityHistory('students', $params['id'], $limit);

    sendResponse(true, 'History fetched successfully', $history);
}

/**
 * Handle POST requests - Create student with history tracking
 */
function handlePost($db, $data, $activityLogger) {
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
        $rollNo = isset($data['roll_no']) ? sanitizeInput($data['roll_no']) : null;

        // Validate email if provided
        if ($email && !validateEmail($email)) {
            sendResponse(false, 'Invalid email format', null, ['email' => 'Invalid']);
        }

        // Generate student ID
        $studentId = generateId('STU', 10);

        // Generate admission number
        $admissionNo = generateAdmissionNo('ADM');

        // Prepare new student data for history
        $newStudentData = [
            'id' => $studentId,
            'name' => $name,
            'gender' => $gender,
            'class' => $class,
            'section' => $section,
            'parent_name' => $parentName,
            'contact' => $contact,
            'email' => $email,
            'address' => $address,
            'dob' => $dob,
            'joining_date' => $joiningDate,
            'blood_group' => $bloodGroup,
            'status' => $status,
            'admission_no' => $admissionNo,
            'roll_no' => $rollNo
        ];

        // Use transaction with history logging
        $result = $activityLogger->logWithTransaction(
            'students',
            $studentId,
            'ADD',
            null,
            $newStudentData,
            function($pdo) use ($studentId, $name, $gender, $class, $section, $parentName, $contact, $email, $address, $dob, $joiningDate, $bloodGroup, $photo, $status, $admissionNo, $rollNo, $data) {
                // Insert student
                $stmt = $pdo->prepare("
                    INSERT INTO students (id, name, gender, class, section, parent_name, contact, email, address, dob, joining_date, blood_group, photo, status, admission_no, roll_no)
                    VALUES (:id, :name, :gender, :class, :section, :parent_name, :contact, :email, :address, :dob, :joining_date, :blood_group, :photo, :status, :admission_no, :roll_no)
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
                    ':admission_no' => $admissionNo,
                    ':roll_no' => $rollNo
                ]);

                // Insert documents if provided
                if (isset($data['documents']) && is_array($data['documents'])) {
                    foreach ($data['documents'] as $doc) {
                        $docStmt = $pdo->prepare("
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

                return ['id' => $studentId, 'admission_no' => $admissionNo];
            },
            "Added new student: {$name}",
            $name
        );

        if ($result['success']) {
            sendResponse(true, 'Student created successfully', $result['data']);
        } else {
            sendResponse(false, 'Error creating student', null, ['error' => $result['error']]);
        }

    } catch (Exception $e) {
        sendResponse(false, 'Error creating student', null, ['error' => $e->getMessage()]);
    }
}

/**
 * Handle PUT requests - Update student with history tracking
 */
function handlePut($db, $data, $activityLogger) {
    try {
        // Validate ID
        if (empty($data['id'])) {
            sendResponse(false, 'Student ID is required', null, ['id' => 'Missing']);
        }

        $studentId = sanitizeInput($data['id']);

        // Get existing student data (for history comparison)
        $stmt = $db->prepare("SELECT * FROM students WHERE id = :id");
        $stmt->execute([':id' => $studentId]);
        $oldStudent = $stmt->fetch(PDO::FETCH_ASSOC);

        if (!$oldStudent) {
            sendResponse(false, 'Student not found', null, ['id' => 'Invalid']);
        }

        // Build update query
        $fields = [];
        $bindings = [':id' => $studentId];
        $newStudentData = $oldStudent; // Start with old data

        $updateableFields = ['name', 'gender', 'class', 'section', 'parent_name', 'contact', 'email', 'address', 'dob', 'joining_date', 'blood_group', 'photo', 'status', 'roll_no'];

        foreach ($updateableFields as $field) {
            if (isset($data[$field])) {
                $dbField = $field;

                if (in_array($field, ['dob', 'joining_date'])) {
                    $fields[] = "$dbField = :$field";
                    $value = formatDateForDB($data[$field]);
                    $bindings[":$field"] = $value;
                    $newStudentData[$field] = $value;
                } else {
                    $fields[] = "$dbField = :$field";
                    $value = sanitizeInput($data[$field]);
                    $bindings[":$field"] = $value;
                    $newStudentData[$field] = $value;
                }
            }
        }

        if (empty($fields)) {
            sendResponse(false, 'No fields to update', null, ['fields' => 'Empty']);
        }

        // Use transaction with history logging
        $result = $activityLogger->logWithTransaction(
            'students',
            $studentId,
            'UPDATE',
            $oldStudent,
            $newStudentData,
            function($pdo) use ($fields, $bindings, $studentId, $data) {
                // Update student
                $query = "UPDATE students SET " . implode(', ', $fields) . " WHERE id = :id";
                $stmt = $pdo->prepare($query);
                $stmt->execute($bindings);

                // Update documents if provided
                if (isset($data['documents']) && is_array($data['documents'])) {
                    // Delete existing documents
                    $deleteStmt = $pdo->prepare("DELETE FROM student_documents WHERE student_id = :student_id");
                    $deleteStmt->execute([':student_id' => $studentId]);

                    // Insert new documents
                    foreach ($data['documents'] as $doc) {
                        if (isset($doc['file']) && !empty($doc['file'])) {
                            $docStmt = $pdo->prepare("
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

                return ['id' => $studentId];
            },
            "Updated student: {$oldStudent['name']}",
            $oldStudent['name']
        );

        if ($result['success']) {
            sendResponse(true, 'Student updated successfully', $result['data']);
        } else {
            sendResponse(false, 'Error updating student', null, ['error' => $result['error']]);
        }

    } catch (Exception $e) {
        sendResponse(false, 'Error updating student', null, ['error' => $e->getMessage()]);
    }
}

/**
 * Handle DELETE requests - Delete student with history tracking (soft delete preferred)
 */
function handleDelete($db, $params, $activityLogger) {
    try {
        // Check for single ID or bulk delete
        if (isset($params['ids']) && !empty($params['ids'])) {
            // Bulk delete
            $ids = explode(',', $params['ids']);
            $ids = array_map('sanitizeInput', $ids);
            $deletedCount = 0;

            $db->beginTransaction();

            foreach ($ids as $studentId) {
                // Get student data before deletion
                $stmt = $db->prepare("SELECT * FROM students WHERE id = :id");
                $stmt->execute([':id' => $studentId]);
                $oldStudent = $stmt->fetch(PDO::FETCH_ASSOC);

                if ($oldStudent) {
                    // Delete student
                    $deleteStmt = $db->prepare("DELETE FROM students WHERE id = :id");
                    $deleteStmt->execute([':id' => $studentId]);

                    // Delete related documents
                    $deleteDocsStmt = $db->prepare("DELETE FROM student_documents WHERE student_id = :student_id");
                    $deleteDocsStmt->execute([':student_id' => $studentId]);

                    // Log deletion
                    $activityLogger->logDelete('students', $studentId, $oldStudent, $oldStudent['name']);

                    $deletedCount++;
                }
            }

            $db->commit();

            sendResponse(true, "$deletedCount student(s) deleted successfully", ['count' => $deletedCount]);

        } elseif (isset($params['id']) && !empty($params['id'])) {
            // Single delete
            $studentId = sanitizeInput($params['id']);

            // Get student data before deletion
            $stmt = $db->prepare("SELECT * FROM students WHERE id = :id");
            $stmt->execute([':id' => $studentId]);
            $oldStudent = $stmt->fetch(PDO::FETCH_ASSOC);

            if (!$oldStudent) {
                sendResponse(false, 'Student not found', null, ['id' => 'Invalid']);
            }

            // Use transaction with history logging
            $result = $activityLogger->logWithTransaction(
                'students',
                $studentId,
                'DELETE',
                $oldStudent,
                null,
                function($pdo) use ($studentId) {
                    // Delete student documents first
                    $deleteDocsStmt = $pdo->prepare("DELETE FROM student_documents WHERE student_id = :student_id");
                    $deleteDocsStmt->execute([':student_id' => $studentId]);

                    // Delete student
                    $stmt = $pdo->prepare("DELETE FROM students WHERE id = :id");
                    $stmt->execute([':id' => $studentId]);

                    return $stmt->rowCount() > 0;
                },
                "Deleted student: {$oldStudent['name']}",
                $oldStudent['name']
            );

            if ($result['success']) {
                sendResponse(true, 'Student deleted successfully', ['id' => $studentId]);
            } else {
                sendResponse(false, 'Error deleting student', null, ['error' => $result['error']]);
            }

        } else {
            sendResponse(false, 'Student ID is required', null, ['id' => 'Missing']);
        }

    } catch (Exception $e) {
        if ($db->inTransaction()) {
            $db->rollBack();
        }
        sendResponse(false, 'Error deleting student', null, ['error' => $e->getMessage()]);
    }
}

/**
 * Get distinct classes from students table
 */
function getDistinctClasses($db) {
    try {
        $stmt = $db->query("SELECT DISTINCT class FROM students WHERE class IS NOT NULL AND class != '' ORDER BY class");
        $classes = $stmt->fetchAll(PDO::FETCH_COLUMN);
        sendResponse(true, 'Classes fetched successfully', $classes);
    } catch (Exception $e) {
        sendResponse(false, 'Error fetching classes', null, ['error' => $e->getMessage()]);
    }
}

/**
 * Get distinct sections from students table
 */
function getDistinctSections($db) {
    try {
        $stmt = $db->query("SELECT DISTINCT section FROM students WHERE section IS NOT NULL AND section != '' ORDER BY section");
        $sections = $stmt->fetchAll(PDO::FETCH_COLUMN);
        sendResponse(true, 'Sections fetched successfully', $sections);
    } catch (Exception $e) {
        sendResponse(false, 'Error fetching sections', null, ['error' => $e->getMessage()]);
    }
}
?>
