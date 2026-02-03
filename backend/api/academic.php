<?php
/**
 * Academic Settings API Endpoint
 * EduManage Pro - School Management System
 *
 * Handles Classes, Sections, Subjects CRUD operations
 * with dependent dropdown support and multi-tenant isolation
 *
 * Endpoints:
 * GET    ?action=classes              - List all classes with sections
 * GET    ?action=classes&id=5         - Get single class details
 * GET    ?action=sections&class_id=5  - Get sections by class (DEPENDENT)
 * GET    ?action=subjects             - List all subjects
 * GET    ?action=subjects&class_id=5  - Get subjects by class (DEPENDENT)
 * POST   action=add_class             - Create class with initial sections
 * POST   action=add_section           - Create section for class
 * POST   action=add_subject           - Create subject
 * POST   action=assign_subjects       - Assign subjects to class
 * PUT    action=update_class          - Update class
 * PUT    action=update_section        - Update section
 * PUT    action=update_subject        - Update subject
 * DELETE ?action=delete_class&id=5    - Delete class (cascades sections)
 * DELETE ?action=delete_section&id=10 - Delete section
 * DELETE ?action=delete_subject&id=15 - Delete subject
 */

// CORS Headers
header('Access-Control-Allow-Origin: *');
header('Access-Control-Allow-Methods: GET, POST, PUT, DELETE, OPTIONS');
header('Access-Control-Allow-Headers: Content-Type, Authorization');
header('Content-Type: application/json; charset=UTF-8');

// Handle preflight
if ($_SERVER['REQUEST_METHOD'] === 'OPTIONS') {
    http_response_code(200);
    exit;
}

require_once __DIR__ . '/../config/db.php';
require_once __DIR__ . '/../config/env.php';

// Include permission guard if available
if (file_exists(__DIR__ . '/../helpers/permission_guard.php')) {
    require_once __DIR__ . '/../helpers/permission_guard.php';
}

// Start session
if (session_status() === PHP_SESSION_NONE) {
    session_start();
}

/**
 * Send JSON response
 */
function sendResponse($success, $message, $data = null, $errors = null) {
    $response = [
        'success' => $success,
        'message' => $message
    ];
    if ($data !== null) {
        $response['data'] = $data;
    }
    if ($errors !== null) {
        $response['errors'] = $errors;
    }
    echo json_encode($response);
    exit;
}

/**
 * Get current school ID for multi-tenant filtering
 */
function getCurrentSchoolId() {
    return $_SESSION['school_id'] ?? null;
}

/**
 * Generate UUID
 */
function generateUUID() {
    return sprintf('%04x%04x-%04x-%04x-%04x-%04x%04x%04x',
        mt_rand(0, 0xffff), mt_rand(0, 0xffff),
        mt_rand(0, 0xffff),
        mt_rand(0, 0x0fff) | 0x4000,
        mt_rand(0, 0x3fff) | 0x8000,
        mt_rand(0, 0xffff), mt_rand(0, 0xffff), mt_rand(0, 0xffff)
    );
}

// Get database connection
try {
    $db = getDB();
} catch (Exception $e) {
    sendResponse(false, 'Database connection failed', null, ['database' => $e->getMessage()]);
}

// Get request method and data
$method = $_SERVER['REQUEST_METHOD'];
$input = file_get_contents('php://input');
$data = json_decode($input, true) ?? [];

// Merge GET and POST data
$requestData = array_merge($_GET, $data);
$action = $requestData['action'] ?? '';

// Route request
switch ($method) {
    case 'GET':
        handleGet($db, $requestData);
        break;
    case 'POST':
        handlePost($db, $requestData);
        break;
    case 'PUT':
        handlePut($db, $requestData);
        break;
    case 'DELETE':
        handleDelete($db, $requestData);
        break;
    default:
        sendResponse(false, 'Method not allowed');
}

// ============================================================================
// GET HANDLERS
// ============================================================================

function handleGet($db, $params) {
    $action = $params['action'] ?? 'classes';
    $schoolId = getCurrentSchoolId();

    switch ($action) {
        case 'classes':
            getClasses($db, $params, $schoolId);
            break;
        case 'sections':
            getSections($db, $params, $schoolId);
            break;
        case 'subjects':
            getSubjects($db, $params, $schoolId);
            break;
        case 'class_subjects':
            getClassSubjects($db, $params, $schoolId);
            break;
        default:
            sendResponse(false, 'Invalid action');
    }
}

/**
 * Get all classes with optional sections and student counts
 * OPTIMIZED: Uses single query with JOINs instead of N+1 queries
 */
function getClasses($db, $params, $schoolId) {
    $withSections = isset($params['with_sections']) && $params['with_sections'] === 'true';
    $id = $params['id'] ?? null;
    $status = $params['status'] ?? 'Active';

    try {
        // Check if table exists first (cached check)
        static $tableExists = null;
        if ($tableExists === null) {
            $tableCheck = $db->query("SHOW TABLES LIKE 'academic_classes'");
            $tableExists = $tableCheck->rowCount() > 0;
        }

        if (!$tableExists) {
            sendResponse(false, 'Academic tables not found. Please run the migration first.', null, [
                'error' => 'Table academic_classes does not exist',
                'solution' => 'Run: php database/run_academic_migration.php'
            ]);
        }

        // OPTIMIZED: Single query to get classes with section counts and student totals
        $sql = "SELECT
                    c.id,
                    c.uuid,
                    c.class_name,
                    c.numeric_value,
                    c.description,
                    c.status,
                    c.display_order,
                    c.school_id,
                    c.created_at,
                    c.updated_at,
                    COALESCE(section_stats.section_count, 0) AS section_count,
                    COALESCE(section_stats.total_students, 0) AS total_students
                FROM academic_classes c
                LEFT JOIN (
                    SELECT
                        s.class_id,
                        COUNT(DISTINCT s.id) AS section_count,
                        COALESCE(SUM(student_counts.cnt), 0) AS total_students
                    FROM academic_sections s
                    LEFT JOIN (
                        SELECT class, section, COUNT(*) AS cnt
                        FROM students
                        WHERE 1=1" . ($schoolId ? " AND (school_id = ? OR school_id IS NULL)" : "") . "
                        GROUP BY class, section
                    ) student_counts ON student_counts.class = (
                        SELECT class_name FROM academic_classes WHERE id = s.class_id
                    ) AND student_counts.section = s.section_name
                    WHERE s.status = 'Active'
                    GROUP BY s.class_id
                ) section_stats ON section_stats.class_id = c.id
                WHERE 1=1";

        $bindParams = $schoolId ? [$schoolId] : [];

        // Filter by ID if provided
        if ($id) {
            $sql .= " AND c.id = ?";
            $bindParams[] = $id;
        }

        // Filter by status
        if ($status && $status !== 'all') {
            $sql .= " AND c.status = ?";
            $bindParams[] = $status;
        }

        // Filter by school_id for multi-tenant
        if ($schoolId) {
            $sql .= " AND (c.school_id = ? OR c.school_id IS NULL)";
            $bindParams[] = $schoolId;
        }

        $sql .= " ORDER BY c.numeric_value ASC, c.display_order ASC";

        $stmt = $db->prepare($sql);
        $stmt->execute($bindParams);
        $classes = $stmt->fetchAll(PDO::FETCH_ASSOC);

        // OPTIMIZED: Fetch all sections in ONE query if needed
        if ($withSections || $id) {
            $classIds = array_column($classes, 'id');

            if (!empty($classIds)) {
                // Build class name mapping for student counts
                $classNameMap = [];
                foreach ($classes as $cls) {
                    $classNameMap[$cls['id']] = $cls['class_name'];
                }

                $placeholders = implode(',', array_fill(0, count($classIds), '?'));
                $sectionsSql = "
                    SELECT
                        s.id,
                        s.uuid,
                        s.class_id,
                        s.section_name,
                        s.capacity,
                        s.class_teacher_id,
                        t.name AS class_teacher_name,
                        s.room_number,
                        s.status,
                        s.display_order
                    FROM academic_sections s
                    LEFT JOIN teachers t ON t.id = s.class_teacher_id
                    WHERE s.class_id IN ($placeholders) AND s.status = 'Active'
                    ORDER BY s.class_id, s.display_order ASC, s.section_name ASC
                ";

                $sectionsStmt = $db->prepare($sectionsSql);
                $sectionsStmt->execute($classIds);
                $allSections = $sectionsStmt->fetchAll(PDO::FETCH_ASSOC);

                // Get student counts per class/section in ONE query
                $studentCountsSql = "
                    SELECT class, section, COUNT(*) AS student_count
                    FROM students
                    WHERE class IN (SELECT class_name FROM academic_classes WHERE id IN ($placeholders))
                    " . ($schoolId ? "AND (school_id = ? OR school_id IS NULL)" : "") . "
                    GROUP BY class, section
                ";
                $studentParams = $classIds;
                if ($schoolId) $studentParams[] = $schoolId;

                $studentStmt = $db->prepare($studentCountsSql);
                $studentStmt->execute($studentParams);
                $studentCounts = [];
                while ($row = $studentStmt->fetch()) {
                    $studentCounts[$row['class'] . '_' . $row['section']] = (int)$row['student_count'];
                }

                // Group sections by class_id and add student counts
                $sectionsByClass = [];
                foreach ($allSections as $section) {
                    $className = $classNameMap[$section['class_id']] ?? '';
                    $key = $className . '_' . $section['section_name'];
                    $section['student_count'] = $studentCounts[$key] ?? 0;
                    $sectionsByClass[$section['class_id']][] = $section;
                }

                // Attach sections to classes
                foreach ($classes as &$class) {
                    $class['sections'] = $sectionsByClass[$class['id']] ?? [];
                }
                unset($class);
            }
        }

        // If single class requested
        if ($id) {
            if (empty($classes)) {
                sendResponse(false, 'Class not found');
            }
            sendResponse(true, 'Class fetched successfully', ['class' => $classes[0]]);
        }

        // Debug: Add count info
        $debugInfo = null;
        if (isset($_GET['debug']) && $_GET['debug'] === '1') {
            $countStmt = $db->query("SELECT COUNT(*) as cnt FROM academic_classes");
            $totalInDb = $countStmt->fetch()['cnt'];
            $debugInfo = [
                'school_id_filter' => $schoolId,
                'total_in_database' => $totalInDb,
                'status_filter' => $status
            ];
        }

        $response = [
            'classes' => $classes,
            'total' => count($classes)
        ];

        if ($debugInfo) {
            $response['debug'] = $debugInfo;
        }

        sendResponse(true, 'Classes fetched successfully', $response);

    } catch (PDOException $e) {
        sendResponse(false, 'Failed to fetch classes', null, ['database' => $e->getMessage()]);
    }
}

/**
 * Get sections by class (DEPENDENT DROPDOWN)
 */
function getSections($db, $params, $schoolId) {
    $classId = $params['class_id'] ?? null;

    if (!$classId) {
        sendResponse(false, 'class_id is required');
    }

    try {
        // Get class info
        $classStmt = $db->prepare("SELECT id, class_name FROM academic_classes WHERE id = ?");
        $classStmt->execute([$classId]);
        $classInfo = $classStmt->fetch(PDO::FETCH_ASSOC);

        if (!$classInfo) {
            sendResponse(false, 'Class not found');
        }

        // Get sections with student count
        $sql = "SELECT
                    s.id,
                    s.uuid,
                    s.section_name,
                    s.capacity,
                    s.class_teacher_id,
                    t.name AS class_teacher_name,
                    s.room_number,
                    s.status,
                    s.display_order,
                    (SELECT COUNT(*) FROM students st
                     WHERE st.class = ? AND st.section = s.section_name
                     AND (st.school_id = ? OR ? IS NULL OR st.school_id IS NULL)) AS student_count
                FROM academic_sections s
                LEFT JOIN teachers t ON t.id = s.class_teacher_id
                WHERE s.class_id = ? AND s.status = 'Active'";

        $bindParams = [$classInfo['class_name'], $schoolId, $schoolId, $classId];

        if ($schoolId) {
            $sql .= " AND (s.school_id = ? OR s.school_id IS NULL)";
            $bindParams[] = $schoolId;
        }

        $sql .= " ORDER BY s.display_order ASC, s.section_name ASC";

        $stmt = $db->prepare($sql);
        $stmt->execute($bindParams);
        $sections = $stmt->fetchAll(PDO::FETCH_ASSOC);

        sendResponse(true, 'Sections fetched successfully', [
            'class_id' => (int)$classId,
            'class_name' => $classInfo['class_name'],
            'sections' => $sections,
            'total' => count($sections)
        ]);

    } catch (PDOException $e) {
        sendResponse(false, 'Failed to fetch sections', null, ['database' => $e->getMessage()]);
    }
}

/**
 * Get all subjects, optionally filtered by class (DEPENDENT DROPDOWN)
 */
function getSubjects($db, $params, $schoolId) {
    $classId = $params['class_id'] ?? null;
    $status = $params['status'] ?? 'Active';
    $type = $params['type'] ?? null;

    try {
        if ($classId) {
            // Get subjects assigned to specific class
            $sql = "SELECT
                        sub.id,
                        sub.uuid,
                        sub.subject_code,
                        sub.subject_name,
                        sub.short_name,
                        sub.subject_type,
                        sub.max_marks,
                        sub.pass_marks,
                        sub.color_code,
                        sub.status,
                        cs.is_mandatory,
                        cs.weekly_periods,
                        cs.assigned_teacher_id,
                        t.name AS assigned_teacher_name
                    FROM academic_subjects sub
                    INNER JOIN academic_class_subjects cs ON cs.subject_id = sub.id
                    LEFT JOIN teachers t ON t.id = cs.assigned_teacher_id
                    WHERE cs.class_id = ? AND sub.status = 'Active'";

            $bindParams = [$classId];

            if ($schoolId) {
                $sql .= " AND (sub.school_id = ? OR sub.school_id IS NULL)";
                $bindParams[] = $schoolId;
            }

            $sql .= " ORDER BY sub.display_order ASC, sub.subject_name ASC";

            $stmt = $db->prepare($sql);
            $stmt->execute($bindParams);
            $subjects = $stmt->fetchAll(PDO::FETCH_ASSOC);

        } else {
            // Get all subjects with their assigned classes
            $sql = "SELECT
                        sub.id,
                        sub.uuid,
                        sub.subject_code,
                        sub.subject_name,
                        sub.short_name,
                        sub.subject_type,
                        sub.description,
                        sub.max_marks,
                        sub.pass_marks,
                        sub.color_code,
                        sub.status,
                        sub.display_order,
                        sub.created_at
                    FROM academic_subjects sub
                    WHERE 1=1";

            $bindParams = [];

            if ($status && $status !== 'all') {
                $sql .= " AND sub.status = ?";
                $bindParams[] = $status;
            }

            if ($type) {
                $sql .= " AND sub.subject_type = ?";
                $bindParams[] = $type;
            }

            if ($schoolId) {
                $sql .= " AND (sub.school_id = ? OR sub.school_id IS NULL)";
                $bindParams[] = $schoolId;
            }

            $sql .= " ORDER BY sub.display_order ASC, sub.subject_name ASC";

            $stmt = $db->prepare($sql);
            $stmt->execute($bindParams);
            $subjects = $stmt->fetchAll(PDO::FETCH_ASSOC);

            // Get assigned classes for each subject
            foreach ($subjects as &$subject) {
                $classesStmt = $db->prepare("
                    SELECT c.id, c.class_name
                    FROM academic_class_subjects cs
                    INNER JOIN academic_classes c ON c.id = cs.class_id
                    WHERE cs.subject_id = ?
                    ORDER BY c.numeric_value
                ");
                $classesStmt->execute([$subject['id']]);
                $assignedClasses = $classesStmt->fetchAll(PDO::FETCH_ASSOC);

                $subject['assigned_classes'] = array_column($assignedClasses, 'class_name');
                $subject['class_ids'] = array_column($assignedClasses, 'id');
            }
        }

        sendResponse(true, 'Subjects fetched successfully', [
            'subjects' => $subjects,
            'total' => count($subjects),
            'class_id' => $classId ? (int)$classId : null
        ]);

    } catch (PDOException $e) {
        sendResponse(false, 'Failed to fetch subjects', null, ['database' => $e->getMessage()]);
    }
}

/**
 * Get class-subject mappings
 */
function getClassSubjects($db, $params, $schoolId) {
    $classId = $params['class_id'] ?? null;

    if (!$classId) {
        sendResponse(false, 'class_id is required');
    }

    try {
        $stmt = $db->prepare("
            SELECT
                cs.id,
                cs.class_id,
                cs.subject_id,
                sub.subject_code,
                sub.subject_name,
                sub.subject_type,
                cs.is_mandatory,
                cs.weekly_periods,
                cs.assigned_teacher_id,
                t.name AS teacher_name
            FROM academic_class_subjects cs
            INNER JOIN academic_subjects sub ON sub.id = cs.subject_id
            LEFT JOIN teachers t ON t.id = cs.assigned_teacher_id
            WHERE cs.class_id = ?
            ORDER BY sub.display_order, sub.subject_name
        ");
        $stmt->execute([$classId]);
        $mappings = $stmt->fetchAll(PDO::FETCH_ASSOC);

        sendResponse(true, 'Class subjects fetched successfully', [
            'class_id' => (int)$classId,
            'subjects' => $mappings
        ]);

    } catch (PDOException $e) {
        sendResponse(false, 'Failed to fetch class subjects', null, ['database' => $e->getMessage()]);
    }
}

// ============================================================================
// POST HANDLERS
// ============================================================================

function handlePost($db, $data) {
    $action = $data['action'] ?? '';
    $schoolId = getCurrentSchoolId();

    switch ($action) {
        case 'add_class':
            addClass($db, $data, $schoolId);
            break;
        case 'add_section':
            addSection($db, $data, $schoolId);
            break;
        case 'add_subject':
            addSubject($db, $data, $schoolId);
            break;
        case 'assign_subjects':
            assignSubjects($db, $data, $schoolId);
            break;
        default:
            sendResponse(false, 'Invalid action');
    }
}

/**
 * Add new class with optional initial sections
 */
function addClass($db, $data, $schoolId) {
    $className = trim($data['class_name'] ?? '');
    $numericValue = $data['numeric_value'] ?? 0;
    $description = $data['description'] ?? null;
    $status = $data['status'] ?? 'Active';

    // Handle sections - accept both 'sections' (array) and 'initial_sections' (comma-separated string)
    $initialSections = $data['sections'] ?? $data['initial_sections'] ?? ['A', 'B'];

    // If sections is a comma-separated string, convert to array
    if (is_string($initialSections)) {
        $initialSections = array_filter(array_map('trim', explode(',', $initialSections)));
    }

    // Default to A, B if empty
    if (empty($initialSections)) {
        $initialSections = ['A', 'B'];
    }

    if (empty($className)) {
        sendResponse(false, 'Class name is required');
    }

    try {
        $db->beginTransaction();

        // Check if class already exists
        $checkStmt = $db->prepare("
            SELECT id FROM academic_classes
            WHERE class_name = ? AND (school_id = ? OR (school_id IS NULL AND ? IS NULL))
        ");
        $checkStmt->execute([$className, $schoolId, $schoolId]);
        if ($checkStmt->fetch()) {
            $db->rollBack();
            sendResponse(false, 'Class already exists');
        }

        // Get next display order
        $orderStmt = $db->prepare("SELECT MAX(display_order) FROM academic_classes WHERE school_id = ? OR school_id IS NULL");
        $orderStmt->execute([$schoolId]);
        $maxOrder = $orderStmt->fetchColumn() ?? 0;

        // Insert class
        $uuid = generateUUID();
        $stmt = $db->prepare("
            INSERT INTO academic_classes
            (uuid, class_name, numeric_value, description, status, display_order, school_id)
            VALUES (?, ?, ?, ?, ?, ?, ?)
        ");
        $stmt->execute([$uuid, $className, $numericValue, $description, $status, $maxOrder + 1, $schoolId]);
        $classId = $db->lastInsertId();

        // Add initial sections
        $sectionsCreated = [];
        if (is_array($initialSections) && !empty($initialSections)) {
            $sectionStmt = $db->prepare("
                INSERT INTO academic_sections (uuid, class_id, section_name, capacity, display_order, school_id)
                VALUES (?, ?, ?, 40, ?, ?)
            ");

            $order = 1;
            foreach ($initialSections as $sectionName) {
                $sectionName = trim($sectionName);
                if (!empty($sectionName)) {
                    $sectionUuid = generateUUID();
                    $sectionStmt->execute([$sectionUuid, $classId, $sectionName, $order, $schoolId]);
                    $sectionsCreated[] = [
                        'id' => $db->lastInsertId(),
                        'section_name' => $sectionName
                    ];
                    $order++;
                }
            }
        }

        $db->commit();

        sendResponse(true, 'Class created successfully', [
            'class' => [
                'id' => (int)$classId,
                'uuid' => $uuid,
                'class_name' => $className,
                'numeric_value' => (int)$numericValue,
                'status' => $status,
                'sections' => $sectionsCreated
            ]
        ]);

    } catch (PDOException $e) {
        $db->rollBack();
        sendResponse(false, 'Failed to create class', null, ['database' => $e->getMessage()]);
    }
}

/**
 * Add section to a class
 */
function addSection($db, $data, $schoolId) {
    $classId = $data['class_id'] ?? null;
    $sectionName = trim($data['section_name'] ?? '');
    $capacity = $data['capacity'] ?? 40;
    $classTeacherId = $data['class_teacher_id'] ?? null;
    $roomNumber = $data['room_number'] ?? null;

    if (!$classId) {
        sendResponse(false, 'class_id is required');
    }
    if (empty($sectionName)) {
        sendResponse(false, 'Section name is required');
    }

    try {
        // Check if class exists
        $classStmt = $db->prepare("SELECT id, school_id FROM academic_classes WHERE id = ?");
        $classStmt->execute([$classId]);
        $classInfo = $classStmt->fetch(PDO::FETCH_ASSOC);

        if (!$classInfo) {
            sendResponse(false, 'Class not found');
        }

        // Check if section already exists
        $checkStmt = $db->prepare("
            SELECT id FROM academic_sections WHERE class_id = ? AND section_name = ?
        ");
        $checkStmt->execute([$classId, $sectionName]);
        if ($checkStmt->fetch()) {
            sendResponse(false, 'Section already exists for this class');
        }

        // Get next display order
        $orderStmt = $db->prepare("SELECT MAX(display_order) FROM academic_sections WHERE class_id = ?");
        $orderStmt->execute([$classId]);
        $maxOrder = $orderStmt->fetchColumn() ?? 0;

        // Insert section
        $uuid = generateUUID();
        $stmt = $db->prepare("
            INSERT INTO academic_sections
            (uuid, class_id, section_name, capacity, class_teacher_id, room_number, display_order, school_id)
            VALUES (?, ?, ?, ?, ?, ?, ?, ?)
        ");
        $stmt->execute([
            $uuid, $classId, $sectionName, $capacity,
            $classTeacherId ?: null, $roomNumber, $maxOrder + 1,
            $classInfo['school_id']
        ]);
        $sectionId = $db->lastInsertId();

        sendResponse(true, 'Section created successfully', [
            'section' => [
                'id' => (int)$sectionId,
                'uuid' => $uuid,
                'class_id' => (int)$classId,
                'section_name' => $sectionName,
                'capacity' => (int)$capacity,
                'class_teacher_id' => $classTeacherId,
                'room_number' => $roomNumber
            ]
        ]);

    } catch (PDOException $e) {
        sendResponse(false, 'Failed to create section', null, ['database' => $e->getMessage()]);
    }
}

/**
 * Add new subject
 */
function addSubject($db, $data, $schoolId) {
    $subjectCode = strtoupper(trim($data['subject_code'] ?? ''));
    $subjectName = trim($data['subject_name'] ?? '');
    $shortName = trim($data['short_name'] ?? '');
    $subjectType = $data['subject_type'] ?? 'Theory';
    $description = $data['description'] ?? null;
    $maxMarks = $data['max_marks'] ?? 100;
    $passMarks = $data['pass_marks'] ?? 33;
    $colorCode = $data['color_code'] ?? null;
    $assignToClasses = $data['class_ids'] ?? [];

    if (empty($subjectCode)) {
        sendResponse(false, 'Subject code is required');
    }
    if (empty($subjectName)) {
        sendResponse(false, 'Subject name is required');
    }

    try {
        $db->beginTransaction();

        // Check if subject code already exists (for this school or system-wide)
        $checkStmt = $db->prepare("
            SELECT id, subject_name FROM academic_subjects
            WHERE subject_code = ? AND (school_id = ? OR school_id IS NULL)
        ");
        $checkStmt->execute([$subjectCode, $schoolId]);
        $existing = $checkStmt->fetch();
        if ($existing) {
            $db->rollBack();
            sendResponse(false, "Subject code '{$subjectCode}' already exists for subject '{$existing['subject_name']}'");
        }

        // Get next display order
        $orderStmt = $db->prepare("SELECT MAX(display_order) FROM academic_subjects WHERE school_id = ? OR school_id IS NULL");
        $orderStmt->execute([$schoolId]);
        $maxOrder = $orderStmt->fetchColumn() ?? 0;

        // Insert subject
        $uuid = generateUUID();
        $stmt = $db->prepare("
            INSERT INTO academic_subjects
            (uuid, subject_code, subject_name, short_name, subject_type, description,
             max_marks, pass_marks, color_code, display_order, school_id)
            VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?)
        ");
        $stmt->execute([
            $uuid, $subjectCode, $subjectName, $shortName ?: substr($subjectName, 0, 10),
            $subjectType, $description, $maxMarks, $passMarks, $colorCode,
            $maxOrder + 1, $schoolId
        ]);
        $subjectId = $db->lastInsertId();

        // Assign to classes if provided
        if (!empty($assignToClasses) && is_array($assignToClasses)) {
            $assignStmt = $db->prepare("
                INSERT INTO academic_class_subjects (uuid, class_id, subject_id, school_id)
                VALUES (?, ?, ?, ?)
                ON DUPLICATE KEY UPDATE updated_at = NOW()
            ");
            foreach ($assignToClasses as $classId) {
                $assignStmt->execute([generateUUID(), $classId, $subjectId, $schoolId]);
            }
        }

        $db->commit();

        sendResponse(true, 'Subject created successfully', [
            'subject' => [
                'id' => (int)$subjectId,
                'uuid' => $uuid,
                'subject_code' => $subjectCode,
                'subject_name' => $subjectName,
                'subject_type' => $subjectType,
                'assigned_classes' => count($assignToClasses)
            ]
        ]);

    } catch (PDOException $e) {
        $db->rollBack();
        sendResponse(false, 'Failed to create subject', null, ['database' => $e->getMessage()]);
    }
}

/**
 * Assign subjects to a class
 */
function assignSubjects($db, $data, $schoolId) {
    $classId = $data['class_id'] ?? null;
    $subjects = $data['subjects'] ?? [];

    if (!$classId) {
        sendResponse(false, 'class_id is required');
    }
    if (empty($subjects) || !is_array($subjects)) {
        sendResponse(false, 'subjects array is required');
    }

    try {
        $db->beginTransaction();

        // Verify class exists
        $classStmt = $db->prepare("SELECT id FROM academic_classes WHERE id = ?");
        $classStmt->execute([$classId]);
        if (!$classStmt->fetch()) {
            $db->rollBack();
            sendResponse(false, 'Class not found');
        }

        // Remove existing assignments for this class (optional - could also merge)
        // $db->prepare("DELETE FROM academic_class_subjects WHERE class_id = ?")->execute([$classId]);

        // Insert/update assignments
        $assignStmt = $db->prepare("
            INSERT INTO academic_class_subjects
            (uuid, class_id, subject_id, is_mandatory, weekly_periods, assigned_teacher_id, school_id)
            VALUES (?, ?, ?, ?, ?, ?, ?)
            ON DUPLICATE KEY UPDATE
                is_mandatory = VALUES(is_mandatory),
                weekly_periods = VALUES(weekly_periods),
                assigned_teacher_id = VALUES(assigned_teacher_id),
                updated_at = NOW()
        ");

        $assigned = 0;
        foreach ($subjects as $subjectData) {
            $subjectId = $subjectData['subject_id'] ?? null;
            if (!$subjectId) continue;

            $isMandatory = $subjectData['is_mandatory'] ?? 1;
            $weeklyPeriods = $subjectData['weekly_periods'] ?? 5;
            $teacherId = $subjectData['teacher_id'] ?? null;

            $assignStmt->execute([
                generateUUID(), $classId, $subjectId,
                $isMandatory, $weeklyPeriods, $teacherId, $schoolId
            ]);
            $assigned++;
        }

        $db->commit();

        sendResponse(true, 'Subjects assigned successfully', [
            'class_id' => (int)$classId,
            'subjects_assigned' => $assigned
        ]);

    } catch (PDOException $e) {
        $db->rollBack();
        sendResponse(false, 'Failed to assign subjects', null, ['database' => $e->getMessage()]);
    }
}

// ============================================================================
// PUT HANDLERS
// ============================================================================

function handlePut($db, $data) {
    $action = $data['action'] ?? '';
    $schoolId = getCurrentSchoolId();

    switch ($action) {
        case 'update_class':
            updateClass($db, $data, $schoolId);
            break;
        case 'update_section':
            updateSection($db, $data, $schoolId);
            break;
        case 'update_subject':
            updateSubject($db, $data, $schoolId);
            break;
        default:
            sendResponse(false, 'Invalid action');
    }
}

/**
 * Update class
 */
function updateClass($db, $data, $schoolId) {
    $id = $data['id'] ?? null;

    if (!$id) {
        sendResponse(false, 'Class ID is required');
    }

    $updates = [];
    $params = [];

    if (isset($data['class_name'])) {
        $updates[] = "class_name = ?";
        $params[] = trim($data['class_name']);
    }
    if (isset($data['numeric_value'])) {
        $updates[] = "numeric_value = ?";
        $params[] = (int)$data['numeric_value'];
    }
    if (isset($data['description'])) {
        $updates[] = "description = ?";
        $params[] = $data['description'];
    }
    if (isset($data['status'])) {
        $updates[] = "status = ?";
        $params[] = $data['status'];
    }
    if (isset($data['display_order'])) {
        $updates[] = "display_order = ?";
        $params[] = (int)$data['display_order'];
    }

    if (empty($updates)) {
        sendResponse(false, 'No fields to update');
    }

    $params[] = $id;

    try {
        $sql = "UPDATE academic_classes SET " . implode(', ', $updates) . " WHERE id = ?";
        $stmt = $db->prepare($sql);
        $stmt->execute($params);

        if ($stmt->rowCount() === 0) {
            sendResponse(false, 'Class not found or no changes made');
        }

        sendResponse(true, 'Class updated successfully');

    } catch (PDOException $e) {
        sendResponse(false, 'Failed to update class', null, ['database' => $e->getMessage()]);
    }
}

/**
 * Update section
 */
function updateSection($db, $data, $schoolId) {
    $id = $data['id'] ?? null;

    if (!$id) {
        sendResponse(false, 'Section ID is required');
    }

    $updates = [];
    $params = [];

    if (isset($data['section_name'])) {
        $updates[] = "section_name = ?";
        $params[] = trim($data['section_name']);
    }
    if (isset($data['capacity'])) {
        $updates[] = "capacity = ?";
        $params[] = (int)$data['capacity'];
    }
    if (array_key_exists('class_teacher_id', $data)) {
        $updates[] = "class_teacher_id = ?";
        $params[] = $data['class_teacher_id'] ?: null;
    }
    if (isset($data['room_number'])) {
        $updates[] = "room_number = ?";
        $params[] = $data['room_number'];
    }
    if (isset($data['status'])) {
        $updates[] = "status = ?";
        $params[] = $data['status'];
    }
    if (isset($data['display_order'])) {
        $updates[] = "display_order = ?";
        $params[] = (int)$data['display_order'];
    }

    if (empty($updates)) {
        sendResponse(false, 'No fields to update');
    }

    $params[] = $id;

    try {
        $sql = "UPDATE academic_sections SET " . implode(', ', $updates) . " WHERE id = ?";
        $stmt = $db->prepare($sql);
        $stmt->execute($params);

        if ($stmt->rowCount() === 0) {
            sendResponse(false, 'Section not found or no changes made');
        }

        sendResponse(true, 'Section updated successfully');

    } catch (PDOException $e) {
        sendResponse(false, 'Failed to update section', null, ['database' => $e->getMessage()]);
    }
}

/**
 * Update subject
 */
function updateSubject($db, $data, $schoolId) {
    $id = $data['id'] ?? null;

    if (!$id) {
        sendResponse(false, 'Subject ID is required');
    }

    $updates = [];
    $params = [];

    if (isset($data['subject_code'])) {
        $updates[] = "subject_code = ?";
        $params[] = strtoupper(trim($data['subject_code']));
    }
    if (isset($data['subject_name'])) {
        $updates[] = "subject_name = ?";
        $params[] = trim($data['subject_name']);
    }
    if (isset($data['short_name'])) {
        $updates[] = "short_name = ?";
        $params[] = trim($data['short_name']);
    }
    if (isset($data['subject_type'])) {
        $updates[] = "subject_type = ?";
        $params[] = $data['subject_type'];
    }
    if (isset($data['description'])) {
        $updates[] = "description = ?";
        $params[] = $data['description'];
    }
    if (isset($data['max_marks'])) {
        $updates[] = "max_marks = ?";
        $params[] = (int)$data['max_marks'];
    }
    if (isset($data['pass_marks'])) {
        $updates[] = "pass_marks = ?";
        $params[] = (int)$data['pass_marks'];
    }
    if (isset($data['color_code'])) {
        $updates[] = "color_code = ?";
        $params[] = $data['color_code'];
    }
    if (isset($data['status'])) {
        $updates[] = "status = ?";
        $params[] = $data['status'];
    }

    if (empty($updates)) {
        sendResponse(false, 'No fields to update');
    }

    $params[] = $id;

    try {
        $sql = "UPDATE academic_subjects SET " . implode(', ', $updates) . " WHERE id = ?";
        $stmt = $db->prepare($sql);
        $stmt->execute($params);

        if ($stmt->rowCount() === 0) {
            sendResponse(false, 'Subject not found or no changes made');
        }

        sendResponse(true, 'Subject updated successfully');

    } catch (PDOException $e) {
        sendResponse(false, 'Failed to update subject', null, ['database' => $e->getMessage()]);
    }
}

// ============================================================================
// DELETE HANDLERS
// ============================================================================

function handleDelete($db, $params) {
    $action = $params['action'] ?? '';
    $schoolId = getCurrentSchoolId();

    switch ($action) {
        case 'delete_class':
            deleteClass($db, $params, $schoolId);
            break;
        case 'delete_section':
            deleteSection($db, $params, $schoolId);
            break;
        case 'delete_subject':
            deleteSubject($db, $params, $schoolId);
            break;
        case 'remove_class_subject':
            removeClassSubject($db, $params, $schoolId);
            break;
        default:
            sendResponse(false, 'Invalid action');
    }
}

/**
 * Delete class (cascades to sections)
 */
function deleteClass($db, $params, $schoolId) {
    $id = $params['id'] ?? null;

    if (!$id) {
        sendResponse(false, 'Class ID is required');
    }

    try {
        // Check if class has students
        $classStmt = $db->prepare("SELECT class_name FROM academic_classes WHERE id = ?");
        $classStmt->execute([$id]);
        $classInfo = $classStmt->fetch(PDO::FETCH_ASSOC);

        if (!$classInfo) {
            sendResponse(false, 'Class not found');
        }

        $studentStmt = $db->prepare("SELECT COUNT(*) FROM students WHERE class = ?");
        $studentStmt->execute([$classInfo['class_name']]);
        $studentCount = $studentStmt->fetchColumn();

        if ($studentCount > 0) {
            sendResponse(false, "Cannot delete class with $studentCount students. Please reassign students first.");
        }

        // Delete class (sections will cascade)
        $deleteStmt = $db->prepare("DELETE FROM academic_classes WHERE id = ?");
        $deleteStmt->execute([$id]);

        sendResponse(true, 'Class deleted successfully');

    } catch (PDOException $e) {
        sendResponse(false, 'Failed to delete class', null, ['database' => $e->getMessage()]);
    }
}

/**
 * Delete section
 */
function deleteSection($db, $params, $schoolId) {
    $id = $params['id'] ?? null;

    if (!$id) {
        sendResponse(false, 'Section ID is required');
    }

    try {
        // Check if section has students
        $sectionStmt = $db->prepare("
            SELECT s.section_name, c.class_name
            FROM academic_sections s
            INNER JOIN academic_classes c ON c.id = s.class_id
            WHERE s.id = ?
        ");
        $sectionStmt->execute([$id]);
        $sectionInfo = $sectionStmt->fetch(PDO::FETCH_ASSOC);

        if (!$sectionInfo) {
            sendResponse(false, 'Section not found');
        }

        $studentStmt = $db->prepare("SELECT COUNT(*) FROM students WHERE class = ? AND section = ?");
        $studentStmt->execute([$sectionInfo['class_name'], $sectionInfo['section_name']]);
        $studentCount = $studentStmt->fetchColumn();

        if ($studentCount > 0) {
            sendResponse(false, "Cannot delete section with $studentCount students. Please reassign students first.");
        }

        // Delete section
        $deleteStmt = $db->prepare("DELETE FROM academic_sections WHERE id = ?");
        $deleteStmt->execute([$id]);

        sendResponse(true, 'Section deleted successfully');

    } catch (PDOException $e) {
        sendResponse(false, 'Failed to delete section', null, ['database' => $e->getMessage()]);
    }
}

/**
 * Delete subject
 */
function deleteSubject($db, $params, $schoolId) {
    $id = $params['id'] ?? null;

    if (!$id) {
        sendResponse(false, 'Subject ID is required');
    }

    try {
        // Delete subject (class mappings will cascade)
        $deleteStmt = $db->prepare("DELETE FROM academic_subjects WHERE id = ?");
        $deleteStmt->execute([$id]);

        if ($deleteStmt->rowCount() === 0) {
            sendResponse(false, 'Subject not found');
        }

        sendResponse(true, 'Subject deleted successfully');

    } catch (PDOException $e) {
        sendResponse(false, 'Failed to delete subject', null, ['database' => $e->getMessage()]);
    }
}

/**
 * Remove subject from class
 */
function removeClassSubject($db, $params, $schoolId) {
    $classId = $params['class_id'] ?? null;
    $subjectId = $params['subject_id'] ?? null;

    if (!$classId || !$subjectId) {
        sendResponse(false, 'class_id and subject_id are required');
    }

    try {
        $deleteStmt = $db->prepare("DELETE FROM academic_class_subjects WHERE class_id = ? AND subject_id = ?");
        $deleteStmt->execute([$classId, $subjectId]);

        if ($deleteStmt->rowCount() === 0) {
            sendResponse(false, 'Class-subject mapping not found');
        }

        sendResponse(true, 'Subject removed from class successfully');

    } catch (PDOException $e) {
        sendResponse(false, 'Failed to remove class subject', null, ['database' => $e->getMessage()]);
    }
}
?>
