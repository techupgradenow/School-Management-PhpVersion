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
require_once __DIR__ . '/../helpers/TenantContext.php';

// Start session for permission checks
if (session_status() === PHP_SESSION_NONE) {
    session_start();
}

/**
 * Get current school ID from session/context
 * CRITICAL: All queries must filter by school_id for multi-tenant isolation
 * Optimized: Accepts optional $db parameter to reuse existing connection
 */
function getCurrentSchoolId($existingDb = null) {
    static $cachedSchoolId = null;

    // Return cached value if available
    if ($cachedSchoolId !== null) {
        return $cachedSchoolId;
    }

    // Try from session first (fastest)
    if (isset($_SESSION['school_id']) && !empty($_SESSION['school_id'])) {
        $cachedSchoolId = $_SESSION['school_id'];
        return $cachedSchoolId;
    }

    // Reuse existing connection or get new one
    $db = $existingDb ?? (function_exists('getDB') ? getDB() : null);

    if ($db) {
        // Try from TenantContext
        try {
            $tenant = TenantContext::getInstance($db);
            $schoolId = $tenant->getSchoolId();
            if ($schoolId) {
                $cachedSchoolId = $schoolId;
                return $cachedSchoolId;
            }
        } catch (Exception $e) {
            // Fall through to default
        }

        // Fallback: Get first school from database (for development)
        try {
            $stmt = $db->query("SELECT id FROM schools WHERE is_active = 1 LIMIT 1");
            $school = $stmt->fetch();
            if ($school) {
                $_SESSION['school_id'] = $school['id'];
                $cachedSchoolId = $school['id'];
                return $cachedSchoolId;
            }
        } catch (Exception $e) {
            // No school found
        }
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

            case 'attendance':
                getStudentAttendance($db, $params);
                break;

            case 'fees':
                getStudentFees($db, $params);
                break;

            case 'exams':
                getStudentExams($db, $params);
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
 * IMPORTANT: All queries filter by school_id for multi-tenant isolation
 */
function getStudentsList($db, $params) {
    $page = isset($params['page']) ? (int)$params['page'] : 1;
    $perPage = isset($params['perPage']) ? (int)$params['perPage'] : 10;
    $search = $params['search'] ?? '';
    $class = $params['class'] ?? '';
    $section = $params['section'] ?? '';
    $status = $params['status'] ?? '';
    $gender = $params['gender'] ?? '';

    // Get current school for multi-tenant filtering
    $schoolId = getCurrentSchoolId();

    // Build query
    $where = [];
    $bindings = [];

    // CRITICAL: Always filter by school_id for data isolation
    if ($schoolId) {
        $where[] = "school_id = :school_id";
        $bindings[':school_id'] = $schoolId;
    }

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

    // Batch fetch documents for all students (fixes N+1 query)
    if (!empty($students)) {
        $studentIds = array_column($students, 'id');
        $placeholders = implode(',', array_fill(0, count($studentIds), '?'));
        $docsStmt = $db->prepare("SELECT * FROM student_documents WHERE student_id IN ($placeholders)");
        $docsStmt->execute($studentIds);
        $allDocs = $docsStmt->fetchAll();

        // Group documents by student_id
        $docsByStudent = [];
        foreach ($allDocs as $doc) {
            $docsByStudent[$doc['student_id']][] = $doc;
        }

        // Assign documents to each student
        foreach ($students as &$student) {
            $student['documents'] = $docsByStudent[$student['id']] ?? [];
        }
        unset($student);
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
 * Helper function to execute a tenant-filtered query
 * Optimized: Reduces code duplication and ensures consistent filtering
 */
function executeStudentRelatedQuery($db, $sql, $studentId, $schoolId, $default = []) {
    try {
        $bindings = [':student_id' => $studentId];
        if ($schoolId) {
            $sql = str_replace('{SCHOOL_FILTER}', ' AND school_id = :school_id', $sql);
            $bindings[':school_id'] = $schoolId;
        } else {
            $sql = str_replace('{SCHOOL_FILTER}', '', $sql);
        }
        $stmt = $db->prepare($sql);
        $stmt->execute($bindings);
        return is_array($default) ? $stmt->fetchAll() : ($stmt->fetch() ?: $default);
    } catch (Exception $e) {
        return $default;
    }
}

/**
 * Get single student by ID with all related data
 * IMPORTANT: All queries filter by school_id for multi-tenant isolation
 * Optimized: Uses helper function to reduce code duplication
 */
function getSingleStudent($db, $params) {
    if (empty($params['id'])) {
        sendResponse(false, 'Student ID is required', null, ['id' => 'Missing']);
    }

    // Get current school for multi-tenant filtering (cached)
    $schoolId = getCurrentSchoolId($db);

    // Build query with school_id filter
    $sql = "SELECT * FROM students WHERE id = :id";
    $bindings = [':id' => $params['id']];

    if ($schoolId) {
        $sql .= " AND school_id = :school_id";
        $bindings[':school_id'] = $schoolId;
    }

    $stmt = $db->prepare($sql);
    $stmt->execute($bindings);
    $student = $stmt->fetch();

    if (!$student) {
        sendResponse(false, 'Student not found', null, ['id' => 'Invalid']);
    }

    $studentId = $student['id'];

    // Fetch all related data using optimized helper function
    $student['documents'] = executeStudentRelatedQuery($db,
        "SELECT * FROM student_documents WHERE student_id = :student_id{SCHOOL_FILTER}",
        $studentId, $schoolId, []);

    $student['parents'] = executeStudentRelatedQuery($db,
        "SELECT * FROM student_parents WHERE student_id = :student_id{SCHOOL_FILTER} ORDER BY is_primary_contact DESC",
        $studentId, $schoolId, []);

    $student['emergency_contacts'] = executeStudentRelatedQuery($db,
        "SELECT * FROM student_emergency_contacts WHERE student_id = :student_id{SCHOOL_FILTER} ORDER BY priority ASC",
        $studentId, $schoolId, []);

    $student['medical'] = executeStudentRelatedQuery($db,
        "SELECT * FROM student_medical WHERE student_id = :student_id{SCHOOL_FILTER}",
        $studentId, $schoolId, null);

    $student['siblings'] = executeStudentRelatedQuery($db,
        "SELECT * FROM student_siblings WHERE student_id = :student_id{SCHOOL_FILTER}",
        $studentId, $schoolId, []);

    $student['previous_school'] = executeStudentRelatedQuery($db,
        "SELECT * FROM student_previous_school WHERE student_id = :student_id{SCHOOL_FILTER}",
        $studentId, $schoolId, null);

    $student['fee_payments'] = executeStudentRelatedQuery($db,
        "SELECT * FROM fee_payments WHERE student_id = :student_id{SCHOOL_FILTER} ORDER BY payment_date DESC",
        $studentId, $schoolId, []);

    $student['attendance_summary'] = executeStudentRelatedQuery($db,
        "SELECT COUNT(*) as total_days,
                SUM(CASE WHEN status = 'Present' THEN 1 ELSE 0 END) as present_days,
                SUM(CASE WHEN status = 'Absent' THEN 1 ELSE 0 END) as absent_days,
                SUM(CASE WHEN status = 'Late' THEN 1 ELSE 0 END) as late_days
         FROM attendance WHERE student_id = :student_id{SCHOOL_FILTER}",
        $studentId, $schoolId, null);

    $student['exam_results'] = executeStudentRelatedQuery($db,
        "SELECT em.*, e.name as exam_name, e.type as exam_type, s.name as subject_name
         FROM exam_marks em
         LEFT JOIN exams e ON em.exam_id = e.id
         LEFT JOIN subjects s ON em.subject_id = s.id
         WHERE em.student_id = :student_id" . ($schoolId ? " AND em.school_id = :school_id" : "") . "
         ORDER BY e.date DESC",
        $studentId, $schoolId, []);

    sendResponse(true, 'Student fetched successfully', $student);
}

/**
 * Get students statistics
 * Optimized: Single query with conditional aggregation (was 5 separate queries)
 */
function getStudentsStats($db, $params) {
    $class = $params['class'] ?? '';
    $section = $params['section'] ?? '';
    $schoolId = getCurrentSchoolId();

    $where = [];
    $bindings = [];

    // CRITICAL: Always filter by school_id for data isolation
    if ($schoolId) {
        $where[] = "school_id = :school_id";
        $bindings[':school_id'] = $schoolId;
    }

    if (!empty($class)) {
        $where[] = "class = :class";
        $bindings[':class'] = $class;
    }

    if (!empty($section)) {
        $where[] = "section = :section";
        $bindings[':section'] = $section;
    }

    $whereClause = !empty($where) ? 'WHERE ' . implode(' AND ', $where) : '';

    // Single optimized query with conditional aggregation
    $query = "
        SELECT
            COUNT(*) as total,
            SUM(CASE WHEN status = 'Active' THEN 1 ELSE 0 END) as active,
            SUM(CASE WHEN gender = 'Male' THEN 1 ELSE 0 END) as male,
            SUM(CASE WHEN gender = 'Female' THEN 1 ELSE 0 END) as female,
            SUM(CASE WHEN DATE_FORMAT(joining_date, '%Y-%m') = DATE_FORMAT(CURDATE(), '%Y-%m') THEN 1 ELSE 0 END) as newThisMonth
        FROM students $whereClause
    ";

    $stmt = $db->prepare($query);
    $stmt->execute($bindings);
    $result = $stmt->fetch();

    $stats = [
        'total' => (int)$result['total'],
        'active' => (int)$result['active'],
        'male' => (int)$result['male'],
        'female' => (int)$result['female'],
        'newThisMonth' => (int)$result['newThisMonth']
    ];

    sendResponse(true, 'Statistics fetched successfully', $stats);
}

/**
 * Search students
 * IMPORTANT: Filters by school_id for multi-tenant isolation
 */
function searchStudents($db, $params) {
    $query = $params['q'] ?? '';
    $schoolId = getCurrentSchoolId();

    if (empty($query)) {
        sendResponse(false, 'Search query is required', null, ['query' => 'Missing']);
    }

    $sql = "
        SELECT id, name, class, section, contact, status
        FROM students
        WHERE (name LIKE :query OR id LIKE :query OR contact LIKE :query)
    ";
    $bindings = [':query' => "%$query%"];

    if ($schoolId) {
        $sql .= " AND school_id = :school_id";
        $bindings[':school_id'] = $schoolId;
    }

    $sql .= " LIMIT 20";

    $stmt = $db->prepare($sql);
    $stmt->execute($bindings);
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
            // Bulk delete - optimized with batch operations
            $ids = explode(',', $params['ids']);
            $ids = array_map('sanitizeInput', $ids);
            $schoolId = getCurrentSchoolId();

            // Batch fetch all students data for logging (single query)
            $placeholders = implode(',', array_fill(0, count($ids), '?'));
            $selectSql = "SELECT * FROM students WHERE id IN ($placeholders)";
            if ($schoolId) {
                $selectSql .= " AND school_id = ?";
                $selectParams = array_merge($ids, [$schoolId]);
            } else {
                $selectParams = $ids;
            }
            $stmt = $db->prepare($selectSql);
            $stmt->execute($selectParams);
            $students = $stmt->fetchAll(PDO::FETCH_ASSOC);

            if (empty($students)) {
                sendResponse(false, 'No students found', null, ['ids' => 'Invalid']);
            }

            // Get valid IDs that exist
            $validIds = array_column($students, 'id');
            $deletedCount = count($validIds);

            $db->beginTransaction();

            // Batch delete documents (single query)
            $docPlaceholders = implode(',', array_fill(0, count($validIds), '?'));
            $deleteDocsStmt = $db->prepare("DELETE FROM student_documents WHERE student_id IN ($docPlaceholders)");
            $deleteDocsStmt->execute($validIds);

            // Batch delete students (single query)
            $deleteStmt = $db->prepare("DELETE FROM students WHERE id IN ($docPlaceholders)");
            $deleteStmt->execute($validIds);

            // Log deletions (must be individual for proper history)
            foreach ($students as $oldStudent) {
                $activityLogger->logDelete('students', $oldStudent['id'], $oldStudent, $oldStudent['name']);
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
 * Get classes from classes table or distinct from students
 * IMPORTANT: Filters by school_id for multi-tenant isolation
 */
function getDistinctClasses($db) {
    try {
        $schoolId = getCurrentSchoolId();

        // First try classes table with school_id filter
        if ($schoolId) {
            $stmt = $db->prepare("SELECT id, name FROM classes WHERE school_id = :school_id AND name IS NOT NULL ORDER BY name");
            $stmt->execute([':school_id' => $schoolId]);
        } else {
            $stmt = $db->query("SELECT id, name FROM classes WHERE name IS NOT NULL ORDER BY name");
        }
        $classes = $stmt->fetchAll(PDO::FETCH_ASSOC);

        if (empty($classes)) {
            // Fallback to distinct classes from students with school_id filter
            if ($schoolId) {
                $stmt = $db->prepare("SELECT DISTINCT class as name FROM students WHERE school_id = :school_id AND class IS NOT NULL AND class != '' ORDER BY class");
                $stmt->execute([':school_id' => $schoolId]);
            } else {
                $stmt = $db->query("SELECT DISTINCT class as name FROM students WHERE class IS NOT NULL AND class != '' ORDER BY class");
            }
            $classes = $stmt->fetchAll(PDO::FETCH_ASSOC);
        }

        sendResponse(true, 'Classes fetched successfully', $classes);
    } catch (Exception $e) {
        sendResponse(false, 'Error fetching classes', null, ['error' => $e->getMessage()]);
    }
}

/**
 * Get sections from sections table or distinct from students
 * IMPORTANT: Filters by school_id for multi-tenant isolation
 */
function getDistinctSections($db) {
    try {
        $schoolId = getCurrentSchoolId();

        // First try sections table with school_id filter
        if ($schoolId) {
            $stmt = $db->prepare("SELECT id, name FROM sections WHERE school_id = :school_id AND name IS NOT NULL ORDER BY name");
            $stmt->execute([':school_id' => $schoolId]);
        } else {
            $stmt = $db->query("SELECT id, name FROM sections WHERE name IS NOT NULL ORDER BY name");
        }
        $sections = $stmt->fetchAll(PDO::FETCH_ASSOC);

        if (empty($sections)) {
            // Fallback to distinct sections from students with school_id filter
            if ($schoolId) {
                $stmt = $db->prepare("SELECT DISTINCT section as name FROM students WHERE school_id = :school_id AND section IS NOT NULL AND section != '' ORDER BY section");
                $stmt->execute([':school_id' => $schoolId]);
            } else {
                $stmt = $db->query("SELECT DISTINCT section as name FROM students WHERE section IS NOT NULL AND section != '' ORDER BY section");
            }
            $sections = $stmt->fetchAll(PDO::FETCH_ASSOC);
        }

        sendResponse(true, 'Sections fetched successfully', $sections);
    } catch (Exception $e) {
        sendResponse(false, 'Error fetching sections', null, ['error' => $e->getMessage()]);
    }
}

/**
 * Get attendance records for a student
 * IMPORTANT: Filters by school_id for multi-tenant isolation
 */
function getStudentAttendance($db, $params) {
    if (empty($params['student_id'])) {
        sendResponse(false, 'Student ID is required', null, ['student_id' => 'Missing']);
    }

    $studentId = $params['student_id'];
    $month = $params['month'] ?? date('m');
    $year = $params['year'] ?? date('Y');
    $schoolId = getCurrentSchoolId();

    try {
        $sql = "SELECT * FROM attendance WHERE student_id = :student_id AND MONTH(date) = :month AND YEAR(date) = :year";
        $bindings = [':student_id' => $studentId, ':month' => $month, ':year' => $year];

        if ($schoolId) {
            $sql .= " AND school_id = :school_id";
            $bindings[':school_id'] = $schoolId;
        }

        $sql .= " ORDER BY date ASC";

        $stmt = $db->prepare($sql);
        $stmt->execute($bindings);
        $attendance = $stmt->fetchAll();

        sendResponse(true, 'Attendance fetched successfully', $attendance);
    } catch (Exception $e) {
        sendResponse(false, 'Error fetching attendance', null, ['error' => $e->getMessage()]);
    }
}

/**
 * Get fee details for a student
 * IMPORTANT: Filters by school_id for multi-tenant isolation
 */
function getStudentFees($db, $params) {
    if (empty($params['student_id'])) {
        sendResponse(false, 'Student ID is required', null, ['student_id' => 'Missing']);
    }

    $studentId = $params['student_id'];
    $schoolId = getCurrentSchoolId();

    try {
        // Get student's class to determine fee structure (with school_id filter)
        $sql = "SELECT class, section FROM students WHERE id = :id";
        $bindings = [':id' => $studentId];
        if ($schoolId) {
            $sql .= " AND school_id = :school_id";
            $bindings[':school_id'] = $schoolId;
        }
        $stmt = $db->prepare($sql);
        $stmt->execute($bindings);
        $student = $stmt->fetch();

        if (!$student) {
            sendResponse(false, 'Student not found', null, ['student_id' => 'Invalid']);
        }

        // Get fee structure for the class (with school_id filter)
        $feeSql = "SELECT * FROM fee_structures WHERE class = :class AND status = 'Active'";
        $feeBindings = [':class' => $student['class']];
        if ($schoolId) {
            $feeSql .= " AND school_id = :school_id";
            $feeBindings[':school_id'] = $schoolId;
        }
        $feeSql .= " ORDER BY due_date";
        $feeStmt = $db->prepare($feeSql);
        $feeStmt->execute($feeBindings);
        $feeStructure = $feeStmt->fetchAll();

        // Get payments made by student (with school_id filter)
        $paymentSql = "SELECT * FROM fee_payments WHERE student_id = :student_id";
        $paymentBindings = [':student_id' => $studentId];
        if ($schoolId) {
            $paymentSql .= " AND school_id = :school_id";
            $paymentBindings[':school_id'] = $schoolId;
        }
        $paymentSql .= " ORDER BY payment_date DESC";
        $paymentStmt = $db->prepare($paymentSql);
        $paymentStmt->execute($paymentBindings);
        $payments = $paymentStmt->fetchAll();

        // Get any discounts applied (with school_id filter)
        $discountSql = "
            SELECT sfd.*, fd.name as discount_name, fd.discount_type, fd.discount_value
            FROM student_fee_discounts sfd
            JOIN fee_discounts fd ON sfd.discount_id = fd.id
            WHERE sfd.student_id = :student_id AND sfd.is_active = 1";
        $discountBindings = [':student_id' => $studentId];
        if ($schoolId) {
            $discountSql .= " AND sfd.school_id = :school_id";
            $discountBindings[':school_id'] = $schoolId;
        }
        $discountStmt = $db->prepare($discountSql);
        $discountStmt->execute($discountBindings);
        $discounts = $discountStmt->fetchAll();

        sendResponse(true, 'Fee details fetched successfully', [
            'fee_structure' => $feeStructure,
            'payments' => $payments,
            'discounts' => $discounts
        ]);
    } catch (Exception $e) {
        sendResponse(false, 'Error fetching fee details', null, ['error' => $e->getMessage()]);
    }
}

/**
 * Get exam results for a student
 * IMPORTANT: Filters by school_id for multi-tenant isolation
 */
function getStudentExams($db, $params) {
    if (empty($params['student_id'])) {
        sendResponse(false, 'Student ID is required', null, ['student_id' => 'Missing']);
    }

    $studentId = $params['student_id'];
    $schoolId = getCurrentSchoolId();

    try {
        $sql = "
            SELECT
                em.*,
                e.name as exam_name,
                e.type as exam_type,
                e.date as exam_date,
                e.total_marks as exam_total_marks,
                s.name as subject_name,
                s.code as subject_code
            FROM exam_marks em
            JOIN exams e ON em.exam_id = e.id
            LEFT JOIN subjects s ON em.subject_id = s.id
            WHERE em.student_id = :student_id";

        $bindings = [':student_id' => $studentId];

        if ($schoolId) {
            $sql .= " AND em.school_id = :school_id";
            $bindings[':school_id'] = $schoolId;
        }

        $sql .= " ORDER BY e.date DESC, s.name ASC";

        $stmt = $db->prepare($sql);
        $stmt->execute($bindings);
        $examResults = $stmt->fetchAll();

        // Group by exam
        $grouped = [];
        foreach ($examResults as $result) {
            $examId = $result['exam_id'];
            if (!isset($grouped[$examId])) {
                $grouped[$examId] = [
                    'exam_id' => $examId,
                    'exam_name' => $result['exam_name'],
                    'exam_type' => $result['exam_type'],
                    'exam_date' => $result['exam_date'],
                    'subjects' => []
                ];
            }
            $grouped[$examId]['subjects'][] = [
                'subject_name' => $result['subject_name'],
                'subject_code' => $result['subject_code'],
                'marks_obtained' => $result['marks_obtained'],
                'total_marks' => $result['total_marks'] ?? $result['exam_total_marks'],
                'grade' => $result['grade'] ?? null
            ];
        }

        sendResponse(true, 'Exam results fetched successfully', array_values($grouped));
    } catch (Exception $e) {
        sendResponse(false, 'Error fetching exam results', null, ['error' => $e->getMessage()]);
    }
}
?>
